<?php
/**
 * GET_AVAILABLE_UNITS_EXTERNAL.PHP - LEADENGINE
 * Version: 1.0.0 - Lihat unit semua developer untuk external marketing
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/get_available_units_external.log');

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';

function writeUnitLog($message, $data = null) {
    $log_dir = LOG_PATH;
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_file = $log_dir . 'get_available_units_external.log';
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data !== null) {
        $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeUnitLog("===== GET AVAILABLE UNITS EXTERNAL =====");

// Cek session marketing
if (!isMarketing()) {
    writeUnitLog("ERROR: Bukan marketing");
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized - Hanya untuk marketing']));
}

$conn = getDB();
if (!$conn) {
    writeUnitLog("ERROR: Koneksi database gagal");
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$marketing_id = $_SESSION['marketing_id'];

// Rate limiting
$ip = getClientIP();
$rate_key = 'get_units_external_' . $ip;
if (!checkRateLimit($rate_key, 30, 60, 300)) {
    writeUnitLog("ERROR: Rate limit exceeded for IP: $ip");
    http_response_code(429);
    die(json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']));
}

// Ambil parameter filter
$developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
$cluster_id = isset($_GET['cluster_id']) ? (int)$_GET['cluster_id'] : 0;
$block_id = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
$program = isset($_GET['program']) ? trim($_GET['program']) : '';
$tipe_unit = isset($_GET['tipe_unit']) ? trim($_GET['tipe_unit']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

writeUnitLog("Marketing ID: $marketing_id, Developer ID: $developer_id");

try {
    // CEK DEVELOPER YANG DIIZINKAN UNTUK MARKETING INI
    require_once __DIR__ . '/can_external_access_developer.php';
    $access = canExternalAccessDeveloper($marketing_id);

    $allowed_developers = $access['allowed_developers'];

    if (empty($allowed_developers)) {
        writeUnitLog("INFO: Marketing $marketing_id belum punya akses ke developer manapun");
        echo json_encode([
            'success' => true,
            'data' => [],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => 1,
                'total_records' => 0,
                'limit' => $limit
            ],
            'allowed_developers' => [],
            'message' => 'Anda belum memiliki akses ke developer manapun'
        ]);
        exit();
    }

    // Jika developer_id diberikan, pastikan termasuk dalam allowed
    if ($developer_id > 0 && !in_array($developer_id, $allowed_developers)) {
        writeUnitLog("WARNING: Marketing $marketing_id mencoba akses developer $developer_id yang tidak diizinkan");
        die(json_encode([
            'success' => false,
            'message' => 'Anda tidak memiliki akses ke developer ini',
            'allowed_developers' => $allowed_developers
        ]));
    }

    // BUILD QUERY
    $sql = "
        SELECT
            u.id,
            u.nomor_unit,
            u.tipe_unit,
            u.program,
            u.harga,
            u.harga_booking,
            u.komisi_eksternal_persen,
            u.komisi_eksternal_rupiah,
            u.komisi_internal_rupiah,
            u.status,
            u.created_at,
            u.updated_at,
            b.id as block_id,
            b.nama_block,
            c.id as cluster_id,
            c.nama_cluster,
            dev.id as developer_id,
            dev.nama_lengkap as developer_name,
            dev.nama_perusahaan,
            dev.logo_perusahaan,
            loc.display_name as location_display,
            loc.icon,
            loc.location_key,
            (SELECT COUNT(*) FROM block_biaya_tambahan WHERE block_id = b.id) as total_biaya_block
        FROM units u
        JOIN blocks b ON u.block_id = b.id
        JOIN clusters c ON b.cluster_id = c.id
        JOIN users dev ON c.developer_id = dev.id
        LEFT JOIN locations loc ON dev.location_access LIKE CONCAT('%', loc.location_key, '%')
        WHERE u.status = 'AVAILABLE'
    ";

    $params = [];

    // Filter berdasarkan developer yang diizinkan
    $placeholders = implode(',', array_fill(0, count($allowed_developers), '?'));
    $sql .= " AND c.developer_id IN ($placeholders)";
    $params = array_merge($params, $allowed_developers);

    // Filter tambahan
    if ($developer_id > 0) {
        $sql .= " AND c.developer_id = ?";
        $params[] = $developer_id;
    }

    if ($cluster_id > 0) {
        $sql .= " AND c.id = ?";
        $params[] = $cluster_id;
    }

    if ($block_id > 0) {
        $sql .= " AND b.id = ?";
        $params[] = $block_id;
    }

    if (!empty($program)) {
        $sql .= " AND u.program = ?";
        $params[] = $program;
    }

    if (!empty($tipe_unit)) {
        $sql .= " AND u.tipe_unit = ?";
        $params[] = $tipe_unit;
    }

    // Hitung total
    $count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as tmp";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Ambil data dengan limit
    $sql .= " ORDER BY c.nama_cluster, b.nama_block, u.nomor_unit LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data
    foreach ($units as &$unit) {
        $unit['harga_formatted'] = $unit['harga'] > 0 ? 'Rp ' . number_format($unit['harga'], 0, ',', '.') : 'Hubungi marketing';
        $unit['harga_booking_formatted'] = $unit['harga_booking'] > 0 ? 'Rp ' . number_format($unit['harga_booking'], 0, ',', '.') : 'Gratis';

        // Hitung komisi untuk external
        if ($unit['komisi_eksternal_rupiah'] > 0) {
            $unit['komisi_external'] = $unit['komisi_eksternal_rupiah'];
            $unit['komisi_external_formatted'] = 'Rp ' . number_format($unit['komisi_eksternal_rupiah'], 0, ',', '.');
            $unit['komisi_type'] = 'fixed';
        } else {
            $komisi = $unit['harga'] * ($unit['komisi_eksternal_persen'] / 100);
            $unit['komisi_external'] = $komisi;
            $unit['komisi_external_formatted'] = number_format($unit['komisi_eksternal_persen'], 2) . '% (Rp ' . number_format($komisi, 0, ',', '.') . ')';
            $unit['komisi_type'] = 'percent';
        }

        // Location dari developer
        if (empty($unit['location_display']) && !empty($unit['location_key'])) {
            $loc_details = getLocationDetails($unit['location_key']);
            $unit['location_display'] = $loc_details['display_name'] ?? $unit['location_key'];
            $unit['icon'] = $loc_details['icon'] ?? '🏠';
        }
    }

    writeUnitLog("SUCCESS: Ditemukan " . count($units) . " unit tersedia dari " . count($allowed_developers) . " developer");

    echo json_encode([
        'success' => true,
        'data' => $units,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'limit' => $limit,
            'offset' => $offset
        ],
        'filters' => [
            'developer_id' => $developer_id,
            'cluster_id' => $cluster_id,
            'block_id' => $block_id,
            'program' => $program,
            'tipe_unit' => $tipe_unit
        ],
        'allowed_developers' => $allowed_developers,
        'marketing_id' => $marketing_id
    ]);

} catch (Exception $e) {
    writeUnitLog("ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>