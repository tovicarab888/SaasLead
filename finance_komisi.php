<?php
/**
 * FINANCE_DEVELOPER_KOMISI.PHP - Kelola Komisi Internal untuk Finance Developer (ID 9)
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

// ========== PROSES FILTER ==========
$status = $_GET['status'] ?? 'all';
$marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// ========== BUILD QUERY ==========
$sql = "
    SELECT 
        k.*,
        l.first_name,
        l.last_name,
        l.phone as customer_phone,
        l.location_key,
        loc.display_name as location_name,
        m.nama_lengkap as marketing_name,
        m.phone as marketing_phone,
        u.nomor_unit,
        u.tipe_unit,
        u.harga
    FROM komisi_logs k
    LEFT JOIN leads l ON k.lead_id = l.id
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    LEFT JOIN marketing_team m ON k.marketing_id = m.id
    LEFT JOIN units u ON k.unit_id = u.id
    WHERE k.developer_id = ? AND k.assigned_type = 'internal'
";
$params = [$developer_id];

if ($status !== 'all') {
    $sql .= " AND k.status = ?";
    $params[] = $status;
}

if ($marketing_id > 0) {
    $sql .= " AND k.marketing_id = ?";
    $params[] = $marketing_id;
}

$sql .= " AND DATE(k.created_at) BETWEEN ? AND ?";
$params[] = $start_date;
$params[] = $end_date;

if (!empty($search)) {
    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR m.nama_lengkap LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}

// Count total
$count_sql = "SELECT COUNT(*) FROM komisi_logs WHERE developer_id = ? AND assigned_type = 'internal'";
$count_params = [$developer_id];

if ($status !== 'all') {
    $count_sql .= " AND status = ?";
    $count_params[] = $status;
}

if ($marketing_id > 0) {
    $count_sql .= " AND marketing_id = ?";
    $count_params[] = $marketing_id;
}

$count_sql .= " AND DATE(created_at) BETWEEN ? AND ?";
$count_params[] = $start_date;
$count_params[] = $end_date;

$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get data
$sql .= " ORDER BY k.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$komisi_list = $stmt->fetchAll();

// ========== STATISTIK ==========
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'cair' THEN 1 ELSE 0 END) as cair,
        SUM(CASE WHEN status = 'batal' THEN 1 ELSE 0 END) as batal,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN komisi_final ELSE 0 END), 0) as total_pending,
        COALESCE(SUM(CASE WHEN status = 'cair' THEN komisi_final ELSE 0 END), 0) as total_cair,
        COALESCE(SUM(komisi_final), 0) as total_komisi
    FROM komisi_logs
    WHERE developer_id = ? AND assigned_type = 'internal'
    AND DATE(created_at) BETWEEN ? AND ?
";
$stats_params = [$developer_id, $start_date, $end_date];

if ($marketing_id > 0) {
    $stats_sql .= " AND marketing_id = ?";
    $stats_params[] = $marketing_id;
}

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute($stats_params);
$stats = $stats_stmt->fetch();

// ========== AMBIL DATA MARKETING UNTUK FILTER ==========
$marketings = $conn->prepare("
    SELECT id, nama_lengkap FROM marketing_team 
    WHERE developer_id = ? AND is_active = 1 
    ORDER BY nama_lengkap
");
$marketings->execute([$developer_id]);
$marketing_list = $marketings->fetchAll();

$page_title = 'Kelola Komisi Internal';
$page_subtitle = $developer_name;
$page_icon = 'fas fa-hand-holding-usd';

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

/* ===== STATS CARD - HORIZONTAL SCROLL ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .stats-grid {
        display: flex;
        overflow-x: auto;
        gap: 12px;
        padding: 4px 0 16px 0;
        margin-bottom: 8px;
        -webkit-overflow-scrolling: touch;
    }
    
    .stats-grid .stat-card {
        flex: 0 0 140px;
    }
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 14px;
    border-left: 4px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.stat-icon {
    font-size: 20px;
    color: var(--secondary);
    margin-bottom: 8px;
}

.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 2px;
}

.stat-value {
    font-size: 22px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

.stat-nominal {
    font-size: 12px;
    font-weight: 600;
    color: var(--success);
}

/* ===== ALERT ===== */
.alert {
    padding: 14px 16px;
    border-radius: 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
    border-left: 4px solid;
}

.alert.success {
    background: #d4edda;
    color: #155724;
    border-left-color: #28a745;
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
    border-left-color: var(--danger);
}

/* ===== FILTER BAR ===== */
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
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    background: white;
}

.filter-select:focus, .filter-input:focus {
    border-color: var(--secondary);
    outline: none;
}

