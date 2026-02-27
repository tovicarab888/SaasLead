<?php
/**
 * DEVELOPER_BIAYA_KATEGORI.PHP - LEADENGINE
 * Version: 3.1.0 - FIXED: Format Rupiah Konsisten saat Edit
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

// ========== PROSES CRUD ==========
$success = '';
$error = '';

// Hapus kategori biaya
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Cek kepemilikan
    $check = $conn->prepare("SELECT id FROM biaya_kategori WHERE id = ? AND developer_id = ?");
    $check->execute([$delete_id, $developer_id]);
    
    if ($check->fetch()) {
        try {
            // Cek apakah sudah digunakan di unit_biaya_tambahan
            $used = $conn->prepare("SELECT COUNT(*) FROM unit_biaya_tambahan WHERE biaya_kategori_id = ?");
            $used->execute([$delete_id]);
            $count = $used->fetchColumn();
            
            if ($count > 0) {
                $error = "❌ Kategori ini sudah digunakan di " . $count . " unit, tidak dapat dihapus!";
            } else {
                $stmt = $conn->prepare("DELETE FROM biaya_kategori WHERE id = ?");
                $stmt->execute([$delete_id]);
                $success = "✅ Kategori biaya berhasil dihapus!";
                logSystem("Biaya kategori deleted", ['id' => $delete_id], 'INFO', 'biaya_kategori.log');
            }
        } catch (Exception $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    } else {
        $error = "❌ Kategori tidak ditemukan atau bukan milik Anda";
    }
}

// Tambah/Edit kategori biaya
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add' || $action == 'edit') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        $satuan = $_POST['satuan'] ?? 'unit';
        $harga_default = !empty($_POST['harga_default']) ? (float)$_POST['harga_default'] : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($nama_kategori)) {
            $error = "❌ Nama kategori wajib diisi!";
        } else {
            try {
                if ($action == 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO biaya_kategori (
                            developer_id, nama_kategori, satuan, harga_default, is_active, created_at
                        ) VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $developer_id, $nama_kategori, $satuan, $harga_default, $is_active
                    ]);
                    $success = "✅ Kategori biaya berhasil ditambahkan!";
                    logSystem("Biaya kategori added", ['name' => $nama_kategori], 'INFO', 'biaya_kategori.log');
                } else {
                    // Cek kepemilikan
                    $check = $conn->prepare("SELECT id FROM biaya_kategori WHERE id = ? AND developer_id = ?");
                    $check->execute([$id, $developer_id]);
                    
                    if ($check->fetch()) {
                        $stmt = $conn->prepare("
                            UPDATE biaya_kategori SET 
                                nama_kategori = ?,
                                satuan = ?,
                                harga_default = ?,
                                is_active = ?,
                                updated_at = NOW()
                            WHERE id = ? AND developer_id = ?
                        ");
                        $stmt->execute([
                            $nama_kategori, $satuan, $harga_default, $is_active,
                            $id, $developer_id
                        ]);
                        $success = "✅ Kategori biaya berhasil diupdate!";
                        logSystem("Biaya kategori updated", ['id' => $id], 'INFO', 'biaya_kategori.log');
                    } else {
                        $error = "❌ Kategori tidak ditemukan atau bukan milik Anda";
                    }
                }
            } catch (Exception $e) {
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    }
}

// Ambil data kategori biaya
$kategoris = [];
$stmt = $conn->prepare("
    SELECT * FROM biaya_kategori 
    WHERE developer_id = ? 
    ORDER BY is_active DESC, id DESC
");
$stmt->execute([$developer_id]);
$kategoris = $stmt->fetchAll();

$page_title = 'Master Biaya Tambahan';
$page_subtitle = 'Kelola Kategori Biaya untuk Unit';
$page_icon = 'fas fa-coins';

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

/* ===== STATS HORIZONTAL ===== */
.stats-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    padding: 4px 0 16px 0;
    margin-bottom: 8px;
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

/* ===== KATEGORI CARDS - HORIZONTAL SCROLL ===== */
.kategori-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    padding: 8px 0 20px 0;
    margin-bottom: 16px;
}

.kategori-horizontal::-webkit-scrollbar {
    height: 4px;
}

.kategori-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.kategori-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.kategori-card {
    flex: 0 0 280px;
    background: white;
    border-radius: 24px;
    padding: 18px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    border-left: 6px solid;
    transition: transform 0.2s;
}

.kategori-card.active {
    border-left-color: var(--success);
}

.kategori-card.inactive {
    border-left-color: var(--danger);
    opacity: 0.8;
}

.kategori-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.kategori-name {
    font-weight: 800;
    color: var(--primary);
    font-size: 18px;
    word-break: break-word;
    max-width: 180px;
}

