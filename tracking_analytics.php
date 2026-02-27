<?php
/**
 * TRACKING_ANALYTICS.PHP - Analisis Performa Tracking Pixel
 * Version: 3.0.0 - UI SUPER KEREN (Mempertahankan UI Referensi)
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

// Hanya admin, manager, developer yang bisa akses
if (!isAdmin() && !isManager() && !isDeveloper() && !isManagerDeveloper()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== PAGINATION ==========
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// ========== FILTER ==========
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : null;
$pixel_type = $_GET['pixel_type'] ?? '';

// Filter berdasarkan role
if (isDeveloper()) {
    $developer_id = $_SESSION['user_id'];
} elseif (isManagerDeveloper() && isset($_SESSION['developer_id'])) {
    $developer_id = $_SESSION['developer_id'];
}

// ========== STATISTIK UTAMA ==========
$stats_sql = "
    SELECT 
        COUNT(*) as total_events,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        ROUND(AVG(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) * 100, 2) as success_rate,
        COUNT(DISTINCT lead_id) as unique_leads,
        COUNT(DISTINCT developer_id) as unique_developers
    FROM tracking_logs
    WHERE DATE(created_at) BETWEEN ? AND ?
";
$params = [$start_date, $end_date];

if ($developer_id !== null && $developer_id > 0) {
    $stats_sql .= " AND developer_id = ?";
    $params[] = $developer_id;
}

if (!empty($pixel_type)) {
    $stats_sql .= " AND pixel_type = ?";
    $params[] = $pixel_type;
}

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();

// ========== STATISTIK PER PIXEL TYPE ==========
$pixel_stats_sql = "
    SELECT 
        pixel_type,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        ROUND(AVG(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) * 100, 2) as success_rate
    FROM tracking_logs
    WHERE DATE(created_at) BETWEEN ? AND ?
";
$pixel_params = [$start_date, $end_date];

if ($developer_id !== null && $developer_id > 0) {
    $pixel_stats_sql .= " AND developer_id = ?";
    $pixel_params[] = $developer_id;
}

$pixel_stats_sql .= " GROUP BY pixel_type ORDER BY total DESC";

$pixel_stmt = $conn->prepare($pixel_stats_sql);
$pixel_stmt->execute($pixel_params);
$pixel_stats = $pixel_stmt->fetchAll();

// ========== STATISTIK PER DEVELOPER ==========
$dev_stats_sql = "
    SELECT 
        tl.developer_id,
        u.nama_lengkap as developer_name,
        COUNT(*) as total_events,
        SUM(CASE WHEN tl.status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN tl.status = 'failed' THEN 1 ELSE 0 END) as failed,
        ROUND(AVG(CASE WHEN tl.status = 'sent' THEN 1 ELSE 0 END) * 100, 2) as success_rate
    FROM tracking_logs tl
    LEFT JOIN users u ON tl.developer_id = u.id
    WHERE DATE(tl.created_at) BETWEEN ? AND ?
";
$dev_params = [$start_date, $end_date];

if ($developer_id !== null && $developer_id > 0) {
    $dev_stats_sql .= " AND tl.developer_id = ?";
    $dev_params[] = $developer_id;
}

$dev_stats_sql .= " GROUP BY tl.developer_id ORDER BY total_events DESC";

$dev_stmt = $conn->prepare($dev_stats_sql);
$dev_stmt->execute($dev_params);
$dev_stats = $dev_stmt->fetchAll();

// ========== DATA UNTUK CHART HARIAN ==========
$daily_sql = "
    SELECT 
        DATE(created_at) as tanggal,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM tracking_logs
    WHERE DATE(created_at) BETWEEN ? AND ?
";
$daily_params = [$start_date, $end_date];

if ($developer_id !== null && $developer_id > 0) {
    $daily_sql .= " AND developer_id = ?";
    $daily_params[] = $developer_id;
}

if (!empty($pixel_type)) {
    $daily_sql .= " AND pixel_type = ?";
    $daily_params[] = $pixel_type;
}

$daily_sql .= " GROUP BY DATE(created_at) ORDER BY tanggal ASC";

$daily_stmt = $conn->prepare($daily_sql);
$daily_stmt->execute($daily_params);
$daily_data = $daily_stmt->fetchAll();

// Format untuk chart
$chart_labels = [];
$chart_sent = [];
$chart_failed = [];
$chart_total = [];

foreach ($daily_data as $row) {
    $chart_labels[] = date('d/m', strtotime($row['tanggal']));
    $chart_sent[] = (int)$row['sent'];
    $chart_failed[] = (int)$row['failed'];
    $chart_total[] = (int)$row['total'];
}

// ========== LEAD TERBARU DENGAN PAGINATION ==========
$recent_sql = "
    SELECT 
        l.id,
        l.first_name,
        l.last_name,
        l.phone,
        l.location_key,
        loc.display_name as location_name,
        l.created_at as lead_created,
        COUNT(tl.id) as tracking_count,
        SUM(CASE WHEN tl.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN tl.status = 'failed' THEN 1 ELSE 0 END) as failed_count
    FROM leads l
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    LEFT JOIN tracking_logs tl ON l.id = tl.lead_id
    WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
";
$recent_params = [];

if ($developer_id !== null && $developer_id > 0) {
    $recent_sql .= " AND l.ditugaskan_ke = ?";
    $recent_params[] = $developer_id;
}

$recent_sql .= " GROUP BY l.id ORDER BY l.created_at DESC";

// Hitung total untuk pagination
$count_sql = "SELECT COUNT(*) as total FROM (" . $recent_sql . ") as temp";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($recent_params);
$total_recent = $count_stmt->fetch()['total'];
$total_pages = ceil($total_recent / $limit);

// Ambil data dengan pagination
$recent_sql .= " LIMIT ? OFFSET ?";
$recent_params[] = $limit;
$recent_params[] = $offset;

$recent_stmt = $conn->prepare($recent_sql);
$recent_stmt->execute($recent_params);
$recent_leads = $recent_stmt->fetchAll();

// ========== AMBIL DATA DEVELOPER UNTUK FILTER ==========
$dev_filter_sql = "SELECT id, nama_lengkap FROM users WHERE role = 'developer' AND is_active = 1 ORDER BY nama_lengkap";
if (isDeveloper()) {
    $dev_filter_sql = "SELECT id, nama_lengkap FROM users WHERE id = " . $_SESSION['user_id'];
} elseif (isManagerDeveloper() && isset($_SESSION['developer_id'])) {
    $dev_filter_sql = "SELECT id, nama_lengkap FROM users WHERE id = " . $_SESSION['developer_id'];
}
$developers = $conn->query($dev_filter_sql)->fetchAll();

// ========== SET VARIABLES ==========
$page_title = 'Tracking Analytics';
$page_subtitle = 'Analisis Performa Tracking Pixel';
$page_icon = 'fas fa-chart-pie';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== MOBILE FIRST VARIABLES ===== */
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
    --meta: #1877F2;
    --tiktok: #000000;
    --google: #EA4335;
}

