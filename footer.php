<?php
/**
 * FOOTER.PHP - TAUFIKMARIE.COM ULTIMATE DASHBOARD
 * Version: 8.0.0 - ROLE BASED NOTIFICATION FILTER
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */
?>
    
    <!-- Admin JS Utama -->
    <script src="assets/js/admin.js?v=6.0.0"></script>
    
    <!-- JS KHUSUS HALAMAN (dipisah per halaman) -->
    <?php if (isset($page_script) && !empty($page_script)): ?>
    <script src="assets/js/<?= $page_script ?>.js?v=2.0.0"></script>
    <?php endif; ?>
    
    <!-- PUSH NOTIFICATION CLIENT SCRIPT - ANDROID FIX + ROLE FILTER -->
    <script>
    (function() {
        'use strict';
        
        // ========== CONFIG ==========
        const API_KEY = 'taufikmarie7878';
        const UNREAD_URL = 'api/get_unread_count.php?key=' + API_KEY;
        const PUSH_URL = 'api/send_notification.php';
        
        // ========== STATE ==========
        let unreadCount = 0;
        let badgeSupported = 'setAppBadge' in navigator;
        let audio = null;
        let audioEnabled = false;
        let lastNotificationTime = 0;
        
        // ========== USER DATA ==========
        const userRole = '<?= getCurrentRole() ?>'; // 'admin', 'manager', 'developer', 'marketing'
        const userId = <?= $_SESSION['user_id'] ?? $_SESSION['marketing_id'] ?? 0 ?>;
        const developerId = <?= $_SESSION['user_id'] ?? ($_SESSION['marketing_developer_id'] ?? 0) ?>;
        const marketingId = <?= $_SESSION['marketing_id'] ?? 0 ?>;
        
        console.log('[PWA] User Role: ' + userRole + ', ID: ' + userId);
        console.log('[PWA] Developer ID: ' + developerId + ', Marketing ID: ' + marketingId);
        
        // ========== INIT ==========
        function init() {
            console.log('[PWA] Footer push client initialized');
            
            // Initialize audio dengan fallback untuk Android
            try {
                audio = new Audio('/assets/sounds/notification.mp3');
                audio.volume = 0.8;
                audio.preload = 'auto';
                
                // Coba load audio
                audio.load();
                
                // Enable audio setelah user berinteraksi
                document.addEventListener('click', enableAudio, { once: true });
                document.addEventListener('touchstart', enableAudio, { once: true });
                document.addEventListener('keydown', enableAudio, { once: true });
                
                // Untuk Android, coba play saat first interaction
                document.body.addEventListener('touchstart', function playOnce() {
                    audio.play().catch(() => {});
                    document.body.removeEventListener('touchstart', playOnce);
                }, { once: true });
                
            } catch (e) {
                console.log('[PWA] Audio init error:', e);
            }
            
            // Cek permission notifikasi
            if ('Notification' in window) {
                if (Notification.permission === 'default') {
                    Notification.requestPermission();
                } else if (Notification.permission === 'granted') {
                    console.log('[PWA] Notification permission granted');
                }
            }
            
            // Cek apakah di-install sebagai PWA
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                                 window.navigator.standalone === true;
            console.log('[PWA] Is standalone:', isStandalone);
            
            // Update pertama
            updateUnreadCount();
            
            // Set interval (15 detik)
            setInterval(updateUnreadCount, 15000);
            
            // Listen for service worker messages
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', handleSWMessage);
                
                // Dapatkan registration
                navigator.serviceWorker.ready.then(registration => {
                    console.log('[PWA] Service worker ready');
                });
            }
            
            // Cek badge support di Android
            if (badgeSupported) {
                console.log('[PWA] App Badge is supported');
            } else {
                console.log('[PWA] App Badge is NOT supported, using fallback');
            }
        }
        
        function enableAudio() {
            audioEnabled = true;
            console.log('[PWA] Audio enabled');
            
            // Test play audio (silent)
            if (audio) {
                audio.volume = 0.01;
                audio.play().then(() => {
                    audio.pause();
                    audio.currentTime = 0;
                    audio.volume = 0.8;
                }).catch(e => {
                    console.log('[PWA] Audio test failed:', e);
                });
            }
        }
        
        // ========== HANDLE SERVICE WORKER MESSAGE ==========
        function handleSWMessage(event) {
            console.log('[PWA] Message from SW:', event.data);
            
            if (event.data && event.data.type === 'PUSH_RECEIVED') {
                const payload = event.data.payload || {};
                
                // Filter notifikasi berdasarkan role
                const shouldShow = shouldShowNotification(payload);
                
                if (shouldShow) {
                    console.log('[PWA] Notification should be shown for this user');
                    
                    if (payload.count !== undefined) {
                        unreadCount = payload.count;
                        updateBadge(unreadCount);
                    }
                    
                    // Update title
                    updateTitle(unreadCount);
                    
                    // Mainkan suara
                    playNotificationSound();
                } else {
                    console.log('[PWA] Notification filtered out for this user');
                }
            }
        }
        
        // ========== FILTER NOTIFIKASI BERDASARKAN ROLE ==========
        function shouldShowNotification(payload) {
            const role = payload.role || 'all';
            const notifUserId = payload.user_id || 0;
            const notifDeveloperId = payload.developer_id || 0;
            const notifMarketingId = payload.marketing_id || 0;
            
            console.log('[PWA] Filter check:', { 
                userRole, userId, developerId, marketingId,
                notifRole: role, notifUserId, notifDeveloperId, notifMarketingId 
            });
            
            // ADMIN & MANAGER: semua notifikasi
            if (userRole === 'admin' || userRole === 'manager') {
                return true;
            }
            
            // DEVELOPER: hanya notifikasi untuk developer-nya
            if (userRole === 'developer') {
                if (role === 'developer' && notifDeveloperId === userId) {
                    return true;
                }
                // Juga terima notifikasi umum
                if (role === 'all') {
                    return true;
                }
                return false;
            }
            
            // MARKETING: hanya notifikasi untuk marketing-nya
            if (userRole === 'marketing') {
                if (role === 'marketing' && notifMarketingId === marketingId) {
                    return true;
                }
                // Juga terima notifikasi umum
                if (role === 'all') {
                    return true;
                }
                return false;
            }
            
            // Default: semua notifikasi
            return true;
        }
        
        // ========== UPDATE UNREAD COUNT ==========
        function updateUnreadCount() {
            fetch(UNREAD_URL + '&t=' + Date.now())
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const oldCount = unreadCount;
                        unreadCount = data.count;
                        
                        // Update badge
                        updateBadge(unreadCount);
                        
                        // Update title
                        updateTitle(unreadCount);
                        
                        // Update favicon badge
                        updateFaviconBadge(unreadCount);
                        
                        // Update mobile badge
                        updateMobileBadge(unreadCount);
                        
                        // Play sound jika ada notifikasi baru
                        if (data.fresh && oldCount < unreadCount) {
                            playNotificationSound();
                        }
                        
                        // Jika ada lead baru dan role admin/manager, selalu play sound
                        if (data.has_new_lead && (userRole === 'admin' || userRole === 'manager')) {
                            playNotificationSound();
                        }
                    }
                })
                .catch(err => console.error('[PWA] Update failed:', err));
        }
        
        // ========== UPDATE BADGE ANDROID ==========
        function updateBadge(count) {
            if (badgeSupported) {
                if (count > 0) {
                    navigator.setAppBadge(count).catch(err => {
                        console.log('[PWA] setAppBadge error:', err);
                        badgeSupported = false;
                        updateFaviconBadge(count); // Fallback
                    });
                } else {
                    navigator.clearAppBadge().catch(() => {});
                }
            } else {
                updateFaviconBadge(count); // Fallback untuk browser yang tidak support
            }
        }
        
        // ========== UPDATE FAVICON BADGE (FALLBACK) ==========
        function updateFaviconBadge(count) {
            let badge = document.getElementById('favicon-badge');
            if (!badge && count > 0) {
                badge = document.createElement('div');
                badge.id = 'favicon-badge';
                badge.style.cssText = `
                    position: fixed;
                    top: 5px;
                    right: 5px;
                    background: #D64F3C;
                    color: white;
                    font-size: 12px;
                    font-weight: bold;
                    padding: 3px 7px;
                    border-radius: 20px;
                    z-index: 999999;
                    min-width: 22px;
                    text-align: center;
                    line-height: 1.4;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                    border: 2px solid white;
                    pointer-events: none;
                `;
                document.body.appendChild(badge);
            }
            
            if (badge) {
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }
        
        // ========== UPDATE TITLE ==========
        function updateTitle(count) {
            document.title = count > 0 
                ? '(' + (count > 99 ? '99+' : count) + ') Lead Engine' 
                : 'Lead Engine - TaufikMarie.com';
        }
        
        // ========== UPDATE MOBILE BADGE ==========
        function updateMobileBadge(count) {
            const mobileDot = document.getElementById('mobileNotificationDot');
            if (mobileDot) {
                mobileDot.style.display = count > 0 ? 'block' : 'none';
            }
            
            // Update badge di tombol notifikasi header
            const notifBtn = document.querySelector('.lead-notif-btn');
            if (notifBtn) {
                let badge = notifBtn.querySelector('.lead-notif-badge');
                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'lead-notif-badge';
                        notifBtn.appendChild(badge);
                    }
                    badge.textContent = count > 9 ? '9+' : count;
                } else if (badge) {
                    badge.remove();
                }
            }
        }
        
        // ========== PLAY NOTIFICATION SOUND (ANDROID FIX) ==========
        function playNotificationSound() {
            if (!audio || !audioEnabled) {
                console.log('[PWA] Audio not ready');
                return;
            }
            
            // Debounce: jangan mainkan terlalu sering
            const now = Date.now();
            if (now - lastNotificationTime < 3000) {
                console.log('[PWA] Sound debounced');
                return;
            }
            lastNotificationTime = now;
            
            // Reset audio
            audio.pause();
            audio.currentTime = 0;
            
            // ANDROID FIX: Gunakan user interaction untuk play
            const playPromise = audio.play();
            
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    console.log('[PWA] Sound played');
                }).catch(error => {
                    console.log('[PWA] Sound play failed:', error.name);
                    
                    // Jika gagal karena user interaction, coba lagi nanti
                    if (error.name === 'NotAllowedError') {
                        // Tunggu user interaction berikutnya
                        document.addEventListener('click', function playOnClick() {
                            audio.play().catch(() => {});
                            document.removeEventListener('click', playOnClick);
                        }, { once: true });
                    }
                });
            }
        }
        
        // ========== TRIGGER NOTIFICATION MANUAL (UNTUK TEST) ==========
        window.triggerTestNotification = function() {
            fetch(PUSH_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    title: '🔔 Test Notifikasi',
                    body: 'Ini adalah notifikasi test untuk role: ' + userRole,
                    role: userRole,
                    user_id: userId,
                    developer_id: developerId,
                    marketing_id: marketingId,
                    count: unreadCount + 1
                })
            }).then(r => r.json()).then(d => {
                console.log('Test notification sent', d);
                alert('Notifikasi test dikirim untuk role: ' + userRole);
            }).catch(e => console.error(e));
        };
        
        // ========== CHECK PWA STATUS ==========
        window.checkPWAStatus = function() {
            const info = {
                isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
                notificationPermission: Notification.permission,
                badgeSupported: 'setAppBadge' in navigator,
                userAgent: navigator.userAgent,
                platform: navigator.platform,
                userRole: userRole,
                userId: userId,
                developerId: developerId,
                marketingId: marketingId,
                audioEnabled: audioEnabled,
                unreadCount: unreadCount
            };
            console.log('[PWA] Status:', info);
            alert(JSON.stringify(info, null, 2));
            return info;
        };
        
        // Start
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>
    
    <!-- BIOMETRIC REGISTRATION SCRIPT (HANYA UNTUK YANG SUDAH LOGIN) -->
    <?php if (checkAuth() && isset($_SESSION['user_id'])): ?>
    <script>
    (function() {
        'use strict';
        
        // Cek apakah user sudah punya passkey
        async function checkAndOfferBiometric() {
            // Hanya di Android + Chrome + PWA
            const isAndroid = /Android/i.test(navigator.userAgent);
            const isChrome = /Chrome/i.test(navigator.userAgent) && !/Edg/i.test(navigator.userAgent);
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            const hasWebAuthn = window.PublicKeyCredential !== undefined;
            
            if (!isAndroid || !isChrome || !isStandalone || !hasWebAuthn) {
                return;
            }
            
            try {
                const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                if (!available) return;
                
                const response = await fetch('/admin/api/webauthn_check.php', {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success && !data.has_passkey) {
                    showBiometricOffer();
                }
            } catch (error) {
                console.error('Biometric check error:', error);
            }
        }
        
        function showBiometricOffer() {
            const card = document.createElement('div');
            card.id = 'biometric-offer';
            card.style.cssText = `
                position: fixed;
                bottom: 80px;
                left: 20px;
                right: 20px;
                background: white;
                border-radius: 24px;
                padding: 20px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                z-index: 10000;
                max-width: 400px;
                margin: 0 auto;
                border-left: 6px solid #D64F3C;
                animation: slideUp 0.3s ease;
            `;
            
            card.innerHTML = `
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <div style="width: 50px; height: 50px; background: #1B4A3C; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 4px 0; color: #1B4A3C; font-size: 16px;">Aktifkan Fingerprint</h3>
                        <p style="margin: 0; color: #4A5A54; font-size: 12px;">Login lebih cepat dengan sidik jari</p>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="registerBiometric()" style="flex: 2; background: #D64F3C; color: white; border: none; padding: 14px; border-radius: 50px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-check"></i> Aktifkan
                    </button>
                    <button onclick="dismissBiometricOffer()" style="flex: 1; background: #F5F3F0; border: none; border-radius: 50px; font-weight: 600; cursor: pointer;">
                        Nanti
                    </button>
                </div>
            `;
            
            document.body.appendChild(card);
        }
        
        window.dismissBiometricOffer = function() {
            const card = document.getElementById('biometric-offer');
            if (card) card.remove();
        };
        
        window.registerBiometric = async function() {
            dismissBiometricOffer();
            
            try {
                // 1. Get CSRF token
                const csrfResponse = await fetch('/admin/api/csrf_token.php?action=generate', {
                    credentials: 'include'
                });
                const csrfData = await csrfResponse.json();
                if (!csrfData.success) throw new Error('Failed to get CSRF token');
                
                // 2. Begin registration
                const beginResponse = await fetch('/admin/api/webauthn_register.php?action=begin', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfData.csrf_token
                    },
                    credentials: 'include'
                });
                
                const beginData = await beginResponse.json();
                if (!beginData.success) throw new Error(beginData.message || 'Failed to start registration');
                
                // 3. Decode options
                const publicKey = beginData.publicKey;
                
                // Decode base64 fields
                publicKey.user.id = Uint8Array.from(atob(publicKey.user.id), c => c.charCodeAt(0));
                publicKey.challenge = Uint8Array.from(atob(publicKey.challenge), c => c.charCodeAt(0));
                
                if (publicKey.excludeCredentials) {
                    publicKey.excludeCredentials = publicKey.excludeCredentials.map(cred => {
                        return {
                            ...cred,
                            id: Uint8Array.from(atob(cred.id), c => c.charCodeAt(0))
                        };
                    });
                }
                
                // 4. Panggil browser API
                const credential = await navigator.credentials.create({
                    publicKey: publicKey
                });
                
                // 5. Encode response
                const response = {
                    id: credential.id,
                    rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))),
                    response: {
                        clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))),
                        attestationObject: btoa(String.fromCharCode(...new Uint8Array(credential.response.attestationObject))),
                        transports: credential.response.getTransports ? credential.response.getTransports() : []
                    },
                    type: credential.type
                };
                
                // 6. Kirim ke server
                const completeResponse = await fetch('/admin/api/webauthn_register.php?action=complete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(response),
                    credentials: 'include'
                });
                
                const completeData = await completeResponse.json();
                
                if (completeData.success) {
                    if (typeof window.showToast === 'function') {
                        window.showToast('✅ Fingerprint berhasil diaktifkan!', 'success');
                    } else {
                        alert('✅ Fingerprint berhasil diaktifkan!');
                    }
                } else {
                    throw new Error(completeData.message || 'Registration failed');
                }
                
            } catch (error) {
                console.error('Biometric registration error:', error);
                if (typeof window.showToast === 'function') {
                    window.showToast('❌ Gagal: ' + error.message, 'error');
                } else {
                    alert('❌ Gagal mengaktifkan fingerprint: ' + error.message);
                }
            }
        };
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', checkAndOfferBiometric);
        } else {
            checkAndOfferBiometric();
        }
    })();
    </script>
    <?php endif; ?>
    
    <!-- Toast notification function -->
    <script>
    function showToast(message, type = 'info') {
        let toast = document.querySelector('.toast-message');
        
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'toast-message';
            toast.style.cssText = `
                position: fixed;
                bottom: 80px;
                left: 50%;
                transform: translateX(-50%);
                background: ${type === 'success' ? '#2A9D8F' : type === 'error' ? '#D64F3C' : '#1B4A3C'};
                color: white;
                padding: 14px 24px;
                border-radius: 50px;
                font-size: 15px;
                font-weight: 600;
                box-shadow: 0 8px 20px rgba(0,0,0,0.2);
                z-index: 9999;
                transition: opacity 0.3s;
                max-width: 90%;
                text-align: center;
            `;
            document.body.appendChild(toast);
        }
        
        toast.textContent = message;
        toast.style.opacity = '1';
        
        setTimeout(() => {
            toast.style.opacity = '0';
        }, 3000);
    }
    
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
    
    // Test functions
    window.testNotification = function() {
        if (typeof triggerTestNotification === 'function') {
            triggerTestNotification();
        } else {
            alert('Fungsi notifikasi belum siap');
        }
    };
    
    window.testBadge = function() {
        if ('setAppBadge' in navigator) {
            navigator.setAppBadge(5).then(() => {
                alert('Badge set to 5!');
            }).catch(e => {
                alert('Badge error: ' + e.message);
            });
        } else {
            alert('Badge tidak didukung di browser ini');
        }
    };
    
    window.testSound = function() {
        const audio = new Audio('/assets/sounds/notification.mp3');
        audio.volume = 0.8;
        audio.play().then(() => {
            alert('Suara diputar!');
        }).catch(e => {
            alert('Gagal putar suara: ' + e.message);
        });
    };
    </script>
</body>
</html>

<?php
if (function_exists('logSystem')) {
    logSystem("Page accessed: " . basename($_SERVER['PHP_SELF']), ['user' => $_SESSION['username'] ?? 'unknown'], 'INFO', 'access.log');
}
?>