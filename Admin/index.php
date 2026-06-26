<?php
require_once 'auth_admin.php';
require_once '../connect.php';

// Fetch statistics
$count_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users"))['cnt'];
$count_providers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM providers WHERE status='aktif'"))['cnt'];
$count_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM providers WHERE status='pending'"))['cnt'];
$count_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM orders"))['cnt'];
$sum_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_harga) as sum FROM orders WHERE status_bayar='lunas'"))['sum'] ?? 0;

// Fetch 5 most recent orders
$recent_orders = [];
$res = mysqli_query($conn, "SELECT o.kode_pesanan, o.tanggal_layanan, o.total_harga, o.status, o.status_bayar, u.nama as customer_name, s.nama as service_name
                           FROM orders o 
                           JOIN users u ON o.user_id = u.id 
                           JOIN services s ON o.service_id = s.id 
                           ORDER BY o.created_at DESC LIMIT 5");
while ($row = mysqli_fetch_assoc($res)) {
    $recent_orders[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Dashboard Admin - GoService</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
            <!-- Navbar Brand-->
            <a class="navbar-brand ps-3" href="index.php"><i class="fas fa-bolt"></i> Admin GoService</a>
            <!-- Sidebar Toggle-->
            <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars"></i></button>
            
            <div class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0">
                <span class="text-white-50"><i class="fas fa-user-shield"></i> Login sebagai: <strong><?= escape($_SESSION['user_nama']) ?></strong></span>
            </div>
            
            <!-- Navbar Dropdown-->
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
                            <a class="nav-link active" href="index.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                                Dashboard
                            </a>
                            
                            <div class="sb-sidenav-menu-heading">Data Master</div>
                            <a class="nav-link" href="users.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                                Pelanggan
                            </a>
                            <a class="nav-link" href="providers.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-briefcase"></i></div>
                                Mitra Jasa
                                <?php if($count_pending > 0): ?>
                                    <span class="badge bg-warning text-dark ms-auto" style="font-size:0.75rem; font-weight:700"><?= $count_pending ?></span>
                                <?php endif; ?>
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
                    <div class="sb-sidenav-footer">
                        <div class="small">Peran Akses:</div>
                        Administrator
                    </div>
                </nav>
            </div>
            
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Dashboard Admin</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item active">Statistik GoService</li>
                        </ol>
                        
                        <!-- COUNTER CARDS -->
                        <div class="row">
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-primary text-white mb-4">
                                    <div class="card-body">
                                        <h3><?= $count_users ?></h3>
                                        <div>Total Pelanggan</div>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link text-decoration-none" href="users.php">Lihat Detail</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-success text-white mb-4">
                                    <div class="card-body">
                                        <h3><?= $count_providers ?></h3>
                                        <div>Mitra Jasa Aktif</div>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link text-decoration-none" href="providers.php">Lihat Detail</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-warning text-dark mb-4">
                                    <div class="card-body">
                                        <h3><?= $count_pending ?></h3>
                                        <div>Mitra Perlu Persetujuan</div>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-dark stretched-link text-decoration-none" href="providers.php?filter=pending">Tinjau Registrasi</a>
                                        <div class="small text-dark"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-info text-dark mb-4">
                                    <div class="card-body">
                                        <h3><?= format_rupiah($sum_revenue) ?></h3>
                                        <div>Total Pendapatan (Lunas)</div>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-dark stretched-link text-decoration-none" href="orders.php">Lihat Transaksi</a>
                                        <div class="small text-dark"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- RECENT ORDERS -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-table me-1"></i>
                                Pesanan Masuk Terakhir
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Kode Pesanan</th>
                                                <th>Pelanggan</th>
                                                <th>Layanan</th>
                                                <th>Tanggal Pelaksanaan</th>
                                                <th>Total Pembayaran</th>
                                                <th>Pembayaran</th>
                                                <th>Status Kerja</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($recent_orders)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">Belum ada transaksi pesanan terdaftar.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($recent_orders as $ord): ?>
                                                    <tr>
                                                        <td><strong><?= escape($ord['kode_pesanan']) ?></strong></td>
                                                        <td><?= escape($ord['customer_name']) ?></td>
                                                        <td><?= escape($ord['service_name']) ?></td>
                                                        <td><?= format_date($ord['tanggal_layanan']) ?></td>
                                                        <td><?= format_rupiah($ord['total_harga']) ?></td>
                                                        <td>
                                                            <?php if($ord['status_bayar'] === 'lunas'): ?>
                                                                <span class="badge bg-success">Lunas</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Belum Bayar</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $st = $ord['status'];
                                                            if($st === 'pending') echo '<span class="badge bg-secondary">Menunggu</span>';
                                                            elseif($st === 'dikonfirmasi') echo '<span class="badge bg-primary">Dikonfirmasi</span>';
                                                            elseif($st === 'dalam_proses') echo '<span class="badge bg-info text-dark">Dikerjakan</span>';
                                                            elseif($st === 'selesai') echo '<span class="badge bg-success">Selesai</span>';
                                                            elseif($st === 'dibatalkan') echo '<span class="badge bg-danger">Batal</span>';
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
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
    </body>
</html>
