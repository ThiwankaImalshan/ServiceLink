<!-- Professional Minimal Loader -->
<div id="pageLoader" class="fixed inset-0 z-50 flex items-center justify-center bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 transition-all duration-500">
    <!-- Background Pattern -->
    <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.1\"%3E%3Ccircle cx=\"30\" cy=\"30\" r=\"1\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-30"></div>
    
    <!-- Floating Elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-16 h-16 bg-white/10 rounded-full animate-float-1 blur-sm"></div>
        <div class="absolute top-40 right-32 w-12 h-12 bg-white/15 rounded-full animate-float-2 blur-sm"></div>
        <div class="absolute bottom-32 left-1/4 w-20 h-20 bg-white/5 rounded-full animate-float-3 blur-md"></div>
        <div class="absolute bottom-20 right-20 w-8 h-8 bg-white/20 rounded-full animate-float-4 blur-sm"></div>
        <div class="absolute top-1/2 left-10 w-6 h-6 bg-white/12 rounded-full animate-float-5 blur-sm"></div>
        <div class="absolute top-3/4 right-1/4 w-14 h-14 bg-white/8 rounded-full animate-float-6 blur-lg"></div>
    </div>
    
    <!-- Main Loader Content -->
    <div class="relative z-10 text-center">
        
        <!-- Minimal Logo Container -->
        <div class="relative mb-6">
            <!-- Simple Logo Background -->
            <div class="relative w-20 h-20 mx-auto bg-white/95 rounded-full shadow-xl flex items-center justify-center border border-white/30">
                <!-- Logo Image -->
                <img src="assets/img/logo.png" alt="ServiceLink Logo" class="w-12 h-12 object-contain">
            </div>
        </div>
        
        <!-- Brand Name -->
        <div class="mb-4">
            <h1 class="text-2xl font-bold text-white mb-1 tracking-wide">
                ServiceLink
            </h1>
            <p class="text-primary-100 text-sm font-medium opacity-90">Find trusted local pros</p>
        </div>
        
        <!-- Simple Loading Indicator -->
        <div class="mb-4">
            <!-- Loading Dots -->
            <div class="flex items-center justify-center space-x-2">
                <div class="w-2 h-2 bg-white/80 rounded-full animate-dot-1"></div>
                <div class="w-2 h-2 bg-white/80 rounded-full animate-dot-2"></div>
                <div class="w-2 h-2 bg-white/80 rounded-full animate-dot-3"></div>
            </div>
        </div>
        
        <!-- Loading Text -->
        <div>
            <p id="loadingText" class="text-white/80 text-sm font-medium">
                Loading services...
            </p>
        </div>
    </div>
</div>

<style>
/* ServiceLink Minimal Loader Styles */

/* Slow floating animations for background elements */
.animate-float-1 { animation: float1 8s ease-in-out infinite; }
.animate-float-2 { animation: float2 10s ease-in-out infinite; }
.animate-float-3 { animation: float3 12s ease-in-out infinite; }
.animate-float-4 { animation: float4 9s ease-in-out infinite; }
.animate-float-5 { animation: float5 11s ease-in-out infinite; }
.animate-float-6 { animation: float6 7s ease-in-out infinite; }

/* Slow dot animations */
.animate-dot-1 { animation: dotPulse 2s ease-in-out infinite; }
.animate-dot-2 { animation: dotPulse 2s ease-in-out infinite 0.4s; }
.animate-dot-3 { animation: dotPulse 2s ease-in-out infinite 0.8s; }

/* Floating keyframes */
@keyframes float1 {
    0%, 100% { transform: translateY(0px) translateX(0px); }
    25% { transform: translateY(-10px) translateX(5px); }
    50% { transform: translateY(-5px) translateX(-5px); }
    75% { transform: translateY(5px) translateX(3px); }
}

@keyframes float2 {
    0%, 100% { transform: translateY(0px) translateX(0px); }
    33% { transform: translateY(8px) translateX(-4px); }
    66% { transform: translateY(-6px) translateX(6px); }
}

@keyframes float3 {
    0%, 100% { transform: translateY(0px) translateX(0px); }
    25% { transform: translateY(6px) translateX(-3px); }
    50% { transform: translateY(-8px) translateX(-2px); }
    75% { transform: translateY(-4px) translateX(4px); }
}

