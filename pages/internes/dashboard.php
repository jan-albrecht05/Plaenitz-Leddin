<?php
// Start output buffering to catch any stray output
ob_start();

require_once '../../includes/session-config.php';
startSecureSession();

// Include database helper functions
require_once '../../includes/db_helper.php';
require_once '../../includes/log-data.php';


// Clean any previous output and BOM
ob_clean();

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

// Fetch comprehensive user statistics
$usersCount = 0;
$activeUsers = 0;
$inactiveUsers = 0;
$pendingUsers = 0;
$adminCount = 0;
$vorstandCount = 0;
$memberCount = 0;

$dbPathMembers = __DIR__ . '/../../assets/db/member.db';
$pdo = new PDO('sqlite:' . $dbPathMembers);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    // Total users
    $stmt = $pdo->query('SELECT COUNT(*) AS count FROM mitglieder');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $usersCount = (int)$row['count'];
    }
    
    // Users by status
    $stmt = $pdo->query('SELECT status, COUNT(*) AS count FROM mitglieder GROUP BY status');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = (int)$row['status'];
        $count = (int)$row['count'];
        if ($status === 1) {
            $activeUsers = $count;
        } elseif ($status === 2) {
            $inactiveUsers = $count;
        } elseif ($status === 0) {
            $pendingUsers = $count;
        }
    }
    
    // Users by role
    $stmt = $pdo->query('SELECT rolle, COUNT(*) AS count FROM mitglieder GROUP BY rolle');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rolle = strtolower(trim((string)$row['rolle']));
        $count = (int)$row['count'];
        if ($rolle === 'admin') {
            $adminCount = $count;
        } elseif ($rolle === 'vorstand') {
            $vorstandCount = $count;
        } elseif ($rolle === 'mitglied') {
            $memberCount = $count;
        }
    }
} catch (Exception $e) {
    error_log('dashboard: Fehler beim Abrufen der Statistiken: ' . $e->getMessage());
}

//get "?neu=" from URL
// Handle password change form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password'], $_POST['new_password'], $_POST['confirm_password'])) {
    error_log("Dashboard PW Change: POST received");
    error_log("Dashboard PW Change: POST data: " . print_r($_POST, true));
    
    if (!isset($_SESSION['user_id'])) {
        error_log("Dashboard PW Change: No user_id in session");
        // User not logged in; cannot change password
        header("Location: dashboard.php");
        exit();
    }
    $userId = $_SESSION['user_id'];
    error_log("Dashboard PW Change: User ID = $userId");
    
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validate new password and confirmation
    if ($newPassword !== $confirmPassword) {
        error_log("Dashboard PW Change: Passwords don't match");
        // Passwords do not match
        header("Location: dashboard.php?change_pw=1&error=" . urlencode("Die neuen Passwörter stimmen nicht überein."));
        exit();
    }

    // Verify current password
    error_log("Dashboard PW Change: Verifying current password");
    if (!verifyUserPassword($userId, $currentPassword)) {
        error_log("Dashboard PW Change: Current password incorrect");
        // Current password incorrect
        header("Location: dashboard.php?change_pw=1&error=" . urlencode("Das aktuelle Passwort ist falsch."));
        exit();
    }

    // Update password in database
    error_log("Dashboard PW Change: Updating password");
    if (updateUserPassword($userId, $newPassword)) {
        error_log("Dashboard PW Change: Password updated successfully");
        // Success - set last_login date and last_visited_date
        error_log("Dashboard PW Change: Calling updateLastLoginDate");
        $loginResult = updateLastLoginDate($userId);
        error_log("Dashboard PW Change: updateLastLoginDate returned: " . ($loginResult ? 'true' : 'false'));
        
        error_log("Dashboard PW Change: Calling updateLastVisitedDate");
        $visitResult = updateLastVisitedDate($userId);
        error_log("Dashboard PW Change: updateLastVisitedDate returned: " . ($visitResult ? 'true' : 'false'));

        // log action
        logAction(date('Y-m-d H:i:s'), 'password_change', $_SESSION['name'] . ' changed password', $_SERVER['REMOTE_ADDR'], $_SESSION['user_id']);

        error_log("Dashboard PW Change: Redirecting to ?pw_changed=1");
        header("Location: dashboard.php?pw_changed=1");
        exit();
    } else {
        error_log("Dashboard PW Change: Failed to update password");
        // Failed to update password
        header("Location: dashboard.php?change_pw=1&error=" . urlencode("Fehler beim Ändern des Passworts. Bitte versuchen Sie es erneut."));
        exit();
    }
}

