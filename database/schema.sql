-- SkillConnect Database Schema
-- Run this file to create the database structure

CREATE DATABASE IF NOT EXISTS skillconnect;
USE skillconnect;

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    role ENUM('user', 'provider', 'admin') DEFAULT 'user',
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) NOT NULL,
    background_image VARCHAR(255),
    description TEXT,
    active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Service providers table
CREATE TABLE providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    business_name VARCHAR(100),
    location VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    hourly_rate DECIMAL(8, 2) NOT NULL,
    experience_years INT NOT NULL,
    working_days JSON, -- Store as array: ["Mon", "Tue", "Wed"]
    working_hours_start TIME,
    working_hours_end TIME,
    best_call_time VARCHAR(50),
    description TEXT,
    profile_image VARCHAR(255),
    rating DECIMAL(3, 2) DEFAULT 0.00,
    review_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    is_skilled BOOLEAN DEFAULT FALSE,
    tags JSON, -- Store as array: ["repair", "custom", "furniture"]
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- Qualifications table
CREATE TABLE qualifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    institute VARCHAR(150) NOT NULL,
    year_obtained YEAR,
    certificate_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

-- Wanted ads table
CREATE TABLE wanted_ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(100) NOT NULL,
    budget_min DECIMAL(8, 2),
    budget_max DECIMAL(8, 2),
    urgency ENUM('low', 'medium', 'high') DEFAULT 'medium',
    contact_method ENUM('phone', 'email', 'both') DEFAULT 'both',
    status ENUM('active', 'closed', 'completed') DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- Reviews table
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    service_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (provider_id, user_id)
);

-- Messages/Inquiries table
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    provider_id INT, -- Reference to provider if this is about a service
    wanted_ad_id INT, -- Reference to wanted ad if this is a response
    subject VARCHAR(200),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL,
    FOREIGN KEY (wanted_ad_id) REFERENCES wanted_ads(id) ON DELETE SET NULL
);

-- Admin settings table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Indexes for better performance
CREATE INDEX idx_providers_category ON providers(category_id);
CREATE INDEX idx_providers_location ON providers(location);
CREATE INDEX idx_providers_rating ON providers(rating);
CREATE INDEX idx_providers_active ON providers(is_active);
CREATE INDEX idx_wanted_ads_category ON wanted_ads(category_id);
CREATE INDEX idx_wanted_ads_location ON wanted_ads(location);
CREATE INDEX idx_wanted_ads_status ON wanted_ads(status);
CREATE INDEX idx_reviews_provider ON reviews(provider_id);
CREATE INDEX idx_messages_recipient ON messages(recipient_id);
CREATE INDEX idx_messages_sender ON messages(sender_id);

-- Insert default categories
INSERT INTO categories (slug, name, icon, background_image, description) VALUES
('teacher', 'Teachers', 'fa-chalkboard-user', 'https://images.unsplash.com/photo-1523246191871-1c7a0cde85d0?q=80&w=1200', 'Find qualified teachers and tutors for all subjects'),
('carpenter', 'Carpenters', 'fa-hammer', 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?q=80&w=1200', 'Professional carpentry and woodworking services'),
('plumber', 'Plumbers', 'fa-faucet-drip', 'https://images.unsplash.com/photo-1581093458791-9d09b64c73f7?q=80&w=1200', 'Reliable plumbing repair and installation services'),
('electrician', 'Electricians', 'fa-bolt-lightning', 'https://images.unsplash.com/photo-1521207418485-99c705420785?q=80&w=1200', 'Licensed electrical repair and installation'),
('cleaner', 'Cleaners', 'fa-broom', 'https://images.unsplash.com/photo-1581578017427-9d1d3bdc9733?q=80&w=1200', 'Home and office cleaning services'),
('it', 'IT Support', 'fa-computer', 'https://images.unsplash.com/photo-1518770660439-4636190af475?q=80&w=1200', 'Computer repair and IT support services'),
('mechanic', 'Mechanics', 'fa-wrench', 'https://images.unsplash.com/photo-1516239322395-0b81d2e5d9fa?q=80&w=1200', 'Auto repair and maintenance services'),
('painter', 'Painters', 'fa-brush', 'https://images.unsplash.com/photo-1506629082955-511b1aa562c8?q=80&w=1200', 'Interior and exterior painting services'),
('babysitter', 'Babysitters', 'fa-baby', 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?q=80&w=1200', 'Childcare and babysitting services'),
('gardener', 'Gardeners', 'fa-leaf', 'https://images.unsplash.com/photo-1444631032564-43fba6f5d8e6?q=80&w=1200', 'Garden maintenance and landscaping services');

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, first_name, last_name, role, email_verified) VALUES
('admin', 'admin@skillconnect.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'admin', TRUE);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_name', 'SkillConnect', 'Website name'),
('site_description', 'Find trusted local service providers', 'Website description'),
('contact_email', 'hello@skillconnect.example', 'Contact email address'),
('max_file_size', '5242880', 'Maximum file upload size in bytes (5MB)'),
('default_currency', 'USD', 'Default currency symbol'),
('items_per_page', '12', 'Number of items to show per page');
