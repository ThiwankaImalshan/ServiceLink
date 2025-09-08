<?php
// Include required files first
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/ImageUploader.php';
require_once 'includes/OTPManager.php';
require_once 'config/email.php';

// Get database connection
$db = getDB();

// Create ImageUploader instance
$imageUploader = new ImageUploader();

// Redirect if already logged in (before any output)
if ($auth->isLoggedIn()) {
    redirect(BASE_URL . '/index.php');
}

$pageTitle = 'Login • ServiceLink';
$pageDescription = 'Login to your ServiceLink account or create a new one.';

$loginError = '';
$registerError = '';
$registerSuccess = '';

// Check for verification success message
if (isset($_GET['verified']) && $_GET['verified'] === '1') {
    $registerSuccess = 'Email verified successfully! You can now login to your account.';
}

// Check for password reset success message
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $registerSuccess = 'Password reset successfully! You can now login with your new password.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if ($username && $password) {
            $result = $auth->login($username, $password);
            if ($result['success']) {
                setFlashMessage('Welcome back, ' . $_SESSION['full_name'] . '!', 'success');
                redirect(BASE_URL . '/index.php');
            } else {
                $loginError = $result['message'];
            }
        } else {
            $loginError = 'Please fill in all fields.';
        }
    } else {
        $loginError = 'Invalid request. Please try again.';
    }
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $username = trim($_POST['reg_username'] ?? '');
        $email = trim($_POST['reg_email'] ?? '');
        $password = $_POST['reg_password'] ?? '';
        $confirmPassword = $_POST['reg_confirm_password'] ?? '';
        $firstName = trim($_POST['reg_first_name'] ?? '');
        $lastName = trim($_POST['reg_last_name'] ?? '');
        $phone = trim($_POST['reg_phone'] ?? '');
        $role = $_POST['reg_role'] ?? 'user';
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        
        // Validation
        if (!$username || !$email || !$password || !$firstName || !$lastName) {
            $registerError = 'Please fill in all required fields.';
        } elseif ($password !== $confirmPassword) {
            $registerError = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $registerError = 'Password must be at least 6 characters long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $registerError = 'Please enter a valid email address.';
        } elseif (!in_array($role, ['user', 'provider'])) {
            $registerError = 'Invalid role selected.';
        } elseif ($role === 'provider' && (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] === UPLOAD_ERR_NO_FILE)) {
            $registerError = 'Profile photo is required for service providers.';
        } elseif ($role === 'provider' && (!$latitude || !$longitude)) {
            $registerError = 'Location is required for service providers.';
        } elseif ($role === 'provider' && (!is_numeric($latitude) || !is_numeric($longitude))) {
            $registerError = 'Invalid location coordinates provided.';
        } elseif ($role === 'provider' && (abs($latitude) > 90 || abs($longitude) > 180)) {
            $registerError = 'Location coordinates are out of valid range.';
        } else {
            $profilePhotoPath = null;
            
            // Handle profile photo upload for providers
            if ($role === 'provider' && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $imageUploader->uploadImage($_FILES['profile_photo'], 'provider_');
                if ($uploadResult['success']) {
                    $profilePhotoPath = $uploadResult['path'];
                } else {
                    $registerError = 'Photo upload failed: ' . $uploadResult['message'];
                }
            }
            
            // Only proceed with registration if no photo upload errors
            if (!$registerError) {
                // Check if email already exists
                try {
                    $stmt = $db->prepare("SELECT id, email_verified FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existingUser) {
                        if ($existingUser['email_verified']) {
                            $registerError = 'An account with this email address already exists.';
                        } else {
                            $registerError = 'An account with this email exists but is not verified. Please check your email for verification instructions.';
                        }
                    } else {
                        // Check daily OTP limit
                        if ($otpManager->hasExceededLimit($email, 'registration', 5)) {
                            $registerError = 'You have exceeded the daily limit for registration attempts. Please try again tomorrow.';
                        } else {
                            // Create user account with email_verified = 0
                            $result = $auth->registerWithEmailVerification($username, $email, $password, $firstName, $lastName, $phone, $role, $profilePhotoPath);
                            
                            if ($result['success']) {
                                $userId = $result['user_id'];
                                
                                // Handle provider-specific setup
                                if ($role === 'provider') {
                                    try {
                                        $db->beginTransaction();
                                        
                                        // Create provider record with location data
                                        $stmt = $db->prepare("
                                            INSERT INTO providers (user_id, latitude, longitude, created_at) 
                                            VALUES (?, ?, ?, NOW())
                                        ");
                                        $stmt->execute([$userId, (float)$latitude, (float)$longitude]);
                                        
                                        $db->commit();
                                    } catch (PDOException $e) {
                                        $db->rollback();
                                        $registerError = 'Registration failed while setting up provider profile.';
                                        
                                        // Clean up uploaded photo and user record
                                        if ($profilePhotoPath) {
                                            $imageUploader->deleteImage(basename($profilePhotoPath));
                                        }
                                        
                                        try {
                                            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                                            $stmt->execute([$userId]);
                                        } catch (PDOException $e2) {
                                            error_log("Failed to cleanup user record: " . $e2->getMessage());
                                        }
                                    }
                                }
                                
                                // Only send OTP if no provider setup errors occurred
                                if (!$registerError) {
                                    // Generate and send OTP
                                    $otpResult = $otpManager->createOTP($email, $userId, 'registration');
                                    
                                    if ($otpResult['success']) {
                                        $emailResult = sendOTPEmail($email, $firstName, $otpResult['otp']);
                                        
                                        if ($emailResult['success']) {
                                            setFlashMessage('Registration successful! Please check your email for verification instructions.', 'success');
                                            redirect(BASE_URL . '/verify-email.php?email=' . urlencode($email));
                                        } else {
                                            $registerError = 'Registration successful but failed to send verification email. Please contact support.';
                                        }
                                    } else {
                                        $registerError = 'Registration successful but failed to generate verification code. Please contact support.';
                                    }
                                }
                            } else {
                                $registerError = $result['message'];
                                // Clean up uploaded photo if registration failed
                                if ($profilePhotoPath) {
                                    $imageUploader->deleteImage(basename($profilePhotoPath));
                                }
                            }
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Registration database error: " . $e->getMessage());
                    $registerError = 'A database error occurred. Please try again.';
                    if ($profilePhotoPath) {
                        $imageUploader->deleteImage(basename($profilePhotoPath));
                    }
                }
            }
        }
    } else {
        $registerError = 'Invalid request. Please try again.';
    }
}

// Include simple header after all processing is complete
include 'includes/header_simple.php';
?>

<div class="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-50">
  <div class="min-h-screen flex">
    
    <!-- Left Side Container - Hidden on Mobile/Tablet -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
      <!-- Background with overlay -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-600 to-secondary-600"></div>
      <div class="absolute inset-0 bg-black bg-opacity-20"></div>
      
      <!-- Content -->
      <div class="relative z-10 flex flex-col justify-center px-12 xl:px-16 text-white">
        <div class="max-w-lg">
          <h1 class="text-4xl xl:text-5xl font-bold mb-6 leading-tight">
            Connect with <span class="text-yellow-300">Skilled Professionals</span>
          </h1>
          <p class="text-xl xl:text-2xl text-blue-100 mb-8 leading-relaxed">
            Find trusted local service providers or offer your skills to customers in your area.
          </p>
          
          <!-- Features List -->
          <div class="space-y-4">
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-check text-white text-sm"></i>
              </div>
              <span class="text-lg">Verified service providers</span>
            </div>
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-check text-white text-sm"></i>
              </div>
              <span class="text-lg">Secure payments & reviews</span>
            </div>
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-check text-white text-sm"></i>
              </div>
              <span class="text-lg">24/7 customer support</span>
            </div>
          </div>
          
          <!-- Statistics -->
          <div class="mt-12 grid grid-cols-3 gap-6">
            <div class="text-center">
              <div class="text-3xl font-bold text-yellow-300">500+</div>
              <div class="text-sm text-blue-100">Active Providers</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-yellow-300">1000+</div>
              <div class="text-sm text-blue-100">Happy Customers</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-yellow-300">25+</div>
              <div class="text-sm text-blue-100">Service Categories</div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Decorative Elements -->
      <div class="absolute top-10 right-10 w-32 h-32 bg-white bg-opacity-10 rounded-full"></div>
      <div class="absolute bottom-10 left-10 w-24 h-24 bg-yellow-300 bg-opacity-20 rounded-full"></div>
      <div class="absolute top-1/2 right-1/4 w-16 h-16 bg-green-400 bg-opacity-15 rounded-full"></div>
    </div>

    <!-- Right Side - Form Container -->
    <div class="w-full lg:w-1/2 flex items-center justify-center py-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-md w-full bg-white rounded-2xl shadow-xl border border-neutral-100 overflow-hidden">
        <div class="p-6 space-y-6">
    
    <!-- Header -->
    <div class="text-center">
      <h2 class="text-2xl font-bold text-neutral-900 mb-1">Welcome</h2>
      <p class="text-neutral-600 text-sm">Sign in to your account or create a new one</p>
    </div>

    <!-- Tabs -->
    <div class="bg-neutral-50 rounded-xl overflow-hidden border border-neutral-200">
      <div class="flex border-b border-neutral-200">
        <button id="login-tab" class="flex-1 py-3 px-6 text-center font-medium text-primary-600 border-b-2 border-primary-600 bg-white transition-colors">
          Sign In
        </button>
        <button id="register-tab" class="flex-1 py-3 px-6 text-center font-medium text-neutral-500 hover:text-neutral-700 transition-colors">
          Sign Up
        </button>
      </div>

      <!-- Login Form -->
      <div id="login-form" class="p-6 bg-white">
        <?php if ($loginError): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <?php echo e($loginError); ?>
        </div>
        <?php endif; ?>

        <?php if ($registerSuccess): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
          <?php echo e($registerSuccess); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
          
          <div>
            <label for="username" class="block text-sm font-medium text-neutral-700 mb-2">Username or Email</label>
            <div class="relative">
              <input type="text" id="username" name="username" required 
                     class="w-full pl-10 pr-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                     placeholder="Enter your username or email">
              <i class="fa-solid fa-user absolute left-3 top-4 text-neutral-400"></i>
            </div>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-neutral-700 mb-2">Password</label>
            <div class="relative">
              <input type="password" id="password" name="password" required 
                     class="w-full pl-10 pr-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                     placeholder="Enter your password">
              <i class="fa-solid fa-lock absolute left-3 top-4 text-neutral-400"></i>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <label class="flex items-center">
              <input type="checkbox" class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
              <span class="ml-2 text-sm text-neutral-600">Remember me</span>
            </label>
            <a href="<?php echo BASE_URL; ?>/forgot-password.php" class="text-sm text-primary-600 hover:text-primary-700">Forgot password?</a>
          </div>

          <button type="submit" name="login" 
                  class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-3 px-4 rounded-lg transition-colors shadow-lg hover:shadow-glow">
            Sign In
          </button>
        </form>
      </div>

      <!-- Register Form -->
      <div id="register-form" class="p-6 bg-white hidden">
        <?php if ($registerError): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <?php echo e($registerError); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
          
          <!-- Account Type -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-3">Account Type</label>
            <div class="grid grid-cols-2 gap-4">
              <label class="flex items-center p-3 border border-neutral-300 rounded-lg cursor-pointer hover:bg-neutral-50 transition-colors">
                <input type="radio" name="reg_role" value="user" checked class="text-primary-600 focus:ring-primary-500">
                <div class="ml-3">
                  <div class="text-sm font-medium text-neutral-900">Customer</div>
                  <div class="text-xs text-neutral-600">Find services</div>
                </div>
              </label>
              <label class="flex items-center p-3 border border-neutral-300 rounded-lg cursor-pointer hover:bg-neutral-50 transition-colors">
                <input type="radio" name="reg_role" value="provider" class="text-primary-600 focus:ring-primary-500">
                <div class="ml-3">
                  <div class="text-sm font-medium text-neutral-900">Provider</div>
                  <div class="text-xs text-neutral-600">Offer services</div>
                </div>
              </label>
            </div>
          </div>

          <!-- Personal Information -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="reg_first_name" class="block text-sm font-medium text-neutral-700 mb-2">First Name *</label>
              <input type="text" id="reg_first_name" name="reg_first_name" required 
                     class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                     placeholder="John">
            </div>
            <div>
              <label for="reg_last_name" class="block text-sm font-medium text-neutral-700 mb-2">Last Name *</label>
              <input type="text" id="reg_last_name" name="reg_last_name" required 
                     class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                     placeholder="Doe">
            </div>
            <div class="col-span-2">
              <label for="reg_gender" class="block text-sm font-medium text-neutral-700 mb-2">Gender *</label>
              <select id="reg_gender" name="reg_gender" required class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <option value="">Select gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>

          <!-- Contact Information -->
          <div>
            <label for="reg_username" class="block text-sm font-medium text-neutral-700 mb-2">Username *</label>
            <input type="text" id="reg_username" name="reg_username" required 
                   class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                   placeholder="johndoe">
          </div>

          <div>
            <label for="reg_email" class="block text-sm font-medium text-neutral-700 mb-2">Email Address *</label>
            <input type="email" id="reg_email" name="reg_email" required 
                   class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                   placeholder="john@example.com">
          </div>

          <div>
            <label for="reg_phone" class="block text-sm font-medium text-neutral-700 mb-2">Phone Number</label>
            <input type="tel" id="reg_phone" name="reg_phone" 
                   class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                   placeholder="+1 555 123 4567">
          </div>

          <!-- Password -->
          <div>
            <label for="reg_password" class="block text-sm font-medium text-neutral-700 mb-2">Password *</label>
            <input type="password" id="reg_password" name="reg_password" required minlength="6"
                   class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                   placeholder="At least 6 characters">
          </div>

          <div>
            <label for="reg_confirm_password" class="block text-sm font-medium text-neutral-700 mb-2">Confirm Password *</label>
            <input type="password" id="reg_confirm_password" name="reg_confirm_password" required minlength="6"
                   class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                   placeholder="Confirm your password">
          </div>

          <!-- Profile Photo for Providers -->
          <div id="provider-photo-section" class="hidden">
            <label for="profile_photo" class="block text-sm font-medium text-neutral-700 mb-2">
              Profile Photo * 
              <span class="text-xs text-neutral-500">(Required for service providers)</span>
            </label>
            
            <!-- Photo Preview -->
            <div class="mb-4">
              <div id="photo-preview" class="hidden">
                <img id="preview-image" src="" alt="Profile Preview" 
                     class="w-24 h-24 rounded-full object-cover border-4 border-primary-200 mx-auto">
                <p class="text-xs text-center text-neutral-600 mt-2">Profile Photo Preview</p>
              </div>
              
              <!-- Upload Placeholder -->
              <div id="photo-placeholder" class="w-24 h-24 rounded-full bg-neutral-100 border-4 border-dashed border-neutral-300 flex items-center justify-center mx-auto">
                <i class="fa-solid fa-camera text-2xl text-neutral-400"></i>
              </div>
            </div>
            
            <!-- File Input -->
            <input type="file" id="profile_photo" name="profile_photo" accept="image/*"
                   class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            <p class="text-xs text-neutral-500 mt-1">
              Upload a clear photo of yourself. Maximum 5MB. Supported formats: JPG, PNG, GIF, WebP.
            </p>
          </div>

          <!-- Location Selection for Providers -->
          <div id="provider-location-section" class="hidden">
            <label class="block text-sm font-medium text-neutral-700 mb-2">
              Service Location * 
              <span class="text-xs text-neutral-500">(Required for service providers)</span>
            </label>
            
            <!-- Location Input and Search -->
            <div class="space-y-4">
              <div>
                <div class="flex space-x-2">
                  <button type="button" id="use-current-location" 
                          class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors flex items-center space-x-1"
                          title="Use current location">
                    <i class="fa-solid fa-location-crosshairs"></i>
                    <span class="hidden sm:inline">Current</span>
                  </button>
                </div>
                <p class="text-xs text-neutral-500 mt-1">
                  Click "Current" to use your current location or click on the map to set your location
                </p>
              </div>
              
              <!-- Map Container -->
              <div class="border border-neutral-300 rounded-lg overflow-hidden">
                <div id="location-map" style="height: 300px; width: 100%;" class="bg-neutral-100 flex items-center justify-center">
                  <div class="text-center text-neutral-500">
                    <i class="fa-solid fa-map-marker-alt text-3xl mb-2"></i>
                    <p>Click on map or search to set your location</p>
                  </div>
                </div>
              </div>
              
              <!-- Selected Location Display -->
              <div id="selected-location" class="hidden p-3 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-start space-x-2">
                  <i class="fa-solid fa-map-marker-alt text-green-600 mt-1"></i>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-green-900">Selected Location:</p>
                    <p id="selected-address" class="text-sm text-green-700"></p>
                    <p class="text-xs text-green-600 mt-1">
                      Lat: <span id="selected-lat"></span>, Lng: <span id="selected-lng"></span>
                    </p>
                  </div>
                  <button type="button" id="clear-location" 
                          class="text-green-600 hover:text-green-800 p-1"
                          title="Clear location">
                    <i class="fa-solid fa-times"></i>
                  </button>
                </div>
              </div>
              
              <!-- Hidden inputs for coordinates -->
              <input type="hidden" id="latitude" name="latitude" value="">
              <input type="hidden" id="longitude" name="longitude" value="">
            </div>
          </div>

          <!-- Terms -->
          <div class="flex items-start">
            <input type="checkbox" id="terms" required class="mt-1 rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
            <label for="terms" class="ml-2 text-sm text-neutral-600">
              I agree to the <a href="#" class="text-primary-600 hover:text-primary-700">Terms of Service</a> and 
              <a href="#" class="text-primary-600 hover:text-primary-700">Privacy Policy</a>
            </label>
          </div>

          <button type="submit" name="register" 
                  class="w-full bg-secondary-600 hover:bg-secondary-700 text-white font-medium py-3 px-4 rounded-lg transition-colors shadow-lg hover:shadow-glow-secondary">
            Create Account
          </button>
        </form>
      </div>
    </div>

    <!-- Demo Accounts Info -->
    <!-- <div class="bg-white rounded-lg shadow-md p-6 mt-6">
      <h3 class="text-lg font-semibold text-neutral-900 mb-3">Demo Accounts</h3>
      <div class="space-y-2 text-sm text-neutral-600">
        <div><strong>Admin:</strong> admin / admin123</div>
        <div><strong>Provider:</strong> alex_carpenter / password123</div>
        <div><strong>Customer:</strong> john_user / password123</div>
      </div>
    </div> -->

        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM loaded, initializing login page');
  
  const loginTab = document.getElementById('login-tab');
  const registerTab = document.getElementById('register-tab');
  const loginForm = document.getElementById('login-form');
  const registerForm = document.getElementById('register-form');

  // Debug: Check if elements exist
  console.log('Elements found:', {
    loginTab: !!loginTab,
    registerTab: !!registerTab,
    loginForm: !!loginForm,
    registerForm: !!registerForm
  });

  if (!loginTab || !registerTab || !loginForm || !registerForm) {
    console.error('Required elements not found!');
    return;
  }

  // Simple tab switching without complex initialization
  loginTab.addEventListener('click', function(e) {
    e.preventDefault();
    console.log('Login tab clicked');
    
    // Update tab appearance
    loginTab.classList.add('text-primary-600', 'border-b-2', 'border-primary-600', 'bg-white');
    loginTab.classList.remove('text-neutral-500');
    registerTab.classList.remove('text-primary-600', 'border-b-2', 'border-primary-600', 'bg-white');
    registerTab.classList.add('text-neutral-500');
    
    // Show/hide forms
    loginForm.classList.remove('hidden');
    registerForm.classList.add('hidden');
  });

  registerTab.addEventListener('click', function(e) {
    e.preventDefault();
    console.log('Register tab clicked');
    
    // Update tab appearance
    registerTab.classList.add('text-primary-600', 'border-b-2', 'border-primary-600', 'bg-white');
    registerTab.classList.remove('text-neutral-500');
    loginTab.classList.remove('text-primary-600', 'border-b-2', 'border-primary-600', 'bg-white');
    loginTab.classList.add('text-neutral-500');
    
    // Show/hide forms
    registerForm.classList.remove('hidden');
    loginForm.classList.add('hidden');
    
    console.log('Register form should now be visible');
  });

  // Initialize everything immediately for testing
  initializePasswordValidation();
  initializeRoleHandlers();
  initializePhotoPreview();
  initializeLocationHandlers();

  // Initialize role handlers function
  function initializeRoleHandlers() {
    console.log('Initializing role handlers');
    const roleInputs = document.querySelectorAll('input[name="reg_role"]');
    const photoSection = document.getElementById('provider-photo-section');
    const locationSection = document.getElementById('provider-location-section');
    const photoInput = document.getElementById('profile_photo');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    
    console.log('Role handler elements:', {
      roleInputs: roleInputs.length,
      photoSection: !!photoSection,
      locationSection: !!locationSection,
      photoInput: !!photoInput,
      latitudeInput: !!latitudeInput,
      longitudeInput: !!longitudeInput
    });
    
    if (!roleInputs.length || !photoSection || !locationSection) {
      console.warn('Role change elements not found');
      return;
    }
    
    function handleRoleChange() {
      const selectedRole = document.querySelector('input[name="reg_role"]:checked');
      if (!selectedRole) return;
      
      if (selectedRole.value === 'provider') {
        photoSection.classList.remove('hidden');
        locationSection.classList.remove('hidden');
        photoInput.setAttribute('required', 'required');
        latitudeInput.setAttribute('required', 'required');
        longitudeInput.setAttribute('required', 'required');
        
        // Initialize map if not already done
        if (!window.mapInitialized) {
          setTimeout(() => {
            initializeMap();
            initializeLocationHandlers();
          }, 100);
        }
      } else {
        photoSection.classList.add('hidden');
        locationSection.classList.add('hidden');
        photoInput.removeAttribute('required');
        latitudeInput.removeAttribute('required');
        longitudeInput.removeAttribute('required');
        
        // Clear the file input and preview
        photoInput.value = '';
        if (typeof hidePreview === 'function') {
          hidePreview();
        }
        
        // Clear location data
        if (typeof clearLocationData === 'function') {
          clearLocationData();
        }
      }
    }
    
    roleInputs.forEach(input => {
      input.addEventListener('change', handleRoleChange);
    });
  }

  // Password confirmation validation
  function initializePasswordValidation() {
    const password = document.getElementById('reg_password');
    const confirmPassword = document.getElementById('reg_confirm_password');
    
    if (!password || !confirmPassword) return;
    
    function validatePassword() {
      if (password.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity('Passwords do not match');
      } else {
        confirmPassword.setCustomValidity('');
      }
    }
    
    password.addEventListener('change', validatePassword);
    confirmPassword.addEventListener('keyup', validatePassword);
  }

  // Initialize everything when register tab is clicked
  registerTab.addEventListener('click', function() {
    setTimeout(() => {
      initializePasswordValidation();
      initializeRoleHandlers();
      initializePhotoPreview();
    }, 100);
  });

  // Photo preview functionality
  function initializePhotoPreview() {
    const photoPreview = document.getElementById('photo-preview');
    const previewImage = document.getElementById('preview-image');
    const photoPlaceholder = document.getElementById('photo-placeholder');
    const photoInput = document.getElementById('profile_photo');
    
    if (!photoPreview || !previewImage || !photoPlaceholder || !photoInput) return;
    
    photoInput.addEventListener('change', function() {
      const file = this.files[0];
      if (file) {
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
          alert('Please select a valid image file (JPG, PNG, GIF, or WebP).');
          this.value = '';
          hidePreview();
          return;
        }
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
          alert('File size must be less than 5MB.');
          this.value = '';
          hidePreview();
          return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
          showPreview(e.target.result);
        };
        reader.readAsDataURL(file);
      } else {
        hidePreview();
      }
    });
  }

  // Global photo preview functions
  function showPreview(src) {
    const previewImage = document.getElementById('preview-image');
    const photoPreview = document.getElementById('photo-preview');
    const photoPlaceholder = document.getElementById('photo-placeholder');
    
    if (previewImage && photoPreview && photoPlaceholder) {
      previewImage.src = src;
      photoPreview.classList.remove('hidden');
      photoPlaceholder.classList.add('hidden');
    }
  }
  
  function hidePreview() {
    const previewImage = document.getElementById('preview-image');
    const photoPreview = document.getElementById('photo-preview');
    const photoPlaceholder = document.getElementById('photo-placeholder');
    
    if (previewImage && photoPreview && photoPlaceholder) {
      previewImage.src = '';
      photoPreview.classList.add('hidden');
      photoPlaceholder.classList.remove('hidden');
    }
  }

  // Initialize location handlers
  function initializeLocationHandlers() {
    console.log('Initializing location handlers');
    // Location functionality
    const useCurrentLocationBtn = document.getElementById('use-current-location');
    const clearLocationBtn = document.getElementById('clear-location');
    
    console.log('Location elements found:', {
      useCurrentLocationBtn: !!useCurrentLocationBtn,
      clearLocationBtn: !!clearLocationBtn
    });
    
    if (!useCurrentLocationBtn || !clearLocationBtn) {
      console.log('Location elements not found, skipping location handlers');
      return;
    }

    // Current location functionality
    const newUseCurrentLocationBtn = useCurrentLocationBtn.cloneNode(true);
    useCurrentLocationBtn.parentNode.replaceChild(newUseCurrentLocationBtn, useCurrentLocationBtn);
    
    newUseCurrentLocationBtn.addEventListener('click', function(e) {
      e.preventDefault();
      console.log('Current location button clicked');
      
      if (navigator.geolocation) {
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span class="hidden sm:inline">Getting...</span>';
        this.disabled = true;
        
        navigator.geolocation.getCurrentPosition(
          function(position) {
            console.log('Got current position:', position.coords);
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            if (window.map) {
              map.setView([lat, lng], 15);
              setLocation(lat, lng);
              reverseGeocode(lat, lng);
            }
            
            // Reset button
            const btn = document.getElementById('use-current-location');
            if (btn) {
              btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> <span class="hidden sm:inline">Current</span>';
              btn.disabled = false;
            }
          },
          function(error) {
            console.error('Geolocation error:', error);
            alert('Error getting your location: ' + error.message);
            
            // Reset button
            const btn = document.getElementById('use-current-location');
            if (btn) {
              btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> <span class="hidden sm:inline">Current</span>';
              btn.disabled = false;
            }
          },
          {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 300000
          }
        );
      } else {
        alert('Geolocation is not supported by this browser.');
      }
    });

    // Clear location button
    const newClearLocationBtn = clearLocationBtn.cloneNode(true);
    clearLocationBtn.parentNode.replaceChild(newClearLocationBtn, clearLocationBtn);
    
    newClearLocationBtn.addEventListener('click', function(e) {
      e.preventDefault();
      console.log('Clear location button clicked');
      clearLocationData();
    });
    
    console.log('Location handlers initialized successfully');
  }

  // Location and Map functionality
  let map, marker;
  window.mapInitialized = false;

  function initializeMap() {
    if (window.mapInitialized) return;
    
    console.log('Initializing map');
    // Initialize map centered on a default location (you can change this)
    map = L.map('location-map').setView([40.7128, -74.0060], 10); // New York City default
    window.map = map; // Make map globally accessible

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: ' © OpenStreetMap contributors'
    }).addTo(map);

    // Add click event to map
    map.on('click', function(e) {
      console.log('Map clicked at:', e.latlng);
      setLocation(e.latlng.lat, e.latlng.lng);
      reverseGeocode(e.latlng.lat, e.latlng.lng);
    });

    window.mapInitialized = true;
    console.log('Map initialized successfully');
  }

  function setLocation(lat, lng) {
    // Remove existing marker
    if (marker) {
      map.removeLayer(marker);
    }

    // Add new marker
    marker = L.marker([lat, lng]).addTo(map);
    
    // Update hidden inputs
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
    
    // Update selected location display
    document.getElementById('selected-lat').textContent = lat.toFixed(6);
    document.getElementById('selected-lng').textContent = lng.toFixed(6);
    document.getElementById('selected-location').classList.remove('hidden');
  }

  function reverseGeocode(lat, lng) {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
      .then(response => response.json())
      .then(data => {
        if (data && data.display_name) {
          document.getElementById('selected-address').textContent = data.display_name;
        } else {
          document.getElementById('selected-address').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
      })
      .catch(error => {
        console.error('Reverse geocoding error:', error);
        document.getElementById('selected-address').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
      });
  }

  function clearLocationData() {
    document.getElementById('latitude').value = '';
    document.getElementById('longitude').value = '';
    document.getElementById('selected-location').classList.add('hidden');
    
    if (marker) {
      map.removeLayer(marker);
      marker = null;
    }
  }

  // Form validation for location
  function initializeFormValidation() {
    const registerFormEl = document.querySelector('form[enctype="multipart/form-data"]');
    if (registerFormEl) {
      registerFormEl.addEventListener('submit', function(e) {
        const selectedRole = document.querySelector('input[name="reg_role"]:checked');
        if (selectedRole && selectedRole.value === 'provider') {
          const lat = document.getElementById('latitude').value;
          const lng = document.getElementById('longitude').value;
          
          if (!lat || !lng) {
            e.preventDefault();
            alert('Please select your service location on the map.');
            return false;
          }
        }
      });
    }
  }

  // Initialize form validation
  initializeFormValidation();
});
</script>

<?php include 'includes/footer.php'; ?>
