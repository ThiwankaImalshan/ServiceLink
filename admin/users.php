<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Require admin role BEFORE any output
$auth->requireRole('admin', '/index.php');

// Get database connection
$db = getDB();

$pageTitle = 'User Management • ServiceLink Admin';
$pageDescription = 'Manage users, their roles and account status.';

// Handle user actions (delete, role change)
if ($_POST) {
    if (isset($_POST['action']) && verifyCSRFToken($_POST['csrf_token'])) {
        switch ($_POST['action']) {
            case 'delete_user':
                if (isset($_POST['user_id'])) {
                    try {
                        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND id != 1"); // Protect admin user
                        $stmt->execute([$_POST['user_id']]);
                        setFlashMessage('User deleted successfully', 'success');
                    } catch (PDOException $e) {
                        setFlashMessage('Error deleting user: ' . $e->getMessage(), 'error');
                    }
                }
                break;
                
            case 'change_role':
                if (isset($_POST['user_id']) && isset($_POST['new_role'])) {
                    try {
                        $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ? AND id != 1"); // Protect admin user
                        $stmt->execute([$_POST['new_role'], $_POST['user_id']]);
                        setFlashMessage('User role updated successfully', 'success');
                    } catch (PDOException $e) {
                        setFlashMessage('Error updating user role: ' . $e->getMessage(), 'error');
                    }
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header("Location: users.php");
        exit();
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($role_filter) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
try {
    $count_sql = "SELECT COUNT(*) FROM users $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_users = $count_stmt->fetchColumn();
    $total_pages = ceil($total_users / $per_page);
} catch (PDOException $e) {
    $total_users = 0;
    $total_pages = 1;
}

// Get users
try {
    $sql = "SELECT id, username, email, first_name, last_name, role, email_verified, created_at 
            FROM users $where_clause 
            ORDER BY created_at DESC 
            LIMIT $per_page OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    setFlashMessage('Error loading users: ' . $e->getMessage(), 'error');
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
            <h1 class="text-3xl font-bold text-neutral-900">User Management</h1>
            <p class="text-neutral-600 mt-2">Manage user accounts, roles and permissions</p>
        </div>

        <!-- Flash Messages -->
        <?php displayFlashMessages(); ?>

        <!-- Search and Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6 mb-6">
            <form method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo e($search); ?>" 
                           placeholder="Search users by name, username or email..."
                           class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <select name="role" class="px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="">All Roles</option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Users</option>
                        <option value="provider" <?php echo $role_filter === 'provider' ? 'selected' : ''; ?>>Providers</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                    </select>
                </div>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg transition-colors">
                    <i class="fa-solid fa-search mr-2"></i>Search
                </button>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-sm border border-neutral-200 overflow-hidden">
            <div class="p-6 border-b border-neutral-200">
                <h2 class="text-xl font-semibold text-neutral-900">
                    Users (<?php echo number_format($total_users); ?>)
                </h2>
            </div>

            <?php if (empty($users)): ?>
                <div class="p-12 text-center">
                    <i class="fa-solid fa-users text-4xl text-neutral-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-neutral-500 mb-2">No users found</h3>
                    <p class="text-neutral-400">Try adjusting your search criteria</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Joined</th>
                                <th class="px-6 py-4 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-neutral-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center mr-3">
                                            <span class="text-primary-600 font-medium">
                                                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="font-medium text-neutral-900">
                                                <?php echo e($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-neutral-500">@<?php echo e($user['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-neutral-900">
                                    <?php echo e($user['email']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                        <?php 
                                        switch($user['role']) {
                                            case 'admin': echo 'bg-red-100 text-red-800'; break;
                                            case 'provider': echo 'bg-blue-100 text-blue-800'; break;
                                            default: echo 'bg-green-100 text-green-800'; break;
                                        }
                                        ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($user['email_verified']): ?>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                            <i class="fa-solid fa-check-circle mr-1"></i>Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                            <i class="fa-solid fa-clock mr-1"></i>Unverified
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-neutral-500">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="edit-user.php?id=<?php echo $user['id']; ?>" 
                                           class="text-primary-600 hover:text-primary-700">
                                            <i class="fa-solid fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($user['id'] != 1): // Don't allow deleting admin user ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-700 ml-2">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
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
                            Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $per_page, $total_users)); ?> 
                            of <?php echo number_format($total_users); ?> users
                        </div>
                        <div class="flex space-x-1">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" 
                                   class="px-3 py-2 text-sm border border-neutral-300 rounded-md hover:bg-neutral-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" 
                                   class="px-3 py-2 text-sm border rounded-md <?php echo $i === $page ? 'bg-primary-600 text-white border-primary-600' : 'border-neutral-300 hover:bg-neutral-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" 
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
