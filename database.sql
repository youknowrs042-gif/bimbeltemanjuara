-- ============================================================
-- DATABASE: bimbelt1_lmsmei
-- Bimbel Teman Juara - LMS & Manajemen Bimbel
-- Full Schema
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- TABEL: users
-- ============================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('ADMIN','TUTOR','SISWA','ORANG_TUA') NOT NULL DEFAULT 'SISWA',
    `jk` ENUM('L','P') DEFAULT NULL,
    `no_hp` VARCHAR(20) DEFAULT NULL,
    `alamat` TEXT DEFAULT NULL,
    `foto` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('AKTIF','NONAKTIF','TERKUNCI') NOT NULL DEFAULT 'AKTIF',
    `last_login` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: siswa (detail khusus siswa)
-- ============================================================
DROP TABLE IF EXISTS `siswa`;
CREATE TABLE `siswa` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `sekolah` VARCHAR(200) DEFAULT NULL,
    `kelas` VARCHAR(50) DEFAULT NULL,
    `jenjang` ENUM('PRA_SD','SD','SMP','SMA') NOT NULL DEFAULT 'SD',
    `nama_ortu` VARCHAR(150) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_jenjang` (`jenjang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: tutor (detail khusus tutor)
-- ============================================================
DROP TABLE IF EXISTS `tutor`;
CREATE TABLE `tutor` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `domisili` VARCHAR(200) DEFAULT NULL,
    `pendidikan` VARCHAR(200) DEFAULT NULL,
    `mapel` VARCHAR(255) DEFAULT NULL,
    `nama_bank` VARCHAR(100) DEFAULT NULL,
    `no_rekening` VARCHAR(50) DEFAULT NULL,
    `atas_nama_rek` VARCHAR(150) DEFAULT NULL,
    `tarif_per_sesi` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: orang_tua (detail khusus orang tua)
-- ============================================================
DROP TABLE IF EXISTS `orang_tua`;
CREATE TABLE `orang_tua` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `pekerjaan` VARCHAR(200) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: pairing_ortu_siswa (relasi orang tua - anak)
-- ============================================================
DROP TABLE IF EXISTS `pairing_ortu_siswa`;
CREATE TABLE `pairing_ortu_siswa` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ortu_user_id` INT UNSIGNED NOT NULL,
    `siswa_user_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ortu_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`siswa_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_pair` (`ortu_user_id`, `siswa_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: program (Private, Klasikal, Semi-Private, Online)
-- ============================================================
DROP TABLE IF EXISTS `program`;
CREATE TABLE `program` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama_program` VARCHAR(100) NOT NULL,
    `tipe` ENUM('KLASIKAL','PRIVATE','SEMI_PRIVATE','ONLINE') NOT NULL,
    `deskripsi` TEXT DEFAULT NULL,
    `status` ENUM('AKTIF','NONAKTIF') NOT NULL DEFAULT 'AKTIF',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: paket (Paket A=4, B=8, C=12 pertemuan)
-- ============================================================
DROP TABLE IF EXISTS `paket`;
CREATE TABLE `paket` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama_paket` VARCHAR(50) NOT NULL,
    `jumlah_pertemuan` INT NOT NULL DEFAULT 4,
    `status` ENUM('AKTIF','NONAKTIF') NOT NULL DEFAULT 'AKTIF',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: harga_paket (Harga kombinasi: program + jenjang + paket)
-- ============================================================
DROP TABLE IF EXISTS `harga_paket`;
CREATE TABLE `harga_paket` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `program_id` INT UNSIGNED NOT NULL,
    `jenjang` ENUM('PRA_SD','SD','SMP','SMA') NOT NULL,
    `paket_id` INT UNSIGNED NOT NULL,
    `harga` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`program_id`) REFERENCES `program`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`paket_id`) REFERENCES `paket`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_harga` (`program_id`, `jenjang`, `paket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: enrolment (Pendaftaran siswa ke program+paket)
-- ============================================================
DROP TABLE IF EXISTS `enrolment`;
CREATE TABLE `enrolment` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `siswa_user_id` INT UNSIGNED NOT NULL,
    `program_id` INT UNSIGNED NOT NULL,
    `paket_id` INT UNSIGNED NOT NULL,
    `tutor_user_id` INT UNSIGNED DEFAULT NULL,
    `jenjang` ENUM('PRA_SD','SD','SMP','SMA') NOT NULL,
    `mapel` VARCHAR(100) DEFAULT NULL,
    `kuota_awal` INT NOT NULL DEFAULT 4,
    `kuota_sisa` INT NOT NULL DEFAULT 4,
    `status` ENUM('AKTIF','SELESAI','BATAL') NOT NULL DEFAULT 'AKTIF',
    `tanggal_mulai` DATE DEFAULT NULL,
    `tanggal_selesai` DATE DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`siswa_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`program_id`) REFERENCES `program`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`paket_id`) REFERENCES `paket`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tutor_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_siswa` (`siswa_user_id`),
    INDEX `idx_tutor` (`tutor_user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: pembayaran (Invoice & payment tracking)
