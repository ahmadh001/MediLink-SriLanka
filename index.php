<?php

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $role = getCurrentUserRole();

    if ($role === ROLE_ADMIN) {
        redirect('admin/dashboard.php');
    }

    if ($role === ROLE_PROVIDER) {
        redirect('provider/dashboard.php');
    }

    redirect('client/dashboard.php');
}

redirect('public/welcome.php');
