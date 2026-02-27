<?php
/**
 * DEVELOPER_SEO.PHP - CMS SEO SUPER KEREN MOBILE FIRST
 * Version: 6.0.0 - FIXED: Developer ID validation
 */

// ===== DEBUG MODE - HAPUS NANTI =====
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// ========== AMBIL PARAMETER DENGAN DEBUG ==========
$developer_id = 0;

// Cek dari GET
if (isset($_GET['developer_id'])) {
    $developer_id = (int)$_GET['developer_id'];
} 
// Cek dari POST
elseif (isset($_POST['developer_id'])) {
    $developer_id = (int)$_POST['developer_id'];
}
// Cek dari REQUEST
elseif (isset($_REQUEST['developer_id'])) {
    $developer_id = (int)$_REQUEST['developer_id'];
}

// DEBUG: Tampilkan di source HTML
echo "<!-- DEBUG: GET = " . print_r($_GET, true) . " -->";
echo "<!-- DEBUG: POST = " . print_r($_POST, true) . " -->";
echo "<!-- DEBUG: developer_id = $developer_id -->";

// Validasi
if ($developer_id <= 0) {
    // Redirect ke halaman pilih developer dengan pesan error
    header('Location: select_developer_seo.php?error=' . urlencode('Developer ID tidak ditemukan. Silakan pilih developer.'));
    exit();
}

