<?php
/**
 * Client / Patient Dashboard
 */

$pageTitle = 'Patient Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/subscription.php';

requireRole(ROLE_CLIENT);
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$clientId = $currentUser['client_id'];

$db = Database::getConnection();

// Fetch Client Profile
$cStmt = $db->prepare("SELECT c.*, u.First_Name, u.Last_Name, u.Email, u.Phone FROM `CLIENT` c JOIN `USER` u ON c.User_ID = u.User_ID WHERE c.Client_ID = ?");
$cStmt->execute([$clientId]);
$client = $cStmt->fetch();

// Check Subscription & Quota
$hasSub = hasActiveSubscription($userId);
$activeSub = getUserActiveSubscription($userId);
$quota = getMonthlyBookingQuota($clientId, $userId);

// Fetch Upcoming Appointments (Booked / Confirmed)
$upcomingStmt = $db->prepare("
    SELECT a.Appointment_ID, a.Booking_DateTime, a.Status AS Appt_Status, a.Notes,
           s.Slot_Date, s.Start_Time, s.End_Time,
           p.Provider_ID, p.Provider_Type, p.Business_Name, p.Address AS Provider_Address, p.City AS Provider_City, p.Contact_Number,
           doc_u.First_Name AS Doc_First, doc_u.Last_Name AS Doc_Last, d.Medical_License_No,
           hc.Centre_Name
    FROM `APPOINTMENT` a
    JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID
    JOIN `PROVIDER` p ON s.Provider_ID = p.Provider_ID
    LEFT JOIN `DOCTOR` d ON s.Doctor_ID = d.Doctor_ID
    LEFT JOIN `PROVIDER` dp ON d.Provider_ID = dp.Provider_ID
    LEFT JOIN `USER` doc_u ON dp.User_ID = doc_u.User_ID
    LEFT JOIN `HEALTHCARE_CENTRE` hc ON p.Provider_ID = hc.Provider_ID
    WHERE a.Client_ID = ?
      AND s.Slot_Date >= CURRENT_DATE
      AND a.Status IN ('BOOKED', 'CONFIRMED')
    ORDER BY s.Slot_Date ASC, s.Start_Time ASC
    LIMIT 5
");
$upcomingStmt->execute([$clientId]);
$upcomingAppointments = $upcomingStmt->fetchAll();

// Fetch Specializations for Quick Search
$specializations = $db->query("SELECT * FROM `SPECIALIZATION` ORDER BY Name ASC")->fetchAll();
$cities = getSriLankanCities();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <!-- Welcome Banner -->
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><?= getTimeBasedGreeting() ?>, <?= e($client['First_Name']) ?>!</h2>
      <p class="text-muted small mb-0">
        <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($client['City']) ?>, Sri Lanka • 
        <span>Patient ID: #CL-<?= str_pad($clientId, 4, '0', STR_PAD_LEFT) ?></span>
      </p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= url('client/search.php') ?>" class="btn btn-teal">
        <i class="bi bi-search me-1"></i> Find Doctors
      </a>
      <a href="<?= url('client/appointments.php') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-calendar-check me-1"></i> My Appointments
      </a>
    </div>
  </div>

  <!-- Subscription Status Banner / Warning if Expired -->
  <?php if (!$hasSub): ?>
    <div class="alert alert-warning border-warning d-flex justify-content-between align-items-center shadow-sm mb-4 p-3 rounded-3" role="alert">
      <div>
        <i class="bi bi-exclamation-triangle-fill fs-4 text-warning me-2"></i>
        <strong>Subscription Expired / Inactive:</strong> You need an active subscription plan to search providers within your GPS radius and book appointments.
      </div>
      <a href="<?= url('client/subscription.php') ?>" class="btn btn-teal btn-sm fw-bold">Choose a Plan Now</a>
    </div>
  <?php endif; ?>

  <!-- Subscription & Quota Card -->
  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <div class="card card-custom p-4 h-100">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <span class="badge <?= $hasSub ? 'bg-success' : 'bg-danger' ?> mb-2">
              <?= $hasSub ? 'ACTIVE MEMBERSHIP' : 'NO ACTIVE PLAN' ?>
            </span>
            <h4 class="fw-bold mb-0 text-teal"><?= e($quota['plan_name']) ?></h4>
          </div>
          <a href="<?= url('client/subscription.php') ?>" class="btn btn-outline-teal btn-sm">
            <?= $hasSub ? 'Manage / Renew' : 'Subscribe' ?>
          </a>
        </div>

        <?php if ($hasSub && $activeSub): ?>
          <div class="row g-2 mb-3 text-center">
            <div class="col-4">
              <div class="bg-light p-2 rounded-3 border">
                <small class="text-muted d-block">Search Radius</small>
                <strong class="text-teal"><?= $activeSub['Search_Radius_KM'] ?> km</strong>
              </div>
            </div>
            <div class="col-4">
              <div class="bg-light p-2 rounded-3 border">
                <small class="text-muted d-block">Monthly Allowance</small>
                <strong><?= $quota['limit'] ?> Bookings</strong>
              </div>
            </div>
            <div class="col-4">
              <div class="bg-light p-2 rounded-3 border">
                <small class="text-muted d-block">Days Remaining</small>
                <strong class="text-dark"><?= $quota['days_remaining'] ?> Days</strong>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Monthly Quota Meter -->
        <div class="pt-2">
          <div class="d-flex justify-content-between small fw-semibold mb-1">
            <span>Monthly Booking Quota (<?= date('F Y') ?>):</span>
            <span class="text-teal fw-bold"><?= $quota['used'] ?> of <?= $quota['limit'] ?> used (<?= $quota['remaining'] ?> remaining)</span>
          </div>
          <div class="progress quota-progress mb-2">
            <?php 
              $pct = ($quota['limit'] > 0) ? min(100, round(($quota['used'] / $quota['limit']) * 100)) : 0;
              $barCls = ($pct >= 90) ? 'bg-danger' : (($pct >= 70) ? 'bg-warning' : 'bg-teal');
            ?>
            <div class="progress-bar <?= $barCls ?>" role="progressbar" style="width: <?= $pct ?>%;"></div>
          </div>
          <small class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-info-circle"></i> Booking quota resets on the 1st of every month.</small>
        </div>
      </div>
    </div>

    <!-- Quick Search Card -->
    <div class="col-lg-5">
      <div class="card card-custom p-4 bg-light h-100 border-0">
        <h5 class="fw-bold mb-3 text-teal"><i class="bi bi-search me-2"></i> Find Nearby Care</h5>
        <form action="<?= url('client/search.php') ?>" method="GET">
          <div class="mb-2">
            <label class="form-label small fw-semibold">Specialization</label>
            <select name="specialization_id" class="form-select">
              <option value="">All Specializations</option>
              <?php foreach ($specializations as $sp): ?>
                <option value="<?= $sp['Specialization_ID'] ?>"><?= e($sp['Name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">City</label>
            <select name="city" class="form-select">
              <option value="">All Sri Lankan Cities</option>
              <?php foreach (array_keys($cities) as $cName): ?>
                <option value="<?= $cName ?>" <?= ($client['City'] === $cName) ? 'selected' : '' ?>><?= $cName ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <button type="submit" class="btn btn-teal w-100 py-2 fw-semibold">
            <i class="bi bi-geo-alt-fill me-1"></i> Search Within My Radius (<?= $hasSub ? $activeSub['Search_Radius_KM'] . 'km' : '5km' ?>)
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Upcoming Appointments -->
  <div class="card card-custom p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0"><i class="bi bi-calendar-event text-teal me-2"></i> Your Upcoming Appointments</h5>
      <a href="<?= url('client/appointments.php') ?>" class="btn btn-outline-secondary btn-sm">Full History</a>
    </div>

    <?php if (empty($upcomingAppointments)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-calendar-plus fs-1 d-block mb-2 text-secondary"></i>
        <p class="mb-2">You have no upcoming appointments scheduled.</p>
        <a href="<?= url('client/search.php') ?>" class="btn btn-teal btn-sm">Find Doctors & Book</a>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Doctor / Healthcare Centre</th>
              <th>Date & Time</th>
              <th>Location</th>
              <th>Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($upcomingAppointments as $ua): ?>
              <tr>
                <td>
                  <?php if ($ua['Doc_First']): ?>
                    <div class="fw-bold">Dr. <?= e($ua['Doc_First'] . ' ' . $ua['Doc_Last']) ?></div>
                    <small class="text-muted"><?= e($ua['Business_Name']) ?></small>
                  <?php else: ?>
                    <div class="fw-bold"><?= e($ua['Centre_Name'] ?: $ua['Business_Name']) ?></div>
                    <small class="text-muted">Healthcare Facility Consultation</small>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="fw-semibold text-teal"><?= formatDate($ua['Slot_Date']) ?></div>
                  <small class="text-muted"><?= formatTime($ua['Start_Time']) ?> – <?= formatTime($ua['End_Time']) ?></small>
                </td>
                <td>
                  <small><i class="bi bi-geo-alt"></i> <?= e($ua['Provider_Address']) ?>, <?= e($ua['Provider_City']) ?></small>
                </td>
                <td><?= renderStatusBadge($ua['Appt_Status']) ?></td>
                <td class="text-end">
                  <a href="<?= url('client/appointments.php') ?>" class="btn btn-outline-teal btn-sm">
                    View / Cancel
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
