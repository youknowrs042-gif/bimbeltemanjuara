<?php
/**
 * DASHBOARD.PHP - Halaman Utama setelah Login
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();

define('PAGE_TITLE', 'Dashboard');

$role = current_role();
$user_id = current_user_id();

// ============================================================
// DATA DASHBOARD BERDASARKAN ROLE
// ============================================================

if ($role === 'ADMIN') {
    $total_siswa = db_count("SELECT COUNT(*) FROM users WHERE role = 'SISWA' AND status != 'NONAKTIF'");
    $total_tutor = db_count("SELECT COUNT(*) FROM users WHERE role = 'TUTOR' AND status != 'NONAKTIF'");
    $total_enrolment_aktif = db_count("SELECT COUNT(*) FROM enrolment WHERE status = 'AKTIF'");
    $total_pembayaran_pending = db_count("SELECT COUNT(*) FROM pembayaran WHERE status IN ('BELUM_BAYAR','MENUNGGU_KONFIRMASI')");
    $total_pendapatan_bulan = db_fetch_one("SELECT COALESCE(SUM(jumlah),0) as total FROM pembayaran WHERE status = 'LUNAS' AND MONTH(tanggal_bayar) = MONTH(NOW()) AND YEAR(tanggal_bayar) = YEAR(NOW())");
    $pendapatan_bulan = (float)($total_pendapatan_bulan['total'] ?? 0);
    
    // Data untuk grafik pendapatan 6 bulan terakhir
    $grafik_pendapatan = db_fetch_all("
        SELECT DATE_FORMAT(tanggal_bayar, '%Y-%m') as bulan, SUM(jumlah) as total 
        FROM pembayaran 
        WHERE status = 'LUNAS' AND tanggal_bayar >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
        GROUP BY DATE_FORMAT(tanggal_bayar, '%Y-%m') 
        ORDER BY bulan ASC
    ");
    
    // Aktivitas terbaru
    $recent_activity = db_fetch_all("
        SELECT al.*, u.nama as user_nama 
        FROM activity_log al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC LIMIT 10
    ");

} elseif ($role === 'TUTOR') {
    $total_siswa_saya = db_count("SELECT COUNT(DISTINCT siswa_user_id) FROM enrolment WHERE tutor_user_id = ? AND status = 'AKTIF'", [$user_id], 'i');
    $total_sesi_bulan = db_count("SELECT COUNT(*) FROM presensi WHERE tutor_user_id = ? AND MONTH(tanggal) = MONTH(NOW()) AND YEAR(tanggal) = YEAR(NOW()) AND status = 'HADIR'", [$user_id], 'i');
    $enrolments_aktif = db_count("SELECT COUNT(*) FROM enrolment WHERE tutor_user_id = ? AND status = 'AKTIF'", [$user_id], 'i');
    
    // Jadwal hari ini
    $presensi_hari_ini = db_fetch_all("
        SELECT p.*, u.nama as siswa_nama, pr.nama_program
        FROM presensi p
        JOIN users u ON p.siswa_user_id = u.id
        JOIN enrolment e ON p.enrolment_id = e.id
        JOIN program pr ON e.program_id = pr.id
        WHERE p.tutor_user_id = ? AND p.tanggal = CURDATE()
        ORDER BY p.jam_mulai ASC
    ", [$user_id], 'i');

} elseif ($role === 'SISWA') {
    // Cek paket aktif
    $paket_aktif = db_fetch_all("
        SELECT e.*, pr.nama_program, pk.nama_paket, u.nama as tutor_nama
        FROM enrolment e
        JOIN program pr ON e.program_id = pr.id
        JOIN paket pk ON e.paket_id = pk.id
        LEFT JOIN users u ON e.tutor_user_id = u.id
        WHERE e.siswa_user_id = ? AND e.status = 'AKTIF'
    ", [$user_id], 'i');
    
    $total_pr_pending = db_count("SELECT COUNT(*) FROM pr_assignment WHERE siswa_user_id = ? AND status = 'BELUM_DIKERJAKAN'", [$user_id], 'i');
    $total_tryout_tersedia = db_count("
        SELECT COUNT(*) FROM tryout 
        WHERE status = 'PUBLISHED' 
        AND (tanggal_mulai IS NULL OR tanggal_mulai <= NOW())
        AND (tanggal_selesai IS NULL OR tanggal_selesai >= NOW())
    ");

} elseif ($role === 'ORANG_TUA') {
    // Data anak-anak
    $anak_list = db_fetch_all("
        SELECT u.id, u.nama, u.status, s.jenjang, s.kelas, s.sekolah
        FROM pairing_ortu_siswa pos
        JOIN users u ON pos.siswa_user_id = u.id
        LEFT JOIN siswa s ON u.id = s.user_id
        WHERE pos.ortu_user_id = ?
    ", [$user_id], 'i');
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/sidebar_html.php';
?>

<!-- ============================================================ -->
<!-- DASHBOARD CONTENT -->
<!-- ============================================================ -->

<?php if ($role === 'ADMIN'): ?>
<!-- ADMIN DASHBOARD -->
<div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Siswa -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 hover-elevate">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Siswa</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1" data-counter="<?= $total_siswa ?>">0</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Tutor -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 hover-elevate">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Tutor</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1" data-counter="<?= $total_tutor ?>">0</p>
                </div>
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Enrolment Aktif -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 hover-elevate">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Enrolment Aktif</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1" data-counter="<?= $total_enrolment_aktif ?>">0</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pendapatan Bulan Ini -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 hover-elevate">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pendapatan Bulan Ini</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-white mt-1"><?= format_rupiah($pendapatan_bulan) ?></p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert: Pembayaran Pending -->
    <?php if ($total_pembayaran_pending > 0): ?>
    <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-xl p-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-orange-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
        </svg>
        <p class="text-sm text-orange-700 dark:text-orange-300">
            <strong><?= $total_pembayaran_pending ?></strong> pembayaran menunggu konfirmasi.
            <a href="<?= BASE_URL ?>pembayaran.php" class="underline font-medium">Lihat</a>
        </p>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Grafik Pendapatan -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-800 dark:text-white mb-4">Pendapatan 6 Bulan Terakhir</h3>
            <canvas id="chartPendapatan" height="200"></canvas>
        </div>

        <!-- Aktivitas Terbaru -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-800 dark:text-white mb-4">Aktivitas Terbaru</h3>
            <div class="space-y-3 max-h-[300px] overflow-y-auto">
                <?php if (empty($recent_activity)): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada aktivitas.</p>
                <?php else: ?>
                    <?php foreach ($recent_activity as $act): ?>
                    <div class="flex items-start gap-3 text-sm">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-1.5 flex-shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-gray-700 dark:text-gray-300 truncate">
                                <strong><?= e($act['user_nama'] ?? 'System') ?></strong> - <?= e($act['action']) ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= format_tanggal($act['created_at'], true) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Chart Pendapatan
const ctx = document.getElementById('chartPendapatan');
if (ctx) {
    const labels = <?= json_encode(array_column($grafik_pendapatan, 'bulan')) ?>;
    const data = <?= json_encode(array_map(fn($r) => (float)$r['total'], $grafik_pendapatan)) ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Pendapatan (Rp)',
                data: data,
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + v.toLocaleString('id-ID') } }
            }
        }
    });
}
</script>

<?php elseif ($role === 'TUTOR'): ?>
<!-- TUTOR DASHBOARD -->
<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 hover-elevate">
            <p class="text-sm text-gray-500 dark:text-gray-400">Siswa Saya</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1" data-counter="<?= $total_siswa_saya ?>">0</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 hover-elevate">
            <p class="text-sm text-gray-500 dark:text-gray-400">Sesi Bulan Ini</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1" data-counter="<?= $total_sesi_bulan ?>">0</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 hover-elevate">
            <p class="text-sm text-gray-500 dark:text-gray-400">Enrolment Aktif</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1" data-counter="<?= $enrolments_aktif ?>">0</p>
        </div>
    </div>

    <!-- Jadwal Hari Ini -->
    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-800 dark:text-white mb-4">Jadwal Hari Ini</h3>
        <?php if (empty($presensi_hari_ini)): ?>
            <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada jadwal hari ini.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($presensi_hari_ini as $jadwal): ?>
                <div class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <div class="text-center">
                        <p class="text-sm font-bold text-blue-600 dark:text-blue-400"><?= e($jadwal['jam_mulai'] ? substr($jadwal['jam_mulai'], 0, 5) : '--:--') ?></p>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800 dark:text-white"><?= e($jadwal['siswa_nama']) ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= e($jadwal['nama_program']) ?></p>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full <?= $jadwal['status'] === 'HADIR' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300' ?>">
                        <?= e($jadwal['status']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($role === 'SISWA'): ?>
<!-- SISWA DASHBOARD -->
<div class="space-y-6">
    <?php if (!check_siswa_lock()): ?>
    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 hover-elevate">
            <p class="text-sm text-gray-500 dark:text-gray-400">Paket Aktif</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1" data-counter="<?= count($paket_aktif) ?>">0</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 hover-elevate">
            <p class="text-sm text-gray-500 dark:text-gray-400">PR Belum Dikerjakan</p>
            <p class="text-2xl font-bold text-orange-600 dark:text-orange-400 mt-1" data-counter="<?= $total_pr_pending ?>">0</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 hover-elevate">
            <p class="text-sm text-gray-500 dark:text-gray-400">Try Out Tersedia</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1" data-counter="<?= $total_tryout_tersedia ?>">0</p>
        </div>
    </div>

    <!-- Paket Aktif -->
    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-800 dark:text-white mb-4">Paket Aktif Saya</h3>
        <?php if (empty($paket_aktif)): ?>
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada paket aktif.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <?php foreach ($paket_aktif as $pa): ?>
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-600">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold text-gray-800 dark:text-white"><?= e($pa['nama_program']) ?></span>
                        <span class="text-xs px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full"><?= e($pa['nama_paket']) ?></span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Tutor: <?= e($pa['tutor_nama'] ?? 'Belum ditentukan') ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Mapel: <?= e($pa['mapel'] ?? '-') ?></p>
                    <div class="mt-2">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-gray-500 dark:text-gray-400">Sisa Kuota</span>
                            <span class="font-medium text-gray-700 dark:text-gray-200"><?= $pa['kuota_sisa'] ?>/<?= $pa['kuota_awal'] ?></span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                            <?php $pct = $pa['kuota_awal'] > 0 ? ($pa['kuota_sisa'] / $pa['kuota_awal']) * 100 : 0; ?>
                            <div class="bg-blue-500 h-2 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <!-- Locked message already shown in sidebar_html -->
    <div class="bg-white dark:bg-gray-800 rounded-xl p-8 border border-gray-200 dark:border-gray-700 text-center">
        <svg class="w-16 h-16 mx-auto text-yellow-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Akun Terkunci</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">Silakan bayar paket terlebih dahulu untuk mengakses semua fitur pembelajaran.</p>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($role === 'ORANG_TUA'): ?>
<!-- ORANG TUA DASHBOARD -->
<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-800 dark:text-white mb-4">Data Anak</h3>
        <?php if (empty($anak_list)): ?>
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada anak yang dipasangkan ke akun Anda. Hubungi Admin.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($anak_list as $anak): ?>
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-600">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                            <span class="text-sm font-bold text-blue-600 dark:text-blue-400"><?= strtoupper(substr($anak['nama'], 0, 1)) ?></span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white"><?= e($anak['nama']) ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= e($anak['jenjang'] ?? '-') ?> - Kelas <?= e($anak['kelas'] ?? '-') ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= e($anak['sekolah'] ?? '-') ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
