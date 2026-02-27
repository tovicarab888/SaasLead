<?php
/**
 * DEVELOPER_BLOCKS.PHP - LEADENGINE
 * Version: 1.4.0 - FIXED: Format Rupiah Konsisten
 * MOBILE FIRST UI - DENGAN KEYPAD ANGKA UNTUK INPUT NOMINAL
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek session developer
if (!isDeveloper()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$developer_id = $_SESSION['user_id'];
$developer_name = $_SESSION['nama_lengkap'] ?? 'Developer';

// Ambil cluster_id dari URL
$cluster_id = isset($_GET['cluster_id']) ? (int)$_GET['cluster_id'] : 0;

// Validasi cluster milik developer ini
if ($cluster_id > 0) {
    $check = $conn->prepare("SELECT * FROM clusters WHERE id = ? AND developer_id = ?");
    $check->execute([$cluster_id, $developer_id]);
    $cluster = $check->fetch();
    
    if (!$cluster) {
        // Cluster tidak ditemukan atau bukan milik developer
        header('Location: /admin/developer_clusters.php');
        exit();
    }
} else {
    // Jika tidak ada cluster_id, redirect ke daftar cluster
    header('Location: /admin/developer_clusters.php');
    exit();
}

// ========== PROSES CRUD ==========
$success = '';
$error = '';

// Hapus block
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Cek apakah block milik cluster developer ini
    $check = $conn->prepare("
        SELECT b.id FROM blocks b
        JOIN clusters c ON b.cluster_id = c.id
        WHERE b.id = ? AND c.developer_id = ?
    ");
    $check->execute([$delete_id, $developer_id]);
    
    if ($check->fetch()) {
        try {
            // Hapus (ON DELETE CASCADE akan hapus units)
            $stmt = $conn->prepare("DELETE FROM blocks WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "✅ Block berhasil dihapus!";
            logSystem("Block deleted", ['id' => $delete_id], 'INFO', 'block.log');
        } catch (Exception $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    } else {
        $error = "❌ Block tidak ditemukan atau bukan milik Anda";
    }
}

// Tambah/Edit block
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add' || $action == 'edit') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama_block = trim($_POST['nama_block'] ?? '');
        
        if (empty($nama_block)) {
            $error = "❌ Nama block wajib diisi!";
        } else {
            try {
                if ($action == 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO blocks (cluster_id, nama_block, created_at)
                        VALUES (?, ?, NOW())
                    ");
                    $stmt->execute([$cluster_id, $nama_block]);
                    $success = "✅ Block berhasil ditambahkan!";
                    logSystem("Block added", ['name' => $nama_block, 'cluster' => $cluster_id], 'INFO', 'block.log');
                } else {
                    // Cek kepemilikan
                    $check = $conn->prepare("
                        SELECT b.id FROM blocks b
                        JOIN clusters c ON b.cluster_id = c.id
                        WHERE b.id = ? AND c.developer_id = ?
                    ");
                    $check->execute([$id, $developer_id]);
                    
                    if ($check->fetch()) {
                        $stmt = $conn->prepare("
                            UPDATE blocks SET 
                                nama_block = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$nama_block, $id]);
                        $success = "✅ Block berhasil diupdate!";
                        logSystem("Block updated", ['id' => $id], 'INFO', 'block.log');
                    } else {
                        $error = "❌ Block tidak ditemukan atau bukan milik Anda";
                    }
                }
            } catch (Exception $e) {
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    }
}

// Ambil data block milik cluster ini
$blocks = [];
$stmt = $conn->prepare("
    SELECT b.*, 
           (SELECT COUNT(*) FROM units WHERE block_id = b.id) as total_units,
           (SELECT COUNT(*) FROM units WHERE block_id = b.id AND status = 'AVAILABLE') as available_units,
           (SELECT COUNT(*) FROM block_biaya_tambahan WHERE block_id = b.id) as total_biaya
    FROM blocks b
    WHERE b.cluster_id = ?
    ORDER BY b.nama_block ASC
");
$stmt->execute([$cluster_id]);
$blocks = $stmt->fetchAll();

// Hitung statistik
$total_blocks = count($blocks);
$total_units = 0;
$total_available = 0;
$total_biaya = 0;

foreach ($blocks as $b) {
    $total_units += $b['total_units'];
    $total_available += $b['available_units'];
    $total_biaya += $b['total_biaya'];
}

$page_title = 'Kelola Block';
$page_subtitle = $cluster['nama_cluster'];
$page_icon = 'fas fa-cubes';

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
    --biaya: #E9C46A;
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

/* ===== BREADCRUMB ===== */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 13px;
    overflow-x: auto;
    white-space: nowrap;
    padding: 4px 0;
    -webkit-overflow-scrolling: touch;
}

