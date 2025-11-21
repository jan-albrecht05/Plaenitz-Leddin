<?php
    session_start();

    // Include database helper functions
    require_once '../../includes/db_helper.php';

    // connection to config.db

    // read config.db "vorstand_can_edit_UI" and "vorstand_can_edit_config"
    /*$vorstandCanEditUI = getConfigValue('vorstand_can_edit_UI');
    $vorstandCanEditConfig = getConfigValue('vorstand_can_edit_config');
    $vorstandCanEditUI = ($vorstandCanEditUI === '1');
    $vorstandCanEditConfig = ($vorstandCanEditConfig === '1');*/

    // Check if user is logged in and is admin
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

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
    <title>config | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../../assets/css/root.css">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/config.css">
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
    </div>
    <div id="main">
        <h1>Internes Konfigurationsmenü</h1>
        <?php
            // if user is Vorstand and vorstandCanEditUI
            if (isUserVorstand($userId) && $vorstandCanEditUI) {
        ?>
        <h2>Oberfläche</h2>
        <section>
            <h3>Tabicon</h3>
            <div id="tabicon-drop-zone" class="file-drop-zone center">
                <span class="material-symbols-outlined">upload_file</span>
                <p>Datei hierher ziehen oder klicken zum Auswählen</p>
                <input type="file" id="tabicon-file-input" accept="image/*" style="display: none;">
            </div>
            <details>
                <summary>verwendete Tabicons anzeigen</summary>
                <div id="tabicon-list">
                    <?php
                        $tabiconDir = __DIR__ . '/../../assets/icons/tabicons/';
                        // use the table tabicons from config.db
                    ?>
            </details>
        </section>
        <?php
            }
            else{
                echo '<h2>Sie haben aktuell keine Berechtigung zur Bearbeitung der Benutzeroberfläche.</h2>';
            };
        ?>
    </div>
    <div id="footer">
        <?php
            include '../../footer.php';
        ?>
    </div>
</body>
</html>