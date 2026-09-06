<?php
/**
 * Provider Appointment Lifecycle Management (Confirm, Complete, Cancel, No-Show)
 */

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

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    CSRF::check();

    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $newStatus = strtoupper(trim($_POST['status'] ?? ''));

    $validStatuses = ['BOOKED', 'CONFIRMED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'];

    if (in_array($newStatus, $validStatuses)) {
        // Verify appointment belongs to this provider
        $chk = $db->prepare("
            SELECT a.Appointment_ID, a.Status, a.Slot_ID, s.Slot_Date
            FROM `APPOINTMENT` a
            JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID
            WHERE a.Appointment_ID = ? AND s.Provider_ID = ?
        ");
        $chk->execute([$appointmentId, $providerId]);
        $appt = $chk->fetch();

        if ($appt) {
            $db->beginTransaction();
            try {
                // Update Appointment Status
                $db->prepare("UPDATE `APPOINTMENT` SET Status = ? WHERE Appointment_ID = ?")->execute([$newStatus, $appointmentId]);

                // If cancelled by provider and slot is future, free the slot
                if ($newStatus === 'CANCELLED' && $appt['Slot_Date'] >= date('Y-m-d')) {
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

// Filter Options
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

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-calendar2-check text-teal me-2"></i> Patient Appointment Management</h2>
      <p class="text-muted small mb-0">Confirm, complete, or reschedule booked consultations with your clinical patients.</p>
    </div>
    <a href="<?= url('provider/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
  </div>

  <!-- Filter & Search Toolbar -->
  <div class="card card-custom p-4 mb-4">
    <form method="GET" action="<?= url('provider/appointments.php') ?>" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Search Patient Name / Phone</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Patient name or phone..." value="<?= e($searchPatient) ?>">
        </div>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-semibold">Filter by Date</label>
        <input type="date" name="date" class="form-control" value="<?= e($filterDate) ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-semibold">Status</label>
        <select name="status" class="form-select">
          <option value="ALL" <?= $filterStatus === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
          <option value="BOOKED" <?= $filterStatus === 'BOOKED' ? 'selected' : '' ?>>Booked (Pending)</option>
          <option value="CONFIRMED" <?= $filterStatus === 'CONFIRMED' ? 'selected' : '' ?>>Confirmed</option>
          <option value="COMPLETED" <?= $filterStatus === 'COMPLETED' ? 'selected' : '' ?>>Completed</option>
          <option value="CANCELLED" <?= $filterStatus === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
          <option value="NO_SHOW" <?= $filterStatus === 'NO_SHOW' ? 'selected' : '' ?>>No-Show</option>
        </select>
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-teal w-100 py-2">Filter</button>
        <a href="<?= url('provider/appointments.php') ?>" class="btn btn-outline-secondary py-2">Reset</a>
      </div>
    </form>
  </div>

  <!-- Appointments Table -->
  <div class="card card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0">Appointments (<?= count($appointments) ?>)</h5>
    </div>

    <?php if (empty($appointments)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
        <p class="mb-0">No patient appointments match your criteria.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Ref No</th>
              <th>Patient</th>
              <th>Date & Time</th>
              <th>Contact Info</th>
              <th>Notes / Reason</th>
              <th>Status</th>
              <th class="text-end">Manage Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($appointments as $apt): ?>
              <tr>
                <td><code class="fw-bold text-dark">#APT-<?= str_pad($apt['Appointment_ID'], 5, '0', STR_PAD_LEFT) ?></code></td>
                <td>
                  <div class="fw-bold"><?= e($apt['Patient_First'] . ' ' . $apt['Patient_Last']) ?></div>
                  <small class="text-muted"><?= e($apt['Gender'] ?: 'Gender N/A') ?> • <?= e($apt['Client_City']) ?></small>
                </td>
                <td>
                  <div class="fw-semibold text-teal"><?= formatDate($apt['Slot_Date']) ?></div>
                  <small class="text-muted"><?= formatTime($apt['Start_Time']) ?> – <?= formatTime($apt['End_Time']) ?></small>
                </td>
                <td>
                  <div class="small fw-semibold"><?= e($apt['Patient_Phone']) ?></div>
                  <small class="text-muted"><?= e($apt['Patient_Email']) ?></small>
                </td>
                <td>
                  <small class="text-secondary text-truncate d-inline-block" style="max-width: 150px;" title="<?= e($apt['Notes']) ?>">
                    <?= e($apt['Notes'] ?: 'None') ?>
                  </small>
                </td>
                <td><?= renderStatusBadge($apt['Appt_Status']) ?></td>
                <td class="text-end">
                  <!-- Action Dropdown for Status Transition -->
                  <div class="dropdown d-inline-block">
                    <button class="btn btn-outline-teal btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                      Update
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                      <?php if ($apt['Appt_Status'] === 'BOOKED'): ?>
                        <li>
                          <form method="POST" action="<?= url('provider/appointments.php') ?>">
                            <?= CSRF::inputField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="appointment_id" value="<?= $apt['Appointment_ID'] ?>">
                            <input type="hidden" name="status" value="CONFIRMED">
                            <button type="submit" class="dropdown-item text-info"><i class="bi bi-check-circle me-2"></i> Confirm Appointment</button>
                          </form>
                        </li>
                      <?php endif; ?>

                      <?php if (in_array($apt['Appt_Status'], ['BOOKED', 'CONFIRMED'])): ?>
                        <li>
                          <form method="POST" action="<?= url('provider/appointments.php') ?>">
                            <?= CSRF::inputField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="appointment_id" value="<?= $apt['Appointment_ID'] ?>">
                            <input type="hidden" name="status" value="COMPLETED">
                            <button type="submit" class="dropdown-item text-primary"><i class="bi bi-check2-all me-2"></i> Mark as Completed</button>
                          </form>
                        </li>
                        <li>
                          <form method="POST" action="<?= url('provider/appointments.php') ?>">
                            <?= CSRF::inputField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="appointment_id" value="<?= $apt['Appointment_ID'] ?>">
                            <input type="hidden" name="status" value="NO_SHOW">
                            <button type="submit" class="dropdown-item text-secondary"><i class="bi bi-person-x me-2"></i> Mark as No-Show</button>
                          </form>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <form method="POST" action="<?= url('provider/appointments.php') ?>" onsubmit="return confirm('Cancel this appointment?');">
                            <?= CSRF::inputField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="appointment_id" value="<?= $apt['Appointment_ID'] ?>">
                            <input type="hidden" name="status" value="CANCELLED">
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-x-circle me-2"></i> Cancel Appointment</button>
                          </form>
                        </li>
                      <?php else: ?>
                        <li><span class="dropdown-item-text text-muted small">No actions for completed/cancelled</span></li>
                      <?php endif; ?>
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
