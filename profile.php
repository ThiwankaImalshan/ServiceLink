    <?php
    // Define DEBUG_MODE if not already defined
    if (!defined('DEBUG_MODE')) {
        define('DEBUG_MODE', false); // Set to true for debugging
    }
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
        function getDB()
        {
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
        function redirect($url)
        {
            header("Location: $url");
            exit();
        }
    }

    // Flash message functions (check if not already defined)
    if (!function_exists('setFlashMessage')) {
        function setFlashMessage($message, $type = 'info')
        {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
    }

    if (!function_exists('getFlashMessage')) {
        function getFlashMessage($type = null)
        {
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
        function getCurrentUser()
        {
            if (!isset($_SESSION['user_id'])) {
                return null;
            }

            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            if (!$stmt) {
                error_log("Error preparing statement: " . $db->error);
                return null;
            }
            $stmt->bind_param("i", $_SESSION['user_id']);
            if (!$stmt->execute()) {
                error_log("Error executing statement: " . $stmt->error);
                return null;
            }
            $result = $stmt->get_result();
            if (!$result) {
                error_log("Error getting result: " . $stmt->error);
                return null;
            }
            return $result->fetch_assoc();
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
                    $gender = trim($_POST['gender'] ?? '');
                    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, gender = ? WHERE id = ?");
                    if ($stmt->execute([$firstName, $lastName, $email, $phone, $gender, $currentUser['id']])) {
                        $successMessage = 'Profile updated successfully!';
                        $currentUser['first_name'] = $firstName;
                        $currentUser['last_name'] = $lastName;
                        $currentUser['email'] = $email;
                        $currentUser['phone'] = $phone;
                        $currentUser['gender'] = $gender;
                    } else {
                        $errorMessage = 'Failed to update profile.';
                    }
                }
            }
        }

        // Handle provider active status toggle
        if (isset($_POST['toggle_provider_active']) && $currentUser['role'] === 'provider') {
            $providerId = (int)($_POST['provider_id'] ?? 0);
            $newStatus = ($_POST['is_active'] ?? '0') === '1' ? 1 : 0;

            // Verify the provider belongs to the current user before updating
            $stmt = $db->prepare("SELECT id FROM providers WHERE id = ? AND user_id = ?");
            $stmt->execute([$providerId, $currentUser['id']]);
            $providerExists = $stmt->fetch();

            if ($providerExists) {
                $stmt = $db->prepare("UPDATE providers SET is_active = ? WHERE id = ? AND user_id = ?");
                if ($stmt->execute([$newStatus, $providerId, $currentUser['id']])) {
                    $successMessage = $newStatus ? 'Provider service activated successfully.' : 'Provider service deactivated successfully.';
                } else {
                    $errorMessage = 'Failed to update provider status.';
                }
            } else {
                $errorMessage = 'Provider not found or access denied.';
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

            // Use separate web and filesystem paths
            $uploadDirWeb = 'uploads/profiles/';
            $uploadDirSystem = rtrim(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadDirWeb), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            $timestamp = time();
            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed, true)) {
                $errorMessage = 'Invalid image type. Please upload JPG, PNG, GIF or WEBP files only.';
            } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
                $errorMessage = 'Image file is too large. Please upload files smaller than 5MB.';
            } else {
                // Ensure upload directory exists
                if (!is_dir($uploadDirSystem) && !mkdir($uploadDirSystem, 0755, true)) {
                    $errorMessage = 'Failed to create upload directory.';
                } else {
                    // Ensure safe .htaccess (no "Options", no handlers)
                    $htaccessPath = $uploadDirSystem . '.htaccess';
                    if (file_exists($htaccessPath)) {
                        $content = @file_get_contents($htaccessPath) ?: '';
                        if (preg_match('/\bOptions\b/i', $content) || stripos($content, 'AddHandler') !== false || stripos($content, 'SetHandler') !== false) {
                            @unlink($htaccessPath);
                        }
                    }
                    if (!file_exists($htaccessPath)) {
                        $safeHtaccess =
                            "<IfModule mod_authz_core.c>
                                <FilesMatch \"\\.(php|phar|phtml|pl|py|jsp|asp|sh|cgi)$\">
                                    Require all denied
                                </FilesMatch>
                                </IfModule>
                                <IfModule mod_access_compat.c>
                                <FilesMatch \"\\.(php|phar|phtml|pl|py|jsp|asp|sh|cgi)$\">
                                    Order allow,deny
                                    Deny from all
                                </FilesMatch>
                                </IfModule>
                                ";
                        @file_put_contents($htaccessPath, $safeHtaccess);
                    }

                    // Save new file
                    $filename = "user_{$userId}_{$timestamp}.{$ext}";
                    $fileSystemPath = $uploadDirSystem . $filename;
                    $targetPathWeb = $uploadDirWeb . $filename;

                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $fileSystemPath)) {
                        @chmod($fileSystemPath, 0644);

                        // Store web path in DB
                        $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                        if ($stmt->execute([$targetPathWeb, $userId])) {
                            $successMessage = 'Profile photo updated successfully!';
                            $currentUser['profile_photo'] = $targetPathWeb;
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

    // Get user's favorites count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM favorite_providers WHERE customer_id = ?");
    $stmt->execute([$currentUser['id']]);
    $result = $stmt->fetch();
    $stats['favorites'] = $result['count'];

    // Get provider info if user is a provider
    $providerInfo = null;
    $allProviders = [];
    if ($currentUser['role'] === 'provider') {
        // Get all provider records for this user
        $stmt = $db->prepare("SELECT p.*, c.name as category_name, c.icon as category_icon
                                        FROM providers p 
                                        LEFT JOIN categories c ON p.category_id = c.id 
                                        WHERE p.user_id = ?
                                        ORDER BY p.created_at DESC");
        $stmt->execute([$currentUser['id']]);
        $allProviders = $stmt->fetchAll();

        // Keep the first one as $providerInfo for backward compatibility
        if (!empty($allProviders)) {
            $providerInfo = $allProviders[0];
        }
    }

    $pageTitle = 'My Profile - ServiceLink';
    $pageDescription = 'Manage your profile information and settings.';

    include 'includes/header.php';
    ?>
    <?php
    // Ensure uploads/profiles has a safe .htaccess (no "Options", no handlers)
    $uploadDirWeb = 'uploads/profiles/';
    $uploadDirSystem = rtrim(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadDirWeb), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (is_dir($uploadDirSystem)) {
        $htaccessPath = $uploadDirSystem . '.htaccess';

        // Remove legacy/bad rules that can cause 500
        if (file_exists($htaccessPath)) {
            $content = @file_get_contents($htaccessPath) ?: '';
            if (preg_match('/\bOptions\b/i', $content) || stripos($content, 'AddHandler') !== false || stripos($content, 'SetHandler') !== false) {
                @unlink($htaccessPath);
            }
        }

        // Write a minimal safe .htaccess (deny script execution only)
        if (!file_exists($htaccessPath)) {
            $safeHtaccess =
                "<IfModule mod_authz_core.c>
                <FilesMatch \"\\.(php|phar|phtml|pl|py|jsp|asp|sh|cgi)$\">
                    Require all denied
                </FilesMatch>
                </IfModule>
                <IfModule mod_access_compat.c>
                <FilesMatch \"\\.(php|phar|phtml|pl|py|jsp|asp|sh|cgi)$\">
                    Order allow,deny
                    Deny from all
                </FilesMatch>
                </IfModule>
                ";
            @file_put_contents($htaccessPath, $safeHtaccess);
        }
    }
    ?>
    <div
        class="min-h-screen bg-gradient-to-br from-neutral-50 to-neutral-100 dark:from-neutral-900 dark:to-neutral-800 text-neutral-900 dark:text-neutral-100 transition-colors duration-300 page-content">
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
                <div class="bg-white rounded-2xl shadow-xl border border-neutral-200 p-4 sm:p-6 lg:p-8 mb-8">
                    <div class="flex flex-col md:flex-row items-center md:items-start space-y-6 md:space-y-0 md:space-x-8">

                        <!-- Profile Photo Section -->
                        <div class="relative flex-shrink-0 w-full md:w-auto flex justify-center md:block">
                            <div class="relative w-fit mx-auto md:mx-0">
                                <?php
                                // Build photo URL via serve-upload.php (avoid direct file access)
                                $profilePhotoPath = $currentUser['profile_photo'] ?? '';
                                $photoUrl = '';
                                $hasValidPhoto = false;

                                if (!empty($profilePhotoPath)) {
                                    $relPath = ltrim(str_replace('\\', '/', $profilePhotoPath), '/');
                                    // Use relative URL to avoid BASE_URL mismatches
                                    $photoUrl = 'serve-upload.php?p=' . rawurlencode($relPath);
                                    $hasValidPhoto = true;
                                }
                                ?>

                                <?php if ($hasValidPhoto): ?>
                                    <!-- Profile Photo Display -->
                                    <div class="profile-photo-container relative mx-auto">
                                        <img id="profilePhoto"
                                            src="<?php echo htmlspecialchars($photoUrl); ?>"
                                            alt="<?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>"
                                            class="w-32 h-32 sm:w-36 sm:h-36 lg:w-40 lg:h-40 rounded-full object-cover border-4 border-white shadow-2xl ring-4 ring-primary-100 transition-all duration-300 hover:scale-105"
                                            style="opacity: 0;" />

                                        <!-- Loading placeholder while image loads -->
                                        <div id="photoLoading" class="absolute inset-0 w-32 h-32 sm:w-36 sm:h-36 lg:w-40 lg:h-40 rounded-full bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center border-4 border-white shadow-2xl ring-4 ring-primary-100 animate-pulse">
                                            <i class="fa-solid fa-spinner fa-spin text-2xl text-gray-400"></i>
                                        </div>

                                        <!-- Fallback avatar (hidden by default) -->
                                        <div id="avatarFallback" class="w-32 h-32 sm:w-36 sm:h-36 lg:w-40 lg:h-40 rounded-full bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center border-4 border-white shadow-2xl ring-4 ring-primary-100 hidden">
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
                                    <div class="w-32 h-32 sm:w-36 sm:h-36 lg:w-40 lg:h-40 rounded-full bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center border-4 border-white shadow-2xl ring-4 ring-primary-100 hover:scale-105 transition-all duration-300 mx-auto">
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
                            </div>
                        </div>

                        <!-- Profile Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-col md:block w-full space-y-6 md:space-y-0 mb-4">
                                <div class="flex flex-col items-center md:items-start w-full md:max-w-none text-center md:text-left">
                                    <h1 id="profileName" class="text-2xl sm:text-3xl lg:text-4xl font-bold text-neutral-900 mb-4">
                                        <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                                    </h1>
                                    <div class="flex flex-col md:flex-row items-center md:items-start space-y-2 md:space-y-0 md:space-x-4 text-neutral-600 text-sm sm:text-base mb-4">
                                        <span id="profileGender" class="flex items-center space-x-2 px-2 py-1 md:px-0 md:py-0">
                                            <i class="fa-solid fa-venus-mars text-primary-500"></i>
                                            <span class="truncate max-w-[160px] sm:max-w-xs">
                                                <?php
                                                $genderMap = [
                                                    'male' => 'Male',
                                                    'female' => 'Female',
                                                    'other' => 'Other',
                                                    '' => 'Not specified',
                                                    null => 'Not specified'
                                                ];
                                                $genderKey = isset($currentUser['gender']) ? strtolower($currentUser['gender']) : '';
                                                echo htmlspecialchars($genderMap[$genderKey] ?? 'Not specified');
                                                ?>
                                            </span>
                                        </span>
                                        <span id="profileLocation" class="flex items-center space-x-2 px-2 py-1 md:px-0 md:py-0">
                                            <i class="fa-solid fa-envelope text-primary-500"></i>
                                            <span class="truncate max-w-[160px] sm:max-w-xs"><?php echo htmlspecialchars($currentUser['email']); ?></span>
                                        </span>
                                        <span id="profileJoined" class="flex items-center space-x-2 px-2 py-1 md:px-0 md:py-0">
                                            <i class="fa-solid fa-calendar text-primary-500"></i>
                                            <span>Joined <?php echo date('M Y', strtotime($currentUser['created_at'])); ?></span>
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap justify-center md:justify-start gap-3 mb-6 md:mb-4">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 text-primary-800">
                                            <i class="fas fa-user-tag mr-1"></i>
                                            <?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'unknown')); ?>
                                        </span>
                                        <?php if ($currentUser['email_verified']): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Verified
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                Unverified
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($currentUser['role'] === 'provider' && $providerInfo): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $providerInfo['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                <div class="w-2 h-2 rounded-full mr-2 <?php echo $providerInfo['is_active'] ? 'bg-green-500' : 'bg-gray-400'; ?> animate-pulse"></div>
                                                <?php echo $providerInfo['is_active'] ? 'Online' : 'Offline'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <button id="editProfileBtn" class="bg-primary-600 text-white px-6 py-3 rounded-lg hover:bg-primary-700 transition-colors font-medium flex items-center justify-center md:justify-start space-x-2 shadow-lg w-full max-w-xs md:w-auto">
                                        <i class="fa-solid fa-edit"></i>
                                        <span>Edit Profile</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Profile Stats -->
                            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 w-full">
                                <div class="bg-gradient-to-r from-blue-50 to-primary-50 p-4 rounded-xl border border-blue-100 hover:shadow-lg transition-shadow duration-300">
                                    <div class="text-center">
                                        <div class="text-2xl sm:text-3xl font-bold text-primary-700 mb-1" id="totalServices">
                                            <?php
                                            if ($currentUser['role'] === 'user') {
                                                echo $stats['favorites'];
                                            } else {
                                                // Get provider stats if user is a provider
                                                $totalServices = 0;
                                                try {
                                                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM providers WHERE user_id = ?");
                                                    $stmt->execute([$currentUser['id']]);
                                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    $totalServices = $result['count'];
                                                } catch (Exception $e) {
                                                }
                                                echo $totalServices;
                                            }
                                            ?>
                                        </div>
                                        <div class="text-sm text-primary-600 font-medium">
                                            <?php echo ($currentUser['role'] === 'user') ? 'Favorites' : 'Services Posted'; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-xl border border-green-100 hover:shadow-lg transition-shadow duration-300">
                                    <div class="text-center">
                                        <div class="text-2xl sm:text-3xl font-bold text-emerald-700 mb-1" id="totalRequests">
                                            <?php echo $stats['requests']; ?>
                                        </div>
                                        <div class="text-sm text-emerald-600 font-medium">Requests Made</div>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-r from-amber-50 to-yellow-50 p-4 rounded-xl border border-amber-100 hover:shadow-lg transition-shadow duration-300">
                                    <div class="text-center">
                                        <div class="text-2xl sm:text-3xl font-bold text-amber-700 mb-1" id="averageRating">
                                            <?php
                                            if ($currentUser['role'] === 'user') {
                                                // Get count of reviews added by the user
                                                try {
                                                    $stmt = $db->prepare("SELECT COUNT(*) as review_count FROM reviews WHERE user_id = ?");
                                                    $stmt->execute([$currentUser['id']]);
                                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    echo $result['review_count'] ?? '0';
                                                } catch (Exception $e) {
                                                    error_log("Error getting review count: " . $e->getMessage());
                                                    echo '0';
                                                }
                                            } else {
                                                // Get average rating if provider
                                                $averageRating = '0.0';
                                                try {
                                                    $stmt = $db->prepare("SELECT AVG(rating) as avg_rating FROM reviews r JOIN providers p ON r.provider_id = p.id WHERE p.user_id = ?");
                                                    $stmt->execute([$currentUser['id']]);
                                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    if ($result['avg_rating']) {
                                                        $averageRating = number_format($result['avg_rating'], 1);
                                                    }
                                                } catch (Exception $e) {
                                                }
                                                echo $averageRating;
                                            }
                                            ?>
                                        </div>
                                        <div class="text-sm text-amber-600 font-medium">
                                            <?php echo ($currentUser['role'] === 'user') ? 'Reviews added' : 'Average Rating'; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-r from-purple-50 to-violet-50 p-4 rounded-xl border border-purple-100 hover:shadow-lg transition-shadow duration-300">
                                    <div class="text-center">
                                        <div class="text-2xl sm:text-3xl font-bold text-purple-700 mb-1" id="completedJobs">
                                            <?php echo ucfirst($currentUser['role'] ?? 'unknown'); ?>
                                        </div>
                                        <div class="text-sm text-purple-600 font-medium">Account Type</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Provider Services Management Section -->
                <?php if ($currentUser['role'] === 'provider'): ?>
                    <div class="bg-white rounded-2xl shadow-lg border border-neutral-200 mb-8">
                        <div class="border-b border-neutral-200 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i class="fas fa-briefcase text-primary-600 mr-3"></i>
                                        My Provider Services
                                    </h2>
                                    <p class="text-sm text-gray-600 mt-1">Manage the availability of your service listings</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-gray-500">Total Services</div>
                                    <div class="text-2xl font-bold text-primary-600"><?php echo count($allProviders); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <?php if (!empty($allProviders)): ?>
                                <div class="grid gap-4">
                                    <?php foreach ($allProviders as $provider): ?>
                                        <div class="bg-gradient-to-r from-gray-50 to-slate-50 border border-gray-200 rounded-xl p-4 hover:shadow-md transition-all duration-300">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <div class="flex items-center mb-2">
                                                        <div class="w-12 h-12 bg-gradient-to-br from-primary-100 to-primary-200 rounded-lg flex items-center justify-center mr-4">
                                                            <i class="<?php echo htmlspecialchars($provider['category_icon'] ?? 'fas fa-briefcase'); ?> text-primary-600 text-lg"></i>
                                                        </div>
                                                        <div>
                                                            <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($provider['business_name'] ?? 'Unnamed Service'); ?></h3>
                                                            <p class="text-sm text-gray-600">
                                                                <i class="fas fa-tag mr-1"></i>
                                                                <?php echo htmlspecialchars($provider['category_name'] ?? 'No Category'); ?>
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($provider['description'])): ?>
                                                        <p class="text-sm text-gray-700 mb-3 line-clamp-2">
                                                            <?php echo htmlspecialchars(substr($provider['description'], 0, 120) . (strlen($provider['description']) > 120 ? '...' : '')); ?>
                                                        </p>
                                                    <?php endif; ?>

                                                    <div class="flex items-center space-x-4 text-xs text-gray-500">
                                                        <span>
                                                            <i class="fas fa-calendar-alt mr-1"></i>
                                                            Created <?php echo date('M j, Y', strtotime($provider['created_at'])); ?>
                                                        </span>
                                                        <?php if ($provider['rating'] > 0): ?>
                                                            <span>
                                                                <i class="fas fa-star text-yellow-500 mr-1"></i>
                                                                <?php echo number_format($provider['rating'], 1); ?> (<?php echo $provider['review_count'] ?? 0; ?> reviews)
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="ml-6">
                                                    <form method="POST" class="provider-toggle-form" data-provider-id="<?php echo $provider['id']; ?>">
                                                        <input type="hidden" name="toggle_provider_active" value="1">
                                                        <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">

                                                        <div class="text-center mb-3">
                                                            <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $provider['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'; ?>">
                                                                <div class="w-2 h-2 rounded-full mr-2 <?php echo $provider['is_active'] ? 'bg-green-500 animate-pulse' : 'bg-gray-400'; ?>"></div>
                                                                <?php echo $provider['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </div>
                                                        </div>

                                                        <label class="flex flex-col items-center cursor-pointer group">
                                                            <input type="checkbox" name="is_active" value="1"
                                                                <?php echo $provider['is_active'] ? 'checked' : ''; ?>
                                                                onchange="toggleProviderService(this, <?php echo $provider['id']; ?>)"
                                                                class="sr-only peer">
                                                            <div class="relative">
                                                                <!-- Enhanced Toggle Track -->
                                                                <div class="w-16 h-8 rounded-full shadow-inner peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-200 transition-all duration-300 group-hover:shadow-lg transform group-hover:scale-105
                                                            <?php echo $provider['is_active']
                                                                ? 'bg-gradient-to-r from-green-400 to-green-500'
                                                                : 'bg-gradient-to-r from-red-400 to-red-500'; ?>">
                                                                </div>
                                                                <!-- Enhanced Toggle Handle -->
                                                                <div class="absolute top-1 left-1 w-6 h-6 bg-white rounded-full shadow-lg border border-gray-200 flex items-center justify-center transition-all duration-300"
                                                                    style="transform: <?php echo $provider['is_active'] ? 'translateX(32px)' : 'translateX(0)'; ?>;">
                                                                    <div class="w-3 h-3 rounded-full transition-all duration-300 <?php echo $provider['is_active'] ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                                                                </div>
                                                            </div>
                                                            <div class="mt-2 text-center">
                                                                <div class="text-xs font-medium <?php echo $provider['is_active'] ? 'text-green-700' : 'text-red-600'; ?> transition-colors duration-300">
                                                                    <?php echo $provider['is_active'] ? 'ONLINE' : 'OFFLINE'; ?>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <script>
                                    function toggleProviderService(checkbox, providerId) {
                                        // Add loading state
                                        const form = checkbox.closest('form');
                                        const label = checkbox.closest('label');
                                        const card = checkbox.closest('.bg-gradient-to-r');

                                        // Visual feedback
                                        label.style.pointerEvents = 'none';
                                        label.style.opacity = '0.7';
                                        card.style.transform = 'scale(0.98)';

                                        // Submit form after brief delay for better UX
                                        setTimeout(() => {
                                            form.submit();
                                        }, 200);
                                    }
                                </script>

                            <?php else: ?>
                                <div class="text-center py-12">
                                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-briefcase text-gray-400 text-2xl"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Provider Services</h3>
                                    <p class="text-gray-600 mb-6 max-w-md mx-auto">
                                        You haven't created any provider service listings yet. Start by adding your first service to begin receiving customer inquiries.
                                    </p>
                                    <a href="my-service.php" class="inline-flex items-center px-6 py-3 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 transition-colors shadow-lg">
                                        <i class="fas fa-plus mr-2"></i>
                                        Create Your First Service
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="bg-white rounded-2xl shadow-lg border border-neutral-200 mb-8">
                    <div class="border-b border-neutral-200 overflow-x-auto scrollbar-hide">
                        <div class="max-w-7xl mx-auto">
                            <nav class="flex flex-nowrap justify-start md:justify-center min-w-full px-2 sm:px-4" aria-label="Tabs">
                                <button type="button" class="tab-btn border-b-2 border-primary-600 text-primary-600 py-4 px-6 sm:px-8 text-sm whitespace-nowrap font-medium flex-shrink-0 flex flex-col sm:flex-row items-center" data-tab="overview">
                                    <i class="fa-solid fa-chart-line text-lg sm:text-base mb-1 sm:mb-0 sm:mr-2"></i>
                                    <span class="text-xs sm:text-sm">Overview</span>
                                </button>
                                <?php if ($currentUser['role'] === 'provider'): ?>
                                    <button type="button" class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-6 sm:px-8 text-sm whitespace-nowrap font-medium transition-colors flex-shrink-0 flex flex-col sm:flex-row items-center" data-tab="services">
                                        <i class="fa-solid fa-briefcase text-lg sm:text-base mb-1 sm:mb-0 sm:mr-2"></i>
                                        <span class="text-xs sm:text-sm">Services</span>
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-6 sm:px-8 text-sm whitespace-nowrap font-medium transition-colors flex-shrink-0 flex flex-col sm:flex-row items-center" data-tab="requests">
                                    <i class="fa-solid fa-clipboard-list text-lg sm:text-base mb-1 sm:mb-0 sm:mr-2"></i>
                                    <span class="text-xs sm:text-sm">Requests</span>
                                </button>
                                <button type="button" class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-6 sm:px-8 text-sm whitespace-nowrap font-medium transition-colors flex-shrink-0 flex flex-col sm:flex-row items-center" data-tab="reviewsAdded">
                                    <i class="fa-solid fa-star text-lg sm:text-base mb-1 sm:mb-0 sm:mr-2"></i>
                                    <span class="text-xs sm:text-sm">Reviews</span>
                                </button>
                                <button type="button" class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-6 sm:px-8 text-sm whitespace-nowrap font-medium transition-colors flex-shrink-0 flex flex-col sm:flex-row items-center" data-tab="favorites">
                                    <i class="fa-solid fa-heart text-lg sm:text-base mb-1 sm:mb-0 sm:mr-2"></i>
                                    <span class="text-xs sm:text-sm">Favorites</span>
                                </button>
                                <button type="button" class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-6 sm:px-8 text-sm whitespace-nowrap font-medium transition-colors flex-shrink-0 flex flex-col sm:flex-row items-center" data-tab="settings">
                                    <i class="fa-solid fa-cog text-lg sm:text-base mb-1 sm:mb-0 sm:mr-2"></i>
                                    <span class="text-xs sm:text-sm">Settings</span>
                                </button>
                            </nav>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-6">
                        <!-- Overview Tab -->
                        <div id="overview-tab" class="tab-content">
                            <h3 class="text-lg font-semibold mb-4">Recent Activities</h3>
                            <div class="space-y-6">
                                <?php
                                // Get recent activities (service requests, bookings, reviews, etc.)
                                $activities = [];

                                // Get recent service requests
                                $stmt = $db->prepare("SELECT 'request' as type, w.title, w.created_at, w.status 
                                            FROM wanted_ads w 
                                            WHERE w.user_id = ? 
                                            ORDER BY w.created_at DESC LIMIT 5");
                                $stmt->execute([$currentUser['id']]);
                                $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));

                                // Get recent reviews (if provider)
                                if ($currentUser['role'] === 'provider') {
                                    $stmt = $db->prepare("SELECT 'review' as type, r.rating, r.comment, r.created_at, 
                                                    u.first_name, u.last_name 
                                                    FROM reviews r 
                                                    JOIN providers p ON r.provider_id = p.id 
                                                    JOIN users u ON r.user_id = u.id 
                                                    WHERE p.user_id = ? 
                                                    ORDER BY r.created_at DESC LIMIT 5");
                                    $stmt->execute([$currentUser['id']]);
                                    $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
                                }

                                // Get recent messages
                                $stmt = $db->prepare("SELECT 'message' as type, m.message, m.created_at, 
                                                CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as direction,
                                                u.first_name, u.last_name
                                                FROM messages m 
                                                JOIN users u ON (m.sender_id = ? AND m.recipient_id = u.id) 
                                                        OR (m.recipient_id = ? AND m.sender_id = u.id)
                                                ORDER BY m.created_at DESC LIMIT 5");
                                $stmt->execute([$currentUser['id'], $currentUser['id'], $currentUser['id']]);
                                $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));

                                // Sort all activities by date
                                usort($activities, function ($a, $b) {
                                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                                });

                                if (!empty($activities)):
                                    foreach ($activities as $activity): ?>
                                        <div class="bg-white border border-neutral-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                                            <div class="p-4">
                                                <?php switch ($activity['type']):
                                                    case 'request': ?>
                                                        <div class="flex items-start">
                                                            <div class="flex-shrink-0">
                                                                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                                    <i class="fas fa-clipboard-list text-blue-600"></i>
                                                                </div>
                                                            </div>
                                                            <div class="ml-4">
                                                                <p class="text-gray-900">
                                                                    Posted a service request: <span class="font-medium"><?php echo htmlspecialchars($activity['title']); ?></span>
                                                                </p>
                                                                <p class="text-sm text-gray-500 mt-1">
                                                                    Status: <span class="inline-block px-2 py-1 text-xs font-medium rounded-full
                                                                <?php echo $activity['status'] === 'open' ? 'bg-green-100 text-green-800' : ($activity['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                                                                    'bg-gray-100 text-gray-800'); ?>">
                                                                        <?php echo ucfirst($activity['status']); ?>
                                                                    </span>
                                                                </p>
                                                                <p class="text-xs text-gray-400 mt-1">
                                                                    <i class="far fa-clock mr-1"></i>
                                                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    case 'review': ?>
                                                        <div class="flex items-start">
                                                            <div class="flex-shrink-0">
                                                                <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                                                                    <i class="fas fa-star text-yellow-600"></i>
                                                                </div>
                                                            </div>
                                                            <div class="ml-4">
                                                                <p class="text-gray-900">
                                                                    Received a <?php echo $activity['rating']; ?> star review from
                                                                    <span class="font-medium">
                                                                        <?php echo htmlspecialchars($activity['first_name'] . ' ' . substr($activity['last_name'], 0, 1) . '.'); ?>
                                                                    </span>
                                                                </p>
                                                                <p class="text-sm text-gray-600 mt-1">
                                                                    "<?php echo htmlspecialchars($activity['comment']); ?>"
                                                                </p>
                                                                <p class="text-xs text-gray-400 mt-1">
                                                                    <i class="far fa-clock mr-1"></i>
                                                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    case 'message': ?>
                                                        <div class="flex items-start">
                                                            <div class="flex-shrink-0">
                                                                <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                                                                    <i class="fas <?php echo $activity['direction'] === 'sent' ? 'fa-paper-plane' : 'fa-envelope'; ?> text-purple-600"></i>
                                                                </div>
                                                            </div>
                                                            <div class="ml-4">
                                                                <p class="text-gray-900">
                                                                    <?php if ($activity['direction'] === 'sent'): ?>
                                                                        Sent a message to
                                                                    <?php else: ?>
                                                                        Received a message from
                                                                    <?php endif; ?>
                                                                    <span class="font-medium">
                                                                        <?php echo htmlspecialchars($activity['first_name'] . ' ' . substr($activity['last_name'], 0, 1) . '.'); ?>
                                                                    </span>
                                                                </p>
                                                                <p class="text-sm text-gray-600 mt-1">
                                                                    "<?php echo htmlspecialchars(substr($activity['message'], 0, 100) . (strlen($activity['message']) > 100 ? '...' : '')); ?>"
                                                                </p>
                                                                <p class="text-xs text-gray-400 mt-1">
                                                                    <i class="far fa-clock mr-1"></i>
                                                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                <?php break;
                                                endswitch; ?>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                else: ?>
                                    <div class="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                                        <div class="text-gray-400 mb-3">
                                            <i class="fas fa-history text-4xl"></i>
                                        </div>
                                        <h4 class="text-gray-900 font-medium mb-1">No Recent Activity</h4>
                                        <p class="text-gray-600 text-sm">Your recent activities will appear here.</p>
                                    </div>
                                <?php endif; ?>
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
                            <div class="mb-6 flex items-center justify-between">
                                <h3 class="text-lg font-semibold">My Requests</h3>
                                <a href="wanted.php" class="inline-flex items-center justify-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
                                    <i class="fas fa-plus mr-2"></i>
                                    New Request
                                </a>
                            </div>
                            <?php
                            // Get user's wanted ads
                            $stmt = $db->prepare("SELECT w.*, c.name as category_name 
                                            FROM wanted_ads w 
                                            LEFT JOIN categories c ON w.category_id = c.id 
                                            WHERE w.user_id = ? 
                                            ORDER BY w.created_at DESC");
                            $stmt->execute([$currentUser['id']]);
                            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!empty($requests)): ?>
                                <div class="space-y-4">
                                    <?php foreach ($requests as $request): ?>
                                        <div class="bg-white rounded-xl border border-neutral-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                                            <div class="p-6">
                                                <div class="flex items-start justify-between">
                                                    <div>
                                                        <h4 class="font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($request['title']); ?></h4>
                                                        <p class="text-sm text-gray-600 mb-2">
                                                            <i class="fas fa-folder text-primary-500 mr-1"></i>
                                                            <?php echo htmlspecialchars($request['category_name']); ?>
                                                        </p>
                                                    </div>
                                                    <span class="px-3 py-1 text-xs font-medium rounded-full 
                                        <?php echo $request['status'] === 'open' ? 'bg-green-100 text-green-800' : ($request['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                                            'bg-gray-100 text-gray-800'); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                                    </span>
                                                </div>
                                                <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                                                    <?php echo htmlspecialchars($request['description']); ?>
                                                </p>
                                                <div class="flex items-center justify-between text-sm">
                                                    <span class="text-gray-500">
                                                        <i class="fas fa-clock text-gray-400 mr-1"></i>
                                                        <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                                    </span>
                                                    <?php if (isset($request['budget_min']) || isset($request['budget_max'])): ?>
                                                        <span class="font-medium text-primary-600">
                                                            <i class="fas fa-tag mr-1"></i>
                                                            <?php
                                                            if (isset($request['budget_min']) && isset($request['budget_max'])) {
                                                                echo 'Rs.' . number_format($request['budget_min'], 2) . ' - Rs.' . number_format($request['budget_max'], 2);
                                                            } elseif (isset($request['budget_min'])) {
                                                                echo 'From Rs.' . number_format($request['budget_min'], 2);
                                                            } elseif (isset($request['budget_max'])) {
                                                                echo 'Up to Rs.' . number_format($request['budget_max'], 2);
                                                            }
                                                            ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="bg-gray-50 px-6 py-3 border-t border-neutral-200">
                                                <div class="flex justify-between items-center">
                                                    <div class="flex items-center gap-4">
                                                        <a href="wanted.php?id=<?php echo $request['id']; ?>" class="text-primary-600 hover:text-primary-700 font-medium text-sm">
                                                            View Details
                                                            <i class="fas fa-arrow-right ml-1"></i>
                                                        </a>
                                                        <button class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center" onclick="openEditWantedModal(<?php echo htmlspecialchars(json_encode($request), ENT_QUOTES, 'UTF-8'); ?>)">
                                                            <i class="fas fa-edit mr-1"></i>
                                                            Edit
                                                        </button>
                                                    </div>
                                                    <?php if ($request['status'] === 'open'): ?>
                                                        <button class="text-red-600 hover:text-red-700 text-sm font-medium"
                                                            onclick="deleteRequest(<?php echo $request['id']; ?>)">
                                                            <i class="fas fa-trash-alt mr-1"></i>
                                                            Delete
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="bg-gray-50 border border-neutral-200 rounded-xl p-8 text-center">
                                    <div class="text-gray-400 mb-4">
                                        <i class="fas fa-clipboard-list text-4xl"></i>
                                    </div>
                                    <h4 class="text-gray-900 font-medium mb-2">No Requests Yet</h4>
                                    <p class="text-gray-600 mb-6">You haven't posted any service requests yet.</p>
                                    <a href="wanted.php" class="inline-flex items-center justify-center px-4 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 transition-colors">
                                        <i class="fas fa-plus mr-2"></i>
                                        Post a Request
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Reviews Tab -->
                        <div id="reviewsAdded-tab" class="tab-content hidden">
                            <div class="mb-6 flex items-center justify-between">
                                <h3 class="text-lg font-semibold">Reviews</h3>
                                <a href="services.php" class="inline-flex items-center justify-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
                                    <i class="fas fa-plus mr-2"></i>
                                    Add Review
                                </a>
                            </div>
                            <?php if ($currentUser['role'] === 'user'):
                                // Get reviews written by the user
                                try {
                                    $stmt = $db->prepare("
                                    SELECT r.*, p.business_name, u.first_name, u.last_name, u.profile_photo,
                                        c.name as category_name, c.icon as category_icon
                                    FROM reviews r
                                    JOIN providers p ON r.provider_id = p.id
                                    JOIN users u ON p.user_id = u.id
                                    JOIN categories c ON p.category_id = c.id
                                    WHERE r.user_id = ?
                                    ORDER BY r.created_at DESC
                                ");
                                    $stmt->execute([$currentUser['id']]);
                                    $reviews = $stmt->fetchAll();

                                    if (empty($reviews)): ?>
                                        <div class="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                                            <div class="text-gray-400 mb-3">
                                                <i class="fas fa-star text-3xl"></i>
                                            </div>
                                            <h4 class="text-gray-900 font-medium mb-1">No Reviews Yet</h4>
                                            <p class="text-gray-600 text-sm">You haven't written any reviews yet.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="space-y-4">
                                            <?php foreach ($reviews as $review): ?>
                                                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-all">
                                                    <div class="flex items-start">
                                                        <?php
                                                        $photoPath = str_replace('\\', '/', $review['profile_photo']);
                                                        if (empty($photoPath)) {
                                                            $imgSrc = BASE_URL . '/assets/img/default-avatar.png';
                                                        } else if (filter_var($photoPath, FILTER_VALIDATE_URL)) {
                                                            $imgSrc = $photoPath;
                                                        } else {
                                                            $imgSrc = BASE_URL . '/serve-upload.php?p=' . rawurlencode(ltrim($photoPath, '/'));
                                                        }
                                                        ?>
                                                        <img src="<?php echo $imgSrc; ?>"
                                                            alt="<?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>"
                                                            class="w-12 h-12 rounded-full object-cover" />
                                                        <div class="ml-4 flex-1">
                                                            <div class="flex items-center justify-between">
                                                                <h4 class="font-semibold text-gray-900">
                                                                    <?php echo htmlspecialchars($review['business_name'] ?: ($review['first_name'] . ' ' . $review['last_name'])); ?>
                                                                </h4>
                                                                <span class="text-sm text-gray-500">
                                                                    <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center mt-1">
                                                                <div class="flex text-yellow-400">
                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                        <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : ' text-gray-300'; ?>"></i>
                                                                    <?php endfor; ?>
                                                                </div>
                                                                <span class="ml-2 text-sm text-gray-600"><?php echo $review['category_name']; ?></span>
                                                            </div>
                                                            <?php if (!empty($review['comment'])): ?>
                                                                <p class="mt-2 text-gray-600"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                <?php endif;
                                } catch (Exception $e) {
                                    echo '<div class="text-red-500">Error loading reviews.</div>';
                                }
                            else: ?>
                                <div class="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                                    <div class="text-gray-400 mb-3">
                                        <i class="fas fa-star text-3xl"></i>
                                    </div>
                                    <h4 class="text-gray-900 font-medium mb-1">Provider Reviews</h4>
                                    <p class="text-gray-600 text-sm">Reviews from customers will appear here.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Favorites Tab -->
                        <div id="favorites-tab" class="tab-content hidden">
                            <h3 class="text-lg font-semibold mb-6">Favorite Service Providers</h3>

                            <?php if (($currentUser['role'] ?? '') !== 'user'): ?>
                                <div class="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                                    <div class="text-gray-400 mb-3"><i class="fas fa-heart text-3xl"></i></div>
                                    <h4 class="text-gray-900 font-medium mb-1">Favorites are available for customer accounts</h4>
                                    <p class="text-gray-600 text-sm">Switch to a customer account to see favorites.</p>
                                </div>
                            <?php else: ?>
                                <?php
                                // Helper to run a prepared query for PDO or MySQLi
                                $runQuery = function ($db, $sql, $params) {
                                    if ($db instanceof PDO) {
                                        $stmt = $db->prepare($sql);
                                        $stmt->execute($params);
                                        return $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    } else { // MySQLi
                                        $stmt = $db->prepare($sql);
                                        if (!$stmt) return [];
                                        // All params here are ints
                                        $types = str_repeat('i', count($params));
                                        $stmt->bind_param($types, ...$params);
                                        if (!$stmt->execute()) return [];
                                        $res = $stmt->get_result();
                                        if (!$res) return [];
                                        return $res->fetch_all(MYSQLI_ASSOC);
                                    }
                                };

                                // Base SELECT (note: LEFT JOIN categories to avoid filtering)
                                $baseSelect = "
                            SELECT
                                p.id AS provider_id,
                                p.user_id,
                                p.category_id,
                                p.business_name,
                                p.description,
                                p.profile_photo AS provider_profile_photo,
                                p.rating,
                                p.review_count,
                                u.first_name, u.last_name, u.profile_photo AS user_profile_photo,
                                c.name AS category_name, c.icon AS category_icon,
                                fp.created_at AS favorited_at,
                                p.created_at
                            FROM favorite_providers fp
                            JOIN providers p ON %s
                            LEFT JOIN users u ON u.id = p.user_id
                            LEFT JOIN categories c ON c.id = p.category_id
                            WHERE fp.customer_id = ?
                            ORDER BY fp.created_at DESC
                            ";

                                // Try the correct mapping first: fp.provider_id = providers.id
                                $favorites = $runQuery($db, sprintf($baseSelect, 'p.id = fp.provider_id'), [(int)$currentUser['id']]);

                                // Fallback if your data was stored with providers.user_id instead
                                if (empty($favorites)) {
                                    $favorites = $runQuery($db, sprintf($baseSelect, 'p.user_id = fp.provider_id'), [(int)$currentUser['id']]);
                                }
                                ?>

                                <?php if (empty($favorites)): ?>
                                    <div class="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                                        <div class="text-gray-400 mb-3"><i class="fas fa-heart text-3xl"></i></div>
                                        <h4 class="text-gray-900 font-medium mb-1">No Favorites Yet</h4>
                                        <p class="text-gray-600 text-sm">You haven't added any service providers to your favorites.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($favorites as $provider): ?>
                                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-all">
                                                <div class="flex items-start">
                                                    <?php
                                                    // Prefer provider's photo; fallback to provider user’s photo; else default
                                                    $photoPath = $provider['provider_profile_photo'] ?: ($provider['user_profile_photo'] ?? '');
                                                    if (!$photoPath) {
                                                        $imgSrc = 'assets/img/default-avatar.svg';
                                                    } elseif (preg_match('~^https?://~i', $photoPath)) {
                                                        $imgSrc = $photoPath;
                                                    } else {
                                                        $normalized = ltrim(str_replace('\\', '/', $photoPath), '/');
                                                        $imgSrc = 'serve-upload.php?p=' . rawurlencode($normalized);
                                                    }
                                                    ?>
                                                    <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                                                        alt="Provider" class="w-12 h-12 rounded-full object-cover">

                                                    <div class="ml-4 flex-1">
                                                        <div class="flex items-center justify-between">
                                                            <h4 class="font-semibold text-gray-900">
                                                                <?php echo htmlspecialchars($provider['business_name'] ?: (($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''))); ?>
                                                            </h4>
                                                            <span class="text-sm text-gray-500">
                                                                <?php echo !empty($provider['created_at']) ? date('M j, Y', strtotime($provider['created_at'])) : ''; ?>
                                                            </span>
                                                        </div>

                                                        <div class="flex items-center mt-1">
                                                            <i class="<?php echo htmlspecialchars($provider['category_icon'] ?? 'fas fa-tags'); ?> text-primary-500"></i>
                                                            <span class="ml-2 text-sm text-gray-600">
                                                                <?php echo htmlspecialchars($provider['category_name'] ?? 'Category'); ?>
                                                            </span>
                                                        </div>

                                                        <?php if (!empty($provider['description'])): ?>
                                                            <p class="mt-2 text-gray-600">
                                                                <?php
                                                                $desc = (string)$provider['description'];
                                                                $short = mb_substr($desc, 0, 150);
                                                                echo nl2br(htmlspecialchars($short . (mb_strlen($desc) > 150 ? '...' : '')));
                                                                ?>
                                                            </p>
                                                        <?php endif; ?>

                                                        <div class="flex items-center justify-between mt-2">
                                                            <div class="flex items-center">
                                                                <?php if ((float)$provider['rating'] > 0): ?>
                                                                    <div class="flex text-yellow-400 text-sm">
                                                                        <?php
                                                                        $stars = (int)floor($provider['rating']);
                                                                        for ($i = 1; $i <= 5; $i++):
                                                                        ?>
                                                                            <i class="fas fa-star<?php echo $i <= $stars ? '' : ' text-gray-300'; ?>"></i>
                                                                        <?php endfor; ?>
                                                                    </div>
                                                                    <span class="ml-2 text-sm text-gray-500">(<?php echo (int)($provider['review_count'] ?? 0); ?>)</span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <a href="provider-profile.php?id=<?php echo (int)$provider['provider_id']; ?>"
                                                                class="inline-flex items-center text-sm text-primary-600 hover:text-primary-700">
                                                                View Profile
                                                                <i class="fas fa-arrow-right ml-1"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <!-- Settings Tab -->
                        <div id="settings-tab" class="tab-content hidden">
                            <div class="mb-6 flex items-center justify-between">
                                <h3 class="text-lg font-semibold">Account Settings</h3>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm text-gray-600">Status:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $currentUser['email_verified'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo $currentUser['email_verified'] ? 'Verified' : 'Unverified'; ?>
                                    </span>
                                </div>
                            </div>

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
                                            <span class="text-gray-600 align-middle">Name:</span>
                                            <span
                                                class="ml-2 font-medium align-middle"
                                                style="max-width: 160px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; line-height: 1;"
                                                id="profileNameInfo">
                                                <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600 align-middle">Email:</span>
                                            <span
                                                class="ml-2 font-medium align-middle"
                                                style="max-width: 160px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; line-height: 1;"
                                                id="profileEmailInfo">
                                                <?php echo htmlspecialchars($currentUser['email']); ?>
                                            </span>
                                        </div>
                                        <script>
                                            // Edit Wanted Ad Modal Logic
                                            function openEditWantedModal(request) {
                                                const modal = document.getElementById('editWantedModal');
                                                if (!modal) return;
                                                modal.classList.remove('hidden');
                                                document.body.style.overflow = 'hidden';
                                                // Fill form fields
                                                document.getElementById('editWantedId').value = request.id;
                                                document.getElementById('editWantedTitle').value = request.title;
                                                document.getElementById('editWantedDescription').value = request.description;
                                                document.getElementById('editWantedBudgetMin').value = request.budget_min ?? '';
                                                document.getElementById('editWantedBudgetMax').value = request.budget_max ?? '';
                                            }

                                            function updateProfileInfoClamp() {
                                                var w = window.innerWidth;
                                                var nameSpan = document.getElementById('profileNameInfo');
                                                var emailSpan = document.getElementById('profileEmailInfo');
                                                if (nameSpan && emailSpan) {
                                                    if (w > 500) {
                                                        nameSpan.style.maxWidth = 'none';
                                                        nameSpan.style.whiteSpace = 'normal';
                                                        nameSpan.style.overflow = 'visible';
                                                        nameSpan.style.textOverflow = 'clip';
                                                        emailSpan.style.maxWidth = 'none';
                                                        emailSpan.style.whiteSpace = 'normal';
                                                        emailSpan.style.overflow = 'visible';
                                                        emailSpan.style.textOverflow = 'clip';
                                                    } else {
                                                        nameSpan.style.maxWidth = '160px';
                                                        nameSpan.style.whiteSpace = 'nowrap';
                                                        nameSpan.style.overflow = 'hidden';
                                                        nameSpan.style.textOverflow = 'ellipsis';
                                                        emailSpan.style.maxWidth = '160px';
                                                        emailSpan.style.whiteSpace = 'nowrap';
                                                        emailSpan.style.overflow = 'hidden';
                                                        emailSpan.style.textOverflow = 'ellipsis';
                                                    }
                                                }
                                            }
                                            window.addEventListener('resize', updateProfileInfoClamp);
                                            document.addEventListener('DOMContentLoaded', updateProfileInfoClamp);
                                        </script>
                                        <div>
                                            <span class="text-gray-600">Phone:</span>
                                            <span class="ml-2 font-medium"><?php echo htmlspecialchars($currentUser['phone'] ?? 'Not provided'); ?></span>
                                        </div>
                                        <!-- <div>
                                    <span class="text-gray-600">Role:</span>
                                    <span class="ml-2 font-medium"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'user')); ?></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Member since:</span>
                                    <span class="ml-2 font-medium"><?php echo date('M j, Y', strtotime($currentUser['created_at'])); ?></span>
                                </div> -->
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
            </div>
    </div>
    <!-- End of Profile Tabs Container -->

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

    <!-- Edit Wanted Ad Modal -->
    <div id="editWantedModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-overlay">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-4 modal-content">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Edit Request</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal('editWantedModal')">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" id="editWantedForm">
                <input type="hidden" name="edit_wanted_id" id="editWantedId">
                <div class="space-y-4">
                    <div>
                        <label for="editWantedTitle" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" id="editWantedTitle" name="title" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                    </div>
                    <div>
                        <label for="editWantedDescription" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="editWantedDescription" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" required></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="editWantedBudgetMin" class="block text-sm font-medium text-gray-700 mb-1">Budget Min</label>
                            <input type="number" id="editWantedBudgetMin" name="budget_min" class="w-full px-3 py-2 border border-gray-300 rounded-md" step="0.01">
                        </div>
                        <div>
                            <label for="editWantedBudgetMax" class="block text-sm font-medium text-gray-700 mb-1">Budget Max</label>
                            <input type="number" id="editWantedBudgetMax" name="budget_max" class="w-full px-3 py-2 border border-gray-300 rounded-md" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3 mt-6">
                    <button type="button" onclick="closeModal('editWantedModal')" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition-colors">Cancel</button>
                    <button type="submit" name="edit_wanted" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">Save Changes</button>
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
                        <div>
                            <label for="modalGender" class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                            <select id="modalGender" name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="" disabled selected>Select gender</option>
                                <option value="male" <?php echo (isset($currentUser['gender']) && $currentUser['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo (isset($currentUser['gender']) && $currentUser['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo (isset($currentUser['gender']) && $currentUser['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
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
        console.log('Profile JavaScript with Modals loading...');

        // Global Image Error Handling Functions (accessible from HTML)
        function handleImageError(img) {
            console.log('Image failed to load:', img.src);

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
            console.log('Image loaded successfully:', img.src);

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
                console.log('Profile image src:', profileImg.src);

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
                console.log('No profile image found - using default avatar');
            }
        }

        // Modal Functions (Global)
        function openModal(modalId) {
            console.log('Opening modal:', modalId);
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
            console.log('Closing modal:', modalId);
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

        // Password visibility toggle (Global)
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

            if (!dropZone || !fileInput) return;

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

            if (fileInput) fileInput.value = '';
            if (dropZoneContent) dropZoneContent.classList.remove('hidden');
            if (imagePreview) imagePreview.classList.add('hidden');
        }

        // Tab functionality
        function switchTab(targetTabId) {
            console.log('Switching to tab:', targetTabId);

            // Get all tab buttons and contents
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            // Reset all buttons
            tabButtons.forEach(btn => {
                btn.classList.remove('border-primary-600', 'text-primary-600');
                btn.classList.add('border-transparent', 'text-neutral-500');
            });

            // Hide all contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });

            // Activate target button
            const targetButton = document.querySelector(`[data-tab="${targetTabId}"]`);
            if (targetButton) {
                targetButton.classList.remove('border-transparent', 'text-neutral-500');
                targetButton.classList.add('border-primary-600', 'text-primary-600');
                console.log('Activated button for:', targetTabId);
            }

            // Show target content
            const targetContent = document.getElementById(targetTabId + '-tab');
            if (targetContent) {
                targetContent.classList.remove('hidden');
                console.log('Showed content for:', targetTabId);
            } else {
                console.error('Content not found for:', targetTabId + '-tab');
            }
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

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, setting up profile with modals...');

            // Set up image handling
            setupImageHandlers();

            // Set up modals
            setupModalCloseOnOutsideClick();
            setupImageUpload();

            // Set up tab buttons
            const tabButtons = document.querySelectorAll('.tab-btn');
            console.log('Found', tabButtons.length, 'tab buttons');

            tabButtons.forEach((button, index) => {
                const tabId = button.getAttribute('data-tab');
                console.log(`Setting up button ${index + 1}: "${tabId}"`);

                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Tab clicked:', tabId);
                    switchTab(tabId);
                });
            });

            // Edit profile button - opens modal instead of switching tab
            const editProfileBtn = document.getElementById('editProfileBtn');
            if (editProfileBtn) {
                editProfileBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Edit profile clicked - opening modal');
                    openModal('profileEditModal');
                });
                console.log('Edit profile button set up');
            }

            // Edit photo button - opens modal instead of switching tab
            const editPhotoBtn = document.getElementById('editPhotoBtn');
            if (editPhotoBtn) {
                editPhotoBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Edit photo clicked - opening modal');
                    openModal('imageUploadModal');
                });
                console.log('Edit photo button set up');
            }

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
            // Ensure an active tab on load
            const activeTabBtn = document.querySelector('.tab-btn.border-primary-600') || document.querySelector('.tab-btn[data-tab]');
            if (activeTabBtn) {
                switchTab(activeTabBtn.getAttribute('data-tab'));
            }

            console.log('Profile with modals setup complete!');
        });

        console.log('Profile JavaScript with Modals loaded');
    </script>

    <?php include 'includes/footer.php'; ?>