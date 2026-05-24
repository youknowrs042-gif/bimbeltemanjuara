<?php
/**
 * PR.PHP - Manajemen PR/Kuis MCQ (Admin)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'PR / Kuis');

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'create':
            csrf_check();
            $judul = trim($_POST['judul'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $bank_soal_id = (int)($_POST['bank_soal_id'] ?? 0);
            $jenjang = $_POST['jenjang'] ?? null;
            $mapel = trim($_POST['mapel'] ?? '');
            $deadline = $_POST['deadline'] ?? null;
            $siswa_ids = $_POST['siswa_ids'] ?? [];
            
            if (empty($judul) || !$bank_soal_id) json_response(['success'=>false,'message'=>'Judul dan bank soal wajib diisi.']);
            
            // Admin langsung publish
            $status = 'PUBLISHED';
            
            $pr_id = db_insert(
                "INSERT INTO pr (judul, deskripsi, bank_soal_id, jenjang, mapel, deadline, status, created_by, approved_by) VALUES (?,?,?,?,?,?,?,?,?)",
                [$judul, $deskripsi, $bank_soal_id, $jenjang, $mapel, $deadline ?: null, $status, current_user_id(), current_user_id()],
                'ssissssii'
            );
            
            if ($pr_id && !empty($siswa_ids)) {
                // Assign ke siswa yang dipilih
                foreach ($siswa_ids as $sid) {
                    db_insert("INSERT INTO pr_assignment (pr_id, siswa_user_id) VALUES (?,?)", [$pr_id, (int)$sid], 'ii');
                }
            }
            
            log_activity('CREATE_PR', "Membuat PR: $judul");
            json_response(['success'=>true,'message'=>'PR berhasil dibuat dan dipublish.']);
            break;


        case 'delete':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            db_query("DELETE FROM pr WHERE id=?", [$id], 'i');
            log_activity('DELETE_PR', "Hapus PR ID: $id");
            json_response(['success'=>true,'message'=>'PR dihapus.']);
            break;

        case 'list':
            $search = trim($_GET['search'] ?? '');
            $page = max(1,(int)($_GET['page']??1));
            $where = "WHERE 1=1"; $params=[]; $types='';
            if ($search) { $where .= " AND (pr.judul LIKE ? OR pr.mapel LIKE ?)"; $s="%$search%"; $params=[$s,$s]; $types='ss'; }
            
            $total = db_count("SELECT COUNT(*) FROM pr $where", $params, $types);
            $paging = paginate($total, 10, $page);
            
            $rows = db_fetch_all("
                SELECT pr.*, u.nama as creator_nama, bs.judul as bank_soal_judul,
                       (SELECT COUNT(*) FROM pr_assignment WHERE pr_id = pr.id) as total_assigned,
                       (SELECT COUNT(*) FROM pr_assignment WHERE pr_id = pr.id AND status = 'SUDAH_DIKERJAKAN') as total_done
                FROM pr
                JOIN users u ON pr.created_by = u.id
                JOIN bank_soal bs ON pr.bank_soal_id = bs.id
                $where
                ORDER BY pr.created_at DESC
                LIMIT {$paging['per_page']} OFFSET {$paging['offset']}
            ", $params, $types);
            
            json_response(['success'=>true,'data'=>$rows,'paging'=>$paging]);
            break;

        case 'get_options':
            $bank_soal = db_fetch_all("SELECT id, judul, mapel, jenjang FROM bank_soal WHERE tipe='SOAL_MCQ' AND status='DISETUJUI' ORDER BY judul");
            $siswa = db_fetch_all("SELECT u.id, u.nama, s.jenjang FROM users u LEFT JOIN siswa s ON u.id=s.user_id WHERE u.role='SISWA' AND u.status!='NONAKTIF' ORDER BY u.nama");
            json_response(['success'=>true,'bank_soal'=>$bank_soal,'siswa'=>$siswa]);
            break;

        case 'get_results':
            $pr_id = (int)($_GET['pr_id'] ?? 0);
            $assignments = db_fetch_all("
                SELECT pa.*, u.nama as siswa_nama
                FROM pr_assignment pa
                JOIN users u ON pa.siswa_user_id = u.id
                WHERE pa.pr_id = ?
                ORDER BY pa.nilai DESC, u.nama
            ", [$pr_id], 'i');
            json_response(['success'=>true,'data'=>$assignments]);
            break;

        case 'add_comment':
            csrf_check();
            $assignment_id = (int)($_POST['assignment_id'] ?? 0);
            $komentar = trim($_POST['komentar_tutor'] ?? '');
            db_query("UPDATE pr_assignment SET komentar_tutor=? WHERE id=?", [$komentar, $assignment_id], 'si');
            json_response(['success'=>true,'message'=>'Komentar disimpan.']);
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
        <div class="relative w-full sm:w-64">
            <input type="text" id="searchInput" placeholder="Cari PR..." class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500">
            <svg class="absolute left-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <button onclick="showCreateModal()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium btn-press flex items-center gap-2 shadow-lg shadow-blue-500/25">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Buat PR
        </button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Judul</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Bank Soal</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Progress</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300 hidden sm:table-cell">Deadline</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Status</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                </tr></thead>
                <tbody id="tableBody"><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Memuat...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div id="paginationContainer"></div>
</div>

<!-- Modal Create PR -->
<div id="modalPR" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-semibold text-gray-800 dark:text-white">Buat PR Baru</h3><button onclick="closeModal('modalPR')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form id="formPR" class="space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Judul PR *</label><input type="text" name="judul" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bank Soal (MCQ) *</label><select id="pr_bank_soal" name="bank_soal_id" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></select></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenjang</label><select name="jenjang" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"><option value="">Semua</option><option value="PRA_SD">Pra SD</option><option value="SD">SD</option><option value="SMP">SMP</option><option value="SMA">SMA</option></select></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deadline</label><input type="datetime-local" name="deadline" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mapel</label><input type="text" name="mapel" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deskripsi</label><textarea name="deskripsi" rows="2" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm resize-none"></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign ke Siswa</label>
                    <div id="siswaCheckboxes" class="max-h-40 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-xl p-3 space-y-2"></div>
                </div>
                <div class="flex gap-3 pt-2"><button type="button" onclick="closeModal('modalPR')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Batal</button><button type="submit" id="btnSubmitPR" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm btn-press">Publish PR</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Results -->
<div id="modalResults" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-semibold text-gray-800 dark:text-white">Hasil PR</h3><button onclick="closeModal('modalResults')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <div id="resultsList" class="space-y-2"></div>
            <button onclick="closeModal('modalResults')" class="w-full mt-4 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Tutup</button>
        </div>
    </div>
</div>


<script>
const BASE='<?= BASE_URL ?>pr.php', CSRF='<?= csrf_token() ?>';
let currentPage=1, searchQuery='', optionsCache=null;

document.addEventListener('DOMContentLoaded', ()=>loadData());
document.getElementById('searchInput').addEventListener('input', debounce(function(){searchQuery=this.value;currentPage=1;loadData();}));

async function loadData(){
    const tb=document.getElementById('tableBody');
    tb.innerHTML='<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div></td></tr>';
    const res=await fetchAPI(`${BASE}?action=list&page=${currentPage}&search=${encodeURIComponent(searchQuery)}`);
    if(res.success&&res.data.length>0){
        tb.innerHTML=res.data.map(r=>{
            const statusBg={'DRAFT':'bg-gray-100 text-gray-700','MENUNGGU_VALIDASI':'bg-yellow-100 text-yellow-700','PUBLISHED':'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300','SELESAI':'bg-blue-100 text-blue-700'};
            return `<tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 table-row-enter">
                <td class="px-4 py-3"><div class="font-medium text-gray-800 dark:text-white">${esc(r.judul)}</div><div class="text-xs text-gray-500">${esc(r.mapel||'-')} &bull; ${esc(r.jenjang||'Semua')}</div></td>
                <td class="px-4 py-3 hidden md:table-cell text-gray-600 dark:text-gray-400 text-xs">${esc(r.bank_soal_judul)}</td>
                <td class="px-4 py-3 text-center"><span class="text-sm font-medium">${r.total_done}/${r.total_assigned}</span></td>
                <td class="px-4 py-3 text-center hidden sm:table-cell text-xs text-gray-500">${r.deadline?r.deadline.substring(0,16).replace('T',' '):'-'}</td>
                <td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs rounded-full ${statusBg[r.status]||''}">${r.status}</span></td>
                <td class="px-4 py-3 text-center"><div class="flex items-center justify-center gap-1">
                    <button onclick="showResults(${r.id})" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="Lihat Hasil"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></button>
                    <button onclick="deletePR(${r.id})" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                </div></td>
            </tr>`;
        }).join('');
    } else { tb.innerHTML='<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada data PR.</td></tr>'; }
    if(res.paging?.total_pages>1){let h=`<div class="flex items-center justify-between mt-4"><div class="text-sm text-gray-500">Hal ${res.paging.current_page}/${res.paging.total_pages}</div><div class="flex gap-1">`;if(res.paging.has_prev)h+=`<button onclick="currentPage--;loadData()" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&laquo;</button>`;if(res.paging.has_next)h+=`<button onclick="currentPage++;loadData()" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&raquo;</button>`;h+='</div></div>';document.getElementById('paginationContainer').innerHTML=h;}else document.getElementById('paginationContainer').innerHTML='';
}

async function showCreateModal(){
    if(!optionsCache) optionsCache=await fetchAPI(`${BASE}?action=get_options`);
    if(optionsCache.success){
        document.getElementById('pr_bank_soal').innerHTML=optionsCache.bank_soal.map(b=>`<option value="${b.id}">${esc(b.judul)} (${esc(b.mapel||'-')})</option>`).join('');
        document.getElementById('siswaCheckboxes').innerHTML=optionsCache.siswa.map(s=>`<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="siswa_ids[]" value="${s.id}" class="rounded border-gray-300"><span>${esc(s.nama)} (${s.jenjang||'-'})</span></label>`).join('');
    }
    document.getElementById('formPR').reset();
    openModal('modalPR');
}

document.getElementById('formPR').addEventListener('submit', async function(e){
    e.preventDefault();const btn=document.getElementById('btnSubmitPR');setButtonLoading(btn,true);
    const fd=new FormData(this);fd.append('action','create');fd.append('csrf_token',CSRF);
    const res=await fetchAPI(BASE,{method:'POST',body:fd});
    setButtonLoading(btn,false);
    if(res.success){closeModal('modalPR');showToast(res.message);loadData();}else showToast(res.message,'error');
});

async function showResults(prId){
    const res=await fetchAPI(`${BASE}?action=get_results&pr_id=${prId}`);
    if(res.success){
        const el=document.getElementById('resultsList');
        if(res.data.length===0){el.innerHTML='<p class="text-sm text-gray-400 text-center">Belum ada assignment.</p>';}
        else{el.innerHTML=res.data.map(r=>{
            const statusBg=r.status==='SUDAH_DIKERJAKAN'?'bg-green-100 text-green-700':'bg-gray-100 text-gray-700';
            return `<div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div><p class="text-sm font-medium text-gray-800 dark:text-white">${esc(r.siswa_nama)}</p><span class="text-xs px-2 py-0.5 rounded-full ${statusBg}">${r.status.replace('_',' ')}</span></div>
                <div class="text-right"><p class="text-lg font-bold ${r.nilai!==null?'text-blue-600':'text-gray-400'}">${r.nilai!==null?r.nilai:'-'}</p></div>
            </div>`;}).join('');}
        openModal('modalResults');
    }
}

async function deletePR(id){if(!confirm('Hapus PR ini?'))return;const fd=new FormData();fd.append('action','delete');fd.append('id',id);fd.append('csrf_token',CSRF);const r=await fetchAPI(BASE,{method:'POST',body:fd});if(r.success){showToast(r.message);loadData();}else showToast(r.message,'error');}
function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
