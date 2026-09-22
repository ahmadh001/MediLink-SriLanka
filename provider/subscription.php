<?php


$pageTitle = 'Provider Subscription';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/subscription.php';

requireRole(ROLE_PROVIDER);
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$providerId = (int)$currentUser['provider_id'];

$db = Database::getConnection();

$verificationStmt = $db->prepare("SELECT Verification_Status FROM `PROVIDER` WHERE Provider_ID = ?");
$verificationStmt->execute([$providerId]);
if ($verificationStmt->fetchColumn() !== 'VERIFIED') {
    setFlash('warning', 'Your provider account must be verified by the System Admin before activating a subscription.');
    redirect('provider/dashboard.php');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'subscribe') {
    CSRF::check();

    $planId = (int)($_POST['plan_id'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? 'VISA / MasterCard';

    $pStmt = $db->prepare("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Plan_ID = ? AND Status = 'ACTIVE' AND Target_Role IN ('PROVIDER', 'ALL')");
    $pStmt->execute([$planId]);
    $plan = $pStmt->fetch();

    if ($plan) {
        $refNo = 'PAY-LKR-PROV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $success = subscribeUser($userId, $planId, $refNo);
        if ($success) {
            $db->prepare("DELETE FROM `PROVIDER_PENDING_PLAN` WHERE Provider_ID = ?")->execute([$providerId]);
            writeAuditLog($db, $userId, 'SUBSCRIPTION_ACTIVATED', 'PROVIDER', $providerId, 'Plan #' . $planId . ' activated.');
            setFlash('success', 'Provider subscription activated successfully for ' . e($plan['Plan_Name']) . '!');
            redirect('provider/subscription.php');
        } else {
            setFlash('danger', 'Unable to process subscription. Please try again.');
        }
    } else {
        setFlash('danger', 'Selected provider plan is invalid.');
    }
}


$activeSub = getUserActiveSubscription($userId);


$providerPlans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Status = 'ACTIVE' AND Target_Role IN ('PROVIDER', 'ALL') ORDER BY Price ASC")->fetchAll();


$histStmt = $db->prepare("
    SELECT us.*, sp.Plan_Name, sp.Price, sp.Duration_Days
    FROM `USER_SUBSCRIPTION` us
    JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID = sp.Plan_ID
    WHERE us.User_ID = ?
    ORDER BY us.Created_At DESC
");
$histStmt->execute([$userId]);
$history = $histStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.provider-subscription-page .page-kicker{font-size:.78rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--primary-teal,#087f78)}
.provider-subscription-page .sub-card{border:1px solid #e7ecef;border-radius:18px;background:#fff;box-shadow:0 10px 30px rgba(15,23,42,.04)}
.provider-subscription-page .status-icon{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:rgba(8,127,120,.1);color:var(--primary-teal,#087f78);font-size:1.25rem}
.provider-subscription-page .metric{padding:16px;border:1px solid #e9eef1;border-radius:14px;background:#fafcfc}
.provider-subscription-page .plan-option{border:1px solid #e5eaed;border-radius:16px;padding:18px;height:100%;transition:.18s ease;background:#fff}
.provider-subscription-page .plan-option:hover{transform:translateY(-2px);border-color:rgba(8,127,120,.35);box-shadow:0 10px 24px rgba(15,23,42,.06)}
.provider-subscription-page .history-item{border:1px solid #e7ecef;border-radius:15px;padding:16px}
.provider-subscription-page .soft-note{background:#f6fbfa;border:1px solid #d9eeeb;border-radius:14px}
@media(max-width:767.98px){.provider-subscription-page .page-actions{width:100%}.provider-subscription-page .page-actions .btn{width:100%}}
</style>

<div class="container py-4 provider-subscription-page">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <div class="page-kicker mb-2">Provider workspace</div>
      <h2 class="fw-bold mb-1">Subscription & visibility</h2>
      <p class="text-muted mb-0">Manage the plan that keeps your provider profile and appointment availability active.</p>
    </div>
    <div class="page-actions">
      <a href="<?= url('provider/dashboard.php') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
      </a>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <section class="sub-card p-4 h-100">
        <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
          <div class="d-flex align-items-center gap-3">
            <div class="status-icon"><i class="bi <?= $activeSub ? 'bi-patch-check' : 'bi-calendar-x' ?>"></i></div>
            <div>
              <div class="small text-muted mb-1">Current provider plan</div>
              <h3 class="h4 fw-bold mb-0"><?= e($activeSub ? $activeSub['Plan_Name'] : 'No active plan') ?></h3>
            </div>
          </div>
          <span class="badge rounded-pill <?= $activeSub ? 'text-bg-success' : 'text-bg-warning' ?> px-3 py-2">
            <?= $activeSub ? 'Active' : 'Action needed' ?>
          </span>
        </div>

        <?php if ($activeSub): ?>
          <?php if (!empty($activeSub['Plan_Description'])): ?>
            <p class="text-secondary mb-4"><?= e($activeSub['Plan_Description']) ?></p>
          <?php endif; ?>
          <div class="row g-3 mb-3">
            <div class="col-sm-6"><div class="metric"><div class="small text-muted mb-1">Days remaining</div><div class="h4 fw-bold mb-0 text-teal"><?= (int)$activeSub['Days_Remaining'] ?> days</div></div></div>
            <div class="col-sm-6"><div class="metric"><div class="small text-muted mb-1">Plan ends</div><div class="h5 fw-bold mb-0"><?= formatDate($activeSub['End_Date']) ?></div></div></div>
          </div>
          <div class="soft-note p-3 small"><i class="bi bi-eye me-2 text-teal"></i>Your active plan enables provider visibility and appointment availability according to the MediLink subscription rules.</div>
        <?php else: ?>
          <div class="soft-note p-3"><div class="fw-semibold mb-1"><i class="bi bi-info-circle me-2 text-teal"></i>Select a provider plan below</div><div class="small text-muted">An active provider subscription is required for provider-facing publishing and booking features controlled by the application.</div></div>
        <?php endif; ?>
      </section>
    </div>

    <div class="col-lg-5">
      <section class="sub-card p-4 h-100">
        <div class="d-flex align-items-center gap-3 mb-3"><div class="status-icon"><i class="bi bi-shield-check"></i></div><div><h3 class="h5 fw-bold mb-1">What the plan controls</h3><p class="small text-muted mb-0">Provider access in one place.</p></div></div>
        <div class="d-grid gap-3 mt-4">
          <div class="d-flex gap-3"><i class="bi bi-person-badge text-teal"></i><div><div class="fw-semibold">Provider presence</div><div class="small text-muted">Keep your provider account available to the platform based on plan status.</div></div></div>
          <div class="d-flex gap-3"><i class="bi bi-calendar2-week text-teal"></i><div><div class="fw-semibold">Schedule publishing</div><div class="small text-muted">Manage appointment slots while your provider access is active.</div></div></div>
          <div class="d-flex gap-3"><i class="bi bi-clock-history text-teal"></i><div><div class="fw-semibold">Renewal history</div><div class="small text-muted">Review previous activations and their effective dates below.</div></div></div>
        </div>
      </section>
    </div>
  </div>

  <section class="mb-4">
    <div class="d-flex justify-content-between align-items-end gap-3 mb-3">
      <div><h3 class="h5 fw-bold mb-1">Available provider plans</h3><p class="small text-muted mb-0">Choose an active plan from the plans configured in the database.</p></div>
    </div>
    <?php if (empty($providerPlans)): ?>
      <div class="sub-card p-4 text-center"><i class="bi bi-inbox fs-3 text-muted"></i><h4 class="h6 fw-bold mt-2">No provider plans available</h4><p class="small text-muted mb-0">There are currently no active provider plans configured.</p></div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($providerPlans as $p): ?>
          <div class="col-md-6 col-xl-4">
            <div class="plan-option d-flex flex-column">
              <div class="d-flex justify-content-between gap-2 align-items-start mb-3">
                <div><div class="small text-muted">Provider plan</div><h4 class="h5 fw-bold mb-0"><?= e($p['Plan_Name']) ?></h4></div>
                <?php if ($activeSub && (int)$activeSub['Plan_ID'] === (int)$p['Plan_ID']): ?><span class="badge rounded-pill text-bg-success">Current</span><?php endif; ?>
              </div>
              <?php if (!empty($p['Plan_Description'])): ?><p class="small text-muted flex-grow-1"><?= e($p['Plan_Description']) ?></p><?php else: ?><div class="flex-grow-1"></div><?php endif; ?>
              <div class="d-flex align-items-end gap-2 my-3"><div class="h3 fw-bold mb-0 text-teal"><?= formatLKR($p['Price']) ?></div><div class="small text-muted mb-1">/ <?= (int)$p['Duration_Days'] ?> days</div></div>
              <?php $tierName=stripos($p['Plan_Name'],'Premium')!==false?'PREMIUM':(stripos($p['Plan_Name'],'Professional')!==false?'PROFESSIONAL':'STARTER'); ?>
              <ul class="list-unstyled small mb-3 d-grid gap-2">
                <li><i class="bi bi-check2 text-teal me-1"></i>Verified listing + appointment management</li>
                <li><i class="bi bi-check2 text-teal me-1"></i><?= (int)$p['Max_Book_per_Month']>=999?'High-volume':(int)$p['Max_Book_per_Month'] ?> monthly booking requests</li>
                <li><i class="bi bi-check2 text-teal me-1"></i>Manual single-slot scheduling</li>
                <?php if($tierName!=='STARTER'): ?><li><i class="bi bi-check2 text-teal me-1"></i>Batch + recurring scheduling</li><li><i class="bi bi-check2 text-teal me-1"></i>Advanced dashboard insights</li><?php endif; ?>
                <?php if($tierName==='PREMIUM'): ?><li><i class="bi bi-stars text-teal me-1"></i>Priority visibility + support</li><?php endif; ?>
              </ul>
              <form method="POST" action="<?= url('provider/subscription.php') ?>">
                <?= CSRF::inputField() ?><input type="hidden" name="action" value="subscribe"><input type="hidden" name="plan_id" value="<?= (int)$p['Plan_ID'] ?>">
                <button type="submit" class="btn <?= ($activeSub && (int)$activeSub['Plan_ID'] === (int)$p['Plan_ID']) ? 'btn-outline-teal' : 'btn-teal' ?> w-100">
                  <i class="bi bi-arrow-repeat me-1"></i><?= $activeSub ? 'Renew / activate' : 'Activate plan' ?>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="soft-note p-3 small mt-3"><i class="bi bi-info-circle me-2 text-teal"></i>This university project uses a demo subscription activation flow. No real card or bank payment is processed on this page.</div>
    <?php endif; ?>
  </section>

  <section class="sub-card p-4">
    <div class="d-flex align-items-center gap-3 mb-4"><div class="status-icon"><i class="bi bi-clock-history"></i></div><div><h3 class="h5 fw-bold mb-1">Subscription history</h3><p class="small text-muted mb-0">Previous provider plan activations and effective periods.</p></div></div>
    <?php if (empty($history)): ?>
      <div class="text-center py-4"><i class="bi bi-receipt fs-2 text-muted"></i><h4 class="h6 fw-bold mt-2">No subscription history yet</h4><p class="small text-muted mb-0">Your provider plan activations will appear here.</p></div>
    <?php else: ?>
      <div class="d-grid gap-3">
        <?php foreach ($history as $h): ?>
          <div class="history-item">
            <div class="row g-3 align-items-center">
              <div class="col-md-4"><div class="small text-muted">Plan</div><div class="fw-bold"><?= e($h['Plan_Name']) ?></div><div class="small text-muted"><?= formatLKR($h['Price']) ?> · <?= (int)$h['Duration_Days'] ?> days</div></div>
              <div class="col-md-3"><div class="small text-muted">Effective period</div><div class="small fw-semibold"><?= formatDate($h['Start_Date']) ?> – <?= formatDate($h['End_Date']) ?></div></div>
              <div class="col-md-2"><div class="small text-muted mb-1">Status</div><?= renderStatusBadge($h['Status']) ?></div>
              <div class="col-md-3 text-md-end"><div class="small text-muted">Reference</div><code class="small text-dark"><?= e($h['Payment_Reference_No']) ?></code><div class="small text-muted mt-1"><?= formatDateTime($h['Created_At']) ?></div></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
