<?php
session_start();
require_once __DIR__ . '/includes/log-data.php';

// Log server-side (synchronous)
$requestedPage = $_SERVER['REQUEST_URI'] ?? 'unknown';
$userId = $_SESSION['user_id'] ?? '';
$userName = $_SESSION['name'] ?? 'Gast';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

logAction(
    date('Y-m-d H:i:s'),
    'error-404',
    $userName . ' hat versucht ' . $requestedPage . ' aufzurufen.',
    $ip,
    $userId
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/root.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/error.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <script src="assets/js/mode.js" defer></script>
    <title>Error 404 | Plänitz-Leddin</title>
</head>
<body>
    <div id="main" class="center">
        <div class="box">
            <div id="img">
                <img src="assets/icons/404.webp" alt="Error 404">
            </div>
            <div id="text">
                <h1>Error <span class="contrast">404</span></h1>
                <h3>Diese Seite existiert leider nicht.</h3>
                <p>Die von Ihnen angeforderte Seite konnte nicht gefunden werden. Möglicherweise wurde sie entfernt, der Name wurde geändert oder sie ist vorübergehend nicht verfügbar.</p>
                <a href="index.php" class="button center">Zur Startseite</a>
            </div>
        </div>
    </div>
    <form id="hidden-form">
        <input type="hidden" name="last-page" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
    </form>
    <script>
        // Optional: Send additional AJAX log for client-side tracking
        (function() {
            const requestedPage = <?php echo json_encode($requestedPage); ?>;
            const userId = <?php echo json_encode($userId); ?>;
            const userName = <?php echo json_encode($userName); ?>;
            
            // Send AJAX log (asynchronous, non-blocking)
            fetch('/includes/log-data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    timecode: new Date().toISOString().slice(0, 19).replace('T', ' '),
                    action: 'error-404AJAX',
                    text: userName + ' (client) hat versucht ' + requestedPage + ' aufzurufen.',
                    user_id: userId || ''
                })
            }).catch(err => console.warn('404 AJAX log failed:', err));
        })();
    </script>
    <div id="footer" class="center">
        <div id="mode-toggle">
            <span class="material-symbols-outlined">light_mode</span>
            <label class="switch">
                <input type="checkbox" id="toggle-checkbox">
                <span class="slider round"></span>
            </label>
            <span class="material-symbols-outlined">dark_mode</span>
        </div>
    </div>
</body>
</html>