<?php
/**
 * GET_AVAILABLE_UNITS.PHP - LEADENGINE API
 * Version: 6.0.0 - FIXED: Komisi sinkron dengan developer_komisi_rules, program booking
 * OPTIMIZED WITH PAGINATION & DEBUG LOGGING
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/taufikma/public_html/logs/api_available_units.log');

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

$log_dir = '/home/taufikma/public_html/logs/';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$log_file = $log_dir . 'api_available_units.log';
$debug_file = $log_dir . 'available_units_debug.log';

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

function writeDebug($message, $data = null) {
    global $debug_file;
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
    @file_put_contents($debug_file, $log, FILE_APPEND | LOCK_EX);
}

writeLog("===== GET_AVAILABLE_UNITS =====");
writeDebug("===== GET_AVAILABLE_UNITS CALLED =====");

if (!isMarketing()) {
    writeLog("ERROR: Bukan marketing");
    writeDebug("ERROR: Bukan marketing");
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized - Hanya untuk marketing']));
}

$conn = getDB();
if (!$conn) {
    writeLog("ERROR: Koneksi database gagal");
    writeDebug("ERROR: Koneksi database gagal");
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$marketing_id = $_SESSION['marketing_id'];
$developer_id = $_SESSION['marketing_developer_id'] ?? 0;
$is_external = ($developer_id === 0);
$user_id = $_SESSION['user_id'] ?? 0;

writeLog("Marketing ID: $marketing_id, Developer ID: $developer_id, Is External: " . ($is_external ? 'YES' : 'NO'));
writeDebug("Marketing ID: $marketing_id, Developer ID: $developer_id, Is External: " . ($is_external ? 'YES' : 'NO'));

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

$cluster_id = isset($_GET['cluster_id']) ? (int)$_GET['cluster_id'] : 0;
$block_id = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
$program = isset($_GET['program']) ? trim($_GET['program']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

writeLog("Filters: cluster=$cluster_id, block=$block_id, program=$program, search=$search, page=$page");
writeDebug("Filters: cluster=$cluster_id, block=$block_id, program=$program, search=$search, page=$page");

try {
    $developer_ids = [];
    
    if ($is_external) {
        // Untuk external marketing, ambil semua developer yang bisa diakses
        $stmt = $conn->prepare("
            SELECT c.developer_id 
            FROM developer_external_access dea
            JOIN marketing_external_team met ON dea.marketing_external_id = met.id
            JOIN clusters c ON c.developer_id = dea.developer_id
            WHERE met.user_id = ? AND dea.can_access = 1
            GROUP BY c.developer_id
        ");
        $stmt->execute([$user_id]);
        $developer_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        writeDebug("Developer IDs untuk external: " . json_encode($developer_ids));
        
        if (empty($developer_ids)) {
            writeLog("Tidak ada developer yang bisa diakses oleh external marketing ini");
            writeDebug("Tidak ada developer yang bisa diakses");
            
            echo json_encode([
                'success' => true,
                'data' => [],
                'flat_data' => [],
                'total_units' => 0,
                'total_all_available' => 0,
                'developer_id' => 0,
                'is_external' => true,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => 1,
                    'total_records' => 0,
                    'limit' => $limit
                ],
                'filters' => [
                    'cluster_id' => $cluster_id,
                    'block_id' => $block_id,
                    'program' => $program,
                    'search' => $search
                ],
                'timestamp' => time()
            ]);
            exit;
        }
    }
    
    // Hitung total semua unit AVAILABLE untuk developer yang relevan
    $count_all_sql = "
        SELECT COUNT(*) 
        FROM units u
        JOIN blocks b ON u.block_id = b.id
        JOIN clusters c ON b.cluster_id = c.id
        WHERE u.status = 'AVAILABLE'
    ";
    
    $count_all_params = [];
    
    if ($is_external) {
        $placeholders = implode(',', array_fill(0, count($developer_ids), '?'));
        $count_all_sql .= " AND c.developer_id IN ($placeholders)";
        $count_all_params = $developer_ids;
    } else {
        $count_all_sql .= " AND c.developer_id = ?";
        $count_all_params[] = $developer_id;
    }
    
    $count_all_stmt = $conn->prepare($count_all_sql);
    $count_all_stmt->execute($count_all_params);
    $total_all_available = $count_all_stmt->fetchColumn();
    
    writeLog("Total semua unit AVAILABLE: $total_all_available");
    writeDebug("Total semua unit AVAILABLE: $total_all_available");
    
    // Bangun query utama - AMBIL SEMUA FIELD YANG DIPERLUKAN
    $sql = "
        SELECT 
            u.id,
            u.cluster_id,
            u.block_id,
            u.nomor_unit,
            u.tipe_unit,
            u.program,
            u.luas_tanah,
            u.luas_bangunan,
            u.harga,
            u.harga_booking,
            u.status,
            u.komisi_eksternal_persen as unit_komisi_eksternal_persen,
            u.komisi_eksternal_rupiah as unit_komisi_eksternal_rupiah,
            u.komisi_internal_rupiah as unit_komisi_internal_rupiah,
            u.program_booking_id,
            u.multiple_program_booking,
            c.nama_cluster,
            c.developer_id,
            b.nama_block,
            pb.nama_program as program_booking_name,
            pb.booking_fee as program_booking_fee,
            pb.is_all_in
        FROM units u
        JOIN blocks b ON u.block_id = b.id
        JOIN clusters c ON b.cluster_id = c.id
        LEFT JOIN program_booking pb ON u.program_booking_id = pb.id
        WHERE u.status = 'AVAILABLE'
    ";
    
    $params = [];
    
    if ($is_external) {
        $placeholders = implode(',', array_fill(0, count($developer_ids), '?'));
        $sql .= " AND c.developer_id IN ($placeholders)";
        $params = $developer_ids;
    } else {
        $sql .= " AND c.developer_id = ?";
        $params[] = $developer_id;
    }
    
    if ($cluster_id > 0) {
        $sql .= " AND u.cluster_id = ?";
        $params[] = $cluster_id;
    }
    
    if ($block_id > 0) {
        $sql .= " AND u.block_id = ?";
        $params[] = $block_id;
    }
    
    if (!empty($program) && in_array($program, ['Subsidi', 'Komersil'])) {
        $sql .= " AND u.program = ?";
        $params[] = $program;
    }
    
    if (!empty($search)) {
        $sql .= " AND (u.nomor_unit LIKE ? OR u.tipe_unit LIKE ?)";
        $s = "%$search%";
        $params[] = $s;
        $params[] = $s;
    }
    
    // Hitung total records dengan filter
    $count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as tmp";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;
    
    writeLog("Total records dengan filter: $total_records, total pages: $total_pages");
    writeDebug("Total records dengan filter: $total_records, total pages: $total_pages");
    
    // Order by
    $sql .= " ORDER BY c.nama_cluster, b.nama_block, 
        CASE 
            WHEN u.nomor_unit REGEXP '^[0-9]+$' THEN LPAD(u.nomor_unit, 10, '0')
            ELSE u.nomor_unit 
        END
    ";
    
    // Pagination
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    writeDebug("SQL Query: " . $sql);
    writeDebug("Params: " . json_encode($params));
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    writeLog("Ditemukan " . count($units) . " units dalam halaman ini");
    writeDebug("Ditemukan " . count($units) . " units dalam halaman ini");
    
    if (count($units) === 0 && $total_all_available > 0) {
        writeLog("PERINGATAN: Tidak ada unit dengan filter saat ini, tapi ada $total_all_available unit total");
        writeDebug("PERINGATAN: Tidak ada unit dengan filter saat ini, tapi ada $total_all_available unit total");
    }
    
    // ===== AMBIL SEMUA KOMISI RULES UNTUK SEMUA DEVELOPER =====
    $komisi_rules_cache = [];
    try {
        // Ambil semua komisi rules untuk developer yang relevan
        $all_developer_ids = $is_external ? $developer_ids : [$developer_id];
        $dev_placeholders = implode(',', array_fill(0, count($all_developer_ids), '?'));
        
        $komisi_sql = "
            SELECT kr.*, mt.type_name, kr.developer_id
            FROM komisi_rules kr
            JOIN marketing_types mt ON kr.marketing_type_id = mt.id
            WHERE kr.developer_id IN ($dev_placeholders)
        ";
        $komisi_stmt = $conn->prepare($komisi_sql);
        $komisi_stmt->execute($all_developer_ids);
        $all_rules = $komisi_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by developer_id
        foreach ($all_rules as $rule) {
            $dev_id = $rule['developer_id'];
            if (!isset($komisi_rules_cache[$dev_id])) {
                $komisi_rules_cache[$dev_id] = [
                    'sales_inhouse' => 1000000, // Default
                    'sales_canvasing' => 3.00    // Default
                ];
            }
            if ($rule['type_name'] === 'sales_inhouse') {
                $komisi_rules_cache[$dev_id]['sales_inhouse'] = (float)$rule['commission_value'];
            } elseif ($rule['type_name'] === 'sales_canvasing') {
                $komisi_rules_cache[$dev_id]['sales_canvasing'] = (float)$rule['commission_value'];
            }
        }
        
        writeDebug("Komisi rules cache: " . json_encode($komisi_rules_cache));
    } catch (Exception $e) {
        writeDebug("Error loading komisi rules: " . $e->getMessage());
    }
    
    // FORMAT UNTUK RESPONSE
    $flat_data = []; // Untuk akses mudah di frontend
    
    foreach ($units as &$unit) {
        $dev_id = $unit['developer_id'];
        
        // Ambil komisi rules untuk developer ini
        $komisi_internal = 1000000; // Default
        $komisi_eksternal_persen = 3.00; // Default
        
        if (isset($komisi_rules_cache[$dev_id])) {
            $komisi_internal = $komisi_rules_cache[$dev_id]['sales_inhouse'];
            $komisi_eksternal_persen = $komisi_rules_cache[$dev_id]['sales_canvasing'];
        }
        
        // Override dengan nilai dari unit jika ada
        $unit_komisi_internal = !empty($unit['unit_komisi_internal_rupiah']) ? (float)$unit['unit_komisi_internal_rupiah'] : $komisi_internal;
        $unit_komisi_eksternal_persen = !empty($unit['unit_komisi_eksternal_persen']) ? (float)$unit['unit_komisi_eksternal_persen'] : $komisi_eksternal_persen;
        $unit_komisi_eksternal_rupiah = !empty($unit['unit_komisi_eksternal_rupiah']) ? (float)$unit['unit_komisi_eksternal_rupiah'] : 0;
        
        // Set nilai final
        $unit['komisi_internal_rupiah'] = $unit_komisi_internal;
        $unit['komisi_eksternal_persen'] = $unit_komisi_eksternal_persen;
        $unit['komisi_eksternal_rupiah'] = $unit_komisi_eksternal_rupiah;
        
        // Format harga
        $unit['harga_formatted'] = $unit['harga'] > 0 ? 'Rp ' . number_format($unit['harga'], 0, ',', '.') : 'Hubungi marketing';
        $unit['harga_booking_formatted'] = $unit['harga_booking'] > 0 
            ? 'Rp ' . number_format($unit['harga_booking'], 0, ',', '.')
            : 'Gratis';
        
        // Format komisi
        if (!empty($unit['komisi_eksternal_rupiah']) && $unit['komisi_eksternal_rupiah'] > 0) {
            $unit['komisi_eksternal_formatted'] = 'Rp ' . number_format($unit['komisi_eksternal_rupiah'], 0, ',', '.');
        } else {
            $unit['komisi_eksternal_formatted'] = number_format($unit['komisi_eksternal_persen'], 2, ',', '.') . '%';
        }
        
        $unit['komisi_internal_formatted'] = 'Rp ' . number_format($unit['komisi_internal_rupiah'], 0, ',', '.');
        
        // Program booking
        if ($unit['program_booking_id']) {
            $unit['program_booking_name'] = $unit['program_booking_name'] ?? 'Program Booking';
            $unit['program_booking_fee_formatted'] = 'Rp ' . number_format($unit['program_booking_fee'] ?? 0, 0, ',', '.');
        }
        
        // Display names
        $unit['display_name'] = $unit['nomor_unit'] . ' - ' . $unit['tipe_unit'] . ' (' . $unit['program'] . ')';
        $unit['full_display'] = $unit['nama_cluster'] . ' - Block ' . $unit['nama_block'] . ' - ' . $unit['nomor_unit'] . ' (' . $unit['tipe_unit'] . ')';
        
        // SIMPAN KE FLAT DATA
        $flat_data[] = $unit;
    }
    
    // BUAT STRUKTUR GROUPED UNTUK TAMPILAN
    $grouped = [];
    foreach ($units as $unit) {
        $cluster_key = $unit['cluster_id'];
        if (!isset($grouped[$cluster_key])) {
            $grouped[$cluster_key] = [
                'cluster_id' => $unit['cluster_id'],
                'nama_cluster' => $unit['nama_cluster'],
                'blocks' => []
            ];
        }
        
        $block_key = $unit['block_id'];
        if (!isset($grouped[$cluster_key]['blocks'][$block_key])) {
            $grouped[$cluster_key]['blocks'][$block_key] = [
                'block_id' => $unit['block_id'],
                'nama_block' => $unit['nama_block'],
                'units' => []
            ];
        }
        
        $grouped[$cluster_key]['blocks'][$block_key]['units'][] = $unit;
    }
    
    $result = [];
    foreach ($grouped as $cluster) {
        $cluster['blocks'] = array_values($cluster['blocks']);
        $result[] = $cluster;
    }
    
    writeDebug("Flat data count: " . count($flat_data));
    writeLog("Response success");
    
    echo json_encode([
        'success' => true,
        'data' => $result,           // Data terstruktur untuk tampilan
        'flat_data' => $flat_data,    // Data flat untuk akses cepat
        'total_units' => count($units),
        'total_all_available' => $total_all_available,
        'developer_id' => $developer_id,
        'is_external' => $is_external,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'limit' => $limit
        ],
        'filters' => [
            'cluster_id' => $cluster_id,
            'block_id' => $block_id,
            'program' => $program,
            'search' => $search
        ],
        'timestamp' => time()
    ]);
    
    writeDebug("Response sent successfully");
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    writeDebug("ERROR: " . $e->getMessage());
    writeDebug("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

writeLog("===== GET_AVAILABLE_UNITS SELESAI =====\n");
writeDebug("===== GET_AVAILABLE_UNITS SELESAI =====\n");

?>