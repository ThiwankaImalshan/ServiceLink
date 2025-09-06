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

$pageTitle = 'Reset Password • ServiceLink';
$pageDescription = 'Enter verification code and set your new password.';

$error = '';
$success = '';
$email = $_GET['email'] ?? '';
$step = $_GET['step'] ?? 'verify'; // 'verify' or 'password'

// Initialize OTP Manager
$otpManager = new OTPManager();

// Validate email parameter
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect(BASE_URL . '/forgot-password.php?error=invalid_reset_link');
}

// Handle OTP verification (Step 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp']) && $step === 'verify') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $otp = trim($_POST['otp'] ?? '');
        
        if (strlen($otp) === 6 && ctype_digit($otp)) {
            $result = $otpManager->verifyOTP($email, $otp, 'password_reset');
            
            if ($result['success']) {
                // Generate a temporary token for password reset
                $resetToken = bin2hex(random_bytes(32));
                
                try {
                    $db = getDB();
                    
                    // Store reset token with expiration (15 minutes)
                    $stmt = $db->prepare("
                        UPDATE users SET 
                            password_reset_token = ?, 
                            password_reset_expires = DATE_ADD(NOW(), INTERVAL 15 MINUTE) 
                        WHERE email = ?
                    ");
                    $stmt->execute([$resetToken, $email]);
                    
                    // Redirect to password reset step
                    redirect(BASE_URL . '/reset-password.php?email=' . urlencode($email) . '&step=password&token=' . $resetToken);
                    
                } catch (PDOException $e) {
                    error_log("Password reset token storage error: " . $e->getMessage());
                    $error = 'Database error occurred. Please try again.';
                }
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Please enter a valid 6-digit verification code.';
        }
    } else {
        $error = 'Invalid request. Please try again.';
    }
}

// Handle password reset (Step 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && $step === 'password') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $token = $_POST['token'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!$token || !$newPassword || !$confirmPassword) {
            $error = 'Please fill in all fields.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            try {
                $db = getDB();
                
                // Verify reset token and check expiration
                $stmt = $db->prepare("
                    SELECT id, first_name FROM users 
                    WHERE email = ? AND password_reset_token = ? AND password_reset_expires > NOW()
                ");
                $stmt->execute([$email, $token]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Update password and clear reset token
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("
                        UPDATE users SET 
                            password_hash = ?, 
                            password_reset_token = NULL, 
                            password_reset_expires = NULL 
                        WHERE id = ?
                    ");
                    $stmt->execute([$passwordHash, $user['id']]);
                    
                    // Send password changed notification
                    sendPasswordChangedEmail($email, $user['first_name']);
                    
                    setFlashMessage('Password reset successful! You can now login with your new password.', 'success');
                    redirect(BASE_URL . '/login.php?password_reset=1');
                    
                } else {
                    $error = 'Invalid or expired reset token. Please request a new password reset.';
                }
                
            } catch (PDOException $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error = 'Database error occurred. Please try again.';
            }
        }
    } else {
        $error = 'Invalid request. Please try again.';
    }
}

