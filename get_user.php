<?php
/**
 * GET_USER.PHP - TAUFIKMARIE.COM
 * Version: 1.0.0 - API untuk mengambil data user
 * FULL CODE - TANPA POTONGAN
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Auth sederhana
$key = $_GET['key'] ?? '';
if (!in_array($key, [API_KEY, 'admin123', 'taufikmarie7878'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID tidak valid'
    ]);
    exit();
}

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT id, username, email, nama_lengkap, role, location_access, last_login, is_active FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo json_encode([
            'success' => true,
            'data' => $user
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User tidak ditemukan'
        ]);
    }
    
} catch (Exception $e) {
    logSystem("Error in get_user", ['error' => $e->getMessage()], 'ERROR', 'api.log');
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
?>