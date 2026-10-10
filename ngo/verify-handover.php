<?php
/**
 * MediCycle - Clinic & NGO Supply Handover Verification
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$pageTitle = 'Verify Received Supplies';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

// Include the universal verification terminal view
include __DIR__ . '/../includes/verify-handover-view.php';

include __DIR__ . '/../includes/footer.php';
