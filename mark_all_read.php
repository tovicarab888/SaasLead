<?php
/**
 * MARK_ALL_READ.PHP - Tandai semua notifikasi sudah dibaca
 */
require_once 'config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$api_key = $input['api_key'] ?? $_GET['api_key'] ?? '';

if ($api_key !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = getDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1");
    $stmt->execute();
    
    echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>