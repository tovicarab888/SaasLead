<?php
/**
 * FINANCE_PLATFORM_EXTERNAL.PHP - Kelola Marketing External
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

// ========== PROSES FORM ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_marketing') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $marketing_type_id = (int)($_POST['marketing_type_id'] ?? 0);
        
        // Validasi
        if (empty($nama_lengkap) || empty($phone) || empty($username) || empty($password)) {
            $error = "❌ Nama, No. HP, Username, dan Password harus diisi";
        } else {
            try {
                // Cek username
                $check = $conn->prepare("SELECT id FROM marketing_team WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $error = "❌ Username sudah digunakan";
                } else {
                    // Dapatkan marketing_type_id untuk external
                    if ($marketing_type_id == 0) {
                        $stmt = $conn->prepare("SELECT id FROM marketing_types WHERE type_name = 'external' LIMIT 1");
                        $stmt->execute();
                        $type = $stmt->fetch();
                        $marketing_type_id = $type ? $type['id'] : 0;
                    }
                    
                    $insert = $conn->prepare("
                        INSERT INTO marketing_team (
                            developer_id, nama_lengkap, phone, email, username, password_hash,
                            marketing_type_id, is_active, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    $insert->execute([
                        0, // developer_id 0 untuk external
                        $nama_lengkap,
                        $phone,
                        $email,
                        $username,
                        password_hash($password, PASSWORD_DEFAULT),
                        $marketing_type_id
                    ]);
                    
                    $success = "✅ Marketing external berhasil ditambahkan";
                    
                    // Log aktivitas
                    logSystem("Marketing external ditambahkan", [
                        'nama' => $nama_lengkap,
                        'username' => $username,
                        'by' => $_SESSION['username']
                    ], 'INFO', 'finance.log');
                }
            } catch (Exception $e) {
                $error = "❌ Gagal menambah marketing: " . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'edit_marketing') {
        $id = (int)($_POST['id'] ?? 0);
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id > 0 && !empty($nama_lengkap)) {
            try {
                $update = $conn->prepare("
                    UPDATE marketing_team SET 
                        nama_lengkap = ?,
                        phone = ?,
                        email = ?,
                        is_active = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->execute([$nama_lengkap, $phone, $email, $is_active, $id]);
                
                // Update password jika diisi
                if (!empty($_POST['password'])) {
                    $update_pass = $conn->prepare("UPDATE marketing_team SET password_hash = ? WHERE id = ?");
                    $update_pass->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
                }
                
                $success = "✅ Data marketing berhasil diupdate";
                
            } catch (Exception $e) {
                $error = "❌ Gagal update: " . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'delete_marketing') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            try {
                // Soft delete - set is_active = 0
                $update = $conn->prepare("UPDATE marketing_team SET is_active = 0 WHERE id = ?");
                $update->execute([$id]);
                $success = "✅ Marketing dinonaktifkan";
                
            } catch (Exception $e) {
                $error = "❌ Gagal menonaktifkan: " . $e->getMessage();
            }
        }
    }
    
    // Hapus bagian round robin jika tidak ada kolom
}

// ========== AMBIL DATA MARKETING EXTERNAL (VERSI AMAN) ==========
$marketings = [];
try {
    // Query sederhana tanpa round_robin_order
    $sql = "
        SELECT 
            m.*,
            mt.type_name,
            mt.commission_type,
            mt.commission_value,
            (SELECT COUNT(*) FROM komisi_logs WHERE marketing_id = m.id AND assigned_type = 'external') as total_komisi,
            (SELECT COALESCE(SUM(komisi_final), 0) FROM komisi_logs WHERE marketing_id = m.id AND assigned_type = 'external') as total_nominal,
            (SELECT MAX(created_at) FROM komisi_logs WHERE marketing_id = m.id AND assigned_type = 'external') as last_komisi
        FROM marketing_team m
        LEFT JOIN marketing_types mt ON m.marketing_type_id = mt.id
        WHERE mt.type_name = 'external' OR mt.type_name IS NULL
        ORDER BY m.nama_lengkap ASC
    ";
    
    $stmt = $conn->query($sql);
    $marketings = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error mengambil data marketing external: " . $e->getMessage());
    $marketings = [];
}

// ========== AMBIL TIPE MARKETING ==========
$marketing_types = $conn->query("
    SELECT * FROM marketing_types 
    WHERE type_name = 'external' 
    ORDER BY type_name
")->fetchAll();

// ========== SET VARIABLES ==========
$page_title = 'Marketing External';
$page_subtitle = 'Kelola Tim Marketing External';
$page_icon = 'fas fa-user-tie';

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
    min-width: 900px;
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

.status-badge.active {
    background: var(--success);
}

.status-badge.inactive {
    background: var(--danger);
}

.status-badge.info {
    background: var(--info);
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

.action-btn.delete {
    background: #ffeeed;
    color: var(--danger);
    border-color: var(--danger);
}

.action-btn.delete:hover {
    background: var(--danger);
    color: white;
}

.action-btn i {
    font-size: 14px;
}

/* ===== MODAL ===== */
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

