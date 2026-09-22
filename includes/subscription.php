<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';


function hasActiveSubscription(?int $userId): bool {
    if (!$userId) return false;

    
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



function getProviderPlanTier(?int $userId): string {
    $sub = getUserActiveSubscription($userId);
    if (!$sub) return 'NONE';
    $name = strtoupper((string)($sub['Plan_Name'] ?? ''));
    if (str_contains($name, 'PREMIUM')) return 'PREMIUM';
    if (str_contains($name, 'PROFESSIONAL')) return 'PROFESSIONAL';
    if (str_contains($name, 'STARTER')) return 'STARTER';
    return 'PROFESSIONAL';
}
function providerPlanAllows(?int $userId, string $feature): bool {
    $tier=getProviderPlanTier($userId);
    $rank=['NONE'=>0,'STARTER'=>1,'PROFESSIONAL'=>2,'PREMIUM'=>3];
    $min=['single_slots'=>1,'appointment_management'=>1,'batch_slots'=>2,'recurring_slots'=>2,'advanced_analytics'=>2,'priority_listing'=>3,'priority_support'=>3];
    return ($rank[$tier]??0) >= ($min[$feature]??99);
}

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
    $subCreated = $activeSub['Created_At'] ?? ($activeSub ? $activeSub['Start_Date'] . ' 00:00:00' : '1970-01-01 00:00:00');

    
    $qStmt = $db->prepare("
        SELECT COUNT(*) AS used_count
        FROM `APPOINTMENT`
        WHERE Client_ID = ?
          AND Status NOT IN ('CANCELLED', 'REJECTED')
          AND Booking_DateTime >= ?
          AND MONTH(Booking_DateTime) = MONTH(CURRENT_DATE())
          AND YEAR(Booking_DateTime) = YEAR(CURRENT_DATE())
    ");
    $qStmt->execute([$clientId, $subCreated]);
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


function getSearchRadiusKM(?int $userId): int {
    if (!$userId) return 5; 
    $sub = getUserActiveSubscription($userId);
    return $sub ? (int)$sub['Search_Radius_KM'] : 5;
}


function subscribeUser(int $userId, int $planId, string $paymentRef): bool {
    $db = Database::getConnection();

    
    $pStmt = $db->prepare("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Plan_ID = ? AND Status = 'ACTIVE'");
    $pStmt->execute([$planId]);
    $plan = $pStmt->fetch();
    if (!$plan) return false;

    $duration = (int)$plan['Duration_Days'];
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime("+{$duration} days"));

    $db->beginTransaction();
    try {
        
        $expireStmt = $db->prepare("
            UPDATE `USER_SUBSCRIPTION` 
            SET Status = 'EXPIRED' 
            WHERE User_ID = ? AND Status = 'ACTIVE'
        ");
        $expireStmt->execute([$userId]);

        
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
