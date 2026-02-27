<?php
/**
 * TRACKING_WORKER.PHP - LEADENGINE
 * Version: 1.0.0 - Background job untuk tracking pixel
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/tracking_worker.log');

// No session needed for background worker
require_once __DIR__ . '/config.php';

function writeWorkerLog($message, $data = null) {
    $log_dir = LOG_PATH;
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_file = $log_dir . 'tracking_worker.log';
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data !== null) {
        $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeWorkerLog("===== TRACKING WORKER STARTED =====");

// Cek apakah dijalankan dari CLI atau HTTP dengan key internal
$is_cli = (php_sapi_name() === 'cli');
$internal_key = $_GET['key'] ?? $_POST['key'] ?? '';

if (!$is_cli && $internal_key !== 'taufikmarie_internal_7878') {
    writeWorkerLog("ERROR: Unauthorized access attempt");
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$conn = getDB();
if (!$conn) {
    writeWorkerLog("ERROR: Koneksi database gagal");
    if (!$is_cli) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Database connection failed']));
    }
    exit(1);
}

// Ambil parameter
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$max_execution_time = isset($_GET['max_time']) ? (int)$_GET['max_time'] : 55; // 55 detik

writeWorkerLog("Limit: $limit, Max execution time: $max_execution_time seconds");

$start_time = time();
$processed = 0;
$success = 0;
$failed = 0;

try {
    // AMBIL JOB TRACKING DARI QUEUE
    $stmt = $conn->prepare("
        SELECT *
        FROM job_queue
        WHERE type = 'tracking' AND status = 'pending' AND retry_count < 3
        ORDER BY created_at ASC
        LIMIT ?
        FOR UPDATE SKIP LOCKED
    ");
    $stmt->execute([$limit]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    writeWorkerLog("Found " . count($jobs) . " tracking jobs to process");

    if (empty($jobs)) {
        writeWorkerLog("No jobs to process");
        if (!$is_cli) {
            echo json_encode(['success' => true, 'message' => 'No jobs to process', 'processed' => 0]);
        }
        exit(0);
    }

    $conn->beginTransaction();

    foreach ($jobs as $job) {
        // Cek waktu
        if (time() - $start_time > $max_execution_time) {
            writeWorkerLog("Max execution time reached, stopping");
            break;
        }

        $job_id = $job['id'];
        $payload = json_decode($job['payload'], true);

        writeWorkerLog("Processing job ID: $job_id, Payload: " . json_encode($payload));

        // Update status menjadi processing
        $update = $conn->prepare("UPDATE job_queue SET status = 'processing', updated_at = NOW() WHERE id = ?");
        $update->execute([$job_id]);

        $processed++;

        // Proses berdasarkan jenis tracking
        $tracking_result = ['success' => false, 'error' => 'Unknown type'];

        if ($payload['platform'] === 'meta') {
            $tracking_result = sendMetaTracking($payload['data'], $payload['developer_id'] ?? null);
        } elseif ($payload['platform'] === 'tiktok') {
            $tracking_result = sendTikTokTracking($payload['data'], $payload['developer_id'] ?? null);
        } elseif ($payload['platform'] === 'google') {
            $tracking_result = sendGATracking($payload['data'], $payload['developer_id'] ?? null);
        } elseif ($payload['platform'] === 'all') {
            // Kirim ke semua platform
            $tracking_result = sendAllTracking($payload['data'], $payload['developer_id'] ?? null);
        }

        writeWorkerLog("Job $job_id result: " . json_encode($tracking_result));

        // Update status job
        if ($tracking_result['success']) {
            $status = 'done';
            $success++;
            $response_msg = json_encode($tracking_result);
        } else {
            $failed++;
            $new_retry = $job['retry_count'] + 1;

            if ($new_retry >= 3) {
                $status = 'failed';
                writeWorkerLog("Job $job_id failed after 3 attempts");
            } else {
                $status = 'pending';
                writeWorkerLog("Job $job_id failed, retry $new_retry/3");
            }

            $response_msg = $tracking_result['error'] ?? 'Unknown error';
        }

        $update_job = $conn->prepare("
            UPDATE job_queue
            SET status = ?, retry_count = retry_count + 1, response = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $update_job->execute([$status, $response_msg, $job_id]);

        // Simpan ke tracking_logs jika ada lead_id
        if (isset($payload['data']['customer_id']) && $payload['data']['customer_id'] > 0) {
            saveTrackingLog(
                $payload['data']['customer_id'],
                $payload['developer_id'] ?? 0,
                $payload['platform'],
                $payload['data']['event_name'] ?? 'Lead',
                $payload['data']['event_id'] ?? 'JOB_' . $job_id,
                $payload,
                $status,
                $response_msg
            );
        }

        // Jeda 100ms antar request untuk menghindari rate limit
        usleep(100000);
    }

    $conn->commit();

    $execution_time = time() - $start_time;
    writeWorkerLog("Completed: $processed jobs processed, $success success, $failed failed in $execution_time seconds");

    if (!$is_cli) {
        echo json_encode([
            'success' => true,
            'message' => 'Tracking worker completed',
            'data' => [
                'processed' => $processed,
                'success' => $success,
                'failed' => $failed,
                'execution_time' => $execution_time
            ]
        ]);
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    writeWorkerLog("ERROR: " . $e->getMessage());
    if (!$is_cli) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Worker error: ' . $e->getMessage()]);
    }
    exit(1);
}

writeWorkerLog("===== TRACKING WORKER FINISHED =====");
?>