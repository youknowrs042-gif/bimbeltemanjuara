<?php
/**
 * PROGRAM.PHP - Manajemen Program, Paket & Harga (Admin)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'Program & Paket');

// Handle AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'list_programs':
            $rows = db_fetch_all("SELECT * FROM program ORDER BY id ASC");
            json_response(['success' => true, 'data' => $rows]);
            break;
        
        case 'list_pakets':
            $rows = db_fetch_all("SELECT * FROM paket ORDER BY jumlah_pertemuan ASC");
            json_response(['success' => true, 'data' => $rows]);
            break;
        
        case 'list_harga':
            $rows = db_fetch_all("
                SELECT hp.*, p.nama_program, pk.nama_paket, pk.jumlah_pertemuan
                FROM harga_paket hp
                JOIN program p ON hp.program_id = p.id
                JOIN paket pk ON hp.paket_id = pk.id
                ORDER BY p.id, hp.jenjang, pk.jumlah_pertemuan
            ");
            json_response(['success' => true, 'data' => $rows]);
            break;


        case 'save_program':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama_program'] ?? '');
            $tipe = $_POST['tipe'] ?? '';
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            if (empty($nama) || empty($tipe)) json_response(['success'=>false,'message'=>'Nama dan tipe wajib diisi.']);
            
            if ($id) {
                db_query("UPDATE program SET nama_program=?, tipe=?, deskripsi=? WHERE id=?", [$nama,$tipe,$deskripsi,$id], 'sssi');
            } else {
                db_insert("INSERT INTO program (nama_program, tipe, deskripsi) VALUES (?,?,?)", [$nama,$tipe,$deskripsi]);
            }
            log_activity('SAVE_PROGRAM', "Program: $nama");
            json_response(['success'=>true,'message'=>'Program berhasil disimpan.']);
            break;

        case 'delete_program':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            db_query("DELETE FROM program WHERE id = ?", [$id], 'i');
            json_response(['success'=>true,'message'=>'Program dihapus.']);
            break;

        case 'save_paket':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama_paket'] ?? '');
            $jml = (int)($_POST['jumlah_pertemuan'] ?? 4);
            if (empty($nama)) json_response(['success'=>false,'message'=>'Nama paket wajib diisi.']);
            if ($id) {
                db_query("UPDATE paket SET nama_paket=?, jumlah_pertemuan=? WHERE id=?", [$nama,$jml,$id], 'sii');
            } else {
                db_insert("INSERT INTO paket (nama_paket, jumlah_pertemuan) VALUES (?,?)", [$nama,$jml], 'si');
            }
            json_response(['success'=>true,'message'=>'Paket berhasil disimpan.']);
            break;

        case 'delete_paket':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            db_query("DELETE FROM paket WHERE id = ?", [$id], 'i');
            json_response(['success'=>true,'message'=>'Paket dihapus.']);
            break;

        case 'save_harga':
            csrf_check();
            $program_id = (int)($_POST['program_id'] ?? 0);
            $jenjang = $_POST['jenjang'] ?? '';
            $paket_id = (int)($_POST['paket_id'] ?? 0);
            $harga = (float)($_POST['harga'] ?? 0);
            if (!$program_id || !$paket_id || empty($jenjang)) json_response(['success'=>false,'message'=>'Data tidak lengkap.']);
            
            // Upsert
            $existing = db_fetch_one("SELECT id FROM harga_paket WHERE program_id=? AND jenjang=? AND paket_id=?", [$program_id,$jenjang,$paket_id], 'isi');
            if ($existing) {
                db_query("UPDATE harga_paket SET harga=? WHERE id=?", [$harga, $existing['id']], 'di');
            } else {
                db_insert("INSERT INTO harga_paket (program_id, jenjang, paket_id, harga) VALUES (?,?,?,?)", [$program_id,$jenjang,$paket_id,$harga], 'isid');
            }
            json_response(['success'=>true,'message'=>'Harga berhasil disimpan.']);
            break;

        case 'delete_harga':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            db_query("DELETE FROM harga_paket WHERE id = ?", [$id], 'i');
            json_response(['success'=>true,'message'=>'Harga dihapus.']);
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
    <div class="flex border-b border-gray-200 dark:border-gray-700">
        <button onclick="switchTab('programs')" id="tabPrograms" class="px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600 dark:text-blue-400">Program</button>
        <button onclick="switchTab('pakets')" id="tabPakets" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400">Paket</button>
        <button onclick="switchTab('harga')" id="tabHarga" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400">Harga</button>
    </div>

    <!-- Tab: Programs -->
    <div id="panelPrograms" class="space-y-4">
        <div class="flex justify-end">
            <button onclick="showProgramForm()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium btn-press flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Program
            </button>
        </div>
        <div id="programList" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
    </div>

    <!-- Tab: Pakets -->
    <div id="panelPakets" class="space-y-4 hidden">
        <div class="flex justify-end">
            <button onclick="showPaketForm()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium btn-press flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Paket
            </button>
        </div>
        <div id="paketList" class="grid grid-cols-1 md:grid-cols-3 gap-4"></div>
    </div>

    <!-- Tab: Harga -->
    <div id="panelHarga" class="space-y-4 hidden">
        <div class="flex justify-end">
            <button onclick="showHargaForm()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium btn-press flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Set Harga
            </button>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Program</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Jenjang</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Paket</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Harga</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="hargaTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- Modal Program -->
<div id="modalProgram" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <h3 id="modalProgramTitle" class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Tambah Program</h3>
            <form id="formProgram" class="space-y-4">
                <input type="hidden" id="prog_id" name="id" value="">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Program *</label><input type="text" id="prog_nama" name="nama_program" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipe *</label>
                    <select id="prog_tipe" name="tipe" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                        <option value="PRIVATE">Private</option><option value="KLASIKAL">Klasikal</option><option value="SEMI_PRIVATE">Semi-Private</option><option value="ONLINE">Online</option>
                    </select></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deskripsi</label><textarea id="prog_desc" name="deskripsi" rows="2" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white resize-none"></textarea></div>
                <div class="flex gap-3"><button type="button" onclick="closeModal('modalProgram')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Batal</button><button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm btn-press">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Paket -->
<div id="modalPaket" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Tambah Paket</h3>
            <form id="formPaket" class="space-y-4">
                <input type="hidden" id="pkt_id" name="id" value="">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Paket *</label><input type="text" id="pkt_nama" name="nama_paket" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jumlah Pertemuan *</label><input type="number" id="pkt_jml" name="jumlah_pertemuan" required min="1" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></div>
                <div class="flex gap-3"><button type="button" onclick="closeModal('modalPaket')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Batal</button><button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm btn-press">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Harga -->
<div id="modalHarga" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Set Harga Paket</h3>
            <form id="formHarga" class="space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Program *</label><select id="h_program" name="program_id" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></select></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenjang *</label><select id="h_jenjang" name="jenjang" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"><option value="PRA_SD">Pra SD</option><option value="SD">SD</option><option value="SMP">SMP</option><option value="SMA">SMA</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paket *</label><select id="h_paket" name="paket_id" required class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></select></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Harga (Rp) *</label><input type="number" id="h_harga" name="harga" required min="0" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-white"></div>
                <div class="flex gap-3"><button type="button" onclick="closeModal('modalHarga')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Batal</button><button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm btn-press">Simpan</button></div>
            </form>
        </div>
    </div>
</div>


<script>
const BASE = '<?= BASE_URL ?>program.php';
const CSRF = '<?= csrf_token() ?>';
let programsCache = [], paketsCache = [];

document.addEventListener('DOMContentLoaded', () => { loadPrograms(); loadPakets(); loadHarga(); });

function switchTab(tab) {
    document.querySelectorAll('[id^="panel"]').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('[id^="tab"]').forEach(t => { t.classList.remove('border-blue-600','text-blue-600','dark:text-blue-400'); t.classList.add('border-transparent','text-gray-500'); });
    document.getElementById('panel' + tab.charAt(0).toUpperCase()+tab.slice(1)).classList.remove('hidden');
    const tabBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase()+tab.slice(1));
    tabBtn.classList.add('border-blue-600','text-blue-600','dark:text-blue-400');
    tabBtn.classList.remove('border-transparent','text-gray-500');
}

async function loadPrograms() {
    const res = await fetchAPI(BASE+'?action=list_programs');
    if (res.success) {
        programsCache = res.data;
        const el = document.getElementById('programList');
        el.innerHTML = res.data.map(p => `
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 hover-elevate">
                <div class="flex justify-between items-start">
                    <div><h4 class="font-semibold text-gray-800 dark:text-white">${esc(p.nama_program)}</h4>
                    <span class="text-xs px-2 py-0.5 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full">${esc(p.tipe)}</span>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${esc(p.deskripsi||'')}</p></div>
                    <div class="flex gap-1">
                        <button onclick="editProgram(${p.id})" class="p-1 text-blue-600 hover:bg-blue-50 rounded"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                        <button onclick="deleteProgram(${p.id})" class="p-1 text-red-600 hover:bg-red-50 rounded"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                    </div>
                </div>
            </div>`).join('') || '<p class="text-gray-400 text-sm">Belum ada program.</p>';
        // Update select options
        document.getElementById('h_program').innerHTML = programsCache.map(p=>`<option value="${p.id}">${esc(p.nama_program)}</option>`).join('');
    }
}

async function loadPakets() {
    const res = await fetchAPI(BASE+'?action=list_pakets');
    if (res.success) {
        paketsCache = res.data;
        document.getElementById('paketList').innerHTML = res.data.map(p => `
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 hover-elevate text-center">
                <h4 class="font-semibold text-gray-800 dark:text-white">${esc(p.nama_paket)}</h4>
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 my-2">${p.jumlah_pertemuan}</p>
                <p class="text-xs text-gray-500">pertemuan</p>
                <div class="flex justify-center gap-2 mt-3">
                    <button onclick="editPaket(${p.id},'${esc(p.nama_paket)}',${p.jumlah_pertemuan})" class="text-blue-600 text-xs hover:underline">Edit</button>
                    <button onclick="deletePaket(${p.id})" class="text-red-600 text-xs hover:underline">Hapus</button>
                </div>
            </div>`).join('') || '<p class="text-gray-400 text-sm">Belum ada paket.</p>';
        document.getElementById('h_paket').innerHTML = paketsCache.map(p=>`<option value="${p.id}">${esc(p.nama_paket)} (${p.jumlah_pertemuan}x)</option>`).join('');
    }
}


async function loadHarga() {
    const res = await fetchAPI(BASE+'?action=list_harga');
    const tb = document.getElementById('hargaTableBody');
    if (res.success && res.data.length > 0) {
        tb.innerHTML = res.data.map(r => `
            <tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                <td class="px-4 py-3 text-gray-800 dark:text-white">${esc(r.nama_program)}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-full">${esc(r.jenjang)}</span></td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">${esc(r.nama_paket)} (${r.jumlah_pertemuan}x)</td>
                <td class="px-4 py-3 text-right font-medium text-gray-800 dark:text-white">Rp ${Number(r.harga).toLocaleString('id-ID')}</td>
                <td class="px-4 py-3 text-center"><button onclick="deleteHarga(${r.id})" class="p-1 text-red-600 hover:bg-red-50 rounded"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></td>
            </tr>`).join('');
    } else { tb.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada data harga.</td></tr>'; }
}

function showProgramForm(id=0) { document.getElementById('prog_id').value=''; document.getElementById('formProgram').reset(); openModal('modalProgram'); }
function editProgram(id) { const p = programsCache.find(x=>x.id==id); if(p){document.getElementById('prog_id').value=p.id; document.getElementById('prog_nama').value=p.nama_program; document.getElementById('prog_tipe').value=p.tipe; document.getElementById('prog_desc').value=p.deskripsi||''; openModal('modalProgram');} }
function showPaketForm() { document.getElementById('pkt_id').value=''; document.getElementById('formPaket').reset(); openModal('modalPaket'); }
function editPaket(id,nama,jml) { document.getElementById('pkt_id').value=id; document.getElementById('pkt_nama').value=nama; document.getElementById('pkt_jml').value=jml; openModal('modalPaket'); }
function showHargaForm() { document.getElementById('formHarga').reset(); openModal('modalHarga'); }

document.getElementById('formProgram').addEventListener('submit', async function(e) {
    e.preventDefault(); const fd = new FormData(this); fd.append('action','save_program'); fd.append('csrf_token',CSRF);
    const res = await fetchAPI(BASE, {method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
    if(res.success){closeModal('modalProgram');showToast(res.message);loadPrograms();}else showToast(res.message,'error');
});
document.getElementById('formPaket').addEventListener('submit', async function(e) {
    e.preventDefault(); const fd = new FormData(this); fd.append('action','save_paket'); fd.append('csrf_token',CSRF);
    const res = await fetchAPI(BASE, {method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
    if(res.success){closeModal('modalPaket');showToast(res.message);loadPakets();}else showToast(res.message,'error');
});
document.getElementById('formHarga').addEventListener('submit', async function(e) {
    e.preventDefault(); const fd = new FormData(this); fd.append('action','save_harga'); fd.append('csrf_token',CSRF);
    const res = await fetchAPI(BASE, {method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
    if(res.success){closeModal('modalHarga');showToast(res.message);loadHarga();}else showToast(res.message,'error');
});

async function deleteProgram(id) { if(!confirm('Hapus program ini?'))return; const fd=new FormData();fd.append('action','delete_program');fd.append('id',id);fd.append('csrf_token',CSRF); const r=await fetchAPI(BASE,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}); if(r.success){showToast(r.message);loadPrograms();}else showToast(r.message,'error'); }
async function deletePaket(id) { if(!confirm('Hapus paket ini?'))return; const fd=new FormData();fd.append('action','delete_paket');fd.append('id',id);fd.append('csrf_token',CSRF); const r=await fetchAPI(BASE,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}); if(r.success){showToast(r.message);loadPakets();}else showToast(r.message,'error'); }
async function deleteHarga(id) { if(!confirm('Hapus harga ini?'))return; const fd=new FormData();fd.append('action','delete_harga');fd.append('id',id);fd.append('csrf_token',CSRF); const r=await fetchAPI(BASE,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}); if(r.success){showToast(r.message);loadHarga();}else showToast(r.message,'error'); }

function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
