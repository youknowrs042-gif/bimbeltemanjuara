<?php
/**
 * SISWA_PERKEMBANGAN.PHP - Perkembangan Siswa (Siswa view)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('SISWA');

define('PAGE_TITLE', 'Perkembangan Saya');
$user_id = current_user_id();

// Get data
$pr_scores = db_fetch_all("
    SELECT pa.nilai, pa.submitted_at, pr.judul, pr.mapel
    FROM pr_assignment pa JOIN pr ON pa.pr_id=pr.id
    WHERE pa.siswa_user_id=? AND pa.status='SUDAH_DIKERJAKAN'
    ORDER BY pa.submitted_at ASC
", [$user_id], 'i');

$tryout_scores = db_fetch_all("
    SELECT ta.nilai, ta.finished_at, t.judul, t.mapel
    FROM tryout_attempt ta JOIN tryout t ON ta.tryout_id=t.id
    WHERE ta.siswa_user_id=? AND ta.status='SELESAI'
    ORDER BY ta.finished_at ASC
", [$user_id], 'i');

$presensi = db_fetch_one("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN status='HADIR' THEN 1 ELSE 0 END) as hadir,
           SUM(CASE WHEN status='IZIN' THEN 1 ELSE 0 END) as izin,
           SUM(CASE WHEN status NOT IN ('HADIR','IZIN','RESCHEDULE') THEN 1 ELSE 0 END) as absen
    FROM presensi WHERE siswa_user_id=?
", [$user_id], 'i');

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/sidebar_html.php';
?>

<div class="space-y-4">
    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 text-center border border-blue-200 dark:border-blue-800"><p class="text-xs text-blue-600">Total Presensi</p><p class="text-2xl font-bold text-blue-700"><?= $presensi['total'] ?? 0 ?></p></div>
        <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 text-center border border-green-200 dark:border-green-800"><p class="text-xs text-green-600">Hadir</p><p class="text-2xl font-bold text-green-700"><?= $presensi['hadir'] ?? 0 ?></p></div>
        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-4 text-center border border-yellow-200 dark:border-yellow-800"><p class="text-xs text-yellow-600">Izin</p><p class="text-2xl font-bold text-yellow-700"><?= $presensi['izin'] ?? 0 ?></p></div>
        <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-4 text-center border border-red-200 dark:border-red-800"><p class="text-xs text-red-600">Absen</p><p class="text-2xl font-bold text-red-700"><?= $presensi['absen'] ?? 0 ?></p></div>
    </div>

    <!-- Chart -->
    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-800 dark:text-white mb-4">Grafik Perkembangan Nilai</h3>
        <canvas id="chartProgress" height="200"></canvas>
    </div>

    <!-- PR Scores -->
    <?php if (!empty($pr_scores)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-800 dark:text-white mb-3">Nilai PR / Kuis</h3>
        <div class="space-y-2">
            <?php foreach ($pr_scores as $s): ?>
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div><p class="text-sm font-medium text-gray-800 dark:text-white"><?= e($s['judul']) ?></p><p class="text-xs text-gray-500"><?= e($s['mapel'] ?? '') ?> - <?= $s['submitted_at'] ? substr($s['submitted_at'],0,10) : '' ?></p></div>
                <span class="text-lg font-bold <?= (float)$s['nilai'] >= 70 ? 'text-green-600' : 'text-red-600' ?>"><?= $s['nilai'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tryout Scores -->
    <?php if (!empty($tryout_scores)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-800 dark:text-white mb-3">Nilai Try Out</h3>
        <div class="space-y-2">
            <?php foreach ($tryout_scores as $s): ?>
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div><p class="text-sm font-medium text-gray-800 dark:text-white"><?= e($s['judul']) ?></p><p class="text-xs text-gray-500"><?= e($s['mapel'] ?? '') ?> - <?= $s['finished_at'] ? substr($s['finished_at'],0,10) : '' ?></p></div>
                <span class="text-lg font-bold <?= (float)$s['nilai'] >= 70 ? 'text-green-600' : 'text-red-600' ?>"><?= $s['nilai'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
    const prLabels=<?= json_encode(array_map(fn($s)=>substr($s['submitted_at']??'',5,5), $pr_scores)) ?>;
    const prData=<?= json_encode(array_map(fn($s)=>(float)$s['nilai'], $pr_scores)) ?>;
    const toLabels=<?= json_encode(array_map(fn($s)=>substr($s['finished_at']??'',5,5), $tryout_scores)) ?>;
    const toData=<?= json_encode(array_map(fn($s)=>(float)$s['nilai'], $tryout_scores)) ?>;
    
    const allLabels=[...new Set([...prLabels,...toLabels])].sort();
    
    new Chart(document.getElementById('chartProgress'),{type:'line',data:{labels:allLabels.length?allLabels:[''],datasets:[
        {label:'PR/Kuis',data:prData,borderColor:'rgb(59,130,246)',backgroundColor:'rgba(59,130,246,0.1)',tension:0.3,fill:true},
        {label:'Try Out',data:toData,borderColor:'rgb(16,185,129)',backgroundColor:'rgba(16,185,129,0.1)',tension:0.3,fill:true}
    ]},options:{responsive:true,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,max:100}}}});
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
