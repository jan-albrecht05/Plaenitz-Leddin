<?php
/**
 * API endpoint to save banner text to config.db
 */
require_once '../../../includes/session-config.php';
startSecureSession();

// Include database helper functions
require_once '../../../includes/db_helper.php';
require_once '../../../includes/config-helper.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit();
}

$userId = $_SESSION['user_id'];

// Check permissions
$vorstandCanEditUI = filter_var(getConfigValue('vorstand_can_edit_UI'), FILTER_VALIDATE_BOOLEAN);
$canEditUI = hasAdminRole($userId) || (hasVorstandRole($userId) && $vorstandCanEditUI);

if (!$canEditUI) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['banner_text'])) {
    echo json_encode(['success' => false, 'error' => 'Kein Banner-Text übermittelt']);
    exit();
}

$bannerText = trim($data['banner_text']);

try {
    // Get global $config connection from config-helper.php
    global $config;
    
    // Check if banner_text entry exists in config table
    $stmt = $config->prepare('SELECT COUNT(*) as count FROM config WHERE name = :name');
    $stmt->bindValue(':name', 'banner_text', SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row['count'] > 0) {
        // Update existing entry
        $stmt = $config->prepare('UPDATE config SET inhalt = :inhalt WHERE name = :name');
        $stmt->bindValue(':inhalt', $bannerText, SQLITE3_TEXT);
        $stmt->bindValue(':name', 'banner_text', SQLITE3_TEXT);
        $stmt->execute();
    } else {
        // Insert new entry
        $stmt = $config->prepare('INSERT INTO config (name, inhalt) VALUES (:name, :inhalt)');
        $stmt->bindValue(':name', 'banner_text', SQLITE3_TEXT);
        $stmt->bindValue(':inhalt', $bannerText, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    // Also add to banner_texte table for history (if table exists)
    try {
        $stmt = $config->prepare('INSERT INTO banner_texte (inhalt, datum) VALUES (:inhalt, :datum)');
        $stmt->bindValue(':inhalt', $bannerText, SQLITE3_TEXT);
        $stmt->bindValue(':datum', gmdate('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->execute();
    } catch (Exception $e) {
        // Table might not exist, ignore
        error_log('banner_texte table insert failed (table might not exist): ' . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Banner-Text erfolgreich gespeichert',
        'reload_list' => true
    ]);
    
} catch (Exception $e) {
    error_log('save-banner-text.php: Error - ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
