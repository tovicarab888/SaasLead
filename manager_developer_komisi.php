<?php
/**
 * MANAGER_DEVELOPER_KOMISI.PHP - LEADENGINE
 * Version: 1.0.0 - Tracking Komisi untuk Manager Developer
 * MOBILE FIRST UI - DAFTAR KOMISI MARKETING INTERNAL
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

// ========== FILTER ==========
$status = $_GET['status'] ?? 'all';
$marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

// ========== AMBIL MARKETING UNTUK FILTER ==========
$marketing_list = [];
$stmt = $conn->prepare("
    SELECT id, nama_lengkap FROM marketing_team 
    WHERE developer_id = ? AND is_active = 1 
    ORDER BY nama_lengkap
");
$stmt->execute([$developer_id]);
$marketing_list = $stmt->fetchAll();

// ========== BANGUN QUERY ==========
$sql = "SELECT k.*, 
               l.first_name, l.last_name, l.phone as customer_phone,
               m.nama_lengkap as marketing_name,
               u.nomor_unit, u.tipe_unit,
               b.nama_bank, b.nomor_rekening, b.atas_nama
        FROM komisi_logs k
        LEFT JOIN leads l ON k.lead_id = l.id
        LEFT JOIN marketing_team m ON k.marketing_id = m.id
        LEFT JOIN units u ON k.unit_id = u.id
        LEFT JOIN banks b ON 1=0 /* TODO: join banks nanti */
        WHERE k.developer_id = ?";

$params = [$developer_id];

// Filter internal only (manager developer hanya lihat internal)
$sql .= " AND k.assigned_type = 'internal'";

if ($status !== 'all' && in_array($status, ['pending', 'cair', 'batal'])) {
    $sql .= " AND k.status = ?";
    $params[] = $status;
}

if ($marketing_id > 0) {
    $sql .= " AND k.marketing_id = ?";
    $params[] = $marketing_id;
}

