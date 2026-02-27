<?php
/**
 * SETTINGS_EXTERNAL.PHP - TAUFIKMARIE.COM
 * Version: 2.3.0 - UI SUPER KEREN (FIX: Layout Desktop Lebar & Center)
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

// Hanya super admin yang bisa akses halaman ini
if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Admin.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== PROSES UPDATE ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_external') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $nomor_whatsapp = trim($_POST['nomor_whatsapp'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($nama_lengkap) || empty($nomor_whatsapp)) {
            $error = "❌ Nama lengkap dan nomor WhatsApp wajib diisi!";
        } else {
            // Validasi nomor WhatsApp
            $phone_clean = validatePhone($nomor_whatsapp);
            if (!$phone_clean) {
                $error = "❌ Nomor WhatsApp tidak valid (harus 10-15 digit)!";
            } else {
                try {
                    // Update di tabel marketing_config
                    $stmt = $conn->prepare("
                        UPDATE marketing_config SET 
                            name = ?,
                            phone = ?,
                            email = ?,
                            updated_at = NOW()
                        WHERE id = 2
                    ");
                    $stmt->execute([$nama_lengkap, $phone_clean, $email]);
                    
                    // Update juga di tabel users untuk Super Admin (ID 1)
                    $stmt2 = $conn->prepare("
                        UPDATE users SET 
                            nama_lengkap = ?,
                            email = ?,
                            updated_at = NOW()
                        WHERE id = 1
                    ");
                    $stmt2->execute([$nama_lengkap, $email]);
                    
                    $success = "✅ Data marketing external berhasil diupdate!";
                    logSystem("External marketing settings updated", ['by' => $_SESSION['username']], 'INFO', 'settings.log');
                    
                } catch (Exception $e) {
                    $error = "❌ Gagal update: " . $e->getMessage();
                    logSystem("External marketing update failed", ['error' => $e->getMessage()], 'ERROR', 'settings.log');
                }
            }
        }
    }
}

// ========== AMBIL DATA EXTERNAL MARKETING ==========
$external_data = [];

// Ambil dari marketing_config (ID 2)
$stmt = $conn->prepare("SELECT * FROM marketing_config WHERE id = 2");
$stmt->execute();
$config = $stmt->fetch();

if ($config) {
    $external_data = [
        'nama_lengkap' => $config['name'],
        'nomor_whatsapp' => $config['phone'],
        'email' => $config['email'] ?? 'lapakmarie@gmail.com'
    ];
} else {
    // Default jika tidak ada
    $external_data = [
        'nama_lengkap' => 'Taufik Marie',
        'nomor_whatsapp' => '628133150078',
        'email' => 'lapakmarie@gmail.com'
    ];
}

// ========== SET VARIABLES UNTUK INCLUDE ==========
$page_title = 'Marketing External';
$page_subtitle = 'Kelola Data Marketing External';
$page_icon = 'fas fa-user-tie';

// ========== INCLUDE HEADER ==========
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
    --whatsapp: #25D366;
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
    padding: 16px;
    margin-left: 0 !important;
}

/* ===== TOP BAR - MOBILE FIRST ===== */
.top-bar {
    background: var(--surface);
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 20px;
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
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    background: rgba(214,79,60,0.1);
    color: var(--secondary);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
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
    border-radius: 14px;
    margin-bottom: 20px;
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

/* ===== INFO CARD ===== */
.info-card {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    box-shadow: 0 8px 20px rgba(27,74,60,0.15);
}

.info-icon {
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: var(--warning);
    flex-shrink: 0;
}

.info-content {
    flex: 1;
}

.info-content h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--warning);
    margin: 0 0 4px 0;
}

.info-content p {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    opacity: 0.9;
}

/* ===== SETTINGS CARD ===== */
.settings-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
}

.settings-title {
    color: var(--primary);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 700;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-soft);
}

.settings-title i {
    width: 32px;
    height: 32px;
    background: rgba(214,79,60,0.1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--secondary);
    font-size: 16px;
}

/* ===== FORM GROUP ===== */
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
    width: 18px;
    color: var(--secondary);
    margin-right: 6px;
    font-size: 13px;
}

.required-star {
    color: var(--danger);
    margin-left: 2px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    background: white;
    transition: all 0.2s;
}

.form-control:focus {
    border-color: var(--secondary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(214,79,60,0.1);
}

.form-control::placeholder {
    color: #B0BAB4;
    font-size: 13px;
}

.form-hint {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
    display: block;
}

/* ===== INFO TIPS ===== */
.info-tips {
    background: var(--primary-soft);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    border-left: 4px solid var(--secondary);
}

.info-tips i {
    width: 32px;
    height: 32px;
    background: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--secondary);
    font-size: 16px;
    flex-shrink: 0;
}

.info-tips-content {
    flex: 1;
    min-width: 200px;
}

.info-tips-content strong {
    color: var(--primary);
    font-size: 14px;
    display: block;
    margin-bottom: 2px;
}

.info-tips-content p {
    margin: 0;
    color: var(--text-light);
    font-size: 12px;
    line-height: 1.5;
}

