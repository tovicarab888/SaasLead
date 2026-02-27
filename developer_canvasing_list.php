<?php
/**
 * DEVELOPER_CANVASING_LIST.PHP - LEADENGINE
 * Version: 4.0.0 - FIXED: Simplified auth, fulltext search, SITE_URL constant
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/developer_canvasing.log');

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

// Buat folder logs
$log_dir = dirname(__DIR__, 2) . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/developer_canvasing.log';

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

writeLog("========== DEVELOPER CANVASING API DIPANGGIL ==========");

// Rate limiting
$client_ip = getClientIP();
if (!checkRateLimit('canvasing_api_' . $client_ip, 30, 60)) {
    writeLog("Rate limit exceeded for IP: $client_ip");
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']);
    exit();
}

// ===== CEK AUTHENTIKASI SEDERHANA =====
$is_authenticated = false;
$developer_id = 0;
$current_role = getCurrentRole();
$current_user_id = $_SESSION['user_id'] ?? 0;

if (checkAuth()) {
    // User terautentikasi via session
    if ($current_role === 'developer') {
        $is_authenticated = true;
        $developer_id = $current_user_id;
        writeLog("Authenticated as developer: $developer_id");
    } elseif (in_array($current_role, ['admin', 'manager'])) {
        $is_authenticated = true;
        $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
        writeLog("Authenticated as $current_role, developer_id: $developer_id");
    } elseif (in_array($current_role, ['manager_developer', 'finance'])) {
        $is_authenticated = true;
        $developer_id = $_SESSION['developer_id'] ?? 0;
        writeLog("Authenticated as $current_role, developer_id: $developer_id");
    }
} elseif (isMarketing()) {
    // Marketing login via marketing session
    $is_authenticated = true;
    $developer_id = $_SESSION['marketing_developer_id'] ?? 0;
    writeLog("Authenticated as marketing, developer_id: $developer_id");
}

if (!$is_authenticated) {
    writeLog("ERROR: Unauthorized - No valid session");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Silakan login terlebih dahulu']);
    exit();
}

if ($developer_id <= 0 && !in_array($current_role, ['admin', 'manager'])) {
    writeLog("ERROR: Developer ID tidak valid: $developer_id");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Developer ID tidak valid']);
    exit();
}

$conn = getDB();
if (!$conn) {
    writeLog("ERROR: Database connection failed");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

writeLog("Developer ID: $developer_id, Action: $action, Role: $current_role");

// ========== PASTIKAN TABEL CANVASING LOGS MEMILIKI FULLTEXT INDEX ==========
try {
    $conn->exec("ALTER TABLE canvasing_logs ADD FULLTEXT INDEX ft_search (customer_name, instansi_name, pic_name, notes)");
} catch (Exception $e) {
    // Index mungkin sudah ada
}

// ========== ACTION: GET_STATS ==========
if ($action === 'get_stats') {
    try {
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                COUNT(DISTINCT marketing_id) as active_marketing,
                COUNT(DISTINCT location_key) as locations,
                MAX(created_at) as last_activity
            FROM canvasing_logs 
            WHERE 1=1";
        $params = [];
        
        if ($developer_id > 0) {
            $sql .= " AND developer_id = ?";
            $params[] = $developer_id;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total' => (int)$stats['total'],
                'today' => (int)$stats['today'],
                'active_marketing' => (int)$stats['active_marketing'],
                'locations' => (int)$stats['locations'],
                'last_activity' => $stats['last_activity']
            ]
        ]);
    } catch (Exception $e) {
        writeLog("ERROR get_stats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ========== ACTION: GET_LIST ==========
if ($action === 'get_list') {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
        $offset = ($page - 1) * $limit;
        
        $marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
        $location_key = isset($_GET['location_key']) ? $_GET['location_key'] : '';
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $sql = "
            SELECT 
                c.*,
                m.nama_lengkap as marketing_name,
                m.phone as marketing_phone,
                l.display_name as location_display,
                l.icon,
                DATEDIFF(NOW(), c.created_at) as days_ago
            FROM canvasing_logs c
            LEFT JOIN marketing_team m ON c.marketing_id = m.id
            LEFT JOIN locations l ON c.location_key = l.location_key
            WHERE 1=1
        ";
        $params = [];

        if ($developer_id > 0) {
            $sql .= " AND c.developer_id = ?";
            $params[] = $developer_id;
        }

        if ($marketing_id > 0) {
            $sql .= " AND c.marketing_id = ?";
            $params[] = $marketing_id;
        }

        if (!empty($location_key)) {
            $sql .= " AND c.location_key = ?";
            $params[] = $location_key;
        }

        if (!empty($date_from) && !empty($date_to)) {
            $sql .= " AND DATE(c.created_at) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }

        // FULLTEXT SEARCH (lebih cepat dari banyak OR)
        if (!empty($search) && strlen($search) > 2) {
            $sql .= " AND MATCH(c.customer_name, c.instansi_name, c.pic_name, c.notes) AGAINST (? IN BOOLEAN MODE)";
            $search_term = '+' . str_replace(' ', ' +', $search) . '*';
            $params[] = $search_term;
        } elseif (!empty($search)) {
            // Fallback untuk search pendek
            $sql .= " AND (c.customer_name LIKE ? OR c.instansi_name LIKE ? OR c.pic_name LIKE ? OR c.customer_phone LIKE ? OR c.pic_phone LIKE ?)";
            $s = "%$search%";
            $params = array_merge($params, [$s, $s, $s, $s, $s]);
        }

        $count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as tmp";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $limit);

        $sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cek keberadaan foto
        foreach ($data as &$item) {
            if (!empty($item['photo_path'])) {
                $full_path = dirname(__DIR__, 2) . '/' . $item['photo_path'];
                $item['photo_exists'] = file_exists($full_path);
                $item['photo_url'] = $item['photo_exists'] ? 
                    SITE_URL . '/' . $item['photo_path'] : null;
            } else {
                $item['photo_exists'] = false;
                $item['photo_url'] = null;
            }
            
            // Format data untuk tampilan
            $item['display_type'] = ucfirst($item['canvasing_type'] ?? 'individual');
            if ($item['canvasing_type'] === 'individual') {
                $item['contact_name'] = $item['customer_name'];
                $item['contact_phone'] = $item['customer_phone'];
            } else {
                $item['contact_name'] = $item['instansi_name'] ?: $item['pic_name'];
                $item['contact_phone'] = $item['pic_phone'] ?: $item['customer_phone'];
            }
            
            // Format koordinat
            $item['latitude'] = $item['latitude'] ? (float)$item['latitude'] : null;
            $item['longitude'] = $item['longitude'] ? (float)$item['longitude'] : null;
            $item['accuracy'] = $item['accuracy'] ? (float)$item['accuracy'] : null;
            
            // Link Google Maps
            if ($item['latitude'] && $item['longitude']) {
                $item['maps_url'] = "https://www.google.com/maps?q={$item['latitude']},{$item['longitude']}";
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_records' => $total_records,
                'limit' => $limit
            ]
        ]);

    } catch (Exception $e) {
        writeLog("ERROR get_list: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ========== ACTION: GET_MARKETING_STATS ==========
if ($action === 'get_marketing_stats') {
    try {
        $sql = "
            SELECT 
                m.id,
                m.nama_lengkap,
                m.phone,
                COUNT(c.id) as total_canvasing,
                COUNT(DISTINCT DATE(c.created_at)) as active_days,
                MAX(c.created_at) as last_canvasing,
                COUNT(DISTINCT c.location_key) as locations_visited,
                SUM(CASE WHEN c.converted_to_lead = 1 THEN 1 ELSE 0 END) as converted_to_lead
            FROM marketing_team m
            LEFT JOIN canvasing_logs c ON m.id = c.marketing_id AND c.developer_id = m.developer_id
            WHERE 1=1
        ";
        $params = [];
        
        if ($developer_id > 0) {
            $sql .= " AND m.developer_id = ?";
            $params[] = $developer_id;
        }
        
        $sql .= " AND m.is_active = 1 GROUP BY m.id ORDER BY total_canvasing DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
    } catch (Exception $e) {
        writeLog("ERROR get_marketing_stats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ========== ACTION: GET_LOCATION_STATS ==========
if ($action === 'get_location_stats') {
    try {
        $sql = "
            SELECT 
                c.location_key,
                l.display_name,
                l.icon,
                COUNT(c.id) as total,
                COUNT(DISTINCT c.marketing_id) as marketing_count,
                MAX(c.created_at) as last_visit,
                SUM(CASE WHEN c.converted_to_lead = 1 THEN 1 ELSE 0 END) as conversions
            FROM canvasing_logs c
            LEFT JOIN locations l ON c.location_key = l.location_key
            WHERE 1=1
        ";
        $params = [];
        
        if ($developer_id > 0) {
            $sql .= " AND c.developer_id = ?";
            $params[] = $developer_id;
        }
        
        $sql .= " GROUP BY c.location_key ORDER BY total DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
    } catch (Exception $e) {
        writeLog("ERROR get_location_stats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ========== ACTION: GET_DETAIL ==========
if ($action === 'get_detail') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }

    try {
        $sql = "
            SELECT 
                c.*,
                m.nama_lengkap as marketing_name,
                m.phone as marketing_phone,
                l.display_name as location_display,
                l.icon
            FROM canvasing_logs c
            LEFT JOIN marketing_team m ON c.marketing_id = m.id
            LEFT JOIN locations l ON c.location_key = l.location_key
            WHERE c.id = ?
        ";
        $params = [$id];
        
        if ($developer_id > 0 && !in_array($current_role, ['admin', 'manager'])) {
            $sql .= " AND c.developer_id = ?";
            $params[] = $developer_id;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
            exit();
        }

        if (!empty($data['photo_path'])) {
            $full_path = dirname(__DIR__, 2) . '/' . $data['photo_path'];
            $data['photo_exists'] = file_exists($full_path);
            $data['photo_url'] = $data['photo_exists'] ? 
                SITE_URL . '/' . $data['photo_path'] : null;
        }
        
        // Format koordinat
        $data['latitude'] = $data['latitude'] ? (float)$data['latitude'] : null;
        $data['longitude'] = $data['longitude'] ? (float)$data['longitude'] : null;

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);

    } catch (Exception $e) {
        writeLog("ERROR get_detail: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ========== ACTION: EXPORT ==========
if ($action === 'export') {
    try {
        $format = isset($_GET['format']) ? $_GET['format'] : 'json';
        $marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
        $location_key = isset($_GET['location_key']) ? $_GET['location_key'] : '';
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

        $sql = "
            SELECT 
                c.id,
                DATE(c.created_at) as tanggal,
                TIME(c.created_at) as waktu,
                m.nama_lengkap as marketing,
                m.phone as no_marketing,
                l.display_name as lokasi,
                c.canvasing_type as tipe_canvasing,
                c.customer_name as nama_customer,
                c.customer_phone as no_customer,
                c.instansi_name as nama_instansi,
                c.pic_name as nama_pic,
                c.pic_phone as no_pic,
                c.latitude,
                c.longitude,
                c.accuracy,
                c.notes as catatan,
                c.converted_to_lead,
                c.converted_lead_id,
                c.photo_path
            FROM canvasing_logs c
            LEFT JOIN marketing_team m ON c.marketing_id = m.id
            LEFT JOIN locations l ON c.location_key = l.location_key
            WHERE 1=1
        ";
        $params = [];

        if ($developer_id > 0) {
            $sql .= " AND c.developer_id = ?";
            $params[] = $developer_id;
        }

        if ($marketing_id > 0) {
            $sql .= " AND c.marketing_id = ?";
            $params[] = $marketing_id;
        }

        if (!empty($location_key)) {
            $sql .= " AND c.location_key = ?";
            $params[] = $location_key;
        }

        if (!empty($date_from) && !empty($date_to)) {
            $sql .= " AND DATE(c.created_at) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }

        $sql .= " ORDER BY c.created_at DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="canvasing_developer_' . $developer_id . '_' . date('Ymd') . '.csv"');

            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($output, [
                'ID',
                'Tanggal',
                'Waktu',
                'Marketing',
                'No. Marketing',
                'Lokasi',
                'Tipe Canvasing',
                'Nama Customer',
                'No. Customer',
                'Nama Instansi',
                'Nama PIC',
                'No. PIC',
                'Latitude',
                'Longitude',
                'Akurasi',
                'Catatan',
                'Converted',
                'Lead ID',
                'Foto'
            ]);

            foreach ($data as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['tanggal'],
                    $row['waktu'],
                    $row['marketing'],
                    $row['no_marketing'],
                    $row['lokasi'],
                    $row['tipe_canvasing'],
                    $row['nama_customer'],
                    $row['no_customer'],
                    $row['nama_instansi'],
                    $row['nama_pic'],
                    $row['no_pic'],
                    $row['latitude'],
                    $row['longitude'],
                    $row['accuracy'],
                    $row['catatan'],
                    $row['converted_to_lead'] ? 'Ya' : 'Tidak',
                    $row['converted_lead_id'] ?: '',
                    $row['photo_path'] ? SITE_URL . '/' . $row['photo_path'] : ''
                ]);
            }

            fclose($output);
            exit();
        } else {
            echo json_encode([
                'success' => true,
                'data' => $data,
                'total' => count($data)
            ]);
        }

    } catch (Exception $e) {
        writeLog("ERROR export: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

writeLog("ERROR: Action tidak dikenal: $action");
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);
exit();
?>