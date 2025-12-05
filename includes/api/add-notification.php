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

    // Check permissions based on message type
    $input = json_decode(file_get_contents('php://input'), true);
    $messageType = $input['typ'] ?? null;

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
    if (!isset($input['typ']) || !isset($input['heading']) || !isset($input['text']) || 
        !isset($input['startzeit']) || !isset($input['endzeit'])) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Fehlende Parameter']);
        http_response_code(400);
        exit();
    }

    $typ = trim($input['typ']);
    $heading = trim($input['heading']);
    $text = trim($input['text']);
    $startzeit = trim($input['startzeit']);
    $endzeit = trim($input['endzeit']);

    // Validate message type
    if (!in_array($typ, ['notification', 'maintenance'])) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Ungültiger Nachrichtentyp']);
        http_response_code(400);
        exit();
    }

    if (empty($heading) || empty($text)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Überschrift und Text sind erforderlich']);
        http_response_code(400);
        exit();
    }

    // Validate dates
    if (!strtotime($startzeit) || !strtotime($endzeit)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Ungültige Zeitwerte']);
        http_response_code(400);
        exit();
    }

    try {
        // Add message to database
        $result = addMessage($typ, $heading, $text, $startzeit, $endzeit, $_SESSION['user_id']);
        
        if ($result) {
            // Log the action
            $action = 'message_created_' . $typ;
            logAction('', $action, $heading, '', $userId);
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Nachricht erstellt']);
            http_response_code(200);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Fehler beim Erstellen']);
            http_response_code(500);
        }
    } catch (Exception $e) {
        error_log('Notification create error: ' . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
        http_response_code(500);
    }

    ob_end_flush();
?>
