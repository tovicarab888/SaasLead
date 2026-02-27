<?php
/**
 * DEVELOPER_TRACKING.PHP - Tracking untuk Developer (Lihat Tracking Sendiri)
 * Version: 2.0.0 - UI SUPER KEREN (FIX: Chart, Table, Stats)
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

// Hanya developer yang bisa akses
if (!isDeveloper()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Developer.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$developer_id = $_SESSION['user_id'];

// ========== FILTER ==========
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$pixel_type = $_GET['pixel_type'] ?? '';
$limit = 50;
$page = (int)($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

// ========== STATISTIK ==========
$stats_sql = "
    SELECT 
        COUNT(*) as total_events,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        ROUND(AVG(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) * 100, 2) as success_rate,
        COUNT(DISTINCT lead_id) as unique_leads
    FROM tracking_logs
    WHERE developer_id = ? AND DATE(created_at) BETWEEN ? AND ?
";
$stats_params = [$developer_id, $start_date, $end_date];

if (!empty($pixel_type)) {
    $stats_sql .= " AND pixel_type = ?";
    $stats_params[] = $pixel_type;
}

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute($stats_params);
$stats = $stats_stmt->fetch();

// ========== STATISTIK PER PIXEL ==========
$pixel_sql = "
    SELECT 
        pixel_type,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        ROUND(AVG(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) * 100, 2) as success_rate
    FROM tracking_logs
    WHERE developer_id = ? AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY pixel_type
";
$pixel_stmt = $conn->prepare($pixel_sql);
$pixel_stmt->execute([$developer_id, $start_date, $end_date]);
$pixel_stats = $pixel_stmt->fetchAll();

// ========== DATA HARIAN ==========
$daily_sql = "
    SELECT 
        DATE(created_at) as tanggal,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM tracking_logs
    WHERE developer_id = ? AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY tanggal ASC
";
$daily_stmt = $conn->prepare($daily_sql);
$daily_stmt->execute([$developer_id, $start_date, $end_date]);
$daily_data = $daily_stmt->fetchAll();

$chart_labels = [];
$chart_sent = [];
$chart_failed = [];
foreach ($daily_data as $row) {
    $chart_labels[] = date('d/m', strtotime($row['tanggal']));
    $chart_sent[] = (int)$row['sent'];
    $chart_failed[] = (int)$row['failed'];
}

// ========== LOGS TERBARU DENGAN PAGINATION ==========
$logs_sql = "
    SELECT 
        tl.*,
        l.first_name,
        l.last_name,
        l.phone,
        l.location_key,
        loc.display_name as location_name
    FROM tracking_logs tl
    LEFT JOIN leads l ON tl.lead_id = l.id
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    WHERE tl.developer_id = ?
";
$count_params = [$developer_id];
$logs_params = [$developer_id];

if (!empty($pixel_type)) {
    $logs_sql .= " AND tl.pixel_type = ?";
    $count_params[] = $pixel_type;
    $logs_params[] = $pixel_type;
}

// Count total untuk pagination
$count_sql = "SELECT COUNT(*) FROM (" . $logs_sql . ") as tmp";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($count_params);
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Get data dengan pagination
$logs_sql .= " ORDER BY tl.created_at DESC LIMIT ? OFFSET ?";
$logs_params[] = $limit;
$logs_params[] = $offset;

$logs_stmt = $conn->prepare($logs_sql);
$logs_stmt->execute($logs_params);
$logs = $logs_stmt->fetchAll();

// ========== SET VARIABLES ==========
$page_title = 'Tracking Developer';
$page_subtitle = 'Pantau Tracking Pixel Anda';
$page_icon = 'fas fa-chart-line';
$use_chart = true;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- ===== CSS TAMBAHAN UNTUK TRACKING ===== -->
<style>
/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    border-left: 6px solid;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.stat-card.total {
    border-left-color: #1B4A3C;
}

.stat-card.sent {
    border-left-color: #2A9D8F;
}

.stat-card.failed {
    border-left-color: #D64F3C;
}

.stat-card.pending {
    border-left-color: #E3B584;
}

.stat-card.unique {
    border-left-color: #4A90E2;
}

.stat-icon {
    font-size: 28px;
    margin-bottom: 12px;
}

.stat-icon.total { color: #1B4A3C; }
.stat-icon.sent { color: #2A9D8F; }
.stat-icon.failed { color: #D64F3C; }
.stat-icon.pending { color: #E3B584; }
.stat-icon.unique { color: #4A90E2; }

.stat-label {
    font-size: 13px;
    color: #7A8A84;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 28px;
    font-weight: 800;
    color: #1A2A24;
}

.stat-sub {
    font-size: 13px;
    color: #4A5A54;
    margin-top: 4px;
}

/* Chart Card */
.chart-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    margin-bottom: 30px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
}

