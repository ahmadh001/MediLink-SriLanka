<?php
/**
 * Provider & Doctor Search Engine with Haversine GPS Distance & Subscription Radius Protection
 */

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

// Determine Client Coordinates for Distance Calculation
$clientLat = DEFAULT_LAT;
$clientLng = DEFAULT_LNG;
$clientCity = DEFAULT_CITY;

if ($currentUser && $currentUser['role'] === ROLE_CLIENT) {
    $cStmt = $db->prepare("SELECT Latitude, Longitude, City FROM `CLIENT` WHERE User_ID = ?");
    $cStmt->execute([$userId]);
    $cRow = $cStmt->fetch();
    if ($cRow && !empty($cRow['Latitude']) && !empty($cRow['Longitude'])) {
        $clientLat = floatval($cRow['Latitude']);
        $clientLng = floatval($cRow['Longitude']);
        $clientCity = $cRow['City'];
    }
}

// Allowed search radius strictly retrieved from active subscription plan (Cannot be tampered from frontend)
$allowedRadiusKM = getSearchRadiusKM($userId); // 5km if no sub, or plan radius (10, 25, 60), or 9999 for Admin

// Filter Parameters from GET
$specId = !empty($_GET['specialization_id']) ? (int)$_GET['specialization_id'] : null;
$cityFilter = trim($_GET['city'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$searchTerm = trim($_GET['q'] ?? '');
$onlyAvailable = !empty($_GET['available']);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;
$offset = ($page - 1) * $perPage;

// Base Search Query using Haversine Distance Formula in SQL
$sql = "
    SELECT p.Provider_ID, p.Provider_Type, p.Business_Name, p.Address, p.City, p.Latitude, p.Longitude,
           p.Contact_Number, p.Description, p.Verification_Status,
           d.Doctor_ID, d.Medical_License_No, d.Experience_Years, d.Consultation_Duration,
           u.First_Name, u.Last_Name,
           hc.Centre_ID, hc.Centre_Name, hc.Registration_No,
           GROUP_CONCAT(DISTINCT s.Name SEPARATOR ', ') AS Specializations,
           (SELECT COUNT(*) FROM `SCHEDULED_SLOT` sl WHERE sl.Provider_ID = p.Provider_ID AND sl.Status = 'AVAILABLE' AND sl.Slot_Date >= CURRENT_DATE) AS available_slot_count,
           (6371 * ACOS(
               LEAST(1.0, GREATEST(-1.0, 
                   COS(RADIANS(:clat1)) * COS(RADIANS(p.Latitude)) * COS(RADIANS(p.Longitude) - RADIANS(:clng)) +
                   SIN(RADIANS(:clat2)) * SIN(RADIANS(p.Latitude))
               ))
           )) AS distance_km
    FROM `PROVIDER` p
    JOIN `USER` u ON p.User_ID = u.User_ID
    LEFT JOIN `DOCTOR` d ON p.Provider_ID = d.Provider_ID
    LEFT JOIN `HEALTHCARE_CENTRE` hc ON p.Provider_ID = hc.Provider_ID
    LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON d.Doctor_ID = ds.Doctor_ID
    LEFT JOIN `SPECIALIZATION` s ON ds.Specialization_ID = s.Specialization_ID
    WHERE p.Verification_Status = 'VERIFIED'
      AND u.Account_Status = 'ACTIVE'
";

$params = [
    ':clat1' => $clientLat,
    ':clng'  => $clientLng,
    ':clat2' => $clientLat
];

if (!empty($searchTerm)) {
    $sql .= " AND (p.Business_Name LIKE :search OR u.First_Name LIKE :search OR u.Last_Name LIKE :search OR hc.Centre_Name LIKE :search)";
    $params[':search'] = '%' . $searchTerm . '%';
}

if (!empty($cityFilter)) {
    $sql .= " AND p.City = :city";
    $params[':city'] = $cityFilter;
}

if (!empty($typeFilter) && in_array($typeFilter, ['DOCTOR', 'HEALTHCARE_CENTRE'])) {
    $sql .= " AND p.Provider_Type = :ptype";
    $params[':ptype'] = $typeFilter;
}

if ($specId) {
    $sql .= " AND (ds.Specialization_ID = :spec_id OR p.Provider_ID IN (
        SELECT d_sub.Provider_ID FROM `CENTRE_DOCTOR_LINK` cdl
        JOIN `DOCTOR` d_sub ON cdl.Doctor_ID = d_sub.Doctor_ID
        JOIN `DOCTOR_SPECIALIZATION` ds_sub ON d_sub.Doctor_ID = ds_sub.Doctor_ID
        WHERE ds_sub.Specialization_ID = :spec_id2 AND cdl.Status = 'ACTIVE'
    ))";
    $params[':spec_id'] = $specId;
    $params[':spec_id2'] = $specId;
}

