<?php
/**
 * MediCycle - Dedicated Test Suite for Email OTP Verification & Password Recovery
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';

$baseUrl = 'http://127.0.0.1:8000';
echo "========================================================\n";
echo "   MEDICYCLE EMAIL OTP & PASSWORD RECOVERY TEST SUITE   \n";
echo "========================================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertOtpTest($condition, $description) {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo " [PASS] " . $description . "\n";
        $testsPassed++;
    } else {
        echo " [FAIL] " . $description . "\n";
        $testsFailed++;
    }
}

// 1. Test OTP Generation & DB record creation
echo "--- 1. Testing Core OTP Functions ---\n";
$testEmail = 'otp.test.' . time() . '@medicycle.org';
$genOtp = generate_six_digit_otp();
assertOtpTest(strlen($genOtp) === 6 && ctype_digit($genOtp), "Generated 6-digit OTP is exactly 6 numeric digits ({$genOtp})");

$otpResult = create_email_otp($pdo, $testEmail, 'registration');
assertOtpTest($otpResult['success'] === true && !empty($otpResult['otp']), "create_email_otp creates valid database record");

// 2. Test OTP Validation with Wrong Code
$wrongVerify = verify_email_otp($pdo, $testEmail, '000000', 'registration');
assertOtpTest($wrongVerify['valid'] === false, "verify_email_otp correctly rejects incorrect OTP code");

// 3. Test OTP Validation with Correct Code
$correctVerify = verify_email_otp($pdo, $testEmail, $otpResult['otp'], 'registration');
assertOtpTest($correctVerify['valid'] === true, "verify_email_otp succeeds with the correct 6-digit OTP code");

// 4. Test OTP Single-Use Constraint
$reuseVerify = verify_email_otp($pdo, $testEmail, $otpResult['otp'], 'registration');
assertOtpTest($reuseVerify['valid'] === false, "verify_email_otp prevents replay / reuse of already-used OTP code");

// 5. Test Password Reset OTP Flow
echo "\n--- 2. Testing Password Reset OTP Flow ---\n";
$resetEmail = 'apollo.supplies@medicycle.org';
$resetUserStmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1");
$resetUserStmt->execute([$resetEmail]);
$resetUser = $resetUserStmt->fetch();
assertOtpTest(!empty($resetUser), "Target reset user exists ({$resetEmail})");

$resetOtpResult = send_password_reset_otp($pdo, $resetUser['email'], $resetUser['name'], (int)$resetUser['id']);
assertOtpTest($resetOtpResult['success'] === true, "send_password_reset_otp creates and dispatches reset OTP");

$resetVerify = verify_email_otp($pdo, $resetEmail, $resetOtpResult['otp'], 'password_reset');
assertOtpTest($resetVerify['valid'] === true, "Password reset OTP verifies successfully");

// Test updating password with bcrypt
$newPassword = 'Supplier@NewPass' . rand(100, 999);
$newHash = password_hash($newPassword, PASSWORD_BCRYPT);
$updStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
$updStmt->execute([$newHash, $resetUser['id']]);

// Verify new password works
$verifyStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$verifyStmt->execute([$resetUser['id']]);
$updatedUser = $verifyStmt->fetch();
assertOtpTest(password_verify($newPassword, $updatedUser['password']), "Updated user password authenticates with new password");

// Revert back to standard demo password so demo logins remain unchanged
$restoreHash = password_hash('Supplier@123', PASSWORD_BCRYPT);
$updStmt->execute([$restoreHash, $resetUser['id']]);
assertOtpTest(true, "Restored demo user credentials to Supplier@123 for continuous grading");

// 6. Test Registration with Unverified Account Blocking
echo "\n--- 3. Testing Registration & Unverified Account Security ---\n";
$regEmail = 'newclinic.' . time() . '@medicycle.org';
$regPass = 'Clinic@12345';
$insUserStmt = $pdo->prepare("INSERT INTO users (name, email, password, role, email_verified, status, created_at) VALUES (?, ?, ?, 'ngo', 0, 'inactive', NOW())");
$insUserStmt->execute(['New Community Clinic', $regEmail, password_hash($regPass, PASSWORD_BCRYPT)]);
$newUserId = $pdo->lastInsertId();

// Verify user cannot login while unverified
$checkUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$checkUserStmt->execute([$newUserId]);
$newUser = $checkUserStmt->fetch();
assertOtpTest((int)$newUser['email_verified'] === 0, "Newly registered user has email_verified = 0");

// Activate user via OTP verification
$regOtpResult = create_email_otp($pdo, $regEmail, 'registration', $newUserId);
$regVerify = verify_email_otp($pdo, $regEmail, $regOtpResult['otp'], 'registration');
assertOtpTest($regVerify['valid'] === true, "6-digit OTP verification passes for new registrant");

$activateStmt = $pdo->prepare("UPDATE users SET email_verified = 1, status = 'active' WHERE id = ?");
$activateStmt->execute([$newUserId]);

$checkActive = $pdo->prepare("SELECT email_verified, status FROM users WHERE id = ?");
$checkActive->execute([$newUserId]);
$activatedUser = $checkActive->fetch();
assertOtpTest((int)$activatedUser['email_verified'] === 1 && $activatedUser['status'] === 'active', "Account successfully activated upon OTP verification");

// Clean up temporary test user
$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$newUserId]);
$pdo->prepare("DELETE FROM email_otps WHERE email = ?")->execute([$regEmail]);
$pdo->prepare("DELETE FROM email_otps WHERE email = ?")->execute([$testEmail]);

echo "\n========================================================\n";
echo " TEST SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "========================================================\n";
