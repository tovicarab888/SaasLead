<?php
/**
 * GET_LOCATIONS.PHP - LEADENGINE API
 * Version: 2.0.0 - FIXED: Added caching, rate limiting, better error handling
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/get_locations.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: public, max-age=300'); // Cache 5 menit

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

// Cek autentikasi (opsional - lokasi bisa public)
$is_public = isset($_GET['public']) && $_GET['public'] == '1';

if (!$is_public && !checkAuth() && !isMarketing()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Rate limiting
$client_ip = getClientIP();
$rate_limit_key = $is_public ? 'locations_public_' . $client_ip : 'locations_' . $client_ip;
if (!checkRateLimit($rate_limit_key, 20, 60)) { // 20 requests per minute
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']);
    exit();
}

// Cek cache di session untuk akses cepat
$cache_key = 'locations_data_v2';
if (isset($_SESSION[$cache_key]) && (time() - $_SESSION[$cache_key . '_time'] < 300)) {
    echo json_encode([
        'success' => true,
        'locations' => $_SESSION[$cache_key],
        'total' => count($_SESSION[$cache_key]),
        'cached' => true,
        'timestamp' => time()
    ]);
    exit();
}

$conn = getDB();
if (!$conn) {
    // Jika database error, return fallback data
    $fallback = [
        ['location_key' => 'kertamulya', 'display_name' => 'Kertamulya Residence', 'icon' => '🏡', 'city' => 'Kuningan', 'description' => 'Perumahan modern di pusat kota', 'subsidi_units_array' => ['Scandinavia 30/60', 'Scandinavia 36/60'], 'komersil_units_array' => ['Scandinavia 45/72'], 'is_active' => 1],
        ['location_key' => 'kertayasa', 'display_name' => 'Madani Regency', 'icon' => '🌳', 'city' => 'Kuningan', 'description' => 'Kawasan hijau asri', 'subsidi_units_array' => ['Modern 30/60'], 'komersil_units_array' => ['Modern 45/72', 'Modern 70/120'], 'is_active' => 1],
        ['location_key' => 'ciperna', 'display_name' => 'Ciperna Valley', 'icon' => '🏘️', 'city' => 'Kuningan', 'description' => 'View pegunungan', 'subsidi_units_array' => ['Valley 30/60'], 'komersil_units_array' => ['Valley 45/72'], 'is_active' => 1],
        ['location_key' => 'windusari', 'display_name' => 'Windusari Hills', 'icon' => '🌄', 'city' => 'Kuningan', 'description' => 'Sunset view premium', 'subsidi_units_array' => [], 'komersil_units_array' => ['Hills 50/60', 'Hills 70/120'], 'is_active' => 1]
    ];
    
    echo json_encode([
        'success' => true,
        'locations' => $fallback,
        'total' => count($fallback),
        'note' => 'Using fallback data (database unavailable)'
    ]);
    exit();
}

try {
    // Query untuk mengambil lokasi
    $sql = "SELECT 
                location_key, 
                display_name, 
                icon, 
                city, 
                description,
                subsidi_units,
                komersil_units,
                sort_order,
                is_active,
                latitude,
                longitude,
                created_at,
                updated_at
            FROM locations";
    
    $params = [];
    
    // Filter hanya yang aktif kecuali admin
    if (!$is_public && in_array(getCurrentRole(), ['admin', 'manager'])) {
        // Admin/manager bisa lihat semua
    } else {
        $sql .= " WHERE is_active = 1";
    }
    
    $sql .= " ORDER BY sort_order ASC, display_name ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse unit menjadi array
    foreach ($locations as &$loc) {
        $loc['subsidi_units_array'] = !empty($loc['subsidi_units']) ? array_map('trim', explode(',', $loc['subsidi_units'])) : [];
        $loc['komersil_units_array'] = !empty($loc['komersil_units']) ? array_map('trim', explode(',', $loc['komersil_units'])) : [];
        
        // Format untuk frontend
        $loc['has_subsidi'] = !empty($loc['subsidi_units_array']);
        $loc['has_komersil'] = !empty($loc['komersil_units_array']);
        
        // Hapus field mentah untuk response public
        if ($is_public) {
            unset($loc['subsidi_units']);
            unset($loc['komersil_units']);
            unset($loc['created_at']);
            unset($loc['updated_at']);
        }
    }
    
    // Simpan ke cache session
    $_SESSION[$cache_key] = $locations;
    $_SESSION[$cache_key . '_time'] = time();
    
    // Log untuk debugging
    logSystem("Locations fetched", ['count' => count($locations), 'public' => $is_public], 'INFO', 'locations.log');
    
    echo json_encode([
        'success' => true,
        'locations' => $locations,
        'total' => count($locations),
        'cached' => false,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_locations: " . $e->getMessage());
    
    // Return fallback data
    $fallback = [
        ['location_key' => 'kertamulya', 'display_name' => 'Kertamulya Residence', 'icon' => '🏡', 'city' => 'Kuningan', 'description' => 'Perumahan modern di pusat kota', 'subsidi_units_array' => ['Scandinavia 30/60', 'Scandinavia 36/60'], 'komersil_units_array' => ['Scandinavia 45/72']],
        ['location_key' => 'kertayasa', 'display_name' => 'Madani Regency', 'icon' => '🌳', 'city' => 'Kuningan', 'description' => 'Kawasan hijau asri', 'subsidi_units_array' => ['Modern 30/60'], 'komersil_units_array' => ['Modern 45/72', 'Modern 70/120']],
        ['location_key' => 'ciperna', 'display_name' => 'Ciperna Valley', 'icon' => '🏘️', 'city' => 'Kuningan', 'description' => 'View pegunungan', 'subsidi_units_array' => ['Valley 30/60'], 'komersil_units_array' => ['Valley 45/72']],
        ['location_key' => 'windusari', 'display_name' => 'Windusari Hills', 'icon' => '🌄', 'city' => 'Kuningan', 'description' => 'Sunset view premium', 'subsidi_units_array' => [], 'komersil_units_array' => ['Hills 50/60', 'Hills 70/120']]
    ];
    
    echo json_encode([
        'success' => true,
        'locations' => $fallback,
        'total' => count($fallback),
        'error' => $e->getMessage(),
        'note' => 'Using fallback data due to database error'
    ]);
}
?>