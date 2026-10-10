<?php
/**
 * MediCycle - Admin Requirements Oversight (NGO Demand & Smart Matching)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$search = clean($_GET['search'] ?? '');
$statusFilter = clean($_GET['status'] ?? '');
$urgencyFilter = clean($_GET['urgency'] ?? '');

// Handle status updates or deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch.');
    } else {
        $action = clean($_POST['action'] ?? '');
        $reqId = (int)($_POST['requirement_id'] ?? 0);

        if ($action === 'status') {
            $newStatus = clean($_POST['new_status'] ?? 'Active');
            $upd = $pdo->prepare("UPDATE requirements SET status = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$newStatus, $reqId]);
            set_flash('success', "Requirement #{$reqId} status updated to {$newStatus}.");
            header('Location: ' . BASE_URL . '/admin/requirements.php');
            exit;
        } elseif ($action === 'delete') {
            $del = $pdo->prepare("DELETE FROM requirements WHERE id = ?");
            $del->execute([$reqId]);
            set_flash('success', "Requirement #{$reqId} removed.");
            header('Location: ' . BASE_URL . '/admin/requirements.php');
            exit;
        }
    }
}

// Build Search Query
$sql = "SELECT r.*, u.name as ngo_contact, o.organization_name, c.category_name 
        FROM requirements r 
        JOIN users u ON r.organization_id = u.id 
        LEFT JOIN organizations o ON u.id = o.user_id 
        JOIN categories c ON r.category_id = c.id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (r.supply_name LIKE ? OR r.city LIKE ? OR o.organization_name LIKE ? OR u.name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($statusFilter)) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($urgencyFilter)) {
    $sql .= " AND r.urgency = ?";
    $params[] = $urgencyFilter;
}

$sql .= " ORDER BY (r.urgency = 'Critical') DESC, (r.urgency = 'High') DESC, r.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requirements = $stmt->fetchAll();

// Available supplies for match recommendation modal
$availSupplies = $pdo->query("SELECT s.*, c.category_name, u.name as supplier_name, o.organization_name as supplier_org 
                              FROM medical_supplies s 
                              JOIN categories c ON s.category_id = c.id 
                              JOIN users u ON s.supplier_id = u.id 
                              LEFT JOIN organizations o ON u.id = o.user_id 
                              WHERE s.status = 'Available' AND s.quantity > 0")->fetchAll();

$pageTitle = 'Requirements Oversight - Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Clinic & NGO Supply Requisitions</h3>
                <p class="text-muted small mb-0">Monitor verified health clinic needs, critical shortages, and run smart matchmaking</p>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by required item, clinic, city..." value="<?php echo e($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="urgency" class="form-select form-select-sm">
                        <option value="">All Urgency Levels</option>
                        <option value="Critical" <?php echo $urgencyFilter === 'Critical' ? 'selected' : ''; ?>>Critical Priority</option>
                        <option value="High" <?php echo $urgencyFilter === 'High' ? 'selected' : ''; ?>>High Priority</option>
                        <option value="Medium" <?php echo $urgencyFilter === 'Medium' ? 'selected' : ''; ?>>Medium Priority</option>
                        <option value="Low" <?php echo $urgencyFilter === 'Low' ? 'selected' : ''; ?>>Low Priority</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Fulfilled" <?php echo $statusFilter === 'Fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                        <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-teal text-white w-100" style="background:#0f766e;">Filter</button>
                    <a href="<?php echo BASE_URL; ?>/admin/requirements.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Requirements Table -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-clipboard-list text-teal me-2"></i> Health Requisition Demands (<?php echo count($requirements); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Item Demanded</th>
                                <th>Category</th>
                                <th>NGO / Health Clinic</th>
                                <th>Required Qty</th>
                                <th>Urgency</th>
                                <th>Required By</th>
                                <th>Status</th>
                                <th class="text-end">Smart Match & Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($requirements)): ?>
                                <?php foreach ($requirements as $req): ?>
                                    <tr>
                                        <td>#<?php echo $req['id']; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($req['supply_name']); ?></div>
                                            <small class="text-muted"><?php echo e(mb_strimwidth($req['description'] ?? '', 0, 50, '...')); ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?php echo e($req['category_name']); ?></span></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($req['organization_name'] ?? $req['ngo_contact']); ?></div>
                                            <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo e($req['city']); ?></small>
                                        </td>
                                        <td><span class="fw-bold text-teal"><?php echo $req['required_quantity'] . ' ' . e($req['unit']); ?></span></td>
                                        <td><?php echo priority_badge($req['urgency']); ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?php echo format_date($req['required_by']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $req['status'] === 'Active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo e($req['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-info py-0" onclick="showMatches(<?php echo htmlspecialchars(json_encode($req)); ?>)">
                                                <i class="fas fa-brain me-1"></i> Smart Match
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this requirement?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="requirement_id" value="<?php echo $req['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 ms-1"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No requirements recorded.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Smart Match Modal -->
<div class="modal fade" id="smartMatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-brain text-teal me-2"></i> Algorithmic Supply Matches</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="small text-muted text-uppercase fw-semibold">Matching Target Requisition</div>
                    <h5 class="fw-bold text-dark mb-1" id="match_target_name"></h5>
                    <div class="small text-secondary" id="match_target_meta"></div>
                </div>

                <h6 class="fw-bold mb-2">Recommended Available Supply Stock:</h6>
                <div id="match_results_container">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
const allAvailableSupplies = <?php echo json_encode($availSupplies); ?>;

function showMatches(req) {
    document.getElementById('match_target_name').textContent = req.supply_name + ' (' + req.required_quantity + ' ' + req.unit + ')';
    document.getElementById('match_target_meta').textContent = 'Clinic: ' + (req.organization_name || req.ngo_contact) + ' | Location: ' + req.city + ' | Urgency: ' + req.urgency;

    const container = document.getElementById('match_results_container');
    container.innerHTML = '';

    // Calculate score for each available supply using same algorithm
    const scored = allAvailableSupplies.map(sup => {
        let score = 0;
        let reasons = [];

        // Category
        if (parseInt(sup.category_id) === parseInt(req.category_id)) {
            score += 35;
            reasons.push('Same category match (' + sup.category_name + ')');
        }
        // Name keyword
        const sWords = sup.supply_name.toLowerCase().split(' ');
        const rWords = req.supply_name.toLowerCase().split(' ');
        const common = sWords.filter(w => rWords.includes(w) && w.length > 2);
        if (common.length > 0) {
            score += 25;
            reasons.push('Keyword match: ' + common.join(', '));
        }
        // City proximity
        if (sup.city && req.city && sup.city.toLowerCase() === req.city.toLowerCase()) {
            score += 20;
            reasons.push('Same city (' + sup.city + ') - rapid courier transit');
        } else {
            score += 5;
        }
        // Quantity adequacy
        if (parseInt(sup.quantity) >= parseInt(req.required_quantity)) {
            score += 10;
            reasons.push('Full quantity available in single consignment');
        } else {
            score += 5;
            reasons.push('Partial fulfillment available (' + sup.quantity + ' in stock)');
        }
        // Urgency
        if (['High', 'Critical'].includes(req.urgency)) {
            score += 10;
            reasons.push('Urgent medical priority match');
        }

        return {
            supply: sup,
            score: Math.min(100, score),
            reasons: reasons
        };
    }).sort((a, b) => b.score - a.score);

    if (scored.length === 0) {
        container.innerHTML = '<div class="alert alert-warning small">No available medical supplies currently listed.</div>';
    } else {
        scored.forEach(item => {
            const s = item.supply;
            const badgeClass = item.score >= 70 ? 'bg-success' : (item.score >= 50 ? 'bg-warning text-dark' : 'bg-secondary');
            
            const card = document.createElement('div');
            card.className = 'card border mb-2 p-3 shadow-none';
            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold text-dark fs-6">${s.supply_name}</div>
                        <div class="small text-muted">Supplier: ${s.supplier_org || s.supplier_name} | City: ${s.city} | In Stock: <span class="fw-bold text-teal">${s.quantity} ${s.unit}</span></div>
                        <div class="small text-success mt-1"><i class="fas fa-check me-1"></i>${item.reasons.join(' &bull; ')}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge ${badgeClass} fs-6">${item.score}% Match</span>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
    }

    new bootstrap.Modal(document.getElementById('smartMatchModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
