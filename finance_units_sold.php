<?php
/**
 * FINANCE_UNITS_SOLD.PHP - Daftar Unit Terjual untuk Finance Developer (ID 9)
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

// ========== FILTER ==========
$cluster_id = isset($_GET['cluster_id']) ? (int)$_GET['cluster_id'] : 0;
$block_id = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// ========== BUILD QUERY ==========
$sql = "
    SELECT 
        u.*,
        c.nama_cluster,
        b.nama_block,
        l.first_name,
        l.last_name,
        l.phone as customer_phone,
        l.email as customer_email,
        l.created_at as lead_created,
        kl.id as komisi_id,
        kl.status as komisi_status,
        kl.komisi_final,
        m.nama_lengkap as marketing_name
    FROM units u
    JOIN clusters c ON u.cluster_id = c.id
    JOIN blocks b ON u.block_id = b.id
    LEFT JOIN leads l ON u.lead_id = l.id
    LEFT JOIN komisi_logs kl ON l.id = kl.lead_id AND kl.assigned_type = 'internal'
    LEFT JOIN marketing_team m ON kl.marketing_id = m.id
    WHERE c.developer_id = ? AND u.status = 'SOLD'
";
$params = [$developer_id];

if ($cluster_id > 0) {
    $sql .= " AND u.cluster_id = ?";
    $params[] = $cluster_id;
}

if ($block_id > 0) {
    $sql .= " AND u.block_id = ?";
    $params[] = $block_id;
}

$sql .= " AND DATE(u.sold_at) BETWEEN ? AND ?";
$params[] = $start_date;
$params[] = $end_date;

if (!empty($search)) {
    $sql .= " AND (u.nomor_unit LIKE ? OR l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}

// Count total
$count_sql = "
    SELECT COUNT(*) 
    FROM units u
    JOIN clusters c ON u.cluster_id = c.id
    WHERE c.developer_id = ? AND u.status = 'SOLD'
";
$count_params = [$developer_id];

if ($cluster_id > 0) {
    $count_sql .= " AND u.cluster_id = ?";
    $count_params[] = $cluster_id;
}

if ($block_id > 0) {
    $count_sql .= " AND u.block_id = ?";
    $count_params[] = $block_id;
}

$count_sql .= " AND DATE(u.sold_at) BETWEEN ? AND ?";
$count_params[] = $start_date;
$count_params[] = $end_date;

$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get data
$sql .= " ORDER BY u.sold_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$units = $stmt->fetchAll();

// ========== STATISTIK ==========
$stats_sql = "
    SELECT 
        COUNT(*) as total_terjual,
        COALESCE(SUM(u.harga), 0) as total_nilai,
        AVG(u.harga) as rata_rata,
        COUNT(DISTINCT u.cluster_id) as total_cluster
    FROM units u
    JOIN clusters c ON u.cluster_id = c.id
    WHERE c.developer_id = ? AND u.status = 'SOLD'
    AND DATE(u.sold_at) BETWEEN ? AND ?
";
$stats_params = [$developer_id, $start_date, $end_date];

if ($cluster_id > 0) {
    $stats_sql .= " AND u.cluster_id = ?";
    $stats_params[] = $cluster_id;
}

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute($stats_params);
$stats = $stats_stmt->fetch();

// ========== AMBIL DATA CLUSTER UNTUK FILTER ==========
$clusters = $conn->prepare("
    SELECT id, nama_cluster FROM clusters 
    WHERE developer_id = ? AND is_active = 1 
    ORDER BY nama_cluster
");
$clusters->execute([$developer_id]);
$cluster_list = $clusters->fetchAll();

// ========== AMBIL DATA BLOCK UNTUK FILTER ==========
$blocks = [];
if ($cluster_id > 0) {
    $blocks_stmt = $conn->prepare("
        SELECT id, nama_block FROM blocks 
        WHERE cluster_id = ? 
        ORDER BY nama_block
    ");
    $blocks_stmt->execute([$cluster_id]);
    $blocks = $blocks_stmt->fetchAll();
}

$page_title = 'Unit Terjual';
$page_subtitle = $developer_name;
$page_icon = 'fas fa-home';

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

/* ===== FILTER SECTION ===== */
.filter-section {
    background: var(--surface);
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 16px;
    border: 1px solid var(--border);
}

.filter-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 12px;
}

@media (min-width: 768px) {
    .filter-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

.filter-item label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 4px;
}

.filter-item select,
.filter-item input {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    background: var(--bg);
}

