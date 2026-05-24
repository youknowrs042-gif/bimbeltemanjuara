    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-200 dark:border-gray-700 px-4 py-3">
        <p class="text-xs text-center text-gray-500 dark:text-gray-400">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> v<?= APP_VERSION ?> - All rights reserved.
        </p>
    </footer>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-[100] space-y-2 pointer-events-none"></div>

<!-- Modal Container (for dynamic modals) -->
<div id="modalContainer"></div>

<!-- JavaScript Core -->
<script>
// ============================================================
// SIDEBAR TOGGLE
// ============================================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

// ============================================================
// DARK MODE TOGGLE
// ============================================================
function toggleDarkMode() {
    const html = document.documentElement;
    html.classList.toggle('dark');
    const isDark = html.classList.contains('dark') ? '1' : '0';
    document.cookie = `dark_mode=${isDark};path=/;max-age=31536000;SameSite=Lax`;
}

// ============================================================
// TOAST NOTIFICATION
// ============================================================
function showToast(message, type = 'success', duration = 4000) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const bgClass = type === 'success' 
        ? 'bg-green-500' 
        : type === 'error' ? 'bg-red-500' 
        : type === 'warning' ? 'bg-yellow-500' 
        : 'bg-blue-500';
    
    toast.className = `${bgClass} text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 text-sm fade-in max-w-sm pointer-events-auto`;
    toast.innerHTML = `
        <span class="flex-1">${message}</span>
        <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white ml-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ============================================================
// AUTO CLOSE FLASH ALERT
// ============================================================
const flashAlert = document.getElementById('flashAlert');
if (flashAlert) {
    setTimeout(() => {
        flashAlert.style.opacity = '0';
        flashAlert.style.transition = 'opacity 0.5s';
        setTimeout(() => flashAlert.remove(), 500);
    }, 5000);
}

// ============================================================
// ANIMATED COUNTER
// ============================================================
function animateCounter(el, target, duration = 1500) {
    let start = 0;
    const step = target / (duration / 16);
    const counter = setInterval(() => {
        start += step;
        if (start >= target) {
            el.textContent = target.toLocaleString('id-ID');
            clearInterval(counter);
        } else {
            el.textContent = Math.floor(start).toLocaleString('id-ID');
        }
    }, 16);
}

// ============================================================
// DEBOUNCE (for Live Search)
// ============================================================
function debounce(func, wait = 300) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// ============================================================
// COPY TO CLIPBOARD
// ============================================================
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Berhasil disalin!', 'success');
    }).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showToast('Berhasil disalin!', 'success');
    });
}

// ============================================================
// LOADING BUTTON
// ============================================================
function setButtonLoading(btn, loading = true) {
    if (loading) {
        btn.disabled = true;
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = `<svg class="animate-spin h-4 w-4 mr-2 inline" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Memproses...`;
    } else {
        btn.disabled = false;
        if (btn.dataset.originalText) {
            btn.innerHTML = btn.dataset.originalText;
        }
    }
}

// ============================================================
// MODAL HELPERS
// ============================================================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modal.querySelector('.modal-content')?.classList.remove('scale-95', 'opacity-0');
            modal.querySelector('.modal-content')?.classList.add('scale-100', 'opacity-100');
        }, 10);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.querySelector('.modal-content')?.classList.remove('scale-100', 'opacity-100');
        modal.querySelector('.modal-content')?.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }, 200);
    }
}

// ============================================================
// AJAX HELPER
// ============================================================
async function fetchAPI(url, options = {}) {
    const config = {
        method: options.method || 'GET',
        credentials: 'same-origin'
    };
    
    if (options.body instanceof FormData) {
        // FormData: jangan set Content-Type (biar browser auto-set multipart/form-data)
        config.body = options.body;
        config.headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '<?= csrf_token() ?>'
        };
    } else if (options.body && typeof options.body === 'object') {
        // JSON object
        config.body = JSON.stringify(options.body);
        config.headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '<?= csrf_token() ?>'
        };
    } else {
        // GET request atau no body
        config.headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '<?= csrf_token() ?>'
        };
    }
    
    try {
        const response = await fetch(url, config);
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch(e) {
            console.error('JSON Parse Error:', text.substring(0, 500));
            showToast('Server error. Cek console.', 'error');
            return { success: false, message: 'Invalid server response' };
        }
    } catch (error) {
        console.error('API Error:', error);
        showToast('Terjadi kesalahan jaringan', 'error');
        return { success: false, message: 'Network error' };
    }
}

// ============================================================
// CONFIRM DELETE
// ============================================================
function confirmDelete(message, callback) {
    if (confirm(message || 'Yakin ingin menghapus data ini?')) {
        callback();
    }
}

// ============================================================
// SMOOTH SCROLL
// ============================================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href'))?.scrollIntoView({ behavior: 'smooth' });
    });
});

// ============================================================
// INIT: Animate Counters on page load
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-counter]').forEach(el => {
        const target = parseInt(el.dataset.counter);
        if (!isNaN(target)) {
            animateCounter(el, target);
        }
    });
});
</script>
</body>
</html>
