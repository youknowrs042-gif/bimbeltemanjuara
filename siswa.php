<?php
/**
 * SISWA.PHP - CRUD Manajemen Siswa (Admin Only)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'Manajemen Siswa');

// Handle AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'create':
            csrf_check();
            $nama = trim($_POST['nama'] ?? '');
            $jk = $_POST['jk'] ?? '';
            $no_hp = trim($_POST['no_hp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $sekolah = trim($_POST['sekolah'] ?? '');
            $kelas = trim($_POST['kelas'] ?? '');
            $jenjang = $_POST['jenjang'] ?? 'SD';
            $nama_ortu = trim($_POST['nama_ortu'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');


            if (empty($nama)) {
                json_response(['success' => false, 'message' => 'Nama wajib diisi.']);
            }
            if (empty($email)) {
                json_response(['success' => false, 'message' => 'Email wajib diisi.']);
            }
            // Cek email unik
            $existing = db_fetch_one("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) {
                json_response(['success' => false, 'message' => 'Email sudah terdaftar.']);
            }
            
            $username = generate_username($nama, 'SISWA');
            $password_raw = generate_password();
            $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
            
            // Insert ke users
            $user_id = db_insert(
                "INSERT INTO users (nama, email, username, password, role, jk, no_hp, alamat, status) VALUES (?, ?, ?, ?, 'SISWA', ?, ?, ?, 'AKTIF')",
                [$nama, $email, $username, $password_hash, $jk, $no_hp, $alamat]
            );
            
            if ($user_id) {
                // Insert ke siswa
                db_query(
                    "INSERT INTO siswa (user_id, sekolah, kelas, jenjang, nama_ortu) VALUES (?, ?, ?, ?, ?)",
                    [$user_id, $sekolah, $kelas, $jenjang, $nama_ortu],
                    'issss'
                );
                log_activity('CREATE_SISWA', "Menambah siswa: $nama");
                json_response([
                    'success' => true, 
                    'message' => 'Siswa berhasil ditambahkan.',
                    'credentials' => ['username' => $username, 'password' => $password_raw]
                ]);
            }
            json_response(['success' => false, 'message' => 'Gagal menyimpan data.']);
            break;


        case 'update':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $jk = $_POST['jk'] ?? '';
            $no_hp = trim($_POST['no_hp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $sekolah = trim($_POST['sekolah'] ?? '');
            $kelas = trim($_POST['kelas'] ?? '');
            $jenjang = $_POST['jenjang'] ?? 'SD';
            $nama_ortu = trim($_POST['nama_ortu'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            
            if (!$id || empty($nama)) {
                json_response(['success' => false, 'message' => 'Data tidak valid.']);
            }
            // Cek email unik (exclude current)
            $existing = db_fetch_one("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id], 'si');
            if ($existing) {
                json_response(['success' => false, 'message' => 'Email sudah digunakan user lain.']);
            }
            
            db_query("UPDATE users SET nama=?, email=?, jk=?, no_hp=?, alamat=? WHERE id=? AND role='SISWA'",
                [$nama, $email, $jk, $no_hp, $alamat, $id], 'sssssi');
            db_query("UPDATE siswa SET sekolah=?, kelas=?, jenjang=?, nama_ortu=? WHERE user_id=?",
                [$sekolah, $kelas, $jenjang, $nama_ortu, $id], 'ssssi');
            
            log_activity('UPDATE_SISWA', "Mengupdate siswa ID: $id");
            json_response(['success' => true, 'message' => 'Data siswa berhasil diupdate.']);
            break;


        case 'delete':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) json_response(['success' => false, 'message' => 'ID tidak valid.']);
            
            $siswa = db_fetch_one("SELECT nama FROM users WHERE id = ? AND role = 'SISWA'", [$id], 'i');
            if (!$siswa) json_response(['success' => false, 'message' => 'Siswa tidak ditemukan.']);
            
            db_query("DELETE FROM users WHERE id = ? AND role = 'SISWA'", [$id], 'i');
            log_activity('DELETE_SISWA', "Menghapus siswa: " . $siswa['nama']);
            json_response(['success' => true, 'message' => 'Siswa berhasil dihapus.']);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $siswa = db_fetch_one("
                SELECT u.*, s.sekolah, s.kelas, s.jenjang, s.nama_ortu
                FROM users u
                LEFT JOIN siswa s ON u.id = s.user_id
                WHERE u.id = ? AND u.role = 'SISWA'
            ", [$id], 'i');
            if (!$siswa) json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);
            json_response(['success' => true, 'data' => $siswa]);
            break;

        case 'reset_password':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            $new_pass = generate_password();
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            db_query("UPDATE users SET password = ? WHERE id = ? AND role = 'SISWA'", [$hash, $id], 'si');
            log_activity('RESET_PASSWORD_SISWA', "Reset password siswa ID: $id");
            json_response(['success' => true, 'message' => 'Password berhasil direset.', 'password' => $new_pass]);
            break;
            
        case 'list':
            $search = trim($_GET['search'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = 10;


            $where = "WHERE u.role = 'SISWA'";
            $params = [];
            $types = '';
            
            if ($search) {
                $where .= " AND (u.nama LIKE ? OR u.email LIKE ? OR u.no_hp LIKE ? OR s.sekolah LIKE ?)";
                $s = "%$search%";
                $params = [$s, $s, $s, $s];
                $types = 'ssss';
            }
            
            $total = db_count("SELECT COUNT(*) FROM users u LEFT JOIN siswa s ON u.id = s.user_id $where", $params, $types);
            $paging = paginate($total, $per_page, $page);
            
            $rows = db_fetch_all("
                SELECT u.id, u.nama, u.email, u.username, u.jk, u.no_hp, u.status, u.created_at,
                       s.sekolah, s.kelas, s.jenjang, s.nama_ortu
                FROM users u
                LEFT JOIN siswa s ON u.id = s.user_id
                $where
                ORDER BY u.created_at DESC
                LIMIT {$paging['per_page']} OFFSET {$paging['offset']}
            ", $params, $types);
            
            json_response(['success' => true, 'data' => $rows, 'paging' => $paging]);
            break;
    }
    exit;
}

// ============================================================
// RENDER HTML
// ============================================================
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/sidebar_html.php';
?>

<div class="space-y-4">
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <div class="relative w-full sm:w-72">
            <input type="text" id="searchInput" placeholder="Cari siswa..."
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            <svg class="absolute left-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <button onclick="openModal('modalSiswa'); resetForm()" 
                class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium transition-colors btn-press flex items-center gap-2 shadow-lg shadow-blue-500/25">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Siswa
        </button>
    </div>


    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Nama</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Jenjang</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden lg:table-cell">Sekolah</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden sm:table-cell">No HP</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">
                        <div class="flex flex-col items-center"><div class="w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mb-2"></div>Memuat data...</div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <div id="paginationContainer"></div>
</div>


<!-- Modal Create/Edit Siswa -->
<div id="modalSiswa" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 id="modalTitle" class="text-lg font-semibold text-gray-800 dark:text-white">Tambah Siswa</h3>
                <button onclick="closeModal('modalSiswa')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="formSiswa" class="space-y-4">
                <input type="hidden" id="siswa_id" name="id" value="">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Lengkap *</label>
                        <input type="text" id="f_nama" name="nama" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis Kelamin</label>
                        <select id="f_jk" name="jk" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                            <option value="">- Pilih -</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">No HP</label>
                        <input type="text" id="f_no_hp" name="no_hp" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
                        <input type="email" id="f_email" name="email" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenjang *</label>
                        <select id="f_jenjang" name="jenjang" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                            <option value="PRA_SD">Pra SD (TK)</option>
                            <option value="SD">SD</option>
                            <option value="SMP">SMP</option>
                            <option value="SMA">SMA</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kelas</label>
                        <input type="text" id="f_kelas" name="kelas" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sekolah</label>
                        <input type="text" id="f_sekolah" name="sekolah" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Orang Tua</label>
                        <input type="text" id="f_nama_ortu" name="nama_ortu" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alamat</label>
                        <textarea id="f_alamat" name="alamat" rows="2" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"></textarea>
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModal('modalSiswa')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Batal</button>
                    <button type="submit" id="btnSubmit" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium transition-colors btn-press">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal Credentials -->
<div id="modalCredentials" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6 text-center">
            <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Akun Berhasil Dibuat</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Simpan data login berikut:</p>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4 text-left space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Username:</span>
                    <span id="credUsername" class="text-sm font-mono font-bold text-gray-800 dark:text-white"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Password:</span>
                    <span id="credPassword" class="text-sm font-mono font-bold text-gray-800 dark:text-white"></span>
                </div>
            </div>
            <div class="flex gap-3 mt-5">
                <button onclick="copyCredentials()" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Salin
                </button>
                <button onclick="closeModal('modalCredentials')" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>


<script>
let currentPage = 1;
let searchQuery = '';

// Load data on page load
document.addEventListener('DOMContentLoaded', () => loadData());

// Live search with debounce
document.getElementById('searchInput').addEventListener('input', debounce(function() {
    searchQuery = this.value;
    currentPage = 1;
    loadData();
}));

async function loadData() {
    const tbody = document.getElementById('tableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center"><div class="flex flex-col items-center"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mb-2"></div>Memuat...</div></td></tr>';
    
    const res = await fetchAPI(`<?= BASE_URL ?>siswa.php?action=list&page=${currentPage}&search=${encodeURIComponent(searchQuery)}`);
    
    if (res.success && res.data.length > 0) {
        tbody.innerHTML = res.data.map((row, i) => `
            <tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors table-row-enter" style="animation-delay:${i*50}ms">
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-800 dark:text-white">${escHtml(row.nama)}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">${escHtml(row.username)} &bull; ${escHtml(row.email)}</div>
                </td>
                <td class="px-4 py-3 hidden md:table-cell">
                    <span class="px-2 py-1 text-xs rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">${escHtml(row.jenjang || '-')}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">${escHtml(row.kelas || '')}</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 hidden lg:table-cell">${escHtml(row.sekolah || '-')}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 hidden sm:table-cell">${escHtml(row.no_hp || '-')}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs rounded-full ${row.status === 'AKTIF' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : row.status === 'TERKUNCI' ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'}">${row.status}</span>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                        <button onclick="editSiswa(${row.id})" class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors" title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <button onclick="resetPassword(${row.id})" class="p-1.5 text-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/30 rounded-lg transition-colors" title="Reset Password">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        </button>
                        <button onclick="deleteSiswa(${row.id})" class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors" title="Hapus">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada data siswa.</td></tr>';
    }
    
    // Render pagination
    if (res.paging && res.paging.total_pages > 1) {
        renderPagination(res.paging);
    } else {
        document.getElementById('paginationContainer').innerHTML = '';
    }
}


function renderPagination(paging) {
    let html = '<div class="flex flex-col sm:flex-row items-center justify-between mt-4 gap-3">';
    html += `<div class="text-sm text-gray-600 dark:text-gray-400">Hal ${paging.current_page}/${paging.total_pages} (${paging.total} data)</div>`;
    html += '<div class="flex gap-1">';
    if (paging.has_prev) html += `<button onclick="goPage(${paging.current_page-1})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">&laquo;</button>`;
    const start = Math.max(1, paging.current_page - 2);
    const end = Math.min(paging.total_pages, paging.current_page + 2);
    for (let i = start; i <= end; i++) {
        if (i === paging.current_page) html += `<span class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg">${i}</span>`;
        else html += `<button onclick="goPage(${i})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">${i}</button>`;
    }
    if (paging.has_next) html += `<button onclick="goPage(${paging.current_page+1})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">&raquo;</button>`;
    html += '</div></div>';
    document.getElementById('paginationContainer').innerHTML = html;
}

function goPage(p) { currentPage = p; loadData(); }

function resetForm() {
    document.getElementById('formSiswa').reset();
    document.getElementById('siswa_id').value = '';
    document.getElementById('modalTitle').textContent = 'Tambah Siswa';
}

async function editSiswa(id) {
    const res = await fetchAPI(`<?= BASE_URL ?>siswa.php?action=get&id=${id}`);
    if (res.success) {
        const d = res.data;
        document.getElementById('siswa_id').value = d.id;
        document.getElementById('f_nama').value = d.nama || '';
        document.getElementById('f_jk').value = d.jk || '';
        document.getElementById('f_no_hp').value = d.no_hp || '';
        document.getElementById('f_email').value = d.email || '';
        document.getElementById('f_sekolah').value = d.sekolah || '';
        document.getElementById('f_kelas').value = d.kelas || '';
        document.getElementById('f_jenjang').value = d.jenjang || 'SD';
        document.getElementById('f_nama_ortu').value = d.nama_ortu || '';
        document.getElementById('f_alamat').value = d.alamat || '';
        document.getElementById('modalTitle').textContent = 'Edit Siswa';
        openModal('modalSiswa');
    }
}


// Form submit
document.getElementById('formSiswa').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmit');
    setButtonLoading(btn, true);
    
    const formData = new FormData(this);
    const id = formData.get('id');
    formData.append('action', id ? 'update' : 'create');
    formData.append('csrf_token', '<?= csrf_token() ?>');
    
    const res = await fetchAPI('<?= BASE_URL ?>siswa.php', { method: 'POST', body: formData });
    
    setButtonLoading(btn, false);
    
    if (res.success) {
        closeModal('modalSiswa');
        showToast(res.message, 'success');
        loadData();
        
        // Show credentials for new user
        if (res.credentials) {
            document.getElementById('credUsername').textContent = res.credentials.username;
            document.getElementById('credPassword').textContent = res.credentials.password;
            setTimeout(() => openModal('modalCredentials'), 300);
        }
    } else {
        showToast(res.message, 'error');
    }
});

async function deleteSiswa(id) {
    if (!confirm('Yakin ingin menghapus siswa ini? Semua data terkait akan ikut terhapus.')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    
    const res = await fetchAPI('<?= BASE_URL ?>siswa.php', { method: 'POST', body: formData });
    if (res.success) { showToast(res.message, 'success'); loadData(); }
    else showToast(res.message, 'error');
}

async function resetPassword(id) {
    if (!confirm('Reset password siswa ini?')) return;
    const formData = new FormData();
    formData.append('action', 'reset_password');
    formData.append('id', id);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    
    const res = await fetchAPI('<?= BASE_URL ?>siswa.php', { method: 'POST', body: formData });
    if (res.success) {
        document.getElementById('credUsername').textContent = 'Password baru:';
        document.getElementById('credPassword').textContent = res.password;
        openModal('modalCredentials');
    } else showToast(res.message, 'error');
}

function copyCredentials() {
    const u = document.getElementById('credUsername').textContent;
    const p = document.getElementById('credPassword').textContent;
    copyToClipboard(`${u}\n${p}`);
}

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
