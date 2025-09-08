<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Get current user
$currentUser = $auth->getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['providerId']) || !isset($data['rating'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$db = getDB();
$providerId = (int)$data['providerId'];
$userId = (int)$currentUser['id'];
$rating = (int)$data['rating'];
$review = isset($data['review']) ? trim($data['review']) : '';

// Validate rating
if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
    exit;
}

try {
    $db->beginTransaction();

    // Check if user has already reviewed this provider
    $stmt = $db->prepare("SELECT id FROM reviews WHERE provider_id = ? AND user_id = ?");
    $stmt->execute([$providerId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing review
        $stmt = $db->prepare("UPDATE reviews SET rating = ?, comment = ?, service_date = CURRENT_DATE WHERE provider_id = ? AND user_id = ?");
        $stmt->execute([$rating, $review, $providerId, $userId]);
    } else {
        // Insert new review
        $stmt = $db->prepare("INSERT INTO reviews (provider_id, user_id, rating, comment, service_date) VALUES (?, ?, ?, ?, CURRENT_DATE)");
        $stmt->execute([$providerId, $userId, $rating, $review]);
    }

    // Update provider's average rating
    $stmt = $db->prepare("
        UPDATE providers p 
        SET rating = (
            SELECT AVG(rating) 
            FROM reviews 
            WHERE provider_id = ?
        ),
        review_count = (
            SELECT COUNT(*) 
            FROM reviews 
            WHERE provider_id = ?
        )
        WHERE id = ?
    ");
    $stmt->execute([$providerId, $providerId, $providerId]);

    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully'
    ]);

} catch (PDOException $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
