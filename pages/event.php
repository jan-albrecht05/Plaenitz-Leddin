<?php
session_start();

// Include database helper functions
require_once '../includes/db_helper.php';

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
// get event ID from URL
$event_id = $_GET['id'] ?? null;
// If no id in query but form posts an event_id (participation), accept that
if ($event_id === null && isset($_POST['event_id'])) {
    $event_id = $_POST['event_id'];
} elseif ($event_id === null) {
    // redirect to events page if no ID is provided anywhere
    header('Location: ../pages/veranstaltungen.php');
    exit;
}
// fetch event details from database
if ($event_id !== null) {
    // Resolve absolute path to the DB (robust against working-directory differences)
    $dbPath = __DIR__ . '/../assets/db/veranstaltungen.db';

    // Basic existence/readability checks so we can log a helpful error instead of a fatal exception
    if (!file_exists($dbPath)) {
        error_log("event.php: database file not found: $dbPath");
        $event = null;
    } elseif (!is_readable($dbPath)) {
        error_log("event.php: database file not readable by PHP process: $dbPath");
        $event = null;
    } else {
        // If a participation POST was sent, process it here before selecting the event
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['participate'])) {
            $postedId = (int)($_POST['event_id'] ?? $event_id);
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            if (!isset($_SESSION['participated_events']) || !is_array($_SESSION['participated_events'])) {
                $_SESSION['participated_events'] = [];
            }

            // Guard: fetch event date/time and block participation for past events
            $isPastEvent = false;
            try {
                $pdo_chk = new PDO('sqlite:' . $dbPath);
                $pdo_chk->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // Avoid long locks if DB is busy
                $pdo_chk->exec('PRAGMA busy_timeout = 2000');
                $stmt_chk = $pdo_chk->prepare('SELECT datum, zeit FROM veranstaltungen WHERE id = :id LIMIT 1');
                $stmt_chk->bindValue(':id', $postedId, PDO::PARAM_INT);
                $stmt_chk->execute();
                $row = $stmt_chk->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $dtStr = trim((string)($row['datum'] ?? ''));
                    $tmStr = trim((string)($row['zeit'] ?? ''));
                    if ($tmStr !== '') { $dtStr .= ' ' . $tmStr; }
                    $ts = $dtStr !== '' ? strtotime($dtStr) : false;
                    if ($ts !== false && $ts < time()) {
                        $isPastEvent = true;
                    }
                }
            } catch (Exception $e) {
                error_log('event.php: Failed to check event datetime - ' . $e->getMessage());
            }

            if (!$isPastEvent && !isset($_SESSION['participated_events'][$postedId])) {
                // mark as participated in session first to avoid race conditions
                $_SESSION['participated_events'][$postedId] = time();
                // Release session lock early to prevent blocking parallel requests
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                try {
                    $pdo_upd = new PDO('sqlite:' . $dbPath);
                    $pdo_upd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    // Avoid long locks if DB is busy
                    $pdo_upd->exec('PRAGMA busy_timeout = 2000');
                    $stmt_upd = $pdo_upd->prepare('UPDATE veranstaltungen SET teilnehmer = COALESCE(teilnehmer, 0) + 1 WHERE id = :id');
                    $stmt_upd->bindValue(':id', $postedId, PDO::PARAM_INT);
                    $stmt_upd->execute();
                } catch (Exception $e) {
                    error_log('event.php: Failed to update participants - ' . $e->getMessage());
                }
            }

            // Redirect back to the event page (PRG pattern) so page reloads don't resubmit the form
            $self = $_SERVER['PHP_SELF'] ?? 'event.php';
            header('Location: ' . $self . '?id=' . urlencode($postedId));
            exit();
        }

        try {
            // Use PDO with sqlite for safer, consistent parameter binding and exceptions
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Avoid long locks if DB is busy
            $pdo->exec('PRAGMA busy_timeout = 2000');

            $stmt = $pdo->prepare('SELECT * FROM veranstaltungen WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', (int)$event_id, PDO::PARAM_INT);
            $stmt->execute();
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($event === false) {
                $event = null; // no row found
            }
        } catch (Exception $e) {
            error_log('event.php: DB error - ' . $e->getMessage());
            $event = null;
        }
    }
}

