<?php
$pageTitle='Audit Log';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/functions.php';
requireRole(ROLE_OWNER);
$db=Database::getConnection();
$logs=$db->query("SELECT al.*,u.First_Name,u.Last_Name,u.Email FROM `AUDIT_LOG` al LEFT JOIN `USER` u ON u.User_ID=al.Actor_User_ID ORDER BY al.Created_At DESC LIMIT 300")->fetchAll();
require_once __DIR__.'/../includes/header.php';
?>
<div class="container py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><div class="text-teal small fw-semibold text-uppercase">Governance</div><h2 class="fw-bold mb-1">Audit Log</h2><p class="text-muted mb-0">Recent privileged and workflow actions.</p></div><a class="btn btn-outline-secondary" href="<?= url('owner/dashboard.php') ?>">Dashboard</a></div>
<div class="card card-custom p-4"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead><tbody>
<?php foreach($logs as $l): ?><tr><td><?= e($l['Created_At']) ?></td><td><?= e(trim(($l['First_Name']??'').' '.($l['Last_Name']??'')) ?: 'System') ?><small class="d-block text-muted"><?= e($l['Email']??'') ?></small></td><td><code><?= e($l['Action_Type']) ?></code></td><td><?= e($l['Entity_Type']) ?><?= $l['Entity_ID']!==null?' #'.e($l['Entity_ID']):'' ?></td><td><?= e($l['Details']??'—') ?></td></tr><?php endforeach; ?>
<?php if(!$logs): ?><tr><td colspan="5" class="text-center text-muted py-4">No audit entries yet.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
