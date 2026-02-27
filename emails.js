/**
 * EMAILS.JS - TAUFIKMARIE.COM
 * Version: 1.0.0 - JavaScript Khusus Halaman Template Email
 * FULL CODE
 */

(function() {
    'use strict';
    
    const sampleData = {
        first_name: 'Budi',
        full_name: 'Budi Santoso',
        marketing_name: 'Taufik Marie',
        marketing_phone: '628133150078',
        location_display: 'Kertamulya Residence',
        icon: '🏡',
        customer_id: '12345',
        date: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
    };
    
    window.showLocation = function(locationKey, btn) {
        document.querySelectorAll('.email-card').forEach(card => {
            card.classList.remove('active');
        });
        document.getElementById('card_' + locationKey).classList.add('active');
        
        document.querySelectorAll('.tab-btn').forEach(tab => {
            tab.classList.remove('active');
        });
        btn.classList.add('active');
    };
    
    window.previewEmail = function(locationKey) {
        const subject = document.getElementById('subject_' + locationKey).value;
        let body = document.getElementById('body_' + locationKey).value;
        
        // Replace placeholders
        body = body.replace(/{first_name}/g, sampleData.first_name);
        body = body.replace(/{full_name}/g, sampleData.full_name);
        body = body.replace(/{marketing_name}/g, sampleData.marketing_name);
        body = body.replace(/{marketing_phone}/g, sampleData.marketing_phone);
        body = body.replace(/{location_display}/g, sampleData.location_display);
        body = body.replace(/{icon}/g, sampleData.icon);
        body = body.replace(/{customer_id}/g, sampleData.customer_id);
        body = body.replace(/{date}/g, sampleData.date);
        
        document.getElementById('preview_subject_' + locationKey).textContent = '📧 Subject: ' + subject;
        
        const iframe = document.getElementById('preview_iframe_' + locationKey);
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(body);
        iframeDoc.close();
        
        document.getElementById('preview_' + locationKey).classList.add('show');
    };
    
    window.closePreview = function(locationKey) {
        document.getElementById('preview_' + locationKey).classList.remove('show');
    };
    
    window.resetSubject = function(locationKey) {
        location.reload();
    };
    
    window.resetBody = function(locationKey) {
        location.reload();
    };
    
    // Auto active first card
    document.addEventListener('DOMContentLoaded', function() {
        const firstCard = document.querySelector('.email-card');
        if (firstCard) {
            firstCard.classList.add('active');
        }
    });
    
    // Confirm before leave
    let formChanged = false;
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('emailForm');
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
})();