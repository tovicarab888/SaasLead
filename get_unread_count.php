<?php
/**
 * GET_UNREAD_COUNT.PHP - Ambil jumlah notifikasi belum dibaca
 */
require_once 'config.php';

header('Content-Type: application/json');

// Cek API Key - terima baik 'key' maupun 'api_key'
$api_key = $_GET['api_key'] ?? $_GET['key'] ?? '';
if ($api_key !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'count' => 0]);
    exit();
}

$conn = getDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'count' => 0]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
    $stmt->execute();
    $count = (int)($stmt->fetch()['count'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'count' => 0
    ]);
}
?>