.kategori-status {
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.kategori-status.active {
    background: var(--success);
    color: white;
}

.kategori-status.inactive {
    background: var(--danger);
    color: white;
}

.kategori-satuan {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
    background: var(--primary-soft);
    color: var(--primary);
    margin: 8px 0;
}

.kategori-harga {
    font-size: 18px;
    font-weight: 800;
    color: var(--secondary);
    margin: 10px 0;
    text-align: center;
    background: var(--primary-soft);
    padding: 10px;
    border-radius: 12px;
}

/* ===== ACTION BUTTONS ===== */
.kategori-actions {
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
    color: var(--danger);
    border-color: var(--danger);
}

.btn-icon.delete:active {
    background: var(--danger);
    color: white;
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

/* ===== INPUT RUPIAH STYLES - KEYPAD ANGKA DI MOBILE ===== */
.rupiah-input {
    -webkit-appearance: none;
    appearance: none;
}

input[type="text"].rupiah-input,
input[type="text"][inputmode="numeric"],
input[type="text"][pattern="[0-9]*"] {
    -webkit-appearance: none;
    appearance: none;
}

/* ===== RADIO GROUP ===== */
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

/* ===== CHECKBOX GROUP ===== */
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--primary-soft);
    border-radius: 14px;
    margin-top: 20px;
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
    cursor: pointer;
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
    
    .btn-add {
        width: auto;
        padding: 14px 28px;
    }
    
    .kategori-horizontal {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        overflow-x: visible;
        gap: 20px;
    }
    
    .kategori-card {
        flex: none;
        width: auto;
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
    
    <!-- STATS HORIZONTAL -->
    <div class="stats-horizontal">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-label">Total Kategori</div>
            <div class="stat-value"><?= count($kategoris) ?></div>
        </div>
        <div class="stat-card" style="border-left-color: var(--success);">
            <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--success);"></i></div>
            <div class="stat-label">Aktif</div>
            <div class="stat-value"><?= count(array_filter($kategoris, fn($k) => $k['is_active'])) ?></div>
        </div>
        <div class="stat-card" style="border-left-color: var(--danger);">
            <div class="stat-icon"><i class="fas fa-times-circle" style="color: var(--danger);"></i></div>
            <div class="stat-label">Nonaktif</div>
            <div class="stat-value"><?= count(array_filter($kategoris, fn($k) => !$k['is_active'])) ?></div>
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
        <button class="btn-add" onclick="openAddKategoriModal()">
            <i class="fas fa-plus-circle"></i> Tambah Kategori Biaya
        </button>
    </div>
    
    <!-- KATEGORI CARDS -->
    <?php if (empty($kategoris)): ?>
    <div class="empty-state">
        <i class="fas fa-coins"></i>
        <h4>Belum Ada Kategori Biaya</h4>
        <p>Klik tombol "Tambah Kategori Biaya" untuk membuat kategori pertama</p>
        <p style="font-size: 12px; margin-top: 10px;">Contoh: Biaya Hook, Biaya Hadap Jalan, Biaya Kelebihan Tanah</p>
    </div>
    <?php else: ?>
    <div class="kategori-horizontal">
        <?php foreach ($kategoris as $k): 
            $status_class = $k['is_active'] ? 'active' : 'inactive';
            $status_text = $k['is_active'] ? 'Aktif' : 'Nonaktif';
            
            $satuan_text = '';
            if ($k['satuan'] == 'unit') $satuan_text = 'Per Unit';
            else if ($k['satuan'] == 'm²') $satuan_text = 'Per Meter Persegi';
            else if ($k['satuan'] == 'persen') $satuan_text = 'Persen (%)';
        ?>
        <div class="kategori-card <?= $status_class ?>">
            <div class="kategori-header">
                <div class="kategori-name"><?= htmlspecialchars($k['nama_kategori']) ?></div>
                <span class="kategori-status <?= $status_class ?>"><?= $status_text ?></span>
            </div>
            
            <div class="kategori-satuan">
                <i class="fas fa-ruler"></i> <?= $satuan_text ?>
            </div>
            
            <?php if ($k['harga_default']): ?>
            <div class="kategori-harga">
                Rp <?= number_format($k['harga_default'], 0, ',', '.') ?>
            </div>
            <?php else: ?>
            <div class="kategori-harga" style="background: var(--bg); color: var(--text-muted);">
                Harga Input Manual
            </div>
            <?php endif; ?>
            
            <div class="kategori-actions">
                <button class="btn-icon edit" onclick="editKategori(<?= htmlspecialchars(json_encode($k)) ?>)" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon delete" onclick="confirmDelete(<?= $k['id'] ?>, '<?= htmlspecialchars(addslashes($k['nama_kategori'])) ?>')" title="Hapus">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div style="font-size: 10px; color: var(--text-muted); margin-top: 12px; text-align: right;">
                ID: #<?= $k['id'] ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Master Biaya Tambahan v3.1 (FIXED: Format Rupiah)</p>
    </div>
    
</div>

<!-- MODAL ADD/EDIT KATEGORI -->
<div class="modal" id="kategoriModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-coins"></i> Tambah Kategori</h2>
            <button class="modal-close" onclick="closeKategoriModal()">&times;</button>
        </div>
        <form method="POST" id="kategoriForm" onsubmit="return prepareHargaDefault()">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="kategoriId" value="0">
            <input type="hidden" name="harga_default" id="harga_default_hidden" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nama Kategori <span class="required">*</span></label>
                    <input type="text" name="nama_kategori" id="nama_kategori" class="form-control" placeholder="Contoh: Biaya Hook, Biaya Hadap Jalan" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-ruler"></i> Satuan <span class="required">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" name="satuan" id="satuan_unit" value="unit" checked>
                            <label for="satuan_unit"><i class="fas fa-home"></i> Per Unit</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="satuan" id="satuan_m2" value="m²">
                            <label for="satuan_m2"><i class="fas fa-arrows-alt"></i> Per m²</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="satuan" id="satuan_persen" value="persen">
                            <label for="satuan_persen"><i class="fas fa-percent"></i> Persen</label>
                        </div>
                    </div>
                </div>
                
                <!-- Harga Default dengan Auto-format Rupiah -->
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Harga Default (opsional)</label>
                    <input type="text" name="harga_default_display" id="harga_default_display" class="form-control rupiah-input" 
                           placeholder="Rp 0" inputmode="numeric" 
                           onkeyup="formatRupiahInput(this)" onblur="formatRupiahBlur(this)">
                    <small style="color: var(--text-muted); display: block; margin-top: 6px;">
                        <i class="fas fa-info-circle"></i> Jika diisi, akan otomatis terisi saat pilih kategori
                    </small>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="is_active" checked value="1">
                    <label for="is_active">
                        <i class="fas fa-check-circle" style="color: #2A9D8F;"></i> Aktif (dapat digunakan di unit)
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeKategoriModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan Kategori</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-trash-alt"></i> Hapus Kategori</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div style="font-size: 48px; color: var(--danger); margin-bottom: 16px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p style="font-size: 16px; margin-bottom: 16px;">Yakin ingin menghapus kategori:</p>
            <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; font-weight: 700; font-size: 16px; color: var(--primary); margin-bottom: 16px;" id="deleteKategoriName"></div>
            <p style="color: var(--text-muted); font-size: 13px;">
                <i class="fas fa-info-circle"></i> Kategori yang sudah digunakan di unit tidak dapat dihapus.
            </p>
            <input type="hidden" id="deleteKategoriId">
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

function prepareHargaDefault() {
    const display = document.getElementById('harga_default_display');
    if (display) {
        const value = parseRupiah(display.value);
        document.getElementById('harga_default_hidden').value = value;
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

// ===== MODAL FUNCTIONS =====
function openAddKategoriModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-coins"></i> Tambah Kategori';
    document.getElementById('formAction').value = 'add';
    document.getElementById('kategoriId').value = '0';
    document.getElementById('nama_kategori').value = '';
    document.getElementById('satuan_unit').checked = true;
    document.getElementById('harga_default_display').value = '';
    document.getElementById('harga_default_hidden').value = '';
    document.getElementById('is_active').checked = true;
    
    document.getElementById('kategoriModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function editKategori(kategori) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Kategori';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('kategoriId').value = kategori.id;
    document.getElementById('nama_kategori').value = kategori.nama_kategori;
    
    if (kategori.satuan === 'unit') document.getElementById('satuan_unit').checked = true;
    else if (kategori.satuan === 'm²') document.getElementById('satuan_m2').checked = true;
    else if (kategori.satuan === 'persen') document.getElementById('satuan_persen').checked = true;
    
    if (kategori.harga_default) {
        let hargaDefault = parseFloat(kategori.harga_default);
        document.getElementById('harga_default_display').value = formatRupiah(hargaDefault, '').replace('Rp ', '');
        document.getElementById('harga_default_hidden').value = hargaDefault;
    } else {
        document.getElementById('harga_default_display').value = '';
        document.getElementById('harga_default_hidden').value = '';
    }
    
    document.getElementById('is_active').checked = kategori.is_active == 1;
    
    document.getElementById('kategoriModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeKategoriModal() {
    document.getElementById('kategoriModal').classList.remove('show');
    document.body.style.overflow = '';
}

function confirmDelete(id, name) {
    document.getElementById('deleteKategoriId').value = id;
    document.getElementById('deleteKategoriName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

function processDelete() {
    const id = document.getElementById('deleteKategoriId').value;
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