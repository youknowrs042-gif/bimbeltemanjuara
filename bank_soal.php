<?php
/**
 * BANK_SOAL.PHP - Manajemen Bank Soal & Modul (Admin)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'Bank Soal & Modul');

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'create':
            csrf_check();
            $judul = trim($_POST['judul'] ?? '');
            $tipe = $_POST['tipe'] ?? 'SOAL_MCQ';
            $jenjang = $_POST['jenjang'] ?? null;
            $mapel = trim($_POST['mapel'] ?? '');
            $konten_html = $_POST['konten_html'] ?? null;
            $status = $_POST['status'] ?? 'MENUNGGU_VALIDASI';
            
            if (empty($judul)) json_response(['success'=>false,'message'=>'Judul wajib diisi.']);
            if (!in_array($tipe, ['SOAL_MCQ','MODUL_PDF','MODUL_HTML'])) json_response(['success'=>false,'message'=>'Tipe tidak valid.']);
            
            $file_path = null;
            if ($tipe === 'MODUL_PDF' && isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
                $upload = upload_file($_FILES['file_upload'], 'modul');
                if ($upload['success']) $file_path = $upload['path'];
                else json_response(['success'=>false,'message'=>$upload['message']]);
            }
            
            // Admin langsung DISETUJUI
            if (is_admin()) $status = 'DISETUJUI';
            
            $id = db_insert(
                "INSERT INTO bank_soal (judul, tipe, jenjang, mapel, konten_html, file_path, status, created_by, approved_by, approved_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$judul, $tipe, $jenjang, $mapel, $konten_html, $file_path, $status, current_user_id(), is_admin()?current_user_id():null, is_admin()?date('Y-m-d H:i:s'):null],
                'sssssssi is'
            );
            
            log_activity('CREATE_BANK_SOAL', "Membuat bank soal: $judul");
            json_response(['success'=>true,'message'=>'Bank soal berhasil dibuat.','id'=>$id]);
            break;


        case 'update':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            $judul = trim($_POST['judul'] ?? '');
            $tipe = $_POST['tipe'] ?? 'SOAL_MCQ';
            $jenjang = $_POST['jenjang'] ?? null;
            $mapel = trim($_POST['mapel'] ?? '');
            $konten_html = $_POST['konten_html'] ?? null;
            
            if (!$id || empty($judul)) json_response(['success'=>false,'message'=>'Data tidak valid.']);
            
            $file_path = null;
            if ($tipe === 'MODUL_PDF' && isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
                $upload = upload_file($_FILES['file_upload'], 'modul');
                if ($upload['success']) $file_path = $upload['path'];
            }
            
            $sql = "UPDATE bank_soal SET judul=?, tipe=?, jenjang=?, mapel=?, konten_html=?";
            $params = [$judul, $tipe, $jenjang, $mapel, $konten_html];
            $types = 'sssss';
            if ($file_path) { $sql .= ", file_path=?"; $params[] = $file_path; $types .= 's'; }
            $sql .= " WHERE id=?"; $params[] = $id; $types .= 'i';
            
            db_query($sql, $params, $types);
            log_activity('UPDATE_BANK_SOAL', "Update bank soal ID: $id");
            json_response(['success'=>true,'message'=>'Data berhasil diupdate.']);
            break;

        case 'approve':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            db_query("UPDATE bank_soal SET status='DISETUJUI', approved_by=?, approved_at=NOW() WHERE id=?", [current_user_id(),$id], 'ii');
            log_activity('APPROVE_BANK_SOAL', "Approve bank soal ID: $id");
            json_response(['success'=>true,'message'=>'Bank soal disetujui.']);
            break;

        case 'reject':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            db_query("UPDATE bank_soal SET status='DITOLAK' WHERE id=?", [$id], 'i');
            json_response(['success'=>true,'message'=>'Bank soal ditolak.']);
            break;

        case 'delete':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            db_query("DELETE FROM bank_soal WHERE id=?", [$id], 'i');
            log_activity('DELETE_BANK_SOAL', "Hapus bank soal ID: $id");
            json_response(['success'=>true,'message'=>'Berhasil dihapus.']);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $data = db_fetch_one("SELECT bs.*, u.nama as creator_nama FROM bank_soal bs JOIN users u ON bs.created_by=u.id WHERE bs.id=?", [$id], 'i');
            if (!$data) json_response(['success'=>false,'message'=>'Tidak ditemukan.']);
            // Get soal if MCQ
            $soal_list = [];
            if ($data['tipe'] === 'SOAL_MCQ') {
                $soal_list = db_fetch_all("SELECT * FROM soal WHERE bank_soal_id=? ORDER BY urutan, id", [$id], 'i');
            }
            $data['soal'] = $soal_list;
            json_response(['success'=>true,'data'=>$data]);
            break;


        case 'list':
            $search = trim($_GET['search'] ?? '');
            $filter_tipe = $_GET['filter_tipe'] ?? '';
            $filter_status = $_GET['filter_status'] ?? '';
            $page = max(1,(int)($_GET['page']??1));
            
            $where = "WHERE 1=1"; $params=[]; $types='';
            if ($search) { $where .= " AND (bs.judul LIKE ? OR bs.mapel LIKE ?)"; $s="%$search%"; $params=[$s,$s]; $types='ss'; }
            if ($filter_tipe) { $where .= " AND bs.tipe=?"; $params[]=$filter_tipe; $types.='s'; }
            if ($filter_status) { $where .= " AND bs.status=?"; $params[]=$filter_status; $types.='s'; }
            
            $total = db_count("SELECT COUNT(*) FROM bank_soal bs $where", $params, $types);
            $paging = paginate($total, 10, $page);
            
            $rows = db_fetch_all("
                SELECT bs.*, u.nama as creator_nama,
                       (SELECT COUNT(*) FROM soal WHERE bank_soal_id = bs.id) as jumlah_soal
                FROM bank_soal bs
                JOIN users u ON bs.created_by = u.id
                $where
                ORDER BY bs.created_at DESC
                LIMIT {$paging['per_page']} OFFSET {$paging['offset']}
            ", $params, $types);
            
            json_response(['success'=>true,'data'=>$rows,'paging'=>$paging]);
            break;

        // === SOAL MCQ CRUD ===
        case 'add_soal':
            csrf_check();
            $bank_id = (int)($_POST['bank_soal_id'] ?? 0);
            $pertanyaan = trim($_POST['pertanyaan'] ?? '');
            $opsi_a = trim($_POST['opsi_a'] ?? '');
            $opsi_b = trim($_POST['opsi_b'] ?? '');
            $opsi_c = trim($_POST['opsi_c'] ?? '');
            $opsi_d = trim($_POST['opsi_d'] ?? '');
            $opsi_e = trim($_POST['opsi_e'] ?? '') ?: null;
            $jawaban = $_POST['jawaban_benar'] ?? '';
            $pembahasan = trim($_POST['pembahasan'] ?? '') ?: null;
            
            if (!$bank_id || empty($pertanyaan) || empty($opsi_a) || empty($opsi_b) || empty($jawaban)) {
                json_response(['success'=>false,'message'=>'Pertanyaan, minimal 2 opsi, dan jawaban benar wajib diisi.']);
            }
            
            $urutan = db_count("SELECT COUNT(*) FROM soal WHERE bank_soal_id=?", [$bank_id], 'i') + 1;
            
            $soal_id = db_insert(
                "INSERT INTO soal (bank_soal_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, jawaban_benar, pembahasan, urutan) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$bank_id, $pertanyaan, $opsi_a, $opsi_b, $opsi_c, $opsi_d, $opsi_e, $jawaban, $pembahasan, $urutan],
                'issssssssi'
            );
            
            json_response(['success'=>true,'message'=>'Soal berhasil ditambahkan.','id'=>$soal_id]);
            break;

        case 'update_soal':
            csrf_check();
            $id = (int)($_POST['soal_id'] ?? 0);
            $pertanyaan = trim($_POST['pertanyaan'] ?? '');
            $opsi_a = trim($_POST['opsi_a'] ?? '');
            $opsi_b = trim($_POST['opsi_b'] ?? '');
            $opsi_c = trim($_POST['opsi_c'] ?? '');
            $opsi_d = trim($_POST['opsi_d'] ?? '');
            $opsi_e = trim($_POST['opsi_e'] ?? '') ?: null;
            $jawaban = $_POST['jawaban_benar'] ?? '';
            $pembahasan = trim($_POST['pembahasan'] ?? '') ?: null;
            
            db_query("UPDATE soal SET pertanyaan=?, opsi_a=?, opsi_b=?, opsi_c=?, opsi_d=?, opsi_e=?, jawaban_benar=?, pembahasan=? WHERE id=?",
                [$pertanyaan,$opsi_a,$opsi_b,$opsi_c,$opsi_d,$opsi_e,$jawaban,$pembahasan,$id], 'ssssssssi');
            json_response(['success'=>true,'message'=>'Soal diupdate.']);
            break;

        case 'delete_soal':
            csrf_check();
            $id = (int)($_POST['soal_id'] ?? 0);
            db_query("DELETE FROM soal WHERE id=?", [$id], 'i');
            json_response(['success'=>true,'message'=>'Soal dihapus.']);
            break;

        case 'get_soal':
            $id = (int)($_GET['soal_id'] ?? 0);
            $soal = db_fetch_one("SELECT * FROM soal WHERE id=?", [$id], 'i');
            json_response(['success'=>(bool)$soal,'data'=>$soal]);
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
                <input type="text" id="searchInput" placeholder="Cari judul/mapel..." class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500">
                <svg class="absolute left-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <select id="filterTipe" onchange="loadData()" class="px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm">
                <option value="">Semua Tipe</option>
                <option value="SOAL_MCQ">Soal MCQ</option>
                <option value="MODUL_PDF">Modul PDF</option>
                <option value="MODUL_HTML">Modul HTML</option>
            </select>
            <select id="filterStatus" onchange="loadData()" class="px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm">
                <option value="">Semua Status</option>
                <option value="MENUNGGU_VALIDASI">Menunggu</option>
                <option value="DISETUJUI">Disetujui</option>
                <option value="DITOLAK">Ditolak</option>
            </select>
        </div>
        <button onclick="showCreateModal()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium btn-press flex items-center gap-2 shadow-lg shadow-blue-500/25">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Bank Soal
        </button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Judul</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Tipe</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden lg:table-cell">Jenjang / Mapel</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Soal</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Status</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                </tr></thead>
                <tbody id="tableBody"><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Memuat...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div id="paginationContainer"></div>
</div>


<!-- Modal Create/Edit Bank Soal -->
<div id="modalBankSoal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5"><h3 id="modalTitle" class="text-lg font-semibold text-gray-800 dark:text-white">Tambah Bank Soal</h3><button onclick="closeModal('modalBankSoal')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form id="formBankSoal" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" id="bs_id" name="id" value="">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Judul *</label><input type="text" id="bs_judul" name="judul" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500"></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipe *</label>
                        <select id="bs_tipe" name="tipe" required onchange="toggleTipeFields()" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm">
                            <option value="SOAL_MCQ">Soal MCQ</option><option value="MODUL_PDF">Modul PDF</option><option value="MODUL_HTML">Modul HTML</option>
                        </select></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenjang</label>
                        <select id="bs_jenjang" name="jenjang" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm">
                            <option value="">- Semua -</option><option value="PRA_SD">Pra SD</option><option value="SD">SD</option><option value="SMP">SMP</option><option value="SMA">SMA</option>
                        </select></div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mapel</label><input type="text" id="bs_mapel" name="mapel" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                <div id="fieldPdf" class="hidden"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">File PDF</label><input type="file" name="file_upload" accept=".pdf" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                <div id="fieldHtml" class="hidden"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Konten HTML</label><textarea id="bs_konten" name="konten_html" rows="5" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm resize-y font-mono"></textarea></div>
                <div class="flex gap-3 pt-2"><button type="button" onclick="closeModal('modalBankSoal')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Batal</button><button type="submit" id="btnSubmitBS" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm btn-press">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Manage Soal -->
<div id="modalSoal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5"><h3 id="soalTitle" class="text-lg font-semibold text-gray-800 dark:text-white">Kelola Soal</h3><button onclick="closeModal('modalSoal')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            
            <!-- Add Soal Form -->
            <form id="formSoal" class="space-y-3 bg-gray-50 dark:bg-gray-700/50 p-4 rounded-xl mb-4">
                <input type="hidden" id="soal_bank_id" name="bank_soal_id" value="">
                <input type="hidden" id="soal_edit_id" name="soal_id" value="">
                <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Pertanyaan *</label><textarea id="s_pertanyaan" name="pertanyaan" rows="2" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm resize-none"></textarea></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Opsi A *</label><input type="text" id="s_a" name="opsi_a" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Opsi B *</label><input type="text" id="s_b" name="opsi_b" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Opsi C</label><input type="text" id="s_c" name="opsi_c" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Opsi D</label><input type="text" id="s_d" name="opsi_d" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Opsi E</label><input type="text" id="s_e" name="opsi_e" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Jawaban Benar *</label><select id="s_jawaban" name="jawaban_benar" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option><option value="E">E</option></select></div>
                </div>
                <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Pembahasan</label><textarea id="s_pembahasan" name="pembahasan" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm resize-none"></textarea></div>
                <button type="submit" id="btnAddSoal" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm btn-press">Tambah Soal</button>
            </form>

            <!-- Soal List -->
            <div id="soalList" class="space-y-3"></div>
        </div>
    </div>
</div>


<script>
const BASE='<?= BASE_URL ?>bank_soal.php', CSRF='<?= csrf_token() ?>';
let currentPage=1, searchQuery='';

document.addEventListener('DOMContentLoaded', ()=>loadData());
document.getElementById('searchInput').addEventListener('input', debounce(function(){searchQuery=this.value;currentPage=1;loadData();}));

function toggleTipeFields() {
    const tipe = document.getElementById('bs_tipe').value;
    document.getElementById('fieldPdf').classList.toggle('hidden', tipe !== 'MODUL_PDF');
    document.getElementById('fieldHtml').classList.toggle('hidden', tipe !== 'MODUL_HTML');
}

async function loadData() {
    const ft=document.getElementById('filterTipe').value;
    const fs=document.getElementById('filterStatus').value;
    const tb=document.getElementById('tableBody');
    tb.innerHTML='<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div></td></tr>';
    
    const res=await fetchAPI(`${BASE}?action=list&page=${currentPage}&search=${encodeURIComponent(searchQuery)}&filter_tipe=${ft}&filter_status=${fs}`);
    if(res.success&&res.data.length>0){
        tb.innerHTML=res.data.map(r=>{
            const tipeLabel={'SOAL_MCQ':'Soal MCQ','MODUL_PDF':'Modul PDF','MODUL_HTML':'Modul HTML'};
            const tipeBg={'SOAL_MCQ':'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300','MODUL_PDF':'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300','MODUL_HTML':'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'};
            const statusBg={'MENUNGGU_VALIDASI':'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300','DISETUJUI':'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300','DITOLAK':'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'};
            return `<tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 table-row-enter">
                <td class="px-4 py-3"><div class="font-medium text-gray-800 dark:text-white">${esc(r.judul)}</div><div class="text-xs text-gray-500">oleh ${esc(r.creator_nama)}</div></td>
                <td class="px-4 py-3 hidden md:table-cell"><span class="px-2 py-1 text-xs rounded-full ${tipeBg[r.tipe]||''}">${tipeLabel[r.tipe]||r.tipe}</span></td>
                <td class="px-4 py-3 hidden lg:table-cell text-gray-600 dark:text-gray-400">${esc(r.jenjang||'-')} / ${esc(r.mapel||'-')}</td>
                <td class="px-4 py-3 text-center"><span class="font-bold text-gray-800 dark:text-white">${r.jumlah_soal}</span></td>
                <td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs rounded-full ${statusBg[r.status]||''}">${r.status.replace('_',' ')}</span></td>
                <td class="px-4 py-3 text-center"><div class="flex items-center justify-center gap-1">
                    ${r.tipe==='SOAL_MCQ'?`<button onclick="manageSoal(${r.id},'${esc(r.judul)}')" class="p-1.5 text-purple-600 hover:bg-purple-50 rounded-lg" title="Kelola Soal"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></button>`:''}
                    ${r.status==='MENUNGGU_VALIDASI'?`<button onclick="approveBS(${r.id})" class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg" title="Approve"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></button>`:''}
                    <button onclick="editBS(${r.id})" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                    <button onclick="deleteBS(${r.id})" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                </div></td>
            </tr>`;
        }).join('');
    } else { tb.innerHTML='<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada data.</td></tr>'; }
    if(res.paging?.total_pages>1){let h=`<div class="flex items-center justify-between mt-4"><div class="text-sm text-gray-500">Hal ${res.paging.current_page}/${res.paging.total_pages} (${res.paging.total})</div><div class="flex gap-1">`;if(res.paging.has_prev)h+=`<button onclick="currentPage--;loadData()" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&laquo;</button>`;if(res.paging.has_next)h+=`<button onclick="currentPage++;loadData()" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&raquo;</button>`;h+='</div></div>';document.getElementById('paginationContainer').innerHTML=h;}else document.getElementById('paginationContainer').innerHTML='';
}

function showCreateModal(){document.getElementById('formBankSoal').reset();document.getElementById('bs_id').value='';document.getElementById('modalTitle').textContent='Tambah Bank Soal';toggleTipeFields();openModal('modalBankSoal');}

async function editBS(id){
    const res=await fetchAPI(`${BASE}?action=get&id=${id}`);
    if(res.success){const d=res.data;document.getElementById('bs_id').value=d.id;document.getElementById('bs_judul').value=d.judul;document.getElementById('bs_tipe').value=d.tipe;document.getElementById('bs_jenjang').value=d.jenjang||'';document.getElementById('bs_mapel').value=d.mapel||'';document.getElementById('bs_konten').value=d.konten_html||'';document.getElementById('modalTitle').textContent='Edit Bank Soal';toggleTipeFields();openModal('modalBankSoal');}
}

document.getElementById('formBankSoal').addEventListener('submit', async function(e){
    e.preventDefault();const btn=document.getElementById('btnSubmitBS');setButtonLoading(btn,true);
    const fd=new FormData(this);fd.append('action',fd.get('id')?'update':'create');fd.append('csrf_token',CSRF);
    const res=await fetchAPI(BASE,{method:'POST',body:fd});
    setButtonLoading(btn,false);
    if(res.success){closeModal('modalBankSoal');showToast(res.message);loadData();}else showToast(res.message,'error');
});

async function approveBS(id){if(!confirm('Setujui bank soal ini?'))return;const fd=new FormData();fd.append('action','approve');fd.append('id',id);fd.append('csrf_token',CSRF);const r=await fetchAPI(BASE,{method:'POST',body:fd});if(r.success){showToast(r.message);loadData();}else showToast(r.message,'error');}
async function deleteBS(id){if(!confirm('Hapus bank soal ini beserta semua soalnya?'))return;const fd=new FormData();fd.append('action','delete');fd.append('id',id);fd.append('csrf_token',CSRF);const r=await fetchAPI(BASE,{method:'POST',body:fd});if(r.success){showToast(r.message);loadData();}else showToast(r.message,'error');}


// === SOAL MANAGEMENT ===
async function manageSoal(bankId, title) {
    document.getElementById('soal_bank_id').value = bankId;
    document.getElementById('soal_edit_id').value = '';
    document.getElementById('soalTitle').textContent = 'Soal: ' + title;
    document.getElementById('formSoal').reset();
    document.getElementById('soal_bank_id').value = bankId;
    document.getElementById('btnAddSoal').textContent = 'Tambah Soal';
    await loadSoalList(bankId);
    openModal('modalSoal');
}

async function loadSoalList(bankId) {
    const res = await fetchAPI(`${BASE}?action=get&id=${bankId}`);
    if (res.success && res.data.soal) {
        const list = document.getElementById('soalList');
        if (res.data.soal.length === 0) {
            list.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">Belum ada soal. Tambahkan soal di form atas.</p>';
        } else {
            list.innerHTML = res.data.soal.map((s, i) => `
                <div class="p-3 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg">
                    <div class="flex justify-between items-start">
                        <div class="flex-1"><p class="text-sm font-medium text-gray-800 dark:text-white">${i+1}. ${esc(s.pertanyaan)}</p>
                            <div class="mt-1 grid grid-cols-2 gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <span class="${s.jawaban_benar==='A'?'font-bold text-green-600':''}">A: ${esc(s.opsi_a)}</span>
                                <span class="${s.jawaban_benar==='B'?'font-bold text-green-600':''}">B: ${esc(s.opsi_b)}</span>
                                ${s.opsi_c?`<span class="${s.jawaban_benar==='C'?'font-bold text-green-600':''}">C: ${esc(s.opsi_c)}</span>`:''}
                                ${s.opsi_d?`<span class="${s.jawaban_benar==='D'?'font-bold text-green-600':''}">D: ${esc(s.opsi_d)}</span>`:''}
                                ${s.opsi_e?`<span class="${s.jawaban_benar==='E'?'font-bold text-green-600':''}">E: ${esc(s.opsi_e)}</span>`:''}
                            </div>
                            <p class="text-xs text-green-600 mt-1">Jawaban: ${s.jawaban_benar}</p>
                        </div>
                        <div class="flex gap-1 ml-2">
                            <button onclick="editSoal(${s.id})" class="p-1 text-blue-600 hover:bg-blue-50 rounded"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                            <button onclick="deleteSoal(${s.id},${bankId})" class="p-1 text-red-600 hover:bg-red-50 rounded"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                        </div>
                    </div>
                </div>`).join('');
        }
    }
}

document.getElementById('formSoal').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const editId = fd.get('soal_id');
    fd.append('action', editId ? 'update_soal' : 'add_soal');
    fd.append('csrf_token', CSRF);
    const res = await fetchAPI(BASE, {method:'POST',body:fd});
    if (res.success) {
        showToast(res.message);
        const bankId = document.getElementById('soal_bank_id').value;
        this.reset();
        document.getElementById('soal_bank_id').value = bankId;
        document.getElementById('soal_edit_id').value = '';
        document.getElementById('btnAddSoal').textContent = 'Tambah Soal';
        await loadSoalList(bankId);
        loadData();
    } else showToast(res.message, 'error');
});

async function editSoal(id) {
    const res = await fetchAPI(`${BASE}?action=get_soal&soal_id=${id}`);
    if (res.success) {
        const s = res.data;
        document.getElementById('soal_edit_id').value = s.id;
        document.getElementById('s_pertanyaan').value = s.pertanyaan;
        document.getElementById('s_a').value = s.opsi_a;
        document.getElementById('s_b').value = s.opsi_b;
        document.getElementById('s_c').value = s.opsi_c || '';
        document.getElementById('s_d').value = s.opsi_d || '';
        document.getElementById('s_e').value = s.opsi_e || '';
        document.getElementById('s_jawaban').value = s.jawaban_benar;
        document.getElementById('s_pembahasan').value = s.pembahasan || '';
        document.getElementById('btnAddSoal').textContent = 'Update Soal';
    }
}

async function deleteSoal(id, bankId) {
    if (!confirm('Hapus soal ini?')) return;
    const fd = new FormData(); fd.append('action','delete_soal'); fd.append('soal_id',id); fd.append('csrf_token',CSRF);
    const res = await fetchAPI(BASE, {method:'POST',body:fd});
    if (res.success) { showToast(res.message); loadSoalList(bankId); loadData(); } else showToast(res.message,'error');
}

function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
