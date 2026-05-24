<?php
/**
 * LOGOUT.PHP - Proses Logout
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';

if (is_logged_in()) {
    log_activity('LOGOUT', 'User ' . ($_SESSION['user_nama'] ?? '') . ' logout');
}

// Hapus semua session
$_SESSION = [];

// Hapus session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Redirect ke login
header("Location: " . BASE_URL . "login.php");
exit;