.info-tips-btn {
    background: var(--primary);
    color: white;
    padding: 10px 20px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    transition: all 0.2s;
}

.info-tips-btn i {
    width: auto;
    height: auto;
    background: transparent;
    color: white;
    font-size: 12px;
}

.info-tips-btn:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
}

/* ===== PREVIEW CARD ===== */
.preview-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-top: 20px;
    border: 2px dashed var(--secondary);
}

.preview-title {
    color: var(--primary);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 700;
}

.preview-title i {
    width: 28px;
    height: 28px;
    background: rgba(214,79,60,0.1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--secondary);
    font-size: 14px;
}

.preview-box {
    background: var(--primary-soft);
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 16px;
}

.preview-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
}

.preview-row:not(:last-child) {
    border-bottom: 1px dashed rgba(0,0,0,0.1);
}

.preview-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
}

.preview-value {
    font-size: 15px;
    font-weight: 600;
    color: var(--primary);
}

.preview-value.whatsapp {
    color: var(--whatsapp);
}

.preview-wa-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: var(--whatsapp);
    color: white;
    padding: 12px 24px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
}

.preview-wa-button i {
    font-size: 14px;
}

.preview-wa-button:hover {
    background: #128C7E;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(37,211,102,0.3);
}

.preview-note {
    text-align: center;
    color: var(--text-muted);
    font-size: 11px;
    margin-top: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.preview-note i {
    color: var(--secondary);
    font-size: 12px;
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

.btn-secondary {
    background: white;
    color: var(--text);
    border: 2px solid var(--border);
    padding: 12px 24px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-secondary i {
    font-size: 14px;
}

.btn-secondary:hover {
    background: var(--border);
}

.action-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin: 24px 0 30px;
    flex-wrap: wrap;
}

/* ===== FOOTER ===== */
.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 11px;
    border-top: 1px solid var(--border);
}

/* ===== DESKTOP LAYOUT - FIX LEBAR & CENTER ===== */
@media (min-width: 1024px) {
    .main-content {
        margin-left: 280px !important;
        padding: 30px !important;
        max-width: 1200px;
        margin-right: auto !important;
        margin-left: auto !important;
    }
    
    /* TOP BAR DESKTOP - LEBIH LEBAR */
    .top-bar {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding: 20px 28px;
        width: 100%;
    }
    
    .welcome-text i {
        width: 56px;
        height: 56px;
        font-size: 24px;
        border-radius: 16px;
    }
    
    .welcome-text h2 {
        font-size: 22px;
    }
    
    .welcome-text h2 span {
        font-size: 14px;
    }
    
    /* INFO CARD DESKTOP */
    .info-card {
        padding: 24px 28px;
        gap: 16px;
        width: 100%;
    }
    
    .info-icon {
        width: 48px;
        height: 48px;
        font-size: 24px;
        border-radius: 14px;
    }
    
    .info-content h3 {
        font-size: 18px;
    }
    
    .info-content p {
        font-size: 14px;
    }
    
    /* SETTINGS CARD DESKTOP */
    .settings-card {
        padding: 28px;
        width: 100%;
    }
    
    .settings-title {
        font-size: 18px;
        margin-bottom: 20px;
    }
    
    .settings-title i {
        width: 36px;
        height: 36px;
        font-size: 18px;
    }
    
    /* FORM GRID - 2 KOLOM DI DESKTOP */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
    }
    
    .form-group {
        margin-bottom: 0;
    }
    
    .form-group.full-width {
        grid-column: span 2;
    }
    
    .form-group label {
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    .form-group label i {
        width: 20px;
        font-size: 14px;
    }
    
    .form-control {
        padding: 14px 18px;
        font-size: 15px;
    }
    
    .form-hint {
        font-size: 12px;
        margin-top: 6px;
    }
    
    /* INFO TIPS DESKTOP */
    .info-tips {
        padding: 20px 24px;
        gap: 16px;
        width: 100%;
    }
    
    .info-tips i {
        width: 36px;
        height: 36px;
        font-size: 18px;
    }
    
    .info-tips-content strong {
        font-size: 15px;
    }
    
    .info-tips-content p {
        font-size: 13px;
    }
    
    .info-tips-btn {
        padding: 12px 24px;
        font-size: 13px;
    }
    
    /* PREVIEW CARD DESKTOP */
    .preview-card {
        padding: 28px;
        width: 100%;
    }
    
    .preview-title {
        font-size: 18px;
    }
    
    .preview-title i {
        width: 32px;
        height: 32px;
        font-size: 16px;
    }
    
    .preview-box {
        padding: 20px;
    }
    
    .preview-row {
        padding: 10px 0;
    }
    
    .preview-label {
        font-size: 14px;
    }
    
    .preview-value {
        font-size: 16px;
    }
    
    .preview-wa-button {
        padding: 14px 28px;
        font-size: 15px;
        min-width: 220px;
    }
    
    .preview-note {
        font-size: 12px;
        margin-top: 20px;
    }
    
    /* ACTION BUTTONS DESKTOP */
    .action-buttons {
        gap: 20px;
        margin: 30px 0 40px;
    }
    
    .btn-primary, .btn-secondary {
        padding: 14px 28px;
        font-size: 15px;
        min-width: 200px;
    }
    
    .btn-primary i, .btn-secondary i {
        font-size: 15px;
    }
    
    /* FOOTER DESKTOP */
    .footer {
        font-size: 12px;
        padding: 24px;
        margin-top: 40px;
    }
}

/* ===== UTILITY ===== */
.text-center { text-align: center; }
.w-100 { width: 100%; }
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
    
    <!-- INFO CARD -->
    <div class="info-card">
        <div class="info-icon">
            <i class="fas fa-info-circle"></i>
        </div>
        <div class="info-content">
            <h3>Informasi Marketing External</h3>
            <p>Data ini akan muncul di halaman terima kasih ketika lead masuk ke bucket external (Anda sebagai pemilik platform).</p>
        </div>
    </div>
    
    <!-- FORM UTAMA -->
    <form method="POST" id="externalForm">
        <input type="hidden" name="action" value="update_external">
        
        <!-- CARD DATA MARKETING EXTERNAL -->
        <div class="settings-card">
            <div class="settings-title">
                <i class="fas fa-user-circle"></i> Data Marketing External (Super Admin)
            </div>
            
            <!-- GRID 2 KOLOM UNTUK DESKTOP -->
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nama Lengkap <span class="required-star">*</span></label>
                    <input type="text" name="nama_lengkap" class="form-control" 
                           value="<?= htmlspecialchars($external_data['nama_lengkap']) ?>" 
                           required
                           placeholder="Contoh: Taufik Marie">
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-whatsapp" style="color: var(--whatsapp);"></i> Nomor WhatsApp <span class="required-star">*</span></label>
                    <input type="tel" name="nomor_whatsapp" class="form-control" 
                           value="<?= htmlspecialchars($external_data['nomor_whatsapp']) ?>" 
                           required
                           placeholder="628133150078">
                </div>
                
                <div class="form-group full-width">
                    <label><i class="fas fa-envelope"></i> Email (Opsional)</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?= htmlspecialchars($external_data['email']) ?>" 
                           placeholder="email@domain.com">
                </div>
            </div>
            
            <div class="form-hint" style="margin-top: 12px;">Format nomor WhatsApp: 628xxxxxxxxx (tanpa + atau spasi)</div>
        </div>
        
        <!-- INFO API -->
        <div class="info-tips">
            <i class="fas fa-info-circle"></i>
            <div class="info-tips-content">
                <strong>Konfigurasi API BalesOtomatis</strong>
                <p>Untuk mengubah konfigurasi API (Number ID, Access Token, dll), silakan gunakan halaman Settings → Marketing.</p>
            </div>
            <a href="settings.php?tab=whatsapp" class="info-tips-btn">
                <i class="fas fa-external-link-alt"></i> Buka Settings
            </a>
        </div>
        
        <!-- ACTION BUTTONS -->
        <div class="action-buttons">
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> SIMPAN PERUBAHAN
            </button>
            
            <a href="settings.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> KEMBALI
            </a>
        </div>
        
    </form>
    
    <!-- PREVIEW CARD -->
    <div class="preview-card">
        <div class="preview-title">
            <i class="fas fa-eye"></i> Preview Halaman Terima Kasih
        </div>
        
        <div class="preview-box">
            <div class="preview-row">
                <span class="preview-label">Konsultan Anda:</span>
                <span class="preview-value" id="previewName"><?= htmlspecialchars($external_data['nama_lengkap']) ?></span>
            </div>
            <div class="preview-row">
                <span class="preview-label">WhatsApp:</span>
                <span class="preview-value whatsapp" id="previewPhone"><?= htmlspecialchars($external_data['nomor_whatsapp']) ?></span>
            </div>
        </div>
        
        <div class="text-center">
            <a href="#" onclick="return false;" class="preview-wa-button">
                <i class="fab fa-whatsapp"></i> Chat via WhatsApp
            </a>
        </div>
        
        <div class="preview-note">
            <i class="fas fa-info-circle"></i> Tampilan saat lead masuk ke bucket external
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - External Marketing Settings v2.3</p>
    </div>
    
</div>

<script>
// ===== LIVE PREVIEW UPDATE =====
document.querySelector('input[name="nama_lengkap"]').addEventListener('input', function(e) {
    document.getElementById('previewName').textContent = e.target.value || 'Taufik Marie';
});

document.querySelector('input[name="nomor_whatsapp"]').addEventListener('input', function(e) {
    document.getElementById('previewPhone').textContent = e.target.value || '628133150078';
});

// ===== CONFIRM BEFORE LEAVE =====
let formChanged = false;
const form = document.getElementById('externalForm');

if (form) {
    form.addEventListener('input', function() {
        formChanged = true;
    });
}

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'Ada perubahan yang belum disimpan. Yakin ingin keluar?';
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
</script>

<!-- FOOTER -->
<?php include 'includes/footer.php'; ?>