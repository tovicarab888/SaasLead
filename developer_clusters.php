<?php
/**
 * DEVELOPER_CLUSTERS.PHP - LEADENGINE
 * Version: 1.2.0 - FIXED: Modal Add Cluster + Link Block
 * MOBILE FIRST UI - NO VERTICAL CARDS, ICON TIDAK GEPENG
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

// ========== PROSES CRUD ==========
$success = '';
$error = '';

// Hapus cluster
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Cek apakah cluster milik developer ini
    $check = $conn->prepare("SELECT id FROM clusters WHERE id = ? AND developer_id = ?");
    $check->execute([$delete_id, $developer_id]);
    
    if ($check->fetch()) {
        try {
            // Hapus (ON DELETE CASCADE akan hapus blocks & units)
            $stmt = $conn->prepare("DELETE FROM clusters WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "✅ Cluster berhasil dihapus!";
            logSystem("Cluster deleted", ['id' => $delete_id, 'developer' => $developer_id], 'INFO', 'cluster.log');
        } catch (Exception $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    } else {
        $error = "❌ Cluster tidak ditemukan atau bukan milik Anda";
    }
}

// Tambah/Edit cluster
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add' || $action == 'edit') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama_cluster = trim($_POST['nama_cluster'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($nama_cluster)) {
            $error = "❌ Nama cluster wajib diisi!";
        } else {
            try {
                if ($action == 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO clusters (developer_id, nama_cluster, deskripsi, is_active, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$developer_id, $nama_cluster, $deskripsi, $is_active]);
                    $success = "✅ Cluster berhasil ditambahkan!";
                    logSystem("Cluster added", ['name' => $nama_cluster], 'INFO', 'cluster.log');
                } else {
                    // Cek kepemilikan
                    $check = $conn->prepare("SELECT id FROM clusters WHERE id = ? AND developer_id = ?");
                    $check->execute([$id, $developer_id]);
                    
                    if ($check->fetch()) {
                        $stmt = $conn->prepare("
                            UPDATE clusters SET 
                                nama_cluster = ?,
                                deskripsi = ?,
                                is_active = ?,
                                updated_at = NOW()
                            WHERE id = ? AND developer_id = ?
                        ");
                        $stmt->execute([$nama_cluster, $deskripsi, $is_active, $id, $developer_id]);
                        $success = "✅ Cluster berhasil diupdate!";
                        logSystem("Cluster updated", ['id' => $id], 'INFO', 'cluster.log');
                    } else {
                        $error = "❌ Cluster tidak ditemukan atau bukan milik Anda";
                    }
                }
            } catch (Exception $e) {
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    }
}

// Ambil data cluster milik developer ini
$clusters = [];
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM blocks WHERE cluster_id = c.id) as total_blocks,
           (SELECT COUNT(*) FROM units WHERE cluster_id = c.id) as total_units
    FROM clusters c
    WHERE c.developer_id = ?
    ORDER BY c.id DESC
");
$stmt->execute([$developer_id]);
$clusters = $stmt->fetchAll();

// Hitung statistik
$total_clusters = count($clusters);
$total_blocks = 0;
$total_units = 0;
$total_available = 0;

foreach ($clusters as $c) {
    $total_blocks += $c['total_blocks'];
    $total_units += $c['total_units'];
    
    // Hitung available units
    $stmt2 = $conn->prepare("SELECT COUNT(*) FROM units WHERE cluster_id = ? AND status = 'AVAILABLE'");
    $stmt2->execute([$c['id']]);
    $total_available += $stmt2->fetchColumn();
}

$page_title = 'Kelola Cluster';
$page_subtitle = 'Atur Cluster Perumahan Anda';
$page_icon = 'fas fa-layer-group';

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

/* ===== ACTION BUTTON - FULL WIDTH MOBILE ===== */
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
}

.btn-add i {
    font-size: 16px;
    width: auto;
    height: auto;
}

.btn-add:active {
    transform: scale(0.98);
}

/* ===== CLUSTER CARDS - HORIZONTAL DI MOBILE ===== */
.clusters-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    padding: 8px 0 20px 0;
    margin-bottom: 16px;
    -webkit-overflow-scrolling: touch;
}

.clusters-horizontal::-webkit-scrollbar {
    height: 4px;
}

