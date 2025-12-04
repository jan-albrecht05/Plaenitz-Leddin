<?php
    require_once '../../includes/session-config.php';
    startSecureSession();

    // Include database helper functions
    require_once '../../includes/db_helper.php';
    require_once '../../includes/config-helper.php';
    
    // read config.db "vorstand_can_edit_UI" and "vorstand_can_edit_config"
    // Normalize config values to booleans (handles "0"/"1", "true"/"false", int/bool)
    $vorstandCanEditUI = filter_var(getConfigValue('vorstand_can_edit_UI'), FILTER_VALIDATE_BOOLEAN);
    $vorstandCanEditConfig = filter_var(getConfigValue('vorstand_can_edit_config'), FILTER_VALIDATE_BOOLEAN);

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];

    // ensure we check both roles explicitly (avoids dependency on helper function presence)
    if (!(hasAdminRole($userId) || hasVorstandRole($userId))) {
        // User doesn't have required role, redirect to login
        session_destroy();
        header("Location: ../login.php?error=" . urlencode("Sie haben keine Berechtigung für diese Seite."));
        exit();
    }

    // default flags (safe initialization)
    $canEditUI = false;
    $canEditConfig = false;

    // Final permission flags (admins always allowed, vorstand only when config allows)
    $canEditUI = hasAdminRole($userId) || (hasVorstandRole($userId) && $vorstandCanEditUI);
    $canEditConfig = hasAdminRole($userId) || (hasVorstandRole($userId) && $vorstandCanEditConfig);

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
    <script src="../../assets/js/config.js"></script>
    <div id="heading">
        <div id="left">
            <a href="../../index.php">
                <img src="../../assets/icons/logo.png" alt="">
            </a>
        </div>
    </div>
    <div id="main">
        <h1>Internes Konfigurationsmenü</h1>
        <hr>
        <h2>Oberfläche</h2>
        <?php
            // if user is allowed to edit UI
            if ($canEditUI) {
        ?>
        <section> <!-- ICONS -->
            <h3>
                Tabicon
                <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Das kleine Bildchen neben dem Seitemnamen im Tab.</span></p>
            </h3>
            <div id="tabicon-drop-zone" class="file-drop-zone center">
                <span class="material-symbols-outlined">upload_file</span>
                <p>Datei hierher ziehen oder klicken zum Auswählen</p>
                <input type="file" id="tabicon-file-input" accept="image/*" style="display: none;">
            </div>
            <details>
                <summary>verwendete Tabicons anzeigen <span class="material-symbols-outlined">keyboard_arrow_down</span></summary>
                <div id="tabicon-list">
                    <?php
                        // Load all icons from config.db icons table
                        try {
                            $stmt = $config->prepare('SELECT * FROM icons ORDER BY datum DESC');
                            $result = $stmt->execute();
                            
                            $iconCount = 0;
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $iconCount++;
                                // Build relative path from root (config.php is in pages/internes/)
                                $iconPath = '../../assets/icons/tabicons/' . ltrim($row['link'], '/\\');
                                echo '<div class="tabicon-item">';
                                    echo '<img src="' . htmlspecialchars($iconPath) . '" alt="Tabicon">';
                                    echo '<h4 class="bildname">' . htmlspecialchars(basename($row['name'])) . '</h4>';
                                    if (!empty($row['dimensions'])) {
                                        echo '<p class="dimensions">' . htmlspecialchars($row['dimensions']) . '</p>';
                                    }
                                    // Display upload date if available
                                    if (!empty($row['datum'])) {
                                        $date = date('d.m.Y', strtotime($row['datum']));
                                        echo '<p class="date">' . htmlspecialchars($date) . '</p>';
                                    }
                                echo '</div>';
                            }
                            
                            // Show message if no icons found
                            if ($iconCount === 0) {
                                echo '<p class="no-icons">Keine Tabicons gefunden.</p>';
                            }
                        } catch (Exception $e) {
                            error_log('Error loading icons from config.db: ' . $e->getMessage());
                            echo '<p class="error">Fehler beim Laden der Tabicons.</p>';
                        }
                    ?>
                </div>
            </details>
        </section>
        <section> <!-- LOGOS -->
            <h3>
                Logo
                <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Das Logo am oberen Bildschirmrand.</span></p>
            </h3>
            <div id="logo-drop-zone" class="file-drop-zone center">
                <span class="material-symbols-outlined">upload_file</span>
                <p>Datei hierher ziehen oder klicken zum Auswählen</p>
                <input type="file" id="logo-file-input" accept="image/*" style="display: none;">
            </div>
            <details>
                <summary>verwendete Logos anzeigen <span class="material-symbols-outlined">keyboard_arrow_down</span></summary>
                <div id="logos-list">
                    <?php
                        // Load all logos from config.db logos table
                        try {
                            $stmt = $config->prepare('SELECT * FROM logos ORDER BY datum DESC');
                            $result = $stmt->execute();
                            
                            $logoCount = 0;
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $logoCount++;
                                // Build relative path from root (config.php is in pages/internes/)
                                $iconPath = '../../assets/icons/logos/' . ltrim($row['link'], '/\\');
                                echo '<div class="logos-item">';
                                    echo '<img src="' . htmlspecialchars($iconPath) . '" alt="Logo">';
                                    echo '<h4 class="bildname">' . htmlspecialchars(basename($row['name'])) . '</h4>';
                                    if (!empty($row['dimensions'])) {
                                        echo '<p class="dimensions">' . htmlspecialchars($row['dimensions']) . '</p>';
                                    }
                                    // Display upload date if available
                                    if (!empty($row['datum'])) {
                                        $date = date('d.m.Y', strtotime($row['datum']));
                                        echo '<p class="date">' . htmlspecialchars($date) . '</p>';
                                    }
                                echo '</div>';
                            }
                            
                            // Show message if no logos found
                            if ($logoCount === 0) {
                                echo '<p class="no-logos">Keine Logos gefunden.</p>';
                            }
                        } catch (Exception $e) {
                            error_log('Error loading icons from config.db: ' . $e->getMessage());
                            echo '<p class="error">Fehler beim Laden der Tabicons.</p>';
                        }
                    ?>
                </div>
            </details>
        </section>
        <section> <!-- Banner-TEXT -->
            <h3>
                Banner Text
                <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Der Spruch auf jeder Seite im Banner.</span></p>
            </h3>
            <form id="banner-text-form" prevent-default="true">
                <input type="text" id="banner-text-input" placeholder="Geben Sie den Banner-Text hier ein..." value="<?php $bannerText = getConfigValue('banner_text');echo htmlspecialchars($bannerText !== null ? $bannerText : ''); ?>">
                <button type="submit">Banner-Text speichern</button>
            </form>
            <details>
                <summary>verwendete Texte anzeigen <span class="material-symbols-outlined">keyboard_arrow_down</span></summary>
                <div id="texte-list">
                    <?php
                        // Load all Texte from config.db logos table
                        try {
                            $stmt = $config->prepare('SELECT * FROM banner_texte ORDER BY datum DESC');
                            $result = $stmt->execute();
                            
                            $logoCount = 0;
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $logoCount++;
                                echo '<div class="text-item">';
                                    echo '<h4 class="banner-text">' . htmlspecialchars($row['inhalt']) . '</h4>';
                                    // Display creation date if available
                                    if (!empty($row['datum'])) {
                                        $date = date('d.m.Y', strtotime($row['datum']));
                                        echo '<p class="date">' . htmlspecialchars($date) . '</p>';
                                    }
                                echo '</div>';
                            }
                            
                            // Show message if no logos found
                            if ($logoCount === 0) {
                                echo '<p class="no-logos">Keine Logos gefunden.</p>';
                            }
                        } catch (Exception $e) {
                            error_log('Error loading icons from config.db: ' . $e->getMessage());
                            echo '<p class="error">Fehler beim Laden der Tabicons.</p>';
                        }
                    ?>
                </div>
            </details>
        </section>
        <?php
            }
            else{
                echo '<h3>Sie haben aktuell keine Berechtigung zur Bearbeitung der Benutzeroberfläche.</h3>';
            };
        ?>
    </div>
    <div id="footer" class="center">
        <?php
            include __DIR__ . '/footer.php';
        ?>
    </div>
</body>
</html>