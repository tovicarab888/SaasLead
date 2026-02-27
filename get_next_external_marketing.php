<?php
/**
 * GET_NEXT_EXTERNAL_MARKETING.PHP - LEADENGINE
 * Version: 1.0.0 - Round robin untuk external marketing
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/get_next_external_marketing.log');

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

function writeExtLog($message, $data = null) {
    $log_dir = LOG_PATH;
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_file = $log_dir . 'get_next_external_marketing.log';
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data !== null) {
        $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeExtLog("===== GET NEXT EXTERNAL MARKETING =====");

// Cek autentikasi (bisa dari sistem internal, tidak perlu user login)
// Atau bisa dengan API key internal
$internal_key = $_GET['key'] ?? $_POST['key'] ?? '';
if ($internal_key !== 'taufikmarie_internal_7878') {
    // Jika tidak pakai internal key, cek session admin
    if (!isAdmin() && !isManager()) {
        writeExtLog("ERROR: Unauthorized");
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    }
}

$conn = getDB();
if (!$conn) {
    writeExtLog("ERROR: Koneksi database gagal");
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Rate limiting
$ip = getClientIP();
$rate_key = 'get_next_external_' . $ip;
if (!checkRateLimit($rate_key, 30, 60, 300)) {
    writeExtLog("ERROR: Rate limit exceeded for IP: $ip");
    http_response_code(429);
    die(json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']));
}

try {
    // CEK APAKAH TABEL marketing_external_team ADA
    $table_check = $conn->query("SHOW TABLES LIKE 'marketing_external_team'");
    if ($table_check->rowCount() == 0) {
        // Buat tabel jika belum ada
        $conn->exec("
            CREATE TABLE IF NOT EXISTS marketing_external_team (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                super_admin_id INT DEFAULT 1,
                round_robin_order INT DEFAULT 0,
                last_assigned DATETIME DEFAULT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY user_id (user_id),
                KEY idx_round_robin (round_robin_order, last_assigned),
                KEY idx_is_active (is_active),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (super_admin_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        writeExtLog("Table marketing_external_team created");
    }

    // CEK APAKAH ADA MARKETING EXTERNAL YANG AKTIF
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM marketing_external_team met
        JOIN users u ON met.user_id = u.id
        WHERE met.is_active = 1 AND u.is_active = 1
    ");
    $stmt->execute();
    $total_active = $stmt->fetchColumn();

    if ($total_active == 0) {
        writeExtLog("WARNING: Tidak ada external marketing aktif, fallback ke super admin");
        // Fallback ke super admin (user_id = 1)
        $fallback = getSuperAdminData($conn);
        echo json_encode([
            'success' => true,
            'is_fallback' => true,
            'data' => $fallback,
            'message' => 'Tidak ada external marketing aktif, menggunakan super admin'
        ]);
        exit();
    }

    // ROUND ROBIN: Ambil external marketing dengan last_assigned terlama
    $stmt = $conn->prepare("
        SELECT
            met.id,
            met.user_id,
            met.round_robin_order,
            met.last_assigned,
            u.id as user_id,
            u.nama_lengkap,
            u.phone,
            u.email,
            u.username,
            u.profile_photo
        FROM marketing_external_team met
        JOIN users u ON met.user_id = u.id
        WHERE met.is_active = 1 AND u.is_active = 1
        ORDER BY
            CASE WHEN met.last_assigned IS NULL THEN 0 ELSE 1 END,
            met.round_robin_order ASC,
            met.last_assigned ASC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute();
    $marketing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$marketing) {
        writeExtLog("ERROR: Tidak ada external marketing yang tersedia");
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Tidak ada external marketing tersedia']));
    }

    writeExtLog("Selected marketing: ID " . $marketing['id'] . ", User ID: " . $marketing['user_id']);

    // UPDATE last_assigned
    $update = $conn->prepare("
        UPDATE marketing_external_team
        SET last_assigned = NOW()
        WHERE id = ?
    ");
    $update->execute([$marketing['id']]);

    // AMBIL NOTIFICATION TEMPLATE DARI MARKETING_CONFIG
    $template_stmt = $conn->prepare("
        SELECT notification_template
        FROM marketing_team
        WHERE user_id = ? AND is_active = 1
        LIMIT 1
    ");
    $template_stmt->execute([$marketing['user_id']]);
    $template = $template_stmt->fetchColumn();

    if (!$template) {
        $template = "🔔 *LEAD BARU UNTUK ANDA!*\n\nHalo *{marketing_name}*,\n\nAnda mendapatkan lead baru:\n• Nama: {customer_name}\n• WhatsApp: {customer_phone}\n• Lokasi: {location}\n• Waktu: {datetime}\n\nSegera hubungi customer:\nhttps://wa.me/{customer_phone}\n\nTerima kasih,\n*LeadEngine*";
    }

    // FORMAT RESPONSE
    $response = [
        'id' => $marketing['id'],
        'user_id' => $marketing['user_id'],
        'nama_lengkap' => $marketing['nama_lengkap'],
        'phone' => $marketing['phone'],
        'email' => $marketing['email'],
        'username' => $marketing['username'],
        'profile_photo' => $marketing['profile_photo'],
        'round_robin_order' => $marketing['round_robin_order'],
        'last_assigned' => $marketing['last_assigned'],
        'notification_template' => $template
    ];

    // Tambah informasi dari marketing_config untuk WhatsApp API
    $config_stmt = $conn->prepare("SELECT * FROM marketing_config WHERE id = 2");
    $config_stmt->execute();
    $config = $config_stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        $response['number_id'] = $config['number_id'];
        $response['access_token'] = $config['access_token'];
        $response['notification_number_id'] = $config['notification_number_id'] ?? NOTIFICATION_NUMBER_ID;
    } else {
        $response['number_id'] = MARKETING_NUMBER_ID;
        $response['access_token'] = MARKETING_TOKEN;
        $response['notification_number_id'] = NOTIFICATION_NUMBER_ID;
    }

    writeExtLog("SUCCESS: Return marketing " . $marketing['nama_lengkap']);

    echo json_encode([
        'success' => true,
        'data' => $response,
        'total_active' => $total_active
    ]);

} catch (Exception $e) {
    writeExtLog("ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>