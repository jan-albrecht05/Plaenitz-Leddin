<?php
    require_once __DIR__ . '/log-data.php';
    
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

    /**
     * Set a config value
     * @param string $key - Config key
     * @param mixed $value - Config value
     * @return bool - Success status
     */
    function setConfigValue($key, $value) {
        global $config;
        
        if ($config === null) {
            error_log("setConfigValue called but config.db connection is not available for key: $key");
            return false;
        }
        
        try {
            // Check if key exists
            $stmt = $config->prepare('SELECT COUNT(*) as cnt FROM config WHERE name = :key');
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $exists = $row['cnt'] > 0;
            
            if ($exists) {
                // Update existing
                $stmt = $config->prepare('UPDATE config SET inhalt = :value WHERE name = :key');
            } else {
                // Insert new
                $stmt = $config->prepare('INSERT INTO config (name, inhalt) VALUES (:key, :value)');
            }
            
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->bindValue(':value', (string)$value, SQLITE3_TEXT);
            $stmt->execute();
            $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : '';
            logAction('', 'set_config_'.$key, "$key → $value", '', $currentUserId);
            return true;
        } catch (Exception $e) {
            error_log("setConfigValue error for key '$key': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add image to history table (icons, logos, banner_images, gifs)
     * @param string $table - Table name
     * @param string $name - Display name
     * @param string $link - Full filename with extension
     * @param string $dimensions - Dimensions (e.g., "1920x1080")
     * @param string $typ - File type/extension
     * @return bool - Success status
     */
    function addImageToHistory($table, $name, $link, $dimensions, $typ) {
        global $config;
        
        if ($config === null) {
            error_log("addImageToHistory called but config.db connection is not available");
            return false;
        }
        
        try {
            $stmt = $config->prepare('INSERT INTO ' . $table . ' (name, link, dimensions, typ, datum) VALUES (:name, :link, :dimensions, :typ, :datum)');
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':link', $link, SQLITE3_TEXT);
            $stmt->bindValue(':dimensions', $dimensions, SQLITE3_TEXT);
            $stmt->bindValue(':typ', $typ, SQLITE3_TEXT);
            $stmt->bindValue(':datum', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->execute();
            $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : '';
            logAction('', 'add_image_history_'.$table, "Table: $table, Name: $name", '', $currentUserId);
            return true;
        } catch (Exception $e) {
            error_log("addImageToHistory error for table '$table': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all images from history table
     * @param string $table - Table name
     * @return array - Array of images
     */
    function getImagesFromHistory($table) {
        global $config;
        
        if ($config === null) {
            return [];
        }
        
        try {
            $stmt = $config->prepare('SELECT * FROM ' . $table . ' ORDER BY datum DESC');
            $result = $stmt->execute();
            $images = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $images[] = $row;
            }
            return $images;
        } catch (Exception $e) {
            error_log("getImagesFromHistory error for table '$table': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete image from history table
     * @param string $table - Table name
     * @param int $id - Image ID
     * @return bool - Success status
     */
    function deleteImageFromHistory($table, $id) {
        global $config;
        
        if ($config === null) {
            return false;
        }
        
        try {
            $stmt = $config->prepare('DELETE FROM ' . $table . ' WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : '';
            logAction('', 'delete_image_history_'.$table, "Table: $table, ID: $id", '', $currentUserId);
            return true;
        } catch (Exception $e) {
            error_log("deleteImageFromHistory error for table '$table': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add color to history
     * @param string $farbcode - Hex color code
     * @return bool - Success status
     */
    function addColorToHistory($farbcode) {
        global $config;
        
        if ($config === null) {
            return false;
        }
        
        try {
            $stmt = $config->prepare('INSERT INTO colors (farbcode, datum) VALUES (:farbcode, :datum)');
            $stmt->bindValue(':farbcode', $farbcode, SQLITE3_TEXT);
            $stmt->bindValue(':datum', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->execute();
            $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : '';
            return true;
        } catch (Exception $e) {
            error_log("addColorToHistory error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get color history
     * @return array - Array of colors
     */
    function getColorHistory() {
        global $config;
        
        if ($config === null) {
            return [];
        }
        
        try {
            $stmt = $config->prepare('SELECT * FROM colors ORDER BY datum DESC');
            $result = $stmt->execute();
            $colors = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $colors[] = $row;
            }
            return $colors;
        } catch (Exception $e) {
            error_log("getColorHistory error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add message (notification or maintenance)
     * @param string $typ - "notification" or "maintenance"
     * @param string $heading - Message heading
     * @param string $text - Message text
     * @param string $startzeit - Start date/time (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
     * @param string $endzeit - End date/time (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
     * @param int $autor - Author user ID
     * @return bool - Success status
     */
    function addMessage($typ, $heading, $text, $startzeit, $endzeit, $autor) {
        global $config;
        
        if ($config === null) {
            return false;
        }
        
        try {
            $stmt = $config->prepare('INSERT INTO messages (typ, heading, text, startzeit, endzeit, autor) VALUES (:typ, :heading, :text, :startzeit, :endzeit, :autor)');
            $stmt->bindValue(':typ', $typ, SQLITE3_TEXT);
            $stmt->bindValue(':heading', $heading, SQLITE3_TEXT);
            $stmt->bindValue(':text', $text, SQLITE3_TEXT);
            $stmt->bindValue(':startzeit', $startzeit, SQLITE3_TEXT);
            $stmt->bindValue(':endzeit', $endzeit, SQLITE3_TEXT);
            $stmt->bindValue(':autor', $autor, SQLITE3_INTEGER);
            $stmt->execute();
            $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : '';
            logAction('', 'add_message', "Type: $typ, Heading: $heading", '', $currentUserId);
            return true;
        } catch (Exception $e) {
            error_log("addMessage error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all messages by type
     * @param string $typ - "notification" or "maintenance"
     * @return array - Array of messages
     */
    function getMessagesByType($typ) {
        global $config;
        
        if ($config === null) {
            return [];
        }
        
        try {
            $stmt = $config->prepare('SELECT * FROM messages WHERE typ = :typ ORDER BY startzeit DESC');
            $stmt->bindValue(':typ', $typ, SQLITE3_TEXT);
            $result = $stmt->execute();
            $messages = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $messages[] = $row;
            }
            return $messages;
        } catch (Exception $e) {
            error_log("getMessagesByType error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get active messages by type (within start and end time)
     * @param string $typ - "notification" or "maintenance"
     * @return array - Array of active messages
     */
    function getActiveMessagesByType($typ) {
        global $config;
        
        if ($config === null) {
            return [];
        }
        
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $config->prepare('SELECT * FROM messages WHERE typ = :typ AND startzeit <= :now AND endzeit >= :now ORDER BY startzeit DESC');
            $stmt->bindValue(':typ', $typ, SQLITE3_TEXT);
            $stmt->bindValue(':now', $now, SQLITE3_TEXT);
            $result = $stmt->execute();
            $messages = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $messages[] = $row;
            }
            return $messages;
        } catch (Exception $e) {
            error_log("getActiveMessagesByType error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update message
     * @param int $id - Message ID
     * @param string $heading - Updated heading
     * @param string $text - Updated text
     * @param string $startzeit - Updated start time
     * @param string $endzeit - Updated end time
     * @return bool - Success status
     */
    function updateMessage($id, $heading, $text, $startzeit, $endzeit) {
        global $config;
        
        if ($config === null) {
            return false;
        }
        
        try {
            $stmt = $config->prepare('UPDATE messages SET heading = :heading, text = :text, startzeit = :startzeit, endzeit = :endzeit WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->bindValue(':heading', $heading, SQLITE3_TEXT);
            $stmt->bindValue(':text', $text, SQLITE3_TEXT);
            $stmt->bindValue(':startzeit', $startzeit, SQLITE3_TEXT);
            $stmt->bindValue(':endzeit', $endzeit, SQLITE3_TEXT);
            $stmt->execute();
            $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : '';
            logAction('', 'update_message', "ID: $id, Heading: $heading", '', $currentUserId);
            return true;
        } catch (Exception $e) {
            error_log("updateMessage error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete message
     * @param int $id - Message ID
     * @return bool - Success status
     */
    function deleteMessage($id) {
        global $config;
        
        if ($config === null) {
            return false;
        }
        
        try {
            $stmt = $config->prepare('DELETE FROM messages WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : '';
            logAction('', 'delete_message', "ID: $id", '', $currentUserId);
            return true;
        } catch (Exception $e) {
            error_log("deleteMessage error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add banner text to history
     * @param string $text - Banner text
     * @return bool - Success status
     */
    function addBannerTextToHistory($text) {
        global $config;
        
        if ($config === null) {
            return false;
        }
        
        try {
            $stmt = $config->prepare('INSERT INTO banner_texte (inhalt, datum) VALUES (:inhalt, :datum)');
            $stmt->bindValue(':inhalt', $text, SQLITE3_TEXT);
            $stmt->bindValue(':datum', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->execute();
            $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : '';
            logAction('', 'add_banner_text', "Text: $text", '', $currentUserId);
            return true;
        } catch (Exception $e) {
            error_log("addBannerTextToHistory error: " . $e->getMessage());
            return false;
        }
    }
?>