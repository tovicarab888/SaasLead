<?php
/**
 * TRIGGER_NOTIFICATION.PHP - TAUFIKMARIE.COM
 * Version: 1.0.0 - MEMICU NOTIFIKASI BERDASARKAN ROLE
 * DIPANGGIL OLEH API_MASTER.PHP SAAT LEAD BARU
 */

require_once __DIR__ . '/config.php';

function triggerRoleBasedNotification($lead_id, $lead_data, $assignment) {
    $conn = getDB();
    if (!$conn) return false;
    
    $title = "🎯 Lead Baru: " . ($lead_data['first_name'] ?? 'Customer');
    $body = $lead_data['first_name'] . ' - ' . ($lead_data['location_display'] ?? $lead_data['location_key']) . ' → ' . $assignment['assigned_marketing_name'];
    $url = '/admin/splash/';
    $count = 1;
    
    // 1. KIRIM KE ADMIN (selalu dapat)
    sendPushNotification([
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'role' => 'admin',
        'count' => $count
    ]);
    
    // 2. KIRIM KE MANAGER (selalu dapat)
    sendPushNotification([
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'role' => 'manager',
        'count' => $count
    ]);
    
    // 3. KIRIM KE DEVELOPER (berdasarkan developer_id)
    if (isset($assignment['developer_id']) && $assignment['developer_id'] > 0) {
        sendPushNotification([
            'title' => $title,
            'body' => $body . ' (Marketing: ' . $assignment['assigned_marketing_name'] . ')',
            'url' => $url,
            'role' => 'developer',
            'user_id' => $assignment['developer_id'],
            'developer_id' => $assignment['developer_id'],
            'count' => $count
        ]);
    }
    
    // 4. KIRIM KE MARKETING (yang kebagian lead)
    if (isset($assignment['assigned_marketing_team_id']) && $assignment['assigned_marketing_team_id'] > 0) {
        sendPushNotification([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'role' => 'marketing',
            'user_id' => $assignment['assigned_marketing_team_id'],
            'marketing_id' => $assignment['assigned_marketing_team_id'],
            'count' => $count
        ]);
    }
    
    return true;
}

function sendPushNotification($data) {
    $webhook_url = "https://taufikmarie.com/admin/api/send_notification.php";
    
    $ch = curl_init($webhook_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    curl_exec($ch);
    curl_close($ch);
}
?>