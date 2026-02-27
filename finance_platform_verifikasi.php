<?php
/**
 * FINANCE_PLATFORM_VERIFIKASI.PHP - Verifikasi Booking External
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
    $komisi_id = (int)($_POST['komisi_id'] ?? 0);
    
    if ($action === 'verifikasi' && $komisi_id > 0) {
        $status = $_POST['status'] ?? 'cair';
        $catatan = trim($_POST['catatan'] ?? '');
        $bukti_transfer = '';
        
        // Upload bukti transfer
        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = UPLOAD_PATH . 'bukti_transfer/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $ext = pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION);
            $filename = 'bukti_' . $komisi_id . '_' . time() . '.' . $ext;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $destination)) {
                $bukti_transfer = 'bukti_transfer/' . $filename;
            }
        }
        
        try {
            $conn->beginTransaction();
            
            // Update komisi_logs
            $update = $conn->prepare("
                UPDATE komisi_logs SET 
                    status = ?,
                    tanggal_cair = NOW(),
                    bukti_transfer = ?,
                    catatan = CONCAT(IFNULL(catatan, ''), ?),
                    updated_at = NOW()
                WHERE id = ? AND assigned_type = 'external'
            ");
            $catatan_full = "\n[Verifikasi oleh Finance Platform " . date('d/m/Y H:i') . "] " . $catatan;
            $update->execute([$status, $bukti_transfer, $catatan_full, $komisi_id]);
            
            // Ambil data komisi untuk notifikasi
            $stmt = $conn->prepare("
                SELECT 
                    kl.*,
                    l.first_name,
                    l.last_name,
                    l.phone as customer_phone,
                    u.nama_lengkap as developer_name,
                    m.nama_lengkap as marketing_name,
                    m.phone as marketing_phone,
                    un.nomor_unit,
                    un.tipe_unit,
                    un.harga
                FROM komisi_logs kl
                LEFT JOIN leads l ON kl.lead_id = l.id
                LEFT JOIN users u ON kl.developer_id = u.id
                LEFT JOIN marketing_team m ON kl.marketing_id = m.id
                LEFT JOIN units un ON kl.unit_id = un.id
                WHERE kl.id = ?
            ");
            $stmt->execute([$komisi_id]);
            $komisi = $stmt->fetch();
            
            // Kirim notifikasi ke marketing
            if ($komisi && $status === 'cair') {
                $komisi_data = [
                    'customer_name' => $komisi['first_name'] . ' ' . ($komisi['last_name'] ?? ''),
                    'unit_info' => $komisi['tipe_unit'] . ' - ' . $komisi['nomor_unit'],
                    'harga' => $komisi['harga'],
                    'komisi_final' => $komisi['komisi_final'],
                    'developer_name' => $komisi['developer_name']
                ];
                
                $marketing_data = [
                    'id' => $komisi['marketing_id'],
                    'nama_lengkap' => $komisi['marketing_name'],
                    'phone' => $komisi['marketing_phone']
                ];
                
                // Ambil data rekening marketing
                $rek_stmt = $conn->prepare("
                    SELECT nama_bank, nomor_rekening, atas_nama 
                    FROM marketing_team 
                    WHERE id = ?
                ");
                $rek_stmt->execute([$komisi['marketing_id']]);
                $rekening = $rek_stmt->fetch();
                
                if (function_exists('sendKomisiNotificationToMarketing')) {
                    sendKomisiNotificationToMarketing($komisi_data, $marketing_data, $rekening);
                }
            }
            
            $conn->commit();
            $success = "✅ Komisi berhasil diverifikasi dan status diubah menjadi " . strtoupper($status);
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "❌ Gagal verifikasi: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'tolak' && $komisi_id > 0) {
        $alasan = trim($_POST['alasan'] ?? '');
        
        try {
            $update = $conn->prepare("
                UPDATE komisi_logs SET 
                    status = 'batal',
                    catatan = CONCAT(IFNULL(catatan, ''), ?),
                    updated_at = NOW()
                WHERE id = ? AND assigned_type = 'external'
            ");
            $catatan = "\n[Ditolak oleh Finance Platform " . date('d/m/Y H:i') . "] " . $alasan;
            $update->execute([$catatan, $komisi_id]);
            
            $success = "✅ Komisi berhasil ditolak";
            
        } catch (Exception $e) {
            $error = "❌ Gagal menolak: " . $e->getMessage();
        }
    }
}

// ========== FILTER ==========
$status = $_GET['status'] ?? 'pending';
$developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
$search = trim($_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// ========== BUILD QUERY ==========
$sql = "
    SELECT 
        kl.*,
        l.first_name,
        l.last_name,
        l.phone as customer_phone,
        l.location_key,
        loc.display_name as location_name,
        u.id as developer_id,
        u.nama_lengkap as developer_name,
        u.nama_perusahaan,
        m.id as marketing_id,
        m.nama_lengkap as marketing_name,
        m.phone as marketing_phone,
        un.nomor_unit,
        un.tipe_unit,
        un.harga
    FROM komisi_logs kl
    LEFT JOIN leads l ON kl.lead_id = l.id
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    LEFT JOIN users u ON kl.developer_id = u.id
    LEFT JOIN marketing_team m ON kl.marketing_id = m.id
    LEFT JOIN units un ON kl.unit_id = un.id
    WHERE kl.assigned_type = 'external'
";
$params = [];

if ($status !== 'all') {
    $sql .= " AND kl.status = ?";
    $params[] = $status;
}

if ($developer_id > 0) {
    $sql .= " AND kl.developer_id = ?";
    $params[] = $developer_id;
}

if (!empty($search)) {
    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR m.nama_lengkap LIKE ? OR u.nama_lengkap LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}

// Count total
$count_sql = str_replace(
    "SELECT 
        kl.*,
        l.first_name,
        l.last_name,
        l.phone as customer_phone,
        l.location_key,
        loc.display_name as location_name,
        u.id as developer_id,
        u.nama_lengkap as developer_name,
        u.nama_perusahaan,
        m.id as marketing_id,
        m.nama_lengkap as marketing_name,
        m.phone as marketing_phone,
        un.nomor_unit,
        un.tipe_unit,
        un.harga",
    "SELECT COUNT(*)",
    $sql
);
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get data
$sql .= " ORDER BY kl.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$komisi_list = $stmt->fetchAll();

// ========== AMBIL DATA DEVELOPER UNTUK FILTER ==========
$developers = $conn->query("
    SELECT DISTINCT u.id, u.nama_lengkap 
    FROM komisi_logs kl
    JOIN users u ON kl.developer_id = u.id
    WHERE kl.assigned_type = 'external'
    ORDER BY u.nama_lengkap
")->fetchAll();

// ========== AMBIL DETAIL KOMISI JIKA ADA ID ==========
$detail_komisi = null;
$detail_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($detail_id > 0) {
    $detail_stmt = $conn->prepare("
        SELECT 
            kl.*,
            l.first_name,
            l.last_name,
            l.phone as customer_phone,
            l.email as customer_email,
            l.location_key,
            loc.display_name as location_name,
            u.id as developer_id,
            u.nama_lengkap as developer_name,
            u.nama_perusahaan,
            m.id as marketing_id,
            m.nama_lengkap as marketing_name,
            m.phone as marketing_phone,
            m.nomor_rekening,
            m.atas_nama_rekening,
            m.nama_bank_rekening,
            un.nomor_unit,
            un.tipe_unit,
            un.harga,
            un.luas_tanah,
            un.luas_bangunan
        FROM komisi_logs kl
        LEFT JOIN leads l ON kl.lead_id = l.id
        LEFT JOIN locations loc ON l.location_key = loc.location_key
        LEFT JOIN users u ON kl.developer_id = u.id
        LEFT JOIN marketing_team m ON kl.marketing_id = m.id
        LEFT JOIN units un ON kl.unit_id = un.id
        WHERE kl.id = ? AND kl.assigned_type = 'external'
    ");
    $detail_stmt->execute([$detail_id]);
    $detail_komisi = $detail_stmt->fetch();
}

// ========== SET VARIABLES ==========
$page_title = 'Verifikasi Komisi External';
$page_subtitle = 'Verifikasi dan Cairkan Komisi Marketing External';
$page_icon = 'fas fa-check-circle';

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
    min-width: 800px;
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
            <select name="status" class="filter-select" style="max-width: 150px;">
                <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="cair" <?= $status == 'cair' ? 'selected' : '' ?>>Sudah Cair</option>
                <option value="batal" <?= $status == 'batal' ? 'selected' : '' ?>>Batal</option>
                <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>Semua</option>
            </select>
            
            <select name="developer_id" class="filter-select">
                <option value="">Semua Developer</option>
                <?php foreach ($developers as $dev): ?>
                <option value="<?= $dev['id'] ?>" <?= $developer_id == $dev['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dev['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <input type="text" name="search" class="filter-input" placeholder="Cari nama customer/marketing/developer" value="<?= htmlspecialchars($search) ?>">
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
                <a href="?" class="filter-btn reset"><i class="fas fa-redo"></i> Reset</a>
            </div>
        </form>
    </div>
    
    <!-- DETAIL KOMISI IF ID EXISTS -->
    <?php if ($detail_komisi): ?>
    <div class="detail-card">
        <div class="table-header">
            <h3><i class="fas fa-file-invoice"></i> Detail Komisi #<?= $detail_komisi['id'] ?></h3>
        </div>
        
        <div class="detail-grid">
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-user"></i> Nama Customer</div>
                <div class="view-item-value"><?= htmlspecialchars($detail_komisi['first_name'] . ' ' . ($detail_komisi['last_name'] ?? '')) ?></div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fab fa-whatsapp"></i> WhatsApp</div>
                <div class="view-item-value"><a href="https://wa.me/<?= $detail_komisi['customer_phone'] ?>" target="_blank" style="color: #25D366;"><?= $detail_komisi['customer_phone'] ?></a></div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-map-marker-alt"></i> Lokasi</div>
                <div class="view-item-value"><?= htmlspecialchars($detail_komisi['location_name'] ?? $detail_komisi['location_key']) ?></div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-home"></i> Unit</div>
                <div class="view-item-value"><?= $detail_komisi['tipe_unit'] ?> - <?= $detail_komisi['nomor_unit'] ?: '-' ?></div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-building"></i> Developer</div>
                <div class="view-item-value"><?= htmlspecialchars($detail_komisi['developer_name'] ?? '-') ?></div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-user-tie"></i> Marketing</div>
                <div class="view-item-value"><?= htmlspecialchars($detail_komisi['marketing_name'] ?? '-') ?></div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-coins"></i> Komisi Final</div>
                <div class="view-item-value" style="font-size: 24px; font-weight: 800; color: var(--success);">Rp <?= number_format($detail_komisi['komisi_final'], 0, ',', '.') ?></div>
            </div>
            <div class="view-item">
                <div class="view-item-label"><i class="fas fa-tag"></i> Status</div>
                <div class="view-item-value">
                    <span class="status-badge <?= $detail_komisi['status'] ?>">
                        <?= strtoupper($detail_komisi['status']) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <?php if ($detail_komisi['status'] == 'pending'): ?>
        <!-- FORM VERIFIKASI -->
        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border);">
            <h4 style="margin-bottom: 20px; color: var(--primary);">Verifikasi Komisi</h4>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="komisi_id" value="<?= $detail_komisi['id'] ?>">
                
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="status" value="cair" checked style="width: 18px; height: 18px; accent-color: var(--success);"> 
                            <span style="font-weight: 600;">Cairkan Komisi</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="status" value="pending" style="width: 18px; height: 18px; accent-color: var(--warning);"> 
                            <span style="font-weight: 600;">Tunda</span>
                        </label>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label><i class="fas fa-upload"></i> Upload Bukti Transfer</label>
                        <input type="file" name="bukti_transfer" class="form-control" accept="image/*,.pdf" required>
                        <small style="color: var(--text-muted);">Format: JPG, PNG, PDF. Maks 2MB</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Catatan (opsional)</label>
                        <input type="text" name="catatan" class="form-control" placeholder="Catatan verifikasi">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" name="action" value="verifikasi" class="btn-primary">
                        <i class="fas fa-check-circle"></i> Verifikasi & Cairkan
                    </button>
                    <button type="submit" name="action" value="tolak" class="btn-danger" onclick="return confirm('Yakin menolak komisi ini?')">
                        <i class="fas fa-times-circle"></i> Tolak
                    </button>
                    <a href="?" class="btn-secondary">Kembali</a>
                </div>
            </form>
        </div>
        <?php elseif ($detail_komisi['status'] == 'cair' && $detail_komisi['bukti_transfer']): ?>
        <div style="margin-top: 20px;">
            <a href="/admin/uploads/<?= $detail_komisi['bukti_transfer'] ?>" target="_blank" class="btn-primary">
                <i class="fas fa-file"></i> Lihat Bukti Transfer
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($detail_komisi['catatan']): ?>
        <div style="margin-top: 20px; background: var(--primary-soft); padding: 16px; border-radius: 16px;">
            <strong>Catatan:</strong>
            <p style="margin-top: 8px; white-space: pre-line;"><?= nl2br(htmlspecialchars($detail_komisi['catatan'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- TABLE KOMISI -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Daftar Komisi External</h3>
            <div class="table-badge">Total: <?= number_format($total_records) ?> | Halaman <?= $page ?> dari <?= $total_pages ?></div>
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
                        <th>Marketing</th>
                        <th>Developer</th>
                        <th>Customer</th>
                        <th>Unit</th>
                        <th>Komisi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($komisi_list as $komisi): ?>
                    <tr>
                        <td>#<?= $komisi['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($komisi['marketing_name'] ?? '-') ?></strong><br>
                            <small style="color: var(--text-muted);"><?= $komisi['marketing_phone'] ?></small>
                        </td>
                        <td><?= htmlspecialchars($komisi['developer_name'] ?? '-') ?></td>
                        <td>
                            <?= htmlspecialchars($komisi['first_name'] ?? '') ?><br>
                            <small style="color: var(--text-muted);"><?= $komisi['customer_phone'] ?></small>
                        </td>
                        <td><?= $komisi['tipe_unit'] ?><br><small><?= $komisi['nomor_unit'] ?></small></td>
                        <td><strong style="color: var(--success);">Rp <?= number_format($komisi['komisi_final'], 0, ',', '.') ?></strong></td>
                        <td>
                            <span class="status-badge <?= $komisi['status'] ?>">
                                <?= strtoupper($komisi['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="?id=<?= $komisi['id'] ?>&status=<?= $status ?>&developer_id=<?= $developer_id ?>&search=<?= urlencode($search) ?>" class="action-btn view" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($komisi['status'] == 'pending'): ?>
                                <a href="?id=<?= $komisi['id'] ?>" class="action-btn edit" title="Verifikasi">
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
            <a href="?page=<?= $page-1 ?>&status=<?= $status ?>&developer_id=<?= $developer_id ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= $status ?>&developer_id=<?= $developer_id ?>&search=<?= urlencode($search) ?>" 
               class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&status=<?= $status ?>&developer_id=<?= $developer_id ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Finance Platform Verifikasi v2.0</p>
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