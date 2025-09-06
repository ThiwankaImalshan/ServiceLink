-- Add password reset functionality to users table
-- Run this migration to add password reset token columns

USE skillconnect;

-- Add password reset columns to users table
ALTER TABLE users 
ADD COLUMN password_reset_token VARCHAR(255) NULL DEFAULT NULL,
ADD COLUMN password_reset_expires TIMESTAMP NULL DEFAULT NULL;

-- Add index for faster token lookups
CREATE INDEX idx_password_reset_token ON users(password_reset_token);
CREATE INDEX idx_password_reset_expires ON users(password_reset_expires);

-- Verify the changes
DESCRIBE users;
