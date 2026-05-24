<?php
/**
 * HEADER.PHP - Template Header & Sidebar
 * Bimbel Teman Juara
 */
if (!defined('PAGE_TITLE')) define('PAGE_TITLE', 'Dashboard');
$current_page = basename($_SERVER['PHP_SELF']);
$flash = get_flash();
$dark_mode = $_COOKIE['dark_mode'] ?? '0';
?>
<!DOCTYPE html>
<html lang="id" class="<?= $dark_mode === '1' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(PAGE_TITLE) ?> - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { 50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',300:'#93c5fd',400:'#60a5fa',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a' }
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <style>
        .sidebar-transition { transition: width 0.3s ease, transform 0.3s ease; }
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        .slide-up { animation: slideUp 0.3s ease-out; }
        .scale-in { animation: scaleIn 0.2s ease-out; }
        .slide-down { animation: slideDown 0.3s ease-out; }
        @keyframes fadeIn { from{opacity:0} to{opacity:1} }
        @keyframes slideUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        @keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
        @keyframes scaleIn { from{opacity:0;transform:scale(0.95)} to{opacity:1;transform:scale(1)} }
        .skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: skeleton 1.5s infinite; }
        .dark .skeleton { background: linear-gradient(90deg, #374151 25%, #4b5563 50%, #374151 75%); background-size: 200% 100%; }
        @keyframes skeleton { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
        .btn-press:active { transform: scale(0.95); }
        .modal-backdrop { backdrop-filter: blur(4px); }
        .table-row-enter { animation: tableRowEnter 0.4s ease-out; }
        @keyframes tableRowEnter { from{opacity:0;transform:translateX(-20px)} to{opacity:1;transform:translateX(0)} }
        .hover-elevate { transition: transform 0.2s, box-shadow 0.2s; }
        .hover-elevate:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex">
