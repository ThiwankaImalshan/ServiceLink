// Build Providers list + filters + Google Map

let map, mobileMap, markers = [], mobileMarkers = [];
let providers = [];
const listEl = document.getElementById('providersList');

function getAllProviders() {
  const saved = JSON.parse(localStorage.getItem('providers') || '[]');
  return saved;
}

function providerIcon(category) {
  const cat = CATEGORIES.find(c => c.slug === category);
  return `<i class="fa-solid ${cat ? cat.icon : 'fa-briefcase'}"></i>`;
}

function ratingStars(rating) {
  const r = Math.round(rating || 0);
  return Array.from({length:5}, (_,i)=> i<r ? '★' : '☆').join('');
}

function renderProviders(items) {
  listEl.innerHTML = '';
  if (!items.length) {
    listEl.innerHTML = `
      <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-lg border border-neutral-200 dark:border-neutral-700 p-8 text-center">
        <i class="fa-solid fa-search text-4xl text-neutral-400 dark:text-neutral-500 mb-4"></i>
        <h3 class="text-lg font-semibold text-neutral-700 dark:text-neutral-300 mb-2">No providers found</h3>
        <p class="text-neutral-500 dark:text-neutral-400">Try adjusting your filters to see more results</p>
      </div>
    `;
    return;
  }
  items.forEach(p => {
    const cardDiv = document.createElement('div');
    cardDiv.className = 'provider-card group relative bg-white dark:bg-neutral-800 rounded-xl sm:rounded-2xl shadow-lg sm:shadow-xl border border-neutral-200/60 dark:border-neutral-700/60 active:shadow-2xl dark:active:shadow-neutral-900/50 active:border-primary-300 dark:active:border-primary-500 transition-all duration-300 overflow-hidden cursor-pointer';
    cardDiv.setAttribute('data-provider-id', p.id);
    
    // Generate status information
    const statusColor = p.active ? 'green' : 'red';
    const statusIcon = p.active ? 'fa-check' : 'fa-times';
    const statusText = p.active ? 'Available Now' : 'Currently Busy';
    const statusBadgeClasses = p.active 
      ? 'bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900/40 dark:to-emerald-900/40 text-green-700 dark:text-green-300 border border-green-200/50 dark:border-green-700/50'
      : 'bg-gradient-to-r from-red-100 to-rose-100 dark:from-red-900/40 dark:to-rose-900/40 text-red-700 dark:text-red-300 border border-red-200/50 dark:border-red-700/50';
    
    // Generate category information
    const category = CATEGORIES.find(c => c.slug === p.category);
    const categoryIcon = category ? category.icon : 'fa-briefcase';
    const categoryName = category ? category.name : p.category;
    
    // Generate tags
    const tagsHtml = (p.tags || []).slice(0, 4).map(tag => 
      `<span class="px-2 py-1 sm:px-3 sm:py-1.5 text-xs font-medium bg-gradient-to-r from-neutral-100 to-neutral-50 dark:from-neutral-700 dark:to-neutral-600 text-neutral-700 dark:text-neutral-300 rounded-md sm:rounded-lg border border-neutral-200/50 dark:border-neutral-600/50 shadow-sm active:shadow-md transition-all duration-200">#${tag}</span>`
    ).join('');
    
    const moreTagsHtml = (p.tags || []).length > 4 
      ? `<span class="px-2 py-1 sm:px-3 sm:py-1.5 text-xs font-medium bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 rounded-md sm:rounded-lg border border-primary-200/50 dark:border-primary-700/50">+${(p.tags || []).length - 4} more</span>`
      : '';

    cardDiv.innerHTML = `
      <!-- Background Gradient Overlay -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-50/20 via-transparent to-secondary-50/20 dark:from-primary-900/10 dark:via-transparent dark:to-secondary-900/10 opacity-0 group-active:opacity-100 transition-opacity duration-300"></div>
      
      <!-- Main Content -->
      <div class="relative z-10 p-4 sm:p-6">
        <!-- Header with photo and basic info -->
        <div class="flex flex-col sm:flex-row items-center sm:items-start space-y-3 sm:space-y-0 sm:space-x-4 mb-4 sm:mb-5">
          <div class="relative flex-shrink-0">
            <!-- Profile Photo with Enhanced Styling - 1:1 Ratio -->
            <div class="relative">
              <img src="${p.photo}" alt="${p.name}" 
                   class="w-20 h-20 sm:w-24 sm:h-24 md:w-28 md:h-28 rounded-xl sm:rounded-2xl object-cover border-2 sm:border-3 border-white dark:border-neutral-700 shadow-lg sm:shadow-xl ring-2 sm:ring-4 ring-primary-100/80 dark:ring-primary-900/50 group-active:ring-primary-200 dark:group-active:ring-primary-800/70 transition-all duration-300" />
              <!-- Status Indicator -->
              <div class="absolute -bottom-0.5 -right-0.5 sm:-bottom-1 sm:-right-1 bg-${statusColor}-500 w-5 h-5 sm:w-6 sm:h-6 rounded-full border-2 sm:border-3 border-white dark:border-neutral-800 shadow-lg flex items-center justify-center">
                <i class="fa-solid ${statusIcon} text-white text-xs sm:text-sm"></i>
              </div>
              <!-- Glow Effect -->
              <div class="absolute inset-0 rounded-xl sm:rounded-2xl bg-gradient-to-br from-primary-400/10 to-secondary-400/10 opacity-0 group-active:opacity-100 transition-opacity duration-300 blur-sm"></div>
            </div>
          </div>
          
          <div class="flex-1 min-w-0 text-center sm:text-left">
            <!-- Category Badge -->
            <div class="flex justify-center sm:justify-start items-center space-x-2 mb-2 sm:mb-2">
              <div class="bg-gradient-to-r from-primary-100 to-primary-50 dark:from-primary-900/60 dark:to-primary-800/40 px-2 py-1 sm:px-3 sm:py-1.5 rounded-lg sm:rounded-xl shadow-sm sm:shadow-md border border-primary-200/50 dark:border-primary-700/50 group-active:shadow-lg transition-all duration-300">
                <div class="flex items-center space-x-1 sm:space-x-2">
                  <div class="bg-primary-500 dark:bg-primary-400 p-0.5 sm:p-1 rounded-md sm:rounded-lg shadow-sm">
                    <i class="fa-solid ${categoryIcon} text-xs sm:text-sm text-white"></i>
                  </div>
                  <span class="text-primary-700 dark:text-primary-300 text-xs sm:text-sm font-semibold">${categoryName}</span>
                </div>
              </div>
            </div>
            
            <!-- Provider Name -->
            <h3 class="text-lg sm:text-xl font-bold text-neutral-900 dark:text-neutral-100 group-active:text-primary-600 dark:group-active:text-primary-400 transition-colors mb-2 line-clamp-2 sm:line-clamp-1">${p.name}</h3>
            
            <!-- Location and Price -->
            <div class="flex flex-col sm:flex-row items-center space-y-2 sm:space-y-0 sm:space-x-4 text-sm text-neutral-600 dark:text-neutral-400">
              <span class="flex items-center space-x-1.5 bg-neutral-100 dark:bg-neutral-700/50 px-2 py-1 rounded-lg">
                <i class="fa-solid fa-location-dot text-primary-500 dark:text-primary-400 text-xs sm:text-sm"></i>
                <span class="font-medium text-xs sm:text-sm">${p.location}</span>
              </span>
              <span class="flex items-center space-x-1.5 bg-gradient-to-r from-secondary-100 to-secondary-50 dark:from-secondary-900/50 dark:to-secondary-800/30 px-2 py-1 rounded-lg border border-secondary-200/50 dark:border-secondary-700/50">
                <i class="fa-solid fa-dollar-sign text-secondary-600 dark:text-secondary-400 text-xs sm:text-sm"></i>
                <span class="font-bold text-secondary-700 dark:text-secondary-300 text-xs sm:text-sm">$${p.price}/hr</span>
              </span>
            </div>
          </div>
        </div>

        <!-- Rating and Experience Section -->
        <div class="flex flex-col sm:flex-row items-center sm:items-center justify-between space-y-3 sm:space-y-0 mb-4 sm:mb-5 p-3 bg-neutral-50 dark:bg-neutral-700/30 rounded-lg sm:rounded-xl border border-neutral-200/50 dark:border-neutral-600/50">
          <div class="flex items-center space-x-3">
            <!-- Star Rating -->
            <div class="flex items-center space-x-1">
              <div class="flex text-yellow-400 text-base sm:text-lg">
                ${ratingStars(p.rating || 0)}
              </div>
              <span class="text-xs sm:text-sm font-bold text-neutral-700 dark:text-neutral-300 ml-1">${(p.rating || 0).toFixed(1)}</span>
            </div>
            
            <!-- Experience Badge -->
            <div class="bg-gradient-to-r from-amber-100 to-orange-100 dark:from-amber-900/40 dark:to-orange-900/40 px-2 py-1 rounded-lg border border-amber-200/50 dark:border-amber-700/50">
              <span class="text-xs font-semibold text-amber-700 dark:text-amber-300 flex items-center">
                <i class="fa-solid fa-medal mr-1"></i>
                <span class="hidden sm:inline">${p.experience || 0} years exp</span>
                <span class="sm:hidden">${p.experience || 0}y</span>
              </span>
            </div>
          </div>
          
          <!-- Status Badge -->
          <div class="flex items-center space-x-2">
            <span class="px-2 py-1 sm:px-3 sm:py-1.5 text-xs font-bold rounded-lg sm:rounded-xl shadow-md ${statusBadgeClasses}">
              <i class="fa-solid ${statusIcon} mr-1"></i>
              <span class="hidden sm:inline">${statusText}</span>
              <span class="sm:hidden">${p.active ? 'Available' : 'Busy'}</span>
            </span>
          </div>
        </div>

        <!-- Skills/Tags Section -->
        <div class="mb-4 sm:mb-5">
          <div class="flex flex-wrap gap-1.5 sm:gap-2 justify-center sm:justify-start">
            ${tagsHtml}
            ${moreTagsHtml}
          </div>
        </div>

        <!-- Contact Information and Action -->
        <div class="flex flex-col sm:flex-row items-center justify-between space-y-3 sm:space-y-0 pt-3 sm:pt-4 border-t border-neutral-200/60 dark:border-neutral-700/60">
          <div class="flex items-center space-x-3 text-sm text-neutral-600 dark:text-neutral-400">
            <span class="flex items-center space-x-1.5 bg-primary-50 dark:bg-primary-900/30 px-2 py-1 rounded-lg border border-primary-200/50 dark:border-primary-700/50">
              <i class="fa-solid fa-clock text-primary-500 dark:text-primary-400 text-xs sm:text-sm"></i>
              <span class="font-medium text-xs sm:text-sm">${p.bestCallTime || 'Contact anytime'}</span>
            </span>
            <!-- Favorite Button -->
            <button class="favorite-btn flex items-center justify-center px-3 py-1 bg-red-50 dark:bg-red-900/30 rounded-lg border border-red-200/50 dark:border-red-700/50 transition-all duration-300 hover:bg-red-100 dark:hover:bg-red-800/40 ${p.isFavorited ? 'favorited' : ''}" data-provider-id="${p.user_id}">
              <i class="fa-regular ${p.isFavorited ? 'fa-heart' : 'fa-heart'} text-red-500 dark:text-red-400 text-sm"></i>
            </button>
          </div>
          
          <!-- Enhanced CTA Button -->
          <button class="view-profile-btn relative bg-gradient-to-r from-primary-600 to-primary-700 active:from-primary-700 active:to-primary-800 dark:from-primary-600 dark:to-primary-700 dark:active:from-primary-700 dark:active:to-primary-800 text-white px-4 py-2 sm:px-6 sm:py-3 rounded-lg sm:rounded-xl font-bold transition-all duration-300 shadow-lg active:shadow-xl border border-primary-500/20 w-full sm:w-auto" data-provider-id="${p.id}">
            <span class="relative z-10 flex items-center justify-center space-x-2">
              <i class="fa-solid fa-eye text-sm"></i>
              <span class="text-sm sm:text-base">View Profile</span>
              <i class="fa-solid fa-arrow-right text-xs"></i>
            </span>
            <!-- Button Glow Effect -->
            <div class="absolute inset-0 bg-gradient-to-r from-primary-400 to-secondary-400 rounded-lg sm:rounded-xl opacity-0 group-active:opacity-20 transition-opacity duration-300 blur-sm"></div>
          </button>
        </div>
      </div>
      
      <!-- Enhanced Active Effect Overlay -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-600/5 via-transparent to-secondary-600/5 dark:from-primary-400/5 dark:via-transparent dark:to-secondary-400/5 opacity-0 group-active:opacity-100 transition-all duration-300 rounded-xl sm:rounded-2xl"></div>
      
      <!-- Subtle Border Glow -->
      <div class="absolute inset-0 rounded-xl sm:rounded-2xl bg-gradient-to-r from-primary-500/10 to-secondary-500/10 opacity-0 group-active:opacity-100 transition-opacity duration-300 blur-sm"></div>
    `;
    
    // Add click event to the entire card
    cardDiv.addEventListener('click', (e) => {
      // Don't trigger if clicking on the button specifically
      if (!e.target.closest('.view-profile-btn')) {
        window.location.href = `provider-profile.php?id=${encodeURIComponent(p.id)}`;
      }
    });
    
    // Add specific click event to the button
    const viewBtn = cardDiv.querySelector('.view-profile-btn');
    if (viewBtn) {
      viewBtn.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevent card click
        window.location.href = `provider-profile.php?id=${encodeURIComponent(p.id)}`;
      });
    }
    
    // Add favorite button functionality
    const favoriteBtn = cardDiv.querySelector('.favorite-btn');
    if (favoriteBtn) {
      favoriteBtn.addEventListener('click', async (e) => {
        e.stopPropagation(); // Prevent card click
        
        try {
          const providerId = favoriteBtn.dataset.providerId;
          const result = await toggleFavorite(providerId);
          
          // Update button state and provider data
          p.isFavorited = result.isFavorited;
          updateFavoriteButton(favoriteBtn, p.isFavorited);
          
          // Show feedback toast
          const message = p.isFavorited ? 'Added to favorites' : 'Removed from favorites';
          showToast(message, 'success');
        } catch (error) {
          console.error('Error toggling favorite:', error);
          showToast('Failed to update favorites', 'error');
        }
      });
    }
    
    listEl.appendChild(cardDiv);
  });
}