.clusters-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.clusters-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.cluster-card {
    flex: 0 0 280px;
    background: white;
    border-radius: 24px;
    padding: 18px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    border-left: 6px solid var(--secondary);
    transition: transform 0.2s;
}

.cluster-card:active {
    transform: scale(0.98);
}

.cluster-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.cluster-name {
    font-weight: 800;
    color: var(--primary);
    font-size: 18px;
    word-break: break-word;
    max-width: 180px;
}

.cluster-status {
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    background: var(--primary-soft);
    color: var(--primary);
    white-space: nowrap;
}

.cluster-status.active {
    background: #2A9D8F;
    color: white;
}

.cluster-status.inactive {
    background: #D64F3C;
    color: white;
}

.cluster-desc {
    font-size: 13px;
    color: var(--text-light);
    margin-bottom: 16px;
    line-height: 1.5;
    word-break: break-word;
    max-height: 60px;
    overflow-y: auto;
}

/* ===== STATS MINI - HORIZONTAL ===== */
.cluster-stats {
    display: flex;
    gap: 8px;
    margin: 16px 0;
    overflow-x: auto;
    padding: 4px 0;
}

.cluster-stats::-webkit-scrollbar {
    display: none;
}

.stat-mini {
    flex: 0 0 70px;
    background: var(--primary-soft);
    border-radius: 12px;
    padding: 8px 4px;
    text-align: center;
}

.stat-mini-value {
    font-size: 16px;
    font-weight: 800;
    color: var(--primary);
}

.stat-mini-label {
    font-size: 9px;
    color: var(--text-muted);
    text-transform: uppercase;
}

/* ===== ACTION BUTTONS - HORIZONTAL ===== */
.cluster-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
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

.btn-icon.blocks {
    background: #e8f0fe;
    color: #1976d2;
    border-color: #1976d2;
}

.btn-icon.blocks:active {
    background: #1976d2;
    color: white;
}

/* ===== EMPTY STATE ===== */
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

/* ===== FORM ELEMENTS (HANYA UNTUK DI DALAM CARD, BUKAN MODAL) ===== */
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

/* ===== CHECKBOX GROUP ===== */
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--primary-soft);
    border-radius: 14px;
}

.checkbox-group input[type="checkbox"] {
    width: 22px;
    height: 22px;
    accent-color: var(--secondary);
}

