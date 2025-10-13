<?php
// Simple test script to debug geocoding
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Geocoding Test</h2>";

// Test coordinates (Berlin)
$testLat = 52.5200;
$testLon = 13.4050;

echo "<p>Testing coordinates: $testLat, $testLon</p>";

// Test 1: Check if cURL is available
echo "<h3>Test 1: cURL availability</h3>";
if (function_exists('curl_init')) {
    echo "✅ cURL is available<br>";
} else {
    echo "❌ cURL is NOT available<br>";
    exit;
}

// Test 2: Test basic cURL request
echo "<h3>Test 2: Basic Nominatim request</h3>";
$url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
    'format' => 'jsonv2',
    'lat' => $testLat,
    'lon' => $testLon,
    'accept-language' => 'de,en',
    'addressdetails' => 1
]);

echo "URL: $url<br>";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'Plaenitz-Leddin Website/1.0 Test',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode<br>";
if ($error) {
    echo "cURL Error: $error<br>";
} else {
    echo "✅ cURL request successful<br>";
}

if ($response) {
    echo "Response length: " . strlen($response) . " bytes<br>";
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON parsing successful<br>";
        echo "<h4>Address data:</h4>";
        if (isset($data['address'])) {
            echo "<pre>" . print_r($data['address'], true) . "</pre>";
        } else {
            echo "No address data found<br>";
        }
    } else {
        echo "❌ JSON parsing failed: " . json_last_error_msg() . "<br>";
        echo "Raw response (first 500 chars): " . htmlspecialchars(substr($response, 0, 500)) . "<br>";
    }
}

// Test 3: Test the actual API endpoint
echo "<h3>Test 3: API endpoint test</h3>";
$testData = json_encode(['lat' => $testLat, 'lon' => $testLon]);
echo "Test data: $testData<br>";

// Simulate POST request to our API
$apiUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/geocode.php';
echo "API URL: $apiUrl<br>";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $testData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 15
]);

$apiResponse = curl_exec($ch);
$apiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$apiError = curl_error($ch);
curl_close($ch);

echo "API HTTP Code: $apiHttpCode<br>";
if ($apiError) {
    echo "API cURL Error: $apiError<br>";
}

if ($apiResponse) {
    echo "API Response: <pre>" . htmlspecialchars($apiResponse) . "</pre>";
} else {
    echo "No API response received<br>";
}
?>