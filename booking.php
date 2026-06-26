<?php
// =====================================================
// GoService - Secure Booking Processor
// =====================================================

require_once 'connect.php';

// Auth Guard: Only logged in customers can book services
guard_auth(['customer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    verify_csrf();

    $user_id    = $_SESSION['user_id'];
    $nama       = trim($_POST['nama'] ?? '');
    $telepon    = trim($_POST['telepon'] ?? '');
    $alamat     = trim($_POST['alamat_layanan'] ?? '');
    $service_id = intval($_POST['service_id'] ?? 0);
    $tanggal    = trim($_POST['tanggal_layanan'] ?? '');
    $waktu      = trim($_POST['waktu_layanan'] ?? '');
    $deskripsi  = trim($_POST['deskripsi'] ?? '');

    if (empty($nama) || empty($telepon) || empty($alamat) || $service_id === 0 || empty($tanggal) || empty($waktu)) {
        log_error("Failed booking creation: empty required fields.");
        redirect("index.php?booking=failed&error=empty_fields#booking");
    }

    // Fetch the base price for the selected service
    $total_harga = 0.00;
    $stmt_price = mysqli_prepare($conn, "SELECT harga_dasar FROM services WHERE id = ?");
    mysqli_stmt_bind_param($stmt_price, "i", $service_id);
    mysqli_stmt_execute($stmt_price);
    mysqli_stmt_bind_result($stmt_price, $harga_dasar);
    if (mysqli_stmt_fetch($stmt_price)) {
        $total_harga = $harga_dasar;
    }
    mysqli_stmt_close($stmt_price);

    // Generate unique order code: GS-YYYYMMDD-XXX
    $kode = 'GS-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

    // Insert the booking order
    $stmt_insert = mysqli_prepare($conn, "INSERT INTO orders (kode_pesanan, user_id, service_id, tanggal_layanan, waktu_layanan, alamat_layanan, deskripsi, total_harga, status, status_bayar, metode_bayar) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'belum', 'tunai')");
    mysqli_stmt_bind_param($stmt_insert, "siissssd", $kode, $user_id, $service_id, $tanggal, $waktu, $alamat, $deskripsi, $total_harga);

    if (mysqli_stmt_execute($stmt_insert)) {
        mysqli_stmt_close($stmt_insert);
        log_error("Booking created successfully: $kode for user ID $user_id");
        // Redirect to dashboard with booking success code
        redirect("customer_dashboard.php?booking_success=" . urlencode($kode));
    } else {
        log_error("Booking database insert error for user ID $user_id");
        mysqli_stmt_close($stmt_insert);
        redirect("index.php?booking=failed&error=db_error#booking");
    }
} else {
    // If not a POST request, redirect to home page
    redirect("index.php");
}
?>
