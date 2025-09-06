let mapP, markerP, provider;

function getProviderById(id) {
  const saved = JSON.parse(localStorage.getItem('providers') || '[]');
  return saved.find(p => p.id === id);
}

function providerIcon(category) {
  const c = CATEGORIES.find(c => c.slug === category);
  return `<i class="fa-solid ${c ? c.icon : 'fa-briefcase'}"></i>`;
}

function ratingStars(rating) {
  const r = Math.round(rating || 0);
  return Array.from({length:5}, (_,i)=> i<r ? '★' : '☆').join('');
}

// Create Professional Provider Profile Header Card
function createProfileHeader(p) {
  const category = CATEGORIES.find(c => c.slug === p.category);
  return `
    <!-- Background Gradient -->
    <div class="absolute inset-0 bg-gradient-to-br from-primary-50/50 via-transparent to-secondary-50/50 rounded-2xl"></div>
    
    <!-- Main Content -->
    <div class="relative z-10">
      <div class="flex flex-col lg:flex-row items-start lg:items-center space-y-6 lg:space-y-0 lg:space-x-8">
        <!-- Profile Photo Section -->
        <div class="relative flex-shrink-0">
          <div class="relative">
            <img src="${p.photo}" alt="${p.name}" 
                 class="w-32 h-32 lg:w-40 lg:h-40 rounded-3xl object-cover border-4 border-white shadow-2xl ring-4 ring-primary-100/80" />
            <!-- Status Indicator -->
            <div class="absolute -bottom-2 -right-2 bg-${p.active ? 'green' : 'red'}-500 w-8 h-8 rounded-full border-4 border-white shadow-lg flex items-center justify-center">
              <i class="fa-solid ${p.active ? 'fa-check' : 'fa-times'} text-white text-sm"></i>
            </div>
            <!-- Premium Badge if experienced -->
            ${p.experience >= 5 ? `
              <div class="absolute -top-2 -left-2 bg-gradient-to-r from-amber-400 to-orange-500 text-white px-2 py-1 rounded-full text-xs font-bold shadow-lg">
                <i class="fa-solid fa-crown mr-1"></i>Expert
              </div>
            ` : ''}
          </div>
        </div>

        <!-- Provider Info Section -->
        <div class="flex-1 min-w-0">
          <!-- Category Badge -->
          <div class="mb-3">
            <div class="inline-flex items-center bg-gradient-to-r from-primary-100 to-primary-50 px-4 py-2 rounded-xl shadow-md border border-primary-200/50">
              <div class="bg-primary-500 p-2 rounded-lg shadow-sm mr-3">
                ${providerIcon(p.category)}
              </div>
              <span class="text-primary-700 font-semibold">${category ? category.name : p.category}</span>
            </div>
          </div>

          <!-- Name and Location -->
          <h1 class="text-3xl lg:text-4xl font-bold text-neutral-900 mb-2">${p.name}</h1>
          <div class="flex flex-wrap items-center gap-4 text-neutral-600 mb-4">
            <span class="flex items-center space-x-2 bg-neutral-100 px-3 py-1.5 rounded-lg">
              <i class="fa-solid fa-location-dot text-primary-500"></i>
              <span class="font-medium">${p.location}</span>
            </span>
            <span class="flex items-center space-x-2 bg-gradient-to-r from-secondary-100 to-secondary-50 px-3 py-1.5 rounded-lg border border-secondary-200/50">
              <i class="fa-solid fa-dollar-sign text-secondary-600"></i>
              <span class="font-bold text-secondary-700">$${p.price}/hour</span>
            </span>
          </div>

          <!-- Rating and Stats -->
          <div class="flex flex-wrap items-center gap-4 mb-6">
            <div class="flex items-center space-x-2 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200/50">
              <div class="flex text-yellow-400 text-lg">
                ${ratingStars(p.rating || 0)}
              </div>
              <span class="font-bold text-amber-700">${(p.rating || 0).toFixed(1)}</span>
            </div>
            <div class="bg-gradient-to-r from-emerald-100 to-green-100 px-3 py-1.5 rounded-lg border border-emerald-200/50">
              <span class="text-sm font-semibold text-emerald-700 flex items-center">
                <i class="fa-solid fa-medal mr-2"></i>
                ${p.experience || 0} years experience
              </span>
            </div>
          </div>

          <!-- Status and Availability -->
          <div class="flex flex-wrap items-center gap-3">
            <span class="px-4 py-2 text-sm font-bold rounded-xl shadow-md ${p.active ? 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-700 border border-green-200/50' : 'bg-gradient-to-r from-red-100 to-rose-100 text-red-700 border border-red-200/50'}">
              <i class="fa-solid ${p.active ? 'fa-circle-check' : 'fa-circle-xmark'} mr-2"></i>
              ${p.active ? 'Available Now' : 'Currently Busy'}
            </span>
            ${p.bestCallTime ? `
              <span class="px-4 py-2 text-sm font-medium bg-blue-50 text-blue-700 rounded-lg border border-blue-200/50">
                <i class="fa-solid fa-clock mr-2"></i>
                Best time: ${p.bestCallTime}
              </span>
            ` : ''}
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col space-y-3 flex-shrink-0">
          <a href="tel:${p.contact?.phone || ''}" 
             class="bg-gradient-to-r from-secondary-600 to-secondary-700 hover:from-secondary-700 hover:to-secondary-800 text-white px-6 py-3 rounded-xl font-bold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 flex items-center space-x-2">
            <i class="fa-solid fa-phone"></i>
            <span>Call Now</span>
          </a>
          <a href="mailto:${p.contact?.email || ''}" 
             class="bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white px-6 py-3 rounded-xl font-bold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 flex items-center space-x-2">
            <i class="fa-solid fa-envelope"></i>
            <span>Email</span>
          </a>
        </div>
      </div>
    </div>
  `;
}

