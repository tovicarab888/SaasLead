<?php
/**
 * LEADS.PHP - VERSION 26.0.0 - OPTIMIZED
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/leads_error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}

$log_file = $log_dir . '/leads_debug.log';

file_put_contents($log_file, "\n" . str_repeat("=", 50) . "\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - NEW REQUEST\n", FILE_APPEND);
file_put_contents($log_file, "METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
file_put_contents($log_file, "GET: " . json_encode($_GET) . "\n", FILE_APPEND);
file_put_contents($log_file, "POST: " . json_encode($_POST) . "\n", FILE_APPEND);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

if (!checkAuth() && !isMarketing()) {
    file_put_contents($log_file, "Unauthorized access\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$conn = getDB();
if (!$conn) {
    file_put_contents($log_file, "Database connection failed\n", FILE_APPEND);
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database error']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    file_put_contents($log_file, "Invalid CSRF token\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

file_put_contents($log_file, "ACTION: $action\n", FILE_APPEND);

try {
    switch ($action) {
        case 'get':
            getLead($conn, $log_file);
            break;
        case 'update':
            updateLead($conn, $log_file);
            break;
        case 'update_with_scoring':
            updateLeadWithScoring($conn, $log_file);
            break;
        case 'delete':
            deleteLead($conn, $log_file);
            break;
        default:
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($action)) {
                getAllLeads($conn, $log_file);
            } else {
                file_put_contents($log_file, "Invalid action: $action\n", FILE_APPEND);
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
    }
} catch (Exception $e) {
    file_put_contents($log_file, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// ========== GET ALL LEADS ==========
function getAllLeads($conn, $log_file) {
    try {
        $sql = "
            SELECT 
                l.*, 
                loc.display_name as location_display, 
                loc.icon,
                -- Marketing Internal
                m.nama_lengkap as marketing_name,
                m.phone as marketing_phone,
                -- Marketing External
                u_external.nama_lengkap as external_marketing_name,
                u_external.phone as external_marketing_phone,
                -- Tampilkan nama marketing berdasarkan tipe
                CASE 
                    WHEN l.assigned_type = 'internal' THEN m.nama_lengkap
                    WHEN l.assigned_type = 'external' THEN u_external.nama_lengkap
                    ELSE '-'
                END as marketing_display,
                l.assigned_type,
                u.nama_lengkap as developer_name
            FROM leads l
            LEFT JOIN locations loc ON l.location_key = loc.location_key
            LEFT JOIN marketing_team m ON l.assigned_marketing_team_id = m.id
            LEFT JOIN users u_external ON l.assigned_marketing_team_id = u_external.id AND u_external.role = 'marketing_external'
            LEFT JOIN users u ON l.ditugaskan_ke = u.id
            WHERE (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
        ";
        
        $params = [];
        
        if (isMarketing()) {
            $sql .= " AND l.assigned_marketing_team_id = ?";
            $params[] = $_SESSION['marketing_id'];
        } elseif (isDeveloper()) {
            $sql .= " AND l.ditugaskan_ke = ?";
            $params[] = $_SESSION['user_id'];
        } elseif (isManagerDeveloper()) {
            $sql .= " AND l.ditugaskan_ke = ?";
            $params[] = $_SESSION['developer_id'];
        } elseif (isFinance()) {
            $sql .= " AND l.ditugaskan_ke = ?";
            $params[] = $_SESSION['developer_id'];
        }
        
        $sql .= " ORDER BY l.created_at DESC LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        file_put_contents($log_file, "getAllLeads SUCCESS: " . count($leads) . " records\n", FILE_APPEND);
        
        echo json_encode(['success' => true, 'data' => $leads], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        file_put_contents($log_file, "getAllLeads ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// ========== GET SINGLE LEAD ==========
function getLead($conn, $log_file) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
    
    file_put_contents($log_file, "getLead ID: $id\n", FILE_APPEND);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        return;
    }
    
    try {
        $sql = "
            SELECT 
                l.*, 
                loc.display_name as location_display, 
                loc.icon,
                loc.subsidi_units,
                loc.komersil_units,
                -- Marketing Internal
                m.nama_lengkap as marketing_name,
                m.phone as marketing_phone,
                -- Marketing External
                u_external.nama_lengkap as external_marketing_name,
                u_external.phone as external_marketing_phone,
                u_external.email as external_marketing_email,
                -- Developer
                u.nama_lengkap as developer_name,
                -- Tampilkan marketing berdasarkan tipe
                CASE 
                    WHEN l.assigned_type = 'internal' THEN m.nama_lengkap
                    WHEN l.assigned_type = 'external' THEN u_external.nama_lengkap
                    ELSE '-'
                END as marketing_display,
                CASE 
                    WHEN l.assigned_type = 'internal' THEN m.phone
                    WHEN l.assigned_type = 'external' THEN u_external.phone
                    ELSE '-'
                END as marketing_phone_display,
                l.assigned_type
            FROM leads l 
            LEFT JOIN locations loc ON l.location_key = loc.location_key 
            LEFT JOIN marketing_team m ON l.assigned_marketing_team_id = m.id
            LEFT JOIN users u_external ON l.assigned_marketing_team_id = u_external.id AND u_external.role = 'marketing_external'
            LEFT JOIN users u ON l.ditugaskan_ke = u.id
            WHERE l.id = ?
        ";
        
        $params = [$id];
        
        if (isMarketing()) {
            $sql .= " AND l.assigned_marketing_team_id = ?";
            $params[] = $_SESSION['marketing_id'];
        } elseif (isDeveloper()) {
            $sql .= " AND l.ditugaskan_ke = ?";
            $params[] = $_SESSION['user_id'];
        } elseif (isManagerDeveloper()) {
            $sql .= " AND l.ditugaskan_ke = ?";
            $params[] = $_SESSION['developer_id'];
        } elseif (isFinance()) {
            $sql .= " AND l.ditugaskan_ke = ?";
            $params[] = $_SESSION['developer_id'];
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lead) {
            echo json_encode(['success' => false, 'message' => 'Lead tidak ditemukan']);
            return;
        }
        
        $lead['subsidi_units_array'] = !empty($lead['subsidi_units']) ? explode(',', $lead['subsidi_units']) : [];
        $lead['komersil_units_array'] = !empty($lead['komersil_units']) ? explode(',', $lead['komersil_units']) : [];
        
        // Format data marketing untuk response
        if ($lead['assigned_type'] === 'internal') {
            $lead['marketing_final_name'] = $lead['marketing_name'] ?? '-';
            $lead['marketing_final_phone'] = $lead['marketing_phone'] ?? '-';
            $lead['marketing_final_email'] = $lead['email'] ?? '-';
            $lead['marketing_type_label'] = 'Internal';
        } elseif ($lead['assigned_type'] === 'external') {
            $lead['marketing_final_name'] = $lead['external_marketing_name'] ?? 'Taufik Marie';
            $lead['marketing_final_phone'] = $lead['external_marketing_phone'] ?? '628133150078';
            $lead['marketing_final_email'] = $lead['external_marketing_email'] ?? 'lapakmarie@gmail.com';
            $lead['marketing_type_label'] = 'External';
        } else {
            $lead['marketing_final_name'] = '-';
            $lead['marketing_final_phone'] = '-';
            $lead['marketing_final_email'] = '-';
            $lead['marketing_type_label'] = '-';
        }
        
        echo json_encode(['success' => true, 'data' => $lead], JSON_UNESCAPED_UNICODE);
        file_put_contents($log_file, "getLead SUCCESS - Marketing: {$lead['marketing_final_name']}\n", FILE_APPEND);
        
    } catch (Exception $e) {
        file_put_contents($log_file, "getLead ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// ========== UPDATE LEAD ==========
function updateLead($conn, $log_file) {
    file_put_contents($log_file, "updateLead called\n", FILE_APPEND);
    file_put_contents($log_file, "POST data: " . json_encode($_POST) . "\n", FILE_APPEND);
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    file_put_contents($log_file, "updateLead ID: $id\n", FILE_APPEND);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        return;
    }
    
    if (isMarketing()) {
        $check = $conn->prepare("SELECT id FROM leads WHERE id = ? AND assigned_marketing_team_id = ?");
        $check->execute([$id, $_SESSION['marketing_id']]);
        if (!$check->fetch()) {
            file_put_contents($log_file, "Access denied for marketing\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
    } elseif (!isAdmin() && !isManager()) {
        file_put_contents($log_file, "Access denied\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $status = $_POST['status'] ?? '';
    $unit_type = $_POST['unit_type'] ?? '';
    $program = $_POST['program'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    file_put_contents($log_file, "Data: status=$status, unit=$unit_type, program=$program\n", FILE_APPEND);
    
    if (empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Status wajib diisi']);
        return;
    }
    
    if (empty($unit_type)) {
        echo json_encode(['success' => false, 'message' => 'Tipe Unit wajib diisi']);
        return;
    }
    
    try {
        $sql = "UPDATE leads SET 
                status = ?, 
                unit_type = ?, 
                program = ?, 
                address = ?, 
                city = ?, 
                notes = ?, 
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([$status, $unit_type, $program, $address, $city, $notes, $id]);
        
        file_put_contents($log_file, "Update result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND);
        
        if ($result) {
            logSystemAction('lead_updated', ['id' => $id], $id, 'leads');
            echo json_encode(['success' => true, 'message' => 'Data lead berhasil diupdate']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate data']);
        }
        
    } catch (Exception $e) {
        file_put_contents($log_file, "updateLead ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// ========== UPDATE LEAD DENGAN SCORING ==========
function updateLeadWithScoring($conn, $log_file) {
    file_put_contents($log_file, "updateLeadWithScoring called\n", FILE_APPEND);
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    file_put_contents($log_file, "Input data: " . json_encode($input) . "\n", FILE_APPEND);
    
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $marketing_id = isset($input['marketing_id']) ? (int)$input['marketing_id'] : 0;
    $status = $input['status'] ?? '';
    $note = $input['note'] ?? '';
    
    file_put_contents($log_file, "ID: $id, Marketing ID: $marketing_id, Status: $status\n", FILE_APPEND);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        return;
    }
    
    if ($marketing_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Marketing ID tidak valid']);
        return;
    }
    
    if (empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Status wajib diisi']);
        return;
    }
    
    if ($marketing_id != $_SESSION['marketing_id']) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $result = updateLeadScoreWithActivity($conn, $id, $marketing_id, $status, null, $note);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Lead berhasil diupdate dengan scoring',
            'data' => [
                'old_score' => $result['old_score'],
                'new_score' => $result['new_score'],
                'old_status' => $result['old_status'],
                'new_status' => $result['new_status']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
}

// ========== DELETE LEAD ==========
function deleteLead($conn, $log_file) {
    file_put_contents($log_file, "deleteLead called\n", FILE_APPEND);
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
    file_put_contents($log_file, "deleteLead ID: $id\n", FILE_APPEND);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        return;
    }
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    try {
        $conn->prepare("UPDATE leads SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
        logSystemAction('lead_deleted', ['id' => $id], $id, 'leads');
        file_put_contents($log_file, "deleteLead SUCCESS\n", FILE_APPEND);
        echo json_encode(['success' => true, 'message' => 'Data lead berhasil dihapus']);
        
    } catch (Exception $e) {
        file_put_contents($log_file, "deleteLead ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

?>