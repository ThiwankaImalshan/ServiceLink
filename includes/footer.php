<?php
// Safely get current user if auth object exists
$currentUser = null;
if (isset($auth) && is_object($auth) && method_exists($auth, 'getCurrentUser')) {
    $currentUser = $auth->getCurrentUser();
}
?>

  <!-- Footer -->
  <footer class="bg-neutral-900 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <!-- Brand Section -->
        <div class="lg:col-span-2">
          <a href="index.php" class="flex items-center space-x-2 text-xl font-bold text-white mb-4">
            <div class="bg-white p-2 rounded-lg">
              <img src="assets/img/logo.png" alt="ServiceLink Logo" class="h-6 w-6 object-contain">
            </div>
            <span>ServiceLink</span>
          </a>
          <p class="text-neutral-400 mb-6 max-w-md">
            We connect you with trusted service providers in your area. Find the perfect professional for any job, big or small.
          </p>
          <!-- Social Links -->
          <div class="flex space-x-4">
            <a href="#" class="bg-neutral-800 p-3 rounded-lg hover:bg-primary-600 transition-colors" aria-label="Twitter">
              <i class="fa-brands fa-x-twitter"></i>
            </a>
            <a href="#" class="bg-neutral-800 p-3 rounded-lg hover:bg-primary-600 transition-colors" aria-label="Facebook">
              <i class="fa-brands fa-facebook"></i>
            </a>
            <a href="#" class="bg-neutral-800 p-3 rounded-lg hover:bg-primary-600 transition-colors" aria-label="Instagram">
              <i class="fa-brands fa-instagram"></i>
            </a>
          </div>
        </div>
        
        <!-- Explore Links -->
        <div>
          <h4 class="text-lg font-semibold mb-4">Explore</h4>
          <ul class="space-y-3">
            <li><a href="<?php echo BASE_URL; ?>/services.php" class="text-neutral-400 hover:text-white transition-colors">Services</a></li>
            <li><a href="<?php echo BASE_URL; ?>/wanted.php" class="text-neutral-400 hover:text-white transition-colors">Wanted</a></li>
            <?php if ($currentUser && ($currentUser['role'] === 'provider' || $currentUser['role'] === 'admin')): ?>
              <li><a href="<?php echo BASE_URL; ?>/my-service.php" class="text-neutral-400 hover:text-white transition-colors">List Your Service</a></li>
            <?php endif; ?>
            <?php if (!$currentUser): ?>
              <li><a href="<?php echo BASE_URL; ?>/login.php" class="text-neutral-400 hover:text-white transition-colors">Login/Register</a></li>
            <?php endif; ?>
          </ul>
        </div>
        
        <!-- Contact Info -->
        <div>
          <h4 class="text-lg font-semibold mb-4">Contact</h4>
          <ul class="space-y-3">
            <li class="flex items-center space-x-2 text-neutral-400">
              <i class="fa-solid fa-envelope"></i>
              <span>hello@servicelink.example</span>
            </li>
            <li class="flex items-center space-x-2 text-neutral-400">
              <i class="fa-solid fa-clock"></i>
              <span>Mon–Fri: 9:00–18:00</span>
            </li>
          </ul>
          <div class="mt-4 space-y-2">
            <a href="#" class="text-neutral-400 hover:text-white transition-colors block">Privacy Policy</a>
            <a href="#" class="text-neutral-400 hover:text-white transition-colors block">Terms of Service</a>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Footer Bottom -->
    <div class="border-t border-neutral-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col sm:flex-row justify-between items-center">
          <p class="text-neutral-400">
            © <?php echo date('Y'); ?> ServiceLink. All rights reserved.
          </p>
          <div class="flex items-center space-x-4 mt-4 sm:mt-0">
            <span class="text-neutral-400">Made with</span>
            <i class="fa-solid fa-heart text-red-500"></i>
            <span class="text-neutral-400">for professionals</span>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <!-- Mobile nav toggle script -->
  <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>
