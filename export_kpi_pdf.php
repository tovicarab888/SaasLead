<?php
/**
 * EXPORT_KPI_PDF.PHP - LEADENGINE PREMIUM
 * Version: 5.0.0 - TAMBAH STATISTIK UNIT TERJUAL PER CLUSTER
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/export_kpi.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

$log_dir = dirname(__DIR__) . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

if (!checkAuth() && !isMarketing()) {
    http_response_code(401);
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    } else {
        die('Unauthorized');
    }
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$current_role = getCurrentRole();
$current_user_id = $_SESSION['user_id'] ?? 0;
$marketing_id = isMarketing() ? $_SESSION['marketing_id'] : 0;
$developer_id = 0;

if (isDeveloper()) {
    $developer_id = $current_user_id;
} elseif (isMarketing()) {
    $developer_id = $_SESSION['marketing_developer_id'] ?? 0;
} elseif (isAdmin() || isManager()) {
    $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
    if ($developer_id <= 0) {
        $stmt = $conn->query("SELECT id FROM users WHERE role = 'developer' AND is_active = 1 ORDER BY id LIMIT 1");
        $dev = $stmt->fetch();
        $developer_id = $dev['id'] ?? 0;
    }
}

$format = $_GET['format'] ?? 'pdf';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$target_marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;

if ($developer_id <= 0) {
    die("Developer ID tidak valid");
}

$stmt = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
$stmt->execute([$developer_id]);
$developer_name = $stmt->fetchColumn() ?: 'Developer';

require_once __DIR__ . '/config.php';

// Ambil statistik unit per cluster
$cluster_stats = [];
$stmt = $conn->prepare("
    SELECT 
        c.nama_cluster,
        COUNT(DISTINCT u.id) as total_units,
        COUNT(DISTINCT CASE WHEN u.status = 'AVAILABLE' THEN u.id END) as available,
        COUNT(DISTINCT CASE WHEN u.status = 'BOOKED' THEN u.id END) as booked,
        COUNT(DISTINCT CASE WHEN u.status = 'SOLD' THEN u.id END) as sold
    FROM clusters c
    LEFT JOIN units u ON c.id = u.cluster_id
    WHERE c.developer_id = ?
    GROUP BY c.id
    ORDER BY c.nama_cluster
");
$stmt->execute([$developer_id]);
$cluster_stats = $stmt->fetchAll();

// Total statistik unit
$total_units = 0;
$total_available = 0;
$total_booked = 0;
$total_sold = 0;

foreach ($cluster_stats as $cs) {
    $total_units += $cs['total_units'];
    $total_available += $cs['available'];
    $total_booked += $cs['booked'];
    $total_sold += $cs['sold'];
}

if ($target_marketing_id > 0) {
    $kpi = getMarketingKPI($conn, $target_marketing_id, $start_date, $end_date);
    $stmt = $conn->prepare("SELECT nama_lengkap, phone, email, username FROM marketing_team WHERE id = ?");
    $stmt->execute([$target_marketing_id]);
    $profile = $stmt->fetch();
    $kpi['nama_lengkap'] = $profile['nama_lengkap'] ?? 'Marketing';
    $kpi['phone'] = $profile['phone'] ?? '';
    $kpi['email'] = $profile['email'] ?? '';
    $kpi['username'] = $profile['username'] ?? '';
    $data = ['marketing' => [$kpi]];
    $total = [
        'total_leads' => $kpi['total_leads_diterima'],
        'total_deal' => $kpi['total_deal'],
        'total_follow_up' => $kpi['total_follow_up'],
        'avg_conversion' => $kpi['conversion_rate']
    ];
    $title = "KPI Marketing: " . $kpi['nama_lengkap'];
} else {
    $result = getAllMarketingKPI($conn, $developer_id, $start_date, $end_date);
    $data = $result['marketing'];
    $total = $result['total'];
    $title = "KPI All Marketing - " . $developer_name;
}

switch ($format) {
    case 'pdf':
        exportKPIPDF($data, $total, $developer_name, $start_date, $end_date, $title, $current_role, $target_marketing_id, $conn, $cluster_stats, $total_units, $total_available, $total_booked, $total_sold);
        break;
    case 'excel':
        exportKPIExcel($data, $total, $developer_name, $start_date, $end_date, $title, $current_role, $cluster_stats);
        break;
    case 'csv':
        exportKPICSV($data, $total, $developer_name, $start_date, $end_date, $title, $cluster_stats);
        break;
    case 'json':
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data,
            'total' => $total,
            'developer' => $developer_name,
            'periode' => "$start_date s/d $end_date",
            'unit_stats' => [
                'clusters' => $cluster_stats,
                'total_units' => $total_units,
                'available' => $total_available,
                'booked' => $total_booked,
                'sold' => $total_sold
            ]
        ], JSON_PRETTY_PRINT);
        exit();
    default:
        exportKPIPDF($data, $total, $developer_name, $start_date, $end_date, $title, $current_role, $target_marketing_id, $conn, $cluster_stats, $total_units, $total_available, $total_booked, $total_sold);
}

function exportKPIPDF($data, $total, $developer_name, $start_date, $end_date, $title, $current_role, $target_marketing_id, $conn, $cluster_stats, $total_units, $total_available, $total_booked, $total_sold) {
    $filename = 'kpi_marketing_' . $developer_name . '_' . date('Y-m-d_His') . '.html';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $filter_info = "$start_date s/d $end_date";
    $role_display = ucfirst($current_role);
    $user_name = $_SESSION['nama_lengkap'] ?? $_SESSION['marketing_name'] ?? 'User';
    $current_date = date('d/m/Y H:i:s') . ' WIB';
    
    $total_negatif = 0;
    foreach ($data as $m) {
        $total_negatif += $m['total_negatif'] ?? 0;
    }
    
    echo '<!DOCTYPE html>';
    echo '<html lang="id">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">';
    echo '<style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Inter", "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f0f4f8 100%);
            padding: 30px 20px;
            color: #1A2A24;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 32px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.15);
            overflow: hidden;
            border: 1px solid rgba(224,218,211,0.3);
        }
        
        /* ===== HEADER ===== */
        .header {
            background: linear-gradient(135deg, #1B4A3C 0%, #2A5F4E 100%);
            padding: 30px 35px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .header::before {
            content: "📊";
            position: absolute;
            right: 20px;
            bottom: -20px;
            font-size: 120px;
            opacity: 0.1;
            transform: rotate(10deg);
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo-icon {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #E3B584;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .logo-text h1 {
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 5px 0;
            letter-spacing: -0.5px;
        }
        .logo-text p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }
        .date-badge {
            background: rgba(255,255,255,0.15);
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .header-title {
            position: relative;
            z-index: 2;
            margin-top: 15px;
        }
        .header-title h2 {
            font-size: 36px;
            font-weight: 800;
            margin: 0 0 10px 0;
            letter-spacing: -1px;
            line-height: 1.2;
        }
        .header-title .subtitle {
            font-size: 16px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .header-title .subtitle span {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            padding: 8px 16px;
            border-radius: 40px;
        }
        
        /* ===== SUMMARY CARDS ===== */
        .summary-section {
            padding: 30px 35px;
            background: white;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .summary-card {
            background: linear-gradient(135deg, #f8fafc, #f0f4f8);
            border-radius: 20px;
            padding: 20px;
            border-left: 6px solid #D64F3C;
        }
        .summary-card .label {
            font-size: 14px;
            color: #7A8A84;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .summary-card .value {
            font-size: 36px;
            font-weight: 800;
            color: #1B4A3C;
            line-height: 1.2;
        }
        
        /* ===== UNIT STATS SECTION ===== */
        .unit-section {
            padding: 0 35px 30px 35px;
        }
        .unit-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 15px;
        }
        .unit-card {
            background: #E7F3EF;
            border-radius: 16px;
            padding: 15px;
            border-left: 4px solid #2A9D8F;
        }
        .unit-card .value {
            font-size: 28px;
            font-weight: 800;
            color: #1B4A3C;
        }
        .unit-card .label {
            font-size: 12px;
            color: #4A5A54;
        }
        .cluster-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .cluster-table th {
            background: #1B4A3C;
            color: white;
            padding: 10px;
            text-align: left;
        }
        .cluster-table td {
            padding: 8px;
            border-bottom: 1px solid #E0DAD3;
        }
        .progress-bar {
            height: 8px;
            background: #E0DAD3;
            border-radius: 4px;
            overflow: hidden;
            width: 100px;
        }
        .progress-fill {
            height: 100%;
            background: #2A9D8F;
            border-radius: 4px;
        }
        
        /* ===== INFO NEGATIF ===== */
        .negatif-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            border-radius: 12px;
            margin: 20px 35px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .negatif-info i { font-size: 28px; color: #ffc107; }
        .negatif-info p { margin: 0; font-size: 13px; color: #856404; }
        
        /* ===== DESKTOP TABLE VIEW ===== */
        .desktop-table {
            display: block;
            padding: 0 35px 30px 35px;
        }
        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 25px 0 20px 0;
            flex-wrap: wrap;
        }
        .section-title i {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #1B4A3C, #2A5F4E);
            color: white;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .section-title h3 {
            font-size: 22px;
            font-weight: 700;
            color: #1B4A3C;
            margin: 0;
        }
        .section-title .badge {
            background: #E7F3EF;
            color: #1B4A3C;
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border-radius: 20px;
            border: 1px solid #E0DAD3;
            margin-bottom: 10px;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-wrapper::-webkit-scrollbar {
            height: 10px;
            background: #f0f0f0;
            border-radius: 10px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #D64F3C, #FF8A5C);
            border-radius: 10px;
        }
        
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
            background: white;
        }
        
        .kpi-table th {
            background: linear-gradient(135deg, #1B4A3C, #2A5F4E);
            color: white;
            padding: 16px 12px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            text-align: left;
            white-space: nowrap;
        }
        
        .kpi-table th i {
            margin-right: 6px;
            font-size: 14px;
        }
        
        .kpi-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #E0DAD3;
            background: white;
            font-size: 14px;
            white-space: nowrap;
        }
        
        .kpi-table tbody tr:hover td {
            background: #f8fafc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 11px;
            font-weight: 600;
            color: white;
        }
        .status-badge.active { background: #2A9D8F; }
        .status-badge.inactive { background: #D64F3C; }
        
        .score-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            min-width: 35px;
            text-align: center;
            color: white;
        }
        .score-hot { background: #D64F3C; }
        .score-warm { background: #E9C46A; color: #1A2A24; }
        .score-cold { background: #4A90E2; }
        
        .highlight-number { font-weight: 700; font-size: 15px; }
        .text-success { color: #2A9D8F; }
        .text-danger { color: #D64F3C; }
        
        .total-row {
            background: linear-gradient(135deg, #E7F3EF, #d4e8e0) !important;
            font-weight: 700;
        }
        .total-row td {
            background: transparent !important;
            border-top: 2px solid #1B4A3C;
        }
        
        /* ===== FOOTER ===== */
        .footer {
            background: linear-gradient(135deg, #1B4A3C, #2A5F4E);
            padding: 20px 35px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .footer-left { display: flex; align-items: center; gap: 20px; }
        .footer-left i { color: #E3B584; font-size: 24px; }
        .footer-right { text-align: right; }
        .footer small { opacity: 0.8; display: block; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .unit-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .header { padding: 20px; }
            .header-title h2 { font-size: 24px; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .unit-grid { grid-template-columns: repeat(2, 1fr); }
            .negatif-info { margin: 20px; flex-direction: column; text-align: center; }
            .footer { flex-direction: column; text-align: center; }
        }
        
        @media (max-width: 480px) {
            .summary-grid { grid-template-columns: 1fr; }
            .unit-grid { grid-template-columns: 1fr; }
            .header-top { flex-direction: column; align-items: flex-start; }
            .date-badge { width: 100%; text-align: center; }
        }
        
        @media print {
            body { background: white; padding: 0; }
            .container { box-shadow: none; }
            .table-wrapper { overflow: visible; }
            .kpi-table { min-width: 100%; }
        }
    </style>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="container">';
    
    // HEADER
    echo '<div class="header">';
    echo '<div class="header-top">';
    echo '<div class="logo-area">';
    echo '<div class="logo-icon"><i class="fas fa-chart-bar"></i></div>';
    echo '<div class="logo-text">';
    echo '<h1>LeadEngine</h1>';
    echo '<p>Premium KPI Marketing Report</p>';
    echo '</div>';
    echo '</div>';
    echo '<div class="date-badge">';
    echo '<i class="fas fa-calendar-alt"></i> ' . $current_date;
    echo '</div>';
    echo '</div>';
    
    echo '<div class="header-title">';
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
    echo '<div class="subtitle">';
    echo '<span><i class="fas fa-calendar-range"></i> ' . $start_date . ' s/d ' . $end_date . '</span>';
    echo '<span><i class="fas fa-building"></i> Developer: ' . htmlspecialchars($developer_name) . '</span>';
    echo '<span><i class="fas fa-user"></i> Eksportir: ' . htmlspecialchars($user_name) . '</span>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // SUMMARY CARDS
    echo '<div class="summary-section">';
    echo '<div class="summary-grid">';
    echo '<div class="summary-card"><div class="label">TOTAL MARKETING</div><div class="value">' . count($data) . '</div></div>';
    echo '<div class="summary-card"><div class="label">LEAD MASUK</div><div class="value">' . $total['total_leads'] . '</div></div>';
    echo '<div class="summary-card"><div class="label">TOTAL FOLLOW UP</div><div class="value">' . $total['total_follow_up'] . '</div></div>';
    echo '<div class="summary-card"><div class="label">TOTAL DEAL</div><div class="value">' . $total['total_deal'] . '</div></div>';
    echo '</div>';
    echo '</div>';
    
    // UNIT STATISTICS (BARU)
    echo '<div class="unit-section">';
    echo '<div class="section-title">';
    echo '<i class="fas fa-home"></i>';
    echo '<h3>Statistik Unit ' . htmlspecialchars($developer_name) . '</h3>';
    echo '<span class="badge">' . $total_units . ' Total Unit</span>';
    echo '</div>';
    
    echo '<div class="unit-grid">';
    echo '<div class="unit-card"><div class="value">' . $total_units . '</div><div class="label">Total Unit</div></div>';
    echo '<div class="unit-card"><div class="value" style="color: #2A9D8F;">' . $total_available . '</div><div class="label">Available</div></div>';
    echo '<div class="unit-card"><div class="value" style="color: #E9C46A;">' . $total_booked . '</div><div class="label">Booked</div></div>';
    echo '<div class="unit-card"><div class="value" style="color: #D64F3C;">' . $total_sold . '</div><div class="label">Sold</div></div>';
    echo '</div>';
    
    if (!empty($cluster_stats)) {
        echo '<table class="cluster-table">';
        echo '<tr><th>Cluster</th><th>Total Unit</th><th>Available</th><th>Booked</th><th>Sold</th><th>Progress</th></tr>';
        foreach ($cluster_stats as $cs) {
            $progress = $cs['total_units'] > 0 ? round(($cs['sold'] / $cs['total_units']) * 100) : 0;
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($cs['nama_cluster']) . '</strong></td>';
            echo '<td>' . $cs['total_units'] . '</td>';
            echo '<td>' . $cs['available'] . '</td>';
            echo '<td>' . $cs['booked'] . '</td>';
            echo '<td>' . $cs['sold'] . '</td>';
            echo '<td><div class="progress-bar"><div class="progress-fill" style="width: ' . $progress . '%;"></div></div> ' . $progress . '%</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // INFO NEGATIF
    if ($total_negatif > 0) {
        echo '<div class="negatif-info">';
        echo '<i class="fas fa-exclamation-triangle"></i>';
        echo '<div>';
        echo '<strong>Kolom NEGATIF: ' . $total_negatif . ' lead</strong> - Tolak Slik + Tidak Minat + Batal';
        echo '</div>';
        echo '</div>';
    }
    
    // DESKTOP TABLE VIEW
    echo '<div class="desktop-table">';
    echo '<div class="section-title">';
    echo '<i class="fas fa-chart-bar"></i>';
    echo '<h3>Detail KPI Marketing</h3>';
    echo '<span class="badge">' . count($data) . ' Marketing</span>';
    echo '</div>';
    
    if (empty($data)) {
        echo '<div style="text-align: center; padding: 60px; background: #f9f9f9; border-radius: 20px;">';
        echo '<i class="fas fa-inbox" style="font-size: 48px; color: #ccc;"></i>';
        echo '<h3 style="color: #666;">Tidak Ada Data</h3>';
        echo '</div>';
    } else {
        echo '<div class="table-wrapper">';
        echo '<table class="kpi-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Marketing</th>';
        echo '<th>Kontak</th>';
        echo '<th>Status</th>';
        echo '<th>Lead<br>Historis</th>';
        echo '<th>Lead<br>Baru</th>';
        echo '<th>Follow<br>Up</th>';
        echo '<th>Deal</th>';
        echo '<th>Negatif</th>';
        echo '<th>Conv<br>Rate</th>';
        echo '<th>Hot</th>';
        echo '<th>Warm</th>';
        echo '<th>Cold</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($data as $m) {
            $status_class = ($m['is_active'] ?? 1) ? 'active' : 'inactive';
            $status_text = ($m['is_active'] ?? 1) ? 'Aktif' : 'Nonaktif';
            $hot = $m['score_distribution']['hot'] ?? 0;
            $warm = $m['score_distribution']['warm'] ?? 0;
            $cold = $m['score_distribution']['cold'] ?? 0;
            
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($m['nama_lengkap'] ?? '-') . '</strong><br><small>@' . htmlspecialchars($m['username'] ?? '') . '</small></td>';
            echo '<td>' . htmlspecialchars($m['phone'] ?? '-') . '<br><small>ID: ' . $m['marketing_id'] . '</small></td>';
            echo '<td><span class="status-badge ' . $status_class . '">' . $status_text . '</span></td>';
            echo '<td class="highlight-number">' . ($m['total_leads_assigned'] ?? 0) . '</td>';
            echo '<td class="highlight-number"><strong>' . ($m['total_leads_diterima'] ?? 0) . '</strong></td>';
            echo '<td class="highlight-number">' . ($m['total_follow_up'] ?? 0) . '</td>';
            echo '<td class="highlight-number text-success"><strong>' . ($m['total_deal'] ?? 0) . '</strong></td>';
            echo '<td class="highlight-number text-danger"><strong>' . ($m['total_negatif'] ?? 0) . '</strong></td>';
            echo '<td class="highlight-number"><strong>' . ($m['conversion_rate'] ?? 0) . '%</strong></td>';
            echo '<td><span class="score-badge score-hot">' . $hot . '</span></td>';
            echo '<td><span class="score-badge score-warm">' . $warm . '</span></td>';
            echo '<td><span class="score-badge score-cold">' . $cold . '</span></td>';
            echo '</tr>';
        }
        
        echo '<tr class="total-row">';
        echo '<td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>';
        echo '<td>-</td>';
        echo '<td><strong>' . $total['total_leads'] . '</strong></td>';
        echo '<td><strong>' . $total['total_follow_up'] . '</strong></td>';
        echo '<td><strong class="text-success">' . $total['total_deal'] . '</strong></td>';
        echo '<td><strong class="text-danger">' . $total_negatif . '</strong></td>';
        echo '<td><strong>' . $total['avg_conversion'] . '%</strong></td>';
        echo '<td colspan="3"></td>';
        echo '</tr>';
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '<p style="text-align: center; color: #7A8A84; font-size: 12px;">';
        echo '<i class="fas fa-arrows-alt-h"></i> Geser tabel ke kanan';
        echo '</p>';
    }
    echo '</div>';
    
    // FOOTER
    echo '<div class="footer">';
    echo '<div class="footer-left">';
    echo '<i class="fas fa-chart-pie"></i>';
    echo '<div>';
    echo '<strong>Rumus:</strong> Negatif = Tolak Slik + Tidak Minat + Batal';
    echo '</div>';
    echo '</div>';
    echo '<div class="footer-right">';
    echo '<strong>© ' . date('Y') . ' LeadEngine</strong>';
    echo '<small>Premium KPI Report + Unit Statistics</small>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    echo '</body>';
    echo '</html>';
    exit();
}

function exportKPIExcel($data, $total, $developer_name, $start_date, $end_date, $title, $current_role, $cluster_stats) {
    $filename = 'kpi_marketing_' . $developer_name . '_' . date('Y-m-d_His') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $user_name = $_SESSION['nama_lengkap'] ?? $_SESSION['marketing_name'] ?? 'User';
    $current_date = date('d/m/Y H:i:s') . ' WIB';
    
    $total_negatif = 0;
    foreach ($data as $m) {
        $total_negatif += $m['total_negatif'] ?? 0;
    }
    
    echo '<html>';
    echo '<head><meta charset="UTF-8">';
    echo '<style>
        body { font-family: Arial, sans-serif; }
        h1 { color: #1B4A3C; }
        .header { background: #1B4A3C; color: white; padding: 20px; }
        .section { background: #E7F3EF; padding: 15px; margin: 20px 0; border-left: 5px solid #D64F3C; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
        th { background: #1B4A3C; color: white; padding: 10px; white-space: nowrap; }
        td { padding: 8px; border: 1px solid #ddd; white-space: nowrap; }
        .total-row { background: #E7F3EF; font-weight: 700; }
    </style>';
    echo '</head><body>';
    
    echo '<div class="header">';
    echo '<h1 style="color: white;">' . htmlspecialchars($title) . '</h1>';
    echo '<p>Periode: ' . $start_date . ' s/d ' . $end_date . ' | Developer: ' . htmlspecialchars($developer_name) . '</p>';
    echo '<p>Diekspor oleh: ' . htmlspecialchars($user_name) . ' pada ' . $current_date . '</p>';
    echo '</div>';
    
    // Unit Statistics
    if (!empty($cluster_stats)) {
        echo '<div class="section">';
        echo '<h3 style="color: #1B4A3C;">📊 Statistik Unit</h3>';
        echo '<table>';
        echo '<tr><th>Cluster</th><th>Total Unit</th><th>Available</th><th>Booked</th><th>Sold</th><th>Progress</th></tr>';
        
        $total_units = 0;
        $total_avail = 0;
        $total_book = 0;
        $total_sold = 0;
        
        foreach ($cluster_stats as $cs) {
            $progress = $cs['total_units'] > 0 ? round(($cs['sold'] / $cs['total_units']) * 100) : 0;
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($cs['nama_cluster']) . '</strong></td>';
            echo '<td>' . $cs['total_units'] . '</td>';
            echo '<td>' . $cs['available'] . '</td>';
            echo '<td>' . $cs['booked'] . '</td>';
            echo '<td>' . $cs['sold'] . '</td>';
            echo '<td>' . $progress . '%</td>';
            echo '</tr>';
            
            $total_units += $cs['total_units'];
            $total_avail += $cs['available'];
            $total_book += $cs['booked'];
            $total_sold += $cs['sold'];
        }
        
        echo '<tr class="total-row">';
        echo '<td><strong>TOTAL</strong></td>';
        echo '<td><strong>' . $total_units . '</strong></td>';
        echo '<td><strong>' . $total_avail . '</strong></td>';
        echo '<td><strong>' . $total_book . '</strong></td>';
        echo '<td><strong>' . $total_sold . '</strong></td>';
        echo '<td><strong>' . ($total_units > 0 ? round(($total_sold / $total_units) * 100) : 0) . '%</strong></td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</div>';
    }
    
    // KPI Table
    echo '<div class="section">';
    echo '<h3 style="color: #1B4A3C;">📈 KPI Marketing</h3>';
    echo '<table>';
    echo '<tr>';
    echo '<th>Marketing</th><th>Kontak</th><th>Status</th><th>Lead Historis</th><th>Lead Baru</th>';
    echo '<th>Follow Up</th><th>Deal</th><th>Negatif</th><th>Conv Rate</th><th>Hot</th><th>Warm</th><th>Cold</th>';
    echo '</tr>';
    
    foreach ($data as $m) {
        $status = ($m['is_active'] ?? 1) ? 'Aktif' : 'Nonaktif';
        $hot = $m['score_distribution']['hot'] ?? 0;
        $warm = $m['score_distribution']['warm'] ?? 0;
        $cold = $m['score_distribution']['cold'] ?? 0;
        
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($m['nama_lengkap'] ?? '-') . '</strong></td>';
        echo '<td>' . htmlspecialchars($m['phone'] ?? '-') . '</td>';
        echo '<td>' . $status . '</td>';
        echo '<td>' . ($m['total_leads_assigned'] ?? 0) . '</td>';
        echo '<td><strong>' . ($m['total_leads_diterima'] ?? 0) . '</strong></td>';
        echo '<td>' . ($m['total_follow_up'] ?? 0) . '</td>';
        echo '<td style="color: #2A9D8F;"><strong>' . ($m['total_deal'] ?? 0) . '</strong></td>';
        echo '<td style="color: #D64F3C;"><strong>' . ($m['total_negatif'] ?? 0) . '</strong></td>';
        echo '<td><strong>' . ($m['conversion_rate'] ?? 0) . '%</strong></td>';
        echo '<td>' . $hot . '</td><td>' . $warm . '</td><td>' . $cold . '</td>';
        echo '</tr>';
    }
    
    echo '<tr class="total-row">';
    echo '<td colspan="4"><strong>TOTAL</strong></td>';
    echo '<td><strong>' . $total['total_leads'] . '</strong></td>';
    echo '<td><strong>' . $total['total_follow_up'] . '</strong></td>';
    echo '<td><strong>' . $total['total_deal'] . '</strong></td>';
    echo '<td><strong>' . $total_negatif . '</strong></td>';
    echo '<td><strong>' . $total['avg_conversion'] . '%</strong></td>';
    echo '<td colspan="3"></td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</div>';
    
    echo '</body></html>';
    exit();
}

function exportKPICSV($data, $total, $developer_name, $start_date, $end_date, $title, $cluster_stats) {
    $filename = 'kpi_marketing_' . $developer_name . '_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header Unit Statistics
    fputcsv($output, ['STATISTIK UNIT', '', '', '', '', '']);
    fputcsv($output, ['Cluster', 'Total Unit', 'Available', 'Booked', 'Sold', 'Progress (%)']);
    
    $total_units = 0;
    $total_avail = 0;
    $total_book = 0;
    $total_sold = 0;
    
    foreach ($cluster_stats as $cs) {
        $progress = $cs['total_units'] > 0 ? round(($cs['sold'] / $cs['total_units']) * 100) : 0;
        fputcsv($output, [
            $cs['nama_cluster'],
            $cs['total_units'],
            $cs['available'],
            $cs['booked'],
            $cs['sold'],
            $progress . '%'
        ]);
        
        $total_units += $cs['total_units'];
        $total_avail += $cs['available'];
        $total_book += $cs['booked'];
        $total_sold += $cs['sold'];
    }
    
    fputcsv($output, [
        'TOTAL',
        $total_units,
        $total_avail,
        $total_book,
        $total_sold,
        ($total_units > 0 ? round(($total_sold / $total_units) * 100) : 0) . '%'
    ]);
    
    fputcsv($output, []);
    fputcsv($output, ['KPI MARKETING', '', '', '', '', '', '', '', '', '', '', '']);
    fputcsv($output, [
        'Marketing', 'Username', 'WhatsApp', 'Status',
        'Lead Historis', 'Lead Baru', 'Follow Up', 'Deal', 'Negatif',
        'Conv Rate (%)', 'Hot', 'Warm', 'Cold'
    ]);
    
    foreach ($data as $m) {
        $status = ($m['is_active'] ?? 1) ? 'Aktif' : 'Nonaktif';
        $hot = $m['score_distribution']['hot'] ?? 0;
        $warm = $m['score_distribution']['warm'] ?? 0;
        $cold = $m['score_distribution']['cold'] ?? 0;
        
        fputcsv($output, [
            $m['nama_lengkap'] ?? '-',
            $m['username'] ?? '',
            $m['phone'] ?? '-',
            $status,
            $m['total_leads_assigned'] ?? 0,
            $m['total_leads_diterima'] ?? 0,
            $m['total_follow_up'] ?? 0,
            $m['total_deal'] ?? 0,
            $m['total_negatif'] ?? 0,
            $m['conversion_rate'] ?? 0,
            $hot,
            $warm,
            $cold
        ]);
    }
    
    $total_negatif = 0;
    foreach ($data as $m) {
        $total_negatif += $m['total_negatif'] ?? 0;
    }
    
    fputcsv($output, []);
    fputcsv($output, [
        'TOTAL', '', '', '', '',
        $total['total_leads'], $total['total_follow_up'],
        $total['total_deal'], $total_negatif, $total['avg_conversion'] . '%',
        '', '', ''
    ]);
    
    fclose($output);
    exit();
}
?>