// Ensure $event_title exists so the <title> tag won't throw a notice
$event_title = $event['titel'] ?? 'Veranstaltung';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $event_title; ?> | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/event.css">
    <link rel="stylesheet" href="../assets/css/heading.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <style>
        #veranstaltungen a{
            color: var(--primary-color);
        }
        #veranstaltungen .line{
            background-color: var(--primary-color);
            width: 100%;
        }
    </style>
</head>
<body>
    <div id="heading">
        <?php include '../pages/heading.php'; ?>
    </div>
    <?php
        // Banner: use event cover image if available, otherwise fall back to default text banner
        $bannerTitle = htmlspecialchars($event['titel'] ?? 'Zwei Dörfer, eine Gemeinschaft');
        $bannerStyle = '';
        if ($event && !empty($event['cover_image_name']) && file_exists(__DIR__ . '/../' . $event['cover_image_name'])) {
            $imgUrl = '../' . $event['cover_image_name'];
            // inline style keeps templates simple; background-size ensures cover look
            $bannerStyle = 'style="background-image: url(' . htmlspecialchars($imgUrl) . '); background-size: cover; background-position: center;"';
        }
    ?>
    <div class="banner" <?php echo $bannerStyle; ?>>
        <h1><?php echo $bannerTitle; ?></h1>
    </div>
    <?php
        // Show success banner if redirected after deletion
        if (isset($_GET['success'])) {
            $successMessage = htmlspecialchars($_GET['success']);
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    var successBar = document.getElementById("success-bar");
                    var successMessageElem = document.getElementById("success-message");
                    var successTimeline = document.getElementById("success-timeline");
                    successMessageElem.textContent = ' . json_encode($successMessage) . ';
                    successBar.style.display = "flex";
                    // Animate timeline
                    successTimeline.style.animation = "timelineAnimation 2s linear forwards";
                    // Hide after 5 seconds
                    setTimeout(function() {
                        successBar.style.display = "none";
                    }, 5000);
                });
            </script>';
        }
    ?>
    <div class="notification-bar center" id="success-bar" style="display: none;">
        <span class="material-symbols-outlined">check_circle</span>
        <span id="success-message"></span>
        <span class="timeline" id="success-timeline"></span>
    </div>
    <div id="main">
        <div id="back-button" onclick="history.back()">
            <span class="material-symbols-outlined">arrow_back</span>
            <span>Zurück</span>
        </div>
        <div id="event-content">
            <div class="info">
                <div class="author">
                    <span class="material-symbols-outlined">person</span>
                    <span><?php echo htmlspecialchars($event['autor'] ?? 'Unbekannt'); ?></span>
                </div>
                <div class="date">
                    <span class="material-symbols-outlined">calendar_month</span>
                    <span><?php
                        if ($event && isset($event['timecode_erstellt'])) {
                            //date in UNIX format
                            $date = new DateTime();
                            $date->setTimestamp((int)$event['timecode_erstellt']);
                            echo $date->format('d.m.Y');
                        } else {
                            echo 'Unbekannt';
                        }
                    ?></span>
                </div>
            </div>
            <h1 class="titel">
                <?php echo htmlspecialchars($event['titel'] ?? 'Veranstaltung'); ?>
            </h1>
            <?php
                if ($event && !empty($event['banner_image_name'])) {
                    echo '<div class="cover-image">';
                        echo '<img src="' . htmlspecialchars($event['banner_image_name']) . '" alt="">';
                    echo '</div>';
                } else {
                    echo '';
                }
            ?>
            <h3 class="untertitel">
                <?php echo htmlspecialchars($event['beschreibung'] ?? ''); ?>
            </h3>
            <h2 class="zeit">
                Wann? <span><?php echo htmlspecialchars(date('d.m.Y', strtotime($event['datum'] ?? ''))) . (isset($event['zeit']) ? htmlspecialchars(', ' . $event['zeit'] . ' Uhr') : ''); ?></span>
            </h2>
            <h2 class="ort">
                Wo? <span><?php echo htmlspecialchars($event['ort'] ?? ''); ?></span>
            </h2>
            <h2 class="kosten">
                Eintritt: <span><?php echo !empty($event['cost']) ? htmlspecialchars($event['cost']) : 'Frei'; ?></span>
            </h2>
            <h2 class="zielgruppe">
                Für wen? <span><?php echo htmlspecialchars($event['zielgruppe'] ?? ''); ?></span>
            </h2>
            <?php
            if (!$event) {
                echo '<p>Veranstaltung nicht gefunden.</p>';
            }
            ?>
            <div id="description">
                <?php echo nl2br(htmlspecialchars($event['text'] ?? '')); ?>
            </div>
            <div class="row">
                <div id="view-count">
                    <span class="material-symbols-outlined">visibility</span>
                    <span><?php echo htmlspecialchars($event['viewcount'] ?? 0); ?></span>
                </div>
                <div id="participants-count">
                    <span class="material-symbols-outlined">group</span>
                    <span><?php echo htmlspecialchars($event['teilnehmer'] ?? 0); ?></span>
                </div>
            </div>
            <?php
                // Compute if event is in the past based on datum and zeit
                $isPast = false;
                if ($event && !empty($event['datum'])) {
                    $dtStr = trim((string)$event['datum']);
                    $tmStr = trim((string)($event['zeit'] ?? ''));
                    if ($tmStr !== '') { $dtStr .= ' ' . $tmStr; }
                    $ts = strtotime($dtStr);
                    if ($ts !== false && $ts < time()) {
                        $isPast = true;
                    }
                }
            ?>
            <?php if(!$isPast): ?>
            <form id="participate-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . urlencode((string)$event_id)); ?>">
                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_id); ?>">
                <button type="submit" name="participate" id="participate-button">
                    <span class="material-symbols-outlined">how_to_reg</span>
                    <span>Teilnehmen</span>
                </button>
            </form>
            <?php endif; ?>
            <?php if($is_admin || $is_vorstand): ?>
            <div class="form">
                <button id="edit-event-button" onclick="location.href='../pages/internes/edit-event.php?id=<?php echo urlencode($event_id); ?>'">
                    <span class="material-symbols-outlined">edit</span>
                    <span>Veranstaltung bearbeiten</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
        // Increment view count
        if ($event) {
            try {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }

                if (!isset($_SESSION['viewed_events']) || !is_array($_SESSION['viewed_events'])) {
                    $_SESSION['viewed_events'] = [];
                }

                $shouldIncrement = true;
                // if this event id was already viewed in this session, skip increment
                if (isset($_SESSION['viewed_events'][(int)$event_id])) {
                    $shouldIncrement = false;
                    error_log('event.php: viewcount not incremented for event ' . (int)$event_id . ' — already viewed in this session');
                }

                if ($shouldIncrement) {
                    // mark as viewed in session immediately to avoid race conditions
                    $_SESSION['viewed_events'][(int)$event_id] = time();

                    $pdo = new PDO('sqlite:' . $dbPath);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    // Avoid long locks if DB is busy
                    $pdo->exec('PRAGMA busy_timeout = 2000');

                    $stmt = $pdo->prepare('UPDATE veranstaltungen SET viewcount = COALESCE(viewcount, 0) + 1 WHERE id = :id');
                    $stmt->bindValue(':id', (int)$event_id, PDO::PARAM_INT);
                    $stmt->execute();
                }
            } catch (Exception $e) {
                error_log('event.php: Failed to increment view count - ' . $e->getMessage());
            }
        }
        // participants are updated via the POST handler above (PRG)
    ?>
    <div id="footer" class="center">
        <?php include '../pages/footer.php'; ?>
    </div>
</body>
</html>