<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Require admin role BEFORE any output
$auth->requireRole('admin', '/index.php');

// Get database connection
$db = getDB();

$pageTitle = 'System Settings • ServiceLink Admin';
$pageDescription = 'Manage system-wide settings and configuration.';

// Handle settings update
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] === 'update_settings' && verifyCSRFToken($_POST['csrf_token'])) {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($_POST as $key => $value) {
            if ($key !== 'action' && $key !== 'csrf_token') {
                try {
                    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    if ($stmt->execute([$value, $key])) {
                        $success_count++;
                    }
                } catch (PDOException $e) {
                    $error_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            setFlashMessage("Settings updated successfully ({$success_count} items)", 'success');
        }
        if ($error_count > 0) {
            setFlashMessage("Some settings failed to update ({$error_count} errors)", 'error');
        }
        
        header("Location: settings.php");
        exit();
    }
}

// Get all settings
try {
    $stmt = $db->prepare("SELECT * FROM settings ORDER BY setting_key ASC");
    $stmt->execute();
    $settings_data = $stmt->fetchAll();
    
    // Convert to associative array for easier access
    $settings = [];
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting;
    }
} catch (PDOException $e) {
    $settings = [];
    setFlashMessage('Error loading settings: ' . $e->getMessage(), 'error');
}

