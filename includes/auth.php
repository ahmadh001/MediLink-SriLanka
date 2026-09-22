<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function isLoggedIn(): bool {
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
}


function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}


function getCurrentUserId(): ?int {
    return $_SESSION['user']['user_id'] ?? null;
}


function getCurrentUserRole(): ?string {
    return $_SESSION['user']['role'] ?? null;
}


function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please sign in to access this page.');
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        $_SESSION['return_to'] = $currentUrl;
        redirect('public/login.php');
    }

    // Re-check account status on protected requests so a suspended account
    // cannot continue using an already-open session.
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT Account_Status, Role_Type FROM `USER` WHERE User_ID = ?");
    $stmt->execute([getCurrentUserId()]);
    $live = $stmt->fetch();
    if (!$live || $live['Account_Status'] !== 'ACTIVE' || $live['Role_Type'] !== getCurrentUserRole()) {
        logoutUser();
        if (session_status() === PHP_SESSION_NONE) session_start();
        setFlash('danger', 'Your account is not currently active. Please contact the appropriate administrator.');
        redirect('public/login.php');
    }

    if ($live['Role_Type'] === ROLE_PROVIDER) {
        $v = $db->prepare("SELECT Verification_Status FROM `PROVIDER` WHERE User_ID = ?");
        $v->execute([getCurrentUserId()]);
        if ($v->fetchColumn() !== STATUS_VERIFIED) {
            logoutUser();
            if (session_status() === PHP_SESSION_NONE) session_start();
            setFlash('warning', 'Your provider account is not currently verified.');
            redirect('public/login.php');
        }
    }
}


function requireRole(array|string $allowedRoles): void {
    requireLogin();
    
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    $userRole = getCurrentUserRole();
    if (!in_array($userRole, $allowedRoles, true)) {
        setFlash('danger', 'Unauthorized access! You do not have permission to view that resource.');
        
        
        switch ($userRole) {
            case ROLE_OWNER:
                redirect('owner/dashboard.php');
                break;
            case ROLE_ADMIN:
                redirect('admin/dashboard.php');
                break;
            case ROLE_PROVIDER:
                redirect('provider/dashboard.php');
                break;
            case ROLE_CLIENT:
                redirect('client/dashboard.php');
                break;
            default:
                redirect('public/index.php');
                break;
        }
    }
}


function loginUser(array $user): void {
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $db = Database::getConnection();
    
    
    $clientId = null;
    $providerId = null;
    $providerType = null;
    $doctorOrCentreId = null;
    $businessName = null;

    if ($user['Role_Type'] === ROLE_CLIENT) {
        $stmt = $db->prepare("SELECT Client_ID, City FROM `CLIENT` WHERE User_ID = ?");
        $stmt->execute([$user['User_ID']]);
        $client = $stmt->fetch();
        if ($client) {
            $clientId = (int)$client['Client_ID'];
        }
    } elseif ($user['Role_Type'] === ROLE_PROVIDER) {
        $stmt = $db->prepare("SELECT Provider_ID, Provider_Type, Business_Name, Verification_Status FROM `PROVIDER` WHERE User_ID = ?");
        $stmt->execute([$user['User_ID']]);
        $provider = $stmt->fetch();
        if ($provider) {
            $providerId = (int)$provider['Provider_ID'];
            $providerType = $provider['Provider_Type'];
            $businessName = $provider['Business_Name'];

            if ($providerType === PROVIDER_DOCTOR) {
                $dStmt = $db->prepare("SELECT Doctor_ID FROM `DOCTOR` WHERE Provider_ID = ?");
                $dStmt->execute([$providerId]);
                $doc = $dStmt->fetch();
                $doctorOrCentreId = $doc ? (int)$doc['Doctor_ID'] : null;
            } else {
                $cStmt = $db->prepare("SELECT Centre_ID FROM `HEALTHCARE_CENTRE` WHERE Provider_ID = ?");
                $cStmt->execute([$providerId]);
                $centre = $cStmt->fetch();
                $doctorOrCentreId = $centre ? (int)$centre['Centre_ID'] : null;
            }
        }
    }

    
    $_SESSION['user'] = [
        'user_id'          => (int)$user['User_ID'],
        'email'            => $user['Email'],
        'first_name'       => $user['First_Name'],
        'last_name'        => $user['Last_Name'],
        'full_name'        => trim($user['First_Name'] . ' ' . $user['Last_Name']),
        'phone'            => $user['Phone'],
        'role'             => $user['Role_Type'],
        'status'           => $user['Account_Status'],
        'client_id'        => $clientId,
        'provider_id'      => $providerId,
        'provider_type'    => $providerType,
        'profile_id'       => $doctorOrCentreId,
        'business_name'    => $businessName,
        'logged_in_at'     => time()
    ];
}


function logoutUser(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
