<?php
/**
 * MANAGER_DEVELOPER_CANVASING.PHP - LEADENGINE
 * Version: 5.0.0 - FIXED: Detail modal, konversi lead, anti duplikat
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek session manager developer
if (!isManagerDeveloper()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$developer_id = $_SESSION['developer_id'] ?? 0;
$manager_name = $_SESSION['nama_lengkap'] ?? 'Manager Developer';

if ($developer_id <= 0) {
    die("Error: Developer ID tidak valid");
}

// Ambil nama developer
$stmt = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
$stmt->execute([$developer_id]);
$developer_name = $stmt->fetchColumn() ?: 'Developer';

// Ambil semua marketing milik developer ini
$marketing_list = [];
$stmt = $conn->prepare("
    SELECT id, nama_lengkap, phone 
    FROM marketing_team 
    WHERE developer_id = ? AND is_active = 1 
    ORDER BY nama_lengkap
");
$stmt->execute([$developer_id]);
$marketing_list = $stmt->fetchAll();

// Ambil lokasi yang dikunjungi
$locations = [];
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
$page_subtitle = 'Monitor Aktivitas Canvasing Tim Marketing';
$page_icon = 'fas fa-camera-retro';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== MANAGER DEVELOPER CANVASING STYLES - MOBILE FIRST ===== */
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

/* ===== BASE LAYOUT - MOBILE FIRST ===== */
.main-content {
    padding: 12px;
    background: var(--bg);
    min-height: 100vh;
}

/* Desktop override */
@media (min-width: 769px) {
    .main-content {
        margin-left: 280px;
        padding: 24px;
    }
}

/* ===== TOP BAR - MOBILE OPTIMIZED ===== */
.top-bar {
    background: var(--surface);
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 16px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    gap: 12px;
}

@media (min-width: 480px) {
    .top-bar {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
    }
}

.welcome-text {
    display: flex;
    align-items: center;
    gap: 12px;
}

.welcome-text i {
    width: 48px;
    height: 48px;
    background: rgba(214,79,60,0.1);
    color: var(--secondary);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

@media (min-width: 768px) {
    .welcome-text i {
        width: 56px;
        height: 56px;
        font-size: 24px;
    }
}

.welcome-text h2 {
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
    margin: 0;
    line-height: 1.3;
}

.welcome-text h2 span {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-muted);
    margin-top: 2px;
}

@media (min-width: 768px) {
    .welcome-text h2 {
        font-size: 22px;
    }
    .welcome-text h2 span {
        font-size: 14px;
    }
}

.datetime {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg);
    padding: 8px 12px;
    border-radius: 30px;
    font-size: 12px;
    align-self: flex-start;
}

@media (min-width: 480px) {
    .datetime {
        align-self: auto;
    }
}

/* ===== FILTER BAR - MOBILE OPTIMIZED ===== */
.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.filter-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

@media (min-width: 640px) {
    .filter-form {
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
    }
}

.filter-select, .filter-input {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    background: white;
}

@media (min-width: 640px) {
    .filter-select, .filter-input {
        flex: 1;
        min-width: 150px;
    }
}

.filter-actions {
    display: flex;
    gap: 8px;
    width: 100%;
}

@media (min-width: 640px) {
    .filter-actions {
        width: auto;
    }
}

.filter-btn {
    flex: 1;
    padding: 12px 16px;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    white-space: nowrap;
}

.filter-btn.reset {
    background: var(--border);
    color: var(--text);
}

/* ===== STATS CARDS - GRID RESPONSIVE ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}

@media (min-width: 480px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
    }
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 14px;
    border-left: 4px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

@media (min-width: 768px) {
    .stat-card {
        padding: 20px;
        border-left-width: 6px;
    }
}

.stat-icon {
    font-size: 20px;
    color: var(--secondary);
    margin-bottom: 8px;
}

@media (min-width: 768px) {
    .stat-icon {
        font-size: 24px;
        margin-bottom: 12px;
    }
}

.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 2px;
}

.stat-value {
    font-size: 22px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

@media (min-width: 768px) {
    .stat-label {
        font-size: 13px;
    }
    .stat-value {
        font-size: 28px;
    }
}

/* ===== MARKETING STATS - HORIZONTAL SCROLL ===== */
.marketing-stats {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    padding: 4px 0 12px 0;
    margin-bottom: 16px;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}

