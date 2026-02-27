<?php
/**
 * GET TRACKING CHARTS - Untuk data chart
 * Version: 1.0.2 - FIXED AMBIGUOUS COLUMN
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== CEK AUTHENTIKASI =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_authenticated = false;

if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    $is_authenticated = true;
}
elseif (isset($_GET['api_key']) && $_GET['api_key'] === API_KEY) {
    $is_authenticated = true;
}

if (!$is_authenticated) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Silakan login ulang']);
    exit();
}

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    // Ambil parameter
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $platform = $_GET['platform'] ?? 'all';
    $developer_id = $_GET['developer_id'] ?? 'all';
    
    // Validasi tanggal
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $start_date = date('Y-m-d', strtotime('-7 days'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $end_date = date('Y-m-d');
    }
    
    // Build conditions - PASTIKAN PAKAI ALIAS tl UNTUK tracking_logs
    $conditions = ["DATE(tl.created_at) BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];
    
    if ($platform !== 'all') {
        $conditions[] = "tl.pixel_type = ?";
        $params[] = $platform;
    }
    
    // Filter developer
    if (function_exists('isDeveloper') && isDeveloper() && isset($_SESSION['user_id'])) {
        $conditions[] = "tl.developer_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($developer_id !== 'all') {
        $conditions[] = "tl.developer_id = ?";
        $params[] = intval($developer_id);
    }
    
    $where_clause = implode(" AND ", $conditions);
    
    // 1. TREND CHART - PASTIKAN PAKAI tl.created_at
    $trend_sql = "
        SELECT 
            DATE(tl.created_at) as tanggal,
            COUNT(*) as total
        FROM tracking_logs tl
        WHERE $where_clause
        GROUP BY DATE(tl.created_at)
        ORDER BY tanggal ASC
    ";
    $trend_stmt = $conn->prepare($trend_sql);
    $trend_stmt->execute($params);
    $trend_data = $trend_stmt->fetchAll();
    
    $trend_labels = [];
    $trend_values = [];
    
    $begin = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end = $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($begin, $interval, $end);
    
    $trend_map = [];
    foreach ($trend_data as $row) {
        $trend_map[$row['tanggal']] = intval($row['total']);
    }
    
    foreach ($daterange as $date) {
        $trend_labels[] = $date->format('d/m');
        $trend_values[] = $trend_map[$date->format('Y-m-d')] ?? 0;
    }
    
    // 2. PLATFORM CHART
    $platform_sql = "
        SELECT 
            tl.pixel_type,
            COUNT(*) as total
        FROM tracking_logs tl
        WHERE $where_clause
        GROUP BY tl.pixel_type
        ORDER BY total DESC
    ";
    $platform_stmt = $conn->prepare($platform_sql);
    $platform_stmt->execute($params);
    $platform_data = $platform_stmt->fetchAll();
    
    $platform_labels = [];
    $platform_values = [];
    
    foreach ($platform_data as $row) {
        $platform_labels[] = ucfirst($row['pixel_type']);
        $platform_values[] = intval($row['total']);
    }
    
    // 3. DEVELOPER CHART
    $dev_sql = "
        SELECT 
            COALESCE(u.nama_lengkap, 'Global') as developer_name,
            COUNT(*) as total
        FROM tracking_logs tl
        LEFT JOIN users u ON tl.developer_id = u.id
        WHERE $where_clause
        GROUP BY tl.developer_id
        ORDER BY total DESC
        LIMIT 10
    ";
    $dev_stmt = $conn->prepare($dev_sql);
    $dev_stmt->execute($params);
    $dev_data = $dev_stmt->fetchAll();
    
    $dev_labels = [];
    $dev_values = [];
    
    foreach ($dev_data as $row) {
        $name = $row['developer_name'];
        if (strlen($name) > 15) {
            $name = substr($name, 0, 12) . '...';
        }
        $dev_labels[] = $name;
        $dev_values[] = intval($row['total']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'trend' => [
                'labels' => $trend_labels,
                'values' => $trend_values
            ],
            'platform' => [
                'labels' => $platform_labels,
                'values' => $platform_values
            ],
            'developers' => [
                'labels' => $dev_labels,
                'values' => $dev_values
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_tracking_charts: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}