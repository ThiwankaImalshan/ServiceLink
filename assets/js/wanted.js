function getWanted() {
  const saved = JSON.parse(localStorage.getItem('wanted') || '[]');
  return saved;
}

function setWanted(list) {
  localStorage.setItem('wanted', JSON.stringify(list));
}

function renderWanted(list) {
  const listEl = document.getElementById('wantedList');
  listEl.innerHTML = '';
  if (!list.length) {
    listEl.innerHTML = `
      <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-lg border border-neutral-200 dark:border-neutral-700 p-8 text-center">
        <i class="fa-solid fa-clipboard-list text-4xl text-neutral-400 dark:text-neutral-500 mb-4"></i>
        <h3 class="text-lg font-semibold text-neutral-700 dark:text-neutral-300 mb-2">No requests yet</h3>
        <p class="text-neutral-500 dark:text-neutral-400">Be the first to post a service request</p>
      </div>
    `;
    return;
  }
  list.sort((a,b)=> (b.createdAt||0)-(a.createdAt||0));
  
  // Get current user (in a real app, this would come from auth)
  const currentUser = localStorage.getItem('currentUser') || 'John Doe';
  
  list.forEach(w => {
    const cat = CATEGORIES.find(c => c.slug === w.category);
    const isOwner = w.postedBy === currentUser || w.id.startsWith('w_'); // User owns posts they created
    const div = document.createElement('div');
    div.className = 'bg-white dark:bg-neutral-800 rounded-xl shadow-lg border border-neutral-200 dark:border-neutral-700 hover:shadow-xl dark:hover:shadow-neutral-900/30 hover:border-secondary-300 dark:hover:border-secondary-600 transition-all duration-300 p-6 group transform hover:-translate-y-1 hover:scale-[1.01] relative';
    div.innerHTML = `
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
          <div class="bg-secondary-100 dark:bg-secondary-900/50 p-2 rounded-lg shadow-sm">
            <i class="fa-solid ${cat ? cat.icon : 'fa-briefcase'} text-secondary-600 dark:text-secondary-400"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 group-hover:text-secondary-600 dark:group-hover:text-secondary-400 transition-colors">
              ${cat ? cat.name : w.category}
            </h3>
            <div class="flex items-center space-x-2 text-sm text-neutral-500 dark:text-neutral-400">
              <i class="fa-solid fa-calendar text-secondary-500 dark:text-secondary-400"></i>
              <span>Posted ${new Date(w.createdAt || Date.now()).toLocaleDateString()}</span>
            </div>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <div class="bg-secondary-50 dark:bg-secondary-900/30 px-3 py-1 rounded-full shadow-sm">
            <span class="text-xs font-medium text-secondary-700 dark:text-secondary-400">Active Request</span>
          </div>
          ${isOwner ? `
            <button onclick="deleteWantedPost('${w.id}')" class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition-colors" title="Delete your post">
              <i class="fa-solid fa-trash text-sm"></i>
            </button>
          ` : ''}
        </div>
      </div>

      <!-- Description -->
      <div class="mb-4">
        <p class="text-neutral-700 dark:text-neutral-300 leading-relaxed">${w.description}</p>
      </div>

      <!-- Contact Information -->
      <div class="bg-neutral-50 dark:bg-neutral-700/50 rounded-lg p-4 space-y-2 border border-neutral-100 dark:border-neutral-600 shadow-sm">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4 text-sm">
            <span class="flex items-center space-x-2 text-neutral-600 dark:text-neutral-400">
              <i class="fa-solid fa-location-dot text-primary-500 dark:text-primary-400"></i>
              <span class="font-medium">${w.location}</span>
            </span>
            <span class="flex items-center space-x-2 text-neutral-600 dark:text-neutral-400">
              <i class="fa-solid fa-user text-secondary-500 dark:text-secondary-400"></i>
              <span class="font-medium">${w.postedBy}</span>
            </span>
          </div>
          <button class="bg-secondary-600 hover:bg-secondary-700 dark:bg-secondary-600 dark:hover:bg-secondary-700 text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium flex items-center space-x-2 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
            <i class="fa-solid fa-phone"></i>
            <span>Contact</span>
          </button>
        </div>
        <div class="flex items-center space-x-2 text-sm text-neutral-500 dark:text-neutral-400 pt-2 border-t border-neutral-200 dark:border-neutral-600">
          <i class="fa-solid fa-envelope"></i>
          <span>${w.contact}</span>
        </div>
      </div>

      <!-- Tags/Category indicator -->
      <div class="mt-4 flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <span class="px-2 py-1 text-xs font-medium bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-400 rounded-md shadow-sm">
            Service Needed
          </span>
          <span class="px-2 py-1 text-xs font-medium bg-neutral-100 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 rounded-md shadow-sm">
            ${w.urgency || 'Standard'}
          </span>
          ${isOwner ? `
            <span class="px-2 py-1 text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400 rounded-md shadow-sm">
              <i class="fa-solid fa-user-check mr-1"></i>Your Post
            </span>
          ` : ''}
        </div>
        <div class="text-xs text-neutral-400 dark:text-neutral-500">
          <i class="fa-solid fa-clock mr-1"></i>
          ${Math.ceil((Date.now() - (w.createdAt || Date.now())) / (1000 * 60 * 60 * 24))} days ago
        </div>
      </div>
      
      <!-- Hover effect overlay -->
      <div class="absolute inset-0 bg-gradient-to-r from-secondary-600/5 to-primary-600/5 dark:from-secondary-400/5 dark:to-primary-400/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-xl pointer-events-none"></div>
    `;
    listEl.appendChild(div);
  });
}

