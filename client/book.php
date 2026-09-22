<?php


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

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
    || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

function bookingRespond(bool $isAjax, bool $ok, string $message, ?int $appointmentId = null, ?string $redirectUrl = null, int $status = 200): void {
    if ($isAjax) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $ok,
            'message' => $message,
            'appointment_id' => $appointmentId,
            'reference' => $appointmentId ? '#APT-' . str_pad((string)$appointmentId, 5, '0', STR_PAD_LEFT) : null,
            'redirect_url' => $redirectUrl
        ]);
        exit;
    }
}

CSRF::check();

$slotId = (int)($_POST['slot_id'] ?? 0);
$providerId = (int)($_POST['provider_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if ($slotId <= 0) {
    $msg='Invalid appointment slot selected.';
    bookingRespond($isAjax,false,$msg,null,url('client/search.php'),422);
    setFlash('danger',$msg); redirect('client/search.php');
}

$db = Database::getConnection();


if (!hasActiveSubscription($userId)) {
    $msg='An active subscription plan is required to book appointments. Please subscribe or renew your membership.';
    bookingRespond($isAjax,false,$msg,null,url('client/subscription.php'),403);
    setFlash('danger',$msg); redirect('client/subscription.php');
}


$quota = getMonthlyBookingQuota($clientId, $userId);
if (!$quota['has_quota']) {
    $msg="Monthly booking quota limit reached ({$quota['used']} of {$quota['limit']} bookings used this month). Please upgrade your plan for more bookings.";
    bookingRespond($isAjax,false,$msg,null,url('client/subscription.php'),403);
    setFlash('danger',$msg); redirect('client/subscription.php');
}


try {
    $db->beginTransaction();

    
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
        $msg='The requested appointment slot does not exist.';
        bookingRespond($isAjax,false,$msg,null,url('client/search.php'),404);
        setFlash('danger',$msg); redirect('client/search.php');
    }

    $providerQuotaStmt=$db->prepare("SELECT sp.Max_Book_per_Month FROM `PROVIDER` p JOIN `USER_SUBSCRIPTION` us ON us.User_ID=p.User_ID JOIN `SUBSCRIPTION_PLAN` sp ON sp.Plan_ID=us.Plan_ID WHERE p.Provider_ID=? AND us.Status='ACTIVE' AND CURRENT_DATE BETWEEN us.Start_Date AND us.End_Date AND sp.Status='ACTIVE' AND sp.Target_Role IN ('PROVIDER','ALL') ORDER BY us.End_Date DESC LIMIT 1");
    $providerQuotaStmt->execute([(int)$slot['Provider_ID']]);
    $providerMonthlyLimit=(int)($providerQuotaStmt->fetchColumn() ?: 0);
    $providerUsedStmt=$db->prepare("SELECT COUNT(*) FROM `APPOINTMENT` a JOIN `SCHEDULED_SLOT` ss ON ss.Slot_ID=a.Slot_ID WHERE ss.Provider_ID=? AND a.Status NOT IN ('CANCELLED','REJECTED') AND MONTH(a.Booking_DateTime)=MONTH(CURRENT_DATE()) AND YEAR(a.Booking_DateTime)=YEAR(CURRENT_DATE())");
    $providerUsedStmt->execute([(int)$slot['Provider_ID']]);
    $providerUsed=(int)$providerUsedStmt->fetchColumn();
    if($providerMonthlyLimit<=0 || $providerUsed >= $providerMonthlyLimit){
        $db->rollBack();
        $msg='This provider has reached the monthly booking capacity of the current plan.';
        bookingRespond($isAjax,false,$msg,null,url('client/provider_view.php?id='.$slot['Provider_ID']),409);
        setFlash('warning',$msg); redirect('client/provider_view.php?id='.$slot['Provider_ID']);
    }

    if ($slot['Status'] !== 'AVAILABLE') {
        $db->rollBack();
        $msg='Sorry, this appointment slot was just reserved by another patient or is no longer available. Please choose another slot.';
        bookingRespond($isAjax,false,$msg,null,url('client/provider_view.php?id='.$slot['Provider_ID']),409);
        setFlash('warning',$msg); redirect('client/provider_view.php?id='.$slot['Provider_ID']);
    }

    
    $slotDateTimeStr = $slot['Slot_Date'] . ' ' . $slot['Start_Time'];
    if (strtotime($slotDateTimeStr) < time()) {
        $db->rollBack();
        $msg='Cannot book appointment slots in the past.';
        bookingRespond($isAjax,false,$msg,null,url('client/provider_view.php?id='.$slot['Provider_ID']),422);
        setFlash('danger',$msg); redirect('client/provider_view.php?id='.$slot['Provider_ID']);
    }

    
    $insAppt = $db->prepare("
        INSERT INTO `APPOINTMENT` (Client_ID, Slot_ID, Booking_DateTime, Status, Notes)
        VALUES (?, ?, NOW(), 'PENDING', ?)
    ");
    $insAppt->execute([$clientId, $slotId, $notes]);
    $appointmentId = (int)$db->lastInsertId();

    
    $updSlot = $db->prepare("
        UPDATE `SCHEDULED_SLOT`
        SET Status = 'BOOKED'
        WHERE Slot_ID = ?
    ");
    $updSlot->execute([$slotId]);

    
    $db->commit();

    $successMessage='Appointment request sent to the provider. The selected slot is reserved while the provider reviews it.';
    bookingRespond($isAjax,true,$successMessage,$appointmentId,url('client/receipt.php?id='.$appointmentId));
    setFlash('success',$successMessage.' Booking Reference ID: #APT-'.str_pad($appointmentId,5,'0',STR_PAD_LEFT));
    redirect('client/receipt.php?id='.$appointmentId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Booking Transaction Error: " . $e->getMessage());
    $msg='Unable to complete appointment booking due to a server error. Please try again.';
    bookingRespond($isAjax,false,$msg,null,url('client/provider_view.php?id='.$providerId),500);
    setFlash('danger',$msg); redirect('client/provider_view.php?id='.$providerId);
}
