<?php
/**
 * GET_DEVELOPER_SEO.PHP - API AMBIL DATA SEO DEVELOPER
 * Version: 1.0.0
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

// Auth
$key = $_GET['key'] ?? '';
if (!in_array($key, [API_KEY, 'taufikmarie7878'])) {
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

$developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;

if ($developer_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Developer ID required']);
    exit();
}

try {
    // Cek apakah developer ada
    $stmt = $conn->prepare("SELECT id, nama_lengkap, nama_perusahaan FROM users WHERE id = ? AND role = 'developer' AND is_active = 1");
    $stmt->execute([$developer_id]);
    $developer = $stmt->fetch();
    
    if (!$developer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Developer not found']);
        exit();
    }
    
    // Ambil SEO
    $seo = getDeveloperSEO($developer_id);
    
    echo json_encode([
        'success' => true,
        'data' => $seo,
        'developer' => [
            'id' => $developer['id'],
            'name' => $developer['nama_perusahaan'] ?: $developer['nama_lengkap']
        ]
    ]);
    
} catch (Exception $e) {
    logSystem("Error in get_developer_seo", ['error' => $e->getMessage()], 'ERROR', 'api.log');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}