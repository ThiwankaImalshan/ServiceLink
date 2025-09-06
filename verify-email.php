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

$pageTitle = 'Email Verification • ServiceLink';
$pageDescription = 'Verify your email address to complete registration.';

$error = '';
$success = '';
$email = $_GET['email'] ?? '';
$resendMessage = '';

// Initialize OTP Manager
$otpManager = new OTPManager();

// Validate email parameter
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect(BASE_URL . '/login.php?error=invalid_verification_link');
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    // Temporary debug: log form submission
    file_put_contents('debug_verification.log', date('Y-m-d H:i:s') . " - Form submitted with OTP: " . ($_POST['otp'] ?? 'none') . "\n", FILE_APPEND);
    
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        file_put_contents('debug_verification.log', date('Y-m-d H:i:s') . " - CSRF token valid\n", FILE_APPEND);
        
        $otp = trim($_POST['otp'] ?? '');
        
        if (strlen($otp) === 6 && ctype_digit($otp)) {
            file_put_contents('debug_verification.log', date('Y-m-d H:i:s') . " - OTP format valid, attempting verification\n", FILE_APPEND);
            
            $result = $otpManager->verifyOTP($email, $otp, 'registration');
            
            file_put_contents('debug_verification.log', date('Y-m-d H:i:s') . " - Verification result: " . json_encode($result) . "\n", FILE_APPEND);
            
            if ($result['success']) {
                // Update user's email_verified status
                try {
                    $db = getDB();
                    $stmt = $db->prepare("UPDATE users SET email_verified = 1 WHERE email = ?");
                    $updateResult = $stmt->execute([$email]);
                    
                    if ($updateResult) {
                        // Get user details for welcome email
                        $stmt = $db->prepare("SELECT first_name, role FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($user) {
                            // Send welcome email (temporarily disabled for debugging)
                            // sendWelcomeEmail($email, $user['first_name'], $user['role']);
                            
                            setFlashMessage('Email verified successfully! You can now login to your account.', 'success');
                            redirect(BASE_URL . '/login.php?verified=1');
                        } else {
                            $error = 'User account not found.';
                        }
                    } else {
                        $error = 'Failed to update verification status.';
                    }
                } catch (PDOException $e) {
                    error_log("Email verification database error: " . $e->getMessage());
                    $error = 'Database error occurred. Please try again.';
                }
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Please enter a valid 6-digit OTP.';
        }
    } else {
        $error = 'Invalid request. Please try again.';
    }
}

