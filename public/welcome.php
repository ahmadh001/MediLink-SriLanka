<?php
$pageTitle = 'Welcome';

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    $role = getCurrentUserRole();
    if ($role === ROLE_ADMIN) redirect('admin/dashboard.php');
    if ($role === ROLE_PROVIDER) redirect('provider/dashboard.php');
    redirect('client/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= url('public/css/style.css') ?>" rel="stylesheet">
    <style>
        body.welcome-body{min-height:100vh;margin:0;font-family:'Inter',sans-serif;background:#f7fafb;color:#17383c}.welcome-shell{min-height:100vh;display:flex;flex-direction:column}.welcome-top{padding:22px clamp(22px,5vw,72px);display:flex;align-items:center;justify-content:space-between}.welcome-brand{display:flex;align-items:center;gap:11px;font-weight:800;color:#0f3438;text-decoration:none}.welcome-logo{width:40px;height:40px;border-radius:12px;background:#e7f6f5;color:#087f78;display:grid;place-items:center;font-size:1.15rem}.welcome-login{font-weight:700;color:#087f78;text-decoration:none}.welcome-main{flex:1;display:grid;place-items:center;padding:42px 22px 64px}.welcome-wrap{width:min(100%,1040px);text-align:center}.welcome-kicker{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border-radius:999px;background:#eaf7f6;color:#087f78;font-size:.78rem;font-weight:700;margin-bottom:18px}.welcome-title{font-size:clamp(2.25rem,5vw,4.3rem);line-height:1.02;letter-spacing:-.055em;font-weight:800;color:#102f33;max-width:760px;margin:0 auto 18px}.welcome-lead{max-width:650px;margin:0 auto;color:#64748b;line-height:1.7}.welcome-primary{margin:30px 0 34px}.welcome-primary .btn{min-height:52px;border-radius:13px;padding:0 22px;font-weight:700;background:#087f78;border-color:#087f78}.welcome-primary .btn:hover{background:#066d67;border-color:#066d67}.entry-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;text-align:left;max-width:820px;margin:0 auto}.role-card{background:#fff;border:1px solid #dfe8eb;border-radius:20px;padding:25px;box-shadow:0 12px 35px rgba(15,23,42,.045);text-decoration:none;color:inherit;transition:.2s ease}.role-card:hover{transform:translateY(-3px);border-color:#b9deda;box-shadow:0 18px 42px rgba(15,23,42,.08)}.role-icon{width:46px;height:46px;border-radius:13px;background:#eaf7f6;color:#087f78;display:grid;place-items:center;font-size:1.25rem;margin-bottom:18px}.role-card h2{font-size:1.08rem;font-weight:800;margin-bottom:8px}.role-card p{font-size:.88rem;color:#64748b;line-height:1.6;margin-bottom:16px}.role-link{font-size:.84rem;font-weight:700;color:#087f78;display:flex;align-items:center;gap:6px}.guest-row{margin-top:24px;color:#64748b;font-size:.84rem}.guest-row a{color:#087f78;font-weight:700;text-decoration:none}.welcome-foot{padding:18px 22px 26px;text-align:center;color:#94a3b8;font-size:.74rem}.welcome-title,.welcome-lead,.welcome-primary,.entry-grid{animation:welcomeUp .55s cubic-bezier(.22,1,.36,1) both}.welcome-lead{animation-delay:.05s}.welcome-primary{animation-delay:.1s}.entry-grid{animation-delay:.15s}@keyframes welcomeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}@media(max-width:700px){.welcome-top{padding:18px 20px}.welcome-main{padding-top:25px}.entry-grid{grid-template-columns:1fr}.welcome-title{font-size:2.45rem}.role-card{padding:21px}}@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
    </style>
</head>
<body class="welcome-body">
<div class="welcome-shell">
    <header class="welcome-top">
        <a href="<?= url('public/index.php') ?>" class="welcome-brand">
            <span class="welcome-logo"><i class="bi bi-hospital"></i></span>
            <span><?= e(APP_NAME) ?> <small class="d-block fw-medium text-secondary" style="font-size:.62rem">Sri Lanka</small></span>
        </a>
        <a href="<?= url('public/login.php') ?>" class="welcome-login"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</a>
    </header>

    <main class="welcome-main">
        <div class="welcome-wrap">
            <div class="welcome-kicker"><i class="bi bi-heart-pulse"></i> Welcome to MediLink</div>
            <h1 class="welcome-title">Choose how you want to use MediLink.</h1>
            <p class="welcome-lead">Find healthcare providers as a patient, or create a provider account to manage your healthcare presence and appointments.</p>

            <div class="welcome-primary">
                <a href="<?= url('client/search.php') ?>" class="btn btn-primary"><i class="bi bi-search me-2"></i>Find a Doctor</a>
            </div>

            <div class="entry-grid">
                <a class="role-card" href="<?= url('public/register.php?role=client') ?>">
                    <span class="role-icon"><i class="bi bi-person"></i></span>
                    <h2>I'm looking for healthcare</h2>
                    <p>Search doctors and centres, review available information, and create a client account when you're ready to book.</p>
                    <span class="role-link">Create client account <i class="bi bi-arrow-right"></i></span>
                </a>

                <a class="role-card" href="<?= url('public/register.php?role=provider') ?>">
                    <span class="role-icon"><i class="bi bi-building"></i></span>
                    <h2>I'm a healthcare provider</h2>
                    <p>Register as a doctor or healthcare centre and access provider tools for profiles, schedules, and appointments.</p>
                    <span class="role-link">Create provider account <i class="bi bi-arrow-right"></i></span>
                </a>
            </div>

            <div class="guest-row">
                Prefer to look around first? <a href="<?= url('public/index.php') ?>">Browse MediLink as a guest <i class="bi bi-arrow-right-short"></i></a>
            </div>
        </div>
    </main>

    <footer class="welcome-foot"><i class="bi bi-shield-check me-1"></i> Protected account access • MediLink Sri Lanka</footer>
</div>
</body>
</html>
