<?php
/**
 * Provider Dashboard (Doctor & Healthcare Centre)
 */

$pageTitle = 'Provider Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/subscription.php';

requireRole(ROLE_PROVIDER);
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$providerId = $currentUser['provider_id'];
$providerType = $currentUser['provider_type'];

$db = Database::getConnection();

// Fetch Provider details
$pStmt = $db->prepare("SELECT * FROM `PROVIDER` WHERE Provider_ID = ?");
$pStmt->execute([$providerId]);
$provider = $pStmt->fetch();

// Check Subscription
$hasSub = hasActiveSubscription($userId);
$activeSub = getUserActiveSubscription($userId);

// Fetch Statistics
$statStmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM `SCHEDULED_SLOT` WHERE Provider_ID = ? AND Status = 'AVAILABLE' AND Slot_Date >= CURRENT_DATE) AS available_slots,
        (SELECT COUNT(*) FROM `APPOINTMENT` a JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID WHERE s.Provider_ID = ? AND s.Slot_Date = CURRENT_DATE AND a.Status != 'CANCELLED') AS today_appts,
        (SELECT COUNT(*) FROM `APPOINTMENT` a JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID WHERE s.Provider_ID = ? AND a.Status = 'BOOKED') AS pending_confirmations,
        (SELECT COUNT(*) FROM `APPOINTMENT` a JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID WHERE s.Provider_ID = ? AND a.Status = 'COMPLETED') AS completed_total
");
$statStmt->execute([$providerId, $providerId, $providerId, $providerId]);
$stats = $statStmt->fetch();

