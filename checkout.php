<?php
// =====================================================
// GoService - Simulated Payment Checkout
// =====================================================

require_once 'connect.php';

// Auth Guard: Customers only
guard_auth(['customer']);

$user_id = $_SESSION['user_id'];
$kode = trim($_GET['kode'] ?? '');

if (empty($kode)) {
    redirect("customer_dashboard.php?payment=failed");
}

// Fetch order details with safety checks
$stmt = mysqli_prepare($conn, "SELECT o.id, o.kode_pesanan, o.total_harga, o.status, o.status_bayar, s.nama AS nama_layanan 
                               FROM orders o
                               JOIN services s ON o.service_id = s.id
                               WHERE o.kode_pesanan = ? AND o.user_id = ?");
mysqli_stmt_bind_param($stmt, "si", $kode, $user_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    log_error("Checkout access failed: Order $kode not found or doesn't belong to user ID $user_id");
    redirect("customer_dashboard.php?payment=failed");
}

// If already paid, redirect
if ($order['status_bayar'] === 'lunas') {
    redirect("customer_dashboard.php?payment=success");
}

// Handle payment submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    verify_csrf();
    
    $payment_method = trim($_POST['payment_method'] ?? 'bank_transfer');
    
    // Prepare webhook payload
    $payload = [
        'order_code'   => $kode,
        'payment_type' => $payment_method,
        'amount'       => floatval($order['total_harga']),
        'status'       => 'settlement',
        'signature'    => hash('sha256', $kode . "MOCK_GATEWAY_SECRET")
    ];

    $success = false;

    // Method 1: Webhook Simulation via local loopback POST
    try {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = dirname($_SERVER['PHP_SELF']);
        if ($uri === '/' || $uri === '\\') $uri = '';
        $webhook_url = "$protocol://$host$uri/payment_webhook.php";

        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($payload),
                'timeout' => 3
            ]
        ];
        $context  = stream_context_create($options);
        $response = @file_get_contents($webhook_url, false, $context);
        
        if ($response) {
            $res_data = json_decode($response, true);
            if (($res_data['status'] ?? '') === 'success') {
                $success = true;
            }
        }
    } catch (Exception $e) {
        log_error("Local loopback webhook cURL simulation failed: " . $e->getMessage());
    }

    // Method 2: Direct Database Fallback (if server loopback fails)
    if (!$success) {
        mysqli_begin_transaction($conn);
        try {
            $db_metode = in_array($payment_method, ['gopay', 'ovo', 'dana']) ? 'ewallet' : 'transfer';
            $stmt_upd = mysqli_prepare($conn, "UPDATE orders SET status_bayar = 'lunas', status = 'dikonfirmasi', metode_bayar = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_upd, "si", $db_metode, $order['id']);
            mysqli_stmt_execute($stmt_upd);
            mysqli_stmt_close($stmt_upd);

            $trans_id = 'TRX-' . time() . '-' . rand(1000, 9999);
            $raw_payload = json_encode(array_merge($payload, ['fallback' => true]));
            
            $stmt_trans = mysqli_prepare($conn, "INSERT INTO transactions (order_id, transaction_id, gross_amount, payment_type, status, raw_payload) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_trans, "isdsss", $order['id'], $trans_id, $order['total_harga'], $payment_method, 'settlement', $raw_payload);
            mysqli_stmt_execute($stmt_trans);
            mysqli_stmt_close($stmt_trans);

            mysqli_commit($conn);
            $success = true;
            log_error("Fallback direct settlement processed successfully for order " . $kode);
        } catch (Exception $ex) {
            mysqli_rollback($conn);
            log_error("Direct checkout settlement fallback error: " . $ex->getMessage());
        }
    }

    if ($success) {
        redirect("customer_dashboard.php?payment=success");
    } else {
        redirect("customer_dashboard.php?payment=failed");
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selesaikan Pembayaran - GoService</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .checkout-page {
            max-width: 600px;
            margin: 40px auto 60px;
            padding: 0 24px;
        }
        .checkout-card {
            background: #fff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            padding: 32px;
            box-shadow: var(--shadow-lg);
        }
        .billing-item {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed var(--border);
            padding: 12px 0;
            font-size: 0.95rem;
        }
        .billing-item.total {
            border-bottom: none;
            padding-top: 18px;
            font-size: 1.2rem;
            color: var(--primary);
            font-weight: 700;
        }
        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 24px 0;
        }
        .method-option {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .method-option:hover {
            border-color: var(--primary-light);
            background: var(--bg);
        }
        .method-option input {
            display: none;
        }
        .method-option input:checked + .method-box {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
        }
        .method-box {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: transparent;
            transition: all 0.2s;
        }
        .method-info {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .method-option.selected {
            border-color: var(--primary);
            background: #eff6ff;
        }
        .method-icon-wrap {
            font-size: 1.4rem;
            width: 32px;
            color: var(--primary);
            text-align: center;
        }
        /* Loader screen */
        .payment-loader {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.9);
            color: #fff;
            z-index: 2000;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.1);
            border-top: 4px solid var(--primary-light);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<header class="navbar">
    <div class="container">
        <a href="index.php" class="nav-brand"><i class="fas fa-bolt"></i> GoService</a>
        <div class="nav-btns">
            <a href="customer_dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>
</header>

<div class="checkout-page">
    <div class="checkout-card">
        <h2 style="margin-bottom:6px;"><i class="fas fa-receipt"></i> Rincian Pembayaran</h2>
        <p style="color:var(--text-light); font-size:0.9rem; margin-bottom:24px;">Silakan selesaikan invoice pemesanan Anda.</p>
        
        <div class="billing-item">
            <span>Kode Pesanan</span>
            <strong><?= escape($order['kode_pesanan']) ?></strong>
        </div>
        
        <div class="billing-item">
            <span>Layanan</span>
            <span><?= escape($order['nama_layanan']) ?></span>
        </div>

        <div class="billing-item total">
            <span>Total Bayar</span>
            <span><?= format_rupiah($order['total_harga']) ?></span>
        </div>

        <form action="" method="POST" onsubmit="showPaymentLoader()">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="pay_now" value="1">

            <h3 style="margin-top: 30px; margin-bottom: 12px; font-size:1.1rem;">Pilih Metode Pembayaran</h3>
            <div class="payment-methods">
                
                <label class="method-option selected" onclick="selectMethod(this)">
                    <input type="radio" name="payment_method" value="bank_transfer" checked>
                    <span class="method-box"><i class="fas fa-check"></i></span>
                    <div class="method-info">
                        <div class="method-icon-wrap"><i class="fas fa-university"></i></div>
                        <span>Transfer Bank (Virtual Account)</span>
                    </div>
                </label>

                <label class="method-option" onclick="selectMethod(this)">
                    <input type="radio" name="payment_method" value="gopay">
                    <span class="method-box"><i class="fas fa-check"></i></span>
                    <div class="method-info">
                        <div class="method-icon-wrap"><i class="fas fa-qrcode"></i></div>
                        <span>GoPay / QRIS</span>
                    </div>
                </label>

                <label class="method-option" onclick="selectMethod(this)">
                    <input type="radio" name="payment_method" value="credit_card">
                    <span class="method-box"><i class="fas fa-check"></i></span>
                    <div class="method-info">
                        <div class="method-icon-wrap"><i class="far fa-credit-card"></i></div>
                        <span>Kartu Kredit / Debit Online</span>
                    </div>
                </label>

            </div>

            <button type="submit" class="btn-submit" style="margin-top:20px; font-size:1.05rem;"><i class="fas fa-lock"></i> Bayar Sekarang</button>
        </form>
    </div>
</div>

<!-- Simulated payment loader -->
<div class="payment-loader" id="paymentLoader">
    <div class="spinner"></div>
    <h3 style="font-weight: 500;">Memproses Pembayaran...</h3>
    <p style="opacity: 0.7; font-size: 0.85rem;">Menghubungkan ke gateway pembayaran bank dan memverifikasi invoice</p>
</div>

<script>
function selectMethod(labelElement) {
    document.querySelectorAll('.method-option').forEach(el => {
        el.classList.remove('selected');
        el.querySelector('.method-box').style.borderColor = 'var(--border)';
        el.querySelector('.method-box').style.background = 'transparent';
        el.querySelector('.method-box').style.color = 'transparent';
    });
    
    labelElement.classList.add('selected');
    const checkedBox = labelElement.querySelector('.method-box');
    checkedBox.style.borderColor = 'var(--primary)';
    checkedBox.style.background = 'var(--primary)';
    checkedBox.style.color = '#fff';
}

function showPaymentLoader() {
    document.getElementById('paymentLoader').style.display = 'flex';
}
</script>
</body>
</html>
