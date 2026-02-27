<?php
/**
 * DEVELOPER_BLOCK_BIAYA.PHP - LEADENGINE
 * Version: 2.1.0 - FIXED: Format Rupiah Konsisten saat Edit
 * MOBILE FIRST UI - INPUT RUPIAH OTOMATIS + KEYPAD ANGKA
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

// ========== AMBIL SEMUA CLUSTER + BLOCK ==========
$clusters = [];
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM blocks WHERE cluster_id = c.id) as total_blocks
    FROM clusters c
    WHERE c.developer_id = ?
    ORDER BY c.nama_cluster
");
$stmt->execute([$developer_id]);
$clusters = $stmt->fetchAll();

// ========== PROSES CRUD ==========
$success = '';
$error = '';

// Hapus biaya block
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    $check = $conn->prepare("
        SELECT bb.id FROM block_biaya_tambahan bb
        JOIN blocks b ON bb.block_id = b.id
        JOIN clusters c ON b.cluster_id = c.id
        WHERE bb.id = ? AND c.developer_id = ?
    ");
    $check->execute([$delete_id, $developer_id]);
    
    if ($check->fetch()) {
        try {
            $stmt = $conn->prepare("DELETE FROM block_biaya_tambahan WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "✅ Biaya block berhasil dihapus!";
            logSystem("Block biaya deleted", ['id' => $delete_id], 'INFO', 'block_biaya.log');
        } catch (Exception $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    } else {
        $error = "❌ Data tidak ditemukan atau bukan milik Anda";
    }
}

// Tambah/Edit biaya block
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add' || $action == 'edit') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $block_id = (int)($_POST['block_id'] ?? 0);
        $nama_biaya = trim($_POST['nama_biaya'] ?? '');
        $nominal = !empty($_POST['nominal']) ? (float)$_POST['nominal'] : 0;
        $tipe_biaya = $_POST['tipe_biaya'] ?? 'tetap';
        $keterangan = trim($_POST['keterangan'] ?? '');
        
        if ($block_id <= 0) {
            $error = "❌ Pilih block terlebih dahulu!";
        } elseif (empty($nama_biaya)) {
            $error = "❌ Nama biaya wajib diisi!";
        } elseif ($nominal <= 0) {
            $error = "❌ Nominal harus lebih dari 0!";
        } else {
            try {
                $check_block = $conn->prepare("
                    SELECT b.id FROM blocks b
                    JOIN clusters c ON b.cluster_id = c.id
                    WHERE b.id = ? AND c.developer_id = ?
                ");
                $check_block->execute([$block_id, $developer_id]);
                
                if (!$check_block->fetch()) {
                    $error = "❌ Block tidak valid atau bukan milik Anda!";
                } else {
                    if ($action == 'add') {
                        $stmt = $conn->prepare("
                            INSERT INTO block_biaya_tambahan (
                                block_id, nama_biaya, nominal, tipe_biaya, keterangan, created_at
                            ) VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$block_id, $nama_biaya, $nominal, $tipe_biaya, $keterangan]);
                        $success = "✅ Biaya block berhasil ditambahkan!";
                        logSystem("Block biaya added", ['block' => $block_id, 'biaya' => $nama_biaya], 'INFO', 'block_biaya.log');
                    } else {
                        $check = $conn->prepare("
                            SELECT bb.id FROM block_biaya_tambahan bb
                            JOIN blocks b ON bb.block_id = b.id
                            JOIN clusters c ON b.cluster_id = c.id
                            WHERE bb.id = ? AND c.developer_id = ?
                        ");
                        $check->execute([$id, $developer_id]);
                        
                        if ($check->fetch()) {
                            $stmt = $conn->prepare("
                                UPDATE block_biaya_tambahan SET 
                                    block_id = ?,
                                    nama_biaya = ?,
                                    nominal = ?,
                                    tipe_biaya = ?,
                                    keterangan = ?,
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([$block_id, $nama_biaya, $nominal, $tipe_biaya, $keterangan, $id]);
                            $success = "✅ Biaya block berhasil diupdate!";
                            logSystem("Block biaya updated", ['id' => $id], 'INFO', 'block_biaya.log');
                        } else {
                            $error = "❌ Data tidak ditemukan atau bukan milik Anda";
                        }
                    }
                }
            } catch (Exception $e) {
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    }
}

// Ambil semua biaya block
$block_biayas = [];
$stmt = $conn->prepare("
    SELECT 
        bb.*,
        b.nama_block,
        c.nama_cluster,
        c.id as cluster_id
    FROM block_biaya_tambahan bb
    JOIN blocks b ON bb.block_id = b.id
    JOIN clusters c ON b.cluster_id = c.id
    WHERE c.developer_id = ?
    ORDER BY c.nama_cluster, b.nama_block, bb.nama_biaya
");
$stmt->execute([$developer_id]);
$block_biayas = $stmt->fetchAll();

$grouped_biayas = [];
foreach ($block_biayas as $bb) {
    $cluster_key = $bb['cluster_id'];
    if (!isset($grouped_biayas[$cluster_key])) {
        $grouped_biayas[$cluster_key] = [
            'cluster_name' => $bb['nama_cluster'],
            'items' => []
        ];
    }
    $grouped_biayas[$cluster_key]['items'][] = $bb;
}

$page_title = 'Biaya per Block';
$page_subtitle = 'Setting Biaya Tambahan Berdasarkan Block';
$page_icon = 'fas fa-cubes';

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
    --biaya: #E9C46A;
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

.cluster-accordion {
    background: white;
    border-radius: 20px;
    margin-bottom: 16px;
    overflow: hidden;
    border: 1px solid var(--border);
}

.cluster-header {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    padding: 16px 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
}

.cluster-header.active {
    border-bottom-color: var(--secondary);
    background: linear-gradient(135deg, #d4e8e0, var(--primary-soft));
}

.cluster-icon {
    width: 48px;
    height: 48px;
    background: white;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    flex-shrink: 0;
}

.cluster-info {
    flex: 1;
}

.cluster-name {
    font-weight: 700;
    color: var(--primary);
    font-size: 18px;
    margin-bottom: 4px;
}

.cluster-stats {
    font-size: 12px;
    color: var(--text-muted);
    display: flex;
    gap: 15px;
}

.cluster-stats i {
    color: var(--secondary);
    margin-right: 4px;
}

.cluster-chevron {
    color: var(--secondary);
    font-size: 18px;
    transition: transform 0.3s;
    flex-shrink: 0;
}

.cluster-content {
    padding: 20px;
    display: none;
    background: white;
}

.blocks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.block-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    border: 1px solid var(--border);
    border-left: 4px solid var(--info);
    box-shadow: 0 4px 8px rgba(0,0,0,0.03);
}

.block-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.block-name {
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
}

.btn-add-biaya {
    background: var(--success);
    color: white;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-add-biaya:hover {
    transform: scale(1.1);
}

.biaya-list {
    margin-top: 12px;
}

.biaya-item {
    background: var(--primary-soft);
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 3px solid var(--secondary);
}

.biaya-info {
    flex: 1;
}

.biaya-nama {
    font-weight: 600;
    color: var(--primary);
    font-size: 13px;
    margin-bottom: 2px;
}

.biaya-nominal {
    font-weight: 700;
    color: var(--secondary);
    font-size: 14px;
}

.biaya-tipe {
    font-size: 10px;
    color: var(--text-muted);
    background: white;
    padding: 2px 8px;
    border-radius: 20px;
    display: inline-block;
    margin-top: 4px;
}

.biaya-actions {
    display: flex;
    gap: 5px;
}

.btn-icon-small {
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    border: 1px solid var(--border);
}

.btn-icon-small.edit {
    color: #B87C00;
}

.btn-icon-small.edit:hover {
    background: #B87C00;
    color: white;
}

.btn-icon-small.delete {
    color: var(--danger);
}

.btn-icon-small.delete:hover {
    background: var(--danger);
    color: white;
}

.empty-biaya {
    text-align: center;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    background: var(--bg);
    border-radius: 10px;
    border: 1px dashed var(--border);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 24px;
}

.empty-state i {
    font-size: 60px;
    color: #E0DAD3;
    margin-bottom: 16px;
}

/* ===== MODAL STYLES ===== */
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

