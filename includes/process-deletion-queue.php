<?php
/**
 * Process Deletion Queue
 * 
 * This script processes the deletion-queue.log file and transfers entries
 * into the main logs database. Run this script via cron or manually.
 * 
 * Usage: php process-deletion-queue.php
 */

require_once __DIR__ . '/db_helper.php';

$logFile = __DIR__ . '/../assets/db/deletion-queue.log';
$processedFile = __DIR__ . '/../assets/db/deletion-queue.processed';

// Check if queue file exists
if (!file_exists($logFile)) {
    echo "No queue file found. Nothing to process.\n";
    exit(0);
}

// Read queue file
$queueContent = file_get_contents($logFile);
if (empty($queueContent)) {
    echo "Queue file is empty.\n";
    exit(0);
}

// Get database connection
$pdo = getLogsDbConnection();
if (!$pdo) {
    echo "ERROR: Cannot connect to logs database.\n";
    exit(1);
}

// Process each line
$lines = explode(PHP_EOL, trim($queueContent));
$processed = 0;
$failed = 0;

foreach ($lines as $line) {
    if (empty(trim($line))) continue;
    
    try {
        $entry = json_decode($line, true);
        if (!$entry) {
            echo "WARNING: Invalid JSON line: " . substr($line, 0, 50) . "...\n";
            $failed++;
            continue;
        }
        
        // Insert into logs database
        $stmt = $pdo->prepare('
            INSERT INTO logs (timecode, url, ip, action, text, user_id)
            VALUES (:timecode, :url, :ip, :action, :text, :user_id)
        ');
        
        $text = $entry['user_name'] . ' hat ' . $entry['count'] . ' Log-Einträge der Aktion "' . $entry['deleted_action'] . '" gelöscht';
        
        $stmt->bindValue(':timecode', $entry['timestamp'], PDO::PARAM_STR);
        $stmt->bindValue(':url', '', PDO::PARAM_STR);
        $stmt->bindValue(':ip', $entry['ip'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':action', 'logs-deleted', PDO::PARAM_STR);
        $stmt->bindValue(':text', $text, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $entry['user_id'] ?? '', PDO::PARAM_STR);
        
        $stmt->execute();
        $processed++;
        
    } catch (Exception $e) {
        echo "ERROR processing entry: " . $e->getMessage() . "\n";
        $failed++;
    }
}

// Archive processed file
if ($processed > 0) {
    $archiveName = $processedFile . '.' . date('Y-m-d_H-i-s') . '.log';
    rename($logFile, $archiveName);
    echo "✓ Successfully processed $processed entries.\n";
    echo "✓ Queue file archived to: $archiveName\n";
} else {
    echo "No entries were processed.\n";
}

if ($failed > 0) {
    echo "⚠ $failed entries failed to process.\n";
}

// Cleanup old processed files (keep last 10)
$processedFiles = glob($processedFile . '.*.log');
if (count($processedFiles) > 10) {
    usort($processedFiles, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    $toDelete = array_slice($processedFiles, 0, count($processedFiles) - 10);
    foreach ($toDelete as $file) {
        unlink($file);
    }
    echo "✓ Cleaned up " . count($toDelete) . " old processed files.\n";
}

exit(0);
