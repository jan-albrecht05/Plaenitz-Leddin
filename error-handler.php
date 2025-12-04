<?php
// Generic error handler - logs error and redirects to display page
require_once __DIR__ . '/includes/session-config.php';
startSecureSession();
require_once __DIR__ . '/includes/log-data.php';

// Get error details
$errorCode = $_GET['code'] ?? '500';
$requestedPage = $_GET['url'] ?? $_SERVER['REQUEST_URI'] ?? 'unknown';
$userId = $_SESSION['user_id'] ?? '';
$userName = $_SESSION['name'] ?? 'Gast';
$referer = $_SERVER['HTTP_REFERER'] ?? 'direkt';

// Map error codes to log actions and messages
$errorMap = [
    '400' => [
        'action' => 'error-400',
        'log' => 'Bad Request',
        'title' => 'Ungültige Anfrage',
        'message' => 'Die Anfrage konnte nicht verarbeitet werden. Bitte überprüfen Sie Ihre Eingabe.'
    ],
    '401' => [
        'action' => 'error-401',
        'log' => 'Unauthorized',
        'title' => 'Keine Berechtigung',
        'message' => 'Sie müssen sich anmelden, um auf diese Seite zuzugreifen.'
    ],
    '403' => [
        'action' => 'error-403',
        'log' => 'Forbidden',
        'title' => 'Zugriff verweigert',
        'message' => 'Sie haben keine Berechtigung, auf diese Ressource zuzugreifen.'
    ],
    '404' => [
        'action' => 'error-404',
        'log' => 'Not Found',
        'title' => 'Seite nicht gefunden',
        'message' => 'Die von Ihnen angeforderte Seite konnte nicht gefunden werden. Möglicherweise wurde sie entfernt, der Name wurde geändert oder sie ist vorübergehend nicht verfügbar.'
    ],
    '500' => [
        'action' => 'error-500',
        'log' => 'Internal Server Error',
        'title' => 'Interner Serverfehler',
        'message' => 'Es ist ein Fehler auf dem Server aufgetreten. Bitte versuchen Sie es später erneut.'
    ],
    '502' => [
        'action' => 'error-502',
        'log' => 'Bad Gateway',
        'title' => 'Gateway-Fehler',
        'message' => 'Der Server hat eine ungültige Antwort erhalten. Bitte versuchen Sie es später erneut.'
    ],
    '503' => [
        'action' => 'error-503',
        'log' => 'Service Unavailable',
        'title' => 'Dienst nicht verfügbar',
        'message' => 'Der Server ist vorübergehend nicht verfügbar. Bitte versuchen Sie es später erneut.'
    ]
];

// Get error info or use default
$errorInfo = $errorMap[$errorCode] ?? $errorMap['500'];

// Log the error
logAction(
    '', // empty = auto timestamp
    $errorInfo['action'],
    $userName . ' hat Fehler ' . $errorCode . ' (' . $errorInfo['log'] . ') bei ' . $requestedPage . ' erhalten. (Referer: ' . $referer . ')',
    '', // empty = auto IP detection
    $userId
);

// Redirect to user-facing error page with parameters
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
http_response_code((int)$errorCode);
header('Location: ' . $basePath . '/error.php?code=' . urlencode($errorCode) . '&title=' . urlencode($errorInfo['title']) . '&message=' . urlencode($errorInfo['message']));
exit();
