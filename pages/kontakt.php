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
    <title>Kontakt | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/heading.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <style>
        #kontakt a{
            color: var(--primary-color);
        }
        #kontakt .line{
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
        <h1>Kontakt</h1>
        <p>Bei Fragen, Anregungen oder Anliegen können Sie uns gerne über die folgenden Kontaktmöglichkeiten erreichen:</p>
        <ul>
            <li><strong>Adresse:</strong> Musterstraße 1, 12345 Plänitz-Leddin</li>
            <li><strong>Telefon:</strong> <a class="missing" href="tel:+491234567890">01234 567890</a></li>
            <li><strong>E-Mail:</strong> <a class="missing" href="mailto:info@plaenitz-leddin.de">info@plaenitz-leddin.de</a></li>
        </ul>
        <h2>Kontaktformular</h2>
        <p>Alternativ können Sie auch das folgende Kontaktformular nutzen, um uns direkt eine Nachricht zu senden:</p>
        <form id="contact-form" method="post">
            <div class="form-group">
                <label for="name"><span class="stern">*</span>Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email"><span class="stern">*</span>E-Mail:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="message"><span class="stern">*</span>Nachricht:</label>
                <textarea id="message" name="message" rows="5" required></textarea>
            </div>
            <div class="row">
                <input type="checkbox" id="privacy" name="privacy" required>
                <label for="privacy"><span class="stern">*</span>Ich habe die <a href="../pages/datenschutz.php" target="_blank">Datenschutzerklärung</a> gelesen und akzeptiere sie.</label>
            </div>
            <div id="captcha-container" class="form-group">
                <!-- CAPTCHA placeholder -->
            </div>
            <button type="submit" class="submit">Absenden</button>
        </form>
        
    </div>
    <div id="footer" class="center">
        <?php include '../pages/footer.php'; ?>
    </div>
</body>
</html>