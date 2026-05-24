<?php
/**
 * SISWA_TRYOUT.PHP - Siswa mengerjakan Try Out dengan Timer & Autosave
 * Bimbel Teman Juara
 */
require_once __DIR__ . '/helpers.php';
auth_check();
role_check('SISWA');
block_if_locked();

define('PAGE_TITLE', 'Try Out');

$user_id = current_user_id();

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'list':
            $tryouts = db_fetch_all("
                SELECT t.*, bs.judul as bank_soal_judul,
                       (SELECT COUNT(*) FROM soal WHERE bank_soal_id = t.bank_soal_id) as total_soal,
                       (SELECT COUNT(*) FROM tryout_attempt WHERE tryout_id = t.id AND siswa_user_id = ?) as my_attempts,
                       (SELECT MAX(nilai) FROM tryout_attempt WHERE tryout_id = t.id AND siswa_user_id = ? AND status = 'SELESAI') as best_score
                FROM tryout t
                JOIN bank_soal bs ON t.bank_soal_id = bs.id
                WHERE t.status = 'PUBLISHED'
                AND (t.tanggal_mulai IS NULL OR t.tanggal_mulai <= NOW())
                AND (t.tanggal_selesai IS NULL OR t.tanggal_selesai >= NOW())
                ORDER BY t.created_at DESC
            ", [$user_id, $user_id], 'ii');
            json_response(['success'=>true,'data'=>$tryouts]);
            break;

        case 'start':
            csrf_check();
            $tryout_id = (int)($_POST['tryout_id'] ?? 0);
            $tryout = db_fetch_one("SELECT * FROM tryout WHERE id=? AND status='PUBLISHED'", [$tryout_id], 'i');
            if (!$tryout) json_response(['success'=>false,'message'=>'Try Out tidak tersedia.']);
            
            // Check attempts
            $attempts = db_count("SELECT COUNT(*) FROM tryout_attempt WHERE tryout_id=? AND siswa_user_id=?", [$tryout_id, $user_id], 'ii');
            
            // Check if allowed retry
            $has_retry = db_fetch_one("SELECT id FROM tryout_attempt WHERE tryout_id=? AND siswa_user_id=? AND izin_ulang=1 AND status='SELESAI' ORDER BY id DESC LIMIT 1", [$tryout_id, $user_id], 'ii');
            
            if ($attempts >= $tryout['max_attempt'] && !$has_retry) {
                json_response(['success'=>false,'message'=>'Anda sudah mencapai batas percobaan. Minta izin ulang dari admin.']);
            }
            
            // Check ongoing attempt
            $ongoing = db_fetch_one("SELECT * FROM tryout_attempt WHERE tryout_id=? AND siswa_user_id=? AND status='BERLANGSUNG'", [$tryout_id, $user_id], 'ii');
            if ($ongoing) {
                json_response(['success'=>true,'message'=>'Melanjutkan attempt.','attempt_id'=>$ongoing['id']]);
            }
            
            // Get soal and randomize
            $soal_ids = db_fetch_all("SELECT id FROM soal WHERE bank_soal_id=? ORDER BY urutan, id", [$tryout['bank_soal_id']], 'i');
            $ids = array_column($soal_ids, 'id');
            if ($tryout['randomize_soal']) shuffle($ids);
            $soal_order = json_encode($ids);
            
            // Reset retry flag
            if ($has_retry) db_query("UPDATE tryout_attempt SET izin_ulang=0 WHERE id=?", [$has_retry['id']], 'i');
            
            $attempt_id = db_insert(
                "INSERT INTO tryout_attempt (tryout_id, siswa_user_id, attempt_ke, soal_order, status) VALUES (?,?,?,?,'BERLANGSUNG')",
                [$tryout_id, $user_id, $attempts + 1, $soal_order], 'iiis'
            );
            
            json_response(['success'=>true,'message'=>'Try Out dimulai!','attempt_id'=>$attempt_id]);
            break;


        case 'get_soal':
            $attempt_id = (int)($_GET['attempt_id'] ?? 0);
            $attempt = db_fetch_one("SELECT ta.*, t.durasi_menit, t.judul, t.randomize_opsi, t.bank_soal_id FROM tryout_attempt ta JOIN tryout t ON ta.tryout_id=t.id WHERE ta.id=? AND ta.siswa_user_id=?", [$attempt_id, $user_id], 'ii');
            if (!$attempt) json_response(['success'=>false,'message'=>'Attempt tidak ditemukan.']);
            if ($attempt['status'] !== 'BERLANGSUNG') json_response(['success'=>false,'message'=>'Try Out sudah selesai.']);
            
            // Check timeout
            $start = strtotime($attempt['started_at']);
            $elapsed = time() - $start;
            $remaining = ($attempt['durasi_menit'] * 60) - $elapsed;
            if ($remaining <= 0) {
                // Auto finish
                finishAttempt($attempt_id, $attempt['bank_soal_id']);
                json_response(['success'=>false,'message'=>'Waktu habis! Try Out otomatis diselesaikan.']);
            }
            
            // Get soal in order
            $order = json_decode($attempt['soal_order'], true) ?: [];
            $soal = [];
            foreach ($order as $sid) {
                $s = db_fetch_one("SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e FROM soal WHERE id=?", [$sid], 'i');
                if ($s) $soal[] = $s;
            }
            
            // Get saved answers
            $saved = db_fetch_all("SELECT soal_id, jawaban FROM tryout_jawaban WHERE attempt_id=?", [$attempt_id], 'i');
            $answers = [];
            foreach ($saved as $sv) $answers[$sv['soal_id']] = $sv['jawaban'];
            
            json_response(['success'=>true,'attempt'=>$attempt,'soal'=>$soal,'answers'=>$answers,'remaining'=>$remaining]);
            break;

        case 'autosave':
            // Save single answer via AJAX
            $attempt_id = (int)($_POST['attempt_id'] ?? 0);
            $soal_id = (int)($_POST['soal_id'] ?? 0);
            $jawaban = $_POST['jawaban'] ?? null;
            
            if (!$attempt_id || !$soal_id) json_response(['success'=>false]);
            
            // Verify ownership & status
            $at = db_fetch_one("SELECT id FROM tryout_attempt WHERE id=? AND siswa_user_id=? AND status='BERLANGSUNG'", [$attempt_id, $user_id], 'ii');
            if (!$at) json_response(['success'=>false]);
            
            // Upsert
            $existing = db_fetch_one("SELECT id FROM tryout_jawaban WHERE attempt_id=? AND soal_id=?", [$attempt_id, $soal_id], 'ii');
            if ($existing) {
                db_query("UPDATE tryout_jawaban SET jawaban=?, answered_at=NOW() WHERE id=?", [$jawaban, $existing['id']], 'si');
            } else {
                db_insert("INSERT INTO tryout_jawaban (attempt_id, soal_id, jawaban, answered_at) VALUES (?,?,?,NOW())", [$attempt_id, $soal_id, $jawaban], 'iis');
            }
            json_response(['success'=>true]);
            break;

        case 'finish':
            csrf_check();
            $attempt_id = (int)($_POST['attempt_id'] ?? 0);
            $attempt = db_fetch_one("SELECT ta.*, t.bank_soal_id FROM tryout_attempt ta JOIN tryout t ON ta.tryout_id=t.id WHERE ta.id=? AND ta.siswa_user_id=? AND ta.status='BERLANGSUNG'", [$attempt_id, $user_id], 'ii');
            if (!$attempt) json_response(['success'=>false,'message'=>'Attempt tidak valid.']);
            
            $result = finishAttempt($attempt_id, $attempt['bank_soal_id']);
            json_response(['success'=>true,'message'=>'Try Out selesai!','nilai'=>$result['nilai'],'benar'=>$result['benar'],'total'=>$result['total']]);
            break;
    }
    exit;
}

