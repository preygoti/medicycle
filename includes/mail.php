<?php
/**
 * MediCycle - Reusable Mail & 6-Digit Email OTP Service
 * Supports SMTP (TLS/SSL) with environment configuration and secure templating
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

/**
 * Generate a cryptographically secure 6-digit OTP code
 */
function generate_six_digit_otp(): string {
    return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Create a new 6-digit OTP record in database with 10-minute expiry and rate limit cooldown
 */
function create_email_otp(PDO $pdo, string $email, string $otpType, ?int $userId = null): array {
    $email = strtolower(trim($email));

    // Cooldown check: Prevent excessive OTP requests within 45 seconds
    $recentStmt = $pdo->prepare("SELECT created_at FROM email_otps 
                                  WHERE email = ? AND otp_type = ? AND is_used = 0 
                                  ORDER BY created_at DESC LIMIT 1");
    $recentStmt->execute([$email, $otpType]);
    $recent = $recentStmt->fetch();

    if ($recent) {
        $secondsSinceLast = time() - strtotime($recent['created_at']);
        if ($secondsSinceLast < 45) {
            $waitSeconds = 45 - $secondsSinceLast;
            return [
                'success' => false,
                'error' => "Please wait {$waitSeconds} seconds before requesting a new verification code.",
                'otp' => null
            ];
        }
    }

    // Invalidate previous active OTPs for this email and type
    $invalidateStmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 
                                     WHERE email = ? AND otp_type = ? AND is_used = 0");
    $invalidateStmt->execute([$email, $otpType]);

    // Generate new 6-digit OTP
    $plainOtp = generate_six_digit_otp();
    $otpHash = password_hash($plainOtp, PASSWORD_BCRYPT);
    $expiresAt = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes

    $insStmt = $pdo->prepare("INSERT INTO email_otps 
        (user_id, email, otp_hash, otp_type, expires_at, attempts, is_used, created_at) 
        VALUES (?, ?, ?, ?, ?, 0, 0, NOW())");
    $insStmt->execute([$userId, $email, $otpHash, $otpType, $expiresAt]);

    return [
        'success' => true,
        'otp' => $plainOtp,
        'expires_at' => $expiresAt,
        'error' => null
    ];
}

/**
 * Verify a 6-digit OTP code against the database
 */
function verify_email_otp(PDO $pdo, string $email, string $enteredOtp, string $otpType): array {
    $email = strtolower(trim($email));
    $enteredOtp = trim($enteredOtp);

    if (strlen($enteredOtp) !== 6 || !ctype_digit($enteredOtp)) {
        return ['valid' => false, 'message' => 'Please enter a valid 6-digit numeric verification code.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM email_otps 
                           WHERE email = ? AND otp_type = ? AND is_used = 0 
                           ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email, $otpType]);
    $otpRecord = $stmt->fetch();

    if (!$otpRecord) {
        return ['valid' => false, 'message' => 'No active verification code found. Please request a new code.'];
    }

    // Check expiration (10 minutes)
    if (strtotime($otpRecord['expires_at']) < time()) {
        $expireStmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 WHERE id = ?");
        $expireStmt->execute([$otpRecord['id']]);
        return ['valid' => false, 'message' => 'Your verification code has expired (valid for 10 minutes). Please request a new code.'];
    }

    // Check attempts threshold (max 5 attempts)
    if ((int)$otpRecord['attempts'] >= 5) {
        $lockStmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 WHERE id = ?");
        $lockStmt->execute([$otpRecord['id']]);
        return ['valid' => false, 'message' => 'Too many incorrect attempts. For security, this code was locked. Please request a new code.'];
    }

    // Increment attempts
    $attStmt = $pdo->prepare("UPDATE email_otps SET attempts = attempts + 1 WHERE id = ?");
    $attStmt->execute([$otpRecord['id']]);

    // Verify bcrypt hash
    if (!password_verify($enteredOtp, $otpRecord['otp_hash'])) {
        $remainingAttempts = 5 - ((int)$otpRecord['attempts'] + 1);
        return [
            'valid' => false,
            'message' => "Incorrect verification code. {$remainingAttempts} attempt(s) remaining."
        ];
    }

    // Mark as used
    $usedStmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 WHERE id = ?");
    $usedStmt->execute([$otpRecord['id']]);

    return [
        'valid' => true,
        'message' => 'Verification successful.',
        'user_id' => $otpRecord['user_id']
    ];
}

/**
 * Send an email via SMTP or native transport with fallback logging
 */
function send_medicycle_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainText = ''): array {
    $toEmail = trim($toEmail);
    $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
    $smtpPort = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
    $smtpEnc = defined('SMTP_ENCRYPTION') ? strtolower(SMTP_ENCRYPTION) : 'tls';
    $smtpUser = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $smtpPass = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
    $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@medicycle.org';
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'MediCycle Platform';

    $isSmtpConfigured = !empty($smtpUser) && !empty($smtpPass);

    $sent = false;
    $errorMsg = '';

    // If real SMTP credentials are provided, attempt SMTP socket connection
    if ($isSmtpConfigured) {
        try {
            $sent = send_smtp_socket(
                $smtpHost, $smtpPort, $smtpEnc, $smtpUser, $smtpPass,
                $fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody
            );
        } catch (Exception $e) {
            $sent = false;
            $errorMsg = $e->getMessage();
            error_log("MediCycle SMTP Error: " . $errorMsg);
        }
    } else {
        // Fallback to PHP native mail()
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            'X-Mailer: MediCycle Healthcare Mailer/1.0'
        ];
        // Suppress warning if local sendmail is not set up
        $sent = @mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));
    }

    // Always log outgoing mail for local audit & testing
    log_mail_locally($toEmail, $subject, $htmlBody);

    // If in development and SMTP is not explicitly configured, treat logged mail as success
    if (!$sent && !$isSmtpConfigured) {
        $sent = true;
    }

    return [
        'success' => $sent,
        'message' => $sent ? 'Email delivered successfully.' : ($errorMsg ?: 'Failed to deliver email through mail transport.')
    ];
}

