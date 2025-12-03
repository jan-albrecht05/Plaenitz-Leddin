<?php
session_start();

// Include database helper functions
require_once '../../includes/db_helper.php';
require_once '../../includes/log-data.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
if (!hasAdminRole($userId)) {
    header("Location: dashboard.php");
    exit();
}

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
                        <td data-label="IP"><?php echo htmlspecialchars($log['ip']); ?></td>
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
        
        <!-- Context Menu -->
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
            const currentUserId = <?php echo json_encode($userId); ?>;
            
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
                        cancelPromote();
                        closeEditPopup();
                        closeMemberInfo();
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