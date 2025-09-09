<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/ImageUploader.php';

$currentUser = $auth->getCurrentUser();
$db = getDB();

$pageTitle = 'Services • ServiceLink';
$pageDescription = 'Browse and find skilled service providers in your area.';

// Get filter parameters
$category = $_GET['category'] ?? '';
$location = $_GET['location'] ?? '';
$search = $_GET['search'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$rating = $_GET['rating'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = ["p.is_active = 1"];
$params = [];

if ($category) {
  $conditions[] = "c.slug = ?";
  $params[] = $category;
}

if ($location) {
  $conditions[] = "p.location LIKE ?";
  $params[] = "%{$location}%";
}

if ($search) {
  $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR p.business_name LIKE ? OR p.description LIKE ?)";
  $searchTerm = "%{$search}%";
  $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($minPrice) {
  $conditions[] = "p.hourly_rate >= ?";
  $params[] = (float) $minPrice;
}

if ($maxPrice) {
  $conditions[] = "p.hourly_rate <= ?";
  $params[] = (float) $maxPrice;
}

if ($rating) {
  $conditions[] = "p.rating >= ?";
  $params[] = (float) $rating;
}

// Add verified filter condition
if (isset($_GET['verified']) && $_GET['verified'] == '1') {
  $conditions[] = "p.is_verified = 1";
}

// District filter using bounding boxes
$district = $_GET['district'] ?? '';
$district_bounds = [
  'Colombo' => ['min_lat' => 6.800, 'max_lat' => 7.100, 'min_lng' => 79.800, 'max_lng' => 80.100],
  'Gampaha' => ['min_lat' => 7.000, 'max_lat' => 7.200, 'min_lng' => 79.900, 'max_lng' => 80.200],
  'Kalutara' => ['min_lat' => 6.500, 'max_lat' => 6.900, 'min_lng' => 79.900, 'max_lng' => 80.200],
  'Kandy' => ['min_lat' => 7.200, 'max_lat' => 7.500, 'min_lng' => 80.500, 'max_lng' => 80.800],
  'Matale' => ['min_lat' => 7.400, 'max_lat' => 7.700, 'min_lng' => 80.500, 'max_lng' => 80.900],
  'Nuwara Eliya' => ['min_lat' => 6.800, 'max_lat' => 7.100, 'min_lng' => 80.600, 'max_lng' => 80.900],
  'Galle' => ['min_lat' => 5.900, 'max_lat' => 6.300, 'min_lng' => 80.100, 'max_lng' => 80.400],
  'Matara' => ['min_lat' => 5.800, 'max_lat' => 6.200, 'min_lng' => 80.400, 'max_lng' => 80.800],
  'Hambantota' => ['min_lat' => 6.000, 'max_lat' => 6.400, 'min_lng' => 80.600, 'max_lng' => 81.200],
  'Jaffna' => ['min_lat' => 9.400, 'max_lat' => 9.900, 'min_lng' => 79.900, 'max_lng' => 80.300],
  'Kilinochchi' => ['min_lat' => 9.200, 'max_lat' => 9.500, 'min_lng' => 80.100, 'max_lng' => 80.500],
  'Mannar' => ['min_lat' => 8.900, 'max_lat' => 9.200, 'min_lng' => 79.700, 'max_lng' => 80.200],
  'Vavuniya' => ['min_lat' => 8.700, 'max_lat' => 9.100, 'min_lng' => 80.200, 'max_lng' => 80.600],
  'Mullaitivu' => ['min_lat' => 9.000, 'max_lat' => 9.400, 'min_lng' => 80.500, 'max_lng' => 81.000],
  'Batticaloa' => ['min_lat' => 7.600, 'max_lat' => 8.100, 'min_lng' => 81.400, 'max_lng' => 81.900],
  'Ampara' => ['min_lat' => 6.700, 'max_lat' => 7.400, 'min_lng' => 81.400, 'max_lng' => 81.900],
  'Trincomalee' => ['min_lat' => 8.000, 'max_lat' => 8.700, 'min_lng' => 81.000, 'max_lng' => 81.500],
  'Kurunegala' => ['min_lat' => 7.300, 'max_lat' => 8.000, 'min_lng' => 80.100, 'max_lng' => 80.700],
  'Puttalam' => ['min_lat' => 7.800, 'max_lat' => 8.400, 'min_lng' => 79.700, 'max_lng' => 80.300],
  'Anuradhapura' => ['min_lat' => 8.000, 'max_lat' => 8.900, 'min_lng' => 80.200, 'max_lng' => 81.000],
  'Polonnaruwa' => ['min_lat' => 7.700, 'max_lat' => 8.200, 'min_lng' => 80.900, 'max_lng' => 81.300],
  'Badulla' => ['min_lat' => 6.800, 'max_lat' => 7.300, 'min_lng' => 81.000, 'max_lng' => 81.200],
  'Monaragala' => ['min_lat' => 6.600, 'max_lat' => 7.200, 'min_lng' => 81.100, 'max_lng' => 81.500],
  'Ratnapura' => ['min_lat' => 6.400, 'max_lat' => 6.900, 'min_lng' => 80.300, 'max_lng' => 80.800],
  'Kegalle' => ['min_lat' => 6.900, 'max_lat' => 7.300, 'min_lng' => 80.200, 'max_lng' => 80.600],
];

if ($district && isset($district_bounds[$district])) {
  $bounds = $district_bounds[$district];
  $conditions[] = "p.latitude >= ? AND p.latitude <= ? AND p.longitude >= ? AND p.longitude <= ?";
  $params[] = $bounds['min_lat'];
  $params[] = $bounds['max_lat'];
  $params[] = $bounds['min_lng'];
  $params[] = $bounds['max_lng'];
}

$whereClause = implode(" AND ", $conditions);

// Get total count
try {
  $countQuery = "
        SELECT COUNT(*) as count
        FROM providers p 
        JOIN users u ON p.user_id = u.id 
        JOIN categories c ON p.category_id = c.id 
        WHERE {$whereClause}
    ";
  $stmt = $db->prepare($countQuery);
  $stmt->execute($params);
  $totalProviders = $stmt->fetch()['count'];
  $totalPages = ceil($totalProviders / $limit);
} catch (PDOException $e) {
  $totalProviders = 0;
  $totalPages = 1;
}

// Get user's favorite providers first
$favoritedProviderIds = [];
if ($currentUser && $currentUser['role'] === 'user') {
  try {
    $stmt = $db->prepare("SELECT provider_id FROM favorite_providers WHERE customer_id = ?");
    $stmt->execute([$currentUser['id']]);
  $favoritedProviderIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'provider_id');
  } catch (PDOException $e) {
    error_log("Error getting favorites: " . $e->getMessage());
  }
}

// Get providers
try {
  $query = "
        SELECT 
            p.id,
            p.user_id,
            p.category_id,
            p.business_name,
            p.location,
            p.latitude,
            p.longitude,
            p.hourly_rate,
            p.experience_years,
            p.description,
            p.is_verified,
            p.rating,
            p.review_count,
            p.is_active,
            u.first_name,
            u.last_name,
            u.profile_photo,
            c.name as category_name,
            c.icon as category_icon,
            c.slug as category_slug
        FROM providers p 
        JOIN users u ON p.user_id = u.id 
        JOIN categories c ON p.category_id = c.id 
        WHERE {$whereClause}
        ORDER BY p.is_verified DESC, p.rating DESC, p.review_count DESC
        LIMIT ? OFFSET ?
    ";
  $stmt = $db->prepare($query);
  $stmt->execute(array_merge($params, [$limit, $offset]));
  $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Debug log before processing
  error_log("Raw providers found: " . count($providers));
  error_log("Favorited provider IDs: " . implode(", ", $favoritedProviderIds));

  // Initialize isFavorited flag for each provider
  foreach ($providers as &$provider) {
    $provider['isFavorited'] = in_array($provider['id'], $favoritedProviderIds);
  }
  unset($provider); // Break the reference

  // Debug log after processing
  error_log("Processed providers:");
  foreach ($providers as $provider) {
    error_log("Provider: ID={$provider['id']}, UserID={$provider['user_id']}, IsFavorited=" .
      ($provider['isFavorited'] ? 'true' : 'false') .
      ", Name={$provider['first_name']} {$provider['last_name']}");
  }
} catch (PDOException $e) {
  error_log("Error getting providers: " . $e->getMessage());
  $providers = [];
}

// Get categories for filter
try {
  $stmt = $db->prepare("SELECT * FROM categories WHERE active = 1 ORDER BY name ASC");
  $stmt->execute();
  $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $categories = [];
}

// Get selected category name
$selectedCategoryName = '';
if ($category) {
  foreach ($categories as $cat) {
    if ($cat['slug'] === $category) {
      $selectedCategoryName = $cat['name'];
      break;
    }
  }
}

// Include header after processing
include 'includes/header.php';
?>

<!-- Auto-clear all filters on browser refresh (no buttons) -->
<script>
  (function autoClearFiltersOnReload() {
    try {
      const nav = (performance.getEntriesByType && performance.getEntriesByType('navigation'))?.[0];
      const isReload = nav ? nav.type === 'reload' : (performance.navigation && performance.navigation.type === 1);

      if (!isReload) return;

      // Known filter params to clear
      const FILTER_KEYS = new Set([
        'search', 'category', 'district', 'location',
        'min_price', 'max_price', 'verified', 'rating', 'page'
      ]);

      if (!location.search) return;

      const params = new URLSearchParams(location.search);
      const hasFilterParams = Array.from(params.keys()).some(k => FILTER_KEYS.has(k));

      if (hasFilterParams) {
        // Remove query string; preserve hash; no extra history entry
        location.replace(location.origin + location.pathname + location.hash);
      }
    } catch (e) {
      // Safe fallback: if there is any query string, drop it
      if (location.search) {
        location.replace(location.pathname + location.hash);
      }
    }
  })();

  // Optional: ensure inputs visually reset after the clean reload (some browsers restore form state)
  (function ensureUIResetAfterCleanReload() {
    const nav = (performance.getEntriesByType && performance.getEntriesByType('navigation'))?.[0];
    const isReload = nav ? nav.type === 'reload' : (performance.navigation && performance.navigation.type === 1);

    if (isReload && !location.search) {
      window.addEventListener('DOMContentLoaded', () => {
        const ids = ['searchInput', 'categorySelect', 'districtSelect', 'locationInput', 'priceMin', 'priceMax'];
        ids.forEach(id => {
          const el = document.getElementById(id);
          if (!el) return;
          if (el.tagName === 'SELECT') {
            el.value = '';
            el.selectedIndex = 0; // if the first option is "All ..."
          } else {
            el.value = '';
          }
        });

        const verified = document.getElementById('verifiedOnly');
        if (verified) verified.checked = false;
        const skilled = document.getElementById('skilledOnly');
        if (skilled) skilled.checked = false;
      });
    }
  })();
</script>

<!-- Add base URL and configuration for JavaScript -->
<script>
  const BASE_URL = '<?php echo rtrim(BASE_URL, '/'); ?>';
  // Debug output
  console.log('Base URL:', BASE_URL);
</script>

<!-- Load required JavaScript files -->
<script src="<?php echo BASE_URL; ?>/assets/js/toast.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/favorites.js"></script>

<!-- Custom CSS for smooth filtering transitions -->
<style>
  /* Global transition for page elements */
  * {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
  }

  /* Enhanced filter animations */
  .filter-container {
    transform: translateY(0);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .filter-container.filtering {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  }

  /* Provider card animations */
  .provider-card {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateY(0) scale(1);
  }

  .provider-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
  }

  /* Mobile-specific card styles */
  @media (max-width: 640px) {
    .provider-card {
      margin-bottom: 0.5rem;
    }

    .provider-card:hover {
      transform: translateY(-2px) scale(1.01);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    /* Ensure text doesn't overflow on small screens */
    .line-clamp-1 {
      display: -webkit-box;
      -webkit-line-clamp: 1;
      line-clamp: 1;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    /* Mobile button improvements */
    .mobile-button-stack {
      flex-direction: column;
      gap: 0.5rem;
    }
  }

  /* Enhanced responsive text sizing */
  @media (max-width: 475px) {
    .provider-card h3 {
      font-size: 1rem;
      line-height: 1.5;
    }

    .provider-card .text-sm {
      font-size: 0.825rem;
    }

    .provider-card .text-xs {
      font-size: 0.7rem;
    }
  }

  /* Input focus animations */
  input:focus,
  select:focus {
    transform: scale(1.02);
    transition: all 0.3s ease-in-out;
  }

  /* Checkbox and radio animations */
  input[type="checkbox"] {
    transition: all 0.2s ease-in-out;
  }

  input[type="checkbox"]:checked {
    animation: checkboxBounce 0.3s ease-in-out;
  }

  @keyframes checkboxBounce {
    0% {
      transform: scale(1);
    }

    50% {
      transform: scale(1.3);
    }

    100% {
      transform: scale(1);
    }
  }

  /* Loading animation */
  .loading-dots {
    animation: loadingPulse 1.5s ease-in-out infinite;
  }

  @keyframes loadingPulse {

    0%,
    100% {
      opacity: 0.4;
    }

    50% {
      opacity: 1;
    }
  }

  /* Smooth grid transitions */
  .grid {
    transition: all 0.5s ease-in-out;
  }

  /* Page fade-in animation */
  .page-content {
    animation: fadeInUp 0.6s ease-out;
  }

  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* Button hover effects */
  button,
  .btn {
    transition: none;
    /* Removed transition for hover effects */
  }

  button:hover,
  .btn:hover {
    transform: none;
    /* Removed hover transform */
    box-shadow: none;
    /* Removed hover shadow */
  }

  /* Search bar pulse effect */
  #searchInput:focus {
    animation: searchPulse 0.5s ease-in-out;
  }

  @keyframes searchPulse {
    0% {
      box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4);
    }

    70% {
      box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
    }

    100% {
      box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
    }
  }

  /* Custom map marker styles */
  .custom-marker-with-label {
    z-index: 1000 !important;
  }

  .custom-marker-with-label:hover {
    z-index: 1001 !important;
  }

  /* Leaflet popup customization */
  .leaflet-popup-content-wrapper {
    border-radius: 8px !important;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
  }

  .leaflet-popup-tip {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
  }

  /* Mobile Map Modal Styles */
  .map-modal {
    animation: modalSlideIn 0.3s ease-out;
  }

  .map-modal.closing {
    animation: modalSlideOut 0.3s ease-in;
  }

  @keyframes modalSlideIn {
    from {
      opacity: 0;
      transform: translateY(100%);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @keyframes modalSlideOut {
    from {
      opacity: 1;
      transform: translateY(0);
    }

    to {
      opacity: 0;
      transform: translateY(100%);
    }
  }

  /* Modal backdrop animation */
  .modal-backdrop {
    animation: backdropFadeIn 0.3s ease-out;
  }

  .modal-backdrop.closing {
    animation: backdropFadeOut 0.3s ease-in;
  }

  @keyframes backdropFadeIn {
    from {
      opacity: 0;
    }

    to {
      opacity: 1;
    }
  }

  @keyframes backdropFadeOut {
    from {
      opacity: 1;
    }

    to {
      opacity: 0;
    }
  }

  /* Ensure mobile map fills container */
  #mobileServicesMap {
    min-height: 180px;
    max-height: 200px;
  }

  /* Compact mobile modal styles */
  .mobile-modal-compact {
    max-width: 400px;
    max-height: 500px;
  }

  @media (max-width: 480px) {
    .mobile-modal-compact {
      max-width: calc(100vw - 1rem);
      max-height: calc(100vh - 2rem);
    }

    #mobileServicesMap {
      min-height: 250px;
      max-height: 350px;
    }
  }

  @media (max-width: 360px) {
    #mobileServicesMap {
      min-height: 180px;
      max-height: 220px;
    }

    .mobile-modal-compact {
      max-height: calc(100vh - 3rem);
    }
  }

  /* Mobile map marker adjustments */
  @media (max-width: 768px) {
    .custom-marker-with-label {
      transform: scale(0.9);
    }

    .leaflet-popup-content-wrapper {
      max-width: 200px !important;
      min-width: 160px !important;
    }

    .leaflet-popup-content {
      margin: 8px 6px !important;
      line-height: 1.2 !important;
    }
  }

  @media (max-width: 480px) {
    .leaflet-popup-content-wrapper {
      max-width: 180px !important;
      min-width: 140px !important;
    }

    .leaflet-popup-content {
      margin: 6px 4px !important;
    }
  }

  /* Mobile filters dropdown animation */
  @media (max-width: 767px) {
    #filtersPanel {
      overflow: hidden;
      max-height: 0;
      /* collapsed default; JS removes this when opening */
      opacity: 0;
      transition: max-height 0.3s ease, opacity 0.2s ease;
    }

    body.mobile-filter-open #filtersPanel {
      opacity: 1;
    }

    .results-grid {
      transition: margin-top 0.2s ease;
    }

    body.mobile-filter-open .results-grid {
      margin-top: 0.25rem;
      /* slight spacing adjustment when open */
    }
  }
