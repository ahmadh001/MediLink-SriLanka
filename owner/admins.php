<?php
$pageTitle = 'Manage System Admins';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(ROLE_OWNER);
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  CSRF::check();
  $action=$_POST['action']??'';
  if($action==='create_admin'){
    $first=trim($_POST['first_name']??''); $last=trim($_POST['last_name']??''); $email=strtolower(trim($_POST['email']??'')); $phone=trim($_POST['phone']??''); $password=$_POST['password']??'';
    if(!$first || !$last || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<8){
      setFlash('danger','Enter a valid name/email and a password of at least 8 characters.');
    } else {
      $exists=$db->prepare("SELECT COUNT(*) FROM `USER` WHERE Email=?"); $exists->execute([$email]);
      if($exists->fetchColumn()){ setFlash('danger','That email address is already registered.'); }
      else {
        $stmt=$db->prepare("INSERT INTO `USER` (Email,Password_Hash,First_Name,Last_Name,Phone,NIC_No,Role_Type,Account_Status) VALUES (?,?,?,?,?,NULL,'SYSTEM_ADMIN','ACTIVE')");
        $stmt->execute([$email,password_hash($password,PASSWORD_DEFAULT),$first,$last,$phone]);
        $newAdminId=(int)$db->lastInsertId();
        writeAuditLog($db,(int)getCurrentUserId(),'SYSTEM_ADMIN_APPOINTED','USER',$newAdminId,'Admin email: '.$email);
        setFlash('success','System Admin appointed successfully.');
      }
    }
  } elseif($action==='toggle_admin'){
    $id=(int)($_POST['user_id']??0);
    $stmt=$db->prepare("SELECT Account_Status FROM `USER` WHERE User_ID=? AND Role_Type='SYSTEM_ADMIN'"); $stmt->execute([$id]); $status=$stmt->fetchColumn();
    if($status){ $new=$status==='ACTIVE'?'SUSPENDED':'ACTIVE'; $db->prepare("UPDATE `USER` SET Account_Status=? WHERE User_ID=? AND Role_Type='SYSTEM_ADMIN'")->execute([$new,$id]); writeAuditLog($db,(int)getCurrentUserId(),'SYSTEM_ADMIN_STATUS_CHANGED','USER',$id,'New status: '.$new); setFlash('success',"System Admin status changed to {$new}."); }
  }
  redirect('owner/admins.php');
}
$admins=$db->query("SELECT User_ID,First_Name,Last_Name,Email,Phone,Account_Status,Created_At FROM `USER` WHERE Role_Type='SYSTEM_ADMIN' ORDER BY Created_At DESC")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
 <div class="d-flex justify-content-between align-items-center gap-3 mb-4"><div><div class="text-teal small fw-semibold text-uppercase">Owner control</div><h2 class="fw-bold mb-1">System Admins</h2><p class="text-muted mb-0">Appoint and control System Admin access.</p></div><a href="<?= url('owner/dashboard.php') ?>" class="btn btn-outline-secondary">Dashboard</a></div>
 <div class="row g-4">
  <div class="col-lg-4"><div class="card card-custom p-4"><h5 class="fw-bold">Appoint System Admin</h5><p class="small text-muted">Creates an active administrator account. Owner access is never delegated.</p>
   <form method="POST"><?= CSRF::inputField() ?><input type="hidden" name="action" value="create_admin">
    <label class="form-label">First name</label><input class="form-control mb-3" name="first_name" required>
    <label class="form-label">Last name</label><input class="form-control mb-3" name="last_name" required>
    <label class="form-label">Email</label><input class="form-control mb-3" type="email" name="email" required>
    <label class="form-label">Phone</label><input class="form-control mb-3" name="phone">
    <label class="form-label">Temporary password</label><input class="form-control mb-3" type="password" name="password" minlength="8" required>
    <button class="btn btn-teal w-100">Appoint Admin</button>
   </form>
  </div></div>
  <div class="col-lg-8"><div class="card card-custom p-4"><h5 class="fw-bold mb-3">Appointed administrators</h5>
   <?php if(!$admins): ?><p class="text-muted">No System Admins.</p><?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Admin</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>
   <?php foreach($admins as $a): ?><tr><td><strong><?= e($a['First_Name'].' '.$a['Last_Name']) ?></strong><small class="d-block text-muted"><?= e($a['Email']) ?></small></td><td><?= renderStatusBadge($a['Account_Status']) ?></td><td><?= formatDate($a['Created_At']) ?></td><td class="text-end"><form method="POST"><?= CSRF::inputField() ?><input type="hidden" name="action" value="toggle_admin"><input type="hidden" name="user_id" value="<?= (int)$a['User_ID'] ?>"><button class="btn btn-sm <?= $a['Account_Status']==='ACTIVE'?'btn-outline-danger':'btn-outline-success' ?>"><?= $a['Account_Status']==='ACTIVE'?'Suspend':'Activate' ?></button></form></td></tr><?php endforeach; ?>
   </tbody></table></div><?php endif; ?>
  </div></div>
 </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
