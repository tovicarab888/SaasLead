<?php
/**
 * FINANCE_PLATFORM_REKENING.PHP - Verifikasi Rekening External Marketing
 * Version: 2.0.0 - UI SUPER KEREN (Mempertahankan UI Referensi)
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// ========== CHECK AUTH ==========
if (!checkAuth()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Hanya finance platform yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'finance_platform') {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Finance Platform.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== PROSES VERIFIKASI ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $marketing_id = (int)($_POST['marketing_id'] ?? 0);
    
    if ($action === 'verifikasi' && $marketing_id > 0) {
        $verified = (int)($_POST['verified'] ?? 1);
        $catatan = trim($_POST['catatan'] ?? '');
        
        try {
            $update = $conn->prepare("
                UPDATE marketing_team SET 
                    rekening_verified = ?,
                    rekening_verified_at = NOW(),
                    rekening_verified_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$verified, $_SESSION['user_id'], $marketing_id]);
            
            if ($verified) {
                $success = "✅ Rekening marketing berhasil diverifikasi";
            } else {
                $success = "✅ Status verifikasi rekening dibatalkan";
            }
            
            // Kirim notifikasi ke marketing
            if ($verified) {
                $stmt = $conn->prepare("
                    SELECT nama_lengkap, phone, nomor_rekening, atas_nama_rekening, nama_bank_rekening 
                    FROM marketing_team WHERE id = ?
                ");
                $stmt->execute([$marketing_id]);
                $marketing = $stmt->fetch();
                
                if ($marketing && function_exists('sendMarketingNotification')) {
                    $notif_data = [
                        'full_name' => $marketing['nama_lengkap'],
                        'first_name' => $marketing['nama_lengkap'],
                        'phone' => $marketing['phone']
                    ];
                    $location = ['display_name' => 'Sistem', 'location_key' => 'system'];
                    
                    $marketing['notification_template'] = "✅ *REKENING ANDA TELAH TERVERIFIKASI!*\n\n"
                        . "Halo *{marketing_name}*,\n\n"
                        . "Rekening Anda telah berhasil diverifikasi oleh tim finance.\n\n"
                        . "🏦 *Detail Rekening:*\n"
                        . "Bank: {$marketing['nama_bank_rekening']}\n"
                        . "Nomor: {$marketing['nomor_rekening']}\n"
                        . "Atas Nama: {$marketing['atas_nama_rekening']}\n\n"
                        . "Sekarang Anda dapat menerima pembayaran komisi.\n\n"
                        . "Terima kasih,\n"
                        . "*Finance Platform*";
                    
                    sendMarketingNotification($marketing, $notif_data, $location);
                }
            }
            
        } catch (Exception $e) {
            $error = "❌ Gagal verifikasi: " . $e->getMessage();
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
                WHERE id = ?
            ");
            $update->execute([$nama_bank, $nomor_rekening, $atas_nama, $marketing_id]);
            
            $success = "✅ Data rekening berhasil diupdate, menunggu verifikasi ulang";
            
        } catch (Exception $e) {
            $error = "❌ Gagal update: " . $e->getMessage();
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
        COUNT(kl.id) as total_komisi,
        COALESCE(SUM(kl.komisi_final), 0) as total_nominal
    FROM marketing_team m
    LEFT JOIN komisi_logs kl ON m.id = kl.marketing_id AND kl.assigned_type = 'external'
    WHERE m.marketing_type_id IN (SELECT id FROM marketing_types WHERE type_name = 'external')
";
$params = [];

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
            COUNT(kl.id) as total_komisi,
            COALESCE(SUM(kl.komisi_final), 0) as total_nominal,
            GROUP_CONCAT(DISTINCT u.nama_lengkap SEPARATOR ', ') as developer_names
        FROM marketing_team m
        LEFT JOIN komisi_logs kl ON m.id = kl.marketing_id AND kl.assigned_type = 'external'
        LEFT JOIN users u ON kl.developer_id = u.id
        WHERE m.id = ?
        GROUP BY m.id
    ");
    $detail_stmt->execute([$detail_id]);
    $detail_marketing = $detail_stmt->fetch();
    
    // Ambil riwayat komisi
    $riwayat_stmt = $conn->prepare("
        SELECT 
            kl.*,
            l.first_name,
            l.last_name,
            u.nama_lengkap as developer_name,
            un.tipe_unit,
            un.nomor_unit
        FROM komisi_logs kl
        LEFT JOIN leads l ON kl.lead_id = l.id
        LEFT JOIN users u ON kl.developer_id = u.id
        LEFT JOIN units un ON kl.unit_id = un.id
        WHERE kl.marketing_id = ? AND kl.assigned_type = 'external'
        ORDER BY kl.created_at DESC
        LIMIT 10
    ");
    $riwayat_stmt->execute([$detail_id]);
    $riwayat_komisi = $riwayat_stmt->fetchAll();
}

// ========== SET VARIABLES ==========
$page_title = 'Verifikasi Rekening External';
$page_subtitle = 'Verifikasi Rekening Marketing External';
$page_icon = 'fas fa-university';

include 'includes/header.php';
?>

<!-- SIDEBAR -->
<?php include 'includes/sidebar.php'; ?>

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

/* ===== VIEW ITEM ===== */
.view-item {
    background: var(--primary-soft);
    padding: 16px;
    border-radius: 16px;
    margin-bottom: 12px;
}

