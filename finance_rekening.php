<?php
/**
 * FINANCE_DEVELOPER_REKENING.PHP - Verifikasi Rekening Marketing Internal
 * Version: 2.0.0 - Mobile First UI
 * FULL CODE - 100% LENGKAP
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

// ========== PROSES VERIFIKASI ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $marketing_id = (int)($_POST['marketing_id'] ?? 0);
    
    if ($action === 'verifikasi' && $marketing_id > 0) {
        $verified = (int)($_POST['verified'] ?? 1);
        
        try {
            $update = $conn->prepare("
                UPDATE marketing_team SET 
                    rekening_verified = ?,
                    rekening_verified_at = NOW(),
                    rekening_verified_by = ?,
                    updated_at = NOW()
                WHERE id = ? AND developer_id = ?
            ");
            $update->execute([$verified, $_SESSION['user_id'], $marketing_id, $developer_id]);
            
            if ($verified) {
                $success = "Rekening marketing berhasil diverifikasi";
            } else {
                $success = "Status verifikasi rekening dibatalkan";
            }
        } catch (Exception $e) {
            $error = "Gagal verifikasi: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'update_rekening' && $marketing_id > 0) {
        $nama_bank = trim($_POST['nama_bank'] ?? '');
        $nomor_rekening = trim($_POST['nomor_rekening'] ?? '');
        $atas_nama = trim($_POST['atas_nama'] ?? '');
        
        try {
            $update = $conn->prepare("
                UPDATE marketing_team SET 
                    nama_bank_rekening = ?,
                    nomor_rekening = ?,
                    atas_nama_rekening = ?,
                    rekening_verified = 0,
                    updated_at = NOW()
                WHERE id = ? AND developer_id = ?
            ");
            $update->execute([$nama_bank, $nomor_rekening, $atas_nama, $marketing_id, $developer_id]);
            
            $success = "Data rekening berhasil diupdate, menunggu verifikasi ulang";
        } catch (Exception $e) {
            $error = "Gagal update: " . $e->getMessage();
        }
    }
}

// ========== FILTER ==========
$verified = isset($_GET['verified']) ? (int)$_GET['verified'] : 0;
$search = trim($_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// ========== BUILD QUERY ==========
$sql = "
    SELECT 
        m.*,
        COUNT(k.id) as total_komisi,
        COALESCE(SUM(k.komisi_final), 0) as total_nominal
    FROM marketing_team m
    LEFT JOIN komisi_logs k ON m.id = k.marketing_id AND k.assigned_type = 'internal'
    WHERE m.developer_id = ?
";
$params = [$developer_id];

if ($verified === 1) {
    $sql .= " AND m.rekening_verified = 1";
} elseif ($verified === 0) {
    $sql .= " AND (m.rekening_verified = 0 OR m.rekening_verified IS NULL) AND m.nomor_rekening IS NOT NULL AND m.nomor_rekening != ''";
}

if (!empty($search)) {
    $sql .= " AND (m.nama_lengkap LIKE ? OR m.phone LIKE ? OR m.nomor_rekening LIKE ? OR m.nama_bank_rekening LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}

$sql .= " GROUP BY m.id ORDER BY m.rekening_verified ASC, m.nama_lengkap ASC";

// Count total
$count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as sub";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get data with pagination
$sql .= " LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$marketings = $stmt->fetchAll();

// ========== AMBIL DETAIL MARKETING JIKA ADA ID ==========
$detail_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$detail_marketing = null;

if ($detail_id > 0) {
    $detail_stmt = $conn->prepare("
        SELECT 
            m.*,
            COUNT(k.id) as total_komisi,
            COALESCE(SUM(k.komisi_final), 0) as total_nominal
        FROM marketing_team m
        LEFT JOIN komisi_logs k ON m.id = k.marketing_id AND k.assigned_type = 'internal'
        WHERE m.id = ? AND m.developer_id = ?
        GROUP BY m.id
    ");
    $detail_stmt->execute([$detail_id, $developer_id]);
    $detail_marketing = $detail_stmt->fetch();
}

$page_title = 'Verifikasi Rekening Marketing';
$page_subtitle = 'Marketing Internal';
$page_icon = 'fas fa-university';

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
    --finance: #2A9D8F;
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
    border-left: 6px solid var(--finance);
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
    background: rgba(42,157,143,0.1);
    color: var(--finance);
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

/* ===== TABLE STYLES ===== */
.table-container {
    background: var(--surface);
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
    overflow-x: auto;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.table-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-header h3 i {
    color: var(--finance);
}

.table-pagination {
    font-size: 12px;
    color: var(--text-muted);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

th {
    text-align: left;
    padding: 12px 8px;
    background: var(--primary-soft);
    color: var(--primary);
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

td {
    padding: 12px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

.status-badge.verified {
    background: var(--success);
}

.status-badge.unverified {
    background: var(--warning);
    color: #1A2A24;
}

.status-badge.no-rekening {
    background: var(--danger);
}

.btn-icon {
    width: 32px;
    height: 32px;
    border: none;
    background: none;
    border-radius: 8px;
    cursor: pointer;
    color: var(--text-light);
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.btn-icon:hover {
    background: var(--primary-soft);
    color: var(--finance);
}

/* ===== DETAIL CARD ===== */
.detail-card {
    background: var(--surface);
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 20px;
    border: 2px solid var(--success);
}

.detail-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.detail-avatar {
    width: 60px;
    height: 60px;
    background: var(--finance);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
}

.detail-title h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
}

.detail-title p {
    font-size: 13px;
    color: var(--text-muted);
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.detail-item {
    background: var(--bg);
    padding: 15px;
    border-radius: 16px;
}

.detail-item label {
    display: block;
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 5px;
}

.detail-item .value {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
}

.detail-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-action {
    flex: 1;
    min-width: 120px;
    padding: 14px;
    border: none;
    border-radius: 40px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

.btn-action.success {
    background: var(--success);
    color: white;
}

.btn-action.danger {
    background: var(--danger);
    color: white;
}

.btn-action.secondary {
    background: var(--bg);
    color: var(--text);
}

/* ===== PAGINATION ===== */
.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.pagination-btn {
    min-width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    text-decoration: none;
    font-size: 13px;
}

.pagination-btn.active {
    background: var(--finance);
    color: white;
    border-color: var(--finance);
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 40px 20px;
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

/* ===== TABLET & DESKTOP ===== */
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
    
    .filter-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .detail-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .table-container {
        padding: 20px;
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
    
    <!-- ALERT MESSAGES -->
    <?php if ($success): ?>
    <div style="background: #d4edda; color: #155724; padding: 16px; border-radius: 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px;">
        <i class="fas fa-check-circle fa-lg"></i>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 16px; border-radius: 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px;">
        <i class="fas fa-exclamation-circle fa-lg"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>
    
    <!-- FILTER SECTION -->
    <div class="filter-section">
        <form method="GET" id="filterForm">
            <div class="filter-grid">
                <div class="filter-item">
                    <label>Status Verifikasi</label>
                    <select name="verified">
                        <option value="0" <?= $verified === 0 ? 'selected' : '' ?>>Belum Verifikasi</option>
                        <option value="1" <?= $verified === 1 ? 'selected' : '' ?>>Sudah Verifikasi</option>
                        <option value="">Semua</option>
                    </select>
                </div>
                
                <div class="filter-item" style="grid-column: span 2;">
                    <label>Cari</label>
                    <input type="text" name="search" placeholder="Nama / No. HP / No. Rekening..." value="<?= htmlspecialchars($search) ?>">
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
    
    <!-- DETAIL MARKETING IF ID EXISTS -->
    <?php if ($detail_marketing): ?>
    <div class="detail-card">
        <div class="detail-header">
            <div class="detail-avatar">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="detail-title">
                <h4><?= htmlspecialchars($detail_marketing['nama_lengkap']) ?></h4>
                <p><?= $detail_marketing['phone'] ?> • <?= $detail_marketing['username'] ?></p>
            </div>
        </div>
        
        <div class="detail-grid">
            <div class="detail-item">
                <label>Status Verifikasi</label>
                <div class="value">
                    <?php if ($detail_marketing['rekening_verified']): ?>
                    <span style="color: var(--success);"><i class="fas fa-check-circle"></i> Terverifikasi</span>
                    <?php else: ?>
                    <span style="color: var(--warning);"><i class="fas fa-clock"></i> Menunggu Verifikasi</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="detail-item">
                <label>Total Komisi</label>
                <div class="value"><?= $detail_marketing['total_komisi'] ?> transaksi</div>
                <div style="font-size: 14px; font-weight: 600; color: var(--secondary);">Rp <?= number_format($detail_marketing['total_nominal'], 0, ',', '.') ?></div>
            </div>
            
            <div class="detail-item">
                <label>Nama Bank</label>
                <div class="value"><?= htmlspecialchars($detail_marketing['nama_bank_rekening'] ?? '-') ?></div>
            </div>
            
            <div class="detail-item">
                <label>Nomor Rekening</label>
                <div class="value"><?= htmlspecialchars($detail_marketing['nomor_rekening'] ?? '-') ?></div>
            </div>
            
            <div class="detail-item" style="grid-column: 1/-1;">
                <label>Atas Nama</label>
                <div class="value"><?= htmlspecialchars($detail_marketing['atas_nama_rekening'] ?? '-') ?></div>
            </div>
        </div>
        
        <div class="detail-actions">
            <?php if (!$detail_marketing['rekening_verified'] && !empty($detail_marketing['nomor_rekening'])): ?>
            <form method="POST" style="flex: 1;">
                <input type="hidden" name="marketing_id" value="<?= $detail_marketing['id'] ?>">
                <input type="hidden" name="action" value="verifikasi">
                <input type="hidden" name="verified" value="1">
                <button type="submit" class="btn-action success">
                    <i class="fas fa-check-circle"></i> Verifikasi Rekening
                </button>
            </form>
            <?php endif; ?>
            
            <?php if ($detail_marketing['rekening_verified']): ?>
            <form method="POST" style="flex: 1;">
                <input type="hidden" name="marketing_id" value="<?= $detail_marketing['id'] ?>">
                <input type="hidden" name="action" value="verifikasi">
                <input type="hidden" name="verified" value="0">
                <button type="submit" class="btn-action danger">
                    <i class="fas fa-times-circle"></i> Batalkan Verifikasi
                </button>
            </form>
            <?php endif; ?>
            
            <a href="?" class="btn-action secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
        
        <!-- Form Update Rekening -->
        <form method="POST" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
            <input type="hidden" name="marketing_id" value="<?= $detail_marketing['id'] ?>">
            <input type="hidden" name="action" value="update_rekening">
            
            <h5 style="margin-bottom: 15px; font-size: 14px;">Update Data Rekening</h5>
            
            <div style="display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 15px;">
                <input type="text" name="nama_bank" class="form-control" placeholder="Nama Bank" value="<?= htmlspecialchars($detail_marketing['nama_bank_rekening'] ?? '') ?>">
                <input type="text" name="nomor_rekening" class="form-control" placeholder="Nomor Rekening" value="<?= htmlspecialchars($detail_marketing['nomor_rekening'] ?? '') ?>">
                <input type="text" name="atas_nama" class="form-control" placeholder="Atas Nama" value="<?= htmlspecialchars($detail_marketing['atas_nama_rekening'] ?? '') ?>">
            </div>
            
            <button type="submit" class="btn-filter primary" style="width: 100%;">
                <i class="fas fa-save"></i> Update Data Rekening
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- TABLE MARKETING -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-users"></i> Daftar Marketing Internal</h3>
            <div class="table-pagination">
                Total: <?= number_format($total_records) ?> | Halaman <?= $page ?> dari <?= $total_pages ?>
            </div>
        </div>
        
        <?php if (empty($marketings)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Tidak ada data marketing</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Marketing</th>
                    <th>Kontak</th>
                    <th>Data Rekening</th>
                    <th>Status</th>
                    <th>Total Komisi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($marketings as $m): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($m['nama_lengkap']) ?></strong><br>
                        <small><?= $m['username'] ?></small>
                    </td>
                    <td>
                        <?= $m['phone'] ?><br>
                        <small><?= $m['email'] ?></small>
                    </td>
                    <td>
                        <?php if ($m['nomor_rekening']): ?>
                            <?= $m['nama_bank_rekening'] ?><br>
                            <?= $m['nomor_rekening'] ?><br>
                            <small>a/n <?= $m['atas_nama_rekening'] ?></small>
                        <?php else: ?>
                            <span style="color: var(--danger);">Belum input</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($m['nomor_rekening']): ?>
                            <?php if ($m['rekening_verified']): ?>
                            <span class="status-badge verified">Terverifikasi</span>
                            <?php else: ?>
                            <span class="status-badge unverified">Menunggu</span>
                            <?php endif; ?>
                        <?php else: ?>
                        <span class="status-badge no-rekening">Belum Input</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $m['total_komisi'] ?> komisi<br>
                        <strong>Rp <?= number_format($m['total_nominal'], 0, ',', '.') ?></strong>
                    </td>
                    <td>
                        <a href="?id=<?= $m['id'] ?>&verified=<?= $verified ?>&search=<?= urlencode($search) ?>" class="btn-icon" title="Detail">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&verified=<?= $verified ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?page=<?= $i ?>&verified=<?= $verified ?>&search=<?= urlencode($search) ?>" 
               class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&verified=<?= $verified ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Finance Developer Rekening v2.0</p>
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