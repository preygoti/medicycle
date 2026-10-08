/**
 * MediCycle - Interactive UI & Form Validation Script
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Enable Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 2. Client-side Form Validation with Bootstrap feedback
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            let isValid = form.checkValidity();

            // Custom checks: Password confirmation
            const password = form.querySelector('input[name="password"]');
            const confirmPassword = form.querySelector('input[name="confirm_password"]');
            if (password && confirmPassword) {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                    isValid = false;
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }

            // Custom checks: Expiry date must be future date
            const expiryDate = form.querySelector('input[name="expiry_date"]');
            if (expiryDate && expiryDate.value) {
                const selected = new Date(expiryDate.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (selected <= today) {
                    expiryDate.setCustomValidity('Expiry date must be in the future. Expired supplies are prohibited.');
                    isValid = false;
                } else {
                    expiryDate.setCustomValidity('');
                }
            }

            // Custom checks: Positive quantity
            const quantity = form.querySelector('input[name="quantity"], input[name="required_quantity"], input[name="requested_quantity"]');
            if (quantity && quantity.value) {
                if (parseInt(quantity.value, 10) <= 0) {
                    quantity.setCustomValidity('Quantity must be greater than zero');
                    isValid = false;
                } else {
                    quantity.setCustomValidity('');
                }
            }

            // Custom checks: Phone format (10 digits minimum)
            const phone = form.querySelector('input[name="phone"]');
            if (phone && phone.value) {
                const phoneClean = phone.value.replace(/[^0-9]/g, '');
                if (phoneClean.length < 10) {
                    phone.setCustomValidity('Please enter a valid phone number with at least 10 digits');
                    isValid = false;
                } else {
                    phone.setCustomValidity('');
                }
            }

            if (!isValid) {
                event.preventDefault();
                event.stopPropagation();
            }

            form.classList.add('was-validated');
        }, false);
    });

    // 3. Confirm Delete / Destructive Action helper
    document.querySelectorAll('[data-confirm]').forEach(function (element) {
        element.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 4. Auto dismiss flash alerts after 6 seconds
    setTimeout(function () {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function (alert) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        });
    }, 6000);

    // 5. Bookmark Navigation & ScrollSpy for Single-Page Unified Landing
    const mainNavLinks = document.querySelectorAll('#mainNavLinks .nav-link[data-bookmark]');
    if (mainNavLinks.length > 0) {
        const sections = [];
        mainNavLinks.forEach(function (link) {
            const id = link.getAttribute('data-bookmark');
            const sec = document.getElementById(id);
            if (sec) {
                sections.push({ id: id, link: link, el: sec });
            }
        });

        // Smooth scroll on bookmark link click
        mainNavLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                const targetId = this.getAttribute('data-bookmark');
                const targetEl = document.getElementById(targetId);
                if (targetEl) {
                    e.preventDefault();
                    const navbarOffset = 70;
                    const elementPosition = targetEl.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - navbarOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });

                    // Update URL hash cleanly
                    if (history.pushState) {
                        history.pushState(null, null, '#' + targetId);
                    } else {
                        location.hash = '#' + targetId;
                    }

                    // Update active state immediately
                    mainNavLinks.forEach(function (l) { l.classList.remove('active'); });
                    this.classList.add('active');

                    // Collapse mobile menu if open
                    const navbarCollapse = document.getElementById('navbarMain');
                    if (navbarCollapse && navbarCollapse.classList.contains('show')) {
                        const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                        if (bsCollapse) bsCollapse.hide();
                    }
                }
            });
        });

        // Dynamic ScrollSpy tracking for active headline line
        if (sections.length > 0) {
            let isScrolling = false;
            const onScroll = function () {
                const scrollPos = window.pageYOffset + 120;
                let current = sections[0];
                for (let i = 0; i < sections.length; i++) {
                    if (sections[i].el.offsetTop <= scrollPos) {
                        current = sections[i];
                    }
                }
                if (current) {
                    mainNavLinks.forEach(function (l) { l.classList.remove('active'); });
                    current.link.classList.add('active');
                }
            };

            window.addEventListener('scroll', function () {
                if (!isScrolling) {
                    window.requestAnimationFrame(function () {
                        onScroll();
                        isScrolling = false;
                    });
                    isScrolling = true;
                }
            }, { passive: true });

            // Initial trigger if loaded with hash
            if (window.location.hash) {
                const hashId = window.location.hash.substring(1);
                const hashTarget = document.getElementById(hashId);
                if (hashTarget) {
                    setTimeout(function () {
                        const navbarOffset = 70;
                        const elementPosition = hashTarget.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - navbarOffset;
                        window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
                    }, 150);
                }
            }
        }
    }
});
