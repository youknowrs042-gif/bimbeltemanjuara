<?php
/**
 * PRESENSI.PHP - Manajemen Presensi (Admin)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'Presensi');

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
            
            if (!$enrolment_id) json_response(['success'=>false,'message'=>'Enrolment wajib dipilih.']);
            if (!in_array($status, ['HADIR','TIDAK_HADIR','IZIN','TANPA_KET','RESCHEDULE'])) {
                json_response(['success'=>false,'message'=>'Status tidak valid.']);
            }
            
            // Get enrolment data
            $enrol = db_fetch_one("SELECT * FROM enrolment WHERE id = ?", [$enrolment_id], 'i');
            if (!$enrol) json_response(['success'=>false,'message'=>'Enrolment tidak ditemukan.']);
            
            // Cek kuota jika HADIR
            if ($status === 'HADIR' && $enrol['kuota_sisa'] <= 0) {
                json_response(['success'=>false,'message'=>'Kuota pertemuan sudah habis!']);
            }


            // Upload foto bukti
            $foto_path = null;
            if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === UPLOAD_ERR_OK) {
                $upload = upload_file($_FILES['foto_bukti'], 'presensi');
                if ($upload['success']) $foto_path = $upload['path'];
            }
            
            $presensi_id = db_insert(
                "INSERT INTO presensi (enrolment_id, siswa_user_id, tutor_user_id, tanggal, jam_mulai, jam_selesai, status, catatan, foto_bukti, lokasi) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$enrolment_id, $enrol['siswa_user_id'], $enrol['tutor_user_id'] ?? current_user_id(), $tanggal, $jam_mulai, $jam_selesai, $status, $catatan, $foto_path, $lokasi],
                'iiissssss s'
            );
            
            // Kurangi kuota HANYA jika HADIR
            if ($presensi_id && $status === 'HADIR') {
                db_query("UPDATE enrolment SET kuota_sisa = kuota_sisa - 1 WHERE id = ? AND kuota_sisa > 0", [$enrolment_id], 'i');
                
                // Cek apakah kuota habis
                $updated = db_fetch_one("SELECT kuota_sisa FROM enrolment WHERE id = ?", [$enrolment_id], 'i');
                if ($updated && (int)$updated['kuota_sisa'] <= 0) {
                    db_query("UPDATE enrolment SET status = 'SELESAI' WHERE id = ?", [$enrolment_id], 'i');
                }
            }
            
            log_activity('CREATE_PRESENSI', "Presensi enrolment $enrolment_id status $status");
            json_response(['success'=>true,'message'=>'Presensi berhasil dicatat.']);
            break;

        case 'delete':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            // Jika status HADIR, kembalikan kuota
            $pres = db_fetch_one("SELECT * FROM presensi WHERE id = ?", [$id], 'i');
            if ($pres && $pres['status'] === 'HADIR') {
                db_query("UPDATE enrolment SET kuota_sisa = kuota_sisa + 1 WHERE id = ?", [$pres['enrolment_id']], 'i');
            }
            db_query("DELETE FROM presensi WHERE id = ?", [$id], 'i');
            json_response(['success'=>true,'message'=>'Presensi dihapus.']);
            break;

        case 'list':
            $search = trim($_GET['search'] ?? '');
            $filter_date = $_GET['filter_date'] ?? '';
            $page = max(1,(int)($_GET['page']??1));
            
            $where = "WHERE 1=1"; $params=[]; $types='';
            if ($search) { $where .= " AND (su.nama LIKE ? OR tu.nama LIKE ?)"; $s="%$search%"; $params=[$s,$s]; $types='ss'; }
            if ($filter_date) { $where .= " AND pr.tanggal = ?"; $params[]=$filter_date; $types.='s'; }
            
            $total = db_count("SELECT COUNT(*) FROM presensi pr JOIN users su ON pr.siswa_user_id=su.id JOIN users tu ON pr.tutor_user_id=tu.id $where", $params, $types);
            $paging = paginate($total, 15, $page);
            
            $rows = db_fetch_all("
                SELECT pr.*, su.nama as siswa_nama, tu.nama as tutor_nama,
                       p.nama_program, e.mapel
                FROM presensi pr
                JOIN users su ON pr.siswa_user_id = su.id
                JOIN users tu ON pr.tutor_user_id = tu.id
                JOIN enrolment e ON pr.enrolment_id = e.id
                JOIN program p ON e.program_id = p.id
                $where
                ORDER BY pr.tanggal DESC, pr.jam_mulai DESC
                LIMIT {$paging['per_page']} OFFSET {$paging['offset']}
            ", $params, $types);
            
            json_response(['success'=>true,'data'=>$rows,'paging'=>$paging]);
            break;

        case 'get_enrolments':
            $rows = db_fetch_all("
                SELECT e.id, e.mapel, e.kuota_sisa, e.kuota_awal,
                       u.nama as siswa_nama, p.nama_program, t.nama as tutor_nama
                FROM enrolment e
                JOIN users u ON e.siswa_user_id = u.id
                JOIN program p ON e.program_id = p.id
                LEFT JOIN users t ON e.tutor_user_id = t.id
                WHERE e.status = 'AKTIF'
                ORDER BY u.nama
            ");
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
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <div class="relative w-full sm:w-56">
                <input type="text" id="searchInput" placeholder="Cari siswa/tutor..." class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500">
                <svg class="absolute left-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input type="date" id="filterDate" onchange="loadData()" class="px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm">
        </div>
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
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Tutor / Program</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Jam</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Status</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                </tr></thead>
                <tbody id="tableBody"><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Memuat...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div id="paginationContainer"></div>
</div>

<!-- Modal Input Presensi -->
<div id="modalPresensi" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-semibold text-gray-800 dark:text-white">Input Presensi</h3><button onclick="closeModal('modalPresensi')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form id="formPresensi" enctype="multipart/form-data" class="space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Enrolment (Siswa - Program) *</label><select id="f_enrolment" name="enrolment_id" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></select></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal *</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status *</label><select name="status" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"><option value="HADIR">Hadir</option><option value="TIDAK_HADIR">Tidak Hadir</option><option value="IZIN">Izin</option><option value="TANPA_KET">Tanpa Keterangan</option><option value="RESCHEDULE">Reschedule</option></select></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jam Mulai</label><input type="time" name="jam_mulai" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jam Selesai</label><input type="time" name="jam_selesai" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Lokasi</label><input type="text" name="lokasi" placeholder="Rumah siswa / Online / Kelas A" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Catatan</label><textarea name="catatan" rows="2" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm resize-none"></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Foto Bukti</label><input type="file" name="foto_bukti" accept="image/*" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                <div class="flex gap-3 pt-2"><button type="button" onclick="closeModal('modalPresensi')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Batal</button><button type="submit" id="btnSubmit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm btn-press">Simpan</button></div>
            </form>
        </div>
    </div>
</div>


<script>
const BASE='<?= BASE_URL ?>presensi.php', CSRF='<?= csrf_token() ?>';
let currentPage=1, searchQuery='';

document.addEventListener('DOMContentLoaded', ()=>loadData());
document.getElementById('searchInput').addEventListener('input', debounce(function(){searchQuery=this.value;currentPage=1;loadData();}));

async function loadData() {
    const filterDate=document.getElementById('filterDate').value;
    const tb=document.getElementById('tableBody');
    tb.innerHTML='<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div></td></tr>';
    const res=await fetchAPI(`${BASE}?action=list&page=${currentPage}&search=${encodeURIComponent(searchQuery)}&filter_date=${filterDate}`);
    if(res.success&&res.data.length>0){
        tb.innerHTML=res.data.map(r=>{
            const sc={'HADIR':'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300','TIDAK_HADIR':'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300','IZIN':'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300','TANPA_KET':'bg-gray-100 text-gray-700','RESCHEDULE':'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'};
            return `<tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 table-row-enter">
                <td class="px-4 py-3 text-sm text-gray-800 dark:text-white">${r.tanggal}</td>
                <td class="px-4 py-3"><div class="font-medium text-gray-800 dark:text-white">${esc(r.siswa_nama)}</div><div class="text-xs text-gray-500">${esc(r.mapel||'-')}</div></td>
                <td class="px-4 py-3 hidden md:table-cell"><div class="text-gray-600 dark:text-gray-400">${esc(r.tutor_nama)}</div><div class="text-xs text-gray-500">${esc(r.nama_program)}</div></td>
                <td class="px-4 py-3 text-center text-xs text-gray-600 dark:text-gray-400">${r.jam_mulai?r.jam_mulai.substring(0,5):'-'} - ${r.jam_selesai?r.jam_selesai.substring(0,5):'-'}</td>
                <td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs rounded-full ${sc[r.status]||''}">${r.status.replace('_',' ')}</span></td>
                <td class="px-4 py-3 text-center"><button onclick="deletePresensi(${r.id})" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></td>
            </tr>`;
        }).join('');
    } else { tb.innerHTML='<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada data presensi.</td></tr>'; }
    if(res.paging?.total_pages>1){let h=`<div class="flex items-center justify-between mt-4"><div class="text-sm text-gray-500">Hal ${res.paging.current_page}/${res.paging.total_pages}</div><div class="flex gap-1">`;if(res.paging.has_prev)h+=`<button onclick="currentPage--;loadData()" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&laquo;</button>`;if(res.paging.has_next)h+=`<button onclick="currentPage++;loadData()" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&raquo;</button>`;h+='</div></div>';document.getElementById('paginationContainer').innerHTML=h;}else{document.getElementById('paginationContainer').innerHTML='';}
}

async function showCreateModal() {
    const res=await fetchAPI(`${BASE}?action=get_enrolments`);
    if(res.success){
        document.getElementById('f_enrolment').innerHTML=res.data.map(e=>`<option value="${e.id}">${esc(e.siswa_nama)} - ${esc(e.nama_program)} (${esc(e.mapel||'-')}) [Sisa: ${e.kuota_sisa}/${e.kuota_awal}]</option>`).join('');
    }
    document.getElementById('formPresensi').reset();
    openModal('modalPresensi');
}

document.getElementById('formPresensi').addEventListener('submit', async function(e){
    e.preventDefault();const btn=document.getElementById('btnSubmit');setButtonLoading(btn,true);
    const fd=new FormData(this);fd.append('action','create');fd.append('csrf_token',CSRF);
    const res=await fetchAPI(BASE,{method:'POST',body:fd});
    setButtonLoading(btn,false);
    if(res.success){closeModal('modalPresensi');showToast(res.message);loadData();}else showToast(res.message,'error');
});

async function deletePresensi(id){if(!confirm('Hapus presensi ini? Kuota akan dikembalikan jika status HADIR.'))return;const fd=new FormData();fd.append('action','delete');fd.append('id',id);fd.append('csrf_token',CSRF);const r=await fetchAPI(BASE,{method:'POST',body:fd});if(r.success){showToast(r.message);loadData();}else showToast(r.message,'error');}
function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
