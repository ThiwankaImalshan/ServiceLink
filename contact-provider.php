<?php
session_start();
ob_start();

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/email.php';
require_once 'includes/functions.php';

// Initialize database and auth
try {
    $db = getDB();
    $currentUser = $auth->getCurrentUser();
} catch (Exception $e) {
    error_log("Error initializing contact-provider.php: " . $e->getMessage());
    setFlashMessage('System error. Please try again later.', 'error');
    redirect(BASE_URL . '/services.php');
}

$pageTitle = 'Contact Provider • ServiceLink';
$pageDescription = 'Send a message to connect with this service provider.';

// Get provider ID from URL
$providerId = (int)($_GET['id'] ?? 0);

if (!$providerId) {
    setFlashMessage('Provider not found.', 'error');
    redirect(BASE_URL . '/services.php');
}

// Get provider details
try {
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email, u.phone, u.profile_photo,
               c.name as category_name, c.icon as category_icon
        FROM providers p 
        JOIN users u ON p.user_id = u.id 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ? AND p.is_active = 1
    ");
    $stmt->execute([$providerId]);
    $provider = $stmt->fetch();
    
    if (!$provider) {
        setFlashMessage('Provider not found or not available.', 'error');
        redirect(BASE_URL . '/services.php');
    }
} catch (PDOException $e) {
    error_log("Database error in contact-provider.php: " . $e->getMessage());
    setFlashMessage('An error occurred while loading the provider.', 'error');
    redirect(BASE_URL . '/services.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        setFlashMessage('Invalid request. Please try again.', 'error');
        redirect($_SERVER['REQUEST_URI']);
    }
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $contactMethod = $_POST['contact_method'] ?? 'email';
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($subject)) {
        $errors[] = 'Subject is required.';
    }
    
    if (empty($message)) {
        $errors[] = 'Message is required.';
    }
    
    if ($contactMethod === 'phone' || $contactMethod === 'both') {
        if (empty($phone)) {
            $errors[] = 'Phone number is required for phone contact.';
        }
    }
    
    // If no errors, save the message and send email
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Get or create sender user ID
            $senderId = null;
            if ($currentUser) {
                $senderId = $currentUser['id'];
            } else {
                // For guest users, we'll store a placeholder sender_id of 0
                $senderId = 0;
            }
            
            // Insert message into database
            $stmt = $db->prepare("
                INSERT INTO messages (sender_id, recipient_id, provider_id, subject, message, contact_method, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $fullMessage = "Name: {$name}\n";
            $fullMessage .= "Email: {$email}\n";
            if (!empty($phone)) {
                $fullMessage .= "Phone: {$phone}\n";
            }
            $fullMessage .= "Preferred Contact: " . ucfirst($contactMethod) . "\n\n";
            $fullMessage .= "Message:\n{$message}";
            
            $stmt->execute([
                $senderId,
                $provider['user_id'],
                $providerId,
                $subject,
                $fullMessage,
                $contactMethod
            ]);
            
            // Send email to provider
            $providerFullName = $provider['first_name'] . ' ' . $provider['last_name'];
            $emailResult = sendContactEmail(
                $provider['email'],
                $providerFullName,
                $name,
                $email,
                $phone,
                $subject,
                $message,
                $contactMethod
            );
            
            $db->commit();
            
            if ($emailResult['success']) {
                setFlashMessage('Your message has been sent successfully! The provider has been notified via email and will contact you soon.', 'success');
            } else {
                setFlashMessage('Your message has been saved, but there was an issue sending the email notification. The provider will still see your message.', 'warning');
                error_log("Email sending failed: " . $emailResult['message']);
            }
            
            // Redirect back to provider profile
            redirect(BASE_URL . '/provider-profile.php?id=' . $providerId);
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Error saving message in contact-provider.php: " . $e->getMessage());
            setFlashMessage('An error occurred while sending your message. Please try again.', 'error');
        }
    } else {
        // Store form data to repopulate
        $_SESSION['contact_form_data'] = $_POST;
        foreach ($errors as $error) {
            setFlashMessage($error, 'error');
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get stored form data if any
$formData = $_SESSION['contact_form_data'] ?? [];
unset($_SESSION['contact_form_data']);

include 'includes/header.php';
?>

<!-- Hero Section -->
<div class="bg-gradient-to-r from-primary-600 to-secondary-600 py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-4">Contact Provider</h1>
            <p class="text-lg text-primary-100 max-w-2xl mx-auto">Send a message to connect with a professional service provider. Your inquiry will be delivered securely and promptly.</p>
        </div>
    </div>
</div>
<div class="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-50">
    <div class="min-h-screen flex flex-col lg:flex-row max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Provider Info Sidebar -->
        <div class="w-full lg:w-1/3 flex-shrink-0 mb-8 lg:mb-0 lg:mr-8">
            <div class="bg-white rounded-2xl shadow-xl border border-neutral-100 overflow-hidden sticky top-8">
                    <!-- Provider Header -->
                    <div class="bg-gradient-to-br from-primary-600 to-secondary-600 px-8 py-10 text-white text-center">
                        <div class="relative inline-block mb-4" style="width: 8rem; height: 8rem;">
                          <?php if ($provider['profile_photo']): ?>
                            <?php
                            // Prefer provider's photo; fallback to user’s photo; else default
                            $photoPath = $provider['provider_profile_photo'] ?? $provider['profile_photo'] ?? '';
                            if (!$photoPath) {
                              $imgSrc = 'assets/img/default-avatar.svg';
                            } elseif (preg_match('~^https?://~i', $photoPath)) {
                              $imgSrc = $photoPath;
                            } else {
                              $normalized = ltrim(str_replace('\\', '/', $photoPath), '/');
                              $imgSrc = BASE_URL . '/serve-upload.php?p=' . rawurlencode($normalized);
                            }
                            ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                               alt="<?= e($provider['first_name'] . ' ' . $provider['last_name']) ?>"
                               class="w-32 h-32 rounded-full border-4 border-white shadow-lg mx-auto object-cover">
                          <?php else: ?>
                            <div class="w-32 h-32 rounded-full border-4 border-white shadow-lg mx-auto bg-primary-400 flex items-center justify-center text-2xl font-bold">
                              <?= strtoupper(substr($provider['first_name'], 0, 1) . substr($provider['last_name'], 0, 1)) ?>
                            </div>
                          <?php endif; ?>
                          <span class="absolute bottom-2 right-2 bg-green-500 w-6 h-6 rounded-full border-2 border-white flex items-center justify-center"></span>
                        </div>
                        
                        <h2 class="text-2xl font-bold mb-2"><?= e($provider['first_name'] . ' ' . $provider['last_name']) ?></h2>
                        
                        <?php if ($provider['business_name']): ?>
                            <p class="text-primary-100 mb-3"><?= e($provider['business_name']) ?></p>
                        <?php endif; ?>
                        
                        <div class="flex items-center justify-center mb-2">
                            <?php if ($provider['category_icon']): ?>
                                <i class="<?= e($provider['category_icon']) ?> mr-2"></i>
                            <?php endif; ?>
                            <span class="bg-white/20 px-3 py-1 rounded-full text-sm font-medium">
                                <?= e($provider['category_name']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Provider Details -->
                    <div class="p-8 space-y-6">
                        <?php if ($provider['location']): ?>
                            <div class="flex items-center text-neutral-600 dark:text-neutral-300">
                                <i class="fas fa-map-marker-alt w-5 h-5 mr-3 text-primary-500"></i>
                                <span class="text-sm"><?= e($provider['location']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($provider['hourly_rate']): ?>
                            <div class="flex items-center text-neutral-600 dark:text-neutral-300">
                                <i class="fas fa-coins w-5 h-5 mr-3 text-primary-500"></i>
                                <span class="text-sm font-semibold text-green-600 dark:text-green-400">
                                    Rs.<?= number_format($provider['hourly_rate'], 2) ?>/hour
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($provider['email']): ?>
                            <div class="flex items-center text-neutral-600 dark:text-neutral-300">
                                <i class="fas fa-envelope w-5 h-5 mr-3 text-primary-500"></i>
                                <span class="text-sm"><?= e($provider['email']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($provider['phone']): ?>
                            <div class="flex items-center text-neutral-600 dark:text-neutral-300">
                                <i class="fas fa-phone w-5 h-5 mr-3 text-primary-500"></i>
                                <span class="text-sm"><?= e($provider['phone']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="pt-4 border-t border-neutral-200 dark:border-neutral-600">
                            <div class="flex items-center text-neutral-500 dark:text-neutral-400 text-sm">
                                <i class="fas fa-clock w-4 h-4 mr-2"></i>
                                Usually responds within 24 hours
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <a href="<?= BASE_URL ?>/provider-profile.php?id=<?= $provider['id'] ?>" 
                               class="w-full bg-neutral-50 hover:bg-neutral-100 text-neutral-700 font-medium py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center text-sm border border-neutral-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
        <!-- Contact Form and Tips Card -->
        <div class="w-full lg:w-2/3 flex flex-col items-center justify-center">
            <div class="max-w-lg w-full bg-white rounded-2xl shadow-xl border border-neutral-100 overflow-hidden mb-8">
                    <!-- Form Header -->
                    <div class="px-8 pt-8 pb-4 text-center">
                        <h2 class="text-2xl font-bold text-neutral-900 mb-1">Contact Provider</h2>
                        <p class="text-neutral-600 text-sm">Send a message to connect with this professional</p>
                    </div>
                    
                    <!-- Form Content -->
                    <div class="p-8">
                        <!-- Flash Messages -->
                        <?php
                        $successMessage = getFlashMessage('success');
                        $warningMessage = getFlashMessage('warning');
                        $errorMessages = getFlashMessage('error');
                        
                        if ($successMessage): ?>
                            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4 flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-0.5 mr-3"></i>
                                <div class="flex-1">
                                    <p class="text-green-800 font-medium"><?= e($successMessage) ?></p>
                                </div>
                                <button onclick="this.parentElement.remove()" class="text-green-500 hover:text-green-700 ml-2">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endif;
                        
                        if ($warningMessage): ?>
                            <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5 mr-3"></i>
                                <div class="flex-1">
                                    <p class="text-yellow-800 font-medium"><?= e($warningMessage) ?></p>
                                </div>
                                <button onclick="this.parentElement.remove()" class="text-yellow-500 hover:text-yellow-700 ml-2">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endif;
                        
                        if ($errorMessages): 
                            if (is_array($errorMessages)): ?>
                                <?php foreach ($errorMessages as $error): ?>
                                    <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4 flex items-start">
                                        <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-3"></i>
                                        <div class="flex-1">
                                            <p class="text-red-800 font-medium"><?= e($error) ?></p>
                                        </div>
                                        <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700 ml-2">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4 flex items-start">
                                    <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-3"></i>
                                    <div class="flex-1">
                                        <p class="text-red-800 font-medium"><?= e($errorMessages) ?></p>
                                    </div>
                                    <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700 ml-2">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endif;
                        endif; ?>
                        
                        <form method="POST" action="" class="space-y-6" id="contactForm">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <!-- Name and Email Row -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label for="name" class="block text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                                        Your Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="name" name="name" required
                                           value="<?= e($formData['name'] ?? ($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?>" 
                                           class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                           placeholder="Enter your full name">
                                </div>
                                
                                <div class="space-y-2">
                                    <label for="email" class="block text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                                        Your Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" id="email" name="email" required
                                           value="<?= e($formData['email'] ?? ($currentUser['email'] ?? '')) ?>" 
                                           class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                           placeholder="your.email@example.com">
                                </div>
                            </div>
                            
                            <!-- Phone and Subject Row -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label for="phone" class="block text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                                        Your Phone Number <span id="phone-required" class="text-red-500 hidden">*</span>
                                    </label>
                                    <input type="tel" id="phone" name="phone"
                                           value="<?= e($formData['phone'] ?? ($currentUser['phone'] ?? '')) ?>" 
                                           class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                           placeholder="+1 (555) 123-4567">
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Optional, but helpful for quick contact</p>
                                </div>
                                
                                <div class="space-y-2">
                                    <label for="subject" class="block text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                                        Subject <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="subject" name="subject" required
                                           value="<?= e($formData['subject'] ?? '') ?>" 
                                           class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                           placeholder="Brief description of your service request">
                                </div>
                            </div>
                            
                            <!-- Message -->
                            <div class="space-y-2">
                                <label for="message" class="block text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                                    Message <span class="text-red-500">*</span>
                                </label>
                                <textarea id="message" name="message" rows="6" required
                                          class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                          placeholder="Describe your project or service needs in detail..."><?= e($formData['message'] ?? '') ?></textarea>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Be specific about your requirements, timeline, and budget if applicable</p>
                            </div>
                            
                            <!-- Contact Method -->
                            <div class="space-y-3">
                                <label class="block text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                                    Preferred Contact Method
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <label class="relative flex items-center p-4 border border-neutral-300 rounded-lg cursor-pointer hover:bg-neutral-50 transition-colors duration-200">
                                        <input type="radio" name="contact_method" value="email" 
                                               <?= ($formData['contact_method'] ?? 'email') === 'email' ? 'checked' : '' ?>
                                               class="sr-only">
                                        <div class="contact-method-option flex-1 text-center">
                                            <i class="fas fa-envelope text-primary-500 text-xl mb-2"></i>
                                            <div class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Email</div>
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400">Most reliable</div>
                                        </div>
                                    </label>
                                    
                                    <label class="relative flex items-center p-4 border border-neutral-300 rounded-lg cursor-pointer hover:bg-neutral-50 transition-colors duration-200">
                                        <input type="radio" name="contact_method" value="phone" 
                                               <?= ($formData['contact_method'] ?? '') === 'phone' ? 'checked' : '' ?>
                                               class="sr-only">
                                        <div class="contact-method-option flex-1 text-center">
                                            <i class="fas fa-phone text-green-500 text-xl mb-2"></i>
                                            <div class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Phone</div>
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400">Quick response</div>
                                        </div>
                                    </label>
                                    
                                    <label class="relative flex items-center p-4 border border-neutral-300 rounded-lg cursor-pointer hover:bg-neutral-50 transition-colors duration-200">
                                        <input type="radio" name="contact_method" value="both" 
                                               <?= ($formData['contact_method'] ?? '') === 'both' ? 'checked' : '' ?>
                                               class="sr-only">
                                        <div class="contact-method-option flex-1 text-center">
                                            <i class="fas fa-comments text-secondary-500 text-xl mb-2"></i>
                                            <div class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Either</div>
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400">Most flexible</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="flex flex-col sm:flex-row gap-4 pt-6">
                                <button type="submit" 
                                        class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-3 px-4 rounded-lg transition-colors shadow-lg hover:shadow-glow flex items-center justify-center">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    <span id="submit-text">Send Message</span>
                                    <div id="submit-spinner" class="hidden ml-2">
                                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                                    </div>
                                </button>
                                <a href="<?= BASE_URL ?>/provider-profile.php?id=<?= $provider['id'] ?>" 
                                   class="w-full bg-neutral-50 hover:bg-neutral-100 text-neutral-700 font-medium py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center border border-neutral-200">
                                    <i class="fas fa-times mr-2"></i>
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
            <!-- Tips Card -->
            <div class="max-w-lg w-full bg-gradient-to-r from-yellow-50 to-yellow-100 border border-yellow-200 rounded-2xl p-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-lightbulb text-amber-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-amber-800 dark:text-amber-300 mb-3">
                                💡 Tips for Better Communication
                            </h3>
                            <ul class="space-y-2 text-sm text-amber-700 dark:text-amber-200">
                                <li class="flex items-start">
                                    <i class="fas fa-check text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Be clear and specific about your project requirements</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Mention your budget range and timeline if applicable</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Ask specific questions about their experience and approach</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Provide context about your project's urgency and scope</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS and JavaScript -->
<style>
    /* Contact method radio button styling */
    input[type="radio"]:checked + .contact-method-option {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
    }
    
    input[type="radio"]:checked + .contact-method-option::after {
        content: '';
        position: absolute;
        top: 8px;
        right: 8px;
        width: 20px;
        height: 20px;
        background: #3b82f6;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    input[type="radio"]:checked + .contact-method-option::before {
        content: '✓';
        position: absolute;
        top: 8px;
        right: 8px;
        width: 20px;
        height: 20px;
        color: white;
        font-size: 12px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
    }
    
    /* Animation classes */
    .animate-fadeInUp {
        animation: fadeInUp 0.6s ease-out forwards;
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
    
    /* Form focus effects */
    input:focus, textarea:focus, select:focus {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    /* Button hover effects */
    button:hover {
        transform: translateY(-1px);
    }
    
    /* Loading state */
    .form-loading {
        pointer-events: none;
        opacity: 0.7;
    }
</style>

<script>
// Enhanced form handling
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contactForm');
    const phoneInput = document.getElementById('phone');
    const phoneRequired = document.getElementById('phone-required');
    const contactMethods = document.getElementsByName('contact_method');
    const submitButton = form.querySelector('button[type="submit"]');
    const submitText = document.getElementById('submit-text');
    const submitSpinner = document.getElementById('submit-spinner');
    
    // Update phone requirement based on contact method
    function updatePhoneRequirement() {
        const selectedMethod = document.querySelector('input[name="contact_method"]:checked').value;
        if (selectedMethod === 'phone' || selectedMethod === 'both') {
            phoneInput.required = true;
            phoneRequired.classList.remove('hidden');
        } else {
            phoneInput.required = false;
            phoneRequired.classList.add('hidden');
        }
    }
    
    // Add event listeners to contact method radios
    contactMethods.forEach(radio => {
        radio.addEventListener('change', updatePhoneRequirement);
    });
    
    // Initial check
    updatePhoneRequirement();
    
    // Form submission handling
    form.addEventListener('submit', function(e) {
        // Show loading state
        submitButton.classList.add('form-loading');
        submitText.textContent = 'Sending...';
        submitSpinner.classList.remove('hidden');
        
        // Basic validation
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('border-red-500');
                field.focus();
            } else {
                field.classList.remove('border-red-500');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            submitButton.classList.remove('form-loading');
            submitText.textContent = 'Send Message';
            submitSpinner.classList.add('hidden');
            return false;
        }
    });
    
    // Auto-resize textarea
    const messageTextarea = document.getElementById('message');
    messageTextarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
    
    // Enhanced form validation
    const inputs = form.querySelectorAll('input, textarea');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.required && !this.value.trim()) {
                this.classList.add('border-red-500');
            } else {
                this.classList.remove('border-red-500');
            }
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('border-red-500') && this.value.trim()) {
                this.classList.remove('border-red-500');
            }
        });
    });
    
    // Auto-dismiss flash messages after 5 seconds
    setTimeout(() => {
        const flashMessages = document.querySelectorAll('[class*="bg-green-50"], [class*="bg-yellow-50"], [class*="bg-red-50"]');
        flashMessages.forEach(message => {
            if (message.querySelector('button')) {
                message.style.transition = 'opacity 0.5s ease-out';
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 500);
            }
        });
    }, 5000);
});
</script>

<?php include 'includes/footer.php'; ?>