// Handle resend OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        // Check if user has exceeded daily limit
        if ($otpManager->hasExceededLimit($email, 'registration', 5)) {
            $error = 'You have exceeded the daily limit for OTP requests. Please try again tomorrow.';
        } else {
            // Get user details
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT first_name FROM users WHERE email = ? AND email_verified = 0");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $otpResult = $otpManager->createOTP($email, null, 'registration');
                    
                    if ($otpResult['success']) {
                        $emailResult = sendOTPEmail($email, $user['first_name'], $otpResult['otp']);
                        
                        if ($emailResult['success']) {
                            $resendMessage = 'New verification code sent to your email.';
                        } else {
                            $error = 'Failed to send verification email. Please try again.';
                        }
                    } else {
                        $error = 'Failed to generate new verification code.';
                    }
                } else {
                    $error = 'Email address not found or already verified.';
                }
            } catch (PDOException $e) {
                error_log("Resend OTP database error: " . $e->getMessage());
                $error = 'Database error occurred. Please try again.';
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
      <div class="absolute inset-0 bg-gradient-to-br from-green-600 to-emerald-600"></div>
      <div class="absolute inset-0 bg-black bg-opacity-20"></div>
      
      <!-- Content -->
      <div class="relative z-10 flex flex-col justify-center px-12 xl:px-16 text-white">
        <div class="max-w-lg">
          <div class="text-center mb-8">
            <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fa-solid fa-envelope-circle-check text-4xl text-white"></i>
            </div>
          </div>
          
          <h1 class="text-4xl xl:text-5xl font-bold mb-6 leading-tight text-center">
            Almost <span class="text-yellow-300">There!</span>
          </h1>
          <p class="text-xl xl:text-2xl text-green-100 mb-8 leading-relaxed text-center">
            We've sent a verification code to your email address. Please check your inbox and enter the code below.
          </p>
          
          <!-- Steps -->
          <div class="space-y-4">
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                <span class="text-white text-sm font-bold">1</span>
              </div>
              <span class="text-lg">Check your email inbox</span>
            </div>
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                <span class="text-white text-sm font-bold">2</span>
              </div>
              <span class="text-lg">Enter the 6-digit verification code</span>
            </div>
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-yellow-300 rounded-full flex items-center justify-center">
                <span class="text-green-800 text-sm font-bold">3</span>
              </div>
              <span class="text-lg">Start using ServiceLink!</span>
            </div>
          </div>
          
          <!-- Info Box -->
          <div class="mt-12 bg-white bg-opacity-10 rounded-xl p-6">
            <h3 class="text-lg font-semibold mb-2">Didn't receive the email?</h3>
            <p class="text-green-100 text-sm">
              Check your spam folder or click the resend button below. The verification code expires in 10 minutes.
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
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fa-solid fa-shield-check text-green-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-neutral-900 mb-1">Verify Your Email</h2>
            <p class="text-neutral-600 text-sm">
              Enter the 6-digit code sent to<br>
              <span class="font-medium text-neutral-900"><?php echo e($email); ?></span>
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

          <?php if ($resendMessage): ?>
          <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg">
            <div class="flex items-center space-x-2">
              <i class="fa-solid fa-info-circle"></i>
              <span><?php echo e($resendMessage); ?></span>
            </div>
          </div>
          <?php endif; ?>

          <!-- Verification Form -->
          <form method="POST" action="" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div>
              <label for="otp" class="block text-sm font-medium text-neutral-700 mb-3 text-center">Verification Code</label>
              <div class="relative">
                <input type="text" id="otp" name="otp" required maxlength="6" 
                       class="w-full text-center text-2xl font-mono tracking-widest py-4 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                       placeholder="000000"
                       autocomplete="one-time-code">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                  <i class="fa-solid fa-key text-neutral-400"></i>
                </div>
              </div>
              <p class="text-xs text-neutral-500 mt-2 text-center">Enter the 6-digit code from your email</p>
            </div>

            <button type="submit" name="verify_otp" 
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors shadow-lg hover:shadow-glow">
              <i class="fa-solid fa-check-circle mr-2"></i>
              Verify Email Address
            </button>
          </form>

          <!-- Resend Section -->
          <div class="border-t border-neutral-200 pt-6">
            <div class="text-center space-y-4">
              <p class="text-sm text-neutral-600">
                Didn't receive the code?
              </p>
              
              <form method="POST" action="" class="inline">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <button type="submit" name="resend_otp" 
                        class="text-green-600 hover:text-green-700 font-medium text-sm border border-green-200 hover:border-green-300 px-4 py-2 rounded-lg transition-colors">
                  <i class="fa-solid fa-paper-plane mr-1"></i>
                  Resend Code
                </button>
              </form>
              
              <div class="text-xs text-neutral-500">
                <p>Or <a href="<?php echo BASE_URL; ?>/login.php" class="text-primary-600 hover:text-primary-700">return to login</a></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Auto-format OTP input
document.getElementById('otp').addEventListener('input', function(e) {
  // Remove any non-digits
  let value = e.target.value.replace(/\D/g, '');
  
  // Limit to 6 digits
  if (value.length > 6) {
    value = value.substring(0, 6);
  }
  
  e.target.value = value;
  
  // Auto-submit when 6 digits are entered (TEMPORARILY DISABLED FOR DEBUGGING)
  /*if (value.length === 6) {
    // Add a small delay to show the complete number
    setTimeout(() => {
      e.target.form.submit();
    }, 500);
  }*/
});

// Focus on OTP input when page loads
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('otp').focus();
});

// Handle paste events
document.getElementById('otp').addEventListener('paste', function(e) {
  e.preventDefault();
  let paste = (e.clipboardData || window.clipboardData).getData('text');
  let digits = paste.replace(/\D/g, '').substring(0, 6);
  this.value = digits;
  
  if (digits.length === 6) {
    // TEMPORARILY DISABLED AUTO-SUBMIT FOR DEBUGGING
    /*setTimeout(() => {
      this.form.submit();
    }, 500);*/
  }
});
</script>

<?php include 'includes/footer.php'; ?>
