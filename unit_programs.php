<?php
/**
 * UNIT_PROGRAMS.PHP - Kelola Multiple Program per Unit
 * Version: 3.0.0 - UI SUPER KEREN (Mempertahankan UI Referensi)
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'api/config.php';

if (!isAdmin() && !isDeveloper()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) die("Database connection failed");

$is_developer = isDeveloper();
$developer_id = $is_developer ? $_SESSION['user_id'] : (isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0);

// ========== PROSES FORM ==========
$success = '';
$error = '';

// UPDATE MULTIPLE PROGRAM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_programs') {
    $unit_id = (int)($_POST['unit_id'] ?? 0);
    $program_ids = $_POST['program_ids'] ?? [];
    
    if ($unit_id <= 0) {
        $error = "❌ Unit tidak valid!";
    } else {
        try {
            $conn->beginTransaction();
            
            // Hapus semua program lama
            $delete = $conn->prepare("DELETE FROM unit_program_booking WHERE unit_id = ?");
            $delete->execute([$unit_id]);
            
            // Insert program baru
            if (!empty($program_ids)) {
                $insert = $conn->prepare("
                    INSERT INTO unit_program_booking (unit_id, program_booking_id, is_active, created_at)
                    VALUES (?, ?, 1, NOW())
                ");
                foreach ($program_ids as $pid) {
                    $insert->execute([$unit_id, (int)$pid]);
                }
            }
            
            // Update kolom multiple_program_booking di tabel units
            $multiple = implode(',', $program_ids);
            $update = $conn->prepare("UPDATE units SET multiple_program_booking = ? WHERE id = ?");
            $update->execute([$multiple, $unit_id]);
            
            $conn->commit();
            $success = "✅ Program booking untuk unit berhasil diupdate!";
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "❌ Gagal: " . $e->getMessage();
        }
    }
}

// ========== AMBIL DATA ==========
// Ambil developer untuk filter
if ($is_developer) {
    $developers = [[
        'id' => $developer_id,
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'Developer'
    ]];
} else {
    $developers = $conn->query("
        SELECT id, nama_lengkap FROM users 
        WHERE role = 'developer' AND is_active = 1 
        ORDER BY nama_lengkap
    ")->fetchAll();
}

// Ambil cluster berdasarkan developer
$clusters = [];
if ($developer_id > 0) {
    $clusters = $conn->prepare("
        SELECT * FROM clusters WHERE developer_id = ? AND is_active = 1 ORDER BY nama_cluster
    ");
    $clusters->execute([$developer_id]);
    $clusters = $clusters->fetchAll();
}

// Ambil program booking untuk developer ini
$programs = [];
if ($developer_id > 0) {
    $programs = $conn->prepare("
        SELECT * FROM program_booking 
        WHERE developer_id = ? AND is_active = 1 
        ORDER BY nama_program
    ");
    $programs->execute([$developer_id]);
    $programs = $programs->fetchAll();
}

// Ambil semua unit untuk developer ini (dengan program yang sudah dipilih)
$units = [];
if ($developer_id > 0) {
    $units = $conn->prepare("
        SELECT u.*, c.nama_cluster, b.nama_block,
               (SELECT GROUP_CONCAT(program_booking_id) 
                FROM unit_program_booking 
                WHERE unit_id = u.id) as selected_programs
        FROM units u
        JOIN clusters c ON u.cluster_id = c.id
        JOIN blocks b ON u.block_id = b.id
        WHERE c.developer_id = ?
        ORDER BY c.nama_cluster, b.nama_block, u.nomor_unit
    ");
    $units->execute([$developer_id]);
    $units = $units->fetchAll();
}

// ========== SET VARIABLES ==========
$page_title = 'Unit Programs';
$page_subtitle = 'Kelola Multiple Program per Unit';
$page_icon = 'fas fa-check-double';

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
    border: 1px solid var(--border);
}

.filter-form {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-select {
    flex: 1;
    min-width: 200px;
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    background: white;
}

.filter-select:focus {
    border-color: var(--secondary);
    outline: none;
}

/* ===== PROGRAM CHIP ===== */
.program-chip {
    display: inline-block;
    background: var(--primary-soft);
    border: 1px solid var(--border);
    border-radius: 30px;
    padding: 8px 16px;
    margin: 0 6px 6px 0;
    font-size: 12px;
    font-weight: 600;
    color: var(--primary);
    cursor: pointer;
    transition: all 0.2s;
}

