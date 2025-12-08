<?php
require_once '../../includes/session-config.php';
startSecureSession();

// Include database helper functions
require_once '../../includes/db_helper.php';
require_once '../../includes/log-data.php';
require_once '../../includes/ip-blocker.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=admin.php");
    exit();
}

$userId = $_SESSION['user_id'];
if (!hasAdminRole($userId)) {
    header("Location: dashboard.php");
    exit();
}

// Get user name for logging
$userName = $_SESSION['name'] ?? 'Admin';

// Get log database connection
$pdo = getLogsDbConnection();
if (!$pdo) {
    die('Fehler: Log-Datenbank nicht verfügbar.');
}

// Handle filters
$filterAction = $_GET['action'] ?? 'all';
$filterDays = (int)($_GET['days'] ?? 7);
$filterUser = $_GET['user_id'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Build WHERE clause
$where = [];
$params = [];

if ($filterAction !== 'all') {
    $where[] = "action = :action";
    $params[':action'] = $filterAction;
}

if ($filterDays > 0) {
    $where[] = "datetime(timecode) >= datetime('now', '-{$filterDays} days')";
}

if ($filterUser !== '') {
    $where[] = "user_id = :user_id";
    $params[':user_id'] = $filterUser;
}

if ($searchTerm !== '') {
    $where[] = "(text LIKE :search OR ip LIKE :search)";
    $params[':search'] = '%' . $searchTerm . '%';
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN action = 'login-success' THEN 1 END) as logins,
        COUNT(CASE WHEN action = 'login-failed' THEN 1 END) as failed_logins,
        COUNT(CASE WHEN action = 'error-404' THEN 1 END) as errors_404,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT ip) as unique_ips
    FROM logs
    WHERE datetime(timecode) >= datetime('now', '-{$filterDays} days')
";
$stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

// Get active sessions (users with successful login in the last 30 minutes)
$activeSessionsQuery = "
    SELECT COUNT(DISTINCT user_id) as active_sessions
    FROM logs
    WHERE action = 'login-success'
    AND user_id IS NOT NULL
    AND user_id != ''
    AND datetime(timecode) >= datetime('now', '-30 minutes')
";
$activeSessions = $pdo->query($activeSessionsQuery)->fetch(PDO::FETCH_ASSOC)['active_sessions'] ?? 0;

// Get active sessions (users with successful login in the last 30 minutes)
$activeSessionsQuery = "
    SELECT COUNT(DISTINCT user_id) as active_sessions
    FROM logs
    WHERE action = 'login-success'
    AND user_id IS NOT NULL
    AND user_id != ''
    AND datetime(timecode) >= datetime('now', '-30 minutes')
";
$activeSessions = $pdo->query($activeSessionsQuery)->fetch(PDO::FETCH_ASSOC)['active_sessions'] ?? 0;

// Get action breakdown
$actionQuery = "
    SELECT action, COUNT(*) as count
    FROM logs
    WHERE datetime(timecode) >= datetime('now', '-{$filterDays} days')
    GROUP BY action
    ORDER BY count DESC
    LIMIT 10
";
$actionStats = $pdo->query($actionQuery)->fetchAll(PDO::FETCH_ASSOC);

// Get 404 errors grouped
$error404Query = "
    SELECT 
        SUBSTR(text, INSTR(text, 'versucht ') + 9, INSTR(text, ' aufzurufen') - INSTR(text, 'versucht ') - 9) as url,
        COUNT(*) as count,
        MAX(timecode) as last_seen
    FROM logs
    WHERE action LIKE 'error-404%'
    AND datetime(timecode) >= datetime('now', '-{$filterDays} days')
    GROUP BY url
    ORDER BY count DESC
    LIMIT 20
";
$error404Stats = $pdo->query($error404Query)->fetchAll(PDO::FETCH_ASSOC);

// Get recent logs
$logsQuery = "SELECT * FROM logs {$whereClause} ORDER BY timecode DESC LIMIT 100";
$stmt = $pdo->prepare($logsQuery);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for filter
$memberPdo = getMemberDbConnection();
$users = [];
$allMembers = [];
if ($memberPdo) {
    $users = $memberPdo->query("SELECT id, name, nachname FROM mitglieder ORDER BY nachname")->fetchAll(PDO::FETCH_ASSOC);
    // Get all members with full details for management section
    $allMembers = $memberPdo->query("SELECT * FROM mitglieder ORDER BY nachname, name")->fetchAll(PDO::FETCH_ASSOC);
    // count the number of users
    $usersCount = count($users);
}

