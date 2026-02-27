<?php
/**
 * GET_BANKS.PHP - LEADENGINE API
 * Version: 2.0.0 - FIXED: CSRF protection, encrypted account numbers, audit trail
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_banks.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Cek autentikasi
if (!checkAuth() && !isMarketing() && !isFinance() && !isManagerDeveloper()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Rate limiting
$client_ip = getClientIP();
if (!checkRateLimit('banks_' . $client_ip, 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']);
    exit();
}

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Tentukan developer_id berdasarkan role
$developer_id = 0;
$current_role = getCurrentRole();
$current_user_id = $_SESSION['user_id'] ?? 0;

if (isDeveloper()) {
    $developer_id = $_SESSION['user_id'];
} elseif (isFinance() || isManagerDeveloper()) {
    $developer_id = $_SESSION['developer_id'] ?? 0;
} elseif (isMarketing()) {
    $developer_id = $_SESSION['marketing_developer_id'] ?? 0;
} elseif (in_array($current_role, ['admin', 'manager'])) {
    $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
}

if ($developer_id <= 0 && !in_array($current_role, ['admin', 'manager'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Developer ID tidak valid']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ========== CSRF VALIDATION FOR POST REQUESTS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token tidak valid']);
        exit();
    }
}

// ========== ENCRYPTION FUNCTION ==========
function encryptAccountNumber($number, $key = 'bank_encryption_key_2026') {
    if (empty($number)) return null;
    $method = 'AES-256-CBC';
    $iv = substr(hash('sha256', $key), 0, 16);
    return openssl_encrypt($number, $method, $key, 0, $iv);
}

function decryptAccountNumber($encrypted, $key = 'bank_encryption_key_2026') {
    if (empty($encrypted)) return null;
    $method = 'AES-256-CBC';
    $iv = substr(hash('sha256', $key), 0, 16);
    return openssl_decrypt($encrypted, $method, $key, 0, $iv);
}

// ========== CREATE TABLE IF NOT EXISTS FOR AUDIT ==========
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS bank_audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bank_id INT,
            action VARCHAR(50) NOT NULL,
            user_id INT NOT NULL,
            user_role VARCHAR(50) NOT NULL,
            old_data JSON,
            new_data JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bank (bank_id),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    // Ignore if table already exists
}

// ========== LOG AUDIT FUNCTION ==========
function logBankAudit($conn, $bank_id, $action, $old_data = null, $new_data = null) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO bank_audit_logs (bank_id, action, user_id, user_role, old_data, new_data, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $bank_id,
            $action,
            $_SESSION['user_id'] ?? $_SESSION['marketing_id'] ?? 0,
            getCurrentRole(),
            $old_data ? json_encode($old_data) : null,
            $new_data ? json_encode($new_data) : null,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Bank audit log error: " . $e->getMessage());
    }
}

try {
    switch ($action) {
        
        // ========== GET ALL BANKS ==========
        case 'get_all':
            $stmt = $conn->prepare("
                SELECT 
                    id,
                    nama_bank,
                    nomor_rekening,
                    atas_nama,
                    cabang,
                    kode_swift,
                    keterangan,
                    is_active,
                    created_at,
                    updated_at
                FROM banks 
                WHERE developer_id = ?
                ORDER BY 
                    is_active DESC,
                    nama_bank ASC
            ");
            $stmt->execute([$developer_id]);
            $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decrypt nomor rekening untuk role tertentu
            $can_see_full = in_array($current_role, ['admin', 'manager', 'finance']);
            
            foreach ($banks as &$bank) {
                if (!empty($bank['nomor_rekening'])) {
                    $decrypted = decryptAccountNumber($bank['nomor_rekening']);
                    $bank['nomor_rekening_decrypted'] = $can_see_full ? $decrypted : null;
                    
                    // Mask untuk keamanan
                    if ($decrypted) {
                        $len = strlen($decrypted);
                        $bank['nomor_rekening_masked'] = substr($decrypted, 0, 4) . str_repeat('*', $len - 8) . substr($decrypted, -4);
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $banks,
                'total' => count($banks),
                'developer_id' => $developer_id
            ]);
            break;
        
        // ========== GET BY ID ==========
        case 'get_by_id':
            $bank_id = isset($_GET['bank_id']) ? (int)$_GET['bank_id'] : (isset($_POST['bank_id']) ? (int)$_POST['bank_id'] : 0);
            
            if ($bank_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Bank ID tidak valid']);
                exit();
            }
            
            $stmt = $conn->prepare("
                SELECT * FROM banks 
                WHERE id = ? AND developer_id = ?
            ");
            $stmt->execute([$bank_id, $developer_id]);
            $bank = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bank) {
                if (!empty($bank['nomor_rekening'])) {
                    $decrypted = decryptAccountNumber($bank['nomor_rekening']);
                    $bank['nomor_rekening_decrypted'] = $decrypted;
                    
                    // Mask
                    if ($decrypted) {
                        $len = strlen($decrypted);
                        $bank['nomor_rekening_masked'] = substr($decrypted, 0, 4) . str_repeat('*', $len - 8) . substr($decrypted, -4);
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $bank
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Rekening tidak ditemukan'
                ]);
            }
            break;
        
        // ========== CREATE BANK ==========
        case 'create':
            if (!in_array($current_role, ['admin', 'manager', 'developer', 'finance'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk membuat rekening']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            $nama_bank = trim($input['nama_bank'] ?? '');
            $nomor_rekening = trim($input['nomor_rekening'] ?? '');
            $atas_nama = trim($input['atas_nama'] ?? '');
            $cabang = trim($input['cabang'] ?? '');
            $kode_swift = trim($input['kode_swift'] ?? '');
            $keterangan = trim($input['keterangan'] ?? '');
            
            // Validasi
            if (empty($nama_bank) || empty($nomor_rekening) || empty($atas_nama)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nama bank, nomor rekening, dan atas nama wajib diisi']);
                exit();
            }
            
            // Cek duplikat
            $stmt = $conn->prepare("SELECT id FROM banks WHERE developer_id = ? AND nomor_rekening = ?");
            $stmt->execute([$developer_id, encryptAccountNumber($nomor_rekening)]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Nomor rekening sudah terdaftar']);
                exit();
            }
            
            // Encrypt nomor rekening
            $encrypted = encryptAccountNumber($nomor_rekening);
            
            $conn->beginTransaction();
            
            try {
                $stmt = $conn->prepare("
                    INSERT INTO banks (developer_id, nama_bank, nomor_rekening, atas_nama, cabang, kode_swift, keterangan, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$developer_id, $nama_bank, $encrypted, $atas_nama, $cabang, $kode_swift, $keterangan]);
                
                $bank_id = $conn->lastInsertId();
                
                // Log audit
                logBankAudit($conn, $bank_id, 'CREATE', null, [
                    'nama_bank' => $nama_bank,
                    'nomor_rekening_masked' => substr($nomor_rekening, 0, 4) . '****' . substr($nomor_rekening, -4),
                    'atas_nama' => $atas_nama
                ]);
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Rekening berhasil ditambahkan',
                    'bank_id' => $bank_id
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
        
        // ========== UPDATE BANK ==========
        case 'update':
            if (!in_array($current_role, ['admin', 'manager', 'developer', 'finance'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk mengupdate rekening']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            $bank_id = isset($input['bank_id']) ? (int)$input['bank_id'] : 0;
            $nama_bank = trim($input['nama_bank'] ?? '');
            $nomor_rekening = trim($input['nomor_rekening'] ?? '');
            $atas_nama = trim($input['atas_nama'] ?? '');
            $cabang = trim($input['cabang'] ?? '');
            $kode_swift = trim($input['kode_swift'] ?? '');
            $keterangan = trim($input['keterangan'] ?? '');
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            
            if ($bank_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Bank ID tidak valid']);
                exit();
            }
            
            if (empty($nama_bank) || empty($nomor_rekening) || empty($atas_nama)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nama bank, nomor rekening, dan atas nama wajib diisi']);
                exit();
            }
            
            // Ambil data lama
            $stmt = $conn->prepare("SELECT * FROM banks WHERE id = ? AND developer_id = ?");
            $stmt->execute([$bank_id, $developer_id]);
            $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$old_data) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Rekening tidak ditemukan']);
                exit();
            }
            
            // Cek duplikat (kecuali dirinya sendiri)
            $encrypted = encryptAccountNumber($nomor_rekening);
            $stmt = $conn->prepare("SELECT id FROM banks WHERE developer_id = ? AND nomor_rekening = ? AND id != ?");
            $stmt->execute([$developer_id, $encrypted, $bank_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Nomor rekening sudah digunakan oleh rekening lain']);
                exit();
            }
            
            $conn->beginTransaction();
            
            try {
                $stmt = $conn->prepare("
                    UPDATE banks SET 
                        nama_bank = ?,
                        nomor_rekening = ?,
                        atas_nama = ?,
                        cabang = ?,
                        kode_swift = ?,
                        keterangan = ?,
                        is_active = ?,
                        updated_at = NOW()
                    WHERE id = ? AND developer_id = ?
                ");
                $stmt->execute([$nama_bank, $encrypted, $atas_nama, $cabang, $kode_swift, $keterangan, $is_active, $bank_id, $developer_id]);
                
                // Log audit
                logBankAudit($conn, $bank_id, 'UPDATE', $old_data, [
                    'nama_bank' => $nama_bank,
                    'nomor_rekening_masked' => substr($nomor_rekening, 0, 4) . '****' . substr($nomor_rekening, -4),
                    'atas_nama' => $atas_nama
                ]);
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Rekening berhasil diupdate'
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
        
        // ========== DELETE BANK ==========
        case 'delete':
            if (!in_array($current_role, ['admin', 'manager', 'developer'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menghapus rekening']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            $bank_id = isset($input['bank_id']) ? (int)$input['bank_id'] : 0;
            
            if ($bank_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Bank ID tidak valid']);
                exit();
            }
            
            // Cek apakah ada marketing yang menggunakan bank ini
            $stmt = $conn->prepare("SELECT COUNT(*) FROM marketing_team WHERE bank_id = ?");
            $stmt->execute([$bank_id]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Rekening tidak dapat dihapus karena masih digunakan oleh marketing. Nonaktifkan saja.'
                ]);
                exit();
            }
            
            // Ambil data lama
            $stmt = $conn->prepare("SELECT * FROM banks WHERE id = ? AND developer_id = ?");
            $stmt->execute([$bank_id, $developer_id]);
            $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$old_data) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Rekening tidak ditemukan']);
                exit();
            }
            
            $conn->beginTransaction();
            
            try {
                $stmt = $conn->prepare("DELETE FROM banks WHERE id = ? AND developer_id = ?");
                $stmt->execute([$bank_id, $developer_id]);
                
                // Log audit
                logBankAudit($conn, $bank_id, 'DELETE', $old_data, null);
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Rekening berhasil dihapus'
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
        
        // ========== GET MARKETING BANK ==========
        case 'get_marketing_bank':
            $marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : (isset($_SESSION['marketing_id']) ? $_SESSION['marketing_id'] : 0);
            
            if ($marketing_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Marketing ID tidak valid']);
                exit();
            }
            
            $stmt = $conn->prepare("
                SELECT 
                    m.bank_id,
                    m.nomor_rekening as marketing_nomor_rekening,
                    m.atas_nama_rekening,
                    m.nama_bank_rekening,
                    b.*
                FROM marketing_team m
                LEFT JOIN banks b ON m.bank_id = b.id
                WHERE m.id = ? AND m.developer_id = ?
            ");
            $stmt->execute([$marketing_id, $developer_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                $result = [
                    'bank_id' => $data['bank_id'],
                    'nama_bank' => $data['nama_bank'] ?? $data['nama_bank_rekening'],
                    'atas_nama' => $data['atas_nama'] ?? $data['atas_nama_rekening'],
                    'cabang' => $data['cabang'] ?? null,
                    'kode_swift' => $data['kode_swift'] ?? null,
                    'is_from_master' => $data['bank_id'] ? true : false
                ];
                
                // Decrypt nomor rekening
                if (!empty($data['nomor_rekening'])) {
                    $decrypted = decryptAccountNumber($data['nomor_rekening']);
                    $result['nomor_rekening'] = $decrypted;
                    $result['nomor_rekening_masked'] = substr($decrypted, 0, 4) . '****' . substr($decrypted, -4);
                } elseif (!empty($data['marketing_nomor_rekening'])) {
                    $result['nomor_rekening'] = $data['marketing_nomor_rekening'];
                    $result['nomor_rekening_masked'] = substr($data['marketing_nomor_rekening'], 0, 4) . '****' . substr($data['marketing_nomor_rekening'], -4);
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => null,
                    'message' => 'Marketing belum memiliki rekening'
                ]);
            }
            break;
        
        // ========== UPDATE MARKETING BANK ==========
        case 'update_marketing_bank':
            if (!in_array($current_role, ['admin', 'manager', 'developer', 'finance', 'manager_developer'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            $marketing_id = isset($input['marketing_id']) ? (int)$input['marketing_id'] : (isset($_SESSION['marketing_id']) ? $_SESSION['marketing_id'] : 0);
            $bank_id = isset($input['bank_id']) ? (int)$input['bank_id'] : 0;
            $nomor_rekening = trim($input['nomor_rekening'] ?? '');
            $atas_nama = trim($input['atas_nama'] ?? '');
            $nama_bank = trim($input['nama_bank'] ?? '');
            
            if ($marketing_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Marketing ID tidak valid']);
                exit();
            }
            
            // Cek apakah marketing ini milik developer yang benar
            $stmt = $conn->prepare("SELECT id FROM marketing_team WHERE id = ? AND developer_id = ?");
            $stmt->execute([$marketing_id, $developer_id]);
            if (!$stmt->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses ke marketing ini']);
                exit();
            }
            
            // Ambil data lama
            $stmt = $conn->prepare("SELECT bank_id, nomor_rekening, atas_nama_rekening, nama_bank_rekening FROM marketing_team WHERE id = ?");
            $stmt->execute([$marketing_id]);
            $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $conn->beginTransaction();
            
            try {
                if ($bank_id > 0) {
                    // Pilih dari master bank
                    $stmt = $conn->prepare("SELECT id, nomor_rekening FROM banks WHERE id = ? AND developer_id = ?");
                    $stmt->execute([$bank_id, $developer_id]);
                    $bank = $stmt->fetch();
                    
                    if (!$bank) {
                        throw new Exception("Rekening tidak ditemukan");
                    }
                    
                    // Update marketing dengan bank_id
                    $stmt = $conn->prepare("
                        UPDATE marketing_team SET 
                            bank_id = ?,
                            nomor_rekening = NULL,
                            atas_nama_rekening = NULL,
                            nama_bank_rekening = NULL,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$bank_id, $marketing_id]);
                    
                } else {
                    // Input manual
                    if (empty($nomor_rekening) || empty($atas_nama) || empty($nama_bank)) {
                        throw new Exception("Nomor rekening, atas nama, dan nama bank wajib diisi untuk input manual");
                    }
                    
                    // Update marketing dengan data manual
                    $stmt = $conn->prepare("
                        UPDATE marketing_team SET 
                            bank_id = NULL,
                            nomor_rekening = ?,
                            atas_nama_rekening = ?,
                            nama_bank_rekening = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$nomor_rekening, $atas_nama, $nama_bank, $marketing_id]);
                }
                
                // Log system
                logSystem("Marketing bank updated", [
                    'marketing_id' => $marketing_id,
                    'bank_id' => $bank_id,
                    'old_data' => $old_data,
                    'by' => $_SESSION['user_id'] ?? $_SESSION['marketing_id'] ?? 0
                ], 'INFO', 'bank.log');
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Rekening marketing berhasil diupdate'
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
        
        // ========== STATS ==========
        case 'stats':
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as nonaktif
                FROM banks 
                WHERE developer_id = ?
            ");
            $stmt->execute([$developer_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Hitung penggunaan di marketing
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(DISTINCT bank_id) as banks_used,
                    COUNT(*) as marketing_with_bank
                FROM marketing_team 
                WHERE developer_id = ? AND bank_id IS NOT NULL
            ");
            $stmt->execute([$developer_id]);
            $usage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => (int)$stats['total'],
                    'aktif' => (int)$stats['aktif'],
                    'nonaktif' => (int)$stats['nonaktif'],
                    'banks_used' => (int)($usage['banks_used'] ?? 0),
                    'marketing_with_bank' => (int)($usage['marketing_with_bank'] ?? 0)
                ]
            ]);
            break;
        
        // ========== AUDIT LOGS ==========
        case 'audit_logs':
            if (!in_array($current_role, ['admin', 'manager'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit();
            }
            
            $bank_id = isset($_GET['bank_id']) ? (int)$_GET['bank_id'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            $sql = "SELECT * FROM bank_audit_logs WHERE 1=1";
            $params = [];
            
            if ($bank_id > 0) {
                $sql .= " AND bank_id = ?";
                $params[] = $bank_id;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $logs
            ]);
            break;
        
        // ========== DEFAULT ==========
        default:
            // Default: ambil semua rekening aktif (untuk dropdown)
            $stmt = $conn->prepare("
                SELECT 
                    id,
                    nama_bank,
                    CONCAT(nama_bank, ' - ', SUBSTRING(nomor_rekening, 1, 4), '****', SUBSTRING(nomor_rekening, -4), ' a.n ', atas_nama) as display_name,
                    nomor_rekening,
                    atas_nama,
                    cabang
                FROM banks 
                WHERE developer_id = ? AND is_active = 1
                ORDER BY nama_bank ASC
            ");
            $stmt->execute([$developer_id]);
            $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decrypt untuk display
            foreach ($banks as &$bank) {
                if (!empty($bank['nomor_rekening'])) {
                    $decrypted = decryptAccountNumber($bank['nomor_rekening']);
                    $bank['nomor_rekening_display'] = $decrypted;
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $banks,
                'total' => count($banks)
            ]);
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Get banks error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>