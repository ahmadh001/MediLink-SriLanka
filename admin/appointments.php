<?php
/**
 * Admin Appointments Management & System-Wide Booking Ledger
 * Patient–Doctor Subscription Booking System (Sri Lanka)
 */

$pageTitle = 'Manage System Appointments';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

// Handle Status Updates (CONFIRM, COMPLETE, CANCEL, NO-SHOW)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    CSRF::check();
    $action = $_POST['action'];
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);

    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        $allowedStatuses = ['BOOKED', 'CONFIRMED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'];

        if (in_array($newStatus, $allowedStatuses) && $appointmentId > 0) {
            try {
                $db->beginTransaction();

                // Fetch current appointment & slot
                $aStmt = $db->prepare("SELECT Slot_ID, Status FROM `APPOINTMENT` WHERE Appointment_ID = ? FOR UPDATE");
                $aStmt->execute([$appointmentId]);
                $currentAppt = $aStmt->fetch();

                if ($currentAppt) {
                    $uStmt = $db->prepare("UPDATE `APPOINTMENT` SET Status = ? WHERE Appointment_ID = ?");
                    $uStmt->execute([$newStatus, $appointmentId]);

                    // If cancelled, make the slot AVAILABLE again
                    if ($newStatus === 'CANCELLED') {
                        $sStmt = $db->prepare("UPDATE `SCHEDULED_SLOT` SET Status = 'AVAILABLE' WHERE Slot_ID = ?");
                        $sStmt->execute([$currentAppt['Slot_ID']]);
                    } elseif ($currentAppt['Status'] === 'CANCELLED' && $newStatus !== 'CANCELLED') {
                        // Re-locking slot
                        $sStmt = $db->prepare("UPDATE `SCHEDULED_SLOT` SET Status = 'BOOKED' WHERE Slot_ID = ?");
                        $sStmt->execute([$currentAppt['Slot_ID']]);
                    }

                    $db->commit();
                    setFlash('success', "Appointment #{$appointmentId} status successfully changed to {$newStatus}.");
                } else {
                    $db->rollBack();
                    setFlash('danger', 'Appointment record not found.');
                }
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('danger', 'Database error: ' . $e->getMessage());
            }
        }
    }
    redirect('admin/appointments.php');
}

