<?php
/**
 * GAJI.PHP - Rekap Gaji Tutor (Admin)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'Gaji Tutor');

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'generate':
            csrf_check();
            $bulan = (int)($_POST['bulan'] ?? date('m'));
            $tahun = (int)($_POST['tahun'] ?? date('Y'));
            
            // Get semua tutor aktif
            $tutors = db_fetch_all("
                SELECT u.id, u.nama, t.tarif_per_sesi
                FROM users u
                JOIN tutor t ON u.id = t.user_id
                WHERE u.role = 'TUTOR' AND u.status = 'AKTIF'
            ");
            
            $generated = 0;
            foreach ($tutors as $tutor) {
                // Hitung total sesi HADIR bulan ini
                $sesi = db_count(
                    "SELECT COUNT(*) FROM presensi WHERE tutor_user_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND status = 'HADIR'",
                    [$tutor['id'], $bulan, $tahun], 'iii'
                );
                
                $tarif = (float)$tutor['tarif_per_sesi'];
                $total_gaji = $sesi * $tarif;
                
                // Upsert gaji
                $existing = db_fetch_one("SELECT id FROM gaji_tutor WHERE tutor_user_id=? AND bulan=? AND tahun=?", [$tutor['id'],$bulan,$tahun], 'iii');
                if ($existing) {
                    db_query("UPDATE gaji_tutor SET total_sesi=?, tarif_per_sesi=?, total_gaji=? WHERE id=?",
                        [$sesi, $tarif, $total_gaji, $existing['id']], 'iddi');
                } else {
                    db_insert("INSERT INTO gaji_tutor (tutor_user_id, bulan, tahun, total_sesi, tarif_per_sesi, total_gaji) VALUES (?,?,?,?,?,?)",
                        [$tutor['id'], $bulan, $tahun, $sesi, $tarif, $total_gaji], 'iiiid d');
                }
                $generated++;
            }
            
            log_activity('GENERATE_GAJI', "Generate gaji $bulan/$tahun untuk $generated tutor");
            json_response(['success'=>true,'message'=>"Gaji bulan $bulan/$tahun berhasil digenerate untuk $generated tutor."]);
            break;


        case 'list':
            $bulan = (int)($_GET['bulan'] ?? date('m'));
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            
            $rows = db_fetch_all("
                SELECT g.*, u.nama as tutor_nama, t.nama_bank, t.no_rekening, t.atas_nama_rek
                FROM gaji_tutor g
                JOIN users u ON g.tutor_user_id = u.id
                LEFT JOIN tutor t ON u.id = t.user_id
                WHERE g.bulan = ? AND g.tahun = ?
                ORDER BY u.nama
            ", [$bulan, $tahun], 'ii');
            
            $total_all = array_sum(array_column($rows, 'total_gaji'));
            json_response(['success'=>true,'data'=>$rows,'total'=>$total_all]);
            break;

        case 'update_status':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['DRAFT','DISETUJUI','DIBAYAR'])) json_response(['success'=>false,'message'=>'Status tidak valid.']);
            db_query("UPDATE gaji_tutor SET status=? WHERE id=?", [$status,$id], 'si');
            json_response(['success'=>true,'message'=>'Status gaji diupdate.']);
            break;
    }
    exit;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/sidebar_html.php';
?>

<div class="space-y-4">
    <!-- Controls -->
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <div class="flex gap-2 items-center">
            <select id="selBulan" class="px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm">
                <?php for($m=1;$m<=12;$m++): ?>
                <option value="<?=$m?>" <?= $m==(int)date('m')?'selected':'' ?>><?= ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][$m] ?></option>
                <?php endfor; ?>
            </select>
            <select id="selTahun" class="px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm">
                <?php for($y=date('Y')-2;$y<=date('Y')+1;$y++): ?>
                <option value="<?=$y?>" <?=$y==(int)date('Y')?'selected':''?>><?=$y?></option>
                <?php endfor; ?>
            </select>
            <button onclick="loadData()" class="px-4 py-2.5 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 rounded-xl text-sm font-medium transition-colors">Tampilkan</button>
        </div>
        <button onclick="generateGaji()" class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl text-sm font-medium btn-press flex items-center gap-2 shadow-lg shadow-green-500/25">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Generate Gaji
        </button>
    </div>

    <!-- Total Summary -->
    <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-xl p-5 text-white">
        <p class="text-sm opacity-80">Total Gaji Bulan Ini</p>
        <p class="text-2xl font-bold mt-1" id="totalGaji">Rp 0</p>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Tutor</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Sesi</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300 hidden sm:table-cell">Tarif/Sesi</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Total Gaji</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Status</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                </tr></thead>
                <tbody id="tableBody"><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Pilih bulan & tahun, lalu klik Generate Gaji.</td></tr></tbody>
            </table>
        </div>
    </div>
</div>


<script>
const BASE='<?= BASE_URL ?>gaji.php', CSRF='<?= csrf_token() ?>';

document.addEventListener('DOMContentLoaded', ()=>loadData());

async function loadData() {
    const bulan=document.getElementById('selBulan').value;
    const tahun=document.getElementById('selTahun').value;
    const tb=document.getElementById('tableBody');
    tb.innerHTML='<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div></td></tr>';
    
    const res=await fetchAPI(`${BASE}?action=list&bulan=${bulan}&tahun=${tahun}`);
    if(res.success&&res.data.length>0){
        document.getElementById('totalGaji').textContent='Rp '+Number(res.total||0).toLocaleString('id-ID');
        tb.innerHTML=res.data.map(r=>{
            const sc={'DRAFT':'bg-gray-100 text-gray-700','DISETUJUI':'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300','DIBAYAR':'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'};
            return `<tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                <td class="px-4 py-3"><div class="font-medium text-gray-800 dark:text-white">${esc(r.tutor_nama)}</div><div class="text-xs text-gray-500">${esc(r.nama_bank||'')} ${esc(r.no_rekening||'')}</div></td>
                <td class="px-4 py-3 text-center font-bold text-gray-800 dark:text-white">${r.total_sesi}</td>
                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 hidden sm:table-cell">Rp ${Number(r.tarif_per_sesi||0).toLocaleString('id-ID')}</td>
                <td class="px-4 py-3 text-right font-bold text-gray-800 dark:text-white">Rp ${Number(r.total_gaji||0).toLocaleString('id-ID')}</td>
                <td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs rounded-full ${sc[r.status]||''}">${r.status}</span></td>
                <td class="px-4 py-3 text-center"><div class="flex items-center justify-center gap-1">
                    ${r.status==='DRAFT'?`<button onclick="updateGajiStatus(${r.id},'DISETUJUI')" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="Setujui"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></button>`:''}
                    ${r.status==='DISETUJUI'?`<button onclick="updateGajiStatus(${r.id},'DIBAYAR')" class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg" title="Tandai Dibayar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 1v8m0 0v1"/></svg></button>`:''}
                </div></td>
            </tr>`;
        }).join('');
    } else {
        tb.innerHTML='<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada data gaji. Klik Generate Gaji.</td></tr>';
        document.getElementById('totalGaji').textContent='Rp 0';
    }
}

async function generateGaji() {
    const bulan=document.getElementById('selBulan').value;
    const tahun=document.getElementById('selTahun').value;
    if(!confirm(`Generate gaji bulan ${bulan}/${tahun}?`)) return;
    const fd=new FormData();fd.append('action','generate');fd.append('bulan',bulan);fd.append('tahun',tahun);fd.append('csrf_token',CSRF);
    const res=await fetchAPI(BASE,{method:'POST',body:fd});
    if(res.success){showToast(res.message);loadData();}else showToast(res.message,'error');
}

async function updateGajiStatus(id,status) {
    const fd=new FormData();fd.append('action','update_status');fd.append('id',id);fd.append('status',status);fd.append('csrf_token',CSRF);
    const res=await fetchAPI(BASE,{method:'POST',body:fd});
    if(res.success){showToast(res.message);loadData();}else showToast(res.message,'error');
}

function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
