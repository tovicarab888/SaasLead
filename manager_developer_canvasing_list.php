<?php
/**
 * MANAGER_DEVELOPER_CANVASING_LIST.PHP - LEADENGINE
 * Version: 1.0.0 - API untuk Manager Developer melihat data canvasing
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/manager_developer_canvasing.log');

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

$log_file = $log_dir . '/manager_developer_canvasing.log';

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

writeLog("========== MANAGER DEVELOPER CANVASING API DIPANGGIL ==========");

// Cek session manager developer
if (!isManagerDeveloper()) {
    writeLog("ERROR: Bukan manager developer");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Silakan login sebagai manager developer']);
    exit();
}

$conn = getDB();
if (!$conn) {
    writeLog("ERROR: Database connection failed");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$developer_id = $_SESSION['developer_id'] ?? 0;
$action = $_GET['action'] ?? $_POST['action'] ?? '';

writeLog("Developer ID: $developer_id, Action: $action");

if ($developer_id <= 0) {
    writeLog("ERROR: Developer ID tidak valid");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Developer ID tidak valid']);
    exit();
}

// ========== ACTION: GET STATS ==========
if ($action === 'get_stats') {
    try {
        // Total canvasing
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM canvasing_logs 
            WHERE developer_id = ?
        ");
        $stmt->execute([$developer_id]);
        $total = $stmt->fetchColumn();

        // Hari ini
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM canvasing_logs 
            WHERE developer_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$developer_id]);
        $today = $stmt->fetchColumn();

        // Marketing aktif
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT marketing_id) FROM canvasing_logs 
            WHERE developer_id = ?
        ");
        $stmt->execute([$developer_id]);
        $active_marketing = $stmt->fetchColumn();

        // Total lokasi
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT location_key) FROM canvasing_logs 
            WHERE developer_id = ?
        ");
        $stmt->execute([$developer_id]);
        $locations = $stmt->fetchColumn();

        // Last activity
        $stmt = $conn->prepare("
            SELECT created_at FROM canvasing_logs 
            WHERE developer_id = ? 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$developer_id]);
        $last = $stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'data' => [
                'total' => (int)$total,
                'today' => (int)$today,
                'active_marketing' => (int)$active_marketing,
                'locations' => (int)$locations,
                'last_activity' => $last ?: null
            ]
        ]);

    } catch (Exception $e) {
        writeLog("ERROR get_stats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ========== ACTION: GET LIST ==========
if ($action === 'get_list') {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;

        $marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
        $location_key = isset($_GET['location_key']) ? $_GET['location_key'] : '';
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        // Bangun query
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
            WHERE c.developer_id = ?
        ";

        $params = [$developer_id];

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

        if (!empty($search)) {
            $sql .= " AND (m.nama_lengkap LIKE ? OR c.customer_name LIKE ? OR c.customer_phone LIKE ? OR c.notes LIKE ?)";
            $s = "%$search%";
            $params = array_merge($params, [$s, $s, $s, $s]);
        }

        // Count total
        $count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as tmp";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $limit);

        // Get data
        $sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        // Tambahkan full URL foto
        foreach ($data as &$item) {
            if (!empty($item['photo_path'])) {
                $item['photo_url'] = 'https://taufikmarie.com/admin/' . $item['photo_path'];
                $file_path = dirname(__DIR__, 2) . '/' . $item['photo_path'];
                $item['photo_exists'] = file_exists($file_path);
            } else {
                $item['photo_url'] = null;
                $item['photo_exists'] = false;
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

// ========== ACTION: GET MARKETING STATS ==========
if ($action === 'get_marketing_stats') {
    try {
        $stmt = $conn->prepare("
            SELECT 
                m.id,
                m.nama_lengkap,
                m.phone,
                COUNT(c.id) as total_canvasing,
                COUNT(DISTINCT DATE(c.created_at)) as active_days,
                MAX(c.created_at) as last_canvasing,
                COUNT(DISTINCT c.location_key) as locations_visited
            FROM marketing_team m
            LEFT JOIN canvasing_logs c ON m.id = c.marketing_id AND c.developer_id = m.developer_id
            WHERE m.developer_id = ? AND m.is_active = 1
            GROUP BY m.id
            ORDER BY total_canvasing DESC
        ");
        $stmt->execute([$developer_id]);
        $stats = $stmt->fetchAll();

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

// ========== ACTION: GET LOCATION STATS ==========
if ($action === 'get_location_stats') {
    try {
        $stmt = $conn->prepare("
            SELECT 
                c.location_key,
                l.display_name,
                l.icon,
                COUNT(c.id) as total,
                COUNT(DISTINCT c.marketing_id) as marketing_count,
                MAX(c.created_at) as last_visit
            FROM canvasing_logs c
            LEFT JOIN locations l ON c.location_key = l.location_key
            WHERE c.developer_id = ?
            GROUP BY c.location_key
            ORDER BY total DESC
        ");
        $stmt->execute([$developer_id]);
        $stats = $stmt->fetchAll();

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

// ========== ACTION: GET DETAIL ==========
if ($action === 'get_detail') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }

    try {
        $stmt = $conn->prepare("
            SELECT 
                c.*,
                m.nama_lengkap as marketing_name,
                m.phone as marketing_phone,
                l.display_name as location_display,
                l.icon
            FROM canvasing_logs c
            LEFT JOIN marketing_team m ON c.marketing_id = m.id
            LEFT JOIN locations l ON c.location_key = l.location_key
            WHERE c.id = ? AND c.developer_id = ?
        ");
        $stmt->execute([$id, $developer_id]);
        $data = $stmt->fetch();

        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
            exit();
        }

        // Tambahkan full URL foto
        if (!empty($data['photo_path'])) {
            $data['photo_url'] = 'https://taufikmarie.com/admin/' . $data['photo_path'];
            $file_path = dirname(__DIR__, 2) . '/' . $data['photo_path'];
            $data['photo_exists'] = file_exists($file_path);
        }

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

// ========== ACTION: GET PHOTO ==========
if ($action === 'get_photo') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }

    try {
        $stmt = $conn->prepare("
            SELECT photo_path FROM canvasing_logs 
            WHERE id = ? AND developer_id = ?
        ");
        $stmt->execute([$id, $developer_id]);
        $photo_path = $stmt->fetchColumn();

        if (!$photo_path) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Foto tidak ditemukan']);
            exit();
        }

        $full_path = dirname(__DIR__, 2) . '/' . $photo_path;

        if (!file_exists($full_path)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File foto tidak ditemukan di server']);
            exit();
        }

        // Return file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $full_path);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($full_path));
        header('Cache-Control: public, max-age=86400');
        readfile($full_path);
        exit();

    } catch (Exception $e) {
        writeLog("ERROR get_photo: " . $e->getMessage());
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

        // Bangun query
        $sql = "
            SELECT 
                c.id,
                c.created_at as tanggal,
                m.nama_lengkap as marketing,
                m.phone as no_marketing,
                l.display_name as lokasi,
                c.customer_name as nama_customer,
                c.customer_phone as no_customer,
                c.latitude,
                c.longitude,
                c.accuracy,
                c.notes as catatan,
                c.photo_path
            FROM canvasing_logs c
            LEFT JOIN marketing_team m ON c.marketing_id = m.id
            LEFT JOIN locations l ON c.location_key = l.location_key
            WHERE c.developer_id = ?
        ";

        $params = [$developer_id];

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
        $data = $stmt->fetchAll();

        if ($format === 'csv') {
            // Output CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="canvasing_developer_' . $developer_id . '_' . date('Ymd') . '.csv"');

            $output = fopen('php://output', 'w');
            // BOM untuk Excel
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header
            fputcsv($output, [
                'ID',
                'Tanggal',
                'Marketing',
                'No. Marketing',
                'Lokasi',
                'Nama Customer',
                'No. Customer',
                'Latitude',
                'Longitude',
                'Akurasi',
                'Catatan',
                'Foto'
            ]);

            // Data
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['tanggal'],
                    $row['marketing'],
                    $row['no_marketing'],
                    $row['lokasi'],
                    $row['nama_customer'],
                    $row['no_customer'],
                    $row['latitude'],
                    $row['longitude'],
                    $row['accuracy'],
                    $row['catatan'],
                    'https://taufikmarie.com/admin/' . $row['photo_path']
                ]);
            }

            fclose($output);
            exit();

        } else {
            // Default JSON
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

// ========== DEFAULT ==========
writeLog("ERROR: Action tidak dikenal: $action");
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);
exit();
// ========== ACTION: DELETE (HANYA UNTUK ADMIN) ==========
if ($action === 'delete') {
    // Cek apakah user adalah admin
    if (!isAdmin()) {
        writeLog("ERROR: Bukan admin, tidak bisa hapus");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden - Hanya admin yang bisa menghapus']);
        exit();
    }
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }
    
    try {
        $conn->beginTransaction();
        
        // Ambil path foto dulu
        $stmt = $conn->prepare("SELECT photo_path FROM canvasing_logs WHERE id = ?");
        $stmt->execute([$id]);
        $photo_path = $stmt->fetchColumn();
        
        // Hapus dari database
        $stmt = $conn->prepare("DELETE FROM canvasing_logs WHERE id = ?");
        $stmt->execute([$id]);
        
        // Hapus file foto jika ada
        if ($photo_path) {
            $full_path = dirname(__DIR__, 2) . '/' . $photo_path;
            if (file_exists($full_path)) {
                unlink($full_path);
                writeLog("File foto dihapus: $full_path");
            }
        }
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Data canvasing berhasil dihapus']);
        
    } catch (Exception $e) {
        $conn->rollBack();
        writeLog("ERROR delete: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}
?>