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

        let isUserClickScrolling = false;
        let userScrollTimeout = null;

        // Smooth scroll on bookmark link click
        mainNavLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                const targetId = this.getAttribute('data-bookmark');
                const targetEl = document.getElementById(targetId);
                if (targetEl) {
                    e.preventDefault();

                    // Immediately switch active highlight to the clicked tab without lingering on previous
                    isUserClickScrolling = true;
                    clearTimeout(userScrollTimeout);
                    mainNavLinks.forEach(function (l) { l.classList.remove('active'); });
                    this.classList.add('active');

                    // Collapse mobile menu immediately so target section positions cleanly below header
                    const navbarCollapse = document.getElementById('navbarMain');
                    if (navbarCollapse && navbarCollapse.classList.contains('show')) {
                        const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse) || new bootstrap.Collapse(navbarCollapse, { toggle: false });
                        bsCollapse.hide();
                    }

                    const isMobile = window.innerWidth < 992;
                    const navbarOffset = isMobile ? 64 : 76;
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

                    // Resume scroll listener only after smooth scroll has finished
                    userScrollTimeout = setTimeout(function () {
                        isUserClickScrolling = false;
                    }, 800);
                }
            });
        });

        // Dynamic ScrollSpy tracking for active headline line
        if (sections.length > 0) {
            let isScrolling = false;
            const onScroll = function () {
                // If user just clicked a nav tab, do NOT revert the active indicator while scrolling
                if (isUserClickScrolling) return;

                const navbar = document.querySelector('.navbar');
                const scrollPos = window.pageYOffset + (navbar ? navbar.offsetHeight : 70) + 40;

                // Handle bottom of page
                if ((window.innerHeight + window.pageYOffset) >= document.body.offsetHeight - 60) {
                    const lastSec = sections[sections.length - 1];
                    mainNavLinks.forEach(function (l) { l.classList.remove('active'); });
                    lastSec.link.classList.add('active');
                    return;
                }

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

    // 7. Real-Time Auto-Refresh Poller Engine (High-Performance Portal Synchronization)
    (function initRealtimePoller() {
        // Only activate background poller on authenticated portal pages with an active sidebar
        if (!document.querySelector('.app-sidebar')) {
            return;
        }

        let lastFingerprint = null;
        let lastGlobalFingerprint = null;
        let lastNotifIds = new Set();
        let isInitialLoad = true;
        let pendingRefresh = false;
        let isRefreshing = false;
        let lastKeystrokeTime = 0;
        const POLL_INTERVAL = 20000; // 20s relaxed interval to optimize server response and minimize PHP thread contention

        // Track actual typing activity so focused inputs in background windows never block live updates
        document.addEventListener('keydown', function (e) {
            if (e.target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
                lastKeystrokeTime = Date.now();
            }
        }, true);

        document.addEventListener('input', function (e) {
            if (e.target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
                lastKeystrokeTime = Date.now();
            }
        }, true);

        // Cross-tab / Cross-window instant BroadcastChannel + localStorage event bridge
        let syncChannel = null;
        try {
            if ('BroadcastChannel' in window) {
                syncChannel = new BroadcastChannel('medicycle_live_sync');
                syncChannel.onmessage = function (ev) {
                    if (ev.data && ev.data.type === 'STATE_UPDATED') {
                        pollStatus(true);
                    }
                };
            }
        } catch (e) {}

        window.addEventListener('storage', function (ev) {
            if (ev.key === 'medicycle_sync_ping') {
                pollStatus(true);
            }
        });

        function broadcastStateChange() {
            try {
                if (syncChannel) {
                    syncChannel.postMessage({ type: 'STATE_UPDATED', ts: Date.now() });
                }
                localStorage.setItem('medicycle_sync_ping', String(Date.now()));
            } catch (e) {}
        }

        // If this page load just displayed a flash alert (meaning an action was just submitted), ping all other tabs immediately
        if (document.querySelector('.alert-dismissible')) {
            setTimeout(broadcastStateChange, 120);
        }

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
            pill.innerHTML = '<i class="fas fa-arrows-rotate fa-spin" style="font-size:0.7rem;"></i> ' + (text || 'Live Synchronized');
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

        // Live update navbar notification badge
        function updateNavbarNotifications(unreadCount) {
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
        async function refreshPageContent() {
            if (isRefreshing) return;

            // Check if user is actively typing within the last 3 seconds
            const isActivelyTyping = (Date.now() - lastKeystrokeTime < 3000) &&
                                     document.hasFocus() &&
                                     document.activeElement &&
                                     ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) &&
                                     !document.activeElement.classList.contains('otp-pin-input');

            // Check if a form modal (like Accept/Reject/Add/Barcode) is open
            const openModal = document.querySelector('.modal.show');
            const isFormModalOpen = !!(openModal && openModal.querySelector('form'));

            if (isActivelyTyping || isFormModalOpen) {
                pendingRefresh = true;
                setTimeout(function() {
                    if (pendingRefresh) refreshPageContent();
                }, 1500);
                return;
            }

            isRefreshing = true;
            try {
                const fetchUrl = window.location.href;
                const res = await fetch(fetchUrl, {
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
                    // If a read-only pass modal was open (e.g. qrModal / tablePassModal), close it cleanly before swapping DOM
                    if (openModal && !isFormModalOpen) {
                        try {
                            const bsModal = bootstrap.Modal.getInstance(openModal);
                            if (bsModal) bsModal.hide();
                        } catch (e) {}
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('overflow');
                        document.body.style.removeProperty('padding-right');
                    }

                    const scrollY = window.scrollY;
                    currentContent.innerHTML = newContent.innerHTML;

                    // Also sync sidebar if present
                    const currentSidebar = document.querySelector('.app-sidebar');
                    const newSidebar = newDoc.querySelector('.app-sidebar');
                    if (currentSidebar && newSidebar) {
                        currentSidebar.innerHTML = newSidebar.innerHTML;
                    }

                    // Also sync navbar notification dropdown items if present
                    const currentNotifBtn = document.getElementById('notifDropdown');
                    const newNotifBtn = newDoc.getElementById('notifDropdown');
                    if (currentNotifBtn && newNotifBtn && currentNotifBtn.parentElement && newNotifBtn.parentElement) {
                        const isDropdownOpen = currentNotifBtn.classList.contains('show');
                        if (!isDropdownOpen) {
                            currentNotifBtn.parentElement.innerHTML = newNotifBtn.parentElement.innerHTML;
                        }
                    }

                    window.scrollTo(0, scrollY);

                    // Re-bind Bootstrap tooltips
                    const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltips.map(function (el) { return new bootstrap.Tooltip(el); });

                    // Re-bind verification terminal PIN inputs & QR scanner if on verify-handover page
                    if (typeof window.initVerifyHandoverTerminal === 'function') {
                        window.initVerifyHandoverTerminal();
                    }

                    showSyncPill('Live Synchronized');
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

        // Listen for modal close to execute any pending refresh immediately
        document.addEventListener('hidden.bs.modal', function () {
            if (pendingRefresh) {
                setTimeout(function() { refreshPageContent(); }, 150);
            }
        });

        // Core Polling Loop
        async function pollStatus() {
            try {
                const apiBase = window.BASE_URL ? window.BASE_URL.replace(/\/$/, '') : '';
                let roleParam = '';
                if (window.location.pathname.indexOf('/supplier/') !== -1) {
                    roleParam = '&role=supplier';
                } else if (window.location.pathname.indexOf('/ngo/') !== -1) {
                    roleParam = '&role=ngo';
                } else if (window.location.pathname.indexOf('/delivery/') !== -1) {
                    roleParam = '&role=delivery';
                }
                const res = await fetch(apiBase + '/api/poll_status.php?_t=' + Date.now() + roleParam, {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });

                if (!res.ok) return;
                const data = await res.json();
                if (!data) return;

                // 1. Process notifications & update bell
                if (data.logged_in) {
                    updateNavbarNotifications(data.unread_count || 0);

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
                }

                // 2. Check fingerprint or global_fingerprint change (instant live refresh across both sides)
                const currentFp = data.fingerprint || data.global_fingerprint;
                const currentGlobalFp = data.global_fingerprint || currentFp;

                if (currentFp) {
                    const fpChanged = (lastFingerprint !== null && lastFingerprint !== currentFp) ||
                                      (lastGlobalFingerprint !== null && lastGlobalFingerprint !== currentGlobalFp);

                    lastFingerprint = currentFp;
                    lastGlobalFingerprint = currentGlobalFp;

                    if (fpChanged && !isInitialLoad) {
                        refreshPageContent();
                    }
                }

                isInitialLoad = false;
            } catch (err) {
                // Silently ignore transient network interruptions
            }
        }

        // Run immediately on page load, then poll every 10 seconds only while tab is visible
        pollStatus();
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                pollStatus();
            }
        }, POLL_INTERVAL);

        // Instantly poll whenever user switches back to this tab
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                pollStatus();
            }
        });
    })();

    // --------------------------------------------------------------------------
    // 6. Modern Scroll-Driven Reveal Animations ("Text Coming From Somewhere")
    // --------------------------------------------------------------------------
    (function initScrollReveals() {
        // Auto-tag landing sections, cards, headlines, and steps
        const autoSelectors = [
            '.hero-section h1',
            '.hero-section p.hero-subtitle',
            '.hero-section .card',
            '.section-header',
            '.stat-card',
            '.feature-card',
            '.step-card',
            '.testimonial-card',
            '.impact-card',
            '.category-pill',
            '.landing-showcase-card',
            'section h2',
            'section .card'
        ];

        autoSelectors.forEach(sel => {
            document.querySelectorAll(sel).forEach(el => {
                if (!el.classList.contains('reveal-on-scroll') && 
                    !el.classList.contains('reveal-fade-left') && 
                    !el.classList.contains('reveal-fade-right') && 
                    !el.classList.contains('reveal-zoom-in') &&
                    !el.closest('.modal')) { // Skip modals
                    el.classList.add('reveal-on-scroll');
                }
            });
        });

        const revealElements = document.querySelectorAll(
            '.reveal-on-scroll, .reveal-fade-left, .reveal-fade-right, .reveal-zoom-in'
        );

        if (!revealElements.length) return;

        if ('IntersectionObserver' in window) {
            const observerOptions = {
                root: null,
                rootMargin: '0px 0px -40px 0px',
                threshold: 0.10
            };

            const revealObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-revealed');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            revealElements.forEach(el => {
                const rect = el.getBoundingClientRect();
                // If already visible at top of page, reveal immediately
                if (rect.top < window.innerHeight * 0.90) {
                    el.classList.add('is-revealed');
                } else {
                    revealObserver.observe(el);
                }
            });
        } else {
            revealElements.forEach(el => el.classList.add('is-revealed'));
        }
    })();

    // -------------------------------------------------------------------------
    // 16. Mobile Navbar: Auto-Close Pop-Up Menu on Outside / Homepage Click
    // -------------------------------------------------------------------------
    (function () {
        const navbarCollapse = document.getElementById('navbarMain');
        const navbarToggler = document.querySelector('.navbar-toggler');

        if (navbarCollapse) {
            const closeMobileNav = function (e) {
                // Check if mobile menu is currently open or opening
                if (navbarCollapse.classList.contains('show')) {
                    const isClickInsideMenu = navbarCollapse.contains(e.target);
                    const isClickOnToggler = navbarToggler && navbarToggler.contains(e.target);

                    // If clicked outside on the homepage or body, close the pop-up header menu
                    if (!isClickInsideMenu && !isClickOnToggler) {
                        const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse) || new bootstrap.Collapse(navbarCollapse, { toggle: false });
                        bsCollapse.hide();
                    }
                }
            };

            // Capture clicks and touch taps on document
            document.addEventListener('click', closeMobileNav);
            document.addEventListener('touchstart', closeMobileNav, { passive: true });

            // Close when clicking any nav item or button inside the collapse (unless it's a dropdown toggle)
            const menuActionLinks = navbarCollapse.querySelectorAll('a, button');
            menuActionLinks.forEach(function (link) {
                link.addEventListener('click', function () {
                    if (this.getAttribute('data-bs-toggle') === 'dropdown') return;
                    if (window.innerWidth < 992 && navbarCollapse.classList.contains('show')) {
                        const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse) || new bootstrap.Collapse(navbarCollapse, { toggle: false });
                        bsCollapse.hide();
                    }
                });
            });

            // Also auto-close if user starts scrolling down the homepage
            window.addEventListener('scroll', function () {
                if (window.innerWidth < 992 && navbarCollapse.classList.contains('show')) {
                    const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                    if (bsCollapse) bsCollapse.hide();
                }
            }, { passive: true });
        }
    })();

    // -------------------------------------------------------------------------
    // 17. Account Portal Mobile Sidebar: Smooth Horizontal Scrolling
    // -------------------------------------------------------------------------
    (function () {
        const sidebarNav = document.querySelector('.app-sidebar .nav, .app-sidebar');
        if (!sidebarNav) return;

        // Auto-scroll the active tab into center view on mobile load
        const activeItem = document.querySelector('.app-sidebar .nav-link.active');
        if (activeItem && window.innerWidth < 992) {
            setTimeout(function () {
                activeItem.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }, 150);
        }

        // Enable horizontal mouse wheel scrolling on mobile/tablet screens
        sidebarNav.addEventListener('wheel', function (e) {
            if (window.innerWidth < 992 && e.deltaY !== 0) {
                e.preventDefault();
                this.scrollLeft += e.deltaY;
            }
        }, { passive: false });
    })();
});



