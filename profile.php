<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files - simplified approach that works with both systems
if (file_exists('config/config.php')) {
    require_once 'config/config.php';
    require_once 'config/database.php';
    require_once 'config/auth.php';
    require_once 'includes/functions.php';
} else {
    // Basic configuration fallback
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'servicelink');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('BASE_URL', 'http://localhost/Service_Delivery_Web');
    
    // Simple database connection function (only if config files don't exist)
    function getDB() {
        static $db = null;
        if ($db === null) {
            try {
                $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                if ($db->connect_error) {
                    throw new Exception("Connection failed: " . $db->connect_error);
                }
                $db->set_charset("utf8mb4");
            } catch (Exception $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return $db;
    }
}

// Simple redirect function (check if not already defined)
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

// Flash message functions (check if not already defined)
if (!function_exists('setFlashMessage')) {
    function setFlashMessage($message, $type = 'info') {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
}

if (!function_exists('getFlashMessage')) {
    function getFlashMessage($type = null) {
        if (!isset($_SESSION['flash_message'])) return null;
        if ($type && $_SESSION['flash_type'] !== $type) return null;
        
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $message;
    }
}

// Get current user (use auth system if available, otherwise simple check)
if (isset($auth) && is_object($auth)) {
    $currentUser = $auth->getCurrentUser();
} else {
    // Simple authentication check
    function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    $currentUser = getCurrentUser();
}

// Check if user is logged in
if (!$currentUser) {
    redirect(BASE_URL . '/login.php');
}

$successMessage = '';
$errorMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    
    // Handle profile update
    if (isset($_POST['update_profile'])) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($firstName) || empty($lastName) || empty($email)) {
            $errorMessage = 'First name, last name, and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address.';
        } else {
            // Check if email is already taken
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $currentUser['id']]);
            
            if ($stmt->fetch()) {
                $errorMessage = 'Email address is already in use.';
            } else {
                // Update profile
                $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
                
                if ($stmt->execute([$firstName, $lastName, $email, $phone, $currentUser['id']])) {
                    $successMessage = 'Profile updated successfully!';
                    $currentUser['first_name'] = $firstName;
                    $currentUser['last_name'] = $lastName;
                    $currentUser['email'] = $email;
                    $currentUser['phone'] = $phone;
                } else {
                    $errorMessage = 'Failed to update profile.';
                }
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMessage = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $errorMessage = 'New password must be at least 6 characters long.';
        } elseif (!password_verify($currentPassword, $currentUser['password_hash'])) {
            $errorMessage = 'Current password is incorrect.';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            
            if ($stmt->execute([$hashedPassword, $currentUser['id']])) {
                $successMessage = 'Password changed successfully!';
            } else {
                $errorMessage = 'Failed to change password.';
            }
        }
    }
    
    // Handle profile photo upload
    if (isset($_POST['upload_photo']) && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $userId = $currentUser['id'];
        $uploadDir = 'uploads/profiles/'; // Always use forward slashes for web paths
        $timestamp = time();
        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $filename = "user_{$userId}_{$timestamp}.{$ext}";
        $targetPath = $uploadDir . $filename; // Web-compatible path with forward slashes
        $fileSystemPath = str_replace('/', DIRECTORY_SEPARATOR, $targetPath); // Convert to file system path for operations
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($ext, $allowed)) {
            $errorMessage = 'Invalid image type. Please upload JPG, PNG, GIF or WEBP files only.';
        } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errorMessage = 'Image file is too large. Please upload files smaller than 5MB.';
        } else {
            // Ensure upload directory exists (use file system path)
            $uploadDirSystem = str_replace('/', DIRECTORY_SEPARATOR, $uploadDir);
            if (!is_dir($uploadDirSystem)) {
                if (!mkdir($uploadDirSystem, 0777, true)) {
                    $errorMessage = 'Failed to create upload directory.';
                } else {
                    // Create .htaccess for security if it doesn't exist
                    $htaccessPath = $uploadDirSystem . '.htaccess';
                    if (!file_exists($htaccessPath)) {
                        file_put_contents($htaccessPath, "Options -Indexes\nOptions -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n");
                    }
                    
                    // Remove old profile photo if exists
                    if (!empty($currentUser['profile_photo'])) {
                        $oldPhotoPath = str_replace('/', DIRECTORY_SEPARATOR, $currentUser['profile_photo']);
                        if (file_exists($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }
                    
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $fileSystemPath)) {
                        // Store web-compatible path (with forward slashes) in database
                        $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                        if ($stmt->execute([$targetPath, $userId])) {
                            $successMessage = 'Profile photo updated successfully!';
                            $currentUser['profile_photo'] = $targetPath;
                        } else {
                            $errorMessage = 'Failed to update profile photo in database.';
                        }
                    } else {
                        $errorMessage = 'Failed to upload image file.';
                    }
                }
            } else {
                // Directory already exists, proceed with upload
                // Remove old profile photo if exists
                if (!empty($currentUser['profile_photo'])) {
                    $oldPhotoPath = str_replace('/', DIRECTORY_SEPARATOR, $currentUser['profile_photo']);
                    if (file_exists($oldPhotoPath)) {
                        unlink($oldPhotoPath);
                    }
                }
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $fileSystemPath)) {
                    // Store web-compatible path (with forward slashes) in database
                    $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    if ($stmt->execute([$targetPath, $userId])) {
                        $successMessage = 'Profile photo updated successfully!';
                        $currentUser['profile_photo'] = $targetPath;
                    } else {
                        $errorMessage = 'Failed to update profile photo in database.';
                    }
                } else {
                    $errorMessage = 'Failed to upload image file.';
                }
            }
        }
    }
    
    // After handling all POST actions, redirect to prevent resubmission
    if ($successMessage || $errorMessage) {
        // Store messages in session and redirect to avoid resubmission
        $_SESSION['flash_message'] = $successMessage ?: $errorMessage;
        $_SESSION['flash_type'] = $successMessage ? 'success' : 'error';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
    }
}




