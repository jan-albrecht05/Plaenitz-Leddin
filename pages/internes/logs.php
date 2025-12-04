<?php
    require_once '../../includes/session-config.php';
    startSecureSession();
    // Include database helper functions
    require_once '../../includes/db_helper.php';
    require_once '../../includes/log-data.php';
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    
    $userId = $_SESSION['user_id'];
    if (!hasAdminOrVorstandRole($userId)) {
        // User doesn't have required role, redirect to login
        session_destroy();
        header("Location: login.php?error=" . urlencode("Sie haben keine Berechtigung für diese Seite."));
        exit();
    }
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>logs | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../../assets/css/root.css">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/logs.css">
    <link rel="stylesheet" href="../../assets/css/heading.css">
    <link rel="stylesheet" href="../../assets/css/footer.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <script src="../../assets/js/mode.js" defer></script>
</head>
<body>
    <div id="heading" style="z-index: 10000;">
        <div id="left">
            <a href="../../index.php">
                <img src="../../assets/icons/logo.png" alt="">
            </a>
        </div>
    </div>
    <div id="main">
        <button id="back-button" class="center" onclick="window.location.href='admin.php'">
            <span class="material-symbols-outlined">arrow_back</span> Zurück zur Admin-Seite
        </button>
        <h1>System Logs</h1>
        <div id="logs-container">
            <?php
                $i=1;
                $pdo = getLogsDbConnection();
                if ($pdo) {
                    try {
                        $stmt = $pdo->query("SELECT * FROM logs ORDER BY timecode DESC LIMIT 100");
                        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($logs)) {
                            echo '<div class="log-entry info" style="padding: 20px; text-align: center;">';
                            echo '<p>Keine Logs vorhanden.</p>';
                            echo '</div>';
                        } else {
                            foreach ($logs as $log) {
                                echo '<div class="log-entry '.$log['action'].'" style="z-index: '.(1000-$i).';">
                                        <div class="spacer"></div>
                                        <div class="log-content">
                                            <div class="log-text">' . htmlspecialchars($log['text']) . '</div>
                                            <div class="log-bottom">
                                                <div class="log-timecode center">' . date('d.m.Y H:i:s', strtotime($log['timecode'])) . '</div>
                                                <div class="log-userid center"><span class="material-symbols-outlined">person</span> ' . htmlspecialchars(isset($log['user_id']) ? $log['user_id'] : '') . '</div>
                                                <div class="log-ip center"><span class="material-symbols-outlined">public</span> ' . htmlspecialchars($log['ip']) . '</div>
                                            </div>
                                        </div>
                                    </div>';
                                $i++;
                            }
                        }
                    } catch (PDOException $e) {
                        echo '<div class="log-entry error" style="padding: 20px;">';
                        echo "<strong>Error fetching logs:</strong> " . htmlspecialchars($e->getMessage());
                        echo '<br><br><strong>Debug Info:</strong><br>';
                        echo 'Database Path: ' . htmlspecialchars(__DIR__ . '/../../assets/db/logs.db');
                        echo '<br>File exists: ' . (file_exists(__DIR__ . '/../../assets/db/logs.db') ? 'Yes' : 'No');
                        echo '<br>File readable: ' . (is_readable(__DIR__ . '/../../assets/db/logs.db') ? 'Yes' : 'No');
                        echo '</div>';
                    }
                } else {
                    echo '<div class="log-entry error" style="padding: 20px;">';
                    echo "<strong>Unable to connect to the logs database.</strong>";
                    echo '<br><br><strong>Debug Info:</strong><br>';
                    $dbPath = __DIR__ . '/../../assets/db/logs.db';
                    echo 'Expected Database Path: ' . htmlspecialchars($dbPath);
                    echo '<br>File exists: ' . (file_exists($dbPath) ? 'Yes' : 'No');
                    echo '<br>File readable: ' . (is_readable($dbPath) ? 'Yes' : 'No');
                    if (file_exists($dbPath)) {
                        echo '<br>File permissions: ' . substr(sprintf('%o', fileperms($dbPath)), -4);
                    }
                    echo '</div>';
                }
            ?>
        </div>
    </div>
    <div id="footer" class="center">
        <?php include '../../pages/internes/footer.php'; ?>
    </div>
</body>
</html>