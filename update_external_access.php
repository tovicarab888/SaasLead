<?php
/**
 * UPDATE_EXTERNAL_ACCESS.PHP - LEADENGINE
 * Version: 1.0.0 - Update izin akses external marketing ke developer
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/update_external_access.log');

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';

function writeAccessLog($message, $data = null) {
    $log_dir = LOG_PATH;
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_file = $log_dir . 'update_external_access.log';
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data !== null) {
        $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeAccessLog("===== UPDATE EXTERNAL ACCESS =====");

// Cek autentikasi - hanya Super Admin
if (!isAdmin()) {
    writeAccessLog("ERROR: Unauthorized - bukan admin");
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya untuk Super Admin.']));
}

// CSRF token check
$csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCSRFToken($csrf_token)) {
    writeAccessLog("ERROR: Invalid CSRF token");
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
}

$conn = getDB();
if (!$conn) {
    writeAccessLog("ERROR: Koneksi database gagal");
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Rate limiting
$ip = getClientIP();
$rate_key = 'update_external_access_' . $ip;
if (!checkRateLimit($rate_key, 20, 60, 300)) {
    writeAccessLog("ERROR: Rate limit exceeded for IP: $ip");
    http_response_code(429);
    die(json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']));
}

// Ambil data dari request
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

writeAccessLog("Input: " . json_encode($input));

$action = $input['action'] ?? 'update';

// ============================================
// ACTION: UPDATE ACCESS (SET / REMOVE)
// ============================================
if ($action === 'update') {
    $marketing_external_id = (int)($input['marketing_external_id'] ?? 0);
    $developer_ids = isset($input['developer_ids']) ? (array)$input['developer_ids'] : [];
    $mode = $input['mode'] ?? 'set'; // 'set' atau 'add' atau 'remove'

    writeAccessLog("Update: marketing_external_id=$marketing_external_id, mode=$mode, developers=" . json_encode($developer_ids));

    if ($marketing_external_id <= 0) {
        writeAccessLog("ERROR: marketing_external_id tidak valid");
        die(json_encode(['success' => false, 'message' => 'Marketing External ID tidak valid']));
    }

    try {
        $conn->beginTransaction();

        // CEK APAKAH MARKETING EXTERNAL ADA
        $check_marketing = $conn->prepare("
            SELECT met.id, u.nama_lengkap
            FROM marketing_external_team met
            JOIN users u ON met.user_id = u.id
            WHERE met.id = ?
        ");
        $check_marketing->execute([$marketing_external_id]);
        $marketing = $check_marketing->fetch(PDO::FETCH_ASSOC);

        if (!$marketing) {
            $conn->rollBack();
            writeAccessLog("ERROR: Marketing external ID $marketing_external_id tidak ditemukan");
            die(json_encode(['success' => false, 'message' => 'Marketing external tidak ditemukan']));
        }

        if ($mode === 'set') {
            // Hapus semua akses lama
            $delete = $conn->prepare("DELETE FROM developer_external_access WHERE marketing_external_id = ?");
            $delete->execute([$marketing_external_id]);
            $deleted_count = $delete->rowCount();
            writeAccessLog("Deleted $deleted_count old access records");

            // Insert akses baru
            $inserted = 0;
            if (!empty($developer_ids)) {
                $insert = $conn->prepare("
                    INSERT INTO developer_external_access
                        (developer_id, marketing_external_id, can_access, created_at, updated_at)
                    VALUES
                        (?, ?, 1, NOW(), NOW())
                ");

                foreach ($developer_ids as $dev_id) {
                    $dev_id = (int)$dev_id;
                    if ($dev_id > 0) {
                        try {
                            $insert->execute([$dev_id, $marketing_external_id]);
                            $inserted++;
                        } catch (Exception $e) {
                            // Mungkin duplicate, skip
                            writeAccessLog("Duplicate skip untuk dev_id $dev_id");
                        }
                    }
                }
            }

            writeAccessLog("Inserted $inserted new access records");

            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Akses berhasil diupdate',
                'data' => [
                    'marketing_external_id' => $marketing_external_id,
                    'marketing_name' => $marketing['nama_lengkap'],
                    'deleted' => $deleted_count,
                    'inserted' => $inserted,
                    'mode' => 'set'
                ]
            ]);

        } elseif ($mode === 'add') {
            // Tambah akses baru (tanpa menghapus yang lama)
            $inserted = 0;
            $skipped = 0;

            if (!empty($developer_ids)) {
                $insert = $conn->prepare("
                    INSERT INTO developer_external_access
                        (developer_id, marketing_external_id, can_access, created_at, updated_at)
                    VALUES
                        (?, ?, 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        can_access = 1,
                        updated_at = NOW()
                ");

                foreach ($developer_ids as $dev_id) {
                    $dev_id = (int)$dev_id;
                    if ($dev_id > 0) {
                        $insert->execute([$dev_id, $marketing_external_id]);
                        if ($insert->rowCount() > 0) {
                            $inserted++;
                        } else {
                            $skipped++;
                        }
                    }
                }
            }

            $conn->commit();

            writeAccessLog("Added: $inserted, Skipped: $skipped");

            echo json_encode([
                'success' => true,
                'message' => 'Akses berhasil ditambahkan',
                'data' => [
                    'marketing_external_id' => $marketing_external_id,
                    'marketing_name' => $marketing['nama_lengkap'],
                    'inserted' => $inserted,
                    'skipped' => $skipped,
                    'mode' => 'add'
                ]
            ]);

        } elseif ($mode === 'remove') {
            // Hapus akses tertentu
            $deleted = 0;

            if (!empty($developer_ids)) {
                $placeholders = implode(',', array_fill(0, count($developer_ids), '?'));
                $params = array_merge($developer_ids, [$marketing_external_id]);

                $delete = $conn->prepare("
                    DELETE FROM developer_external_access
                    WHERE developer_id IN ($placeholders) AND marketing_external_id = ?
                ");
                $delete->execute($params);
                $deleted = $delete->rowCount();
            }

            $conn->commit();

            writeAccessLog("Removed: $deleted access records");

            echo json_encode([
                'success' => true,
                'message' => 'Akses berhasil dihapus',
                'data' => [
                    'marketing_external_id' => $marketing_external_id,
                    'marketing_name' => $marketing['nama_lengkap'],
                    'deleted' => $deleted,
                    'mode' => 'remove'
                ]
            ]);

        } else {
            $conn->rollBack();
            writeAccessLog("ERROR: Mode tidak dikenal: $mode");
            die(json_encode(['success' => false, 'message' => 'Mode tidak dikenal']));
        }

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        writeAccessLog("ERROR: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: GET ACCESS LIST
// ============================================
elseif ($action === 'get') {
    $marketing_external_id = isset($input['marketing_external_id']) ? (int)$input['marketing_external_id'] : 0;
    $developer_id = isset($input['developer_id']) ? (int)$input['developer_id'] : 0;

    writeAccessLog("Get access: marketing_external_id=$marketing_external_id, developer_id=$developer_id");

    try {
        if ($marketing_external_id > 0) {
            // Ambil semua developer yang diakses oleh marketing ini
            $sql = "
                SELECT
                    dea.id,
                    dea.developer_id,
                    dea.can_access,
                    dea.created_at as access_created,
                    u.nama_lengkap as developer_name,
                    u.nama_perusahaan,
                    u.email as developer_email,
                    u.phone as developer_phone,
                    u.location_access,
                    (SELECT COUNT(*) FROM units u2
                     JOIN blocks b ON u2.block_id = b.id
                     JOIN clusters c ON b.cluster_id = c.id
                     WHERE c.developer_id = u.id AND u2.status = 'AVAILABLE') as available_units
                FROM developer_external_access dea
                JOIN users u ON dea.developer_id = u.id
                WHERE dea.marketing_external_id = ? AND dea.can_access = 1
                ORDER BY u.nama_lengkap
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$marketing_external_id]);
            $access = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $access,
                'marketing_external_id' => $marketing_external_id,
                'count' => count($access)
            ]);

        } elseif ($developer_id > 0) {
            // Ambil semua marketing yang punya akses ke developer ini
            $sql = "
                SELECT
                    dea.id,
                    dea.marketing_external_id,
                    dea.can_access,
                    dea.created_at as access_created,
                    met.user_id,
                    u.nama_lengkap as marketing_name,
                    u.email as marketing_email,
                    u.phone as marketing_phone,
                    u.username
                FROM developer_external_access dea
                JOIN marketing_external_team met ON dea.marketing_external_id = met.id
                JOIN users u ON met.user_id = u.id
                WHERE dea.developer_id = ? AND dea.can_access = 1
                ORDER BY u.nama_lengkap
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$developer_id]);
            $access = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $access,
                'developer_id' => $developer_id,
                'count' => count($access)
            ]);

        } else {
            // Ambil semua akses
            $sql = "
                SELECT
                    dea.id,
                    dea.developer_id,
                    dea.marketing_external_id,
                    dea.can_access,
                    dea.created_at,
                    dev.nama_lengkap as developer_name,
                    mkt.user_id,
                    mkt_user.nama_lengkap as marketing_name
                FROM developer_external_access dea
                JOIN users dev ON dea.developer_id = dev.id
                JOIN marketing_external_team mkt ON dea.marketing_external_id = mkt.id
                JOIN users mkt_user ON mkt.user_id = mkt_user.id
                ORDER BY dev.nama_lengkap, mkt_user.nama_lengkap
                LIMIT 1000
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $access = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $access,
                'count' => count($access)
            ]);
        }

    } catch (Exception $e) {
        writeAccessLog("ERROR get: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: GET ALL DEVELOPERS (for dropdown)
// ============================================
elseif ($action === 'developers') {
    writeAccessLog("ACTION: get all developers");

    try {
        $stmt = $conn->prepare("
            SELECT
                u.id,
                u.nama_lengkap,
                u.nama_perusahaan,
                u.email,
                u.phone,
                u.location_access,
                (SELECT COUNT(*) FROM clusters WHERE developer_id = u.id) as total_clusters,
                (SELECT COUNT(*) FROM units u2
                 JOIN blocks b ON u2.block_id = b.id
                 JOIN clusters c ON b.cluster_id = c.id
                 WHERE c.developer_id = u.id) as total_units
            FROM users u
            WHERE u.role = 'developer' AND u.is_active = 1
            ORDER BY u.nama_lengkap
        ");
        $stmt->execute();
        $developers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $developers,
            'count' => count($developers)
        ]);

    } catch (Exception $e) {
        writeAccessLog("ERROR developers: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: GET ALL EXTERNAL MARKETING (for dropdown)
// ============================================
elseif ($action === 'marketing') {
    writeAccessLog("ACTION: get all external marketing");

    try {
        $stmt = $conn->prepare("
            SELECT
                met.id,
                met.user_id,
                u.nama_lengkap,
                u.username,
                u.email,
                u.phone
            FROM marketing_external_team met
            JOIN users u ON met.user_id = u.id
            WHERE met.is_active = 1 AND u.is_active = 1
            ORDER BY u.nama_lengkap
        ");
        $stmt->execute();
        $marketing = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $marketing,
            'count' => count($marketing)
        ]);

    } catch (Exception $e) {
        writeAccessLog("ERROR marketing: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION DEFAULT
// ============================================
else {
    writeAccessLog("ERROR: Action tidak dikenal: $action");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Action tidak dikenal',
        'available_actions' => ['update', 'get', 'developers', 'marketing']
    ]);
}
?>