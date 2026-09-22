<?php


require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/subscription.php';

$user = getCurrentUser();
$isClient = ($user && $user['role'] === ROLE_CLIENT);
$isProvider = ($user && $user['role'] === ROLE_PROVIDER);
$isAdmin = ($user && $user['role'] === ROLE_ADMIN);
$isOwner = ($user && $user['role'] === ROLE_OWNER);

$subDetails = null;
if ($user && ($isClient || $isProvider)) {
    $subDetails = getUserActiveSubscription($user['user_id']);
}

$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
function mlNavActive(string $path): string {
    global $currentPath;
    return str_ends_with($currentPath, $path) ? ' active' : '';
}
?>
<nav class="navbar navbar-expand-lg navbar-light ml-navbar" aria-label="Main navigation">
  <div class="container">
    <a class="navbar-brand ml-navbar-brand" href="<?= url('public/index.php') ?>" aria-label="MediLink Sri Lanka home">
      <span class="ml-brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
      <span class="ml-brand-copy"><strong><?= APP_NAME ?></strong><small>Sri Lanka</small></span>
    </a>

    <button class="navbar-toggler ml-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <i class="bi bi-list"></i>
    </button>

    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav ml-nav-links mx-lg-auto">
        <?php if (!$user): ?>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/public/index.php') ?>" href="<?= url('public/index.php') ?>"><i class="bi bi-house"></i><span>Home</span></a></li>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/public/plans.php') ?>" href="<?= url('public/plans.php') ?>"><i class="bi bi-grid"></i><span>Plans</span></a></li>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/public/about.php') ?>" href="<?= url('public/about.php') ?>"><i class="bi bi-info-circle"></i><span>About</span></a></li>
        <?php elseif ($isClient): ?>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/client/dashboard.php') ?>" href="<?= url('client/dashboard.php') ?>"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a></li>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/client/search.php') ?>" href="<?= url('client/search.php') ?>"><i class="bi bi-search"></i><span>Find Doctors</span></a></li>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/client/appointments.php') ?>" href="<?= url('client/appointments.php') ?>"><i class="bi bi-calendar-check"></i><span>Appointments</span></a></li>
        <?php elseif ($isProvider): ?>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/provider/dashboard.php') ?>" href="<?= url('provider/dashboard.php') ?>"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a></li>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/provider/slots.php') ?>" href="<?= url('provider/slots.php') ?>"><i class="bi bi-calendar3"></i><span>Slots</span></a></li>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/provider/appointments.php') ?>" href="<?= url('provider/appointments.php') ?>"><i class="bi bi-calendar-check"></i><span>Appointments</span></a></li>
          <?php if (($user['provider_type'] ?? '') === PROVIDER_CENTRE): ?>
            <li class="nav-item"><a class="nav-link<?= mlNavActive('/provider/doctors.php') ?>" href="<?= url('provider/doctors.php') ?>"><i class="bi bi-people"></i><span>Doctors</span></a></li>
          <?php endif; ?>
        <?php elseif ($isOwner): ?>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/owner/dashboard.php') ?>" href="<?= url('owner/dashboard.php') ?>"><i class="bi bi-speedometer2"></i><span>Owner Dashboard</span></a></li>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/owner/admins.php') ?>" href="<?= url('owner/admins.php') ?>"><i class="bi bi-person-gear"></i><span>System Admins</span></a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('admin/reports.php') ?>"><i class="bi bi-bar-chart"></i><span>Reports</span></a></li>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/owner/audit.php') ?>" href="<?= url('owner/audit.php') ?>"><i class="bi bi-journal-check"></i><span>Audit</span></a></li>
        <?php elseif ($isAdmin): ?>
          <li class="nav-item"><a class="nav-link<?= mlNavActive('/admin/dashboard.php') ?>" href="<?= url('admin/dashboard.php') ?>"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-sliders"></i><span>Manage</span></a>
            <ul class="dropdown-menu ml-nav-menu">
              <li><a class="dropdown-item" href="<?= url('admin/users.php') ?>"><i class="bi bi-people"></i> Users</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/providers.php') ?>"><i class="bi bi-patch-check"></i> Providers</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/appointments.php') ?>"><i class="bi bi-calendar-range"></i> Appointments</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/subscriptions.php') ?>"><i class="bi bi-receipt"></i> Subscriptions</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/plans.php') ?>"><i class="bi bi-tags"></i> Plans</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('admin/database.php') ?>"><i class="bi bi-database"></i> Database</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/specializations.php') ?>"><i class="bi bi-heart-pulse"></i> Specializations</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/cities.php') ?>"><i class="bi bi-geo-alt"></i> Cities</a></li>
              <li><a class="dropdown-item" href="<?= url('admin/reports.php') ?>"><i class="bi bi-bar-chart"></i> Reports</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>

      <div class="ml-nav-actions">
        <?php if (!$user): ?>
          <a href="<?= url('public/login.php') ?>" class="btn ml-btn-login"><i class="bi bi-person"></i><span>Sign in</span></a>
          <a href="<?= url('client/search.php') ?>" class="btn ml-btn-primary"><i class="bi bi-search"></i><span>Find Doctors</span></a>
        <?php else: ?>
          <?php if (($isClient || $isProvider) && !$subDetails): ?>
            <a href="<?= url(($isClient ? 'client' : 'provider') . '/subscription.php') ?>" class="ml-nav-alert" title="Subscription needs attention"><i class="bi bi-exclamation-circle"></i></a>
          <?php endif; ?>
          <div class="dropdown">
            <button class="btn ml-profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="ml-avatar"><i class="bi bi-person"></i></span>
              <span class="ml-profile-copy"><strong><?= e($user['full_name']) ?></strong><small><?= e(ucfirst($user['role'])) ?></small></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end ml-nav-menu">
              <?php if ($isClient): ?>
                <li><a class="dropdown-item" href="<?= url('client/profile.php') ?>"><i class="bi bi-person-gear"></i> Profile</a></li>
                <li><a class="dropdown-item" href="<?= url('client/subscription.php') ?>"><i class="bi bi-credit-card"></i> Subscription</a></li>
              <?php elseif ($isProvider): ?>
                <li><a class="dropdown-item" href="<?= url('provider/profile.php') ?>"><i class="bi bi-building-gear"></i> Profile</a></li>
                <li><a class="dropdown-item" href="<?= url('provider/subscription.php') ?>"><i class="bi bi-credit-card"></i> Subscription</a></li>
              <?php elseif ($isOwner): ?>
                <li><a class="dropdown-item" href="<?= url('owner/dashboard.php') ?>"><i class="bi bi-speedometer2"></i> Owner dashboard</a></li>
                <li><a class="dropdown-item" href="<?= url('owner/profile.php') ?>"><i class="bi bi-person-gear"></i> Owner profile</a></li>
                <li><a class="dropdown-item" href="<?= url('owner/admins.php') ?>"><i class="bi bi-person-gear"></i> Manage admins</a></li>
              <?php elseif ($isAdmin): ?>
                <li><a class="dropdown-item" href="<?= url('admin/dashboard.php') ?>"><i class="bi bi-shield-check"></i> Admin panel</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= url('public/logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Sign out</a></li>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
