<?php
/**
 * Authentication and Authorization Guard Helper
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently logged in
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
}

/**
 * Get logged-in user data from session
 */
function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Get logged-in User ID
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user']['user_id'] ?? null;
}

/**
 * Get logged-in User Role
 */
function getCurrentUserRole(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

/**
 * Require user to be authenticated, otherwise redirect to login
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please sign in to access this page.');
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        $_SESSION['return_to'] = $currentUrl;
        redirect('public/login.php');
    }
}

/**
 * Require specific role(s) to access page
 */
function requireRole(array|string $allowedRoles): void {
    requireLogin();
    
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    $userRole = getCurrentUserRole();
    if (!in_array($userRole, $allowedRoles, true)) {
        setFlash('danger', 'Unauthorized access! You do not have permission to view that resource.');
        
        // Redirect to appropriate user dashboard
        switch ($userRole) {
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

/**
 * Set session for authenticated user with associated profile IDs
 */
function loginUser(array $user): void {
    $db = Database::getConnection();
    
    // Fetch associated client or provider profile details
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

    // Populate secure session array
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

/**
 * Destroy current session on logout
 */
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
