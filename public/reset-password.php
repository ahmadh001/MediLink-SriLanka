<?php
$pageTitle='Reset Password';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/functions.php';
$db=Database::getConnection();$token=$_GET['token']??($_POST['token']??'');$valid=false;$uid=null;
if($token){$h=hash('sha256',$token);$s=$db->prepare("SELECT User_ID FROM `PASSWORD_RESET` WHERE Token_Hash=? AND Used_At IS NULL AND Expires_At>NOW()");$s->execute([$h]);$uid=$s->fetchColumn();$valid=(bool)$uid;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 CSRF::check();$new=$_POST['new_password']??'';$confirm=$_POST['confirm_password']??'';
 if(!$valid)setFlash('danger','This reset link is invalid or expired.');
 elseif(strlen($new)<8)setFlash('danger','Password must be at least 8 characters.');
 elseif($new!==$confirm)setFlash('danger','Passwords do not match.');
 else{$db->beginTransaction();try{$db->prepare("UPDATE `USER` SET Password_Hash=? WHERE User_ID=?")->execute([password_hash($new,PASSWORD_DEFAULT),$uid]);$db->prepare("UPDATE `PASSWORD_RESET` SET Used_At=NOW() WHERE User_ID=? AND Used_At IS NULL")->execute([$uid]);$db->commit();setFlash('success','Password reset successfully. You can sign in now.');redirect('public/login.php');}catch(Throwable $e){$db->rollBack();setFlash('danger','Password reset failed. Please try again.');}}
}
require_once __DIR__.'/../includes/header.php';
?>
<div class="container py-5" style="max-width:620px"><div class="card card-custom p-4"><h2 class="fw-bold">Reset password</h2>
<?php if(!$valid): ?><div class="alert alert-danger">This reset link is invalid or expired.</div><a href="<?= url('public/forgot-password.php') ?>" class="btn btn-outline-teal">Request a new link</a>
<?php else: ?><form method="POST"><?= CSRF::inputField() ?><input type="hidden" name="token" value="<?= e($token) ?>"><label class="form-label">New password</label><input class="form-control mb-3" type="password" name="new_password" minlength="8" required><label class="form-label">Confirm password</label><input class="form-control mb-3" type="password" name="confirm_password" minlength="8" required><button class="btn btn-teal w-100">Reset password</button></form><?php endif; ?>
</div></div><?php require_once __DIR__.'/../includes/footer.php'; ?>
