<?php
/**
 * MANAGER_ACTIVITIES.PHP - TAUFIKMARIE.COM
 * Version: 2.1.0 - FIXED HEADER TEXT COLOR (PUTIH)
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

if (!checkAuth()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

if (!isAdmin() && !isManager()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Admin dan Manager.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== AMBIL SEMUA DEVELOPER & MARKETING UNTUK FILTER ==========
$developers = $conn->query("
    SELECT id, nama_lengkap FROM users WHERE role = 'developer' AND is_active = 1 ORDER BY nama_lengkap
")->fetchAll();

$marketing_list = $conn->query("
    SELECT id, nama_lengkap, developer_id FROM marketing_team WHERE is_active = 1 ORDER BY nama_lengkap
")->fetchAll();

// ========== FILTER ==========
$search = $_GET['search'] ?? '';
$developer_filter = isset($_GET['developer']) ? (int)$_GET['developer'] : 0;
$marketing_filter = isset($_GET['marketing']) ? (int)$_GET['marketing'] : 0;
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$page = (int)($_GET['page'] ?? 1);
$limit = 30;
$offset = ($page - 1) * $limit;

// ========== BUILD QUERY ==========
$sql = "
    SELECT a.*, 
           m.nama_lengkap as marketing_name,
           m.phone as marketing_phone,
           u.nama_lengkap as developer_name,
           l.first_name, l.last_name, l.phone as customer_phone, l.location_key
    FROM marketing_activities a
    LEFT JOIN marketing_team m ON a.marketing_id = m.id
    LEFT JOIN users u ON m.developer_id = u.id
    LEFT JOIN leads l ON a.lead_id = l.id
    WHERE 1=1
";
$params = [];

if ($developer_filter > 0) {
    $sql .= " AND m.developer_id = ?";
    $params[] = $developer_filter;
}

if ($marketing_filter > 0) {
    $sql .= " AND a.marketing_id = ?";
    $params[] = $marketing_filter;
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

if (!empty($search)) {
    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR m.nama_lengkap LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}

// Count total
$count_sql = str_replace("a.*, m.nama_lengkap as marketing_name, m.phone as marketing_phone, u.nama_lengkap as developer_name, l.first_name, l.last_name, l.phone as customer_phone, l.location_key", "COUNT(*)", $sql);
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get data
$sql .= " ORDER BY a.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

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

$page_title = 'Aktivitas Marketing';
$page_subtitle = 'Semua Aktivitas dari Seluruh Marketing';
$page_icon = 'fas fa-history';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== VARIABLES ===== */
:root {
    --primary: #1B4A3C;
    --primary-light: #2A5F4E;
    --secondary: #D64F3C;
    --secondary-light: #FF6B4A;
    --success: #2A9D8F;
    --warning: #E9C46A;
    --danger: #D64F3C;
    --info: #4A90E2;
    --bg: #F5F3F0;
    --surface: #FFFFFF;
    --text: #1A2A24;
    --text-light: #4A5A54;
    --text-muted: #7A8A84;
    --border: #E0DAD3;
    --primary-soft: #E7F3EF;
}

/* ===== MAIN LAYOUT ===== */
.main-content {
    margin-left: 280px;
    padding: 24px;
    background: var(--bg);
    min-height: 100vh;
}

/* ===== TOP BAR ===== */
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

/* ===== HEADER - TEXT PUTIH ===== */
.activities-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    color: white !important;
    box-shadow: 0 10px 25px rgba(27,74,60,0.3);
}

.activities-header h3,
.activities-header p,
.activities-header div,
.activities-header span {
    color: white !important;
}

.activities-header i {
    color: rgba(255,255,255,0.9) !important;
}

/* ===== FILTER BAR ===== */
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

.filter-btn.reset:hover {
    background: var(--text-muted);
    color: white;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

/* ===== HORIZONTAL SCROLL ACTIVITIES ===== */
.activities-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    padding: 8px 4px 20px 4px;
    scrollbar-width: thin;
    scrollbar-color: var(--secondary) var(--primary-soft);
    -webkit-overflow-scrolling: touch;
}

.activities-horizontal::-webkit-scrollbar {
    height: 6px;
}

.activities-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.activities-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.activity-card-horizontal {
    flex: 0 0 300px;
    background: white;
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-left: 4px solid var(--secondary);
    transition: transform 0.2s;
}

.activity-card-horizontal:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.activity-marketing {
    font-weight: 700;
    color: var(--primary);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.activity-icon {
    width: 32px;
    height: 32px;
    background: var(--primary-soft);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--secondary);
}

.activity-developer {
    font-size: 11px;
    color: var(--text-muted);
    background: var(--primary-soft);
    padding: 3px 8px;
    border-radius: 20px;
    white-space: nowrap;
}

.activity-time {
    font-size: 10px;
    color: var(--text-muted);
    background: var(--bg);
    padding: 3px 8px;
    border-radius: 20px;
    white-space: nowrap;
}