.marketing-stats::-webkit-scrollbar {
    height: 4px;
}

.marketing-stats::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.marketing-stats::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.marketing-stat-card {
    flex: 0 0 200px;
    background: white;
    border-radius: 16px;
    padding: 14px;
    border-left: 4px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

/* ===== LOCATION STATS - GRID ===== */
.location-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}

@media (min-width: 480px) {
    .location-stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 768px) {
    .location-stats {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
}

.location-stat-card {
    background: white;
    border-radius: 16px;
    padding: 12px;
    border-left: 4px solid var(--info);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

/* ===== CANVASING GRID - MOBILE FIRST ===== */
.canvasing-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 16px;
}

@media (min-width: 480px) {
    .canvasing-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
}

@media (min-width: 1024px) {
    .canvasing-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
}

.canvasing-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    transition: transform 0.2s;
    display: flex;
    flex-direction: column;
}

.canvasing-card:active {
    transform: scale(0.98);
}

@media (min-width: 768px) {
    .canvasing-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
}

.canvasing-photo {
    width: 100%;
    height: 160px;
    object-fit: cover;
    cursor: pointer;
    background: #f0f0f0;
}

@media (min-width: 768px) {
    .canvasing-photo {
        height: 180px;
    }
}

.photo-placeholder {
    width: 100%;
    height: 160px;
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 40px;
}

.photo-placeholder span {
    font-size: 12px;
    margin-top: 8px;
    color: var(--text-muted);
}

.canvasing-body {
    padding: 12px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

@media (min-width: 768px) {
    .canvasing-body {
        padding: 15px;
    }
}

.canvasing-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    flex-wrap: wrap;
    gap: 6px;
}

.canvasing-marketing {
    font-weight: 700;
    color: var(--primary);
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

.canvasing-marketing i {
    color: var(--secondary);
    font-size: 12px;
}

.canvasing-time {
    font-size: 10px;
    color: var(--text-muted);
    background: var(--bg);
    padding: 3px 8px;
    border-radius: 30px;
    white-space: nowrap;
}

.canvasing-location {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
}

.canvasing-detail {
    font-size: 11px;
    color: var(--text-light);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.canvasing-detail i {
    color: var(--secondary);
    width: 14px;
    font-size: 11px;
}

.canvasing-gps {
    background: var(--primary-soft);
    padding: 6px 8px;
    border-radius: 8px;
    font-size: 10px;
    color: var(--primary);
    margin: 6px 0 8px;
    word-break: break-all;
    line-height: 1.4;
}

.canvasing-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
    margin-top: auto;
}

.canvasing-actions .btn-view {
    grid-column: span 1;
}

.canvasing-actions .btn-lead {
    grid-column: span 1;
}

.btn-view, .btn-lead {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 6px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    text-decoration: none;
}

.btn-view {
    background: var(--primary);
}

.btn-view:hover {
    background: var(--primary-light);
}

.btn-lead {
    background: var(--secondary);
}

.btn-lead:hover {
    background: var(--secondary-light);
}

.btn-lead:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 40px 16px;
    background: white;
    border-radius: 20px;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 48px;
    color: #E0DAD3;
    margin-bottom: 12px;
}

.empty-state h4 {
    color: var(--text);
    margin-bottom: 6px;
    font-size: 16px;
}

.empty-state p {
    color: var(--text-muted);
    font-size: 13px;
}