if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND DATE(k.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

if (!empty($search)) {
    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR m.nama_lengkap LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}

$sql .= " ORDER BY k.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$komisi_list = $stmt->fetchAll();

// ========== HITUNG STATISTIK ==========
$total_pending = 0;
$total_pending_nominal = 0;
$total_cair = 0;
$total_cair_nominal = 0;
$total_batal = 0;
$total_batal_nominal = 0;
$total_all = count($komisi_list);
$total_all_nominal = 0;

foreach ($komisi_list as $k) {
    $total_all_nominal += $k['komisi_final'];
    
    if ($k['status'] == 'pending') {
        $total_pending++;
        $total_pending_nominal += $k['komisi_final'];
    } elseif ($k['status'] == 'cair') {
        $total_cair++;
        $total_cair_nominal += $k['komisi_final'];
    } elseif ($k['status'] == 'batal') {
        $total_batal++;
        $total_batal_nominal += $k['komisi_final'];
    }
}

// ========== STATISTIK PER MARKETING ==========
$stats_per_marketing = [];
$stmt = $conn->prepare("
    SELECT 
        m.id,
        m.nama_lengkap,
        COUNT(k.id) as total_komisi,
        SUM(CASE WHEN k.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN k.status = 'pending' THEN k.komisi_final ELSE 0 END) as pending_nominal,
        SUM(CASE WHEN k.status = 'cair' THEN 1 ELSE 0 END) as cair,
        SUM(CASE WHEN k.status = 'cair' THEN k.komisi_final ELSE 0 END) as cair_nominal,
        SUM(k.komisi_final) as total_nominal
    FROM marketing_team m
    LEFT JOIN komisi_logs k ON m.id = k.marketing_id AND k.developer_id = ? AND k.assigned_type = 'internal'
    WHERE m.developer_id = ? AND m.is_active = 1
    GROUP BY m.id
    ORDER BY total_nominal DESC
");
$stmt->execute([$developer_id, $developer_id]);
$stats_per_marketing = $stmt->fetchAll();

$page_title = 'Tracking Komisi';
$page_subtitle = 'Komisi Marketing Internal';
$page_icon = 'fas fa-coins';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
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
}

.main-content {
    width: 100%;
    min-height: 100vh;
    padding: 12px;
    margin-left: 0 !important;
}

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

.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-select, .filter-input {
    flex: 1;
    min-width: 140px;
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    background: white;
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
}

.filter-btn.reset {
    background: var(--border);
    color: var(--text);
}

.export-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.export-btn {
    flex: 1;
    min-width: 120px;
    padding: 12px 16px;
    border-radius: 40px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s;
}

.export-btn.pdf {
    background: linear-gradient(135deg, #D64F3C, #FF6B4A);
    color: white;
}

.export-btn.excel {
    background: linear-gradient(135deg, var(--success), #40BEB0);
    color: white;
}

.export-btn.csv {
    background: linear-gradient(135deg, var(--info), #6DA5F0);
    color: white;
}

.stats-summary {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    overflow-x: auto;
    padding: 4px 0;
}

.stat-summary-card {
    flex: 0 0 160px;
    background: white;
    border-radius: 16px;
    padding: 14px;
    border-left: 4px solid;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.stat-summary-card.pending { border-left-color: var(--warning); }
.stat-summary-card.cair { border-left-color: var(--success); }
.stat-summary-card.batal { border-left-color: var(--danger); }
.stat-summary-card.total { border-left-color: var(--secondary); }

.stat-summary-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-summary-value {
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
}

.stat-summary-nominal {
    font-size: 14px;
    font-weight: 700;
    color: var(--secondary);
    margin-top: 4px;
}

/* ===== TABEL STATISTIK PER MARKETING ===== */
.table-container {
    background: white;
    border-radius: 24px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    overflow: hidden;
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
    min-width: 800px;
}

th {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 13px;
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

.text-right {
    text-align: right;
}

.text-success {
    color: var(--success);
    font-weight: 600;
}

.text-warning {
    color: #B87C00;
    font-weight: 600;
}

.text-danger {
    color: var(--danger);
    font-weight: 600;
}

/* ===== KOMISI CARDS ===== */
.komisi-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    padding: 8px 0 20px 0;
    margin-bottom: 16px;
}

.komisi-horizontal::-webkit-scrollbar {
    height: 4px;
}

.komisi-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.komisi-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.komisi-card {
    flex: 0 0 300px;
    background: white;
    border-radius: 20px;
    padding: 16px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    border-left: 6px solid;
    transition: transform 0.2s;
}

.komisi-card.pending { border-left-color: var(--warning); }
.komisi-card.cair { border-left-color: var(--success); }
.komisi-card.batal { border-left-color: var(--danger); }

.komisi-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.komisi-marketing {
    font-weight: 700;
    color: var(--primary);
    font-size: 15px;
}

.komisi-status {
    font-size: 10px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 20px;
}

.komisi-status.pending {
    background: var(--warning);
    color: #1A2A24;
}

.komisi-status.cair {
    background: var(--success);
    color: white;
}

.komisi-status.batal {
    background: var(--danger);
    color: white;
}

.komisi-customer {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 8px;
}

.komisi-detail {
    font-size: 12px;
    color: var(--text-light);
    margin: 8px 0;
    line-height: 1.6;
}

.komisi-detail i {
    color: var(--secondary);
    width: 18px;
    margin-right: 4px;
}

.komisi-nominal {
    font-size: 18px;
    font-weight: 800;
    color: var(--secondary);
    text-align: right;
    margin: 12px 0 8px;
}

.komisi-tanggal {
    font-size: 10px;
    color: var(--text-muted);
    text-align: right;
    margin-bottom: 12px;
}

.btn-action {
    display: block;
    background: var(--secondary);
    color: white;
    text-align: center;
    padding: 10px;
    border-radius: 40px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    margin-top: 8px;
}

.btn-action.cair {
    background: var(--success);
}

.btn-action:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 24px;
    width: 100%;
}

.empty-state i {
    font-size: 60px;
    color: #E0DAD3;
    margin-bottom: 16px;
}

.empty-state h4 {
    color: var(--text);
    margin-bottom: 8px;
    font-size: 18px;
}

.empty-state p {
    color: var(--text-muted);
    margin-bottom: 20px;
    font-size: 14px;
}

.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

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
    
    .komisi-horizontal {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        overflow-x: visible;
    }
    
    .komisi-card {
        flex: none;
        width: auto;
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
    
    <!-- FILTER BAR -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <select name="status" class="filter-select">
                <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="cair" <?= $status == 'cair' ? 'selected' : '' ?>>Sudah Cair</option>
                <option value="batal" <?= $status == 'batal' ? 'selected' : '' ?>>Batal</option>
            </select>
            
            <select name="marketing_id" class="filter-select">
                <option value="">Semua Marketing</option>
                <?php foreach ($marketing_list as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $marketing_id == $m['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" name="date_from" class="filter-input" value="<?= $date_from ?>">
            <input type="date" name="date_to" class="filter-input" value="<?= $date_to ?>">
            
            <input type="text" name="search" class="filter-input" placeholder="Cari customer/marketing..." value="<?= htmlspecialchars($search) ?>">
            
            <button type="submit" class="filter-btn">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="?" class="filter-btn reset">
                <i class="fas fa-redo"></i> Reset
            </a>
        </form>
        
        <!-- EXPORT BUTTONS -->
        <div class="export-buttons">
            <a href="api/export_komisi.php?type=manager_developer&status=<?= $status ?>&marketing_id=<?= $marketing_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&format=pdf" class="export-btn pdf">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <a href="api/export_komisi.php?type=manager_developer&status=<?= $status ?>&marketing_id=<?= $marketing_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&format=excel" class="export-btn excel">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="api/export_komisi.php?type=manager_developer&status=<?= $status ?>&marketing_id=<?= $marketing_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&format=csv" class="export-btn csv">
                <i class="fas fa-file-csv"></i> CSV
            </a>
        </div>
    </div>
    
    <!-- STATS SUMMARY -->
    <div class="stats-summary">
        <div class="stat-summary-card pending">
            <div class="stat-summary-label">Pending</div>
            <div class="stat-summary-value"><?= $total_pending ?></div>
            <div class="stat-summary-nominal">Rp <?= number_format($total_pending_nominal, 0, ',', '.') ?></div>
        </div>
        <div class="stat-summary-card cair">
            <div class="stat-summary-label">Sudah Cair</div>
            <div class="stat-summary-value"><?= $total_cair ?></div>
            <div class="stat-summary-nominal">Rp <?= number_format($total_cair_nominal, 0, ',', '.') ?></div>
        </div>
        <div class="stat-summary-card batal">
            <div class="stat-summary-label">Batal</div>
            <div class="stat-summary-value"><?= $total_batal ?></div>
            <div class="stat-summary-nominal">Rp <?= number_format($total_batal_nominal, 0, ',', '.') ?></div>
        </div>
        <div class="stat-summary-card total">
            <div class="stat-summary-label">Total</div>
            <div class="stat-summary-value"><?= $total_all ?></div>
            <div class="stat-summary-nominal">Rp <?= number_format($total_all_nominal, 0, ',', '.') ?></div>
        </div>
    </div>
    
    <!-- TABEL STATISTIK PER MARKETING -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-chart-bar"></i> Rekap Komisi per Marketing</h3>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Marketing</th>
                        <th class="text-right">Pending</th>
                        <th class="text-right">Nominal Pending</th>
                        <th class="text-right">Cair</th>
                        <th class="text-right">Nominal Cair</th>
                        <th class="text-right">Total Komisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats_per_marketing)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 30px;">Belum ada data</td></tr>
                    <?php else: ?>
                        <?php foreach ($stats_per_marketing as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['nama_lengkap']) ?></strong></td>
                            <td class="text-right text-warning"><?= $s['pending'] ?></td>
                            <td class="text-right">Rp <?= number_format($s['pending_nominal'], 0, ',', '.') ?></td>
                            <td class="text-right text-success"><?= $s['cair'] ?></td>
                            <td class="text-right">Rp <?= number_format($s['cair_nominal'], 0, ',', '.') ?></td>
                            <td class="text-right"><strong>Rp <?= number_format($s['total_nominal'], 0, ',', '.') ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- KOMISI LIST -->
    <?php if (empty($komisi_list)): ?>
    <div class="empty-state">
        <i class="fas fa-coins"></i>
        <h4>Tidak Ada Data Komisi</h4>
        <p>Tidak ditemukan komisi dengan filter yang dipilih</p>
    </div>
    <?php else: ?>
    <div class="komisi-horizontal">
        <?php foreach ($komisi_list as $k): 
            $full_name = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
        ?>
        <div class="komisi-card <?= $k['status'] ?>">
            <div class="komisi-header">
                <span class="komisi-marketing"><?= htmlspecialchars($k['marketing_name'] ?? 'Unknown') ?></span>
                <span class="komisi-status <?= $k['status'] ?>">
                    <?= $k['status'] == 'pending' ? 'Pending' : ($k['status'] == 'cair' ? 'Cair' : 'Batal') ?>
                </span>
            </div>
            
            <div class="komisi-customer">
                <i class="fas fa-user"></i> <?= htmlspecialchars($full_name ?: 'Lead #' . $k['lead_id']) ?>
            </div>
            
            <div class="komisi-detail">
                <div><i class="fas fa-home"></i> Unit: <?= htmlspecialchars($k['nomor_unit'] ?? '-') ?> (<?= htmlspecialchars($k['tipe_unit'] ?? '-') ?>)</div>
                <div><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($k['customer_phone'] ?? '-') ?></div>
            </div>
            
            <div class="komisi-nominal">
                Rp <?= number_format($k['komisi_final'], 0, ',', '.') ?>
            </div>
            
            <div class="komisi-tanggal">
                <i class="far fa-clock"></i> <?= date('d/m/Y H:i', strtotime($k['created_at'])) ?>
            </div>
            
            <?php if ($k['status'] == 'pending'): ?>
            <a href="#" onclick="alert('Untuk proses pencairan, hubungi tim finance')" class="btn-action">
                <i class="fas fa-info-circle"></i> Menunggu Finance
            </a>
            <?php elseif ($k['status'] == 'cair' && !empty($k['bukti_transfer'])): ?>
            <a href="/admin/uploads/bukti/<?= $k['bukti_transfer'] ?>" target="_blank" class="btn-action cair">
                <i class="fas fa-file-invoice"></i> Lihat Bukti
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Tracking Komisi v1.0</p>
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