<?php
/**
 * MediCycle - Global HTML Header & SEO Engine
 */
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME . ' - ' . APP_TAGLINE;
$currentDir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
$isPortalPage = in_array($currentDir, ['supplier', 'ngo', 'delivery']);

$metaDescription = isset($pageDescription) ? $pageDescription : 'MediCycle is an intelligent medical supply redistribution platform connecting hospitals, certified suppliers, clinics, and NGOs to eliminate healthcare waste and save lives.';
$metaKeywords = 'medical supply redistribution, medical equipment donation, surplus medical supplies, NGO healthcare supplies, medical waste reduction, MediCycle';
$canonicalUrl = BASE_URL . ($_SERVER['REQUEST_URI'] ?? '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    
    <!-- Search Engine Indexing & Robots Directives -->
    <?php if ($isPortalPage): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php else: ?>
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <meta name="googlebot" content="index, follow">
    <?php endif; ?>
    
    <!-- Meta SEO Tags -->
    <meta name="description" content="<?php echo e($metaDescription); ?>">
    <meta name="keywords" content="<?php echo e($metaKeywords); ?>">
    <meta name="author" content="MediCycle Team">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Open Graph / Facebook Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo e($pageTitle); ?>">
    <meta property="og:description" content="<?php echo e($metaDescription); ?>">
    <meta property="og:image" content="<?php echo BASE_URL; ?>/assets/images/logo-icon.png">
    <meta property="og:image:secure_url" content="<?php echo BASE_URL; ?>/assets/images/logo-icon.png">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:alt" content="MediCycle Official Logo">
    <meta property="og:site_name" content="MediCycle">
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:title" content="<?php echo e($pageTitle); ?>">
    <meta name="twitter:description" content="<?php echo e($metaDescription); ?>">
    <meta name="twitter:image" content="<?php echo BASE_URL; ?>/assets/images/logo-icon.png">
    
    <!-- Google Search Engine Thumbnail -->
    <meta name="thumbnail" content="<?php echo BASE_URL; ?>/assets/images/logo-icon.png">
    
    <!-- Google Search Console Verification Meta Tag -->
    <meta name="google-site-verification" content="nfmJ_SXLJ_zElY9VsFkJAryz3zJy1shhjIPzwJUamcU" />

    <!-- Schema.org JSON-LD Structured Data for Google Rich Snippets & Official Brand Logo -->
    <?php if (!$isPortalPage): ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "Organization",
          "@id": "<?php echo BASE_URL; ?>/#organization",
          "name": "MediCycle",
          "url": "<?php echo BASE_URL; ?>/",
          "logo": {
            "@type": "ImageObject",
            "@id": "<?php echo BASE_URL; ?>/#logo",
            "url": "<?php echo BASE_URL; ?>/assets/images/logo-icon.png",
            "caption": "MediCycle Logo"
          },
          "image": "<?php echo BASE_URL; ?>/assets/images/logo-icon.png",
          "description": "Smart Medical Supply Redistribution System connecting hospitals, suppliers, and NGOs to eliminate medical waste."
        },
        {
          "@type": "WebSite",
          "@id": "<?php echo BASE_URL; ?>/#website",
          "url": "<?php echo BASE_URL; ?>/",
          "name": "MediCycle",
          "publisher": {
            "@id": "<?php echo BASE_URL; ?>/#organization"
          }
        }
      ]
    }
    </script>
    <?php endif; ?>

    <!-- MediCycle Multi-Format Icons for Googlebot & Mobile Browsers -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>/assets/images/favicon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo BASE_URL; ?>/assets/images/logo-icon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>/assets/images/logo-icon.png">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/favicon.ico">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CDNs Preconnect for faster asset delivery -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Chart.js for Dashboards & Analytics (deferred for non-blocking rendering) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    
    <!-- MediCycle Custom Theme CSS with Auto Cache Busting -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=2.8.7_<?php echo file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
    <script>
        window.BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
</head>
<body>
