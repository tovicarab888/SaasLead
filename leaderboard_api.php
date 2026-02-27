<?php
/**
 * LEADERBOARD_API.PHP - LEADENGINE
 * Version: 1.0.0 - API untuk Leaderboard Marketing
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/leaderboard.log');

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

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

$log_dir = dirname(__DIR__, 2) . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/leaderboard.log';

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

writeLog("========== LEADERBOARD API DIPANGGIL ==========");

// Cek session marketing
if (!isMarketing()) {
    writeLog("ERROR: Bukan marketing");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = getDB();
if (!$conn) {
    writeLog("ERROR: Database connection failed");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$marketing_id = $_SESSION['marketing_id'];
$developer_id = $_SESSION['marketing_developer_id'] ?? 0;

$action = $_GET['action'] ?? '';

writeLog("Action: $action, Marketing ID: $marketing_id, Developer ID: $developer_id");

if ($action !== 'get') {
    writeLog("ERROR: Invalid action: $action");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

if ($developer_id <= 0) {
    writeLog("ERROR: Developer ID tidak valid");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Developer ID tidak valid']);
    exit();
}

$period = $_GET['period'] ?? 'week';
$sort_by = $_GET['sort_by'] ?? 'deal';

writeLog("Period: $period, Sort by: $sort_by");

// Tentukan tanggal berdasarkan periode
$date_condition = '';
switch ($period) {
    case 'today':
        $date_condition = "DATE(l.created_at) = CURDATE()";
        break;
    case 'week':
        $date_condition = "YEARWEEK(l.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
        $date_condition = "MONTH(l.created_at) = MONTH(CURDATE()) AND YEAR(l.created_at) = YEAR(CURDATE())";
        break;
    default:
        $date_condition = "YEARWEEK(l.created_at, 1) = YEARWEEK(CURDATE(), 1)";
}

$deal_statuses = ['Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'];
$deal_placeholders = implode(',', array_fill(0, count($deal_statuses), '?'));

try {
    // Query leaderboard
    $sql = "
        SELECT 
            m.id,
            m.nama_lengkap,
            m.phone,
            m.username,
            COUNT(DISTINCT l.id) as total_leads,
            COUNT(DISTINCT CASE WHEN l.status IN ($deal_placeholders) THEN l.id END) as total_deal,
            COUNT(DISTINCT ma.id) as follow_up,
            COALESCE(AVG(l.lead_score), 0) as avg_score
        FROM marketing_team m
        LEFT JOIN leads l ON m.id = l.assigned_marketing_team_id 
            AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
            AND $date_condition
        LEFT JOIN marketing_activities ma ON m.id = ma.marketing_id 
            AND DATE(ma.created_at) = DATE(l.created_at)
        WHERE m.developer_id = ? AND m.is_active = 1
        GROUP BY m.id
        HAVING total_leads > 0 OR total_deal > 0
    ";
    
    $params = array_merge($deal_statuses, [$developer_id]);
    
    // Order by
    if ($sort_by === 'deal') {
        $sql .= " ORDER BY total_deal DESC, total_leads DESC, avg_score DESC";
    } else if ($sort_by === 'leads') {
        $sql .= " ORDER BY total_leads DESC, total_deal DESC, avg_score DESC";
    } else {
        $sql .= " ORDER BY avg_score DESC, total_deal DESC, total_leads DESC";
    }
    
    writeLog("SQL Query:", $sql);
    writeLog("Params:", $params);
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $leaderboard = $stmt->fetchAll();
    
    writeLog("Leaderboard data fetched: " . count($leaderboard) . " rows");
    
    echo json_encode([
        'success' => true,
        'data' => $leaderboard,
        'period' => $period,
        'sort_by' => $sort_by,
        'developer_id' => $developer_id
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
?>