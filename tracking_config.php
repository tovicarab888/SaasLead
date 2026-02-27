<?php
/**
 * TRACKING_CONFIG.PHP - Kelola Tracking Pixel per Developer
 * Version: 4.2.0 - UI SUPER KEREN (FIX: Modal Bisa Scroll)
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

// Hanya super admin yang bisa akses
if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Super Admin.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== PROSES FORM ==========
$success = '';
$error = '';

// TAMBAH/EDIT CONFIG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
    $id = (int)($_POST['id'] ?? 0);
    $developer_id = !empty($_POST['developer_id']) ? (int)$_POST['developer_id'] : null;
    $platform = $_POST['platform'] ?? '';
    $pixel_id = trim($_POST['pixel_id'] ?? '');
    $access_token = trim($_POST['access_token'] ?? '');
    $api_version = trim($_POST['api_version'] ?? '');
    $measurement_id = trim($_POST['measurement_id'] ?? '');
    $api_secret = trim($_POST['api_secret'] ?? '');
    $pixel_type = $_POST['pixel_type'] ?? 'all';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($platform)) {
        $error = "❌ Platform harus dipilih!";
    } elseif (($platform === 'meta' || $platform === 'tiktok') && empty($pixel_id)) {
        $error = "❌ Pixel ID wajib diisi!";
    } elseif ($platform === 'google' && empty($measurement_id)) {
        $error = "❌ Measurement ID wajib diisi!";
    } else {
        try {
            $check = $conn->prepare("
                SELECT id FROM tracking_config 
                WHERE developer_id " . ($developer_id === null ? "IS NULL" : "= ?") . " 
                AND platform = ? AND id != ?
            ");
            if ($developer_id === null) {
                $check->execute([$platform, $id]);
            } else {
                $check->execute([$developer_id, $platform, $id]);
            }
            
            if ($check->fetch()) {
                $error = "❌ Konfigurasi untuk developer dan platform ini sudah ada!";
            } else {
                if ($id > 0) {
                    $sql = "
                        UPDATE tracking_config SET
                            developer_id = ?,
                            platform = ?,
                            pixel_id = ?,
                            access_token = ?,
                            api_version = ?,
                            measurement_id = ?,
                            api_secret = ?,
                            pixel_type = ?,
                            is_active = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $developer_id,
                        $platform,
                        $pixel_id,
                        $access_token,
                        $api_version,
                        $measurement_id,
                        $api_secret,
                        $pixel_type,
                        $is_active,
                        $id
                    ]);
                    $success = "✅ Konfigurasi berhasil diupdate!";
                } else {
                    $sql = "
                        INSERT INTO tracking_config (
                            developer_id, platform, pixel_id, access_token,
                            api_version, measurement_id, api_secret, pixel_type, is_active, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $developer_id,
                        $platform,
                        $pixel_id,
                        $access_token,
                        $api_version,
                        $measurement_id,
                        $api_secret,
                        $pixel_type,
                        $is_active
                    ]);
                    $success = "✅ Konfigurasi berhasil ditambahkan!";
                }
                
                logSystem("Tracking config saved", [
                    'developer_id' => $developer_id,
                    'platform' => $platform,
                    'by' => $_SESSION['username']
                ], 'INFO', 'tracking.log');
            }
        } catch (Exception $e) {
            $error = "❌ Gagal: " . $e->getMessage();
            logSystem("Tracking config error", ['error' => $e->getMessage()], 'ERROR', 'tracking.log');
        }
    }
}

// HAPUS CONFIG
if (isset($_GET['delete']) && (int)$_GET['delete'] > 0) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM tracking_config WHERE id = ?");
        $stmt->execute([$id]);
        $success = "✅ Konfigurasi berhasil dihapus!";
        logSystem("Tracking config deleted", ['id' => $id], 'INFO', 'tracking.log');
    } catch (Exception $e) {
        $error = "❌ Gagal hapus: " . $e->getMessage();
    }
}

// TOGGLE STATUS
if (isset($_GET['toggle']) && (int)$_GET['toggle'] > 0) {
    $id = (int)$_GET['toggle'];
    $stmt = $conn->prepare("UPDATE tracking_config SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: tracking_config.php');
    exit();
}

// ========== AMBIL DATA ==========
$developers = $conn->query("
    SELECT id, nama_lengkap, nama_perusahaan 
    FROM users 
    WHERE role = 'developer' AND is_active = 1 
    ORDER BY nama_lengkap
")->fetchAll();

$configs = $conn->query("
    SELECT 
        tc.*,
        u.nama_lengkap as developer_name,
        u.nama_perusahaan
    FROM tracking_config tc
    LEFT JOIN users u ON tc.developer_id = u.id
    ORDER BY 
        CASE WHEN tc.developer_id IS NULL THEN 0 ELSE 1 END,
        tc.developer_id,
        tc.platform
")->fetchAll();

$grouped_configs = [];
foreach ($configs as $cfg) {
    $dev_key = $cfg['developer_id'] ?? 'global';
    if (!isset($grouped_configs[$dev_key])) {
        $grouped_configs[$dev_key] = [
            'developer_id' => $cfg['developer_id'],
            'developer_name' => $cfg['developer_name'] ?? 'GLOBAL (Default)',
            'perusahaan' => $cfg['nama_perusahaan'] ?? '',
            'configs' => []
        ];
    }
    $grouped_configs[$dev_key]['configs'][$cfg['platform']] = $cfg;
}

// ========== SET VARIABLES ==========
$page_title = 'Tracking Pixel';
$page_subtitle = 'Kelola Konfigurasi Tracking';
$page_icon = 'fas fa-chart-line';

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
    --meta: #1877F2;
    --tiktok: #000000;
    --google: #EA4335;
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
    margin-bottom: 24px;
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

/* ===== DEVELOPER SECTION ===== */
.developer-section {
    background: white;
    border-radius: 24px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
}

