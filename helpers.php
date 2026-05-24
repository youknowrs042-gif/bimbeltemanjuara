<?php
/**
 * ============================================================
 * HELPERS.PHP - Bimbel Teman Juara
 * Fungsi-fungsi utilitas umum
 * ============================================================
 */

require_once __DIR__ . '/config.php';

// ============================================================
// FUNGSI AUTENTIKASI & OTORISASI
// ============================================================

/**
 * Cek apakah user sudah login
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Paksa login - redirect jika belum login
 */
function auth_check(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

/**
 * Cek role user - redirect jika tidak sesuai
 * @param array|string $allowed_roles Role yang diizinkan
 */
function role_check($allowed_roles): void
{
    auth_check();
    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        set_flash('error', 'Anda tidak memiliki akses ke halaman ini.');
        redirect('dashboard.php');
    }
}

/**
 * Cek apakah akun siswa terkunci (belum bayar)
 * Return true jika terkunci
 */
function check_siswa_lock(): bool
{
    if (($_SESSION['user_role'] ?? '') === 'SISWA' && ($_SESSION['user_status'] ?? '') === 'TERKUNCI') {
        return true;
    }
    return false;
}

/**
 * Middleware untuk halaman yang harus tidak boleh diakses siswa terkunci
 */
function block_if_locked(): void
{
    if (check_siswa_lock()) {
        set_flash('error', 'Akun terkunci, silakan bayar paket terlebih dahulu.');
        redirect('dashboard.php');
    }
}

/**
 * Get current user role
 */
function current_role(): string
{
    return $_SESSION['user_role'] ?? '';
}

/**
 * Get current user ID
 */
function current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Check if current user is admin
 */
function is_admin(): bool
{
    return current_role() === 'ADMIN';
}

// ============================================================
// FUNGSI KEAMANAN
// ============================================================

/**
 * Escape output HTML
 */
function e(?string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render hidden CSRF field
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Validasi CSRF token
 */
function verify_csrf(string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Cek CSRF dan hentikan jika gagal
 */
function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf($token)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            json_response(['success' => false, 'message' => 'Token keamanan tidak valid.'], 403);
        }
        set_flash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}

/**
 * Sanitasi nama file upload
 */
function sanitize_filename(string $filename): string
{
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $name = bin2hex(random_bytes(16));
    return $name . '.' . strtolower($ext);
}

// ============================================================
// FUNGSI REDIRECT & RESPONSE
// ============================================================

/**
 * Redirect ke URL
 */
function redirect(string $url): void
{
    if (!str_starts_with($url, 'http')) {
        $url = BASE_URL . $url;
    }
    header("Location: $url");
    exit;
}

/**
 * JSON response untuk AJAX
 */
function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// FUNGSI FLASH MESSAGE
// ============================================================

/**
 * Set flash message
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get dan hapus flash message
 */
function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ============================================================
// FUNGSI DATABASE HELPER
// ============================================================

/**
 * Query dengan prepared statement
 */
function db_query(string $sql, array $params = [], string $types = ''): mysqli_result|bool
{
    global $conn;
    
    if (empty($params)) {
        $result = mysqli_query($conn, $sql);
        if ($result === false) {
            error_log("DB Error: " . mysqli_error($conn) . " | SQL: $sql");
        }
        return $result;
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("DB Prepare Error: " . mysqli_error($conn) . " | SQL: $sql");
        return false;
    }
    
    if (empty($types)) {
        $types = str_repeat('s', count($params));
    }
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result === false) {
        // Untuk INSERT, UPDATE, DELETE
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $affected >= 0;
    }
    
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Fetch satu row
 */
function db_fetch_one(string $sql, array $params = [], string $types = ''): ?array
{
    $result = db_query($sql, $params, $types);
    if ($result instanceof mysqli_result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        return $row;
    }
    return null;
}

/**
 * Fetch semua row
 */
function db_fetch_all(string $sql, array $params = [], string $types = ''): array
{
    $result = db_query($sql, $params, $types);
    if ($result instanceof mysqli_result) {
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_free_result($result);
        return $rows;
    }
    return [];
}

/**
 * Insert dan return last ID
 */
function db_insert(string $sql, array $params = [], string $types = ''): int
{
    global $conn;
    db_query($sql, $params, $types);
    return (int)mysqli_insert_id($conn);
}

/**
 * Hitung total row untuk pagination
 */
function db_count(string $sql, array $params = [], string $types = ''): int
{
    $row = db_fetch_one($sql, $params, $types);
    if ($row) {
        return (int)reset($row);
    }
    return 0;
}

// ============================================================
// FUNGSI PAGINATION
// ============================================================

/**
 * Generate pagination data
 */
