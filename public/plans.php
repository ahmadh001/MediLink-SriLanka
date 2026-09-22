<?php


$pageTitle = 'Subscription Plans & Pricing';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();


$clientPlans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Status = 'ACTIVE' AND Target_Role IN ('CLIENT', 'ALL') ORDER BY Price ASC")->fetchAll();


$providerPlans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Status = 'ACTIVE' AND Target_Role = 'PROVIDER' ORDER BY Price ASC")->fetchAll();

$user = getCurrentUser();
?>

<section class="plans-hero-v2 text-center">
  <div class="container">
    <span class="plans-eyebrow"><i class="bi bi-wallet2"></i> Simple subscription access</span>
    <h1 class="fw-bold">Choose the MediLink plan that fits your role.</h1>
    <p class="plans-lead">Compare client booking access and healthcare provider publishing plans using the pricing and limits stored in MediLink.</p>

    <div class="plan-audience-switch" role="group" aria-label="Choose plan type">
      <button type="button" class="btn active" data-plan-target="client-plans" aria-pressed="true">
        <i class="bi bi-person"></i> For Clients
      </button>
      <button type="button" class="btn" data-plan-target="provider-plans" aria-pressed="false">
        <i class="bi bi-hospital"></i> For Providers
      </button>
    </div>
  </div>
</section>

