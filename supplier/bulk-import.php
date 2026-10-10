<?php
/**
 * MediCycle - Bulk CSV Supply Import & Batch Ingestion
 * Allows suppliers to upload large spreadsheets of eligible medical consumables
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];
$errors = [];
$successCount = 0;
$skippedCount = 0;
$importedItems = [];

// Fetch user default location
$orgStmt = $pdo->prepare("SELECT city FROM organizations WHERE user_id = ? LIMIT 1");
$orgStmt->execute([$userId]);
$userCity = $orgStmt->fetchColumn() ?: 'Ahmedabad';

// Map categories by name (lowercase) to category ID
$catRows = $pdo->query("SELECT id, category_name FROM categories")->fetchAll();
$categoryMap = [];
foreach ($catRows as $cat) {
    $categoryMap[strtolower(trim($cat['category_name']))] = (int)$cat['id'];
}
$defaultCatId = !empty($catRows) ? (int)$catRows[0]['id'] : 1;

// Handle Sample CSV Download
if (isset($_GET['action']) && $_GET['action'] === 'download_sample') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="medicycle_supply_sample_template.csv"');
    
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'supply_name',
        'category_name',
        'quantity',
        'unit',
        'condition_status',
        'packaging_status',
        'expiry_date',
        'batch_number',
        'storage_requirements',
        'location',
        'description'
    ]);
    fputcsv($out, [
        'Sterile Nitrile Examination Gloves (M)',
        'Personal Protective Equipment (PPE)',
        '250',
        'Boxes (100 pcs)',
        'New / Unopened',
        'Original Factory Seal',
        date('Y-m-d', strtotime('+18 months')),
        'LOT-GLV-8821',
        'Cool and Dry (15-25°C)',
        $userCity,
        'Powder-free surgical exam nitrile gloves'
    ]);
    fputcsv($out, [
        '3-Ply Surgical Earloop Face Masks',
        'Personal Protective Equipment (PPE)',
        '500',
        'Boxes (50 pcs)',
        'New / Unopened',
        'Original Factory Seal',
        date('Y-m-d', strtotime('+24 months')),
        'LOT-MSK-4402',
        'Room Temperature',
        $userCity,
        'BFE >= 98% splash resistant surgical masks'
    ]);
    fputcsv($out, [
        'Elastic Crepe Compression Bandage 10cm',
        'Wound Care & Dressing Consumables',
        '120',
        'Packs',
        'Sterile Sealed',
        'Tamper Evident Packaging',
        date('Y-m-d', strtotime('+12 months')),
        'LOT-BND-9011',
        'Room Temperature',
        $userCity,
        'Washable fast-edge crepe compression bandages'
    ]);
    fclose($out);
    exit;
}

// Handle CSV File Upload Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please try again.';
    } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed. Please choose a valid CSV file.';
    } else {
        $fileTmp = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExt !== 'csv') {
            $errors[] = 'Invalid file type. Please upload a spreadsheet formatted as a comma-separated CSV (.csv).';
        } else {
            $handle = fopen($fileTmp, 'r');
            if ($handle === false) {
                $errors[] = 'Unable to read the uploaded CSV file.';
            } else {
                // Parse header row
                $header = fgetcsv($handle);
                if (!$header) {
                    $errors[] = 'The uploaded CSV file is empty.';
                } else {
                    $headerMap = [];
                    foreach ($header as $idx => $colName) {
                        $cleanCol = strtolower(trim(str_replace([' ', '-', '_'], '', $colName)));
                        $headerMap[$cleanCol] = $idx;
                    }

                    $rowNum = 1;
                    $pdo->beginTransaction();
                    try {
                        $insertStmt = $pdo->prepare("INSERT INTO medical_supplies 
                            (supplier_id, category_id, supply_name, description, quantity, unit, condition_status, packaging_status, expiry_date, batch_number, storage_requirements, location, status, priority_score, priority_level, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available', ?, ?, NOW())");

                        while (($row = fgetcsv($handle)) !== false) {
                            $rowNum++;
                            // Skip completely empty lines
                            if (empty(array_filter($row))) continue;

                            // Extract fields
                            $sName = trim($row[$headerMap['supplyname'] ?? 0] ?? '');
                            $cName = trim($row[$headerMap['categoryname'] ?? 1] ?? '');
                            $qty = (int)($row[$headerMap['quantity'] ?? 2] ?? 0);
                            $unit = trim($row[$headerMap['unit'] ?? 3] ?? 'Packs');
                            $cond = trim($row[$headerMap['conditionstatus'] ?? 4] ?? 'New / Unopened');
                            $pkg = trim($row[$headerMap['packagingstatus'] ?? 5] ?? 'Original Factory Seal');
                            $expDate = trim($row[$headerMap['expirydate'] ?? 6] ?? '');
                            $batch = trim($row[$headerMap['batchnumber'] ?? 7] ?? 'BATCH-' . date('Ymd') . '-' . $rowNum);
                            $storage = trim($row[$headerMap['storagerequirements'] ?? 8] ?? 'Room Temperature (15-25°C)');
                            $loc = trim($row[$headerMap['location'] ?? 9] ?? $userCity);
                            $desc = trim($row[$headerMap['description'] ?? 10] ?? 'Bulk imported medical consumable lot.');

                            // Fallback checks
                            if (empty($sName) || $qty <= 0) {
                                $skippedCount++;
                                continue;
                            }

                            // Expiry date validation
                            if (empty($expDate) || strtotime($expDate) <= time()) {
                                $expDate = date('Y-m-d', strtotime('+12 months')); // Safety default to prevent crash
                            }

                            // Match category ID
                            $catId = $defaultCatId;
                            $searchCat = strtolower($cName);
                            foreach ($categoryMap as $k => $id) {
                                if (str_contains($searchCat, $k) || str_contains($k, $searchCat)) {
                                    $catId = $id;
                                    break;
                                }
                            }

                            // Priority calculation
                            $priority = calculate_supply_priority_score($expDate, $cond, $qty);

                            // Execute insertion
                            $insertStmt->execute([
                                $userId,
                                $catId,
                                $sName,
                                $desc,
                                $qty,
                                $unit,
                                $cond,
                                $pkg,
                                $expDate,
                                $batch,
                                $storage,
                                $loc,
                                $priority['score'],
                                $priority['level']
                            ]);

                            $successCount++;
                            $importedItems[] = [
                                'name' => $sName,
                                'qty' => $qty,
                                'unit' => $unit,
                                'batch' => $batch,
                                'expiry' => $expDate
                            ];
                        }

                        $pdo->commit();
                        fclose($handle);

                        if ($successCount > 0) {
                            set_flash('success', "Successfully imported <strong>{$successCount}</strong> medical supply items via CSV!");
                        } else {
                            $errors[] = "No valid records were imported. Please check your CSV column formats.";
                        }

                    } catch (Exception $e) {
                        $pdo->rollBack();
                        if (is_resource($handle)) fclose($handle);
                        error_log("Bulk import error: " . $e->getMessage());
                        $errors[] = "Error processing CSV data: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

$pageTitle = 'Bulk CSV Import - Medical Supplies';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="mb-4">
            <h3 class="fw-bold text-dark mb-1">Bulk Medical Supply Ingestion</h3>
            <div class="heading-accent-line start" style="width: 45px; height: 3px; margin: 0.35rem 0 0.5rem;"></div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm mb-4">
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- 1. CSV Column Specifications (Full Width - Zero Horizontal Scroll) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <span class="rounded-circle p-2 d-inline-flex" style="background:#ccfbf1; color:#0f766e;">
                        <i class="fas fa-list-check"></i>
                    </span>
                    CSV Column Specifications &amp; Ingestion Format
                </h5>
                <a href="<?php echo BASE_URL; ?>/supplier/bulk-import.php?action=download_sample" class="btn btn-outline-teal btn-sm" style="color:#0f766e; border-color:#0f766e;">
                    <i class="fas fa-download me-1"></i> Download Pre-Filled Sample Template (.csv)
                </a>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    Ensure your spreadsheet column headers match the exact specifications below. Unrecognized columns are safely skipped.
                </p>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 18%;">Column Header</th>
                                <th style="width: 12%;">Required</th>
                                <th style="width: 25%;">Accepted Format / Rule</th>
                                <th style="width: 45%;">Example Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code class="fw-bold text-teal">supply_name</code></td>
                                <td><span class="badge bg-danger">Required</span></td>
                                <td class="text-muted">Item title / description (max 150 chars)</td>
                                <td class="fw-medium text-dark">Sterile Nitrile Examination Gloves (M)</td>
                            </tr>
                            <tr>
                                <td><code class="fw-bold text-teal">category_name</code></td>
                                <td><span class="badge bg-secondary">Auto-Mapped</span></td>
                                <td class="text-muted">Matches closest active category name</td>
                                <td class="fw-medium text-dark">Personal Protective Equipment (PPE)</td>
                            </tr>
                            <tr>
                                <td><code class="fw-bold text-teal">quantity</code></td>
                                <td><span class="badge bg-danger">Required</span></td>
                                <td class="text-muted">Positive whole number (&gt; 0)</td>
                                <td class="fw-medium text-dark">250</td>
                            </tr>
                            <tr>
                                <td><code class="fw-bold text-teal">unit</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td class="text-muted">Packaging unit (Defaults to 'Units')</td>
                                <td class="fw-medium text-dark">Boxes (100 pcs)</td>
                            </tr>
                            <tr>
                                <td><code class="fw-bold text-teal">expiry_date</code></td>
                                <td><span class="badge bg-danger">Required</span></td>
                                <td class="text-muted">Valid future date format: <code>YYYY-MM-DD</code></td>
                                <td class="fw-medium text-dark"><?php echo date('Y-m-d', strtotime('+18 months')); ?></td>
                            </tr>
                            <tr>
                                <td><code class="fw-bold text-teal">batch_number</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td class="text-muted">Manufacturer LOT / Batch number</td>
                                <td class="fw-medium text-dark">LOT-GLV-8821</td>
                            </tr>
                            <tr>
                                <td><code class="fw-bold text-teal">location</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td class="text-muted">Pickup city (Defaults to your facility city)</td>
                                <td class="fw-medium text-dark"><?php echo e($userCity); ?></td>
                            </tr>
                            <tr>
                                <td><code class="fw-bold text-teal">description</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td class="text-muted">Additional notes or packaging details</td>
                                <td class="fw-medium text-dark">Powder-free, micro-textured fingertip surgical gloves</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info py-2 px-3 small mt-3 mb-0 d-flex align-items-center gap-2">
                    <i class="fas fa-circle-info text-info fs-5"></i>
                    <div>
                        <strong>Pro-Tip:</strong> Click the <strong>Download Pre-Filled Sample Template</strong> button above to get an already formatted CSV file you can open in Excel, fill with your supplies, and upload below.
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Option for Adding / Uploading File (Directly Below Column Specifications) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <span class="rounded-circle p-2 d-inline-flex" style="background:#ccfbf1; color:#0f766e;">
                        <i class="fas fa-cloud-arrow-up"></i>
                    </span>
                    Upload &amp; Ingest Supply CSV File
                </h5>
            </div>
            <div class="card-body p-4 p-md-5">
                <form action="<?php echo BASE_URL; ?>/supplier/bulk-import.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="p-4 border rounded-3 bg-light text-center mb-4" style="border-style: dashed !important; border-width: 2px !important; border-color: #cbd5e1 !important;">
                        <div class="mb-2">
                            <i class="fas fa-file-csv fs-1" style="color:#0f766e;"></i>
                        </div>
                        <label for="csv_file" class="d-block fw-bold text-dark mb-1 fs-6 cursor-pointer">
                            Select or Drag &amp; Drop Your .CSV Spreadsheet
                        </label>
                        <p class="text-muted small mb-3">Accepted format: <code>.csv</code> (UTF-8, comma separated) &bull; Maximum file size: 5MB</p>
                        
                        <div class="col-md-6 mx-auto">
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                            <div class="invalid-feedback">Please select a valid CSV spreadsheet file.</div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span class="small text-muted">
                            <i class="fas fa-shield-halved text-success me-1"></i> Strictly non-drug consumables only (PPE, wound care, sterile kits).
                        </span>
                        <button type="submit" class="btn btn-primary px-4 py-2 shadow-sm fw-semibold">
                            <i class="fas fa-cloud-arrow-up me-1"></i> Parse &amp; Ingest Supplies
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($importedItems)): ?>
            <!-- Results table -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="fas fa-check-circle text-success me-2"></i>Recently Ingested Batches (<?php echo count($importedItems); ?>)</h6>
                    <a href="<?php echo BASE_URL; ?>/supplier/inventory.php" class="btn btn-teal text-white btn-sm" style="background:#0f766e;">Go to Inventory</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Supply Name</th>
                                    <th>Quantity</th>
                                    <th>Batch Number</th>
                                    <th>Expiration Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($importedItems as $item): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?php echo e($item['name']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo e($item['qty'] . ' ' . $item['unit']); ?></span></td>
                                        <td><code><?php echo e($item['batch']); ?></code></td>
                                        <td><i class="far fa-calendar text-muted me-1"></i><?php echo e($item['expiry']); ?></td>
                                        <td><span class="badge bg-success-subtle text-success border border-success-subtle">Available</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
