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
    </style>
</head>
<body>
    <div id="heading">
        <?php include '../pages/heading.php'; ?>
    </div>
    <div class="banner">
        <h1>Zwei Dörfer, eine Gemeinschaft</h1>
    </div>
    <div id="main">
        <p id="einführung">
            Wir sind der Verein Plänitz-Leddin e.V., eine Gemeinschaft von engagierten Einwohnern der Dörfer Plänitz und Leddin. Unser Ziel ist es, das Dorfleben zu fördern, Traditionen zu bewahren und gemeinsame Aktivitäten zu organisieren, die das Miteinander stärken.
        </p>
        <div class="person">
            <div class="textseite">
                <h1 class="name">Jan</h1>
                <div class="info">
                    <span class="material-symbols-outlined">location_on</span>
                    <span class="ort">Berlin</span>
                    <span class="material-symbols-outlined">person</span>
                    <span class="datum">Admin | Web-Dev</span>
                </div>
                <p class="beschreibung">
                    Jan ist seit 2015 Mitglied unseres Vereins und engagiert sich besonders in der Organisation von Veranstaltungen. Als Admin sorgt er dafür, dass unsere Online-Präsenz stets aktuell bleibt.
                </p>
            </div>
            <div class="bildseite">
                <div class="wrapper">
                    <div class="blob shape-1 pink"></div> 
                    <img src="../assets/images/team/jan.jpg" alt="">
                </div>
            </div>
        </div>
        <div class="person">
            <div class="bildseite">
                <div class="wrapper">
                    <div class="blob shape-1 green"></div> 
                    <img src="../assets/images/team/jan.jpg" alt="">
                </div>
            </div>
            <div class="textseite">
                <h1 class="name">Jan</h1>
                <div class="info">
                    <span class="material-symbols-outlined">location_on</span>
                    <span class="ort">Berlin</span>
                    <span>|</span>
                    <span class="material-symbols-outlined">person</span>
                    <span class="job">Admin | Web-Dev</span>
                </div>
                <p class="beschreibung">
                    Jan ist seit 2015 Mitglied unseres Vereins und engagiert sich besonders in der Organisation von Veranstaltungen. Als Admin sorgt er dafür, dass unsere Online-Präsenz stets aktuell bleibt.
                </p>
            </div>
        </div>
    </div>
    <div id="footer" class="center">
        <?php include '../pages/footer.php'; ?>
    </div>
</body>
</html>