<?php
/**
 * DEVELOPER_DASHBOARD.PHP - LEADENGINE
 * Version: 3.0.0 - Dashboard Lengkap untuk Developer
 * MOBILE FIRST UI - Statistik Leads, Unit, Marketing, Komisi
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek session developer
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
$location_access = $_SESSION['location_access'] ?? '';

// ========== CEK AKSES LOKASI ==========
$locations_list = explode(',', $location_access);
$locations_list = array_map('trim', $locations_list);
$locations_list = array_filter($locations_list);

if (empty($locations_list)) {
    $has_locations = false;
    $warning_message = "Anda belum memiliki akses lokasi. Silakan hubungi Admin untuk mengatur lokasi Anda.";
} else {
    $has_locations = true;
}

// ========== AMBIL DATA LOKASI UNTUK DROPDOWN ==========
$locations = [];
if ($has_locations) {
    $placeholders = implode(',', array_fill(0, count($locations_list), '?'));
    $stmt = $conn->prepare("SELECT * FROM locations WHERE location_key IN ($placeholders) ORDER BY sort_order");
    $stmt->execute($locations_list);
    $locations = $stmt->fetchAll();
}

// ========== STATISTIK LEAD ==========
$lead_stats = [
    'total' => 0,
    'today' => 0,
    'week' => 0,
    'month' => 0,
    'hot' => 0,
    'warm' => 0,
    'cold' => 0,
    'avg_score' => 0
];

if ($has_locations) {
    $placeholders = implode(',', array_fill(0, count($locations_list), '?'));
    
    // Total leads
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE location_key IN ($placeholders) AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
    $stmt->execute($locations_list);
    $lead_stats['total'] = $stmt->fetchColumn();
    
    // Today
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE location_key IN ($placeholders) AND DATE(created_at) = CURDATE() AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
    $stmt->execute($locations_list);
    $lead_stats['today'] = $stmt->fetchColumn();
    
    // Week
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE location_key IN ($placeholders) AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
    $stmt->execute($locations_list);
    $lead_stats['week'] = $stmt->fetchColumn();
    
    // Month
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE location_key IN ($placeholders) AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
    $stmt->execute($locations_list);
    $lead_stats['month'] = $stmt->fetchColumn();
    
    // Score stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN status IN ('Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun') THEN 1 END) as hot,
            COUNT(CASE WHEN status IN ('Booking', 'Survey', 'Follow Up') THEN 1 END) as warm,
            COUNT(CASE WHEN status IN ('Tolak Slik', 'Tidak Minat', 'Batal') THEN 1 END) as cold,
            AVG(lead_score) as avg_score
        FROM leads 
        WHERE location_key IN ($placeholders) 
        AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
    ");
    $stmt->execute($locations_list);
    $score = $stmt->fetch();
    
    $lead_stats['hot'] = (int)($score['hot'] ?? 0);
    $lead_stats['warm'] = (int)($score['warm'] ?? 0);
    $lead_stats['cold'] = (int)($score['cold'] ?? 0);
    $lead_stats['avg_score'] = $score['avg_score'] ? round($score['avg_score']) : 0;
}

// ========== STATISTIK UNIT ==========
$unit_stats = [
    'total_units' => 0,
    'available' => 0,
    'booked' => 0,
    'sold' => 0,
    'subsidi' => 0,
    'komersil' => 0,
    'total_clusters' => 0,
    'total_blocks' => 0
];

// Total clusters
$stmt = $conn->prepare("SELECT COUNT(*) FROM clusters WHERE developer_id = ?");
$stmt->execute([$developer_id]);
$unit_stats['total_clusters'] = $stmt->fetchColumn();

// Total blocks
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM blocks b
    JOIN clusters c ON b.cluster_id = c.id
    WHERE c.developer_id = ?
");
$stmt->execute([$developer_id]);
$unit_stats['total_blocks'] = $stmt->fetchColumn();

// Unit stats
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'AVAILABLE' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'BOOKED' THEN 1 ELSE 0 END) as booked,
        SUM(CASE WHEN status = 'SOLD' THEN 1 ELSE 0 END) as sold,
        SUM(CASE WHEN program = 'Subsidi' THEN 1 ELSE 0 END) as subsidi,
        SUM(CASE WHEN program = 'Komersil' THEN 1 ELSE 0 END) as komersil
    FROM units u
    JOIN blocks b ON u.block_id = b.id
    JOIN clusters c ON b.cluster_id = c.id
    WHERE c.developer_id = ?
");
$stmt->execute([$developer_id]);
$unit_data = $stmt->fetch();

$unit_stats['total_units'] = (int)($unit_data['total'] ?? 0);
$unit_stats['available'] = (int)($unit_data['available'] ?? 0);
$unit_stats['booked'] = (int)($unit_data['booked'] ?? 0);
$unit_stats['sold'] = (int)($unit_data['sold'] ?? 0);
$unit_stats['subsidi'] = (int)($unit_data['subsidi'] ?? 0);
$unit_stats['komersil'] = (int)($unit_data['komersil'] ?? 0);

// Progress penjualan
$unit_stats['progress'] = $unit_stats['total_units'] > 0 
    ? round(($unit_stats['sold'] / $unit_stats['total_units']) * 100, 1)
    : 0;

// ========== STATISTIK MARKETING ==========
$marketing_stats = [
    'total' => 0,
    'aktif' => 0,
    'inhouse' => 0,
    'canvasing' => 0
];

$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN marketing_type_id = 1 THEN 1 ELSE 0 END) as inhouse,
        SUM(CASE WHEN marketing_type_id = 2 THEN 1 ELSE 0 END) as canvasing
    FROM marketing_team 
    WHERE developer_id = ?
");
$stmt->execute([$developer_id]);
$marketing_stats = array_merge($marketing_stats, $stmt->fetch());

// ========== STATISTIK KOMISI ==========
$komisi_stats = [
    'pending' => 0,
    'pending_nominal' => 0,
    'cair' => 0,
    'cair_nominal' => 0,
    'batal' => 0,
    'batal_nominal' => 0
];

$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(komisi_final) as total_nominal,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'pending' THEN komisi_final ELSE 0 END) as pending_nominal,
        SUM(CASE WHEN status = 'cair' THEN 1 ELSE 0 END) as cair_count,
        SUM(CASE WHEN status = 'cair' THEN komisi_final ELSE 0 END) as cair_nominal,
        SUM(CASE WHEN status = 'batal' THEN 1 ELSE 0 END) as batal_count,
        SUM(CASE WHEN status = 'batal' THEN komisi_final ELSE 0 END) as batal_nominal
    FROM komisi_logs 
    WHERE developer_id = ?
");
$stmt->execute([$developer_id]);
$komisi_data = $stmt->fetch();

$komisi_stats['pending'] = (int)($komisi_data['pending_count'] ?? 0);
$komisi_stats['pending_nominal'] = (float)($komisi_data['pending_nominal'] ?? 0);
$komisi_stats['cair'] = (int)($komisi_data['cair_count'] ?? 0);
$komisi_stats['cair_nominal'] = (float)($komisi_data['cair_nominal'] ?? 0);
$komisi_stats['batal'] = (int)($komisi_data['batal_count'] ?? 0);
$komisi_stats['batal_nominal'] = (float)($komisi_data['batal_nominal'] ?? 0);

// ========== STATISTIK SPLIT HUTANG ==========
$split_stats = [
    'pending' => 0,
    'pending_nominal' => 0,
    'lunas' => 0,
    'lunas_nominal' => 0
];

$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(nominal) as total_nominal,
        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'PENDING' THEN nominal ELSE 0 END) as pending_nominal,
        SUM(CASE WHEN status = 'LUNAS' THEN 1 ELSE 0 END) as lunas_count,
        SUM(CASE WHEN status = 'LUNAS' THEN nominal ELSE 0 END) as lunas_nominal
    FROM komisi_split_hutang 
    WHERE developer_id = ?
");
$stmt->execute([$developer_id]);
$split_data = $stmt->fetch();

$split_stats['pending'] = (int)($split_data['pending_count'] ?? 0);
$split_stats['pending_nominal'] = (float)($split_data['pending_nominal'] ?? 0);
$split_stats['lunas'] = (int)($split_data['lunas_count'] ?? 0);
$split_stats['lunas_nominal'] = (float)($split_data['lunas_nominal'] ?? 0);

// ========== TOP PERFORMER MARKETING ==========
$top_marketing = [];
$stmt = $conn->prepare("
    SELECT 
        m.id,
        m.nama_lengkap,
        m.phone,
        COUNT(DISTINCT l.id) as total_leads,
        COUNT(DISTINCT CASE WHEN l.status IN ('Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun') THEN l.id END) as total_deal,
        COUNT(DISTINCT ma.id) as follow_up,
        COALESCE(kl.total_komisi, 0) as total_komisi
    FROM marketing_team m
    LEFT JOIN leads l ON m.id = l.assigned_marketing_team_id AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
    LEFT JOIN marketing_activities ma ON m.id = ma.marketing_id AND DATE(ma.created_at) = CURDATE()
    LEFT JOIN (
        SELECT marketing_id, SUM(komisi_final) as total_komisi
        FROM komisi_logs
        WHERE developer_id = ? AND status = 'cair'
        GROUP BY marketing_id
    ) kl ON m.id = kl.marketing_id
    WHERE m.developer_id = ? AND m.is_active = 1
    GROUP BY m.id
    HAVING total_leads > 0 OR total_deal > 0 OR total_komisi > 0
    ORDER BY total_deal DESC, total_komisi DESC
    LIMIT 5
");
$stmt->execute([$developer_id, $developer_id]);
$top_marketing = $stmt->fetchAll();

// ========== RECENT ACTIVITIES ==========
$recent_activities = [];
$stmt = $conn->prepare("
    SELECT 
        ma.*,
        l.first_name,
        l.last_name,
        l.phone,
        m.nama_lengkap as marketing_name
    FROM marketing_activities ma
    JOIN leads l ON ma.lead_id = l.id
    JOIN marketing_team m ON ma.marketing_id = m.id
    WHERE ma.developer_id = ?
    ORDER BY ma.created_at DESC
    LIMIT 20
");
$stmt->execute([$developer_id]);
$recent_activities = $stmt->fetchAll();

// ========== DATA CHART ==========
$chart_labels = [];
$chart_data = [];

if ($has_locations) {
    $placeholders = implode(',', array_fill(0, count($locations_list), '?'));
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('d/m', strtotime($date));
        
        $sql = "SELECT COUNT(*) FROM leads 
                WHERE location_key IN ($placeholders) 
                AND DATE(created_at) = ? 
                AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge($locations_list, [$date]));
        $chart_data[] = (int)$stmt->fetchColumn();
    }
} else {
    for ($i = 6; $i >= 0; $i--) {
        $chart_labels[] = date('d/m', strtotime("-$i days"));
        $chart_data[] = 0;
    }
}

// ========== FILTER STATUS ==========
$status_list = [
    'Baru', 
    'Follow Up', 
    'Survey', 
    'Booking', 
    'Tolak Slik', 
    'Tidak Minat', 
    'Batal', 
    'Deal KPR', 
    'Deal Tunai',
    'Deal Bertahap 6 Bulan',
    'Deal Bertahap 1 Tahun'
];

$status_filter = $_GET['status'] ?? '';

// ========== PAGINATION ==========
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// ========== AMBIL DATA LEADS ==========
$leads = [];
$total_records = 0;
$total_pages = 1;

if ($has_locations) {
    $placeholders = implode(',', array_fill(0, count($locations_list), '?'));
    
    // Query untuk mengambil data leads
    $sql = "SELECT 
            l.*, 
            loc.display_name as location_display, 
            loc.icon,
            m.nama_lengkap as marketing_name,
            m.phone as marketing_phone,
            u_external.nama_lengkap as external_marketing_name,
            CASE 
                WHEN l.assigned_type = 'internal' THEN m.nama_lengkap
                WHEN l.assigned_type = 'external' THEN u_external.nama_lengkap
                ELSE '-'
            END as marketing_display
            FROM leads l
            LEFT JOIN locations loc ON l.location_key = loc.location_key
            LEFT JOIN marketing_team m ON l.assigned_marketing_team_id = m.id
            LEFT JOIN users u_external ON l.assigned_marketing_team_id = u_external.id AND u_external.role = 'marketing_external'
            WHERE l.location_key IN ($placeholders)
            AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";
    $params = $locations_list;
    
    if ($status_filter) {
        $sql .= " AND l.status = ?";
        $params[] = $status_filter;
    }
    
    // Hitung total records (QUERY SEDERHANA untuk COUNT)
    $count_sql = "SELECT COUNT(*) FROM leads l
                  WHERE l.location_key IN ($placeholders)
                  AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";
    $count_params = $locations_list;
    
    if ($status_filter) {
        $count_sql .= " AND l.status = ?";
        $count_params[] = $status_filter;
    }
    
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Ambil data dengan pagination
    $sql .= " ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();
}

// ========== PASS DATA KE JAVASCRIPT ==========
echo '<script>';
echo 'window.chartLabels = ' . json_encode($chart_labels) . ';';
echo 'window.chartData = ' . json_encode($chart_data) . ';';
echo 'window.API_KEY = "' . API_KEY . '";';
echo '</script>';

// ========== SET VARIABLES UNTUK INCLUDE ==========
$page_title = 'Dashboard Developer';
$page_subtitle = $developer_name;
$page_icon = 'fas fa-chart-pie';
$use_chart = true;

include 'includes/header.php';
?>

<!-- SIDEBAR -->
<?php include 'includes/sidebar.php'; ?>

<style>
/* ===== VARIABLES ===== */
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
    --gold: #E3B584;
}

