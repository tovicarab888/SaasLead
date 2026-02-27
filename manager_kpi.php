<?php
/**
 * MANAGER_KPI.PHP - TAUFIKMARIE.COM
 * Version: 1.0.0 - KPI MARKETING UNTUK MANAGER (SEMUA DEVELOPER)
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

// ========== AMBIL SEMUA DEVELOPER ==========
$developers = $conn->query("
    SELECT id, username, nama_lengkap 
    FROM users 
    WHERE role = 'developer' AND is_active = 1 
    ORDER BY nama_lengkap ASC
")->fetchAll();

// ========== FILTER ==========
$selected_developer = isset($_GET['developer']) ? (int)$_GET['developer'] : 0;
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// ========== AMBIL SEMUA MARKETING ==========
$marketing_list = [];

if ($selected_developer > 0) {
    $stmt = $conn->prepare("
        SELECT m.*, u.nama_lengkap as developer_name 
        FROM marketing_team m
        LEFT JOIN users u ON m.developer_id = u.id
        WHERE m.developer_id = ? AND m.is_active = 1
        ORDER BY m.nama_lengkap ASC
    ");
    $stmt->execute([$selected_developer]);
    $marketing_list = $stmt->fetchAll();
} else {
    $marketing_list = $conn->query("
        SELECT m.*, u.nama_lengkap as developer_name 
        FROM marketing_team m
        LEFT JOIN users u ON m.developer_id = u.id
        WHERE m.is_active = 1
        ORDER BY u.nama_lengkap, m.nama_lengkap
    ")->fetchAll();
}

// ========== HITUNG KPI UNTUK SETIAP MARKETING ==========
$kpi_data = [];
$total_all_leads = 0;
$total_all_deal = 0;
$total_all_followup = 0;

foreach ($marketing_list as $marketing) {
    $kpi = getMarketingKPI($conn, $marketing['id'], $start_date, $end_date);
    $kpi['nama_lengkap'] = $marketing['nama_lengkap'];
    $kpi['developer_name'] = $marketing['developer_name'] ?? 'Unknown';
    $kpi['phone'] = $marketing['phone'];
    $kpi['username'] = $marketing['username'];
    $kpi_data[] = $kpi;
    
    $total_all_leads += $kpi['total_leads_diterima'];
    $total_all_deal += $kpi['total_deal'];
    $total_all_followup += $kpi['total_follow_up'];
}

$total_conversion = $total_all_leads > 0 ? round(($total_all_deal / $total_all_leads) * 100, 2) : 0;

$page_title = 'KPI Marketing';
$page_subtitle = 'Key Performance Indicators Semua Marketing';
$page_icon = 'fas fa-chart-bar';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== KPI PAGE STYLES ===== */
.kpi-header {
    background: linear-gradient(135deg, #4A90E2, #6DA5F0);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    color: white;
    box-shadow: 0 10px 25px rgba(74,144,226,0.3);
}

.kpi-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-top: 20px;
}

.kpi-summary-item {
    background: rgba(255,255,255,0.15);
    border-radius: 16px;
    padding: 15px;
    text-align: center;
}

.kpi-summary-value {
    font-size: 28px;
    font-weight: 800;
}

