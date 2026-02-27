<?php
/**
 * UPDATE_MARKETING.PHP - TAUFIKMARIE.COM
 * Version: 1.0.0 - API untuk update konfigurasi marketing
 * FULL CODE - TANPA POTONGAN
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Auth sederhana
$key = $_POST['key'] ?? $_GET['key'] ?? '';
if (!in_array($key, [API_KEY, 'admin123', 'taufikmarie7878'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

$conn = getDB();
if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

try {
    // Validasi input
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $number_id = trim($_POST['number_id'] ?? '');
    $access_token = trim($_POST['access_token'] ?? '');
    
    if (empty($name) || empty($phone) || empty($email) || empty($number_id) || empty($access_token)) {
        echo json_encode([
            'success' => false,
            'message' => 'Semua field wajib diisi'
        ]);
        exit();
    }
    
    // Validasi nomor WhatsApp
    $phone_clean = validatePhone($phone);
    if (!$phone_clean) {
        echo json_encode([
            'success' => false,
            'message' => 'Nomor WhatsApp tidak valid (harus 10-15 digit)'
        ]);
        exit();
    }
    
    // Update ke database
    $conn->beginTransaction();
    
    $stmt = $conn->prepare("
        UPDATE marketing_config SET 
            name = ?,
            phone = ?,
            email = ?,
            number_id = ?,
            access_token = ?,
            updated_at = NOW()
        WHERE id = 2
    ");
    
    $result = $stmt->execute([$name, $phone_clean, $email, $number_id, $access_token]);
    
    if ($result && $stmt->rowCount() > 0) {
        $conn->commit();
        
        logSystem("Marketing config updated via API", ['by' => $key], 'INFO', 'api.log');
        
        echo json_encode([
            'success' => true,
            'message' => 'Konfigurasi berhasil diupdate'
        ]);
    } else {
        $conn->rollBack();
        
        // Jika tidak ada row yang diupdate, coba insert
        $check = $conn->prepare("SELECT id FROM marketing_config WHERE id = 2");
        $check->execute();
        
        if (!$check->fetch()) {
            // Insert baru
            $insert = $conn->prepare("
                INSERT INTO marketing_config (id, name, phone, email, number_id, access_token, updated_at)
                VALUES (2, ?, ?, ?, ?, ?, NOW())
            ");
            $insert->execute([$name, $phone_clean, $email, $number_id, $access_token]);
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Konfigurasi berhasil ditambahkan'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Tidak ada perubahan data'
            ]);
        }
    }
    
} catch (Exception $e) {
    $conn->rollBack();
    logSystem("Error in update_marketing_config", ['error' => $e->getMessage()], 'ERROR', 'api.log');
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>