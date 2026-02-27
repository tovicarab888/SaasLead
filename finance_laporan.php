<?php
/**
 * FINANCE_DEVELOPER_LAPORAN.PHP - Laporan Komisi Internal untuk Finance Developer (ID 9)
 * Version: 2.0.0 - UI SUPER KEREN (Mempertahankan UI Referensi)
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek session finance developer
if (!isFinance()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$developer_id = $_SESSION['developer_id'] ?? 0;
if ($developer_id <= 0) {
    die("Error: Developer ID tidak ditemukan");
}

// Ambil data developer
$stmt = $conn->prepare("SELECT nama_lengkap, nama_perusahaan FROM users WHERE id = ?");
$stmt->execute([$developer_id]);
$developer = $stmt->fetch();
$developer_name = $developer['nama_lengkap'] ?? 'Developer';
$perusahaan = $developer['nama_perusahaan'] ?? '';

// ========== FUNGSI EXPORT EXCEL ==========
function exportToExcel($conn, $params) {
    $developer_id = $params['developer_id'];
    $status = $params['status'] ?? 'all';
    $marketing_id = (int)($params['marketing_id'] ?? 0);
    $start_date = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $params['end_date'] ?? date('Y-m-d');
    $search = $params['search'] ?? '';
    
    // Build query
    $sql = "
        SELECT 
            k.id as 'ID Komisi',
            DATE_FORMAT(k.created_at, '%d/%m/%Y') as 'Tanggal',
            k.assigned_type as 'Tipe',
            m.nama_lengkap as 'Marketing',
            m.phone as 'No. HP Marketing',
            CONCAT(l.first_name, ' ', l.last_name) as 'Nama Customer',
            l.phone as 'No. HP Customer',
            l.email as 'Email Customer',
            loc.display_name as 'Lokasi',
            u.nomor_unit as 'Nomor Unit',
            u.tipe_unit as 'Tipe Unit',
            u.harga as 'Harga Unit',
            k.komisi_final as 'Komisi Final',
            k.status as 'Status',
            DATE_FORMAT(k.tanggal_cair, '%d/%m/%Y') as 'Tanggal Cair',
            k.bukti_transfer as 'Bukti Transfer',
            k.catatan as 'Catatan'
        FROM komisi_logs k
        LEFT JOIN leads l ON k.lead_id = l.id
        LEFT JOIN locations loc ON l.location_key = loc.location_key
        LEFT JOIN marketing_team m ON k.marketing_id = m.id
        LEFT JOIN units u ON k.unit_id = u.id
        WHERE k.developer_id = ? AND k.assigned_type = 'internal'
    ";
    $params_sql = [$developer_id];

    if ($status !== 'all') {
        $sql .= " AND k.status = ?";
        $params_sql[] = $status;
    }

    if ($marketing_id > 0) {
        $sql .= " AND k.marketing_id = ?";
        $params_sql[] = $marketing_id;
    }

    $sql .= " AND DATE(k.created_at) BETWEEN ? AND ?";
    $params_sql[] = $start_date;
    $params_sql[] = $end_date;

    if (!empty($search)) {
        $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR m.nama_lengkap LIKE ?)";
        $s = "%$search%";
        $params_sql = array_merge($params_sql, [$s, $s, $s, $s]);
    }

    $sql .= " ORDER BY k.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params_sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_komisi_internal_' . date('Ymd') . '.xls"');
    header('Cache-Control: max-age=0');

    // Output Excel
    echo '<table border="1">';
    
    // Header
    echo '<tr>';
    if (!empty($data)) {
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . $header . '</th>';
        }
    }
    echo '</tr>';
    
    // Data
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $value) {
            echo '<td>' . htmlspecialchars($value ?? '') . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

// Handle export
if (isset($_POST['action']) && $_POST['action'] === 'export_excel') {
    $_POST['developer_id'] = $developer_id;
    exportToExcel($conn, $_POST);
}

// ========== FILTER ==========
$report_type = $_GET['type'] ?? 'summary';
$status = $_GET['status'] ?? 'all';
$marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// ========== SUMMARY REPORT ==========
$summary_sql = "
    SELECT 
        COUNT(*) as total_transaksi,
        COUNT(DISTINCT marketing_id) as total_marketing,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count,
        COALESCE(SUM(CASE WHEN status = 'cair' THEN 1 ELSE 0 END), 0) as cair_count,
        COALESCE(SUM(CASE WHEN status = 'batal' THEN 1 ELSE 0 END), 0) as batal_count,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN komisi_final ELSE 0 END), 0) as pending_nominal,
        COALESCE(SUM(CASE WHEN status = 'cair' THEN komisi_final ELSE 0 END), 0) as cair_nominal,
        COALESCE(SUM(komisi_final), 0) as total_nominal
    FROM komisi_logs
    WHERE developer_id = ? AND assigned_type = 'internal'
    AND DATE(created_at) BETWEEN ? AND ?
";
$summary_params = [$developer_id, $start_date, $end_date];

if ($marketing_id > 0) {
    $summary_sql .= " AND marketing_id = ?";
    $summary_params[] = $marketing_id;
}

$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->execute($summary_params);
$summary = $summary_stmt->fetch();

// ========== GRAFIK BULANAN ==========
$monthly_sql = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as bulan,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'cair' THEN komisi_final ELSE 0 END) as nominal_cair,
        SUM(komisi_final) as total_nominal
    FROM komisi_logs
    WHERE developer_id = ? AND assigned_type = 'internal'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY bulan ASC
";
$monthly_stmt = $conn->prepare($monthly_sql);
$monthly_stmt->execute([$developer_id]);
$monthly_data = $monthly_stmt->fetchAll();

// ========== LAPORAN PER MARKETING ==========
$marketing_report_sql = "
    SELECT 
        m.id,
        m.nama_lengkap,
        m.phone,
        COUNT(k.id) as total_komisi,
        SUM(CASE WHEN k.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN k.status = 'cair' THEN 1 ELSE 0 END) as cair,
        SUM(CASE WHEN k.status = 'batal' THEN 1 ELSE 0 END) as batal,
        COALESCE(SUM(k.komisi_final), 0) as total_nominal,
        MAX(k.created_at) as last_komisi
    FROM marketing_team m
    LEFT JOIN komisi_logs k ON m.id = k.marketing_id AND k.assigned_type = 'internal'
        AND DATE(k.created_at) BETWEEN ? AND ?
    WHERE m.developer_id = ?
    GROUP BY m.id
    ORDER BY total_nominal DESC
";
$marketing_stmt = $conn->prepare($marketing_report_sql);
$marketing_stmt->execute([$start_date, $end_date, $developer_id]);
$marketing_report = $marketing_stmt->fetchAll();

// ========== AMBIL DATA MARKETING UNTUK FILTER ==========
$marketings = $conn->prepare("
    SELECT id, nama_lengkap FROM marketing_team 
    WHERE developer_id = ? AND is_active = 1 
    ORDER BY nama_lengkap
");
$marketings->execute([$developer_id]);
$marketing_list = $marketings->fetchAll();

$page_title = 'Laporan Komisi Internal';
$page_subtitle = $developer_name;
$page_icon = 'fas fa-file-invoice';

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
    --finance: #2A9D8F;
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

/* ===== STATS CARD - HORIZONTAL SCROLL ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .stats-grid {
        display: flex;
        overflow-x: auto;
        gap: 12px;
        padding: 4px 0 16px 0;
        margin-bottom: 8px;
        -webkit-overflow-scrolling: touch;
    }
    
    .stats-grid .stat-card {
        flex: 0 0 140px;
    }
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 14px;
    border-left: 4px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
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
    margin-bottom: 2px;
}

.stat-value {
    font-size: 22px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

.stat-nominal {
    font-size: 12px;
    font-weight: 600;
    color: var(--success);
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
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.filter-btn.reset {
    background: var(--border);
    color: var(--text);
}

.filter-btn.reset:hover {
    background: var(--text-muted);
    color: white;
}

.filter-btn.success {
    background: linear-gradient(135deg, var(--success), #40BEB0);
}

/* ===== TABLE CARD ===== */
.table-card {
    background: white;
    border-radius: 24px;
    padding: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    margin-bottom: 24px;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-soft);
    flex-wrap: wrap;
    gap: 12px;
}

