<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/ImageUploader.php';
require_once 'includes/VerificationManager.php';
require_once 'includes/FavoritesManager.php';

$db = getDB();
$currentUser = $auth->getCurrentUser();
$verificationManager = new VerificationManager();
$favoritesManager = new FavoritesManager();

$pageTitle = 'Provider Profile • ServiceLink';
$pageDescription = 'View provider details and contact information.';

$providerId = (int)($_GET['id'] ?? 0);

if (!$providerId) {
    setFlashMessage('Provider not found.', 'error');
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
        setFlashMessage('Provider not found.', 'error');
        redirect(BASE_URL . '/services.php');
    }
    
    error_log("Provider profile loaded for ID: " . $providerId . " - " . $provider['first_name'] . " " . $provider['last_name']);
} catch (PDOException $e) {
    error_log("Database error in provider-profile.php: " . $e->getMessage());
    setFlashMessage('An error occurred while loading the provider.', 'error');
    redirect(BASE_URL . '/services.php');
}

// Include header after processing
include 'includes/header.php';

// Get verification status
$providerVerificationStatus = $verificationManager->getVerificationStatus($provider['user_id']);

// Get provider qualifications
try {
    $stmt = $db->prepare("SELECT * FROM qualifications WHERE provider_id = ? ORDER BY year_obtained DESC");
    $stmt->execute([$providerId]);
    $qualifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $qualifications = [];
}

// Get provider reviews
try {
    $stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.provider_id = ? 
        ORDER BY r.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$providerId]);
    $reviews = $stmt->fetchAll();
} catch (PDOException $e) {
    $reviews = [];
}

// Check if provider is in user's favorites (if user is logged in)
$isFavorite = false;
if ($currentUser && $currentUser['role'] === 'user') {
    $isFavorite = $favoritesManager->isFavorite($currentUser['id'], $providerId);
}

// Update page title
$pageTitle = ($provider['business_name'] ?: ($provider['first_name'] . ' ' . $provider['last_name'])) . ' • ServiceLink';

// Parse working days and tags
$workingDays = json_decode($provider['working_days'], true) ?: [];
$tags = json_decode($provider['tags'], true) ?: [];
?>

