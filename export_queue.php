<?php
/**
 * EXPORT_QUEUE.PHP - LEADENGINE
 * Version: 1.0.0 - Handle export besar via queue
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/export_queue.log');

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';

function writeExportLog($message, $data = null) {
    $log_dir = LOG_PATH;
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_file = $log_dir . 'export_queue.log';
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data !== null) {
        $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeExportLog("===== EXPORT QUEUE =====");

// Cek autentikasi
if (!checkAuth()) {
    writeExportLog("ERROR: Unauthorized");
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Hanya admin, manager, finance_platform yang bisa export besar
$allowed_roles = ['admin', 'manager', 'finance_platform'];
if (!in_array(getCurrentRole(), $allowed_roles)) {
    writeExportLog("ERROR: Forbidden - role: " . getCurrentRole());
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Akses ditolak']));
}

// CSRF token check untuk POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        writeExportLog("ERROR: Invalid CSRF token");
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
    }
}

$conn = getDB();
if (!$conn) {
    writeExportLog("ERROR: Koneksi database gagal");
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Rate limiting
$ip = getClientIP();
$rate_key = 'export_queue_' . $ip;
if (!checkRateLimit($rate_key, 5, 300, 900)) { // 5 exports per 5 menit
    writeExportLog("ERROR: Rate limit exceeded for IP: $ip");
    http_response_code(429);
    die(json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan export. Silakan coba lagi nanti.']));
}

// Ambil action
$action = $_GET['action'] ?? $_POST['action'] ?? 'queue';

// ============================================
// ACTION: QUEUE EXPORT
// ============================================
if ($action === 'queue') {
    $export_type = $_POST['export_type'] ?? $_GET['export_type'] ?? 'leads';
    $format = $_POST['format'] ?? $_GET['format'] ?? 'csv';
    $filters = $_POST['filters'] ?? $_GET['filters'] ?? [];
    $notify_whatsapp = isset($_POST['notify_whatsapp']) ? (bool)$_POST['notify_whatsapp'] : true;

    if (is_string($filters)) {
        $filters = json_decode($filters, true) ?: [];
    }

    writeExportLog("Queue export: type=$export_type, format=$format, filters=" . json_encode($filters));

    $user_id = $_SESSION['user_id'] ?? 0;
    $user_role = getCurrentRole();
    $user_name = $_SESSION['nama_lengkap'] ?? 'User';
    $user_phone = '';

    // Ambil nomor WhatsApp user untuk notifikasi
    if ($user_role === 'admin' || $user_role === 'manager') {
        $stmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_phone = $stmt->fetchColumn();
    }

    if (empty($user_phone)) {
        // Default ke nomor admin
        $user_phone = MARKETING_PHONE;
    }

    try {
        // Hitung estimasi jumlah data
        $count_sql = "SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'";
        $count_params = [];

        if ($user_role === 'finance_platform') {
            $count_sql .= " AND assigned_type = 'external'";
        }

        // Apply filters
        if (!empty($filters['start_date'])) {
            $count_sql .= " AND DATE(created_at) >= ?";
            $count_params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $count_sql .= " AND DATE(created_at) <= ?";
            $count_params[] = $filters['end_date'];
        }
        if (!empty($filters['location'])) {
            $count_sql .= " AND location_key = ?";
            $count_params[] = $filters['location'];
        }
        if (!empty($filters['status'])) {
            $count_sql .= " AND status = ?";
            $count_params[] = $filters['status'];
        }
        if (!empty($filters['assigned_type'])) {
            $count_sql .= " AND assigned_type = ?";
            $count_params[] = $filters['assigned_type'];
        }

        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute($count_params);
        $total_records = $count_stmt->fetchColumn();

        writeExportLog("Estimated records: $total_records");

        // Generate unique ID untuk export
        $export_id = 'EXPORT_' . time() . '_' . bin2hex(random_bytes(8));

        // Buat payload
        $payload = [
            'export_id' => $export_id,
            'user_id' => $user_id,
            'user_role' => $user_role,
            'user_name' => $user_name,
            'user_phone' => $user_phone,
            'export_type' => $export_type,
            'format' => $format,
            'filters' => $filters,
            'total_records' => $total_records,
            'notify_whatsapp' => $notify_whatsapp,
            'request_time' => date('Y-m-d H:i:s')
        ];

        // Simpan ke job_queue
        $stmt = $conn->prepare("
            INSERT INTO job_queue
                (type, payload, status, retry_count, created_at, updated_at)
            VALUES
                ('export', ?, 'pending', 0, NOW(), NOW())
        ");
        $stmt->execute([json_encode($payload)]);

        $job_id = $conn->lastInsertId();

        writeExportLog("Export job created: ID $job_id, export_id $export_id");

        echo json_encode([
            'success' => true,
            'message' => 'Export sedang diproses. Anda akan mendapat notifikasi via WhatsApp setelah selesai.',
            'data' => [
                'job_id' => $job_id,
                'export_id' => $export_id,
                'total_records' => $total_records,
                'estimated_time' => ceil($total_records / 1000) . ' menit'
            ]
        ]);

    } catch (Exception $e) {
        writeExportLog("ERROR queue: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: CHECK STATUS
// ============================================
elseif ($action === 'status') {
    $job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
    $export_id = $_GET['export_id'] ?? '';

    writeExportLog("Check status: job_id=$job_id, export_id=$export_id");

    try {
        if ($job_id > 0) {
            $stmt = $conn->prepare("SELECT * FROM job_queue WHERE id = ? AND type = 'export'");
            $stmt->execute([$job_id]);
        } elseif (!empty($export_id)) {
            $stmt = $conn->prepare("
                SELECT * FROM job_queue
                WHERE type = 'export' AND JSON_EXTRACT(payload, '$.export_id') = ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$export_id]);
        } else {
            die(json_encode(['success' => false, 'message' => 'Job ID atau Export ID diperlukan']));
        }

        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            die(json_encode(['success' => false, 'message' => 'Job tidak ditemukan']));
        }

        $payload = json_decode($job['payload'], true);

        $response = [
            'success' => true,
            'job_id' => $job['id'],
            'export_id' => $payload['export_id'] ?? null,
            'status' => $job['status'],
            'retry_count' => $job['retry_count'],
            'created_at' => $job['created_at'],
            'updated_at' => $job['updated_at'],
            'total_records' => $payload['total_records'] ?? 0,
            'export_type' => $payload['export_type'] ?? 'leads',
            'format' => $payload['format'] ?? 'csv'
        ];

        if ($job['status'] === 'done' && !empty($job['response'])) {
            $response_data = json_decode($job['response'], true);
            $response['download_url'] = $response_data['download_url'] ?? null;
            $response['expires_at'] = $response_data['expires_at'] ?? null;
            $response['file_size'] = $response_data['file_size'] ?? null;
        }

        echo json_encode($response);

    } catch (Exception $e) {
        writeExportLog("ERROR status: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: DOWNLOAD (untuk file yang sudah siap)
// ============================================
elseif ($action === 'download') {
    $token = $_GET['token'] ?? '';

    if (empty($token)) {
        http_response_code(400);
        die('Token diperlukan');
    }

    // Cek di session atau database untuk token
    // Token sebaiknya disimpan di tabel terpisah dengan expiry
    $export_dir = sys_get_temp_dir() . '/leadengine_exports/';
    $file_path = $export_dir . $token . '.csv';

    if (!file_exists($file_path)) {
        http_response_code(404);
        die('File tidak ditemukan atau sudah expired');
    }

    // Cek expiry (24 jam)
    if (time() - filemtime($file_path) > 86400) {
        unlink($file_path);
        http_response_code(410);
        die('File sudah expired (lebih dari 24 jam)');
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_' . date('Y-m-d_His') . '.csv"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    readfile($file_path);
    exit();
}

// ============================================
// ACTION: LIST MY EXPORTS
// ============================================
elseif ($action === 'my_exports') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

    writeExportLog("List my exports: user_id=$user_id");

    try {
        $stmt = $conn->prepare("
            SELECT *
            FROM job_queue
            WHERE type = 'export' AND JSON_EXTRACT(payload, '$.user_id') = ?
            ORDER BY id DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($jobs as $job) {
            $payload = json_decode($job['payload'], true);
            $result[] = [
                'job_id' => $job['id'],
                'export_id' => $payload['export_id'] ?? null,
                'status' => $job['status'],
                'export_type' => $payload['export_type'] ?? 'leads',
                'format' => $payload['format'] ?? 'csv',
                'total_records' => $payload['total_records'] ?? 0,
                'created_at' => $job['created_at'],
                'completed_at' => $job['status'] === 'done' ? $job['updated_at'] : null
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $result,
            'count' => count($result)
        ]);

    } catch (Exception $e) {
        writeExportLog("ERROR my_exports: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: CANCEL
// ============================================
elseif ($action === 'cancel') {
    $job_id = (int)($_POST['job_id'] ?? $_GET['job_id'] ?? 0);

    writeExportLog("Cancel export: job_id=$job_id");

    if ($job_id <= 0) {
        die(json_encode(['success' => false, 'message' => 'Job ID diperlukan']));
    }

    try {
        $stmt = $conn->prepare("
            UPDATE job_queue
            SET status = 'failed', response = 'Cancelled by user', updated_at = NOW()
            WHERE id = ? AND type = 'export' AND status = 'pending'
        ");
        $stmt->execute([$job_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Export dibatalkan']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Job tidak ditemukan atau sudah diproses']);
        }

    } catch (Exception $e) {
        writeExportLog("ERROR cancel: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION DEFAULT
// ============================================
else {
    writeExportLog("ERROR: Action tidak dikenal: $action");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Action tidak dikenal',
        'available_actions' => ['queue', 'status', 'download', 'my_exports', 'cancel']
    ]);
}
?>