<?php

$pageTitle = 'Find Doctors & Healthcare Centres';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/subscription.php';

$currentUser = getCurrentUser();
$userId = $currentUser['user_id'] ?? null;

$hasSub = hasActiveSubscription($userId);
$activeSub = getUserActiveSubscription($userId);

$db = Database::getConnection();
// Lightweight global stale availability cleanup: search never exposes past availability,
 // even if the provider has not opened the schedule page recently.
$db->exec("UPDATE `SCHEDULED_SLOT` SET Status='BLOCKED' WHERE Slot_Date < CURRENT_DATE AND Status='AVAILABLE'");

$clientLat = DEFAULT_LAT;
$clientLng = DEFAULT_LNG;
$clientCity = DEFAULT_CITY;
$hasSavedLocation = false;

if ($currentUser && $currentUser['role'] === ROLE_CLIENT) {
    $cStmt = $db->prepare(
        "SELECT Latitude, Longitude, City
         FROM `CLIENT`
         WHERE User_ID = ?"
    );

    $cStmt->execute([$userId]);
    $cRow = $cStmt->fetch();

    if (
        $cRow &&
        !empty($cRow['Latitude']) &&
        !empty($cRow['Longitude'])
    ) {
        $clientLat = (float)$cRow['Latitude'];
        $clientLng = (float)$cRow['Longitude'];
        $clientCity = $cRow['City'];
        $hasSavedLocation = true;
    }
}

$allowedRadiusKM = getSearchRadiusKM($userId);

$specId = !empty($_GET['specialization_id'])
    ? (int)$_GET['specialization_id']
    : null;