// Handle member context menu actions (same as dashboard.php but without restrictions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'], $_POST['action'])) {
    $currentUserId = $_SESSION['user_id'] ?? null;
    $targetId = (int)$_POST['member_id'];
    $action = (string)$_POST['action'];

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

    $respond = function($success, $message) use ($isAjax) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => (bool)$success, 'message' => $message]);
            exit();
        } else {
            if ($success) header('Location: admin.php?success=' . urlencode($message));
            else header('Location: admin.php?error=' . urlencode($message));
            exit();
        }
    };

    if (!$currentUserId || $targetId <= 0) {
        $respond(false, 'Ungültige Anfrage.');
    }

    $pdo = getMemberDbConnection();
    if (!$pdo) {
        error_log('admin: Mitgliederdatenbank nicht erreichbar.');
        $respond(false, 'Datenbankfehler.');
    }

    try {
        if ($action === 'delete') {
            if ($targetId === $currentUserId) throw new Exception('Sie können sich nicht selbst löschen.');
            $stmt = $pdo->prepare('DELETE FROM mitglieder WHERE id = :id');
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $respond(true, 'Benutzer gelöscht.');
        }

        if ($action === 'promote') {
            $tempPassword = $_POST['temp_password'] ?? null;
            if (empty($tempPassword)) {
                $respond(false, 'Bitte geben Sie ein vorläufiges Passwort ein.');
            }
            
            if (strlen($tempPassword) < 8) {
                $respond(false, 'Das Passwort muss mindestens 8 Zeichen lang sein.');
            }
            
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('UPDATE mitglieder SET rolle = :rolle WHERE id = :id');
                $stmt->bindValue(':rolle', 'vorstand', PDO::PARAM_STR);
                $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
                $stmt->execute();
                
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE mitglieder SET password = :password, last_visited_date = NULL WHERE id = :id');
                $stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
                $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
                $stmt->execute();
                
                $pdo->commit();
                $respond(true, 'Benutzer zum Vorstand gemacht und Passwort gesetzt.');
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        if ($action === 'demote') {
            $stmt = $pdo->prepare('UPDATE mitglieder SET rolle = :rolle, last_visited_date = NULL WHERE id = :id');
            $stmt->bindValue(':rolle', 'mitglied', PDO::PARAM_STR);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $respond(true, 'Benutzer gedemoted.');
        }

        if ($action === 'activate' || $action === 'deactivate') {
            $newStatus = $action === 'activate' ? 1 : 2;
            $stmt = $pdo->prepare('UPDATE mitglieder SET status = :status WHERE id = :id');
            $stmt->bindValue(':status', $newStatus, PDO::PARAM_INT);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $msg = $action === 'activate' ? 'Benutzer aktiviert.' : 'Benutzer deaktiviert.';
            $respond(true, $msg);
        }

        if ($action === 'make_admin') {
            $stmt = $pdo->prepare('UPDATE mitglieder SET rolle = :rolle WHERE id = :id');
            $stmt->bindValue(':rolle', 'admin', PDO::PARAM_STR);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $respond(true, 'Benutzer zum Admin gemacht.');
        }

        if ($action === 'remove_admin') {
            $stmt = $pdo->prepare('UPDATE mitglieder SET rolle = :rolle, last_visited_date = NULL WHERE id = :id');
            $stmt->bindValue(':rolle', 'mitglied', PDO::PARAM_STR);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $respond(true, 'Admin-Rolle entfernt.');
        }

        if ($action === 'edit-member') {
            $requiredFields = ['titel', 'name', 'nachname', 'strasse', 'hausnummer', 'plz', 'ort', 'e_mail'];
            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field])) {
                    $respond(false, "Feld '$field' fehlt.");
                }
            }

            $stmt = $pdo->prepare('UPDATE mitglieder SET titel = :titel, name = :name, nachname = :nachname, strasse = :strasse, hausnummer = :hausnummer, adresszusatz = :adresszusatz, plz = :plz, ort = :ort, festnetz = :festnetz, mobilnummer = :mobilnummer, e_mail = :e_mail WHERE id = :id');
            $stmt->bindValue(':titel', $_POST['titel'], PDO::PARAM_STR);
            $stmt->bindValue(':name', $_POST['name'], PDO::PARAM_STR);
            $stmt->bindValue(':nachname', $_POST['nachname'], PDO::PARAM_STR);
            $stmt->bindValue(':strasse', $_POST['strasse'], PDO::PARAM_STR);
            $stmt->bindValue(':hausnummer', $_POST['hausnummer'], PDO::PARAM_STR);
            $stmt->bindValue(':adresszusatz', $_POST['adresszusatz'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':plz', $_POST['plz'], PDO::PARAM_STR);
            $stmt->bindValue(':ort', $_POST['ort'], PDO::PARAM_STR);
            $stmt->bindValue(':festnetz', $_POST['festnetz'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':mobilnummer', $_POST['mobilnummer'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':e_mail', $_POST['e_mail'], PDO::PARAM_STR);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $respond(true, 'Mitglied erfolgreich bearbeitet.');
        }

        $respond(false, 'Unbekannte Aktion.');

    } catch (Exception $e) {
        error_log('admin action error: ' . $e->getMessage());
        $respond(false, $e->getMessage());
    }
}