.developer-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--primary-soft);
}

.developer-avatar {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
    flex-shrink: 0;
}

.developer-info h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    margin: 0 0 4px 0;
}

.developer-info p {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0;
}

/* ===== CONFIG GRID ===== */
.config-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

@media (max-width: 1024px) {
    .config-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .config-grid {
        grid-template-columns: 1fr;
    }
}

/* ===== CONFIG CARD ===== */
.config-card {
    background: var(--primary-soft);
    border-radius: 18px;
    padding: 16px;
    border: 1px solid var(--border);
    transition: all 0.2s;
}

.config-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.05);
}

.config-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.config-platform {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.config-platform.meta {
    background: var(--meta);
    color: white;
}

.config-platform.tiktok {
    background: var(--tiktok);
    color: white;
}

.config-platform.google {
    background: var(--google);
    color: white;
}

.config-title {
    flex: 1;
}

.config-title h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--primary);
    margin: 0 0 4px 0;
}

.platform-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 600;
    color: white;
}

.platform-badge.meta {
    background: var(--meta);
}

.platform-badge.tiktok {
    background: var(--tiktok);
}

.platform-badge.google {
    background: var(--google);
}

/* ===== TOGGLE SWITCH ===== */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
    cursor: pointer;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--border);
    transition: .3s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background: linear-gradient(135deg, var(--success), #40BEB0);
}

input:checked + .toggle-slider:before {
    transform: translateX(20px);
}

/* ===== CONFIG DETAILS ===== */
.config-details {
    background: white;
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 16px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    padding: 6px 0;
}

.detail-item:not(:last-child) {
    border-bottom: 1px dashed var(--border);
}

.detail-label {
    color: var(--text-muted);
    font-weight: 500;
    min-width: 70px;
}

.detail-label i {
    color: var(--secondary);
    width: 14px;
    margin-right: 4px;
}

.detail-item code {
    background: var(--primary-soft);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    color: var(--primary);
    font-family: monospace;
    word-break: break-all;
}

/* ===== EMPTY CONFIG ===== */
.empty-config {
    background: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    color: var(--text-muted);
    font-size: 12px;
    margin-bottom: 16px;
}

.empty-config i {
    font-size: 24px;
    color: #E0DAD3;
    margin-bottom: 8px;
    display: block;
}

/* ===== CONFIG ACTIONS ===== */
.config-actions {
    display: flex;
    gap: 8px;
}

