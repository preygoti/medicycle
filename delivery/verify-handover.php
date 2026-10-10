<?php
/**
 * MediCycle - Courier Consignment Handover Verification
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('delivery');

$pageTitle = 'Verify Logistics Handover';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

// Include the universal verification terminal view
include __DIR__ . '/../includes/verify-handover-view.php';

include __DIR__ . '/../includes/footer.php';
