<?php
/**
 * TUTOR.PHP - CRUD Manajemen Tutor (Admin Only)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'Manajemen Tutor');

// Handle AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'create':
            csrf_check();
            $nama = trim($_POST['nama'] ?? '');
            $jk = $_POST['jk'] ?? '';
            $no_hp = trim($_POST['no_hp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $domisili = trim($_POST['domisili'] ?? '');
            $pendidikan = trim($_POST['pendidikan'] ?? '');
            $mapel = trim($_POST['mapel'] ?? '');
            $nama_bank = trim($_POST['nama_bank'] ?? '');
            $no_rekening = trim($_POST['no_rekening'] ?? '');
            $atas_nama_rek = trim($_POST['atas_nama_rek'] ?? '');
            $tarif = (float)($_POST['tarif_per_sesi'] ?? 0);
            $alamat = trim($_POST['alamat'] ?? '');


            if (empty($nama) || empty($email)) {
                json_response(['success' => false, 'message' => 'Nama dan email wajib diisi.']);
            }
            $existing = db_fetch_one("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) json_response(['success' => false, 'message' => 'Email sudah terdaftar.']);
            
            $username = generate_username($nama, 'TUTOR');
            $password_raw = generate_password();
            $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
            
            $user_id = db_insert(
                "INSERT INTO users (nama, email, username, password, role, jk, no_hp, alamat, status) VALUES (?, ?, ?, ?, 'TUTOR', ?, ?, ?, 'AKTIF')",
                [$nama, $email, $username, $password_hash, $jk, $no_hp, $alamat]
            );
            
            if ($user_id) {
                db_query(
                    "INSERT INTO tutor (user_id, domisili, pendidikan, mapel, nama_bank, no_rekening, atas_nama_rek, tarif_per_sesi) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$user_id, $domisili, $pendidikan, $mapel, $nama_bank, $no_rekening, $atas_nama_rek, $tarif],
                    'issssss d'
                );
                log_activity('CREATE_TUTOR', "Menambah tutor: $nama");
                json_response(['success' => true, 'message' => 'Tutor berhasil ditambahkan.', 'credentials' => ['username' => $username, 'password' => $password_raw]]);
            }
            json_response(['success' => false, 'message' => 'Gagal menyimpan.']);
            break;

        case 'update':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $jk = $_POST['jk'] ?? '';
            $no_hp = trim($_POST['no_hp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $domisili = trim($_POST['domisili'] ?? '');
            $pendidikan = trim($_POST['pendidikan'] ?? '');
            $mapel = trim($_POST['mapel'] ?? '');
            $nama_bank = trim($_POST['nama_bank'] ?? '');
            $no_rekening = trim($_POST['no_rekening'] ?? '');
            $atas_nama_rek = trim($_POST['atas_nama_rek'] ?? '');
            $tarif = (float)($_POST['tarif_per_sesi'] ?? 0);
            $alamat = trim($_POST['alamat'] ?? '');
            
            if (!$id || empty($nama)) json_response(['success' => false, 'message' => 'Data tidak valid.']);
            $existing = db_fetch_one("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id], 'si');
            if ($existing) json_response(['success' => false, 'message' => 'Email sudah digunakan.']);
            
            db_query("UPDATE users SET nama=?, email=?, jk=?, no_hp=?, alamat=? WHERE id=? AND role='TUTOR'", [$nama, $email, $jk, $no_hp, $alamat, $id], 'sssssi');
            db_query("UPDATE tutor SET domisili=?, pendidikan=?, mapel=?, nama_bank=?, no_rekening=?, atas_nama_rek=?, tarif_per_sesi=? WHERE user_id=?", [$domisili, $pendidikan, $mapel, $nama_bank, $no_rekening, $atas_nama_rek, $tarif, $id], 'ssssssd i');
            
            log_activity('UPDATE_TUTOR', "Update tutor ID: $id");
            json_response(['success' => true, 'message' => 'Data tutor berhasil diupdate.']);
            break;


        case 'delete':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) json_response(['success' => false, 'message' => 'ID tidak valid.']);
            $tutor = db_fetch_one("SELECT nama FROM users WHERE id = ? AND role = 'TUTOR'", [$id], 'i');
            if (!$tutor) json_response(['success' => false, 'message' => 'Tutor tidak ditemukan.']);
            db_query("DELETE FROM users WHERE id = ? AND role = 'TUTOR'", [$id], 'i');
            log_activity('DELETE_TUTOR', "Menghapus tutor: " . $tutor['nama']);
            json_response(['success' => true, 'message' => 'Tutor berhasil dihapus.']);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $data = db_fetch_one("
                SELECT u.*, t.domisili, t.pendidikan, t.mapel, t.nama_bank, t.no_rekening, t.atas_nama_rek, t.tarif_per_sesi
                FROM users u LEFT JOIN tutor t ON u.id = t.user_id
                WHERE u.id = ? AND u.role = 'TUTOR'
            ", [$id], 'i');
            if (!$data) json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);
            json_response(['success' => true, 'data' => $data]);
            break;

        case 'list':
            $search = trim($_GET['search'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = 10;
            $where = "WHERE u.role = 'TUTOR'";
            $params = []; $types = '';
            if ($search) {
                $where .= " AND (u.nama LIKE ? OR u.email LIKE ? OR t.mapel LIKE ?)";
                $s = "%$search%"; $params = [$s,$s,$s]; $types = 'sss';
            }
            $total = db_count("SELECT COUNT(*) FROM users u LEFT JOIN tutor t ON u.id = t.user_id $where", $params, $types);
            $paging = paginate($total, $per_page, $page);
            $rows = db_fetch_all("
                SELECT u.id, u.nama, u.email, u.username, u.jk, u.no_hp, u.status,
                       t.domisili, t.pendidikan, t.mapel, t.tarif_per_sesi
                FROM users u LEFT JOIN tutor t ON u.id = t.user_id
                $where ORDER BY u.created_at DESC
                LIMIT {$paging['per_page']} OFFSET {$paging['offset']}
            ", $params, $types);
            json_response(['success' => true, 'data' => $rows, 'paging' => $paging]);
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
        <div class="relative w-full sm:w-72">
            <input type="text" id="searchInput" placeholder="Cari tutor..."
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <svg class="absolute left-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <button onclick="openModal('modalTutor'); resetForm()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium transition-colors btn-press flex items-center gap-2 shadow-lg shadow-blue-500/25">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Tutor
        </button>
    </div>


    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Nama</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Mapel</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden lg:table-cell">Domisili</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden sm:table-cell">Tarif/Sesi</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>Memuat...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div id="paginationContainer"></div>
</div>

<!-- Modal Tutor -->
<div id="modalTutor" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 id="modalTitle" class="text-lg font-semibold text-gray-800 dark:text-white">Tambah Tutor</h3>
                <button onclick="closeModal('modalTutor')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form id="formTutor" class="space-y-4">
                <input type="hidden" id="tutor_id" name="id" value="">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama *</label><input type="text" id="f_nama" name="nama" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">JK</label><select id="f_jk" name="jk" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"><option value="">-</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">No HP</label><input type="text" id="f_no_hp" name="no_hp" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></div>
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label><input type="email" id="f_email" name="email" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Domisili</label><input type="text" id="f_domisili" name="domisili" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pendidikan</label><input type="text" id="f_pendidikan" name="pendidikan" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></div>
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mapel</label><input type="text" id="f_mapel" name="mapel" placeholder="Matematika, Fisika, dll" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tarif/Sesi (Rp)</label><input type="number" id="f_tarif" name="tarif_per_sesi" min="0" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bank</label><input type="text" id="f_nama_bank" name="nama_bank" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">No Rekening</label><input type="text" id="f_no_rek" name="no_rekening" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Atas Nama Rek</label><input type="text" id="f_atas_nama" name="atas_nama_rek" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></div>
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alamat</label><textarea id="f_alamat" name="alamat" rows="2" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white resize-none"></textarea></div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModal('modalTutor')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700">Batal</button>
                    <button type="submit" id="btnSubmit" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium btn-press">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal Credentials -->
<div id="modalCredentials" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6 text-center">
            <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-4"><svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Akun Berhasil Dibuat</h3>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4 text-left space-y-2">
                <div class="flex justify-between"><span class="text-sm text-gray-500">Username:</span><span id="credUsername" class="text-sm font-mono font-bold text-gray-800 dark:text-white"></span></div>
                <div class="flex justify-between"><span class="text-sm text-gray-500">Password:</span><span id="credPassword" class="text-sm font-mono font-bold text-gray-800 dark:text-white"></span></div>
            </div>
            <div class="flex gap-3 mt-5">
                <button onclick="copyToClipboard(document.getElementById('credUsername').textContent+' / '+document.getElementById('credPassword').textContent)" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Salin</button>
                <button onclick="closeModal('modalCredentials')" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1, searchQuery = '';
document.addEventListener('DOMContentLoaded', () => loadData());
document.getElementById('searchInput').addEventListener('input', debounce(function() { searchQuery = this.value; currentPage = 1; loadData(); }));

async function loadData() {
    const tbody = document.getElementById('tableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div></td></tr>';
    const res = await fetchAPI(`<?= BASE_URL ?>tutor.php?action=list&page=${currentPage}&search=${encodeURIComponent(searchQuery)}`);
    if (res.success && res.data.length > 0) {
        tbody.innerHTML = res.data.map((r,i) => `
            <tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors table-row-enter">
                <td class="px-4 py-3"><div class="font-medium text-gray-800 dark:text-white">${esc(r.nama)}</div><div class="text-xs text-gray-500">${esc(r.email)}</div></td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 hidden md:table-cell">${esc(r.mapel||'-')}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 hidden lg:table-cell">${esc(r.domisili||'-')}</td>
                <td class="px-4 py-3 hidden sm:table-cell"><span class="text-sm font-medium text-gray-800 dark:text-white">Rp ${Number(r.tarif_per_sesi||0).toLocaleString('id-ID')}</span></td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                        <button onclick="editTutor(${r.id})" class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                        <button onclick="deleteTutor(${r.id})" class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                    </div>
                </td>
            </tr>`).join('');
    } else { tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Tidak ada data.</td></tr>'; }
    if (res.paging?.total_pages > 1) { renderPagination(res.paging); } else { document.getElementById('paginationContainer').innerHTML = ''; }
}


function renderPagination(p) {
    let h = `<div class="flex flex-col sm:flex-row items-center justify-between mt-4 gap-3"><div class="text-sm text-gray-600 dark:text-gray-400">Hal ${p.current_page}/${p.total_pages} (${p.total})</div><div class="flex gap-1">`;
    if (p.has_prev) h += `<button onclick="goPage(${p.current_page-1})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&laquo;</button>`;
    for (let i = Math.max(1,p.current_page-2); i <= Math.min(p.total_pages,p.current_page+2); i++) {
        h += i===p.current_page ? `<span class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg">${i}</span>` : `<button onclick="goPage(${i})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">${i}</button>`;
    }
    if (p.has_next) h += `<button onclick="goPage(${p.current_page+1})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&raquo;</button>`;
    h += '</div></div>';
    document.getElementById('paginationContainer').innerHTML = h;
}
function goPage(p) { currentPage = p; loadData(); }
function resetForm() { document.getElementById('formTutor').reset(); document.getElementById('tutor_id').value = ''; document.getElementById('modalTitle').textContent = 'Tambah Tutor'; }

async function editTutor(id) {
    const res = await fetchAPI(`<?= BASE_URL ?>tutor.php?action=get&id=${id}`);
    if (res.success) {
        const d = res.data;
        document.getElementById('tutor_id').value = d.id;
        document.getElementById('f_nama').value = d.nama||'';
        document.getElementById('f_jk').value = d.jk||'';
        document.getElementById('f_no_hp').value = d.no_hp||'';
        document.getElementById('f_email').value = d.email||'';
        document.getElementById('f_domisili').value = d.domisili||'';
        document.getElementById('f_pendidikan').value = d.pendidikan||'';
        document.getElementById('f_mapel').value = d.mapel||'';
        document.getElementById('f_tarif').value = d.tarif_per_sesi||0;
        document.getElementById('f_nama_bank').value = d.nama_bank||'';
        document.getElementById('f_no_rek').value = d.no_rekening||'';
        document.getElementById('f_atas_nama').value = d.atas_nama_rek||'';
        document.getElementById('f_alamat').value = d.alamat||'';
        document.getElementById('modalTitle').textContent = 'Edit Tutor';
        openModal('modalTutor');
    }
}

document.getElementById('formTutor').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmit');
    setButtonLoading(btn, true);
    const fd = new FormData(this);
    fd.append('action', fd.get('id') ? 'update' : 'create');
    fd.append('csrf_token', '<?= csrf_token() ?>');
    const res = await fetchAPI('<?= BASE_URL ?>tutor.php', {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}});
    setButtonLoading(btn, false);
    if (res.success) {
        closeModal('modalTutor'); showToast(res.message,'success'); loadData();
        if (res.credentials) { document.getElementById('credUsername').textContent=res.credentials.username; document.getElementById('credPassword').textContent=res.credentials.password; setTimeout(()=>openModal('modalCredentials'),300); }
    } else { showToast(res.message,'error'); }
});

async function deleteTutor(id) {
    if (!confirm('Yakin hapus tutor ini?')) return;
    const fd = new FormData(); fd.append('action','delete'); fd.append('id',id); fd.append('csrf_token','<?= csrf_token() ?>');
    const res = await fetchAPI('<?= BASE_URL ?>tutor.php', {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}});
    if (res.success) { showToast(res.message,'success'); loadData(); } else showToast(res.message,'error');
}

function esc(s) { if(!s) return ''; const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
