<?php
$pageTitle='Owner Profile';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/functions.php';
requireRole(ROLE_OWNER);
$db=Database::getConnection(); $uid=getCurrentUserId();

if($_SERVER['REQUEST_METHOD']==='POST'){
  CSRF::check(); $action=$_POST['action']??'';
  $stmt=$db->prepare("SELECT * FROM `USER` WHERE User_ID=? AND Role_Type='OWNER'"); $stmt->execute([$uid]); $owner=$stmt->fetch();
  if($action==='email'){
    $email=strtolower(trim($_POST['email']??'')); $password=$_POST['current_password']??'';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL) || !password_verify($password,$owner['Password_Hash'])) setFlash('danger','Current password or new email is invalid.');
    else {
      $x=$db->prepare("SELECT COUNT(*) FROM `USER` WHERE Email=? AND User_ID<>?"); $x->execute([$email,$uid]);
      if($x->fetchColumn()) setFlash('danger','That email is already in use.');
      else { $db->prepare("UPDATE `USER` SET Email=? WHERE User_ID=?")->execute([$email,$uid]); $_SESSION['user']['email']=$email; setFlash('success','Owner email updated.'); }
    }
  } elseif($action==='password'){
    $current=$_POST['current_password']??''; $new=$_POST['new_password']??''; $confirm=$_POST['confirm_password']??'';
    if(!password_verify($current,$owner['Password_Hash'])) setFlash('danger','Current password is incorrect.');
    elseif(strlen($new)<8) setFlash('danger','New password must be at least 8 characters.');
    elseif($new!==$confirm) setFlash('danger','New passwords do not match.');
    else { $db->prepare("UPDATE `USER` SET Password_Hash=? WHERE User_ID=?")->execute([password_hash($new,PASSWORD_DEFAULT),$uid]); $db->prepare("UPDATE `PASSWORD_RESET` SET Used_At=NOW() WHERE User_ID=? AND Used_At IS NULL")->execute([$uid]); setFlash('success','Password changed successfully.'); }
  }
  redirect('owner/profile.php');
}
$stmt=$db->prepare("SELECT Email,First_Name,Last_Name,Phone FROM `USER` WHERE User_ID=?");$stmt->execute([$uid]);$owner=$stmt->fetch();
require_once __DIR__.'/../includes/header.php';
?>
<div class="container py-4"><div class="mb-4"><div class="text-teal small fw-semibold text-uppercase">Owner security</div><h2 class="fw-bold">Owner Profile</h2><p class="text-muted">Manage the Owner sign-in email and password.</p></div>
<div class="row g-4">
<div class="col-lg-6"><div class="card card-custom p-4"><h5 class="fw-bold">Change email</h5><form method="POST"><?= CSRF::inputField() ?><input type="hidden" name="action" value="email"><label class="form-label">New email</label><input class="form-control mb-3" type="email" name="email" value="<?= e($owner['Email']) ?>" required><label class="form-label">Current password</label><input class="form-control mb-3" type="password" name="current_password" required><button class="btn btn-teal">Update email</button></form></div></div>
<div class="col-lg-6"><div class="card card-custom p-4"><h5 class="fw-bold">Change password</h5><form method="POST"><?= CSRF::inputField() ?><input type="hidden" name="action" value="password"><label class="form-label">Current password</label><input class="form-control mb-3" type="password" name="current_password" required><label class="form-label">New password</label><input class="form-control mb-3" type="password" name="new_password" minlength="8" required><label class="form-label">Confirm new password</label><input class="form-control mb-3" type="password" name="confirm_password" minlength="8" required><button class="btn btn-teal">Change password</button></form></div></div>
</div></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
