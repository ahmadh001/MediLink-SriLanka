<?php
$pageTitle='Forgot Password';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/functions.php';
$db=Database::getConnection(); $demoResetUrl=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 CSRF::check(); $email=strtolower(trim($_POST['email']??'')); $nic=trim($_POST['nic_no']??'');
 $stmt=$db->prepare("SELECT User_ID,Role_Type,NIC_No FROM `USER` WHERE Email=? AND Account_Status='ACTIVE'");$stmt->execute([$email]);$account=$stmt->fetch(); $uid=false;
 if($account){
   $privileged=in_array($account['Role_Type'],['OWNER','SYSTEM_ADMIN'],true);
   if(!$privileged || ($nic!=='' && !empty($account['NIC_No']) && hash_equals((string)$account['NIC_No'],$nic))) $uid=(int)$account['User_ID'];
 }
 if($uid){
   $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
   $db->prepare("UPDATE `PASSWORD_RESET` SET Used_At=NOW() WHERE User_ID=? AND Used_At IS NULL")->execute([$uid]);
   $db->prepare("INSERT INTO `PASSWORD_RESET` (User_ID,Token_Hash,Expires_At) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))")->execute([$uid,$hash]);
   $demoResetUrl=url('public/reset-password.php?token='.urlencode($token));
 }
 setFlash('success','If an active account matches that email, a password reset request has been created.');
}
require_once __DIR__.'/../includes/header.php';
?>
<div class="container py-5" style="max-width:620px"><div class="card card-custom p-4"><h2 class="fw-bold">Forgot password</h2><p class="text-muted">Enter your MediLink account email.</p><form method="POST"><?= CSRF::inputField() ?><input class="form-control mb-3" type="email" name="email" placeholder="name@example.com" required><label class="form-label">NIC (required for Owner / System Admin recovery)</label><input class="form-control mb-3" name="nic_no" placeholder="Privileged accounts only"><button class="btn btn-teal w-100">Create reset request</button></form>
<?php if($demoResetUrl): ?><div class="alert alert-warning mt-4 mb-0"><strong>Local demo mode:</strong> email delivery is not configured. Use this one-time link within 30 minutes:<br><a href="<?= e($demoResetUrl) ?>">Reset password</a></div><?php endif; ?>
</div></div><?php require_once __DIR__.'/../includes/footer.php'; ?>
