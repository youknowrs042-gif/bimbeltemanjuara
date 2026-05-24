<?php
/**
 * LAPORAN.PHP - Laporan & Export PDF (Admin)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'Laporan');

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'pembayaran':
            $dari = $_GET['dari'] ?? date('Y-m-01');
            $sampai = $_GET['sampai'] ?? date('Y-m-d');
            $rows = db_fetch_all("
                SELECT pb.no_invoice, pb.jumlah, pb.status, pb.tanggal_bayar, pb.metode_bayar,
                       u.nama as siswa_nama, p.nama_program, pk.nama_paket, e.jenjang, e.mapel
                FROM pembayaran pb
                JOIN users u ON pb.siswa_user_id = u.id
                JOIN enrolment e ON pb.enrolment_id = e.id
                JOIN program p ON e.program_id = p.id
                JOIN paket pk ON e.paket_id = pk.id
                WHERE pb.created_at BETWEEN ? AND ?
                ORDER BY pb.created_at DESC
            ", [$dari . ' 00:00:00', $sampai . ' 23:59:59'], 'ss');
            $total_lunas = 0; $total_pending = 0;
            foreach ($rows as $r) {
                if ($r['status'] === 'LUNAS') $total_lunas += (float)$r['jumlah'];
                else $total_pending += (float)$r['jumlah'];
            }
            json_response(['success'=>true,'data'=>$rows,'summary'=>['total_lunas'=>$total_lunas,'total_pending'=>$total_pending,'jumlah_transaksi'=>count($rows)]]);
            break;

        case 'presensi':
            $dari = $_GET['dari'] ?? date('Y-m-01');
            $sampai = $_GET['sampai'] ?? date('Y-m-d');
            $tutor_id = (int)($_GET['tutor_id'] ?? 0);
            $where = "WHERE pr.tanggal BETWEEN ? AND ?";
            $params = [$dari, $sampai]; $types = 'ss';
            if ($tutor_id) { $where .= " AND pr.tutor_user_id = ?"; $params[] = $tutor_id; $types .= 'i'; }
            
            $rows = db_fetch_all("
                SELECT pr.tanggal, pr.jam_mulai, pr.jam_selesai, pr.status, pr.catatan, pr.lokasi,
                       su.nama as siswa_nama, tu.nama as tutor_nama, p.nama_program, e.mapel
                FROM presensi pr
                JOIN users su ON pr.siswa_user_id = su.id
                JOIN users tu ON pr.tutor_user_id = tu.id
                JOIN enrolment e ON pr.enrolment_id = e.id
                JOIN program p ON e.program_id = p.id
                $where
                ORDER BY pr.tanggal DESC, pr.jam_mulai
            ", $params, $types);
            
            $hadir = count(array_filter($rows, fn($r) => $r['status'] === 'HADIR'));
            json_response(['success'=>true,'data'=>$rows,'summary'=>['total'=>count($rows),'hadir'=>$hadir]]);
            break;


        case 'gaji':
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
            $total = array_sum(array_column($rows, 'total_gaji'));
            json_response(['success'=>true,'data'=>$rows,'summary'=>['total_gaji'=>$total]]);
            break;

        case 'perkembangan':
            $siswa_id = (int)($_GET['siswa_id'] ?? 0);
            if (!$siswa_id) json_response(['success'=>false,'message'=>'Pilih siswa.']);
            
            $siswa = db_fetch_one("SELECT u.nama, s.jenjang, s.kelas, s.sekolah FROM users u LEFT JOIN siswa s ON u.id=s.user_id WHERE u.id=?", [$siswa_id], 'i');
            
            // PR scores
            $pr_scores = db_fetch_all("
                SELECT pa.nilai, pa.submitted_at, pr.judul, pr.mapel
                FROM pr_assignment pa
                JOIN pr ON pa.pr_id = pr.id
                WHERE pa.siswa_user_id = ? AND pa.status = 'SUDAH_DIKERJAKAN'
                ORDER BY pa.submitted_at ASC
            ", [$siswa_id], 'i');
            
            // Tryout scores
            $tryout_scores = db_fetch_all("
                SELECT ta.nilai, ta.finished_at, t.judul, t.mapel
                FROM tryout_attempt ta
                JOIN tryout t ON ta.tryout_id = t.id
                WHERE ta.siswa_user_id = ? AND ta.status = 'SELESAI'
                ORDER BY ta.finished_at ASC
            ", [$siswa_id], 'i');
            
            // Presensi summary
            $presensi = db_fetch_one("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status='HADIR' THEN 1 ELSE 0 END) as hadir,
                    SUM(CASE WHEN status='IZIN' THEN 1 ELSE 0 END) as izin,
                    SUM(CASE WHEN status='TIDAK_HADIR' THEN 1 ELSE 0 END) as tidak_hadir
                FROM presensi WHERE siswa_user_id = ?
            ", [$siswa_id], 'i');
            
            json_response(['success'=>true,'siswa'=>$siswa,'pr_scores'=>$pr_scores,'tryout_scores'=>$tryout_scores,'presensi'=>$presensi]);
            break;

        case 'get_siswa_list':
            $rows = db_fetch_all("SELECT id, nama FROM users WHERE role='SISWA' AND status!='NONAKTIF' ORDER BY nama");
            json_response(['success'=>true,'data'=>$rows]);
            break;

        case 'get_tutor_list':
            $rows = db_fetch_all("SELECT id, nama FROM users WHERE role='TUTOR' AND status='AKTIF' ORDER BY nama");
            json_response(['success'=>true,'data'=>$rows]);
            break;
    }
    exit;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/sidebar_html.php';
?>


<div class="space-y-6">
    <!-- Tab Navigation -->
    <div class="flex flex-wrap border-b border-gray-200 dark:border-gray-700 gap-1">
        <button onclick="switchTab('pembayaran')" id="tabPembayaran" class="px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600">Pembayaran</button>
        <button onclick="switchTab('presensi')" id="tabPresensi" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Presensi</button>
        <button onclick="switchTab('gaji')" id="tabGaji" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Gaji</button>
        <button onclick="switchTab('perkembangan')" id="tabPerkembangan" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Perkembangan</button>
    </div>

    <!-- Panel Pembayaran -->
    <div id="panelPembayaran" class="space-y-4">
        <div class="flex flex-wrap gap-3 items-end">
            <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Dari</label><input type="date" id="pb_dari" value="<?= date('Y-m-01') ?>" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Sampai</label><input type="date" id="pb_sampai" value="<?= date('Y-m-d') ?>" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm"></div>
            <button onclick="loadPembayaran()" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm btn-press">Tampilkan</button>
            <button onclick="printReport('pembayaran')" class="px-4 py-2 bg-green-600 text-white rounded-xl text-sm btn-press flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>Print</button>
        </div>
        <div id="pbSummary" class="grid grid-cols-1 sm:grid-cols-3 gap-3"></div>
        <div id="pbTable" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 dark:bg-gray-700/50"><tr><th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Invoice</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Siswa</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-600 hidden md:table-cell">Program</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-600">Jumlah</th><th class="px-3 py-2 text-center text-xs font-medium text-gray-600">Status</th></tr></thead><tbody id="pbBody"><tr><td colspan="5" class="px-3 py-6 text-center text-gray-400 text-sm">Klik Tampilkan</td></tr></tbody></table></div></div>
    </div>

    <!-- Panel Presensi -->
    <div id="panelPresensi" class="space-y-4 hidden">
        <div class="flex flex-wrap gap-3 items-end">
            <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Dari</label><input type="date" id="pr_dari" value="<?= date('Y-m-01') ?>" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Sampai</label><input type="date" id="pr_sampai" value="<?= date('Y-m-d') ?>" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Tutor</label><select id="pr_tutor" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm"><option value="0">Semua</option></select></div>
            <button onclick="loadPresensi()" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm btn-press">Tampilkan</button>
            <button onclick="printReport('presensi')" class="px-4 py-2 bg-green-600 text-white rounded-xl text-sm btn-press flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>Print</button>
        </div>
        <div id="prSummary" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
        <div id="prTable" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 dark:bg-gray-700/50"><tr><th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Tanggal</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Siswa</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-600 hidden md:table-cell">Tutor</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-600 hidden lg:table-cell">Program</th><th class="px-3 py-2 text-center text-xs font-medium text-gray-600">Status</th></tr></thead><tbody id="prBody"><tr><td colspan="5" class="px-3 py-6 text-center text-gray-400 text-sm">Klik Tampilkan</td></tr></tbody></table></div></div>
    </div>

    <!-- Panel Gaji -->
    <div id="panelGaji" class="space-y-4 hidden">
        <div class="flex flex-wrap gap-3 items-end">
            <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Bulan</label><select id="gj_bulan" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm"><?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m==(int)date('m')?'selected':''?>><?= ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][$m] ?></option><?php endfor; ?></select></div>
            <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Tahun</label><select id="gj_tahun" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm"><?php for($y=date('Y')-2;$y<=date('Y')+1;$y++): ?><option value="<?=$y?>" <?=$y==(int)date('Y')?'selected':''?>><?=$y?></option><?php endfor; ?></select></div>
            <button onclick="loadGaji()" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm btn-press">Tampilkan</button>
            <button onclick="printReport('gaji')" class="px-4 py-2 bg-green-600 text-white rounded-xl text-sm btn-press flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>Print</button>
        </div>
        <div id="gjTable" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 dark:bg-gray-700/50"><tr><th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Tutor</th><th class="px-3 py-2 text-center text-xs font-medium text-gray-600">Sesi</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-600">Tarif</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-600">Total</th><th class="px-3 py-2 text-center text-xs font-medium text-gray-600">Status</th></tr></thead><tbody id="gjBody"><tr><td colspan="5" class="px-3 py-6 text-center text-gray-400 text-sm">Klik Tampilkan</td></tr></tbody></table></div></div>
    </div>

    <!-- Panel Perkembangan -->
    <div id="panelPerkembangan" class="space-y-4 hidden">
        <div class="flex flex-wrap gap-3 items-end">
            <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Siswa</label><select id="pk_siswa" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm"><option value="0">- Pilih Siswa -</option></select></div>
            <button onclick="loadPerkembangan()" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm btn-press">Tampilkan</button>
            <button onclick="printReport('perkembangan')" class="px-4 py-2 bg-green-600 text-white rounded-xl text-sm btn-press flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>Print</button>
        </div>
        <div id="pkContent" class="space-y-4">
            <p class="text-center text-gray-400 py-8">Pilih siswa untuk melihat perkembangan.</p>
        </div>
    </div>
</div>


<script>
const BASE='<?= BASE_URL ?>laporan.php';

document.addEventListener('DOMContentLoaded', async ()=>{
    // Load tutor & siswa options
    const tutors=await fetchAPI(`${BASE}?action=get_tutor_list`);
    if(tutors.success) document.getElementById('pr_tutor').innerHTML='<option value="0">Semua</option>'+tutors.data.map(t=>`<option value="${t.id}">${esc(t.nama)}</option>`).join('');
    const siswa=await fetchAPI(`${BASE}?action=get_siswa_list`);
    if(siswa.success) document.getElementById('pk_siswa').innerHTML='<option value="0">- Pilih Siswa -</option>'+siswa.data.map(s=>`<option value="${s.id}">${esc(s.nama)}</option>`).join('');
});

function switchTab(tab){
    ['Pembayaran','Presensi','Gaji','Perkembangan'].forEach(t=>{
        document.getElementById('panel'+t).classList.add('hidden');
        const btn=document.getElementById('tab'+t);
        btn.classList.remove('border-blue-600','text-blue-600');
        btn.classList.add('border-transparent','text-gray-500');
    });
    const name=tab.charAt(0).toUpperCase()+tab.slice(1);
    document.getElementById('panel'+name).classList.remove('hidden');
    const btn=document.getElementById('tab'+name);
    btn.classList.add('border-blue-600','text-blue-600');
    btn.classList.remove('border-transparent','text-gray-500');
}

async function loadPembayaran(){
    const dari=document.getElementById('pb_dari').value, sampai=document.getElementById('pb_sampai').value;
    const res=await fetchAPI(`${BASE}?action=pembayaran&dari=${dari}&sampai=${sampai}`);
    if(!res.success) return;
    document.getElementById('pbSummary').innerHTML=`
        <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 border border-green-200 dark:border-green-800"><p class="text-xs text-green-600">Total Lunas</p><p class="text-lg font-bold text-green-700 dark:text-green-300">Rp ${Number(res.summary.total_lunas).toLocaleString('id-ID')}</p></div>
        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-4 border border-yellow-200 dark:border-yellow-800"><p class="text-xs text-yellow-600">Total Pending</p><p class="text-lg font-bold text-yellow-700 dark:text-yellow-300">Rp ${Number(res.summary.total_pending).toLocaleString('id-ID')}</p></div>
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 border border-blue-200 dark:border-blue-800"><p class="text-xs text-blue-600">Transaksi</p><p class="text-lg font-bold text-blue-700 dark:text-blue-300">${res.summary.jumlah_transaksi}</p></div>`;
    const tb=document.getElementById('pbBody');
    tb.innerHTML=res.data.length?res.data.map(r=>{
        const sc={'LUNAS':'bg-green-100 text-green-700','BELUM_BAYAR':'bg-red-100 text-red-700','MENUNGGU_KONFIRMASI':'bg-yellow-100 text-yellow-700','BATAL':'bg-gray-100 text-gray-700'};
        return `<tr class="border-t border-gray-100 dark:border-gray-700"><td class="px-3 py-2 font-mono text-xs">${esc(r.no_invoice)}</td><td class="px-3 py-2">${esc(r.siswa_nama)}</td><td class="px-3 py-2 hidden md:table-cell text-xs">${esc(r.nama_program)} - ${esc(r.nama_paket)}</td><td class="px-3 py-2 text-right font-medium">Rp ${Number(r.jumlah).toLocaleString('id-ID')}</td><td class="px-3 py-2 text-center"><span class="px-2 py-0.5 text-xs rounded-full ${sc[r.status]||''}">${r.status.replace('_',' ')}</span></td></tr>`;
    }).join(''):'<tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">Tidak ada data.</td></tr>';
}

async function loadPresensi(){
    const dari=document.getElementById('pr_dari').value, sampai=document.getElementById('pr_sampai').value, tutor=document.getElementById('pr_tutor').value;
    const res=await fetchAPI(`${BASE}?action=presensi&dari=${dari}&sampai=${sampai}&tutor_id=${tutor}`);
    if(!res.success) return;
    document.getElementById('prSummary').innerHTML=`
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 border border-blue-200"><p class="text-xs text-blue-600">Total Presensi</p><p class="text-lg font-bold text-blue-700">${res.summary.total}</p></div>
        <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 border border-green-200"><p class="text-xs text-green-600">Total Hadir</p><p class="text-lg font-bold text-green-700">${res.summary.hadir}</p></div>`;
    const tb=document.getElementById('prBody');
    tb.innerHTML=res.data.length?res.data.map(r=>{
        const sc={'HADIR':'bg-green-100 text-green-700','TIDAK_HADIR':'bg-red-100 text-red-700','IZIN':'bg-blue-100 text-blue-700','TANPA_KET':'bg-gray-100 text-gray-700','RESCHEDULE':'bg-yellow-100 text-yellow-700'};
        return `<tr class="border-t border-gray-100 dark:border-gray-700"><td class="px-3 py-2 text-xs">${r.tanggal}</td><td class="px-3 py-2">${esc(r.siswa_nama)}</td><td class="px-3 py-2 hidden md:table-cell text-xs">${esc(r.tutor_nama)}</td><td class="px-3 py-2 hidden lg:table-cell text-xs">${esc(r.nama_program)} - ${esc(r.mapel||'')}</td><td class="px-3 py-2 text-center"><span class="px-2 py-0.5 text-xs rounded-full ${sc[r.status]||''}">${r.status.replace('_',' ')}</span></td></tr>`;
    }).join(''):'<tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">Tidak ada data.</td></tr>';
}

async function loadGaji(){
    const bulan=document.getElementById('gj_bulan').value, tahun=document.getElementById('gj_tahun').value;
    const res=await fetchAPI(`${BASE}?action=gaji&bulan=${bulan}&tahun=${tahun}`);
    if(!res.success) return;
    const tb=document.getElementById('gjBody');
    tb.innerHTML=res.data.length?res.data.map(r=>{
        const sc={'DRAFT':'bg-gray-100 text-gray-700','DISETUJUI':'bg-blue-100 text-blue-700','DIBAYAR':'bg-green-100 text-green-700'};
        return `<tr class="border-t border-gray-100 dark:border-gray-700"><td class="px-3 py-2 font-medium">${esc(r.tutor_nama)}</td><td class="px-3 py-2 text-center">${r.total_sesi}</td><td class="px-3 py-2 text-right">Rp ${Number(r.tarif_per_sesi).toLocaleString('id-ID')}</td><td class="px-3 py-2 text-right font-bold">Rp ${Number(r.total_gaji).toLocaleString('id-ID')}</td><td class="px-3 py-2 text-center"><span class="px-2 py-0.5 text-xs rounded-full ${sc[r.status]||''}">${r.status}</span></td></tr>`;
    }).join('')+'<tr class="border-t-2 border-gray-300"><td colspan="3" class="px-3 py-2 font-bold text-right">TOTAL</td><td class="px-3 py-2 text-right font-bold text-blue-600">Rp '+Number(res.summary.total_gaji).toLocaleString('id-ID')+'</td><td></td></tr>':'<tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">Tidak ada data.</td></tr>';
}


async function loadPerkembangan(){
    const siswaId=document.getElementById('pk_siswa').value;
    if(!siswaId||siswaId==='0'){showToast('Pilih siswa dulu','error');return;}
    const res=await fetchAPI(`${BASE}?action=perkembangan&siswa_id=${siswaId}`);
    if(!res.success){showToast(res.message,'error');return;}
    
    const el=document.getElementById('pkContent');
    let html=`
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <h4 class="font-semibold text-gray-800 dark:text-white">${esc(res.siswa.nama)}</h4>
            <p class="text-sm text-gray-500">${esc(res.siswa.jenjang||'-')} - Kelas ${esc(res.siswa.kelas||'-')} | ${esc(res.siswa.sekolah||'-')}</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3 text-center"><p class="text-xs text-blue-600">Total Presensi</p><p class="text-xl font-bold text-blue-700">${res.presensi?.total||0}</p></div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-3 text-center"><p class="text-xs text-green-600">Hadir</p><p class="text-xl font-bold text-green-700">${res.presensi?.hadir||0}</p></div>
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-3 text-center"><p class="text-xs text-yellow-600">Izin</p><p class="text-xl font-bold text-yellow-700">${res.presensi?.izin||0}</p></div>
            <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3 text-center"><p class="text-xs text-red-600">Tidak Hadir</p><p class="text-xl font-bold text-red-700">${res.presensi?.tidak_hadir||0}</p></div>
        </div>`;
    
    // Chart
    html += '<div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700"><h4 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Grafik Perkembangan Nilai</h4><canvas id="chartPerkembangan" height="200"></canvas></div>';
    
    // Tables
    if(res.pr_scores.length){
        html+='<div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700"><h4 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Nilai PR/Kuis</h4><div class="space-y-2">'+res.pr_scores.map(s=>`<div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg"><div><p class="text-sm font-medium">${esc(s.judul)}</p><p class="text-xs text-gray-500">${esc(s.mapel||'')} - ${s.submitted_at?s.submitted_at.substring(0,10):''}</p></div><span class="text-lg font-bold ${s.nilai>=70?'text-green-600':'text-red-600'}">${s.nilai}</span></div>`).join('')+'</div></div>';
    }
    if(res.tryout_scores.length){
        html+='<div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700"><h4 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Nilai Try Out</h4><div class="space-y-2">'+res.tryout_scores.map(s=>`<div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg"><div><p class="text-sm font-medium">${esc(s.judul)}</p><p class="text-xs text-gray-500">${esc(s.mapel||'')} - ${s.finished_at?s.finished_at.substring(0,10):''}</p></div><span class="text-lg font-bold ${s.nilai>=70?'text-green-600':'text-red-600'}">${s.nilai}</span></div>`).join('')+'</div></div>';
    }
    
    el.innerHTML=html;
    
    // Render chart
    setTimeout(()=>{
        const ctx=document.getElementById('chartPerkembangan');
        if(!ctx) return;
        const labels=[]; const prData=[]; const toData=[];
        res.pr_scores.forEach(s=>{labels.push(s.submitted_at?s.submitted_at.substring(5,10):'');prData.push(parseFloat(s.nilai));});
        res.tryout_scores.forEach(s=>{if(!labels.includes(s.finished_at?s.finished_at.substring(5,10):''))labels.push(s.finished_at?s.finished_at.substring(5,10):'');toData.push(parseFloat(s.nilai));});
        new Chart(ctx,{type:'line',data:{labels:labels.length?labels:[''],datasets:[
            {label:'PR/Kuis',data:prData,borderColor:'rgb(59,130,246)',backgroundColor:'rgba(59,130,246,0.1)',tension:0.3,fill:true},
            {label:'Try Out',data:toData,borderColor:'rgb(16,185,129)',backgroundColor:'rgba(16,185,129,0.1)',tension:0.3,fill:true}
        ]},options:{responsive:true,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,max:100}}}});
    },100);
}

function printReport(type){
    const panel=document.getElementById('panel'+type.charAt(0).toUpperCase()+type.slice(1));
    const printWin=window.open('','_blank');
    printWin.document.write(`<!DOCTYPE html><html><head><title>Laporan ${type} - <?= APP_NAME ?></title><script src="https://cdn.tailwindcss.com"><\/script><style>@media print{body{print-color-adjust:exact;-webkit-print-color-adjust:exact;}}</style></head><body class="p-8"><div class="text-center mb-6"><h1 class="text-xl font-bold"><?= APP_NAME ?></h1><h2 class="text-lg">Laporan ${type.charAt(0).toUpperCase()+type.slice(1)}</h2><p class="text-sm text-gray-500">Dicetak: ${new Date().toLocaleString('id-ID')}</p></div>${panel.innerHTML}</body></html>`);
    printWin.document.close();
    setTimeout(()=>{printWin.print();},500);
}

function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
