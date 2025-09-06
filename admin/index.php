<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Require admin role BEFORE any output
$auth->requireRole('admin', '/index.php');

// Get database connection
$db = getDB();

$pageTitle = 'Admin Dashboard • ServiceLink';
$pageDescription = 'Manage users, providers, categories and system settings.';

// Include header
include '../includes/header.php';

// Get dashboard statistics
try {
    // Users count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $usersCount = $stmt->fetch()['count'];
    
    // Providers count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM providers WHERE is_active = 1");
    $stmt->execute();
    $providersCount = $stmt->fetch()['count'];
    
    // Active wanted ads count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM wanted_ads WHERE status = 'active'");
    $stmt->execute();
    $wantedAdsCount = $stmt->fetch()['count'];
    
    // Reviews count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reviews");
    $stmt->execute();
    $reviewsCount = $stmt->fetch()['count'];
    
    // Recent users
    $stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $recentUsers = $stmt->fetchAll();
    
    // Recent providers
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name, c.name as category_name 
        FROM providers p 
        JOIN users u ON p.user_id = u.id 
        JOIN categories c ON p.category_id = c.id 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentProviders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $usersCount = 0;
    $providersCount = 0;
    $wantedAdsCount = 0;
    $reviewsCount = 0;
    $recentUsers = [];
    $recentProviders = [];
}
?>

