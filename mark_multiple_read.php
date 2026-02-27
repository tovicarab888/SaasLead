<?php
/**
 * MARK_MULTIPLE_READ.PHP - Tandai banyak notifikasi telah dibaca
 */
require_once 'config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$ids = $input['ids'] ?? [];
$key = $input['key'] ?? '';

if ($key !== API_KEY) {
    echo json_encode(['success' => false, 'message' => 'Invalid key']);
    exit;
}

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'IDs required']);
    exit;
}

$conn = getDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    echo json_encode(['success' => true, 'count' => count($ids)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>