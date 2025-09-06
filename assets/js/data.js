// Categories data - Updated structure
const CATEGORIES = [
  // Home Services - Repairs & Maintenance
  { slug: 'plumbing', name: 'Plumbing', icon: 'fa-solid fa-faucet-drip', bg: 'https://images.unsplash.com/photo-1581093458791-9d09b64c73f7?q=80&w=1200' },
  { slug: 'electrical', name: 'Electrical', icon: 'fa-solid fa-bolt-lightning', bg: 'https://images.unsplash.com/photo-1521207418485-99c705420785?q=80&w=1200' },
  { slug: 'painting', name: 'Painting', icon: 'fa-solid fa-paintbrush', bg: 'https://images.unsplash.com/photo-1506629082955-511b1aa562c8?q=80&w=1200' },
  
  // Home Services - Cleaning
  { slug: 'house-cleaning', name: 'House Cleaning', icon: 'fa-solid fa-broom', bg: 'https://images.unsplash.com/photo-1581578017427-9d1d3bdc9733?q=80&w=1200' },
  
  // Education & Training - Tutoring
  { slug: 'academic-subjects', name: 'Academic Subjects', icon: 'fa-solid fa-graduation-cap', bg: 'https://images.unsplash.com/photo-1523246191871-1c7a0cde85d0?q=80&w=1200' },
  { slug: 'languages', name: 'Languages', icon: 'fa-solid fa-language', bg: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=1200' },
  
  // Education & Training - Performing & Visual Arts
  { slug: 'music', name: 'Music', icon: 'fa-solid fa-music', bg: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?q=80&w=1200' },
  { slug: 'art', name: 'Art', icon: 'fa-solid fa-palette', bg: 'https://images.unsplash.com/photo-1541961017774-22349e4a1262?q=80&w=1200' },
  { slug: 'dance', name: 'Dance', icon: 'fa-solid fa-user-group', bg: 'https://images.unsplash.com/photo-1518611012118-696072aa579a?q=80&w=1200' },
  
  // Vehicle Services
  { slug: 'car-bike-repair', name: 'Car/Bike Repair', icon: 'fa-solid fa-car-side', bg: 'https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?q=80&w=1200' },
  
  // Tech & Digital Support - Device Help
  { slug: 'computer-laptop-repair', name: 'Computer/Laptop Repair', icon: 'fa-solid fa-laptop', bg: 'https://images.unsplash.com/photo-1518770660439-4636190af475?q=80&w=1200' },
  
  // Tech & Digital Support - Digital Services
  { slug: 'graphic-design', name: 'Graphic Design', icon: 'fa-solid fa-pen-nib', bg: 'https://images.unsplash.com/photo-1541701494587-cb58502866ab?q=80&w=1200' },
  { slug: 'video-editing', name: 'Video Editing', icon: 'fa-solid fa-video', bg: 'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?q=80&w=1200' },
];

// Empty arrays - providers and wanted ads will be loaded from the database
const SAMPLE_PROVIDERS = [];
const SAMPLE_WANTED = [];