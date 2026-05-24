<?php
/**
 * BACKUP.PHP - Database Backup Script
 * Jalankan via CLI atau cron: php /path/to/backup.php
 * 
 * CRON SETUP di cPanel:
 * 0 2 * * * /usr/local/bin/php /home/bimbelt1/public_html/backup.php
 * (Setiap hari jam 02:00)
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    // Allow admin access via web with auth
    require_once __DIR__ . '/helpers.php';
    if (!is_logged_in() || current_role() !== 'ADMIN') {
        http_response_code(403);
        die('Forbidden');
    }
}

require_once __DIR__ . '/config.php';

$backup_dir = BACKUP_DIR;
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_His') . '.sql';
$filepath = $backup_dir . $filename;

// Use mysqldump if available
$mysqldump = '/usr/bin/mysqldump';
if (file_exists($mysqldump)) {
    $cmd = sprintf(
        '%s --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s 2>&1',
        escapeshellcmd($mysqldump),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($filepath)
    );
    exec($cmd, $output, $return_code);
    
    if ($return_code === 0 && file_exists($filepath) && filesize($filepath) > 0) {
        // Compress
        $gz_file = $filepath . '.gz';
        $fp = fopen($filepath, 'rb');
        $gz = gzopen($gz_file, 'wb9');
        while (!feof($fp)) {
            gzwrite($gz, fread($fp, 524288));
        }
        fclose($fp);
        gzclose($gz);
        unlink($filepath); // Remove uncompressed
        
        // Delete old backups (keep last 7)
        $files = glob($backup_dir . 'backup_*.sql.gz');
        if (count($files) > 7) {
            usort($files, function($a, $b) { return filemtime($a) - filemtime($b); });
            $to_delete = array_slice($files, 0, count($files) - 7);
            foreach ($to_delete as $f) unlink($f);
        }
        
        $message = "Backup berhasil: $filename.gz (" . round(filesize($gz_file)/1024, 1) . " KB)";
    } else {
        $message = "Backup gagal. Return code: $return_code";
    }
} else {
    // Fallback: PHP-based backup
    $tables = [];
    $result = mysqli_query($conn, "SHOW TABLES");
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    
    $sql_dump = "-- Backup " . DB_NAME . " - " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        // Create table
        $create = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE `$table`"));
        $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n{$create[1]};\n\n";
        
        // Data
        $data = mysqli_query($conn, "SELECT * FROM `$table`");
        if (mysqli_num_rows($data) > 0) {
            while ($row = mysqli_fetch_assoc($data)) {
                $values = array_map(function($v) use ($conn) {
                    if ($v === null) return 'NULL';
                    return "'" . mysqli_real_escape_string($conn, $v) . "'";
                }, array_values($row));
                $sql_dump .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
            }
            $sql_dump .= "\n";
        }
    }
    
    $sql_dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    file_put_contents($filepath, $sql_dump);
    
    // Compress
    $gz_file = $filepath . '.gz';
    $fp = fopen($filepath, 'rb');
    $gz = gzopen($gz_file, 'wb9');
    while (!feof($fp)) {
        gzwrite($gz, fread($fp, 524288));
    }
    fclose($fp);
    gzclose($gz);
    unlink($filepath);
    
    // Cleanup old
    $files = glob($backup_dir . 'backup_*.sql.gz');
    if (count($files) > 7) {
        usort($files, function($a, $b) { return filemtime($a) - filemtime($b); });
        foreach (array_slice($files, 0, count($files) - 7) as $f) unlink($f);
    }
    
    $message = "Backup berhasil (PHP): $filename.gz";
}

// Output
if (php_sapi_name() === 'cli') {
    echo $message . "\n";
} else {
    set_flash('success', $message);
    redirect('dashboard.php');
}
