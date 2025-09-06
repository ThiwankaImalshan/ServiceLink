// Profile Page Functionality

// User data will be loaded from the server/session
const currentUser = {
  id: '',
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  location: '',
  bio: '',
  photo: '',
  joinedDate: '',
  stats: {
    servicesPosted: 0,
    requestsMade: 0,
    averageRating: 0,
    completedJobs: 0
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

// Load Profile Data
function loadProfileData() {
  // Update profile header
  document.getElementById('profileName').textContent = `${currentUser.firstName} ${currentUser.lastName}`;
  document.getElementById('profileLocation').querySelector('span').textContent = currentUser.location;
  document.getElementById('profileJoined').querySelector('span').textContent = `Joined ${formatDate(currentUser.joinedDate)}`;
  document.getElementById('profilePhoto').src = currentUser.photo;

  // Update stats
  document.getElementById('totalServices').textContent = currentUser.stats.servicesPosted;
  document.getElementById('totalRequests').textContent = currentUser.stats.requestsMade;
  document.getElementById('averageRating').textContent = currentUser.stats.averageRating;
  document.getElementById('completedJobs').textContent = currentUser.stats.completedJobs;

  // Update form fields
  document.getElementById('firstName').value = currentUser.firstName;
  document.getElementById('lastName').value = currentUser.lastName;
  document.getElementById('email').value = currentUser.email;
  document.getElementById('phone').value = currentUser.phone;
  document.getElementById('location').value = currentUser.location;
  document.getElementById('bio').value = currentUser.bio || '';
}

// Load Recent Activity
function loadRecentActivity() {
  const activities = [
    {
      type: 'service_posted',
      message: 'Posted a new service: "Web Development"',
      time: '2 hours ago',
      icon: 'fa-plus',
      color: 'text-green-600'
    },
    {
      type: 'request_made',
      message: 'Requested "House Cleaning" service',
      time: '1 day ago',
      icon: 'fa-search',
      color: 'text-blue-600'
    },
    {
      type: 'review_received',
      message: 'Received a 5-star review from Sarah Johnson',
      time: '3 days ago',
      icon: 'fa-star',
      color: 'text-yellow-600'
    },
    {
      type: 'job_completed',
      message: 'Completed tutoring session with Mike Chen',
      time: '1 week ago',
      icon: 'fa-check',
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
    .filter(service => service.userId === currentUser.id)
    .slice(0, 6); // Show only first 6

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
          <button class="text-primary-600 hover:text-primary-700 font-medium text-sm">
            Edit
          </button>
        </div>
      </div>
    `;
  }).join('');
}

// Load My Requests
function loadMyRequests() {
  const requests = JSON.parse(localStorage.getItem('wanted') || '[]')
    .filter(request => request.userId === currentUser.id);

  const container = document.getElementById('myRequests');

  if (requests.length === 0) {
    container.innerHTML = `
      <div class="text-center py-12">
        <div class="bg-neutral-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-clipboard-list text-2xl text-neutral-400"></i>
        </div>
        <h3 class="text-lg font-medium text-neutral-900 mb-2">No requests yet</h3>
        <p class="text-neutral-500 mb-4">Post your first service request</p>
        <a href="wanted.html" class="bg-secondary-600 text-white px-4 py-2 rounded-lg hover:bg-secondary-700 transition-colors">
          Post Your First Request
        </a>
      </div>
    `;
    return;
  }

  container.innerHTML = requests.map(request => {
    const category = CATEGORIES.find(c => c.slug === request.category);
    return `
      <div class="bg-white rounded-xl shadow-lg border border-neutral-200 p-6 hover:shadow-xl transition-shadow">
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-start space-x-3">
            <div class="bg-secondary-100 p-2 rounded-lg">
              <i class="fa-solid ${category ? category.icon : 'fa-briefcase'} text-secondary-600"></i>
            </div>
            <div class="flex-1">
              <h4 class="font-semibold text-neutral-900 mb-1">${category ? category.name : request.category}</h4>
              <p class="text-neutral-600 text-sm mb-2">${request.description}</p>
              <div class="flex items-center space-x-4 text-sm text-neutral-500">
                <span class="flex items-center space-x-1">
                  <i class="fa-solid fa-location-dot"></i>
                  <span>${request.location}</span>
                </span>
                <span class="flex items-center space-x-1">
                  <i class="fa-solid fa-calendar"></i>
                  <span>${formatDate(request.createdAt)}</span>
                </span>
              </div>
            </div>
          </div>
          <div class="flex space-x-2">
            <button class="text-primary-600 hover:text-primary-700 text-sm font-medium">Edit</button>
            <button class="text-red-600 hover:text-red-700 text-sm font-medium">Delete</button>
          </div>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
          <div class="flex items-center space-x-2 text-green-700">
            <i class="fa-solid fa-check-circle"></i>
            <span class="text-sm font-medium">Active Request</span>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

// Load Reviews
function loadReviews() {
  const reviews = JSON.parse(localStorage.getItem('reviews') || '[]')
    .filter(review => review.providerId === currentUser.id);

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

// Utility Functions
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  });
}

// Edit Profile Modal (simplified version)
function editProfile() {
  // In a real app, this would open a modal or navigate to edit page
  alert('Edit profile functionality would be implemented here');
}

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
  // Initialize tabs
  initTabs();
  
  // Load profile data
  loadProfileData();
  loadRecentActivity();
  loadMyServices();
  loadMyRequests();
  loadReviews();

  // Edit profile button
  document.getElementById('editProfileBtn').addEventListener('click', editProfile);

  // Photo edit button
  document.getElementById('editPhotoBtn').addEventListener('click', () => {
    alert('Photo upload functionality would be implemented here');
  });

  // Quick action buttons
  document.getElementById('postRequestBtn').addEventListener('click', () => {
    window.location.href = 'wanted.html';
  });

  document.getElementById('newRequestBtn').addEventListener('click', () => {
    window.location.href = 'wanted.html';
  });

  // Personal info form
  document.getElementById('personalInfoForm').addEventListener('submit', (e) => {
    e.preventDefault();
    
    // Update user data
    currentUser.firstName = document.getElementById('firstName').value;
    currentUser.lastName = document.getElementById('lastName').value;
    currentUser.email = document.getElementById('email').value;
    currentUser.phone = document.getElementById('phone').value;
    currentUser.location = document.getElementById('location').value;
    currentUser.bio = document.getElementById('bio').value;

    // Update profile display
    loadProfileData();

    // Show success message
    const successMsg = document.createElement('div');
    successMsg.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center space-x-2';
    successMsg.innerHTML = `
      <i class="fa-solid fa-check-circle"></i>
      <span>Profile updated successfully!</span>
    `;
    document.body.appendChild(successMsg);
    
    setTimeout(() => {
      successMsg.remove();
    }, 3000);
  });

  // Logout functionality
  const logoutBtns = ['logoutBtn', 'logoutBtnMobile'];
  logoutBtns.forEach(btnId => {
    const btn = document.getElementById(btnId);
    if (btn) {
      btn.addEventListener('click', () => {
        if (confirm('Are you sure you want to logout?')) {
          // Clear user session (in real app, this would involve server)
          localStorage.removeItem('currentUser');
          window.location.href = 'login.html';
        }
      });
    }
  });

  // Set current year in footer
  document.getElementById('year').textContent = new Date().getFullYear();
});
