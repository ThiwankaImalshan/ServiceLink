<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Require admin role BEFORE any output
$auth->requireRole('admin', '/index.php');

// Get database connection
$db = getDB();

$pageTitle = 'Edit User • ServiceLink Admin';
$pageDescription = 'Edit user account details and settings.';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    setFlashMessage('Invalid user ID', 'error');
    header("Location: users.php");
    exit();
}

// Handle form submission
if ($_POST) {
    if (isset($_POST['action']) && verifyCSRFToken($_POST['csrf_token'])) {
        switch ($_POST['action']) {
            case 'update_user':
                try {
                    $db->beginTransaction();
                    
                    $updateFields = [];
                    $updateValues = [];

                    // Dynamically build the query based on non-empty fields
                    if (!empty($_POST['first_name'])) {
                        $updateFields[] = "first_name = ?";
                        $updateValues[] = $_POST['first_name'];
                    }
                    if (!empty($_POST['last_name'])) {
                        $updateFields[] = "last_name = ?";
                        $updateValues[] = $_POST['last_name'];
                    }
                    if (!empty($_POST['email'])) {
                        $updateFields[] = "email = ?";
                        $updateValues[] = $_POST['email'];
                    }
                    if (!empty($_POST['phone'])) {
                        $updateFields[] = "phone = ?";
                        $updateValues[] = $_POST['phone'];
                    }
                    if (!empty($_POST['role'])) {
                        $updateFields[] = "role = ?";
                        $updateValues[] = $_POST['role'];
                    }
                    $updateFields[] = "email_verified = ?";
                    $updateValues[] = isset($_POST['email_verified']) ? 1 : 0;

                    $updateValues[] = $user_id;

                    // Only execute the query if there are fields to update
                    if (!empty($updateFields)) {
                        $stmt = $db->prepare("UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?");
                        $stmt->execute($updateValues);
                    }
                    
                    // Update password if provided
                    if (!empty($_POST['new_password'])) {
                        if ($_POST['new_password'] !== $_POST['confirm_password']) {
                            throw new Exception('Passwords do not match');
                        }
                        
                        $password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $stmt->execute([$password_hash, $user_id]);
                    }
                    
                    $db->commit();
                    setFlashMessage('User updated successfully', 'success');
                    header("Location: edit-user.php?id={$user_id}");
                    exit();
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    setFlashMessage('Error updating user: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'delete_user':
                if ($user_id != 1) { // Protect admin user
                    try {
                        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        setFlashMessage('User deleted successfully', 'success');
                        header("Location: users.php");
                        exit();
                    } catch (PDOException $e) {
                        setFlashMessage('Error deleting user: ' . $e->getMessage(), 'error');
                    }
                } else {
                    setFlashMessage('Cannot delete admin user', 'error');
                }
                break;
        }
    }
}

// Get user details
try {
    $stmt = $db->prepare("
        SELECT u.*, 
               CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END as is_provider,
               p.id as provider_id,
               p.business_name,
               p.location,
               p.hourly_rate,
               p.rating,
               p.review_count,
               p.is_verified,
               p.is_active,
               c.name as category_name
        FROM users u 
        LEFT JOIN providers p ON u.id = p.user_id 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('User not found', 'error');
        header("Location: users.php");
        exit();
    }
} catch (PDOException $e) {
    setFlashMessage('Error loading user: ' . $e->getMessage(), 'error');
    header("Location: users.php");
    exit();
}

// Get user's reviews if they're a provider
$reviews = [];
if ($user['is_provider']) {
    try {
        $stmt = $db->prepare("
            SELECT r.*, u.first_name, u.last_name 
            FROM reviews r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.provider_id = ? 
            ORDER BY r.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user['provider_id']]);
        $reviews = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Reviews are optional, don't fail if there's an error
    }
}

// Include header
include '../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-50">
    <?php include '../components/admin-nav.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-neutral-900">Edit User</h1>
                    <p class="text-neutral-600 mt-2">Manage user account details and settings</p>
                </div>
                <a href="users.php" class="bg-neutral-200 hover:bg-neutral-300 text-neutral-700 px-4 py-2 rounded-lg transition-colors">
                    <i class="fa-solid fa-arrow-left mr-2"></i>Back to Users
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php displayFlashMessages(); ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- User Details -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6">
                    <h2 class="text-xl font-semibold text-neutral-900 mb-6">User Information</h2>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_user">
                        
                        <!-- Basic Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-neutral-700 mb-2">First Name *</label>
                                <input type="text" id="first_name" name="first_name" required
                                       value="<?php echo e($user['first_name']); ?>"
                                       class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-neutral-700 mb-2">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" required
                                       value="<?php echo e($user['last_name']); ?>"
                                       class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="username" class="block text-sm font-medium text-neutral-700 mb-2">Username</label>
                                <input type="text" id="username" name="username" disabled
                                       value="<?php echo e($user['username']); ?>"
                                       class="w-full px-4 py-2 border border-neutral-300 rounded-lg bg-neutral-100 text-neutral-500">
                                <p class="text-xs text-neutral-500 mt-1">Username cannot be changed</p>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-neutral-700 mb-2">Email *</label>
                                <input type="email" id="email" name="email" required
                                       value="<?php echo e($user['email']); ?>"
                                       class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-neutral-700 mb-2">Phone</label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?php echo e($user['phone']); ?>"
                                       class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="role" class="block text-sm font-medium text-neutral-700 mb-2">Role *</label>
                                <select id="role" name="role" required
                                        class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                        <?php echo $user['id'] == 1 ? 'disabled' : ''; ?>>
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="provider" <?php echo $user['role'] === 'provider' ? 'selected' : ''; ?>>Provider</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <?php if ($user['id'] == 1): ?>
                                    <p class="text-xs text-neutral-500 mt-1">Admin user role cannot be changed</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Account Status -->
                        <div class="border-t border-neutral-200 pt-6">
                            <h3 class="text-lg font-medium text-neutral-900 mb-4">Account Status</h3>
                            <div class="flex items-center">
                                <label class="flex items-center">
                                    <input type="checkbox" name="email_verified" value="1" 
                                           <?php echo $user['email_verified'] ? 'checked' : ''; ?>
                                           class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-neutral-700">Email verified</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Password Change -->
                        <div class="border-t border-neutral-200 pt-6">
                            <h3 class="text-lg font-medium text-neutral-900 mb-4">Change Password</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="new_password" class="block text-sm font-medium text-neutral-700 mb-2">New Password</label>
                                    <input type="password" id="new_password" name="new_password"
                                           class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                    <p class="text-xs text-neutral-500 mt-1">Leave blank to keep current password</p>
                                </div>
                                
                                <div>
                                    <label for="confirm_password" class="block text-sm font-medium text-neutral-700 mb-2">Confirm Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password"
                                           class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex justify-between items-center pt-6 border-t border-neutral-200">
                            <div>
                                <?php if ($user['id'] != 1): // Don't allow deleting admin user ?>
                                <button type="button" onclick="confirmDelete()" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg transition-colors">
                                    <i class="fa-solid fa-trash mr-2"></i>Delete User
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <button type="submit" 
                                    class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-2 rounded-lg transition-colors">
                                <i class="fa-solid fa-save mr-2"></i>Update User
                            </button>
                        </div>
                    </form>
                    
                    <!-- Hidden delete form -->
                    <form id="deleteForm" method="POST" style="display: none;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete_user">
                    </form>
                </div>
            </div>

            <!-- User Summary & Provider Info -->
            <div class="lg:col-span-1 space-y-6">
                <!-- User Summary -->
                <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6">
                    <h3 class="text-lg font-semibold text-neutral-900 mb-4">User Summary</h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mr-4">
                                <span class="text-primary-600 font-medium text-xl">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                </span>
                            </div>
                            <div>
                                <h4 class="font-medium text-neutral-900">
                                    <?php echo e($user['first_name'] . ' ' . $user['last_name']); ?>
                                </h4>
                                <p class="text-sm text-neutral-500">@<?php echo e($user['username']); ?></p>
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full mt-1
                                    <?php 
                                    switch($user['role']) {
                                        case 'admin': echo 'bg-red-100 text-red-800'; break;
                                        case 'provider': echo 'bg-blue-100 text-blue-800'; break;
                                        default: echo 'bg-green-100 text-green-800'; break;
                                    }
                                    ?>">
                                    <?php echo ucfirst($user['role'] ?? 'unknown'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="border-t border-neutral-200 pt-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-neutral-600">Email:</span>
                                <span class="font-medium"><?php echo e($user['email']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-neutral-600">Phone:</span>
                                <span class="font-medium"><?php echo $user['phone'] ? e($user['phone']) : 'Not provided'; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-neutral-600">Status:</span>
                                <span class="font-medium">
                                    <?php if ($user['email_verified']): ?>
                                        <span class="text-green-600">Verified</span>
                                    <?php else: ?>
                                        <span class="text-yellow-600">Unverified</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-neutral-600">Joined:</span>
                                <span class="font-medium"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Provider Information -->
                <?php if ($user['is_provider']): ?>
                <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6">
                    <h3 class="text-lg font-semibold text-neutral-900 mb-4">Provider Details</h3>
                    
                    <div class="space-y-3 text-sm">
                        <?php if ($user['business_name']): ?>
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Business:</span>
                            <span class="font-medium"><?php echo e($user['business_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Category:</span>
                            <span class="font-medium"><?php echo e($user['category_name']); ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Location:</span>
                            <span class="font-medium"><?php echo e($user['location']); ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Rate:</span>
                            <span class="font-medium">LKR <?php echo number_format($user['hourly_rate'], 2); ?>/hr</span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Rating:</span>
                            <span class="font-medium"><?php echo number_format($user['rating'], 1); ?>/5 (<?php echo $user['review_count']; ?> reviews)</span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Verified:</span>
                            <span class="font-medium">
                                <?php if ($user['is_verified']): ?>
                                    <span class="text-green-600">Yes</span>
                                <?php else: ?>
                                    <span class="text-red-600">No</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Status:</span>
                            <span class="font-medium">
                                <?php if ($user['is_active']): ?>
                                    <span class="text-green-600">Active</span>
                                <?php else: ?>
                                    <span class="text-red-600">Inactive</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-neutral-200">
                        <a href="<?php echo BASE_URL; ?>/provider-profile.php?id=<?php echo $user['provider_id']; ?>" 
                           target="_blank"
                           class="w-full bg-primary-600 hover:bg-primary-700 text-white text-center py-2 px-4 rounded-lg transition-colors block">
                            View Provider Profile
                        </a>
                    </div>
                </div>
                
                <!-- Recent Reviews -->
                <?php if (!empty($reviews)): ?>
                <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6">
                    <h3 class="text-lg font-semibold text-neutral-900 mb-4">Recent Reviews</h3>
                    
                    <div class="space-y-4">
                        <?php foreach (array_slice($reviews, 0, 3) as $review): ?>
                        <div class="border border-neutral-200 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium text-sm"><?php echo e($review['first_name'] . ' ' . $review['last_name']); ?></span>
                                <div class="flex text-yellow-400">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-solid fa-star<?php echo $i <= $review['rating'] ? '' : ' text-neutral-300'; ?> text-xs"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if ($review['comment']): ?>
                                <p class="text-xs text-neutral-600"><?php echo e(substr($review['comment'], 0, 100)); ?><?php echo strlen($review['comment']) > 100 ? '...' : ''; ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-neutral-400 mt-1"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('new_password').value;
    const confirm = this.value;
    
    if (password && confirm && password !== confirm) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
