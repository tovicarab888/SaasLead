<?php
/**
 * MARKETING_EXTERNAL_KOMISI.PHP - Riwayat Komisi Marketing External
 * Version: 1.0.0 - UI GLOBAL KEREN
 */

session_start();
require_once 'api/config.php';

// Cek akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'marketing_external') {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) die("Database connection failed");

$user_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Hitung total
$count_sql = "SELECT COUNT(*) FROM komisi_logs WHERE marketing_id = ?";
$count_params = [$user_id];

if (!empty($status_filter)) {
    $count_sql .= " AND status = ?";
    $count_params[] = $status_filter;
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Ambil data komisi
$sql = "
    SELECT 
        k.*,
        l.first_name,
        l.last_name,
        l.phone,
        l.location_key,
        loc.display_name as location_display,
        un.nomor_unit,
        un.tipe_unit,
        un.harga
    FROM komisi_logs k
    LEFT JOIN leads l ON k.lead_id = l.id
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    LEFT JOIN units un ON k.unit_id = un.id
    WHERE k.marketing_id = ?
";
$params = [$user_id];

if (!empty($status_filter)) {
    $sql .= " AND k.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY k.created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$komisi = $stmt->fetchAll();

// Hitung total komisi
$total_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN status = 'cair' THEN komisi_final ELSE 0 END) as total_cair,
        SUM(CASE WHEN status = 'pending' THEN komisi_final ELSE 0 END) as total_pending
    FROM komisi_logs
    WHERE marketing_id = ?
");
$total_stmt->execute([$user_id]);
$total = $total_stmt->fetch();

$page_title = 'Riwayat Komisi';
$page_subtitle = 'Laporan komisi Anda';
$page_icon = 'fas fa-money-bill-wave';

include 'includes/header.php';
include 'includes/sidebar_marketing_external.php';
?>

<style>
/* ===== STATS CARDS KECIL ===== */
.stats-small-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

@media (min-width: 768px) {
    .stats-small-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.stat-small-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    border-left: 4px solid var(--secondary);
}

.stat-small-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 4px;
}

.stat-small-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--text);
}

/* ===== FILTER SECTION ===== */
.filter-section {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
}

.filter-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

@media (min-width: 768px) {
    .filter-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* ===== TABLE SECTION ===== */
.table-section {
    background: white;
    border-radius: 24px;
    padding: 20px;
    border: 1px solid var(--border);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.table-header h3 {
    font-size: 1.2rem;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
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
    min-width: 1000px;
}

th {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    padding: 14px 12px;
    font-weight: 700;
    color: var(--primary);
    font-size: 0.8rem;
    text-transform: uppercase;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-badge.cair {
    background: #D1FAE5;
    color: #065F46;
}

.status-badge.pending {
    background: #FEF3C7;
    color: #92400E;
}

.status-badge.batal {
    background: #FEE2E2;
    color: #991B1B;
}

.amount {
    font-weight: 700;
    color: var(--success);
}

/* ===== PAGINATION ===== */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
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
    padding: 60px 20px;
    background: #F8FAFC;
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
    
    <!-- STATS CARDS -->
    <div class="stats-small-grid">
        <div class="stat-small-card">
            <div class="stat-small-label">Total Komisi Cair</div>
            <div class="stat-small-value">Rp <?= number_format($total['total_cair'] ?? 0, 0, ',', '.') ?></div>
        </div>
        <div class="stat-small-card" style="border-left-color: #E9C46A;">
            <div class="stat-small-label">Pending</div>
            <div class="stat-small-value">Rp <?= number_format($total['total_pending'] ?? 0, 0, ',', '.') ?></div>
        </div>
        <div class="stat-small-card" style="border-left-color: var(--info);">
            <div class="stat-small-label">Total Transaksi</div>
            <div class="stat-small-value"><?= $total_records ?> komisi</div>
        </div>
    </div>
    
    <!-- FILTER SECTION -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-grid">
                <div class="filter-item">
                    <label><i class="fas fa-tag"></i> Status</label>
                    <select name="status" class="filter-select">
                        <option value="">Semua Status</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="cair" <?= $status_filter == 'cair' ? 'selected' : '' ?>>Cair</option>
                        <option value="batal" <?= $status_filter == 'batal' ? 'selected' : '' ?>>Batal</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions" style="margin-top: 15px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="?" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- TABLE SECTION -->
    <div class="table-section">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Riwayat Komisi</h3>
            <div class="table-info">
                <span class="badge">Halaman <?= $page ?> dari <?= $total_pages ?></span>
            </div>
        </div>
        
        <?php if (empty($komisi)): ?>
        <div class="empty-state">
            <i class="fas fa-coins"></i>
            <p>Belum ada riwayat komisi</p>
        </div>
        <?php else: ?>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Lead</th>
                        <th>Unit</th>
                        <th>Developer</th>
                        <th>Komisi</th>
                        <th>Status</th>
                        <th>Tanggal Cair</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($komisi as $k): 
                        $lead_name = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
                    ?>
                    <tr>
                        <td><strong>#<?= $k['id'] ?></strong></td>
                        <td>
                            <?= htmlspecialchars($lead_name ?: 'Lead #' . $k['lead_id']) ?><br>
                            <small><?= htmlspecialchars($k['phone'] ?? '-') ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($k['nomor_unit'] ?? '-') ?><br>
                            <small><?= htmlspecialchars($k['tipe_unit'] ?? '-') ?></small>
                        </td>
                        <td><?= htmlspecialchars($k['developer_id'] ? 'ID: ' . $k['developer_id'] : '-') ?></td>
                        <td class="amount">Rp <?= number_format($k['komisi_final'], 0, ',', '.') ?></td>
                        <td>
                            <span class="status-badge <?= $k['status'] ?>">
                                <?= ucfirst($k['status']) ?>
                            </span>
                        </td>
                        <td><?= $k['tanggal_cair'] ? date('d/m/Y', strtotime($k['tanggal_cair'])) : '-' ?></td>
                        <td><?= date('d/m/Y', strtotime($k['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
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
            <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>" 
               class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
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
        
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Marketing External Komisi</p>
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