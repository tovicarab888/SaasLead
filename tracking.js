/**
 * TRACKING.JS - TAUFIKMARIE.COM
 * Version: 2.0.0 - JavaScript Khusus Halaman Tracking (BERFUNGSI)
 * FULL CODE
 */

(function() {
    'use strict';
    
    // Toggle accordion untuk tracking
    window.toggleAccordion = function(index) {
        if (typeof window.Admin !== 'undefined' && window.Admin.toggleAccordion) {
            window.Admin.toggleAccordion(index);
        } else if (typeof window.toggleAccordion === 'function') {
            window.toggleAccordion(index);
        }
    };
    
    // Open first accordion by default
    document.addEventListener('DOMContentLoaded', function() {
        if (document.querySelectorAll('.accordion-item').length > 0) {
            setTimeout(() => {
                if (typeof window.toggleAccordion === 'function') {
                    window.toggleAccordion(0);
                }
            }, 100);
        }
    });
    
    // Confirm before leave if changes made
    let formChanged = false;
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('trackingForm');
        if (form) {
            form.addEventListener('input', function() {
                formChanged = true;
            });
            
            form.addEventListener('change', function() {
                formChanged = true;
            });
        }
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'Ada perubahan yang belum disimpan. Yakin ingin keluar?';
        }
    });
    
    console.log('Tracking JS loaded - BERFUNGSI');
})();