<?php
/**
 * MediCycle - How It Works (Redirects to unified landing page bookmark)
 */
require_once __DIR__ . '/config/config.php';
header('Location: ' . BASE_URL . '/index.php#how-it-works');
exit;
