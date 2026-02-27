<?php
/**
 * CANVASING_DELETE.PHP - LEADENGINE
 * Version: 1.0.0 - Hapus data canvasing (Hanya Super Admin)
 * FULL CODE - 100% LENGKAP
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek session admin
if (!isAdmin()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$message = '';
$error = '';

// Proses hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $confirm = $_POST['confirm'] ?? '';
    
    if ($confirm !== 'DELETE') {
        $error = 'Ketik "DELETE" untuk konfirmasi';
    } else {
        try {
            $conn->beginTransaction();
            
            // Ambil path foto dulu
            $stmt = $conn->prepare("SELECT photo_path FROM canvasing_logs WHERE id = ?");
            $stmt->execute([$id]);
            $photo_path = $stmt->fetchColumn();
            
            // Hapus dari database
            $stmt = $conn->prepare("DELETE FROM canvasing_logs WHERE id = ?");
            $stmt->execute([$id]);
            
            // Hapus file foto jika ada
            if ($photo_path) {
                $full_path = dirname(__DIR__) . '/' . $photo_path;
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
            }
            
            $conn->commit();
            $message = "Data canvasing ID $id berhasil dihapus permanen.";
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Ambil semua data canvasing dengan filter
$developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
$marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

$sql = "
    SELECT 
        c.*,
        m.nama_lengkap as marketing_name,
        m.phone as marketing_phone,
        l.display_name as location_display,
        l.icon,
        u.nama_lengkap as developer_name
    FROM canvasing_logs c
    LEFT JOIN marketing_team m ON c.marketing_id = m.id
    LEFT JOIN locations l ON c.location_key = l.location_key
    LEFT JOIN users u ON c.developer_id = u.id
    WHERE 1=1
";
$params = [];

if ($developer_id > 0) {
    $sql .= " AND c.developer_id = ?";
    $params[] = $developer_id;
}

if ($marketing_id > 0) {
    $sql .= " AND c.marketing_id = ?";
    $params[] = $marketing_id;
}

$sql .= " AND DATE(c.created_at) BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;

$sql .= " ORDER BY c.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$canvasing_list = $stmt->fetchAll();

// Ambil daftar developer
$developers = $conn->query("SELECT id, nama_lengkap FROM users WHERE role = 'developer' AND is_active = 1 ORDER BY nama_lengkap")->fetchAll();

// Ambil daftar marketing (jika developer dipilih)
$marketings = [];
if ($developer_id > 0) {
    $stmt = $conn->prepare("SELECT id, nama_lengkap FROM marketing_team WHERE developer_id = ? AND is_active = 1 ORDER BY nama_lengkap");
    $stmt->execute([$developer_id]);
    $marketings = $stmt->fetchAll();
}

$page_title = 'Hapus Data Canvasing';
$page_subtitle = 'Hanya untuk Super Admin';
$page_icon = 'fas fa-trash-alt';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== STYLES KHUSUS ===== */
:root {
    --primary: #1B4A3C;
    --primary-light: #2A5F4E;
    --secondary: #D64F3C;
    --secondary-light: #FF6B4A;
    --bg: #F5F3F0;
    --surface: #FFFFFF;
    --text: #1A2A24;
    --text-muted: #7A8A84;
    --border: #E0DAD3;
    --danger: #D64F3C;
    --danger-light: #FFE5E5;
}

.main-content {
    margin-left: 280px;
    padding: 24px;
    background: var(--bg);
    min-height: 100vh;
}

