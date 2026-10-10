<?php
/**
 * MediCycle - Admin Category Management (Full CRUD)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

// Handle Actions: Create, Update, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch.');
    } else {
        $action = clean($_POST['action'] ?? '');

        // 1. CREATE CATEGORY (INSERT)
        if ($action === 'create') {
            $name = clean($_POST['category_name'] ?? '');
            $desc = clean($_POST['description'] ?? '');
            $icon = clean($_POST['icon'] ?? 'fa-box-medical');

            if (empty($name)) {
                set_flash('danger', 'Category name is required.');
            } else {
                try {
                    $ins = $pdo->prepare("INSERT INTO categories (category_name, description, icon, status) VALUES (?, ?, ?, 'active')");
                    $ins->execute([$name, $desc, $icon]);
                    set_flash('success', "Category '{$name}' created successfully!");
                    header('Location: ' . BASE_URL . '/admin/categories.php');
                    exit;
                } catch (PDOException $e) {
                    set_flash('danger', 'Category name must be unique.');
                }
            }
        }

        // 2. UPDATE CATEGORY (UPDATE)
        elseif ($action === 'update') {
            $catId = (int)($_POST['category_id'] ?? 0);
            $name = clean($_POST['category_name'] ?? '');
            $desc = clean($_POST['description'] ?? '');
            $icon = clean($_POST['icon'] ?? 'fa-box-medical');

            if (empty($name)) {
                set_flash('danger', 'Category name is required.');
            } else {
                try {
                    $upd = $pdo->prepare("UPDATE categories SET category_name = ?, description = ?, icon = ? WHERE id = ?");
                    $upd->execute([$name, $desc, $icon, $catId]);
                    set_flash('success', "Category #{$catId} updated successfully!");
                    header('Location: ' . BASE_URL . '/admin/categories.php');
                    exit;
                } catch (PDOException $e) {
                    set_flash('danger', 'Error updating category: ' . $e->getMessage());
                }
            }
        }

        // 3. DELETE CATEGORY (DELETE)
        elseif ($action === 'delete') {
            $catId = (int)($_POST['category_id'] ?? 0);
            try {
                // Check if supplies exist
                $chk = $pdo->prepare("SELECT COUNT(*) FROM medical_supplies WHERE category_id = ?");
                $chk->execute([$catId]);
                if ($chk->fetchColumn() > 0) {
                    set_flash('danger', 'Cannot delete category: active medical supply listings are classified under this category.');
                } else {
                    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$catId]);
                    set_flash('success', "Category #{$catId} deleted.");
                }
                header('Location: ' . BASE_URL . '/admin/categories.php');
                exit;
            } catch (PDOException $e) {
                set_flash('danger', 'Database error: ' . $e->getMessage());
            }
        }
    }
}

// Fetch categories with active supply item counts
$categories = $pdo->query("
    SELECT c.*, COUNT(s.id) as item_count 
    FROM categories c 
    LEFT JOIN medical_supplies s ON c.id = s.category_id 
    GROUP BY c.id 
    ORDER BY c.category_name ASC
")->fetchAll();

$pageTitle = 'Manage Categories - Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Eligible Medical Categories</h3>
                <p class="text-muted small mb-0">Manage healthcare consumables taxonomy (Strictly non-drug medical equipment & wound care)</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus-circle me-1"></i> Add Category
                </button>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($categories as $cat): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="rounded-3 p-2 text-teal" style="background:#ccfbf1; color:#0f766e;">
                                <i class="fas <?php echo htmlspecialchars($cat['icon']); ?> fs-4"></i>
                            </div>
                            <span class="badge bg-light text-dark border">
                                <?php echo $cat['item_count']; ?> Listings
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1"><?php echo e($cat['category_name']); ?></h6>
                        <p class="text-muted small mb-3 flex-grow-1"><?php echo e($cat['description']); ?></p>
                        
                        <div class="d-flex justify-content-end gap-2 border-top pt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="openEditCat(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                <i class="fas fa-edit me-1"></i> Edit
                            </button>
                            <?php if ($cat['item_count'] == 0): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete category?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Supply Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Category Name</label>
                    <input type="text" name="category_name" class="form-control form-control-sm" required placeholder="e.g. Suture Kits & Wound Closure">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">FontAwesome Icon Class</label>
                    <input type="text" name="icon" class="form-control form-control-sm" value="fa-box-medical" required>
                    <small class="text-muted">e.g. fa-bandage, fa-syringe, fa-head-side-mask, fa-kit-medical</small>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Scope Description</label>
                    <textarea name="description" class="form-control form-control-sm" rows="3" required placeholder="Describe eligible medical items under this category..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="category_id" id="edit_cat_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Category Name</label>
                    <input type="text" name="category_name" id="edit_cat_name" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Icon Class</label>
                    <input type="text" name="icon" id="edit_cat_icon" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" id="edit_cat_desc" class="form-control form-control-sm" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCat(cat) {
    document.getElementById('edit_cat_id').value = cat.id;
    document.getElementById('edit_cat_name').value = cat.category_name;
    document.getElementById('edit_cat_icon').value = cat.icon;
    document.getElementById('edit_cat_desc').value = cat.description;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