.filter-actions {
    display: flex;
    gap: 10px;
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
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.filter-btn.reset {
    background: var(--border);
    color: var(--text);
}

.filter-btn.reset:hover {
    background: var(--text-muted);
    color: white;
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

/* ===== ACTION BUTTONS ===== */
.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
    align-items: center;
    flex-wrap: nowrap;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    border: 1px solid var(--border);
    text-decoration: none;
    flex-shrink: 0;
}

.action-btn.edit {
    background: #fff8e1;
    color: #B87C00;
    border-color: #B87C00;
}

.action-btn.edit:hover {
    background: #B87C00;
    color: white;
}

.action-btn.view {
    background: #e8f0fe;
    color: #1976d2;
    border-color: #1976d2;
}

.action-btn.view:hover {
    background: #1976d2;
    color: white;
}

.action-btn i {
    font-size: 14px;
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
    <div class="filter-bar">
        <form method="GET" class="filter-form" id="filterForm">
            <select name="status" class="filter-select" style="max-width: 150px;">
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
            
            <input type="date" name="start_date" class="filter-input" value="<?= $start_date ?>" style="max-width: 150px;">
            <input type="date" name="end_date" class="filter-input" value="<?= $end_date ?>" style="max-width: 150px;">
            
            <input type="text" name="search" class="filter-input" placeholder="Nama customer / marketing..." value="<?= htmlspecialchars($search) ?>">
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
                <a href="?" class="filter-btn reset"><i class="fas fa-redo"></i> Reset</a>
            </div>
        </form>
    </div>
    
    <!-- STATS ROW -->
    <div class="stats-grid">
        <div class="stat-card" style="border-left-color: var(--warning);">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= number_format($stats['pending'] ?? 0) ?></div>
            <div class="stat-nominal">Rp <?= number_format($stats['total_pending'] ?? 0, 0, ',', '.') ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--success);">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Sudah Cair</div>
            <div class="stat-value"><?= number_format($stats['cair'] ?? 0) ?></div>
            <div class="stat-nominal">Rp <?= number_format($stats['total_cair'] ?? 0, 0, ',', '.') ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--primary);">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-label">Total Komisi</div>
            <div class="stat-value">Rp <?= number_format($stats['total_komisi'] ?? 0, 0, ',', '.') ?></div>
            <div class="stat-nominal"><?= $stats['total'] ?? 0 ?> transaksi</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--danger);">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-label">Batal</div>
            <div class="stat-value"><?= number_format($stats['batal'] ?? 0) ?></div>
            <div class="stat-nominal">-</div>
        </div>
    </div>
    
    <!-- TABLE KOMISI -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Daftar Komisi Internal</h3>
            <div class="table-badge">
                Total: <?= number_format($total_records) ?> | Halaman <?= $page ?> dari <?= $total_pages ?>
            </div>
        </div>
        
        <?php if (empty($komisi_list)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Tidak ada data komisi</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Marketing</th>
                        <th>Customer</th>
                        <th>Unit</th>
                        <th>Komisi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($komisi_list as $k): 
                        $customer = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
                    ?>
                    <tr>
                        <td>#<?= $k['id'] ?></td>
                        <td><?= date('d/m/Y', strtotime($k['created_at'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($k['marketing_name'] ?? '-') ?></strong><br>
                            <small style="color: var(--text-muted);"><?= $k['marketing_phone'] ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($customer ?: 'Lead #' . $k['lead_id']) ?><br>
                            <small style="color: var(--text-muted);"><?= $k['customer_phone'] ?></small>
                        </td>
                        <td>
                            <?= $k['tipe_unit'] ?><br>
                            <small style="color: var(--text-muted);"><?= $k['nomor_unit'] ?></small>
                        </td>
                        <td><strong style="color: var(--success);">Rp <?= number_format($k['komisi_final'], 0, ',', '.') ?></strong></td>
                        <td>
                            <span class="status-badge <?= $k['status'] ?>">
                                <?= strtoupper($k['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="finance_developer_verifikasi.php?id=<?= $k['id'] ?>" class="action-btn view" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($k['status'] == 'pending'): ?>
                                <a href="finance_developer_verifikasi.php?id=<?= $k['id'] ?>" class="action-btn edit" title="Proses">
                                    <i class="fas fa-check-circle"></i>
                                </a>
                                <?php endif; ?>
                            </div>
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
            <a href="?page=<?= $page-1 ?>&status=<?= $status ?>&marketing_id=<?= $marketing_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= $status ?>&marketing_id=<?= $marketing_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&search=<?= urlencode($search) ?>" 
               class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&status=<?= $status ?>&marketing_id=<?= $marketing_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Finance Developer Komisi v2.0</p>
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