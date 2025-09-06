<?php
// Load required files first
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

// Initialize database and auth
$db = getDB();
$auth = new Auth($db);
$currentUser = $auth->getCurrentUser();

// Handle new wanted ad submission first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_wanted'])) {
    if ($auth->isLoggedIn() && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $location = trim($_POST['location'] ?? '');
        $budgetMin = !empty($_POST['budget_min']) ? (float)$_POST['budget_min'] : null;
        $budgetMax = !empty($_POST['budget_max']) ? (float)$_POST['budget_max'] : null;
        $urgency = $_POST['urgency'] ?? 'medium';
        $contactMethod = $_POST['contact_method'] ?? 'both';
        
        if ($title && $description && $categoryId && $location) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO wanted_ads (user_id, category_id, title, description, location, budget_min, budget_max, urgency, contact_method) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$currentUser['id'], $categoryId, $title, $description, $location, $budgetMin, $budgetMax, $urgency, $contactMethod]);
                
                setFlashMessage('Your wanted ad has been posted successfully!', 'success');
                redirect(BASE_URL . '/wanted.php');
                exit;
            } catch (PDOException $e) {
                setFlashMessage('An error occurred while posting your ad.', 'error');
            }
        } else {
            setFlashMessage('Please fill in all required fields.', 'error');
        }
    } else {
        setFlashMessage('You must be logged in to post a wanted ad.', 'error');
    }
}

$pageTitle = 'Wanted Ads • ServiceLink';
$pageDescription = 'Post requests for services or browse what customers are looking for.';

// Include header after all possible redirects
include 'includes/header.php';

