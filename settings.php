<?php
/**
 * SETTINGS.PHP - TAUFIKMARIE.COM ULTIMATE
 * Version: 11.0.0 - UI SUPER KEREN (Mempertahankan UI Referensi)
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
    die('Akses ditolak. Halaman ini hanya untuk Super Admin.');
}

$conn = getDB();

// ========== AMBIL KONFIGURASI DARI DATABASE ==========
// 1. Marketing Config (untuk API BalesOtomatis)
$marketing_config = [];
try {
    $stmt = $conn->query("SELECT * FROM marketing_config WHERE id = 2");
    $marketing_config = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error loading marketing config: " . $e->getMessage());
}

// Default values jika belum ada
if (!$marketing_config) {
    $marketing_config = [
        'id' => 2,
        'name' => 'Taufik Marie',
        'phone' => '628133150078',
        'email' => 'lapakmarie@gmail.com',
        'number_id' => 'BO-U80VWtQlpti3IlSj',
        'notification_number_id' => 'BO-GU8Ll274yVjj0hQc',
        'access_token' => 'VwrFrkYj1l1841fn58M',
        'notification_token' => 'VwrFrkYj1l1841fn58M',
        'is_active' => 1
    ];
}

// 2. Tracking Config (Meta, TikTok, Google) - Opsional
$tracking_config = [];
try {
    $stmt = $conn->query("SELECT * FROM tracking_config ORDER BY platform");
    while ($row = $stmt->fetch()) {
        $tracking_config[$row['platform']] = $row;
    }
} catch (Exception $e) {
    error_log("Error loading tracking config: " . $e->getMessage());
}

// Default tracking values
if (!isset($tracking_config['meta'])) {
    $tracking_config['meta'] = ['pixel_id' => '2224730075026860', 'access_token' => '', 'api_version' => 'v19.0', 'is_active' => 1];
}
if (!isset($tracking_config['tiktok'])) {
    $tracking_config['tiktok'] = ['pixel_id' => 'D3L405BC77U8AFC9O0RG', 'access_token' => '', 'api_version' => 'v1.3', 'is_active' => 1];
}
if (!isset($tracking_config['google'])) {
    $tracking_config['google'] = ['measurement_id' => 'G-B9YZXZQ8L8', 'api_secret' => '', 'is_active' => 1];
}

// ========== PROSES UPDATE ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Tab aktif
    $active_tab = $_POST['tab'] ?? 'whatsapp';
    
    // ===== TAB WHATSAPP (BALESOTOMATIS) =====
    if ($active_tab == 'whatsapp') {
        try {
            $conn->beginTransaction();
            
            $name = trim($_POST['name'] ?? 'Taufik Marie');
            $phone = trim($_POST['phone'] ?? '628133150078');
            $email = trim($_POST['email'] ?? 'lapakmarie@gmail.com');
            $number_id = trim($_POST['number_id'] ?? '');
            $notification_number_id = trim($_POST['notification_number_id'] ?? '');
            $access_token = trim($_POST['access_token'] ?? '');
            $notification_token = trim($_POST['notification_token'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Validasi
            if (empty($number_id)) {
                throw new Exception("Device ID (Number ID) wajib diisi!");
            }
            
            if (empty($access_token)) {
                throw new Exception("Access Token wajib diisi!");
            }
            
            if (empty($notification_number_id)) {
                $notification_number_id = $number_id; // Default sama dengan number_id
            }
            
            if (empty($notification_token)) {
                $notification_token = $access_token; // Default sama dengan access_token
            }
            
            // Update atau Insert
            $check = $conn->prepare("SELECT id FROM marketing_config WHERE id = 2");
            $check->execute();
            
            if ($check->fetch()) {
                // Update
                $stmt = $conn->prepare("
                    UPDATE marketing_config SET 
                        name = ?,
                        phone = ?,
                        email = ?,
                        number_id = ?,
                        notification_number_id = ?,
                        access_token = ?,
                        notification_token = ?,
                        is_active = ?,
                        updated_at = NOW()
                    WHERE id = 2
                ");
                $stmt->execute([$name, $phone, $email, $number_id, $notification_number_id, $access_token, $notification_token, $is_active]);
            } else {
                // Insert
                $stmt = $conn->prepare("
                    INSERT INTO marketing_config 
                    (id, name, phone, email, number_id, notification_number_id, access_token, notification_token, is_active, created_at, updated_at)
                    VALUES (2, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$name, $phone, $email, $number_id, $notification_number_id, $access_token, $notification_token, $is_active]);
            }
            
            $conn->commit();
            $success = "✅ Konfigurasi WhatsApp BalesOtomatis berhasil diupdate!";
            logSystem("WhatsApp config updated", ['by' => $_SESSION['username']], 'INFO', 'settings.log');
            
            // Refresh data
            $stmt = $conn->query("SELECT * FROM marketing_config WHERE id = 2");
            $marketing_config = $stmt->fetch();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "❌ Gagal update: " . $e->getMessage();
            logSystem("WhatsApp config update failed", ['error' => $e->getMessage()], 'ERROR', 'settings.log');
        }
    }
    
    // ===== TAB TRACKING PIXEL =====
    elseif ($active_tab == 'tracking') {
        try {
            $conn->beginTransaction();
            
            // Meta/Facebook
            $meta_pixel_id = trim($_POST['meta_pixel_id'] ?? '');
            $meta_access_token = trim($_POST['meta_access_token'] ?? '');
            $meta_api_version = trim($_POST['meta_api_version'] ?? 'v19.0');
            $meta_is_active = isset($_POST['meta_is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE tracking_config SET pixel_id = ?, access_token = ?, api_version = ?, is_active = ?, updated_at = NOW() WHERE platform = 'meta'");
            $stmt->execute([$meta_pixel_id, $meta_access_token, $meta_api_version, $meta_is_active]);
            
            // TikTok
            $tiktok_pixel_id = trim($_POST['tiktok_pixel_id'] ?? '');
            $tiktok_access_token = trim($_POST['tiktok_access_token'] ?? '');
            $tiktok_api_version = trim($_POST['tiktok_api_version'] ?? 'v1.3');
            $tiktok_is_active = isset($_POST['tiktok_is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE tracking_config SET pixel_id = ?, access_token = ?, api_version = ?, is_active = ?, updated_at = NOW() WHERE platform = 'tiktok'");
            $stmt->execute([$tiktok_pixel_id, $tiktok_access_token, $tiktok_api_version, $tiktok_is_active]);
            
            // Google
            $google_measurement_id = trim($_POST['google_measurement_id'] ?? '');
            $google_api_secret = trim($_POST['google_api_secret'] ?? '');
            $google_is_active = isset($_POST['google_is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE tracking_config SET measurement_id = ?, api_secret = ?, is_active = ?, updated_at = NOW() WHERE platform = 'google'");
            $stmt->execute([$google_measurement_id, $google_api_secret, $google_is_active]);
            
            $conn->commit();
            $success = "✅ Konfigurasi tracking pixel berhasil diupdate!";
            logSystem("Tracking config updated", ['by' => $_SESSION['username']], 'INFO', 'tracking.log');
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "❌ Gagal update: " . $e->getMessage();
            logSystem("Tracking config update failed", ['error' => $e->getMessage()], 'ERROR', 'tracking.log');
        }
    }
}

// ========== DETEKSI TAB AKTIF ==========
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'whatsapp';

// ========== SET VARIABLES UNTUK INCLUDE ==========
$page_title = 'Pengaturan Sistem';
$page_subtitle = 'Konfigurasi WhatsApp API & Tracking';
$page_icon = 'fas fa-cog';

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

/* ===== TAB NAVIGATION ===== */
.tab-navigation {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    flex-wrap: wrap;
    background: white;
    border-radius: 60px;
    padding: 6px;
    border: 1px solid var(--border);
}

