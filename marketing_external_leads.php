<?php
/**
 * MARKETING_EXTERNAL_LEADS.PHP - Daftar Leads untuk Marketing External
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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Hitung total
$count_sql = "
    SELECT COUNT(*) 
    FROM leads l
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    WHERE l.assigned_marketing_team_id = ? 
      AND l.assigned_type = 'external'
      AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
";
$count_params = [$user_id];

if (!empty($search)) {
    $count_sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ?)";
    $s = "%$search%";
    $count_params = array_merge($count_params, [$s, $s, $s]);
}

if (!empty($status_filter)) {
    $count_sql .= " AND l.status = ?";
    $count_params[] = $status_filter;
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Ambil data
$sql = "
    SELECT 
        l.*,
        loc.display_name as location_display,
        loc.icon
    FROM leads l
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    WHERE l.assigned_marketing_team_id = ? 
      AND l.assigned_type = 'external'
      AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
";
$params = [$user_id];

if (!empty($search)) {
    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}

if (!empty($status_filter)) {
    $sql .= " AND l.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Ambil daftar status untuk filter
$status_list = $conn->query("
    SELECT DISTINCT status FROM leads 
    WHERE assigned_type = 'external' 
    ORDER BY FIELD(status, 
        'Baru', 'Follow Up', 'Survey', 'Booking', 
        'Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun',
        'Tolak Slik', 'Tidak Minat', 'Batal'
    )
")->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Leads Saya';
$page_subtitle = 'Daftar leads yang ditugaskan';
$page_icon = 'fas fa-users';

include 'includes/header.php';
include 'includes/sidebar_marketing_external.php';
?>

<style>
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
        grid-template-columns: repeat(4, 1fr);
    }
}

.filter-item {
    width: 100%;
}

.filter-item label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--primary);
}

.filter-item select,
.filter-item input {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-family: inherit;
    font-size: 0.9rem;
}

.filter-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

/* ===== TABLE STYLES ===== */
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

/* ===== STATUS BADGE ===== */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-badge.Baru { background: #4A90E2; color: white; }
.status-badge.Follow\ Up { background: #E9C46A; color: #1A2A24; }
.status-badge.Survey { background: #E9C46A; color: #1A2A24; }
.status-badge.Booking { background: #1B4A3C; color: white; }
.status-badge.Tolak\ Slik { background: #9C27B0; color: white; }
.status-badge.Tidak\ Minat { background: #757575; color: white; }
.status-badge.Batal { background: #D64F3C; color: white; }
.status-badge.Deal\ KPR { background: #2A9D8F; color: white; }
.status-badge.Deal\ Tunai { background: #FF9800; color: white; }
.status-badge.Deal\ Bertahap\ 6\ Bulan { background: #2A9D8F; color: white; }
.status-badge.Deal\ Bertahap\ 1\ Tahun { background: #2A9D8F; color: white; }

/* ===== ACTION BUTTONS ===== */
.action-buttons {
    display: flex;
    gap: 6px;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: white;
    color: var(--text);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
}

.btn-icon:hover {
    background: var(--primary-soft);
    color: var(--secondary);
}

.btn-icon.whatsapp {
    background: #e8f5e9;
    color: #25D366;
    border-color: #25D366;
}

.btn-icon.whatsapp:hover {
    background: #25D366;
    color: white;
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

.pagination-btn:hover:not(.active) {
    background: var(--primary-soft);
    border-color: var(--secondary);
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

/* ===== DESKTOP UPGRADE ===== */
@media (min-width: 1024px) {
    .main-content {
        margin-left: 280px !important;
        padding: 24px !important;
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
    
    <!-- FILTER SECTION -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-grid">
                <div class="filter-item">
                    <label><i class="fas fa-search"></i> Cari</label>
                    <input type="text" name="search" class="filter-input" value="<?= htmlspecialchars($search) ?>" placeholder="Nama / No. WA">
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-tag"></i> Status</label>
                    <select name="status" class="filter-select">
                        <option value="">Semua Status</option>
                        <?php foreach ($status_list as $s): ?>
                        <option value="<?= $s ?>" <?= $status_filter == $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
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
            <h3><i class="fas fa-list"></i> Daftar Leads (<?= number_format($total_records) ?>)</h3>
            <div class="table-info">
                <span class="badge">Halaman <?= $page ?> dari <?= $total_pages ?></span>
            </div>
        </div>
        
        <?php if (empty($leads)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Belum ada leads yang ditugaskan</p>
        </div>
        <?php else: ?>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Kontak</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Unit</th>
                        <th>Program</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): 
                        $full_name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
                    ?>
                    <tr>
                        <td><strong>#<?= $lead['id'] ?></strong></td>
                        <td><?= htmlspecialchars($full_name ?: 'Tanpa Nama') ?></td>
                        <td>
                            <?= htmlspecialchars($lead['phone']) ?><br>
                            <small><?= htmlspecialchars($lead['email'] ?? '-') ?></small>
                        </td>
                        <td><?= htmlspecialchars($lead['location_display'] ?? $lead['location_key']) ?></td>
                        <td>
                            <span class="status-badge" style="background: <?php
                                switch($lead['status']) {
                                    case 'Baru': echo '#4A90E2'; break;
                                    case 'Follow Up': echo '#E9C46A'; break;
                                    case 'Survey': echo '#E9C46A'; break;
                                    case 'Booking': echo '#1B4A3C'; break;
                                    case 'Deal KPR': echo '#2A9D8F'; break;
                                    case 'Deal Tunai': echo '#FF9800'; break;
                                    case 'Deal Bertahap 6 Bulan': echo '#2A9D8F'; break;
                                    case 'Deal Bertahap 1 Tahun': echo '#2A9D8F'; break;
                                    case 'Tolak Slik': echo '#9C27B0'; break;
                                    case 'Tidak Minat': echo '#757575'; break;
                                    case 'Batal': echo '#D64F3C'; break;
                                    default: echo '#757575';
                                }
                            ?>; color: white;">
                                <?= $lead['status'] ?>
                            </span>
                        </td>
                        <td><strong><?= $lead['lead_score'] ?></strong></td>
                        <td><?= htmlspecialchars($lead['unit_type'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($lead['program'] ?? '-') ?></td>
                        <td><?= date('d/m/Y', strtotime($lead['created_at'])) ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="marketing_external_lead_detail.php?id=<?= $lead['id'] ?>" class="btn-icon" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="https://wa.me/<?= $lead['phone'] ?>" target="_blank" class="btn-icon whatsapp" title="WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
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
            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++): 
            ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" 
               class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Marketing External Leads</p>
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