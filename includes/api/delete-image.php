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

$userId = $_SESSION['user_id'];

// Check permissions
$vorstandCanEditUI = filter_var(getConfigValue('vorstand_can_edit_UI'), FILTER_VALIDATE_BOOLEAN);
$canEditUI = hasAdminRole($userId) || (hasVorstandRole($userId) && $vorstandCanEditUI);

if (!$canEditUI) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$imageId = $input['image_id'] ?? null;
$imageType = $input['image_type'] ?? '';

if (!$imageId || !$imageType) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Fehlende Parameter']);
    exit();
}

$validTypes = ['icons', 'logos', 'banner_images', 'gifs'];
if (!in_array($imageType, $validTypes)) {
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
    // Get the image data
    $stmt = $config->prepare("SELECT link FROM {$imageType} WHERE rowid = :id");
    $stmt->bindValue(':id', $imageId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $image = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$image) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bild nicht gefunden']);
        exit();
    }
    
    $fileName = $image['link'];
    
    // Determine file path based on type
    $pathMappings = [
        'icons' => __DIR__ . '/../../assets/icons/tabicons/',
        'logos' => __DIR__ . '/../../assets/icons/logos/',
        'banner_images' => __DIR__ . '/../../assets/images/banner/',
        'gifs' => __DIR__ . '/../../assets/images/gifs/'
    ];
    
    $filePath = $pathMappings[$imageType] . $fileName;
    
    // Delete from database first
    $stmt = $config->prepare("DELETE FROM {$imageType} WHERE rowid = :id");
    $stmt->bindValue(':id', $imageId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fehler beim Löschen aus der Datenbank']);
        exit();
    }
    
    // Delete file from server
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            error_log('Warning: Could not delete file: ' . $filePath);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Bild wurde gelöscht']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit();
?>
