<?php
// Test if config-helper.php loads correctly
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting test...\n";

// Try to load config-helper
require_once 'includes/config-helper.php';

echo "config-helper.php loaded successfully!\n";

// Check if function exists
if (function_exists('getConfigValue')) {
    echo "getConfigValue() function EXISTS!\n";
    
    // Try to call it
    $result = getConfigValue('banner_text');
    echo "Result: " . var_export($result, true) . "\n";
} else {
    echo "ERROR: getConfigValue() function NOT FOUND!\n";
}

echo "Test complete.\n";
