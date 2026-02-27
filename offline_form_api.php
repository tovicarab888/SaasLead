<?php
/**
 * OFFLINE_FORM_API.PHP - LEADENGINE
 * Version: 9.0.0 - FINAL: Hapus unit_id, Submit Berhasil
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/offline_form_api.log');

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

$log_dir = dirname(__DIR__, 2) . '/logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$log_file = $log_dir . '/offline_form_api.log';

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

writeLog("========== OFFLINE FORM API DIPANGGIL ==========");
writeLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
writeLog("GET parameters: " . json_encode($_GET));
writeLog("POST parameters: " . json_encode($_POST));

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
$marketing_name = $_SESSION['marketing_name'] ?? 'Marketing';
$developer_id = $_SESSION['marketing_developer_id'] ?? 0;

$action = $_GET['action'] ?? $_POST['action'] ?? '';

writeLog("Action: $action, Marketing ID: $marketing_id, Developer ID: $developer_id");

// ============================================
// FUNGSI VALIDASI NOMOR WHATSAPP
// ============================================
function validatePhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (empty($phone)) return ['valid' => true, 'number' => ''];
    if (strlen($phone) < 10 || strlen($phone) > 13) {
        return ['valid' => false, 'message' => 'Nomor harus 10-13 digit'];
    }
    if (!preg_match('/^(0|62)/', $phone)) {
        return ['valid' => false, 'message' => 'Nomor harus diawali 0 atau 62'];
    }
    if (substr($phone, 0, 1) == '0') {
        $phone = '62' . substr($phone, 1);
    }
    return ['valid' => true, 'number' => $phone];
}

// ============================================
// FUNGSI HITUNG SCORE SEDERHANA
// ============================================
function calculateSimpleScore($data) {
    $source = $data['source'] ?? 'website';
    $first_name = $data['first_name'] ?? '';
    $last_name = $data['last_name'] ?? '';
    $phone = $data['phone'] ?? '';
    $email = $data['email'] ?? '';
    
    $source_scores = [
        'google' => 60, 'facebook' => 55, 'instagram' => 65, 'tiktok' => 50,
        'referensi' => 65, 'referensi_nama' => 70, 'whatsapp' => 70,
        'offline' => 80, 'brosur' => 40, 'event' => 45,
        'iklan_kantor_ig' => 50, 'iklan_kantor_fb' => 52, 'iklan_kantor_tt' => 48,
        'iklan_kantor_google' => 55, 'iklan_pribadi_ig' => 60, 'iklan_pribadi_fb' => 62,
        'iklan_pribadi_tt' => 58, 'marketing_internal' => 75, 'marketing_external' => 70,
        'iklan_pribadi' => 60, 'iklan_kantor' => 50, 'website' => 50
    ];
    
    $source_score = $source_scores[$source] ?? 50;
    
    $bonus = 0;
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) $bonus += 5;
    
    $full_name = trim($first_name . ' ' . $last_name);
    if (strlen($full_name) > 10) $bonus += 5;
    elseif (strlen($full_name) > 5) $bonus += 2;
    
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone_clean) >= 12) $bonus += 5;
    elseif (strlen($phone_clean) >= 11) $bonus += 3;
    elseif (strlen($phone_clean) >= 10) $bonus += 2;
    
    $final_score = min($source_score + $bonus, 100);
    
    $category = 'BARU';
    if ($final_score >= 80) $category = 'HOT';
    elseif ($final_score >= 60) $category = 'WARM';
    
    return [
        'score' => $final_score,
        'source_score' => $source_score,
        'bonus' => $bonus,
        'category' => $category
    ];
}

// ============================================
// ACTION: CALCULATE_SCORE
// ============================================
if ($action === 'calculate_score') {
    writeLog("ACTION: calculate_score");
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
    
    writeLog("Input calculate_score: " . json_encode($input));
    
    $result = calculateSimpleScore($input);
    
    echo json_encode([
        'success' => true,
        'score' => $result['score'],
        'source_score' => $result['source_score'],
        'bonus' => $result['bonus'],
        'category' => $result['category']
    ]);
    exit();
}

// ============================================
// ACTION: SUBMIT - FIXED v2.0
// ============================================
if ($action === 'submit') {
    writeLog("ACTION: submit");
    writeLog("POST data: " . json_encode($_POST));
    
    // ===== DEBUG LOGGING (AKAN DIHAPUS NANTI) =====
    $debug_log = __DIR__ . '/../../logs/offline_form_debug.log';
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " ===== SUBMIT CALLED =====\n", FILE_APPEND);
    file_put_contents($debug_log, "POST: " . json_encode($_POST) . "\n", FILE_APPEND);
    file_put_contents($debug_log, "Session: marketing_id=$marketing_id, developer_id=$developer_id\n", FILE_APPEND);
    
    // Ambil data dari form
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $location_key = trim($_POST['location_key'] ?? '');
    $unit_type = trim($_POST['unit_type'] ?? 'Type 36/60');
    $program = trim($_POST['program'] ?? 'Subsidi');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $source_type = trim($_POST['source_type'] ?? 'offline');
    $source_detail = trim($_POST['source_detail'] ?? '');
    $target_marketing_id = isset($_POST['target_marketing_id']) ? (int)$_POST['target_marketing_id'] : 0;
    
    writeLog("Processed data - first_name: $first_name, phone: $phone, location: $location_key, source: $source_type");
    file_put_contents($debug_log, "Data: first_name=$first_name, phone=$phone, source=$source_type, target=$target_marketing_id\n", FILE_APPEND);
    
    // Validasi wajib
    $errors = [];
    if (empty($first_name)) $errors[] = 'Nama depan wajib diisi';
    if (empty($phone)) $errors[] = 'Nomor WhatsApp wajib diisi';
    if (empty($location_key)) $errors[] = 'Pilih lokasi';
    
    if (!empty($errors)) {
        file_put_contents($debug_log, "ERROR validasi: " . implode(', ', $errors) . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit();
    }
    
    // Validasi nomor WhatsApp
    $phone_valid = validatePhoneNumber($phone);
    if (!$phone_valid['valid']) {
        file_put_contents($debug_log, "ERROR phone: " . $phone_valid['message'] . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => $phone_valid['message']]);
        exit();
    }
    $phone = $phone_valid['number'];
    
    // Tentukan source untuk database
    $source = $source_type;
    if (!empty($source_detail) && $source_detail !== $source_type) {
        $source = $source_detail;
    }
    
    // Hapus source iklan_kantor dari sistem (karena sudah dihandle external)
    if (strpos($source, 'iklan_kantor') !== false) {
        $source = 'external_iklan'; // Ubah jadi external
        file_put_contents($debug_log, "Mengubah iklan_kantor menjadi external\n", FILE_APPEND);
    }
    
    // Hitung lead score
    $score_data = calculateSimpleScore([
        'source' => $source,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone' => $phone,
        'email' => $email
    ]);
    $lead_score = $score_data['score'];
    
    writeLog("Lead score calculated: $lead_score");
    file_put_contents($debug_log, "Lead score: $lead_score, final source: $source\n", FILE_APPEND);
    
    // ===== TENTUKAN ASSIGNMENT MARKETING =====
    $assigned_marketing_team_id = null;
    $assigned_type = 'external';
    $assignment_reason = '';
    
    // SOURCE YANG WAJIB KE INTERNAL (PERSONAL)
    $internal_sources = [
        'iklan_pribadi', 'iklan_pribadi_ig', 'iklan_pribadi_fb', 'iklan_pribadi_tt',
        'brosur', 'event', 'referensi', 'referensi_nama', 'walk_in', 'kantor'
    ];
    
    // CEK APAKAH INI MARKETING INTERNAL (MANUAL)
    if ($source_type === 'marketing_internal' && $target_marketing_id > 0) {
        $assigned_marketing_team_id = $target_marketing_id;
        $assigned_type = 'internal';
        $assignment_reason = 'manual_internal';
        writeLog("Manual internal assignment to marketing ID: $target_marketing_id");
        file_put_contents($debug_log, "ASSIGN: manual internal ke ID $target_marketing_id\n", FILE_APPEND);
        
    } 
    // CEK APAKAH INI MARKETING EXTERNAL
    elseif ($source_type === 'marketing_external') {
        $assigned_type = 'external';
        $assignment_reason = 'manual_external';
        writeLog("External assignment (manual)");
        file_put_contents($debug_log, "ASSIGN: manual external\n", FILE_APPEND);
        
        // Ambil external marketing
        $external = getNextExternalMarketing();
        $assigned_marketing_team_id = $external['user_id'] ?? 1;
        
    } 
    // CEK APAKAH SOURCE INI PERSONAL (HARUS KE INTERNAL)
    else {
        $is_internal_source = false;
        foreach ($internal_sources as $is) {
            if (strpos($source, $is) !== false) {
                $is_internal_source = true;
                break;
            }
        }
        
        if ($is_internal_source) {
            // WAJIB KE INTERNAL
            file_put_contents($debug_log, "DETEKSI: Personal source ($source), wajib ke internal\n", FILE_APPEND);
            
            // Cek apakah ada marketing internal
            $internal = getNextInternalMarketing($conn, $developer_id);
            
            if ($internal) {
                $assigned_type = 'internal';
                $assigned_marketing_team_id = $internal['id'];
                $assignment_reason = 'personal_source_internal';
                file_put_contents($debug_log, "ASSIGN: personal source ke internal ID {$internal['id']}\n", FILE_APPEND);
            } else {
                // Fallback ke external jika tidak ada internal
                file_put_contents($debug_log, "WARNING: Tidak ada internal, fallback ke external\n", FILE_APPEND);
                $external = getNextExternalMarketing();
                $assigned_type = 'external';
                $assigned_marketing_team_id = $external['user_id'] ?? 1;
                $assignment_reason = 'personal_source_no_internal_fallback';
            }
        } else {
            // GUNAKAN FUNGSI assignLeadToMarketing UNTUK SOURCE LAIN
            file_put_contents($debug_log, "Menggunakan assignLeadToMarketing untuk source $source\n", FILE_APPEND);
            
            if (function_exists('assignLeadToMarketing')) {
                $assignment = assignLeadToMarketing($conn, $developer_id, [
                    'location' => $location_key,
                    'source' => $source
                ]);
                
                $assigned_type = $assignment['assigned_type'] ?? 'external';
                $assigned_marketing_team_id = $assignment['assigned_marketing_team_id'] ?? null;
                $assignment_reason = $assignment['assignment_reason'] ?? 'auto_assign';
                
                writeLog("Auto assignment - Type: $assigned_type, Marketing ID: " . ($assigned_marketing_team_id ?? 'null'));
                file_put_contents($debug_log, "ASSIGN: auto - type=$assigned_type, id=$assigned_marketing_team_id, reason=$assignment_reason\n", FILE_APPEND);
            } else {
                writeLog("Warning: assignLeadToMarketing function not found");
                file_put_contents($debug_log, "ERROR: assignLeadToMarketing tidak ditemukan\n", FILE_APPEND);
                
                // Fallback
                $external = getNextExternalMarketing();
                $assigned_type = 'external';
                $assigned_marketing_team_id = $external['user_id'] ?? 1;
                $assignment_reason = 'function_not_found_fallback';
            }
        }
    }
    
    // ===== DEBUG FINAL ASSIGNMENT =====
    file_put_contents($debug_log, "FINAL ASSIGNMENT: type=$assigned_type, id=$assigned_marketing_team_id, reason=$assignment_reason\n", FILE_APPEND);
    
    try {
        $conn->beginTransaction();
        
        // Cek duplikat
        $is_duplicate = false;
        if (function_exists('checkDuplicateLead')) {
            $duplicate_check = checkDuplicateLead($conn, $phone, $email);
            $is_duplicate = $duplicate_check['is_duplicate'] ?? false;
            writeLog("Duplicate check result: " . ($is_duplicate ? 'DUPLICATE' : 'NEW'));
            file_put_contents($debug_log, "Duplicate check: " . ($is_duplicate ? 'DUPLICATE' : 'NEW') . "\n", FILE_APPEND);
        }
        
        if ($is_duplicate) {
            $conn->rollBack();
            file_put_contents($debug_log, "ERROR: Duplicate phone\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Nomor WhatsApp sudah terdaftar']);
            exit();
        }
        
        // Insert ke tabel leads
        $sql = "INSERT INTO leads (
            first_name, last_name, phone, email, location_key,
            address, city, unit_type, program,
            status, lead_score, source, assigned_type,
            assigned_marketing_team_id, ditugaskan_ke, notes, created_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            'Baru', ?, ?, ?,
            ?, ?, ?, NOW()
        )";
        
        $params = [
            $first_name,
            $last_name,
            $phone,
            $email,
            $location_key,
            $address,
            $city,
            $unit_type,
            $program,
            $lead_score,
            $source,
            $assigned_type,
            $assigned_marketing_team_id,
            $developer_id,
            $notes
        ];
        
        writeLog("SQL Params: " . json_encode($params));
        file_put_contents($debug_log, "SQL Params: " . json_encode($params) . "\n", FILE_APPEND);
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            $error = $stmt->errorInfo();
            throw new Exception('Gagal insert: ' . ($error[2] ?? 'Unknown'));
        }
        
        $lead_id = $conn->lastInsertId();
        writeLog("Lead inserted with ID: $lead_id");
        file_put_contents($debug_log, "SUCCESS: Lead ID $lead_id created\n", FILE_APPEND);
        
        // Catat aktivitas marketing
        $activity_sql = "INSERT INTO marketing_activities (
            lead_id, marketing_id, developer_id, action_type, note_text, created_at
        ) VALUES (?, ?, ?, 'add_lead', ?, NOW())";
        
        $activity_note = "Menambahkan lead offline dari sumber: $source (assignment: $assigned_type, reason: $assignment_reason)";
        $activity_stmt = $conn->prepare($activity_sql);
        $activity_stmt->execute([$lead_id, $marketing_id, $developer_id, $activity_note]);
        
        // Kirim notifikasi ke marketing jika ada
        if ($assigned_marketing_team_id) {
            $marketing_info = null;
            
            if ($assigned_type === 'internal') {
                $stmt = $conn->prepare("SELECT nama_lengkap, phone FROM marketing_team WHERE id = ?");
                $stmt->execute([$assigned_marketing_team_id]);
                $marketing_info = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $conn->prepare("SELECT nama_lengkap, phone FROM users WHERE id = ?");
                $stmt->execute([$assigned_marketing_team_id]);
                $marketing_info = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if ($marketing_info) {
                $customer_data = [
                    'full_name' => $first_name . ' ' . $last_name,
                    'phone' => $phone,
                    'first_name' => $first_name
                ];
                
                $location_data = getLocationDetails($location_key);
                
                // Queue notifikasi (async)
                if (function_exists('queueJob')) {
                    queueJob([
                        'type' => 'whatsapp',
                        'payload' => [
                            'action' => 'new_lead_notification',
                            'to_user_id' => $assigned_marketing_team_id,
                            'to_role' => $assigned_type,
                            'lead_id' => $lead_id,
                            'customer' => $customer_data,
                            'marketing' => $marketing_info,
                            'location' => $location_data
                        ]
                    ]);
                }
            }
        }
        
        $conn->commit();
        writeLog("Transaction committed successfully");
        file_put_contents($debug_log, "TRANSACTION COMMITTED\n", FILE_APPEND);
        
        // Ambil data marketing untuk response
        $marketing_name = '';
        if ($assigned_marketing_team_id) {
            if ($assigned_type === 'internal') {
                $stmt = $conn->prepare("SELECT nama_lengkap FROM marketing_team WHERE id = ?");
                $stmt->execute([$assigned_marketing_team_id]);
                $marketing_name = $stmt->fetchColumn();
            } else {
                $stmt = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
                $stmt->execute([$assigned_marketing_team_id]);
                $marketing_name = $stmt->fetchColumn();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Data customer berhasil disimpan',
            'lead_id' => $lead_id,
            'score' => $lead_score,
            'assigned_type' => $assigned_type,
            'assigned_marketing_id' => $assigned_marketing_team_id,
            'assigned_marketing_name' => $marketing_name,
            'assignment_reason' => $assignment_reason,
            'debug' => [
                'source' => $source,
                'target_marketing_id' => $target_marketing_id
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        writeLog("ERROR submit: " . $e->getMessage());
        writeLog("Stack trace: " . $e->getTraceAsString());
        file_put_contents($debug_log, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        file_put_contents($debug_log, "Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit();
}

// ============================================
// ACTION: CHECK_DUPLICATE
// ============================================
if ($action === 'check_duplicate') {
    $phone = $_GET['phone'] ?? $_POST['phone'] ?? '';
    $email = $_GET['email'] ?? $_POST['email'] ?? '';
    
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Nomor telepon diperlukan']);
        exit();
    }
    
    $phone_valid = validatePhoneNumber($phone);
    if ($phone_valid['valid']) {
        $phone = $phone_valid['number'];
    }
    
    if (function_exists('checkDuplicateLead')) {
        $duplicate = checkDuplicateLead($conn, $phone, $email);
        echo json_encode([
            'success' => true,
            'is_duplicate' => $duplicate['is_duplicate'] ?? false,
            'data' => $duplicate
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'is_duplicate' => false
        ]);
    }
    exit();
}

// ============================================
// ACTION: DEFAULT
// ============================================
writeLog("ERROR: Action tidak dikenal: $action");
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);

writeLog("========== OFFLINE FORM API SELESAI ==========\n");
?>