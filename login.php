<?php
/**
 * LOGIN.PHP - Halaman Login
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';

// Jika sudah login, redirect ke dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Cek flash message
$flash = get_flash();
if ($flash) {
    if ($flash['type'] === 'success') $success = $flash['message'];
    else $error = $flash['message'];
}

// Proses Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';
    
    // Validasi CSRF
    if (!verify_csrf($csrf)) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        // Cari user berdasarkan username atau email
        $sql = "SELECT * FROM users WHERE (username = ? OR email = ?) AND status != 'NONAKTIF' LIMIT 1";
        $user = db_fetch_one($sql, [$username, $username]);
        
        if ($user && password_verify($password, $user['password'])) {
            // Login berhasil - set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nama'] = $user['nama'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_status'] = $user['status'];
            $_SESSION['user_foto'] = $user['foto'];
            
            // Regenerate session ID untuk keamanan
            session_regenerate_id(true);
            
            // Update last login
            db_query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']], 'i');
            
            // Log aktivitas
            log_activity('LOGIN', 'User ' . $user['nama'] . ' berhasil login');
            
            // Regenerate CSRF token
            unset($_SESSION['csrf_token']);
            
            redirect('dashboard.php');
        } else {
            $error = 'Username atau password salah.';
            log_activity('LOGIN_FAILED', 'Percobaan login gagal untuk: ' . $username);
        }
    }
}

$dark_mode = $_COOKIE['dark_mode'] ?? '0';
?>
<!DOCTYPE html>
<html lang="id" class="<?= $dark_mode === '1' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary: { 50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',300:'#93c5fd',400:'#60a5fa',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a' } } } }
        }
    </script>
    <style>
        .fade-in { animation: fadeIn 0.5s ease-in-out; }
        .slide-up { animation: slideUp 0.5s ease-out; }
        @keyframes fadeIn { from{opacity:0} to{opacity:1} }
        @keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        .btn-press:active { transform: scale(0.97); }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md slide-up">
        <!-- Logo & Branding -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl shadow-lg mb-4">
                <span class="text-white font-bold text-2xl">B</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white"><?= APP_NAME ?></h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Learning Management System</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-8">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-6">Masuk ke Akun</h2>

            <!-- Error Message -->
            <?php if ($error): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800 text-sm flex items-center gap-2 fade-in">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?= e($error) ?>
            </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if ($success): ?>
            <div class="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800 text-sm flex items-center gap-2 fade-in">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?= e($success) ?>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" id="loginForm">
                <?= csrf_field() ?>
                
                <div class="space-y-4">
                    <!-- Username -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username atau Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <input type="text" id="username" name="username" required autocomplete="username"
                                   value="<?= e($_POST['username'] ?? '') ?>"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                   placeholder="Masukkan username atau email">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input type="password" id="password" name="password" required autocomplete="current-password"
                                   class="w-full pl-10 pr-12 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                   placeholder="Masukkan password">
                            <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <svg id="eyeIcon" class="w-5 h-5 text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" id="loginBtn"
                        class="w-full mt-6 py-3 px-4 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/25 transition-all duration-200 btn-press text-sm">
                    Masuk
                </button>
            </form>
        </div>

        <!-- Footer -->
        <p class="text-center text-xs text-gray-500 dark:text-gray-400 mt-6">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> - All rights reserved.
        </p>

        <!-- Dark Mode Toggle -->
        <div class="text-center mt-4">
            <button onclick="toggleDarkMode()" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                <span class="dark:hidden">Mode Gelap</span>
                <span class="hidden dark:inline">Mode Terang</span>
            </button>
        </div>
    </div>

    <script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
        } else {
            input.type = 'password';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
        }
    }

    function toggleDarkMode() {
        const html = document.documentElement;
        html.classList.toggle('dark');
        const isDark = html.classList.contains('dark') ? '1' : '0';
        document.cookie = `dark_mode=${isDark};path=/;max-age=31536000;SameSite=Lax`;
    }

    // Loading button on form submit
    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('loginBtn');
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Memproses...';
    });
    </script>
</body>
</html>