-- ============================================================
DROP TABLE IF EXISTS `pembayaran`;
CREATE TABLE `pembayaran` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `enrolment_id` INT UNSIGNED NOT NULL,
    `no_invoice` VARCHAR(20) NOT NULL UNIQUE,
    `siswa_user_id` INT UNSIGNED NOT NULL,
    `jumlah` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `status` ENUM('BELUM_BAYAR','MENUNGGU_KONFIRMASI','LUNAS','BATAL') NOT NULL DEFAULT 'BELUM_BAYAR',
    `metode_bayar` VARCHAR(100) DEFAULT NULL,
    `bukti_transfer` VARCHAR(255) DEFAULT NULL,
    `catatan` TEXT DEFAULT NULL,
    `tanggal_bayar` DATE DEFAULT NULL,
    `confirmed_by` INT UNSIGNED DEFAULT NULL,
    `confirmed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`enrolment_id`) REFERENCES `enrolment`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`siswa_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`confirmed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_siswa` (`siswa_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: presensi (Absensi per sesi)
-- ============================================================
DROP TABLE IF EXISTS `presensi`;
CREATE TABLE `presensi` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `enrolment_id` INT UNSIGNED NOT NULL,
    `siswa_user_id` INT UNSIGNED NOT NULL,
    `tutor_user_id` INT UNSIGNED NOT NULL,
    `tanggal` DATE NOT NULL,
    `jam_mulai` TIME DEFAULT NULL,
    `jam_selesai` TIME DEFAULT NULL,
    `status` ENUM('HADIR','TIDAK_HADIR','IZIN','TANPA_KET','RESCHEDULE') NOT NULL DEFAULT 'HADIR',
    `catatan` TEXT DEFAULT NULL,
    `foto_bukti` VARCHAR(255) DEFAULT NULL,
    `lokasi` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`enrolment_id`) REFERENCES `enrolment`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`siswa_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tutor_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_tanggal` (`tanggal`),
    INDEX `idx_siswa` (`siswa_user_id`),
    INDEX `idx_tutor` (`tutor_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: gaji_tutor (Rekap gaji bulanan)
-- ============================================================
DROP TABLE IF EXISTS `gaji_tutor`;
CREATE TABLE `gaji_tutor` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tutor_user_id` INT UNSIGNED NOT NULL,
    `bulan` INT NOT NULL,
    `tahun` INT NOT NULL,
    `total_sesi` INT NOT NULL DEFAULT 0,
    `tarif_per_sesi` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_gaji` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `status` ENUM('DRAFT','DISETUJUI','DIBAYAR') NOT NULL DEFAULT 'DRAFT',
    `catatan` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tutor_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_gaji` (`tutor_user_id`, `bulan`, `tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: bank_soal (Soal & Modul)
-- ============================================================
DROP TABLE IF EXISTS `bank_soal`;
CREATE TABLE `bank_soal` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `judul` VARCHAR(255) NOT NULL,
    `tipe` ENUM('SOAL_MCQ','MODUL_PDF','MODUL_HTML') NOT NULL DEFAULT 'SOAL_MCQ',
    `jenjang` ENUM('PRA_SD','SD','SMP','SMA') DEFAULT NULL,
    `mapel` VARCHAR(100) DEFAULT NULL,
    `konten_html` LONGTEXT DEFAULT NULL,
    `file_path` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('MENUNGGU_VALIDASI','DISETUJUI','DITOLAK') NOT NULL DEFAULT 'MENUNGGU_VALIDASI',
    `created_by` INT UNSIGNED NOT NULL,
    `approved_by` INT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_tipe` (`tipe`),
    INDEX `idx_status` (`status`),
    INDEX `idx_jenjang` (`jenjang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: soal (Detail soal MCQ)
-- ============================================================
DROP TABLE IF EXISTS `soal`;
CREATE TABLE `soal` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `bank_soal_id` INT UNSIGNED NOT NULL,
    `pertanyaan` TEXT NOT NULL,
    `opsi_a` TEXT NOT NULL,
    `opsi_b` TEXT NOT NULL,
    `opsi_c` TEXT NOT NULL,
    `opsi_d` TEXT NOT NULL,
    `opsi_e` TEXT DEFAULT NULL,
    `jawaban_benar` ENUM('A','B','C','D','E') NOT NULL,
    `pembahasan` TEXT DEFAULT NULL,
    `urutan` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bank_soal_id`) REFERENCES `bank_soal`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: pr (Pekerjaan Rumah / Kuis)
-- ============================================================
DROP TABLE IF EXISTS `pr`;
CREATE TABLE `pr` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `judul` VARCHAR(255) NOT NULL,
    `deskripsi` TEXT DEFAULT NULL,
    `bank_soal_id` INT UNSIGNED NOT NULL,
    `jenjang` ENUM('PRA_SD','SD','SMP','SMA') DEFAULT NULL,
    `mapel` VARCHAR(100) DEFAULT NULL,
    `deadline` DATETIME DEFAULT NULL,
    `status` ENUM('DRAFT','MENUNGGU_VALIDASI','PUBLISHED','SELESAI') NOT NULL DEFAULT 'DRAFT',
    `created_by` INT UNSIGNED NOT NULL,
    `approved_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bank_soal_id`) REFERENCES `bank_soal`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: pr_assignment (PR di-assign ke siswa tertentu)
-- ============================================================
DROP TABLE IF EXISTS `pr_assignment`;
CREATE TABLE `pr_assignment` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pr_id` INT UNSIGNED NOT NULL,
    `siswa_user_id` INT UNSIGNED NOT NULL,
    `status` ENUM('BELUM_DIKERJAKAN','SUDAH_DIKERJAKAN','DINILAI') NOT NULL DEFAULT 'BELUM_DIKERJAKAN',
    `nilai` DECIMAL(5,2) DEFAULT NULL,
    `komentar_tutor` TEXT DEFAULT NULL,
    `submitted_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`pr_id`) REFERENCES `pr`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`siswa_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_pr_siswa` (`pr_id`, `siswa_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: pr_jawaban (Jawaban siswa untuk PR)
-- ============================================================
DROP TABLE IF EXISTS `pr_jawaban`;
CREATE TABLE `pr_jawaban` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pr_assignment_id` INT UNSIGNED NOT NULL,
    `soal_id` INT UNSIGNED NOT NULL,
    `jawaban` ENUM('A','B','C','D','E') DEFAULT NULL,
    `is_benar` TINYINT(1) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`pr_assignment_id`) REFERENCES `pr_assignment`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`soal_id`) REFERENCES `soal`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: tryout (Try Out Online)
-- ============================================================
DROP TABLE IF EXISTS `tryout`;
CREATE TABLE `tryout` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `judul` VARCHAR(255) NOT NULL,
    `deskripsi` TEXT DEFAULT NULL,
    `bank_soal_id` INT UNSIGNED NOT NULL,
    `jenjang` ENUM('PRA_SD','SD','SMP','SMA') DEFAULT NULL,
    `mapel` VARCHAR(100) DEFAULT NULL,
    `durasi_menit` INT NOT NULL DEFAULT 60,
    `randomize_soal` TINYINT(1) NOT NULL DEFAULT 1,
    `randomize_opsi` TINYINT(1) NOT NULL DEFAULT 1,
    `max_attempt` INT NOT NULL DEFAULT 1,
    `status` ENUM('DRAFT','PUBLISHED','SELESAI') NOT NULL DEFAULT 'DRAFT',
    `tanggal_mulai` DATETIME DEFAULT NULL,
    `tanggal_selesai` DATETIME DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bank_soal_id`) REFERENCES `bank_soal`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: tryout_attempt (Percobaan siswa)