// Handle resend OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp']) && $step === 'verify') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        if ($otpManager->hasExceededLimit($email, 'password_reset', 3)) {
            $error = 'You have exceeded the daily limit for password reset requests. Please try again tomorrow.';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT first_name FROM users WHERE email = ? AND email_verified = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $otpResult = $otpManager->createOTP($email, null, 'password_reset');
                    
                    if ($otpResult['success']) {
                        $emailResult = sendPasswordResetEmail($email, $user['first_name'], $otpResult['otp']);
                        
                        if ($emailResult['success']) {
                            $success = 'New verification code sent to your email.';
                        } else {
                            $error = 'Failed to send verification email. Please try again.';
                        }
                    } else {
                        $error = 'Failed to generate new verification code.';
                    }
                } else {
                    $error = 'Email address not found or not verified.';
                }
            } catch (PDOException $e) {
                error_log("Resend password reset OTP error: " . $e->getMessage());
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
      <div class="absolute inset-0 bg-gradient-to-br from-purple-600 to-indigo-600"></div>
      <div class="absolute inset-0 bg-black bg-opacity-20"></div>
      
      <!-- Content -->
      <div class="relative z-10 flex flex-col justify-center px-12 xl:px-16 text-white">
        <div class="max-w-lg">
          <div class="text-center mb-8">
            <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fa-solid fa-<?php echo $step === 'verify' ? 'shield-check' : 'lock-open'; ?> text-4xl text-white"></i>
            </div>
          </div>
          
          <h1 class="text-4xl xl:text-5xl font-bold mb-6 leading-tight text-center">
            <?php if ($step === 'verify'): ?>
              Security <span class="text-yellow-300">Verification</span>
            <?php else: ?>
              Create New <span class="text-yellow-300">Password</span>
            <?php endif; ?>
          </h1>
          
          <?php if ($step === 'verify'): ?>
          <p class="text-xl xl:text-2xl text-purple-100 mb-8 leading-relaxed text-center">
            We've sent a secure verification code to your email. This ensures only you can reset your password.
          </p>
          
          <!-- Verification Steps -->
          <div class="space-y-4">
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-envelope text-purple-800 text-sm"></i>
              </div>
              <span class="text-lg">Check your email for the code</span>
            </div>
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-key text-purple-800 text-sm"></i>
              </div>
              <span class="text-lg">Enter the 6-digit verification code</span>
            </div>
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0 w-8 h-8 bg-purple-300 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-lock text-purple-800 text-sm"></i>
              </div>
              <span class="text-lg">Set your new secure password</span>
            </div>
          </div>
          <?php else: ?>
          <p class="text-xl xl:text-2xl text-purple-100 mb-8 leading-relaxed text-center">
            You're almost done! Create a strong, secure password for your account.
          </p>
          
          <!-- Password Requirements -->
          <div class="space-y-3">
            <h3 class="text-lg font-semibold text-yellow-300">Password Requirements:</h3>
            <div class="space-y-2 text-sm">
              <div class="flex items-center space-x-2">
                <i class="fa-solid fa-check text-green-400"></i>
                <span>At least 6 characters long</span>
              </div>
              <div class="flex items-center space-x-2">
                <i class="fa-solid fa-check text-green-400"></i>
                <span>Mix of letters and numbers</span>
              </div>
              <div class="flex items-center space-x-2">
                <i class="fa-solid fa-check text-green-400"></i>
                <span>Avoid common passwords</span>
              </div>
            </div>
          </div>
          <?php endif; ?>
          
          <!-- Security Notice -->
          <div class="mt-12 bg-white bg-opacity-10 rounded-xl p-6">
            <h3 class="text-lg font-semibold mb-2 flex items-center">
              <i class="fa-solid fa-shield-check mr-2 text-yellow-300"></i>
              Secure Process
            </h3>
            <p class="text-purple-100 text-sm">
              <?php if ($step === 'verify'): ?>
              Your verification code expires in 10 minutes for security. If you don't receive the email, check your spam folder.
              <?php else: ?>
              Your new password will be encrypted and stored securely. We never store passwords in plain text.
              <?php endif; ?>
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
            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fa-solid fa-<?php echo $step === 'verify' ? 'shield-check' : 'lock-open'; ?> text-purple-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-neutral-900 mb-1">
              <?php echo $step === 'verify' ? 'Verify Reset Code' : 'Set New Password'; ?>
            </h2>
            <p class="text-neutral-600 text-sm">
              <?php if ($step === 'verify'): ?>
                Enter the 6-digit code sent to<br>
                <span class="font-medium text-neutral-900"><?php echo e($email); ?></span>
              <?php else: ?>
                Create a strong, secure password for your account
              <?php endif; ?>
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

          <?php if ($step === 'verify'): ?>
          <!-- Verification Form -->
          <form method="POST" action="" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div>
              <label for="otp" class="block text-sm font-medium text-neutral-700 mb-3 text-center">Verification Code</label>
              <div class="relative">
                <input type="text" id="otp" name="otp" required maxlength="6" 
                       class="w-full text-center text-2xl font-mono tracking-widest py-4 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="000000"
                       autocomplete="one-time-code">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                  <i class="fa-solid fa-key text-neutral-400"></i>
                </div>
              </div>
              <p class="text-xs text-neutral-500 mt-2 text-center">Enter the 6-digit code from your email</p>
            </div>

            <button type="submit" name="verify_otp" 
                    class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-4 rounded-lg transition-colors shadow-lg hover:shadow-glow">
              <i class="fa-solid fa-check-circle mr-2"></i>
              Verify Code
            </button>
          </form>

          <?php else: ?>
          <!-- Password Reset Form -->
          <form method="POST" action="" class="space-y-6" id="passwordForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="token" value="<?php echo e($_GET['token'] ?? ''); ?>">
            
            <div>
              <label for="new_password" class="block text-sm font-medium text-neutral-700 mb-2">New Password</label>
              <div class="relative">
                <input type="password" id="new_password" name="new_password" required minlength="6"
                       class="w-full pl-10 pr-10 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Enter your new password">
                <i class="fa-solid fa-lock absolute left-3 top-4 text-neutral-400"></i>
                <button type="button" id="toggleNewPassword" class="absolute right-3 top-4 text-neutral-400 hover:text-neutral-600">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
              <div id="passwordStrength" class="mt-2 text-xs"></div>
            </div>

            <div>
              <label for="confirm_password" class="block text-sm font-medium text-neutral-700 mb-2">Confirm New Password</label>
              <div class="relative">
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                       class="w-full pl-10 pr-10 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Confirm your new password">
                <i class="fa-solid fa-lock absolute left-3 top-4 text-neutral-400"></i>
                <button type="button" id="toggleConfirmPassword" class="absolute right-3 top-4 text-neutral-400 hover:text-neutral-600">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
              <div id="passwordMatch" class="mt-2 text-xs"></div>
            </div>

            <button type="submit" name="reset_password" id="submitBtn"
                    class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-4 rounded-lg transition-colors shadow-lg hover:shadow-glow disabled:opacity-50 disabled:cursor-not-allowed">
              <i class="fa-solid fa-key mr-2"></i>
              Reset Password
            </button>
          </form>
          <?php endif; ?>

          <!-- Back to Login -->
          <div class="border-t border-neutral-200 pt-6">
            <div class="text-center">
              <p class="text-sm text-neutral-600 mb-3">
                Remember your password?
              </p>
              <a href="<?php echo BASE_URL; ?>/login.php" 
                 class="text-purple-600 hover:text-purple-700 font-medium text-sm border border-purple-200 hover:border-purple-300 px-4 py-2 rounded-lg transition-colors inline-flex items-center space-x-1">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Login</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Auto-format OTP input
const otpInput = document.getElementById('otp');
if (otpInput) {
  otpInput.addEventListener('input', function(e) {
    // Remove any non-digits
    let value = e.target.value.replace(/\D/g, '');
    
    // Limit to 6 digits
    if (value.length > 6) {
      value = value.substring(0, 6);
    }
    
    e.target.value = value;
    
    // Auto-submit when 6 digits are entered
    if (value.length === 6) {
      setTimeout(() => {
        e.target.form.submit();
      }, 500);
    }
  });

  // Focus on OTP input when page loads
  document.addEventListener('DOMContentLoaded', function() {
    otpInput.focus();
  });

  // Handle paste events
  otpInput.addEventListener('paste', function(e) {
    e.preventDefault();
    let paste = (e.clipboardData || window.clipboardData).getData('text');
    let digits = paste.replace(/\D/g, '').substring(0, 6);
    this.value = digits;
    
    if (digits.length === 6) {
      setTimeout(() => {
        this.form.submit();
      }, 500);
    }
  });
}

// Password form functionality
const passwordForm = document.getElementById('passwordForm');
if (passwordForm) {
  const newPassword = document.getElementById('new_password');
  const confirmPassword = document.getElementById('confirm_password');
  const submitBtn = document.getElementById('submitBtn');
  const passwordStrength = document.getElementById('passwordStrength');
  const passwordMatch = document.getElementById('passwordMatch');

  // Password visibility toggles
  document.getElementById('toggleNewPassword').addEventListener('click', function() {
    const input = document.getElementById('new_password');
    const icon = this.querySelector('i');
    
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'fa-solid fa-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'fa-solid fa-eye';
    }
  });

  document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
    const input = document.getElementById('confirm_password');
    const icon = this.querySelector('i');
    
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'fa-solid fa-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'fa-solid fa-eye';
    }
  });

  // Password strength checker
  newPassword.addEventListener('input', function() {
    const password = this.value;
    let strength = 0;
    let feedback = [];

    if (password.length >= 6) strength++;
    else feedback.push('At least 6 characters');

    if (/[a-z]/.test(password)) strength++;
    else feedback.push('Lowercase letter');

    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('Uppercase letter');

    if (/[0-9]/.test(password)) strength++;
    else feedback.push('Number');

    if (/[^A-Za-z0-9]/.test(password)) strength++;
    else feedback.push('Special character');

    // Update strength indicator
    let strengthText = '';
    let strengthClass = '';

    if (password.length === 0) {
      strengthText = '';
    } else if (strength < 2) {
      strengthText = 'Weak password';
      strengthClass = 'text-red-600';
    } else if (strength < 4) {
      strengthText = 'Medium password';
      strengthClass = 'text-yellow-600';
    } else {
      strengthText = 'Strong password';
      strengthClass = 'text-green-600';
    }

    passwordStrength.innerHTML = strengthText ? `<span class="${strengthClass}">${strengthText}</span>` : '';
    
    checkPasswordMatch();
  });

  // Password match checker
  confirmPassword.addEventListener('input', checkPasswordMatch);

  function checkPasswordMatch() {
    const password = newPassword.value;
    const confirm = confirmPassword.value;

    if (confirm.length === 0) {
      passwordMatch.innerHTML = '';
      submitBtn.disabled = false;
      return;
    }

    if (password === confirm) {
      passwordMatch.innerHTML = '<span class="text-green-600">Passwords match</span>';
      submitBtn.disabled = false;
    } else {
      passwordMatch.innerHTML = '<span class="text-red-600">Passwords do not match</span>';
      submitBtn.disabled = true;
    }
  }

  // Focus on password input when page loads
  document.addEventListener('DOMContentLoaded', function() {
    newPassword.focus();
  });
}
</script>

<?php include 'includes/footer.php'; ?>
