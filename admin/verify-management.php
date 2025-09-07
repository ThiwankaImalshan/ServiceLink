<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/VerificationManager.php';

// Check if user is logged in and is admin
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    redirect(BASE_URL . '/login.php');
}

$verificationManager = new VerificationManager();

$pageTitle = 'Verification Management • ServiceLink Admin';
$pageDescription = 'Manage user verification requests';

$message = '';
$messageType = '';

// POST/Redirect/GET pattern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['verification_message'] = 'Invalid request. Please try again.';
        $_SESSION['verification_message_type'] = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $requestId = intval($_POST['request_id'] ?? 0);
        $adminNotes = trim($_POST['admin_notes'] ?? '');
        $adminUser = $auth->getCurrentUser();
        $adminId = $adminUser ? $adminUser['id'] : null;
        if ($action === 'approve_id') {
            $result = $verificationManager->approveVerification($requestId, $adminId, $adminNotes);
        } elseif ($action === 'reject_id') {
            $result = $verificationManager->rejectVerification($requestId, $adminId, $adminNotes);
        } elseif ($action === 'approve_linkedin') {
            $result = $verificationManager->approveVerification($requestId, $adminId, $adminNotes);
        } elseif ($action === 'reject_linkedin') {
            $result = $verificationManager->rejectVerification($requestId, $adminId, $adminNotes);
        } else {
            $result = ['success' => false, 'message' => 'Invalid action'];
        }
        $_SESSION['verification_message'] = $result['message'];
        $_SESSION['verification_message_type'] = $result['success'] ? 'success' : 'error';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

if (isset($_SESSION['verification_message'])) {
    $message = $_SESSION['verification_message'];
    unset($_SESSION['verification_message']);
}
if (isset($_SESSION['verification_message_type'])) {
    $messageType = $_SESSION['verification_message_type'];
    unset($_SESSION['verification_message_type']);
}

// Get verification statistics
$stats = $verificationManager->getVerificationStats();
$pending_count = $stats['id']['pending'] + $stats['linkedin']['pending'];
$approved_count = $stats['id']['approved'] + $stats['linkedin']['approved'];
$rejected_count = $stats['id']['rejected'] + $stats['linkedin']['rejected'];
$total_count = $pending_count + $approved_count + $rejected_count;

// Get pending verification requests
$pendingRequests = $verificationManager->getPendingVerifications();

