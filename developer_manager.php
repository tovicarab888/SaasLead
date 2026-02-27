<?php
/**
 * DEVELOPER_MANAGER.PHP - Manajemen Developer untuk SEO
 * Version: 1.0.0 - Tambah developer baru via database
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// ========== CHECK AUTH ==========
if (!checkAuth()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Hanya admin yang bisa akses
if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Super Admin.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== PROSES TAMBAH DEVELOPER ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'add_developer') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $nama_perusahaan = trim($_POST['nama_perusahaan'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $kota = trim($_POST['kota'] ?? 'Kuningan');
        $folder_name = trim($_POST['folder_name'] ?? '');
        $telepon_perusahaan = trim($_POST['telepon_perusahaan'] ?? '');
        
        // Validasi
        if (empty($nama_lengkap) || empty($username) || empty($email) || empty($password)) {
            $error = "❌ Field wajib harus diisi!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "❌ Email tidak valid!";
        } elseif (!empty($folder_name) && !preg_match('/^[a-z0-9-]+$/', $folder_name)) {
            $error = "❌ Nama folder hanya boleh huruf kecil, angka, dan tanda strip (-)";
        } else {
            try {
                // Cek username sudah ada
                $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $check->execute([$username, $email]);
                if ($check->fetch()) {
                    $error = "❌ Username atau Email sudah digunakan!";
                } else {
                    // Hash password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert developer baru
                    $insert = $conn->prepare("
                        INSERT INTO users (
                            username, email, password, nama_lengkap, nama_perusahaan,
                            kota, telepon_perusahaan, folder_name, role, is_active,
                            created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'developer', 1, NOW(), NOW())
                    ");
                    $insert->execute([
                        $username, $email, $password_hash, $nama_lengkap, $nama_perusahaan,
                        $kota, $telepon_perusahaan, $folder_name
                    ]);
                    
                    $new_id = $conn->lastInsertId();
                    
                    // Generate default SEO
                    generateDefaultSEO($new_id);
                    
                    $success = "✅ Developer berhasil ditambahkan dengan ID: $new_id";
                    logSystem("New developer added", ['id' => $new_id, 'name' => $nama_lengkap], 'INFO', 'developer.log');
                }
            } catch (Exception $e) {
                $error = "❌ Gagal menambah developer: " . $e->getMessage();
            }
        }
    }
    
    elseif ($_POST['action'] === 'update_folder') {
        $id = (int)($_POST['id'] ?? 0);
        $folder_name = trim($_POST['folder_name'] ?? '');
        
        if ($id > 0 && !empty($folder_name)) {
            if (!preg_match('/^[a-z0-9-]+$/', $folder_name)) {
                $error = "❌ Nama folder hanya boleh huruf kecil, angka, dan tanda strip (-)";
            } else {
                try {
                    $update = $conn->prepare("UPDATE users SET folder_name = ? WHERE id = ? AND role = 'developer'");
                    $update->execute([$folder_name, $id]);
                    $success = "✅ Nama folder berhasil diupdate!";
                } catch (Exception $e) {
                    $error = "❌ Gagal update: " . $e->getMessage();
                }
            }
        }
    }
}

// ========== AMBIL DATA DEVELOPER ==========
$developers = $conn->query("
    SELECT id, nama_lengkap, nama_perusahaan, username, email, kota, 
           folder_name, telepon_perusahaan, is_active
    FROM users 
    WHERE role = 'developer' 
    ORDER BY id DESC
")->fetchAll();

// ========== SET VARIABLES ==========
$page_title = 'Manajemen Developer';
$page_subtitle = 'Tambah & Kelola Developer untuk SEO';
$page_icon = 'fas fa-building';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== CSS SINKRON DENGAN ADMIN.CSS ===== */
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
    --danger: #D64F3C;
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-xl: 24px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
}

.main-content {
    width: 100%;
    padding: 12px;
}

.top-bar {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 16px;
    margin-bottom: 16px;
    border-left: 6px solid var(--secondary);
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
    width: 44px;
    height: 44px;
    background: rgba(214,79,60,0.1);
    color: var(--secondary);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.welcome-text h2 {
    font-size: 18px;
    font-weight: 800;
}

.welcome-text h2 span {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-muted);
}

.datetime {
    display: flex;
    justify-content: space-between;
    background: var(--bg);
    padding: 6px 14px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
    color: var(--primary);
}

.alert {
    padding: 12px 16px;
    border-radius: var(--radius-md);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
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

/* ===== CARD FORM ===== */
.form-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 20px;
    margin-bottom: 24px;
    border: 1px solid var(--border);
}

.form-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-title i {
    color: var(--secondary);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

@media (min-width: 768px) {
    .form-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--primary);
    font-size: 13px;
}

.form-group label i {
    color: var(--secondary);
    margin-right: 4px;
    width: 16px;
}

.form-control, .form-select {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    background: white;
}

