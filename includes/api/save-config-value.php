<?php
// Prevent any output before JSON
ob_start();

require_once '../session-config.php';
startSecureSession();

require_once '../db_helper.php';
require_once '../config-helper.php';

// Clear any output from includes
ob_end_clean();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht autorisiert']);
    exit();
}

$userId = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['config_key']) || !isset($input['config_value'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Fehlende Parameter']);
    exit();
}

$configKey = $input['config_key'];
$configValue = $input['config_value'];

// Check permissions based on config key
$vorstandCanEditUI = filter_var(getConfigValue('vorstand_can_edit_UI'), FILTER_VALIDATE_BOOLEAN);
$vorstandCanEditConfig = filter_var(getConfigValue('vorstand_can_edit_config'), FILTER_VALIDATE_BOOLEAN);

$isAdmin = hasAdminRole($userId);
$isVorstand = hasVorstandRole($userId);

// Define which keys require which permissions
$adminOnlyKeys = ['vorstand_can_edit_UI', 'vorstand_can_edit_config', 'show_error', 'show_notification', 'system_version'];
$uiKeys = ['banner_text', 'primary_color', 'show_gif', 'auto_rotate_gif'];
$configKeys = ['show_notification'];

// Check permission
$hasPermission = false;

if ($isAdmin) {
    $hasPermission = true;
} elseif ($isVorstand) {
    if (in_array($configKey, $adminOnlyKeys)) {
        $hasPermission = false;
    } elseif (in_array($configKey, $uiKeys) && $vorstandCanEditUI) {
        $hasPermission = true;
    } elseif (in_array($configKey, $configKeys) && $vorstandCanEditConfig) {
        $hasPermission = true;
    }
}

if (!$hasPermission) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit();
}

// Save config value
try {
    $success = setConfigValue($configKey, $configValue);
    
    if ($success) {
        // Special handling for banner_text - add to history
        if ($configKey === 'banner_text' && !empty($configValue)) {
            global $config;
            $stmt = $config->prepare('INSERT INTO banner_texte (inhalt, datum) VALUES (:inhalt, :datum)');
            $stmt->bindValue(':inhalt', $configValue, SQLITE3_TEXT);
            $stmt->bindValue(':datum', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->execute();
        }
        
        // Special handling for primary_color - add to history
        if ($configKey === 'primary_color' && !empty($configValue)) {
            addColorToHistory($configValue);
        }
        
        echo json_encode(['success' => true, 'message' => 'Wert gespeichert']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