// Create About Card
function createAboutCard(p) {
  return `
    <div class="relative">
      <!-- Background decoration -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-50/30 to-secondary-50/30 rounded-2xl opacity-50"></div>
      
      <div class="relative z-10">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6 flex items-center">
          <i class="fa-solid fa-user-circle text-primary-600 mr-3"></i>
          About ${p.name}
        </h2>
        
        <div class="prose max-w-none">
          <p class="text-neutral-700 leading-relaxed text-lg">
            ${p.tags && p.tags.length ? 
              `Specializing in ${p.tags.slice(0, 3).join(', ')}${p.tags.length > 3 ? ` and ${p.tags.length - 3} more areas` : ''}.` :
              'Professional service provider ready to help with your needs.'
            }
            ${p.experience >= 3 ? ` With ${p.experience} years of experience, ` : ''}
            ${p.name.split(' ')[0]} is committed to delivering high-quality service.
          </p>
        </div>

        <!-- Skills Tags -->
        ${p.tags && p.tags.length ? `
          <div class="mt-6">
            <h3 class="text-lg font-semibold text-neutral-900 mb-3">Skills & Expertise</h3>
            <div class="flex flex-wrap gap-2">
              ${p.tags.map(tag => `
                <span class="px-3 py-1.5 text-sm font-medium bg-gradient-to-r from-neutral-100 to-neutral-50 text-neutral-700 rounded-lg border border-neutral-200/50 shadow-sm">
                  #${tag}
                </span>
              `).join('')}
            </div>
          </div>
        ` : ''}
      </div>
    </div>
  `;
}

// Create Experience & Skills Card
function createExperienceCard(p) {
  return `
    <div class="relative">
      <div class="absolute inset-0 bg-gradient-to-br from-emerald-50/30 to-green-50/30 rounded-2xl opacity-50"></div>
      
      <div class="relative z-10">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6 flex items-center">
          <i class="fa-solid fa-chart-line text-emerald-600 mr-3"></i>
          Experience & Performance
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Experience Years -->
          <div class="bg-gradient-to-br from-emerald-100 to-green-100 p-6 rounded-xl border border-emerald-200/50 text-center">
            <div class="text-3xl font-bold text-emerald-700 mb-2">
              ${p.experience || 0}
            </div>
            <div class="text-sm font-medium text-emerald-600">Years Experience</div>
          </div>
          
          <!-- Rating -->
          <div class="bg-gradient-to-br from-amber-100 to-yellow-100 p-6 rounded-xl border border-amber-200/50 text-center">
            <div class="text-3xl font-bold text-amber-700 mb-2">
              ${(p.rating || 0).toFixed(1)}
            </div>
            <div class="text-sm font-medium text-amber-600">Average Rating</div>
          </div>
          
          <!-- Price Range -->
          <div class="bg-gradient-to-br from-blue-100 to-indigo-100 p-6 rounded-xl border border-blue-200/50 text-center">
            <div class="text-3xl font-bold text-blue-700 mb-2">
              $${p.price}
            </div>
            <div class="text-sm font-medium text-blue-600">Per Hour</div>
          </div>
        </div>
      </div>
    </div>
  `;
}

