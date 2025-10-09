<?php
    // quick and dirty account creation page
    session_start();
    require_once '../../includes/db_helper.php';
    // read form input
    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $nachname = trim($_POST['nachname'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'member';
        $email = $_POST['email'] ?? '';

        if(empty($name) || empty($nachname) || empty($password)) {
            $error = "Vorname, Nachname und Passwort dürfen nicht leer sein.";
        } else {
            // create user in database
            $result = createUser($name, $nachname, $password, $role, $email);
            if($result['success']) {
                $success = "Benutzer erfolgreich erstellt.";
            } else {
                $error = "Fehler beim Erstellen des Benutzers: " . $result['error'];
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzer hinzufügen | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../../assets/css/root.css">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/login.css">
    <style>
        .error { color: red; margin: 10px 0; }
        .success { color: green; margin: 10px 0; }
        label { display: block; margin-bottom: 5px; }
        input, select { width: 100%; padding: 8px; margin-bottom: 10px; }
        button { padding: 10px 20px; background-color: #007cba; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #005a87; }
    </style>
</head>
<body>
    <form action="adduser.php" method="post">
        <h1>Neuen Benutzer erstellen</h1>
        <?php if(isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if(isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <div class="form-group">
            <label for="name">Vorname:</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
            <label for="nachname">Nachname:</label>
            <input type="text" id="nachname" name="nachname" required>
        </div>
        <div class="form-group">
            <label for="password">Passwort:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="email">E-Mail (optional):</label>
            <input type="email" id="email" name="email">
        </div>
        <div class="form-group">
            <label for="role">Rolle:</label>
            <select id="role" name="role" required>
                <option value="member">Mitglied</option>
                <option value="vorstand">Vorstand</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit">Benutzer erstellen</button>
    </form>
</body>
</html>