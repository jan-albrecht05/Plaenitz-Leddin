<?php
session_start();

// Include database helper functions
require_once '../../includes/db_helper.php';

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
                    <input type="file" id="file-input" accept="image/*" />
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
                <input type="time" id="uhrzeit" name="uhrzeit" disabled value="18:00">
                <span>Uhrzeit hinzufügen
                    <input type="checkbox" id="uhrzeit-aktivieren" onchange="document.getElementById('uhrzeit').disabled = !this.checked;">
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
                <select>
                    <option value="alle" selected>Alle Dorfbewohner</option>
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