$sql .= " GROUP BY p.Provider_ID";

// Apply Allowed Subscription Search Radius
$sql .= " HAVING distance_km <= :max_radius";
$params[':max_radius'] = $allowedRadiusKM;

if ($onlyAvailable) {
    $sql .= " AND available_slot_count > 0";
}

$sql .= " ORDER BY distance_km ASC";

// Get Total Count for Pagination
$countStmt = $db->prepare($sql);
$countStmt->execute($params);
$totalResults = count($countStmt->fetchAll());
$totalPages = ceil($totalResults / $perPage);

// Add Pagination Limits
$sql .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$providers = $stmt->fetchAll();

// Fetch filter options
$specializations = $db->query("SELECT * FROM `SPECIALIZATION` ORDER BY Name ASC")->fetchAll();
$cities = getSriLankanCities();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <!-- Search Header -->
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-search-heart text-teal me-2"></i> Find Healthcare Providers</h2>
      <p class="text-muted small mb-0">
        Showing verified doctors & centres within your 
        <strong class="text-teal"><?= $allowedRadiusKM >= 1000 ? 'Island-wide' : $allowedRadiusKM . ' km' ?></strong> 
        subscription search radius from <strong><?= e($clientCity) ?></strong> (<?= round($clientLat, 4) ?>, <?= round($clientLng, 4) ?>).
      </p>
    </div>

    <?php if (!$hasSub): ?>
      <div class="badge bg-warning text-dark p-2 border">
        <i class="bi bi-exclamation-circle me-1"></i> Radius capped at 5 km. 
        <a href="<?= url('client/subscription.php') ?>" class="text-dark fw-bold text-decoration-underline ms-1">Upgrade for larger radius</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Search & Filter Form Card -->
  <div class="card card-custom p-4 mb-4 shadow-sm">
    <form method="GET" action="<?= url('client/search.php') ?>" class="row g-3">
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Keywords / Doctor / Hospital Name</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="e.g. Dr. Ruwan or Asiri" value="<?= e($searchTerm) ?>">
        </div>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-semibold">Specialization</label>
        <select name="specialization_id" class="form-select">
          <option value="">All Specializations</option>
          <?php foreach ($specializations as $sp): ?>
            <option value="<?= $sp['Specialization_ID'] ?>" <?= $specId == $sp['Specialization_ID'] ? 'selected' : '' ?>>
              <?= e($sp['Name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-semibold">Sri Lankan City</label>
        <select name="city" class="form-select">
          <option value="">All Cities</option>
          <?php foreach (array_keys($cities) as $cName): ?>
            <option value="<?= $cName ?>" <?= $cityFilter === $cName ? 'selected' : '' ?>><?= $cName ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label small fw-semibold">Provider Type</label>
        <select name="type" class="form-select">
          <option value="">All Types</option>
          <option value="DOCTOR" <?= $typeFilter === 'DOCTOR' ? 'selected' : '' ?>>Doctor</option>
          <option value="HEALTHCARE_CENTRE" <?= $typeFilter === 'HEALTHCARE_CENTRE' ? 'selected' : '' ?>>Healthcare Centre</option>
        </select>
      </div>

      <div class="col-12 d-flex justify-content-between align-items-center pt-2">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="available" value="1" id="chk-available" <?= $onlyAvailable ? 'checked' : '' ?>>
          <label class="form-check-label small" for="chk-available">
            Only show providers with available upcoming slots
          </label>
        </div>

        <div class="d-flex gap-2">
          <a href="<?= url('client/search.php') ?>" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
          <button type="submit" class="btn btn-teal btn-sm px-4 fw-semibold">Apply Search Filters</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Search Results Count -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-muted small">
      Found <strong><?= $totalResults ?></strong> matching provider(s) within your subscription zone.
    </div>
  </div>

  <!-- Providers List Cards -->
  <?php if (empty($providers)): ?>
    <div class="card card-custom p-5 text-center my-4">
      <i class="bi bi-geo-alt-slash fs-1 text-muted mb-3"></i>
      <h4 class="fw-bold">No Providers Found Within Radius</h4>
      <p class="text-muted max-w-500 mx-auto mb-4">
        We could not find any verified healthcare providers matching your filters within your allowed <?= $allowedRadiusKM ?> km radius.
      </p>
      <div class="d-flex gap-2 justify-content-center">
        <a href="<?= url('client/search.php') ?>" class="btn btn-outline-secondary">Reset Search Filters</a>
        <?php if (!$hasSub || ($activeSub && $activeSub['Search_Radius_KM'] < 60)): ?>
          <a href="<?= url('client/subscription.php') ?>" class="btn btn-teal">Upgrade Plan for 25km / 60km Radius</a>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="row g-4 mb-4">
      <?php foreach ($providers as $p): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card card-custom h-100 p-4 d-flex flex-column">
            <!-- Header with Type & Distance -->
            <div class="d-flex justify-content-between align-items-start mb-3">
              <span class="badge <?= $p['Provider_Type'] === 'DOCTOR' ? 'bg-teal text-white' : 'bg-primary text-white' ?>">
                <i class="bi <?= $p['Provider_Type'] === 'DOCTOR' ? 'bi-person-badge' : 'bi-hospital' ?> me-1"></i>
                <?= $p['Provider_Type'] === 'DOCTOR' ? 'Specialist Doctor' : 'Healthcare Centre' ?>
              </span>
              <span class="badge bg-light text-dark border">
                <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= round($p['distance_km'], 1) ?> km away
              </span>
            </div>

            <!-- Provider Name & Credentials -->
            <?php if ($p['Provider_Type'] === 'DOCTOR'): ?>
              <h5 class="fw-bold mb-1">Dr. <?= e($p['First_Name'] . ' ' . $p['Last_Name']) ?></h5>
              <div class="small text-teal fw-semibold mb-2">
                <i class="bi bi-patch-check-fill text-success me-1"></i> <?= e($p['Medical_License_No']) ?> • <?= $p['Experience_Years'] ?> Yrs Exp
              </div>
            <?php else: ?>
              <h5 class="fw-bold mb-1"><?= e($p['Centre_Name'] ?: $p['Business_Name']) ?></h5>
              <div class="small text-primary fw-semibold mb-2">
                <i class="bi bi-patch-check-fill text-success me-1"></i> <?= e($p['Registration_No']) ?>
              </div>
            <?php endif; ?>

            <!-- Specializations -->
            <p class="small text-muted mb-2">
              <i class="bi bi-heart-pulse text-danger me-1"></i>
              <strong>Speciality:</strong> <?= e($p['Specializations'] ?: 'General Consultations') ?>
            </p>

            <div class="small text-secondary mb-3 d-flex justify-content-between align-items-start">
              <span><i class="bi bi-geo-alt me-1"></i> <?= e($p['Address']) ?>, <?= e($p['City']) ?></span>
              <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $p['Latitude'] ?>,<?= $p['Longitude'] ?>" target="_blank" rel="noopener noreferrer" class="text-teal small fw-semibold text-decoration-none ms-2 text-nowrap" title="Directions on Google Maps">
                <i class="bi bi-map-fill me-1"></i> Map
              </a>
            </div>

            <!-- Available Slots Indicator -->
            <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
              <div>
                <?php if ($p['available_slot_count'] > 0): ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-calendar-check me-1"></i> <?= $p['available_slot_count'] ?> Available Slot(s)
                  </span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border">No Open Slots</span>
                <?php endif; ?>
              </div>

              <a href="<?= url('client/provider_view.php?id=' . $p['Provider_ID']) ?>" class="btn btn-teal btn-sm px-3">
                View & Book
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <nav class="d-flex justify-content-center my-4">
        <ul class="pagination">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $page === $i ? 'active' : '' ?>">
              <a class="page-link" href="<?= url('client/search.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>">
                <?= $i ?>
              </a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
