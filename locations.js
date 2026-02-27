/**
 * LOCATIONS.JS - TAUFIKMARIE.COM ULTIMATE
 * Version: 3.0.0 - JavaScript Khusus Lokasi (BERFUNGSI)
 * FULL CODE - TANPA POTONGAN
 */

(function() {
    'use strict';
    
    // Update icon preview
    window.updateIcon = function(key, value) {
        const preview = document.getElementById('preview_icon_' + key);
        if (preview) {
            preview.textContent = value || '🏠';
        }
        updatePreview();
    };
    
    // Update header title
    window.updateHeader = function(key, value) {
        const card = document.querySelector(`#preview_icon_${key}`)?.closest('.accordion-item');
        if (card) {
            const header = card.querySelector('h3');
            if (header) {
                header.textContent = value;
            }
        }
        updatePreview();
    };
    
    // Update preview panel
    window.updatePreview = function() {
        const previewPanel = document.getElementById('previewPanel');
        const container = document.getElementById('previewContainer');
        
        if (!previewPanel || !container) return;
        
        const items = document.querySelectorAll('.accordion-item');
        let html = '';
        
        items.forEach(item => {
            const iconInput = item.querySelector('input[name*="[icon]"]');
            const nameInput = item.querySelector('input[name*="[display_name]"]');
            const cityInput = item.querySelector('input[name*="[city]"]');
            const activeCheck = item.querySelector('input[name*="[is_active]"]');
            
            if (iconInput && nameInput && cityInput) {
                const icon = iconInput.value || '🏠';
                const name = nameInput.value;
                const city = cityInput.value;
                const isActive = activeCheck ? activeCheck.checked : true;
                
                if (isActive) {
                    html += `
                        <div style="background: linear-gradient(135deg, #1B4A3C 0%, #2A5F4E 100%); padding: 20px; border-radius: 16px; color: white; box-shadow: 0 10px 20px rgba(27,74,60,0.2); border-left: 5px solid #D64F3C;">
                            <div style="font-size: 40px; margin-bottom: 10px; background: rgba(255,255,255,0.1); width: 70px; height: 70px; border-radius: 16px; display: flex; align-items: center; justify-content: center;">${icon}</div>
                            <div style="font-weight: 700; font-size: 18px; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${name}</div>
                            <div style="font-size: 13px; opacity: 0.9; display: flex; align-items: center; gap: 5px;"><i class="fas fa-map-marker-alt" style="color: #E3B584;"></i> ${city}</div>
                        </div>
                    `;
                }
            }
        });
        
        container.innerHTML = html;
        previewPanel.style.display = 'block';
        previewPanel.classList.add('show');
    };
    
    // Preview locations
    window.previewLocations = function() {
        updatePreview();
        document.getElementById('previewPanel').scrollIntoView({ behavior: 'smooth' });
    };
    
    // Confirm before leave if changes made
    let formChanged = false;
    
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('locationsForm');
        if (form) {
            form.addEventListener('input', function() {
                formChanged = true;
            });
            
            form.addEventListener('change', function() {
                formChanged = true;
            });
        }
        
        // Open first accordion by default
        if (document.querySelector('.accordion-item')) {
            setTimeout(() => {
                if (typeof window.toggleAccordion === 'function') {
                    window.toggleAccordion(0);
                }
            }, 100);
        }
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'Ada perubahan yang belum disimpan. Yakin ingin keluar?';
        }
    });
    
    console.log('Locations JS loaded - BERFUNGSI');
})();