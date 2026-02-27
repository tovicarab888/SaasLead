<?php
/**
 * GEOCODE_PROXY.PHP - LEADENGINE
 * Version: 2.0.0 - FIXED: File cache, retry mechanism, rate limiting
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/geocode_proxy.log');

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

// Buat folder cache
$cache_dir = __DIR__ . '/../../cache/geocode';
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

$log_dir = dirname(__DIR__, 2) . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/geocode_proxy.log';

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

writeLog("========== GEOCODE PROXY DIPANGGIL ==========");

// Cek autentikasi (hanya user yang login)
if (!checkAuth() && !isMarketing()) {
    writeLog("ERROR: Unauthorized");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Rate limiting
$client_ip = getClientIP();
if (!checkRateLimit('geocode_' . $client_ip, 20, 60)) { // 20 requests per minute
    writeLog("Rate limit exceeded for IP: $client_ip");
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.'
    ]);
    exit();
}

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : 0;

writeLog("Koordinat: $lat, $lng");

if ($lat == 0 || $lng == 0) {
    writeLog("ERROR: Koordinat tidak valid");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Koordinat tidak valid']);
    exit();
}

// Buat cache key (round to 4 decimal places ~ 11 meters)
$cache_key = round($lat, 4) . '_' . round($lng, 4);
$cache_file = $cache_dir . '/' . md5($cache_key) . '.json';

// Cek cache file (berlaku 30 hari)
if (file_exists($cache_file) && (time() - filemtime($cache_file) < 2592000)) {
    $cached = json_decode(file_get_contents($cache_file), true);
    writeLog("Menggunakan file cache: " . $cached['address']);
    
    echo json_encode([
        'success' => true,
        'address' => $cached['address'],
        'cached' => true,
        'source' => $cached['source'] ?? 'cache',
        'lat' => $lat,
        'lng' => $lng,
        'expires_in' => 2592000 - (time() - filemtime($cache_file))
    ]);
    exit();
}

// Cek cache di session (jangka pendek)
$session_key = 'geocode_' . $cache_key;
if (isset($_SESSION[$session_key]) && isset($_SESSION[$session_key . '_time']) && 
    (time() - $_SESSION[$session_key . '_time'] < 3600)) {
    writeLog("Menggunakan session cache");
    echo json_encode([
        'success' => true,
        'address' => $_SESSION[$session_key],
        'cached' => true,
        'source' => 'session',
        'lat' => $lat,
        'lng' => $lng
    ]);
    exit();
}

// ========== FUNGSI UNTUK MEMANGGIL API DENGAN RETRY ==========
function callGeocodingAPI($url, $timeout = 3, $retries = 2) {
    $attempt = 0;
    $last_error = '';
    
    while ($attempt <= $retries) {
        $attempt++;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'LeadEngine/2.0 (https://taufikmarie.com)',
            CURLOPT_HTTPHEADER => [
                'Accept-Language: id-ID,id;q=0.9,en;q=0.8'
            ]
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code == 200 && $response) {
            return ['success' => true, 'response' => $response, 'attempt' => $attempt];
        }
        
        $last_error = "Attempt $attempt: HTTP $http_code, Error: $error";
        
        if ($attempt <= $retries) {
            sleep(1); // Delay 1 second before retry
        }
    }
    
    return ['success' => false, 'error' => $last_error];
}

// ========== 1. COBA DENGAN NOMINATIM (OPENSTREETMAP) ==========
$nominatim_url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";
$result = callGeocodingAPI($nominatim_url, 5, 2);

if ($result['success']) {
    $data = json_decode($result['response'], true);
    
    if (isset($data['display_name'])) {
        $address = $data['display_name'];
        
        // Simpan ke cache
        $_SESSION[$session_key] = $address;
        $_SESSION[$session_key . '_time'] = time();
        file_put_contents($cache_file, json_encode([
            'address' => $address,
            'source' => 'nominatim',
            'timestamp' => time()
        ]));
        
        writeLog("Nominatim address ditemukan (attempt {$result['attempt']}): $address");
        
        echo json_encode([
            'success' => true,
            'address' => $address,
            'source' => 'nominatim',
            'attempt' => $result['attempt'],
            'lat' => $lat,
            'lng' => $lng
        ]);
        exit();
    }
}

// ========== 2. COBA DENGAN GOOGLE MAPS (JIKA ADA API KEY) ==========
// Google Maps API key - sebaiknya disimpan di config terpisah
// define('GOOGLE_MAPS_API_KEY', 'AIzaSyCg_6J7k9X8z5Q3w7R9tY2uI4oP6aSsD8fG');
if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY) {
    $google_url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key=" . GOOGLE_MAPS_API_KEY . "&language=id";
    $result = callGeocodingAPI($google_url, 3, 1);
    
    if ($result['success']) {
        $data = json_decode($result['response'], true);
        
        if ($data['status'] == 'OK' && isset($data['results'][0]['formatted_address'])) {
            $address = $data['results'][0]['formatted_address'];
            
            // Simpan ke cache
            $_SESSION[$session_key] = $address;
            $_SESSION[$session_key . '_time'] = time();
            file_put_contents($cache_file, json_encode([
                'address' => $address,
                'source' => 'google',
                'timestamp' => time()
            ]));
            
            writeLog("Google Maps address ditemukan: $address");
            
            echo json_encode([
                'success' => true,
                'address' => $address,
                'source' => 'google',
                'lat' => $lat,
                'lng' => $lng
            ]);
            exit();
        }
    }
}

// ========== 3. FORMAT SEDERHANA ==========
$simple_address = sprintf("Lokasi (%.6f, %.6f)", $lat, $lng);

// Coba dapatkan kota dari database berdasarkan proximity
$conn = getDB();
if ($conn) {
    try {
        // Cari lokasi terdekat dari tabel locations
        $stmt = $conn->prepare("
            SELECT display_name, city, 
                (6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(latitude)))) AS distance
            FROM locations
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            HAVING distance < 10
            ORDER BY distance
            LIMIT 1
        ");
        $stmt->execute([$lat, $lng, $lat]);
        $nearby = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($nearby) {
            $simple_address = "Area " . $nearby['display_name'] . ", " . $nearby['city'];
        }
    } catch (Exception $e) {
        // Abaikan error
    }
}

// Simpan fallback ke cache (short expiry)
$_SESSION[$session_key] = $simple_address;
$_SESSION[$session_key . '_time'] = time();

writeLog("Semua sumber gagal, menggunakan fallback: $simple_address");

echo json_encode([
    'success' => true,
    'address' => $simple_address,
    'source' => 'fallback',
    'lat' => $lat,
    'lng' => $lng,
    'note' => 'Could not retrieve exact address'
]);

writeLog("========== GEOCODE PROXY SELESAI ==========\n");
?>