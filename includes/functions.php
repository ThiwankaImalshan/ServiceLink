<?php
/**
 * Common functions for the application
 */

/**
 * Escape HTML output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Get flash message
 */
function getFlashMessage($key) {
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    return null;
}

/**
 * Set flash message
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_messages'][] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Display flash messages
 */
function displayFlashMessages() {
    if (!isset($_SESSION['flash_messages']) || empty($_SESSION['flash_messages'])) {
        return;
    }
    
    foreach ($_SESSION['flash_messages'] as $flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        
        // Determine CSS classes based on message type
        $classes = '';
        $icon = '';
        switch ($type) {
            case 'success':
                $classes = 'bg-green-100 border-green-400 text-green-700';
                $icon = 'fa-check-circle';
                break;
            case 'error':
                $classes = 'bg-red-100 border-red-400 text-red-700';
                $icon = 'fa-exclamation-circle';
                break;
            case 'warning':
                $classes = 'bg-yellow-100 border-yellow-400 text-yellow-700';
                $icon = 'fa-exclamation-triangle';
                break;
            default:
                $classes = 'bg-blue-100 border-blue-400 text-blue-700';
                $icon = 'fa-info-circle';
                break;
        }
        
        echo '<div class="' . $classes . ' px-4 py-3 rounded border mb-4">';
        echo '<div class="flex items-center">';
        echo '<i class="fa-solid ' . $icon . ' mr-2"></i>';
        echo '<span>' . htmlspecialchars($message) . '</span>';
        echo '</div>';
        echo '</div>';
    }
    
    // Clear flash messages after displaying
    unset($_SESSION['flash_messages']);
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return 'Rs. ' . number_format($amount, 2);
}

/**
 * Time ago function
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31104000) return floor($time/2592000) . ' months ago';
    return floor($time/31104000) . ' years ago';
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get current page name
 */
function getCurrentPage() {
    return basename($_SERVER['PHP_SELF'], '.php');
}

/**
 * Check if current page matches
 */
function isCurrentPage($page) {
    return getCurrentPage() === $page;
}
?>
