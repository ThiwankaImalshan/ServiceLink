<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/ImageUploader.php';

$currentUser = $auth->getCurrentUser();
$db = getDB();

$pageTitle = 'ServiceLink • Find Trusted Local Services';
$pageDescription = 'Find and hire reliable local service providers: home services, education & training, vehicle repair, tech support, and more.';

// Get categories from database
try {
  $stmt = $db->prepare("SELECT * FROM categories WHERE active = 1 ORDER BY sort_order ASC, name ASC");
  $stmt->execute([]);
  $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $categories = [];
}

// Get featured providers (rating >= 4.5, maximize category diversity, exactly 9 cards)
try {
  // First, get the best provider from each category with rating >= 4.5
  $stmt = $db->prepare("
        SELECT DISTINCT 
            p.id, p.user_id, p.business_name, p.location, p.hourly_rate, p.experience_years, 
            p.description, p.rating, p.review_count, p.is_active, p.is_verified, p.is_skilled,
            u.first_name, u.last_name, u.profile_photo, u.email, u.phone,
            c.name as category_name, c.icon as category_icon, c.slug as category_slug,
            c.id as category_id
        FROM providers p 
        JOIN users u ON p.user_id = u.id 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 AND p.is_verified = 1 AND p.rating >= 4.5
        ORDER BY c.id, p.rating DESC, p.review_count DESC
    ");
  $stmt->execute();
  $stmt->execute([]);
  $highRatedProviders = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Group by category and get the best provider from each
  $featuredProviders = [];
  $usedCategories = [];

  foreach ($highRatedProviders as $provider) {
    if (!in_array($provider['category_id'], $usedCategories)) {
      $featuredProviders[] = $provider;
      $usedCategories[] = $provider['category_id'];
    }
    // Stop when we have 9 different categories covered
    if (count($featuredProviders) >= 9) {
      break;
    }
  }

  // If we have less than 9, add more high-rated providers from any category
  if (count($featuredProviders) < 9) {
    $remainingSlots = 9 - count($featuredProviders);
    $usedProviderIds = array_column($featuredProviders, 'id');

    foreach ($highRatedProviders as $provider) {
      if (!in_array($provider['id'], $usedProviderIds)) {
        $featuredProviders[] = $provider;
        $usedProviderIds[] = $provider['id'];
        $remainingSlots--;
        if ($remainingSlots <= 0) {
          break;
        }
      }
    }
  }

  // If still not enough, get providers with rating >= 4.0
  if (count($featuredProviders) < 9) {
    $stmt = $db->prepare("
            SELECT DISTINCT 
                p.id, p.user_id, p.business_name, p.location, p.hourly_rate, p.experience_years, 
                p.description, p.rating, p.review_count, p.is_active, p.is_verified, p.is_skilled,
                u.first_name, u.last_name, u.profile_photo, u.email, u.phone,
                c.name as category_name, c.icon as category_icon, c.slug as category_slug,
                c.id as category_id
            FROM providers p 
            JOIN users u ON p.user_id = u.id 
            JOIN categories c ON p.category_id = c.id 
            WHERE p.is_active = 1 AND p.is_verified = 1 AND p.rating >= 4.0
            ORDER BY p.rating DESC, p.review_count DESC
            LIMIT 20
        ");
    $stmt->execute();
    $stmt->execute([]);
    $goodProviders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $usedProviderIds = array_column($featuredProviders, 'id');
    $remainingSlots = 9 - count($featuredProviders);

    foreach ($goodProviders as $provider) {
      if (!in_array($provider['id'], $usedProviderIds)) {
        $featuredProviders[] = $provider;
        $remainingSlots--;
        if ($remainingSlots <= 0) {
          break;
        }
      }
    }
  }

  // Ensure exactly 9 providers
  $featuredProviders = array_slice($featuredProviders, 0, 9);

  error_log("Featured providers found: " . count($featuredProviders) . " providers");
  error_log("Categories covered: " . count(array_unique(array_column($featuredProviders, 'category_name'))));

  // If no providers found, keep empty array
  if (empty($featuredProviders)) {
    $featuredProviders = [];
    error_log("No providers found - using empty array");
  }
} catch (PDOException $e) {
  // If database error, use empty array
  $featuredProviders = [];
  error_log("Database error fetching featured providers: " . $e->getMessage());
}

// Get provider count
try {
  $stmt = $db->prepare("SELECT COUNT(*) as count FROM providers WHERE is_active = 1");
  $stmt->execute();
  $providerCount = $stmt->fetch()['count'];
} catch (PDOException $e) {
  $providerCount = 0;
}


// Include header after processing
include 'includes/header.php';
// include 'includes/loader.php';
?>

<!-- Hero Section -->
<section class="relative bg-gradient-to-r from-primary-600 via-primary-700 to-primary-800 text-white overflow-hidden">
  <!-- Background Image with Reduced Opacity -->
  <div class="absolute inset-0 bg-[url('assets/img/profession.png')] bg-cover bg-center opacity-20"></div>

  <!-- Background Pattern -->
  <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=" 60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg" %3E%3Cg fill="none" fill-rule="evenodd" %3E%3Cg fill="%23ffffff" fill-opacity="0.1" %3E%3Ccircle cx="30" cy="30" r="1" /%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-20"></div>

  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-24 lg:py-32">
    <div class="text-center">
      <h1 class="text-4xl sm:text-5xl md:text-6xl font-black tracking-tight leading-tight mb-6">
        Find trusted local
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-yellow-300 to-orange-300">
          pros near you
        </span>
      </h1>
      <p class="text-xl sm:text-2xl text-primary-100 max-w-3xl mx-auto mb-10 leading-relaxed">
        Home services, education & training, vehicle repair, tech support, and more.
      </p>

      <!-- CTA Buttons -->
      <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
        <a href="<?php echo BASE_URL; ?>/services.php" class="group bg-white text-primary-600 px-8 py-4 rounded-xl font-semibold text-lg hover:bg-neutral-100 transition-all duration-300 transform hover:scale-105 shadow-lg flex items-center space-x-2">
          <i class="fa-solid fa-magnifying-glass group-hover:animate-pulse"></i>
          <span>Browse Services</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/login.php" class="group bg-secondary-500 text-white px-8 py-4 rounded-xl font-semibold text-lg hover:bg-secondary-600 transition-all duration-300 transform hover:scale-105 shadow-lg flex items-center space-x-2">
          <i class="fa-solid fa-user-plus group-hover:animate-pulse"></i>
          <span>List Your Service</span>
        </a>
      </div>

      <!-- Stats -->
      <div class="mt-16 grid grid-cols-1 sm:grid-cols-3 gap-8 max-w-3xl mx-auto">
        <div class="text-center transform hover:scale-105 transition-transform duration-300">
          <div class="text-3xl font-bold text-yellow-300 animate-pulse"><?php echo number_format($providerCount); ?>+</div>
          <div class="text-primary-200 font-medium">Service Providers</div>
        </div>
        <div class="text-center transform hover:scale-105 transition-transform duration-300">
          <div class="text-3xl font-bold text-yellow-300 animate-pulse" style="animation-delay: 0.5s;"><?php echo count($categories); ?>+</div>
          <div class="text-primary-200 font-medium">Service Categories</div>
        </div>
        <div class="text-center transform hover:scale-105 transition-transform duration-300">
          <div class="text-3xl font-bold text-yellow-300 animate-pulse" style="animation-delay: 1s;">24/7</div>
          <div class="text-primary-200 font-medium">Customer Support</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Wave Bottom -->
  <div class="absolute bottom-0 left-0 right-0">
    <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-auto">
      <path d="M0 120L60 105C120 90 240 60 360 45C480 30 600 30 720 37.5C840 45 960 60 1080 67.5C1200 75 1320 75 1380 75L1440 75V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" fill="currentColor" class="text-white" />
    </svg>
  </div>
</section>

<!-- Categories Section -->
<section class="py-20 bg-gradient-to-b from-neutral-50 to-white relative overflow-hidden">
  <!-- Background Pattern -->
  <div class="absolute inset-0 opacity-10">
    <div class="absolute top-10 left-10 w-20 h-20 bg-primary-200 rounded-full blur-xl animate-pulse"></div>
    <div class="absolute top-32 right-20 w-16 h-16 bg-secondary-200 rounded-full blur-lg animate-pulse" style="animation-delay: 2s;"></div>
    <div class="absolute bottom-20 left-1/4 w-12 h-12 bg-primary-300 rounded-full blur-md animate-pulse" style="animation-delay: 4s;"></div>
    <div class="absolute bottom-32 right-1/3 w-14 h-14 bg-secondary-300 rounded-full blur-lg animate-pulse" style="animation-delay: 6s;"></div>
  </div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
    <div class="text-center mb-16">
      <h2 class="text-3xl sm:text-4xl font-bold text-neutral-900 mb-4">
        Popular Categories
      </h2>
      <p class="text-xl text-neutral-600 max-w-2xl mx-auto">
        Discover top-rated professionals in your area
      </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 lg:gap-8">
      <?php foreach ($categories as $index => $category): ?>
        <a href="<?php echo BASE_URL; ?>/services.php?category=<?php echo e($category['slug']); ?>"
          class="group relative block overflow-hidden rounded-2xl transform transition-all duration-500 hover:scale-105 hover:-translate-y-3 cursor-pointer opacity-100 translate-y-0"
          style="animation-delay: <?php echo $index * 200; ?>ms;">
          <!-- Main Card Container -->
          <div class="relative h-48 sm:h-56 bg-white shadow-lg group-hover:shadow-2xl transition-all duration-500 rounded-2xl overflow-hidden border border-neutral-100 group-hover:border-primary-200">

            <!-- Animated Background Gradient -->
            <div class="absolute inset-0 bg-gradient-to-br from-primary-50 via-white to-secondary-50 group-hover:from-primary-100 group-hover:via-primary-50 group-hover:to-secondary-100 transition-all duration-700"></div>

            <!-- Floating Geometric Shapes -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
              <div class="absolute -top-4 -right-4 w-16 h-16 bg-gradient-to-br from-primary-200/40 to-secondary-200/40 rounded-full blur-sm group-hover:scale-150 group-hover:rotate-90 transition-all duration-700"></div>
              <div class="absolute -bottom-2 -left-2 w-12 h-12 bg-gradient-to-tr from-secondary-200/30 to-primary-200/30 rounded-full blur-sm group-hover:scale-125 group-hover:-rotate-45 transition-all duration-500"></div>
              <div class="absolute top-1/3 right-1/4 w-6 h-6 bg-primary-300/20 rounded-full blur-sm group-hover:scale-200 transition-all duration-1000"></div>
            </div>

            <!-- Content Container -->
            <div class="relative z-10 h-full flex flex-col items-center justify-center p-6 text-center">

              <!-- Icon Container with Enhanced Design -->
              <div class="relative mb-4 group-hover:mb-6 transition-all duration-300">
                <!-- Icon Background Circle -->
                <div class="absolute inset-0 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-2xl blur-lg opacity-20 group-hover:opacity-40 group-hover:blur-xl transition-all duration-500 transform group-hover:scale-110"></div>

                <!-- Main Icon Container -->
                <div class="relative bg-gradient-to-br from-white via-neutral-50 to-white p-5 rounded-2xl shadow-lg group-hover:shadow-xl group-hover:shadow-primary-200/50 transition-all duration-300 border border-white/50 group-hover:border-primary-200/50 transform group-hover:rotate-3 group-hover:scale-110">
                  <i class="<?php echo e($category['icon']); ?> text-3xl sm:text-4xl bg-gradient-to-br from-primary-600 via-primary-500 to-secondary-600 bg-clip-text text-transparent group-hover:from-primary-700 group-hover:to-secondary-700 transition-all duration-300"></i>
                </div>

                <!-- Pulsing Ring Animation -->
                <div class="absolute inset-0 rounded-2xl border-2 border-primary-300/30 group-hover:border-primary-400/50 group-hover:scale-125 opacity-0 group-hover:opacity-100 transition-all duration-500 animate-pulse"></div>
              </div>

              <!-- Category Name with Enhanced Typography -->
              <h3 class="font-bold text-lg sm:text-xl text-neutral-800 group-hover:text-primary-700 transition-all duration-300 tracking-tight leading-tight"><?php echo e($category['name']); ?></h3>

              <!-- Subtitle/Description -->
              <p class="text-xs sm:text-sm text-neutral-500 group-hover:text-neutral-600 mt-1 opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-y-2 group-hover:translate-y-0">Browse professionals</p>
            </div>

            <!-- Bottom Accent with Animated Line -->
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-primary-500 via-primary-400 to-secondary-500 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-center"></div>

            <!-- Shine Effect on Hover -->
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000 ease-out"></div>

            <!-- Corner Accent -->
            <div class="absolute top-0 right-0 w-0 h-0 border-l-[20px] border-l-transparent border-t-[20px] border-t-primary-400/20 group-hover:border-t-primary-500/30 transition-all duration-300"></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Advertisement Banner -->
    <div class="mt-20 relative">
      <!-- Background Image -->
      <div class="absolute inset-0">
        <img src="assets/img/electrician.jpg" alt="Advertise" class="w-full h-full object-cover rounded-2xl opacity-40" style="pointer-events:none;">
        <!-- White overlay -->
        <div class="absolute inset-0 bg-white rounded-2xl opacity-50"></div>
      </div>
      <div class="relative z-10 p-8 text-center rounded-2xl border-2 border-dashed border-neutral-300 dark:border-neutral-500 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-[1.02] bg-transparent">
        <div class="max-w-2xl mx-auto">
          <div class="bg-primary-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-bullhorn text-2xl text-primary-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-neutral-700 dark:text-neutral-200 mb-2">Advertise Your Service</h3>
          <p class="text-neutral-600 dark:text-neutral-300 mb-6">Reach thousands of potential customers in your area and grow your service with ServiceLink</p>
          <a href="<?php echo BASE_URL; ?>/login.php" class="inline-flex items-center space-x-2 bg-primary-600 hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600 text-white px-6 py-3 rounded-xl transition-all duration-300 font-semibold shadow-lg hover:shadow-glow transform hover:scale-105">
            <span>Get Started</span>
            <i class="fa-solid fa-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($featuredProviders)): ?>
  <!-- Featured Providers Section -->
  <section class="py-20 bg-gradient-to-b from-white to-neutral-50 relative overflow-hidden">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-5">
      <div class="absolute top-20 right-20 w-32 h-32 bg-primary-200 rounded-full blur-3xl"></div>
      <div class="absolute bottom-20 left-20 w-24 h-24 bg-secondary-200 rounded-full blur-2xl"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
      <div class="text-center mb-16">
        <h2 class="text-3xl sm:text-4xl font-bold text-neutral-900 mb-4">
          Featured Professionals
        </h2>
        <p class="text-xl text-neutral-600 max-w-2xl mx-auto">
          Top-rated service providers ready to help you
        </p>
        <div class="w-24 h-1 bg-gradient-to-r from-primary-500 to-secondary-500 mx-auto mt-6 rounded-full"></div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
        <?php foreach ($featuredProviders as $index => $provider): ?>
          <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 border border-neutral-200 hover:border-primary-300 overflow-hidden transform hover:-translate-y-1 animate-fadeInUp"
            style="animation-delay: <?php echo $index * 0.1; ?>s;">

            <!-- Provider Card -->
            <div class="p-4 sm:p-6">
              <div class="flex items-start space-x-3 sm:space-x-4 mb-3 sm:mb-4">

                <!-- Profile Image -->
                <div class="relative flex-shrink-0">
                  <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden border-3 border-primary-100">
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
                      class="w-full h-full object-cover rounded-full">
                  </div>

                  <!-- Verified Badge -->
                  <?php if ($provider['is_verified']): ?>
                    <div class="absolute -bottom-1 -right-1 bg-green-500 rounded-lg p-1 border-2 border-white shadow-md">
                      <i class="fa-solid fa-check text-white text-xs"></i>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Provider Info -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between mb-2">
                    <div class="flex-1 min-w-0">
                      <h3 class="text-base sm:text-lg font-bold text-neutral-900 mb-1 line-clamp-1">
                        <?php echo e($provider['business_name'] ?: ($provider['first_name'] . ' ' . $provider['last_name'])); ?>
                      </h3>
                      <div class="flex items-center space-x-2 sm:space-x-3 mb-2">
                        <span class="inline-flex items-center px-2 py-1 bg-primary-100 text-primary-700 rounded-full text-xs font-medium">
                          <i class="<?php echo e($provider['category_icon']); ?> mr-1 text-xs"></i>
                          <span class="hidden sm:inline"><?php echo e($provider['category_name']); ?></span>
                          <span class="sm:hidden"><?php echo e(substr($provider['category_name'], 0, 8) . (strlen($provider['category_name']) > 8 ? '...' : '')); ?></span>
                        </span>
                      </div>
                      <span class="text-xs text-neutral-500 flex items-center">
                        <i class="fa-solid fa-location-dot mr-1 text-primary-500"></i>
                        <span class="line-clamp-1"><?php echo e($provider['location']); ?></span>
                      </span>
                    </div>

                    <!-- Rating Badge -->
                    <div class="flex items-center space-x-1 bg-yellow-50 px-2 py-1 rounded-lg border border-yellow-100 ml-2">
                      <i class="fa-solid fa-star text-yellow-400 text-xs"></i>
                      <span class="text-xs font-bold text-yellow-700">
                        <?php echo number_format($provider['rating'], 1); ?>
                      </span>
                      <span class="text-xs text-neutral-500 hidden sm:inline">
                        (<?php echo $provider['review_count']; ?>)
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Description -->
              <?php if (!empty($provider['description'])): ?>
                <p class="text-sm text-neutral-600 mb-3 sm:mb-4 line-clamp-2">
                  <?php echo e($provider['description']); ?>
                </p>
              <?php endif; ?>

              <!-- Stats & Actions -->
              <div class="space-y-3">
                <!-- Stats Row -->
                <div class="flex items-center justify-center space-x-4 bg-neutral-50 rounded-lg p-2 sm:p-3">
                  <div class="text-center flex-1">
                    <div class="text-xs font-semibold text-neutral-600">Experience</div>
                    <div class="text-sm font-bold text-primary-600">
                      <?php echo $provider['experience_years']; ?>+ <span class="hidden sm:inline">years</span><span class="sm:hidden">yrs</span>
                    </div>
                  </div>
                  <div class="w-px h-6 bg-neutral-200"></div>
                  <div class="text-center flex-1">
                    <div class="text-xs font-semibold text-neutral-600">Rate</div>
                    <div class="text-sm font-bold text-secondary-600">
                      <?php echo formatCurrency($provider['hourly_rate']); ?><span class="text-xs">/hr</span>
                    </div>
                  </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-2">
                  <a href="<?php echo BASE_URL; ?>/provider-profile.php?id=<?php echo $provider['id']; ?>"
                    class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-3 py-2 rounded-lg transition-colors font-medium text-xs sm:text-sm text-center"
                    data-provider-id="<?php echo $provider['id']; ?>"
                    title="View <?php echo e($provider['first_name'] . ' ' . $provider['last_name']); ?>'s profile">
                    <i class="fa-solid fa-eye mr-1"></i>
                    View
                  </a>
                  <a href="<?php echo BASE_URL; ?>/contact-provider.php?id=<?php echo $provider['id']; ?>"
                    class="flex-1 bg-white hover:bg-neutral-50 text-neutral-700 px-3 py-2 rounded-lg border border-neutral-200 hover:border-primary-300 transition-colors font-medium text-xs sm:text-sm text-center"
                    data-provider-id="<?php echo $provider['id']; ?>"
                    title="Contact <?php echo e($provider['first_name'] . ' ' . $provider['last_name']); ?>">
                    <i class="fa-solid fa-message mr-1"></i>
                    Contact
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="text-center mt-12">
        <a href="<?php echo BASE_URL; ?>/services.php" class="inline-flex items-center space-x-2 bg-secondary-600 hover:bg-secondary-700 text-white px-8 py-3 rounded-xl transition-all duration-300 font-semibold text-lg transform hover:scale-105 shadow-lg hover:shadow-glow-secondary">
          <span>View All Providers</span>
          <i class="fa-solid fa-arrow-right"></i>
        </a>
      </div>
    </div>
  </section>
<?php endif; ?>

<!-- Call-to-Action Section -->
<section class="relative py-20 bg-gradient-to-r from-secondary-600 to-secondary-700 text-white overflow-hidden">
  <!-- Background Pattern -->
  <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=" 40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" %3E%3Cg fill="none" fill-rule="evenodd" %3E%3Cg fill="%23ffffff" fill-opacity="0.1" %3E%3Cpath d="M20 20c0-5.5-4.5-10-10-10s-10 4.5-10 10 4.5 10 10 10 10-4.5 10-10zm10 0c0-5.5-4.5-10-10-10s-10 4.5-10 10 4.5 10 10 10 10-4.5 10-10z" /%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-20"></div>

  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
    <h3 class="text-3xl sm:text-4xl font-bold mb-6">
      Need a pro this week?
    </h3>
    <p class="text-xl text-secondary-100 max-w-2xl mx-auto mb-10">
      Post a "Wanted" request and get tailored responses from top-rated providers.
    </p>
    <a href="<?php echo BASE_URL; ?>/wanted.php" class="inline-flex items-center space-x-3 bg-white text-secondary-600 px-8 py-4 rounded-xl font-semibold text-lg hover:bg-neutral-100 transition-all duration-300 transform hover:scale-105 shadow-lg">
      <i class="fa-solid fa-plus-circle"></i>
      <span>Post a Wanted</span>
    </a>
  </div>
</section>

<script>
  // Add smooth scrolling and intersection observer for animations
  document.addEventListener('DOMContentLoaded', function() {
    // Intersection Observer for fade-in animations
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, observerOptions);

    // Observe all animated elements
    document.querySelectorAll('.animate-fadeInUp').forEach(el => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(30px)';
      el.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
      observer.observe(el);
    });

    // Add hover effects to category cards
    document.querySelectorAll('[href*="services.php?category="]').forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-8px) scale(1.02)';
      });

      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
      });
    });

    // Add parallax effect to hero section
    window.addEventListener('scroll', function() {
      const scrolled = window.pageYOffset;
      const parallax = document.querySelector('.bg-gradient-to-r.from-primary-600');
      if (parallax) {
        parallax.style.transform = `translateY(${scrolled * 0.5}px)`;
      }
    });

    // Debug provider buttons
    const providerButtons = document.querySelectorAll('[data-provider-id]');
    console.log('Found provider buttons:', providerButtons.length);

    providerButtons.forEach(function(button, index) {
      const providerId = button.getAttribute('data-provider-id');
      const href = button.getAttribute('href');
      const buttonText = button.textContent.trim();
      console.log(`Button ${index + 1}: Provider ID = ${providerId}, Link = ${href}, Text = ${buttonText}`);

      // Add click event to log when buttons are clicked
      button.addEventListener('click', function(e) {
        console.log('Provider button clicked:', {
          providerId: providerId,
          href: href,
          target: buttonText,
          timestamp: new Date().toLocaleTimeString()
        });

        // Visual feedback
        button.style.opacity = '0.7';
        setTimeout(() => {
          button.style.opacity = '1';
        }, 200);

        // Don't prevent default - let the link work normally
      });
    });

    // Also log all featured providers data from PHP
    console.log('Featured providers data:', <?php echo json_encode($featuredProviders); ?>);
  });
</script>

<?php include 'includes/footer.php'; ?>