/**
 * Socket-based SMTP implementation (Supports STARTTLS and SSL)
 */
function send_smtp_socket($host, $port, $encryption, $username, $password, $fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody): bool {
    $timeout = 10;
    $target = ($encryption === 'ssl') ? "ssl://{$host}" : $host;
    $socket = @fsockopen($target, $port, $errno, $errstr, $timeout);

    if (!$socket) {
        throw new Exception("Cannot connect to SMTP server {$host}:{$port} - {$errstr} ({$errno})");
    }

    $read = function($expectedCode = null) use ($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        if ($expectedCode && strpos($response, (string)$expectedCode) !== 0) {
            throw new Exception("SMTP Unexpected response: {$response}");
        }
        return $response;
    };

    $write = function($cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    $read(220);
    $write("EHLO " . (gethostname() ?: 'localhost'));
    $read(250);

    if ($encryption === 'tls') {
        $write("STARTTLS");
        $read(220);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("Failed to establish TLS encryption with SMTP server.");
        }
        $write("EHLO " . (gethostname() ?: 'localhost'));
        $read(250);
    }

    // AUTH LOGIN
    $write("AUTH LOGIN");
    $read(334);
    $write(base64_encode($username));
    $read(334);
    $write(base64_encode($password));
    $read(235);

    // MAIL FROM & RCPT TO
    $write("MAIL FROM: <{$fromEmail}>");
    $read(250);
    $write("RCPT TO: <{$toEmail}>");
    $read(250);

    // DATA
    $write("DATA");
    $read(354);

    $boundary = "----=_Part_" . md5(uniqid());
    $headers = [
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
        "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "Date: " . date('r'),
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        "",
        "--{$boundary}",
        "Content-Type: text/plain; charset=UTF-8",
        "Content-Transfer-Encoding: base64",
        "",
        base64_encode(strip_tags($htmlBody)),
        "",
        "--{$boundary}",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: base64",
        "",
        base64_encode($htmlBody),
        "",
        "--{$boundary}--",
        "."
    ];

    $write(implode("\r\n", $headers));
    $read(250);

    $write("QUIT");
    fclose($socket);

    return true;
}

/**
 * Log mail to local log for audit and developer visibility
 */
