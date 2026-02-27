<?php
/**
 * CONVERT_CANVASING_TO_LEAD.PHP - LEADENGINE
 * Version: 2.1.0 - FIXED: Menerima canvasing_id dari request
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/taufikma/public_html/logs/convert_canvasing.log');

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

// Log request
$log_dir = '/home/taufikma/public_html/logs/';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$debug_log = $log_dir . 'convert_canvasing.log';
file_put_contents($debug_log, date('Y-m-d H:i:s') . " ===== CONVERT REQUEST =====\n", FILE_APPEND);
file_put_contents($debug_log, date('Y-m-d H:i:s') . " Session: " . json_encode($_SESSION) . "\n", FILE_APPEND);

// Cek autentikasi (bisa admin, manager, manager_developer, developer)
if (!isAdmin() && !isManager() && !isManagerDeveloper() && !isDeveloper()) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Unauthorized - Role: " . (getCurrentRole() ?? 'unknown') . "\n", FILE_APPEND);
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized - Anda tidak memiliki akses']));
}

// Ambil user ID yang melakukan konversi
$converted_by = 0;
if (isMarketing()) {
    $converted_by = $_SESSION['marketing_id'] ?? 0;
} else {
    $converted_by = $_SESSION['user_id'] ?? 0;
}

file_put_contents($debug_log, date('Y-m-d H:i:s') . " Converted by: $converted_by\n", FILE_APPEND);

$conn = getDB();
if (!$conn) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Koneksi database gagal\n", FILE_APPEND);
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Koneksi database gagal']));
}

// Ambil data dari request
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

file_put_contents($debug_log, date('Y-m-d H:i:s') . " Input: " . json_encode($input) . "\n", FILE_APPEND);

// Validasi data wajib
$first_name = trim($input['first_name'] ?? '');
$last_name = trim($input['last_name'] ?? '');
$phone = trim($input['phone'] ?? '');
$location_key = trim($input['location_key'] ?? '');
$source = trim($input['source'] ?? 'canvasing');
$assigned_marketing_team_id = (int)($input['assigned_marketing_team_id'] ?? 0);
$developer_id = (int)($input['developer_id'] ?? 0);
$address = trim($input['address'] ?? '');
$notes = trim($input['notes'] ?? '');
$canvasing_id = (int)($input['canvasing_id'] ?? 0); // ⚠️ INI YANG DIPAKAI

file_put_contents($debug_log, date('Y-m-d H:i:s') . " Canvasing ID dari request: $canvasing_id\n", FILE_APPEND);

if (empty($first_name) && empty($last_name)) {
    $first_name = 'Customer';
}

if (empty($phone)) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: No telepon kosong\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Nomor telepon wajib diisi']));
}

if (empty($location_key)) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Lokasi kosong\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Lokasi wajib dipilih']));
}

if ($assigned_marketing_team_id <= 0) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Marketing ID tidak valid: $assigned_marketing_team_id\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Marketing tidak valid']));
}

if ($developer_id <= 0) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: Developer ID tidak valid: $developer_id\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Developer tidak valid']));
}

// Validasi nomor telepon menggunakan fungsi dari config.php
$phone_validation = validatePhone($phone);
if (!$phone_validation['valid']) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: No telepon tidak valid: " . $phone_validation['message'] . "\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Nomor telepon tidak valid: ' . $phone_validation['message']]));
}
$phone = $phone_validation['number'];

// Cek apakah canvasing ini sudah pernah dikonversi
if ($canvasing_id > 0) {
    $check_stmt = $conn->prepare("SELECT converted_to_lead, converted_lead_id FROM canvasing_logs WHERE id = ?");
    $check_stmt->execute([$canvasing_id]);
    $canvasing_check = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($canvasing_check) {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " Canvasing check: " . json_encode($canvasing_check) . "\n", FILE_APPEND);
        
        if ($canvasing_check['converted_to_lead'] == 1) {
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " WARNING: Canvasing sudah pernah dikonversi ke lead ID: " . $canvasing_check['converted_lead_id'] . "\n", FILE_APPEND);
            die(json_encode([
                'success' => false, 
                'message' => 'Data canvasing ini sudah pernah dikonversi ke lead',
                'existing_lead_id' => $canvasing_check['converted_lead_id']
            ]));
        }
    } else {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " WARNING: Canvasing ID $canvasing_id tidak ditemukan di database\n", FILE_APPEND);
        // Tetap lanjutkan, mungkin canvasing_id tidak dikirim
    }
}

// Cek duplikat berdasarkan nomor telepon
$check_dup = checkDuplicateLead($conn, $phone);
$is_duplicate = $check_dup['is_duplicate'];

if ($is_duplicate) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " WARNING: Duplikat lead ditemukan\n", FILE_APPEND);
    // Tetap lanjutkan tapi beri warning
}

try {
    $conn->beginTransaction();
    
    // Hitung lead score untuk lead baru
    $lead_data = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone' => $phone,
        'location_key' => $location_key,
        'source' => $source
    ];
    
    $lead_score = calculateLeadScorePremium('Baru', $lead_data);
    
    // Ambil informasi marketing untuk notifikasi nanti
    $marketing_stmt = $conn->prepare("SELECT nama_lengkap, phone FROM marketing_team WHERE id = ?");
    $marketing_stmt->execute([$assigned_marketing_team_id]);
    $marketing = $marketing_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Insert ke tabel leads
    $sql = "INSERT INTO leads (
        first_name, last_name, phone, location_key, address, notes,
        source, assigned_marketing_team_id, ditugaskan_ke, lead_score,
        status, created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        'Baru', NOW(), NOW()
    )";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $first_name,
        $last_name,
        $phone,
        $location_key,
        $address,
        $notes,
        $source,
        $assigned_marketing_team_id,
        $developer_id,
        $lead_score
    ]);
    
    $lead_id = $conn->lastInsertId();
    
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " Lead created: ID $lead_id\n", FILE_APPEND);
    
    // Catat aktivitas marketing
    $activity_sql = "INSERT INTO marketing_activities (
        lead_id, marketing_id, developer_id, action_type, note_text, created_at
    ) VALUES (?, ?, ?, 'add_note', ?, NOW())";
    
    $activity_stmt = $conn->prepare($activity_sql);
    $activity_stmt->execute([
        $lead_id,
        $assigned_marketing_team_id,
        $developer_id,
        "Lead dibuat dari hasil canvasing. Catatan awal: " . ($notes ?: 'Tidak ada catatan')
    ]);
    
    // Update tabel canvasing_logs jika ada canvasing_id
    if ($canvasing_id > 0) {
        $update_canvasing = $conn->prepare("
            UPDATE canvasing_logs 
            SET converted_to_lead = 1, 
                converted_lead_id = ?,
                converted_at = NOW(),
                converted_by = ?
            WHERE id = ?
        ");
        $update_canvasing->execute([$lead_id, $converted_by, $canvasing_id]);
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " Canvasing $canvasing_id updated as converted\n", FILE_APPEND);
    } else {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " WARNING: No canvasing_id provided, skipping update\n", FILE_APPEND);
    }
    
    $conn->commit();
    
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " SUKSES! Lead ID: $lead_id\n", FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'message' => 'Berhasil dikonversi ke lead',
        'lead_id' => $lead_id,
        'is_duplicate' => $is_duplicate,
        'marketing_id' => $assigned_marketing_team_id,
        'marketing_name' => $marketing['nama_lengkap'] ?? 'Marketing'
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>