// Handle IP blocking/unblocking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ip_action'], $_POST['ip_address'])) {
    $ipAction = $_POST['ip_action'];
    $ipAddress = $_POST['ip_address'];
    
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    
    $respond = function($success, $message) use ($isAjax) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => (bool)$success, 'message' => $message]);
            exit();
        } else {
            if ($success) header('Location: admin.php?success=' . urlencode($message));
            else header('Location: admin.php?error=' . urlencode($message));
            exit();
        }
    };
    
    try {
        if ($ipAction === 'block') {
            $result = blockIP($ipAddress);
            if ($result) {
                logAction('', 'ip-blocked', $userName . ' hat IP ' . $ipAddress . ' blockiert', '', $userId);
                $respond(true, 'IP-Adresse erfolgreich blockiert: ' . $ipAddress);
            } else {
                $respond(false, 'Fehler beim Blockieren der IP-Adresse.');
            }
        } elseif ($ipAction === 'unblock') {
            $result = unblockIP($ipAddress);
            if ($result) {
                logAction('', 'ip-unblocked', $userName . ' hat IP ' . $ipAddress . ' entblockt', '', $userId);
                $respond(true, 'IP-Adresse erfolgreich entblockt: ' . $ipAddress);
            } else {
                $respond(false, 'Fehler beim Entblocken der IP-Adresse.');
            }
        } else {
            $respond(false, 'Unbekannte IP-Aktion.');
        }
    } catch (Exception $e) {
        error_log('IP blocker error: ' . $e->getMessage());
        $respond(false, $e->getMessage());
    }
}

// Handle log deletion by action type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_logs_action'])) {
    $actionToDelete = $_POST['delete_logs_action'];
    
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    
    try {
        $logPdo = getLogsDbConnection();
        if (!$logPdo) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Log-Datenbank nicht verfügbar.', 'count' => 0]);
            } else {
                header('Location: admin.php?error=' . urlencode('Log-Datenbank nicht verfügbar.'));
            }
            exit();
        }
        
        // Count before deletion
        $countStmt = $logPdo->prepare('SELECT COUNT(*) as count FROM logs WHERE action = :action');
        $countStmt->bindValue(':action', $actionToDelete, PDO::PARAM_STR);
        $countStmt->execute();
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Delete logs
        $deleteStmt = $logPdo->prepare('DELETE FROM logs WHERE action = :action');
        $deleteStmt->bindValue(':action', $actionToDelete, PDO::PARAM_STR);
        $deleteStmt->execute();
        
        // Close immediately
        $logPdo = null;
        
        // Send response FIRST
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => $count . ' Log-Einträge erfolgreich gelöscht.', 'count' => $count]);
        } else {
            header('Location: admin.php?success=' . urlencode($count . ' Log-Einträge erfolgreich gelöscht.'));
        }
        
        // Flush response to client immediately
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level() > 0) ob_end_flush();
            flush();
        }
        
        // NOW log to file in background (non-blocking, no DB lock)
        try {
            $logDir = __DIR__ . '/../../assets/db';
            $logFile = $logDir . '/deletion-queue.log';
            $timestamp = date('Y-m-d H:i:s');
            $logEntry = json_encode([
                'timestamp' => $timestamp,
                'user_id' => $userId,
                'user_name' => $userName,
                'action' => 'logs-deleted',
                'deleted_action' => $actionToDelete,
                'count' => $count,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]) . PHP_EOL;
            
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            
            // Trigger background processing after 5 seconds (non-blocking)
            $phpPath = 'php'; // Adjust if needed (e.g., 'C:\xampp\php\php.exe')
            $scriptPath = realpath(__DIR__ . '/../../includes/process-deletion-queue.php');
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows: Use START command to run in background
                $command = 'start /B timeout /T 5 /NOBREAK >nul 2>&1 && ' . $phpPath . ' "' . $scriptPath . '" >nul 2>&1';
                pclose(popen($command, 'r'));
            } else {
                // Linux/Unix: Use nohup and sleep
                $command = '(sleep 5 && ' . $phpPath . ' "' . $scriptPath . '" > /dev/null 2>&1 &) &';
                exec($command);
            }
        } catch (Exception $logError) {
            // Silent fail - user already got success response
            error_log('Background logging failed: ' . $logError->getMessage());
        }
        
        exit();
        
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $e->getMessage(), 'count' => 0]);
        } else {
            header('Location: admin.php?error=' . urlencode($e->getMessage()));
        }
        exit();
    }
}

// Get list of blocked IPs
$blockedIPs = getBlockedIPs();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log-Analyse | Plänitz-Leddin</title>
        <link rel="stylesheet" href="../../assets/css/root.css">
        <link rel="stylesheet" href="../../assets/css/main.css">
        <link rel="stylesheet" href="../../assets/css/heading.css">
        <link rel="stylesheet" href="../../assets/css/footer.css">
        <link rel="stylesheet" href="../../assets/css/admin.css">
        <link rel="stylesheet" href="../../assets/css/dashboard.css">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
        <script src="../../assets/js/dashboard.js" defer></script>
        <script src="../../assets/js/block-IP.js" defer></script>