.tab-link {
    flex: 1;
    padding: 14px 20px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    text-align: center;
    font-size: 14px;
    transition: all 0.2s;
}

.tab-link.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.tab-link.inactive {
    background: white;
    color: var(--text-light);
}

/* ===== INFO CARD ===== */
.info-card {
    background: linear-gradient(135deg, var(--whatsapp) 0%, #128C7E 100%);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 30px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 20px;
    color: white;
    box-shadow: 0 15px 35px rgba(37, 211, 102, 0.3);
    width: 100%;
    box-sizing: border-box;
}

.info-card.tracking {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    box-shadow: 0 15px 35px rgba(27, 74, 60, 0.3);
}

.info-icon {
    font-size: 48px;
    color: white;
    flex-shrink: 0;
}

.info-content {
    flex: 1;
    min-width: 250px;
}

.info-content h3 {
    font-size: 20px;
    color: white;
    margin: 0 0 8px 0;
}

.info-content p {
    margin: 0;
    font-size: 14px;
    line-height: 1.6;
    opacity: 0.9;
}

.info-button {
    background: white;
    color: var(--whatsapp);
    border: none;
    padding: 14px 28px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    white-space: nowrap;
    flex-shrink: 0;
    transition: all 0.2s;
}

.info-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255,255,255,0.2);
}

