<?php
    require_once '../../includes/session-config.php';
    startSecureSession();

    // Include database helper functions
    require_once '../../includes/db_helper.php';
    require_once '../../includes/config-helper.php';

    $showGIF = filter_var(getConfigValue('show_gif'), FILTER_VALIDATE_BOOLEAN);
    $currentGIF = getConfigValue('current_gif');
    $version = getConfigValue('system_version');
    
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
    <?php
        // load values from config
        // set tabicon:
        $tabicon = getConfigValue('tabicon');
        if ($tabicon) {
            echo '<link rel="icon" type="image/png" href="../../assets/icons/tabicons/' . htmlspecialchars($tabicon) . '">';
        }
        // set primary color:
        $primaryColor = getConfigValue('primary_color');
        if ($primaryColor) {
            echo '<style>:root { --primary-color: ' . htmlspecialchars($primaryColor) . '; }</style>';
        }else {
            echo '<style>:root { --primary-color: #00b300; }</style>'; // default color
        }
        // set logo:
        $logo = getConfigValue('logo');
    ?>
</head>
<body>
    <script src="../../assets/js/config.js"></script>
    <div id="heading">
        <div id="left">
            <a href="../../index.php">
                <img src="<?php echo '../../assets/icons/logos/' . htmlspecialchars($logo); ?>" alt="">
            </a>
        </div>
    </div>
    <div id="main">
        <button id="back-button" class="center" onclick="location.href=window.history.back();">
            <span class="material-symbols-outlined">arrow_back</span> 
            <span>Zurück</span>
        </button>
        <h1>Internes Konfigurationsmenü</h1>
        <hr>
        <div class="maintenance" style="height: auto; margin-bottom: 20px;">
            <div class="mg-left center">
                <span class="material-symbols-outlined">warning</span>
            </div>
            <div class="mg-right" style="margin-bottom: 20px; font-size: 20px;">
                <h2>Achtung</h2>
                <p>
                    Änderungen treten sofort auf der gesamten Website in Kraft und können die Funktionalität der Website beeinträchtigen.<br>
                    Bitte seien Sie vorsichtig und ändern Sie nur Einstellungen, deren Auswirkungen Sie verstehen.
                </p>
            </div>
        </div>

        <!-- ===== VORSTAND SECTION (ALWAYS FIRST) ===== -->
        <?php if ($canEditUI || $canEditConfig) { ?>

        <!-- ===== OBERFLÄCHE / UI SECTION (VORSTAND can edit if allowed) ===== -->
        <h2>Oberfläche</h2>
        <?php if ($canEditUI) { ?>

        <section> <!-- TABICON -->
            <h3>
                Tabicon
                <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Das kleine Bildchen neben dem Seitemnamen im Tab.</span></p>
            </h3>
            <div id="tabicon-drop-zone" class="file-drop-zone">
                <span class="material-symbols-outlined">upload_file</span>
                <p>Datei hierher ziehen oder klicken zum Auswählen</p>
                <input type="file" id="tabicon-file-input" accept="image/*" style="display: none;">
            </div>
            <details>
                <summary>verwendete Tabicons anzeigen <span class="material-symbols-outlined">keyboard_arrow_down</span></summary>
                <div id="tabicon-list">
                    <?php
                        try {
                            $stmt = $config->prepare('SELECT rowid as id, * FROM icons ORDER BY datum DESC');
                            $result = $stmt->execute();
                            
                            $iconCount = 0;
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $iconCount++;
                                $iconPath = '../../assets/icons/tabicons/' . ltrim($row['link'], '/\\');
                                echo '<div class="tabicon-item" data-image-id="' . htmlspecialchars($row['id']) . '">';
                                    echo '<img src="' . htmlspecialchars($iconPath) . '" alt="Tabicon">';
                                    echo '<h4 class="bildname">' . htmlspecialchars(basename($row['name'])) . '</h4>';
                                    if (!empty($row['dimensions'])) {
                                        echo '<p class="dimensions">' . htmlspecialchars($row['dimensions']) . '</p>';
                                    }
                                    if (!empty($row['datum'])) {
                                        $date = date('d.m.Y', strtotime($row['datum']));
                                        echo '<p class="date">' . htmlspecialchars($date) . '</p>';
                                    }
                                echo '</div>';
                            }
                            
                            if ($iconCount === 0) {
                                echo '<p class="no-items">Keine Tabicons gefunden.</p>';
                            }
                        } catch (Exception $e) {
                            error_log('Error loading icons: ' . $e->getMessage());
                            echo '<p class="error">Fehler beim Laden der Tabicons.</p>';
                        }
                    ?>
                </div>
            </details>
        </section>

        <section> <!-- LOGO -->
            <h3>
                Logo
                <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Das Logo am oberen Bildschirmrand.</span></p>
            </h3>
            <div id="logo-drop-zone" class="file-drop-zone">
                <span class="material-symbols-outlined">upload_file</span>
                <p>Datei hierher ziehen oder klicken zum Auswählen</p>
                <input type="file" id="logo-file-input" accept="image/*" style="display: none;">
            </div>
            <details>
                <summary>verwendete Logos anzeigen <span class="material-symbols-outlined">keyboard_arrow_down</span></summary>
                <div id="logos-list">
                    <?php
                        try {
                            $stmt = $config->prepare('SELECT rowid as id, * FROM logos ORDER BY datum DESC');
                            $result = $stmt->execute();
                            
                            $logoCount = 0;
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $logoCount++;
                                $logoPath = '../../assets/icons/logos/' . ltrim($row['link'], '/\\');
                                echo '<div class="logos-item" data-image-id="' . htmlspecialchars($row['id']) . '">';
                                    echo '<img src="' . htmlspecialchars($logoPath) . '" alt="Logo">';
                                    echo '<h4 class="bildname">' . htmlspecialchars(basename($row['name'])) . '</h4>';
                                    if (!empty($row['dimensions'])) {
                                        echo '<p class="dimensions">' . htmlspecialchars($row['dimensions']) . '</p>';
                                    }
                                    if (!empty($row['datum'])) {
                                        $date = date('d.m.Y', strtotime($row['datum']));
                                        echo '<p class="date">' . htmlspecialchars($date) . '</p>';
                                    }
                                echo '</div>';
                            }
                            
                            if ($logoCount === 0) {
                                echo '<p class="no-items">Keine Logos gefunden.</p>';
                            }
                        } catch (Exception $e) {
                            error_log('Error loading logos: ' . $e->getMessage());
                            echo '<p class="error">Fehler beim Laden der Logos.</p>';
                        }
                    ?>
                </div>
            </details>
        </section>

        <section> <!-- BANNER TEXT -->
            <h3>
                Banner Text
                <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Der Spruch auf jeder Seite im Banner.</span></p>
            </h3>
            <form id="banner-text-form">
                <input type="text" id="banner-text-input" placeholder="Geben Sie den Banner-Text hier ein..." value="<?php $bannerText = getConfigValue('banner_text'); echo htmlspecialchars($bannerText !== null ? $bannerText : ''); ?>">
                <button type="submit">Banner-Text speichern</button>
            </form>
            <details>
                <summary>verwendete Texte anzeigen <span class="material-symbols-outlined">keyboard_arrow_down</span></summary>
                <div id="texte-list">
                    <?php
                        try {
                            $stmt = $config->prepare('SELECT * FROM banner_texte ORDER BY datum DESC');
                            $result = $stmt->execute();
                            
                            $textCount = 0;
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $textCount++;
                                echo '<div class="text-item">';
                                    echo '<h4 class="banner-text">' . htmlspecialchars($row['inhalt']) . '</h4>';
                                    if (!empty($row['datum'])) {
                                        $date = date('d.m.Y', strtotime($row['datum']));
                                        echo '<p class="date">' . htmlspecialchars($date) . '</p>';
                                    }
                                echo '</div>';
                            }
                            
                            if ($textCount === 0) {
                                echo '<p class="no-items">Keine Texte gefunden.</p>';
                            }
                        } catch (Exception $e) {
                            error_log('Error loading texts: ' . $e->getMessage());
                            echo '<p class="error">Fehler beim Laden der Texte.</p>';
                        }
                    ?>
                </div>
            </details>
        </section>

        <section> <!-- BANNER IMAGE -->
            <h3>
                Banner-Bild
                <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Das Hintergrundbild des Banners auf der Startseite.</span></p>
            </h3>
            <div id="banner-image-drop-zone" class="file-drop-zone">
                <span class="material-symbols-outlined">upload_file</span>
                <p>Bild hierher ziehen oder klicken zum Auswählen</p>
                <input type="file" id="banner-image-file-input" accept="image/*" style="display: none;">
            </div>
            <details>
                <summary>verwendete Banner-Bilder anzeigen <span class="material-symbols-outlined">keyboard_arrow_down</span></summary>
                <div id="banner-images-list">
                    <?php
                        try {
                            $stmt = $config->prepare('SELECT rowid as id, * FROM banner_images ORDER BY datum DESC');
                            $result = $stmt->execute();
                            
                            $imageCount = 0;
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $imageCount++;
                                $imagePath = '../../assets/images/banner/' . ltrim($row['link'], '/\\');
                                echo '<div class="banner-image-item" data-image-id="' . htmlspecialchars($row['id']) . '">';
                                    echo '<img src="' . htmlspecialchars($imagePath) . '" alt="Banner-Bild">';
                                    if (!empty($row['datum'])) {
                                        $date = date('d.m.Y', strtotime($row['datum']));
                                        echo '<p class="date">' . htmlspecialchars($date) . '</p>';
                                    }
                                echo '</div>';
                            }
                            
                            if ($imageCount === 0) {
                                echo '<p class="no-items">Keine Banner-Bilder gefunden.</p>';
                            }
                        } catch (Exception $e) {
                            error_log('Error loading banner images: ' . $e->getMessage());
                            echo '<p class="error">Fehler beim Laden der Banner-Bilder.</p>';
                        }
                    ?>
                </div>
            </details>
        </section>

        <section> <!-- GIF SECTION -->
            <h3>
                GIFs
                <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Animierte GIFs für die Website.</span></p>
            </h3>
            <div id="gif-drop-zone" class="file-drop-zone">
                <span class="material-symbols-outlined">upload_file</span>
                <p>GIF hierher ziehen oder klicken zum Auswählen</p>
                <input type="file" id="gif-file-input" accept=".gif" style="display: none;">
            </div>
            <details>
                <summary>verwendete GIFs anzeigen <span class="material-symbols-outlined">keyboard_arrow_down</span></summary>
                <div id="gifs-list">
                    <?php
                        try {
                            $stmt = $config->prepare('SELECT rowid as id, * FROM gifs ORDER BY datum DESC');
                            $result = $stmt->execute();
                            
                            $gifCount = 0;
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $gifCount++;
                                $gifPath = '../../assets/images/gifs/' . ltrim($row['link'], '/\\');
                                $season = htmlspecialchars($row['type'] ?? 'Keine Jahreszeit');
                                echo '<div class="gif-item" data-image-id="' . htmlspecialchars($row['id']) . '">';
                                    echo '<img src="' . htmlspecialchars($gifPath) . '" alt="GIF">';
                                    echo '<h4>' . htmlspecialchars(basename($row['name'])) . '</h4>';
                                    echo '<p class="season">Jahreszeit: ' . $season . '</p>';
                                    if (!empty($row['datum'])) {
                                        $date = date('d.m.Y', strtotime($row['datum']));
                                        echo '<p class="date">' . htmlspecialchars($date) . '</p>';
                                    }
                                echo '</div>';
                            }
                            
                            if ($gifCount === 0) {
                                echo '<p class="no-items">Keine GIFs gefunden.</p>';
                            }
                        } catch (Exception $e) {
                            error_log('Error loading gifs: ' . $e->getMessage());
                            echo '<p class="error">Fehler beim Laden der GIFs.</p>';
                        }
                    ?>
                </div>
            </details>
            <div class="config-item">
                <label for="show-gif-toggle">
                    GIFs anzeigen
                    <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Aktivieren oder deaktivieren Sie die Anzeige von GIFs.</span></p>
                </label>
                <label class="switch">
                    <input type="checkbox" id="show-gif-toggle" data-config-key="show_gif" <?php echo filter_var(getConfigValue('show_gif'), FILTER_VALIDATE_BOOLEAN) ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="config-item">
                <label for="auto-rotate-gif-toggle">
                    GIF-Wechsel nach Jahreszeit
                    <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">GIFs werden je nach Jahreszeit automatisch gewechselt.</span></p>
                </label>
                <label class="switch">
                    <input type="checkbox" id="auto-rotate-gif-toggle" data-config-key="auto_rotate_gif" <?php echo filter_var(getConfigValue('auto_rotate_gif'), FILTER_VALIDATE_BOOLEAN) ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </section>

        <?php } else {
            echo '<h3>Sie haben aktuell keine Berechtigung zur Bearbeitung der Benutzeroberfläche.</h3>';
        } ?>

        <section> <!-- COLOR MANAGEMENT -->
            <h3>
                Farben
                <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Verwenden Sie die Farbhistorie, um zwischen gespeicherten Farben zu wechseln.</span></p>
            </h3>
            <div class="color-picker-container">
                <input type="color" id="color-input" value="#<?php $color = getConfigValue('primary_color'); echo htmlspecialchars($color !== null ? ltrim($color, '#') : '007ACC'); ?>">
                <button id="color-save-btn">Farbe speichern</button>
            </div>
            <details>
                <summary>Farbhistorie anzeigen <span class="material-symbols-outlined">keyboard_arrow_down</span></summary>
                <div id="color-history-list">
                    <?php
                        try {
                            $stmt = $config->prepare('SELECT * FROM colors ORDER BY datum DESC LIMIT 20');
                            $result = $stmt->execute();
                            
                            $colorCount = 0;
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $colorCount++;
                                $farbcode = htmlspecialchars($row['farbcode']);
                                $date = date('d.m.Y H:i', strtotime($row['datum']));
                                echo '<div class="color-history-item" style="border-left: 4px solid ' . $farbcode . ';">';
                                    echo '<span class="color-code">' . $farbcode . '</span>';
                                    echo '<span class="color-date">' . $date . '</span>';
                                echo '</div>';
                            }
                            
                            if ($colorCount === 0) {
                                echo '<p class="no-items">Keine Farben in der Historie.</p>';
                            }
                        } catch (Exception $e) {
                            error_log('Error loading colors: ' . $e->getMessage());
                            echo '<p class="error">Fehler beim Laden der Farbhistorie.</p>';
                        }
                    ?>
                </div>
            </details>
        </section>

        <!-- ===== BENACHRICHTIGUNGEN (VORSTAND SECTION) ===== -->
        <h2>Benachrichtigungen</h2>
        <?php if ($canEditConfig) { ?>
        <section> <!-- NOTIFICATIONS -->
            <h3>
                Mitteilungen für Besucher
                <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Erstellen Sie zeitgesteuerte Benachrichtigungen für die Website.<br>Diese werden allen Besuchern auf der Startseite angezeigt.</span></p>
            </h3>
            <form id="notification-form">
                <input type="text" id="notification-heading" placeholder="Überschrift (z.B. 'Mitteilung')">
                <textarea id="notification-text" placeholder="Nachrichtentext..."></textarea>
                <div class="date-time-group">
                    <input type="datetime-local" id="notification-start" placeholder="Startzeit">
                    <input type="datetime-local" id="notification-end" placeholder="Endzeit">
                </div>
                <button type="submit">Benachrichtigung erstellen</button>
            </form>
            <details>
                <summary>Benachrichtigungen anzeigen <span class="material-symbols-outlined">keyboard_arrow_down</span></summary>
                <div id="notification-list">
                    <?php
                        try {
                            $stmt = $config->prepare("SELECT * FROM messages WHERE typ = 'notification' ORDER BY startzeit DESC");
                            $result = $stmt->execute();
                            
                            $notificationCount = 0;
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $notificationCount++;
                                $isActive = strtotime($row['startzeit']) <= time() && time() <= strtotime($row['endzeit']) ? 'active' : 'inactive';
                                echo '<div class="message-item ' . $isActive . '">';
                                    echo '<h4>' . htmlspecialchars($row['heading']) . '</h4>';
                                    echo '<p>' . htmlspecialchars($row['text']) . '</p>';
                                    echo '<span class="message-time">Von: ' . date('d.m.Y H:i', strtotime($row['startzeit'])) . '</span>';
                                    echo '<span class="message-time">Bis: ' . date('d.m.Y H:i', strtotime($row['endzeit'])) . '</span>';
                                    echo '<button class="edit-message-btn" data-message-id="' . htmlspecialchars($row['id']) . '">Bearbeiten</button>';
                                    echo '<button class="delete-message-btn" data-message-id="' . htmlspecialchars($row['id']) . '" data-message-type="notification">Löschen</button>';
                                echo '</div>';
                            }
                            
                            if ($notificationCount === 0) {
                                echo '<p class="no-items">Keine Benachrichtigungen gefunden.</p>';
                            }
                        } catch (Exception $e) {
                            error_log('Error loading notifications: ' . $e->getMessage());
                            echo '<p class="error">Fehler beim Laden der Benachrichtigungen.</p>';
                        }
                    ?>
                </div>
            </details>
        </section>
        <section> <!-- Kosten pro Jahr -->
            <h3>Mitgliedskosten pro Jahr</h3>
            <p>Aktuelle Kosten: <strong><?php echo htmlspecialchars(getConfigValue('kosten_pro_jahr').'€' ?? 'Unbekannt'); ?></strong></p>
            <form id="cost-form">
                <input type="text" id="cost-input" placeholder="Neue Kosten eingeben...">
                <button type="submit">Kosten aktualisieren</button>
            </form>
        </section>
        <?php } else if (hasVorstandRole($userId)) {
            echo '<h3>Sie haben aktuell keine Berechtigung zur Bearbeitung der Benachrichtigungen. Der Admin muss diese zuerst freischalten.</h3>';
        } ?>

        <?php } else {
            echo '<h3>Sie haben nicht die erforderliche Berechtigung zur Bearbeitung der Konfiguration.</h3>';
        } ?>

        <!-- ===== ADMIN SECTION (ONLY FOR ADMINS) ===== -->
        <?php if (hasAdminRole($userId)) { ?>
        <hr>
        <h2>Administration</h2>

        <section> <!-- Version -->
            <h3>Systemversion</h3>
            <p>Aktuelle Version: <strong><?php echo htmlspecialchars(getConfigValue('system_version') ?? 'Unbekannt'); ?></strong></p>
            <form id="version-form">
                <input type="text" id="version-input" placeholder="Neue Version eingeben...">
                <button type="submit">Version aktualisieren</button>
            </form>
        </section>
        <section> <!-- MAINTENANCE MESSAGES -->
            <h3>
                Wartungsmitteilungen
                <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Zeigen Sie Wartungsmitteilungen zu bestimmten Zeiten an.</span></p>
            </h3>
            <form id="maintenance-form">
                <input type="text" id="maintenance-heading" placeholder="Überschrift (z.B. 'Wartungsarbeiten')">
                <textarea id="maintenance-text" placeholder="Nachrichtentext..."></textarea>
                <div class="date-time-group">
                    <input type="datetime-local" id="maintenance-start" placeholder="Startzeit">
                    <input type="datetime-local" id="maintenance-end" placeholder="Endzeit">
                </div>
                <button type="submit">Wartungsmitteilung erstellen</button>
            </form>
            <details>
                <summary>Wartungsmitteilungen anzeigen <span class="material-symbols-outlined">keyboard_arrow_down</span></summary>
                <div id="maintenance-list">
                    <?php
                        try {
                            $stmt = $config->prepare("SELECT * FROM messages WHERE typ = 'maintenance' ORDER BY startzeit DESC");
                            $result = $stmt->execute();
                            
                            $maintenanceCount = 0;
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $maintenanceCount++;
                                $isActive = strtotime($row['startzeit']) <= time() && time() <= strtotime($row['endzeit']) ? 'active' : 'inactive';
                                echo '<div class="message-item ' . $isActive . '">';
                                    echo '<h4>' . htmlspecialchars($row['heading']) . '</h4>';
                                    echo '<p>' . htmlspecialchars($row['text']) . '</p>';
                                    echo '<span class="message-time">Von: ' . date('d.m.Y H:i', strtotime($row['startzeit'])) . '</span>';
                                    echo '<span class="message-time">Bis: ' . date('d.m.Y H:i', strtotime($row['endzeit'])) . '</span>';
                                    echo '<button class="delete-message-btn" data-message-id="' . htmlspecialchars($row['id']) . '" data-message-type="maintenance">Löschen</button>';
                                echo '</div>';
                            }
                            
                            if ($maintenanceCount === 0) {
                                echo '<p class="no-items">Keine Wartungsmitteilungen gefunden.</p>';
                            }
                        } catch (Exception $e) {
                            error_log('Error loading maintenance: ' . $e->getMessage());
                            echo '<p class="error">Fehler beim Laden der Wartungsmitteilungen.</p>';
                        }
                    ?>
                </div>
            </details>
        </section>
        <section> <!-- SYSTEM CONFIGURATION -->
            <h3>Systemkonfiguration</h3>
            
            <div class="config-group">
                <div class="config-item">
                    <label for="show-error-toggle">
                        Wartungsmitteilungen anzeigen
                        <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Zeigt Wartungsmitteilungen auf der Website an.</span></p>
                    </label>
                    <label class="switch">
                        <input type="checkbox" id="show-error-toggle" data-config-key="show_error" <?php echo filter_var(getConfigValue('show_error'), FILTER_VALIDATE_BOOLEAN) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="config-item">
                    <label for="show-notification-toggle">
                        Benachrichtigungen anzeigen
                        <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Zeigt aktive Benachrichtigungen auf der Website an.</span></p>
                    </label>
                    <label class="switch">
                        <input type="checkbox" id="show-notification-toggle" data-config-key="show_notification" <?php echo filter_var(getConfigValue('show_notification'), FILTER_VALIDATE_BOOLEAN) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="config-item">
                    <label for="vorstand-edit-ui-toggle">
                        Vorstand darf Oberfläche bearbeiten
                        <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Erlaubt Vorstandsmitgliedern, Logo, Icon und Banner-Text zu ändern.</span></p>
                    </label>
                    <label class="switch">
                        <input type="checkbox" id="vorstand-edit-ui-toggle" data-config-key="vorstand_can_edit_UI" <?php echo $vorstandCanEditUI ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="config-item">
                    <label for="vorstand-edit-config-toggle">
                        Vorstand darf Konfiguration bearbeiten
                        <p class="info"><span class="material-symbols-outlined">info</span><span class="infotext">Erlaubt Vorstandsmitgliedern, Benachrichtigungen und Wartungsmitteilungen zu verwalten.</span></p>
                    </label>
                    <label class="switch">
                        <input type="checkbox" id="vorstand-edit-config-toggle" data-config-key="vorstand_can_edit_config" <?php echo $vorstandCanEditConfig ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </section>

        <?php } ?>
    </div>
    <div id="footer" class="center">
        <?php
            include __DIR__ . '/footer.php';
        ?>
    </div>
</body>
</html>
