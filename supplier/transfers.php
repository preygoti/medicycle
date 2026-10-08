<?php
/**
 * MediCycle - Redirect transfers to requests in direct handover model
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');
header('Location: ' . BASE_URL . '/supplier/requests.php');
exit;
