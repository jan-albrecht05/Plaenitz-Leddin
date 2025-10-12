<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/db_helper.php';

// Validate login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

$pdo = getMemberDbConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_unavailable']);
    exit;
}

try {
    // Use current UTC timestamp
    $dt = new DateTime('now', new DateTimeZone('UTC'));
    $nowUtc = $dt->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare('UPDATE mitglieder SET last_viewed_notification = :ts WHERE id = :id');
    $stmt->bindValue(':ts', $nowUtc, PDO::PARAM_STR);
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['ok' => true, 'timestamp' => $nowUtc]);
} catch (Exception $e) {
    error_log('mark_notifications_read: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'update_failed']);
}