include '../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-neutral-900 mb-2">Verification Management</h1>
            <p class="text-neutral-600">Review and manage user verification requests</p>
        </div>

        <!-- Status Messages -->
        <?php if ($message): ?>
        <div class="mb-6">
            <div class="<?php echo $messageType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> border px-4 py-3 rounded-lg">
                <?php echo e($message); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow border border-neutral-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-hourglass-half text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-neutral-500">Pending</p>
                        <p class="text-2xl font-bold text-neutral-900"><?php echo $pending_count; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow border border-neutral-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-check-circle text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-neutral-500">Approved</p>
                        <p class="text-2xl font-bold text-neutral-900"><?php echo $approved_count; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow border border-neutral-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-times-circle text-red-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-neutral-500">Rejected</p>
                        <p class="text-2xl font-bold text-neutral-900"><?php echo $rejected_count; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow border border-neutral-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-chart-line text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-neutral-500">Total Requests</p>
                        <p class="text-2xl font-bold text-neutral-900"><?php echo $total_count; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Verification Requests -->
        <div class="bg-white rounded-lg shadow border border-neutral-200">
            <div class="px-6 py-4 border-b border-neutral-200">
                <h2 class="text-lg font-semibold text-neutral-900">Pending Verification Requests</h2>
            </div>
            
            <?php if (empty($pendingRequests)): ?>
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-check text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-neutral-900 mb-2">All caught up!</h3>
                <p class="text-neutral-600">There are no pending verification requests at the moment.</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-neutral-200">
                <?php foreach ($pendingRequests as $request): ?>
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <!-- User Info -->
                            <div class="flex items-center mb-4">
                                <div class="w-10 h-10 bg-neutral-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fa-solid fa-user text-neutral-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-medium text-neutral-900">
                                        <?php echo e($request['first_name'] . ' ' . $request['last_name']); ?>
                                    </h3>
                                    <p class="text-sm text-neutral-600"><?php echo e($request['email']); ?></p>
                                </div>
                                
                                <!-- Verification Type Badge -->
                                <div class="ml-4">
                                    <?php if ($request['verification_type'] === 'id_card'): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <i class="fa-solid fa-id-card mr-1"></i>
                                        ID Verification
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        <i class="fa-brands fa-linkedin mr-1"></i>
                                        LinkedIn Verification
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Request Details -->
                            <div class="bg-neutral-50 rounded-lg p-4 mb-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-neutral-600">
                                            <strong>Submitted:</strong> 
                                            <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                                        </p>
                                        <p class="text-sm text-neutral-600">
                                            <strong>Request ID:</strong> #<?php echo $request['id']; ?>
                                        </p>
                                    </div>
                                    
                                    <?php if ($request['verification_type'] === 'id_card'): ?>
                                    <div>
                                        <p class="text-sm text-neutral-600">
                                            <strong>Verification Status:</strong> 
                                            <span class="capitalize"><?php echo e($request['id_verification_status'] ?? 'not_submitted'); ?></span>
                                        </p>
                                        <?php if (!empty($request['id_verification_notes'])): ?>
                                        <p class="text-sm text-neutral-600">
                                            <strong>Previous Notes:</strong> <?php echo e($request['id_verification_notes']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <div>
                                        <p class="text-sm text-neutral-600">
                                            <strong>LinkedIn Profile:</strong>
                                            <?php if (!empty($request['linkedin_profile'])): ?>
                                            <a href="<?php echo e($request['linkedin_profile']); ?>" target="_blank" 
                                               class="text-blue-600 hover:text-blue-800 ml-1">
                                                View Profile <i class="fa-solid fa-external-link-alt text-xs"></i>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-neutral-400 ml-1">No profile link available</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-sm text-neutral-600">
                                            <strong>Status:</strong> 
                                            <span class="capitalize"><?php echo e($request['linkedin_verification_status'] ?? 'not_submitted'); ?></span>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Document Preview (for ID verification) -->
                            <?php if ($request['verification_type'] === 'id_card'): ?>
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-neutral-900 mb-3">ID Document Images</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php if (!empty($request['id_document_front'])): ?>
                                    <div>
                                        <p class="text-xs text-neutral-600 mb-2">Front Side</p>
                                        <div class="relative inline-block">
                                            <img src="<?php echo BASE_URL . '/' . e($request['id_document_front']); ?>" 
                                                 alt="ID Document Front" 
                                                 class="w-full max-w-xs h-32 object-cover rounded-lg border border-neutral-300 cursor-pointer hover:opacity-80 transition-opacity"
                                                 onclick="openImageModal('<?php echo BASE_URL . '/' . e($request['id_document_front']); ?>', 'ID Document - Front Side')">
                                            <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-0 hover:bg-opacity-10 transition-all rounded-lg cursor-pointer">
                                                <i class="fa-solid fa-search-plus text-white opacity-0 hover:opacity-100 transition-opacity"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($request['id_document_back'])): ?>
                                    <div>
                                        <p class="text-xs text-neutral-600 mb-2">Back Side</p>
                                        <div class="relative inline-block">
                                            <img src="<?php echo BASE_URL . '/' . e($request['id_document_back']); ?>" 
                                                 alt="ID Document Back" 
                                                 class="w-full max-w-xs h-32 object-cover rounded-lg border border-neutral-300 cursor-pointer hover:opacity-80 transition-opacity"
                                                 onclick="openImageModal('<?php echo BASE_URL . '/' . e($request['id_document_back']); ?>', 'ID Document - Back Side')">
                                            <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-0 hover:bg-opacity-10 transition-all rounded-lg cursor-pointer">
                                                <i class="fa-solid fa-search-plus text-white opacity-0 hover:opacity-100 transition-opacity"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (empty($request['id_document_front']) && empty($request['id_document_back'])): ?>
                                    <div class="col-span-2">
                                        <p class="text-sm text-neutral-500 italic">No ID document images available</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Action Form -->
                    <form method="POST" class="mt-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        
                        <div class="mb-4">
                            <label for="admin_notes_<?php echo $request['id']; ?>" 
                                   class="block text-sm font-medium text-neutral-700 mb-2">
                                Admin Notes (optional)
                            </label>
                            <textarea id="admin_notes_<?php echo $request['id']; ?>" name="admin_notes" rows="2"
                                      class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Add any notes about this verification decision..."></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="submit" name="action" 
                                    value="<?php echo $request['verification_type'] === 'id_card' ? 'reject_id' : 'reject_linkedin'; ?>"
                                    class="bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition-colors flex items-center space-x-2"
                                    onclick="return confirm('Are you sure you want to reject this verification request?')">
                                <i class="fa-solid fa-times"></i>
                                <span>Reject</span>
                            </button>
                            <button type="submit" name="action" 
                                    value="<?php echo $request['verification_type'] === 'id_card' ? 'approve_id' : 'approve_linkedin'; ?>"
                                    class="bg-green-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-green-700 transition-colors flex items-center space-x-2"
                                    onclick="return confirm('Are you sure you want to approve this verification request?')">
                                <i class="fa-solid fa-check"></i>
                                <span>Approve</span>
                            </button>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50 flex items-center justify-center">
    <div class="relative max-w-4xl max-h-full p-4">
        <div class="bg-white rounded-lg overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-neutral-200">
                <h3 id="modalTitle" class="text-lg font-medium text-neutral-900">Document Preview</h3>
                <button onclick="closeImageModal()" 
                        class="text-neutral-400 hover:text-neutral-600 text-xl">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <img id="modalImage" src="" alt="Document" class="max-w-full max-h-96 mx-auto rounded-lg">
            </div>
        </div>
    </div>
</div>

<script>
function openImageModal(src, title = 'Document Preview') {
    document.getElementById('modalImage').src = src;
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('imageModal').classList.remove('hidden');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
}

// Close modal when clicking outside the content
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});
</script>

<?php include('../includes/footer.php'); ?>
