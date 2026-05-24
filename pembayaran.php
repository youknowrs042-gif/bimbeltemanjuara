<?php
/**
 * PEMBAYARAN.PHP - Manajemen Pembayaran & Invoice (Admin)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'Pembayaran');

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'list':
            $search = trim($_GET['search'] ?? '');
            $filter_status = $_GET['filter_status'] ?? '';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = 10;
            
            $where = "WHERE 1=1";
            $params = []; $types = '';
            if ($search) {
                $where .= " AND (u.nama LIKE ? OR pb.no_invoice LIKE ?)";
                $s = "%$search%"; $params = [$s,$s]; $types = 'ss';
            }
            if ($filter_status) {
                $where .= " AND pb.status = ?";
                $params[] = $filter_status; $types .= 's';
            }
            
            $total = db_count("SELECT COUNT(*) FROM pembayaran pb JOIN users u ON pb.siswa_user_id=u.id $where", $params, $types);
            $paging = paginate($total, $per_page, $page);
            
            $rows = db_fetch_all("
                SELECT pb.*, u.nama as siswa_nama,
                       e.mapel, p.nama_program, pk.nama_paket
                FROM pembayaran pb
                JOIN users u ON pb.siswa_user_id = u.id
                JOIN enrolment e ON pb.enrolment_id = e.id
                JOIN program p ON e.program_id = p.id
                JOIN paket pk ON e.paket_id = pk.id
                $where
                ORDER BY pb.created_at DESC
                LIMIT {$paging['per_page']} OFFSET {$paging['offset']}
            ", $params, $types);
            
            json_response(['success' => true, 'data' => $rows, 'paging' => $paging]);
            break;


        case 'confirm':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) json_response(['success'=>false,'message'=>'ID tidak valid.']);
            
            $pb = db_fetch_one("SELECT * FROM pembayaran WHERE id = ?", [$id], 'i');
            if (!$pb) json_response(['success'=>false,'message'=>'Data tidak ditemukan.']);
            
            // Update payment status to LUNAS
            db_query("UPDATE pembayaran SET status='LUNAS', confirmed_by=?, confirmed_at=NOW(), tanggal_bayar=COALESCE(tanggal_bayar, CURDATE()) WHERE id=?",
                [current_user_id(), $id], 'ii');
            
            // Unlock siswa account
            db_query("UPDATE users SET status='AKTIF' WHERE id=? AND status='TERKUNCI'", [$pb['siswa_user_id']], 'i');
            
            log_activity('CONFIRM_PAYMENT', "Konfirmasi pembayaran #{$pb['no_invoice']}");
            json_response(['success'=>true,'message'=>'Pembayaran dikonfirmasi. Akun siswa diaktifkan.']);
            break;

        case 'reject':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            db_query("UPDATE pembayaran SET status='BELUM_BAYAR', bukti_transfer=NULL WHERE id=?", [$id], 'i');
            json_response(['success'=>true,'message'=>'Pembayaran ditolak.']);
            break;

        case 'upload_bukti':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            $metode = trim($_POST['metode_bayar'] ?? '');
            $tanggal = $_POST['tanggal_bayar'] ?? date('Y-m-d');
            
            $bukti_path = null;
            if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
                $upload = upload_file($_FILES['bukti_transfer'], 'bukti_bayar');
                if ($upload['success']) {
                    $bukti_path = $upload['path'];
                } else {
                    json_response(['success'=>false,'message'=>$upload['message']]);
                }
            }
            
            $sql = "UPDATE pembayaran SET status='MENUNGGU_KONFIRMASI', metode_bayar=?, tanggal_bayar=?";
            $params = [$metode, $tanggal]; $types = 'ss';
            if ($bukti_path) {
                $sql .= ", bukti_transfer=?";
                $params[] = $bukti_path; $types .= 's';
            }
            $sql .= " WHERE id=?";
            $params[] = $id; $types .= 'i';
            
            db_query($sql, $params, $types);
            log_activity('UPLOAD_BUKTI', "Upload bukti bayar invoice ID: $id");
            json_response(['success'=>true,'message'=>'Bukti transfer berhasil diupload. Menunggu konfirmasi admin.']);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $data = db_fetch_one("
                SELECT pb.*, u.nama as siswa_nama, u.email as siswa_email,
                       e.mapel, e.jenjang, p.nama_program, pk.nama_paket, pk.jumlah_pertemuan
                FROM pembayaran pb
                JOIN users u ON pb.siswa_user_id = u.id
                JOIN enrolment e ON pb.enrolment_id = e.id
                JOIN program p ON e.program_id = p.id
                JOIN paket pk ON e.paket_id = pk.id
                WHERE pb.id = ?
            ", [$id], 'i');
            json_response(['success'=>(bool)$data, 'data'=>$data]);
            break;

        case 'update_jumlah':
            csrf_check();
            $id = (int)($_POST['id'] ?? 0);
            $jumlah = (float)($_POST['jumlah'] ?? 0);
            $catatan = trim($_POST['catatan'] ?? '');
            db_query("UPDATE pembayaran SET jumlah=?, catatan=? WHERE id=?", [$jumlah, $catatan, $id], 'dsi');
            json_response(['success'=>true,'message'=>'Data pembayaran diupdate.']);
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
                <input type="text" id="searchInput" placeholder="Cari invoice/siswa..." class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                <svg class="absolute left-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <select id="filterStatus" onchange="loadData()" class="px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm">
                <option value="">Semua</option>
                <option value="BELUM_BAYAR">Belum Bayar</option>
                <option value="MENUNGGU_KONFIRMASI">Menunggu Konfirmasi</option>
                <option value="LUNAS">Lunas</option>
                <option value="BATAL">Batal</option>
            </select>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Invoice</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Siswa</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Program</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Jumlah</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Status</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                </tr></thead>
                <tbody id="tableBody"><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Memuat...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div id="paginationContainer"></div>
</div>

<!-- Modal Upload Bukti -->
<div id="modalBukti" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Upload Bukti Transfer</h3>
            <form id="formBukti" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" id="bukti_id" name="id" value="">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Metode Bayar</label><input type="text" name="metode_bayar" placeholder="Transfer BCA, dll" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Bayar</label><input type="date" name="tanggal_bayar" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bukti Transfer</label><input type="file" name="bukti_transfer" accept="image/*,.pdf" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm"></div>
                <div class="flex gap-3"><button type="button" onclick="closeModal('modalBukti')" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Batal</button><button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm btn-press">Upload</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail -->
<div id="modalDetail" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Detail Pembayaran</h3>
            <div id="detailContent" class="space-y-3 text-sm"></div>
            <button onclick="closeModal('modalDetail')" class="w-full mt-4 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm">Tutup</button>
        </div>
    </div>
</div>


<script>
const BASE='<?= BASE_URL ?>pembayaran.php', CSRF='<?= csrf_token() ?>';
let currentPage=1, searchQuery='';

document.addEventListener('DOMContentLoaded', ()=>loadData());
document.getElementById('searchInput').addEventListener('input', debounce(function(){searchQuery=this.value;currentPage=1;loadData();}));

async function loadData() {
    const filterStatus = document.getElementById('filterStatus').value;
    const tb = document.getElementById('tableBody');
    tb.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div></td></tr>';
    
    const res = await fetchAPI(`${BASE}?action=list&page=${currentPage}&search=${encodeURIComponent(searchQuery)}&filter_status=${filterStatus}`);
    if (res.success && res.data.length > 0) {
        tb.innerHTML = res.data.map(r => {
            const sc = {'BELUM_BAYAR':'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300','MENUNGGU_KONFIRMASI':'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300','LUNAS':'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300','BATAL':'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300'};
            const statusLabel = {'BELUM_BAYAR':'Belum Bayar','MENUNGGU_KONFIRMASI':'Menunggu','LUNAS':'Lunas','BATAL':'Batal'};
            return `<tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 table-row-enter">
                <td class="px-4 py-3"><div class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">${esc(r.no_invoice)}</div><div class="text-xs text-gray-500">${r.created_at ? r.created_at.substring(0,10) : ''}</div></td>
                <td class="px-4 py-3"><div class="font-medium text-gray-800 dark:text-white">${esc(r.siswa_nama)}</div></td>
                <td class="px-4 py-3 hidden md:table-cell"><div class="text-gray-600 dark:text-gray-400">${esc(r.nama_program)}</div><div class="text-xs text-gray-500">${esc(r.nama_paket)}</div></td>
                <td class="px-4 py-3 text-right font-medium text-gray-800 dark:text-white">Rp ${Number(r.jumlah||0).toLocaleString('id-ID')}</td>
                <td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs rounded-full ${sc[r.status]||''}">${statusLabel[r.status]||r.status}</span></td>
                <td class="px-4 py-3 text-center"><div class="flex items-center justify-center gap-1">
                    <button onclick="showDetail(${r.id})" class="p-1.5 text-gray-600 hover:bg-gray-100 rounded-lg" title="Detail"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                    ${r.status==='BELUM_BAYAR'?`<button onclick="showUpload(${r.id})" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="Upload Bukti"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg></button>`:''}
                    ${r.status==='MENUNGGU_KONFIRMASI'?`<button onclick="confirmPayment(${r.id})" class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg" title="Konfirmasi"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></button><button onclick="rejectPayment(${r.id})" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Tolak"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>`:''}
                </div></td>
            </tr>`;
        }).join('');
    } else { tb.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada data pembayaran.</td></tr>'; }
    if (res.paging?.total_pages > 1) renderPagination(res.paging); else document.getElementById('paginationContainer').innerHTML = '';
}

function renderPagination(p) {
    let h = `<div class="flex flex-col sm:flex-row items-center justify-between mt-4 gap-3"><div class="text-sm text-gray-600 dark:text-gray-400">Hal ${p.current_page}/${p.total_pages}</div><div class="flex gap-1">`;
    if(p.has_prev) h+=`<button onclick="goPage(${p.current_page-1})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&laquo;</button>`;
    for(let i=Math.max(1,p.current_page-2);i<=Math.min(p.total_pages,p.current_page+2);i++) h+=i===p.current_page?`<span class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg">${i}</span>`:`<button onclick="goPage(${i})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">${i}</button>`;
    if(p.has_next) h+=`<button onclick="goPage(${p.current_page+1})" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&raquo;</button>`;
    h+='</div></div>';document.getElementById('paginationContainer').innerHTML=h;
}
function goPage(p){currentPage=p;loadData();}

function showUpload(id){document.getElementById('bukti_id').value=id;document.getElementById('formBukti').reset();openModal('modalBukti');}

document.getElementById('formBukti').addEventListener('submit', async function(e){
    e.preventDefault();
    const fd=new FormData(this); fd.append('action','upload_bukti'); fd.append('csrf_token',CSRF);
    const res=await fetchAPI(BASE,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
    if(res.success){closeModal('modalBukti');showToast(res.message);loadData();}else showToast(res.message,'error');
});

async function confirmPayment(id){if(!confirm('Konfirmasi pembayaran ini? Akun siswa akan diaktifkan.'))return;const fd=new FormData();fd.append('action','confirm');fd.append('id',id);fd.append('csrf_token',CSRF);const r=await fetchAPI(BASE,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});if(r.success){showToast(r.message);loadData();}else showToast(r.message,'error');}
async function rejectPayment(id){if(!confirm('Tolak bukti bayar ini?'))return;const fd=new FormData();fd.append('action','reject');fd.append('id',id);fd.append('csrf_token',CSRF);const r=await fetchAPI(BASE,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});if(r.success){showToast(r.message);loadData();}else showToast(r.message,'error');}

async function showDetail(id){
    const res=await fetchAPI(`${BASE}?action=get&id=${id}`);
    if(res.success&&res.data){
        const d=res.data;
        let html=`<div class="grid grid-cols-2 gap-2">
            <div><span class="text-gray-500">Invoice:</span></div><div class="font-mono font-bold">${esc(d.no_invoice)}</div>
            <div><span class="text-gray-500">Siswa:</span></div><div>${esc(d.siswa_nama)}</div>
            <div><span class="text-gray-500">Program:</span></div><div>${esc(d.nama_program)} - ${esc(d.nama_paket)}</div>
            <div><span class="text-gray-500">Jenjang:</span></div><div>${esc(d.jenjang)}</div>
            <div><span class="text-gray-500">Mapel:</span></div><div>${esc(d.mapel||'-')}</div>
            <div><span class="text-gray-500">Jumlah:</span></div><div class="font-bold">Rp ${Number(d.jumlah||0).toLocaleString('id-ID')}</div>
            <div><span class="text-gray-500">Status:</span></div><div class="font-bold">${d.status}</div>
            <div><span class="text-gray-500">Metode:</span></div><div>${esc(d.metode_bayar||'-')}</div>
            <div><span class="text-gray-500">Tgl Bayar:</span></div><div>${esc(d.tanggal_bayar||'-')}</div>
        </div>`;
        if(d.bukti_transfer){html+=`<div class="mt-3"><a href="<?= BASE_URL ?>uploads/${d.bukti_transfer}" target="_blank" class="text-blue-600 hover:underline text-sm">Lihat Bukti Transfer</a></div>`;}
        document.getElementById('detailContent').innerHTML=html;
        openModal('modalDetail');
    }
}
function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
