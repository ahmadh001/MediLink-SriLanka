<?php
/**
 * Provider Subscription Management & Renewal
 */

$pageTitle = 'Provider Subscription';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/subscription.php';

requireRole(ROLE_PROVIDER);
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];

$db = Database::getConnection();

// Process Provider Subscription Purchase / Renewal
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
            setFlash('success', 'Provider subscription activated successfully for ' . e($plan['Plan_Name']) . '!');
            redirect('provider/subscription.php');
        } else {
            setFlash('danger', 'Unable to process subscription. Please try again.');
        }
    } else {
        setFlash('danger', 'Selected provider plan is invalid.');
    }
}

// Fetch active subscription
$activeSub = getUserActiveSubscription($userId);

// Fetch provider plans
$providerPlans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Status = 'ACTIVE' AND Target_Role IN ('PROVIDER', 'ALL') ORDER BY Price ASC")->fetchAll();

// Fetch subscription history
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

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-patch-check-fill text-teal me-2"></i> Provider Listing Subscription</h2>
      <p class="text-muted small mb-0">Maintain your verified doctor / clinic presence and schedule publishing status.</p>
    </div>
    <a href="<?= url('provider/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Provider Dashboard
    </a>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <div class="card card-custom p-4 h-100 <?= $activeSub ? 'border-success' : 'border-warning' ?>">
        <span class="badge <?= $activeSub ? 'bg-success' : 'bg-warning text-dark' ?> align-self-start mb-2">
          <?= $activeSub ? 'ACTIVE PROVIDER STATUS' : 'SUBSCRIPTION EXPIRED / REQUIRED' ?>
        </span>
        <h3 class="fw-bold text-teal mb-1"><?= e($activeSub ? $activeSub['Plan_Name'] : 'No Active Provider Plan') ?></h3>
        
        <?php if ($activeSub): ?>
          <p class="text-secondary small mb-3"><?= e($activeSub['Plan_Description']) ?></p>
          <div class="row g-3 text-center my-2">
            <div class="col-6">
              <div class="bg-light p-3 rounded-3 border">
                <div class="small text-muted">Days Remaining</div>
                <div class="fs-4 fw-bold text-teal"><?= $activeSub['Days_Remaining'] ?> Days</div>
              </div>
            </div>
            <div class="col-6">
              <div class="bg-light p-3 rounded-3 border">
                <div class="small text-muted">Valid Expiry Date</div>
                <div class="fs-5 fw-bold text-dark"><?= formatDate($activeSub['End_Date']) ?></div>
              </div>
            </div>
          </div>
          <div class="alert alert-success small mt-3 mb-0">
            <i class="bi bi-check-circle-fill me-1"></i> Your schedule slots are currently published to Sri Lankan patients and accessible via GPS distance search.
          </div>
        <?php else: ?>
          <div class="alert alert-warning small my-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> An active provider plan is required to publish schedule availability slots and receive bookings. Please renew below.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card card-custom p-4 bg-light h-100 border-0">
        <h5 class="fw-bold mb-3"><i class="bi bi-credit-card-fill text-teal me-2"></i> Renew Provider Plan</h5>
        
        <form method="POST" action="<?= url('provider/subscription.php') ?>">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="subscribe">

          <div class="mb-3">
            <label class="form-label small fw-semibold">Select Provider Tier</label>
            <select name="plan_id" class="form-select" required>
              <?php foreach ($providerPlans as $p): ?>
                <option value="<?= $p['Plan_ID'] ?>" <?= ($activeSub && $activeSub['Plan_ID'] === $p['Plan_ID']) ? 'selected' : '' ?>>
                  <?= e($p['Plan_Name']) ?> — <?= formatLKR($p['Price']) ?> (<?= $p['Duration_Days'] ?> Days)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Payment Channel</label>
            <select name="payment_method" class="form-select">
              <option value="Corporate VISA / MasterCard">Corporate Card (VISA / Master)</option>
              <option value="Commercial Bank Business Pay">Commercial Bank Direct</option>
              <option value="Sampath Bank Corporate">Sampath Bank Corporate</option>
            </select>
          </div>

          <button type="submit" class="btn btn-teal w-100 py-2 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Complete Provider Renewal
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Billing Receipts -->
  <div class="card card-custom p-4">
    <h5 class="fw-bold mb-3"><i class="bi bi-receipt text-teal me-2"></i> Provider Billing Invoices & History</h5>
    <?php if (empty($history)): ?>
      <p class="text-muted small mb-0">No past subscription invoices found.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Reference Number</th>
              <th>Plan</th>
              <th>Fee (LKR)</th>
              <th>Effective Dates</th>
              <th>Status</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h): ?>
              <tr>
                <td><code class="fw-bold text-dark"><?= e($h['Payment_Reference_No']) ?></code></td>
                <td><strong><?= e($h['Plan_Name']) ?></strong></td>
                <td><?= formatLKR($h['Price']) ?></td>
                <td><small><?= formatDate($h['Start_Date']) ?> – <?= formatDate($h['End_Date']) ?></small></td>
                <td><?= renderStatusBadge($h['Status']) ?></td>
                <td><small class="text-muted"><?= formatDateTime($h['Created_At']) ?></small></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
