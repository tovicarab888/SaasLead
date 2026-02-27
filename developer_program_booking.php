<?php
/**
 * DEVELOPER_PROGRAM_BOOKING.PHP - LEADENGINE
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

// Hapus program booking
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    $check = $conn->prepare("SELECT id FROM program_booking WHERE id = ? AND developer_id = ?");
    $check->execute([$delete_id, $developer_id]);
    
    if ($check->fetch()) {
        try {
            $used = $conn->prepare("SELECT COUNT(*) FROM units WHERE program_booking_id = ?");
            $used->execute([$delete_id]);
            $count = $used->fetchColumn();
            
            if ($count > 0) {
                $error = "❌ Program ini sudah digunakan di " . $count . " unit, tidak dapat dihapus!";
            } else {
                $stmt = $conn->prepare("DELETE FROM program_booking WHERE id = ?");
                $stmt->execute([$delete_id]);
                $success = "✅ Program booking berhasil dihapus!";
                logSystem("Program booking deleted", ['id' => $delete_id], 'INFO', 'program_booking.log');
            }
        } catch (Exception $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    } else {
        $error = "❌ Program tidak ditemukan atau bukan milik Anda";
    }
}

// Tambah/Edit program booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add' || $action == 'edit') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama_program = trim($_POST['nama_program'] ?? '');
        $booking_fee = !empty($_POST['booking_fee']) ? (float)$_POST['booking_fee'] : 0;
        $is_all_in = isset($_POST['is_all_in']) ? 1 : 0;
        $include_renovasi = trim($_POST['include_renovasi'] ?? '');
        $ketentuan_pembatalan = trim($_POST['ketentuan_pembatalan'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($nama_program)) {
            $error = "❌ Nama program wajib diisi!";
        } elseif ($booking_fee <= 0) {
            $error = "❌ Booking fee harus lebih dari 0!";
        } else {
            try {
                if ($action == 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO program_booking (
                            developer_id, nama_program, booking_fee, is_all_in, 
                            include_renovasi, ketentuan_pembatalan, is_active, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $developer_id, $nama_program, $booking_fee, $is_all_in,
                        $include_renovasi, $ketentuan_pembatalan, $is_active
                    ]);
                    $success = "✅ Program booking berhasil ditambahkan!";
                    logSystem("Program booking added", ['name' => $nama_program], 'INFO', 'program_booking.log');
                } else {
                    $check = $conn->prepare("SELECT id FROM program_booking WHERE id = ? AND developer_id = ?");
                    $check->execute([$id, $developer_id]);
                    
                    if ($check->fetch()) {
                        $stmt = $conn->prepare("
                            UPDATE program_booking SET 
                                nama_program = ?,
                                booking_fee = ?,
                                is_all_in = ?,
                                include_renovasi = ?,
                                ketentuan_pembatalan = ?,
                                is_active = ?,
                                updated_at = NOW()
                            WHERE id = ? AND developer_id = ?
                        ");
                        $stmt->execute([
                            $nama_program, $booking_fee, $is_all_in,
                            $include_renovasi, $ketentuan_pembatalan, $is_active,
                            $id, $developer_id
                        ]);
                        $success = "✅ Program booking berhasil diupdate!";
                        logSystem("Program booking updated", ['id' => $id], 'INFO', 'program_booking.log');
                    } else {
                        $error = "❌ Program tidak ditemukan atau bukan milik Anda";
                    }
                }
            } catch (Exception $e) {
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    }
}

// Ambil data program booking
$programs = [];
$stmt = $conn->prepare("
    SELECT * FROM program_booking 
    WHERE developer_id = ? 
    ORDER BY is_active DESC, id DESC
");
$stmt->execute([$developer_id]);
$programs = $stmt->fetchAll();

$page_title = 'Program Booking';
$page_subtitle = 'Kelola Program Booking & Ketentuan';
$page_icon = 'fas fa-tags';

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

/* ===== PROGRAM CARDS - HORIZONTAL SCROLL ===== */
.programs-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    padding: 8px 0 20px 0;
    margin-bottom: 16px;
}

.programs-horizontal::-webkit-scrollbar {
    height: 4px;
}

.programs-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.programs-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.program-card {
    flex: 0 0 280px;
    background: white;
    border-radius: 24px;
    padding: 18px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    border-left: 6px solid;
    transition: transform 0.2s;
}

.program-card.active {
    border-left-color: var(--success);
}

.program-card.inactive {
    border-left-color: var(--danger);
    opacity: 0.8;
}

.program-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.program-name {
    font-weight: 800;
    color: var(--primary);
    font-size: 18px;
    word-break: break-word;
    max-width: 180px;
}

