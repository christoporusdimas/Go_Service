<?php
require_once 'auth_admin.php';
require_once '../connect.php';

$success_msg = '';
$error_msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'assign_provider') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $provider_id = intval($_POST['provider_id'] ?? 0);

        if ($provider_id === 0) {
            $error_msg = "Silakan pilih Mitra Jasa yang valid.";
        } else {
            // Update order with provider and set status to dikonfirmasi
            $stmt = mysqli_prepare($conn, "UPDATE orders SET provider_id = ?, status = 'dikonfirmasi' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $provider_id, $order_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Mitra Jasa berhasil ditugaskan ke pesanan. Status diset menjadi 'Dikonfirmasi'.";
                log_error("Admin assigned provider ID $provider_id to Order ID $order_id");
            } else {
                $error_msg = "Gabi menugaskan Mitra Jasa.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($action === 'update_status') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $new_status = trim($_POST['status'] ?? '');
        $status_bayar = trim($_POST['status_bayar'] ?? '');

        if (!empty($new_status)) {
            $stmt = mysqli_prepare($conn, "UPDATE orders SET status = ?, status_bayar = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $new_status, $status_bayar, $order_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Status pesanan berhasil diperbarui.";
                log_error("Admin updated Order ID $order_id: Status=$new_status, PaymentStatus=$status_bayar");
            } else {
                $error_msg = "Gagal memperbarui status pesanan.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch all orders
$orders = [];
$query_orders = "SELECT o.id, o.kode_pesanan, o.tanggal_layanan, o.waktu_layanan, o.alamat_layanan, o.deskripsi, o.total_harga, o.status, o.status_bayar, o.metode_bayar, o.created_at,
                        u.nama AS nama_pelanggan, u.telepon AS telp_pelanggan,
                        s.nama AS nama_layanan, s.parent_id AS parent_service_id, o.service_id,
                        p.id AS provider_id, p.nama AS nama_mitra
                 FROM orders o
                 JOIN users u ON o.user_id = u.id
                 JOIN services s ON o.service_id = s.id
                 LEFT JOIN providers p ON o.provider_id = p.id
                 ORDER BY o.created_at DESC";
$res_orders = mysqli_query($conn, $query_orders);
while ($row = mysqli_fetch_assoc($res_orders)) {
    $orders[] = $row;
}

// Fetch all active providers grouped by their category for assignment selection
$active_providers = [];
$res_prov = mysqli_query($conn, "SELECT id, nama, service_id FROM providers WHERE status = 'aktif'");
while ($row = mysqli_fetch_assoc($res_prov)) {
    $active_providers[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Kelola Pesanan - GoService Admin</title>
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
                            <a class="nav-link" href="providers.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-briefcase"></i></div>
                                Mitra Jasa
                            </a>
                            <a class="nav-link" href="services.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-tools"></i></div>
                                Kategori Layanan
                            </a>
                            <a class="nav-link active" href="orders.php">
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
                        <h1 class="mt-4">Kelola Transaksi Pesanan</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Transaksi Pesanan</li>
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
                                <i class="fas fa-shopping-cart me-1"></i>
                                Semua Pesanan Pelanggan
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="datatablesSimple" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Kode Pesanan</th>
                                                <th>Pelanggan & Telp</th>
                                                <th>Layanan</th>
                                                <th>Jadwal & Alamat</th>
                                                <th>Total Harga</th>
                                                <th>Metode & Status Bayar</th>
                                                <th>Mitra Ditugaskan</th>
                                                <th>Status Kerja</th>
                                                <th>Aksi Kontrol</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($orders as $o): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= escape($o['kode_pesanan']) ?></strong><br>
                                                        <small class="text-muted"><?= format_date($o['created_at']) ?></small>
                                                    </td>
                                                    <td>
                                                        <strong><?= escape($o['nama_pelanggan']) ?></strong><br>
                                                        <small><?= escape($o['telp_pelanggan']) ?></small>
                                                    </td>
                                                    <td><?= escape($o['nama_layanan']) ?></td>
                                                    <td>
                                                        <?= format_date($o['tanggal_layanan']) ?> Pkl <?= escape(substr($o['waktu_layanan'], 0, 5)) ?><br>
                                                        <small class="text-muted"><?= escape($o['alamat_layanan']) ?></small>
                                                    </td>
                                                    <td><strong><?= format_rupiah($o['total_harga']) ?></strong></td>
                                                    <td>
                                                        <small class="text-uppercase" style="font-weight:600;"><?= escape($o['metode_bayar']) ?></small><br>
                                                        <?php if($o['status_bayar'] === 'lunas'): ?>
                                                            <span class="badge bg-success">Lunas</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Belum Lunas</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if($o['provider_id']): ?>
                                                            <strong class="text-primary"><?= escape($o['nama_mitra']) ?></strong>
                                                        <?php else: ?>
                                                            <span class="text-danger italic" style="font-size:0.85rem;"><i class="fas fa-exclamation-triangle"></i> Belum ditugaskan</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $st = $o['status'];
                                                        if($st === 'pending') echo '<span class="badge bg-secondary">Menunggu</span>';
                                                        elseif($st === 'dikonfirmasi') echo '<span class="badge bg-primary">Dikonfirmasi</span>';
                                                        elseif($st === 'dalam_proses') echo '<span class="badge bg-info text-dark">Dikerjakan</span>';
                                                        elseif($st === 'selesai') echo '<span class="badge bg-success">Selesai</span>';
                                                        elseif($st === 'dibatalkan') echo '<span class="badge bg-danger">Batal</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-column gap-1">
                                                            <!-- Assign Provider Button -->
                                                            <?php if(in_array($st, ['pending', 'dikonfirmasi'])): ?>
                                                                <button class="btn btn-sm btn-primary py-1" onclick="openAssignModal(<?= $o['id'] ?>, <?= $o['service_id'] ?>, '<?= $o['parent_service_id'] ? $o['parent_service_id'] : $o['service_id'] ?>')">
                                                                    <i class="fas fa-user-plus"></i> Tugaskan
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <!-- Edit status button -->
                                                            <button class="btn btn-sm btn-outline-dark py-1" onclick="openEditStatusModal(<?= $o['id'] ?>, '<?= $st ?>', '<?= $o['status_bayar'] ?>')">
                                                                <i class="fas fa-edit"></i> Update
                                                            </button>
                                                        </div>
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

        <!-- ASSIGN PROVIDER MODAL -->
        <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="assignModalLabel">Tugaskan Mitra Jasa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="orders.php" method="POST">
                        <input type="hidden" name="action" value="assign_provider">
                        <input type="hidden" name="order_id" id="assign_order_id" value="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Pilih Mitra Jasa Aktif</label>
                                <select name="provider_id" id="assign_provider_select" class="form-select" required>
                                    <option value="">-- Pilih Mitra --</option>
                                    <!-- Options will be filtered dynamically by JS based on service category -->
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Tugaskan Mitra</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- EDIT STATUS MODAL -->
        <div class="modal fade" id="editStatusModal" tabindex="-1" aria-labelledby="editStatusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editStatusModalLabel">Update Status Transaksi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="orders.php" method="POST">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" id="edit_order_id" value="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Status Pekerjaan</label>
                                <select name="status" id="edit_order_status" class="form-select">
                                    <option value="pending">Menunggu (Pending)</option>
                                    <option value="dikonfirmasi">Dikonfirmasi (Assigned)</option>
                                    <option value="dalam_proses">Dikerjakan (In Progress)</option>
                                    <option value="selesai">Selesai (Completed)</option>
                                    <option value="dibatalkan">Batal (Cancelled)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status Pembayaran</label>
                                <select name="status_bayar" id="edit_order_status_bayar" class="form-select">
                                    <option value="belum">Belum Lunas</option>
                                    <option value="lunas">Lunas</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="js/datatables-simple-demo.js"></script>
        <script>
        // List of all active providers loaded from PHP
        const allProviders = <?= json_encode($active_providers) ?>;

        function openAssignModal(orderId, serviceId, parentId) {
            document.getElementById('assign_order_id').value = orderId;
            
            const selectEl = document.getElementById('assign_provider_select');
            selectEl.innerHTML = '<option value="">-- Pilih Mitra --</option>';
            
            // Filter providers that matches the order's service_id or parent_id
            const matchedProviders = allProviders.filter(p => p.service_id == serviceId || p.service_id == parentId);
            
            if(matchedProviders.length === 0) {
                selectEl.innerHTML += '<option value="" disabled>Tidak ada mitra aktif untuk kategori layanan ini</option>';
            } else {
                matchedProviders.forEach(p => {
                    selectEl.innerHTML += `<option value="${p.id}">${p.nama}</option>`;
                });
            }
            
            const assignModal = new bootstrap.Modal(document.getElementById('assignModal'));
            assignModal.show();
        }

        function openEditStatusModal(orderId, currentStatus, currentPayStatus) {
            document.getElementById('edit_order_id').value = orderId;
            document.getElementById('edit_order_status').value = currentStatus;
            document.getElementById('edit_order_status_bayar').value = currentPayStatus;
            
            const editModal = new bootstrap.Modal(document.getElementById('editStatusModal'));
            editModal.show();
        }
        </script>
    </body>
</html>
