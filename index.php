<?php
/**
 * INDEX.PHP - Entry Point
 * Redirect ke dashboard jika sudah login, ke login jika belum
 */
require_once __DIR__ . '/helpers.php';

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
