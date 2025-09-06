-- Add profile photo column to users table
ALTER TABLE users 
ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL AFTER phone;

-- Add profile photo column to providers table (if not exists)
ALTER TABLE providers 
ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL AFTER description;
