// Provider Profile Page Functionality

// Provider data will be loaded from the server/session
const currentProvider = {
  id: '',
  name: '',
  businessName: '',
  category: '',
  email: '',
  phone: '',
  location: '',
  description: '',
  photo: '',
  hourlyRate: 0,
  experience: 0,
  joinedDate: '',
  stats: {
    activeServices: 0,
    averageRating: 0,
    totalReviews: 0,
    completedJobs: 0
  },
  workingDays: [],
  workingHours: {
    start: '',
    end: ''
  }
};

// Tab Management
function initTabs() {
  const tabButtons = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');

  tabButtons.forEach(button => {
    button.addEventListener('click', () => {
      const targetTab = button.getAttribute('data-tab');

      // Remove active classes
      tabButtons.forEach(btn => {
        btn.classList.remove('border-primary-600', 'text-primary-600');
        btn.classList.add('border-transparent', 'text-neutral-500');
      });

      // Add active classes to clicked tab
      button.classList.remove('border-transparent', 'text-neutral-500');
      button.classList.add('border-primary-600', 'text-primary-600');

      // Hide all tab contents
      tabContents.forEach(content => {
        content.classList.add('hidden');
      });

      // Show target tab content
      document.getElementById(`${targetTab}-tab`).classList.remove('hidden');
    });
  });
}

// Load Provider Profile Data
function loadProviderData() {
  // Update profile header
  document.getElementById('profileName').textContent = currentProvider.name;
  document.getElementById('profileLocation').querySelector('span').textContent = currentProvider.location;
  document.getElementById('profilePhoto').src = currentProvider.photo;

  // Update category
  const category = CATEGORIES.find(c => c.slug === currentProvider.category);
  const categoryElement = document.getElementById('profileCategory');
  if (category) {
    categoryElement.querySelector('i').className = `fa-solid ${category.icon} text-primary-600`;
    categoryElement.querySelector('span').textContent = category.name;
  }

  // Update stats
  document.getElementById('totalServices').textContent = currentProvider.stats.activeServices;
  document.getElementById('averageRating').textContent = currentProvider.stats.averageRating;
  document.getElementById('totalReviews').textContent = currentProvider.stats.totalReviews;
  document.getElementById('completedJobs').textContent = currentProvider.stats.completedJobs;

  // Update form fields
  document.getElementById('businessName').value = currentProvider.businessName;
  document.getElementById('category').value = currentProvider.category;
  document.getElementById('hourlyRate').value = currentProvider.hourlyRate;
  document.getElementById('experience').value = currentProvider.experience;
  document.getElementById('serviceDescription').value = currentProvider.description;
}

// Load Recent Activity
function loadRecentActivity() {
  const activities = [
    {
      type: 'booking_received',
      message: 'New booking request from Sarah Johnson',
      time: '1 hour ago',
      icon: 'fa-calendar-plus',
      color: 'text-green-600'
    },
    {
      type: 'service_updated',
      message: 'Updated "React Development" service pricing',
      time: '3 hours ago',
      icon: 'fa-edit',
      color: 'text-blue-600'
    },
    {
      type: 'review_received',
      message: 'Received 5-star review from Mike Chen',
      time: '1 day ago',
      icon: 'fa-star',
      color: 'text-yellow-600'
    },
    {
      type: 'payment_received',
      message: 'Payment received for tutoring session',
      time: '2 days ago',
      icon: 'fa-dollar-sign',
      color: 'text-emerald-600'
    },
    {
      type: 'service_completed',
      message: 'Completed website development project',
      time: '3 days ago',
      icon: 'fa-check-circle',
      color: 'text-purple-600'
    }
  ];

  const container = document.getElementById('recentActivity');
  container.innerHTML = activities.map(activity => `
    <div class="flex items-start space-x-3 p-4 bg-neutral-50 rounded-lg border border-neutral-100 hover:shadow-md transition-shadow">
      <div class="flex-shrink-0">
        <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-sm">
          <i class="fa-solid ${activity.icon} ${activity.color} text-sm"></i>
        </div>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-neutral-900">${activity.message}</p>
        <p class="text-xs text-neutral-500 mt-1">${activity.time}</p>
      </div>
    </div>
  `).join('');
}

