<?php
/**
 * Image Upload Utility Class
 * Handles secure image uploads with validation and resizing
 */

class ImageUploader {
    private $uploadDir;
    private $allowedTypes;
    private $maxFileSize;
    private $maxWidth;
    private $maxHeight;
    
    public function __construct($uploadDir = 'uploads/profiles/') {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $this->maxFileSize = 5 * 1024 * 1024; // 5MB
        $this->maxWidth = 1000;
        $this->maxHeight = 1000;
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Upload and process an image file
     */
    public function uploadImage($file, $prefix = '') {
        try {
            // Validate file upload
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Generate unique filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = $prefix . uniqid() . '_' . time() . '.' . $extension;
            $filepath = $this->uploadDir . $filename;
            
            // Create image resource based on type
            $imageInfo = getimagesize($file['tmp_name']);
            $mimeType = $imageInfo['mime'];
            
            switch ($mimeType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($file['tmp_name']);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($file['tmp_name']);
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp($file['tmp_name']);
                    break;
                default:
                    return ['success' => false, 'message' => 'Unsupported image type'];
            }
            
            if (!$image) {
                return ['success' => false, 'message' => 'Failed to create image resource'];
            }
            
            // Get original dimensions
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);
            
            // Calculate new dimensions maintaining aspect ratio
            $newDimensions = $this->calculateDimensions($originalWidth, $originalHeight);
            
            // Create new image with calculated dimensions
            $resizedImage = imagecreatetruecolor($newDimensions['width'], $newDimensions['height']);
            
            // Preserve transparency for PNG and GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
            }
            
            // Resize image
            imagecopyresampled(
                $resizedImage, $image,
                0, 0, 0, 0,
                $newDimensions['width'], $newDimensions['height'],
                $originalWidth, $originalHeight
            );
            
            // Save resized image
            $saved = false;
            switch ($mimeType) {
                case 'image/jpeg':
                    $saved = imagejpeg($resizedImage, $filepath, 90);
                    break;
                case 'image/png':
                    $saved = imagepng($resizedImage, $filepath);
                    break;
                case 'image/gif':
                    $saved = imagegif($resizedImage, $filepath);
                    break;
                case 'image/webp':
                    $saved = imagewebp($resizedImage, $filepath, 90);
                    break;
            }
            
            // Clean up memory
            imagedestroy($image);
            imagedestroy($resizedImage);
            
            if ($saved) {
                // Return relative path for database storage
                return [
                    'success' => true,
                    'filename' => $filename,
                    'path' => $this->uploadDir . $filename,
                    'url' => BASE_URL . '/' . $this->uploadDir . $filename
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to save image'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Upload error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'message' => 'Invalid file upload'];
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'message' => 'No file was uploaded'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'message' => 'File too large'];
            default:
                return ['success' => false, 'message' => 'Unknown upload error'];
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'message' => 'File size exceeds limit (5MB max)'];
        }
        
        // Check file type
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $file['tmp_name']);
        finfo_close($fileInfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed'];
        }
        
        // Additional security check for image files
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['success' => false, 'message' => 'File is not a valid image'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Calculate new dimensions maintaining aspect ratio
     */
    private function calculateDimensions($originalWidth, $originalHeight) {
        $ratio = min($this->maxWidth / $originalWidth, $this->maxHeight / $originalHeight);
        
        // If image is already smaller than max dimensions, don't upscale
        if ($ratio > 1) {
            $ratio = 1;
        }
        
        return [
            'width' => round($originalWidth * $ratio),
            'height' => round($originalHeight * $ratio)
        ];
    }
    
    /**
     * Delete an uploaded image
     */
    public function deleteImage($filename) {
        if (!$filename) return true;
        
        $filepath = $this->uploadDir . basename($filename);
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return true;
    }
    
    /**
     * Get default profile image URL
     */
    public static function getDefaultProfileImage() {
        return BASE_URL . '/assets/img/default-avatar.svg';
    }
    
    /**
     * Get profile image URL with fallback
     */
    public static function getProfileImageUrl($imagePath) {
        // Handle NULL, empty string, or false values
        if (empty($imagePath) || is_null($imagePath)) {
            return self::getDefaultProfileImage();
        }
        
        // Ensure the path doesn't start with a slash (relative path)
        $imagePath = ltrim($imagePath, '/');
        
        // Check if file exists
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/Service_Delivery_Web/' . $imagePath;
        if (!file_exists($fullPath)) {
            return self::getDefaultProfileImage();
        }
        
        return BASE_URL . '/' . $imagePath;
    }
}
