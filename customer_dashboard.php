<?php
// =====================================================
// GoService - Customer Dashboard
// =====================================================

require_once 'connect.php';

// Auth Guard: Customers only
guard_auth(['customer']);

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle AJAX/POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $nama    = trim($_POST['nama'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');
        $alamat  = trim($_POST['alamat'] ?? '');

        if (empty($nama) || empty($telepon)) {
            $error_msg = "Nama dan Telepon wajib diisi.";
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET nama = ?, telepon = ?, alamat = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $nama, $telepon, $alamat, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['user_nama'] = $nama; // Update session
                $success_msg = "Profil berhasil diperbarui.";
            } else {
                $error_msg = "Gagal memperbarui profil di database.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($action === 'submit_review') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $rating   = intval($_POST['rating'] ?? 5);
        $komentar = trim($_POST['komentar'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $error_msg = "Rating tidak valid (harus 1 - 5).";
        } else {
            // Retrieve provider_id from order and verify ownership
            $stmt_verify = mysqli_prepare($conn, "SELECT provider_id FROM orders WHERE id = ? AND user_id = ? AND status = 'selesai'");
            mysqli_stmt_bind_param($stmt_verify, "ii", $order_id, $user_id);
            mysqli_stmt_execute($stmt_verify);
            mysqli_stmt_bind_result($stmt_verify, $provider_id);
            if (mysqli_stmt_fetch($stmt_verify)) {
                mysqli_stmt_close($stmt_verify);

                if (empty($provider_id)) {
                    $error_msg = "Tidak dapat memberi ulasan karena Mitra belum ditentukan.";
                } else {
                    // Try inserting the review
                    $stmt_ins = mysqli_prepare($conn, "INSERT INTO reviews (order_id, user_id, provider_id, rating, komentar) VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt_ins, "iiiis", $order_id, $user_id, $provider_id, $rating, $komentar);
                    
                    if (mysqli_stmt_execute($stmt_ins)) {
                        mysqli_stmt_close($stmt_ins);
                        
                        // Recalculate provider average rating and count
                        $stmt_calc = mysqli_prepare($conn, "SELECT AVG(rating), COUNT(id) FROM reviews WHERE provider_id = ?");
                        mysqli_stmt_bind_param($stmt_calc, "i", $provider_id);
                        mysqli_stmt_execute($stmt_calc);
                        mysqli_stmt_bind_result($stmt_calc, $avg_rating, $total_review);
                        mysqli_stmt_fetch($stmt_calc);
                        mysqli_stmt_close($stmt_calc);

                        // Update provider table
                        $stmt_upd = mysqli_prepare($conn, "UPDATE providers SET rating = ?, total_review = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt_upd, "dii", $avg_rating, $total_review, $provider_id);
                        mysqli_stmt_execute($stmt_upd);
                        mysqli_stmt_close($stmt_upd);

                        $success_msg = "Terima kasih! Ulasan Anda berhasil dikirim.";
                    } else {
                        $error_msg = "Anda sudah mengirimkan ulasan untuk pesanan ini.";
                    }
                }
            } else {
                mysqli_stmt_close($stmt_verify);
                $error_msg = "Pesanan tidak ditemukan atau belum selesai.";
            }
        }
    }

    if ($action === 'cancel_order') {
        $order_id = intval($_POST['order_id'] ?? 0);

        // Verify ownership and status is pending/dikonfirmasi
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM orders WHERE id = ? AND user_id = ? AND status IN ('pending', 'dikonfirmasi')");
        mysqli_stmt_bind_param($stmt_check, "ii", $order_id, $user_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            mysqli_stmt_close($stmt_check);

            $stmt_upd = mysqli_prepare($conn, "UPDATE orders SET status = 'dibatalkan' WHERE id = ?");
            mysqli_stmt_bind_param($stmt_upd, "i", $order_id);
            if (mysqli_stmt_execute($stmt_upd)) {
                $success_msg = "Pesanan berhasil dibatalkan.";
            } else {
                $error_msg = "Gagal membatalkan pesanan.";
            }
            mysqli_stmt_close($stmt_upd);
        } else {
            mysqli_stmt_close($stmt_check);
            $error_msg = "Pesanan tidak dapat dibatalkan atau bukan milik Anda.";
        }
    }
}

// Fetch customer profile
$stmt_profile = mysqli_prepare($conn, "SELECT nama, email, telepon, alamat FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_profile, "i", $user_id);
mysqli_stmt_execute($stmt_profile);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_profile));
mysqli_stmt_close($stmt_profile);

// Fetch bookings list
$orders = [];
$orders_query = "SELECT o.id, o.kode_pesanan, o.tanggal_layanan, o.waktu_layanan, o.total_harga, o.status, o.status_bayar, o.metode_bayar, 
                        s.nama AS nama_layanan, p.nama AS nama_mitra, r.id AS review_id
                 FROM orders o
                 JOIN services s ON o.service_id = s.id
                 LEFT JOIN providers p ON o.provider_id = p.id
                 LEFT JOIN reviews r ON o.id = r.order_id
                 WHERE o.user_id = ?
                 ORDER BY o.created_at DESC";
$stmt_orders = mysqli_prepare($conn, $orders_query);
mysqli_stmt_bind_param($stmt_orders, "i", $user_id);
mysqli_stmt_execute($stmt_orders);
$orders_res = mysqli_stmt_get_result($stmt_orders);
while ($row = mysqli_fetch_assoc($orders_res)) {
    $orders[] = $row;
}
mysqli_stmt_close($stmt_orders);

// Handle success codes from redirects
if (isset($_GET['booking_success'])) {
    $success_msg = "Pemesanan berhasil dibuat dengan kode: <strong>" . escape($_GET['booking_success']) . "</strong>. Silakan selesaikan pembayaran.";
}
if (isset($_GET['payment'])) {
    if ($_GET['payment'] === 'success') {
        $success_msg = "Pembayaran sukses diverifikasi! Pesanan Anda telah diperbarui.";
    } elseif ($_GET['payment'] === 'failed') {
        $error_msg = "Pembayaran gagal atau dibatalkan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pelanggan - GoService</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            margin-top: 40px;
            margin-bottom: 60px;
        }
        @media(max-width: 992px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
        }
        .sidebar-card {
            background: #fff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            padding: 30px;
            height: fit-content;
        }
        .sidebar-menu {
            list-style: none;
            margin-top: 24px;
        }
        .sidebar-menu li {
            margin-bottom: 12px;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 8px;
            color: var(--text-light);
            font-weight: 500;
            transition: all 0.2s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: var(--bg);
            color: var(--primary);
        }
        .dashboard-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        .content-card {
            background: #fff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            padding: 32px;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 50px;
            text-align: center;
        }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-dikonfirmasi { background: #dbeafe; color: #2563eb; }
        .badge-proses { background: #e0f2fe; color: #0284c7; }
        .badge-selesai { background: #dcfce3; color: #059669; }
        .badge-batal { background: #fee2e2; color: #dc2626; }

        .badge-paid { background: #dcfce3; color: #059669; border: 1px solid #a7f3d0; }
        .badge-unpaid { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }

        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #fff;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 500px;
            padding: 32px;
            box-shadow: var(--shadow-lg);
            position: relative;
        }
        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.4rem;
            color: var(--text-light);
            cursor: pointer;
        }
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            gap: 8px;
            margin: 20px 0;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 2.2rem;
            color: #cbd5e1;
            cursor: pointer;
            transition: color 0.15s;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #fbbf24;
        }
        .alert-bar {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
        }
        .alert-bar-success { background: #dcfce3; color: #047857; border: 1px solid #a7f3d0; }
        .alert-bar-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table-custom th, .table-custom td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }
        .table-custom th {
            font-weight: 600;
            color: var(--text);
            background: var(--bg);
        }
        .avatar-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 20px;
            text-align: center;
        }
        .avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            box-shadow: 0 4px 10px rgba(37,99,235,0.2);
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<header class="navbar">
    <div class="container">
        <a href="index.php" class="nav-brand"><i class="fas fa-bolt"></i> GoService</a>
        <ul class="nav-links">
            <li><a href="index.php#services">Layanan</a></li>
            <li><a href="index.php#how-it-works">Cara Kerja</a></li>
        </ul>
        <div class="nav-btns">
            <span style="font-weight:600;color:var(--primary)"><i class="fas fa-user-circle"></i> <?= escape($profile['nama']) ?> (Pelanggan)</span>
            <a href="auth.php?logout=1" class="btn btn-outline" style="padding:8px 16px;font-size:.85rem">Keluar</a>
        </div>
    </div>
</header>

<div class="container">
    
    <div class="dashboard-layout">
        
        <!-- SIDEBAR -->
        <aside class="sidebar-card">
            <div class="avatar-section">
                <div class="avatar-large">
                    <?= strtoupper(substr($profile['nama'], 0, 1)) ?>
                </div>
                <h3><?= escape($profile['nama']) ?></h3>
                <p style="color:var(--text-light); font-size:0.85rem; margin-top:4px;"><?= escape($profile['email']) ?></p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="#" class="active" id="menu-bookings" onclick="showTab('bookings')"><i class="fas fa-calendar-alt"></i> Pesanan Saya</a></li>
                <li><a href="#" id="menu-profile" onclick="showTab('profile')"><i class="fas fa-user-edit"></i> Edit Profil</a></li>
            </ul>
        </aside>

        <!-- MAIN PANEL -->
        <main class="dashboard-content">
            
            <?php if(!empty($success_msg)): ?>
                <div class="alert-bar alert-bar-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?= $success_msg ?></div>
                </div>
            <?php endif; ?>

            <?php if(!empty($error_msg)): ?>
                <div class="alert-bar alert-bar-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?= $error_msg ?></div>
                </div>
            <?php endif; ?>

            <!-- TAB: BOOKINGS -->
            <section id="tab-content-bookings" class="content-card tab-content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h2>Riwayat Pemesanan</h2>
                        <p style="color:var(--text-light); font-size:0.9rem;">Kelola pemesanan layanan home service Anda</p>
                    </div>
                    <a href="index.php#booking" class="btn btn-primary"><i class="fas fa-plus"></i> Pesan Baru</a>
                </div>

                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Layanan</th>
                                <th>Mitra</th>
                                <th>Tanggal & Waktu</th>
                                <th>Total Harga</th>
                                <th>Pembayaran</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($orders)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:40px; color:var(--text-light)">
                                        <i class="fas fa-calendar-times" style="font-size:3rem; margin-bottom:12px; display:block; opacity:0.3"></i>
                                        Belum ada riwayat pesanan. <a href="index.php#booking" style="color:var(--primary); font-weight:600">Pesan sekarang!</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($orders as $order): ?>
                                    <tr>
                                        <td><strong><?= escape($order['kode_pesanan']) ?></strong></td>
                                        <td><?= escape($order['nama_layanan']) ?></td>
                                        <td><?= escape($order['nama_mitra'] ?? 'Mencari Mitra...') ?></td>
                                        <td>
                                            <?= format_date($order['tanggal_layanan']) ?><br>
                                            <small style="color:var(--text-light)"><i class="far fa-clock"></i> <?= escape(substr($order['waktu_layanan'], 0, 5)) ?></small>
                                        </td>
                                        <td><strong><?= format_rupiah($order['total_harga']) ?></strong></td>
                                        <td>
                                            <?php if($order['status_bayar'] === 'lunas'): ?>
                                                <span class="badge badge-paid">Lunas</span>
                                            <?php else: ?>
                                                <span class="badge badge-unpaid">Belum Bayar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $st = $order['status'];
                                            if($st === 'pending') echo '<span class="badge badge-pending">Menunggu</span>';
                                            elseif($st === 'dikonfirmasi') echo '<span class="badge badge-dikonfirmasi">Dikonfirmasi</span>';
                                            elseif($st === 'dalam_proses') echo '<span class="badge badge-proses">Dalam Proses</span>';
                                            elseif($st === 'selesai') echo '<span class="badge badge-selesai">Selesai</span>';
                                            elseif($st === 'dibatalkan') echo '<span class="badge badge-batal">Batal</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:6px;">
                                                <?php if($order['status_bayar'] === 'belum' && in_array($order['status'], ['pending', 'dikonfirmasi'])): ?>
                                                    <a href="checkout.php?kode=<?= $order['kode_pesanan'] ?>" class="btn btn-primary" style="padding:6px 12px; font-size:0.8rem;"><i class="fas fa-wallet"></i> Bayar</a>
                                                    
                                                    <form action="customer_dashboard.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')">
                                                        <input type="hidden" name="action" value="cancel_order">
                                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <button type="submit" class="btn btn-outline" style="padding:6px 12px; font-size:0.8rem; border-color:#ef4444; color:#ef4444; background:transparent;"><i class="fas fa-times"></i> Batal</button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if($order['status'] === 'selesai'): ?>
                                                    <?php if(empty($order['review_id'])): ?>
                                                        <button type="button" class="btn btn-primary" style="padding:6px 12px; font-size:0.8rem; background:#fbbf24; border:none; color:#0f172a;" onclick="openReviewModal(<?= $order['id'] ?>, '<?= escape($order['nama_mitra']) ?>')"><i class="fas fa-star"></i> Ulas</button>
                                                    <?php else: ?>
                                                        <span style="font-size:0.8rem; color:#059669; font-weight:600;"><i class="fas fa-check-double"></i> Diulas</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- TAB: EDIT PROFILE -->
            <section id="tab-content-profile" class="content-card tab-content" style="display:none">
                <h2>Pengaturan Profil</h2>
                <p style="color:var(--text-light); margin-bottom:24px;">Perbarui data personal Anda untuk kemudahan komunikasi</p>
                
                <form action="customer_dashboard.php" method="POST" style="max-width: 600px;">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama" class="form-input" value="<?= escape($profile['nama']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email (Tidak dapat diubah)</label>
                        <input type="email" class="form-input" value="<?= escape($profile['email']) ?>" readonly style="background:#f1f5f9; color:var(--text-light)">
                    </div>

                    <div class="form-group">
                        <label>Nomor Telepon</label>
                        <input type="tel" name="telepon" class="form-input" value="<?= escape($profile['telepon']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <textarea name="alamat" class="form-input" rows="3" required><?= escape($profile['alamat']) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:10px;"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </form>
            </section>
        </main>
    </div>
</div>

<!-- REVIEW MODAL -->
<div class="modal" id="reviewModal">
    <div class="modal-content">
        <button class="close-modal" onclick="closeReviewModal()">&times;</button>
        <h3 style="margin-bottom:6px;">Berikan Ulasan Jasa</h3>
        <p style="color:var(--text-light); font-size:0.9rem;">Beri rating untuk Mitra: <strong id="modalMitraName">Mitra</strong></p>
        
        <form action="customer_dashboard.php" method="POST" id="reviewForm" onsubmit="return validateReviewForm()">
            <input type="hidden" name="action" value="submit_review">
            <input type="hidden" name="order_id" id="modalOrderId" value="">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="star-rating">
                <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="Sangat Baik"><i class="fas fa-star"></i></label>
                <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="Baik"><i class="fas fa-star"></i></label>
                <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="Cukup"><i class="fas fa-star"></i></label>
                <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="Buruk"><i class="fas fa-star"></i></label>
                <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="Sangat Buruk"><i class="fas fa-star"></i></label>
            </div>

            <div class="form-group">
                <label>Komentar / Masukan</label>
                <textarea name="komentar" class="form-input" rows="3" placeholder="Ceritakan pengalaman Anda menggunakan jasa mitra ini..." required></textarea>
            </div>

            <button type="submit" class="btn-submit" style="margin-top:10px; background:linear-gradient(135deg, #fbbf24, #d97706); color:#fff;"><i class="fas fa-paper-plane"></i> Kirim Ulasan</button>
        </form>
    </div>
</div>

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="footer-bottom" style="margin-top:0; border:none; padding-top:0;">© 2025 GoService. All rights reserved.</div>
    </div>
</footer>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.sidebar-menu a').forEach(el => el.classList.remove('active'));
    
    // Show select tab
    document.getElementById('tab-content-' + tabName).style.display = 'block';
    document.getElementById('menu-' + tabName).classList.add('active');
}

function openReviewModal(orderId, providerName) {
    document.getElementById('modalOrderId').value = orderId;
    document.getElementById('modalMitraName').textContent = providerName;
    document.getElementById('reviewModal').classList.add('active');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('active');
}

function validateReviewForm() {
    const ratings = document.getElementsByName('rating');
    let ratingSelected = false;
    for (let i = 0; i < ratings.length; i++) {
        if (ratings[i].checked) {
            ratingSelected = true;
            break;
        }
    }
    if (!ratingSelected) {
        alert('Mohon pilih rating bintang Anda.');
        return false;
    }
    return true;
}

// Keep menu status if page refreshed with get parameters
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('tab') === 'profile') {
    showTab('profile');
}
</script>
</body>
</html>