<div class="container pb-5">
  <!-- Client Membership Plans -->
  <section class="mb-5 plan-section-anchor client-plans-v2" id="client-plans">
    <div class="plans-section-heading">
      <span class="plans-section-kicker"><i class="bi bi-person-check"></i> Client membership</span>
      <h2 class="h3 fw-bold mb-2">Choose your booking access</h2>
      <p class="text-muted mb-0">Compare the appointment quota and search radius included with each active client plan.</p>
    </div>

    <?php if ($clientPlans): ?>
      <div class="row g-4">
        <?php foreach ($clientPlans as $plan): ?>
          <div class="col-lg-4 col-md-6">
            <article class="client-plan-card-v2">
              <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div class="plan-icon" aria-hidden="true"><i class="bi bi-heart-pulse"></i></div>
                <span class="badge rounded-pill text-bg-light border">Client plan</span>
              </div>

              <h3 class="h5 fw-bold mb-1"><?= e($plan['Plan_Name']) ?></h3>
              <?php if (!empty($plan['Description'])): ?>
                <p class="text-muted small mb-3"><?= e($plan['Description']) ?></p>
              <?php endif; ?>

              <div class="mb-1">
                <div class="plan-price"><?= formatLKR($plan['Price']) ?></div>
                <div class="plan-duration">for <?= (int)$plan['Duration_Days'] ?> days</div>
              </div>

              <div class="plan-limit-grid">
                <div class="plan-limit">
                  <span class="small text-muted"><i class="bi bi-calendar2-check"></i> Monthly quota</span>
                  <strong><?= (int)$plan['Max_Book_per_Month'] ?> appointments</strong>
                </div>
                <div class="plan-limit">
                  <span class="small text-muted"><i class="bi bi-geo-alt"></i> Search radius</span>
                  <strong><?= (int)$plan['Search_Radius_KM'] ?> km</strong>
                </div>
              </div>

              <ul class="list-unstyled plan-features mb-4">
                <li><i class="bi bi-check2-circle"></i><span>Browse doctor profiles and specializations.</span></li>
                <li><i class="bi bi-check2-circle"></i><span>View available appointment slots before booking.</span></li>
                <li><i class="bi bi-check2-circle"></i><span>Manage your booked appointments from one account.</span></li>
              </ul>

              <div class="mt-auto">
                <?php if (!$user): ?>
                  <a href="<?= url('public/register.php?plan_id=' . $plan['Plan_ID']) ?>" class="btn btn-teal w-100 py-2">
                    <i class="bi bi-person-plus me-1"></i> Create account
                  </a>
                <?php elseif ($user['role'] === ROLE_CLIENT): ?>
                  <a href="<?= url('client/subscription.php?plan_id=' . $plan['Plan_ID']) ?>" class="btn btn-teal w-100 py-2">
                    <i class="bi bi-arrow-repeat me-1"></i> Subscribe / Renew
                  </a>
                <?php else: ?>
                  <button class="btn btn-light border w-100 py-2" disabled><i class="bi bi-lock me-1"></i> Client plan</button>
                  <div class="plan-role-note">Available to MediLink client accounts.</div>
                <?php endif; ?>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="client-plans-empty">
        <i class="bi bi-wallet2 fs-3 text-teal"></i>
        <h3 class="h6 fw-bold mt-3">No client plans available</h3>
        <p class="small text-muted mb-0">Active client subscription plans will appear here.</p>
      </div>
    <?php endif; ?>
  </section>

  <!-- Provider Listing Plans -->
  <section class="provider-plans-v2 plan-section-anchor" id="provider-plans">
    <div class="plans-section-heading">
      <span class="plans-section-kicker"><i class="bi bi-hospital"></i> Provider access</span>
      <h2 class="h3 fw-bold mb-2">Plans for healthcare providers</h2>
      <p class="text-muted mb-0">Publish provider information, manage availability and handle patient appointments with an active provider subscription.</p>
    </div>

    <?php if ($providerPlans): ?>
      <div class="row g-4">
        <?php foreach ($providerPlans as $plan): ?>
          <div class="col-md-6 col-xl-4">
            <article class="provider-plan-card-v2">
              <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div class="provider-plan-icon" aria-hidden="true"><i class="bi bi-building-check"></i></div>
                <?php
                  $providerPlanLabel = stripos($plan['Plan_Name'], 'Professional') !== false ? 'Most popular' :
                    (stripos($plan['Plan_Name'], 'Premium') !== false ? 'Premium' : 'Starter');
                ?>
                <span class="badge rounded-pill text-bg-light border"><?= e($providerPlanLabel) ?></span>
              </div>

              <h3 class="h5 fw-bold mb-1"><?= e($plan['Plan_Name']) ?></h3>
              <?php if (!empty($plan['Description'])): ?>
                <p class="text-muted small mb-3"><?= e($plan['Description']) ?></p>
              <?php endif; ?>

              <div>
                <div class="provider-plan-price"><?= formatLKR($plan['Price']) ?></div>
                <div class="provider-plan-duration">for <?= (int)$plan['Duration_Days'] ?> days</div>
              </div>

              <div class="provider-plan-summary">
                <div>
                  <span><i class="bi bi-calendar-range me-1"></i> Subscription period</span>
                  <strong><?= (int)$plan['Duration_Days'] ?> days</strong>
                </div>
                <div>
                  <span><i class="bi bi-shield-check me-1"></i> Account type</span>
                  <strong>Healthcare provider</strong>
                </div>
              </div>

              <?php $tierName=stripos($plan['Plan_Name'],'Premium')!==false?'PREMIUM':(stripos($plan['Plan_Name'],'Professional')!==false?'PROFESSIONAL':'STARTER'); ?>
              <ul class="list-unstyled provider-plan-features mb-4">
                <li><i class="bi bi-check2-circle"></i><span>Verified directory listing</span></li>
                <li><i class="bi bi-check2-circle"></i><span>Appointment request management</span></li>
                <li><i class="bi bi-check2-circle"></i><span><?= (int)$plan['Max_Book_per_Month']>=999?'High-volume':(int)$plan['Max_Book_per_Month'] ?> booking requests / month</span></li>
                <li><i class="bi bi-check2-circle"></i><span>Manual single-slot scheduling</span></li>
                <?php if($tierName!=='STARTER'): ?><li><i class="bi bi-check2-circle"></i><span>Batch + recurring scheduling</span></li><li><i class="bi bi-check2-circle"></i><span>Advanced dashboard insights</span></li><?php else: ?><li class="text-muted"><i class="bi bi-lock"></i><span>Batch + recurring scheduling — Professional</span></li><?php endif; ?>
                <?php if($tierName==='PREMIUM'): ?><li><i class="bi bi-stars"></i><span>Priority visibility + support</span></li><?php elseif($tierName==='PROFESSIONAL'): ?><li class="text-muted"><i class="bi bi-lock"></i><span>Priority visibility + support — Premium</span></li><?php endif; ?>
              </ul>

              <div class="mt-auto">
                <?php if (!$user): ?>
                  <a href="<?= url('public/register.php?role=PROVIDER&plan_id=' . $plan['Plan_ID']) ?>" class="btn btn-teal w-100 py-2">
                    <i class="bi bi-building-add me-1"></i> Register as provider
                  </a>
                <?php elseif ($user['role'] === ROLE_PROVIDER): ?>
                  <a href="<?= url('provider/subscription.php?plan_id=' . $plan['Plan_ID']) ?>" class="btn btn-teal w-100 py-2">
                    <i class="bi bi-arrow-repeat me-1"></i> Subscribe / Renew
                  </a>
                <?php else: ?>
                  <button class="btn btn-light border w-100 py-2" disabled><i class="bi bi-lock me-1"></i> Provider plan</button>
                  <div class="plan-role-note">Available to MediLink provider accounts.</div>
                <?php endif; ?>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="provider-plans-empty">
        <i class="bi bi-hospital fs-3 text-teal"></i>
        <h3 class="h6 fw-bold mt-3">No provider plans available</h3>
        <p class="small text-muted mb-0">Active healthcare provider subscription plans will appear here.</p>
      </div>
    <?php endif; ?>
  </section>

  <section class="plan-comparison-v2 mt-5" aria-labelledby="planComparisonTitle">
    <div class="plans-section-heading mb-4">
      <span class="plans-section-kicker"><i class="bi bi-layout-text-sidebar-reverse"></i> Quick comparison</span>
      <h2 class="h3 fw-bold mb-2" id="planComparisonTitle">Compare what each plan includes</h2>
      <p class="text-muted mb-0">A compact view of the limits stored for MediLink's currently active plans.</p>
    </div>

    <?php if ($clientPlans): ?>
      <div class="comparison-table-card mb-4">
        <div class="comparison-table-title"><i class="bi bi-person"></i><span>Client plans</span></div>
        <div class="table-responsive">
          <table class="table align-middle mb-0 plan-compare-table">
            <thead><tr><th>Plan</th><th>Price</th><th>Duration</th><th>Monthly bookings</th><th>Search radius</th></tr></thead>
            <tbody>
            <?php foreach ($clientPlans as $plan): ?>
              <tr>
                <td class="fw-semibold"><?= e($plan['Plan_Name']) ?></td>
                <td><?= formatLKR($plan['Price']) ?></td>
                <td><?= (int)$plan['Duration_Days'] ?> days</td>
                <td><?= (int)$plan['Max_Book_per_Month'] ?></td>
                <td><?= (int)$plan['Search_Radius_KM'] ?> km</td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($providerPlans): ?>
      <div class="comparison-table-card">
        <div class="comparison-table-title"><i class="bi bi-hospital"></i><span>Provider plans</span></div>
        <div class="table-responsive">
          <table class="table align-middle mb-0 plan-compare-table">
            <thead><tr><th>Plan</th><th>Price</th><th>Duration</th><th>Account access</th></tr></thead>
            <tbody>
            <?php foreach ($providerPlans as $plan): ?>
              <tr>
                <td class="fw-semibold"><?= e($plan['Plan_Name']) ?></td>
                <td><?= formatLKR($plan['Price']) ?></td>
                <td><?= (int)$plan['Duration_Days'] ?> days</td>
                <td>Provider workspace</td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <section class="quota-explainer-v2 mt-5" aria-labelledby="quotaTitle">
    <div class="plans-section-heading mb-4">
      <span class="plans-section-kicker"><i class="bi bi-info-circle"></i> Before you subscribe</span>
      <h2 class="h3 fw-bold mb-2" id="quotaTitle">How plan limits and fees work</h2>
      <p class="text-muted mb-0">The subscription controls MediLink platform access; medical consultation charges are separate.</p>
    </div>
    <div class="row g-3">
      <div class="col-md-6 col-lg-3"><article class="quota-info-card"><i class="bi bi-calendar2-check"></i><h3>Booking quota</h3><p>Client plans define the maximum number of appointments that can be booked per month.</p></article></div>
      <div class="col-md-6 col-lg-3"><article class="quota-info-card"><i class="bi bi-geo-alt"></i><h3>Search radius</h3><p>Your active client plan determines the distance range available for proximity-based doctor discovery.</p></article></div>
      <div class="col-md-6 col-lg-3"><article class="quota-info-card"><i class="bi bi-hourglass-split"></i><h3>Plan duration</h3><p>Each subscription remains active for the number of days shown on its plan, subject to its subscription status.</p></article></div>
      <div class="col-md-6 col-lg-3"><article class="quota-info-card"><i class="bi bi-receipt"></i><h3>Medical fees</h3><p>Doctor consultation and treatment charges are not included in the MediLink subscription price and are settled with the healthcare provider.</p></article></div>
    </div>
    <div class="plan-note-v2 mt-3"><i class="bi bi-arrow-repeat"></i><span>Existing client and provider accounts can use their subscription page to subscribe or renew an eligible plan.</span></div>
  </section>

  <section class="plans-final-cta-v2 mt-5" aria-labelledby="plansFinalCtaTitle">
    <div class="plans-final-cta-inner">
      <div class="plans-final-cta-copy">
        <span class="plans-section-kicker"><i class="bi bi-check2-circle"></i> Ready when you are</span>
        <h2 class="h3 fw-bold mb-2" id="plansFinalCtaTitle">Manage your MediLink access in one place.</h2>
        <p class="mb-0">Choose an eligible subscription for your account role, then manage renewals and plan access from your MediLink workspace.</p>
      </div>

      <div class="plans-final-cta-actions">
        <?php if (!$user): ?>
          <a href="<?= url('public/register.php') ?>" class="btn btn-teal px-4"><i class="bi bi-person-plus me-1"></i> Create Account</a>
          <a href="<?= url('public/login.php') ?>" class="btn btn-outline-teal px-4"><i class="bi bi-box-arrow-in-right me-1"></i> Sign In</a>
        <?php elseif ($user['role'] === ROLE_CLIENT): ?>
          <a href="<?= url('client/subscription.php') ?>" class="btn btn-teal px-4"><i class="bi bi-wallet2 me-1"></i> Manage Subscription</a>
        <?php elseif ($user['role'] === ROLE_PROVIDER): ?>
          <a href="<?= url('provider/subscription.php') ?>" class="btn btn-teal px-4"><i class="bi bi-building-gear me-1"></i> Manage Provider Plan</a>
        <?php else: ?>
          <a href="<?= url('admin/plans.php') ?>" class="btn btn-teal px-4"><i class="bi bi-sliders me-1"></i> Manage Plans</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="plans-final-cta-notes" aria-label="Subscription notes">
      <span><i class="bi bi-shield-lock"></i> Protected account access</span>
      <span><i class="bi bi-receipt"></i> Medical consultation fees are separate</span>
      <span><i class="bi bi-arrow-repeat"></i> Eligible accounts can renew from their subscription page</span>
    </div>
  </section>
</div>

<script>
document.querySelectorAll('[data-plan-target]').forEach(function (button) {
  button.addEventListener('click', function () {
    document.querySelectorAll('[data-plan-target]').forEach(function (item) {
      item.classList.toggle('active', item === button);
      item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
    });
    var target = document.getElementById(button.dataset.planTarget);
    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
