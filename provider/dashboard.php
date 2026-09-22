<?php


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


$pStmt = $db->prepare("SELECT * FROM `PROVIDER` WHERE Provider_ID = ?");
$pStmt->execute([$providerId]);
$provider = $pStmt->fetch();


$doctorAffiliationRequests = [];
if ($providerType === PROVIDER_DOCTOR) {
    $doctorRow = $db->prepare("SELECT Doctor_ID FROM `DOCTOR` WHERE Provider_ID = ?");
    $doctorRow->execute([$providerId]);
    $doctorIdForRequests = (int)($doctorRow->fetchColumn() ?: 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'affiliation_response') {
        CSRF::check();
        $centreId = (int)($_POST['centre_id'] ?? 0);
        $decision = strtoupper($_POST['decision'] ?? '');
        if ($doctorIdForRequests && $centreId && in_array($decision, ['ACCEPT','REJECT'], true)) {
            $newLinkStatus = $decision === 'ACCEPT' ? 'ACTIVE' : 'REJECTED';
            $stmt = $db->prepare("UPDATE `CENTRE_DOCTOR_LINK` SET Status = ?, Joined_Date = CURRENT_DATE WHERE Centre_ID = ? AND Doctor_ID = ? AND Status = 'PENDING'");
            $stmt->execute([$newLinkStatus, $centreId, $doctorIdForRequests]);
            setFlash($decision === 'ACCEPT' ? 'success' : 'info', $decision === 'ACCEPT' ? 'Healthcare centre affiliation accepted.' : 'Healthcare centre affiliation rejected.');
        }
        redirect('provider/dashboard.php');
    }

    if ($doctorIdForRequests) {
        $reqStmt = $db->prepare("
            SELECT cdl.Centre_ID, hc.Centre_Name, p.City
            FROM `CENTRE_DOCTOR_LINK` cdl
            JOIN `HEALTHCARE_CENTRE` hc ON cdl.Centre_ID = hc.Centre_ID
            JOIN `PROVIDER` p ON hc.Provider_ID = p.Provider_ID
            WHERE cdl.Doctor_ID = ? AND cdl.Status = 'PENDING'
            ORDER BY cdl.Joined_Date DESC
        ");
        $reqStmt->execute([$doctorIdForRequests]);
        $doctorAffiliationRequests = $reqStmt->fetchAll();
    }
}

$hasSub = hasActiveSubscription($userId);
$activeSub = getUserActiveSubscription($userId);


$statStmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM `SCHEDULED_SLOT` WHERE Provider_ID = ? AND Status = 'AVAILABLE' AND Slot_Date >= CURRENT_DATE) AS available_slots,
        (SELECT COUNT(*) FROM `APPOINTMENT` a JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID WHERE s.Provider_ID = ? AND s.Slot_Date = CURRENT_DATE AND a.Status NOT IN ('CANCELLED','REJECTED')) AS today_appts,
        (SELECT COUNT(*) FROM `APPOINTMENT` a JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID WHERE s.Provider_ID = ? AND a.Status IN ('PENDING','BOOKED')) AS pending_confirmations,
        (SELECT COUNT(*) FROM `APPOINTMENT` a JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID WHERE s.Provider_ID = ? AND a.Status = 'COMPLETED') AS completed_total
");
$statStmt->execute([$providerId, $providerId, $providerId, $providerId]);
$stats = $statStmt->fetch();


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
      AND s.Slot_Date >= CURRENT_DATE
      AND a.Status NOT IN ('CANCELLED','REJECTED')
    ORDER BY s.Slot_Date ASC, s.Start_Time ASC
    LIMIT 10
