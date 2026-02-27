<?php
/**
 * MARKETING_DASHBOARD.PHP - LEADENGINE
 * Version: 10.0.0 - DENGAN WIDGET LEADERBOARD
 * UPDATE: Menambahkan widget leaderboard di dashboard marketing
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

if (!isMarketing()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$marketing_id = $_SESSION['marketing_id'];
$marketing_name = $_SESSION['marketing_name'] ?? 'Marketing';
$developer_id = $_SESSION['marketing_developer_id'] ?? 0;

// Ambil KPI
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$kpi = getMarketingKPI($conn, $marketing_id, $start_date, $end_date);

// Leads terbaru
$stmt = $conn->prepare("
    SELECT l.*, loc.display_name as location_display, loc.icon
    FROM leads l
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    WHERE l.assigned_marketing_team_id = ?
    AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
    ORDER BY l.created_at DESC
    LIMIT 10
");
$stmt->execute([$marketing_id]);
$recent_leads = $stmt->fetchAll();

// Aktivitas terbaru
$stmt = $conn->prepare("
    SELECT a.*, l.first_name, l.last_name, l.phone
    FROM marketing_activities a
    LEFT JOIN leads l ON a.lead_id = l.id
    WHERE a.marketing_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute([$marketing_id]);
$recent_activities = $stmt->fetchAll();

// Statistik status
$stmt = $conn->prepare("
    SELECT status, COUNT(*) as count
    FROM leads
    WHERE assigned_marketing_team_id = ?
    AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
    GROUP BY status
    ORDER BY count DESC
");
$stmt->execute([$marketing_id]);
$status_stats = $stmt->fetchAll();

// 🔥 AMBIL DATA LEADERBOARD UNTUK WIDGET
$top_marketing = [];
if ($developer_id > 0) {
    $deal_statuses = ['Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'];
    $deal_placeholders = implode(',', array_fill(0, count($deal_statuses), '?'));
    
    $sql = "
        SELECT 
            m.id,
            m.nama_lengkap,
            m.phone,
            COUNT(DISTINCT l.id) as total_leads,
            COUNT(DISTINCT CASE WHEN l.status IN ($deal_placeholders) THEN l.id END) as total_deal,
            COALESCE(AVG(l.lead_score), 0) as avg_score
        FROM marketing_team m
        LEFT JOIN leads l ON m.id = l.assigned_marketing_team_id 
            AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
            AND YEARWEEK(l.created_at, 1) = YEARWEEK(CURDATE(), 1)
        WHERE m.developer_id = ? AND m.is_active = 1
        GROUP BY m.id
        HAVING total_leads > 0 OR total_deal > 0
        ORDER BY total_deal DESC, total_leads DESC
        LIMIT 5
    ";
    
    $params = array_merge($deal_statuses, [$developer_id]);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $top_marketing = $stmt->fetchAll();
}

$page_title = 'Dashboard Marketing';
$page_subtitle = 'Pantau Kinerja dan Leads Anda';
$page_icon = 'fas fa-user-tie';

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

/* ===== FILTER BAR ===== */
.filter-bar {
    background: var(--surface);
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.filter-form {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-input {
    flex: 1;
    min-width: 200px;
    padding: 14px 18px;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-size: 14px;
    background: white;
}

.filter-input:focus {
    border-color: var(--secondary);
    outline: none;
    box-shadow: 0 0 0 4px rgba(214,79,60,0.1);
}

.filter-actions {
    display: flex;
    gap: 12px;
}

.filter-btn {
    padding: 14px 28px;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border: none;
    border-radius: 14px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(214,79,60,0.2);
    transition: all 0.2s;
}

.filter-btn.reset {
    background: var(--border);
    color: var(--text);
    box-shadow: none;
}

.filter-btn.reset:hover {
    background: var(--text-muted);
    color: white;
}

.filter-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(214,79,60,0.3);
}

/* ===== STATS GRID ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--surface);
    border-radius: 16px;
    padding: 20px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.stat-icon {
    font-size: 28px;
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
    font-size: 32px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

/* ===== SCORE GRID ===== */
.score-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.score-card {
    background: var(--surface);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: transform 0.2s;
}

.score-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.score-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    flex-shrink: 0;
}

.score-icon.hot {
    background: rgba(214,79,60,0.15);
    color: var(--danger);
}