// Load My Services
function loadMyServices() {
  const services = JSON.parse(localStorage.getItem('providers') || '[]')
    .filter(service => service.providerId === currentProvider.id);

  const container = document.getElementById('myServices');
  
  if (services.length === 0) {
    container.innerHTML = `
      <div class="col-span-full text-center py-12">
        <div class="bg-neutral-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-briefcase text-2xl text-neutral-400"></i>
        </div>
        <h3 class="text-lg font-medium text-neutral-900 mb-2">No services yet</h3>
        <p class="text-neutral-500 mb-4">Start by posting your first service</p>
        <a href="my-service.html" class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors">
          Post Your First Service
        </a>
      </div>
    `;
    return;
  }

  container.innerHTML = services.map(service => {
    const category = CATEGORIES.find(c => c.slug === service.category);
    return `
      <div class="bg-white rounded-xl shadow-lg border border-neutral-200 p-6 hover:shadow-xl transition-shadow">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center space-x-3">
            <div class="bg-primary-100 p-2 rounded-lg">
              <i class="fa-solid ${category ? category.icon : 'fa-briefcase'} text-primary-600"></i>
            </div>
            <div>
              <h4 class="font-semibold text-neutral-900">${category ? category.name : service.category}</h4>
              <p class="text-sm text-neutral-500">${service.location}</p>
            </div>
          </div>
          <span class="text-lg font-bold text-primary-600">$${service.price}/hr</span>
        </div>
        <p class="text-neutral-600 text-sm mb-4 line-clamp-2">${service.description}</p>
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <div class="flex text-yellow-400 text-sm">
              ${Array.from({length: 5}, (_, i) => i < Math.round(service.rating || 0) ? '★' : '☆').join('')}
            </div>
            <span class="text-sm text-neutral-500">(${service.rating || 0})</span>
          </div>
          <div class="flex space-x-2">
            <button class="text-primary-600 hover:text-primary-700 font-medium text-sm" onclick="editService('${service.id}')">
              Edit
            </button>
            <button class="text-red-600 hover:text-red-700 font-medium text-sm" onclick="deleteService('${service.id}')">
              Delete
            </button>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

// Load Reviews
function loadReviews() {
  const reviews = JSON.parse(localStorage.getItem('reviews') || '[]')
    .filter(review => review.providerId === currentProvider.id);

  const container = document.getElementById('reviewsContent');

  if (reviews.length === 0) {
    container.innerHTML = `
      <div class="text-center py-12">
        <div class="bg-neutral-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-star text-2xl text-neutral-400"></i>
        </div>
        <h3 class="text-lg font-medium text-neutral-900 mb-2">No reviews yet</h3>
        <p class="text-neutral-500">Reviews from your clients will appear here</p>
      </div>
    `;
    return;
  }

  container.innerHTML = `
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      ${reviews.map(review => `
        <div class="bg-white rounded-xl shadow-lg border border-neutral-200 p-6">
          <div class="flex items-start justify-between mb-4">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
                <span class="text-primary-600 font-semibold">${review.reviewerName.charAt(0)}</span>
              </div>
              <div>
                <h4 class="font-semibold text-neutral-900">${review.reviewerName}</h4>
                <div class="flex items-center space-x-2">
                  <div class="flex text-yellow-400">
                    ${Array.from({length: 5}, (_, i) => i < review.rating ? '★' : '☆').join('')}
                  </div>
                  <span class="text-sm text-neutral-500">${formatDate(review.date)}</span>
                </div>
              </div>
            </div>
          </div>
          ${review.review ? `<p class="text-neutral-600 text-sm">"${review.review}"</p>` : ''}
        </div>
      `).join('')}
    </div>
  `;
}

// Service Management Functions
function editService(serviceId) {
  // In a real app, this would open edit modal or navigate to edit page
  alert(`Edit service functionality would open for service ID: ${serviceId}`);
}

function deleteService(serviceId) {
  if (confirm('Are you sure you want to delete this service?')) {
    let services = JSON.parse(localStorage.getItem('providers') || '[]');
    services = services.filter(service => service.id !== serviceId);
    localStorage.setItem('providers', JSON.stringify(services));
    
    // Reload services
    loadMyServices();
    
    // Show success message
    showSuccessMessage('Service deleted successfully!');
  }
}

// Utility Functions
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  });
}

function showSuccessMessage(message) {
  const successMsg = document.createElement('div');
  successMsg.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center space-x-2';
  successMsg.innerHTML = `
    <i class="fa-solid fa-check-circle"></i>
    <span>${message}</span>
  `;
  document.body.appendChild(successMsg);
  
  setTimeout(() => {
    successMsg.remove();
  }, 3000);
}

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
  // Initialize tabs
  initTabs();
  
  // Load provider data
  loadProviderData();
  loadRecentActivity();
  loadMyServices();
  loadReviews();

  // Edit profile button
  document.getElementById('editProfileBtn').addEventListener('click', () => {
    alert('Edit profile functionality would be implemented here');
  });

  // Photo edit button
  document.getElementById('editPhotoBtn').addEventListener('click', () => {
    alert('Photo upload functionality would be implemented here');
  });

  // Business info form
  document.getElementById('businessInfoForm').addEventListener('submit', (e) => {
    e.preventDefault();
    
    // Update provider data
    currentProvider.businessName = document.getElementById('businessName').value;
    currentProvider.category = document.getElementById('category').value;
    currentProvider.hourlyRate = parseInt(document.getElementById('hourlyRate').value);
    currentProvider.experience = parseInt(document.getElementById('experience').value);
    currentProvider.description = document.getElementById('serviceDescription').value;

    // Update profile display
    loadProviderData();

    showSuccessMessage('Business information updated successfully!');
  });

  // Logout functionality
  const logoutBtns = ['logoutBtn', 'logoutBtnMobile'];
  logoutBtns.forEach(btnId => {
    const btn = document.getElementById(btnId);
    if (btn) {
      btn.addEventListener('click', () => {
        if (confirm('Are you sure you want to logout?')) {
          localStorage.removeItem('currentProvider');
          window.location.href = 'login.html';
        }
      });
    }
  });

  // Set current year in footer
  document.getElementById('year').textContent = new Date().getFullYear();
});