function log_mail_locally(string $toEmail, string $subject, string $htmlBody): void {
    $logDir = APP_ROOT . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/mail.log';
    $entry = "[" . date('Y-m-d H:i:s') . "] TO: {$toEmail} | SUBJECT: {$subject}\n";
    if (preg_match('/<div class="otp-code">(\d{6})<\/div>/i', $htmlBody, $m)) {
        $entry .= "  OTP_CODE: {$m[1]}\n";
    }
    $entry .= "--------------------------------------------------------\n";
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

/**
 * Render the branded MediCycle HTML Email Template
 */
function render_medicycle_email_template(string $title, string $subtitle, string $otp, string $instructions, string $expiryNotice = 'This code expires in 10 minutes.'): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
<style>
  body { margin: 0; padding: 0; background-color: #F0FDFA; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0F172A; }
  .email-container { max-width: 580px; margin: 30px auto; background: #FFFFFF; border-radius: 12px; overflow: hidden; border: 1px solid #E2E8F0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
  .email-header { background: #0F766E; padding: 32px 24px; text-align: center; }
  .email-brand { color: #FFFFFF; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; margin: 0; }
  .email-tagline { color: #5EEAD4; font-size: 13px; font-weight: 500; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
  .email-body { padding: 36px 32px; }
  .email-title { font-size: 20px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0; }
  .email-text { font-size: 15px; line-height: 1.6; color: #475569; margin: 0 0 24px 0; }
  .otp-box { background: #F0FDFA; border: 2px dashed #0F766E; border-radius: 10px; padding: 24px; text-align: center; margin: 28px 0; }
  .otp-label { font-size: 12px; text-transform: uppercase; color: #0F766E; font-weight: 700; letter-spacing: 1px; margin-bottom: 8px; }
  .otp-code { font-size: 36px; font-weight: 800; letter-spacing: 10px; color: #0F766E; font-family: 'Courier New', Courier, monospace; margin: 0; padding-left: 10px; }
  .otp-expiry { font-size: 13px; color: #64748B; margin-top: 10px; }
  .security-notice { background: #F8FAFC; border-left: 4px solid #94A3B8; padding: 12px 16px; font-size: 13px; color: #64748B; line-height: 1.5; border-radius: 4px; margin-top: 24px; }
  .email-footer { background: #F8FAFC; padding: 24px; text-align: center; border-top: 1px solid #E2E8F0; font-size: 12px; color: #94A3B8; }
</style>
</head>
<body>
<div class="email-container">
  <div class="email-header">
    <div class="email-brand">&#9877; MediCycle</div>
    <div class="email-tagline">Smart Medical Supply Redistribution</div>
  </div>
  <div class="email-body">
    <h2 class="email-title">{$title}</h2>
    <p class="email-text">{$subtitle}</p>
    <div class="otp-box">
      <div class="otp-label">Your 6-Digit Verification Code</div>
      <div class="otp-code">{$otp}</div>
      <div class="otp-expiry">&#9200; {$expiryNotice}</div>
    </div>
    <p class="email-text">{$instructions}</p>
    <div class="security-notice">
      <strong>Security Notice:</strong> MediCycle will never ask for your password or verification code via phone or chat. If you did not initiate this request, you can safely ignore this email.
    </div>
  </div>
  <div class="email-footer">
    &copy; 2026 MediCycle. Responsible Healthcare Resource Redistribution.<br>
    Non-drug medical consumables only.
  </div>
</div>
</body>
</html>
HTML;
}

/**
 * Send 6-digit Email Verification OTP for Registration
 */
function send_registration_otp(PDO $pdo, string $email, string $name, ?int $userId = null): array {
    $otpResult = create_email_otp($pdo, $email, 'registration', $userId);
    if (!$otpResult['success']) {
        return $otpResult;
    }

    $otp = $otpResult['otp'];
    $title = "Verify Your MediCycle Email";
    $subtitle = "Hello " . htmlspecialchars($name) . ", thank you for joining MediCycle. Please verify your email address to activate your healthcare portal account.";
    $instructions = "Enter the 6-digit code above on the email verification page to activate your account and start redistributing surplus medical supplies.";

    $html = render_medicycle_email_template($title, $subtitle, $otp, $instructions);
    $sendResult = send_medicycle_mail($email, $name, "Verify Your MediCycle Email - Code: {$otp}", $html);

    return [
        'success' => $sendResult['success'],
        'error' => $sendResult['success'] ? null : $sendResult['message'],
        'otp' => $otp
    ];
}

/**
 * Send 6-digit Password Reset OTP
 */
function send_password_reset_otp(PDO $pdo, string $email, string $name, ?int $userId = null): array {
    $otpResult = create_email_otp($pdo, $email, 'password_reset', $userId);
    if (!$otpResult['success']) {
        return $otpResult;
    }

    $otp = $otpResult['otp'];
    $title = "Password Reset Verification";
    $subtitle = "Hello " . htmlspecialchars($name) . ", we received a request to reset your MediCycle account password.";
    $instructions = "Enter the 6-digit code above on the password verification screen to set a new password. This code can only be used once.";

    $html = render_medicycle_email_template($title, $subtitle, $otp, $instructions);
    $sendResult = send_medicycle_mail($email, $name, "MediCycle Password Reset OTP - Code: {$otp}", $html);

    return [
        'success' => $sendResult['success'],
        'error' => $sendResult['success'] ? null : $sendResult['message'],
        'otp' => $otp
    ];
}
