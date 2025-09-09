<?php
/**
 * Favorites Manager
 * Handles customer favorite providers functionality
 */

class FavoritesManager {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Add provider to favorites
     */
    public function addToFavorites($customerId, $providerId) {
        try {
            // Validate customer exists and is a user
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ? AND role = 'user'");
            $stmt->execute([$customerId]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Invalid customer'];
            }
            
            // Validate provider exists and is a provider
            $stmt = $this->db->prepare("SELECT id FROM providers WHERE id = ?");
            $stmt->execute([$providerId]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Provider not found'];
            }
            
            // Check if already in favorites
            $stmt = $this->db->prepare("SELECT id FROM favorite_providers WHERE customer_id = ? AND provider_id = ?");
            $stmt->execute([$customerId, $providerId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Provider already in favorites'];
            }
            
            // Add to favorites
            $stmt = $this->db->prepare("INSERT INTO favorite_providers (customer_id, provider_id) VALUES (?, ?)");
            $stmt->execute([$customerId, $providerId]);
            
            return ['success' => true, 'message' => 'Provider added to favorites'];
            
        } catch (Exception $e) {
            error_log("Add to favorites error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to add to favorites'];
        }
    }
    
    /**
     * Remove provider from favorites
     */
    public function removeFromFavorites($customerId, $providerId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM favorite_providers WHERE customer_id = ? AND provider_id = ?");
            $stmt->execute([$customerId, $providerId]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Provider removed from favorites'];
            } else {
                return ['success' => false, 'message' => 'Provider not found in favorites'];
            }
            
        } catch (Exception $e) {
            error_log("Remove from favorites error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to remove from favorites'];
        }
    }
    
    /**
     * Get customer's favorite providers
     */
    /**
     * Check if a provider is favorited by a customer
     */
    public function isProviderFavorited($customerId, $providerId) {
        try {
            $stmt = $this->db->prepare("SELECT 1 FROM favorite_providers WHERE customer_id = ? AND provider_id = ? LIMIT 1");
            $stmt->execute([$customerId, $providerId]);
            return $stmt->fetchColumn() !== false;
        } catch (Exception $e) {
            error_log("Check favorite status error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get list of provider IDs favorited by a customer
     */
    public function getFavoritedProviderIds($customerId) {
        try {
            $stmt = $this->db->prepare("SELECT provider_id FROM favorite_providers WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Get favorited provider IDs error: " . $e->getMessage());
            return [];
        }
    }

    public function getFavoriteProviders($customerId, $limit = null, $offset = 0) {
        try {
            $sql = "
                SELECT p.*, u.first_name, u.last_name, u.profile_photo, u.email, u.phone,
                       c.name as category_name, c.icon as category_icon, c.slug as category_slug,
                       fp.created_at as favorited_at
                FROM favorite_providers fp
                JOIN providers p ON fp.provider_id = p.user_id
                JOIN users u ON p.user_id = u.id
                JOIN categories c ON p.category_id = c.id
                WHERE fp.customer_id = ? AND p.is_active = 1
                ORDER BY fp.created_at DESC
            ";
            
            $params = [$customerId];
            
            if ($limit) {
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get favorite providers error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if provider is in customer's favorites
     */
    public function isFavorite($customerId, $providerId) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM favorite_providers WHERE customer_id = ? AND provider_id = ?");
            $stmt->execute([$customerId, $providerId]);
            return $stmt->fetch() ? true : false;
            
        } catch (Exception $e) {
            error_log("Check favorite error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get favorite count for customer
     */
    public function getFavoriteCount($customerId) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM favorite_providers WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            return $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Get favorite count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get customers who favorited a provider
     */
    public function getProviderFavorites($providerId) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.id, u.first_name, u.last_name, u.email, fp.created_at as favorited_at
                FROM favorite_providers fp
                JOIN users u ON fp.customer_id = u.id
                WHERE fp.provider_id = ?
                ORDER BY fp.created_at DESC
            ");
            $stmt->execute([$providerId]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get provider favorites error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get favorite statistics
     */
    public function getFavoriteStats($customerId = null, $providerId = null) {
        try {
            $stats = [];
            
            if ($customerId) {
                // Customer's favorite stats
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as total_favorites,
                           COUNT(CASE WHEN p.is_verified = 1 THEN 1 END) as verified_favorites,
                           AVG(p.rating) as avg_rating
                    FROM favorite_providers fp
                    JOIN providers p ON fp.provider_id = p.user_id
                    WHERE fp.customer_id = ?
                ");
                $stmt->execute([$customerId]);
                $stats['customer'] = $stmt->fetch();
            }
            
            if ($providerId) {
                // Provider's favorite stats
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as total_customers
                    FROM favorite_providers
                    WHERE provider_id = ?
                ");
                $stmt->execute([$providerId]);
                $stats['provider'] = $stmt->fetch();
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Get favorite stats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get trending favorite providers
     */
    public function getTrendingFavorites($limit = 10, $days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, u.first_name, u.last_name, u.profile_photo,
                       c.name as category_name, c.icon as category_icon,
                       COUNT(fp.id) as favorite_count
                FROM providers p
                JOIN users u ON p.user_id = u.id
                JOIN categories c ON p.category_id = c.id
                LEFT JOIN favorite_providers fp ON p.user_id = fp.provider_id
                    AND fp.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                WHERE p.is_active = 1 AND p.is_verified = 1
                GROUP BY p.id
                ORDER BY favorite_count DESC, p.rating DESC
                LIMIT ?
            ");
            $stmt->execute([$days, $limit]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get trending favorites error: " . $e->getMessage());
            return [];
        }
    }
}
?>
