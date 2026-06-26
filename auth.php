<?php
// =====================================================
// GoService - Secure Authentication Processor
// =====================================================

require_once 'connect.php';

// Route action processing
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// Verify CSRF token for all state-changing POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

// ===================== LOGIN =====================
if ($action === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'customer';

    if (empty($email) || empty($password)) {
        redirect("login.php?error=empty_fields&role=" . urlencode($role));
    }

    if ($role === 'customer') {
        // Customer login
        $stmt = mysqli_prepare($conn, "SELECT id, password, nama, status FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            if ($user['status'] !== 'aktif') {
                log_error("Failed customer login: account status is " . $user['status'] . " for " . $email);
                redirect("login.php?error=inactive_account&role=customer");
            }

            if (password_verify($password, $user['password'])) {
                // Password matches, log in
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_nama'] = $user['nama'];
                $_SESSION['user_role'] = 'customer';
                
                log_error("Customer logged in successfully: " . $email);
                redirect("customer_dashboard.php");
            } else {
                log_error("Failed customer login: invalid password for " . $email);
                redirect("login.php?error=1&role=customer");
            }
        } else {
            log_error("Failed customer login: email not found " . $email);
            redirect("login.php?error=1&role=customer");
        }
        mysqli_stmt_close($stmt);

    } elseif ($role === 'provider') {
        // Provider login
        $stmt = mysqli_prepare($conn, "SELECT id, password, nama, status FROM providers WHERE email = ? OR nik = ?");
        mysqli_stmt_bind_param($stmt, "ss", $email, $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($provider = mysqli_fetch_assoc($result)) {
            if ($provider['status'] !== 'aktif') {
                log_error("Failed provider login: account status is " . $provider['status'] . " for " . $email);
                redirect("login.php?error=inactive_provider_" . $provider['status'] . "&role=provider");
            }

            if (password_verify($password, $provider['password'])) {
                $_SESSION['user_id']   = $provider['id'];
                $_SESSION['user_nama'] = $provider['nama'];
                $_SESSION['user_role'] = 'provider';

                log_error("Provider logged in successfully: " . $email);
                redirect("provider_dashboard.php");
            } else {
                log_error("Failed provider login: invalid password for " . $email);
                redirect("login.php?error=1&role=provider");
            }
        } else {
            log_error("Failed provider login: provider not found " . $email);
            redirect("login.php?error=1&role=provider");
        }
        mysqli_stmt_close($stmt);

    } elseif ($role === 'admin') {
        // Admin login
        $stmt = mysqli_prepare($conn, "SELECT id, password, nama FROM admin WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($stmt, "ss", $email, $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($admin = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $admin['password'])) {
                $_SESSION['user_id']   = $admin['id'];
                $_SESSION['user_nama'] = $admin['nama'];
                $_SESSION['user_role'] = 'admin';

                log_error("Admin logged in successfully: " . $email);
                redirect("Admin/index.php");
            } else {
                log_error("Failed admin login: invalid password for " . $email);
                redirect("login.php?error=1&role=admin");
            }
        } else {
            log_error("Failed admin login: admin not found " . $email);
            redirect("login.php?error=1&role=admin");
        }
        mysqli_stmt_close($stmt);
    }
}

// ===================== REGISTER CUSTOMER =====================
if ($action === 'register_customer') {
    $nama     = trim($_POST['nama'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telepon  = trim($_POST['telepon'] ?? '');
    $alamat   = trim($_POST['alamat'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($nama) || empty($email) || empty($telepon) || empty($password)) {
        redirect("login.php?error=empty_fields&mode=register&role=customer");
    }

    // Check if email already registered in users
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        redirect("login.php?error=email_exists&mode=register&role=customer");
    }
    mysqli_stmt_close($stmt);

    // Hash the password securely using Bcrypt
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    $stmt = mysqli_prepare($conn, "INSERT INTO users (nama, email, password, telepon, alamat, status) VALUES (?, ?, ?, ?, ?, 'aktif')");
    mysqli_stmt_bind_param($stmt, "sssss", $nama, $email, $hashed_password, $telepon, $alamat);

    if (mysqli_stmt_execute($stmt)) {
        $new_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // Auto login on successful registration
        $_SESSION['user_id']   = $new_id;
        $_SESSION['user_nama'] = $nama;
        $_SESSION['user_role'] = 'customer';

        log_error("New customer registered successfully: " . $email);
        redirect("customer_dashboard.php");
    } else {
        log_error("Customer registration database error for: " . $email);
        redirect("login.php?error=register_failed&mode=register&role=customer");
    }
}

// ===================== REGISTER PROVIDER =====================
if ($action === 'register_provider') {
    $nama       = trim($_POST['nama'] ?? '');
    $nik        = trim($_POST['nik'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $telepon    = trim($_POST['telepon'] ?? '');
    $alamat     = trim($_POST['alamat'] ?? '');
    $service_id = intval($_POST['service_id'] ?? 0);
    $pengalaman = intval($_POST['pengalaman'] ?? 0);
    $deskripsi  = trim($_POST['deskripsi'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($nama) || empty($nik) || empty($email) || empty($telepon) || empty($password) || $service_id === 0) {
        redirect("login.php?error=empty_fields&mode=register&role=provider");
    }

    // Check if email or NIK already exists in providers
    $stmt = mysqli_prepare($conn, "SELECT id FROM providers WHERE email = ? OR nik = ?");
    mysqli_stmt_bind_param($stmt, "ss", $email, $nik);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        redirect("login.php?error=email_exists&mode=register&role=provider");
    }
    mysqli_stmt_close($stmt);

    // Hash the password securely using Bcrypt
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    $stmt = mysqli_prepare($conn, "INSERT INTO providers (nama, nik, email, password, telepon, alamat, service_id, pengalaman, deskripsi, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    mysqli_stmt_bind_param($stmt, "ssssssiis", $nama, $nik, $email, $hashed_password, $telepon, $alamat, $service_id, $pengalaman, $deskripsi);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        log_error("New provider registered in pending state: " . $email);
        redirect("login.php?success=registered&role=provider");
    } else {
        log_error("Provider registration database error for: " . $email);
        redirect("login.php?error=register_failed&mode=register&role=provider");
    }
}

// ===================== LOGOUT =====================
if ($action === 'logout' || isset($_GET['logout'])) {
    $role = $_SESSION['user_role'] ?? 'customer';
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    log_error("User logged out successfully.");
    redirect("index.php");
}

// Default fallthrough redirect
redirect("index.php");
?>
