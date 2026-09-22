<?php


$pageTitle = 'My Appointment Bookings';
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


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_appointment') {
    CSRF::check();

    $appointmentId = (int)($_POST['appointment_id'] ?? 0);

    
    $chkStmt = $db->prepare("
        SELECT a.Appointment_ID, a.Status AS Appt_Status, a.Slot_ID, s.Slot_Date, s.Start_Time
        FROM `APPOINTMENT` a
        JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID
        WHERE a.Appointment_ID = ? AND a.Client_ID = ?
    ");
    $chkStmt->execute([$appointmentId, $clientId]);
    $appt = $chkStmt->fetch();

    if ($appt) {
        if (in_array($appt['Appt_Status'], ['PENDING', 'BOOKED', 'CONFIRMED'])) {
            $db->beginTransaction();
            try {
                
                $db->prepare("UPDATE `APPOINTMENT` SET Status = 'CANCELLED' WHERE Appointment_ID = ?")->execute([$appointmentId]);

                
                if ($appt['Slot_Date'] >= date('Y-m-d')) {
                    $db->prepare("UPDATE `SCHEDULED_SLOT` SET Status = 'AVAILABLE' WHERE Slot_ID = ?")->execute([$appt['Slot_ID']]);
                }

                $db->commit();
                setFlash('info', 'Appointment #APT-' . str_pad($appointmentId, 5, '0', STR_PAD_LEFT) . ' has been cancelled. Your monthly booking quota has been restored.');
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('danger', 'Error cancelling appointment: ' . $e->getMessage());
            }
        } else {
            setFlash('danger', 'Cannot cancel an appointment that is already ' . strtolower($appt['Appt_Status']) . '.');
        }
    } else {
        setFlash('danger', 'Appointment not found.');
    }
    redirect('client/appointments.php');
}


$filterStatus = $_GET['status'] ?? 'ALL';

$query = "
    SELECT a.Appointment_ID, a.Booking_DateTime, a.Status AS Appt_Status, a.Notes,
           s.Slot_ID, s.Slot_Date, s.Start_Time, s.End_Time,
           p.Provider_ID, p.Provider_Type, p.Business_Name, p.Address, p.City, p.Contact_Number,
           u.First_Name AS Doc_First, u.Last_Name AS Doc_Last, d.Medical_License_No,
           hc.Centre_Name,
           GROUP_CONCAT(DISTINCT spec.Name SEPARATOR ', ') AS Specializations
    FROM `APPOINTMENT` a
    JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID
    JOIN `PROVIDER` p ON s.Provider_ID = p.Provider_ID
    LEFT JOIN `DOCTOR` d ON s.Doctor_ID = d.Doctor_ID
    LEFT JOIN `PROVIDER` dp ON d.Provider_ID = dp.Provider_ID
    LEFT JOIN `USER` u ON dp.User_ID = u.User_ID
    LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON d.Doctor_ID = ds.Doctor_ID
    LEFT JOIN `SPECIALIZATION` spec ON ds.Specialization_ID = spec.Specialization_ID
    LEFT JOIN `HEALTHCARE_CENTRE` hc ON p.Provider_ID = hc.Provider_ID
    WHERE a.Client_ID = ?
";

$params = [$clientId];

if ($filterStatus !== 'ALL') {
    $query .= " AND a.Status = ?";
    $params[] = $filterStatus;
}

$query .= " GROUP BY a.Appointment_ID ORDER BY s.Slot_Date DESC, s.Start_Time DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();


