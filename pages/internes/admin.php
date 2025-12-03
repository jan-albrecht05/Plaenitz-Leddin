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
if ($memberPdo) {
    $users = $memberPdo->query("SELECT id, name, nachname FROM mitglieder ORDER BY nachname")->fetchAll(PDO::FETCH_ASSOC);
    // cout the number of users
    $usersCount = count($users);
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
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
</head>
<body>
    <div id="heading">
        <div id="left">
            <a href="../../index.php">
                <img src="../../assets/icons/logo.png" alt="">
            </a>
        </div>
        <div id="right">
            <a href="dashboard.php" style="margin-right: 1rem; color: var(--text-primary); text-decoration: none;">
                <span class="material-symbols-outlined">arrow_back</span>
            </a>
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
                <h3>Zeitraum</h3>
                <div class="value"><?php echo $filterDays === 0 ? 'Alle Zeit' : "Letzte {$filterDays} Tage"; ?></div>
            </div>
            <div class="stat-card">
                <h3>Unique Users</h3>
                <div class="value"><?php echo number_format($stats['unique_users']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Unique IPs</h3>
                <div class="value"><?php echo number_format($stats['unique_ips']); ?></div>
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
                        <td><span class="action-badge action-<?php echo htmlspecialchars($action['action']); ?>"><?php echo htmlspecialchars($action['action']); ?></span></td>
                        <td><?php echo number_format($action['count']); ?></td>
                        <td><?php echo $percentage; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 404 Errors -->
        <?php if (count($error404Stats) > 0): ?>
        <div class="section">
            <h2><span class="material-symbols-outlined">link_off</span> Häufigste 404-Fehler</h2>
            <ul class="error-list">
                <?php foreach ($error404Stats as $error): ?>
                <li class="error-item">
                    <div>
                        <div class="error-url"><?php echo htmlspecialchars($error['url']); ?></div>
                        <small style="color: var(--text-secondary);">Zuletzt: <?php echo htmlspecialchars($error['last_seen']); ?></small>
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
                <div class="filter-group">
                    <label>Suche</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Text oder IP...">
                </div>
                <button type="submit" class="filter-btn">
                    <span class="material-symbols-outlined">filter_alt</span>
                    Filtern
                </button>
                <a href="admin.php" class="reset-btn">
                    Zurücksetzen
                </a>
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
                        <td><?php echo htmlspecialchars($log['timecode']); ?></td>
                        <td><span class="action-badge action-<?php echo htmlspecialchars($log['action']); ?>"><?php echo htmlspecialchars($log['action']); ?></span></td>
                        <td><?php echo htmlspecialchars($log['text']); ?></td>
                        <td><?php echo htmlspecialchars($log['ip']); ?></td>
                        <td><?php echo htmlspecialchars($log['user_id'] ?: '-'); ?></td>
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
    </div>

    <div id="footer" class="center">
        <?php include '../../pages/internes/footer.php'; ?>
    </div>
</body>
</html>