.activity-customer {
    font-weight: 600;
    font-size: 13px;
    color: var(--text);
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.activity-customer i {
    color: var(--secondary);
    margin-right: 4px;
}

.activity-detail {
    font-size: 12px;
    color: var(--text-light);
    margin-bottom: 8px;
    line-height: 1.4;
}

.activity-note {
    background: var(--primary-soft);
    padding: 10px;
    border-radius: 10px;
    font-size: 11px;
    color: var(--text);
    margin: 8px 0;
    border-left: 3px solid var(--secondary);
    max-height: 60px;
    overflow-y: auto;
}

.activity-footer {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.activity-footer a {
    flex: 1;
    padding: 6px 0;
    text-align: center;
    background: var(--bg);
    border-radius: 8px;
    color: var(--text);
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
    transition: all 0.2s;
}

.activity-footer a:hover {
    background: var(--secondary);
    color: white;
}

.activity-footer a.whatsapp {
    background: #25D366;
    color: white;
}

.activity-footer a.whatsapp:hover {
    background: #128C7E;
}

/* ===== TABLE UNTUK DESKTOP ===== */
.activities-table {
    display: block;
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    overflow-x: auto;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.table-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-badge {
    background: var(--primary-soft);
    color: var(--primary);
    padding: 6px 16px;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 600;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

th {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 2px solid var(--border);
}

td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: middle;
}

tr:hover td {
    background: rgba(231,243,239,0.3);
}

/* ===== PAGINATION ===== */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 24px;
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
    transform: translateY(-2px);
}

.pagination-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 20px;
}

.empty-state i {
    font-size: 60px;
    color: var(--border);
    margin-bottom: 16px;
}

.empty-state h3 {
    color: var(--text);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--text-muted);
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

/* ===== MOBILE RESPONSIVE ===== */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 12px !important;
    }
    
    .top-bar {
        flex-direction: column;
        align-items: flex-start;
        padding: 16px;
    }
    
    .datetime {
        width: 100%;
        justify-content: space-between;
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
    
    /* Tampilkan horizontal scroll di mobile, sembunyikan tabel */
    .activities-table {
        display: none;
    }
    
    .activities-horizontal {
        display: flex;
    }
    
    .activity-card-horizontal {
        flex: 0 0 280px;
    }
}

