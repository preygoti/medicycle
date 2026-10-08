<?php
/**
 * MediCycle - Contact & Help Page
 */
require_once __DIR__ . '/config/config.php';

$success = false;
$errors = [];
$name = '';
$email = '';
$subject = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please refresh and try again.';
    } else {
        $name = clean($_POST['name'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $subject = clean($_POST['subject'] ?? '');
        $message = clean($_POST['message'] ?? '');

        if (empty($name)) $errors[] = 'Please provide your name.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please provide a valid email address.';
        if (empty($subject)) $errors[] = 'Subject cannot be empty.';
        if (strlen($message) < 10) $errors[] = 'Message must be at least 10 characters.';

        if (empty($errors)) {
            $success = true;
            // For a college OEP demo, log message or notify admin
            try {
                create_notification(
                    $pdo,
                    1, // Admin
                    "New Contact Inquiry: {$subject}",
                    "From {$name} ({$email}): " . substr($message, 0, 100) . '...',
                    'admin/dashboard.php'
                );
            } catch (Exception $e) {
                // notification optional
            }
            // Reset fields
            $name = $email = $subject = $message = '';
        }
    }
}

$pageTitle = 'Contact Us';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="bg-light py-5 border-bottom">
    <div class="container text-center">
        <span class="badge bg-teal-light text-primary px-3 py-2 rounded-pill fw-semibold mb-2" style="background:#ccfbf1; color:#0f766e;">
            Support & Community
        </span>
        <h1 class="fw-bold text-dark mb-2">Get in Touch with MediCycle</h1>
        <div class="heading-accent-line mx-auto"></div>
        <p class="text-muted mx-auto page-headline" style="max-width: 600px;">
            Have questions about healthcare supplier onboarding, NGO verification, or logistics partnerships? We are here to help.
        </p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4 p-md-5">
                <h4 class="fw-bold text-dark mb-4"><i class="fas fa-paper-plane text-teal me-2" style="color:#0f766e;"></i>Send Us an Inquiry</h4>

                <?php if ($success): ?>
                    <div class="alert alert-success shadow-sm mb-4">
                        <i class="fas fa-check-circle me-2"></i>
                        Thank you! Your inquiry has been dispatched to the MediCycle coordination desk. We will respond promptly.
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger shadow-sm mb-4">
                        <ul class="mb-0 ps-3 small">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?php echo BASE_URL; ?>/contact.php" method="POST" class="needs-validation" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold">Your Name *</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo e($name); ?>" placeholder="Full Name" required>
                        <div class="invalid-feedback">Name is required.</div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo e($email); ?>" placeholder="email@organization.org" required>
                        <div class="invalid-feedback">Valid email required.</div>
                    </div>

                    <div class="mb-3">
                        <label for="subject" class="form-label small fw-semibold">Inquiry Subject *</label>
                        <input type="text" class="form-control" id="subject" name="subject" value="<?php echo e($subject); ?>" placeholder="e.g. Hospital Verification Status" required>
                        <div class="invalid-feedback">Subject is required.</div>
                    </div>

                    <div class="mb-4">
                        <label for="message" class="form-label small fw-semibold">Detailed Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="4" placeholder="How can the MediCycle network assist your organization?" required minlength="10"><?php echo e($message); ?></textarea>
                        <div class="invalid-feedback">Message must be at least 10 characters.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-paper-plane me-1"></i> Send Message
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="mb-4">
                <h4 class="fw-bold text-dark mb-3">Frequently Asked Questions</h4>
                <div class="accordion shadow-sm" id="faqAccordion">
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="faq1">
                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                Who can register as a Supplier on MediCycle?
                            </button>
                        </h2>
                        <div id="collapse1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary small">
                                Any verified hospital, licensed surgical clinic, pharmacy network, or authorized distributor with surplus unexpired consumables may register. All organizations undergo manual admin verification prior to active listings.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="faq2">
                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                Are medical supplies sold or charged for?
                            </button>
                        </h2>
                        <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary small">
                                MediCycle is designed for benevolent redistribution of surplus consumables to charitable healthcare facilities, free community health posts, and disaster relief operations. Supplies are donated to prevent landfill waste.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="faq3">
                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                Why are prescription drugs excluded?
                            </button>
                        </h2>
                        <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary small">
                                Prescription pharmaceuticals require specialized cold-chain logs, schedule drug compliance, and doctor-patient chain-of-custody. MediCycle focuses exclusively on eligible consumables (PPE, bandages, sterile dressings, and non-drug disposables) to maintain strict patient safety.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm p-4 bg-white">
                <h6 class="fw-bold text-dark mb-3"><i class="fas fa-headset text-teal me-2" style="color:#0f766e;"></i>Contact Information</h6>
                <p class="text-secondary small mb-2"><i class="fas fa-envelope text-muted me-2"></i> support@medicycle.org</p>
                <p class="text-secondary small mb-2"><i class="fas fa-phone text-muted me-2"></i> +91 (079) 2630-1000</p>
                <p class="text-secondary small mb-0"><i class="fas fa-map-marker-alt text-muted me-2"></i> Healthcare Innovation Hub, University Enclave, Ahmedabad, Gujarat, India</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
