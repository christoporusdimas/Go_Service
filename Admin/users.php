<?php
require_once 'auth_admin.php';
require_once '../connect.php';

$success_msg = '';
$error_msg = '';

// Toggle customer status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    verify_csrf();
    
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $current_status = trim($_POST['current_status'] ?? 'aktif');
    $new_status = ($current_status === 'aktif') ? 'nonaktif' : 'aktif';

    $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $new_status, $customer_id);
    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "Status pelanggan berhasil diubah menjadi: <strong>" . $new_status . "</strong>";
        log_error("Admin user status changed: User ID $customer_id set to $new_status");
    } else {
        $error_msg = "Gagal memperbarui status pelanggan.";
    }
    mysqli_stmt_close($stmt);
}

// Fetch customers list
$customers = [];
$res = mysqli_query($conn, "SELECT id, nama, email, telepon, alamat, status, created_at FROM users ORDER BY created_at DESC");
while ($row = mysqli_fetch_assoc($res)) {
    $customers[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Kelola Pelanggan - GoService Admin</title>
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
                            <a class="nav-link active" href="users.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                                Pelanggan
                            </a>
                            <a class="nav-link" href="providers.php">
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
                        <h1 class="mt-4">Kelola Pelanggan</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Pelanggan</li>
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

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-users me-1"></i>
                                Daftar Pelanggan Terdaftar
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="datatablesSimple" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Nama Lengkap</th>
                                                <th>Email</th>
                                                <th>No. Telepon</th>
                                                <th>Alamat</th>
                                                <th>Tanggal Gabung</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($customers as $c): ?>
                                                <tr>
                                                    <td><strong><?= escape($c['nama']) ?></strong></td>
                                                    <td><?= escape($c['email']) ?></td>
                                                    <td><?= escape($c['telepon']) ?></td>
                                                    <td><?= escape($c['alamat'] ?: '-') ?></td>
                                                    <td><?= format_date($c['created_at']) ?></td>
                                                    <td>
                                                        <?php if($c['status'] === 'aktif'): ?>
                                                            <span class="badge bg-success">Aktif</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Nonaktif</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <form action="users.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengubah status aktif pelanggan ini?')">
                                                            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                                            <input type="hidden" name="current_status" value="<?= $c['status'] ?>">
                                                            <input type="hidden" name="toggle_status" value="1">
                                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                            <?php if($c['status'] === 'aktif'): ?>
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" style="width: 100px;"><i class="fas fa-ban"></i> Nonaktifkan</button>
                                                            <?php else: ?>
                                                                <button type="submit" class="btn btn-sm btn-outline-success" style="width: 100px;"><i class="fas fa-check"></i> Aktifkan</button>
                                                            <?php endif; ?>
                                                        </form>
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