.program-chip.selected {
    background: var(--secondary);
    color: white;
    border-color: var(--secondary);
}

.program-chip.all-in {
    border-left: 4px solid var(--warning);
}

.program-chip i {
    margin-right: 4px;
    font-size: 11px;
}

.program-chip .allin-badge {
    background: var(--warning);
    color: #1A2A24;
    padding: 2px 6px;
    border-radius: 20px;
    font-size: 9px;
    margin-left: 6px;
    font-weight: 700;
}

/* ===== TABLE CARD ===== */
.table-card {
    background: white;
    border-radius: 24px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
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

/* ===== UNIT ROW ===== */
.unit-row {
    background: white;
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 12px;
    border: 1px solid var(--border);
    transition: all 0.2s;
}

.unit-row:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.unit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    cursor: pointer;
}

.unit-title {
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
}

.unit-subtitle {
    font-size: 12px;
    color: var(--text-muted);
}

.program-container {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px dashed var(--border);
}

.selected-programs {
    background: var(--primary-soft);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 16px;
}

.selected-programs-title {
    font-size: 12px;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 8px;
}

.available-programs {
    margin-bottom: 16px;
}

.available-programs-title {
    font-size: 12px;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 8px;
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

.empty-state a {
    color: var(--secondary);
    font-weight: 600;
    text-decoration: none;
}

.empty-state a:hover {
    text-decoration: underline;
}

/* ===== INFO CARD ===== */
.info-card {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 20px;
    padding: 20px;
    margin: 24px 0;
    display: flex;
    align-items: center;
    gap: 16px;
    color: white;
    box-shadow: 0 8px 20px rgba(27,74,60,0.2);
}

.info-icon {
    width: 48px;
    height: 48px;
    background: rgba(255,255,255,0.15);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: var(--warning);
    flex-shrink: 0;
}

.info-text {
    flex: 1;
}

.info-text strong {
    font-size: 16px;
    font-weight: 700;
    color: var(--warning);
    display: block;
    margin-bottom: 4px;
}

.info-text p {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    opacity: 0.9;
}

/* ===== BUTTONS ===== */
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 8px 20px rgba(27,74,60,0.2);
    transition: all 0.2s;
}

.btn-primary i {
    font-size: 14px;
}

