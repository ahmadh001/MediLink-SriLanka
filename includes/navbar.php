<?php
/**
 * Navigation Bar Component
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/subscription.php';

$user = getCurrentUser();
$isClient = ($user && $user['role'] === ROLE_CLIENT);
$isProvider = ($user && $user['role'] === ROLE_PROVIDER);
$isAdmin = ($user && $user['role'] === ROLE_ADMIN);

$subDetails = null;
if ($user && ($isClient || $isProvider)) {
    $subDetails = getUserActiveSubscription($user['user_id']);
}
?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="<?= url('public/index.php') ?>">
      <span class="brand-icon"><i class="bi bi-hospital"></i></span>
      <span><?= APP_NAME ?></span>
    </a>
    
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="<?= url('public/index.php') ?>"><i class="bi bi-house-door me-1"></i> Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= url('public/plans.php') ?>"><i class="bi bi-gem me-1"></i> Plans & Pricing</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= url('public/about.php') ?>"><i class="bi bi-info-circle me-1"></i> About & FAQ</a>
        </li>

        <?php if ($isClient): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= url('client/search.php') ?>"><i class="bi bi-search me-1"></i> Find Doctors</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= url('client/appointments.php') ?>"><i class="bi bi-calendar-check me-1"></i> My Appointments</a>
          </li>
        <?php endif; ?>

        <?php if ($isProvider): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= url('provider/slots.php') ?>"><i class="bi bi-calendar-plus me-1"></i> Manage Slots</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= url('provider/appointments.php') ?>"><i class="bi bi-calendar2-range me-1"></i> Appointments</a>
          </li>
          <?php if (($user['provider_type'] ?? '') === PROVIDER_CENTRE): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= url('provider/doctors.php') ?>"><i class="bi bi-people me-1"></i> Affiliated Doctors</a>
            </li>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-speedometer2 me-1"></i> Admin Management
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= url('admin/dashboard.php') ?>"><i class="bi bi-graph-up me-2"></i> Dashboard Overview</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('admin/users.php') ?>"><i class="bi bi-people me-2"></i> Manage Users</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/providers.php') ?>"><i class="bi bi-patch-check me-2"></i> Provider Verifications</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/appointments.php') ?>"><i class="bi bi-calendar-range me-2"></i> Manage Appointments</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/subscriptions.php') ?>"><i class="bi bi-receipt me-2"></i> Subscription Ledger</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/plans.php') ?>"><i class="bi bi-tags me-2"></i> Subscription Plans</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/specializations.php') ?>"><i class="bi bi-heart-pulse me-2"></i> Specializations</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/cities.php') ?>"><i class="bi bi-geo-alt me-2"></i> Manage Cities</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/reports.php') ?>"><i class="bi bi-file-earmark-bar-graph me-2"></i> System Reports</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>

      <!-- Right Side Actions & User Profile -->
      <div class="d-flex align-items-center gap-2">
        <?php if ($user): ?>
          <!-- Subscription Badge for Client/Provider -->
          <?php if ($isClient || $isProvider): ?>
            <?php if ($subDetails): ?>
              <span class="badge bg-success d-none d-md-inline-block py-2 px-3">
                <i class="bi bi-patch-check-fill me-1"></i> <?= e($subDetails['Plan_Name']) ?>
              </span>
            <?php else: ?>
              <a href="<?= url(($isClient ? 'client' : 'provider') . '/subscription.php') ?>" class="badge bg-warning text-dark text-decoration-none py-2 px-3">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Expired - Renew Now
              </a>
            <?php endif; ?>
          <?php elseif ($isAdmin): ?>
            <span class="badge bg-danger py-2 px-3">
              <i class="bi bi-shield-lock-fill me-1"></i> Admin Superuser
            </span>
          <?php endif; ?>

          <!-- User Profile Dropdown -->
          <div class="dropdown">
            <button class="btn btn-light btn-sm dropdown-toggle rounded-pill px-3 py-2 fw-semibold d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle fs-6 me-2 text-primary"></i>
              <span><?= e($user['full_name']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
              <li class="dropdown-header text-muted">
                <small>Signed in as <strong><?= e($user['role']) ?></strong></small><br>
                <small class="text-truncate d-block" style="max-width: 180px;"><?= e($user['email']) ?></small>
              </li>
              <li><hr class="dropdown-divider"></li>
              
              <?php if ($isClient): ?>
                <li><a class="dropdown-item" href="<?= url('client/dashboard.php') ?>"><i class="bi bi-columns-gap me-2"></i> Client Dashboard</a></li>
                <li><a class="dropdown-item" href="<?= url('client/profile.php') ?>"><i class="bi bi-person-gear me-2"></i> Edit Profile</a></li>
                <li><a class="dropdown-item" href="<?= url('client/subscription.php') ?>"><i class="bi bi-credit-card me-2"></i> My Subscription</a></li>
              <?php elseif ($isProvider): ?>
                <li><a class="dropdown-item" href="<?= url('provider/dashboard.php') ?>"><i class="bi bi-columns-gap me-2"></i> Provider Dashboard</a></li>
                <li><a class="dropdown-item" href="<?= url('provider/profile.php') ?>"><i class="bi bi-building-gear me-2"></i> Provider Profile</a></li>
                <li><a class="dropdown-item" href="<?= url('provider/subscription.php') ?>"><i class="bi bi-credit-card me-2"></i> Subscription Status</a></li>
              <?php elseif ($isAdmin): ?>
                <li><a class="dropdown-item" href="<?= url('admin/dashboard.php') ?>"><i class="bi bi-speedometer2 me-2"></i> Admin Panel</a></li>
              <?php endif; ?>

              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= url('public/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
          </div>

        <?php else: ?>
          <!-- Guest Navigation -->
          <a href="<?= url('public/login.php') ?>" class="btn btn-outline-light btn-sm px-3">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
          </a>
          <a href="<?= url('public/register.php') ?>" class="btn btn-light btn-sm px-3 fw-semibold text-teal">
            <i class="bi bi-person-plus me-1"></i> Register
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