<div class="min-h-screen bg-gradient-to-br from-neutral-50 to-neutral-100 text-neutral-900">
  <!-- Main Content -->
  <main class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      
      <!-- Back Button -->
      <div class="mb-6">
        <a href="<?php echo BASE_URL; ?>/services.php" class="inline-flex items-center text-neutral-600 hover:text-primary-600 transition-colors font-medium">
          <i class="fa-solid fa-arrow-left mr-2"></i>
          Back to Services
        </a>
      </div>

      <!-- Profile Header -->
      <div class="bg-white rounded-2xl shadow-xl border border-neutral-200 p-4 sm:p-6 lg:p-8 mb-8">
        <div class="flex flex-col md:flex-row items-center md:items-start space-y-6 md:space-y-0 md:space-x-8">
          <!-- Profile Photo Section -->
          <div class="relative flex-shrink-0 w-full md:w-auto flex justify-center md:block">
            <div class="relative w-fit mx-auto md:mx-0">
              <img src="<?php echo e(ImageUploader::getProfileImageUrl($provider['profile_photo'])); ?>" 
                   alt="<?php echo e($provider['first_name'] . ' ' . $provider['last_name']); ?>" 
                   class="w-32 h-32 sm:w-36 sm:h-36 lg:w-40 lg:h-40 rounded-full object-cover border-4 border-white shadow-2xl ring-4 ring-primary-100" />
              
              <!-- Provider Badge -->
              <!-- <div class="absolute -top-2 -left-2 bg-gradient-to-r from-emerald-500 to-green-600 text-white px-3 py-1 rounded-full text-xs font-bold shadow-lg">
                <i class="fa-solid fa-briefcase mr-1"></i>Provider
              </div> -->
              
              <!-- Verification Badge -->
                <?php if ($providerVerificationStatus === 'verified'): ?>
                <div class="absolute bottom-2 right-2 bg-gradient-to-r from-green-400 to-green-600 text-white p-2 rounded-full shadow-lg border-2 border-white">
                  <i class="fa-solid fa-shield-halved text-sm"></i>
                </div>
                <?php endif; ?>
            </div>
          </div>

          <!-- Profile Info and Stats -->
          <div class="flex-1 min-w-0">
            <div class="flex flex-col md:block w-full space-y-6 md:space-y-0 mb-4">
              <div class="flex flex-col items-center md:items-start w-full md:max-w-none text-center md:text-left">
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-neutral-900 mb-4">
                  <?php echo e($provider['business_name'] ?: ($provider['first_name'] . ' ' . $provider['last_name'])); ?>
                </h1>
                <!-- Added Provider Badge to Info Section -->
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 text-neutral-600 text-sm sm:text-base mb-6">
                  <span class="flex items-center space-x-2">
                    <i class="fa-solid fa-location-dot text-primary-500"></i>
                    <span><?php echo e($provider['location']); ?></span>
                  </span>
                  <span class="flex items-center space-x-2">
                    <i class="fa-solid fa-user text-primary-500"></i>
                    <span>
                      <?php 
                      $gender = $provider['gender'] ?? null; // Check if 'gender' key exists
                      echo $gender ? ucfirst(str_replace('_', ' ', $gender)) : 'Not specified'; // Provide default value if null
                      ?>
                    </span>
                  </span>
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-lg">
                    <i class="fa-solid fa-briefcase mr-1"></i>Provider
                  </span>
                </div>
              </div>

              <!-- Provider Stats -->
              <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 w-full">
                <div class="bg-gradient-to-r from-blue-50 to-primary-50 p-4 rounded-xl border border-blue-100 hover:shadow-lg transition-shadow duration-300">
                  <div class="text-center">
                    <div class="text-2xl sm:text-3xl font-bold text-primary-700 mb-1">
                      <?php echo $provider['experience_years']; ?>+
                    </div>
                    <div class="text-sm text-primary-600 font-medium">Years Experience</div>
                  </div>
                </div>
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-xl border border-green-100 hover:shadow-lg transition-shadow duration-300">
                  <div class="text-center">
                    <div class="text-2xl sm:text-3xl font-bold text-emerald-700 mb-1">
                      <?php echo $provider['review_count']; ?>
                    </div>
                    <div class="text-sm text-emerald-600 font-medium">Total Reviews</div>
                  </div>
                </div>
                <div class="bg-gradient-to-r from-amber-50 to-yellow-50 p-4 rounded-xl border border-amber-100 hover:shadow-lg transition-shadow duration-300">
                  <div class="text-center">
                    <div class="text-2xl sm:text-3xl font-bold text-amber-700 mb-1">
                      <?php echo number_format($provider['rating'], 1); ?>
                    </div>
                    <div class="text-sm text-amber-600 font-medium">Average Rating</div>
                  </div>
                </div>
                <div class="bg-gradient-to-r from-purple-50 to-violet-50 p-4 rounded-xl border border-purple-100 hover:shadow-lg transition-shadow duration-300">
                  <div class="text-center">
                    <div class="text-2xl sm:text-3xl font-bold text-purple-700 mb-1">
                      <?php echo formatCurrency($provider['hourly_rate']); ?>
                    </div>
                    <div class="text-sm text-purple-600 font-medium">Hourly Rate</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="bg-white rounded-2xl shadow-lg border border-neutral-200 mb-8">
        <div class="border-b border-neutral-200 overflow-x-auto scrollbar-hide">
          <div class="max-w-7xl mx-auto">
            <nav class="flex flex-nowrap justify-start md:justify-center min-w-full px-2 sm:px-4" aria-label="Tabs">
              <button class="tab-btn border-b-2 border-primary-600 text-primary-600 py-4 px-6 sm:px-8 text-sm whitespace-nowrap font-medium flex-shrink-0 flex flex-col sm:flex-row items-center" data-tab="overview">
                <i class="fa-solid fa-chart-line text-lg sm:text-base mb-1 sm:mb-0 sm:mr-2"></i>
                <span class="text-xs sm:text-sm">Overview</span>
              </button>
              <button class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-6 sm:px-8 text-sm whitespace-nowrap font-medium transition-colors flex-shrink-0 flex flex-col sm:flex-row items-center" data-tab="reviews">
                <i class="fa-solid fa-star text-lg sm:text-base mb-1 sm:mb-0 sm:mr-2"></i>
                <span class="text-xs sm:text-sm">Reviews</span>
              </button>
              <button class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-6 sm:px-8 text-sm whitespace-nowrap font-medium transition-colors flex-shrink-0 flex flex-col sm:flex-row items-center" data-tab="qualifications">
                <i class="fa-solid fa-certificate text-lg sm:text-base mb-1 sm:mb-0 sm:mr-2"></i>
                <span class="text-xs sm:text-sm">Qualifications</span>
              </button>
              <button class="tab-btn border-b-2 border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 py-4 px-6 sm:px-8 text-sm whitespace-nowrap font-medium transition-colors flex-shrink-0 flex flex-col sm:flex-row items-center" data-tab="contact">
                <i class="fa-solid fa-phone text-lg sm:text-base mb-1 sm:mb-0 sm:mr-2"></i>
                <span class="text-xs sm:text-sm">Contact</span>
              </button>
            </nav>
          </div>
        </div>

        <!-- Tab Content -->
        <div class="p-4 sm:p-6">
          <!-- Overview Tab -->
          <div id="overview-tab" class="tab-content">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <!-- About -->
              <div>
                <h3 class="text-lg font-semibold text-neutral-900 mb-4 flex items-center">
                  <i class="fa-solid fa-user text-primary-600 mr-2"></i>
                  About
                </h3>
                <?php if (!empty($provider['description'])): ?>
                <p class="text-neutral-600 leading-relaxed mb-6">
                  <?php echo nl2br(e($provider['description'])); ?>
                </p>
                <?php else: ?>
                <p class="text-neutral-500 italic">No description provided.</p>
                <?php endif; ?>
                
                <!-- Tags/Skills -->
                <?php if (!empty($tags)): ?>
                <div class="mb-6">
                  <h4 class="text-md font-semibold text-neutral-900 mb-3">Skills & Expertise</h4>
                  <div class="flex flex-wrap gap-2">
                    <?php foreach ($tags as $tag): ?>
                    <span class="inline-flex items-center px-3 py-1 bg-primary-100 text-primary-700 rounded-full text-sm font-medium">
                      <?php echo e($tag); ?>
                    </span>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>
              </div>

              <!-- Details -->
              <div>
                <h3 class="text-lg font-semibold text-neutral-900 mb-4 flex items-center">
                  <i class="fa-solid fa-circle-info text-primary-600 mr-2"></i>
                  Service Details
                </h3>
                <div class="space-y-4">
                  <!-- Status -->
                    <div class="p-4 rounded-xl border <?php echo (!empty($provider['is_active']) && $provider['is_active'] == 1) ? 'bg-gradient-to-r from-green-50 to-emerald-50 border-green-100' : 'bg-gradient-to-r from-amber-50 to-yellow-50 border-amber-100'; ?>">
                    <div class="flex justify-between items-center">
                    <span class="text-sm font-medium <?php echo (!empty($provider['is_active']) && $provider['is_active'] == 1) ? 'text-green-700' : 'text-amber-700'; ?>">Status</span>
                    <?php if (!empty($provider['is_active']) && $provider['is_active'] == 1): ?>
                    <span class="text-lg font-bold text-green-700 bg-green-100 px-3 py-1 rounded-full">Available</span>
                    <?php else: ?>
                    <span class="text-lg font-bold text-amber-800 bg-amber-100 px-3 py-1 rounded-full">Not Available</span>
                    <?php endif; ?>
                    </div>
                    </div>
                  
                  <!-- Working Days -->
                  <?php if (!empty($workingDays)): ?>
                  <div class="bg-gradient-to-r from-purple-50 to-violet-50 p-4 rounded-xl border border-purple-100">
                  <span class="text-sm font-medium text-purple-700 block mb-2">Working Days</span>
                  <div class="flex flex-wrap gap-1">
                    <?php 
                    $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    foreach ($dayNames as $day): 
                    $isActive = in_array($day, $workingDays); // Check if the day is in the workingDays array
                    ?>
                    <span class="px-2 py-1 text-xs rounded <?php echo $isActive ? 'bg-purple-500 text-white font-semibold shadow' : 'bg-neutral-200 text-neutral-600'; ?>">
                    <?php echo $day; ?>
                    </span>
                    <?php endforeach; ?>
                  </div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Reviews Tab -->
          <div id="reviews-tab" class="tab-content hidden">
            <h3 class="text-lg font-semibold text-neutral-900 mb-6">Client Reviews</h3>
            <?php if (!empty($reviews)): ?>
            <div class="space-y-6">
              <?php foreach ($reviews as $review): ?>
              <div class="bg-neutral-50 rounded-xl p-6 border border-neutral-200">
                <div class="flex items-start justify-between mb-4">
                  <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
                      <span class="text-primary-600 font-semibold">
                        <?php echo strtoupper(substr($review['first_name'], 0, 1)); ?>
                      </span>
                    </div>
                    <div>
                      <h4 class="font-semibold text-neutral-900">
                        <?php echo e(($review['first_name'] ?? 'Anonymous') . ' ' . substr($review['last_name'] ?? '', 0, 1) . '.'); ?>
                      </h4>
                      <p class="text-sm text-neutral-500">
                        <?php echo date('M j, Y', strtotime($review['created_at'] ?? 'now')); ?>
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center space-x-1">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fa-solid fa-star text-<?php echo $i <= ($review['rating'] ?? 0) ? 'yellow-400' : 'neutral-300'; ?> text-sm"></i>
                    <?php endfor; ?>
                  </div>
                </div>
                <p class="text-neutral-600 leading-relaxed">
                  <?php echo nl2br(e($review['comment'] ?? 'No comment provided')); ?>
                </p>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
              <div class="bg-neutral-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-star text-2xl text-neutral-400"></i>
              </div>
              <h3 class="text-lg font-medium text-neutral-900 mb-2">No reviews yet</h3>
              <p class="text-neutral-500">This provider hasn't received any reviews yet.</p>
            </div>
            <?php endif; ?>
          </div>

          <!-- Qualifications Tab -->
          <div id="qualifications-tab" class="tab-content hidden">
            <h3 class="text-lg font-semibold text-neutral-900 mb-6">Qualifications & Certifications</h3>
            <?php if (!empty($qualifications)): ?>
            <div class="space-y-4">
              <?php foreach ($qualifications as $qualification): ?>
              <div class="bg-neutral-50 rounded-xl p-6 border border-neutral-200">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <h4 class="font-semibold text-neutral-900 mb-1">
                      <?php echo e($qualification['title'] ?? 'No title provided'); ?>
                    </h4>
                    <?php if (!empty($qualification['institution'])): ?>
                    <p class="text-neutral-600 mb-2">
                      <?php echo e($qualification['institution']); ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($qualification['description'])): ?>
                    <p class="text-sm text-neutral-500">
                      <?php echo e($qualification['description']); ?>
                    </p>
                    <?php endif; ?>
                  </div>
                  <div class="text-right">
                    <span class="inline-flex items-center px-3 py-1 bg-primary-100 text-primary-700 rounded-full text-sm font-medium">
                      <?php echo $qualification['year_obtained'] ?? 'N/A'; ?>
                    </span>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
              <div class="bg-neutral-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-certificate text-2xl text-neutral-400"></i>
              </div>
              <h3 class="text-lg font-medium text-neutral-900 mb-2">No qualifications listed</h3>
              <p class="text-neutral-500">This provider hasn't added any qualifications yet.</p>
            </div>
            <?php endif; ?>
          </div>

          <!-- Contact Tab -->
          <div id="contact-tab" class="tab-content hidden">
            <h3 class="text-lg font-semibold text-neutral-900 mb-6">Contact Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="bg-neutral-50 rounded-xl p-6">
                <h4 class="text-md font-semibold text-neutral-900 mb-4 flex items-center">
                  <i class="fa-solid fa-address-card text-primary-600 mr-2"></i>
                  Contact Details
                </h4>
                <div class="space-y-3">
                  <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-envelope text-neutral-400 w-5"></i>
                    <span class="text-neutral-600"><?php echo e($provider['email']); ?></span>
                  </div>
                  <?php if (!empty($provider['phone'])): ?>
                  <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-phone text-neutral-400 w-5"></i>
                    <span class="text-neutral-600"><?php echo e($provider['phone']); ?></span>
                  </div>
                  <?php endif; ?>
                  <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-location-dot text-neutral-400 w-5"></i>
                    <span class="text-neutral-600"><?php echo e($provider['location']); ?></span>
                  </div>
                </div>
              </div>
              
              <div class="bg-neutral-50 rounded-xl p-6">
                <h4 class="text-md font-semibold text-neutral-900 mb-4 flex items-center">
                  <i class="fa-solid fa-handshake text-primary-600 mr-2"></i>
                  Get in Touch
                </h4>
                <?php if ($currentUser && $currentUser['id'] != $provider['user_id']): ?>
                <div class="space-y-3">
                  <a href="<?php echo BASE_URL; ?>/contact-provider.php?id=<?php echo $provider['id']; ?>" 
                     class="w-full bg-primary-600 text-white px-4 py-3 rounded-lg hover:bg-primary-700 transition-colors font-medium flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-envelope"></i>
                    <span>Send Message</span>
                  </a>
                  <a href="tel:<?php echo e($provider['phone']); ?>" 
                     class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 transition-colors font-medium flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-phone"></i>
                    <span>Call Now</span>
                  </a>
                </div>
                <?php else: ?>
                <p class="text-neutral-500 italic">Please log in to contact this provider.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
  const tabButtons = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');
  
  tabButtons.forEach(button => {
    button.addEventListener('click', () => {
      const targetTab = button.getAttribute('data-tab');
      
      // Remove active classes from all buttons
      tabButtons.forEach(btn => {
        btn.classList.remove('border-primary-600', 'text-primary-600');
        btn.classList.add('border-transparent', 'text-neutral-500');
      });
      
      // Add active classes to clicked button
      button.classList.remove('border-transparent', 'text-neutral-500');
      button.classList.add('border-primary-600', 'text-primary-600');
      
      // Hide all tab contents
      tabContents.forEach(content => {
        content.classList.add('hidden');
      });
      
      // Show target tab content
      const targetContent = document.getElementById(targetTab + '-tab');
      if (targetContent) {
        targetContent.classList.remove('hidden');
      }
    });
  });
  
  // Favorites functionality
  const favoriteBtn = document.getElementById('favoriteBtn');
  if (favoriteBtn) {
    favoriteBtn.addEventListener('click', function() {
      const providerId = this.getAttribute('data-provider-id');
      const isFavorite = this.getAttribute('data-is-favorite') === 'true';
      const action = isFavorite ? 'remove' : 'add';
      
      // Disable button during request
      this.disabled = true;
      this.style.opacity = '0.6';
      
      fetch('<?php echo BASE_URL; ?>/api/favorites.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          action: action,
          provider_id: providerId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update button state
          const newIsFavorite = !isFavorite;
          this.setAttribute('data-is-favorite', newIsFavorite);
          
          // Update button appearance
          const icon = this.querySelector('i');
          const text = this.querySelector('span');
          
          if (newIsFavorite) {
            this.className = 'bg-red-100 text-red-600 border-red-200 border px-4 py-2 rounded-lg hover:opacity-80 transition-all font-medium flex items-center space-x-2';
            icon.className = 'fa-solid fa-heart';
            text.textContent = 'Favorited';
          } else {
            this.className = 'bg-neutral-100 text-neutral-600 border-neutral-200 border px-4 py-2 rounded-lg hover:opacity-80 transition-all font-medium flex items-center space-x-2';
            icon.className = 'fa-regular fa-heart';
            text.textContent = 'Add to Favorites';
          }
          
          // Show success message (optional)
          showNotification(data.message, 'success');
        } else {
          showNotification(data.message || 'An error occurred', 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showNotification('Network error. Please try again.', 'error');
      })
      .finally(() => {
        // Re-enable button
        this.disabled = false;
        this.style.opacity = '1';
      });
    });
  }
});

// Simple notification function
function showNotification(message, type) {
  // Create notification element
  const notification = document.createElement('div');
  notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg ${
    type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'
  }`;
  notification.textContent = message;
  
  // Add to document
  document.body.appendChild(notification);
  
  // Remove after 3 seconds
  setTimeout(() => {
    if (notification.parentNode) {
      notification.parentNode.removeChild(notification);
    }
  }, 3000);
}
</script>

<?php include 'includes/footer.php'; ?>
