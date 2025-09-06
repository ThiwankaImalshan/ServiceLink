<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Require admin role BEFORE any output
$auth->requireRole('admin', '/index.php');

// Get database connection
$db = getDB();

$pageTitle = 'Category Management • ServiceLink Admin';
$pageDescription = 'Manage service categories and their settings.';

// Handle category actions
if ($_POST) {
    if (isset($_POST['action']) && verifyCSRFToken($_POST['csrf_token'])) {
        switch ($_POST['action']) {
            case 'add_category':
                if (isset($_POST['name']) && isset($_POST['slug']) && isset($_POST['icon'])) {
                    try {
                        $stmt = $db->prepare("INSERT INTO categories (name, slug, icon, description, background_image, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_POST['name'],
                            $_POST['slug'],
                            $_POST['icon'],
                            $_POST['description'] ?? '',
                            $_POST['background_image'] ?? null,
                            (int)($_POST['sort_order'] ?? 0),
                            isset($_POST['active']) ? 1 : 0
                        ]);
                        setFlashMessage('Category added successfully', 'success');
                    } catch (PDOException $e) {
                        setFlashMessage('Error adding category: ' . $e->getMessage(), 'error');
                    }
                }
                break;
                
            case 'edit_category':
                if (isset($_POST['category_id']) && isset($_POST['name']) && isset($_POST['slug']) && isset($_POST['icon'])) {
                    try {
                        $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, description = ?, background_image = ?, sort_order = ?, active = ? WHERE id = ?");
                        $stmt->execute([
                            $_POST['name'],
                            $_POST['slug'],
                            $_POST['icon'],
                            $_POST['description'] ?? '',
                            $_POST['background_image'] ?? null,
                            (int)($_POST['sort_order'] ?? 0),
                            isset($_POST['active']) ? 1 : 0,
                            $_POST['category_id']
                        ]);
                        setFlashMessage('Category updated successfully', 'success');
                    } catch (PDOException $e) {
                        setFlashMessage('Error updating category: ' . $e->getMessage(), 'error');
                    }
                }
                break;
                
            case 'delete_category':
                if (isset($_POST['category_id'])) {
                    try {
                        // Check if category has providers
                        $check_stmt = $db->prepare("SELECT COUNT(*) FROM providers WHERE category_id = ?");
                        $check_stmt->execute([$_POST['category_id']]);
                        $provider_count = $check_stmt->fetchColumn();
                        
                        if ($provider_count > 0) {
                            setFlashMessage("Cannot delete category: {$provider_count} providers are using this category", 'error');
                        } else {
                            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                            $stmt->execute([$_POST['category_id']]);
                            setFlashMessage('Category deleted successfully', 'success');
                        }
                    } catch (PDOException $e) {
                        setFlashMessage('Error deleting category: ' . $e->getMessage(), 'error');
                    }
                }
                break;
                
            case 'toggle_active':
                if (isset($_POST['category_id'])) {
                    try {
                        $stmt = $db->prepare("UPDATE categories SET active = NOT active WHERE id = ?");
                        $stmt->execute([$_POST['category_id']]);
                        setFlashMessage('Category status updated', 'success');
                    } catch (PDOException $e) {
                        setFlashMessage('Error updating status: ' . $e->getMessage(), 'error');
                    }
                }
                break;
        }
        
        header("Location: categories.php");
        exit();
    }
}

