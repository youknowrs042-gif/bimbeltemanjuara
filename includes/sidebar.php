<?php
/**
 * SIDEBAR.PHP - Navigasi Sidebar berdasarkan Role
 */
$role = current_role();

// Menu berdasarkan role
$menus = [];

if ($role === 'ADMIN') {
    $menus = [
        ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php'],
        ['icon' => 'people', 'label' => 'Siswa', 'url' => 'siswa.php'],
        ['icon' => 'family', 'label' => 'Orang Tua', 'url' => 'ortu.php'],
        ['icon' => 'school', 'label' => 'Tutor', 'url' => 'tutor.php'],
        ['icon' => 'package', 'label' => 'Program & Paket', 'url' => 'program.php'],
        ['icon' => 'enroll', 'label' => 'Enrolment', 'url' => 'enrolment.php'],
        ['icon' => 'payment', 'label' => 'Pembayaran', 'url' => 'pembayaran.php'],
        ['icon' => 'presence', 'label' => 'Presensi', 'url' => 'presensi.php'],
        ['icon' => 'salary', 'label' => 'Gaji Tutor', 'url' => 'gaji.php'],
        ['icon' => 'book', 'label' => 'Bank Soal', 'url' => 'bank_soal.php'],
        ['icon' => 'quiz', 'label' => 'PR / Kuis', 'url' => 'pr.php'],
        ['icon' => 'exam', 'label' => 'Try Out', 'url' => 'tryout.php'],
        ['icon' => 'report', 'label' => 'Laporan', 'url' => 'laporan.php'],
        ['icon' => 'log', 'label' => 'Log Aktivitas', 'url' => 'log.php'],
    ];
} elseif ($role === 'TUTOR') {
    $menus = [
        ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php'],
        ['icon' => 'people', 'label' => 'Siswa Saya', 'url' => 'tutor_siswa.php'],
        ['icon' => 'presence', 'label' => 'Presensi', 'url' => 'tutor_presensi.php'],
        ['icon' => 'book', 'label' => 'Bank Soal', 'url' => 'tutor_bank_soal.php'],
        ['icon' => 'quiz', 'label' => 'PR / Kuis', 'url' => 'tutor_pr.php'],
        ['icon' => 'exam', 'label' => 'Try Out', 'url' => 'tutor_tryout.php'],
        ['icon' => 'report', 'label' => 'Perkembangan', 'url' => 'tutor_perkembangan.php'],
    ];
} elseif ($role === 'SISWA') {
    $menus = [
        ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php'],
        ['icon' => 'presence', 'label' => 'Presensi Saya', 'url' => 'siswa_presensi.php'],
        ['icon' => 'book', 'label' => 'Materi', 'url' => 'siswa_materi.php'],
        ['icon' => 'quiz', 'label' => 'PR / Kuis', 'url' => 'siswa_pr.php'],
        ['icon' => 'exam', 'label' => 'Try Out', 'url' => 'siswa_tryout.php'],
        ['icon' => 'report', 'label' => 'Perkembangan', 'url' => 'siswa_perkembangan.php'],
    ];
} elseif ($role === 'ORANG_TUA') {
    $menus = [
        ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php'],
        ['icon' => 'people', 'label' => 'Data Anak', 'url' => 'ortu_anak.php'],
        ['icon' => 'report', 'label' => 'Perkembangan', 'url' => 'ortu_perkembangan.php'],
        ['icon' => 'payment', 'label' => 'Pembayaran', 'url' => 'ortu_pembayaran.php'],
    ];
}

// SVG Icons map
function get_menu_icon(string $icon): string {
    $icons = [
        'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
        'people' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>',
        'family' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
        'school' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
        'package' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
        'enroll' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>',
        'payment' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>',
        'presence' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
        'salary' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'book' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
        'quiz' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
        'exam' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>',
        'report' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
        'log' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>',
    ];
    return $icons[$icon] ?? $icons['dashboard'];
}
?>
