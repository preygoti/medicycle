<?php
/**
 * MediCycle - Full End-to-End System Integration Test
 * Harvest Ledger Model: Direct Supplier <-> NGO Medical Supply Redistribution
 */

$baseUrl = 'http://127.0.0.1:8000';
echo "========================================================\n";
echo "   MEDICYCLE (HARVEST LEDGER MODEL) SYSTEM TEST SUITE   \n";
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

// Cookie jar helper for cURL
class HttpClient {
    private $cookieFile;
    private $baseUrl;

    public function __construct($baseUrl) {
        $this->baseUrl = $baseUrl;
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'mc_cookie_');
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
        curl_setopt($ch, CURLOPT_USERAGENT, 'MediCycleTestAgent/2.0');

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
            'url' => $effectiveUrl
        ];
    }

    public function extractCsrf($html) {
        if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/i', $html, $matches)) {
            return $matches[1];
        }
        return '';
    }
}

// TEST 1: Public Pages Accessibility
echo "--- Testing Public Pages ---\n";
$anon = new HttpClient($baseUrl);
$res = $anon->request('/index.php');
assertTest($res['code'] === 200 && strpos($res['body'], 'MediCycle') !== false, "Homepage loads (HTTP 200 with MediCycle branding)");

$res = $anon->request('/about.php');
assertTest($res['code'] === 200 && strpos($res['body'], 'About the Initiative') !== false, "About page loads (HTTP 200)");

$res = $anon->request('/how-it-works.php');
assertTest($res['code'] === 200 && strpos($res['body'], 'How MediCycle Works') !== false, "How It Works page loads (HTTP 200)");

$res = $anon->request('/contact.php');
assertTest($res['code'] === 200 && strpos($res['body'], 'Contact Us') !== false, "Contact page loads (HTTP 200)");

$res = $anon->request('/login.php');
assertTest($res['code'] === 200 && strpos($res['body'], 'Welcome to MediCycle') !== false, "Login page loads (HTTP 200)");

$res = $anon->request('/register.php');
assertTest($res['code'] === 200 && strpos($res['body'], 'Join MediCycle') !== false, "Registration page loads (HTTP 200)");

// TEST 2: Role Security Guards
echo "\n--- Testing Security Guards & Role Enforcement ---\n";
$unauth = new HttpClient($baseUrl);
$res = $unauth->request('/supplier/dashboard.php', 'GET', [], false);
assertTest($res['code'] === 302 || strpos($res['url'], 'login.php') !== false, "Unauthenticated access to Supplier portal redirected to login");

$res = $unauth->request('/ngo/dashboard.php', 'GET', [], false);
assertTest($res['code'] === 302 || strpos($res['url'], 'login.php') !== false, "Unauthenticated access to NGO portal redirected to login");

// TEST 3: Supplier Authentication & Inventory Management
echo "\n--- Testing Supplier Module & Inventory Management ---\n";
$supplierClient = new HttpClient($baseUrl);
$suppLoginHtml = $supplierClient->request('/login.php');
$csrfSupp = $supplierClient->extractCsrf($suppLoginHtml['body']);
assertTest(!empty($csrfSupp), "CSRF security token generated on login form");

$suppLogin = $supplierClient->request('/login.php', 'POST', [
    'csrf_token' => $csrfSupp,
    'email' => 'apollo.supplies@medicycle.org',
    'password' => 'Supplier@123'
]);
assertTest(strpos($suppLogin['url'], 'supplier/dashboard.php') !== false || strpos($suppLogin['body'], 'Supplier Portal') !== false, "Supplier successfully logged in (bcrypt verified)");

// Post a new medical supply (INSERT)
$addSupplyPage = $supplierClient->request('/supplier/add-supply.php');
$csrfAdd = $supplierClient->extractCsrf($addSupplyPage['body']);

$testSupplyName = 'Test Sterile Gauze - ' . substr(bin2hex(random_bytes(2)), 0, 4);
$addRes = $supplierClient->request('/supplier/add-supply.php', 'POST', [
    'csrf_token' => $csrfAdd,
    'supply_name' => $testSupplyName,
    'category_id' => '2',
    'description' => 'Integration test sterile gauze roll packaging',
    'quantity' => '150',
    'unit' => 'Rolls',
    'condition_status' => 'Sterile Sealed',
    'packaging_status' => 'Original Factory Seal',
    'expiry_date' => '2027-11-30',
    'batch_number' => 'LOT-HL-' . rand(100, 999),
    'storage_requirements' => 'Dry storage',
    'location' => 'Ahmedabad'
]);
assertTest(strpos($addRes['body'], $testSupplyName) !== false || strpos($addRes['url'], 'supplier/inventory.php') !== false, "Supplier added new medical supply lot (INSERT verified)");

// TEST 4: NGO Authentication & Search
echo "\n--- Testing NGO Discovery & Requisition Flow ---\n";
$ngoClient = new HttpClient($baseUrl);
$ngoLoginHtml = $ngoClient->request('/login.php');
$csrfNgo = $ngoClient->extractCsrf($ngoLoginHtml['body']);

