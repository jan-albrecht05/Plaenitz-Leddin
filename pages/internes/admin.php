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

// Get user name for logging (needed before AJAX handlers)
$userName = $_SESSION['name'] ?? 'Admin';

// === HANDLE AJAX MEMBER ACTIONS FIRST (before any output) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'], $_POST['action'])) {
    $currentUserId = $_SESSION['user_id'] ?? null;
    $targetId = (int)$_POST['member_id'];
    $action = (string)$_POST['action'];

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

    $respond = function($success, $message) use ($isAjax) {
        if ($isAjax) {
            // Clear any buffered output before sending JSON
            while (ob_get_level()) {
                ob_end_clean();
            }
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

    // Get target member info for logging
    $stmt = $pdo->prepare('SELECT name, nachname FROM mitglieder WHERE id = :id');
    $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
    $stmt->execute();
    $targetMember = $stmt->fetch(PDO::FETCH_ASSOC);
    $targetName = $targetMember ? ($targetMember['name'] . ' ' . $targetMember['nachname']) : 'Unbekannt';

    // Process the action
    if ($action === 'activate' || $action === 'deactivate') {
        $newStatus = ($action === 'activate') ? 1 : 2;
        $stmt = $pdo->prepare('UPDATE mitglieder SET status = :status WHERE id = :id');
        $stmt->bindValue(':status', $newStatus, PDO::PARAM_INT);
        $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Log the action
        $logActionType = $action === 'activate' ? 'activate_member' : 'deactivate_member';
        $logText = $userName . ' hat ' . $targetName . ($action === 'activate' ? ' aktiviert' : ' deaktiviert');
        logAction('', $logActionType, $logText, '', $currentUserId);
        
        $msg = $action === 'activate' ? 'Benutzer aktiviert.' : 'Benutzer deaktiviert.';
        $respond(true, $msg);
    }

    if ($action === 'make_admin') {
        $stmt = $pdo->prepare('UPDATE mitglieder SET rolle = :rolle WHERE id = :id');
        $stmt->bindValue(':rolle', 'admin', PDO::PARAM_STR);
        $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Log the action
        logAction('', 'member-make-admin', $userName . ' hat ' . $targetName . ' zum Admin gemacht', '', $currentUserId);
        
        $respond(true, 'Benutzer zum Admin gemacht.');
    }

    if ($action === 'remove_admin') {
        // Prevent self-demotion
        if ($currentUserId === $targetId) {
            $respond(false, 'Sie können sich nicht selbst die Admin-Rolle entziehen.');
        }
        
        $stmt = $pdo->prepare('UPDATE mitglieder SET rolle = :rolle, last_visited_date = NULL WHERE id = :id');
        $stmt->bindValue(':rolle', 'mitglied', PDO::PARAM_STR);
        $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Log the action
        logAction('', 'member-remove-admin', $userName . ' hat die Admin-Rolle von ' . $targetName . ' entfernt', '', $currentUserId);
        
        $respond(true, 'Admin-Rolle entfernt.');
    }

    if ($action === 'promote' || $action === 'demote') {
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
                
                // Log the action
                logAction('', 'promote-member', $userName . ' hat ' . $targetName . ' zum Vorstand befördert', '', $currentUserId);
                
                $respond(true, 'Benutzer zum Vorstand gemacht und Passwort gesetzt.');
            } catch (Exception $e) {
                $pdo->rollBack();
                $respond(false, $e->getMessage());
            }
        } else {
            // demote
            $stmt = $pdo->prepare('UPDATE mitglieder SET rolle = :rolle, last_visited_date = NULL WHERE id = :id');
            $stmt->bindValue(':rolle', 'mitglied', PDO::PARAM_STR);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Log the action
            logAction('', 'demote-member', $userName . ' hat ' . $targetName . ' zum Mitglied degradiert', '', $currentUserId);
            
            $respond(true, 'Benutzer gedemoted.');
        }
    }

    if ($action === 'edit-member') {
        // Clear output buffer before processing
        while (ob_get_level()) {
            ob_end_clean();
        }
        
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
        
        // Log the action
        logAction('', 'edit-member', $userName . ' hat die Daten von ' . $targetName . ' bearbeitet', '', $currentUserId);
        
        $respond(true, 'Mitglied erfolgreich bearbeitet.');
    }

    if ($action === 'delete') {
        if ($currentUserId === $targetId) {
            $respond(false, 'Sie können sich nicht selbst löschen.');
        }
        $stmt = $pdo->prepare('DELETE FROM mitglieder WHERE id = :id');
        $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Log the action
        logAction('', 'delete-member', $userName . ' hat ' . $targetName . ' gelöscht', '', $currentUserId);
        
        $respond(true, 'Benutzer gelöscht.');
    }

    // If we reach here, action was not handled
    $respond(false, 'Unbekannte Aktion.');
}
// === END AJAX HANDLERS ===

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
    $where[] = "(text LIKE :search OR ip LIKE :search OR action LIKE :search)";
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

