<?php
require_once '..//includes/config-helper.php';

// Get config values
$tabicon = getConfigValue('tabicon') ?? 'PL1.png';
$logo = getConfigValue('logo') ?? 'logo.png';
$bannerImage = getConfigValue('banner_image') ?? '';
$bannerText = getConfigValue('banner_text') ?? 'Zwei Dörfer, eine Gemeinschaft';
$primaryColor = getConfigValue('primary_color') ?? '#4a6fa5';
$showNotification = filter_var(getConfigValue('show_notification'), FILTER_VALIDATE_BOOLEAN);
$showError = filter_var(getConfigValue('show_error'), FILTER_VALIDATE_BOOLEAN);
$showGIF = filter_var(getConfigValue('show_gif'), FILTER_VALIDATE_BOOLEAN);
$currentGIF = getConfigValue('current_gif');
$version = getConfigValue('system_version');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Über uns | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/uber-uns.css">
    <link rel="stylesheet" href="../assets/css/heading.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <style>
        #uber-uns a{
            color: var(--primary-color);
        }
        #uber-uns .line{
            background-color: var(--primary-color);
            width: 100%;
        }
        :root{
            --primary-color: <?php echo htmlspecialchars($primaryColor); ?>;
        }
    </style>
</head>
<body>
    <div id="heading">
        <?php include '../pages/heading.php'; ?>
    </div>
    <div class="banner" <?php if (!empty($bannerImage)): ?>style="background-image: url('../assets/images/banner/<?php echo htmlspecialchars($bannerImage); ?>');"<?php endif; ?>>
        <h1><?php echo htmlspecialchars($bannerText); ?></h1>
    </div>
    <div id="main">
        <p id="einführung">
            Wir sind der Verein <b>Gemeinsam für Plänitz-Leddin e.V.</b>, eine Gemeinschaft von engagierten Einwohnern der Dörfer Plänitz und Leddin. Unser Ziel ist es, das Dorfleben zu fördern, Traditionen zu bewahren und gemeinsame Aktivitäten zu organisieren, die das Miteinander stärken.
        </p>
        <div class="person">
            <div class="bildseite">
                <div class="wrapper">
                    <div class="blob shape-1 green"></div> 
                    <img src="../assets/images/team/jan.jpg" alt="">
                </div>
            </div>
            <div class="textseite">
                <h1 class="name">Jan Albrecht</h1>
                <div class="info">
                    <span class="material-symbols-outlined">location_on</span>
                    <span class="ort">Berlin</span>
                    <span class="material-symbols-outlined">person</span>
                    <span class="datum">Admin | Web-Dev</span>
                </div>
                <p class="beschreibung">
                    Jan ist ehrenamtliches Mitglied unseres Vereins und engagiert sich besonders in der Online-Präsenz. Als Admin dieser Seite sorgt er dafür, dass unsere Inhalte stets aktuell bleiben.
                </p>
            </div>
        </div>
        <div class="person">
            <div class="bildseite">
                <div class="wrapper">
                    <div class="blob shape-1 pink"></div> 
                    <img src="../assets/images/team/jan.jpg" alt="">
                </div>
            </div>
            <div class="textseite">
                <h1 class="name">Jan Albrecht</h1>
                <div class="info">
                    <span class="material-symbols-outlined">location_on</span>
                    <span class="ort">Berlin</span>
                    <span class="material-symbols-outlined">person</span>
                    <span class="datum">Admin | Web-Dev</span>
                </div>
                <p class="beschreibung">
                    Jan ist ehrenamtliches Mitglied unseres Vereins und engagiert sich besonders in der Online-Präsenz. Als Admin dieser Seite sorgt er dafür, dass unsere Inhalte stets aktuell bleiben.
                </p>
            </div>
        </div>
        
    </div>
    <div id="footer" class="center">
        <?php include '../pages/footer.php'; ?>
    </div>
</body>
</html>