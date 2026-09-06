<?php
/**
 * Database Connection using PHP PDO (Prepared Statements & Singleton Pattern)
 */

require_once __DIR__ . '/constants.php';

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // In production, write to logs without exposing raw credentials
                error_log("Database Connection Error: " . $e->getMessage());
                die("<div style='font-family:sans-serif;padding:2rem;text-align:center;'>
                        <h2 style='color:#dc3545;'>Database Connection Error</h2>
                        <p>Unable to connect to MySQL database <code>" . htmlspecialchars(DB_NAME) . "</code>.</p>
                        <p>Please ensure MySQL is running in XAMPP and the database <code>database.sql</code> has been imported.</p>
                     </div>");
            }
        }
        return self::$instance;
    }
}
