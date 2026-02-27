/**
 * ADMIN.JS - TAUFIKMARIE.COM ULTIMATE DASHBOARD
 * Version: 6.0.1 - FIXED: SyntaxError dan fungsi lengkap
 * FULL CODE - SEMUA FUNGSI BEKERJA
 */

(function() {
    'use strict';
    
    // ===== STATE MANAGEMENT =====
    let currentLeadId = null;
    let currentLeadName = '';
    let exportDropdownActive = false;
    let currentUserRole = null;
    
    // ===== DETEKSI ROLE DARI SESSION =====
    function detectUserRole() {
        // Coba deteksi dari body class
        const bodyClasses = document.body.className;
        if (bodyClasses.includes('role-admin')) {
            currentUserRole = 'admin';
        } else if (bodyClasses.includes('role-manager')) {
            currentUserRole = 'manager';
        } else if (bodyClasses.includes('role-developer')) {
            currentUserRole = 'developer';
        } else {
            // Default cek dari meta tag atau variable PHP
            const metaRole = document.querySelector('meta[name="user-role"]');
            if (metaRole) {
                currentUserRole = metaRole.getAttribute('content');
            } else {
                currentUserRole = 'guest';
            }
        }
        console.log('User role detected:', currentUserRole);
    }
    
    // ===== JAM REAL-TIME INDONESIA =====
    function updateDateTime() {
        const now = new Date();
        
        // Format tanggal Indonesia
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        const dateStr = now.toLocaleDateString('id-ID', options);
        const dateElement = document.querySelector('#currentDate span');
        if (dateElement) dateElement.textContent = dateStr;
        
        // Format jam
        const timeStr = now.toLocaleTimeString('id-ID', { 
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        const timeElement = document.querySelector('#currentTime span');
        if (timeElement) timeElement.textContent = timeStr;
    }
    
    // ===== INITIALIZE =====
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Admin JS initialized - Version 6.0.1');
        
        // Deteksi role user
        detectUserRole();
        
        // Jalankan jam
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Set active menu berdasarkan URL
        setActiveMenu();
        
        // Detect mobile and adjust if needed
        checkMobile();
        
        // Add touch events for mobile
        addMobileTouchEvents();
        
        // Initialize export buttons (dropdown saja, bukan action buttons)
        initExportButtons();
        
        // Close modals when clicking outside
        initModalCloseHandlers();
    });
    
    // ===== CHECK MOBILE =====
    function checkMobile() {
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            document.body.classList.add('is-mobile');
        } else {
            document.body.classList.remove('is-mobile');
        }
    }
    
    // ===== ADD MOBILE TOUCH EVENTS =====
    function addMobileTouchEvents() {
        const touchElements = document.querySelectorAll('.stat-card, .location-card, .score-card, .btn, .action-btn-small, .bottom-nav-item, .filter-btn');
        
        touchElements.forEach(el => {
            el.addEventListener('touchstart', function(e) {
                this.style.transform = 'scale(0.98)';
            }, { passive: true });
            
            el.addEventListener('touchend', function() {
                this.style.transform = '';
            });
            
            el.addEventListener('touchcancel', function() {
                this.style.transform = '';
            });
        });
    }
    
    // ===== SET ACTIVE MENU =====
    function setActiveMenu() {
        const currentPage = window.location.pathname.split('/').pop();
        const sidebarItems = document.querySelectorAll('.sidebar-item');
        const bottomNavItems = document.querySelectorAll('.bottom-nav-item');
        
        // Sidebar
        sidebarItems.forEach(item => {
            item.classList.remove('active');
            if (item.getAttribute('href')?.includes(currentPage)) {
                item.classList.add('active');
            }
        });
        
        // Bottom navigation
        bottomNavItems.forEach(item => {
            item.classList.remove('active');
            if (item.getAttribute('href')?.includes(currentPage)) {
                item.classList.add('active');
            }
        });
    }
    
    // ===== INIT EXPORT BUTTONS =====
    function initExportButtons() {
        console.log('Initializing export buttons...');
        
        const exportTrigger = document.querySelector('.export-dropdown > .export-btn');
        const exportDropdown = document.querySelector('.export-dropdown');
        
        if (exportTrigger && exportDropdown) {
            exportTrigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                exportDropdown.classList.toggle('active');
                exportDropdownActive = !exportDropdownActive;
            });
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (exportDropdown && !exportDropdown.contains(e.target) && exportDropdownActive) {
                exportDropdown.classList.remove('active');
                exportDropdownActive = false;
            }
        });
        
        // Export options - handle click
        document.querySelectorAll('.export-option').forEach(option => {
            option.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const format = this.dataset.format || 'csv';
                
                // Show loading toast
                if (typeof window.showToast === 'function') {
                    window.showToast(`Mengekspor ${format.toUpperCase()}...`);
                }
                
                // Open in new tab
                window.open(this.href, '_blank');
                
                // Close dropdown
                if (exportDropdown) {
                    exportDropdown.classList.remove('active');
                    exportDropdownActive = false;
                }
            });
        });
    }
    
    // ===== INIT MODAL CLOSE HANDLERS =====
    function initModalCloseHandlers() {
        // Close when clicking outside modal content
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal') && e.target.classList.contains('show')) {
                closeModal(e.target.id);
            }
        });
        
        // Close buttons
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    closeModal(modal.id);
                }
            });
        });
    }
    
    // ===== OPEN MODAL =====
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        // Close other modals
        document.querySelectorAll('.modal.show').forEach(m => {
            m.classList.remove('show');
        });
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    };
    
    // ===== CLOSE MODAL =====
    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    };
    
    // ===== CLOSE VIEW MODAL =====
    window.closeViewModal = function() {
        closeModal('viewModal');
    };
    
    // ===== CLOSE EDIT MODAL =====
    window.closeEditModal = function() {
        closeModal('editModal');
    };
    
    // ===== CLOSE DELETE MODAL =====
    window.closeDeleteModal = function() {
        closeModal('deleteModal');
        currentLeadId = null;
        currentLeadName = '';
    };
    
    // ===== CLOSE DUPLICATE MODAL =====
    window.closeDuplicateModal = function() {
        closeModal('duplicateModal');
    };
    
    // ===== SHOW TOAST =====
    window.showToast = function(message, type = 'info') {
        let toast = document.querySelector('.toast-message');
        
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'toast-message';
            document.body.appendChild(toast);
        }
        
        // Set background color based on type
        if (type === 'success') {
            toast.style.background = '#2A9D8F';
        } else if (type === 'error') {
            toast.style.background = '#D64F3C';
        } else {
            toast.style.background = '#1B4A3C';
        }
        
        toast.textContent = message;
        toast.style.opacity = '1';
        
        setTimeout(() => {
            toast.style.opacity = '0';
        }, 3000);
    };
    
    // ===== TOGGLE ACCORDION (untuk locations, messages, emails, tracking) =====
    window.toggleAccordion = function(index) {
        const content = document.getElementById('content_' + index);
        const icon = document.getElementById('icon_' + index);
        const header = document.getElementById('header_' + index);
        
        if (!content || !icon || !header) return;
        
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.style.transform = 'rotate(180deg)';
            header.style.borderBottomColor = '#D64F3C';
            header.style.background = 'linear-gradient(135deg, #E7F3EF 0%, #d4e8e0 100%)';
            
            // Scroll ke content di mobile
            if (window.innerWidth <= 768) {
                setTimeout(() => {
                    content.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            }
        } else {
            content.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
            header.style.borderBottomColor = 'transparent';
            header.style.background = 'linear-gradient(135deg, #F5F3F0 0%, #E7F3EF 100%)';
        }
    };
    
    // ===== COPY TO CLIPBOARD =====
    window.copyToClipboard = function(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                window.showToast('✅ Berhasil dicopy!', 'success');
            }).catch(() => {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    };
    
    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand('copy');
            window.showToast('✅ Berhasil dicopy!', 'success');
        } catch (err) {
            window.showToast('❌ Gagal copy', 'error');
        }
        
        document.body.removeChild(textarea);
    }
    
    // ===== HANDLE ORIENTATION CHANGE =====
    window.addEventListener('resize', function() {
        checkMobile();
    });
    
    // ===== PREVENT BODY SCROLL WHEN MODAL OPEN =====
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal') && e.target.classList.contains('show')) {
            e.target.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
    
    // ===== EXPORT FUNCTIONS =====
    window.Admin = {
        updateDateTime: updateDateTime,
        toggleAccordion: window.toggleAccordion,
        closeModal: window.closeModal,
        openModal: window.openModal,
        copyToClipboard: window.copyToClipboard,
        showToast: window.showToast,
        getCurrentRole: function() { return currentUserRole; }
    };
    
    console.log('Admin JS fully loaded - Version 6.0.1');
})();