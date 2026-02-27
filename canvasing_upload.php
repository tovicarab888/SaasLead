<?php
/**
 * CANVASING_UPLOAD.PHP - LEADENGINE
 * Version: 10.0.0 - FIX: Gunakan validatePhone dari config.php, validasi 10-13 digit
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/taufikma/public_html/logs/canvasing_upload.log');

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

if (session_status() === PHP_SESSION_NONE) session_start();

// ===== INI YANG BENAR =====
require_once __DIR__ . '/config.php';

// Log semua request untuk debugging
$log_dir = '/home/taufikma/public_html/logs/';
$debug_log = $log_dir . 'canvasing_debug.log';
file_put_contents($debug_log, date('Y-m-d H:i:s') . " ===== UPLOAD REQUEST =====\n", FILE_APPEND);
file_put_contents($debug_log, date('Y-m-d H:i:s') . " POST: " . json_encode($_POST) . "\n", FILE_APPEND);
file_put_contents($debug_log, date('Y-m-d H:i:s') . " SESSION: " . json_encode($_SESSION) . "\n", FILE_APPEND);

if (!isMarketing()) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Bukan marketing\n", FILE_APPEND);
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized - Silakan login sebagai marketing']));
}

$conn = getDB();
if (!$conn) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Koneksi database gagal\n", FILE_APPEND);
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Koneksi database gagal']));
}

$marketing_id = $_SESSION['marketing_id'];
$developer_id = $_SESSION['marketing_developer_id'] ?? 0;

file_put_contents($debug_log, date('Y-m-d H:i:s') . " Marketing ID: $marketing_id, Developer ID: $developer_id\n", FILE_APPEND);

if ($developer_id <= 0) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Developer ID tidak valid\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Developer ID tidak valid']));
}

// Ambil data
$location_key = $_POST['location_key'] ?? '';
$canvasing_type = $_POST['canvasing_type'] ?? 'individual';
$customer_name = $_POST['customer_name'] ?? '';
$customer_phone = $_POST['customer_phone'] ?? '';
$instansi_name = $_POST['instansi_name'] ?? '';
$pic_name = $_POST['pic_name'] ?? '';
$pic_phone = $_POST['pic_phone'] ?? '';
$notes = $_POST['notes'] ?? '';
$address = $_POST['address'] ?? '';
$latitude = $_POST['latitude'] ?? '';
$longitude = $_POST['longitude'] ?? '';
$accuracy = $_POST['accuracy'] ?? '';
$photo_data = $_POST['photo_data'] ?? '';

file_put_contents($debug_log, date('Y-m-d H:i:s') . " Data: location=$location_key, type=$canvasing_type, lat=$latitude, lng=$longitude\n", FILE_APPEND);

// Validasi wajib
if (empty($location_key)) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Lokasi kosong\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Lokasi wajib dipilih']));
}

if (empty($photo_data)) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Foto kosong\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Foto wajib diambil']));
}

if (empty($latitude) || empty($longitude)) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: GPS tidak valid - lat=$latitude, lng=$longitude\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'GPS tidak valid. Dapatkan lokasi terlebih dahulu.']));
}

// ===== VALIDASI NOMOR WHATSAPP MENGGUNAKAN FUNGSI DARI CONFIG.PHP =====
// Fungsi validatePhone() sudah ada di config.php, kita gunakan langsung

if (!empty($customer_phone)) {
    // Validasi panjang minimal 10, maksimal 13 digit (sebelum diformat)
    $clean_phone = preg_replace('/[^0-9]/', '', $customer_phone);
    if (strlen($clean_phone) < 10 || strlen($clean_phone) > 13) {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: No. Customer harus 10-13 digit (sekarang " . strlen($clean_phone) . " digit)\n", FILE_APPEND);
        die(json_encode(['success' => false, 'message' => 'No. Customer harus 10-13 digit']));
    }
    
    $v = validatePhone($customer_phone);
    if (!$v['valid']) {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: No. Customer: " . $v['message'] . "\n", FILE_APPEND);
        die(json_encode(['success' => false, 'message' => 'No. Customer: ' . $v['message']]));
    }
    $customer_phone = $v['number'];
}

if (!empty($pic_phone)) {
    // Validasi panjang minimal 10, maksimal 13 digit (sebelum diformat)
    $clean_phone = preg_replace('/[^0-9]/', '', $pic_phone);
    if (strlen($clean_phone) < 10 || strlen($clean_phone) > 13) {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: No. PIC harus 10-13 digit (sekarang " . strlen($clean_phone) . " digit)\n", FILE_APPEND);
        die(json_encode(['success' => false, 'message' => 'No. PIC harus 10-13 digit']));
    }
    
    $v = validatePhone($pic_phone);
    if (!$v['valid']) {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: No. PIC: " . $v['message'] . "\n", FILE_APPEND);
        die(json_encode(['success' => false, 'message' => 'No. PIC: ' . $v['message']]));
    }
    $pic_phone = $v['number'];
}

// Validasi type
if ($canvasing_type !== 'individual') {
    if (empty($instansi_name)) {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Nama instansi kosong\n", FILE_APPEND);
        die(json_encode(['success' => false, 'message' => 'Nama instansi/toko wajib diisi']));
    }
    if (empty($pic_name)) {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Nama PIC kosong\n", FILE_APPEND);
        die(json_encode(['success' => false, 'message' => 'Nama PIC wajib diisi']));
    }
}

// Cek duplikat (5 menit terakhir)
try {
    $stmt = $conn->prepare("
        SELECT id FROM canvasing_logs 
        WHERE marketing_id = ? 
        AND ABS(latitude - ?) < 0.0001 
        AND ABS(longitude - ?) < 0.0001 
        AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$marketing_id, (float)$latitude, (float)$longitude]);
    if ($stmt->fetch()) {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " WARNING: Duplikat dalam 5 menit\n", FILE_APPEND);
        // Allow saja, jangan ditolak
    }
} catch (Exception $e) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " WARNING: Cek duplikat error: " . $e->getMessage() . "\n", FILE_APPEND);
    // Lanjutkan saja jika error
}

// Buat folder dengan path ABSOLUT yang benar
$upload_base = '/home/taufikma/public_html/uploads/canvasing/';  // TANPA 'admin/'
$dev_folder = $upload_base . 'developer_' . $developer_id . '/';

file_put_contents($debug_log, date('Y-m-d H:i:s') . " Upload base: $upload_base\n", FILE_APPEND);
file_put_contents($debug_log, date('Y-m-d H:i:s') . " Dev folder: $dev_folder\n", FILE_APPEND);

// Cek dan buat folder
if (!is_dir($upload_base)) {
    $mkdir_result = mkdir($upload_base, 0755, true);
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " Membuat upload_base: " . ($mkdir_result ? 'SUKSES' : 'GAGAL') . "\n", FILE_APPEND);
    if (!$mkdir_result) {
        die(json_encode(['success' => false, 'message' => 'Gagal membuat folder upload: ' . $upload_base]));
    }
}

if (!is_dir($dev_folder)) {
    $mkdir_result = mkdir($dev_folder, 0755, true);
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " Membuat dev_folder: " . ($mkdir_result ? 'SUKSES' : 'GAGAL') . "\n", FILE_APPEND);
    if (!$mkdir_result) {
        die(json_encode(['success' => false, 'message' => 'Gagal membuat folder developer: ' . $dev_folder]));
    }
}

// Cek writability
if (!is_writable($dev_folder)) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Folder tidak writable: $dev_folder\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Folder upload tidak dapat ditulisi. Hubungi admin.']));
}

// Simpan foto - gunakan timestamp dan random
$filename = $marketing_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
$filepath = $dev_folder . $filename;

file_put_contents($debug_log, date('Y-m-d H:i:s') . " Filepath: $filepath\n", FILE_APPEND);

// Decode base64
$image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $photo_data));
if (!$image_data) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Gagal decode base64\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Gagal decode foto. Format tidak valid.']));
}

// Simpan file
$write_result = file_put_contents($filepath, $image_data);
if (!$write_result) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Gagal write file - bytes: " . strlen($image_data) . "\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Gagal menyimpan foto (write error)']));
}

file_put_contents($debug_log, date('Y-m-d H:i:s') . " File tersimpan: $write_result bytes\n", FILE_APPEND);

// Cek apakah file benar-benar tersimpan
if (!file_exists($filepath)) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: File tidak ditemukan setelah write\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'File foto gagal tersimpan']));
}

$filesize = filesize($filepath);
file_put_contents($debug_log, date('Y-m-d H:i:s') . " File size: $filesize bytes\n", FILE_APPEND);

// Path relatif untuk database - SESUAIKAN DENGAN DATA YANG SUDAH ADA
$relative_path = 'uploads/canvasing/developer_' . $developer_id . '/' . $filename;  // TANPA 'admin/'

file_put_contents($debug_log, date('Y-m-d H:i:s') . " Relative path: $relative_path\n", FILE_APPEND);

// Insert database
try {
    $conn->beginTransaction();
    
    // Kolom address sudah pasti ada di database
    $sql = "INSERT INTO canvasing_logs (
        marketing_id, developer_id, location_key, photo_path,
        latitude, longitude, accuracy, canvasing_type,
        customer_name, customer_phone, instansi_name, pic_name, pic_phone,
        address, notes, created_at
    ) VALUES (
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, NOW()
    )";
    
    $stmt = $conn->prepare($sql);
    
    $params = [
        $marketing_id,
        $developer_id,
        $location_key,
        $relative_path,
        $latitude,
        $longitude,
        $accuracy,
        $canvasing_type,
        $customer_name,
        $customer_phone,
        $instansi_name,
        $pic_name,
        $pic_phone,
        $address,
        $notes
    ];
    
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " Executing with params: " . json_encode($params) . "\n", FILE_APPEND);
    
    $result = $stmt->execute($params);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Execute failed: " . ($errorInfo[2] ?? 'Unknown error'));
    }
    
    $last_id = $conn->lastInsertId();
    
    $conn->commit();
    
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " SUKSES! ID: $last_id\n", FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'message' => 'Data canvasing berhasil disimpan',
        'id' => $last_id,
        'photo_url' => 'https://taufikmarie.com/' . $relative_path
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    
    // Hapus file jika sudah terlanjur tersimpan
    if (file_exists($filepath)) {
        unlink($filepath);
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " File dihapus karena error\n", FILE_APPEND);
    }
    
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR database: " . $e->getMessage() . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>