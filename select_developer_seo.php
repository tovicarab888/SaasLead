<?php
/**
 * SELECT_DEVELOPER_SEO.PHP - HALAMAN PILIH DEVELOPER UNTUK SEO
 * Version: 3.0.0 - UI SUPER KEREN, HORIZONTAL DI MOBILE
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// ========== CHECK AUTH ==========
if (!checkAuth()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Hanya admin yang bisa akses
if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Super Admin.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== AMBIL DATA DEVELOPER ==========
$developers = $conn->query("
    SELECT 
        u.id, 
        u.nama_lengkap, 
        u.nama_perusahaan, 
        u.kota,
        u.folder_name,
        (SELECT COUNT(*) FROM developer_seo WHERE developer_id = u.id) as has_seo
    FROM users u
    WHERE u.role = 'developer' AND u.is_active = 1 
    ORDER BY u.nama_lengkap
")->fetchAll();

// ========== SET VARIABLES ==========
$page_title = 'SEO Developer';
$page_subtitle = 'Pilih Developer untuk Kelola SEO';
$page_icon = 'fas fa-code-branch';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== RESET & VARIABLES ===== */
:root {
    --primary: #1B4A3C;
    --primary-light: #2A5F4E;
    --secondary: #D64F3C;
    --secondary-light: #FF6B4A;
    --bg: #F5F3F0;
    --surface: #FFFFFF;
    --text: #1A2A24;
    --text-light: #4A5A54;
    --text-muted: #7A8A84;
    --border: #E0DAD3;
    --primary-soft: #E7F3EF;
    --success: #2A9D8F;
    --warning: #E9C46A;
    --danger: #D64F3C;
    --info: #4A90E2;
    --shadow-sm: 0 4px 12px rgba(0,0,0,0.05);
    --shadow-md: 0 8px 24px rgba(0,0,0,0.08);
    --shadow-lg: 0 16px 32px rgba(0,0,0,0.1);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-xl: 24px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
}

.main-content {
    width: 100%;
    padding: 12px;
}

/* ===== TOP BAR ===== */
.top-bar {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 16px;
    margin-bottom: 16px;
    border-left: 6px solid var(--secondary);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    gap: 12px;
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
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.welcome-text h2 {
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
}

.welcome-text h2 span {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
}

.datetime {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg);
    padding: 8px 16px;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 600;
    color: var(--primary);
}

.time {
    background: var(--surface);
    padding: 4px 12px;
    border-radius: 30px;
}

/* ===== SEARCH & FILTER ===== */
.search-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

@media (min-width: 768px) {
    .search-section {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.search-bar {
    background: white;
    border-radius: 60px;
    padding: 4px;
    display: flex;
    align-items: center;
    border: 2px solid var(--border);
    max-width: 400px;
    width: 100%;
}

.search-bar input {
    flex: 1;
    border: none;
    padding: 12px 18px;
    font-size: 14px;
    background: transparent;
    outline: none;
}

.search-bar button {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: none;
    background: var(--secondary);
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.search-bar button:hover {
    background: var(--secondary-light);
    transform: scale(1.05);
}

.filter-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border-radius: 40px;
    background: white;
    border: 1px solid var(--border);
    font-weight: 600;
    font-size: 13px;
    color: var(--text);
    cursor: pointer;
    transition: all 0.2s;
}

.filter-tab:hover {
    border-color: var(--secondary);
    color: var(--secondary);
}

.filter-tab.active {
    background: var(--secondary);
    border-color: var(--secondary);
    color: white;
}

/* ===== DEVELOPER GRID - HORIZONTAL DI MOBILE ===== */
.developer-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-top: 20px;
}

@media (min-width: 640px) {
    .developer-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
}

@media (min-width: 1024px) {
    .developer-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }
}

@media (min-width: 1400px) {
    .developer-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}

.developer-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 16px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
    height: 100%;
}

.developer-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
    border-color: var(--secondary);
}

.developer-card.seo-done {
    border-left: 5px solid var(--success);
}

.developer-card.seo-pending {
    border-left: 5px solid var(--warning);
}

.developer-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 700;
    z-index: 2;
    box-shadow: var(--shadow-sm);
}