.program-status {
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.program-status.active {
    background: var(--success);
    color: white;
}

.program-status.inactive {
    background: var(--danger);
    color: white;
}

.program-fee {
    font-size: 20px;
    font-weight: 800;
    color: var(--secondary);
    margin: 10px 0;
    text-align: center;
    background: var(--primary-soft);
    padding: 10px;
    border-radius: 12px;
}

.program-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 8px;
}

.program-badge.allin {
    background: #2A9D8F;
    color: white;
}

.program-detail {
    margin: 12px 0;
    padding: 10px;
    background: var(--bg);
    border-radius: 12px;
    font-size: 12px;
    color: var(--text-light);
    max-height: 100px;
    overflow-y: auto;
}

.program-actions {
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
    min-height: 100px;
    resize: vertical;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--primary-soft);
    border-radius: 14px;
    margin-bottom: 20px;
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
    
    .programs-horizontal {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        overflow-x: visible;
        gap: 20px;
    }
    
    .program-card {
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
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
            <div class="stat-label">Total Program</div>
            <div class="stat-value"><?= count($programs) ?></div>
        </div>
        <div class="stat-card" style="border-left-color: var(--success);">
            <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--success);"></i></div>
            <div class="stat-label">Aktif</div>
            <div class="stat-value"><?= count(array_filter($programs, fn($p) => $p['is_active'])) ?></div>
        </div>
        <div class="stat-card" style="border-left-color: var(--danger);">
            <div class="stat-icon"><i class="fas fa-times-circle" style="color: var(--danger);"></i></div>
            <div class="stat-label">Nonaktif</div>
            <div class="stat-value"><?= count(array_filter($programs, fn($p) => !$p['is_active'])) ?></div>
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
        <button class="btn-add" onclick="openAddProgramModal()">
            <i class="fas fa-plus-circle"></i> Tambah Program Booking
        </button>
    </div>
    
    <!-- PROGRAM CARDS -->
    <?php if (empty($programs)): ?>
    <div class="empty-state">
        <i class="fas fa-tags"></i>
        <h4>Belum Ada Program Booking</h4>
        <p>Klik tombol "Tambah Program Booking" untuk membuat program pertama</p>
    </div>
    <?php else: ?>
    <div class="programs-horizontal">
        <?php foreach ($programs as $p): 
            $status_class = $p['is_active'] ? 'active' : 'inactive';
            $status_text = $p['is_active'] ? 'Aktif' : 'Nonaktif';
        ?>
        <div class="program-card <?= $status_class ?>">
            <div class="program-header">
                <div class="program-name"><?= htmlspecialchars($p['nama_program']) ?></div>
                <span class="program-status <?= $status_class ?>"><?= $status_text ?></span>
            </div>
            
            <div class="program-fee">
                Rp <?= number_format($p['booking_fee'], 0, ',', '.') ?>
            </div>
            
            <?php if ($p['is_all_in']): ?>
            <div class="program-badge allin">ALL-IN PACKAGE</div>
            <?php endif; ?>
            
            <?php if (!empty($p['include_renovasi'])): ?>
            <div class="program-detail">
                <strong>Include Renovasi:</strong><br>
                <?= nl2br(htmlspecialchars($p['include_renovasi'])) ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($p['ketentuan_pembatalan'])): ?>
            <div class="program-detail">
                <strong>Ketentuan Pembatalan:</strong><br>
                <?= nl2br(htmlspecialchars($p['ketentuan_pembatalan'])) ?>
            </div>
            <?php endif; ?>
            
            <div class="program-actions">
                <button class="btn-icon edit" onclick="editProgram(<?= htmlspecialchars(json_encode($p)) ?>)" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon delete" onclick="confirmDelete(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nama_program'])) ?>')" title="Hapus">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div style="font-size: 10px; color: var(--text-muted); margin-top: 12px; text-align: right;">
                ID: #<?= $p['id'] ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Program Booking v3.1 (FIXED: Format Rupiah)</p>
    </div>
    
</div>

