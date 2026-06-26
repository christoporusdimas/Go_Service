<?php
require_once 'auth_admin.php';
require_once '../connect.php';

$success_msg = '';
$error_msg = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_provider_status'])) {
    verify_csrf();
    
    $provider_id = intval($_POST['provider_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? 'aktif');

    if (in_array($new_status, ['aktif', 'nonaktif', 'ditolak', 'pending'])) {
        $stmt = mysqli_prepare($conn, "UPDATE providers SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $new_status, $provider_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Status Mitra berhasil diubah menjadi: <strong>" . strtoupper($new_status) . "</strong>";
            log_error("Admin provider status changed: Provider ID $provider_id set to $new_status");
        } else {
            $error_msg = "Gagal memperbarui status Mitra.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Check filter
$filter = trim($_GET['filter'] ?? '');
$query = "SELECT p.id, p.nama, p.nik, p.email, p.telepon, p.alamat, p.pengalaman, p.deskripsi, p.rating, p.total_order, p.status, p.created_at, s.nama AS nama_layanan 
          FROM providers p
          JOIN services s ON p.service_id = s.id";

if ($filter === 'pending') {
    $query .= " WHERE p.status = 'pending'";
}
$query .= " ORDER BY p.created_at DESC";

$providers = [];
$res = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($res)) {
    $providers[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Kelola Mitra Jasa - GoService Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
            <a class="navbar-brand ps-3" href="index.php"><i class="fas fa-bolt"></i> Admin GoService</a>
            <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars"></i></button>
            <div class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0">
                <span class="text-white-50"><i class="fas fa-user-shield"></i> Login sebagai: <strong><?= escape($_SESSION['user_nama']) ?></strong></span>
            </div>
            <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-user fa-fw"></i></a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="../auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
        
        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
                <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                    <div class="sb-sidenav-menu">
                        <div class="nav">
                            <div class="sb-sidenav-menu-heading">Menu Utama</div>
                            <a class="nav-link" href="index.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                                Dashboard
                            </a>
                            
                            <div class="sb-sidenav-menu-heading">Data Master</div>
                            <a class="nav-link" href="users.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                                Pelanggan
                            </a>
                            <a class="nav-link active" href="providers.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-briefcase"></i></div>
                                Mitra Jasa
                            </a>
                            <a class="nav-link" href="services.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-tools"></i></div>
                                Kategori Layanan
                            </a>
                            <a class="nav-link" href="orders.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-shopping-cart"></i></div>
                                Transaksi Pesanan
                            </a>
                            
                            <div class="sb-sidenav-menu-heading">System</div>
                            <a class="nav-link" href="../auth.php?action=logout">
                                <div class="sb-nav-link-icon"><i class="fas fa-sign-out-alt"></i></div>
                                Keluar
                            </a>
                        </div>
                    </div>
                </nav>
            </div>
            
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Kelola Mitra Jasa</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Mitra Jasa</li>
                        </ol>

                        <?php if(!empty($success_msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?= $success_msg ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if(!empty($error_msg)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?= $error_msg ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- FILTERING BUTTONS -->
                        <div class="mb-4">
                            <a href="providers.php" class="btn btn-outline-primary <?= $filter !== 'pending' ? 'active' : '' ?>"><i class="fas fa-list"></i> Semua Mitra</a>
                            <a href="providers.php?filter=pending" class="btn btn-outline-warning <?= $filter === 'pending' ? 'active' : '' ?>"><i class="fas fa-clock"></i> Perlu Verifikasi (Pending)</a>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-briefcase me-1"></i>
                                Daftar Mitra Jasa / Teknisi
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="datatablesSimple" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Nama Mitra</th>
                                                <th>NIK & Email</th>
                                                <th>Layanan</th>
                                                <th>No. Telepon</th>
                                                <th>Exp & Rating</th>
                                                <th>Deskripsi</th>
                                                <th>Status</th>
                                                <th>Aksi Persetujuan / Kontrol</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($providers as $p): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= escape($p['nama']) ?></strong><br>
                                                        <small class="text-muted">Daftar: <?= format_date($p['created_at']) ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">NIK: <?= escape($p['nik']) ?></span><br>
                                                        <?= escape($p['email']) ?>
                                                    </td>
                                                    <td><span class="badge bg-info text-dark"><?= escape($p['nama_layanan']) ?></span></td>
                                                    <td><?= escape($p['telepon']) ?></td>
                                                    <td>
                                                        <small class="d-block">Pengalaman: <?= intval($p['pengalaman']) ?> Tahun</small>
                                                        <strong><?= number_format($p['rating'], 1) ?> ★</strong> (<?= intval($p['total_order']) ?> order)
                                                    </td>
                                                    <td><small><?= escape(substr($p['deskripsi'], 0, 100)) ?><?= strlen($p['deskripsi']) > 100 ? '...' : '' ?></small></td>
                                                    <td>
                                                        <?php 
                                                        $st = $p['status'];
                                                        if($st === 'pending') echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                        elseif($st === 'aktif') echo '<span class="badge bg-success">Aktif</span>';
                                                        elseif($st === 'nonaktif') echo '<span class="badge bg-secondary">Nonaktif</span>';
                                                        elseif($st === 'ditolak') echo '<span class="badge bg-danger">Ditolak</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if($st === 'pending'): ?>
                                                            <div class="d-flex gap-1">
                                                                <form action="providers.php?filter=<?= $filter ?>" method="POST" onsubmit="return confirm('Setujui pendaftaran mitra ini?')">
                                                                    <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                                                                    <input type="hidden" name="status" value="aktif">
                                                                    <input type="hidden" name="update_provider_status" value="1">
                                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Setujui</button>
                                                                </form>
                                                                <form action="providers.php?filter=<?= $filter ?>" method="POST" onsubmit="return confirm('Tolak pendaftaran mitra ini?')">
                                                                    <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                                                                    <input type="hidden" name="status" value="ditolak">
                                                                    <input type="hidden" name="update_provider_status" value="1">
                                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Tolak</button>
                                                                </form>
                                                            </div>
                                                        <?php else: ?>
                                                            <form action="providers.php?filter=<?= $filter ?>" method="POST" onsubmit="return confirm('Ubah status aktif mitra ini?')">
                                                                <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                                                                <input type="hidden" name="status" value="<?= $st === 'aktif' ? 'nonaktif' : 'aktif' ?>">
                                                                <input type="hidden" name="update_provider_status" value="1">
                                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                                <?php if($st === 'aktif'): ?>
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="width:100%"><i class="fas fa-ban"></i> Nonaktifkan</button>
                                                                <?php else: ?>
                                                                    <button type="submit" class="btn btn-sm btn-outline-success" style="width:100%"><i class="fas fa-check"></i> Aktifkan</button>
                                                                <?php endif; ?>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
                
                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted">© 2025 GoService. All rights reserved.</div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="js/datatables-simple-demo.js"></script>
    </body>
</html>
