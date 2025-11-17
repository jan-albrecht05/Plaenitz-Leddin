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
//get "?neu=" from URL
// Context-menu actions (delete, promote, demote, activate, deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'], $_POST['action'])) {
    $currentUserId = $_SESSION['user_id'] ?? null;
    $targetId = (int)$_POST['member_id'];
    $action = (string)$_POST['action'];

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

    $respond = function($success, $message) use ($isAjax) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => (bool)$success, 'message' => $message]);
            exit();
        } else {
            if ($success) header('Location: dashboard.php?success=' . urlencode($message));
            else header('Location: dashboard.php?error=' . urlencode($message));
            exit();
        }
    };

    if (!$currentUserId || $targetId <= 0) {
        $respond(false, 'Ungültige Anfrage.');
    }

    // Use helper to get PDO connection to member DB
    $pdo = getMemberDbConnection();
    if (!$pdo) {
        error_log('dashboard: Mitgliederdatenbank nicht erreichbar.');
        $respond(false, 'Datenbankfehler.');
    }

    $isAdmin = hasAdminRole($currentUserId);
    $isVorstand = hasVorstandRole($currentUserId);
    $canModifyStatus = $isAdmin || $isVorstand;
    $canChangeRoleOrDelete = $isAdmin;

    try {
        if ($action === 'delete') {
            if (!$canChangeRoleOrDelete) throw new Exception('Keine Berechtigung zum Löschen.');
            if ($targetId === $currentUserId) throw new Exception('Sie können sich nicht selbst löschen.');
            $stmt = $pdo->prepare('DELETE FROM mitglieder WHERE id = :id');
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $respond(true, 'Benutzer gelöscht.');
        }

        if ($action === 'promote') {
            if (!$canChangeRoleOrDelete) throw new Exception('Keine Berechtigung zum Promoten.');
            $stmt = $pdo->prepare('UPDATE mitglieder SET rolle = :rolle WHERE id = :id AND rolle != :adminRole');
            $stmt->bindValue(':rolle', 'vorstand', PDO::PARAM_STR);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->bindValue(':adminRole', 'admin', PDO::PARAM_STR);
            $stmt->execute();
            $respond(true, 'Benutzer zum Vorstand gemacht.');
        }

        if ($action === 'demote') {
            if (!$canChangeRoleOrDelete) throw new Exception('Keine Berechtigung zum Demoten.');
            $stmt = $pdo->prepare('SELECT rolle FROM mitglieder WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Benutzer nicht gefunden.');
            if (strtolower((string)$row['rolle']) === 'admin') throw new Exception('Admin kann nicht gedemoted werden.');
            $stmt = $pdo->prepare('UPDATE mitglieder SET rolle = :rolle WHERE id = :id');
            $stmt->bindValue(':rolle', 'mitglied', PDO::PARAM_STR);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $respond(true, 'Benutzer gedemoted.');
        }

        if ($action === 'activate' || $action === 'deactivate') {
            if (!$canModifyStatus) throw new Exception('Keine Berechtigung zur Statusänderung.');
            $newStatus = $action === 'activate' ? 1 : 2;
            $stmt = $pdo->prepare('UPDATE mitglieder SET status = :status WHERE id = :id');
            $stmt->bindValue(':status', $newStatus, PDO::PARAM_INT);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $msg = $action === 'activate' ? 'Benutzer aktiviert.' : 'Benutzer deaktiviert.';
            $respond(true, $msg);
        }

        $respond(false, 'Unbekannte Aktion.');

    } catch (Exception $e) {
        error_log('dashboard action error: ' . $e->getMessage());
        $respond(false, $e->getMessage());
    }
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
        <script>
            // Submit context action via AJAX and update UI without reload
            async function submitContextActionAjax(action, memberId) {
                try {
                    const params = new URLSearchParams();
                    params.append('action', action);
                    params.append('member_id', memberId);

                    const res = await fetch('dashboard.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: params.toString()
                    });

                    const data = await res.json();
                    if (!data.success) {
                        alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
                        return;
                    }

                    // Update DOM based on action
                    const el = document.querySelector('.member[data-member-id="' + memberId + '"]');
                    if (action === 'delete') {
                        if (el) {
                            el.style.transition = 'opacity 0.3s';
                            el.style.opacity = '0';
                            setTimeout(() => el.remove(), 300);
                        }
                    } else if (action === 'promote') {
                        if (el) {
                            const roleDiv = el.querySelector('.role');
                            const roleText = el.querySelector('.role-text');
                            const roleIcon = el.querySelector('.role-symbol');
                            if (roleDiv) { 
                                roleDiv.classList.remove('member', 'hidden'); 
                                roleDiv.classList.add('vorstand'); 
                            }
                            if (roleText) roleText.textContent = 'Vorstand';
                            if (roleIcon) roleIcon.textContent = 'shield_person';
                        }
                    } else if (action === 'demote') {
                        if (el) {
                            const roleDiv = el.querySelector('.role');
                            const roleText = el.querySelector('.role-text');
                            const roleIcon = el.querySelector('.role-symbol');
                            if (roleDiv) { 
                                roleDiv.classList.remove('vorstand', 'hidden'); 
                                roleDiv.classList.add('member'); 
                            }
                            if (roleText) roleText.textContent = 'Mitglied';
                            if (roleIcon) roleIcon.textContent = 'person';
                        }
                    } else if (action === 'activate' || action === 'deactivate') {
                        if (el) {
                            const statusDiv = el.querySelector('.status');
                            const statusText = el.querySelector('.status-text');
                            const statusIcon = el.querySelector('.status-symbol');
                            const roleDiv = el.querySelector('.role');
                            const roleTextEl = el.querySelector('.role-text');
                            if (statusDiv && statusText && statusIcon) {
                                if (action === 'activate') {
                                    statusDiv.className = 'status aktiv center';
                                    statusText.textContent = 'Aktiv';
                                    statusIcon.textContent = 'verified';
                                    if (roleDiv) {
                                        roleDiv.classList.remove('hidden');
                                        const hasSpecificRole = roleDiv.classList.contains('admin') || roleDiv.classList.contains('vorstand');
                                        if (!hasSpecificRole && !roleDiv.classList.contains('member')) {
                                            roleDiv.classList.add('member');
                                        }
                                    }
                                } else {
                                    statusDiv.className = 'status inaktiv center';
                                    statusText.textContent = 'Inaktiv';
                                    statusIcon.textContent = 'do_not_disturb_on';
                                    if (roleDiv) roleDiv.classList.add('hidden');
                                }
                            }
                        }
                    }

                    // Success feedback
                    console.log('✓ ' + (data.message || 'Erfolgreich'));
                } catch (err) {
                    alert('Netzwerkfehler: ' + err.message);
                }
            }

            // Convenience wrapper for non-AJAX fallback: set hidden fields and submit
            function submitContextAction(action, memberId) {
                const form = document.getElementById('member-context-menu-form');
                if (!form) return;
                // If fetch is available, use AJAX
                if (window.fetch) {
                    submitContextActionAjax(action, memberId);
                    return;
                }
                document.getElementById('context-member-id').value = memberId;
                document.getElementById('context-action').value = action;
                form.submit();
            }
        </script>
        <div id="right">
            <a href="logout.php" id="logout-button">
                <span class="material-symbols-outlined">logout</span>
            </a>
        </div>
    </div>
    <div id="dashboard-container" class="banner">
        <h1>Willkommen zum Dashboard</h1>
    </div>
    <div class="popup" <?php if (isset($_GET['change_pw'])) echo 'style="display: flex;"'; ?> id="pw-change-popup">
        <div class="popup-content">
            <h2>Es scheint, als wäre dies ihr erster Login.</h2>
            <h3>Bitte legen Sie sich ein neues Passwort fest.</h3>
            <form action="dashboard.php" method="post">
                <div class="form-group">
                    <label for="current-password">Aktuelles Passwort:</label>
                    <input type="password" id="current-password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new-password">Neues Passwort:</label>
                    <input type="password" id="new-password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm-password">Neues Passwort bestätigen:</label>
                    <input type="password" id="confirm-password" name="confirm_password" required>
                </div>
                <button type="submit">Passwort ändern</button>
            </form>
        </div>
    </div>
    <?php
        // Update db with users new password if form submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password'], $_POST['new_password'], $_POST['confirm_password'])) {
            if (!isset($_SESSION['user_id'])) {
                // User not logged in; cannot change password
                header("Location: dashboard.php");
                exit();
            }
            $userId = $_SESSION['user_id'];
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            // Validate new password and confirmation
            if ($newPassword !== $confirmPassword) {
                // Passwords do not match
                header("Location: dashboard.php?change_pw=1&error=" . urlencode("Die neuen Passwörter stimmen nicht überein."));
                exit();
            }

            // Verify current password
            if (!verifyUserPassword($userId, $currentPassword)) {
                // Current password incorrect
                header("Location: dashboard.php?change_pw=1&error=" . urlencode("Das aktuelle Passwort ist falsch."));
                exit();
            }

            // Update password in database
            if (updateUserPassword($userId, $newPassword)) {
                // Success
                updateLastVisitedDate($userId);
                header("Location: dashboard.php?pw_changed=1");
                exit();
            } else {
                // Failed to update password
                header("Location: dashboard.php?change_pw=1&error=" . urlencode("Fehler beim Ändern des Passworts. Bitte versuchen Sie es erneut."));
                exit();
            }
        }
        
    ?>
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
                            <div class="member" data-member-id="<?php echo (int)$member['id']; ?>" oncontextmenu="opencontextMenu('<?php echo htmlspecialchars($member['id']); ?>'); return false;">
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
                                    <button class="edit-button" onclick="opencontextMenu('<?php echo htmlspecialchars($member['id']); ?>')">
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
        <div id="member-context-menu" class="context-menu">
            <form action="dashboard.php" method="post" id="member-context-menu-form">
                <input type="hidden" id="context-member-id" name="member_id" value="">
                <input type="hidden" id="context-action" name="action" value="">
                <button type="button" id="edit-member">
                    <span class="material-symbols-outlined">edit</span>
                    <span class="text">bearbeiten</span>
                </button>
                <button type="button" id="activate-member">
                    <span class="material-symbols-outlined">verified</span>
                    <span class="text">als aktiv markieren</span>
                </button>
                <button type="button" id="deactivate-member">
                    <span class="material-symbols-outlined">do_not_disturb_on</span>
                    <span class="text">als inaktiv markieren</span>
                </button>
                <button type="button" id="up-member">
                    <span class="material-symbols-outlined">shield_person</span>
                    <span class="text">Vorstandsrolle hinzufügen</span>
                </button>
                <button type="button" id="down-member">
                    <span class="material-symbols-outlined">person</span>
                    <span class="text">Vorstandsrolle entfernen</span>
                </button>
                <button type="button" id="delete-member">
                    <span class="material-symbols-outlined">delete</span>
                    <span class="text">löschen</span>
                </button>
            </form>
        </div>
    </div>
    <div id="footer" class="center">
        <?php include '../../pages/internes/footer.php'; ?>
    </div>
</body>
</html>