/* ===== MOBILE FIRST LAYOUT ===== */
.main-content {
    width: 100%;
    min-height: 100vh;
    padding: 12px;
    margin-left: 0 !important;
}

/* ===== TOP BAR ===== */
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

/* ===== WELCOME CARD ===== */
.header-card {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 24px;
    padding: 24px;
    margin-bottom: 20px;
    color: white;
    box-shadow: 0 10px 30px rgba(27,74,60,0.3);
}

/* ===== STATS GRID ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    border-left: 6px solid;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.stat-card:nth-child(1) { border-left-color: var(--primary); }
.stat-card:nth-child(2) { border-left-color: var(--info); }
.stat-card:nth-child(3) { border-left-color: var(--success); }
.stat-card:nth-child(4) { border-left-color: var(--warning); }

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
    margin-bottom: 2px;
}

.stat-value {
    font-size: 22px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

/* ===== SCORE GRID ===== */
.score-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.score-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-left: 6px solid;
}

.score-card:nth-child(1) { border-left-color: #D64F3C; }
.score-card:nth-child(2) { border-left-color: #E9C46A; }
.score-card:nth-child(3) { border-left-color: #4A90E2; }

.score-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.score-icon.hot { background: rgba(214,79,60,0.1); color: #D64F3C; }
.score-icon.warm { background: rgba(233,196,106,0.1); color: #B87C00; }
.score-icon.cold { background: rgba(74,144,226,0.1); color: #4A90E2; }

.score-info { flex: 1; }
.score-value { font-size: 22px; font-weight: 800; line-height: 1.2; }
.score-label { font-size: 11px; color: var(--text-muted); }

/* ===== UNIT STATS GRID ===== */
.unit-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.unit-card {
    background: white;
    border-radius: 16px;
    padding: 12px;
    text-align: center;
    border-left: 6px solid;
}

.unit-card:nth-child(1) { border-left-color: var(--success); }
.unit-card:nth-child(2) { border-left-color: var(--warning); }
.unit-card:nth-child(3) { border-left-color: var(--danger); }
.unit-card:nth-child(4) { border-left-color: var(--info); }

.unit-value { font-size: 18px; font-weight: 800; }
.unit-label { font-size: 10px; color: var(--text-muted); }

/* ===== KOMISI GRID ===== */
.komisi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.komisi-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    border-left: 6px solid;
}

.komisi-card.pending { border-left-color: var(--warning); }
.komisi-card.cair { border-left-color: var(--success); }
.komisi-card.split { border-left-color: var(--info); }

.komisi-icon { font-size: 20px; margin-bottom: 8px; }
.komisi-icon.pending { color: var(--warning); }
.komisi-icon.cair { color: var(--success); }
.komisi-icon.split { color: var(--info); }

.komisi-label { font-size: 11px; color: var(--text-muted); }
.komisi-value { font-size: 18px; font-weight: 800; }
.komisi-sub { font-size: 11px; color: var(--text-muted); }

/* ===== CHART CARD ===== */
.chart-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.chart-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-title i { color: var(--secondary); }
.chart-container { height: 250px; }

/* ===== TOP PERFORMER ===== */
.performer-grid {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    padding: 8px 0 20px 0;
    margin-bottom: 20px;
}

.performer-card {
    flex: 0 0 280px;
    background: white;
    border-radius: 20px;
    padding: 16px;
    border-left: 6px solid;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.performer-card:nth-child(1) { border-left-color: #FFD700; }
.performer-card:nth-child(2) { border-left-color: #C0C0C0; }
.performer-card:nth-child(3) { border-left-color: #CD7F32; }

.performer-rank {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: white;
}

.performer-name { font-weight: 700; color: var(--primary); }
.performer-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
.performer-stat-value { font-size: 16px; font-weight: 800; }
.performer-stat-label { font-size: 10px; color: var(--text-muted); }

/* ===== ACTIVITIES ===== */
.activity-item {
    background: var(--primary-soft);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 8px;
    border-left: 4px solid var(--secondary);
}

.activity-time { font-size: 10px; color: var(--text-muted); }
.activity-name { font-weight: 600; color: var(--primary); }
.activity-marketing { font-size: 11px; color: var(--info); }

/* ===== FILTER BAR ===== */
.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-select {
    flex: 1;
    min-width: 200px;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
}

.filter-actions { display: flex; gap: 12px; }
.filter-btn {
    padding: 12px 24px;
    background: var(--secondary);
    color: white;
    border: none;
    border-radius: 40px;
    font-weight: 600;
    cursor: pointer;
}
.filter-btn.reset { background: var(--border); color: var(--text); }

/* ===== TABLE ===== */
.table-container {
    background: white;
    border-radius: 24px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 10px;
}

.table-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-badge {
    background: var(--primary-soft);
    color: var(--primary);
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 12px;
}

.table-responsive {
    overflow-x: auto;
    margin: 0 -20px;
    padding: 0 20px;
    width: calc(100% + 40px);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

th {
    background: var(--primary-soft);
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 12px;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

/* ===== STATUS BADGE ===== */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
}

.status-badge.Baru { background: #4A90E2; color: white; }
.status-badge.Follow\ Up { background: #E9C46A; color: #1A2A24; }
.status-badge.Survey { background: #E9C46A; color: #1A2A24; }
.status-badge.Booking { background: #1B4A3C; color: white; }
.status-badge.Deal\ KPR { background: #2A9D8F; color: white; }
.status-badge.Deal\ Tunai { background: #FF9800; color: white; }
.status-badge.Deal\ Bertahap\ 6\ Bulan { background: #2A9D8F; color: white; }
.status-badge.Deal\ Bertahap\ 1\ Tahun { background: #2A9D8F; color: white; }
.status-badge.Tolak\ Slik { background: #9C27B0; color: white; }
.status-badge.Tidak\ Minat { background: #757575; color: white; }
.status-badge.Batal { background: #D64F3C; color: white; }

.score-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
}
.score-hot { background: #D64F3C; color: white; }
.score-warm { background: #E9C46A; color: #1A2A24; }
.score-cold { background: #4A90E2; color: white; }

/* ===== ACTION BUTTONS ===== */
.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    text-decoration: none;
}

.action-btn.view { color: var(--info); }
.action-btn.whatsapp { color: #25D366; }

/* ===== PAGINATION ===== */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.pagination-btn {
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: white;
    border: 2px solid var(--border);
    color: var(--text);
    text-decoration: none;
}

.pagination-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* ===== MODAL ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.modal.show { display: flex !important; }

.modal-content {
    background: white;
    border-radius: 28px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 20px;
    border-bottom: 2px solid var(--primary-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-close {
    width: 40px;
    height: 40px;
    background: var(--primary-soft);
    border: none;
    border-radius: 12px;
    color: var(--secondary);
    cursor: pointer;
}

.modal-body { padding: 20px; overflow-y: auto; }
.modal-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* ===== VIEW SECTION ===== */
.view-section {
    background: var(--primary-soft);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 16px;
}

.view-section-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.view-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.view-item-label {
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 2px;
}
.view-item-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    word-break: break-word;
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

/* ===== DESKTOP ===== */
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
    
    .stats-grid { grid-template-columns: repeat(4, 1fr); }
    .unit-grid { grid-template-columns: repeat(4, 1fr); }
    .komisi-grid { grid-template-columns: repeat(3, 1fr); }
}
</style>

<div class="main-content">

    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="welcome-text">
            <i class="<?= $page_icon ?>"></i>
            <h2>
                <?= $page_title ?>
                <span><?= htmlspecialchars($page_subtitle) ?></span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>

    <!-- ===== PERINGATAN JIKA TIDAK ADA AKSES LOKASI ===== -->
    <?php if (!$has_locations): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 24px; border-radius: 20px; margin-bottom: 24px; border-left: 6px solid #dc3545; display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
        <div style="width: 60px; height: 60px; background: rgba(220,53,69,0.2); border-radius: 30px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-exclamation-triangle fa-3x" style="color: #dc3545;"></i>
        </div>
        <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; color: #721c24; font-size: 20px;">Akses Lokasi Belum Dikonfigurasi</h3>
            <p style="margin: 0; opacity: 0.9; font-size: 15px;"><?= $warning_message ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- WELCOME CARD -->
    <div class="header-card">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 30px; display: flex; align-items: center; justify-content: center; font-size: 28px;">
                <i class="fas fa-user-tie"></i>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <h3 style="font-size: 20px; font-weight: 700; margin-bottom: 5px; color: white;">Selamat datang, <?= htmlspecialchars($developer_name) ?>!</h3>
                <p style="opacity: 0.9; font-size: 14px; color: white; margin: 0;">
                    Developer ID: #<?= $developer_id ?> | Total Unit: <?= $unit_stats['total_units'] ?>
                </p>
            </div>
        </div>
    </div>

    <!-- FILTER INFO -->
    <?php if ($status_filter): ?>
    <div style="background: #E7F3EF; border-radius: 40px; padding: 10px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <i class="fas fa-filter" style="color: var(--secondary);"></i>
        <span style="font-weight: 600; color: var(--primary);">Filter aktif:</span>
        <span style="background: white; padding: 4px 12px; border-radius: 30px; font-size: 12px;"><i class="fas fa-tag"></i> <?= htmlspecialchars($status_filter) ?></span>
        <a href="?" style="margin-left: auto; color: var(--secondary); font-size: 13px;"><i class="fas fa-times"></i> Reset</a>
    </div>
    <?php endif; ?>

    <!-- ===== SECTION 1: STATISTIK LEAD ===== -->
    <h3 style="color: var(--primary); margin: 20px 0 10px 0; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-users" style="color: var(--secondary);"></i> Statistik Lead
    </h3>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Leads</div>
            <div class="stat-value"><?= number_format($lead_stats['total']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-label">Hari Ini</div>
            <div class="stat-value"><?= $lead_stats['today'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
            <div class="stat-label">Minggu Ini</div>
            <div class="stat-value"><?= $lead_stats['week'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-label">Bulan Ini</div>
            <div class="stat-value"><?= $lead_stats['month'] ?></div>
        </div>
    </div>

    <div class="score-grid">
        <div class="score-card">
            <div class="score-icon hot"><i class="fas fa-fire"></i></div>
            <div class="score-info">
                <div class="score-value"><?= $lead_stats['hot'] ?></div>
                <div class="score-label">Hot Lead</div>
            </div>
        </div>
        <div class="score-card">
            <div class="score-icon warm"><i class="fas fa-sun"></i></div>
            <div class="score-info">
                <div class="score-value"><?= $lead_stats['warm'] ?></div>
                <div class="score-label">Warm Lead</div>
            </div>
        </div>
        <div class="score-card">
            <div class="score-icon cold"><i class="fas fa-snowflake"></i></div>
            <div class="score-info">
                <div class="score-value"><?= $lead_stats['cold'] ?></div>
                <div class="score-label">Cold Lead</div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 2: STATISTIK UNIT ===== -->
    <h3 style="color: var(--primary); margin: 30px 0 10px 0; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-home" style="color: var(--secondary);"></i> Statistik Unit
    </h3>

    <div class="unit-grid">
        <div class="unit-card">
            <div class="unit-value" style="color: var(--success);"><?= $unit_stats['available'] ?></div>
            <div class="unit-label">Available</div>
        </div>
        <div class="unit-card">
            <div class="unit-value" style="color: var(--warning);"><?= $unit_stats['booked'] ?></div>
            <div class="unit-label">Booked</div>
        </div>
        <div class="unit-card">
            <div class="unit-value" style="color: var(--danger);"><?= $unit_stats['sold'] ?></div>
            <div class="unit-label">Sold</div>
        </div>
        <div class="unit-card">
            <div class="unit-value" style="color: var(--info);"><?= $unit_stats['total_units'] ?></div>
            <div class="unit-label">Total Unit</div>
        </div>
    </div>

    <div style="display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap;">
        <div style="flex: 1; background: white; border-radius: 16px; padding: 16px;">
            <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 8px;">Program Subsidi</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--success);"><?= $unit_stats['subsidi'] ?></div>
        </div>
        <div style="flex: 1; background: white; border-radius: 16px; padding: 16px;">
            <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 8px;">Program Komersil</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--info);"><?= $unit_stats['komersil'] ?></div>
        </div>
    </div>

    <div style="background: white; border-radius: 16px; padding: 16px; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 13px; color: var(--text-muted);">Progress Penjualan</span>
            <span style="font-weight: 700; color: var(--primary);"><?= $unit_stats['progress'] ?>%</span>
        </div>
        <div style="height: 10px; background: var(--border); border-radius: 5px; overflow: hidden;">
            <div style="height: 100%; width: <?= $unit_stats['progress'] ?>%; background: linear-gradient(90deg, var(--success), var(--info)); border-radius: 5px;"></div>
        </div>
    </div>

    <!-- ===== SECTION 3: STATISTIK KOMISI ===== -->
    <h3 style="color: var(--primary); margin: 30px 0 10px 0; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-coins" style="color: var(--secondary);"></i> Statistik Komisi
    </h3>

    <div class="komisi-grid">
        <div class="komisi-card pending">
            <div class="komisi-icon pending"><i class="fas fa-clock"></i></div>
            <div class="komisi-label">Pending</div>
            <div class="komisi-value"><?= $komisi_stats['pending'] ?></div>
            <div class="komisi-sub">Rp <?= number_format($komisi_stats['pending_nominal'], 0, ',', '.') ?></div>
        </div>
        <div class="komisi-card cair">
            <div class="komisi-icon cair"><i class="fas fa-check-circle"></i></div>
            <div class="komisi-label">Cair</div>
            <div class="komisi-value"><?= $komisi_stats['cair'] ?></div>
            <div class="komisi-sub">Rp <?= number_format($komisi_stats['cair_nominal'], 0, ',', '.') ?></div>
        </div>
        <div class="komisi-card split">
            <div class="komisi-icon split"><i class="fas fa-handshake"></i></div>
            <div class="komisi-label">Split Hutang</div>
            <div class="komisi-value"><?= $split_stats['pending'] ?></div>
            <div class="komisi-sub">Rp <?= number_format($split_stats['pending_nominal'], 0, ',', '.') ?></div>
        </div>
    </div>

    <!-- ===== SECTION 4: CHART ===== -->
    <div class="chart-card">
        <div class="chart-title">
            <i class="fas fa-chart-line"></i> Tren Leads 7 Hari Terakhir
        </div>
        <div class="chart-container">
            <canvas id="trendChart"></canvas>
        </div>
        <?php if (!$has_locations): ?>
        <p style="text-align: center; color: var(--text-muted); margin-top: 10px;">
            <i class="fas fa-info-circle"></i> Data chart kosong karena akses lokasi belum dikonfigurasi.
        </p>
        <?php endif; ?>
    </div>

    <!-- ===== SECTION 5: TOP PERFORMER MARKETING ===== -->
    <?php if (!empty($top_marketing)): ?>
    <h3 style="color: var(--primary); margin: 20px 0 10px 0; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-crown" style="color: #FFD700;"></i> Top Performer Marketing
    </h3>

    <div class="performer-grid">
        <?php foreach ($top_marketing as $index => $m): 
            $rank_class = $index == 0 ? '#FFD700' : ($index == 1 ? '#C0C0C0' : ($index == 2 ? '#CD7F32' : 'var(--primary-soft)'));
        ?>
        <div class="performer-card" style="border-left-color: <?= $rank_class ?>;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                <div class="performer-rank" style="background: <?= $rank_class ?>;"><?= $index + 1 ?></div>
                <div class="performer-name"><?= htmlspecialchars($m['nama_lengkap']) ?></div>
            </div>
            <div class="performer-stats">
                <div>
                    <div class="performer-stat-value"><?= $m['total_leads'] ?></div>
                    <div class="performer-stat-label">Leads</div>
                </div>
                <div>
                    <div class="performer-stat-value" style="color: var(--success);"><?= $m['total_deal'] ?></div>
                    <div class="performer-stat-label">Deal</div>
                </div>
                <div>
                    <div class="performer-stat-value"><?= $m['follow_up'] ?></div>
                    <div class="performer-stat-label">Follow Up</div>
                </div>
                <div>
                    <div class="performer-stat-value" style="color: var(--info);">Rp <?= number_format($m['total_komisi'] ?? 0, 0, ',', '.') ?></div>
                    <div class="performer-stat-label">Komisi</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ===== SECTION 6: RECENT ACTIVITIES ===== -->
    <?php if (!empty($recent_activities)): ?>
    <h3 style="color: var(--primary); margin: 20px 0 10px 0; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-history" style="color: var(--secondary);"></i> Aktivitas Terbaru
    </h3>

    <div style="background: white; border-radius: 20px; padding: 16px; margin-bottom: 20px;">
        <?php foreach ($recent_activities as $act): 
            $customer = trim($act['first_name'] . ' ' . ($act['last_name'] ?? ''));
        ?>
        <div class="activity-item">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <span class="activity-time"><?= date('d/m/Y H:i', strtotime($act['created_at'])) ?></span>
                <span class="activity-marketing"><?= htmlspecialchars($act['marketing_name']) ?></span>
            </div>
            <div class="activity-name"><?= htmlspecialchars($customer ?: 'Customer') ?></div>
            <div style="font-size: 12px; color: var(--text); margin-top: 4px;">
                <?php 
                $action_text = '';
                switch($act['action_type']) {
                    case 'update_status': $action_text = "Mengubah status dari {$act['status_before']} ke {$act['status_after']}"; break;
                    case 'add_note': $action_text = "Menambahkan catatan"; break;
                    case 'booking': $action_text = "Booking unit"; break;
                    default: $action_text = $act['action_type'];
                }
                echo htmlspecialchars($action_text);
                ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ===== SECTION 7: FILTER BAR ===== -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <select name="status" class="filter-select">
                <option value="">Semua Status</option>
                <?php foreach ($status_list as $status): ?>
                <option value="<?= $status ?>" <?= $status_filter == $status ? 'selected' : '' ?>>
                    <?= $status ?>
                </option>
                <?php endforeach; ?>
            </select>

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

    <!-- ===== SECTION 8: TABLE LEADS ===== -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fas fa-list"></i> Daftar Leads
            </h3>
            <div class="table-badge">
                <i class="fas fa-database"></i> Total: <?= $total_records ?> | Halaman <?= $page ?> dari <?= $total_pages ?>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Lokasi</th>
                        <th>Nama</th>
                        <th>Kontak</th>
                        <th>Marketing</th>
                        <th>Unit</th>
                        <th>Program</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 60px;">
                            <i class="fas fa-inbox fa-4x" style="color: var(--border); margin-bottom: 16px;"></i>
                            <p style="color: var(--text-muted);">
                                <?php if (!$has_locations): ?>
                                    Akses lokasi belum dikonfigurasi. Hubungi admin.
                                <?php else: ?>
                                    Belum ada data lead
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($leads as $lead):
                            $score_class = $lead['lead_score'] >= 80 ? 'hot' : ($lead['lead_score'] >= 60 ? 'warm' : 'cold');
                            $full_name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
                            $location_name = $lead['location_display'] ?? $lead['location_key'];
                            $status_class = str_replace(' ', '-', $lead['status'] ?? 'Baru');
                            $marketing_display = $lead['marketing_display'] ?? ($lead['marketing_name'] ?? '-');
                        ?>
                        <tr>
                            <td><strong>#<?= $lead['id'] ?></strong></td>
                            <td>
                                <span class="location-badge">
                                    <?= $lead['icon'] ?? '' ?> <?= htmlspecialchars($location_name) ?>
                                </span>
                            </td>
                            <td><strong><?= htmlspecialchars($full_name ?: '-') ?></strong></td>
                            <td>
                                <div>
                                    <a href="https://wa.me/<?= $lead['phone'] ?>" target="_blank" style="color: #25D366;">
                                        <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($lead['phone']) ?>
                                    </a>
                                    <?php if (!empty($lead['email'])): ?>
                                    <div style="font-size: 11px; color: var(--text-muted);">
                                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($lead['email']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?= htmlspecialchars($marketing_display) ?>
                                <?php if ($lead['assigned_type']): ?>
                                <br><small style="color: var(--text-muted);">(<?= $lead['assigned_type'] ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($lead['unit_type'] ?? 'Type 36') ?></td>
                            <td><?= htmlspecialchars($lead['program'] ?? 'Subsidi') ?></td>
                            <td>
                                <span class="status-badge <?= $status_class ?>">
                                    <?= htmlspecialchars($lead['status'] ?? 'Baru') ?>
                                </span>
                            </td>
                            <td>
                                <span class="score-badge score-<?= $score_class ?>">
                                    <?= $lead['lead_score'] ?? 0 ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($lead['created_at'])) ?></td>
                            <td style="text-align: center;">
                                <div class="action-buttons">
                                    <button class="action-btn view" onclick="viewLead(<?= $lead['id'] ?>)" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="https://wa.me/<?= $lead['phone'] ?>" target="_blank" class="action-btn whatsapp" title="Chat WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&status=<?= urlencode($status_filter) ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>" class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&status=<?= urlencode($status_filter) ?>" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Developer Dashboard v3.0</p>
        <p>Total Leads: <?= number_format($lead_stats['total']) ?> | Total Unit: <?= $unit_stats['total_units'] ?> | Komisi Pending: Rp <?= number_format($komisi_stats['pending_nominal'], 0, ',', '.') ?></p>
    </div>

</div>

<!-- VIEW MODAL -->
<div class="modal" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-circle"></i> Detail Lead #<span id="viewId"></span></h2>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body" id="viewContent">
            <div style="text-align: center; padding: 40px;">
                <div class="spinner" style="width: 40px; height: 40px; border: 4px solid var(--primary-soft); border-top-color: var(--secondary); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                <p style="margin-top: 16px; color: var(--text-muted);">Memuat data...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeViewModal()">Tutup</button>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="assets/js/index.js"></script>

<script>
// ===== VIEW LEAD =====
function viewLead(id) {
    document.getElementById('viewId').textContent = id;
    document.getElementById('viewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner" style="width: 40px; height: 40px; border: 4px solid var(--primary-soft); border-top-color: var(--secondary); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div><p style="margin-top: 16px; color: var(--text-muted);">Memuat data...</p></div>';
    openModal('viewModal');
    
    fetch('api/leads.php?action=get&id=' + id + '&key=<?= API_KEY ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                const l = data.data;
                const name = (l.first_name || '') + ' ' + (l.last_name || '');
                const scoreClass = l.lead_score >= 80 ? 'score-hot' : (l.lead_score >= 60 ? 'score-warm' : 'score-cold');
                
                // Gunakan marketing_final_name dan marketing_final_phone dari API
                const marketingName = l.marketing_final_name || '-';
                const marketingPhone = l.marketing_final_phone || '-';
                const marketingType = l.marketing_type_label || (l.assigned_type || '-');
                
                document.getElementById('viewContent').innerHTML = `
                    <div class="view-section">
                        <div class="view-section-title"><i class="fas fa-user"></i> Informasi Pribadi</div>
                        <div class="view-grid">
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-user"></i> Nama</div>
                                <div class="view-item-value">${name}</div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-map-marker-alt"></i> Lokasi</div>
                                <div class="view-item-value">${l.location_display || l.location_key}</div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fab fa-whatsapp"></i> WhatsApp</div>
                                <div class="view-item-value"><a href="https://wa.me/${l.phone}" target="_blank">${l.phone}</a></div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-envelope"></i> Email</div>
                                <div class="view-item-value">${l.email || '-'}</div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-city"></i> Kota</div>
                                <div class="view-item-value">${l.city || '-'}</div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-home"></i> Unit</div>
                                <div class="view-item-value">${l.unit_type || 'Type 36'}</div>
                            </div>
                        </div>
                    </div>
                    <div class="view-section">
                        <div class="view-section-title"><i class="fas fa-tag"></i> Status</div>
                        <div class="view-grid">
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-tag"></i> Status</div>
                                <div class="view-item-value"><span class="status-badge ${(l.status || 'Baru').replace(/\s+/g, '-')}">${l.status || 'Baru'}</span></div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-chart-line"></i> Score</div>
                                <div class="view-item-value"><span class="score-badge ${scoreClass}">${l.lead_score || 0}</span></div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-file-signature"></i> Program</div>
                                <div class="view-item-value">${l.program || 'Subsidi'}</div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-calendar"></i> Tanggal</div>
                                <div class="view-item-value">${new Date(l.created_at).toLocaleDateString('id-ID')}</div>
                            </div>
                        </div>
                    </div>
                    <div class="view-section">
                        <div class="view-section-title">
                            <i class="fas fa-user-tie"></i> Marketing 
                            <span style="background: var(--primary-soft); padding: 2px 8px; border-radius: 20px; font-size: 10px; margin-left: 8px;">${marketingType}</span>
                        </div>
                        <div class="view-grid">
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-user"></i> Nama</div>
                                <div class="view-item-value"><strong>${marketingName}</strong></div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fab fa-whatsapp"></i> Kontak</div>
                                <div class="view-item-value">
                                    ${marketingPhone !== '-' ? `<a href="https://wa.me/${marketingPhone}" target="_blank">${marketingPhone}</a>` : '-'}
                                </div>
                            </div>
                        </div>
                    </div>
                    ${l.notes ? `
                    <div class="view-section">
                        <div class="view-section-title"><i class="fas fa-sticky-note"></i> Catatan</div>
                        <div style="background:white; padding:16px; border-radius:12px; white-space: pre-line;">${l.notes}</div>
                    </div>` : ''}
                `;
            } else {
                document.getElementById('viewContent').innerHTML = '<div style="text-align: center; padding: 40px; color: var(--danger);"><i class="fas fa-exclamation-circle fa-3x"></i><p style="margin-top: 16px;">Gagal memuat data</p></div>';
            }
        })
        .catch((err) => {
            console.error('Error:', err);
            document.getElementById('viewContent').innerHTML = '<div style="text-align: center; padding: 40px; color: var(--danger);"><i class="fas fa-exclamation-circle fa-3x"></i><p style="margin-top: 16px;">Terjadi kesalahan</p></div>';
        });
}

// ===== MODAL CONTROLS =====
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = '';
}

function closeViewModal() {
    closeModal('viewModal');
}

// ===== DATE TIME =====
function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', options);
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { hour12: false });
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
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(modal => {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            });
        }
    });
});

// Export functions
window.viewLead = viewLead;
window.openModal = openModal;
window.closeViewModal = closeViewModal;
</script>

<?php include 'includes/footer.php'; ?>