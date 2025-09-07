<?php
// serve-upload.php - safely stream uploaded images without relying on .htaccess

declare(strict_types=1);

// Base paths
$baseDir     = __DIR__;
$uploadsRoot = realpath($baseDir . '/uploads');
if ($uploadsRoot === false) {
    http_response_code(404);
    exit;
}

// Read and sanitize requested relative path
$rel = isset($_GET['p']) ? $_GET['p'] : '';
$rel = rawurldecode($rel);
$rel = str_replace('\\', '/', $rel);
$rel = ltrim($rel, '/');

// Only allow files under /uploads and only image extensions
$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
if ($rel === '' || !in_array($ext, $allowedExt, true)) {
    http_response_code(403);
    exit;
}

$abs = realpath($baseDir . '/' . $rel);
if ($abs === false || strpos($abs, $uploadsRoot) !== 0 || !is_file($abs)) {
    http_response_code(404);
    exit;
}

// Detect MIME type
$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $f = finfo_open(FILEINFO_MIME_TYPE);
    if ($f) {
        $detected = finfo_file($f, $abs);
        if ($detected) $mime = $detected;
        finfo_close($f);
    }
} else {
    // Fallback by extension
    $map = [
        'jpg' => 'image/jpeg','jpeg' => 'image/jpeg',
        'png' => 'image/png','gif' => 'image/gif',
        'webp'=> 'image/webp'
    ];
    if (isset($map[$ext])) $mime = $map[$ext];
}

// Caching headers
$mtime = @filemtime($abs) ?: time();
$etag  = '"' . md5($abs . '|' . $mtime . '|' . (@filesize($abs) ?: 0)) . '"';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

readfile($abs);