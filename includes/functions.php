<?php
/**
 * Global Helper Functions & Utilities
 */

require_once __DIR__ . '/../config/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape HTML output to prevent XSS
 */
function e(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate absolute or relative URL safely
 */
function url(string $path = ''): string {
    $path = ltrim($path, '/');
    return rtrim(BASE_URL, '/') . '/' . $path;
}

/**
 * Safe HTTP redirection
 */
function redirect(string $path): void {
    if (strpos($path, 'http') !== 0) {
        $path = url($path);
    }
    header("Location: " . $path);
    exit;
}

/**
 * Set Flash Message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear Flash Messages
 */
function getFlash(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Check if there are any flash messages
 */
function hasFlash(): bool {
    return !empty($_SESSION['flash_messages']);
}

/**
 * Dynamic Time-of-Day Greeting (Good morning, Good afternoon, Good evening)
 */
function getTimeBasedGreeting(): string {
    // Determine greeting by 24-hour time
    $hour = (int)date('G');
    if ($hour >= 5 && $hour < 12) {
        return 'Good morning';
    } elseif ($hour >= 12 && $hour < 17) {
        return 'Good afternoon';
    } else {
        return 'Good evening';
    }
}

/**
 * Format Currency in LKR (e.g., Rs. 3,500.00)
 */
function formatLKR(float|int|string $amount): string {
    return CURRENCY_SYMBOL . ' ' . number_format((float)$amount, 2);
}

/**
 * Format Date (e.g. 05 Sep 2026)
 */
function formatDate(?string $date): string {
    if (!$date || $date === '0000-00-00') return 'N/A';
    return date('d M Y', strtotime($date));
}

/**
 * Format Time (e.g. 09:30 AM)
 */
function formatTime(?string $time): string {
    if (!$time) return 'N/A';
    return date('h:i A', strtotime($time));
}

/**
 * Format DateTime (e.g. 05 Sep 2026, 09:30 AM)
 */
function formatDateTime(?string $datetime): string {
    if (!$datetime) return 'N/A';
    return date('d M Y, h:i A', strtotime($datetime));
}

/**
 * Calculate Haversine Distance in Kilometers between two coordinates in PHP
 */
function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c, 2);
}

/**
 * Sri Lankan Cities with standard GPS coordinates (Loaded from database table `CITY`)
 */
function getSriLankanCities(): array {
    static $cachedCities = null;
    if ($cachedCities !== null) {
        return $cachedCities;
    }

    try {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT City_Name, District, Latitude, Longitude FROM `CITY` ORDER BY City_Name ASC");
        $rows = $stmt->fetchAll();

        if (!empty($rows)) {
            $cachedCities = [];
            foreach ($rows as $row) {
                $cachedCities[$row['City_Name']] = [
                    'lat'      => (float)$row['Latitude'],
                    'lng'      => (float)$row['Longitude'],
                    'district' => $row['District']
                ];
            }
            return $cachedCities;
        }
    } catch (Exception $e) {
        // Fallback to presets if database not ready
    }

    // Static fallback if table is empty or inaccessible
    $cachedCities = [
        'Colombo'      => ['lat' => 6.9271, 'lng' => 79.8612, 'district' => 'Colombo'],
        'Kandy'        => ['lat' => 7.2906, 'lng' => 80.6337, 'district' => 'Kandy'],
        'Galle'        => ['lat' => 6.0535, 'lng' => 80.2210, 'district' => 'Galle'],
        'Negombo'      => ['lat' => 7.2008, 'lng' => 79.8737, 'district' => 'Gampaha'],
        'Gampaha'      => ['lat' => 7.0840, 'lng' => 79.9939, 'district' => 'Gampaha'],
        'Kurunegala'   => ['lat' => 7.4818, 'lng' => 80.3609, 'district' => 'Kurunegala'],
        'Matara'       => ['lat' => 5.9549, 'lng' => 80.5550, 'district' => 'Matara'],
        'Jaffna'       => ['lat' => 9.6615, 'lng' => 80.0255, 'district' => 'Jaffna'],
        'Anuradhapura' => ['lat' => 8.3114, 'lng' => 80.4037, 'district' => 'Anuradhapura'],
        'Batticaloa'   => ['lat' => 7.7310, 'lng' => 81.6747, 'district' => 'Batticaloa'],
        'Ratnapura'    => ['lat' => 6.6828, 'lng' => 80.4000, 'district' => 'Ratnapura'],
        'Badulla'      => ['lat' => 6.9934, 'lng' => 81.0550, 'district' => 'Badulla'],
        'Kalutara'     => ['lat' => 6.5854, 'lng' => 79.9607, 'district' => 'Kalutara']
    ];
    return $cachedCities;
}

/**
 * Render Status Badges in HTML
 */
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
