<?php
/**
 * MediCycle - Database Connection
 * PDO connection with multi-host cloud fallback, robust error handling, and UTF-8 encoding.
 */

// Auto-detect environment: InfinityFree cloud production vs local development
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isInfinityFree = (strpos($httpHost, 'infinityfree') !== false) 
               || (strpos($httpHost, 'rf.gd') !== false)
               || (strpos($httpHost, 'epizy.com') !== false)
               || (isset($_SERVER['DOCUMENT_ROOT']) && strpos($_SERVER['DOCUMENT_ROOT'], 'htdocs') !== false && !is_dir('C:/xampp'));

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5,
];

try {
    if ($isInfinityFree) {
        // Multi-Host Resilient Connection for InfinityFree / iFastNet Cloud Cluster
        $envHost = getenv('DB_HOST');
        $primaryHost = (!empty($envHost) && $envHost !== '127.0.0.1' && $envHost !== 'localhost') ? $envHost : 'sql206.byetcluster.com';
        
        $candidateHosts = array_unique([
            $primaryHost,
            'sql206.byetcluster.com',
            'sql206.epizy.com',
            'sql206.infinityfree.com',
            '192.168.0.176',
            'localhost',
            '127.0.0.1'
        ]);

        $envDb = getenv('DB_NAME');
        $candidateDbs = array_unique(array_filter([
            (!empty($envDb) && $envDb !== 'medicycle_db') ? $envDb : 'if0_43175843_medicycle',
            'if0_43175843_medicycle',
            'if0_43175843_medicycle_db'
        ]));

        $envUser = getenv('DB_USER');
        $db_user = (!empty($envUser) && $envUser !== 'root') ? $envUser : 'if0_43175843';
        $envPass = getenv('DB_PASS');
        $db_pass = ($envPass !== false && $envPass !== '') ? $envPass : 'PreyGoti123';
        $db_port = getenv('DB_PORT') ?: '3306';

        $pdo = null;
        $lastException = null;

        foreach ($candidateHosts as $h) {
            foreach ($candidateDbs as $d) {
                try {
                    $dsn = "mysql:host={$h};port={$db_port};dbname={$d};charset=utf8mb4";
                    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
                    $db_host = $h;
                    $db_name = $d;
                    break 2;
                } catch (PDOException $ex) {
                    $lastException = $ex;
                }
            }
        }

        if (!$pdo) {
            throw $lastException;
        }
    } else {
        // Localhost Development (XAMPP / Local tunnel)
        $db_host = getenv('DB_HOST') ?: '127.0.0.1';
        $db_port = getenv('DB_PORT') ?: '3306';
        $db_name = getenv('DB_NAME') ?: 'medicycle_db';
        $db_user = getenv('DB_USER') ?: 'root';
        $db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    }
    
    try {
        $pdo->exec("SET time_zone = '+05:30'");
    } catch (Exception $tzEx) {
        // Fallback silently if hosting MySQL restricts timezone commands
    }
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    $isLocal = !$isInfinityFree;
    $advice = $isLocal 
        ? "Please open the <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to <strong>MySQL</strong>."
        : "InfinityFree MySQL server is temporarily synchronizing. Please refresh in 10-20 seconds or verify your database status in the InfinityFree Control Panel.";

    die('<div style="font-family:system-ui,-apple-system,sans-serif;padding:32px;max-width:620px;margin:60px auto;border:1px solid #fecaca;background:#fef2f2;border-radius:14px;box-shadow:0 10px 25px rgba(0,0,0,0.06);color:#991b1b;line-height:1.5;">
        <h3 style="margin-top:0;color:#b91c1c;font-size:1.25rem;display:flex;align-items:center;gap:8px;">⚠️ Database Connection Notice</h3>
        <p style="color:#374151;font-size:0.95rem;">' . $advice . '</p>
        <div style="background:#ffffff;border:1px solid #fee2e2;border-radius:8px;padding:12px;margin:16px 0;font-size:0.82rem;font-family:monospace;color:#dc2626;word-break:break-all;">
            ' . htmlspecialchars($e->getMessage()) . '
        </div>
        <p style="margin-bottom:0;color:#6b7280;font-size:0.8rem;">Host attempted: ' . htmlspecialchars($db_host ?? 'unknown') . ' | Environment: ' . ($isInfinityFree ? 'InfinityFree Cloud' : 'Localhost') . '</p>
    </div>');
}
