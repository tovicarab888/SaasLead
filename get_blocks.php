<?php
/**
 * GET_BLOCKS.PHP - LEADENGINE API
 * Version: 2.1.0 - FIXED: Error 500, gunakan parameter array
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_blocks.log');

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

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';

if (!checkAuth() && !isMarketing()) {
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

$cluster_id = isset($_GET['cluster_id']) ? (int)$_GET['cluster_id'] : 0;
$program = isset($_GET['program']) ? trim($_GET['program']) : '';
$with_units = isset($_GET['with_units']) ? (int)$_GET['with_units'] : 0;

if ($cluster_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cluster ID tidak valid']);
    exit();
}

if (!empty($program) && !in_array($program, ['Subsidi', 'Komersil'])) {
    $program = '';
}

try {
    // 🔥 PERBAIKAN: Gunakan parameter array, bukan bindParam
    $params = [$cluster_id];
    
    if ($with_units) {
        $sql = "
            SELECT 
                b.id,
                b.nama_block,
                b.created_at,
                b.updated_at,
                COUNT(DISTINCT u.id) as total_units,
                COUNT(DISTINCT CASE WHEN u.status = 'AVAILABLE' THEN u.id END) as available_units,
                COUNT(DISTINCT CASE WHEN u.status = 'BOOKED' THEN u.id END) as booked_units,
                COUNT(DISTINCT CASE WHEN u.status = 'SOLD' THEN u.id END) as sold_units,
                MIN(u.harga) as min_harga,
                MAX(u.harga) as max_harga,
                AVG(u.harga) as avg_harga
            FROM blocks b
            LEFT JOIN units u ON b.id = u.block_id
            WHERE b.cluster_id = ?
        ";
        
        if (!empty($program)) {
            $sql .= " AND u.program = ?";
            $params[] = $program;
        }
        
        $sql .= " GROUP BY b.id ORDER BY b.nama_block ASC";
        
    } else {
        $sql = "
            SELECT 
                b.id,
                b.nama_block,
                b.created_at,
                b.updated_at,
                (SELECT COUNT(*) FROM units WHERE block_id = b.id) as total_units,
                (SELECT COUNT(*) FROM units WHERE block_id = b.id AND status = 'AVAILABLE') as available_units,
                (SELECT COUNT(*) FROM units WHERE block_id = b.id AND status = 'BOOKED') as booked_units,
                (SELECT COUNT(*) FROM units WHERE block_id = b.id AND status = 'SOLD') as sold_units
            FROM blocks b
            WHERE b.cluster_id = ?
        ";
        
        if (!empty($program)) {
            $sql = "
                SELECT 
                    b.id,
                    b.nama_block,
                    b.created_at,
                    b.updated_at,
                    (SELECT COUNT(*) FROM units WHERE block_id = b.id AND program = ?) as total_units,
                    (SELECT COUNT(*) FROM units WHERE block_id = b.id AND program = ? AND status = 'AVAILABLE') as available_units,
                    (SELECT COUNT(*) FROM units WHERE block_id = b.id AND program = ? AND status = 'BOOKED') as booked_units,
                    (SELECT COUNT(*) FROM units WHERE block_id = b.id AND program = ? AND status = 'SOLD') as sold_units
                FROM blocks b
                WHERE b.cluster_id = ?
            ";
            $params = [$program, $program, $program, $program, $cluster_id];
        }
        
        $sql .= " ORDER BY b.nama_block ASC";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($blocks as &$block) {
        $block['total_units'] = (int)$block['total_units'];
        $block['available_units'] = (int)$block['available_units'];
        $block['booked_units'] = (int)$block['booked_units'];
        $block['sold_units'] = (int)$block['sold_units'];
        $block['has_available'] = $block['available_units'] > 0;
        $block['sold_percentage'] = $block['total_units'] > 0 ? round($block['sold_units'] / $block['total_units'] * 100, 1) : 0;
        
        if (isset($block['min_harga'])) {
            $block['min_harga_formatted'] = 'Rp ' . number_format($block['min_harga'], 0, ',', '.');
            $block['max_harga_formatted'] = 'Rp ' . number_format($block['max_harga'], 0, ',', '.');
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $blocks,
        'stats' => [
            'total_blocks' => count($blocks),
            'total_units' => array_sum(array_column($blocks, 'total_units')),
            'available' => array_sum(array_column($blocks, 'available_units')),
            'booked' => array_sum(array_column($blocks, 'booked_units')),
            'sold' => array_sum(array_column($blocks, 'sold_units'))
        ],
        'cluster_id' => $cluster_id,
        'program' => $program ?: 'all',
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_blocks: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>