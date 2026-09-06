<?php
/**
 * Client Subscription Management & LKR Demo Payment Checkout
 */

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

// Process Subscription Purchase / Renewal via Demo Gateway
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'subscribe') {
    CSRF::check();

    $planId = (int)($_POST['plan_id'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? 'VISA / Master';

    // Verify Plan exists
    $pStmt = $db->prepare("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Plan_ID = ? AND Status = 'ACTIVE' AND Target_Role IN ('CLIENT', 'ALL')");
    $pStmt->execute([$planId]);
    $plan = $pStmt->fetch();

    if ($plan) {
        // Generate authentic Sri Lankan Payment Reference Number
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

// Fetch current active plan & quota
$activeSub = getUserActiveSubscription($userId);
$quota = getMonthlyBookingQuota($clientId, $userId);

// Fetch all available client plans
$availablePlans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Status = 'ACTIVE' AND Target_Role IN ('CLIENT', 'ALL') ORDER BY Price ASC")->fetchAll();

// Fetch subscription history
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

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-credit-card-2-front text-teal me-2"></i> Client Subscription & Quotas</h2>
      <p class="text-muted small mb-0">Manage your medical discovery membership and track your monthly appointment allowance.</p>
    </div>
    <a href="<?= url('client/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
    </a>
  </div>

  <!-- Active Subscription Overview Banner -->
  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div class="card card-custom p-4 h-100 <?= $activeSub ? 'border-success' : 'border-warning' ?>">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <span class="badge <?= $activeSub ? 'bg-success' : 'bg-warning text-dark' ?> mb-2">
              <?= $activeSub ? 'ACTIVE MEMBERSHIP' : 'NO ACTIVE SUBSCRIPTION' ?>
            </span>
            <h3 class="fw-bold mb-0 text-teal"><?= e($activeSub ? $activeSub['Plan_Name'] : 'Subscription Required') ?></h3>
          </div>
          <?php if ($activeSub): ?>
            <span class="badge bg-light text-dark border p-2">
              <i class="bi bi-clock-history me-1"></i> <?= $activeSub['Days_Remaining'] ?> Days Remaining
            </span>
          <?php endif; ?>
        </div>

        <?php if ($activeSub): ?>
          <p class="text-secondary small mb-3"><?= e($activeSub['Plan_Description']) ?></p>
          <div class="row g-3 text-center mb-3">
            <div class="col-4">
              <div class="bg-light p-2 rounded-3 border">
                <div class="small text-muted">Annual Price</div>
                <div class="fw-bold text-dark"><?= formatLKR($activeSub['Price']) ?>/yr</div>
              </div>
            </div>
            <div class="col-4">
              <div class="bg-light p-2 rounded-3 border">
                <div class="small text-muted">Search Radius</div>
                <div class="fw-bold text-teal"><?= $activeSub['Search_Radius_KM'] ?> km</div>
              </div>
            </div>
            <div class="col-4">
              <div class="bg-light p-2 rounded-3 border">
                <div class="small text-muted">Valid Until</div>
                <div class="fw-bold text-dark"><?= formatDate($activeSub['End_Date']) ?></div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="alert alert-warning small mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            You do not currently have an active subscription. You cannot book doctor appointments or use expanded distance search until you subscribe to a plan below.
          </div>
        <?php endif; ?>

        <!-- Monthly Quota Progress -->
        <div class="mt-2 pt-3 border-top">
          <div class="d-flex justify-content-between align-items-center mb-1 small fw-semibold">
            <span>Monthly Booking Quota Usage (<?= date('F Y') ?>):</span>
            <span class="text-teal"><?= $quota['used'] ?> of <?= $quota['limit'] ?> used (<?= $quota['remaining'] ?> remaining)</span>
          </div>
          <div class="progress quota-progress mb-2">
            <?php 
              $pct = ($quota['limit'] > 0) ? min(100, round(($quota['used'] / $quota['limit']) * 100)) : 0; 
              $barColor = ($pct >= 90) ? 'bg-danger' : (($pct >= 70) ? 'bg-warning' : 'bg-teal');
            ?>
            <div class="progress-bar <?= $barColor ?>" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <small class="text-muted" style="font-size: 0.78rem;"><i class="bi bi-info-circle"></i> Quota automatically refreshes on the 1st day of every month. Cancelled appointments are not deducted.</small>
        </div>
      </div>
    </div>

    <!-- Quick Upgrade / Renew Card -->
    <div class="col-lg-4">
      <div class="card card-custom p-4 bg-light h-100 border-0">
        <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock-fill text-teal me-2"></i> Secure Membership Checkout</h5>
        <p class="small text-muted mb-3">
          Select your preferred membership tier and payment method to activate or renew your MediLink patient subscription.
        </p>

        <form method="POST" action="<?= url('client/subscription.php') ?>" id="subscribeForm">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="subscribe">

          <div class="mb-3">
            <label class="form-label small fw-semibold">Choose Tier</label>
            <select name="plan_id" id="planSelect" class="form-select" required>
              <?php foreach ($availablePlans as $p): ?>
                <option value="<?= $p['Plan_ID'] ?>" <?= ($preselectedPlanId === $p['Plan_ID'] || ($activeSub && $activeSub['Plan_ID'] === $p['Plan_ID'])) ? 'selected' : '' ?>>
                  <?= e($p['Plan_Name']) ?> — <?= formatLKR($p['Price']) ?>/year (<?= $p['Max_Book_per_Month'] ?> appts/mo, <?= $p['Search_Radius_KM'] ?> km)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Payment Method</label>
            <select name="payment_method" class="form-select">
              <option value="Credit / Debit Card (VISA / MasterCard / Amex)">Credit / Debit Card (VISA / MasterCard / Amex)</option>
              <option value="Commercial Bank IPG">Commercial Bank e-Gateway (IPG)</option>
              <option value="Sampath Vishwa Direct">Sampath Vishwa Direct Pay</option>
              <option value="HNB PayFast">HNB PayFast Mobile</option>
              <option value="Seylan Merchant Pay">Seylan Merchant Pay</option>
            </select>
          </div>

          <button type="submit" class="btn btn-teal w-100 py-2 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Confirm & Activate Plan
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Subscription History Table -->
  <div class="card card-custom p-4">
    <h5 class="fw-bold mb-3"><i class="bi bi-receipt text-teal me-2"></i> Subscription Billing History</h5>

    <?php if (empty($history)): ?>
      <p class="text-muted small mb-0">No past subscription records found.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Reference No</th>
              <th>Plan</th>
              <th>Price (LKR)</th>
              <th>Period</th>
              <th>Quota & Radius</th>
              <th>Status</th>
              <th>Purchased On</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h): ?>
              <tr>
                <td><code class="fw-bold text-dark"><?= e($h['Payment_Reference_No']) ?></code></td>
                <td><strong><?= e($h['Plan_Name']) ?></strong></td>
                <td><?= formatLKR($h['Price']) ?></td>
                <td><small><?= formatDate($h['Start_Date']) ?> – <?= formatDate($h['End_Date']) ?></small></td>
                <td><small><?= $h['Max_Book_per_Month'] ?> appts • <?= $h['Search_Radius_KM'] ?> km</small></td>
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
