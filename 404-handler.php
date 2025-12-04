<?php
// Internal 404 handler - logs error and redirects to display page
require_once __DIR__ . '/includes/session-config.php';
startSecureSession();
require_once __DIR__ . '/includes/log-data.php';

// Get the original requested URL from query parameter (passed by .htaccess)
$requestedPage = $_GET['url'] ?? $_SERVER['REQUEST_URI'] ?? 'unknown';
$userId = $_SESSION['user_id'] ?? '';
$userName = $_SESSION['name'] ?? 'Gast';
$referer = $_SERVER['HTTP_REFERER'] ?? 'direkt';

// Log the 404 error
logAction(
    '', // empty = auto timestamp
    'error-404',
    $userName . ' hat versucht ' . $requestedPage . ' aufzurufen. (Referer: ' . $referer . ')',
    '', // empty = auto IP detection
    $userId
);

// Redirect to user-facing 404 page
// Use relative path that works both on XAMPP subdirectory and live server root
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
http_response_code(404);
header('Location: ' . $basePath . '/404.php');
exit();
