<?php
// =====================================================
// GoService - Core Security & Utility Helpers
// =====================================================

// Prevent direct access if session is not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape HTML output to prevent XSS attacks.
 */
function escape($html) {
    return htmlspecialchars($html ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token if not exists, and return it.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Check if the submitted CSRF token matches the session token.
 */
function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            log_error("CSRF token verification failed from IP: " . $_SERVER['REMOTE_ADDR']);
            die("Error: Token keamanan tidak valid (CSRF). Silakan refresh halaman dan coba lagi.");
        }
    }
}

/**
 * Safe redirect helper.
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Log app errors to logs/app.log file.
 */
function log_error($message) {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/app.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

/**
 * Format currency to Rupiah.
 */
function format_rupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}

/**
 * Format date to Indonesian text format.
 */
function format_date($date_string) {
    if (empty($date_string)) return '-';
    $timestamp = strtotime($date_string);
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $day = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    return "$day $month $year";
}

/**
 * Role-Based Access Control (RBAC) guard.
 * Redirects unauthorized users.
 */
function guard_auth($allowed_roles = []) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        log_error("Unauthorized access attempt. Redirected to login page.");
        redirect((in_array('admin', $allowed_roles) ? '/GoService/Admin/login.html' : '/GoService/login.php') . "?error=login_required");
    }

    if (!empty($allowed_roles) && !in_array($_SESSION['user_role'], $allowed_roles)) {
        log_error("Unauthorized role access attempt by user ID " . $_SESSION['user_id'] . " (Role: " . $_SESSION['user_role'] . "). Redirected to 401.");
        
        if (in_array('admin', $allowed_roles)) {
            redirect("/GoService/Admin/401.html");
        } else {
            redirect("/GoService/index.php?error=unauthorized");
        }
    }
}
?>