/* ===== DESKTOP ===== */
@media (min-width: 769px) {
    .activities-horizontal {
        display: none;
    }
    
    .activities-table {
        display: block;
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
    
    <!-- HEADER - TEXT PUTIH -->
    <div class="activities-header">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 30px; display: flex; align-items: center; justify-content: center; font-size: 28px;">
                <i class="fas fa-history" style="color: white !important;"></i>
            </div>
            <div>
                <h3 style="font-size: 20px; font-weight: 800; margin-bottom: 4px; color: white !important;">Aktivitas Marketing</h3>
                <p style="opacity: 0.9; color: white !important;">Monitor semua aktivitas follow up, update status, dan catatan dari seluruh marketing</p>
            </div>
        </div>
    </div>
    
    <!-- FILTER -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <select name="developer" class="filter-select">
                <option value="">Semua Developer</option>
                <?php foreach ($developers as $dev): ?>
                <option value="<?= $dev['id'] ?>" <?= $developer_filter == $dev['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dev['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="marketing" class="filter-select">
                <option value="">Semua Marketing</option>
                <?php foreach ($marketing_list as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $marketing_filter == $m['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="action" class="filter-select">
                <option value="">Semua Aktivitas</option>
                <?php foreach ($action_icons as $key => $icon): ?>
                <option value="<?= $key ?>" <?= $action_filter == $key ? 'selected' : '' ?>>
                    <?= $icon ?> <?= ucfirst(str_replace('_', ' ', $key)) ?>
                </option>
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
    
    <!-- INFO TOTAL -->
    <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <span class="table-badge">Total <?= $total_records ?> aktivitas</span>
        <span style="color: var(--text-muted); font-size: 13px;">Halaman <?= $page ?> dari <?= $total_pages ?></span>
    </div>
    
    <!-- ===== HORIZONTAL SCROLL UNTUK MOBILE ===== -->
    <?php if (!empty($activities)): ?>
    <div class="activities-horizontal">
        <?php foreach ($activities as $act): 
            $icon = $action_icons[$act['action_type']] ?? '📋';
            $action_name = ucfirst(str_replace('_', ' ', $act['action_type']));
            $full_name = trim(($act['first_name'] ?? '') . ' ' . ($act['last_name'] ?? ''));
            $status_change = ($act['status_before'] != $act['status_after']);
        ?>
        <div class="activity-card-horizontal">
            <div class="activity-header">
                <div class="activity-marketing">
                    <span class="activity-icon"><?= $icon ?></span>
                    <span><?= htmlspecialchars(substr($act['marketing_name'] ?? 'Unknown', 0, 15)) ?><?= strlen($act['marketing_name'] ?? '') > 15 ? '...' : '' ?></span>
                </div>
                <div>
                    <span class="activity-time"><?= date('d/m H:i', strtotime($act['created_at'])) ?></span>
                </div>
            </div>
            
            <div class="activity-customer">
                <i class="fas fa-user"></i> <?= htmlspecialchars(substr($full_name ?: 'Lead #' . $act['lead_id'], 0, 20)) ?><?= strlen($full_name ?: '') > 20 ? '...' : '' ?>
            </div>
            
            <div class="activity-developer" style="margin-bottom: 8px; display: inline-block;">
                <i class="fas fa-building"></i> <?= htmlspecialchars(substr($act['developer_name'] ?? 'Unknown', 0, 15)) ?>
            </div>
            
            <?php if ($status_change): ?>
            <div class="activity-detail">
                <span class="status-badge" style="font-size: 9px; padding: 2px 5px;"><?= substr($act['status_before'], 0, 8) ?></span>
                <i class="fas fa-arrow-right" style="margin: 0 4px; color: var(--secondary); font-size: 9px;"></i>
                <span class="status-badge" style="font-size: 9px; padding: 2px 5px;"><?= substr($act['status_after'], 0, 8) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($act['note_text'])): ?>
            <div class="activity-note">
                <i class="fas fa-quote-left" style="color: var(--secondary); font-size: 9px;"></i>
                <?= htmlspecialchars(substr($act['note_text'], 0, 60)) ?><?= strlen($act['note_text']) > 60 ? '...' : '' ?>
            </div>
            <?php endif; ?>
            
            <div class="activity-footer">
                <?php if (!empty($act['marketing_phone'])): ?>
                <a href="https://wa.me/<?= $act['marketing_phone'] ?>" target="_blank" class="whatsapp">
                    <i class="fab fa-whatsapp"></i> Marketing
                </a>
                <?php endif; ?>
                
                <?php if (!empty($act['customer_phone'])): ?>
                <a href="https://wa.me/<?= $act['customer_phone'] ?>" target="_blank" class="whatsapp">
                    <i class="fab fa-whatsapp"></i> Customer
                </a>
                <?php endif; ?>
                
                <?php if ($act['lead_id']): ?>
                <a href="#" onclick="viewLead(<?= $act['lead_id'] ?>)">
                    <i class="fas fa-eye"></i> Detail
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- ===== TABEL UNTUK DESKTOP ===== -->
    <div class="activities-table">
        <?php if (empty($activities)): ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <h3>Tidak Ada Aktivitas</h3>
            <p>Belum ada aktivitas marketing yang tercatat</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Marketing</th>
                    <th>Developer</th>
                    <th>Aktivitas</th>
                    <th>Customer</th>
                    <th>Perubahan</th>
                    <th>Catatan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $act): 
                    $icon = $action_icons[$act['action_type']] ?? '📋';
                    $action_name = ucfirst(str_replace('_', ' ', $act['action_type']));
                    $full_name = trim(($act['first_name'] ?? '') . ' ' . ($act['last_name'] ?? ''));
                ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($act['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($act['marketing_name'] ?? 'Unknown') ?></strong></td>
                    <td><?= htmlspecialchars($act['developer_name'] ?? '-') ?></td>
                    <td><?= $icon ?> <?= $action_name ?></td>
                    <td>
                        <?= htmlspecialchars($full_name ?: 'Lead #' . $act['lead_id']) ?>
                        <?php if (!empty($act['customer_phone'])): ?>
                        <br><small style="color: #25D366;"><?= $act['customer_phone'] ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($act['status_before'] != $act['status_after']): ?>
                        <span class="status-badge" style="font-size: 10px;"><?= $act['status_before'] ?></span>
                        <i class="fas fa-arrow-right" style="margin: 0 4px;"></i>
                        <span class="status-badge" style="font-size: 10px;"><?= $act['status_after'] ?></span>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($act['note_text'])): ?>
                        <span title="<?= htmlspecialchars($act['note_text']) ?>">
                            <?= htmlspecialchars(substr($act['note_text'], 0, 30)) ?>...
                        </span>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if (!empty($act['marketing_phone'])): ?>
                            <a href="https://wa.me/<?= $act['marketing_phone'] ?>" target="_blank" class="action-btn whatsapp" title="Chat Marketing">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($act['customer_phone'])): ?>
                            <a href="https://wa.me/<?= $act['customer_phone'] ?>" target="_blank" class="action-btn whatsapp" title="Chat Customer">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($act['lead_id']): ?>
                            <button class="action-btn view" onclick="viewLead(<?= $act['lead_id'] ?>)" title="Lihat Lead">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&developer=<?= $developer_filter ?>&marketing=<?= $marketing_filter ?>&action=<?= urlencode($action_filter) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&developer=<?= $developer_filter ?>&marketing=<?= $marketing_filter ?>&action=<?= urlencode($action_filter) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
               class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&developer=<?= $developer_filter ?>&marketing=<?= $marketing_filter ?>&action=<?= urlencode($action_filter) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> TaufikMarie.com - Aktivitas Marketing v2.1</p>
    </div>
    
</div>

<script>
function viewLead(id) {
    window.location.href = 'index.php?tab=leads&search=#' + id;
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

// Prevent form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php include 'includes/footer.php'; ?>