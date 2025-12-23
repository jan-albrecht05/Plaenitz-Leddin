<?php
require_once '../../includes/session-config.php';
startSecureSession();    // Include database helper functions
    require_once '../../includes/db_helper.php';
    require_once '../../includes/log-data.php';
    
    if(isset($_SESSION['user_id'])) {
        // User is already logged in - redirect to appropriate page based on role
        $targetPage = hasAdminRole($_SESSION['user_id']) ? 'admin.php' : 'dashboard.php';
        header("Location: " . $targetPage);
        exit();
    }

    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        $fullname = trim($_POST['fullname'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($fullname === '') {
            $error = "Bitte geben Sie Vor- und Nachname ein.";
            header("Location: login.php?error=" . urlencode($error));
            exit();
        }

        $redirect = $_GET['redirect'] ?? '';
        // Split fullname into name and nachname: last token = nachname, rest = name
        $parts = preg_split('/\s+/', $fullname);
        if (count($parts) < 2) {
            $error = "Bitte geben Sie Vor- und Nachname (z. B. 'Max Mustermann') ein.";
            header("Location: login.php?error=" . urlencode($error));
            exit();
        }

        $nachname = array_pop($parts);
        $name = implode(' ', $parts);

        // Authenticate user against database
        $user = authenticateUser($name, $nachname, $password);

        if($user) {
            // Store only user ID in session, roles will be checked from database
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            
            // Determine redirect based on role and password setup status
            $userId = $user['id'];
            $isAdmin = hasAdminRole($userId);
            $hasCompletedSetup = userHasCompletedPasswordSetup($userId);
            
            // Determine target page based on role
            $targetPage = $isAdmin ? 'admin.php' : 'dashboard.php';
            
            // Check if user needs to complete password setup
            if (!$hasCompletedSetup) {
                $redirect = $targetPage . '?neu=1';
                // DO NOT update last_visited_date here - it will be set after successful password change
            } else {
                // User already has last_visited_date, update it now
                updateLastVisitedDate($userId);
                $redirect = $targetPage;
            }
            
            // log successful login
            logAction('', 'login-success', $user['name'] . ' (' . $name . ' ' . $nachname . ') hat sich erfolgreich eingeloggt', '', $userId);
            
            header("Location: " . $redirect);
            exit();
        } else {
            // log failed login attempt
            logAction('', 'login-failed', 'Fehlgeschlagener Login-Versuch für: ' . $name . ' ' . $nachname, '', '');
            
            $error = "Ungültiger Benutzername oder Passwort.";
            header("Location: login.php?error=" . urlencode($error));
            exit();
        }
    }
    require_once '../../includes/config-helper.php';

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
    <title>Login | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../../assets/css/root.css">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/login.css">
    <link rel="stylesheet" href="../../assets/css/heading.css">
    <link rel="stylesheet" href="../../assets/css/footer.css">
    <link rel="icon" type="image/png" href="../../assets/icons/tabicons/<?php echo htmlspecialchars($tabicon); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($primaryColor); ?>;
        }
    </style>
</head>
<body>
    <div id="heading">
        <div id="left">
            <a href="../../index.php">
                <img src="../../assets/icons/logo.png" alt="">
            </a>
        </div>
        <div id="right"></div>
    </div>
    <div id="login-container" class="center">
        <form action="login.php" method="post">
            <h1>Login</h1>
            <div class="form-group">
                <label for="fullname">Name (Vorname Nachname):</label>
                <input type="text" id="fullname" name="fullname" placeholder="Max Mustermann" required>
            </div>
            <div class="form-group">
                <label for="password">Passwort:</label>
                <input type="password" id="password" name="password" required>
                <div class="error">
                    <?php
                        if(isset($_GET['error'])) {
                            echo htmlspecialchars($_GET['error']);
                        }
                    ?>
                </div>
            </div>
            <button type="submit" class="center">
                Einloggen
                <span class="material-symbols-outlined">login</span>
            </button>
        </form>
    </div>
    <div id="footer" class="center" style="flex-direction: column; gap: 10px;">
        <div id="mode-toggle">
            <span class="material-symbols-outlined">light_mode</span>
            <label class="switch">
                <input type="checkbox" id="toggle-checkbox">
                <span class="slider round"></span>
            </label>
            <span class="material-symbols-outlined">dark_mode</span>
            <script src="../../assets/js/mode.js"></script>
        </div>
        <a href="https://github.com/jan-albrecht05/Plaenitz-Leddin/commits/main/" target="_blank" id="version">
            Version <?php echo htmlspecialchars($version); ?>
        </div>
    </div>
</body>
</html>