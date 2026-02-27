<?php
/**
 * MANAGER_DEVELOPER_DASHBOARD.PHP - LEADENGINE
 * Version: 1.0.0 - Dashboard untuk Manager Developer
 * MOBILE FIRST UI - STATISTIK LEADS DEVELOPER
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek session manager developer
if (!isManagerDeveloper()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$manager_id = $_SESSION['user_id'];
$manager_name = $_SESSION['nama_lengkap'] ?? 'Manager Developer';
$developer_id = $_SESSION['developer_id'] ?? 0;

if ($developer_id <= 0) {
    die("Error: Developer ID tidak ditemukan");
}

// Ambil data developer
$stmt = $conn->prepare("SELECT nama_lengkap, location_access FROM users WHERE id = ?");
$stmt->execute([$developer_id]);
$developer = $stmt->fetch();
$developer_name = $developer['nama_lengkap'] ?? 'Developer';
$location_access = $developer['location_access'] ?? '';

// ========== CEK AKSES LOKASI ==========
$locations_list = explode(',', $location_access);
$locations_list = array_map('trim', $locations_list);
$locations_list = array_filter($locations_list);

if (empty($locations_list)) {
    $has_locations = false;
    $warning_message = "Developer belum memiliki akses lokasi. Silakan hubungi Admin.";
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

// ========== STATISTIK ==========
$stats = [
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
    $stats['total'] = $stmt->fetchColumn();
    
    // Today
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE location_key IN ($placeholders) AND DATE(created_at) = CURDATE() AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
    $stmt->execute($locations_list);
    $stats['today'] = $stmt->fetchColumn();
    
    // Week
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE location_key IN ($placeholders) AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
    $stmt->execute($locations_list);
    $stats['week'] = $stmt->fetchColumn();
    
    // Month
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE location_key IN ($placeholders) AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
    $stmt->execute($locations_list);
    $stats['month'] = $stmt->fetchColumn();
    
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
    
    $stats['hot'] = (int)($score['hot'] ?? 0);
    $stats['warm'] = (int)($score['warm'] ?? 0);
    $stats['cold'] = (int)($score['cold'] ?? 0);
    $stats['avg_score'] = $score['avg_score'] ? round($score['avg_score']) : 0;
}

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

// ========== STATISTIK MARKETING ==========
$marketing_stats = [];
if ($developer_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_marketing,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as aktif
        FROM marketing_team 
        WHERE developer_id = ?
    ");
    $stmt->execute([$developer_id]);
    $marketing_stats = $stmt->fetch();
}

// ========== TOP PERFORMER MARKETING ==========
$top_performers = [];
if ($developer_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            m.id,
            m.nama_lengkap,
            m.phone,
            COUNT(DISTINCT l.id) as total_leads,
            COUNT(DISTINCT CASE WHEN l.status IN ('Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun') THEN l.id END) as total_deal,
            COUNT(DISTINCT ma.id) as follow_up
        FROM marketing_team m
        LEFT JOIN leads l ON m.id = l.assigned_marketing_team_id AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
        LEFT JOIN marketing_activities ma ON m.id = ma.marketing_id AND DATE(ma.created_at) = CURDATE()
        WHERE m.developer_id = ? AND m.is_active = 1
        GROUP BY m.id
        HAVING total_leads > 0 OR total_deal > 0
        ORDER BY total_deal DESC, total_leads DESC
        LIMIT 5
    ");
    $stmt->execute([$developer_id]);
    $top_performers = $stmt->fetchAll();
}

// ========== KOMISI PENDING ==========
$komisi_pending = [];
$total_komisi_pending = 0;
if ($developer_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(komisi_final) as total_nominal
        FROM komisi_logs 
        WHERE developer_id = ? AND status = 'pending'
    ");
    $stmt->execute([$developer_id]);
    $komisi_data = $stmt->fetch();
    $total_komisi_pending = (int)($komisi_data['total'] ?? 0);
    $total_nominal_pending = (float)($komisi_data['total_nominal'] ?? 0);
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
    
    // Query dengan DEBUG - ambil semua field yang mungkin
    $sql = "SELECT 
            l.*, 
            loc.display_name as location_display, 
            loc.icon,
            -- Marketing Internal
            m.id as internal_id,
            m.nama_lengkap as internal_marketing_name,
            m.phone as internal_marketing_phone,
            -- Marketing External (dari tabel users)
            u_external.id as external_user_id,
            u_external.nama_lengkap as external_marketing_name,
            u_external.phone as external_marketing_phone,
            u_external.email as external_marketing_email,
            u_external.role as external_role,
            -- Tampilkan marketing berdasarkan tipe
            CASE 
                WHEN l.assigned_type = 'internal' AND m.id IS NOT NULL THEN m.nama_lengkap
                WHEN l.assigned_type = 'external' AND u_external.id IS NOT NULL THEN u_external.nama_lengkap
                WHEN l.assigned_type = 'external' THEN 'Taufik Marie (Default)'
                ELSE '-'
            END as marketing_display,
            l.assigned_type
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
    
    // DEBUG: Tulis query ke log
    error_log("DEBUG - Query: " . $sql);
    error_log("DEBUG - Params: " . json_encode($params));
    
    // Hitung total records
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
    
    // DEBUG: Tulis jumlah data ke log
    error_log("DEBUG - Jumlah leads ditemukan: " . count($leads));
    
    // DEBUG: Tampilkan data pertama untuk investigasi
    if (!empty($leads)) {
        error_log("DEBUG - Data lead pertama: " . json_encode($leads[0]));
    }
}

// ========== PASS DATA KE JAVASCRIPT ==========
echo '<script>';
echo 'window.chartLabels = ' . json_encode($chart_labels) . ';';
echo 'window.chartData = ' . json_encode($chart_data) . ';';
echo 'window.API_KEY = "' . API_KEY . '";';
echo '</script>';

// ========== SET VARIABLES UNTUK INCLUDE ==========
$page_title = 'Dashboard Manager Developer';
$page_subtitle = $developer_name;
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
                <span><?= htmlspecialchars($page_subtitle) ?></span>
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

    <!-- ===== PERINGATAN JIKA TIDAK ADA AKSES LOKASI ===== -->
    <?php if (!$has_locations): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 24px; border-radius: 20px; margin-bottom: 24px; border-left: 6px solid #dc3545; display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
        <div style="width: 60px; height: 60px; background: rgba(220,53,69,0.2); border-radius: 30px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-exclamation-triangle fa-3x" style="color: #dc3545;"></i>
        </div>
        <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; color: #721c24; font-size: 20px;">Developer Belum Punya Akses Lokasi</h3>
            <p style="margin: 0; opacity: 0.9; font-size: 15px;"><?= $warning_message ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- WELCOME CARD -->
    <div class="header-card" style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 30px; display: flex; align-items: center; justify-content: center; font-size: 28px;">
                <i class="fas fa-user-tie"></i>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 5px; color: white;">Selamat datang, <?= htmlspecialchars($manager_name) ?>!</h3>
                <p style="opacity: 0.9; font-size: 14px; color: white; margin: 0;">
                    Anda mengelola data untuk <strong><?= htmlspecialchars($developer_name) ?></strong>
                </p>
                <p style="opacity: 0.8; font-size: 12px; color: white; margin-top: 5px;">
                    <i class="fas fa-users"></i> <?= $marketing_stats['aktif'] ?? 0 ?> Marketing Aktif • 
                    <i class="fas fa-coins"></i> <?= $total_komisi_pending ?> Komisi Pending
                </p>
            </div>
        </div>
    </div>

    <!-- FILTER INFO -->
    <?php if ($status_filter): ?>
    <div style="background: #E7F3EF; border-radius: 40px; padding: 10px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <i class="fas fa-filter" style="color: var(--secondary);"></i>
        <span style="font-weight: 600; color: var(--primary);">Filter aktif:</span>
        <?php if ($status_filter): ?>
        <span style="background: white; padding: 4px 12px; border-radius: 30px; font-size: 12px;"><i class="fas fa-tag"></i> <?= htmlspecialchars($status_filter) ?></span>
        <?php endif; ?>
        <a href="?" style="margin-left: auto; color: var(--secondary); font-size: 13px;"><i class="fas fa-times"></i> Reset</a>
    </div>
    <?php endif; ?>

    <!-- STATS CARDS -->
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

    <!-- SCORE CARDS -->
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
        <?php if (!$has_locations): ?>
        <p style="text-align: center; color: var(--text-muted); margin-top: 10px;">
            <i class="fas fa-info-circle"></i> Data chart kosong karena akses lokasi belum dikonfigurasi.
        </p>
        <?php endif; ?>
    </div>

    <!-- TOP PERFORMER CARD -->
    <?php if (!empty($top_performers)): ?>
    <div style="background: white; border-radius: 20px; padding: 20px; margin-bottom: 30px; box-shadow: var(--shadow-md);">
        <h3 style="color: var(--primary); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-crown" style="color: #FFD700;"></i> Top Performer Hari Ini
        </h3>
        
        <div style="display: flex; overflow-x: auto; gap: 16px; padding: 8px 0;">
            <?php foreach ($top_performers as $index => $tp): ?>
            <div style="flex: 0 0 250px; background: linear-gradient(135deg, <?= $index == 0 ? '#FFD700' : ($index == 1 ? '#C0C0C0' : ($index == 2 ? '#CD7F32' : 'var(--primary-soft)')) ?>, white); border-radius: 16px; padding: 16px; border-left: 6px solid <?= $index == 0 ? '#FFD700' : ($index == 1 ? '#C0C0C0' : ($index == 2 ? '#CD7F32' : 'var(--secondary)')) ?>;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <div style="width: 40px; height: 40px; background: <?= $index == 0 ? '#FFD700' : ($index == 1 ? '#C0C0C0' : ($index == 2 ? '#CD7F32' : 'var(--primary)')) ?>; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800;">
                        <?= $index + 1 ?>
                    </div>
                    <div style="font-weight: 700; color: var(--primary);"><?= htmlspecialchars($tp['nama_lengkap']) ?></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <div style="font-size: 11px; color: var(--text-muted);">Leads</div>
                        <div style="font-size: 18px; font-weight: 800;"><?= $tp['total_leads'] ?></div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: var(--text-muted);">Deal</div>
                        <div style="font-size: 18px; font-weight: 800; color: var(--success);"><?= $tp['total_deal'] ?></div>
                    </div>
                    <div style="grid-column: span 2;">
                        <div style="font-size: 11px; color: var(--text-muted);">Follow Up</div>
                        <div style="font-size: 16px; font-weight: 700;"><?= $tp['follow_up'] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <form method="GET" class="filter-form" id="filterForm">
            <select name="status" class="filter-select" style="max-width: 300px;">
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

    <!-- EXPORT BUTTONS -->
    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-bottom: 20px; flex-wrap: wrap;">
        <a href="#" onclick="openPremiumExportModal(); return false;" class="export-btn" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A); color: white; padding: 12px 24px; border-radius: 40px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-download"></i> Export Premium
        </a>
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
            
            // HAPUS DEBUG - SUDAH TIDAK PERLU
            // if (isset($_GET['debug']) && $_GET['debug'] == 1) { ... }
            
            // Tentukan marketing display - CLEAN VERSION
            $marketing_text = '-';
            $marketing_type = '';
            
            if (isset($lead['assigned_type']) && $lead['assigned_type'] === 'external') {
                if (!empty($lead['external_marketing_name'])) {
                    $marketing_text = $lead['external_marketing_name'];
                    $marketing_type = 'external';
                } else {
                    $marketing_text = 'Taufik Marie';
                    $marketing_type = 'external';
                }
            } 
            elseif (isset($lead['assigned_type']) && $lead['assigned_type'] === 'internal') {
                if (!empty($lead['internal_marketing_name'])) {
                    $marketing_text = $lead['internal_marketing_name'];
                    $marketing_type = 'internal';
                }
            } 
            elseif (!empty($lead['marketing_display']) && $lead['marketing_display'] !== '-') {
                $marketing_text = $lead['marketing_display'];
            }
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
                <?= htmlspecialchars($marketing_text) ?>
                <?php if ($marketing_type): ?>
                <br><small style="color: var(--text-muted); font-size: 10px;">(<?= $marketing_type ?>)</small>
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
            <td style="text-align: center; white-space: nowrap;">
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
        <p>© <?= date('Y') ?> LeadEngine - Manager Developer Dashboard v1.0</p>
        <p>Total Leads: <?= number_format($stats['total']) ?> | Hari Ini: <?= $stats['today'] ?></p>
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
// ===== FUNGSI EXPORT =====
function toggleExportDropdown() {
    document.querySelector('.export-dropdown')?.classList.toggle('active');
}

document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.export-dropdown');
    const button = dropdown?.querySelector('button');
    const content = dropdown?.querySelector('.export-dropdown-content');
    
    if (dropdown && button && !button.contains(event.target) && !content?.contains(event.target)) {
        dropdown.classList.remove('active');
    }
});

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
                                <div class="view-item-value"><strong>${name}</strong></div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-map-marker-alt"></i> Lokasi</div>
                                <div class="view-item-value">${l.location_display || l.location_key}</div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fab fa-whatsapp"></i> WhatsApp</div>
                                <div class="view-item-value"><a href="https://wa.me/${l.phone}" target="_blank" style="color: #25D366; text-decoration: none;">${l.phone}</a></div>
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
                        <div class="view-section-title"><i class="fas fa-tag"></i> Status & Program</div>
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
                                <div class="view-item-label"><i class="fas fa-calendar"></i> Tanggal Daftar</div>
                                <div class="view-item-value">${new Date(l.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="view-section">
                        <div class="view-section-title">
                            <i class="fas fa-user-tie"></i> Marketing 
                            <span style="background: var(--primary-soft); padding: 2px 8px; border-radius: 20px; font-size: 10px; margin-left: 8px; color: var(--primary);">${marketingType}</span>
                        </div>
                        <div class="view-grid">
                            <div class="view-item">
                                <div class="view-item-label"><i class="fas fa-user"></i> Nama Marketing</div>
                                <div class="view-item-value"><strong>${marketingName}</strong></div>
                            </div>
                            <div class="view-item">
                                <div class="view-item-label"><i class="fab fa-whatsapp"></i> Kontak Marketing</div>
                                <div class="view-item-value">
                                    ${marketingPhone !== '-' ? `<a href="https://wa.me/${marketingPhone}" target="_blank" style="color: #25D366; text-decoration: none;">${marketingPhone}</a>` : '-'}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    ${l.address ? `
                    <div class="view-section">
                        <div class="view-section-title"><i class="fas fa-map-pin"></i> Alamat</div>
                        <div style="background:white; padding:16px; border-radius:12px;">
                            ${l.address}${l.city ? ', ' + l.city : ''}
                        </div>
                    </div>` : ''}
                    
                    ${l.notes ? `
                    <div class="view-section">
                        <div class="view-section-title"><i class="fas fa-sticky-note"></i> Catatan</div>
                        <div style="background:white; padding:16px; border-radius:12px; white-space: pre-line; max-height: 200px; overflow-y: auto;">${l.notes}</div>
                    </div>` : ''}
                `;
            } else {
                document.getElementById('viewContent').innerHTML = '<div style="text-align: center; padding: 40px; color: var(--danger);"><i class="fas fa-exclamation-circle fa-3x"></i><p style="margin-top: 16px;">Gagal memuat data</p></div>';
            }
        })
        .catch((err) => {
            console.error('Error loading lead:', err);
            document.getElementById('viewContent').innerHTML = '<div style="text-align: center; padding: 40px; color: var(--danger);"><i class="fas fa-exclamation-circle fa-3x"></i><p style="margin-top: 16px;">Terjadi kesalahan: ' + err.message + '</p></div>';
        });
}

// ===== FUNGSI DEBUG =====
window.debugBooking = function() {
    console.log('=== 🔍 DEBUG INFO ===');
    console.log('currentUnits length:', currentUnits.length);
    console.log('currentUnits:', currentUnits);
    console.log('totalAvailableUnits:', totalAvailableUnits);
    console.log('isInternalMarketing:', isInternalMarketing);
    
    if (currentUnits.length > 0) {
        console.log('Sample unit IDs:', currentUnits.map(u => u.id));
        console.log('Sample unit data:', currentUnits[0]);
    }
    
    // Cek elemen HTML
    console.log('unitPagination:', document.getElementById('unitPagination'));
    console.log('bookingPagination:', document.getElementById('bookingPagination'));
    console.log('statsContainer:', document.getElementById('statsContainer'));
    
    return '✅ Debug info printed to console';
}

window.checkUnit = function(unitId) {
    const unit = currentUnits.find(u => u.id == unitId);
    console.log('🔍 Unit ' + unitId + ' found:', unit ? 'YES' : 'NO');
    if (unit) {
        console.log('📋 Unit data:', unit);
    } else {
        console.log('❌ Unit tidak ditemukan. Available IDs:', currentUnits.map(u => u.id));
    }
    return unit;
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
    
    const dateEl = document.querySelector('.date span');
    const timeEl = document.querySelector('.time span');
    
    if (dateEl) dateEl.textContent = now.toLocaleDateString('id-ID', options);
    if (timeEl) timeEl.textContent = now.toLocaleTimeString('id-ID', { hour12: false });
}

// ===== TOAST =====
function showToast(message, type = 'info') {
    let toast = document.querySelector('.toast-message');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-message';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.style.opacity = '1';
    toast.style.background = type === 'success' ? '#2A9D8F' : (type === 'error' ? '#D64F3C' : '#1B4A3C');
    
    setTimeout(() => {
        toast.style.opacity = '0';
    }, 3000);
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

// Export functions ke global
window.viewLead = viewLead;
window.openModal = openModal;
window.closeViewModal = closeViewModal;
window.openExportModal = openExportModal;
window.closeExportModal = closeExportModal;
window.openPremiumExportModal = openPremiumExportModal;
window.showToast = showToast;
</script>

<!-- FOOTER -->
<?php include 'includes/footer.php'; ?>