<?php
/**
 * BACKUP.PHP - Script Backup Database Otomatis
 * Bimbel Teman Juara
 * 
 * Jalankan via cron cPanel:
 * 0 2 * * * /usr/local/bin/php /home/bimbelt1/public_html/backup.php
 * (Setiap hari pukul 02:00 WIB)
 */

// Jalankan tanpa web access
if (php_sapi_name() !== 'cli' && !defined('BACKUP_ALLOWED')) {
    die('Access denied.');
}

require_once __DIR__ . '/config.php';

$backup_dir = __DIR__ . '/uploads/backup/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_His') . '.sql';
$filepath = $backup_dir . $filename;

// Get all tables
$tables = [];
$result = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_row($result)) {
    $tables[] = $row[0];
}

$sql_content = "-- Backup Database: " . DB_NAME . "\n";
$sql_content .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n";
$sql_content .= "-- Generator: Bimbel Teman Juara Backup Script\n\n";
$sql_content .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // Create table statement
    $create = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
    $row = mysqli_fetch_assoc($create);
    $sql_content .= "DROP TABLE IF EXISTS `$table`;\n";
    $sql_content .= $row['Create Table'] . ";\n\n";
    
    // Insert data
    $data = mysqli_query($conn, "SELECT * FROM `$table`");
    $num_fields = mysqli_num_fields($data);
    
    while ($row = mysqli_fetch_row($data)) {
        $sql_content .= "INSERT INTO `$table` VALUES(";
        $values = [];
        for ($i = 0; $i < $num_fields; $i++) {
            if ($row[$i] === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . mysqli_real_escape_string($conn, $row[$i]) . "'";
            }
        }
        $sql_content .= implode(',', $values) . ");\n";
    }
    $sql_content .= "\n";
}

$sql_content .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Save file
file_put_contents($filepath, $sql_content);

// Delete backups older than 30 days
$files = glob($backup_dir . 'backup_*.sql');
foreach ($files as $file) {
    if (filemtime($file) < time() - (30 * 24 * 60 * 60)) {
        unlink($file);
    }
}

echo "Backup berhasil: $filename\n";
