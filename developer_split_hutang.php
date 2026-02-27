<?php
/**
 * DEVELOPER_SPLIT_HUTANG.PHP - LEADENGINE
 * Version: 1.0.0 - Daftar Hutang Komisi Split Developer ke Platform
 * MOBILE FIRST UI - SESUAI GLOBAL SISTEM
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek akses: hanya Developer
if (!isDeveloper()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Developer.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$developer_id = $_SESSION['user_id'];
$developer_name = $_SESSION['nama_lengkap'] ?? 'Developer';

// ========== FILTER ==========
$status = $_GET['status'] ?? 'PENDING';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// ========== BANGUN QUERY ==========
$sql = "
    SELECT 
        ks.*,
        l.first_name, l.last_name, l.phone as customer_phone,
        u.nomor_unit, u.tipe_unit, u.harga,
        pb.nama_program as program_booking_name,
        fp.nama_lengkap as paid_by_name
    FROM komisi_split_hutang ks
    LEFT JOIN leads l ON ks.lead_id = l.id
    LEFT JOIN units u ON ks.unit_id = u.id
    LEFT JOIN program_booking pb ON u.program_booking_id = pb.id
    LEFT JOIN users fp ON ks.paid_by = fp.id
    WHERE ks.developer_id = ?
";

$params = [$developer_id];

if ($status !== 'ALL') {
    $sql .= " AND ks.status = ?";
    $params[] = $status;
}

$sql .= " AND DATE(ks.created_at) BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;

// Hitung total
$count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as tmp";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Ambil data
$sql .= " ORDER BY ks.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$hutang = $stmt->fetchAll();

// Hitung total
$total_pending = 0;
$total_lunas = 0;
$total_semua = 0;

foreach ($hutang as $h) {
    if ($h['status'] == 'PENDING') $total_pending += $h['nominal'];
    if ($h['status'] == 'LUNAS') $total_lunas += $h['nominal'];
    $total_semua += $h['nominal'];
}

$page_title = 'Hutang Komisi Split';
$page_subtitle = 'Tagihan ke Platform';
$page_icon = 'fas fa-hand-holding-usd';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* CSS SAMA DENGAN FILE GLOBAL */
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
    --gold: #E3B584;
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
    border-left: 6px solid var(--gold);
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
    background: rgba(227,181,132,0.1);
    color: var(--gold);
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
}

.filter-btn.reset {
    background: var(--border);
    color: var(--text);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    border-left: 6px solid var(--warning);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.stat-card:nth-child(2) {
    border-left-color: var(--success);
}

.stat-card:nth-child(3) {
    border-left-color: var(--gold);
}

.stat-icon {
    font-size: 20px;
    color: var(--warning);
    margin-bottom: 8px;
}

.stat-card:nth-child(2) .stat-icon {
    color: var(--success);
}

.stat-card:nth-child(3) .stat-icon {
    color: var(--gold);
}

.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 20px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

.table-container {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    overflow: hidden;
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
    padding: 6px 14px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
}

.table-responsive {
    overflow-x: auto;
    margin: 0 -24px;
    padding: 0 24px;
    width: calc(100% + 48px);
    -webkit-overflow-scrolling: touch;
}

.table-responsive::-webkit-scrollbar {
    height: 6px;
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
    min-width: 1000px;
}

th {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    padding: 16px 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 12px;
    text-transform: uppercase;
}

td {
    padding: 16px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

tr:hover td {
    background: var(--primary-soft);
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
}

.status-badge.PENDING {
    background: var(--warning);
    color: #1A2A24;
}

.status-badge.LUNAS {
    background: var(--success);
    color: white;
}

.status-badge.BATAL {
    background: var(--danger);
    color: white;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 8px;
    border-radius: 10px;
    background: white;
    border: 2px solid var(--border);
    color: var(--text);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s;
}

.pagination-btn:hover {
    background: var(--primary-soft);
    border-color: var(--primary);
}

.pagination-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 20px;
}

.empty-state i {
    font-size: 60px;
    color: #E0DAD3;
    margin-bottom: 16px;
}

.empty-state h4 {
    color: var(--text);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--text-muted);
    margin-bottom: 20px;
}

.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 12px !important;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
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
                <option value="PENDING" <?= $status == 'PENDING' ? 'selected' : '' ?>>Pending</option>
                <option value="LUNAS" <?= $status == 'LUNAS' ? 'selected' : '' ?>>Lunas</option>
                <option value="BATAL" <?= $status == 'BATAL' ? 'selected' : '' ?>>Batal</option>
                <option value="ALL" <?= $status == 'ALL' ? 'selected' : '' ?>>Semua</option>
            </select>
            
            <input type="date" name="date_from" class="filter-input" value="<?= $date_from ?>">
            <input type="date" name="date_to" class="filter-input" value="<?= $date_to ?>">
            
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
    
    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Pending</div>
            <div class="stat-value">Rp <?= number_format($total_pending, 0, ',', '.') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Lunas</div>
            <div class="stat-value">Rp <?= number_format($total_lunas, 0, ',', '.') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-label">Total Tagihan</div>
            <div class="stat-value">Rp <?= number_format($total_semua, 0, ',', '.') ?></div>
        </div>
    </div>
    
    <!-- TABLE -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Daftar Hutang Komisi Split</h3>
            <div class="table-badge">
                <i class="fas fa-database"></i> 
                Total: <?= $total_records ?> | Halaman <?= $page ?> dari <?= $total_pages ?>
            </div>
        </div>
        
        <?php if (empty($hutang)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle" style="color: var(--success);"></i>
            <h4>Tidak Ada Hutang</h4>
            <p>Semua komisi split sudah lunas atau belum ada transaksi external</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Unit</th>
                        <th>Nominal</th>
                        <th>Status</th>
                        <th>Jatuh Tempo</th>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hutang as $h): ?>
                    <tr>
                        <td>#<?= $h['id'] ?></td>
                        <td>
                            <?= htmlspecialchars(trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''))) ?>
                            <br>
                            <small><?= htmlspecialchars($h['customer_phone'] ?? '') ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($h['nomor_unit'] ?? '-') ?>
                            <br>
                            <small><?= htmlspecialchars($h['tipe_unit'] ?? '') ?></small>
                        </td>
                        <td>
                            <strong style="color: var(--secondary);">Rp <?= number_format($h['nominal'], 0, ',', '.') ?></strong>
                        </td>
                        <td>
                            <span class="status-badge <?= $h['status'] ?>">
                                <?= $h['status'] ?>
                            </span>
                        </td>
                        <td>
                            <?= $h['jatuh_tempo'] ? date('d/m/Y', strtotime($h['jatuh_tempo'])) : '-' ?>
                        </td>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
                            <?php if ($h['paid_at']): ?>
                            <br><small>Lunas: <?= date('d/m/Y H:i', strtotime($h['paid_at'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($h['catatan'] ?? '-') ?>
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
            <a href="?page=<?= $page-1 ?>&status=<?= $status ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a href="?page=<?= $i ?>&status=<?= $status ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
               class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&status=<?= $status ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Hutang Komisi Split v1.0</p>
    </div>
    
</div>

<script>
function updateDateTime() {
    const now = new Date();
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    const dayName = days[now.getDay()];
    const day = now.getDate();
    const month = months[now.getMonth()];
    const year = now.getFullYear();
    
    document.querySelector('.date span').textContent = dayName + ', ' + day + ' ' + month + ' ' + year;
    
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    document.querySelector('.time span').textContent = hours + ':' + minutes + ':' + seconds;
}

setInterval(updateDateTime, 1000);
updateDateTime();
</script>

<?php include 'includes/footer.php'; ?>