<?php


$pageTitle = 'Manage Patient Appointments';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_PROVIDER);
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$providerId = $currentUser['provider_id'];

$db = Database::getConnection();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    CSRF::check();

    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $newStatus = strtoupper(trim($_POST['status'] ?? ''));

    $validStatuses = ['CONFIRMED', 'REJECTED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'];

    if (in_array($newStatus, $validStatuses)) {
        
        $chk = $db->prepare("
            SELECT a.Appointment_ID, a.Status, a.Slot_ID, s.Slot_Date
            FROM `APPOINTMENT` a
            JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID
            WHERE a.Appointment_ID = ? AND s.Provider_ID = ?
        ");
        $chk->execute([$appointmentId, $providerId]);
        $appt = $chk->fetch();

        if ($appt) {
            $currentStatus = strtoupper($appt['Status']);
            $allowedTransition = (
                ($currentStatus === 'PENDING' && in_array($newStatus, ['CONFIRMED', 'REJECTED'], true)) ||
                ($currentStatus === 'BOOKED' && in_array($newStatus, ['CONFIRMED', 'CANCELLED'], true)) ||
                ($currentStatus === 'CONFIRMED' && in_array($newStatus, ['COMPLETED', 'CANCELLED', 'NO_SHOW'], true))
            );

            if (!$allowedTransition) {
                setFlash('danger', 'This appointment action is not allowed for its current status.');
                redirect('provider/appointments.php');
            }

            $db->beginTransaction();
            try {
                
                $db->prepare("UPDATE `APPOINTMENT` SET Status = ? WHERE Appointment_ID = ?")->execute([$newStatus, $appointmentId]);

                
                if (in_array($newStatus, ['CANCELLED', 'REJECTED'], true) && $appt['Slot_Date'] >= date('Y-m-d')) {
                    $db->prepare("UPDATE `SCHEDULED_SLOT` SET Status = 'AVAILABLE' WHERE Slot_ID = ?")->execute([$appt['Slot_ID']]);
                }

                $db->commit();
                setFlash('success', "Appointment #APT-" . str_pad($appointmentId, 5, '0', STR_PAD_LEFT) . " status updated to {$newStatus}.");
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('danger', 'Error updating appointment: ' . $e->getMessage());
            }
        } else {
            setFlash('danger', 'Appointment not found or unauthorized.');
        }
    } else {
        setFlash('danger', 'Invalid status transition requested.');
    }
    redirect('provider/appointments.php');
}


$filterStatus = $_GET['status'] ?? 'ALL';
$filterDate = $_GET['date'] ?? '';
$searchPatient = trim($_GET['q'] ?? '');

$query = "
    SELECT a.Appointment_ID, a.Booking_DateTime, a.Status AS Appt_Status, a.Notes,
           s.Slot_ID, s.Slot_Date, s.Start_Time, s.End_Time,
           c.Client_ID, c.Date_of_Birth, c.Gender, c.City AS Client_City,
           u.First_Name AS Patient_First, u.Last_Name AS Patient_Last, u.Email AS Patient_Email, u.Phone AS Patient_Phone,
           doc_u.First_Name AS Doc_First, doc_u.Last_Name AS Doc_Last, d.Medical_License_No
    FROM `APPOINTMENT` a
    JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID
    JOIN `CLIENT` c ON a.Client_ID = c.Client_ID
    JOIN `USER` u ON c.User_ID = u.User_ID
    LEFT JOIN `DOCTOR` d ON s.Doctor_ID = d.Doctor_ID
    LEFT JOIN `PROVIDER` dp ON d.Provider_ID = dp.Provider_ID
    LEFT JOIN `USER` doc_u ON dp.User_ID = doc_u.User_ID
    WHERE s.Provider_ID = ?
";
$params = [$providerId];

if ($filterStatus !== 'ALL') {
    $query .= " AND a.Status = ?";
    $params[] = $filterStatus;
}
if (!empty($filterDate)) {
    $query .= " AND s.Slot_Date = ?";
    $params[] = $filterDate;
}
if (!empty($searchPatient)) {
    $query .= " AND (u.First_Name LIKE ? OR u.Last_Name LIKE ? OR u.Phone LIKE ?)";
    $params[] = "%$searchPatient%";
    $params[] = "%$searchPatient%";
    $params[] = "%$searchPatient%";
}

