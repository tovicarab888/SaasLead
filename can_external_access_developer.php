<?php
/**
 * CAN_EXTERNAL_ACCESS_DEVELOPER.PHP - LEADENGINE
 * Version: 1.0.0 - Cek apakah external marketing boleh akses developer
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/can_external_access_developer.log');

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

function writeCanLog($message, $data = null) {
    $log_dir = LOG_PATH;
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_file = $log_dir . 'can_external_access_developer.log';
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data !== null) {
        $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

/**
 * Fungsi untuk mengecek apakah external marketing boleh mengakses developer
 * @param int $marketing_id ID dari marketing_team
 * @param int $developer_id ID developer yang ingin diakses (0 untuk semua)
 * @return array ['can_access' => bool, 'allowed_developers' => array]
 */
function canExternalAccessDeveloper($marketing_id, $developer_id = 0) {
    $conn = getDB();
    if (!$conn) {
        writeCanLog("ERROR: Koneksi database gagal");
        return ['can_access' => false, 'allowed_developers' => []];
    }

    try {
        // Cek apakah marketing ini external
        $type_check = $conn->prepare("
            SELECT mt.type_name
            FROM marketing_team m
            JOIN marketing_types mt ON m.marketing_type_id = mt.id
            WHERE m.id = ?
        ");
        $type_check->execute([$marketing_id]);
        $type = $type_check->fetchColumn();

        $is_external = ($type === 'external');

        if (!$is_external) {
            writeCanLog("INFO: Marketing $marketing_id bukan external");
            return ['can_access' => false, 'allowed_developers' => []];
        }

        // Cari marketing_external_id dari tabel marketing_external_team
        $met_stmt = $conn->prepare("
            SELECT met.id
            FROM marketing_external_team met
            JOIN users u ON met.user_id = u.id
            WHERE u.id = (SELECT user_id FROM marketing_team WHERE id = ?)
        ");
        $met_stmt->execute([$marketing_id]);
        $marketing_external_id = $met_stmt->fetchColumn();

        if (!$marketing_external_id) {
            writeCanLog("INFO: Marketing $marketing_id belum terdaftar di marketing_external_team");
            return ['can_access' => false, 'allowed_developers' => []];
        }

        // Ambil semua developer yang diizinkan
        $access_stmt = $conn->prepare("
            SELECT dea.developer_id
            FROM developer_external_access dea
            WHERE dea.marketing_external_id = ? AND dea.can_access = 1
        ");
        $access_stmt->execute([$marketing_external_id]);
        $allowed = $access_stmt->fetchAll(PDO::FETCH_COLUMN);

        writeCanLog("Marketing $marketing_id (external_id: $marketing_external_id) memiliki akses ke " . count($allowed) . " developer");

        if ($developer_id > 0) {
            $can_access = in_array($developer_id, $allowed);
            writeCanLog("Cek akses ke developer $developer_id: " . ($can_access ? 'YES' : 'NO'));
            return [
                'can_access' => $can_access,
                'allowed_developers' => $allowed,
                'marketing_external_id' => $marketing_external_id
            ];
        } else {
            return [
                'can_access' => !empty($allowed),
                'allowed_developers' => $allowed,
                'marketing_external_id' => $marketing_external_id
            ];
        }

    } catch (Exception $e) {
        writeCanLog("ERROR: " . $e->getMessage());
        return ['can_access' => false, 'allowed_developers' => []];
    }
}

// ============================================
// HANDLE API REQUEST
// ============================================
writeCanLog("===== CAN EXTERNAL ACCESS DEVELOPER =====");
writeCanLog("Session: " . json_encode($_SESSION));

// Cek autentikasi (bisa dari sistem internal atau session)
$internal_key = $_GET['key'] ?? $_POST['key'] ?? '';
$is_internal = ($internal_key === 'taufikmarie_internal_7878');

if (!$is_internal && !isMarketing() && !isAdmin()) {
    writeCanLog("ERROR: Unauthorized");
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Rate limiting
$ip = getClientIP();
$rate_key = 'can_external_access_' . $ip;
if (!checkRateLimit($rate_key, 30, 60, 300)) {
    writeCanLog("ERROR: Rate limit exceeded for IP: $ip");
    http_response_code(429);
    die(json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']));
}

// Ambil parameter
$marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : (isset($_POST['marketing_id']) ? (int)$_POST['marketing_id'] : 0);
$developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : (isset($_POST['developer_id']) ? (int)$_POST['developer_id'] : 0);

// Jika dari session marketing
if (!$marketing_id && isMarketing()) {
    $marketing_id = $_SESSION['marketing_id'];
}

writeCanLog("Marketing ID: $marketing_id, Developer ID: $developer_id");

if ($marketing_id <= 0) {
    writeCanLog("ERROR: marketing_id tidak valid");
    die(json_encode(['success' => false, 'message' => 'Marketing ID tidak valid']));
}

$result = canExternalAccessDeveloper($marketing_id, $developer_id);

if ($result['can_access'] === false && !empty($result['allowed_developers'])) {
    // Ini berarti $developer_id tidak ada dalam daftar izin
    $result['message'] = 'Anda tidak memiliki akses ke developer ini';
} elseif (empty($result['allowed_developers'])) {
    $result['message'] = 'Anda belum memiliki akses ke developer manapun';
} else {
    $result['message'] = 'Akses valid';
}

echo json_encode(array_merge(['success' => true], $result));
?>