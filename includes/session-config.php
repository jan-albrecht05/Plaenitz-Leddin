<?php
/**
 * Zentrale Session-Konfiguration
 * 
 * Diese Datei sollte vor session_start() in jeder Datei eingebunden werden,
 * die Sessions verwendet.
 */

// Session-Lebensdauer: 30 Minuten (1800 Sekunden)
$sessionLifetime = 1800;

// Session-Cookie-Parameter setzen
ini_set('session.gc_maxlifetime', $sessionLifetime);
ini_set('session.cookie_lifetime', $sessionLifetime);

// Session-Cookie-Parameter für mehr Sicherheit
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'domain' => '', // Leer = aktuelle Domain
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Nur HTTPS wenn verfügbar
    'httponly' => true, // Schutz vor XSS
    'samesite' => 'Lax' // CSRF-Schutz
]);

// Garbage Collection Wahrscheinlichkeit erhöhen für zuverlässigeres Aufräumen
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100); // 1% Chance bei jedem Request

/**
 * Session starten mit automatischer Timeout-Prüfung
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Prüfen, ob Session abgelaufen ist
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        $inactiveTime = time() - $_SESSION['LAST_ACTIVITY'];
        
        if ($inactiveTime > $GLOBALS['sessionLifetime'] ?? 1800) {
            // Session ist abgelaufen - aufräumen und neu starten
            session_unset();
            session_destroy();
            session_start();
            return false; // Signalisiert, dass Session abgelaufen war
        }
    }
    
    // Aktivitäts-Timestamp aktualisieren
    $_SESSION['LAST_ACTIVITY'] = time();
    
    // Session-ID alle 10 Minuten regenerieren (Sicherheit)
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } else if (time() - $_SESSION['CREATED'] > 600) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
    
    return true; // Session ist gültig
}
