    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-200 dark:border-gray-700 px-4 py-3">
        <p class="text-xs text-center text-gray-500 dark:text-gray-400">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> - All rights reserved.
        </p>
    </footer>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-[100] space-y-2"></div>

<!-- JavaScript Core -->
<script>
// Sidebar Toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

// Dark Mode Toggle
function toggleDarkMode() {
    const html = document.documentElement;
    html.classList.toggle('dark');
    const isDark = html.classList.contains('dark') ? '1' : '0';
    document.cookie = `dark_mode=${isDark};path=/;max-age=31536000`;
}

// Toast Notification
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const bgClass = type === 'success' 
        ? 'bg-green-500' 
        : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    
    toast.className = `${bgClass} text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 text-sm fade-in max-w-sm`;
    toast.innerHTML = `
        <span class="flex-1">${message}</span>
        <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    `;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
}

// Auto close flash alert
const flashAlert = document.getElementById('flashAlert');
if (flashAlert) {
    setTimeout(() => { flashAlert.style.opacity = '0'; flashAlert.style.transition = 'opacity 0.5s'; setTimeout(() => flashAlert.remove(), 500); }, 5000);
}

// Animated Counter
function animateCounter(el, target, duration = 1500) {
    let start = 0;
    const step = target / (duration / 16);
    const counter = setInterval(() => {
        start += step;
        if (start >= target) { el.textContent = target.toLocaleString('id-ID'); clearInterval(counter); }
        else { el.textContent = Math.floor(start).toLocaleString('id-ID'); }
    }, 16);
}

// Debounce for Live Search
function debounce(func, wait = 300) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Copy to Clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => showToast('Berhasil disalin!'));
}

// Loading Button
function setButtonLoading(btn, loading = true) {
    if (loading) {
        btn.disabled = true;
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = `<svg class="animate-spin h-4 w-4 mr-2 inline" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Memproses...`;
    } else {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.originalText;
    }
}

// Smooth Scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href'))?.scrollIntoView({ behavior: 'smooth' });
    });
});

// Initialize counters on page load
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-counter]').forEach(el => {
        animateCounter(el, parseInt(el.dataset.counter));
    });
});
</script>
</body>
</html>
