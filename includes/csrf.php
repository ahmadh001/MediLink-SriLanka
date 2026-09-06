<?php
/**
 * Cross-Site Request Forgery (CSRF) Protection
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class CSRF {
    public static function generateToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function getToken(): string {
        return self::generateToken();
    }

    public static function validateToken(?string $token): bool {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function inputField(): string {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!self::validateToken($token)) {
                http_response_code(403);
                die("<div style='font-family:sans-serif;padding:2rem;text-align:center;'>
                        <h2 style='color:#dc3545;'>403 Forbidden - Security Token Validation Failed</h2>
                        <p>Invalid or expired CSRF token. Please refresh the page and submit again.</p>
                        <a href='javascript:history.back()' style='color:#0d6efd;'>Go Back</a>
                     </div>");
            }
        }
    }
}
