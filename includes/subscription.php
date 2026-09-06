<?php
/**
 * Subscription & Quota Validation Engine
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

/**
 * Check if a user has an active subscription.
 * Note: System administrators bypass subscription restrictions.
 */
function hasActiveSubscription(?int $userId): bool {
    if (!$userId) return false;

    // Admin bypasses all subscription restrictions
    $user = getCurrentUser();
    if ($user && $user['user_id'] === $userId && $user['role'] === ROLE_ADMIN) {
        return true;
    }

    $db = Database::getConnection();
    $stmt = $db->prepare("
        SELECT us.User_Subscription_ID, us.Start_Date, us.End_Date, us.Status, sp.Plan_Name
        FROM `USER_SUBSCRIPTION` us
        JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID = sp.Plan_ID
        WHERE us.User_ID = ?
          AND us.Status = 'ACTIVE'
          AND CURRENT_DATE BETWEEN us.Start_Date AND us.End_Date
        ORDER BY us.End_Date DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $sub = $stmt->fetch();

    return !empty($sub);
}

/**
 * Get active subscription details for a user
 */
function getUserActiveSubscription(?int $userId): ?array {
    if (!$userId) return null;

    $user = getCurrentUser();
    if ($user && $user['user_id'] === $userId && $user['role'] === ROLE_ADMIN) {
        return [
            'User_Subscription_ID' => 0,
            'Plan_ID' => 0,
            'Plan_Name' => 'System Admin Unlimited',
            'Price' => 0.00,
            'Duration_Days' => 9999,
            'Max_Book_per_Month' => 9999,
            'Search_Radius_KM' => 9999,
            'Start_Date' => date('Y-01-01'),
            'End_Date' => date('Y-12-31'),
            'Status' => 'ACTIVE',
            'Payment_Reference_No' => 'ADMIN-OVERRIDE',
            'Days_Remaining' => 365
        ];
    }

    $db = Database::getConnection();
    $stmt = $db->prepare("
        SELECT us.*, sp.Plan_Name, sp.Price, sp.Duration_Days, sp.Max_Book_per_Month, 
               sp.Search_Radius_KM, sp.Description AS Plan_Description,
               DATEDIFF(us.End_Date, CURRENT_DATE) AS Days_Remaining
        FROM `USER_SUBSCRIPTION` us
        JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID = sp.Plan_ID
        WHERE us.User_ID = ?
          AND us.Status = 'ACTIVE'
          AND CURRENT_DATE BETWEEN us.Start_Date AND us.End_Date
        ORDER BY us.End_Date DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $sub = $stmt->fetch();

    return $sub ?: null;
}

/**
 * Get client monthly booking quota usage
 */
function getMonthlyBookingQuota(int $clientId, ?int $userId = null): array {
    $db = Database::getConnection();

    if ($userId === null) {
        $stmt = $db->prepare("SELECT User_ID FROM `CLIENT` WHERE Client_ID = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();
        $userId = $client ? (int)$client['User_ID'] : null;
    }

    $activeSub = getUserActiveSubscription($userId);
    $maxLimit = $activeSub ? (int)$activeSub['Max_Book_per_Month'] : 0;

    // Count non-cancelled appointments booked in the current calendar month
    $qStmt = $db->prepare("
        SELECT COUNT(*) AS used_count
        FROM `APPOINTMENT`
        WHERE Client_ID = ?
          AND Status != 'CANCELLED'
          AND MONTH(Booking_DateTime) = MONTH(CURRENT_DATE())
          AND YEAR(Booking_DateTime) = YEAR(CURRENT_DATE())
    ");
    $qStmt->execute([$clientId]);
    $used = (int)$qStmt->fetch()['used_count'];

    $remaining = max(0, $maxLimit - $used);
    $hasQuota = ($activeSub !== null) && ($used < $maxLimit);

    return [
        'is_subscribed' => ($activeSub !== null),
        'plan_name'     => $activeSub ? $activeSub['Plan_Name'] : 'No Active Plan',
        'used'          => $used,
        'limit'         => $maxLimit,
        'remaining'     => $remaining,
        'has_quota'     => $hasQuota,
        'end_date'      => $activeSub['End_Date'] ?? null,
        'days_remaining'=> $activeSub['Days_Remaining'] ?? 0
    ];
}

/**
 * Retrieve allowed Search Radius in KM for a user
 */
function getSearchRadiusKM(?int $userId): int {
    if (!$userId) return 5; // Default free radius if not logged in
    $sub = getUserActiveSubscription($userId);
    return $sub ? (int)$sub['Search_Radius_KM'] : 5;
}

/**
 * Subscribe or Renew user plan with payment reference
 */
function subscribeUser(int $userId, int $planId, string $paymentRef): bool {
    $db = Database::getConnection();

    // Fetch plan details
    $pStmt = $db->prepare("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Plan_ID = ? AND Status = 'ACTIVE'");
    $pStmt->execute([$planId]);
    $plan = $pStmt->fetch();
    if (!$plan) return false;

    $duration = (int)$plan['Duration_Days'];
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime("+{$duration} days"));

    $db->beginTransaction();
    try {
        // Mark any previous active subscriptions as EXPIRED
        $expireStmt = $db->prepare("
            UPDATE `USER_SUBSCRIPTION` 
            SET Status = 'EXPIRED' 
            WHERE User_ID = ? AND Status = 'ACTIVE'
        ");
        $expireStmt->execute([$userId]);

        // Insert new active subscription
        $insStmt = $db->prepare("
            INSERT INTO `USER_SUBSCRIPTION` (User_ID, Plan_ID, Start_Date, End_Date, Status, Payment_Reference_No)
            VALUES (?, ?, ?, ?, 'ACTIVE', ?)
        ");
        $insStmt->execute([$userId, $planId, $startDate, $endDate, $paymentRef]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Subscription Error: " . $e->getMessage());
        return false;
    }
}