/* ===== PAGINATION - MOBILE ===== */
.pagination {
    display: flex;
    justify-content: center;
    gap: 4px;
    margin-top: 16px;
    flex-wrap: wrap;
}

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 6px;
    border-radius: 10px;
    background: white;
    border: 2px solid var(--border);
    color: var(--text);
    text-decoration: none;
    font-weight: 600;
    font-size: 12px;
    cursor: pointer;
}

.pagination-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* ===== PHOTO MODAL ===== */
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
    padding: 16px;
}

.photo-modal.show {
    display: flex;
}

.photo-modal img {
    max-width: 100%;
    max-height: 90vh;
    border-radius: 12px;
}

.photo-modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 44px;
    height: 44px;
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 22px;
    color: white;
    font-size: 24px;
    cursor: pointer;
}

/* ===== MODAL KONVERSI KE LEAD ===== */
.convert-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    z-index: 100000;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.convert-modal.show {
    display: flex;
}

.convert-modal-content {
    background: white;
    border-radius: 24px;
    width: 100%;
    max-width: 400px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}

.convert-modal-header {
    padding: 16px 20px;
    border-bottom: 2px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.convert-modal-header h3 {
    color: var(--primary);
    font-size: 18px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.convert-modal-header h3 i {
    color: var(--secondary);
}

.convert-modal-close {
    width: 36px;
    height: 36px;
    background: var(--bg);
    border: none;
    border-radius: 10px;
    color: var(--text);
    font-size: 18px;
    cursor: pointer;
}

.convert-modal-body {
    padding: 20px;
}

.convert-modal-footer {
    padding: 16px 20px;
    border-top: 2px solid var(--border);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* ===== FOOTER ===== */
.footer {
    text-align: center;
    margin-top: 30px;
    padding: 16px;
    color: var(--text-muted);
    font-size: 11px;
    border-top: 1px solid var(--border);
}
</style>

<div class="main-content">
    
    <!-- TOP BAR -->
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
    
    <!-- STATS CARDS (LOAD VIA API) -->
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
    
    <!-- MARKETING STATS (LOAD VIA API) -->
    <div class="marketing-stats" id="marketingStatsContainer">
        <!-- Akan diisi via API -->
    </div>
    
    <!-- LOCATION STATS (LOAD VIA API) -->
    <div class="location-stats" id="locationStatsContainer">
        <!-- Akan diisi via API -->
    </div>
    
    <!-- FILTER BAR -->
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
    
    <!-- CANVASING LIST -->
    <div class="canvasing-grid" id="canvasingContainer">
        <div class="empty-state">
            <i class="fas fa-spinner fa-spin"></i>
            <h4>Memuat Data...</h4>
            <p>Silakan tunggu sebentar</p>
        </div>
    </div>
    
    <!-- PAGINATION -->
    <div class="pagination" id="paginationContainer"></div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Manager Developer Canvasing v5.0</p>
    </div>
    
</div>

<!-- PHOTO MODAL -->
<div class="photo-modal" id="photoModal" onclick="hidePhoto()">
    <button class="photo-modal-close" onclick="hidePhoto()">&times;</button>
    <img id="modalPhoto" src="" alt="Canvasing Photo">
</div>

<!-- MODAL KONVERSI KE LEAD -->
<div class="convert-modal" id="convertModal">
    <div class="convert-modal-content">
        <div class="convert-modal-header">
            <h3><i class="fas fa-user-plus"></i> Konversi ke Lead</h3>
            <button class="convert-modal-close" onclick="closeConvertModal()">&times;</button>
        </div>
        <div class="convert-modal-body">
            <p style="margin-bottom: 15px; color: var(--text);">Konversi data canvasing menjadi lead untuk marketing:</p>
            
            <div style="background: var(--primary-soft); border-radius: 12px; padding: 15px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <i class="fas fa-user" style="color: var(--secondary);"></i>
                    <span id="convertCustomerName" style="font-weight: 600;">-</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <i class="fab fa-whatsapp" style="color: #25D366;"></i>
                    <span id="convertCustomerPhone">-</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-map-marker-alt" style="color: var(--secondary);"></i>
                    <span id="convertLocation">-</span>
                </div>
            </div>
            
            <input type="hidden" id="convertCanvasingId" value="">
            <input type="hidden" id="convertMarketingId" value="">
            <input type="hidden" id="convertDeveloperId" value="">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: var(--primary);">
                    <i class="fas fa-tag"></i> Sumber Lead
                </label>
                <select id="convertSource" class="filter-select" style="width: 100%;">
                    <option value="canvasing_individual">Canvasing Individual</option>
                    <option value="canvasing_instansi">Canvasing Instansi</option>
                    <option value="canvasing_toko">Canvasing Toko</option>
                    <option value="canvasing_warung">Canvasing Warung</option>
                    <option value="canvasing_kantor">Canvasing Kantor</option>
                    <option value="canvasing_rumah">Canvasing Rumah</option>
                    <option value="canvasing_lainnya">Canvasing Lainnya</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: var(--primary);">
                    <i class="fas fa-sticky-note"></i> Catatan Awal (opsional)
                </label>
                <textarea id="convertNotes" class="filter-input" rows="3" style="width: 100%; resize: vertical;" placeholder="Hasil canvasing..."></textarea>
            </div>
        </div>
        <div class="convert-modal-footer">
            <button class="filter-btn reset" onclick="closeConvertModal()">Batal</button>
            <button class="filter-btn" onclick="confirmConvertToLead()">Konversi ke Lead</button>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;
let currentCanvasingData = [];

// ===== LOAD STATS =====
function loadStats() {
    fetch('api/manager_developer_canvasing_list.php?action=get_stats')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderStats(data.data);
            }
        })
        .catch(err => {
            console.error('Stats error:', err);
            document.getElementById('statsContainer').innerHTML = `
                <div class="stat-card" style="grid-column: span 4; text-align: center; color: var(--danger);">
                    <i class="fas fa-exclamation-triangle"></i> Gagal memuat stats
                </div>
            `;
        });
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

// ===== LOAD MARKETING STATS =====
function loadMarketingStats() {
    fetch('api/manager_developer_canvasing_list.php?action=get_marketing_stats')
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
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <div style="width: 36px; height: 36px; background: var(--primary-soft); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                        <i class="fas fa-user"></i>
                    </div>
                    <div style="font-weight: 700; color: var(--primary); font-size: 14px;">${m.nama_lengkap}</div>
                </div>
                <div style="display: flex; justify-content: space-around; margin: 8px 0;">
                    <div style="text-align: center;">
                        <div style="font-weight: 800; color: var(--secondary); font-size: 16px;">${m.total_canvasing}</div>
                        <div style="font-size: 9px; color: var(--text-muted);">Total</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-weight: 800; color: var(--secondary); font-size: 16px;">${m.locations_visited}</div>
                        <div style="font-size: 9px; color: var(--text-muted);">Lokasi</div>
                    </div>
                </div>
                <div style="font-size: 10px; color: var(--text-muted); text-align: center;">
                    Terakhir: ${last}
                </div>
            </div>
        `;
    });
    document.getElementById('marketingStatsContainer').innerHTML = html;
}

// ===== LOAD LOCATION STATS =====
function loadLocationStats() {
    fetch('api/manager_developer_canvasing_list.php?action=get_location_stats')
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
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <span style="font-size: 24px;">${l.icon || '📍'}</span>
                    <span style="font-weight: 700; color: var(--primary); font-size: 13px;">${l.display_name || l.location_key}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 11px; color: var(--text-muted);">Marketing: ${l.marketing_count}</div>
                        <div style="font-size: 10px; color: var(--text-muted);">Terakhir: ${last}</div>
                    </div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--secondary);">${l.total}</div>
                </div>
            </div>
        `;
    });
    document.getElementById('locationStatsContainer').innerHTML = html;
}

