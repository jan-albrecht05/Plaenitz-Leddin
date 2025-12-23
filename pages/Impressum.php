<?php
require_once '..//includes/config-helper.php';

// Get config values
$tabicon = getConfigValue('tabicon') ?? 'PL1.png';
$logo = getConfigValue('logo') ?? 'logo.png';
$bannerImage = getConfigValue('banner_image') ?? '';
$bannerText = getConfigValue('banner_text') ?? 'Zwei Dörfer, eine Gemeinschaft';
$primaryColor = getConfigValue('primary_color') ?? '#4a6fa5';
$showGIF = filter_var(getConfigValue('show_gif'), FILTER_VALIDATE_BOOLEAN);
$currentGIF = getConfigValue('current_gif');
$version = getConfigValue('system_version');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impressum | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/heading.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <style>
        :root {
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
        <h1>Impressum</h1>
        <p>Angaben gemäß § 5 TMG:</p>
        <p>Gemeinsam für Plänitz-Leddin e.V.<br>
        <span class="missing">Straße</span><br>
        <span class="missing">PLZ Ort</span></p>
        <p>Vertreten durch den Vorstand:<br>
        Paul<span class="missing"> Nachname</span><br>
        <span class="missing">Vorname Nachname</span></p>
        <h2>Kontakt:</h2>
        <p>Telefon: <span class="missing"></span><br>
        E-Mail: <span class="missing"></span></p>
        <h2>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV:</h2>
        <p>Vorname Nachname<br>
        <span class="missing">Straße</span><br>
        <span class="missing">PLZ Ort</span></p>
        <h2>Haftungsausschluss (Disclaimer):</h2>
        <h3>Haftung für Inhalte</h3>
        <p>Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.</p>
        <p>Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.</p>
        <h3>Haftung für Links</h3>
        <p>Unser Angebot enthält keine Links zu externen Websites Dritter.</p>
        <h3>Urheberrecht</h3>
        <p>Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers. Downloads und Kopien dieser Seite sind nur für den privaten, nicht kommerziellen Gebrauch gestattet.</p>
        <p>Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie trotzdem auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen.</p>
        <p>Urheber: <b>Jan Albrecht</b></p>
    </div>
    <div id="footer" class="center">
        <?php include '../pages/footer.php'; ?>
    </div>
</body>
</html>