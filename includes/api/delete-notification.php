<?php
    require_once '../session-config.php';
    startSecureSession();

    require_once '../db_helper.php';
    require_once '../config-helper.php';
    require_once '../log-data.php';

    ob_start();
    header('Content-Type: application/json');

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
        http_response_code(401);
        exit();
    }

    $userId = $_SESSION['user_id'];

    // Check permissions
    $input = json_decode(file_get_contents('php://input'), true);
    $messageType = $input['message_type'] ?? null;

    // Admin always allowed, Vorstand needs specific permission
    if (!hasAdminRole($userId)) {
        if (!hasVorstandRole($userId)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
            http_response_code(403);
            exit();
        }
        
        // Check if Vorstand is allowed to edit config
        $vorstandCanEdit = filter_var(getConfigValue('vorstand_can_edit_config'), FILTER_VALIDATE_BOOLEAN);
        if (!$vorstandCanEdit) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Keine Berechtigung zur Bearbeitung']);
            http_response_code(403);
            exit();
        }
    }

    // Validate input
    if (!isset($input['message_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Fehlende Parameter']);
        http_response_code(400);
        exit();
    }

    $messageId = intval($input['message_id']);

    if ($messageId <= 0) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Ungültige Nachrichts-ID']);
        http_response_code(400);
        exit();
    }

    try {
        // Delete message from database
        $result = deleteMessage($messageId);
        
        if ($result) {
            // Log the action
            $action = 'message_deleted_' . $messageType;
            logAction('', $action, 'ID: ' . $messageId, '', $userId);
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Nachricht gelöscht']);
            http_response_code(200);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Fehler beim Löschen']);
            http_response_code(500);
        }
    } catch (Exception $e) {
        error_log('Notification delete error: ' . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
        http_response_code(500);
    }

    ob_end_flush();
?>
