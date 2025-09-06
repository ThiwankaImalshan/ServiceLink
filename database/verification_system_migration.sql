-- Enhanced verification and profile features database schema
-- Run this migration to add verification and profile enhancement features

USE servicelink;

-- 1. Add gender field to users table
ALTER TABLE users 
ADD COLUMN gender ENUM('male', 'female', 'other', 'prefer_not_to_say') DEFAULT NULL;

-- 2. Add verification fields to users table
ALTER TABLE users 
ADD COLUMN id_verification_status ENUM('pending', 'approved', 'rejected', 'not_submitted') DEFAULT 'not_submitted',
ADD COLUMN id_document_front VARCHAR(255) DEFAULT NULL,
ADD COLUMN id_document_back VARCHAR(255) DEFAULT NULL,
ADD COLUMN id_verification_notes TEXT DEFAULT NULL,
ADD COLUMN id_verified_at TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN id_verified_by INT DEFAULT NULL,
ADD COLUMN linkedin_profile VARCHAR(255) DEFAULT NULL,
ADD COLUMN linkedin_verification_status ENUM('pending', 'verified', 'rejected', 'not_submitted') DEFAULT 'not_submitted',
ADD COLUMN linkedin_verification_token VARCHAR(255) DEFAULT NULL,
ADD COLUMN linkedin_verified_at TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN linkedin_verified_by INT DEFAULT NULL;

-- 3. Create verification_requests table for tracking verification history
CREATE TABLE verification_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    verification_type ENUM('id_card', 'linkedin') NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    submitted_data JSON NOT NULL,
    admin_notes TEXT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_verification_user (user_id),
    KEY idx_verification_type (verification_type),
    KEY idx_verification_status (status)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 4. Create favorite_providers table for customer favorites
CREATE TABLE favorite_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    provider_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favorite (customer_id, provider_id),
    KEY idx_customer_favorites (customer_id),
    KEY idx_provider_favorites (provider_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 5. Create admin_notifications table for verification alerts
CREATE TABLE admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('id_verification', 'linkedin_verification', 'new_provider', 'provider_update') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    user_id INT DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_admin_notifications_type (type),
    KEY idx_admin_notifications_read (is_read),
    KEY idx_admin_notifications_date (created_at)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 6. Add indexes for performance
ALTER TABLE users 
ADD INDEX idx_id_verification_status (id_verification_status),
ADD INDEX idx_linkedin_verification_status (linkedin_verification_status),
ADD INDEX idx_gender (gender);

-- 7. Insert sample admin notification for testing
INSERT INTO admin_notifications (type, title, message, is_read) VALUES 
('id_verification', 'Verification System Active', 'ID and LinkedIn verification system has been successfully installed and is ready for use.', FALSE);

-- Show updated schema
DESCRIBE users;
SHOW TABLES LIKE '%verification%';
SHOW TABLES LIKE '%favorite%';
SHOW TABLES LIKE '%admin%';