.table-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.table-header h3 i {
    color: var(--secondary);
}

.table-badge {
    background: var(--primary-soft);
    color: var(--primary);
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

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
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 12px;
    text-transform: uppercase;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

tr:hover td {
    background: var(--primary-soft);
}

/* ===== CHART CARD ===== */
.chart-card {
    background: white;
    border-radius: 24px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
}

.chart-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-title i {
    color: var(--secondary);
}

.chart-container {
    height: 300px;
    position: relative;
}

/* ===== REPORT TABS ===== */
.report-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
    overflow-x: auto;
    padding: 4px 0;
}

.report-tab {
    padding: 10px 20px;
    background: white;
    border: 2px solid var(--border);
    border-radius: 40px;
    font-weight: 600;
    font-size: 13px;
    color: var(--text);
    text-decoration: none;
    white-space: nowrap;
}

.report-tab.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* ===== STATUS BADGE ===== */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    text-align: center;
    color: white;
}

.status-badge.pending {
    background: var(--warning);
    color: #1A2A24;
}

.status-badge.cair,
.status-badge.active {
    background: var(--success);
}

.status-badge.batal,
.status-badge.inactive {
    background: var(--danger);
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

/* ===== TABLET & DESKTOP UPGRADE ===== */
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
}
</style>

<div class="main-content">
    
    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="welcome-text">
            <i class="<?= $page_icon ?>"></i>
            <h2>
                <?= $page_title ?>
                <span><?= htmlspecialchars($page_subtitle) ?> <?= $perusahaan ? " - $perusahaan" : '' ?></span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <!-- REPORT TABS -->
    <div class="report-tabs">
        <a href="?type=summary&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&marketing_id=<?= $marketing_id ?>" class="report-tab <?= $report_type == 'summary' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> Ringkasan
        </a>
        <a href="?type=marketing&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&marketing_id=<?= $marketing_id ?>" class="report-tab <?= $report_type == 'marketing' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Per Marketing
        </a>
        <a href="?type=monthly&year=<?= $year ?>" class="report-tab <?= $report_type == 'monthly' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> Bulanan
        </a>
    </div>
    
    <!-- FILTER SECTION -->
    <div class="filter-bar">
        <form method="GET" class="filter-form" id="filterForm">
            <input type="hidden" name="type" value="<?= $report_type ?>">
            
            <?php if ($report_type == 'summary'): ?>
            <select name="status" class="filter-select" style="max-width: 150px;">
                <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="cair" <?= $status == 'cair' ? 'selected' : '' ?>>Sudah Cair</option>
                <option value="batal" <?= $status == 'batal' ? 'selected' : '' ?>>Batal</option>
            </select>
            <?php endif; ?>
            
            <?php if ($report_type == 'marketing'): ?>
            <select name="marketing_id" class="filter-select">
                <option value="">Semua Marketing</option>
                <?php foreach ($marketing_list as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $marketing_id == $m['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <?php if ($report_type == 'summary' || $report_type == 'marketing'): ?>
            <input type="date" name="start_date" class="filter-input" value="<?= $start_date ?>" style="max-width: 150px;">
            <input type="date" name="end_date" class="filter-input" value="<?= $end_date ?>" style="max-width: 150px;">
            <?php endif; ?>
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Tampilkan</button>
                <button type="submit" name="action" value="export_excel" formmethod="POST" class="filter-btn success">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <a href="?" class="filter-btn reset"><i class="fas fa-redo"></i> Reset</a>
            </div>
        </form>
    </div>
    
    <!-- SUMMARY STATS (always show) -->
    <div class="stats-grid">
        <div class="stat-card" style="border-left-color: var(--warning);">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= number_format($summary['pending_count'] ?? 0) ?></div>
            <div class="stat-nominal">Rp <?= number_format($summary['pending_nominal'] ?? 0, 0, ',', '.') ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--success);">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Sudah Cair</div>
            <div class="stat-value"><?= number_format($summary['cair_count'] ?? 0) ?></div>
            <div class="stat-nominal">Rp <?= number_format($summary['cair_nominal'] ?? 0, 0, ',', '.') ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--primary);">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-label">Total Komisi</div>
            <div class="stat-value">Rp <?= number_format($summary['total_nominal'] ?? 0, 0, ',', '.') ?></div>
            <div class="stat-nominal"><?= $summary['total_transaksi'] ?? 0 ?> transaksi</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--danger);">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-label">Batal</div>
            <div class="stat-value"><?= number_format($summary['batal_count'] ?? 0) ?></div>
            <div class="stat-nominal">-</div>
        </div>
    </div>
    
    <div class="stats-grid" style="margin-top: -20px;">
        <div class="stat-card" style="border-left-color: var(--info);">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Marketing</div>
            <div class="stat-value"><?= number_format($summary['total_marketing'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--primary); grid-column: span 3;">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-label">Periode</div>
            <div class="stat-value"><?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></div>
        </div>
    </div>
    
    <!-- MONTHLY CHART (for summary & monthly) -->
    <?php if ($report_type == 'summary' || $report_type == 'monthly'): ?>
    <div class="chart-card">
        <div class="chart-title">
            <i class="fas fa-chart-bar"></i> Tren Komisi 12 Bulan Terakhir
        </div>
        <div class="chart-container">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- MARKETING REPORT TABLE -->
    <?php if ($report_type == 'marketing'): ?>
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-users"></i> Rekapitulasi per Marketing</h3>
            <div class="table-badge">Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></div>
        </div>
        
        <?php if (empty($marketing_report)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Tidak ada data komisi untuk periode ini</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Marketing</th>
                        <th>Kontak</th>
                        <th>Total</th>
                        <th>Pending</th>
                        <th>Cair</th>
                        <th>Batal</th>
                        <th>Total Nominal</th>
                        <th>Komisi Terakhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marketing_report as $m): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($m['nama_lengkap']) ?></strong></td>
                        <td><?= $m['phone'] ?></td>
                        <td><?= $m['total_komisi'] ?></td>
                        <td><span class="status-badge pending"><?= $m['pending'] ?></span></td>
                        <td><span class="status-badge cair"><?= $m['cair'] ?></span></td>
                        <td><span class="status-badge batal"><?= $m['batal'] ?></span></td>
                        <td><strong style="color: var(--success);">Rp <?= number_format($m['total_nominal'], 0, ',', '.') ?></strong></td>
                        <td><?= $m['last_komisi'] ? date('d/m/Y', strtotime($m['last_komisi'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Finance Developer Laporan v2.0</p>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Data untuk chart
    const monthlyLabels = <?= json_encode(array_column($monthly_data, 'bulan')) ?>;
    const monthlyNominal = <?= json_encode(array_column($monthly_data, 'nominal_cair')) ?>;
    
    <?php if (!empty($monthly_data)): ?>
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthlyLabels,
            datasets: [{
                label: 'Nominal Cair (Rp)',
                data: monthlyNominal,
                backgroundColor: '#2A9D8F',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
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