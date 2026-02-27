<?php
/**
 * GET_UNITS.PHP - LEADENGINE API
 * Version: 3.1.0 - FIXED: Tambah parameter unit_id, hapus kolom keterangan
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_units.log');

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

function writeLog($message, $data = null) {
    $log_dir = dirname(__DIR__, 2) . '/logs';
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_file = $log_dir . '/api_units.log';
    
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
    $log_dir = dirname(__DIR__, 2) . '/logs';
    $debug_file = $log_dir . '/api_units_debug.log';
    
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

writeLog("========== GET_UNITS API DIPANGGIL ==========");
writeLog("Request URI: " . $_SERVER['REQUEST_URI']);
writeLog("Method: " . $_SERVER['REQUEST_METHOD']);
writeDebug("========== GET_UNITS API CALLED ==========");
writeDebug("GET params: " . json_encode($_GET));

$is_authenticated = checkAuth() || isMarketing();

if (!$is_authenticated) {
    writeLog("ERROR: Unauthorized");
    writeDebug("ERROR: Unauthorized");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = getDB();
if (!$conn) {
    writeLog("ERROR: Database connection failed");
    writeDebug("ERROR: Database connection failed");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// ===== PARAMETER YANG DIDUKUNG =====
$block_id = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
$unit_id = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0; // <-- TAMBAHAN UNTUK BOOKING
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$program = isset($_GET['program']) ? trim($_GET['program']) : '';

writeLog("Parameters - block_id: $block_id, unit_id: $unit_id, status: $status, program: $program");
writeDebug("Parameters - block_id: $block_id, unit_id: $unit_id, status: $status, program: $program");

// Validasi parameter
if ($block_id <= 0 && $unit_id <= 0) {
    writeLog("ERROR: Block ID atau Unit ID diperlukan");
    writeDebug("ERROR: Block ID atau Unit ID diperlukan");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Block ID atau Unit ID diperlukan']);
    exit();
}

$valid_statuses = ['AVAILABLE', 'BOOKED', 'SOLD', 'RESERVED'];
if (!empty($status) && !in_array($status, $valid_statuses)) {
    writeLog("ERROR: Status tidak valid: $status");
    writeDebug("ERROR: Status tidak valid: $status");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
    exit();
}

// Tentukan developer_id berdasarkan role
$developer_id = 0;
$current_role = getCurrentRole();

if (isDeveloper()) {
    $developer_id = $_SESSION['user_id'];
} elseif (isMarketing()) {
    $developer_id = $_SESSION['marketing_developer_id'] ?? 0;
} elseif (isManagerDeveloper()) {
    $developer_id = $_SESSION['developer_id'] ?? 0;
}

writeDebug("Developer ID dari session: $developer_id, Role: $current_role");

try {
    // ===== CEK AKSES =====
    if ($developer_id > 0) {
        if ($unit_id > 0) {
            // Cek akses untuk unit_id
            $stmt = $conn->prepare("
                SELECT u.id, c.developer_id 
                FROM units u
                JOIN blocks b ON u.block_id = b.id
                JOIN clusters c ON b.cluster_id = c.id
                WHERE u.id = ?
            ");
            $stmt->execute([$unit_id]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$unit) {
                writeLog("ERROR: Unit tidak ditemukan - ID: $unit_id");
                writeDebug("ERROR: Unit tidak ditemukan - ID: $unit_id");
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Unit tidak ditemukan']);
                exit();
            }
            
            if ($unit['developer_id'] != $developer_id) {
                writeLog("ERROR: Developer tidak memiliki akses ke unit ini");
                writeDebug("ERROR: Developer tidak memiliki akses ke unit ini");
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
                exit();
            }
        } else {
            // Cek akses untuk block_id
            $stmt = $conn->prepare("
                SELECT b.id, c.developer_id 
                FROM blocks b
                JOIN clusters c ON b.cluster_id = c.id
                WHERE b.id = ?
            ");
            $stmt->execute([$block_id]);
            $block = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$block) {
                writeLog("ERROR: Block tidak ditemukan - ID: $block_id");
                writeDebug("ERROR: Block tidak ditemukan - ID: $block_id");
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Block tidak ditemukan']);
                exit();
            }
            
            if ($block['developer_id'] != $developer_id) {
                writeLog("ERROR: Developer tidak memiliki akses ke block ini");
                writeDebug("ERROR: Developer tidak memiliki akses ke block ini");
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
                exit();
            }
        }
    }
    
    // ===== BANGUN QUERY =====
    if ($unit_id > 0) {
        // QUERY UNTUK SATU UNIT (UNTUK BOOKING)
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
                u.lead_id,
                u.booking_at,
                u.sold_at,
                u.created_at,
                u.updated_at,
                u.komisi_eksternal_persen,
                u.komisi_eksternal_rupiah,
                u.komisi_internal_rupiah,
                u.komisi_split_persen,
                u.komisi_split_rupiah,
                u.program_booking_id,
                u.multiple_program_booking,
                c.nama_cluster,
                b.nama_block,
                pb.nama_program as program_booking_name,
                pb.booking_fee as program_booking_fee,
                pb.is_all_in,
                CONCAT(l.first_name, ' ', l.last_name) as lead_name,
                l.phone as lead_phone
            FROM units u
            JOIN blocks b ON u.block_id = b.id
            JOIN clusters c ON b.cluster_id = c.id
            LEFT JOIN program_booking pb ON u.program_booking_id = pb.id
            LEFT JOIN leads l ON u.lead_id = l.id
            WHERE u.id = ?
        ";
        
        $params = [$unit_id];
        writeDebug("SQL untuk unit_id: $sql");
        
    } else {
        // QUERY UNTUK SEMUA UNIT DALAM BLOCK
        $sql = "
            SELECT 
                u.id,
                u.nomor_unit,
                u.tipe_unit,
                u.program,
                u.harga,
                u.harga_booking,
                u.status,
                u.created_at,
                u.komisi_eksternal_persen,
                u.komisi_eksternal_rupiah,
                u.komisi_internal_rupiah,
                u.program_booking_id,
                b.nama_block,
                c.nama_cluster,
                c.id as cluster_id
            FROM units u
            JOIN blocks b ON u.block_id = b.id
            JOIN clusters c ON b.cluster_id = c.id
            WHERE u.block_id = ?
        ";
        
        $params = [$block_id];
        
        if (!empty($status)) {
            $sql .= " AND u.status = ?";
            $params[] = $status;
        }
        
        if (!empty($program)) {
            $sql .= " AND u.program = ?";
            $params[] = $program;
        }
        
        $sql .= " ORDER BY u.nomor_unit ASC";
        writeDebug("SQL untuk block_id: $sql");
    }
    
    writeDebug("Params: " . json_encode($params));
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    if ($unit_id > 0) {
        // Return satu unit
        $unit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$unit) {
            writeLog("ERROR: Unit tidak ditemukan - ID: $unit_id");
            writeDebug("ERROR: Unit tidak ditemukan - ID: $unit_id");
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Unit tidak ditemukan']);
            exit();
        }
        
        // Format data
        $unit['harga_formatted'] = $unit['harga'] > 0 ? 'Rp ' . number_format($unit['harga'], 0, ',', '.') : 'Hubungi marketing';
        $unit['harga_booking_formatted'] = $unit['harga_booking'] > 0 ? 'Rp ' . number_format($unit['harga_booking'], 0, ',', '.') : 'Gratis';
        
        if (!empty($unit['komisi_eksternal_rupiah']) && $unit['komisi_eksternal_rupiah'] > 0) {
            $unit['komisi_eksternal_formatted'] = 'Rp ' . number_format($unit['komisi_eksternal_rupiah'], 0, ',', '.');
        } else {
            $unit['komisi_eksternal_formatted'] = number_format($unit['komisi_eksternal_persen'] ?? 3.00, 2, ',', '.') . '%';
        }
        
        $unit['komisi_internal_formatted'] = 'Rp ' . number_format($unit['komisi_internal_rupiah'] ?? 0, 0, ',', '.');
        $unit['display_name'] = $unit['nomor_unit'] . ' - ' . $unit['tipe_unit'] . ' (' . $unit['program'] . ')';
        $unit['full_display'] = $unit['nama_cluster'] . ' - Block ' . $unit['nama_block'] . ' - ' . $unit['nomor_unit'];
        $unit['is_available'] = ($unit['status'] === 'AVAILABLE');
        
        writeLog("Unit ditemukan: ID $unit_id");
        writeDebug("Unit data: " . json_encode($unit));
        
        echo json_encode([
            'success' => true,
            'data' => $unit,
            'unit_id' => $unit_id
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // Return array unit
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        writeLog("Ditemukan " . count($units) . " units untuk block_id: $block_id");
        writeDebug("Ditemukan " . count($units) . " units");
        
        foreach ($units as &$unit) {
            $unit['harga_formatted'] = $unit['harga'] > 0 ? 'Rp ' . number_format($unit['harga'], 0, ',', '.') : 'Hubungi marketing';
            $unit['harga_booking_formatted'] = $unit['harga_booking'] > 0 ? 'Rp ' . number_format($unit['harga_booking'], 0, ',', '.') : 'Gratis';
            $unit['display_name'] = $unit['nomor_unit'] . ' - ' . $unit['tipe_unit'] . ' (' . $unit['program'] . ')';
            $unit['can_book'] = ($unit['status'] === 'AVAILABLE');
        }
        
        echo json_encode([
            'success' => true,
            'data' => $units,
            'total' => count($units),
            'block_id' => $block_id
        ], JSON_UNESCAPED_UNICODE);
    }
    
    writeLog("Response sukses dikirim");
    writeDebug("Response sukses dikirim");
    
} catch (PDOException $e) {
    writeLog("PDO ERROR: " . $e->getMessage());
    writeDebug("PDO ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    writeDebug("ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

writeLog("========== GET_UNITS API SELESAI ==========\n");
writeDebug("========== GET_UNITS API SELESAI ==========\n");
?>