// Get filter parameters
$category = $_GET['category'] ?? '';
$location = $_GET['location'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = ["w.status = 'active'"];
$params = [];

if ($category) {
    $conditions[] = "c.slug = ?";
    $params[] = $category;
}

if ($location) {
    $conditions[] = "w.location LIKE ?";
    $params[] = "%{$location}%";
}

if ($search) {
    $conditions[] = "(w.title LIKE ? OR w.description LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

$whereClause = implode(" AND ", $conditions);

// Get total count
try {
    $countQuery = "
        SELECT COUNT(*) as count
        FROM wanted_ads w 
        JOIN categories c ON w.category_id = c.id 
        WHERE {$whereClause}
    ";
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalAds = $stmt->fetch()['count'];
    $totalPages = ceil($totalAds / $limit);
} catch (PDOException $e) {
    $totalAds = 0;
    $totalPages = 1;
}

// Get wanted ads
try {
    $query = "
        SELECT w.*, u.first_name, u.last_name, c.name as category_name, c.icon as category_icon, c.slug as category_slug
        FROM wanted_ads w 
        JOIN users u ON w.user_id = u.id 
        JOIN categories c ON w.category_id = c.id 
        WHERE {$whereClause}
        ORDER BY w.urgency DESC, w.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($query);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $wantedAds = $stmt->fetchAll();
} catch (PDOException $e) {
    $wantedAds = [];
}

// Get categories for filter
try {
    $stmt = $db->prepare("SELECT * FROM categories WHERE active = 1 ORDER BY name ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Handle new wanted ad errors (form will be re-displayed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_wanted'])) {
    if (!$auth->isLoggedIn() || !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('You must be logged in to post a wanted ad.', 'error');
    }
}
?>

<!-- Main Content -->
<main class="py-8">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- Page Header -->
    <div class="text-center mb-12">
      <h1 class="text-3xl sm:text-4xl font-bold text-neutral-900 mb-4">Wanted Requests</h1>
      <p class="text-lg text-neutral-600 max-w-2xl mx-auto mb-6">
        Browse service requests from clients or post your own to get professional quotes
      </p>
      
      <!-- Post Request Button for Mobile/Tablet in Header -->
      <?php if ($currentUser): ?>
      <button onclick="togglePostForm()" id="postRequestBtnHeader" class="lg:hidden bg-secondary-600 text-white px-6 py-3 rounded-lg hover:bg-secondary-700 transition-colors font-medium flex items-center space-x-2 mx-auto shadow-lg hover:shadow-xl transform hover:scale-105">
        <i class="fa-solid fa-plus"></i>
        <span>Post a Request</span>
      </button>
      <?php else: ?>
      <a href="<?php echo BASE_URL; ?>/login.php" id="postRequestBtnHeader" class="lg:hidden bg-secondary-600 text-white px-6 py-3 rounded-lg hover:bg-secondary-700 transition-colors font-medium flex items-center space-x-2 mx-auto shadow-lg hover:shadow-xl transform hover:scale-105">
        <i class="fa-solid fa-sign-in-alt"></i>
        <span>Login to Post</span>
      </a>
      <?php endif; ?>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6 mb-8">
      <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        
        <!-- Category Filter -->
        <div>
          <label for="wantedCategory" class="block text-sm font-medium text-neutral-700 mb-2">Category</label>
          <select id="wantedCategory" name="category" 
                  class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?php echo e($cat['slug']); ?>" <?php echo $category === $cat['slug'] ? 'selected' : ''; ?>>
              <?php echo e($cat['name']); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Location -->
        <div>
          <label for="wantedLocation" class="block text-sm font-medium text-neutral-700 mb-2">Location</label>
          <input id="wantedLocation" name="location" type="text" placeholder="City or area" value="<?php echo e($location); ?>"
                 class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
        </div>

        <!-- Search Query -->
        <div class="md:col-span-2">
          <label for="wantedQuery" class="block text-sm font-medium text-neutral-700 mb-2">What are you looking for?</label>
          <input id="wantedQuery" name="search" type="text" placeholder="e.g., 'paint my living room', 'math tutor for grade 8'" value="<?php echo e($search); ?>"
                 class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
        </div>

        <!-- Action Buttons Row (Desktop) -->
        <div class="hidden md:flex md:col-span-4 gap-3 justify-center pt-4 border-t border-neutral-200">
          <button type="submit" id="wantedSearchBtn" 
                  class="bg-primary-600 text-white px-8 py-3 rounded-lg hover:bg-primary-700 transition-colors font-medium flex items-center space-x-2 shadow-md hover:shadow-lg">
            <i class="fa-solid fa-search"></i>
            <span>Search Requests</span>
          </button>
          <a href="<?php echo BASE_URL; ?>/wanted.php" id="wantedClearBtn" 
             class="bg-neutral-100 text-neutral-700 px-8 py-3 rounded-lg hover:bg-neutral-200 transition-colors font-medium flex items-center space-x-2 shadow-md hover:shadow-lg">
            <i class="fa-solid fa-rotate-left"></i>
            <span>Reset Filters</span>
          </a>
        </div>
      </form>

      <!-- Action Buttons (Mobile) -->
      <div class="flex md:hidden gap-3 justify-center">
        <button type="submit" form="wantedSearchForm" id="wantedSearchBtnMobile" 
                class="flex-1 bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors font-medium flex items-center justify-center space-x-2">
          <i class="fa-solid fa-search"></i>
          <span>Search</span>
        </button>
        <a href="<?php echo BASE_URL; ?>/wanted.php" id="wantedClearBtnMobile" 
           class="flex-1 bg-neutral-100 text-neutral-700 px-4 py-2 rounded-lg hover:bg-neutral-200 transition-colors font-medium flex items-center justify-center space-x-2">
          <i class="fa-solid fa-rotate-left"></i>
          <span>Reset</span>
        </a>
      </div>
    </div>

    <!-- Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Wanted List -->
      <div class="lg:col-span-2">
        <div id="wantedList" class="space-y-4">
          
          <?php if (empty($wantedAds)): ?>
          <!-- No Results -->
          <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-12 text-center">
            <i class="fa-solid fa-search text-4xl text-neutral-400 mb-4"></i>
            <h3 class="text-xl font-semibold text-neutral-900 mb-2">No wanted ads found</h3>
            <p class="text-neutral-600 mb-6">Try adjusting your filters or search terms.</p>
            <a href="<?php echo BASE_URL; ?>/wanted.php" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg transition-colors font-medium">
              View All Ads
            </a>
          </div>
          
          <?php else: ?>
          
          <!-- Wanted Ads Cards -->
          <?php foreach ($wantedAds as $ad): ?>
          <div class="bg-white rounded-xl shadow-sm border border-neutral-200 hover:shadow-md transition-all duration-300 hover:border-primary-300 overflow-hidden">
            
            <!-- Ad Header -->
            <div class="p-4 md:p-6 border-b border-neutral-100">
              <div class="flex flex-col md:flex-row md:items-start justify-between space-y-3 md:space-y-0">
                <div class="flex-1">
                  <div class="flex items-center space-x-3 mb-2">
                    <!-- Category Icon -->
                    <div class="w-10 h-10 bg-gradient-to-br from-primary-100 to-secondary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                      <i class="<?php echo e($ad['category_icon']); ?> text-primary-600"></i>
                    </div>
                    
                    <!-- Title and Meta -->
                    <div class="min-w-0 flex-1">
                      <h3 class="text-lg md:text-xl font-semibold text-neutral-900 break-words"><?php echo e($ad['title']); ?></h3>
                      <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-3 mt-1 space-y-1 sm:space-y-0">
                        <span class="text-sm text-neutral-600"><?php echo e($ad['category_name']); ?></span>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium w-fit
                          <?php 
                          echo $ad['urgency'] === 'high' ? 'bg-red-100 text-red-800' : 
                               ($ad['urgency'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); 
                          ?>">
                          <?php echo ucfirst($ad['urgency']); ?> Priority
                        </span>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Location and Posted Info -->
                  <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4 space-y-1 sm:space-y-0 text-sm text-neutral-500 mb-3">
                    <span class="flex items-center">
                      <i class="fa-solid fa-location-dot mr-1 flex-shrink-0"></i>
                      <span class="break-words"><?php echo e($ad['location']); ?></span>
                    </span>
                    <span class="flex items-center">
                      <i class="fa-solid fa-clock mr-1 flex-shrink-0"></i>
                      Posted <?php echo timeAgo($ad['created_at']); ?>
                    </span>
                    <span class="flex items-center">
                      <i class="fa-solid fa-user mr-1 flex-shrink-0"></i>
                      <?php echo e($ad['first_name'] . ' ' . substr($ad['last_name'], 0, 1) . '.'); ?>
                    </span>
                  </div>
                </div>
                
                <!-- Budget -->
                <?php if ($ad['budget_min'] || $ad['budget_max']): ?>
                <div class="text-left md:text-right">
                  <p class="text-sm text-neutral-500">Budget</p>
                  <p class="text-lg font-semibold text-primary-600">
                    <?php if ($ad['budget_min'] && $ad['budget_max']): ?>
                      <?php echo formatCurrency($ad['budget_min']); ?> - <?php echo formatCurrency($ad['budget_max']); ?>
                    <?php elseif ($ad['budget_min']): ?>
                      From <?php echo formatCurrency($ad['budget_min']); ?>
                    <?php elseif ($ad['budget_max']): ?>
                      Up to <?php echo formatCurrency($ad['budget_max']); ?>
                    <?php endif; ?>
                  </p>
                </div>
                <?php endif; ?>
              </div>
            </div>
            
            <!-- Ad Details -->
            <div class="p-4 md:p-6">
              <!-- Description -->
              <p class="text-neutral-700 mb-4 line-clamp-3 break-words">
                <?php echo nl2br(e($ad['description'])); ?>
              </p>
              
              <!-- Contact Method and Action -->
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                <div class="flex items-center space-x-2 text-sm text-neutral-600">
                  <i class="fa-solid fa-envelope flex-shrink-0"></i>
                  <span>
                    Contact via 
                    <?php 
                    echo $ad['contact_method'] === 'both' ? 'phone or email' : 
                         ($ad['contact_method'] === 'email' ? 'email only' : 'phone only'); 
                    ?>
                  </span>
                </div>
                
                <!-- Action Button -->
                <?php if ($currentUser && $currentUser['role'] === 'provider'): ?>
                <a href="<?php echo BASE_URL; ?>/respond-wanted.php?id=<?php echo $ad['id']; ?>" 
                   class="bg-secondary-600 hover:bg-secondary-700 text-white px-4 py-2 rounded-lg transition-colors font-medium text-sm text-center w-full sm:w-auto">
                  Respond to Request
                </a>
                <?php elseif ($currentUser): ?>
                <span class="text-sm text-neutral-500 text-center sm:text-left">Provider accounts can respond</span>
                <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/login.php" 
                   class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors font-medium text-sm text-center w-full sm:w-auto">
                  Login to Respond
                </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          
          <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex justify-center mt-8">
          <nav class="flex items-center space-x-2">
            
            <!-- Previous Page -->
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
               class="px-3 py-2 text-sm font-medium text-neutral-500 bg-white border border-neutral-300 rounded-md hover:bg-neutral-50">
              Previous
            </a>
            <?php endif; ?>
            
            <!-- Page Numbers -->
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            ?>
            
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
               class="px-3 py-2 text-sm font-medium <?php echo $i === $page ? 'text-primary-600 bg-primary-50 border-primary-500' : 'text-neutral-500 bg-white border-neutral-300 hover:bg-neutral-50'; ?> border rounded-md">
              <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <!-- Next Page -->
            <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
               class="px-3 py-2 text-sm font-medium text-neutral-500 bg-white border border-neutral-300 rounded-md hover:bg-neutral-50">
              Next
            </a>
            <?php endif; ?>
            
          </nav>
        </div>
        <?php endif; ?>
      </div>

      <!-- Post Form (Desktop Only) -->
      <div class="lg:col-span-1 hidden lg:block">
        <div class="sticky top-24">
          <?php if ($currentUser): ?>
          <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6">
            <div class="text-center mb-6">
              <div class="bg-secondary-100 p-3 rounded-full w-16 h-16 mx-auto mb-3 flex items-center justify-center">
                <i class="fa-solid fa-plus text-2xl text-secondary-600"></i>
              </div>
              <h3 class="text-xl font-semibold text-neutral-900 mb-2">Post a Wanted</h3>
              <p class="text-sm text-neutral-600">Tell us what service you need</p>
            </div>

            <form id="wantedForm" method="POST" action="" class="space-y-4">
              <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
              
              <div>
                <label for="postCategory" class="block text-sm font-medium text-neutral-700 mb-2">Category</label>
                <select id="postCategory" name="category_id" required 
                        class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500">
                  <option value="">Select a category</option>
                  <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label for="postTitle" class="block text-sm font-medium text-neutral-700 mb-2">Title</label>
                <input id="postTitle" name="title" type="text" placeholder="What service do you need?" required 
                       class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500" />
              </div>

              <div>
                <label for="postDescription" class="block text-sm font-medium text-neutral-700 mb-2">Description</label>
                <textarea id="postDescription" name="description" rows="3" placeholder="Describe what you need" required 
                          class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500 resize-none"></textarea>
              </div>

              <div>
                <label for="postLocation" class="block text-sm font-medium text-neutral-700 mb-2">Location</label>
                <input id="postLocation" name="location" type="text" placeholder="City/Area" required 
                       class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500" />
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label for="postBudgetMin" class="block text-sm font-medium text-neutral-700 mb-2">Min Budget</label>
                  <input id="postBudgetMin" name="budget_min" type="number" placeholder="Min Rs." min="0" step="0.01"
                         class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500" />
                </div>
                <div>
                  <label for="postBudgetMax" class="block text-sm font-medium text-neutral-700 mb-2">Max Budget</label>
                  <input id="postBudgetMax" name="budget_max" type="number" placeholder="Max Rs." min="0" step="0.01"
                         class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500" />
                </div>
              </div>

              <div>
                <label for="postUrgency" class="block text-sm font-medium text-neutral-700 mb-2">Urgency</label>
                <select id="postUrgency" name="urgency" 
                        class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500">
                  <option value="low">Low - Within a month</option>
                  <option value="medium" selected>Medium - Within a week</option>
                  <option value="high">High - ASAP</option>
                </select>
              </div>

              <button type="submit" name="submit_wanted"
                      class="w-full bg-secondary-600 text-white py-3 px-4 rounded-lg hover:bg-secondary-700 transition-colors font-medium flex items-center justify-center space-x-2 shadow-md hover:shadow-lg">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Post Request</span>
              </button>
            </form>
          </div>
          <?php else: ?>
          <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6 text-center">
            <div class="bg-primary-100 p-3 rounded-full w-16 h-16 mx-auto mb-3 flex items-center justify-center">
              <i class="fa-solid fa-sign-in-alt text-2xl text-primary-600"></i>
            </div>
            <h3 class="text-xl font-semibold text-neutral-900 mb-2">Login Required</h3>
            <p class="text-sm text-neutral-600 mb-4">Sign in to post a service request</p>
            <a href="<?php echo BASE_URL; ?>/login.php" 
               class="w-full bg-primary-600 text-white py-3 px-4 rounded-lg hover:bg-primary-700 transition-colors font-medium flex items-center justify-center space-x-2 shadow-md hover:shadow-lg">
              <i class="fa-solid fa-sign-in-alt"></i>
              <span>Login</span>
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Mobile/Tablet Post Modal -->
<?php if ($currentUser): ?>
<div id="postModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden lg:hidden">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
      <!-- Modal Header -->
      <div class="flex items-center justify-between p-6 border-b border-neutral-200">
        <div class="flex items-center space-x-3">
          <div class="bg-secondary-100 p-2 rounded-lg">
            <i class="fa-solid fa-plus text-lg text-secondary-600"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-neutral-900">Post a Request</h3>
            <p class="text-sm text-neutral-600">Tell us what you need</p>
          </div>
        </div>
        <button id="closeModal" class="p-2 text-neutral-400 hover:text-neutral-600 transition-colors">
          <i class="fa-solid fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <form id="wantedFormMobile" method="POST" action="" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
          
          <div>
            <label for="postCategoryMobile" class="block text-sm font-medium text-neutral-700 mb-2">Category</label>
            <select id="postCategoryMobile" name="category_id" required 
                    class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500">
              <option value="">Select a category</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label for="postTitleMobile" class="block text-sm font-medium text-neutral-700 mb-2">Title</label>
            <input id="postTitleMobile" name="title" type="text" placeholder="What service do you need?" required 
                   class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500" />
          </div>

          <div>
            <label for="postDescriptionMobile" class="block text-sm font-medium text-neutral-700 mb-2">Description</label>
            <textarea id="postDescriptionMobile" name="description" rows="3" placeholder="Describe what you need" required 
                      class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500 resize-none"></textarea>
          </div>

          <div>
            <label for="postLocationMobile" class="block text-sm font-medium text-neutral-700 mb-2">Location</label>
            <input id="postLocationMobile" name="location" type="text" placeholder="City/Area" required 
                   class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="postBudgetMinMobile" class="block text-sm font-medium text-neutral-700 mb-2">Min Budget</label>
              <input id="postBudgetMinMobile" name="budget_min" type="number" placeholder="Min Rs." min="0" step="0.01"
                     class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500" />
            </div>
            <div>
              <label for="postBudgetMaxMobile" class="block text-sm font-medium text-neutral-700 mb-2">Max Budget</label>
              <input id="postBudgetMaxMobile" name="budget_max" type="number" placeholder="Max Rs." min="0" step="0.01"
                     class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500" />
            </div>
          </div>

          <div>
            <label for="postUrgencyMobile" class="block text-sm font-medium text-neutral-700 mb-2">Urgency</label>
            <select id="postUrgencyMobile" name="urgency" 
                    class="block w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-secondary-500 focus:border-secondary-500">
              <option value="low">Low - Within a month</option>
              <option value="medium" selected>Medium - Within a week</option>
              <option value="high">High - ASAP</option>
            </select>
          </div>

          <div class="flex space-x-3 pt-4">
            <button type="button" id="cancelModal"
                    class="flex-1 bg-neutral-100 text-neutral-700 py-3 px-4 rounded-lg hover:bg-neutral-200 transition-colors font-medium">
              Cancel
            </button>
            <button type="submit" name="submit_wanted"
                    class="flex-1 bg-secondary-600 text-white py-3 px-4 rounded-lg hover:bg-secondary-700 transition-colors font-medium flex items-center justify-center space-x-2">
              <i class="fa-solid fa-paper-plane"></i>
              <span>Post</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// Toggle post form for mobile
function togglePostForm() {
  const modal = document.getElementById('postModal');
  if (modal) {
    modal.classList.toggle('hidden');
  }
}

// Modal controls
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('postModal');
  const postBtn = document.getElementById('postRequestBtnHeader');
  const closeBtn = document.getElementById('closeModal');
  const cancelBtn = document.getElementById('cancelModal');
  
  if (postBtn && modal) {
    postBtn.addEventListener('click', () => {
      modal.classList.remove('hidden');
    });
  }
  
  if (closeBtn && modal) {
    closeBtn.addEventListener('click', () => {
      modal.classList.add('hidden');
    });
  }
  
  if (cancelBtn && modal) {
    cancelBtn.addEventListener('click', () => {
      modal.classList.add('hidden');
    });
  }
  
  // Close modal on background click
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.add('hidden');
      }
    });
  }
});
</script>

<?php include 'includes/footer.php'; ?>
