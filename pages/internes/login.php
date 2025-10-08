<?php
    session_start();
    if(isset($_SESSION['user_id'])) {
        header("Location: dashboard.php");
        exit();
    }

    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Dummy credentials for demonstration purposes
        $valid_username = 'Admin';
        $valid_password = 'admin';

        if($username === $valid_username && $password === $valid_password) {
            $_SESSION['user_id'] = 1; // Set a dummy user ID
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
                <label for="username">Benutzername:</label>
                <input type="text" id="username" name="username" required>
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