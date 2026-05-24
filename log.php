<?php
/**
 * LOG.PHP - Log Aktivitas (Admin)
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('ADMIN');

define('PAGE_TITLE', 'Log Aktivitas');

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $search = trim($_GET['search'] ?? '');
        $page = max(1,(int)($_GET['page']??1));
        $where = "WHERE 1=1"; $params=[]; $types='';
        if ($search) { $where .= " AND (al.action LIKE ? OR al.description LIKE ? OR u.nama LIKE ?)"; $s="%$search%"; $params=[$s,$s,$s]; $types='sss'; }
        
        $total = db_count("SELECT COUNT(*) FROM activity_log al LEFT JOIN users u ON al.user_id=u.id $where", $params, $types);
        $paging = paginate($total, 20, $page);
        
        $rows = db_fetch_all("
            SELECT al.*, u.nama as user_nama, u.role as user_role
            FROM activity_log al
            LEFT JOIN users u ON al.user_id = u.id
            $where
            ORDER BY al.created_at DESC
            LIMIT {$paging['per_page']} OFFSET {$paging['offset']}
        ", $params, $types);
        
        json_response(['success'=>true,'data'=>$rows,'paging'=>$paging]);
    }
    exit;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/sidebar_html.php';
?>

<div class="space-y-4">
    <div class="relative w-full sm:w-72">
        <input type="text" id="searchInput" placeholder="Cari log..." class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500">
        <svg class="absolute left-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Waktu</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">User</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Deskripsi</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 hidden lg:table-cell">IP</th>
                </tr></thead>
                <tbody id="tableBody"><tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Memuat...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div id="paginationContainer"></div>
</div>

<script>
const BASE='<?= BASE_URL ?>log.php';
let currentPage=1, searchQuery='';

document.addEventListener('DOMContentLoaded', ()=>loadData());
document.getElementById('searchInput').addEventListener('input', debounce(function(){searchQuery=this.value;currentPage=1;loadData();}));

async function loadData(){
    const tb=document.getElementById('tableBody');
    tb.innerHTML='<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div></td></tr>';
    const res=await fetchAPI(`${BASE}?action=list&page=${currentPage}&search=${encodeURIComponent(searchQuery)}`);
    if(res.success&&res.data.length>0){
        tb.innerHTML=res.data.map(r=>`<tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30">
            <td class="px-4 py-2.5 text-xs text-gray-500 whitespace-nowrap">${r.created_at||''}</td>
            <td class="px-4 py-2.5"><span class="text-gray-800 dark:text-white text-xs font-medium">${esc(r.user_nama||'System')}</span></td>
            <td class="px-4 py-2.5"><span class="px-2 py-0.5 text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full">${esc(r.action)}</span></td>
            <td class="px-4 py-2.5 text-xs text-gray-500 hidden md:table-cell max-w-xs truncate">${esc(r.description||'-')}</td>
            <td class="px-4 py-2.5 text-xs text-gray-400 hidden lg:table-cell font-mono">${esc(r.ip_address||'-')}</td>
        </tr>`).join('');
    } else tb.innerHTML='<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Tidak ada log.</td></tr>';
    if(res.paging?.total_pages>1){let h=`<div class="flex items-center justify-between mt-4"><div class="text-sm text-gray-500">Hal ${res.paging.current_page}/${res.paging.total_pages} (${res.paging.total})</div><div class="flex gap-1">`;if(res.paging.has_prev)h+=`<button onclick="currentPage--;loadData()" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&laquo;</button>`;if(res.paging.has_next)h+=`<button onclick="currentPage++;loadData()" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border rounded-lg">&raquo;</button>`;h+='</div></div>';document.getElementById('paginationContainer').innerHTML=h;}else document.getElementById('paginationContainer').innerHTML='';
}
function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
