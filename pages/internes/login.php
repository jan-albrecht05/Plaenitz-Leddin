<?php
    session_start();
    
    // Include database helper functions
    require_once '../../includes/db_helper.php';
    
    if(isset($_SESSION['user_id'])) {
        header("Location: dashboard.php");
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
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Ungültiger Benutzername oder Passwort.";
            header("Location: login.php?error=" . urlencode($error));
            exit();
        }
    }
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
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
    <div id="footer" class="center">
        <div id="mode-toggle">
                <span class="material-symbols-outlined">light_mode</span>
                <label class="switch">
                    <input type="checkbox" id="toggle-checkbox">
                    <span class="slider round"></span>
                </label>
                <span class="material-symbols-outlined">dark_mode</span>
                <script src="../../assets/js/mode.js"></script>
            </div>
        </div>
    </div>
</body>
</html>