<?php
/**
 * DASHBOARD.PHP - Halaman Utama setelah login
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();

// Cek lock siswa
if (check_siswa_lock()) {
    $page_locked = true;
}

define('PAGE_TITLE', 'Dashboard');
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/sidebar_html.php';

$role = current_role();
$user_id = current_user_id();
?>

<?php if (!empty($page_locked)): ?>
<!-- LOCKED STATE untuk siswa belum bayar -->
<div class="flex flex-col items-center justify-center py-16 fade-in">
    <div class="w-20 h-20 bg-yellow-100 dark:bg-yellow-900/30 rounded-full flex items-center justify-center mb-6">
        <svg class="w-10 h-10 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
    </div>
    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Akun Terkunci</h3>
    <p class="text-gray-500 dark:text-gray-400 text-center max-w-md mb-6">
        Akun terkunci, silakan bayar paket terlebih dahulu. Hubungi admin untuk informasi pembayaran.
    </p>
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 max-w-md">
        <h4 class="font-semibold text-blue-700 dark:text-blue-300 text-sm mb-2">Informasi Pembayaran:</h4>
        <p class="text-sm text-blue-600 dark:text-blue-400">
            Transfer ke rekening yang tertera pada invoice Anda, kemudian konfirmasi ke admin melalui WhatsApp.
        </p>
    </div>
</div>

<?php else: ?>
<!-- DASHBOARD CONTENT berdasarkan role -->
<div class="space-y-6 fade-in">
    <!-- Welcome Card -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-6 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-xl md:text-2xl font-bold mb-1">Halo, <?= e($_SESSION['user_nama']) ?>! 👋</h3>
                <p class="text-blue-100 text-sm">
                    <?php
                    $hour = (int)date('H');
                    if ($hour < 12) echo 'Selamat Pagi';
                    elseif ($hour < 15) echo 'Selamat Siang';
                    elseif ($hour < 18) echo 'Selamat Sore';
                    else echo 'Selamat Malam';
                    ?> - <?= format_tanggal(date('Y-m-d')) ?>
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <span class="inline-block px-3 py-1 bg-white/20 rounded-full text-sm font-medium"><?= e($role) ?></span>
            </div>
        </div>
    </div>

    <?php if ($role === 'ADMIN'): ?>
    <!-- ADMIN DASHBOARD -->
    <?php
    $total_siswa = db_count("SELECT COUNT(*) FROM users WHERE role = 'SISWA' AND status != 'NONAKTIF'");
    $total_tutor = db_count("SELECT COUNT(*) FROM users WHERE role = 'TUTOR' AND status != 'NONAKTIF'");
    $total_ortu = db_count("SELECT COUNT(*) FROM users WHERE role = 'ORANG_TUA' AND status != 'NONAKTIF'");
    $total_users = db_count("SELECT COUNT(*) FROM users WHERE status != 'NONAKTIF'");
    ?>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white" data-counter="<?= $total_siswa ?>">0</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Siswa</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white" data-counter="<?= $total_tutor ?>">0</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Tutor</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white" data-counter="<?= $total_ortu ?>">0</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Orang Tua</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white" data-counter="<?= $total_users ?>">0</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Users</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h4 class="font-semibold text-gray-800 dark:text-white mb-4">Aktivitas Terbaru</h4>
        <?php
        $logs = db_fetch_all("SELECT al.*, u.nama FROM activity_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 10");
        ?>
        <?php if (empty($logs)): ?>
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada aktivitas.</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($logs as $log): ?>
            <div class="flex items-start gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                <div class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-bold text-gray-500"><?= strtoupper(substr($log['nama'] ?? 'S', 0, 1)) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-700 dark:text-gray-300"><span class="font-medium"><?= e($log['nama'] ?? 'System') ?></span> - <?= e($log['action']) ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><?= e($log['description'] ?? '') ?></p>
                </div>
                <span class="text-xs text-gray-400 whitespace-nowrap"><?= format_tanggal($log['created_at'], true) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif ($role === 'TUTOR'): ?>
    <!-- TUTOR DASHBOARD -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Siswa Saya</h4>
            <p class="text-sm text-gray-500 dark:text-gray-400">Fitur ini akan menampilkan daftar siswa yang Anda ajar.</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Jadwal Hari Ini</h4>
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada jadwal untuk hari ini.</p>
        </div>
    </div>

    <?php elseif ($role === 'SISWA'): ?>
    <!-- SISWA DASHBOARD -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h4 class="font-semibold text-gray-800 dark:text-white mb-2">PR Aktif</h4>
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada PR yang harus dikerjakan.</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Try Out Tersedia</h4>
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada try out yang tersedia.</p>
        </div>
    </div>

    <?php elseif ($role === 'ORANG_TUA'): ?>
    <!-- ORANG TUA DASHBOARD -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Data Anak</h4>
        <p class="text-sm text-gray-500 dark:text-gray-400">Silakan lihat perkembangan belajar anak Anda melalui menu di sidebar.</p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
