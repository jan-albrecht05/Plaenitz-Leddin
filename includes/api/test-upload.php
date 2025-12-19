<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing includes...<br>";

try {
    require_once '../session-config.php';
    echo "session-config.php loaded<br>";
    
    startSecureSession();
    echo "Session started<br>";
    
    require_once '../db_helper.php';
    echo "db_helper.php loaded<br>";
    
    require_once '../config-helper.php';
    echo "config-helper.php loaded<br>";
    
    if (!isset($_SESSION['user_id'])) {
        echo "No user logged in<br>";
    } else {
        echo "User ID: " . $_SESSION['user_id'] . "<br>";
        
        $isAdmin = hasAdminRole($_SESSION['user_id']);
        echo "Is Admin: " . ($isAdmin ? 'Yes' : 'No') . "<br>";
        
        $isVorstand = hasVorstandRole($_SESSION['user_id']);
        echo "Is Vorstand: " . ($isVorstand ? 'Yes' : 'No') . "<br>";
    }
    
    global $config;
    if ($config) {
        echo "Config DB connected<br>";
    } else {
        echo "Config DB NOT connected<br>";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?>