.top-bar {
    background: var(--surface);
    border-radius: 20px;
    padding: 20px 24px;
    margin-bottom: 24px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.welcome-text {
    display: flex;
    align-items: center;
    gap: 16px;
}

.welcome-text i {
    width: 56px;
    height: 56px;
    background: rgba(214,79,60,0.1);
    color: var(--secondary);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.welcome-text h2 {
    font-size: 22px;
    font-weight: 800;
    color: var(--text);
    margin: 0;
    line-height: 1.3;
}

.welcome-text h2 span {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-muted);
    margin-top: 4px;
}

/* Alert */
.alert {
    padding: 16px 20px;
    border-radius: 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert.success {
    background: #E8F5E9;
    color: #2A9D8F;
    border-left: 4px solid #2A9D8F;
}

.alert.error {
    background: #FFEBEE;
    color: #D64F3C;
    border-left: 4px solid #D64F3C;
}

/* Filter Bar */
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
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-group label {
    display: block;
    font-weight: 600;
    font-size: 12px;
    color: var(--primary);
    margin-bottom: 5px;
}

.filter-select, .filter-input {
    width: 100%;
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
    align-items: flex-end;
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
}

.filter-btn.reset {
    background: var(--border);
    color: var(--text);
}

/* Table */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
}

th {
    background: var(--primary-soft);
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 13px;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.delete-btn {
    background: var(--danger-light);
    color: var(--danger);
    border: 1px solid var(--danger);
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.delete-btn:hover {
    background: var(--danger);
    color: white;
}

/* Modal */
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
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 28px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 30px 60px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 2px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    color: var(--danger);
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close {
    width: 40px;
    height: 40px;
    background: var(--bg);
    border: none;
    border-radius: 12px;
    color: var(--text);
    font-size: 20px;
    cursor: pointer;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 2px solid var(--border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.warning-card {
    background: var(--danger-light);
    border-radius: 16px;
    padding: 20px;
    margin: 20px 0;
    color: var(--danger);
    text-align: center;
    border: 2px solid var(--danger);
}

.warning-card i {
    font-size: 48px;
    margin-bottom: 10px;
}

.confirm-input {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 16px;
    text-align: center;
    margin: 15px 0;
}

.confirm-input:focus {
    border-color: var(--danger);
    outline: none;
}

.btn-danger {
    background: var(--danger);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
}

.btn-secondary {
    background: var(--border);
    color: var(--text);
    border: none;
    padding: 12px 24px;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 12px;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
    }
    
    .filter-btn {
        flex: 1;
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
            <div class="date" id="currentDate">
                <i class="fas fa-calendar-alt"></i>
                <span>Memuat...</span>
            </div>
            <div class="time" id="currentTime">
                <i class="fas fa-clock"></i>
                <span>--:--:--</span>
            </div>
        </div>
    </div>
    
    <!-- ALERT MESSAGES -->
    <?php if ($message): ?>
    <div class="alert success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <!-- FILTER BAR -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label><i class="fas fa-building"></i> Developer</label>
                <select name="developer_id" class="filter-select" onchange="this.form.submit()">
                    <option value="">Semua Developer</option>
                    <?php foreach ($developers as $dev): ?>
                    <option value="<?= $dev['id'] ?>" <?= $developer_id == $dev['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dev['nama_lengkap']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($developer_id > 0): ?>
            <div class="filter-group">
                <label><i class="fas fa-user"></i> Marketing</label>
                <select name="marketing_id" class="filter-select">
                    <option value="">Semua Marketing</option>
                    <?php foreach ($marketings as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $marketing_id == $m['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nama_lengkap']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Dari Tanggal</label>
                <input type="date" name="date_from" class="filter-input" value="<?= $date_from ?>">
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Sampai Tanggal</label>
                <input type="date" name="date_to" class="filter-input" value="<?= $date_to ?>">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
                <a href="?" class="filter-btn reset">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- TABLE -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Developer</th>
                    <th>Marketing</th>
                    <th>Lokasi</th>
                    <th>Customer</th>
                    <th>Tanggal</th>
                    <th>Foto</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($canvasing_list)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 60px;">
                        <i class="fas fa-camera fa-4x" style="color: var(--border);"></i>
                        <p style="margin-top: 16px; color: var(--text-muted);">Tidak ada data canvasing</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($canvasing_list as $item): ?>
                    <tr>
                        <td><strong>#<?= $item['id'] ?></strong></td>
                        <td><?= htmlspecialchars($item['developer_name'] ?? '-') ?></td>
                        <td>
                            <div><strong><?= htmlspecialchars($item['marketing_name'] ?? '-') ?></strong></div>
                            <small><?= htmlspecialchars($item['marketing_phone'] ?? '') ?></small>
                        </td>
                        <td>
                            <?= $item['icon'] ?? '📍' ?> <?= htmlspecialchars($item['location_display'] ?? $item['location_key']) ?>
                        </td>
                        <td>
                            <?php if ($item['customer_name']): ?>
                                <div><strong><?= htmlspecialchars($item['customer_name']) ?></strong></div>
                                <small><?= htmlspecialchars($item['customer_phone'] ?? '') ?></small>
                            <?php elseif ($item['instansi_name']): ?>
                                <div><strong><?= htmlspecialchars($item['instansi_name']) ?></strong></div>
                                <small>PIC: <?= htmlspecialchars($item['pic_name'] ?? '') ?></small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></td>
                        <td>
                            <?php if (!empty($item['photo_path'])): ?>
                            <a href="https://taufikmarie.com/<?= $item['photo_path'] ?>" target="_blank">
                                <i class="fas fa-image" style="color: var(--primary);"></i> Lihat
                            </a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="delete-btn" onclick="showDeleteModal(<?= $item['id'] ?>)">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
</div>

<!-- DELETE MODAL -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Hapus Data Canvasing</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="warning-card">
                    <i class="fas fa-trash-alt"></i>
                    <h4 style="margin: 10px 0; color: var(--danger);">PERINGATAN!</h4>
                    <p>Anda akan menghapus data canvasing <strong>#<span id="deleteIdSpan"></span></strong></p>
                    <p style="font-size: 14px; margin-top: 10px;">
                        Tindakan ini <strong>tidak dapat dibatalkan</strong>.<br>
                        Data akan hilang permanen dari database dan file foto akan dihapus.
                    </p>
                </div>
                
                <p style="margin-bottom: 10px; font-weight: 600;">
                    Ketik <span style="background: var(--border); padding: 4px 8px; border-radius: 6px; font-family: monospace;">DELETE</span> untuk konfirmasi:
                </p>
                
                <input type="hidden" name="delete_id" id="deleteId">
                <input type="text" name="confirm" class="confirm-input" placeholder="DELETE" required pattern="DELETE" title="Ketik DELETE">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Batal</button>
                <button type="submit" class="btn-danger">
                    <i class="fas fa-trash"></i> Hapus Permanen
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showDeleteModal(id) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteIdSpan').textContent = id;
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
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

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeDeleteModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>