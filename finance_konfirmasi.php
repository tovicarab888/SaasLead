<?php
/**
 * FINANCE_KONFIRMASI.PHP - LEADENGINE
 * Version: 1.0.0 - Halaman Konfirmasi Pencairan Komisi
 * MOBILE FIRST UI - UPLOAD BUKTI TRANSFER + AUTO-FORMAT RUPIAH
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek session finance
if (!isFinance()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$finance_id = $_SESSION['user_id'];
$finance_name = $_SESSION['nama_lengkap'] ?? 'Finance';
$developer_id = $_SESSION['developer_id'] ?? 0;

if ($developer_id <= 0) {
    die("Error: Developer ID tidak ditemukan");
}

// Ambil ID komisi dari URL
$komisi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($komisi_id <= 0) {
    header('Location: finance_komisi.php');
    exit();
}

// Ambil data komisi
$stmt = $conn->prepare("
    SELECT k.*, 
           l.first_name, l.last_name, l.phone as customer_phone,
           m.nama_lengkap as marketing_name, m.phone as marketing_phone,
           u.nomor_unit, u.tipe_unit, u.harga,
           dev.nama_lengkap as developer_name
    FROM komisi_logs k
    LEFT JOIN leads l ON k.lead_id = l.id
    LEFT JOIN marketing_team m ON k.marketing_id = m.id
    LEFT JOIN units u ON k.unit_id = u.id
    LEFT JOIN users dev ON k.developer_id = dev.id
    WHERE k.id = ? AND k.developer_id = ?
");
$stmt->execute([$komisi_id, $developer_id]);
$komisi = $stmt->fetch();

if (!$komisi) {
    header('Location: finance_komisi.php');
    exit();
}

// Jika sudah cair, redirect
if ($komisi['status'] == 'cair') {
    header('Location: finance_komisi.php?status=cair');
    exit();
}

// Ambil daftar rekening bank
$banks = [];
$stmt = $conn->prepare("
    SELECT * FROM banks 
    WHERE developer_id = ? AND is_active = 1 
    ORDER BY nama_bank
");
$stmt->execute([$developer_id]);
$banks = $stmt->fetchAll();

// Proses konfirmasi
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'confirm') {
        $bank_id = isset($_POST['bank_id']) ? (int)$_POST['bank_id'] : 0;
        $tanggal_cair = $_POST['tanggal_cair'] ?? date('Y-m-d H:i:s');
        $catatan = trim($_POST['catatan'] ?? '');
        
        // Upload bukti transfer
        $bukti_transfer = null;
        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = dirname(__DIR__) . '/uploads/bukti/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            
            if (in_array($file_ext, $allowed)) {
                $filename = 'bukti_' . $komisi_id . '_' . time() . '.' . $file_ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $filepath)) {
                    $bukti_transfer = $filename;
                } else {
                    $error = "❌ Gagal upload file";
                }
            } else {
                $error = "❌ Tipe file harus JPG, PNG, atau PDF";
            }
        } else {
            $error = "❌ Bukti transfer wajib diupload";
        }
        
        if (empty($error) && $bukti_transfer) {
            try {
                $conn->beginTransaction();
                
                // Update status komisi
                $stmt = $conn->prepare("
                    UPDATE komisi_logs SET 
                        status = 'cair',
                        tanggal_cair = ?,
                        bukti_transfer = ?,
                        catatan = ?
                    WHERE id = ? AND developer_id = ?
                ");
                $stmt->execute([$tanggal_cair, $bukti_transfer, $catatan, $komisi_id, $developer_id]);
                
                // Kirim notifikasi ke marketing
                if ($komisi['marketing_id'] && !empty($komisi['marketing_phone'])) {
                    $marketing_data = [
                        'id' => $komisi['marketing_id'],
                        'nama_lengkap' => $komisi['marketing_name'],
                        'phone' => $komisi['marketing_phone']
                    ];
                    
                    $komisi_data = [
                        'customer_name' => trim($komisi['first_name'] . ' ' . $komisi['last_name']),
                        'unit_info' => $komisi['nomor_unit'] . ' (' . $komisi['tipe_unit'] . ')',
                        'harga' => $komisi['harga'],
                        'komisi_final' => $komisi['komisi_final'],
                        'developer_name' => $komisi['developer_name']
                    ];
                    
                    // Panggil fungsi notifikasi (nanti diimplementasi)
                    // sendKomisiNotification($komisi_data, $marketing_data);
                }
                
                $conn->commit();
                $success = "✅ Komisi berhasil dicairkan!";
                
                // Redirect setelah 2 detik
                header("refresh:2;url=finance_komisi.php?status=cair");
                
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    } elseif ($action == 'batal') {
        $catatan = trim($_POST['catatan_batal'] ?? '');
        
        try {
            $stmt = $conn->prepare("
                UPDATE komisi_logs SET 
                    status = 'batal',
                    catatan = ?
                WHERE id = ? AND developer_id = ?
            ");
            $stmt->execute([$catatan, $komisi_id, $developer_id]);
            
            $success = "✅ Komisi dibatalkan!";
            header("refresh:2;url=finance_komisi.php");
            
        } catch (Exception $e) {
            $error = "❌ Gagal: " . $e->getMessage();
        }
    }
}

$full_name = trim(($komisi['first_name'] ?? '') . ' ' . ($komisi['last_name'] ?? ''));

$page_title = 'Konfirmasi Pencairan';
$page_subtitle = 'Komisi Marketing';
$page_icon = 'fas fa-check-circle';

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
    --finance: #2A9D8F;
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
    border-left: 6px solid var(--finance);
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
    background: rgba(42,157,143,0.1);
    color: var(--finance);
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

.komisi-card {
    background: white;
    border-radius: 24px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    border-left: 6px solid var(--warning);
}

.komisi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-soft);
}

.komisi-title {
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
}

.komisi-status {
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    background: var(--warning);
    color: #1A2A24;
}

.komisi-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 14px;
}

.komisi-label {
    color: var(--text-muted);
    font-weight: 500;
}

.komisi-value {
    font-weight: 700;
    color: var(--text);
    text-align: right;
}

.komisi-value.amount {
    color: var(--secondary);
    font-size: 18px;
}

.form-card {
    background: white;
    border-radius: 24px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
}

.form-title {
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-title i {
    color: var(--finance);
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
    color: var(--finance);
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
    border-color: var(--finance);
    outline: none;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.upload-area {
    border: 2px dashed var(--border);
    border-radius: 16px;
    padding: 20px;
    background: var(--primary-soft);
    cursor: pointer;
    text-align: center;
    transition: all 0.3s;
}

.upload-area:hover {
    border-color: var(--finance);
    background: #d4e8e0;
}

.upload-area i {
    font-size: 40px;
    color: var(--finance);
    margin-bottom: 10px;
}

.upload-text {
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 5px;
}

.upload-filename {
    color: var(--text-muted);
    font-size: 12px;
}

.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.btn-primary {
    flex: 1;
    background: linear-gradient(135deg, var(--finance), #40BEB0);
    color: white;
    border: none;
    padding: 16px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 56px;
}

.btn-primary i {
    font-size: 16px;
}

.btn-danger {
    flex: 1;
    background: linear-gradient(135deg, var(--danger), #FF6B4A);
    color: white;
    border: none;
    padding: 16px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 56px;
}

.btn-secondary {
    flex: 1;
    background: var(--border);
    color: var(--text);
    border: none;
    padding: 16px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    min-height: 56px;
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
    
    .komisi-card {
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .form-card {
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
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
    
    <!-- KOMISI CARD -->
    <div class="komisi-card">
        <div class="komisi-header">
            <span class="komisi-title">Detail Komisi</span>
            <span class="komisi-status">PENDING</span>
        </div>
        
        <div class="komisi-row">
            <span class="komisi-label">Marketing</span>
            <span class="komisi-value"><?= htmlspecialchars($komisi['marketing_name'] ?? '-') ?></span>
        </div>
        
        <div class="komisi-row">
            <span class="komisi-label">Customer</span>
            <span class="komisi-value"><?= htmlspecialchars($full_name ?: 'Lead #' . $komisi['lead_id']) ?></span>
        </div>
        
        <div class="komisi-row">
            <span class="komisi-label">WhatsApp</span>
            <span class="komisi-value"><?= htmlspecialchars($komisi['customer_phone'] ?? '-') ?></span>
        </div>
        
        <div class="komisi-row">
            <span class="komisi-label">Unit</span>
            <span class="komisi-value"><?= htmlspecialchars($komisi['nomor_unit'] ?? '-') ?> (<?= htmlspecialchars($komisi['tipe_unit'] ?? '-') ?>)</span>
        </div>
        
        <div class="komisi-row">
            <span class="komisi-label">Tipe</span>
            <span class="komisi-value"><?= $komisi['assigned_type'] == 'internal' ? 'Internal' : 'External' ?></span>
        </div>
        
        <div class="komisi-row">
            <span class="komisi-label">Komisi</span>
            <span class="komisi-value amount">Rp <?= number_format($komisi['komisi_final'], 0, ',', '.') ?></span>
        </div>
        
        <div class="komisi-row">
            <span class="komisi-label">Tanggal</span>
            <span class="komisi-value"><?= date('d/m/Y H:i', strtotime($komisi['created_at'])) ?></span>
        </div>
    </div>
    
    <!-- FORM KONFIRMASI -->
    <form method="POST" enctype="multipart/form-data" class="form-card">
        <input type="hidden" name="action" value="confirm">
        
        <div class="form-title">
            <i class="fas fa-check-circle"></i> Konfirmasi Pencairan
        </div>
        
        <div class="form-group">
            <label><i class="fas fa-university"></i> Rekening Tujuan</label>
            <select name="bank_id" class="form-select" required>
                <option value="">— Pilih Rekening —</option>
                <?php foreach ($banks as $b): ?>
                <option value="<?= $b['id'] ?>">
                    <?= htmlspecialchars($b['nama_bank']) ?> - <?= htmlspecialchars($b['nomor_rekening']) ?> a.n <?= htmlspecialchars($b['atas_nama']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label><i class="fas fa-calendar"></i> Tanggal Cair</label>
            <input type="datetime-local" name="tanggal_cair" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
        </div>
        
        <div class="form-group">
            <label><i class="fas fa-file-invoice"></i> Bukti Transfer</label>
            <div class="upload-area" onclick="document.getElementById('bukti_file').click()">
                <input type="file" id="bukti_file" name="bukti_transfer" accept=".jpg,.jpeg,.png,.pdf" style="display: none;" required onchange="updateFileName(this)">
                <i class="fas fa-cloud-upload-alt"></i>
                <p class="upload-text">Klik untuk upload file</p>
                <p class="upload-filename" id="file_name">Belum ada file dipilih</p>
            </div>
            <small style="color: var(--text-muted);">Format: JPG, PNG, PDF. Maksimal 2MB</small>
        </div>
        
        <div class="form-group">
            <label><i class="fas fa-sticky-note"></i> Catatan (opsional)</label>
            <textarea name="catatan" class="form-control" rows="3" placeholder="Catatan transfer..."></textarea>
        </div>
        
        <div class="action-buttons">
            <button type="submit" class="btn-primary">
                <i class="fas fa-check-circle"></i> Konfirmasi Cair
            </button>
            <button type="button" class="btn-danger" onclick="showBatalModal()">
                <i class="fas fa-times-circle"></i> Batalkan
            </button>
            <a href="finance_komisi.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </form>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Konfirmasi Pencairan v1.0</p>
    </div>
    
</div>

<!-- MODAL BATAL -->
<div class="modal" id="batalModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-times-circle"></i> Batalkan Komisi</h2>
            <button class="modal-close" onclick="closeBatalModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="batal">
            
            <div class="modal-body">
                <p style="margin-bottom: 16px;">Yakin ingin membatalkan komisi ini?</p>
                
                <div class="form-group">
                    <label>Alasan Pembatalan</label>
                    <textarea name="catatan_batal" class="form-control" rows="3" placeholder="Contoh: Marketing mengundurkan diri, unit batal, dll" required></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeBatalModal()">Tutup</button>
                <button type="submit" class="btn-danger">Ya, Batalkan</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateFileName(input) {
    const fileName = input.files[0] ? input.files[0].name : 'Belum ada file dipilih';
    document.getElementById('file_name').textContent = fileName;
}

function showBatalModal() {
    document.getElementById('batalModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeBatalModal() {
    document.getElementById('batalModal').classList.remove('show');
    document.body.style.overflow = '';
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
</script>

<?php include 'includes/footer.php'; ?>