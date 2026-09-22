<?php


$pageTitle = 'Provider Verification & Management';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();
$currentAdmin = getCurrentUser();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_verification') {
    CSRF::check();

    $providerId = (int)($_POST['provider_id'] ?? 0);
    $newStatus = strtoupper(trim($_POST['verification_status'] ?? ''));
    $reason = trim($_POST['reason'] ?? '');

    if ($newStatus === 'REJECTED' && $reason === '') {
        setFlash('danger', 'Please provide a rejection reason.');
        redirect('admin/providers.php');
    }

    if (in_array($newStatus, ['PENDING', 'VERIFIED', 'REJECTED'])) {
        $db->beginTransaction();
        try {
            $oldStmt = $db->prepare("SELECT Verification_Status FROM `PROVIDER` WHERE Provider_ID = ? FOR UPDATE");
            $oldStmt->execute([$providerId]);
            $oldStatus = $oldStmt->fetchColumn();
            if (!$oldStatus) throw new RuntimeException('Provider not found.');

            $db->prepare("UPDATE `PROVIDER` SET Verification_Status = ? WHERE Provider_ID = ?")->execute([$newStatus, $providerId]);
            
            
            $db->prepare("UPDATE `DOCTOR` SET Verification_Status = ? WHERE Provider_ID = ?")->execute([$newStatus, $providerId]);
            $db->prepare("UPDATE `HEALTHCARE_CENTRE` SET Verification_Status = ? WHERE Provider_ID = ?")->execute([$newStatus, $providerId]);

            // Synchronize login access with provider verification.
            // VERIFIED => ACTIVE. PENDING/REJECTED => PENDING (login blocked).
            $accountStatus = ($newStatus === 'VERIFIED') ? 'ACTIVE' : 'PENDING';
            $userStatusStmt = $db->prepare("
                UPDATE `USER` u
                JOIN `PROVIDER` p ON p.User_ID = u.User_ID
                SET u.Account_Status = ?
                WHERE p.Provider_ID = ?
            ");
            $userStatusStmt->execute([$accountStatus, $providerId]);

            $hist = $db->prepare("INSERT INTO `PROVIDER_VERIFICATION_HISTORY` (Provider_ID,Admin_User_ID,Old_Status,New_Status,Reason) VALUES (?,?,?,?,?)");
            $hist->execute([$providerId, (int)$currentAdmin['user_id'], $oldStatus, $newStatus, $reason ?: null]);
            writeAuditLog($db, (int)$currentAdmin['user_id'], 'PROVIDER_VERIFICATION_CHANGED', 'PROVIDER', $providerId, $oldStatus . ' -> ' . $newStatus . ($reason ? ' | Reason: ' . $reason : ''));

            $db->commit();
            setFlash('success', "Provider #{$providerId} verification updated to {$newStatus}.");
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Error updating verification: ' . $e->getMessage());
        }
    } else {
        setFlash('danger', 'Invalid verification status.');
    }
    redirect('admin/providers.php');
}


$filterStatus = $_GET['status'] ?? 'ALL';
$filterType = $_GET['type'] ?? 'ALL';
$search = trim($_GET['q'] ?? '');

$query = "
    SELECT p.*, u.First_Name, u.Last_Name, u.Email, u.Account_Status,
           d.Doctor_ID, d.Medical_License_No, d.Experience_Years, d.Consultation_Duration,
           hc.Centre_ID, hc.Centre_Name, hc.Registration_No,
           GROUP_CONCAT(DISTINCT s.Name SEPARATOR ', ') AS Specializations,
           (SELECT COUNT(*) FROM `SCHEDULED_SLOT` ss WHERE ss.Provider_ID = p.Provider_ID) AS total_slots
    FROM `PROVIDER` p
    JOIN `USER` u ON p.User_ID = u.User_ID
    LEFT JOIN `DOCTOR` d ON p.Provider_ID = d.Provider_ID
    LEFT JOIN `HEALTHCARE_CENTRE` hc ON p.Provider_ID = hc.Provider_ID
    LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON d.Doctor_ID = ds.Doctor_ID
    LEFT JOIN `SPECIALIZATION` s ON ds.Specialization_ID = s.Specialization_ID
    WHERE 1=1
";
$params = [];

if ($filterStatus !== 'ALL') {
    $query .= " AND p.Verification_Status = ?";
    $params[] = $filterStatus;
}
if ($filterType !== 'ALL') {
    $query .= " AND p.Provider_Type = ?";
    $params[] = $filterType;
}
if ($search !== '') {
    $query .= " AND (p.Business_Name LIKE ? OR u.First_Name LIKE ? OR u.Last_Name LIKE ? OR u.Email LIKE ? OR d.Medical_License_No LIKE ? OR hc.Registration_No LIKE ?)";
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term, $term);
}