// Handle member edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_member_submit'])) {
    $currentUserId = $_SESSION['user_id'] ?? null;
    $targetId = (int)$_POST['member_id'];
    
    if (!$currentUserId || $targetId <= 0) {
        header('Location: dashboard.php?error=' . urlencode('Ungültige Anfrage.'));
        exit();
    }
    
    $pdo = getMemberDbConnection();
    if (!$pdo) {
        error_log('dashboard: Mitgliederdatenbank nicht erreichbar.');
        header('Location: dashboard.php?error=' . urlencode('Datenbankfehler.'));
        exit();
    }
    
    try {
        $stmt = $pdo->prepare('UPDATE mitglieder SET titel = :titel, name = :name, nachname = :nachname, strasse = :strasse, hausnummer = :hausnummer, adresszusatz = :adresszusatz, plz = :plz, ort = :ort, festnetz = :festnetz, mobilnummer = :mobilnummer, e_mail = :e_mail WHERE id = :id');
        
        $stmt->bindValue(':titel', $_POST['titel'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':name', $_POST['name'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':nachname', $_POST['nachname'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':strasse', $_POST['strasse'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':hausnummer', $_POST['hausnummer'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':adresszusatz', $_POST['adresszusatz'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':plz', $_POST['plz'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':ort', $_POST['ort'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':festnetz', $_POST['telefon'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':mobilnummer', $_POST['mobilnummer'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':e_mail', $_POST['email'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
        
        $stmt->execute();

        // log action
        logAction(date('Y-m-d H:i:s'), 'edit_member', $_SESSION['name'] . ' updated member ' . $targetId, $_SERVER['REMOTE_ADDR'], $_SESSION['user_id']);
            
        
        header('Location: dashboard.php?success=' . urlencode('Mitglied erfolgreich aktualisiert.'));
        exit();
        
    } catch (Exception $e) {
        error_log('dashboard edit error: ' . $e->getMessage());
        header('Location: dashboard.php?error=' . urlencode('Fehler beim Aktualisieren: ' . $e->getMessage()));
        exit();
    }
}

// Context-menu actions (delete, promote, demote, activate, deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'], $_POST['action'])) {
    $currentUserId = $_SESSION['user_id'] ?? null;
    $targetId = (int)$_POST['member_id'];
    $action = (string)$_POST['action'];

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

    $respond = function($success, $message) use ($isAjax) {
        if ($isAjax) {
            // Clean buffer and send clean JSON
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => (bool)$success, 'message' => $message]);
            ob_end_flush();
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
    // Delete should be allowed for Admins and Vorstand; role-changes remain Admin-only
    $canDelete = $isAdmin || $isVorstand;
    $canChangeRole = $isAdmin;

    try {
        if ($action === 'delete') {
            if (!$canDelete) throw new Exception('Keine Berechtigung zum Löschen.');
            if ($targetId === $currentUserId) throw new Exception('Sie können sich nicht selbst löschen.');
            $stmt = $pdo->prepare('DELETE FROM mitglieder WHERE id = :id');
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            logAction(date('Y-m-d H:i:s'), 'delete_member', $_SESSION['name'] . ' deleted member ' . $targetId, $_SERVER['REMOTE_ADDR'], $_SESSION['user_id']);
            $respond(true, 'Benutzer gelöscht.');
        }

        if ($action === 'promote') {
            if (!$canChangeRole) throw new Exception('Keine Berechtigung zum Promoten.');
            
            // Check if a temporary password was provided
            $tempPassword = $_POST['temp_password'] ?? null;
            if (empty($tempPassword)) {
                $respond(false, 'Bitte geben Sie ein vorläufiges Passwort ein.');
            }
            
            // Validate password length
            if (strlen($tempPassword) < 8) {
                $respond(false, 'Das Passwort muss mindestens 8 Zeichen lang sein.');
            }
            
            // Update role and password
            $pdo->beginTransaction();
            try {
                // Update role
                $stmt = $pdo->prepare('UPDATE mitglieder SET rolle = :rolle WHERE id = :id AND rolle != :adminRole');
                $stmt->bindValue(':rolle', 'vorstand', PDO::PARAM_STR);
                $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
                $stmt->bindValue(':adminRole', 'admin', PDO::PARAM_STR);
                $stmt->execute();
                
                // Update password
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE mitglieder SET password = :password WHERE id = :id');
                $stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
                $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
                $stmt->execute();
                
                $pdo->commit();
                logAction(date('Y-m-d H:i:s'), 'promote_member', $_SESSION['name'] . ' promoted member ' . $targetId . ' to vorstand', $_SERVER['REMOTE_ADDR'], $_SESSION['user_id']);
                $respond(true, 'Benutzer zum Vorstand gemacht und Passwort gesetzt.');
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        if ($action === 'demote') {
            if (!$canChangeRole) throw new Exception('Keine Berechtigung zum Demoten.');
            $stmt = $pdo->prepare('SELECT rolle FROM mitglieder WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Benutzer nicht gefunden.');
            if (strtolower((string)$row['rolle']) === 'admin') throw new Exception('Admin kann nicht gedemoted werden.');
            
            // Update role and reset last_visited_date to null so user must set new password on next login
            $stmt = $pdo->prepare('UPDATE mitglieder SET rolle = :rolle, last_visited_date = NULL WHERE id = :id');
            $stmt->bindValue(':rolle', 'mitglied', PDO::PARAM_STR);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            logAction(date('Y-m-d H:i:s'), 'demote_member', $_SESSION['name'] . ' demoted member ' . $targetId . ' from vorstand', $_SERVER['REMOTE_ADDR'], $_SESSION['user_id']);
            $respond(true, 'Benutzer gedemoted.');
        }

        if ($action === 'activate' || $action === 'deactivate') {
            if (!$canModifyStatus) throw new Exception('Keine Berechtigung zur Statusänderung.');
            $newStatus = $action === 'activate' ? 1 : 2;
            $stmt = $pdo->prepare('UPDATE mitglieder SET status = :status WHERE id = :id');
            $stmt->bindValue(':status', $newStatus, PDO::PARAM_INT);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            logAction(date('Y-m-d H:i:s'), $action === 'activate' ? 'activate_member' : 'deactivate_member', $_SESSION['name'] . ' ' . ($action === 'activate' ? 'activated' : 'deactivated') . ' member ' . $targetId, $_SERVER['REMOTE_ADDR'], $_SESSION['user_id']);
            $msg = $action === 'activate' ? 'Benutzer aktiviert.' : 'Benutzer deaktiviert.';
            $respond(true, $msg);
        }

        $respond(false, 'Unbekannte Aktion.');

    } catch (Exception $e) {
        error_log('dashboard action error: ' . $e->getMessage());
        $respond(false, $e->getMessage());
    }
}

// Check if user needs to set up password (no last_visited_date) - AFTER POST handlers
// This only runs for GET requests or after all POST processing is complete
$needsPasswordSetup = !userHasCompletedPasswordSetup($userId);
error_log("Dashboard: User $userId - needsPasswordSetup = " . ($needsPasswordSetup ? 'true' : 'false'));
error_log("Dashboard: User $userId - GET params: neu=" . (isset($_GET['neu']) ? '1' : '0') . ", change_pw=" . (isset($_GET['change_pw']) ? '1' : '0') . ", pw_changed=" . (isset($_GET['pw_changed']) ? '1' : '0'));

// If user hasn't completed password setup and no neu/change_pw parameter, redirect with neu flag
if ($needsPasswordSetup && !isset($_GET['neu']) && !isset($_GET['change_pw']) && !isset($_GET['pw_changed'])) {
    error_log("Dashboard: User $userId - Redirecting to ?neu=1");
    header("Location: dashboard.php?neu=1");
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
    <style>
        #heading{
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between;
        }
        #heading #right{
            transform: translateX(0);
            background-color: transparent;
            height: auto;
            display: flex;
            align-items: end;
            margin-right: 10px;
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
        <script>
            // Submit context action via AJAX and update UI without reload
            // Expose current user id and admin-flag to client JS so the context menu can adapt
            window.currentUserId = <?php echo json_encode($userId); ?>;
            window.currentUserIsAdmin = <?php echo json_encode(hasAdminRole($userId)); ?>;
            window.currentUserIsVorstand = <?php echo json_encode(hasVorstandRole($userId)); ?>;
            async function submitContextActionAjax(action, memberId, tempPassword) {
                try {
                    const params = new URLSearchParams();
                    params.append('action', action);
                    params.append('member_id', memberId);
                    if (tempPassword) {
                        params.append('temp_password', tempPassword);
                    }

                    const res = await fetch('dashboard.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: params.toString()
                    });

                    // Defensive parsing: some server responses may be HTML or error text.
                    const raw = await res.text();
                    let data = null;
                    try {
                        data = JSON.parse(raw);
                    } catch (err) {
                        console.error('submitContextActionAjax: failed to parse JSON response:', raw);
                        alert('Server antwortete nicht mit JSON. Siehe Konsole für Details.');
                        return;
                    }

                    if (!data || !data.success) {
                        alert('Fehler: ' + (data && data.message ? data.message : 'Unbekannter Fehler'));
                        return;
                    }

                    // Update DOM based on action
                    const el = document.querySelector('.member[data-member-id="' + memberId + '"]');
                    if (action === 'delete') {
                        if (el) {
                            el.style.transition = 'opacity 0.3s';
                            el.style.opacity = '0';
                            setTimeout(() => el.remove(), 300);
                            // Success feedback
                                var successBar = document.getElementById("success-bar");
                                var successMessageElem = document.getElementById("success-message");
                                var successTimeline = document.getElementById("success-timeline");
                                successMessageElem.textContent = "Eintrag erfolgreich gelöscht.";
                                successBar.style.display = "flex";
                                // Animate timeline
                                successTimeline.style.animation = "timelineAnimation 2s linear forwards";
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

            // Store pending promote action
            let pendingPromoteMemberId = null;
            // Show password popup for promote action
            function showPromotePasswordPopup(memberId) {
                pendingPromoteMemberId = memberId;
                document.getElementById('promote-password-popup').style.display = 'flex';
                document.getElementById('promote-temp-password').value = '';
                document.getElementById('promote-temp-password-confirm').value = '';
                document.getElementById('promote-temp-password').focus();
            }
            // Cancel promote action
            function cancelPromote() {
                pendingPromoteMemberId = null;
                document.getElementById('promote-password-popup').style.display = 'none';
            }
            // Confirm promote with password
            function confirmPromote() {
                const password = document.getElementById('promote-temp-password').value;
                const passwordConfirm = document.getElementById('promote-temp-password-confirm').value;
                if (!password || password.length < 8) {
                    alert('Das Passwort muss mindestens 8 Zeichen lang sein.');
                    return;
                }
                if (password !== passwordConfirm) {
                    alert('Die Passwörter stimmen nicht überein.');
                    return;
                }
                if (pendingPromoteMemberId) {
                    document.getElementById('promote-password-popup').style.display = 'none';
                    submitContextActionAjax('promote', pendingPromoteMemberId, password);
                    pendingPromoteMemberId = null;
                }
            }
            // Convenience wrapper for non-AJAX fallback: set hidden fields and submit
            function submitContextAction(action, memberId) {
                const form = document.getElementById('member-context-menu-form');
                if (!form) return;
                // If fetch is available, use AJAX
                if (window.fetch) {
                    // For promote action, show password popup first
                    if (action === 'promote') {
                        showPromotePasswordPopup(memberId);
                        return;
                    }
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
    <?php
        // Show success banner if redirected
        if (isset($_GET['success'])) {
            $successMessage = htmlspecialchars($_GET['success']);
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    var successBar = document.getElementById("success-bar");
                    var successMessageElem = document.getElementById("success-message");
                    var successTimeline = document.getElementById("success-timeline");
                    successMessageElem.textContent = ' . json_encode($successMessage) . ';
                    successBar.style.display = "flex";
                    // Animate timeline
                    successTimeline.style.animation = "timelineAnimation 2s linear forwards";
                    // Hide after 5 seconds
                    setTimeout(function() {
                        successBar.style.display = "none";
                    }, 5000);
                });
            </script>';
        }
    ?>
    <div class="notification-bar center" id="success-bar" style="display: none;">
        <span class="material-symbols-outlined">check_circle</span>
        <span id="success-message"></span>
        <span class="timeline" id="success-timeline"></span>
    </div>
    <div class="popup" id="promote-password-popup">
        <div class="popup-content">
            <h2>Vorstandsrolle zuweisen</h2>
            <h3>Bitte geben Sie ein vorläufiges Passwort für den neuen Vorstand ein.</h3>
            <div class="form-group">
                <label for="promote-temp-password">Vorläufiges Passwort:</label>
                <input type="password" id="promote-temp-password" required minlength="8">
                <small style="color: #666;">Mindestens 8 Zeichen</small>
            </div>
            <div class="form-group">
                <label for="promote-temp-password-confirm">Passwort bestätigen:</label>
                <input type="password" id="promote-temp-password-confirm" required minlength="8">
            </div>
            <div class="buttons">
                <button type="button" class="abbrechen" onclick="cancelPromote()">Abbrechen</button>
                <button type="button" class="submit" onclick="confirmPromote()">Bestätigen</button>
            </div>
        </div>
    </div>
    <div class="popup" <?php if (isset($_GET['neu']) || isset($_GET['change_pw'])) echo 'style="display: flex;"'; ?> id="pw-change-popup">
        <div class="popup-content">
            <h2>Es scheint, als wäre dies ihr erster Login.</h2>
            <h3>Bitte legen Sie sich ein neues Passwort fest.</h3>
            <?php if (isset($_GET['error'])): ?>
                <div class="error-message" style="background-color: #ffebee; border: 1px solid #ef5350; color: #c62828; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            <form action="dashboard.php" method="post" id="pw-change-form">
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
                <button type="submit" class="submit" onclick="console.log('Submit button clicked');">Passwort ändern</button>
            </form>
        </div>
    </div>
    <div class="popup" <?php if (isset($_GET['neu']) || isset($_GET['change_pw'])) echo 'style="display: flex;"'; ?> id="password-change-popup">
        <div class="popup-content">
            <h2>Passwort ändern</h2>
            <h3>Bitte legen Sie sich ein neues Passwort fest.</h3>
            <?php if (isset($_GET['error'])): ?>
                <div class="error-message" style="background-color: #ffebee; border: 1px solid #ef5350; color: #c62828; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            <form action="dashboard.php" method="post" id="pw-change-form">
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
                <button type="submit" class="submit" onclick="console.log('Submit button clicked');">Passwort ändern</button>
            </form>
        </div>
    </div>
    <div id="main">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Gesamt-Mitglieder</h3>
                <div class="value"><?php echo number_format($usersCount); ?></div>
            </div>
            <div class="stat-card">
                <h3>Aktive Mitglieder</h3>
                <div class="value"><?php echo number_format($activeUsers); ?></div>
            </div>
            <div class="stat-card">
                <h3>Inaktive Mitglieder</h3>
                <div class="value"><?php echo number_format($inactiveUsers); ?></div>
            </div>
            <div class="stat-card">
                <h3>Ausstehende Mitglieder</h3>
                <div class="value"><?php echo number_format($pendingUsers); ?></div>
            </div>
            <div class="stat-card">
                <h3>Admins</h3>
                <div class="value"><?php echo number_format($adminCount); ?></div>
            </div>
            <div class="stat-card">
                <h3>Vorstand</h3>
                <div class="value"><?php echo number_format($vorstandCount); ?></div>
            </div>
            <div class="stat-card">
                <h3>Mitglieder (Rolle)</h3>
                <div class="value"><?php echo number_format($memberCount); ?></div>
            </div>
            <div class="stat-card">
                <h3>Aktivitätsrate</h3>
                <div class="value"><?php echo $usersCount > 0 ? round(($activeUsers / $usersCount) * 100, 1) : 0; ?>%</div>
            </div>
        </div>
        <div id="member-output">
            <div id="member-output-heading">
                <button class="member-button ganzer-name" onclick="window.location.href='dashboard.php?sort=nachname'">Name</button>
                <div class="role-status flex-row">
                    <button class="member-button role" onclick="window.location.href='dashboard.php?sort=rolle'">Rolle</button>
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
                    // Determine sorting
                    $allowedSorts = ['name', 'nachname', 'rolle', 'status', 'join_date'];
                    $sort = 'nachname'; // default sort
                    if (isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts)) {
                        $sort = $_GET['sort'];
                    }
                    // Get all members
                    $stmt = $pdo->prepare('SELECT * FROM mitglieder ORDER BY ' . $sort . ' ASC');
                    $stmt->execute();
                    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($members && count($members) > 0) {
                        foreach ($members as $member) {
                            // Defaults for role and status to ensure variables are always set
                            $roleClass = 'member';
                            $roleIcon = 'person';
                            $roleText = 'Mitglied';
                            
                            // Prepare data attributes for JavaScript
                            $dataAttrs = sprintf(
                                'data-titel="%s" data-name="%s" data-nachname="%s" data-strasse="%s" data-hausnummer="%s" data-adresszusatz="%s" data-plz="%s" data-ort="%s" data-festnetz="%s" data-mobilnummer="%s" data-email="%s"',
                                htmlspecialchars($member['titel'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['name'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['nachname'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['strasse'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['hausnummer'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['adresszusatz'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['PLZ'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['ort'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['festnetz'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['mobilnummer'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['e_mail'] ?? '', ENT_QUOTES),
                                htmlspecialchars($member['info'] ?? '', ENT_QUOTES)
                            );

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
                            $address1 = !empty($member['strasse']) ? htmlspecialchars($member['strasse']) . ' ' . htmlspecialchars($member['hausnummer']) : '';
                            $address2 = (!empty($member['PLZ']) ? htmlspecialchars($member['PLZ']) . ' ' : '') . 
                                        (!empty($member['ort']) ? htmlspecialchars($member['ort']) : '').
                                        (!empty($member['adresszusatz']) ? '<br>'.htmlspecialchars($member['adresszusatz']) : '');
                            
                            $hasAddress = !empty($address1) || !empty($address2);
                            ?>
                            <div class="member" data-member-id="<?php echo (int)$member['id']; ?>" <?php echo $dataAttrs; ?> oncontextmenu="opencontextMenu('<?php echo htmlspecialchars($member['id']); ?>'); return false;">
                                <div class="member-top">
                                    <div class="ganzer-name">
                                        <h2 class="nachname"><?php echo htmlspecialchars($member['titel'] ?? '') . ' ' . htmlspecialchars($member['nachname'] ?? ''); ?>, <?php echo htmlspecialchars($member['name'] ?? ''); ?></h2>
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
                                        <div class="phone center">
                                            <span class="material-symbols-outlined phone-symbol">phone</span>
                                            <span class="phone-text"><?php echo htmlspecialchars($telefonVal); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($mobilVal)): ?>
                                        <div class="mobile center">
                                            <span class="material-symbols-outlined mobile-symbol">smartphone</span>
                                            <span class="mobile-text"><?php echo htmlspecialchars($mobilVal); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($member['e_mail'])): ?>
                                        <div class="email center">
                                            <span class="material-symbols-outlined email-symbol">email</span>
                                            <span class="email-text"><?php echo htmlspecialchars($member['e_mail']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($member['info'])): ?>
                                        <div class="info-text center">
                                            <span class="material-symbols-outlined email-symbol">sticky_note_2</span>
                                            <span class="email-text"><?php echo htmlspecialchars($member['info']); ?></span>
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
                <button type="button" id="edit-password-member">
                    <span class="material-symbols-outlined">key</span>
                    <span class="text">Passwort ändern</span>
                </button>
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
        <div class="popup center" id="member-edit-popup">
            <div id="member-edit-form" class="popup-content">
                <button class="close-popup-button center" onclick="closeMemberEditPopup()">
                    <span class="material-symbols-outlined">close</span>
                </button>
                <form action="dashboard.php" method="post">
                    <input type="hidden" id="edit-member-id" name="member_id" value="">
                    <input type="hidden" name="edit_member_submit" value="1">
                    <h2>Mitglied bearbeiten</h2>
                    <h3>Grunddaten</h3>
                    <div class="row">
                        <div class="short form-group">
                            <label for="edit-titel">Titel:</label>
                            <input type="text" id="edit-titel" name="titel" value="">
                        </div>
                        <div class="form-group">
                            <label for ="edit-name">Vorname:</label>
                            <input type="text" id="edit-name" name="name" value="">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit-nachname">Nachname:</label>
                        <input type="text" id="edit-nachname" name="nachname" value="">
                    </div>
                    <h3>Addresse</h3>
                    <div class="row">
                        <div class="form-group" id="form-group-street">
                            <label for="edit-strasse">Straße:</label>
                            <input type="text" id="edit-strasse" name="strasse" value="">
                        </div>
                        <div class="short form-group">
                            <label for="edit-hausnummer">Hausnummer:</label>
                            <input type="text" id="edit-hausnummer" name="hausnummer" value="">
                        </div>
                    </div>
                    <div class="row">
                        <div class="short form-group">
                            <label for="edit-plz">PLZ:</label>
                            <input type="text" id="edit-plz" name="plz" value="">
                        </div>
                        <div class="form-group">
                            <label for="edit-ort">Ort:</label>
                            <input type="text" id="edit-ort" name="ort" value="">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit-adresszusatz">Adresszusatz:</label>
                        <input type="text" id="edit-adresszusatz" name="adresszusatz" value="">
                    </div>
                    <h3>Kontaktinformationen</h3>
                    <div class="form-group">
                        <label for="edit-email">E-Mail:</label>
                        <input type="email" id="edit-email" name="email" value="">
                    </div>
                    <div class="form-group">
                        <label for="edit-telefon">Telefon:</label>
                        <input type="tel" id="edit-telefon" name="telefon" value="">
                    </div>
                    <div class="form-group">
                        <label for="edit-mobilnummer">Mobilnummer:</label>
                        <input type="tel" id="edit-mobilnummer" name="mobilnummer" value="">
                    </div>
                    <div class="buttons">
                        <button type="button" class="abbrechen" onclick="closeMemberEditPopup()">Abbrechen</button>
                        <button type="submit" class="submit">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="footer" class="center">
        <?php include '../../pages/internes/footer.php'; ?>
    </div>
</body>
</html>