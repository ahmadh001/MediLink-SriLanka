<?php


$pageTitle = 'My Subscription & Quotas';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/subscription.php';

requireRole(ROLE_CLIENT);
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$clientId = $currentUser['client_id'];

$db = Database::getConnection();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'subscribe') {
    CSRF::check();

    $planId = (int)($_POST['plan_id'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? 'VISA / Master';

    
    $pStmt = $db->prepare("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Plan_ID = ? AND Status = 'ACTIVE' AND Target_Role IN ('CLIENT', 'ALL')");
    $pStmt->execute([$planId]);
    $plan = $pStmt->fetch();

    if ($plan) {
        
        $refNo = 'PAY-LKR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        
        $success = subscribeUser($userId, $planId, $refNo);
        if ($success) {
            setFlash('success', 'Subscription activated successfully! You now have access to ' . e($plan['Plan_Name']) . ' with ' . $plan['Max_Book_per_Month'] . ' monthly bookings and ' . $plan['Search_Radius_KM'] . ' km radius.');
            redirect('client/subscription.php');
        } else {
            setFlash('danger', 'Unable to process subscription. Please try again.');
        }
    } else {
        setFlash('danger', 'Selected subscription plan is invalid.');
    }
}


$activeSub = getUserActiveSubscription($userId);
$quota = getMonthlyBookingQuota($clientId, $userId);


$availablePlans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Status = 'ACTIVE' AND Target_Role IN ('CLIENT', 'ALL') ORDER BY Price ASC")->fetchAll();


$histStmt = $db->prepare("
    SELECT us.*, sp.Plan_Name, sp.Price, sp.Max_Book_per_Month, sp.Search_Radius_KM
    FROM `USER_SUBSCRIPTION` us
    JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID = sp.Plan_ID
    WHERE us.User_ID = ?
    ORDER BY us.Created_At DESC
");
$histStmt->execute([$userId]);
$history = $histStmt->fetchAll();

$preselectedPlanId = (int)($_GET['plan_id'] ?? 0);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
  .sub-page{max-width:1180px}.sub-hero{padding:1.1rem 0 .35rem}.sub-card{border:1px solid #e7ecef;border-radius:18px;background:#fff;box-shadow:0 8px 28px rgba(18,55,62,.055)}
  .sub-status{display:inline-flex;align-items:center;gap:.4rem;padding:.38rem .65rem;border-radius:999px;font-size:.75rem;font-weight:700}.sub-status.active{background:#e9f8f3;color:#087f78}.sub-status.inactive{background:#fff4df;color:#8a5a00}
  .metric{padding:.85rem;border:1px solid #edf1f2;border-radius:14px;background:#fbfcfc}.metric-label{font-size:.76rem;color:#6c757d;margin-bottom:.2rem}.metric-value{font-weight:700}
  .quota-track{height:9px;border-radius:99px;background:#edf2f3;overflow:hidden}.quota-fill{height:100%;border-radius:99px;background:var(--primary-teal,#087f78)}
  .plan-option{border:1px solid #e7ecef;border-radius:16px;padding:1rem;height:100%;transition:.18s ease}.plan-option:hover{border-color:#b8d8d4;transform:translateY(-2px)}.plan-option.current{border-color:#87c9c1;background:#f6fbfa}
  .history-item{display:grid;grid-template-columns:minmax(150px,1.2fr) minmax(130px,.9fr) minmax(160px,1.1fr) auto;gap:1rem;align-items:center;padding:1rem 0;border-bottom:1px solid #edf1f2}.history-item:last-child{border-bottom:0}
  .ref-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.78rem;color:#536168;word-break:break-all}
  @media(max-width:767.98px){.history-item{grid-template-columns:1fr;gap:.35rem}.sub-card{border-radius:15px}}
</style>

<div class="container sub-page py-4 py-lg-5">
  <div class="sub-hero d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
      <div class="text-uppercase small fw-bold text-teal mb-2" style="letter-spacing:.08em"><i class="bi bi-wallet2 me-1"></i> Membership</div>
      <h1 class="h2 fw-bold mb-2">Subscription & booking quota</h1>
      <p class="text-muted mb-0">See your current plan, monthly booking allowance and subscription history in one place.</p>
    </div>
    <a href="<?= url('client/dashboard.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <section class="sub-card p-4 h-100">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
          <div>
            <span class="sub-status <?= $activeSub ? 'active' : 'inactive' ?> mb-2"><i class="bi <?= $activeSub ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i><?= $activeSub ? 'Active plan' : 'No active plan' ?></span>
            <h2 class="h4 fw-bold mb-1"><?= e($activeSub ? $activeSub['Plan_Name'] : 'Choose a plan to start booking') ?></h2>
            <?php if ($activeSub && !empty($activeSub['Plan_Description'])): ?><p class="text-muted small mb-0"><?= e($activeSub['Plan_Description']) ?></p><?php endif; ?>
          </div>
          <?php if ($activeSub): ?><div class="text-md-end"><div class="small text-muted">Time remaining</div><div class="fw-bold"><i class="bi bi-calendar-check me-1 text-teal"></i><?= (int)$activeSub['Days_Remaining'] ?> days</div></div><?php endif; ?>
        </div>

        <?php if ($activeSub): ?>
          <div class="row g-3 mb-4">
            <div class="col-sm-4"><div class="metric"><div class="metric-label">Plan price</div><div class="metric-value"><?= formatLKR($activeSub['Price']) ?></div></div></div>
            <div class="col-sm-4"><div class="metric"><div class="metric-label">Search radius</div><div class="metric-value"><?= (int)$activeSub['Search_Radius_KM'] ?> km</div></div></div>
            <div class="col-sm-4"><div class="metric"><div class="metric-label">Valid until</div><div class="metric-value"><?= formatDate($activeSub['End_Date']) ?></div></div></div>
          </div>
        <?php else: ?>
          <div class="alert alert-warning border-0 rounded-3 small mb-4"><i class="bi bi-info-circle me-2"></i>An active plan is required for appointment booking and plan-based search radius access.</div>
        <?php endif; ?>

        <?php $pct = ($quota['limit'] > 0) ? min(100, round(($quota['used'] / $quota['limit']) * 100)) : 0; ?>
        <div class="d-flex justify-content-between gap-3 mb-2"><div><div class="fw-semibold">Booking quota</div><div class="small text-muted"><?= date('F Y') ?></div></div><div class="text-end"><strong><?= (int)$quota['remaining'] ?></strong> <span class="text-muted small">remaining</span><div class="small text-muted"><?= (int)$quota['used'] ?> / <?= (int)$quota['limit'] ?> used</div></div></div>
        <div class="quota-track mb-2" role="progressbar" aria-label="Monthly booking quota" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="quota-fill" style="width:<?= $pct ?>%"></div></div>
        <div class="small text-muted"><i class="bi bi-arrow-repeat me-1"></i>Monthly quota refreshes on the first day of each month. Cancelled appointments are not deducted.</div>
      </section>
    </div>

    <div class="col-lg-5">
      <section class="sub-card p-4 h-100">
        <div class="d-flex align-items-center gap-2 mb-2"><span class="rounded-circle bg-light p-2 text-teal"><i class="bi bi-arrow-up-right-circle"></i></span><h2 class="h5 fw-bold mb-0"><?= $activeSub ? 'Change or renew plan' : 'Activate a plan' ?></h2></div>
        <p class="small text-muted mb-4">Select a client plan below. This project uses a demo subscription activation flow.</p>
        <?php if (empty($availablePlans)): ?>
          <div class="alert alert-light border mb-0">No active client plans are available right now.</div>
        <?php else: ?>
          <form method="POST" action="<?= url('client/subscription.php') ?>">
            <?= CSRF::inputField() ?><input type="hidden" name="action" value="subscribe">
            <label class="form-label small fw-semibold" for="planSelect">Plan</label>
            <select name="plan_id" id="planSelect" class="form-select mb-3" required>
              <?php foreach ($availablePlans as $p): ?><option value="<?= (int)$p['Plan_ID'] ?>" <?= ($preselectedPlanId === (int)$p['Plan_ID'] || ($activeSub && (int)$activeSub['Plan_ID'] === (int)$p['Plan_ID'])) ? 'selected' : '' ?>><?= e($p['Plan_Name']) ?> — <?= formatLKR($p['Price']) ?> · <?= (int)$p['Max_Book_per_Month'] ?> bookings · <?= (int)$p['Search_Radius_KM'] ?> km</option><?php endforeach; ?>
            </select>
            <input type="hidden" name="payment_method" value="Demo activation">
            <button type="submit" class="btn btn-teal w-100 py-2 fw-semibold"><i class="bi bi-check2-circle me-1"></i><?= $activeSub ? 'Confirm plan selection' : 'Activate selected plan' ?></button>
            <div class="small text-muted mt-3"><i class="bi bi-shield-check me-1"></i>Medical consultation fees are separate from MediLink subscription pricing.</div>
          </form>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <section class="mb-4">
    <div class="d-flex justify-content-between align-items-end gap-3 mb-3"><div><h2 class="h4 fw-bold mb-1">Available client plans</h2><p class="text-muted small mb-0">Compare the limits that affect doctor discovery and booking.</p></div><a href="<?= url('plans.php#client-plans') ?>" class="small fw-semibold text-decoration-none">Full comparison <i class="bi bi-arrow-right"></i></a></div>
    <div class="row g-3">
      <?php foreach ($availablePlans as $p): $isCurrent=$activeSub && (int)$activeSub['Plan_ID']===(int)$p['Plan_ID']; ?>
        <div class="col-md-6 col-xl-4"><div class="plan-option <?= $isCurrent ? 'current' : '' ?>">
          <div class="d-flex justify-content-between gap-2 mb-2"><h3 class="h6 fw-bold mb-0"><?= e($p['Plan_Name']) ?></h3><?php if($isCurrent): ?><span class="badge bg-success-subtle text-success border border-success-subtle">Current</span><?php endif; ?></div>
          <div class="h5 fw-bold mb-3"><?= formatLKR($p['Price']) ?></div>
          <div class="small mb-2"><i class="bi bi-calendar2-check text-teal me-2"></i><strong><?= (int)$p['Max_Book_per_Month'] ?></strong> bookings / month</div>
          <div class="small"><i class="bi bi-geo-alt text-teal me-2"></i><strong><?= (int)$p['Search_Radius_KM'] ?> km</strong> search radius</div>
        </div></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sub-card p-4">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3"><div><h2 class="h5 fw-bold mb-1"><i class="bi bi-clock-history text-teal me-2"></i>Subscription history</h2><p class="small text-muted mb-0">Previous activations and renewals linked to your account.</p></div><span class="badge bg-light text-dark border"><?= count($history) ?> record<?= count($history)===1?'':'s' ?></span></div>
    <?php if (empty($history)): ?>
      <div class="text-center py-4"><i class="bi bi-receipt fs-2 text-muted"></i><h3 class="h6 fw-bold mt-2">No subscription history yet</h3><p class="small text-muted mb-0">Your plan activations will appear here.</p></div>
    <?php else: ?>
      <?php foreach ($history as $h): ?>
        <div class="history-item">
          <div><div class="fw-semibold"><?= e($h['Plan_Name']) ?></div><div class="ref-code"><?= e($h['Payment_Reference_No']) ?></div></div>
          <div><div class="fw-semibold"><?= formatLKR($h['Price']) ?></div><div class="small text-muted"><?= (int)$h['Max_Book_per_Month'] ?> bookings · <?= (int)$h['Search_Radius_KM'] ?> km</div></div>
          <div><div class="small fw-semibold"><?= formatDate($h['Start_Date']) ?> → <?= formatDate($h['End_Date']) ?></div><div class="small text-muted">Activated <?= formatDateTime($h['Created_At']) ?></div></div>
          <div><?= renderStatusBadge($h['Status']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