/* ===== MOBILE FIRST LAYOUT ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}

.main-content {
    width: 100%;
    min-height: 100vh;
    padding: 12px;
    margin-left: 0 !important;
}

/* ===== TOP BAR - MOBILE FIRST ===== */
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
    flex-shrink: 0;
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

/* ===== ALERT ===== */
.alert {
    padding: 14px 16px;
    border-radius: 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
    border-left: 4px solid;
}

.alert.success {
    background: #d4edda;
    color: #155724;
    border-left-color: #28a745;
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
    border-left-color: var(--danger);
}

/* ===== FILTER SECTION ===== */
.filter-section {
    background: white;
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
}

.filter-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    cursor: pointer;
    font-weight: 600;
    color: var(--primary);
}

.filter-header i:first-child {
    color: var(--secondary);
    font-size: 16px;
}

.filter-header i:last-child {
    margin-left: auto;
    transition: transform 0.3s;
    color: var(--secondary);
}

.filter-body {
    margin-top: 16px;
    display: block;
}

.filter-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 16px;
}

@media (min-width: 768px) {
    .filter-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

.filter-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 4px;
}

.filter-input, .filter-select {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    background: white;
}

.filter-input:focus, .filter-select:focus {
    border-color: var(--secondary);
    outline: none;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.btn-filter {
    flex: 1;
    padding: 12px 20px;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-filter-reset {
    flex: 1;
    padding: 12px 20px;
    background: var(--border);
    color: var(--text);
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

/* ===== STATS CARDS ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

@media (min-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 14px;
    border-left: 4px solid;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    display: flex;
    align-items: center;
    gap: 12px;
}

.stat-card.primary { border-left-color: var(--primary); }
.stat-card.success { border-left-color: var(--success); }
.stat-card.danger { border-left-color: var(--danger); }
.stat-card.warning { border-left-color: var(--warning); }
.stat-card.info { border-left-color: var(--info); }

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.stat-icon.bg-primary { background: rgba(27,74,60,0.1); color: var(--primary); }
.stat-icon.bg-success { background: rgba(42,157,143,0.1); color: var(--success); }
.stat-icon.bg-danger { background: rgba(214,79,60,0.1); color: var(--danger); }
.stat-icon.bg-warning { background: rgba(233,196,106,0.1); color: #B87C00; }
.stat-icon.bg-info { background: rgba(74,144,226,0.1); color: var(--info); }
.stat-icon.bg-purple { background: rgba(155,89,182,0.1); color: #9B59B6; }

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 2px;
}

.stat-value {
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

/* ===== CARD ===== */
.card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-soft);
}

.card-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.card-header h3 i {
    color: var(--secondary);
}

.card-body {
    width: 100%;
}

.card-body.p-0 {
    padding: 0;
}

/* ===== CHART CONTAINER ===== */
.chart-container {
    height: 250px;
    position: relative;
}

/* ===== PIXEL STATS GRID ===== */
.pixel-stats-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

@media (min-width: 768px) {
    .pixel-stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.pixel-stat-card {
    background: var(--primary-soft);
    border-radius: 16px;
    padding: 16px;
}

.pixel-stat-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.pixel-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.pixel-icon.meta {
    background: var(--meta);
    color: white;
}

.pixel-icon.tiktok {
    background: var(--tiktok);
    color: white;
}

.pixel-icon.google {
    background: var(--google);
    color: white;
}

.pixel-stat-info {
    flex: 1;
}

.pixel-stat-info h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--primary);
    margin: 0 0 2px 0;
}