function populateWantedCats() {
  const selects = [
    document.getElementById('wantedCategory'), 
    document.getElementById('postCategory'),
    document.getElementById('postCategoryMobile')
  ];
  selects.forEach(sel => {
    if (sel) {
      CATEGORIES.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.slug;
        opt.textContent = c.name;
        sel.appendChild(opt);
      });
    }
  });
}

function filterWanted() {
  const cat = document.getElementById('wantedCategory').value;
  const loc = document.getElementById('wantedLocation').value.toLowerCase().trim();
  const q = document.getElementById('wantedQuery').value.toLowerCase().trim();
  const all = getWanted();
  const items = all.filter(w => {
    const matchCat = !cat || w.category === cat;
    const matchLoc = !loc || w.location.toLowerCase().includes(loc);
    const matchQ = !q || w.description.toLowerCase().includes(q);
    return matchCat && matchLoc && matchQ;
  });
  renderWanted(items);
}

document.addEventListener('DOMContentLoaded', () => {
  populateWantedCats();
  renderWanted(getWanted());

  // Modal functionality
  const modal = document.getElementById('postModal');
  const postBtn = document.getElementById('postRequestBtn');
  const postBtnHeader = document.getElementById('postRequestBtnHeader');
  const closeBtn = document.getElementById('closeModal');
  const cancelBtn = document.getElementById('cancelModal');

  // Open modal function
  function openModal() {
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  // Open modal from header button
  if (postBtn) {
    postBtn.addEventListener('click', openModal);
  }

  // Open modal from page header button
  if (postBtnHeader) {
    postBtnHeader.addEventListener('click', openModal);
  }

  // Close modal
  function closeModal() {
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    document.getElementById('wantedFormMobile').reset();
  }

  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

  // Close modal when clicking outside
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  // Close modal with Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeModal();
    }
  });

  // Handle desktop search and clear buttons
  document.getElementById('wantedSearchBtn').addEventListener('click', filterWanted);
  document.getElementById('wantedClearBtn').addEventListener('click', () => {
    ['wantedCategory','wantedLocation','wantedQuery'].forEach(id => document.getElementById(id).value = '');
    filterWanted();
  });

  // Handle mobile search and clear buttons
  document.getElementById('wantedSearchBtnMobile').addEventListener('click', filterWanted);
  document.getElementById('wantedClearBtnMobile').addEventListener('click', () => {
    ['wantedCategory','wantedLocation','wantedQuery'].forEach(id => document.getElementById(id).value = '');
    filterWanted();
  });

  // Handle desktop form submission
  const desktopForm = document.getElementById('wantedForm');
  if (desktopForm) {
    desktopForm.addEventListener('submit', e => {
      e.preventDefault();
      const post = {
        id: 'w_' + Date.now(),
        category: document.getElementById('postCategory').value,
        description: document.getElementById('postDescription').value.trim(),
        location: document.getElementById('postLocation').value.trim(),
        postedBy: document.getElementById('postUser').value.trim(),
        contact: document.getElementById('postContact').value.trim(),
        createdAt: Date.now()
      };
      const cur = JSON.parse(localStorage.getItem('wanted') || '[]');
      cur.push(post);
      setWanted(cur);
      e.target.reset();
      renderWanted(getWanted());
      alert('Wanted post published!');
    });
  }

  // Handle mobile form submission
  const mobileForm = document.getElementById('wantedFormMobile');
  if (mobileForm) {
    mobileForm.addEventListener('submit', e => {
      e.preventDefault();
      const post = {
        id: 'w_' + Date.now(),
        category: document.getElementById('postCategoryMobile').value,
        description: document.getElementById('postDescriptionMobile').value.trim(),
        location: document.getElementById('postLocationMobile').value.trim(),
        postedBy: document.getElementById('postUserMobile').value.trim(),
        contact: document.getElementById('postContactMobile').value.trim(),
        createdAt: Date.now()
      };
      const cur = JSON.parse(localStorage.getItem('wanted') || '[]');
      cur.push(post);
      setWanted(cur);
      e.target.reset();
      renderWanted(getWanted());
      closeModal();
      
      // Show success message
      const successMsg = document.createElement('div');
      successMsg.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center space-x-2';
      successMsg.innerHTML = `
        <i class="fa-solid fa-check-circle"></i>
        <span>Request published successfully!</span>
      `;
      document.body.appendChild(successMsg);
      setTimeout(() => {
        successMsg.remove();
      }, 3000);
    });
  }
});

// Delete wanted post function
function deleteWantedPost(postId) {
  if (confirm('Are you sure you want to delete this wanted post?')) {
    let wantedPosts = JSON.parse(localStorage.getItem('wanted') || '[]');
    wantedPosts = wantedPosts.filter(post => post.id !== postId);
    setWanted(wantedPosts);
    renderWanted(getWanted());
    
    // Show success message
    const successMsg = document.createElement('div');
    successMsg.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center space-x-2';
    successMsg.innerHTML = `
      <i class="fa-solid fa-check-circle"></i>
      <span>Post deleted successfully!</span>
    `;
    document.body.appendChild(successMsg);
    setTimeout(() => {
      successMsg.remove();
    }, 3000);
  }
}