.chart-title {
    font-size: 18px;
    font-weight: 700;
    color: #1B4A3C;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-title i {
    color: #D64F3C;
}

.chart-container {
    height: 300px;
    position: relative;
}

/* Pixel Cards */
.pixel-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.pixel-card {
    background: white;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border: 1px solid #E0DAD3;
    transition: transform 0.2s;
    text-align: center;
}

.pixel-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.pixel-icon {
    width: 60px;
    height: 60px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 30px;
    color: white;
}

.pixel-icon.meta { background: #1877F2; }
.pixel-icon.tiktok { background: #000000; }
.pixel-icon.google { background: #EA4335; }

.pixel-title {
    font-size: 16px;
    font-weight: 700;
    color: #1B4A3C;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.pixel-stats {
    font-size: 24px;
    font-weight: 800;
    color: #D64F3C;
    margin-bottom: 8px;
}

.pixel-detail {
    font-size: 13px;
    color: #4A5A54;
    display: flex;
    justify-content: center;
    gap: 16px;
}

.pixel-detail span i {
    margin-right: 4px;
}

.pixel-detail .sent { color: #2A9D8F; }
.pixel-detail .failed { color: #D64F3C; }

/* Filter Bar */
.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.filter-form {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #1B4A3C;
    margin-bottom: 6px;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #E0DAD3;
    border-radius: 12px;
    font-size: 14px;
    background: white;
}

.filter-group input:focus,
.filter-group select:focus {
    border-color: #D64F3C;
    outline: none;
}

.filter-actions {
    display: flex;
    gap: 12px;
}

.btn-filter {
    padding: 12px 24px;
    background: linear-gradient(135deg, #1B4A3C, #2A5F4E);
    color: white;
    border: none;
    border-radius: 40px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-reset {
    padding: 12px 24px;
    background: #E0DAD3;
    color: #1A2A24;
    border: none;
    border-radius: 40px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 16px;
}

.table-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1B4A3C;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-header h3 i {
    color: #D64F3C;
}

.table-badge {
    background: #E7F3EF;
    color: #1B4A3C;
    padding: 8px 16px;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 600;
}

.table-responsive {
    overflow-x: auto;
    margin: 0 -24px;
    padding: 0 24px;
    width: calc(100% + 48px);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

th {
    background: #E7F3EF;
    padding: 16px 12px;
    text-align: left;
    font-weight: 700;
    color: #1B4A3C;
    font-size: 13px;
    white-space: nowrap;
}

td {
    padding: 16px 12px;
    border-bottom: 1px solid #E0DAD3;
    font-size: 14px;
    vertical-align: middle;
}

tr:last-child td {
    border-bottom: none;
}

.pixel-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
    color: white;
}

.pixel-badge.meta { background: #1877F2; }
.pixel-badge.tiktok { background: #000000; }
.pixel-badge.google { background: #EA4335; }

.status-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
    color: white;
}

.status-badge.sent { background: #2A9D8F; }
.status-badge.failed { background: #D64F3C; }
.status-badge.pending { background: #E3B584; color: #1A2A24; }

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.pagination-btn {
    min-width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: white;
    border: 2px solid #E0DAD3;
    color: #1A2A24;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
}

.pagination-btn:hover {
    background: #E7F3EF;
    border-color: #1B4A3C;
}

.pagination-btn.active {
    background: #1B4A3C;
    border-color: #1B4A3C;
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 64px;
    color: #E0DAD3;
    margin-bottom: 16px;
}

.empty-state p {
    color: #7A8A84;
    font-size: 16px;
}

/* Footer */
.footer {
    text-align: center;
    margin-top: 40px;
    padding: 20px;
    color: #7A8A84;
    font-size: 12px;
    border-top: 1px solid #E0DAD3;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 20px 16px 90px !important;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .pixel-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .btn-filter,
    .btn-reset {
        width: 100%;
        justify-content: center;
    }
    
    .chart-container {
        height: 250px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 16px;
    }
    
    .stat-value {
        font-size: 24px;
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
            <div class="date" id="currentDate">
                <i class="fas fa-calendar-alt"></i>
                <span>Memuat tanggal...</span>
            </div>
            <div class="time" id="currentTime">
                <i class="fas fa-clock"></i>
                <span>--:--:--</span>
            </div>
        </div>
    </div>
    
    <!-- FILTER SECTION -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Start Date</label>
                <input type="date" name="start_date" value="<?= $start_date ?>">
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> End Date</label>
                <input type="date" name="end_date" value="<?= $end_date ?>">
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> Pixel Type</label>
                <select name="pixel_type">
                    <option value="">Semua Pixel</option>
                    <option value="meta" <?= $pixel_type == 'meta' ? 'selected' : '' ?>>Meta Pixel</option>
                    <option value="tiktok" <?= $pixel_type == 'tiktok' ? 'selected' : '' ?>>TikTok Pixel</option>
                    <option value="google" <?= $pixel_type == 'google' ? 'selected' : '' ?>>Google Analytics</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Terapkan Filter
                </button>
                <a href="?" class="btn-reset">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon total"><i class="fas fa-chart-line"></i></div>
            <div class="stat-label">Total Events</div>
            <div class="stat-value"><?= number_format($stats['total_events'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card sent">
            <div class="stat-icon sent"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Sent</div>
            <div class="stat-value"><?= number_format($stats['sent'] ?? 0) ?></div>
            <div class="stat-sub"><?= $stats['success_rate'] ?? 0 ?>% success</div>
        </div>
        
        <div class="stat-card failed">
            <div class="stat-icon failed"><i class="fas fa-times-circle"></i></div>
            <div class="stat-label">Failed</div>
            <div class="stat-value"><?= number_format($stats['failed'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card pending">
            <div class="stat-icon pending"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= number_format($stats['pending'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card unique">
            <div class="stat-icon unique"><i class="fas fa-users"></i></div>
            <div class="stat-label">Unique Leads</div>
            <div class="stat-value"><?= number_format($stats['unique_leads'] ?? 0) ?></div>
        </div>
    </div>
    
    <!-- CHART SECTION -->
    <div class="chart-card">
        <div class="chart-title">
            <i class="fas fa-chart-bar"></i>
            Tracking Events per Hari
        </div>
        <div class="chart-container">
            <canvas id="trackingChart"></canvas>
        </div>
    </div>
    
    <!-- PIXEL STATS CARDS -->
    <div class="pixel-grid">
        <?php 
        $pixel_types = ['meta', 'tiktok', 'google'];
        $pixel_names = ['Meta Pixel', 'TikTok Pixel', 'Google Analytics'];
        $pixel_icons = ['facebook-f', 'tiktok', 'google'];
        $pixel_colors = ['#1877F2', '#000000', '#EA4335'];
        
        foreach ($pixel_types as $index => $type):
            $found = false;
            foreach ($pixel_stats as $stat):
                if ($stat['pixel_type'] == $type):
                    $found = true;
        ?>
        <div class="pixel-card">
            <div class="pixel-icon <?= $type ?>" style="background: <?= $pixel_colors[$index] ?>;">
                <i class="fab fa-<?= $pixel_icons[$index] ?>"></i>
            </div>
            <div class="pixel-title"><?= $pixel_names[$index] ?></div>
            <div class="pixel-stats"><?= $stat['success_rate'] ?>%</div>
            <div class="pixel-detail">
                <span class="sent"><i class="fas fa-check-circle"></i> <?= number_format($stat['sent']) ?></span>
                <span class="failed"><i class="fas fa-times-circle"></i> <?= number_format($stat['failed']) ?></span>
            </div>
        </div>
        <?php 
                endif;
            endforeach;
            
            if (!$found):
        ?>
        <div class="pixel-card">
            <div class="pixel-icon <?= $type ?>" style="background: <?= $pixel_colors[$index] ?>;">
                <i class="fab fa-<?= $pixel_icons[$index] ?>"></i>
            </div>
            <div class="pixel-title"><?= $pixel_names[$index] ?></div>
            <div class="pixel-stats">0%</div>
            <div class="pixel-detail">
                <span class="sent"><i class="fas fa-check-circle"></i> 0</span>
                <span class="failed"><i class="fas fa-times-circle"></i> 0</span>
            </div>
        </div>
        <?php 
            endif;
        endforeach; 
        ?>
    </div>
    
    <!-- LOGS TABLE -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-history"></i> Logs Tracking Terbaru</h3>
            <div class="table-badge">
                <i class="fas fa-database"></i> Total: <?= number_format($total_logs) ?> | Halaman <?= $page ?> dari <?= $total_pages ?>
            </div>
        </div>
        
        <?php if (empty($logs)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Belum ada data tracking</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pixel</th>
                        <th>Lead</th>
                        <th>Event</th>
                        <th>Event ID</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $full_name = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <div><?= date('d/m/Y', strtotime($log['created_at'])) ?></div>
                            <small style="color: #7A8A84;"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                        </td>
                        <td>
                            <span class="pixel-badge <?= $log['pixel_type'] ?>">
                                <i class="fab fa-<?= $log['pixel_type'] == 'meta' ? 'facebook-f' : $log['pixel_type'] ?>"></i>
                                <?= strtoupper($log['pixel_type']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['lead_id']): ?>
                                <a href="index.php?search=<?= $log['lead_id'] ?>" target="_blank" style="color: #1B4A3C; font-weight: 600;">
                                    #<?= $log['lead_id'] ?>
                                </a>
                                <?php if (!empty($full_name)): ?>
                                <br><small><?= htmlspecialchars($full_name) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #7A8A84;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($log['event_name']) ?></td>
                        <td>
                            <code style="background: #E7F3EF; padding: 6px 10px; border-radius: 8px; font-size: 11px;">
                                <?= substr($log['event_id'], 0, 20) ?>...
                            </code>
                        </td>
                        <td>
                            <span class="status-badge <?= $log['status'] ?>">
                                <?= strtoupper($log['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&pixel_type=<?= $pixel_type ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++): 
            ?>
            <a href="?page=<?= $i ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&pixel_type=<?= $pixel_type ?>" 
               class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&pixel_type=<?= $pixel_type ?>" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Developer Tracking v2.0</p>
        <p style="margin-top: 5px;">Total Events: <?= number_format($stats['total_events'] ?? 0) ?> | Sent: <?= number_format($stats['sent'] ?? 0) ?> | Failed: <?= number_format($stats['failed'] ?? 0) ?></p>
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
                    borderWidth: 3
                },
                {
                    label: 'Failed',
                    data: <?= json_encode($chart_failed) ?>,
                    borderColor: '#D64F3C',
                    backgroundColor: 'rgba(214, 79, 60, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3
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
                        usePointStyle: true,
                        boxWidth: 8
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Update datetime
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', options);
        document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { hour12: false });
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();
    
    // Toast function
    function showToast(message, type = 'success') {
        let toast = document.querySelector('.toast-message');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'toast-message';
            document.body.appendChild(toast);
        }
        
        toast.className = `toast-message ${type}`;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
        
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>

<?php include 'includes/footer.php'; ?>