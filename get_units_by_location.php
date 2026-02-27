<?php
/**
 * GET_UNITS_BY_LOCATION.PHP - LEADENGINE API
 * Version: 1.0.0 - Mengambil unit berdasarkan lokasi
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/get_units_by_location.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

require_once __DIR__ . '/config.php';

// Buat folder logs
$log_dir = __DIR__ . '/../../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/get_units_by_location.log';

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

writeLog("========== GET_UNITS_BY_LOCATION DIPANGGIL ==========");

$conn = getDB();
if (!$conn) {
    writeLog("ERROR: Database connection failed");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Ambil parameter
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$program = isset($_GET['program']) ? trim($_GET['program']) : '';

writeLog("Location: $location, Program: $program");

if (empty($location)) {
    writeLog("ERROR: Location parameter required");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter location diperlukan']);
    exit();
}

try {
    // Cari developer berdasarkan lokasi
    $stmt = $conn->prepare("
        SELECT id, username, nama_lengkap, distribution_mode 
        FROM users 
        WHERE role = 'developer' AND is_active = 1 
        AND (location_access LIKE ? OR location_access = ?)
        LIMIT 1
    ");
    $search_term = '%' . $location . '%';
    $stmt->execute([$search_term, $location]);
    $developer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$developer) {
        writeLog("WARNING: Developer not found for location: $location");
    }
    
    // Ambil unit berdasarkan lokasi
    $sql = "
        SELECT 
            u.id,
            u.nomor_unit,
            u.tipe_unit,
            u.program,
            u.harga,
            u.harga_booking,
            u.status,
            u.cluster_id,
            u.block_id,
            c.nama_cluster,
            b.nama_block
        FROM units u
        JOIN blocks b ON u.block_id = b.id
        JOIN clusters c ON u.cluster_id = c.id
        WHERE u.status = 'AVAILABLE'
        AND c.developer_id IN (
            SELECT id FROM users WHERE role = 'developer' 
            AND (location_access LIKE ? OR location_access = ?)
        )
    ";
    
    $params = ['%' . $location . '%', $location];
    
    if (!empty($program) && in_array($program, ['Subsidi', 'Komersil'])) {
        $sql .= " AND u.program = ?";
        $params[] = $program;
    }
    
    $sql .= " ORDER BY c.nama_cluster, b.nama_block, u.nomor_unit";
    
    writeLog("SQL Query: " . $sql);
    writeLog("Params: " . json_encode($params));
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    writeLog("Found " . count($units) . " units");
    
    // Format data untuk frontend
    foreach ($units as &$unit) {
        $unit['harga_formatted'] = $unit['harga'] > 0 
            ? 'Rp ' . number_format($unit['harga'], 0, ',', '.') 
            : 'Hubungi marketing';
        $unit['harga_booking_formatted'] = $unit['harga_booking'] > 0 
            ? 'Rp ' . number_format($unit['harga_booking'], 0, ',', '.') 
            : 'Gratis';
        $unit['display_name'] = $unit['nomor_unit'] . ' - ' . $unit['tipe_unit'] . ' (' . $unit['program'] . ')';
        $unit['full_display'] = $unit['nama_cluster'] . ' - Block ' . $unit['nama_block'] . ' - ' . $unit['nomor_unit'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $units,
        'total' => count($units),
        'location' => $location,
        'program' => $program ?: 'all',
        'developer' => $developer ?: null,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    writeLog("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

writeLog("========== GET_UNITS_BY_LOCATION SELESAI ==========\n");
?>