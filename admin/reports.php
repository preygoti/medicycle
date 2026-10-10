<?php
/**
 * MediCycle - Admin Audit & Redistribution Reports
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$startDate = clean($_GET['start_date'] ?? date('Y-m-01', strtotime('-3 months')));
$endDate = clean($_GET['end_date'] ?? date('Y-m-d'));
$reportType = clean($_GET['report_type'] ?? 'completed_transfers');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="medicycle_report_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Request ID', 'Supply Item', 'Category', 'Donor Facility', 'Recipient Clinic', 'Quantity', 'Unit', 'Waste Avoided (kg)', 'Estimated Value (INR)', 'Completed Date']);

    $stmt = $pdo->prepare("
        SELECT r.id, ms.supply_name, c.category_name, 
               COALESCE(so.organization_name, su.name) as donor,
               COALESCE(no.organization_name, nu.name) as recipient,
               r.requested_quantity, ms.unit,
               COALESCE(im.estimated_waste_avoided, 0) as waste_kg,
               COALESCE(im.estimated_value_saved, 0) as value_inr,
               r.completed_at
        FROM requests r
        JOIN medical_supplies ms ON r.supply_id = ms.id
        JOIN categories c ON ms.category_id = c.id
        JOIN users su ON ms.supplier_id = su.id
        LEFT JOIN organizations so ON su.id = so.user_id
        JOIN users nu ON r.requester_id = nu.id
        LEFT JOIN organizations no ON nu.id = no.user_id
        LEFT JOIN impact_metrics im ON im.request_id = r.id
        WHERE r.status = 'Completed' AND DATE(r.completed_at) BETWEEN ? AND ?
        ORDER BY r.completed_at DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Fetch Report Data
$reportQuery = $pdo->prepare("
    SELECT r.id, ms.supply_name, c.category_name, 
           COALESCE(so.organization_name, su.name) as donor,
           COALESCE(no.organization_name, nu.name) as recipient,
           r.requested_quantity, ms.unit,
           COALESCE(im.estimated_waste_avoided, 0) as waste_kg,
           COALESCE(im.estimated_value_saved, 0) as value_inr,
           r.completed_at
    FROM requests r
    JOIN medical_supplies ms ON r.supply_id = ms.id
    JOIN categories c ON ms.category_id = c.id
    JOIN users su ON ms.supplier_id = su.id
    LEFT JOIN organizations so ON su.id = so.user_id
    JOIN users nu ON r.requester_id = nu.id
    LEFT JOIN organizations no ON nu.id = no.user_id
    LEFT JOIN impact_metrics im ON im.request_id = r.id
    WHERE r.status = 'Completed' AND DATE(r.completed_at) BETWEEN ? AND ?
    ORDER BY r.completed_at DESC
");
$reportQuery->execute([$startDate, $endDate]);
$reportRows = $reportQuery->fetchAll();

$totalRedistributedUnits = array_sum(array_column($reportRows, 'requested_quantity'));
$totalWasteAvoided = array_sum(array_column($reportRows, 'waste_kg'));
$totalValueSaved = array_sum(array_column($reportRows, 'value_inr'));

$pageTitle = 'Redistribution Audit Reports - Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h3 class="fw-bold text-dark mb-1">Official Redistribution Audit Reports</h3>
                <p class="text-muted small mb-0">Auditable transfer receipts, landfill waste diverted and community benefit logs</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-print me-1"></i> Print Report
                </button>
                <a href="<?php echo BASE_URL; ?>/admin/reports.php?export=csv&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-file-csv me-1"></i> Export to CSV
                </a>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white d-print-none">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <label class="small text-muted fw-semibold">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="col-md-4">
                    <label class="small text-muted fw-semibold">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100 py-2">
                        <i class="fas fa-filter me-1"></i> Generate Report
                    </button>
                    <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="btn btn-sm btn-outline-secondary py-2">Reset</a>
                </div>
            </form>
        </div>

        <!-- Summary Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="text-muted small text-uppercase fw-semibold">Consumables Redistributed</div>
                    <div class="fs-4 fw-bold text-teal mt-1"><?php echo number_format($totalRedistributedUnits); ?> items</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="text-muted small text-uppercase fw-semibold">Waste Averted from Landfill</div>
                    <div class="fs-4 fw-bold text-success mt-1"><?php echo number_format($totalWasteAvoided, 2); ?> kg</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="text-muted small text-uppercase fw-semibold">Healthcare Costs Saved</div>
                    <div class="fs-4 fw-bold text-dark mt-1">₹<?php echo number_format($totalValueSaved, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-table text-teal me-2"></i> Completed Redistribution Consignments (<?php echo count($reportRows); ?> records)</h6>
                <span class="text-muted small">Period: <?php echo format_date($startDate); ?> &mdash; <?php echo format_date($endDate); ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Transfer ID</th>
                                <th>Item Transferred</th>
                                <th>Category</th>
                                <th>Donor Supplier</th>
                                <th>Recipient Clinic</th>
                                <th>Volume</th>
                                <th>Waste Saved</th>
                                <th>Cost Value</th>
                                <th>Fulfillment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reportRows)): ?>
                                <?php foreach ($reportRows as $row): ?>
                                    <tr>
                                        <td>#TXN-<?php echo $row['id']; ?></td>
                                        <td class="fw-bold text-dark"><?php echo e($row['supply_name']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo e($row['category_name']); ?></span></td>
                                        <td><?php echo e($row['donor']); ?></td>
                                        <td class="fw-semibold text-teal"><?php echo e($row['recipient']); ?></td>
                                        <td class="fw-bold"><?php echo $row['requested_quantity'] . ' ' . e($row['unit']); ?></td>
                                        <td class="text-success fw-semibold"><?php echo number_format($row['waste_kg'], 2); ?> kg</td>
                                        <td>₹<?php echo number_format($row['value_inr'], 2); ?></td>
                                        <td class="text-muted"><?php echo format_date($row['completed_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No completed transfers found in the selected date range.</td>
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
