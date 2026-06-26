<?php
// =====================================================
// GoService - Payment Webhook Endpoint (Simulation)
// =====================================================

require_once 'connect.php';

// Disable HTML error rendering for API response
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Read input payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit;
}

$order_code   = $data['order_code'] ?? '';
$payment_type = $data['payment_type'] ?? '';
$amount       = floatval($data['amount'] ?? 0);
$status       = $data['status'] ?? '';
$signature    = $data['signature'] ?? '';

// Verify security signature
$expected_signature = hash('sha256', $order_code . "MOCK_GATEWAY_SECRET");
if ($signature !== $expected_signature) {
    log_error("Webhook Security Warning: Signature mismatch for order code " . $order_code);
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized signature']);
    exit;
}

// Update order status on successful settlement
try {
    $stmt = mysqli_prepare($conn, "SELECT id, status, status_bayar FROM orders WHERE kode_pesanan = ?");
    mysqli_stmt_bind_param($stmt, "s", $order_code);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($order = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);

        if ($status === 'settlement') {
            // Determine enum mapping
            $db_metode = 'transfer';
            if (in_array($payment_type, ['gopay', 'ovo', 'dana'])) {
                $db_metode = 'ewallet';
            } elseif ($payment_type === 'credit_card') {
                // credit card falls under transfer/ewallet schema in DB, we'll map to transfer
                $db_metode = 'transfer';
            }

            // Update order: status_bayar = 'lunas', status = 'dikonfirmasi'
            $stmt_upd = mysqli_prepare($conn, "UPDATE orders SET status_bayar = 'lunas', status = 'dikonfirmasi', metode_bayar = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_upd, "si", $db_metode, $order['id']);
            mysqli_stmt_execute($stmt_upd);
            mysqli_stmt_close($stmt_upd);

            // Log details in transaction table
            $trans_id = 'TRX-' . time() . '-' . rand(1000, 9999);
            $raw_payload = json_encode($data);
            
            $stmt_trans = mysqli_prepare($conn, "INSERT INTO transactions (order_id, transaction_id, gross_amount, payment_type, status, raw_payload) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_trans, "isdsss", $order['id'], $trans_id, $amount, $payment_type, $status, $raw_payload);
            mysqli_stmt_execute($stmt_trans);
            mysqli_stmt_close($stmt_trans);

            log_error("Webhook verified successfully: Order $order_code marked as paid. Transaction ID: $trans_id");
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Payment settled and logged successfully']);
            exit;
        } else {
            http_response_code(200);
            echo json_encode(['status' => 'ignored', 'message' => 'Non-settlement status ignored']);
            exit;
        }
    } else {
        mysqli_stmt_close($stmt);
        log_error("Webhook transaction error: Order code $order_code not found in database.");
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Order code not found']);
        exit;
    }
} catch (Exception $e) {
    log_error("Webhook database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
    exit;
}
?>