.badge-done {
    background: var(--success);
    color: white;
}

.badge-pending {
    background: var(--warning);
    color: #1A2A24;
}

.developer-avatar {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    margin-bottom: 12px;
    flex-shrink: 0;
    box-shadow: 0 6px 12px rgba(27,74,60,0.2);
}

.developer-name {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 2px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.developer-company {
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 8px;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.developer-location {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: var(--text-light);
    margin-bottom: 12px;
}

.developer-location i {
    color: var(--secondary);
    font-size: 11px;
}

.folder-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--primary-soft);
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 10px;
    color: var(--primary);
    margin-bottom: 12px;
    width: fit-content;
    border: 1px solid rgba(214,79,60,0.1);
}

.folder-badge i {
    color: var(--secondary);
    font-size: 10px;
}

.developer-actions {
    display: flex;
    gap: 6px;
    margin-top: auto;
}

.btn-developer {
    flex: 1;
    padding: 10px 8px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 11px;
    text-decoration: none;
    text-align: center;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    border: none;
    cursor: pointer;
}

.btn-developer i {
    font-size: 11px;
}

.btn-edit {
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    box-shadow: 0 4px 10px rgba(214,79,60,0.2);
}

.btn-edit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(214,79,60,0.3);
}

.btn-view {
    background: var(--primary-soft);
    color: var(--primary);
    border: 1px solid var(--border);
}

.btn-view:hover {
    background: var(--primary);
    color: white;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
}

.empty-state i {
    font-size: 60px;
    color: #E0DAD3;
    margin-bottom: 16px;
}

.empty-state p {
    color: var(--text-muted);
    font-size: 15px;
}

/* ===== QUICK STATS ===== */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin: 20px 0;
}

.quick-stat {
    background: white;
    border-radius: var(--radius-md);
    padding: 16px 12px;
    text-align: center;
    border: 1px solid var(--border);
}

.quick-stat-value {
    font-size: 24px;
    font-weight: 800;
    color: var(--primary);
    line-height: 1.2;
}

.quick-stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
}

