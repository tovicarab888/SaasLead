<?php
/**
 * GET TRACKING STATS - Untuk stats cards
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
    
    // Build conditions - PASTIKAN PAKAI ALIAS
    $conditions = ["DATE(created_at) BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];
    
    if ($platform !== 'all') {
        $conditions[] = "pixel_type = ?";
        $params[] = $platform;
    }
    
    // Filter developer
    if (function_exists('isDeveloper') && isDeveloper() && isset($_SESSION['user_id'])) {
        $conditions[] = "developer_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($developer_id !== 'all') {
        $conditions[] = "developer_id = ?";
        $params[] = intval($developer_id);
    }
    
    $where_clause = implode(" AND ", $conditions);
    
    // Statistik hari ini
    $today_sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM tracking_logs 
        WHERE DATE(created_at) = CURDATE() AND $where_clause
    ";
    $today_stmt = $conn->prepare($today_sql);
    $today_stmt->execute($params);
    $today = $today_stmt->fetch();
    
    // Statistik kemarin
    $yesterday_sql = "
        SELECT COUNT(*) as total
        FROM tracking_logs 
        WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND $where_clause
    ";
    $yesterday_stmt = $conn->prepare($yesterday_sql);
    $yesterday_stmt->execute($params);
    $yesterday = $yesterday_stmt->fetch();
    
    // Hitung trend
    $today_trend = 0;
    if ($yesterday['total'] > 0) {
        $today_trend = round((($today['total'] - $yesterday['total']) / $yesterday['total']) * 100, 1);
    }
    
    // Statistik bulan ini
    $month_sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM tracking_logs 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
          AND YEAR(created_at) = YEAR(CURDATE()) 
          AND $where_clause
    ";
    $month_stmt = $conn->prepare($month_sql);
    $month_stmt->execute($params);
    $month = $month_stmt->fetch();
    
    // Total statistik periode
    $total_sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM tracking_logs 
        WHERE $where_clause
    ";
    $total_stmt = $conn->prepare($total_sql);
    $total_stmt->execute($params);
    $total = $total_stmt->fetch();
    
    $success_rate = $total['total'] > 0 ? round(($total['sent'] / $total['total']) * 100, 1) : 0;
    $failed_rate = $total['total'] > 0 ? round(($total['failed'] / $total['total']) * 100, 1) : 0;
    $month_progress = $month['total'] > 0 ? round(($month['total'] / 1000) * 100, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'today' => intval($today['total'] ?? 0),
            'today_sent' => intval($today['sent'] ?? 0),
            'today_failed' => intval($today['failed'] ?? 0),
            'today_trend' => $today_trend,
            'month' => intval($month['total'] ?? 0),
            'month_sent' => intval($month['sent'] ?? 0),
            'month_failed' => intval($month['failed'] ?? 0),
            'month_progress' => $month_progress,
            'total' => intval($total['total'] ?? 0),
            'sent' => intval($total['sent'] ?? 0),
            'failed' => intval($total['failed'] ?? 0),
            'pending' => intval($total['pending'] ?? 0),
            'success_rate' => $success_rate,
            'failed_rate' => $failed_rate,
            'success_count' => intval($total['sent'] ?? 0),
            'failed_count' => intval($total['failed'] ?? 0)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_tracking_stats: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}