$db = getDB();

// Get user statistics
$stats = ['requests' => 0, 'reviews' => 0, 'messages' => 0];

// Get user's wanted ads (requests)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM wanted_ads WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$result = $stmt->fetch();
$stats['requests'] = $result['count'];

// Get user's reviews (if they are a provider)
if ($currentUser['role'] === 'provider') {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reviews r 
                         JOIN providers p ON r.provider_id = p.id 
                         WHERE p.user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $result = $stmt->fetch();
    $stats['reviews'] = $result['count'];
}

// Get user's messages
$stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? OR sender_id = ?");
$stmt->execute([$currentUser['id'], $currentUser['id']]);
$result = $stmt->fetch();
$stats['messages'] = $result['count'];

// Get provider info if user is a provider
$providerInfo = null;
if ($currentUser['role'] === 'provider') {
    $stmt = $db->prepare("SELECT p.*, c.name as category_name 
                         FROM providers p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         WHERE p.user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $providerInfo = $stmt->fetch();
}

$pageTitle = 'My Profile - ServiceLink';
$pageDescription = 'Manage your profile information and settings.';

include 'includes/header.php';
?>

<!-- Main Content -->
<main class="py-8">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- Flash Messages -->
    <?php 
    $flashMessage = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
    $flashType = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'info';
    if ($flashMessage): 
        // Clear the flash message after displaying
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $flashType === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
        <div class="flex items-center">
            <i class="fas <?php echo $flashType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-3"></i>
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    </div>
    <?php endif; ?>
    
    
    <!-- Profile Header -->
    <div class="bg-white rounded-2xl shadow-xl border border-neutral-200 p-8 mb-8">
      <div class="flex flex-col lg:flex-row items-start lg:items-center space-y-6 lg:space-y-0 lg:space-x-8">
        
        <!-- Profile Photo Section -->
        <div class="relative flex-shrink-0">
          <div class="relative">
            <?php 
            // Enhanced profile photo display with debugging
            $profilePhotoPath = $currentUser['profile_photo'] ?? '';
            $hasValidPhoto = false;
            $photoUrl = '';
            
            if (!empty($profilePhotoPath)) {
                // Ensure the path uses forward slashes for web display
                $webPath = str_replace('\\', '/', $profilePhotoPath);
                // Check if file exists using file system path
                $fileSystemPath = str_replace('/', DIRECTORY_SEPARATOR, $profilePhotoPath);
                
                if (file_exists($fileSystemPath)) {
                    $hasValidPhoto = true;
                    $photoUrl = $webPath;
                }
            }
            ?>
            
            <?php if ($hasValidPhoto): ?>
            <!-- Profile Photo Display -->
            <div class="profile-photo-container relative">
                <img id="profilePhoto" 
                     src="<?php echo htmlspecialchars($photoUrl); ?>?v=<?php echo time(); ?>"
                     alt="<?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>"
                     class="w-32 h-32 lg:w-40 lg:h-40 rounded-full object-cover border-4 border-white shadow-2xl ring-4 ring-primary-100 transition-all duration-300 hover:scale-105" 
                     onload="this.style.opacity='1';"
                     onerror="handleImageError(this);"
                     style="opacity: 0;" />
                     
                <!-- Loading placeholder while image loads -->
                <div id="photoLoading" class="absolute inset-0 w-32 h-32 lg:w-40 lg:h-40 rounded-full bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center border-4 border-white shadow-2xl ring-4 ring-primary-100 animate-pulse">
                    <i class="fa-solid fa-spinner fa-spin text-2xl text-gray-400"></i>
                </div>
                
                <!-- Fallback avatar (hidden by default) -->
                <div id="avatarFallback" class="w-32 h-32 lg:w-40 lg:h-40 rounded-full bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center border-4 border-white shadow-2xl ring-4 ring-primary-100 hidden">
                    <div class="text-center">
                        <i class="fa-solid fa-user text-3xl lg:text-4xl text-primary-600 mb-1"></i>
                        <div class="text-xs text-primary-500 font-medium">
                            <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Default Avatar with Initials -->
            <div class="w-32 h-32 lg:w-40 lg:h-40 rounded-full bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center border-4 border-white shadow-2xl ring-4 ring-primary-100 hover:scale-105 transition-all duration-300">
                <div class="text-center">
                    <i class="fa-solid fa-user text-3xl lg:text-4xl text-primary-600 mb-1"></i>
                    <div class="text-xs text-primary-500 font-medium">
                        <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Edit Photo Button -->
            <button id="editPhotoBtn" class="absolute bottom-2 right-2 bg-primary-600 text-white p-3 rounded-full hover:bg-primary-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-110 z-10">
              <i class="fa-solid fa-camera text-sm"></i>
            </button>
            
            <!-- Debug Info (remove in production) -->
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <div class="absolute top-0 left-0 bg-black bg-opacity-75 text-white text-xs p-2 rounded max-w-xs">
                <div>DB Path: <?php echo htmlspecialchars($profilePhotoPath); ?></div>
                <div>Web Path: <?php echo htmlspecialchars($photoUrl); ?></div>
                <div>File Exists: <?php echo $hasValidPhoto ? 'Yes' : 'No'; ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Profile Info -->
        <div class="flex-1 min-w-0">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
            <div>
              <h1 id="profileName" class="text-3xl lg:text-4xl font-bold text-neutral-900 mb-2">
                <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
              </h1>
              <div class="flex items-center space-x-4 text-neutral-600 mb-4">
                <span id="profileLocation" class="flex items-center space-x-2">
                  <i class="fa-solid fa-envelope text-primary-500"></i>
                  <span><?php echo htmlspecialchars($currentUser['email']); ?></span>
                </span>
                <span id="profileJoined" class="flex items-center space-x-2">
                  <i class="fa-solid fa-calendar text-primary-500"></i>
                  <span>Joined <?php echo date('M Y', strtotime($currentUser['created_at'])); ?></span>
                </span>
              </div>
              <div class="flex items-center space-x-4 mt-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 text-primary-800">
                    <i class="fas fa-user-tag mr-1"></i>
                    <?php echo htmlspecialchars(ucfirst($currentUser['role'])); ?>
                </span>
                <?php if ($currentUser['email_verified']): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                        <i class="fas fa-check-circle mr-1"></i>Verified
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                        <i class="fas fa-exclamation-triangle mr-1"></i>Unverified
                    </span>
                <?php endif; ?>
              </div>
            </div>
            <button id="editProfileBtn" class="bg-primary-600 text-white px-6 py-3 rounded-lg hover:bg-primary-700 transition-colors font-medium flex items-center space-x-2 shadow-lg">
              <i class="fa-solid fa-edit"></i>
              <span>Edit Profile</span>
            </button>
          </div>

          <!-- Profile Stats -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-gradient-to-r from-blue-50 to-primary-50 p-4 rounded-xl border border-blue-100">
              <div class="text-2xl font-bold text-primary-700" id="totalServices">
                <?php
                // Get provider stats if user is a provider
                $totalServices = 0;
                if ($currentUser['role'] === 'provider') {
                  try {
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM providers WHERE user_id = ?");
                    $stmt->bind_param("i", $currentUser['id']);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $totalServices = $result['count'];
                  } catch (Exception $e) {}
                }
                echo $totalServices;
                ?>
              </div>
              <div class="text-sm text-primary-600">Services Posted</div>
            </div>
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-xl border border-green-100">
              <div class="text-2xl font-bold text-emerald-700" id="totalRequests">
                <?php echo $stats['requests']; ?>
              </div>
              <div class="text-sm text-emerald-600">Requests Made</div>
            </div>
            <div class="bg-gradient-to-r from-amber-50 to-yellow-50 p-4 rounded-xl border border-amber-100">
              <div class="text-2xl font-bold text-amber-700" id="averageRating">
                <?php
                // Get average rating if provider
                $averageRating = '0.0';
                if ($currentUser['role'] === 'provider') {
                  try {
                    $stmt = $db->prepare("SELECT AVG(rating) as avg_rating FROM reviews r JOIN providers p ON r.provider_id = p.id WHERE p.user_id = ?");
                    $stmt->bind_param("i", $currentUser['id']);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    if ($result['avg_rating']) {
                      $averageRating = number_format($result['avg_rating'], 1);
                    }
                  } catch (Exception $e) {}
                }
                echo $averageRating;
                ?>
              </div>
              <div class="text-sm text-amber-600">Average Rating</div>
            </div>
            <div class="bg-gradient-to-r from-purple-50 to-violet-50 p-4 rounded-xl border border-purple-100">
              <div class="text-2xl font-bold text-purple-700" id="completedJobs">
                <?php echo ucfirst($currentUser['role']); ?>
              </div>
              <div class="text-sm text-purple-600">Account Type</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white rounded-2xl shadow-lg border border-neutral-200 mb-8">
      <div class="border-b border-neutral-200">
        <nav class="flex space-x-8 px-6" aria-label="Tabs">
          <button class="tab-btn border-b-2 border-primary-600 text-primary-600 py-4 px-1 text-sm font-medium" data-tab="overview">
            <i class="fa-solid fa-chart-line mr-2"></i>Overview
          </button>
          <?php if ($currentUser['role'] === 'provider'): ?>
          <button class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-1 text-sm font-medium transition-colors" data-tab="services">
            <i class="fa-solid fa-briefcase mr-2"></i>My Services
          </button>
          <?php endif; ?>
          <button class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-1 text-sm font-medium transition-colors" data-tab="requests">
            <i class="fa-solid fa-clipboard-list mr-2"></i>My Requests
          </button>
          <button class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-1 text-sm font-medium transition-colors" data-tab="reviews">
            <i class="fa-solid fa-star mr-2"></i>Reviews
          </button>
          <button class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-1 text-sm font-medium transition-colors" data-tab="settings">
            <i class="fa-solid fa-cog mr-2"></i>Settings
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <div class="p-6">
                <!-- Overview Tab -->
                <div id="overview-tab" class="tab-content">
                    <h3 class="text-lg font-semibold mb-4">Overview</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-medium text-blue-900 mb-2">Profile Information</h4>
                            <p class="text-blue-700">Name: <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></p>
                            <p class="text-blue-700">Email: <?php echo htmlspecialchars($currentUser['email']); ?></p>
                            <p class="text-blue-700">Phone: <?php echo htmlspecialchars($currentUser['phone'] ?? 'Not provided'); ?></p>
                            <p class="text-blue-700">Role: <?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'user')); ?></p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-medium text-green-900 mb-2">Account Status</h4>
                            <p class="text-green-700">✅ Account Active</p>
                            <p class="text-green-700">📅 Joined: <?php echo date('M j, Y', strtotime($currentUser['created_at'])); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Services Tab -->
                <?php if ($currentUser['role'] === 'provider'): ?>
                <div id="services-tab" class="tab-content hidden">
                    <h3 class="text-lg font-semibold mb-4">My Services</h3>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <p class="text-yellow-700">🚧 Services management coming soon...</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Requests Tab -->
                <div id="requests-tab" class="tab-content hidden">
                    <h3 class="text-lg font-semibold mb-4">My Requests</h3>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <p class="text-purple-700">📋 No requests found. Your service requests will appear here.</p>
                    </div>
                </div>

                <!-- Reviews Tab -->
                <div id="reviews-tab" class="tab-content hidden">
                    <h3 class="text-lg font-semibold mb-4">Reviews</h3>
                    <div class="bg-orange-50 p-4 rounded-lg">
                        <p class="text-orange-700">⭐ No reviews yet. Reviews from customers will appear here.</p>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div id="settings-tab" class="tab-content hidden">
                    <h3 class="text-lg font-semibold mb-6">Account Settings</h3>
                    
                    <div class="space-y-6">
                        <!-- Quick Actions -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <button onclick="openModal('profileEditModal')" 
                                    class="flex items-center justify-center p-6 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors group">
                                <div class="text-center">
                                    <i class="fas fa-user-edit text-3xl text-blue-600 mb-3 group-hover:scale-110 transition-transform"></i>
                                    <h4 class="font-medium text-blue-900">Edit Profile</h4>
                                    <p class="text-sm text-blue-600 mt-1">Update your personal information</p>
                                </div>
                            </button>
                            
                            <button onclick="openModal('imageUploadModal')" 
                                    class="flex items-center justify-center p-6 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors group">
                                <div class="text-center">
                                    <i class="fas fa-camera text-3xl text-green-600 mb-3 group-hover:scale-110 transition-transform"></i>
                                    <h4 class="font-medium text-green-900">Change Photo</h4>
                                    <p class="text-sm text-green-600 mt-1">Upload a new profile picture</p>
                                </div>
                            </button>
                            
                            <button onclick="openModal('passwordChangeModal')" 
                                    class="flex items-center justify-center p-6 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors group">
                                <div class="text-center">
                                    <i class="fas fa-lock text-3xl text-red-600 mb-3 group-hover:scale-110 transition-transform"></i>
                                    <h4 class="font-medium text-red-900">Change Password</h4>
                                    <p class="text-sm text-red-600 mt-1">Update your account password</p>
                                </div>
                            </button>
                        </div>
                        
                        <!-- Account Information Display -->
                        <div class="bg-gray-50 p-6 rounded-lg">
                            <h4 class="font-medium text-gray-900 mb-4">Current Account Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Name:</span>
                                    <span class="ml-2 font-medium"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Email:</span>
                                    <span class="ml-2 font-medium"><?php echo htmlspecialchars($currentUser['email']); ?></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Phone:</span>
                                    <span class="ml-2 font-medium"><?php echo htmlspecialchars($currentUser['phone'] ?? 'Not provided'); ?></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Role:</span>
                                    <span class="ml-2 font-medium"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'user')); ?></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Member since:</span>
                                    <span class="ml-2 font-medium"><?php echo date('M j, Y', strtotime($currentUser['created_at'])); ?></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Email Status:</span>
                                    <span class="ml-2">
                                        <?php if ($currentUser['email_verified']): ?>
                                            <span class="text-green-600 font-medium">✓ Verified</span>
                                        <?php else: ?>
                                            <span class="text-orange-600 font-medium">⚠ Unverified</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Overlays -->
    <style>
        /* Modal animations and enhancements */
        .modal-overlay {
            backdrop-filter: blur(4px);
            animation: fadeIn 0.2s ease-out;
        }
        
        .modal-content {
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* Profile photo enhancements */
        .profile-photo-container {
            position: relative;
        }
        
        .profile-photo-container img {
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
        }
        
        .profile-photo-container:hover img {
            transform: scale(1.05);
        }
        
        /* Loading animation */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Drag and drop zone enhancements */
        .drop-zone-active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(147, 51, 234, 0.1));
            border-color: #3b82f6;
            transform: scale(1.02);
        }
        
        /* Custom file input styling */
        .custom-file-input::-webkit-file-upload-button {
            visibility: hidden;
        }
        
        .custom-file-input::before {
            content: 'Choose File';
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            outline: none;
            white-space: nowrap;
            cursor: pointer;
            color: white;
            font-weight: 500;
            font-size: 14px;
        }
        
        .custom-file-input:hover::before {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        
        /* Enhanced button styles */
        .edit-photo-btn {
            backdrop-filter: blur(10px);
            background: rgba(99, 102, 241, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .edit-photo-btn:hover {
            background: rgba(79, 70, 229, 0.95);
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }
    </style>
    
    <!-- Image Upload Modal -->
    <div id="imageUploadModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-overlay">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4 modal-content">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Upload Profile Photo</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal('imageUploadModal')">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="modalPhotoUploadForm">
                <div class="mb-4">
                    <div id="dropZone" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary-500 transition-colors cursor-pointer">
                        <div id="dropZoneContent">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-600 mb-2">Drag and drop your image here</p>
                            <p class="text-sm text-gray-500 mb-4">or click to browse</p>
                            <button type="button" class="bg-primary-600 text-white px-4 py-2 rounded-md hover:bg-primary-700 transition-colors">
                                Choose File
                            </button>
                        </div>
                        <div id="imagePreview" class="hidden">
                            <img id="previewImg" src="" alt="Preview" class="w-32 h-32 object-cover rounded-full mx-auto mb-4">
                            <p id="fileName" class="text-sm text-gray-600 mb-2"></p>
                            <button type="button" onclick="clearImagePreview()" class="text-red-500 hover:text-red-700 text-sm">
                                <i class="fas fa-trash mr-1"></i>Remove
                            </button>
                        </div>
                    </div>
                    <input type="file" id="modalProfilePhoto" name="profile_photo" accept="image/*" class="hidden">
                </div>
                
                <p class="text-xs text-gray-500 mb-4">
                    Supported formats: JPG, PNG, GIF, WEBP. Maximum size: 5MB
                </p>
                
                <div class="flex space-x-3">
                    <button type="button" onclick="closeModal('imageUploadModal')" 
                            class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" name="upload_photo" 
                            class="flex-1 bg-primary-600 text-white px-4 py-2 rounded-md hover:bg-primary-700 transition-colors">
                        Upload Photo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Password Change Modal -->
    <div id="passwordChangeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-overlay">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4 modal-content">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Change Password</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal('passwordChangeModal')">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" id="modalPasswordChangeForm">
                <div class="space-y-4">
                    <div>
                        <label for="modalCurrentPassword" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <div class="relative">
                            <input type="password" id="modalCurrentPassword" name="current_password" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 pr-10"
                                   required>
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('modalCurrentPassword')">
                                <i class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label for="modalNewPassword" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <div class="relative">
                            <input type="password" id="modalNewPassword" name="new_password" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 pr-10"
                                   minlength="6" required>
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('modalNewPassword')">
                                <i class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                    </div>
                    
                    <div>
                        <label for="modalConfirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" id="modalConfirmPassword" name="confirm_password" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 pr-10"
                                   minlength="6" required>
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('modalConfirmPassword')">
                                <i class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="flex space-x-3 mt-6">
                    <button type="button" onclick="closeModal('passwordChangeModal')" 
                            class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" name="change_password" 
                            class="flex-1 bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition-colors">
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Profile Edit Modal -->
    <div id="profileEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-overlay">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-4 modal-content">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Edit Profile</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal('profileEditModal')">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" id="modalProfileEditForm">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="modalFirstName" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" id="modalFirstName" name="first_name" 
                                   value="<?php echo htmlspecialchars($currentUser['first_name']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   required>
                        </div>
                        <div>
                            <label for="modalLastName" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" id="modalLastName" name="last_name" 
                                   value="<?php echo htmlspecialchars($currentUser['last_name']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   required>
                        </div>
                    </div>
                    
                    <div>
                        <label for="modalEmail" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="modalEmail" name="email" 
                               value="<?php echo htmlspecialchars($currentUser['email']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               required>
                    </div>
                    
                    <div>
                        <label for="modalPhone" class="block text-sm font-medium text-gray-700 mb-1">Phone (optional)</label>
                        <input type="tel" id="modalPhone" name="phone" 
                               value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               placeholder="+1 (555) 123-4567">
                    </div>
                </div>
                
                <div class="flex space-x-3 mt-6">
                    <button type="button" onclick="closeModal('profileEditModal')" 
                            class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" name="update_profile" 
                            class="flex-1 bg-primary-600 text-white px-4 py-2 rounded-md hover:bg-primary-700 transition-colors">
                        Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        console.log('🔥 Profile JavaScript with Modals loading...');

        // Image Error Handling
        function handleImageError(img) {
            console.log('❌ Image failed to load:', img.src);
            
            // Hide the image and loading indicator
            img.style.display = 'none';
            const loadingDiv = document.getElementById('photoLoading');
            if (loadingDiv) {
                loadingDiv.style.display = 'none';
            }
            
            // Show the fallback avatar
            const fallback = document.getElementById('avatarFallback');
            if (fallback) {
                fallback.classList.remove('hidden');
                fallback.classList.add('flex');
            }
        }

        // Image Load Success Handler
        function handleImageLoad(img) {
            console.log('✅ Image loaded successfully:', img.src);
            
            // Hide loading indicator
            const loadingDiv = document.getElementById('photoLoading');
            if (loadingDiv) {
                loadingDiv.style.display = 'none';
            }
            
            // Make image visible with fade-in effect
            img.style.opacity = '1';
        }

        // Setup image loading handlers
        function setupImageHandlers() {
            const profileImg = document.getElementById('profilePhoto');
            if (profileImg) {
                console.log('🖼️ Profile image src:', profileImg.src);
                
                profileImg.addEventListener('load', function() {
                    handleImageLoad(this);
                });
                
                profileImg.addEventListener('error', function() {
                    handleImageError(this);
                });
                
                // Check if image is already loaded (cached)
                if (profileImg.complete && profileImg.naturalHeight !== 0) {
                    handleImageLoad(profileImg);
                } else if (profileImg.complete) {
                    // Image failed to load
                    handleImageError(profileImg);
                }
            } else {
                console.log('📷 No profile image found - using default avatar');
            }
        }

        // Modal Functions
        function openModal(modalId) {
            console.log('🔓 Opening modal:', modalId);
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
                
                // Focus on first input if available
                const firstInput = modal.querySelector('input:not([type="hidden"])');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 100);
                }
            }
        }

        function closeModal(modalId) {
            console.log('🔒 Closing modal:', modalId);
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto'; // Restore scrolling
                
                // Clear form if it's the image upload modal
                if (modalId === 'imageUploadModal') {
                    clearImagePreview();
                }
            }
        }

        // Close modal when clicking outside
        function setupModalCloseOnOutsideClick() {
            ['imageUploadModal', 'passwordChangeModal', 'profileEditModal'].forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            closeModal(modalId);
                        }
                    });
                }
            });
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                ['imageUploadModal', 'passwordChangeModal', 'profileEditModal'].forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (modal && !modal.classList.contains('hidden')) {
                        closeModal(modalId);
                    }
                });
            }
        });

        // Password visibility toggle
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Image Upload Functions
        function setupImageUpload() {
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('modalProfilePhoto');
            const dropZoneContent = document.getElementById('dropZoneContent');
            const imagePreview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            const fileName = document.getElementById('fileName');

            // Click to select file
            dropZone.addEventListener('click', function(e) {
                if (e.target.tagName !== 'BUTTON') return;
                fileInput.click();
            });

            // File input change
            fileInput.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    handleFileSelection(e.target.files[0]);
                }
            });

            // Drag and drop
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                dropZone.classList.add('border-primary-500', 'bg-primary-50');
            });

            dropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                dropZone.classList.remove('border-primary-500', 'bg-primary-50');
            });

            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                dropZone.classList.remove('border-primary-500', 'bg-primary-50');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFileSelection(files[0]);
                }
            });

            function handleFileSelection(file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, PNG, GIF, WEBP)');
                    return;
                }

                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    fileName.textContent = file.name;
                    dropZoneContent.classList.add('hidden');
                    imagePreview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        }

        function clearImagePreview() {
            const fileInput = document.getElementById('modalProfilePhoto');
            const dropZoneContent = document.getElementById('dropZoneContent');
            const imagePreview = document.getElementById('imagePreview');
            
            fileInput.value = '';
            dropZoneContent.classList.remove('hidden');
            imagePreview.classList.add('hidden');
        }

        // Tab functionality
        function switchTab(targetTabId) {
            console.log('🎯 Switching to tab:', targetTabId);
            
            // Get all tab buttons and contents
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            // Reset all buttons
            tabButtons.forEach(btn => {
                btn.classList.remove('border-primary-600', 'text-primary-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Hide all contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Activate target button
            const targetButton = document.querySelector(`[data-tab="${targetTabId}"]`);
            if (targetButton) {
                targetButton.classList.remove('border-transparent', 'text-gray-500');
                targetButton.classList.add('border-primary-600', 'text-primary-600');
                console.log('✅ Activated button for:', targetTabId);
            }
            
            // Show target content
            const targetContent = document.getElementById(targetTabId + '-tab');
            if (targetContent) {
                targetContent.classList.remove('hidden');
                console.log('✅ Showed content for:', targetTabId);
            } else {
                console.error('❌ Content not found for:', targetTabId + '-tab');
            }
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM loaded, setting up profile with modals...');
            
            // Set up image handling
            setupImageHandlers();
            
            // Set up modals
            setupModalCloseOnOutsideClick();
            setupImageUpload();
            
            // Set up tab buttons
            const tabButtons = document.querySelectorAll('.tab-btn');
            console.log('📊 Found', tabButtons.length, 'tab buttons');
            
            tabButtons.forEach((button, index) => {
                const tabId = button.getAttribute('data-tab');
                console.log(`🔧 Setting up button ${index + 1}: "${tabId}"`);
                
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('🖱️ Tab clicked:', tabId);
                    switchTab(tabId);
                });
            });
            
            // Edit profile button - opens modal instead of switching tab
            const editProfileBtn = document.getElementById('editProfileBtn');
            if (editProfileBtn) {
                editProfileBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('🖱️ Edit profile clicked - opening modal');
                    openModal('profileEditModal');
                });
                console.log('✅ Edit profile button set up');
            }
            
            // Edit photo button - opens modal instead of switching tab
            const editPhotoBtn = document.getElementById('editPhotoBtn');
            if (editPhotoBtn) {
                editPhotoBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('🖱️ Edit photo clicked - opening modal');
                    openModal('imageUploadModal');
                });
                console.log('✅ Edit photo button set up');
            }

            // Add password change button functionality (if you want to add a button for it)
            // You can add this button anywhere in your UI
            window.openPasswordModal = function() {
                openModal('passwordChangeModal');
            };
            
            // Global functions for testing
            window.switchToTab = switchTab;
            window.openModal = openModal;
            window.closeModal = closeModal;
            window.testProfile = function() {
                console.log('=== PROFILE TEST ===');
                const buttons = document.querySelectorAll('.tab-btn');
                const contents = document.querySelectorAll('.tab-content');
                
                console.log('Buttons:', buttons.length);
                buttons.forEach((btn, i) => {
                    const tab = btn.getAttribute('data-tab');
                    const active = btn.classList.contains('border-primary-600');
                    console.log(`  ${i+1}. ${tab}: ${active ? 'ACTIVE' : 'inactive'}`);
                });
                
                console.log('Contents:', contents.length);
                contents.forEach((content, i) => {
                    const hidden = content.classList.contains('hidden');
                    console.log(`  ${i+1}. ${content.id}: ${hidden ? 'HIDDEN' : 'VISIBLE'}`);
                });
            };
            
            console.log('✅ Profile with modals setup complete!');
        });

        console.log('📝 Profile JavaScript with Modals loaded');
    </script>

<?php include 'includes/footer.php'; ?>
