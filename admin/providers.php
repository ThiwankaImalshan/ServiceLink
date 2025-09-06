<?php
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
    if (isset($_POST['action']) && verifyCSRFToken($_POST['csrf_token'])) {
        switch ($_POST['action']) {
            case 'toggle_verification':
                if (isset($_POST['provider_id'])) {
                    try {
                        $stmt = $db->prepare("UPDATE providers SET is_verified = NOT is_verified WHERE id = ?");
                        $stmt->execute([$_POST['provider_id']]);
                        setFlashMessage('Provider verification status updated', 'success');
                    } catch (PDOException $e) {
                        setFlashMessage('Error updating verification: ' . $e->getMessage(), 'error');
                    }
                }
                break;
                
            case 'toggle_active':
                if (isset($_POST['provider_id'])) {
                    try {
                        $stmt = $db->prepare("UPDATE providers SET is_active = NOT is_active WHERE id = ?");
                        $stmt->execute([$_POST['provider_id']]);
                        setFlashMessage('Provider status updated', 'success');
                    } catch (PDOException $e) {
                        setFlashMessage('Error updating status: ' . $e->getMessage(), 'error');
                    }
                }
                break;
                
            case 'delete_provider':
                if (isset($_POST['provider_id'])) {
                    try {
                        $stmt = $db->prepare("DELETE FROM providers WHERE id = ?");
                        $stmt->execute([$_POST['provider_id']]);
                        setFlashMessage('Provider deleted successfully', 'success');
                    } catch (PDOException $e) {
                        setFlashMessage('Error deleting provider: ' . $e->getMessage(), 'error');
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

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR p.business_name LIKE ? OR p.location LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($category_filter) {
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

// Get categories for filter
try {
    $categories_stmt = $db->prepare("SELECT id, name FROM categories ORDER BY name");
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Get total count
try {
    $count_sql = "SELECT COUNT(*) FROM providers p 
                  JOIN users u ON p.user_id = u.id 
                  JOIN categories c ON p.category_id = c.id $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_providers = $count_stmt->fetchColumn();
    $total_pages = ceil($total_providers / $per_page);
} catch (PDOException $e) {
    $total_providers = 0;
    $total_pages = 1;
}

// Get providers
try {
    $sql = "SELECT p.*, u.first_name, u.last_name, u.email, u.phone, c.name as category_name, c.icon as category_icon
            FROM providers p 
            JOIN users u ON p.user_id = u.id 
            JOIN categories c ON p.category_id = c.id 
            $where_clause 
            ORDER BY p.created_at DESC 
            LIMIT $per_page OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $providers = $stmt->fetchAll();
} catch (PDOException $e) {
    $providers = [];
    setFlashMessage('Error loading providers: ' . $e->getMessage(), 'error');
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
            <h1 class="text-3xl font-bold text-neutral-900">Provider Management</h1>
            <p class="text-neutral-600 mt-2">Manage service providers, verification and profiles</p>
        </div>

        <!-- Flash Messages -->
        <?php displayFlashMessages(); ?>

        <!-- Search and Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6 mb-6">
            <form method="GET" class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo e($search); ?>" 
                           placeholder="Search providers by name, business or location..."
                           class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <select name="category" class="px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo e($category['name']); ?>
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
                    <p class="text-neutral-400">Try adjusting your search criteria</p>
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
                                            <?php if ($provider['profile_photo']): ?>
                                                <img src="<?php echo BASE_URL; ?>/<?php echo e($provider['profile_photo']); ?>" 
                                                     alt="Profile" class="w-12 h-12 rounded-full object-cover">
                                            <?php else: ?>
                                                <span class="text-primary-600 font-medium">
                                                    <?php echo strtoupper(substr($provider['first_name'], 0, 1)); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-medium text-neutral-900">
                                                <?php echo e($provider['first_name'] . ' ' . $provider['last_name']); ?>
                                            </div>
                                            <?php if ($provider['business_name']): ?>
                                                <div class="text-sm text-neutral-500"><?php echo e($provider['business_name']); ?></div>
                                            <?php endif; ?>
                                            <div class="text-xs text-neutral-400"><?php echo e($provider['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <i class="<?php echo e($provider['category_icon']); ?> text-primary-600 mr-2"></i>
                                        <span class="text-sm text-neutral-900"><?php echo e($provider['category_name']); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-neutral-900">
                                    <?php echo e($provider['location']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-neutral-900">
                                    LKR <?php echo number_format($provider['hourly_rate'], 2); ?>/hr
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex text-yellow-400 mr-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fa-solid fa-star<?php echo $i <= $provider['rating'] ? '' : ' text-neutral-300'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-sm text-neutral-500">(<?php echo $provider['review_count']; ?>)</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col space-y-1">
                                        <?php if ($provider['is_verified']): ?>
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                                <i class="fa-solid fa-check-circle mr-1"></i>Verified
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                                <i class="fa-solid fa-clock mr-1"></i>Unverified
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($provider['is_active']): ?>
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                                <i class="fa-solid fa-power-off mr-1"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                                <i class="fa-solid fa-ban mr-1"></i>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        <!-- Toggle Verification -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="toggle_verification">
                                            <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                            <button type="submit" 
                                                    class="<?php echo $provider['is_verified'] ? 'text-yellow-600 hover:text-yellow-700' : 'text-green-600 hover:text-green-700'; ?>"
                                                    title="<?php echo $provider['is_verified'] ? 'Remove verification' : 'Verify provider'; ?>">
                                                <i class="fa-solid fa-<?php echo $provider['is_verified'] ? 'shield-xmark' : 'shield-check'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Toggle Active Status -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                            <button type="submit" 
                                                    class="<?php echo $provider['is_active'] ? 'text-red-600 hover:text-red-700' : 'text-blue-600 hover:text-blue-700'; ?>"
                                                    title="<?php echo $provider['is_active'] ? 'Deactivate provider' : 'Activate provider'; ?>">
                                                <i class="fa-solid fa-<?php echo $provider['is_active'] ? 'ban' : 'power-off'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- View Provider Profile -->
                                        <a href="<?php echo BASE_URL; ?>/provider-profile.php?id=<?php echo $provider['id']; ?>" 
                                           target="_blank"
                                           class="text-primary-600 hover:text-primary-700"
                                           title="View profile">
                                            <i class="fa-solid fa-external-link-alt"></i>
                                        </a>
                                        
                                        <!-- Delete Provider -->
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this provider?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="delete_provider">
                                            <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-700" title="Delete provider">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t border-neutral-200">
                    <div class="flex items-center justify-between">
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
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
