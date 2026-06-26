<?php
// =====================================================
// GoService - Provider Dashboard
// =====================================================

require_once 'connect.php';

// Auth Guard: Providers only
guard_auth(['provider']);

$provider_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle status updates & profile changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'start_job') {
        $order_id = intval($_POST['order_id'] ?? 0);

        // Verify order is assigned to this provider and is confirmed
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM orders WHERE id = ? AND provider_id = ? AND status = 'dikonfirmasi'");
        mysqli_stmt_bind_param($stmt_check, "ii", $order_id, $provider_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            mysqli_stmt_close($stmt_check);

            $stmt_upd = mysqli_prepare($conn, "UPDATE orders SET status = 'dalam_proses' WHERE id = ?");
            mysqli_stmt_bind_param($stmt_upd, "i", $order_id);
            if (mysqli_stmt_execute($stmt_upd)) {
                $success_msg = "Pekerjaan dimulai. Status berhasil diperbarui menjadi 'Dalam Proses'.";
            } else {
                $error_msg = "Gagal mengubah status pekerjaan.";
            }
            mysqli_stmt_close($stmt_upd);
        } else {
            mysqli_stmt_close($stmt_check);
            $error_msg = "Pekerjaan tidak ditemukan atau tidak dapat dimulai.";
        }
    }

    if ($action === 'complete_job') {
        $order_id = intval($_POST['order_id'] ?? 0);

        // Verify order is assigned to this provider and in progress
        $stmt_check = mysqli_prepare($conn, "SELECT id, total_harga, metode_bayar FROM orders WHERE id = ? AND provider_id = ? AND status = 'dalam_proses'");
        mysqli_stmt_bind_param($stmt_check, "ii", $order_id, $provider_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_bind_result($stmt_check, $oid, $total_harga, $metode_bayar);
        
        if (mysqli_stmt_fetch($stmt_check)) {
            mysqli_stmt_close($stmt_check);

            mysqli_begin_transaction($conn);
            try {
                // If payment method is tunai (COD), mark status_bayar as lunas when completing job
                $query_update = "UPDATE orders SET status = 'selesai'";
                if ($metode_bayar === 'tunai') {
                    $query_update .= ", status_bayar = 'lunas'";
                }
                $query_update .= " WHERE id = ?";

                $stmt_upd = mysqli_prepare($conn, $query_update);
                mysqli_stmt_bind_param($stmt_upd, "i", $order_id);
                mysqli_stmt_execute($stmt_upd);
                mysqli_stmt_close($stmt_upd);

                // Increment total_order in providers
                $stmt_provider = mysqli_prepare($conn, "UPDATE providers SET total_order = total_order + 1 WHERE id = ?");
                mysqli_stmt_bind_param($stmt_provider, "i", $provider_id);
                mysqli_stmt_execute($stmt_provider);
                mysqli_stmt_close($stmt_provider);

                mysqli_commit($conn);
                $success_msg = "Pekerjaan diselesaikan! Terima kasih atas dedikasi Anda.";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                log_error("Failed to complete job: " . $e->getMessage());
                $error_msg = "Terjadi kesalahan database saat merampungkan pekerjaan.";
            }
        } else {
            mysqli_stmt_close($stmt_check);
            $error_msg = "Pekerjaan tidak ditemukan atau belum dimulai.";
        }
    }

    if ($action === 'update_profile') {
        $nama       = trim($_POST['nama'] ?? '');
        $telepon    = trim($_POST['telepon'] ?? '');
        $alamat     = trim($_POST['alamat'] ?? '');
        $pengalaman = intval($_POST['pengalaman'] ?? 0);
        $deskripsi  = trim($_POST['deskripsi'] ?? '');

        if (empty($nama) || empty($telepon)) {
            $error_msg = "Nama dan Telepon wajib diisi.";
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE providers SET nama = ?, telepon = ?, alamat = ?, pengalaman = ?, deskripsi = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssiis", $nama, $telepon, $alamat, $pengalaman, $deskripsi, $provider_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['user_nama'] = $nama; // Update session value
                $success_msg = "Profil Mitra berhasil diperbarui.";
            } else {
                $error_msg = "Gagal memperbarui profil di database.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch provider profile & ratings details
$stmt_profile = mysqli_prepare($conn, "SELECT p.nama, p.email, p.telepon, p.alamat, p.pengalaman, p.deskripsi, p.rating, p.total_review, p.total_order, s.nama AS nama_layanan 
                                       FROM providers p
                                       JOIN services s ON p.service_id = s.id
                                       WHERE p.id = ?");
mysqli_stmt_bind_param($stmt_profile, "i", $provider_id);
mysqli_stmt_execute($stmt_profile);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_profile));
mysqli_stmt_close($stmt_profile);

// Calculate total earnings (sum of completed orders where user paid)
$total_earnings = 0.00;
$stmt_earn = mysqli_prepare($conn, "SELECT SUM(total_harga) FROM orders WHERE provider_id = ? AND status = 'selesai' AND status_bayar = 'lunas'");
mysqli_stmt_bind_param($stmt_earn, "i", $provider_id);
mysqli_stmt_execute($stmt_earn);
mysqli_stmt_bind_result($stmt_earn, $sum_earn);
if (mysqli_stmt_fetch($stmt_earn)) {
    $total_earnings = floatval($sum_earn ?? 0);
}
mysqli_stmt_close($stmt_earn);

// Fetch assigned active orders list
$jobs = [];
$jobs_query = "SELECT o.id, o.kode_pesanan, o.tanggal_layanan, o.waktu_layanan, o.alamat_layanan, o.deskripsi, o.total_harga, o.status, o.status_bayar, o.metode_bayar,
                      u.nama AS nama_pelanggan, u.telepon AS telp_pelanggan
               FROM orders o
               JOIN users u ON o.user_id = u.id
               WHERE o.provider_id = ? AND o.status IN ('dikonfirmasi', 'dalam_proses')
               ORDER BY o.tanggal_layanan ASC, o.waktu_layanan ASC";
$stmt_jobs = mysqli_prepare($conn, $jobs_query);
mysqli_stmt_bind_param($stmt_jobs, "i", $provider_id);
mysqli_stmt_execute($stmt_jobs);
$jobs_res = mysqli_stmt_get_result($stmt_jobs);
while ($row = mysqli_fetch_assoc($jobs_res)) {
    $jobs[] = $row;
}
mysqli_stmt_close($stmt_jobs);

// Fetch recent customer reviews
$reviews = [];
$rev_query = "SELECT r.rating, r.komentar, r.created_at, u.nama AS nama_pelanggan 
              FROM reviews r
              JOIN users u ON r.user_id = u.id
              WHERE r.provider_id = ?
              ORDER BY r.created_at DESC LIMIT 5";
$stmt_rev = mysqli_prepare($conn, $rev_query);
mysqli_stmt_bind_param($stmt_rev, "i", $provider_id);
mysqli_stmt_execute($stmt_rev);
$rev_res = mysqli_stmt_get_result($stmt_rev);
while ($row = mysqli_fetch_assoc($rev_res)) {
    $reviews[] = $row;
}
mysqli_stmt_close($stmt_rev);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mitra - GoService</title>
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
        .stats-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 10px;
        }
        @media(max-width: 768px) {
            .stats-grid-4 {
                grid-template-columns: 1fr 1fr;
            }
        }
        .stat-card {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
        .sci-blue { background: #eff6ff; color: #2563eb; }
        .sci-green { background: #ecfdf5; color: #059669; }
        .sci-orange { background: #fffbeb; color: #d97706; }
        .sci-purple { background: #faf5ff; color: #7c3aed; }
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

        .badge-paid { background: #dcfce3; color: #059669; }
        .badge-unpaid { background: #fee2e2; color: #dc2626; }
        
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
        .review-item {
            border-bottom: 1px solid var(--border);
            padding: 18px 0;
        }
        .review-item:last-child {
            border-bottom: none;
        }
        .review-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .review-stars {
            color: #fbbf24;
            font-size: 0.85rem;
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
            <span style="font-weight:600;color:var(--primary)"><i class="fas fa-user-circle"></i> <?= escape($profile['nama']) ?> (Mitra Jasa)</span>
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
                <span class="badge badge-dikonfirmasi" style="margin-top:4px;"><?= escape($profile['nama_layanan']) ?></span>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="#" class="active" id="menu-jobs" onclick="showTab('jobs')"><i class="fas fa-briefcase"></i> Pekerjaan Aktif</a></li>
                <li><a href="#" id="menu-reviews" onclick="showTab('reviews')"><i class="fas fa-star"></i> Ulasan Konsumen</a></li>
                <li><a href="#" id="menu-profile" onclick="showTab('profile')"><i class="fas fa-user-edit"></i> Edit Profil Mitra</a></li>
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

            <!-- STATS COUNTERS -->
            <section class="stats-grid-4">
                <div class="stat-card">
                    <div class="stat-card-icon sci-orange"><i class="fas fa-star"></i></div>
                    <div>
                        <h4 style="color:var(--text-light); font-size:0.8rem; font-weight:500;">Rating Jasa</h4>
                        <strong style="font-size:1.4rem;"><?= number_format($profile['rating'], 1) ?> ★</strong>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon sci-blue"><i class="fas fa-comments"></i></div>
                    <div>
                        <h4 style="color:var(--text-light); font-size:0.8rem; font-weight:500;">Total Ulasan</h4>
                        <strong style="font-size:1.4rem;"><?= intval($profile['total_review']) ?></strong>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon sci-green"><i class="fas fa-wallet"></i></div>
                    <div>
                        <h4 style="color:var(--text-light); font-size:0.8rem; font-weight:500;">Pendapatan</h4>
                        <strong style="font-size:1.15rem;"><?= format_rupiah($total_earnings) ?></strong>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon sci-purple"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <h4 style="color:var(--text-light); font-size:0.8rem; font-weight:500;">Kerja Selesai</h4>
                        <strong style="font-size:1.4rem;"><?= intval($profile['total_order']) ?></strong>
                    </div>
                </div>
            </section>

            <!-- TAB: JOBS -->
            <section id="tab-content-jobs" class="content-card tab-content">
                <h2>Pekerjaan yang Harus Dikerjakan</h2>
                <p style="color:var(--text-light); font-size:0.9rem; margin-bottom:20px;">Daftar pekerjaan aktif yang ditugaskan kepada Anda</p>

                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Pelanggan</th>
                                <th>Alamat & Telepon</th>
                                <th>Tanggal & Waktu</th>
                                <th>Masalah / Detail</th>
                                <th>Metode Bayar</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($jobs)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:40px; color:var(--text-light)">
                                        <i class="fas fa-clipboard-list" style="font-size:3rem; margin-bottom:12px; display:block; opacity:0.3"></i>
                                        Tidak ada pekerjaan aktif saat ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($jobs as $job): ?>
                                    <tr>
                                        <td><strong><?= escape($job['kode_pesanan']) ?></strong></td>
                                        <td><?= escape($job['nama_pelanggan']) ?></td>
                                        <td>
                                            <?= escape($job['alamat_layanan']) ?><br>
                                            <small style="color:var(--primary); font-weight:500;"><i class="fas fa-phone"></i> <?= escape($job['telp_pelanggan']) ?></small>
                                        </td>
                                        <td>
                                            <?= format_date($job['tanggal_layanan']) ?><br>
                                            <small style="color:var(--text-light)"><i class="far fa-clock"></i> <?= escape(substr($job['waktu_layanan'], 0, 5)) ?></small>
                                        </td>
                                        <td>
                                            <small style="display:block; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= escape($job['deskripsi']) ?>">
                                                <?= escape($job['deskripsi'] ?: '-') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong style="font-size:0.85rem; text-transform:uppercase;"><?= escape($job['metode_bayar']) ?></strong><br>
                                            <?php if($job['status_bayar'] === 'lunas'): ?>
                                                <span class="badge badge-paid" style="font-size:0.7rem; padding:2px 6px;">Lunas</span>
                                            <?php else: ?>
                                                <span class="badge badge-unpaid" style="font-size:0.7rem; padding:2px 6px;">COD/Belum</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($job['status'] === 'dikonfirmasi'): ?>
                                                <span class="badge badge-dikonfirmasi">Ditugaskan</span>
                                            <?php else: ?>
                                                <span class="badge badge-proses">Dikerjakan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($job['status'] === 'dikonfirmasi'): ?>
                                                <form action="provider_dashboard.php" method="POST" onsubmit="return confirm('Mulai kerjakan order ini?')">
                                                    <input type="hidden" name="action" value="start_job">
                                                    <input type="hidden" name="order_id" value="<?= $job['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    <button type="submit" class="btn btn-primary" style="padding:6px 12px; font-size:0.8rem;"><i class="fas fa-play"></i> Kerjakan</button>
                                                </form>
                                            <?php elseif($job['status'] === 'dalam_proses'): ?>
                                                <form action="provider_dashboard.php" method="POST" onsubmit="return confirm('Konfirmasi pekerjaan telah selesai dilakukan?')">
                                                    <input type="hidden" name="action" value="complete_job">
                                                    <input type="hidden" name="order_id" value="<?= $job['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    <button type="submit" class="btn" style="padding:6px 12px; font-size:0.8rem; background:#059669; color:#fff; border:none;"><i class="fas fa-check"></i> Selesai</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- TAB: REVIEWS -->
            <section id="tab-content-reviews" class="content-card tab-content" style="display:none">
                <h2>Ulasan Konsumen Terakhir</h2>
                <p style="color:var(--text-light); font-size:0.9rem; margin-bottom:20px;">Feedback nyata dari konsumen yang pernah menggunakan jasa Anda</p>
                
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <?php if(empty($reviews)): ?>
                        <div style="text-align:center; padding:40px; color:var(--text-light)">
                            <i class="far fa-star-half" style="font-size:3rem; margin-bottom:12px; display:block; opacity:0.3"></i>
                            Belum ada ulasan dari konsumen Anda.
                        </div>
                    <?php else: ?>
                        <?php foreach($reviews as $rev): ?>
                            <div class="review-item">
                                <div class="review-meta">
                                    <strong><?= escape($rev['nama_pelanggan']) ?></strong>
                                    <span style="color:var(--text-light); font-size:0.8rem;"><?= format_date($rev['created_at']) ?></span>
                                </div>
                                <div class="review-stars">
                                    <?php 
                                    $stars = intval($rev['rating']);
                                    for ($i = 0; $i < 5; $i++) {
                                        echo ($i < $stars) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                </div>
                                <p style="margin-top:8px; color:var(--text-light); font-style:italic; font-size:0.9rem;">
                                    "<?= escape($rev['komentar']) ?>"
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- TAB: EDIT PROFILE -->
            <section id="tab-content-profile" class="content-card tab-content" style="display:none">
                <h2>Profil Penyedia Jasa</h2>
                <p style="color:var(--text-light); margin-bottom:24px;">Kelola informasi profil profesional Anda</p>
                
                <form action="provider_dashboard.php" method="POST" style="max-width: 600px;">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="form-group">
                        <label>Nama Lengkap / Nama Bisnis</label>
                        <input type="text" name="nama" class="form-input" value="<?= escape($profile['nama']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Layanan Utama (Tidak dapat diubah)</label>
                        <input type="text" class="form-input" value="<?= escape($profile['nama_layanan']) ?>" readonly style="background:#f1f5f9; color:var(--text-light)">
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
                        <label>Pengalaman Kerja (Tahun)</label>
                        <input type="number" name="pengalaman" class="form-input" value="<?= intval($profile['pengalaman']) ?>" required min="0">
                    </div>

                    <div class="form-group">
                        <label>Alamat Operasional Jasa</label>
                        <textarea name="alamat" class="form-input" rows="2" required><?= escape($profile['alamat']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi Keahlian & Layanan Anda</label>
                        <textarea name="deskripsi" class="form-input" rows="4"><?= escape($profile['deskripsi']) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:10px;"><i class="fas fa-save"></i> Simpan Profil</button>
                </form>
            </section>
        </main>
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
</script>
</body>
</html>