function fitMapToMarkers(mapInstance, markersArray) {
  if (!markersArray.length || !mapInstance) return;
  const bounds = new google.maps.LatLngBounds();
  markersArray.forEach(m => bounds.extend(m.getPosition()));
  mapInstance.fitBounds(bounds);
  if (markersArray.length === 1) {
    mapInstance.setZoom(13);
  }
}

function clearMarkers(markersArray) {
  markersArray.forEach(m => m.setMap(null));
  markersArray.length = 0;
}

function addMarkers(items, mapInstance, markersArray) {
  clearMarkers(markersArray);
  items.forEach(p => {
    const cat = CATEGORIES.find(c => c.slug === p.category);
    const marker = new google.maps.Marker({
      position: { lat: p.lat, lng: p.lng },
      map: mapInstance,
      title: `${p.name} • ${cat ? cat.name : ''}`,
      icon: {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 8,
        fillColor: '#6366f1',
        fillOpacity: 0.9,
        strokeColor: '#fff',
        strokeWeight: 2
      }
    });
    marker.addListener('click', () => {
      window.location.href = `provider-profile.php?id=${encodeURIComponent(p.id)}`;
    });
    markersArray.push(marker);
  });
  fitMapToMarkers(mapInstance, markersArray);
}

function filterProviders() {
  const q = document.getElementById('searchInput').value.toLowerCase().trim();
  const cat = document.getElementById('categorySelect').value;
  const priceMin = parseFloat(document.getElementById('priceMin').value || '0');
  const priceMax = parseFloat(document.getElementById('priceMax').value || '100000');
  const activeOnly = document.getElementById('activeOnly').checked;
  const skilledOnly = document.getElementById('skilledOnly').checked;
  const locationQ = document.getElementById('locationInput').value.toLowerCase().trim();

  let items = providers.filter(p => {
    const matchQ = !q || [p.name, p.location, p.tags?.join(' '), p.category].filter(Boolean).join(' ').toLowerCase().includes(q);
    const matchCat = !cat || p.category === cat;
    const matchPrice = p.price >= priceMin && p.price <= priceMax;
    const matchActive = !activeOnly || p.active;
    const matchSkilled = !skilledOnly || (p.rating >= 4.0 || p.skilled);
    const matchLoc = !locationQ || (p.location || '').toLowerCase().includes(locationQ);
    return matchQ && matchCat && matchPrice && matchActive && matchSkilled && matchLoc;
  });

  renderProviders(items);
  
  // Update both desktop and mobile maps
  if (map) {
    addMarkers(items, map, markers);
  }
  if (mobileMap) {
    addMarkers(items, mobileMap, mobileMarkers);
  }
}

