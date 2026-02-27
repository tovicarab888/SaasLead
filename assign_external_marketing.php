<?php
/**
 * ASSIGN_EXTERNAL_MARKETING.PHP - LEADENGINE
 * Version: 1.0.0 - Assign external marketing ke sistem (Super Admin only)
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/assign_external_marketing.log');

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

function writeAssignLog($message, $data = null) {
    $log_dir = LOG_PATH;
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_file = $log_dir . 'assign_external_marketing.log';
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data !== null) {
        $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeAssignLog("===== ASSIGN EXTERNAL MARKETING =====");

// Cek autentikasi - hanya Super Admin
if (!isAdmin()) {
    writeAssignLog("ERROR: Unauthorized - bukan admin");
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya untuk Super Admin.']));
}

// CSRF token check
$csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCSRFToken($csrf_token)) {
    writeAssignLog("ERROR: Invalid CSRF token");
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
}

$conn = getDB();
if (!$conn) {
    writeAssignLog("ERROR: Koneksi database gagal");
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Rate limiting
$ip = getClientIP();
$rate_key = 'assign_external_' . $ip;
if (!checkRateLimit($rate_key, 10, 60, 300)) {
    writeAssignLog("ERROR: Rate limit exceeded for IP: $ip");
    http_response_code(429);
    die(json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']));
}

// Ambil data dari request
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

writeAssignLog("Input: " . json_encode($input));

$action = $input['action'] ?? 'assign';

// ============================================
// ACTION: ASSIGN NEW EXTERNAL MARKETING
// ============================================
if ($action === 'assign') {
    $user_id = (int)($input['user_id'] ?? 0);
    $super_admin_id = (int)($input['super_admin_id'] ?? 1);
    $round_robin_order = isset($input['round_robin_order']) ? (int)$input['round_robin_order'] : null;
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    writeAssignLog("Assign: user_id=$user_id, super_admin_id=$super_admin_id");

    if ($user_id <= 0) {
        writeAssignLog("ERROR: user_id tidak valid");
        die(json_encode(['success' => false, 'message' => 'User ID tidak valid']));
    }

    try {
        // CEK APAKAH USER ADA
        $user_check = $conn->prepare("SELECT id, nama_lengkap, role FROM users WHERE id = ? AND is_active = 1");
        $user_check->execute([$user_id]);
        $user = $user_check->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            writeAssignLog("ERROR: User ID $user_id tidak ditemukan atau tidak aktif");
            die(json_encode(['success' => false, 'message' => 'User tidak ditemukan atau tidak aktif']));
        }

        // CEK APAKAH SUDAH TERDAFTAR DI EXTERNAL TEAM
        $check = $conn->prepare("SELECT id FROM marketing_external_team WHERE user_id = ?");
        $check->execute([$user_id]);

        if ($check->fetch()) {
            writeAssignLog("WARNING: User $user_id sudah terdaftar sebagai external marketing");

            // Update saja
            $update = $conn->prepare("
                UPDATE marketing_external_team
                SET super_admin_id = ?,
                    round_robin_order = COALESCE(?, round_robin_order),
                    is_active = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $update->execute([$super_admin_id, $round_robin_order, $is_active, $user_id]);

            $affected = $update->rowCount();

            writeAssignLog("SUKSES: Update external marketing user $user_id");

            echo json_encode([
                'success' => true,
                'message' => 'External marketing berhasil diupdate',
                'data' => [
                    'user_id' => $user_id,
                    'nama_lengkap' => $user['nama_lengkap'],
                    'action' => 'updated'
                ]
            ]);
            exit();
        }

        // Jika round_robin_order tidak diberikan, set ke urutan terakhir + 1
        if ($round_robin_order === null) {
            $order_stmt = $conn->query("SELECT MAX(round_robin_order) as max_order FROM marketing_external_team");
            $max_order = $order_stmt->fetchColumn();
            $round_robin_order = ($max_order !== null) ? $max_order + 1 : 1;
        }

        // Insert baru
        $insert = $conn->prepare("
            INSERT INTO marketing_external_team
                (user_id, super_admin_id, round_robin_order, is_active, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, NOW(), NOW())
        ");
        $insert->execute([$user_id, $super_admin_id, $round_robin_order, $is_active]);

        $id = $conn->lastInsertId();

        writeAssignLog("SUKSES: Assign external marketing user $user_id dengan ID $id, order $round_robin_order");

        echo json_encode([
            'success' => true,
            'message' => 'External marketing berhasil ditambahkan',
            'data' => [
                'id' => $id,
                'user_id' => $user_id,
                'nama_lengkap' => $user['nama_lengkap'],
                'round_robin_order' => $round_robin_order,
                'action' => 'inserted'
            ]
        ]);

    } catch (Exception $e) {
        writeAssignLog("ERROR: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: LIST ALL EXTERNAL MARKETING
// ============================================
elseif ($action === 'list') {
    writeAssignLog("ACTION: list external marketing");

    try {
        $sql = "
            SELECT
                met.id,
                met.user_id,
                met.super_admin_id,
                met.round_robin_order,
                met.last_assigned,
                met.is_active as team_active,
                met.created_at,
                met.updated_at,
                u.nama_lengkap,
                u.username,
                u.email,
                u.phone,
                u.profile_photo,
                u.is_active as user_active,
                u.last_login,
                sa.nama_lengkap as super_admin_name
            FROM marketing_external_team met
            JOIN users u ON met.user_id = u.id
            LEFT JOIN users sa ON met.super_admin_id = sa.id
            ORDER BY met.round_robin_order ASC, met.id ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        writeAssignLog("Ditemukan " . count($list) . " external marketing");

        echo json_encode([
            'success' => true,
            'data' => $list,
            'count' => count($list)
        ]);

    } catch (Exception $e) {
        writeAssignLog("ERROR list: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: UPDATE STATUS / ORDER
// ============================================
elseif ($action === 'update') {
    $id = (int)($input['id'] ?? 0);
    $round_robin_order = isset($input['round_robin_order']) ? (int)$input['round_robin_order'] : null;
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : null;

    writeAssignLog("Update: id=$id, order=$round_robin_order, active=$is_active");

    if ($id <= 0) {
        writeAssignLog("ERROR: id tidak valid");
        die(json_encode(['success' => false, 'message' => 'ID tidak valid']));
    }

    try {
        $update_fields = [];
        $params = [];

        if ($round_robin_order !== null) {
            $update_fields[] = "round_robin_order = ?";
            $params[] = $round_robin_order;
        }

        if ($is_active !== null) {
            $update_fields[] = "is_active = ?";
            $params[] = $is_active;
        }

        if (empty($update_fields)) {
            writeAssignLog("ERROR: Tidak ada field yang diupdate");
            die(json_encode(['success' => false, 'message' => 'Tidak ada data yang diupdate']));
        }

        $update_fields[] = "updated_at = NOW()";
        $params[] = $id;

        $sql = "UPDATE marketing_external_team SET " . implode(", ", $update_fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $affected = $stmt->rowCount();

        writeAssignLog("SUKSES: Update ID $id, affected: $affected rows");

        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil diupdate',
            'affected_rows' => $affected
        ]);

    } catch (Exception $e) {
        writeAssignLog("ERROR update: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: REMOVE (DELETE)
// ============================================
elseif ($action === 'remove') {
    $id = (int)($input['id'] ?? 0);

    writeAssignLog("Remove: id=$id");

    if ($id <= 0) {
        writeAssignLog("ERROR: id tidak valid");
        die(json_encode(['success' => false, 'message' => 'ID tidak valid']));
    }

    try {
        // Cek apakah ada di developer_external_access
        $check_access = $conn->prepare("SELECT COUNT(*) FROM developer_external_access WHERE marketing_external_id = ?");
        $check_access->execute([$id]);
        $access_count = $check_access->fetchColumn();

        if ($access_count > 0) {
            writeAssignLog("WARNING: ID $id memiliki $access_count akses developer, akan dihapus juga");
            // Hapus akses terkait (ON DELETE CASCADE seharusnya)
        }

        $stmt = $conn->prepare("DELETE FROM marketing_external_team WHERE id = ?");
        $stmt->execute([$id]);

        $affected = $stmt->rowCount();

        writeAssignLog("SUKSES: Remove ID $id, affected: $affected rows");

        echo json_encode([
            'success' => true,
            'message' => 'External marketing berhasil dihapus',
            'affected_rows' => $affected
        ]);

    } catch (Exception $e) {
        writeAssignLog("ERROR remove: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION DEFAULT
// ============================================
else {
    writeAssignLog("ERROR: Action tidak dikenal: $action");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Action tidak dikenal',
        'available_actions' => ['assign', 'list', 'update', 'remove']
    ]);
}
?>