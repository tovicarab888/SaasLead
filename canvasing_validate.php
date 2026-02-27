<?php
/**
 * CANVASING_VALIDATE.PHP - LEADENGINE
 * Version: 4.0.0 - FIXED: Tampilkan koordinat untuk debugging
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/canvasing_validate.log');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

$location_key = $input['location_key'] ?? '';
$latitude = isset($input['latitude']) ? (float)$input['latitude'] : 0;
$longitude = isset($input['longitude']) ? (float)$input['longitude'] : 0;
$developer_id = isset($input['developer_id']) ? (int)$input['developer_id'] : 0;

if (empty($location_key) || $latitude == 0 || $longitude == 0 || $developer_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit();
}

// Radius maksimal 500 meter (diperbesar untuk testing)
$MAX_RADIUS_METERS = 500;

try {
    // Cek apakah lokasi ini milik developer
    $stmt = $conn->prepare("
        SELECT location_access FROM users 
        WHERE id = ? AND role = 'developer' AND is_active = 1
    ");
    $stmt->execute([$developer_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Developer tidak valid']);
        exit();
    }
    
    $location_access = explode(',', $user['location_access'] ?? '');
    $location_access = array_map('trim', $location_access);
    
    if (!in_array($location_key, $location_access)) {
        echo json_encode([
            'success' => false, 
            'valid' => false,
            'message' => 'Anda tidak memiliki akses ke lokasi ini'
        ]);
        exit();
    }
    
    // CEK APAKAH KOLOM LATITUDE ADA
    $check_column = $conn->query("SHOW COLUMNS FROM locations LIKE 'latitude'");
    $column_exists = $check_column->rowCount() > 0;
    
    $valid = true;
    $distance = null;
    $db_lat = null;
    $db_lng = null;
    $message = 'Lokasi valid';
    
    if ($column_exists) {
        // Ambil koordinat lokasi dari database
        $stmt = $conn->prepare("SELECT latitude, longitude FROM locations WHERE location_key = ?");
        $stmt->execute([$location_key]);
        $loc = $stmt->fetch();
        
        if ($loc && $loc['latitude'] && $loc['longitude']) {
            $db_lat = (float)$loc['latitude'];
            $db_lng = (float)$loc['longitude'];
            
            // Hitung jarak menggunakan rumus Haversine
            $lat1 = deg2rad($latitude);
            $lon1 = deg2rad($longitude);
            $lat2 = deg2rad($db_lat);
            $lon2 = deg2rad($db_lng);
            
            $dlat = $lat2 - $lat1;
            $dlon = $lon2 - $lon1;
            
            $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance = 6371000 * $c; // dalam meter
            
            if ($distance > $MAX_RADIUS_METERS) {
                $valid = false;
                $message = "Anda berada " . round($distance) . "m dari lokasi target (maksimal " . $MAX_RADIUS_METERS . "m)";
            } else {
                $message = "Lokasi valid (jarak " . round($distance) . "m)";
            }
        } else {
            // Jika lokasi tidak punya koordinat, anggap valid
            $message = "Lokasi valid (tanpa batasan jarak - koordinat database kosong)";
        }
    } else {
        // Jika kolom tidak ada, anggap valid
        $message = "Lokasi valid (validasi jarak tidak tersedia - kolom tidak ada)";
    }
    
    echo json_encode([
        'success' => true,
        'valid' => $valid,
        'message' => $message,
        'distance' => $distance ? round($distance) : null,
        'max_radius' => $MAX_RADIUS_METERS,
        'location' => $location_key,
        'developer_id' => $developer_id,
        'has_coordinates' => $column_exists,
        'user_lat' => $latitude,
        'user_lng' => $longitude,
        'db_lat' => $db_lat,
        'db_lng' => $db_lng
    ]);
    
} catch (Exception $e) {
    error_log("Canvasing validate error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>