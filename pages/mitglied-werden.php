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
        <div id="left">
            <a href="../index.php">
                <img src="../assets/icons/logo.png" alt="Plänitz-Leddin Logo">
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
        </div>
        <script src="../assets/js/navbar.js"></script>
        <a href="javascript:void(0);" style="font-size:15px;" class="icon" onclick="dreibalkensymbol()">&#9776;</a>
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
            <p>Die Mitgliedschaft in unserem Verein ist kostenlos.</p>
            <br><h3>Wir freuen uns darauf, Sie als neues Mitglied in unserer Gemeinschaft willkommen zu heißen!</h3>
        </div>
        <form id="member-form" method="post">
            <h2>Grunddaten:</h2>
            <div class="row no-flex">
                <label for="anrede">Anrede:</label>
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
            <h2>Möchten Sie und noch etwas mitteilen?</h2>
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
            // Sanitize and validate input data
            $titel = filter_input(INPUT_POST, 'titel', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $anrede = filter_input(INPUT_POST, 'anrede', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $vorname = filter_input(INPUT_POST, 'vorname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $nachname = filter_input(INPUT_POST, 'nachname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $strasse = filter_input(INPUT_POST, 'strasse', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $hausnummer = filter_input(INPUT_POST, 'hausnummer', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $ort = filter_input(INPUT_POST, 'ort', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $plz = filter_input(INPUT_POST, 'plz', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $zusatz = filter_input(INPUT_POST, 'zusatz', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $telefon = filter_input(INPUT_POST, 'telefon', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $mobil = filter_input(INPUT_POST, 'mobil', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $kontakt = filter_input(INPUT_POST, 'kontakt', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $nachricht = filter_input(INPUT_POST, 'nachricht', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $date = date('Y-m-d H:i:s');

            // Check if required fields are filled
            if (true) {
                try {
                    // Save to member.db
                    $db = new SQLite3('../assets/db/member.db');
                    
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