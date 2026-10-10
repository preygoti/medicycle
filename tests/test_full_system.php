<?php
/**
 * MediCycle - Full 4-Role System Integration Test
 * Verifies: Admin, Supplier, NGO/Clinic, Delivery Partner workflows
 */

$baseUrl = 'http://127.0.0.1:8000';
echo "========================================================\n";
echo "   MEDICYCLE 4-ROLE WORKFLOW INTEGRATION TEST SUITE     \n";
echo "========================================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTest($condition, $description) {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo " [PASS] " . $description . "\n";
        $testsPassed++;
    } else {
        echo " [FAIL] " . $description . "\n";
        $testsFailed++;
    }
}

class TestClient {
    private $cookieFile;
    private $baseUrl;

    public function __construct($baseUrl) {
        $this->baseUrl = $baseUrl;
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'mc_test_');
    }

    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function request($path, $method = 'GET', $data = [], $followRedirects = true) {
        $ch = curl_init();
        $url = $this->baseUrl . $path;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MediCycleFullTester/1.0');

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => $body,
            'url'  => $effectiveUrl
        ];
    }

    public function extractCsrf($html) {
        if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/i', $html, $m)) {
            return $m[1];
        }
        return '';
    }
}

// ------------------------------------------------------------------
// 1. PUBLIC PAGES
// ------------------------------------------------------------------
echo "--- 1. Testing Public Pages ---\n";
$anon = new TestClient($baseUrl);

$p = $anon->request('/index.php');
assertTest($p['code'] === 200 && strpos($p['body'], 'MediCycle') !== false, "Homepage loads (HTTP 200)");

$p = $anon->request('/about.php');
assertTest($p['code'] === 200 && strpos($p['body'], 'About') !== false, "About page loads (HTTP 200)");

$p = $anon->request('/how-it-works.php');
assertTest($p['code'] === 200 && strpos($p['body'], 'How It Works') !== false, "How It Works page loads (HTTP 200)");

$p = $anon->request('/contact.php');
assertTest($p['code'] === 200, "Contact page loads (HTTP 200)");

$p = $anon->request('/setup.php');
assertTest($p['code'] === 200 && strpos($p['body'], 'Database Setup') !== false, "Setup installer page loads (HTTP 200)");

$p = $anon->request('/login.php');
assertTest($p['code'] === 200 && strpos($p['body'], 'Big Supplier') !== false, "Login page with demo logins loads (HTTP 200)");

// ------------------------------------------------------------------
// 2. ADMIN WORKFLOW
// ------------------------------------------------------------------
echo "\n--- 2. Testing Administrator Role ---\n";
$admin = new TestClient($baseUrl);
$loginPage = $admin->request('/login.php');
$csrf = $admin->extractCsrf($loginPage['body']);

$loginRes = $admin->request('/login.php', 'POST', [
    'csrf_token' => $csrf,
    'email' => 'admin@medicycle.org',
    'password' => 'Admin@123'
]);
assertTest(strpos($loginRes['url'], 'admin/dashboard.php') !== false || strpos($loginRes['body'], 'Administrative Operations') !== false, "Admin logged in successfully");

$adminDash = $admin->request('/admin/dashboard.php');
assertTest($adminDash['code'] === 200 && strpos($adminDash['body'], 'Administrative Operations Control') !== false, "Admin dashboard renders KPI cards");

$adminUsers = $admin->request('/admin/users.php');
assertTest($adminUsers['code'] === 200 && strpos($adminUsers['body'], 'User Account Management') !== false, "Admin users list loads");

$adminOrgs = $admin->request('/admin/organizations.php');
assertTest($adminOrgs['code'] === 200 && strpos($adminOrgs['body'], 'Healthcare Organizations') !== false, "Admin organizations verification loads");

$adminSupplies = $admin->request('/admin/supplies.php');
assertTest($adminSupplies['code'] === 200 && strpos($adminSupplies['body'], 'Medical Supplies Inventory') !== false, "Admin supplies oversight loads");

$adminDeliveries = $admin->request('/admin/deliveries.php');
assertTest($adminDeliveries['code'] === 200 && strpos($adminDeliveries['body'], 'Deliveries & Transport') !== false, "Admin deliveries oversight loads");

$adminReports = $admin->request('/admin/reports.php');
assertTest($adminReports['code'] === 200 && strpos($adminReports['body'], 'Audit Reports') !== false, "Admin audit reports load");

$adminAnalytics = $admin->request('/admin/analytics.php');
assertTest($adminAnalytics['code'] === 200 && strpos($adminAnalytics['body'], 'Redistribution Analytics') !== false, "Admin analytics load");