.info-button.tracking {
    background: #1877F2;
    color: white;
}

/* ===== SETTINGS CARD ===== */
.settings-card {
    background: white;
    border-radius: 28px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    width: 100%;
    box-sizing: border-box;
}

.settings-title {
    color: var(--primary);
    margin: 0 0 24px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 20px;
    font-weight: 700;
}

.settings-title i {
    color: var(--secondary);
}

/* ===== FORM GRID ===== */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
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

.form-control {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid var(--border);
    border-radius: 16px;
    font-size: 15px;
    font-family: 'Inter', sans-serif;
    background: white;
    min-height: 52px;
    transition: all 0.2s;
}

.form-control:focus {
    border-color: var(--secondary);
    outline: none;
    box-shadow: 0 0 0 4px rgba(214, 79, 60, 0.1);
}

.form-control[readonly] {
    background: var(--primary-soft);
    color: var(--text-light);
    cursor: not-allowed;
}

/* ===== PASSWORD WRAPPER ===== */
.password-wrapper {
    position: relative;
}

.password-wrapper input {
    width: 100%;
    padding-right: 50px;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 18px;
    padding: 10px;
}

.password-toggle:hover {
    color: var(--secondary);
}

/* ===== DIVIDER ===== */
.form-divider {
    border-top: 2px dashed var(--border);
    margin: 20px 0;
}

/* ===== NOTIFICATION SECTION ===== */
.notification-section {
    background: var(--primary-soft);
    border-radius: 20px;
    padding: 20px;
    margin-top: 10px;
}

.notification-section h4 {
    color: var(--primary);
    margin: 0 0 15px 0;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.notification-section h4 i {
    color: var(--secondary);
}

/* ===== CHECKBOX GROUP ===== */
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--primary-soft);
    padding: 14px 18px;
    border-radius: 16px;
}

.checkbox-group input[type="checkbox"] {
    width: 22px;
    height: 22px;
    accent-color: var(--secondary);
}

.checkbox-group label {
    font-weight: 600;
    color: var(--primary);
    cursor: pointer;
}

/* ===== STATUS CARD ===== */
.status-card {
    background: white;
    border-radius: 28px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .status-grid {
        grid-template-columns: 1fr;
    }
}

.status-item {
    background: var(--primary-soft);
    padding: 20px;
    border-radius: 16px;
}

.status-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 10px;
}

.status-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    word-break: break-word;
}

.status-value.whatsapp {
    color: var(--whatsapp);
}

.status-code {
    background: white;
    padding: 12px 16px;
    border-radius: 12px;
    display: block;
    font-size: 14px;
    word-break: break-all;
    border: 1px solid var(--whatsapp);
    font-family: monospace;
}

.status-badge {
    display: inline-block;
    padding: 6px 18px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 13px;
    color: white;
}

.status-badge.active {
    background: var(--success);
}

.status-badge.inactive {
    background: var(--danger);
}

/* ===== INFO TIPS ===== */
.info-tips {
    background: var(--primary-soft);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    flex-wrap: wrap;
}

.info-tips i {
    font-size: 28px;
    color: var(--whatsapp);
    flex-shrink: 0;
}

