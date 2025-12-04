<?php
    // conn to config.db
    $config = null;
    try {
        $config = new SQLite3(__DIR__ . '/../assets/db/config.db', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    } catch (Exception $e) {
        error_log('Failed to open config.db: ' . $e->getMessage());
        // Don't die - gracefully degrade instead
        $config = null;
    }

    // get config value by key
    function getConfigValue($key) {
        global $config;
        
        // Graceful degradation if DB connection failed
        if ($config === null) {
            error_log("getConfigValue called but config.db connection is not available for key: $key");
            return null;
        }
        
        try {
            $stmt = $config->prepare('SELECT inhalt FROM config WHERE name = :key');
            // bind the same placeholder name used in the SQL statement
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            // Normalize return: trim strings and return null when not found
            if (!$row) {
                return null;
            }
            return is_string($row['inhalt']) ? trim($row['inhalt']) : $row['inhalt'];
        } catch (Exception $e) {
            error_log("getConfigValue error for key '$key': " . $e->getMessage());
            return null;
        }
    }