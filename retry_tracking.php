<?php
/**
 * API: RETRY TRACKING - Untuk mengirim ulang event yang failed
 * Version: 1.0.1 - FIXED PATH
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../api/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Hanya admin yang bisa retry
if (!isAdmin() && !isManager()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? intval($input['id']) : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit();
    }
    
    // Ambil log yang failed
    $stmt = $conn->prepare("
        SELECT * FROM tracking_logs 
        WHERE id = ? AND status = 'failed'
    ");
    $stmt->execute([$id]);
    $log = $stmt->fetch();
    
    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Failed log not found']);
        exit();
    }
    
    // Decode payload
    $payload = json_decode($log['payload'], true);
    if (!$payload) {
        echo json_encode(['success' => false, 'message' => 'Invalid payload']);
        exit();
    }
    
    // Ambil data lead jika ada
    $lead_data = [];
    if ($log['lead_id'] > 0) {
        $stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
        $stmt->execute([$log['lead_id']]);
        $lead = $stmt->fetch();
        if ($lead) {
            $lead_data = [
                'customer_id' => $lead['id'],
                'first_name' => $lead['first_name'],
                'last_name' => $lead['last_name'],
                'email' => $lead['email'],
                'phone' => $lead['phone'],
                'location' => $lead['location_key'],
                'fbp' => $lead['fbp'],
                'fbc' => $lead['fbc']
            ];
        }
    }
    
    // Kirim ulang berdasarkan platform
    $result = ['success' => false];
    
    if ($log['pixel_type'] === 'meta') {
        $result = sendMetaTracking($lead_data, $log['developer_id']);
    } elseif ($log['pixel_type'] === 'tiktok') {
        $result = sendTikTokTracking($lead_data, $log['developer_id']);
    } elseif ($log['pixel_type'] === 'google') {
        $result = sendGATracking($lead_data, $log['developer_id']);
    }
    
    if ($result['success']) {
        // Update status log
        $update = $conn->prepare("
            UPDATE tracking_logs 
            SET status = 'sent', response = ?, sent_at = NOW() 
            WHERE id = ?
        ");
        $update->execute(['Retry successful: ' . json_encode($result), $id]);
        
        echo json_encode(['success' => true, 'message' => 'Event retry successful']);
    } else {
        // Increment retry count
        $update = $conn->prepare("
            UPDATE tracking_logs 
            SET retry_count = retry_count + 1, response = ? 
            WHERE id = ?
        ");
        $update->execute(['Retry failed: ' . ($result['error'] ?? 'Unknown error'), $id]);
        
        echo json_encode(['success' => false, 'message' => 'Retry failed: ' . ($result['error'] ?? 'Unknown error')]);
    }
    
} catch (Exception $e) {
    error_log("Error in retry_tracking: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}