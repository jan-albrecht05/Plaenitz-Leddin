<?php
/**
 * Database helper functions for user authentication and role management
 */

/**
 * Get PDO connection to member database
 * @return PDO|null
 */
function getMemberDbConnection() {
    $dbPath = __DIR__ . '/../assets/db/member.db';
    
    if (!file_exists($dbPath)) {
        error_log("member.db: database file not found: $dbPath");
        return null;
    }
    
    if (!is_readable($dbPath)) {
        error_log("member.db: database file not readable by PHP process: $dbPath");
        return null;
    }
    
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (Exception $e) {
        error_log('member.db: DB connection error - ' . $e->getMessage());
        return null;
    }
}

/**
 * Authenticate user and return user data with roles
 * @param string $name
 * @param string $nachname
 * @param string $password
 * @return array|false Returns user data array or false if authentication fails
 */
function authenticateUser($name, $nachname, $password) {
    $pdo = getMemberDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        // Expecting columns: id, name, nachname, password, rolle
        $stmt = $pdo->prepare('SELECT id, name, nachname, password, rolle FROM mitglieder WHERE name = :name AND nachname = :nachname LIMIT 1');
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':nachname', $nachname, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }

        return false;
    } catch (Exception $e) {
        error_log('authenticateUser: DB error - ' . $e->getMessage());
        return false;
    }
}

/**
 * Get user role by user ID
 * @param int $userId
 * @return string|false Returns role string or false if not found
 */
function getUserRole($userId) {
    $pdo = getMemberDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT rolle FROM mitglieder WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['rolle'] : false;
    } catch (Exception $e) {
        error_log('getUserRole: DB error - ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has admin or vorstand role
 * @param int $userId
 * @return bool
 */
function hasAdminOrVorstandRole($userId) {
    $role = getUserRole($userId);
    return $role && (strtolower($role) === 'admin' || strtolower($role) === 'vorstand');
}

/**
 * Check if user has admin role
 * @param int $userId
 * @return bool
 */
function hasAdminRole($userId) {
    $role = getUserRole($userId);
    return $role && strtolower($role) === 'admin';
}

/**
 * Check if user has vorstand role
 * @param int $userId
 * @return bool
 */
function hasVorstandRole($userId) {
    $role = getUserRole($userId);
    return $role && strtolower($role) === 'vorstand';
}

/**
 * Create a new user in the database
 * @param string $username
 * @param string $password
 * @param string $role Role: 'admin', 'vorstand', or 'member'
 * @param string $email Optional email address
 * @return array Returns array with 'success' boolean and 'error' message if failed
 */
function createUser($name, $nachname, $password, $role = 'member', $email = '') {
    $pdo = getMemberDbConnection();
    if (!$pdo) {
        return ['success' => false, 'error' => 'Datenbankverbindung fehlgeschlagen'];
    }

    // Validate role
    $validRoles = ['admin', 'vorstand', 'Mitglied'];
    if (!in_array(strtolower($role), $validRoles)) {
        return ['success' => false, 'error' => 'Ungültige Rolle'];
    }

    try {
        // Check if the name + nachname combination already exists
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM mitglieder WHERE name = :name AND nachname = :nachname');
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':nachname', $nachname, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'error' => 'Benutzer bereits vorhanden'];
        }

        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
    $stmt = $pdo->prepare('INSERT INTO mitglieder (name, nachname, password, rolle, e_mail) VALUES (:name, :nachname, :password, :rolle, :e_mail)');
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':nachname', $nachname, PDO::PARAM_STR);
        $stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindValue(':rolle', strtolower($role), PDO::PARAM_STR);
    $stmt->bindValue(':e_mail', $email, PDO::PARAM_STR);

        // Debug: capture statement params for troubleshooting
        try {
            // capture debug output
            ob_start();
            $stmt->debugDumpParams();
            $debug = ob_get_clean();
            error_log('createUser: prepared statement debug:\n' . $debug);

            $stmt->execute();
            return ['success' => true, 'error' => ''];
        } catch (Exception $ex) {
            // Log the debug info plus exception
            error_log('createUser: execute failed - ' . $ex->getMessage());
            ob_start();
            $stmt->debugDumpParams();
            $debug = ob_get_clean();
            error_log('createUser: debug after failure:\n' . $debug);
            throw $ex; // rethrow so caller sees the original exception
        }

    } catch (Exception $e) {
        error_log('createUser: DB error - ' . $e->getMessage());
        return ['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()];
    }
}

/**
 * Update last visited date for user
 * @param int $userId
 * @return bool Returns true on success, false on failure
 */
function updateLastVisitedDate($userId) {
    $pdo = getMemberDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        // Set last_visited_date to current UTC timestamp
        $stmt = $pdo->prepare('UPDATE mitglieder SET last_visited_date = :last_visited_date WHERE id = :id');
        $stmt->bindValue(':last_visited_date', gmdate('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    } catch (Exception $e) {
        error_log('updateLastVisitedDate: DB error - ' . $e->getMessage());
        return false;
    }
}

/**
 * Verify user password
 * @param int $userId
 * @param string $password
 * @return bool Returns true if password matches, false otherwise
 */
function verifyUserPassword($userId, $password) {
    $pdo = getMemberDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT password FROM mitglieder WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && password_verify($password, $result['password'])) {
            return true;
        }

        return false;
    } catch (Exception $e) {
        error_log('verifyUserPassword: DB error - ' . $e->getMessage());
        return false;
    }
}

/**
 * Update user password
 * @param int $userId
 * @param string $newPassword
 * @return bool Returns true on success, false on failure
 */
function updateUserPassword($userId, $newPassword) {
    $pdo = getMemberDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password in database
        $stmt = $pdo->prepare('UPDATE mitglieder SET password = :password WHERE id = :id');
        $stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    } catch (Exception $e) {
        error_log('updateUserPassword: DB error - ' . $e->getMessage());
        return false;
    }
}

/**
 * Update last login date for user (set when user successfully changes password for first time)
 * @param int $userId
 * @return bool Returns true on success, false on failure
 */
function updateLastLoginDate($userId) {
    $pdo = getMemberDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        // Set last_login to current UTC timestamp
        $stmt = $pdo->prepare('UPDATE mitglieder SET last_visited_date = :last_visited_date WHERE id = :id');
        $stmt->bindValue(':last_visited_date', gmdate('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    } catch (Exception $e) {
        error_log('updateLastLoginDate: DB error - ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has completed initial password setup (has last_visited_date)
 * @param int $userId
 * @return bool Returns true if user has last_visited_date set, false otherwise
 */
function userHasCompletedPasswordSetup($userId) {
    $pdo = getMemberDbConnection();
    if (!$pdo) {
        error_log("userHasCompletedPasswordSetup: No PDO connection for user $userId");
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT last_visited_date FROM mitglieder WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("userHasCompletedPasswordSetup: User $userId - Result: " . print_r($result, true));
        error_log("userHasCompletedPasswordSetup: User $userId - last_visited_date = " . ($result['last_visited_date'] ?? 'NULL'));
        error_log("userHasCompletedPasswordSetup: User $userId - isEmpty check = " . (empty($result['last_visited_date']) ? 'true' : 'false'));

        // Return true if last_visited_date exists and is not null/empty
        $hasCompleted = $result && !empty($result['last_visited_date']);
        error_log("userHasCompletedPasswordSetup: User $userId - Returning: " . ($hasCompleted ? 'true' : 'false'));
        return $hasCompleted;
    } catch (Exception $e) {
        error_log('userHasCompletedPasswordSetup: DB error - ' . $e->getMessage());
        return false;
    }
}
?>