.kpi-summary-label {
    font-size: 12px;
    opacity: 0.9;
    margin-top: 4px;
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

.filter-select, .filter-input {
    flex: 1;
    min-width: 150px;
    padding: 12px 16px;
    border: 2px solid #E0DAD3;
    border-radius: 12px;
    font-size: 14px;
}

.filter-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, #4A90E2, #6DA5F0);
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

.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

th {
    background: #E7F3EF;
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    color: #1B4A3C;
    font-size: 12px;
    white-space: nowrap;
}

td {
    padding: 12px;
    border-bottom: 1px solid #E0DAD3;
    font-size: 13px;
}

tr:hover td {
    background: #F5F3F0;
}

.developer-badge {
    background: #4A90E2;
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    display: inline-block;
}

.score-hot {
    background: #ffebee;
    color: #D64F3C;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 20px;
}

.score-warm {
    background: #fff3e0;
    color: #B87C00;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 20px;
}

.score-cold {
    background: #e3f2fd;
    color: #4A90E2;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 20px;
}

.text-success {
    color: #2A9D8F;
    font-weight: 700;
}

.text-danger {
    color: #D64F3C;
    font-weight: 700;
}

.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: #7A8A84;
    font-size: 12px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .kpi-summary-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .kpi-summary-value {
        font-size: 22px;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .filter-select, .filter-input {
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
    
    <!-- KPI HEADER -->
    <div class="kpi-header">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 25px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div>
                <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 4px;">Ringkasan KPI Marketing</h3>
                <p style="opacity: 0.9; font-size: 13px;">Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></p>
            </div>
        </div>
        
        <div class="kpi-summary-grid">
            <div class="kpi-summary-item">
                <div class="kpi-summary-value"><?= count($marketing_list) ?></div>
                <div class="kpi-summary-label">Marketing Aktif</div>
            </div>
            <div class="kpi-summary-item">
                <div class="kpi-summary-value"><?= number_format($total_all_leads) ?></div>
                <div class="kpi-summary-label">Total Leads</div>
            </div>
            <div class="kpi-summary-item">
                <div class="kpi-summary-value"><?= number_format($total_all_deal) ?></div>
                <div class="kpi-summary-label">Total Deal</div>
            </div>
            <div class="kpi-summary-item">
                <div class="kpi-summary-value"><?= $total_conversion ?>%</div>
                <div class="kpi-summary-label">Conversion Rate</div>
            </div>
        </div>
    </div>
    
    <!-- FILTER BAR -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <select name="developer" class="filter-select">
                <option value="">Semua Developer</option>
                <?php foreach ($developers as $dev): ?>
                <option value="<?= $dev['id'] ?>" <?= $selected_developer == $dev['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dev['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" name="start_date" class="filter-input" value="<?= $start_date ?>">
            <input type="date" name="end_date" class="filter-input" value="<?= $end_date ?>">
            
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
    
    <!-- TABLE KPI -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Marketing</th>
                    <th>Developer</th>
                    <th>Kontak</th>
                    <th>Lead<br>Historis</th>
                    <th>Lead<br>Baru</th>
                    <th>Follow<br>Up</th>
                    <th>Deal</th>
                    <th>Negatif</th>
                    <th>Conv<br>Rate</th>
                    <th>Hot</th>
                    <th>Warm</th>
                    <th>Cold</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kpi_data)): ?>
                <tr>
                    <td colspan="12" style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox fa-3x" style="color: #ccc; margin-bottom: 10px;"></i>
                        <p>Tidak ada data marketing</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($kpi_data as $k): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($k['nama_lengkap'] ?? '-') ?></strong><br><small>@<?= htmlspecialchars($k['username'] ?? '') ?></small></td>
                        <td><span class="developer-badge"><?= htmlspecialchars($k['developer_name'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($k['phone'] ?? '-') ?></td>
                        <td><?= $k['total_leads_assigned'] ?? 0 ?></td>
                        <td><strong><?= $k['total_leads_diterima'] ?? 0 ?></strong></td>
                        <td><?= $k['total_follow_up'] ?? 0 ?></td>
                        <td class="text-success"><strong><?= $k['total_deal'] ?? 0 ?></strong></td>
                        <td class="text-danger"><strong><?= $k['total_negatif'] ?? 0 ?></strong></td>
                        <td><strong><?= $k['conversion_rate'] ?? 0 ?>%</strong></td>
                        <td><span class="score-hot"><?= $k['score_distribution']['hot'] ?? 0 ?></span></td>
                        <td><span class="score-warm"><?= $k['score_distribution']['warm'] ?? 0 ?></span></td>
                        <td><span class="score-cold"><?= $k['score_distribution']['cold'] ?? 0 ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- TOTAL ROW -->
                    <tr style="background: #E7F3EF; font-weight: 700;">
                        <td colspan="4"><strong>TOTAL</strong></td>
                        <td><strong><?= $total_all_leads ?></strong></td>
                        <td><strong><?= $total_all_followup ?></strong></td>
                        <td><strong class="text-success"><?= $total_all_deal ?></strong></td>
                        <td colspan="5"></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> TaufikMarie.com - KPI Marketing Manager</p>
    </div>
    
</div>

<script>
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