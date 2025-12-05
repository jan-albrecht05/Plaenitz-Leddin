<?php
    require_once '../session-config.php';
    startSecureSession();

    require_once '../db_helper.php';
    require_once '../config-helper.php';
    require_once '../log-data.php';

    // Start output buffering immediately
    ob_start();
    
    // Ensure clean output
    while (ob_get_level() > 1) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');

    // Check if user is logged in and is admin
    if (!isset($_SESSION['user_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
        http_response_code(401);
        exit();
    }

    $userId = $_SESSION['user_id'];

    // Get request data first to check which key is being changed
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['config_key']) || !isset($input['config_value'])) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Fehlende Parameter']);
        http_response_code(400);
        exit();
    }

    $configKey = trim($input['config_key']);
    $configValue = trim($input['config_value']);

    // Validate config key (whitelist of allowed keys)
    $allowedKeys = [
        'banner_text',
        'show_error',
        'show_notification',
        'show_gif',
        'auto_rotate_gif',
        'vorstand_can_edit_UI',
        'vorstand_can_edit_config',
        'primary_color'
    ];

    if (!in_array($configKey, $allowedKeys)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Ungültiger Config-Key']);
        http_response_code(400);
        exit();
    }

    // UI-related keys that Vorstand can edit if they have permission
    $uiKeys = ['banner_text', 'show_gif', 'auto_rotate_gif'];
    
    // Check permissions based on key type
    $isAdmin = hasAdminRole($userId);
    $isVorstand = hasVorstandRole($userId);
    $vorstandCanEditUI = filter_var(getConfigValue('vorstand_can_edit_UI'), FILTER_VALIDATE_BOOLEAN);
    
    // Admin-only keys
    $adminOnlyKeys = ['show_error', 'show_notification', 'vorstand_can_edit_UI', 'vorstand_can_edit_config', 'primary_color'];
    
    // Determine if user has permission for this specific key
    $hasPermission = false;
    
    if ($isAdmin) {
        $hasPermission = true; // Admins can change everything
    } elseif ($isVorstand && $vorstandCanEditUI && in_array($configKey, $uiKeys)) {
        $hasPermission = true; // Vorstand can change UI keys if allowed
    }
    
    if (!$hasPermission) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diese Einstellung']);
        http_response_code(403);
        exit();
    }

    try {
        // Set the config value
        $result = setConfigValue($configKey, $configValue);
        
        // Log for debugging
        error_log("setConfigValue called for key '$configKey' with value '$configValue', result: " . ($result ? 'true' : 'false'));
        
        if ($result) {
            // Log the action
            $action = 'config_changed_' . $configKey;
            logAction('', $action, "Wert: {$configValue}", '', $userId);
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Einstellung gespeichert']);
            http_response_code(200);
        } else {
            error_log("setConfigValue returned false for key '$configKey'");
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern in die Datenbank']);
            http_response_code(500);
        }
    } catch (Exception $e) {
        error_log('Config save error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
        http_response_code(500);
    }

    ob_end_flush();
?>
