<?php
/**
 * INDEX.PHP - Entry Point
 * Redirect ke login atau dashboard
 */
require_once __DIR__ . '/helpers.php';

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
