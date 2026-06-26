<?php
// =====================================================
// GoService - Dynamic Category Service Detail Page
// =====================================================

require_once 'connect.php';

$slug = trim($_GET['slug'] ?? '');

if (empty($slug)) {
    redirect("index.php");
}

// Fetch parent service details
$parent_service = null;
try {
    $stmt = mysqli_prepare($conn, "SELECT id, nama, deskripsi, icon FROM services WHERE slug = ? AND parent_id IS NULL AND status = 'aktif'");
    mysqli_stmt_bind_param($stmt, "s", $slug);
    mysqli_stmt_execute($stmt);
    $parent_service = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    log_error("Error loading service category info: " . $e->getMessage());
}

if (!$parent_service) {
    // Category not found, show error
    log_error("Category slug not found: " . $slug);
    die("Kategori layanan tidak ditemukan.");
}

$parent_id = $parent_service['id'];

// Fetch child sub-services for tabs
$sub_services = [];
try {
    $stmt_sub = mysqli_prepare($conn, "SELECT id, nama, slug, harga_dasar FROM services WHERE parent_id = ? AND status = 'aktif' ORDER BY urutan ASC");
    mysqli_stmt_bind_param($stmt_sub, "i", $parent_id);
    mysqli_stmt_execute($stmt_sub);
    $res_sub = mysqli_stmt_get_result($stmt_sub);
    while ($row = mysqli_fetch_assoc($res_sub)) {
        $sub_services[] = $row;
    }
    mysqli_stmt_close($stmt_sub);
} catch (Exception $e) {
    log_error("Error loading sub-services: " . $e->getMessage());
}

// Fetch active providers associated with this parent service or its child sub-services
$providers = [];
try {
    $prov_query = "SELECT p.id, p.nama, p.pengalaman, p.deskripsi, p.rating, p.total_review, p.total_order, s.id AS sub_id, s.nama AS nama_subservice, s.harga_dasar
                   FROM providers p
                   JOIN services s ON p.service_id = s.id
                   WHERE p.status = 'aktif' AND (p.service_id = ? OR s.parent_id = ?)
                   ORDER BY p.rating DESC, p.total_order DESC";
    $stmt_prov = mysqli_prepare($conn, $prov_query);
    mysqli_stmt_bind_param($stmt_prov, "ii", $parent_id, $parent_id);
    mysqli_stmt_execute($stmt_prov);
    $res_prov = mysqli_stmt_get_result($stmt_prov);
    while ($row = mysqli_fetch_assoc($res_prov)) {
        $providers[] = $row;
    }
    mysqli_stmt_close($stmt_prov);
} catch (Exception $e) {
    log_error("Error loading category providers: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan <?= escape($parent_service['nama']) ?> - GoService</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .no-mitra {
            text-align: center;
            padding: 50px 20px;
            background: #fff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            grid-column: 1 / -1;
        }
        .dashboard-link {
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 6px;
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
            <li><a href="index.php#testimonials">Testimoni</a></li>
        </ul>
        <div class="nav-btns">
            <?php if(isset($_SESSION['user_nama'])): ?>
                <?php 
                $dashboard_url = 'customer_dashboard.php';
                if ($_SESSION['user_role'] === 'provider') $dashboard_url = 'provider_dashboard.php';
                elseif ($_SESSION['user_role'] === 'admin') $dashboard_url = 'Admin/index.php';
                ?>
                <a href="<?= $dashboard_url ?>" class="dashboard-link"><i class="fas fa-user-circle"></i> <?= escape($_SESSION['user_nama']) ?></a>
                <a href="auth.php?logout=1" class="btn btn-outline" style="padding:8px 16px;font-size:.85rem">Keluar</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline">Masuk</a>
                <a href="login.php?mode=register" class="btn btn-primary">Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- PAGE HERO -->
<section class="page-hero">
    <div class="container">
        <h1>Layanan <?= escape($parent_service['nama']) ?></h1>
        <p><?= escape($parent_service['deskripsi']) ?></p>
        <div class="breadcrumb">
            <a href="index.php">Beranda</a> / <span><?= escape($parent_service['nama']) ?></span>
        </div>
    </div>
</section>

<!-- CONTENT -->
<section class="section" style="padding-top:40px">
    <div class="container">
        
        <!-- TABS -->
        <?php if(!empty($sub_services)): ?>
            <div class="tabs">
                <button class="tab-btn active" onclick="filterProvider('semua')">Semua Layanan</button>
                <?php foreach($sub_services as $sub): ?>
                    <button class="tab-btn" onclick="filterProvider(<?= $sub['id'] ?>)"><?= escape($sub['nama']) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- PROVIDERS -->
        <div class="provider-list">
            <?php if(empty($providers)): ?>
                <div class="no-mitra">
                    <i class="fas fa-user-slash" style="font-size:3rem; color:var(--text-light); margin-bottom:12px; opacity:0.3;"></i>
                    <h3>Mitra Jasa Belum Tersedia</h3>
                    <p style="color:var(--text-light); margin-top:4px;">Saat ini belum ada Mitra Jasa terverifikasi untuk kategori ini di wilayah Anda.</p>
                </div>
            <?php else: ?>
                <?php foreach($providers as $p): ?>
                    <div class="provider-item" data-category="<?= $p['sub_id'] ?>">
                        <div class="provider-item-img" style="background:#f1f5f9; display:flex; align-items:center; justify-content:center; position:relative;">
                            <img src="images/layanan_<?= escape($slug) ?>.png" alt="<?= escape($p['nama_subservice']) ?>" onerror="this.src='images/layanan_lainnya.png'" style="width:100%; height:100%; object-fit:cover;">
                            <span style="position:absolute; top:12px; left:12px; background:rgba(15,23,42,0.8); backdrop-filter:blur(4px); color:#fff; font-size:0.75rem; padding:4px 8px; border-radius:6px; font-weight:600;">
                                <?= escape($p['nama_subservice']) ?>
                            </span>
                        </div>
                        <div class="provider-item-body">
                            <span class="price-tag">Mulai <?= format_rupiah($p['harga_dasar']) ?></span>
                            <h3><?= escape($p['nama']) ?></h3>
                            <div class="provider-rating">
                                <span class="stars"><i class="fas fa-star"></i> <?= number_format($p['rating'], 1) ?></span>
                                <span class="count">(<?= intval($p['total_review']) ?> ulasan) • Pengalaman <?= intval($p['pengalaman']) ?> thn</span>
                            </div>
                            <p><?= escape($p['deskripsi'] ?: 'Mitra penyedia jasa profesional berdedikasi tinggi.') ?></p>
                            <a href="index.php?booking_service_id=<?= $p['sub_id'] ?>#booking" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-calendar-check"></i> Pesan Sekarang</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</section>

<script>
function filterProvider(subId) {
    // Update active tab styling
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');

    // Filter items
    const items = document.querySelectorAll('.provider-item');
    let visibleCount = 0;
    
    items.forEach(item => {
        if (subId === 'semua' || item.dataset.category == subId) {
            item.style.display = 'block';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
}
</script>

</body>
</html>