.info-tips-content {
    flex: 1;
}

.info-tips-content strong {
    color: var(--primary);
    display: block;
    margin-bottom: 8px;
}

.info-tips-content ol {
    margin: 8px 0 0 20px;
    color: var(--text-light);
    font-size: 13px;
}

.info-tips-content li {
    margin-bottom: 4px;
}

.info-tips-content a {
    color: var(--secondary);
    text-decoration: none;
}

.info-tips-content a:hover {
    text-decoration: underline;
}

/* ===== TRACKING GRID ===== */
.tracking-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

@media (max-width: 1024px) {
    .tracking-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .tracking-grid {
        grid-template-columns: 1fr;
    }
}

.tracking-platform-card {
    background: var(--primary-soft);
    padding: 20px;
    border-radius: 16px;
    text-align: center;
}

.tracking-platform-icon {
    font-size: 40px;
    margin-bottom: 10px;
}

.tracking-platform-icon.meta { color: #1877F2; }
.tracking-platform-icon.tiktok { color: #000000; }
.tracking-platform-icon.google { color: #EA4335; }

.tracking-platform-name {
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 5px;
}

.tracking-platform-id {
    font-size: 12px;
    color: var(--text-muted);
    word-break: break-all;
    margin-bottom: 10px;
}

/* ===== BUTTONS ===== */
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 16px 36px;
    border-radius: 60px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 15px 35px rgba(27,74,60,0.3);
    transition: all 0.2s;
    min-height: 60px;
}

.btn-primary.whatsapp {
    background: linear-gradient(135deg, var(--whatsapp), #128C7E);
    box-shadow: 0 15px 35px rgba(37,211,102,0.3);
}

.btn-primary:active {
    transform: scale(0.98);
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
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: var(--text-muted);
    color: white;
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
        max-width: 1200px;
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
    
    .action-bar {
        flex-direction: row;
        justify-content: flex-start;
    }
    
    .btn-add {
        width: auto;
        padding: 14px 28px;
    }
}

/* ===== UTILITY ===== */
.text-center { text-align: center; }
.mt-4 { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }
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
    
    <!-- TAB NAVIGATION -->
    <div class="tab-navigation">
        <a href="?tab=whatsapp" class="tab-link <?= $active_tab == 'whatsapp' ? 'active' : 'inactive' ?>">
            <i class="fab fa-whatsapp"></i> WhatsApp API (BalesOtomatis)
        </a>
        <a href="?tab=tracking" class="tab-link <?= $active_tab == 'tracking' ? 'active' : 'inactive' ?>">
            <i class="fas fa-chart-line"></i> Tracking Pixel
        </a>
    </div>
    
    <?php if ($active_tab == 'whatsapp'): ?>
    <!-- ===== TAB WHATSAPP API ===== -->
    
    <!-- INFO CARD -->
    <div class="info-card">
        <i class="fab fa-whatsapp info-icon"></i>
        <div class="info-content">
            <h3>WhatsApp BalesOtomatis Configuration</h3>
            <p>Konfigurasi Device ID (Number ID) dan Access Token untuk mengirim pesan otomatis ke customer.</p>
        </div>
        <a href="https://app.balesotomatis.id/dashboard" target="_blank" class="info-button">
            <i class="fas fa-external-link-alt"></i> Buka Dashboard
        </a>
    </div>
    
    <!-- FORM WHATSAPP -->
    <form method="POST">
        <input type="hidden" name="tab" value="whatsapp">
        
        <!-- CARD UTAMA -->
        <div class="settings-card">
            <h3 class="settings-title">
                <i class="fab fa-whatsapp" style="color: #25D366;"></i> Konfigurasi API BalesOtomatis
            </h3>
            
            <!-- Data Marketing -->
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nama Marketing</label>
                    <input type="text" name="name" class="form-control" 
                           value="<?= htmlspecialchars($marketing_config['name'] ?? 'Taufik Marie') ?>" 
                           placeholder="Nama Marketing">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone-alt"></i> Nomor WhatsApp Marketing</label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?= htmlspecialchars($marketing_config['phone'] ?? '628133150078') ?>" 
                           placeholder="628133150078">
                    <small style="color: var(--text-muted);">Format: 628xxxxxxxxx (tanpa +)</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Marketing</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?= htmlspecialchars($marketing_config['email'] ?? 'lapakmarie@gmail.com') ?>" 
                           placeholder="email@domain.com">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-power-off"></i> Status</label>
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="is_active" value="1" 
                               <?= ($marketing_config['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="is_active">Aktifkan WhatsApp API</label>
                    </div>
                </div>
            </div>
            
            <div class="form-divider"></div>
            
            <!-- KONFIGURASI API -->
            <div class="form-grid">
                <div class="form-group">
                    <label style="font-weight: 700;"><i class="fas fa-id-card" style="color: #25D366;"></i> Device ID (Number ID) *</label>
                    <input type="text" name="number_id" class="form-control" 
                           value="<?= htmlspecialchars($marketing_config['number_id'] ?? 'BO-U80VWtQlpti3IlSj') ?>" 
                           placeholder="BO-XXXXXXXXXXXXXX" required
                           style="border-color: #25D366; background: #F0FFF4;">
                    <small style="color: #25D366;">Dapatkan dari dashboard BalesOtomatis → Devices</small>
                </div>
                
                <div class="form-group">
                    <label style="font-weight: 700;"><i class="fas fa-key" style="color: #25D366;"></i> Access Token *</label>
                    <div class="password-wrapper">
                        <input type="password" name="access_token" id="access_token" class="form-control" 
                               value="<?= htmlspecialchars($marketing_config['access_token'] ?? 'VwrFrkYj1l1841fn58M') ?>" 
                               placeholder="Access Token" required
                               style="border-color: #25D366; background: #F0FFF4;">
                        <button type="button" class="password-toggle" onclick="togglePassword('access_token')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small style="color: #25D366;">Dapatkan dari dashboard BalesOtomatis → Settings</small>
                </div>
            </div>
            
            <!-- NOTIFICATION CONFIG -->
            <div class="notification-section">
                <h4><i class="fas fa-bell" style="color: var(--secondary);"></i> Konfigurasi Notifikasi Marketing Internal</h4>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Notification Number ID</label>
                        <input type="text" name="notification_number_id" class="form-control" 
                               value="<?= htmlspecialchars($marketing_config['notification_number_id'] ?? $marketing_config['number_id'] ?? 'BO-GU8Ll274yVjj0hQc') ?>" 
                               placeholder="BO-XXXXXXXXXXXXXX">
                        <small>Kosongkan jika sama dengan Device ID</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Notification Token</label>
                        <div class="password-wrapper">
                            <input type="password" name="notification_token" id="notification_token" class="form-control" 
                                   value="<?= htmlspecialchars($marketing_config['notification_token'] ?? $marketing_config['access_token'] ?? '') ?>" 
                                   placeholder="Notification Token">
                            <button type="button" class="password-toggle" onclick="togglePassword('notification_token')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small>Kosongkan jika sama dengan Access Token</small>
                    </div>
                </div>
            </div>
            
            <!-- INFO TIPS -->
            <div class="info-tips">
                <i class="fas fa-info-circle"></i>
                <div class="info-tips-content">
                    <strong>Cara Mendapatkan Device ID dan Token:</strong>
                    <ol>
                        <li>Login ke <a href="https://app.balesotomatis.id/dashboard" target="_blank">dashboard.balesotomatis.id</a></li>
                        <li>Buka menu <strong>Devices</strong> → pilih device yang aktif</li>
                        <li>Copy <strong>Number ID</strong> (format: BO-xxxxxxxx)</li>
                        <li>Buka menu <strong>Settings</strong> → copy <strong>Access Token</strong></li>
                        <li>Masukkan ke form di atas</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <!-- STATUS CARD -->
        <div class="status-card">
            <h3 class="settings-title">
                <i class="fas fa-check-circle" style="color: #25D366;"></i> Status Konfigurasi Saat Ini
            </h3>
            
            <div class="status-grid">
                <div class="status-item">
                    <div class="status-label">Marketing Name</div>
                    <div class="status-value"><?= htmlspecialchars($marketing_config['name'] ?? 'Taufik Marie') ?></div>
                </div>
                
                <div class="status-item">
                    <div class="status-label">Marketing Phone</div>
                    <div class="status-value whatsapp"><?= htmlspecialchars($marketing_config['phone'] ?? '628133150078') ?></div>
                </div>
                
                <div style="grid-column: span 2;">
                    <div class="status-item">
                        <div class="status-label">Device ID (Number ID)</div>
                        <code class="status-code"><?= htmlspecialchars($marketing_config['number_id'] ?? 'BO-U80VWtQlpti3IlSj') ?></code>
                    </div>
                </div>
                
                <div style="grid-column: span 2;">
                    <div class="status-item">
                        <div class="status-label">Access Token</div>
                        <code class="status-code">
                            <?php 
                            $token = $marketing_config['access_token'] ?? 'VwrFrkYj1l1841fn58M';
                            echo htmlspecialchars(substr($token, 0, 10) . '...' . substr($token, -10));
                            ?>
                        </code>
                    </div>
                </div>
            </div>
            
            <div class="form-divider"></div>
            
            <div class="d-flex align-items-center" style="display: flex; align-items: center; gap: 12px;">
                <span style="font-weight: 600; color: var(--primary);">Status API:</span>
                <?php if ($marketing_config['is_active'] ?? 1): ?>
                    <span class="status-badge active"><i class="fas fa-check-circle"></i> AKTIF</span>
                <?php else: ?>
                    <span class="status-badge inactive"><i class="fas fa-times-circle"></i> NONAKTIF</span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- TOMBOL SIMPAN -->
        <div class="text-center mt-4">
            <button type="submit" class="btn-primary whatsapp">
                <i class="fas fa-save"></i> SIMPAN KONFIGURASI WHATSAPP
            </button>
        </div>
        
    </form>
    
    <?php elseif ($active_tab == 'tracking'): ?>
    <!-- ===== TAB TRACKING PIXEL ===== -->
    
    <!-- INFO CARD -->
    <div class="info-card tracking">
        <i class="fas fa-chart-line info-icon" style="color: #E3B584;"></i>
        <div class="info-content">
            <h3 style="color: #E3B584;">Tracking Pixel Configuration</h3>
            <p>Konfigurasi Meta Pixel, TikTok Pixel, dan Google Analytics untuk tracking pengunjung dan konversi.</p>
        </div>
        <a href="https://developers.facebook.com/docs/meta-pixel/" target="_blank" class="info-button tracking">
            <i class="fab fa-facebook"></i> Dokumentasi
        </a>
    </div>
    
    <!-- TRACKING FORM -->
    <form method="POST">
        <input type="hidden" name="tab" value="tracking">
        
        <!-- META/FACEBOOK PIXEL -->
        <div class="settings-card">
            <h3 class="settings-title">
                <i class="fab fa-facebook" style="color: #1877F2;"></i> Meta Pixel (Facebook)
            </h3>
            
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Pixel ID</label>
                    <input type="text" name="meta_pixel_id" class="form-control" 
                           value="<?= htmlspecialchars($tracking_config['meta']['pixel_id'] ?? '2224730075026860') ?>" 
                           placeholder="Contoh: 2224730075026860">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-code-branch"></i> API Version</label>
                    <input type="text" name="meta_api_version" class="form-control" 
                           value="<?= htmlspecialchars($tracking_config['meta']['api_version'] ?? 'v19.0') ?>" 
                           placeholder="v19.0">
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-key"></i> Access Token (untuk CAPI)</label>
                <div class="password-wrapper">
                    <input type="password" name="meta_access_token" id="meta_access_token" class="form-control" 
                           value="<?= htmlspecialchars($tracking_config['meta']['access_token'] ?? '') ?>" 
                           placeholder="Access Token untuk Conversions API">
                    <button type="button" class="password-toggle" onclick="togglePassword('meta_access_token')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small>Opsional, untuk server-side tracking</small>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="meta_is_active" id="meta_is_active" value="1" 
                       <?= (isset($tracking_config['meta']['is_active']) && $tracking_config['meta']['is_active']) ? 'checked' : '' ?>>
                <label for="meta_is_active"><i class="fas fa-check-circle" style="color: var(--success);"></i> Aktifkan Meta Pixel</label>
            </div>
        </div>
        
        <!-- TIKTOK PIXEL -->
        <div class="settings-card">
            <h3 class="settings-title">
                <i class="fab fa-tiktok" style="color: #000000;"></i> TikTok Pixel
            </h3>
            
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Pixel ID</label>
                    <input type="text" name="tiktok_pixel_id" class="form-control" 
                           value="<?= htmlspecialchars($tracking_config['tiktok']['pixel_id'] ?? 'D3L405BC77U8AFC9O0RG') ?>" 
                           placeholder="Contoh: D3L405BC77U8AFC9O0RG">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-code-branch"></i> API Version</label>
                    <input type="text" name="tiktok_api_version" class="form-control" 
                           value="<?= htmlspecialchars($tracking_config['tiktok']['api_version'] ?? 'v1.3') ?>" 
                           placeholder="v1.3">
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-key"></i> Access Token</label>
                <div class="password-wrapper">
                    <input type="password" name="tiktok_access_token" id="tiktok_access_token" class="form-control" 
                           value="<?= htmlspecialchars($tracking_config['tiktok']['access_token'] ?? '') ?>" 
                           placeholder="Access Token untuk Events API">
                    <button type="button" class="password-toggle" onclick="togglePassword('tiktok_access_token')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small>Opsional, untuk server-side tracking</small>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="tiktok_is_active" id="tiktok_is_active" value="1" 
                       <?= (isset($tracking_config['tiktok']['is_active']) && $tracking_config['tiktok']['is_active']) ? 'checked' : '' ?>>
                <label for="tiktok_is_active"><i class="fas fa-check-circle" style="color: var(--success);"></i> Aktifkan TikTok Pixel</label>
            </div>
        </div>
        
        <!-- GOOGLE ANALYTICS -->
        <div class="settings-card">
            <h3 class="settings-title">
                <i class="fab fa-google" style="color: #EA4335;"></i> Google Analytics 4
            </h3>
            
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Measurement ID</label>
                    <input type="text" name="google_measurement_id" class="form-control" 
                           value="<?= htmlspecialchars($tracking_config['google']['measurement_id'] ?? 'G-B9YZXZQ8L8') ?>" 
                           placeholder="G-XXXXXXXXXX">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-key"></i> API Secret</label>
                    <div class="password-wrapper">
                        <input type="password" name="google_api_secret" id="google_api_secret" class="form-control" 
                               value="<?= htmlspecialchars($tracking_config['google']['api_secret'] ?? '') ?>" 
                               placeholder="API Secret untuk Measurement Protocol">
                        <button type="button" class="password-toggle" onclick="togglePassword('google_api_secret')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="google_is_active" id="google_is_active" value="1" 
                       <?= (isset($tracking_config['google']['is_active']) && $tracking_config['google']['is_active']) ? 'checked' : '' ?>>
                <label for="google_is_active"><i class="fas fa-check-circle" style="color: var(--success);"></i> Aktifkan Google Analytics</label>
            </div>
        </div>
        
        <!-- STATUS CARD -->
        <div class="status-card">
            <h3 class="settings-title">
                <i class="fas fa-chart-line" style="color: var(--secondary);"></i> Status Tracking Saat Ini
            </h3>
            
            <div class="tracking-grid">
                <div class="tracking-platform-card">
                    <div class="tracking-platform-icon meta">
                        <i class="fab fa-facebook"></i>
                    </div>
                    <div class="tracking-platform-name">Meta Pixel</div>
                    <div class="tracking-platform-id">
                        <?= !empty($tracking_config['meta']['pixel_id']) ? $tracking_config['meta']['pixel_id'] : 'Belum dikonfigurasi' ?>
                    </div>
                    <?php if (isset($tracking_config['meta']['is_active']) && $tracking_config['meta']['is_active']): ?>
                        <span class="status-badge active">Aktif</span>
                    <?php else: ?>
                        <span class="status-badge inactive">Nonaktif</span>
                    <?php endif; ?>
                </div>
                
                <div class="tracking-platform-card">
                    <div class="tracking-platform-icon tiktok">
                        <i class="fab fa-tiktok"></i>
                    </div>
                    <div class="tracking-platform-name">TikTok Pixel</div>
                    <div class="tracking-platform-id">
                        <?= !empty($tracking_config['tiktok']['pixel_id']) ? $tracking_config['tiktok']['pixel_id'] : 'Belum dikonfigurasi' ?>
                    </div>
                    <?php if (isset($tracking_config['tiktok']['is_active']) && $tracking_config['tiktok']['is_active']): ?>
                        <span class="status-badge active">Aktif</span>
                    <?php else: ?>
                        <span class="status-badge inactive">Nonaktif</span>
                    <?php endif; ?>
                </div>
                
                <div class="tracking-platform-card">
                    <div class="tracking-platform-icon google">
                        <i class="fab fa-google"></i>
                    </div>
                    <div class="tracking-platform-name">Google Analytics</div>
                    <div class="tracking-platform-id">
                        <?= !empty($tracking_config['google']['measurement_id']) ? $tracking_config['google']['measurement_id'] : 'Belum dikonfigurasi' ?>
                    </div>
                    <?php if (isset($tracking_config['google']['is_active']) && $tracking_config['google']['is_active']): ?>
                        <span class="status-badge active">Aktif</span>
                    <?php else: ?>
                        <span class="status-badge inactive">Nonaktif</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- INFORMASI TAMBAHAN -->
        <div class="settings-card">
            <h3 class="settings-title">
                <i class="fas fa-lightbulb" style="color: var(--secondary);"></i> Cara Mendapatkan API Credentials
            </h3>
            
            <div class="tracking-grid">
                <div class="tracking-platform-card" style="text-align: left;">
                    <div style="font-weight: 700; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <span style="background: var(--secondary); color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;">1</span>
                        Meta Pixel
                    </div>
                    <p style="font-size: 13px; line-height: 1.6;">
                        <strong>Pixel ID:</strong> business.facebook.com → Events Manager<br>
                        <strong>Access Token:</strong> Settings → Conversions API
                    </p>
                </div>
                
                <div class="tracking-platform-card" style="text-align: left;">
                    <div style="font-weight: 700; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <span style="background: var(--secondary); color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;">2</span>
                        TikTok Pixel
                    </div>
                    <p style="font-size: 13px; line-height: 1.6;">
                        <strong>Pixel ID:</strong> ads.tiktok.com → Events → Pixel<br>
                        <strong>Access Token:</strong> Settings → Events API
                    </p>
                </div>
                
                <div class="tracking-platform-card" style="text-align: left;">
                    <div style="font-weight: 700; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <span style="background: var(--secondary); color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;">3</span>
                        Google Analytics
                    </div>
                    <p style="font-size: 13px; line-height: 1.6;">
                        <strong>Measurement ID:</strong> analytics.google.com → Admin → Data Streams<br>
                        <strong>API Secret:</strong> Measurement Protocol API secrets
                    </p>
                </div>
            </div>
        </div>
        
        <!-- TOMBOL SIMPAN -->
        <div class="text-center mt-4">
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> SIMPAN TRACKING PIXEL
            </button>
        </div>
        
    </form>
    
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Settings v11.0 | WhatsApp BalesOtomatis + Tracking Pixel</p>
    </div>
    
</div>

<script>
// ===== FUNGSI TOGGLE PASSWORD =====
function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// ===== CONFIRM BEFORE LEAVE =====
let formChanged = false;
const whatsappForm = document.querySelector('form[action*="whatsapp"]');
const trackingForm = document.querySelector('form[action*="tracking"]');

if (whatsappForm) {
    whatsappForm.addEventListener('input', function() { formChanged = true; });
    whatsappForm.addEventListener('change', function() { formChanged = true; });
}

if (trackingForm) {
    trackingForm.addEventListener('input', function() { formChanged = true; });
    trackingForm.addEventListener('change', function() { formChanged = true; });
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