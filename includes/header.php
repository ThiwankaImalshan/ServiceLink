<?php
// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/functions.php';

// Get database connection
$db = getDB();

// Get current user if logged in
$currentUser = $auth->getCurrentUser();
$currentPage = getCurrentPage();
?><!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo e($pageTitle ?? 'ServiceLink'); ?></title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="../assets/img/favicon_io/site.webmanifest">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              50: '#eff6ff',
              100: '#dbeafe',
              200: '#bfdbfe',
              300: '#93c5fd',
              400: '#60a5fa',
              500: '#3b82f6',
              600: '#2563eb',
              700: '#1d4ed8',
              800: '#1e40af',
              900: '#1e3a8a'
            },
            secondary: {
              50: '#f0fdfa',
              100: '#ccfbf1',
              200: '#99f6e4',
              300: '#5eead4',
              400: '#2dd4bf',
              500: '#14b8a6',
              600: '#0d9488',
              700: '#0f766e',
              800: '#115e59',
              900: '#134e4a'
            },
            neutral: {
              50: '#fafafa',
              100: '#f5f5f5',
              200: '#e5e5e5',
              300: '#d4d4d4',
              400: '#a3a3a3',
              500: '#737373',
              600: '#525252',
              700: '#404040',
              800: '#262626',
              900: '#171717'
            }
          },
          fontFamily: {
            sans: ['Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Arial', 'sans-serif']
          },
          boxShadow: {
            'glow': '0 0 20px rgba(59, 130, 246, 0.3)',
            'glow-secondary': '0 0 20px rgba(20, 184, 166, 0.3)',
          }
        }
      }
    }
  </script>
  <style>
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
    
    @keyframes slideInLeft {
      from {
        opacity: 0;
        transform: translateX(-30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    .animate-fadeInUp {
      animation: fadeInUp 0.6s ease-out forwards;
    }
    
    .animate-slideInLeft {
      animation: slideInLeft 0.6s ease-out forwards;
    }
    
    .line-clamp-1 {
      display: -webkit-box;
      -webkit-line-clamp: 1;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    
    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    
    .glass-effect {
      backdrop-filter: blur(10px);
      background: rgba(255, 255, 255, 0.1);
    }
    
    .hover-glow:hover {
      box-shadow: 0 0 30px rgba(59, 130, 246, 0.4);
    }
  </style>
  <link rel="icon" href="<?php echo BASE_URL; ?>/assets/img/favicon.ico" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <!-- Font Awesome fallback -->
  <script>
    if (!window.FontAwesome) {
      document.write('<link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.6.0/css/all.css" crossorigin="anonymous">');
    }
    
    // Check if Font Awesome is loaded after page load
    window.addEventListener('load', function() {
      setTimeout(function() {
        var testIcon = document.createElement('i');
        testIcon.className = 'fa-solid fa-heart';
        testIcon.style.position = 'absolute';
        testIcon.style.left = '-9999px';
        document.body.appendChild(testIcon);
        
        var computedStyle = window.getComputedStyle(testIcon, ':before');
        var isLoaded = computedStyle.content && computedStyle.content !== 'none';
        
        if (!isLoaded) {
          console.warn('Font Awesome may not be loaded properly');
          // Try loading backup CDN
          var link = document.createElement('link');
          link.rel = 'stylesheet';
          link.href = 'https://kit.fontawesome.com/css/all.css';
          document.head.appendChild(link);
        }
        
        document.body.removeChild(testIcon);
      }, 100);
    });
  </script>
  <meta name="description" content="<?php echo e($pageDescription ?? 'Find and hire reliable local service providers: home services, education & training, vehicle repair, tech support, and more.'); ?>" />
</head>
<body class="min-h-screen bg-gradient-to-br from-neutral-50 to-neutral-100 dark:from-neutral-900 dark:to-neutral-800 text-neutral-900 dark:text-neutral-100 transition-colors duration-300">
  
  <!-- Flash Messages -->
  <?php 
  $successMessage = getFlashMessage('success');
  $errorMessage = getFlashMessage('error');
  if ($successMessage || $errorMessage): 
  ?>
  <div id="flash-message" class="fixed top-4 right-4 z-50 max-w-md">
    <?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 shadow-lg">
      <span class="block sm:inline"><?php echo e($successMessage); ?></span>
      <button onclick="this.parentElement.remove()" class="float-right text-green-700 hover:text-green-900">
        <i class="fa-solid fa-times"></i>
      </button>
    </div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 shadow-lg">
      <span class="block sm:inline"><?php echo e($errorMessage); ?></span>
      <button onclick="this.parentElement.remove()" class="float-right text-red-700 hover:text-red-900">
        <i class="fa-solid fa-times"></i>
      </button>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Header / Navigation -->
  <header class="sticky top-0 z-50 bg-white/80 dark:bg-neutral-900/80 backdrop-blur-md border-b border-neutral-200 dark:border-neutral-700 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        <!-- Logo -->
        <a href="<?php echo BASE_URL; ?>/index.php" class="flex items-center space-x-2 text-xl font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors">
          <!-- <div class="bg-primary-100 dark:bg-primary-900 p-2 rounded-lg shadow-glow"> -->
            <div class="p-2 rounded-lg shadow-glow">
            <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="ServiceLink Logo" class="h-6 w-6 object-contain" />
          </div>
          <span>ServiceLink</span>
        </a>
        
        <!-- Desktop Navigation -->
        <nav class="hidden md:flex items-center space-x-8">
          <a href="<?php echo BASE_URL; ?>/services.php" class="text-neutral-600 dark:text-neutral-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition-colors <?php echo isCurrentPage('services') ? 'text-primary-600' : ''; ?>">Services</a>
          <a href="<?php echo BASE_URL; ?>/wanted.php" class="text-neutral-600 dark:text-neutral-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition-colors <?php echo isCurrentPage('wanted') ? 'text-primary-600' : ''; ?>">Wanted</a>
          
          <?php if ($currentUser): ?>
            <?php if ($currentUser['role'] === 'provider'): ?>
              <a href="<?php echo BASE_URL; ?>/my-service.php" class="text-neutral-600 hover:text-primary-600 font-medium transition-colors <?php echo isCurrentPage('my-service') ? 'text-primary-600' : ''; ?>">My Service</a>
            <?php endif; ?>
            
            <!-- User Dropdown -->
            <div class="relative">
              <button id="user-menu-button" class="flex items-center space-x-2 text-neutral-600 hover:text-primary-600 font-medium transition-colors">
                <i class="fa-solid fa-user"></i>
                <span><?php echo e($currentUser['first_name']); ?></span>
                <i class="fa-solid fa-chevron-down text-xs"></i>
              </button>
              
              <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-neutral-200">
                <a href="<?php echo BASE_URL; ?>/profile.php" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">Profile</a>
                <?php if ($currentUser['role'] === 'admin'): ?>
                  <a href="<?php echo BASE_URL; ?>/admin/" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">Admin Panel</a>
                <?php endif; ?>
                <hr class="my-1">
                <a href="<?php echo BASE_URL; ?>/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">Logout</a>
              </div>
            </div>
          <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/login.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-all duration-300 font-medium shadow-lg hover:shadow-glow">Login</a>
          <?php endif; ?>
        </nav>
        
        <!-- Mobile menu button -->
        <button class="md:hidden p-2 text-neutral-600 hover:text-primary-600 transition-colors bg-neutral-100 rounded-lg hover:bg-neutral-200" id="hamburger" aria-label="Open menu">
          <i class="fa-solid fa-bars text-lg"></i>
        </button>
      </div>
    </div>
    
    <!-- Mobile Navigation Menu -->
    <div class="md:hidden hidden bg-white border-t border-neutral-200 shadow-lg" id="mobile-menu">
      <div class="px-4 py-4 space-y-3">
        <a href="<?php echo BASE_URL; ?>/services.php" class="block text-neutral-600 hover:text-primary-600 font-medium transition-colors py-2">Services</a>
        <a href="<?php echo BASE_URL; ?>/wanted.php" class="block text-neutral-600 hover:text-primary-600 font-medium transition-colors py-2">Wanted</a>
        
        <?php if ($currentUser): ?>
          <?php if ($currentUser['role'] === 'provider'): ?>
            <a href="<?php echo BASE_URL; ?>/my-service.php" class="block text-neutral-600 hover:text-primary-600 font-medium transition-colors py-2">My Service</a>
          <?php endif; ?>
          <a href="<?php echo BASE_URL; ?>/profile.php" class="block text-neutral-600 hover:text-primary-600 font-medium transition-colors py-2">Profile</a>
          <?php if ($currentUser['role'] === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>/admin/" class="block text-neutral-600 hover:text-primary-600 font-medium transition-colors py-2">Admin Panel</a>
          <?php endif; ?>
          <a href="<?php echo BASE_URL; ?>/logout.php" class="block bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors font-medium text-center shadow-lg">Logout</a>
        <?php else: ?>
          <a href="<?php echo BASE_URL; ?>/login.php" class="block bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors font-medium text-center shadow-lg">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <script>
    // Auto-hide flash messages
    setTimeout(() => {
      const flashMessage = document.getElementById('flash-message');
      if (flashMessage) {
        flashMessage.style.opacity = '0';
        setTimeout(() => flashMessage.remove(), 300);
      }
    }, 5000);

    // User menu dropdown
    document.addEventListener('DOMContentLoaded', function() {
      const userMenuButton = document.getElementById('user-menu-button');
      const userMenu = document.getElementById('user-menu');
      
      if (userMenuButton && userMenu) {
        userMenuButton.addEventListener('click', function(e) {
          e.preventDefault();
          userMenu.classList.toggle('hidden');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
          if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
            userMenu.classList.add('hidden');
          }
        });
      }
    });
  </script>
