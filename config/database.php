<?php
/**
 * MediCycle - Database Connection
 * PDO connection with robust error handling and UTF-8 encoding.
 */

// Load environment variables if available
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'medicycle_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // In production, log error instead of raw message
    error_log("Database connection error: " . $e->getMessage());
    die('<div style="font-family:sans-serif;padding:30px;max-width:600px;margin:50px auto;border:1px solid #f5c6cb;background:#f8d7da;border-radius:8px;color:#721c24;">
        <h3 style="margin-top:0;">Database Connection Error</h3>
        <p>Could not connect to the MediCycle database. Please ensure MySQL is running and the database has been imported.</p>
        <p><small>Error details: ' . htmlspecialchars($e->getMessage()) . '</small></p>
    </div>');
}
