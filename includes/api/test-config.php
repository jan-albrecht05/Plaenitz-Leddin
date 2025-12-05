<?php
    require_once '../session-config.php';
    startSecureSession();
    require_once '../config-helper.php';

    header('Content-Type: application/json');

    // Test if config.db is accessible
    global $config;
    
    $result = [
        'config_db_exists' => $config !== null,
        'test_key' => null,
        'error' => null
    ];

    if ($config !== null) {
        try {
            // Try to get a test value
            $testValue = getConfigValue('show_gif');
            $result['test_key'] = $testValue;
            
            // Try to set a test value
            $setResult = setConfigValue('test_key', 'test_value');
            $result['set_result'] = $setResult;
            
            // Try to read it back
            $readBack = getConfigValue('test_key');
            $result['read_back'] = $readBack;
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
    } else {
        $result['error'] = 'config.db connection is null';
    }

    echo json_encode($result, JSON_PRETTY_PRINT);
?>
