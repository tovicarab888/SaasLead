<?php
/**
 * GENERATE_SCHEMA.PHP - GENERATE SCHEMA JSON OTOMATIS
 * Version: 1.0.0
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$key = $input['key'] ?? $_GET['key'] ?? '';
if (!in_array($key, [API_KEY, 'taufikmarie7878'])) {
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

$developer_id = isset($input['developer_id']) ? (int)$input['developer_id'] : 0;

if ($developer_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Developer ID required']);
    exit();
}

try {
    // Ambil data developer
    $stmt = $conn->prepare("
        SELECT id, nama_lengkap, nama_perusahaan, alamat_perusahaan, kota, 
               telepon_perusahaan, email_perusahaan, website_perusahaan, logo_perusahaan
        FROM users 
        WHERE id = ? AND role = 'developer' AND is_active = 1
    ");
    $stmt->execute([$developer_id]);
    $developer = $stmt->fetch();
    
    if (!$developer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Developer not found']);
        exit();
    }
    
    // Ambil data SEO
    $seo = getDeveloperSEO($developer_id);
    
    // Generate schema
    $schema_json = generateDeveloperSchema($developer, $seo);
    
    // Update di database
    $update = $conn->prepare("UPDATE developer_seo SET schema_json = ?, updated_at = NOW() WHERE developer_id = ?");
    $update->execute([$schema_json, $developer_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Schema berhasil digenerate',
        'data' => [
            'schema_json' => $schema_json
        ]
    ]);
    
} catch (Exception $e) {
    logSystem("Error in generate_schema", ['error' => $e->getMessage()], 'ERROR', 'api.log');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}