// ========== AMBIL DATA DEVELOPER ==========
$stmt = $conn->prepare("
    SELECT id, nama_lengkap, nama_perusahaan, kota, alamat_perusahaan, 
           telepon_perusahaan, website_perusahaan, logo_perusahaan, email_perusahaan,
           folder_name
    FROM users 
    WHERE id = ? AND role = 'developer' AND is_active = 1
");
$stmt->execute([$developer_id]);
$developer = $stmt->fetch();

if (!$developer) {
    die("Developer tidak ditemukan untuk ID: $developer_id");
}

// ========== DETEKSI FOLDER UNTUK PREVIEW ==========
$folder_map = [
    3 => 'kertamulya',
    4 => 'kertayasa',
    5 => 'ciperna',
    6 => 'windusari'
];

if (!empty($developer['folder_name'])) {
    $folder_name = $developer['folder_name'];
} else {
    $folder_name = isset($folder_map[$developer_id]) ? $folder_map[$developer_id] : '';
}

$root_path = dirname(__DIR__);
$folder_exists = !empty($folder_name) && is_dir($root_path . '/' . $folder_name);

if ($folder_exists) {
    $preview_url = '/' . $folder_name . '/';
} else {
    $preview_url = '/?dev_id=' . $developer_id;
}

// ========== AMBIL DATA SEO ==========
$seo = getDeveloperSEO($developer_id);

// ========== PROSES SIMPAN ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'save_seo') {
        
        // Konversi robots meta menjadi dua kolom terpisah
        $robots = explode(',', $_POST['robots_meta'] ?? 'index, follow');
        $robots_index = strpos(implode('', $robots), 'noindex') !== false ? 0 : 1;
        $robots_follow = strpos(implode('', $robots), 'nofollow') !== false ? 0 : 1;
        
        $data = [
            'seo_title' => trim($_POST['seo_title'] ?? ''),
            'seo_description' => trim($_POST['seo_description'] ?? ''),
            'seo_keywords' => trim($_POST['seo_keywords'] ?? ''),
            'canonical_url' => trim($_POST['canonical_url'] ?? ''),
            'h1_tag' => trim($_POST['h1_tag'] ?? ''),
            
            'meta_robots_index' => $robots_index,
            'meta_robots_follow' => $robots_follow,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
            'seo_priority' => (int)($_POST['seo_priority'] ?? 0),
            
            'og_type' => $_POST['og_type'] ?? 'website',
            'og_title' => trim($_POST['og_title'] ?? ''),
            'og_description' => trim($_POST['og_description'] ?? ''),
            'og_image' => trim($_POST['og_image'] ?? ''),
            'og_image_width' => (int)($_POST['og_image_width'] ?? 1200),
            'og_image_height' => (int)($_POST['og_image_height'] ?? 630),
            'og_url' => trim($_POST['og_url'] ?? ''),
            
            'twitter_title' => trim($_POST['twitter_title'] ?? ''),
            'twitter_description' => trim($_POST['twitter_description'] ?? ''),
            'twitter_image' => trim($_POST['twitter_image'] ?? ''),
            'twitter_image_alt' => trim($_POST['twitter_image_alt'] ?? ''),
            'twitter_card_type' => $_POST['twitter_card_type'] ?? 'summary_large_image',
            
            'schema_json' => trim($_POST['schema_json'] ?? ''),
            'faq_json' => trim($_POST['faq_json'] ?? ''),
            'breadcrumb_json' => trim($_POST['breadcrumb_json'] ?? '')
        ];
        
        try {
            // CEK STRUKTUR TABEL TERLEBIH DAHULU
            $columns = $conn->query("SHOW COLUMNS FROM developer_seo")->fetchAll(PDO::FETCH_COLUMN);
            
            // Cek apakah sudah ada
            $check = $conn->prepare("SELECT id FROM developer_seo WHERE developer_id = ?");
            $check->execute([$developer_id]);
            
            if ($check->fetch()) {
                // UPDATE - Sesuaikan dengan kolom yang ada
                $update_fields = [];
                $update_values = [];
                
                $field_map = [
                    'seo_title' => $data['seo_title'],
                    'seo_description' => $data['seo_description'],
                    'seo_keywords' => $data['seo_keywords'],
                    'canonical_url' => $data['canonical_url'],
                    'h1_tag' => $data['h1_tag'],
                    'meta_robots_index' => $data['meta_robots_index'],
                    'meta_robots_follow' => $data['meta_robots_follow'],
                    'is_active' => $data['is_active'],
                    'is_default' => $data['is_default'],
                    'seo_priority' => $data['seo_priority'],
                    'og_type' => $data['og_type'],
                    'og_title' => $data['og_title'],
                    'og_description' => $data['og_description'],
                    'og_image' => $data['og_image'],
                    'og_image_width' => $data['og_image_width'],
                    'og_image_height' => $data['og_image_height'],
                    'og_url' => $data['og_url'],
                    'twitter_title' => $data['twitter_title'],
                    'twitter_description' => $data['twitter_description'],
                    'twitter_image' => $data['twitter_image'],
                    'twitter_image_alt' => $data['twitter_image_alt'],
                    'twitter_card_type' => $data['twitter_card_type'],
                    'schema_json' => $data['schema_json'],
                    'faq_json' => $data['faq_json'],
                    'breadcrumb_json' => $data['breadcrumb_json']
                ];
                
                foreach ($field_map as $field => $value) {
                    if (in_array($field, $columns)) {
                        $update_fields[] = "$field = ?";
                        $update_values[] = $value;
                    }
                }
                
                $update_values[] = $developer_id;
                $update_sql = "UPDATE developer_seo SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE developer_id = ?";
                
                $stmt = $conn->prepare($update_sql);
                $stmt->execute($update_values);
                
            } else {
                // INSERT - Sesuaikan dengan kolom yang ada
                $insert_fields = ['developer_id'];
                $insert_values = [$developer_id];
                
                $field_map = [
                    'seo_title' => $data['seo_title'],
                    'seo_description' => $data['seo_description'],
                    'seo_keywords' => $data['seo_keywords'],
                    'canonical_url' => $data['canonical_url'],
                    'h1_tag' => $data['h1_tag'],
                    'meta_robots_index' => $data['meta_robots_index'],
                    'meta_robots_follow' => $data['meta_robots_follow'],
                    'is_active' => $data['is_active'],
                    'is_default' => $data['is_default'],
                    'seo_priority' => $data['seo_priority'],
                    'og_type' => $data['og_type'],
                    'og_title' => $data['og_title'],
                    'og_description' => $data['og_description'],
                    'og_image' => $data['og_image'],
                    'og_image_width' => $data['og_image_width'],
                    'og_image_height' => $data['og_image_height'],
                    'og_url' => $data['og_url'],
                    'twitter_title' => $data['twitter_title'],
                    'twitter_description' => $data['twitter_description'],
                    'twitter_image' => $data['twitter_image'],
                    'twitter_image_alt' => $data['twitter_image_alt'],
                    'twitter_card_type' => $data['twitter_card_type'],
                    'schema_json' => $data['schema_json'],
                    'faq_json' => $data['faq_json'],
                    'breadcrumb_json' => $data['breadcrumb_json']
                ];
                
                foreach ($field_map as $field => $value) {
                    if (in_array($field, $columns)) {
                        $insert_fields[] = $field;
                        $insert_values[] = $value;
                    }
                }
                
                $insert_fields[] = 'created_at';
                $insert_fields[] = 'updated_at';
                $insert_values[] = date('Y-m-d H:i:s');
                $insert_values[] = date('Y-m-d H:i:s');
                
                $placeholders = implode(', ', array_fill(0, count($insert_fields), '?'));
                $insert_sql = "INSERT INTO developer_seo (" . implode(', ', $insert_fields) . ") VALUES ($placeholders)";
                
                $stmt = $conn->prepare($insert_sql);
                $stmt->execute($insert_values);
            }
            
            $success = "✅ Data SEO berhasil disimpan!";
            $seo = getDeveloperSEO($developer_id);
            logSystem("SEO updated for developer $developer_id", ['by' => $_SESSION['username']], 'INFO', 'seo.log');
            
        } catch (Exception $e) {
            $error = "❌ Gagal menyimpan: " . $e->getMessage();
        }
    }
    
    elseif ($_POST['action'] === 'reset_default') {
        $conn->prepare("DELETE FROM developer_seo WHERE developer_id = ?")->execute([$developer_id]);
        $seo = generateDefaultSEO($developer_id);
        $success = "✅ SEO direset ke default!";
    }
    
    elseif ($_POST['action'] === 'generate_schema') {
        $dev_data = [
            'id' => $developer['id'],
            'nama_perusahaan' => $developer['nama_perusahaan'],
            'nama_lengkap' => $developer['nama_lengkap'],
            'alamat_perusahaan' => $developer['alamat_perusahaan'],
            'kota' => $developer['kota'],
            'telepon_perusahaan' => $developer['telepon_perusahaan'],
            'email_perusahaan' => $developer['email_perusahaan'],
            'website_perusahaan' => $developer['website_perusahaan'],
            'logo_perusahaan' => $developer['logo_perusahaan']
        ];
        
        $schema = generateDeveloperSchema($dev_data, $seo ?: []);
        $conn->prepare("UPDATE developer_seo SET schema_json = ? WHERE developer_id = ?")
             ->execute([$schema, $developer_id]);
        
        $seo['schema_json'] = $schema;
        $success = "✅ Schema berhasil digenerate!";
    }
}

// ========== SET VARIABLES ==========
$page_title = 'SEO Developer';
$page_subtitle = $developer['nama_perusahaan'] ?: $developer['nama_lengkap'];
$page_icon = 'fas fa-search';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- ========== TAMBAHKAN DEBUG VISUAL ========== -->
<div style="background: #f0f0f0; padding: 10px; margin-bottom: 10px; border-radius: 5px; font-size: 12px; display: none;">
    <strong>DEBUG INFO:</strong><br>
    Developer ID: <?= $developer_id ?><br>
    GET: <?= htmlspecialchars(print_r($_GET, true)) ?><br>
    POST: <?= htmlspecialchars(print_r($_POST, true)) ?><br>
    Folder: <?= $folder_name ?: '-' ?><br>
    Preview URL: <?= $preview_url ?>