.filter-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.btn-filter {
    flex: 1;
    padding: 14px;
    border: none;
    border-radius: 40px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-filter.primary {
    background: var(--finance);
    color: white;
}

.btn-filter.secondary {
    background: var(--bg);
    color: var(--text);
}

/* ===== STATS ROW ===== */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

.stat-mini-card {
    background: var(--surface);
    border-radius: 16px;
    padding: 14px;
    border-left: 4px solid;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.stat-mini-card.total {
    border-left-color: var(--primary);
}

.stat-mini-card.nilai {
    border-left-color: var(--success);
}

.stat-mini-card.rata {
    border-left-color: var(--info);
}

.stat-mini-card.cluster {
    border-left-color: var(--warning);
}

.stat-mini-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 4px;
}

.stat-mini-value {
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
}

.stat-mini-nominal {
    font-size: 13px;
    font-weight: 600;
    color: var(--secondary);
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
    min-width: 900px;
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

/* ===== PAGINATION ===== */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.pagination-btn {
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: white;
    border: 2px solid var(--border);
    color: var(--text);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
}

.pagination-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 16px;
}

.empty-state i {
    font-size: 48px;
    color: #E0DAD3;
    margin-bottom: 16px;
}

.empty-state p {
    color: var(--text-muted);
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
    
    <!-- FILTER SECTION -->
    <div class="filter-section">
        <form method="GET" id="filterForm">
            <div class="filter-grid">
                <div class="filter-item">
                    <label>Cluster</label>
                    <select name="cluster_id" id="cluster_id" onchange="this.form.submit()">
                        <option value="">Semua Cluster</option>
                        <?php foreach ($cluster_list as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $cluster_id == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nama_cluster']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>Block</label>
                    <select name="block_id">
                        <option value="">Semua Block</option>
                        <?php foreach ($blocks as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $block_id == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['nama_block']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>Dari Tanggal</label>
                    <input type="date" name="start_date" value="<?= $start_date ?>">
                </div>
                
                <div class="filter-item">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="end_date" value="<?= $end_date ?>">
                </div>
                
                <div class="filter-item" style="grid-column: 1/-1;">
                    <label>Cari</label>
                    <input type="text" name="search" placeholder="Nomor unit / nama customer..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-filter primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="?" class="btn-filter secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- STATS ROW -->
    <div class="stats-row">
        <div class="stat-mini-card total">
            <div class="stat-mini-label">Total Terjual</div>
            <div class="stat-mini-value"><?= number_format($stats['total_terjual'] ?? 0) ?></div>
            <div class="stat-mini-nominal">unit</div>
        </div>
        
        <div class="stat-mini-card nilai">
            <div class="stat-mini-label">Total Nilai</div>
            <div class="stat-mini-value">Rp <?= number_format($stats['total_nilai'] ?? 0, 0, ',', '.') ?></div>
            <div class="stat-mini-nominal">-</div>
        </div>
        
        <div class="stat-mini-card rata">
            <div class="stat-mini-label">Rata-rata Harga</div>
            <div class="stat-mini-value">Rp <?= number_format($stats['rata_rata'] ?? 0, 0, ',', '.') ?></div>
            <div class="stat-mini-nominal">per unit</div>
        </div>
        
        <div class="stat-mini-card cluster">
            <div class="stat-mini-label">Cluster</div>
            <div class="stat-mini-value"><?= number_format($stats['total_cluster'] ?? 0) ?></div>
            <div class="stat-mini-nominal">terjual</div>
        </div>
    </div>
    
    <!-- TABLE UNITS -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Daftar Unit Terjual</h3>
            <div class="table-badge">
                Total: <?= number_format($total_records) ?> | Halaman <?= $page ?> dari <?= $total_pages ?>
            </div>
        </div>
        
        <?php if (empty($units)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Tidak ada unit terjual</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Cluster/Block</th>
                        <th>Customer</th>
                        <th>Harga</th>
                        <th>Tanggal Jual</th>
                        <th>Marketing</th>
                        <th>Komisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($units as $u): 
                        $customer = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <strong><?= $u['nomor_unit'] ?></strong><br>
                            <small style="color: var(--text-muted);"><?= $u['tipe_unit'] ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($u['nama_cluster']) ?><br>
                            <small style="color: var(--text-muted);">Block <?= $u['nama_block'] ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($customer ?: 'Lead #' . $u['lead_id']) ?><br>
                            <small style="color: var(--text-muted);"><?= $u['customer_phone'] ?></small>
                        </td>
                        <td><strong style="color: var(--success);">Rp <?= number_format($u['harga'] ?? 0, 0, ',', '.') ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($u['sold_at'])) ?></td>
                        <td><?= htmlspecialchars($u['marketing_name'] ?? '-') ?></td>
                        <td>
                            <?php if ($u['komisi_id']): ?>
                                <span class="status-badge <?= $u['komisi_status'] ?? 'pending' ?>">
                                    Rp <?= number_format($u['komisi_final'] ?? 0, 0, ',', '.') ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">-</span>
                            <?php endif; ?>
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
            <a href="?page=<?= $page-1 ?>&cluster_id=<?= $cluster_id ?>&block_id=<?= $block_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?page=<?= $i ?>&cluster_id=<?= $cluster_id ?>&block_id=<?= $block_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&search=<?= urlencode($search) ?>" 
               class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&cluster_id=<?= $cluster_id ?>&block_id=<?= $block_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Finance Units Sold v2.0</p>
    </div>
    
</div>

<script>
// Dynamic block loading based on cluster
document.getElementById('cluster_id').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});

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