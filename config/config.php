<?php
/**
 * MediCycle - Global Application Configuration
 */

// Define application root directory
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Determine the web root URL dynamically
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Calculate relative path from DOCUMENT_ROOT to project directory
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    $appRoot = str_replace('\\', '/', APP_ROOT);
    
    if (!empty($docRoot) && strpos($appRoot, $docRoot) === 0) {
        $relativePath = substr($appRoot, strlen($docRoot));
        $baseUrl = rtrim($protocol . $host . $relativePath, '/');
    } else {
        // Fallback or PHP built-in web server running directly in project root
        $baseUrl = rtrim($protocol . $host, '/');
    }
    define('BASE_URL', $baseUrl);
}

// Application settings
define('APP_NAME', 'MediCycle');
define('APP_TAGLINE', 'Smart Medical Supply Redistribution System');
define('APP_VERSION', '1.0.0');
define('SESSION_LIFETIME', 7200); // 2 hours

// Safety scope: Disallowed categories/items warning
define('ELIGIBLE_SCOPE_NOTICE', 'MediCycle handles strictly eligible, unexpired, unopened non-drug medical consumables (PPE, dressings, bandages, sterile kits). Prescription medications and opened consumables are prohibited.');

// Require database connection
require_once __DIR__ . '/database.php';
require_once APP_ROOT . '/includes/session.php';
require_once APP_ROOT . '/includes/functions.php';
