<?php
/**
 * MARKETING_KPI.PHP - LEADENGINE
 * Version: 9.1.0 - FIXED: Akses untuk Manager Developer
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

if (!checkAuth()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// ===== FIX: Tambahkan isManagerDeveloper() =====
if (!isAdmin() && !isManager() && !isDeveloper() && !isManagerDeveloper()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Admin, Manager, Developer, dan Manager Developer.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$current_role = getCurrentRole();
$current_user_id = $_SESSION['user_id'] ?? 0;
$developer_id = 0;

// ===== FIX: Tambahkan logic untuk Manager Developer =====
if (isDeveloper()) {
    $developer_id = $current_user_id;
} elseif (isManagerDeveloper()) {
    $developer_id = $_SESSION['developer_id'] ?? 0;
    if ($developer_id <= 0) {
        die("Error: Developer ID tidak ditemukan untuk Manager Developer");
    }
} elseif (isAdmin() || isManager()) {
    $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
    if ($developer_id <= 0) {
        $stmt = $conn->query("SELECT id FROM users WHERE role = 'developer' AND is_active = 1 ORDER BY id LIMIT 1");
        $dev = $stmt->fetch();
        $developer_id = $dev['id'] ?? 0;
    }
}

if ($developer_id <= 0) {
    die("Developer ID tidak valid");
}

$stmt = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
$stmt->execute([$developer_id]);
$developer_name = $stmt->fetchColumn() ?: 'Developer';

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$kpi_data = getAllMarketingKPI($conn, $developer_id, $start_date, $end_date);

$page_title = 'KPI Marketing';
$page_subtitle = 'Monitoring Performa Marketing';
$page_icon = 'fas fa-chart-bar';

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

.filter-select, .filter-input {
    flex: 1;
    min-width: 150px;
    padding: 14px 18px;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-size: 14px;
    background: white;
}

.filter-select:focus, .filter-input:focus {
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

/* ===== EXPORT BUTTONS ===== */
.export-buttons {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.export-btn {
    padding: 12px 24px;
    border-radius: 40px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    white-space: nowrap;
}

.export-btn.pdf {
    background: linear-gradient(135deg, #D64F3C, #FF6B4A);
    color: white;
    box-shadow: 0 4px 12px rgba(214,79,60,0.2);
}

.export-btn.excel {
    background: linear-gradient(135deg, #2A9D8F, #40BEB0);
    color: white;
    box-shadow: 0 4px 12px rgba(42,157,143,0.2);
}

.export-btn.csv {
    background: linear-gradient(135deg, #4A90E2, #6DA5F0);
    color: white;
    box-shadow: 0 4px 12px rgba(74,144,226,0.2);
}

.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

/* ===== STATS CARDS ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--surface);
    border-radius: 20px;
    padding: 20px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
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
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 32px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

/* ===== TABLE UNTUK DESKTOP ===== */
.desktop-table {
    display: block;
}

.table-container {
    background: var(--surface);
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    margin-bottom: 30px;
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
    gap: 16px;
}

.table-header h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-header h3 i {
    color: var(--secondary);
}

.table-badge {
    background: #E7F3EF;
    color: var(--primary);
    padding: 6px 16px;
    border-radius: 40px;
    font-size: 14px;
    font-weight: 600;
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.kpi-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.kpi-table th {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 16px 12px;
    text-align: left;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.kpi-table th i {
    margin-right: 6px;
}

.kpi-table td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border);
    background: white;
    white-space: nowrap;
}

.kpi-table tbody tr:hover td {
    background: #f8fafc;
}

/* ===== CARD VIEW UNTUK MOBILE ===== */
.mobile-cards {
    display: none;
}

.marketing-card {
    background: white;
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border-left: 6px solid var(--secondary);
}

.marketing-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
}

.marketing-card-name {
    font-weight: 700;
    font-size: 16px;
    color: var(--primary);
}

.marketing-card-username {
    font-size: 12px;
    color: var(--text-muted);
}

.marketing-card-status {
    padding: 4px 12px;
    border-radius: 40px;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

.marketing-card-status.active {
    background-color: #2A9D8F;
}

.marketing-card-status.inactive {
    background-color: #D64F3C;
}

.marketing-card-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
}

.marketing-card-label {
    color: var(--text-muted);
    font-weight: 500;
}

.marketing-card-value {
    font-weight: 700;
}

.marketing-card-value.success {
    color: #2A9D8F;
}

.marketing-card-value.danger {
    color: #D64F3C;
}

.marketing-card-scores {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    padding-top: 8px;
    border-top: 1px solid var(--border);
}

.score-pill {
    flex: 1;
    text-align: center;
    padding: 4px 0;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

.score-pill.hot {
    background-color: #D64F3C;
}

.score-pill.warm {
    background-color: #E9C46A;
    color: #1A2A24;
}

.score-pill.cold {
    background-color: #4A90E2;
}

/* ===== BADGES ===== */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    color: white;
}

.status-badge.active {
    background-color: #2A9D8F;
}

.status-badge.inactive {
    background-color: #D64F3C;
}

.score-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    min-width: 35px;
    text-align: center;
    color: white;
}

.score-hot {
    background-color: #D64F3C;
}

.score-warm {
    background-color: #E9C46A;
    color: #1A2A24;
}

.score-cold {
    background-color: #4A90E2;
}

.highlight-number {
    font-weight: 700;
    font-size: 15px;
}

.text-success {
    color: #2A9D8F;
}

.text-danger {
    color: #D64F3C;
}

/* ===== TOTAL ROW ===== */
.total-row {
    background: linear-gradient(135deg, #E7F3EF, #d4e8e0) !important;
    font-weight: 700;
}

.total-row td {
    background: transparent !important;
    border-top: 2px solid var(--primary);
}

/* ===== SCROLL HINT ===== */
.scroll-hint {
    text-align: center;
    color: #7A8A84;
    font-size: 13px;
    margin-top: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
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

/* ===== DESKTOP ===== */
@media (min-width: 1025px) {
    .desktop-table {
        display: block;
    }
    .mobile-cards {
        display: none;
    }
}

/* ===== TABLET ===== */
@media (min-width: 769px) and (max-width: 1024px) {
    .desktop-table {
        display: block;
    }
    .mobile-cards {
        display: none;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .kpi-table {
        font-size: 13px;
    }
    
    .kpi-table th,
    .kpi-table td {
        padding: 12px 8px;
    }
}

/* ===== MOBILE ===== */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 16px !important;
    }
    
    .top-bar {
        flex-direction: column;
        align-items: flex-start;
        padding: 16px;
    }
    
    .datetime {
        width: 100%;
        justify-content: space-between;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .filter-select, .filter-input {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
    }
    
    .filter-btn {
        flex: 1;
    }
    
    .export-buttons {
        flex-direction: row;
        width: 100%;
        gap: 8px;
    }
    
    .export-btn {
        flex: 1;
        padding: 12px 16px;
        font-size: 13px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px;
    }
    
    .stat-card {
        padding: 16px;
    }
    
    .stat-value {
        font-size: 24px;
    }
    
    /* ===== SEMBUNYIKAN TABEL, TAMPILKAN CARD ===== */
    .desktop-table {
        display: none;
    }
    
    .mobile-cards {
        display: block;
    }
    
    .table-header {
        margin-bottom: 16px;
    }
    
    .table-header h3 {
        font-size: 18px;
    }
    
    .table-badge {
        font-size: 12px;
        padding: 4px 12px;
    }
}

/* ===== SMALL MOBILE ===== */
@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px;
    }
    
    .stat-card {
        padding: 12px;
    }
    
    .stat-icon {
        font-size: 22px;
        margin-bottom: 8px;
    }
    
    .stat-label {
        font-size: 11px;
    }
    
    .stat-value {
        font-size: 20px;
    }
    
    .export-buttons {
        flex-direction: column;
    }
    
    .export-btn {
        width: 100%;
    }
    
    .marketing-card {
        padding: 14px;
    }
    
    .marketing-card-name {
        font-size: 15px;
    }
    
    .marketing-card-row {
        font-size: 12px;
    }
}

/* ===== EXTRA SMALL ===== */
@media (max-width: 360px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 6px;
    }
    
    .stat-card {
        padding: 10px;
    }
    
    .stat-value {
        font-size: 18px;
    }
    
    .marketing-card {
        padding: 12px;
    }
    
    .marketing-card-name {
        font-size: 14px;
    }
    
    .marketing-card-row {
        font-size: 11px;
    }
    
    .score-pill {
        font-size: 10px;
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
                <span><?= $page_subtitle ?> - <?= htmlspecialchars($developer_name) ?></span>
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
            <?php if (isAdmin() || isManager()): ?>
            <select name="developer_id" class="filter-select">
                <?php
                $devs = $conn->query("SELECT id, nama_lengkap FROM users WHERE role = 'developer' AND is_active = 1 ORDER BY nama_lengkap");
                while ($dev = $devs->fetch()) {
                    $selected = ($dev['id'] == $developer_id) ? 'selected' : '';
                    echo '<option value="' . $dev['id'] . '" ' . $selected . '>' . htmlspecialchars($dev['nama_lengkap']) . '</option>';
                }
                ?>
            </select>
            <?php endif; ?>
            
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
    
    <!-- EXPORT BUTTONS -->
    <div class="export-buttons">
        <a href="api/export_kpi_pdf.php?developer_id=<?= $developer_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&format=pdf" 
           class="export-btn pdf">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
        <a href="api/export_kpi_pdf.php?developer_id=<?= $developer_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&format=excel" 
           class="export-btn excel">
            <i class="fas fa-file-excel"></i> Excel
        </a>
        <a href="api/export_kpi_pdf.php?developer_id=<?= $developer_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&format=csv" 
           class="export-btn csv">
            <i class="fas fa-file-csv"></i> CSV
        </a>
    </div>
    
    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Marketing</div>
            <div class="stat-value"><?= count($kpi_data['marketing']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
            <div class="stat-label">Lead Masuk</div>
            <div class="stat-value"><?= $kpi_data['total']['total_leads'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle" style="color: #2A9D8F;"></i></div>
            <div class="stat-label">Total Deal</div>
            <div class="stat-value"><?= $kpi_data['total']['total_deal'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-label">Rata-rata Conv</div>
            <div class="stat-value"><?= $kpi_data['total']['avg_conversion'] ?>%</div>
        </div>
    </div>
    
    <!-- DESKTOP TABLE VIEW -->
    <div class="desktop-table">
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-chart-bar"></i> Detail KPI Marketing</h3>
                <span class="table-badge"><?= count($kpi_data['marketing']) ?> Marketing</span>
            </div>
            
            <?php if (empty($kpi_data['marketing'])): ?>
            <div style="text-align: center; padding: 60px; background: #f9f9f9; border-radius: 20px;">
                <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                <h3 style="color: #666;">Belum Ada Data Marketing</h3>
                <p style="color: #999;">Tambah marketing terlebih dahulu di halaman Marketing Team</p>
            </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="kpi-table">
                        <thead>
                            <tr>
                                <th>Marketing</th>
                                <th>Kontak</th>
                                <th>Status</th>
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
                            <?php 
                            $total_negatif = 0;
                            foreach ($kpi_data['marketing'] as $m): 
                                $status_class = ($m['is_active'] ?? 1) ? 'active' : 'inactive';
                                $status_text = ($m['is_active'] ?? 1) ? 'Aktif' : 'Nonaktif';
                                $hot = $m['score_distribution']['hot'] ?? 0;
                                $warm = $m['score_distribution']['warm'] ?? 0;
                                $cold = $m['score_distribution']['cold'] ?? 0;
                                $total_negatif += $m['total_negatif'] ?? 0;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($m['nama_lengkap'] ?? '-') ?></strong><br><small>@<?= htmlspecialchars($m['username'] ?? '') ?></small></td>
                                <td><?= htmlspecialchars($m['phone'] ?? '-') ?><br><small>ID: <?= $m['marketing_id'] ?></small></td>
                                <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                                <td class="highlight-number"><?= $m['total_leads_assigned'] ?? 0 ?></td>
                                <td class="highlight-number"><strong><?= $m['total_leads_diterima'] ?? 0 ?></strong></td>
                                <td class="highlight-number"><?= $m['total_follow_up'] ?? 0 ?></td>
                                <td class="highlight-number text-success"><strong><?= $m['total_deal'] ?? 0 ?></strong></td>
                                <td class="highlight-number text-danger"><strong><?= $m['total_negatif'] ?? 0 ?></strong></td>
                                <td class="highlight-number"><strong><?= $m['conversion_rate'] ?? 0 ?>%</strong></td>
                                <td><span class="score-badge score-hot"><?= $hot ?></span></td>
                                <td><span class="score-badge score-warm"><?= $warm ?></span></td>
                                <td><span class="score-badge score-cold"><?= $cold ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="total-row">
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
                                <td>-</td>
                                <td><strong><?= $kpi_data['total']['total_leads'] ?></strong></td>
                                <td><strong><?= $kpi_data['total']['total_follow_up'] ?></strong></td>
                                <td><strong class="text-success"><?= $kpi_data['total']['total_deal'] ?></strong></td>
                                <td><strong class="text-danger"><?= $total_negatif ?></strong></td>
                                <td><strong><?= $kpi_data['total']['avg_conversion'] ?>%</strong></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i> Geser tabel untuk melihat semua kolom
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MOBILE CARD VIEW -->
    <div class="mobile-cards">
        <div class="table-header">
            <h3><i class="fas fa-chart-bar"></i> KPI Marketing</h3>
            <span class="table-badge"><?= count($kpi_data['marketing']) ?> Marketing</span>
        </div>
        
        <?php if (empty($kpi_data['marketing'])): ?>
        <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 20px;">
            <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
            <p style="color: #666;">Belum Ada Data Marketing</p>
        </div>
        <?php else: ?>
            <?php foreach ($kpi_data['marketing'] as $m): 
                $status_class = ($m['is_active'] ?? 1) ? 'active' : 'inactive';
                $status_text = ($m['is_active'] ?? 1) ? 'Aktif' : 'Nonaktif';
                $hot = $m['score_distribution']['hot'] ?? 0;
                $warm = $m['score_distribution']['warm'] ?? 0;
                $cold = $m['score_distribution']['cold'] ?? 0;
            ?>
            <div class="marketing-card">
                <div class="marketing-card-header">
                    <div>
                        <span class="marketing-card-name"><?= htmlspecialchars($m['nama_lengkap'] ?? '-') ?></span>
                        <div class="marketing-card-username">@<?= htmlspecialchars($m['username'] ?? '') ?></div>
                    </div>
                    <span class="marketing-card-status <?= $status_class ?>"><?= $status_text ?></span>
                </div>
                
                <div class="marketing-card-row">
                    <span class="marketing-card-label">ID Marketing:</span>
                    <span class="marketing-card-value"><?= $m['marketing_id'] ?></span>
                </div>
                
                <div class="marketing-card-row">
                    <span class="marketing-card-label">WhatsApp:</span>
                    <span class="marketing-card-value"><?= htmlspecialchars($m['phone'] ?? '-') ?></span>
                </div>
                
                <div class="marketing-card-row">
                    <span class="marketing-card-label">Lead Historis:</span>
                    <span class="marketing-card-value"><?= $m['total_leads_assigned'] ?? 0 ?></span>
                </div>
                
                <div class="marketing-card-row">
                    <span class="marketing-card-label">Lead Baru:</span>
                    <span class="marketing-card-value"><strong><?= $m['total_leads_diterima'] ?? 0 ?></strong></span>
                </div>
                
                <div class="marketing-card-row">
                    <span class="marketing-card-label">Follow Up:</span>
                    <span class="marketing-card-value"><?= $m['total_follow_up'] ?? 0 ?></span>
                </div>
                
                <div class="marketing-card-row">
                    <span class="marketing-card-label">Deal:</span>
                    <span class="marketing-card-value success"><strong><?= $m['total_deal'] ?? 0 ?></strong></span>
                </div>
                
                <div class="marketing-card-row">
                    <span class="marketing-card-label">Negatif:</span>
                    <span class="marketing-card-value danger"><strong><?= $m['total_negatif'] ?? 0 ?></strong></span>
                </div>
                
                <div class="marketing-card-row">
                    <span class="marketing-card-label">Conversion Rate:</span>
                    <span class="marketing-card-value"><strong><?= $m['conversion_rate'] ?? 0 ?>%</strong></span>
                </div>
                
                <div class="marketing-card-scores">
                    <div class="score-pill hot">Hot: <?= $hot ?></div>
                    <div class="score-pill warm">Warm: <?= $warm ?></div>
                    <div class="score-pill cold">Cold: <?= $cold ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - KPI Marketing System</p>
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