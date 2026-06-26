<?php
// =====================================================
// GoService - Admin Authenticator Guard
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check role authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Session is invalid or not admin, redirect to central login portal
    header("Location: ../login.php?role=admin&error=login_required");
    exit;
}
?>