.btn-icon-text {
    flex: 1;
    padding: 8px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    cursor: pointer;
    border: 1px solid var(--border);
    background: white;
    color: var(--text);
    text-decoration: none;
    transition: all 0.2s;
}

.btn-icon-text i {
    font-size: 12px;
}

.btn-icon-text:hover {
    background: var(--primary-soft);
}

.btn-icon-text.btn-danger {
    background: #ffeeed;
    color: var(--danger);
    border-color: var(--danger);
}

.btn-icon-text.btn-danger:hover {
    background: var(--danger);
    color: white;
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

/* ===== FOOTER STATS ===== */
.footer-stats {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin: 30px 0 20px;
    flex-wrap: wrap;
}

.footer-stat {
    display: flex;
    align-items: center;
    gap: 8px;
    background: white;
    padding: 10px 20px;
    border-radius: 40px;
    border: 1px solid var(--border);
}

.footer-stat i {
    color: var(--secondary);
    font-size: 16px;
}

.footer-stat span {
    font-size: 13px;
    font-weight: 600;
    color: var(--primary);
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
        border-radius: 16px;
    }
    
    .welcome-text h2 {
        font-size: 22px;
    }
    
    .action-bar {
        display: flex;
        justify-content: flex-start;
    }
    
    .btn-add {
        width: auto;
        padding: 14px 32px;
    }
    
    .config-grid {
        gap: 20px;
    }
    
    .config-card {
        padding: 18px;
    }
    
    .config-platform {
        width: 44px;
        height: 44px;
        font-size: 22px;
    }
    
    .config-title h4 {
        font-size: 15px;
    }
    
    .detail-item {
        font-size: 12px;
    }
    
    .detail-item code {
        font-size: 11px;
    }
    
    .btn-icon-text {
        font-size: 12px;
        padding: 10px 12px;
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
    
    .footer-stats {
        gap: 40px;
    }
    
    .footer-stat {
        padding: 12px 24px;
    }
    
    .footer-stat i {
        font-size: 18px;
    }
    
    .footer-stat span {
        font-size: 14px;
    }
}

/* ===== MODAL FIX - PASTIKAN BISA SCROLL ===== */
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
    padding: 20px;
}

.modal.show {
    display: flex !important;
}

.modal-content {
    background: white;
    border-radius: 28px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 30px 60px rgba(0,0,0,0.3);
    animation: modalFade 0.3s ease;
    overflow: hidden;
}