$cityFilter = trim($_GET['city'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$searchTerm = trim($_GET['q'] ?? '');
$onlyAvailable = !empty($_GET['available']);

// Search modes: an explicit city is manual district mode; GPS mode only uses
// coordinates supplied by the browser after the user presses "Use my location".
$gpsLat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$gpsLng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);
$gpsAccuracy = filter_input(INPUT_GET, 'accuracy', FILTER_VALIDATE_FLOAT);
$gpsMode = (($_GET['mode'] ?? '') === 'gps'
    && $gpsLat !== false && $gpsLat !== null
    && $gpsLng !== false && $gpsLng !== null
    && $gpsLat >= -90 && $gpsLat <= 90
    && $gpsLng >= -180 && $gpsLng <= 180);

if ($gpsMode) {
    $clientLat = (float)$gpsLat;
    $clientLng = (float)$gpsLng;
}

// Nearby radius can be expanded by the user for discovery. A selected city/district
// intentionally switches to district-wide discovery instead of GPS-radius filtering.
$requestedRadius = isset($_GET['radius']) ? (int)$_GET['radius'] : $allowedRadiusKM;
$radiusSteps = [5, 10, 25, 50];
if (!in_array($requestedRadius, $radiusSteps, true)) {
    $requestedRadius = $allowedRadiusKM;
}
$effectiveRadiusKM = max($allowedRadiusKM, $requestedRadius);
$districtWideSearch = ($cityFilter !== '');
$hasDistanceReference = $gpsMode;
$locationMode = $districtWideSearch ? 'manual' : ($gpsMode ? 'gps' : 'none');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT
        p.Provider_ID,
        p.Provider_Type,
        p.Business_Name,
        p.Address,
        p.City,
        p.Latitude,
        p.Longitude,
        p.Contact_Number,
        p.Description,
        p.Verification_Status,

        d.Doctor_ID,
        d.Medical_License_No,
        d.Experience_Years,
        d.Consultation_Duration,

        u.First_Name,
        u.Last_Name,

        hc.Centre_ID,
        hc.Centre_Name,
        hc.Registration_No,

        (
            SELECT GROUP_CONCAT(
                DISTINCT s2.Name
                SEPARATOR ', '
            )
            FROM `DOCTOR_SPECIALIZATION` ds2
            JOIN `SPECIALIZATION` s2
                ON ds2.Specialization_ID = s2.Specialization_ID
            WHERE ds2.Doctor_ID = d.Doctor_ID
        ) AS Specializations,

        (
            SELECT COUNT(*)
            FROM `SCHEDULED_SLOT` sl
            WHERE sl.Provider_ID = p.Provider_ID
              AND sl.Status = 'AVAILABLE'
              AND sl.Slot_Date >= CURRENT_DATE
        ) AS available_slot_count,

        (
            6371 * ACOS(
                LEAST(
                    1.0,
                    GREATEST(
                        -1.0,
                        COS(RADIANS(:clat1))
                        * COS(RADIANS(p.Latitude))
                        * COS(
                            RADIANS(p.Longitude)
                            - RADIANS(:clng)
                        )
                        +
                        SIN(RADIANS(:clat2))
                        * SIN(RADIANS(p.Latitude))
                    )
                )
            )
        ) AS distance_km

    FROM `PROVIDER` p

    JOIN `USER` u
        ON p.User_ID = u.User_ID

    LEFT JOIN `DOCTOR` d
        ON p.Provider_ID = d.Provider_ID

    LEFT JOIN `HEALTHCARE_CENTRE` hc
        ON p.Provider_ID = hc.Provider_ID

    WHERE p.Verification_Status = 'VERIFIED'
      AND u.Account_Status = 'ACTIVE'
      AND EXISTS (
          SELECT 1 FROM `USER_SUBSCRIPTION` pus
          JOIN `SUBSCRIPTION_PLAN` psp ON psp.Plan_ID = pus.Plan_ID
          WHERE pus.User_ID = u.User_ID AND pus.Status = 'ACTIVE'
            AND CURRENT_DATE BETWEEN pus.Start_Date AND pus.End_Date
            AND psp.Status = 'ACTIVE' AND psp.Target_Role IN ('PROVIDER','ALL')
      )
";

$params = [
    ':clat1' => $clientLat,
    ':clng'  => $clientLng,
    ':clat2' => $clientLat
];

if ($searchTerm !== '') {
    $sql .= "
        AND (
            p.Business_Name LIKE :search
            OR u.First_Name LIKE :search
            OR u.Last_Name LIKE :search
            OR hc.Centre_Name LIKE :search
        )
    ";

    $params[':search'] = '%' . $searchTerm . '%';
}

if ($cityFilter !== '') {
    $sql .= " AND p.City = :city";
    $params[':city'] = $cityFilter;
}

if (
    $typeFilter !== '' &&
    in_array(
        $typeFilter,
        ['DOCTOR', 'HEALTHCARE_CENTRE'],
        true
    )
) {
    $sql .= " AND p.Provider_Type = :ptype";
    $params[':ptype'] = $typeFilter;
}

if ($specId) {
    $sql .= "
        AND (
            EXISTS (
                SELECT 1
                FROM `DOCTOR_SPECIALIZATION` ds_self
                WHERE ds_self.Doctor_ID = d.Doctor_ID
                  AND ds_self.Specialization_ID = :spec_id
            )
            OR
            p.Provider_ID IN (
                SELECT d_sub.Provider_ID
                FROM `CENTRE_DOCTOR_LINK` cdl
                JOIN `DOCTOR` d_sub
                    ON cdl.Doctor_ID = d_sub.Doctor_ID
                JOIN `DOCTOR_SPECIALIZATION` ds_sub
                    ON d_sub.Doctor_ID = ds_sub.Doctor_ID
                WHERE ds_sub.Specialization_ID = :spec_id2
                  AND cdl.Status = 'ACTIVE'
            )
        )
    ";

    $params[':spec_id'] = $specId;
    $params[':spec_id2'] = $specId;
}

if ($districtWideSearch || !$gpsMode) {
    // Manual district mode searches the whole area. With no chosen location mode,
    // do not silently use a default coordinate to restrict results.
    $sql .= " HAVING 1=1";
} else {
    $sql .= " HAVING distance_km <= :max_radius";
    $params[':max_radius'] = $effectiveRadiusKM;
}

if ($onlyAvailable) {
    $sql .= " AND available_slot_count > 0";
}

$sql .= $hasDistanceReference ? " ORDER BY distance_km ASC" : " ORDER BY p.City ASC, p.Business_Name ASC";

$countStmt = $db->prepare($sql);
$countStmt->execute($params);

$totalResults = count($countStmt->fetchAll());
$totalPages = max(1, (int)ceil($totalResults / $perPage));

$sql .= "
    LIMIT " . (int)$perPage . "
    OFFSET " . (int)$offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);

$providers = $stmt->fetchAll();

$specializations = $db
    ->query(
        "SELECT *
         FROM `SPECIALIZATION`
         ORDER BY Name ASC"
    )
    ->fetchAll();

$cities = getSriLankanCities();

require_once __DIR__ . '/../includes/header.php';

?>

<?php
$activeFilterCount = 0;
if ($searchTerm !== '') $activeFilterCount++;
if ($specId) $activeFilterCount++;
if ($cityFilter !== '') $activeFilterCount++;
if ($typeFilter !== '') $activeFilterCount++;
if ($onlyAvailable) $activeFilterCount++;

$selectedSpecName = '';
if ($specId) {
    foreach ($specializations as $sp) {
        if ((int)$sp['Specialization_ID'] === (int)$specId) {
            $selectedSpecName = $sp['Name'];
            break;
        }
    }
}

function searchUrlWithout(string $key): string {
    $query = $_GET;
    unset($query[$key], $query['page']);
    $qs = http_build_query($query);
    return url('client/search.php' . ($qs ? '?' . $qs : ''));
}
?>

<?php if (!$currentUser): ?>
<div class="container mt-3">
    <div class="search-guest-note">
        <div class="search-guest-copy">
            <span class="search-icon-box"><i class="bi bi-eye"></i></span>
            <div><strong>Browsing as a guest</strong><small>Explore verified providers now. Sign in when you are ready to view protected details and book.</small></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-teal" href="<?= url('public/login.php') ?>"><i class="bi bi-box-arrow-in-right me-1"></i>Sign in</a>
            <a class="btn btn-sm btn-teal" href="<?= url('public/register.php') ?>"><i class="bi bi-person-plus me-1"></i>Create account</a>
        </div>
    </div>
</div>
<?php endif; ?>

<main class="container py-4 client-search-v2">
    <section class="search-page-head mb-4">
        <div>
            <span class="search-kicker"><i class="bi bi-search-heart"></i> Healthcare discovery</span>
            <h1>Find the right care near you</h1>
            <p><?php if ($districtWideSearch): ?>Browsing verified doctors and healthcare centres across <strong><?= e($cityFilter) ?></strong> district.<?php elseif ($gpsMode): ?>Showing verified providers within <strong><?= e($effectiveRadiusKM) ?> km</strong> of your current GPS location.<?php else: ?>Choose a city/district manually or use your current location to find care.<?php endif; ?></p>
        </div>
        <div class="search-head-meta">
            <span><i class="bi bi-patch-check"></i> Verified providers</span>
            <span><i class="bi bi-geo-alt"></i> <?= $districtWideSearch ? e($cityFilter) . ' district' : ($gpsMode ? e($effectiveRadiusKM) . ' km GPS radius' : 'Choose search mode') ?></span>
        </div>
    </section>

    <?php if (!$hasSub): ?>
        <div class="search-plan-note mb-3">
            <i class="bi bi-info-circle"></i>
            <div><?php if ($districtWideSearch): ?><strong>Browsing <?= e($cityFilter) ?> district.</strong><span>Manual district searches are not limited by your <?= (int)$allowedRadiusKM ?> km nearby radius.</span><?php elseif ($gpsMode): ?><strong>GPS search is using your <?= (int)$effectiveRadiusKM ?> km radius.</strong><span><?= $gpsAccuracy ? 'Location accuracy: approximately ±' . (int)round($gpsAccuracy) . ' m.' : 'Results are ordered from your current location.' ?></span><?php else: ?><strong>Choose how you want to search.</strong><span>Use GPS for nearby care or select a city/district to browse that whole area.</span><?php endif; ?></div>
            <a href="<?= url('client/subscription.php') ?>">View plans <i class="bi bi-arrow-right"></i></a>
        </div>
    <?php endif; ?>

    <button class="btn btn-outline-teal w-100 d-lg-none search-mobile-filter mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#searchFilters" aria-expanded="<?= $activeFilterCount ? 'true' : 'false' ?>">
        <i class="bi bi-sliders2"></i> Filters<?= $activeFilterCount ? ' (' . $activeFilterCount . ')' : '' ?>
    </button>

    <div class="row g-4 align-items-start">
        <aside class="col-lg-3">
            <div class="collapse d-lg-block <?= $activeFilterCount ? 'show' : '' ?>" id="searchFilters">
                <div class="search-filter-panel">
                    <div class="search-filter-title">
                        <div><span>Refine results</span><small><?= $activeFilterCount ? $activeFilterCount . ' active filter' . ($activeFilterCount > 1 ? 's' : '') : 'Choose what matters' ?></small></div>
                        <?php if ($activeFilterCount): ?><a href="<?= url('client/search.php') ?>">Clear</a><?php endif; ?>
                    </div>
                    <div class="search-location-mode mb-3">
                        <button type="button" class="btn btn-teal w-100" id="use-my-location"><i class="bi bi-crosshair me-1"></i>Use my location</button>
                        <small id="gps-status" class="search-gps-status" aria-live="polite"><?= $gpsMode ? 'GPS location active' : 'GPS is used only when you press this button.' ?></small>
                        <div class="search-mode-divider"><span>or choose manually</span></div>
                    </div>
                    <form method="GET" action="<?= url('client/search.php') ?>" class="search-filter-form">
                        <?php if ($gpsMode): ?>
                            <input type="hidden" name="mode" value="gps">
                            <input type="hidden" name="lat" value="<?= e($clientLat) ?>">
                            <input type="hidden" name="lng" value="<?= e($clientLng) ?>">
                            <?php if ($gpsAccuracy): ?><input type="hidden" name="accuracy" value="<?= e($gpsAccuracy) ?>"><?php endif; ?>
                        <?php endif; ?>
                        <div>
                            <label for="search-q"><i class="bi bi-search"></i> Name or keyword</label>
                            <input id="search-q" type="text" name="q" class="form-control" placeholder="Doctor or centre" value="<?= e($searchTerm) ?>">
                        </div>
                        <div>
                            <label for="search-spec"><i class="bi bi-heart-pulse"></i> Specialization</label>
                            <select id="search-spec" name="specialization_id" class="form-select">
                                <option value="">All specializations</option>
                                <?php foreach ($specializations as $sp): ?>
                                    <option value="<?= $sp['Specialization_ID'] ?>" <?= $specId == $sp['Specialization_ID'] ? 'selected' : '' ?>><?= e($sp['Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="search-city"><i class="bi bi-buildings"></i> City / District</label>
                            <select id="search-city" name="city" class="form-select">
                                <option value="">All cities</option>
                                <?php foreach (array_keys($cities) as $cName): ?>
                                    <option value="<?= e($cName) ?>" <?= $cityFilter === $cName ? 'selected' : '' ?>><?= e($cName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="search-type"><i class="bi bi-person-vcard"></i> Provider type</label>
                            <select id="search-type" name="type" class="form-select">
                                <option value="">Doctors & centres</option>
                                <option value="DOCTOR" <?= $typeFilter === 'DOCTOR' ? 'selected' : '' ?>>Doctor</option>
                                <option value="HEALTHCARE_CENTRE" <?= $typeFilter === 'HEALTHCARE_CENTRE' ? 'selected' : '' ?>>Healthcare centre</option>
                            </select>
                        </div>
                        <label class="search-check" for="chk-available">
                            <input class="form-check-input" type="checkbox" name="available" value="1" id="chk-available" <?= $onlyAvailable ? 'checked' : '' ?>>
                            <span><strong>Available slots only</strong><small>Hide providers without upcoming open slots.</small></span>
                        </label>
                        <button type="submit" class="btn btn-teal w-100"><i class="bi bi-search me-1"></i>Show results</button>
                    </form>
                </div>
            </div>
        </aside>

        <section class="col-lg-9">
            <div class="search-results-toolbar">
                <div><strong><?= number_format($totalResults) ?></strong> <span>matching provider<?= $totalResults == 1 ? '' : 's' ?></span></div>
                <span class="search-sort-label"><i class="bi <?= $gpsMode ? 'bi-geo' : 'bi-sort-alpha-down' ?>"></i> <?= $gpsMode ? 'Nearest first' : 'Browse results' ?></span>
            </div>

            <?php if ($activeFilterCount): ?>
                <div class="search-filter-chips mb-3">
                    <?php if ($searchTerm !== ''): ?><a href="<?= searchUrlWithout('q') ?>"><i class="bi bi-search"></i><?= e($searchTerm) ?><i class="bi bi-x-lg"></i></a><?php endif; ?>
                    <?php if ($selectedSpecName !== ''): ?><a href="<?= searchUrlWithout('specialization_id') ?>"><i class="bi bi-heart-pulse"></i><?= e($selectedSpecName) ?><i class="bi bi-x-lg"></i></a><?php endif; ?>
                    <?php if ($cityFilter !== ''): ?><a href="<?= searchUrlWithout('city') ?>"><i class="bi bi-geo-alt"></i><?= e($cityFilter) ?><i class="bi bi-x-lg"></i></a><?php endif; ?>
                    <?php if ($typeFilter !== ''): ?><a href="<?= searchUrlWithout('type') ?>"><i class="bi bi-person-vcard"></i><?= $typeFilter === 'DOCTOR' ? 'Doctors' : 'Healthcare centres' ?><i class="bi bi-x-lg"></i></a><?php endif; ?>
                    <?php if ($onlyAvailable): ?><a href="<?= searchUrlWithout('available') ?>"><i class="bi bi-calendar-check"></i>Available slots<i class="bi bi-x-lg"></i></a><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($providers)): ?>
                <div class="search-empty-v2">
                    <span class="search-empty-icon"><i class="bi bi-search"></i></span>
                    <h2>No matching providers yet</h2>
                    <?php if ($districtWideSearch): ?>
                        <p>No providers matched the selected filters across <?= e($cityFilter) ?>. Try clearing a filter or choose another district.</p>
                    <?php elseif ($gpsMode): ?>
                        <p>No providers matched within <?= (int)$effectiveRadiusKM ?> km. Expand the nearby radius or choose a city/district manually.</p>
                    <?php else: ?>
                        <p>Choose a city/district or use your current location to narrow the results.</p>
                    <?php endif; ?>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="<?= url('client/search.php') ?>" class="btn btn-outline-teal"><i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters</a>
                        <?php if ($gpsMode && !$districtWideSearch): ?>
                            <?php foreach ([10, 25, 50] as $step): if ($step <= $effectiveRadiusKM) continue; ?>
                                <a href="<?= url('client/search.php?' . http_build_query(array_merge($_GET, ['radius' => $step, 'page' => 1]))) ?>" class="btn btn-outline-teal"><i class="bi bi-arrows-angle-expand me-1"></i><?= $step ?> km</a>
                            <?php endforeach; ?>
                            <a href="<?= url('client/search.php?' . http_build_query(array_merge($_GET, ['city' => $clientCity, 'page' => 1]))) ?>" class="btn btn-teal"><i class="bi bi-buildings me-1"></i>Browse <?= e($clientCity) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="search-result-list">
                    <?php foreach ($providers as $p): ?>
                        <article class="provider-result-card">
                            <div class="provider-result-icon <?= $p['Provider_Type'] === 'DOCTOR' ? 'is-doctor' : 'is-centre' ?>">
                                <i class="bi <?= $p['Provider_Type'] === 'DOCTOR' ? 'bi-person-badge' : 'bi-hospital' ?>"></i>
                            </div>
                            <div class="provider-result-body">
                                <div class="provider-result-top">
                                    <div>
                                        <div class="provider-result-type"><i class="bi bi-patch-check-fill"></i><?= $p['Provider_Type'] === 'DOCTOR' ? 'Verified doctor' : 'Verified healthcare centre' ?></div>
                                        <h2><?= $p['Provider_Type'] === 'DOCTOR' ? 'Dr. ' . e($p['First_Name'] . ' ' . $p['Last_Name']) : e($p['Centre_Name'] ?: $p['Business_Name']) ?></h2>
                                    </div>
                                    <?php if ($hasDistanceReference): ?><span class="provider-distance" title="Distance from your current GPS location"><i class="bi bi-geo-alt"></i><?= round((float)$p['distance_km'], 1) ?> km</span><?php endif; ?>
                                </div>
                                <p class="provider-speciality"><i class="bi bi-heart-pulse"></i><?= e($p['Specializations'] ?: 'General consultations') ?></p>
                                <div class="provider-meta-grid">
                                    <span><i class="bi bi-geo"></i><?= e($p['City']) ?></span>
                                    <?php if ($p['Provider_Type'] === 'DOCTOR'): ?>
                                        <span><i class="bi bi-award"></i><?= (int)$p['Experience_Years'] ?> years experience</span>
                                        <?php if (!empty($p['Consultation_Duration'])): ?><span><i class="bi bi-clock"></i><?= (int)$p['Consultation_Duration'] ?> min consultation</span><?php endif; ?>
                                    <?php else: ?>
                                        <span><i class="bi bi-card-checklist"></i><?= e($p['Registration_No']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="provider-result-foot">
                                    <div class="provider-slot-state <?= (int)$p['available_slot_count'] > 0 ? 'has-slots' : 'no-slots' ?>">
                                        <i class="bi <?= (int)$p['available_slot_count'] > 0 ? 'bi-calendar-check' : 'bi-calendar-x' ?>"></i>
                                        <?= (int)$p['available_slot_count'] > 0 ? (int)$p['available_slot_count'] . ' upcoming slot' . ((int)$p['available_slot_count'] === 1 ? '' : 's') : 'No open slots' ?>
                                    </div>
                                    <div class="provider-result-actions">
                                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($p['Latitude'] . ',' . $p['Longitude']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary" aria-label="Directions to provider"><i class="bi bi-map"></i><span>Directions</span></a>
                                        <a href="<?= url('client/provider_view.php?id=' . (int)$p['Provider_ID']) ?>" class="btn btn-sm btn-teal">View profile & slots <i class="bi bi-arrow-right ms-1"></i></a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="search-pagination" aria-label="Search result pages"><ul class="pagination mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $page === $i ? 'active' : '' ?>"><a class="page-link" href="<?= url('client/search.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                    </ul></nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
(function(){
    document.querySelectorAll('.search-filter-form select, .search-filter-form input').forEach(function(el){
        el.addEventListener('change', function(){ document.body.classList.add('search-filter-changed'); });
    });

    const gpsButton = document.getElementById('use-my-location');
    const gpsStatus = document.getElementById('gps-status');
    const citySelect = document.getElementById('search-city');

    if (citySelect) {
        citySelect.addEventListener('change', function(){
            if (!this.value) return;
            // A manual district choice always wins over GPS.
            const form = this.form;
            ['mode','lat','lng','accuracy','radius'].forEach(function(name){
                const input = form.querySelector('[name="' + name + '"]');
                if (input) input.remove();
            });
        });
    }

    if (gpsButton) {
        gpsButton.addEventListener('click', function(){
            if (!navigator.geolocation) {
                gpsStatus.textContent = 'Location is not supported by this browser.';
                return;
            }
            gpsButton.disabled = true;
            gpsStatus.textContent = 'Getting your current location…';

            navigator.geolocation.getCurrentPosition(function(position){
                const accuracy = Math.round(position.coords.accuracy || 0);
                gpsStatus.textContent = accuracy ? 'Location found (±' + accuracy + ' m). Loading nearby care…' : 'Location found. Loading nearby care…';

                const url = new URL(window.location.href);
                url.searchParams.set('mode', 'gps');
                url.searchParams.set('lat', position.coords.latitude.toFixed(7));
                url.searchParams.set('lng', position.coords.longitude.toFixed(7));
                if (accuracy) url.searchParams.set('accuracy', accuracy);
                url.searchParams.delete('city');
                url.searchParams.delete('page');
                url.searchParams.delete('radius');
                window.location.assign(url.toString());
            }, function(error){
                gpsButton.disabled = false;
                const messages = {
                    1: 'Location permission was denied. Choose a city/district manually instead.',
                    2: 'Your location could not be determined. Try again or search manually.',
                    3: 'Location request timed out. Try again or search manually.'
                };
                gpsStatus.textContent = messages[error.code] || 'Could not get your location. Try manual search.';
            }, {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0
            });
        });
    }
})();
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>