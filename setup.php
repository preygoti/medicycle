<?php
/**
 * MediCycle - Database Setup & Reset Utility
 * College OEP Installer & Database Verification Tool
 */
require_once __DIR__ . '/config/config.php';

$message = '';
$messageType = '';
$installedTables = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'initialize') {
    try {
        $sqlFile = __DIR__ . '/database/medicycle_db.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("Database schema file not found at: {$sqlFile}");
        }

        $sql = file_get_contents($sqlFile);
        
        // Execute multi-query using PDO
        $pdo->exec($sql);
        
        $message = "Database successfully initialized! All tables, constraints, categories, and realistic demo accounts have been loaded.";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Installation error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Fetch current table status
try {
    $stmt = $pdo->query("SHOW TABLES");
    $installedTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $installedTables = [];
}

$pageTitle = 'Database Setup & Installer';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-teal mb-0"><i class="fas fa-database me-2"></i> MediCycle Database Setup</h5>
                    <span class="badge bg-light text-secondary border">OEP Installer</span>
                </div>
                <div class="card-body p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-info-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <p class="text-muted">
                        This utility sets up the MySQL database schema for <strong>MediCycle</strong>, creates foreign key constraints,
                        and seeds initial demo data for Admin, Hospital Supplier, Clinic NGO, and Delivery Partner roles.
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <h6 class="fw-bold mb-2"><i class="fas fa-server text-teal me-2"></i> Database Connection</h6>
                                <div class="small text-muted">Host: <code><?php echo htmlspecialchars($db_host ?? '127.0.0.1'); ?>:<?php echo htmlspecialchars($db_port ?? '3306'); ?></code></div>
                                <div class="small text-muted">Database: <code><?php echo htmlspecialchars($db_name ?? 'medicycle_db'); ?></code></div>
                                <div class="small text-success mt-1"><i class="fas fa-circle-check me-1"></i> PDO Connected Successfully</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <h6 class="fw-bold mb-2"><i class="fas fa-table-list text-primary me-2"></i> Installed Tables (<?php echo count($installedTables); ?>)</h6>
                                <div class="small text-muted text-truncate">
                                    <?php echo !empty($installedTables) ? implode(', ', $installedTables) : 'No tables installed yet.'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" onsubmit="return confirm('Initialize / reset the database with fresh schema and demo accounts? Existing data will be reset.');">
                        <input type="hidden" name="action" value="initialize">
                        <button type="submit" class="btn btn-primary px-4 py-2">
                            <i class="fas fa-rotate me-2"></i> Initialize / Reset Database Schema
                        </button>
                        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-secondary px-4 py-2 ms-2">
                            Go to Sign In
                        </a>
                    </form>

                    <hr class="my-4">

                    <h6 class="fw-bold mb-3"><i class="fas fa-users-gear text-teal me-2"></i> Demo User Accounts for OEP Presentation</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Role</th>
                                    <th>Organization Name</th>
                                    <th>Email</th>
                                    <th>Password</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-danger">Admin</span></td>
                                    <td>System Administrator</td>
                                    <td><code>admin@medicycle.org</code></td>
                                    <td><code>Admin@123</code></td>
                                    <td><a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-primary btn-sm py-0">Login</a></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-primary">Supplier</span></td>
                                    <td>Apollo Central Hospital</td>
                                    <td><code>apollo.supplies@medicycle.org</code></td>
                                    <td><code>Supplier@123</code></td>
                                    <td><a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-primary btn-sm py-0">Login</a></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">NGO / Clinic</span></td>
                                    <td>Hope Rural Health Mission</td>
                                    <td><code>hope.clinic@medicycle.org</code></td>
                                    <td><code>Ngo@123</code></td>
                                    <td><a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-primary btn-sm py-0">Login</a></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning text-dark">Delivery</span></td>
                                    <td>SwiftCare Volunteer Logistics</td>
                                    <td><code>delivery@medicycle.org</code></td>
                                    <td><code>Delivery@123</code></td>
                                    <td><a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-primary btn-sm py-0">Login</a></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
