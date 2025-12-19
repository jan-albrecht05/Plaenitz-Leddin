<?php
// Test script to check database structure
ob_start();
require_once '../session-config.php';
startSecureSession();
require_once '../db_helper.php';
require_once '../config-helper.php';
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !hasAdminRole($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Keine Berechtigung']);
    exit();
}

global $config;

if ($config === null) {
    echo json_encode(['error' => 'Datenbankverbindung fehlgeschlagen']);
    exit();
}

$tables = ['icons', 'logos', 'banner_images', 'gifs', 'banner_texte', 'colors', 'messages', 'config'];
$result = [];

foreach ($tables as $table) {
    try {
        // Check if table exists
        $stmt = $config->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        $exists = $stmt->fetchArray(SQLITE3_ASSOC);
        
        if ($exists) {
            // Get table structure
            $stmt = $config->query("PRAGMA table_info($table)");
            $columns = [];
            while ($col = $stmt->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $col['name'] . ' (' . $col['type'] . ')';
            }
            
            // Get row count
            $stmt = $config->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetchArray(SQLITE3_ASSOC)['count'];
            
            $result[$table] = [
                'exists' => true,
                'columns' => $columns,
                'row_count' => $count
            ];
        } else {
            $result[$table] = ['exists' => false];
        }
    } catch (Exception $e) {
        $result[$table] = ['error' => $e->getMessage()];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>
