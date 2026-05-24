<?php
/**
 * TUTOR_PRESENSI.PHP - Tutor input presensi (only own students)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('TUTOR');

define('PAGE_TITLE', 'Presensi');
$user_id = current_user_id();

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'create':
            csrf_check();
            $enrolment_id = (int)($_POST['enrolment_id'] ?? 0);
            $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
            $jam_mulai = $_POST['jam_mulai'] ?? null;
            $jam_selesai = $_POST['jam_selesai'] ?? null;
            $status = $_POST['status'] ?? 'HADIR';
            $catatan = trim($_POST['catatan'] ?? '');
            $lokasi = trim($_POST['lokasi'] ?? '');
            
            // Verify this is tutor's enrolment
            $enrol = db_fetch_one("SELECT * FROM enrolment WHERE id=? AND tutor_user_id=?", [$enrolment_id, $user_id], 'ii');
            if (!$enrol) json_response(['success'=>false,'message'=>'Anda tidak memiliki akses ke enrolment ini.']);
            
            if ($status === 'HADIR' && $enrol['kuota_sisa'] <= 0) {
                json_response(['success'=>false,'message'=>'Kuota pertemuan sudah habis!']);
            }
            
            $foto_path = null;
            if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === UPLOAD_ERR_OK) {
                $upload = upload_file($_FILES['foto_bukti'], 'presensi');
                if ($upload['success']) $foto_path = $upload['path'];
            }
            
            db_insert("INSERT INTO presensi (enrolment_id, siswa_user_id, tutor_user_id, tanggal, jam_mulai, jam_selesai, status, catatan, foto_bukti, lokasi) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$enrolment_id, $enrol['siswa_user_id'], $user_id, $tanggal, $jam_mulai, $jam_selesai, $status, $catatan, $foto_path, $lokasi], 'iiisssssss');
            
            if ($status === 'HADIR') {
                db_query("UPDATE enrolment SET kuota_sisa = kuota_sisa - 1 WHERE id=? AND kuota_sisa > 0", [$enrolment_id], 'i');
                $upd = db_fetch_one("SELECT kuota_sisa FROM enrolment WHERE id=?", [$enrolment_id], 'i');
                if ($upd && (int)$upd['kuota_sisa'] <= 0) db_query("UPDATE enrolment SET status='SELESAI' WHERE id=?", [$enrolment_id], 'i');
            }
            
            log_activity('TUTOR_PRESENSI', "Tutor input presensi enrolment $enrolment_id");
            json_response(['success'=>true,'message'=>'Presensi berhasil dicatat.']);
            break;

        case 'list':
            $page = max(1,(int)($_GET['page']??1));
            $total = db_count("SELECT COUNT(*) FROM presensi WHERE tutor_user_id=?", [$user_id], 'i');
            $paging = paginate($total, 15, $page);
            $rows = db_fetch_all("
                SELECT pr.*, su.nama as siswa_nama, p.nama_program, e.mapel
                FROM presensi pr
                JOIN users su ON pr.siswa_user_id = su.id
                JOIN enrolment e ON pr.enrolment_id = e.id
                JOIN program p ON e.program_id = p.id
                WHERE pr.tutor_user_id = ?
                ORDER BY pr.tanggal DESC, pr.jam_mulai DESC
                LIMIT {$paging['per_page']} OFFSET {$paging['offset']}
            ", [$user_id], 'i');
            json_response(['success'=>true,'data'=>$rows,'paging'=>$paging]);
            break;

        case 'get_enrolments':
            $rows = db_fetch_all("
                SELECT e.id, e.mapel, e.kuota_sisa, e.kuota_awal, u.nama as siswa_nama, p.nama_program
                FROM enrolment e
                JOIN users u ON e.siswa_user_id = u.id
                JOIN program p ON e.program_id = p.id
                WHERE e.tutor_user_id = ? AND e.status = 'AKTIF'
                ORDER BY u.nama
            ", [$user_id], 'i');
            json_response(['success'=>true,'data'=>$rows]);
            break;
    }
    exit;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/sidebar_html.php';
?>

<div class="space-y-4">
    <div class="flex justify-end">
        <button onclick="showCreateModal()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium btn-press flex items-center gap-2 shadow-lg shadow-blue-500/25">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Input Presensi
        </button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Tanggal</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Siswa</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Program</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Jam</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Status</th>
                </tr></thead>
                <tbody id="tableBody"><tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Memuat...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div id="paginationContainer"></div>
</div>

<!-- Modal -->
<div id="modalPresensi" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-semibold text-gray-800 dark:text-white">Input Presensi</h3><button onclick="closeModal('modalPresensi')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form id="formPresensi" enctype="multipart/form-data" class="space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Siswa - Program *</label><select id="f_enrolment" name="enrolment_id" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></select></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal *</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status *</label><select name="status" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"><option value="HADIR">Hadir</option><option value="TIDAK_HADIR">Tidak Hadir</option><option value="IZIN">Izin</option><option value="TANPA_KET">Tanpa Ket</option><option value="RESCHEDULE">Reschedule</option></select></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jam Mulai</label><input type="time" name="jam_mulai" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jam Selesai</label><input type="time" name="jam_selesai" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Lokasi</label><input type="text" name="lokasi" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Catatan</label><textarea name="catatan" rows="2" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm resize-none"></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Foto Bukti</label><input type="file" name="foto_bukti" accept="image/*" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                <div class="flex gap-3 pt-2"><button type="button" onclick="closeModal('modalPresensi')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Batal</button><button type="submit" id="btnSubmit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm btn-press">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<script>
const BASE='<?= BASE_URL ?>tutor_presensi.php', CSRF='<?= csrf_token() ?>';
let currentPage=1;
document.addEventListener('DOMContentLoaded', ()=>loadData());

async function loadData(){
    const tb=document.getElementById('tableBody');
    tb.innerHTML='<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div></td></tr>';
    const res=await fetchAPI(`${BASE}?action=list&page=${currentPage}`);
    if(res.success&&res.data.length>0){
        tb.innerHTML=res.data.map(r=>{
            const sc={'HADIR':'bg-green-100 text-green-700','TIDAK_HADIR':'bg-red-100 text-red-700','IZIN':'bg-blue-100 text-blue-700','TANPA_KET':'bg-gray-100 text-gray-700','RESCHEDULE':'bg-yellow-100 text-yellow-700'};
            return `<tr class="border-t border-gray-100 dark:border-gray-700"><td class="px-4 py-3 text-sm">${r.tanggal}</td><td class="px-4 py-3 font-medium text-gray-800 dark:text-white">${esc(r.siswa_nama)}</td><td class="px-4 py-3 hidden md:table-cell text-xs text-gray-500">${esc(r.nama_program)} - ${esc(r.mapel||'')}</td><td class="px-4 py-3 text-center text-xs">${r.jam_mulai?r.jam_mulai.substring(0,5):'-'} - ${r.jam_selesai?r.jam_selesai.substring(0,5):'-'}</td><td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs rounded-full ${sc[r.status]||''}">${r.status.replace('_',' ')}</span></td></tr>`;
        }).join('');
    } else tb.innerHTML='<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada presensi.</td></tr>';
}

async function showCreateModal(){
    const res=await fetchAPI(`${BASE}?action=get_enrolments`);
    if(res.success) document.getElementById('f_enrolment').innerHTML=res.data.map(e=>`<option value="${e.id}">${esc(e.siswa_nama)} - ${esc(e.nama_program)} (${esc(e.mapel||'-')}) [${e.kuota_sisa}/${e.kuota_awal}]</option>`).join('');
    document.getElementById('formPresensi').reset();openModal('modalPresensi');
}

document.getElementById('formPresensi').addEventListener('submit', async function(e){
    e.preventDefault();const btn=document.getElementById('btnSubmit');setButtonLoading(btn,true);
    const fd=new FormData(this);fd.append('action','create');fd.append('csrf_token',CSRF);
    const res=await fetchAPI(BASE,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
    setButtonLoading(btn,false);
    if(res.success){closeModal('modalPresensi');showToast(res.message);loadData();}else showToast(res.message,'error');
});

function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
