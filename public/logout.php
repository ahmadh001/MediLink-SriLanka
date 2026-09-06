<?php
/**
 * Logout Page
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

logoutUser();
setFlash('info', 'You have been successfully signed out.');
redirect('public/login.php');
