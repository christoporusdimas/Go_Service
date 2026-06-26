<?php
// =====================================================
// GoService - Database Connection & Security Setup
// =====================================================

// Include global helpers first
require_once __DIR__ . '/helpers.php';

// Database configurations (supporting Environment variables for Docker/Production environments)
$server   = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : "";
$database = getenv('DB_NAME') ?: "GoService";

// Set MySQLi to throw exceptions for better error catching
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect($server, $username, $password, $database);
    mysqli_set_charset($conn, "utf8mb4");
} catch (Exception $e) {
    log_error("Database Connection Failure: " . $e->getMessage());
    die("Maaf, sistem sedang mengalami kendala teknis. Silakan coba beberapa saat lagi.");
}

// Security Headers for Production-readiness
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
?>