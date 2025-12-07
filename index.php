<?php
require_once 'includes/session-config.php';
startSecureSession();

// Include database helper functions
require_once 'includes/db_helper.php';

if(isset($_SESSION['user_id'])) {
    // User is logged in, check roles from database
    $user_id = $_SESSION['user_id'];
    $is_admin = hasAdminRole($user_id);
    $is_vorstand = hasVorstandRole($user_id);
} else {
    // User is not logged in
    $user_id = null;
    $is_admin = false;
    $is_vorstand = false;
}
// compute notification indicator (new members since last_viewed_notification)
$hasNewNotifications = false;
// Use UTC consistently for comparisons
$utc = new DateTimeZone('UTC');
if (($is_admin || $is_vorstand) && $user_id !== null) {
    try {
        $memberPdo = function_exists('getMemberDbConnection') ? getMemberDbConnection() : null;
        if ($memberPdo) {
            $stmt = $memberPdo->prepare('SELECT last_viewed_notification FROM mitglieder WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', (int)$user_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            // Default to Unix epoch in UTC if empty
            $lastViewed = $row && !empty($row['last_viewed_notification']) ? $row['last_viewed_notification'] : (new DateTime('1970-01-01 00:00:00', $utc))->format('Y-m-d H:i:s');

            $stmt2 = $memberPdo->prepare('SELECT COUNT(*) AS c FROM mitglieder WHERE join_date > :lv AND id != :me');
            $stmt2->bindValue(':lv', $lastViewed, PDO::PARAM_STR);
            $stmt2->bindValue(':me', (int)$user_id, PDO::PARAM_INT);
            $stmt2->execute();
            $count = (int)($stmt2->fetchColumn() ?: 0);
            $hasNewNotifications = $count > 0;
        }
    } catch (Exception $e) {
        error_log('index.php: indicator check failed - ' . $e->getMessage());
    }
}
// connect to events database
// Resolve absolute path to the DB (robust against working-directory differences)
// index.php sits in the project root; the DB is in the `db/` folder at project root
$dbPath = __DIR__ . '/assets/db/veranstaltungen.db';
// Basic existence/readability checks so we can log a helpful error instead of a fatal exception
if (!file_exists($dbPath)) {
    error_log("index.php: database file not found: $dbPath");
    $events = [];
} elseif (!is_readable($dbPath)) {
    error_log("index.php: database file not readable by PHP process: $dbPath");
    $events = [];
} else {
    try {
        // Use PDO with sqlite for safer, consistent parameter binding and exceptions
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->query('SELECT * FROM veranstaltungen WHERE datum >= date("now") ORDER BY datum ASC LIMIT 4');
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($events === false) {
            $events = []; // no rows found
        }
    } catch (Exception $e) {
        error_log('index.php: DB error - ' . $e->getMessage());
        $events = [];
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plänitz-Leddin</title>
    <link rel="stylesheet" href="assets/css/root.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/heading.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <style>
        #startseite a{
            color: var(--primary-color);
        }
        #startseite .line{
            background-color: var(--primary-color);
            width: 100%;
        }
    </style>
</head>
<body>
    <div id="heading">
        <div id="left">
            <a href="#">
                <img src="assets/icons/logo.png" alt="Plänitz-Leddin Logo">
            </a>
        </div>
        <div id="right">
            <div class="link" id="startseite">
                <a href="#">Startseite</a>
                <span class="line"></span>
            </div>
            <div class="link" id="uber-uns">
                <a href="pages/uber-uns.php">Über uns</a>
                <span class="line"></span>
            </div>
            <div class="link" id="veranstaltungen">
                <a href="pages/veranstaltungen.php">Veranstaltungen</a>
                <span class="line"></span>
            </div>
            <div class="link" id="kontakt">
                <a href="pages/kontakt.php">Kontakt</a>
                <span class="line"></span>
            </div>
            <button id="mitglied-werden" onclick="location.href='pages/mitglied-werden.php'">Mitglied werden</button>
            <?php
            if ($is_admin || $is_vorstand) {
                echo '<div id="admin-buttons">'
                    . '<a id="admin-button" onclick="location.href=\'pages/internes/admin.php\'">'
                        . '<span class="material-symbols-outlined">admin_panel_settings</span>'
                    . '</a>'
                    . '<a id="notifications-button" onclick="showNotifications()">'
                        . '<span class="material-symbols-outlined">notifications</span>'
                        . '<span id="notification-indicator"' . ($hasNewNotifications ? '' : ' style="display:none"') . '></span>'
                    . '</a>'
                . '</div>';
            }
            ?>
        </div>
        <script src="assets/js/navbar.js"></script>
        <a href="javascript:void(0);" style="font-size:15px;" class="icon" onclick="dreibalkensymbol()">&#9776;</a>
        <div id="notifications-popup" class="hidden">
            <div id="notifications-header">
                <span>Benachrichtigungen</span>
                <span id="close-notifications" onclick="hideNotifications()">&times;</span>
            </div>
            <div id="notifications-content">
                <?php
                // Show notifications for admins / vorstand: members who joined after the admin's last_viewed_notification
                if ($is_admin || $is_vorstand) {
                    // Attempt to get PDO connection from helper
                    $memberPdo = null;
                    if (function_exists('getMemberDbConnection')) {
                        $memberPdo = getMemberDbConnection();
                    }

                    // Fallback: try project-root db/member.db
                    if (!$memberPdo) {
                        $memberDbPath = __DIR__ . '/assets/db/member.db';
                        if (file_exists($memberDbPath) && is_readable($memberDbPath)) {
                            try {
                                $memberPdo = new PDO('sqlite:' . $memberDbPath);
                                $memberPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            } catch (Exception $ex) {
                                error_log('notifications: fallback member DB connection failed - ' . $ex->getMessage());
                                $memberPdo = null;
                            }
                        }
                    }

                    if (!$memberPdo) {
                        echo '<p>Fehler: Keine Verbindung zur Mitglieder-Datenbank.</p>';
                    } else {
                        try {
                            // Get this user's last_viewed_notification (expect ISO date string)
                            $stmt = $memberPdo->prepare('SELECT last_viewed_notification FROM mitglieder WHERE id = :id LIMIT 1');
                            $stmt->bindValue(':id', (int)$user_id, PDO::PARAM_INT);
                            $stmt->execute();
                            $row = $stmt->fetch(PDO::FETCH_ASSOC);
                            $lastViewed = $row && !empty($row['last_viewed_notification']) ? $row['last_viewed_notification'] : null;

                            // If lastViewed is null, set to very old date so everyone is considered new
                            if (!$lastViewed) {
                                $lastViewed = '1970-01-01';
                            }

                            // Find members who joined AFTER lastViewed
                            $stmt2 = $memberPdo->prepare('SELECT id, name, nachname, join_date FROM mitglieder WHERE join_date > :lastViewed AND id != :me ORDER BY join_date ASC');
                            $stmt2->bindValue(':lastViewed', $lastViewed, PDO::PARAM_STR);
                            $stmt2->bindValue(':me', (int)$user_id, PDO::PARAM_INT);
                            $stmt2->execute();
                            $newMembers = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                            if (empty($newMembers)) {
                                echo '<p>Keine neuen Benachrichtigungen.</p>';
                            } else {
                                echo '<ul class="notifications-list">';
                                foreach ($newMembers as $m) {
                                    $displayName = htmlspecialchars(trim(($m['name'] ?? '') . ' ' . ($m['nachname'] ?? '')));
                                    $joinDate = !empty($m['join_date']) ? htmlspecialchars((new DateTime($m['join_date']))->format('d.m.Y')) : '-';
                                    echo '<li class="notification-item">';
                                    echo '<strong>' . $displayName . '</strong> — Beigetreten am ' . $joinDate;
                                    echo '</li>';
                                }
                                echo '</ul>';
                                    echo '<button id="mark-read" data-user-id="' . htmlspecialchars((string)$user_id) . '" data-endpoint="pages/internes/mark_notifications_read.php">Als gelesen markieren</button>';
                            }
                            // set new date for last_viewed_notification in member.db when "mark-read" is clicked
                                // inline script removed; handled by assets/js/notifications.js
                        } catch (Exception $e) {
                            error_log('notifications: DB query failed - ' . $e->getMessage());
                            echo '<p>Fehler beim Lesen der Benachrichtigungen.</p>';
                        }
                    }
                } else {
                    echo '<p>Keine Berechtigung für Benachrichtigungen.</p>';
                }
                ?>
            </div>
        </div>
        <script src="assets/js/notifications.js"></script>
    </div>
    <div class="banner">
        <h1>Zwei Dörfer, eine Gemeinschaft</h1>
    </div>
    <div id="main">
        <div class="notification">
            <div class="mg-left" class="center">
                <span class="mmaterial-symbols-outlined"></span>
            </div>
            <div class="mg-right">
                <h2>Wartungsarbeiten</h2>
                <p>
                    Es finden Wartungsarbeiten im Zeitraum vom XX.XX bis zum YY.YY statt.
                </p>
                <span id="zeitraum">XX.XX. - YY.YY.</span>
            </div>
        </div>
        <div class="section" id="willkommen">
            <h1>Willkommen</h1>
            <p>Wir sind ein Verein, der sich der Förderung und Unterstützung der Gemeinschaft in den Dörfern Plänitz und Leddin widmet. Unser Ziel ist es, das soziale Miteinander zu stärken und gemeinsame Aktivitäten zu organisieren.</p>
            <p>Ob Sie neu in der Gegend sind oder schon lange hier leben, wir laden Sie herzlich ein, Teil unserer Gemeinschaft zu werden. Gemeinsam können wir viel erreichen und das Leben in unseren Dörfern bereichern.</p>
            <p>Schauen Sie sich auf unserer Webseite um, um mehr über unsere Aktivitäten und Veranstaltungen zu erfahren. Wir freuen uns darauf, Sie kennenzulernen!</p>
            <a href="pages/uber-uns.php" class="button center">Mehr erfahren<span class="material-symbols-outlined center">chevron_right</span></a>
        </div>
        <div class="section" id="veranstaltungen">
            <h1>Veranstaltungen</h1>
            <p>Wir organisieren regelmäßig Veranstaltungen, um die Gemeinschaft zu fördern und den Zusammenhalt zu stärken. Feste wie Oster- oder Herbstfeuer, einen Weihnachtsmarkt und viele weitere Aktivitäten stehen auf dem Programm.</p>
            <p>Unsere Veranstaltungen bieten eine großartige Gelegenheit, neue Leute kennenzulernen, Spaß zu haben und aktiv am Dorfleben teilzunehmen. Wir freuen uns immer über neue Gesichter und frische Ideen.</p>
            <div class="einrück">
                <h2>Nächste Veranstaltungen:</h2>
                <?php
                // only show future events IMPORTANT
                if (!empty($events)) {
                    foreach ($events as $event) {
                        echo '<div class="event">
                                <span class="event-title">' . htmlspecialchars($event['titel']) . '</span>
                                <span class="date">' . htmlspecialchars((new DateTime($event['datum']))->format('d.m.Y')) . '</span>
                                <span class="location">' . htmlspecialchars($event['ort']) . '</span>
                                <a href="pages/event.php?id=' . urlencode($event['id']) . '" class="more-info" title="Mehr erfahren"><span class="material-symbols-outlined">arrow_right_alt</span></a>
                              </div>';
                    }
                } else {
                    echo '<p>Keine bevorstehenden Veranstaltungen.</p>';
                }
                ?>                
            </div>
            <a href="pages/veranstaltungen.php" class="button center">Alle Veranstaltungen<span class="material-symbols-outlined center">chevron_right</span></a>
        </div>
        <div class="section" id="mitglied-werden">
            <h1>Mitglied werden</h1>
            <p>Werden Sie Teil unserer Gemeinschaft und unterstützen Sie unsere Arbeit durch eine Mitgliedschaft im Verein. Als Mitglied profitieren Sie von zahlreichen Vorteilen und können aktiv an der Gestaltung des Dorflebens teilnehmen.</p>
            <p>Die Mitgliedschaft ist einfach und unkompliziert. Füllen Sie unser Online-Formular aus oder kontaktieren Sie uns direkt, um mehr über die Vorteile und den Ablauf zu erfahren.</p>
            <a href="pages/mitglied-werden.php" class="button center">Jetzt Mitglied werden<span class="material-symbols-outlined center">chevron_right</span></a>
        </div>
    </div>
    <div id="cookie-banner">
        <div id="cookie-img">
            <img src="assets/icons/Omi.png" alt="Cookie Icon">
        </div>
        <div id="cookie-text">
            <h2>Mit Liebe serviert.</h2>
            <p>Unsere Website verwendet Cookies, um Ihnen das bestmögliche Erlebnis zu bieten. Durch die Nutzung unserer Website stimmen Sie der Verwendung von Cookies zu. Weitere Informationen finden Sie in unserer <a href="pages/datenschutz.php">Datenschutzerklärung</a>.</p>
            <button id="accept-cookies" onclick="acceptCookies()">Cookies akzeptieren</button>
            <script src="assets/js/cookies.js"></script>
        </div>
    </div>
    <div id="footer" class="center">
        <div id="footer-gif">
            <img src="assets/GIFs/autumn.gif" alt="">
        </div>
        <div id="left">
            <div id="mode-toggle">
                <span class="material-symbols-outlined">light_mode</span>
                <label class="switch">
                    <input type="checkbox" id="toggle-checkbox">
                    <span class="slider round"></span>
                </label>
                <span class="material-symbols-outlined">dark_mode</span>
                <script src="assets/js/mode.js"></script>
            </div>
            <?php
            if($is_admin){
                echo '<a href="pages/internes/admin.php">Admin-Anmeldung<span class="material-symbols-outlined">open_in_new</span></a>';
            }else{
                echo '<a href="pages/internes/dashboard.php">Admin-Anmeldung<span class="material-symbols-outlined">open_in_new</span></a>';
            }
            ?>
        </div>
        <div id="middle">
            <span>&copy; 2025-<?php echo date("Y"); ?> Gemeinsam für Plänitz-Leddin.<br> Alle Rechte vorbehalten.</span>
            <a href="https://github.com/jan-albrecht05/Plaenitz-Leddin/commits/main/">Version 0.9</a>
        </div>
        <div id="right">
            <a href="pages/kontakt.php">Kontakt<span class="material-symbols-outlined">open_in_new</span></a>
            <a href="pages/datenschutz.php">Datenschutz<span class="material-symbols-outlined">open_in_new</span></a>
            <a href="pages/impressum.php">Impressum<span class="material-symbols-outlined">open_in_new</span></a>
        </div>
    </div>
</body>
</html>