<?php
require_once 'connect.php';

// If already logged in, redirect to correct dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'customer') {
        redirect('customer_dashboard.php');
    } elseif ($_SESSION['user_role'] === 'provider') {
        redirect('provider_dashboard.php');
    } elseif ($_SESSION['user_role'] === 'admin') {
        redirect('Admin/index.php');
    }
}

// Fetch active parent services for provider registration selection
$services_options = [];
try {
    $serv_query = "SELECT id, nama FROM services WHERE parent_id IS NULL AND status = 'aktif' ORDER BY urutan ASC";
    $serv_res = mysqli_query($conn, $serv_query);
    while ($row = mysqli_fetch_assoc($serv_res)) {
        $services_options[] = $row;
    }
} catch (Exception $e) {
    log_error("Failed to load services for login page: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk / Daftar - GoService</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .auth-container {
            width: 100%;
            max-width: 480px;
            margin: 20px auto;
        }
        .auth-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: var(--radius-lg);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
        }
        .auth-card:hover {
            box-shadow: 0 30px 60px -15px rgba(0,0,0,0.15);
        }
        .alert-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }
        .alert-success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .role-tab.active-admin {
            background: #475569;
            color: #fff;
            box-shadow: 0 2px 8px rgba(71, 85, 105, 0.3);
        }
    </style>
</head>
<body>

