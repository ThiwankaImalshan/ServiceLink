<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/ImageUploader.php';
require_once 'includes/VerificationManager.php';
require_once 'includes/FavoritesManagerProviderProfile.php';

$db = getDB();
$currentUser = $auth->getCurrentUser();
$verificationManager = new VerificationManager();
$favoritesManager = new FavoritesManagerProviderProfile();

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
        SELECT p.*, u.first_name, u.last_name, u.email, u.phone, u.profile_photo, u.email_verified,
               u.id_verification_status, u.linkedin_profile, u.linkedin_verification_status, u.created_at as user_created_at,
               c.name as category_name, c.icon as category_icon, c.slug as category_slug
        FROM providers p 
        JOIN users u ON p.user_id = u.id 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ?
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
  // Use provider id for favorites logic
  $isFavorite = $favoritesManager->isFavorite($currentUser['id'], $provider['id']);
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
    
  <!-- Rating Modal Popup -->
  <div id="ratingModal" class="fixed inset-0 z-50 hidden">
    <!-- Modal Backdrop -->
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm transition-opacity duration-300"></div>
    <!-- Modal Content -->
    <div class="fixed inset-0 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all duration-300 scale-95 opacity-0" id="ratingModalContent">
        <!-- Modal Header -->
        <div class="p-6 border-b border-neutral-200">
          <div class="flex items-center justify-between">
            <h3 class="text-xl font-semibold text-neutral-900" id="ratingModalTitle">Rate Provider</h3>
            <button onclick="hideRatingModal()" class="text-neutral-400 hover:text-neutral-500">
              <i class="fa-solid fa-times"></i>
            </button>
          </div>
        </div>
        <!-- Modal Body -->
        <div class="p-6">
          <form id="ratingForm" class="space-y-4">
            <input type="hidden" id="providerId" name="providerId">
            <!-- Star Rating -->
            <div class="text-center mb-6">
              <div class="text-3xl space-x-3" id="starRating">
                <i class="fa-star cursor-pointer text-neutral-300 hover:text-yellow-400 transition-all transform hover:scale-125 star-rating-icon" data-rating="1"></i>
                <i class="fa-star cursor-pointer text-neutral-300 hover:text-yellow-400 transition-all transform hover:scale-125 star-rating-icon" data-rating="2"></i>
                <i class="fa-star cursor-pointer text-neutral-300 hover:text-yellow-400 transition-all transform hover:scale-125 star-rating-icon" data-rating="3"></i>
                <i class="fa-star cursor-pointer text-neutral-300 hover:text-yellow-400 transition-all transform hover:scale-125 star-rating-icon" data-rating="4"></i>
                <i class="fa-star cursor-pointer text-neutral-300 hover:text-yellow-400 transition-all transform hover:scale-125 star-rating-icon" data-rating="5"></i>
              </div>
              <p class="mt-3 text-sm font-medium text-neutral-600" id="ratingText">Select your rating</p>
            </div>
            <!-- Review Text -->
            <div>
              <label for="review" class="block text-sm font-medium text-neutral-700 mb-2">Your Review</label>
              <textarea id="review" name="review" rows="3" class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Share your experience..."></textarea>
            </div>
          </form>
        </div>
        <!-- Modal Footer -->
        <div class="p-6 border-t border-neutral-200 flex justify-end space-x-3">
          <button onclick="hideRatingModal()" class="px-4 py-2 text-neutral-700 hover:bg-neutral-100 rounded-lg transition-colors">Cancel</button>
          <button onclick="submitRating()" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors">Submit Rating</button>
        </div>
      </div>
    </div>
  </div>
  <style>
    @keyframes star-bounce {
      0%,100% { transform: scale(1); }
      50% { transform: scale(1.2); }
    }
    .star-rating-icon { display: inline-block; transition: all 0.2s cubic-bezier(0.4,0,0.2,1); }
    .star-rating-icon.active { animation: star-bounce 0.4s cubic-bezier(0.68,-0.55,0.265,1.55); color: #facc15; text-shadow: 0 0 15px rgba(250,204,21,0.5); }
    #starRating:hover .star-rating-icon { transform: scale(0.9); opacity: 0.75; }
    #starRating .star-rating-icon:hover, #starRating .star-rating-icon:hover~.star-rating-icon { transform: scale(1.2); opacity: 1; }
  </style>
  <script>
    let currentRating = 0;
    const ratingTexts = { 1: "Poor", 2: "Fair", 3: "Good", 4: "Very Good", 5: "Excellent" };
    function showRatingModal(providerId, providerName) {
      const modal = document.getElementById('ratingModal');
      const modalContent = document.getElementById('ratingModalContent');
      document.getElementById('providerId').value = providerId;
      document.getElementById('ratingModalTitle').textContent = `Rate ${providerName}`;
      currentRating = 0;
      document.getElementById('review').value = '';
      updateStars(0);
      document.getElementById('ratingText').textContent = 'Select your rating';
      modal.classList.remove('hidden');
      setTimeout(() => { modalContent.classList.remove('scale-95', 'opacity-0'); modalContent.classList.add('scale-100', 'opacity-100'); }, 10);
      document.body.style.overflow = 'hidden';
    }
    function hideRatingModal() {
      const modal = document.getElementById('ratingModal');
      const modalContent = document.getElementById('ratingModalContent');
      modalContent.classList.add('scale-95', 'opacity-0');
      modalContent.classList.remove('scale-100', 'opacity-100');
      setTimeout(() => { modal.classList.add('hidden'); document.body.style.overflow = ''; }, 300);
    }
    function updateStars(rating) {
      const stars = document.querySelectorAll('#starRating i');
      stars.forEach((star, index) => {
        star.classList.remove('fas', 'far', 'active');
        star.classList.add(index < rating ? 'fas' : 'far');
        if (index < rating) { setTimeout(() => { star.classList.add('active'); }, index * 50); }
        star.classList.toggle('text-yellow-400', index < rating);
        star.classList.toggle('text-neutral-300', index >= rating);
      });
      const ratingText = document.getElementById('ratingText');
      ratingText.style.opacity = '0';
      setTimeout(() => { ratingText.textContent = rating ? ratingTexts[rating] : 'Select your rating'; ratingText.style.opacity = '1'; }, 200);
    }
    document.getElementById('starRating').addEventListener('click', (e) => {
      if (e.target.matches('i')) { currentRating = parseInt(e.target.dataset.rating); updateStars(currentRating); }
    });
    document.getElementById('starRating').addEventListener('mouseover', (e) => {
      if (e.target.matches('i')) { updateStars(parseInt(e.target.dataset.rating)); }
    });
    document.getElementById('starRating').addEventListener('mouseleave', () => { updateStars(currentRating); });
    function submitRating() {
      if (!currentRating) { alert('Please select a rating'); return; }
      const providerId = document.getElementById('providerId').value;
      const review = document.getElementById('review').value;
      fetch('api/ratings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ providerId: providerId, rating: currentRating, review: review })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          hideRatingModal();
          alert('Thank you for your rating!');
          location.reload();
        } else {
          alert(data.message || 'Error submitting rating. Please try again.');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error submitting rating. Please try again.');
      });
    }
  </script>

      <!-- Profile Header -->
      <div class="bg-white rounded-2xl shadow-xl border border-neutral-200 p-4 sm:p-6 lg:p-8 mb-8">
        <div class="flex flex-col md:flex-row items-center md:items-start space-y-6 md:space-y-0 md:space-x-8">
          <!-- Profile Photo Section -->
          <div class="relative flex-shrink-0 w-full md:w-auto flex justify-center md:block">
            <div class="relative w-fit mx-auto md:mx-0">
                <?php
                  $photoPath = str_replace('\\', '/', $provider['profile_photo']);
                  if (empty($photoPath)) {
                    $imgSrc = BASE_URL . '/assets/img/default-avatar.png';
                  } else if (filter_var($photoPath, FILTER_VALIDATE_URL)) {
                    $imgSrc = $photoPath;
                  } else {
                    $imgSrc = BASE_URL . '/serve-upload.php?p=' . rawurlencode(ltrim($photoPath, '/'));
                  }
                ?>
                <img src="<?php echo $imgSrc; ?>"
                   alt="<?php echo e($provider['first_name'] . ' ' . $provider['last_name']); ?>" 
                   class="w-32 h-32 sm:w-36 sm:h-36 lg:w-40 lg:h-40 rounded-full object-cover border-4 border-white shadow-2xl ring-4 ring-primary-100" />
              
              <!-- Verified Badge -->
              <?php if ($provider['is_verified']): ?>
                <div class="absolute bottom-2 right-2 sm:bottom-2 sm:right-2 md:bottom-2 md:right-2 lg:bottom-2 lg:right-2" style="right:0.25rem; bottom:0.25rem; z-index:2;">
                  <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gradient-to-br from-green-500 via-emerald-400 to-green-700 shadow-lg border-2 border-white ring-2 ring-green-300"
                       style="position:relative;">
                    <i class="fa-solid fa-certificate text-white text-xl drop-shadow" title="Verified Provider"></i>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Profile Info and Stats -->
          <div class="flex-1 min-w-0">
            <div class="flex flex-col md:block w-full space-y-6 md:space-y-0 mb-4">
              <div class="flex flex-col items-center md:items-start w-full md:max-w-none text-center md:text-left">
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-neutral-900 mb-2">
                  <?php echo e($provider['business_name'] ?: ($provider['first_name'] . ' ' . $provider['last_name'])); ?>
                </h1>
                <!-- Favorite and Review Buttons for Users -->
                <?php if ($currentUser && $currentUser['role'] === 'user'): ?>
                <div class="flex flex-col sm:flex-row items-center justify-center md:justify-start gap-2 sm:gap-3 mb-4 w-full">
                  <!-- Favorite Icon -->
                  <button id="favoriteBtn"
                    class="<?php echo $isFavorite ? 'bg-red-100 text-red-600 border-red-200' : 'bg-neutral-100 text-neutral-600 border-neutral-200'; ?> border px-2 py-1 rounded-lg shadow-sm hover:shadow-md hover:scale-[1.03] transition-all font-medium flex items-center space-x-1 w-full sm:w-auto justify-center text-xs sm:text-sm"
                    data-provider-id="<?php echo htmlspecialchars($providerId); ?>"
                    data-is-favorite="<?php echo $isFavorite ? 'true' : 'false'; ?>"
                    title="<?php echo $isFavorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
                    <i class="<?php echo $isFavorite ? 'fa-solid fa-heart' : 'fa-regular fa-heart'; ?> text-base sm:text-sm"></i>
                    <span class="ml-1"><?php echo $isFavorite ? 'Favorited' : 'Add to Favorites'; ?></span>
                  </button>
                  <!-- Review Button -->
                  <button class="bg-gradient-to-r from-primary-600 to-secondary-600 text-white px-2 py-1 rounded-lg shadow-sm hover:shadow-md hover:scale-[1.03] transition-all font-medium flex items-center gap-1 w-full sm:w-auto justify-center text-xs sm:text-sm" onclick="showRatingModal(<?php echo $provider['id']; ?>, '<?php echo addslashes($provider['business_name'] ?: ($provider['first_name'] . ' ' . $provider['last_name'])); ?>')">
                    <i class="fa-regular fa-star text-base sm:text-sm"></i>
                    <span class="ml-1">Rate & Review</span>
                  </button>
                </div>
                <?php endif; ?>
                
                <!-- Category and Location -->
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 text-neutral-600 text-sm sm:text-base mb-4">
                  <span class="flex items-center space-x-2">
                    <i class="<?php echo e($provider['category_icon']); ?> text-primary-500"></i>
                    <span><?php echo e($provider['category_name']); ?></span>
                  </span>
                  <span class="flex items-center space-x-2">
                    <i class="fa-solid fa-location-dot text-primary-500"></i>
                    <span><?php echo e($provider['location']); ?></span>
                  </span>
                    <span class="flex items-center space-x-2">
                    <i class="fa-solid fa-user-clock text-primary-500"></i>
                    <span>Since&nbsp;<?php echo date('M Y', strtotime($provider['user_created_at'])); ?></span>
                    </span>
                </div>

                <!-- Status Badges -->
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-2 mb-4">
                  <!-- Provider Badge -->
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-lg">
                    <i class="fa-solid fa-briefcase mr-1"></i>Provider
                  </span>
                  
                  <!-- Active Status -->
                  <?php if ($provider['is_active']): ?>
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <i class="fa-solid fa-circle text-green-500 mr-1"></i>Active
                  </span>
                  <?php else: ?>
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                    <i class="fa-solid fa-circle text-red-500 mr-1"></i>Inactive
                  </span>
                  <?php endif; ?>
                  
                  <!-- Verification Badges -->
                  <?php if ($provider['id_verification_status'] === 'approved'): ?>
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    <i class="fa-solid fa-id-card mr-1"></i>ID Verified
                  </span>
                  <?php endif; ?>
                  
                  <?php if ($provider['linkedin_verification_status'] === 'verified'): ?>
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    <i class="fa-brands fa-linkedin mr-1"></i>LinkedIn Verified
                  </span>
                  <?php endif; ?>
                  
                  <?php if ($provider['email_verified']): ?>
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-cyan-100 text-cyan-800">
                    <i class="fa-solid fa-envelope-circle-check mr-1"></i>Email Verified
                  </span>
                  <?php endif; ?>
                </div>

                <!-- LinkedIn Profile Link -->
                <?php if (!empty($provider['linkedin_profile'])): ?>
                <div class="mb-4">
                  <a href="<?php echo e($provider['linkedin_profile']); ?>" target="_blank" 
                     class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium transition-colors">
                    <i class="fa-brands fa-linkedin mr-2"></i>
                    View LinkedIn Profile
                    <i class="fa-solid fa-external-link-alt ml-1 text-xs"></i>
                  </a>
                </div>
                <?php endif; ?>

                <!-- Rating Display -->
                <div class="flex items-center justify-center md:justify-start mb-4">
                  <div class="flex items-center space-x-1">
                    <?php 
                    $rating = floatval($provider['rating']);
                    for ($i = 1; $i <= 5; $i++): 
                    ?>
                      <?php if ($i <= $rating): ?>
                        <i class="fa-solid fa-star text-yellow-400"></i>
                      <?php elseif ($i - 0.5 <= $rating): ?>
                        <i class="fa-solid fa-star-half-stroke text-yellow-400"></i>
                      <?php else: ?>
                        <i class="fa-regular fa-star text-gray-300"></i>
                      <?php endif; ?>
                    <?php endfor; ?>
                    <span class="ml-2 text-sm text-gray-600">
                      <?php echo number_format($rating, 1); ?> 
                      (<?php echo $provider['review_count']; ?> <?php echo $provider['review_count'] == 1 ? 'review' : 'reviews'; ?>)
                    </span>
                  </div>
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
        <nav class="flex flex-nowrap justify-start min-w-full px-2 sm:px-4" aria-label="Tabs">
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

              <!-- Service Details -->
              <div>
                <h3 class="text-lg font-semibold text-neutral-900 mb-4 flex items-center">
                  <i class="fa-solid fa-circle-info text-primary-600 mr-2"></i>
                  Service Details
                </h3>
                <div class="space-y-4">
                  <!-- Status -->
                  <div class="p-4 rounded-xl border <?php echo $provider['is_active'] ? 'bg-gradient-to-r from-green-50 to-emerald-50 border-green-100' : 'bg-gradient-to-r from-red-50 to-pink-50 border-red-100'; ?>">
                    <div class="flex justify-between items-center">
                      <span class="text-sm font-medium <?php echo $provider['is_active'] ? 'text-green-700' : 'text-red-700'; ?>">Status</span>
                      <span class="text-lg font-bold <?php echo $provider['is_active'] ? 'text-green-700 bg-green-100' : 'text-red-700 bg-red-100'; ?> px-3 py-1 rounded-full">
                        <?php echo $provider['is_active'] ? 'Available' : 'Not Available'; ?>
                      </span>
                    </div>
                  </div>
                  
                  <!-- Working Days -->
                  <?php if (!empty($workingDays)): ?>
                  <div class="bg-gradient-to-r from-purple-50 to-violet-50 p-4 rounded-xl border border-purple-100">
                    <span class="text-sm font-medium text-purple-700 block mb-2">Working Days</span>
                    <div class="flex flex-wrap gap-1">
                      <?php 
                      $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                      $shortDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                      foreach ($shortDays as $i => $day): 
                        $isActive = in_array($dayNames[$i], $workingDays) || in_array($day, $workingDays);
                      ?>
                      <span class="px-2 py-1 text-xs rounded <?php echo $isActive ? 'bg-purple-500 text-white font-semibold shadow' : 'bg-neutral-200 text-neutral-600'; ?>">
                        <?php echo $day; ?>
                      </span>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>
                  
                  <!-- Working Hours -->
                  <?php if ($provider['working_hours_start'] && $provider['working_hours_end']): ?>
                  <div class="bg-gradient-to-r from-amber-50 to-orange-50 p-4 rounded-xl border border-amber-100">
                    <div class="flex justify-between items-center">
                      <span class="text-sm font-medium text-amber-700">Working Hours</span>
                      <span class="text-sm font-bold text-amber-900">
                        <?php echo date('g:i A', strtotime($provider['working_hours_start'])); ?> - 
                        <?php echo date('g:i A', strtotime($provider['working_hours_end'])); ?>
                      </span>
                    </div>
                  </div>
                  <?php endif; ?>
                  
                  <!-- Best Call Time -->
                  <?php if (!empty($provider['best_call_time'])): ?>
                  <div class="bg-gradient-to-r from-cyan-50 to-blue-50 p-4 rounded-xl border border-cyan-100">
                    <div class="flex justify-between items-center">
                      <span class="text-sm font-medium text-cyan-700">Best Call Time</span>
                      <span class="text-sm font-bold text-cyan-900"><?php echo e($provider['best_call_time']); ?></span>
                    </div>
                  </div>
                  <?php endif; ?>
                  
                  <!-- Location with Map -->
                  <?php if ($provider['latitude'] && $provider['longitude']): ?>
                  <div class="bg-gradient-to-r from-indigo-50 to-purple-50 p-4 rounded-xl border border-indigo-100">
                    <span class="text-sm font-medium text-indigo-700 block mb-3">Service Location</span>
                    <div class="flex items-center justify-between mb-3">
                      <span class="text-sm text-indigo-900"><?php echo e($provider['location']); ?></span>
                      <a href="https://www.google.com/maps?q=<?php echo $provider['latitude']; ?>,<?php echo $provider['longitude']; ?>" 
                         target="_blank" 
                         class="inline-flex items-center px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium hover:bg-indigo-200 transition-colors">
                        <i class="fa-solid fa-map-location-dot mr-1"></i>
                        View on Map
                      </a>
                    </div>
                    <!-- <div class="text-xs text-indigo-600">
                      Coordinates: <?php echo $provider['latitude']; ?>, <?php echo $provider['longitude']; ?>
                    </div> -->
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
                <div class="space-y-4">
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
                  
                  <div class="flex items-start space-x-3">
                    <i class="fa-solid fa-location-dot text-neutral-400 w-5 mt-1"></i>
                    <span class="text-neutral-600"><?php echo e($provider['location']); ?></span>
                  </div>
                  
                  <?php if (!empty($provider['linkedin_profile'])): ?>
                  <div class="flex items-center space-x-3">
                    <i class="fa-brands fa-linkedin text-neutral-400 w-5"></i>
                    <a href="<?php echo e($provider['linkedin_profile']); ?>" target="_blank" 
                       class="text-blue-600 hover:text-blue-800 transition-colors">
                      LinkedIn Profile
                      <i class="fa-solid fa-external-link-alt ml-1 text-xs"></i>
                    </a>
                  </div>
                  <?php endif; ?>
                  
                  <?php if (!empty($provider['best_call_time'])): ?>
                  <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-clock text-neutral-400 w-5"></i>
                    <div>
                      <span class="text-neutral-600">Best time to call: </span>
                      <span class="font-medium text-neutral-900"><?php echo e($provider['best_call_time']); ?></span>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
                
                <!-- Service Area Map -->
                <?php if ($provider['latitude'] && $provider['longitude']): ?>
                <div class="mt-6 pt-6 border-t border-neutral-200">
                  <h5 class="text-sm font-semibold text-neutral-900 mb-3">Service Area</h5>
                  <div class="bg-gray-100 rounded-lg p-4 text-center">
                    <iframe
                      width="100%"
                      height="200"
                      frameborder="0"
                      scrolling="no"
                      marginheight="0"
                      marginwidth="0"
                      class="rounded-lg"
                      src="https://www.openstreetmap.org/export/embed.html?bbox=<?php echo ($provider['longitude']-0.01); ?>%2C<?php echo ($provider['latitude']-0.01); ?>%2C<?php echo ($provider['longitude']+0.01); ?>%2C<?php echo ($provider['latitude']+0.01); ?>&amp;layer=mapnik&amp;marker=<?php echo $provider['latitude']; ?>%2C<?php echo $provider['longitude']; ?>">
                    </iframe>
                    <div style="display:none;" class="text-gray-500 py-8">
                      <i class="fa-solid fa-map-location-dot text-2xl mb-2 block"></i>
                      Map unavailable
                    </div>
                  </div>
                </div>
                <?php endif; ?>
              </div>
              
              <div class="space-y-6">
                <!-- Get in Touch -->
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
                    <?php if (!empty($provider['phone'])): ?>
                    <a href="tel:<?php echo e($provider['phone']); ?>" 
                       class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 transition-colors font-medium flex items-center justify-center space-x-2">
                      <i class="fa-solid fa-phone"></i>
                      <span>Call Now</span>
                    </a>
                    <?php endif; ?>
                  </div>
                  <?php else: ?>
                  <p class="text-neutral-500 italic">Please log in to contact this provider.</p>
                  <?php endif; ?>
                </div>
                
                <!-- Business Hours -->
                <div class="bg-neutral-50 rounded-xl p-6">
                  <h4 class="text-md font-semibold text-neutral-900 mb-4 flex items-center">
                    <i class="fa-solid fa-clock text-primary-600 mr-2"></i>
                    Availability
                  </h4>
                  <div class="space-y-3">
                    <?php if ($provider['working_hours_start'] && $provider['working_hours_end']): ?>
                    <div class="flex justify-between items-center">
                      <span class="text-neutral-600">Working Hours:</span>
                      <span class="font-medium text-neutral-900">
                        <?php echo date('g:i A', strtotime($provider['working_hours_start'])); ?> - 
                        <?php echo date('g:i A', strtotime($provider['working_hours_end'])); ?>
                      </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($workingDays)): ?>
                    <div>
                      <span class="text-neutral-600 block mb-2">Working Days:</span>
                      <div class="flex flex-wrap gap-1">
                        <?php 
                        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        $shortDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        foreach ($shortDays as $i => $day): 
                          $isActive = in_array($dayNames[$i], $workingDays) || in_array($day, $workingDays);
                        ?>
                        <span class="px-2 py-1 text-xs rounded <?php echo $isActive ? 'bg-primary-500 text-white font-semibold' : 'bg-neutral-200 text-neutral-600'; ?>">
                          <?php echo $day; ?>
                        </span>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between items-center">
                      <span class="text-neutral-600">Status:</span>
                      <span class="font-medium <?php echo $provider['is_active'] ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo $provider['is_active'] ? 'Currently Available' : 'Currently Unavailable'; ?>
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
      
      fetch('<?php echo BASE_URL; ?>/api/provider-favorites.php', {
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
