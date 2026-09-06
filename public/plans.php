<?php
/**
 * Subscription Plans Page
 */

$pageTitle = 'Subscription Plans & Pricing';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Fetch Client Plans
$clientPlans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Status = 'ACTIVE' AND Target_Role IN ('CLIENT', 'ALL') ORDER BY Price ASC")->fetchAll();

// Fetch Provider Plans
$providerPlans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Status = 'ACTIVE' AND Target_Role = 'PROVIDER' ORDER BY Price ASC")->fetchAll();

$user = getCurrentUser();
?>

<div class="container py-5">
  <div class="text-center max-w-700 mx-auto mb-5">
    <span class="badge bg-teal-subtle text-teal px-3 py-2 rounded-pill fw-semibold mb-2">Transparent LKR Pricing</span>
    <h1 class="fw-bold">Platform Subscription Plans</h1>
    <p class="text-muted">
      MediLink operates on a transparent subscription model. Clients subscribe for booking quotas & proximity search, while healthcare providers subscribe for schedule publishing & verification.
    </p>
  </div>

  <!-- Client Membership Plans -->
  <div class="mb-5">
    <div class="d-flex align-items-center gap-2 mb-4">
      <div class="rounded-circle bg-teal text-white p-2 d-inline-flex"><i class="bi bi-people-fill"></i></div>
      <h3 class="fw-bold mb-0">Patient / Client Plans</h3>
    </div>

    <div class="row g-4">
      <?php foreach ($clientPlans as $plan): ?>
        <div class="col-md-4">
          <div class="card plan-card h-100 p-4 bg-white <?= $plan['Plan_ID'] == 2 ? 'featured' : '' ?>">
            <?php if ($plan['Plan_ID'] == 2): ?>
              <span class="badge bg-teal text-white plan-badge"><i class="bi bi-star-fill me-1"></i> Recommended</span>
            <?php endif; ?>

            <h4 class="fw-bold mb-1"><?= e($plan['Plan_Name']) ?></h4>
            <p class="text-muted small mb-3"><?= e($plan['Description']) ?></p>

            <div class="mb-4">
              <span class="stat-value text-teal"><?= formatLKR($plan['Price']) ?></span>
              <span class="text-muted small"> / year (<?= $plan['Duration_Days'] ?> days)</span>
            </div>

            <ul class="list-unstyled d-flex flex-column gap-2 small text-secondary mb-4">
              <li><i class="bi bi-check-circle-fill text-teal me-2"></i> <strong><?= $plan['Max_Book_per_Month'] ?> Appointments</strong> per month</li>
              <li><i class="bi bi-check-circle-fill text-teal me-2"></i> <strong><?= $plan['Search_Radius_KM'] ?> km</strong> search radius from your location</li>
              <li><i class="bi bi-check-circle-fill text-teal me-2"></i> Real-time slot locking with transaction safety</li>
              <li><i class="bi bi-check-circle-fill text-teal me-2"></i> Doctor bio and specializations lookup</li>
              <li><i class="bi bi-check-circle-fill text-teal me-2"></i> Live appointment cancellation</li>
            </ul>

            <div class="mt-auto">
              <?php if (!$user): ?>
                <a href="<?= url('public/register.php?plan_id=' . $plan['Plan_ID']) ?>" class="btn <?= $plan['Plan_ID'] == 2 ? 'btn-teal' : 'btn-outline-teal' ?> w-100 py-2">
                  Get Started (Register)
                </a>
              <?php elseif ($user['role'] === ROLE_CLIENT): ?>
                <a href="<?= url('client/subscription.php?plan_id=' . $plan['Plan_ID']) ?>" class="btn <?= $plan['Plan_ID'] == 2 ? 'btn-teal' : 'btn-outline-teal' ?> w-100 py-2">
                  Subscribe / Renew
                </a>
              <?php else: ?>
                <button class="btn btn-secondary w-100 py-2" disabled>Client Only Plan</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Provider Listing Plans -->
  <div class="pt-4 border-top">
    <div class="d-flex align-items-center gap-2 mb-4">
      <div class="rounded-circle bg-teal text-white p-2 d-inline-flex"><i class="bi bi-hospital-fill"></i></div>
      <h3 class="fw-bold mb-0">Healthcare Provider & Doctor Plans</h3>
    </div>

    <div class="row g-4">
      <?php foreach ($providerPlans as $plan): ?>
        <div class="col-md-6">
          <div class="card card-custom p-4 bg-white h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h4 class="fw-bold mb-1 text-teal"><?= e($plan['Plan_Name']) ?></h4>
                <p class="text-muted small mb-0"><?= e($plan['Description']) ?></p>
              </div>
              <div class="text-end">
                <span class="fs-3 fw-bold text-dark"><?= formatLKR($plan['Price']) ?></span>
                <span class="text-muted small d-block">/ month (<?= $plan['Duration_Days'] ?> days)</span>
              </div>
            </div>

            <ul class="list-unstyled d-flex flex-column gap-2 small text-secondary mb-4">
              <li><i class="bi bi-patch-check-fill text-success me-2"></i> Verified Directory Listing in Sri Lanka Registry</li>
              <li><i class="bi bi-check2-square text-success me-2"></i> Unlimited Daily, Single & Weekly Recurring Slot Creation</li>
              <li><i class="bi bi-check2-square text-success me-2"></i> Appointment confirmation & completion management</li>
              <li><i class="bi bi-check2-square text-success me-2"></i> Multi-doctor affiliation linking (Healthcare Centres)</li>
            </ul>

            <div class="mt-auto">
              <?php if (!$user): ?>
                <a href="<?= url('public/register.php?role=PROVIDER&plan_id=' . $plan['Plan_ID']) ?>" class="btn btn-teal w-100 py-2">
                  Register as Provider
                </a>
              <?php elseif ($user['role'] === ROLE_PROVIDER): ?>
                <a href="<?= url('provider/subscription.php?plan_id=' . $plan['Plan_ID']) ?>" class="btn btn-teal w-100 py-2">
                  Renew Provider Subscription
                </a>
              <?php else: ?>
                <button class="btn btn-secondary w-100 py-2" disabled>Provider Only Plan</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Medical Fee Notice Disclaimer -->
  <div class="alert alert-info border-info mt-5 p-4 rounded-3">
    <div class="d-flex gap-3">
      <i class="bi bi-info-circle-fill fs-3 text-info"></i>
      <div>
        <h5 class="fw-bold mb-1">Important Clarification on Medical Consultation Fees</h5>
        <p class="small mb-0 text-secondary">
          MediLink Sri Lanka is a subscription-based healthcare mediator platform. The subscription fees shown above grant access to discovery, proximity distance filtering, and real-time appointment booking. 
          <strong>Consultation fees and medical treatments are separate and settled directly between the patient and doctor/healthcare facility during the visit.</strong>
        </p>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
