<?php
/**
 * LEADS_MARKETING.PHP - LEADENGINE
 * Version: 17.0.0 - FIXED: Semua action, tambah get_available_for_booking
 * 
 * API KHUSUS UNTUK MARKETING DASHBOARD
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/leads_marketing_error.log');

while (ob_get_level()) ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';

$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$log_file = $log_dir . '/leads_marketing.log';

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
    $log .= str_repeat("-", 80) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeLog("========== LEADS_MARKETING DIPANGGIL ==========");
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
    writeLog("ERROR: Koneksi database gagal");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$marketing_id = $_SESSION['marketing_id'];
$developer_id = $_SESSION['marketing_developer_id'] ?? 0;
$action = $_GET['action'] ?? $_POST['action'] ?? '';

writeLog("Action: $action, Marketing ID: $marketing_id, Developer ID: $developer_id");

// ============================================
// FUNGSI LEAD SCORING INTERNAL
// ============================================
function calculateLeadScoreForMarketing($status, $lead_data = [], $old_status = null) {
    $SCORE_DEAL = 100;
    $SCORE_BOOKING = 85;
    $SCORE_SURVEY = 75;
    $SCORE_FOLLOW_UP = 65;
    $SCORE_BARU = 50;
    $SCORE_NEGATIF_MAX = 30;
    
    $SCORE_SOURCE_GOOGLE = 60;
    $SCORE_SOURCE_FACEBOOK = 55;
    $SCORE_SOURCE_TIKTOK = 50;
    $SCORE_SOURCE_INSTAGRAM = 65;
    $SCORE_SOURCE_REFERENSI = 65;
    $SCORE_SOURCE_WEBSITE = 50;
    $SCORE_SOURCE_WHATSAPP = 70;
    $SCORE_SOURCE_OFFLINE = 80;
    $SCORE_SOURCE_DEFAULT = 50;
    
    $SCORE_BONUS_EMAIL = 5;
    $SCORE_BONUS_NAME_LENGTH = 5;
    $SCORE_BONUS_PHONE_LENGTH = 5;
    
    $deal_statuses = ['Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'];
    if (in_array($status, $deal_statuses)) {
        return $SCORE_DEAL;
    }
    
    $negative_statuses = ['Tolak Slik', 'Tidak Minat', 'Batal'];
    if (in_array($status, $negative_statuses)) {
        $source_score = isset($lead_data['source']) ? calculateSourceScoreSimple($lead_data['source']) : $SCORE_SOURCE_DEFAULT;
        return min($source_score, $SCORE_NEGATIF_MAX);
    }
    
    $source_score = isset($lead_data['source']) ? calculateSourceScoreSimple($lead_data['source']) : $SCORE_SOURCE_DEFAULT;
    
    $status_score_map = [
        'Baru' => $SCORE_BARU,
        'Follow Up' => $SCORE_FOLLOW_UP,
        'Survey' => $SCORE_SURVEY,
        'Booking' => $SCORE_BOOKING,
    ];
    
    $status_score = $status_score_map[$status] ?? $SCORE_BARU;
    $base_score = (int)round(($source_score * 0.5) + ($status_score * 0.5));
    
    $bonus = calculateBonusPointsSimple($lead_data);
    $final_score = $base_score + $bonus;
    
    return min(max($final_score, 0), 100);
}

function calculateSourceScoreSimple($source) {
    $source = strtolower(trim($source));
    $source_scores = [
        'google' => 60, 'google ads' => 60, 'facebook' => 55, 'meta' => 55,
        'instagram' => 65, 'ig' => 65, 'tiktok' => 50, 'tt' => 50,
        'referensi' => 65, 'teman' => 65, 'website' => 50, 'whatsapp' => 70,
        'wa' => 70, 'offline' => 80, 'brosur' => 40, 'event' => 45,
        'iklan_kantor_ig' => 50, 'iklan_kantor_fb' => 52, 'iklan_kantor_tt' => 48,
        'iklan_pribadi_ig' => 60, 'iklan_pribadi_fb' => 62, 'iklan_pribadi_tt' => 58,
        'marketing_internal' => 75, 'marketing_external' => 70
    ];
    if (isset($source_scores[$source])) return $source_scores[$source];
    foreach ($source_scores as $key => $score) {
        if (strpos($source, $key) !== false) return $score;
    }
    return 50;
}

function calculateBonusPointsSimple($lead_data) {
    $bonus = 0;
    if (!empty($lead_data['email']) && filter_var($lead_data['email'], FILTER_VALIDATE_EMAIL)) $bonus += 5;
    
    $full_name = trim(($lead_data['first_name'] ?? '') . ' ' . ($lead_data['last_name'] ?? ''));
    if (strlen($full_name) > 10) $bonus += 5;
    elseif (strlen($full_name) > 5) $bonus += 2;
    
    $phone = $lead_data['phone'] ?? '';
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone_clean) >= 12) $bonus += 5;
    elseif (strlen($phone_clean) >= 11) $bonus += 3;
    elseif (strlen($phone_clean) >= 10) $bonus += 2;
    
    return min($bonus, 20);
}

// ============================================
// ACTION: GET LEAD
// ============================================
if ($action === 'get') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
    
    writeLog("Get lead ID: $id");
    
    if ($id <= 0) {
        writeLog("ERROR: ID tidak valid");
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT l.*, 
                   loc.display_name as location_display, 
                   loc.icon,
                   m.nama_lengkap as marketing_name,
                   m.phone as marketing_phone,
                   m.bank_id, 
                   m.nomor_rekening as marketing_nomor_rekening,
                   m.atas_nama_rekening, 
                   m.nama_bank_rekening,
                   dev.nama_lengkap as developer_name
            FROM leads l 
            LEFT JOIN locations loc ON l.location_key = loc.location_key 
            LEFT JOIN marketing_team m ON l.assigned_marketing_team_id = m.id
            LEFT JOIN users dev ON l.ditugaskan_ke = dev.id
            WHERE l.id = ? AND l.assigned_marketing_team_id = ?
        ");
        $stmt->execute([$id, $marketing_id]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lead) {
            writeLog("Lead tidak ditemukan atau bukan milik marketing ini");
            echo json_encode(['success' => false, 'message' => 'Lead tidak ditemukan']);
            exit();
        }
        
        // Ambil aktivitas marketing
        $actStmt = $conn->prepare("
            SELECT * FROM marketing_activities 
            WHERE lead_id = ? AND marketing_id = ?
            ORDER BY created_at DESC LIMIT 20
        ");
        $actStmt->execute([$id, $marketing_id]);
        $activities = $actStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ambil data komisi jika ada
        $komisiStmt = $conn->prepare("
            SELECT * FROM komisi_logs 
            WHERE lead_id = ?
            ORDER BY created_at DESC
        ");
        $komisiStmt->execute([$id]);
        $komisi = $komisiStmt->fetchAll(PDO::FETCH_ASSOC);
        
        writeLog("Get lead sukses, ditemukan " . count($activities) . " aktivitas");
        
        echo json_encode([
            'success' => true, 
            'data' => $lead,
            'activities' => $activities,
            'komisi' => $komisi
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        writeLog("ERROR get lead: " . $e->getMessage());
        writeLog("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// ============================================
// ACTION: ADD NOTE
// ============================================
if ($action === 'add_note') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
    
    writeLog("Add note input:", $input);
    
    $lead_id = isset($input['lead_id']) ? (int)$input['lead_id'] : 0;
    $action_type = isset($input['action_type']) ? trim($input['action_type']) : 'follow_up';
    $note = isset($input['note']) ? trim($input['note']) : '';
    
    if ($lead_id <= 0) {
        writeLog("ERROR: ID lead tidak valid");
        echo json_encode(['success' => false, 'message' => 'ID lead tidak valid']);
        exit();
    }
    
    if (empty($note)) {
        writeLog("ERROR: Catatan kosong");
        echo json_encode(['success' => false, 'message' => 'Catatan tidak boleh kosong']);
        exit();
    }
    
    $valid_actions = ['follow_up', 'call', 'whatsapp', 'survey', 'booking', 'cek_slik', 'utj', 'pemberkasan', 'proses_bank', 'akad', 'serah_terima', 'add_note'];
    if (!in_array($action_type, $valid_actions)) {
        $action_type = 'follow_up';
    }
    
    try {
        $stmt = $conn->prepare("SELECT id, ditugaskan_ke FROM leads WHERE id = ? AND assigned_marketing_team_id = ?");
        $stmt->execute([$lead_id, $marketing_id]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lead) {
            writeLog("Lead bukan milik marketing ini");
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses ke lead ini']);
            exit();
        }
        
        $dev_id = $lead['ditugaskan_ke'] ?? 0;
        
        $stmt = $conn->prepare("
            INSERT INTO marketing_activities (
                lead_id, marketing_id, developer_id, action_type, note_text, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $result = $stmt->execute([$lead_id, $marketing_id, $dev_id, $action_type, $note]);
        
        if (!$result) {
            throw new Exception("Gagal menyimpan catatan");
        }
        
        $updateStmt = $conn->prepare("UPDATE leads SET last_followup_at = NOW(), total_followups = total_followups + 1 WHERE id = ?");
        $updateStmt->execute([$lead_id]);
        
        writeLog("Note added successfully");
        echo json_encode(['success' => true, 'message' => 'Catatan berhasil ditambahkan']);
        
    } catch (Exception $e) {
        writeLog("ERROR add note: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// ============================================
// ACTION: UPDATE WITH SCORING
// ============================================
if ($action === 'update_with_scoring') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
    
    writeLog("Update with scoring input:", $input);
    
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $status = isset($input['status']) ? trim($input['status']) : '';
    $email = isset($input['email']) ? trim($input['email']) : '';
    $unit_type = isset($input['unit_type']) ? trim($input['unit_type']) : 'Type 36/60';
    $program = isset($input['program']) ? trim($input['program']) : 'Subsidi';
    $address = isset($input['address']) ? trim($input['address']) : '';
    $city = isset($input['city']) ? trim($input['city']) : '';
    $notes = isset($input['notes']) ? trim($input['notes']) : '';
    $note = isset($input['note']) ? trim($input['note']) : 'Update dari dashboard marketing';
    
    writeLog("ID: $id, Status: $status");
    
    if ($id <= 0) {
        writeLog("ERROR: ID tidak valid");
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }
    
    if (empty($status)) {
        writeLog("ERROR: Status kosong");
        echo json_encode(['success' => false, 'message' => 'Status wajib diisi']);
        exit();
    }
    
    $valid_statuses = ['Baru', 'Follow Up', 'Survey', 'Booking', 'Tolak Slik', 'Tidak Minat', 'Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun', 'Batal'];
    if (!in_array($status, $valid_statuses)) {
        writeLog("ERROR: Status tidak valid: $status");
        echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
        exit();
    }
    
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("
            SELECT l.*, u.id as developer_id
            FROM leads l
            LEFT JOIN users u ON l.ditugaskan_ke = u.id
            WHERE l.id = ? AND l.assigned_marketing_team_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$id, $marketing_id]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lead) {
            $conn->rollBack();
            writeLog("Lead tidak ditemukan");
            echo json_encode(['success' => false, 'message' => 'Lead tidak ditemukan']);
            exit();
        }
        
        $old_status = $lead['status'];
        $old_score = (int)$lead['lead_score'];
        $dev_id = $lead['developer_id'] ?? 0;
        
        writeLog("Old status: $old_status, Old score: $old_score");
        
        $lead_data = [
            'first_name' => $lead['first_name'],
            'last_name' => $lead['last_name'],
            'phone' => $lead['phone'],
            'email' => $email ?: $lead['email'],
            'location_key' => $lead['location_key'],
            'source' => $lead['source']
        ];
        
        $new_score = calculateLeadScoreForMarketing($status, $lead_data, $old_status);
        writeLog("New score: $new_score");
        
        $update = $conn->prepare("
            UPDATE leads SET 
                status = ?, lead_score = ?, email = ?, unit_type = ?, 
                program = ?, address = ?, city = ?, 
                notes = CONCAT(IFNULL(notes, ''), ?),
                last_followup_at = NOW(), total_followups = total_followups + 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateResult = $update->execute([
            $status, $new_score, $email, $unit_type, $program, 
            $address, $city, "\n[" . date('d/m/Y H:i') . "] " . $note,
            $id
        ]);
        
        if (!$updateResult) {
            throw new Exception("Gagal mengupdate lead");
        }
        
        $activity = $conn->prepare("
            INSERT INTO marketing_activities (
                lead_id, marketing_id, developer_id, action_type,
                status_before, status_after, score_before, score_after,
                note_text, created_at
            ) VALUES (?, ?, ?, 'update_status', ?, ?, ?, ?, ?, NOW())
        ");
        
        $activity->execute([
            $id, $marketing_id, $dev_id,
            $old_status, $status, $old_score, $new_score,
            $note ?: "Update status dari $old_status ke $status"
        ]);
        
        $conn->commit();
        
        writeLog("Update sukses untuk lead ID: $id");
        
        echo json_encode([
            'success' => true,
            'message' => 'Lead berhasil diupdate',
            'data' => [
                'old_score' => $old_score,
                'new_score' => $new_score,
                'old_status' => $old_status,
                'new_status' => $status
            ]
        ]);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        writeLog("ERROR update: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// ============================================
// ACTION: GET ACTIVITIES
// ============================================
if ($action === 'get_activities') {
    $lead_id = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    
    writeLog("Get activities untuk lead ID: $lead_id");
    
    if ($lead_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID lead tidak valid']);
        exit();
    }
    
    try {
        $checkStmt = $conn->prepare("SELECT id FROM leads WHERE id = ? AND assigned_marketing_team_id = ?");
        $checkStmt->execute([$lead_id, $marketing_id]);
        
        if (!$checkStmt->fetch()) {
            writeLog("Lead bukan milik marketing ini");
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses ke lead ini']);
            exit();
        }
        
        $stmt = $conn->prepare("
            SELECT * FROM marketing_activities 
            WHERE lead_id = ? AND marketing_id = ?
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$lead_id, $marketing_id, $limit]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'activities' => $activities]);
        
    } catch (Exception $e) {
        writeLog("ERROR get activities: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================
// ACTION: GET AVAILABLE FOR BOOKING
// ============================================
if ($action === 'get_available_for_booking') {
    writeLog("ACTION: get_available_for_booking untuk marketing ID: $marketing_id");
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                l.id,
                l.first_name,
                l.last_name,
                l.phone,
                l.email,
                l.location_key,
                loc.display_name as location_display,
                loc.icon,
                l.status,
                l.lead_score,
                l.created_at
            FROM leads l
            LEFT JOIN locations loc ON l.location_key = loc.location_key
            WHERE l.assigned_marketing_team_id = ? 
                AND l.status IN ('Baru', 'Follow Up', 'Survey')
                AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
                AND NOT EXISTS (
                    SELECT 1 FROM units u 
                    WHERE u.lead_id = l.id AND u.status IN ('BOOKED', 'SOLD')
                )
            ORDER BY l.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$marketing_id]);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($leads as &$lead) {
            $lead['full_name'] = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
            $lead['display_name'] = $lead['full_name'] . ' - ' . $lead['phone'] . ' (' . $lead['status'] . ')';
        }
        
        writeLog("Ditemukan " . count($leads) . " leads untuk booking");
        
        echo json_encode([
            'success' => true,
            'data' => $leads,
            'total' => count($leads)
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR get_available_for_booking: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// ============================================
// ACTION: GET STATS (UNTUK DASHBOARD MARKETING)
// ============================================
if ($action === 'get_stats') {
    writeLog("ACTION: get_stats untuk marketing ID: $marketing_id");
    
    try {
        // Total leads assigned
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM leads 
            WHERE assigned_marketing_team_id = ? 
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute([$marketing_id]);
        $total_leads = $stmt->fetchColumn();
        
        // Total leads hari ini
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM leads 
            WHERE assigned_marketing_team_id = ? 
            AND DATE(created_at) = CURDATE()
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute([$marketing_id]);
        $today_leads = $stmt->fetchColumn();
        
        // Total booking (lead dengan status Booking)
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM leads 
            WHERE assigned_marketing_team_id = ? 
            AND status = 'Booking'
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute([$marketing_id]);
        $total_booking = $stmt->fetchColumn();
        
        // Total deal
        $deal_statuses = ['Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'];
        $placeholders = implode(',', array_fill(0, count($deal_statuses), '?'));
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM leads 
            WHERE assigned_marketing_team_id = ? 
            AND status IN ($placeholders)
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $params = array_merge([$marketing_id], $deal_statuses);
        $stmt->execute($params);
        $total_deal = $stmt->fetchColumn();
        
        // Rata-rata lead score
        $stmt = $conn->prepare("
            SELECT AVG(lead_score) FROM leads 
            WHERE assigned_marketing_team_id = ? 
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute([$marketing_id]);
        $avg_score = $stmt->fetchColumn();
        
        // Status distribution
        $stmt = $conn->prepare("
            SELECT status, COUNT(*) as count
            FROM leads 
            WHERE assigned_marketing_team_id = ? 
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            GROUP BY status
            ORDER BY count DESC
        ");
        $stmt->execute([$marketing_id]);
        $status_dist = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_leads' => (int)$total_leads,
                'today_leads' => (int)$today_leads,
                'total_booking' => (int)$total_booking,
                'total_deal' => (int)$total_deal,
                'avg_score' => $avg_score ? round($avg_score) : 0,
                'status_distribution' => $status_dist
            ]
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR get_stats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================
// ACTION: GET LEADS (UNTUK TABEL)
// ============================================
if ($action === 'get_leads') {
    writeLog("ACTION: get_leads untuk marketing ID: $marketing_id");
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    
    try {
        $sql = "
            SELECT l.*, loc.display_name as location_display, loc.icon
            FROM leads l
            LEFT JOIN locations loc ON l.location_key = loc.location_key
            WHERE l.assigned_marketing_team_id = ?
            AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
        ";
        $params = [$marketing_id];
        
        if (!empty($search)) {
            $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)";
            $s = "%$search%";
            $params = array_merge($params, [$s, $s, $s, $s]);
        }
        
        if (!empty($status_filter)) {
            $sql .= " AND l.status = ?";
            $params[] = $status_filter;
        }
        
        // Count total
        $count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as tmp";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $limit);
        
        // Get data with pagination
        $sql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($leads as &$lead) {
            $lead['full_name'] = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
        }
        
        echo json_encode([
            'success' => true,
            'data' => $leads,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_records' => $total_records,
                'limit' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR get_leads: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================
// ACTION: CHECK DUPLICATE
// ============================================
if ($action === 'check_duplicate') {
    $phone = isset($_GET['phone']) ? trim($_GET['phone']) : (isset($_POST['phone']) ? trim($_POST['phone']) : '');
    $email = isset($_GET['email']) ? trim($_GET['email']) : (isset($_POST['email']) ? trim($_POST['email']) : '');
    $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : (isset($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : 0);
    
    writeLog("Check duplicate - phone: $phone, email: $email, exclude_id: $exclude_id");
    
    if (empty($phone) && empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Phone atau email diperlukan']);
        exit();
    }
    
    try {
        $result = [
            'is_duplicate' => false,
            'phone_duplicate' => false,
            'email_duplicate' => false,
            'existing_data' => null
        ];
        
        if (!empty($phone)) {
            // Format phone
            $phone_clean = preg_replace('/[^0-9]/', '', $phone);
            if (substr($phone_clean, 0, 1) === '0') {
                $phone_formatted = '62' . substr($phone_clean, 1);
            } elseif (substr($phone_clean, 0, 2) !== '62') {
                $phone_formatted = '62' . $phone_clean;
            } else {
                $phone_formatted = $phone_clean;
            }
            
            $sql = "SELECT id, first_name, last_name, phone FROM leads WHERE phone = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
            $params = [$phone_formatted];
            
            if ($exclude_id > 0) {
                $sql .= " AND id != ?";
                $params[] = $exclude_id;
            }
            
            $sql .= " LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $dup = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dup) {
                $result['is_duplicate'] = true;
                $result['phone_duplicate'] = true;
                $result['existing_data'] = $dup;
            }
        }
        
        if (!$result['is_duplicate'] && !empty($email)) {
            $sql = "SELECT id, first_name, last_name, email FROM leads WHERE email = ? AND email != '' AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
            $params = [$email];
            
            if ($exclude_id > 0) {
                $sql .= " AND id != ?";
                $params[] = $exclude_id;
            }
            
            $sql .= " LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $dup = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dup) {
                $result['is_duplicate'] = true;
                $result['email_duplicate'] = true;
                $result['existing_data'] = $dup;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR check_duplicate: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================
// DEFAULT - ACTION TIDAK DIKENAL
// ============================================
writeLog("ERROR: Action tidak dikenal: $action");
http_response_code(400);
echo json_encode([
    'success' => false, 
    'message' => 'Action tidak dikenal',
    'available_actions' => [
        'get', 
        'add_note', 
        'update_with_scoring', 
        'get_activities', 
        'get_available_for_booking',
        'get_stats',
        'get_leads',
        'check_duplicate'
    ]
]);
exit();
?>