/* ===== FOOTER ===== */
.footer {
    text-align: center;
    margin-top: 40px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

/* ===== DESKTOP UPGRADE ===== */
@media (min-width: 768px) {
    .main-content {
        margin-left: 280px !important;
        padding: 24px !important;
        max-width: calc(100% - 280px);
    }
    
    .top-bar {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
    }
    
    .welcome-text i {
        width: 56px;
        height: 56px;
        font-size: 24px;
    }
    
    .welcome-text h2 {
        font-size: 22px;
    }
    
    .developer-name {
        font-size: 18px;
    }
    
    .btn-developer {
        padding: 12px 10px;
        font-size: 12px;
    }
    
    .quick-stats {
        gap: 20px;
    }
    
    .quick-stat-value {
        font-size: 28px;
    }
}
</style>

<div class="main-content">
    
    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="welcome-text">
            <i class="<?= $page_icon ?>"></i>
            <h2>
                <?= $page_title ?>
                <span><?= $page_subtitle ?></span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <!-- QUICK STATS -->
    <?php 
    $total_developer = count($developers);
    $seo_done = count(array_filter($developers, fn($d) => $d['has_seo'] > 0));
    $seo_pending = $total_developer - $seo_done;
    ?>
    <div class="quick-stats">
        <div class="quick-stat">
            <div class="quick-stat-value"><?= $total_developer ?></div>
            <div class="quick-stat-label">Total Developer</div>
        </div>
        <div class="quick-stat">
            <div class="quick-stat-value" style="color: var(--success);"><?= $seo_done ?></div>
            <div class="quick-stat-label">SEO Siap</div>
        </div>
        <div class="quick-stat">
            <div class="quick-stat-value" style="color: var(--warning);"><?= $seo_pending ?></div>
            <div class="quick-stat-label">Belum SEO</div>
        </div>
    </div>
    
    <!-- SEARCH & FILTER -->
    <div class="search-section">
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Cari developer..." onkeyup="filterDevelopers()">
            <button><i class="fas fa-search"></i></button>
        </div>
        
        <div class="filter-tabs">
            <span class="filter-tab active" onclick="filterByStatus('all')">Semua</span>
            <span class="filter-tab" onclick="filterByStatus('done')">Sudah SEO</span>
            <span class="filter-tab" onclick="filterByStatus('pending')">Belum SEO</span>
        </div>
    </div>
    
    <!-- DEVELOPER GRID -->
    <?php if (empty($developers)): ?>
    <div class="empty-state">
        <i class="fas fa-building"></i>
        <p>Belum ada developer aktif</p>
        <a href="developer_manager.php" class="btn-developer btn-edit" style="display: inline-block; width: auto; margin-top: 16px; padding: 12px 24px;">
            <i class="fas fa-plus"></i> Tambah Developer
        </a>
    </div>
    <?php else: ?>
    <div class="developer-grid" id="developerGrid">
        <?php foreach ($developers as $dev): 
            $has_seo = $dev['has_seo'] > 0;
            $status_class = $has_seo ? 'seo-done' : 'seo-pending';
            $badge_text = $has_seo ? '✅ SEO' : '⚠️ Baru';
            $badge_class = $has_seo ? 'badge-done' : 'badge-pending';
            $initial = strtoupper(substr($dev['nama_lengkap'], 0, 1));
            
            // Tentukan URL preview
            if (!empty($dev['folder_name'])) {
                $preview_url = '/' . $dev['folder_name'] . '/';
            } else {
                $preview_url = '/?dev_id=' . $dev['id'];
            }
        ?>
        <div class="developer-card <?= $status_class ?>" data-status="<?= $has_seo ? 'done' : 'pending' ?>" data-name="<?= strtolower($dev['nama_lengkap'] . ' ' . ($dev['nama_perusahaan'] ?? '')) ?>">
            <span class="developer-badge <?= $badge_class ?>"><?= $badge_text ?></span>
            
            <div class="developer-avatar">
                <?= $initial ?>
            </div>
            
            <div class="developer-name">
                <?= htmlspecialchars($dev['nama_lengkap']) ?>
            </div>
            
            <?php if (!empty($dev['nama_perusahaan'])): ?>
            <div class="developer-company">
                <?= htmlspecialchars($dev['nama_perusahaan']) ?>
            </div>
            <?php endif; ?>
            
            <div class="developer-location">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($dev['kota'] ?? 'Kuningan') ?>
            </div>
            
            <?php if (!empty($dev['folder_name'])): ?>
            <div class="folder-badge">
                <i class="fas fa-folder"></i> <?= $dev['folder_name'] ?>
            </div>
            <?php endif; ?>
            
            <div class="developer-actions">
                <a href="developer_seo.php?developer_id=<?= $dev['id'] ?>" class="btn-developer btn-edit">
    <i class="fas fa-edit"></i> <?= $has_seo ? 'Edit SEO' : 'Buat SEO' ?>
</a>
                <a href="<?= $preview_url ?>" target="_blank" class="btn-developer btn-view">
                    <i class="fas fa-external-link-alt"></i> Preview
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- ACTION BUTTON -->
    <div style="margin-top: 24px; text-align: center;">
        <a href="developer_manager.php" class="btn-developer btn-edit" style="display: inline-block; width: auto; padding: 14px 30px; font-size: 14px;">
            <i class="fas fa-plus-circle"></i> Tambah Developer Baru
        </a>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - SEO Manager v3.0</p>
    </div>
    
</div>

<script>
// Filter berdasarkan pencarian
function filterDevelopers() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.developer-card');
    
    cards.forEach(card => {
        const name = card.dataset.name || '';
        if (name.includes(search)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Filter berdasarkan status SEO
function filterByStatus(status) {
    // Update active tab
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    const cards = document.querySelectorAll('.developer-card');
    
    cards.forEach(card => {
        if (status === 'all') {
            card.style.display = 'flex';
        } else {
            const cardStatus = card.dataset.status;
            card.style.display = (cardStatus === status) ? 'flex' : 'none';
        }
    });
}

// Update datetime
function updateDateTime() {
    const now = new Date();
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { 
        hour12: false 
    });
}
setInterval(updateDateTime, 1000);
updateDateTime();
</script>

<?php include 'includes/footer.php'; ?>