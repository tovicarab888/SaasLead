<?php
/**
 * DEVELOPER_BANKS.PHP - LEADENGINE
 * Version: 1.1.0 - FIXED: Struktur tabel banks dengan field lengkap
 * MOBILE FIRST UI - CRUD REKENING BANK
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

// Hapus rekening
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    $check = $conn->prepare("SELECT id FROM banks WHERE id = ? AND developer_id = ?");
    $check->execute([$delete_id, $developer_id]);
    
    if ($check->fetch()) {
        try {
            // Cek apakah rekening digunakan di marketing_team
            $used = $conn->prepare("SELECT COUNT(*) FROM marketing_team WHERE bank_id = ?");
            $used->execute([$delete_id]);
            $count = $used->fetchColumn();
            
            if ($count > 0) {
                $error = "❌ Rekening ini masih digunakan oleh " . $count . " marketing, tidak dapat dihapus!";
            } else {
                $stmt = $conn->prepare("DELETE FROM banks WHERE id = ?");
                $stmt->execute([$delete_id]);
                $success = "✅ Rekening berhasil dihapus!";
                logSystem("Bank deleted", ['id' => $delete_id], 'INFO', 'bank.log');
            }
        } catch (Exception $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    } else {
        $error = "❌ Rekening tidak ditemukan";
    }
}

// Tambah/Edit rekening
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add' || $action == 'edit') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama_bank = trim($_POST['nama_bank'] ?? '');
        $nomor_rekening = trim($_POST['nomor_rekening'] ?? '');
        $atas_nama = trim($_POST['atas_nama'] ?? '');
        $cabang = trim($_POST['cabang'] ?? '');
        $kode_swift = trim($_POST['kode_swift'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($nama_bank) || empty($nomor_rekening) || empty($atas_nama)) {
            $error = "❌ Nama bank, nomor rekening, dan atas nama wajib diisi!";
        } else {
            try {
                if ($action == 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO banks (
                            developer_id, nama_bank, nomor_rekening, atas_nama,
                            cabang, kode_swift, keterangan, is_active, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $developer_id, $nama_bank, $nomor_rekening, $atas_nama,
                        $cabang, $kode_swift, $keterangan, $is_active
                    ]);
                    $success = "✅ Rekening bank berhasil ditambahkan!";
                    logSystem("Bank added", ['bank' => $nama_bank], 'INFO', 'bank.log');
                } else {
                    $check = $conn->prepare("SELECT id FROM banks WHERE id = ? AND developer_id = ?");
                    $check->execute([$id, $developer_id]);
                    
                    if ($check->fetch()) {
                        $stmt = $conn->prepare("
                            UPDATE banks SET 
                                nama_bank = ?,
                                nomor_rekening = ?,
                                atas_nama = ?,
                                cabang = ?,
                                kode_swift = ?,
                                keterangan = ?,
                                is_active = ?,
                                updated_at = NOW()
                            WHERE id = ? AND developer_id = ?
                        ");
                        $stmt->execute([
                            $nama_bank, $nomor_rekening, $atas_nama,
                            $cabang, $kode_swift, $keterangan, $is_active,
                            $id, $developer_id
                        ]);
                        $success = "✅ Rekening bank berhasil diupdate!";
                        logSystem("Bank updated", ['id' => $id], 'INFO', 'bank.log');
                    } else {
                        $error = "❌ Rekening tidak ditemukan";
                    }
                }
            } catch (Exception $e) {
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    }
}

// Ambil data rekening
$banks = [];
$stmt = $conn->prepare("
    SELECT * FROM banks 
    WHERE developer_id = ? 
    ORDER BY is_active DESC, id DESC
");
$stmt->execute([$developer_id]);
$banks = $stmt->fetchAll();

$page_title = 'Rekening Bank Perusahaan';
$page_subtitle = 'Kelola Rekening untuk Transfer Komisi';
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

.stats-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    padding: 4px 0 16px 0;
    margin-bottom: 16px;
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

.banks-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    padding: 8px 0 20px 0;
    margin-bottom: 16px;
}

.banks-horizontal::-webkit-scrollbar {
    height: 4px;
}

.banks-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.banks-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.bank-card {
    flex: 0 0 300px;
    background: white;
    border-radius: 24px;
    padding: 18px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    border-left: 6px solid;
    transition: transform 0.2s;
}

.bank-card.active {
    border-left-color: var(--success);
}

.bank-card.inactive {
    border-left-color: var(--danger);
    opacity: 0.8;
}

.bank-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.bank-name {
    font-weight: 800;
    color: var(--primary);
    font-size: 18px;
    word-break: break-word;
    max-width: 180px;
}

.bank-status {
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.bank-status.active {
    background: var(--success);
    color: white;
}

.bank-status.inactive {
    background: var(--danger);
    color: white;
}

.bank-detail {
    margin: 16px 0;
    padding: 12px;
    background: var(--primary-soft);
    border-radius: 16px;
}

.bank-detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
}

.bank-detail-row:last-child {
    margin-bottom: 0;
}

.bank-detail-label {
    color: var(--text-muted);
    font-weight: 500;
}

.bank-detail-value {
    font-weight: 700;
    color: var(--primary);
    text-align: right;
    word-break: break-word;
}

.bank-keterangan {
    margin-top: 12px;
    padding: 10px;
    background: var(--bg);
    border-radius: 12px;
    font-size: 12px;
    color: var(--text-light);
    font-style: italic;
}

.bank-actions {
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
    
    .btn-add, .btn-back {
        width: auto;
        padding: 14px 28px;
    }
    
    .banks-horizontal {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        overflow-x: visible;
        gap: 20px;
    }
    
    .bank-card {
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
            <div class="stat-icon"><i class="fas fa-university"></i></div>
            <div class="stat-label">Total Rekening</div>
            <div class="stat-value"><?= count($banks) ?></div>
        </div>
        <div class="stat-card" style="border-left-color: var(--success);">
            <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--success);"></i></div>
            <div class="stat-label">Aktif</div>
            <div class="stat-value"><?= count(array_filter($banks, fn($b) => $b['is_active'])) ?></div>
        </div>
        <div class="stat-card" style="border-left-color: var(--danger);">
            <div class="stat-icon"><i class="fas fa-times-circle" style="color: var(--danger);"></i></div>
            <div class="stat-label">Nonaktif</div>
            <div class="stat-value"><?= count(array_filter($banks, fn($b) => !$b['is_active'])) ?></div>
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
        <button class="btn-add" onclick="openAddBankModal()">
            <i class="fas fa-plus-circle"></i> Tambah Rekening Bank
        </button>
        <a href="developer_dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>
    
    <!-- BANK CARDS -->
    <?php if (empty($banks)): ?>
    <div class="empty-state">
        <i class="fas fa-university"></i>
        <h4>Belum Ada Rekening Bank</h4>
        <p>Klik tombol "Tambah Rekening Bank" untuk menambahkan rekening pertama</p>
        <p style="font-size: 12px; margin-top: 10px;">Rekening ini akan digunakan untuk transfer komisi ke marketing</p>
    </div>
    <?php else: ?>
    <div class="banks-horizontal">
        <?php foreach ($banks as $b): 
            $status_class = $b['is_active'] ? 'active' : 'inactive';
            $status_text = $b['is_active'] ? 'Aktif' : 'Nonaktif';
        ?>
        <div class="bank-card <?= $status_class ?>">
            <div class="bank-header">
                <div class="bank-name"><?= htmlspecialchars($b['nama_bank']) ?></div>
                <span class="bank-status <?= $status_class ?>"><?= $status_text ?></span>
            </div>
            
            <div class="bank-detail">
                <div class="bank-detail-row">
                    <span class="bank-detail-label">Nomor Rekening</span>
                    <span class="bank-detail-value"><?= htmlspecialchars($b['nomor_rekening']) ?></span>
                </div>
                <div class="bank-detail-row">
                    <span class="bank-detail-label">Atas Nama</span>
                    <span class="bank-detail-value"><?= htmlspecialchars($b['atas_nama']) ?></span>
                </div>
                <?php if (!empty($b['cabang'])): ?>
                <div class="bank-detail-row">
                    <span class="bank-detail-label">Cabang</span>
                    <span class="bank-detail-value"><?= htmlspecialchars($b['cabang']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($b['kode_swift'])): ?>
                <div class="bank-detail-row">
                    <span class="bank-detail-label">Kode Swift</span>
                    <span class="bank-detail-value"><?= htmlspecialchars($b['kode_swift']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($b['keterangan'])): ?>
            <div class="bank-keterangan">
                <i class="fas fa-quote-left" style="color: var(--secondary);"></i> <?= htmlspecialchars($b['keterangan']) ?>
            </div>
            <?php endif; ?>
            
            <div class="bank-actions">
                <button class="btn-icon edit" onclick="editBank(<?= htmlspecialchars(json_encode($b)) ?>)" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon delete" onclick="confirmDelete(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['nama_bank'])) ?>')" title="Hapus">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div style="font-size: 10px; color: var(--text-muted); margin-top: 12px; text-align: right;">
                ID: #<?= $b['id'] ?> • Dibuat: <?= date('d/m/Y', strtotime($b['created_at'] ?? $b['created_at'] ?? 'now')) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Rekening Bank Perusahaan v1.1</p>
    </div>
    
</div>

<!-- MODAL ADD/EDIT BANK -->
<div class="modal" id="bankModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-university"></i> Tambah Rekening</h2>
            <button class="modal-close" onclick="closeBankModal()">&times;</button>
        </div>
        <form method="POST" id="bankForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="bankId" value="0">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-university"></i> Nama Bank <span class="required">*</span></label>
                    <input type="text" name="nama_bank" id="nama_bank" class="form-control" placeholder="Contoh: Bank Mandiri, BCA, BRI" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Nomor Rekening <span class="required">*</span></label>
                    <input type="text" name="nomor_rekening" id="nomor_rekening" class="form-control" placeholder="1234567890" required maxlength="50" inputmode="numeric">
                    <small style="color: var(--text-muted);">Masukkan tanpa spasi atau tanda hubung</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Atas Nama <span class="required">*</span></label>
                    <input type="text" name="atas_nama" id="atas_nama" class="form-control" placeholder="PT. Perusahaan Anda / Nama Pribadi" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-location-dot"></i> Cabang (opsional)</label>
                    <input type="text" name="cabang" id="cabang" class="form-control" placeholder="Contoh: KCU Kuningan" maxlength="100">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-code"></i> Kode Swift (opsional)</label>
                    <input type="text" name="kode_swift" id="kode_swift" class="form-control" placeholder="BMRIIDJA" maxlength="20">
                    <small style="color: var(--text-muted);">Kode SWIFT/BIC untuk transfer internasional</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> Keterangan (opsional)</label>
                    <textarea name="keterangan" id="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="is_active" checked value="1">
                    <label for="is_active">
                        <i class="fas fa-check-circle" style="color: #2A9D8F;"></i> Aktif (dapat digunakan untuk transfer)
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeBankModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan Rekening</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-trash-alt"></i> Hapus Rekening</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div style="font-size: 48px; color: var(--danger); margin-bottom: 16px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p style="font-size: 16px; margin-bottom: 16px;">Yakin ingin menghapus rekening:</p>
            <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; font-weight: 700; font-size: 16px; color: var(--primary); margin-bottom: 16px;" id="deleteBankName"></div>
            <p style="color: var(--text-muted); font-size: 13px;">
                <i class="fas fa-info-circle"></i> Rekening yang masih digunakan oleh marketing tidak dapat dihapus.
            </p>
            <input type="hidden" id="deleteBankId">
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeDeleteModal()">Batal</button>
            <button class="btn-primary" style="background: var(--danger);" onclick="processDelete()">Ya, Hapus</button>
        </div>
    </div>
</div>

<script>
function openAddBankModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-university"></i> Tambah Rekening';
    document.getElementById('formAction').value = 'add';
    document.getElementById('bankId').value = '0';
    document.getElementById('nama_bank').value = '';
    document.getElementById('nomor_rekening').value = '';
    document.getElementById('atas_nama').value = '';
    document.getElementById('cabang').value = '';
    document.getElementById('kode_swift').value = '';
    document.getElementById('keterangan').value = '';
    document.getElementById('is_active').checked = true;
    
    document.getElementById('bankModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function editBank(bank) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Rekening';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('bankId').value = bank.id;
    document.getElementById('nama_bank').value = bank.nama_bank;
    document.getElementById('nomor_rekening').value = bank.nomor_rekening;
    document.getElementById('atas_nama').value = bank.atas_nama;
    document.getElementById('cabang').value = bank.cabang || '';
    document.getElementById('kode_swift').value = bank.kode_swift || '';
    document.getElementById('keterangan').value = bank.keterangan || '';
    document.getElementById('is_active').checked = bank.is_active == 1;
    
    document.getElementById('bankModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeBankModal() {
    document.getElementById('bankModal').classList.remove('show');
    document.body.style.overflow = '';
}

function confirmDelete(id, name) {
    document.getElementById('deleteBankId').value = id;
    document.getElementById('deleteBankName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

function processDelete() {
    const id = document.getElementById('deleteBankId').value;
    if (id) {
        window.location.href = '?delete=' + id;
    }
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

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

if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php include 'includes/footer.php'; ?>