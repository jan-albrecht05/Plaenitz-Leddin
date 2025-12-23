<?php
require_once 'includes/config-helper.php';

// Get config values
$tabicon = getConfigValue('tabicon') ?? 'PL1.png';
$logo = getConfigValue('logo') ?? 'logo.png';
$primaryColor = getConfigValue('primary_color') ?? '#4a6fa5';
$version = getConfigValue('system_version');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/root.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/error.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="icon" type="image/x-icon" href="assets/icons/tabicons/<?php echo htmlspecialchars($tabicon); ?>">
    <script src="assets/js/mode.js" defer></script>
    <title>Fehler <?php echo htmlspecialchars($_GET['code'] ?? '500'); ?> | Plänitz-Leddin</title>
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($primaryColor); ?>;
        }
    </style>
</head>
<body>
    <div id="main" class="center">
        <div class="box">
            <div id="img">
                <?php
                $errorCode = $_GET['code'] ?? '500';
                $imagePath = 'assets/icons/' . $errorCode . '.webp';
                
                // Fallback to generic error image if specific error image doesn't exist
                if (!file_exists($imagePath)) {
                    $imagePath = 'assets/icons/error.webp';
                }
                
                // If even generic error image doesn't exist, use 404 image as ultimate fallback
                if (!file_exists($imagePath)) {
                    $imagePath = 'assets/icons/404.webp';
                }
                ?>
                <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Error <?php echo htmlspecialchars($errorCode); ?>">
            </div>
            <div id="text">
                <h1>Error <span class="contrast"><?php echo htmlspecialchars($_GET['code'] ?? '500'); ?></span></h1>
                <h3><?php echo htmlspecialchars($_GET['title'] ?? 'Ein Fehler ist aufgetreten'); ?></h3>
                <p><?php echo htmlspecialchars($_GET['message'] ?? 'Es ist ein unerwarteter Fehler aufgetreten. Bitte versuchen Sie es später erneut.'); ?></p>
                <a href="index.php" class="button center">Zur Startseite</a>
            </div>
        </div>
    </div>
    <div id="footer" class="center">
        <div id="mode-toggle">
            <span class="material-symbols-outlined">light_mode</span>
            <label class="switch">
                <input type="checkbox" id="toggle-checkbox">
                <span class="slider round"></span>
            </label>
            <span class="material-symbols-outlined">dark_mode</span>
        </div>
    </div>
</body>
</html>
