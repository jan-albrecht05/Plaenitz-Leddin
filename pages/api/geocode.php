<?php
/**
 * Reverse Geocoding Proxy for OpenStreetMap Nominatim API
 * Avoids CORS issues by proxying requests server-side
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get and validate input
    $rawInput = file_get_contents('php://input');
    error_log('Geocoding API: Raw input: ' . $rawInput);
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    $lat = floatval($input['lat'] ?? 0);
    $lon = floatval($input['lon'] ?? 0);
    
    error_log("Geocoding API: Received coordinates lat=$lat, lon=$lon");

    if (!$lat || !$lon) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid latitude or longitude']);
        exit;
    }

    // Validate coordinate ranges
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Coordinates out of valid range']);
        exit;
    }
} catch (Exception $e) {
    error_log('Geocoding API: Input validation error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Input processing failed: ' . $e->getMessage()]);
    exit;
}

try {
    // Build Nominatim API URL
    $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
        'format' => 'jsonv2',
        'lat' => $lat,
        'lon' => $lon,
        'accept-language' => 'de,en',
        'addressdetails' => 1
    ]);
    
    error_log('Geocoding API: Requesting URL: ' . $url);

    // Check if cURL is available
    if (!function_exists('curl_init')) {
        throw new Exception('cURL is not available on this server');
    }

    // Set up cURL with proper headers
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Plaenitz-Leddin Website/1.0 (contact@plaenitz-leddin.de)',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate',
        ],
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => false, // Temporarily disable for testing
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlInfo = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log('Geocoding API: HTTP Code: ' . $httpCode);
    error_log('Geocoding API: Response length: ' . strlen($response));
    if ($error) {
        error_log('Geocoding API: cURL error: ' . $error);
    }

    if ($error) {
        throw new Exception('cURL error: ' . $error);
    }

    if ($httpCode !== 200) {
        error_log('Geocoding API: Non-200 response: ' . substr($response, 0, 200));
        throw new Exception('HTTP error: ' . $httpCode . ' - ' . substr($response, 0, 100));
    }

    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Geocoding API: JSON decode error: ' . json_last_error_msg());
        error_log('Geocoding API: Raw response: ' . substr($response, 0, 500));
        throw new Exception('Invalid JSON response from Nominatim API: ' . json_last_error_msg());
    }
    
    error_log('Geocoding API: Successfully decoded response');

    // Extract and normalize address data
    $address = $data['address'] ?? [];
    $result = [
        'success' => true,
        'address' => [
            'road' => $address['road'] ?? '',
            'house_number' => $address['house_number'] ?? '',
            'postcode' => $address['postcode'] ?? '',
            'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? '',
            'country' => $address['country'] ?? '',
            'country_code' => $address['country_code'] ?? ''
        ],
        'display_name' => $data['display_name'] ?? '',
        'coordinates' => [
            'lat' => $lat,
            'lon' => $lon
        ]
    ];

    error_log('Geocoding API: Returning success response');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Geocoding API: Exception caught: ' . $e->getMessage());
    error_log('Geocoding API: Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Geocoding service error: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}