");
$apptStmt->execute([$providerId]);
$upcomingAppts = $apptStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.provider-dashboard-v2{--pd-border:#e7ecef;--pd-soft:#f7faf9;--pd-teal:#087f78}.provider-dashboard-v2 .pd-hero{background:linear-gradient(135deg,#f7fbfa 0%,#fff 72%);border:1px solid var(--pd-border);border-radius:22px;padding:1.35rem}.provider-dashboard-v2 .pd-kpi{border:1px solid var(--pd-border);border-radius:18px;background:#fff;padding:1.05rem;height:100%;box-shadow:0 8px 26px rgba(18,47,43,.045)}.provider-dashboard-v2 .pd-kpi-icon{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:#eef8f6;color:var(--pd-teal);font-size:1.15rem}.provider-dashboard-v2 .pd-kpi-value{font-size:1.7rem;line-height:1;font-weight:800;letter-spacing:-.03em}.provider-dashboard-v2 .pd-panel{border:1px solid var(--pd-border);border-radius:20px;background:#fff;box-shadow:0 8px 28px rgba(18,47,43,.04)}.provider-dashboard-v2 .pd-action{display:flex;align-items:center;gap:.8rem;padding:.85rem;border:1px solid var(--pd-border);border-radius:14px;color:inherit;text-decoration:none;transition:.18s ease}.provider-dashboard-v2 .pd-action:hover{border-color:#b9dcd8;background:#f7fbfa;transform:translateY(-1px)}.provider-dashboard-v2 .pd-action-icon{width:38px;height:38px;border-radius:11px;background:#eef8f6;color:var(--pd-teal);display:grid;place-items:center;flex:0 0 auto}.provider-dashboard-v2 .pd-appt{border:1px solid var(--pd-border);border-radius:15px;padding:.9rem 1rem}.provider-dashboard-v2 .pd-date{min-width:72px;border-right:1px solid var(--pd-border)}.provider-dashboard-v2 .pd-sub{background:#fff9e9;border:1px solid #f1dda2;border-radius:16px}.provider-dashboard-v2 .pd-plan{background:#f7faf9;border:1px solid var(--pd-border);border-radius:14px}@media(max-width:767.98px){.provider-dashboard-v2 .pd-hero{padding:1rem}.provider-dashboard-v2 .pd-date{min-width:60px}.provider-dashboard-v2 .pd-appt{padding:.8rem}.provider-dashboard-v2 .pd-appt-main{min-width:0}}
</style>

<div class="container py-4 provider-dashboard-v2">
  <section class="pd-hero mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
          <span class="badge rounded-pill text-bg-light border"><i class="bi bi-grid me-1"></i>Provider workspace</span>
          <?= renderStatusBadge($provider['Verification_Status']) ?>
        </div>
        <h2 class="fw-bold mb-1"><?= e($provider['Business_Name']) ?></h2>
        <div class="text-muted small d-flex flex-wrap gap-3">
          <span><i class="bi bi-geo-alt me-1"></i><?= e($provider['City']) ?></span>
          <span><i class="bi <?= $providerType === 'DOCTOR' ? 'bi-person-badge' : 'bi-hospital' ?> me-1"></i><?= $providerType === 'DOCTOR' ? 'Medical Practitioner' : 'Healthcare Facility' ?></span>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="<?= url('provider/slots.php?action=create') ?>" class="btn btn-teal"><i class="bi bi-calendar-plus me-1"></i>Add slot</a>
        <a href="<?= url('provider/profile.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil-square me-1"></i>Edit profile</a>
      </div>
    </div>
  </section>

  <?php if (!$hasSub): ?>
    <div class="pd-sub p-3 mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
      <div class="d-flex gap-3 align-items-start"><i class="bi bi-exclamation-circle fs-4 text-warning"></i><div><div class="fw-bold">Provider plan inactive</div><div class="small text-muted">An active provider plan is required to publish bookable schedule slots.</div></div></div>
      <a href="<?= url('provider/subscription.php') ?>" class="btn btn-dark btn-sm flex-shrink-0">Manage subscription</a>
    </div>
  <?php endif; ?>

  <?php if ($providerType === PROVIDER_DOCTOR && !empty($doctorAffiliationRequests)): ?>
    <div class="pd-panel p-3 p-md-4 mb-4">
      <div class="d-flex align-items-center justify-content-between mb-3"><div><h5 class="fw-bold mb-1">Healthcare centre requests</h5><div class="small text-muted">Accept only centres you want to be affiliated with.</div></div><span class="badge text-bg-warning"><?= count($doctorAffiliationRequests) ?> pending</span></div>
      <div class="d-grid gap-2">
        <?php foreach ($doctorAffiliationRequests as $req): ?>
          <div class="pd-appt d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div><strong><?= e($req['Centre_Name']) ?></strong><div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($req['City']) ?></div></div>
            <div class="d-flex gap-2">
              <form method="POST"><?= CSRF::inputField() ?><input type="hidden" name="action" value="affiliation_response"><input type="hidden" name="centre_id" value="<?= (int)$req['Centre_ID'] ?>"><input type="hidden" name="decision" value="ACCEPT"><button class="btn btn-teal btn-sm">Accept</button></form>
              <form method="POST"><?= CSRF::inputField() ?><input type="hidden" name="action" value="affiliation_response"><input type="hidden" name="centre_id" value="<?= (int)$req['Centre_ID'] ?>"><input type="hidden" name="decision" value="REJECT"><button class="btn btn-outline-danger btn-sm">Reject</button></form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <?php
      $kpis = [
        ['bi-calendar2-check','Available slots',$stats['available_slots'],'Upcoming bookable'],
        ['bi-calendar-day',"Today's appointments",$stats['today_appts'],date('d M Y')],
        ['bi-hourglass-split','Pending',$stats['pending_confirmations'],'Awaiting action'],
        ['bi-check2-circle','Completed',$stats['completed_total'],'All-time total'],
      ];
      foreach ($kpis as $k): ?>
      <div class="col-6 col-xl-3"><div class="pd-kpi">
        <div class="d-flex justify-content-between align-items-start mb-3"><div class="pd-kpi-icon"><i class="bi <?= $k[0] ?>"></i></div><span class="pd-kpi-value"><?= (int)$k[2] ?></span></div>
        <div class="fw-semibold small"><?= e($k[1]) ?></div><div class="text-muted small"><?= e($k[3]) ?></div>
      </div></div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4">
    <div class="col-xl-4">
      <div class="pd-panel p-3 p-md-4 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3"><h5 class="fw-bold mb-0">Quick actions</h5><i class="bi bi-lightning-charge text-teal"></i></div>
        <div class="d-grid gap-2">
          <a class="pd-action" href="<?= url('provider/slots.php?action=create') ?>"><span class="pd-action-icon"><i class="bi bi-plus-lg"></i></span><span><strong class="d-block small">Create a slot</strong><small class="text-muted">Publish one appointment time</small></span></a>
          <a class="pd-action" href="<?= url('provider/slots.php?tab=batch') ?>"><span class="pd-action-icon"><i class="bi bi-calendar2-week"></i></span><span><strong class="d-block small">Batch schedule</strong><small class="text-muted">Generate multiple time slots</small></span></a>
          <a class="pd-action" href="<?= url('provider/appointments.php') ?>"><span class="pd-action-icon"><i class="bi bi-calendar-check"></i></span><span><strong class="d-block small">Appointments</strong><small class="text-muted">Review and update bookings</small></span></a>
          <?php if ($providerType === PROVIDER_CENTRE): ?><a class="pd-action" href="<?= url('provider/doctors.php') ?>"><span class="pd-action-icon"><i class="bi bi-people"></i></span><span><strong class="d-block small">Affiliated doctors</strong><small class="text-muted">Manage your clinical team</small></span></a><?php endif; ?>
        </div>
      </div>
      <div class="pd-plan p-3">
        <div class="d-flex justify-content-between align-items-center"><div><div class="small text-muted">Subscription</div><div class="fw-bold"><?= $hasSub ? e($activeSub['Plan_Name'] ?? 'Active plan') : 'No active plan' ?></div></div><i class="bi bi-credit-card fs-4 text-teal"></i></div>
        <a href="<?= url('provider/subscription.php') ?>" class="small text-teal fw-semibold text-decoration-none d-inline-block mt-2">View plan details <i class="bi bi-arrow-right"></i></a>
      </div>
    </div>

    <div class="col-xl-8">
      <div class="pd-panel p-3 p-md-4 h-100">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3"><div><h5 class="fw-bold mb-0">Upcoming appointments</h5><div class="small text-muted">Your next patient bookings</div></div><a href="<?= url('provider/appointments.php') ?>" class="btn btn-outline-secondary btn-sm">View all</a></div>
        <?php if (empty($upcomingAppts)): ?>
          <div class="text-center py-5"><div class="pd-kpi-icon mx-auto mb-3"><i class="bi bi-calendar2-plus"></i></div><h6 class="fw-bold">No upcoming appointments</h6><p class="small text-muted mb-3">Publish availability so patients can reserve a time.</p><a href="<?= url('provider/slots.php?action=create') ?>" class="btn btn-teal btn-sm">Add available slot</a></div>
        <?php else: ?>
          <div class="d-grid gap-2">
            <?php foreach ($upcomingAppts as $ap): ?>
              <div class="pd-appt d-flex align-items-center gap-3">
                <div class="pd-date text-center flex-shrink-0 pe-3"><div class="small text-uppercase text-muted"><?= date('M', strtotime($ap['Slot_Date'])) ?></div><div class="fs-4 fw-bold lh-1"><?= date('d', strtotime($ap['Slot_Date'])) ?></div><div class="small text-muted mt-1"><?= formatTime($ap['Start_Time']) ?></div></div>
                <div class="pd-appt-main flex-grow-1"><div class="d-flex flex-wrap align-items-center gap-2"><strong><?= e($ap['Patient_First'].' '.$ap['Patient_Last']) ?></strong><?= renderStatusBadge($ap['Status']) ?></div><div class="small text-muted text-truncate"><i class="bi bi-geo-alt me-1"></i><?= e($ap['Patient_City']) ?><?php if (!empty($ap['Patient_Phone'])): ?> <span class="mx-1">·</span><i class="bi bi-telephone me-1"></i><?= e($ap['Patient_Phone']) ?><?php endif; ?></div></div>
                <a href="<?= url('provider/appointments.php?id='.$ap['Appointment_ID']) ?>" class="btn btn-outline-teal btn-sm flex-shrink-0"><span class="d-none d-sm-inline">Manage</span><i class="bi bi-chevron-right d-sm-none"></i></a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