.btn-primary:active {
    transform: scale(0.98);
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

/* ===== DESKTOP UPGRADE ===== */
@media (min-width: 1024px) {
    .main-content {
        margin-left: 280px !important;
        padding: 24px !important;
        max-width: 1400px;
        margin-right: auto !important;
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
    
    .filter-bar {
        padding: 24px;
    }
    
    .filter-select {
        min-width: 250px;
        padding: 14px 18px;
    }
    
    .table-card {
        padding: 24px;
    }
    
    .unit-row {
        padding: 18px;
    }
    
    .program-chip {
        padding: 10px 18px;
        font-size: 13px;
    }
    
    .info-card {
        padding: 24px;
    }
    
    .info-icon {
        width: 56px;
        height: 56px;
        font-size: 28px;
    }
    
    .info-text strong {
        font-size: 18px;
    }
    
    .info-text p {
        font-size: 14px;
    }
    
    .btn-primary {
        padding: 14px 28px;
        font-size: 15px;
    }
}

/* ===== UTILITY ===== */
.text-center { text-align: center; }
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
    
    <!-- FILTER DEVELOPER (UNTUK ADMIN) -->
    <?php if (!$is_developer): ?>
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <select name="developer_id" class="filter-select" onchange="this.form.submit()">
                <option value="">Pilih Developer</option>
                <?php foreach ($developers as $dev): ?>
                <option value="<?= $dev['id'] ?>" <?= $developer_id == $dev['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dev['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>
    
    <?php if ($developer_id <= 0): ?>
    <div class="empty-state">
        <i class="fas fa-hand-pointer"></i>
        <p>Pilih developer terlebih dahulu</p>
    </div>
    <?php elseif (empty($programs)): ?>
    <div class="empty-state">
        <i class="fas fa-tag"></i>
        <p>Belum ada program booking untuk developer ini. <a href="developer_program_booking.php">Tambah program</a> terlebih dahulu.</p>
    </div>
    <?php elseif (empty($units)): ?>
    <div class="empty-state">
        <i class="fas fa-home"></i>
        <p>Belum ada unit untuk developer ini</p>
    </div>
    <?php else: ?>
    
    <!-- DAFTAR PROGRAM TERSEDIA -->
    <div class="table-card" style="margin-bottom: 30px;">
        <div class="table-header">
            <h3><i class="fas fa-tags" style="color: var(--secondary);"></i> Program Tersedia</h3>
        </div>
        <div style="padding: 10px 0;">
            <?php foreach ($programs as $prog): ?>
            <span class="program-chip" data-id="<?= $prog['id'] ?>" data-nama="<?= htmlspecialchars($prog['nama_program']) ?>" data-allin="<?= $prog['is_all_in'] ?>">
                <i class="fas fa-tag"></i> <?= htmlspecialchars($prog['nama_program']) ?> 
                (Rp <?= number_format($prog['booking_fee'], 0, ',', '.') ?>)
                <?php if ($prog['is_all_in']): ?> 
                <span class="allin-badge">ALL-IN</span>
                <?php endif; ?>
            </span>
            <?php endforeach; ?>
        </div>
        <small style="color: var(--text-muted); display: block; margin-top: 12px;">
            <i class="fas fa-info-circle"></i> Klik program untuk menambah/menghapus dari unit
        </small>
    </div>
    
    <!-- DAFTAR UNIT -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-home" style="color: var(--secondary);"></i> Unit</h3>
        </div>
        
        <div id="unitList">
            <?php foreach ($units as $unit): 
                $selected = explode(',', $unit['selected_programs'] ?? '');
            ?>
            <div class="unit-row" data-unit-id="<?= $unit['id'] ?>">
                <div class="unit-header" onclick="toggleUnit(this)">
                    <div>
                        <span class="unit-title"><?= htmlspecialchars($unit['nomor_unit']) ?></span>
                        <span class="unit-subtitle"> - <?= htmlspecialchars($unit['nama_cluster']) ?>/<?= htmlspecialchars($unit['nama_block']) ?> (<?= $unit['tipe_unit'] ?>)</span>
                    </div>
                    <i class="fas fa-chevron-down" style="color: var(--secondary); transition: transform 0.3s;"></i>
                </div>
                
                <div class="program-container" style="display: none;">
                    <!-- Program Terpilih -->
                    <div class="selected-programs">
                        <div class="selected-programs-title">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i> Program Terpilih:
                        </div>
                        <div id="selected-programs-<?= $unit['id'] ?>">
                            <?php 
                            $has_selected = false;
                            foreach ($programs as $prog): 
                                if (in_array($prog['id'], $selected)):
                                    $has_selected = true;
                            ?>
                            <span class="program-chip selected" data-prog-id="<?= $prog['id'] ?>" onclick="toggleProgram(this, <?= $unit['id'] ?>)">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($prog['nama_program']) ?>
                            </span>
                            <?php endif; endforeach; ?>
                            <?php if (!$has_selected): ?>
                            <span style="color: var(--text-muted); font-style: italic;">Belum ada program dipilih</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Pilihan Program -->
                    <div class="available-programs">
                        <div class="available-programs-title">
                            <i class="fas fa-list"></i> Pilih Program:
                        </div>
                        <div>
                            <?php foreach ($programs as $prog): ?>
                            <span class="program-chip <?= in_array($prog['id'], $selected) ? 'selected' : '' ?>" 
                                  data-prog-id="<?= $prog['id'] ?>" 
                                  onclick="toggleProgram(this, <?= $unit['id'] ?>)">
                                <?= htmlspecialchars($prog['nama_program']) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: right;">
                        <button class="btn-primary" onclick="saveUnitPrograms(<?= $unit['id'] ?>)">
                            <i class="fas fa-save"></i> Simpan untuk Unit Ini
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- FORM TERSEMBUNYI UNTUK SUBMIT -->
    <form id="programForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_programs">
        <input type="hidden" name="unit_id" id="form_unit_id" value="">
        <input type="hidden" name="program_ids[]" id="form_program_ids" value="">
    </form>
    
    <?php endif; ?>
    
    <!-- INFO CARD -->
    <div class="info-card">
        <div class="info-icon">
            <i class="fas fa-info-circle"></i>
        </div>
        <div class="info-text">
            <strong>Informasi Multiple Program</strong>
            <p>Setiap unit dapat memiliki beberapa program booking sekaligus. Customer dapat memilih salah satu program saat booking.</p>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Unit Programs v3.0</p>
    </div>
    
</div>

<script>
let currentUnitPrograms = {};

function toggleUnit(header) {
    const container = header.nextElementSibling;
    const icon = header.querySelector('i');
    
    if (container.style.display === 'none' || container.style.display === '') {
        container.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        container.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}

function toggleProgram(element, unitId) {
    element.classList.toggle('selected');
    
    // Update tampilan program terpilih
    const progId = element.dataset.progId;
    const progName = element.textContent.trim().replace(/<[^>]*>/g, ''); // Hapus HTML tags
    const selectedContainer = document.getElementById(`selected-programs-${unitId}`);
    
    // Hapus pesan "Belum ada program"
    const emptyMsg = selectedContainer.querySelector('span[style*="italic"]');
    if (emptyMsg) emptyMsg.remove();
    
    // Cek apakah sudah ada chip dengan progId ini di selected
    const existing = Array.from(selectedContainer.children).find(
        child => child.dataset.progId == progId
    );
    
    if (element.classList.contains('selected')) {
        // Tambah ke selected jika belum ada
        if (!existing) {
            const chip = document.createElement('span');
            chip.className = 'program-chip selected';
            chip.dataset.progId = progId;
            chip.onclick = function() { toggleProgram(this, unitId); };
            chip.innerHTML = '<i class="fas fa-check-circle"></i> ' + progName;
            selectedContainer.appendChild(chip);
        }
    } else {
        // Hapus dari selected
        if (existing) existing.remove();
        
        // Jika tidak ada program terpilih, tampilkan pesan
        if (selectedContainer.children.length === 0) {
            const msg = document.createElement('span');
            msg.style.color = 'var(--text-muted)';
            msg.style.fontStyle = 'italic';
            msg.textContent = 'Belum ada program dipilih';
            selectedContainer.appendChild(msg);
        }
    }
}

function saveUnitPrograms(unitId) {
    // Kumpulkan semua program yang dipilih untuk unit ini
    const selectedContainer = document.getElementById(`selected-programs-${unitId}`);
    const selectedChips = selectedContainer.querySelectorAll('.program-chip.selected');
    
    const programIds = [];
    selectedChips.forEach(chip => {
        if (chip.dataset.progId) {
            programIds.push(chip.dataset.progId);
        }
    });
    
    // Siapkan form
    const form = document.getElementById('programForm');
    document.getElementById('form_unit_id').value = unitId;
    
    // Hapus semua input program_ids yang lama
    document.querySelectorAll('#programForm input[name="program_ids[]"]').forEach(el => el.remove());
    
    // Tambah input baru
    programIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'program_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    // Submit form
    form.submit();
}

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