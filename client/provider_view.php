<?php
/**
 * Provider Details & Interactive Slot Booking Calendar
 */

$pageTitle = 'Provider Profile & Booking';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/subscription.php';

$providerId = (int)($_GET['id'] ?? 0);
if ($providerId <= 0) {
    setFlash('danger', 'Invalid provider requested.');
    redirect('client/search.php');
}

$db = Database::getConnection();

// Fetch Provider details
$pStmt = $db->prepare("
    SELECT p.*, u.First_Name, u.Last_Name, u.Email
    FROM `PROVIDER` p
    JOIN `USER` u ON p.User_ID = u.User_ID
    WHERE p.Provider_ID = ? AND p.Verification_Status = 'VERIFIED' AND u.Account_Status = 'ACTIVE'
");
$pStmt->execute([$providerId]);
$provider = $pStmt->fetch();

if (!$provider) {
    setFlash('danger', 'Provider not found or currently inactive.');
    redirect('client/search.php');
}

$providerType = $provider['Provider_Type'];
$doctor = null;
$centre = null;
$specializations = [];
$affiliatedDoctors = [];
$affiliatedCentres = [];

if ($providerType === PROVIDER_DOCTOR) {
    $dStmt = $db->prepare("SELECT * FROM `DOCTOR` WHERE Provider_ID = ?");
    $dStmt->execute([$providerId]);
    $doctor = $dStmt->fetch();

    if ($doctor) {
        $sStmt = $db->prepare("
            SELECT s.Name, s.Description
            FROM `DOCTOR_SPECIALIZATION` ds
            JOIN `SPECIALIZATION` s ON ds.Specialization_ID = s.Specialization_ID
            WHERE ds.Doctor_ID = ?
        ");
        $sStmt->execute([$doctor['Doctor_ID']]);
        $specializations = $sStmt->fetchAll();

        // Check centres where doctor consults
        $cStmt = $db->prepare("
            SELECT hc.Centre_Name, p.City, p.Address, p.Provider_ID
            FROM `CENTRE_DOCTOR_LINK` cdl
            JOIN `HEALTHCARE_CENTRE` hc ON cdl.Centre_ID = hc.Centre_ID
            JOIN `PROVIDER` p ON hc.Provider_ID = p.Provider_ID
            WHERE cdl.Doctor_ID = ? AND cdl.Status = 'ACTIVE'
        ");
        $cStmt->execute([$doctor['Doctor_ID']]);
        $affiliatedCentres = $cStmt->fetchAll();
    }
} else { // HEALTHCARE_CENTRE
    $cStmt = $db->prepare("SELECT * FROM `HEALTHCARE_CENTRE` WHERE Provider_ID = ?");
    $cStmt->execute([$providerId]);
    $centre = $cStmt->fetch();

    if ($centre) {
        $adStmt = $db->prepare("
            SELECT d.Doctor_ID, d.Medical_License_No, u.First_Name, u.Last_Name,
                   GROUP_CONCAT(s.Name SEPARATOR ', ') AS Specializations
            FROM `CENTRE_DOCTOR_LINK` cdl
            JOIN `DOCTOR` d ON cdl.Doctor_ID = d.Doctor_ID
            JOIN `PROVIDER` p ON d.Provider_ID = p.Provider_ID
            JOIN `USER` u ON p.User_ID = u.User_ID
            LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON d.Doctor_ID = ds.Doctor_ID
            LEFT JOIN `SPECIALIZATION` s ON ds.Specialization_ID = s.Specialization_ID
            WHERE cdl.Centre_ID = ? AND cdl.Status = 'ACTIVE'
            GROUP BY d.Doctor_ID
        ");
        $adStmt->execute([$centre['Centre_ID']]);
        $affiliatedDoctors = $adStmt->fetchAll();
    }
}

// Fetch Available Slots grouped by Date (Today + next 14 days)
$slotsStmt = $db->prepare("
    SELECT s.*, 
           u.First_Name AS Doc_First, u.Last_Name AS Doc_Last, d.Medical_License_No
    FROM `SCHEDULED_SLOT` s
    LEFT JOIN `DOCTOR` d ON s.Doctor_ID = d.Doctor_ID
    LEFT JOIN `PROVIDER` p ON d.Provider_ID = p.Provider_ID
    LEFT JOIN `USER` u ON p.User_ID = u.User_ID
    WHERE s.Provider_ID = ?
      AND s.Slot_Date >= CURRENT_DATE
      AND s.Slot_Date <= DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY)
      AND s.Status = 'AVAILABLE'
    ORDER BY s.Slot_Date ASC, s.Start_Time ASC
");
$slotsStmt->execute([$providerId]);
$allAvailableSlots = $slotsStmt->fetchAll();

// Group slots by date
$slotsByDate = [];
foreach ($allAvailableSlots as $slot) {
    $slotsByDate[$slot['Slot_Date']][] = $slot;
}

// Subscription & Quota Check for Client
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'] ?? null;
$clientId = $currentUser['client_id'] ?? null;
$hasSub = hasActiveSubscription($userId);
$activeSub = getUserActiveSubscription($userId);
$quota = $clientId ? getMonthlyBookingQuota($clientId, $userId) : null;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= url('public/index.php') ?>">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= url('client/search.php') ?>">Search</a></li>
      <li class="breadcrumb-item active"><?= e($provider['Business_Name']) ?></li>
    </ol>
  </nav>

  <!-- Provider Header Card -->
  <div class="card card-custom p-4 p-md-5 mb-4 shadow-sm">
    <div class="row align-items-center g-4">
      <div class="col-md-8">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge <?= $providerType === 'DOCTOR' ? 'bg-teal text-white' : 'bg-primary text-white' ?> py-2 px-3">
            <i class="bi <?= $providerType === 'DOCTOR' ? 'bi-person-badge' : 'bi-hospital' ?> me-1"></i>
            <?= $providerType === 'DOCTOR' ? 'Specialist Medical Practitioner' : 'Healthcare Facility' ?>
          </span>
          <?= renderStatusBadge($provider['Verification_Status']) ?>
        </div>

        <?php if ($providerType === 'DOCTOR'): ?>
          <h2 class="fw-bold mb-1">Dr. <?= e($provider['First_Name'] . ' ' . $provider['Last_Name']) ?></h2>
          <div class="text-teal fw-semibold mb-3">
            <i class="bi bi-patch-check-fill text-success me-1"></i> <?= e($doctor['Medical_License_No']) ?> • <?= $doctor['Experience_Years'] ?> Years Clinical Experience • <?= $doctor['Consultation_Duration'] ?> mins / consult
          </div>
        <?php else: ?>
          <h2 class="fw-bold mb-1"><?= e($centre['Centre_Name'] ?: $provider['Business_Name']) ?></h2>
          <div class="text-primary fw-semibold mb-3">
            <i class="bi bi-patch-check-fill text-success me-1"></i> Registration: <?= e($centre['Registration_No']) ?>
          </div>
        <?php endif; ?>

        <p class="text-secondary small mb-3">
          <i class="bi bi-geo-alt-fill text-danger me-1"></i> <strong>Location:</strong> <?= e($provider['Address']) ?>, <?= e($provider['City']) ?>, Sri Lanka<br>
          <i class="bi bi-telephone-fill text-success me-1"></i> <strong>Direct Clinic Contact:</strong> <?= e($provider['Contact_Number']) ?>
        </p>

        <?php if (!empty($specializations)): ?>
          <div class="mb-3">
            <strong class="small text-muted d-block mb-1">Specialties & Medical Fields:</strong>
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ($specializations as $sp): ?>
                <span class="badge bg-light text-teal border p-2"><i class="bi bi-award me-1"></i> <?= e($sp['Name']) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <p class="text-muted small mb-0">
          <?= nl2br(e($providerType === 'DOCTOR' ? ($doctor['Professional_Bio'] ?: $provider['Description']) : ($centre['Description'] ?: $provider['Description']))) ?>
        </p>
      </div>

      <div class="col-md-4 text-md-end">
        <div class="bg-light p-3 rounded-3 border text-start">
          <h6 class="fw-bold text-teal mb-2"><i class="bi bi-shield-check me-1"></i> Booking Guarantee</h6>
          <p class="small text-muted mb-2">
            Locked appointment with SMS/portal confirmation. Consultation fees are settled at the clinical counter.
          </p>
          <?php if (!$currentUser): ?>
            <a href="<?= url('public/login.php') ?>" class="btn btn-teal w-100 btn-sm">Sign In to Book</a>
          <?php elseif ($currentUser['role'] !== ROLE_CLIENT): ?>
            <span class="badge bg-secondary w-100 p-2">Client Portal Only</span>
          <?php elseif (!$hasSub): ?>
            <a href="<?= url('client/subscription.php') ?>" class="btn btn-warning w-100 btn-sm text-dark fw-bold">Active Subscription Required</a>
          <?php elseif (!$quota['has_quota']): ?>
            <span class="badge bg-danger w-100 p-2">Monthly Quota Exceeded (<?= $quota['used'] ?>/<?= $quota['limit'] ?>)</span>
          <?php else: ?>
            <div class="badge bg-success-subtle text-success border border-success-subtle w-100 p-2">
              <i class="bi bi-check-circle-fill me-1"></i> Subscription Active (<?= $quota['remaining'] ?> bookings left)
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Clinic Location & Interactive Google Map -->
  <div class="card card-custom p-4 mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
      <div>
        <h5 class="fw-bold mb-0 text-teal"><i class="bi bi-map-fill me-2"></i> Clinic Location & Google Maps</h5>
        <small class="text-muted"><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($provider['Address']) ?>, <?= e($provider['City']) ?> (Coordinates: <?= round($provider['Latitude'], 5) ?>, <?= round($provider['Longitude'], 5) ?>)</small>
      </div>
      <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $provider['Latitude'] ?>,<?= $provider['Longitude'] ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-teal btn-sm">
        <i class="bi bi-arrow-up-right-circle me-1"></i> Get Directions on Google Maps
      </a>
    </div>

    <!-- Embedded Google Map Frame (Free, No API Key Required) -->
    <div class="rounded-3 overflow-hidden border shadow-sm" style="height: 300px;">
      <iframe 
        width="100%" 
        height="100%" 
        style="border:0;" 
        loading="lazy" 
        allowfullscreen 
        referrerpolicy="no-referrer-when-downgrade"
        src="https://maps.google.com/maps?q=<?= urlencode($provider['Latitude'] . ',' . $provider['Longitude']) ?>&hl=en&z=15&output=embed">
      </iframe>
    </div>
  </div>

  <!-- Affiliated Centre or Affiliated Doctors Section -->
  <?php if ($providerType === 'DOCTOR' && !empty($affiliatedCentres)): ?>
    <div class="card card-custom p-4 mb-4">
      <h5 class="fw-bold mb-3 text-teal"><i class="bi bi-hospital me-2"></i> Also Consults at Partner Healthcare Centres</h5>
      <div class="row g-3">
        <?php foreach ($affiliatedCentres as $ac): ?>
          <div class="col-md-6">
            <div class="p-3 border rounded-3 bg-light d-flex justify-content-between align-items-center">
              <div>
                <h6 class="fw-bold mb-1"><?= e($ac['Centre_Name']) ?></h6>
                <small class="text-muted"><i class="bi bi-geo-alt"></i> <?= e($ac['Address']) ?>, <?= e($ac['City']) ?></small>
              </div>
              <a href="<?= url('client/provider_view.php?id=' . $ac['Provider_ID']) ?>" class="btn btn-outline-teal btn-sm">View Centre</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php elseif ($providerType === 'HEALTHCARE_CENTRE' && !empty($affiliatedDoctors)): ?>
    <div class="card card-custom p-4 mb-4">
      <h5 class="fw-bold mb-3 text-teal"><i class="bi bi-people-fill me-2"></i> Consulting Medical Specialists at this Centre</h5>
      <div class="row g-3">
        <?php foreach ($affiliatedDoctors as $ad): ?>
          <div class="col-md-6">
            <div class="p-3 border rounded-3 bg-light">
              <h6 class="fw-bold mb-1">Dr. <?= e($ad['First_Name'] . ' ' . $ad['Last_Name']) ?></h6>
              <div class="small text-teal fw-semibold mb-1"><?= e($ad['Specializations'] ?: 'Consultant') ?></div>
              <small class="text-muted"><i class="bi bi-patch-check"></i> SLMC: <?= e($ad['Medical_License_No']) ?></small>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Available Slots Schedule Calendar Grid -->
  <div class="card card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-calendar-week text-teal me-2"></i> Available Appointment Slots</h4>
        <p class="text-muted small mb-0">Select an open time slot below to proceed with your booking reservation.</p>
      </div>
      <span class="badge bg-light text-dark border p-2">
        <i class="bi bi-calendar3 me-1"></i> Next 14 Days
      </span>
    </div>

    <?php if (empty($slotsByDate)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
        <h5 class="fw-bold">No Open Slots Available</h5>
        <p class="small text-muted max-w-500 mx-auto">
          This provider currently has no available schedule slots open for online booking. Please check back soon or try searching for alternative specialists nearby.
        </p>
        <a href="<?= url('client/search.php') ?>" class="btn btn-outline-teal btn-sm mt-2">Find Other Specialists</a>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($slotsByDate as $date => $daySlots): ?>
          <div class="col-md-6 col-lg-4">
            <div class="card h-100 border rounded-3 p-3 bg-white shadow-sm">
              <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <span class="fw-bold text-teal"><?= formatDate($date) ?></span>
                <span class="badge bg-light text-muted border"><?= date('l', strtotime($date)) ?></span>
              </div>

              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($daySlots as $s): ?>
                  <?php 
                    $docLabel = $s['Doc_First'] ? 'Dr. ' . $s['Doc_First'] . ' ' . $s['Doc_Last'] : ($providerType === 'DOCTOR' ? 'Dr. ' . $provider['First_Name'] . ' ' . $provider['Last_Name'] : $provider['Business_Name']);
                    $timeLabel = formatTime($s['Start_Time']) . ' – ' . formatTime($s['End_Time']);
                  ?>
                  
                  <?php if (!$currentUser || $currentUser['role'] !== ROLE_CLIENT): ?>
                    <a href="<?= url('public/login.php') ?>" class="slot-badge">
                      <i class="bi bi-clock me-1"></i> <?= $timeLabel ?>
                    </a>
                  <?php elseif (!$hasSub || ($quota && !$quota['has_quota'])): ?>
                    <button type="button" class="slot-badge text-muted" onclick="alert('An active subscription with available monthly booking quota is required to book slots.');">
                      <i class="bi bi-lock me-1"></i> <?= $timeLabel ?>
                    </button>
                  <?php else: ?>
                    <button type="button" class="slot-badge" data-bs-toggle="modal" data-bs-target="#bookingModal"
                            data-slot-id="<?= $s['Slot_ID'] ?>"
                            data-slot-date="<?= formatDate($s['Slot_Date']) ?>"
                            data-slot-time="<?= $timeLabel ?>"
                            data-doctor-name="<?= e($docLabel) ?>"
                            data-provider-name="<?= e($provider['Business_Name']) ?>">
                      <i class="bi bi-clock-fill text-teal me-1"></i> <?= $timeLabel ?>
                    </button>
                  <?php endif; ?>

                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Transaction-Safe Booking Confirmation Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <form method="POST" action="<?= url('client/book.php') ?>">
        <?= CSRF::inputField() ?>
        <input type="hidden" name="slot_id" id="modal-slot-id" value="">
        <input type="hidden" name="provider_id" value="<?= $providerId ?>">

        <div class="modal-header bg-teal text-white">
          <h5 class="modal-title fw-bold" id="bookingModalLabel">
            <i class="bi bi-calendar-check-fill me-2"></i> Confirm Appointment Booking
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <div class="bg-light p-3 rounded-3 border mb-3">
            <div class="mb-2">
              <small class="text-muted d-block">Healthcare Provider / Clinic:</small>
              <strong id="modal-provider-name" class="text-dark"></strong>
            </div>
            <div class="mb-2">
              <small class="text-muted d-block">Consulting Doctor:</small>
              <strong id="modal-doctor-name" class="text-teal"></strong>
            </div>
            <div class="row g-2">
              <div class="col-6">
                <small class="text-muted d-block">Appointment Date:</small>
                <strong id="modal-slot-date"></strong>
              </div>
              <div class="col-6">
                <small class="text-muted d-block">Time Window:</small>
                <strong id="modal-slot-time"></strong>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Patient Symptoms / Notes (Optional)</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Briefly state your symptoms, reason for consultation, or follow-up notes..."></textarea>
          </div>

          <div class="alert alert-info small mb-0">
            <i class="bi bi-info-circle-fill me-1"></i> 
            This reservation locks the slot using real-time database row locking. Consultation charges are settled at the clinical facility.
          </div>
        </div>

        <div class="modal-footer bg-light border-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-teal btn-sm px-4 fw-semibold">
            <i class="bi bi-check-circle-fill me-1"></i> Lock & Reserve Slot
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
