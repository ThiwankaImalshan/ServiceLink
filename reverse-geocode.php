<?php
// reverse-geocode.php

// Disable error display for production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Ensure required parameters are provided
if (!isset($_GET['lat']) || !isset($_GET['lng'])) {
    http_response_code(400);
    error_log('Missing latitude or longitude parameters.');
    echo json_encode(['error' => 'Missing latitude or longitude']);
    exit;
}

$lat = $_GET['lat'];
$lng = $_GET['lng'];

// Validate latitude and longitude
if (!is_numeric($lat) || !is_numeric($lng)) {
    http_response_code(400);
    error_log('Invalid latitude or longitude values.');
    echo json_encode(['error' => 'Invalid latitude or longitude']);
    exit;
}

// Make the reverse geocoding request to OpenStreetMap
$url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng";

$options = [
    'http' => [
        'header' => "User-Agent: ServiceLink/1.0\r\n"
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response === FALSE) {
    error_log('Failed to fetch location details from OpenStreetMap. URL: ' . $url);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch location details']);
    exit;
}

// Ensure the response is valid JSON
$jsonResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Invalid JSON response from OpenStreetMap: ' . json_last_error_msg());
    http_response_code(500);
    echo json_encode(['error' => 'Invalid JSON response from geocoding service']);
    exit;
}

header('Content-Type: application/json');
error_log('Reverse geocoding successful for lat: ' . $lat . ', lng: ' . $lng);
echo $response;
?>
