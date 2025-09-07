<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/VerificationManager.php';

// Check if user is logged in and is a provider
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'provider') {
    redirect(BASE_URL . '/login.php');
}

$verificationManager = new VerificationManager();
$currentUser = $auth->getCurrentUser();
$userId = $currentUser['id'];

$pageTitle = 'ID Verification • ServiceLink';
$pageDescription = 'Verify your identity to become a trusted provider';

$message = '';
$messageType = '';

// Get current verification status
$verificationStatus = $verificationManager->getVerificationStatus($userId);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_verification'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'error';
    } else {
        $frontImage = $_FILES['id_front'] ?? null;
        $backImage = $_FILES['id_back'] ?? null;
        $notes = trim($_POST['notes'] ?? '');
        
        // Validate required files
        if (!$frontImage || !$backImage || 
            !isset($frontImage['tmp_name']) || !isset($backImage['tmp_name']) ||
            !is_uploaded_file($frontImage['tmp_name']) || !is_uploaded_file($backImage['tmp_name'])) {
            $message = 'Please upload both front and back images of your ID';
            $messageType = 'error';
        } else {
            $result = $verificationManager->submitIdVerification($userId, $frontImage, $backImage, $notes);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            
            if ($result['success']) {
                // Refresh verification status
                $verificationStatus = $verificationManager->getVerificationStatus($userId);
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-blue-900 to-blue-700">
  <div class="min-h-screen flex">
    <!-- Left Side Decorative (hidden on mobile) -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-blue-900 to-blue-700"></div>
      <div class="absolute inset-0 bg-black bg-opacity-20"></div>
      <div class="absolute top-10 right-10 w-32 h-32 bg-white bg-opacity-10 rounded-full"></div>
      <div class="absolute bottom-10 left-10 w-24 h-24 bg-blue-300 bg-opacity-20 rounded-full"></div>
      <div class="absolute top-1/2 right-1/4 w-16 h-16 bg-blue-400 bg-opacity-15 rounded-full"></div>
      <div class="relative z-10 flex flex-col justify-center px-12 xl:px-16 text-white">
        <div class="max-w-lg">
          <h1 class="text-4xl xl:text-5xl font-bold mb-6 leading-tight">
            ID Verification
          </h1>
          <p class="text-xl xl:text-2xl text-blue-100 mb-8 leading-relaxed">
            Verify your identity to build trust with customers and become a certified provider.
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
            <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-100 rounded-full mb-4">
                <i class="fa-solid fa-id-card text-primary-600 text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-neutral-900 mb-2">ID Verification</h1>
            <p class="text-neutral-600 max-w-2xl mx-auto">
                Verify your identity to build trust with customers and become a certified provider
            </p>
        </div>

        <!-- Status Messages -->
        <?php if ($message): ?>
        <div class="mb-6">
            <div class="<?php echo $messageType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> border px-4 py-3 rounded-lg">
                <?php echo e($message); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Verification Status -->
        <div class="bg-white rounded-xl shadow-lg border border-neutral-200 mb-8">
            <div class="p-6">
                <h2 class="text-xl font-semibold text-neutral-900 mb-4">Current Status</h2>
                
                <?php
                $status = $verificationStatus['id_verification_status'] ?? 'not_submitted';
                $statusConfig = [
                    'not_submitted' => ['text' => 'Not Submitted', 'color' => 'text-neutral-500', 'bg' => 'bg-neutral-100', 'icon' => 'fa-clock'],
                    'pending' => ['text' => 'Pending Review', 'color' => 'text-yellow-600', 'bg' => 'bg-yellow-100', 'icon' => 'fa-hourglass-half'],
                    'approved' => ['text' => 'Verified', 'color' => 'text-green-600', 'bg' => 'bg-green-100', 'icon' => 'fa-check-circle'],
                    'rejected' => ['text' => 'Rejected', 'color' => 'text-red-600', 'bg' => 'bg-red-100', 'icon' => 'fa-times-circle']
                ];
                $config = $statusConfig[$status];
                ?>
                
                <div class="inline-flex items-center px-4 py-2 rounded-full <?php echo $config['bg']; ?>">
                    <i class="fa-solid <?php echo $config['icon']; ?> <?php echo $config['color']; ?> mr-2"></i>
                    <span class="<?php echo $config['color']; ?> font-medium"><?php echo $config['text']; ?></span>
                </div>
                
                <?php if ($status === 'pending'): ?>
                <p class="text-neutral-600 mt-4">
                    Your ID verification is currently being reviewed by our team. You will be notified via email once the review is complete.
                </p>
                <?php elseif ($status === 'approved'): ?>
                <p class="text-green-600 mt-4">
                    <i class="fa-solid fa-check mr-2"></i>
                    Your identity has been successfully verified! You are now a trusted provider.
                </p>
                <?php elseif ($status === 'rejected'): ?>
                <p class="text-red-600 mt-4">
                    Your ID verification was rejected. Please review the requirements and submit again with valid documents.
                </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($status !== 'approved' && $status !== 'pending'): ?>
        <!-- Verification Form -->
        <div class="bg-white rounded-xl shadow-lg border border-neutral-200">
            <div class="p-6">
                <h2 class="text-xl font-semibold text-neutral-900 mb-6">Submit ID Documents</h2>
                
                <!-- Requirements -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h3 class="text-blue-900 font-medium mb-2">
                        <i class="fa-solid fa-info-circle mr-2"></i>
                        Requirements
                    </h3>
                    <ul class="text-blue-800 text-sm space-y-1">
                        <li>• Upload clear photos of both front and back of your ID</li>
                        <li>• Accepted documents: National ID, Driver's License, Passport</li>
                        <li>• Images must be in JPEG or PNG format</li>
                        <li>• Maximum file size: 5MB per image</li>
                        <li>• Ensure all text is clearly readable</li>
                    </ul>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- Front Image -->
                    <div>
                        <label for="id_front" class="block text-sm font-medium text-neutral-700 mb-2">
                            ID Front Image *
                        </label>
                        <div class="border-2 border-dashed border-neutral-300 rounded-lg p-6 text-center hover:border-primary-400 transition-colors">
                            <input type="file" id="id_front" name="id_front" accept="image/*" required
                                   class="hidden" onchange="previewImage(this, 'front-preview')">
                            <div id="front-preview" class="space-y-4">
                                <i class="fa-solid fa-cloud-upload-alt text-neutral-400 text-3xl"></i>
                                <div>
                                    <button type="button" onclick="document.getElementById('id_front').click()"
                                            class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors">
                                        Choose Front Image
                                    </button>
                                    <p class="text-neutral-500 text-sm mt-2">PNG, JPG up to 5MB</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Back Image -->
                    <div>
                        <label for="id_back" class="block text-sm font-medium text-neutral-700 mb-2">
                            ID Back Image *
                        </label>
                        <div class="border-2 border-dashed border-neutral-300 rounded-lg p-6 text-center hover:border-primary-400 transition-colors">
                            <input type="file" id="id_back" name="id_back" accept="image/*" required
                                   class="hidden" onchange="previewImage(this, 'back-preview')">
                            <div id="back-preview" class="space-y-4">
                                <i class="fa-solid fa-cloud-upload-alt text-neutral-400 text-3xl"></i>
                                <div>
                                    <button type="button" onclick="document.getElementById('id_back').click()"
                                            class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors">
                                        Choose Back Image
                                    </button>
                                    <p class="text-neutral-500 text-sm mt-2">PNG, JPG up to 5MB</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="notes" class="block text-sm font-medium text-neutral-700 mb-2">
                            Additional Notes (Optional)
                        </label>
                        <textarea id="notes" name="notes" rows="3"
                                  class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                  placeholder="Any additional information about your ID documents..."></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button type="submit" name="submit_verification"
                                class="bg-primary-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors flex items-center space-x-2">
                            <i class="fa-solid fa-upload"></i>
                            <span>Submit for Verification</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Security Notice -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-6">
            <div class="flex items-start space-x-3">
                <i class="fa-solid fa-shield-alt text-yellow-600 mt-1"></i>
                <div>
                    <h3 class="text-yellow-900 font-medium">Security & Privacy</h3>
                    <p class="text-yellow-800 text-sm mt-1">
                        Your ID documents are encrypted and stored securely. They are only used for verification purposes 
                        and will not be shared with customers or third parties.
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
function previewImage(input, previewId) {
    const file = input.files[0];
    const preview = document.getElementById(previewId);
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="space-y-4">
                    <img src="${e.target.result}" alt="Preview" class="max-w-full h-48 object-contain mx-auto rounded-lg">
                    <div>
                        <p class="text-green-600 font-medium">${file.name}</p>
                        <p class="text-neutral-500 text-sm">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                        <button type="button" onclick="document.getElementById('${input.id}').click()"
                                class="text-primary-600 hover:text-primary-700 text-sm mt-2">
                            Change Image
                        </button>
                    </div>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
