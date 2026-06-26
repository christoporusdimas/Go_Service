<?php
require_once 'connect.php';

// Fetch active services (categories) for home view
$services = [];
try {
    $res_s = mysqli_query($conn, "SELECT id, nama, slug, deskripsi, icon FROM services WHERE parent_id IS NULL AND status = 'aktif' ORDER BY urutan ASC");
    while ($row = mysqli_fetch_assoc($res_s)) {
        $services[] = $row;
    }
} catch (Exception $e) {
    log_error("Error loading services for index.php: " . $e->getMessage());
}

// Fetch sub-services for booking form list
$booking_services = [];
try {
    $res_bs = mysqli_query($conn, "SELECT id, nama FROM services WHERE parent_id IS NOT NULL AND status = 'aktif' ORDER BY parent_id ASC, urutan ASC");
    while ($row = mysqli_fetch_assoc($res_bs)) {
        $booking_services[] = $row;
    }
} catch (Exception $e) {
    log_error("Error loading subservices for booking: " . $e->getMessage());
}

// Check if user info should be loaded to pre-fill the form
$user_info = null;
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'customer') {
    try {
        $stmt_u = mysqli_prepare($conn, "SELECT nama, telepon, alamat FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt_u, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt_u);
        $user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u));
        mysqli_stmt_close($stmt_u);
    } catch (Exception $e) {
        log_error("Error retrieving user info for booking: " . $e->getMessage());
    }
}

