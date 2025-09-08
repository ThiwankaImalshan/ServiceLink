<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/FavoritesManager.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to add favorites']);
    exit;
}

$currentUser = $auth->getCurrentUser();

// Only customers can have favorites
if ($currentUser['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only customers can add favorites']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$action = $input['action'] ?? '';
$providerId = intval($input['provider_id'] ?? 0);

if (!in_array($action, ['add', 'remove']) || !$providerId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $favoritesManager = new FavoritesManager();
    
    if ($action === 'add') {
        $result = $favoritesManager->addToFavorites($currentUser['id'], $providerId);
        $message = $result['success'] ? 'Provider added to favorites' : $result['message'];
    } else {
        $result = $favoritesManager->removeFromFavorites($currentUser['id'], $providerId);
        $message = $result['success'] ? 'Provider removed from favorites' : $result['message'];
    }
    
    echo json_encode([
        'success' => $result['success'],
        'message' => $message
    ]);
    
} catch (Exception $e) {
    error_log("Favorites API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating favorites'
    ]);
}
?>