function finishAttempt(int $attempt_id, int $bank_soal_id): array {
    // Calculate score
    $soal_list = db_fetch_all("SELECT id, jawaban_benar FROM soal WHERE bank_soal_id=?", [$bank_soal_id], 'i');
    $total = count($soal_list);
    $benar = 0;
    
    foreach ($soal_list as $soal) {
        $jawaban = db_fetch_one("SELECT jawaban FROM tryout_jawaban WHERE attempt_id=? AND soal_id=?", [$attempt_id, $soal['id']], 'ii');
        $is_correct = ($jawaban && $jawaban['jawaban'] === $soal['jawaban_benar']) ? 1 : 0;
        if ($is_correct) $benar++;
        
        // Update is_benar
        db_query("UPDATE tryout_jawaban SET is_benar=? WHERE attempt_id=? AND soal_id=?", [$is_correct, $attempt_id, $soal['id']], 'iii');
    }
    
    $nilai = $total > 0 ? round(($benar / $total) * 100, 2) : 0;
    
    db_query("UPDATE tryout_attempt SET status='SELESAI', finished_at=NOW(), nilai=?, total_benar=?, total_soal=? WHERE id=?",
        [$nilai, $benar, $total, $attempt_id], 'diii');
    
    return ['nilai' => $nilai, 'benar' => $benar, 'total' => $total];
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/sidebar_html.php';
?>


<!-- List View -->
<div id="listView" class="space-y-4">
    <div id="tryoutList" class="space-y-3"><div class="text-center py-8 text-gray-400"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>Memuat...</div></div>
</div>

<!-- Exam View -->
<div id="examView" class="hidden">
    <!-- Timer Bar -->
    <div class="sticky top-16 z-20 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-3 mb-4 rounded-xl shadow-sm">
        <div class="flex items-center justify-between">
            <h3 id="examTitle" class="text-sm font-semibold text-gray-800 dark:text-white truncate"></h3>
            <div class="flex items-center gap-2">
                <span id="timerDisplay" class="px-3 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-full text-sm font-mono font-bold"></span>
                <button onclick="finishExam()" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs btn-press">Selesai</button>
            </div>
        </div>
        <!-- Progress -->
        <div class="mt-2 flex gap-1 flex-wrap" id="progressDots"></div>
    </div>
    
    <!-- Soal -->
    <div id="examSoalContainer" class="space-y-4"></div>
</div>

<!-- Modal Result -->
<div id="modalResult" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 modal-backdrop p-4">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm transform transition-all duration-200 scale-95 opacity-0">
        <div class="p-6 text-center">
            <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-1">Try Out Selesai!</h3>
            <p id="resultNilai" class="text-4xl font-bold text-blue-600 my-3"></p>
            <p id="resultDetail" class="text-sm text-gray-500 dark:text-gray-400"></p>
            <button onclick="closeModal('modalResult');backToList()" class="w-full mt-5 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm btn-press">Kembali</button>
        </div>
    </div>
</div>

<script>
const BASE='<?= BASE_URL ?>siswa_tryout.php', CSRF='<?= csrf_token() ?>';
let timerInterval=null, remainingSeconds=0, currentAttemptId=0, autosaveInterval=null;

document.addEventListener('DOMContentLoaded', ()=>loadList());

async function loadList(){
    const res=await fetchAPI(`${BASE}?action=list`);
    const el=document.getElementById('tryoutList');
    if(res.success&&res.data.length>0){
        el.innerHTML=res.data.map(t=>{
            const canTake=t.my_attempts<t.max_attempt||t.best_score===null;
            return `<div class="p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover-elevate">
                <div class="flex items-start justify-between">
                    <div class="flex-1"><h4 class="font-semibold text-gray-800 dark:text-white">${esc(t.judul)}</h4>
                        <p class="text-xs text-gray-500 mt-1">${esc(t.mapel||'-')} &bull; ${t.total_soal} soal &bull; ${t.durasi_menit} menit</p>
                        ${t.deskripsi?`<p class="text-xs text-gray-500 mt-1">${esc(t.deskripsi)}</p>`:''}
                        <p class="text-xs text-gray-500 mt-1">Attempt: ${t.my_attempts}/${t.max_attempt}</p>
                    </div>
                    <div class="text-right ml-3">
                        ${t.best_score!==null?`<p class="text-xl font-bold text-blue-600">${t.best_score}</p><p class="text-xs text-gray-500">Best</p>`:''}
                        ${canTake?`<button onclick="startTryout(${t.id})" class="mt-2 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs btn-press">Mulai</button>`:`<span class="text-xs text-gray-400">Max attempts</span>`}
                    </div>
                </div>
            </div>`;
        }).join('');
    } else el.innerHTML='<p class="text-center text-gray-400 py-8">Tidak ada Try Out tersedia.</p>';
}

async function startTryout(tryoutId){
    const fd=new FormData();fd.append('action','start');fd.append('tryout_id',tryoutId);fd.append('csrf_token',CSRF);
    const res=await fetchAPI(BASE,{method:'POST',body:fd});
    if(res.success){
        currentAttemptId=res.attempt_id;
        await loadExam(res.attempt_id);
    } else showToast(res.message,'error');
}

async function loadExam(attemptId){
    const res=await fetchAPI(`${BASE}?action=get_soal&attempt_id=${attemptId}`);
    if(!res.success){showToast(res.message,'error');loadList();return;}
    
    document.getElementById('listView').classList.add('hidden');
    document.getElementById('examView').classList.remove('hidden');
    document.getElementById('examTitle').textContent=res.attempt.judul;
    
    remainingSeconds=res.remaining;
    startTimer();
    
    // Render progress dots
    document.getElementById('progressDots').innerHTML=res.soal.map((_,i)=>`<div id="dot${i}" class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center text-xs cursor-pointer hover:border-blue-500" onclick="scrollToSoal(${i})">${i+1}</div>`).join('');
    
    // Render soal
    const container=document.getElementById('examSoalContainer');
    container.innerHTML=res.soal.map((s,i)=>{
        const saved=res.answers[s.id]||'';
        const opts=['A','B','C','D','E'];
        const fields=['opsi_a','opsi_b','opsi_c','opsi_d','opsi_e'];
        let optHtml='';
        for(let j=0;j<5;j++){
            if(!s[fields[j]])continue;
            optHtml+=`<label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-blue-50 dark:hover:bg-blue-900/10 cursor-pointer transition-colors ${saved===opts[j]?'bg-blue-50 dark:bg-blue-900/20 border-blue-300':''}">
                <input type="radio" name="ans_${s.id}" value="${opts[j]}" data-soal="${s.id}" ${saved===opts[j]?'checked':''} onchange="saveAnswer(${s.id},'${opts[j]}',${i})" class="mt-0.5">
                <span class="text-sm"><strong>${opts[j]}.</strong> ${esc(s[fields[j]])}</span>
            </label>`;
        }
        return `<div id="soal${i}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 slide-up">
            <p class="text-sm font-medium text-gray-800 dark:text-white mb-3">${i+1}. ${esc(s.pertanyaan)}</p>
            <div class="space-y-2">${optHtml}</div>
        </div>`;
    }).join('');
    
    // Update dots for saved answers
    res.soal.forEach((s,i)=>{if(res.answers[s.id])markDot(i,true);});
}


function startTimer(){
    updateTimerDisplay();
    timerInterval=setInterval(()=>{
        remainingSeconds--;
        updateTimerDisplay();
        if(remainingSeconds<=0){clearInterval(timerInterval);finishExam();}
    },1000);
}

function updateTimerDisplay(){
    const m=Math.floor(remainingSeconds/60);
    const s=remainingSeconds%60;
    const display=`${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    const el=document.getElementById('timerDisplay');
    el.textContent=display;
    if(remainingSeconds<300) el.classList.add('animate-pulse');
}

function markDot(index, answered){
    const dot=document.getElementById('dot'+index);
    if(dot){
        if(answered){dot.classList.remove('border-gray-300');dot.classList.add('bg-blue-500','border-blue-500','text-white');}
    }
}

function scrollToSoal(i){document.getElementById('soal'+i)?.scrollIntoView({behavior:'smooth',block:'center'});}

async function saveAnswer(soalId, jawaban, index){
    markDot(index, true);
    // Autosave via AJAX
    const fd=new FormData();
    fd.append('action','autosave');
    fd.append('attempt_id',currentAttemptId);
    fd.append('soal_id',soalId);
    fd.append('jawaban',jawaban);
    fd.append('csrf_token',CSRF);
    fetchAPI(BASE,{method:'POST',body:fd});
}

async function finishExam(){
    if(remainingSeconds>0&&!confirm('Yakin ingin menyelesaikan Try Out?'))return;
    clearInterval(timerInterval);
    
    const fd=new FormData();
    fd.append('action','finish');
    fd.append('attempt_id',currentAttemptId);
    fd.append('csrf_token',CSRF);
    const res=await fetchAPI(BASE,{method:'POST',body:fd});
    
    if(res.success){
        document.getElementById('resultNilai').textContent=res.nilai;
        document.getElementById('resultDetail').textContent=`Benar ${res.benar} dari ${res.total} soal`;
        openModal('modalResult');
    } else showToast(res.message,'error');
}

function backToList(){
    document.getElementById('examView').classList.add('hidden');
    document.getElementById('listView').classList.remove('hidden');
    clearInterval(timerInterval);
    loadList();
}

function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
