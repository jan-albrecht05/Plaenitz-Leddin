<?php
session_start();

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
// Release session lock early to avoid blocking other requests
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
// Determine event id from GET or POST (edit target)
$event_id = $_GET['id'] ?? ($_POST['event_id'] ?? null);

// Resolve DB path early
$dbPath = __DIR__ . '/../../assets/db/veranstaltungen.db';
if (!file_exists($dbPath) || !is_readable($dbPath)) {
    error_log('edit-event.php: events DB not found or not readable: ' . $dbPath);
    header("Location: veranstaltungen.php?error=" . urlencode("Datenbank nicht verfügbar."));
    exit();
}

// Fetch existing event if id provided
$event = null;
if ($event_id !== null) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Avoid long locks if DB is busy and enable WAL for better concurrency
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $stmt = $pdo->prepare('SELECT * FROM veranstaltungen WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', (int)$event_id, PDO::PARAM_INT);
        $stmt->execute();
        $event = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log('edit-event.php: DB error fetching event - ' . $e->getMessage());
        $event = null;
    }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $event_id !== null && isset($_POST['delete_event'])) {
    try {
        $pdoDel = new PDO('sqlite:' . $dbPath);
        $pdoDel->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdoDel->exec('PRAGMA busy_timeout = 5000');
        $pdoDel->exec('PRAGMA journal_mode = WAL');

        // Delete banner image file if exists
        if (!empty($event['banner_image_name'])) {
            $projectRoot = dirname(__DIR__, 2);
            $stored = $event['banner_image_name'];
            $relative = preg_replace('#^(\.{1,2}/)+#', '', $stored);
            $oldFull = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
            if (file_exists($oldFull) && is_file($oldFull)) {
                @unlink($oldFull);
            }
        }

        // Delete event from database
        $pdoDel->beginTransaction();
        $titel = $event['titel'] ?? 'unknown';
        $deleteStmt = $pdoDel->prepare('DELETE FROM veranstaltungen WHERE id = :id');
        $deleteStmt->bindValue(':id', (int)$event_id, PDO::PARAM_INT);
        $deleteStmt->execute();
        $pdoDel->commit();
        logAction(date('Y-m-d H:i:s'), 'delete_event', $_SESSION['name'] . ' deleted event ' . $titel, $_SERVER['REMOTE_ADDR'], $_SESSION['user_id']);

        // Redirect to events list
        header('Location: ../veranstaltungen.php?success=' . urlencode('Veranstaltung wurde gelöscht.'));
        exit();
    } catch (Exception $e) {
        if (isset($pdoDel) && $pdoDel->inTransaction()) {
            $pdoDel->rollBack();
        }
        error_log('edit-event.php: Failed to delete event - ' . $e->getMessage());
        $deleteError = 'Fehler beim Löschen: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle form submission (update existing event)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $event_id !== null && !isset($_POST['delete_event'])) {
    // Retrieve and sanitize form inputs
    $titel = trim($_POST['titel'] ?? '');
    $untertitel = trim($_POST['untertitel'] ?? '');
    $datum = $_POST['datum'] ?? null;
    if (isset($_POST['uhrzeit_aktiv']) && ($_POST['uhrzeit_aktiv'] === 'on' || $_POST['uhrzeit_aktiv'] === '1')) {
        $uhrzeit = !empty($_POST['uhrzeit']) ? $_POST['uhrzeit'] : null;
    } else {
        $uhrzeit = null;
    }
    $ort = trim($_POST['ort'] ?? '');
    // Kosten handling: checkbox now named kosten_aktiv
    if (isset($_POST['kosten_aktiv']) && ($_POST['kosten_aktiv'] === 'on' || $_POST['kosten_aktiv'] === '1')) {
        $kosten = !empty($_POST['kosten']) ? trim($_POST['kosten']) : null;
    } else {
        $kosten = null;
    }
    $beschreibung = trim($_POST['beschreibung'] ?? '');
    $zielgruppe = $_POST['zielgruppe'] ?? ($event['zielgruppe'] ?? 'alle');

    // Handle file upload: if a new file provided, store it; otherwise keep existing
    $coverImagePath = $event['banner_image_name'] ?? null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../assets/images/uploads/event_covers/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileTmpPath = $_FILES['cover_image']['tmp_name'];
        $fileName = basename($_FILES['cover_image']['name']);
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = uniqid('event_cover_', true) . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // store relative path matching veranstaltung-erstellen.php
            $newRelative = '../assets/images/uploads/event_covers/' . $newFileName;
            // delete previous file if it exists and is a local file
            if (!empty($event['banner_image_name'])) {
                // resolve stored path to filesystem path
                $projectRoot = dirname(__DIR__, 2);
                $stored = $event['banner_image_name'];
                // strip leading ../ segments
                $relative = preg_replace('#^(\.{1,2}/)+#', '', $stored);
                $oldFull = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
                if (file_exists($oldFull) && is_file($oldFull)) {
                    @unlink($oldFull);
                }
            }
            $coverImagePath = $newRelative;
        }
    }

    // If user requested to remove the existing cover (and didn't upload a new one), delete file and clear path
    if (empty($_FILES['cover_image']['name']) && !empty($_POST['remove_cover']) && $_POST['remove_cover'] === '1') {
        if (!empty($event['banner_image_name'])) {
            $projectRoot = dirname(__DIR__, 2);
            $stored = $event['banner_image_name'];
            $relative = preg_replace('#^(\.{1,2}/)+#', '', $stored);
            $oldFull = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
            if (file_exists($oldFull) && is_file($oldFull)) {
                @unlink($oldFull);
            }
        }
        $coverImagePath = null;
    }

    try {
        // Ensure previous read cursor/connection is closed before writing
        if (isset($stmt) && is_object($stmt) && method_exists($stmt, 'closeCursor')) {
            $stmt->closeCursor();
        }
        if (isset($pdo)) { $pdo = null; }

        $pdoUp = new PDO('sqlite:' . $dbPath);
        $pdoUp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Avoid long locks if DB is busy and enable WAL for better concurrency
        $pdoUp->exec('PRAGMA busy_timeout = 5000');
        $pdoUp->exec('PRAGMA journal_mode = WAL');

        $updateStmt = $pdoUp->prepare('UPDATE veranstaltungen 
            SET titel = :titel,
                beschreibung = :beschreibung,
                datum = :datum,
                zeit = :zeit,
                ort = :ort,
                cost = :cost,
                text = :text,
                zielgruppe = :zielgruppe,
                banner_image_name = :banner
            WHERE id = :id');

        $updateStmt->bindValue(':titel', $titel, PDO::PARAM_STR);
        $updateStmt->bindValue(':beschreibung', $untertitel !== '' ? $untertitel : null, $untertitel !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $updateStmt->bindValue(':datum', $datum ?: null, $datum ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $updateStmt->bindValue(':zeit', $uhrzeit ?: null, $uhrzeit ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $updateStmt->bindValue(':ort', $ort, PDO::PARAM_STR);
        $updateStmt->bindValue(':cost', $kosten !== null && $kosten !== '' ? $kosten : null, ($kosten !== null && $kosten !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $updateStmt->bindValue(':text', $beschreibung, PDO::PARAM_STR);
        $updateStmt->bindValue(':zielgruppe', $zielgruppe, PDO::PARAM_STR);
        $updateStmt->bindValue(':banner', $coverImagePath !== null && $coverImagePath !== '' ? $coverImagePath : null, ($coverImagePath !== null && $coverImagePath !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $updateStmt->bindValue(':id', (int)$event_id, PDO::PARAM_INT);
        
        // Execute inside a transaction for atomicity
        $pdoUp->beginTransaction();
        $updateStmt->execute();
        $pdoUp->commit();
        logAction(date('Y-m-d H:i:s'), 'edit_event', $_SESSION['name'] . ' edited event ' . $titel, $_SERVER['REMOTE_ADDR'], $_SESSION['user_id']);

        // After updating, redirect to the event view page
        header('Location: ../event.php?id=' . urlencode((string)$event_id) . '&success=' . urlencode('Veranstaltung wurde erfolgreich bearbeitet.'));
        exit();
    } catch (Exception $e) {
        if (isset($pdoUp) && $pdoUp->inTransaction()) {
            $pdoUp->rollBack();
        }
        error_log('edit-event.php: Failed to update event - ' . $e->getMessage());
        $updateError = 'Fehler beim Speichern: ' . htmlspecialchars($e->getMessage());
        // fall through to re-render the form with submitted values and an error
    }
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veranstaltung bearbeiten | Plänitz-Leddin</title>
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
        <h1>Veranstaltung bearbeiten</h1>
    </div>
    <div id="main">
        <div id="back-button" onclick="history.back()">
            <span class="material-symbols-outlined">arrow_back</span>
            <span>Zurück</span>
        </div>
        <?php if (!empty($updateError)): ?>
            <div class="error-message" style="color: var(--danger, #b00020); margin: 8px 0 16px;">
                <?php echo $updateError; ?>
            </div>
        <?php endif; ?>
        <form id="event-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . urlencode((string)$event_id)); ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_id); ?>">
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
                <input type="text" id="titel" name="titel" required value="<?php echo htmlspecialchars($event['titel'] ?? ''); ?>">
            </div>
            <div class="form-row">
                <?php
                    // prepare inline style for existing banner image if present
                    $dropStyle = '';
                    $dropClass = 'center';
                    if (!empty($event['banner_image_name'])) {
                        $raw = $event['banner_image_name'];
                        $bannerUrl = $raw;
                        // absolute or root-relative URLs are used as-is
                        if (strpos($raw, 'http://') === 0 || strpos($raw, 'https://') === 0 || strpos($raw, '/') === 0) {
                            $bannerUrl = $raw;
                        } elseif (strpos($raw, '../../') === 0) {
                            // already reaches up two levels -> use as-is
                            $bannerUrl = $raw;
                        } elseif (strpos($raw, '../') === 0) {
                            // stored as '../assets/...' (works from pages/). From pages/internes we need one more '../'
                            $bannerUrl = '../' . $raw;
                        } elseif (strpos($raw, './') === 0) {
                            $bannerUrl = '../' . substr($raw, 2);
                        } else {
                            // fallback: assume path relative to project root
                            $bannerUrl = '../../' . ltrim($raw, '/');
                        }

                        $dropStyle = 'style="background-image: url(\'' . htmlspecialchars($bannerUrl) . '\'); background-size: cover; background-position: center;"';
                        $dropClass .= ' has-preview';
                    }
                ?>
                <label id="drop-zone" class="<?php echo $dropClass; ?>" <?php echo $dropStyle; ?>>
                    <div id="first-text" class="center">
                        <span class="material-symbols-outlined">image_arrow_up</span>
                        <span>Cover-Bild hochladen oder hier ablegen</span>
                    </div>
                    <input type="file" id="file-input" name="cover_image" accept="image/*" />
                    <?php if (!empty($event['banner_image_name'])): ?>
                        <input type="hidden" name="existing_cover_image" value="<?php echo htmlspecialchars($bannerUrl ?? $event['banner_image_name']); ?>">
                    <?php endif; ?>
                    <button id="clear-btn" class="center" type="button" onclick="clearDropzoneBackground()">
                        <span class="material-symbols-outlined">delete</span>
                        Bild entfernen
                    </button>
                </label>
                <script src="../../assets/js/add-event.js"></script>
            </div>
            <div class="form-row">
                <label for="untertitel">Untertitel der Veranstaltung:</label>
                <input type="text" id="untertitel" name="untertitel" required value="<?php echo htmlspecialchars($event['beschreibung'] ?? ''); ?>">
            </div>
            <div class="form-row">
                <label for="datum">Datum:</label>
                <input type="date" id="datum" name="datum" required value="<?php echo htmlspecialchars($event['datum'] ?? ''); ?>">
            </div>
            <div class="form-row">
                <label for="uhrzeit">Uhrzeit:</label>
                <?php
                    $hasTime = !empty($event['zeit']);
                ?>
                <input type="time" id="uhrzeit" name="uhrzeit" <?php echo $hasTime ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars($event['zeit'] ?? ''); ?>" placeholder="18:00:00">
                <span>Uhrzeit hinzufügen
                    <input type="checkbox" id="uhrzeit-aktivieren" name="uhrzeit_aktiv" onchange="document.getElementById('uhrzeit').disabled = !this.checked;" <?php echo $hasTime ? 'checked' : ''; ?> >
                </span>
            </div>
            <div class="form-row">
                <label for="ort">Ort:</label>
                <input type="text" id="ort" name="ort" required placeholder="z.B. Gemeindehaus Leddin" value="<?php echo htmlspecialchars($event['ort'] ?? ''); ?>">
            </div>
            <div class="form-row">
                <label for="kosten">Eintrittskosten:</label>
                <?php $hasCost = isset($event['cost']) && $event['cost'] !== null && $event['cost'] !== '' && $event['cost'] !== 'Frei'; ?>
                <input type="text" id="kosten" name="kosten" <?php echo $hasCost ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars($event['cost'] ?? 'Frei'); ?>">
                <span>Eintritt festlegen
                    <input type="checkbox" id="kosten-aktivieren" name="kosten_aktiv" onchange="document.getElementById('kosten').disabled = !this.checked;" <?php echo $hasCost ? 'checked' : ''; ?>>
                </span>
            </div>
            <div class="form-row">
                <label for="zielgruppe">Zielgruppe:</label>
                <select name="zielgruppe">
                    <?php
                        $options = ['alle' => 'Alle Anwohner', 'Familien' => 'Familien', 'Kinder' => 'Kinder', 'Mitglieder' => 'Mitglieder', 'Rentner' => 'Rentner', 'Vorstand' => 'Vorstand'];
                        $currentZ = $event['zielgruppe'] ?? 'alle';
                        foreach ($options as $val => $label) {
                            $sel = ($val === $currentZ) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($val) . "\" $sel>" . htmlspecialchars($label) . "</option>\n";
                        }
                    ?>
                </select>
            </div>
            <div class="form-row">
                <label for="beschreibung">Beschreibung:</label>
                <textarea id="beschreibung" name="beschreibung" rows="5" required><?php echo htmlspecialchars($event['text'] ?? ''); ?></textarea>
            </div>
            <button type="submit">Veranstaltung speichern</button>
        </form>
        
        <!-- Separate form for deletion with confirmation -->
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . urlencode((string)$event_id)); ?>" onsubmit="return confirm('Möchten Sie diese Veranstaltung wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');" style="margin-top: 20px;">
            <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_id); ?>">
            <input type="hidden" name="delete_event" value="1">
            <button type="submit" style="background-color: var(--danger, #b00020); color: white;" class="center">
                <span class="material-symbols-outlined">delete</span>
                Veranstaltung löschen
            </button>
        </form>
        
        <?php if (!empty($deleteError)): ?>
            <div class="error-message" style="color: var(--danger, #b00020); margin: 16px 0;">
                <?php echo $deleteError; ?>
            </div>
        <?php endif; ?>
    </div>
    <div id="footer" class="center">
        <?php include '../../pages/internes/footer.php'; ?>
    </div>
</body>
</html>