</head>
<body>
    <div id="heading">
        <div id="left">
            <a href="../../index.php">
                <img src="../../assets/icons/logo.png" alt="">
            </a>
        </div>
        <div id="right">
            <a href="logout.php" id="logout-button">
                <span class="material-symbols-outlined">logout</span>
            </a>
        </div>
    </div>
    <div id="main" class="admin-dashboard">
        <h1><span class="material-symbols-outlined">analytics</span> Log-Analyse Dashboard</h1>
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>alle Mitglieder</h3>
                <div class="value"><?php echo number_format($usersCount); ?></div>
            </div>
            <div class="stat-card">
                <h3>Aktive Sessions</h3>
                <div class="value"><?php echo number_format($activeSessions); ?></div>
                <small style="font-size: 0.75rem; color: var(--text-secondary);">letzte 30 Min.</small>
            </div>
            <div class="stat-card">
                <h3>Gesamt-Einträge</h3>
                <div class="value"><?php echo number_format($stats['total']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Erfolgreiche Logins</h3>
                <div class="value"><?php echo number_format($stats['logins']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Fehlgeschlagene Logins</h3>
                <div class="value"><?php echo number_format($stats['failed_logins']); ?></div>
            </div>
            <div class="stat-card">
                <h3>404-Fehler</h3>
                <div class="value"><?php echo number_format($stats['errors_404']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Unique Users</h3>
                <div class="value"><?php echo number_format($stats['unique_users']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Unique IPs</h3>
                <div class="value"><?php echo number_format($stats['unique_ips']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Zeitraum</h3>
                <div class="value"><?php echo $filterDays === 0 ? 'Alle Zeit' : "Letzte {$filterDays} Tage"; ?></div>
            </div>
        </div>
        
        <!-- Top Actions -->
        <div class="section">
            <h2><span class="material-symbols-outlined">bar_chart</span> Top Aktionen</h2>
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Aktion</th>
                        <th>Anzahl</th>
                        <th>Anteil</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actionStats as $action): 
                        $percentage = ($stats['total'] > 0) ? round(($action['count'] / $stats['total']) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td data-label="Aktion"><span class="action-badge action-<?php echo htmlspecialchars($action['action']); ?>"><?php echo htmlspecialchars($action['action']); ?></span></td>
                        <td data-label="Anzahl"><?php echo number_format($action['count']); ?></td>
                        <td data-label="Anteil"><?php echo $percentage; ?>%</td>
                        <td data-label="Aktionen">
                            <button class="del-log center" onclick="deleteLogsByAction('<?php echo htmlspecialchars($action['action'], ENT_QUOTES); ?>', <?php echo $action['count']; ?>)" 
                                    title="Alle Logs dieser Aktion löschen">
                                <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                Löschen
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <hr>
        <!-- 404 Errors -->
        <?php if (count($error404Stats) > 0): ?>
        <div class="section">
            <h2><span class="material-symbols-outlined">link_off</span> Häufigste 404-Fehler</h2>
            <ul class="error-list">
                <?php foreach ($error404Stats as $error): ?>
                <li class="error-item">
                    <div>
                        <div class="error-url"><?php echo htmlspecialchars($error['url']); ?></div>
                        <small>Zuletzt: <?php echo htmlspecialchars($error['last_seen']); ?></small>
                    </div>
                    <span class="error-count"><?php echo $error['count']; ?>×</span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="admin.php">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Aktion</label>
                        <select name="action">
                            <option value="all" <?php echo $filterAction === 'all' ? 'selected' : ''; ?>>Alle</option>
                            <option value="login-success" <?php echo $filterAction === 'login-success' ? 'selected' : ''; ?>>Login (erfolgreich)</option>
                            <option value="login-failed" <?php echo $filterAction === 'login-failed' ? 'selected' : ''; ?>>Login (fehlgeschlagen)</option>
                            <option value="error-404" <?php echo $filterAction === 'error-404' ? 'selected' : ''; ?>>404-Fehler</option>
                            <option value="delete" <?php echo $filterAction === 'delete' ? 'selected' : ''; ?>>Löschen</option>
                            <option value="promote" <?php echo $filterAction === 'promote' ? 'selected' : ''; ?>>Promote</option>
                            <option value="demote" <?php echo $filterAction === 'demote' ? 'selected' : ''; ?>>Demote</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Zeitraum</label>
                        <select name="days">
                            <option value="1" <?php echo $filterDays === 1 ? 'selected' : ''; ?>>Heute</option>
                            <option value="7" <?php echo $filterDays === 7 ? 'selected' : ''; ?>>Letzte 7 Tage</option>
                            <option value="30" <?php echo $filterDays === 30 ? 'selected' : ''; ?>>Letzte 30 Tage</option>
                            <option value="90" <?php echo $filterDays === 90 ? 'selected' : ''; ?>>Letzte 90 Tage</option>
                            <option value="0" <?php echo $filterDays === 0 ? 'selected' : ''; ?>>Alle</option>
                        </select>
                    </div>
                    <?php if (count($users) > 0): ?>
                        <div class="filter-group">
                            <label>Benutzer</label>
                            <select name="user_id">
                                <option value="">Alle</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $filterUser == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['nachname'] . ', ' . $user['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>Suche</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Text oder IP...">
                            </div>
                            <button type="submit" class="filter-btn center">
                                <span class="material-symbols-outlined">filter_alt</span>
                                Filtern
                            </button>
                            <a href="admin.php" class="reset-btn center">
                                Zurücksetzen
                            </a>
                        </div>
            </form>
        </div>
        <!-- Recent Logs -->
        <div class="section">
            <h2><span class="material-symbols-outlined">list</span> Letzte Einträge (max. 100)</h2>
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Zeit</th>
                        <th>Aktion</th>
                        <th>Details</th>
                        <th>IP</th>
                        <th>User-ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td data-label="Zeit"><?php echo htmlspecialchars($log['timecode']); ?></td>
                        <td data-label="Aktion"><span class="action-badge action-<?php echo htmlspecialchars($log['action']); ?>"><?php echo htmlspecialchars($log['action']); ?></span></td>
                        <td data-label="Details"><?php echo htmlspecialchars($log['text']); ?></td>
                        <td data-label="IP">
                            <span class="ip-address" oncontextmenu="showIPBlockMenu(event, '<?php echo htmlspecialchars($log['ip'], ENT_QUOTES); ?>'); return false;">
                                <?php echo htmlspecialchars($log['ip']); ?>
                            </span>
                            <?php if (in_array($log['ip'], $blockedIPs)): ?>
                                <span class="material-symbols-outlined" style="color: #f44336; font-size: 16px; vertical-align: middle;" title="Diese IP ist blockiert">block</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="User-ID"><?php echo htmlspecialchars($log['user_id'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($logs) === 0): ?>
                    <tr>
                        <td colspan="5" class="no-entries">
                            Keine Einträge gefunden.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Blocked IPs Section -->
        <div class="section">
            <h2><span class="material-symbols-outlined">block</span> Blockierte IP-Adressen</h2>
            <?php if (count($blockedIPs) > 0): ?>
                <ul class="error-list">
                    <?php foreach ($blockedIPs as $ip): ?>
                    <li class="error-item">
                        <div>
                            <div class="error-url"><?php echo htmlspecialchars($ip); ?></div>
                            <small>Blockiert in .htaccess</small>
                        </div>
                        <button onclick="unblockIPConfirm('<?php echo htmlspecialchars($ip, ENT_QUOTES); ?>')" style="background: #4caf50; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                            <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">check_circle</span>
                            Entblocken
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">Keine IPs blockiert</p>
            <?php endif; ?>
        </div>
        
        <!-- IP-Block Context Menu -->
        <div id="ip-context-menu" class="context-menu" style="display: none;" data-blocked-ips='<?php echo json_encode($blockedIPs); ?>'>
            <button type="button" id="block-ip-btn" onclick="blockIPConfirm()">
                <span class="material-symbols-outlined">block</span>
                <span class="text">IP blockieren</span>
            </button>
            <button type="button" id="unblock-ip-btn" onclick="unblockIPConfirm()" style="display: none;">
                <span class="material-symbols-outlined">check_circle</span>
                <span class="text">IP entblocken</span>
            </button>
            <button type="button" onclick="copyIPToClipboard()">
                <span class="material-symbols-outlined">content_copy</span>
                <span class="text">IP kopieren</span>
            </button>
        </div>
        
        <!-- Member Context Menu -->
        <div id="member-context-menu" class="context-menu">
            <form action="admin.php" method="post" id="member-context-menu-form">
                <input type="hidden" id="context-member-id" name="member_id" value="">
                <input type="hidden" id="context-action" name="action" value="">
                <button type="button" id="edit-member">
                    <span class="material-symbols-outlined">edit</span>
                    <span class="text">bearbeiten</span>
                </button>
                <button type="button" id="activate-member">
                    <span class="material-symbols-outlined">verified</span>
                    <span class="text">als aktiv markieren</span>
                </button>
                <button type="button" id="deactivate-member">
                    <span class="material-symbols-outlined">do_not_disturb_on</span>
                    <span class="text">als inaktiv markieren</span>
                </button>
                <button type="button" id="up-member">
                    <span class="material-symbols-outlined">shield_person</span>
                    <span class="text">Vorstandsrolle hinzufügen</span>
                </button>
                <button type="button" id="down-member">
                    <span class="material-symbols-outlined">person</span>
                    <span class="text">Vorstandsrolle entfernen</span>
                </button>
                <button type="button" id="make-admin-member">
                    <span class="material-symbols-outlined">admin_panel_settings</span>
                    <span class="text">Zum Admin machen</span>
                </button>
                <button type="button" id="remove-admin-member">
                    <span class="material-symbols-outlined">person</span>
                    <span class="text">Admin-Rolle entfernen</span>
                </button>
                <button type="button" id="delete-member">
                    <span class="material-symbols-outlined">delete</span>
                    <span class="text">löschen</span>
                </button>
            </form>
        </div>

        <!-- Edit Member Popup -->
        <div class="popup" id="edit-member-popup">
            <div class="popup-content" style="max-width: 600px;">
                <h2>Mitglied bearbeiten</h2>
                <form id="edit-member-form">
                    <input type="hidden" id="edit-member-id" name="member_id">
                    <input type="hidden" name="action" value="edit-member">
                    
                    <div class="form-group">
                        <label for="edit-titel">Titel:</label>
                        <input type="text" id="edit-titel" name="titel">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-name">Vorname: *</label>
                        <input type="text" id="edit-name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-nachname">Nachname: *</label>
                        <input type="text" id="edit-nachname" name="nachname" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-strasse">Straße: *</label>
                        <input type="text" id="edit-strasse" name="strasse" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-hausnummer">Hausnummer: *</label>
                        <input type="text" id="edit-hausnummer" name="hausnummer" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-adresszusatz">Adresszusatz:</label>
                        <input type="text" id="edit-adresszusatz" name="adresszusatz">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-plz">PLZ: *</label>
                        <input type="text" id="edit-plz" name="plz" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-ort">Ort: *</label>
                        <input type="text" id="edit-ort" name="ort" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-festnetz">Festnetz:</label>
                        <input type="tel" id="edit-festnetz" name="festnetz">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-mobilnummer">Mobilnummer:</label>
                        <input type="tel" id="edit-mobilnummer" name="mobilnummer">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-email">E-Mail: *</label>
                        <input type="email" id="edit-email" name="e_mail" required>
                    </div>
                    
                    <div class="buttons">
                        <button type="button" class="abbrechen" onclick="closeEditPopup()">Abbrechen</button>
                        <button type="submit" class="submit">Speichern</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Promote Password Popup -->
        <div class="popup" id="promote-password-popup">
            <div class="popup-content">
                <h2>Vorstandsrolle zuweisen</h2>
                <h3>Bitte geben Sie ein vorläufiges Passwort für den neuen Vorstand ein.</h3>
                <div class="form-group">
                    <label for="promote-temp-password">Vorläufiges Passwort:</label>
                    <input type="password" id="promote-temp-password" required minlength="8">
                    <small style="color: #666;">Mindestens 8 Zeichen</small>
                </div>
                <div class="form-group">
                    <label for="promote-temp-password-confirm">Passwort bestätigen:</label>
                    <input type="password" id="promote-temp-password-confirm" required minlength="8">
                </div>
                <div class="buttons">
                    <button type="button" class="abbrechen" onclick="cancelPromote()">Abbrechen</button>
                    <button type="button" class="submit" onclick="confirmPromote()">Bestätigen</button>
                </div>
            </div>
        </div>

        <script>
            // Log deletion function
            function deleteLogsByAction(action, count) {
                if (!confirm('Möchten Sie wirklich alle ' + count + ' Log-Einträge der Aktion "' + action + '" löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden!')) {
                    return;
                }
                
                fetch('admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: 'delete_logs_action=' + encodeURIComponent(action)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('✓ ' + data.count + ' Log-Einträge erfolgreich gelöscht.');
                        location.reload();
                    } else {
                        alert('✗ Fehler beim Löschen: ' + (data.message || 'Unbekannter Fehler'));
                    }
                })
                .catch(error => {
                    console.error('Lösch-Fehler:', error);
                    alert('✗ Fehler beim Löschen der Logs:\n' + error.message + '\n\nMöglicherweise haben Sie keine Berechtigung oder die Verbindung wurde unterbrochen.');
                });
            }
            
            // Member info display functions
            function showMemberInfo(memberId) {
                const memberElement = document.querySelector(`.member[data-member-id='${memberId}']`);
                if (!memberElement) return;
                
                const data = {
                    titel: memberElement.dataset.titel || '',
                    name: memberElement.dataset.name || '',
                    nachname: memberElement.dataset.nachname || '',
                    strasse: memberElement.dataset.strasse || '',
                    hausnummer: memberElement.dataset.hausnummer || '',
                    adresszusatz: memberElement.dataset.adresszusatz || '',
                    plz: memberElement.dataset.plz || '',
                    ort: memberElement.dataset.ort || '',
                    festnetz: memberElement.dataset.festnetz || '',
                    mobilnummer: memberElement.dataset.mobilnummer || '',
                    email: memberElement.dataset.email || ''
                };
                
                const rolleText = memberElement.querySelector('.role-text').innerText;
                const statusText = memberElement.querySelector('.status-text').innerText;
                
                const isOwnAccount = parseInt(memberId) === currentUserId;
                const passwordButton = isOwnAccount ? `
                    <button onclick="showPasswordChangePopup()" class="detail-button" style="margin-top: 15px;">
                        <span class="material-symbols-outlined">lock</span>
                        Passwort ändern
                    </button>
                ` : '';
                
                document.getElementById('member-detail').innerHTML = `
                    <div class="member-detail-header">
                        <h3>${data.titel} ${data.name} ${data.nachname}</h3>
                        <div class="member-detail-badges">
                            <span class="badge ${rolleText.toLowerCase()}">${rolleText}</span>
                            <span class="badge ${statusText.toLowerCase()}">${statusText}</span>
                        </div>
                    </div>
                    <div class="member-detail-content">
                        <div class="detail-section">
                            <h4>Kontaktinformationen</h4>
                            <div class="detail-item">
                                <span class="material-symbols-outlined">mail</span>
                                <div>
                                    <label>E-Mail</label>
                                    <span>${data.email || '-'}</span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <span class="material-symbols-outlined">call</span>
                                <div>
                                    <label>Festnetz</label>
                                    <span>${data.festnetz || '-'}</span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <span class="material-symbols-outlined">smartphone</span>
                                <div>
                                    <label>Mobilnummer</label>
                                    <span>${data.mobilnummer || '-'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="detail-section">
                            <h4>Adresse</h4>
                            <div class="detail-item">
                                <span class="material-symbols-outlined">home</span>
                                <div>
                                    <label>Straße & Hausnummer</label>
                                    <span>${data.strasse} ${data.hausnummer}</span>
                                </div>
                            </div>
                            ${data.adresszusatz ? `
                            <div class="detail-item">
                                <span class="material-symbols-outlined">add_home</span>
                                <div>
                                    <label>Adresszusatz</label>
                                    <span>${data.adresszusatz}</span>
                                </div>
                            </div>
                            ` : ''}
                            <div class="detail-item">
                                <span class="material-symbols-outlined">location_on</span>
                                <div>
                                    <label>PLZ & Ort</label>
                                    <span>${data.plz} ${data.ort}</span>
                                </div>
                            </div>
                        </div>
                        ${passwordButton}
                    </div>
                `;
            }
            
            function closeMemberInfo() {
                document.getElementById('member-detail').innerHTML = `
                    <div id="no-selection">
                        <span class="material-symbols-outlined" style="font-size: 64px; opacity: 0.3;">person</span>
                        <p>Wählen Sie ein Mitglied aus, um Details anzuzeigen</p>
                    </div>
                `;
            }
            
            // Edit popup functions
            function openEditPopup(memberId) {
                const memberElement = document.querySelector(`.member[data-member-id='${memberId}']`);
                if (!memberElement) return;
                
                document.getElementById('edit-member-id').value = memberId;
                document.getElementById('edit-titel').value = memberElement.dataset.titel || '';
                document.getElementById('edit-name').value = memberElement.dataset.name || '';
                document.getElementById('edit-nachname').value = memberElement.dataset.nachname || '';
                document.getElementById('edit-strasse').value = memberElement.dataset.strasse || '';
                document.getElementById('edit-hausnummer').value = memberElement.dataset.hausnummer || '';
                document.getElementById('edit-adresszusatz').value = memberElement.dataset.adresszusatz || '';
                document.getElementById('edit-plz').value = memberElement.dataset.plz || '';
                document.getElementById('edit-ort').value = memberElement.dataset.ort || '';
                document.getElementById('edit-festnetz').value = memberElement.dataset.festnetz || '';
                document.getElementById('edit-mobilnummer').value = memberElement.dataset.mobilnummer || '';
                document.getElementById('edit-email').value = memberElement.dataset.email || '';
                
                document.getElementById('edit-member-popup').style.display = 'flex';
            }
            
            function closeEditPopup() {
                document.getElementById('edit-member-popup').style.display = 'none';
            }
            
            function showPasswordChangePopup() {
                // Redirect to dashboard password change
                window.location.href = 'dashboard.php#password-change';
            }
            
            // Copy dashboard.js functions for member management
            function opencontextMenu(memberId) {
                const contextMenu = document.getElementById('member-context-menu');
                contextMenu.style.display = 'block';
                if (window.innerWidth - event.pageX < contextMenu.offsetWidth) {
                    contextMenu.style.left = (event.pageX - contextMenu.offsetWidth) + 'px';
                } else {
                    contextMenu.style.left = event.pageX + 'px';
                }
                contextMenu.style.top = event.pageY + 'px';
                contextMenu.setAttribute('data-member-id', memberId);
                document.getElementById("context-member-id").value = memberId;
                
                const memberElement = document.querySelector(`.member[data-member-id='${memberId}']`);
                const statusText = memberElement.querySelector('.status-text').innerText;
                if (statusText === 'Aktiv') {
                    document.getElementById('deactivate-member').style.display = 'flex';
                    document.getElementById('activate-member').style.display = 'none';
                } else {
                    document.getElementById('deactivate-member').style.display = 'none';
                    document.getElementById('activate-member').style.display = 'flex';
                }
                
                const rolleText = memberElement.querySelector('.role-text').innerText;
                const isAdmin = rolleText === 'Admin';
                
                if (rolleText === 'Mitglied') {
                    document.getElementById('up-member').style.display = 'flex';
                    document.getElementById('down-member').style.display = 'none';
                } else if (rolleText === 'Vorstand') {
                    document.getElementById('up-member').style.display = 'none';
                    document.getElementById('down-member').style.display = 'flex';
                } else {
                    document.getElementById('up-member').style.display = 'none';
                    document.getElementById('down-member').style.display = 'none';
                }
                
                // Show "Make Admin" button for non-admins, "Remove Admin" button for admins
                document.getElementById('make-admin-member').style.display = isAdmin ? 'none' : 'flex';
                document.getElementById('remove-admin-member').style.display = isAdmin ? 'flex' : 'none';
            }

            function closeContextMenu() {
                document.getElementById('member-context-menu').style.display = 'none';
            }

            let pendingPromoteMemberId = null;
            function showPromotePasswordPopup(memberId) {
                pendingPromoteMemberId = memberId;
                document.getElementById('promote-password-popup').style.display = 'flex';
                document.getElementById('promote-temp-password').value = '';
                document.getElementById('promote-temp-password-confirm').value = '';
                document.getElementById('promote-temp-password').focus();
            }
            
            function cancelPromote() {
                pendingPromoteMemberId = null;
                document.getElementById('promote-password-popup').style.display = 'none';
            }
            
            function confirmPromote() {
                const password = document.getElementById('promote-temp-password').value;
                const passwordConfirm = document.getElementById('promote-temp-password-confirm').value;
                if (!password || password.length < 8) {
                    alert('Das Passwort muss mindestens 8 Zeichen lang sein.');
                    return;
                }
                if (password !== passwordConfirm) {
                    alert('Die Passwörter stimmen nicht überein.');
                    return;
                }
                if (pendingPromoteMemberId) {
                    document.getElementById('promote-password-popup').style.display = 'none';
                    submitContextActionAjax('promote', pendingPromoteMemberId, password);
                    pendingPromoteMemberId = null;
                }
            }

            async function submitContextActionAjax(action, memberId, tempPassword) {
                try {
                    const params = new URLSearchParams();
                    params.append('action', action);
                    params.append('member_id', memberId);
                    if (tempPassword) {
                        params.append('temp_password', tempPassword);
                    }

                    const res = await fetch('admin.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: params.toString()
                    });

                    const data = await res.json();
                    if (!data.success) {
                        alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
                        return;
                    }

                    location.reload();
                } catch (err) {
                    alert('Netzwerkfehler: ' + err.message);
                }
            }

            // Event listeners
            document.addEventListener('DOMContentLoaded', () => {
                // Edit member form submission
                document.getElementById('edit-member-form')?.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(e.target);
                    const params = new URLSearchParams(formData);
                    
                    try {
                        const res = await fetch('admin.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: params.toString()
                        });
                        
                        const data = await res.json();
                        if (!data.success) {
                            alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
                            return;
                        }
                        
                        closeEditPopup();
                        location.reload();
                    } catch (err) {
                        alert('Netzwerkfehler: ' + err.message);
                    }
                });
                
                // Edit button in context menu
                document.getElementById('edit-member')?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const memberId = document.getElementById('context-member-id').value;
                    if (memberId) {
                        openEditPopup(memberId);
                        closeContextMenu();
                    }
                });
                
                document.getElementById('activate-member')?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const memberId = document.getElementById('context-member-id').value;
                    if (memberId) {
                        submitContextActionAjax('activate', memberId);
                        closeContextMenu();
                    }
                });

                document.getElementById('deactivate-member')?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const memberId = document.getElementById('context-member-id').value;
                    if (memberId) {
                        submitContextActionAjax('deactivate', memberId);
                        closeContextMenu();
                    }
                });

                document.getElementById('up-member')?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const memberId = document.getElementById('context-member-id').value;
                    if (memberId) {
                        showPromotePasswordPopup(memberId);
                        closeContextMenu();
                    }
                });

                document.getElementById('down-member')?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const memberId = document.getElementById('context-member-id').value;
                    if (memberId && confirm('Vorstandsrolle entfernen?')) {
                        submitContextActionAjax('demote', memberId);
                        closeContextMenu();
                    }
                });

                document.getElementById('make-admin-member')?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const memberId = document.getElementById('context-member-id').value;
                    if (memberId && confirm('Diesen Benutzer zum Admin machen? Diese Aktion gibt dem Benutzer volle Rechte!')) {
                        submitContextActionAjax('make_admin', memberId);
                        closeContextMenu();
                    }
                });

                document.getElementById('remove-admin-member')?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const memberId = document.getElementById('context-member-id').value;
                    if (memberId && confirm('Admin-Rolle entfernen? Der Benutzer wird zum normalen Mitglied.')) {
                        submitContextActionAjax('remove_admin', memberId);
                        closeContextMenu();
                    }
                });

                document.getElementById('delete-member')?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const memberId = document.getElementById('context-member-id').value;
                    if (memberId && confirm('Möchten Sie dieses Mitglied wirklich löschen?')) {
                        submitContextActionAjax('delete', memberId);
                        closeContextMenu();
                    }
                });

                document.addEventListener('click', (event) => {
                    const contextMenu = document.getElementById('member-context-menu');
                    if (contextMenu && contextMenu.style.display !== 'none' && !contextMenu.contains(event.target) && !event.target.classList.contains('edit-button')) {
                        contextMenu.style.display = 'none';
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeContextMenu();
                        closeIPContextMenu();
                        cancelPromote();
                        closeEditPopup();
                        closeMemberInfo();
                    }
                });
                
                // Close IP menu on outside click
                document.addEventListener('click', (event) => {
                    const ipMenu = document.getElementById('ip-context-menu');
                    if (ipMenu && ipMenu.style.display !== 'none' && !ipMenu.contains(event.target) && !event.target.closest('[onclick*="showIPBlockMenu"]')) {
                        closeIPContextMenu();
                    }
                });
            });
        </script>
    </div>
    <div id="footer" class="center">
        <?php include '../../pages/internes/footer.php'; ?>
    </div>
</body>
</html>