</div>

<!-- ===== CSS SUPER KEREN (SINKRON DENGAN ADMIN.CSS) ===== -->
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
    --danger: #D64F3C;
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-xl: 24px;
    --shadow-sm: 0 4px 12px rgba(0,0,0,0.05);
    --shadow-md: 0 8px 24px rgba(0,0,0,0.08);
    --shadow-lg: 0 16px 32px rgba(0,0,0,0.1);
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
    box-shadow: var(--shadow-sm);
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
    background: rgba(214,79,60,0.1);
    color: var(--secondary);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.welcome-text h2 {
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
}

.welcome-text h2 span {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
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

.developer-card {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: var(--radius-lg);
    padding: 20px;
    margin-bottom: 20px;
    color: white;
    display: flex;
    flex-direction: column;
    gap: 16px;
    box-shadow: var(--shadow-md);
}

.developer-avatar {
    width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.2);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    flex-shrink: 0;
}

.developer-name {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 4px;
}

.developer-details {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 13px;
    opacity: 0.9;
}

.developer-details span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.developer-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-outline-light {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
    padding: 8px 14px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    backdrop-filter: blur(5px);
    transition: all 0.2s;
}

.btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

.tabs-container {
    background: white;
    border-radius: var(--radius-lg);
    overflow: hidden;
    border: 1px solid var(--border);
}

.tabs-header {
    display: flex;
    overflow-x: auto;
    background: #fafafa;
    border-bottom: 2px solid var(--primary-soft);
    padding: 4px 4px 0;
}

.tabs-header::-webkit-scrollbar {
    display: none;
}

.tab-btn {
    padding: 12px 14px;
    background: none;
    border: none;
    font-weight: 600;
    font-size: 12px;
    color: var(--text-light);
    cursor: pointer;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
    border-bottom: 3px solid transparent;
    flex: 1;
    min-width: fit-content;
    transition: all 0.2s;
}

.tab-btn i {
    font-size: 13px;
}

.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--secondary);
    background: rgba(214,79,60,0.03);
}

.tab-content {
    padding: 20px;
}

.two-column {
    display: block;
}

