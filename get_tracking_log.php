<?php
/**
 * API: GET SINGLE TRACKING LOG - Untuk detail modal
 * Version: 1.0.0
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!checkAuth()) {
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

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit();
    }
    
    $sql = "
        SELECT 
            tl.*,
            u.nama_lengkap as developer_name,
            l.first_name,
            l.last_name,
            l.phone
        FROM tracking_logs tl
        LEFT JOIN users u ON tl.developer_id = u.id
        LEFT JOIN leads l ON tl.lead_id = l.id
        WHERE tl.id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $log = $stmt->fetch();
    
    if (!$log) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Log not found']);
        exit();
    }
    
    // Format response
    $response = [
        'success' => true,
        'data' => [
            'id' => $log['id'],
            'created_at' => $log['created_at'],
            'developer_id' => $log['developer_id'],
            'developer_name' => $log['developer_name'] ?? 'Global',
            'pixel_type' => $log['pixel_type'],
            'event_name' => $log['event_name'],
            'event_id' => $log['event_id'],
            'lead_id' => $log['lead_id'],
            'lead_name' => trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')),
            'lead_phone' => $log['phone'] ?? '',
            'status' => $log['status'],
            'response_code' => '-', // Default karena tidak ada kolom
            'payload' => $log['payload'],
            'response' => $log['response'],
            'sent_at' => $log['sent_at']
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_tracking_log: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}