// ===== LOAD CANVASING LIST =====
function loadCanvasing(page = 1) {
    currentPage = page;
    
    const marketingId = document.getElementById('marketingFilter').value;
    const locationKey = document.getElementById('locationFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    const search = document.getElementById('searchInput').value;
    
    let url = `api/manager_developer_canvasing_list.php?action=get_list&page=${page}&limit=12`;
    if (marketingId) url += `&marketing_id=${marketingId}`;
    if (locationKey) url += `&location_key=${encodeURIComponent(locationKey)}`;
    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo) url += `&date_to=${dateTo}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    
    console.log('Fetching URL:', url);
    
    document.getElementById('canvasingContainer').innerHTML = `
        <div class="empty-state">
            <i class="fas fa-spinner fa-spin"></i>
            <h4>Memuat Data...</h4>
            <p>Silakan tunggu sebentar</p>
        </div>
    `;
    
    fetch(url)
        .then(res => {
            if (!res.ok) {
                throw new Error('HTTP error ' + res.status);
            }
            return res.json();
        })
        .then(data => {
            console.log('API Response:', data);
            
            if (data.success) {
                currentCanvasingData = data.data;
                renderCanvasing(data.data);
                renderPagination(data.pagination);
                totalPages = data.pagination.total_pages;
            } else {
                document.getElementById('canvasingContainer').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <h4>Gagal Memuat</h4>
                        <p>${data.message || 'Unknown error'}</p>
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
                    <p>Gagal terhubung ke server: ${err.message}</p>
                </div>
            `;
        });
}