// Summary Metrics
$mStmt = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN Status = 'BOOKED' THEN 1 ELSE 0 END) AS booked,
        SUM(CASE WHEN Status = 'CONFIRMED' THEN 1 ELSE 0 END) AS confirmed,
        SUM(CASE WHEN Status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN Status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled,
        SUM(CASE WHEN Status = 'NO_SHOW' THEN 1 ELSE 0 END) AS no_show
    FROM `APPOINTMENT`
");
$metrics = $mStmt->fetch();

// Filters & Search
$statusFilter = $_GET['status'] ?? 'ALL';
$searchQuery = trim($_GET['q'] ?? '');

$sql = "
    SELECT a.Appointment_ID, a.Booking_DateTime, a.Status, a.Notes, a.Created_At,
           s.Slot_Date, s.Start_Time, s.End_Time,
           c.Client_ID, cu.First_Name AS Patient_First, cu.Last_Name AS Patient_Last, cu.Email AS Patient_Email, cu.Phone AS Patient_Phone,
           p.Provider_ID, p.Business_Name, p.Provider_Type, p.City AS Provider_City,
           d.Medical_License_No, du.First_Name AS Doctor_First, du.Last_Name AS Doctor_Last
    FROM `APPOINTMENT` a
    JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID
    JOIN `PROVIDER` p ON s.Provider_ID = p.Provider_ID
    JOIN `CLIENT` c ON a.Client_ID = c.Client_ID
    JOIN `USER` cu ON c.User_ID = cu.User_ID
    LEFT JOIN `DOCTOR` d ON s.Doctor_ID = d.Doctor_ID
    LEFT JOIN `PROVIDER` dp ON d.Provider_ID = dp.Provider_ID
    LEFT JOIN `USER` du ON dp.User_ID = du.User_ID
    WHERE 1=1
";
$params = [];

if ($statusFilter !== 'ALL') {
    $sql .= " AND a.Status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery !== '') {
    $sql .= " AND (cu.First_Name LIKE ? OR cu.Last_Name LIKE ? OR p.Business_Name LIKE ? OR a.Appointment_ID = ?)";
    $term = "%{$searchQuery}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = is_numeric($searchQuery) ? (int)$searchQuery : 0;
}

$sql .= " ORDER BY s.Slot_Date DESC, s.Start_Time DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-calendar-range-fill text-teal me-2"></i> System Appointments Management</h2>
      <p class="text-muted small mb-0">Platform-wide overview of patient bookings, clinical consultations, and schedule lifecycle.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Admin Dashboard
      </a>
      <a href="<?= url('admin/reports.php') ?>" class="btn btn-teal btn-sm">
        <i class="bi bi-bar-chart-line me-1"></i> Booking Reports
      </a>
    </div>
  </div>

  <!-- Appointment Statistics Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
      <div class="card card-custom p-3 text-center">
        <div class="small text-muted fw-semibold">Total</div>
        <div class="fs-4 fw-bold text-dark"><?= (int)$metrics['total'] ?></div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-custom p-3 text-center border-primary">
        <div class="small text-muted fw-semibold">Booked</div>
        <div class="fs-4 fw-bold text-primary"><?= (int)$metrics['booked'] ?></div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-custom p-3 text-center border-info">
        <div class="small text-muted fw-semibold">Confirmed</div>
        <div class="fs-4 fw-bold text-info"><?= (int)$metrics['confirmed'] ?></div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-custom p-3 text-center border-success">
        <div class="small text-muted fw-semibold">Completed</div>
        <div class="fs-4 fw-bold text-success"><?= (int)$metrics['completed'] ?></div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-custom p-3 text-center border-danger">
        <div class="small text-muted fw-semibold">Cancelled</div>
        <div class="fs-4 fw-bold text-danger"><?= (int)$metrics['cancelled'] ?></div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-custom p-3 text-center border-warning">
        <div class="small text-muted fw-semibold">No-Show</div>
        <div class="fs-4 fw-bold text-warning"><?= (int)$metrics['no_show'] ?></div>
      </div>
    </div>
  </div>

  <!-- Filter & Search Toolbar -->
  <div class="card card-custom p-3 mb-4">
    <form method="GET" action="<?= url('admin/appointments.php') ?>" class="row g-2 align-items-center">
      <div class="col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Search by patient name, clinic or appt ID..." value="<?= e($searchQuery) ?>">
        </div>
      </div>
      <div class="col-md-4">
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
          <option value="BOOKED" <?= $statusFilter === 'BOOKED' ? 'selected' : '' ?>>Booked (Pending)</option>
          <option value="CONFIRMED" <?= $statusFilter === 'CONFIRMED' ? 'selected' : '' ?>>Confirmed</option>
          <option value="COMPLETED" <?= $statusFilter === 'COMPLETED' ? 'selected' : '' ?>>Completed</option>
          <option value="CANCELLED" <?= $statusFilter === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
          <option value="NO_SHOW" <?= $statusFilter === 'NO_SHOW' ? 'selected' : '' ?>>No-Show</option>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-teal btn-sm flex-grow-1"><i class="bi bi-filter"></i> Apply Filter</button>
        <?php if ($statusFilter !== 'ALL' || $searchQuery !== ''): ?>
          <a href="<?= url('admin/appointments.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle"></i> Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Appointments Master Table -->
  <div class="card card-custom p-4">
    <?php if (empty($appointments)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
        <h5 class="fw-bold">No appointment records found</h5>
        <p class="small mb-0">Try clearing or adjusting your search filters.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Patient</th>
              <th>Doctor / Provider</th>
              <th>Schedule Slot</th>
              <th>Status</th>
              <th>Notes</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($appointments as $a): ?>
              <?php
                $statusBadges = [
                    'BOOKED' => 'bg-primary',
                    'CONFIRMED' => 'bg-info text-dark',
                    'COMPLETED' => 'bg-success',
                    'CANCELLED' => 'bg-danger',
                    'NO_SHOW' => 'bg-warning text-dark'
                ];
                $badge = $statusBadges[$a['Status']] ?? 'bg-secondary';
              ?>
              <tr>
                <td><strong>#<?= $a['Appointment_ID'] ?></strong></td>
                <td>
                  <div class="fw-semibold text-dark"><?= e($a['Patient_First'] . ' ' . $a['Patient_Last']) ?></div>
                  <small class="text-muted"><i class="bi bi-telephone"></i> <?= e($a['Patient_Phone']) ?></small>
                </td>
                <td>
                  <div class="fw-semibold text-teal"><?= e($a['Business_Name']) ?></div>
                  <small class="text-muted">
                    <?php if ($a['Doctor_First']): ?>
                      Dr. <?= e($a['Doctor_First'] . ' ' . $a['Doctor_Last']) ?> (<?= e($a['Medical_License_No']) ?>)
                    <?php else: ?>
                      <?= e($a['Provider_City']) ?>
                    <?php endif; ?>
                  </small>
                </td>
                <td>
                  <div class="fw-semibold text-dark"><i class="bi bi-calendar-event me-1"></i> <?= formatDate($a['Slot_Date']) ?></div>
                  <small class="text-muted"><i class="bi bi-clock me-1"></i> <?= formatTime($a['Start_Time']) ?> - <?= formatTime($a['End_Time']) ?></small>
                </td>
                <td>
                  <span class="badge <?= $badge ?>"><?= $a['Status'] ?></span>
                </td>
                <td>
                  <small class="text-muted"><?= e($a['Notes'] ?: '—') ?></small>
                </td>
                <td class="text-end">
                  <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                      Manage
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                      <li>
                        <form method="POST" action="<?= url('admin/appointments.php') ?>">
                          <?= CSRF::inputField() ?>
                          <input type="hidden" name="action" value="update_status">
                          <input type="hidden" name="appointment_id" value="<?= $a['Appointment_ID'] ?>">
                          <input type="hidden" name="status" value="CONFIRMED">
                          <button type="submit" class="dropdown-item small text-info"><i class="bi bi-check-circle me-2"></i> Mark Confirmed</button>
                        </form>
                      </li>
                      <li>
                        <form method="POST" action="<?= url('admin/appointments.php') ?>">
                          <?= CSRF::inputField() ?>
                          <input type="hidden" name="action" value="update_status">
                          <input type="hidden" name="appointment_id" value="<?= $a['Appointment_ID'] ?>">
                          <input type="hidden" name="status" value="COMPLETED">
                          <button type="submit" class="dropdown-item small text-success"><i class="bi bi-patch-check me-2"></i> Mark Completed</button>
                        </form>
                      </li>
                      <li>
                        <form method="POST" action="<?= url('admin/appointments.php') ?>">
                          <?= CSRF::inputField() ?>
                          <input type="hidden" name="action" value="update_status">
                          <input type="hidden" name="appointment_id" value="<?= $a['Appointment_ID'] ?>">
                          <input type="hidden" name="status" value="NO_SHOW">
                          <button type="submit" class="dropdown-item small text-warning"><i class="bi bi-person-x me-2"></i> Mark No-Show</button>
                        </form>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <form method="POST" action="<?= url('admin/appointments.php') ?>" onsubmit="return confirm('Cancel appointment #<?= $a['Appointment_ID'] ?>? Slot will be re-opened for booking.')">
                          <?= CSRF::inputField() ?>
                          <input type="hidden" name="action" value="update_status">
                          <input type="hidden" name="appointment_id" value="<?= $a['Appointment_ID'] ?>">
                          <input type="hidden" name="status" value="CANCELLED">
                          <button type="submit" class="dropdown-item small text-danger"><i class="bi bi-x-circle me-2"></i> Cancel & Release Slot</button>
                        </form>
                      </li>
                    </ul>
                  </div>
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
