<?php
/**
 * INDEX.PHP - TAUFIKMARIE.COM ULTIMATE DASHBOARD
 * Version: 47.0.0 - FIXED: Support Finance Platform & Manager Developer
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

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

// ========== CEK AKSES BERDASARKAN ROLE ==========
$current_role = getCurrentRole();
$allowed_roles = ['admin', 'manager', 'finance_platform'];

// Hanya role tertentu yang bisa akses halaman ini
if (!in_array($current_role, $allowed_roles)) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Admin, Manager, dan Finance Platform.');
}

// ========== GET DATABASE CONNECTION ==========
$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== GET STATISTICS ==========
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
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// ========== BUILD QUERY ==========
$sql = "SELECT l.*, loc.display_name as location_display, loc.icon 
        FROM leads l 
        LEFT JOIN locations loc ON l.location_key = loc.location_key 
        WHERE (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";
$params = [];

// Jika finance platform, filter hanya untuk external leads (assigned_type = 'external')
if (isFinancePlatform()) {
    $sql .= " AND l.assigned_type = 'external'";
}

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

// Count total
$count_sql = "SELECT COUNT(*) FROM leads WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
$count_params = [];

// Jika finance platform, filter hanya untuk external leads
if (isFinancePlatform()) {
    $count_sql .= " AND assigned_type = 'external'";
}

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

// Untuk finance platform, chart juga filter external
if (isFinancePlatform()) {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('d/m', strtotime($date));
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE DATE(created_at) = ? AND assigned_type = 'external' AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
        $stmt->execute([$date]);
        $chart_data[] = (int)$stmt->fetchColumn();
    }
} else {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('d/m', strtotime($date));
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE DATE(created_at) = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
        $stmt->execute([$date]);
        $chart_data[] = (int)$stmt->fetchColumn();
    }
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
$loc_stats_sql = "
    SELECT l.location_key, loc.display_name, loc.icon, COUNT(*) as count 
    FROM leads l 
    LEFT JOIN locations loc ON l.location_key = loc.location_key 
    WHERE (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
";

if (isFinancePlatform()) {
    $loc_stats_sql .= " AND l.assigned_type = 'external'";
}

$loc_stats_sql .= " GROUP BY l.location_key ORDER BY count DESC";

$loc_stats = $conn->query($loc_stats_sql)->fetchAll();

// ========== GET STATUS COUNTS ==========
$status_counts_sql = "SELECT status, COUNT(*) as count FROM leads WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";

if (isFinancePlatform()) {
    $status_counts_sql .= " AND assigned_type = 'external'";
}

$status_counts_sql .= " GROUP BY status";

$status_counts = $conn->query($status_counts_sql)->fetchAll();
$status_map = [];
foreach ($status_counts as $s) {
    $status_map[$s['status']] = $s['count'];
}

// ========== SET GLOBAL VARIABLE UNTUK CHART.JS ==========
echo '<script>';
echo 'window.chartLabels = ' . json_encode($chart_labels) . ';';
echo 'window.chartData = ' . json_encode($chart_data) . ';';
echo 'window.isAdmin = ' . (isAdmin() ? 'true' : 'false') . ';';
echo 'window.isManager = ' . (isManager() ? 'true' : 'false') . ';';
echo 'window.isFinancePlatform = ' . (isFinancePlatform() ? 'true' : 'false') . ';';
echo 'window.API_KEY = "' . API_KEY . '";';
echo '</script>';

// ========== SET VARIABLES UNTUK INCLUDE ==========
$page_title = 'Dashboard Utama';
$page_subtitle = isFinancePlatform() ? 'Ringkasan Leads External' : 'Ringkasan Data Pelanggan';
$page_icon = 'fas fa-chart-line';
$use_chart = true;

// ========== INCLUDE HEADER ==========
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
        
        <!-- EXPORT BUTTONS - Hanya untuk Admin & Manager -->
        <?php if (isAdmin() || isManager()): ?>
        <div style="display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap;">
            <button onclick="openExportModal()" class="export-btn" style="background: linear-gradient(135deg, var(--secondary), var(--secondary-light));">
                <i class="fas fa-star"></i> Export Premium
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- STATS CARDS - HORIZONTAL -->
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
    
    <!-- SCORE CARDS - HORIZONTAL -->
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
    
    <!-- LOCATION CARDS - HORIZONTAL -->
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
    
    <!-- STATUS GRID - HORIZONTAL -->
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
    
    <!-- FILTER BAR -->
    <div class="filter-bar">
        <form method="GET" class="filter-form" id="filterForm">
            <input type="text" name="search" class="filter-input" 
                   placeholder="Cari nama, telepon, email, kota..." 
                   value="<?= htmlspecialchars($search) ?>">
            
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
    
    <!-- TABLE DENGAN ACTION BUTTONS + BADGE DUPLIKAT -->
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
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 60px;">
                            <i class="fas fa-inbox fa-4x" style="color: var(--border); margin-bottom: 16px;"></i>
                            <p style="color: var(--text-muted);">Belum ada data lead</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($leads as $lead): 
                            $score_class = $lead['lead_score'] >= 80 ? 'hot' : ($lead['lead_score'] >= 60 ? 'warm' : 'cold');
                            $full_name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
                            $location_name = $lead['location_display'] ?? $lead['location_key'];
                            $status_class = str_replace(' ', '-', $lead['status'] ?? 'Baru');
                            $duplicate_class = !empty($lead['is_duplicate_warning']) ? 'duplicate-warning' : '';
                        ?>
                        <tr class="<?= $duplicate_class ?>">
                            <td><strong>#<?= $lead['id'] ?></strong></td>
                            <td>
                                <span class="location-badge">
                                    <?= $lead['icon'] ?? '' ?> <?= htmlspecialchars($location_name) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($full_name ?: '-') ?></strong>
                                <?php if (!empty($lead['is_duplicate_warning'])): ?>
                                <span class="duplicate-badge" title="Data duplikat berdasarkan nomor/email">⚠️ DUPLIKAT</span>
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
                            <td><?= date('d/m/Y', strtotime($lead['created_at'])) ?></td>
                            <td>
                                <?php if ($lead['assigned_type'] == 'internal'): ?>
                                <span style="background: #2A9D8F; color: white; padding: 4px 8px; border-radius: 20px; font-size: 11px;">
                                    Internal
                                </span>
                                <?php elseif ($lead['assigned_type'] == 'external'): ?>
                                <span style="background: #4A90E2; color: white; padding: 4px 8px; border-radius: 20px; font-size: 11px;">
                                    External
                                </span>
                                <?php else: ?>
                                <span style="background: #E3B584; color: white; padding: 4px 8px; border-radius: 20px; font-size: 11px;">
                                    -
                                </span>
                                <?php endif; ?>
                            </td>
                           <td style="text-align: center; white-space: nowrap;">
            <!-- ACTION BUTTONS -->
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
            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&location=<?= urlencode($location_filter) ?>&status=<?= urlencode($status_filter) ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&location=<?= urlencode($location_filter) ?>&status=<?= urlencode($status_filter) ?>" 
               class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&location=<?= urlencode($location_filter) ?>&status=<?= urlencode($status_filter) ?>" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> TaufikMarie.com - Ultimate System Version 47.0.0 (Support Finance Platform & Manager Developer)</p>
        <p>Total Leads: <?= number_format($stats['total']) ?> | Hari Ini: <?= $stats['today'] ?> | Duplikat: <?= $stats['duplicate_warnings'] ?></p>
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

<!-- EDIT MODAL - DENGAN DROPDOWN UNIT & PROGRAM -->
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

<!-- DELETE MODAL PREMIUM -->
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

<!-- Chart.js dipanggil langsung -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- Index.js -->
<script src="assets/js/index.js"></script>

<script>
// ==================== EXPORT DROPDOWN ====================
function toggleExportDropdown() {
    document.querySelector('.export-dropdown').classList.toggle('active');
}

document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.export-dropdown');
    const button = dropdown?.querySelector('button');
    const content = dropdown?.querySelector('.export-dropdown-content');
    
    if (dropdown && button && !button.contains(event.target) && !content?.contains(event.target)) {
        dropdown.classList.remove('active');
    }
});

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
    const dateEl = document.getElementById('dateDisplay');
    const timeEl = document.getElementById('timeDisplay');
    
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
    
    // Set loading state
    document.getElementById('edit_unit_type').innerHTML = '<option value="">Memuat tipe unit...</option>';
    document.getElementById('edit_program').innerHTML = '<option value="">Memuat program...</option>';
    document.getElementById('edit_unit_type').disabled = true;
    document.getElementById('edit_program').disabled = true;
    
    fetch('api/leads.php?action=get&id=' + id + '&key=<?= API_KEY ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                const l = data.data;
                
                // Set basic fields
                document.getElementById('edit_status').value = l.status || 'Baru';
                document.getElementById('edit_address').value = l.address || '';
                document.getElementById('edit_city').value = l.city || '';
                document.getElementById('edit_notes').value = l.notes || '';
                document.getElementById('edit_location_key').value = l.location_key || '';
                
                // Load unit types and programs based on location
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

// Fungsi untuk load unit dan program berdasarkan lokasi
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
                    // Parse unit types
                    const units = location.unit_types_array || [];
                    const programs = location.programs_array || [];
                    
                    // Update unit type dropdown
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
                    
                    // Update program dropdown
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
                    
                } else {
                    // Fallback jika lokasi tidak ditemukan
                    document.getElementById('edit_unit_type').innerHTML = '<option value="Type 36/60">Type 36/60</option>';
                    document.getElementById('edit_program').innerHTML = '<option value="Subsidi">Subsidi</option><option value="Komersil">Komersil</option>';
                    document.getElementById('edit_unit_type').disabled = false;
                    document.getElementById('edit_program').disabled = false;
                }
            }
        })
        .catch(error => {
            console.error('Error loading location data:', error);
            // Fallback
            document.getElementById('edit_unit_type').innerHTML = '<option value="Type 36/60">Type 36/60</option>';
            document.getElementById('edit_program').innerHTML = '<option value="Subsidi">Subsidi</option><option value="Komersil">Komersil</option>';
            document.getElementById('edit_unit_type').disabled = false;
            document.getElementById('edit_program').disabled = false;
        });
}

function submitEdit() {
    <?php if (!isAdmin()): ?>
    alert('Anda tidak memiliki izin untuk mengedit data.');
    return;
    <?php endif; ?>
    
    // Validasi unit type dan program
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
    
    // Tambahkan unit_type dan program ke formData jika belum ada
    if (!formData.has('unit_type')) {
        formData.append('unit_type', unitType);
    }
    if (!formData.has('program')) {
        formData.append('program', program);
    }
    
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

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(modal => {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
});

// ==================== TOAST ====================
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-message ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// ==================== EXPORT FUNCTIONS FOR SIDEBAR ====================
window.openExportModalFromSidebar = openExportModal;
window.openPremiumExportModalFromSidebar = openPremiumExportModal;
</script>

<!-- FOOTER -->
<?php include 'includes/footer.php'; ?>