</style>

<!-- Leaflet.js CSS and JS for OpenStreetMap -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
  integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
  integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div
  class="min-h-screen bg-gradient-to-br from-neutral-50 to-neutral-100 dark:from-neutral-900 dark:to-neutral-800 text-neutral-900 dark:text-neutral-100 transition-colors duration-300 page-content">
  <!-- Main Content -->
  <main class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Page Header -->
      <div class="mb-8">
        <h1 class="text-3xl sm:text-4xl font-bold text-neutral-900 mb-4">
          <?php echo $selectedCategoryName ? e($selectedCategoryName) : 'Browse Services'; ?>
        </h1>
        <p class="text-lg text-neutral-600">
          Find the perfect professional for your needs, <?php echo $totalProviders; ?>
          provider<?php echo $totalProviders !== 1 ? 's' : ''; ?> found
        </p>
      </div>

      <!-- Search and Filters -->
      <div
        class="bg-white dark:bg-neutral-800 rounded-xl shadow-lg border border-neutral-200 dark:border-neutral-700 p-6 mb-8 filter-container">
        <!-- Search Bar -->
        <div class="flex flex-col lg:flex-row gap-4 mb-6">
          <div class="flex-1 relative mb-0 md:mb-0">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fa-solid fa-magnifying-glass text-neutral-400 dark:text-neutral-500"></i>
            </div>
            <input type="text" id="searchInput" name="search" value="<?php echo e($search); ?>"
              class="block w-full pl-10 pr-3 py-3 border border-neutral-300 dark:border-neutral-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-neutral-700 dark:text-neutral-100 placeholder-neutral-500 dark:placeholder-neutral-400 transition-colors"
              placeholder="Search by name, service, or keyword..." />
          </div>
          <div class="flex gap-3 mb-0 md:mb-0">
            <button type="button" id="searchButton"
              class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg transition-colors font-medium flex items-center space-x-2 shadow-lg hover:shadow-xl">
              <i class="fa-solid fa-search"></i>
              <span>Search</span>
            </button>
            <button type="button" id="clearFilters"
              class="bg-neutral-100 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 px-6 py-3 rounded-lg hover:bg-neutral-200 dark:hover:bg-neutral-600 transition-colors font-medium flex items-center space-x-2">
              <i class="fa-solid fa-rotate-left"></i>
              <span>Reset</span>
            </button>
          </div>
        </div>

        <!-- Mobile Filters Toggle -->
        <div class="md:hidden mb-4">
          <button
            type="button"
            id="mobileFiltersToggle"
            aria-controls="filtersPanel"
            aria-expanded="false"
            class="w-full flex items-center justify-between bg-neutral-100 dark:bg-neutral-700 text-neutral-800 dark:text-neutral-100 px-4 py-3 rounded-lg border border-neutral-200 dark:border-neutral-600">
            <span class="flex items-center">
              <i class="fa-solid fa-sliders mr-2 text-primary-600"></i>
              Filters
              <span id="activeFiltersBadge"
                class="ml-2 hidden min-w-[22px] h-[22px] text-xs inline-flex items-center justify-center rounded-full bg-primary-600 text-white"></span>
            </span>
            <i id="filtersChevron" class="fa-solid fa-chevron-down text-neutral-500 transition-transform duration-200"></i>
          </button>
        </div>

        <!-- Filter Options -->
        <div id="filtersPanel" class="hidden md:grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 transition-all duration-300">
          <!-- District Filter -->
          <div class="transition-all duration-200 mb-4 md:mb-0">
            <label for="districtSelect" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">District</label>
            <select id="districtSelect" name="district" form="filterForm" class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-neutral-700 dark:text-neutral-100 transition-all duration-300 focus:shadow-lg">
              <?php $districts = [
                '' => 'All districts',
                'Colombo' => 'Colombo',
                'Gampaha' => 'Gampaha',
                'Kalutara' => 'Kalutara',
                'Kandy' => 'Kandy',
                'Matale' => 'Matale',
                'Nuwara Eliya' => 'Nuwara Eliya',
                'Galle' => 'Galle',
                'Matara' => 'Matara',
                'Hambantota' => 'Hambantota',
                'Jaffna' => 'Jaffna',
                'Kilinochchi' => 'Kilinochchi',
                'Mannar' => 'Mannar',
                'Vavuniya' => 'Vavuniya',
                'Mullaitivu' => 'Mullaitivu',
                'Batticaloa' => 'Batticaloa',
                'Ampara' => 'Ampara',
                'Trincomalee' => 'Trincomalee',
                'Kurunegala' => 'Kurunegala',
                'Puttalam' => 'Puttalam',
                'Anuradhapura' => 'Anuradhapura',
                'Polonnaruwa' => 'Polonnaruwa',
                'Badulla' => 'Badulla',
                'Monaragala' => 'Monaragala',
                'Ratnapura' => 'Ratnapura',
                'Kegalle' => 'Kegalle',
              ];
              foreach ($districts as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo ($district === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Category Filter -->
          <div class="transition-all duration-200 mb-4 md:mb-0">
            <label for="categorySelect"
              class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">Category</label>
            <select id="categorySelect" name="category" form="filterForm"
              class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-neutral-700 dark:text-neutral-100 transition-all duration-300 focus:shadow-lg">
              <option value="">All categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo e($cat['slug']); ?>" <?php echo $category === $cat['slug'] ? 'selected' : ''; ?>>
                  <?php echo e($cat['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Price Range -->
          <div class="transition-all duration-200 mb-4 md:mb-0">
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">Price range
              (Rs.)</label>
            <div class="flex items-center space-x-2">
              <input type="number" id="priceMin" name="min_price" value="<?php echo e($minPrice); ?>" placeholder="Min"
                min="0"
                class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-neutral-700 dark:text-neutral-100 transition-all duration-300 focus:shadow-lg" />
              <span class="text-neutral-500 dark:text-neutral-400">—</span>
              <input type="number" id="priceMax" name="max_price" value="<?php echo e($maxPrice); ?>" placeholder="Max"
                min="0"
                class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-neutral-700 dark:text-neutral-100 transition-all duration-300 focus:shadow-lg" />
            </div>
          </div>

            <!-- Filters -->
            <div class="transition-all duration-200 mb-4 md:mb-0">
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">Filters</label>
            <div class="flex items-center space-x-6 mt-5">
              <label class="flex items-center cursor-pointer">
                <input type="checkbox" id="verifiedOnly" name="verified" value="1" <?php echo (isset($_GET['verified']) && $_GET['verified'] == '1') ? 'checked' : ''; ?>
                  class="text-primary-600 border-neutral-300 dark:border-neutral-600 rounded focus:ring-primary-500 dark:bg-neutral-700 transition-all duration-200" />
                <span class="ml-2 text-sm text-neutral-700 dark:text-neutral-300 transition-colors duration-200">Verified</span>
              </label>
              <label class="flex items-center cursor-pointer">
                <input type="checkbox" id="skilledOnly" name="rating" value="4" <?php echo $rating == '4' ? 'checked' : ''; ?>
                  class="text-primary-600 border-neutral-300 dark:border-neutral-700 rounded focus:ring-primary-500 dark:bg-neutral-700 transition-all duration-200" />
                <span class="ml-2 text-sm text-neutral-700 dark:text-neutral-300 transition-colors duration-200">Rated</span>
              </label>
            </div>
          </div>

          <!-- Location -->
          <div class="transition-all duration-200 mb-4 md:mb-0">
            <label for="locationInput"
              class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">Location</label>
            <input type="text" id="locationInput" name="location" value="<?php echo e($location); ?>"
              placeholder="City or area"
              class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-neutral-700 dark:text-neutral-100 transition-all duration-300 focus:shadow-lg" />
          </div>
          <!-- Apply Filters Button in grid -->
          <div class="flex items-end pt-2 mb-4 md:mb-0">
            <button type="button" id="applyFilters"
              class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg transition-colors font-medium flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl">
              <i class="fa-solid fa-filter"></i>
              <span>Apply Filters</span>
            </button>
          </div>
        </div>
        <!-- Manual Filter Button Row -->
        <!-- Removed manual filter button row, now in grid above -->
      </div>
      <style>
        @media (max-width: 767px) {

          #filtersPanel>div,
          .filter-container>.flex,
          .filter-container>.md\:hidden {
            margin-bottom: 1rem !important;
          }

          #filtersPanel>div:last-child {
            margin-bottom: 0 !important;
          }
        }
      </style>

      <!-- Loading Indicator -->
      <div id="loadingIndicator" class="hidden mt-4 text-center">
        <div class="inline-flex items-center px-4 py-2 bg-primary-100 text-primary-600 rounded-lg animate-pulse">
          <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none"
            viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
            </path>
          </svg>
          Applying filters...
        </div>
      </div>
    </div>

    <!-- Results Layout -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 results-grid">
        <!-- Provider List -->
        <div class="lg:col-span-2">
          <div id="providersList" class="space-y-4">
            <?php if (empty($providers)): ?>
              <!-- No Results -->
              <div
                class="bg-white dark:bg-neutral-800 rounded-xl shadow-lg border border-neutral-200 dark:border-neutral-700 p-12 text-center">
                <div
                  class="bg-neutral-100 dark:bg-neutral-700 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                  <i class="fa-solid fa-search text-2xl text-neutral-400 dark:text-neutral-500"></i>
                </div>
                <h3 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100 mb-2">No providers found</h3>
                <p class="text-neutral-600 dark:text-neutral-400 mb-6">Try adjusting your filters or search terms.</p>
                <a href="<?php echo BASE_URL; ?>/services.php"
                  class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg transition-colors font-medium">
                  View All Providers
                </a>
              </div>

            <?php else: ?>

              <!-- Providers List -->
              <?php foreach ($providers as $provider): ?>
                <div
                  class="bg-white dark:bg-neutral-800 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 border border-neutral-200 dark:border-neutral-700 hover:border-primary-300 dark:hover:border-primary-600 overflow-hidden provider-card">

                  <!-- Provider Card -->
                  <div class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-start space-y-4 sm:space-y-0 sm:space-x-4">

                      <!-- Mobile Header: Profile Image + Basic Info -->
                      <div class="flex items-center space-x-4 sm:flex-col sm:space-x-0 sm:space-y-2">
                        <!-- Profile Image -->
                        <div class="relative flex-shrink-0">
                          <div
                            class="w-16 h-16 sm:w-20 sm:h-20 rounded-full overflow-hidden border-3 border-primary-100 dark:border-primary-800">
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
                            <div
                              class="absolute -bottom-1 -right-1 bg-green-500 rounded-lg p-1 border-2 border-white dark:border-neutral-800 shadow-md">
                              <i class="fa-solid fa-check text-white text-xs"></i>
                            </div>
                          <?php endif; ?>
                        </div>

                        <!-- Mobile: Name and Rating -->
                        <div class="flex-1 sm:hidden">
                          <div class="flex items-center justify-between mb-1">
                            <h3 class="text-lg font-bold text-neutral-900 dark:text-neutral-100 line-clamp-1">
                              <?php echo e($provider['business_name'] ?: ($provider['first_name'] . ' ' . $provider['last_name'])); ?>
                            </h3>
                            <!-- Mobile Favorite Heart -->
                            <?php if ($currentUser && $currentUser['role'] === 'user'): ?>
                              <button
                                class="favorite-btn text-xl <?php echo (!empty($provider['isFavorited'])) ? 'favorited text-yellow-400' : 'text-neutral-300'; ?> hover:text-yellow-400 transition-colors ml-2"
                                onclick="toggleFavorite(this, '<?php echo htmlspecialchars($provider['id']); ?>')"
                                data-provider-id="<?php echo htmlspecialchars($provider['id']); ?>"
                                title="<?php echo (!empty($provider['isFavorited'])) ? 'Remove from favorites' : 'Add to favorites'; ?>">
                                <i class="<?php echo (!empty($provider['isFavorited'])) ? 'fas' : 'far'; ?> fa-heart"></i>
                              </button>
                            <?php endif; ?>
                          </div>
                          <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                              <div
                                class="flex items-center space-x-1 bg-yellow-50 dark:bg-yellow-900/20 px-2 py-1 rounded text-xs">
                                <i class="fa-solid fa-star text-yellow-400"></i>
                                <span class="font-bold text-yellow-700 dark:text-yellow-300">
                                  <?php echo number_format($provider['rating'], 1); ?>
                                </span>
                              </div>
                              <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                (<?php echo $provider['review_count']; ?> reviews)
                              </span>
                            </div>
                            <!-- Mobile Rate Button -->
                            <?php if ($currentUser && $currentUser['role'] === 'user'): ?>
                              <button
                                class="text-xs text-neutral-500 hover:text-primary-600 dark:text-neutral-400 dark:hover:text-primary-400 transition-colors"
                                onclick="showRatingModal(<?php echo $provider['id']; ?>, '<?php echo addslashes($provider['business_name'] ?: ($provider['first_name'] . ' ' . $provider['last_name'])); ?>')">
                                <i class="fa-regular fa-star"></i>
                                <span>Rate</span>
                              </button>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>

                      <!-- Provider Info -->
                      <div class="flex-1 min-w-0">
                        <!-- Desktop: Name and Rating -->
                        <div class="hidden sm:flex sm:items-center sm:justify-between mb-2">
                          <div class="flex items-center gap-3">
                            <h3 class="text-xl font-bold text-neutral-900 dark:text-neutral-100">
                              <?php echo e($provider['business_name'] ?: ($provider['first_name'] . ' ' . $provider['last_name'])); ?>
                            </h3>

                            <!-- Desktop Rating Badge -->
                            <div
                              class="flex items-center space-x-1 bg-yellow-50 dark:bg-yellow-900/20 px-3 py-1 rounded-lg border border-yellow-100 dark:border-yellow-800">
                              <i class="fa-solid fa-star text-yellow-400"></i>
                              <span class="text-sm font-bold text-yellow-700 dark:text-yellow-300">
                                <?php echo number_format($provider['rating'], 1); ?>
                              </span>
                              <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                (<?php echo $provider['review_count']; ?>)
                              </span>
                            </div>
                            <!-- Rate Provider Button (only for logged-in users with role 'user') -->
                            <?php if ($currentUser && $currentUser['role'] === 'user'): ?>
                              <button
                                class="text-sm text-neutral-500 hover:text-primary-600 dark:text-neutral-400 dark:hover:text-primary-400 transition-colors"
                                onclick="showRatingModal(<?php echo $provider['id']; ?>, '<?php echo addslashes($provider['business_name'] ?: ($provider['first_name'] . ' ' . $provider['last_name'])); ?>')">
                                <i class="fa-regular fa-star"></i>
                                <span>Rate</span>
                              </button>
                            <?php endif; ?>
                          </div>

                          <!-- Desktop Favorite Heart -->
                          <?php if ($currentUser && $currentUser['role'] === 'user'): ?>
                            <button
                              class="favorite-btn text-2xl <?php echo (!empty($provider['isFavorited'])) ? 'favorited text-yellow-400' : 'text-neutral-300'; ?> hover:text-yellow-400 transition-colors"
                              onclick="toggleFavorite(this, '<?php echo htmlspecialchars($provider['id']); ?>')"
                              data-provider-id="<?php echo htmlspecialchars($provider['id']); ?>"
                              title="<?php echo (!empty($provider['isFavorited'])) ? 'Remove from favorites' : 'Add to favorites'; ?>">
                              <i class="<?php echo (!empty($provider['isFavorited'])) ? 'fas' : 'far'; ?> fa-heart"></i>
                            </button>
                          <?php endif; ?>
                        </div>

                        <!-- Category and Location -->
                        <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-3 mb-3">
                          <span
                            class="inline-flex items-center px-3 py-1 bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 rounded-full text-sm font-medium w-fit">
                            <i class="<?php echo e($provider['category_icon']); ?> mr-2"></i>
                            <?php echo e($provider['category_name']); ?>
                          </span>
                          <span class="text-sm text-neutral-500 dark:text-neutral-400 flex items-center">
                            <i class="fa-solid fa-location-dot mr-1 text-primary-500"></i>
                            <?php echo e($provider['location']); ?>
                          </span>
                        </div>

                        <!-- Description -->
                        <?php if (!empty($provider['description'])): ?>
                          <p class="text-sm text-neutral-600 dark:text-neutral-300 mb-4 line-clamp-2">
                            <?php echo e($provider['description']); ?>
                          </p>
                        <?php endif; ?>

                        <!-- Stats & Actions -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                          <!-- Stats -->
                          <div class="flex items-center justify-center sm:justify-start space-x-6">
                            <div class="text-center">
                              <div class="text-xs sm:text-sm font-semibold text-neutral-600 dark:text-neutral-400">
                                Experience</div>
                              <div class="text-sm sm:text-lg font-bold text-primary-600 dark:text-primary-400">
                                <?php echo $provider['experience_years']; ?>+ years
                              </div>
                            </div>
                            <div class="w-px h-8 bg-neutral-200 dark:bg-neutral-600"></div>
                            <div class="text-center">
                              <div class="text-xs sm:text-sm font-semibold text-neutral-600 dark:text-neutral-400">Rate
                              </div>
                              <div class="text-sm sm:text-lg font-bold text-secondary-600 dark:text-secondary-400">
                                <?php echo formatCurrency($provider['hourly_rate']); ?>/hr
                              </div>
                            </div>
                          </div>

                          <!-- Action Buttons -->
                          <div class="flex space-x-2 w-full sm:w-auto">
                            <a href="<?php echo BASE_URL; ?>/provider-profile.php?id=<?php echo $provider['id']; ?>"
                              class="flex-1 sm:flex-none bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors font-medium text-sm text-center">
                              <i class="fa-solid fa-eye mr-1"></i>
                              <span class="hidden sm:inline">View</span>
                              <span class="sm:hidden">View Profile</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/contact-provider.php?id=<?php echo $provider['id']; ?>"
                              class="flex-1 sm:flex-none bg-white hover:bg-neutral-50 dark:bg-neutral-700 dark:hover:bg-neutral-600 text-neutral-700 dark:text-neutral-300 px-4 py-2 rounded-lg border-2 border-neutral-200 dark:border-neutral-600 hover:border-primary-300 dark:hover:border-primary-500 transition-colors font-medium text-sm text-center">
                              <i class="fa-solid fa-message mr-1"></i>
                              <span class="hidden sm:inline">Contact</span>
                              <span class="sm:hidden">Contact</span>
                            </a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>

            <?php endif; ?>
          </div>
        </div>

        <!-- Map (Desktop Only) -->
        <div class="lg:col-span-1 hidden lg:block">
          <div class="sticky top-24">
            <div
              class="bg-white dark:bg-neutral-800 rounded-xl shadow-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden">
              <div class="p-4 bg-neutral-50 dark:bg-neutral-700/50 border-b border-neutral-200 dark:border-neutral-700">
                <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 flex items-center">
                  <i class="fa-solid fa-map-location-dot text-primary-600 dark:text-primary-400 mr-2"></i>
                  Service Locations
                </h3>
              </div>
              <div id="servicesMap" class="h-96 bg-neutral-100 dark:bg-neutral-700 relative">
                <!-- Map will be initialized here by JavaScript -->
                <div id="mapLoadingIndicator"
                  class="absolute inset-0 flex items-center justify-center bg-neutral-100 dark:bg-neutral-700 z-10">
                  <div class="text-center text-neutral-500 dark:text-neutral-400">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto mb-2"></div>
                    <p class="text-sm">Loading map...</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div class="flex justify-center mt-8">
          <nav class="flex items-center space-x-2">
            <?php if ($page > 1): ?>
              <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                class="px-3 py-2 text-neutral-600 dark:text-neutral-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                <i class="fa-solid fa-chevron-left"></i>
              </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
              <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                class="px-3 py-2 <?php echo $i === $page ? 'bg-primary-600 text-white' : 'text-neutral-600 dark:text-neutral-400 hover:text-primary-600 dark:hover:text-primary-400'; ?> rounded-lg transition-colors">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
              <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                class="px-3 py-2 text-neutral-600 dark:text-neutral-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                <i class="fa-solid fa-chevron-right"></i>
              </a>
            <?php endif; ?>
          </nav>
        </div>
      <?php endif; ?>

    </div>
  </main>

  <!-- Floating Map Button (Mobile/Tablet Only) -->
  <button id="mapButton" onclick="showMobileMap()"
    class="lg:hidden fixed right-0 top-3/4 transform -translate-y-1/2 bg-primary-600 hover:bg-primary-700 text-white p-3 rounded-l-lg shadow-lg z-40 transition-all duration-300 hover:scale-105 hover:shadow-xl">
    <i class="fa-solid fa-map-location-dot text-lg"></i>
  </button>

  <!-- Mobile/Tablet Map Popup Modal -->
  <div id="mobileMapModal" class="lg:hidden fixed inset-0 z-50 hidden">
    <!-- Modal Backdrop -->
    <div id="mapModalBackdrop"
      class="absolute inset-0 bg-black bg-opacity-50 backdrop-blur-sm transition-opacity duration-300"></div>

    <!-- Modal Container - Reduced Size -->
    <div class="relative h-full flex items-center justify-center p-4">
      <div
        class="bg-white dark:bg-neutral-800 rounded-xl shadow-2xl w-full max-w-md h-auto max-h-80 flex flex-col overflow-hidden mobile-modal-compact">
        <!-- Modal Header - Compact -->
        <div
          class="bg-white dark:bg-neutral-800 shadow-sm border-b border-neutral-200 dark:border-neutral-700 p-3 flex items-center justify-between">
          <div>
            <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 flex items-center">
              <i class="fa-solid fa-map-location-dot text-primary-600 dark:text-primary-400 mr-2 text-sm"></i>
              Service Locations
            </h3>
            <p class="text-xs text-neutral-600 dark:text-neutral-400 mt-0.5">
              <?php echo count($providers); ?> providers nearby
            </p>
          </div>
          <button id="closeMobileMapBtn"
            class="p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors">
            <i class="fa-solid fa-times text-neutral-500 dark:text-neutral-400 text-lg"></i>
          </button>
        </div>

        <!-- Modal Content - Map -->
        <div class="flex-1 relative">
          <div id="mobileServicesMap" class="w-full h-full">
            <!-- Map will be initialized here -->
            <div id="mobileMapLoadingIndicator"
              class="absolute inset-0 flex items-center justify-center bg-neutral-100 dark:bg-neutral-700 z-10">
              <div class="text-center text-neutral-500 dark:text-neutral-400">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600 mx-auto mb-2"></div>
                <p class="text-xs">Loading map...</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer - Compact Stats -->
        <div class="bg-white dark:bg-neutral-800 border-t border-neutral-200 dark:border-neutral-700 p-2">
          <div class="grid grid-cols-3 gap-2 text-center">
            <div>
              <div class="text-sm font-bold text-primary-600 dark:text-primary-400">
                <?php echo count($providers); ?>
              </div>
              <div class="text-[10px] text-neutral-600 dark:text-neutral-400">Total</div>
            </div>
            <div>
              <div class="text-sm font-bold text-green-600 dark:text-green-400">
                <?php echo count(array_filter($providers, function ($p) {
                  return $p['is_verified'];
                })); ?>
              </div>
              <div class="text-[10px] text-neutral-600 dark:text-neutral-400">Verified</div>
            </div>
            <div>
              <div class="text-sm font-bold text-yellow-600 dark:text-yellow-400">
                <?php
                $categoryCount = count(array_unique(array_column($providers, 'category_name')));
                echo $categoryCount;
                ?>
              </div>
              <div class="text-[10px] text-neutral-600 dark:text-neutral-400">Categories</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter Form for JavaScript -->
  <form id="filterForm" method="GET" action="" style="display: none;">
    <input type="hidden" name="search" id="hiddenSearch">
    <input type="hidden" name="category" id="hiddenCategory">
    <input type="hidden" name="location" id="hiddenLocation">
    <input type="hidden" name="min_price" id="hiddenMinPrice">
    <input type="hidden" name="max_price" id="hiddenMaxPrice">
    <input type="hidden" name="verified" id="hiddenVerified">
    <input type="hidden" name="rating" id="hiddenRating">
    <input type="hidden" name="district" id="hiddenDistrict">
  </form>

  <!-- Provider Data for Map -->
  <script>
    // Provider locations data for map
    // Function to toggle favorite status
    function showToast(message, type = 'error') {
      const toast = document.createElement('div');
      toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in ${type === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'
        }`;
      toast.textContent = message;
      document.body.appendChild(toast);

      setTimeout(() => {
        toast.classList.add('animate-fade-out');
        setTimeout(() => toast.remove(), 300);
      }, 3000);
    }

    function toggleFavorite(button, providerId) {
      // Prevent double-clicks
      if (button.disabled) return;

      // Disable button and show loading state
      button.disabled = true;
      const icon = button.querySelector('i');
      const isAdding = icon.classList.contains('far');

      // Add loading animation
      button.classList.add('animate-pulse');

      fetch('api/favorites.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            provider_id: providerId,
            action: isAdding ? 'add' : 'remove'
          })
        })
        .then(response => {
          if (!response.ok) {
            throw new Error(response.status === 401 ? 'Please log in to add favorites' : 'Network response was not ok');
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            // Update all instances of this provider's favorite button
            const buttons = document.querySelectorAll(`.favorite-btn[data-provider-id="${providerId}"]`);
            buttons.forEach(btn => {
              const btnIcon = btn.querySelector('i');
              if (isAdding) {
                btnIcon.classList.remove('far');
                btnIcon.classList.add('fas', 'text-yellow-400');
                btn.classList.remove('text-neutral-300');
                btn.classList.add('text-yellow-400');
                // Add bounce animation
                btnIcon.classList.add('animate-bounce');
                setTimeout(() => btnIcon.classList.remove('animate-bounce'), 1000);
              } else {
                btnIcon.classList.remove('fas', 'text-yellow-400');
                btnIcon.classList.add('far');
                btn.classList.remove('text-yellow-400');
                btn.classList.add('text-neutral-300');
              }
              // Update tooltip
              btn.title = isAdding ? 'Remove from favorites' : 'Add to favorites';
            });

            // Show success message
            showToast(isAdding ? 'Added to favorites' : 'Removed from favorites', 'success');
          } else {
            throw new Error(data.message || 'Failed to update favorite');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          // Show error message to user
          showToast(error.message || 'Failed to update favorite status');
        });
    }

    const providerLocations = <?php
                              // Get favorites for current user if logged in
                              $favorites = [];
                              if ($currentUser && $currentUser['role'] === 'user') {
                                try {
                                  $stmt = $db->prepare("SELECT provider_id FROM favorites WHERE user_id = ?");
                                  $stmt->execute([$currentUser['id']]);
                                  $favorites = array_column($stmt->fetchAll(), 'provider_id');
                                } catch (PDOException $e) {
                                  // Silently handle error
                                }
                              }

                              $mapData = [];
                              foreach ($providers as $provider) {
                                // Use actual latitude and longitude from providers table
                                $lat = $provider['latitude'];
                                $lng = $provider['longitude'];

                                $mapData[] = [
                                  'id' => $provider['id'],
                                  'name' => $provider['business_name'] ?: ($provider['first_name'] . ' ' . $provider['last_name']),
                                  'category' => $provider['category_name'],
                                  'categoryIcon' => $provider['category_icon'],
                                  'location' => $provider['location'],
                                  'rating' => $provider['rating'],
                                  'hourlyRate' => $provider['hourly_rate'],
                                  'isVerified' => $provider['is_verified'],
                                  'profilePhoto' => $provider['profile_photo'],
                                  'lat' => $lat,
                                  'lng' => $lng,
                                  'profileUrl' => BASE_URL . '/provider-profile.php?id=' . $provider['id']
                                ];
                              }

                              // Debug: Output unique categories found
                              $uniqueCategories = array_unique(array_column($mapData, 'category'));
                              echo "<!-- Debug: Categories found: " . implode(', ', $uniqueCategories) . " -->\n";

                              echo json_encode($mapData);
                              ?>;
  </script>

  <script>
    // Enhanced smooth filtering with transitions
    // Global state management
    let isFiltering = false;

    document.addEventListener('DOMContentLoaded', function() {
      const mobileToggle = document.getElementById('mobileFiltersToggle');
      const panel = document.getElementById('filtersPanel');
      const chevron = document.getElementById('filtersChevron');
      const badge = document.getElementById('activeFiltersBadge');

      function computeActiveFilters() {
        let count = 0;
        if (document.getElementById('searchInput')?.value.trim()) count++;
        if (document.getElementById('categorySelect')?.value) count++;
        if (document.getElementById('districtSelect')?.value) count++;
        if (document.getElementById('locationInput')?.value.trim()) count++;
        if (document.getElementById('priceMin')?.value) count++;
        if (document.getElementById('priceMax')?.value) count++;
        if (document.getElementById('verifiedOnly')?.checked) count++;
        if (document.getElementById('skilledOnly')?.checked) count++;
        if (badge) {
          if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('hidden');
          } else {
            badge.textContent = '';
            badge.classList.add('hidden');
          }
        }
      }

      function openPanel() {
        if (!panel) return;
        panel.classList.remove('hidden');
        panel.style.maxHeight = panel.scrollHeight + 'px'; // animate down
        document.body.classList.add('mobile-filter-open');
        mobileToggle?.setAttribute('aria-expanded', 'true');
        chevron?.classList.add('rotate-180');
        setTimeout(() => {
          panel.style.maxHeight = 'none'; // allow internal growth after animation
        }, 300);
      }

      function closePanel() {
        if (!panel) return;
        panel.style.maxHeight = panel.scrollHeight + 'px'; // set current height
        panel.offsetHeight; // reflow
        panel.style.maxHeight = '0px'; // animate up
        chevron?.classList.remove('rotate-180');
        document.body.classList.remove('mobile-filter-open');
        mobileToggle?.setAttribute('aria-expanded', 'false');
        panel.addEventListener(
          'transitionend',
          () => {
            panel.classList.add('hidden');
            panel.style.maxHeight = '';
          }, {
            once: true
          }
        );
      }

      mobileToggle?.addEventListener('click', () => {
        if (panel.classList.contains('hidden')) openPanel();
        else closePanel();
      });

      // Keep correct state when resizing
      const mq = window.matchMedia('(min-width: 768px)');

      function handleMq(e) {
        if (e.matches) {
          // md and up: always open
          panel?.classList.remove('hidden');
          panel?.style.removeProperty('max-height');
          document.body.classList.remove('mobile-filter-open');
          chevron?.classList.remove('rotate-180');
          mobileToggle?.setAttribute('aria-expanded', 'true');
        } else {
          // below md: collapse by default
          if (panel && !document.body.classList.contains('mobile-filter-open')) {
            panel.classList.add('hidden');
            panel.style.removeProperty('max-height');
            mobileToggle?.setAttribute('aria-expanded', 'false');
          }
        }
      }
      mq.addEventListener ? mq.addEventListener('change', handleMq) : mq.addListener(handleMq);
      handleMq(mq);

      // Update active filter count initially and on changes
      computeActiveFilters();
      ['input', 'change'].forEach((evt) => {
        document.getElementById('searchInput')?.addEventListener(evt, computeActiveFilters);
        document.getElementById('categorySelect')?.addEventListener(evt, computeActiveFilters);
        document.getElementById('districtSelect')?.addEventListener(evt, computeActiveFilters);
        document.getElementById('locationInput')?.addEventListener(evt, computeActiveFilters);
        document.getElementById('priceMin')?.addEventListener(evt, computeActiveFilters);
        document.getElementById('priceMax')?.addEventListener(evt, computeActiveFilters);
        document.getElementById('verifiedOnly')?.addEventListener(evt, computeActiveFilters);
        document.getElementById('skilledOnly')?.addEventListener(evt, computeActiveFilters);
      });

      // Close the panel on Apply/Reset (mobile), then continue your existing logic
      document.getElementById('applyFilters')?.addEventListener('click', () => {
        if (window.innerWidth < 768 && !panel.classList.contains('hidden')) closePanel();
      });
      document.getElementById('clearFilters')?.addEventListener('click', () => {
        if (window.innerWidth < 768 && !panel.classList.contains('hidden')) closePanel();
        setTimeout(computeActiveFilters, 0);
      });

      // Pressing Escape key triggers clearFilters and refreshes results
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          clearFilters();
          applyFilters();
        }
      });
      // Pressing Enter in searchInput triggers search
      var searchInput = document.getElementById('searchInput');
      if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            applyFilters();
          }
        });
      }
      // Search button functionality
      var searchBtn = document.getElementById('searchButton');
      if (searchBtn) {
        searchBtn.addEventListener('click', function() {
          applyFilters();
        });
      }
      // Only trigger filter on button click
      function setHiddenFilterFields() {
        document.getElementById('hiddenSearch').value = document.getElementById('searchInput').value;
        document.getElementById('hiddenCategory').value = document.getElementById('categorySelect').value;
        document.getElementById('hiddenLocation').value = document.getElementById('locationInput').value;
        document.getElementById('hiddenMinPrice').value = document.getElementById('priceMin').value;
        document.getElementById('hiddenMaxPrice').value = document.getElementById('priceMax').value;
        document.getElementById('hiddenVerified').value = document.getElementById('verifiedOnly').checked ? '1' : '';
        document.getElementById('hiddenRating').value = document.getElementById('skilledOnly').checked ? '4' : '';

        const distSel = document.getElementById('districtSelect');
        const hiddenDist = document.getElementById('hiddenDistrict');
        if (distSel && hiddenDist) hiddenDist.value = distSel.value || '';
      }

      function applyFilters() {
        setHiddenFilterFields();
        document.getElementById('filterForm').submit();
      }

      function clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('categorySelect').value = '';
        const distSel = document.getElementById('districtSelect');
        if (distSel) distSel.selectedIndex = 0;
        document.getElementById('locationInput').value = '';
        document.getElementById('priceMin').value = '';
        document.getElementById('priceMax').value = '';
        document.getElementById('verifiedOnly').checked = false;
        document.getElementById('skilledOnly').checked = false;
        document.getElementById('hiddenSearch').value = '';
        setHiddenFilterFields();
        document.getElementById('filterForm').submit();
      }

      // Apply Filters button
      var applyBtn = document.getElementById('applyFilters');
      if (applyBtn) {
        applyBtn.addEventListener('click', function() {
          applyFilters();
        });
      }

      // Clear Filters button
      document.getElementById('clearFilters').addEventListener('click', function() {
        clearFilters();
      });

      // No auto search input event. Only filter on button click.

      // Add staggered animation for provider cards on page load
      function animateProviderCards() {
        const cards = document.querySelectorAll('.provider-card, .bg-white.shadow-sm');
        cards.forEach((card, index) => {
          card.style.opacity = '0';
          card.style.transform = 'translateY(20px)';
          card.style.transition = 'all 0.6s ease-out';

          setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
          }, index * 100); // Stagger by 100ms
        });
      }

      // Add smooth hover effects only to provider cards (not filter elements)
      const providerCards = document.querySelectorAll('.provider-card');
      providerCards.forEach(element => {
        element.addEventListener('mouseenter', function() {
          if (!isFiltering) {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
          }
        });

        element.addEventListener('mouseleave', function() {
          this.style.transform = 'translateY(0)';
          this.style.boxShadow = '';
        });
      });

      // Add page transition effect
      document.body.style.opacity = '0';
      document.body.style.transition = 'opacity 0.5s ease-in-out';
      requestAnimationFrame(() => {
        document.body.style.opacity = '1';
        // Run card animation after page loads
        // setTimeout(animateProviderCards, 100);

        // Initialize the map
        initializeProvidersMap();
      });

      // Map initialization function
      function initializeProvidersMap() {
        const mapContainer = document.getElementById('servicesMap');
        const loadingIndicator = document.getElementById('mapLoadingIndicator');

        if (!mapContainer || typeof L === 'undefined') {
          console.error('Map container not found or Leaflet not loaded');
          return;
        }

        try {
          // Initialize map centered on Sri Lanka
          const map = L.map('servicesMap').setView([7.8731, 80.7718], 8);

          // Add OpenStreetMap tiles
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 18
          }).addTo(map);

          // Custom marker icons with category-specific colors and name labels
          const createCustomIcon = (categoryIcon, providerName, categoryName) => {
            // Define unique colors for different categories (with common variations)
            const categoryColors = {
              // Common service category names and variations
              'Plumbing': {
                bg: '#e0f7ff',
                border: '#0891b2',
                icon: '#0e7490'
              },
              'plumbing': {
                bg: '#e0f7ff',
                border: '#0891b2',
                icon: '#0e7490'
              },
              'Electrical': {
                bg: '#fff7ed',
                border: '#ea580c',
                icon: '#c2410c'
              },
              'electrical': {
                bg: '#fff7ed',
                border: '#ea580c',
                icon: '#c2410c'
              },
              'Carpentry': {
                bg: '#f4f3ff',
                border: '#7c3aed',
                icon: '#6d28d9'
              },
              'carpentry': {
                bg: '#f4f3ff',
                border: '#7c3aed',
                icon: '#6d28d9'
              },
              'Cleaning': {
                bg: '#ecfdf5',
                border: '#10b981',
                icon: '#059669'
              },
              'cleaning': {
                bg: '#ecfdf5',
                border: '#10b981',
                icon: '#059669'
              },
              'Painting': {
                bg: '#fef2f2',
                border: '#dc2626',
                icon: '#b91c1c'
              },
              'painting': {
                bg: '#fef2f2',
                border: '#dc2626',
                icon: '#b91c1c'
              },
              'Gardening': {
                bg: '#f7fee7',
                border: '#65a30d',
                icon: '#4d7c0f'
              },
              'gardening': {
                bg: '#f7fee7',
                border: '#65a30d',
                icon: '#4d7c0f'
              },
              'IT Services': {
                bg: '#eff6ff',
                border: '#2563eb',
                icon: '#1d4ed8'
              },
              'it services': {
                bg: '#eff6ff',
                border: '#2563eb',
                icon: '#1d4ed8'
              },
              'Technology': {
                bg: '#eff6ff',
                border: '#2563eb',
                icon: '#1d4ed8'
              },
              'technology': {
                bg: '#eff6ff',
                border: '#2563eb',
                icon: '#1d4ed8'
              },
              'Tutoring': {
                bg: '#fefce8',
                border: '#eab308',
                icon: '#ca8a04'
              },
              'tutoring': {
                bg: '#fefce8',
                border: '#eab308',
                icon: '#ca8a04'
              },
              'Education': {
                bg: '#fefce8',
                border: '#eab308',
                icon: '#ca8a04'
              },
              'education': {
                bg: '#fefce8',
                border: '#eab308',
                icon: '#ca8a04'
              },
              'Beauty': {
                bg: '#fdf2f8',
                border: '#ec4899',
                icon: '#be185d'
              },
              'beauty': {
                bg: '#fdf2f8',
                border: '#ec4899',
                icon: '#be185d'
              },
              'Fitness': {
                bg: '#f0fdf4',
                border: '#16a34a',
                icon: '#15803d'
              },
              'fitness': {
                bg: '#f0fdf4',
                border: '#16a34a',
                icon: '#15803d'
              },
              'Photography': {
                bg: '#f8fafc',
                border: '#475569',
                icon: '#334155'
              },
              'photography': {
                bg: '#f8fafc',
                border: '#475569',
                icon: '#334155'
              },
              'Catering': {
                bg: '#fef3c7',
                border: '#d97706',
                icon: '#b45309'
              },
              'catering': {
                bg: '#fef3c7',
                border: '#d97706',
                icon: '#b45309'
              },
              'Food': {
                bg: '#fef3c7',
                border: '#d97706',
                icon: '#b45309'
              },
              'food': {
                bg: '#fef3c7',
                border: '#d97706',
                icon: '#b45309'
              },

              // Additional common categories
              'Repair': {
                bg: '#fdf4ff',
                border: '#a855f7',
                icon: '#9333ea'
              },
              'repair': {
                bg: '#fdf4ff',
                border: '#a855f7',
                icon: '#9333ea'
              },
              'Transport': {
                bg: '#f1f5f9',
                border: '#0f172a',
                icon: '#1e293b'
              },
              'transport': {
                bg: '#f1f5f9',
                border: '#0f172a',
                icon: '#1e293b'
              },
              'Pet': {
                bg: '#fef7ed',
                border: '#fb923c',
                icon: '#ea580c'
              },
              'pet': {
                bg: '#fef7ed',
                border: '#fb923c',
                icon: '#ea580c'
              },
              'Security': {
                bg: '#fafafa',
                border: '#404040',
                icon: '#262626'
              },
              'security': {
                bg: '#fafafa',
                border: '#404040',
                icon: '#262626'
              },
              'Health': {
                bg: '#f0fdfa',
                border: '#14b8a6',
                icon: '#0f766e'
              },
              'health': {
                bg: '#f0fdfa',
                border: '#14b8a6',
                icon: '#0f766e'
              },
              'Legal': {
                bg: '#fffbeb',
                border: '#f59e0b',
                icon: '#d97706'
              },
              'legal': {
                bg: '#fffbeb',
                border: '#f59e0b',
                icon: '#d97706'
              },
              'Event': {
                bg: '#fdf2f8',
                border: '#d946ef',
                icon: '#c026d3'
              },
              'event': {
                bg: '#fdf2f8',
                border: '#d946ef',
                icon: '#c026d3'
              },
              'Auto': {
                bg: '#f3f4f6',
                border: '#6b7280',
                icon: '#4b5563'
              },
              'auto': {
                bg: '#f3f4f6',
                border: '#6b7280',
                icon: '#4b5563'
              }
            };

            // Debug: Log the category name to see what we're getting
            console.log('Category for marker:', categoryName);

            // Simplified color assignment - try direct match first, then keyword matching
            const normalizedCategory = (categoryName || 'default').toLowerCase().trim();
            let colors = categoryColors[categoryName] || categoryColors[normalizedCategory];

            // If no direct match, try keyword matching
            if (!colors) {
              if (normalizedCategory.includes('plumb')) colors = categoryColors['plumbing'];
              else if (normalizedCategory.includes('electric')) colors = categoryColors['electrical'];
              else if (normalizedCategory.includes('carpen') || normalizedCategory.includes('wood')) colors = categoryColors['carpentry'];
              else if (normalizedCategory.includes('clean')) colors = categoryColors['cleaning'];
              else if (normalizedCategory.includes('paint')) colors = categoryColors['painting'];
              else if (normalizedCategory.includes('garden') || normalizedCategory.includes('landscape')) colors = categoryColors['gardening'];
              else if (normalizedCategory.includes('tech') || normalizedCategory.includes('computer') || normalizedCategory.includes('it')) colors = categoryColors['technology'];
              else if (normalizedCategory.includes('tutor') || normalizedCategory.includes('teach') || normalizedCategory.includes('education')) colors = categoryColors['education'];
              else if (normalizedCategory.includes('beauty') || normalizedCategory.includes('salon') || normalizedCategory.includes('makeup')) colors = categoryColors['beauty'];
              else if (normalizedCategory.includes('fitness') || normalizedCategory.includes('gym') || normalizedCategory.includes('exercise')) colors = categoryColors['fitness'];
              else if (normalizedCategory.includes('photo')) colors = categoryColors['photography'];
              else if (normalizedCategory.includes('food') || normalizedCategory.includes('cater') || normalizedCategory.includes('cook')) colors = categoryColors['catering'];
              else if (normalizedCategory.includes('repair') || normalizedCategory.includes('fix')) colors = categoryColors['repair'];
              else if (normalizedCategory.includes('transport') || normalizedCategory.includes('taxi') || normalizedCategory.includes('drive')) colors = categoryColors['transport'];
              else if (normalizedCategory.includes('pet') || normalizedCategory.includes('animal')) colors = categoryColors['pet'];
              else if (normalizedCategory.includes('security') || normalizedCategory.includes('guard')) colors = categoryColors['security'];
              else if (normalizedCategory.includes('health') || normalizedCategory.includes('medical') || normalizedCategory.includes('doctor')) colors = categoryColors['health'];
              else if (normalizedCategory.includes('legal') || normalizedCategory.includes('law')) colors = categoryColors['legal'];
              else if (normalizedCategory.includes('event') || normalizedCategory.includes('party') || normalizedCategory.includes('wedding')) colors = categoryColors['event'];
              else if (normalizedCategory.includes('auto') || normalizedCategory.includes('car') || normalizedCategory.includes('vehicle')) colors = categoryColors['auto'];
            }

            // If still no match, assign based on hash for consistency
            if (!colors) {
              const colorOptions = [{
                  bg: '#e0f7ff',
                  border: '#0891b2',
                  icon: '#0e7490'
                }, // Cyan
                {
                  bg: '#fff7ed',
                  border: '#ea580c',
                  icon: '#c2410c'
                }, // Orange
                {
                  bg: '#f4f3ff',
                  border: '#7c3aed',
                  icon: '#6d28d9'
                }, // Purple
                {
                  bg: '#ecfdf5',
                  border: '#10b981',
                  icon: '#059669'
                }, // Green
                {
                  bg: '#fef2f2',
                  border: '#dc2626',
                  icon: '#b91c1c'
                }, // Red
                {
                  bg: '#f7fee7',
                  border: '#65a30d',
                  icon: '#4d7c0f'
                }, // Lime
                {
                  bg: '#eff6ff',
                  border: '#2563eb',
                  icon: '#1d4ed8'
                }, // Blue
                {
                  bg: '#fefce8',
                  border: '#eab308',
                  icon: '#ca8a04'
                }, // Yellow
                {
                  bg: '#fdf2f8',
                  border: '#ec4899',
                  icon: '#be185d'
                }, // Pink
                {
                  bg: '#f0fdf4',
                  border: '#16a34a',
                  icon: '#15803d'
                } // Forest
              ];

              let hash = 0;
              for (let i = 0; i < normalizedCategory.length; i++) {
                hash = normalizedCategory.charCodeAt(i) + ((hash << 5) - hash);
              }
              const colorIndex = Math.abs(hash) % colorOptions.length;
              colors = colorOptions[colorIndex];

              console.log(`Hash-assigned color index ${colorIndex} for category: ${categoryName}`);
            } else {
              console.log(`Found color match for category: ${categoryName}`);
            }

            return L.divIcon({
              className: 'custom-marker-with-label',
              html: `
            <div style="
              position: relative;
              display: flex;
              flex-direction: column;
              align-items: center;
              pointer-events: none;
            ">
              <!-- Marker Icon -->
              <div style="
                background-color: ${colors.bg};
                width: 36px;
                height: 36px;
                border-radius: 50%;
                border: 3px solid ${colors.border};
                box-shadow: 0 3px 10px rgba(0,0,0,0.3);
                display: flex;
                align-items: center;
                justify-content: center;
                color: ${colors.icon};
                font-size: 14px;
                font-weight: bold;
                position: relative;
                z-index: 2;
                pointer-events: auto;
              ">
                <i class="${categoryIcon}"></i>
              </div>
              
              <!-- Provider Name Label -->
              <div style="
                background-color: rgba(255, 255, 255, 0.95);
                color: #1f2937;
                font-size: 11px;
                font-weight: 600;
                padding: 2px 6px;
                border-radius: 4px;
                border: 1px solid ${colors.border};
                box-shadow: 0 1px 4px rgba(0,0,0,0.2);
                white-space: nowrap;
                max-width: 120px;
                overflow: hidden;
                text-overflow: ellipsis;
                margin-top: 2px;
                position: relative;
                z-index: 1;
                text-align: center;
                font-family: system-ui, -apple-system, sans-serif;
              ">
                ${providerName.length > 15 ? providerName.substring(0, 15) + '...' : providerName}
              </div>
            </div>
          `,
              iconSize: [120, 50],
              iconAnchor: [60, 36],
              popupAnchor: [0, -36]
            });
          };

          // Add markers for each provider
          const markers = [];
          providerLocations.forEach(provider => {
            if (provider.lat && provider.lng) {
              const marker = L.marker([provider.lat, provider.lng], {
                icon: createCustomIcon(provider.categoryIcon, provider.name, provider.category)
              });

              // Create popup content
              const popupContent = `
            <div class="provider-popup" style="min-width: 250px; font-family: system-ui;">
              <div style="margin-bottom: 8px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: bold; color: #1f2937;">
                  ${provider.name}
                </h3>
                ${provider.isVerified ? '<span style="background: #10b981; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 4px;">✓ VERIFIED</span>' : ''}
              </div>
              
              <div style="margin-bottom: 8px;">
                <span style="background: #3b82f6; color: white; padding: 4px 8px; border-radius: 16px; font-size: 12px;">
                  ${provider.category}
                </span>
              </div>
              
              <div style="margin-bottom: 8px; color: #6b7280; font-size: 14px;">
                <i class="fa-solid fa-location-dot" style="margin-right: 4px; color: #3b82f6;"></i>
                ${provider.location}
              </div>
              
              <div style="margin-bottom: 8px; color: #6b7280; font-size: 14px;">
                <i class="fa-solid fa-star" style="margin-right: 4px; color: #fbbf24;"></i>
                ${provider.rating}/5.0
                <span style="margin-left: 12px;">
                  <i class="fa-solid fa-money-bill" style="margin-right: 4px; color: #10b981;"></i>
                  Rs. ${provider.hourlyRate}/hr
                </span>
              </div>
              
              <div style="margin-top: 12px;">
                <a href="${provider.profileUrl}" 
                   style="background: #3b82f6; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; display: inline-block; font-weight: 500;">
                  <i class="fa-solid fa-eye" style="margin-right: 4px;"></i>
                  View Profile
                </a>
              </div>
            </div>
          `;

              marker.bindPopup(popupContent);
              marker.addTo(map);
              markers.push(marker);
            }
          });

          // Fit map to show all markers if there are any
          if (markers.length > 0) {
            const group = new L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
          }

          // Hide loading indicator
          setTimeout(() => {
            loadingIndicator.style.display = 'none';
          }, 1000);

          console.log(`Map initialized with ${markers.length} provider markers`);

        } catch (error) {
          console.error('Error initializing map:', error);
          loadingIndicator.innerHTML = `
        <div class="text-center text-red-500">
          <i class="fa-solid fa-exclamation-triangle text-2xl mb-2"></i>
          <p class="text-sm">Error loading map</p>
        </div>
      `;
        }
      }

      // Mobile map modal functionality
      let mobileMap = null;
      let mobileMapInitialized = false;

      // Function to show mobile map modal
      window.showMobileMap = function() {
        const mobileMapModal = document.getElementById('mobileMapModal');

        if (mobileMapModal) {
          mobileMapModal.classList.remove('hidden');
          document.body.style.overflow = 'hidden'; // Prevent background scrolling

          // Initialize mobile map if not already done
          if (!mobileMapInitialized) {
            setTimeout(() => {
              initializeMobileProvidersMap();
            }, 200); // Small delay to ensure modal is visible
          } else if (mobileMap) {
            // Resize existing map
            setTimeout(() => {
              mobileMap.invalidateSize();
            }, 200);
          }
        }
      };

      // Function to hide mobile map modal
      window.hideMobileMap = function() {
        const mobileMapModal = document.getElementById('mobileMapModal');
        if (mobileMapModal) {
          mobileMapModal.classList.add('closing');
          document.body.style.overflow = ''; // Restore scrolling

          setTimeout(() => {
            mobileMapModal.classList.add('hidden');
            mobileMapModal.classList.remove('closing');
          }, 300); // Match animation duration
        }
      };

      // Initialize mobile map with same functionality as desktop
      function initializeMobileProvidersMap() {
        const mobileMapContainer = document.getElementById('mobileServicesMap');
        const mobileLoadingIndicator = document.getElementById('mobileMapLoadingIndicator');

        if (!mobileMapContainer || typeof L === 'undefined') {
          console.error('Mobile map container not found or Leaflet not loaded');
          return;
        }

        try {
          // Initialize mobile map centered on Sri Lanka
          mobileMap = L.map('mobileServicesMap').setView([7.8731, 80.7718], 8);

          // Add OpenStreetMap tiles
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 18
          }).addTo(mobileMap);

          // Reuse the same marker creation function
          const createCustomIcon = (categoryIcon, providerName, categoryName) => {
            // Same color definitions as desktop map
            const categoryColors = {
              'Plumbing': {
                bg: '#e0f7ff',
                border: '#0891b2',
                icon: '#0e7490'
              },
              'plumbing': {
                bg: '#e0f7ff',
                border: '#0891b2',
                icon: '#0e7490'
              },
              'Electrical': {
                bg: '#fff7ed',
                border: '#ea580c',
                icon: '#c2410c'
              },
              'electrical': {
                bg: '#fff7ed',
                border: '#ea580c',
                icon: '#c2410c'
              },
              'Carpentry': {
                bg: '#f4f3ff',
                border: '#7c3aed',
                icon: '#6d28d9'
              },
              'carpentry': {
                bg: '#f4f3ff',
                border: '#7c3aed',
                icon: '#6d28d9'
              },
              'Cleaning': {
                bg: '#ecfdf5',
                border: '#10b981',
                icon: '#059669'
              },
              'cleaning': {
                bg: '#ecfdf5',
                border: '#10b981',
                icon: '#059669'
              },
              'Painting': {
                bg: '#fef2f2',
                border: '#dc2626',
                icon: '#b91c1c'
              },
              'painting': {
                bg: '#fef2f2',
                border: '#dc2626',
                icon: '#b91c1c'
              },
              'Gardening': {
                bg: '#f7fee7',
                border: '#65a30d',
                icon: '#4d7c0f'
              },
              'gardening': {
                bg: '#f7fee7',
                border: '#65a30d',
                icon: '#4d7c0f'
              },
              'IT Services': {
                bg: '#eff6ff',
                border: '#2563eb',
                icon: '#1d4ed8'
              },
              'it services': {
                bg: '#eff6ff',
                border: '#2563eb',
                icon: '#1d4ed8'
              },
              'Technology': {
                bg: '#eff6ff',
                border: '#2563eb',
                icon: '#1d4ed8'
              },
              'technology': {
                bg: '#eff6ff',
                border: '#2563eb',
                icon: '#1d4ed8'
              },
              'Tutoring': {
                bg: '#fefce8',
                border: '#eab308',
                icon: '#ca8a04'
              },
              'tutoring': {
                bg: '#fefce8',
                border: '#eab308',
                icon: '#ca8a04'
              },
              'Education': {
                bg: '#fefce8',
                border: '#eab308',
                icon: '#ca8a04'
              },
              'education': {
                bg: '#fefce8',
                border: '#eab308',
                icon: '#ca8a04'
              },
              'Beauty': {
                bg: '#fdf2f8',
                border: '#ec4899',
                icon: '#be185d'
              },
              'beauty': {
                bg: '#fdf2f8',
                border: '#ec4899',
                icon: '#be185d'
              },
              'Fitness': {
                bg: '#f0fdf4',
                border: '#16a34a',
                icon: '#15803d'
              },
              'fitness': {
                bg: '#f0fdf4',
                border: '#16a34a',
                icon: '#15803d'
              },
              'Photography': {
                bg: '#f8fafc',
                border: '#475569',
                icon: '#334155'
              },
              'photography': {
                bg: '#f8fafc',
                border: '#475569',
                icon: '#334155'
              },
              'Catering': {
                bg: '#fef3c7',
                border: '#d97706',
                icon: '#b45309'
              },
              'catering': {
                bg: '#fef3c7',
                border: '#d97706',
                icon: '#b45309'
              },
              'Food': {
                bg: '#fef3c7',
                border: '#d97706',
                icon: '#b45309'
              },
              'food': {
                bg: '#fef3c7',
                border: '#d97706',
                icon: '#b45309'
              },
              'Repair': {
                bg: '#fdf4ff',
                border: '#a855f7',
                icon: '#9333ea'
              },
              'repair': {
                bg: '#fdf4ff',
                border: '#a855f7',
                icon: '#9333ea'
              },
              'Transport': {
                bg: '#f1f5f9',
                border: '#0f172a',
                icon: '#1e293b'
              },
              'transport': {
                bg: '#f1f5f9',
                border: '#0f172a',
                icon: '#1e293b'
              },
              'Pet': {
                bg: '#fef7ed',
                border: '#fb923c',
                icon: '#ea580c'
              },
              'pet': {
                bg: '#fef7ed',
                border: '#fb923c',
                icon: '#ea580c'
              },
              'Security': {
                bg: '#fafafa',
                border: '#404040',
                icon: '#262626'
              },
              'security': {
                bg: '#fafafa',
                border: '#404040',
                icon: '#262626'
              },
              'Health': {
                bg: '#f0fdfa',
                border: '#14b8a6',
                icon: '#0f766e'
              },
              'health': {
                bg: '#f0fdfa',
                border: '#14b8a6',
                icon: '#0f766e'
              },
              'Legal': {
                bg: '#fffbeb',
                border: '#f59e0b',
                icon: '#d97706'
              },
              'legal': {
                bg: '#fffbeb',
                border: '#f59e0b',
                icon: '#d97706'
              },
              'Event': {
                bg: '#fdf2f8',
                border: '#d946ef',
                icon: '#c026d3'
              },
              'event': {
                bg: '#fdf2f8',
                border: '#d946ef',
                icon: '#c026d3'
              },
              'Auto': {
                bg: '#f3f4f6',
                border: '#6b7280',
                icon: '#4b5563'
              },
              'auto': {
                bg: '#f3f4f6',
                border: '#6b7280',
                icon: '#4b5563'
              }
            };

            const normalizedCategory = (categoryName || 'default').toLowerCase().trim();
            let colors = categoryColors[categoryName] || categoryColors[normalizedCategory];

            // Keyword matching for unmatched categories
            if (!colors) {
              if (normalizedCategory.includes('plumb')) colors = categoryColors['plumbing'];
              else if (normalizedCategory.includes('electric')) colors = categoryColors['electrical'];
              else if (normalizedCategory.includes('carpen') || normalizedCategory.includes('wood')) colors = categoryColors['carpentry'];
              else if (normalizedCategory.includes('clean')) colors = categoryColors['cleaning'];
              else if (normalizedCategory.includes('paint')) colors = categoryColors['painting'];
              else if (normalizedCategory.includes('garden') || normalizedCategory.includes('landscape')) colors = categoryColors['gardening'];
              else if (normalizedCategory.includes('tech') || normalizedCategory.includes('computer') || normalizedCategory.includes('it')) colors = categoryColors['technology'];
              else if (normalizedCategory.includes('tutor') || normalizedCategory.includes('teach') || normalizedCategory.includes('education')) colors = categoryColors['education'];
              else if (normalizedCategory.includes('beauty') || normalizedCategory.includes('salon') || normalizedCategory.includes('makeup')) colors = categoryColors['beauty'];
              else if (normalizedCategory.includes('fitness') || normalizedCategory.includes('gym') || normalizedCategory.includes('exercise')) colors = categoryColors['fitness'];
              else if (normalizedCategory.includes('photo')) colors = categoryColors['photography'];
              else if (normalizedCategory.includes('food') || normalizedCategory.includes('cater') || normalizedCategory.includes('cook')) colors = categoryColors['catering'];
              else if (normalizedCategory.includes('repair') || normalizedCategory.includes('fix')) colors = categoryColors['repair'];
              else if (normalizedCategory.includes('transport') || normalizedCategory.includes('taxi') || normalizedCategory.includes('drive')) colors = categoryColors['transport'];
              else if (normalizedCategory.includes('pet') || normalizedCategory.includes('animal')) colors = categoryColors['pet'];
              else if (normalizedCategory.includes('security') || normalizedCategory.includes('guard')) colors = categoryColors['security'];
              else if (normalizedCategory.includes('health') || normalizedCategory.includes('medical') || normalizedCategory.includes('doctor')) colors = categoryColors['health'];
              else if (normalizedCategory.includes('legal') || normalizedCategory.includes('law')) colors = categoryColors['legal'];
              else if (normalizedCategory.includes('event') || normalizedCategory.includes('party') || normalizedCategory.includes('wedding')) colors = categoryColors['event'];
              else if (normalizedCategory.includes('auto') || normalizedCategory.includes('car') || normalizedCategory.includes('vehicle')) colors = categoryColors['auto'];
            }

            // Hash-based fallback for consistency
            if (!colors) {
              const colorOptions = [{
                  bg: '#e0f7ff',
                  border: '#0891b2',
                  icon: '#0e7490'
                },
                {
                  bg: '#fff7ed',
                  border: '#ea580c',
                  icon: '#c2410c'
                },
                {
                  bg: '#f4f3ff',
                  border: '#7c3aed',
                  icon: '#6d28d9'
                },
                {
                  bg: '#ecfdf5',
                  border: '#10b981',
                  icon: '#059669'
                },
                {
                  bg: '#fef2f2',
                  border: '#dc2626',
                  icon: '#b91c1c'
                }
              ];

              let hash = 0;
              for (let i = 0; i < normalizedCategory.length; i++) {
                hash = normalizedCategory.charCodeAt(i) + ((hash << 5) - hash);
              }
              colors = colorOptions[Math.abs(hash) % colorOptions.length];
            }

            return L.divIcon({
              className: 'custom-marker-with-label',
              html: `
            <div style="position: relative; display: flex; flex-direction: column; align-items: center;">
              <div style="
                background-color: ${colors.bg};
                width: 32px; height: 32px;
                border-radius: 50%;
                border: 2px solid ${colors.border};
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                display: flex; align-items: center; justify-content: center;
                color: ${colors.icon}; font-size: 12px; font-weight: bold;
              ">
                <i class="${categoryIcon}"></i>
              </div>
              <div style="
                background-color: rgba(255, 255, 255, 0.95);
                color: #1f2937; font-size: 10px; font-weight: 600;
                padding: 1px 4px; border-radius: 3px;
                border: 1px solid ${colors.border};
                box-shadow: 0 1px 3px rgba(0,0,0,0.2);
                white-space: nowrap; max-width: 100px;
                overflow: hidden; text-overflow: ellipsis;
                margin-top: 1px; text-align: center;
              ">
                ${providerName.length > 12 ? providerName.substring(0, 12) + '...' : providerName}
              </div>
            </div>
          `,
              iconSize: [100, 40],
              iconAnchor: [50, 32],
              popupAnchor: [0, -32]
            });
          };

          // Add markers for each provider
          const mobileMarkers = [];
          providerLocations.forEach(provider => {
            if (provider.lat && provider.lng) {
              const marker = L.marker([provider.lat, provider.lng], {
                icon: createCustomIcon(provider.categoryIcon, provider.name, provider.category)
              });

              // Mobile-optimized popup content
              const popupContent = `
            <div class="provider-popup" style="min-width: 160px; max-width: 200px; font-family: system-ui;">
              <div style="margin-bottom: 4px;">
                <h3 style="margin: 0; font-size: 12px; font-weight: bold; color: #1f2937; line-height: 1.2;">
                  ${provider.name}
                </h3>
                ${provider.isVerified ? '<span style="background: #10b981; color: white; padding: 1px 3px; border-radius: 2px; font-size: 8px; margin-left: 2px;">✓</span>' : ''}
              </div>
              
              <div style="margin-bottom: 4px;">
                <span style="background: #3b82f6; color: white; padding: 2px 4px; border-radius: 8px; font-size: 9px;">
                  ${provider.category}
                </span>
              </div>
              
              <div style="margin-bottom: 4px; color: #6b7280; font-size: 10px; line-height: 1.2;">
                <i class="fa-solid fa-location-dot" style="margin-right: 2px; color: #3b82f6; font-size: 9px;"></i>
                ${provider.location.length > 25 ? provider.location.substring(0, 25) + '...' : provider.location}
              </div>
              
              <div style="margin-bottom: 6px; color: #6b7280; font-size: 10px; display: flex; justify-content: space-between;">
                <span>
                  <i class="fa-solid fa-star" style="margin-right: 2px; color: #fbbf24; font-size: 9px;"></i>
                  ${provider.rating}/5
                </span>
                <span>
                  <i class="fa-solid fa-money-bill" style="margin-right: 2px; color: #10b981; font-size: 9px;"></i>
                  Rs. ${provider.hourlyRate}/hr
                </span>
              </div>
              
              <div style="margin-top: 6px;">
                <a href="${provider.profileUrl}" 
                   style="background: #3b82f6; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 10px; display: inline-block; font-weight: 500;">
                  <i class="fa-solid fa-eye" style="margin-right: 2px; font-size: 9px;"></i>
                  View
                </a>
              </div>
            </div>
          `;

              marker.bindPopup(popupContent);
              marker.addTo(mobileMap);
              mobileMarkers.push(marker);
            }
          });

          // Fit map to show all markers if there are any
          if (mobileMarkers.length > 0) {
            const group = new L.featureGroup(mobileMarkers);
            mobileMap.fitBounds(group.getBounds().pad(0.1));
          }

          // Hide loading indicator
          setTimeout(() => {
            if (mobileLoadingIndicator) {
              mobileLoadingIndicator.style.display = 'none';
            }
          }, 1000);

          mobileMapInitialized = true;
          console.log(`Mobile map initialized with ${mobileMarkers.length} provider markers`);

        } catch (error) {
          console.error('Error initializing mobile map:', error);
          if (mobileLoadingIndicator) {
            mobileLoadingIndicator.innerHTML = `
          <div class="text-center text-red-500">
            <i class="fa-solid fa-exclamation-triangle text-xl mb-2"></i>
            <p class="text-xs">Error loading map</p>
          </div>
        `;
          }
        }
      }

      // Initialize modal event listeners immediately
      const closeMobileMapBtn = document.getElementById('closeMobileMapBtn');
      const mobileMapModal = document.getElementById('mobileMapModal');

      if (closeMobileMapBtn) {
        closeMobileMapBtn.addEventListener('click', hideMobileMap);
      }

      // Close modal when clicking backdrop
      if (mobileMapModal) {
        mobileMapModal.addEventListener('click', function(e) {
          if (e.target === mobileMapModal) {
            hideMobileMap();
          }
        });

        // Prevent modal content clicks from closing modal
        const modalContent = mobileMapModal.querySelector('.mobile-modal-compact');
        if (modalContent) {
          modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
          });
        }
      }
    });
  </script>

  <!-- Rating Modal -->
  <div id="ratingModal" class="fixed inset-0 z-50 hidden">
    <!-- Modal Backdrop -->
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm transition-opacity duration-300"></div>

    <!-- Modal Content -->
    <div class="fixed inset-0 flex items-center justify-center p-4">
      <div
        class="bg-white dark:bg-neutral-800 rounded-xl shadow-2xl w-full max-w-md transform transition-all duration-300 scale-95 opacity-0"
        id="ratingModalContent">
        <!-- Modal Header -->
        <div class="p-6 border-b border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center justify-between">
            <h3 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100" id="ratingModalTitle">
              Rate Provider
            </h3>
            <button onclick="hideRatingModal()"
              class="text-neutral-400 hover:text-neutral-500 dark:hover:text-neutral-300">
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
                <i class="fa-star cursor-pointer text-neutral-300 hover:text-yellow-400 transition-all transform hover:scale-125 star-rating-icon"
                  data-rating="1"></i>
                <i class="fa-star cursor-pointer text-neutral-300 hover:text-yellow-400 transition-all transform hover:scale-125 star-rating-icon"
                  data-rating="2"></i>
                <i class="fa-star cursor-pointer text-neutral-300 hover:text-yellow-400 transition-all transform hover:scale-125 star-rating-icon"
                  data-rating="3"></i>
                <i class="fa-star cursor-pointer text-neutral-300 hover:text-yellow-400 transition-all transform hover:scale-125 star-rating-icon"
                  data-rating="4"></i>
                <i class="fa-star cursor-pointer text-neutral-300 hover:text-yellow-400 transition-all transform hover:scale-125 star-rating-icon"
                  data-rating="5"></i>
              </div>
              <p class="mt-3 text-sm font-medium text-neutral-600 dark:text-neutral-400" id="ratingText">Select your
                rating</p>
            </div>

            <style>
              @keyframes star-bounce {

                0%,
                100% {
                  transform: scale(1);
                }

                50% {
                  transform: scale(1.2);
                }
              }

              .star-rating-icon {
                display: inline-block;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
              }

              .star-rating-icon.active {
                animation: star-bounce 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                color: #facc15;
                text-shadow: 0 0 15px rgba(250, 204, 21, 0.5);
              }

              #starRating:hover .star-rating-icon {
                transform: scale(0.9);
                opacity: 0.75;
              }

              #starRating .star-rating-icon:hover,
              #starRating .star-rating-icon:hover~.star-rating-icon {
                transform: scale(1.2);
                opacity: 1;
              }
            </style>

            <!-- Review Text -->
            <div>
              <label for="review" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">Your
                Review</label>
              <textarea id="review" name="review" rows="3"
                class="w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-neutral-700 dark:text-neutral-100"
                placeholder="Share your experience..."></textarea>
            </div>
          </form>
        </div>

        <!-- Modal Footer -->
        <div class="p-6 border-t border-neutral-200 dark:border-neutral-700 flex justify-end space-x-3">
          <button onclick="hideRatingModal()"
            class="px-4 py-2 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 rounded-lg transition-colors">
            Cancel
          </button>
          <button onclick="submitRating()"
            class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors">
            Submit Rating
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Rating Modal Functionality
    let currentRating = 0;
    const ratingTexts = {
      1: "Poor",
      2: "Fair",
      3: "Good",
      4: "Very Good",
      5: "Excellent"
    };

    function showRatingModal(providerId, providerName) {
      const modal = document.getElementById('ratingModal');
      const modalContent = document.getElementById('ratingModalContent');
      document.getElementById('providerId').value = providerId;
      document.getElementById('ratingModalTitle').textContent = `Rate ${providerName}`;

      // Reset form
      currentRating = 0;
      document.getElementById('review').value = '';
      updateStars(0);
      document.getElementById('ratingText').textContent = 'Select your rating';

      // Show modal with animation
      modal.classList.remove('hidden');
      setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
      }, 10);

      // Prevent body scroll
      document.body.style.overflow = 'hidden';
    }

    function hideRatingModal() {
      const modal = document.getElementById('ratingModal');
      const modalContent = document.getElementById('ratingModalContent');

      modalContent.classList.add('scale-95', 'opacity-0');
      modalContent.classList.remove('scale-100', 'opacity-100');

      setTimeout(() => {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
      }, 300);
    }

    function updateStars(rating) {
      const stars = document.querySelectorAll('#starRating i');
      stars.forEach((star, index) => {
        star.classList.remove('fas', 'far', 'active');
        star.classList.add(index < rating ? 'fas' : 'far');

        if (index < rating) {
          setTimeout(() => {
            star.classList.add('active');
          }, index * 50); // Stagger the animation
        }

        star.classList.toggle('text-yellow-400', index < rating);
        star.classList.toggle('text-neutral-300', index >= rating);
      });

      // Update rating text with fade effect
      const ratingText = document.getElementById('ratingText');
      ratingText.style.opacity = '0';
      setTimeout(() => {
        ratingText.textContent = rating ? ratingTexts[rating] : 'Select your rating';
        ratingText.style.opacity = '1';
      }, 200);
    }

    // Initialize star rating functionality
    document.getElementById('starRating').addEventListener('click', (e) => {
      if (e.target.matches('i')) {
        currentRating = parseInt(e.target.dataset.rating);
        updateStars(currentRating);
      }
    });

    document.getElementById('starRating').addEventListener('mouseover', (e) => {
      if (e.target.matches('i')) {
        updateStars(parseInt(e.target.dataset.rating));
      }
    });

    document.getElementById('starRating').addEventListener('mouseleave', () => {
      updateStars(currentRating);
    });

    function submitRating() {
      if (!currentRating) {
        alert('Please select a rating');
        return;
      }

      const providerId = document.getElementById('providerId').value;
      const review = document.getElementById('review').value;

      // Submit rating to server
      fetch('api/ratings.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            providerId: providerId,
            rating: currentRating,
            review: review
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Update the rating display in the provider card
            hideRatingModal();
            // Optional: Show success message and reload the page to update ratings
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

  <!-- Custom scripts for services page -->
  <script src="<?php echo BASE_URL; ?>/assets/js/toast.js"></script>
  <script src="<?php echo BASE_URL; ?>/assets/js/favorites.js"></script>
  <script src="<?php echo BASE_URL; ?>/assets/js/services.js"></script>

  <?php include 'includes/footer.php'; ?>