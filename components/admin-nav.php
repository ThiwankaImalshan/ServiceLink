<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="bg-white border-b border-neutral-200 shadow-sm sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo and Admin Title -->
            <div class="flex items-center">
                <a href="<?php echo BASE_URL; ?>/index.php" class="flex items-center space-x-2 text-xl font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors">
          <!-- <div class="bg-primary-100 dark:bg-primary-900 p-2 rounded-lg shadow-glow"> -->
                    <div class=" rounded-lg shadow-glow">
                    <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="ServiceLink Logo" class="h-6 w-6 object-contain" />
                </div>
                <span>ServiceLink</span>
                </a>
                <span class="ml-4 text-xs sm:text-sm font-semibold text-primary-700 bg-primary-50 px-3 py-1 rounded-full shadow-sm border border-primary-100 hidden sm:inline-flex items-center">
                    <i class="fa-solid fa-user-shield mr-2"></i>
                    Admin Panel
                </span>
            </div>

            <!-- Desktop Navigation Links -->
            <div class="hidden md:flex items-center space-x-1">
                <a href="index.php" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'index.php' ? 'bg-primary-100 text-primary-700' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100'; ?>">
                    <i class="fa-solid fa-dashboard mr-2"></i>Dashboard
                </a>
                
                <a href="users.php" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'users.php' || $current_page === 'edit-user.php' ? 'bg-primary-100 text-primary-700' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100'; ?>">
                    <i class="fa-solid fa-users mr-2"></i>Users
                </a>
                
                <a href="providers.php" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'providers.php' ? 'bg-primary-100 text-primary-700' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100'; ?>">
                    <i class="fa-solid fa-briefcase mr-2"></i>Providers
                </a>
                
                <a href="categories.php" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'categories.php' ? 'bg-primary-100 text-primary-700' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100'; ?>">
                    <i class="fa-solid fa-folder mr-2"></i>Categories
                </a>
                
                <a href="settings.php" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'settings.php' ? 'bg-primary-100 text-primary-700' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100'; ?>">
                    <i class="fa-solid fa-cog mr-2"></i>Settings
                </a>
            </div>

            <!-- Desktop User Menu -->
            <div class="hidden md:flex items-center space-x-4">
                <!-- View Site Link -->
                <a href="<?php echo BASE_URL; ?>/index.php" 
                   class="text-neutral-600 hover:text-neutral-900 text-sm"
                   target="_blank">
                    <i class="fa-solid fa-external-link-alt mr-1"></i>View Site
                </a>
                
                <!-- User Info -->
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-user text-primary-600 text-sm"></i>
                    </div>
                    <div class="hidden lg:block">
                        <div class="text-sm font-medium text-neutral-900">
                            <?php 
                            $currentUser = $auth->getCurrentUser();
                            echo $currentUser ? e($currentUser['first_name'] . ' ' . $currentUser['last_name']) : 'Admin';
                            ?>
                        </div>
                        <div class="text-xs text-neutral-500">Administrator</div>
                    </div>
                </div>
                
                <!-- Logout -->
                <a href="<?php echo BASE_URL; ?>/logout.php" 
                   class="text-neutral-600 hover:text-red-600 transition-colors"
                   onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fa-solid fa-sign-out-alt"></i>
                </a>
            </div>

            <!-- Mobile User Menu with Hamburger Button -->
            <div class="md:hidden flex items-center space-x-3">
                <a href="<?php echo BASE_URL; ?>/index.php" 
                   class="text-neutral-600 hover:text-neutral-900"
                   target="_blank"
                   title="View Site">
                    <i class="fa-solid fa-external-link-alt"></i>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/logout.php" 
                   class="text-neutral-600 hover:text-red-600 transition-colors"
                   onclick="return confirm('Are you sure you want to logout?')"
                   title="Logout">
                    <i class="fa-solid fa-sign-out-alt"></i>
                </a>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="text-neutral-600 hover:text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500 p-2 ml-2">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation Menu (Hidden by default) -->
    <div id="mobile-menu" class="md:hidden border-t border-neutral-200 bg-white hidden">
        <div class="px-4 py-2 space-y-1">
            <!-- Admin Panel Badge for Mobile -->
            <div class="px-3 py-2 text-xs text-neutral-500 bg-neutral-50 rounded-md mb-2">
                <i class="fa-solid fa-user-shield mr-2"></i>Admin Panel
            </div>
            
            <a href="index.php" 
               class="block px-3 py-2 rounded-md text-sm font-medium <?php echo $current_page === 'index.php' ? 'bg-primary-100 text-primary-700' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100'; ?>">
                <i class="fa-solid fa-dashboard mr-3 w-4"></i>Dashboard
            </a>
            
            <a href="users.php" 
               class="block px-3 py-2 rounded-md text-sm font-medium <?php echo $current_page === 'users.php' || $current_page === 'edit-user.php' ? 'bg-primary-100 text-primary-700' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100'; ?>">
                <i class="fa-solid fa-users mr-3 w-4"></i>Users
            </a>
            
            <a href="providers.php" 
               class="block px-3 py-2 rounded-md text-sm font-medium <?php echo $current_page === 'providers.php' ? 'bg-primary-100 text-primary-700' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100'; ?>">
                <i class="fa-solid fa-briefcase mr-3 w-4"></i>Providers
            </a>
            
            <a href="categories.php" 
               class="block px-3 py-2 rounded-md text-sm font-medium <?php echo $current_page === 'categories.php' ? 'bg-primary-100 text-primary-700' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100'; ?>">
                <i class="fa-solid fa-folder mr-3 w-4"></i>Categories
            </a>
            
            <a href="settings.php" 
               class="block px-3 py-2 rounded-md text-sm font-medium <?php echo $current_page === 'settings.php' ? 'bg-primary-100 text-primary-700' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100'; ?>">
                <i class="fa-solid fa-cog mr-3 w-4"></i>Settings
            </a>
            
            <!-- Mobile User Info -->
            <div class="border-t border-neutral-200 pt-3 mt-3">
                <div class="px-3 py-2 text-sm">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                            <i class="fa-solid fa-user text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-neutral-900">
                                <?php 
                                $currentUser = $auth->getCurrentUser();
                                echo $currentUser ? e($currentUser['first_name'] . ' ' . $currentUser['last_name']) : 'Admin';
                                ?>
                            </div>
                            <div class="text-xs text-neutral-500">Administrator</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
            
            // Toggle icon
            const icon = mobileMenuBtn.querySelector('i');
            if (mobileMenu.classList.contains('hidden')) {
                icon.className = 'fa-solid fa-bars text-lg';
            } else {
                icon.className = 'fa-solid fa-times text-lg';
            }
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!mobileMenuBtn.contains(event.target) && !mobileMenu.contains(event.target)) {
                mobileMenu.classList.add('hidden');
                const icon = mobileMenuBtn.querySelector('i');
                icon.className = 'fa-solid fa-bars text-lg';
            }
        });
        
        // Close mobile menu when resizing to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) { // md breakpoint
                mobileMenu.classList.add('hidden');
                const icon = mobileMenuBtn.querySelector('i');
                icon.className = 'fa-solid fa-bars text-lg';
            }
        });
    }
});
</script>
