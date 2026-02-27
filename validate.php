<?php
/**
 * VALIDATE.PHP - TAUFIKMARIE.COM ULTIMATE (DENGAN SOFT DELETE)
 * Version: 2.0.0 - Real-time Validation API dengan Soft Delete
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $conn = getDB();
    $type = $_GET['type'] ?? $_POST['type'] ?? '';
    
    switch ($type) {
        case 'phone':
            validatePhone($conn);
            break;
        case 'email':
            validateEmail($conn);
            break;
        case 'form':
            validateForm($conn);
            break;
        case 'locations':
            getLocations($conn);
            break;
        default:
            sendResponse(false, 'Invalid validation type', null, 400);
    }
    
} catch (Exception $e) {
    logSystem("Validation error", ['error' => $e->getMessage()], 'ERROR', 'api.log');
    sendResponse(false, 'Validation error occurred', null, 500);
}

// ========== GET LOCATIONS ==========
function getLocations($conn) {
    $stmt = $conn->query("SELECT location_key, display_name, icon FROM locations WHERE is_active = 1 ORDER BY sort_order");
    $locations = $stmt->fetchAll();
    
    sendResponse(true, 'Success', [
        'locations' => $locations
    ]);
}

// ========== VALIDATE PHONE ==========
function validatePhone($conn) {
    $phone = $_GET['phone'] ?? $_POST['phone'] ?? '';
    $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
    
    if (empty($phone)) {
        sendResponse(false, 'Phone required', [
            'available' => false,
            'valid' => false
        ], 400);
    }
    
    // Clean and validate phone
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone_clean) < 10 || strlen($phone_clean) > 15) {
        sendResponse(true, 'Phone invalid length', [
            'available' => false,
            'valid' => false,
            'message' => 'Nomor harus 10-15 digit'
        ]);
        return;
    }
    
    // Format to 62
    if (substr($phone_clean, 0, 1) === '0') {
        $phone_formatted = '62' . substr($phone_clean, 1);
    } elseif (substr($phone_clean, 0, 2) !== '62') {
        $phone_formatted = '62' . $phone_clean;
    } else {
        $phone_formatted = $phone_clean;
    }
    
    // Check if exists - hanya cek yang belum dihapus
    $sql = "SELECT id, first_name, last_name FROM leads WHERE phone = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
    $params = [$phone_formatted];
    
    if ($exclude_id > 0) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
    }
    
    $stmt = $conn->prepare($sql . " LIMIT 1");
    $stmt->execute($params);
    $exists = $stmt->fetch();
    
    if ($exists && is_array($exists) && isset($exists['id'])) {
        sendResponse(true, 'Phone already registered', [
            'available' => false,
            'valid' => true,
            'exists' => true,
            'name' => ($exists['first_name'] ?? '') . ' ' . ($exists['last_name'] ?? '')
        ]);
    } else {
        sendResponse(true, 'Phone available', [
            'available' => true,
            'valid' => true,
            'exists' => false
        ]);
    }
}

// ========== VALIDATE EMAIL ==========
function validateEmail($conn) {
    $email = $_GET['email'] ?? $_POST['email'] ?? '';
    $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
    
    if (empty($email)) {
        sendResponse(true, 'No email provided', [
            'available' => true,
            'valid' => false
        ]);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(true, 'Invalid email format', [
            'available' => false,
            'valid' => false,
            'message' => 'Format email tidak valid'
        ]);
        return;
    }
    
    // Check if exists - hanya cek yang belum dihapus
    $sql = "SELECT id, first_name, last_name FROM leads WHERE email = ? AND email != '' AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
    $params = [$email];
    
    if ($exclude_id > 0) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
    }
    
    $stmt = $conn->prepare($sql . " LIMIT 1");
    $stmt->execute($params);
    $exists = $stmt->fetch();
    
    if ($exists && is_array($exists) && isset($exists['id'])) {
        sendResponse(true, 'Email already registered', [
            'available' => false,
            'valid' => true,
            'exists' => true,
            'name' => ($exists['first_name'] ?? '') . ' ' . ($exists['last_name'] ?? '')
        ]);
    } else {
        sendResponse(true, 'Email available', [
            'available' => true,
            'valid' => true,
            'exists' => false
        ]);
    }
}

// ========== VALIDATE FULL FORM ==========
function validateForm($conn) {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $location = $_POST['location'] ?? '';
    
    $errors = [];
    
    // Validate first name
    if (empty($first_name)) {
        $errors[] = ['field' => 'first_name', 'message' => 'Nama depan wajib diisi'];
    } elseif (strlen($first_name) < 2) {
        $errors[] = ['field' => 'first_name', 'message' => 'Nama depan minimal 2 karakter'];
    }
    
    // Validate phone
    if (empty($phone)) {
        $errors[] = ['field' => 'phone', 'message' => 'Nomor WhatsApp wajib diisi'];
    } else {
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone_clean) < 10 || strlen($phone_clean) > 15) {
            $errors[] = ['field' => 'phone', 'message' => 'Nomor WhatsApp harus 10-15 digit'];
        } else {
            // Check duplicate - hanya cek yang belum dihapus
            $phone_formatted = $phone_clean;
            if (substr($phone_formatted, 0, 1) === '0') {
                $phone_formatted = '62' . substr($phone_formatted, 1);
            } elseif (substr($phone_formatted, 0, 2) !== '62') {
                $phone_formatted = '62' . $phone_formatted;
            }
            
            $stmt = $conn->prepare("SELECT id FROM leads WHERE phone = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') LIMIT 1");
            $stmt->execute([$phone_formatted]);
            if ($stmt->fetch()) {
                $errors[] = ['field' => 'phone', 'message' => 'Nomor WhatsApp sudah terdaftar'];
            }
        }
    }
    
    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = ['field' => 'email', 'message' => 'Format email tidak valid'];
    } elseif (!empty($email)) {
        // Check duplicate email - hanya cek yang belum dihapus
        $stmt = $conn->prepare("SELECT id FROM leads WHERE email = ? AND email != '' AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = ['field' => 'email', 'message' => 'Email sudah terdaftar'];
        }
    }
    
    // Validate location
    if (empty($location)) {
        $errors[] = ['field' => 'location', 'message' => 'Pilih lokasi'];
    } else {
        $stmt = $conn->prepare("SELECT id FROM locations WHERE location_key = ? AND is_active = 1");
        $stmt->execute([$location]);
        if (!$stmt->fetch()) {
            $errors[] = ['field' => 'location', 'message' => 'Lokasi tidak valid'];
        }
    }
    
    if (empty($errors)) {
        sendResponse(true, 'Validation passed', [
            'valid' => true,
            'errors' => []
        ]);
    } else {
        sendResponse(false, 'Validation failed', [
            'valid' => false,
            'errors' => $errors
        ], 400);
    }
}

// ========== SEND RESPONSE ==========
function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
?>