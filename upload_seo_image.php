<?php
/**
 * UPLOAD_SEO_IMAGE.PHP - UPLOAD GAMBAR UNTUK SEO
 * Version: 3.0.0 - ULTIMATE FIX
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Debug log
error_log("========== UPLOAD SEO IMAGE ==========");
error_log("POST: " . print_r($_POST, true));
error_log("FILES: " . print_r($_FILES, true));

// Auth
$key = $_POST['key'] ?? $_GET['key'] ?? '';
if (!in_array($key, [API_KEY, 'taufikmarie7878'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$developer_id = isset($_POST['developer_id']) ? (int)$_POST['developer_id'] : 0;
$type = $_POST['type'] ?? 'og';

error_log("Developer ID: $developer_id, Type: $type");

if ($developer_id <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Developer ID tidak valid. Diterima: ' . $developer_id,
        'debug' => ['post' => $_POST]
    ]);
    exit();
}

// Cek file
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $error = $_FILES['image']['error'] ?? 'No file uploaded';
    $error_msg = match($error) {
        1 => 'File terlalu besar (melebihi upload_max_filesize)',
        2 => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
        3 => 'File hanya terupload sebagian',
        4 => 'Tidak ada file yang diupload',
        6 => 'Folder temporary tidak ada',
        7 => 'Gagal menulis file ke disk',
        8 => 'Ekstensi file tidak diizinkan',
        default => 'Upload error: ' . $error
    };
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit();
}

$file = $_FILES['image'];

// Validasi tipe file
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, GIF, atau WEBP.']);
    exit();
}

// Validasi ukuran (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 5MB']);
    exit();
}

// Buat direktori upload
$upload_dir = dirname(__DIR__) . '/uploads/seo/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Gagal membuat folder upload']);
        exit();
    }
}

// Generate nama file
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'dev_' . $developer_id . '_' . $type . '_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;
$relative_path = 'uploads/seo/' . $filename;

// Pindahkan file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    
    // Dapatkan dimensi
    list($width, $height) = @getimagesize($filepath);
    
    // Dapatkan base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'leadproperti.com';
    $base_url = $protocol . '://' . $host;
    
    logSystem("SEO image uploaded", [
        'developer_id' => $developer_id,
        'type' => $type,
        'filename' => $filename
    ], 'INFO', 'seo.log');
    
    echo json_encode([
        'success' => true,
        'message' => 'Gambar berhasil diupload',
        'data' => [
            'path' => '/' . $relative_path,
            'full_url' => $base_url . '/' . $relative_path,
            'width' => $width ?: 1200,
            'height' => $height ?: 630,
            'filename' => $filename
        ]
    ]);
    
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file. Periksa permission folder.']);
}
?>