-- ============================================================
DROP TABLE IF EXISTS `tryout_attempt`;
CREATE TABLE `tryout_attempt` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tryout_id` INT UNSIGNED NOT NULL,
    `siswa_user_id` INT UNSIGNED NOT NULL,
    `attempt_ke` INT NOT NULL DEFAULT 1,
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finished_at` DATETIME DEFAULT NULL,
    `nilai` DECIMAL(5,2) DEFAULT NULL,
    `total_benar` INT DEFAULT 0,
    `total_soal` INT DEFAULT 0,
    `status` ENUM('BERLANGSUNG','SELESAI','TIMEOUT') NOT NULL DEFAULT 'BERLANGSUNG',
    `izin_ulang` TINYINT(1) NOT NULL DEFAULT 0,
    `soal_order` TEXT DEFAULT NULL COMMENT 'JSON array urutan soal',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tryout_id`) REFERENCES `tryout`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`siswa_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_tryout_siswa` (`tryout_id`, `siswa_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: tryout_jawaban (Jawaban per soal di tryout)
-- ============================================================
DROP TABLE IF EXISTS `tryout_jawaban`;
CREATE TABLE `tryout_jawaban` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `attempt_id` INT UNSIGNED NOT NULL,
    `soal_id` INT UNSIGNED NOT NULL,
    `jawaban` ENUM('A','B','C','D','E') DEFAULT NULL,
    `is_benar` TINYINT(1) DEFAULT NULL,
    `answered_at` DATETIME DEFAULT NULL,
    FOREIGN KEY (`attempt_id`) REFERENCES `tryout_attempt`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`soal_id`) REFERENCES `soal`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_attempt_soal` (`attempt_id`, `soal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: activity_log