function populateCategoryFilter() {
  const sel = document.getElementById('categorySelect');
  CATEGORIES.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.slug;
    opt.textContent = c.name;
    sel.appendChild(opt);
  });

  // Preselect from URL param
  const params = new URLSearchParams(location.search);
  const cat = params.get('category');
  if (cat) {
    sel.value = cat;
  }
}

function bindFilterEvents() {
  // Only filter on button click
  document.getElementById('searchButton').addEventListener('click', filterProviders);
  // Clear filters button
  document.getElementById('clearFilters').addEventListener('click', () => {
    ['searchInput','priceMin','priceMax','locationInput'].forEach(id => document.getElementById(id).value = '');
    ['activeOnly','skilledOnly'].forEach(id => document.getElementById(id).checked = false);
    document.getElementById('categorySelect').value = '';
    filterProviders();
  });
}

window.initServicesMap = function() {
  providers = getAllProviders();

  // Initialize desktop map
  const mapEl = document.getElementById('servicesMap');
  if (mapEl) {
    map = new google.maps.Map(mapEl, {
      center: { lat: 37.773972, lng: -122.431297 },
      zoom: 10,
      mapId: undefined
    });
  }

  populateCategoryFilter();
  bindFilterEvents();
  initMobileMapEvents();
  // Do not call filterProviders() automatically. Only call on button click.
};

