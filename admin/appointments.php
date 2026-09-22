<?php


$pageTitle = 'Manage System Appointments';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    CSRF::check();
    $action = $_POST['action'];
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);

    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        $allowedStatuses = ['PENDING', 'BOOKED', 'CONFIRMED', 'REJECTED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'];

        if (in_array($newStatus, $allowedStatuses) && $appointmentId > 0) {
            try {
                $db->beginTransaction();

                
                $aStmt = $db->prepare("SELECT Slot_ID, Status FROM `APPOINTMENT` WHERE Appointment_ID = ? FOR UPDATE");
                $aStmt->execute([$appointmentId]);
                $currentAppt = $aStmt->fetch();

                if ($currentAppt) {
                    $uStmt = $db->prepare("UPDATE `APPOINTMENT` SET Status = ? WHERE Appointment_ID = ?");
                    $uStmt->execute([$newStatus, $appointmentId]);

                    
                    if (in_array($newStatus, ['CANCELLED', 'REJECTED'], true)) {
                        $sStmt = $db->prepare("UPDATE `SCHEDULED_SLOT` SET Status = 'AVAILABLE' WHERE Slot_ID = ?");
                        $sStmt->execute([$currentAppt['Slot_ID']]);
                    } elseif ($currentAppt['Status'] === 'CANCELLED' && $newStatus !== 'CANCELLED') {
                        
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


$mStmt = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN Status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN Status = 'BOOKED' THEN 1 ELSE 0 END) AS booked,
        SUM(CASE WHEN Status = 'CONFIRMED' THEN 1 ELSE 0 END) AS confirmed,
        SUM(CASE WHEN Status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN Status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled,
        SUM(CASE WHEN Status = 'NO_SHOW' THEN 1 ELSE 0 END) AS no_show
    FROM `APPOINTMENT`
");
$metrics = $mStmt->fetch();


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

$statusMeta = [
    'PENDING' => ['class' => 'warning', 'icon' => 'hourglass-split', 'label' => 'Pending'],
    'BOOKED' => ['class' => 'primary', 'icon' => 'calendar2-check', 'label' => 'Booked'],
    'CONFIRMED' => ['class' => 'info', 'icon' => 'check2-circle', 'label' => 'Confirmed'],
    'COMPLETED' => ['class' => 'success', 'icon' => 'patch-check', 'label' => 'Completed'],
    'REJECTED' => ['class' => 'danger', 'icon' => 'x-octagon', 'label' => 'Rejected'],
    'CANCELLED' => ['class' => 'danger', 'icon' => 'x-circle', 'label' => 'Cancelled'],
    'NO_SHOW' => ['class' => 'warning', 'icon' => 'person-x', 'label' => 'No-show'],
];
?>

<style>
.admin-appt-page{--aa-border:#e5e7eb;--aa-muted:#64748b;--aa-soft:#f8fafc}
.admin-appt-page .page-kicker{font-size:.76rem;letter-spacing:.08em;text-transform:uppercase;font-weight:700;color:var(--primary-teal,#087f78)}
.admin-appt-page .metric-card{height:100%;border:1px solid var(--aa-border);border-radius:16px;background:#fff;padding:1rem;box-shadow:0 4px 18px rgba(15,23,42,.035)}
.admin-appt-page .metric-icon{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;background:var(--aa-soft);font-size:1rem}
.admin-appt-page .filter-card,.admin-appt-page .appointment-card{border:1px solid var(--aa-border);border-radius:18px;background:#fff;box-shadow:0 5px 20px rgba(15,23,42,.035)}
.admin-appt-page .appointment-card{padding:1.15rem;transition:transform .18s ease,box-shadow .18s ease}
.admin-appt-page .appointment-card:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(15,23,42,.07)}
.admin-appt-page .appt-date{min-width:92px;border-radius:14px;background:var(--aa-soft);padding:.75rem;text-align:center}
.admin-appt-page .appt-date .day{font-size:1.35rem;font-weight:800;line-height:1.05}
.admin-appt-page .info-line{display:flex;gap:.55rem;align-items:flex-start;color:var(--aa-muted);font-size:.88rem}
.admin-appt-page .info-line i{width:18px;color:#94a3b8;margin-top:.05rem}
.admin-appt-page .status-pill{display:inline-flex;align-items:center;gap:.38rem;padding:.38rem .65rem;border-radius:999px;font-size:.76rem;font-weight:700}
.admin-appt-page .notes-box{background:var(--aa-soft);border-radius:12px;padding:.7rem .8rem;color:var(--aa-muted);font-size:.85rem}
.admin-appt-page .result-count{font-size:.88rem;color:var(--aa-muted)}
@media(max-width:767.98px){.admin-appt-page .appointment-card{padding:1rem}.admin-appt-page .appt-date{min-width:76px}.admin-appt-page .header-actions{width:100%}.admin-appt-page .header-actions .btn{flex:1}}
</style>

<div class="container py-4 admin-appt-page">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
      <div class="page-kicker mb-1">Admin workspace</div>
      <h2 class="fw-bold mb-1">Appointments</h2>
      <p class="text-muted mb-0">Review platform bookings and manage appointment lifecycle from one place.</p>
    </div>
    <div class="d-flex gap-2 header-actions">
      <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-grid me-1"></i> Dashboard</a>
      <a href="<?= url('admin/reports.php') ?>" class="btn btn-teal"><i class="bi bi-bar-chart-line me-1"></i> Reports</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <?php
      $metricItems = [
        ['Total', (int)$metrics['total'], 'calendar3', 'dark'],
        ['Booked', (int)$metrics['booked'], 'calendar2-check', 'primary'],
        ['Confirmed', (int)$metrics['confirmed'], 'check2-circle', 'info'],
        ['Completed', (int)$metrics['completed'], 'patch-check', 'success'],
        ['Cancelled', (int)$metrics['cancelled'], 'x-circle', 'danger'],
        ['No-show', (int)$metrics['no_show'], 'person-x', 'warning'],
      ];
    ?>
    <?php foreach ($metricItems as [$label,$value,$icon,$tone]): ?>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="metric-card">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div><div class="small text-muted fw-semibold mb-1"><?= e($label) ?></div><div class="fs-4 fw-bold mb-0"><?= $value ?></div></div>
            <div class="metric-icon text-<?= e($tone) ?>"><i class="bi bi-<?= e($icon) ?>"></i></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="filter-card p-3 mb-4">
    <form method="GET" action="<?= url('admin/appointments.php') ?>" class="row g-2 align-items-center">
      <div class="col-lg-6">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="search" name="q" class="form-control border-start-0 ps-0" placeholder="Patient, provider or appointment ID" value="<?= e($searchQuery) ?>">
        </div>
      </div>
      <div class="col-sm-7 col-lg-3">
        <select name="status" class="form-select">
          <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>All statuses</option>
          <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>Pending</option>
          <option value="BOOKED" <?= $statusFilter === 'BOOKED' ? 'selected' : '' ?>>Booked</option>
          <option value="REJECTED" <?= $statusFilter === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
          <option value="CONFIRMED" <?= $statusFilter === 'CONFIRMED' ? 'selected' : '' ?>>Confirmed</option>
          <option value="COMPLETED" <?= $statusFilter === 'COMPLETED' ? 'selected' : '' ?>>Completed</option>
          <option value="CANCELLED" <?= $statusFilter === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
          <option value="NO_SHOW" <?= $statusFilter === 'NO_SHOW' ? 'selected' : '' ?>>No-show</option>
        </select>
      </div>
      <div class="col-sm-5 col-lg-3 d-flex gap-2">
        <button class="btn btn-teal flex-grow-1" type="submit"><i class="bi bi-funnel me-1"></i> Filter</button>
        <?php if ($statusFilter !== 'ALL' || $searchQuery !== ''): ?>
          <a href="<?= url('admin/appointments.php') ?>" class="btn btn-outline-secondary" title="Clear filters"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0">Booking directory</h5>
    <div class="result-count"><?= count($appointments) ?> result<?= count($appointments) === 1 ? '' : 's' ?></div>
  </div>

  <?php if (empty($appointments)): ?>
    <div class="filter-card text-center py-5 px-3">
      <div class="metric-icon mx-auto mb-3"><i class="bi bi-calendar-x"></i></div>
      <h5 class="fw-bold">No appointments found</h5>
      <p class="text-muted small mb-3">No booking records match the current search and status filters.</p>
      <?php if ($statusFilter !== 'ALL' || $searchQuery !== ''): ?><a href="<?= url('admin/appointments.php') ?>" class="btn btn-outline-secondary btn-sm">Clear filters</a><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="d-grid gap-3">
      <?php foreach ($appointments as $a): ?>
        <?php $meta = $statusMeta[$a['Status']] ?? ['class'=>'secondary','icon'=>'circle','label'=>$a['Status']]; ?>
        <article class="appointment-card">
          <div class="d-flex flex-column flex-xl-row gap-3">
            <div class="d-flex gap-3 flex-grow-1">
              <div class="appt-date flex-shrink-0">
                <div class="small text-uppercase text-muted fw-bold"><?= e(date('M', strtotime($a['Slot_Date']))) ?></div>
                <div class="day"><?= e(date('d', strtotime($a['Slot_Date']))) ?></div>
                <div class="small text-muted"><?= e(date('D', strtotime($a['Slot_Date']))) ?></div>
              </div>
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                  <h5 class="fw-bold mb-0">#<?= (int)$a['Appointment_ID'] ?> · <?= e($a['Patient_First'].' '.$a['Patient_Last']) ?></h5>
                  <span class="status-pill bg-<?= e($meta['class']) ?> bg-opacity-10 text-<?= e($meta['class']) ?>"><i class="bi bi-<?= e($meta['icon']) ?>"></i><?= e($meta['label']) ?></span>
                </div>
                <div class="row g-2">
                  <div class="col-md-6">
                    <div class="info-line"><i class="bi bi-clock"></i><span><?= formatTime($a['Start_Time']) ?> – <?= formatTime($a['End_Time']) ?></span></div>
                    <div class="info-line mt-1"><i class="bi bi-person"></i><span><?= e($a['Patient_Email']) ?><?php if (!empty($a['Patient_Phone'])): ?> · <?= e($a['Patient_Phone']) ?><?php endif; ?></span></div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-line"><i class="bi bi-hospital"></i><span><strong class="text-dark"><?= e($a['Business_Name']) ?></strong> · <?= e($a['Provider_City']) ?></span></div>
                    <div class="info-line mt-1"><i class="bi bi-person-badge"></i><span><?php if ($a['Doctor_First']): ?>Dr. <?= e($a['Doctor_First'].' '.$a['Doctor_Last']) ?><?php if ($a['Medical_License_No']): ?> · <?= e($a['Medical_License_No']) ?><?php endif; ?><?php else: ?>Provider-managed appointment<?php endif; ?></span></div>
                  </div>
                </div>
                <?php if (!empty($a['Notes'])): ?><div class="notes-box mt-3"><i class="bi bi-chat-left-text me-2"></i><?= e($a['Notes']) ?></div><?php endif; ?>
              </div>
            </div>

            <div class="d-flex align-items-start justify-content-xl-end flex-shrink-0">
              <div class="dropdown w-100">
                <button class="btn btn-outline-secondary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown"><i class="bi bi-sliders me-1"></i> Update status</button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                  <?php foreach ([['CONFIRMED','check2-circle','Confirmed'],['COMPLETED','patch-check','Completed'],['NO_SHOW','person-x','No-show']] as [$newStatus,$icon,$label]): ?>
                    <?php if ($a['Status'] !== $newStatus): ?>
                    <li><form method="POST" action="<?= url('admin/appointments.php') ?>"><?= CSRF::inputField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="appointment_id" value="<?= (int)$a['Appointment_ID'] ?>"><input type="hidden" name="status" value="<?= e($newStatus) ?>"><button class="dropdown-item" type="submit"><i class="bi bi-<?= e($icon) ?> me-2"></i><?= e($label) ?></button></form></li>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <?php if ($a['Status'] !== 'CANCELLED'): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="<?= url('admin/appointments.php') ?>" data-confirm="Cancel appointment #<?= (int)$a['Appointment_ID'] ?>? The slot will be reopened for booking."><?= CSRF::inputField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="appointment_id" value="<?= (int)$a['Appointment_ID'] ?>"><input type="hidden" name="status" value="CANCELLED"><button class="dropdown-item text-danger" type="submit"><i class="bi bi-x-circle me-2"></i>Cancel & release slot</button></form></li>
                  <?php else: ?>
                    <li><hr class="dropdown-divider"></li><li><span class="dropdown-item-text small text-muted">Cancelled slot is available again.</span></li>
                  <?php endif; ?>
                </ul>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