// Handle IP blocking/unblocking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ip_action'], $_POST['ip_address'])) {
    $ipAction = $_POST['ip_action'];
    $ipAddress = $_POST['ip_address'];
    
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    
    $respond = function($success, $message) use ($isAjax) {
        if ($isAjax) {
            // Clear any buffered output before sending JSON
            while (ob_get_level()) {
                ob_end_clean();
            }
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
        <script src="../../assets/js/admin-members.js" defer></script>
        <script src="../../assets/js/block-IP.js" defer></script>
        <script src="../../assets/js/log-deletion.js" defer></script>
        <script>
            // expose current user for logging and client-side helpers
            window.currentUserId = <?php echo json_encode($userId); ?>;
            window.currentUserName = <?php echo json_encode($userName); ?>;
            window.currentUserIsAdmin = <?php echo json_encode(hasAdminRole($userId)); ?>;
            window.currentUserIsVorstand = <?php echo json_encode(hasVorstandRole($userId)); ?>;
            
            // Global state for promote action
            let pendingPromoteMemberId = null;
            
            // Show password popup for promote action
            function showPromotePasswordPopup(memberId) {
                pendingPromoteMemberId = memberId;
                document.getElementById('promote-password-popup').style.display = 'flex';
                document.getElementById('promote-temp-password').value = '';
                document.getElementById('promote-temp-password-confirm').value = '';
                document.getElementById('promote-temp-password').focus();
            }
            
            // Cancel promote action
            function cancelPromote() {
                pendingPromoteMemberId = null;
                document.getElementById('promote-password-popup').style.display = 'none';
            }
            
            // Confirm promote with password
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
                if (pendingPromoteMemberId && window.submitContextActionAjax) {
                    document.getElementById('promote-password-popup').style.display = 'none';
                    submitContextActionAjax('promote', pendingPromoteMemberId, password);
                    pendingPromoteMemberId = null;
                }
            }
            
            // Wrapper function for context actions (needed by dashboard.js)
            function submitContextAction(action, memberId) {
                // For promote action, show password popup first
                if (action === 'promote') {
                    showPromotePasswordPopup(memberId);
                    return;
                }
                // For other actions, use AJAX directly
                if (window.submitContextActionAjax) {
                    submitContextActionAjax(action, memberId);
                }
            }
        </script>
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
        <!-- Member Management -->
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
        <div class="popup" <?php if (isset($_GET['neu']) || isset($_GET['change_pw'])) echo 'style="display: flex;"'; ?> id="pw-change-popup">
            <div class="popup-content">
                <h2>Es scheint, als wäre dies ihr erster Login.</h2>
                <h3>Bitte legen Sie sich ein neues Passwort fest.</h3>
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message" style="background-color: #ffebee; border: 1px solid #ef5350; color: #c62828; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                <form action="dashboard.php" method="post" id="pw-change-form">
                    <div class="form-group">
                        <label for="current-password">Aktuelles Passwort:</label>
                        <input type="password" id="current-password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new-password">Neues Passwort:</label>
                        <input type="password" id="new-password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">Neues Passwort bestätigen:</label>
                        <input type="password" id="confirm-password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="submit" onclick="console.log('Submit button clicked');">Passwort ändern</button>
                </form>
            </div>
        </div>
        <div class="popup" <?php if (isset($_GET['neu']) || isset($_GET['change_pw'])) echo 'style="display: flex;"'; ?> id="password-change-popup">
            <div class="popup-content">
                <h2>Passwort ändern</h2>
                <h3>Bitte legen Sie sich ein neues Passwort fest.</h3>
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message" style="background-color: #ffebee; border: 1px solid #ef5350; color: #c62828; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                <form action="dashboard.php" method="post" id="pw-change-form">
                    <div class="form-group">
                        <label for="current-password">Aktuelles Passwort:</label>
                        <input type="password" id="current-password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new-password">Neues Passwort:</label>
                        <input type="password" id="new-password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">Neues Passwort bestätigen:</label>
                        <input type="password" id="confirm-password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="submit" onclick="console.log('Submit button clicked');">Passwort ändern</button>
                </form>
            </div>
        </div>
        <div id="member-context-menu" class="context-menu">
            <form action="dashboard.php" method="post" id="member-context-menu-form">
                <input type="hidden" id="context-member-id" name="member_id" value="">
                <input type="hidden" id="context-action" name="action" value="">
                <button type="button" id="edit-password-member">
                    <span class="material-symbols-outlined">key</span>
                    <span class="text">Passwort ändern</span>
                </button>
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
        <div class="popup center" id="member-edit-popup">
            <div id="member-edit-form" class="popup-content">
                <button class="close-popup-button center" onclick="closeMemberEditPopup()">
                    <span class="material-symbols-outlined">close</span>
                </button>
                <form action="dashboard.php" method="post">
                    <input type="hidden" id="edit-member-id" name="member_id" value="">
                    <input type="hidden" name="edit_member_submit" value="1">
                    <h2>Mitglied bearbeiten</h2>
                    <h3>Grunddaten</h3>
                    <div class="row">
                        <div class="short form-group">
                            <label for="edit-titel">Titel:</label>
                            <input type="text" id="edit-titel" name="titel" value="">
                        </div>
                        <div class="form-group">
                            <label for ="edit-name">Vorname:</label>
                            <input type="text" id="edit-name" name="name" value="">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit-nachname">Nachname:</label>
                        <input type="text" id="edit-nachname" name="nachname" value="">
                    </div>
                    <h3>Addresse</h3>
                    <div class="row">
                        <div class="form-group" id="form-group-street">
                            <label for="edit-strasse">Straße:</label>
                            <input type="text" id="edit-strasse" name="strasse" value="">
                        </div>
                        <div class="short form-group">
                            <label for="edit-hausnummer">Hausnummer:</label>
                            <input type="text" id="edit-hausnummer" name="hausnummer" value="">
                        </div>
                    </div>
                    <div class="row">
                        <div class="short form-group">
                            <label for="edit-plz">PLZ:</label>
                            <input type="text" id="edit-plz" name="plz" value="">
                        </div>
                        <div class="form-group">
                            <label for="edit-ort">Ort:</label>
                            <input type="text" id="edit-ort" name="ort" value="">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit-adresszusatz">Adresszusatz:</label>
                        <input type="text" id="edit-adresszusatz" name="adresszusatz" value="">
                    </div>
                    <h3>Kontaktinformationen</h3>
                    <div class="form-group">
                        <label for="edit-email">E-Mail:</label>
                        <input type="email" id="edit-email" name="email" value="">
                    </div>
                    <div class="form-group">
                        <label for="edit-telefon">Telefon:</label>
                        <input type="tel" id="edit-telefon" name="telefon" value="">
                    </div>
                    <div class="form-group">
                        <label for="edit-mobilnummer">Mobilnummer:</label>
                        <input type="tel" id="edit-mobilnummer" name="mobilnummer" value="">
                    </div>
                    <div class="buttons">
                        <button type="button" class="abbrechen" onclick="closeMemberEditPopup()">Abbrechen</button>
                        <button type="submit" class="submit">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
        <div id="member-output">
            <h2><span class="material-symbols-outlined">person</span> User Management</h2>
            <div id="member-output-heading">
                <button class="member-button ganzer-name" onclick="window.location.href='dashboard.php?sort=nachname'">Name</button>
                <div class="role-status flex-row">
                    <button class="member-button role" onclick="window.location.href='dashboard.php?sort=rolle'">Rolle</button>
                    <span class="status">Status</span>
                </div>
                <span class="edit-button"></span>
            </div>
            <script>
                // opencontextMenu function for admin.php with admin-specific logic
                document.addEventListener('DOMContentLoaded', () => {
                    window.opencontextMenu = function(memberId, event) {
                        const contextMenu = document.getElementById('member-context-menu');
                        contextMenu.style.display = 'block';
                        
                        // Position context menu
                        if (event && window.innerWidth - event.pageX < contextMenu.offsetWidth) {
                            contextMenu.style.left = (event.pageX - contextMenu.offsetWidth) + 'px';
                        } else if (event) {
                            contextMenu.style.left = event.pageX + 'px';
                        }
                        if (event) {
                            contextMenu.style.top = event.pageY + 'px';
                        }
                        
                        contextMenu.setAttribute('data-member-id', memberId);
                        document.getElementById("context-member-id").value = memberId;
                        
                        // Get member element
                        const memberElement = document.querySelector(\`.member[data-member-id='\${memberId}']\`);
                        if (!memberElement) return;
                        
                        const statusText = memberElement.querySelector('.status-text')?.innerText || '';
                        const rolleText = memberElement.querySelector('.role-text')?.innerText || '';
                        
                        // Get all buttons
                        const activateBtn = document.getElementById('activate-member');
                        const deactivateBtn = document.getElementById('deactivate-member');
                        const upBtn = document.getElementById('up-member');
                        const downBtn = document.getElementById('down-member');
                        const makeAdminBtn = document.getElementById('make-admin-member');
                        const removeAdminBtn = document.getElementById('remove-admin-member');
                        const deleteBtn = document.getElementById('delete-member');
                        const editPassBtn = document.getElementById('edit-password-member');
                        
                        // Hide all buttons
                        if (activateBtn) activateBtn.style.display = 'none';
                        if (deactivateBtn) deactivateBtn.style.display = 'none';
                        if (upBtn) upBtn.style.display = 'none';
                        if (downBtn) downBtn.style.display = 'none';
                        if (makeAdminBtn) makeAdminBtn.style.display = 'none';
                        if (removeAdminBtn) removeAdminBtn.style.display = 'none';
                        if (deleteBtn) deleteBtn.style.display = 'none';
                        if (editPassBtn) editPassBtn.style.display = 'none';
                        
                        // Show status buttons
                        if (statusText === 'Aktiv') {
                            if (deactivateBtn) deactivateBtn.style.display = 'flex';
                        } else {
                            if (activateBtn) activateBtn.style.display = 'flex';
                        }
                        
                        // Show role buttons (only for active members)
                        if (statusText === 'Aktiv') {
                            const isAdmin = !!window.currentUserIsAdmin;
                            const isVorstand = !!window.currentUserIsVorstand;
                            const isOwnAccount = (window.currentUserId && parseInt(window.currentUserId, 10) === parseInt(memberId, 10));
                            
                            if (rolleText === 'Mitglied') {
                                if (upBtn && isAdmin) upBtn.style.display = 'flex';
                                if (makeAdminBtn) makeAdminBtn.style.display = 'flex';
                            } else if (rolleText === 'Vorstand') {
                                if (downBtn && isAdmin) downBtn.style.display = 'flex';
                                if (makeAdminBtn) makeAdminBtn.style.display = 'flex';
                            } else if (rolleText === 'Admin') {
                                if (removeAdminBtn && !isOwnAccount) removeAdminBtn.style.display = 'flex';
                            }
                            
                            // Show delete button
                            if (deleteBtn && (isAdmin || isVorstand) && !isOwnAccount) {
                                deleteBtn.style.display = 'flex';
                            }
                            
                            // Show password change for own account
                            if (editPassBtn && isOwnAccount) {
                                editPassBtn.style.display = 'flex';
                            }
                        }
                    };
                });
            </script>
            <?php
            // Database connection for members
            $dbPath = __DIR__ . '/../../assets/db/member.db';
            
            if (!file_exists($dbPath)) {
                echo '<div class="member"><p>Datenbankdatei nicht gefunden: ' . htmlspecialchars($dbPath) . '</p></div>';
            } else {
                try {
                    // Connect to database
                    $pdo = new PDO('sqlite:' . $dbPath);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    // Set UTF-8 encoding for proper display of special characters (ß, ä, ö, ü, etc.)
                    $pdo->exec("PRAGMA encoding = 'UTF-8'");
                    // Determine sorting
                    $allowedSorts = ['name', 'nachname', 'rolle', 'status', 'join_date'];
                    $sort = 'nachname'; // default sort
                    if (isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts)) {
                        $sort = $_GET['sort'];
                    }
                    // Get all members
                    $stmt = $pdo->prepare('SELECT * FROM mitglieder ORDER BY ' . $sort . ' ASC');
                    $stmt->execute();
                    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($members && count($members) > 0) {
                        foreach ($members as $member) {
                            // Defaults for role and status to ensure variables are always set
                            $roleClass = 'member';
                            $roleIcon = 'person';
                            $roleText = 'Mitglied';
                            
                            // Prepare data attributes for JavaScript
                            $dataAttrs = sprintf(
                                'data-titel="%s" data-name="%s" data-nachname="%s" data-strasse="%s" data-hausnummer="%s" data-adresszusatz="%s" data-plz="%s" data-ort="%s" data-festnetz="%s" data-mobilnummer="%s" data-email="%s"',
                                htmlspecialchars($member['titel'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['name'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['nachname'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['strasse'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['hausnummer'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['adresszusatz'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['PLZ'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['ort'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['festnetz'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['mobilnummer'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['e_mail'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['info'] ?? '', ENT_QUOTES)
                            );

                            $statusClass = 'pending';
                            $statusIcon = 'schedule';
                            $statusText = 'Ausstehend';

                            // Determine role class and display text
                            if (!empty($member['rolle'])) {
                                $roleLower = strtolower(trim((string)$member['rolle']));
                                if ($roleLower === 'admin') {
                                    $roleClass = 'admin';
                                    $roleIcon = 'admin_panel_settings';
                                    $roleText = 'Admin';
                                } elseif ($roleLower === 'vorstand') {
                                    $roleClass = 'vorstand';
                                    $roleIcon = 'shield_person';
                                    $roleText = 'Vorstand';
                                } elseif ($roleLower === 'mitglied') {
                                    $roleClass = 'member';
                                    $roleIcon = 'person';
                                    $roleText = 'Mitglied';
                                }
                            }

                            // Normalize and map status value (accepts 0/1/2 as int or string)
                            $statusInt = isset($member['status']) && is_numeric($member['status']) ? (int)$member['status'] : null;
                            if ($statusInt === 1) {
                                $statusClass = 'aktiv';
                                $statusIcon = 'verified';
                                $statusText = 'Aktiv';
                            } elseif ($statusInt === 2) {
                                $statusClass = 'inaktiv';
                                $statusIcon = 'do_not_disturb_on';
                                $statusText = 'Inaktiv';
                                $roleClass = 'hidden';
                            } elseif ($statusInt === 0) {
                                $statusClass = 'pending';
                                $statusIcon = 'schedule';
                                $statusText = 'Ausstehend';
                                $roleClass = 'hidden';
                            }

                            // Prepare contact values supporting both column name variants
                            $telefonVal = '';
                            if (!empty($member['festnetz'])) {
                                $telefonVal = $member['festnetz'];
                            } elseif (!empty($member['telefon'])) {
                                $telefonVal = $member['telefon'];
                            }

                            $mobilVal = '';
                            if ($member['mobilnummer'] === '+49' || $member['mobilnummer'] === '+49 ') {
                                $mobilVal = '';
                            }else{
                                $mobilVal = $member['mobilnummer'];
                            }


                            // Format join date
                            $joinDateFormatted = '';
                            if (!empty($member['join_date'])) {
                                try {
                                    $date = new DateTime($member['join_date']);
                                    $joinDateFormatted = $date->format('d.m.Y');
                                } catch (Exception $e) {
                                    $joinDateFormatted = '';
                                }
                            }
                            
                            // Format address
                            $address1 = !empty($member['strasse']) ? htmlspecialchars($member['strasse']) . ' ' . htmlspecialchars($member['hausnummer']) : '';
                            $address2 = (!empty($member['PLZ']) ? htmlspecialchars($member['PLZ']) . ' ' : '') . 
                                        (!empty($member['ort']) ? htmlspecialchars($member['ort']) : '').
                                        (!empty($member['adresszusatz']) ? '<br>'.htmlspecialchars($member['adresszusatz']) : '');
                            
                            $hasAddress = !empty($address1) || !empty($address2);
                            ?>
                            <div class="member" data-member-id="<?php echo (int)$member['id']; ?>" <?php echo $dataAttrs; ?> oncontextmenu="opencontextMenu('<?php echo htmlspecialchars($member['id']); ?>', event); return false;">
                                <div class="member-top">
                                    <div class="ganzer-name">
                                        <h2 class="nachname"><?php echo htmlspecialchars($member['titel'] ?? '') . ' ' . htmlspecialchars($member['nachname'] ?? ''); ?>, <?php echo htmlspecialchars($member['name'] ?? ''); ?></h2>
                                    </div>
                                    <div class="role-status flex-row">
                                        <div class="role <?php echo $roleClass; ?> center">
                                            <span class="material-symbols-outlined role-symbol"><?php echo $roleIcon; ?></span>
                                            <span class="role-text"><?php echo $roleText; ?></span>
                                        </div>
                                        <div class="status <?php echo $statusClass; ?> center">
                                            <span class="material-symbols-outlined status-symbol"><?php echo $statusIcon; ?></span>
                                            <span class="status-text"><?php echo $statusText; ?></span>
                                        </div>
                                    </div>
                                    <button class="edit-button" onclick="opencontextMenu('<?php echo htmlspecialchars($member['id']); ?>', event)">
                                        <span class="material-symbols-outlined">more_vert</span>
                                    </button>
                                </div>
                                <div class="member-bottom">
                                    <div class="left">
                                        <?php if ($hasAddress): ?>
                                        <div class="address center">
                                            <span class="material-symbols-outlined address-symbol">home</span>
                                            <div class="flex-column">
                                                <?php if (!empty($address1)): ?>
                                                <span class="address-text"><?php echo $address1; ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($address2)): ?>
                                                <span class="address-text"><?php echo $address2; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($joinDateFormatted)): ?>
                                        <div class="join-date center">
                                            <span class="material-symbols-outlined join-date-symbol">event</span>
                                            <span class="join-date-text"><?php echo $joinDateFormatted; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="right">
                                        <?php if (!empty($telefonVal)): ?>
                                        <div class="phone center">
                                            <span class="material-symbols-outlined phone-symbol">phone</span>
                                            <span class="phone-text"><?php echo htmlspecialchars($telefonVal); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($mobilVal)): ?>
                                        <div class="mobile center">
                                            <span class="material-symbols-outlined mobile-symbol">smartphone</span>
                                            <span class="mobile-text"><?php echo htmlspecialchars($mobilVal); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($member['e_mail'])): ?>
                                        <div class="email center">
                                            <span class="material-symbols-outlined email-symbol">email</span>
                                            <span class="email-text"><?php echo htmlspecialchars($member['e_mail']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($member['info'])): ?>
                                        <div class="info-text center">
                                            <span class="material-symbols-outlined email-symbol">sticky_note_2</span>
                                            <span class="email-text"><?php echo htmlspecialchars($member['info']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="member"><p>Keine Mitglieder gefunden.</p></div>';
                    }
                    
                } catch (Exception $e) {
                    error_log('Dashboard: DB error - ' . $e->getMessage());
                    echo '<div class="member"><p>Fehler beim Laden der Mitglieder: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
                }
            }
            ?>
        </div>
        <hr id="after-members-hr">
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
                            <option value="activate-member" <?php echo $filterAction === 'activate-member' ? 'selected' : ''; ?>>Aktivieren</option>
                            <option value="deactivate-member" <?php echo $filterAction === 'deactivate-member' ? 'selected' : ''; ?>>Deaktivieren</option>
                            <option value="promote-member" <?php echo $filterAction === 'promote-member' ? 'selected' : ''; ?>>Befördern</option>
                            <option value="demote-member" <?php echo $filterAction === 'demote-member' ? 'selected' : ''; ?>>Degradieren</option>
                            <option value="member-make-admin" <?php echo $filterAction === 'member-make-admin' ? 'selected' : ''; ?>>Admin hinzufügen</option>
                            <option value="member-remove-admin" <?php echo $filterAction === 'member-remove-admin' ? 'selected' : ''; ?>>Admin entfernen</option>
                            <option value="delete-member" <?php echo $filterAction === 'delete-member' ? 'selected' : ''; ?>>Mitglied löschen</option>
                            <option value="edit-member" <?php echo $filterAction === 'edit-member' ? 'selected' : ''; ?>>Mitglied bearbeiten</option>
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
    </div>
    <div id="footer" class="center">
        <?php include '../../pages/internes/footer.php'; ?>
    </div>
</body>
</html>