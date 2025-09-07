<?php
/**
 * Verification Manager
 * Handles ID card and LinkedIn verification processes
 */

class VerificationManager {
    private $db;
    private $uploadDir;
    
    public function __construct() {
        $this->db = getDB();
        $this->uploadDir = dirname(__DIR__) . '/uploads/verifications/';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Submit ID card verification
     */
    public function submitIdVerification($userId, $frontImage, $backImage, $notes = '') {
        try {
            // Validate user exists and is a provider
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user || $user['role'] !== 'provider') {
                return ['success' => false, 'message' => 'Only providers can submit ID verification'];
            }
            
            // Check if already verified or pending
            $stmt = $this->db->prepare("SELECT id_verification_status FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $status = $stmt->fetchColumn();
            
            if ($status === 'approved') {
                return ['success' => false, 'message' => 'ID already verified'];
            }
            
            if ($status === 'pending') {
                return ['success' => false, 'message' => 'ID verification already pending review'];
            }
            
            // Handle file uploads
            $frontPath = $this->uploadIdDocument($userId, $frontImage, 'front');
            $backPath = $this->uploadIdDocument($userId, $backImage, 'back');
            
            if (!$frontPath || !$backPath) {
                return ['success' => false, 'message' => 'Failed to upload ID documents'];
            }
            
            // Update user record
            $stmt = $this->db->prepare("
                UPDATE users 
                SET id_verification_status = 'pending',
                    id_document_front = ?,
                    id_document_back = ?,
                    id_verification_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$frontPath, $backPath, $notes, $userId]);
            
            // Create verification request record
            $submittedData = json_encode([
                'front_image' => $frontPath,
                'back_image' => $backPath,
                'notes' => $notes,
                'submitted_at' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $this->db->prepare("
                INSERT INTO verification_requests (user_id, verification_type, submitted_data) 
                VALUES (?, 'id_card', ?)
            ");
            $stmt->execute([$userId, $submittedData]);
            
            // Create admin notification
            $this->createAdminNotification(
                'id_verification',
                'New ID Verification Request',
                "Provider ID $userId has submitted ID documents for verification",
                $userId
            );
            
            return ['success' => true, 'message' => 'ID verification submitted successfully'];
            
        } catch (Exception $e) {
            error_log("ID verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to submit ID verification'];
        }
    }
    
    /**
     * Submit LinkedIn verification
     */
    public function submitLinkedInVerification($userId, $linkedinUrl) {
        try {
            // Validate LinkedIn URL
            if (!$this->isValidLinkedInUrl($linkedinUrl)) {
                return ['success' => false, 'message' => 'Invalid LinkedIn profile URL'];
            }
            
            // Extract username from LinkedIn URL
            $username = $this->extractLinkedInUsername($linkedinUrl);
            if (!$username) {
                return ['success' => false, 'message' => 'Could not extract username from LinkedIn URL'];
            }
            
            // Check if already verified or pending
            $stmt = $this->db->prepare("SELECT linkedin_verification_status FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $status = $stmt->fetchColumn();
            
            if ($status === 'verified') {
                return ['success' => false, 'message' => 'LinkedIn already verified'];
            }
            
            if ($status === 'pending') {
                return ['success' => false, 'message' => 'LinkedIn verification already pending'];
            }
            
            // Generate verification token
            $token = bin2hex(random_bytes(32));
            
            // Update user record
            $stmt = $this->db->prepare("
                UPDATE users 
                SET linkedin_profile = ?,
                    linkedin_verification_status = 'pending',
                    linkedin_verification_token = ?
                WHERE id = ?
            ");
            $stmt->execute([$linkedinUrl, $token, $userId]);
            
            // Create verification request
            $submittedData = json_encode([
                'linkedin_url' => $linkedinUrl,
                'username' => $username,
                'verification_token' => $token,
                'submitted_at' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $this->db->prepare("
                INSERT INTO verification_requests (user_id, verification_type, submitted_data) 
                VALUES (?, 'linkedin', ?)
            ");
            $stmt->execute([$userId, $submittedData]);
            
            // Create admin notification
            $this->createAdminNotification(
                'linkedin_verification',
                'New LinkedIn Verification Request',
                "User ID $userId has submitted LinkedIn profile for verification: $linkedinUrl",
                $userId
            );
            
            return [
                'success' => true, 
                'message' => 'LinkedIn verification submitted successfully',
                'verification_url' => $this->getLinkedInVerificationUrl($userId, $token)
            ];
            
        } catch (Exception $e) {
            error_log("LinkedIn verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to submit LinkedIn verification'];
        }
    }
    
    /**
     * Upload ID document
     */
    private function uploadIdDocument($userId, $file, $side) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($file['type'], $allowedTypes)) {
            return false;
        }
        
        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return false;
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = "id_{$userId}_{$side}_" . time() . "." . $extension;
        $filePath = $this->uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return 'uploads/verifications/' . $filename;
        }
        
        return false;
    }
    
    /**
     * Validate LinkedIn URL
     */
    private function isValidLinkedInUrl($url) {
        return preg_match('/^https?:\/\/(www\.)?linkedin\.com\/in\/[a-zA-Z0-9\-]+\/?$/', $url);
    }
    
    /**
     * Extract username from LinkedIn URL
     */
    private function extractLinkedInUsername($url) {
        if (preg_match('/linkedin\.com\/in\/([a-zA-Z0-9\-]+)/', $url, $matches)) {
            return $matches[1];
        }
        return false;
    }
    
    /**
     * Get LinkedIn verification URL
     */
    private function getLinkedInVerificationUrl($userId, $token) {
        return BASE_URL . "/verify-linkedin.php?user_id=$userId&token=$token";
    }
    
    /**
     * Create admin notification
     */
    private function createAdminNotification($type, $title, $message, $userId = null) {
        $stmt = $this->db->prepare("
            INSERT INTO admin_notifications (type, title, message, user_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$type, $title, $message, $userId]);
    }
    
    /**
     * Get verification status for user
     */
    public function getVerificationStatus($userId) {
        $stmt = $this->db->prepare("
            SELECT id_verification_status, linkedin_verification_status, 
                   id_document_front, id_document_back, linkedin_profile
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Get pending verifications for admin
     */
    public function getPendingVerifications($type = null) {
        $sql = "
            SELECT vr.*, u.username, u.first_name, u.last_name, u.email,
                   u.linkedin_profile, u.linkedin_verification_status,
                   u.id_document_front, u.id_document_back, u.id_verification_status,
                   u.id_verification_notes
            FROM verification_requests vr
            JOIN users u ON vr.user_id = u.id
            WHERE vr.status = 'pending'
        ";
        
        $params = [];
        if ($type) {
            $sql .= " AND vr.verification_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY vr.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Approve verification
     */
    public function approveVerification($requestId, $adminId, $notes = '') {
        try {
            // Get verification request
            $stmt = $this->db->prepare("SELECT * FROM verification_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            
            if (!$request) {
                return ['success' => false, 'message' => 'Verification request not found'];
            }
            
            // Update verification request
            $stmt = $this->db->prepare("
                UPDATE verification_requests 
                SET status = 'approved', admin_notes = ?, reviewed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$notes, $adminId, $requestId]);
            
            // Update user record based on verification type
            if ($request['verification_type'] === 'id_card') {
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET id_verification_status = 'approved',
                        id_verified_at = NOW(),
                        id_verified_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$adminId, $request['user_id']]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET linkedin_verification_status = 'verified',
                        linkedin_verified_at = NOW(),
                        linkedin_verified_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$adminId, $request['user_id']]);
            }
            
            return ['success' => true, 'message' => 'Verification approved successfully'];
            
        } catch (Exception $e) {
            error_log("Verification approval error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to approve verification'];
        }
    }
    
    /**
     * Reject verification
     */
    public function rejectVerification($requestId, $adminId, $reason) {
        try {
            // Get verification request
            $stmt = $this->db->prepare("SELECT * FROM verification_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            
            if (!$request) {
                return ['success' => false, 'message' => 'Verification request not found'];
            }
            
            // Update verification request
            $stmt = $this->db->prepare("
                UPDATE verification_requests 
                SET status = 'rejected', admin_notes = ?, reviewed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$reason, $adminId, $requestId]);
            
            // Update user record
            if ($request['verification_type'] === 'id_card') {
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET id_verification_status = 'rejected'
                    WHERE id = ?
                ");
                $stmt->execute([$request['user_id']]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET linkedin_verification_status = 'rejected'
                    WHERE id = ?
                ");
                $stmt->execute([$request['user_id']]);
            }
            
            return ['success' => true, 'message' => 'Verification rejected'];
            
        } catch (Exception $e) {
            error_log("Verification rejection error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to reject verification'];
        }
    }
    
    /**
     * Get verification statistics (counts for each status)
     */
    public function getVerificationStats() {
        $stats = [
            'id' => ['pending' => 0, 'approved' => 0, 'rejected' => 0],
            'linkedin' => ['pending' => 0, 'approved' => 0, 'rejected' => 0]
        ];
        // ID Verification stats
        $stmt = $this->db->query("SELECT id_verification_status, COUNT(*) as count FROM users GROUP BY id_verification_status");
        while ($row = $stmt->fetch()) {
            $status = strtolower($row['id_verification_status']);
            if (isset($stats['id'][$status])) {
                $stats['id'][$status] = (int)$row['count'];
            }
        }
        // LinkedIn Verification stats
        $stmt = $this->db->query("SELECT linkedin_verification_status, COUNT(*) as count FROM users GROUP BY linkedin_verification_status");
        while ($row = $stmt->fetch()) {
            $status = strtolower($row['linkedin_verification_status']);
            if (isset($stats['linkedin'][$status])) {
                $stats['linkedin'][$status] = (int)$row['count'];
            }
        }
        return $stats;
    }
}
?>
