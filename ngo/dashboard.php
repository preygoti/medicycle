<?php
/**
 * MediCycle - NGO / Clinic Dashboard
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];

try {
    // 1. Metrics Counts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM requirements WHERE organization_id = ? AND status = 'Active'");
    $stmt->execute([$userId]);
    $activeRequirements = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE requester_id = ?");
    $stmt->execute([$userId]);
    $totalRequests = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE requester_id = ? AND status IN ('Accepted', 'Ready for Handover')");
    $stmt->execute([$userId]);
    $approvedRequests = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE requester_id = ? AND status IN ('Completed', 'Received')");
    $stmt->execute([$userId]);
    $completedDeliveries = $stmt->fetchColumn();

    // 2. Fetch Active Requirements for Matchmaking
    $stmt = $pdo->prepare("SELECT * FROM requirements WHERE organization_id = ? AND status = 'Active'");
    $stmt->execute([$userId]);
    $myReqs = $stmt->fetchAll();

    // 3. Smart Matching: Find top recommended supplies matching active requirements
    $availableSuppliesStmt = $pdo->query("SELECT s.*, c.category_name, o.organization_name as supplier_name, o.city 
                                          FROM medical_supplies s 
                                          JOIN categories c ON s.category_id = c.id 
                                          JOIN organizations o ON s.supplier_id = o.user_id 
                                          WHERE s.status = 'Available' AND s.expiry_date > CURDATE()");
    $availableSupplies = $availableSuppliesStmt->fetchAll();

    $smartMatches = [];
    if (!empty($myReqs)) {
        foreach ($myReqs as $req) {
            foreach ($availableSupplies as $sup) {
                $match = calculate_match_score($sup, $req);
                if ($match['is_recommended']) {
                    $smartMatches[] = [
                        'supply' => $sup,
                        'requirement' => $req,
                        'score' => $match['score'],
                        'summary' => $match['summary']
                    ];
                }
            }
        }
        // Sort matches by highest score
        usort($smartMatches, fn($a, $b) => $b['score'] <=> $a['score']);
        $smartMatches = array_slice($smartMatches, 0, 4);
    }

    // 4. Recent Requests
    $stmt = $pdo->prepare("SELECT r.*, s.supply_name, s.unit, d.tracking_number, d.delivery_status 
                           FROM requests r 
                           JOIN medical_supplies s ON r.supply_id = s.id 
                           LEFT JOIN deliveries d ON r.id = d.request_id 
                           WHERE r.requester_id = ? 
                           ORDER BY r.requested_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recentRequests = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("NGO dashboard error: " . $e->getMessage());
    $activeRequirements = $totalRequests = $approvedRequests = $completedDeliveries = 0;
    $smartMatches = [];
    $recentRequests = [];
}

$pageTitle = 'NGO / Clinic Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Clinic & NGO Portal</h3>
                <p class="text-muted small mb-0">Overview for <?php echo e($_SESSION['org_name']); ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> Search Supplies
                </a>
                <a href="<?php echo BASE_URL; ?>/ngo/post-requirement.php" class="btn btn-outline-secondary">
                    <i class="fas fa-bullhorn me-1"></i> Post Requirement
                </a>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-teal"><?php echo number_format($activeRequirements); ?></div>
                        <div class="stat-label">Active Needs Posted</div>
                    </div>
                    <div class="stat-icon teal"><i class="fas fa-clipboard-list"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number"><?php echo number_format($totalRequests); ?></div>
                        <div class="stat-label">Total Requests</div>
                    </div>
                    <div class="stat-icon blue"><i class="fas fa-hands-helping"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-warning"><?php echo number_format($approvedRequests); ?></div>
                        <div class="stat-label">Ready / In Handover</div>
                    </div>
                    <div class="stat-icon amber"><i class="fas fa-handshake"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-success"><?php echo number_format($completedDeliveries); ?></div>
                        <div class="stat-label">Completed Receipts</div>
                    </div>
                    <div class="stat-icon green"><i class="fas fa-check-double"></i></div>
                </div>
            </div>
        </div>

        <!-- SMART AI MODULE: Recommended Matches Highlight -->
        <?php if (!empty($smartMatches)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="match-badge me-2"><i class="fas fa-brain"></i> AI Matchmaking</span>
                        <span class="fw-bold text-dark">Recommended Supplies For Your Clinic</span>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="small text-decoration-none">Search All</a>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <?php foreach ($smartMatches as $m): ?>
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0"><?php echo e($m['supply']['supply_name']); ?></h6>
                                            <span class="text-muted small">Donor: <?php echo e($m['supply']['supplier_name']); ?> (<?php echo e($m['supply']['city']); ?>)</span>
                                        </div>
                                        <span class="badge bg-success bg-opacity-75 text-white">Match: <?php echo $m['score']; ?>%</span>
                                    </div>
                                    <div class="small text-secondary mb-2">
                                        <strong>Available:</strong> <?php echo $m['supply']['quantity'] . ' ' . e($m['supply']['unit']); ?> | 
                                        <strong>Expiry:</strong> <?php echo format_date($m['supply']['expiry_date']); ?>
                                    </div>
                                    <div class="p-2 bg-white rounded border small text-success mb-3" style="font-size:0.78rem;">
                                        <i class="fas fa-check-circle me-1"></i> <?php echo e($m['summary']); ?>
                                    </div>
                                    <div class="mt-auto">
                                        <a href="<?php echo BASE_URL; ?>/ngo/supply-details.php?id=<?php echo $m['supply']['id']; ?>" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-hand-holding-heart me-1"></i> Request This Matched Batch
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Requests Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-inbox text-teal me-2" style="color:#0f766e;"></i>My Recent Requisitions</h6>
                <a href="<?php echo BASE_URL; ?>/ngo/my-requests.php" class="small text-decoration-none">View All Requests</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item Requested</th>
                                <th>Quantity</th>
                                <th>Requested Date</th>
                                <th>Status</th>
                                <th>Tracking #</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentRequests)): ?>
                                <?php foreach ($recentRequests as $r): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark d-block"><?php echo e($r['supply_name']); ?></span>
                                            <span class="text-muted small"><?php echo e($r['purpose'] ?? 'Clinical outreach'); ?></span>
                                        </td>
                                        <td class="fw-semibold"><?php echo $r['requested_quantity'] . ' ' . e($r['unit']); ?></td>
                                        <td class="small text-muted"><?php echo format_date($r['requested_at']); ?></td>
                                        <td><?php echo status_badge($r['status']); ?></td>
                                        <td>
                                            <?php if (!empty($r['tracking_number'])): ?>
                                                <code><?php echo e($r['tracking_number']); ?></code>
                                                <div class="small text-muted" style="font-size:0.72rem;"><?php echo e($r['delivery_status']); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted small">Not dispatched</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo BASE_URL; ?>/ngo/request-details.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted small">
                                        You haven't requested any medical supplies yet.<br>
                                        <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="btn btn-sm btn-primary mt-2">Explore Available Supplies</a>
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