// Get categories with provider count
try {
    $stmt = $db->prepare("
        SELECT c.*, 
               COUNT(p.id) as provider_count 
        FROM categories c 
        LEFT JOIN providers p ON c.id = p.category_id 
        GROUP BY c.id 
        ORDER BY c.sort_order ASC, c.name ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    setFlashMessage('Error loading categories: ' . $e->getMessage(), 'error');
}

// Get category for editing if specified
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_category = $stmt->fetch();
    } catch (PDOException $e) {
        setFlashMessage('Error loading category for editing: ' . $e->getMessage(), 'error');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <meta name="description" content="<?php echo e($pageDescription); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd',
                            400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8',
                            800: '#1e40af', 900: '#1e3a8a', 950: '#172554'
                        },
                        secondary: {
                            50: '#fdf4ff', 100: '#fae8ff', 200: '#f5d0fe', 300: '#f0abfc',
                            400: '#e879f9', 500: '#d946ef', 600: '#c026d3', 700: '#a21caf',
                            800: '#86198f', 900: '#701a75', 950: '#4a044e'
                        },
                        neutral: {
                            50: '#fafafa', 100: '#f5f5f5', 200: '#e5e5e5', 300: '#d4d4d4',
                            400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040',
                            800: '#262626', 900: '#171717', 950: '#0a0a0a'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="../assets/img/favicon_io/site.webmanifest">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-neutral-50 text-neutral-900">

<div class="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-50">
    <?php include '../components/admin-nav.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-neutral-900">Category Management</h1>
            <p class="text-neutral-600 mt-2">Manage service categories and their settings</p>
        </div>

        <!-- Flash Messages -->
        <?php displayFlashMessages(); ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Category Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-neutral-200 p-6">
                    <h2 class="text-xl font-semibold text-neutral-900 mb-6">
                        <?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?>
                    </h2>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_category ? 'edit_category' : 'add_category'; ?>">
                        <?php if ($edit_category): ?>
                            <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
                        <?php endif; ?>
                        
                        <div>
                            <label for="name" class="block text-sm font-medium text-neutral-700 mb-2">Category Name *</label>
                            <input type="text" id="name" name="name" required
                                   value="<?php echo $edit_category ? e($edit_category['name']) : ''; ?>"
                                   class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="slug" class="block text-sm font-medium text-neutral-700 mb-2">Slug *</label>
                            <input type="text" id="slug" name="slug" required
                                   value="<?php echo $edit_category ? e($edit_category['slug']) : ''; ?>"
                                   class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <p class="text-xs text-neutral-500 mt-1">URL-friendly version (e.g., "web-design")</p>
                        </div>
                        
                        <div>
                            <label for="icon" class="block text-sm font-medium text-neutral-700 mb-2">Icon Class *</label>
                            <input type="text" id="icon" name="icon" required
                                   value="<?php echo $edit_category ? e($edit_category['icon']) : ''; ?>"
                                   placeholder="fa-solid fa-hammer"
                                   class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <p class="text-xs text-neutral-500 mt-1">Font Awesome icon class</p>
                        </div>
                        
                        <div>
                            <label for="description" class="block text-sm font-medium text-neutral-700 mb-2">Description</label>
                            <textarea id="description" name="description" rows="3"
                                      class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                      placeholder="Brief description of the category"><?php echo $edit_category ? e($edit_category['description']) : ''; ?></textarea>
                        </div>
                        
                        <div>
                            <label for="background_image" class="block text-sm font-medium text-neutral-700 mb-2">Background Image URL</label>
                            <input type="url" id="background_image" name="background_image"
                                   value="<?php echo $edit_category ? e($edit_category['background_image']) : ''; ?>"
                                   placeholder="https://images.unsplash.com/..."
                                   class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="sort_order" class="block text-sm font-medium text-neutral-700 mb-2">Sort Order</label>
                            <input type="number" id="sort_order" name="sort_order" min="0"
                                   value="<?php echo $edit_category ? $edit_category['sort_order'] : '0'; ?>"
                                   class="w-full px-4 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="active" value="1" 
                                       <?php echo (!$edit_category || $edit_category['active']) ? 'checked' : ''; ?>
                                       class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                                <span class="ml-2 text-sm text-neutral-700">Active</span>
                            </label>
                        </div>
                        
                        <div class="flex space-x-3">
                            <button type="submit" 
                                    class="flex-1 bg-primary-600 hover:bg-primary-700 text-white py-2 px-4 rounded-lg transition-colors">
                                <?php echo $edit_category ? 'Update Category' : 'Add Category'; ?>
                            </button>
                            
                            <?php if ($edit_category): ?>
                                <a href="categories.php" 
                                   class="flex-1 text-center bg-neutral-200 hover:bg-neutral-300 text-neutral-700 py-2 px-4 rounded-lg transition-colors">
                                    Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Categories List -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-neutral-200 overflow-hidden">
                    <div class="p-6 border-b border-neutral-200">
                        <h2 class="text-xl font-semibold text-neutral-900">
                            Categories (<?php echo count($categories); ?>)
                        </h2>
                    </div>

                    <?php if (empty($categories)): ?>
                        <div class="p-12 text-center">
                            <i class="fa-solid fa-folder-open text-4xl text-neutral-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-neutral-500 mb-2">No categories found</h3>
                            <p class="text-neutral-400">Add your first category using the form</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-neutral-200">
                            <?php foreach ($categories as $category): ?>
                            <div class="p-6 hover:bg-neutral-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center">
                                            <i class="<?php echo e($category['icon']); ?> text-primary-600 text-lg"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-medium text-neutral-900">
                                                <?php echo e($category['name']); ?>
                                                <?php if (!$category['active']): ?>
                                                    <span class="ml-2 inline-flex px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                                        Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </h3>
                                            <p class="text-sm text-neutral-500">
                                                Slug: <code class="bg-neutral-100 px-2 py-1 rounded"><?php echo e($category['slug']); ?></code>
                                                • <?php echo $category['provider_count']; ?> provider(s)
                                                • Sort: <?php echo $category['sort_order']; ?>
                                            </p>
                                            <?php if ($category['description']): ?>
                                                <p class="text-sm text-neutral-600 mt-1"><?php echo e($category['description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        <!-- Toggle Active -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                            <button type="submit" 
                                                    class="<?php echo $category['active'] ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700'; ?> p-2"
                                                    title="<?php echo $category['active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fa-solid fa-<?php echo $category['active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Edit -->
                                        <a href="categories.php?edit=<?php echo $category['id']; ?>" 
                                           class="text-primary-600 hover:text-primary-700 p-2"
                                           title="Edit category">
                                            <i class="fa-solid fa-edit"></i>
                                        </a>
                                        
                                        <!-- Delete -->
                                        <?php if ($category['provider_count'] == 0): ?>
                                        <form method="POST" class="inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this category?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-700 p-2" title="Delete category">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-neutral-400 p-2" title="Cannot delete: has providers">
                                            <i class="fa-solid fa-lock"></i>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-generate slug from name
document.getElementById('name').addEventListener('input', function() {
    const slug = this.value
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
    document.getElementById('slug').value = slug;
});
</script>

</body>
</html>
