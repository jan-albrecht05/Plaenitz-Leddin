<?php
ob_start();
require_once '../session-config.php';
startSecureSession();
require_once '../db_helper.php';
require_once '../config-helper.php';
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht autorisiert']);
    exit();
}

$type = $_GET['type'] ?? '';

$validTypes = ['icons', 'logos', 'banner_images', 'gifs'];
if (!in_array($type, $validTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültiger Typ']);
    exit();
}

global $config;
if ($config === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankverbindung fehlgeschlagen']);
    exit();
}

try {
    $stmt = $config->prepare("SELECT rowid as id, * FROM {$type} ORDER BY datum DESC");
    $result = $stmt->execute();
    
    $images = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $images[] = $row;
    }
    
    echo json_encode(['success' => true, 'images' => $images]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit();
?>
