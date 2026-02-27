/**
 * MAIN.JS - TAUFIKMARIE.COM ULTIMATE
 * Version: 4.0.0 - TANPA TOAST SYSTEM, FOKUS PADA UTILITY & ANIMASI
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

(function() {
    'use strict';
    
    // ============================================
    // GLOBAL CONFIGURATION
    // ============================================
    const CONFIG = {
        debug: false,
        version: '4.0.0',
        apiUrl: '/admin/api/',
        whatsappNumber: '628133150078',
        marketingName: 'Taufik Marie',
        maintenanceMode: false,
        animationEnabled: true
    };
    
    // ============================================
    // UTILITY FUNCTIONS
    // ============================================
    const Utils = {
        log: function(...args) {
            if (CONFIG.debug) {
                console.log('[TM]', ...args);
            }
        },
        
        formatRupiah: function(number) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(number);
        },
        
        formatDate: function(date, withTime = false) {
            const d = new Date(date);
            const options = {
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            };
            if (withTime) {
                options.hour = '2-digit';
                options.minute = '2-digit';
            }
            return d.toLocaleDateString('id-ID', options);
        },
        
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        throttle: function(func, limit) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },
        
        getCookie: function(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null;
        },
        
        setCookie: function(name, value, days = 30) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = `expires=${date.toUTCString()}`;
            document.cookie = `${name}=${value}; ${expires}; path=/; SameSite=Lax`;
        },
        
        getUrlParams: function() {
            const params = new URLSearchParams(window.location.search);
            const result = {};
            for (const [key, value] of params) {
                result[key] = value;
            }
            return result;
        },
        
        getDeviceType: function() {
            const ua = navigator.userAgent;
            if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
                return 'tablet';
            }
            if (/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) {
                return 'mobile';
            }
            return 'desktop';
        },
        
        getBrowserLanguage: function() {
            return (navigator.language || navigator.userLanguage || 'id').split('-')[0];
        },
        
        copyToClipboard: function(text) {
            navigator.clipboard.writeText(text).then(() => {
                console.log('Teks berhasil disalin');
            }).catch(() => {
                console.log('Gagal menyalin teks');
            });
        },
        
        isInViewport: function(element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        },
        
        scrollTo: function(element, offset = 0, behavior = 'smooth') {
            const elementPosition = element.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - offset;
            
            window.scrollTo({
                top: offsetPosition,
                behavior: behavior
            });
        },
        
        scrollToTop: function(behavior = 'smooth') {
            window.scrollTo({
                top: 0,
                behavior: behavior
            });
        }
    };
    
    // ============================================
    // LOADING INDICATOR
    // ============================================
    const Loading = {
        overlay: null,
        
        init: function() {
            if (!document.getElementById('loadingOverlay')) {
                this.overlay = document.createElement('div');
                this.overlay.id = 'loadingOverlay';
                this.overlay.className = 'loading-overlay';
                this.overlay.innerHTML = `
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Memuat...</p>
                    </div>
                `;
                document.body.appendChild(this.overlay);
                
                // Add CSS if not exists
                if (!document.querySelector('#loading-styles')) {
                    const style = document.createElement('style');
                    style.id = 'loading-styles';
                    style.textContent = `
                        .loading-overlay {
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(255,255,255,0.95);
                            backdrop-filter: blur(5px);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            z-index: 999999;
                            opacity: 0;
                            pointer-events: none;
                            transition: opacity 0.3s;
                        }
                        .loading-overlay.show {
                            opacity: 1;
                            pointer-events: all;
                        }
                        .loading-spinner {
                            text-align: center;
                        }
                        .spinner {
                            width: 60px;
                            height: 60px;
                            border: 5px solid #E7F3EF;
                            border-top-color: #D64F3C;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                            margin: 0 auto 20px;
                        }
                        @keyframes spin {
                            to { transform: rotate(360deg); }
                        }
                    `;
                    document.head.appendChild(style);
                }
            } else {
                this.overlay = document.getElementById('loadingOverlay');
            }
        },
        
        show: function(message = 'Memuat...') {
            if (!this.overlay) this.init();
            const spinner = this.overlay.querySelector('.loading-spinner p');
            if (spinner) spinner.textContent = message;
            this.overlay.classList.add('show');
        },
        
        hide: function() {
            if (this.overlay) {
                this.overlay.classList.remove('show');
            }
        }
    };
    
    // ============================================
    // MODAL SYSTEM
    // ============================================
    const Modal = {
        modal: null,
        
        init: function() {
            if (!document.getElementById('modalSystem')) {
                this.modal = document.createElement('div');
                this.modal.id = 'modalSystem';
                this.modal.className = 'modal-system';
                this.modal.innerHTML = `
                    <div class="modal-content-system">
                        <div class="modal-header-system">
                            <h3 class="modal-title-system"></h3>
                            <button class="modal-close-system">&times;</button>
                        </div>
                        <div class="modal-body-system"></div>
                        <div class="modal-footer-system"></div>
                    </div>
                `;
                document.body.appendChild(this.modal);
                
                // Add CSS if not exists
                if (!document.querySelector('#modal-system-styles')) {
                    const style = document.createElement('style');
                    style.id = 'modal-system-styles';
                    style.textContent = `
                        .modal-system {
                            display: none;
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0,0,0,0.6);
                            backdrop-filter: blur(10px);
                            align-items: center;
                            justify-content: center;
                            z-index: 999999;
                            padding: 20px;
                        }
                        .modal-system.show {
                            display: flex;
                        }
                        .modal-content-system {
                            background: white;
                            border-radius: 30px;
                            max-width: 450px;
                            width: 100%;
                            max-height: 80vh;
                            overflow-y: auto;
                            padding: 30px;
                        }
                        .modal-header-system {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            margin-bottom: 20px;
                        }
                        .modal-title-system {
                            font-size: 20px;
                            font-weight: 700;
                            color: #1B4A3C;
                        }
                        .modal-close-system {
                            background: none;
                            border: none;
                            font-size: 24px;
                            cursor: pointer;
                            color: #4A5A54;
                        }
                        .modal-body-system {
                            margin-bottom: 20px;
                        }
                        .modal-footer-system {
                            display: flex;
                            justify-content: flex-end;
                            gap: 10px;
                        }
                    `;
                    document.head.appendChild(style);
                }
                
                this.modal.querySelector('.modal-close-system').addEventListener('click', () => this.hide());
                
                this.modal.addEventListener('click', (e) => {
                    if (e.target === this.modal) {
                        this.hide();
                    }
                });
            } else {
                this.modal = document.getElementById('modalSystem');
            }
        },
        
        show: function(options = {}) {
            if (!this.modal) this.init();
            
            const { title = '', body = '', footer = '', onClose = null } = options;
            
            this.modal.querySelector('.modal-title-system').textContent = title;
            this.modal.querySelector('.modal-body-system').innerHTML = body;
            this.modal.querySelector('.modal-footer-system').innerHTML = footer;
            
            this.modal.classList.add('show');
            this.onClose = onClose;
        },
        
        hide: function() {
            if (this.modal) {
                this.modal.classList.remove('show');
                if (this.onClose) {
                    this.onClose();
                    this.onClose = null;
                }
            }
        }
    };
    
    // ============================================
    // ANIMATION CONTROLLER
    // ============================================
    const Animation = {
        init: function() {
            if (!CONFIG.animationEnabled) return;
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });
            
            document.querySelectorAll('.animate-on-scroll').forEach(el => {
                observer.observe(el);
            });
            
            // Add CSS if not exists
            if (!document.querySelector('#animation-styles')) {
                const style = document.createElement('style');
                style.id = 'animation-styles';
                style.textContent = `
                    .animate-on-scroll {
                        opacity: 0;
                        transform: translateY(30px);
                        transition: opacity 0.6s ease, transform 0.6s ease;
                    }
                    .animate-on-scroll.animate-in {
                        opacity: 1;
                        transform: translateY(0);
                    }
                `;
                document.head.appendChild(style);
            }
        },
        
        fadeIn: function(element, duration = 300) {
            element.style.opacity = '0';
            element.style.display = 'block';
            
            let start = null;
            const animate = (timestamp) => {
                if (!start) start = timestamp;
                const progress = timestamp - start;
                const opacity = Math.min(progress / duration, 1);
                element.style.opacity = opacity;
                
                if (progress < duration) {
                    requestAnimationFrame(animate);
                }
            };
            
            requestAnimationFrame(animate);
        },
        
        fadeOut: function(element, duration = 300) {
            let start = null;
            const animate = (timestamp) => {
                if (!start) start = timestamp;
                const progress = timestamp - start;
                const opacity = 1 - Math.min(progress / duration, 1);
                element.style.opacity = opacity;
                
                if (progress < duration) {
                    requestAnimationFrame(animate);
                } else {
                    element.style.display = 'none';
                }
            };
            
            requestAnimationFrame(animate);
        },
        
        slideDown: function(element, duration = 300) {
            const height = element.scrollHeight;
            element.style.overflow = 'hidden';
            element.style.maxHeight = '0';
            element.style.transition = `max-height ${duration}ms ease`;
            
            setTimeout(() => {
                element.style.maxHeight = height + 'px';
            }, 10);
            
            setTimeout(() => {
                element.style.maxHeight = 'none';
                element.style.overflow = 'visible';
            }, duration + 50);
        },
        
        slideUp: function(element, duration = 300) {
            const height = element.scrollHeight;
            element.style.overflow = 'hidden';
            element.style.maxHeight = height + 'px';
            element.style.transition = `max-height ${duration}ms ease`;
            
            setTimeout(() => {
                element.style.maxHeight = '0';
            }, 10);
        }
    };
    
    // ============================================
    // COUNTDOWN TIMER CLASS
    // ============================================
    class CountdownTimer {
        constructor(element, endTime, onComplete = null) {
            this.element = element;
            this.endTime = new Date(endTime).getTime();
            this.onComplete = onComplete;
            this.interval = null;
            this.start();
        }
        
        start() {
            this.interval = setInterval(() => this.update(), 1000);
            this.update();
        }
        
        update() {
            const now = new Date().getTime();
            const distance = this.endTime - now;
            
            if (distance < 0) {
                clearInterval(this.interval);
                if (this.onComplete) this.onComplete();
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            this.element.innerHTML = `
                <div class="countdown-block">
                    <span>${days}</span>
                    <small>Hari</small>
                </div>
                <div class="countdown-block">
                    <span>${hours}</span>
                    <small>Jam</small>
                </div>
                <div class="countdown-block">
                    <span>${minutes}</span>
                    <small>Menit</small>
                </div>
                <div class="countdown-block">
                    <span>${seconds}</span>
                    <small>Detik</small>
                </div>
            `;
        }
        
        stop() {
            clearInterval(this.interval);
        }
    }
    
    // ============================================
    // SCROLL MANAGER
    // ============================================
    const ScrollManager = {
        init: function() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', (e) => {
                    const href = anchor.getAttribute('href');
                    if (href === '#') return;
                    
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        Utils.scrollTo(target, 20);
                    }
                });
            });
        }
    };
    
    // ============================================
    // ACCORDION COMPONENT
    // ============================================
    class Accordion {
        constructor(container) {
            this.container = container;
            this.items = container.querySelectorAll('.accordion-item');
            this.init();
        }
        
        init() {
            this.items.forEach(item => {
                const header = item.querySelector('.accordion-header');
                const content = item.querySelector('.accordion-content');
                
                if (header && content) {
                    header.addEventListener('click', () => {
                        const isOpen = item.classList.contains('active');
                        
                        if (!isOpen) {
                            this.closeAll();
                            item.classList.add('active');
                            content.style.maxHeight = content.scrollHeight + 'px';
                        } else {
                            item.classList.remove('active');
                            content.style.maxHeight = '0';
                        }
                    });
                }
            });
        }
        
        closeAll() {
            this.items.forEach(item => {
                item.classList.remove('active');
                const content = item.querySelector('.accordion-content');
                if (content) {
                    content.style.maxHeight = '0';
                }
            });
        }
    }
    
    // ============================================
    // TAB COMPONENT
    // ============================================
    class Tabs {
        constructor(container) {
            this.container = container;
            this.tabs = container.querySelectorAll('.tab');
            this.panels = container.querySelectorAll('.tab-panel');
            this.init();
        }
        
        init() {
            this.tabs.forEach((tab, index) => {
                tab.addEventListener('click', () => {
                    this.activate(index);
                });
            });
        }
        
        activate(index) {
            this.tabs.forEach(tab => tab.classList.remove('active'));
            this.panels.forEach(panel => panel.classList.remove('active'));
            
            if (this.tabs[index]) this.tabs[index].classList.add('active');
            if (this.panels[index]) this.panels[index].classList.add('active');
        }
    }
    
    // ============================================
    // DROPDOWN COMPONENT
    // ============================================
    class Dropdown {
        constructor(element) {
            this.element = element;
            this.button = element.querySelector('.dropdown-toggle');
            this.menu = element.querySelector('.dropdown-menu');
            this.isOpen = false;
            this.init();
        }
        
        init() {
            if (!this.button || !this.menu) return;
            
            this.button.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });
            
            document.addEventListener('click', (e) => {
                if (!this.element.contains(e.target)) {
                    this.close();
                }
            });
        }
        
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        open() {
            this.isOpen = true;
            this.menu.classList.add('show');
            this.button.classList.add('active');
        }
        
        close() {
            this.isOpen = false;
            this.menu.classList.remove('show');
            this.button.classList.remove('active');
        }
    }
    
    // ============================================
    // VIDEO PLAYER
    // ============================================
    class VideoPlayer {
        constructor(videoElement) {
            this.video = videoElement;
            this.controls = videoElement.nextElementSibling;
            this.init();
        }
        
        init() {
            if (!this.video) return;
            
            this.video.addEventListener('click', () => this.togglePlay());
            
            this.video.addEventListener('play', () => this.updateControls());
            this.video.addEventListener('pause', () => this.updateControls());
            this.video.addEventListener('ended', () => this.updateControls());
        }
        
        togglePlay() {
            if (!this.video) return;
            
            if (this.video.paused) {
                this.video.play();
            } else {
                this.video.pause();
            }
        }
        
        updateControls() {
            if (this.controls && this.controls.querySelector('.play-pause')) {
                const playPauseBtn = this.controls.querySelector('.play-pause');
                if (playPauseBtn) {
                    playPauseBtn.textContent = this.video.paused ? '▶' : '⏸';
                }
            }
        }
    }
    
    // ============================================
    // LAZY LOAD IMAGES
    // ============================================
    const LazyLoad = {
        init: function() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.classList.add('loaded');
                            }
                            observer.unobserve(img);
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            } else {
                // Fallback for older browsers
                document.querySelectorAll('img[data-src]').forEach(img => {
                    img.src = img.dataset.src;
                });
            }
        }
    };
    
    // ============================================
    // FLOATING CTA
    // ============================================
    function initFloatingCTA() {
        const floatingCta = document.getElementById('floatingCta');
        const formSection = document.querySelector('.form-super');
        
        if (!floatingCta || !formSection) return;
        
        window.addEventListener('scroll', Utils.throttle(() => {
            const rect = formSection.getBoundingClientRect();
            const windowHeight = window.innerHeight;
            
            if (rect.bottom < 0 || rect.top > windowHeight) {
                floatingCta.classList.add('show');
            } else {
                floatingCta.classList.remove('show');
            }
        }, 100));
    }
    
    // ============================================
    // WHATSAPP BUTTON
    // ============================================
    function initWhatsAppButton() {
        const waButtons = document.querySelectorAll('[data-wa]');
        
        waButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                
                const phone = button.getAttribute('data-wa') || CONFIG.whatsappNumber;
                const message = button.getAttribute('data-message') || 'Halo, saya tertarik dengan informasi rumah subsidi.';
                
                window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
            });
        });
    }
    
    // ============================================
    // ROTATING TEXT
    // ============================================
    function initRotatingText() {
        const items = document.querySelectorAll('.rotating-item');
        if (items.length === 0) return;
        
        let currentIndex = 0;
        items[currentIndex].classList.add('active');
        
        function rotateText() {
            items[currentIndex].classList.remove('active');
            currentIndex = (currentIndex + 1) % items.length;
            items[currentIndex].classList.add('active');
        }
        
        setInterval(rotateText, 3000);
    }
    
    // ============================================
    // MAINTENANCE MODE
    // ============================================
    function enableMaintenanceMode() {
        const maintenanceHTML = `
            <div class="maintenance-mode" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: linear-gradient(135deg, #1B4A3C, #0A2A21);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999999;
                padding: 20px;
            ">
                <div style="
                    background: white;
                    border-radius: 40px;
                    padding: 40px 30px;
                    max-width: 400px;
                    text-align: center;
                ">
                    <div style="font-size: 60px; margin-bottom: 20px;">🛠️</div>
                    <h2 style="color: #1B4A3C; margin-bottom: 15px;">Website Sedang Maintenance</h2>
                    <p style="color: #4A5A54; margin-bottom: 25px;">Kami sedang melakukan perbaikan untuk memberikan pelayanan terbaik. Silakan kembali lagi nanti.</p>
                    <a href="https://wa.me/${CONFIG.whatsappNumber}" class="btn-wa-super" style="
                        display: inline-block;
                        background: #25D366;
                        color: white;
                        text-decoration: none;
                        padding: 15px 30px;
                        border-radius: 60px;
                        font-weight: 600;
                    " target="_blank">
                        <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                    </a>
                </div>
            </div>
        `;
        
        document.body.innerHTML = maintenanceHTML;
    }
    
    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        Utils.log('Main.js v4.0.0 initialized', { 
            device: Utils.getDeviceType(), 
            language: Utils.getBrowserLanguage() 
        });
        
        // Initialize core components (TANPA TOAST)
        Animation.init();
        ScrollManager.init();
        LazyLoad.init();
        
        // Initialize UI components
        initFloatingCTA();
        initRotatingText();
        initWhatsAppButton();
        
        // Initialize video players
        document.querySelectorAll('video').forEach(video => {
            new VideoPlayer(video);
        });
        
        // Initialize accordions
        document.querySelectorAll('.accordion').forEach(accordion => {
            new Accordion(accordion);
        });
        
        // Initialize tabs
        document.querySelectorAll('.tabs').forEach(tabs => {
            new Tabs(tabs);
        });
        
        // Initialize dropdowns
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            new Dropdown(dropdown);
        });
        
        // Maintenance mode check
        if (CONFIG.maintenanceMode) {
            enableMaintenanceMode();
        }
        
        // Add device and language classes to body
        document.body.classList.add(`device-${Utils.getDeviceType()}`);
        document.body.classList.add(`lang-${Utils.getBrowserLanguage()}`);
        
        // Hide loading overlay after page load
        setTimeout(() => {
            Loading.hide();
        }, 500);
        
        Utils.log('✅ All components initialized successfully');
    });
    
    // ============================================
    // WINDOW LOAD EVENT
    // ============================================
    window.addEventListener('load', function() {
        Utils.log('Window fully loaded');
        
        // Hide loading overlay if still visible
        Loading.hide();
    });
    
    // ============================================
    // EXPORT TO GLOBAL SCOPE (TANPA TOAST)
    // ============================================
    window.TM = {
        Utils: Utils,
        Loading: Loading,
        Modal: Modal,
        Animation: Animation,
        ScrollManager: ScrollManager,
        CountdownTimer: CountdownTimer,
        Accordion: Accordion,
        Tabs: Tabs,
        Dropdown: Dropdown,
        VideoPlayer: VideoPlayer,
        CONFIG: CONFIG
    };
    
})();