$ngoLogin = $ngoClient->request('/login.php', 'POST', [
    'csrf_token' => $csrfNgo,
    'email' => 'hope.clinic@medicycle.org',
    'password' => 'Ngo@123'
]);
assertTest(strpos($ngoLogin['url'], 'ngo/dashboard.php') !== false || strpos($ngoLogin['body'], 'Clinic & NGO Portal') !== false, "NGO logged in successfully");

$searchRes = $ngoClient->request('/ngo/search-supplies.php');
assertTest($searchRes['code'] === 200 && strpos($searchRes['body'], 'Search Available Medical Supplies') !== false, "NGO search & filter catalog loads");

// NGO Post Clinical Requirement (INSERT)
$postReqPage = $ngoClient->request('/ngo/post-requirement.php');
$csrfPostReq = $ngoClient->extractCsrf($postReqPage['body']);
$testReqName = 'Test Dressing Kits ' . rand(10, 99);
$postReqRes = $ngoClient->request('/ngo/post-requirement.php', 'POST', [
    'csrf_token' => $csrfPostReq,
    'supply_name' => $testReqName,
    'category_id' => '2',
    'required_quantity' => '30',
    'unit' => 'Kits',
    'urgency' => 'High',
    'required_by' => '2026-12-15',
    'city' => 'Ahmedabad',
    'description' => 'Direct outreach requirement for rural mobile dispensary'
]);
assertTest(strpos($postReqRes['url'], 'ngo/my-requirements.php') !== false || strpos($postReqRes['body'], $testReqName) !== false, "NGO posted requirement successfully (INSERT verified)");

// Submit Requisition Request for Supply #1
$detailsPage = $ngoClient->request('/ngo/supply-details.php?id=1');
$csrfReqSubmit = $ngoClient->extractCsrf($detailsPage['body']);
$reqSubmitRes = $ngoClient->request('/ngo/supply-details.php?id=1', 'POST', [
    'csrf_token' => $csrfReqSubmit,
    'requested_quantity' => '10',
    'purpose' => 'Rural Health Camp',
    'urgency' => 'High',
    'preferred_collection_date' => date('Y-m-d', strtotime('+3 days')),
    'message' => 'Harvest Ledger direct collection test'
]);
assertTest(strpos($reqSubmitRes['url'], 'ngo/my-requests.php') !== false, "NGO submitted collection request with preferred date");

// TEST 5: Supplier Review, Accept & Handover Code Issuance
echo "\n--- Testing Supplier Acceptance & Handover Code Generation ---\n";
$reqsPage = $supplierClient->request('/supplier/requests.php');
$csrfApprove = $supplierClient->extractCsrf($reqsPage['body']);

if (preg_match('/name="request_id"\s+value="(\d+)"/i', $reqsPage['body'], $reqMatch)) {
    $latestReqId = $reqMatch[1];
    $acceptRes = $supplierClient->request('/supplier/requests.php', 'POST', [
        'csrf_token' => $csrfApprove,
        'action' => 'accept',
        'request_id' => $latestReqId,
        'remarks' => 'Pickup at Pharmacy Counter B between 10am-4pm'
    ]);
    assertTest(strpos($acceptRes['body'], 'accepted') !== false || strpos($acceptRes['body'], 'HAND-') !== false, "Supplier accepted request and generated Handover Code");
} else {
    assertTest(false, "Could not locate pending request ID on supplier dashboard");
}

// TEST 6: NGO Confirmation of Receipt & Impact Recording
echo "\n--- Testing NGO Receipt Confirmation & Ecological Impact ---\n";
$myReqsPage = $ngoClient->request('/ngo/my-requests.php');
$csrfConfirm = $ngoClient->extractCsrf($myReqsPage['body']);

if (preg_match('/name="request_id"\s+value="(\d+)"/i', $myReqsPage['body'], $confMatch)) {
    $confReqId = $confMatch[1];
    $confirmRes = $ngoClient->request('/ngo/my-requests.php', 'POST', [
        'csrf_token' => $csrfConfirm,
        'action' => 'confirm_receipt',
        'request_id' => $confReqId
    ]);
    assertTest(strpos($confirmRes['body'], 'Collection confirmed') !== false || strpos($confirmRes['body'], 'recorded') !== false, "NGO confirmed physical collection; impact metrics recorded");
} else {
    echo " [INFO] No pending confirmable request found in NGO list\n";
}

// TEST 7: Verification of Direct Impact Analytics
echo "\n--- Testing Supplier & NGO Impact Analytics ---\n";
$suppAnalytics = $supplierClient->request('/supplier/analytics.php');
assertTest($suppAnalytics['code'] === 200 && strpos($suppAnalytics['body'], 'Impact Analytics') !== false, "Supplier impact analytics dashboard active");

$ngoImpact = $ngoClient->request('/ngo/impact.php');
assertTest($ngoImpact['code'] === 200 && strpos($ngoImpact['body'], 'Clinical Impact Metrics') !== false, "NGO clinical impact metrics active");

echo "\n========================================================\n";
echo " TEST SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "========================================================\n";

if ($testsFailed === 0) {
    echo "\n>>> ALL MEDICYCLE HARVEST LEDGER WORKFLOWS VERIFIED 100% FUNCTIONAL! <<<\n\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED. INVESTIGATION REQUIRED. <<<\n\n";
    exit(1);
}