// ===== RENDER CANVASING =====
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
        // Buat URL foto yang benar
        let photoUrl = '';
        let photoExists = false;
        
        if (item.photo_path) {
            if (item.photo_path.startsWith('uploads/')) {
                photoUrl = 'https://taufikmarie.com/' + item.photo_path;
            } else if (item.photo_path.startsWith('admin/')) {
                photoUrl = 'https://taufikmarie.com/' + item.photo_path;
            } else {
                photoUrl = 'https://taufikmarie.com/uploads/canvasing/developer_' + item.developer_id + '/' + item.photo_path;
            }
            photoExists = item.photo_exists === true;
        }
        
        const date = new Date(item.created_at).toLocaleDateString('id-ID', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });
        
        let customerDisplay = '';
        if (item.customer_name) {
            customerDisplay = item.customer_name;
        } else if (item.instansi_name) {
            customerDisplay = item.instansi_name + (item.pic_name ? ' (' + item.pic_name + ')' : '');
        } else {
            customerDisplay = '-';
        }
        
        let phoneDisplay = '';
        if (item.customer_phone) {
            phoneDisplay = item.customer_phone;
        } else if (item.pic_phone) {
            phoneDisplay = item.pic_phone;
        }
        
        // ===== BADGE STATUS =====
        let statusBadge = '';
        let disableConvert = false;
        
        if (!item.customer_phone && !item.pic_phone) {
            statusBadge = '<span style="background: #FF9800; color: white; padding: 2px 8px; border-radius: 30px; font-size: 10px; margin-left: 8px; display: inline-block;">Butuh No. HP</span>';
            disableConvert = true;
        } else if (item.converted_to_lead == 1) {
            statusBadge = '<span style="background: #4CAF50; color: white; padding: 2px 8px; border-radius: 30px; font-size: 10px; margin-left: 8px; display: inline-block;">Sudah Jadi Lead</span>';
            disableConvert = true;
        } else {
            statusBadge = '<span style="background: #2196F3; color: white; padding: 2px 8px; border-radius: 30px; font-size: 10px; margin-left: 8px; display: inline-block;">Siap Konversi</span>';
        }
        
        let photoSection = '';
        if (photoExists && photoUrl) {
            photoSection = `
                <img src="${photoUrl}" class="canvasing-photo" alt="Canvasing" 
                     onclick="showPhoto('${photoUrl}')"
                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'photo-placeholder\\' onclick=\\'showPhotoError(${item.id})\\'><i class=\\'fas fa-image\\'></i><span>Foto error</span></div>';">
            `;
        } else {
            photoSection = `
                <div class="photo-placeholder" onclick="showPhotoError(${item.id})">
                    <i class="fas fa-image"></i>
                    <span>Foto tidak tersedia</span>
                </div>
            `;
        }
        
        html += `
            <div class="canvasing-card">
                ${photoSection}
                
                <div class="canvasing-body">
                    <div class="canvasing-header">
                        <div class="canvasing-marketing">
                            <i class="fas fa-user"></i> ${item.marketing_name || 'Unknown'}
                            ${statusBadge}
                        </div>
                        <div class="canvasing-time">
                            <i class="far fa-clock"></i> ${date}
                        </div>
                    </div>
                    
                    <div class="canvasing-location">
                        ${item.icon || '📍'} ${item.location_display || item.location_key}
                    </div>
                    
                    <div class="canvasing-detail">
                        <i class="fas fa-user"></i> ${customerDisplay}
                    </div>
                    
                    ${phoneDisplay ? `
                    <div class="canvasing-detail">
                        <i class="fab fa-whatsapp"></i> ${phoneDisplay}
                    </div>
                    ` : ''}
                    
                    ${item.notes ? `
                    <div class="canvasing-detail">
                        <i class="fas fa-sticky-note"></i> ${item.notes.substring(0, 30)}${item.notes.length > 30 ? '...' : ''}
                    </div>
                    ` : ''}
                    
                    <div class="canvasing-gps">
                        <i class="fas fa-map-pin"></i> ${parseFloat(item.latitude).toFixed(6)}, ${parseFloat(item.longitude).toFixed(6)}
                    </div>
                    
                    <div class="canvasing-actions">
                        <button class="btn-view" onclick="showDetail(${item.id})">
                            <i class="fas fa-eye"></i> Detail
                        </button>
                        
                        ${disableConvert ? 
                            `<button class="btn-lead" style="opacity:0.5; cursor:not-allowed;" disabled>
                                <i class="fas fa-user-plus"></i> Tidak Tersedia
                            </button>` : 
                            `<button class="btn-lead" onclick="openConvertModal(${item.id})">
                                <i class="fas fa-user-plus"></i> Jadikan Lead
                            </button>`
                        }
                    </div>
                </div>
            </div>
        `;
    });
    
    document.getElementById('canvasingContainer').innerHTML = html;
}

