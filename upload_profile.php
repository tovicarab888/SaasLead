<?php
/**
 * UPLOAD_PROFILE.PHP - TAUFIKMARIE.COM
 * Version: 1.0.0 - Upload profile photo untuk semua role
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Cek autentikasi
if (!checkAuth() && !isMarketing()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Buat folder upload jika belum ada
$upload_dir = dirname(__DIR__) . '/uploads/profiles/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Konfigurasi upload
$max_file_size = 5 * 1024 * 1024; // 5MB
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Cek apakah ada file yang diupload
if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    $error_code = $_FILES['profile_photo']['error'] ?? 'unknown';
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Tidak ada file yang diupload atau terjadi error',
        'error_code' => $error_code
    ]);
    exit();
}

$file = $_FILES['profile_photo'];

// Cek ukuran file
if ($file['size'] > $max_file_size) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Ukuran file maksimal 5MB'
    ]);
    exit();
}

// Cek tipe file
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, GIF, atau WEBP'
    ]);
    exit();
}

// Dapatkan ekstensi file
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowed_extensions)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Ekstensi file tidak valid'
    ]);
    exit();
}

// Tentukan role user
$is_marketing = isMarketing();
$user_id = 0;
$role = '';

if ($is_marketing) {
    $user_id = $_SESSION['marketing_id'] ?? 0;
    $role = 'marketing';
    $table = 'marketing_team';
    $id_field = 'id';
    $username = $_SESSION['marketing_username'] ?? 'marketing';
} else {
    $user_id = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? 'user';
    $table = 'users';
    $id_field = 'id';
    $username = $_SESSION['username'] ?? 'user';
}

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID tidak valid']);
    exit();
}

// Generate nama file unik
$filename = $role . '_' . $user_id . '_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Hapus foto lama jika ada
try {
    $stmt = $conn->prepare("SELECT profile_photo FROM $table WHERE $id_field = ?");
    $stmt->execute([$user_id]);
    $old_photo = $stmt->fetchColumn();
    
    if ($old_photo && file_exists($upload_dir . $old_photo)) {
        @unlink($upload_dir . $old_photo);
    }
} catch (Exception $e) {
    // Abaikan error, lanjutkan upload
    error_log("Error deleting old profile photo: " . $e->getMessage());
}

// Upload file baru
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Auto-crop dan resize ke ukuran standar (opsional - bisa dengan GD library)
    // Untuk production, gunakan library seperti Intervention Image
    
    try {
        // Update database
        $stmt = $conn->prepare("
            UPDATE $table SET 
                profile_photo = ?,
                profile_photo_updated_at = NOW()
            WHERE $id_field = ?
        ");
        $result = $stmt->execute([$filename, $user_id]);
        
        if ($result) {
            // Log aktivitas
            logSystem("Profile photo uploaded", [
                'user_id' => $user_id,
                'role' => $role,
                'filename' => $filename,
                'size' => $file['size']
            ], 'INFO', 'profile.log');
            
            echo json_encode([
                'success' => true,
                'message' => 'Foto profil berhasil diupload',
                'filename' => $filename,
                'url' => '/admin/uploads/profiles/' . $filename,
                'role' => $role
            ]);
        } else {
            // Hapus file jika update database gagal
            @unlink($filepath);
            throw new Exception('Gagal update database');
        }
        
    } catch (Exception $e) {
        @unlink($filepath);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menyimpan ke database: ' . $e->getMessage()
        ]);
    }
    
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal upload file'
    ]);
}
?>