/**
 * BACKUP.JS - TAUFIKMARIE.COM
 * Version: 2.0.1 - JavaScript Khusus Halaman Backup (BERFUNGSI)
 * FULL CODE
 */

(function() {
    'use strict';
    
    // Konfirmasi hapus backup
    window.confirmDelete = function(filename) {
        if (confirm('Yakin ingin menghapus backup ' + filename + '?')) {
            return true;
        }
        return false;
    };
    
    // Format file size (jika perlu)
    window.formatSize = function(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(2) + ' KB';
        if (bytes < 1073741824) return (bytes / 1048576).toFixed(2) + ' MB';
        return (bytes / 1073741824).toFixed(2) + ' GB';
    };
    
    console.log('Backup JS loaded - BERFUNGSI');
})();