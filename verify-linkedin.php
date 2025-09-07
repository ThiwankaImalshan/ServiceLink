<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/VerificationManager.php';

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

$verificationManager = new VerificationManager();
$currentUser = $auth->getCurrentUser();
$userId = $currentUser['id'];

$pageTitle = 'LinkedIn Verification • ServiceLink';
$pageDescription = 'Connect and verify your LinkedIn profile';

$message = '';
$messageType = '';

// Get current verification status
$verificationStatus = $verificationManager->getVerificationStatus($userId);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_linkedin'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'error';
    } else {
        $linkedinUrl = trim($_POST['linkedin_url'] ?? '');
        
        if (empty($linkedinUrl)) {
            $message = 'Please enter your LinkedIn profile URL';
            $messageType = 'error';
        } else {
            $result = $verificationManager->submitLinkedInVerification($userId, $linkedinUrl);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            
            if ($result['success']) {
                // Refresh verification status
                $verificationStatus = $verificationManager->getVerificationStatus($userId);
                $verificationUrl = $result['verification_url'] ?? '';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-blue-100 to-blue-300">
  <div class="min-h-screen flex">
    <!-- Left Side Decorative (hidden on mobile) -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-blue-600 to-blue-400"></div>
      <div class="absolute inset-0 bg-black bg-opacity-20"></div>
      <div class="absolute top-10 right-10 w-32 h-32 bg-white bg-opacity-10 rounded-full"></div>
      <div class="absolute bottom-10 left-10 w-24 h-24 bg-blue-300 bg-opacity-20 rounded-full"></div>
      <div class="absolute top-1/2 right-1/4 w-16 h-16 bg-blue-400 bg-opacity-15 rounded-full"></div>
      <div class="relative z-10 flex flex-col justify-center px-12 xl:px-16 text-white">
        <div class="max-w-lg">
          <h1 class="text-4xl xl:text-5xl font-bold mb-6 leading-tight">
            LinkedIn Verification
          </h1>
          <p class="text-xl xl:text-2xl text-blue-100 mb-8 leading-relaxed">
            Connect your LinkedIn profile to build professional credibility and trust.
          </p>
        </div>
      </div>
    </div>
    <!-- Right Side - Form Card -->
    <div class="w-full lg:w-1/2 flex items-center justify-center py-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-xl w-full bg-white rounded-2xl shadow-xl border border-neutral-100 overflow-hidden">
        <div class="p-8 space-y-8">
          <!-- Header -->
          <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
              <i class="fa-brands fa-linkedin text-blue-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-neutral-900 mb-2">LinkedIn Verification</h2>
            <p class="text-neutral-600 max-w-2xl mx-auto text-sm">
              Connect your LinkedIn profile to build professional credibility and trust.
            </p>
          </div>

          <!-- Status Messages -->
          <?php if ($message): ?>
          <div class="mb-6">
              <div class="<?php echo $messageType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> border px-4 py-3 rounded-lg">
                  <?php echo e($message); ?>
                  
                  <?php if ($messageType === 'success' && isset($verificationUrl)): ?>
                  <div class="mt-3 p-3 bg-white rounded border">
                      <p class="text-sm text-neutral-600 mb-2">
                          <strong>Next Step:</strong> Visit the verification URL to complete the process:
                      </p>
                      <a href="<?php echo e($verificationUrl); ?>" target="_blank" 
                         class="text-blue-600 hover:text-blue-800 text-sm break-all">
                          <?php echo e($verificationUrl); ?>
                      </a>
                  </div>
                  <?php endif; ?>
              </div>
          </div>
          <?php endif; ?>

          <!-- Verification Status -->
          <div class="bg-white rounded-xl shadow-lg border border-neutral-200 mb-8">
              <div class="p-6">
                  <h2 class="text-xl font-semibold text-neutral-900 mb-4">Current Status</h2>
                  
                  <?php
                  $status = $verificationStatus['linkedin_verification_status'] ?? 'not_submitted';
                  $statusConfig = [
                      'not_submitted' => ['text' => 'Not Connected', 'color' => 'text-neutral-500', 'bg' => 'bg-neutral-100', 'icon' => 'fa-unlink'],
                      'pending' => ['text' => 'Pending Verification', 'color' => 'text-yellow-600', 'bg' => 'bg-yellow-100', 'icon' => 'fa-hourglass-half'],
                      'verified' => ['text' => 'Verified', 'color' => 'text-green-600', 'bg' => 'bg-green-100', 'icon' => 'fa-check-circle'],
                      'rejected' => ['text' => 'Verification Failed', 'color' => 'text-red-600', 'bg' => 'bg-red-100', 'icon' => 'fa-times-circle']
                  ];
                  $config = $statusConfig[$status];
                  ?>
                  
                  <div class="flex items-center justify-between">
                      <div class="inline-flex items-center px-4 py-2 rounded-full <?php echo $config['bg']; ?>">
                          <i class="fa-solid <?php echo $config['icon']; ?> <?php echo $config['color']; ?> mr-2"></i>
                          <span class="<?php echo $config['color']; ?> font-medium"><?php echo $config['text']; ?></span>
                      </div>
                      
                      <?php if ($status === 'verified' && $verificationStatus['linkedin_profile']): ?>
                      <a href="<?php echo e($verificationStatus['linkedin_profile']); ?>" target="_blank"
                         class="text-blue-600 hover:text-blue-800 text-sm flex items-center space-x-1">
                          <i class="fa-brands fa-linkedin"></i>
                          <span>View Profile</span>
                          <i class="fa-solid fa-external-link-alt text-xs"></i>
                      </a>
                      <?php endif; ?>
                  </div>
                  
                  <?php if ($status === 'pending'): ?>
                  <p class="text-neutral-600 mt-4">
                      Your LinkedIn profile is being verified. Please ensure your LinkedIn username matches your ServiceLink profile name.
                  </p>
                  <?php elseif ($status === 'verified'): ?>
                  <p class="text-green-600 mt-4">
                      <i class="fa-solid fa-check mr-2"></i>
                      Your LinkedIn profile has been successfully verified!
                  </p>
                  <?php elseif ($status === 'rejected'): ?>
                  <p class="text-red-600 mt-4">
                      LinkedIn verification failed. Please ensure the profile URL is correct and the name matches your account.
                  </p>
                  <?php endif; ?>
              </div>
          </div>

          <?php if ($status !== 'verified' && $status !== 'pending'): ?>
          <!-- LinkedIn Connection Form -->
          <div class="bg-white rounded-xl shadow-lg border border-neutral-200">
              <div class="p-6">
                  <h2 class="text-xl font-semibold text-neutral-900 mb-6">Connect LinkedIn Profile</h2>
                  
                  <!-- Benefits -->
                  <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                      <h3 class="text-blue-900 font-medium mb-2">
                          <i class="fa-solid fa-star mr-2"></i>
                          Benefits of LinkedIn Verification
                      </h3>
                      <ul class="text-blue-800 text-sm space-y-1">
                          <li>• Increase customer trust and credibility</li>
                          <li>• Stand out with a verified professional badge</li>
                          <li>• Show your professional experience and skills</li>
                          <li>• Higher ranking in search results</li>
                          <li>• Access to premium provider features</li>
                      </ul>
                  </div>

                  <!-- Instructions -->
                  <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                      <h3 class="text-yellow-900 font-medium mb-2">
                          <i class="fa-solid fa-lightbulb mr-2"></i>
                          How it works
                      </h3>
                      <ol class="text-yellow-800 text-sm space-y-1">
                          <li>1. Enter your LinkedIn profile URL below</li>
                          <li>2. Click "Connect LinkedIn Profile"</li>
                          <li>3. You'll receive a verification link via email</li>
                          <li>4. Visit the verification link to confirm</li>
                          <li>5. Our team will verify your profile matches your account</li>
                      </ol>
                  </div>

                  <form method="POST" class="space-y-6">
                      <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                      
                      <!-- Current User Info -->
                      <div class="bg-neutral-50 rounded-lg p-4">
                          <h4 class="font-medium text-neutral-900 mb-2">Your ServiceLink Profile</h4>
                          <p class="text-neutral-600 text-sm">
                              <strong>Name:</strong> <?php echo e($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?><br>
                              <strong>Email:</strong> <?php echo e($currentUser['email']); ?>
                          </p>
                          <p class="text-xs text-neutral-500 mt-2">
                              Your LinkedIn profile name should match this information for successful verification.
                          </p>
                      </div>
                      
                      <!-- LinkedIn URL -->
                      <div>
                          <label for="linkedin_url" class="block text-sm font-medium text-neutral-700 mb-2">
                              LinkedIn Profile URL *
                          </label>
                          <div class="relative">
                              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                  <i class="fa-brands fa-linkedin text-blue-600"></i>
                              </div>
                              <input type="url" id="linkedin_url" name="linkedin_url" required
                                     value="<?php echo e($verificationStatus['linkedin_profile'] ?? ''); ?>"
                                     class="w-full pl-10 pr-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                     placeholder="https://www.linkedin.com/in/your-username">
                          </div>
                          <p class="text-neutral-500 text-xs mt-1">
                              Example: https://www.linkedin.com/in/john-doe
                          </p>
                      </div>

                      <!-- Submit Button -->
                      <div class="flex justify-end">
                          <button type="submit" name="submit_linkedin"
                                  class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors flex items-center space-x-2">
                              <i class="fa-brands fa-linkedin"></i>
                              <span>Connect LinkedIn Profile</span>
                          </button>
                      </div>
                  </form>
              </div>
          </div>
          <?php endif; ?>
          
          <!-- Privacy Notice -->
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
              <div class="flex items-start space-x-3">
                  <i class="fa-solid fa-shield-alt text-blue-600 mt-1"></i>
                  <div>
                      <h3 class="text-blue-900 font-medium">Privacy & Security</h3>
                      <p class="text-blue-800 text-sm mt-1">
                          We only use your LinkedIn profile for verification purposes. Your profile information 
                          remains private and is not shared without your consent. You can disconnect at any time.
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
// Auto-format LinkedIn URL
document.getElementById('linkedin_url').addEventListener('input', function(e) {
    let value = e.target.value;
    
    // Basic LinkedIn URL validation and formatting
    if (value && !value.startsWith('http')) {
        if (value.startsWith('linkedin.com') || value.startsWith('www.linkedin.com')) {
            e.target.value = 'https://' + value;
        } else if (!value.includes('linkedin.com')) {
            // If user just enters username, format it
            const username = value.replace(/[^a-zA-Z0-9\-]/g, '');
            if (username) {
                e.target.value = 'https://www.linkedin.com/in/' + username;
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