$query .= " ORDER BY s.Slot_Date DESC, s.Start_Time DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php
$statusCounts = ['ALL' => count($appointments), 'PENDING' => 0, 'BOOKED' => 0, 'CONFIRMED' => 0, 'REJECTED' => 0, 'COMPLETED' => 0, 'CANCELLED' => 0, 'NO_SHOW' => 0];
foreach ($appointments as $item) {
    $key = strtoupper($item['Appt_Status'] ?? '');
    if (isset($statusCounts[$key])) $statusCounts[$key]++;
}
?>
<div class="container py-4 provider-appointments-v2">
  <section class="provider-appt-head mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
      <div>
        <span class="provider-appt-eyebrow"><i class="bi bi-calendar2-check"></i> Appointment workspace</span>
        <h1 class="provider-appt-title">Patient appointments</h1>
        <p class="provider-appt-subtitle mb-0">Review patient details and move each appointment through its consultation status.</p>
      </div>
      <a href="<?= url('provider/dashboard.php') ?>" class="btn btn-outline-secondary provider-appt-back"><i class="bi bi-grid me-2"></i>Dashboard</a>
    </div>
  </section>

  <section class="provider-appt-stats mb-4" aria-label="Appointment summary">
    <div class="provider-appt-stat"><span class="provider-appt-stat-icon"><i class="bi bi-calendar3"></i></span><div><strong><?= count($appointments) ?></strong><span>Results</span></div></div>
    <div class="provider-appt-stat"><span class="provider-appt-stat-icon"><i class="bi bi-hourglass-split"></i></span><div><strong><?= $statusCounts['PENDING'] ?></strong><span>Requests</span></div></div>
    <div class="provider-appt-stat"><span class="provider-appt-stat-icon"><i class="bi bi-patch-check"></i></span><div><strong><?= $statusCounts['CONFIRMED'] ?></strong><span>Confirmed</span></div></div>
    <div class="provider-appt-stat"><span class="provider-appt-stat-icon"><i class="bi bi-check2-circle"></i></span><div><strong><?= $statusCounts['COMPLETED'] ?></strong><span>Completed</span></div></div>
  </section>

  <section class="provider-appt-filter mb-4">
    <form method="GET" action="<?= url('provider/appointments.php') ?>" class="row g-3 align-items-end">
      <div class="col-lg-4">
        <label class="form-label">Patient</label>
        <div class="input-group provider-appt-input"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="text" name="q" class="form-control" placeholder="Name or phone number" value="<?= e($searchPatient) ?>"></div>
      </div>
      <div class="col-sm-6 col-lg-3"><label class="form-label">Appointment date</label><input type="date" name="date" class="form-control" value="<?= e($filterDate) ?>"></div>
      <div class="col-sm-6 col-lg-3"><label class="form-label">Status</label><select name="status" class="form-select">
        <option value="ALL" <?= $filterStatus === 'ALL' ? 'selected' : '' ?>>All statuses</option>
        <option value="PENDING" <?= $filterStatus === 'PENDING' ? 'selected' : '' ?>>Pending requests</option><option value="BOOKED" <?= $filterStatus === 'BOOKED' ? 'selected' : '' ?>>Booked (legacy)</option><option value="CONFIRMED" <?= $filterStatus === 'CONFIRMED' ? 'selected' : '' ?>>Confirmed</option><option value="COMPLETED" <?= $filterStatus === 'COMPLETED' ? 'selected' : '' ?>>Completed</option><option value="CANCELLED" <?= $filterStatus === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option><option value="NO_SHOW" <?= $filterStatus === 'NO_SHOW' ? 'selected' : '' ?>>No-show</option>
      </select></div>
      <div class="col-lg-2 d-flex gap-2"><button type="submit" class="btn btn-teal flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button><a href="<?= url('provider/appointments.php') ?>" class="btn btn-outline-secondary" aria-label="Clear filters"><i class="bi bi-arrow-counterclockwise"></i></a></div>
    </form>
    <?php if ($filterStatus !== 'ALL' || $filterDate || $searchPatient): ?><div class="provider-appt-filter-note"><i class="bi bi-funnel-fill"></i> Showing filtered results <a href="<?= url('provider/appointments.php') ?>">Clear all</a></div><?php endif; ?>
  </section>

  <?php if (empty($appointments)): ?>
    <section class="provider-appt-empty"><span><i class="bi bi-calendar2-x"></i></span><h3>No appointments found</h3><p>There are no patient appointments matching these filters.</p><a href="<?= url('provider/appointments.php') ?>" class="btn btn-outline-teal">Clear filters</a></section>
  <?php else: ?>
    <section class="provider-appt-list">
      <?php foreach ($appointments as $apt):
        $patientName = trim($apt['Patient_First'] . ' ' . $apt['Patient_Last']);
        $initials = strtoupper(substr($apt['Patient_First'] ?: 'P', 0, 1) . substr($apt['Patient_Last'] ?: '', 0, 1));
        $isActionable = in_array($apt['Appt_Status'], ['PENDING', 'BOOKED', 'CONFIRMED']);
      ?>
      <article class="provider-appt-card">
        <div class="provider-appt-datebox"><span><?= date('M', strtotime($apt['Slot_Date'])) ?></span><strong><?= date('d', strtotime($apt['Slot_Date'])) ?></strong><small><?= date('D', strtotime($apt['Slot_Date'])) ?></small></div>
        <div class="provider-appt-main">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div class="d-flex align-items-center gap-3"><div class="provider-appt-avatar"><?= e($initials) ?></div><div><h3><?= e($patientName) ?></h3><div class="provider-appt-ref">#APT-<?= str_pad($apt['Appointment_ID'], 5, '0', STR_PAD_LEFT) ?> · <?= e($apt['Client_City'] ?: 'City not provided') ?></div></div></div>
            <?= renderStatusBadge($apt['Appt_Status']) ?>
          </div>
          <div class="provider-appt-meta">
            <div><i class="bi bi-clock"></i><span><strong><?= formatTime($apt['Start_Time']) ?> – <?= formatTime($apt['End_Time']) ?></strong><small><?= formatDate($apt['Slot_Date']) ?></small></span></div>
            <div><i class="bi bi-telephone"></i><span><strong><?= e($apt['Patient_Phone'] ?: 'Not provided') ?></strong><small><?= e($apt['Patient_Email']) ?></small></span></div>
            <div><i class="bi bi-person-vcard"></i><span><strong><?= e($apt['Gender'] ?: 'Not specified') ?></strong><small>Patient details</small></span></div>
          </div>
          <?php if (!empty($apt['Notes'])): ?><div class="provider-appt-note"><i class="bi bi-chat-left-text"></i><div><strong>Booking note</strong><p><?= nl2br(e($apt['Notes'])) ?></p></div></div><?php endif; ?>
        </div>
        <div class="provider-appt-actions">
          <?php if ($isActionable): ?>
          <div class="dropdown w-100"><button class="btn btn-outline-teal dropdown-toggle w-100" type="button" data-bs-toggle="dropdown"><i class="bi bi-arrow-repeat me-1"></i>Update status</button><ul class="dropdown-menu dropdown-menu-end shadow-sm w-100">
            <?php if ($apt['Appt_Status'] === 'PENDING'): ?>
            <li><form method="POST" action="<?= url('provider/appointments.php') ?>"><?= CSRF::inputField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="appointment_id" value="<?= $apt['Appointment_ID'] ?>"><input type="hidden" name="status" value="CONFIRMED"><button type="submit" class="dropdown-item"><i class="bi bi-check-circle me-2"></i>Accept request</button></form></li>
            <li><form method="POST" action="<?= url('provider/appointments.php') ?>" data-confirm="Reject this appointment request? The slot will become available again."><?= CSRF::inputField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="appointment_id" value="<?= $apt['Appointment_ID'] ?>"><input type="hidden" name="status" value="REJECTED"><button type="submit" class="dropdown-item text-danger"><i class="bi bi-x-circle me-2"></i>Reject request</button></form></li>
            <?php endif; ?>
            <?php if ($apt['Appt_Status'] === 'BOOKED'): ?><li><form method="POST" action="<?= url('provider/appointments.php') ?>"><?= CSRF::inputField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="appointment_id" value="<?= $apt['Appointment_ID'] ?>"><input type="hidden" name="status" value="CONFIRMED"><button type="submit" class="dropdown-item"><i class="bi bi-patch-check me-2"></i>Confirm appointment</button></form></li><?php endif; ?>
            <li><form method="POST" action="<?= url('provider/appointments.php') ?>"><?= CSRF::inputField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="appointment_id" value="<?= $apt['Appointment_ID'] ?>"><input type="hidden" name="status" value="COMPLETED"><button type="submit" class="dropdown-item"><i class="bi bi-check2-circle me-2"></i>Mark completed</button></form></li>
            <li><form method="POST" action="<?= url('provider/appointments.php') ?>"><?= CSRF::inputField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="appointment_id" value="<?= $apt['Appointment_ID'] ?>"><input type="hidden" name="status" value="NO_SHOW"><button type="submit" class="dropdown-item"><i class="bi bi-person-x me-2"></i>Mark no-show</button></form></li>
            <li><hr class="dropdown-divider"></li><li><form method="POST" action="<?= url('provider/appointments.php') ?>" data-confirm="Cancel this appointment?"><?= CSRF::inputField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="appointment_id" value="<?= $apt['Appointment_ID'] ?>"><input type="hidden" name="status" value="CANCELLED"><button type="submit" class="dropdown-item text-danger"><i class="bi bi-x-circle me-2"></i>Cancel appointment</button></form></li>
          </ul></div>
          <?php else: ?><div class="provider-appt-closed"><i class="bi bi-lock"></i><span>Status closed<small>No further actions</small></span></div><?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
