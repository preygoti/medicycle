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
                    const navbar = document.querySelector('.navbar');
                    const navbarOffset = (navbar ? navbar.offsetHeight : 70) + 12;
                    const elementPosition = targetEl.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - navbarOffset;

                    window.scrollTo({
                        top: Math.max(0, offsetPosition),
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
                const navbar = document.querySelector('.navbar');
                const scrollPos = window.pageYOffset + (navbar ? navbar.offsetHeight : 70) + 30;
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

    // 5. Touch & Trackpad Finger Swipe Back Mechanism (Edge-Only & Scroll-Safe)
    (function initSwipeBack() {
        let touchStartX = 0;
        let touchStartY = 0;
        let isEdgeSwipe = false;
        let isNavigatingBack = false;
        const swipeThreshold = 85; // px required for touch swipe

        // Helper: Check if an element or its ancestors has active horizontal scrolling
        function hasHorizontalScrollParent(target) {
            let el = target;
            while (el && el !== document.body && el !== document.documentElement) {
                if (el.classList && (el.classList.contains('table-responsive') || el.classList.contains('overflow-x-auto') || el.classList.contains('overflow-auto'))) {
                    return true;
                }
                const style = window.getComputedStyle(el);
                if ((style.overflowX === 'auto' || style.overflowX === 'scroll') && el.scrollWidth > el.clientWidth) {
                    return true;
                }
                el = el.parentElement;
            }
            return false;
        }

        // Create visual swipe indicator
        const pill = document.createElement('div');
        pill.className = 'swipe-back-pill';
        pill.innerHTML = '<i class="fas fa-arrow-left"></i>';
        document.body.appendChild(pill);

        // Touch event listeners for mobile / touch devices
        window.addEventListener('touchstart', function (e) {
            if (e.touches.length === 1) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                // Only trigger if touch begins right at the extreme left screen edge (<= 45px)
                // and NOT inside an element with horizontal scrolling
                isEdgeSwipe = (touchStartX <= 45) && !hasHorizontalScrollParent(e.target);
            }
        }, { passive: true });

        window.addEventListener('touchmove', function (e) {
            if (!isEdgeSwipe || e.touches.length !== 1 || isNavigatingBack) return;
            const currentX = e.touches[0].clientX;
            const currentY = e.touches[0].clientY;
            const deltaX = currentX - touchStartX;
            const deltaY = Math.abs(currentY - touchStartY);

            // Check if moving primarily horizontally to the right
            if (deltaX > 20 && deltaY < deltaX * 0.6) {
                const progress = Math.min(deltaX / swipeThreshold, 1);
                pill.style.opacity = (progress * 0.95).toString();
                pill.style.transform = `translateY(-50%) translateX(${Math.min(deltaX * 0.45, 35)}px) scale(${0.8 + progress * 0.25})`;
            } else {
                pill.style.opacity = '0';
            }
        }, { passive: true });

        window.addEventListener('touchend', function (e) {
            if (!isEdgeSwipe || isNavigatingBack) return;
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            const deltaX = endX - touchStartX;
            const deltaY = Math.abs(endY - touchStartY);

            if (deltaX >= swipeThreshold && deltaY < deltaX * 0.6) {
                isNavigatingBack = true;
                pill.style.transform = 'translateY(-50%) translateX(45px) scale(1.15)';
                pill.style.opacity = '1';
                setTimeout(function () {
                    if (window.history.length > 1) {
                        window.history.back();
                    }
                    setTimeout(() => { isNavigatingBack = false; }, 1000);
                }, 100);
            }

            isEdgeSwipe = false;
            setTimeout(function () {
                pill.style.opacity = '0';
                pill.style.transform = 'translateY(-50%) translateX(0px) scale(0.8)';
            }, 250);
        }, { passive: true });

        // Two-Finger Trackpad Horizontal Gesture
        // Calibrated with high threshold so it NEVER triggers accidentally during normal horizontal scrolling
        let trackpadAccumulator = 0;
        let trackpadTimer = null;

        window.addEventListener('wheel', function (e) {
            if (isNavigatingBack) return;

            // 1. If mouse cursor is NOT near the left edge of the screen (<= 85px),
            // it is normal page or table horizontal scrolling. Never trigger back navigation!
            if (e.clientX > 85) {
                trackpadAccumulator = 0;
                pill.style.opacity = '0';
                return;
            }

            // 2. If hovering over a horizontally scrollable element (table, slider, etc.), let it scroll normally!
            if (hasHorizontalScrollParent(e.target)) {
                trackpadAccumulator = 0;
                pill.style.opacity = '0';
                return;
            }

            // 3. Must be at the leftmost scroll boundary of the window
            if (window.scrollX > 5) {
                trackpadAccumulator = 0;
                pill.style.opacity = '0';
                return;
            }

            // 4. Must be a clear, deliberate horizontal rightward swipe (deltaX < -25)
            if (Math.abs(e.deltaX) > Math.abs(e.deltaY) * 1.5 && e.deltaX < -25) {
                trackpadAccumulator += Math.abs(e.deltaX);
                const progress = Math.min(trackpadAccumulator / 340, 1);
                pill.style.opacity = (progress * 0.95).toString();
                pill.style.transform = `translateY(-50%) translateX(${progress * 28}px) scale(${0.8 + progress * 0.2})`;

                clearTimeout(trackpadTimer);
                trackpadTimer = setTimeout(function () {
                    trackpadAccumulator = 0;
                    pill.style.opacity = '0';
                    pill.style.transform = 'translateY(-50%) translateX(0px) scale(0.8)';
                }, 300);

                // High threshold: 340 cumulative units (deliberate swipe, impossible to trigger accidentally)
                if (trackpadAccumulator >= 340) {
                    isNavigatingBack = true;
                    trackpadAccumulator = 0;
                    pill.style.transform = 'translateY(-50%) translateX(45px) scale(1.15)';
                    pill.style.opacity = '1';
                    setTimeout(function () {
                        if (window.history.length > 1) {
                            window.history.back();
                        }
                        setTimeout(() => { isNavigatingBack = false; }, 1000);
                    }, 120);
                }
            } else {
                trackpadAccumulator = Math.max(0, trackpadAccumulator - 25);
                if (trackpadAccumulator === 0) {
                    pill.style.opacity = '0';
                }
            }
        }, { passive: true });
    })();

    // 7. Real-Time Auto-Refresh Poller Engine (Fast Status Sync without Manual F5)
    (function initRealtimePoller() {
        if (!document.querySelector('.app-container') && !document.querySelector('.dashboard-card')) {
            return;
        }

        let lastFingerprint = null;
        let lastNotifIds = new Set();
        let isInitialLoad = true;
        let pendingRefresh = false;
        let isRefreshing = false;
        const POLL_INTERVAL = 2500; // 2.5 seconds for instant reactive updates

        // Web Audio chime for live incoming updates
        function playSoftChime() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.14);
                gain.gain.setValueAtTime(0.06, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.32);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.33);
            } catch (e) {}
        }

        // Floating sync pill indicator
        function showSyncPill(text) {
            let pill = document.getElementById('live-sync-pill');
            if (!pill) {
                pill = document.createElement('div');
                pill.id = 'live-sync-pill';
                pill.style.cssText = 'position:fixed; bottom:22px; right:25px; z-index:99998; background:#0f766e; color:#ffffff; font-size:0.75rem; font-weight:600; padding:6px 14px; border-radius:50px; box-shadow:0 4px 15px rgba(15,118,110,0.3); display:flex; align-items:center; gap:6px; opacity:0; transition:all 0.3s ease; pointer-events:none;';
                document.body.appendChild(pill);
            }
            pill.innerHTML = '<i class="fas fa-arrows-rotate fa-spin" style="font-size:0.7rem;"></i> ' + (text || 'Live Updated');
            pill.style.opacity = '1';
            pill.style.transform = 'translateY(0)';
            setTimeout(function() {
                pill.style.opacity = '0';
                pill.style.transform = 'translateY(8px)';
            }, 1800);
        }

        // Live notification toast popup
        function showLiveToast(title, message, link) {
            let container = document.getElementById('live-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'live-toast-container';
                container.style.cssText = 'position:fixed; top:85px; right:25px; z-index:99999; display:flex; flex-direction:column; gap:10px; max-width:380px; pointer-events:none;';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'live-toast-alert';
            toast.style.cssText = 'background:#ffffff; border-left:4px solid #0f766e; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.12); padding:14px 16px; pointer-events:auto; transition:all 0.35s ease; animation:slideInToast 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;';
            
            let linkHtml = link ? ('<a href="' + link + '" class="btn btn-xs text-white py-1 px-3 rounded-pill fw-semibold mt-2 d-inline-block shadow-2xs" style="background:#0f766e; font-size:0.75rem;">View Now <i class="fas fa-arrow-right ms-1"></i></a>') : '';

            toast.innerHTML = 
                '<div class="d-flex align-items-start gap-3">' +
                    '<span class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px; background:#ccfbf1; color:#0f766e; flex-shrink:0;">' +
                        '<i class="fas fa-bell"></i>' +
                    '</span>' +
                    '<div style="flex:1;">' +
                        '<div class="d-flex justify-content-between align-items-center mb-1">' +
                            '<strong class="text-dark small">' + title + '</strong>' +
                            '<button type="button" class="btn-close btn-close-xs" style="font-size:0.65rem;" onclick="this.closest(\'.live-toast-alert\').remove()"></button>' +
                        '</div>' +
                        '<p class="text-secondary small mb-1" style="font-size:0.8rem; line-height:1.35;">' + message + '</p>' +
                        linkHtml +
                    '</div>' +
                '</div>';

            container.appendChild(toast);
            playSoftChime();

            setTimeout(function() {
                if (toast && toast.parentNode) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(-15px) scale(0.95)';
                    setTimeout(function() { toast.remove(); }, 350);
                }
            }, 6500);
        }

        // Live update navbar notification badge and dropdown
        function updateNavbarNotifications(unreadCount, recentNotifs) {
            const bellBtn = document.getElementById('notifDropdown');
            if (!bellBtn) return;

            let badge = bellBtn.querySelector('.notif-pulse-badge');
            if (unreadCount > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'notif-pulse-badge';
                    bellBtn.appendChild(badge);
                }
                badge.textContent = unreadCount;
            } else if (badge) {
                badge.remove();
            }

            const menu = bellBtn.nextElementSibling;
            if (menu && menu.tagName === 'UL') {
                const countBadge = menu.querySelector('.badge');
                if (countBadge) {
                    countBadge.textContent = unreadCount + ' New';
                }
            }
        }

        // Seamless background DOM content refresh
        async function refreshPageContent(reason) {
            if (isRefreshing) return;

            const hasOpenModal = !!document.querySelector('.modal.show');
            const isUserTyping = !!(document.activeElement && ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName));

            if (hasOpenModal || isUserTyping) {
                pendingRefresh = true;
                return;
            }

            isRefreshing = true;
            try {
                const baseUrl = window.location.href;
                const res = await fetch(baseUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });

                if (!res.ok) {
                    window.location.reload();
                    return;
                }

                const html = await res.text();
                const parser = new DOMParser();
                const newDoc = parser.parseFromString(html, 'text/html');

                const currentContent = document.querySelector('.app-content');
                const newContent = newDoc.querySelector('.app-content');

                if (currentContent && newContent) {
                    const scrollY = window.scrollY;
                    currentContent.innerHTML = newContent.innerHTML;
                    window.scrollTo(0, scrollY);

                    // Re-bind Bootstrap tooltips
                    const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltips.map(function (el) { return new bootstrap.Tooltip(el); });

                    showSyncPill('Updated Live');
                } else {
                    window.location.reload();
                }
            } catch (err) {
                window.location.reload();
            } finally {
                isRefreshing = false;
                pendingRefresh = false;
            }
        }

        // Listen for modal close and input blur to execute pending refreshes
        document.addEventListener('hidden.bs.modal', function () {
            if (pendingRefresh) {
                setTimeout(function() { refreshPageContent('Modal closed'); }, 200);
            }
        });

        document.addEventListener('blur', function (e) {
            if (pendingRefresh && e.target && ['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
                setTimeout(function () {
                    const stillTyping = document.activeElement && ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName);
                    const modalOpen = !!document.querySelector('.modal.show');
                    if (!stillTyping && !modalOpen) {
                        refreshPageContent('Finished typing');
                    }
                }, 350);
            }
        }, true);

        // Core Polling Loop
        async function pollStatus() {
            try {
                const apiBase = window.BASE_URL ? window.BASE_URL.replace(/\/$/, '') : '';
                const res = await fetch(apiBase + '/api/poll_status.php', {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });

                if (!res.ok) return;
                const data = await res.json();
                if (!data || !data.logged_in) return;

                // 1. Process notifications & update bell
                updateNavbarNotifications(data.unread_count || 0, data.recent_notifications || []);

                if (data.recent_notifications && data.recent_notifications.length > 0) {
                    data.recent_notifications.forEach(function (n) {
                        if (!lastNotifIds.has(n.id)) {
                            lastNotifIds.add(n.id);
                            if (!isInitialLoad && !n.is_read) {
                                const link = n.link ? (apiBase + '/' + n.link.replace(/^\//, '')) : null;
                                showLiveToast(n.title, n.message, link);
                            }
                        }
                    });
                }

                // 2. Check fingerprint change (instant live refresh)
                if (data.fingerprint) {
                    if (lastFingerprint !== null && lastFingerprint !== data.fingerprint) {
                        lastFingerprint = data.fingerprint;
                        if (!isInitialLoad) {
                            refreshPageContent('State transition detected');
                        }
                    } else {
                        lastFingerprint = data.fingerprint;
                    }
                }

                isInitialLoad = false;
            } catch (err) {
                // Silently ignore network interruptions
            }
        }

        // Run immediately on page load, then poll every 2.5 seconds
        pollStatus();
        setInterval(pollStatus, POLL_INTERVAL);
    })();
});


