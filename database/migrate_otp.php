<?php
/**
 * MediCycle - Database Migration for Email OTP System
 */
require_once __DIR__ . '/../config/database.php';

try {
    // 1. Add email_verified column to users if not exists
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 1 AFTER role");
        echo "[OK] Added email_verified column to users table.\n";
    } else {
        echo "[INFO] email_verified column already exists.\n";
    }

    // Ensure existing demo users are verified
    $pdo->exec("UPDATE users SET email_verified = 1, status = 'active'");
    echo "[OK] Updated all existing demo users to verified active status.\n";

    // 2. Create email_otps table
    $sql = "CREATE TABLE IF NOT EXISTS email_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        email VARCHAR(100) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        otp_type ENUM('registration', 'password_reset') NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts INT DEFAULT 0,
        is_used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_otp_lookup (email, otp_type, is_used),
        INDEX idx_otp_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "[OK] email_otps table verified / created successfully.\n";

} catch (PDOException $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