.score-icon.warm {
    background: rgba(233,196,106,0.15);
    color: #B87C00;
}

.score-icon.cold {
    background: rgba(74,144,226,0.15);
    color: var(--info);
}

.score-info {
    flex: 1;
}

.score-value {
    font-size: 28px;
    font-weight: 800;
    line-height: 1.2;
    color: var(--text);
}

.score-label {
    font-size: 13px;
    color: var(--text-muted);
    font-weight: 500;
}

/* ===== KPI DETAILS ===== */
.kpi-details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.kpi-card {
    background: var(--surface);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.kpi-card h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--border);
}

.kpi-card h3 i {
    color: var(--secondary);
}

.kpi-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.kpi-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
}

.kpi-item:last-child {
    border-bottom: none;
}

.kpi-label {
    font-weight: 500;
    color: var(--text-light);
    font-size: 14px;
}

.kpi-value {
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
}

.status-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.status-stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
}

.status-stat-item:last-child {
    border-bottom: none;
}

.status-stat-count {
    font-weight: 700;
    color: var(--primary);
    font-size: 18px;
    background: var(--primary-soft);
    padding: 4px 12px;
    border-radius: 40px;
    min-width: 40px;
    text-align: center;
}

.empty-state {
    text-align: center;
    color: var(--text-muted);
    padding: 20px;
    font-style: italic;
}

/* ===== 🔥 LEADERBOARD WIDGET ===== */
.leaderboard-widget {
    background: var(--surface);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border-left: 6px solid var(--gold, #FFD700);
}

.leaderboard-widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.leaderboard-widget-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.leaderboard-widget-header h3 i {
    color: #FFD700;
}

.leaderboard-widget-header a {
    color: var(--secondary);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    padding: 6px 12px;
    background: var(--primary-soft);
    border-radius: 30px;
    transition: all 0.2s;
}

.leaderboard-widget-header a:hover {
    background: var(--secondary);
    color: white;
}

.leaderboard-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 15px;
    padding: 8px 4px 15px 4px;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}

.leaderboard-horizontal::-webkit-scrollbar {
    height: 6px;
}

