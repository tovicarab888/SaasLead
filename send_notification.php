<?php
/**
 * SEND_NOTIFICATION.PHP - TAUFIKMARIE.COM
 * Version: 6.2.0 - FIXED: Hanya 1 notifikasi per batch, bukan per user
 */

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/push_notifications.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

require_once __DIR__ . '/config.php';

$log_file = __DIR__ . '/../logs/push_notifications.log';

function pushLog($message, $data = null) {
    global $log_file;
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data) {
        if (is_array($data) || is_object($data)) {
            $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $log .= $data . "\n";
        }
    }
    $log .= str_repeat("-", 50) . "\n";
    file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

pushLog("========== SEND NOTIFICATION DIPANGGIL ==========");

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

pushLog("Input data:", $input);

$title = $input['title'] ?? 'Lead Engine';
$body = $input['body'] ?? 'Ada notifikasi baru';
$url = $input['url'] ?? '/admin/splash/';
$role = $input['role'] ?? 'all';
$count = isset($input['count']) ? (int)$input['count'] : 1;
$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$developerId = isset($input['developer_id']) ? (int)$input['developer_id'] : 0;
$marketingId = isset($input['marketing_id']) ? (int)$input['marketing_id'] : 0;
$icon = $input['icon'] ?? '/assets/images/icon-192.png';
$badge = $input['badge'] ?? '/assets/images/icon-72.png';
$sound = $input['sound'] ?? '/assets/sounds/notification.mp3';
$vibrate = $input['vibrate'] ?? [500, 250, 500, 250, 500];
$timestamp = time();

// CEK APAKAH SUDAH ADA NOTIFIKASI DENGAN TITLE YANG SAMA DALAM 5 DETIK TERAKHIR
$cache_file = __DIR__ . '/../logs/last_notification.txt';
$last_notif_time = 0;
$last_notif_title = '';

if (file_exists($cache_file)) {
    $last_data = file_get_contents($cache_file);
    $parts = explode('||', $last_data);
    if (count($parts) == 2) {
        $last_notif_time = (int)$parts[0];
        $last_notif_title = $parts[1];
    }
}

$current_time = time();
$time_diff = $current_time - $last_notif_time;

// JIKA NOTIFIKASI SAMA DALAM 5 DETIK, SKIP
if ($time_diff < 5 && $last_notif_title === $title) {
    pushLog("⏭️ Notifikasi duplikat dalam 5 detik, di-SKIP: $title");
    echo json_encode([
        'success' => true,
        'message' => 'Notification skipped (duplicate)',
        'data' => [
            'skipped' => true,
            'reason' => 'duplicate_in_5_seconds'
        ]
    ]);
    exit();
}

// SIMPAN NOTIFIKASI TERAKHIR
file_put_contents($cache_file, $current_time . '||' . $title);

// Ambil unread count dari database
$conn = getDB();
$unread_count = 0;
$fresh_leads = [];

if ($conn) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
        $stmt->execute();
        $unread_count = (int)($stmt->fetch()['count'] ?? 0);
        
        $stmt = $conn->prepare("
            SELECT l.id, l.first_name, l.last_name, l.location_key, l.assigned_type, 
                   l.assigned_marketing_team_id, l.ditugaskan_ke as developer_id
            FROM leads l 
            WHERE l.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY l.id DESC
        ");
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            $fresh_leads[] = [
                'id' => $row['id'],
                'name' => trim($row['first_name'] . ' ' . ($row['last_name'] ?? '')),
                'location' => $row['location_key'],
                'type' => $row['assigned_type'] ?? 'external',
                'marketing_id' => $row['assigned_marketing_team_id'] ?? 0,
                'developer_id' => $row['developer_id'] ?? 0
            ];
        }
        
        pushLog("Unread count: $unread_count, Fresh leads: " . count($fresh_leads));
        
    } catch (Exception $e) {
        pushLog("DB Error: " . $e->getMessage());
    }
}

// KIRIM KE FCM HANYA 1 KALI (bukan per user)
if (function_exists('sendToFCM')) {
    sendToFCM([
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'count' => $unread_count,
        'icon' => $icon,
        'badge' => $badge,
        'sound' => $sound
    ]);
}

$response = [
    'success' => true,
    'message' => 'Notification processed',
    'data' => [
        'unread_count' => $unread_count,
        'fresh_leads' => $fresh_leads,
        'role' => $role,
        'user_id' => $userId,
        'developer_id' => $developerId,
        'marketing_id' => $marketingId,
        'timestamp' => $timestamp,
        'sound_played' => ($time_diff >= 5 || $last_notif_title !== $title)
    ]
];

pushLog("Response:", $response);

while (ob_get_level()) {
    ob_end_clean();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();

// Fungsi helper untuk kirim ke FCM (panggil sekali)
function sendToFCM($data) {
    $payload = [
        'to' => '/topics/all', // Kirim ke topic, bukan per token
        'notification' => [
            'title' => $data['title'],
            'body' => $data['body'],
            'icon' => $data['icon'],
            'badge' => $data['badge'],
            'sound' => 'default',
            'vibrate' => [500, 250, 500, 250, 500],
            'priority' => 'high',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        ],
        'data' => [
            'title' => $data['title'],
            'body' => $data['body'],
            'url' => $data['url'],
            'count' => (string)$data['count'],
            'sound' => $data['sound'],
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        ],
        'android' => [
            'priority' => 'high',
            'notification' => [
                'sound' => 'default',
                'vibrate' => [500, 250, 500, 250, 500],
                'priority' => 'high',
                'visibility' => 'public',
                'channel_id' => 'lead_engine_channel' // Channel ID untuk Android
            ]
        ]
    ];
    
    $serverKey = 'AAAA_PLePJ0:APA91bFQ8XyX8z5Q3w7R9tY2uI4oP6aSsD8fG';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    pushLog("FCM Response: $http_code", $response);
}
?>