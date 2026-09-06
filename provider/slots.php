<?php
/**
 * Provider Slot Management (Single, Daily Batch & Weekly Recurring with Overlap Prevention)
 */

$pageTitle = 'Manage Schedule Availability Slots';
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

// Check if provider has active subscription
$hasSub = hasActiveSubscription($userId);

// Fetch doctors if healthcare centre
$affiliatedDoctors = [];
$myDoctorId = null;
if ($providerType === PROVIDER_DOCTOR) {
    $dRow = $db->query("SELECT Doctor_ID FROM `DOCTOR` WHERE Provider_ID = " . (int)$providerId)->fetch();
    $myDoctorId = $dRow ? (int)$dRow['Doctor_ID'] : null;
} else { // HEALTHCARE_CENTRE
    $cRow = $db->query("SELECT Centre_ID FROM `HEALTHCARE_CENTRE` WHERE Provider_ID = " . (int)$providerId)->fetch();
    if ($cRow) {
        $cId = (int)$cRow['Centre_ID'];
        $docListStmt = $db->prepare("
            SELECT d.Doctor_ID, u.First_Name, u.Last_Name, d.Medical_License_No
            FROM `CENTRE_DOCTOR_LINK` cdl
            JOIN `DOCTOR` d ON cdl.Doctor_ID = d.Doctor_ID
            JOIN `PROVIDER` p ON d.Provider_ID = p.Provider_ID
            JOIN `USER` u ON p.User_ID = u.User_ID
            WHERE cdl.Centre_ID = ? AND cdl.Status = 'ACTIVE'
        ");
        $docListStmt->execute([$cId]);
        $affiliatedDoctors = $docListStmt->fetchAll();
    }
}

// Function to check slot overlap for a provider
function hasSlotOverlap(PDO $db, int $providerId, string $date, string $startTime, string $endTime, ?int $excludeSlotId = null): bool {
    $sql = "
        SELECT COUNT(*) AS overlap_count
        FROM `SCHEDULED_SLOT`
        WHERE Provider_ID = ?
          AND Slot_Date = ?
          AND Status != 'BLOCKED'
          AND (Start_Time < ? AND End_Time > ?)
    ";
    $params = [$providerId, $date, $endTime, $startTime];
    if ($excludeSlotId) {
        $sql .= " AND Slot_ID != ?";
        $params[] = $excludeSlotId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return ((int)$stmt->fetch()['overlap_count'] > 0);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = $_POST['action'] ?? '';

    // Action: Create Single Slot
    if ($action === 'create_single') {
        $slotDate = $_POST['slot_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $doctorId = ($providerType === PROVIDER_DOCTOR) ? $myDoctorId : (!empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null);

        if (empty($slotDate) || empty($startTime) || empty($endTime)) {
            setFlash('danger', 'Please provide date, start time, and end time.');
        } elseif ($slotDate < date('Y-m-d')) {
            setFlash('danger', 'Cannot create availability slots in the past.');
        } elseif ($startTime >= $endTime) {
            setFlash('danger', 'End time must be later than start time.');
        } elseif (hasSlotOverlap($db, $providerId, $slotDate, $startTime, $endTime)) {
            setFlash('danger', 'Slot overlaps with an existing scheduled slot for this date.');
        } else {
            $ins = $db->prepare("
                INSERT INTO `SCHEDULED_SLOT` (Provider_ID, Doctor_ID, Slot_Date, Start_Time, End_Time, Status)
                VALUES (?, ?, ?, ?, ?, 'AVAILABLE')
            ");
            $ins->execute([$providerId, $doctorId, $slotDate, $startTime, $endTime]);
            setFlash('success', 'Availability slot created successfully.');
            redirect('provider/slots.php');
        }

    // Action: Generate Daily Batch Slots
    } elseif ($action === 'generate_batch') {
        $slotDate = $_POST['batch_date'] ?? '';
        $windowStart = $_POST['window_start'] ?? '09:00';
        $windowEnd = $_POST['window_end'] ?? '13:00';
        $durationMins = max(10, (int)($_POST['duration_mins'] ?? 20));
        $breakMins = max(0, (int)($_POST['break_mins'] ?? 0));
        $doctorId = ($providerType === PROVIDER_DOCTOR) ? $myDoctorId : (!empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null);

        if (empty($slotDate) || $slotDate < date('Y-m-d')) {
            setFlash('danger', 'Invalid batch date.');
        } elseif ($windowStart >= $windowEnd) {
            setFlash('danger', 'Batch window end time must be after start time.');
        } else {
            $currentTimestamp = strtotime("$slotDate $windowStart");
            $endTimestamp = strtotime("$slotDate $windowEnd");
            $inserted = 0;
            $skipped = 0;

            $insStmt = $db->prepare("
                INSERT INTO `SCHEDULED_SLOT` (Provider_ID, Doctor_ID, Slot_Date, Start_Time, End_Time, Status)
                VALUES (?, ?, ?, ?, ?, 'AVAILABLE')
            ");

            while (($currentTimestamp + ($durationMins * 60)) <= $endTimestamp) {
                $slotStart = date('H:i:s', $currentTimestamp);
                $slotEnd = date('H:i:s', $currentTimestamp + ($durationMins * 60));

                if (!hasSlotOverlap($db, $providerId, $slotDate, $slotStart, $slotEnd)) {
                    $insStmt->execute([$providerId, $doctorId, $slotDate, $slotStart, $slotEnd]);
                    $inserted++;
                } else {
                    $skipped++;
                }

                $currentTimestamp += (($durationMins + $breakMins) * 60);
            }

            setFlash('success', "Batch generated: {$inserted} slots created successfully" . ($skipped > 0 ? " ({$skipped} overlapping slots skipped)" : "") . ".");
            redirect('provider/slots.php');
        }

    // Action: Generate Weekly Recurring Slots
    } elseif ($action === 'generate_recurring') {
        $startDate = $_POST['recur_start_date'] ?? date('Y-m-d');
        $endDate = $_POST['recur_end_date'] ?? date('Y-m-d', strtotime('+14 days'));
        $daysOfWeek = $_POST['days_of_week'] ?? []; // array of 1 (Mon) to 7 (Sun)
        $windowStart = $_POST['recur_window_start'] ?? '09:00';
        $windowEnd = $_POST['recur_window_end'] ?? '12:00';
        $durationMins = max(10, (int)($_POST['recur_duration'] ?? 20));
        $doctorId = ($providerType === PROVIDER_DOCTOR) ? $myDoctorId : (!empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null);

        if (empty($daysOfWeek)) {
            setFlash('danger', 'Please select at least one day of the week.');
        } elseif ($startDate > $endDate || $startDate < date('Y-m-d')) {
            setFlash('danger', 'Invalid recurring date range.');
        } else {
            $currentDate = new DateTime($startDate);
            $finalDate = new DateTime($endDate);
            $inserted = 0;
            $skipped = 0;

            $insStmt = $db->prepare("
                INSERT INTO `SCHEDULED_SLOT` (Provider_ID, Doctor_ID, Slot_Date, Start_Time, End_Time, Status)
                VALUES (?, ?, ?, ?, ?, 'AVAILABLE')
            ");

            while ($currentDate <= $finalDate) {
                $dayNum = $currentDate->format('N'); // 1 (Mon) to 7 (Sun)
                if (in_array($dayNum, $daysOfWeek)) {
                    $currDateStr = $currentDate->format('Y-m-d');
                    $currentTimestamp = strtotime("$currDateStr $windowStart");
                    $endTimestamp = strtotime("$currDateStr $windowEnd");

                    while (($currentTimestamp + ($durationMins * 60)) <= $endTimestamp) {
                        $slotStart = date('H:i:s', $currentTimestamp);
                        $slotEnd = date('H:i:s', $currentTimestamp + ($durationMins * 60));

                        if (!hasSlotOverlap($db, $providerId, $currDateStr, $slotStart, $slotEnd)) {
                            $insStmt->execute([$providerId, $doctorId, $currDateStr, $slotStart, $slotEnd]);
                            $inserted++;
                        } else {
                            $skipped++;
                        }

                        $currentTimestamp += ($durationMins * 60);
                    }
                }
                $currentDate->modify('+1 day');
            }

            setFlash('success', "Weekly schedule created: {$inserted} recurring slots published successfully" . ($skipped > 0 ? " ({$skipped} skipped due to overlaps)" : "") . ".");
            redirect('provider/slots.php');
        }

    // Action: Block Slot
    } elseif ($action === 'block_slot') {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        
        // Ensure slot belongs to provider and is not booked
        $sChk = $db->prepare("SELECT Status FROM `SCHEDULED_SLOT` WHERE Slot_ID = ? AND Provider_ID = ?");
        $sChk->execute([$slotId, $providerId]);
        $slot = $sChk->fetch();

        if ($slot && $slot['Status'] === 'AVAILABLE') {
            $db->prepare("UPDATE `SCHEDULED_SLOT` SET Status = 'BLOCKED' WHERE Slot_ID = ?")->execute([$slotId]);
            setFlash('info', 'Slot has been blocked from patient booking.');
        } else {
            setFlash('danger', 'Cannot block this slot (already booked or invalid).');
        }
        redirect('provider/slots.php');

    // Action: Unblock Slot
    } elseif ($action === 'unblock_slot') {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        $sChk = $db->prepare("SELECT Status FROM `SCHEDULED_SLOT` WHERE Slot_ID = ? AND Provider_ID = ?");
        $sChk->execute([$slotId, $providerId]);
        $slot = $sChk->fetch();

        if ($slot && $slot['Status'] === 'BLOCKED') {
            $db->prepare("UPDATE `SCHEDULED_SLOT` SET Status = 'AVAILABLE' WHERE Slot_ID = ?")->execute([$slotId]);
            setFlash('success', 'Slot has been unblocked and is now available for booking.');
        }
        redirect('provider/slots.php');

    // Action: Delete Available Slot
    } elseif ($action === 'delete_slot') {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        
        // Check if there is an appointment attached
        $aChk = $db->prepare("SELECT COUNT(*) AS appt_count FROM `APPOINTMENT` WHERE Slot_ID = ?");
        $aChk->execute([$slotId]);
        $hasAppt = (int)$aChk->fetch()['appt_count'] > 0;

        if ($hasAppt) {
            setFlash('danger', 'Cannot delete a slot that contains appointment history. You can mark the appointment as cancelled instead.');
        } else {
            $del = $db->prepare("DELETE FROM `SCHEDULED_SLOT` WHERE Slot_ID = ? AND Provider_ID = ? AND Status != 'BOOKED'");
            $del->execute([$slotId, $providerId]);
            setFlash('success', 'Slot deleted successfully.');
        }
        redirect('provider/slots.php');
    }
}

// Fetch Slots for Display
$filterDate = $_GET['filter_date'] ?? date('Y-m-d');
$filterStatus = $_GET['filter_status'] ?? 'ALL';

$query = "
    SELECT s.*, 
           u.First_Name AS Doc_First, u.Last_Name AS Doc_Last, d.Medical_License_No,
           a.Appointment_ID, a.Status AS Appt_Status,
           pu.First_Name AS Patient_First, pu.Last_Name AS Patient_Last
    FROM `SCHEDULED_SLOT` s
    LEFT JOIN `DOCTOR` d ON s.Doctor_ID = d.Doctor_ID
    LEFT JOIN `PROVIDER` p ON d.Provider_ID = p.Provider_ID
    LEFT JOIN `USER` u ON p.User_ID = u.User_ID
    LEFT JOIN `APPOINTMENT` a ON s.Slot_ID = a.Slot_ID
    LEFT JOIN `CLIENT` c ON a.Client_ID = c.Client_ID
    LEFT JOIN `USER` pu ON c.User_ID = pu.User_ID
    WHERE s.Provider_ID = ?
";
$params = [$providerId];

if (!empty($filterDate)) {
    $query .= " AND s.Slot_Date = ?";
    $params[] = $filterDate;
}
if ($filterStatus !== 'ALL') {
    $query .= " AND s.Status = ?";
    $params[] = $filterStatus;
}
$query .= " ORDER BY s.Slot_Date ASC, s.Start_Time ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$slots = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-calendar2-range text-teal me-2"></i> Schedule Slot Management</h2>
      <p class="text-muted small mb-0">Publish single, daily batch, or weekly recurring slots with automated overlap validation.</p>
    </div>
    <a href="<?= url('provider/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
  </div>

  <?php if (!$hasSub): ?>
    <div class="alert alert-warning mb-4">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <strong>Subscription Notice:</strong> Your provider subscription is currently inactive. You may configure slots, but active subscriptions are required for full patient visibility.
      <a href="<?= url('provider/subscription.php') ?>" class="alert-link ms-2">Renew now</a>.
    </div>
  <?php endif; ?>

  <!-- Slot Generator Accordion / Cards -->
  <div class="card card-custom p-4 mb-4">
    <ul class="nav nav-pills mb-3" id="slotTabs" role="tablist">
      <li class="nav-item">
        <button class="nav-link active" id="single-tab" data-bs-toggle="pill" data-bs-target="#tab-single" type="button">
          <i class="bi bi-plus-circle me-1"></i> Single Slot
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" id="batch-tab" data-bs-toggle="pill" data-bs-target="#tab-batch" type="button">
          <i class="bi bi-calendar-range me-1"></i> Daily Batch Generator
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" id="recurring-tab" data-bs-toggle="pill" data-bs-target="#tab-recurring" type="button">
          <i class="bi bi-arrow-repeat me-1"></i> Weekly Recurring Generator
        </button>
      </li>
    </ul>

    <div class="tab-content" id="slotTabContent">
      <!-- 1. Single Slot Generator -->
      <div class="tab-pane fade show active" id="tab-single">
        <form method="POST" action="<?= url('provider/slots.php') ?>" class="row g-3 align-items-end">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="create_single">

          <div class="col-md-3">
            <label class="form-label small fw-semibold">Slot Date</label>
            <input type="date" name="slot_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Start Time</label>
            <input type="time" name="start_time" class="form-control" value="09:00" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">End Time</label>
            <input type="time" name="end_time" class="form-control" value="09:20" required>
          </div>

          <?php if ($providerType === PROVIDER_CENTRE): ?>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Assigned Doctor</label>
              <select name="doctor_id" class="form-select">
                <option value="">General Facility Slot</option>
                <?php foreach ($affiliatedDoctors as $ad): ?>
                  <option value="<?= $ad['Doctor_ID'] ?>">Dr. <?= e($ad['First_Name'] . ' ' . $ad['Last_Name']) ?> (<?= e($ad['Medical_License_No']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="col-md-2">
            <button type="submit" class="btn btn-teal w-100 py-2">
              <i class="bi bi-plus-lg me-1"></i> Add Slot
            </button>
          </div>
        </form>
      </div>

      <!-- 2. Daily Batch Generator -->
      <div class="tab-pane fade" id="tab-batch">
        <form method="POST" action="<?= url('provider/slots.php') ?>" class="row g-3 align-items-end">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="generate_batch">

          <div class="col-md-3">
            <label class="form-label small fw-semibold">Target Date</label>
            <input type="date" name="batch_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Window Start</label>
            <input type="time" name="window_start" class="form-control" value="09:00" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Window End</label>
            <input type="time" name="window_end" class="form-control" value="13:00" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Slot Duration (Mins)</label>
            <input type="number" name="duration_mins" class="form-control" value="20" min="10" max="120" step="5" required>
          </div>
          <div class="col-md-1">
            <label class="form-label small fw-semibold">Break</label>
            <input type="number" name="break_mins" class="form-control" value="0" min="0" max="30" step="5">
          </div>

          <?php if ($providerType === PROVIDER_CENTRE): ?>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">Assigned Doctor</label>
              <select name="doctor_id" class="form-select">
                <option value="">General Facility</option>
                <?php foreach ($affiliatedDoctors as $ad): ?>
                  <option value="<?= $ad['Doctor_ID'] ?>">Dr. <?= e($ad['First_Name'] . ' ' . $ad['Last_Name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="col-md-2">
            <button type="submit" class="btn btn-teal w-100 py-2">
              <i class="bi bi-magic me-1"></i> Auto-Generate
            </button>
          </div>
        </form>
      </div>

      <!-- 3. Weekly Recurring Generator -->
      <div class="tab-pane fade" id="tab-recurring">
        <form method="POST" action="<?= url('provider/slots.php') ?>">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="generate_recurring">

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Start Date</label>
              <input type="date" name="recur_start_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">End Date (Up to 4 weeks)</label>
              <input type="date" name="recur_end_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+21 days')) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">Window Start</label>
              <input type="time" name="recur_window_start" class="form-control" value="16:00" required>
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">Window End</label>
              <input type="time" name="recur_window_end" class="form-control" value="19:00" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Select Recurring Weekdays</label>
            <div class="d-flex flex-wrap gap-3 p-2 bg-light rounded-3 border">
              <div class="form-check"><input class="form-check-input" type="checkbox" name="days_of_week[]" value="1" id="d1" checked><label class="form-check-label small" for="d1">Monday</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="days_of_week[]" value="2" id="d2"><label class="form-check-label small" for="d2">Tuesday</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="days_of_week[]" value="3" id="d3" checked><label class="form-check-label small" for="d3">Wednesday</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="days_of_week[]" value="4" id="d4"><label class="form-check-label small" for="d4">Thursday</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="days_of_week[]" value="5" id="d5" checked><label class="form-check-label small" for="d5">Friday</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="days_of_week[]" value="6" id="d6"><label class="form-check-label small" for="d6">Saturday</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="days_of_week[]" value="7" id="d7"><label class="form-check-label small" for="d7">Sunday</label></div>
            </div>
          </div>

          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Slot Duration</label>
              <input type="number" name="recur_duration" class="form-control" value="20" min="10" max="60" step="5">
            </div>
            <?php if ($providerType === PROVIDER_CENTRE): ?>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Assigned Doctor</label>
                <select name="doctor_id" class="form-select">
                  <option value="">General Facility</option>
                  <?php foreach ($affiliatedDoctors as $ad): ?>
                    <option value="<?= $ad['Doctor_ID'] ?>">Dr. <?= e($ad['First_Name'] . ' ' . $ad['Last_Name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
            <div class="col-md-3">
              <button type="submit" class="btn btn-teal w-100 py-2">
                <i class="bi bi-calendar-plus-fill me-1"></i> Generate Weekly Slots
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Slots Filter & Table -->
  <div class="card card-custom p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
      <h5 class="fw-bold mb-0"><i class="bi bi-clock-history text-teal me-2"></i> Published Slots</h5>

      <!-- Filter Form -->
      <form method="GET" action="<?= url('provider/slots.php') ?>" class="d-flex flex-wrap gap-2 align-items-center">
        <input type="date" name="filter_date" class="form-control form-control-sm w-auto" value="<?= e($filterDate) ?>" onchange="this.form.submit()">
        <select name="filter_status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
          <option value="ALL" <?= $filterStatus === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
          <option value="AVAILABLE" <?= $filterStatus === 'AVAILABLE' ? 'selected' : '' ?>>Available</option>
          <option value="BOOKED" <?= $filterStatus === 'BOOKED' ? 'selected' : '' ?>>Booked</option>
          <option value="BLOCKED" <?= $filterStatus === 'BLOCKED' ? 'selected' : '' ?>>Blocked</option>
        </select>
        <a href="<?= url('provider/slots.php?filter_date=&filter_status=ALL') ?>" class="btn btn-outline-secondary btn-sm">Clear Filter</a>
      </form>
    </div>

    <?php if (empty($slots)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
        <p class="mb-0">No slots matching your filter criteria.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th>Time Window</th>
              <?php if ($providerType === PROVIDER_CENTRE): ?><th>Doctor</th><?php endif; ?>
              <th>Status</th>
              <th>Patient / Booking Note</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($slots as $s): ?>
              <tr>
                <td>
                  <span class="fw-bold text-dark"><?= formatDate($s['Slot_Date']) ?></span>
                  <small class="text-muted d-block"><?= date('l', strtotime($s['Slot_Date'])) ?></small>
                </td>
                <td>
                  <span class="badge bg-light text-teal border fw-bold">
                    <?= formatTime($s['Start_Time']) ?> – <?= formatTime($s['End_Time']) ?>
                  </span>
                </td>
                <?php if ($providerType === PROVIDER_CENTRE): ?>
                  <td>
                    <?php if ($s['Doc_First']): ?>
                      <span class="fw-semibold">Dr. <?= e($s['Doc_First'] . ' ' . $s['Doc_Last']) ?></span>
                    <?php else: ?>
                      <span class="text-muted small">General Facility</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td><?= renderStatusBadge($s['Status']) ?></td>
                <td>
                  <?php if ($s['Patient_First']): ?>
                    <div class="fw-semibold"><?= e($s['Patient_First'] . ' ' . $s['Patient_Last']) ?></div>
                    <small class="text-muted"><?= renderStatusBadge($s['Appt_Status']) ?></small>
                  <?php else: ?>
                    <span class="text-muted small">None</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <?php if ($s['Status'] === 'AVAILABLE'): ?>
                      <!-- Block Button -->
                      <form method="POST" action="<?= url('provider/slots.php') ?>" onsubmit="return confirm('Block this slot from client booking?');">
                        <?= CSRF::inputField() ?>
                        <input type="hidden" name="action" value="block_slot">
                        <input type="hidden" name="slot_id" value="<?= $s['Slot_ID'] ?>">
                        <button type="submit" class="btn btn-outline-warning btn-sm" title="Block Slot">
                          <i class="bi bi-slash-circle"></i>
                        </button>
                      </form>
                      <!-- Delete Button -->
                      <form method="POST" action="<?= url('provider/slots.php') ?>" onsubmit="return confirm('Delete this slot permanently?');">
                        <?= CSRF::inputField() ?>
                        <input type="hidden" name="action" value="delete_slot">
                        <input type="hidden" name="slot_id" value="<?= $s['Slot_ID'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Slot">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    <?php elseif ($s['Status'] === 'BLOCKED'): ?>
                      <!-- Unblock Button -->
                      <form method="POST" action="<?= url('provider/slots.php') ?>">
                        <?= CSRF::inputField() ?>
                        <input type="hidden" name="action" value="unblock_slot">
                        <input type="hidden" name="slot_id" value="<?= $s['Slot_ID'] ?>">
                        <button type="submit" class="btn btn-outline-success btn-sm" title="Unblock Slot">
                          <i class="bi bi-check2"></i> Unblock
                        </button>
                      </form>
                      <!-- Delete Button -->
                      <form method="POST" action="<?= url('provider/slots.php') ?>" onsubmit="return confirm('Delete this slot permanently?');">
                        <?= CSRF::inputField() ?>
                        <input type="hidden" name="action" value="delete_slot">
                        <input type="hidden" name="slot_id" value="<?= $s['Slot_ID'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Slot">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    <?php elseif ($s['Status'] === 'BOOKED'): ?>
                      <a href="<?= url('provider/appointments.php?id=' . $s['Appointment_ID']) ?>" class="btn btn-outline-teal btn-sm">
                        View Appt
                      </a>
                    <?php endif; ?>
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