.leaderboard-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.leaderboard-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.leaderboard-mini-card {
    flex: 0 0 180px;
    background: linear-gradient(135deg, #f8fafc, white);
    border-radius: 16px;
    padding: 15px;
    border-left: 4px solid;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.leaderboard-mini-card.rank-1 { border-left-color: #FFD700; }
.leaderboard-mini-card.rank-2 { border-left-color: #C0C0C0; }
.leaderboard-mini-card.rank-3 { border-left-color: #CD7F32; }

.leaderboard-mini-rank {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-muted);
    margin-bottom: 5px;
}

.leaderboard-mini-name {
    font-weight: 700;
    color: var(--primary);
    font-size: 15px;
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.leaderboard-mini-stats {
    display: flex;
    gap: 10px;
    font-size: 12px;
    margin-bottom: 8px;
}

.leaderboard-mini-stats span {
    display: flex;
    align-items: center;
    gap: 3px;
}

.leaderboard-mini-stats i {
    color: var(--secondary);
    font-size: 11px;
}

.leaderboard-mini-deal {
    font-size: 18px;
    font-weight: 800;
    color: var(--success);
}

/* ===== TABLE ===== */
.table-container {
    background: var(--surface);
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    margin-bottom: 24px;
    overflow: hidden;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 0 20px 0;
    border-bottom: 2px solid var(--border);
    margin-bottom: 20px;
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

.table-header h3 i {
    color: var(--secondary);
}

.table-header .btn {
    background: var(--primary);
    color: white;
    padding: 10px 20px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.table-header .btn:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
}

.table-responsive {
    overflow-x: auto;
    margin: 0 -24px;
    padding: 0 24px;
    width: calc(100% + 48px);
    -webkit-overflow-scrolling: touch;
}

.table-responsive::-webkit-scrollbar {
    height: 6px;
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
    min-width: 1000px;
}

th {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    padding: 16px 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 2px solid var(--border);
}

td {
    padding: 16px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    background: white;
}

tr:hover td {
    background-color: rgba(231,243,239,0.3);
}

/* ===== STATUS BADGES ===== */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    color: white;
}

.status-badge.Baru { background: #4A90E2; }
.status-badge.Follow\ Up { background: #E9C46A; color: #1A2A24; }
.status-badge.Survey { background: #E9C46A; color: #1A2A24; }
.status-badge.Booking { background: #1B4A3C; }
.status-badge.TolakSlik { background: #9C27B0; }
.status-badge.Tolak\ Slik { background: #9C27B0; }
.status-badge.TidakMinat { background: #757575; }
.status-badge.Tidak\ Minat { background: #757575; }
.status-badge.Batal { background: #D64F3C; }
.status-badge.DealKPR { background: #2A9D8F; }
.status-badge.Deal\ KPR { background: #2A9D8F; }
.status-badge.DealTunai { background: #FF9800; }
.status-badge.Deal\ Tunai { background: #FF9800; }
.status-badge.DealBertahap6Bulan { background: #2A9D8F; }
.status-badge.Deal\ Bertahap\ 6\ Bulan { background: #2A9D8F; }
.status-badge.DealBertahap1Tahun { background: #2A9D8F; }
.status-badge.Deal\ Bertahap\ 1\ Tahun { background: #2A9D8F; }

.score-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 700;
    min-width: 45px;
    text-align: center;
    color: white;
}

.score-hot { background: #D64F3C; }
.score-warm { background: #E9C46A; color: #1A2A24; }
.score-cold { background: #4A90E2; }

.location-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--primary-soft);
    padding: 4px 12px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
    color: var(--primary);
    white-space: nowrap;
}

/* ===== ACTION BUTTONS ===== */
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
    align-items: center;
    flex-wrap: nowrap;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    background: white;
    flex-shrink: 0;
}

.action-btn.view {
    background: #e8f0fe;
    color: #1976d2;
    border: 1px solid #1976d2;
}

.action-btn.view:hover {
    background: #1976d2;
    color: white;
}

.action-btn.edit {
    background: #fff8e1;
    color: #ff8f00;
    border: 1px solid #ff8f00;
}

.action-btn.edit:hover {
    background: #ff8f00;
    color: white;
}

.action-btn.note {
    background: #f3e5f5;
    color: #9c27b0;
    border: 1px solid #9c27b0;
}

.action-btn.note:hover {
    background: #9c27b0;
    color: white;
}

.action-btn.whatsapp {
    background: #e8f5e9;
    color: #25D366;
    border: 1px solid #25D366;
}

.action-btn.whatsapp:hover {
    background: #25D366;
    color: white;
}

/* ===== ACTIVITY ITEM ===== */
.activity-item {
    background: white;
    padding: 16px;
    border-radius: 16px;
    margin-bottom: 12px;
    border-left: 3px solid var(--secondary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.activity-type {
    font-weight: 700;
    color: var(--primary);
    text-transform: capitalize;
}

.activity-time {
    font-size: 11px;
    color: var(--text-muted);
}

.activity-detail {
    font-size: 12px;
    color: var(--text-light);
    margin-bottom: 8px;
}

.activity-lead {
    font-weight: 600;
    color: var(--primary);
}

.activity-note {
    font-size: 12px;
    color: var(--text-muted);
    background: var(--bg);
    padding: 10px;
    border-radius: 8px;
    margin-top: 8px;
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
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .score-grid {
        gap: 15px;
    }
    
    .kpi-details-grid {
        gap: 15px;
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
        padding: 16px;
    }
    
    .welcome-text i {
        width: 48px;
        height: 48px;
        font-size: 22px;
    }
    
    .welcome-text h2 {
        font-size: 18px;
    }
    
    .datetime {
        width: 100%;
        justify-content: space-between;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .filter-input {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
    }
    
    .filter-btn {
        flex: 1;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px;
    }
    
    .stat-card {
        padding: 14px;
    }
    
    .stat-icon {
        font-size: 24px;
        margin-bottom: 8px;
    }
    
    .stat-label {
        font-size: 11px;
    }
    
    .stat-value {
        font-size: 22px;
    }
    
    .score-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px;
    }
    
    .score-card {
        padding: 14px;
        gap: 10px;
    }
    
    .score-icon {
        width: 44px;
        height: 44px;
        font-size: 22px;
    }
    
    .score-value {
        font-size: 22px;
    }
    
    .score-label {
        font-size: 11px;
    }
    
    .kpi-details-grid {
        grid-template-columns: 1fr !important;
        gap: 12px;
    }
    
    .kpi-card {
        padding: 16px;
    }
    
    .kpi-card h3 {
        font-size: 16px;
    }
    
    .kpi-label {
        font-size: 13px;
    }
    
    .kpi-value {
        font-size: 15px;
    }
    
    .status-stat-count {
        font-size: 16px;
        padding: 3px 10px;
    }
    
    .table-container {
        padding: 16px;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .table-header h3 {
        font-size: 16px;
    }
    
    .table-header .btn {
        width: 100%;
        justify-content: center;
    }
    
    .table-responsive {
        margin: 0 -16px;
        padding: 0 16px;
        width: calc(100% + 32px);
    }
    
    table {
        min-width: 900px;
    }
    
    th {
        padding: 12px 8px;
        font-size: 12px;
    }
    
    td {
        padding: 12px 8px;
        font-size: 12px;
    }
    
    .action-buttons {
        gap: 4px;
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
        font-size: 13px;
    }
    
    .status-badge {
        padding: 3px 8px;
        font-size: 10px;
    }
    
    .score-badge {
        padding: 3px 6px;
        font-size: 10px;
        min-width: 35px;
    }
    
    .activity-item {
        padding: 12px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px;
    }
    
    .stat-card {
        padding: 12px;
    }
    
    .stat-value {
        font-size: 20px;
    }
    
    .score-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px;
    }
    
    .score-card {
        padding: 12px;
    }
    
    .score-icon {
        width: 40px;
        height: 40px;
        font-size: 20px;
    }
    
    .score-value {
        font-size: 20px;
    }
    
    table {
        min-width: 800px;
    }
    
    th {
        padding: 10px 6px;
        font-size: 11px;
    }
    
    td {
        padding: 10px 6px;
        font-size: 11px;
    }
    
    .action-btn {
        width: 30px;
        height: 30px;
        font-size: 12px;
    }
}

@media (max-width: 360px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 5px;
    }
    
    .stat-card {
        padding: 10px;
    }
    
    .stat-value {
        font-size: 18px;
    }
    
    .score-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 5px;
    }
    
    .score-card {
        padding: 10px;
    }
    
    .score-icon {
        width: 36px;
        height: 36px;
        font-size: 18px;
    }
    
    .score-value {
        font-size: 18px;
    }
    
    table {
        min-width: 700px;
    }
    
    .action-btn {
        width: 28px;
        height: 28px;
        font-size: 11px;
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
                <span><?= htmlspecialchars($marketing_name) ?></span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <!-- FILTER BAR -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <input type="date" name="start_date" class="filter-input" value="<?= $start_date ?>">
            <input type="date" name="end_date" class="filter-input" value="<?= $end_date ?>">
            <div class="filter-actions">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-filter"></i> Tampilkan
                </button>
                <a href="?" class="filter-btn reset">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Lead</div>
            <div class="stat-value"><?= $kpi['total_leads_assigned'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-plus"></i></div>
            <div class="stat-label">Lead Baru</div>
            <div class="stat-value"><?= $kpi['total_leads_diterima'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle" style="color: #2A9D8F;"></i></div>
            <div class="stat-label">Total Deal</div>
            <div class="stat-value"><?= $kpi['total_deal'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-label">Conversion</div>
            <div class="stat-value"><?= $kpi['conversion_rate'] ?>%</div>
        </div>
    </div>
    
    <!-- SCORE CARDS -->
    <div class="score-grid">
        <div class="score-card">
            <div class="score-icon hot"><i class="fas fa-fire"></i></div>
            <div class="score-info">
                <div class="score-value"><?= $kpi['score_distribution']['hot'] ?></div>
                <div class="score-label">Hot (80-100)</div>
            </div>
        </div>
        <div class="score-card">
            <div class="score-icon warm"><i class="fas fa-sun"></i></div>
            <div class="score-info">
                <div class="score-value"><?= $kpi['score_distribution']['warm'] ?></div>
                <div class="score-label">Warm (60-79)</div>
            </div>
        </div>
        <div class="score-card">
            <div class="score-icon cold"><i class="fas fa-snowflake"></i></div>
            <div class="score-info">
                <div class="score-value"><?= $kpi['score_distribution']['cold'] ?></div>
                <div class="score-label">Cold (0-59)</div>
            </div>
        </div>
    </div>
    
    <!-- KPI DETAILS -->
    <div class="kpi-details-grid">
        <div class="kpi-card">
            <h3><i class="fas fa-chart-pie"></i> Detail KPI</h3>
            <div class="kpi-list">
                <div class="kpi-item">
                    <span class="kpi-label">Total Follow Up</span>
                    <span class="kpi-value"><?= $kpi['total_follow_up'] ?></span>
                </div>
                <div class="kpi-item">
                    <span class="kpi-label">Status Update</span>
                    <span class="kpi-value"><?= $kpi['total_status_update'] ?></span>
                </div>
                <div class="kpi-item">
                    <span class="kpi-label">Rata Follow Up/Lead</span>
                    <span class="kpi-value"><?= $kpi['avg_followups_per_lead'] ?></span>
                </div>
                <div class="kpi-item">
                    <span class="kpi-label">Last Activity</span>
                    <span class="kpi-value"><?= $kpi['last_activity'] ? date('d/m/Y H:i', strtotime($kpi['last_activity'])) : '-' ?></span>
                </div>
            </div>
        </div>
        
        <div class="kpi-card">
            <h3><i class="fas fa-tag"></i> Statistik Status</h3>
            <div class="status-list">
                <?php if (empty($status_stats)): ?>
                    <div class="empty-state">Belum ada data</div>
                <?php else: ?>
                    <?php foreach ($status_stats as $stat): ?>
                    <div class="status-stat-item">
                        <span class="status-badge <?= str_replace(' ', '', $stat['status']) ?>"><?= $stat['status'] ?></span>
                        <span class="status-stat-count"><?= $stat['count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 🔥 LEADERBOARD WIDGET -->
    <?php if (!empty($top_marketing)): ?>
    <div class="leaderboard-widget">
        <div class="leaderboard-widget-header">
            <h3><i class="fas fa-trophy"></i> 🏆 Top Marketing Minggu Ini</h3>
            <a href="marketing_leaderboard.php">Lihat Semua <i class="fas fa-arrow-right"></i></a>
        </div>
        
        <div class="leaderboard-horizontal">
            <?php 
            $rank = 1;
            foreach ($top_marketing as $top): 
                $rankClass = $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : ''));
            ?>
            <div class="leaderboard-mini-card <?= $rankClass ?>">
                <div class="leaderboard-mini-rank">#<?= $rank ?></div>
                <div class="leaderboard-mini-name"><?= htmlspecialchars($top['nama_lengkap']) ?></div>
                <div class="leaderboard-mini-stats">
                    <span><i class="fas fa-check-circle"></i> <?= $top['total_deal'] ?></span>
                    <span><i class="fas fa-users"></i> <?= $top['total_leads'] ?></span>
                </div>
                <div class="leaderboard-mini-deal">⭐ <?= round($top['avg_score']) ?></div>
            </div>
            <?php 
            $rank++;
            endforeach; 
            ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- RECENT LEADS TABLE -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-clock"></i> Leads Terbaru</h3>
            <a href="marketing_leads.php" class="btn">
                <i class="fas fa-arrow-right"></i> Lihat Semua
            </a>
        </div>
        
        <?php if (empty($recent_leads)): ?>
        <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: var(--radius-lg);">
            <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
            <p style="color: #666;">Belum ada lead</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Kontak</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_leads as $lead): 
                        $full_name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
                        $score_class = $lead['lead_score'] >= 80 ? 'hot' : ($lead['lead_score'] >= 60 ? 'warm' : 'cold');
                        
                        // PASTIKAN STATUS TIDAK NULL
                        $status = !empty($lead['status']) ? $lead['status'] : 'Baru';
                        
                        // HAPUS SPASI UNTUK CSS CLASS
                        $status_class_name = str_replace(' ', '', $status);
                        $status_class_name = str_replace('-', '', $status_class_name);
                    ?>
                    <tr>
                        <td>#<?= $lead['id'] ?></td>
                        <td><strong><?= htmlspecialchars($full_name) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($lead['phone']) ?><br>
                            <small><?= htmlspecialchars($lead['email'] ?? '-') ?></small>
                        </td>
                        <td>
                            <span class="location-badge">
                                <span><?= $lead['icon'] ?? '🏠' ?></span>
                                <?= htmlspecialchars($lead['location_display'] ?? $lead['location_key']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?= $status_class_name ?>">
                                <?= $status ?>
                            </span>
                        </td>
                        <td>
                            <span class="score-badge score-<?= $score_class ?>"><?= $lead['lead_score'] ?? 0 ?></span>
                        </td>
                        <td><?= date('d/m/Y', strtotime($lead['created_at'])) ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewLead(<?= $lead['id'] ?>)" title="Lihat">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn edit" onclick="editLead(<?= $lead['id'] ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn note" onclick="openNoteModal(<?= $lead['id'] ?>, '<?= htmlspecialchars(addslashes($full_name)) ?>')" title="Catatan">
                                    <i class="fas fa-sticky-note"></i>
                                </button>
                                <a href="https://wa.me/<?= $lead['phone'] ?>" target="_blank" class="action-btn whatsapp" title="WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- RECENT ACTIVITIES -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-history"></i> Aktivitas Terbaru</h3>
        </div>
        
        <?php if (empty($recent_activities)): ?>
        <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: var(--radius-lg);">
            <i class="fas fa-history" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
            <p style="color: #666;">Belum ada aktivitas</p>
        </div>
        <?php else: ?>
            <?php foreach ($recent_activities as $act): 
                $full_name = trim($act['first_name'] . ' ' . ($act['last_name'] ?? ''));
                $action_icons = [
                    'update_status' => '🔄',
                    'update_data' => '📝',
                    'follow_up' => '📞',
                    'call' => '📱',
                    'whatsapp' => '💬',
                    'survey' => '📍',
                    'booking' => '📝',
                    'add_note' => '📌'
                ];
                $icon = $action_icons[$act['action_type']] ?? '📋';
                $action_name = ucfirst(str_replace('_', ' ', $act['action_type']));
            ?>
            <div class="activity-item">
                <div class="activity-header">
                    <span class="activity-type"><?= $icon ?> <?= $action_name ?></span>
                    <span class="activity-time"><?= date('d/m/Y H:i', strtotime($act['created_at'])) ?></span>
                </div>
                <div class="activity-detail">
                    <span class="activity-lead"><?= htmlspecialchars($full_name) ?></span>
                    <?php if ($act['status_before'] != $act['status_after']): ?>
                    <span> | 
                        <span class="status-badge <?= str_replace(' ', '', $act['status_before']) ?>" style="font-size: 10px;"><?= $act['status_before'] ?></span>
                        <i class="fas fa-arrow-right" style="margin: 0 4px; color: var(--secondary);"></i>
                        <span class="status-badge <?= str_replace(' ', '', $act['status_after']) ?>" style="font-size: 10px;"><?= $act['status_after'] ?></span>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($act['score_before'] != $act['score_after']): ?>
                    <span> | 
                        <span class="score-badge score-<?= $act['score_before'] >= 80 ? 'hot' : ($act['score_before'] >= 60 ? 'warm' : 'cold') ?>" style="font-size: 10px; padding: 2px 6px;"><?= $act['score_before'] ?></span>
                        <i class="fas fa-arrow-right" style="margin: 0 4px; color: var(--secondary);"></i>
                        <span class="score-badge score-<?= $act['score_after'] >= 80 ? 'hot' : ($act['score_after'] >= 60 ? 'warm' : 'cold') ?>" style="font-size: 10px; padding: 2px 6px;"><?= $act['score_after'] ?></span>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($act['note_text'])): ?>
                <div class="activity-note">
                    <i class="fas fa-quote-left" style="color: var(--secondary); font-size: 10px; margin-right: 4px;"></i>
                    <?= htmlspecialchars($act['note_text']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Marketing Dashboard v10.0</p>
    </div>
</div>

<!-- MODALS -->
<?php include 'includes/modals_marketing.php'; ?>

<script>
let currentViewLeadId = 0;

// ===== VIEW LEAD =====
function viewLead(id) {
    currentViewLeadId = id;
    document.getElementById('viewModalBody').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin fa-3x" style="color: #D64F3C;"></i><p style="margin-top: 16px; color: #7A8A84;">Memuat data...</p></div>';
    openModal('viewModal');
    
    fetch('api/leads_marketing.php?action=get&id=' + id, { 
        method: 'GET',
        credentials: 'include',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('View lead response:', data);
        
        if (data.success) {
            renderViewModal(data.data, data.activities || []);
        } else {
            document.getElementById('viewModalBody').innerHTML = '<div style="text-align: center; padding: 40px; color: #D64F3C;">Gagal memuat data</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('viewModalBody').innerHTML = '<div style="text-align: center; padding: 40px; color: #D64F3C;">Terjadi kesalahan: ' + error.message + '</div>';
    });
}

function renderViewModal(lead, activities) {
    const fullName = lead.first_name + ' ' + (lead.last_name || '');
    const scoreClass = lead.lead_score >= 80 ? 'hot' : (lead.lead_score >= 60 ? 'warm' : 'cold');
    
    // Tentukan class untuk status badge
    const statusClass = (lead.status || 'Baru').replace(/\s+/g, '-');
    
    let activitiesHtml = '';
    if (activities.length > 0) {
        activities.forEach(a => {
            const actionIcons = {
                'update_status': '🔄',
                'update_data': '📝',
                'follow_up': '📞',
                'call': '📱',
                'whatsapp': '💬',
                'survey': '📍',
                'booking': '📝',
                'add_note': '📌'
            };
            const icon = actionIcons[a.action_type] || '📋';
            const actionName = a.action_type.replace(/_/g, ' ');
            
            activitiesHtml += `
                <div style="background: white; padding: 12px; border-radius: 8px; margin-bottom: 8px; border-left: 3px solid #D64F3C;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span><strong>${icon} ${actionName}</strong></span>
                        <span style="color: #7A8A84; font-size: 11px;">${new Date(a.created_at).toLocaleString('id-ID')}</span>
                    </div>
                    <div style="font-size: 12px; color: #4A5A54;">${a.note_text || '-'}</div>
                </div>
            `;
        });
    } else {
        activitiesHtml = '<p style="color: #7A8A84; text-align: center;">Belum ada aktivitas</p>';
    }
    
    document.getElementById('viewModalBody').innerHTML = `
        <div class="view-section">
            <div class="view-section-title"><i class="fas fa-user"></i> Informasi Customer</div>
            <div class="view-grid">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user"></i> Nama</div>
                    <div class="view-item-value"><strong>${fullName}</strong></div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fab fa-whatsapp"></i> WhatsApp</div>
                    <div class="view-item-value"><a href="https://wa.me/${lead.phone}" target="_blank">${lead.phone}</a></div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-envelope"></i> Email</div>
                    <div class="view-item-value">${lead.email || '-'}</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marker-alt"></i> Lokasi</div>
                    <div class="view-item-value">${lead.icon || '🏠'} ${lead.location_display || lead.location_key}</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-tag"></i> Status</div>
                    <div class="view-item-value"><span class="status-badge ${statusClass}">${lead.status || 'Baru'}</span></div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-chart-line"></i> Score</div>
                    <div class="view-item-value"><span class="score-badge score-${scoreClass}">${lead.lead_score || 0}</span></div>
                </div>
                <div class="view-item" style="grid-column: span 2;">
                    <div class="view-item-label"><i class="fas fa-map-pin"></i> Alamat</div>
                    <div class="view-item-value">${lead.address || '-'} ${lead.city ? ', ' + lead.city : ''}</div>
                </div>
            </div>
        </div>
        
        <div class="view-section">
            <div class="view-section-title"><i class="fas fa-history"></i> Riwayat Aktivitas</div>
            ${activitiesHtml}
        </div>
    `;
}

function editFromView() {
    if (currentViewLeadId) {
        closeViewModal();
        editLead(currentViewLeadId);
    }
}

// ===== EDIT LEAD =====
function editLead(id) {
    // Buka modal dulu
    openModal('editModal');
    
    // Set loading
    document.getElementById('edit_customer_name').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memuat...';
    document.getElementById('edit_phone').innerHTML = '';
    
    fetch('api/leads_marketing.php?action=get&id=' + id, { 
        method: 'GET',
        credentials: 'include',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('Edit lead response:', data);
        
        if (data.success && data.data) {
            const l = data.data;
            
            // Set form values
            document.getElementById('edit_id').value = l.id || '';
            document.getElementById('edit_customer_name').innerHTML = '<i class="fas fa-user" style="margin-right: 8px;"></i>' + 
                (l.first_name || '') + ' ' + (l.last_name || '');
            document.getElementById('edit_phone').innerHTML = '<i class="fab fa-whatsapp" style="color: #25D366; margin-right: 6px;"></i>' + 
                (l.phone || '');
            document.getElementById('edit_status').value = l.status || 'Baru';
            document.getElementById('edit_email').value = l.email || '';
            document.getElementById('edit_unit_type').value = l.unit_type || 'Type 36/60';
            document.getElementById('edit_program').value = l.program || 'Subsidi';
            document.getElementById('edit_address').value = l.address || '';
            document.getElementById('edit_city').value = l.city || '';
            document.getElementById('edit_notes').value = l.notes || '';
            
        } else {
            showToast('❌ Gagal memuat data: ' + (data.message || 'Unknown error'), 'error');
            closeEditModal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('❌ Terjadi kesalahan: ' + error.message, 'error');
        closeEditModal();
    });
}

function submitEditLead(e) {
    e.preventDefault();
    
    // Tampilkan loading
    const submitBtn = document.querySelector('#editModal .btn-primary');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    submitBtn.disabled = true;
    
    // Ambil data dari form
    const form = document.getElementById('editLeadForm');
    const formData = new FormData(form);
    
    // Konversi ke object
    const data = {
        id: formData.get('id'),
        marketing_id: formData.get('marketing_id') || '<?= $_SESSION['marketing_id'] ?? 0 ?>',
        status: formData.get('status') || 'Baru',
        email: formData.get('email') || '',
        unit_type: formData.get('unit_type') || 'Type 36/60',
        program: formData.get('program') || 'Subsidi',
        address: formData.get('address') || '',
        city: formData.get('city') || '',
        notes: formData.get('notes') || '',
        note: 'Update dari dashboard marketing'
    };
    
    console.log('Data dikirim:', data);
    
    // Kirim ke API
    fetch('api/leads_marketing.php?action=update_with_scoring', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data),
        credentials: 'include'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        return response.json();
    })
    .then(result => {
        console.log('Response:', result);
        
        if (result.success) {
            showToast('✅ ' + (result.message || 'Lead berhasil diupdate'), 'success');
            closeEditModal();
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast('❌ ' + (result.message || 'Gagal mengupdate data'), 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('❌ Terjadi kesalahan: ' + error.message, 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// ===== NOTE MODAL =====
function openNoteModal(leadId, customerName) {
    document.getElementById('note_lead_id').value = leadId;
    document.getElementById('note_customer_name').innerHTML = '<i class="fas fa-user" style="margin-right: 8px;"></i>' + customerName;
    document.getElementById('note_text').value = '';
    document.getElementById('note_action_type').value = 'follow_up';
    openModal('noteModal');
}

function submitNote(e) {
    e.preventDefault();
    
    const submitBtn = document.querySelector('#noteModal .btn-primary');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    submitBtn.disabled = true;
    
    const data = {
        lead_id: document.getElementById('note_lead_id').value,
        action_type: document.getElementById('note_action_type').value,
        note: document.getElementById('note_text').value
    };
    
    fetch('api/leads_marketing.php?action=add_note', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
        credentials: 'include'
    })
    .then(response => response.json())
    .then(res => {
        if (res.success) {
            showToast('✅ Catatan ditambahkan', 'success');
            closeNoteModal();
            if (currentViewLeadId) {
                viewLead(currentViewLeadId);
            }
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast('❌ ' + (res.message || 'Gagal menambahkan catatan'), 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('❌ Terjadi kesalahan', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// ===== MODAL CONTROLS =====
function openModal(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}

function closeViewModal() { closeModal('viewModal'); }
function closeEditModal() { closeModal('editModal'); }
function closeNoteModal() { closeModal('noteModal'); }

// ===== TOAST =====
function showToast(msg, type) {
    let toast = document.querySelector('.toast-message');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-message';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.background = type === 'success' ? '#2A9D8F' : '#D64F3C';
    toast.style.opacity = '1';
    setTimeout(() => toast.style.opacity = '0', 3000);
}

// ===== CLOCK =====
function updateDateTime() {
    const now = new Date();
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { 
        hour12: false 
    });
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>