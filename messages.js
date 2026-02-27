/**
 * MESSAGES.JS - TAUFIKMARIE.COM
 * Version: 2.0.0 - JavaScript Khusus Halaman Pesan WhatsApp (BERFUNGSI)
 * FULL CODE
 */

(function() {
    'use strict';
    
    const sampleData = {
        name: 'Budi',
        full_name: 'Budi Santoso',
        marketing: 'Taufik Marie',
        location: 'Kertamulya Residence',
        icon: '🏡'
    };
    
    window.showLocation = function(locationKey, btn) {
        document.querySelectorAll('.message-card').forEach(card => {
            card.classList.remove('active');
        });
        document.getElementById('card_' + locationKey).classList.add('active');
        
        document.querySelectorAll('.tab-btn').forEach(tab => {
            tab.classList.remove('active');
        });
        btn.classList.add('active');
    };
    
    window.updateCounter = function(textareaId, counterId) {
        const textarea = document.getElementById(textareaId);
        const counter = document.getElementById(counterId);
        if (!textarea || !counter) return;
        
        const length = textarea.value.length;
        counter.textContent = length + ' karakter';
        
        if (length > 1000) {
            counter.style.color = '#D64F3C';
            counter.style.fontWeight = '700';
        } else if (length > 800) {
            counter.style.color = '#E9C46A';
            counter.style.fontWeight = '600';
        } else {
            counter.style.color = '#7A8A84';
            counter.style.fontWeight = '400';
        }
    };
    
    window.resetMessage = function(locationKey, type) {
        // Reload page to reset to defaults
        location.reload();
    };
    
    window.previewMessages = function(index) {
        const panel = document.getElementById('preview_' + index);
        const content = document.getElementById('preview_content_' + index);
        
        // Get the location key from the panel
        const locationKey = document.querySelectorAll('.accordion-item')[index]?.querySelector('input[name*="[pesan1]"]')?.id.replace('msg1_', '');
        
        if (!locationKey || !panel || !content) return;
        
        let previewText = '';
        
        for (let i = 1; i <= 3; i++) {
            const textarea = document.getElementById('msg' + i + '_' + locationKey);
            if (textarea) {
                let msg = textarea.value;
                msg = msg.replace(/{name}/g, sampleData.name);
                msg = msg.replace(/{full_name}/g, sampleData.full_name);
                msg = msg.replace(/{marketing}/g, sampleData.marketing);
                msg = msg.replace(/{location}/g, sampleData.location);
                msg = msg.replace(/{icon}/g, sampleData.icon);
                
                previewText += '📨 PESAN ' + i + ':\n' + msg + '\n\n' + '─'.repeat(40) + '\n\n';
            }
        }
        
        content.textContent = previewText;
        panel.style.display = 'block';
    };
    
    window.closePreview = function(index) {
        const panel = document.getElementById('preview_' + index);
        if (panel) {
            panel.style.display = 'none';
        }
    };
    
    window.copyPlaceholders = function() {
        const placeholders = '{name}, {full_name}, {marketing}, {location}, {icon}';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(placeholders).then(() => {
                if (typeof window.showToast === 'function') {
                    window.showToast('✅ Placeholder dicopy!');
                } else {
                    alert('✅ Placeholder dicopy!');
                }
            });
        } else {
            alert('❌ Gagal copy. Silakan copy manual.');
        }
    };
    
    // Auto active first card
    document.addEventListener('DOMContentLoaded', function() {
        const firstCard = document.querySelector('.message-card');
        if (firstCard) {
            firstCard.classList.add('active');
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
    
    // Confirm before leave
    let formChanged = false;
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('messagesForm');
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
    
    console.log('Messages JS loaded - BERFUNGSI');
})();