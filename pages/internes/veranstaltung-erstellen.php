<?php
require_once '../../includes/session-config.php';
startSecureSession();

// Include database helper functions
require_once '../../includes/db_helper.php';
require_once '../../includes/log-data.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has admin or vorstand role by querying the database
$userId = $_SESSION['user_id'];
if (!hasAdminOrVorstandRole($userId)) {
    // User doesn't have required role, redirect to login
    session_destroy();
    header("Location: login.php?error=" . urlencode("Sie haben keine Berechtigung für diese Seite."));
    exit();
}
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form inputs
    $titel = trim($_POST['titel']);
    $untertitel = trim($_POST['untertitel']);
    $datum = $_POST['datum'];
    // If the time checkbox was not checked (input disabled), treat time as NULL.
    // The checkbox 'uhrzeit_aktiv' is present when the user enabled a time.
    if (isset($_POST['uhrzeit_aktiv']) && ($_POST['uhrzeit_aktiv'] === 'on' || $_POST['uhrzeit_aktiv'] === '1')) {
        $uhrzeit = !empty($_POST['uhrzeit']) ? $_POST['uhrzeit'] : null;
    } else {
        $uhrzeit = null;
    }
    $ort = trim($_POST['ort']);
    $kosten = !empty($_POST['kosten']) ? trim($_POST['kosten']) : 'Frei';
    $beschreibung = trim($_POST['beschreibung']);
    $zielgruppe = $_POST['zielgruppe'] ?? 'alle';
    $tags = ''; // Placeholder for tags, can be extended later

    // Handle file upload
    $coverImagePath = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/images/uploads/event_covers/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileTmpPath = $_FILES['cover_image']['tmp_name'];
        $fileName = basename($_FILES['cover_image']['name']);
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = uniqid('event_cover_', true) . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $coverImagePath = '../assets/images/uploads/event_covers/' . $newFileName;
        }
    }

    // Insert event into database
    // Resolve path to events DB and create PDO connection
    $dbPath = __DIR__ . '/../../assets/db/veranstaltungen.db';
    if (!file_exists($dbPath) || !is_readable($dbPath)) {
        error_log('veranstaltung-erstellen.php: events DB not found or not readable: ' . $dbPath);
        header("Location: veranstaltungen.php?error=" . urlencode("Datenbank nicht verfügbar."));
        exit();
    }
    // do not use time input when disabled (check the html input element)
    

    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Insert into the existing 'veranstaltungen' table (columns match pages/event.php usage)
        $stmt = $pdo->prepare("INSERT INTO veranstaltungen 
                    (titel, beschreibung, autor, datum, zeit, ort, cost, text, zielgruppe, banner_image_name, timecode_erstellt, tags, flag) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        try {
            $stmt->execute([$titel, $untertitel, $_SESSION['name'], $datum, $uhrzeit, $ort, $kosten, $beschreibung, $zielgruppe, $coverImagePath, time(), $tags, 1]);
            logAction(date('Y-m-d H:i:s'), 'create_event', $_SESSION['name'] . ' created event ' . $titel, $_SERVER['REMOTE_ADDR'], $_SESSION['user_id']);
            // Log last insert id for verification
            try {
                $lastId = $pdo->lastInsertId();
                error_log('veranstaltung-erstellen.php: insert successful, lastInsertId=' . $lastId);
            } catch (Exception $e) {
                error_log('veranstaltung-erstellen.php: could not get lastInsertId: ' . $e->getMessage());
            }
        } catch (Exception $ex) {
            error_log('veranstaltung-erstellen.php: execute failed - ' . $ex->getMessage());
            try {
                ob_start();
                $stmt->debugDumpParams();
                $dbg2 = ob_get_clean();
                error_log('veranstaltung-erstellen.php: prepared statement AFTER failure:\n' . $dbg2);
            } catch (Exception $e2) {
                error_log('veranstaltung-erstellen.php: debugDumpParams after failure failed: ' . $e2->getMessage());
            }
            throw $ex;
        }
    } catch (Exception $e) {
        error_log('../veranstaltung-erstellen.php: DB error - ' . $e->getMessage());
        //header("Location: veranstaltungen.php?error=" . urlencode("Fehler beim Erstellen der Veranstaltung."));
        exit();
    }


    // Redirect to event list or confirmation page
    header("Location: ../veranstaltungen.php?success=" . urlencode("Veranstaltung erfolgreich erstellt."));
    exit();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veranstaltung erstellen | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../../assets/css/root.css">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/event.css">
    <link rel="stylesheet" href="../../assets/css/add-event.css">
    <link rel="stylesheet" href="../../assets/css/heading.css">
    <link rel="stylesheet" href="../../assets/css/footer.css">
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
            <a href="logout.php" id="logout-button">
                <span class="material-symbols-outlined">logout</span>
            </a>
        </div>
    </div>
    <div class="banner">
        <h1>Veranstaltung erstellen</h1>
    </div>
    <div id="main">
        <div id="back-button" onclick="history.back()">
            <span class="material-symbols-outlined">arrow_back</span>
            <span>Zurück</span>
        </div>
        <form id="event-form" action="veranstaltung-erstellen.php" method="POST" enctype="multipart/form-data">
            <div class="info">
                <div class="author">
                    <span class="material-symbols-outlined">person</span>
                    <span><?php echo htmlspecialchars($_SESSION['name'] ?? 'Unbekannt'); ?></span>
                </div>
                <div class="date">
                    <span class="material-symbols-outlined">calendar_month</span>
                    <span>
                        <?php
                            echo (new DateTime('now'))->format('d.m.Y');
                        ?>
                    </span>
                </div>
            </div>
            <div class="form-row">
                <label for="titel">Titel der Veranstaltung:</label>
                <input type="text" id="titel" name="titel" required>
            </div>
            <div class="form-row">
                <label id="drop-zone" class="center">
                    <div id="first-text" class="center">
                        <span class="material-symbols-outlined">image_arrow_up</span>
                        <span>Cover-Bild hochladen oder hier ablegen</span>
                    </div>
                    <input type="file" id="file-input" name="cover_image" accept="image/*" />
                    <button id="clear-btn" class="center" type="button" onclick="clearDropzoneBackground()">
                        <span class="material-symbols-outlined">delete</span>
                        Bild entfernen
                    </button>
                </label>
                <script src="../../assets/js/add-event.js"></script>
            </div>
            <div class="form-row">
                <label for="untertitel">Untertitel der Veranstaltung:</label>
                <input type="text" id="untertitel" name="untertitel" required>
            </div>
            <div class="form-row">
                <label for="datum">Datum:</label>
                <input type="date" id="datum" name="datum" required>
            </div>
            <div class="form-row">
                <label for="uhrzeit">Uhrzeit:</label>
                <input type="time" id="uhrzeit" name="uhrzeit" disabled placeholder="18:00:00">
                <span>Uhrzeit hinzufügen
                    <input type="checkbox" id="uhrzeit-aktivieren" name="uhrzeit_aktiv" onchange="document.getElementById('uhrzeit').disabled = !this.checked;">
                </span>
            </div>
            <div class="form-row">
                <label for="ort">Ort:</label>
                <input type="text" id="ort" name="ort" required placeholder="z.B. Gemeindehaus Leddin">
            </div>
            <div class="form-row">
                <label for="kosten">Eintrittskosten:</label>
                <input type="text" id="kosten" name="kosten" disabled value="Frei">
                <span>Eintritt festlegen
                    <input type="checkbox" id="kosten-aktivieren" onchange="document.getElementById('kosten').disabled = !this.checked;">
                </span>
            </div>
            <div class="form-row">
                <label for="zielgruppe">Zielgruppe:</label>
                <select name="zielgruppe">
                    <option value="alle" selected>Alle Anwohner</option>
                    <option value="Familien">Familien</option>
                    <option value="Kinder">Kinder</option>
                    <option value="Mitglieder">Mitglieder</option>
                    <option value="Rentner">Rentner</option>
                    <option value="Vorstand">Vorstand</option>
                </select>
            </div>
            <div class="form-row">
                <label for="beschreibung">Beschreibung:</label>
                <textarea id="beschreibung" name="beschreibung" rows="5" required></textarea>
            </div>
            <button type="submit">Veranstaltung erstellen</button>
        </form>
    </div>
    <div id="footer" class="center">
        <?php include '../../pages/internes/footer.php'; ?>
    </div>
</body>
</html>