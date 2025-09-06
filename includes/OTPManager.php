<?php
/**
 * OTP (One-Time Password) Management System
 * Handles generation, storage, validation and cleanup of OTPs
 */

require_once __DIR__ . '/../config/database.php';

class OTPManager {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
        $this->createOTPTable();
    }
    
    /**
     * Create OTP table if it doesn't exist
     */
    private function createOTPTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `email_otps` (
            `id` int NOT NULL AUTO_INCREMENT,
            `email` varchar(255) NOT NULL,
            `otp_code` varchar(6) NOT NULL,
            `user_id` int DEFAULT NULL,
            `purpose` enum('registration','password_reset') NOT NULL DEFAULT 'registration',
            `is_used` tinyint(1) DEFAULT '0',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at` timestamp NOT NULL,
            PRIMARY KEY (`id`),
            KEY `email` (`email`),
            KEY `otp_code` (`otp_code`),
            KEY `expires_at` (`expires_at`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
        ";
        
        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create OTP table: " . $e->getMessage());
        }
    }
    
    /**
     * Generate a 6-digit OTP
     */
    private function generateOTP() {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Create and store new OTP for email
     */
    public function createOTP($email, $userId = null, $purpose = 'registration') {
        try {
            // Clean up any existing OTPs for this email
            $this->cleanupOTPs($email);
            
            $otp = $this->generateOTP();
            $expiresAt = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes from now
            
            $stmt = $this->db->prepare("
                INSERT INTO email_otps (email, otp_code, user_id, purpose, expires_at) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([$email, $otp, $userId, $purpose, $expiresAt]);
            
            if ($result) {
                return [
                    'success' => true,
                    'otp' => $otp,
                    'expires_at' => $expiresAt
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to create OTP'];
            }
            
        } catch (PDOException $e) {
            error_log("OTP creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Verify OTP for email
     */
    public function verifyOTP($email, $otp, $purpose = 'registration') {
        try {
            $stmt = $this->db->prepare("
                SELECT id, user_id, created_at, expires_at 
                FROM email_otps 
                WHERE email = ? AND otp_code = ? AND purpose = ? AND is_used = 0 AND expires_at > NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            
            $stmt->execute([$email, $otp, $purpose]);
            $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($otpRecord) {
                // Mark OTP as used
                $updateStmt = $this->db->prepare("UPDATE email_otps SET is_used = 1 WHERE id = ?");
                $updateStmt->execute([$otpRecord['id']]);
                
                return [
                    'success' => true,
                    'user_id' => $otpRecord['user_id'],
                    'message' => 'OTP verified successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ];
            }
            
        } catch (PDOException $e) {
            error_log("OTP verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Check if OTP exists and is valid for email
     */
    public function hasValidOTP($email, $purpose = 'registration') {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM email_otps 
                WHERE email = ? AND purpose = ? AND is_used = 0 AND expires_at > NOW()
            ");
            
            $stmt->execute([$email, $purpose]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
            
        } catch (PDOException $e) {
            error_log("OTP check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old/used OTPs for email
     */
    public function cleanupOTPs($email = null) {
        try {
            if ($email) {
                // Clean up for specific email
                $stmt = $this->db->prepare("
                    DELETE FROM email_otps 
                    WHERE email = ? AND (is_used = 1 OR expires_at < NOW())
                ");
                $stmt->execute([$email]);
            } else {
                // Clean up all expired/used OTPs
                $stmt = $this->db->prepare("
                    DELETE FROM email_otps 
                    WHERE is_used = 1 OR expires_at < NOW()
                ");
                $stmt->execute();
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("OTP cleanup error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get OTP attempts count for email (last 24 hours)
     */
    public function getAttemptCount($email, $purpose = 'registration') {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM email_otps 
                WHERE email = ? AND purpose = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            
            $stmt->execute([$email, $purpose]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int)$result['count'];
            
        } catch (PDOException $e) {
            error_log("OTP attempt count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if email has exceeded daily OTP limit
     */
    public function hasExceededLimit($email, $purpose = 'registration', $limit = 5) {
        return $this->getAttemptCount($email, $purpose) >= $limit;
    }
}

// Create global instance
$otpManager = new OTPManager();