<div class="min-h-screen bg-gradient-to-br from-neutral-50 to-neutral-100 py-8">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-3xl sm:text-4xl font-bold text-neutral-900 mb-4">Admin Dashboard</h1>
      <p class="text-lg text-neutral-600">Manage your ServiceLink platform</p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      
      <!-- Users -->
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
            <i class="fa-solid fa-users text-2xl text-blue-600"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-neutral-600">Total Users</p>
            <p class="text-2xl font-bold text-neutral-900"><?php echo number_format($usersCount); ?></p>
          </div>
        </div>
      </div>

      <!-- Providers -->
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
            <i class="fa-solid fa-user-tie text-2xl text-green-600"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-neutral-600">Active Providers</p>
            <p class="text-2xl font-bold text-neutral-900"><?php echo number_format($providersCount); ?></p>
          </div>
        </div>
      </div>

      <!-- Wanted Ads -->
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
            <i class="fa-solid fa-bullhorn text-2xl text-yellow-600"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-neutral-600">Active Requests</p>
            <p class="text-2xl font-bold text-neutral-900"><?php echo number_format($wantedAdsCount); ?></p>
          </div>
        </div>
      </div>

      <!-- Reviews -->
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
            <i class="fa-solid fa-star text-2xl text-purple-600"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-neutral-600">Total Reviews</p>
            <p class="text-2xl font-bold text-neutral-900"><?php echo number_format($reviewsCount); ?></p>
          </div>
        </div>
      </div>

    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
      <h2 class="text-xl font-semibold text-neutral-900 mb-6">Quick Actions</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <a href="<?php echo BASE_URL; ?>/admin/users.php" class="flex items-center p-4 border border-neutral-200 rounded-lg hover:border-primary-300 hover:bg-primary-50 transition-colors">
          <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
            <i class="fa-solid fa-users text-blue-600"></i>
          </div>
          <div>
            <h3 class="font-medium text-neutral-900">Manage Users</h3>
            <p class="text-sm text-neutral-600">View and edit users</p>
          </div>
        </a>

        <a href="<?php echo BASE_URL; ?>/admin/providers.php" class="flex items-center p-4 border border-neutral-200 rounded-lg hover:border-primary-300 hover:bg-primary-50 transition-colors">
          <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
            <i class="fa-solid fa-user-tie text-green-600"></i>
          </div>
          <div>
            <h3 class="font-medium text-neutral-900">Manage Providers</h3>
            <p class="text-sm text-neutral-600">Approve and verify</p>
          </div>
        </a>

        <a href="<?php echo BASE_URL; ?>/admin/categories.php" class="flex items-center p-4 border border-neutral-200 rounded-lg hover:border-primary-300 hover:bg-primary-50 transition-colors">
          <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
            <i class="fa-solid fa-tags text-purple-600"></i>
          </div>
          <div>
            <h3 class="font-medium text-neutral-900">Categories</h3>
            <p class="text-sm text-neutral-600">Manage service types</p>
          </div>
        </a>

        <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="flex items-center p-4 border border-neutral-200 rounded-lg hover:border-primary-300 hover:bg-primary-50 transition-colors">
          <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
            <i class="fa-solid fa-cog text-orange-600"></i>
          </div>
          <div>
            <h3 class="font-medium text-neutral-900">Settings</h3>
            <p class="text-sm text-neutral-600">System configuration</p>
          </div>
        </a>

      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      
      <!-- Recent Users -->
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-neutral-900">Recent Users</h2>
          <a href="<?php echo BASE_URL; ?>/admin/users.php" class="text-primary-600 hover:text-primary-700 font-medium">View All</a>
        </div>
        
        <?php if (!empty($recentUsers)): ?>
        <div class="space-y-4">
          <?php foreach ($recentUsers as $user): ?>
          <div class="flex items-center justify-between p-3 border border-neutral-200 rounded-lg">
            <div>
              <h3 class="font-medium text-neutral-900">
                <?php echo e($user['first_name'] . ' ' . $user['last_name']); ?>
              </h3>
              <p class="text-sm text-neutral-600"><?php echo e($user['email']); ?></p>
              <p class="text-xs text-neutral-500">
                <?php echo ucfirst($user['role']); ?> • Joined <?php echo timeAgo($user['created_at']); ?>
              </p>
            </div>
            <div class="flex space-x-2">
              <a href="<?php echo BASE_URL; ?>/admin/edit-user.php?id=<?php echo $user['id']; ?>" 
                 class="text-primary-600 hover:text-primary-700">
                <i class="fa-solid fa-edit"></i>
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-neutral-500 text-center py-8">No users found.</p>
        <?php endif; ?>
      </div>

      <!-- Recent Providers -->
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-neutral-900">Recent Providers</h2>
          <a href="<?php echo BASE_URL; ?>/admin/providers.php" class="text-primary-600 hover:text-primary-700 font-medium">View All</a>
        </div>
        
        <?php if (!empty($recentProviders)): ?>
        <div class="space-y-4">
          <?php foreach ($recentProviders as $provider): ?>
          <div class="flex items-center justify-between p-3 border border-neutral-200 rounded-lg">
            <div>
              <h3 class="font-medium text-neutral-900">
                <?php echo e($provider['business_name'] ?: ($provider['first_name'] . ' ' . $provider['last_name'])); ?>
              </h3>
              <p class="text-sm text-neutral-600"><?php echo e($provider['category_name']); ?></p>
              <p class="text-xs text-neutral-500">
                <?php echo e($provider['location']); ?> • <?php echo formatCurrency($provider['hourly_rate']); ?>/hr
              </p>
            </div>
            <div class="flex items-center space-x-2">
              <?php if ($provider['is_verified']): ?>
                <span class="text-green-600"><i class="fa-solid fa-check-circle"></i></span>
              <?php else: ?>
                <span class="text-yellow-600"><i class="fa-solid fa-clock"></i></span>
              <?php endif; ?>
              <a href="<?php echo BASE_URL; ?>/provider-profile.php?id=<?php echo $provider['id']; ?>" 
                 class="text-primary-600 hover:text-primary-700">
                <i class="fa-solid fa-eye"></i>
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-neutral-500 text-center py-8">No providers found.</p>
        <?php endif; ?>
      </div>

    </div>

    <!-- System Info -->
    <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
      <h2 class="text-xl font-semibold text-neutral-900 mb-6">System Information</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <div>
          <h3 class="font-medium text-neutral-900 mb-2">Database</h3>
          <p class="text-sm text-neutral-600">MySQL Connected</p>
          <p class="text-xs text-neutral-500">
            PHP Version: <?php echo phpversion(); ?>
          </p>
        </div>

        <div>
          <h3 class="font-medium text-neutral-900 mb-2">Storage</h3>
          <p class="text-sm text-neutral-600">Uploads folder ready</p>
          <p class="text-xs text-neutral-500">
            Max file size: <?php echo ini_get('upload_max_filesize'); ?>
          </p>
        </div>

        <div>
          <h3 class="font-medium text-neutral-900 mb-2">Cache</h3>
          <p class="text-sm text-neutral-600">Session storage active</p>
          <p class="text-xs text-neutral-500">
            Session lifetime: <?php echo SESSION_LIFETIME; ?>s
          </p>
        </div>

      </div>
    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
