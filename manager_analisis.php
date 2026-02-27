<?php
/**
 * MANAGER_ANALISIS.PHP - TAUFIKMARIE.COM
 * Version: 2.0.0 - FIXED BLANK PAGE & ERROR HANDLING
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan error reporting untuk debugging (akan dimatikan setelah fix)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/manager_analisis_error.log');

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
    die("Database connection failed: " . print_r(PDO::errorInfo(), true));
}

// ========== AMBIL SEMUA DEVELOPER ==========
try {
    $developers = $conn->query("
        SELECT id, username, nama_lengkap 
        FROM users 
        WHERE role = 'developer' AND is_active = 1 
        ORDER BY nama_lengkap ASC
    ")->fetchAll();
} catch (Exception $e) {
    $developers = [];
    error_log("Error loading developers: " . $e->getMessage());
}

// ========== FILTER ==========
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_developer = isset($_GET['developer']) ? (int)$_GET['developer'] : 0;

// Validasi month dan year
if ($month < 1 || $month > 12) $month = (int)date('m');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

// ========== DATA BULAN INI ==========
$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

// Pastikan tanggal valid
if (!$start_date || !$end_date) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

$deal_statuses = ['Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'];
$deal_placeholders = implode(',', array_fill(0, count($deal_statuses), '?'));

$params = $deal_statuses;
$developer_condition = "";

if ($selected_developer > 0) {
    $developer_condition = "AND ditugaskan_ke = ?";
    $params[] = $selected_developer;
}

// ========== STATISTIK UTAMA ==========
$main_stats = [
    'total_leads' => 0,
    'total_deal' => 0,
    'total_negatif' => 0,
    'avg_score' => 0
];

try {
    $main_sql = "
        SELECT 
            COUNT(*) as total_leads,
            SUM(CASE WHEN status IN ($deal_placeholders) THEN 1 ELSE 0 END) as total_deal,
            SUM(CASE WHEN status IN ('Tolak Slik', 'Tidak Minat', 'Batal') THEN 1 ELSE 0 END) as total_negatif,
            AVG(lead_score) as avg_score
        FROM leads 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        $developer_condition
    ";

    $main_params = array_merge($params, [$start_date, $end_date]);
    $stmt = $conn->prepare($main_sql);
    $stmt->execute($main_params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $main_stats = [
            'total_leads' => (int)($result['total_leads'] ?? 0),
            'total_deal' => (int)($result['total_deal'] ?? 0),
            'total_negatif' => (int)($result['total_negatif'] ?? 0),
            'avg_score' => round((float)($result['avg_score'] ?? 0), 1)
        ];
    }
} catch (Exception $e) {
    error_log("Error in main stats: " . $e->getMessage());
}

// ========== DATA PER DEVELOPER ==========
$dev_stats = [];
foreach ($developers as $dev) {
    try {
        $dev_sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status IN ($deal_placeholders) THEN 1 ELSE 0 END) as deal
            FROM leads 
            WHERE ditugaskan_ke = ?
            AND DATE(created_at) BETWEEN ? AND ?
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ";
        $dev_stmt = $conn->prepare($dev_sql);
        $dev_stmt->execute([$dev['id'], $start_date, $end_date]);
        $dev_stats[$dev['id']] = $dev_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $dev_stats[$dev['id']] = ['total' => 0, 'deal' => 0];
        error_log("Error in dev stats for {$dev['id']}: " . $e->getMessage());
    }
}

// ========== DATA PER MARKETING ==========
$marketing_stats = [];
try {
    $marketing_sql = "
        SELECT 
            m.id,
            m.nama_lengkap,
            u.nama_lengkap as developer_name,
            COUNT(l.id) as total_leads,
            SUM(CASE WHEN l.status IN ($deal_placeholders) THEN 1 ELSE 0 END) as total_deal,
            AVG(l.lead_score) as avg_score
        FROM marketing_team m
        LEFT JOIN users u ON m.developer_id = u.id
        LEFT JOIN leads l ON m.id = l.assigned_marketing_team_id 
            AND DATE(l.created_at) BETWEEN ? AND ?
            AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
        WHERE m.is_active = 1
        GROUP BY m.id
        HAVING total_leads > 0
        ORDER BY total_deal DESC
    ";

    $marketing_stmt = $conn->prepare($marketing_sql);
    $marketing_stmt->execute([$start_date, $end_date]);
    $marketing_stats = $marketing_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $marketing_stats = [];
    error_log("Error in marketing stats: " . $e->getMessage());
}

// ========== DATA HARIAN UNTUK CHART ==========
$daily_data = [];
$daily_labels = [];
$daily_totals = [];
$daily_deals = [];

try {
    $daily_sql = "
        SELECT 
            DAY(created_at) as day,
            COUNT(*) as total,
            SUM(CASE WHEN status IN ($deal_placeholders) THEN 1 ELSE 0 END) as deal
        FROM leads 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        GROUP BY DAY(created_at)
        ORDER BY day ASC
    ";

    $daily_stmt = $conn->prepare($daily_sql);
    $daily_stmt->execute([$start_date, $end_date]);
    $daily_data = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Siapkan data untuk chart
    $days_in_month = date('t', strtotime($start_date));
    $daily_labels = range(1, $days_in_month);
    $daily_totals = array_fill(1, $days_in_month, 0);
    $daily_deals = array_fill(1, $days_in_month, 0);
    
    foreach ($daily_data as $d) {
        $day = (int)$d['day'];
        $daily_totals[$day] = (int)($d['total'] ?? 0);
        $daily_deals[$day] = (int)($d['deal'] ?? 0);
    }
    
    // Convert ke array untuk JSON
    $daily_labels = array_values($daily_labels);
    $daily_totals = array_values($daily_totals);
    $daily_deals = array_values($daily_deals);
    
} catch (Exception $e) {
    error_log("Error in daily stats: " . $e->getMessage());
}

// ========== PASS DATA KE JAVASCRIPT ==========
echo '<script>';
echo 'window.chartLabels = ' . json_encode($daily_labels) . ';';
echo 'window.chartTotals = ' . json_encode($daily_totals) . ';';
echo 'window.chartDeals = ' . json_encode($daily_deals) . ';';
echo 'window.monthName = "' . date('F Y', strtotime($start_date)) . '";';
echo '</script>';

$page_title = 'Analisis Bulanan';
$page_subtitle = date('F Y', strtotime($start_date));
$page_icon = 'fas fa-chart-pie';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== ANALISIS PAGE STYLES ===== */
.analisis-header {
    background: linear-gradient(135deg, #2A9D8F, #40BEB0);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    color: white;
    box-shadow: 0 10px 25px rgba(42,157,143,0.3);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border-left: 6px solid;
}

.stat-card.total { border-left-color: #1B4A3C; }
.stat-card.deal { border-left-color: #2A9D8F; }
.stat-card.negatif { border-left-color: #D64F3C; }
.stat-card.score { border-left-color: #4A90E2; }

.stat-value {
    font-size: 32px;
    font-weight: 800;
    line-height: 1.2;
}

.stat-label {
    font-size: 13px;
    color: #7A8A84;
    margin-top: 4px;
}

.chart-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.chart-title {
    font-size: 16px;
    font-weight: 700;
    color: #1B4A3C;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-wrapper {
    height: 300px;
    position: relative;
}

.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #E7F3EF;
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    color: #1B4A3C;
    font-size: 13px;
    white-space: nowrap;
}

td {
    padding: 12px;
    border-bottom: 1px solid #E0DAD3;
    font-size: 14px;
}

tr:hover td {
    background: #F5F3F0;
}

.progress-bar {
    height: 8px;
    background: #E0DAD3;
    border-radius: 4px;
    overflow: hidden;
    width: 120px;
}

.progress-fill {
    height: 100%;
    background: #2A9D8F;
    border-radius: 4px;
}

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

.filter-select {
    flex: 1;
    min-width: 150px;
    padding: 12px 16px;
    border: 2px solid #E0DAD3;
    border-radius: 12px;
    font-size: 14px;
    background: white;
}

.filter-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, #2A9D8F, #40BEB0);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}

.filter-btn.reset {
    background: #E0DAD3;
    color: #1A2A24;
}

.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: #7A8A84;
    font-size: 12px;
    border-top: 1px solid #E0DAD3;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .stat-value {
        font-size: 24px;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .filter-select {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
        display: flex;
        gap: 10px;
    }
    
    .filter-btn {
        flex: 1;
    }
    
    .progress-bar {
        width: 80px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .stat-value {
        font-size: 20px;
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
    
    <!-- HEADER -->
    <div class="analisis-header">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 30px; display: flex; align-items: center; justify-content: center; font-size: 28px;">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div>
                <h3 style="font-size: 20px; font-weight: 800; margin-bottom: 4px;">Analisis Bulan <?= date('F Y', strtotime($start_date)) ?></h3>
                <p style="opacity: 0.9;">Ringkasan kinerja leads dan marketing</p>
            </div>
        </div>
    </div>
    
    <!-- FILTER -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <select name="year" class="filter-select">
                <?php for ($y = date('Y'); $y >= date('Y')-2; $y--): ?>
                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            
            <select name="month" class="filter-select">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
            
            <select name="developer" class="filter-select">
                <option value="">Semua Developer</option>
                <?php foreach ($developers as $dev): ?>
                <option value="<?= $dev['id'] ?>" <?= $selected_developer == $dev['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dev['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
                <a href="?" class="filter-btn reset">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-value"><?= number_format($main_stats['total_leads']) ?></div>
            <div class="stat-label">Total Leads</div>
        </div>
        <div class="stat-card deal">
            <div class="stat-value" style="color: #2A9D8F;"><?= number_format($main_stats['total_deal']) ?></div>
            <div class="stat-label">Total Deal</div>
        </div>
        <div class="stat-card negatif">
            <div class="stat-value" style="color: #D64F3C;"><?= number_format($main_stats['total_negatif']) ?></div>
            <div class="stat-label">Lead Negatif</div>
        </div>
        <div class="stat-card score">
            <div class="stat-value"><?= $main_stats['avg_score'] ?></div>
            <div class="stat-label">Rata-rata Score</div>
        </div>
    </div>
    
    <!-- CHART -->
    <div class="chart-container">
        <div class="chart-title">
            <i class="fas fa-chart-line" style="color: #D64F3C;"></i>
            Tren Harian - <?= date('F Y', strtotime($start_date)) ?>
        </div>
        <div class="chart-wrapper">
            <canvas id="dailyChart"></canvas>
        </div>
        <?php if (empty($daily_totals) || array_sum($daily_totals) == 0): ?>
        <p style="text-align: center; color: #7A8A84; margin-top: 10px;">
            <i class="fas fa-info-circle"></i> Tidak ada data untuk periode ini
        </p>
        <?php endif; ?>
    </div>
    
    <!-- PER DEVELOPER -->
    <div class="table-container">
        <h3 style="margin-bottom: 15px; color: #1B4A3C; font-size: 18px;">Statistik per Developer</h3>
        <?php if (empty($developers)): ?>
        <p style="text-align: center; padding: 30px; color: #7A8A84;">Tidak ada developer aktif</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Developer</th>
                    <th>Total Leads</th>
                    <th>Total Deal</th>
                    <th>Conversion</th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($developers as $dev): 
                    $dev_total = isset($dev_stats[$dev['id']]['total']) ? (int)$dev_stats[$dev['id']]['total'] : 0;
                    $dev_deal = isset($dev_stats[$dev['id']]['deal']) ? (int)$dev_stats[$dev['id']]['deal'] : 0;
                    $dev_conv = $dev_total > 0 ? round(($dev_deal / $dev_total) * 100, 1) : 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($dev['nama_lengkap']) ?></strong></td>
                    <td><?= number_format($dev_total) ?></td>
                    <td><span style="color: #2A9D8F; font-weight: 600;"><?= number_format($dev_deal) ?></span></td>
                    <td><?= $dev_conv ?>%</td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $dev_conv ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- PER MARKETING -->
    <div class="table-container">
        <h3 style="margin-bottom: 15px; color: #1B4A3C; font-size: 18px;">Kinerja Marketing</h3>
        <?php if (empty($marketing_stats)): ?>
        <p style="text-align: center; padding: 30px; color: #7A8A84;">Tidak ada aktivitas marketing di bulan ini</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Marketing</th>
                    <th>Developer</th>
                    <th>Total Leads</th>
                    <th>Total Deal</th>
                    <th>Conversion</th>
                    <th>Rata Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($marketing_stats as $m): 
                    $conv = $m['total_leads'] > 0 ? round(($m['total_deal'] / $m['total_leads']) * 100, 1) : 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($m['nama_lengkap'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($m['developer_name'] ?? '-') ?></td>
                    <td><?= number_format($m['total_leads'] ?? 0) ?></td>
                    <td><span style="color: #2A9D8F; font-weight: 600;"><?= number_format($m['total_deal'] ?? 0) ?></span></td>
                    <td><?= $conv ?>%</td>
                    <td><?= round($m['avg_score'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> TaufikMarie.com - Analisis Bulanan v2.0</p>
    </div>
    
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// ========== CHART INIT ==========
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('dailyChart');
    
    if (!ctx) {
        console.error('Canvas element not found');
        return;
    }
    
    const labels = window.chartLabels || [];
    const totals = window.chartTotals || [];
    const deals = window.chartDeals || [];
    
    console.log('Chart data loaded:', { labels, totals, deals });
    
    // Jika tidak ada data, tampilkan pesan
    if (labels.length === 0 || totals.length === 0) {
        console.log('No chart data available');
        return;
    }
    
    try {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels.map(d => 'Tgl ' + d),
                datasets: [
                    {
                        label: 'Total Leads',
                        data: totals,
                        borderColor: '#1B4A3C',
                        backgroundColor: 'rgba(27,74,60,0.1)',
                        tension: 0.3,
                        borderWidth: 2,
                        pointBackgroundColor: '#1B4A3C',
                        pointBorderColor: 'white',
                        pointRadius: 4
                    },
                    {
                        label: 'Deal',
                        data: deals,
                        borderColor: '#2A9D8F',
                        backgroundColor: 'rgba(42,157,143,0.1)',
                        tension: 0.3,
                        borderWidth: 2,
                        pointBackgroundColor: '#2A9D8F',
                        pointBorderColor: 'white',
                        pointRadius: 4
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
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(27,74,60,0.9)',
                        titleColor: 'white',
                        bodyColor: 'rgba(255,255,255,0.9)'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            callback: function(value) {
                                if (Math.floor(value) === value) {
                                    return value;
                                }
                            }
                        }
                    }
                }
            }
        });
        console.log('Chart initialized successfully');
    } catch (error) {
        console.error('Chart initialization error:', error);
    }
});

// ========== DATE TIME ==========
function updateDateTime() {
    const now = new Date();
    const dateEl = document.querySelector('.date span');
    const timeEl = document.querySelector('.time span');
    
    if (dateEl) {
        dateEl.textContent = now.toLocaleDateString('id-ID', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    }
    
    if (timeEl) {
        timeEl.textContent = now.toLocaleTimeString('id-ID', { 
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
}

setInterval(updateDateTime, 1000);
updateDateTime();

// ========== PREVENT FORM RESUBMISSION ==========
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php include 'includes/footer.php'; ?>