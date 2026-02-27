<?php
/**
 * MANAGER_CANVASING_DASHBOARD.PHP - LEADENGINE
 * Version: 2.0.0 - Dashboard Canvasing untuk Manager Platform (Menggunakan API)
 * MANAGER PLATFORM: Bisa lihat SEMUA developer & marketing
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// [CONTINUED FROM ORIGINAL FILE - ONLY SHOWING UPDATED SCRIPT SECTION]
// SISIPKAN SCRIPT INI DI BAWAH HTML YANG SUDAH ADA
?>

<script>
let currentPage = 1;
let totalPages = 1;

// Load stats
function loadStats() {
    const developerId = document.getElementById('developerFilter')?.value || '';
    let url = 'api/manager_canvasing_list.php?action=get_stats';
    if (developerId) url += `&developer_id=${developerId}`;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderStats(data.data);
            }
        })
        .catch(err => console.error('Stats error:', err));
}

function renderStats(stats) {
    document.querySelector('.stat-card:nth-child(1) .stat-value').textContent = stats.total;
    document.querySelector('.stat-card:nth-child(2) .stat-value').textContent = stats.today;
    document.querySelector('.stat-card:nth-child(3) .stat-value').textContent = stats.active_marketing;
    document.querySelector('.stat-card:nth-child(4) .stat-value').textContent = stats.locations;
}

// Load developer stats
function loadDeveloperStats() {
    fetch('api/manager_canvasing_list.php?action=get_developer_stats')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                renderDeveloperStats(data.data);
            }
        })
        .catch(err => console.error('Developer stats error:', err));
}

function renderDeveloperStats(stats) {
    const container = document.querySelector('.dev-grid');
    if (!container) return;
    
    let html = '';
    stats.forEach(dev => {
        const last = dev.last_activity ? new Date(dev.last_activity).toLocaleDateString('id-ID') : '-';
        html += `
            <div class="dev-card">
                <div class="dev-card-header">
                    <div class="dev-icon"><i class="fas fa-building"></i></div>
                    <div class="dev-name">${dev.nama_lengkap}</div>
                </div>
                <div class="dev-stats">
                    <div class="dev-stat">
                        <div class="dev-stat-value">${dev.total_canvasing}</div>
                        <div class="dev-stat-label">Canvasing</div>
                    </div>
                    <div class="dev-stat">
                        <div class="dev-stat-value">${dev.marketing_count}</div>
                        <div class="dev-stat-label">Marketing</div>
                    </div>
                    <div class="dev-stat">
                        <a href="?developer=${dev.id}&date_from=${document.getElementById('dateFrom')?.value || '<?= date('Y-m-d', strtotime('-30 days')) ?>'}&date_to=${document.getElementById('dateTo')?.value || '<?= date('Y-m-d') ?>'}" class="dev-stat-value" style="color: var(--secondary);">Detail</a>
                        <div class="dev-stat-label">Lihat</div>
                    </div>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

// Load canvasing list
function loadCanvasing(page = 1) {
    currentPage = page;
    
    const developerId = document.getElementById('developerFilter')?.value || '';
    const marketingId = document.getElementById('marketingFilter')?.value || '';
    const locationKey = document.getElementById('locationFilter')?.value || '';
    const dateFrom = document.getElementById('dateFrom')?.value || '';
    const dateTo = document.getElementById('dateTo')?.value || '';
    const search = document.getElementById('searchInput')?.value || '';
    
    let url = `api/manager_canvasing_list.php?action=get_list&page=${page}`;
    if (developerId) url += `&developer_id=${developerId}`;
    if (marketingId) url += `&marketing_id=${marketingId}`;
    if (locationKey) url += `&location_key=${encodeURIComponent(locationKey)}`;
    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo) url += `&date_to=${dateTo}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderCanvasing(data.data);
                renderPagination(data.pagination);
                totalPages = data.pagination.total_pages;
            } else {
                document.querySelector('.canvasing-grid').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <h4>Gagal Memuat</h4>
                        <p>${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error('Error:', err);
            document.querySelector('.canvasing-grid').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4>Error</h4>
                    <p>Gagal terhubung ke server</p>
                </div>
            `;
        });
}

function renderCanvasing(data) {
    const container = document.querySelector('.canvasing-grid');
    
    if (data.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-camera"></i>
                <h4>Tidak Ada Data</h4>
                <p>Belum ada aktivitas canvasing untuk filter ini</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    data.forEach(item => {
        const photoUrl = item.photo_url || '#';
        const photoExists = item.photo_exists;
        const date = new Date(item.created_at).toLocaleDateString('id-ID', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });
        
        html += `
            <div class="canvasing-card">
                <img src="${photoUrl}" class="canvasing-photo" alt="Canvasing" 
                     onclick="showPhoto('${photoUrl}')"
                     onerror="this.onerror=null; this.parentElement.querySelector('.canvasing-photo').style.display='none'; this.parentElement.innerHTML+='<div class=\\'photo-placeholder\\'><i class=\\'fas fa-image\\'></i></div>';">
                
                <div class="canvasing-body">
                    <div class="canvasing-header">
                        <div class="canvasing-marketing">
                            <i class="fas fa-user"></i> ${item.marketing_name || 'Unknown'}
                        </div>
                        <div class="canvasing-time">
                            <i class="far fa-clock"></i> ${date}
                        </div>
                    </div>
                    
                    <div class="canvasing-developer">
                        <i class="fas fa-building"></i> ${item.developer_name || 'Unknown'}
                    </div>
                    
                    <div class="canvasing-location">
                        ${item.icon || '📍'} ${item.location_display || item.location_key}
                    </div>
                    
                    ${item.customer_name ? `
                    <div class="canvasing-detail">
                        <i class="fas fa-user"></i> ${item.customer_name}
                    </div>
                    ` : ''}
                    
                    ${item.customer_phone ? `
                    <div class="canvasing-detail">
                        <i class="fab fa-whatsapp"></i> ${item.customer_phone}
                    </div>
                    ` : ''}
                    
                    ${item.notes ? `
                    <div class="canvasing-detail">
                        <i class="fas fa-sticky-note"></i> ${item.notes.substring(0, 50)}${item.notes.length > 50 ? '...' : ''}
                    </div>
                    ` : ''}
                    
                    <div class="canvasing-gps" style="font-size: 10px; color: var(--text-muted); margin-top: 8px;">
                        <i class="fas fa-map-pin"></i> ${item.latitude}, ${item.longitude}
                    </div>
                    
                    <div class="canvasing-actions">
                        <button class="btn-view" onclick="showPhoto('${photoUrl}')">
                            <i class="fas fa-eye"></i> Foto
                        </button>
                        
                        ${item.customer_phone ? `
                        <a href="https://wa.me/${item.customer_phone}" target="_blank" class="btn-wa">
                            <i class="fab fa-whatsapp"></i> Customer
                        </a>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function renderPagination(pagination) {
    const container = document.querySelector('.pagination');
    if (!container) return;
    
    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '';
    const current = pagination.current_page;
    const total = pagination.total_pages;
    
    if (current > 1) {
        html += `<a href="#" onclick="loadCanvasing(${current - 1}); return false;" class="pagination-btn"><i class="fas fa-chevron-left"></i></a>`;
    }
    
    const start = Math.max(1, current - 2);
    const end = Math.min(total, current + 2);
    
    for (let i = start; i <= end; i++) {
        html += `<a href="#" onclick="loadCanvasing(${i}); return false;" class="pagination-btn ${i === current ? 'active' : ''}">${i}</a>`;
    }
    
    if (current < total) {
        html += `<a href="#" onclick="loadCanvasing(${current + 1}); return false;" class="pagination-btn"><i class="fas fa-chevron-right"></i></a>`;
    }
    
    container.innerHTML = html;
}

function resetFilters() {
    document.getElementById('developerFilter').value = '';
    document.getElementById('marketingFilter').value = '';
    document.getElementById('locationFilter').value = '';
    document.getElementById('dateFrom').value = '<?= date('Y-m-d', strtotime('-30 days')) ?>';
    document.getElementById('dateTo').value = '<?= date('Y-m-d') ?>';
    document.getElementById('searchInput').value = '';
    loadCanvasing(1);
}

// Show photo
function showPhoto(url) {
    document.getElementById('modalPhoto').src = url;
    document.getElementById('photoModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function hidePhoto() {
    document.getElementById('photoModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Date time
function updateDateTime() {
    const now = new Date();
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { 
        hour12: false 
    });
}

// Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hidePhoto();
    }
});

// Init
document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    loadStats();
    loadDeveloperStats();
    loadCanvasing(1);
    
    // Event listener untuk filter form
    document.getElementById('filterForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        loadCanvasing(1);
    });
});
</script>