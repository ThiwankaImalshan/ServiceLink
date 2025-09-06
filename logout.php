<?php
require_once 'config/auth.php';
require_once 'includes/functions.php';

// Logout user
$result = $auth->logout();

if ($result['success']) {
    setFlashMessage('You have been logged out successfully.', 'success');
} else {
    setFlashMessage('An error occurred during logout.', 'error');
}

// Redirect to homepage
redirect(BASE_URL . '/index.php');
?>
