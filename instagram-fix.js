/**
 * INSTAGRAM-FIX.JS - TAUFIKMARIE.COM ULTIMATE
 * Version: 3.0.0 - SUPER FIX for Instagram/Facebook In-App Browser
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 * 
 * Features:
 * - Detects all in-app browsers (Instagram, Facebook, Messenger, TikTok)
 * - Applies specific CSS fixes for each browser
 * - Ensures forms work properly in restricted environments
 * - Fixes 100vh issue on iOS
 * - Prevents zoom on input focus
 * - Improves touch targets
 */

(function() {
    'use strict';
    
    // ============================================
    // DETECTION FUNCTIONS
    // ============================================
    
    /**
     * Detects if current browser is Instagram in-app browser
     */
    function isInstagramBrowser() {
        const ua = navigator.userAgent || navigator.vendor || window.opera;
        
        // Instagram patterns
        const patterns = [
            /Instagram/i,
            /IGUS/i,
            /InstagramApp/i
        ];
        
        return patterns.some(pattern => pattern.test(ua));
    }
    
    /**
     * Detects if current browser is Facebook in-app browser
     */
    function isFacebookBrowser() {
        const ua = navigator.userAgent || navigator.vendor || window.opera;
        
        // Facebook patterns
        const patterns = [
            /FBAN/i,
            /FBAV/i,
            /FBIOS/i,
            /FB_IAB/i,
            /FB4A/i,
            /FBAV/i,
            /FBSV/i,
            /FBSS/i,
            /FBDV/i,
            /FBBV/i,
            /FBPN/i,
            /FBLC/i,
            /FBCR/i,
            /FBDM/i,
            /FBOP/i,
            /FBCA/i,
            /Facebook/i,
            /Meta/i
        ];
        
        return patterns.some(pattern => pattern.test(ua));
    }
    
    /**
     * Detects if current browser is Messenger in-app browser
     */
    function isMessengerBrowser() {
        const ua = navigator.userAgent || navigator.vendor || window.opera;
        
        // Messenger patterns
        const patterns = [
            /Messenger/i,
            /MESSENGER/i,
            /MSGR/i
        ];
        
        return patterns.some(pattern => pattern.test(ua));
    }
    
    /**
     * Detects if current browser is TikTok in-app browser
     */
    function isTikTokBrowser() {
        const ua = navigator.userAgent || navigator.vendor || window.opera;
        
        // TikTok patterns
        const patterns = [
            /TikTok/i,
            /musical/i,
            /musically/i,
            /ByteDance/i
        ];
        
        return patterns.some(pattern => pattern.test(ua));
    }
    
    /**
     * Detects if current browser is LINE in-app browser
     */
    function isLINEBrowser() {
        const ua = navigator.userAgent || navigator.vendor || window.opera;
        
        // LINE patterns
        const patterns = [
            /Line/i,
            /LINE/i
        ];
        
        return patterns.some(pattern => pattern.test(ua));
    }
    
    /**
     * Detects if current browser is any in-app browser
     */
    function isInAppBrowser() {
        return isInstagramBrowser() || isFacebookBrowser() || 
               isMessengerBrowser() || isTikTokBrowser() || 
               isLINEBrowser();
    }
    
    /**
     * Detects if device is iOS
     */
    function isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    }
    
    /**
     * Detects if device is Android
     */
    function isAndroid() {
        return /Android/.test(navigator.userAgent);
    }
    
    /**
     * Detects if device is mobile (any)
     */
    function isMobile() {
        return /Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(navigator.userAgent);
    }
    
    // ============================================
    // FIX FUNCTIONS
    // ============================================
    
    /**
     * Fix for iOS 100vh issue
     */
    function fixIOS100vh() {
        if (!isIOS()) return;
        
        const setVH = () => {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
            
            // Apply to elements with vh-based height
            document.querySelectorAll('.modal, .fullscreen, .hero-section').forEach(el => {
                if (el.classList.contains('modal')) {
                    el.style.height = '100vh';
                    el.style.height = 'calc(var(--vh, 1vh) * 100)';
                }
            });
        };
        
        setVH();
        window.addEventListener('resize', setVH);
        window.addEventListener('orientationchange', setVH);
    }
    
    /**
     * Fix for input zoom on focus (iOS)
     */
    function fixInputZoom() {
        if (!isIOS()) return;
        
        document.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('focus', () => {
                // Prevent default zoom by ensuring font size >= 16px
                if (el.style.fontSize < '16px') {
                    el.style.fontSize = '16px';
                }
                
                // Scroll to keep input in view
                setTimeout(() => {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
            
            el.addEventListener('blur', () => {
                // Restore font size if needed
                if (el.dataset.originalFontSize) {
                    el.style.fontSize = el.dataset.originalFontSize;
                }
            });
            
            // Store original font size
            el.dataset.originalFontSize = el.style.fontSize || '16px';
        });
    }
    
    /**
     * Fix for select dropdowns in Instagram/Facebook
     */
    function fixSelectDropdowns() {
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('touchstart', function(e) {
                // Ensure dropdown works
                this.style.opacity = '1';
                this.style.visibility = 'visible';
            });
            
            // Add custom styling for better UX
            select.classList.add('inapp-select');
        });
    }
    
    /**
     * Fix for modal scrolling in in-app browsers
     */
    function fixModalScrolling() {
        document.querySelectorAll('.modal, .modal-info, .modal-thankyou, .modal-protect').forEach(modal => {
            // Prevent body scroll when modal is open
            modal.addEventListener('show', function() {
                document.body.style.overflow = 'hidden';
            });
            
            modal.addEventListener('hide', function() {
                document.body.style.overflow = '';
            });
            
            // Fix touch events
            modal.addEventListener('touchmove', (e) => {
                if (e.target === modal) {
                    e.preventDefault();
                }
            }, { passive: false });
            
            const content = modal.querySelector('.modal-content, .info-card, .thankyou-card, .protect-card');
            if (content) {
                content.addEventListener('touchmove', (e) => {
                    e.stopPropagation();
                }, { passive: true });
            }
        });
    }
    
    /**
     * Fix for fixed positioning in in-app browsers
     */
    function fixFixedPositioning() {
        const floatingCta = document.getElementById('floatingCta');
        if (floatingCta && isInAppBrowser()) {
            floatingCta.style.position = 'sticky';
            floatingCta.style.position = '-webkit-sticky';
            floatingCta.style.bottom = '20px';
        }
    }
    
    /**
     * Fix for video autoplay in in-app browsers
     */
    function fixVideoAutoplay() {
        document.querySelectorAll('video').forEach(video => {
            // In-app browsers often block autoplay with sound
            if (isInAppBrowser()) {
                video.muted = true;
                video.playsInline = true;
                video.setAttribute('playsinline', '');
                
                // Try to play
                video.play().catch(() => {
                    console.log('Video autoplay prevented, user interaction needed');
                });
                
                // Add tap to unmute
                video.addEventListener('click', function() {
                    this.muted = false;
                });
            }
        });
    }
    
    /**
     * Inject CSS fixes for in-app browsers
     */
    function injectCSSFixes() {
        const browserType = [];
        if (isInstagramBrowser()) browserType.push('instagram');
        if (isFacebookBrowser()) browserType.push('facebook');
        if (isMessengerBrowser()) browserType.push('messenger');
        if (isTikTokBrowser()) browserType.push('tiktok');
        if (isLINEBrowser()) browserType.push('line');
        
        const browserClass = browserType.join(' ');
        
        const style = document.createElement('style');
        style.id = 'inapp-browser-fixes';
        style.textContent = `
            /* General in-app browser fixes */
            .inapp-browser .form-control,
            .inapp-browser .form-input,
            .inapp-browser select,
            .inapp-browser button {
                font-size: 16px !important;
                -webkit-appearance: none !important;
                appearance: none !important;
                border-radius: 18px !important;
            }
            
            .inapp-browser select {
                background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23D64F3C' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>") !important;
                background-repeat: no-repeat !important;
                background-position: right 16px center !important;
                background-size: 16px !important;
                padding-right: 45px !important;
            }
            
            .inapp-browser .btn-cta,
            .inapp-browser .btn-submit {
                padding: 18px 20px !important;
                font-size: 18px !important;
            }
            
            /* Instagram specific fixes */
            .inapp-browser.instagram .video-section video {
                object-fit: cover;
                background: #000;
            }
            
            .inapp-browser.instagram .modal-content {
                max-width: 90%;
                margin: 0 auto;
            }
            
            /* Facebook specific fixes */
            .inapp-browser.facebook .form-input {
                background-color: #f5f5f5 !important;
            }
            
            /* TikTok specific fixes */
            .inapp-browser.tiktok .countdown-block {
                min-width: 55px;
            }
            
            /* Mobile improvements */
            @media (max-width: 768px) {
                .inapp-browser .rating-super {
                    flex-wrap: wrap;
                    justify-content: center;
                }
                
                .inapp-browser .rating-item {
                    width: auto;
                }
            }
            
            /* Fix for tap highlight */
            .inapp-browser a,
            .inapp-browser button,
            .inapp-browser .benefit-card,
            .inapp-browser .countdown-block {
                -webkit-tap-highlight-color: rgba(214, 79, 60, 0.3);
            }
            
            /* Ensure text is readable */
            .inapp-browser .headline {
                font-size: clamp(28px, 8vw, 42px);
            }
            
            .inapp-browser .price-number {
                font-size: clamp(42px, 10vw, 64px);
            }
            
            /* Fix for modal in Instagram */
            .inapp-browser .modal-thankyou .thankyou-card {
                max-width: 380px;
                padding: 30px 20px;
            }
            
            .inapp-browser .marketing-info {
                flex-direction: column;
                text-align: center;
            }
            
            .inapp-browser .marketing-avatar {
                margin: 0 auto 10px;
            }
            
            /* Fix for floating CTA */
            .inapp-browser .floating-mobile {
                bottom: 10px;
                left: 10px;
                right: 10px;
            }
            
            .inapp-browser .floating-btn {
                padding: 16px;
                font-size: 16px;
            }
        `;
        
        document.head.appendChild(style);
        
        // Add class to body
        document.body.classList.add('inapp-browser');
        if (browserClass) {
            document.body.classList.add(...browserClass.split(' '));
        }
    }
    
    /**
     * Fix for double tap zoom
     */
    function fixDoubleTapZoom() {
        let lastTap = 0;
        document.addEventListener('touchend', function(e) {
            const currentTime = new Date().getTime();
            const tapLength = currentTime - lastTap;
            
            if (tapLength < 500 && tapLength > 0) {
                // Double tap detected
                e.preventDefault();
            }
            
            lastTap = currentTime;
        });
    }
    
    /**
     * Fix for orientation change
     */
    function fixOrientationChange() {
        window.addEventListener('orientationchange', function() {
            setTimeout(() => {
                // Force repaint
                document.body.style.display = 'none';
                document.body.offsetHeight;
                document.body.style.display = '';
                
                // Re-calc vh for iOS
                if (isIOS()) {
                    fixIOS100vh();
                }
            }, 200);
        });
    }
    
    // ============================================
    // MAIN INITIALIZATION
    // ============================================
    
    function init() {
        console.log('📱 Instagram Fix v3.0.0 - Detecting browser...');
        
        // Log detection results
        console.log({
            isInAppBrowser: isInAppBrowser(),
            isInstagram: isInstagramBrowser(),
            isFacebook: isFacebookBrowser(),
            isMessenger: isMessengerBrowser(),
            isTikTok: isTikTokBrowser(),
            isLINE: isLINEBrowser(),
            isIOS: isIOS(),
            isAndroid: isAndroid(),
            isMobile: isMobile()
        });
        
        if (isInAppBrowser()) {
            console.log('✅ In-app browser detected - applying fixes');
            
            // Apply all fixes
            injectCSSFixes();
            fixIOS100vh();
            fixInputZoom();
            fixSelectDropdowns();
            fixModalScrolling();
            fixFixedPositioning();
            fixVideoAutoplay();
            fixDoubleTapZoom();
            fixOrientationChange();
            
            // Force viewport settings
            const viewport = document.querySelector('meta[name="viewport"]');
            if (viewport) {
                viewport.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';
            }
            
            console.log('✅ All fixes applied successfully');
        } else {
            console.log('📱 Regular browser detected - no fixes needed');
            
            // Still add basic responsive fixes
            if (isMobile()) {
                document.body.classList.add('mobile-device');
            }
        }
    }
    
    // Run init after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // ============================================
    // EXPORT FOR DEBUGGING
    // ============================================
    window.InstagramFix = {
        isInAppBrowser,
        isInstagramBrowser,
        isFacebookBrowser,
        isMessengerBrowser,
        isTikTokBrowser,
        isLINEBrowser,
        isIOS,
        isAndroid,
        isMobile
    };
    
})();