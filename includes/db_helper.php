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
    $validRoles = ['admin', 'vorstand', 'member'];
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
?>