<!-- MODAL ADD/EDIT PROGRAM -->
<div class="modal" id="programModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-tags"></i> Tambah Program</h2>
            <button class="modal-close" onclick="closeProgramModal()">&times;</button>
        </div>
        <form method="POST" id="programForm" onsubmit="return prepareBookingFee()">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="programId" value="0">
            <input type="hidden" name="booking_fee" id="booking_fee_hidden" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nama Program <span class="required">*</span></label>
                    <input type="text" name="nama_program" id="nama_program" class="form-control" placeholder="Contoh: Booking Subsidi, Booking All-In" required maxlength="100">
                </div>
                
                <!-- Booking Fee dengan Auto-format Rupiah -->
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Booking Fee (Rp) <span class="required">*</span></label>
                    <input type="text" name="booking_fee_display" id="booking_fee_display" class="form-control rupiah-input" 
                           placeholder="Rp 500.000" required inputmode="numeric" 
                           onkeyup="formatRupiahInput(this)" onblur="formatRupiahBlur(this)">
                    <small style="color: var(--text-muted); display: block; margin-top: 6px;">
                        <i class="fas fa-info-circle"></i> Contoh: 500000 akan otomatis jadi Rp 500.000
                    </small>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_all_in" id="is_all_in" value="1">
                    <label for="is_all_in">
                        <i class="fas fa-check-circle" style="color: #2A9D8F;"></i> All-In Package (termasuk renovasi)
                    </label>
                </div>
                
                <div class="form-group" id="renovasi_group" style="display: none;">
                    <label><i class="fas fa-tools"></i> Include Renovasi</label>
                    <textarea name="include_renovasi" id="include_renovasi" class="form-control" rows="3" placeholder="Contoh: Keramik dapur, area dapur, dinding..."></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-file-contract"></i> Ketentuan Pembatalan</label>
                    <textarea name="ketentuan_pembatalan" id="ketentuan_pembatalan" class="form-control" rows="3" placeholder="Contoh: Hangus 100% jika batal sepihak, kembali 50% jika ditolak bank..."></textarea>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="is_active" checked value="1">
                    <label for="is_active">
                        <i class="fas fa-check-circle" style="color: #2A9D8F;"></i> Aktif (dapat dipilih marketing)
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeProgramModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan Program</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-trash-alt"></i> Hapus Program</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div style="font-size: 48px; color: var(--danger); margin-bottom: 16px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p style="font-size: 16px; margin-bottom: 16px;">Yakin ingin menghapus program:</p>
            <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; font-weight: 700; font-size: 16px; color: var(--primary); margin-bottom: 16px;" id="deleteProgramName"></div>
            <p style="color: var(--text-muted); font-size: 13px;">
                <i class="fas fa-info-circle"></i> Program yang sudah digunakan di unit tidak dapat dihapus.
            </p>
            <input type="hidden" id="deleteProgramId">
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

function prepareBookingFee() {
    const display = document.getElementById('booking_fee_display');
    if (display) {
        const value = parseRupiah(display.value);
        document.getElementById('booking_fee_hidden').value = value;
        
        if (value <= 0) {
            alert('Booking fee harus lebih dari 0');
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

// ===== MODAL FUNCTIONS =====
function openAddProgramModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-tags"></i> Tambah Program';
    document.getElementById('formAction').value = 'add';
    document.getElementById('programId').value = '0';
    document.getElementById('nama_program').value = '';
    document.getElementById('booking_fee_display').value = '';
    document.getElementById('booking_fee_hidden').value = '';
    document.getElementById('is_all_in').checked = false;
    document.getElementById('include_renovasi').value = '';
    document.getElementById('ketentuan_pembatalan').value = '';
    document.getElementById('is_active').checked = true;
    document.getElementById('renovasi_group').style.display = 'none';
    
    document.getElementById('programModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function editProgram(program) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Program';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('programId').value = program.id;
    document.getElementById('nama_program').value = program.nama_program;
    
    // 🔥 FIX: Format booking_fee dengan benar
    let bookingFee = parseFloat(program.booking_fee);
    document.getElementById('booking_fee_display').value = formatRupiah(bookingFee, '').replace('Rp ', '');
    document.getElementById('booking_fee_hidden').value = bookingFee;
    
    document.getElementById('is_all_in').checked = program.is_all_in == 1;
    document.getElementById('include_renovasi').value = program.include_renovasi || '';
    document.getElementById('ketentuan_pembatalan').value = program.ketentuan_pembatalan || '';
    document.getElementById('is_active').checked = program.is_active == 1;
    
    if (program.is_all_in == 1) {
        document.getElementById('renovasi_group').style.display = 'block';
    } else {
        document.getElementById('renovasi_group').style.display = 'none';
    }
    
    document.getElementById('programModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeProgramModal() {
    document.getElementById('programModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('is_all_in').addEventListener('change', function() {
    const renovasiGroup = document.getElementById('renovasi_group');
    if (this.checked) {
        renovasiGroup.style.display = 'block';
    } else {
        renovasiGroup.style.display = 'none';
    }
});

function confirmDelete(id, name) {
    document.getElementById('deleteProgramId').value = id;
    document.getElementById('deleteProgramName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

function processDelete() {
    const id = document.getElementById('deleteProgramId').value;
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