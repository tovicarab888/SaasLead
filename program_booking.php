<?php
/**
 * PROGRAM_BOOKING.PHP - Kelola Program Booking
 * Version: 2.0.0 - MOBILE FIRST
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'api/config.php';

if (!isAdmin()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) die("Database connection failed");

// ========== PAGINATION ==========
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// ========== PROSES FORM ==========
$success = '';
$error = '';

// TAMBAH PROGRAM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_program') {
    $developer_id = (int)($_POST['developer_id'] ?? 0);
    $nama_program = trim($_POST['nama_program'] ?? '');
    $booking_fee = (float)str_replace(['.', ','], ['', '.'], $_POST['booking_fee'] ?? 0);
    $is_all_in = isset($_POST['is_all_in']) ? 1 : 0;
    $include_renovasi = trim($_POST['include_renovasi'] ?? '');
    $ketentuan_pembatalan = trim($_POST['ketentuan_pembatalan'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($developer_id) || empty($nama_program) || $booking_fee <= 0) {
        $error = "Developer, nama program, dan booking fee harus diisi!";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO program_booking (
                    developer_id, nama_program, booking_fee, is_all_in,
                    include_renovasi, ketentuan_pembatalan, is_active,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $developer_id, $nama_program, $booking_fee, $is_all_in,
                $include_renovasi, $ketentuan_pembatalan, $is_active
            ]);
            $success = "Program booking berhasil ditambahkan!";
        } catch (Exception $e) {
            $error = "Gagal: " . $e->getMessage();
        }
    }
}

// EDIT PROGRAM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_program') {
    $id = (int)($_POST['id'] ?? 0);
    $developer_id = (int)($_POST['developer_id'] ?? 0);
    $nama_program = trim($_POST['nama_program'] ?? '');
    $booking_fee = (float)str_replace(['.', ','], ['', '.'], $_POST['booking_fee'] ?? 0);
    $is_all_in = isset($_POST['is_all_in']) ? 1 : 0;
    $include_renovasi = trim($_POST['include_renovasi'] ?? '');
    $ketentuan_pembatalan = trim($_POST['ketentuan_pembatalan'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($id <= 0 || empty($developer_id) || empty($nama_program) || $booking_fee <= 0) {
        $error = "Data tidak valid!";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE program_booking SET
                    developer_id = ?,
                    nama_program = ?,
                    booking_fee = ?,
                    is_all_in = ?,
                    include_renovasi = ?,
                    ketentuan_pembatalan = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $developer_id, $nama_program, $booking_fee, $is_all_in,
                $include_renovasi, $ketentuan_pembatalan, $is_active, $id
            ]);
            $success = "Program booking berhasil diupdate!";
        } catch (Exception $e) {
            $error = "Gagal: " . $e->getMessage();
        }
    }
}

// HAPUS PROGRAM
if (isset($_GET['delete']) && (int)$_GET['delete'] > 0) {
    $id = (int)$_GET['delete'];
    
    $check = $conn->prepare("SELECT COUNT(*) FROM units WHERE program_booking_id = ?");
    $check->execute([$id]);
    $used = $check->fetchColumn();
    
    if ($used > 0) {
        $error = "Program tidak bisa dihapus karena sudah digunakan oleh $used unit!";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM program_booking WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Program booking berhasil dihapus!";
        } catch (Exception $e) {
            $error = "Gagal: " . $e->getMessage();
        }
    }
}

// TOGGLE STATUS
if (isset($_GET['toggle']) && (int)$_GET['toggle'] > 0) {
    $id = (int)$_GET['toggle'];
    $conn->prepare("UPDATE program_booking SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    header('Location: program_booking.php');
    exit();
}

// ========== AMBIL DATA ==========
$developers = $conn->query("
    SELECT id, nama_lengkap, nama_perusahaan 
    FROM users 
    WHERE role = 'developer' AND is_active = 1 
    ORDER BY nama_lengkap
")->fetchAll();

$total_programs = $conn->query("SELECT COUNT(*) FROM program_booking")->fetchColumn();
$total_pages = ceil($total_programs / $limit);

$programs = $conn->prepare("
    SELECT pb.*, u.nama_lengkap as developer_name, u.nama_perusahaan,
           (SELECT COUNT(*) FROM units WHERE program_booking_id = pb.id) as total_units
    FROM program_booking pb
    JOIN users u ON pb.developer_id = u.id
    ORDER BY pb.developer_id, pb.is_active DESC, pb.created_at DESC
    LIMIT ? OFFSET ?
");
$programs->execute([$limit, $offset]);
$programs = $programs->fetchAll();

$grouped_programs = [];
foreach ($programs as $prog) {
    $dev_id = $prog['developer_id'];
    if (!isset($grouped_programs[$dev_id])) {
        $grouped_programs[$dev_id] = [
            'developer_id' => $dev_id,
            'developer_name' => $prog['developer_name'],
            'perusahaan' => $prog['nama_perusahaan'],
            'programs' => []
        ];
    }
    $grouped_programs[$dev_id]['programs'][] = $prog;
}

// ========== SET VARIABLES ==========
$page_title = 'Program Booking';
$page_subtitle = 'Kelola Program Booking Unit';
$page_icon = 'fas fa-tags';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    
    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="welcome-text">
            <i class="<?= $page_icon ?>"></i>
            <div class="welcome-text-content">
                <h2><?= $page_title ?></h2>
                <span><?= $page_subtitle ?></span>
            </div>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span id="date"></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span id="time"></span></div>
        </div>
    </div>
    
    <!-- ALERT MESSAGES -->
    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>
    
    <!-- ACTION BUTTON -->
    <div class="action-bar">
        <button onclick="openAddModal()" class="btn-primary">
            <i class="fas fa-plus-circle"></i>
            <span>Tambah Program</span>
        </button>
    </div>
    
    <!-- DAFTAR PROGRAM -->
    <div class="program-list">
        <?php foreach ($programs as $prog): ?>
        <div class="program-card <?= !$prog['is_active'] ? 'inactive' : '' ?>">
            <div class="program-header">
                <div class="program-title">
                    <h3><?= htmlspecialchars($prog['nama_program']) ?></h3>
                    <span class="developer-badge">
                        <i class="fas fa-building"></i>
                        <?= htmlspecialchars($prog['developer_name']) ?>
                    </span>
                </div>
                <div class="program-status">
                    <?php if ($prog['is_all_in']): ?>
                    <span class="badge badge-warning">ALL-IN</span>
                    <?php endif; ?>
                    <span class="badge <?= $prog['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                        <?= $prog['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                </div>
            </div>
            
            <div class="program-body">
                <div class="program-detail">
                    <div class="detail-item">
                        <span class="detail-label">Booking Fee</span>
                        <span class="detail-value">Rp <?= number_format($prog['booking_fee'], 0, ',', '.') ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Unit Terpakai</span>
                        <span class="detail-value"><?= $prog['total_units'] ?> unit</span>
                    </div>
                </div>
                
                <?php if (!empty($prog['include_renovasi'])): ?>
                <div class="program-note">
                    <i class="fas fa-tools"></i>
                    <span><?= nl2br(htmlspecialchars($prog['include_renovasi'])) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($prog['ketentuan_pembatalan'])): ?>
                <div class="program-note">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= nl2br(htmlspecialchars($prog['ketentuan_pembatalan'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="program-footer">
                <div class="program-actions">
                    <a href="?toggle=<?= $prog['id'] ?>" class="btn-icon" title="<?= $prog['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                        <i class="fas fa-<?= $prog['is_active'] ? 'ban' : 'check-circle' ?>"></i>
                    </a>
                    <button onclick="editProgram(<?= htmlspecialchars(json_encode($prog)) ?>)" class="btn-icon" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php if ($prog['total_units'] == 0): ?>
                    <a href="?delete=<?= $prog['id'] ?>" class="btn-icon btn-danger" 
                       title="Hapus" onclick="return confirm('Hapus program <?= htmlspecialchars($prog['nama_program']) ?>?')">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($programs)): ?>
        <div class="empty-state">
            <i class="fas fa-tag"></i>
            <p>Belum ada program booking</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>" class="pagination-item">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php for($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?= $i ?>" class="pagination-item <?= $i == $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page+1 ?>" class="pagination-item">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- INFO CARD -->
    <div class="info-card">
        <div class="info-icon">
            <i class="fas fa-info-circle"></i>
        </div>
        <div class="info-text">
            <strong>Informasi Program Booking</strong>
            <p>Program booking dapat digunakan untuk unit secara single atau multiple. Untuk multiple program, gunakan halaman Unit Programs.</p>
        </div>
    </div>
    
    <!-- FOOTER STATS -->
    <div class="footer-stats">
        <div class="footer-stat">
            <i class="fas fa-tags"></i>
            <span>Total Program: <?= $total_programs ?></span>
        </div>
        <div class="footer-stat">
            <i class="fas fa-building"></i>
            <span>Developer: <?= count($developers) ?></span>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Program Booking v2.0</p>
    </div>
    
</div>

<!-- MODAL TAMBAH PROGRAM -->
<div class="modal-overlay" id="addOverlay" onclick="closeAddModal()"></div>
<div class="modal" id="addModal">
    <div class="modal-header">
        <h2><i class="fas fa-plus-circle"></i> Tambah Program</h2>
        <button class="modal-close" onclick="closeAddModal()">&times;</button>
    </div>
    
    <form method="POST" class="modal-form">
        <input type="hidden" name="action" value="add_program">
        
        <div class="modal-body">
            <div class="form-group">
                <label>Developer *</label>
                <select name="developer_id" class="form-control" required>
                    <option value="">Pilih Developer</option>
                    <?php foreach ($developers as $dev): ?>
                    <option value="<?= $dev['id'] ?>">
                        <?= htmlspecialchars($dev['nama_lengkap']) ?> <?= !empty($dev['nama_perusahaan']) ? '- ' . $dev['nama_perusahaan'] : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Nama Program *</label>
                <input type="text" name="nama_program" class="form-control" required placeholder="Contoh: All In One Siap Tinggal">
            </div>
            
            <div class="form-group">
                <label>Booking Fee *</label>
                <input type="text" name="booking_fee" class="form-control" required placeholder="500000" onkeyup="formatRupiah(this)">
                <small>Isi dalam angka, contoh: 500000</small>
            </div>
            
            <div class="form-checkbox">
                <input type="checkbox" name="is_all_in" id="is_all_in" value="1">
                <label for="is_all_in">Program All-In (termasuk renovasi & biaya lain)</label>
            </div>
            
            <div class="form-group">
                <label>Termasuk Renovasi</label>
                <textarea name="include_renovasi" class="form-control" rows="3" placeholder="Contoh: Keramik Dapur, Canopy..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Ketentuan Pembatalan</label>
                <textarea name="ketentuan_pembatalan" class="form-control" rows="3" placeholder="Contoh: Booking fee hangus jika batal..."></textarea>
            </div>
            
            <div class="form-checkbox">
                <input type="checkbox" name="is_active" id="is_active_add" value="1" checked>
                <label for="is_active_add">Aktifkan program</label>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeAddModal()">Batal</button>
            <button type="submit" class="btn-primary">Simpan Program</button>
        </div>
    </form>
</div>

<!-- MODAL EDIT PROGRAM -->
<div class="modal-overlay" id="editOverlay" onclick="closeEditModal()"></div>
<div class="modal" id="editModal">
    <div class="modal-header">
        <h2><i class="fas fa-edit"></i> Edit Program</h2>
        <button class="modal-close" onclick="closeEditModal()">&times;</button>
    </div>
    
    <form method="POST" class="modal-form">
        <input type="hidden" name="action" value="edit_program">
        <input type="hidden" name="id" id="edit_id" value="">
        
        <div class="modal-body">
            <div class="form-group">
                <label>Developer *</label>
                <select name="developer_id" id="edit_developer_id" class="form-control" required>
                    <option value="">Pilih Developer</option>
                    <?php foreach ($developers as $dev): ?>
                    <option value="<?= $dev['id'] ?>">
                        <?= htmlspecialchars($dev['nama_lengkap']) ?> <?= !empty($dev['nama_perusahaan']) ? '- ' . $dev['nama_perusahaan'] : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Nama Program *</label>
                <input type="text" name="nama_program" id="edit_nama" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Booking Fee *</label>
                <input type="text" name="booking_fee" id="edit_fee" class="form-control" required onkeyup="formatRupiah(this)">
            </div>
            
            <div class="form-checkbox">
                <input type="checkbox" name="is_all_in" id="edit_is_all_in" value="1">
                <label for="edit_is_all_in">Program All-In</label>
            </div>
            
            <div class="form-group">
                <label>Termasuk Renovasi</label>
                <textarea name="include_renovasi" id="edit_renovasi" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Ketentuan Pembatalan</label>
                <textarea name="ketentuan_pembatalan" id="edit_ketentuan" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-checkbox">
                <input type="checkbox" name="is_active" id="edit_active" value="1">
                <label for="edit_active">Aktifkan program</label>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeEditModal()">Batal</button>
            <button type="submit" class="btn-primary">Update Program</button>
        </div>
    </form>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('show');
    document.getElementById('addOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('show');
    document.getElementById('addOverlay').classList.remove('show');
    document.body.style.overflow = '';
}

function editProgram(prog) {
    document.getElementById('edit_id').value = prog.id;
    document.getElementById('edit_developer_id').value = prog.developer_id;
    document.getElementById('edit_nama').value = prog.nama_program;
    document.getElementById('edit_fee').value = prog.booking_fee.toLocaleString('id-ID');
    document.getElementById('edit_is_all_in').checked = prog.is_all_in == 1;
    document.getElementById('edit_renovasi').value = prog.include_renovasi || '';
    document.getElementById('edit_ketentuan').value = prog.ketentuan_pembatalan || '';
    document.getElementById('edit_active').checked = prog.is_active == 1;
    
    document.getElementById('editModal').classList.add('show');
    document.getElementById('editOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
    document.getElementById('editOverlay').classList.remove('show');
    document.body.style.overflow = '';
}

function formatRupiah(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    if (value) {
        input.value = parseInt(value).toLocaleString('id-ID');
    }
}

function updateDateTime() {
    const now = new Date();
    document.getElementById('date').textContent = now.toLocaleDateString('id-ID', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    document.getElementById('time').textContent = now.toLocaleTimeString('id-ID', { 
        hour12: false 
    });
}

setInterval(updateDateTime, 1000);
updateDateTime();

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddModal();
        closeEditModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>