.form-group {
    margin-bottom: 18px;
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

.form-control, .form-select, textarea.form-control {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    background: white;
    transition: all 0.2s;
}

.form-control:focus, .form-select:focus, textarea.form-control:focus {
    border-color: var(--secondary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(214,79,60,0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.form-hint {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    color: var(--text-muted);
}

.counter {
    font-size: 11px;
    color: var(--text-muted);
}

.preview-card {
    background: var(--bg);
    border-radius: var(--radius-md);
    padding: 16px;
    margin-top: 16px;
}

.preview-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.preview-title i {
    color: var(--secondary);
}

.google-preview {
    background: white;
    border-radius: var(--radius-sm);
    padding: 14px;
    border: 1px solid var(--border);
}

.preview-title-text {
    color: #1a0dab;
    font-size: 18px;
    font-weight: 400;
    line-height: 1.3;
    margin-bottom: 2px;
}

.preview-url {
    color: #006621;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 2px;
    word-break: break-all;
}

.preview-desc {
    color: #545454;
    font-size: 13px;
    line-height: 1.58;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    box-shadow: var(--shadow-sm);
    transition: all 0.2s;
    width: 100%;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-secondary {
    background: var(--border);
    color: var(--text);
    border: none;
    padding: 12px 20px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s;
    width: 100%;
}

.btn-secondary:hover {
    background: var(--text-muted);
    color: white;
}

.btn-icon {
    width: 42px;
    height: 42px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background: white;
    color: var(--text);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.btn-icon:hover {
    background: var(--primary-soft);
    color: var(--secondary);
}

.checkbox-group {
    background: var(--primary-soft);
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--secondary);
}

.form-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 2px solid var(--primary-soft);
}

.footer {
    text-align: center;
    margin-top: 30px;
    padding: 16px;
    color: var(--text-muted);
    font-size: 11px;
    border-top: 1px solid var(--border);
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
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
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
    gap: 6px;
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
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 16px 20px;
    overflow-y: auto;
    max-height: 60vh;
}

.modal-footer {
    padding: 14px 20px 20px;
    display: flex;
    gap: 10px;
    border-top: 1px solid var(--border);
}

.modal-footer button {
    flex: 1;
    min-height: 44px;
    border-radius: 50px;
}

.upload-area {
    border: 2px dashed var(--border);
    border-radius: var(--radius-md);
    padding: 20px;
    text-align: center;
    background: var(--bg);
    cursor: pointer;
    margin-bottom: 14px;
}

.upload-area i {
    font-size: 36px;
    color: var(--secondary);
    margin-bottom: 8px;
}

.upload-text {
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 4px;
    font-size: 13px;
}

.upload-filename {
    font-size: 11px;
    color: var(--text-muted);
}

.upload-progress {
    height: 6px;
    background: var(--border);
    border-radius: 6px;
    overflow: hidden;
    margin: 14px 0;
    display: none;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--secondary), var(--secondary-light));
    transition: width 0.3s;
}

/* ===== DESKTOP UPGRADE ===== */
@media (min-width: 768px) {
    .main-content {
        margin-left: 280px !important;
        padding: 24px !important;
        max-width: calc(100% - 280px);
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
    
    .developer-card {
        flex-direction: row;
        align-items: center;
        padding: 24px;
    }
    
    .developer-avatar {
        width: 70px;
        height: 70px;
        font-size: 32px;
    }
    
    .developer-name {
        font-size: 20px;
    }
    
    .developer-details {
        flex-direction: row;
        gap: 24px;
    }
    
    .developer-actions {
        margin-top: 0;
    }
    
    .btn-outline-light {
        padding: 10px 16px;
        font-size: 13px;
    }
    
    .form-actions {
        flex-direction: row;
        justify-content: flex-end;
    }
    
    .btn-primary, .btn-secondary {
        width: auto;
        min-width: 160px;
    }
    
    .modal {
        align-items: center;
        padding: 20px;
    }
    
    .modal-content {
        border-radius: var(--radius-xl);
        max-width: 600px;
        animation: modalFade 0.3s ease;
    }
    
    @keyframes modalFade {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }
}

@media (min-width: 1024px) {
    .two-column {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 24px;
    }
    
    .preview-card {
        margin-top: 0;
    }
}
</style>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">
    
    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="welcome-text">
            <i class="<?= $page_icon ?>"></i>
            <h2>
                <?= $page_title ?>
                <span><?= htmlspecialchars($page_subtitle) ?></span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <!-- ALERT -->
    <?php if ($success): ?>
    <div class="alert success">
        <i class="fas fa-check-circle"></i> <?= $success ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
    </div>
    <?php endif; ?>
    
    <!-- DEVELOPER INFO CARD -->
    <div class="developer-card">
        <div class="developer-avatar">
            <i class="fas fa-building"></i>
        </div>
        <div class="developer-info">
            <div class="developer-name">
                <?= htmlspecialchars($developer['nama_perusahaan'] ?: $developer['nama_lengkap']) ?>
            </div>
            <div class="developer-details">
                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($developer['kota'] ?: 'Kuningan') ?></span>
                <?php if (!empty($developer['telepon_perusahaan'])): ?>
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($developer['telepon_perusahaan']) ?></span>
                <?php endif; ?>
                <?php if (!empty($folder_name)): ?>
                <span><i class="fas fa-folder"></i> <?= $folder_name ?>/</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="developer-actions">
            <a href="<?= $preview_url ?>" target="_blank" class="btn-outline-light">
                <i class="fas fa-external-link-alt"></i> Preview
            </a>
        </div>
    </div>
    
    <!-- TABS CONTAINER -->
    <div class="tabs-container">
        <div class="tabs-header">
            <button class="tab-btn active" data-tab="basic">
                <i class="fas fa-search"></i> Basic
            </button>
            <button class="tab-btn" data-tab="og">
                <i class="fab fa-facebook"></i> OG
            </button>
            <button class="tab-btn" data-tab="twitter">
                <i class="fab fa-twitter"></i> Twitter
            </button>
            <button class="tab-btn" data-tab="schema">
                <i class="fas fa-code"></i> Schema
            </button>
            <button class="tab-btn" data-tab="faq">
                <i class="fas fa-question-circle"></i> FAQ
            </button>
        </div>
        
        <!-- FORM -->
        <form method="POST" id="seoForm">
            <input type="hidden" name="action" value="save_seo">
            <!-- PASTIKAN INI ADA -->
            <input type="hidden" name="developer_id" value="<?= $developer_id ?>">
            
            <!-- TAB BASIC SEO -->
            <div class="tab-content active" id="tab-basic">
                <div class="two-column">
                    <div>
                        <!-- AI GENERATE BUTTON -->
                        <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                            <button type="button" class="btn-primary" onclick="generateSEO()" style="width: auto; padding: 10px 20px; background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                                <i class="fas fa-robot"></i> Generate SEO Otomatis (AI)
                            </button>
                        </div>
                        
                        <!-- SEO Title -->
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> SEO Title <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="seo_title" id="seo_title" class="form-control" 
                                   value="<?= htmlspecialchars($seo['seo_title'] ?? '') ?>" 
                                   maxlength="60" required placeholder="Contoh: Rumah Subsidi Kertamulya - Cicilan 1 Juta">
                            <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                                <small class="form-hint"><i class="fas fa-info-circle"></i> Maksimal 60 karakter</small>
                                <small id="title_counter" class="counter">0/60</small>
                            </div>
                        </div>
                        
                        <!-- Meta Description -->
                        <div class="form-group">
                            <label><i class="fas fa-paragraph"></i> Meta Description <span style="color: var(--danger);">*</span></label>
                            <textarea name="seo_description" id="seo_description" class="form-control" 
                                      rows="3" maxlength="160" required placeholder="Deskripsi singkat tentang developer ini..."><?= htmlspecialchars($seo['seo_description'] ?? '') ?></textarea>
                            <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                                <small class="form-hint"><i class="fas fa-info-circle"></i> Maksimal 160 karakter</small>
                                <small id="desc_counter" class="counter">0/160</small>
                            </div>
                        </div>
                        
                        <!-- H1 Tag -->
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> H1 Tag</label>
                            <input type="text" name="h1_tag" class="form-control" 
                                   value="<?= htmlspecialchars($seo['h1_tag'] ?? '') ?>" 
                                   placeholder="Judul Halaman">
                        </div>
                        
                        <!-- Keywords -->
                        <div class="form-group">
                            <label><i class="fas fa-tags"></i> Keywords</label>
                            <input type="text" name="seo_keywords" class="form-control" 
                                   value="<?= htmlspecialchars($seo['seo_keywords'] ?? '') ?>" 
                                   placeholder="rumah subsidi, properti, developer">
                            <small class="form-hint"><i class="fas fa-info-circle"></i> Pisahkan dengan koma</small>
                        </div>
                        
                        <!-- Robots Meta -->
                        <div class="form-group">
                            <label><i class="fas fa-robot"></i> Robots Meta</label>
                            <select name="robots_meta" class="form-control">
                                <option value="index, follow" <?= ($seo['meta_robots_index'] ?? 1) && ($seo['meta_robots_follow'] ?? 1) ? 'selected' : '' ?>>index, follow (Rekomendasi)</option>
                                <option value="noindex, follow" <?= !($seo['meta_robots_index'] ?? 1) && ($seo['meta_robots_follow'] ?? 1) ? 'selected' : '' ?>>noindex, follow</option>
                                <option value="index, nofollow" <?= ($seo['meta_robots_index'] ?? 1) && !($seo['meta_robots_follow'] ?? 1) ? 'selected' : '' ?>>index, nofollow</option>
                                <option value="noindex, nofollow" <?= !($seo['meta_robots_index'] ?? 1) && !($seo['meta_robots_follow'] ?? 1) ? 'selected' : '' ?>>noindex, nofollow</option>
                            </select>
                        </div>
                        
                        <!-- Canonical URL -->
                        <div class="form-group">
                            <label><i class="fas fa-link"></i> Canonical URL</label>
                            <input type="url" name="canonical_url" class="form-control" 
                                   value="<?= htmlspecialchars($seo['canonical_url'] ?? SITE_URL . '/' . ($folder_name ?: '?dev_id=' . $developer_id)) ?>"
                                   placeholder="https://example.com/halaman-ini">
                            <small class="form-hint"><i class="fas fa-info-circle"></i> Kosongkan untuk menggunakan URL default</small>
                        </div>
                        
                        <!-- Priority & Status -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="form-group">
                                <label><i class="fas fa-star"></i> Priority</label>
                                <select name="seo_priority" class="form-control">
                                    <option value="0" <?= ($seo['seo_priority'] ?? 0) == 0 ? 'selected' : '' ?>>Normal</option>
                                    <option value="1" <?= ($seo['seo_priority'] ?? 0) == 1 ? 'selected' : '' ?>>High</option>
                                    <option value="2" <?= ($seo['seo_priority'] ?? 0) == 2 ? 'selected' : '' ?>>Very High</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-check-circle"></i> Status</label>
                                <select name="is_active" class="form-control">
                                    <option value="1" <?= ($seo['is_active'] ?? 1) ? 'selected' : '' ?>>Aktif</option>
                                    <option value="0" <?= !($seo['is_active'] ?? 1) ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Default -->
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_default" id="is_default" value="1" <?= ($seo['is_default'] ?? 0) ? 'checked' : '' ?>>
                            <label for="is_default">Jadikan SEO Default</label>
                        </div>
                    </div>
                    
                    <!-- PREVIEW COLUMN -->
                    <div>
                        <div class="preview-card">
                            <div class="preview-title">
                                <i class="fab fa-google"></i> Google Preview
                            </div>
                            <div class="google-preview" id="googlePreview">
                                <div class="preview-title-text" id="previewTitle">
                                    <?= htmlspecialchars($seo['seo_title'] ?? 'Lead Engine Property') ?>
                                </div>
                                <div class="preview-url" id="previewUrl">
                                    <?= htmlspecialchars($seo['canonical_url'] ?? SITE_URL) ?>
                                </div>
                                <div class="preview-desc" id="previewDesc">
                                    <?= htmlspecialchars($seo['seo_description'] ?? 'Platform properti terintegrasi') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- TAB OPEN GRAPH -->
            <div class="tab-content" id="tab-og" style="display: none;">
                <div class="two-column">
                    <div>
                        <!-- OG Type -->
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> OG Type</label>
                            <select name="og_type" class="form-control">
                                <option value="website" <?= ($seo['og_type'] ?? '') == 'website' ? 'selected' : '' ?>>website</option>
                                <option value="article" <?= ($seo['og_type'] ?? '') == 'article' ? 'selected' : '' ?>>article</option>
                                <option value="product" <?= ($seo['og_type'] ?? '') == 'product' ? 'selected' : '' ?>>product</option>
                            </select>
                        </div>
                        
                        <!-- OG Title -->
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> OG Title</label>
                            <input type="text" name="og_title" class="form-control" 
                                   value="<?= htmlspecialchars($seo['og_title'] ?? $seo['seo_title'] ?? '') ?>"
                                   placeholder="Kosongkan untuk menggunakan SEO Title">
                            <small class="form-hint"><i class="fas fa-info-circle"></i> Kosongkan untuk menggunakan SEO Title</small>
                        </div>
                        
                        <!-- OG Description -->
                        <div class="form-group">
                            <label><i class="fas fa-paragraph"></i> OG Description</label>
                            <textarea name="og_description" class="form-control" rows="3" placeholder="Kosongkan untuk menggunakan Meta Description"><?= htmlspecialchars($seo['og_description'] ?? $seo['seo_description'] ?? '') ?></textarea>
                            <small class="form-hint"><i class="fas fa-info-circle"></i> Kosongkan untuk menggunakan Meta Description</small>
                        </div>
                        
                        <!-- OG Image -->
                        <div class="form-group">
                            <label><i class="fas fa-image"></i> OG Image URL</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" name="og_image" id="og_image" class="form-control" 
                                       value="<?= htmlspecialchars($seo['og_image'] ?? '') ?>" 
                                       placeholder="/uploads/seo/og-image.jpg">
                                <button type="button" class="btn-icon" onclick="openImageUpload('og')" title="Upload Gambar">
                                    <i class="fas fa-upload"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- OG URL -->
                        <div class="form-group">
                            <label><i class="fas fa-link"></i> OG URL</label>
                            <input type="url" name="og_url" class="form-control" 
                                   value="<?= htmlspecialchars($seo['og_url'] ?? $seo['canonical_url'] ?? SITE_URL) ?>"
                                   placeholder="Kosongkan untuk menggunakan Canonical URL">
                            <small class="form-hint"><i class="fas fa-info-circle"></i> Kosongkan untuk menggunakan Canonical URL</small>
                        </div>
                    </div>
                    
                    <!-- PREVIEW COLUMN -->
                    <div>
                        <div class="preview-card">
                            <div class="preview-title">
                                <i class="fab fa-facebook"></i> Facebook Preview
                            </div>
                            <div style="background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border);">
                                <div style="height: 140px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); display: flex; align-items: center; justify-content: center; color: white; font-size: 13px;">
                                    <i class="fas fa-image"></i> Preview
                                </div>
                                <div style="padding: 10px;">
                                    <div style="font-size: 11px; color: #606770;">leadproperti.com</div>
                                    <div style="font-weight: 600; font-size: 14px; color: #1d2129; margin: 3px 0;" id="previewOgTitle">
                                        <?= htmlspecialchars($seo['og_title'] ?? $seo['seo_title'] ?? 'Lead Engine Property') ?>
                                    </div>
                                    <div style="font-size: 12px; color: #606770;" id="previewOgDesc">
                                        <?= htmlspecialchars($seo['og_description'] ?? $seo['seo_description'] ?? '') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- TAB TWITTER -->
            <div class="tab-content" id="tab-twitter" style="display: none;">
                <div class="two-column">
                    <div>
                        <!-- Twitter Card Type -->
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Card Type</label>
                            <select name="twitter_card_type" class="form-control">
                                <option value="summary" <?= ($seo['twitter_card_type'] ?? '') == 'summary' ? 'selected' : '' ?>>summary</option>
                                <option value="summary_large_image" <?= ($seo['twitter_card_type'] ?? 'summary_large_image') == 'summary_large_image' ? 'selected' : '' ?>>summary_large_image</option>
                            </select>
                        </div>
                        
                        <!-- Twitter Title -->
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> Twitter Title</label>
                            <input type="text" name="twitter_title" class="form-control" 
                                   value="<?= htmlspecialchars($seo['twitter_title'] ?? $seo['og_title'] ?? $seo['seo_title'] ?? '') ?>"
                                   placeholder="Kosongkan untuk menggunakan OG Title">
                            <small class="form-hint"><i class="fas fa-info-circle"></i> Kosongkan untuk menggunakan OG Title</small>
                        </div>
                        
                        <!-- Twitter Description -->
                        <div class="form-group">
                            <label><i class="fas fa-paragraph"></i> Twitter Description</label>
                            <textarea name="twitter_description" class="form-control" rows="3" placeholder="Kosongkan untuk menggunakan OG Description"><?= htmlspecialchars($seo['twitter_description'] ?? $seo['og_description'] ?? $seo['seo_description'] ?? '') ?></textarea>
                            <small class="form-hint"><i class="fas fa-info-circle"></i> Kosongkan untuk menggunakan OG Description</small>
                        </div>
                        
                        <!-- Twitter Image -->
                        <div class="form-group">
                            <label><i class="fas fa-image"></i> Twitter Image URL</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" name="twitter_image" id="twitter_image" class="form-control" 
                                       value="<?= htmlspecialchars($seo['twitter_image'] ?? $seo['og_image'] ?? '') ?>" 
                                       placeholder="/uploads/seo/twitter-image.jpg">
                                <button type="button" class="btn-icon" onclick="openImageUpload('twitter')" title="Upload Gambar">
                                    <i class="fas fa-upload"></i>
                                </button>
                            </div>
                            <small class="form-hint"><i class="fas fa-info-circle"></i> Kosongkan untuk menggunakan OG Image</small>
                        </div>
                        
                        <!-- Twitter Image Alt -->
                        <div class="form-group">
                            <label><i class="fas fa-text"></i> Twitter Image Alt</label>
                            <input type="text" name="twitter_image_alt" class="form-control" 
                                   value="<?= htmlspecialchars($seo['twitter_image_alt'] ?? '') ?>"
                                   placeholder="Deskripsi gambar">
                        </div>
                    </div>
                    
                    <!-- PREVIEW COLUMN -->
                    <div>
                        <div class="preview-card">
                            <div class="preview-title">
                                <i class="fab fa-twitter"></i> Twitter Preview
                            </div>
                            <div style="background: white; border-radius: 12px; overflow: hidden; border: 1px solid var(--border);">
                                <div style="height: 130px; background: linear-gradient(135deg, var(--secondary), var(--secondary-light)); display: flex; align-items: center; justify-content: center; color: white; font-size: 13px;">
                                    <i class="fas fa-image"></i> Preview
                                </div>
                                <div style="padding: 10px;">
                                    <div style="font-weight: 700; font-size: 13px; color: #1d2129;" id="previewTwitterTitle">
                                        <?= htmlspecialchars($seo['twitter_title'] ?? $seo['og_title'] ?? $seo['seo_title'] ?? 'Lead Engine Property') ?>
                                    </div>
                                    <div style="font-size: 12px; color: #5b6770; margin-top: 3px;" id="previewTwitterDesc">
                                        <?= htmlspecialchars($seo['twitter_description'] ?? $seo['og_description'] ?? $seo['seo_description'] ?? '') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- TAB SCHEMA -->
            <div class="tab-content" id="tab-schema" style="display: none;">
                <div style="margin-bottom: 16px; display: flex; justify-content: flex-end;">
                    <button type="button" class="btn-primary" onclick="generateSchema()" style="width: auto; padding: 10px 16px;">
                        <i class="fas fa-magic"></i> Generate Schema
                    </button>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-code"></i> Schema JSON-LD</label>
                    <textarea name="schema_json" id="schema_json" class="form-control" rows="10" style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($seo['schema_json'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- TAB FAQ -->
            <div class="tab-content" id="tab-faq" style="display: none;">
                <div class="form-group">
                    <label><i class="fas fa-question-circle"></i> FAQ JSON-LD</label>
                    <textarea name="faq_json" id="faq_json" class="form-control" rows="8" style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($seo['faq_json'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-link"></i> Breadcrumb JSON-LD</label>
                    <textarea name="breadcrumb_json" id="breadcrumb_json" class="form-control" rows="5" style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($seo['breadcrumb_json'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="resetToDefault()">
                    <i class="fas fa-undo"></i> Reset ke Default
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - SEO Manager v6.0</p>
    </div>
</div>

<!-- MODAL UPLOAD IMAGE -->
<div class="modal" id="imageUploadModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-image"></i> Upload Gambar</h2>
            <button class="modal-close" onclick="closeModal('imageUploadModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="imageUploadForm" enctype="multipart/form-data">
                <input type="hidden" name="type" id="uploadType" value="og">
                <!-- PASTIKAN INI ADA -->
                <input type="hidden" name="developer_id" value="<?= $developer_id ?>">
                <input type="hidden" name="key" value="<?= API_KEY ?>">
                
                <div class="upload-area" onclick="document.getElementById('imageFile').click()">
                    <input type="file" id="imageFile" name="image" accept="image/jpeg,image/jpg,image/png,image/webp" style="display: none;" onchange="previewUploadImage(this)">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p class="upload-text">Klik untuk memilih file</p>
                    <p class="upload-filename" id="selectedImageName">Belum ada file dipilih</p>
                </div>
                
                <div id="imagePreview" style="text-align: center; margin: 15px 0; display: none;">
                    <img id="uploadPreview" style="max-width: 100%; max-height: 200px; border-radius: 8px; border: 2px solid var(--border);">
                </div>
                
                <div class="upload-progress" id="uploadProgress" style="display: none;">
                    <div class="progress-bar" id="uploadProgressBar" style="width: 0%;"></div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('imageUploadModal')">Batal</button>
            <button type="button" class="btn-primary" onclick="uploadImage()">Upload</button>
        </div>
    </div>
</div>

<script>
// ===== TAB NAVIGATION =====
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        document.querySelectorAll('.tab-content').forEach(content => {
            content.style.display = 'none';
        });
        
        const tab = this.dataset.tab;
        document.getElementById('tab-' + tab).style.display = 'block';
    });
});

// ===== SEO COUNTERS =====
function updateCounters() {
    const title = document.getElementById('seo_title');
    const desc = document.getElementById('seo_description');
    
    if (title) {
        const len = title.value.length;
        document.getElementById('title_counter').innerHTML = len + '/60';
        document.getElementById('title_counter').style.color = len > 55 ? '#D64F3C' : '#7A8A84';
        document.getElementById('previewTitle').innerHTML = title.value || 'Lead Engine Property';
    }
    
    if (desc) {
        const len = desc.value.length;
        document.getElementById('desc_counter').innerHTML = len + '/160';
        document.getElementById('desc_counter').style.color = len > 150 ? '#D64F3C' : '#7A8A84';
        document.getElementById('previewDesc').innerHTML = desc.value || 'Platform properti terintegrasi';
    }
    
    const canonical = document.querySelector('[name="canonical_url"]')?.value || '<?= SITE_URL ?>';
    document.getElementById('previewUrl').innerHTML = canonical;
    
    const ogTitle = document.querySelector('[name="og_title"]')?.value || title?.value || 'Lead Engine Property';
    document.getElementById('previewOgTitle').innerHTML = ogTitle;
    
    const ogDesc = document.querySelector('[name="og_description"]')?.value || desc?.value || '';
    document.getElementById('previewOgDesc').innerHTML = ogDesc;
    
    const twitterTitle = document.querySelector('[name="twitter_title"]')?.value || ogTitle;
    document.getElementById('previewTwitterTitle').innerHTML = twitterTitle;
    
    const twitterDesc = document.querySelector('[name="twitter_description"]')?.value || ogDesc;
    document.getElementById('previewTwitterDesc').innerHTML = twitterDesc;
}

document.querySelectorAll('#seo_title, #seo_description, [name="og_title"], [name="og_description"], [name="twitter_title"], [name="twitter_description"], [name="canonical_url"]').forEach(el => {
    el.addEventListener('input', updateCounters);
    el.addEventListener('change', updateCounters);
});

updateCounters();

// ===== GENERATE SEO OTOMATIS (AI) - DENGAN DEBUG =====
function generateSEO() {
    console.log('========== GENERATE SEO DEBUG ==========');
    
    // Cek developer_id dari berbagai sumber
    const devIdFromPhp = <?= $developer_id ?>;
    const devIdFromInput = document.querySelector('input[name="developer_id"]')?.value;
    
    console.log('Developer ID dari PHP:', devIdFromPhp);
    console.log('Developer ID dari input[name="developer_id"]:', devIdFromInput);
    
    // Ambil developer_id
    let developerId = devIdFromPhp;
    
    console.log('Developer ID final:', developerId);
    
    if (!developerId || developerId === 0) {
        alert('❌ Error: Developer ID tidak valid.\n\n' +
              'Nilai yang ditemukan:\n' +
              '- Dari PHP: ' + devIdFromPhp + '\n' +
              '- Dari input: ' + devIdFromInput + '\n\n' +
              'Silakan refresh halaman.');
        return;
    }
    
    if (!confirm('Generate SEO otomatis? Data Title, Description, Keywords akan digenerate ulang.')) {
        return;
    }
    
    // Tampilkan loading
    const generateBtn = event.target;
    const originalText = generateBtn.innerHTML;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    generateBtn.disabled = true;
    
    // Kirim request
    const formData = new URLSearchParams();
    formData.append('developer_id', developerId);
    formData.append('key', '<?= API_KEY ?>');
    
    console.log('Sending data:', Object.fromEntries(formData));
    
    fetch('api/generate_seo_ai.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString()
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON: ' + text.substring(0, 200));
            }
        });
    })
    .then(data => {
        console.log('Parsed response:', data);
        
        if (data.success) {
            // Update form fields
            const fields = {
                'seo_title': data.data.seo_title,
                'seo_description': data.data.seo_description,
                'seo_keywords': data.data.seo_keywords,
                'h1_tag': data.data.h1_tag,
                'og_title': data.data.og_title,
                'og_description': data.data.og_description,
                'twitter_title': data.data.twitter_title,
                'twitter_description': data.data.twitter_description
            };
            
            for (const [field, value] of Object.entries(fields)) {
                const el = document.querySelector(`[name="${field}"]`) || document.getElementById(field);
                if (el) {
                    el.value = value;
                }
            }
            
            updateCounters();
            showToast('✅ SEO berhasil digenerate!', 'success');
        } else {
            showToast('❌ ' + (data.message || 'Gagal generate SEO'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('❌ Error: ' + error.message, 'error');
    })
    .finally(() => {
        generateBtn.innerHTML = originalText;
        generateBtn.disabled = false;
    });
}

// ===== GENERATE SCHEMA =====
function generateSchema() {
    if (confirm('Generate schema otomatis?')) {
        fetch('api/generate_schema.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                developer_id: <?= $developer_id ?>,
                key: '<?= API_KEY ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('schema_json').value = data.data.schema_json;
                showToast('✅ Schema berhasil digenerate', 'success');
            } else {
                showToast('❌ ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('❌ Error: ' + error.message, 'error');
        });
    }
}

// ===== RESET TO DEFAULT =====
function resetToDefault() {
    if (confirm('Reset semua data SEO ke default?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="reset_default">';
        document.body.appendChild(form);
        form.submit();
    }
}

// ===== IMAGE UPLOAD =====
let currentUploadType = 'og';

function openImageUpload(type) {
    currentUploadType = type;
    document.getElementById('uploadType').value = type;
    document.getElementById('imageFile').value = '';
    document.getElementById('selectedImageName').innerText = 'Belum ada file dipilih';
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('uploadProgress').style.display = 'none';
    openModal('imageUploadModal');
}

function previewUploadImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('selectedImageName').innerText = file.name;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('uploadPreview').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

function uploadImage() {
    console.log('========== UPLOAD DEBUG ==========');
    
    const fileInput = document.getElementById('imageFile');
    if (!fileInput.files || !fileInput.files[0]) {
        alert('❌ Pilih file terlebih dahulu');
        return;
    }
    
    // Cek developer_id
    const devId = document.querySelector('input[name="developer_id"]')?.value;
    console.log('Developer ID from form:', devId);
    
    if (!devId || devId === '0') {
        alert('❌ Error: Developer ID tidak valid. Silakan refresh halaman.');
        return;
    }
    
    const formData = new FormData(document.getElementById('imageUploadForm'));
    
    console.log('FormData contents:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ':', pair[1]);
    }
    
    const uploadBtn = document.querySelector('#imageUploadModal .btn-primary');
    const originalText = uploadBtn.innerHTML;
    
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    uploadBtn.disabled = true;
    
    document.getElementById('uploadProgress').style.display = 'block';
    document.getElementById('uploadProgressBar').style.width = '0%';
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/upload_seo_image.php', true);
    
    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            const percent = (e.loaded / e.total) * 100;
            document.getElementById('uploadProgressBar').style.width = percent + '%';
        }
    };
    
    xhr.onload = function() {
        console.log('Upload response status:', xhr.status);
        console.log('Upload response:', xhr.responseText);
        
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    if (currentUploadType === 'og') {
                        document.getElementById('og_image').value = response.data.path;
                    } else {
                        document.getElementById('twitter_image').value = response.data.path;
                    }
                    
                    closeModal('imageUploadModal');
                    showToast('✅ ' + response.message, 'success');
                } else {
                    showToast('❌ ' + response.message, 'error');
                }
            } catch (e) {
                showToast('❌ Error parsing response', 'error');
            }
        } else {
            showToast('❌ Upload failed: ' + xhr.status, 'error');
        }
        
        uploadBtn.innerHTML = originalText;
        uploadBtn.disabled = false;
        document.getElementById('uploadProgress').style.display = 'none';
    };
    
    xhr.onerror = function() {
        showToast('❌ Network error', 'error');
        uploadBtn.innerHTML = originalText;
        uploadBtn.disabled = false;
        document.getElementById('uploadProgress').style.display = 'none';
    };
    
    xhr.send(formData);
}

// ===== MODAL FUNCTIONS =====
function openModal(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}

function showToast(message, type = 'info') {
    let toast = document.querySelector('.toast-message');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-message';
        toast.style.cssText = `
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: ${type === 'success' ? '#2A9D8F' : type === 'error' ? '#D64F3C' : '#1B4A3C'};
            color: white;
            padding: 12px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            z-index: 10001;
            transition: opacity 0.3s;
            max-width: 90%;
            text-align: center;
        `;
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.style.opacity = '1';
    
    setTimeout(() => {
        toast.style.opacity = '0';
    }, 3000);
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

// Close modal on outside click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// Escape key close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(modal => {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>