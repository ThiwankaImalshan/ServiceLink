<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/ImageUploader.php';

// Require user to be logged in
if (!$auth->isLoggedIn()) {
  redirect(BASE_URL . '/login.php');
}

$currentUser = $auth->getCurrentUser();
$db = getDB();
$imageUploader = new ImageUploader();

$pageTitle = 'My Service • ServiceLink';
$pageDescription = 'Manage your service profile and business information.';

$currentProvider = null;
$isEditing = false;

// Check if user is already a provider
try {
  $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug 
        FROM providers p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.user_id = ?
    ");
  $stmt->execute([$currentUser['id']]);
  $currentProvider = $stmt->fetch();
  $isEditing = !empty($currentProvider);
} catch (PDOException $e) {
  // Error handled below
}

// Get categories
try {
  $stmt = $db->prepare("SELECT * FROM categories WHERE active = 1 ORDER BY name ASC");
  $stmt->execute();
  $categories = $stmt->fetchAll();
} catch (PDOException $e) {
  $categories = [];
}

// Get qualifications for current provider
$qualifications = [];
if ($isEditing) {
  try {
    $stmt = $db->prepare("SELECT * FROM qualifications WHERE provider_id = ? ORDER BY year_obtained DESC");
    $stmt->execute([$currentProvider['id']]);
    $qualifications = $stmt->fetchAll();
  } catch (PDOException $e) {
    $qualifications = [];
  }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Debug CSRF token.
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('Invalid CSRF token. Please try again.', 'error');
    error_log('CSRF token mismatch: ' . ($_POST['csrf_token'] ?? 'missing'));
    redirect(BASE_URL . '/my-service.php');
  }

  // Handle profile photo upload
  if (isset($_POST['upload_photo']) && isset($_FILES['profile_photo'])) {
    $uploadResult = $imageUploader->uploadImage($_FILES['profile_photo'], 'provider_');

    if ($uploadResult['success']) {
      // Delete old photo if exists
      if ($currentUser['profile_photo']) {
        $imageUploader->deleteImage(basename($currentUser['profile_photo']));
      }

      // Update database
      $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
      if ($stmt->execute([$uploadResult['path'], $currentUser['id']])) {
        setFlashMessage('Profile photo updated successfully!', 'success');
        $currentUser['profile_photo'] = $uploadResult['path'];
      } else {
        setFlashMessage('Failed to update profile photo in database.', 'error');
        $imageUploader->deleteImage($uploadResult['filename']);
      }
    } else {
      setFlashMessage($uploadResult['message'], 'error');
    }
    redirect(BASE_URL . '/my-service.php');
  }

  // Handle service profile photo upload within main form
  $profilePhotoPath = $currentUser['profile_photo']; // Keep existing photo by default
  if (isset($_FILES['service_profile_photo']) && $_FILES['service_profile_photo']['error'] === 0) {
    $uploadResult = $imageUploader->uploadImage($_FILES['service_profile_photo'], 'provider_');

    if ($uploadResult['success']) {
      // Delete old photo if exists
      if ($currentUser['profile_photo']) {
        $imageUploader->deleteImage(basename($currentUser['profile_photo']));
      }
      $profilePhotoPath = $uploadResult['path'];
    } else {
      setFlashMessage('Photo upload failed: ' . $uploadResult['message'], 'error');
      redirect(BASE_URL . '/my-service.php');
    }
  }

  // Handle service profile update
  $businessName = trim($_POST['business_name'] ?? '');
  $categoryId = (int)($_POST['category_id'] ?? 0);
  $location = trim($_POST['location'] ?? '');
  $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
  $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
  $hourlyRate = (float)($_POST['hourly_rate'] ?? 0);
  $experienceYears = (int)($_POST['experience_years'] ?? 0);
  $description = trim($_POST['description'] ?? '');
  $workingDays = $_POST['working_days'] ?? [];
  $workingHoursStart = $_POST['working_hours_start'] ?? '';
  $workingHoursEnd = $_POST['working_hours_end'] ?? '';
  $bestCallTime = trim($_POST['best_call_time'] ?? '');
  $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

  // Auto-determine skilled professional status based on qualifications
  $hasQualifications = false;
  if (isset($_POST['qualifications']) && is_array($_POST['qualifications'])) {
    foreach ($_POST['qualifications'] as $qualification) {
      $title = trim($qualification['title'] ?? '');
      $institute = trim($qualification['institute'] ?? '');
      if (!empty($title) && !empty($institute)) {
        $hasQualifications = true;
        break;
      }
    }
  }

  $isActive = 1; // Always active by default
  $isVerified = 0; // Admin verification required
  $isSkilled = $hasQualifications ? 1 : 0; // Auto-set based on qualifications

  // Debug incoming data
  error_log("Form Data Received: " . print_r($_POST, true));

  // Comprehensive validation
  $validationErrors = [];

  // Required fields validation
  $requiredFields = [
    'business_name' => 'Business name',
    'category_id' => 'Service category',
    'location' => 'Location',
    'hourly_rate' => 'Hourly rate',
    'experience_years' => 'Years of experience'
  ];

  foreach ($requiredFields as $field => $label) {
    $value = trim($_POST[$field] ?? '');
    if (empty($value)) {
      $validationErrors[] = $label . ' is required.';
      error_log("Validation failed: {$label} is empty");
    }
  }

  // Numeric validations
  if (!empty($_POST['hourly_rate']) && (!is_numeric($_POST['hourly_rate']) || $_POST['hourly_rate'] <= 0)) {
    $validationErrors[] = 'Hourly rate must be a positive number.';
    error_log("Validation failed: Invalid hourly rate: " . $_POST['hourly_rate']);
  }

  if (!empty($_POST['experience_years']) && (!is_numeric($_POST['experience_years']) || $_POST['experience_years'] < 0)) {
    $validationErrors[] = 'Experience years must be a non-negative number.';
    error_log("Validation failed: Invalid experience years: " . $_POST['experience_years']);
  }

  // Working days validation
  if (!empty($_POST['working_days']) && !is_array($_POST['working_days'])) {
    $validationErrors[] = 'Working days must be selected properly.';
    error_log("Validation failed: Working days is not an array");
  }

  // Time format validation
  if (!empty($_POST['working_hours_start']) && !preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $_POST['working_hours_start'])) {
    $validationErrors[] = 'Invalid working hours start time format.';
    error_log("Validation failed: Invalid working hours start: " . $_POST['working_hours_start']);
  }

  if (!empty($_POST['working_hours_end']) && !preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $_POST['working_hours_end'])) {
    $validationErrors[] = 'Invalid working hours end time format.';
    error_log("Validation failed: Invalid working hours end: " . $_POST['working_hours_end']);
  }
  if (!isset($_POST['hourly_rate']) || $_POST['hourly_rate'] < 1 || $_POST['hourly_rate'] > 999) {
    $validationErrors[] = 'Hourly rate must be between Rs. 1 and Rs. 999.';
  }
  if (!isset($_POST['experience_years']) || $_POST['experience_years'] < 0 || $_POST['experience_years'] > 50) {
    $validationErrors[] = 'Experience years must be between 0 and 50.';
  }

  if (!empty($validationErrors)) {
    error_log("Validation errors found: " . implode(", ", $validationErrors));
    foreach ($validationErrors as $error) {
      setFlashMessage($error, 'error');
    }
    redirect(BASE_URL . '/my-service.php');
    exit; // Ensure script stops here if validation fails
  }

  // Log validated data
  error_log("Validation passed. Proceeding with data:");
  error_log("Business Name: $businessName");
  error_log("Category ID: $categoryId");
  error_log("Location: $location");
  error_log("Hourly Rate: $hourlyRate");
  error_log("Experience Years: $experienceYears");


  // Validate qualifications if provided
  $qualificationErrors = [];
  if (isset($_POST['qualifications']) && is_array($_POST['qualifications'])) {
    foreach ($_POST['qualifications'] as $index => $qualification) {
      $title = trim($qualification['title'] ?? '');
      $institute = trim($qualification['institute'] ?? '');
      $yearObtained = !empty($qualification['year_obtained']) ? (int)$qualification['year_obtained'] : null;

      // Skip completely empty qualifications
      if (empty($title) && empty($institute) && empty($yearObtained)) {
        continue;
      }

      // Validate required fields for non-empty qualifications
      if (empty($title)) {
        $qualificationErrors[] = "Qualification #" . ($index + 1) . ": Title is required";
      }
      if (empty($institute)) {
        $qualificationErrors[] = "Qualification #" . ($index + 1) . ": Institute is required";
      }
      if (!empty($yearObtained) && ($yearObtained < 1900 || $yearObtained > 2030)) {
        $qualificationErrors[] = "Qualification #" . ($index + 1) . ": Year must be between 1900 and 2030";
      }

      // Validate certificate image if provided
      $fileInputName = "qualifications_{$index}_certificate_image";
      if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
          $qualificationErrors[] = "Qualification #" . ($index + 1) . ": Certificate image upload failed";
        } elseif ($_FILES[$fileInputName]['size'] > 5 * 1024 * 1024) {
          $qualificationErrors[] = "Qualification #" . ($index + 1) . ": Certificate image must be less than 5MB";
        } elseif (!in_array($_FILES[$fileInputName]['type'], ['image/jpeg', 'image/png', 'image/gif'])) {
          $qualificationErrors[] = "Qualification #" . ($index + 1) . ": Certificate image must be JPG, PNG, or GIF";
        }
      }
    }
  }

  if (!empty($qualificationErrors)) {
    setFlashMessage('Qualification errors: ' . implode('; ', $qualificationErrors), 'error');
  } else {
    try {
      if ($isEditing) {
        // Update existing provider
        $stmt = $db->prepare("
                    UPDATE providers SET 
                        business_name = ?, category_id = ?, location = ?, latitude = ?, longitude = ?, 
                        hourly_rate = ?, experience_years = ?, description = ?, working_days = ?, 
                        working_hours_start = ?, working_hours_end = ?, best_call_time = ?, tags = ?,
                        profile_photo = ?, is_active = ?, is_verified = ?, is_skilled = ?
                    WHERE user_id = ?
                ");
        $stmt->execute([
          $businessName,
          $categoryId,
          $location,
          $latitude,
          $longitude,
          $hourlyRate,
          $experienceYears,
          $description,
          json_encode($workingDays),
          $workingHoursStart,
          $workingHoursEnd,
          $bestCallTime,
          json_encode($tags),
          $profilePhotoPath,
          $isActive,
          $isVerified,
          $isSkilled,
          $currentUser['id']
        ]);

        // Update user profile photo if changed
        if ($profilePhotoPath !== $currentUser['profile_photo']) {
          $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
          $stmt->execute([$profilePhotoPath, $currentUser['id']]);
          $currentUser['profile_photo'] = $profilePhotoPath;
        }

        setFlashMessage('Your service profile has been updated successfully!', 'success');
      } else {
        // Create new provider
        try {
          // Ensure all required data is present
          if (!$currentUser['id']) {
            throw new Exception("User ID is missing");
          }

          // Format and validate all data before insert
          $insertData = [
            'user_id' => $currentUser['id'],
            'category_id' => $categoryId,
            'business_name' => $businessName,
            'location' => $location,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'hourly_rate' => $hourlyRate,
            'experience_years' => $experienceYears,
            'working_days' => is_array($workingDays) ? json_encode($workingDays) : null,
            'working_hours_start' => $workingHoursStart ?: null,
            'working_hours_end' => $workingHoursEnd ?: null,
            'best_call_time' => $bestCallTime ?: null,
            'description' => $description ?: null,
            'profile_photo' => $profilePhotoPath ?: null,
            'tags' => !empty($tags) ? json_encode($tags) : null,
            'is_active' => $isActive,
            'is_verified' => $isVerified,
            'is_skilled' => $isSkilled
          ];

          // Debug log before insert
          error_log("Attempting to insert new provider with data: " . print_r($insertData, true));

          $stmt = $db->prepare("
                        INSERT INTO providers (
                            user_id, category_id, business_name, location, latitude, longitude, 
                            hourly_rate, experience_years, description, working_days, working_hours_start, 
                            working_hours_end, best_call_time, tags, profile_photo, is_active, is_verified, is_skilled
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

          // Prepare the data array for better error tracking
          $insertData = [
            $currentUser['id'],
            $categoryId,
            $businessName,
            $location,
            $latitude,
            $longitude,
            $hourlyRate,
            $experienceYears,
            $description,
            json_encode($workingDays),
            $workingHoursStart,
            $workingHoursEnd,
            $bestCallTime,
            json_encode($tags),
            $profilePhotoPath,
            $isActive,
            $isVerified,
            $isSkilled
          ];

          // Execute with error handling
          if (!$stmt->execute($insertData)) {
            $errorInfo = $stmt->errorInfo();
            error_log("SQL Error: " . print_r($errorInfo, true));
            throw new Exception("Database error: " . $errorInfo[2]);
          }

          // Log successful insertion
          error_log("Successfully inserted new provider with ID: " . $db->lastInsertId());

          // Update user role to provider
          $stmt = $db->prepare("UPDATE users SET role = 'provider', profile_photo = ? WHERE id = ?");
          $stmt->execute([$profilePhotoPath, $currentUser['id']]);
          $_SESSION['role'] = 'provider';
          $currentUser['profile_photo'] = $profilePhotoPath;

          // Set success message and redirect to profile page
          setFlashMessage('Your service profile has been created successfully! You can now manage your services.', 'success');
          redirect(BASE_URL . '/provider-profile.php');
          exit;
        } catch (Exception $e) {
          error_log("Exception during provider insertion: " . $e->getMessage());
          setFlashMessage('Error creating provider profile: ' . $e->getMessage(), 'error');
          redirect(BASE_URL . '/my-service.php');
          exit;
        }
      }

      // Handle qualifications processing
      if (isset($_POST['qualifications']) && is_array($_POST['qualifications'])) {
        $providerId = $isEditing ? $currentProvider['id'] : $db->lastInsertId();

        foreach ($_POST['qualifications'] as $index => $qualification) {
          $title = trim($qualification['title'] ?? '');
          $institute = trim($qualification['institute'] ?? '');
          $yearObtained = !empty($qualification['year_obtained']) ? (int)$qualification['year_obtained'] : null;

          // Skip empty qualifications
          if (empty($title) || empty($institute)) {
            continue;
          }

          // Handle certificate image upload
          $certificateImagePath = null;
          $fileInputName = "qualifications_{$index}_certificate_image";

          if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === 0) {
            $uploadResult = $imageUploader->uploadImage($_FILES[$fileInputName], 'certificate_');
            if ($uploadResult['success']) {
              $certificateImagePath = $uploadResult['path'];
            }
          }

          // Insert qualification
          $stmt = $db->prepare("
                        INSERT INTO qualifications (provider_id, title, institute, year_obtained, certificate_image) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
          $stmt->execute([$providerId, $title, $institute, $yearObtained, $certificateImagePath]);
        }
      }

      redirect(BASE_URL . '/my-service.php');
    } catch (PDOException $e) {
      setFlashMessage('An error occurred while saving your profile.', 'error');
    } catch (Exception $e) {
      setFlashMessage('An unexpected error occurred. Please try again later.', 'error');
      error_log('Form submission error: ' . $e->getMessage());
      redirect(BASE_URL . '/my-service.php');
    }
  }
}


# Parse current provider data for form
$formData = [
  'business_name' => $currentProvider['business_name'] ?? '',
  'category_id' => $currentProvider['category_id'] ?? '',
  'location' => $currentProvider['location'] ?? '',
  'latitude' => $currentProvider['latitude'] ?? '',
  'longitude' => $currentProvider['longitude'] ?? '',
  'hourly_rate' => $currentProvider['hourly_rate'] ?? '',
  'experience_years' => $currentProvider['experience_years'] ?? '',
  'description' => $currentProvider['description'] ?? '',
  'working_days' => json_decode($currentProvider['working_days'] ?? '[]', true) ?: [],
  'working_hours_start' => $currentProvider['working_hours_start'] ?? '',
  'working_hours_end' => $currentProvider['working_hours_end'] ?? '',
  'best_call_time' => $currentProvider['best_call_time'] ?? '',
  'tags' => implode(', ', json_decode($currentProvider['tags'] ?? '[]', true) ?: []),
  'profile_photo' => $currentProvider['profile_photo'] ?? '',
  'rating' => $currentProvider['rating'] ?? '0.00',
  'review_count' => $currentProvider['review_count'] ?? '0',
  'is_active' => $currentProvider['is_active'] ?? 1,
  'is_verified' => $currentProvider['is_verified'] ?? 0,
  'is_skilled' => $currentProvider['is_skilled'] ?? 0
];

// Include header after processing
include 'includes/header.php';
?>

<!-- Leaflet CSS and JS for Map functionality -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
  integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
  crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
  integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
  crossorigin=""></script>

<!-- Main Content -->
<main class="py-12">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <!-- Header -->
    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-neutral-900 mb-4">
        <?php echo $isEditing ? 'Update Your Service' : 'Register Your Service'; ?>
      </h1>
      <p class="text-lg text-neutral-600 max-w-2xl mx-auto">
        <?php echo $isEditing ? 'Update your service information and reach more customers.' : 'Join our platform and connect with customers who need your skills and expertise.'; ?>
      </p>
    </div>

    <!-- Main Layout: Left Content + Right Form -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">

      <!-- Left Side: Promotional Content (Hidden on Mobile/Tablet) -->
      <div class="hidden lg:block lg:sticky lg:top-24">
        <!-- Hero Image/Background -->
        <div class="relative rounded-2xl overflow-hidden mb-8 h-64 lg:h-80">
          <div class="absolute inset-0 bg-gradient-to-br from-primary-600/90 to-secondary-600/90"></div>
          <div class="absolute inset-0 flex items-center justify-center text-white text-center p-8">
            <div>
              <h2 class="text-3xl font-bold mb-4">Grow Your Business</h2>
              <p class="text-lg opacity-90">Connect with customers who need your expertise</p>
            </div>
          </div>
        </div>

        <!-- Benefits -->
        <div class="bg-white rounded-2xl shadow-lg border border-neutral-200 p-6 mb-6">
          <h3 class="text-xl font-semibold text-neutral-900 mb-4 flex items-center">
            <i class="fa-solid fa-star text-primary-600 mr-2"></i>
            Why Join ServiceLink?
          </h3>
          <div class="space-y-4">
            <div class="flex items-start space-x-3">
              <div class="bg-primary-100 p-2 rounded-lg flex-shrink-0">
                <i class="fa-solid fa-users text-primary-600"></i>
              </div>
              <div>
                <h4 class="font-medium text-neutral-900">Access to Customers</h4>
                <p class="text-sm text-neutral-600">Connect with clients actively looking for your services</p>
              </div>
            </div>
            <div class="flex items-start space-x-3">
              <div class="bg-secondary-100 p-2 rounded-lg flex-shrink-0">
                <i class="fa-solid fa-calendar-check text-secondary-600"></i>
              </div>
              <div>
                <h4 class="font-medium text-neutral-900">Flexible Schedule</h4>
                <p class="text-sm text-neutral-600">Work on your own terms and set your availability</p>
              </div>
            </div>
            <div class="flex items-start space-x-3">
              <div class="bg-green-100 p-2 rounded-lg flex-shrink-0">
                <i class="fa-solid fa-dollar-sign text-green-600"></i>
              </div>
              <div>
                <h4 class="font-medium text-neutral-900">Set Your Rates</h4>
                <p class="text-sm text-neutral-600">Price your services competitively and earn more</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4">
          <div class="bg-white rounded-xl shadow-md border border-neutral-200 p-4 text-center">
            <div class="text-2xl font-bold text-primary-600">1000+</div>
            <div class="text-sm text-neutral-600">Active Providers</div>
          </div>
          <div class="bg-white rounded-xl shadow-md border border-neutral-200 p-4 text-center">
            <div class="text-2xl font-bold text-secondary-600">5000+</div>
            <div class="text-sm text-neutral-600">Happy Customers</div>
          </div>
          <div class="bg-white rounded-xl shadow-md border border-neutral-200 p-4 text-center">
            <div class="text-2xl font-bold text-green-600">98%</div>
            <div class="text-sm text-neutral-600">Satisfaction Rate</div>
          </div>
        </div>
      </div>

      <!-- Right Side: Registration Form -->
      <div class="bg-white rounded-2xl shadow-xl border border-neutral-200 p-8">
        <form id="providerForm" method="POST" action="" enctype="multipart/form-data" class="space-y-8">
          <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

          <!-- Personal Information Section -->
          <div class="border-b border-neutral-200 pb-8">
            <h2 class="text-xl font-semibold text-neutral-900 mb-6 flex items-center">
              <i class="fa-solid fa-user text-primary-600 mr-2"></i>
              Personal Information
            </h2>

            <!-- Profile Photo Upload -->
            <div class="mb-6">
              <label for="service_profile_photo" class="block text-sm font-medium text-neutral-700 mb-2">
                Profile Photo
                <span class="text-xs text-neutral-500">(Recommended for better visibility)</span>
              </label>

              <!-- Photo Preview Section -->
              <div class="flex items-center space-x-6">
                <!-- Current/Preview Photo Display -->
                <div class="flex-shrink-0">
                  <!-- Current Profile Photo (if editing and has photo) -->
                  <?php if ($isEditing && $currentUser['profile_photo']): ?>
                    <div id="current-service-photo">
                      <img src="<?php echo e(ImageUploader::getProfileImageUrl($currentUser['profile_photo'])); ?>"
                        alt="Current Profile Photo"
                        class="w-24 h-24 rounded-full object-cover border-4 border-primary-100">
                      <p class="text-xs text-center text-neutral-600 mt-2">Current Photo</p>
                    </div>
                  <?php endif; ?>

                  <!-- Photo Preview (hidden by default) -->
                  <div id="service-photo-preview" class="hidden">
                    <img id="service-preview-image" src="" alt="Profile Preview"
                      class="w-24 h-24 rounded-full object-cover border-4 border-primary-200">
                    <p class="text-xs text-center text-neutral-600 mt-2">Photo Preview</p>
                  </div>

                  <!-- Upload Placeholder (shown when no current photo or when cleared) -->
                  <div id="service-photo-placeholder" class="<?php echo ($isEditing && $currentUser['profile_photo']) ? 'hidden' : ''; ?> w-24 h-24 rounded-full bg-neutral-100 border-4 border-dashed border-neutral-300 flex items-center justify-center">
                    <i class="fa-solid fa-camera text-2xl text-neutral-400"></i>
                  </div>
                </div>

                <!-- File Input and Instructions -->
                <div class="flex-1">
                  <input type="file" id="service_profile_photo" name="service_profile_photo" accept="image/*"
                    class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                  <p class="text-xs text-neutral-500 mt-1">
                    Upload a clear photo of yourself. Maximum 5MB. Supported formats: JPG, PNG, GIF, WebP.
                  </p>
                  <button type="button" id="clear-service-photo"
                    class="mt-2 text-sm text-neutral-600 hover:text-neutral-800 underline">
                    Clear Photo
                  </button>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Business Name -->
              <div class="md:col-span-2">
                <label for="business_name" class="block text-sm font-medium text-neutral-700 mb-2">Business Name</label>
                <input type="text" id="business_name" name="business_name" maxlength="100"
                  value="<?php echo e($formData['business_name']); ?>"
                  placeholder="Leave blank to use your full name"
                  class="w-full px-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
              </div>

              <!-- Category -->
              <div class="md:col-span-2">
                <label for="category_id" class="block text-sm font-medium text-neutral-700 mb-2">Service Category *</label>
                <select id="category_id" name="category_id" required class="w-full px-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                  <option value="">Select a category</option>
                  <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo $formData['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                      <?php echo e($category['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <!-- Location Section -->
          <div class="border-b border-neutral-200 pb-8">
            <h2 class="text-xl font-semibold text-neutral-900 mb-6 flex items-center">
              <i class="fa-solid fa-location-dot text-primary-600 mr-2"></i>
              Location
            </h2>

            <div class="space-y-6">
              <!-- Location Input -->
              <div>
                <label for="location" class="block text-sm font-medium text-neutral-700 mb-2">
                  Service Location *
                  <span class="text-xs text-neutral-500">(Area/City where you provide services)</span>
                </label>
                <input type="text" id="location" name="location" required maxlength="100"
                  value="<?php echo e($formData['location']); ?>"
                  placeholder="City or area you serve"
                  class="w-full px-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
              </div>

              <!-- Map Location Selection -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 mb-2">
                  Precise Location (Optional)
                  <span class="text-xs text-neutral-500">(Click on map or use current location)</span>
                </label>

                <div class="space-y-4">
                  <div>
                    <button type="button" id="use-current-location"
                      class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors flex items-center space-x-1"
                      title="Use current location">
                      <i class="fa-solid fa-location-crosshairs"></i>
                      <span class="hidden sm:inline">Use Current Location</span>
                    </button>
                    <p class="text-xs text-neutral-500 mt-1">
                      Click "Use Current Location" or click on the map to set your precise location
                    </p>
                  </div>

                  <!-- Map Container -->
                  <div class="border border-neutral-300 rounded-lg overflow-hidden">
                    <div id="location-map" style="height: 300px; width: 100%; z-index: 10;" class="bg-neutral-100 flex items-center justify-center">
                      <div class="text-center text-neutral-500">
                        <i class="fa-solid fa-map-marker-alt text-3xl mb-2"></i>
                        <p>Click on map or use current location to set precise location</p>
                      </div>
                    </div>
                  </div>

                  <!-- Selected Location Display -->
                  <div id="selected-location" class="<?php echo empty($formData['latitude']) ? 'hidden' : ''; ?> p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-start space-x-2">
                      <i class="fa-solid fa-map-marker-alt text-green-600 mt-1"></i>
                      <div class="flex-1">
                        <p class="text-sm font-medium text-green-900">Selected Location:</p>
                        <p id="selected-address" class="text-sm text-green-700"><?php echo !empty($formData['latitude']) ? $formData['latitude'] . ', ' . $formData['longitude'] : ''; ?></p>
                        <p class="text-xs text-green-600 mt-1">
                          Lat: <span id="selected-lat"><?php echo e($formData['latitude']); ?></span>,
                          Lng: <span id="selected-lng"><?php echo e($formData['longitude']); ?></span>
                        </p>
                      </div>
                      <button type="button" id="clear-location"
                        class="text-green-600 hover:text-green-800 p-1"
                        title="Clear location">
                        <i class="fa-solid fa-times"></i>
                      </button>
                    </div>
                  </div>

                  <!-- Hidden inputs for coordinates -->
                  <input type="hidden" id="latitude" name="latitude" value="<?php echo e($formData['latitude']); ?>">
                  <input type="hidden" id="longitude" name="longitude" value="<?php echo e($formData['longitude']); ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Service Details Section -->
          <div class="border-b border-neutral-200 pb-8">
            <h2 class="text-xl font-semibold text-neutral-900 mb-6 flex items-center">
              <i class="fa-solid fa-briefcase text-primary-600 mr-2"></i>
              Service Details
            </h2>

            <div class="space-y-6">
              <!-- Rate and Experience -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Hourly Rate -->
                <div>
                  <label for="hourly_rate" class="block text-sm font-medium text-neutral-700 mb-2">Hourly Rate (LKR) *</label>
                  <div class="relative">
                    <span class="absolute left-4 top-3 text-neutral-500">Rs.</span>
                    <input type="number" id="hourly_rate" name="hourly_rate" required min="1" max="999" step="0.01"
                      value="<?php echo e($formData['hourly_rate']); ?>"
                      placeholder="50.00"
                      class="w-full pl-12 pr-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                  </div>
                </div>

                <!-- Experience Years -->
                <div>
                  <label for="experience_years" class="block text-sm font-medium text-neutral-700 mb-2">Years of Experience *</label>
                  <input type="number" id="experience_years" name="experience_years" required min="0" max="50"
                    value="<?php echo e($formData['experience_years']); ?>"
                    placeholder="5"
                    class="w-full px-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                </div>
              </div>

              <!-- Description -->
              <div>
                <label for="description" class="block text-sm font-medium text-neutral-700 mb-2">About Your Service</label>
                <textarea id="description" name="description" rows="5"
                  placeholder="Describe your services, experience, and what makes you stand out..."
                  class="w-full px-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-none transition-colors"><?php echo e($formData['description']); ?></textarea>
              </div>

              <!-- Tags/Skills -->
              <div>
                <label for="tags" class="block text-sm font-medium text-neutral-700 mb-2">Skills & Specialties</label>
                <input type="text" id="tags" name="tags"
                  value="<?php echo e($formData['tags']); ?>"
                  placeholder="repair, installation, maintenance (separated by commas)"
                  class="w-full px-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                <p class="text-sm text-neutral-500 mt-1">Enter skills or specialties separated by commas</p>
              </div>
            </div>
          </div>

          <!-- Qualifications Section -->
          <div class="border-b border-neutral-200 pb-8">
            <h2 class="text-xl font-semibold text-neutral-900 mb-6 flex items-center">
              <i class="fa-solid fa-certificate text-primary-600 mr-2"></i>
              Qualifications & Certifications
              <span class="ml-2 text-sm font-normal text-neutral-500">(Optional)</span>
            </h2>

            <!-- Auto-skilled notification -->
            <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
              <div class="flex items-start">
                <i class="fa-solid fa-info-circle text-blue-600 mt-0.5 mr-3"></i>
                <div>
                  <h3 class="text-sm font-medium text-blue-800 mb-1">Skilled Professional Status</h3>
                  <p class="text-sm text-blue-700">
                    Adding qualifications will automatically mark you as a "Skilled Professional"
                    - showing customers that you're certified or highly experienced.
                  </p>
                </div>
              </div>
            </div>

            <!-- Existing Qualifications (for editing) -->
            <?php if ($isEditing && !empty($qualifications)): ?>
              <div class="mb-6">
                <h3 class="text-lg font-medium text-neutral-800 mb-4">Current Qualifications</h3>
                <div class="space-y-4">
                  <?php foreach ($qualifications as $qualification): ?>
                    <div class="bg-neutral-50 border border-neutral-200 rounded-lg p-4">
                      <div class="flex items-start justify-between">
                        <div class="flex-1">
                          <h4 class="font-medium text-neutral-900"><?php echo e($qualification['title']); ?></h4>
                          <p class="text-sm text-neutral-600"><?php echo e($qualification['institute']); ?></p>
                          <p class="text-xs text-neutral-500">Year: <?php echo e($qualification['year_obtained']); ?></p>
                        </div>
                        <?php if ($qualification['certificate_image']): ?>
                          <div class="ml-4">
                            <img src="<?php echo e($qualification['certificate_image']); ?>"
                              alt="Certificate"
                              class="w-16 h-16 object-cover rounded border">
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- Dynamic Qualifications Form -->
            <div>
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-neutral-800">
                  <?php echo $isEditing ? 'Add New Qualifications' : 'Add Qualifications'; ?>
                </h3>
                <button type="button" id="add-qualification-btn"
                  class="inline-flex items-center px-3 py-2 bg-primary-600 text-white text-sm rounded-lg hover:bg-primary-700 transition-colors">
                  <i class="fa-solid fa-plus mr-2"></i>
                  Add Qualification
                </button>
              </div>

              <div id="qualifications-container" class="space-y-4">
                <!-- Qualification entries will be added here dynamically -->
              </div>

              <div id="no-qualifications-message" class="text-center py-8 bg-neutral-50 rounded-lg border-2 border-dashed border-neutral-300">
                <i class="fa-solid fa-certificate text-3xl text-neutral-400 mb-3"></i>
                <p class="text-neutral-600 mb-2">No qualifications added yet</p>
                <p class="text-sm text-neutral-500">Click "Add Qualification" to add your certifications and qualifications</p>
              </div>
            </div>
          </div>

          <!-- Availability Section -->
          <div class="border-b border-neutral-200 pb-8">
            <h2 class="text-xl font-semibold text-neutral-900 mb-6 flex items-center">
              <i class="fa-solid fa-calendar-check text-primary-600 mr-2"></i>
              Availability
            </h2>

            <div class="space-y-6">
              <!-- Working Days -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 mb-3">Working Days</label>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                  <?php
                  $allDays = [
                    'Mon' => 'Monday',
                    'Tue' => 'Tuesday',
                    'Wed' => 'Wednesday',
                    'Thu' => 'Thursday',
                    'Fri' => 'Friday',
                    'Sat' => 'Saturday',
                    'Sun' => 'Sunday'
                  ];
                  foreach ($allDays as $short => $full):
                  ?>
                    <label class="flex items-center justify-center p-3 border-2 border-neutral-200 rounded-lg cursor-pointer hover:bg-primary-50 hover:border-primary-300 transition-all has-[:checked]:bg-primary-100 has-[:checked]:border-primary-500">
                      <input type="checkbox" name="working_days[]" value="<?php echo $short; ?>"
                        <?php echo in_array($short, $formData['working_days']) ? 'checked' : ''; ?>
                        class="text-primary-600 focus:ring-primary-500 rounded">
                      <span class="ml-2 text-sm font-medium text-neutral-900"><?php echo $short; ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Working Hours -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label for="working_hours_start" class="block text-sm font-medium text-neutral-700 mb-2">Start Time</label>
                  <input type="time" id="working_hours_start" name="working_hours_start"
                    value="<?php echo e($formData['working_hours_start']); ?>"
                    class="w-full px-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                </div>

                <div>
                  <label for="working_hours_end" class="block text-sm font-medium text-neutral-700 mb-2">End Time</label>
                  <input type="time" id="working_hours_end" name="working_hours_end"
                    value="<?php echo e($formData['working_hours_end']); ?>"
                    class="w-full px-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                </div>
              </div>

              <!-- Best Call Time -->
              <div>
                <label for="best_call_time" class="block text-sm font-medium text-neutral-700 mb-2">Best Time to Call</label>
                <input type="text" id="best_call_time" name="best_call_time" maxlength="50"
                  value="<?php echo e($formData['best_call_time']); ?>"
                  placeholder="e.g., 9:00 AM - 5:00 PM or Weekdays only"
                  class="w-full px-4 py-3 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
              </div>
            </div>
          </div>

          <!-- Contact Information Section -->
          <div class="border-b border-neutral-200 pb-8">
            <h2 class="text-xl font-semibold text-neutral-900 mb-6 flex items-center">
              <i class="fa-solid fa-phone text-primary-600 mr-2"></i>
              Contact Information
            </h2>

            <div class="bg-neutral-50 rounded-lg p-4">
              <p class="text-sm text-neutral-600">
                <i class="fa-solid fa-info-circle text-primary-600 mr-1"></i>
                Your contact information from your account will be used for customer inquiries.
              </p>
            </div>
          </div>

          <!-- Status Section -->
          <div class="pb-8">
            <h2 class="text-xl font-semibold text-neutral-900 mb-6 flex items-center">
              <i class="fa-solid fa-toggle-on text-primary-600 mr-2"></i>
              Status
            </h2>

            <div class="flex items-center justify-between p-4 bg-neutral-50 rounded-lg">
              <div>
                <h3 class="font-medium text-neutral-900">Service Active</h3>
                <p class="text-sm text-neutral-600">Make your service visible to potential customers</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="is_active" value="1" checked class="sr-only peer">
                <div class="w-11 h-6 bg-neutral-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
              </label>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="flex flex-col sm:flex-row items-center justify-between pt-8 border-t border-neutral-200">
            <p class="text-sm text-neutral-500 mb-4 sm:mb-0">
              By submitting, you agree to our Terms of Service
            </p>
            <div class="flex space-x-4">
              <a href="<?php echo BASE_URL; ?>/index.php" class="px-6 py-3 border border-neutral-300 rounded-lg text-neutral-700 hover:bg-neutral-50 transition-colors font-medium">
                Cancel
              </a>
              <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-3 rounded-lg transition-colors font-medium shadow-lg hover:shadow-xl transform hover:scale-105">
                <?php echo $isEditing ? 'Update Service' : 'Create Service'; ?>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <?php if ($isEditing): ?>
      <!-- Profile Photo Upload Section -->
      <div class="mt-8 bg-white rounded-2xl shadow-lg border border-neutral-200 p-6">
        <h3 class="text-lg font-semibold text-neutral-900 mb-4 flex items-center">
          <i class="fa-solid fa-camera text-primary-600 mr-2"></i>
          Profile Photo
        </h3>

        <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-4 sm:space-y-0 sm:space-x-6">
          <div class="flex-shrink-0">
            <!-- Current Profile Photo -->
            <div id="current-photo">
              <img src="<?php echo e(ImageUploader::getProfileImageUrl($currentUser['profile_photo'])); ?>"
                alt="Profile Photo"
                class="w-20 h-20 rounded-full object-cover border-4 border-primary-100">
            </div>

            <!-- Photo Preview -->
            <div id="photo-preview" class="hidden">
              <img id="preview-image" src="" alt="Profile Preview"
                class="w-20 h-20 rounded-full object-cover border-4 border-primary-200">
              <p class="text-xs text-center text-neutral-600 mt-2">New Photo Preview</p>
            </div>

            <!-- Upload Placeholder -->
            <div id="photo-placeholder" class="hidden w-20 h-20 rounded-full bg-neutral-100 border-4 border-dashed border-neutral-300 flex items-center justify-center">
              <i class="fa-solid fa-camera text-xl text-neutral-400"></i>
            </div>
          </div>

          <div class="flex-1">
            <form method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-start sm:items-end space-y-3 sm:space-y-0 sm:space-x-4">
              <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

              <div class="flex-1">
                <label for="profile_photo" class="block text-sm font-medium text-neutral-700 mb-2">
                  Choose New Photo
                </label>
                <input type="file"
                  name="profile_photo"
                  id="profile_photo"
                  accept="image/*"
                  class="block w-full text-sm text-neutral-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 transition-colors">
                <p class="text-xs text-neutral-500 mt-1">JPG, PNG, GIF, WebP. Max 5MB.</p>
              </div>

              <div class="flex space-x-2">
                <button type="submit"
                  name="upload_photo"
                  class="bg-primary-600 hover:bg-primary-700 text-white py-2 px-6 rounded-lg transition-colors font-medium whitespace-nowrap">
                  Upload Photo
                </button>
                <button type="button"
                  id="clear-photo-preview"
                  class="bg-neutral-500 hover:bg-neutral-600 text-white py-2 px-4 rounded-lg transition-colors font-medium whitespace-nowrap">
                  Clear
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Additional Actions -->
      <div class="mt-8 bg-white rounded-2xl shadow-lg border border-neutral-200 p-6">
        <h3 class="text-lg font-semibold text-neutral-900 mb-4 flex items-center">
          <i class="fa-solid fa-cog text-primary-600 mr-2"></i>
          Additional Actions
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <a href="<?php echo BASE_URL; ?>/manage-qualifications.php" class="flex items-center space-x-2 text-primary-600 hover:text-primary-700 font-medium p-3 rounded-lg hover:bg-primary-50 transition-colors">
            <i class="fa-solid fa-certificate"></i>
            <span>Manage Qualifications</span>
          </a>
          <a href="<?php echo BASE_URL; ?>/view-reviews.php" class="flex items-center space-x-2 text-primary-600 hover:text-primary-700 font-medium p-3 rounded-lg hover:bg-primary-50 transition-colors">
            <i class="fa-solid fa-star"></i>
            <span>View Reviews</span>
          </a>
          <a href="<?php echo BASE_URL; ?>/profile.php" class="flex items-center space-x-2 text-primary-600 hover:text-primary-700 font-medium p-3 rounded-lg hover:bg-primary-50 transition-colors">
            <i class="fa-solid fa-user-cog"></i>
            <span>Account Settings</span>
          </a>
        </div>
      </div>
    <?php endif; ?>

  </div>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Initialize photo preview functionality
    initializePhotoPreview();

    // Initialize service photo preview functionality
    initializeServicePhotoPreview();

    // Initialize location functionality 
    initializeLocationHandlers();

    // Initialize map if coordinates exist
    if (document.getElementById('latitude').value && document.getElementById('longitude').value) {
      setTimeout(() => {
        const lat = parseFloat(document.getElementById('latitude').value);
        const lng = parseFloat(document.getElementById('longitude').value);
        if (!isNaN(lat) && !isNaN(lng)) {
          initializeMap();
          if (window.map) {
            window.map.setView([lat, lng], 15);
            setLocation(lat, lng);
          }
        }
      }, 100);
    }
  });

  // Photo preview functionality
  function initializePhotoPreview() {
    const photoPreview = document.getElementById('photo-preview');
    const previewImage = document.getElementById('preview-image');
    const currentPhoto = document.getElementById('current-photo');
    const photoPlaceholder = document.getElementById('photo-placeholder');
    const photoInput = document.getElementById('profile_photo');
    const clearButton = document.getElementById('clear-photo-preview');

    if (!photoPreview || !previewImage || !currentPhoto || !photoPlaceholder || !photoInput) return;

    function hidePreview() {
      photoPreview.classList.add('hidden');
      currentPhoto.classList.remove('hidden');
      photoPlaceholder.classList.add('hidden');
      photoInput.value = '';
      previewImage.src = '';
    }

    // Handle file input change
    photoInput.addEventListener('change', function(e) {
      const file = e.target.files[0];

      if (!file) {
        hidePreview();
        return;
      }

      // Validate file size (5MB limit)
      if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB.');
        hidePreview();
        return;
      }

      // Show preview
      const reader = new FileReader();
      reader.onload = function(e) {
        previewImage.src = e.target.result;
        photoPreview.classList.remove('hidden');
        currentPhoto.classList.add('hidden');
        photoPlaceholder.classList.add('hidden');
      };
      reader.readAsDataURL(file);
    });

    // Handle clear button
    if (clearButton) {
      clearButton.addEventListener('click', hidePreview);
    }
  }

  // Service Photo preview functionality (for main form)
  function initializeServicePhotoPreview() {
    const servicePhotoPreview = document.getElementById('service-photo-preview');
    const servicePreviewImage = document.getElementById('service-preview-image');
    const currentServicePhoto = document.getElementById('current-service-photo');
    const servicePhotoPlaceholder = document.getElementById('service-photo-placeholder');
    const servicePhotoInput = document.getElementById('service_profile_photo');
    const clearServiceButton = document.getElementById('clear-service-photo');

    // Debug: Check which elements are found
    console.log('Service photo elements found:', {
      servicePhotoPreview: !!servicePhotoPreview,
      servicePreviewImage: !!servicePreviewImage,
      currentServicePhoto: !!currentServicePhoto,
      servicePhotoPlaceholder: !!servicePhotoPlaceholder,
      servicePhotoInput: !!servicePhotoInput,
      clearServiceButton: !!clearServiceButton
    });

    if (!servicePhotoInput) {
      console.log('Service photo input not found, skipping initialization');
      return; // Exit if service photo input doesn't exist
    }

    function hideServicePreview() {
      // Hide preview
      if (servicePhotoPreview) servicePhotoPreview.classList.add('hidden');

      // Clear the file input and preview image
      servicePhotoInput.value = '';
      if (servicePreviewImage) servicePreviewImage.src = '';

      // Show either current photo or placeholder
      if (currentServicePhoto) {
        // If there's a current photo, show it and hide placeholder
        currentServicePhoto.classList.remove('hidden');
        if (servicePhotoPlaceholder) servicePhotoPlaceholder.classList.add('hidden');
      } else {
        // If no current photo, show placeholder
        if (servicePhotoPlaceholder) servicePhotoPlaceholder.classList.remove('hidden');
      }
    }

    // Handle file input change
    servicePhotoInput.addEventListener('change', function(e) {
      const file = e.target.files[0];

      if (!file) {
        hideServicePreview();
        return;
      }

      // Validate file size (5MB limit)
      if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB.');
        hideServicePreview();
        return;
      }

      // Show preview
      const reader = new FileReader();
      reader.onload = function(e) {
        if (servicePreviewImage) {
          servicePreviewImage.src = e.target.result;
          servicePhotoPreview.classList.remove('hidden');
        }

        // Hide other elements when showing preview
        if (currentServicePhoto) currentServicePhoto.classList.add('hidden');
        if (servicePhotoPlaceholder) servicePhotoPlaceholder.classList.add('hidden');
      };
      reader.readAsDataURL(file);
    });

    // Handle clear button
    if (clearServiceButton) {
      clearServiceButton.addEventListener('click', hideServicePreview);
    }
  }

  // Location and Map functionality
  let map, marker;
  window.mapInitialized = false;

  function initializeMap() {
    if (window.mapInitialized) return;

    console.log('Initializing map');
    // Initialize map centered on a default location
    map = L.map('location-map').setView([26.8467, 80.9462], 10); // Lucknow, India default
    window.map = map; // Make map globally accessible

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: ' © OpenStreetMap contributors'
    }).addTo(map);

    // Add click event to map
    map.on('click', function(e) {
      console.log('Map clicked at:', e.latlng);
      setLocation(e.latlng.lat, e.latlng.lng);
      reverseGeocode(e.latlng.lat, e.latlng.lng);
    });

    window.mapInitialized = true;
    console.log('Map initialized successfully');
  }

  function initializeLocationHandlers() {
    console.log('Initializing location handlers');
    const useCurrentLocationBtn = document.getElementById('use-current-location');
    const clearLocationBtn = document.getElementById('clear-location');

    console.log('Location elements found:', {
      useCurrentLocationBtn: !!useCurrentLocationBtn,
      clearLocationBtn: !!clearLocationBtn
    });

    if (!useCurrentLocationBtn || !clearLocationBtn) {
      console.log('Location elements not found, skipping location handlers');
      return;
    }

    // Current location functionality
    const newUseCurrentLocationBtn = useCurrentLocationBtn.cloneNode(true);
    useCurrentLocationBtn.parentNode.replaceChild(newUseCurrentLocationBtn, useCurrentLocationBtn);

    newUseCurrentLocationBtn.addEventListener('click', function(e) {
      e.preventDefault();
      console.log('Current location button clicked');

      if (navigator.geolocation) {
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span class="hidden sm:inline">Getting...</span>';
        this.disabled = true;

        navigator.geolocation.getCurrentPosition(
          function(position) {
            console.log('Got current position:', position.coords);
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            if (!window.mapInitialized) {
              initializeMap();
            }

            if (window.map) {
              map.setView([lat, lng], 15);
              setLocation(lat, lng);
              reverseGeocode(lat, lng);
            }

            // Reset button
            const btn = document.getElementById('use-current-location');
            if (btn) {
              btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> <span class="hidden sm:inline">Use Current Location</span>';
              btn.disabled = false;
            }
          },
          function(error) {
            console.error('Geolocation error:', error);
            alert('Error getting your location: ' + error.message);

            // Reset button
            const btn = document.getElementById('use-current-location');
            if (btn) {
              btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> <span class="hidden sm:inline">Use Current Location</span>';
              btn.disabled = false;
            }
          }, {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 300000
          }
        );
      } else {
        alert('Geolocation is not supported by this browser.');
      }
    });

    // Clear location functionality
    const newClearLocationBtn = clearLocationBtn.cloneNode(true);
    clearLocationBtn.parentNode.replaceChild(newClearLocationBtn, clearLocationBtn);

    newClearLocationBtn.addEventListener('click', function(e) {
      e.preventDefault();
      clearLocationData();
    });

    // Initialize map when location section becomes visible
    const mapContainer = document.getElementById('location-map');
    if (mapContainer) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting && !window.mapInitialized) {
            initializeMap();
          }
        });
      });
      observer.observe(mapContainer);
    }

    console.log('Location handlers initialized successfully');
  }

  function setLocation(lat, lng) {
    try {
      // Validate coordinates
      if (isNaN(lat) || isNaN(lng)) {
        console.error('Invalid coordinates:', lat, lng);
        return;
      }

      // Check if map exists
      if (!window.map) {
        console.error('Map not available in setLocation');
        return;
      }

      // Remove existing marker
      if (window.marker) {
        window.map.removeLayer(window.marker);
      }

      // Add new marker
      window.marker = L.marker([lat, lng]).addTo(window.map);

      // Update hidden inputs
      const latInput = document.getElementById('latitude');
      const lngInput = document.getElementById('longitude');
      const selectedLat = document.getElementById('selected-lat');
      const selectedLng = document.getElementById('selected-lng');
      const selectedLocation = document.getElementById('selected-location');

      if (latInput) latInput.value = lat;
      if (lngInput) lngInput.value = lng;

      // Update selected location display
      if (selectedLat) selectedLat.textContent = lat.toFixed(6);
      if (selectedLng) selectedLng.textContent = lng.toFixed(6);
      if (selectedLocation) selectedLocation.classList.remove('hidden');

      console.log('Location set successfully:', lat, lng);
    } catch (error) {
      console.error('Error in setLocation:', error);
    }
  }

  // Move reverse geocoding to server-side to avoid CORS issues.

  // Reverse Geocoding Function
  async function reverseGeocode(lat, lng) {
    try {
      // Make a server-side request to handle reverse geocoding
      const response = await fetch(`reverse-geocode.php?lat=${lat}&lng=${lng}`);

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('Invalid JSON response from server');
      }

      const data = await response.json();
      console.log('Reverse geocoding result:', data);
      return data;
    } catch (error) {
      console.error('Reverse geocoding error:', error);
      alert('Failed to fetch location details. Please try again later.');
      return null;
    }
  }

  // Map Click Event
  function onMapClick(event) {
    const {
      lat,
      lng
    } = event.latlng;
    console.log('Map clicked at:', lat, lng);

    // Update `currentLocation`
    currentLocation = {
      lat,
      lng
    };
    console.log('Location set successfully:', currentLocation.lat, currentLocation.lng);

    // Attempt reverse geocoding
    reverseGeocode(currentLocation.lat, currentLocation.lng)
      .then((data) => {
        if (data) {
          console.log('Reverse geocoding data:', data);
        }
      });
  }

  // Ensure `currentLocation` is defined globally to avoid ReferenceError.
  let currentLocation = null;

  // Attach Map Click Event Listener
  if (window.map) {
    window.map.on('click', onMapClick);
  }

  // Qualifications Management
  let qualificationCount = 0;

  function initializeQualifications() {
    const addBtn = document.getElementById('add-qualification-btn');
    const container = document.getElementById('qualifications-container');
    const noMessage = document.getElementById('no-qualifications-message');

    if (addBtn) {
      addBtn.addEventListener('click', function() {
        addQualificationEntry();
      });
    }

    // Show/hide no qualifications message
    function updateQualificationsDisplay() {
      const hasQualifications = container.children.length > 0;
      if (noMessage) {
        noMessage.style.display = hasQualifications ? 'none' : 'block';
      }
    }

    updateQualificationsDisplay();
  }

  function addQualificationEntry() {
    qualificationCount++;
    const container = document.getElementById('qualifications-container');
    const noMessage = document.getElementById('no-qualifications-message');

    const qualificationHtml = `
    <div class="qualification-entry bg-white border border-neutral-200 rounded-lg p-6" data-qualification-id="${qualificationCount}">
      <div class="flex items-center justify-between mb-4">
        <h4 class="text-lg font-medium text-neutral-800">
          <i class="fa-solid fa-certificate text-primary-600 mr-2"></i>
          Qualification #${qualificationCount}
        </h4>
        <button type="button" class="remove-qualification text-red-600 hover:text-red-800 p-2" 
                title="Remove this qualification">
          <i class="fa-solid fa-trash"></i>
        </button>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Title -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 mb-2">
            Qualification Title *
          </label>
          <input type="text" 
                 name="qualifications[${qualificationCount}][title]" 
                 id="qualification_title_${qualificationCount}"
                 class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                 placeholder="e.g., Bachelor of Computer Science"
                 required>
        </div>
        
        <!-- Institute -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 mb-2">
            Institute/Organization *
          </label>
          <input type="text" 
                 name="qualifications[${qualificationCount}][institute]" 
                 id="qualification_institute_${qualificationCount}"
                 class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                 placeholder="e.g., University of Technology"
                 required>
        </div>
        
        <!-- Year -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 mb-2">
            Year Obtained
          </label>
          <input type="number" 
                 name="qualifications[${qualificationCount}][year_obtained]" 
                 id="qualification_year_${qualificationCount}"
                 class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                 placeholder="e.g., 2020"
                 min="1900" 
                 max="2030">
        </div>
        
        <!-- Certificate Image -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 mb-2">
            Certificate Image
          </label>
          <div class="certificate-upload-area">
            <input type="file" 
                   name="qualifications_${qualificationCount}_certificate_image" 
                   id="qualification_certificate_${qualificationCount}"
                   class="hidden certificate-file-input"
                   accept="image/*">
            <div class="certificate-upload-button cursor-pointer border-2 border-dashed border-neutral-300 rounded-lg p-4 text-center hover:border-primary-400 transition-colors">
              <i class="fa-solid fa-cloud-upload-alt text-2xl text-neutral-400 mb-2"></i>
              <p class="text-sm text-neutral-600">Click to upload certificate</p>
              <p class="text-xs text-neutral-500">PNG, JPG up to 5MB</p>
            </div>
            <div class="certificate-preview hidden mt-3">
              <div class="relative inline-block">
                <img class="certificate-preview-image w-24 h-24 object-cover rounded border">
                <button type="button" class="remove-certificate absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600">
                  ×
                </button>
              </div>
              <p class="certificate-filename text-xs text-neutral-600 mt-1"></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;

    container.insertAdjacentHTML('beforeend', qualificationHtml);

    // Hide no qualifications message
    if (noMessage) {
      noMessage.style.display = 'none';
    }

    // Add event listeners to the new qualification entry
    const newEntry = container.lastElementChild;
    setupQualificationEvents(newEntry);
  }

  function setupQualificationEvents(entry) {
    // Remove button
    const removeBtn = entry.querySelector('.remove-qualification');
    if (removeBtn) {
      removeBtn.addEventListener('click', function() {
        removeQualificationEntry(entry);
      });
    }

    // File upload
    const fileInput = entry.querySelector('.certificate-file-input');
    const uploadButton = entry.querySelector('.certificate-upload-button');
    const preview = entry.querySelector('.certificate-preview');
    const previewImage = entry.querySelector('.certificate-preview-image');
    const filename = entry.querySelector('.certificate-filename');
    const removeFileBtn = entry.querySelector('.remove-certificate');

    if (uploadButton && fileInput) {
      uploadButton.addEventListener('click', function() {
        fileInput.click();
      });

      fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          // Validate file
          if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            fileInput.value = '';
            return;
          }

          if (!file.type.startsWith('image/')) {
            alert('Please select an image file');
            fileInput.value = '';
            return;
          }

          // Show preview
          const reader = new FileReader();
          reader.onload = function(e) {
            previewImage.src = e.target.result;
            filename.textContent = file.name;
            uploadButton.style.display = 'none';
            preview.classList.remove('hidden');
          };
          reader.readAsDataURL(file);
        }
      });

      if (removeFileBtn) {
        removeFileBtn.addEventListener('click', function() {
          fileInput.value = '';
          uploadButton.style.display = 'block';
          preview.classList.add('hidden');
        });
      }
    }
  }

  function removeQualificationEntry(entry) {
    const container = document.getElementById('qualifications-container');
    const noMessage = document.getElementById('no-qualifications-message');

    entry.remove();

    // Show no qualifications message if no entries left
    if (container.children.length === 0 && noMessage) {
      noMessage.style.display = 'block';
    }

    // Renumber remaining qualifications
    renumberQualifications();
  }

  function renumberQualifications() {
    const entries = document.querySelectorAll('.qualification-entry');
    entries.forEach((entry, index) => {
      const newNumber = index + 1;
      const title = entry.querySelector('h4');
      if (title) {
        title.innerHTML = `<i class="fa-solid fa-certificate text-primary-600 mr-2"></i>Qualification #${newNumber}`;
      }
    });
  }

  // Listen for flash message from PHP (success)
  document.addEventListener('DOMContentLoaded', function() {
    const flashSuccess = document.querySelector('.flash-message.success');
    if (flashSuccess) {
      showAlert(flashSuccess.textContent, 'success');
      // Clear all form fields
      const form = document.getElementById('providerForm');
      if (form) {
        form.reset();
        // If you use custom fields (e.g. select2, custom file inputs), clear them here
        // Example: $("#yourSelect").val(null).trigger('change');
      }
      // Redirect after 2 seconds
      setTimeout(function() {
        window.location.href = 'provider-profile.php';
      }, 2000);
    }
  });

  // Alert function for user feedback
  function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${
    type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
    type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
    'bg-blue-100 text-blue-800 border border-blue-200'
  }`;

    alertDiv.innerHTML = `
    <div class="flex items-center">
      <i class="fa-solid ${
        type === 'success' ? 'fa-check-circle' :
        type === 'error' ? 'fa-exclamation-circle' :
        'fa-info-circle'
      } mr-2"></i>
      <span>${message}</span>
    </div>
  `;

    document.body.appendChild(alertDiv);

    setTimeout(() => {
      alertDiv.remove();
    }, 3000);
  }

  // Ensure all JavaScript blocks are properly closed
</script>

<?php include 'includes/footer.php'; ?>