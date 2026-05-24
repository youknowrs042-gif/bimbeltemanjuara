<?php
/**
 * SISWA_PR.PHP - Siswa mengerjakan PR/Kuis MCQ
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('SISWA');
block_if_locked();

define('PAGE_TITLE', 'PR / Kuis Saya');

$user_id = current_user_id();

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'list':
            $assignments = db_fetch_all("
                SELECT pa.*, pr.judul, pr.deskripsi, pr.mapel, pr.deadline, pr.bank_soal_id,
                       (SELECT COUNT(*) FROM soal WHERE bank_soal_id = pr.bank_soal_id) as total_soal
                FROM pr_assignment pa
                JOIN pr ON pa.pr_id = pr.id
                WHERE pa.siswa_user_id = ? AND pr.status = 'PUBLISHED'
                ORDER BY FIELD(pa.status, 'BELUM_DIKERJAKAN', 'SUDAH_DIKERJAKAN', 'DINILAI'), pr.deadline ASC
            ", [$user_id], 'i');
            json_response(['success'=>true,'data'=>$assignments]);
            break;

        case 'get_soal':
            $assignment_id = (int)($_GET['assignment_id'] ?? 0);
            // Verify ownership
            $pa = db_fetch_one("SELECT pa.*, pr.bank_soal_id, pr.judul FROM pr_assignment pa JOIN pr ON pa.pr_id=pr.id WHERE pa.id=? AND pa.siswa_user_id=?", [$assignment_id,$user_id], 'ii');
            if (!$pa) json_response(['success'=>false,'message'=>'Tidak ditemukan.']);
            
            $soal = db_fetch_all("SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, urutan FROM soal WHERE bank_soal_id=? ORDER BY urutan, id", [$pa['bank_soal_id']], 'i');
            
            // Get existing answers
            $jawaban = db_fetch_all("SELECT soal_id, jawaban FROM pr_jawaban WHERE pr_assignment_id=?", [$assignment_id], 'i');
            $jawaban_map = [];
            foreach ($jawaban as $j) $jawaban_map[$j['soal_id']] = $j['jawaban'];
            
            json_response(['success'=>true,'pr'=>$pa,'soal'=>$soal,'jawaban'=>$jawaban_map]);
            break;

        case 'submit':
            csrf_check();
            $assignment_id = (int)($_POST['assignment_id'] ?? 0);
            $answers = $_POST['answers'] ?? [];
            
            $pa = db_fetch_one("SELECT pa.*, pr.bank_soal_id FROM pr_assignment pa JOIN pr ON pa.pr_id=pr.id WHERE pa.id=? AND pa.siswa_user_id=?", [$assignment_id,$user_id], 'ii');
            if (!$pa) json_response(['success'=>false,'message'=>'Assignment tidak ditemukan.']);
            if ($pa['status'] !== 'BELUM_DIKERJAKAN') json_response(['success'=>false,'message'=>'PR sudah dikerjakan sebelumnya.']);
            
            // Get correct answers
            $soal_list = db_fetch_all("SELECT id, jawaban_benar FROM soal WHERE bank_soal_id=?", [$pa['bank_soal_id']], 'i');
            
            $total = count($soal_list);
            $benar = 0;
            
            // Delete old answers
            db_query("DELETE FROM pr_jawaban WHERE pr_assignment_id=?", [$assignment_id], 'i');
            
            foreach ($soal_list as $soal) {
                $jawaban = $answers[$soal['id']] ?? null;
                $is_benar = ($jawaban === $soal['jawaban_benar']) ? 1 : 0;
                if ($is_benar) $benar++;
                
                db_insert("INSERT INTO pr_jawaban (pr_assignment_id, soal_id, jawaban, is_benar) VALUES (?,?,?,?)",
                    [$assignment_id, $soal['id'], $jawaban, $is_benar], 'iisi');
            }
            
            $nilai = $total > 0 ? round(($benar / $total) * 100, 2) : 0;
            
            db_query("UPDATE pr_assignment SET status='SUDAH_DIKERJAKAN', nilai=?, submitted_at=NOW() WHERE id=?",
                [$nilai, $assignment_id], 'di');
            
            json_response(['success'=>true,'message'=>"PR berhasil dikumpulkan!",'nilai'=>$nilai,'benar'=>$benar,'total'=>$total]);
            break;
    }
    exit;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/sidebar_html.php';
?>


<div id="prListView" class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Daftar PR</h3>
    <div id="prList" class="space-y-3">
        <div class="text-center py-8 text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>Memuat...</div>
    </div>
</div>

<div id="prWorkView" class="hidden space-y-4">
    <div class="flex items-center gap-3 mb-4">
        <button onclick="backToList()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
        <h3 id="prWorkTitle" class="text-lg font-semibold text-gray-800 dark:text-white"></h3>
    </div>
    <form id="formKerjakan" class="space-y-4">
        <input type="hidden" id="work_assignment_id" value="">
        <div id="soalContainer" class="space-y-4"></div>
        <button type="submit" id="btnKumpulkan" class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl text-sm font-medium btn-press">Kumpulkan Jawaban</button>
    </form>
</div>

<!-- Modal Hasil -->
<div id="modalHasil" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6 text-center">
            <div id="hasilIcon" class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"></div>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Hasil PR</h3>
            <p id="hasilNilai" class="text-4xl font-bold text-blue-600 mb-2"></p>
            <p id="hasilDetail" class="text-sm text-gray-500 dark:text-gray-400"></p>
            <button onclick="closeModal('modalHasil');backToList();loadPRList()" class="w-full mt-5 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm btn-press">OK</button>
        </div>
    </div>
</div>


<script>
const BASE='<?= BASE_URL ?>siswa_pr.php', CSRF='<?= csrf_token() ?>';

document.addEventListener('DOMContentLoaded', ()=>loadPRList());

async function loadPRList(){
    const res=await fetchAPI(`${BASE}?action=list`);
    const el=document.getElementById('prList');
    if(res.success&&res.data.length>0){
        el.innerHTML=res.data.map(r=>{
            const isPast=r.deadline&&new Date(r.deadline)<new Date();
            const statusClass=r.status==='BELUM_DIKERJAKAN'?'border-orange-300 bg-orange-50 dark:bg-orange-900/10':r.status==='SUDAH_DIKERJAKAN'?'border-green-300 bg-green-50 dark:bg-green-900/10':'border-blue-300 bg-blue-50 dark:bg-blue-900/10';
            return `<div class="p-4 rounded-xl border ${statusClass} hover-elevate">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800 dark:text-white">${esc(r.judul)}</h4>
                        <p class="text-xs text-gray-500 mt-1">${esc(r.mapel||'-')} &bull; ${r.total_soal} soal</p>
                        ${r.deadline?`<p class="text-xs ${isPast?'text-red-500':'text-gray-500'} mt-1">Deadline: ${r.deadline.substring(0,16).replace('T',' ')}</p>`:''}
                        ${r.deskripsi?`<p class="text-xs text-gray-500 mt-1">${esc(r.deskripsi)}</p>`:''}
                    </div>
                    <div class="text-right ml-3">
                        ${r.status==='BELUM_DIKERJAKAN'&&!isPast?`<button onclick="startPR(${r.id})" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs btn-press">Kerjakan</button>`:''}
                        ${r.status==='SUDAH_DIKERJAKAN'?`<span class="text-xl font-bold text-green-600">${r.nilai}</span><p class="text-xs text-gray-500">Nilai</p>`:''}
                        ${r.status==='BELUM_DIKERJAKAN'&&isPast?`<span class="text-xs text-red-500">Expired</span>`:''}
                    </div>
                </div>
                ${r.komentar_tutor?`<div class="mt-2 p-2 bg-white dark:bg-gray-700 rounded-lg text-xs text-gray-600 dark:text-gray-300"><strong>Komentar:</strong> ${esc(r.komentar_tutor)}</div>`:''}
            </div>`;
        }).join('');
    } else { el.innerHTML='<p class="text-center text-gray-400 py-8">Belum ada PR untuk Anda.</p>'; }
}

async function startPR(assignmentId){
    const res=await fetchAPI(`${BASE}?action=get_soal&assignment_id=${assignmentId}`);
    if(!res.success){showToast(res.message,'error');return;}
    
    document.getElementById('prListView').classList.add('hidden');
    document.getElementById('prWorkView').classList.remove('hidden');
    document.getElementById('prWorkTitle').textContent=res.pr.judul;
    document.getElementById('work_assignment_id').value=assignmentId;
    
    const container=document.getElementById('soalContainer');
    container.innerHTML=res.soal.map((s,i)=>{
        const saved=res.jawaban[s.id]||'';
        const opts=['A','B','C','D','E'];
        const opsiFields=['opsi_a','opsi_b','opsi_c','opsi_d','opsi_e'];
        let optionsHtml='';
        for(let j=0;j<5;j++){
            if(!s[opsiFields[j]])continue;
            optionsHtml+=`<label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-blue-50 dark:hover:bg-blue-900/10 cursor-pointer transition-colors ${saved===opts[j]?'bg-blue-50 dark:bg-blue-900/20 border-blue-300':''}">
                <input type="radio" name="answers[${s.id}]" value="${opts[j]}" ${saved===opts[j]?'checked':''} class="mt-0.5">
                <span class="text-sm"><strong>${opts[j]}.</strong> ${esc(s[opsiFields[j]])}</span>
            </label>`;
        }
        return `<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 slide-up">
            <p class="text-sm font-medium text-gray-800 dark:text-white mb-3">${i+1}. ${esc(s.pertanyaan)}</p>
            <div class="space-y-2">${optionsHtml}</div>
        </div>`;
    }).join('');
}

function backToList(){
    document.getElementById('prListView').classList.remove('hidden');
    document.getElementById('prWorkView').classList.add('hidden');
}

document.getElementById('formKerjakan').addEventListener('submit', async function(e){
    e.preventDefault();
    if(!confirm('Yakin ingin mengumpulkan jawaban? Anda tidak bisa mengubah setelah ini.'))return;
    const btn=document.getElementById('btnKumpulkan');setButtonLoading(btn,true);
    const fd=new FormData(this);
    fd.append('action','submit');
    fd.append('assignment_id',document.getElementById('work_assignment_id').value);
    fd.append('csrf_token',CSRF);
    const res=await fetchAPI(BASE,{method:'POST',body:fd});
    setButtonLoading(btn,false);
    if(res.success){
        const pct=res.nilai;
        const iconColor=pct>=70?'bg-green-100 text-green-600':pct>=50?'bg-yellow-100 text-yellow-600':'bg-red-100 text-red-600';
        document.getElementById('hasilIcon').className=`w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 ${iconColor}`;
        document.getElementById('hasilIcon').innerHTML='<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="'+(pct>=70?'M5 13l4 4L19 7':'M12 8v4m0 4h.01')+'"/></svg>';
        document.getElementById('hasilNilai').textContent=res.nilai;
        document.getElementById('hasilDetail').textContent=`Benar ${res.benar} dari ${res.total} soal`;
        openModal('modalHasil');
    } else showToast(res.message,'error');
});

function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
