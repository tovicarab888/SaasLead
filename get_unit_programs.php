<?php
/**
 * GET_UNIT_PROGRAMS.PHP - LEADENGINE API
 * Version: 1.0.0 - Mengambil program booking untuk unit tertentu
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/get_unit_programs.log');

while (ob_get_level()) ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (!isMarketing()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$unit_id = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;

if ($unit_id <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Unit ID diperlukan']));
}

try {
    // Ambil program yang tersedia untuk unit ini
    $sql = "
        SELECT 
            pb.*,
            CASE 
                WHEN upb.id IS NOT NULL THEN 1 
                ELSE 0 
            END as is_selected
        FROM program_booking pb
        LEFT JOIN unit_program_booking upb ON pb.id = upb.program_booking_id AND upb.unit_id = ?
        WHERE pb.is_active = 1
        ORDER BY pb.nama_program
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$unit_id]);
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($programs as &$p) {
        $p['booking_fee_formatted'] = 'Rp ' . number_format($p['booking_fee'], 0, ',', '.');
    }
    
    echo json_encode([
        'success' => true,
        'programs' => $programs,
        'total' => count($programs)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_unit_programs: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}