function initMobileMapEvents() {
  const mapButton = document.getElementById('mapButton');
  const mapModal = document.getElementById('mapModal');
  const closeMapModal = document.getElementById('closeMapModal');
  const closeMapModalBtn = document.getElementById('closeMapModalBtn');

  // Open mobile map modal
  if (mapButton) {
    mapButton.addEventListener('click', () => {
      mapModal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      
      // Initialize mobile map if not already done
      if (!mobileMap) {
        const mobileMapEl = document.getElementById('servicesMapMobile');
        if (mobileMapEl) {
          mobileMap = new google.maps.Map(mobileMapEl, {
            center: { lat: 37.773972, lng: -122.431297 },
            zoom: 10,
            mapId: undefined
          });
          
          // Add current filtered providers to mobile map
          const filteredProviders = getFilteredProviders();
          addMarkers(filteredProviders, mobileMap, mobileMarkers);
        }
      }
    });
  }

  // Close mobile map modal
  function closeModal() {
    mapModal.classList.add('hidden');
    document.body.style.overflow = 'auto';
  }

  if (closeMapModal) closeMapModal.addEventListener('click', closeModal);
  if (closeMapModalBtn) closeMapModalBtn.addEventListener('click', closeModal);

  // Close modal when clicking outside
  if (mapModal) {
    mapModal.addEventListener('click', (e) => {
      if (e.target === mapModal) closeModal();
    });
  }

  // Close modal with Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !mapModal.classList.contains('hidden')) {
      closeModal();
    }
  });
}

function getFilteredProviders() {
  const q = document.getElementById('searchInput').value.toLowerCase().trim();
  const cat = document.getElementById('categorySelect').value;
  const priceMin = parseFloat(document.getElementById('priceMin').value || '0');
  const priceMax = parseFloat(document.getElementById('priceMax').value || '100000');
  const activeOnly = document.getElementById('activeOnly').checked;
  const skilledOnly = document.getElementById('skilledOnly').checked;
  const locationQ = document.getElementById('locationInput').value.toLowerCase().trim();

  return providers.filter(p => {
    const matchQ = !q || [p.name, p.location, p.tags?.join(' '), p.category].filter(Boolean).join(' ').toLowerCase().includes(q);
    const matchCat = !cat || p.category === cat;
    const matchPrice = p.price >= priceMin && p.price <= priceMax;
    const matchActive = !activeOnly || p.active;
    const matchSkilled = !skilledOnly || (p.rating >= 4.0 || p.skilled);
    const matchLoc = !locationQ || (p.location || '').toLowerCase().includes(locationQ);
    return matchQ && matchCat && matchPrice && matchActive && matchSkilled && matchLoc;
  });
}