<div class="auth-page">
    <a href="index.php" class="back-home"><i class="fas fa-arrow-left"></i> Beranda</a>
    
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <h1><i class="fas fa-bolt"></i> GoService</h1>
                <p>Silakan masuk atau buat akun baru Anda.</p>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert-box alert-error">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i> 
                    <div>
                        <?php 
                        $err = $_GET['error'];
                        if($err === '1') echo "Email atau password salah!";
                        elseif($err === 'empty_fields') echo "Semua kolom wajib diisi!";
                        elseif($err === 'email_exists') echo "Email atau NIK sudah terdaftar!";
                        elseif($err === 'inactive_account') echo "Akun Pelanggan Anda tidak aktif.";
                        elseif($err === 'inactive_provider_pending') echo "Pendaftaran Mitra Anda sedang diproses. Mohon tunggu konfirmasi admin.";
                        elseif($err === 'inactive_provider_ditolak') echo "Pendaftaran Mitra Anda ditolak oleh admin.";
                        elseif($err === 'inactive_provider_nonaktif') echo "Akun Mitra Anda telah dinonaktifkan.";
                        elseif($err === 'login_required') echo "Silakan masuk terlebih dahulu untuk memesan.";
                        else echo "Terjadi kesalahan. Silakan coba kembali.";
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['success'])): ?>
                <div class="alert-box alert-success">
                    <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i> 
                    <div>
                        <?php 
                        if($_GET['success'] === 'registered') echo "Registrasi berhasil! Mitra Anda berstatus <strong>Pending</strong> menunggu verifikasi berkas oleh tim Admin kami.";
                        else echo "Proses berhasil!";
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ROLE SELECTION -->
            <div class="role-tabs">
                <button class="role-tab active" id="tabCustomer" onclick="setRole('customer')"><i class="fas fa-user"></i> Pelanggan</button>
                <button class="role-tab" id="tabProvider" onclick="setRole('provider')"><i class="fas fa-briefcase"></i> Mitra Jasa</button>
                <button class="role-tab" id="tabAdmin" onclick="setRole('admin')"><i class="fas fa-user-shield"></i> Admin</button>
            </div>

            <!-- MODE SWITCH (Hidden for Admin) -->
            <div class="mode-tabs" id="modeTabsContainer">
                <button class="mode-tab active" id="modeLogin" onclick="setMode('login')">Masuk</button>
                <button class="mode-tab" id="modeRegister" onclick="setMode('register')">Daftar</button>
            </div>

            <!-- LOGIN FORM -->
            <form id="loginForm" class="auth-form active" action="auth.php" method="POST" onsubmit="return validateLogin()">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="role" id="loginRole" value="customer">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="form-group">
                    <label id="emailLabel">Email</label>
                    <input type="text" name="email" id="loginEmail" class="form-input" placeholder="Masukkan email Anda" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="pw-toggle">
                        <input type="password" name="password" id="loginPassword" class="form-input pw-input" placeholder="Masukkan password" required>
                        <button type="button" class="toggle-pw" onclick="togglePw(this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                
                <div style="display:flex;justify-content:space-between;margin-bottom:24px;align-items:center;">
                    <label class="checkbox-label">
                        <input type="checkbox"> Ingat Saya
                    </label>
                    <a href="#" class="forgot-pw" style="font-size:0.85rem;color:var(--primary);font-weight:500">Lupa password?</a>
                </div>
                
                <button type="submit" class="btn-submit">Masuk</button>
                
                <div class="divider" id="socialDivider"><span>Atau masuk dengan</span></div>
                <div class="social-btns" id="socialButtons">
                    <button type="button" class="social-btn"><i class="fab fa-google" style="color:#ea4335"></i> Google</button>
                    <button type="button" class="social-btn"><i class="fab fa-facebook-f" style="color:#1877f2"></i> Facebook</button>
                </div>
            </form>

            <!-- REGISTER FORM -->
            <form id="registerForm" class="auth-form" action="auth.php" method="POST" onsubmit="return validateRegister()">
                <input type="hidden" name="action" id="registerAction" value="register_customer">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" id="regNama" class="form-input" placeholder="Nama sesuai KTP" required>
                </div>
                
                <div id="providerFields" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label>NIK</label>
                            <input type="text" name="nik" id="regNik" class="form-input" placeholder="16 Digit NIK" maxlength="16">
                        </div>
                        <div class="form-group">
                            <label>Layanan Utama</label>
                            <select name="service_id" id="regService" class="form-input">
                                <option value="">Pilih Layanan</option>
                                <?php foreach($services_options as $opt): ?>
                                    <option value="<?= $opt['id'] ?>"><?= escape($opt['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pengalaman (Tahun)</label>
                            <input type="number" name="pengalaman" id="regPengalaman" class="form-input" placeholder="Misal: 5" min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi Keahlian</label>
                        <textarea name="deskripsi" id="regDeskripsi" class="form-input" rows="2" placeholder="Ceritakan keahlian Anda secara singkat"></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="regEmail" class="form-input" placeholder="Email aktif" required>
                    </div>
                    <div class="form-group">
                        <label>Nomor Telepon</label>
                        <input type="tel" name="telepon" id="regTelepon" class="form-input" placeholder="08xx..." required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Alamat Lengkap</label>
                    <textarea name="alamat" id="regAlamat" class="form-input" rows="2" placeholder="Alamat rumah lengkap" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="pw-toggle">
                        <input type="password" name="password" id="regPassword" class="form-input pw-input" placeholder="Minimal 8 karakter" required>
                        <button type="button" class="toggle-pw" onclick="togglePw(this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                
                <label class="checkbox-label" style="margin-bottom:24px">
                    <input type="checkbox" id="regTerms" required>
                    <span>Saya menyetujui <a href="#" style="color: var(--primary); font-weight: 500;">Syarat Ketentuan</a> & <a href="#" style="color: var(--primary); font-weight: 500;">Kebijakan Privasi</a></span>
                </label>
                
                <button type="submit" class="btn-submit">Daftar Akun Baru</button>
            </form>
        </div>
    </div>
</div>

<script>
let currentRole = 'customer';
let currentMode = 'login';

function setRole(role) {
    currentRole = role;
    
    // Reset active classes
    document.querySelectorAll('.role-tab').forEach(el => {
        el.classList.remove('active');
        el.classList.remove('active-admin');
    });
    
    const clickedTab = event.currentTarget;
    if (role === 'admin') {
        clickedTab.classList.add('active-admin');
        document.getElementById('modeTabsContainer').style.display = 'none';
        document.getElementById('socialDivider').style.display = 'none';
        document.getElementById('socialButtons').style.display = 'none';
        document.getElementById('emailLabel').textContent = 'Username / Email Admin';
        document.getElementById('loginEmail').placeholder = 'Masukkan username admin';
        setMode('login'); // Admin can only login, no register
    } else {
        clickedTab.classList.add('active');
        document.getElementById('modeTabsContainer').style.display = 'flex';
        document.getElementById('socialDivider').style.display = 'block';
        document.getElementById('socialButtons').style.display = 'flex';
        document.getElementById('emailLabel').textContent = 'Email';
        document.getElementById('loginEmail').placeholder = 'Masukkan email Anda';
    }
    
    // Update role hidden field
    document.getElementById('loginRole').value = role;
    
    // Configure register fields based on customer vs provider
    document.getElementById('registerAction').value = (role === 'customer') ? 'register_customer' : 'register_provider';
    
    const providerFields = document.getElementById('providerFields');
    const provInputs = providerFields.querySelectorAll('input, select, textarea');
    
    if (role === 'provider') {
        providerFields.style.display = 'block';
        provInputs.forEach(i => i.required = true);
    } else {
        providerFields.style.display = 'none';
        provInputs.forEach(i => i.required = false);
    }
}

function setMode(mode) {
    currentMode = mode;
    document.querySelectorAll('.mode-tab').forEach(el => el.classList.remove('active'));
    
    const activeTab = document.getElementById('mode' + mode.charAt(0).toUpperCase() + mode.slice(1));
    if (activeTab) activeTab.classList.add('active');
    
    document.querySelectorAll('.auth-form').forEach(el => el.classList.remove('active'));
    document.getElementById(mode + 'Form').classList.add('active');
}

function togglePw(btn) {
    const input = btn.previousElementSibling;
    const icon = btn.querySelector('i');
    if(input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Client-side validations
function validateLogin() {
    const email = document.getElementById('loginEmail').value.trim();
    const pass = document.getElementById('loginPassword').value;
    if (!email || !pass) {
        alert('Email dan Password wajib diisi.');
        return false;
    }
    return true;
}

function validateRegister() {
    const nama = document.getElementById('regNama').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const tel = document.getElementById('regTelepon').value.trim();
    const alamat = document.getElementById('regAlamat').value.trim();
    const pass = document.getElementById('regPassword').value;
    
    if (nama.length < 3) {
        alert('Nama lengkap minimal 3 karakter.');
        return false;
    }
    
    if (currentRole === 'provider') {
        const nik = document.getElementById('regNik').value.trim();
        const service = document.getElementById('regService').value;
        const exp = document.getElementById('regPengalaman').value;
        
        if (nik.length !== 16 || isNaN(nik)) {
            alert('NIK wajib 16 digit angka.');
            return false;
        }
        if (!service) {
            alert('Silakan pilih Layanan Utama Anda.');
            return false;
        }
        if (exp === '' || exp < 0) {
            alert('Pengalaman kerja tidak valid.');
            return false;
        }
    }
    
    if (pass.length < 8) {
        alert('Password minimal harus 8 karakter.');
        return false;
    }
    
    return true;
}

// Handle URL query parameters for initial state setup
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('role') === 'provider') {
    document.getElementById('tabProvider').click();
} else if (urlParams.get('role') === 'admin') {
    document.getElementById('tabAdmin').click();
}
if (urlParams.get('mode') === 'register') {
    setMode('register');
}
</script>

</body>
</html>
