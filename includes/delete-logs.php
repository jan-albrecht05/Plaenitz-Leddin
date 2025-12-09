<?php
// Force clean JSON output (avoid notices/warnings in body)
ini_set('display_errors', '0');
while (ob_get_level() > 0) {
    ob_end_clean();
}

require_once __DIR__ . '/session-config.php';
startSecureSession();

require_once __DIR__ . '/db_helper.php';
require_once __DIR__ . '/log-data.php';

header('Content-Type: application/json; charset=utf-8');

$send = function (int $status, array $payload) {
    http_response_code($status);
    echo json_encode($payload);
    exit();
};

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $send(405, ['success' => false, 'message' => 'Method not allowed']);
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        $send(401, ['success' => false, 'message' => 'Session abgelaufen. Bitte neu anmelden.']);
    }
    if (!hasAdminRole($userId)) {
        $send(403, ['success' => false, 'message' => 'Nicht autorisiert']);
    }

    $action = $_POST['action'] ?? '';
    $logType = trim($_POST['log_type'] ?? '');

    if ($action !== 'delete_logs' || $logType === '') {
        $send(400, ['success' => false, 'message' => 'Ungültige Anfrage']);
    }

    $pdo = getLogsDbConnection();
    if (!$pdo) {
        $send(500, ['success' => false, 'message' => 'Log-Datenbank nicht verfügbar.']);
    }

    $pdo->exec('PRAGMA busy_timeout = 5000;');
    $pdo->exec('PRAGMA journal_mode = WAL;');

    $pdo->beginTransaction();

    $countStmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM logs WHERE action = :action');
    $countStmt->bindValue(':action', $logType, PDO::PARAM_STR);
    $countStmt->execute();
    $count = (int)$countStmt->fetchColumn();

    $deleteStmt = $pdo->prepare('DELETE FROM logs WHERE action = :action');
    $deleteStmt->bindValue(':action', $logType, PDO::PARAM_STR);
    $deleteStmt->execute();

    $pdo->commit();

    $send(200, [
        'success' => true,
        'deleted' => $count,
        'log_type' => $logType,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('delete-logs.php error: ' . $e->getMessage());
    $send(500, ['success' => false, 'message' => 'Fehler beim Löschen: ' . $e->getMessage()]);
}
