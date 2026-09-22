<?php
$pageTitle = 'Owner Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(ROLE_OWNER);
$db = Database::getConnection();

$stats = $db->query("
 SELECT
 (SELECT COUNT(*) FROM `USER` WHERE Role_Type='SYSTEM_ADMIN') admins,
 (SELECT COUNT(*) FROM `USER` WHERE Role_Type='CLIENT') clients,
 (SELECT COUNT(*) FROM `PROVIDER` WHERE Verification_Status='VERIFIED') verified_providers,
 (SELECT COUNT(*) FROM `APPOINTMENT`) appointments,
 (SELECT COALESCE(SUM(sp.Price),0) FROM `USER_SUBSCRIPTION` us JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID=sp.Plan_ID) subscription_value
")->fetch();

$recentAdmins = $db->query("SELECT User_ID, First_Name, Last_Name, Email, Account_Status, Created_At FROM `USER` WHERE Role_Type='SYSTEM_ADMIN' ORDER BY Created_At DESC LIMIT 6")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div><div class="text-teal fw-semibold small text-uppercase">Highest-level oversight</div><h2 class="fw-bold mb-1">Owner Dashboard</h2><p class="text-muted mb-0">Business overview and System Admin governance.</p></div>
    <a href="<?= url('owner/admins.php') ?>" class="btn btn-teal"><i class="bi bi-person-plus me-1"></i>Manage System Admins</a>
  </div>
  <div class="row g-3 mb-4">
    <?php foreach ([['System Admins',$stats['admins'],'bi-shield-lock'],['Clients',$stats['clients'],'bi-people'],['Verified Providers',$stats['verified_providers'],'bi-patch-check'],['Appointments',$stats['appointments'],'bi-calendar-check']] as $s): ?>
    <div class="col-6 col-xl-3"><div class="card card-custom p-3 h-100"><i class="bi <?= $s[2] ?> text-teal fs-4"></i><div class="fs-3 fw-bold mt-2"><?= (int)$s[1] ?></div><div class="small text-muted"><?= e($s[0]) ?></div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="card card-custom p-4 mb-4"><div class="small text-muted">Recorded subscription value</div><div class="fs-3 fw-bold">Rs. <?= number_format((float)$stats['subscription_value'],2) ?></div><small class="text-muted">Based on subscription records in the database.</small></div>
  <div class="card card-custom p-4">
    <div class="d-flex justify-content-between mb-3"><div><h5 class="fw-bold mb-1">System Admins</h5><small class="text-muted">Only the Owner can appoint or suspend these accounts.</small></div><a href="<?= url('owner/admins.php') ?>" class="btn btn-outline-teal btn-sm">View all</a></div>
    <?php if(!$recentAdmins): ?><p class="text-muted mb-0">No System Admin accounts yet.</p><?php else: ?>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Created</th></tr></thead><tbody>
    <?php foreach($recentAdmins as $a): ?><tr><td><?= e($a['First_Name'].' '.$a['Last_Name']) ?></td><td><?= e($a['Email']) ?></td><td><?= renderStatusBadge($a['Account_Status']) ?></td><td><?= formatDate($a['Created_At']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
