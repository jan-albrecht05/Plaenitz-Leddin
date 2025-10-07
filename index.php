<?php
session_start();
if(isset($_SESSION['user_id'])) {
    // User is logged in
    $user_id = $_SESSION['user_id'];
    $is_admin = $_SESSION['is_admin'] ?? false;
} else {
    // User is not logged in
    $user_id = null;
    $is_admin = false;
}
?>
<!DOCTYPE html>
<html lang="en">
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
            $is_admin = true;
            if($is_admin) {
                echo '<div id="admin-buttons">
                        <a id="admin-button" onclick="location.href=\'pages/internes/admin.php\'">
                            <span class="material-symbols-outlined">admin_panel_settings</span>
                        </a>
                        <a id="notifications-button" onclick="showNotifications()">
                            <span class="material-symbols-outlined">notifications</span>
                            <span id="notification-indicator"></span>
                        </a>
                    </div>';
            }
            ?>
        </div>
        <script src="assets/js/navbar.js"></script>
        <a href="javascript:void(0);" style="font-size:15px;" class="icon" onclick="dreibalkensymbol()">&#9776;</a>
    </div>
    <div class="banner">
        <h1>Zwei Dörfer, eine Gemeinschaft</h1>
    </div>
    <div id="main">
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
                // get Veranstaltungen from SQL database
                ?>
                <ul>
                    <li>Osterfeuer - 20. April 2026</li>
                    <li>Sommerfest - 15. Juni 2026</li>
                    <li>Herbstmarkt - 10. Oktober 2026</li>
                    <li>Weihnachtsmarkt - 5. Dezember 2026</li>
                </ul>
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
            <a href="pages/internes/admin.php">Admin-Anmeldung<span class="material-symbols-outlined">open_in_new</span></a>
        </div>
        <div id="middle">
            <span>&copy; 2025 Plänitz-Leddin. Alle Rechte vorbehalten.</span>
            <span>Version 0.5</span>
        </div>
        <div id="right">
            <a href="pages/kontakt.php">Kontakt<span class="material-symbols-outlined">open_in_new</span></a>
            <a href="pages/datenschutz.php">Datenschutz<span class="material-symbols-outlined">open_in_new</span></a>
            <a href="pages/impressum.php">Impressum<span class="material-symbols-outlined">open_in_new</span></a>
        </div>
    </div>
</body>
</html>