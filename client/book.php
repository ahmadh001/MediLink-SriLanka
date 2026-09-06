<?php
/**
 * Transactional Appointment Booking Processor (ACID Transaction with Row Locking)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/subscription.php';

requireRole(ROLE_CLIENT);
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$clientId = $currentUser['client_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('client/search.php');
}

CSRF::check();

$slotId = (int)($_POST['slot_id'] ?? 0);
$providerId = (int)($_POST['provider_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if ($slotId <= 0) {
    setFlash('danger', 'Invalid appointment slot selected.');
    redirect('client/search.php');
}

$db = Database::getConnection();

// 1. Verify Active Subscription
if (!hasActiveSubscription($userId)) {
    setFlash('danger', 'An active subscription plan is required to book appointments. Please subscribe or renew your membership.');
    redirect('client/subscription.php');
}

// 2. Verify Server-Side Monthly Quota
$quota = getMonthlyBookingQuota($clientId, $userId);
if (!$quota['has_quota']) {
    setFlash('danger', "Monthly booking quota limit reached ({$quota['used']} of {$quota['limit']} bookings used this month). Please upgrade your plan for more bookings.");
    redirect('client/subscription.php');
}

// 3. Execute Transaction with Row Locking (SELECT ... FOR UPDATE)
try {
    $db->beginTransaction();

    // Row Lock the target slot to prevent concurrent double-booking
    $slotStmt = $db->prepare("
        SELECT Slot_ID, Provider_ID, Doctor_ID, Slot_Date, Start_Time, End_Time, Status
        FROM `SCHEDULED_SLOT`
        WHERE Slot_ID = ?
        FOR UPDATE
    ");
    $slotStmt->execute([$slotId]);
    $slot = $slotStmt->fetch();

    if (!$slot) {
        $db->rollBack();
        setFlash('danger', 'The requested appointment slot does not exist.');
        redirect('client/search.php');
    }

    if ($slot['Status'] !== 'AVAILABLE') {
        $db->rollBack();
        setFlash('warning', 'Sorry, this appointment slot was just reserved by another patient or is no longer available. Please choose another slot.');
        redirect('client/provider_view.php?id=' . $slot['Provider_ID']);
    }

    // Check that slot date/time is not in the past
    $slotDateTimeStr = $slot['Slot_Date'] . ' ' . $slot['Start_Time'];
    if (strtotime($slotDateTimeStr) < time()) {
        $db->rollBack();
        setFlash('danger', 'Cannot book appointment slots in the past.');
        redirect('client/provider_view.php?id=' . $slot['Provider_ID']);
    }

    // Insert Appointment record
    $insAppt = $db->prepare("
        INSERT INTO `APPOINTMENT` (Client_ID, Slot_ID, Booking_DateTime, Status, Notes)
        VALUES (?, ?, NOW(), 'BOOKED', ?)
    ");
    $insAppt->execute([$clientId, $slotId, $notes]);
    $appointmentId = (int)$db->lastInsertId();

    // Update Slot status to BOOKED
    $updSlot = $db->prepare("
        UPDATE `SCHEDULED_SLOT`
        SET Status = 'BOOKED'
        WHERE Slot_ID = ?
    ");
    $updSlot->execute([$slotId]);

    // Commit Transaction
    $db->commit();

    setFlash('success', 'Appointment successfully reserved and locked! Booking Reference ID: #APT-' . str_pad($appointmentId, 5, '0', STR_PAD_LEFT));
    redirect('client/appointments.php');

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Booking Transaction Error: " . $e->getMessage());
    setFlash('danger', 'Unable to complete appointment booking due to a server error. Please try again.');
    redirect('client/provider_view.php?id=' . $providerId);
}
