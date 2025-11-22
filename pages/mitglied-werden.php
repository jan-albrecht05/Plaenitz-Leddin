<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitglied werden | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/mitglied-werden.css">
    <link rel="stylesheet" href="../assets/css/heading.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
</head>
<body>
    <div id="heading">
        <?php include '../pages/heading.php'; ?>    
    </div>
    <div class="banner">
        <h1>Zwei Dörfer, eine Gemeinschaft</h1>
    </div>
    <div id="main">
        <div class="content">
            <h1>Mitglied werden</h1>
            <p>Wir freuen uns über Ihr Interesse, Mitglied in unserem Verein zu werden! Um den Beitrittsprozess so einfach wie möglich zu gestalten, haben wir ein online-Formular für Sie erstellt</p>
            <p>Bitte füllen Sie das folgende Formular aus, um Ihre Mitgliedschaft zu beantragen. Nach dem Absenden des Formulars wird sich ein Vorstandsmitglied mit Ihnen in Verbindung setzen, um den weiteren Ablauf zu besprechen.</p>
            <p>Falls Sie Fragen zum Beitrittsprozess oder zu unserem Verein haben, zögern Sie nicht, uns über das <a href="../pages/kontakt.php">Kontaktformular</a> zu erreichen.</p>
            <h2>Ihre Vorteile im Verein</h2>
            <p>Als Mitglied unseres Vereins profitieren Sie von zahlreichen Vorteilen:</p>
            <ul>
                <li>Teilnahme an exklusiven Veranstaltungen und Workshops</li>
                <li>Regelmäßige Informationen über Neuigkeiten und Entwicklungen im Verein</li>
                <li>Unterstützung bei Ihren Projekten und Ideen</li>
            </ul>
            <h2>Kosten</h2>
            <p>Die Mitgliedschaft in unserem Verein beträgt 24 Euro pro Jahr.<br>Der Beitrag muss jährlich bis zum 31. März auf dem Vereinskonto eingegangen sein.</p>
            <br><h3>Wir freuen uns darauf, Sie als neues Mitglied in unserer Gemeinschaft willkommen zu heißen!</h3>
        </div>
        <form id="member-form" method="post">
            <h2>Grunddaten:</h2>
            <div class="row no-flex">
                <label for="anrede"><span class="stern">*</span>Anrede:</label>
                <input type="radio" id="herr" name="anrede" value="Herr" required>
                <label for="herr">Herr</label>
                <input type="radio" id="frau" name="anrede" value="Frau" required>
                <label for="frau">Frau</label>
                <input type="radio" id="divers" name="anrede" value="Divers" required>
                <label for="divers">Divers</label>
            </div>
            <div class="row no-flex">
                <div class="half">
                    <label for="titel">Titel:</label>
                    <div class="input-wrapper">
                        <input type="text" id="titel" name="titel">
                        <span class="input-checkmark material-symbols-outlined">check</span>
                    </div>
                </div>
                <div class="half">
                    <label for="vorname"><span class="stern">*</span>Vorname:</label>
                    <div class="input-wrapper">
                        <input type="text" id="vorname" name="vorname" required>
                        <span class="input-checkmark material-symbols-outlined">check</span>
                    </div>
                </div>
            </div>
            <div class="row">
                <label for="nachname"><span class="stern">*</span>Nachname:</label>
                <div class="input-wrapper">
                    <input type="text" id="nachname" name="nachname" required>
                    <span class="input-checkmark material-symbols-outlined">check</span>
                </div>
            </div>
            <h2>Adresse:</h2>
            <div class="location-section">
                <button id="auto-fill-address" type="button" onclick="autoFillAddress()" class="location-button">
                    <span class="material-symbols-outlined">distance</span>
                    <span class="info-text">Meinen Standort verwenden</span>
                </button>
            </div>
            <div class="row no-flex">
                <div class="half">
                    <label for="strasse"><span class="stern">*</span>Straße:</label>
                    <div class="input-wrapper">
                        <input type="text" id="strasse" name="strasse" required>
                        <span class="input-checkmark material-symbols-outlined">check</span>
                    </div>
                </div>
                <div class="half hausnummer">
                    <label for="hausnummer"><span class="stern">*</span>Hausnummer:</label>
                    <div class="input-wrapper">
                        <input type="text" id="hausnummer" name="hausnummer" required>
                        <span class="input-checkmark material-symbols-outlined">check</span>
                    </div>
                </div>
            </div>
            <div class="row no-flex">
                <div class="half plz">
                    <label for="plz"><span class="stern">*</span>PLZ:</label>
                    <div class="input-wrapper">
                        <input type="text" id="plz" name="plz" required>
                        <span class="input-checkmark material-symbols-outlined">check</span>
                    </div>
                </div>
                <div class="half">
                    <label for="ort"><span class="stern">*</span>Ort:</label>
                    <div class="input-wrapper">
                        <input type="text" id="ort" name="ort" required>
                        <span class="input-checkmark material-symbols-outlined">check</span>
                    </div>
                </div>
            </div>
            <div class="row">
                <label for="zusatz">Adresszusatz:</label>
                <div class="input-wrapper">
                    <input type="text" id="zusatz" name="zusatz">
                    <span class="input-checkmark material-symbols-outlined">check</span>
                </div>
            </div>
            <h2>Kontaktdaten:</h2>
            <div class="row">
                <label for="email">E-Mail:</label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email">
                    <span class="input-checkmark material-symbols-outlined">check</span>
                </div>
            </div>
            <div class="row">
                <label for="telefon"></span>Festnetz:</label>
                <div class="input-wrapper">
                    <input type="tel" id="telefon" name="telefon">
                    <span class="input-checkmark material-symbols-outlined">check</span>
                </div>
            </div>
            <div class="row">
                <label for="mobil">Mobil:</label>
                <div class="input-wrapper">
                    <input type="tel" id="mobil" name="mobil" value="+49 ">
                    <span class="input-checkmark material-symbols-outlined">check</span>
                </div>
            </div>
            <h3><span class="stern">*</span>Wie können wie Sie am besten erreichen?</h3>
            <div class="row no-flex" id="radios">
                <input type="radio" id="kontakt-email" name="kontakt" value="email">
                <nobr><label for="kontakt-email">
                    <span class="material-symbols-outlined">mail</span>
                    E-Mail</label></nobr>
                <input type="radio" id="kontakt-telefon" name="kontakt" value="telefon">
                <nobr><label for="kontakt-telefon">
                    <span class="material-symbols-outlined">phone</span>
                    Festnetz</label></nobr>
                <input type="radio" id="kontakt-mobil" name="kontakt" value="mobil">
                <nobr><label for="kontakt-mobil">
                    <span class="material-symbols-outlined">smartphone</span>
                    Mobil</label></nobr>
                <input type="radio" id="post" name="kontakt" value="post">
                <nobr><label for="post">
                    <span class="material-symbols-outlined">markunread_mailbox</span>
                    Post</label></nobr>
            </div>
            <h2>Möchten Sie uns noch etwas mitteilen?</h2>
            <div class="row">
                <div class="input-wrapper">
                    <textarea id="nachricht" name="nachricht" rows="4" placeholder="Ihre Nachricht..."></textarea>
                    <span class="input-checkmark material-symbols-outlined">check</span>
                </div>
            </div>
            <div class="row no-flex">
                <input type="checkbox" id="datenschutz" name="datenschutz" required>
                <label for="datenschutz">
                    <span class="stern">*</span>
                    Ich habe die <a href="../pages/datenschutz.php" target="_blank" rel="noopener noreferrer">Datenschutzerklärung </a> gelesen und akzeptiere sie.</label>
            </div>
            <div class="row no-flex" id="form-buttons">
                <button type="reset" id="reset-button" onclick="resetForm()">
                    <span class="material-symbols-outlined">refresh</span>
                    Formular zurücksetzen
                </button>
                <button type="submit" id="submit-button">
                    <span class="material-symbols-outlined">send</span>
                    Formular absenden
                </button>
                <span id="form-status"></span>
            </div>
            <span class="stern">*</span> Pflichtfelder
        </form>
        <?php
        // PHP code to handle form submission
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            // Sanitize and validate input data - using FILTER_UNSAFE_RAW to preserve UTF-8 characters
            $titel = filter_input(INPUT_POST, 'titel', FILTER_UNSAFE_RAW);
            $anrede = filter_input(INPUT_POST, 'anrede', FILTER_UNSAFE_RAW);
            $vorname = filter_input(INPUT_POST, 'vorname', FILTER_UNSAFE_RAW);
            $nachname = filter_input(INPUT_POST, 'nachname', FILTER_UNSAFE_RAW);
            $strasse = filter_input(INPUT_POST, 'strasse', FILTER_UNSAFE_RAW);
            $hausnummer = filter_input(INPUT_POST, 'hausnummer', FILTER_UNSAFE_RAW);
            $ort = filter_input(INPUT_POST, 'ort', FILTER_UNSAFE_RAW);
            $plz = filter_input(INPUT_POST, 'plz', FILTER_UNSAFE_RAW);
            $zusatz = filter_input(INPUT_POST, 'zusatz', FILTER_UNSAFE_RAW);
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $telefon = filter_input(INPUT_POST, 'telefon', FILTER_UNSAFE_RAW);
            $mobil = filter_input(INPUT_POST, 'mobil', FILTER_UNSAFE_RAW);
            $kontakt = filter_input(INPUT_POST, 'kontakt', FILTER_UNSAFE_RAW);
            $nachricht = filter_input(INPUT_POST, 'nachricht', FILTER_UNSAFE_RAW);
            $date = gmdate('Y-m-d H:i:s'); // Use UTC time for consistency

            // Check if required fields are filled
            if (true) {
                try {
                    // Save to member.db
                    $db = new SQLite3('../assets/db/member.db');
                    // Set UTF-8 encoding for proper handling of special characters (ß, ä, ö, ü, etc.)
                    $db->exec("PRAGMA encoding = 'UTF-8'");
                    
                    $stmt = $db->prepare('INSERT INTO mitglieder (anrede, titel, name, nachname, strasse, hausnummer, ort, plz, adresszusatz, e_mail, festnetz, mobilnummer, info, bevorzugte_kommunikation, join_date) 
                                         VALUES (:anrede, :titel, :vorname, :nachname, :strasse, :hausnummer, :ort, :plz, :zusatz, :email, :telefon, :mobil, :nachricht, :kontakt, :date)');
                    
                    // Bind parameters
                    $stmt->bindValue(':anrede', $anrede, SQLITE3_TEXT);
                    $stmt->bindValue(':titel', $titel, SQLITE3_TEXT);
                    $stmt->bindValue(':vorname', $vorname, SQLITE3_TEXT);
                    $stmt->bindValue(':nachname', $nachname, SQLITE3_TEXT);
                    $stmt->bindValue(':strasse', $strasse, SQLITE3_TEXT);
                    $stmt->bindValue(':hausnummer', $hausnummer, SQLITE3_TEXT);
                    $stmt->bindValue(':ort', $ort, SQLITE3_TEXT);
                    $stmt->bindValue(':plz', $plz, SQLITE3_TEXT);
                    $stmt->bindValue(':zusatz', $zusatz, SQLITE3_TEXT);
                    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                    $stmt->bindValue(':telefon', $telefon, SQLITE3_TEXT);
                    $stmt->bindValue(':mobil', $mobil, SQLITE3_TEXT);
                    $stmt->bindValue(':nachricht', $nachricht, SQLITE3_TEXT);
                    $stmt->bindValue(':kontakt', $kontakt, SQLITE3_TEXT);
                    $stmt->bindValue(':date', $date, SQLITE3_TEXT);
                    // Execute the statement
                    $result = $stmt->execute();
                    // positive feedback
                    if ($result) {
                        echo '<div class="success-message"><p>Vielen Dank für Ihre Mitgliedschaftsanfrage! Ein Vorstandsmitglied wird sich bald bei Ihnen melden.</p></div>';
                    } else {
                        echo '<div class="error-message"><p>Es gab einen Fehler beim Speichern Ihrer Daten. Bitte versuchen Sie es erneut.</p></div>';
                    }
                    $db->close();
                } catch (Exception $e) {
                    error_log("Database error in mitglied-werden.php: " . $e->getMessage());
                    echo '<div class="error-message"><p>Es gab einen technischen Fehler. Bitte versuchen Sie es später erneut oder kontaktieren Sie uns direkt.</p></div>';
                }
            } /*else {
                echo '<div class="error-message"><p>Bitte füllen Sie alle Pflichtfelder aus.</p></div>';
            }*/
        }
        ?>
    </div>
    <div id="footer" class="center">
        <?php include '../pages/footer.php'; ?>
    </div>
    <script src="../assets/js/GPS2Adress.js"></script>
    <script src="../assets/js/ticks.js"></script>
</body>
</html>