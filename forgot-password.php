<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/OTPManager.php';
require_once 'config/email.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect(BASE_URL . '/index.php');
}

$pageTitle = 'Forgot Password • ServiceLink';
$pageDescription = 'Reset your ServiceLink account password.';

$error = '';
$success = '';

// Initialize OTP Manager
$otpManager = new OTPManager();

// Handle forgot password form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $email = trim($_POST['email'] ?? '');
        
        if (!$email) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $db = getDB();
                
                // Check if user exists and is verified
                $stmt = $db->prepare("SELECT id, first_name, email_verified FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    // Don't reveal if email exists for security
                    $success = 'If your email address is registered with us, you will receive password reset instructions.';
                } elseif (!$user['email_verified']) {
                    $error = 'Please verify your email address first before requesting a password reset.';
                } else {
                    // Check daily OTP limit
                    if ($otpManager->hasExceededLimit($email, 'password_reset', 3)) {
                        $error = 'You have exceeded the daily limit for password reset requests. Please try again tomorrow.';
                    } else {
                        // Generate and send password reset OTP
                        $otpResult = $otpManager->createOTP($email, $user['id'], 'password_reset');
                        
                        if ($otpResult['success']) {
                            $emailResult = sendPasswordResetEmail($email, $user['first_name'], $otpResult['otp']);
                            
                            if ($emailResult['success']) {
                                setFlashMessage('Password reset instructions have been sent to your email.', 'success');
                                redirect(BASE_URL . '/reset-password.php?email=' . urlencode($email));
                            } else {
                                $error = 'Failed to send password reset email. Please try again.';
                            }
                        } else {
                            $error = 'Failed to generate password reset code. Please try again.';
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Forgot password database error: " . $e->getMessage());
                $error = 'A database error occurred. Please try again.';
            }
        }
    } else {
        $error = 'Invalid request. Please try again.';
    }
}

include 'includes/header_simple.php';
?>

<div class="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-50">
  <div class="min-h-screen flex">
    
    <!-- Left Side Container - Hidden on Mobile/Tablet -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
      <!-- Background with overlay -->
      <div class="absolute inset-0 bg-gradient-to-br from-blue-600 to-purple-600"></div>
      <div class="absolute inset-0 bg-black bg-opacity-20"></div>
      
      <!-- Content -->
      <div class="relative z-10 flex flex-col justify-center px-12 xl:px-16 text-white">
        <div class="max-w-lg">
          <div class="text-center mb-8">
            <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fa-solid fa-shield-halved text-4xl text-white"></i>
            </div>
          </div>
          
          <h1 class="text-4xl xl:text-5xl font-bold mb-6 leading-tight text-center">
            Secure <span class="text-yellow-300">Account Recovery</span>
          </h1>
          <p class="text-xl xl:text-2xl text-blue-100 mb-8 leading-relaxed text-center">
            Don't worry! It happens to the best of us. Let's get you back into your account safely.
          </p>
          
          <!-- Security Steps -->
          <div class="space-y-4">
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-envelope text-blue-800 text-sm"></i>
              </div>
              <span class="text-lg">Enter your registered email address</span>
            </div>
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-key text-blue-800 text-sm"></i>
              </div>
              <span class="text-lg">Receive a secure verification code</span>
            </div>
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-lock text-blue-800 text-sm"></i>
              </div>
              <span class="text-lg">Create a new strong password</span>
            </div>
          </div>
          
          <!-- Security Notice -->
          <div class="mt-12 bg-white bg-opacity-10 rounded-xl p-6">
            <h3 class="text-lg font-semibold mb-2 flex items-center">
              <i class="fa-solid fa-shield-check mr-2 text-yellow-300"></i>
              Your Security Matters
            </h3>
            <p class="text-blue-100 text-sm">
              We use advanced encryption and secure verification processes to protect your account. Only you can reset your password with access to your email.
            </p>
          </div>
        </div>
      </div>
      
      <!-- Decorative Elements -->
      <div class="absolute top-10 right-10 w-32 h-32 bg-white bg-opacity-10 rounded-full"></div>
      <div class="absolute bottom-10 left-10 w-24 h-24 bg-yellow-300 bg-opacity-20 rounded-full"></div>
      <div class="absolute top-1/2 right-1/4 w-16 h-16 bg-white bg-opacity-15 rounded-full"></div>
    </div>

    <!-- Right Side - Form Container -->
    <div class="w-full lg:w-1/2 flex items-center justify-center py-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-md w-full bg-white rounded-2xl shadow-xl border border-neutral-100 overflow-hidden">
        <div class="p-6 space-y-6">
    
          <!-- Header -->
          <div class="text-center">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fa-solid fa-key text-blue-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-neutral-900 mb-1">Reset Password</h2>
            <p class="text-neutral-600 text-sm">
              Enter your email address and we'll send you a secure verification code to reset your password.
            </p>
          </div>

          <!-- Messages -->
          <?php if ($error): ?>
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
            <div class="flex items-center space-x-2">
              <i class="fa-solid fa-exclamation-circle"></i>
              <span><?php echo e($error); ?></span>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($success): ?>
          <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            <div class="flex items-center space-x-2">
              <i class="fa-solid fa-check-circle"></i>
              <span><?php echo e($success); ?></span>
            </div>
          </div>
          <?php endif; ?>

          <!-- Reset Form -->
          <form method="POST" action="" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div>
              <label for="email" class="block text-sm font-medium text-neutral-700 mb-2">Email Address</label>
              <div class="relative">
                <input type="email" id="email" name="email" required 
                       class="w-full pl-10 pr-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Enter your registered email"
                       value="<?php echo e($_POST['email'] ?? ''); ?>">
                <i class="fa-solid fa-envelope absolute left-3 top-4 text-neutral-400"></i>
              </div>
              <p class="text-xs text-neutral-500 mt-2">We'll send a verification code to this email address</p>
            </div>

            <button type="submit" name="forgot_password" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors shadow-lg hover:shadow-glow">
              <i class="fa-solid fa-paper-plane mr-2"></i>
              Send Verification Code
            </button>
          </form>

          <!-- Back to Login -->
          <div class="border-t border-neutral-200 pt-6">
            <div class="text-center">
              <p class="text-sm text-neutral-600 mb-3">
                Remember your password?
              </p>
              <a href="<?php echo BASE_URL; ?>/login.php" 
                 class="text-blue-600 hover:text-blue-700 font-medium text-sm border border-blue-200 hover:border-blue-300 px-4 py-2 rounded-lg transition-colors inline-flex items-center space-x-1">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Login</span>
              </a>
            </div>
          </div>

          <!-- Security Notice -->
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start space-x-3">
              <i class="fa-solid fa-info-circle text-blue-600 mt-1 flex-shrink-0"></i>
              <div>
                <h4 class="text-blue-900 font-medium text-sm">Security Notice</h4>
                <p class="text-blue-800 text-xs mt-1">
                  For your security, password reset links expire after 10 minutes. If you don't receive an email, please check your spam folder.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Focus on email input when page loads
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('email').focus();
});

// Email validation
document.getElementById('email').addEventListener('input', function(e) {
  const email = e.target.value;
  const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  
  if (email.length > 0 && !isValid) {
    e.target.setCustomValidity('Please enter a valid email address');
  } else {
    e.target.setCustomValidity('');
  }
});
</script>

<?php include 'includes/footer.php'; ?>