$query .= " GROUP BY p.Provider_ID ORDER BY p.Created_At DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$providers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.provider-admin-hero{background:linear-gradient(135deg,rgba(8,127,120,.09),rgba(255,255,255,.96));border:1px solid rgba(8,127,120,.14);border-radius:24px;padding:1.5rem}.provider-summary{border:1px solid #e8edf0;border-radius:18px;background:#fff;padding:1rem;height:100%}.provider-summary .icon{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:#eef8f7;color:#087f78;font-size:1.15rem}.provider-directory-card{border:1px solid #e7ecef;border-radius:20px;background:#fff;padding:1.25rem;transition:.18s ease}.provider-directory-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(26,43,54,.07)}.provider-avatar{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:#eef8f7;color:#087f78;font-size:1.25rem}.provider-meta{display:flex;flex-wrap:wrap;gap:.55rem 1rem;color:#64727b;font-size:.875rem}.provider-meta i{color:#087f78}.verification-actions{display:flex;gap:.5rem;flex-wrap:wrap}.filter-panel{border:1px solid #e7ecef;border-radius:18px;background:#fff;padding:1rem}.filter-pills .btn{border-radius:999px}.credential-box{background:#f8fafb;border:1px solid #edf0f2;border-radius:12px;padding:.7rem .85rem}.credential-box code{color:#24333b}.provider-notice{border-radius:16px;background:#f8fafb;border:1px solid #e8edf0;padding:.9rem 1rem}@media(max-width:767.98px){.provider-admin-hero{padding:1.15rem}.provider-directory-card{padding:1rem}.verification-actions .btn,.verification-actions form{width:100%}.verification-actions form .btn{width:100%}}
</style>

