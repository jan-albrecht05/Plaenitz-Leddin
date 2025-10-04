<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veranstaltungen | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/main.css">
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
                <a href="../uber-uns.php">Über uns</a>
                <span class="line"></span>
            </div>
            <div class="link" id="veranstaltungen">
                <a href="#">Veranstaltungen</a>
                <span class="line"></span>
            </div>
            <div class="link" id="kontakt">
                <a href="../kontakt.php">Kontakt</a>
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
                <script src="../assets/js/mode.js"></script>
            </div>
            <a href="../pages/internes/admin.php">Admin-Anmeldung<span class="material-symbols-outlined">open_in_new</span></a>
        </div>
        <div id="middle">
             <p>&copy; 2024 Plänitz-Leddin. Alle Rechte vorbehalten.</p>
        </div>
        <div id="right">
            <a href="../pages/datenschutz.php">Datenschutz<span class="material-symbols-outlined">open_in_new</span></a>
            <a href="../pages/impressum.php">Impressum<span class="material-symbols-outlined">open_in_new</span></a>
            <a href="../pages/kontakt.php">Kontakt<span class="material-symbols-outlined">open_in_new</span></a>
        </div>
    </div>
</body>
</html>