// Pre-selected service ID from query parameter
$preselected_id = intval($_GET['booking_service_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GoService - Layanan Home Service Profesional</title>
<meta name="description" content="GoService - Platform booking layanan jasa profesional untuk kebersihan, perbaikan, pembangunan, desain, dan servis kendaraan.">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<!-- NAVBAR -->
<header class="navbar">
<div class="container">
<a href="index.php" class="nav-brand"><i class="fas fa-bolt"></i> GoService</a>
<ul class="nav-links">
<li><a href="#services">Layanan</a></li>
<li><a href="#how-it-works">Cara Kerja</a></li>
<li><a href="#testimonials">Testimoni</a></li>
</ul>
<div class="nav-btns">
<?php if(isset($_SESSION['user_nama'])): ?>
    <?php
    $dashboard_url = 'customer_dashboard.php';
    if($_SESSION['user_role'] === 'provider') $dashboard_url = 'provider_dashboard.php';
    elseif($_SESSION['user_role'] === 'admin') $dashboard_url = 'Admin/index.php';
    ?>
    <a href="<?= $dashboard_url ?>" class="btn btn-outline" style="border-color:transparent;"><i class="fas fa-user-circle"></i> <?= escape($_SESSION['user_nama']) ?></a>
    <a href="auth.php?logout=1" class="btn btn-outline" style="padding:8px 16px;font-size:.85rem">Keluar</a>
<?php else: ?>
    <a href="login.php" class="btn btn-outline">Masuk</a>
    <a href="login.php?mode=register" class="btn btn-primary">Daftar</a>
<?php endif; ?>
</div>
<button class="nav-toggle" onclick="document.querySelector('.nav-links').style.display=this.dataset.open?'none':'flex';this.dataset.open=!this.dataset.open"><i class="fas fa-bars"></i></button>
</div>
</header>

<!-- HERO -->
<section class="hero">
<div class="container">
<div class="hero-content">
<h1>Layanan Home Service <span>Profesional</span> untuk Kebutuhan Anda</h1>
<p>Temukan tukang terbaik untuk kebersihan, perbaikan, pembangunan, desain, atau servis kendaraan langsung di rumah Anda.</p>
<div style="display:flex;gap:12px;flex-wrap:wrap">
<a href="#booking" class="btn btn-white btn-lg"><i class="fas fa-calendar-check"></i> Pesan Sekarang</a>
<a href="login.php?role=provider&mode=register" class="btn btn-lg" style="border:2px solid rgba(255,255,255,.4);color:#fff">Jadi Mitra <i class="fas fa-arrow-right"></i></a>
</div>
<div class="hero-stats">
<div class="hero-stat"><div class="num">500+</div><div class="label">Mitra Aktif</div></div>
<div class="hero-stat"><div class="num">10,000+</div><div class="label">Pelanggan</div></div>
<div class="hero-stat"><div class="num">4.8★</div><div class="label">Rating</div></div>
</div>
</div>
<div class="hero-img">
<img src="images/layanan_pembangunan.png" alt="GoService Home Service" onerror="this.src='images/layanan_lainnya.png'">
</div>
</div>
</section>

<!-- SERVICES -->
<section id="services" class="section section-alt">
<div class="container">
<div class="section-header">
<h2>Layanan Kami</h2>
<p>Berbagai solusi profesional untuk kebutuhan rumah dan kendaraan Anda</p>
</div>
<div class="services-grid">

<?php if(empty($services)): ?>
    <div style="text-align:center; grid-column:1/-1; padding:40px; color:var(--text-light)">
        Belum ada kategori layanan aktif.
    </div>
<?php else: ?>
    <?php foreach($services as $serv): ?>
        <a href="layanan.php?slug=<?= escape($serv['slug']) ?>" class="service-card">
            <div class="service-card-img">
                <img src="images/layanan_<?= escape($serv['slug']) ?>.png" alt="<?= escape($serv['nama']) ?>" onerror="this.src='images/layanan_lainnya.png'">
            </div>
            <div class="service-card-body">
                <div class="service-card-icon"><i class="fas <?= escape($serv['icon']) ?>"></i></div>
                <h3><?= escape($serv['nama']) ?></h3>
                <p><?= escape($serv['deskripsi']) ?></p>
                <span class="link">Lihat Detail <i class="fas fa-arrow-right"></i></span>
            </div>
        </a>
    <?php endforeach; ?>
<?php endif; ?>

</div>
</div>
</section>

<!-- HOW IT WORKS -->
<section id="how-it-works" class="section">
<div class="container">
<div class="section-header">
<h2>Cara Memesan Layanan</h2>
<p>Hanya dalam 4 langkah mudah, dapatkan layanan profesional di rumah Anda</p>
</div>
<div class="steps-grid">
<div class="step"><div class="step-num">1</div><h3>Pilih Layanan</h3><p>Temukan layanan yang Anda butuhkan dari berbagai kategori</p></div>
<div class="step"><div class="step-num">2</div><h3>Buat Pesanan</h3><p>Isi detail pesanan termasuk lokasi, tanggal, dan waktu</p></div>
<div class="step"><div class="step-num">3</div><h3>Pilih Penyedia</h3><p>Penyedia terbaik ditugaskan oleh tim ahli kami</p></div>
<div class="step"><div class="step-num">4</div><h3>Tukang Datang</h3><p>Tukang profesional datang sesuai jadwal yang ditentukan</p></div>
</div>
</div>
</section>

<!-- TESTIMONIALS -->
<section id="testimonials" class="section section-alt">
<div class="container">
<div class="section-header">
<h2>Apa Kata Pelanggan Kami</h2>
<p>Pengalaman nyata dari pelanggan yang puas dengan layanan kami</p>
</div>
<div class="testimonials-grid">
<div class="testimonial">
<div class="testimonial-header">
<div class="testimonial-avatar">DS</div>
<div><div class="testimonial-name">Dian Sastrowardoyo</div><div class="testimonial-stars">★★★★★</div></div>
</div>
<p>"Sangat puas dengan jasa kebersihan dari GoService. Tukangnya profesional dan detail dalam membersihkan setiap sudut rumah saya."</p>
</div>
<div class="testimonial">
<div class="testimonial-header">
<div class="testimonial-avatar">BS</div>
<div><div class="testimonial-name">Budi Santoso</div><div class="testimonial-stars">★★★★½</div></div>
</div>
<p>"Teknisi berhasil memperbaiki AC saya yang rusak hanya dalam 1 jam. Harganya juga cukup terjangkau. Sangat recommended!"</p>
</div>
<div class="testimonial">
<div class="testimonial-header">
<div class="testimonial-avatar">RW</div>
<div><div class="testimonial-name">Rina Wijaya</div><div class="testimonial-stars">★★★★★</div></div>
</div>
<p>"Desain interior rumah saya dibuat sangat bagus. Mereka memahami gaya yang saya inginkan dan mengeksekusi dengan sempurna."</p>
</div>
</div>
</div>
</section>

<!-- BOOKING SECTION -->
<section id="booking" class="cta-section">
<div class="container">
<div class="cta-box">
<div class="cta-info">
<h2>Butuh Bantuan Segera?</h2>
<p>Pesan layanan sekarang dan dapatkan tukang profesional di lokasi Anda dalam waktu 24 jam.</p>
<div class="cta-contact">
<div><i class="fas fa-phone-alt"></i> +62 123 4567 8910</div>
<div><i class="fas fa-envelope"></i> cs@goservice.id</div>
<div><i class="fas fa-map-marker-alt"></i> Kota Harapan Indah, Bekasi</div>
</div>
</div>
<div class="cta-form">
<h3>Formulir Pemesanan</h3>

<?php if(!isset($_SESSION['user_id'])): ?>
    <div style="background: rgba(255,255,255,0.9); padding: 30px; border-radius: 12px; text-align: center; border: 1px solid var(--border)">
        <i class="fas fa-lock" style="font-size: 2.8rem; color: var(--primary); margin-bottom: 16px;"></i>
        <h4 style="color:var(--text); font-weight: 700; margin-bottom: 8px;">Masuk Diperlukan</h4>
        <p style="color:var(--text-light); font-size: 0.9rem; margin-bottom: 24px;">Silakan login terlebih dahulu menggunakan akun Pelanggan Anda untuk melakukan pemesanan.</p>
        <a href="login.php?error=login_required" class="btn btn-primary" style="justify-content:center; width:100%"><i class="fas fa-sign-in-alt"></i> Masuk / Daftar</a>
    </div>
<?php elseif($_SESSION['user_role'] !== 'customer'): ?>
    <div style="background: rgba(255,255,255,0.9); padding: 30px; border-radius: 12px; text-align: center; border: 1px solid var(--border)">
        <i class="fas fa-user-slash" style="font-size: 2.8rem; color: #ef4444; margin-bottom: 16px;"></i>
        <h4 style="color:var(--text); font-weight: 700; margin-bottom: 8px;">Akses Terbatas</h4>
        <p style="color:var(--text-light); font-size: 0.9rem; margin-bottom: 24px;">Saat ini Anda masuk sebagai <strong><?= escape(strtoupper($_SESSION['user_role'])) ?></strong>. Silakan keluar dan gunakan akun Pelanggan untuk memesan.</p>
        <a href="auth.php?logout=1" class="btn btn-outline" style="justify-content:center; width:100%; border-color:#ef4444; color:#ef4444;"><i class="fas fa-sign-out-alt"></i> Keluar Akun</a>
    </div>
<?php else: ?>
    <!-- BOOKING FORM -->
    <form action="booking.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" name="nama" class="form-input" value="<?= escape($user_info['nama'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Nomor Telepon</label>
            <input type="tel" name="telepon" class="form-input" value="<?= escape($user_info['telepon'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Alamat Pengantaran</label>
            <textarea name="alamat_layanan" class="form-input" rows="2" required><?= escape($user_info['alamat'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Tanggal Pelaksanaan</label>
                <input type="date" name="tanggal_layanan" class="form-input" min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Waktu</label>
                <input type="time" name="waktu_layanan" class="form-input" required>
            </div>
        </div>
        <div class="form-group">
            <label>Jenis Layanan</label>
            <select name="service_id" class="form-input" required>
                <option value="">Pilih Layanan</option>
                <?php foreach($booking_services as $bs): ?>
                    <option value="<?= $bs['id'] ?>" <?= $preselected_id === $bs['id'] ? 'selected' : '' ?>><?= escape($bs['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Deskripsi Masalah / Catatan Tambahan</label>
            <textarea name="deskripsi" class="form-input" rows="2" placeholder="Tuliskan keluhan atau permintaan spesifik Anda di sini..."></textarea>
        </div>
        <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Pesan Sekarang</button>
    </form>
<?php endif; ?>

</div>
</div>
</div>
</section>

<!-- FOOTER -->
<footer class="footer">
<div class="container">
<div class="footer-grid">
<div>
<h3><i class="fas fa-bolt"></i> GoService</h3>
<p>Solusi profesional untuk semua kebutuhan layanan rumah dan kendaraan Anda.</p>
<div class="footer-social">
<a href="#"><i class="fab fa-facebook-f"></i></a>
<a href="#"><i class="fab fa-instagram"></i></a>
<a href="#"><i class="fab fa-twitter"></i></a>
<a href="#"><i class="fab fa-linkedin-in"></i></a>
</div>
</div>
<div>
<h4>Layanan</h4>
<ul>
<?php foreach($services as $serv): ?>
    <li><a href="layanan.php?slug=<?= escape($serv['slug']) ?>"><?= escape($serv['nama']) ?></a></li>
<?php endforeach; ?>
</ul>
</div>
<div>
<h4>Perusahaan</h4>
<ul>
<li><a href="#">Tentang Kami</a></li>
<li><a href="#">Blog</a></li>
<li><a href="#">Karir</a></li>
<li><a href="#">Kebijakan Privasi</a></li>
<li><a href="#">Syarat & Ketentuan</a></li>
</ul>
</div>
<div>
<h4>Hubungi Kami</h4>
<ul>
<li><i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:8px"></i> Kota Harapan Indah, Bekasi</li>
<li><i class="fas fa-phone-alt" style="color:var(--primary);margin-right:8px"></i> +62 123 4567 8910</li>
<li><i class="fas fa-envelope" style="color:var(--primary);margin-right:8px"></i> cs@goservice.id</li>
</ul>
</div>
</div>
<div class="footer-bottom">© 2025 GoService. All rights reserved.</div>
</div>
</footer>

<script>
document.querySelectorAll('a[href^="#"]').forEach(a=>{a.addEventListener('click',e=>{e.preventDefault();const t=document.querySelector(a.getAttribute('href'));if(t)t.scrollIntoView({behavior:'smooth'})})});
</script>
</body>
</html>
