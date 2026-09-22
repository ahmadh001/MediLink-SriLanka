<?php


require_once __DIR__ . '/../config/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function e(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}


function url(string $path = ''): string {
    $path = ltrim($path, '/');
    return rtrim(BASE_URL, '/') . '/' . $path;
}


function redirect(string $path): void {
    if (strpos($path, 'http') !== 0) {
        $path = url($path);
    }
    header("Location: " . $path);
    exit;
}


function setFlash(string $type, string $message): void {
    $_SESSION['flash_messages'][] = [
        'type' => $type, 
        'message' => $message
    ];
}


function getFlash(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}


function hasFlash(): bool {
    return !empty($_SESSION['flash_messages']);
}


function getTimeBasedGreeting(): string {
    
    $hour = (int)date('G');
    if ($hour >= 5 && $hour < 12) {
        return 'Good morning';
    } elseif ($hour >= 12 && $hour < 17) {
        return 'Good afternoon';
    } else {
        return 'Good evening';
    }
}


function formatLKR(float|int|string $amount): string {
    return CURRENCY_SYMBOL . ' ' . number_format((float)$amount, 2);
}


function formatDate(?string $date): string {
    if (!$date || $date === '0000-00-00') return 'N/A';
    return date('d M Y', strtotime($date));
}


function formatTime(?string $time): string {
    if (!$time) return 'N/A';
    return date('h:i A', strtotime($time));
}


function formatDateTime(?string $datetime): string {
    if (!$datetime) return 'N/A';
    return date('d M Y, h:i A', strtotime($datetime));
}


function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadius = 6371; 
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c, 2);
}


function getSriLankanCities(): array {
    // Keep a complete Sri Lankan district-centre list available even when an
    // older database only contains the original demo cities. Exact user
    // coordinates are still stored separately from this city/district label.
    $defaults = [
        'Ampara' => ['lat'=>7.2912,'lng'=>81.6724,'district'=>'Ampara'],
        'Anuradhapura' => ['lat'=>8.3114,'lng'=>80.4037,'district'=>'Anuradhapura'],
        'Badulla' => ['lat'=>6.9934,'lng'=>81.0550,'district'=>'Badulla'],
        'Batticaloa' => ['lat'=>7.7310,'lng'=>81.6747,'district'=>'Batticaloa'],
        'Colombo' => ['lat'=>6.9271,'lng'=>79.8612,'district'=>'Colombo'],
        'Galle' => ['lat'=>6.0535,'lng'=>80.2210,'district'=>'Galle'],
        'Gampaha' => ['lat'=>7.0840,'lng'=>79.9939,'district'=>'Gampaha'],
        'Hambantota' => ['lat'=>6.1241,'lng'=>81.1185,'district'=>'Hambantota'],
        'Jaffna' => ['lat'=>9.6615,'lng'=>80.0255,'district'=>'Jaffna'],
        'Kalutara' => ['lat'=>6.5854,'lng'=>79.9607,'district'=>'Kalutara'],
        'Kandy' => ['lat'=>7.2906,'lng'=>80.6337,'district'=>'Kandy'],
        'Kegalle' => ['lat'=>7.2513,'lng'=>80.3464,'district'=>'Kegalle'],
        'Kilinochchi' => ['lat'=>9.3803,'lng'=>80.3770,'district'=>'Kilinochchi'],
        'Kurunegala' => ['lat'=>7.4818,'lng'=>80.3609,'district'=>'Kurunegala'],
        'Mannar' => ['lat'=>8.9810,'lng'=>79.9044,'district'=>'Mannar'],
        'Matale' => ['lat'=>7.4675,'lng'=>80.6234,'district'=>'Matale'],
        'Matara' => ['lat'=>5.9549,'lng'=>80.5550,'district'=>'Matara'],
        'Monaragala' => ['lat'=>6.8728,'lng'=>81.3507,'district'=>'Monaragala'],
        'Mullaitivu' => ['lat'=>9.2671,'lng'=>80.8142,'district'=>'Mullaitivu'],
        'Nuwara Eliya' => ['lat'=>6.9497,'lng'=>80.7891,'district'=>'Nuwara Eliya'],
        'Polonnaruwa' => ['lat'=>7.9403,'lng'=>81.0188,'district'=>'Polonnaruwa'],
        'Puttalam' => ['lat'=>8.0408,'lng'=>79.8394,'district'=>'Puttalam'],
        'Ratnapura' => ['lat'=>6.6828,'lng'=>80.4000,'district'=>'Ratnapura'],
        'Trincomalee' => ['lat'=>8.5874,'lng'=>81.2152,'district'=>'Trincomalee'],
        'Vavuniya' => ['lat'=>8.7514,'lng'=>80.4971,'district'=>'Vavuniya'],
        'Negombo' => ['lat'=>7.2008,'lng'=>79.8737,'district'=>'Gampaha']
    ];

    try {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT City_Name, District, Latitude, Longitude FROM `CITY` ORDER BY City_Name ASC");
        foreach ($stmt->fetchAll() as $row) {
            $defaults[$row['City_Name']] = [
                'lat' => (float)$row['Latitude'], 'lng' => (float)$row['Longitude'],
                'district' => $row['District']
            ];
        }
    } catch (Exception $e) {
        // Registration remains usable with the built-in location catalogue.
    }
    ksort($defaults);
    return $defaults;
}


function renderStatusBadge(string $status): string {
    $status = strtoupper($status);
    $map = [
        'ACTIVE'    => 'bg-success',
        'VERIFIED'  => 'bg-success',
        'AVAILABLE' => 'bg-success',
        'CONFIRMED' => 'bg-info text-dark',
        'COMPLETED' => 'bg-primary',
        'BOOKED'    => 'bg-warning text-dark',
        'PENDING'   => 'bg-secondary',
        'EXPIRED'   => 'bg-danger',
        'CANCELLED' => 'bg-danger',
        'SUSPENDED' => 'bg-danger',
        'REJECTED'  => 'bg-danger',
        'BLOCKED'   => 'bg-dark',
        'NO_SHOW'   => 'bg-secondary'
    ];
    $cls = $map[$status] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . e($status) . '</span>';
}


function writeAuditLog(PDO $db, ?int $actorUserId, string $actionType, string $entityType, string|int|null $entityId = null, ?string $details = null): void {
    try {
        $stmt = $db->prepare("INSERT INTO `AUDIT_LOG` (Actor_User_ID, Action_Type, Entity_Type, Entity_ID, Details) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$actorUserId, $actionType, $entityType, $entityId === null ? null : (string)$entityId, $details]);
    } catch (Throwable $e) {
        // Audit logging must not break the main user operation in local/demo environments.
    }
}

