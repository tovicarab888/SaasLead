<?php
/**
 * DEVELOPER_CANVASING_DASHBOARD.PHP - LEADENGINE
 * Version: 3.0.0 - FIXED: UI Card, Foto muncul, Responsive
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

if (!isDeveloper()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$developer_id = $_SESSION['user_id'];
$developer_name = $_SESSION['nama_lengkap'] ?? 'Developer';

$stmt = $conn->prepare("
    SELECT id, nama_lengkap, phone 
    FROM marketing_team 
    WHERE developer_id = ? AND is_active = 1 
    ORDER BY nama_lengkap
");
$stmt->execute([$developer_id]);
$marketing_list = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT DISTINCT c.location_key, l.display_name, l.icon
    FROM canvasing_logs c
    LEFT JOIN locations l ON c.location_key = l.location_key
    WHERE c.developer_id = ?
    ORDER BY l.display_name
");
$stmt->execute([$developer_id]);
$locations = $stmt->fetchAll();

$page_title = 'Canvasing Dashboard';
$page_subtitle = 'Monitor Aktivitas Canvasing Marketing';
$page_icon = 'fas fa-camera-retro';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
:root {
    --primary: #1B4A3C;
    --primary-light: #2A5F4E;
    --secondary: #D64F3C;
    --secondary-light: #FF6B4A;
    --bg: #F5F3F0;
    --surface: #FFFFFF;
    --text: #1A2A24;
    --text-muted: #7A8A84;
    --border: #E0DAD3;
    --primary-soft: #E7F3EF;
    --success: #2A9D8F;
    --warning: #E9C46A;
    --danger: #D64F3C;
    --info: #4A90E2;
}

.main-content {
    margin-left: 280px;
    padding: 24px;
    background: var(--bg);
    min-height: 100vh;
}

.top-bar {
    background: var(--surface);
    border-radius: 20px;
    padding: 20px 24px;
    margin-bottom: 24px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.welcome-text {
    display: flex;
    align-items: center;
    gap: 16px;
}

.welcome-text i {
    width: 56px;
    height: 56px;
    background: rgba(214,79,60,0.1);
    color: var(--secondary);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.welcome-text h2 {
    font-size: 22px;
    font-weight: 800;
    color: var(--text);
    margin: 0;
    line-height: 1.3;
}

.welcome-text h2 span {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-muted);
    margin-top: 4px;
}

.datetime {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--bg);
    padding: 10px 20px;
    border-radius: 40px;
}

.date, .time {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--primary);
}

.time {
    background: var(--surface);
    padding: 6px 16px;
    border-radius: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.filter-form {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-select, .filter-input {
    flex: 1;
    min-width: 150px;
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    background: white;
}

.filter-select:focus, .filter-input:focus {
    border-color: var(--secondary);
    outline: none;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.filter-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}

.filter-btn.reset {
    background: var(--border);
    color: var(--text);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.stat-icon {
    font-size: 24px;
    color: var(--secondary);
    margin-bottom: 12px;
}

.stat-label {
    font-size: 13px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

.marketing-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}

.marketing-stat-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    border-left: 4px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.marketing-stat-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.marketing-stat-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-soft);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 18px;
}

.marketing-stat-name {
    font-weight: 700;
    color: var(--primary);
    font-size: 15px;
}

.marketing-stat-stats {
    display: flex;
    justify-content: space-around;
    margin-top: 10px;
}

.marketing-stat-item {
    text-align: center;
    flex: 1;
}

.marketing-stat-value {
    font-weight: 800;
    color: var(--secondary);
    font-size: 18px;
}

.marketing-stat-label {
    font-size: 10px;
    color: var(--text-muted);
}

.location-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}

.location-stat-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    border-left: 4px solid var(--info);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.location-stat-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.location-stat-icon {
    font-size: 32px;
}

.location-stat-name {
    font-weight: 700;
    color: var(--primary);
    font-size: 14px;
}

.location-stat-count {
    font-size: 24px;
    font-weight: 800;
    color: var(--secondary);
    text-align: right;
}

.canvasing-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.canvasing-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    transition: transform 0.3s;
    display: flex;
    flex-direction: column;
}

.canvasing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
}

.canvasing-photo {
    width: 100%;
    height: 200px;
    object-fit: cover;
    cursor: pointer;
    background: #f0f0f0;
    transition: opacity 0.3s;
}

.canvasing-photo:hover {
    opacity: 0.9;
}

.photo-placeholder {
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 48px;
    cursor: pointer;
}

.photo-placeholder span {
    font-size: 14px;
    margin-top: 10px;
    color: var(--text-muted);
}

.canvasing-body {
    padding: 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.canvasing-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 8px;
}

.canvasing-marketing {
    font-weight: 700;
    color: var(--primary);
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.canvasing-time {
    font-size: 11px;
    color: var(--text-muted);
    background: var(--bg);
    padding: 4px 10px;
    border-radius: 30px;
    white-space: nowrap;
}

.canvasing-location {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
}

.canvasing-detail {
    font-size: 13px;
    color: var(--text-light);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.canvasing-detail i {
    color: var(--secondary);
    width: 18px;
    font-size: 14px;
}

.canvasing-gps {
    background: var(--primary-soft);
    padding: 10px;
    border-radius: 10px;
    font-size: 11px;
    color: var(--primary);
    margin: 10px 0;
    word-break: break-all;
    line-height: 1.5;
}

.canvasing-actions {
    display: flex;
    gap: 10px;
    margin-top: auto;
    padding-top: 10px;
}

.btn-view {
    flex: 1;
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-view:hover {
    background: var(--primary-light);
}

.btn-wa {
    background: #25D366;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-wa:hover {
    background: #128C7E;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 20px;
    grid-column: span 3;
}

.empty-state i {
    font-size: 60px;
    color: #E0DAD3;
    margin-bottom: 16px;
}

.empty-state h4 {
    color: var(--text);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--text-muted);
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 8px;
    border-radius: 10px;
    background: white;
    border: 2px solid var(--border);
    color: var(--text);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s;
    cursor: pointer;
}

.pagination-btn:hover {
    background: var(--primary-soft);
    border-color: var(--primary);
}

.pagination-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.footer {
    text-align: center;
    margin-top: 40px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

.photo-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    z-index: 100000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.photo-modal.show {
    display: flex;
}

.photo-modal img {
    max-width: 100%;
    max-height: 90vh;
    border-radius: 10px;
}

.photo-modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 25px;
    color: white;
    font-size: 24px;
    cursor: pointer;
}

@media (max-width: 1200px) {
    .canvasing-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 12px !important;
    }
    
    .top-bar {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .datetime {
        width: 100%;
        justify-content: space-between;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .filter-select, .filter-input {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
    }
    
    .filter-btn {
        flex: 1;
    }
    
    .marketing-stats,
    .location-stats {
        grid-template-columns: 1fr;
    }
    
    .canvasing-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .empty-state {
        grid-column: span 1;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stat-card {
        padding: 12px;
    }
    
    .stat-value {
        font-size: 20px;
    }
    
    .canvasing-actions {
        flex-direction: column;
    }
    
    .btn-view, .btn-wa {
        width: 100%;
    }
}
</style>

<div class="main-content">
    
    <div class="top-bar">
        <div class="welcome-text">
            <i class="<?= $page_icon ?>"></i>
            <h2>
                <?= $page_title ?>
                <span><?= $page_subtitle ?> - <?= htmlspecialchars($developer_name) ?></span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <div class="stats-grid" id="statsContainer">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="stat-label">Memuat...</div>
            <div class="stat-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="stat-label">Memuat...</div>
            <div class="stat-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="stat-label">Memuat...</div>
            <div class="stat-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="stat-label">Memuat...</div>
            <div class="stat-value">-</div>
        </div>
    </div>
    
    <div class="marketing-stats" id="marketingStatsContainer"></div>
    
    <div class="location-stats" id="locationStatsContainer"></div>
    
    <div class="filter-bar">
        <form class="filter-form" id="filterForm" onsubmit="loadCanvasing(1); return false;">
            <select name="marketing_id" id="marketingFilter" class="filter-select">
                <option value="">Semua Marketing</option>
                <?php foreach ($marketing_list as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_lengkap']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <select name="location_key" id="locationFilter" class="filter-select">
                <option value="">Semua Lokasi</option>
                <?php foreach ($locations as $loc): ?>
                <option value="<?= htmlspecialchars($loc['location_key']) ?>">
                    <?= $loc['icon'] ?? '📍' ?> <?= htmlspecialchars($loc['display_name'] ?? $loc['location_key']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" name="date_from" id="dateFrom" class="filter-input" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
            <input type="date" name="date_to" id="dateTo" class="filter-input" value="<?= date('Y-m-d') ?>">
            
            <input type="text" name="search" id="searchInput" class="filter-input" placeholder="Cari marketing/customer...">
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
                <button type="button" class="filter-btn reset" onclick="resetFilters()">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </form>
    </div>
    
    <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
        <a href="#" onclick="exportData('json'); return false;" class="filter-btn" style="background: var(--info);">
            <i class="fas fa-file-code"></i> Export JSON
        </a>
        <a href="#" onclick="exportData('csv'); return false;" class="filter-btn" style="background: var(--success);">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>
    
    <div class="canvasing-grid" id="canvasingContainer">
        <div class="empty-state">
            <i class="fas fa-spinner fa-spin"></i>
            <h4>Memuat Data...</h4>
            <p>Silakan tunggu sebentar</p>
        </div>
    </div>
    
    <div class="pagination" id="paginationContainer"></div>
    
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Developer Canvasing Dashboard v3.0</p>
    </div>
    
</div>

<div class="photo-modal" id="photoModal" onclick="hidePhoto()">
    <button class="photo-modal-close" onclick="hidePhoto()">&times;</button>
    <img id="modalPhoto" src="" alt="Canvasing Photo">
</div>

<script>
let currentPage = 1;
let totalPages = 1;
let currentStats = null;

function loadStats() {
    fetch('api/developer_canvasing_list.php?action=get_stats')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentStats = data.data;
                renderStats(data.data);
            }
        })
        .catch(err => console.error('Stats error:', err));
}

function renderStats(stats) {
    const container = document.getElementById('statsContainer');
    container.innerHTML = `
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-camera"></i></div>
            <div class="stat-label">Total Canvasing</div>
            <div class="stat-value">${stats.total}</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-label">Hari Ini</div>
            <div class="stat-value">${stats.today}</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Marketing Aktif</div>
            <div class="stat-value">${stats.active_marketing}</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Terakhir</div>
            <div class="stat-value">${stats.last_activity ? new Date(stats.last_activity).toLocaleDateString('id-ID') : '-'}</div>
        </div>
    `;
}

function loadMarketingStats() {
    fetch('api/developer_canvasing_list.php?action=get_marketing_stats')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                renderMarketingStats(data.data);
            } else {
                document.getElementById('marketingStatsContainer').innerHTML = '';
            }
        })
        .catch(err => console.error('Marketing stats error:', err));
}

function renderMarketingStats(stats) {
    let html = '';
    stats.forEach(m => {
        const last = m.last_canvasing ? new Date(m.last_canvasing).toLocaleDateString('id-ID') : '-';
        html += `
            <div class="marketing-stat-card">
                <div class="marketing-stat-header">
                    <div class="marketing-stat-icon"><i class="fas fa-user"></i></div>
                    <div class="marketing-stat-name">${m.nama_lengkap}</div>
                </div>
                <div class="marketing-stat-stats">
                    <div class="marketing-stat-item">
                        <div class="marketing-stat-value">${m.total_canvasing}</div>
                        <div class="marketing-stat-label">Total</div>
                    </div>
                    <div class="marketing-stat-item">
                        <div class="marketing-stat-value">${m.locations_visited}</div>
                        <div class="marketing-stat-label">Lokasi</div>
                    </div>
                    <div class="marketing-stat-item">
                        <div class="marketing-stat-value">${m.active_days}</div>
                        <div class="marketing-stat-label">Hari</div>
                    </div>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); text-align: center; margin-top: 8px;">
                    Terakhir: ${last}
                </div>
            </div>
        `;
    });
    document.getElementById('marketingStatsContainer').innerHTML = html;
}

function loadLocationStats() {
    fetch('api/developer_canvasing_list.php?action=get_location_stats')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                renderLocationStats(data.data);
            } else {
                document.getElementById('locationStatsContainer').innerHTML = '';
            }
        })
        .catch(err => console.error('Location stats error:', err));
}

function renderLocationStats(stats) {
    let html = '';
    stats.forEach(l => {
        const last = l.last_visit ? new Date(l.last_visit).toLocaleDateString('id-ID') : '-';
        html += `
            <div class="location-stat-card">
                <div class="location-stat-header">
                    <div class="location-stat-icon">${l.icon || '📍'}</div>
                    <div class="location-stat-name">${l.display_name || l.location_key}</div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 12px; color: var(--text-muted);">Marketing: ${l.marketing_count}</div>
                        <div style="font-size: 11px; color: var(--text-muted);">Terakhir: ${last}</div>
                    </div>
                    <div class="location-stat-count">${l.total}</div>
                </div>
            </div>
        `;
    });
    document.getElementById('locationStatsContainer').innerHTML = html;
}

function loadCanvasing(page = 1) {
    currentPage = page;
    
    const marketingId = document.getElementById('marketingFilter').value;
    const locationKey = document.getElementById('locationFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    const search = document.getElementById('searchInput').value;
    
    let url = `api/developer_canvasing_list.php?action=get_list&page=${page}&limit=12`;
    if (marketingId) url += `&marketing_id=${marketingId}`;
    if (locationKey) url += `&location_key=${encodeURIComponent(locationKey)}`;
    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo) url += `&date_to=${dateTo}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    
    document.getElementById('canvasingContainer').innerHTML = `
        <div class="empty-state">
            <i class="fas fa-spinner fa-spin"></i>
            <h4>Memuat Data...</h4>
            <p>Silakan tunggu sebentar</p>
        </div>
    `;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderCanvasing(data.data);
                renderPagination(data.pagination);
                totalPages = data.pagination.total_pages;
            } else {
                document.getElementById('canvasingContainer').innerHTML = `
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
            document.getElementById('canvasingContainer').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4>Error</h4>
                    <p>Gagal terhubung ke server</p>
                </div>
            `;
        });
}

function renderCanvasing(data) {
    if (data.length === 0) {
        document.getElementById('canvasingContainer').innerHTML = `
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
        const date = new Date(item.created_at).toLocaleDateString('id-ID', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });
        
        let photoHtml = '';
        if (item.photo_exists && item.photo_url) {
            photoHtml = `<img src="${item.photo_url}" class="canvasing-photo" alt="Canvasing" onclick="showPhoto('${item.photo_url}')">`;
        } else {
            photoHtml = `
                <div class="photo-placeholder" onclick="showPhotoError(${item.id})">
                    <i class="fas fa-image"></i>
                    <span>Foto tidak ditemukan</span>
                </div>
            `;
        }
        
        html += `
            <div class="canvasing-card">
                ${photoHtml}
                
                <div class="canvasing-body">
                    <div class="canvasing-header">
                        <div class="canvasing-marketing">
                            <i class="fas fa-user"></i> ${item.marketing_name || 'Unknown'}
                        </div>
                        <div class="canvasing-time">
                            <i class="far fa-clock"></i> ${date}
                        </div>
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
                    
                    <div class="canvasing-gps">
                        <i class="fas fa-map-pin"></i> ${item.latitude}, ${item.longitude}
                        ${item.accuracy ? ` (akurasi ±${Math.round(item.accuracy)}m)` : ''}
                    </div>
                    
                    <div class="canvasing-actions">
                        ${item.photo_exists ? 
                            `<button class="btn-view" onclick="showPhoto('${item.photo_url}')">
                                <i class="fas fa-eye"></i> Lihat Foto
                            </button>` : 
                            `<button class="btn-view" onclick="showPhotoError(${item.id})" style="background: #999;">
                                <i class="fas fa-eye-slash"></i> Foto Rusak
                            </button>`
                        }
                        
                        ${item.customer_phone ? `
                        <a href="https://wa.me/${item.customer_phone}" target="_blank" class="btn-wa">
                            <i class="fab fa-whatsapp"></i> WA
                        </a>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    document.getElementById('canvasingContainer').innerHTML = html;
}

function renderPagination(pagination) {
    if (pagination.total_pages <= 1) {
        document.getElementById('paginationContainer').innerHTML = '';
        return;
    }
    
    let html = '';
    const current = pagination.current_page;
    const total = pagination.total_pages;
    
    if (current > 1) {
        html += `<button class="pagination-btn" onclick="loadCanvasing(${current - 1})"><i class="fas fa-chevron-left"></i></button>`;
    }
    
    const start = Math.max(1, current - 2);
    const end = Math.min(total, current + 2);
    
    for (let i = start; i <= end; i++) {
        html += `<button class="pagination-btn ${i === current ? 'active' : ''}" onclick="loadCanvasing(${i})">${i}</button>`;
    }
    
    if (current < total) {
        html += `<button class="pagination-btn" onclick="loadCanvasing(${current + 1})"><i class="fas fa-chevron-right"></i></button>`;
    }
    
    document.getElementById('paginationContainer').innerHTML = html;
}

function resetFilters() {
    document.getElementById('marketingFilter').value = '';
    document.getElementById('locationFilter').value = '';
    document.getElementById('dateFrom').value = '<?= date('Y-m-d', strtotime('-30 days')) ?>';
    document.getElementById('dateTo').value = '<?= date('Y-m-d') ?>';
    document.getElementById('searchInput').value = '';
    loadCanvasing(1);
}

function showPhoto(url) {
    document.getElementById('modalPhoto').src = url;
    document.getElementById('photoModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function showPhotoError(id) {
    alert(`Foto dengan ID ${id} tidak ditemukan di server. Mungkin file sudah dihapus.`);
}

function hidePhoto() {
    document.getElementById('photoModal').classList.remove('show');
    document.body.style.overflow = '';
}

function showDetail(id) {
    fetch(`api/developer_canvasing_list.php?action=get_detail&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showDetailModal(data.data);
            } else {
                alert('Gagal memuat detail: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Terjadi kesalahan');
        });
}

function exportData(format) {
    const marketingId = document.getElementById('marketingFilter').value;
    const locationKey = document.getElementById('locationFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    let url = `api/developer_canvasing_list.php?action=export&format=${format}`;
    if (marketingId) url += `&marketing_id=${marketingId}`;
    if (locationKey) url += `&location_key=${encodeURIComponent(locationKey)}`;
    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo) url += `&date_to=${dateTo}`;
    
    if (format === 'csv') {
        window.location.href = url;
    } else {
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    console.log('Export data:', data);
                    alert(`Berhasil mengexport ${data.total} data. Cek console.`);
                } else {
                    alert('Gagal export: ' + data.message);
                }
            })
            .catch(err => {
                console.error('Export error:', err);
                alert('Terjadi kesalahan');
            });
    }
}

function updateDateTime() {
    const now = new Date();
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { 
        hour12: false 
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hidePhoto();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    loadStats();
    loadMarketingStats();
    loadLocationStats();
    loadCanvasing(1);
});
</script>

<?php include 'includes/footer.php'; ?>