// Create Availability Card
function createAvailabilityCard(p) {
  const workingDays = p.workingDays || ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
  const allDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  
  return `
    <div class="relative">
      <div class="absolute inset-0 bg-gradient-to-br from-blue-50/30 to-indigo-50/30 rounded-2xl opacity-50"></div>
      
      <div class="relative z-10">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6 flex items-center">
          <i class="fa-solid fa-calendar-clock text-blue-600 mr-3"></i>
          Availability
        </h2>
        
        <div class="space-y-6">
          <!-- Working Days -->
          <div>
            <h3 class="text-lg font-semibold text-neutral-900 mb-3">Working Days</h3>
            <div class="grid grid-cols-7 gap-2">
              ${allDays.map(day => `
                <div class="text-center p-3 rounded-lg border ${workingDays.includes(day) 
                  ? 'bg-green-100 border-green-200 text-green-700' 
                  : 'bg-neutral-100 border-neutral-200 text-neutral-500'
                }">
                  <div class="text-xs font-medium">${day}</div>
                </div>
              `).join('')}
            </div>
          </div>
          
          <!-- Working Hours -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-gradient-to-r from-blue-100 to-indigo-100 p-4 rounded-xl border border-blue-200/50">
              <h4 class="font-semibold text-blue-700 mb-2 flex items-center">
                <i class="fa-solid fa-clock mr-2"></i>
                Working Hours
              </h4>
              <p class="text-blue-600 font-medium">
                ${p.workingTime ? `${p.workingTime.start} - ${p.workingTime.end}` : '9:00 AM - 6:00 PM'}
              </p>
            </div>
            
            <div class="bg-gradient-to-r from-emerald-100 to-green-100 p-4 rounded-xl border border-emerald-200/50">
              <h4 class="font-semibold text-emerald-700 mb-2 flex items-center">
                <i class="fa-solid fa-phone-clock mr-2"></i>
                Best Call Time
              </h4>
              <p class="text-emerald-600 font-medium">
                ${p.bestCallTime || 'Contact anytime during working hours'}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;
}

// Create Qualifications Card
function createQualificationsCard(p) {
  return `
    <div class="relative">
      <div class="absolute inset-0 bg-gradient-to-br from-purple-50/30 to-indigo-50/30 rounded-2xl opacity-50"></div>
      
      <div class="relative z-10">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6 flex items-center">
          <i class="fa-solid fa-graduation-cap text-purple-600 mr-3"></i>
          Qualifications & Certifications
        </h2>
        
        ${p.qualifications && p.qualifications.length ? `
          <div class="space-y-4">
            ${p.qualifications.map(q => `
              <div class="bg-white p-4 rounded-xl border border-neutral-200 shadow-sm">
                <div class="flex items-start space-x-3">
                  <div class="bg-purple-100 p-2 rounded-lg">
                    <i class="fa-solid fa-certificate text-purple-600"></i>
                  </div>
                  <div class="flex-1">
                    <h3 class="font-semibold text-neutral-900">${q.title}</h3>
                    <p class="text-neutral-600 text-sm">${q.institute}</p>
                  </div>
                </div>
              </div>
            `).join('')}
          </div>
        ` : `
          <div class="text-center py-8">
            <div class="bg-neutral-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fa-solid fa-certificate text-2xl text-neutral-400"></i>
            </div>
            <p class="text-neutral-500">No qualifications listed yet.</p>
          </div>
        `}
      </div>
    </div>
  `;
}

// Create Contact Card
function createContactCard(p) {
  return `
    <div class="relative">
      <div class="absolute inset-0 bg-gradient-to-br from-secondary-50/50 to-primary-50/50 rounded-2xl opacity-50"></div>
      
      <div class="relative z-10">
        <h2 class="text-xl font-bold text-neutral-900 mb-6 flex items-center">
          <i class="fa-solid fa-address-card text-secondary-600 mr-3"></i>
          Contact Information
        </h2>
        
        <div class="space-y-4">
          <!-- Phone -->
          <div class="flex items-center space-x-3 p-3 bg-white rounded-lg border border-neutral-200">
            <div class="bg-green-100 p-2 rounded-lg">
              <i class="fa-solid fa-phone text-green-600"></i>
            </div>
            <div class="flex-1">
              <div class="text-sm text-neutral-500">Phone</div>
              <div class="font-medium text-neutral-900">
                ${p.contact?.phone || 'Not provided'}
              </div>
            </div>
          </div>
          
          <!-- Email -->
          <div class="flex items-center space-x-3 p-3 bg-white rounded-lg border border-neutral-200">
            <div class="bg-blue-100 p-2 rounded-lg">
              <i class="fa-solid fa-envelope text-blue-600"></i>
            </div>
            <div class="flex-1">
              <div class="text-sm text-neutral-500">Email</div>
              <div class="font-medium text-neutral-900 break-all">
                ${p.contact?.email || 'Not provided'}
              </div>
            </div>
          </div>
          
          <!-- Location with Embedded Map -->
          <div class="bg-white rounded-lg border border-neutral-200 overflow-hidden">
            <div class="flex items-center space-x-3 p-3 border-b border-neutral-200">
              <div class="bg-red-100 p-2 rounded-lg">
                <i class="fa-solid fa-location-dot text-red-600"></i>
              </div>
              <div class="flex-1">
                <div class="text-sm text-neutral-500">Location</div>
                <div class="font-medium text-neutral-900">${p.location}</div>
              </div>
            </div>
            <!-- Embedded Map Container -->
            <div class="relative">
              <div id="providerMap" class="h-48 bg-neutral-100" style="pointer-events: none;"></div>
              <!-- Click overlay to prevent any map interactions -->
              <div class="absolute inset-0 bg-transparent cursor-default" style="pointer-events: auto;"></div>
            </div>
          </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="mt-6 space-y-3">
          <a href="tel:${p.contact?.phone || ''}" 
             class="w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 flex items-center justify-center space-x-2">
            <i class="fa-solid fa-phone"></i>
            <span>Call Now</span>
          </a>
          
          <a href="mailto:${p.contact?.email || ''}" 
             class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 flex items-center justify-center space-x-2">
            <i class="fa-solid fa-envelope"></i>
            <span>Send Email</span>
          </a>
          
          <a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(p.location)}" 
             target="_blank"
             class="w-full bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 flex items-center justify-center space-x-2">
            <i class="fa-solid fa-external-link-alt"></i>
            <span>View on Google Maps</span>
          </a>
        </div>
      </div>
    </div>
  `;
}

function fillProfile(p) {
  // Fill each card with modern styled content
  document.getElementById('profileHeader').innerHTML = createProfileHeader(p);
  document.getElementById('aboutCard').innerHTML = createAboutCard(p);
  document.getElementById('experienceCard').innerHTML = createExperienceCard(p);
  document.getElementById('availabilityCard').innerHTML = createAvailabilityCard(p);
  document.getElementById('qualificationsCard').innerHTML = createQualificationsCard(p);
  document.getElementById('contactCard').innerHTML = createContactCard(p);
}

// Initialize provider content immediately (separate from map)
function initProvider() {
  const id = new URLSearchParams(location.search).get('id');
  provider = getProviderById(id);
  
  if (!provider) {
    document.querySelector('main .max-w-7xl').innerHTML = `
      <div class="bg-white rounded-2xl shadow-xl border-2 border-neutral-200/60 p-8 text-center">
        <div class="bg-red-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-exclamation-triangle text-2xl text-red-600"></i>
        </div>
        <h2 class="text-2xl font-bold text-neutral-900 mb-2">Provider Not Found</h2>
        <p class="text-neutral-600 mb-6">The provider you're looking for doesn't exist or has been removed.</p>
        <a href="services.html" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
          <i class="fa-solid fa-arrow-left mr-2"></i>
          Back to Services
        </a>
      </div>
    `;
    return;
  }

  fillProfile(provider);
  
  // Initialize rating system after profile is loaded
  setTimeout(() => {
    initRatingSystem();
  }, 0);
}

// Initialize map (separate function)
window.initProviderMap = function() {
  if (!provider) {
    // Provider not loaded yet, try to load it
    initProvider();
  }
  
  if (!provider) return; // Still no provider found

  // Initialize map with fallback for missing API key
  const mapEl = document.getElementById('providerMap');
  if (mapEl) {
    // Check if Google Maps is available
    if (window.google && window.google.maps) {
      try {
        mapP = new google.maps.Map(mapEl, {
          center: { lat: provider.lat, lng: provider.lng },
          zoom: 13,
          gestureHandling: 'none', // Completely disable all map interactions
          zoomControl: false,
          mapTypeControl: false,
          scaleControl: false,
          streetViewControl: false,
          rotateControl: false,
          fullscreenControl: false,
          disableDefaultUI: true, // Disable all UI controls
          draggable: false, // Disable dragging
          scrollwheel: false, // Disable scroll wheel zoom
          disableDoubleClickZoom: true, // Disable double-click zoom
          styles: [
            {
              "featureType": "all",
              "elementType": "geometry.fill",
              "stylers": [{"weight": "2.00"}]
            },
            {
              "featureType": "all",
              "elementType": "geometry.stroke",
              "stylers": [{"color": "#9c9c9c"}]
            }
          ]
        });
        
        // Remove all event listeners - map should be completely static
        // No scroll handling needed since map is non-interactive
        
        markerP = new google.maps.Marker({
          position: { lat: provider.lat, lng: provider.lng },
          map: mapP,
          title: provider.name,
          icon: {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
              <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="15" cy="15" r="12" fill="#2563eb" stroke="white" stroke-width="3"/>
                <circle cx="15" cy="15" r="4" fill="white"/>
              </svg>
            `),
            scaledSize: new google.maps.Size(30, 30),
            anchor: new google.maps.Point(15, 15)
          }
        });
      } catch (error) {
        console.error('Google Maps initialization failed:', error);
        showMapFallback(mapEl, provider);
      }
    } else {
      // Google Maps not loaded or API key invalid
      showMapFallback(mapEl, provider);
    }
  }
};

