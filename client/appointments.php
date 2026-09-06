<?php
/**
 * Client Appointment History & Cancellation Management
 */

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

// Handle Cancellation Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_appointment') {
    CSRF::check();

    $appointmentId = (int)($_POST['appointment_id'] ?? 0);

    // Fetch Appointment & Slot
    $chkStmt = $db->prepare("
        SELECT a.Appointment_ID, a.Status AS Appt_Status, a.Slot_ID, s.Slot_Date, s.Start_Time
        FROM `APPOINTMENT` a
        JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID
        WHERE a.Appointment_ID = ? AND a.Client_ID = ?
    ");
    $chkStmt->execute([$appointmentId, $clientId]);
    $appt = $chkStmt->fetch();

    if ($appt) {
        if (in_array($appt['Appt_Status'], ['BOOKED', 'CONFIRMED'])) {
            $db->beginTransaction();
            try {
                // Update Appointment Status to CANCELLED
                $db->prepare("UPDATE `APPOINTMENT` SET Status = 'CANCELLED' WHERE Appointment_ID = ?")->execute([$appointmentId]);

                // If slot is in future, restore it to AVAILABLE for other patients
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

// Filter Status
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

// Monthly Quota Summary
$quota = getMonthlyBookingQuota($clientId, $userId);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-calendar2-check text-teal me-2"></i> My Appointment Bookings</h2>
      <p class="text-muted small mb-0">Track your upcoming consultations, medical receipts, and cancellation history.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= url('client/search.php') ?>" class="btn btn-teal btn-sm">
        <i class="bi bi-plus-circle me-1"></i> Book New Appointment
      </a>
      <a href="<?= url('client/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
      </a>
    </div>
  </div>

  <!-- Monthly Quota Widget -->
  <div class="card card-custom p-3 mb-4 bg-light border-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
      <div class="small fw-semibold">
        <i class="bi bi-info-circle text-teal me-1"></i>
        <span>Monthly Booking Quota (<?= date('F Y') ?>): <strong><?= $quota['used'] ?> of <?= $quota['limit'] ?> used</strong> (<?= $quota['remaining'] ?> remaining)</span>
      </div>
      <div>
        <a href="<?= url('client/subscription.php') ?>" class="small text-teal fw-bold text-decoration-none">
          View Subscription & Quotas <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- Filters & Appointments Table -->
  <div class="card card-custom p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
      <h5 class="fw-bold mb-0">Bookings History</h5>

      <div class="d-flex gap-2">
        <a href="<?= url('client/appointments.php?status=ALL') ?>" class="btn btn-sm <?= $filterStatus === 'ALL' ? 'btn-teal' : 'btn-outline-secondary' ?>">All</a>
        <a href="<?= url('client/appointments.php?status=BOOKED') ?>" class="btn btn-sm <?= $filterStatus === 'BOOKED' ? 'btn-teal' : 'btn-outline-secondary' ?>">Booked</a>
        <a href="<?= url('client/appointments.php?status=CONFIRMED') ?>" class="btn btn-sm <?= $filterStatus === 'CONFIRMED' ? 'btn-teal' : 'btn-outline-secondary' ?>">Confirmed</a>
        <a href="<?= url('client/appointments.php?status=COMPLETED') ?>" class="btn btn-sm <?= $filterStatus === 'COMPLETED' ? 'btn-teal' : 'btn-outline-secondary' ?>">Completed</a>
        <a href="<?= url('client/appointments.php?status=CANCELLED') ?>" class="btn btn-sm <?= $filterStatus === 'CANCELLED' ? 'btn-teal' : 'btn-outline-secondary' ?>">Cancelled</a>
      </div>
    </div>

    <?php if (empty($appointments)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
        <h5 class="fw-bold">No Appointments Found</h5>
        <p class="small text-muted mb-3">You don't have any appointments matching the selected status.</p>
        <a href="<?= url('client/search.php') ?>" class="btn btn-teal btn-sm">Find Doctors & Book</a>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Ref No</th>
              <th>Provider / Doctor</th>
              <th>Date & Schedule</th>
              <th>Location & Contact</th>
              <th>Status</th>
              <th>Notes</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($appointments as $apt): ?>
              <tr>
                <td><code class="fw-bold text-dark">#APT-<?= str_pad($apt['Appointment_ID'], 5, '0', STR_PAD_LEFT) ?></code></td>
                <td>
                  <?php if ($apt['Doc_First']): ?>
                    <div class="fw-bold">Dr. <?= e($apt['Doc_First'] . ' ' . $apt['Doc_Last']) ?></div>
                    <small class="text-teal"><?= e($apt['Specializations'] ?: 'Consultant') ?></small>
                  <?php else: ?>
                    <div class="fw-bold"><?= e($apt['Centre_Name'] ?: $apt['Business_Name']) ?></div>
                    <small class="text-muted">Healthcare Centre</small>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="fw-semibold text-dark"><?= formatDate($apt['Slot_Date']) ?></div>
                  <small class="text-teal fw-semibold"><?= formatTime($apt['Start_Time']) ?> – <?= formatTime($apt['End_Time']) ?></small>
                </td>
                <td>
                  <div class="small"><?= e($apt['City']) ?></div>
                  <small class="text-muted"><?= e($apt['Contact_Number']) ?></small>
                </td>
                <td><?= renderStatusBadge($apt['Appt_Status']) ?></td>
                <td>
                  <small class="text-secondary text-truncate d-inline-block" style="max-width: 150px;">
                    <?= e($apt['Notes'] ?: '—') ?>
                  </small>
                </td>
                <td class="text-end">
                  <?php if (in_array($apt['Appt_Status'], ['BOOKED', 'CONFIRMED'])): ?>
                    <form method="POST" action="<?= url('client/appointments.php') ?>" onsubmit="return confirm('Are you sure you want to cancel appointment #APT-<?= str_pad($apt['Appointment_ID'], 5, '0', STR_PAD_LEFT) ?>? Your quota will be restored.');">
                      <?= CSRF::inputField() ?>
                      <input type="hidden" name="action" value="cancel_appointment">
                      <input type="hidden" name="appointment_id" value="<?= $apt['Appointment_ID'] ?>">
                      <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
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