// ===== RENDER PAGINATION =====
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

// ===== RESET FILTERS =====
function resetFilters() {
    document.getElementById('marketingFilter').value = '';
    document.getElementById('locationFilter').value = '';
    document.getElementById('dateFrom').value = '<?= date('Y-m-d', strtotime('-30 days')) ?>';
    document.getElementById('dateTo').value = '<?= date('Y-m-d') ?>';
    document.getElementById('searchInput').value = '';
    loadCanvasing(1);
}

// ===== SHOW PHOTO =====
function showPhoto(url) {
    document.getElementById('modalPhoto').src = url;
    document.getElementById('photoModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function showPhotoError(id) {
    alert(`Foto dengan ID ${id} tidak ditemukan di server.`);
}

function hidePhoto() {
    document.getElementById('photoModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHOW DETAIL - FIXED =====
function showDetail(id) {
    console.log('Show detail for ID:', id);
    console.log('Current data:', currentCanvasingData);
    
    const item = currentCanvasingData.find(c => c.id == id);
    if (!item) {
        alert('Data tidak ditemukan. Silakan refresh halaman.');
        return;
    }
    
    showDetailModal(item);
}

function showDetailModal(item) {
    const date = new Date(item.created_at).toLocaleString('id-ID');
    
    let photoUrl = '';
    if (item.photo_path) {
        if (item.photo_path.startsWith('uploads/')) {
            photoUrl = 'https://taufikmarie.com/' + item.photo_path;
        } else if (item.photo_path.startsWith('admin/')) {
            photoUrl = 'https://taufikmarie.com/' + item.photo_path;
        } else {
            photoUrl = 'https://taufikmarie.com/uploads/canvasing/developer_' + item.developer_id + '/' + item.photo_path;
        }
    }
    
    let customerInfo = '';
    if (item.customer_name) {
        customerInfo = `
            <div style="margin-bottom: 8px;">
                <strong>Nama Customer:</strong> ${item.customer_name}
            </div>
            ${item.customer_phone ? `
            <div style="margin-bottom: 8px;">
                <strong>No. WA:</strong> <a href="https://wa.me/${item.customer_phone}" target="_blank">${item.customer_phone}</a>
            </div>
            ` : ''}
        `;
    } else if (item.instansi_name) {
        customerInfo = `
            <div style="margin-bottom: 8px;">
                <strong>Instansi:</strong> ${item.instansi_name}
            </div>
            <div style="margin-bottom: 8px;">
                <strong>PIC:</strong> ${item.pic_name || '-'}
            </div>
            ${item.pic_phone ? `
            <div style="margin-bottom: 8px;">
                <strong>No. PIC:</strong> <a href="https://wa.me/${item.pic_phone}" target="_blank">${item.pic_phone}</a>
            </div>
            ` : ''}
        `;
    } else {
        customerInfo = '<div style="color: var(--text-muted);">Tidak ada data customer</div>';
    }
    
    const modalHtml = `
        <div class="modal" id="detailModal" style="display: flex;">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-info-circle"></i> Detail Canvasing #${item.id}</h2>
                    <button class="modal-close" onclick="closeDetailModal()">&times;</button>
                </div>
                <div class="modal-body">
                    ${photoUrl ? `
                    <div style="text-align: center; margin-bottom: 15px;">
                        <img src="${photoUrl}" style="max-width: 100%; max-height: 200px; border-radius: 8px;" 
                             onerror="this.onerror=null; this.src=''; this.parentElement.innerHTML='<div style=\\'padding: 20px; background: #f0f0f0; border-radius: 8px;\\'><i class=\\'fas fa-image\\' style=\\'font-size: 40px; color: #999;\\'></i><p>Foto error</p></div>';">
                    </div>
                    ` : ''}
                    
                    <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-weight: 700; color: var(--primary);">Marketing:</span>
                            <span>${item.marketing_name || '-'}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: 700; color: var(--primary);">Waktu:</span>
                            <span>${date}</span>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border);">
                        <div style="font-weight: 700; color: var(--primary); margin-bottom: 8px;">Lokasi:</div>
                        <div>${item.icon || '📍'} ${item.location_display || item.location_key}</div>
                        ${item.address ? `<div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">${item.address}</div>` : ''}
                    </div>
                    
                    <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border);">
                        <div style="font-weight: 700; color: var(--primary); margin-bottom: 8px;">Data Customer:</div>
                        ${customerInfo}
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <div style="font-weight: 700; color: var(--primary); margin-bottom: 8px;">GPS:</div>
                        <div style="background: var(--primary-soft); padding: 8px; border-radius: 8px; font-size: 12px;">
                            <div>Latitude: ${item.latitude}</div>
                            <div>Longitude: ${item.longitude}</div>
                            <div>Akurasi: ±${Math.round(item.accuracy)}m</div>
                            <div style="margin-top: 5px;">
                                <a href="https://www.google.com/maps?q=${item.latitude},${item.longitude}" target="_blank" style="color: var(--secondary);">
                                    <i class="fas fa-external-link-alt"></i> Buka di Google Maps
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    ${item.notes ? `
                    <div>
                        <div style="font-weight: 700; color: var(--primary); margin-bottom: 8px;">Catatan:</div>
                        <div style="background: var(--bg); padding: 8px; border-radius: 8px;">${item.notes}</div>
                    </div>
                    ` : ''}
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary" onclick="closeDetailModal()">Tutup</button>
                    ${(!item.customer_phone && !item.pic_phone) || item.converted_to_lead == 1 ? 
                        `<button class="btn-primary" style="opacity:0.5;" disabled>Tidak Dapat Dikonversi</button>` : 
                        `<button class="btn-primary" onclick="closeDetailModal(); openConvertModal(${item.id})">
                            <i class="fas fa-user-plus"></i> Jadikan Lead
                        </button>`
                    }
                </div>
            </div>
        </div>
    `;
    
    const oldModal = document.getElementById('detailModal');
    if (oldModal) oldModal.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.body.style.overflow = 'hidden';
}

function closeDetailModal() {
    const modal = document.getElementById('detailModal');
    if (modal) modal.remove();
    document.body.style.overflow = '';
}

// ===== KONVERSI KE LEAD - FIXED =====
function openConvertModal(canvasingId) {
    console.log('Open convert modal for ID:', canvasingId);
    console.log('Current data:', currentCanvasingData);
    
    const item = currentCanvasingData.find(c => c.id == canvasingId);
    if (!item) {
        alert('Data tidak ditemukan. Silakan refresh halaman.');
        return;
    }
    
    document.getElementById('convertCanvasingId').value = item.id;
    document.getElementById('convertMarketingId').value = item.marketing_id;
    document.getElementById('convertDeveloperId').value = item.developer_id;
    document.getElementById('convertCustomerName').textContent = item.customer_name || item.instansi_name || '-';
    document.getElementById('convertCustomerPhone').textContent = item.customer_phone || item.pic_phone || '-';
    document.getElementById('convertLocation').textContent = item.location_display || item.location_key;
    
    const sourceSelect = document.getElementById('convertSource');
    if (item.canvasing_type) {
        sourceSelect.value = 'canvasing_' + item.canvasing_type;
    } else {
        sourceSelect.value = 'canvasing_individual';
    }
    
    document.getElementById('convertNotes').value = item.notes || '';
    
    document.getElementById('convertModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeConvertModal() {
    document.getElementById('convertModal').classList.remove('show');
    document.body.style.overflow = '';
}

function confirmConvertToLead() {
    const canvasingId = document.getElementById('convertCanvasingId').value;
    const marketingId = document.getElementById('convertMarketingId').value;
    const developerId = document.getElementById('convertDeveloperId').value;
    const source = document.getElementById('convertSource').value;
    const notes = document.getElementById('convertNotes').value;
    
    console.log('Converting canvasing ID:', canvasingId);
    
    const item = currentCanvasingData.find(c => c.id == canvasingId);
    if (!item) {
        alert('Data tidak ditemukan. Silakan refresh halaman.');
        return;
    }
    
    const leadData = {
        canvasing_id: canvasingId,
        first_name: item.customer_name || item.pic_name || 'Customer',
        last_name: '',
        phone: item.customer_phone || item.pic_phone || '',
        location_key: item.location_key,
        address: item.address || '',
        notes: notes || item.notes || '',
        source: source,
        assigned_marketing_team_id: marketingId,
        developer_id: developerId
    };
    
    console.log('Lead Data:', leadData);
    
    if (!leadData.phone) {
        alert('Nomor telepon tidak tersedia');
        return;
    }
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    btn.disabled = true;
    
    fetch('api/convert_canvasing_to_lead.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(leadData)
    })
    .then(res => {
        console.log('Response status:', res.status);
        return res.json();
    })
    .then(data => {
        console.log('Response data:', data);
        
        if (data.success) {
            alert('✅ Berhasil dikonversi ke lead! ID Lead: ' + data.lead_id);
            closeConvertModal();
            loadCanvasing(currentPage);
            
            if (confirm('Lihat data lead sekarang?')) {
                window.location.href = 'marketing_leads.php';
            }
        } else {
            alert('❌ Gagal: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('❌ Terjadi kesalahan: ' + err.message);
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// ===== DATE TIME =====
function updateDateTime() {
    const now = new Date();
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { 
        hour12: false 
    });
}

// ===== ESC KEY =====
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hidePhoto();
        closeDetailModal();
        closeConvertModal();
    }
});

// ===== INIT =====
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