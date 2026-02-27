<?php
/**
 * GET_MARKETING_CONFIG.PHP - Mengambil konfigurasi marketing untuk developer
 * Version: 1.0.0
 * FULL CODE - 100% LENGKAP
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

require_once __DIR__ . '/config.php';

$log_file = __DIR__ . '/../logs/marketing_config.log';

function writeLog($message, $data = null) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data) {
        if (is_array($data)) {
            $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $log .= $data . "\n";
        }
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeLog("========== GET_MARKETING_CONFIG DIPANGGIL ==========");

try {
    // Ambil parameter
    $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : null;
    $marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : null;
    $type = isset($_GET['type']) ? $_GET['type'] : null; // internal, external, all
    
    writeLog("Parameter:", [
        'developer_id' => $developer_id,
        'marketing_id' => $marketing_id,
        'type' => $type
    ]);
    
    $conn = getDB();
    if (!$conn) {
        writeLog("ERROR: Database connection failed");
        sendResponse(false, 'Database connection failed', null, 500);
    }
    
    // Jika marketing_id spesifik
    if ($marketing_id !== null && $marketing_id > 0) {
        $stmt = $conn->prepare("
            SELECT 
                m.id,
                m.nama_lengkap,
                m.phone,
                m.email,
                m.username,
                m.notification_template,
                m.developer_id,
                mt.id as marketing_type_id,
                mt.type_name as marketing_type,
                mt.can_book,
                mt.can_canvasing,
                mt.commission_type,
                mt.commission_value,
                u.nama_perusahaan as developer_name
            FROM marketing_team m
            LEFT JOIN marketing_types mt ON m.marketing_type_id = mt.id
            LEFT JOIN users u ON m.developer_id = u.id
            WHERE m.id = ? AND m.is_active = 1
        ");
        $stmt->execute([$marketing_id]);
        $marketing = $stmt->fetch();
        
        if (!$marketing) {
            sendResponse(false, 'Marketing tidak ditemukan', null, 404);
        }
        
        // Tambah info rekening jika ada
        if (!empty($marketing['bank_id'])) {
            $bank_stmt = $conn->prepare("
                SELECT nama_bank, nomor_rekening, atas_nama 
                FROM banks WHERE id = ?
            ");
            $bank_stmt->execute([$marketing['bank_id']]);
            $bank = $bank_stmt->fetch();
            if ($bank) {
                $marketing['bank'] = $bank;
            }
        }
        
        writeLog("Marketing ditemukan:", $marketing);
        sendResponse(true, 'Marketing ditemukan', $marketing, 200);
    }
    
    // Jika developer_id diberikan
    if ($developer_id !== null && $developer_id > 0) {
        $sql = "
            SELECT 
                m.id,
                m.nama_lengkap,
                m.phone,
                m.email,
                m.username,
                m.notification_template,
                m.developer_id,
                mt.id as marketing_type_id,
                mt.type_name as marketing_type,
                mt.can_book,
                mt.can_canvasing,
                mt.commission_type,
                mt.commission_value
            FROM marketing_team m
            LEFT JOIN marketing_types mt ON m.marketing_type_id = mt.id
            WHERE m.developer_id = ? AND m.is_active = 1
        ";
        
        $params = [$developer_id];
        
        // Filter berdasarkan tipe
        if ($type === 'internal') {
            $sql .= " AND mt.type_name IN ('sales_inhouse', 'sales_canvasing')";
        } elseif ($type === 'external') {
            $sql .= " AND mt.type_name = 'external'";
        }
        
        $sql .= " ORDER BY m.nama_lengkap ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $marketings = $stmt->fetchAll();
        
        writeLog("Marketing untuk developer $developer_id ditemukan: " . count($marketings));
        sendResponse(true, 'Marketing ditemukan', $marketings, 200);
    }
    
    // Jika tidak ada parameter, ambil semua marketing aktif
    $stmt = $conn->prepare("
        SELECT 
            m.id,
            m.nama_lengkap,
            m.phone,
            m.email,
            m.username,
            m.developer_id,
            mt.type_name as marketing_type
        FROM marketing_team m
        LEFT JOIN marketing_types mt ON m.marketing_type_id = mt.id
        WHERE m.is_active = 1
        ORDER BY m.developer_id, m.nama_lengkap
    ");
    $stmt->execute();
    $all_marketings = $stmt->fetchAll();
    
    writeLog("Semua marketing ditemukan: " . count($all_marketings));
    sendResponse(true, 'Marketing ditemukan', $all_marketings, 200);
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    sendResponse(false, 'System error', null, 500);
}

function sendResponse($success, $message, $data = null, $code = 200) {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}
?>