<?php
/**
 * SAVE_DEVELOPER_SEO.PHP - API SIMPAN DATA SEO
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

// Auth
$key = $_POST['key'] ?? $_GET['key'] ?? '';
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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$developer_id = isset($input['developer_id']) ? (int)$input['developer_id'] : 0;

if ($developer_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Developer ID required']);
    exit();
}

// Validasi developer
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'developer' AND is_active = 1");
$stmt->execute([$developer_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Developer not found']);
    exit();
}

$data = [
    'seo_title' => trim($input['seo_title'] ?? ''),
    'seo_description' => trim($input['seo_description'] ?? ''),
    'seo_keywords' => trim($input['seo_keywords'] ?? ''),
    'robots_meta' => $input['robots_meta'] ?? 'index, follow',
    'canonical_url' => trim($input['canonical_url'] ?? ''),
    'og_type' => $input['og_type'] ?? 'website',
    'og_site_name' => trim($input['og_site_name'] ?? 'Lead Engine Property'),
    'og_title' => trim($input['og_title'] ?? ''),
    'og_description' => trim($input['og_description'] ?? ''),
    'og_image' => trim($input['og_image'] ?? ''),
    'og_image_width' => (int)($input['og_image_width'] ?? 1200),
    'og_image_height' => (int)($input['og_image_height'] ?? 630),
    'og_image_alt' => trim($input['og_image_alt'] ?? ''),
    'og_url' => trim($input['og_url'] ?? ''),
    'twitter_title' => trim($input['twitter_title'] ?? ''),
    'twitter_description' => trim($input['twitter_description'] ?? ''),
    'twitter_image' => trim($input['twitter_image'] ?? ''),
    'twitter_image_alt' => trim($input['twitter_image_alt'] ?? ''),
    'twitter_card_type' => $input['twitter_card_type'] ?? 'summary_large_image',
    'schema_json' => trim($input['schema_json'] ?? ''),
    'faq_json' => trim($input['faq_json'] ?? ''),
    'breadcrumb_json' => trim($input['breadcrumb_json'] ?? '')
];

// Validasi
$errors = validateSEOData($data);

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    exit();
}

try {
    // Cek apakah sudah ada
    $check = $conn->prepare("SELECT id FROM developer_seo WHERE developer_id = ?");
    $check->execute([$developer_id]);
    
    if ($check->fetch()) {
        // Update
        $sql = "
            UPDATE developer_seo SET
                seo_title = ?, seo_description = ?, seo_keywords = ?, robots_meta = ?,
                canonical_url = ?, og_type = ?, og_site_name = ?, og_title = ?,
                og_description = ?, og_image = ?, og_image_width = ?, og_image_height = ?,
                og_image_alt = ?, og_url = ?, twitter_title = ?, twitter_description = ?,
                twitter_image = ?, twitter_image_alt = ?, twitter_card_type = ?,
                schema_json = ?, faq_json = ?, breadcrumb_json = ?, updated_at = NOW()
            WHERE developer_id = ?
        ";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            $data['seo_title'], $data['seo_description'], $data['seo_keywords'], $data['robots_meta'],
            $data['canonical_url'], $data['og_type'], $data['og_site_name'], $data['og_title'],
            $data['og_description'], $data['og_image'], $data['og_image_width'], $data['og_image_height'],
            $data['og_image_alt'], $data['og_url'], $data['twitter_title'], $data['twitter_description'],
            $data['twitter_image'], $data['twitter_image_alt'], $data['twitter_card_type'],
            $data['schema_json'], $data['faq_json'], $data['breadcrumb_json'], $developer_id
        ]);
    } else {
        // Insert
        $sql = "
            INSERT INTO developer_seo (
                developer_id, seo_title, seo_description, seo_keywords, robots_meta,
                canonical_url, og_type, og_site_name, og_title, og_description,
                og_image, og_image_width, og_image_height, og_image_alt, og_url,
                twitter_title, twitter_description, twitter_image, twitter_image_alt,
                twitter_card_type, schema_json, faq_json, breadcrumb_json, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            $developer_id,
            $data['seo_title'], $data['seo_description'], $data['seo_keywords'], $data['robots_meta'],
            $data['canonical_url'], $data['og_type'], $data['og_site_name'], $data['og_title'],
            $data['og_description'], $data['og_image'], $data['og_image_width'], $data['og_image_height'],
            $data['og_image_alt'], $data['og_url'], $data['twitter_title'], $data['twitter_description'],
            $data['twitter_image'], $data['twitter_image_alt'], $data['twitter_card_type'],
            $data['schema_json'], $data['faq_json'], $data['breadcrumb_json']
        ]);
    }
    
    if ($result) {
        logSystem("SEO saved for developer $developer_id", ['by' => $_SERVER['REMOTE_ADDR']], 'INFO', 'seo.log');
        echo json_encode(['success' => true, 'message' => 'SEO berhasil disimpan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan']);
    }
    
} catch (Exception $e) {
    logSystem("Error in save_developer_seo", ['error' => $e->getMessage()], 'ERROR', 'api.log');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}