.form-control, .form-select {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    background: white;
    min-height: 52px;
}

.form-control:focus, .form-select:focus {
    border-color: var(--secondary);
    outline: none;
}

/* ===== INPUT RUPIAH STYLES ===== */
.rupiah-input {
    -webkit-appearance: none;
    appearance: none;
}

input[type="text"].rupiah-input,
input[type="text"][inputmode="numeric"] {
    -webkit-appearance: none;
    appearance: none;
}

textarea.form-control {
    min-height: 80px;
    resize: vertical;
}

.radio-group {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

.radio-option {
    flex: 1;
}

.radio-option input[type="radio"] {
    display: none;
}

.radio-option label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    background: white;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 52px;
}

.radio-option input[type="radio"]:checked + label {
    border-color: var(--secondary);
    background: linear-gradient(135deg, rgba(214,79,60,0.05), rgba(255,107,74,0.05));
}

.radio-option label i {
    color: var(--secondary);
    font-size: 16px;
}

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
    
    .action-bar {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .btn-add {
        width: auto;
        padding: 14px 28px;
    }
    
    .modal {
        align-items: center;
        padding: 20px;
    }
    
    .modal-content {
        border-radius: 28px;
        max-width: 600px;
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
        <button class="btn-add" onclick="openAddBiayaModal()">
            <i class="fas fa-plus-circle"></i> Tambah Biaya Block
        </button>
    </div>
    
    <!-- CLUSTER ACCORDIONS -->
    <?php if (empty($clusters)): ?>
    <div class="empty-state">
        <i class="fas fa-cubes"></i>
        <h4>Belum Ada Cluster</h4>
        <p>Buat cluster terlebih dahulu di menu Kelola Cluster</p>
        <a href="/admin/developer_clusters.php" class="btn-primary" style="display: inline-block; margin-top: 16px; text-decoration: none; padding: 12px 24px;">
            <i class="fas fa-layer-group"></i> Kelola Cluster
        </a>
    </div>
    <?php else: ?>
        <?php foreach ($clusters as $index => $cluster): 
            $cluster_biayas = isset($grouped_biayas[$cluster['id']]) ? $grouped_biayas[$cluster['id']]['items'] : [];
            
            $blocks = [];
            $stmt = $conn->prepare("SELECT * FROM blocks WHERE cluster_id = ? ORDER BY nama_block");
            $stmt->execute([$cluster['id']]);
            $blocks = $stmt->fetchAll();
        ?>
        <div class="cluster-accordion">
            <div class="cluster-header" onclick="toggleCluster(<?= $index ?>)">
                <div class="cluster-icon"><?= $cluster['icon'] ?? '🏢' ?></div>
                <div class="cluster-info">
                    <div class="cluster-name"><?= htmlspecialchars($cluster['nama_cluster']) ?></div>
                    <div class="cluster-stats">
                        <span><i class="fas fa-cubes"></i> <?= count($blocks) ?> Block</span>
                        <span><i class="fas fa-coins"></i> <?= count($cluster_biayas) ?> Biaya</span>
                    </div>
                </div>
                <i class="fas fa-chevron-down cluster-chevron" id="chevron_<?= $index ?>"></i>
            </div>
            <div class="cluster-content" id="cluster_<?= $index ?>">
                
                <?php if (empty($blocks)): ?>
                <div class="empty-state" style="padding: 30px;">
                    <p>Belum ada block dalam cluster ini</p>
                </div>
                <?php else: ?>
                
                <div class="blocks-grid">
                    <?php foreach ($blocks as $block): 
                        $block_biayas = array_filter($cluster_biayas, fn($b) => $b['block_id'] == $block['id']);
                    ?>
                    <div class="block-card">
                        <div class="block-header">
                            <span class="block-name">Block <?= htmlspecialchars($block['nama_block']) ?></span>
                            <button class="btn-add-biaya" onclick="openAddBiayaModal(<?= $block['id'] ?>, '<?= htmlspecialchars($cluster['nama_cluster']) ?> - Block <?= htmlspecialchars($block['nama_block']) ?>')" title="Tambah Biaya">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        
                        <div class="biaya-list">
                            <?php if (empty($block_biayas)): ?>
                            <div class="empty-biaya">
                                <i class="fas fa-info-circle"></i> Belum ada biaya tambahan
                            </div>
                            <?php else: ?>
                                <?php foreach ($block_biayas as $bb): 
                                    $tipe_text = $bb['tipe_biaya'] == 'tetap' ? 'Tetap' : ($bb['tipe_biaya'] == 'per_m2' ? 'Per m²' : 'Persen');
                                ?>
                                <div class="biaya-item">
                                    <div class="biaya-info">
                                        <div class="biaya-nama"><?= htmlspecialchars($bb['nama_biaya']) ?></div>
                                        <div class="biaya-nominal">Rp <?= number_format($bb['nominal'], 0, ',', '.') ?></div>
                                        <span class="biaya-tipe"><?= $tipe_text ?></span>
                                        <?php if (!empty($bb['keterangan'])): ?>
                                        <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($bb['keterangan']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="biaya-actions">
                                        <button class="btn-icon-small edit" onclick="editBiaya(<?= htmlspecialchars(json_encode($bb)) ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon-small delete" onclick="confirmDelete(<?= $bb['id'] ?>, '<?= htmlspecialchars(addslashes($bb['nama_biaya'])) ?>')" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Biaya per Block v2.1 (FIXED: Format Rupiah)</p>
    </div>
    
</div>

<!-- MODAL ADD/EDIT BIAYA BLOCK -->
<div class="modal" id="biayaModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-plus-circle"></i> Tambah Biaya Block</h2>
            <button class="modal-close" onclick="closeBiayaModal()">&times;</button>
        </div>
        <form method="POST" id="biayaForm" onsubmit="return prepareNominal()">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="biayaId" value="0">
            <input type="hidden" name="nominal" id="nominal_hidden" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-cubes"></i> Pilih Block <span class="required">*</span></label>
                    <select name="block_id" id="block_id" class="form-select" required>
                        <option value="">— Pilih Block —</option>
                        <?php foreach ($clusters as $cluster): 
                            $blocks = [];
                            $stmt = $conn->prepare("SELECT * FROM blocks WHERE cluster_id = ? ORDER BY nama_block");
                            $stmt->execute([$cluster['id']]);
                            $blocks = $stmt->fetchAll();
                            if (!empty($blocks)):
                        ?>
                        <optgroup label="<?= htmlspecialchars($cluster['nama_cluster']) ?>">
                            <?php foreach ($blocks as $block): ?>
                            <option value="<?= $block['id'] ?>">
                                <?= htmlspecialchars($cluster['nama_cluster']) ?> - Block <?= htmlspecialchars($block['nama_block']) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nama Biaya <span class="required">*</span></label>
                    <input type="text" name="nama_biaya" id="nama_biaya" class="form-control" placeholder="Contoh: Biaya Premium Block" required maxlength="100">
                </div>
                
                <!-- INPUT RUPIAH DENGAN AUTO-FORMAT -->
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Nominal (Rp) <span class="required">*</span></label>
                    <input type="text" name="nominal_display" id="nominal_display" class="form-control rupiah-input" 
                           placeholder="Rp 1.500.000" required inputmode="numeric" 
                           onkeyup="formatRupiahInput(this)" onblur="formatRupiahBlur(this)">
                    <small style="color: var(--text-muted); display: block; margin-top: 6px;">
                        <i class="fas fa-info-circle"></i> Contoh: 1500000 akan otomatis jadi Rp 1.500.000
                    </small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tasks"></i> Tipe Biaya</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" name="tipe_biaya" id="tipe_tetap" value="tetap" checked>
                            <label for="tipe_tetap"><i class="fas fa-check-circle"></i> Tetap</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="tipe_biaya" id="tipe_per_m2" value="per_m2">
                            <label for="tipe_per_m2"><i class="fas fa-arrows-alt"></i> Per m²</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="tipe_biaya" id="tipe_persen" value="persen">
                            <label for="tipe_persen"><i class="fas fa-percent"></i> Persen</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> Keterangan (opsional)</label>
                    <textarea name="keterangan" id="keterangan" class="form-control" rows="2" placeholder="Contoh: Khusus block hook, hadap jalan utama..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeBiayaModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan Biaya</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-trash-alt"></i> Hapus Biaya</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div style="font-size: 48px; color: var(--danger); margin-bottom: 16px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p style="font-size: 16px; margin-bottom: 16px;">Yakin ingin menghapus biaya:</p>
            <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; font-weight: 700; font-size: 16px; color: var(--primary); margin-bottom: 16px;" id="deleteBiayaName"></div>
            <input type="hidden" id="deleteBiayaId">
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeDeleteModal()">Batal</button>
            <button class="btn-primary" style="background: var(--danger);" onclick="processDelete()">Ya, Hapus</button>
        </div>
    </div>
</div>

<script>
// ===== FUNGSI FORMAT RUPIAH KONSISTEN =====
function formatRupiah(angka, prefix = 'Rp ') {
    if (!angka && angka !== 0) return prefix + '0';
    
    // Pastikan angka adalah number
    let num = typeof angka === 'string' ? parseFloat(angka) : angka;
    if (isNaN(num)) return prefix + '0';
    
    // Format ke string dengan pemisah ribuan (.)
    let number_string = Math.floor(num).toString();
    let sisa = number_string.length % 3;
    let rupiah = number_string.substr(0, sisa);
    let ribuan = number_string.substr(sisa).match(/\d{3}/g);
    
    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    
    // Jika ada desimal
    let desimal = (num % 1).toFixed(2).substring(1);
    if (desimal !== '.00') {
        rupiah += ',' + desimal.substring(2);
    }
    
    return prefix + rupiah;
}

function parseRupiah(rupiah) {
    if (!rupiah) return 0;
    // Hapus 'Rp ' dan semua titik, ganti koma dengan titik untuk desimal
    let number = rupiah.toString()
        .replace(/[Rr]p\s?/g, '')
        .replace(/\./g, '')
        .replace(/,/g, '.');
    
    let parsed = parseFloat(number);
    return isNaN(parsed) ? 0 : parsed;
}

function formatRupiahInput(input) {
    let cursorPos = input.selectionStart;
    let value = input.value;
    let rawValue = value.replace(/[^0-9,]/g, '');
    
    if (rawValue) {
        // Pisahkan bagian integer dan desimal
        let parts = rawValue.split(',');
        let integer = parts[0].replace(/^0+/, '') || '0';
        let decimal = parts[1] || '';
        
        // Format integer dengan titik ribuan
        let formattedInteger = '';
        for (let i = 0; i < integer.length; i++) {
            if (i > 0 && (integer.length - i) % 3 === 0) {
                formattedInteger += '.';
            }
            formattedInteger += integer[i];
        }
        
        if (decimal) {
            input.value = formattedInteger + ',' + decimal;
        } else {
            input.value = formattedInteger;
        }
        
        // Kembalikan cursor ke posisi yang sesuai
        let newLength = input.value.length;
        let diff = newLength - value.length;
        input.setSelectionRange(cursorPos + diff, cursorPos + diff);
    }
}

function formatRupiahBlur(input) {
    let value = parseRupiah(input.value);
    if (value > 0) {
        input.value = formatRupiah(value, '').replace('Rp ', '');
    } else {
        input.value = '';
    }
}

// Prepare form submission
function prepareNominal() {
    const display = document.getElementById('nominal_display');
    if (display) {
        const value = parseRupiah(display.value);
        document.getElementById('nominal_hidden').value = value;
        
        if (value <= 0) {
            alert('Nominal harus lebih dari 0');
            return false;
        }
    }
    return true;
}

// Inisialisasi semua input rupiah
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.rupiah-input').forEach(input => {
        let value = input.value;
        // Jika value adalah angka murni (dari database), format
        if (value && !isNaN(value) && value.toString().indexOf('.') === -1) {
            input.value = formatRupiah(parseInt(value), '').replace('Rp ', '');
        }
    });
});