.breadcrumb::-webkit-scrollbar {
    display: none;
}

.breadcrumb a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    padding: 8px 12px;
    background: white;
    border-radius: 40px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.breadcrumb i {
    color: var(--secondary);
    font-size: 12px;
}

.breadcrumb span {
    color: var(--text-muted);
    padding: 8px 12px;
    background: var(--surface);
    border-radius: 40px;
}

/* ===== STATS CARD - HORIZONTAL SCROLL ===== */
.stats-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    padding: 4px 0 16px 0;
    margin-bottom: 8px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

.stats-horizontal::-webkit-scrollbar {
    height: 4px;
}

.stats-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.stats-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.stat-card {
    flex: 0 0 140px;
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

/* ===== ACTION BAR ===== */
.action-bar {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.btn-add {
    width: 100%;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border: none;
    padding: 16px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(214,79,60,0.2);
    transition: all 0.3s;
    min-height: 56px;
}

.btn-add i {
    font-size: 16px;
    width: auto;
    height: auto;
}

.btn-add:active {
    transform: scale(0.98);
}

/* TOMBOL BARU UNTUK BIAYA PER BLOCK */
.btn-biaya {
    width: 100%;
    background: linear-gradient(135deg, #E9C46A, #F0D48C);
    color: #1A2A24;
    border: none;
    padding: 16px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(233,196,106,0.3);
    transition: all 0.3s;
    min-height: 56px;
    text-decoration: none;
}

.btn-biaya i {
    font-size: 16px;
    width: auto;
    height: auto;
    color: #1A2A24;
}

.btn-biaya:active {
    transform: scale(0.98);
}

.btn-back {
    width: 100%;
    background: var(--border);
    color: var(--text);
    border: none;
    padding: 14px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    text-decoration: none;
    min-height: 48px;
}

.btn-back i {
    color: var(--secondary);
}

/* ===== BLOCK CARDS - HORIZONTAL DI MOBILE ===== */
.blocks-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    padding: 8px 0 20px 0;
    margin-bottom: 16px;
    -webkit-overflow-scrolling: touch;
}

.blocks-horizontal::-webkit-scrollbar {
    height: 4px;
}

.blocks-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.blocks-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.block-card {
    flex: 0 0 280px;
    background: white;
    border-radius: 24px;
    padding: 18px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    border-left: 6px solid #4A90E2;
    transition: transform 0.2s;
}

.block-card:active {
    transform: scale(0.98);
}

.block-card.has-biaya {
    border-left-color: #E9C46A;
    background: linear-gradient(135deg, white, #FFF9E6);
}

.block-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.block-name {
    font-weight: 800;
    color: var(--primary);
    font-size: 20px;
    background: var(--primary-soft);
    width: 48px;
    height: 48px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.block-cluster {
    font-size: 13px;
    color: var(--text-muted);
    background: var(--bg);
    padding: 6px 12px;
    border-radius: 30px;
    white-space: nowrap;
}

/* ===== STATS MINI ===== */
.block-stats {
    display: flex;
    gap: 8px;
    margin: 16px 0;
    overflow-x: auto;
    padding: 4px 0;
}

.block-stats::-webkit-scrollbar {
    display: none;
}

.stat-mini {
    flex: 0 0 80px;
    background: var(--primary-soft);
    border-radius: 12px;
    padding: 10px 4px;
    text-align: center;
}

.stat-mini-value {
    font-size: 18px;
    font-weight: 800;
    color: var(--primary);
}

.stat-mini-label {
    font-size: 10px;
    color: var(--text-muted);
    text-transform: uppercase;
}

.stat-mini.available .stat-mini-value {
    color: #2A9D8F;
}

.stat-mini.biaya .stat-mini-value {
    color: #B87C00;
}

/* ===== PROGRESS BAR ===== */
.block-progress {
    margin: 12px 0;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 4px;
}

.progress-bar {
    height: 8px;
    background: var(--border);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2A9D8F, #40BEB0);
    border-radius: 4px;
    transition: width 0.3s;
}

/* ===== ACTION BUTTONS ===== */
.block-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}

.btn-icon {
    flex: 1;
    min-width: 44px;
    min-height: 44px;
    border: none;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    border: 1px solid var(--border);
}

.btn-icon i {
    font-size: 16px;
    width: auto;
    height: auto;
}

.btn-icon.edit {
    background: #fff8e1;
    color: #B87C00;
    border-color: #B87C00;
}

.btn-icon.edit:active {
    background: #B87C00;
    color: white;
}

.btn-icon.delete {
    background: #ffeeed;
    color: #D64F3C;
    border-color: #D64F3C;
}

.btn-icon.delete:active {
    background: #D64F3C;
    color: white;
}

.btn-icon.units {
    background: #e8f0fe;
    color: #1976d2;
    border-color: #1976d2;
}

.btn-icon.units:active {
    background: #1976d2;
    color: white;
}

/* TOMBOL BIAYA PER BLOCK DI CARD */
.btn-icon.biaya {
    background: #FFF9E6;
    color: #B87C00;
    border-color: #E9C46A;
}

.btn-icon.biaya:active {
    background: #E9C46A;
    color: #1A2A24;
}

/* ===== EMPTY STATE ===== */
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

/* ===== MODAL MOBILE FIRST ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    z-index: 10000;
    align-items: flex-end;
    justify-content: center;
    padding: 0;
}

.modal.show {
    display: flex !important;
}

.modal-content {
    background: white;
    border-radius: 28px 28px 0 0;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}

.modal-header {
    padding: 20px 20px 16px;
    border-bottom: 2px solid var(--primary-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    color: var(--primary);
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-header h2 i {
    color: var(--secondary);
    font-size: 20px;
}

.modal-close {
    width: 44px;
    height: 44px;
    background: var(--primary-soft);
    border: none;
    border-radius: 12px;
    color: var(--secondary);
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    max-height: 60vh;
}

.modal-footer {
    padding: 16px 20px 24px;
    display: flex;
    gap: 12px;
    border-top: 1px solid var(--border);
}

.modal-footer button {
    flex: 1;
    min-height: 48px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 15px;
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

/* Untuk keypad angka di mobile */
input[type="text"].rupiah-input,
input[type="number"].rupiah-input,
input[type="text"][inputmode="numeric"],
input[type="number"][inputmode="numeric"] {
    -webkit-appearance: none;
    appearance: none;
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
    
    .action-bar {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .btn-add, .btn-biaya, .btn-back {
        width: auto;
        padding: 14px 28px;
    }
    
    .blocks-horizontal {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        overflow-x: visible;
        gap: 20px;
    }
    
    .block-card {
        flex: none;
        width: auto;
    }
    
    .modal {
        align-items: center;
        padding: 20px;
    }
    
    .modal-content {
        border-radius: 28px;
        max-width: 500px;
        animation: modalFade 0.3s ease;
    }
    
    @keyframes modalFade {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
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
                <span><?= htmlspecialchars($page_subtitle) ?></span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <a href="/admin/developer_clusters.php"><i class="fas fa-layer-group"></i> Cluster</a>
        <i class="fas fa-chevron-right"></i>
        <span><?= htmlspecialchars($cluster['nama_cluster']) ?></span>
    </div>
    
    <!-- STATS HORIZONTAL SCROLL -->
    <div class="stats-horizontal">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-cubes"></i></div>
            <div class="stat-label">Total Block</div>
            <div class="stat-value"><?= $total_blocks ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-home"></i></div>
            <div class="stat-label">Total Unit</div>
            <div class="stat-value"><?= $total_units ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle" style="color: #2A9D8F;"></i></div>
            <div class="stat-label">Available</div>
            <div class="stat-value"><?= $total_available ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #E9C46A;">
            <div class="stat-icon"><i class="fas fa-coins" style="color: #B87C00;"></i></div>
            <div class="stat-label">Total Biaya</div>
            <div class="stat-value"><?= $total_biaya ?></div>
        </div>
    </div>
    
    <!-- ALERT MESSAGES -->
    <?php if ($success): ?>
    <div class="alert success">
        <i class="fas fa-check-circle"></i>
        <?= $success ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle"></i>
        <?= $error ?>
    </div>
    <?php endif; ?>
    
    <!-- ACTION BAR -->
    <div class="action-bar">
        <button class="btn-add" onclick="openAddBlockModal()">
            <i class="fas fa-plus-circle"></i> Tambah Block Baru
        </button>
        
        <!-- TOMBOL BARU: Kelola Biaya per Block (untuk cluster ini) -->
        <a href="/admin/developer_block_biaya.php?cluster_id=<?= $cluster_id ?>" class="btn-biaya">
            <i class="fas fa-coins"></i> Kelola Biaya per Block
        </a>
        
        <a href="/admin/developer_clusters.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Kembali ke Cluster
        </a>
    </div>
    
    <!-- BLOCK CARDS - HORIZONTAL SCROLL DI MOBILE -->
    <?php if (empty($blocks)): ?>
    <div class="empty-state">
        <i class="fas fa-cubes"></i>
        <h4>Belum Ada Block</h4>
        <p>Klik tombol "Tambah Block Baru" untuk membuat block pertama</p>
    </div>
    <?php else: ?>
    <div class="blocks-horizontal">
        <?php foreach ($blocks as $b): 
            $available = $b['available_units'];
            $total = $b['total_units'];
            $sold = $total - $available;
            $progress = $total > 0 ? round($sold / $total * 100) : 0;
            $has_biaya = $b['total_biaya'] > 0;
            $card_class = $has_biaya ? 'has-biaya' : '';
        ?>
        <div class="block-card <?= $card_class ?>">
            <div class="block-header">
                <div class="block-name"><?= htmlspecialchars($b['nama_block']) ?></div>
                <div class="block-cluster"><?= htmlspecialchars($cluster['nama_cluster']) ?></div>
            </div>
            
            <!-- STATS MINI HORIZONTAL -->
            <div class="block-stats">
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $total ?></div>
                    <div class="stat-mini-label">Total</div>
                </div>
                <div class="stat-mini available">
                    <div class="stat-mini-value"><?= $available ?></div>
                    <div class="stat-mini-label">Ready</div>
                </div>
                <div class="stat-mini biaya">
                    <div class="stat-mini-value"><?= $b['total_biaya'] ?></div>
                    <div class="stat-mini-label">Biaya</div>
                </div>
            </div>
            
            <!-- PROGRESS BAR -->
            <div class="block-progress">
                <div class="progress-label">
                    <span>Terjual</span>
                    <span><?= $progress ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $progress ?>%;"></div>
                </div>
            </div>
            
            <!-- ACTION BUTTONS -->
            <div class="block-actions">
                <button class="btn-icon edit" onclick="editBlock(<?= htmlspecialchars(json_encode($b)) ?>)" title="Edit Block">
                    <i class="fas fa-edit"></i>
                </button>
                
                <!-- TOMBOL BARU: Biaya per Block (langsung ke block ini) -->
                <a href="/admin/developer_block_biaya.php?block_id=<?= $b['id'] ?>" class="btn-icon biaya" title="Kelola Biaya Block">
                    <i class="fas fa-coins"></i>
                </a>
                
                <a href="/admin/developer_units.php?block_id=<?= $b['id'] ?>" class="btn-icon units" title="Kelola Unit">
                    <i class="fas fa-home"></i>
                </a>
                
                <button class="btn-icon delete" onclick="confirmDelete(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['nama_block'])) ?>')" title="Hapus Block">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div style="font-size: 10px; color: var(--text-muted); margin-top: 12px; text-align: right;">
                ID: #<?= $b['id'] ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Kelola Block v1.4 (FIXED: Format Rupiah)</p>
    </div>
    
</div>

<!-- MODAL ADD/EDIT BLOCK -->
<div class="modal" id="blockModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-cubes"></i> Tambah Block</h2>
            <button class="modal-close" onclick="closeBlockModal()">&times;</button>
        </div>
        <form method="POST" id="blockForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="blockId" value="0">
            <input type="hidden" name="cluster_id" value="<?= $cluster_id ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nama Block <span class="required">*</span></label>
                    <input type="text" name="nama_block" id="nama_block" class="form-control" placeholder="Contoh: A, B, C, D" required maxlength="50" inputmode="text">
                    <small style="color: var(--text-muted); display: block; margin-top: 6px;">
                        <i class="fas fa-info-circle"></i> Contoh: A, B, C, D, atau Blok A, Blok B
                    </small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeBlockModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan Block</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-trash-alt"></i> Hapus Block</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div style="font-size: 48px; color: var(--danger); margin-bottom: 16px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p style="font-size: 16px; margin-bottom: 16px;">Yakin ingin menghapus block:</p>
            <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; font-weight: 700; font-size: 20px; color: var(--primary); margin-bottom: 16px;" id="deleteBlockName"></div>
            <p style="color: var(--text-muted); font-size: 13px;">
                <i class="fas fa-info-circle"></i> Semua unit dalam block ini akan ikut terhapus.
            </p>
            <input type="hidden" id="deleteBlockId">
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeDeleteModal()">Batal</button>
            <button class="btn-primary" style="background: var(--danger);" onclick="processDelete()">Ya, Hapus</button>
        </div>
    </div>
</div>

<script>
// ===== FUNGSI MODAL =====
function openAddBlockModal() {
    console.log('openAddBlockModal dipanggil');
    
    // Reset form untuk add
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-cubes"></i> Tambah Block';
    document.getElementById('formAction').value = 'add';
    document.getElementById('blockId').value = '0';
    document.getElementById('nama_block').value = '';
    
    // Tampilkan modal
    document.getElementById('blockModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function editBlock(block) {
    console.log('editBlock dipanggil:', block);
    
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Block';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('blockId').value = block.id;
    document.getElementById('nama_block').value = block.nama_block;
    
    document.getElementById('blockModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeBlockModal() {
    document.getElementById('blockModal').classList.remove('show');
    document.body.style.overflow = '';
}

function confirmDelete(id, name) {
    document.getElementById('deleteBlockId').value = id;
    document.getElementById('deleteBlockName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

function processDelete() {
    const id = document.getElementById('deleteBlockId').value;
    if (id) {
        window.location.href = '?cluster_id=<?= $cluster_id ?>&delete=' + id;
    }
}

// ===== CLOSE MODAL ON OVERLAY CLICK =====
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// ===== DATE TIME =====
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

// ===== PREVENT FORM RESUBMISSION =====
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php include 'includes/footer.php'; ?>