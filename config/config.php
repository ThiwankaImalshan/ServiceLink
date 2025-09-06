<?php
/**
 * Database Configuration
 * Update these settings according to your MySQL setup
 */

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'servicelink');
define('DB_USER', 'root');
define('DB_PASS', ''); // Update with your MySQL root password

// Application settings
define('BASE_URL', 'http://localhost/ServiceLink');
define('SITE_NAME', 'ServiceLink');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Session settings
define('SESSION_LIFETIME', 3600); // 1 hour

// Timezone
date_default_timezone_set('America/Los_Angeles');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
