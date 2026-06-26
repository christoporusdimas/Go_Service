<?php
require_once 'auth_admin.php';
require_once '../connect.php';

$success_msg = '';
$error_msg = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nama        = trim($_POST['nama'] ?? '');
        $deskripsi   = trim($_POST['deskripsi'] ?? '');
        $icon        = trim($_POST['icon'] ?? 'fa-tools');
        $harga_dasar = floatval($_POST['harga_dasar'] ?? 0);
        $parent_id   = $_POST['parent_id'] !== '' ? intval($_POST['parent_id']) : null;
        
        // Auto generate slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nama), '-'));

        // Check unique slug
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM services WHERE slug = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $slug);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $slug .= '-' . rand(10, 99);
        }
        mysqli_stmt_close($stmt_check);

        $stmt = mysqli_prepare($conn, "INSERT INTO services (nama, slug, deskripsi, icon, harga_dasar, parent_id, status) VALUES (?, ?, ?, ?, ?, ?, 'aktif')");
        mysqli_stmt_bind_param($stmt, "ssssdi", $nama, $slug, $deskripsi, $icon, $harga_dasar, $parent_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Layanan baru <strong>" . escape($nama) . "</strong> berhasil ditambahkan.";
            log_error("Admin service added: " . $nama);
        } else {
            $error_msg = "Gagal menyimpan layanan baru.";
        }
        mysqli_stmt_close($stmt);
    }

    if ($action === 'edit') {
        $id          = intval($_POST['id'] ?? 0);
        $nama        = trim($_POST['nama'] ?? '');
        $deskripsi   = trim($_POST['deskripsi'] ?? '');
        $icon        = trim($_POST['icon'] ?? 'fa-tools');
        $harga_dasar = floatval($_POST['harga_dasar'] ?? 0);
        $parent_id   = $_POST['parent_id'] !== '' ? intval($_POST['parent_id']) : null;

        $stmt = mysqli_prepare($conn, "UPDATE services SET nama = ?, deskripsi = ?, icon = ?, harga_dasar = ?, parent_id = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssdii", $nama, $deskripsi, $icon, $harga_dasar, $parent_id, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Layanan <strong>" . escape($nama) . "</strong> berhasil diperbarui.";
            log_error("Admin service updated: ID " . $id);
        } else {
            $error_msg = "Gagal memperbarui layanan.";
        }
        mysqli_stmt_close($stmt);
    }

    if ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        $current_status = trim($_POST['current_status'] ?? 'aktif');
        $new_status = ($current_status === 'aktif') ? 'nonaktif' : 'aktif';

        $stmt = mysqli_prepare($conn, "UPDATE services SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $new_status, $id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Status layanan berhasil diperbarui.";
            log_error("Admin service status toggled: ID $id set to $new_status");
        } else {
            $error_msg = "Gagal mengubah status layanan.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch parent services for select inputs
$parents = [];
$res_p = mysqli_query($conn, "SELECT id, nama FROM services WHERE parent_id IS NULL AND status = 'aktif' ORDER BY urutan ASC");
while ($row = mysqli_fetch_assoc($res_p)) {
    $parents[] = $row;
}

// Fetch all services
$services = [];
$res_s = mysqli_query($conn, "SELECT s.id, s.nama, s.slug, s.deskripsi, s.icon, s.harga_dasar, s.parent_id, s.status, p.nama AS nama_induk 
                              FROM services s 
                              LEFT JOIN services p ON s.parent_id = p.id 
                              ORDER BY COALESCE(s.parent_id, s.id), s.parent_id IS NOT NULL, s.urutan ASC");
while ($row = mysqli_fetch_assoc($res_s)) {
    $services[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Kelola Layanan - GoService Admin</title>
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
                            <a class="nav-link active" href="services.php">
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
                        <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                            <h1>Kelola Kategori Layanan</h1>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal"><i class="fas fa-plus"></i> Tambah Layanan</button>
                        </div>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Kategori Layanan</li>
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
                                <i class="fas fa-tools me-1"></i>
                                Daftar Kategori & Sub-Layanan
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="datatablesSimple" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Icon</th>
                                                <th>Nama Layanan</th>
                                                <th>Induk Kategori</th>
                                                <th>Harga Dasar</th>
                                                <th>Deskripsi</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($services as $s): ?>
                                                <tr>
                                                    <td class="text-center"><i class="fas <?= escape($s['icon']) ?> text-primary" style="font-size:1.2rem;"></i></td>
                                                    <td><strong><?= escape($s['nama']) ?></strong></td>
                                                    <td>
                                                        <?php if($s['parent_id']): ?>
                                                            <span class="badge bg-secondary"><?= escape($s['nama_induk']) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-dark">Kategori Utama</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><strong><?= format_rupiah($s['harga_dasar']) ?></strong></td>
                                                    <td><small><?= escape($s['deskripsi'] ?: '-') ?></small></td>
                                                    <td>
                                                        <?php if($s['status'] === 'aktif'): ?>
                                                            <span class="badge bg-success">Aktif</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Nonaktif</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    onclick="openEditModal(<?= $s['id'] ?>, '<?= escape($s['nama']) ?>', '<?= escape($s['deskripsi']) ?>', '<?= escape($s['icon']) ?>', <?= $s['harga_dasar'] ?>, '<?= $s['parent_id'] ?>')">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <form action="services.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengubah status aktif layanan ini?')">
                                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                                <input type="hidden" name="current_status" value="<?= $s['status'] ?>">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                                <?php if($s['status'] === 'aktif'): ?>
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i> Batalkan</button>
                                                                <?php else: ?>
                                                                    <button type="submit" class="btn btn-sm btn-outline-success"><i class="fas fa-check"></i> Aktifkan</button>
                                                                <?php endif; ?>
                                                            </form>
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

        <!-- ADD MODAL -->
        <div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addServiceModalLabel">Tambah Layanan Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="services.php" method="POST">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Layanan</label>
                                <input type="text" name="nama" class="form-control" required placeholder="Contoh: Bersih Kamar Mandi">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Induk Kategori</label>
                                <select name="parent_id" class="form-select">
                                    <option value="">-- Kategori Utama --</option>
                                    <?php foreach($parents as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= escape($p['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Harga Dasar (Rp)</label>
                                <input type="number" name="harga_dasar" class="form-control" value="0" min="0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Icon FontAwesome Class</label>
                                <input type="text" name="icon" class="form-control" value="fa-tools">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deskripsi Jasa</label>
                                <textarea name="deskripsi" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- EDIT MODAL -->
        <div class="modal fade" id="editServiceModal" tabindex="-1" aria-labelledby="editServiceModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editServiceModalLabel">Edit Layanan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="services.php" method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id" value="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Layanan</label>
                                <input type="text" name="nama" id="edit_nama" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Induk Kategori</label>
                                <select name="parent_id" id="edit_parent_id" class="form-select">
                                    <option value="">-- Kategori Utama --</option>
                                    <?php foreach($parents as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= escape($p['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Harga Dasar (Rp)</label>
                                <input type="number" name="harga_dasar" id="edit_harga_dasar" class="form-control" min="0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Icon FontAwesome Class</label>
                                <input type="text" name="icon" id="edit_icon" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deskripsi Jasa</label>
                                <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
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
        function openEditModal(id, nama, deskripsi, icon, harga, parent_id) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_deskripsi').value = deskripsi;
            document.getElementById('edit_icon').value = icon;
            document.getElementById('edit_harga_dasar').value = harga;
            document.getElementById('edit_parent_id').value = parent_id ? parent_id : '';
            
            const editModal = new bootstrap.Modal(document.getElementById('editServiceModal'));
            editModal.show();
        }
        </script>
    </body>
</html>
