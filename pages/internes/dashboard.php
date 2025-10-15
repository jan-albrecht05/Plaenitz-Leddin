<?php
session_start();

// Include database helper functions
require_once '../../includes/db_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has admin or vorstand role by querying the database
$userId = $_SESSION['user_id'];
if (!hasAdminOrVorstandRole($userId)) {
    // User doesn't have required role, redirect to login
    session_destroy();
    header("Location: login.php?error=" . urlencode("Sie haben keine Berechtigung für diese Seite."));
    exit();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../../assets/css/root.css">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
        <div id="right">
            <a href="logout.php" id="logout-button">
                <span class="material-symbols-outlined">logout</span>
            </a>
        </div>
    </div>
    <div id="dashboard-container" class="banner">
        <h1>Willkommen zum Dashboard</h1>
    </div>
    <div id="main">
        <div id="member-output">
            <div id="member-output-heading">
                <button class="member-button ganzer-name">Name</button>
                <button class="member-button role">Rolle</button>
                <span class="status">Status</span>
                <span class="edit-button"></span>
            </div>
            <div class="member ">
                <div class="member-top">
                    <div class="ganzer-name">
                        <h2 class="nachname">Nachname,</h2>
                        <h2 class="name">Name</h2>
                    </div>
                    <div class="role admin center">
                        <span class="material-symbols-outlined role-symbol">admin_panel_settings</span> 
                        <span class="role-text">Admin</span>
                    </div>
                    <div class="status aktiv center">
                        <span class="material-symbols-outlined status-symbol">verified</span> 
                        <span class="status-text">Aktiv</span>
                    </div>

                    <button class="edit-button">
                        <span class="material-symbols-outlined">more_vert</span>
                    </button>
                </div>
                <div class="member-bottom">
                    <div class="left">
                        <div class="address center">
                            <span class="material-symbols-outlined address-symbol">home</span>
                            <div class="flex-column">
                                <span class="address-text">Musterstraße 1,</span>
                                <span class="address-text">12345 Musterstadt</span>
                            </div>
                        </div>
                        <div class="join-date center">
                            <span class="material-symbols-outlined join-date-symbol">event</span> 
                            <span class="join-date-text">01.01.2020</span>
                        </div>
                    </div>
                    <div class="right">
                        <div class="phone">
                            <span class="material-symbols-outlined phone-symbol">phone</span> 
                            <span class="phone-text">01234 567890</span>
                        </div>
                        <div class="mobile">
                            <span class="material-symbols-outlined mobile-symbol">smartphone</span> 
                            <span class="mobile-text">01234 567890</span>
                        </div>
                        <div class="email">
                            <span class="material-symbols-outlined email-symbol">email</span> 
                            <span class="email-text">email@example.com</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="member open">
                <div class="member-top">
                    <div class="ganzer-name">
                        <h2 class="nachname">Nachname,</h2>
                        <h2 class="name">Name</h2>
                    </div>
                    <div class="role member center">
                        <span class="material-symbols-outlined role-symbol">person</span> 
                        <span class="role-text">Mitglied</span>
                    </div>
                    <div class="status aktiv center">
                        <span class="material-symbols-outlined status-symbol">verified</span> 
                        <span class="status-text">Aktiv</span>
                    </div>

                    <button class="edit-button">
                        <span class="material-symbols-outlined">more_vert</span>
                    </button>
                </div>
                <div class="member-bottom">
                    <div class="left">
                        <div class="address center">
                            <span class="material-symbols-outlined address-symbol">home</span>
                            <div class="flex-column">
                                <span class="address-text">Musterstraße 1,</span>
                                <span class="address-text">12345 Musterstadt</span>
                            </div>
                        </div>
                        <div class="join-date center">
                            <span class="material-symbols-outlined join-date-symbol">event</span> 
                            <span class="join-date-text">01.01.2020</span>
                        </div>
                    </div>
                    <div class="right">
                        <div class="phone">
                            <span class="material-symbols-outlined phone-symbol">phone</span> 
                            <span class="phone-text">01234 567890</span>
                        </div>
                        <div class="mobile">
                            <span class="material-symbols-outlined mobile-symbol">smartphone</span> 
                            <span class="mobile-text">01234 567890</span>
                        </div>
                        <div class="email">
                            <span class="material-symbols-outlined email-symbol">email</span> 
                            <span class="email-text">email@example.com</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="member ">
                <div class="member-top">
                    <div class="ganzer-name">
                        <h2 class="nachname">Nachname,</h2>
                        <h2 class="name">Name</h2>
                    </div>
                    <div class="role vorstand center">
                        <span class="material-symbols-outlined role-symbol">admin_panel_settings</span> 
                        <span class="role-text">Vorstand</span>
                    </div>
                    <div class="status aktiv center">
                        <span class="material-symbols-outlined status-symbol">verified</span> 
                        <span class="status-text">Aktiv</span>
                    </div>

                    <button class="edit-button">
                        <span class="material-symbols-outlined">more_vert</span>
                    </button>
                </div>
                <div class="member-bottom">
                    <div class="left">
                        <div class="address center">
                            <span class="material-symbols-outlined address-symbol">home</span>
                            <div class="flex-column">
                                <span class="address-text">Musterstraße 1,</span>
                                <span class="address-text">12345 Musterstadt</span>
                            </div>
                        </div>
                        <div class="join-date center">
                            <span class="material-symbols-outlined join-date-symbol">event</span> 
                            <span class="join-date-text">01.01.2020</span>
                        </div>
                    </div>
                    <div class="right">
                        <div class="phone">
                            <span class="material-symbols-outlined phone-symbol">phone</span> 
                            <span class="phone-text">01234 567890</span>
                        </div>
                        <div class="mobile">
                            <span class="material-symbols-outlined mobile-symbol">smartphone</span> 
                            <span class="mobile-text">01234 567890</span>
                        </div>
                        <div class="email">
                            <span class="material-symbols-outlined email-symbol">email</span> 
                            <span class="email-text">email@example.com</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="footer" class="center">
        <?php include '../../pages/internes/footer.php'; ?>
    </div>
</body>
</html>