.checkbox-group label {
    margin: 0;
    font-weight: 600;
    color: var(--primary);
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
    
    .btn-add {
        width: auto;
        padding: 14px 28px;
    }
    
    .clusters-horizontal {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        overflow-x: visible;
        gap: 20px;
    }
    
    .cluster-card {
        flex: none;
        width: auto;
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
    
    <!-- STATS HORIZONTAL SCROLL -->
    <div class="stats-horizontal">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
            <div class="stat-label">Total Cluster</div>
            <div class="stat-value"><?= $total_clusters ?></div>
        </div>
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
        <button class="btn-add" onclick="openAddClusterModal()">
            <i class="fas fa-plus-circle"></i> Tambah Cluster Baru
        </button>
    </div>
    
    <!-- CLUSTER CARDS - HORIZONTAL SCROLL DI MOBILE -->
    <?php if (empty($clusters)): ?>
    <div class="empty-state">
        <i class="fas fa-layer-group"></i>
        <h4>Belum Ada Cluster</h4>
        <p>Klik tombol "Tambah Cluster Baru" untuk memulai</p>
    </div>
    <?php else: ?>
    <div class="clusters-horizontal">
        <?php foreach ($clusters as $c): 
            $status_class = $c['is_active'] ? 'active' : 'inactive';
            $status_text = $c['is_active'] ? 'Aktif' : 'Nonaktif';
        ?>
        <div class="cluster-card">
            <div class="cluster-header">
                <div class="cluster-name"><?= htmlspecialchars($c['nama_cluster']) ?></div>
                <span class="cluster-status <?= $status_class ?>"><?= $status_text ?></span>
            </div>
            
            <?php if (!empty($c['deskripsi'])): ?>
            <div class="cluster-desc"><?= htmlspecialchars($c['deskripsi']) ?></div>
            <?php endif; ?>
            
            <!-- STATS MINI HORIZONTAL -->
            <div class="cluster-stats">
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $c['total_blocks'] ?></div>
                    <div class="stat-mini-label">Block</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $c['total_units'] ?></div>
                    <div class="stat-mini-label">Unit</div>
                </div>
                <?php
                $stmt = $conn->prepare("SELECT COUNT(*) FROM units WHERE cluster_id = ? AND status = 'AVAILABLE'");
                $stmt->execute([$c['id']]);
                $available = $stmt->fetchColumn();
                ?>
                <div class="stat-mini">
                    <div class="stat-mini-value" style="color: #2A9D8F;"><?= $available ?></div>
                    <div class="stat-mini-label">Ready</div>
                </div>
            </div>
            
            <!-- ACTION BUTTONS HORIZONTAL - PAKAI PATH ABSOLUT -->
            <div class="cluster-actions">
                <button class="btn-icon edit" onclick="editCluster(<?= htmlspecialchars(json_encode($c)) ?>)" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <a href="/admin/developer_blocks.php?cluster_id=<?= $c['id'] ?>" class="btn-icon blocks" title="Kelola Block">
                    <i class="fas fa-cubes"></i>
                </a>
                <button class="btn-icon delete" onclick="confirmDelete(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['nama_cluster'])) ?>')" title="Hapus">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div style="font-size: 10px; color: var(--text-muted); margin-top: 12px; text-align: right;">
                ID: #<?= $c['id'] ?> • Dibuat: <?= date('d/m/Y', strtotime($c['created_at'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Kelola Cluster v1.2 (Fixed Modal & Block Link)</p>
    </div>
    
</div>

<!-- MODAL ADD/EDIT CLUSTER - DENGAN ID UNIK -->
<div class="modal" id="addClusterModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-layer-group"></i> Tambah Cluster</h2>
            <button class="modal-close" onclick="closeClusterModal()">&times;</button>
        </div>
        <form method="POST" id="clusterForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="clusterId" value="0">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nama Cluster <span class="required">*</span></label>
                    <input type="text" name="nama_cluster" id="nama_cluster" class="form-control" placeholder="Contoh: Ruman, Scandinavia" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Deskripsi (opsional)</label>
                    <textarea name="deskripsi" id="deskripsi" class="form-control" rows="3" placeholder="Deskripsi cluster..."></textarea>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="is_active" checked value="1">
                    <label for="is_active">
                        <i class="fas fa-check-circle" style="color: #2A9D8F;"></i> Aktif (tampil di marketing)
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeClusterModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan Cluster</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-trash-alt"></i> Hapus Cluster</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div style="font-size: 48px; color: var(--danger); margin-bottom: 16px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p style="font-size: 16px; margin-bottom: 16px;">Yakin ingin menghapus cluster:</p>
            <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; font-weight: 700; font-size: 16px; color: var(--primary); margin-bottom: 16px;" id="deleteClusterName"></div>
            <p style="color: var(--text-muted); font-size: 13px;">
                <i class="fas fa-info-circle"></i> Semua block dan unit dalam cluster ini akan ikut terhapus.
            </p>
            <input type="hidden" id="deleteClusterId">
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeDeleteModal()">Batal</button>
            <button class="btn-primary" style="background: var(--danger);" onclick="processDelete()">Ya, Hapus</button>
        </div>
    </div>
</div>

<script>
// ===== FUNGSI MODAL YANG SUDAH DIPERBAIKI =====
function openAddClusterModal() {
    console.log('openAddClusterModal dipanggil');
    
    // Reset form untuk add
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-layer-group"></i> Tambah Cluster';
    document.getElementById('formAction').value = 'add';
    document.getElementById('clusterId').value = '0';
    document.getElementById('nama_cluster').value = '';
    document.getElementById('deskripsi').value = '';
    document.getElementById('is_active').checked = true;
    
    // Tampilkan modal
    document.getElementById('addClusterModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function editCluster(cluster) {
    console.log('editCluster dipanggil:', cluster);
    
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Cluster';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('clusterId').value = cluster.id;
    document.getElementById('nama_cluster').value = cluster.nama_cluster;
    document.getElementById('deskripsi').value = cluster.deskripsi || '';
    document.getElementById('is_active').checked = cluster.is_active == 1;
    
    document.getElementById('addClusterModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeClusterModal() {
    document.getElementById('addClusterModal').classList.remove('show');
    document.body.style.overflow = '';
}

function confirmDelete(id, name) {
    document.getElementById('deleteClusterId').value = id;
    document.getElementById('deleteClusterName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

function processDelete() {
    const id = document.getElementById('deleteClusterId').value;
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