.form-control:focus, .form-select:focus {
    border-color: var(--secondary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(214,79,60,0.1);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-secondary {
    background: var(--border);
    color: var(--text);
    border: none;
    padding: 12px 24px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
}

/* ===== TABLE CARD ===== */
.table-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 20px;
    border: 1px solid var(--border);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.table-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 6px;
}

.table-header h3 i {
    color: var(--secondary);
}

.table-badge {
    background: var(--primary-soft);
    color: var(--primary);
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
}

.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

th {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    padding: 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 12px;
}

td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

.folder-badge {
    background: var(--primary-soft);
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.folder-badge i {
    color: var(--secondary);
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: white;
    color: var(--text);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 2px;
}

.action-btn.edit {
    color: #ff8f00;
    border-color: #ff8f00;
}

.action-btn.edit:hover {
    background: #ff8f00;
    color: white;
}

.footer {
    text-align: center;
    margin-top: 30px;
    padding: 16px;
    color: var(--text-muted);
    font-size: 11px;
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
    
    <!-- ALERT -->
    <?php if ($success): ?>
    <div class="alert success"><?= $success ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert error"><?= $error ?></div>
    <?php endif; ?>
    
    <!-- FORM TAMBAH DEVELOPER -->
    <div class="form-card">
        <div class="form-title">
            <i class="fas fa-plus-circle"></i> Tambah Developer Baru
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_developer">
            
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Nama Perusahaan</label>
                    <input type="text" name="nama_perusahaan" class="form-control">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-circle"></i> Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Kota</label>
                    <input type="text" name="kota" class="form-control" value="Kuningan">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-folder"></i> Nama Folder</label>
                    <input type="text" name="folder_name" class="form-control" placeholder="contoh: perumahan-baru">
                    <small style="color: var(--text-muted);">Huruf kecil, angka, dan strip saja</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Telepon Perusahaan</label>
                    <input type="text" name="telepon_perusahaan" class="form-control">
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Simpan Developer
                </button>
            </div>
        </form>
    </div>
    
    <!-- TABEL DEVELOPER -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Daftar Developer</h3>
            <div class="table-badge">Total: <?= count($developers) ?></div>
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Perusahaan</th>
                        <th>Username</th>
                        <th>Folder</th>
                        <th>Kota</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($developers as $dev): ?>
                    <tr>
                        <td><strong>#<?= $dev['id'] ?></strong></td>
                        <td><?= htmlspecialchars($dev['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($dev['nama_perusahaan'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($dev['username']) ?></td>
                        <td>
                            <?php if (!empty($dev['folder_name'])): ?>
                            <span class="folder-badge">
                                <i class="fas fa-folder"></i> <?= $dev['folder_name'] ?>
                            </span>
                            <?php else: ?>
                            <span style="color: var(--text-muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($dev['kota'] ?: '-') ?></td>
                        <td>
                            <span style="background: <?= $dev['is_active'] ? 'var(--success)' : 'var(--danger)' ?>; color: white; padding: 4px 8px; border-radius: 20px; font-size: 11px;">
                                <?= $dev['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td>
                            <button class="action-btn edit" onclick="editFolder(<?= $dev['id'] ?>, '<?= $dev['folder_name'] ?>')" title="Edit Folder">
                                <i class="fas fa-folder"></i>
                            </button>
                            <a href="developer_seo.php?developer_id=<?= $dev['id'] ?>" class="action-btn edit" title="Edit SEO">
                                <i class="fas fa-search"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Developer Manager</p>
    </div>
    
</div>

<!-- MODAL EDIT FOLDER -->
<div class="modal" id="folderModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-folder"></i> Edit Nama Folder</h2>
            <button class="modal-close" onclick="closeFolderModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_folder">
            <input type="hidden" name="id" id="folder_dev_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Nama Folder</label>
                    <input type="text" name="folder_name" id="folder_name" class="form-control" placeholder="contoh: perumahan-baru">
                    <small style="color: var(--text-muted);">Hanya huruf kecil, angka, dan strip (-)</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeFolderModal()">Batal</button>
                <button type="submit" class="btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<style>
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
    display: flex !important;
}

.modal-content {
    background: white;
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 500px;
    animation: modalFade 0.3s ease;
}

.modal-header {
    padding: 16px 20px;
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
}

.modal-close {
    width: 40px;
    height: 40px;
    background: var(--primary-soft);
    border: none;
    border-radius: var(--radius-sm);
    color: var(--secondary);
    font-size: 18px;
    cursor: pointer;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 16px 20px 24px;
    display: flex;
    gap: 12px;
    border-top: 1px solid var(--border);
}

.modal-footer button {
    flex: 1;
    min-height: 44px;
    border-radius: 50px;
}
</style>

<script>
function editFolder(id, folder) {
    document.getElementById('folder_dev_id').value = id;
    document.getElementById('folder_name').value = folder || '';
    document.getElementById('folderModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeFolderModal() {
    document.getElementById('folderModal').classList.remove('show');
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

// Close modal on outside click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});
</script>

<?php include 'includes/footer.php'; ?>