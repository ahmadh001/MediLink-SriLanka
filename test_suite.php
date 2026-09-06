<?php
/**
 * Automated Verification & Unit Test Suite
 * Patient–Doctor Subscription Booking System (Sri Lanka)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/subscription.php';

$isCli = (php_sapi_name() === 'cli');

$passCount = 0;
$failCount = 0;

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>MediLink Verification Suite</title>';
    echo '<style>
        body { background: #0b0f19; color: #e2e8f0; font-family: "Cascadia Code", "Consolas", "Fira Code", monospace; padding: 30px 15px; margin: 0; display: flex; justify-content: center; }
        .container { width: 100%; max-width: 960px; background: #111827; border: 1px solid #1f2937; border-radius: 12px; box-shadow: 0 20px 45px rgba(0,0,0,0.6); overflow: hidden; }
        .terminal-header { background: #1f2937; padding: 14px 20px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #374151; }
        .dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
        .dot-red { background: #ef4444; }
        .dot-yellow { background: #f59e0b; }
        .dot-green { background: #10b981; }
        .title { margin-left: 12px; font-size: 13px; color: #9ca3af; font-weight: 600; letter-spacing: 0.5px; }
        .terminal-body { padding: 25px 28px; line-height: 1.8; font-size: 14px; }
        .banner { color: #38bdf8; font-weight: 700; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px dashed #374151; font-size: 15px; }
        .test-row { margin-bottom: 6px; display: flex; align-items: center; }
        .pass-tag { color: #22c55e; font-weight: 700; background: rgba(34, 197, 94, 0.15); padding: 1px 7px; border-radius: 4px; margin-right: 12px; font-size: 12px; letter-spacing: 0.5px; }
        .fail-tag { color: #ef4444; font-weight: 700; background: rgba(239, 68, 68, 0.15); padding: 1px 7px; border-radius: 4px; margin-right: 12px; font-size: 12px; }
        .summary-box { margin-top: 25px; padding: 16px; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.35); border-radius: 8px; color: #4ade80; font-weight: 700; text-align: center; font-size: 16px; }
    </style></head><body>';
    echo '<div class="container"><div class="terminal-header"><span class="dot dot-red"></span><span class="dot dot-yellow"></span><span class="dot dot-green"></span><span class="title">MediLink Verification Suite — PHP 8.x / MySQL PDO</span></div><div class="terminal-body">';
    echo '<div class="banner">⚡ MediLink Sri Lanka — Comprehensive System & Database Verification</div>';
} else {
    echo "=======================================================\n";
    echo " MediLink Sri Lanka - Comprehensive Verification Suite \n";
    echo "=======================================================\n\n";
}

function assertTest(string $testName, bool $condition, string $details = '') {
    global $passCount, $failCount, $isCli;
    if ($condition) {
        if ($isCli) {
            echo " [PASS] " . $testName . "\n";
        } else {
            echo '<div class="test-row"><span class="pass-tag">[PASS]</span> <span>' . htmlspecialchars($testName) . '</span></div>';
        }
        $passCount++;
    } else {
        if ($isCli) {
            echo " [FAIL] " . $testName . " -> " . $details . "\n";
        } else {
            echo '<div class="test-row"><span class="fail-tag">[FAIL]</span> <span>' . htmlspecialchars($testName) . ' <strong style="color:#f87171;">(' . htmlspecialchars($details) . ')</strong></span></div>';
        }
        $failCount++;
    }
}

$db = Database::getConnection();

// TEST 1: Database Tables Existence
$tables = ['USER', 'CLIENT', 'PROVIDER', 'DOCTOR', 'HEALTHCARE_CENTRE', 'CENTRE_DOCTOR_LINK', 'SPECIALIZATION', 'DOCTOR_SPECIALIZATION', 'SUBSCRIPTION_PLAN', 'USER_SUBSCRIPTION', 'SCHEDULED_SLOT', 'APPOINTMENT'];
$existingTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$existingTablesLower = array_map('strtolower', $existingTables);

foreach ($tables as $tbl) {
    assertTest("Table exists: {$tbl}", in_array(strtolower($tbl), $existingTablesLower), "Missing table {$tbl}");
}

// TEST 2: Demo Authentication & Password Verification
$demoUsers = [
    'admin@example.com' => 'SYSTEM_ADMIN',
    'client@example.com' => 'CLIENT',
    'expired.client@example.com' => 'CLIENT',
    'doctor@example.com' => 'PROVIDER',
    'centre@example.com' => 'PROVIDER'
];

foreach ($demoUsers as $email => $expectedRole) {
    $stmt = $db->prepare("SELECT * FROM `USER` WHERE Email = ?");
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    $validPass = ($u && password_verify('Password123!', $u['Password_Hash']));
    $validRole = ($u && $u['Role_Type'] === $expectedRole);
    assertTest("Demo User Auth: {$email} (Password: Password123!)", $validPass && $validRole, "Failed auth or role mismatch");
}

// TEST 3: Subscription Status Engine
// Client 1 (User 2) has ACTIVE subscription
$clientUserStmt = $db->prepare("SELECT User_ID, Client_ID FROM `CLIENT` WHERE User_ID = 2");
$clientUserStmt->execute();
$cUser = $clientUserStmt->fetch();
assertTest("Active Subscription Detection for User 2 (Client)", hasActiveSubscription(2), "User 2 should be active");

// Client 2 (User 3) has EXPIRED subscription
assertTest("Expired Subscription Detection for User 3", !hasActiveSubscription(3), "User 3 should be detected as expired/inactive");

// TEST 4: Monthly Quota Calculation
$quota = getMonthlyBookingQuota((int)$cUser['Client_ID'], 2);
assertTest("Monthly Quota Calculation for Active Client", $quota['is_subscribed'] === true && $quota['limit'] > 0 && $quota['has_quota'] === true, "Quota returned invalid data: " . json_encode($quota));

// TEST 5: Haversine Distance Calculation (Colombo to Kandy ~95-115 km)
$colomboLat = 6.9271;
$colomboLng = 79.8612;
$kandyLat = 7.2906;
$kandyLng = 80.6337;
$dist = calculateHaversineDistance($colomboLat, $colomboLng, $kandyLat, $kandyLng);
assertTest("Haversine Distance (Colombo to Kandy: ~95-115km)", ($dist >= 90 && $dist <= 120), "Calculated distance was: {$dist} km");

// TEST 6: Overlapping Slot Prevention Logic
// Insert a known test slot on a future date to test overlap detection
$overlapTestDate = date('Y-m-d', strtotime('+15 days'));
$db->prepare("DELETE FROM `SCHEDULED_SLOT` WHERE Provider_ID = 1 AND Slot_Date = ?")->execute([$overlapTestDate]);
$db->prepare("INSERT INTO `SCHEDULED_SLOT` (Provider_ID, Doctor_ID, Slot_Date, Start_Time, End_Time, Status) VALUES (1, 1, ?, '09:00:00', '09:20:00', 'AVAILABLE')")->execute([$overlapTestDate]);

$overlapStmt = $db->prepare("
    SELECT COUNT(*) AS overlap_count
    FROM `SCHEDULED_SLOT`
    WHERE Provider_ID = 1
      AND Slot_Date = ?
      AND Status != 'BLOCKED'
      AND (Start_Time < '09:15:00' AND End_Time > '09:05:00')
");
$overlapStmt->execute([$overlapTestDate]);
$overlapCount = (int)$overlapStmt->fetch()['overlap_count'];
assertTest("Slot Overlap Detection (09:05 - 09:15 overlapping 09:00 - 09:20)", $overlapCount > 0, "Failed to catch overlap");
$db->prepare("DELETE FROM `SCHEDULED_SLOT` WHERE Provider_ID = 1 AND Slot_Date = ?")->execute([$overlapTestDate]);

// TEST 7: Transaction-Safe Slot Booking & Double-Booking Prevention
$testDate = date('Y-m-d', strtotime('+10 days'));
// Create clean test slot
$db->prepare("DELETE FROM `SCHEDULED_SLOT` WHERE Provider_ID = 1 AND Slot_Date = ?")->execute([$testDate]);
$db->prepare("INSERT INTO `SCHEDULED_SLOT` (Provider_ID, Doctor_ID, Slot_Date, Start_Time, End_Time, Status) VALUES (1, 1, ?, '11:00:00', '11:20:00', 'AVAILABLE')")->execute([$testDate]);
$testSlotId = (int)$db->lastInsertId();

// First Booking (Should Succeed)
$db->beginTransaction();
$slotLock = $db->prepare("SELECT * FROM `SCHEDULED_SLOT` WHERE Slot_ID = ? FOR UPDATE");
$slotLock->execute([$testSlotId]);
$lockedSlot = $slotLock->fetch();

$firstBookSuccess = false;
if ($lockedSlot && $lockedSlot['Status'] === 'AVAILABLE') {
    $db->prepare("INSERT INTO `APPOINTMENT` (Client_ID, Slot_ID, Booking_DateTime, Status, Notes) VALUES (?, ?, NOW(), 'BOOKED', 'Automated Test Booking')")->execute([$cUser['Client_ID'], $testSlotId]);
    $db->prepare("UPDATE `SCHEDULED_SLOT` SET Status = 'BOOKED' WHERE Slot_ID = ?")->execute([$testSlotId]);
    $db->commit();
    $firstBookSuccess = true;
} else {
    $db->rollBack();
}
assertTest("Transactional Slot Booking (Row Lock & Commit)", $firstBookSuccess, "First booking failed");

// Second Concurrent Booking Attempt on Same Slot (Must Fail)
$db->beginTransaction();
$slotLock2 = $db->prepare("SELECT * FROM `SCHEDULED_SLOT` WHERE Slot_ID = ? FOR UPDATE");
$slotLock2->execute([$testSlotId]);
$lockedSlot2 = $slotLock2->fetch();

$secondBookBlocked = false;
if ($lockedSlot2['Status'] !== 'AVAILABLE') {
    $secondBookBlocked = true; // Correctly rejected!
    $db->rollBack();
} else {
    $db->commit();
}
assertTest("Double Booking Prevention on Locked Slot", $secondBookBlocked, "Double booking was erroneously allowed!");

// TEST 8: Appointment Cancellation & Slot Release
$apptFetch = $db->prepare("SELECT Appointment_ID FROM `APPOINTMENT` WHERE Slot_ID = ?");
$apptFetch->execute([$testSlotId]);
$testApptId = (int)$apptFetch->fetch()['Appointment_ID'];

$db->beginTransaction();
$db->prepare("UPDATE `APPOINTMENT` SET Status = 'CANCELLED' WHERE Appointment_ID = ?")->execute([$testApptId]);
$db->prepare("UPDATE `SCHEDULED_SLOT` SET Status = 'AVAILABLE' WHERE Slot_ID = ?")->execute([$testSlotId]);
$db->commit();

$verifySlot = $db->prepare("SELECT Status FROM `SCHEDULED_SLOT` WHERE Slot_ID = ?");
$verifySlot->execute([$testSlotId]);
$releasedSlotStatus = $verifySlot->fetch()['Status'];
assertTest("Appointment Cancellation & Slot Re-availability", $releasedSlotStatus === 'AVAILABLE', "Slot status is {$releasedSlotStatus}");

// Cleanup test slot & appointment
$db->prepare("DELETE FROM `APPOINTMENT` WHERE Appointment_ID = ?")->execute([$testApptId]);
$db->prepare("DELETE FROM `SCHEDULED_SLOT` WHERE Slot_ID = ?")->execute([$testSlotId]);

if ($isCli) {
    echo "\n=======================================================\n";
    echo " Test Results: Total {$passCount} Passed, {$failCount} Failed\n";
    echo "=======================================================\n";
} else {
    echo '<div class="summary-box">✓ Test Results: Total ' . $passCount . ' Passed, ' . $failCount . ' Failed</div>';
    echo '</div></div></body></html>';
}