// ===== ACCORDION FUNCTIONS =====
function toggleCluster(index) {
    const content = document.getElementById('cluster_' + index);
    const chevron = document.getElementById('chevron_' + index);
    const header = content.previousElementSibling;
    
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        chevron.style.transform = 'rotate(180deg)';
        header.classList.add('active');
    } else {
        content.style.display = 'none';
        chevron.style.transform = 'rotate(0deg)';
        header.classList.remove('active');
    }
}

setTimeout(() => {
    const firstContent = document.getElementById('cluster_0');
    const firstChevron = document.getElementById('chevron_0');
    if (firstContent && firstChevron) {
        firstContent.style.display = 'block';
        firstChevron.style.transform = 'rotate(180deg)';
        firstContent.previousElementSibling.classList.add('active');
    }
}, 100);

// ===== MODAL FUNCTIONS =====
function openAddBiayaModal(blockId = null, blockLabel = '') {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Tambah Biaya Block';
    document.getElementById('formAction').value = 'add';
    document.getElementById('biayaId').value = '0';
    document.getElementById('nama_biaya').value = '';
    document.getElementById('nominal_display').value = '';
    document.getElementById('nominal_hidden').value = '';
    document.getElementById('tipe_tetap').checked = true;
    document.getElementById('keterangan').value = '';
    
    if (blockId) {
        document.getElementById('block_id').value = blockId;
    } else {
        document.getElementById('block_id').value = '';
    }
    
    document.getElementById('biayaModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function editBiaya(biaya) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Biaya';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('biayaId').value = biaya.id;
    document.getElementById('block_id').value = biaya.block_id;
    document.getElementById('nama_biaya').value = biaya.nama_biaya;
    
    // 🔥 FIX: Format nominal dengan benar
    let nominal = parseFloat(biaya.nominal);
    document.getElementById('nominal_display').value = formatRupiah(nominal, '').replace('Rp ', '');
    document.getElementById('nominal_hidden').value = nominal;
    
    if (biaya.tipe_biaya === 'tetap') document.getElementById('tipe_tetap').checked = true;
    else if (biaya.tipe_biaya === 'per_m2') document.getElementById('tipe_per_m2').checked = true;
    else if (biaya.tipe_biaya === 'persen') document.getElementById('tipe_persen').checked = true;
    
    document.getElementById('keterangan').value = biaya.keterangan || '';
    
    document.getElementById('biayaModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeBiayaModal() {
    document.getElementById('biayaModal').classList.remove('show');
    document.body.style.overflow = '';
}

function confirmDelete(id, name) {
    document.getElementById('deleteBiayaId').value = id;
    document.getElementById('deleteBiayaName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

function processDelete() {
    const id = document.getElementById('deleteBiayaId').value;
    if (id) {
        window.location.href = '?delete=' + id;
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