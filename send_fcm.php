<?php
/**
 * SEND_FCM.PHP - Kirim notifikasi via Firebase Cloud Messaging
 * Version: 1.0.0
 */
require_once 'config.php';

header('Content-Type: application/json');

// Cek API key
$api_key = $_POST['key'] ?? $_GET['key'] ?? '';
if ($api_key !== API_KEY) {
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

// Ambil parameter
$title = $_POST['title'] ?? 'Lead Engine';
$body = $_POST['body'] ?? 'Ada notifikasi baru';
$count = isset($_POST['count']) ? (int)$_POST['count'] : 1;
$target_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$target_marketing_id = isset($_POST['marketing_id']) ? (int)$_POST['marketing_id'] : 0;
$url = $_POST['url'] ?? '/admin/login.php';

// Server Key dari Firebase Console (Project Settings > Cloud Messaging)
$serverKey = 'AAAA_PLePJ0:APA91bFQ8XyX8z5Q3w7R9tY2uI4oP6aSsD8fG'; // GANTI DENGAN SERVER KEY ANDA

$conn = getDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

try {
    // Ambil token dari database
    $sql = "SELECT token FROM fcm_tokens WHERE 1=1";
    $params = [];
    
    if ($target_user_id > 0) {
        $sql .= " AND user_id = ?";
        $params[] = $target_user_id;
    }
    
    if ($target_marketing_id > 0) {
        $sql .= " AND marketing_id = ?";
        $params[] = $target_marketing_id;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tokens)) {
        echo json_encode([
            'success' => false,
            'message' => 'No FCM tokens found'
        ]);
        exit;
    }
    
    $success_count = 0;
    $fail_count = 0;
    
    // Kirim ke setiap token
    foreach ($tokens as $token) {
        $payload = [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => '/assets/images/icon-192.png',
                'badge' => '/assets/images/icon-72.png',
                'sound' => 'default',
                'vibrate' => [500, 250, 500, 250, 500],
                'priority' => 'high',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ],
            'data' => [
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'count' => (string)$count,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'icon' => '/assets/images/icon-192.png'
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                    'vibrate' => [500, 250, 500, 250, 500],
                    'priority' => 'high',
                    'visibility' => 'public'
                ]
            ],
            'webpush' => [
                'headers' => [
                    'Urgency' => 'high'
                ],
                'notification' => [
                    'vibrate' => [500, 250, 500, 250, 500],
                    'requireInteraction' => true,
                    'silent' => false
                ]
            ]
        ];
        
        $headers = [
            'Authorization: key=' . $serverKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Parse response
        $result = json_decode($response, true);
        if ($http_code == 200 && isset($result['success']) && $result['success'] == 1) {
            $success_count++;
        } else {
            $fail_count++;
            error_log("FCM send failed: $response");
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Notifikasi terkirim ke $success_count perangkat",
        'data' => [
            'sent' => $success_count,
            'failed' => $fail_count,
            'total' => count($tokens)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>