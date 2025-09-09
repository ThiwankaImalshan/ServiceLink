<?php
// FavoritesManagerProviderProfile.php
// Handles favorites logic for provider-profile.php only

require_once __DIR__ . '/../config/database.php';

class FavoritesManagerProviderProfile {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    // Check if provider is in user's favorites
    public function isFavorite($userId, $providerId) {
        $stmt = $this->db->prepare("SELECT id FROM favorite_providers WHERE customer_id = ? AND provider_id = ? LIMIT 1");
        $stmt->execute([$userId, $providerId]);
        return (bool)$stmt->fetch();
    }

    // Add provider to favorites
    public function addToFavorites($userId, $providerId) {
        // Validate provider exists by provider id
        $stmt = $this->db->prepare("SELECT id FROM providers WHERE id = ? LIMIT 1");
        $stmt->execute([$providerId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'message' => 'Provider not found'];
        }
        // Check if already in favorites
        $stmt = $this->db->prepare("SELECT id FROM favorite_providers WHERE customer_id = ? AND provider_id = ? LIMIT 1");
        $stmt->execute([$userId, $providerId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Provider already in favorites'];
        }
        // Add to favorites
        $stmt = $this->db->prepare("INSERT INTO favorite_providers (customer_id, provider_id) VALUES (?, ?)");
        $stmt->execute([$userId, $providerId]);
        return ['success' => true, 'message' => 'Provider added to favorites'];
    }

    // Remove provider from favorites
    public function removeFromFavorites($userId, $providerId) {
        $stmt = $this->db->prepare("DELETE FROM favorite_providers WHERE customer_id = ? AND provider_id = ?");
        $stmt->execute([$userId, $providerId]);
        return ['success' => true, 'message' => 'Provider removed from favorites'];
    }
}
