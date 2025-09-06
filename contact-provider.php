<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/ImageUploader.php';

$db = getDB();
$currentUser = $auth->getCurrentUser();

// Require user to be logged in
if (!$currentUser) {
    setFlashMessage('error', 'Please log in to contact providers.');
    redirect(BASE_URL . '/login.php');
}

$providerId = (int)($_GET['id'] ?? 0);

if (!$providerId) {
    setFlashMessage('error', 'Provider not found.');
    redirect(BASE_URL . '/services.php');
}

// Get provider details
try {
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email, u.phone, u.profile_photo,
               c.name as category_name, c.icon as category_icon, c.slug as category_slug
        FROM providers p 
        JOIN users u ON p.user_id = u.id 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ? AND p.is_active = 1
    ");
    $stmt->execute([$providerId]);
    $provider = $stmt->fetch();
    
    if (!$provider) {
        setFlashMessage('error', 'Provider not found.');
        redirect(BASE_URL . '/services.php');
    }
    
    error_log("Contact provider loaded for ID: " . $providerId . " - " . $provider['first_name'] . " " . $provider['last_name']);
} catch (PDOException $e) {
    error_log("Database error in contact-provider.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading the provider.');
    redirect(BASE_URL . '/services.php');
}

// Check if user is trying to contact themselves
if ($currentUser['id'] == $provider['user_id']) {
    setFlashMessage('error', 'You cannot contact yourself.');
    redirect(BASE_URL . '/provider-profile.php?id=' . $providerId);
}

$pageTitle = 'Contact ' . $provider['first_name'] . ' ' . $provider['last_name'] . ' • ServiceLink';
$pageDescription = 'Send a message to ' . $provider['first_name'] . ' for ' . $provider['category_name'] . ' services.';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $contactMethod = $_POST['contact_method'] ?? 'email';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!validateCSRFToken($csrfToken)) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
    } elseif (empty($subject) || empty($message)) {
        setFlashMessage('error', 'Please fill in all required fields.');
    } elseif (strlen($message) < 10) {
        setFlashMessage('error', 'Your message must be at least 10 characters long.');
    } elseif (strlen($message) > 1000) {
        setFlashMessage('error', 'Your message must be less than 1000 characters.');
    } else {
        try {
            // Insert message into database
            $stmt = $db->prepare("
                INSERT INTO messages (sender_id, recipient_id, provider_id, subject, message, contact_method, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $currentUser['id'],
                $provider['user_id'],
                $providerId,
                $subject,
                $message,
                $contactMethod
            ]);
            
            setFlashMessage('success', 'Your message has been sent successfully! The provider will contact you soon.');
            redirect(BASE_URL . '/provider-profile.php?id=' . $providerId);
            
        } catch (PDOException $e) {
            setFlashMessage('error', 'An error occurred while sending your message. Please try again.');
        }
    }
}