$quota = getMonthlyBookingQuota($clientId, $userId);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.client-appt-page{max-width:1180px}.appt-hero{padding:1.1rem 0 .5rem}.appt-quota{background:#f7fbfa;border:1px solid #dceeea;border-radius:18px}.appt-filter{display:flex;gap:.45rem;overflow-x:auto;padding-bottom:.2rem}.appt-filter .btn{white-space:nowrap;border-radius:999px}.appt-list{display:grid;gap:1rem}.appt-card{border:1px solid #e6eceb;border-radius:20px;background:#fff;padding:1.15rem;transition:.18s ease}.appt-card:hover{border-color:#c9dfdb;box-shadow:0 10px 30px rgba(21,62,57,.06);transform:translateY(-1px)}.appt-datebox{min-width:86px;border-radius:16px;background:#f4f9f8;padding:.8rem;text-align:center}.appt-datebox .day{font-size:1.55rem;line-height:1;font-weight:800}.appt-meta{display:flex;flex-wrap:wrap;gap:.55rem 1.1rem;color:#667572;font-size:.88rem}.appt-meta i{color:var(--primary-teal,#087f78)}.appt-ref{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.78rem;color:#667572}.appt-actions{display:flex;gap:.5rem;flex-wrap:wrap;justify-content:flex-end}.quota-progress{height:7px;background:#e2ecea;border-radius:999px;overflow:hidden}.quota-progress>span{display:block;height:100%;background:var(--primary-teal,#087f78);border-radius:inherit}.empty-appt{border:1px dashed #cfdcda;border-radius:20px;padding:3rem 1rem;text-align:center;background:#fbfdfd}@media(max-width:767.98px){.appt-card{padding:1rem}.appt-datebox{min-width:72px}.appt-actions{justify-content:flex-start;width:100%;margin-top:.35rem}.appt-actions .btn{flex:1}.appt-hero .btn{width:100%}}
</style>

<div class="container client-appt-page py-4 py-lg-5">
  <div class="appt-hero d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
    <div>
      <div class="text-teal small fw-semibold mb-2"><i class="bi bi-calendar2-check me-1"></i> APPOINTMENTS</div>
      <h2 class="fw-bold mb-2">My appointments</h2>
      <p class="text-muted mb-0">See upcoming bookings, review past visits and manage cancellations in one place.</p>
    </div>
    <div class="d-flex flex-column flex-sm-row gap-2">
      <a href="<?= url('client/search.php') ?>" class="btn btn-teal"><i class="bi bi-search me-2"></i>Find a doctor</a>
      <a href="<?= url('client/dashboard.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-grid me-2"></i>Dashboard</a>
    </div>
  </div>

  <?php $quotaPercent = ($quota['limit'] ?? 0) > 0 ? min(100, round(($quota['used'] / $quota['limit']) * 100)) : 0; ?>
  <div class="appt-quota p-3 p-lg-4 mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
      <div class="flex-grow-1">
        <div class="d-flex justify-content-between gap-3 mb-2">
          <div><span class="fw-bold">Monthly booking quota</span> <span class="text-muted small">· <?= date('F Y') ?></span></div>
          <div class="small fw-semibold"><?= (int)$quota['remaining'] ?> remaining</div>
        </div>
        <div class="quota-progress" aria-label="Booking quota used"><span style="width:<?= $quotaPercent ?>%"></span></div>
        <div class="small text-muted mt-2"><?= (int)$quota['used'] ?> of <?= (int)$quota['limit'] ?> bookings used this month.</div>
      </div>
      <a href="<?= url('client/subscription.php') ?>" class="btn btn-sm btn-outline-teal flex-shrink-0">Manage plan <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
  </div>

  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <div>
      <h5 class="fw-bold mb-1">Booking history</h5>
      <div class="small text-muted"><?= count($appointments) ?> appointment<?= count($appointments) === 1 ? '' : 's' ?> in this view</div>
    </div>
    <div class="appt-filter" aria-label="Filter appointments by status">
      <?php foreach (['ALL'=>'All','PENDING'=>'Pending','CONFIRMED'=>'Confirmed','REJECTED'=>'Rejected','COMPLETED'=>'Completed','CANCELLED'=>'Cancelled'] as $statusKey => $statusLabel): ?>
        <a href="<?= url('client/appointments.php?status=' . $statusKey) ?>" class="btn btn-sm <?= $filterStatus === $statusKey ? 'btn-teal' : 'btn-outline-secondary' ?>"><?= $statusLabel ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($appointments)): ?>
    <div class="empty-appt">
      <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light mb-3" style="width:58px;height:58px"><i class="bi bi-calendar2 fs-3 text-teal"></i></div>
      <h5 class="fw-bold">No appointments here yet</h5>
      <p class="text-muted mb-3">There are no bookings matching this status filter.</p>
      <a href="<?= url('client/search.php') ?>" class="btn btn-teal"><i class="bi bi-search me-2"></i>Find a doctor</a>
    </div>
  <?php else: ?>
    <div class="appt-list">
      <?php foreach ($appointments as $apt):
        $dateTs = strtotime($apt['Slot_Date']);
        $canCancel = in_array($apt['Appt_Status'], ['PENDING', 'BOOKED', 'CONFIRMED'], true);
      ?>
        <article class="appt-card">
          <div class="d-flex flex-column flex-md-row gap-3 align-items-md-start">
            <div class="appt-datebox flex-shrink-0">
              <div class="small text-uppercase text-muted fw-semibold"><?= date('M', $dateTs) ?></div>
              <div class="day my-1"><?= date('d', $dateTs) ?></div>
              <div class="small text-muted"><?= date('D', $dateTs) ?></div>
            </div>

            <div class="flex-grow-1 min-w-0">
              <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-2">
                <div>
                  <?php if ($apt['Doc_First']): ?>
                    <h5 class="fw-bold mb-1">Dr. <?= e($apt['Doc_First'] . ' ' . $apt['Doc_Last']) ?></h5>
                    <div class="text-teal small fw-semibold"><?= e($apt['Specializations'] ?: 'Consultant') ?></div>
                  <?php else: ?>
                    <h5 class="fw-bold mb-1"><?= e($apt['Centre_Name'] ?: $apt['Business_Name']) ?></h5>
                    <div class="text-muted small">Healthcare Centre</div>
                  <?php endif; ?>
                </div>
                <div class="text-md-end">
                  <?= renderStatusBadge($apt['Appt_Status']) ?>
                  <div class="appt-ref mt-1">#APT-<?= str_pad($apt['Appointment_ID'], 5, '0', STR_PAD_LEFT) ?></div>
                </div>
              </div>

              <div class="appt-meta mb-2">
                <span><i class="bi bi-clock me-1"></i><?= formatTime($apt['Start_Time']) ?> – <?= formatTime($apt['End_Time']) ?></span>
                <span><i class="bi bi-geo-alt me-1"></i><?= e($apt['City'] ?: 'Location not specified') ?></span>
                <?php if (!empty($apt['Contact_Number'])): ?><span><i class="bi bi-telephone me-1"></i><?= e($apt['Contact_Number']) ?></span><?php endif; ?>
              </div>

              <?php if (!empty($apt['Notes'])): ?>
                <div class="small text-muted"><i class="bi bi-chat-left-text me-1"></i><?= e($apt['Notes']) ?></div>
              <?php endif; ?>
            </div>

            <div class="appt-actions flex-md-column flex-lg-row flex-shrink-0">
              <a href="<?= url('client/receipt.php?id=' . $apt['Appointment_ID']) ?>" class="btn btn-outline-teal btn-sm"><i class="bi bi-receipt me-1"></i>Details</a>
              <?php if ($canCancel): ?>
                <form method="POST" action="<?= url('client/appointments.php') ?>" class="d-flex" data-confirm="Cancel appointment #APT-<?= str_pad($apt['Appointment_ID'], 5, '0', STR_PAD_LEFT) ?>? Your quota will be restored.">
                  <?= CSRF::inputField() ?>
                  <input type="hidden" name="action" value="cancel_appointment">
                  <input type="hidden" name="appointment_id" value="<?= (int)$apt['Appointment_ID'] ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-x-circle me-1"></i>Cancel</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
