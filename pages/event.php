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
if($event_id === null) {
    // redirect to events page if no ID is provided
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
        try {
            // Use PDO with sqlite for safer, consistent parameter binding and exceptions
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
                if ($event && !empty($event['cover_image_name']) && file_exists(__DIR__ . '/../' . $event['cover_image_name'])) {
                    echo '<div class="cover-image">';
                        echo '<img src="../' . htmlspecialchars($event['cover_image_name']) . '" alt="' . htmlspecialchars($event['titel'] ?? 'Veranstaltung') . '">';
                    echo '</div>';
                } else {
                    echo '';
                }
            ?>
            <h3 class="untertitel">
                <?php echo htmlspecialchars($event['beschreibung'] ?? ''); ?>
            </h3>
            <h2 class="zeit">
                Wann? <span><?php echo htmlspecialchars($event['datum'] ?? '') . ', ' . (isset($event['zeit']) ? htmlspecialchars($event['zeit'].' Uhr') : ''); ?></span>
            </h2>
            <h2 class="ort">
                Wo? <span><?php echo htmlspecialchars($event['ort'] ?? ''); ?></span>
            </h2>
            <h2 class="kosten">
                Eintritt: <span><?php echo htmlspecialchars($event['cost'] ?? ''); ?></span>
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
            <?php if($user_id == null): ?>
            <form id="participate-form" method="post" action="../pages/veranstaltungen.php">
                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_id); ?>">
                <button type="submit" name="participate" id="participate-button">
                    <span class="material-symbols-outlined">how_to_reg</span>
                    <span>Teilnehmen</span>
                </button>
            </form>
            <?php else: ?>
            <div class="form">
                <button id="edit-event-button" onclick="location.href='../pages/internes/dashboard.php'">
                    <span class="material-symbols-outlined">edit</span>
                    <span>Veranstaltung bearbeiten</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div id="footer" class="center">
        <?php include '../pages/footer.php'; ?>
    </div>
</body>
</html>