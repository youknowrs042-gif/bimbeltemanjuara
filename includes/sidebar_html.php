<!-- Overlay Mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden transition-opacity" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<aside id="sidebar" class="sidebar-transition fixed lg:static inset-y-0 left-0 z-50 w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 transform -translate-x-full lg:translate-x-0 flex flex-col">
    <!-- Logo -->
    <div class="flex items-center gap-3 px-4 py-5 border-b border-gray-200 dark:border-gray-700">
        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
            <span class="text-white font-bold text-lg">B</span>
        </div>
        <div class="sidebar-text">
            <h1 class="text-sm font-bold text-gray-800 dark:text-white leading-tight">Bimbel</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">Teman Juara</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        <?php foreach ($menus as $menu): ?>
            <?php 
            $menu_file = basename($menu['url']);
            $is_active = ($current_page === $menu_file); 
            ?>
            <a href="<?= BASE_URL . $menu['url'] ?>" 
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200
                      <?= $is_active 
                          ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 shadow-sm' 
                          : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <?= get_menu_icon($menu['icon']) ?>
                </svg>
                <span class="sidebar-text"><?= e($menu['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- User Info Bottom -->
    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center">
                <span class="text-sm font-bold text-white">
                    <?= strtoupper(substr($_SESSION['user_nama'] ?? 'U', 0, 1)) ?>
                </span>
            </div>
            <div class="sidebar-text flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate"><?= e($_SESSION['user_nama'] ?? '') ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400 capitalize"><?= e(strtolower(str_replace('_', ' ', $_SESSION['user_role'] ?? ''))) ?></p>
            </div>
            <a href="<?= BASE_URL ?>logout.php" class="sidebar-text text-gray-400 hover:text-red-500 transition-colors" title="Logout">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
            </a>
        </div>
    </div>
</aside>

<!-- Main Content Wrapper -->
<div class="flex-1 flex flex-col min-h-screen lg:ml-0">
    <!-- Top Bar -->
    <header class="sticky top-0 z-30 bg-white/80 dark:bg-gray-800/80 backdrop-blur-md border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between px-4 py-3">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white"><?= e(PAGE_TITLE) ?></h2>
            </div>
            <div class="flex items-center gap-2">
                <!-- Dark Mode Toggle -->
                <button onclick="toggleDarkMode()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Toggle Dark Mode">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-300 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <svg class="w-5 h-5 text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Flash Message -->
    <?php if ($flash): ?>
    <div id="flashAlert" class="mx-4 mt-4 fade-in">
        <div class="p-4 rounded-lg flex items-center gap-3 <?= $flash['type'] === 'success' ? 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800' ?>">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <?php if ($flash['type'] === 'success'): ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                <?php else: ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                <?php endif; ?>
            </svg>
            <p class="flex-1 text-sm"><?= e($flash['message']) ?></p>
            <button onclick="this.parentElement.parentElement.remove()" class="text-current opacity-50 hover:opacity-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Locked Account Warning for Siswa -->
    <?php if (check_siswa_lock()): ?>
    <div class="mx-4 mt-4">
        <div class="p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800 flex items-center gap-3">
            <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <div>
                <p class="font-semibold">Akun Terkunci</p>
                <p class="text-sm">Silakan bayar paket terlebih dahulu untuk mengakses semua fitur.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Page Content -->
    <main class="flex-1 p-4 md:p-6">
