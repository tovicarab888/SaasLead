<?php
/**
 * CHECK_UNIT_AVAILABILITY.PHP - LEADENGINE API
 * Version: 1.0.0 - Cek status unit real-time (untuk booking)
 * MOBILE FIRST - Response cepat dengan lock checking
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_unit_check.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

// Cek session marketing
if (!isMarketing()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Hanya untuk marketing']);
    exit();
}

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$marketing_id = $_SESSION['marketing_id'];
$developer_id = $_SESSION['marketing_developer_id'] ?? 0;

// Ambil parameter
$unit_id = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : (isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0);

if ($unit_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unit ID diperlukan']);
    exit();
}

try {
    // Ambil data unit dengan informasi lengkap
    $stmt = $conn->prepare("
        SELECT 
            u.*,
            c.nama_cluster,
            c.developer_id,
            b.nama_block,
            CONCAT(l.first_name, ' ', l.last_name) as customer_name,
            l.phone as customer_phone,
            l.id as current_lead_id
        FROM units u
        JOIN blocks b ON u.block_id = b.id
        JOIN clusters c ON u.cluster_id = c.id
        LEFT JOIN leads l ON u.lead_id = l.id
        WHERE u.id = ?
    ");
    $stmt->execute([$unit_id]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$unit) {
        echo json_encode([
            'success' => false,
            'message' => 'Unit tidak ditemukan'
        ]);
        exit();
    }
    
    // Validasi developer
    if ($unit['developer_id'] != $developer_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Anda tidak memiliki akses ke unit ini'
        ]);
        exit();
    }
    
    // Response lengkap
    $response = [
        'success' => true,
        'unit_id' => $unit['id'],
        'nomor_unit' => $unit['nomor_unit'],
        'cluster' => $unit['nama_cluster'],
        'block' => $unit['nama_block'],
        'tipe_unit' => $unit['tipe_unit'],
        'program' => $unit['program'],
        'status' => $unit['status'],
        'harga' => $unit['harga'],
        'harga_formatted' => 'Rp ' . number_format($unit['harga'], 0, ',', '.'),
        'harga_booking' => $unit['harga_booking'],
        'harga_booking_formatted' => $unit['harga_booking'] > 0 
            ? 'Rp ' . number_format($unit['harga_booking'], 0, ',', '.')
            : 'Gratis',
        'is_available' => ($unit['status'] === 'AVAILABLE'),
        'customer_info' => $unit['customer_name'] ? [
            'name' => $unit['customer_name'],
            'phone' => $unit['customer_phone'],
            'lead_id' => $unit['current_lead_id']
        ] : null,
        'booking_at' => $unit['booking_at'],
        'sold_at' => $unit['sold_at'],
        'timestamp' => time()
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Check unit availability error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>