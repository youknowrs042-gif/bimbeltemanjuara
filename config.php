<?php
/**
 * ============================================================
 * CONFIG.PHP - Bimbel Teman Juara
 * Koneksi Database, Konstanta, Session
 * ============================================================
 */

// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// KONSTANTA APLIKASI
// ============================================================
define('APP_NAME', 'Bimbel Teman Juara');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://app.bimbeltemanjuara.com/');

// ============================================================
// KONFIGURASI DATABASE
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'bimbelt1_app');
define('DB_USER', 'bimbelt1_appuser');
define('DB_PASS', 'Sadasa123!');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// KONFIGURASI UPLOAD
// ============================================================
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', 500 * 1024 * 1024); // 500MB

// ============================================================
// KONFIGURASI EMAIL (PHPMailer)
// ============================================================
define('MAIL_HOST', 'mail.bimbeltemanjuara.com');
define('MAIL_PORT', 465);
define('MAIL_USERNAME', 'noreply@bimbeltemanjuara.com');
define('MAIL_PASSWORD', '');
define('MAIL_FROM_NAME', 'Bimbel Teman Juara');

// ============================================================
// KONEKSI DATABASE (MySQLi)
// ============================================================
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die('<div style="text-align:center;padding:50px;font-family:sans-serif;">
        <h2>Koneksi Database Gagal</h2>
        <p>Silakan periksa konfigurasi database.</p>
    </div>');
}

mysqli_set_charset($conn, DB_CHARSET);
mysqli_query($conn, "SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

// ============================================================
// ZONA WAKTU
// ============================================================
date_default_timezone_set('Asia/Jakarta');
