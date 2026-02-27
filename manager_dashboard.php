<?php
/**
 * MANAGER_DASHBOARD.PHP - TAUFIKMARIE.COM
 * Version: 41.0.0 - SUPER MANAGER DASHBOARD (MOBILE HORIZONTAL FIX)
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

// ========== AMBIL SEMUA DEVELOPER AKTIF ==========
$developers = $conn->query("
    SELECT id, username, nama_lengkap, location_access 
    FROM users 
    WHERE role = 'developer' AND is_active = 1 
    ORDER BY nama_lengkap ASC
")->fetchAll();

// ========== AMBIL SEMUA MARKETING AKTIF (dengan developer info) ==========
$all_marketing = $conn->query("
    SELECT m.*, u.nama_lengkap as developer_name, u.id as developer_id
    FROM marketing_team m
    LEFT JOIN users u ON m.developer_id = u.id
    WHERE m.is_active = 1
    ORDER BY u.nama_lengkap, m.nama_lengkap
")->fetchAll();

// ========== STATISTIK ==========
$stats = getSystemStats($conn, $_SESSION['user_id'] ?? null, $_SESSION['role'] ?? null);

// ========== LOCATIONS FOR FILTER ==========
$locations = $conn->query("SELECT location_key, display_name, icon FROM locations ORDER BY sort_order")->fetchAll();

// ========== STATUS LIST ==========
$status_list = [
    'Baru', 'Follow Up', 'Survey', 'Booking', 
    'Tolak Slik', 'Tidak Minat', 'Batal',
    'Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'
];

// ========== GET FILTERS ==========
$search = $_GET['search'] ?? '';
$location_filter = $_GET['location'] ?? '';
$status_filter = $_GET['status'] ?? '';
$developer_filter = isset($_GET['developer']) ? (int)$_GET['developer'] : 0;
$marketing_filter = isset($_GET['marketing']) ? (int)$_GET['marketing'] : 0;
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// ========== BUILD QUERY ==========
$sql = "SELECT l.*, loc.display_name as location_display, loc.icon,
               u.nama_lengkap as developer_name,
               m.nama_lengkap as marketing_name
        FROM leads l 
        LEFT JOIN locations loc ON l.location_key = loc.location_key 
        LEFT JOIN users u ON l.ditugaskan_ke = u.id
        LEFT JOIN marketing_team m ON l.assigned_marketing_team_id = m.id
        WHERE (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";
$params = [];

if ($search) {
    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? OR l.city LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}

if ($location_filter) {
    $sql .= " AND l.location_key = ?";
    $params[] = $location_filter;
}

if ($status_filter) {
    $sql .= " AND l.status = ?";
    $params[] = $status_filter;
}

if ($developer_filter > 0) {
    $sql .= " AND l.ditugaskan_ke = ?";
    $params[] = $developer_filter;
}

if ($marketing_filter > 0) {
    $sql .= " AND l.assigned_marketing_team_id = ?";
    $params[] = $marketing_filter;
}

// Count total
$count_sql = "SELECT COUNT(*) FROM leads WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
$count_params = [];

if ($search) {
    $count_sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ? OR city LIKE ?)";
    $count_params = array_merge($count_params, [$s, $s, $s, $s, $s]);
}

if ($location_filter) {
    $count_sql .= " AND location_key = ?";
    $count_params[] = $location_filter;
}

if ($status_filter) {
    $count_sql .= " AND status = ?";
    $count_params[] = $status_filter;
}

if ($developer_filter > 0) {
    $count_sql .= " AND ditugaskan_ke = ?";
    $count_params[] = $developer_filter;
}

if ($marketing_filter > 0) {
    $count_sql .= " AND assigned_marketing_team_id = ?";
    $count_params[] = $marketing_filter;
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get data
$sql .= " ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// ========== GET CHART DATA ==========
$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime($date));
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE DATE(created_at) = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
    $stmt->execute([$date]);
    $chart_data[] = (int)$stmt->fetchColumn();
}

// ========== GET NOTIFICATIONS ==========
$notif_stmt = $conn->query("
    SELECT * FROM notifications 
    WHERE is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 10
");
$notifications = $notif_stmt->fetchAll();

// ========== GET LOCATION STATS ==========
$loc_stats = $conn->query("
    SELECT l.location_key, loc.display_name, loc.icon, COUNT(*) as count 
    FROM leads l 
    LEFT JOIN locations loc ON l.location_key = loc.location_key 
    WHERE (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
    GROUP BY l.location_key 
    ORDER BY count DESC
")->fetchAll();

// ========== GET STATUS COUNTS ==========
$status_counts = $conn->query("SELECT status, COUNT(*) as count FROM leads WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') GROUP BY status")->fetchAll();
$status_map = [];
foreach ($status_counts as $s) {
    $status_map[$s['status']] = $s['count'];
}

// ========== DATA UNTUK KPI MARKETING ==========
$deal_statuses = ['Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'];
$deal_placeholders = implode(',', array_fill(0, count($deal_statuses), '?'));

// Total marketing aktif
$total_marketing_aktif = count($all_marketing);

// Total leads marketing
$stmt = $conn->prepare("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN status IN ($deal_placeholders) THEN 1 ELSE 0 END) as deal
    FROM leads 
    WHERE assigned_marketing_team_id IS NOT NULL
    AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
");
$stmt->execute($deal_statuses);
$kpi_total = $stmt->fetch();
$total_leads_marketing = $kpi_total['total'] ?? 0;
$total_deal_marketing = $kpi_total['deal'] ?? 0;
$conversion_rate_marketing = $total_leads_marketing > 0 ? round(($total_deal_marketing / $total_leads_marketing) * 100, 2) : 0;

// ========== TOP PERFORMER MARKETING (GLOBAL) ==========
$top_global_sql = "
    SELECT m.id, m.nama_lengkap, m.phone, u.nama_lengkap as developer_name,
           COUNT(l.id) as total_leads,
           SUM(CASE WHEN l.status IN ($deal_placeholders) THEN 1 ELSE 0 END) as total_deal
    FROM marketing_team m
    LEFT JOIN users u ON m.developer_id = u.id
    LEFT JOIN leads l ON m.id = l.assigned_marketing_team_id 
        AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
        AND MONTH(l.created_at) = MONTH(CURDATE())
        AND YEAR(l.created_at) = YEAR(CURDATE())
    WHERE m.is_active = 1
    GROUP BY m.id
    ORDER BY total_deal DESC, total_leads DESC
    LIMIT 5
";
$top_global_stmt = $conn->prepare($top_global_sql);
$top_global_stmt->execute($deal_statuses);
$top_global = $top_global_stmt->fetchAll();

// ========== TOP PERFORMER PER DEVELOPER ==========
$top_per_developer = [];
foreach ($developers as $dev) {
    $top_sql = "
        SELECT m.id, m.nama_lengkap, m.phone,
               COUNT(l.id) as total_leads,
               SUM(CASE WHEN l.status IN ($deal_placeholders) THEN 1 ELSE 0 END) as total_deal
        FROM marketing_team m
        LEFT JOIN leads l ON m.id = l.assigned_marketing_team_id 
            AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
            AND MONTH(l.created_at) = MONTH(CURDATE())
            AND YEAR(l.created_at) = YEAR(CURDATE())
        WHERE m.developer_id = ? AND m.is_active = 1
        GROUP BY m.id
        ORDER BY total_deal DESC, total_leads DESC
        LIMIT 1
    ";
    $top_stmt = $conn->prepare($top_sql);
    $top_stmt->execute(array_merge([$dev['id']], $deal_statuses));
    $top = $top_stmt->fetch();
    if ($top) {
        $top['developer_name'] = $dev['nama_lengkap'];
        $top_per_developer[] = $top;
    }
}

// ========== DATA BULANAN ==========
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));

$month_sql = "
    SELECT 
        COUNT(*) as total_month,
        SUM(CASE WHEN status IN ($deal_placeholders) THEN 1 ELSE 0 END) as deal_month,
        SUM(CASE WHEN status IN ('Tolak Slik', 'Tidak Minat', 'Batal') THEN 1 ELSE 0 END) as negatif_month
    FROM leads 
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
";

$month_stmt = $conn->prepare($month_sql);
$month_stmt->execute(array_merge($deal_statuses, [$month_start, $month_end]));
$month_data = $month_stmt->fetch();

$last_stmt = $conn->prepare($month_sql);
$last_stmt->execute(array_merge($deal_statuses, [$last_month_start, $last_month_end]));
$last_data = $last_stmt->fetch();

$month_total = $month_data['total_month'] ?? 0;
$month_deal = $month_data['deal_month'] ?? 0;
$month_negatif = $month_data['negatif_month'] ?? 0;
$last_total = $last_data['total_month'] ?? 0;
$total_change = $last_total > 0 ? round((($month_total - $last_total) / $last_total) * 100, 1) : 100;

// ========== RECENT ACTIVITIES ==========
$activity_sql = "
    SELECT a.*, 
           m.nama_lengkap as marketing_name, 
           u.nama_lengkap as developer_name,
           l.first_name, l.last_name, l.phone
    FROM marketing_activities a
    LEFT JOIN marketing_team m ON a.marketing_id = m.id
    LEFT JOIN users u ON m.developer_id = u.id
    LEFT JOIN leads l ON a.lead_id = l.id
    ORDER BY a.created_at DESC
    LIMIT 20
";
$recent_activities = $conn->query($activity_sql)->fetchAll();

// ========== SET GLOBAL VARIABLE UNTUK CHART.JS ==========
echo '<script>';
echo 'window.chartLabels = ' . json_encode($chart_labels) . ';';
echo 'window.chartData = ' . json_encode($chart_data) . ';';
echo 'window.isAdmin = ' . (isAdmin() ? 'true' : 'false') . ';';
echo 'window.isManager = ' . (isManager() ? 'true' : 'false') . ';';
echo 'window.API_KEY = "' . API_KEY . '";';
echo '</script>';

// ========== SET VARIABLES UNTUK INCLUDE ==========
$page_title = 'Dashboard Manager';
$page_subtitle = 'Supervisi Multi Developer';
$page_icon = 'fas fa-chart-line';
$use_chart = true;

include 'includes/header.php';
?>

<!-- SIDEBAR -->
<?php include 'includes/sidebar.php'; ?>

<!-- MAIN CONTENT -->
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
    
    <!-- NOTIFICATION BAR -->
    <?php if (!empty($notifications)): ?>
    <div class="alert success" id="notificationBar">
        <i class="fas fa-bell"></i>
        <div style="flex: 1;">
            <strong><?= count($notifications) ?> notifikasi baru</strong>
            <?php foreach (array_slice($notifications, 0, 2) as $notif): ?>
            <span> • <?= htmlspecialchars($notif['title']) ?></span>
            <?php endforeach; ?>
            <?php if (count($notifications) > 2): ?>
            <span> dan <?= count($notifications) - 2 ?> lainnya</span>
            <?php endif; ?>
        </div>
        <button class="modal-close" onclick="document.getElementById('notificationBar').style.display='none'" style="background: none; border: none; color: white; cursor: pointer; width: auto; height: auto;">✕</button>
    </div>
    <?php endif; ?>
    
    <!-- HEADER CARD -->
    <div class="header-card">
        <div class="header-title">
            <i class="fas fa-chart-line"></i>
            Data Pelanggan
        </div>
        <div class="header-stats">
            <div class="header-stat">
                <div class="header-stat-value"><?= number_format($stats['today']) ?></div>
                <div class="header-stat-label">Hari Ini</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value"><?= number_format($stats['week']) ?></div>
                <div class="header-stat-label">Minggu Ini</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value"><?= $stats['avg_score'] ?></div>
                <div class="header-stat-label">Rata Score</div>
            </div>
        </div>
        
        <!-- EXPORT BUTTONS -->
        <div style="display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap;">
            <button onclick="openExportModal()" class="export-btn" style="background: linear-gradient(135deg, var(--secondary), var(--secondary-light));">
                <i class="fas fa-star"></i> Export Premium
            </button>
        </div>
    </div>
    
    <!-- ===== KPI MARKETING CARD (HORIZONTAL DI MOBILE) ===== -->
    <div class="kpi-manager-card" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0); border-radius: 20px; padding: 20px; margin-bottom: 24px; color: white; box-shadow: 0 10px 25px rgba(74,144,226,0.3);">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">
            <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 25px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fas fa-users"></i>
            </div>
            <div style="flex: 1;">
                <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 3px; color: white;">KPI Marketing (Semua Developer)</h3>
                <p style="opacity: 0.9; font-size: 12px; margin: 0;">Ringkasan kinerja semua marketing</p>
            </div>
            <a href="marketing_kpi.php" style="background: white; color: #4A90E2; padding: 8px 16px; border-radius: 40px; text-decoration: none; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;">
                <i class="fas fa-chart-bar"></i> Detail
            </a>
        </div>
        
        <!-- GRID 2 KOLOM UNTUK MOBILE -->
        <div class="kpi-stats-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
            <div style="background: rgba(255,255,255,0.15); border-radius: 12px; padding: 12px; text-align: center;">
                <div style="font-size: 22px; font-weight: 800;"><?= $total_marketing_aktif ?></div>
                <div style="font-size: 11px; opacity: 0.9;">Marketing Aktif</div>
                <div style="font-size: 10px; margin-top: 3px;">dari <?= count($developers) ?> developer</div>
            </div>
            
            <div style="background: rgba(255,255,255,0.15); border-radius: 12px; padding: 12px; text-align: center;">
                <div style="font-size: 22px; font-weight: 800;"><?= number_format($total_leads_marketing) ?></div>
                <div style="font-size: 11px; opacity: 0.9;">Total Leads</div>
                <div style="font-size: 10px; margin-top: 3px;">diassign ke marketing</div>
            </div>
            
            <div style="background: rgba(255,255,255,0.15); border-radius: 12px; padding: 12px; text-align: center;">
                <div style="font-size: 22px; font-weight: 800; color: #FFD700;"><?= $total_deal_marketing ?></div>
                <div style="font-size: 11px; opacity: 0.9;">Total Deal</div>
                <div style="font-size: 10px; margin-top: 3px;">semua marketing</div>
            </div>
            
            <div style="background: rgba(255,255,255,0.15); border-radius: 12px; padding: 12px; text-align: center;">
                <div style="font-size: 22px; font-weight: 800;"><?= $conversion_rate_marketing ?>%</div>
                <div style="font-size: 11px; opacity: 0.9;">Conversion Rate</div>
                <div style="font-size: 10px; margin-top: 3px;">rata-rata</div>
            </div>
        </div>
    </div>
    
        <!-- ===== TOP PERFORMER + ANALISIS BULANAN (GRID 2 KOLOM DI DESKTOP, 1 KOLOM DI MOBILE) ===== -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
        
        <!-- TOP PERFORMER GLOBAL - FIXED UNTUK MOBILE -->
        <div style="background: white; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <h3 style="font-size: 15px; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-crown" style="color: #FFD700; font-size: 16px;"></i> Top 5 Marketing
                </h3>
                <span style="background: #E7F3EF; padding: 3px 10px; border-radius: 20px; font-size: 10px; white-space: nowrap;">Bulan Ini</span>
            </div>
            
            <!-- GRID 2 KOLOM UNTUK MOBILE, 1 KOLOM UNTUK DESKTOP -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; max-height: 300px; overflow-y: auto; padding-right: 4px;">
                <?php 
                $rank = 1;
                foreach ($top_global as $top): 
                ?>
                <div style="background: #F8F9FA; border-radius: 12px; padding: 10px; border-left: 3px solid <?= $rank == 1 ? '#FFD700' : ($rank == 2 ? '#C0C0C0' : ($rank == 3 ? '#CD7F32' : '#E0DAD3')) ?>;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <div style="width: 24px; height: 24px; background: <?= $rank == 1 ? '#FFD700' : ($rank == 2 ? '#C0C0C0' : ($rank == 3 ? '#CD7F32' : '#E7F3EF')) ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; color: <?= $rank <= 3 ? '#000' : '#1B4A3C' ?>;">
                            <?= $rank ?>
                        </div>
                        <div style="font-weight: 600; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($top['nama_lengkap']) ?></div>
                    </div>
                    <div style="font-size: 10px; color: #7A8A84; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($top['developer_name'] ?? 'Unknown') ?></div>
                    <div style="display: flex; justify-content: space-between; font-size: 11px;">
                        <span>Leads: <strong><?= $top['total_leads'] ?></strong></span>
                        <span>Deal: <strong style="color: #2A9D8F;"><?= $top['total_deal'] ?></strong></span>
                    </div>
                </div>
                <?php 
                $rank++;
                endforeach; 
                ?>
                
                <?php if (empty($top_global)): ?>
                <div style="text-align: center; padding: 20px; color: #7A8A84; grid-column: 1 / -1;">
                    <i class="fas fa-inbox fa-2x" style="margin-bottom: 8px;"></i>
                    <p style="font-size: 12px;">Belum ada data</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ANALISIS BULANAN + TOP PER DEVELOPER (TETAP SAMA) -->
        <div style="background: white; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <h3 style="font-size: 15px; font-weight: 700; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-calendar-alt" style="color: var(--secondary);"></i> Bulan <?= date('M Y') ?>
            </h3>
            
            <!-- GRID 2 KOLOM UNTUK STATISTIK BULANAN -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 15px;">
                <div style="background: #F5F3F0; border-radius: 10px; padding: 10px;">
                    <div style="font-size: 10px; color: #7A8A84;">Total Leads</div>
                    <div style="font-size: 18px; font-weight: 800; color: var(--primary);"><?= number_format($month_total) ?></div>
                    <?php 
                    $change_class = $total_change >= 0 ? 'text-success' : 'text-danger';
                    $change_icon = $total_change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                    ?>
                    <div style="font-size: 9px; margin-top: 2px;">
                        <span class="<?= $change_class ?>">
                            <i class="fas <?= $change_icon ?>"></i> <?= abs($total_change) ?>%
                        </span>
                    </div>
                </div>
                
                <div style="background: #F5F3F0; border-radius: 10px; padding: 10px;">
                    <div style="font-size: 10px; color: #7A8A84;">Total Deal</div>
                    <div style="font-size: 18px; font-weight: 800; color: #2A9D8F;"><?= number_format($month_deal) ?></div>
                    <div style="font-size: 9px; margin-top: 2px;">
                        Conv: <?= $month_total > 0 ? round(($month_deal / $month_total) * 100, 1) : 0 ?>%
                    </div>
                </div>
                
                <div style="background: #F5F3F0; border-radius: 10px; padding: 10px;">
                    <div style="font-size: 10px; color: #7A8A84;">Lead Negatif</div>
                    <div style="font-size: 18px; font-weight: 800; color: #D64F3C;"><?= number_format($month_negatif) ?></div>
                    <div style="font-size: 9px; margin-top: 2px;">
                        Rate: <?= $month_total > 0 ? round(($month_negatif / $month_total) * 100, 1) : 0 ?>%
                    </div>
                </div>
                
                <div style="background: #F5F3F0; border-radius: 10px; padding: 10px;">
                    <div style="font-size: 10px; color: #7A8A84;">Progress</div>
                    <div style="font-size: 18px; font-weight: 800; color: #4A90E2;"><?= min(100, round(($month_deal / 30) * 100)) ?>%</div>
                    <div style="height: 4px; background: #E0DAD3; border-radius: 2px; margin-top: 5px; overflow: hidden;">
                        <div style="height: 4px; width: <?= min(100, round(($month_deal / 30) * 100)) ?>%; background: #4A90E2; border-radius: 2px;"></div>
                    </div>
                </div>
            </div>
            
            <!-- TOP PER DEVELOPER (GRID 2 KOLOM) -->
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #E0DAD3;">
                <h4 style="font-size: 13px; font-weight: 600; margin-bottom: 10px; color: var(--primary);">🏆 Top per Developer</h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                    <?php foreach ($top_per_developer as $top_dev): ?>
                    <div style="background: #E7F3EF; border-radius: 8px; padding: 8px;">
                        <div style="font-weight: 600; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($top_dev['nama_lengkap'] ?? '-') ?></div>
                        <div style="font-size: 9px; color: #7A8A84; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($top_dev['developer_name'] ?? '-') ?></div>
                        <div style="display: flex; gap: 8px; margin-top: 4px; font-size: 9px;">
                            <span>Leads: <?= $top_dev['total_leads'] ?? 0 ?></span>
                            <span style="color: #2A9D8F;">Deal: <?= $top_dev['total_deal'] ?? 0 ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
            
            
    
    <!-- STATS CARDS (HORIZONTAL DI MOBILE) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Leads</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-label">Hari Ini</div>
            <div class="stat-value"><?= $stats['today'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
            <div class="stat-label">Minggu Ini</div>
            <div class="stat-value"><?= $stats['week'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-label">Bulan Ini</div>
            <div class="stat-value"><?= $stats['month'] ?></div>
        </div>
    </div>
    
    <!-- SCORE CARDS (HORIZONTAL DI MOBILE) -->
    <div class="score-grid">
        <div class="score-card">
            <div class="score-icon hot"><i class="fas fa-fire"></i></div>
            <div class="score-info">
                <div class="score-value"><?= $stats['hot'] ?></div>
                <div class="score-label">Hot Lead</div>
            </div>
        </div>
        <div class="score-card">
            <div class="score-icon warm"><i class="fas fa-sun"></i></div>
            <div class="score-info">
                <div class="score-value"><?= $stats['warm'] ?></div>
                <div class="score-label">Warm Lead</div>
            </div>
        </div>
        <div class="score-card">
            <div class="score-icon cold"><i class="fas fa-snowflake"></i></div>
            <div class="score-info">
                <div class="score-value"><?= $stats['cold'] ?></div>
                <div class="score-label">Cold Lead</div>
            </div>
        </div>
    </div>
    
    <!-- CHART -->
    <div class="chart-card">
        <div class="chart-title">
            <i class="fas fa-chart-line"></i>
            Tren Leads 7 Hari Terakhir
        </div>
        <div class="chart-container">
            <canvas id="trendChart"></canvas>
        </div>
    </div>
    
    <!-- LOCATION CARDS (HORIZONTAL DI MOBILE) -->
    <div class="location-grid">
        <?php foreach ($loc_stats as $loc): ?>
        <div class="location-card">
            <div class="location-icon"><?= $loc['icon'] ?? '🏠' ?></div>
            <div class="location-info">
                <div class="location-name"><?= htmlspecialchars($loc['display_name'] ?? $loc['location_key']) ?></div>
                <div class="location-count"><?= $loc['count'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- STATUS GRID (HORIZONTAL DI MOBILE) -->
    <div class="status-grid">
        <?php foreach ($status_list as $status):
            $count = $status_map[$status] ?? 0;
        ?>
        <div class="status-item">
            <div class="status-name"><?= $status ?></div>
            <div class="status-count"><?= $count ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- FILTER BAR DENGAN DEVELOPER & MARKETING -->
    <div class="filter-bar">
        <form method="GET" class="filter-form" id="filterForm">
            <input type="text" name="search" class="filter-input" 
                   placeholder="Cari nama, telepon, email, kota..." 
                   value="<?= htmlspecialchars($search) ?>">
            
            <select name="developer" class="filter-select" id="filterDeveloper" onchange="this.form.submit()">
                <option value="">Semua Developer</option>
                <?php foreach ($developers as $dev): ?>
                <option value="<?= $dev['id'] ?>" <?= $developer_filter == $dev['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dev['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="marketing" class="filter-select" id="filterMarketing">
                <option value="">Semua Marketing</option>
                <?php 
                if ($developer_filter > 0) {
                    $marketing_options = $conn->prepare("SELECT id, nama_lengkap FROM marketing_team WHERE developer_id = ? AND is_active = 1 ORDER BY nama_lengkap");
                    $marketing_options->execute([$developer_filter]);
                } else {
                    $marketing_options = $conn->query("SELECT id, nama_lengkap FROM marketing_team WHERE is_active = 1 ORDER BY nama_lengkap");
                }
                
                while ($m = $marketing_options->fetch()):
                    $selected = ($marketing_filter == $m['id']) ? 'selected' : '';
                ?>
                <option value="<?= $m['id'] ?>" <?= $selected ?>><?= htmlspecialchars($m['nama_lengkap']) ?></option>
                <?php endwhile; ?>
            </select>
            
            <select name="location" class="filter-select">
                <option value="">Semua Perumahan</option>
                <?php foreach ($locations as $loc): ?>
                <option value="<?= $loc['location_key'] ?>" <?= $location_filter == $loc['location_key'] ? 'selected' : '' ?>>
                    <?= $loc['icon'] ?> <?= htmlspecialchars($loc['display_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
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
    
    <!-- TABLE LEADS -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fas fa-list"></i>
                Daftar Leads
            </h3>
            <div class="table-badge">
                <i class="fas fa-database"></i>
                Total: <?= $total_records ?> | Halaman <?= $page ?> dari <?= $total_pages ?>
                <?php if ($stats['duplicate_warnings'] > 0): ?>
                <span style="margin-left: 10px; background: #FF9800; color: white; padding: 4px 10px; border-radius: 30px; font-size: 11px;">
                    ⚠️ <?= $stats['duplicate_warnings'] ?> Duplikat
                </span>
                <?php endif; ?>
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
                        <th>Unit</th>
                        <th>Program</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Developer</th>
                        <th>Marketing</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 60px;">
                            <i class="fas fa-inbox fa-4x" style="color: var(--border); margin-bottom: 16px;"></i>
                            <p style="color: var(--text-muted);">Belum ada data lead</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($leads as $lead): 
                            $score_class = $lead['lead_score'] >= 80 ? 'hot' : ($lead['lead_score'] >= 60 ? 'warm' : 'cold');
                            $full_name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
                            $status_class = str_replace(' ', '-', $lead['status'] ?? 'Baru');
                            $duplicate_class = !empty($lead['is_duplicate_warning']) ? 'duplicate-warning' : '';
                        ?>
                        <tr class="<?= $duplicate_class ?>">
                            <td><strong>#<?= $lead['id'] ?></strong></td>
                            <td>
                                <span class="location-badge">
                                    <?= $lead['icon'] ?? '' ?> <?= htmlspecialchars($lead['location_display'] ?? $lead['location_key']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($full_name ?: '-') ?></strong>
                                <?php if (!empty($lead['is_duplicate_warning'])): ?>
                                <span class="duplicate-badge">⚠️ DUPLIKAT</span>
                                <?php endif; ?>
                            </td>
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
                            <td><?= htmlspecialchars($lead['developer_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($lead['marketing_name'] ?? '-') ?></td>
                            <td><?= date('d/m/Y', strtotime($lead['created_at'])) ?></td>
                            <td style="text-align: center; white-space: nowrap;">
                                <div class="action-buttons">
                                    <button class="action-btn view" onclick="viewLead(<?= $lead['id'] ?>)" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if (isAdmin()): ?>
                                    <button class="action-btn edit" onclick="editLead(<?= $lead['id'] ?>)" title="Edit Data">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="showDeleteModal(<?= $lead['id'] ?>, '<?= htmlspecialchars(addslashes($full_name)) ?>')" title="Hapus Data">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                    
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
            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&location=<?= urlencode($location_filter) ?>&status=<?= urlencode($status_filter) ?>&developer=<?= $developer_filter ?>&marketing=<?= $marketing_filter ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&location=<?= urlencode($location_filter) ?>&status=<?= urlencode($status_filter) ?>&developer=<?= $developer_filter ?>&marketing=<?= $marketing_filter ?>" 
               class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&location=<?= urlencode($location_filter) ?>&status=<?= urlencode($status_filter) ?>&developer=<?= $developer_filter ?>&marketing=<?= $marketing_filter ?>" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- RECENT ACTIVITIES -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fas fa-history"></i>
                Aktivitas Terbaru Semua Marketing
            </h3>
            <span class="table-badge"><?= count($recent_activities) ?> aktivitas</span>
        </div>
        
        <?php if (empty($recent_activities)): ?>
        <div style="text-align: center; padding: 30px; background: #f9f9f9; border-radius: 12px;">
            <i class="fas fa-history" style="font-size: 40px; color: #ccc; margin-bottom: 10px;"></i>
            <p style="color: #666; font-size: 13px;">Belum ada aktivitas</p>
        </div>
        <?php else: ?>
        <div style="max-height: 400px; overflow-y: auto;">
            <?php foreach ($recent_activities as $act): 
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
                $icon = $action_icons[$act['action_type']] ?? '📋';
                $full_name = trim(($act['first_name'] ?? '') . ' ' . ($act['last_name'] ?? ''));
            ?>
            <div style="padding: 12px; border-bottom: 1px solid #E0DAD3; display: flex; align-items: flex-start; gap: 10px;">
                <div style="width: 36px; height: 36px; background: #E7F3EF; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                    <?= $icon ?>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; flex-wrap: wrap; gap: 5px;">
                        <span style="font-weight: 700; color: var(--primary); font-size: 13px;"><?= htmlspecialchars($act['marketing_name'] ?? 'Unknown') ?></span>
                        <span style="font-size: 10px; color: #7A8A84;"><?= date('d/m H:i', strtotime($act['created_at'])) ?></span>
                    </div>
                    <div style="font-size: 12px; color: #4A5A54; word-wrap: break-word;">
                        <span style="font-weight: 600;"><?= htmlspecialchars($full_name ?: 'Lead #' . $act['lead_id']) ?></span>
                        <?php if ($act['status_before'] != $act['status_after']): ?>
                        <span style="margin: 0 4px; white-space: nowrap;">
                            <span class="status-badge" style="font-size: 9px; padding: 2px 5px;"><?= $act['status_before'] ?></span>
                            <i class="fas fa-arrow-right" style="margin: 0 2px; color: var(--secondary); font-size: 9px;"></i>
                            <span class="status-badge" style="font-size: 9px; padding: 2px 5px;"><?= $act['status_after'] ?></span>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 11px; color: #7A8A84; margin-top: 3px;">
                        <i class="fas fa-building"></i> <?= htmlspecialchars($act['developer_name'] ?? 'Unknown') ?>
                        <?php if (!empty($act['note_text'])): ?>
                        <div style="margin-top: 4px; padding: 5px; background: #f5f5f5; border-radius: 6px; font-size: 11px;">
                            <i class="fas fa-quote-left" style="color: var(--secondary);"></i> <?= htmlspecialchars(substr($act['note_text'], 0, 80)) ?><?= strlen($act['note_text']) > 80 ? '...' : '' ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> TaufikMarie.com - Manager Dashboard v41.0 (Mobile Horizontal)</p>
        <p>Total Leads: <?= number_format($stats['total']) ?> | Hari Ini: <?= $stats['today'] ?> | Marketing Aktif: <?= $total_marketing_aktif ?></p>
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
            <div class="text-center">
                <div class="spinner"></div>
                <p style="margin-top: 16px; color: var(--text-muted);">Memuat data...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeViewModal()">Tutup</button>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Lead #<span id="editIdDisplay"></span></h2>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editForm" onsubmit="event.preventDefault(); submitEdit();">
            <input type="hidden" id="edit_id" name="id">
            <input type="hidden" name="key" value="<?= API_KEY ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit_location_key" name="location_key">
            
            <div class="modal-body" style="max-height: 50vh; overflow-y: auto; padding: 20px 24px;">
                <div class="form-group">
                    <label for="edit_status">
                        <i class="fas fa-tag"></i> Status Lead
                    </label>
                    <select id="edit_status" name="status" class="form-control" required>
                        <?php foreach ($status_list as $status): ?>
                        <option value="<?= $status ?>"><?= $status ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_unit_type">
                        <i class="fas fa-home"></i> Tipe Unit
                    </label>
                    <select id="edit_unit_type" name="unit_type" class="form-control" required>
                        <option value="">Memuat tipe unit...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_program">
                        <i class="fas fa-file-signature"></i> Program
                    </label>
                    <select id="edit_program" name="program" class="form-control" required>
                        <option value="">Memuat program...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_address">
                        <i class="fas fa-map-marker-alt"></i> Alamat
                    </label>
                    <textarea id="edit_address" name="address" class="form-control" rows="2" placeholder="Alamat lengkap..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_city">
                        <i class="fas fa-city"></i> Kota
                    </label>
                    <input type="text" id="edit_city" name="city" class="form-control" placeholder="Kota">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="edit_notes">
                        <i class="fas fa-sticky-note"></i> Catatan
                    </label>
                    <textarea id="edit_notes" name="notes" class="form-control" rows="4" placeholder="Catatan penting..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer" style="padding: 16px 24px; border-top: 2px solid var(--primary-soft); background: white;">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 20px 24px;">
            <div class="delete-icon-wrapper">
                <i class="fas fa-trash-alt"></i>
            </div>
            
            <h3 class="delete-title">Hapus Data Permanen?</h3>
            <p class="delete-subtitle">Tindakan ini tidak dapat dibatalkan</p>
            
            <div class="delete-name-card" id="deleteName"></div>
            
            <div class="delete-warning-card">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Peringatan!</strong>
                    <p>Semua riwayat aktivitas, notifikasi, dan data terkait akan ikut terhapus permanen.</p>
                </div>
            </div>
            
            <input type="hidden" id="deleteId">
            
            <div class="delete-actions">
                <button class="delete-btn cancel" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button class="delete-btn confirm" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Hapus Permanen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EXPORT MODAL -->
<div class="modal" id="exportModal">
    <div class="modal-content" style="max-width: 1200px; height: 90vh; padding: 0;">
        <iframe id="exportIframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="assets/js/index.js"></script>

<script>
// ==================== EXPORT MODAL ====================
function openExportModal() {
    const modal = document.getElementById('exportModal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        document.getElementById('exportIframe').src = 'export_modal.php';
    }
}

function closeExportModal() {
    const modal = document.getElementById('exportModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        document.getElementById('exportIframe').src = '';
    }
}

function openPremiumExportModal() {
    openExportModal();
}

// ==================== DATE & TIME ====================
document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
});

function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const dateEl = document.querySelector('.date span');
    const timeEl = document.querySelector('.time span');
    
    if (dateEl) dateEl.textContent = now.toLocaleDateString('id-ID', options);
    if (timeEl) timeEl.textContent = now.toLocaleTimeString('id-ID', { hour12: false });
}

// ==================== VIEW LEAD ====================
function viewLead(id) {
    document.getElementById('viewId').textContent = id;
    document.getElementById('viewContent').innerHTML = '<div class="text-center"><div class="spinner"></div><p style="margin-top: 16px; color: var(--text-muted);">Memuat data...</p></div>';
    openModal('viewModal');
    
    fetch('api/leads.php?action=get&id=' + id + '&key=<?= API_KEY ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                const l = data.data;
                const name = (l.first_name || '') + ' ' + (l.last_name || '');
                
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
                                <div class="view-item-label"><i class="fas fa-home"></i> Tipe Unit</div>
                                <div class="view-item-value">${l.unit_type || 'Type 36'}</div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-file-signature"></i> Program</div>
                                <div class="view-item-value">${l.program || 'Subsidi'}</div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-city"></i> Kota</div>
                                <div class="view-item-value">${l.city || '-'}</div>
                            </div>
                            <div class="view-item" style="grid-column: span 2;">
                                <div class="view-item-label"><i class="fas fa-map-pin"></i> Alamat</div>
                                <div class="view-item-value">${l.address || '-'}</div>
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
                                <div class="view-item-value"><span class="score-badge ${l.lead_score >= 80 ? 'score-hot' : (l.lead_score >= 60 ? 'score-warm' : 'score-cold')}">${l.lead_score || 0}</span></div>
                            </div>
                        </div>
                    </div>
                    ${l.notes ? `
                    <div class="view-section">
                        <div class="view-section-title"><i class="fas fa-sticky-note"></i> Catatan</div>
                        <div style="background:white; padding:16px; border-radius:12px;">${l.notes.replace(/\n/g, '<br>')}</div>
                    </div>` : ''}
                `;
            } else {
                document.getElementById('viewContent').innerHTML = '<div class="text-center" style="color:var(--danger)"><i class="fas fa-exclamation-circle fa-3x"></i><p>Gagal memuat data</p></div>';
            }
        })
        .catch(() => {
            document.getElementById('viewContent').innerHTML = '<div class="text-center" style="color:var(--danger)"><i class="fas fa-exclamation-circle fa-3x"></i><p>Terjadi kesalahan</p></div>';
        });
}

// ==================== EDIT LEAD ====================
function editLead(id) {
    <?php if (!isAdmin()): ?>
    alert('Anda tidak memiliki izin untuk mengedit data.');
    return;
    <?php endif; ?>
    
    document.getElementById('editIdDisplay').textContent = id;
    document.getElementById('edit_id').value = id;
    
    openModal('editModal');
    
    document.getElementById('edit_unit_type').innerHTML = '<option value="">Memuat tipe unit...</option>';
    document.getElementById('edit_program').innerHTML = '<option value="">Memuat program...</option>';
    document.getElementById('edit_unit_type').disabled = true;
    document.getElementById('edit_program').disabled = true;
    
    fetch('api/leads.php?action=get&id=' + id + '&key=<?= API_KEY ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                const l = data.data;
                
                document.getElementById('edit_status').value = l.status || 'Baru';
                document.getElementById('edit_address').value = l.address || '';
                document.getElementById('edit_city').value = l.city || '';
                document.getElementById('edit_notes').value = l.notes || '';
                document.getElementById('edit_location_key').value = l.location_key || '';
                
                loadUnitAndProgramOptions(l.location_key, l.unit_type, l.program);
                
            } else {
                alert('Gagal memuat data');
                closeEditModal();
            }
        })
        .catch(() => {
            alert('Terjadi kesalahan');
            closeEditModal();
        });
}

function loadUnitAndProgramOptions(locationKey, selectedUnit, selectedProgram) {
    if (!locationKey) {
        document.getElementById('edit_unit_type').innerHTML = '<option value="">Pilih lokasi terlebih dahulu</option>';
        document.getElementById('edit_program').innerHTML = '<option value="">Pilih lokasi terlebih dahulu</option>';
        document.getElementById('edit_unit_type').disabled = true;
        document.getElementById('edit_program').disabled = true;
        return;
    }
    
    fetch('/admin/api/get_locations.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const location = data.locations.find(loc => loc.location_key === locationKey);
                
                if (location) {
                    const units = location.unit_types_array || [];
                    const programs = location.programs_array || [];
                    
                    let unitOptions = '<option value="">Pilih Tipe Unit</option>';
                    units.forEach(unit => {
                        const selected = (unit === selectedUnit) ? 'selected' : '';
                        unitOptions += `<option value="${unit}" ${selected}>${unit}</option>`;
                    });
                    
                    if (units.length === 0) {
                        unitOptions = '<option value="Type 36/60">Type 36/60</option>';
                    }
                    
                    document.getElementById('edit_unit_type').innerHTML = unitOptions;
                    document.getElementById('edit_unit_type').disabled = false;
                    
                    let programOptions = '<option value="">Pilih Program</option>';
                    programs.forEach(prog => {
                        const selected = (prog === selectedProgram) ? 'selected' : '';
                        programOptions += `<option value="${prog}" ${selected}>${prog}</option>`;
                    });
                    
                    if (programs.length === 0) {
                        programOptions = '<option value="Subsidi">Subsidi</option><option value="Komersil">Komersil</option>';
                    }
                    
                    document.getElementById('edit_program').innerHTML = programOptions;
                    document.getElementById('edit_program').disabled = false;
                }
            }
        });
}

function submitEdit() {
    <?php if (!isAdmin()): ?>
    alert('Anda tidak memiliki izin untuk mengedit data.');
    return;
    <?php endif; ?>
    
    const unitType = document.getElementById('edit_unit_type').value;
    const program = document.getElementById('edit_program').value;
    
    if (!unitType) {
        alert('Pilih tipe unit terlebih dahulu');
        return;
    }
    
    if (!program) {
        alert('Pilih program terlebih dahulu');
        return;
    }
    
    const form = document.getElementById('editForm');
    const formData = new FormData(form);
    
    fetch('api/leads.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Perubahan tersimpan', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('❌ Gagal: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(() => {
        showToast('❌ Terjadi kesalahan', 'error');
    });
}

// ==================== DELETE LEAD ====================
function showDeleteModal(id, name) {
    <?php if (!isAdmin()): ?>
    alert('Anda tidak memiliki izin untuk menghapus data.');
    return;
    <?php endif; ?>
    
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name;
    openModal('deleteModal');
}

function confirmDelete() {
    <?php if (!isAdmin()): ?>
    alert('Anda tidak memiliki izin untuk menghapus data.');
    return;
    <?php endif; ?>
    
    const id = document.getElementById('deleteId').value;
    if (!id) return;
    
    fetch('api/leads.php?action=delete&id=' + id + '&key=<?= API_KEY ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('✅ Data berhasil dihapus', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('❌ Gagal: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(() => {
            showToast('❌ Terjadi kesalahan', 'error');
        });
}

// ==================== MODAL FUNCTIONS ====================
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('show');
    document.body.style.overflow = '';
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
    document.body.style.overflow = '';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

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

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-message ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

window.openExportModalFromSidebar = openExportModal;
window.openPremiumExportModalFromSidebar = openPremiumExportModal;
</script>

<!-- FOOTER -->
<?php include 'includes/footer.php'; ?>