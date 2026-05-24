-- ============================================================
-- DATABASE: bimbelt1_app
-- Bimbel Teman Juara - LMS & Manajemen Bimbel
-- ============================================================

CREATE DATABASE IF NOT EXISTS `bimbelt1_app` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bimbelt1_app`;

-- ============================================================
-- TABEL: users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
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
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: siswa (detail khusus siswa)
-- ============================================================
CREATE TABLE IF NOT EXISTS `siswa` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `sekolah` VARCHAR(200) DEFAULT NULL,
    `kelas` VARCHAR(50) DEFAULT NULL,
    `jenjang` ENUM('PRA_SD','SD','SMP','SMA') NOT NULL DEFAULT 'SD',
    `nama_ortu` VARCHAR(150) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: tutor (detail khusus tutor)
-- ============================================================
CREATE TABLE IF NOT EXISTS `tutor` (
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
CREATE TABLE IF NOT EXISTS `orang_tua` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `pekerjaan` VARCHAR(200) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: pairing_ortu_siswa (relasi orang tua - anak)
-- ============================================================
CREATE TABLE IF NOT EXISTS `pairing_ortu_siswa` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ortu_user_id` INT UNSIGNED NOT NULL,
    `siswa_user_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ortu_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`siswa_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_pair` (`ortu_user_id`, `siswa_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: activity_log
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: csrf_tokens
-- ============================================================
CREATE TABLE IF NOT EXISTS `csrf_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token` VARCHAR(64) NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token` (`token`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INSERT DEFAULT ADMIN
-- Password: Admin123!
-- ============================================================
INSERT INTO `users` (`nama`, `email`, `username`, `password`, `role`, `status`) VALUES
('Administrator', 'admin@bimbeltemanjuara.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN', 'AKTIF');