// Fallback function when Google Maps fails to load
function showMapFallback(mapEl, provider) {
  mapEl.innerHTML = `
    <div class="h-full flex flex-col items-center justify-center bg-gradient-to-br from-neutral-100 to-neutral-200 text-center p-4">
      <div class="bg-primary-100 p-3 rounded-full mb-3">
        <i class="fa-solid fa-map-location-dot text-2xl text-primary-600"></i>
      </div>
      <p class="text-sm text-neutral-600 mb-3">Interactive map unavailable</p>
      <p class="text-xs text-neutral-500">Use the "View on Google Maps" button below for directions</p>
    </div>
  `;
}

// Copy location function
function copyLocation(location) {
  navigator.clipboard.writeText(location).then(() => {
    // Show success feedback
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa-solid fa-check mr-2"></i>Copied!';
    button.classList.add('bg-green-500', 'text-white');
    button.classList.remove('bg-neutral-200', 'dark:bg-neutral-700', 'text-neutral-700', 'dark:text-neutral-300');
    
    setTimeout(() => {
      button.innerHTML = originalText;
      button.classList.remove('bg-green-500', 'text-white');
      button.classList.add('bg-neutral-200', 'dark:bg-neutral-700', 'text-neutral-700', 'dark:text-neutral-300');
    }, 2000);
  }).catch(() => {
    alert('Could not copy address. Please copy manually: ' + location);
  });
}

