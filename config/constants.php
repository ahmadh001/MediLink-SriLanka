<?php
/**
 * Application Constants & Configuration
 * Patient–Doctor Subscription Booking System (Sri Lanka)
 */

if (!defined('APP_NAME')) {
    define('APP_NAME', 'MediLink Sri Lanka');
    define('APP_TAGLINE', 'Patient–Doctor Subscription Booking System');
    define('CURRENCY_CODE', 'LKR');
    define('CURRENCY_SYMBOL', 'Rs.');
    
    // Determine dynamic base URL based on server environment
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Auto-detect project folder path relative to document root
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $projDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $subDir = '';
    if ($docRoot && strpos($projDir, $docRoot) === 0) {
        $subDir = substr($projDir, strlen($docRoot));
    }
    $baseUrl = rtrim($protocol . $host . $subDir, '/');
    define('BASE_URL', $baseUrl);

    // Database credentials for XAMPP
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'patient_doctor_booking');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');

    // Role Constants
    define('ROLE_CLIENT', 'CLIENT');
    define('ROLE_PROVIDER', 'PROVIDER');
    define('ROLE_ADMIN', 'SYSTEM_ADMIN');

    // Provider Types
    define('PROVIDER_DOCTOR', 'DOCTOR');
    define('PROVIDER_CENTRE', 'HEALTHCARE_CENTRE');

    // Verification Statuses
    define('STATUS_PENDING', 'PENDING');
    define('STATUS_VERIFIED', 'VERIFIED');
    define('STATUS_REJECTED', 'REJECTED');

    // Subscription Statuses
    define('SUB_ACTIVE', 'ACTIVE');
    define('SUB_EXPIRED', 'EXPIRED');
    define('SUB_CANCELLED', 'CANCELLED');
    define('SUB_PENDING', 'PENDING');

    // Appointment Statuses
    define('APPT_BOOKED', 'BOOKED');
    define('APPT_CONFIRMED', 'CONFIRMED');
    define('APPT_COMPLETED', 'COMPLETED');
    define('APPT_CANCELLED', 'CANCELLED');
    define('APPT_NO_SHOW', 'NO_SHOW');

    // Slot Statuses
    define('SLOT_AVAILABLE', 'AVAILABLE');
    define('SLOT_BOOKED', 'BOOKED');
    define('SLOT_BLOCKED', 'BLOCKED');

    // Default Coordinates (Sri Lanka center / Colombo)
    define('DEFAULT_LAT', 6.9271);
    define('DEFAULT_LNG', 79.8612);
    define('DEFAULT_CITY', 'Colombo');
}
