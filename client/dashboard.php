<?php


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


$cStmt = $db->prepare("SELECT c.*, u.First_Name, u.Last_Name, u.Email, u.Phone FROM `CLIENT` c JOIN `USER` u ON c.User_ID = u.User_ID WHERE c.Client_ID = ?");
$cStmt->execute([$clientId]);
$client = $cStmt->fetch();


$hasSub = hasActiveSubscription($userId);
$activeSub = getUserActiveSubscription($userId);
$quota = getMonthlyBookingQuota($clientId, $userId);


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
      AND a.Status IN ('PENDING', 'BOOKED', 'CONFIRMED')
    ORDER BY s.Slot_Date ASC, s.Start_Time ASC
    LIMIT 5
");
$upcomingStmt->execute([$clientId]);
$upcomingAppointments = $upcomingStmt->fetchAll();


$specializations = $db->query("SELECT * FROM `SPECIALIZATION` ORDER BY Name ASC")->fetchAll();
$cities = getSriLankanCities();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5 client-dashboard-v2">
  <section class="client-dash-head mb-4">
    <div>
      <span class="client-dash-kicker"><i class="bi bi-grid-1x2"></i> Patient dashboard</span>
      <h1 class="client-dash-title mb-1"><?= getTimeBasedGreeting() ?>, <?= e($client['First_Name']) ?></h1>
      <p class="client-dash-sub mb-0"><i class="bi bi-geo-alt"></i> <?= e($client['City']) ?>, Sri Lanka <span aria-hidden="true">·</span> Patient ID #CL-<?= str_pad($clientId, 4, '0', STR_PAD_LEFT) ?></p>
    </div>
    <a href="<?= url('client/search.php') ?>" class="btn btn-teal client-dash-primary"><i class="bi bi-search"></i> Find Doctors</a>
  </section>

  <?php if (!$hasSub): ?>
    <div class="client-sub-alert mb-4" role="alert">
      <div class="client-sub-alert-icon"><i class="bi bi-exclamation-circle"></i></div>
      <div class="flex-grow-1"><strong>No active subscription</strong><p class="mb-0">Choose a plan to use plan-based search radius and appointment booking features.</p></div>
      <a href="<?= url('client/subscription.php') ?>" class="btn btn-sm btn-teal">View plans</a>
    </div>
  <?php endif; ?>

  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <section class="client-next-card h-100" aria-labelledby="nextAppointmentTitle">
        <div class="client-card-label"><i class="bi bi-calendar2-check"></i> Next appointment</div>
        <?php if (!empty($upcomingAppointments)): $next = $upcomingAppointments[0]; ?>
          <div class="client-next-main">
            <div class="client-date-block">
              <span><?= strtoupper(date('M', strtotime($next['Slot_Date']))) ?></span>
              <strong><?= date('d', strtotime($next['Slot_Date'])) ?></strong>
            </div>
            <div class="min-w-0">
              <h2 id="nextAppointmentTitle" class="client-next-name"><?= $next['Doc_First'] ? 'Dr. ' . e($next['Doc_First'] . ' ' . $next['Doc_Last']) : e($next['Centre_Name'] ?: $next['Business_Name']) ?></h2>
              <p class="client-next-provider mb-0"><?= e($next['Business_Name']) ?></p>
            </div>
          </div>
          <div class="client-next-meta">
            <span><i class="bi bi-clock"></i><?= formatTime($next['Start_Time']) ?> – <?= formatTime($next['End_Time']) ?></span>
            <span><i class="bi bi-geo-alt"></i><?= e($next['Provider_City']) ?></span>
            <span><i class="bi bi-check-circle"></i><?= e(ucfirst(strtolower($next['Appt_Status']))) ?></span>
          </div>
          <div class="client-next-actions">
            <a href="<?= url('client/appointments.php') ?>" class="btn btn-outline-teal btn-sm"><i class="bi bi-eye"></i> View appointment</a>
            <?php if (!empty($next['Contact_Number'])): ?><a href="tel:<?= e($next['Contact_Number']) ?>" class="btn btn-light btn-sm"><i class="bi bi-telephone"></i> Contact provider</a><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="client-empty-next">
            <div class="client-empty-icon"><i class="bi bi-calendar-plus"></i></div>
            <h2 id="nextAppointmentTitle">No upcoming appointment</h2>
            <p>Your schedule is clear. Search available providers when you need your next appointment.</p>
            <a href="<?= url('client/search.php') ?>" class="btn btn-teal btn-sm"><i class="bi bi-search"></i> Find available care</a>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <div class="col-lg-5">
      <section class="client-plan-card h-100">
        <div class="d-flex justify-content-between align-items-start gap-3">
          <div><div class="client-card-label"><i class="bi bi-wallet2"></i> Plan & quota</div><h2 class="client-plan-name mb-1"><?= e($quota['plan_name']) ?></h2></div>
          <span class="client-plan-state <?= $hasSub ? 'is-active' : 'is-inactive' ?>"><?= $hasSub ? 'Active' : 'Inactive' ?></span>
        </div>
        <div class="client-quota-row"><strong><?= (int)$quota['remaining'] ?></strong><span>bookings remaining this month</span></div>
        <?php $pct = ($quota['limit'] > 0) ? min(100, round(($quota['used'] / $quota['limit']) * 100)) : 0; ?>
        <div class="progress client-quota-progress" role="progressbar" aria-label="Monthly booking quota" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
        <div class="client-plan-stats">
          <div><span>Used</span><strong><?= (int)$quota['used'] ?> / <?= (int)$quota['limit'] ?></strong></div>
          <div><span>Search radius</span><strong><?= ($hasSub && $activeSub) ? e($activeSub['Search_Radius_KM']) . ' km' : '—' ?></strong></div>
          <div><span>Days left</span><strong><?= $hasSub ? (int)$quota['days_remaining'] : '—' ?></strong></div>
        </div>
        <div class="client-plan-foot"><small><i class="bi bi-arrow-repeat"></i> Booking quota resets monthly.</small><a href="<?= url('client/subscription.php') ?>">Manage plan <i class="bi bi-arrow-right"></i></a></div>
      </section>
    </div>
  </div>

  <section class="client-quick-section mb-4">
    <div class="client-section-head"><div><span class="client-card-label"><i class="bi bi-lightning-charge"></i> Quick actions</span><h2>What would you like to do?</h2></div></div>
    <div class="row g-3">
      <div class="col-6 col-lg-3"><a class="client-quick-card" href="<?= url('client/search.php') ?>"><i class="bi bi-search"></i><span><strong>Find Doctors</strong><small>Search available care</small></span><i class="bi bi-chevron-right"></i></a></div>
      <div class="col-6 col-lg-3"><a class="client-quick-card" href="<?= url('client/appointments.php') ?>"><i class="bi bi-calendar2-week"></i><span><strong>Appointments</strong><small>View your bookings</small></span><i class="bi bi-chevron-right"></i></a></div>
      <div class="col-6 col-lg-3"><a class="client-quick-card" href="<?= url('client/subscription.php') ?>"><i class="bi bi-credit-card"></i><span><strong>Subscription</strong><small>Plan and quota</small></span><i class="bi bi-chevron-right"></i></a></div>
      <div class="col-6 col-lg-3"><a class="client-quick-card" href="<?= url('client/profile.php') ?>"><i class="bi bi-person"></i><span><strong>Profile</strong><small>Personal details</small></span><i class="bi bi-chevron-right"></i></a></div>
    </div>
  </section>

  <div class="row g-4 mb-4">
    <div class="col-lg-5">
      <section class="client-search-card h-100">
        <div class="client-card-label"><i class="bi bi-compass"></i> Quick search</div>
        <h2>Find nearby care</h2><p>Start with a specialty and city. You can refine results on the search page.</p>
        <form action="<?= url('client/search.php') ?>" method="GET" class="mt-3">
          <label class="form-label">Specialization</label>
          <select name="specialization_id" class="form-select mb-3"><option value="">All specializations</option><?php foreach ($specializations as $sp): ?><option value="<?= $sp['Specialization_ID'] ?>"><?= e($sp['Name']) ?></option><?php endforeach; ?></select>
          <label class="form-label">City</label>
          <select name="city" class="form-select mb-3"><option value="">All Sri Lankan cities</option><?php foreach (array_keys($cities) as $cName): ?><option value="<?= e($cName) ?>" <?= ($client['City'] === $cName) ? 'selected' : '' ?>><?= e($cName) ?></option><?php endforeach; ?></select>
          <button type="submit" class="btn btn-teal w-100"><i class="bi bi-search"></i> Search doctors</button>
        </form>
      </section>
    </div>
    <div class="col-lg-7">
      <section class="client-upcoming-card h-100">
        <div class="client-section-head"><div><div class="client-card-label"><i class="bi bi-calendar-event"></i> Schedule</div><h2>Upcoming appointments</h2></div><a href="<?= url('client/appointments.php') ?>">View all <i class="bi bi-arrow-right"></i></a></div>
        <?php if (empty($upcomingAppointments)): ?>
          <div class="client-upcoming-empty"><i class="bi bi-calendar2"></i><p class="mb-0">No upcoming appointments to show.</p></div>
        <?php else: ?>
          <div class="client-appointment-list">
            <?php foreach ($upcomingAppointments as $ua): ?>
              <a href="<?= url('client/appointments.php') ?>" class="client-appointment-row">
                <div class="client-appt-date"><strong><?= date('d', strtotime($ua['Slot_Date'])) ?></strong><span><?= strtoupper(date('M', strtotime($ua['Slot_Date']))) ?></span></div>
                <div class="client-appt-copy"><strong><?= $ua['Doc_First'] ? 'Dr. ' . e($ua['Doc_First'] . ' ' . $ua['Doc_Last']) : e($ua['Centre_Name'] ?: $ua['Business_Name']) ?></strong><span><?= formatTime($ua['Start_Time']) ?> · <?= e($ua['Provider_City']) ?></span></div>
                <div class="client-appt-status"><?= renderStatusBadge($ua['Appt_Status']) ?></div><i class="bi bi-chevron-right"></i>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