-- ============================================================
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: file_uploads (Metadata file upload)
-- ============================================================
DROP TABLE IF EXISTS `file_uploads`;
CREATE TABLE `file_uploads` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT NOT NULL DEFAULT 0,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `kategori` VARCHAR(50) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: password_resets
-- ============================================================
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(100) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- INSERT DATA AWAL
-- ============================================================

-- Admin default (password: Admin123!)
-- Hash generated with: password_hash('Admin123!', PASSWORD_BCRYPT, ['cost' => 10])
INSERT INTO `users` (`nama`, `email`, `username`, `password`, `role`, `status`) VALUES
('Administrator', 'admin@bimbeltemanjuara.com', 'admin', '$2y$10$GBI/APvP5N4LW2bSpWrWCuGX5g8aqQBcnkA.CpUTT5lPjB2FqlAJS', 'ADMIN', 'AKTIF');

-- Program default
INSERT INTO `program` (`nama_program`, `tipe`, `deskripsi`) VALUES
('Private', 'PRIVATE', 'Program les private 1 tutor 1 siswa (home visit)'),
('Klasikal', 'KLASIKAL', 'Program klasikal 1 tutor banyak siswa di kelas'),
('Semi-Private', 'SEMI_PRIVATE', 'Program semi-private 1 tutor beberapa siswa (visit)'),
('Online', 'ONLINE', 'Program online via meeting');

-- Paket default
INSERT INTO `paket` (`nama_paket`, `jumlah_pertemuan`) VALUES
('Paket A', 4),
('Paket B', 8),
('Paket C', 12);
