<?php
$pageTitle = 'Provider Application Status';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$uid = (int)($_SESSION['pending_provider_user_id'] ?? 0);
if ($uid <= 0) redirect('public/login.php');

$db = Database::getConnection();
$stmt = $db->prepare("
    SELECT u.User_ID,u.First_Name,u.Email,u.Account_Status,
           p.Provider_ID,p.Provider_Type,p.Business_Name,p.Verification_Status,
           (SELECT h.Reason
              FROM `PROVIDER_VERIFICATION_HISTORY` h
             WHERE h.Provider_ID=p.Provider_ID AND h.New_Status='REJECTED'
             ORDER BY h.Created_At DESC,h.Verification_History_ID DESC LIMIT 1) AS Rejection_Reason
      FROM `USER` u
      JOIN `PROVIDER` p ON p.User_ID=u.User_ID
     WHERE u.User_ID=? LIMIT 1
");
$stmt->execute([$uid]);
$app=$stmt->fetch();
if(!$app){ unset($_SESSION['pending_provider_user_id']); redirect('public/login.php'); }

$isApproved=$app['Verification_Status']==='VERIFIED' && $app['Account_Status']==='ACTIVE';
$isRejected=$app['Verification_Status']==='REJECTED';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="provider-wait-page">
  <section class="provider-wait-card" aria-live="polite">
    <div class="wait-illustration <?= $isApproved?'approved':($isRejected?'rejected':'pending') ?>" aria-hidden="true">
      <div class="wait-orbit"><span></span><span></span><span></span></div>
      <div class="wait-character">
        <div class="wait-head"><span class="eye e1"></span><span class="eye e2"></span><span class="smile"></span></div>
        <div class="wait-body"><i class="bi <?= $isApproved?'bi-check-lg':($isRejected?'bi-x-lg':'bi-hourglass-split') ?>"></i></div>
      </div>
    </div>

    <?php if($isApproved): ?>
      <span class="wait-kicker">Application approved</span>
      <h1>You're ready to continue</h1>
      <p>System Admin verified <strong><?= e($app['Business_Name']) ?></strong>. Your provider account is now active.</p>
      <a class="btn btn-teal px-4" href="<?= url('public/login.php?approved=1') ?>">Continue to sign in</a>
    <?php elseif($isRejected): ?>
      <span class="wait-kicker rejected">Application reviewed</span>
      <h1>Verification needs attention</h1>
      <p>Your provider application was not approved by the System Admin.</p>
      <?php if(!empty($app['Rejection_Reason'])): ?>
        <div class="wait-reason"><strong>Admin note</strong><br><?= e($app['Rejection_Reason']) ?></div>
      <?php endif; ?>
      <a class="btn btn-outline-secondary px-4" href="<?= url('public/login.php') ?>">Back to sign in</a>
    <?php else: ?>
      <span class="wait-kicker">Verification in progress</span>
      <h1>Your profile is waiting for approval</h1>
      <p>Hi <?= e($app['First_Name']) ?>. <strong><?= e($app['Business_Name']) ?></strong> was submitted successfully. The provider dashboard opens after System Admin verification.</p>
      <div class="wait-steps">
        <div class="done"><i class="bi bi-check-circle-fill"></i><span><b>Registration submitted</b><small>Your details are safely saved.</small></span></div>
        <div class="current"><i class="bi bi-shield-check"></i><span><b>System Admin verification</b><small>Currently waiting for review.</small></span></div>
        <div><i class="bi bi-grid"></i><span><b>Provider dashboard</b><small>Available immediately after approval.</small></span></div>
      </div>
      <p class="wait-refresh"><i class="bi bi-arrow-clockwise"></i> Status checks automatically every 15 seconds.</p>
    <?php endif; ?>
  </section>
</div>
<?php if(!$isApproved && !$isRejected): ?><script>setTimeout(()=>location.reload(),15000);</script><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