<div class="container py-4 py-lg-5">
  <section class="provider-admin-hero mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div>
        <div class="small text-uppercase fw-semibold text-teal mb-2"><i class="bi bi-shield-check me-1"></i> Verification workspace</div>
        <h2 class="fw-bold mb-2">Provider Management</h2>
        <p class="text-muted mb-0">Review doctor and healthcare-centre registrations, credentials and verification status from one place.</p>
      </div>
      <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-grid me-2"></i>Admin Dashboard</a>
    </div>
  </section>

  <?php
    $visibleTotal = count($providers);
    $pendingCount = count(array_filter($providers, fn($x) => $x['Verification_Status'] === 'PENDING'));
    $verifiedCount = count(array_filter($providers, fn($x) => $x['Verification_Status'] === 'VERIFIED'));
    $rejectedCount = count(array_filter($providers, fn($x) => $x['Verification_Status'] === 'REJECTED'));
  ?>
  <div class="row g-3 mb-4">
    <?php foreach ([
      ['bi-buildings','Results',$visibleTotal],
      ['bi-hourglass-split','Pending',$pendingCount],
      ['bi-patch-check','Verified',$verifiedCount],
      ['bi-x-circle','Rejected',$rejectedCount]
    ] as [$icon,$label,$value]): ?>
      <div class="col-6 col-lg-3"><div class="provider-summary d-flex align-items-center gap-3"><div class="icon"><i class="bi <?= $icon ?>"></i></div><div><div class="h4 fw-bold mb-0"><?= $value ?></div><div class="small text-muted"><?= $label ?></div></div></div></div>
    <?php endforeach; ?>
  </div>

  <form method="GET" action="<?= url('admin/providers.php') ?>" class="filter-panel mb-4">
    <div class="row g-3 align-items-end">
      <div class="col-lg-5">
        <label class="form-label small fw-semibold">Search providers</label>
        <div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Name, email, licence or registration no."></div>
      </div>
      <div class="col-md-4 col-lg-3">
        <label class="form-label small fw-semibold">Provider type</label>
        <select class="form-select" name="type"><option value="ALL">All types</option><option value="DOCTOR" <?= $filterType==='DOCTOR'?'selected':'' ?>>Doctors</option><option value="HEALTHCARE_CENTRE" <?= $filterType==='HEALTHCARE_CENTRE'?'selected':'' ?>>Healthcare centres</option></select>
      </div>
      <div class="col-md-4 col-lg-2">
        <label class="form-label small fw-semibold">Status</label>
        <select class="form-select" name="status"><option value="ALL">All statuses</option><?php foreach(['PENDING'=>'Pending','VERIFIED'=>'Verified','REJECTED'=>'Rejected'] as $v=>$l): ?><option value="<?= $v ?>" <?= $filterStatus===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
      </div>
      <div class="col-md-4 col-lg-2 d-grid"><button class="btn btn-teal"><i class="bi bi-funnel me-1"></i>Apply</button></div>
    </div>
    <?php if ($search !== '' || $filterStatus !== 'ALL' || $filterType !== 'ALL'): ?><div class="mt-3"><a href="<?= url('admin/providers.php') ?>" class="small text-decoration-none"><i class="bi bi-x-circle me-1"></i>Clear all filters</a></div><?php endif; ?>
  </form>

  <div class="provider-notice d-flex gap-2 align-items-start mb-4"><i class="bi bi-info-circle text-teal mt-1"></i><div class="small text-muted"><strong class="text-dark">Verification decision:</strong> review the registration or medical licence information shown in the provider record before changing its status. MediLink stores the decision consistently on the provider and its doctor/centre record.</div></div>

  <?php if (empty($providers)): ?>
    <div class="card card-custom text-center py-5 px-3"><i class="bi bi-search fs-1 text-secondary mb-3"></i><h5 class="fw-bold">No providers found</h5><p class="text-muted mb-3">Try changing the search, provider type or verification status.</p><div><a href="<?= url('admin/providers.php') ?>" class="btn btn-outline-secondary">Clear filters</a></div></div>
  <?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="fw-bold mb-0">Provider Directory</h5><small class="text-muted"><?= count($providers) ?> matching record<?= count($providers)===1?'':'s' ?></small></div></div>
    <div class="d-grid gap-3">
      <?php foreach ($providers as $p): ?>
        <article class="provider-directory-card">
          <div class="d-flex flex-column flex-xl-row gap-3 justify-content-between">
            <div class="d-flex gap-3 flex-grow-1">
              <div class="provider-avatar flex-shrink-0"><i class="bi <?= $p['Provider_Type']==='DOCTOR'?'bi-person-badge':'bi-hospital' ?>"></i></div>
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-1"><h5 class="fw-bold mb-0"><?= e($p['Business_Name']) ?></h5><?= renderStatusBadge($p['Verification_Status']) ?><span class="badge text-bg-light border"><?= e(str_replace('_',' ',$p['Provider_Type'])) ?></span></div>
                <div class="text-muted small mb-3"><?= e($p['First_Name'].' '.$p['Last_Name']) ?> · <?= e($p['Email']) ?></div>
                <div class="row g-3">
                  <div class="col-md-5"><div class="credential-box h-100"><div class="small text-muted mb-1"><?= $p['Provider_Type']==='DOCTOR'?'Medical licence':'Registration number' ?></div><code class="fw-semibold"><?= e($p['Medical_License_No'] ?: ($p['Registration_No'] ?: 'Not provided')) ?></code><?php if($p['Provider_Type']==='DOCTOR' && $p['Experience_Years']!==null): ?><div class="small text-muted mt-1"><?= (int)$p['Experience_Years'] ?> years experience</div><?php endif; ?></div></div>
                  <div class="col-md-7"><div class="provider-meta h-100 align-content-center"><span><i class="bi bi-geo-alt me-1"></i><?= e(trim(($p['Address'] ?? '').', '.($p['City'] ?? ''), ', ')) ?: 'Location not provided' ?></span><?php if(!empty($p['Specializations'])): ?><span><i class="bi bi-heart-pulse me-1"></i><?= e($p['Specializations']) ?></span><?php endif; ?><span><i class="bi bi-calendar3 me-1"></i><?= (int)$p['total_slots'] ?> scheduled slots</span><span><i class="bi bi-person-check me-1"></i>Account <?= e(ucfirst(strtolower($p['Account_Status']))) ?></span></div></div>
                </div>
              </div>
            </div>
            <div class="verification-actions align-self-xl-center flex-xl-column" style="min-width:150px">
              <?php if ($p['Verification_Status'] !== 'VERIFIED'): ?><form method="POST" action="<?= url('admin/providers.php') ?>" data-confirm="Verify this provider after reviewing the supplied credentials?"><?= CSRF::inputField() ?><input type="hidden" name="action" value="update_verification"><input type="hidden" name="provider_id" value="<?= $p['Provider_ID'] ?>"><input type="hidden" name="verification_status" value="VERIFIED"><button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Verify</button></form><?php endif; ?>
              <?php if ($p['Verification_Status'] !== 'PENDING'): ?><form method="POST" action="<?= url('admin/providers.php') ?>"><?= CSRF::inputField() ?><input type="hidden" name="action" value="update_verification"><input type="hidden" name="provider_id" value="<?= $p['Provider_ID'] ?>"><input type="hidden" name="verification_status" value="PENDING"><button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Pending</button></form><?php endif; ?>
              <?php if ($p['Verification_Status'] !== 'REJECTED'): ?><form method="POST" action="<?= url('admin/providers.php') ?>" class="d-grid gap-1" data-confirm="Reject this provider application with the entered reason?"><?= CSRF::inputField() ?><input type="hidden" name="action" value="update_verification"><input type="hidden" name="provider_id" value="<?= $p['Provider_ID'] ?>"><input type="hidden" name="verification_status" value="REJECTED"><input type="text" name="reason" class="form-control form-control-sm" maxlength="500" placeholder="Rejection reason" required><button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Reject</button></form><?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