.pixel-total {
    font-size: 11px;
    color: var(--text-muted);
}

.pixel-success-rate {
    font-size: 18px;
    font-weight: 800;
}

.pixel-success-rate.high {
    color: var(--success);
}

.pixel-success-rate.low {
    color: var(--danger);
}

.progress-bar {
    height: 6px;
    background: var(--border);
    border-radius: 3px;
    overflow: hidden;
    margin: 12px 0;
}

.progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s;
}

.progress-fill.bg-success {
    background: linear-gradient(90deg, var(--success), #40BEB0);
}

.progress-fill.bg-danger {
    background: linear-gradient(90deg, var(--danger), #FF6B4A);
}

.pixel-stats-footer {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
}

.pixel-stats-footer i {
    width: 14px;
    margin-right: 2px;
}

.text-success { color: var(--success); }
.text-danger { color: var(--danger); }

/* ===== TABLE ===== */
.table-responsive {
    overflow-x: auto;
    margin: 0 -20px;
    padding: 0 20px;
    width: calc(100% + 40px);
    -webkit-overflow-scrolling: touch;
}

.table-responsive::-webkit-scrollbar {
    height: 4px;
}

.table-responsive::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

th {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    padding: 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 12px;
    text-transform: uppercase;
}

td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

tr:hover td {
    background: var(--primary-soft);
}

.lead-info {
    display: flex;
    flex-direction: column;
}

.lead-info strong {
    font-size: 14px;
    color: var(--primary);
}

.lead-info small {
    font-size: 11px;
    color: var(--text-muted);
}

/* ===== BADGE ===== */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

.badge-success {
    background: var(--success);
}

.badge-danger {
    background: var(--danger);
}

.badge-warning {
    background: var(--warning);
    color: #1A2A24;
}

/* ===== BTN ICON ===== */
.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    border: 1px solid var(--border);
    text-decoration: none;
    color: var(--text);
}

.btn-icon:hover {
    background: var(--primary-soft);
    color: var(--secondary);
}

/* ===== PAGINATION ===== */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.pagination-item {
    min-width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: white;
    border: 2px solid var(--border);
    color: var(--text);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
}

.pagination-item.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* ===== FOOTER STATS ===== */
.footer-stats {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin: 30px 0 20px;
    flex-wrap: wrap;
}

.footer-stat {
    display: flex;
    align-items: center;
    gap: 8px;
    background: white;
    padding: 8px 16px;
    border-radius: 40px;
    border: 1px solid var(--border);
    font-size: 12px;
}

.footer-stat i {
    color: var(--secondary);
    font-size: 14px;
}

/* ===== FOOTER ===== */
.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

/* ===== UTILITY ===== */
.text-center { text-align: center; }
.py-4 { padding-top: 16px; padding-bottom: 16px; }

/* ===== DESKTOP UPGRADE ===== */
@media (min-width: 1024px) {
    .main-content {
        margin-left: 280px !important;
        padding: 24px !important;
        max-width: 1400px;
        margin-right: auto !important;
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
    
    .filter-section {
        padding: 20px;
    }
    
    .stats-grid {
        gap: 20px;
    }
    
    .stat-card {
        padding: 16px;
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        font-size: 22px;
    }
    
    .stat-value {
        font-size: 22px;
    }
    
    .card {
        padding: 24px;
    }
    
    .card-header h3 {
        font-size: 18px;
    }
    
    .chart-container {
        height: 300px;
    }
    
    .pixel-stats-grid {
        gap: 20px;
    }
    
    .pixel-stat-card {
        padding: 18px;
    }
    
    .pixel-icon {
        width: 44px;
        height: 44px;
        font-size: 22px;
    }
    
    .pixel-stat-info h4 {
        font-size: 15px;
    }
    
    .pixel-success-rate {
        font-size: 20px;
    }
    
    th {
        padding: 14px;
    }
    
    td {
        padding: 14px;
    }
    
    .footer-stats {
        gap: 30px;
    }
    
    .footer-stat {
        padding: 10px 20px;
        font-size: 13px;
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
    
    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div class="filter-header" onclick="toggleFilter()">
            <i class="fas fa-filter"></i>
            <span>Filter Data Tracking</span>
            <i class="fas fa-chevron-down filter-toggle-icon"></i>
        </div>
        <div class="filter-body" id="filterBody">
            <form method="GET" class="filter-form">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= $start_date ?>" class="filter-input">
                    </div>
                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= $end_date ?>" class="filter-input">
                    </div>
                    <?php if (isAdmin() || isManager()): ?>
                    <div class="filter-group">
                        <label>Developer</label>
                        <select name="developer_id" class="filter-select">
                            <option value="">Semua Developer</option>
                            <?php foreach ($developers as $dev): ?>
                            <option value="<?= $dev['id'] ?>" <?= $developer_id == $dev['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dev['nama_lengkap']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="filter-group">
                        <label>Pixel Type</label>
                        <select name="pixel_type" class="filter-select">
                            <option value="">Semua Pixel</option>
                            <option value="meta" <?= $pixel_type == 'meta' ? 'selected' : '' ?>>Meta</option>
                            <option value="tiktok" <?= $pixel_type == 'tiktok' ? 'selected' : '' ?>>TikTok</option>
                            <option value="google" <?= $pixel_type == 'google' ? 'selected' : '' ?>>Google</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Terapkan Filter
                    </button>
                    <a href="?" class="btn-filter-reset">
                        <i class="fas fa-redo-alt"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-icon bg-primary">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Events</div>
                <div class="stat-value"><?= number_format($stats['total_events'] ?? 0) ?></div>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon bg-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Success</div>
                <div class="stat-value"><?= number_format($stats['sent'] ?? 0) ?></div>
            </div>
        </div>
        
        <div class="stat-card danger">
            <div class="stat-icon bg-danger">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Failed</div>
                <div class="stat-value"><?= number_format($stats['failed'] ?? 0) ?></div>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-icon bg-warning">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= number_format($stats['pending'] ?? 0) ?></div>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-icon bg-info">
                <i class="fas fa-percent"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Success Rate</div>
                <div class="stat-value"><?= $stats['success_rate'] ?? 0 ?>%</div>
            </div>
        </div>
        
        <div class="stat-card" style="border-left-color: #9B59B6;">
            <div class="stat-icon bg-purple">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Unique Leads</div>
                <div class="stat-value"><?= number_format($stats['unique_leads'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    
    <!-- CHART SECTION -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Tracking Events per Hari</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="trackingChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- PIXEL PERFORMANCE -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Performa per Pixel</h3>
        </div>
        <div class="card-body">
            <div class="pixel-stats-grid">
                <?php foreach ($pixel_stats as $pixel): ?>
                <div class="pixel-stat-card">
                    <div class="pixel-stat-header">
                        <div class="pixel-icon <?= $pixel['pixel_type'] ?>">
                            <i class="fab fa-<?= $pixel['pixel_type'] == 'meta' ? 'facebook-f' : ($pixel['pixel_type'] == 'tiktok' ? 'tiktok' : 'google') ?>"></i>
                        </div>
                        <div class="pixel-stat-info">
                            <h4><?= strtoupper($pixel['pixel_type']) ?></h4>
                            <span class="pixel-total">Total: <?= number_format($pixel['total']) ?></span>
                        </div>
                        <div class="pixel-success-rate <?= $pixel['success_rate'] > 90 ? 'high' : 'low' ?>">
                            <?= $pixel['success_rate'] ?>%
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill <?= $pixel['success_rate'] > 90 ? 'bg-success' : 'bg-danger' ?>" 
                             style="width: <?= $pixel['success_rate'] ?>%"></div>
                    </div>
                    <div class="pixel-stats-footer">
                        <span><i class="fas fa-check-circle text-success"></i> Sent: <?= number_format($pixel['sent']) ?></span>
                        <span><i class="fas fa-times-circle text-danger"></i> Failed: <?= number_format($pixel['failed']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($pixel_stats)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <p>Belum ada data tracking untuk periode ini</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- DEVELOPER PERFORMANCE (Hanya untuk Admin/Manager) -->
    <?php if (isAdmin() || isManager()): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-building"></i> Performa per Developer</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Developer</th>
                            <th>Total</th>
                            <th>Sent</th>
                            <th>Failed</th>
                            <th>Success</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dev_stats as $dev): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($dev['developer_name'] ?? 'Unknown') ?></strong></td>
                            <td><?= number_format($dev['total_events']) ?></td>
                            <td class="text-success"><?= number_format($dev['sent']) ?></td>
                            <td class="text-danger"><?= number_format($dev['failed']) ?></td>
                            <td>
                                <span class="badge <?= $dev['success_rate'] > 90 ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $dev['success_rate'] ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($dev_stats)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">Tidak ada data</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- RECENT LEADS WITH TRACKING -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> Leads Terbaru (7 Hari)</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Lead</th>
                            <th>Lokasi</th>
                            <th>Tracking</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_leads as $lead): ?>
                        <tr>
                            <td>
                                <div class="lead-info">
                                    <strong><?= htmlspecialchars($lead['first_name'] . ' ' . ($lead['last_name'] ?? '')) ?></strong>
                                    <small><?= date('d/m H:i', strtotime($lead['lead_created'])) ?></small>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($lead['location_name'] ?? $lead['location_key']) ?></td>
                            <td><?= $lead['tracking_count'] ?> events</td>
                            <td>
                                <?php if ($lead['failed_count'] > 0): ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-exclamation-triangle"></i> <?= $lead['failed_count'] ?> Failed
                                </span>
                                <?php elseif ($lead['sent_count'] > 0): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Sent
                                </span>
                                <?php else: ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-hourglass"></i> No Tracking
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="tracking_report.php?lead_id=<?= $lead['id'] ?>" class="btn-icon" title="Lihat Tracking">
                                    <i class="fas fa-chart-line"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recent_leads)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">Tidak ada leads dalam 7 hari terakhir</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&developer_id=<?= $developer_id ?>&pixel_type=<?= $pixel_type ?>" 
                   class="pagination-item">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&developer_id=<?= $developer_id ?>&pixel_type=<?= $pixel_type ?>" 
                   class="pagination-item <?= $i == $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&developer_id=<?= $developer_id ?>&pixel_type=<?= $pixel_type ?>" 
                   class="pagination-item">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- FOOTER STATS -->
    <div class="footer-stats">
        <div class="footer-stat">
            <i class="fas fa-calendar-alt"></i>
            <span>Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></span>
        </div>
        <div class="footer-stat">
            <i class="fas fa-database"></i>
            <span>Total Data: <?= number_format($stats['total_events'] ?? 0) ?> events</span>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Tracking Analytics v3.0</p>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Chart Tracking Harian
const ctx = document.getElementById('trackingChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
            {
                label: 'Sent',
                data: <?= json_encode($chart_sent) ?>,
                borderColor: '#2A9D8F',
                backgroundColor: 'rgba(42, 157, 143, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            },
            {
                label: 'Failed',
                data: <?= json_encode($chart_failed) ?>,
                borderColor: '#D64F3C',
                backgroundColor: 'rgba(214, 79, 60, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    boxWidth: 12,
                    padding: 15,
                    font: { size: 12 }
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: '#1A2A24',
                titleColor: '#FFFFFF',
                bodyColor: '#E0E7E0',
                borderColor: '#2A9D8F',
                borderWidth: 1
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { font: { size: 11 } }
            },
            x: {
                grid: { display: false },
                ticks: { 
                    maxRotation: 45,
                    minRotation: 45,
                    font: { size: 10 }
                }
            }
        }
    }
});

// Toggle filter untuk mobile
function toggleFilter() {
    const filterBody = document.getElementById('filterBody');
    const icon = document.querySelector('.filter-toggle-icon');
    
    if (filterBody.style.display === 'none') {
        filterBody.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        filterBody.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}

// Update datetime
function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', options);
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { hour12: false });
}
setInterval(updateDateTime, 1000);
updateDateTime();

// Responsive chart
window.addEventListener('resize', function() {
    const chart = Chart.getChart('trackingChart');
    if (chart) {
        chart.resize();
    }
});
</script>

<?php include 'includes/footer.php'; ?>