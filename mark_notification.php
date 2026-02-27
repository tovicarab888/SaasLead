<?php
/**
 * MARK_NOTIFICATION.PHP - Tandai notifikasi telah dibaca
 */
require_once 'config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$key = $input['key'] ?? '';

if ($key !== API_KEY) {
    echo json_encode(['success' => false, 'message' => 'Invalid key']);
    exit;
}

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID required']);
    exit;
}

$conn = getDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>