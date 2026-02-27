<?php
/**
 * EXPORT_WORKER.PHP - Proses export besar di background
 */
require_once __DIR__ . '/config.php';

$job_id = $argv[1] ?? null;
if (!$job_id) {
    // Ambil job pending dari queue
    $conn = getDB();
    $stmt = $conn->query("SELECT * FROM job_queue WHERE type = 'export' AND status = 'pending' ORDER BY id ASC LIMIT 1");
    $job = $stmt->fetch();
    if ($job) {
        processExport($job['id'], json_decode($job['payload'], true));
    }
}

function processExport($job_id, $payload) {
    // Proses export besar...
}
?>