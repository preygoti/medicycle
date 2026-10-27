<?php
/**
 * MediCycle - Global Application Configuration
 */

// Set application timezone
date_default_timezone_set('Asia/Kolkata');

// Define application root directory
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Determine the web root URL dynamically
if (!defined('BASE_URL')) {
    $appUrl = getenv('APP_URL');
    if (!empty($appUrl)) {
        define('BASE_URL', rtrim($appUrl, '/'));
    } else {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
            || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false)
            || (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) === 'on');

        $protocol = $isHttps ? "https://" : "http://";
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        
        // Calculate relative path from DOCUMENT_ROOT to project directory (case-insensitive for Windows)
        $rawDocRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $docRoot = !empty($rawDocRoot) ? str_replace('\\', '/', realpath($rawDocRoot) ?: $rawDocRoot) : '';
        $appRoot = str_replace('\\', '/', realpath(APP_ROOT) ?: APP_ROOT);
        
        $docRootLower = strtolower($docRoot);
        $appRootLower = strtolower($appRoot);
        
        if (!empty($docRoot) && strpos($appRootLower, $docRootLower) === 0) {
            $relativePath = substr($appRoot, strlen($docRoot));
            $baseUrl = rtrim($protocol . $host . $relativePath, '/');
        } else {
            // Robust fallback via SCRIPT_NAME to dynamically detect subdirectory on localhost
            $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $projectFolder = basename($appRoot);
            $pos = stripos($scriptName, '/' . $projectFolder);
            if ($pos !== false) {
                $subPath = substr($scriptName, 0, $pos + strlen('/' . $projectFolder));
                $baseUrl = rtrim($protocol . $host . $subPath, '/');
            } else {
                $baseUrl = rtrim($protocol . $host, '/');
            }
        }
        define('BASE_URL', $baseUrl);
    }
}

// Application settings
define('APP_NAME', 'MediCycle');
define('APP_TAGLINE', 'Smart Medical Supply Redistribution System');
define('APP_VERSION', '1.0.0');
define('SESSION_LIFETIME', 7200); // 2 hours

// Safety scope: Disallowed categories/items warning
define('ELIGIBLE_SCOPE_NOTICE', 'MediCycle handles strictly eligible, unexpired, unopened non-drug medical consumables (PPE, dressings, bandages, sterile kits). Prescription medications and opened consumables are prohibited.');

// Load .env file variables if present
$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (getenv($key) === false) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
            }
        }
    }
}

// SMTP Mail Settings
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'preygoti11@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'pmjmytmvjygennrp');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'preygoti11@gmail.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Medi Cycle');

// Google Apps Script Mail Relay (HTTPS Webhook for free hosts like InfinityFree)
define('GOOGLE_MAIL_RELAY_URL', getenv('GOOGLE_MAIL_RELAY_URL') ?: 'https://script.google.com/macros/s/AKfycbwVj4ldoiQP6ONvoqoFoCmOEpUU1gOtmBfzMhxRiVF2TTuBiMsuOgQ7yA_vrPcKqqYsVg/exec');

// Require database connection
require_once __DIR__ . '/database.php';
require_once APP_ROOT . '/includes/session.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/mail.php';
