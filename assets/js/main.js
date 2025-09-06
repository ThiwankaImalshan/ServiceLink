// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
  // Mobile nav toggle - Fixed implementation
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobile-menu');

  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', (e) => {
      e.preventDefault();
      mobileMenu.classList.toggle('hidden');
      
      // Update hamburger icon
      const icon = hamburger.querySelector('i');
      if (mobileMenu.classList.contains('hidden')) {
        icon.className = 'fa-solid fa-bars text-lg';
      } else {
        icon.className = 'fa-solid fa-times text-lg';
      }
    });

    // Close mobile menu when clicking outside
    document.addEventListener('click', (e) => {
      if (!hamburger.contains(e.target) && !mobileMenu.contains(e.target)) {
        mobileMenu.classList.add('hidden');
        const icon = hamburger.querySelector('i');
        icon.className = 'fa-solid fa-bars text-lg';
      }
    });

    // Close mobile menu when clicking on a link
    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mobileMenu.classList.add('hidden');
        const icon = hamburger.querySelector('i');
        icon.className = 'fa-solid fa-bars text-lg';
      });
    });
  }

  // Footer year
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();
});

// Populate categories on Home and other pages if container exists
function renderCategoriesGrid() {
  const grid = document.getElementById('categoriesGrid');
  if (!grid) return;
  grid.innerHTML = '';
  CATEGORIES.forEach((c, index) => {
    const card = document.createElement('a');
    card.href = `services.html?category=${encodeURIComponent(c.slug)}`;
    card.className = 'group relative block overflow-hidden rounded-2xl transform transition-all duration-500 hover:scale-105 hover:-translate-y-3 cursor-pointer opacity-0 translate-y-8';
    card.style.animationDelay = `${index * 100}ms`;
    card.innerHTML = `
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
              <i class="fa-solid ${c.icon} text-3xl sm:text-4xl bg-gradient-to-br from-primary-600 via-primary-500 to-secondary-600 bg-clip-text text-transparent group-hover:from-primary-700 group-hover:to-secondary-700 transition-all duration-300"></i>
            </div>
            
            <!-- Pulsing Ring Animation -->
            <div class="absolute inset-0 rounded-2xl border-2 border-primary-300/30 group-hover:border-primary-400/50 group-hover:scale-125 opacity-0 group-hover:opacity-100 transition-all duration-500 animate-pulse"></div>
          </div>
          
          <!-- Category Name with Enhanced Typography -->
          <h3 class="font-bold text-lg sm:text-xl text-neutral-800 group-hover:text-primary-700 transition-all duration-300 tracking-tight leading-tight">${c.name}</h3>
          
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
    `;
    grid.appendChild(card);
    
    // Trigger entrance animation
    setTimeout(() => {
      card.classList.remove('opacity-0', 'translate-y-8');
      card.classList.add('opacity-100', 'translate-y-0');
    }, index * 100 + 100);
  });
}
renderCategoriesGrid();

// Simple auth-aware Login label update
(function syncAuth() {
  const current = JSON.parse(localStorage.getItem('currentUser') || 'null');
  if (current) {
    document.querySelectorAll('a[href="login.html"]').forEach(el => {
      el.textContent = `Hi, ${current.name.split(' ')[0]}`;
      el.href = '#';
      el.addEventListener('click', (e) => {
        e.preventDefault();
        if (confirm('Logout?')) {
          localStorage.removeItem('currentUser');
          location.reload();
        }
      }, { once: true });
    });
  }
})();