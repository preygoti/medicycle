<?php
/**
 * MediCycle - NGO My Posted Requirements (READ, DELETE, Smart Matching)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];

// Handle DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch.');
    } else {
        $delId = (int)($_POST['requirement_id'] ?? 0);
        try {
            $delStmt = $pdo->prepare("DELETE FROM requirements WHERE id = ? AND organization_id = ?");
            $delStmt->execute([$delId, $userId]);
            set_flash('success', 'Requirement removed successfully.');
        } catch (PDOException $e) {
            error_log("Delete requirement error: " . $e->getMessage());
            set_flash('danger', 'Error deleting requirement.');
        }
        header('Location: ' . BASE_URL . '/ngo/my-requirements.php');
        exit;
    }
}

// Fetch requirements
$stmt = $pdo->prepare("SELECT r.*, c.category_name 
                       FROM requirements r 
                       JOIN categories c ON r.category_id = c.id 
                       WHERE r.organization_id = ? 
                       ORDER BY r.created_at DESC");
$stmt->execute([$userId]);
$requirements = $stmt->fetchAll();

// Fetch available supplies for match checking
$suppliesStmt = $pdo->query("SELECT s.*, c.category_name, o.organization_name, o.city 
                             FROM medical_supplies s 
                             JOIN categories c ON s.category_id = c.id 
                             JOIN organizations o ON s.supplier_id = o.user_id 
                             WHERE s.status = 'Available' AND s.expiry_date > CURDATE()");
$availableSupplies = $suppliesStmt->fetchAll();

$pageTitle = 'My Clinical Requirements';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">My Clinical Requirements</h3>
                <p class="text-muted small mb-0">Track your facility's active requests and view matching supply lots</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/ngo/post-requirement.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> Post New Need
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item Needed</th>
                                <th>Category</th>
                                <th>Quantity Needed</th>
                                <th>Urgency</th>
                                <th>Required By</th>
                                <th>Smart Matches</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($requirements)): ?>
                                <?php foreach ($requirements as $r): ?>
                                    <?php
                                        // Calculate match count
                                        $matchCount = 0;
                                        $bestMatchSupply = null;
                                        foreach ($availableSupplies as $s) {
                                            $m = calculate_match_score($s, $r);
                                            if ($m['is_recommended']) {
                                                $matchCount++;
                                                if (!$bestMatchSupply || $m['score'] > $bestMatchSupply['score']) {
                                                    $bestMatchSupply = ['supply' => $s, 'score' => $m['score'], 'summary' => $m['summary']];
                                                }
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark d-block"><?php echo e($r['supply_name']); ?></span>
                                            <?php if (!empty($r['description'])): ?>
                                                <span class="text-muted small"><?php echo e(substr($r['description'], 0, 50)) . '...'; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border"><?php echo e($r['category_name']); ?></span></td>
                                        <td class="fw-bold text-teal"><?php echo number_format($r['required_quantity']) . ' ' . e($r['unit']); ?></td>
                                        <td><?php echo priority_badge($r['urgency']); ?></td>
                                        <td class="small text-muted"><?php echo format_date($r['required_by']); ?></td>
                                        <td>
                                            <?php if ($matchCount > 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" data-bs-toggle="modal" data-bs-target="#matchModal<?php echo $r['id']; ?>">
                                                    <i class="fas fa-brain me-1"></i> <?php echo $matchCount; ?> Available
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">No match yet</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo status_badge($r['status']); ?></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/ngo/edit-requirement.php?id=<?php echo $r['id']; ?>" class="btn btn-outline-primary" title="Edit Requirement">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="<?php echo BASE_URL; ?>/ngo/my-requirements.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this requirement post?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="requirement_id" value="<?php echo $r['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- MATCH MODAL -->
                                    <?php if ($bestMatchSupply): ?>
                                        <div class="modal fade" id="matchModal<?php echo $r['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold text-success"><i class="fas fa-brain me-2"></i>Smart Match Found!</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="p-3 bg-light rounded-3 mb-3">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <h6 class="fw-bold text-dark mb-0"><?php echo e($bestMatchSupply['supply']['supply_name']); ?></h6>
                                                                <span class="badge bg-success">Match Score: <?php echo $bestMatchSupply['score']; ?>%</span>
                                                            </div>
                                                            <div class="small text-muted mb-2">Hospital: <?php echo e($bestMatchSupply['supply']['organization_name']); ?> (<?php echo e($bestMatchSupply['supply']['city']); ?>)</div>
                                                            <div class="small text-dark mb-1">Available: <strong><?php echo $bestMatchSupply['supply']['quantity'] . ' ' . e($bestMatchSupply['supply']['unit']); ?></strong></div>
                                                            <div class="small text-dark">Expiry: <strong><?php echo format_date($bestMatchSupply['supply']['expiry_date']); ?></strong></div>
                                                        </div>
                                                        <div class="p-2 bg-success bg-opacity-10 text-success rounded small mb-2">
                                                            <i class="fas fa-check-circle me-1"></i> <?php echo e($bestMatchSupply['summary']); ?>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                                                        <a href="<?php echo BASE_URL; ?>/ngo/supply-details.php?id=<?php echo $bestMatchSupply['supply']['id']; ?>" class="btn btn-primary btn-sm">
                                                            Request This Batch
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-clipboard-list fs-2 text-secondary mb-2 d-block"></i>
                                        You haven't posted any clinical requirements yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