@keyframes float4 {
    0%, 100% { transform: translateY(0px) translateX(0px); }
    30% { transform: translateY(-7px) translateX(3px); }
    70% { transform: translateY(5px) translateX(-5px); }
}

@keyframes float5 {
    0%, 100% { transform: translateY(0px) translateX(0px); }
    40% { transform: translateY(4px) translateX(2px); }
    80% { transform: translateY(-6px) translateX(-3px); }
}

@keyframes float6 {
    0%, 100% { transform: translateY(0px) translateX(0px); }
    20% { transform: translateY(-5px) translateX(-2px); }
    60% { transform: translateY(7px) translateX(4px); }
}

/* Dot pulse animation */
@keyframes dotPulse {
    0%, 100% { opacity: 0.4; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.2); }
}

/* Responsive Adjustments */
@media (max-width: 640px) {
    #pageLoader .w-20 {
        width: 4rem;
        height: 4rem;
    }
    
    #pageLoader .w-12 {
        width: 2.5rem;
        height: 2.5rem;
    }
}

/* Smooth transitions for loader hide */
#pageLoader.loader-hidden {
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
}
</style>

<script>
// ServiceLink Minimal Loader Script
document.addEventListener('DOMContentLoaded', function() {
    const loader = document.getElementById('pageLoader');
    const loadingText = document.getElementById('loadingText');
    
    // Simple loading messages
    const loadingMessages = [
        "Loading services...",
        "Finding professionals...",
        "Almost ready...",
        "Welcome to ServiceLink"
    ];
    
    let currentMessageIndex = 0;
    let startTime = Date.now();
    const minLoadingTime = 3000; // Minimum 3 seconds for slower experience
    
    function updateLoadingMessage() {
        if (currentMessageIndex < loadingMessages.length) {
            loadingText.textContent = loadingMessages[currentMessageIndex];
            currentMessageIndex++;
        }
    }
    
    // Update message every 800ms for slower pace
    const messageInterval = setInterval(() => {
        if (currentMessageIndex < loadingMessages.length) {
            updateLoadingMessage();
        }
    }, 800);
    
    function completeLoading() {
        clearInterval(messageInterval);
        loadingText.textContent = "Welcome to ServiceLink";
        
        setTimeout(() => {
            hideLoader();
        }, 1000);
    }
    
    function hideLoader() {
        loader.style.opacity = '0';
        loader.style.transition = 'opacity 0.8s ease-out';
        
        setTimeout(() => {
            loader.style.display = 'none';
            
            const loadingCompleteEvent = new CustomEvent('servicelink-loaded', {
                detail: { loadingTime: Date.now() - startTime }
            });
            document.dispatchEvent(loadingCompleteEvent);
        }, 800);
    }
    
    // Complete loading after minimum time
    setTimeout(() => {
        completeLoading();
    }, minLoadingTime);
    
    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            loader.style.animationPlayState = 'paused';
        } else {
            loader.style.animationPlayState = 'running';
        }
    });
    
    // Preload critical resources
    const criticalImages = [
        'assets/img/logo.png',
        'assets/img/profession.png',
        'assets/img/electrician.jpg'
    ];
    
    criticalImages.forEach(src => {
        const img = new Image();
        img.src = src;
    });
    
    // Global functions
    window.showServiceLinkLoader = function() {
        loader.style.display = 'flex';
        loader.style.opacity = '1';
        currentMessageIndex = 0;
        startTime = Date.now();
        updateLoadingMessage();
        
        const newMessageInterval = setInterval(() => {
            if (currentMessageIndex < loadingMessages.length) {
                updateLoadingMessage();
            }
        }, 800);
        
        setTimeout(() => {
            clearInterval(newMessageInterval);
            completeLoading();
        }, minLoadingTime);
    };
    
    window.hideServiceLinkLoader = function() {
        hideLoader();
    };
});

// Optional: Listen for the custom loading complete event
document.addEventListener('servicelink-loaded', function(event) {
    console.log('ServiceLink loaded in:', event.detail.loadingTime + 'ms');
});
</script>
