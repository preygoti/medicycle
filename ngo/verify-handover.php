<?php
/**
 * MediCycle - Clinic & NGO Supply Handover Code Pass Redirect
 * Redirects to the dedicated recipient Code Pass & Scanner page.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$code = clean($_GET['code'] ?? '');
$redirectUrl = BASE_URL . '/ngo/code-pass.php' . (!empty($code) ? '?code=' . urlencode($code) : '');
header('Location: ' . $redirectUrl);
exit;
