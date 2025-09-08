// Manage favorites functionality
async function toggleFavorite(button, providerId) {
  try {
    const isFavorited = button.classList.contains('favorited');
    const action = isFavorited ? 'remove' : 'add';

    console.log('Toggling favorite:', {
      providerId,
      action,
      url: `${BASE_URL}/api/favorites.php`
    });

    const response = await fetch(`${BASE_URL}/api/favorites.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ 
        provider_id: providerId,
        action: action
      }),
      credentials: 'include'
    });

    console.log('Response status:', response.status);
    
    // For debugging, log the raw response text
    const responseText = await response.text();
    console.log('Raw response:', responseText);
    
    // Try to parse the response as JSON
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (e) {
      console.error('Failed to parse response as JSON:', e);
      throw new Error('Invalid response from server');
    }

    if (!response.ok) {
      throw new Error(data.message || 'Failed to toggle favorite');
    }
    
    // Update button state
    updateFavoriteButton(button, !isFavorited);
    
    // Show success message
    showToast(data.message || (isFavorited ? 'Removed from favorites' : 'Added to favorites'), 'success');
    
    return data;
  } catch (error) {
    console.error('Error toggling favorite:', error);
    showToast(error.message || 'Failed to update favorites', 'error');
    throw error;
  }
}

// Update favorite button state
function updateFavoriteButton(btn, isFavorited) {
  const icon = btn.querySelector('i');
  
  // Update button classes
  if (isFavorited) {
    btn.classList.add('favorited', 'text-yellow-400');
    btn.classList.remove('text-neutral-300');
    icon.classList.remove('far');
    icon.classList.add('fas');
  } else {
    btn.classList.remove('favorited', 'text-yellow-400');
    btn.classList.add('text-neutral-300');
    icon.classList.remove('fas');
    icon.classList.add('far');
  }
  
  // Update the title
  btn.title = isFavorited ? 'Remove from favorites' : 'Add to favorites';
  
  // Find and update any other favorite buttons for the same provider
  const providerId = btn.getAttribute('data-provider-id');
  document.querySelectorAll(`.favorite-btn[data-provider-id="${providerId}"]`).forEach(otherBtn => {
    if (otherBtn !== btn) {
      if (isFavorited) {
        otherBtn.classList.add('favorited', 'text-yellow-400');
        otherBtn.classList.remove('text-neutral-300');
        otherBtn.querySelector('i').classList.remove('far');
        otherBtn.querySelector('i').classList.add('fas');
      } else {
        otherBtn.classList.remove('favorited', 'text-yellow-400');
        otherBtn.classList.add('text-neutral-300');
        otherBtn.querySelector('i').classList.remove('fas');
        otherBtn.querySelector('i').classList.add('far');
      }
      otherBtn.title = isFavorited ? 'Remove from favorites' : 'Add to favorites';
    }
  });
}
