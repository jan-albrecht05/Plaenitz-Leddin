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
                <div class="role-status flex-row">
                    <button class="member-button role">Rolle</button>
                    <span class="status">Status</span>
                </div>
                <span class="edit-button"></span>
            </div>
            <script src="../../assets/js/dashboard.js" defer></script>
            <?php
            // Database connection for members
            $dbPath = __DIR__ . '/../../assets/db/member.db';
            
            if (!file_exists($dbPath)) {
                echo '<div class="member"><p>Datenbankdatei nicht gefunden: ' . htmlspecialchars($dbPath) . '</p></div>';
            } else {
                try {
                    // Connect to database
                    $pdo = new PDO('sqlite:' . $dbPath);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    // Set UTF-8 encoding for proper display of special characters (ß, ä, ö, ü, etc.)
                    $pdo->exec("PRAGMA encoding = 'UTF-8'");
                    
                    // Get all members
                    $stmt = $pdo->prepare('SELECT id, titel, name, nachname, strasse, hausnummer, adresszusatz, plz, ort, festnetz, mobilnummer, e_mail, rolle, status, join_date FROM mitglieder ORDER BY id ASC, name ASC');
                    $stmt->execute();
                    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($members && count($members) > 0) {
                        foreach ($members as $member) {
                            // Defaults for role and status to ensure variables are always set
                            $roleClass = 'member';
                            $roleIcon = 'person';
                            $roleText = 'Mitglied';

                            $statusClass = 'pending';
                            $statusIcon = 'schedule';
                            $statusText = 'Ausstehend';

                            // Determine role class and display text
                            if (!empty($member['rolle'])) {
                                $roleLower = strtolower(trim((string)$member['rolle']));
                                if ($roleLower === 'admin') {
                                    $roleClass = 'admin';
                                    $roleIcon = 'admin_panel_settings';
                                    $roleText = 'Admin';
                                } elseif ($roleLower === 'vorstand') {
                                    $roleClass = 'vorstand';
                                    $roleIcon = 'shield_person';
                                    $roleText = 'Vorstand';
                                } elseif ($roleLower === 'mitglied') {
                                    $roleClass = 'member';
                                    $roleIcon = 'person';
                                    $roleText = 'Mitglied';
                                }
                            }

                            // Normalize and map status value (accepts 0/1/2 as int or string)
                            $statusInt = isset($member['status']) && is_numeric($member['status']) ? (int)$member['status'] : null;
                            if ($statusInt === 1) {
                                $statusClass = 'aktiv';
                                $statusIcon = 'verified';
                                $statusText = 'Aktiv';
                            } elseif ($statusInt === 2) {
                                $statusClass = 'inaktiv';
                                $statusIcon = 'do_not_disturb_on';
                                $statusText = 'Inaktiv';
                                $roleClass = 'hidden';
                            } elseif ($statusInt === 0) {
                                $statusClass = 'pending';
                                $statusIcon = 'schedule';
                                $statusText = 'Ausstehend';
                                $roleClass = 'hidden';
                            }

                            // Prepare contact values supporting both column name variants
                            $telefonVal = '';
                            if (!empty($member['festnetz'])) {
                                $telefonVal = $member['festnetz'];
                            } elseif (!empty($member['telefon'])) {
                                $telefonVal = $member['telefon'];
                            }

                            $mobilVal = '';
                            if ($member['mobilnummer'] === '+49' || $member['mobilnummer'] === '+49 ') {
                                $mobilVal = '';
                            }else{
                                $mobilVal = $member['mobilnummer'];
                            }


                            // Format join date
                            $joinDateFormatted = '';
                            if (!empty($member['join_date'])) {
                                try {
                                    $date = new DateTime($member['join_date']);
                                    $joinDateFormatted = $date->format('d.m.Y');
                                } catch (Exception $e) {
                                    $joinDateFormatted = '';
                                }
                            }
                            
                            // Format address
                            $address1 = !empty($member['strasse']) ? htmlspecialchars($member['strasse']) . ', ' . htmlspecialchars($member['hausnummer']) : '';
                            $address2 = (!empty($member['PLZ']) ? htmlspecialchars($member['PLZ']) . ' ' : '') . 
                                        (!empty($member['ort']) ? htmlspecialchars($member['ort']) : '').
                                        (!empty($member['adresszusatz']) ? '<br>'.htmlspecialchars($member['adresszusatz']) : '');
                            
                            $hasAddress = !empty($address1) || !empty($address2);
                            ?>
                            <div class="member" data-member-id="<?php echo (int)$member['id']; ?>">
                                <div class="member-top">
                                    <div class="ganzer-name">
                                        <h2 class="nachname"><?php echo htmlspecialchars($member['titel']) . ' ' . htmlspecialchars($member['nachname']); ?>, <?php echo htmlspecialchars($member['name']); ?></h2>
                                    </div>
                                    <div class="role-status flex-row">
                                        <div class="role <?php echo $roleClass; ?> center">
                                            <span class="material-symbols-outlined role-symbol"><?php echo $roleIcon; ?></span>
                                            <span class="role-text"><?php echo $roleText; ?></span>
                                        </div>
                                        <div class="status <?php echo $statusClass; ?> center">
                                            <span class="material-symbols-outlined status-symbol"><?php echo $statusIcon; ?></span>
                                            <span class="status-text"><?php echo $statusText; ?></span>
                                        </div>
                                    </div>
                                    <button class="edit-button">
                                        <span class="material-symbols-outlined">more_vert</span>
                                    </button>
                                </div>
                                <div class="member-bottom">
                                    <div class="left">
                                        <?php if ($hasAddress): ?>
                                        <div class="address center">
                                            <span class="material-symbols-outlined address-symbol">home</span>
                                            <div class="flex-column">
                                                <?php if (!empty($address1)): ?>
                                                <span class="address-text"><?php echo $address1; ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($address2)): ?>
                                                <span class="address-text"><?php echo $address2; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($joinDateFormatted)): ?>
                                        <div class="join-date center">
                                            <span class="material-symbols-outlined join-date-symbol">event</span>
                                            <span class="join-date-text"><?php echo $joinDateFormatted; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="right">
                                        <?php if (!empty($telefonVal)): ?>
                                        <div class="phone">
                                            <span class="material-symbols-outlined phone-symbol">phone</span>
                                            <span class="phone-text"><?php echo htmlspecialchars($telefonVal); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($mobilVal)): ?>
                                        <div class="mobile">
                                            <span class="material-symbols-outlined mobile-symbol">smartphone</span>
                                            <span class="mobile-text"><?php echo htmlspecialchars($mobilVal); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($member['e_mail'])): ?>
                                        <div class="email">
                                            <span class="material-symbols-outlined email-symbol">email</span>
                                            <span class="email-text"><?php echo htmlspecialchars($member['e_mail']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="member"><p>Keine Mitglieder gefunden.</p></div>';
                    }
                    
                } catch (Exception $e) {
                    error_log('Dashboard: DB error - ' . $e->getMessage());
                    echo '<div class="member"><p>Fehler beim Laden der Mitglieder: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
                }
            }
            ?>
        </div>
    </div>
    <div id="footer" class="center">
        <?php include '../../pages/internes/footer.php'; ?>
    </div>
</body>
</html>