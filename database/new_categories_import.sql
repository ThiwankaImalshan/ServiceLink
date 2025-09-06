-- Import this SQL file into your servicelink database
-- This will update the categories table with the new structure

USE servicelink;

-- First backup existing data (optional)
-- CREATE TABLE categories_backup AS SELECT * FROM categories;

-- Clear existing categories
DELETE FROM categories;

-- Reset auto increment
ALTER TABLE categories AUTO_INCREMENT = 1;

-- Insert new categories structure
INSERT INTO categories (id, slug, name, icon, background_image, description, active, sort_order, created_at) VALUES
-- Home Services - Repairs & Maintenance
(1, 'plumbing', 'Plumbing', 'fa-solid fa-faucet-drip', 'https://images.unsplash.com/photo-1581093458791-9d09b64c73f7?q=80&w=1200', 'Professional plumbing repair and installation services', 1, 10, CURRENT_TIMESTAMP),
(2, 'electrical', 'Electrical', 'fa-solid fa-bolt-lightning', 'https://images.unsplash.com/photo-1521207418485-99c705420785?q=80&w=1200', 'Licensed electrical repair and installation services', 1, 20, CURRENT_TIMESTAMP),
(3, 'painting', 'Painting', 'fa-solid fa-paintbrush', 'https://images.unsplash.com/photo-1506629082955-511b1aa562c8?q=80&w=1200', 'Interior and exterior painting services', 1, 30, CURRENT_TIMESTAMP),

-- Home Services - Cleaning
(4, 'house-cleaning', 'House Cleaning', 'fa-solid fa-broom', 'https://images.unsplash.com/photo-1581578017427-9d1d3bdc9733?q=80&w=1200', 'Professional home and office cleaning services', 1, 40, CURRENT_TIMESTAMP),

-- Education & Training - Tutoring
(5, 'academic-subjects', 'Academic Subjects', 'fa-solid fa-graduation-cap', 'https://images.unsplash.com/photo-1523246191871-1c7a0cde85d0?q=80&w=1200', 'Math, Science, English and other academic tutoring', 1, 50, CURRENT_TIMESTAMP),
(6, 'languages', 'Languages', 'fa-solid fa-language', 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=1200', 'Foreign language learning and tutoring', 1, 60, CURRENT_TIMESTAMP),

-- Education & Training - Performing & Visual Arts
(7, 'music', 'Music', 'fa-solid fa-music', 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?q=80&w=1200', 'Music lessons for all instruments and vocal training', 1, 70, CURRENT_TIMESTAMP),
(8, 'art', 'Art', 'fa-solid fa-palette', 'https://images.unsplash.com/photo-1541961017774-22349e4a1262?q=80&w=1200', 'Drawing, painting, and visual arts instruction', 1, 80, CURRENT_TIMESTAMP),
(9, 'dance', 'Dance', 'fa-solid fa-user-group', 'https://images.unsplash.com/photo-1518611012118-696072aa579a?q=80&w=1200', 'Dance lessons in various styles and levels', 1, 90, CURRENT_TIMESTAMP),

-- Vehicle Services
(10, 'car-bike-repair', 'Car/Bike Repair', 'fa-solid fa-car-side', 'https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?q=80&w=1200', 'Automotive and motorcycle repair services', 1, 100, CURRENT_TIMESTAMP),

-- Tech & Digital Support - Device Help
(11, 'computer-laptop-repair', 'Computer/Laptop Repair', 'fa-solid fa-laptop', 'https://images.unsplash.com/photo-1518770660439-4636190af475?q=80&w=1200', 'Computer and laptop repair and maintenance', 1, 110, CURRENT_TIMESTAMP),

-- Tech & Digital Support - Digital Services
(12, 'graphic-design', 'Graphic Design', 'fa-solid fa-pen-nib', 'https://images.unsplash.com/photo-1541701494587-cb58502866ab?q=80&w=1200', 'Logo design, branding, and visual graphics', 1, 120, CURRENT_TIMESTAMP),
(13, 'video-editing', 'Video Editing', 'fa-solid fa-video', 'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?q=80&w=1200', 'Professional video editing and post-production', 1, 130, CURRENT_TIMESTAMP);

-- Show the updated categories
SELECT id, slug, name, icon, description, sort_order FROM categories ORDER BY sort_order;