// Rating System
let currentRating = 0;
const ratingTexts = {
  1: "Poor - Not satisfied",
  2: "Fair - Below expectations", 
  3: "Good - Met expectations",
  4: "Very Good - Exceeded expectations",
  5: "Excellent - Outstanding service"
};

function initRatingSystem() {
  const modal = document.getElementById('ratingModal');
  const modalContent = document.getElementById('ratingModalContent');
  const stars = document.querySelectorAll('.star-btn');
  const ratingText = document.getElementById('ratingText');
  const submitBtn = document.getElementById('submitRating');
  const form = document.getElementById('ratingForm');

  // Add rating button to contact card
  function addRatingButton() {
    const contactCard = document.getElementById('contactCard');
    if (contactCard && !document.getElementById('rateProviderBtn')) {
      const ratingButton = document.createElement('button');
      ratingButton.id = 'rateProviderBtn';
      ratingButton.className = 'w-full bg-amber-500 text-white py-3 px-4 rounded-xl hover:bg-amber-600 transition-all duration-300 font-semibold flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl transform hover:scale-105 mt-4';
      ratingButton.innerHTML = `
        <i class="fa-solid fa-star"></i>
        <span>Rate this Provider</span>
      `;
      ratingButton.addEventListener('click', openRatingModal);
      contactCard.appendChild(ratingButton);
    }
  }

  // Open modal with animation
  function openRatingModal() {
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Trigger animation
    setTimeout(() => {
      modalContent.classList.remove('scale-95', 'opacity-0');
      modalContent.classList.add('scale-100', 'opacity-100');
    }, 0);
  }

  // Close modal with animation
  function closeRatingModal() {
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
      modal.classList.add('hidden');
      document.body.style.overflow = 'auto';
      resetForm();
    }, 0);
  }

  // Reset form
  function resetForm() {
    currentRating = 0;
    form.reset();
    updateStars();
    ratingText.textContent = 'Select a rating';
    submitBtn.disabled = true;
  }

  // Update star display
  function updateStars() {
    stars.forEach((star, index) => {
      const rating = index + 1;
      if (rating <= currentRating) {
        star.classList.remove('text-neutral-300');
        star.classList.add('text-yellow-400');
        star.style.transform = 'scale(1.1)';
      } else {
        star.classList.remove('text-yellow-400');
        star.classList.add('text-neutral-300');
        star.style.transform = 'scale(1)';
      }
    });
    
    // Update rating text
    if (currentRating > 0) {
      ratingText.textContent = ratingTexts[currentRating];
      ratingText.className = 'text-sm font-medium text-amber-600';
      submitBtn.disabled = false;
    } else {
      ratingText.textContent = 'Select a rating';
      ratingText.className = 'text-sm text-neutral-500';
      submitBtn.disabled = true;
    }
  }

  // Star click handlers
  stars.forEach((star, index) => {
    const rating = index + 1;
    
    // Click handler
    star.addEventListener('click', (e) => {
      e.preventDefault();
      currentRating = rating;
      updateStars();
    });

    // Hover effects
    star.addEventListener('mouseenter', () => {
      stars.forEach((s, i) => {
        if (i <= index) {
          s.classList.remove('text-neutral-300');
          s.classList.add('text-yellow-300');
        } else {
          s.classList.remove('text-yellow-300');
          s.classList.add('text-neutral-300');
        }
      });
    });

    star.addEventListener('mouseleave', () => {
      updateStars();
    });
  });

  // Modal event listeners
  document.getElementById('closeRatingModal').addEventListener('click', closeRatingModal);
  document.getElementById('cancelRating').addEventListener('click', closeRatingModal);

  // Close on outside click
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeRatingModal();
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeRatingModal();
    }
  });

  // Form submission
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    
    if (currentRating === 0) {
      alert('Please select a rating');
      return;
    }

    const reviewData = {
      rating: currentRating,
      review: document.getElementById('reviewText').value.trim(),
      reviewerName: document.getElementById('reviewerName').value.trim(),
      providerId: provider.id,
      date: new Date().toISOString()
    };

    // Save review to localStorage
    const reviews = JSON.parse(localStorage.getItem('reviews') || '[]');
    reviews.push(reviewData);
    localStorage.setItem('reviews', JSON.stringify(reviews));

    // Show success message
    const successMsg = document.createElement('div');
    successMsg.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center space-x-2';
    successMsg.innerHTML = `
      <i class="fa-solid fa-check-circle"></i>
      <span>Thank you for your review!</span>
    `;
    document.body.appendChild(successMsg);
    
    setTimeout(() => {
      successMsg.remove();
    }, 3000);

    closeRatingModal();
  });

  // Add rating button when provider is loaded
  addRatingButton();
}

// Initialize rating system when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  // Initialize provider content first
  initProvider();
  
  // Then initialize rating system
  setTimeout(() => {
    initRatingSystem();
  }, 0);
});
