<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Require admin role BEFORE any output
$auth->requireRole('admin', '/index.php');

// Get database connection
$db = getDB();

$pageTitle = 'Provider Management • ServiceLink Admin';
$pageDescription = 'Manage service providers, verification status and profiles.';

// Handle provider actions
if ($_POST) {
    if (isset($_POST['action']) && function_exists('verifyCSRFToken') && verifyCSRFToken($_POST['csrf_token'])) {
        switch ($_POST['action']) {
            case 'toggle_verification':
                if (isset($_POST['provider_id']) && is_numeric($_POST['provider_id'])) {
                    try {
                        $stmt = $db->prepare("UPDATE providers SET is_verified = CASE WHEN is_verified = 1 THEN 0 ELSE 1 END WHERE id = ?");
                        $stmt->execute([$_POST['provider_id']]);
                        if (function_exists('setFlashMessage')) {
                            setFlashMessage('Provider verification status updated', 'success');
                        }
                    } catch (PDOException $e) {
                        if (function_exists('setFlashMessage')) {
                            setFlashMessage('Error updating verification: ' . $e->getMessage(), 'error');
                        }
                    }
                }
                break;
                
            case 'toggle_active':
                if (isset($_POST['provider_id']) && is_numeric($_POST['provider_id'])) {
                    try {
                        $stmt = $db->prepare("UPDATE providers SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?");
                        $stmt->execute([$_POST['provider_id']]);
                        if (function_exists('setFlashMessage')) {
                            setFlashMessage('Provider status updated', 'success');
                        }
                    } catch (PDOException $e) {
                        if (function_exists('setFlashMessage')) {
                            setFlashMessage('Error updating status: ' . $e->getMessage(), 'error');
                        }
                    }
                }
                break;
                
            case 'delete_provider':
                if (isset($_POST['provider_id']) && is_numeric($_POST['provider_id'])) {
                    try {
                        $stmt = $db->prepare("DELETE FROM providers WHERE id = ?");
                        $stmt->execute([$_POST['provider_id']]);
                        if (function_exists('setFlashMessage')) {
                            setFlashMessage('Provider deleted successfully', 'success');
                        }
                    } catch (PDOException $e) {
                        if (function_exists('setFlashMessage')) {
                            setFlashMessage('Error deleting provider: ' . $e->getMessage(), 'error');
                        }
                    }
                }
                break;
        }
        
        header("Location: providers.php");
        exit();
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(COALESCE(u.first_name, '') LIKE ? OR COALESCE(u.last_name, '') LIKE ? OR COALESCE(p.business_name, '') LIKE ? OR COALESCE(p.location, '') LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($category_filter && is_numeric($category_filter)) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    switch ($status_filter) {
        case 'verified':
            $where_conditions[] = "p.is_verified = 1";
            break;
        case 'unverified':
            $where_conditions[] = "p.is_verified = 0";
            break;
        case 'active':
            $where_conditions[] = "p.is_active = 1";
            break;
        case 'inactive':
            $where_conditions[] = "p.is_active = 0";
            break;
    }
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get categories for filter - handle case where categories table might not exist
$categories = [];
try {
    // First check if categories table exists
    $check_table = $db->query("SHOW TABLES LIKE 'categories'");
    if ($check_table->rowCount() > 0) {
        $categories_stmt = $db->prepare("SELECT id, name FROM categories ORDER BY name");
        $categories_stmt->execute();
        $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Categories table might not exist yet
    $categories = [];
}

// Get total count - handle case where users table might not exist
try {
    if (empty($categories)) {
        // If no categories, just count providers
        $count_sql = "SELECT COUNT(*) FROM providers p $where_clause";
        $count_params = [];
        // Remove category-related conditions if categories don't exist
        if ($category_filter) {
            $where_conditions = array_filter($where_conditions, function($condition) {
                return strpos($condition, 'category_id') === false;
            });
            $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            $count_sql = "SELECT COUNT(*) FROM providers p $where_clause";
            // Remove category param
            $count_params = array_slice($params, 0, -1);
        } else {
            $count_params = $params;
        }
    } else {
        // Check if users table exists
        $check_users = $db->query("SHOW TABLES LIKE 'users'");
        if ($check_users->rowCount() > 0) {
            $count_sql = "SELECT COUNT(*) FROM providers p 
                          LEFT JOIN users u ON p.user_id = u.id 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          $where_clause";
        } else {
            $count_sql = "SELECT COUNT(*) FROM providers p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          $where_clause";
        }
        $count_params = $params;
    }
    
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_providers = $count_stmt->fetchColumn();
    $total_pages = ceil($total_providers / $per_page);
} catch (PDOException $e) {
    $total_providers = 0;
    $total_pages = 1;
    if (function_exists('setFlashMessage')) {
        setFlashMessage('Error counting providers: ' . $e->getMessage(), 'error');
    }
}

// Get providers - handle missing tables gracefully
$providers = [];
try {
    // Check what tables exist
    $check_users = $db->query("SHOW TABLES LIKE 'users'");
    $check_categories = $db->query("SHOW TABLES LIKE 'categories'");
    
    $users_exist = $check_users->rowCount() > 0;
    $categories_exist = $check_categories->rowCount() > 0;
    
    if ($users_exist && $categories_exist) {
        $sql = "SELECT p.*, 
                       COALESCE(u.first_name, 'Unknown') as first_name, 
                       COALESCE(u.last_name, 'User') as last_name, 
                       COALESCE(u.email, 'N/A') as email, 
                       COALESCE(u.phone, 'N/A') as phone, 
                       COALESCE(c.name, 'Unknown') as category_name, 
                       COALESCE(c.icon, 'fa-solid fa-briefcase') as category_icon
                FROM providers p 
                LEFT JOIN users u ON p.user_id = u.id 
                LEFT JOIN categories c ON p.category_id = c.id 
                $where_clause 
                ORDER BY p.created_at DESC 
                LIMIT $per_page OFFSET $offset";
    } else if ($users_exist && !$categories_exist) {
        $sql = "SELECT p.*, 
                       COALESCE(u.first_name, 'Unknown') as first_name, 
                       COALESCE(u.last_name, 'User') as last_name, 
                       COALESCE(u.email, 'N/A') as email, 
                       COALESCE(u.phone, 'N/A') as phone,
                       'Unknown Category' as category_name, 
                       'fa-solid fa-briefcase' as category_icon
                FROM providers p 
                LEFT JOIN users u ON p.user_id = u.id 
                $where_clause 
                ORDER BY p.created_at DESC 
                LIMIT $per_page OFFSET $offset";
    } else if (!$users_exist && $categories_exist) {
        $sql = "SELECT p.*, 
                       'Unknown' as first_name, 
                       'User' as last_name, 
                       'N/A' as email, 
                       'N/A' as phone,
                       COALESCE(c.name, 'Unknown') as category_name, 
                       COALESCE(c.icon, 'fa-solid fa-briefcase') as category_icon
                FROM providers p 
                LEFT JOIN categories c ON p.category_id = c.id 
                $where_clause 
                ORDER BY p.created_at DESC 
                LIMIT $per_page OFFSET $offset";
    } else {
        $sql = "SELECT p.*, 
                       'Unknown' as first_name, 
                       'User' as last_name, 
                       'N/A' as email, 
                       'N/A' as phone,
                       'Unknown Category' as category_name, 
                       'fa-solid fa-briefcase' as category_icon
                FROM providers p 
                $where_clause 
                ORDER BY p.created_at DESC 
                LIMIT $per_page OFFSET $offset";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $providers = [];
    if (function_exists('setFlashMessage')) {
        setFlashMessage('Error loading providers: ' . $e->getMessage(), 'error');
    }
    // Show the actual error for debugging
    echo "<!-- Database Error: " . $e->getMessage() . " -->";
}

// Function to safely display profile photo
function getProfilePhotoUrl($profile_photo) {
    if (empty($profile_photo)) {
        return null;
    }
    
    // If it's already a full URL (starts with http), return as is
    if (strpos($profile_photo, 'http') === 0) {
        return $profile_photo;
    }
    
    // Otherwise, prepend BASE_URL if it exists
    $base_url = defined('BASE_URL') ? BASE_URL : '';
    return $base_url . '/' . ltrim($profile_photo, '/');
}

// Function to safely escape HTML
function safe_echo($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe_echo($pageTitle); ?></title>
    <meta name="description" content="<?php echo safe_echo($pageDescription); ?>">
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
    <?php 
    // Check if admin nav exists
    if (file_exists('../components/admin-nav.php')) {
        include '../components/admin-nav.php'; 
    } else {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Warning: admin-nav.php not found</div>';
    }
    ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-neutral-900">Provider Management</h1>
            <p class="text-neutral-600 mt-2">Manage service providers, verification and profiles</p>
        </div>

        <!-- Flash Messages -->
        <?php 
        if (function_exists('displayFlashMessages')) {
            displayFlashMessages(); 
        }
        ?>

        <!-- Search and Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6 mb-6">
            <form method="GET" class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo safe_echo($search); ?>" 
                           placeholder="Search providers by name, business or location..."
                           class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <select name="category" class="px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo safe_echo($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <select name="status" class="px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="unverified" <?php echo $status_filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg transition-colors">
                    <i class="fa-solid fa-search mr-2"></i>Search
                </button>
            </form>
        </div>

        <!-- Providers Table -->
        <div class="bg-white rounded-xl shadow-sm border border-neutral-200 overflow-hidden">
            <div class="p-6 border-b border-neutral-200">
                <h2 class="text-xl font-semibold text-neutral-900">
                    Providers (<?php echo number_format($total_providers); ?>)
                </h2>
            </div>

            <?php if (empty($providers)): ?>
                <div class="p-12 text-center">
                    <i class="fa-solid fa-briefcase text-4xl text-neutral-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-neutral-500 mb-2">No providers found</h3>
                    <p class="text-neutral-400">Try adjusting your search criteria or check if providers exist in the database</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Provider</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Rate</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Rating</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            <?php foreach ($providers as $provider): ?>
                            <tr class="hover:bg-neutral-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center mr-3">
                                            <?php 
                                            $profile_photo_url = getProfilePhotoUrl($provider['profile_photo'] ?? '');
                                            if ($profile_photo_url): 
                                            ?>
                                                <img src="<?php echo safe_echo($profile_photo_url); ?>" 
                                                     alt="Profile" class="w-12 h-12 rounded-full object-cover"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <span class="text-primary-600 font-medium" style="display:none;">
                                                    <?php echo strtoupper(substr($provider['first_name'] ?? 'U', 0, 1)); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-primary-600 font-medium">
                                                    <?php echo strtoupper(substr($provider['first_name'] ?? 'U', 0, 1)); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-medium text-neutral-900">
                                                <?php echo safe_echo(($provider['first_name'] ?? 'Unknown') . ' ' . ($provider['last_name'] ?? 'User')); ?>
                                            </div>
                                            <?php if (!empty($provider['business_name'])): ?>
                                                <div class="text-sm text-neutral-500"><?php echo safe_echo($provider['business_name']); ?></div>
                                            <?php endif; ?>
                                            <div class="text-xs text-neutral-400"><?php echo safe_echo($provider['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-2">
                                        <i class="<?php echo safe_echo($provider['category_icon']); ?> text-primary-500"></i>
                                        <?php echo safe_echo($provider['category_name']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?php echo safe_echo($provider['location'] ?? ''); ?></td>
                                <td class="px-6 py-4"><?php echo safe_echo($provider['rate'] ?? ''); ?></td>
                                <td class="px-6 py-4">
                                    <?php if (isset($provider['rating'])): ?>
                                        <span class="inline-flex items-center gap-1">
                                            <i class="fa-solid fa-star text-yellow-400"></i>
                                            <?php echo number_format($provider['rating'], 1); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-neutral-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-2">
                                        <?php if ($provider['is_verified']): ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Verified</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">Unverified</span>
                                        <?php endif; ?>
                                        <?php if ($provider['is_active']): ?>
                                            <span class="px-2 py-1 bg-primary-100 text-primary-700 rounded text-xs">Active</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-neutral-100 text-neutral-700 rounded text-xs">Inactive</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo safe_echo($_SESSION['csrf_token'] ?? ''); ?>">
                                        <input type="hidden" name="provider_id" value="<?php echo (int)$provider['id']; ?>">
                                        <button type="submit" name="action" value="toggle_verification" title="Toggle verification" class="text-xs px-2 py-1 rounded bg-yellow-100 text-yellow-700 hover:bg-yellow-200 mr-1">
                                            <i class="fa-solid fa-shield-halved"></i>
                                        </button>
                                        <button type="submit" name="action" value="toggle_active" title="Toggle active status" class="text-xs px-2 py-1 rounded bg-primary-100 text-primary-700 hover:bg-primary-200 mr-1">
                                            <i class="fa-solid fa-power-off"></i>
                                        </button>
                                        <button type="submit" name="action" value="delete_provider" title="Delete provider" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <div class="px-6 py-4 border-t border-neutral-200 bg-neutral-50 flex items-center justify-between">
                <div class="text-sm text-neutral-500">
                    Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $per_page, $total_providers)); ?> 
                    of <?php echo number_format($total_providers); ?> providers
                </div>
                <div class="flex space-x-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="px-3 py-2 text-sm border border-neutral-300 rounded-md hover:bg-neutral-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="px-3 py-2 text-sm border rounded-md <?php echo $i === $page ? 'bg-primary-600 text-white border-primary-600' : 'border-neutral-300 hover:bg-neutral-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="px-3 py-2 text-sm border border-neutral-300 rounded-md hover:bg-neutral-50">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add some basic JavaScript for better UX
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form on status/category change
    const selects = document.querySelectorAll('select[name="category"], select[name="status"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('button[title="Delete provider"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this provider? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });
    });
});
</script>

</body>
</html>