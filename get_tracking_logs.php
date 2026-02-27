<?php
/**
 * GET TRACKING LOGS - VERSI FINAL (Tanpa response_code)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://leadproperti.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Cek session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek API Key
$api_key = isset($_POST['api_key']) ? $_POST['api_key'] : (isset($_GET['api_key']) ? $_GET['api_key'] : '');
if ($api_key !== 'taufikmarie7878' && !isset($_SESSION['user_id']) && !isset($_SESSION['marketing_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

try {
    // Ambil parameter DataTables
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 25;
    
    // Hitung total records
    $total = $conn->query("SELECT COUNT(*) FROM tracking_logs")->fetchColumn();
    
    // Query TANPA response_code
    $sql = "
        SELECT 
            tl.id,
            tl.created_at,
            tl.pixel_type,
            tl.event_name,
            tl.event_id,
            tl.lead_id,
            tl.status,
            tl.response,
            COALESCE(u.nama_lengkap, 'Global') as developer_name,
            l.first_name,
            l.last_name
        FROM tracking_logs tl
        LEFT JOIN users u ON tl.developer_id = u.id
        LEFT JOIN leads l ON tl.lead_id = l.id
        ORDER BY tl.id DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$length, $start]);
    $data = $stmt->fetchAll();
    
    $output = [
        'draw' => $draw,
        'recordsTotal' => intval($total),
        'recordsFiltered' => intval($total),
        'data' => []
    ];
    
    foreach ($data as $row) {
        // Ekstrak response code dari response text jika ada
        $response_code = '-';
        if (!empty($row['response'])) {
            if (preg_match('/HTTP[^"]*"?\s*(\d{3})/', $row['response'], $matches)) {
                $response_code = $matches[1];
            } elseif (preg_match('/"code":(\d+)/', $row['response'], $matches)) {
                $response_code = $matches[1];
            }
        }
        
        $output['data'][] = [
            'id' => intval($row['id']),
            'created_at' => $row['created_at'],
            'developer_name' => $row['developer_name'],
            'pixel_type' => $row['pixel_type'],
            'event_name' => $row['event_name'] ?? '-',
            'event_id' => $row['event_id'] ?? '-',
            'lead_id' => $row['lead_id'] ? intval($row['lead_id']) : null,
            'status' => $row['status'],
            'response_code' => $response_code, // Hasil ekstraksi
            'response' => $row['response']
        ];
    }
    
    echo json_encode($output);
    
} catch (Exception $e) {
    error_log("Error in get_tracking_logs: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}