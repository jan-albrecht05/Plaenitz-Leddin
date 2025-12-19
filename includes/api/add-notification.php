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

// Check permissions
$vorstandCanEditConfig = filter_var(getConfigValue('vorstand_can_edit_config'), FILTER_VALIDATE_BOOLEAN);
$isAdmin = hasAdminRole($userId);
$isVorstand = hasVorstandRole($userId);

$canEdit = $isAdmin || ($isVorstand && $vorstandCanEditConfig);

if (!$canEdit) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$typ = $input['typ'] ?? '';
$heading = $input['heading'] ?? '';
$text = $input['text'] ?? '';
$startzeit = $input['startzeit'] ?? '';
$endzeit = $input['endzeit'] ?? '';

// Validate input
if (empty($typ) || empty($heading) || empty($text) || empty($startzeit) || empty($endzeit)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Fehlende Parameter']);
    exit();
}

// Only admins can create maintenance messages
if ($typ === 'maintenance' && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Nur Admins können Wartungsmitteilungen erstellen']);
    exit();
}

// Validate type
if (!in_array($typ, ['notification', 'maintenance'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültiger Typ']);
    exit();
}

// Add message
try {
    $success = addMessage($typ, $heading, $text, $startzeit, $endzeit, $userId);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Nachricht erstellt']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fehler beim Erstellen']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