.view-item-label {
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.view-item-label i {
    color: var(--secondary);
}

.view-item-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    word-break: break-word;
}

/* ===== DETAIL CARD ===== */
.detail-card {
    background: white;
    border-radius: 24px;
    padding: 20px;
    margin-bottom: 24px;
    border: 2px solid var(--success);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
}

/* ===== FORM ELEMENTS ===== */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--primary);
    font-size: 14px;
}

.form-group label i {
    color: var(--secondary);
    margin-right: 6px;
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    background: white;
    min-height: 52px;
}

.form-control:focus {
    border-color: var(--secondary);
    outline: none;
}

/* ===== BUTTONS ===== */
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 52px;
}

.btn-secondary {
    background: var(--border);
    color: var(--text);
    border: none;
    padding: 14px 20px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    min-height: 52px;
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #FF6B4A);
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 52px;
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #40BEB0);
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 52px;
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
    <div class="alert success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <!-- FILTER FORM -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <select name="verified" class="filter-select" style="max-width: 200px;">
                <option value="0" <?= $verified === 0 ? 'selected' : '' ?>>Belum Verifikasi</option>
                <option value="1" <?= $verified === 1 ? 'selected' : '' ?>>Sudah Verifikasi</option>
                <option value="">Semua</option>
            </select>
            
            <input type="text" name="search" class="filter-input" placeholder="Cari nama / No. HP / No. Rekening" value="<?= htmlspecialchars($search) ?>">
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
                <a href="?" class="filter-btn reset"><i class="fas fa-redo"></i> Reset</a>
            </div>
        </form>
    </div>
    
    <!-- DETAIL MARKETING IF ID EXISTS -->
    <?php if ($detail_marketing): ?>
    <div class="detail-card">
        <div class="table-header">
            <h3><i class="fas fa-user-circle"></i> Detail Marketing External</h3>
        </div>
        
        <div class="detail-grid">
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-user"></i> Nama</div>
                <div class="view-item-value"><?= htmlspecialchars($detail_marketing['nama_lengkap']) ?></div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fab fa-whatsapp"></i> WhatsApp</div>
                <div class="view-item-value"><a href="https://wa.me/<?= $detail_marketing['phone'] ?>" target="_blank" style="color: #25D366;"><?= $detail_marketing['phone'] ?></a></div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-envelope"></i> Email</div>
                <div class="view-item-value"><?= $detail_marketing['email'] ?: '-' ?></div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-power-off"></i> Status</div>
                <div class="view-item-value">
                    <span class="status-badge <?= $detail_marketing['is_active'] ? 'active' : 'inactive' ?>">
                        <?= $detail_marketing['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                </div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-coins"></i> Total Komisi</div>
                <div class="view-item-value"><?= $detail_marketing['total_komisi'] ?> transaksi</div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-money-bill-wave"></i> Total Nominal</div>
                <div class="view-item-value" style="color: var(--success); font-weight: 700;">Rp <?= number_format($detail_marketing['total_nominal'], 0, ',', '.') ?></div>
            </div>
        </div>
        
        <!-- FORM UPDATE REKENING -->
        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border);">
            <h4 style="margin-bottom: 20px; color: var(--primary);">Data Rekening</h4>
            
            <form method="POST" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                <input type="hidden" name="marketing_id" value="<?= $detail_marketing['id'] ?>">
                
                <div class="form-group">
                    <label>Nama Bank</label>
                    <input type="text" name="nama_bank" class="form-control" value="<?= htmlspecialchars($detail_marketing['nama_bank_rekening'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Nomor Rekening</label>
                    <input type="text" name="nomor_rekening" class="form-control" value="<?= htmlspecialchars($detail_marketing['nomor_rekening'] ?? '') ?>">
                </div>
                
                <div class="form-group" style="grid-column: 1/-1;">
                    <label>Atas Nama</label>
                    <input type="text" name="atas_nama" class="form-control" value="<?= htmlspecialchars($detail_marketing['atas_nama_rekening'] ?? '') ?>">
                </div>
                
                <div style="grid-column: 1/-1; display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                    <button type="submit" name="action" value="update_rekening" class="btn-primary">
                        <i class="fas fa-save"></i> Update Data Rekening
                    </button>
                    
                    <?php if (!$detail_marketing['rekening_verified'] && !empty($detail_marketing['nomor_rekening'])): ?>
                    <button type="submit" name="action" value="verifikasi" class="btn-success">
                        <i class="fas fa-check-circle"></i> Verifikasi Rekening
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($detail_marketing['rekening_verified']): ?>
                    <button type="submit" name="action" value="verifikasi" class="btn-danger">
                        <input type="hidden" name="verified" value="0">
                        <i class="fas fa-times-circle"></i> Batalkan Verifikasi
                    </button>
                    <?php endif; ?>
                    
                    <a href="?" class="btn-secondary">Kembali</a>
                </div>
            </form>
        </div>
        
        <!-- RIWAYAT KOMISI -->
        <?php if (!empty($riwayat_komisi)): ?>
        <div style="margin-top: 30px;">
            <h4 style="margin-bottom: 15px; color: var(--primary);">Riwayat Komisi</h4>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Developer</th>
                            <th>Customer</th>
                            <th>Unit</th>
                            <th>Komisi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat_komisi as $rk): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($rk['created_at'])) ?></td>
                            <td><?= htmlspecialchars($rk['developer_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($rk['first_name'] ?? '') ?></td>
                            <td><?= $rk['tipe_unit'] ?> - <?= $rk['nomor_unit'] ?></td>
                            <td><strong style="color: var(--success);">Rp <?= number_format($rk['komisi_final'], 0, ',', '.') ?></strong></td>
                            <td>
                                <span class="status-badge <?= $rk['status'] ?>">
                                    <?= strtoupper($rk['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- TABLE MARKETING -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-users"></i> Daftar Marketing External</h3>
            <div class="table-badge">Total: <?= number_format($total_records) ?> | Halaman <?= $page ?> dari <?= $total_pages ?></div>
        </div>
        
        <?php if (empty($marketings)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>Tidak ada data marketing external</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Marketing</th>
                        <th>Kontak</th>
                        <th>Data Rekening</th>
                        <th>Status Verifikasi</th>
                        <th>Total Komisi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marketings as $m): ?>
                    <tr>
                        <td>#<?= $m['id'] ?></td>
                        <td><strong><?= htmlspecialchars($m['nama_lengkap']) ?></strong></td>
                        <td>
                            <?= $m['phone'] ?><br>
                            <small style="color: var(--text-muted);"><?= $m['email'] ?></small>
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
                                <span class="status-badge active">Terverifikasi</span>
                                <?php else: ?>
                                <span class="status-badge pending">Menunggu</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="status-badge inactive">Belum Input</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $m['total_komisi'] ?> komisi<br>
                            <strong style="color: var(--success);">Rp <?= number_format($m['total_nominal'], 0, ',', '.') ?></strong>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="?id=<?= $m['id'] ?>&verified=<?= $verified ?>&search=<?= urlencode($search) ?>" class="action-btn view" title="Detail">
                                    <i class="fas fa-eye"></i>
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
        <p>© <?= date('Y') ?> LeadEngine - Finance Platform Rekening v2.0</p>
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