// Include header after processing
include 'includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-neutral-50 to-neutral-100 py-8 sm:py-12">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- Header Section -->
    <div class="text-center mb-8">
      <nav class="flex items-center justify-center space-x-2 text-sm text-neutral-500 mb-4">
        <a href="<?php echo BASE_URL; ?>/index.php" class="hover:text-primary-600 transition-colors">Home</a>
        <i class="fa-solid fa-chevron-right text-xs"></i>
        <a href="<?php echo BASE_URL; ?>/services.php" class="hover:text-primary-600 transition-colors">Services</a>
        <i class="fa-solid fa-chevron-right text-xs"></i>
        <a href="<?php echo BASE_URL; ?>/provider-profile.php?id=<?php echo $provider['id']; ?>" class="hover:text-primary-600 transition-colors">Provider Profile</a>
        <i class="fa-solid fa-chevron-right text-xs"></i>
        <span class="text-primary-600">Contact</span>
      </nav>
      
      <h1 class="text-3xl sm:text-4xl font-bold text-neutral-900 mb-4">
        Contact Service Provider
      </h1>
      <p class="text-lg text-neutral-600 max-w-2xl mx-auto">
        Send a message to connect with this professional and discuss your service needs.
      </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- Provider Information Card -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-lg border border-neutral-200 overflow-hidden sticky top-8">
          
          <!-- Provider Header -->
          <div class="relative bg-gradient-to-br from-primary-500 to-secondary-500 p-6 text-white">
            <div class="flex items-center space-x-4">
              <!-- Profile Photo -->
              <div class="relative">
                <div class="w-16 h-16 rounded-xl overflow-hidden border-3 border-white shadow-lg">
                  <img src="<?php echo e(ImageUploader::getProfileImageUrl($provider['profile_photo'])); ?>" 
                       alt="<?php echo e($provider['first_name'] . ' ' . $provider['last_name']); ?>" 
                       class="w-full h-full object-cover">
                </div>
                <?php if ($provider['is_verified']): ?>
                <div class="absolute -bottom-1 -right-1 bg-green-500 rounded-lg p-1 border-2 border-white shadow-md">
                  <i class="fa-solid fa-check text-white text-xs"></i>
                </div>
                <?php endif; ?>
              </div>
              
              <!-- Provider Info -->
              <div class="flex-1">
                <h3 class="text-xl font-bold text-white mb-1">
                  <?php echo e($provider['first_name'] . ' ' . $provider['last_name']); ?>
                </h3>
                <div class="flex items-center space-x-2 text-primary-100">
                  <i class="<?php echo e($provider['category_icon']); ?> text-sm"></i>
                  <span class="text-sm font-medium"><?php echo e($provider['category_name']); ?></span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Provider Details -->
          <div class="p-6 space-y-4">
            
            <!-- Business Name -->
            <?php if (!empty($provider['business_name'])): ?>
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-building text-primary-600 text-sm"></i>
              </div>
              <div>
                <p class="text-sm text-neutral-500">Business</p>
                <p class="font-medium text-neutral-900"><?php echo e($provider['business_name']); ?></p>
              </div>
            </div>
            <?php endif; ?>
            
            <!-- Location -->
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-location-dot text-primary-600 text-sm"></i>
              </div>
              <div>
                <p class="text-sm text-neutral-500">Location</p>
                <p class="font-medium text-neutral-900"><?php echo e($provider['location']); ?></p>
              </div>
            </div>
            
            <!-- Experience -->
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-award text-primary-600 text-sm"></i>
              </div>
              <div>
                <p class="text-sm text-neutral-500">Experience</p>
                <p class="font-medium text-neutral-900"><?php echo $provider['experience_years']; ?>+ years</p>
              </div>
            </div>
            
            <!-- Hourly Rate -->
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 bg-secondary-100 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-tag text-secondary-600 text-sm"></i>
              </div>
              <div>
                <p class="text-sm text-neutral-500">Rate</p>
                <p class="font-medium text-neutral-900"><?php echo formatCurrency($provider['hourly_rate']); ?>/hr</p>
              </div>
            </div>
            
            <!-- Rating -->
            <?php if ($provider['review_count'] > 0): ?>
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-star text-yellow-600 text-sm"></i>
              </div>
              <div>
                <p class="text-sm text-neutral-500">Rating</p>
                <div class="flex items-center space-x-2">
                  <span class="font-medium text-neutral-900"><?php echo number_format($provider['rating'], 1); ?></span>
                  <span class="text-sm text-neutral-500">(<?php echo $provider['review_count']; ?> reviews)</span>
                </div>
              </div>
            </div>
            <?php endif; ?>
            
          </div>
          
          <!-- Back Button -->
          <div class="p-6 pt-0">
            <a href="<?php echo BASE_URL; ?>/provider-profile.php?id=<?php echo $provider['id']; ?>" 
               class="w-full bg-neutral-100 hover:bg-neutral-200 text-neutral-700 px-4 py-3 rounded-lg transition-colors font-medium text-center flex items-center justify-center space-x-2">
              <i class="fa-solid fa-arrow-left"></i>
              <span>Back to Profile</span>
            </a>
          </div>
          
        </div>
      </div>
      
      <!-- Contact Form -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-lg border border-neutral-200 p-8">
          
          <!-- Form Header -->
          <div class="text-center mb-8">
            <div class="w-16 h-16 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fa-solid fa-envelope text-2xl text-white"></i>
            </div>
            <h2 class="text-2xl font-bold text-neutral-900 mb-2">Send a Message</h2>
            <p class="text-neutral-600">
              Describe your project or ask questions about their services.
            </p>
          </div>
          
          <!-- Contact Form -->
          <form method="POST" action="" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <!-- Subject -->
            <div>
              <label for="subject" class="block text-sm font-medium text-neutral-700 mb-2">Subject *</label>
              <input type="text" id="subject" name="subject" required maxlength="200"
                     value="<?php echo e($_POST['subject'] ?? ''); ?>"
                     placeholder="Brief description of your inquiry"
                     class="w-full px-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
            </div>
            
            <!-- Message -->
            <div>
              <label for="message" class="block text-sm font-medium text-neutral-700 mb-2">Message *</label>
              <textarea id="message" name="message" rows="6" required maxlength="1000"
                        placeholder="Describe your project, timeline, budget expectations, and any specific questions you have..."
                        class="w-full px-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors resize-none"><?php echo e($_POST['message'] ?? ''); ?></textarea>
              <div class="mt-2 flex justify-between text-sm text-neutral-500">
                <span>Minimum 10 characters</span>
                <span id="charCount">0/1000</span>
              </div>
            </div>
            
            <!-- Contact Method Preference -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-3">How would you prefer to be contacted?</label>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label class="flex items-center p-3 border border-neutral-300 rounded-lg cursor-pointer hover:border-primary-300 hover:bg-primary-50 transition-colors">
                  <input type="radio" name="contact_method" value="email" checked class="sr-only">
                  <div class="w-4 h-4 border-2 border-primary-500 rounded-full mr-3 flex items-center justify-center">
                    <div class="w-2 h-2 bg-primary-500 rounded-full hidden"></div>
                  </div>
                  <div class="flex items-center space-x-2">
                    <i class="fa-solid fa-envelope text-primary-600"></i>
                    <span class="text-sm font-medium text-neutral-700">Email</span>
                  </div>
                </label>
                
                <label class="flex items-center p-3 border border-neutral-300 rounded-lg cursor-pointer hover:border-primary-300 hover:bg-primary-50 transition-colors">
                  <input type="radio" name="contact_method" value="phone" class="sr-only">
                  <div class="w-4 h-4 border-2 border-neutral-300 rounded-full mr-3 flex items-center justify-center">
                    <div class="w-2 h-2 bg-primary-500 rounded-full hidden"></div>
                  </div>
                  <div class="flex items-center space-x-2">
                    <i class="fa-solid fa-phone text-primary-600"></i>
                    <span class="text-sm font-medium text-neutral-700">Phone</span>
                  </div>
                </label>
                
                <label class="flex items-center p-3 border border-neutral-300 rounded-lg cursor-pointer hover:border-primary-300 hover:bg-primary-50 transition-colors">
                  <input type="radio" name="contact_method" value="both" class="sr-only">
                  <div class="w-4 h-4 border-2 border-neutral-300 rounded-full mr-3 flex items-center justify-center">
                    <div class="w-2 h-2 bg-primary-500 rounded-full hidden"></div>
                  </div>
                  <div class="flex items-center space-x-2">
                    <i class="fa-solid fa-comments text-primary-600"></i>
                    <span class="text-sm font-medium text-neutral-700">Either</span>
                  </div>
                </label>
              </div>
            </div>
            
            <!-- Submit Button -->
            <div class="pt-4">
              <button type="submit" 
                      class="w-full bg-gradient-to-r from-primary-600 to-secondary-600 hover:from-primary-700 hover:to-secondary-700 text-white px-6 py-4 rounded-lg transition-all duration-300 font-semibold text-lg shadow-lg hover:shadow-xl transform hover:scale-[1.02] flex items-center justify-center space-x-3">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Send Message</span>
              </button>
            </div>
            
          </form>
          
          <!-- Contact Tips -->
          <div class="mt-8 p-6 bg-gradient-to-r from-primary-50 to-secondary-50 rounded-xl border border-primary-100">
            <h3 class="text-lg font-semibold text-neutral-900 mb-3 flex items-center">
              <i class="fa-solid fa-lightbulb text-primary-600 mr-2"></i>
              Tips for Better Communication
            </h3>
            <ul class="space-y-2 text-sm text-neutral-700">
              <li class="flex items-start space-x-2">
                <i class="fa-solid fa-check text-primary-600 mt-0.5 text-xs"></i>
                <span>Be specific about your project requirements and timeline</span>
              </li>
              <li class="flex items-start space-x-2">
                <i class="fa-solid fa-check text-primary-600 mt-0.5 text-xs"></i>
                <span>Mention your budget range if you're comfortable sharing</span>
              </li>
              <li class="flex items-start space-x-2">
                <i class="fa-solid fa-check text-primary-600 mt-0.5 text-xs"></i>
                <span>Ask about availability and how soon they can start</span>
              </li>
              <li class="flex items-start space-x-2">
                <i class="fa-solid fa-check text-primary-600 mt-0.5 text-xs"></i>
                <span>Include any relevant photos or documents if needed</span>
              </li>
            </ul>
          </div>
          
        </div>
      </div>
      
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Character count for message textarea
  const messageTextarea = document.getElementById('message');
  const charCount = document.getElementById('charCount');
  
  if (messageTextarea && charCount) {
    messageTextarea.addEventListener('input', function() {
      const count = this.value.length;
      charCount.textContent = count + '/1000';
      
      if (count > 1000) {
        charCount.classList.add('text-red-500');
      } else {
        charCount.classList.remove('text-red-500');
      }
    });
    
    // Initial count
    const initialCount = messageTextarea.value.length;
    charCount.textContent = initialCount + '/1000';
  }
  
  // Custom radio button styling
  const radioInputs = document.querySelectorAll('input[type="radio"][name="contact_method"]');
  radioInputs.forEach(function(radio) {
    radio.addEventListener('change', function() {
      // Reset all radio buttons
      radioInputs.forEach(function(r) {
        const dot = r.parentElement.querySelector('.w-2.h-2');
        const border = r.parentElement.querySelector('.w-4.h-4');
        dot.classList.add('hidden');
        border.classList.remove('border-primary-500');
        border.classList.add('border-neutral-300');
      });
      
      // Activate selected radio button
      if (this.checked) {
        const dot = this.parentElement.querySelector('.w-2.h-2');
        const border = this.parentElement.querySelector('.w-4.h-4');
        dot.classList.remove('hidden');
        border.classList.add('border-primary-500');
        border.classList.remove('border-neutral-300');
      }
    });
    
    // Check initial state
    if (radio.checked) {
      const dot = radio.parentElement.querySelector('.w-2.h-2');
      const border = radio.parentElement.querySelector('.w-4.h-4');
      dot.classList.remove('hidden');
      border.classList.add('border-primary-500');
      border.classList.remove('border-neutral-300');
    }
  });
  
  // Form validation
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function(e) {
      const subject = document.getElementById('subject').value.trim();
      const message = document.getElementById('message').value.trim();
      
      if (!subject || !message) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
      }
      
      if (message.length < 10) {
        e.preventDefault();
        alert('Your message must be at least 10 characters long.');
        return false;
      }
      
      if (message.length > 1000) {
        e.preventDefault();
        alert('Your message must be less than 1000 characters.');
        return false;
      }
    });
  }
});
</script>

<?php include 'includes/footer.php'; ?>
