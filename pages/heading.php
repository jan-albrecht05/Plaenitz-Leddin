<div id="left">
            <a href="../index.php">
                <img src="../assets/icons/logo.png" alt="">
                <img src="../../assets/icons/logo.png" alt="">
            </a>
        </div>
        <div id="right">
            <div class="link" id="startseite">
                <a href="../index.php">Startseite</a>
                <span class="line"></span>
            </div>
            <div class="link" id="uber-uns">
                <a href="../pages/uber-uns.php">Über uns</a>
                <span class="line"></span>
            </div>
            <div class="link" id="veranstaltungen">
                <a href="../pages/veranstaltungen.php">Veranstaltungen</a>
                <span class="line"></span>
            </div>
            <div class="link" id="kontakt">
                <a href="../pages/kontakt.php">Kontakt</a>
                <span class="line"></span>
            </div>
            <button id="mitglied-werden" onclick="location.href='../pages/mitglied-werden.php'">Mitglied werden</button>
            <?php
            // Ensure session and helpers are available
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            @require_once __DIR__ . '/../includes/db_helper.php';

            $user_id = $_SESSION['user_id'] ?? null;
            $is_admin = $user_id ? (function_exists('hasAdminRole') ? hasAdminRole($user_id) : false) : false;
            $is_vorstand = $user_id ? (function_exists('hasVorstandRole') ? hasVorstandRole($user_id) : false) : false;

            $hasNewNotifications = false;
            $utc = new DateTimeZone('UTC');
            if (($is_admin || $is_vorstand) && $user_id) {
                try {
                    $pdo = function_exists('getMemberDbConnection') ? getMemberDbConnection() : null;
                    if ($pdo) {
                        $stmt = $pdo->prepare('SELECT last_viewed_notification FROM mitglieder WHERE id = :id LIMIT 1');
                        $stmt->bindValue(':id', (int)$user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        $lastViewed = $row && !empty($row['last_viewed_notification']) ? $row['last_viewed_notification'] : (new DateTime('1970-01-01 00:00:00', $utc))->format('Y-m-d H:i:s');

                        $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM mitglieder WHERE join_date > :lv AND id != :me');
                        $stmt2->bindValue(':lv', $lastViewed, PDO::PARAM_STR);
                        $stmt2->bindValue(':me', (int)$user_id, PDO::PARAM_INT);
                        $stmt2->execute();
                        $hasNewNotifications = ((int)$stmt2->fetchColumn()) > 0;
                    }
                } catch (Exception $e) {
                    error_log('heading.php: indicator check failed - ' . $e->getMessage());
                }
            }

            if ($is_admin || $is_vorstand) {
                echo '<div id="admin-buttons">'
                    . '<a id="admin-button" onclick="location.href=\'internes/admin.php\'">'
                        . '<span class="material-symbols-outlined">admin_panel_settings</span>'
                    . '</a>'
                    . '<a id="notifications-button" onclick="showNotifications()">'
                        . '<span class="material-symbols-outlined">notifications</span>'
                        . '<span id="notification-indicator"' . ($hasNewNotifications ? '' : ' style="display:none"') . '></span>'
                    . '</a>'
                . '</div>';
            }
            ?>
    <script src="../assets/js/navbar.js"></script>
    <script src="../../assets/js/navbar.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script src="../../assets/js/notifications.js"></script>
    <a href="javascript:void(0);" style="font-size:15px;" class="icon" onclick="dreibalkensymbol()">&#9776;</a>
</div>
<!-- Notifications Popup (shared) -->
<div id="notifications-popup" class="hidden">
    <div id="notifications-header">
        <span>Benachrichtigungen</span>
        <span id="close-notifications" onclick="hideNotifications()">&times;</span>
    </div>
    <div id="notifications-content">
        <?php
            // Render notifications list if user has permission
            if ($is_admin || $is_vorstand) {
                try {
                    $memberPdo = function_exists('getMemberDbConnection') ? getMemberDbConnection() : null;
                    if (!$memberPdo) {
                        echo '<p>Fehler: Keine Verbindung zur Mitglieder-Datenbank.</p>';
                    } else {
                        $utc = new DateTimeZone('UTC');
                        $stmt = $memberPdo->prepare('SELECT last_viewed_notification FROM mitglieder WHERE id = :id LIMIT 1');
                        $stmt->bindValue(':id', (int)$user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        $lastViewed = $row && !empty($row['last_viewed_notification'])
                        ? $row['last_viewed_notification']
                        : (new DateTime('1970-01-01 00:00:00', $utc))->format('Y-m-d H:i:s');
                        
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
                                // Format join_date explicitly in UTC (assumes DB stores UTC or naive timestamps treated as UTC)
                                if (!empty($m['join_date'])) {
                                    try {
                                        $dt = new DateTime($m['join_date'], $utc);
                                        $joinDate = htmlspecialchars($dt->setTimezone($utc)->format('d.m.Y'));
                                    } catch (Exception $e) {
                                        $joinDate = htmlspecialchars($m['join_date']);
                                    }
                                } else {
                                    $joinDate = '-';
                                }
                                echo '<li class="notification-item">';
                                echo '<strong>' . $displayName . '</strong> — Beigetreten am ' . $joinDate;
                                echo '</li>';
                            }
                            echo '</ul>';
                            // Use an absolute endpoint to be robust across include contexts; JS also has fallbacks
                            echo '<button id="mark-read" data-user-id="' . htmlspecialchars((string)$user_id) . '" data-endpoint="/pages/internes/mark_notifications_read.php">Als gelesen markieren</button>';
                        }
                    }
                } catch (Exception $e) {
                    error_log('heading.php notifications: ' . $e->getMessage());
                    echo '<p>Fehler beim Laden der Benachrichtigungen.</p>';
                }
            } else {
                echo '<p>Keine Berechtigung für Benachrichtigungen.</p>';
            }
            ?>
        </div>
    </div>
</div>