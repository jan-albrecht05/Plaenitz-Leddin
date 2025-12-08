<?php
// IP Blocker Helper Functions
// Manages IP blocking in .htaccess file

/**
 * Get path to .htaccess file
 */
function getHtaccessPath() {
    return __DIR__ . '/../.htaccess';
}

/**
 * Get current blocked IPs from .htaccess
 * Returns array of blocked IP addresses
 */
function getBlockedIPs() {
    $htaccessPath = getHtaccessPath();
    
    if (!file_exists($htaccessPath)) {
        return [];
    }
    
    $content = file_get_contents($htaccessPath);
    $blockedIPs = [];
    
    // Match both "Require not ip" and "Deny from" formats
    preg_match_all('/(?:Require not ip|Deny from)\s+([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}(?:\/[0-9]{1,2})?)/i', $content, $matches);
    
    if (!empty($matches[1])) {
        $blockedIPs = array_unique($matches[1]);
    }
    
    return $blockedIPs;
}

/**
 * Check if an IP is already blocked
 */
function isIPBlocked($ip) {
    $blockedIPs = getBlockedIPs();
    return in_array($ip, $blockedIPs);
}

/**
 * Block an IP address by adding it to .htaccess
 * Returns true on success, false on failure
 */
function blockIP($ip) {
    // Validate IP format
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        error_log('blockIP: Invalid IP format: ' . $ip);
        return false;
    }
    
    // Check if already blocked
    if (isIPBlocked($ip)) {
        error_log('blockIP: IP already blocked: ' . $ip);
        return true; // Not an error, just already done
    }
    
    $htaccessPath = getHtaccessPath();
    
    // Check if file is writable
    if (!is_writable($htaccessPath)) {
        error_log('blockIP: .htaccess is not writable: ' . $htaccessPath);
        return false;
    }
    
    // Read current content
    $content = file_get_contents($htaccessPath);
    
    // Find the blocked IPs section or create it
    $blockMarkerStart = '# Blocked IPs - Managed by Admin Panel';
    $blockMarkerEnd = '# End Blocked IPs';
    
    if (strpos($content, $blockMarkerStart) === false) {
        // Create new blocked IPs section at the top of the file
        $blockSection = "\n" . $blockMarkerStart . "\n";
        $blockSection .= "<RequireAll>\n";
        $blockSection .= "    Require all granted\n";
        $blockSection .= "    Require not ip " . $ip . "\n";
        $blockSection .= "</RequireAll>\n";
        $blockSection .= $blockMarkerEnd . "\n\n";
        
        $content = $blockSection . $content;
    } else {
        // Add to existing section (insert before </RequireAll>)
        $pattern = '/(' . preg_quote($blockMarkerStart, '/') . '.*?)([ \t]*<\/RequireAll>)/s';
        
        $replacement = function($matches) use ($ip) {
            return $matches[1] . "    Require not ip " . $ip . "\n" . $matches[2];
        };
        
        $content = preg_replace_callback($pattern, $replacement, $content);
    }
    
    // Create backup before modifying
    $backupPath = $htaccessPath . '.backup.' . date('Y-m-d_H-i-s');
    copy($htaccessPath, $backupPath);
    
    // Write updated content
    $result = file_put_contents($htaccessPath, $content);
    
    if ($result === false) {
        error_log('blockIP: Failed to write .htaccess');
        // Restore backup
        copy($backupPath, $htaccessPath);
        return false;
    }
    
    // Clean up old backups (keep last 5)
    cleanupBackups();
    
    return true;
}

/**
 * Unblock an IP address by removing it from .htaccess
 * Returns true on success, false on failure
 */
function unblockIP($ip) {
    // Validate IP format
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        error_log('unblockIP: Invalid IP format: ' . $ip);
        return false;
    }
    
    $htaccessPath = getHtaccessPath();
    
    if (!is_writable($htaccessPath)) {
        error_log('unblockIP: .htaccess is not writable: ' . $htaccessPath);
        return false;
    }
    
    // Read current content
    $content = file_get_contents($htaccessPath);
    
    // Create backup
    $backupPath = $htaccessPath . '.backup.' . date('Y-m-d_H-i-s');
    copy($htaccessPath, $backupPath);
    
    // Remove the IP line(s)
    $patterns = [
        '/Require not ip\s+' . preg_quote($ip, '/') . '\s*\n/i',
        '/Deny from\s+' . preg_quote($ip, '/') . '\s*\n/i'
    ];
    
    foreach ($patterns as $pattern) {
        $content = preg_replace($pattern, '', $content);
    }
    
    // Write updated content
    $result = file_put_contents($htaccessPath, $content);
    
    if ($result === false) {
        error_log('unblockIP: Failed to write .htaccess');
        copy($backupPath, $htaccessPath);
        return false;
    }
    
    cleanupBackups();
    
    return true;
}

/**
 * Clean up old .htaccess backups (keep last 5)
 */
function cleanupBackups() {
    $htaccessPath = getHtaccessPath();
    $dir = dirname($htaccessPath);
    $backups = glob($dir . '/.htaccess.backup.*');
    
    if (count($backups) > 5) {
        // Sort by modification time
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Delete oldest backups
        $toDelete = array_slice($backups, 0, count($backups) - 5);
        foreach ($toDelete as $backup) {
            unlink($backup);
        }
    }
}