// Fetch Upcoming Appointments (Next 10)
$apptStmt = $db->prepare("
    SELECT a.Appointment_ID, a.Booking_DateTime, a.Status, a.Notes,
           s.Slot_Date, s.Start_Time, s.End_Time,
           u.First_Name AS Patient_First, u.Last_Name AS Patient_Last, u.Phone AS Patient_Phone, u.Email AS Patient_Email,
           c.City AS Patient_City,
           doc_u.First_Name AS Doc_First, doc_u.Last_Name AS Doc_Last
    FROM `APPOINTMENT` a
    JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID
    JOIN `CLIENT` c ON a.Client_ID = c.Client_ID
    JOIN `USER` u ON c.User_ID = u.User_ID
    LEFT JOIN `DOCTOR` d ON s.Doctor_ID = d.Doctor_ID
    LEFT JOIN `PROVIDER` doc_p ON d.Provider_ID = doc_p.Provider_ID
    LEFT JOIN `USER` doc_u ON doc_p.User_ID = doc_u.User_ID
    WHERE s.Provider_ID = ?
    ORDER BY s.Slot_Date ASC, s.Start_Time ASC
    LIMIT 10
");
$apptStmt->execute([$providerId]);
$upcomingAppts = $apptStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <!-- Welcome Banner -->
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2">
        <h2 class="fw-bold mb-0"><?= e($provider['Business_Name']) ?></h2>
        <?= renderStatusBadge($provider['Verification_Status']) ?>
      </div>
      <p class="text-muted small mb-0">
        <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($provider['Address']) ?>, <?= e($provider['City']) ?> • 
        <span class="badge bg-light text-dark border"><?= $providerType === 'DOCTOR' ? 'Medical Practitioner' : 'Healthcare Facility' ?></span>
      </p>
    </div>

    <div class="d-flex gap-2">
      <a href="<?= url('provider/slots.php') ?>" class="btn btn-teal">
        <i class="bi bi-calendar-plus me-1"></i> Manage Slots
      </a>
      <a href="<?= url('provider/profile.php') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-gear me-1"></i> Edit Profile
      </a>
    </div>
  </div>

  <!-- Subscription Status Alert if Expired -->
  <?php if (!$hasSub): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center shadow-sm mb-4" role="alert">
      <div>
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
        <strong>Provider Subscription Required:</strong> You need an active provider listing plan to publish schedule slots to patients.
      </div>
      <a href="<?= url('provider/subscription.php') ?>" class="btn btn-dark btn-sm fw-semibold">Renew Subscription Now</a>
    </div>
  <?php endif; ?>

  <!-- KPI Stat Cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="card card-custom p-3 card-stat">
        <div class="small text-muted fw-semibold">Published Available Slots</div>
        <div class="stat-value text-teal"><?= $stats['available_slots'] ?></div>
        <small class="text-muted">Upcoming bookable slots</small>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-custom p-3 card-stat stat-warning">
        <div class="small text-muted fw-semibold">Today's Appointments</div>
        <div class="stat-value text-warning"><?= $stats['today_appts'] ?></div>
        <small class="text-muted"><?= date('d M Y') ?></small>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-custom p-3 card-stat stat-success">
        <div class="small text-muted fw-semibold">Pending Confirmations</div>
        <div class="stat-value text-success"><?= $stats['pending_confirmations'] ?></div>
        <small class="text-muted">Booked by patients</small>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-custom p-3 card-stat">
        <div class="small text-muted fw-semibold">Completed Consultations</div>
        <div class="stat-value text-dark"><?= $stats['completed_total'] ?></div>
        <small class="text-muted">Total history</small>
      </div>
    </div>
  </div>

  <!-- Quick Actions Panel -->
  <div class="row g-4 mb-4">
    <div class="col-lg-4">
      <div class="card card-custom p-4 h-100">
        <h5 class="fw-bold mb-3"><i class="bi bi-lightning-charge text-teal me-2"></i> Provider Shortcuts</h5>
        <div class="d-grid gap-2">
          <a href="<?= url('provider/slots.php?action=create') ?>" class="btn btn-outline-teal text-start py-2">
            <i class="bi bi-plus-circle me-2"></i> Create Single Slot
          </a>
          <a href="<?= url('provider/slots.php?tab=batch') ?>" class="btn btn-outline-teal text-start py-2">
            <i class="bi bi-calendar2-week me-2"></i> Generate Daily Batch Schedule
          </a>
          <a href="<?= url('provider/appointments.php') ?>" class="btn btn-outline-secondary text-start py-2">
            <i class="bi bi-calendar-check me-2"></i> Manage All Appointments
          </a>
          <?php if ($providerType === PROVIDER_CENTRE): ?>
            <a href="<?= url('provider/doctors.php') ?>" class="btn btn-outline-primary text-start py-2">
              <i class="bi bi-people me-2"></i> Manage Affiliated Doctors
            </a>
          <?php endif; ?>
          <a href="<?= url('provider/subscription.php') ?>" class="btn btn-outline-dark text-start py-2">
            <i class="bi bi-credit-card me-2"></i> Subscription Details
          </a>
        </div>
      </div>
    </div>

    <!-- Upcoming Bookings Table -->
    <div class="col-lg-8">
      <div class="card card-custom p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0"><i class="bi bi-calendar3 text-teal me-2"></i> Upcoming Patient Appointments</h5>
          <a href="<?= url('provider/appointments.php') ?>" class="btn btn-outline-secondary btn-sm">View All</a>
        </div>

        <?php if (empty($upcomingAppts)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
            <p class="mb-0">No upcoming appointments found.</p>
            <a href="<?= url('provider/slots.php') ?>" class="btn btn-teal btn-sm mt-3">Publish New Slots</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Schedule</th>
                  <th>Contact</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($upcomingAppts as $ap): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= e($ap['Patient_First'] . ' ' . $ap['Patient_Last']) ?></div>
                      <small class="text-muted"><?= e($ap['Patient_City']) ?></small>
                    </td>
                    <td>
                      <div class="fw-semibold text-teal"><?= formatDate($ap['Slot_Date']) ?></div>
                      <small class="text-muted"><?= formatTime($ap['Start_Time']) ?> – <?= formatTime($ap['End_Time']) ?></small>
                    </td>
                    <td>
                      <small><?= e($ap['Patient_Phone']) ?></small>
                    </td>
                    <td><?= renderStatusBadge($ap['Status']) ?></td>
                    <td class="text-end">
                      <a href="<?= url('provider/appointments.php?id=' . $ap['Appointment_ID']) ?>" class="btn btn-outline-teal btn-sm">
                        Manage
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