@media (min-width: 768px) {
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
        justify-content: flex-start;
    }
    
    .btn-add {
        width: auto;
        padding: 14px 28px;
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
    
    <!-- ACTION BUTTONS -->
    <div class="action-bar">
        <button onclick="openAddModal()" class="btn-add">
            <i class="fas fa-plus-circle"></i> Tambah Marketing External
        </button>
    </div>
    
    <!-- TABLE MARKETING -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-users"></i> Daftar Marketing External</h3>
            <div class="table-badge">Total: <?= count($marketings) ?> marketing</div>
        </div>
        
        <?php if (empty($marketings)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>Belum ada marketing external</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Kontak</th>
                        <th>Komisi</th>
                        <th>Total Komisi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marketings as $m): ?>
                    <tr>
                        <td>#<?= $m['id'] ?></td>
                        <td><strong><?= htmlspecialchars($m['nama_lengkap']) ?></strong></td>
                        <td><?= $m['username'] ?></td>
                        <td>
                            <?= $m['phone'] ?><br>
                            <small style="color: var(--text-muted);"><?= $m['email'] ?></small>
                        </td>
                        <td>
                            <span class="status-badge info" style="background: var(--info);">
                                <?= $m['commission_type'] == 'EXTERNAL_PERCENT' ? $m['commission_value'] . '%' : 'Rp ' . number_format($m['commission_value']) ?>
                            </span>
                        </td>
                        <td>
                            <?= $m['total_komisi'] ?> komisi<br>
                            <strong style="color: var(--success);">Rp <?= number_format($m['total_nominal'], 0, ',', '.') ?></strong>
                        </td>
                        <td>
                            <span class="status-badge <?= $m['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $m['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn edit" onclick="editMarketing(<?= htmlspecialchars(json_encode($m)) ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($m['is_active']): ?>
                                <button class="action-btn delete" onclick="deactivateMarketing(<?= $m['id'] ?>, '<?= htmlspecialchars($m['nama_lengkap']) ?>')" title="Nonaktifkan">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Finance Platform External v2.0</p>
    </div>
    
</div>

<!-- MODAL ADD MARKETING -->
<div class="modal" id="addModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle"></i> Tambah Marketing External</h2>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_marketing">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-whatsapp"></i> Nomor WhatsApp *</label>
                    <input type="text" name="phone" class="form-control" placeholder="628xxxxxxxxxx" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-circle"></i> Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Tipe Marketing</label>
                    <select name="marketing_type_id" class="form-control">
                        <?php foreach ($marketing_types as $type): ?>
                        <option value="<?= $type['id'] ?>"><?= $type['type_name'] ?> (<?= $type['commission_type'] ?> <?= $type['commission_value'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('addModal')">Batal</button>
                <button type="submit" class="btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT MARKETING -->
<div class="modal" id="editModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Marketing External</h2>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="edit_marketing">
            <input type="hidden" name="id" id="edit_id" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-whatsapp"></i> Nomor WhatsApp</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-circle"></i> Username</label>
                    <input type="text" name="username" id="edit_username" class="form-control" readonly style="background: #f5f5f5;">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password Baru (kosongkan jika tidak diubah)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                
                <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="is_active" id="edit_active" value="1" style="width: 18px; height: 18px;">
                    <label for="edit_active" style="margin: 0;">Aktif</label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Batal</button>
                <button type="submit" class="btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    openModal('addModal');
}

function editMarketing(m) {
    document.getElementById('edit_id').value = m.id;
    document.getElementById('edit_nama').value = m.nama_lengkap;
    document.getElementById('edit_phone').value = m.phone;
    document.getElementById('edit_email').value = m.email || '';
    document.getElementById('edit_username').value = m.username;
    document.getElementById('edit_active').checked = m.is_active == 1;
    openModal('editModal');
}

function deactivateMarketing(id, name) {
    if (confirm(`Nonaktifkan marketing ${name}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_marketing">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function openModal(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
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
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});
</script>

<?php include 'includes/footer.php'; ?>