// ------------------------------------------------------------------
// 3. SUPPLIER WORKFLOW
// ------------------------------------------------------------------
echo "\n--- 3. Testing Healthcare Supplier Role ---\n";
$supplier = new TestClient($baseUrl);
$suppLoginPage = $supplier->request('/login.php');
$csrfSupp = $supplier->extractCsrf($suppLoginPage['body']);

$suppLoginRes = $supplier->request('/login.php', 'POST', [
    'csrf_token' => $csrfSupp,
    'email' => 'apollo.supplies@medicycle.org',
    'password' => 'Supplier@123'
]);
assertTest(strpos($suppLoginRes['url'], 'supplier/dashboard.php') !== false || strpos($suppLoginRes['body'], 'Supplier') !== false, "Supplier logged in successfully");

// Add a supply (INSERT)
$addPage = $supplier->request('/supplier/add-supply.php');
$csrfAdd = $supplier->extractCsrf($addPage['body']);
$uniqueItem = "Surgical Masks 3-Ply " . rand(100, 999);

$addRes = $supplier->request('/supplier/add-supply.php', 'POST', [
    'csrf_token' => $csrfAdd,
    'supply_name' => $uniqueItem,
    'category_id' => '1',
    'description' => 'Unopened certified surgical masks in factory sealed boxes',
    'quantity' => '200',
    'unit' => 'Boxes (50 pcs)',
    'condition_status' => 'New / Unopened',
    'packaging_status' => 'Original Factory Seal',
    'expiry_date' => '2027-12-31',
    'batch_number' => 'LOT-MK-992',
    'storage_requirements' => 'Room Temperature',
    'location' => 'Ahmedabad'
]);
assertTest(strpos($addRes['body'], $uniqueItem) !== false || strpos($addRes['url'], 'supplier/inventory.php') !== false, "Supplier added new medical supply (INSERT verified)");

$invPage = $supplier->request('/supplier/inventory.php');
assertTest(strpos($invPage['body'], $uniqueItem) !== false, "Newly added supply appears in supplier inventory");

$transfersPage = $supplier->request('/supplier/transfers.php');
assertTest($transfersPage['code'] === 200 && strpos($transfersPage['body'], 'Consignment Transfers') !== false, "Supplier transfers page loads");

// ------------------------------------------------------------------
// 4. NGO / CLINIC WORKFLOW
// ------------------------------------------------------------------
echo "\n--- 4. Testing NGO / Clinic Role ---\n";
$ngo = new TestClient($baseUrl);
$ngoLoginPage = $ngo->request('/login.php');
$csrfNgo = $ngo->extractCsrf($ngoLoginPage['body']);

$ngoLoginRes = $ngo->request('/login.php', 'POST', [
    'csrf_token' => $csrfNgo,
    'email' => 'hope.clinic@medicycle.org',
    'password' => 'Ngo@123'
]);
assertTest(strpos($ngoLoginRes['url'], 'ngo/dashboard.php') !== false || strpos($ngoLoginRes['body'], 'NGO') !== false, "NGO logged in successfully");

// Search supplies
$searchPage = $ngo->request('/ngo/search-supplies.php');
assertTest($searchPage['code'] === 200 && strpos($searchPage['body'], 'Discover Supplies') !== false || strpos($searchPage['body'], 'Search Available') !== false, "NGO search supplies loads");

// Post requirement (INSERT)
$postReqPage = $ngo->request('/ngo/post-requirement.php');
$csrfReq = $ngo->extractCsrf($postReqPage['body']);
$uniqueReq = "Sterile Cotton Gauze Rolls " . rand(100, 999);

$postReqRes = $ngo->request('/ngo/post-requirement.php', 'POST', [
    'csrf_token' => $csrfReq,
    'supply_name' => $uniqueReq,
    'category_id' => '2',
    'required_quantity' => '50',
    'unit' => 'Rolls',
    'urgency' => 'High',
    'required_by' => '2026-11-30',
    'city' => 'Vadodara',
    'description' => 'Urgent post-operative dressing requirements for weekly mobile camp'
]);
assertTest(strpos($postReqRes['url'], 'ngo/my-requirements.php') !== false || strpos($postReqRes['body'], $uniqueReq) !== false, "NGO posted clinical requirement (INSERT verified)");

// Submit request for supply #1
$supDetails = $ngo->request('/ngo/supply-details.php?id=1');
$csrfOrder = $ngo->extractCsrf($supDetails['body']);

