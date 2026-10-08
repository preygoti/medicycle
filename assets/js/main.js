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
});