@keyframes modalFade {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 2px solid var(--primary-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, white, #fafafa);
    flex-shrink: 0;
}

.modal-header h2 {
    color: var(--primary);
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h2 i {
    color: var(--secondary);
    font-size: 22px;
}

.modal-close {
    width: 44px;
    height: 44px;
    background: var(--primary-soft);
    border: none;
    border-radius: 12px;
    color: var(--secondary);
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.modal-close:hover {
    background: var(--secondary);
    color: white;
    transform: rotate(90deg);
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
    background: white;
    max-height: 60vh;
}

.modal-footer {
    padding: 16px 24px 24px;
    border-top: 2px solid var(--primary-soft);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    background: #fafafa;
    flex-shrink: 0;
}

.modal-footer button {
    flex: 1;
    min-height: 48px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
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
    width: 20px;
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
    box-shadow: 0 0 0 4px rgba(214,79,60,0.1);
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--primary-soft);
    border-radius: 14px;
}

.checkbox-group input[type="checkbox"] {
    width: 22px;
    height: 22px;
    accent-color: var(--secondary);
    cursor: pointer;
}

.checkbox-group label {
    margin: 0;
    font-weight: 600;
    color: var(--primary);
    cursor: pointer;
}

/* ===== PLATFORM FIELDS ===== */
.platform-fields {
    background: var(--primary-soft);
    padding: 16px;
    border-radius: 14px;
    margin-bottom: 20px;
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
    box-shadow: 0 8px 20px rgba(27,74,60,0.2);
    transition: all 0.2s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(27,74,60,0.3);
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
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: var(--text-muted);
    color: white;
    transform: translateY(-2px);
}

.btn-secondary:active {
    transform: scale(0.98);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #FF6B4A);
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(214,79,60,0.2);
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(214,79,60,0.3);
}

.btn-danger:active {
    transform: scale(0.98);
}

/* ===== DELETE MODAL PREMIUM ===== */
.delete-icon-wrapper {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, rgba(214,79,60,0.1), rgba(214,79,60,0.2));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    border: 3px solid var(--danger);
}

.delete-icon-wrapper i {
    font-size: 40px;
    color: var(--danger);
}

.delete-title {
    font-size: 24px;
    font-weight: 800;
    color: var(--danger);
    margin-bottom: 8px;
    text-align: center;
}

.delete-subtitle {
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 24px;
    text-align: center;
}

.delete-name-card {
    background: var(--primary-soft);
    padding: 16px;
    border-radius: 16px;
    font-weight: 700;
    font-size: 16px;
    color: var(--primary);
    margin-bottom: 20px;
    border: 2px dashed var(--secondary);
    text-align: center;
    word-break: break-word;
}

.delete-warning-card {
    background: #fff3cd;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 24px;
    border-left: 4px solid #ffc107;
    text-align: left;
}

.delete-warning-card i {
    font-size: 24px;
    color: #ffc107;
    flex-shrink: 0;
}

.delete-warning-card p {
    margin-bottom: 0;
    font-size: 13px;
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
    
    <!-- ACTION BUTTON -->
    <div class="action-bar">
        <button onclick="openAddModal()" class="btn-add">
            <i class="fas fa-plus-circle"></i> Tambah Konfigurasi
        </button>
    </div>
    
    <!-- DAFTAR KONFIGURASI -->
    <?php foreach ($grouped_configs as $group): ?>
    <div class="developer-section">
        <div class="developer-header">
            <div class="developer-avatar">
                <i class="fas fa-<?= $group['developer_id'] === null ? 'globe' : 'building' ?>"></i>
            </div>
            <div class="developer-info">
                <h3><?= htmlspecialchars($group['developer_name']) ?></h3>
                <?php if (!empty($group['perusahaan'])): ?>
                <p><?= htmlspecialchars($group['perusahaan']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="config-grid">
            <!-- META -->
            <?php 
            $meta = $group['configs']['meta'] ?? null;
            $meta_id = $meta['id'] ?? 0;
            ?>
            <div class="config-card">
                <div class="config-card-header">
                    <div class="config-platform meta">
                        <i class="fab fa-facebook-f"></i>
                    </div>
                    <div class="config-title">
                        <h4>Meta Pixel</h4>
                        <span class="platform-badge meta">Facebook</span>
                    </div>
                    <?php if ($meta_id > 0): ?>
                    <label class="toggle-switch">
                        <input type="checkbox" onchange="toggleStatus(<?= $meta_id ?>)" <?= $meta['is_active'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <?php endif; ?>
                </div>
                
                <?php if ($meta): ?>
                <div class="config-details">
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-id-card"></i> Pixel ID:</span>
                        <code><?= htmlspecialchars($meta['pixel_id'] ?? '-') ?></code>
                    </div>
                    <?php if (!empty($meta['access_token'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-key"></i> Token:</span>
                        <code>••••••••••••••••</code>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-code-branch"></i> Version:</span>
                        <code><?= htmlspecialchars($meta['api_version'] ?? 'v19.0') ?></code>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-config">
                    <i class="fas fa-times-circle"></i>
                    <span>Belum dikonfigurasi</span>
                </div>
                <?php endif; ?>
                
                <div class="config-actions">
                    <button onclick="editConfig(<?= htmlspecialchars(json_encode($meta)) ?>)" class="btn-icon-text">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <?php if ($meta): ?>
                    <button onclick="openDeleteModal(<?= $meta['id'] ?>, 'Meta Pixel')" class="btn-icon-text btn-danger">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- TIKTOK -->
            <?php 
            $tiktok = $group['configs']['tiktok'] ?? null;
            $tiktok_id = $tiktok['id'] ?? 0;
            ?>
            <div class="config-card">
                <div class="config-card-header">
                    <div class="config-platform tiktok">
                        <i class="fab fa-tiktok"></i>
                    </div>
                    <div class="config-title">
                        <h4>TikTok Pixel</h4>
                        <span class="platform-badge tiktok">TikTok</span>
                    </div>
                    <?php if ($tiktok_id > 0): ?>
                    <label class="toggle-switch">
                        <input type="checkbox" onchange="toggleStatus(<?= $tiktok_id ?>)" <?= $tiktok['is_active'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <?php endif; ?>
                </div>
                
                <?php if ($tiktok): ?>
                <div class="config-details">
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-id-card"></i> Pixel ID:</span>
                        <code><?= htmlspecialchars($tiktok['pixel_id'] ?? '-') ?></code>
                    </div>
                    <?php if (!empty($tiktok['access_token'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-key"></i> Token:</span>
                        <code>••••••••••••••••</code>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-code-branch"></i> Version:</span>
                        <code><?= htmlspecialchars($tiktok['api_version'] ?? 'v1.3') ?></code>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-config">
                    <i class="fas fa-times-circle"></i>
                    <span>Belum dikonfigurasi</span>
                </div>
                <?php endif; ?>
                
                <div class="config-actions">
                    <button onclick="editConfig(<?= htmlspecialchars(json_encode($tiktok)) ?>)" class="btn-icon-text">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <?php if ($tiktok): ?>
                    <button onclick="openDeleteModal(<?= $tiktok['id'] ?>, 'TikTok Pixel')" class="btn-icon-text btn-danger">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- GOOGLE -->
            <?php 
            $google = $group['configs']['google'] ?? null;
            $google_id = $google['id'] ?? 0;
            ?>
            <div class="config-card">
                <div class="config-card-header">
                    <div class="config-platform google">
                        <i class="fab fa-google"></i>
                    </div>
                    <div class="config-title">
                        <h4>Google Analytics</h4>
                        <span class="platform-badge google">GA4</span>
                    </div>
                    <?php if ($google_id > 0): ?>
                    <label class="toggle-switch">
                        <input type="checkbox" onchange="toggleStatus(<?= $google_id ?>)" <?= $google['is_active'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <?php endif; ?>
                </div>
                
                <?php if ($google): ?>
                <div class="config-details">
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-id-card"></i> Measurement ID:</span>
                        <code><?= htmlspecialchars($google['measurement_id'] ?? '-') ?></code>
                    </div>
                    <?php if (!empty($google['api_secret'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-key"></i> Secret:</span>
                        <code>••••••••••••••••</code>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="empty-config">
                    <i class="fas fa-times-circle"></i>
                    <span>Belum dikonfigurasi</span>
                </div>
                <?php endif; ?>
                
                <div class="config-actions">
                    <button onclick="editConfig(<?= htmlspecialchars(json_encode($google)) ?>)" class="btn-icon-text">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <?php if ($google): ?>
                    <button onclick="openDeleteModal(<?= $google['id'] ?>, 'Google Analytics')" class="btn-icon-text btn-danger">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- INFO CARD -->
    <div class="info-card">
        <div class="info-icon">
            <i class="fas fa-info-circle"></i>
        </div>
        <div class="info-text">
            <strong>Informasi Tracking Pixel</strong>
            <p>Setiap developer dapat memiliki konfigurasi tracking sendiri. Jika tidak ada konfigurasi khusus, sistem akan menggunakan konfigurasi GLOBAL sebagai fallback.</p>
        </div>
    </div>
    
    <!-- FOOTER STATS -->
    <div class="footer-stats">
        <div class="footer-stat">
            <i class="fas fa-chart-line"></i>
            <span>Total Konfigurasi: <?= count($configs) ?></span>
        </div>
        <div class="footer-stat">
            <i class="fas fa-building"></i>
            <span>Developer: <?= count($developers) ?></span>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Tracking Config v4.2</p>
    </div>
    
</div>

<!-- MODAL TAMBAH/EDIT CONFIG -->
<div class="modal" id="configModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-plus-circle"></i> Tambah Konfigurasi</h2>
            <button class="modal-close" onclick="closeModal('configModal')">&times;</button>
        </div>
        
        <form method="POST" id="configForm">
            <input type="hidden" name="action" value="save_config">
            <input type="hidden" name="id" id="config_id" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Developer</label>
                    <select name="developer_id" id="developer_id" class="form-control">
                        <option value="">-- GLOBAL (Default) --</option>
                        <?php foreach ($developers as $dev): ?>
                        <option value="<?= $dev['id'] ?>">
                            <?= htmlspecialchars($dev['nama_lengkap']) ?> 
                            <?= !empty($dev['nama_perusahaan']) ? '- ' . $dev['nama_perusahaan'] : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-muted); display: block; margin-top: 6px;">Kosongkan untuk konfigurasi global</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Platform *</label>
                    <select name="platform" id="platform" class="form-control" required onchange="togglePlatformFields()">
                        <option value="">Pilih Platform</option>
                        <option value="meta">Meta Pixel (Facebook)</option>
                        <option value="tiktok">TikTok Pixel</option>
                        <option value="google">Google Analytics 4</option>
                    </select>
                </div>
                
                <!-- Meta Fields -->
                <div id="meta_fields" class="platform-fields" style="display: none;">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Pixel ID *</label>
                        <input type="text" name="pixel_id" id="meta_pixel_id" class="form-control" placeholder="Contoh: 123456789012345">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Access Token</label>
                        <input type="password" name="access_token" id="meta_access_token" class="form-control" placeholder="Token untuk CAPI">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-code-branch"></i> API Version</label>
                        <input type="text" name="api_version" id="meta_api_version" class="form-control" placeholder="v19.0" value="v19.0">
                    </div>
                </div>
                
                <!-- TikTok Fields -->
                <div id="tiktok_fields" class="platform-fields" style="display: none;">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Pixel ID *</label>
                        <input type="text" name="pixel_id" id="tiktok_pixel_id" class="form-control" placeholder="Contoh: D3L405BC77U8AFC9O0RG">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Access Token</label>
                        <input type="password" name="access_token" id="tiktok_access_token" class="form-control" placeholder="Token untuk Events API">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-code-branch"></i> API Version</label>
                        <input type="text" name="api_version" id="tiktok_api_version" class="form-control" placeholder="v1.3" value="v1.3">
                    </div>
                </div>
                
                <!-- Google Fields -->
                <div id="google_fields" class="platform-fields" style="display: none;">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Measurement ID *</label>
                        <input type="text" name="measurement_id" id="google_measurement_id" class="form-control" placeholder="Contoh: G-XXXXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> API Secret</label>
                        <input type="password" name="api_secret" id="google_api_secret" class="form-control" placeholder="Secret dari Google Analytics">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-filter"></i> Pixel Type</label>
                    <select name="pixel_type" id="pixel_type" class="form-control">
                        <option value="all">All (Semua Event)</option>
                        <option value="meta">Meta Only</option>
                        <option value="tiktok">TikTok Only</option>
                        <option value="google">Google Only</option>
                    </select>
                </div>
                
                <div class="checkbox-group" style="margin-top: 20px;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                    <label for="is_active">Aktifkan tracking ini</label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('configModal')">Batal</button>
                <button type="submit" class="btn-primary">Simpan Konfigurasi</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE PREMIUM -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Hapus Konfigurasi</h2>
            <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div class="delete-icon-wrapper">
                <i class="fas fa-trash-alt"></i>
            </div>
            <h3 class="delete-title">Hapus Konfigurasi?</h3>
            <p class="delete-subtitle">Tindakan ini tidak dapat dibatalkan</p>
            <div class="delete-name-card" id="deleteItemName"></div>
            <div class="delete-warning-card">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Peringatan!</strong>
                    <p>Semua data tracking terkait akan kehilangan konfigurasi ini.</p>
                </div>
            </div>
            <input type="hidden" id="deleteItemId">
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('deleteModal')">Batal</button>
            <button class="btn-danger" onclick="processDelete()">Ya, Hapus</button>
        </div>
    </div>
</div>

<script>
// Fungsi untuk membuka modal tambah
function openAddModal() {
    // Reset form
    document.getElementById('configForm').reset();
    document.getElementById('config_id').value = '';
    
    // Sembunyikan semua fields platform
    document.querySelectorAll('.platform-fields').forEach(el => {
        el.style.display = 'none';
        el.querySelectorAll('input').forEach(input => {
            input.disabled = true;
            input.required = false;
        });
    });
    
    // Set title
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Tambah Konfigurasi';
    
    // Set default values
    document.getElementById('developer_id').value = '';
    document.getElementById('platform').value = '';
    document.getElementById('pixel_type').value = 'all';
    document.getElementById('is_active').checked = true;
    
    // Tampilkan modal
    openModal('configModal');
}

// Fungsi untuk membuka modal edit
function editConfig(config) {
    if (!config) return;
    
    console.log('Editing config:', config);
    
    // Reset form
    document.getElementById('configForm').reset();
    
    // Set title
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Konfigurasi';
    
    // Set values
    document.getElementById('config_id').value = config.id || '';
    document.getElementById('developer_id').value = config.developer_id || '';
    document.getElementById('platform').value = config.platform || '';
    document.getElementById('pixel_type').value = config.pixel_type || 'all';
    document.getElementById('is_active').checked = config.is_active == 1;
    
    // Sembunyikan semua fields dulu
    document.querySelectorAll('.platform-fields').forEach(el => {
        el.style.display = 'none';
        el.querySelectorAll('input').forEach(input => {
            input.disabled = true;
            input.required = false;
        });
    });
    
    // Tampilkan fields sesuai platform
    if (config.platform) {
        // Set platform dulu
        document.getElementById('platform').value = config.platform;
        
        // Panggil togglePlatformFields untuk menampilkan fields yang sesuai
        togglePlatformFields();
        
        // Isi data sesuai platform
        if (config.platform === 'meta') {
            document.getElementById('meta_pixel_id').value = config.pixel_id || '';
            document.getElementById('meta_access_token').value = config.access_token || '';
            document.getElementById('meta_api_version').value = config.api_version || 'v19.0';
        } else if (config.platform === 'tiktok') {
            document.getElementById('tiktok_pixel_id').value = config.pixel_id || '';
            document.getElementById('tiktok_access_token').value = config.access_token || '';
            document.getElementById('tiktok_api_version').value = config.api_version || 'v1.3';
        } else if (config.platform === 'google') {
            document.getElementById('google_measurement_id').value = config.measurement_id || '';
            document.getElementById('google_api_secret').value = config.api_secret || '';
        }
    }
    
    // Tampilkan modal
    openModal('configModal');
}

// Fungsi untuk membuka modal delete
function openDeleteModal(id, name) {
    document.getElementById('deleteItemId').value = id;
    document.getElementById('deleteItemName').textContent = name;
    openModal('deleteModal');
}

// Fungsi untuk proses delete
function processDelete() {
    const id = document.getElementById('deleteItemId').value;
    if (id) {
        window.location.href = '?delete=' + id;
    }
}

// Fungsi untuk toggle platform fields
function togglePlatformFields() {
    const platform = document.getElementById('platform').value;
    
    // Sembunyikan semua fields
    document.querySelectorAll('.platform-fields').forEach(el => {
        el.style.display = 'none';
        el.querySelectorAll('input').forEach(input => {
            input.disabled = true;
            input.required = false;
        });
    });
    
    // Tampilkan fields sesuai platform
    if (platform === 'meta') {
        document.getElementById('meta_fields').style.display = 'block';
        document.querySelectorAll('#meta_fields input').forEach(input => {
            input.disabled = false;
            if (input.id === 'meta_pixel_id') input.required = true;
        });
    } else if (platform === 'tiktok') {
        document.getElementById('tiktok_fields').style.display = 'block';
        document.querySelectorAll('#tiktok_fields input').forEach(input => {
            input.disabled = false;
            if (input.id === 'tiktok_pixel_id') input.required = true;
        });
    } else if (platform === 'google') {
        document.getElementById('google_fields').style.display = 'block';
        document.querySelectorAll('#google_fields input').forEach(input => {
            input.disabled = false;
            if (input.id === 'google_measurement_id') input.required = true;
        });
    }
}

// Fungsi untuk toggle status
function toggleStatus(id) {
    if (id <= 0) return;
    window.location.href = '?toggle=' + id;
}

// Fungsi untuk membuka modal
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

// Fungsi untuk menutup modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = '';
}

// Update datetime
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

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('configModal');
        closeModal('deleteModal');
    }
});

// Close modal when clicking overlay
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});
</script>

<?php include 'includes/footer.php'; ?>