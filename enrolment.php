<?php
/**
 * ENROLMENT.PHP - Pendaftaran Siswa ke Program+Paket (Admin)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'Enrolment');

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'create':
            csrf_check();
            $siswa_id = (int)($_POST['siswa_user_id'] ?? 0);
            $program_id = (int)($_POST['program_id'] ?? 0);
            $paket_id = (int)($_POST['paket_id'] ?? 0);
            $tutor_id = (int)($_POST['tutor_user_id'] ?? 0) ?: null;
            $jenjang = $_POST['jenjang'] ?? '';
            $mapel = trim($_POST['mapel'] ?? '');
            $tanggal_mulai = $_POST['tanggal_mulai'] ?? null;
            
            if (!$siswa_id || !$program_id || !$paket_id || empty($jenjang)) {
                json_response(['success' => false, 'message' => 'Siswa, program, paket, dan jenjang wajib diisi.']);
            }
            
            // Get jumlah pertemuan dari paket
            $paket = db_fetch_one("SELECT jumlah_pertemuan FROM paket WHERE id = ?", [$paket_id], 'i');
            if (!$paket) json_response(['success' => false, 'message' => 'Paket tidak ditemukan.']);
            
            $kuota = (int)$paket['jumlah_pertemuan'];


            $enrol_id = db_insert(
                "INSERT INTO enrolment (siswa_user_id, program_id, paket_id, tutor_user_id, jenjang, mapel, kuota_awal, kuota_sisa, status, tanggal_mulai) VALUES (?,?,?,?,?,?,?,?,'AKTIF',?)",
                [$siswa_id, $program_id, $paket_id, $tutor_id, $jenjang, $mapel, $kuota, $kuota, $tanggal_mulai],
                'iiiissii s'
            );
            
            if ($enrol_id) {
                // Get harga untuk auto-create invoice
                $harga_row = db_fetch_one("SELECT harga FROM harga_paket WHERE program_id=? AND jenjang=? AND paket_id=?", [$program_id, $jenjang, $paket_id], 'isi');
                $harga = $harga_row ? (float)$harga_row['harga'] : 0;
                
                // Auto-create pembayaran/invoice
                $no_invoice = generate_invoice_number();
                db_insert(
                    "INSERT INTO pembayaran (enrolment_id, no_invoice, siswa_user_id, jumlah, status) VALUES (?,?,?,?,'BELUM_BAYAR')",
                    [$enrol_id, $no_invoice, $siswa_id, $harga],
                    'isid'
                );
                
                // Set siswa status TERKUNCI karena belum bayar
                db_query("UPDATE users SET status = 'TERKUNCI' WHERE id = ? AND role = 'SISWA'", [$siswa_id], 'i');
                
                log_activity('CREATE_ENROLMENT', "Enrolment siswa ID $siswa_id ke program ID $program_id");
                json_response(['success' => true, 'message' => "Enrolment berhasil. Invoice $no_invoice dibuat otomatis."]);
            }
            json_response(['success' => false, 'message' => 'Gagal menyimpan.']);
            break;

        case 'update_status':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['AKTIF','SELESAI','BATAL'])) json_response(['success'=>false,'message'=>'Status tidak valid.']);
            db_query("UPDATE enrolment SET status=? WHERE id=?", [$status, $id], 'si');
            log_activity('UPDATE_ENROLMENT_STATUS', "Enrolment $id -> $status");
            json_response(['success' => true, 'message' => 'Status diupdate.']);
            break;

        case 'delete':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            db_query("DELETE FROM enrolment WHERE id = ?", [$id], 'i');
            log_activity('DELETE_ENROLMENT', "Hapus enrolment ID: $id");
            json_response(['success' => true, 'message' => 'Enrolment dihapus.']);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $data = db_fetch_one("
                SELECT e.*, u.nama as siswa_nama, t.nama as tutor_nama, p.nama_program, pk.nama_paket
                FROM enrolment e
                JOIN users u ON e.siswa_user_id = u.id
                LEFT JOIN users t ON e.tutor_user_id = t.id
                JOIN program p ON e.program_id = p.id
                JOIN paket pk ON e.paket_id = pk.id
                WHERE e.id = ?
            ", [$id], 'i');
            json_response(['success' => (bool)$data, 'data' => $data]);
            break;


        case 'list':
            $search = trim($_GET['search'] ?? '');
            $filter_status = $_GET['filter_status'] ?? '';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = 10;
            
            $where = "WHERE 1=1";
            $params = []; $types = '';
            
            if ($search) {
                $where .= " AND (u.nama LIKE ? OR p.nama_program LIKE ? OR e.mapel LIKE ?)";
                $s = "%$search%"; $params = array_merge($params, [$s,$s,$s]); $types .= 'sss';
            }
            if ($filter_status) {
                $where .= " AND e.status = ?";
                $params[] = $filter_status; $types .= 's';
            }
            
            $total = db_count("
                SELECT COUNT(*) FROM enrolment e
                JOIN users u ON e.siswa_user_id = u.id
                JOIN program p ON e.program_id = p.id
                $where
            ", $params, $types);
            
            $paging = paginate($total, $per_page, $page);
            
            $rows = db_fetch_all("
                SELECT e.id, e.siswa_user_id, e.jenjang, e.mapel, e.kuota_awal, e.kuota_sisa, e.status, e.tanggal_mulai, e.created_at,
                       u.nama as siswa_nama,
                       t.nama as tutor_nama,
                       p.nama_program, p.tipe as program_tipe,
                       pk.nama_paket
                FROM enrolment e
                JOIN users u ON e.siswa_user_id = u.id
                LEFT JOIN users t ON e.tutor_user_id = t.id
                JOIN program p ON e.program_id = p.id
                JOIN paket pk ON e.paket_id = pk.id
                $where
                ORDER BY e.created_at DESC
                LIMIT {$paging['per_page']} OFFSET {$paging['offset']}
            ", $params, $types);
            
            json_response(['success' => true, 'data' => $rows, 'paging' => $paging]);
            break;

        case 'get_options':
            $siswa_list = db_fetch_all("SELECT u.id, u.nama, s.jenjang FROM users u LEFT JOIN siswa s ON u.id=s.user_id WHERE u.role='SISWA' AND u.status!='NONAKTIF' ORDER BY u.nama");
            $tutor_list = db_fetch_all("SELECT u.id, u.nama, t.mapel FROM users u LEFT JOIN tutor t ON u.id=t.user_id WHERE u.role='TUTOR' AND u.status='AKTIF' ORDER BY u.nama");
            $program_list = db_fetch_all("SELECT id, nama_program, tipe FROM program WHERE status='AKTIF' ORDER BY nama_program");
            $paket_list = db_fetch_all("SELECT id, nama_paket, jumlah_pertemuan FROM paket WHERE status='AKTIF' ORDER BY jumlah_pertemuan");
            json_response(['success'=>true, 'siswa'=>$siswa_list, 'tutor'=>$tutor_list, 'program'=>$program_list, 'paket'=>$paket_list]);
            break;

        case 'get_harga':
            $program_id = (int)($_GET['program_id'] ?? 0);
            $jenjang = $_GET['jenjang'] ?? '';
            $paket_id = (int)($_GET['paket_id'] ?? 0);
            $row = db_fetch_one("SELECT harga FROM harga_paket WHERE program_id=? AND jenjang=? AND paket_id=?", [$program_id,$jenjang,$paket_id], 'isi');
            json_response(['success'=>true, 'harga'=> $row ? (float)$row['harga'] : 0]);
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
            <div class="relative w-full sm:w-64">
                <input type="text" id="searchInput" placeholder="Cari siswa/program..." class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                <svg class="absolute left-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <select id="filterStatus" onchange="loadData()" class="px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                <option value="">Semua Status</option>
                <option value="AKTIF">Aktif</option>
                <option value="SELESAI">Selesai</option>
                <option value="BATAL">Batal</option>
            </select>
        </div>
        <button onclick="showCreateModal()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium btn-press flex items-center gap-2 shadow-lg shadow-blue-500/25">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Enrolment
        </button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Siswa</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Program / Paket</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden lg:table-cell">Tutor</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Kuota</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Status</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                </tr></thead>
                <tbody id="tableBody"><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Memuat...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div id="paginationContainer"></div>
</div>


<!-- Modal Create Enrolment -->
<div id="modalEnrol" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Tambah Enrolment</h3>
                <button onclick="closeModal('modalEnrol')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form id="formEnrol" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Siswa *</label>
                    <select id="f_siswa" name="siswa_user_id" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm" onchange="autoFillJenjang()"></select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Program *</label>
                        <select id="f_program" name="program_id" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm" onchange="calcHarga()"></select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paket *</label>
                        <select id="f_paket" name="paket_id" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm" onchange="calcHarga()"></select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenjang *</label>
                        <select id="f_jenjang" name="jenjang" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm" onchange="calcHarga()">
                            <option value="PRA_SD">Pra SD</option><option value="SD">SD</option><option value="SMP">SMP</option><option value="SMA">SMA</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tutor</label>
                        <select id="f_tutor" name="tutor_user_id" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm">
                            <option value="">- Belum ditentukan -</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mapel</label>
                        <input type="text" id="f_mapel" name="mapel" placeholder="Matematika, dll" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Mulai</label>
                        <input type="date" id="f_tgl_mulai" name="tanggal_mulai" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm">
                    </div>
                </div>
                <!-- Harga Preview -->
                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
                    <p class="text-sm text-blue-700 dark:text-blue-300">Harga Paket: <strong id="hargaPreview">-</strong></p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModal('modalEnrol')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Batal</button>
                    <button type="submit" id="btnSubmit" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium btn-press">Daftarkan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
const BASE = '<?= BASE_URL ?>enrolment.php', CSRF = '<?= csrf_token() ?>';
let currentPage = 1, searchQuery = '', optionsCache = null;

document.addEventListener('DOMContentLoaded', () => loadData());
document.getElementById('searchInput').addEventListener('input', debounce(function(){ searchQuery=this.value; currentPage=1; loadData(); }));

async function loadData() {
    const filterStatus = document.getElementById('filterStatus').value;
    const tb = document.getElementById('tableBody');
    tb.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div></td></tr>';
    
    const res = await fetchAPI(`${BASE}?action=list&page=${currentPage}&search=${encodeURIComponent(searchQuery)}&filter_status=${filterStatus}`);
    if (res.success && res.data.length > 0) {
        tb.innerHTML = res.data.map(r => {
            const pct = r.kuota_awal > 0 ? Math.round((r.kuota_sisa / r.kuota_awal) * 100) : 0;
            const statusClass = r.status === 'AKTIF' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : r.status === 'SELESAI' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300';
            return `<tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors table-row-enter">
                <td class="px-4 py-3"><div class="font-medium text-gray-800 dark:text-white">${esc(r.siswa_nama)}</div><div class="text-xs text-gray-500">${esc(r.jenjang)} &bull; ${esc(r.mapel||'-')}</div></td>
                <td class="px-4 py-3 hidden md:table-cell"><div class="text-gray-800 dark:text-white">${esc(r.nama_program)}</div><div class="text-xs text-gray-500">${esc(r.nama_paket)}</div></td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 hidden lg:table-cell">${esc(r.tutor_nama||'Belum ada')}</td>
                <td class="px-4 py-3 text-center"><div class="flex flex-col items-center"><span class="text-sm font-medium text-gray-800 dark:text-white">${r.kuota_sisa}/${r.kuota_awal}</span><div class="w-16 h-1.5 bg-gray-200 dark:bg-gray-600 rounded-full mt-1"><div class="h-1.5 bg-blue-500 rounded-full" style="width:${pct}%"></div></div></div></td>
                <td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs rounded-full ${statusClass}">${r.status}</span></td>
                <td class="px-4 py-3 text-center"><div class="flex items-center justify-center gap-1">
                    ${r.status==='AKTIF' ? `<button onclick="updateStatus(${r.id},'SELESAI')" class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg" title="Selesaikan"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></button>` : ''}
                    <button onclick="deleteEnrol(${r.id})" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Hapus"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                </div></td>
            </tr>`;
        }).join('');
    } else {
        tb.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada data enrolment.</td></tr>';
    }
    if (res.paging?.total_pages > 1) renderPagination(res.paging); else document.getElementById('paginationContainer').innerHTML = '';
}

function renderPagination(p) {
    let h = `<div class="flex flex-col sm:flex-row items-center justify-between mt-4 gap-3"><div class="text-sm text-gray-600 dark:text-gray-400">Hal ${p.current_page}/${p.total_pages} (${p.total})</div><div class="flex gap-1">`;
    if (p.has_prev) h += `<button onclick="goPage(${p.current_page-1})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&laquo;</button>`;
    for (let i = Math.max(1,p.current_page-2); i <= Math.min(p.total_pages,p.current_page+2); i++) h += i===p.current_page ? `<span class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg">${i}</span>` : `<button onclick="goPage(${i})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">${i}</button>`;
    if (p.has_next) h += `<button onclick="goPage(${p.current_page+1})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&raquo;</button>`;
    h += '</div></div>'; document.getElementById('paginationContainer').innerHTML = h;
}
function goPage(p) { currentPage = p; loadData(); }


async function showCreateModal() {
    if (!optionsCache) {
        optionsCache = await fetchAPI(`${BASE}?action=get_options`);
    }
    if (optionsCache.success) {
        document.getElementById('f_siswa').innerHTML = '<option value="">- Pilih Siswa -</option>' + optionsCache.siswa.map(s => `<option value="${s.id}" data-jenjang="${s.jenjang||''}">${esc(s.nama)} (${s.jenjang||'-'})</option>`).join('');
        document.getElementById('f_program').innerHTML = optionsCache.program.map(p => `<option value="${p.id}">${esc(p.nama_program)} - ${p.tipe}</option>`).join('');
        document.getElementById('f_paket').innerHTML = optionsCache.paket.map(p => `<option value="${p.id}">${esc(p.nama_paket)} (${p.jumlah_pertemuan}x)</option>`).join('');
        document.getElementById('f_tutor').innerHTML = '<option value="">- Belum ditentukan -</option>' + optionsCache.tutor.map(t => `<option value="${t.id}">${esc(t.nama)} (${t.mapel||'-'})</option>`).join('');
    }
    document.getElementById('formEnrol').reset();
    document.getElementById('hargaPreview').textContent = '-';
    openModal('modalEnrol');
}

function autoFillJenjang() {
    const sel = document.getElementById('f_siswa');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.jenjang) {
        document.getElementById('f_jenjang').value = opt.dataset.jenjang;
    }
    calcHarga();
}

async function calcHarga() {
    const program_id = document.getElementById('f_program').value;
    const jenjang = document.getElementById('f_jenjang').value;
    const paket_id = document.getElementById('f_paket').value;
    if (program_id && jenjang && paket_id) {
        const res = await fetchAPI(`${BASE}?action=get_harga&program_id=${program_id}&jenjang=${jenjang}&paket_id=${paket_id}`);
        if (res.success) {
            document.getElementById('hargaPreview').textContent = res.harga > 0 ? 'Rp ' + Number(res.harga).toLocaleString('id-ID') : 'Belum diset';
        }
    }
}

document.getElementById('formEnrol').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmit');
    setButtonLoading(btn, true);
    const fd = new FormData(this);
    fd.append('action', 'create');
    fd.append('csrf_token', CSRF);
    const res = await fetchAPI(BASE, {method:'POST', body:fd});
    setButtonLoading(btn, false);
    if (res.success) { closeModal('modalEnrol'); showToast(res.message); loadData(); }
    else showToast(res.message, 'error');
});

async function updateStatus(id, status) {
    if (!confirm(`Ubah status menjadi ${status}?`)) return;
    const fd = new FormData(); fd.append('action','update_status'); fd.append('id',id); fd.append('status',status); fd.append('csrf_token',CSRF);
    const res = await fetchAPI(BASE, {method:'POST',body:fd});
    if (res.success) { showToast(res.message); loadData(); } else showToast(res.message,'error');
}

async function deleteEnrol(id) {
    if (!confirm('Hapus enrolment ini?')) return;
    const fd = new FormData(); fd.append('action','delete'); fd.append('id',id); fd.append('csrf_token',CSRF);
    const res = await fetchAPI(BASE, {method:'POST',body:fd});
    if (res.success) { showToast(res.message); loadData(); } else showToast(res.message,'error');
}

function esc(s) { if(!s) return ''; const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
