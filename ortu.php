<?php
/**
 * ORTU.PHP - CRUD Manajemen Orang Tua + Pairing (Admin)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'Manajemen Orang Tua');

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'create':
            csrf_check();
            $nama = trim($_POST['nama'] ?? '');
            $jk = $_POST['jk'] ?? '';
            $no_hp = trim($_POST['no_hp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $pekerjaan = trim($_POST['pekerjaan'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            
            if (empty($nama) || empty($email)) json_response(['success'=>false,'message'=>'Nama dan email wajib.']);
            $existing = db_fetch_one("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) json_response(['success'=>false,'message'=>'Email sudah terdaftar.']);
            
            $username = generate_username($nama, 'ORANG_TUA');
            $pw = generate_password();
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            
            $uid = db_insert("INSERT INTO users (nama,email,username,password,role,jk,no_hp,alamat,status) VALUES (?,?,?,?,'ORANG_TUA',?,?,?,'AKTIF')", [$nama,$email,$username,$hash,$jk,$no_hp,$alamat]);
            if ($uid) {
                db_query("INSERT INTO orang_tua (user_id, pekerjaan) VALUES (?,?)", [$uid,$pekerjaan], 'is');
                log_activity('CREATE_ORTU', "Menambah ortu: $nama");
                json_response(['success'=>true,'message'=>'Orang tua berhasil ditambahkan.','credentials'=>['username'=>$username,'password'=>$pw]]);
            }
            json_response(['success'=>false,'message'=>'Gagal menyimpan.']);
            break;


        case 'update':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $jk = $_POST['jk'] ?? '';
            $no_hp = trim($_POST['no_hp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $pekerjaan = trim($_POST['pekerjaan'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            if (!$id || empty($nama)) json_response(['success'=>false,'message'=>'Data tidak valid.']);
            $existing = db_fetch_one("SELECT id FROM users WHERE email=? AND id!=?", [$email,$id], 'si');
            if ($existing) json_response(['success'=>false,'message'=>'Email sudah digunakan.']);
            db_query("UPDATE users SET nama=?,email=?,jk=?,no_hp=?,alamat=? WHERE id=? AND role='ORANG_TUA'", [$nama,$email,$jk,$no_hp,$alamat,$id], 'sssssi');
            db_query("UPDATE orang_tua SET pekerjaan=? WHERE user_id=?", [$pekerjaan,$id], 'si');
            log_activity('UPDATE_ORTU', "Update ortu ID: $id");
            json_response(['success'=>true,'message'=>'Data berhasil diupdate.']);
            break;

        case 'delete':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            db_query("DELETE FROM users WHERE id=? AND role='ORANG_TUA'", [$id], 'i');
            log_activity('DELETE_ORTU', "Hapus ortu ID: $id");
            json_response(['success'=>true,'message'=>'Data dihapus.']);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $data = db_fetch_one("SELECT u.*, ot.pekerjaan FROM users u LEFT JOIN orang_tua ot ON u.id=ot.user_id WHERE u.id=? AND u.role='ORANG_TUA'", [$id], 'i');
            if (!$data) json_response(['success'=>false,'message'=>'Tidak ditemukan.']);
            // Get paired children
            $children = db_fetch_all("SELECT u.id, u.nama FROM pairing_ortu_siswa pos JOIN users u ON pos.siswa_user_id=u.id WHERE pos.ortu_user_id=?", [$id], 'i');
            $data['children'] = $children;
            json_response(['success'=>true,'data'=>$data]);
            break;

        case 'list':
            $search = trim($_GET['search'] ?? '');
            $page = max(1,(int)($_GET['page']??1));
            $where = "WHERE u.role='ORANG_TUA'"; $params=[]; $types='';
            if ($search) { $where .= " AND (u.nama LIKE ? OR u.email LIKE ?)"; $s="%$search%"; $params=[$s,$s]; $types='ss'; }
            $total = db_count("SELECT COUNT(*) FROM users u $where", $params, $types);
            $paging = paginate($total, 10, $page);
            $rows = db_fetch_all("SELECT u.id,u.nama,u.email,u.username,u.no_hp,u.status,ot.pekerjaan FROM users u LEFT JOIN orang_tua ot ON u.id=ot.user_id $where ORDER BY u.created_at DESC LIMIT {$paging['per_page']} OFFSET {$paging['offset']}", $params, $types);
            // Append children count
            foreach ($rows as &$r) {
                $cnt = db_count("SELECT COUNT(*) FROM pairing_ortu_siswa WHERE ortu_user_id=?", [$r['id']], 'i');
                $r['jumlah_anak'] = $cnt;
            }
            json_response(['success'=>true,'data'=>$rows,'paging'=>$paging]);
            break;

        case 'list_siswa':
            $rows = db_fetch_all("SELECT id, nama FROM users WHERE role='SISWA' AND status != 'NONAKTIF' ORDER BY nama");
            json_response(['success'=>true,'data'=>$rows]);
            break;

        case 'pair':
            csrf_check();
            $ortu_id = (int)($_POST['ortu_id'] ?? 0);
            $siswa_id = (int)($_POST['siswa_id'] ?? 0);
            if (!$ortu_id || !$siswa_id) json_response(['success'=>false,'message'=>'Data tidak valid.']);
            $exists = db_fetch_one("SELECT id FROM pairing_ortu_siswa WHERE ortu_user_id=? AND siswa_user_id=?", [$ortu_id,$siswa_id], 'ii');
            if ($exists) json_response(['success'=>false,'message'=>'Pairing sudah ada.']);
            db_insert("INSERT INTO pairing_ortu_siswa (ortu_user_id, siswa_user_id) VALUES (?,?)", [$ortu_id,$siswa_id], 'ii');
            log_activity('PAIR_ORTU_SISWA', "Pair ortu $ortu_id -> siswa $siswa_id");
            json_response(['success'=>true,'message'=>'Pairing berhasil.']);
            break;

        case 'unpair':
            csrf_check();
            $ortu_id = (int)($_POST['ortu_id'] ?? 0);
            $siswa_id = (int)($_POST['siswa_id'] ?? 0);
            db_query("DELETE FROM pairing_ortu_siswa WHERE ortu_user_id=? AND siswa_user_id=?", [$ortu_id,$siswa_id], 'ii');
            json_response(['success'=>true,'message'=>'Pairing dihapus.']);
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
            <input type="text" id="searchInput" placeholder="Cari orang tua..." class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
            <svg class="absolute left-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <button onclick="openModal('modalOrtu');resetForm()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium btn-press flex items-center gap-2 shadow-lg shadow-blue-500/25">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Tambah Orang Tua
        </button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Nama</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Pekerjaan</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden sm:table-cell">No HP</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Anak</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                </tr></thead>
                <tbody id="tableBody"><tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Memuat...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div id="paginationContainer"></div>
</div>

<!-- Modal Ortu -->
<div id="modalOrtu" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5"><h3 id="modalTitle" class="text-lg font-semibold text-gray-800 dark:text-white">Tambah Orang Tua</h3><button onclick="closeModal('modalOrtu')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form id="formOrtu" class="space-y-4">
                <input type="hidden" id="ortu_id" name="id" value="">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama *</label><input type="text" id="f_nama" name="nama" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">JK</label><select id="f_jk" name="jk" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"><option value="">-</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">No HP</label><input type="text" id="f_no_hp" name="no_hp" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label><input type="email" id="f_email" name="email" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pekerjaan</label><input type="text" id="f_pekerjaan" name="pekerjaan" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alamat</label><textarea id="f_alamat" name="alamat" rows="2" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm resize-none"></textarea></div>
                </div>
                <div class="flex gap-3"><button type="button" onclick="closeModal('modalOrtu')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Batal</button><button type="submit" id="btnSubmit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm btn-press">Simpan</button></div>
            </form>
        </div>
    </div>
</div>


<!-- Modal Pairing -->
<div id="modalPairing" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Pairing Anak</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Ortu: <strong id="pairOrtuName"></strong></p>
            <div id="pairedList" class="space-y-2 mb-4"></div>
            <div class="flex gap-2">
                <select id="pairSiswaSelect" class="flex-1 px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></select>
                <button onclick="doPair()" class="px-4 py-2.5 bg-green-600 text-white rounded-xl text-sm btn-press">Tambah</button>
            </div>
            <button onclick="closeModal('modalPairing')" class="w-full mt-4 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal Credentials -->
<div id="modalCredentials" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6 text-center">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Akun Dibuat</h3>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4 text-left space-y-2">
                <div class="flex justify-between"><span class="text-sm text-gray-500">Username:</span><span id="credUsername" class="text-sm font-mono font-bold text-gray-800 dark:text-white"></span></div>
                <div class="flex justify-between"><span class="text-sm text-gray-500">Password:</span><span id="credPassword" class="text-sm font-mono font-bold text-gray-800 dark:text-white"></span></div>
            </div>
            <div class="flex gap-3 mt-4"><button onclick="copyToClipboard(document.getElementById('credUsername').textContent+' / '+document.getElementById('credPassword').textContent)" class="flex-1 px-4 py-2.5 border rounded-xl text-sm">Salin</button><button onclick="closeModal('modalCredentials')" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm">Tutup</button></div>
        </div>
    </div>
</div>

<script>
const BASE='<?= BASE_URL ?>ortu.php', CSRF='<?= csrf_token() ?>';
let currentPage=1, searchQuery='', currentPairOrtuId=0;

document.addEventListener('DOMContentLoaded', ()=>loadData());
document.getElementById('searchInput').addEventListener('input', debounce(function(){searchQuery=this.value;currentPage=1;loadData();}));

async function loadData() {
    const tb=document.getElementById('tableBody');
    tb.innerHTML='<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Memuat...</td></tr>';
    const res=await fetchAPI(`${BASE}?action=list&page=${currentPage}&search=${encodeURIComponent(searchQuery)}`);
    if(res.success&&res.data.length>0){
        tb.innerHTML=res.data.map(r=>`
            <tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 table-row-enter">
                <td class="px-4 py-3"><div class="font-medium text-gray-800 dark:text-white">${esc(r.nama)}</div><div class="text-xs text-gray-500">${esc(r.email)}</div></td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 hidden md:table-cell">${esc(r.pekerjaan||'-')}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 hidden sm:table-cell">${esc(r.no_hp||'-')}</td>
                <td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full">${r.jumlah_anak} anak</span></td>
                <td class="px-4 py-3 text-center"><div class="flex items-center justify-center gap-1">
                    <button onclick="showPairing(${r.id},'${esc(r.nama)}')" class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg" title="Pairing"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg></button>
                    <button onclick="editOrtu(${r.id})" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                    <button onclick="deleteOrtu(${r.id})" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                </div></td>
            </tr>`).join('');
    } else { tb.innerHTML='<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Tidak ada data.</td></tr>'; }
}


function resetForm(){document.getElementById('formOrtu').reset();document.getElementById('ortu_id').value='';document.getElementById('modalTitle').textContent='Tambah Orang Tua';}

async function editOrtu(id) {
    const res=await fetchAPI(`${BASE}?action=get&id=${id}`);
    if(res.success){const d=res.data;document.getElementById('ortu_id').value=d.id;document.getElementById('f_nama').value=d.nama||'';document.getElementById('f_jk').value=d.jk||'';document.getElementById('f_no_hp').value=d.no_hp||'';document.getElementById('f_email').value=d.email||'';document.getElementById('f_pekerjaan').value=d.pekerjaan||'';document.getElementById('f_alamat').value=d.alamat||'';document.getElementById('modalTitle').textContent='Edit Orang Tua';openModal('modalOrtu');}
}

document.getElementById('formOrtu').addEventListener('submit', async function(e){
    e.preventDefault();const btn=document.getElementById('btnSubmit');setButtonLoading(btn,true);
    const fd=new FormData(this);fd.append('action',fd.get('id')?'update':'create');fd.append('csrf_token',CSRF);
    const res=await fetchAPI(BASE,{method:'POST',body:fd});
    setButtonLoading(btn,false);
    if(res.success){closeModal('modalOrtu');showToast(res.message);loadData();if(res.credentials){document.getElementById('credUsername').textContent=res.credentials.username;document.getElementById('credPassword').textContent=res.credentials.password;setTimeout(()=>openModal('modalCredentials'),300);}}else showToast(res.message,'error');
});

async function deleteOrtu(id){if(!confirm('Hapus data orang tua ini?'))return;const fd=new FormData();fd.append('action','delete');fd.append('id',id);fd.append('csrf_token',CSRF);const r=await fetchAPI(BASE,{method:'POST',body:fd});if(r.success){showToast(r.message);loadData();}else showToast(r.message,'error');}

async function showPairing(ortuId, nama) {
    currentPairOrtuId = ortuId;
    document.getElementById('pairOrtuName').textContent = nama;
    // Load current children
    const res = await fetchAPI(`${BASE}?action=get&id=${ortuId}`);
    if (res.success) {
        const children = res.data.children || [];
        document.getElementById('pairedList').innerHTML = children.length > 0
            ? children.map(c => `<div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded-lg"><span class="text-sm text-gray-800 dark:text-white">${esc(c.nama)}</span><button onclick="doUnpair(${ortuId},${c.id})" class="text-xs text-red-600 hover:underline">Hapus</button></div>`).join('')
            : '<p class="text-sm text-gray-400">Belum ada anak.</p>';
    }
    // Load siswa list
    const sList = await fetchAPI(`${BASE}?action=list_siswa`);
    if (sList.success) {
        document.getElementById('pairSiswaSelect').innerHTML = sList.data.map(s => `<option value="${s.id}">${esc(s.nama)}</option>`).join('');
    }
    openModal('modalPairing');
}

async function doPair() {
    const siswaId = document.getElementById('pairSiswaSelect').value;
    const fd = new FormData(); fd.append('action','pair'); fd.append('ortu_id',currentPairOrtuId); fd.append('siswa_id',siswaId); fd.append('csrf_token',CSRF);
    const r = await fetchAPI(BASE, {method:'POST',body:fd});
    if(r.success){showToast(r.message);showPairing(currentPairOrtuId,document.getElementById('pairOrtuName').textContent);loadData();}else showToast(r.message,'error');
}

async function doUnpair(ortuId, siswaId) {
    const fd = new FormData(); fd.append('action','unpair'); fd.append('ortu_id',ortuId); fd.append('siswa_id',siswaId); fd.append('csrf_token',CSRF);
    const r = await fetchAPI(BASE, {method:'POST',body:fd});
    if(r.success){showToast(r.message);showPairing(ortuId,document.getElementById('pairOrtuName').textContent);loadData();}else showToast(r.message,'error');
}

function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
