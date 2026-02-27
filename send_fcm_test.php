<?php
/**
 * SEND_FCM_TEST.PHP - Test notifikasi FCM
 */
require_once 'config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$title = $input['title'] ?? 'Test Firebase';
$body = $input['body'] ?? 'Notifikasi real-time test';
$count = $input['count'] ?? 3;

// Panggil fungsi send_fcm.php
$ch = curl_init('/admin/api/send_fcm.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'key' => API_KEY,
    'title' => $title,
    'body' => $body,
    'count' => $count
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>