function paginate(int $total, int $per_page = 10, int $current_page = 1): array
{
    $total_pages = max(1, (int)ceil($total / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;
    
    return [
        'total' => $total,
        'per_page' => $per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

/**
 * Render pagination HTML
 */
function render_pagination(array $paging, string $base_url = ''): string
{
    if ($paging['total_pages'] <= 1) return '';
    
    $separator = str_contains($base_url, '?') ? '&' : '?';
    $html = '<nav class="flex flex-col sm:flex-row items-center justify-between mt-6 gap-3">';
    $html .= '<div class="text-sm text-gray-600 dark:text-gray-400">Halaman ' . $paging['current_page'] . ' dari ' . $paging['total_pages'] . ' (' . $paging['total'] . ' data)</div>';
    $html .= '<div class="flex gap-1">';
    
    if ($paging['has_prev']) {
        $html .= '<a href="' . $base_url . $separator . 'page=' . ($paging['current_page'] - 1) . '" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">&laquo;</a>';
    }
    
    $start = max(1, $paging['current_page'] - 2);
    $end = min($paging['total_pages'], $paging['current_page'] + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $paging['current_page']) {
            $html .= '<span class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $base_url . $separator . 'page=' . $i . '" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">' . $i . '</a>';
        }
    }
    
    if ($paging['has_next']) {
        $html .= '<a href="' . $base_url . $separator . 'page=' . ($paging['current_page'] + 1) . '" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">&raquo;</a>';
    }
    
    $html .= '</div></nav>';
    return $html;
}

// ============================================================
// FUNGSI LOG AKTIVITAS
// ============================================================

/**
 * Simpan log aktivitas
 */
function log_activity(string $action, ?string $description = null): void
{
    $user_id = current_user_id() ?: null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 500);
    
    $sql = "INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    db_query($sql, [$user_id, $action, $description, $ip, $ua], 'issss');
}

// ============================================================
// FUNGSI FORMAT
// ============================================================

/**
 * Format tanggal Indonesia
 */
function format_tanggal(?string $date, bool $with_time = false): string
{
    if (!$date) return '-';
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $timestamp = strtotime($date);
    if ($timestamp === false) return '-';
    $format = (int)date('d', $timestamp) . ' ' . $bulan[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
    if ($with_time) {
        $format .= ' ' . date('H:i', $timestamp);
    }
    return $format;
}

/**
 * Format rupiah
 */
function format_rupiah($angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

/**
 * Format nomor invoice
 */
function generate_invoice_number(): string
{
    $year = date('Y');
    $sql = "SELECT COUNT(*) as cnt FROM pembayaran WHERE YEAR(created_at) = ?";
    $row = db_fetch_one($sql, [$year], 'i');
    $seq = ($row['cnt'] ?? 0) + 1;
    return 'INV-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate random password
 */
function generate_password(int $length = 8): string
{
    $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Generate username from name
 */
function generate_username(string $nama, string $role): string
{
    $prefix = match($role) {
        'SISWA' => 'siswa',
        'TUTOR' => 'tutor',
        'ORANG_TUA' => 'ortu',
        default => 'user'
    };
    
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nama));
    $base = substr($base, 0, 10);
    $username = $prefix . '_' . $base;
    
    // Cek unik
    $existing = db_fetch_one("SELECT id FROM users WHERE username = ?", [$username]);
    if ($existing) {
        $username .= '_' . random_int(100, 999);
    }
    
    return $username;
}

// ============================================================
// FUNGSI UPLOAD FILE
// ============================================================

/**
 * Upload file dengan validasi
 */
function upload_file(array $file, string $subfolder = ''): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (batas server).',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (batas form).',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian.',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file.',
        ];
        $msg = $errors[$file['error']] ?? 'Upload gagal: error code ' . $file['error'];
        return ['success' => false, 'message' => $msg];
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'Ukuran file melebihi batas 500MB.'];
    }
    
    $target_dir = UPLOAD_DIR . ($subfolder ? $subfolder . '/' : '');
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $safe_name = sanitize_filename($file['name']);
    $target_path = $target_dir . $safe_name;
    
    // Proteksi path traversal
    $real_dir = realpath($target_dir);
    if ($real_dir === false) {
        // Directory baru dibuat, cek parent
        $real_dir = $target_dir;
    }
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Simpan metadata ke DB
        $mime = mime_content_type($target_path) ?: 'application/octet-stream';
        $rel_path = ($subfolder ? $subfolder . '/' : '') . $safe_name;
        
        db_insert(
            "INSERT INTO file_uploads (user_id, original_name, stored_name, file_path, file_size, mime_type, kategori) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [current_user_id(), $file['name'], $safe_name, $rel_path, $file['size'], $mime, $subfolder],
            'isssiss'
        );
        
        return [
            'success' => true, 
            'filename' => $safe_name,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'path' => $rel_path,
            'mime' => $mime
        ];
    }
    
    return ['success' => false, 'message' => 'Gagal menyimpan file.'];
}

/**
 * Get full URL for uploaded file
 */
function upload_url(string $path): string
{
    return BASE_URL . 'uploads/' . $path;
}