// Get some system statistics
try {
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $total_users = $stmt->fetch()['count'];
    
    // Total providers
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM providers");
    $stmt->execute();
    $total_providers = $stmt->fetch()['count'];
    
    // Total categories
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM categories WHERE active = 1");
    $stmt->execute();
    $total_categories = $stmt->fetch()['count'];
    
    // Total reviews
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reviews");
    $stmt->execute();
    $total_reviews = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $total_users = $total_providers = $total_categories = $total_reviews = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <meta name="description" content="<?php echo e($pageDescription); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd',
                            400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8',
                            800: '#1e40af', 900: '#1e3a8a', 950: '#172554'
                        },
                        secondary: {
                            50: '#fdf4ff', 100: '#fae8ff', 200: '#f5d0fe', 300: '#f0abfc',
                            400: '#e879f9', 500: '#d946ef', 600: '#c026d3', 700: '#a21caf',
                            800: '#86198f', 900: '#701a75', 950: '#4a044e'
                        },
                        neutral: {
                            50: '#fafafa', 100: '#f5f5f5', 200: '#e5e5e5', 300: '#d4d4d4',
                            400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040',
                            800: '#262626', 900: '#171717', 950: '#0a0a0a'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="../assets/img/favicon_io/site.webmanifest">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-neutral-50 text-neutral-900">

<div class="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-50">
    <?php include '../components/admin-nav.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-neutral-900">System Settings</h1>
            <p class="text-neutral-600 mt-2">Manage system-wide configuration and preferences</p>
        </div>

        <!-- Flash Messages -->
        <?php displayFlashMessages(); ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- System Statistics -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6 mb-6">
                    <h2 class="text-xl font-semibold text-neutral-900 mb-6">System Overview</h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fa-solid fa-users text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-blue-700">Total Users</p>
                                    <p class="text-2xl font-bold text-blue-900"><?php echo number_format($total_users); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fa-solid fa-briefcase text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-green-700">Total Providers</p>
                                    <p class="text-2xl font-bold text-green-900"><?php echo number_format($total_providers); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-purple-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fa-solid fa-folder text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-purple-700">Active Categories</p>
                                    <p class="text-2xl font-bold text-purple-900"><?php echo number_format($total_categories); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-yellow-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fa-solid fa-star text-yellow-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-yellow-700">Total Reviews</p>
                                    <p class="text-2xl font-bold text-yellow-900"><?php echo number_format($total_reviews); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Info -->
                <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6">
                    <h3 class="text-lg font-semibold text-neutral-900 mb-4">System Information</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-neutral-600">PHP Version:</span>
                            <span class="font-medium"><?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Server Software:</span>
                            <span class="font-medium"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Database:</span>
                            <span class="font-medium">
                                <?php 
                                try {
                                    $version = $db->query('SELECT VERSION()')->fetchColumn();
                                    echo 'MySQL ' . $version;
                                } catch (Exception $e) {
                                    echo 'MySQL (Version unavailable)';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Memory Limit:</span>
                            <span class="font-medium"><?php echo ini_get('memory_limit'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Upload Max Size:</span>
                            <span class="font-medium"><?php echo ini_get('upload_max_filesize'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6">
                    <h2 class="text-xl font-semibold text-neutral-900 mb-6">Configuration Settings</h2>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <!-- Site Settings -->
                        <div class="border border-neutral-200 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-neutral-900 mb-4">Site Settings</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="site_name" class="block text-sm font-medium text-neutral-700 mb-2">Site Name</label>
                                    <input type="text" id="site_name" name="site_name" 
                                           value="<?php echo isset($settings['site_name']) ? e($settings['site_name']['setting_value']) : ''; ?>"
                                           class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                    <?php if (isset($settings['site_name']['description'])): ?>
                                        <p class="text-xs text-neutral-500 mt-1"><?php echo e($settings['site_name']['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <label for="contact_email" class="block text-sm font-medium text-neutral-700 mb-2">Contact Email</label>
                                    <input type="email" id="contact_email" name="contact_email" 
                                           value="<?php echo isset($settings['contact_email']) ? e($settings['contact_email']['setting_value']) : ''; ?>"
                                           class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                    <?php if (isset($settings['contact_email']['description'])): ?>
                                        <p class="text-xs text-neutral-500 mt-1"><?php echo e($settings['contact_email']['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label for="site_description" class="block text-sm font-medium text-neutral-700 mb-2">Site Description</label>
                                    <textarea id="site_description" name="site_description" rows="3"
                                              class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"><?php echo isset($settings['site_description']) ? e($settings['site_description']['setting_value']) : ''; ?></textarea>
                                    <?php if (isset($settings['site_description']['description'])): ?>
                                        <p class="text-xs text-neutral-500 mt-1"><?php echo e($settings['site_description']['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Settings -->
                        <div class="border border-neutral-200 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-neutral-900 mb-4">System Settings</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="default_currency" class="block text-sm font-medium text-neutral-700 mb-2">Default Currency</label>
                                    <select id="default_currency" name="default_currency" 
                                            class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                        <option value="USD" <?php echo (isset($settings['default_currency']) && $settings['default_currency']['setting_value'] === 'USD') ? 'selected' : ''; ?>>USD ($)</option>
                                        <option value="EUR" <?php echo (isset($settings['default_currency']) && $settings['default_currency']['setting_value'] === 'EUR') ? 'selected' : ''; ?>>EUR (€)</option>
                                        <option value="GBP" <?php echo (isset($settings['default_currency']) && $settings['default_currency']['setting_value'] === 'GBP') ? 'selected' : ''; ?>>GBP (£)</option>
                                        <option value="LKR" <?php echo (isset($settings['default_currency']) && $settings['default_currency']['setting_value'] === 'LKR') ? 'selected' : ''; ?>>LKR (Rs)</option>
                                        <option value="INR" <?php echo (isset($settings['default_currency']) && $settings['default_currency']['setting_value'] === 'INR') ? 'selected' : ''; ?>>INR (₹)</option>
                                    </select>
                                    <?php if (isset($settings['default_currency']['description'])): ?>
                                        <p class="text-xs text-neutral-500 mt-1"><?php echo e($settings['default_currency']['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <label for="items_per_page" class="block text-sm font-medium text-neutral-700 mb-2">Items Per Page</label>
                                    <input type="number" id="items_per_page" name="items_per_page" min="1" max="100"
                                           value="<?php echo isset($settings['items_per_page']) ? e($settings['items_per_page']['setting_value']) : '12'; ?>"
                                           class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                    <?php if (isset($settings['items_per_page']['description'])): ?>
                                        <p class="text-xs text-neutral-500 mt-1"><?php echo e($settings['items_per_page']['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <label for="max_file_size" class="block text-sm font-medium text-neutral-700 mb-2">Max File Size (bytes)</label>
                                    <input type="number" id="max_file_size" name="max_file_size" min="1024"
                                           value="<?php echo isset($settings['max_file_size']) ? e($settings['max_file_size']['setting_value']) : '5242880'; ?>"
                                           class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                    <?php if (isset($settings['max_file_size']['description'])): ?>
                                        <p class="text-xs text-neutral-500 mt-1"><?php echo e($settings['max_file_size']['description']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-neutral-500 mt-1">
                                        Current: <?php echo isset($settings['max_file_size']) ? round($settings['max_file_size']['setting_value'] / 1024 / 1024, 2) . 'MB' : '5MB'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Custom Settings -->
                        <?php
                        $system_keys = ['site_name', 'site_description', 'contact_email', 'default_currency', 'items_per_page', 'max_file_size'];
                        $custom_settings = array_filter($settings, function($key) use ($system_keys) {
                            return !in_array($key, $system_keys);
                        }, ARRAY_FILTER_USE_KEY);
                        ?>
                        
                        <?php if (!empty($custom_settings)): ?>
                        <div class="border border-neutral-200 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-neutral-900 mb-4">Additional Settings</h3>
                            <div class="space-y-4">
                                <?php foreach ($custom_settings as $key => $setting): ?>
                                <div>
                                    <label for="<?php echo e($key); ?>" class="block text-sm font-medium text-neutral-700 mb-2">
                                        <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                    </label>
                                    <input type="text" id="<?php echo e($key); ?>" name="<?php echo e($key); ?>" 
                                           value="<?php echo e($setting['setting_value']); ?>"
                                           class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                    <?php if ($setting['description']): ?>
                                        <p class="text-xs text-neutral-500 mt-1"><?php echo e($setting['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-3 rounded-lg transition-colors font-medium">
                                <i class="fa-solid fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
