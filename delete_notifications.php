<?php
/**
 * DELETE_NOTIFICATIONS.PHP - LEADENGINE API
 * Version: 2.1.0 - FIXED: Bisa hapus semua notifikasi dengan mudah
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/delete_notifications.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

$log_dir = dirname(__DIR__, 2) . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/delete_notifications.log';

function writeLog($message, $data = null) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $log .= $data . "\n";
        }
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeLog("========== DELETE NOTIFICATIONS DIPANGGIL ==========");

// ========== CEK AUTHENTIKASI VIA SESSION ATAU API KEY ==========
$is_authenticated = false;
$user_id = 0;
$user_role = '';

// Cek API Key dulu (untuk testing)
$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
if ($api_key === API_KEY) {
    $is_authenticated = true;
    $user_role = 'admin'; // Anggap admin untuk API key
    writeLog("Authenticated via API Key");
}

// Cek session
if (!$is_authenticated && checkAuth()) {
    $is_authenticated = true;
    $user_id = $_SESSION['user_id'] ?? 0;
    $user_role = $_SESSION['role'] ?? '';
    writeLog("Authenticated as user: $user_id, role: $user_role");
}

if (!$is_authenticated && isMarketing()) {
    $is_authenticated = true;
    $user_id = $_SESSION['marketing_id'] ?? 0;
    $user_role = 'marketing';
    writeLog("Authenticated as marketing: $user_id");
}

if (!$is_authenticated) {
    writeLog("ERROR: Unauthorized - No valid session or API key");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Silakan login terlebih dahulu']);
    exit();
}

// ========== AMBIL INPUT ==========
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Juga cek parameter GET (untuk hapus via URL)
$ids = isset($input['ids']) ? $input['ids'] : [];
$single_id = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$delete_all = isset($input['delete_all']) ? (bool)$input['delete_all'] : (isset($_GET['delete_all']) && $_GET['delete_all'] === 'true');

// Log input
writeLog("Input received", [
    'ids' => $ids,
    'single_id' => $single_id,
    'delete_all' => $delete_all,
    'method' => $_SERVER['REQUEST_METHOD'],
    'get_params' => $_GET
]);

// Jika single_id diberikan, masukkan ke array
if ($single_id > 0) {
    $ids = [$single_id];
}

// Jika tidak ada parameter, hapus semua (untuk kemudahan testing)
if (empty($ids) && !$delete_all && isset($_GET['clear']) && $_GET['clear'] === 'all') {
    $delete_all = true;
    writeLog("Clear all via GET parameter");
}

$conn = getDB();
if (!$conn) {
    writeLog("ERROR: Database connection failed");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    $conn->beginTransaction();
    
    $deleted_count = 0;
    
    if ($delete_all) {
        // HAPUS SEMUA NOTIFIKASI
        $stmt = $conn->prepare("DELETE FROM notifications");
        $stmt->execute();
        $deleted_count = $stmt->rowCount();
        
        writeLog("Deleted all notifications: $deleted_count rows");
        
    } elseif (!empty($ids)) {
        // HAPUS BERDASARKAN ID
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $deleted_count = $stmt->rowCount();
        
        writeLog("Deleted by IDs: $deleted_count rows", $ids);
        
    } else {
        // Jika tidak ada parameter, hapus semua (default behavior)
        $stmt = $conn->prepare("DELETE FROM notifications");
        $stmt->execute();
        $deleted_count = $stmt->rowCount();
        
        writeLog("No parameters, deleted all: $deleted_count rows");
    }
    
    // Log ke system_logs
    if (function_exists('logSystem')) {
        logSystem("Notifications deleted", [
            'count' => $deleted_count,
            'delete_all' => $delete_all,
            'ids' => $ids,
            'by_user' => $user_id,
            'by_role' => $user_role
        ], 'INFO', 'notifications.log');
    }
    
    $conn->commit();
    
    // Update badge count ke 0 setelah hapus
    $response_data = [
        'success' => true,
        'deleted' => $deleted_count,
        'message' => "Berhasil menghapus $deleted_count notifikasi",
        'new_count' => 0
    ];
    
    // Coba update badge via FCM
    if (function_exists('sendToFCM')) {
        // Kirim sinyal ke semua client untuk update badge
        $fcm_payload = [
            'to' => '/topics/all',
            'data' => [
                'type' => 'badge_update',
                'count' => '0',
                'timestamp' => (string)time()
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key=AAAA_PLePJ0:APA91bFQ8XyX8z5Q3w7R9tY2uI4oP6aSsD8fG',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcm_payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_exec($ch);
        curl_close($ch);
    }
    
    echo json_encode($response_data);
    
} catch (Exception $e) {
    $conn->rollBack();
    writeLog("ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>