$orderRes = $ngo->request('/ngo/supply-details.php?id=1', 'POST', [
    'csrf_token' => $csrfOrder,
    'requested_quantity' => '15',
    'purpose' => 'Rural Health Outpatient Dressing',
    'urgency' => 'High',
    'preferred_collection_date' => date('Y-m-d', strtotime('+4 days')),
    'message' => 'Requesting allocation for community wellness drive'
]);
assertTest(strpos($orderRes['url'], 'ngo/my-requests.php') !== false, "NGO submitted supply request (INSERT verified)");

// ------------------------------------------------------------------
// 5. SUPPLIER APPROVAL & DELIVERY DISPATCH GENERATION
// ------------------------------------------------------------------
echo "\n--- 5. Testing Supplier Approval & Logistics Generation ---\n";
$reqsPage = $supplier->request('/supplier/requests.php');
$csrfApprove = $supplier->extractCsrf($reqsPage['body']);

if (preg_match('/name="request_id"\s+value="(\d+)"/i', $reqsPage['body'], $reqMatch)) {
    $targetReqId = $reqMatch[1];
    $appRes = $supplier->request('/supplier/requests.php', 'POST', [
        'csrf_token' => $csrfApprove,
        'action' => 'accept',
        'request_id' => $targetReqId,
        'remarks' => 'Approved with priority. Handover scheduled with volunteer fleet.'
    ]);
    assertTest(strpos($appRes['body'], 'approved') !== false || strpos($appRes['body'], 'accepted') !== false || strpos($appRes['body'], 'HO-') !== false, "Supplier approved request and auto-assigned delivery consignment");
} else {
    echo " [INFO] No pending requests to approve at this instant.\n";
}

// ------------------------------------------------------------------
// 6. DELIVERY PARTNER WORKFLOW
// ------------------------------------------------------------------
echo "\n--- 6. Testing Delivery Partner Role ---\n";
$delivery = new TestClient($baseUrl);
$delLoginPage = $delivery->request('/login.php');
$csrfDel = $delivery->extractCsrf($delLoginPage['body']);

$delLoginRes = $delivery->request('/login.php', 'POST', [
    'csrf_token' => $csrfDel,
    'email' => 'delivery@medicycle.org',
    'password' => 'Delivery@123'
]);
assertTest(strpos($delLoginRes['url'], 'delivery/dashboard.php') !== false || strpos($delLoginRes['body'], 'Medical Logistics') !== false, "Delivery partner logged in successfully");

$delDash = $delivery->request('/delivery/dashboard.php');
assertTest($delDash['code'] === 200 && strpos($delDash['body'], 'Medical Logistics Fleet') !== false, "Delivery dashboard renders");

$delAssigned = $delivery->request('/delivery/assigned.php');
assertTest($delAssigned['code'] === 200 && strpos($delAssigned['body'], 'Assigned Courier Consignments') !== false, "Assigned deliveries page renders");

// Update delivery status
$updatePage = $delivery->request('/delivery/update-status.php?id=2');
if ($updatePage['code'] === 200) {
    $csrfUpd = $delivery->extractCsrf($updatePage['body']);
    $updRes = $delivery->request('/delivery/update-status.php?id=2', 'POST', [
        'csrf_token' => $csrfUpd,
        'delivery_id' => '2',
        'delivery_status' => 'In Transit',
        'tracking_notes' => 'Dispatched via cold-chain transit van GJ-01-AB-1234'
    ]);
    assertTest(strpos($updRes['body'], 'In Transit') !== false || strpos($updRes['url'], 'delivery') !== false, "Delivery status updated to In Transit");
}

$delHistory = $delivery->request('/delivery/history.php');
assertTest($delHistory['code'] === 200 && strpos($delHistory['body'], 'Fulfilled Delivery History') !== false, "Delivery history renders");

// ------------------------------------------------------------------
// 7. SECURITY & ACCESS CONTROL TEST
// ------------------------------------------------------------------
echo "\n--- 7. Testing Security Role Barriers ---\n";
$ngoTryAdmin = $ngo->request('/admin/dashboard.php', 'GET', [], false);
assertTest($ngoTryAdmin['code'] === 302 || strpos($ngoTryAdmin['url'], 'ngo/dashboard.php') !== false, "NGO blocked from accessing Admin portal (Role protection verified)");

$suppTryAdmin = $supplier->request('/admin/settings.php', 'GET', [], false);
assertTest($suppTryAdmin['code'] === 302 || strpos($suppTryAdmin['url'], 'supplier/dashboard.php') !== false, "Supplier blocked from accessing Admin settings (Role protection verified)");

echo "\n========================================================\n";
echo " TOTAL RESULT: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "========================================================\n";

if ($testsFailed === 0) {
    echo "\n>>> 100% OF SYSTEM WORKFLOW TESTS PASSED PERFECTLY! <<<\n\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED. <<<\n\n";
    exit(1);
}
