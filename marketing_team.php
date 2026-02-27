<?php
/**
 * MARKETING_ACTIVITIES.PHP - LEADENGINE
 * Version: 1.0.0 - Aktivitas Marketing untuk Manager Developer
 * MOBILE FIRST UI - FILTER + PAGINATION
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// ========== CHECK AUTH ==========
if (!checkAuth()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Yang bisa akses: Admin, Developer, Manager Developer
if (!isAdmin() && !isDeveloper() && !isManagerDeveloper()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Admin, Developer, dan Manager Developer.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== TENTUKAN DEVELOPER ID ==========
$developer_id = 0;

if (isAdmin()) {
    // Admin bisa lihat semua, bisa filter
    $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
} elseif (isDeveloper()) {
    $developer_id = $_SESSION['user_id'];
} elseif (isManagerDeveloper()) {
    $developer_id = $_SESSION['developer_id'] ?? 0;
}

if ($developer_id <= 0 && !isAdmin()) {
    die("Error: Developer ID tidak valid");
}

// ========== FILTER ==========
$search = $_GET['search'] ?? '';
$marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Action types untuk filter
$action_types = [
    '' => 'Semua Aktivitas',
    'update_status' => '🔄 Update Status',
    'update_data' => '📝 Update Data',
    'follow_up' => '📞 Follow Up',
    'call' => '📱 Call',
    'whatsapp' => '💬 WhatsApp',
    'survey' => '📍 Survey',
    'booking' => '📝 Booking',
    'add_note' => '📌 Tambah Catatan',
    'cek_slik' => '🔍 Cek Slik',
    'utj' => '💰 UTJ',
    'pemberkasan' => '📋 Pemberkasan',
    'proses_bank' => '🏦 Proses Bank',
    'akad' => '📝 Akad',
    'serah_terima' => '🔑 Serah Terima'
];

// Ikon untuk setiap action
$action_icons = [
    'update_status' => '🔄',
    'update_data' => '📝',
    'follow_up' => '📞',
    'call' => '📱',
    'whatsapp' => '💬',
    'survey' => '📍',
    'booking' => '📝',
    'add_note' => '📌',
    'cek_slik' => '🔍',
    'utj' => '💰',
    'pemberkasan' => '📋',
    'proses_bank' => '🏦',
    'akad' => '📝',
    'serah_terima' => '🔑'
];

// ========== AMBIL MARKETING UNTUK FILTER ==========
$marketing_list = [];
if ($developer_id > 0) {
    $stmt = $conn->prepare("
        SELECT id, nama_lengkap FROM marketing_team 
        WHERE developer_id = ? AND is_active = 1 
        ORDER BY nama_lengkap
    ");
    $stmt->execute([$developer_id]);
    $marketing_list = $stmt->fetchAll();
} elseif (isAdmin() && $developer_id == 0) {
    // Admin lihat semua marketing
    $stmt = $conn->query("
        SELECT m.id, m.nama_lengkap, u.nama_lengkap as developer_name
        FROM marketing_team m
        JOIN users u ON m.developer_id = u.id
        ORDER BY u.nama_lengkap, m.nama_lengkap
    ");
    $marketing_list = $stmt->fetchAll();
}

// ========== BANGUN QUERY ==========
$sql = "
    SELECT a.*, 
           l.first_name, l.last_name, l.phone, l.location_key, l.status as lead_status,
           loc.display_name as location_display, loc.icon,
           m.nama_lengkap as marketing_name,
           u.nama_lengkap as developer_name
    FROM marketing_activities a
    LEFT JOIN leads l ON a.lead_id = l.id
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    LEFT JOIN marketing_team m ON a.marketing_id = m.id
    LEFT JOIN users u ON a.developer_id = u.id
    WHERE 1=1
";

$params = [];

if ($developer_id > 0) {
    $sql .= " AND a.developer_id = ?";
    $params[] = $developer_id;
}

if ($marketing_id > 0) {
    $sql .= " AND a.marketing_id = ?";
    $params[] = $marketing_id;
}

if (!empty($search)) {
    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR a.note_text LIKE ? OR m.nama_lengkap LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}

if (!empty($action_filter)) {
    $sql .= " AND a.action_type = ?";
    $params[] = $action_filter;
}

if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND DATE(a.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

// Count total
$count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as tmp";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get data
$sql .= " ORDER BY a.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

$page_title = 'Aktivitas Marketing';
$page_subtitle = 'Riwayat Aktivitas Tim Marketing';
$page_icon = 'fas fa-history';

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
    --text-light: #4A5A54;
    --text-muted: #7A8A84;
    --border: #E0DAD3;
    --primary-soft: #E7F3EF;
    --success: #2A9D8F;
    --warning: #E9C46A;
    --danger: #D64F3C;
    --info: #4A90E2;
}

.main-content {
    width: 100%;
    min-height: 100vh;
    padding: 12px;
    margin-left: 0 !important;
}

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
}

.welcome-text h2 {
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.3;
}

.welcome-text h2 span {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
    margin-top: 2px;
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

.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-select, .filter-input {
    flex: 1;
    min-width: 140px;
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    background: white;
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

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.stat-icon {
    font-size: 20px;
    color: var(--secondary);
    margin-bottom: 8px;
}

.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 20px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

/* Timeline Style */
.timeline {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.timeline-item {
    display: flex;
    gap: 12px;
    padding: 16px;
    background: #F8FAFC;
    border-radius: 16px;
    border-left: 4px solid var(--secondary);
    transition: all 0.2s;
}

.timeline-item:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.timeline-icon {
    width: 40px;
    height: 40px;
    background: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: var(--secondary);
    flex-shrink: 0;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}

.timeline-content {
    flex: 1;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    flex-wrap: wrap;
    gap: 8px;
}

.timeline-action {
    font-weight: 700;
    color: var(--primary);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.timeline-time {
    font-size: 11px;
    color: var(--text-muted);
    background: var(--primary-soft);
    padding: 4px 10px;
    border-radius: 30px;
    white-space: nowrap;
}

.timeline-marketing {
    font-size: 12px;
    color: var(--info);
    margin-bottom: 4px;
}

.timeline-customer {
    margin-bottom: 8px;
}

.timeline-customer a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
}

.timeline-location {
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.timeline-note {
    background: white;
    padding: 10px;
    border-radius: 10px;
    font-size: 12px;
    color: var(--text-light);
    border: 1px solid var(--border);
    margin-top: 6px;
}

.timeline-changes {
    display: flex;
    gap: 8px;
    margin-top: 6px;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 600;
}

.status-badge.old {
    background: #FEE2E2;
    color: var(--danger);
    text-decoration: line-through;
}

.status-badge.new {
    background: #D1FAE5;
    color: var(--success);
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

.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: #F8FAFC;
    border-radius: 20px;
}

.empty-state i {
    font-size: 48px;
    color: var(--border);
    margin-bottom: 12px;
}

.empty-state p {
    color: var(--text-muted);
}

.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 12px !important;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
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
    
    .timeline-item {
        flex-direction: column;
    }
    
    .timeline-icon {
        width: 36px;
        height: 36px;
        font-size: 18px;
    }
}

@media (min-width: 768px) {
    .main-content {
        margin-left: 280px !important;
        padding: 24px !important;
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
    
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
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
    
    <!-- STATS CARDS -->
    <?php
    // Hitung statistik
    $total_activities = count($activities);
    $unique_marketing = 0;
    $unique_leads = 0;
    
    if ($developer_id > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM marketing_activities WHERE developer_id = ?");
        $stmt->execute([$developer_id]);
        $total_activities = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT marketing_id) FROM marketing_activities WHERE developer_id = ?");
        $stmt->execute([$developer_id]);
        $unique_marketing = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT lead_id) FROM marketing_activities WHERE developer_id = ?");
        $stmt->execute([$developer_id]);
        $unique_leads = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT MAX(created_at) FROM marketing_activities WHERE developer_id = ?");
        $stmt->execute([$developer_id]);
        $last_activity = $stmt->fetchColumn();
    }
    ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-history"></i></div>
            <div class="stat-label">Total Aktivitas</div>
            <div class="stat-value"><?= number_format($total_activities) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Marketing Aktif</div>
            <div class="stat-value"><?= $unique_marketing ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user"></i></div>
            <div class="stat-label">Lead Difollow</div>
            <div class="stat-value"><?= $unique_leads ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Terakhir</div>
            <div class="stat-value"><?= $last_activity ? date('d/m', strtotime($last_activity)) : '-' ?></div>
        </div>
    </div>
    
    <!-- FILTER BAR -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <?php if (isAdmin() && $developer_id == 0): ?>
            <select name="developer_id" class="filter-select">
                <option value="">Semua Developer</option>
                <?php foreach ($developers as $dev): ?>
                <option value="<?= $dev['id'] ?>" <?= $developer_id == $dev['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dev['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <select name="marketing_id" class="filter-select">
                <option value="">Semua Marketing</option>
                <?php foreach ($marketing_list as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $marketing_id == $m['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['nama_lengkap']) ?>
                    <?php if (isset($m['developer_name'])): ?> (<?= $m['developer_name'] ?>)<?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="action" class="filter-select">
                <?php foreach ($action_types as $key => $label): ?>
                <option value="<?= $key ?>" <?= $action_filter == $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" name="date_from" class="filter-input" value="<?= $date_from ?>">
            <input type="date" name="date_to" class="filter-input" value="<?= $date_to ?>">
            
            <input type="text" name="search" class="filter-input" placeholder="Cari customer/marketing..." value="<?= htmlspecialchars($search) ?>">
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="?" class="filter-btn reset">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- ACTIVITIES LIST -->
    <?php if (empty($activities)): ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p>Tidak ada aktivitas ditemukan</p>
    </div>
    <?php else: ?>
    
    <div class="timeline">
        <?php foreach ($activities as $act): 
            $full_name = trim(($act['first_name'] ?? '') . ' ' . ($act['last_name'] ?? ''));
            $icon = $action_icons[$act['action_type']] ?? '📋';
            $action_label = str_replace('_', ' ', $act['action_type']);
            $action_label = ucfirst($action_label);
        ?>
        <div class="timeline-item">
            <div class="timeline-icon">
                <?= $icon ?>
            </div>
            <div class="timeline-content">
                <div class="timeline-header">
                    <span class="timeline-action">
                        <?= $icon ?> <?= $action_label ?>
                    </span>
                    <span class="timeline-time">
                        <i class="far fa-clock"></i> <?= date('d/m/Y H:i', strtotime($act['created_at'])) ?>
                    </span>
                </div>
                
                <div class="timeline-marketing">
                    <i class="fas fa-user-tie"></i> <?= htmlspecialchars($act['marketing_name'] ?? 'Unknown') ?>
                    <?php if (!empty($act['developer_name'])): ?>
                    <span style="color: var(--text-muted);">(<?= htmlspecialchars($act['developer_name']) ?>)</span>
                    <?php endif; ?>
                </div>
                
                <div class="timeline-customer">
                    <?php if ($act['lead_id']): ?>
                    <a href="#" onclick="viewLead(<?= $act['lead_id'] ?>)" title="Lihat Detail Lead">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($full_name ?: 'Lead #' . $act['lead_id']) ?>
                    </a>
                    <?php else: ?>
                    <i class="fas fa-user"></i> Lead telah dihapus
                    <?php endif; ?>
                </div>
                
                <div class="timeline-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <?= htmlspecialchars($act['location_display'] ?? $act['location_key'] ?? 'Unknown') ?>
                    <?php if (!empty($act['phone'])): ?>
                    <span style="margin-left: 8px;">
                        <i class="fab fa-whatsapp" style="color: #25D366;"></i> <?= htmlspecialchars($act['phone']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($act['note_text'])): ?>
                <div class="timeline-note">
                    <i class="fas fa-quote-left" style="color: var(--secondary);"></i>
                    <?= nl2br(htmlspecialchars($act['note_text'])) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($act['status_before'] != $act['status_after']): ?>
                <div class="timeline-changes">
                    <span class="status-badge old"><?= htmlspecialchars($act['status_before'] ?? '-') ?></span>
                    <i class="fas fa-arrow-right" style="color: var(--secondary); font-size: 11px;"></i>
                    <span class="status-badge new"><?= htmlspecialchars($act['status_after'] ?? '-') ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&developer_id=<?= $developer_id ?>&marketing_id=<?= $marketing_id ?>&action=<?= urlencode($action_filter) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
        <a href="?page=<?= $i ?>&developer_id=<?= $developer_id ?>&marketing_id=<?= $marketing_id ?>&action=<?= urlencode($action_filter) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>" 
           class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page+1 ?>&developer_id=<?= $developer_id ?>&marketing_id=<?= $marketing_id ?>&action=<?= urlencode($action_filter) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Marketing Activities</p>
    </div>
    
</div>

<script>
function viewLead(id) {
    window.location.href = 'marketing_dashboard.php?lead_id=' + id;
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

setInterval(updateDateTime, 1000);
updateDateTime();
</script>

<?php include 'includes/footer.php'; ?>