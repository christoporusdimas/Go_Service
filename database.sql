-- =====================================================
-- GoService Database Schema
-- Platform Booking Layanan Jasa
-- Jalankan file ini di phpMyAdmin atau MySQL CLI
-- =====================================================

CREATE DATABASE IF NOT EXISTS GoService CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE GoService;

-- -----------------------------------------------------
-- Tabel: admin
-- -----------------------------------------------------
DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Tabel: users (Pelanggan)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `telepon` VARCHAR(20) NOT NULL,
  `alamat` TEXT,
  `foto` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Tabel: services (Kategori Layanan)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `deskripsi` TEXT,
  `icon` VARCHAR(50) DEFAULT NULL,
  `harga_dasar` DECIMAL(12,2) DEFAULT 0.00,
  `parent_id` INT DEFAULT NULL,
  `urutan` INT DEFAULT 0,
  `status` ENUM('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `services`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Tabel: providers (Penyedia Jasa / Mitra)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `providers`;
CREATE TABLE `providers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `nik` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `telepon` VARCHAR(20) NOT NULL,
  `alamat` TEXT,
  `service_id` INT NOT NULL,
  `pengalaman` INT DEFAULT 0 COMMENT 'Tahun pengalaman',
  `deskripsi` TEXT,
  `rating` DECIMAL(2,1) DEFAULT 0.0,
  `total_review` INT DEFAULT 0,
  `total_order` INT DEFAULT 0,
  `foto` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','aktif','nonaktif','ditolak') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE,
  INDEX `idx_provider_service` (`service_id`),
  INDEX `idx_provider_status` (`status`),
  INDEX `idx_provider_rating` (`rating`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Tabel: orders (Pesanan)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_pesanan` VARCHAR(20) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `provider_id` INT DEFAULT NULL,
  `service_id` INT NOT NULL,
  `tanggal_layanan` DATE NOT NULL,
  `waktu_layanan` TIME NOT NULL,
  `alamat_layanan` TEXT NOT NULL,
  `deskripsi` TEXT,
  `total_harga` DECIMAL(12,2) DEFAULT 0.00,
  `status` ENUM('pending','dikonfirmasi','dalam_proses','selesai','dibatalkan') DEFAULT 'pending',
  `metode_bayar` ENUM('tunai','transfer','ewallet') DEFAULT 'tunai',
  `status_bayar` ENUM('belum','lunas') DEFAULT 'belum',
  `catatan` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE,
  INDEX `idx_order_user` (`user_id`),
  INDEX `idx_order_provider` (`provider_id`),
  INDEX `idx_order_status` (`status`),
  INDEX `idx_order_tanggal` (`tanggal_layanan`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Tabel: reviews (Ulasan)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `provider_id` INT NOT NULL,
  `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `komentar` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE,
  INDEX `idx_review_provider` (`provider_id`),
  UNIQUE KEY `unique_order_review` (`order_id`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Tabel: transactions (Riwayat Pembayaran)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
  `gross_amount` DECIMAL(12,2) NOT NULL,
  `payment_type` VARCHAR(50) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `raw_payload` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_transaction_order` (`order_id`)
) ENGINE=InnoDB;

-- =====================================================
-- SAMPLE DATA
-- =====================================================

-- -- Admin (password: 'password')
INSERT INTO `admin` (`username`, `password`, `nama`, `email`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@goservice.id');

-- Users / Pelanggan (password: 'password')
INSERT INTO `users` (`nama`, `email`, `password`, `telepon`, `alamat`) VALUES
('Dian Sastrowardoyo', 'dian@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567890', 'Jl. Harapan Indah Blok A No. 10, Bekasi'),
('Budi Santoso', 'budi@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081298765432', 'Jl. Melati No. 5, Jakarta Timur'),
('Rina Wijaya', 'rina@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '085611223344', 'Jl. Kenanga No. 12, Bekasi Selatan'),
('Ahmad Fauzi', 'ahmad@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '087855667788', 'Jl. Mawar No. 8, Cikarang'),
('Siti Nurhaliza', 'siti@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081377889900', 'Jl. Anggrek No. 3, Tambun');

-- Services / Kategori Layanan (Parent)
INSERT INTO `services` (`nama`, `slug`, `deskripsi`, `icon`, `harga_dasar`, `urutan`) VALUES
('Kebersihan Rumah', 'kebersihan', 'Jasa bersih-bersih rumah, kantor, atau apartemen oleh tenaga profesional.', 'fa-broom', 150000.00, 1),
('Perbaikan Rumah', 'perbaikan', 'Perbaikan segala kerusakan di rumah termasuk listrik, plumbing, keramik.', 'fa-tools', 200000.00, 2),
('Pembangunan', 'pembangunan', 'Jasa pembangunan dan renovasi rumah, termasuk interior dan eksterior.', 'fa-hammer', 1000000.00, 3),
('Desain Interior', 'desain', 'Jasa desain interior dan eksterior rumah sesuai gaya dan kebutuhan.', 'fa-pencil-ruler', 500000.00, 4),
('Servis Kendaraan', 'kendaraan', 'Perbaikan dan perawatan kendaraan roda dua dan empat di lokasi Anda.', 'fa-car', 150000.00, 5),
('Layanan Lainnya', 'lainnya', 'Berbagai layanan home service lainnya sesuai kebutuhan spesifik Anda.', 'fa-ellipsis-h', 100000.00, 6);

-- Sub-services
INSERT INTO `services` (`nama`, `slug`, `deskripsi`, `icon`, `harga_dasar`, `parent_id`, `urutan`) VALUES
('Bersih Rumah', 'bersih-rumah', 'Pembersihan menyeluruh untuk rumah tinggal', 'fa-home', 150000.00, 1, 1),
('Bersih Kantor', 'bersih-kantor', 'Pembersihan profesional untuk ruang kantor', 'fa-building', 350000.00, 1, 2),
('Bersih Apartemen', 'bersih-apartemen', 'Pembersihan khusus unit apartemen', 'fa-city', 200000.00, 1, 3),
('Perbaikan Listrik', 'perbaikan-listrik', 'Perbaikan instalasi and peralatan listrik', 'fa-bolt', 200000.00, 2, 1),
('Perbaikan Pipa', 'perbaikan-pipa', 'Perbaikan pipa air dan saluran', 'fa-faucet', 150000.00, 2, 2),
('Perbaikan AC', 'perbaikan-ac', 'Servis dan perbaikan pendingin ruangan', 'fa-snowflake', 100000.00, 2, 3),
('Renovasi Rumah', 'renovasi-rumah', 'Renovasi dan pembangunan struktur rumah', 'fa-hard-hat', 1500000.00, 3, 1),
('Pengecatan', 'pengecatan', 'Jasa pengecatan interior dan eksterior', 'fa-paint-roller', 500000.00, 3, 2),
('Desain Ruangan', 'desain-ruangan', 'Desain interior ruangan lengkap', 'fa-couch', 1200000.00, 4, 1),
('Konsultasi Desain', 'konsultasi-desain', 'Konsultasi desain dengan ahli', 'fa-comments', 250000.00, 4, 2),
('Servis Motor', 'servis-motor', 'Perawatan dan perbaikan sepeda motor', 'fa-motorcycle', 100000.00, 5, 1),
('Servis Mobil', 'servis-mobil', 'Perawatan dan perbaikan mobil', 'fa-car-side', 250000.00, 5, 2);

-- Providers / Penyedia Jasa (password: 'password')
INSERT INTO `providers` (`nama`, `nik`, `email`, `password`, `telepon`, `alamat`, `service_id`, `pengalaman`, `deskripsi`, `rating`, `total_review`, `total_order`, `status`) VALUES
('Budi Clean Service', '3201010101010001', 'budiclean@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200001111', 'Bekasi Utara', 1, 5, 'Spesialis pembersihan rumah dengan pengalaman 5 tahun menggunakan peralatan modern.', 4.5, 120, 245, 'aktif'),
('OfficeCare Solutions', '3201010101010002', 'officecare@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200002222', 'Jakarta Timur', 1, 8, 'Spesialis pembersihan kantor dengan standar kebersihan profesional.', 5.0, 85, 180, 'aktif'),
('ApartClean Team', '3201010101010003', 'apartclean@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200003333', 'Bekasi Selatan', 1, 3, 'Spesialis pembersihan apartemen dengan peralatan khusus untuk ruang terbatas.', 4.8, 63, 120, 'aktif'),
('Jaya Elektrik', '3201010101010004', 'jayaelektrik@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200004444', 'Cikarang', 2, 10, 'Teknisi listrik berpengalaman untuk segala kebutuhan instalasi dan perbaikan.', 4.7, 95, 200, 'aktif'),
('Pipa Sejahtera', '3201010101010005', 'pipasejahtera@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200005555', 'Tambun', 2, 7, 'Ahli plumbing dan perbaikan saluran air dengan garansi kerja.', 4.6, 78, 160, 'aktif'),
('AC Cool Master', '3201010101010006', 'acmaster@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200006666', 'Bekasi Barat', 2, 6, 'Teknisi AC bersertifikat untuk semua merek pendingin ruangan.', 4.9, 110, 230, 'aktif'),
('Bangun Jaya Kontraktor', '3201010101010007', 'bangunjaya@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200007777', 'Jakarta Utara', 3, 15, 'Kontraktor bangunan profesional untuk renovasi dan pembangunan baru.', 4.4, 45, 80, 'aktif'),
('Cat Indah Service', '3201010101010008', 'catindah@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200008888', 'Bekasi Timur', 3, 8, 'Jasa pengecatan profesional dengan bahan berkualitas tinggi.', 4.6, 67, 140, 'aktif'),
('Desain Kreatif Studio', '3201010101010009', 'desainkreatif@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200009999', 'Jakarta Selatan', 4, 12, 'Studio desain interior dengan portofolio rumah, apartemen, dan kantor.', 4.8, 55, 90, 'aktif'),
('Motor Sehat Bengkel', '3201010101010010', 'motorsehat@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200010101', 'Bekasi Kota', 5, 9, 'Bengkel motor panggilan untuk servis berkala dan perbaikan.', 4.5, 140, 320, 'aktif'),
('AutoFix Garage', '3201010101010011', 'autofix@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081200011111', 'Cikarang Barat', 5, 11, 'Mekanik mobil berpengalaman untuk servis di rumah Anda.', 4.7, 92, 175, 'aktif');

-- Orders / Pesanan
INSERT INTO `orders` (`kode_pesanan`, `user_id`, `provider_id`, `service_id`, `tanggal_layanan`, `waktu_layanan`, `alamat_layanan`, `deskripsi`, `total_harga`, `status`, `metode_bayar`, `status_bayar`) VALUES
('GS-20250401001', 1, 1, 1, '2025-04-05', '09:00:00', 'Jl. Harapan Indah Blok A No. 10, Bekasi', 'Bersih rumah 2 lantai lengkap', 350000.00, 'selesai', 'transfer', 'lunas'),
('GS-20250401002', 2, 4, 2, '2025-04-06', '10:00:00', 'Jl. Melati No. 5, Jakarta Timur', 'Perbaikan instalasi listrik dapur', 500000.00, 'selesai', 'tunai', 'lunas'),
('GS-20250402001', 3, 9, 4, '2025-04-10', '13:00:00', 'Jl. Kenanga No. 12, Bekasi Selatan', 'Konsultasi desain ruang tamu', 750000.00, 'selesai', 'transfer', 'lunas'),
('GS-20250403001', 1, 6, 2, '2025-04-12', '08:00:00', 'Jl. Harapan Indah Blok A No. 10, Bekasi', 'Servis AC 2 unit', 400000.00, 'selesai', 'ewallet', 'lunas'),
('GS-20250404001', 4, 10, 5, '2025-04-15', '14:00:00', 'Jl. Mawar No. 8, Cikarang', 'Servis motor Honda Beat - tune up lengkap', 250000.00, 'selesai', 'tunai', 'lunas'),
('GS-20250405001', 5, 2, 1, '2025-04-18', '09:30:00', 'Jl. Anggrek No. 3, Tambun', 'Bersih kantor kecil 3 ruangan', 450000.00, 'dalam_proses', 'transfer', 'lunas'),
('GS-20250406001', 2, 7, 3, '2025-04-20', '08:00:00', 'Jl. Melati No. 5, Jakarta Timur', 'Renovasi kamar mandi', 5500000.00, 'dikonfirmasi', 'transfer', 'belum'),
('GS-20250407001', 3, 11, 5, '2025-04-22', '10:00:00', 'Jl. Kenanga No. 12, Bekasi Selatan', 'Servis mobil Toyota Avanza', 600000.00, 'pending', 'tunai', 'belum'),
('GS-20250408001', 4, 3, 1, '2025-04-25', '11:00:00', 'Jl. Mawar No. 8, Cikarang', 'Bersih apartemen studio', 200000.00, 'pending', 'ewallet', 'belum'),
('GS-20250409001', 1, 8, 3, '2025-04-28', '09:00:00', 'Jl. Harapan Indah Blok A No. 10, Bekasi', 'Pengecatan ulang ruang tamu dan kamar', 2500000.00, 'dikonfirmasi', 'transfer', 'belum');

-- Reviews / Ulasan
INSERT INTO `reviews` (`order_id`, `user_id`, `provider_id`, `rating`, `komentar`) VALUES
(1, 1, 1, 5, 'Sangat puas dengan jasa kebersihan dari Budi Clean Service. Tukangnya profesional dan detail dalam membersihkan setiap sudut rumah saya.'),
(2, 2, 4, 4, 'Teknisi dari Jaya Elektrik berhasil memperbaiki instalasi listrik saya yang rusak hanya dalam waktu 1 jam. Harganya juga terjangkau.'),
(3, 3, 9, 5, 'Desain interior rumah saya dibuat sangat bagus oleh Desain Kreatif Studio. Mereka memahami gaya yang saya inginkan.'),
(4, 1, 6, 5, 'AC Cool Master sangat profesional. AC saya sekarang dingin lagi seperti baru.'),
(5, 4, 10, 4, 'Motor Sehat Bengkel datang tepat waktu dan servisnya sangat rapih. Recommended!');
