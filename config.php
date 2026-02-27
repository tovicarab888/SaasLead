<?php
/**
 * CONFIG.PHP - MASTER KONFIGURASI LEAD ENGINE
 * Version: 27.1.0 - OPTIMIZED WITH ALL FUNCTIONS
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

date_default_timezone_set('Asia/Jakarta');

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'taufikma_property');
define('DB_USER', 'taufikma_db');
define('DB_PASS', 'Tasya@2323');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// ============================================
// SECURITY CONFIGURATION
// ============================================
define('SESSION_TIMEOUT', 3600);
define('REMEMBER_TOKEN_EXPIRY', 30);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('RATE_LIMIT_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 300);
define('RATE_LIMIT_BLOCK_TIME', 900);

// ============================================
// WEBAUTHN CONFIGURATION
// ============================================
define('RP_ID', 'taufikmarie.com');
define('RP_NAME', 'TaufikMarie.com');
define('WEBAUTHN_TIMEOUT', 60000);

// ============================================
// MARKETING CONFIGURATION
// ============================================
define('MARKETING_NAME', 'Taufik Marie');
define('MARKETING_PHONE', '628133150078');
define('MARKETING_EMAIL', 'lapakmarie@gmail.com');
define('MARKETING_NUMBER_ID', 'BO-U80VWtQlpti3IlSj');
define('MARKETING_TOKEN', 'VwrFrkYj1l1841fn58M');

// ============================================
// NOTIFICATION CONFIGURATION
// ============================================
define('NOTIFICATION_NUMBER_ID', 'BO-GU8Ll274yVjj0hQc');
define('NOTIFICATION_PHONE', '6281122234555');

// ============================================
// WHATSAPP API
// ============================================
define('WHATSAPP_API_URL', 'https://api.balesotomatis.id/public/v1/send_personal_message');
define('WHATSAPP_TIMEOUT', 15);

// ============================================
// SYSTEM CONFIGURATION
// ============================================
define('SITE_URL', 'https://leadproperti.com');  // <-- UBAH INI
define('SITE_NAME', 'LeadProperti.com');
define('SYSTEM_VERSION', '27.1.0-optimized');
define('API_KEY', 'taufikmarie7878');

// ============================================
// PATHS
// ============================================
define('BASE_PATH', dirname(__DIR__, 3) . '/');
define('ADMIN_PATH', dirname(__DIR__) . '/');
define('API_PATH', __DIR__ . '/');
define('LOG_PATH', ADMIN_PATH . 'logs/');
define('SESSION_PATH', LOG_PATH . 'sessions/');
define('UPLOAD_PATH', ADMIN_PATH . 'uploads/');
define('CANVASING_PATH', UPLOAD_PATH . 'canvasing/');
define('BUKTI_PATH', UPLOAD_PATH . 'bukti_pembayaran/');
define('AKAD_PATH', UPLOAD_PATH . 'akad/');
define('PROFILE_PATH', UPLOAD_PATH . 'profiles/');

// ============================================
// LEAD SCORING CONFIGURATION
// ============================================
define('SCORE_DEAL', 100);
define('SCORE_BOOKING', 85);
define('SCORE_SURVEY', 75);
define('SCORE_FOLLOW_UP', 65);
define('SCORE_BARU', 50);
define('SCORE_NEGATIF_MAX', 30);

define('SCORE_SOURCE_GOOGLE', 60);
define('SCORE_SOURCE_FACEBOOK', 55);
define('SCORE_SOURCE_TIKTOK', 50);
define('SCORE_SOURCE_INSTAGRAM', 65);
define('SCORE_SOURCE_REFERENSI', 65);
define('SCORE_SOURCE_WEBSITE', 50);
define('SCORE_SOURCE_WHATSAPP', 70);
define('SCORE_SOURCE_OFFLINE', 80);
define('SCORE_SOURCE_DEFAULT', 50);

define('SCORE_SOURCE_BROSUR', 40);
define('SCORE_SOURCE_EVENT', 45);
define('SCORE_SOURCE_IKLAN_KANTOR_IG', 50);
define('SCORE_SOURCE_IKLAN_KANTOR_FB', 52);
define('SCORE_SOURCE_IKLAN_KANTOR_TT', 48);
define('SCORE_SOURCE_IKLAN_PRIBADI_IG', 60);
define('SCORE_SOURCE_IKLAN_PRIBADI_FB', 62);
define('SCORE_SOURCE_IKLAN_PRIBADI_TT', 58);
define('SCORE_SOURCE_REFERENSI_NAMA', 70);

define('SCORE_BONUS_EMAIL', 5);
define('SCORE_BONUS_NAME_LENGTH', 5);
define('SCORE_BONUS_PHONE_LENGTH', 5);
define('SCORE_BONUS_LOCATION_PREMIUM', 5);

$PREMIUM_LOCATIONS = ['kertamulya', 'windusari'];

// ============================================
// KOMISI CONFIGURATION
// ============================================
define('KOMISI_INTERNAL_DEFAULT', 1000000);
define('KOMISI_EKSTERNAL_PERSEN_DEFAULT', 3.00);

// ============================================
// TRACKING CONFIGURATION
// ============================================
define('TRACKING_RETRY_ATTEMPTS', 3);
define('TRACKING_RETRY_DELAY', 5);
define('TRACKING_LOG_ENABLED', true);
define('META_API_VERSION', 'v19.0');
define('TIKTOK_API_VERSION', 'v1.3');

// ============================================
// START SESSION - VERSION FINAL
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    $session_path = __DIR__ . '/../logs/sessions';
    if (!is_dir($session_path)) {
        mkdir($session_path, 0777, true);
    }
    
    session_save_path($session_path);
    
    // DETECT HTTPS - DEFINISIKAN DULU!
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || $_SERVER['SERVER_PORT'] == 443;
    
    // SET COOKIE PARAMS
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,  // SEKARANG $is_https SUDAH TERDEFINISI
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_name('TM_SESSION');
    
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $is_https ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_path', '/');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 86400);
    
    session_start();
}

// ============================================
// DATABASE CONNECTION
// ============================================
function getDB() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_COLLATION,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            $conn->exec("SET time_zone = '+07:00'");
            
        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage());
            return null;
        }
    }
    
    return $conn;
}

// ============================================
// CSRF PROTECTION FUNCTIONS
// ============================================
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token) {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        return false;
    }
    if ($token !== $_SESSION[CSRF_TOKEN_NAME]) {
        return false;
    }
    if (time() - ($_SESSION[CSRF_TOKEN_NAME . '_time'] ?? 0) > 3600) {
        unset($_SESSION[CSRF_TOKEN_NAME]);
        unset($_SESSION[CSRF_TOKEN_NAME . '_time']);
        return false;
    }
    return true;
}

function regenerateCSRFToken() {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
}

// ============================================
// RATE LIMITING FUNCTIONS
// ============================================
function checkRateLimit($key, $maxAttempts = RATE_LIMIT_ATTEMPTS, $window = RATE_LIMIT_WINDOW, $blockTime = RATE_LIMIT_BLOCK_TIME) {
    $conn = getDB();
    if (!$conn) return true;
    
    $ip = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $fingerprint = hash('sha256', $ip . $userAgent . $key);
    
    try {
        $stmt = $conn->prepare("
            SELECT * FROM rate_limits 
            WHERE fingerprint = ? 
            AND blocked_until > NOW()
        ");
        $stmt->execute([$fingerprint]);
        if ($stmt->fetch()) {
            return false;
        }
        
        $stmt = $conn->prepare("DELETE FROM rate_limits WHERE expires_at < NOW()");
        $stmt->execute();
        
        $stmt = $conn->prepare("
            INSERT INTO rate_limits (fingerprint, attempt_count, created_at, expires_at) 
            VALUES (?, 1, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))
            ON DUPLICATE KEY UPDATE 
                attempt_count = attempt_count + 1,
                expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$fingerprint, $window, $window]);
        
        $stmt = $conn->prepare("
            SELECT attempt_count FROM rate_limits 
            WHERE fingerprint = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$fingerprint, $window]);
        $count = $stmt->fetchColumn();
        
        if ($count >= $maxAttempts) {
            $stmt = $conn->prepare("
                UPDATE rate_limits SET 
                    blocked_until = DATE_ADD(NOW(), INTERVAL ? SECOND),
                    expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
                WHERE fingerprint = ?
            ");
            $stmt->execute([$blockTime, $blockTime, $fingerprint]);
            
            logSystem("Rate limit exceeded", ['fingerprint' => $fingerprint, 'key' => $key], 'WARNING', 'security.log');
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Rate limit error: " . $e->getMessage());
        return true;
    }
}

// ============================================
// DATABASE UTILITY FUNCTIONS
// ============================================
function beginTransaction() {
    $db = getDB();
    if ($db) {
        return $db->beginTransaction();
    }
    return false;
}

function commitTransaction() {
    $db = getDB();
    if ($db) {
        return $db->commit();
    }
    return false;
}

function rollbackTransaction() {
    $db = getDB();
    if ($db) {
        return $db->rollBack();
    }
    return false;
}

function inTransaction() {
    $db = getDB();
    if ($db) {
        return $db->inTransaction();
    }
    return false;
}

function lastInsertId() {
    $db = getDB();
    if ($db) {
        return $db->lastInsertId();
    }
    return 0;
}

// ============================================
// VALIDASI NOMOR TELEPON
// ============================================
function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) < 10 || strlen($phone) > 15) {
        return ['valid' => false, 'message' => 'Nomor harus 10-15 digit'];
    }
    
    if (!preg_match('/^(0|62|8)/', $phone)) {
        return ['valid' => false, 'message' => 'Nomor harus diawali 0, 62, atau 8'];
    }
    
    if (substr($phone, 0, 1) == '0') {
        $phone = '62' . substr($phone, 1);
    } elseif (substr($phone, 0, 1) == '8') {
        $phone = '62' . $phone;
    }
    
    if (!preg_match('/^62[0-9]{9,13}$/', $phone)) {
        return ['valid' => false, 'message' => 'Format nomor tidak valid'];
    }
    
    return ['valid' => true, 'number' => $phone];
}

// ============================================
// CEK DUPLICATE LEAD
// ============================================
function checkDuplicateLead($conn, $phone, $email = '', $exclude_id = 0) {
    $result = [
        'is_duplicate' => false,
        'existing_data' => null,
        'phone_duplicate' => false,
        'email_duplicate' => false
    ];
    
    if (!$conn) return $result;
    
    try {
        $sql = "SELECT id, first_name, last_name, email, deleted_at FROM leads 
                WHERE phone = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        $params = [$phone];
        
        if ($exclude_id > 0) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $sql .= " LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $phone_dup = $stmt->fetch();
        
        if ($phone_dup) {
            $result['is_duplicate'] = true;
            $result['phone_duplicate'] = true;
            $result['existing_data'] = $phone_dup;
            return $result;
        }
        
        if (!empty($email)) {
            $sql = "SELECT id, first_name, last_name, phone, deleted_at FROM leads 
                    WHERE email = ? AND email != '' 
                    AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
            $params = [$email];
            
            if ($exclude_id > 0) {
                $sql .= " AND id != ?";
                $params[] = $exclude_id;
            }
            
            $sql .= " LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $email_dup = $stmt->fetch();
            
            if ($email_dup) {
                $result['is_duplicate'] = true;
                $result['email_duplicate'] = true;
                $result['existing_data'] = $email_dup;
                return $result;
            }
        }
        
    } catch (Exception $e) {
        error_log("Error in checkDuplicateLead: " . $e->getMessage());
    }
    
    return $result;
}

function markDuplicateWarning($conn, $lead_id, $reason = 'phone') {
    if (!$conn || $lead_id <= 0) return false;
    
    try {
        $stmt = $conn->prepare("UPDATE leads SET is_duplicate_warning = 1 WHERE id = ?");
        return $stmt->execute([$lead_id]);
    } catch (Exception $e) {
        error_log("Error in markDuplicateWarning: " . $e->getMessage());
        return false;
    }
}

// ============================================
// GET LOCATION DETAILS
// ============================================
function getLocationDetails($key) {
    $conn = getDB();
    if (!$conn) return ['display_name' => ucfirst($key), 'icon' => '🏠', 'is_active' => 1];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM locations WHERE location_key = ?");
        $stmt->execute([$key]);
        $location = $stmt->fetch();
        
        if (!$location) {
            return [
                'display_name' => ucfirst(str_replace('_', ' ', $key)),
                'icon' => '🏠',
                'city' => 'Kuningan',
                'is_active' => 1
            ];
        }
        
        if (!empty($location['subsidi_units'])) {
            $location['subsidi_units_array'] = explode(',', $location['subsidi_units']);
        } else {
            $location['subsidi_units_array'] = [];
        }
        
        if (!empty($location['komersil_units'])) {
            $location['komersil_units_array'] = explode(',', $location['komersil_units']);
        } else {
            $location['komersil_units_array'] = [];
        }
        
        return $location;
        
    } catch (Exception $e) {
        error_log("Error in getLocationDetails: " . $e->getMessage());
        return ['display_name' => ucfirst($key), 'icon' => '🏠', 'is_active' => 1];
    }
}

function getAllLocations() {
    $conn = getDB();
    if (!$conn) return [];
    
    try {
        $stmt = $conn->query("SELECT * FROM locations WHERE is_active = 1 ORDER BY sort_order ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error in getAllLocations: " . $e->getMessage());
        return [];
    }
}

function getLocationsForUser($user_id = null, $role = null, $location_access = null) {
    $conn = getDB();
    if (!$conn) return [];
    
    try {
        if ($role === 'admin' || $role === 'manager' || $role === 'finance_platform') {
            $stmt = $conn->query("SELECT * FROM locations ORDER BY sort_order");
            return $stmt->fetchAll();
            
        } elseif ($role === 'developer' && $location_access) {
            $locations = explode(',', $location_access);
            $locations = array_map('trim', $locations);
            $placeholders = implode(',', array_fill(0, count($locations), '?'));
            $stmt = $conn->prepare("SELECT * FROM locations WHERE location_key IN ($placeholders) ORDER BY sort_order");
            $stmt->execute($locations);
            return $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error in getLocationsForUser: " . $e->getMessage());
    }
    
    return [];
}

// ============================================
// WHATSAPP MESSAGES
// ============================================
function getWhatsAppMessages($location_key) {
    $conn = getDB();
    if (!$conn) return ['pesan1' => '', 'pesan2' => '', 'pesan3' => '', 'pesan_cs' => ''];
    
    try {
        $stmt = $conn->prepare("SELECT message_type, message_text FROM whatsapp_messages WHERE location_key = ?");
        $stmt->execute([$location_key]);
        
        $messages = ['pesan1' => '', 'pesan2' => '', 'pesan3' => '', 'pesan_cs' => ''];
        while ($row = $stmt->fetch()) {
            $messages[$row['message_type']] = $row['message_text'];
        }
        
        return $messages;
        
    } catch (Exception $e) {
        error_log("Error in getWhatsAppMessages: " . $e->getMessage());
        return ['pesan1' => '', 'pesan2' => '', 'pesan3' => '', 'pesan_cs' => ''];
    }
}

// ============================================
// GET CLIENT IP
// ============================================
function getClientIP() {
    $sources = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($sources as $source) {
        if (!empty($_SERVER[$source])) {
            $ip = $_SERVER[$source];
            if (strpos($ip, ',') !== false) $ip = explode(',', $ip)[0];
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getUserAgentInfo() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $info = [
        'browser' => 'Unknown',
        'browser_version' => '',
        'os' => 'Unknown',
        'device' => 'desktop',
        'user_agent' => $ua
    ];
    
    if (empty($ua)) return $info;
    
    if (preg_match('/(android|iphone|ipad|ipod|mobile|blackberry|opera mini|iemobile)/i', $ua)) {
        $info['device'] = 'mobile';
    } elseif (preg_match('/(tablet|ipad)/i', $ua)) {
        $info['device'] = 'tablet';
    }
    
    if (preg_match('/windows nt 10/i', $ua)) $info['os'] = 'Windows 10';
    elseif (preg_match('/mac os x/i', $ua)) $info['os'] = 'macOS';
    elseif (preg_match('/linux/i', $ua)) $info['os'] = 'Linux';
    elseif (preg_match('/android/i', $ua)) $info['os'] = 'Android';
    elseif (preg_match('/iphone/i', $ua)) $info['os'] = 'iOS';
    
    return $info;
}

function generateEventId($prefix = 'EVT') {
    return $prefix . '_' . time() . '_' . bin2hex(random_bytes(8));
}

function logSystem($message, $data = null, $level = 'INFO', $file = 'system.log') {
    $log_dir = LOG_PATH;
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . $file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";
    if ($data) {
        if (is_array($data)) {
            $log_entry .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $log_entry .= $data . "\n";
        }
    }
    $log_entry .= str_repeat("-", 80) . "\n";
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// ============================================
// GET MARKETING CONFIG
// ============================================
function getMarketingConfig() {
    $conn = getDB();
    if (!$conn) {
        return [
            'id' => 2,
            'name' => MARKETING_NAME,
            'phone' => MARKETING_PHONE,
            'email' => MARKETING_EMAIL,
            'number_id' => MARKETING_NUMBER_ID,
            'access_token' => MARKETING_TOKEN,
            'notification_number_id' => NOTIFICATION_NUMBER_ID,
            'notification_token' => MARKETING_TOKEN
        ];
    }
    
    try {
        $stmt = $conn->prepare("SELECT * FROM marketing_config WHERE id = 2");
        $stmt->execute();
        $config = $stmt->fetch();
        
        if ($config) {
            return [
                'id' => $config['id'],
                'name' => $config['name'],
                'phone' => $config['phone'],
                'email' => $config['email'],
                'number_id' => $config['number_id'],
                'access_token' => $config['access_token'],
                'notification_number_id' => $config['notification_number_id'] ?? NOTIFICATION_NUMBER_ID,
                'notification_token' => $config['notification_token'] ?? $config['access_token']
            ];
        }
    } catch (Exception $e) {
        error_log("Error in getMarketingConfig: " . $e->getMessage());
    }
    
    return [
        'id' => 2,
        'name' => MARKETING_NAME,
        'phone' => MARKETING_PHONE,
        'email' => MARKETING_EMAIL,
        'number_id' => MARKETING_NUMBER_ID,
        'access_token' => MARKETING_TOKEN,
        'notification_number_id' => NOTIFICATION_NUMBER_ID,
        'notification_token' => MARKETING_TOKEN
    ];
}

function updateMarketingConfig($data) {
    $conn = getDB();
    if (!$conn) return false;
    
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("
            UPDATE marketing_config SET 
                name = ?,
                phone = ?,
                email = ?,
                number_id = ?,
                notification_number_id = ?,
                access_token = ?,
                notification_token = ?,
                updated_at = NOW()
            WHERE id = 2
        ");
        
        $result = $stmt->execute([
            $data['nama_lengkap'],
            $data['nomor_whatsapp'],
            $data['email'],
            $data['number_id'],
            $data['notification_number_id'] ?? NOTIFICATION_NUMBER_ID,
            $data['access_token'],
            $data['notification_token'] ?? $data['access_token']
        ]);
        
        $conn->commit();
        return $result;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error in updateMarketingConfig: " . $e->getMessage());
        return false;
    }
}

function getExternalMarketingData() {
    $conn = getDB();
    if (!$conn) {
        return [
            'nama_lengkap' => MARKETING_NAME,
            'nomor_whatsapp' => MARKETING_PHONE,
            'email' => MARKETING_EMAIL
        ];
    }
    
    try {
        $stmt = $conn->prepare("SELECT name, phone, email FROM marketing_config WHERE id = 2");
        $stmt->execute();
        $data = $stmt->fetch();
        
        if ($data) {
            return [
                'nama_lengkap' => $data['name'],
                'nomor_whatsapp' => $data['phone'],
                'email' => $data['email'] ?? MARKETING_EMAIL
            ];
        }
    } catch (Exception $e) {
        error_log("Error in getExternalMarketingData: " . $e->getMessage());
    }
    
    return [
        'nama_lengkap' => MARKETING_NAME,
        'nomor_whatsapp' => MARKETING_PHONE,
        'email' => MARKETING_EMAIL
    ];
}

// ============================================
// GET TRACKING CONFIG
// ============================================
function getTrackingConfig($platform = null, $developer_id = null) {
    $conn = getDB();
    if (!$conn) {
        $defaults = [
            'meta' => ['pixel_id' => '2224730075026860', 'access_token' => '', 'api_version' => 'v19.0', 'is_active' => 1],
            'tiktok' => ['pixel_id' => 'D3L405BC77U8AFC9O0RG', 'access_token' => '', 'api_version' => 'v1.3', 'is_active' => 1],
            'google' => ['measurement_id' => 'G-B9YZXZQ8L8', 'api_secret' => '', 'is_active' => 1]
        ];
        return $platform ? ($defaults[$platform] ?? null) : $defaults;
    }
    
    try {
        if ($developer_id !== null) {
            $stmt = $conn->prepare("
                SELECT * FROM tracking_config 
                WHERE developer_id = ? AND is_active = 1
                ORDER BY platform
            ");
            $stmt->execute([$developer_id]);
            $dev_configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($dev_configs)) {
                $result = [];
                foreach ($dev_configs as $cfg) {
                    $result[$cfg['platform']] = $cfg;
                }
                return $platform ? ($result[$platform] ?? null) : $result;
            }
        }
        
        if ($platform) {
            $stmt = $conn->prepare("
                SELECT * FROM tracking_config 
                WHERE platform = ? AND (developer_id IS NULL OR developer_id = 0) AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$platform]);
            return $stmt->fetch();
        } else {
            $stmt = $conn->prepare("
                SELECT * FROM tracking_config 
                WHERE (developer_id IS NULL OR developer_id = 0) AND is_active = 1
                ORDER BY platform
            ");
            $stmt->execute();
            $configs = [];
            while ($row = $stmt->fetch()) {
                $configs[$row['platform']] = $row;
            }
            return $configs;
        }
        
    } catch (Exception $e) {
        error_log("Error in getTrackingConfig: " . $e->getMessage());
        $defaults = [
            'meta' => ['pixel_id' => '2224730075026860', 'api_version' => 'v19.0', 'is_active' => 1],
            'tiktok' => ['pixel_id' => 'D3L405BC77U8AFC9O0RG', 'api_version' => 'v1.3', 'is_active' => 1],
            'google' => ['measurement_id' => 'G-B9YZXZQ8L8', 'is_active' => 1]
        ];
        return $platform ? ($defaults[$platform] ?? null) : $defaults;
    }
}

// ============================================
// LEAD SCORING FUNCTIONS
// ============================================
function calculateSourceScore($source) {
    $source = strtolower(trim($source));
    
    $source_scores = [
        'google' => SCORE_SOURCE_GOOGLE,
        'google ads' => SCORE_SOURCE_GOOGLE,
        'google organic' => SCORE_SOURCE_GOOGLE,
        'facebook' => SCORE_SOURCE_FACEBOOK,
        'meta' => SCORE_SOURCE_FACEBOOK,
        'fb' => SCORE_SOURCE_FACEBOOK,
        'instagram' => SCORE_SOURCE_INSTAGRAM,
        'ig' => SCORE_SOURCE_INSTAGRAM,
        'tiktok' => SCORE_SOURCE_TIKTOK,
        'tt' => SCORE_SOURCE_TIKTOK,
        'referensi' => SCORE_SOURCE_REFERENSI,
        'referral' => SCORE_SOURCE_REFERENSI,
        'teman' => SCORE_SOURCE_REFERENSI,
        'website' => SCORE_SOURCE_WEBSITE,
        'landing page' => SCORE_SOURCE_WEBSITE,
        'whatsapp' => SCORE_SOURCE_WHATSAPP,
        'wa' => SCORE_SOURCE_WHATSAPP,
        'offline' => SCORE_SOURCE_OFFLINE,
        'walk in' => SCORE_SOURCE_OFFLINE,
        'kantor' => SCORE_SOURCE_OFFLINE,
        'brosur' => SCORE_SOURCE_BROSUR,
        'event' => SCORE_SOURCE_EVENT,
        'iklan_kantor_ig' => SCORE_SOURCE_IKLAN_KANTOR_IG,
        'iklan_kantor_fb' => SCORE_SOURCE_IKLAN_KANTOR_FB,
        'iklan_kantor_tt' => SCORE_SOURCE_IKLAN_KANTOR_TT,
        'iklan_pribadi_ig' => SCORE_SOURCE_IKLAN_PRIBADI_IG,
        'iklan_pribadi_fb' => SCORE_SOURCE_IKLAN_PRIBADI_FB,
        'iklan_pribadi_tt' => SCORE_SOURCE_IKLAN_PRIBADI_TT,
        'referensi_nama' => SCORE_SOURCE_REFERENSI_NAMA
    ];
    
    if (isset($source_scores[$source])) {
        return $source_scores[$source];
    }
    
    foreach ($source_scores as $key => $score) {
        if (strpos($source, $key) !== false) {
            return $score;
        }
    }
    
    return SCORE_SOURCE_DEFAULT;
}

function calculateBonusPoints($lead_data) {
    $bonus = 0;
    
    if (!empty($lead_data['email']) && filter_var($lead_data['email'], FILTER_VALIDATE_EMAIL)) {
        $bonus += SCORE_BONUS_EMAIL;
    }
    
    $full_name = trim(($lead_data['first_name'] ?? '') . ' ' . ($lead_data['last_name'] ?? ''));
    if (strlen($full_name) > 10) {
        $bonus += SCORE_BONUS_NAME_LENGTH;
    } elseif (strlen($full_name) > 5) {
        $bonus += 2;
    }
    
    $phone = $lead_data['phone'] ?? '';
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone_clean) >= 12) {
        $bonus += SCORE_BONUS_PHONE_LENGTH;
    } elseif (strlen($phone_clean) >= 11) {
        $bonus += 3;
    } elseif (strlen($phone_clean) >= 10) {
        $bonus += 2;
    }
    
    global $PREMIUM_LOCATIONS;
    if (!empty($lead_data['location_key']) && in_array($lead_data['location_key'], $PREMIUM_LOCATIONS)) {
        $bonus += SCORE_BONUS_LOCATION_PREMIUM;
    }
    
    return min($bonus, 20);
}

function calculateLeadScorePremium($status, $lead_data = [], $old_status = null) {
    $deal_statuses = ['Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'];
    if (in_array($status, $deal_statuses)) {
        return SCORE_DEAL;
    }
    
    $negative_statuses = ['Tolak Slik', 'Tidak Minat', 'Batal'];
    if (in_array($status, $negative_statuses)) {
        $source_score = isset($lead_data['source']) ? calculateSourceScore($lead_data['source']) : SCORE_SOURCE_DEFAULT;
        return min($source_score, SCORE_NEGATIF_MAX);
    }
    
    $source_score = isset($lead_data['source']) ? calculateSourceScore($lead_data['source']) : SCORE_SOURCE_DEFAULT;
    
    $status_score_map = [
        'Baru' => SCORE_BARU,
        'Follow Up' => SCORE_FOLLOW_UP,
        'Survey' => SCORE_SURVEY,
        'Booking' => SCORE_BOOKING,
    ];
    
    $status_score = $status_score_map[$status] ?? SCORE_BARU;
    
    $base_score = (int)round(($source_score * 0.5) + ($status_score * 0.5));
    $bonus = calculateBonusPoints($lead_data);
    $final_score = $base_score + $bonus;
    
    return min(max($final_score, 0), 100);
}

function calculateLeadScore($first_name, $last_name, $phone, $email, $location, $source = 'website') {
    $lead_data = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone' => $phone,
        'email' => $email,
        'location_key' => $location,
        'source' => $source
    ];
    return calculateLeadScorePremium('Baru', $lead_data);
}

function calculateLeadScoreFinal($status, $lead_data = [], $old_status = null) {
    return calculateLeadScorePremium($status, $lead_data, $old_status);
}

function updateLeadScoreWithActivity($conn, $lead_id, $marketing_id, $new_status, $old_status = null, $note = '') {
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("
            SELECT l.*, u.id as developer_id 
            FROM leads l
            LEFT JOIN users u ON l.ditugaskan_ke = u.id
            WHERE l.id = ? FOR UPDATE
        ");
        $stmt->execute([$lead_id]);
        $lead = $stmt->fetch();
        
        if (!$lead) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Lead tidak ditemukan'];
        }
        
        if ($lead['assigned_marketing_team_id'] != $marketing_id) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Anda tidak memiliki akses ke lead ini'];
        }
        
        $old_status = $old_status ?: $lead['status'];
        $old_score = (int)$lead['lead_score'];
        
        $lead_data = [
            'first_name' => $lead['first_name'],
            'last_name' => $lead['last_name'],
            'phone' => $lead['phone'],
            'email' => $lead['email'],
            'location_key' => $lead['location_key'],
            'source' => $lead['source']
        ];
        
        $new_score = calculateLeadScorePremium($new_status, $lead_data, $old_status);
        
        $update = $conn->prepare("
            UPDATE leads SET 
                status = ?,
                lead_score = ?,
                last_followup_at = NOW(),
                total_followups = total_followups + 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $update->execute([$new_status, $new_score, $lead_id]);
        
        $activity = $conn->prepare("
            INSERT INTO marketing_activities (
                lead_id, marketing_id, developer_id, action_type,
                status_before, status_after, score_before, score_after,
                note_text, created_at
            ) VALUES (?, ?, ?, 'update_status', ?, ?, ?, ?, ?, NOW())
        ");
        $activity->execute([
            $lead_id,
            $marketing_id,
            $lead['developer_id'] ?: 0,
            $old_status,
            $new_status,
            $old_score,
            $new_score,
            $note ?: "Update status dari $old_status ke $new_status"
        ]);
        
        $conn->commit();
        
        return [
            'success' => true,
            'old_score' => $old_score,
            'new_score' => $new_score,
            'old_status' => $old_status,
            'new_status' => $new_status
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error in updateLeadScoreWithActivity: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================
// SYSTEM STATISTICS
// ============================================
function getSystemStats($conn, $user_id = null, $user_role = null) {
    $stats = [];
    
    try {
        $base_condition = "(deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        $params = [];
        
        if ($user_role === 'developer' && $user_id) {
            $stmt = $conn->prepare("SELECT location_access FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user && $user['location_access']) {
                $locations = explode(',', $user['location_access']);
                $locations = array_map('trim', $locations);
                $placeholders = implode(',', array_fill(0, count($locations), '?'));
                $base_condition .= " AND location_key IN ($placeholders)";
                $params = $locations;
            }
        }
        
        if (in_array($user_role, ['manager_developer', 'finance']) && isset($_SESSION['developer_id'])) {
            $dev_id = $_SESSION['developer_id'];
            $base_condition .= " AND ditugaskan_ke = ?";
            $params[] = $dev_id;
        }
        
        if ($user_role === 'finance_platform') {
            $base_condition .= " AND assigned_type = 'external'";
        }
        
        $sql = "SELECT COUNT(*) FROM leads WHERE $base_condition";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $stats['total'] = $stmt->fetchColumn();
        
        $sql = "SELECT COUNT(*) FROM leads WHERE DATE(created_at) = CURDATE() AND $base_condition";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $stats['today'] = $stmt->fetchColumn();
        
        $sql = "SELECT COUNT(*) FROM leads WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) AND $base_condition";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $stats['week'] = $stmt->fetchColumn();
        
        $sql = "SELECT COUNT(*) FROM leads WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND $base_condition";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $stats['month'] = $stmt->fetchColumn();
        
        $sql = "SELECT COUNT(*) FROM leads WHERE is_duplicate_warning = 1 AND $base_condition";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $stats['duplicate_warnings'] = $stmt->fetchColumn();
        
        $score_sql = "SELECT 
            COUNT(CASE WHEN status IN ('Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun') THEN 1 END) as hot,
            COUNT(CASE WHEN status IN ('Booking', 'Survey', 'Follow Up') THEN 1 END) as warm,
            COUNT(CASE WHEN status IN ('Tolak Slik', 'Tidak Minat', 'Batal') THEN 1 END) as cold,
            AVG(lead_score) as avg_score
            FROM leads WHERE $base_condition";
        
        $stmt = $conn->prepare($score_sql);
        $stmt->execute($params);
        $score = $stmt->fetch();
        
        $stats['hot'] = $score['hot'] ?? 0;
        $stats['warm'] = $score['warm'] ?? 0;
        $stats['cold'] = $score['cold'] ?? 0;
        $stats['avg_score'] = $score['avg_score'] ? round($score['avg_score']) : 0;
        
    } catch (Exception $e) {
        error_log("Error in getSystemStats: " . $e->getMessage());
        $stats = [
            'total' => 0, 'today' => 0, 'week' => 0, 'month' => 0,
            'hot' => 0, 'warm' => 0, 'cold' => 0, 'avg_score' => 0,
            'duplicate_warnings' => 0
        ];
    }
    
    return $stats;
}

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================
function loginUser($username, $password, $remember = false) {
    $conn = getDB();
    if (!$conn) return false;
    
    if (!checkRateLimit('login_' . $username)) {
        logSystem("Login blocked - rate limit", ['username' => $username], 'WARNING', 'security.log');
        return false;
    }
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['location_access'] = $user['location_access'] ?? '';
            $_SESSION['distribution_mode'] = $user['distribution_mode'] ?? 'FULL_EXTERNAL';
            $_SESSION['login_time'] = time();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['ip_address'] = getClientIP();
            
            if ($user['role'] === 'developer') {
                $_SESSION['developer_alamat'] = $user['alamat'] ?? '';
                $_SESSION['developer_kota'] = $user['kota'] ?? '';
                $_SESSION['developer_npwp'] = $user['npwp'] ?? '';
                $_SESSION['developer_siup'] = $user['siup'] ?? '';
                $_SESSION['developer_telepon'] = $user['telepon_perusahaan'] ?? '';
            }
            
            if (in_array($user['role'], ['manager_developer', 'finance'])) {
                $_SESSION['developer_id'] = (int)($user['developer_id'] ?? 0);
            }
            
            if ($user['role'] === 'finance_platform') {
                logSystem("Finance Platform login: " . $user['username']);
            }
            
            regenerateCSRFToken();
            
            $update = $conn->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
            $update->execute([getClientIP(), $user['id']]);
            
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+' . REMEMBER_TOKEN_EXPIRY . ' days'));
                
                $conn->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?")
                    ->execute([$token, $expiry, $user['id']]);
                
                setcookie('remember_token', $token, [
                    'expires' => time() + (REMEMBER_TOKEN_EXPIRY * 86400),
                    'path' => '/',
                    'domain' => '',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            
            logSystem("Login successful", ['user_id' => $user['id'], 'username' => $username, 'role' => $user['role']], 'INFO', 'auth.log');
            return true;
        }
        
        logSystem("Login failed", ['username' => $username], 'WARNING', 'auth.log');
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
    }
    
    return false;
}

function checkAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        logSystem("Session hijacking detected", ['user_agent_mismatch' => true], 'CRITICAL', 'security.log');
        return false;
    }
    
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] < SESSION_TIMEOUT)) {
            $_SESSION['login_time'] = time();
            return true;
        } else {
            logSystem("Session expired", ['user_id' => $_SESSION['user_id'] ?? 'unknown'], 'INFO', 'auth.log');
            return false;
        }
    }
    
    if (isset($_SESSION['marketing_id']) && $_SESSION['marketing_id'] > 0) {
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] < SESSION_TIMEOUT)) {
            $_SESSION['login_time'] = time();
            return true;
        } else {
            logSystem("Marketing session expired", ['marketing_id' => $_SESSION['marketing_id'] ?? 'unknown'], 'INFO', 'auth.log');
            return false;
        }
    }
    
    if (isset($_COOKIE['remember_token'])) {
        $conn = getDB();
        if ($conn) {
            try {
                $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ? AND token_expiry > NOW() AND is_active = 1");
                $stmt->execute([$_COOKIE['remember_token']]);
                $user = $stmt->fetch();
                
                if ($user) {
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['location_access'] = $user['location_access'] ?? '';
                    $_SESSION['distribution_mode'] = $user['distribution_mode'] ?? 'FULL_EXTERNAL';
                    $_SESSION['login_time'] = time();
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                    $_SESSION['ip_address'] = getClientIP();
                    
                    if (in_array($user['role'], ['manager_developer', 'finance'])) {
                        $_SESSION['developer_id'] = (int)($user['developer_id'] ?? 0);
                    }
                    
                    regenerateCSRFToken();
                    
                    logSystem("Remember token login", ['user_id' => $user['id']], 'INFO', 'auth.log');
                    return true;
                }
            } catch (Exception $e) {
                error_log("Remember me check error: " . $e->getMessage());
            }
        }
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    return false;
}

// ============================================
// ROLE CHECK FUNCTIONS
// ============================================
function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function isManager() { return isset($_SESSION['role']) && $_SESSION['role'] === 'manager'; }
function isFinancePlatform() { return isset($_SESSION['role']) && $_SESSION['role'] === 'finance_platform'; }
function isDeveloper() { return isset($_SESSION['role']) && $_SESSION['role'] === 'developer'; }
function isManagerDeveloper() { return isset($_SESSION['role']) && $_SESSION['role'] === 'manager_developer'; }
function isFinance() { return isset($_SESSION['role']) && $_SESSION['role'] === 'finance'; }
function isMarketing() { return isset($_SESSION['marketing_id']) && $_SESSION['marketing_id'] > 0; }

function getCurrentRole() {
    if (isset($_SESSION['role'])) return $_SESSION['role'];
    if (isset($_SESSION['marketing_id']) && $_SESSION['marketing_id'] > 0) return 'marketing';
    return 'guest';
}

function getUserDeveloperId() {
    return $_SESSION['developer_id'] ?? 0;
}

// ============================================
// REQUIRE AUTH FUNCTIONS
// ============================================
function requireAuth() {
    if (!checkAuth()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

function requireAdmin() { requireAuth(); if (!isAdmin()) { header('HTTP/1.0 403 Forbidden'); die('Akses ditolak. Halaman ini hanya untuk Super Admin.'); } }
function requireManager() { requireAuth(); if (!isManager()) { header('HTTP/1.0 403 Forbidden'); die('Akses ditolak. Halaman ini hanya untuk Manager Platform.'); } }
function requireFinancePlatform() { requireAuth(); if (!isFinancePlatform()) { header('HTTP/1.0 403 Forbidden'); die('Akses ditolak. Halaman ini hanya untuk Finance Platform.'); } }
function requireDeveloper() { requireAuth(); if (!isDeveloper()) { header('HTTP/1.0 403 Forbidden'); die('Akses ditolak. Halaman ini hanya untuk Developer.'); } }
function requireManagerDeveloper() { requireAuth(); if (!isManagerDeveloper()) { header('HTTP/1.0 403 Forbidden'); die('Akses ditolak. Halaman ini hanya untuk Manager Developer.'); } }
function requireFinance() { requireAuth(); if (!isFinance()) { header('HTTP/1.0 403 Forbidden'); die('Akses ditolak. Halaman ini hanya untuk Finance Developer.'); } }
function requireMarketing() { if (!isMarketing()) { header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit(); } }

// ============================================
// SELECT NEXT MARKETING (LEGACY)
// ============================================
function selectNextMarketing($conn) {
    $default = [
        'id' => 2,
        'nama_lengkap' => MARKETING_NAME,
        'nomor_whatsapp' => MARKETING_PHONE,
        'email' => MARKETING_EMAIL,
        'number_id' => MARKETING_NUMBER_ID,
        'access_token' => MARKETING_TOKEN
    ];
    
    if (!$conn) return $default;
    
    try {
        $stmt = $conn->query("SELECT * FROM marketing_config WHERE is_active = 1 ORDER BY last_assigned ASC");
        $marketings = $stmt->fetchAll();
        
        if (empty($marketings)) return $default;
        
        $selected = $marketings[0];
        
        $update = $conn->prepare("UPDATE marketing_config SET last_assigned = NOW() WHERE id = ?");
        $update->execute([$selected['id']]);
        
        return [
            'id' => $selected['id'],
            'nama_lengkap' => $selected['name'],
            'nomor_whatsapp' => $selected['phone'],
            'email' => $selected['email'],
            'number_id' => $selected['number_id'],
            'access_token' => $selected['access_token']
        ];
        
    } catch (Exception $e) {
        error_log("Error in selectNextMarketing: " . $e->getMessage());
        return $default;
    }
}

// ============================================
// ROUND ROBIN FUNCTIONS FOR INTERNAL MARKETING
// ============================================
function getNextInternalMarketing($conn, $developer_id) {
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS developer_rr_counters (
                developer_id INT PRIMARY KEY,
                last_index INT DEFAULT 0,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt = $conn->prepare("
            SELECT id, nama_lengkap, phone, username, notification_template 
            FROM marketing_team 
            WHERE developer_id = ? AND is_active = 1 
            ORDER BY id ASC
        ");
        $stmt->execute([$developer_id]);
        $active_marketing = $stmt->fetchAll();
        
        if (empty($active_marketing)) {
            return null;
        }
        
        $stmt = $conn->prepare("SELECT last_index FROM developer_rr_counters WHERE developer_id = ?");
        $stmt->execute([$developer_id]);
        $counter = $stmt->fetch();
        
        $total = count($active_marketing);
        $last_index = $counter ? (int)$counter['last_index'] : -1;
        
        $next_index = ($last_index + 1) % $total;
        $selected = $active_marketing[$next_index];
        
        if ($counter) {
            $stmt = $conn->prepare("UPDATE developer_rr_counters SET last_index = ? WHERE developer_id = ?");
            $stmt->execute([$next_index, $developer_id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO developer_rr_counters (developer_id, last_index) VALUES (?, ?)");
            $stmt->execute([$developer_id, $next_index]);
        }
        
        $stmt = $conn->prepare("UPDATE marketing_team SET last_assigned = NOW() WHERE id = ?");
        $stmt->execute([$selected['id']]);
        
        return [
            'id' => $selected['id'],
            'nama_lengkap' => $selected['nama_lengkap'],
            'phone' => $selected['phone'],
            'username' => $selected['username'],
            'notification_template' => $selected['notification_template']
        ];
        
    } catch (Exception $e) {
        error_log("Round robin error: " . $e->getMessage());
        return null;
    }
}

// ============================================
// GET NEXT EXTERNAL MARKETING (ROUND ROBIN) - DENGAN LIST NAMA
// ============================================
function getNextExternalMarketing() {
    $conn = getDB();
    if (!$conn) {
        return [
            'id' => 0,
            'user_id' => 1,
            'nama_lengkap' => MARKETING_NAME,
            'phone' => MARKETING_PHONE,
            'email' => MARKETING_EMAIL,
            'username' => 'admin'
        ];
    }
    
    try {
        // Cek apakah ada marketing external aktif
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM marketing_external_team met
            JOIN users u ON met.user_id = u.id
            WHERE met.is_active = 1 AND u.is_active = 1 AND u.role = 'marketing_external'
        ");
        $stmt->execute();
        $total_active = $stmt->fetchColumn();
        
        if ($total_active == 0) {
            error_log("⚠️ Tidak ada marketing external aktif, fallback ke super admin");
            return [
                'id' => 0,
                'user_id' => 1,
                'nama_lengkap' => MARKETING_NAME,
                'phone' => MARKETING_PHONE,
                'email' => MARKETING_EMAIL,
                'username' => 'admin'
            ];
        }
        
        // AMBIL SEMUA EXTERNAL MARKETING DENGAN URUTAN
        $stmt = $conn->prepare("
            SELECT 
                met.id,
                met.user_id,
                met.round_robin_order,
                met.last_assigned,
                u.id as user_id,
                u.nama_lengkap,
                u.contact_phone as phone,
                u.email,
                u.username,
                u.profile_photo
            FROM marketing_external_team met
            JOIN users u ON met.user_id = u.id
            WHERE met.is_active = 1 AND u.is_active = 1 AND u.role = 'marketing_external'
            ORDER BY
                CASE WHEN met.last_assigned IS NULL THEN 0 ELSE 1 END,
                met.round_robin_order ASC,
                met.last_assigned ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute();
        $marketing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$marketing) {
            error_log("❌ Tidak ada marketing external tersedia");
            return [
                'id' => 0,
                'user_id' => 1,
                'nama_lengkap' => MARKETING_NAME,
                'phone' => MARKETING_PHONE,
                'email' => MARKETING_EMAIL,
                'username' => 'admin'
            ];
        }
        
        // UPDATE last_assigned
        $update = $conn->prepare("
            UPDATE marketing_external_team
            SET last_assigned = NOW()
            WHERE id = ?
        ");
        $update->execute([$marketing['id']]);
        
        return [
            'id' => $marketing['id'],
            'user_id' => $marketing['user_id'],
            'nama_lengkap' => $marketing['nama_lengkap'],
            'phone' => $marketing['phone'],
            'email' => $marketing['email'],
            'username' => $marketing['username'],
            'profile_photo' => $marketing['profile_photo'] ?? null,
            'round_robin_order' => $marketing['round_robin_order']
        ];
        
    } catch (Exception $e) {
        error_log("Error in getNextExternalMarketing: " . $e->getMessage());
        return [
            'id' => 0,
            'user_id' => 1,
            'nama_lengkap' => MARKETING_NAME,
            'phone' => MARKETING_PHONE,
            'email' => MARKETING_EMAIL,
            'username' => 'admin'
        ];
    }
}

// ============================================
// CEK AKSES EXTERNAL MARKETING KE DEVELOPER
// ============================================
function canExternalAccessDeveloper($external_user_id, $developer_id) {
    $conn = getDB();
    if (!$conn) return true;
    
    if ($external_user_id == 1) return true; // Super admin selalu bisa
    
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM developer_external_access dea
            JOIN marketing_external_team met ON dea.marketing_external_id = met.id
            JOIN users u ON met.user_id = u.id
            WHERE u.id = ? AND dea.developer_id = ? AND dea.can_access = 1
            AND u.role = 'marketing_external'  -- PASTIKAN ROLE BENAR
        ");
        $stmt->execute([$external_user_id, $developer_id]);
        return $stmt->fetchColumn() > 0;
        
    } catch (Exception $e) {
        error_log("Error in canExternalAccessDeveloper: " . $e->getMessage());
        return true;
    }
}

// ============================================
// NEW: GET ALL EXTERNAL MARKETING
// ============================================
function getAllExternalMarketing($conn, $include_inactive = false) {
    try {
        $sql = "
            SELECT 
                met.*,
                u.id as user_id,
                u.nama_lengkap,
                u.phone,
                u.email,
                u.username,
                u.profile_photo,
                u.is_active as user_active
            FROM marketing_external_team met
            JOIN users u ON met.user_id = u.id
            WHERE 1=1
        ";
        
        if (!$include_inactive) {
            $sql .= " AND met.is_active = 1 AND u.is_active = 1";
        }
        
        $sql .= " ORDER BY met.round_robin_order ASC, met.id ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error in getAllExternalMarketing: " . $e->getMessage());
        return [];
    }
}

// ============================================
// DETERMINE BUCKET (INTERNAL/EXTERNAL)
// ============================================
function determineBucket($developer_id) {
    $conn = getDB();
    if (!$conn) return 'external';
    
    try {
        $stmt = $conn->prepare("SELECT distribution_mode FROM users WHERE id = ?");
        $stmt->execute([$developer_id]);
        $mode = $stmt->fetchColumn();
        
        if ($mode === 'FULL_EXTERNAL') {
            return 'external';
        }
        
        $conn->exec("
            CREATE TABLE IF NOT EXISTS developer_bucket_counters (
                developer_id INT PRIMARY KEY,
                last_bucket ENUM('internal', 'external') DEFAULT 'internal',
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt = $conn->prepare("SELECT last_bucket FROM developer_bucket_counters WHERE developer_id = ? FOR UPDATE");
        $stmt->execute([$developer_id]);
        $last = $stmt->fetchColumn();
        
        if (!$last) {
            $next = 'internal';
            $insert = $conn->prepare("INSERT INTO developer_bucket_counters (developer_id, last_bucket) VALUES (?, ?)");
            $insert->execute([$developer_id, $next]);
            return $next;
        }
        
        $next = ($last === 'internal') ? 'external' : 'internal';
        
        $update = $conn->prepare("UPDATE developer_bucket_counters SET last_bucket = ? WHERE developer_id = ?");
        $update->execute([$next, $developer_id]);
        
        return $next;
        
    } catch (Exception $e) {
        error_log("Error in determineBucket: " . $e->getMessage());
        return 'external';
    }
}

// ============================================
// GET SUPER ADMIN DATA
// ============================================
function getSuperAdminData($conn) {
    $external_data = getExternalMarketingData();
    
    return [
        'id' => 1,
        'nama_lengkap' => $external_data['nama_lengkap'],
        'phone' => $external_data['nomor_whatsapp'],
        'email' => $external_data['email'],
        'username' => 'admin',
        'notification_template' => "🔔 *LEAD BARU UNTUK ANDA!*\n\nHalo *{marketing_name}*,\n\nAnda mendapatkan lead baru:\n• Nama: {customer_name}\n• WhatsApp: {customer_phone}\n• Lokasi: {location}\n• Waktu: {datetime}\n\nSegera hubungi customer:\nhttps://wa.me/{customer_phone}\n\nTerima kasih,\n*LeadEngine*"
    ];
}

// ============================================
// MODIFIED: ASSIGN LEAD TO MARKETING - FIXED v2.0
// ============================================
function assignLeadToMarketing($conn, $developer_id, $lead_data) {
    // ===== DEBUG LOGGING (AKAN DIHAPUS NANTI) =====
    $log_file = __DIR__ . '/../../logs/assign_debug.log';
    $debug_data = [
        'time' => date('Y-m-d H:i:s'),
        'developer_id' => $developer_id,
        'lead_data' => $lead_data
    ];
    file_put_contents($log_file, "=== ASSIGN LEAD CALLED ===\n" . json_encode($debug_data, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
    
    // Ambil data developer
    $stmt = $conn->prepare("SELECT distribution_mode, location_access, nama_lengkap FROM users WHERE id = ?");
    $stmt->execute([$developer_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        error_log("Developer ID $developer_id tidak ditemukan");
        file_put_contents($log_file, "ERROR: Developer tidak ditemukan\n", FILE_APPEND);
        
        $external = getNextExternalMarketing();
        return [
            'assigned_type' => 'external',
            'assigned_marketing_team_id' => $external['user_id'] ?? 1,
            'assigned_marketing_name' => $external['nama_lengkap'],
            'assigned_marketing_phone' => $external['phone'],
            'assigned_marketing_id' => $external['user_id'] ?? 1,
            'marketing_data' => $external,
            'developer_id' => $developer_id,
            'developer_name' => 'Unknown Developer',
            'developer_mode' => 'FULL_EXTERNAL',
            'source_used' => $lead_data['source'] ?? 'unknown'
        ];
    }
    
    $mode = $user['distribution_mode'] ?? 'FULL_EXTERNAL';
    $location_access = $user['location_access'] ?? '';
    $developer_name = $user['nama_lengkap'] ?? 'Developer';
    
    // ===== CEK AKSES LOKASI =====
    if (!empty($location_access) && isset($lead_data['location'])) {
        $locations = array_map('trim', explode(',', $location_access));
        if (!in_array($lead_data['location'], $locations)) {
            error_log("Developer $developer_id tidak punya akses ke lokasi " . $lead_data['location']);
            file_put_contents($log_file, "Developer tidak punya akses ke lokasi\n", FILE_APPEND);
            
            $external = getNextExternalMarketing();
            return [
                'assigned_type' => 'external',
                'assigned_marketing_team_id' => $external['user_id'] ?? 1,
                'assigned_marketing_name' => $external['nama_lengkap'],
                'assigned_marketing_phone' => $external['phone'],
                'assigned_marketing_id' => $external['user_id'] ?? 1,
                'marketing_data' => $external,
                'developer_id' => $developer_id,
                'developer_name' => $developer_name,
                'developer_mode' => $mode,
                'source_used' => $lead_data['source'] ?? 'unknown'
            ];
        }
    }
    
    // ===== DETEKSI SOURCE UNTUK PENENTUAN INTERNAL/EXTERNAL =====
    $source = strtolower($lead_data['source'] ?? '');
    $assigned_type = 'external'; // default
    $assigned_marketing_team_id = null;
    $assigned_marketing_name = '';
    $assigned_marketing_phone = '';
    $marketing_data = null;
    
    // SOURCE YANG HARUS KE MARKETING SENDIRI (BUKAN SPLIT)
    $personal_sources = [
        'iklan_pribadi', 'iklan_pribadi_ig', 'iklan_pribadi_fb', 'iklan_pribadi_tt',
        'brosur', 'event', 'referensi', 'referensi_nama', 'walk_in', 'kantor'
    ];
    
    file_put_contents($log_file, "Source: $source, Mode: $mode\n", FILE_APPEND);
    
    // CEK APAKAH SOURCE INI PERSONAL (MILIK MARKETING SENDIRI)
    $is_personal_source = false;
    foreach ($personal_sources as $ps) {
        if (strpos($source, $ps) !== false) {
            $is_personal_source = true;
            break;
        }
    }
    
    // JIKA SOURCE PERSONAL, WAJIB KE INTERNAL
    if ($is_personal_source) {
        file_put_contents($log_file, "DETEKSI: Personal source, akan assign ke internal\n", FILE_APPEND);
        
        $internal = getNextInternalMarketing($conn, $developer_id);
        if ($internal) {
            $assigned_type = 'internal';
            $assigned_marketing_team_id = $internal['id'];
            $assigned_marketing_name = $internal['nama_lengkap'];
            $assigned_marketing_phone = $internal['phone'];
            $marketing_data = $internal;
            
            file_put_contents($log_file, "Assign ke internal: ID {$internal['id']} - {$internal['nama_lengkap']}\n", FILE_APPEND);
            
            return [
                'assigned_type' => $assigned_type,
                'assigned_marketing_team_id' => $assigned_marketing_team_id,
                'assigned_marketing_name' => $assigned_marketing_name,
                'assigned_marketing_phone' => $assigned_marketing_phone,
                'assigned_marketing_id' => $assigned_marketing_team_id,
                'marketing_data' => $marketing_data,
                'developer_id' => $developer_id,
                'developer_name' => $developer_name,
                'developer_mode' => $mode,
                'source_used' => $source,
                'assignment_reason' => 'personal_source'
            ];
        }
    }
    
    // JIKA MODE FULL_EXTERNAL, LANGSUNG KE EXTERNAL
    if ($mode === 'FULL_EXTERNAL') {
        file_put_contents($log_file, "Mode FULL_EXTERNAL, assign ke external\n", FILE_APPEND);
        
        $external = getNextExternalMarketing();
        return [
            'assigned_type' => 'external',
            'assigned_marketing_team_id' => $external['user_id'] ?? 1,
            'assigned_marketing_name' => $external['nama_lengkap'],
            'assigned_marketing_phone' => $external['phone'],
            'assigned_marketing_id' => $external['user_id'] ?? 1,
            'marketing_data' => $external,
            'developer_id' => $developer_id,
            'developer_name' => $developer_name,
            'developer_mode' => $mode,
            'source_used' => $source,
            'assignment_reason' => 'full_external_mode'
        ];
    }
    
    // JIKA MODE SPLIT_50_50, GUNAKAN BUCKET
    if ($mode === 'SPLIT_50_50') {
        $bucket = determineBucket($developer_id);
        file_put_contents($log_file, "Mode SPLIT_50_50, bucket: $bucket\n", FILE_APPEND);
        
        if ($bucket === 'external') {
            $external = getNextExternalMarketing();
            return [
                'assigned_type' => 'external',
                'assigned_marketing_team_id' => $external['user_id'] ?? 1,
                'assigned_marketing_name' => $external['nama_lengkap'],
                'assigned_marketing_phone' => $external['phone'],
                'assigned_marketing_id' => $external['user_id'] ?? 1,
                'marketing_data' => $external,
                'developer_id' => $developer_id,
                'developer_name' => $developer_name,
                'developer_mode' => $mode,
                'source_used' => $source,
                'assignment_reason' => 'split_external'
            ];
        }
        
        // BUCKET INTERNAL
        $internal = getNextInternalMarketing($conn, $developer_id);
        if ($internal) {
            return [
                'assigned_type' => 'internal',
                'assigned_marketing_team_id' => $internal['id'],
                'assigned_marketing_name' => $internal['nama_lengkap'],
                'assigned_marketing_phone' => $internal['phone'],
                'assigned_marketing_id' => $internal['id'],
                'marketing_data' => $internal,
                'developer_id' => $developer_id,
                'developer_name' => $developer_name,
                'developer_mode' => $mode,
                'source_used' => $source,
                'assignment_reason' => 'split_internal'
            ];
        }
    }
    
    // FALLBACK: Jika tidak ada internal, gunakan external
    file_put_contents($log_file, "FALLBACK: Tidak ada internal, gunakan external\n", FILE_APPEND);
    
    $external = getNextExternalMarketing();
    return [
        'assigned_type' => 'external',
        'assigned_marketing_team_id' => $external['user_id'] ?? 1,
        'assigned_marketing_name' => $external['nama_lengkap'],
        'assigned_marketing_phone' => $external['phone'],
        'assigned_marketing_id' => $external['user_id'] ?? 1,
        'marketing_data' => $external,
        'developer_id' => $developer_id,
        'developer_name' => $developer_name,
        'developer_mode' => $mode,
        'source_used' => $source,
        'assignment_reason' => 'fallback'
    ];
}

// ============================================
// SEND MARKETING NOTIFICATION
// ============================================
function sendMarketingNotification($marketing, $customer, $location) {
    $config = getMarketingConfig();
    
    $api_url = WHATSAPP_API_URL;
    $api_key = $config['notification_token'] ?? $config['access_token'];
    $number_id = NOTIFICATION_NUMBER_ID;
    
    $target_clean = preg_replace('/[^0-9]/', '', $marketing['phone']);
    if (substr($target_clean, 0, 1) == '0') {
        $target_formatted = '62' . substr($target_clean, 1);
    } elseif (substr($target_clean, 0, 2) != '62') {
        $target_formatted = '62' . $target_clean;
    } else {
        $target_formatted = $target_clean;
    }
    
    $template = $marketing['notification_template'] ?? "🔔 *LEAD BARU UNTUK ANDA!*\n\nHalo *{marketing_name}*,\n\nAnda mendapatkan lead baru:\n• Nama: {customer_name}\n• WhatsApp: {customer_phone}\n• Lokasi: {location}\n• Waktu: {datetime}\n\nSegera hubungi customer:\nhttps://wa.me/{customer_phone}\n\nTerima kasih,\n*LeadEngine*";
    
    $placeholders = [
        '{marketing_name}' => $marketing['nama_lengkap'],
        '{customer_name}' => $customer['full_name'] ?? $customer['first_name'] ?? 'Customer',
        '{customer_phone}' => $customer['phone'] ?? '',
        '{location}' => $location['display_name'] ?? $location['location_key'] ?? 'Unknown',
        '{datetime}' => date('d/m/Y H:i')
    ];
    
    $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
    
    $payload = [
        'api_key' => $api_key,
        'number_id' => $number_id,
        'enable_typing' => "1",
        'method_send' => "async",
        'phone_no' => $target_formatted,
        'country_code' => "62",
        'message' => $message
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    logSystem("Marketing notification sent", [
        'target' => $target_formatted,
        'http_code' => $http_code,
        'marketing_name' => $marketing['nama_lengkap']
    ], 'INFO', 'notifications.log');
    
    return ($http_code == 200);
}

// ============================================
// META TRACKING
// ============================================
function sendMetaTracking($data, $developer_id = null) {
    $config = getTrackingConfig('meta', $developer_id);
    if (!$config || empty($config['pixel_id']) || empty($config['is_active'])) {
        return ['success' => false, 'error' => 'No config'];
    }
    
    try {
        $customer_id = $data['customer_id'] ?? 0;
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $location = $data['location'] ?? '';
        $event_id = $data['meta_event_id'] ?? 'META_' . time() . '_' . uniqid();
        $fbp = $data['fbp'] ?? '';
        $fbc = $data['fbc'] ?? '';
        $client_ip = $data['client_ip'] ?? getClientIP();
        $user_agent = $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $page_url = $data['page_url'] ?? $_SERVER['HTTP_REFERER'] ?? SITE_URL;
        $event_name = $data['event_name'] ?? 'Lead';
        
        $hashed_data = [];
        if (!empty($email)) $hashed_data['em'] = [hash('sha256', strtolower(trim($email)))];
        if (!empty($phone)) $hashed_data['ph'] = [hash('sha256', preg_replace('/[^0-9]/', '', $phone))];
        if (!empty($first_name)) $hashed_data['fn'] = [hash('sha256', strtolower(trim($first_name)))];
        if (!empty($last_name)) $hashed_data['ln'] = [hash('sha256', strtolower(trim($last_name)))];
        
        $user_data = [
            'client_ip_address' => $client_ip,
            'client_user_agent' => $user_agent,
            'fbp' => $fbp,
            'fbc' => $fbc,
            'external_id' => [(string)$customer_id]
        ] + $hashed_data;
        
        $custom_data = [
            'value' => 500000000,
            'currency' => 'IDR',
            'content_name' => 'Rumah Subsidi ' . $location,
            'content_category' => 'Real Estate',
            'content_ids' => ['LEAD_' . $customer_id],
            'content_type' => 'product',
            'num_items' => 1
        ];
        
        $payload = [
            'data' => [[
                'event_name' => $event_name,
                'event_time' => time(),
                'event_id' => $event_id,
                'action_source' => 'website',
                'event_source_url' => $page_url,
                'user_data' => array_filter($user_data),
                'custom_data' => $custom_data
            ]],
            'access_token' => $config['access_token'] ?? '',
        ];
        
        $url = "https://graph.facebook.com/" . ($config['api_version'] ?? META_API_VERSION) . "/" . $config['pixel_id'] . "/events";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (function_exists('saveTrackingLog')) {
            saveTrackingLog(
                $customer_id,
                $developer_id ?? 0,
                'meta',
                $event_name,
                $event_id,
                $payload,
                ($http_code == 200) ? 'sent' : 'failed',
                $response
            );
        }
        
        return ['success' => ($http_code == 200), 'event_id' => $event_id];
        
    } catch (Exception $e) {
        error_log("Meta tracking error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ============================================
// TIKTOK TRACKING
// ============================================
function sendTikTokTracking($data, $developer_id = null) {
    $config = getTrackingConfig('tiktok', $developer_id);
    if (!$config || empty($config['pixel_id']) || empty($config['is_active'])) {
        return ['success' => false, 'error' => 'No config'];
    }
    
    try {
        $customer_id = $data['customer_id'] ?? 0;
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $location = $data['location'] ?? '';
        $event_id = $data['tiktok_event_id'] ?? 'TT_' . time() . '_' . uniqid();
        $ttclid = $data['ttclid'] ?? '';
        $client_ip = $data['client_ip'] ?? getClientIP();
        $user_agent = $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $page_url = $data['page_url'] ?? $_SERVER['HTTP_REFERER'] ?? SITE_URL;
        $event_name = $data['event_name'] ?? 'CompleteRegistration';
        
        $hashed_email = !empty($email) ? [hash('sha256', strtolower(trim($email)))] : [];
        $hashed_phone = !empty($phone) ? [hash('sha256', preg_replace('/[^0-9]/', '', $phone))] : [];
        
        $payload = [
            'pixel_code' => $config['pixel_id'],
            'event' => $event_name,
            'event_id' => $event_id,
            'timestamp' => date('c'),
            'context' => [
                'user' => [
                    'email' => $hashed_email,
                    'phone' => $hashed_phone,
                    'external_id' => [(string)$customer_id]
                ],
                'ad' => ['callback' => $ttclid],
                'page' => [
                    'url' => $page_url,
                    'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
                ],
                'ip' => $client_ip,
                'user_agent' => $user_agent
            ],
            'properties' => [
                'contents' => [[
                    'content_id' => 'LEAD_' . $customer_id,
                    'content_name' => 'Rumah Subsidi ' . $location,
                    'content_category' => 'Real Estate',
                    'quantity' => 1,
                    'price' => 500000000
                ]],
                'value' => 500000000,
                'currency' => 'IDR'
            ],
            'test_mode' => false
        ];
        
        $url = "https://business-api.tiktok.com/open_api/" . ($config['api_version'] ?? TIKTOK_API_VERSION) . "/pixel/track/";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Access-Token: ' . ($config['access_token'] ?? '')
            ],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (function_exists('saveTrackingLog')) {
            saveTrackingLog(
                $customer_id,
                $developer_id ?? 0,
                'tiktok',
                $event_name,
                $event_id,
                $payload,
                ($http_code == 200) ? 'sent' : 'failed',
                $response
            );
        }
        
        return ['success' => ($http_code == 200), 'event_id' => $event_id];
        
    } catch (Exception $e) {
        error_log("TikTok tracking error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ============================================
// GOOGLE ANALYTICS TRACKING
// ============================================
function sendGATracking($data, $developer_id = null) {
    $config = getTrackingConfig('google', $developer_id);
    if (!$config || empty($config['measurement_id']) || empty($config['is_active'])) {
        return ['success' => false, 'error' => 'No config'];
    }
    
    try {
        $customer_id = $data['customer_id'] ?? 0;
        $first_name = $data['first_name'] ?? '';
        $full_name = $data['full_name'] ?? $first_name;
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $location = $data['location'] ?? '';
        $fbp = $data['fbp'] ?? '';
        $page_url = $data['page_url'] ?? $_SERVER['HTTP_REFERER'] ?? SITE_URL;
        $event_name = $data['event_name'] ?? 'generate_lead';
        
        $client_id = $fbp ?: 'lead_' . $customer_id . '_' . time();
        
        $payload = [
            'client_id' => $client_id,
            'user_id' => (string)$customer_id,
            'events' => [[
                'name' => $event_name,
                'params' => [
                    'value' => 500000000,
                    'currency' => 'IDR',
                    'lead_id' => (string)$customer_id,
                    'location' => $location,
                    'customer_name' => $full_name,
                    'customer_email' => $email,
                    'customer_phone' => substr(preg_replace('/[^0-9]/', '', $phone), 0, 4) . '****' . substr(preg_replace('/[^0-9]/', '', $phone), -3),
                    'page_location' => $page_url,
                    'page_referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                    'engagement_time_msec' => '1000'
                ]
            ]]
        ];
        
        $url = "https://www.google-analytics.com/mp/collect?measurement_id=" . $config['measurement_id'] . "&api_secret=" . ($config['api_secret'] ?? '');
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (function_exists('saveTrackingLog')) {
            saveTrackingLog(
                $customer_id,
                $developer_id ?? 0,
                'google',
                $event_name,
                $client_id,
                $payload,
                ($http_code >= 200 && $http_code < 300) ? 'sent' : 'failed',
                $response
            );
        }
        
        return ['success' => ($http_code >= 200 && $http_code < 300), 'client_id' => $client_id];
        
    } catch (Exception $e) {
        error_log("GA tracking error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ============================================
// SEND ALL TRACKING
// ============================================
function sendAllTracking($data, $developer_id = null) {
    $results = [];
    $results['meta'] = sendMetaTracking($data, $developer_id);
    $results['tiktok'] = sendTikTokTracking($data, $developer_id);
    $results['ga'] = sendGATracking($data, $developer_id);
    
    logSystem("Tracking results", ['developer_id' => $developer_id, 'results' => $results], 'INFO', 'tracking.log');
    
    return $results;
}

// ============================================
// SAVE TRACKING LOG
// ============================================
function saveTrackingLog($lead_id, $developer_id, $pixel_type, $event_name, $event_id, $payload, $status, $response = '') {
    $conn = getDB();
    if (!$conn) return false;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO tracking_logs (
                lead_id, developer_id, pixel_type, event_name, event_id, 
                payload, status, response, sent_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        return $stmt->execute([
            $lead_id,
            $developer_id,
            $pixel_type,
            $event_name,
            $event_id,
            is_array($payload) ? json_encode($payload) : $payload,
            $status,
            $response
        ]);
    } catch (Exception $e) {
        error_log("Error saving tracking log: " . $e->getMessage());
        return false;
    }
}

function getTrackingLogs($lead_id) {
    $conn = getDB();
    if (!$conn) return [];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM tracking_logs WHERE lead_id = ? ORDER BY created_at DESC");
        $stmt->execute([$lead_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting tracking logs: " . $e->getMessage());
        return [];
    }
}

// ============================================
// HITUNG KOMISI
// ============================================
function hitungKomisi($unit_data, $lead_data = []) {
    $result = [
        'komisi_eksternal_persen' => 0,
        'komisi_eksternal_rupiah' => 0,
        'komisi_internal_rupiah' => 0,
        'komisi_final_eksternal' => 0,
        'komisi_final_internal' => 0,
        'assigned_type' => $lead_data['assigned_type'] ?? 'external'
    ];
    
    $harga = $unit_data['harga'] ?? 0;
    $komisi_eksternal_persen = $unit_data['komisi_eksternal_persen'] ?? KOMISI_EKSTERNAL_PERSEN_DEFAULT;
    $komisi_eksternal_rupiah = $unit_data['komisi_eksternal_rupiah'] ?? 0;
    $komisi_internal_rupiah = $unit_data['komisi_internal_rupiah'] ?? KOMISI_INTERNAL_DEFAULT;
    
    $result['komisi_eksternal_persen'] = $komisi_eksternal_persen;
    $result['komisi_eksternal_rupiah'] = $komisi_eksternal_rupiah;
    $result['komisi_internal_rupiah'] = $komisi_internal_rupiah;
    
    if ($lead_data['assigned_type'] === 'internal') {
        $result['komisi_final_internal'] = $komisi_internal_rupiah;
        $result['komisi_final_eksternal'] = 0;
    } else {
        if ($komisi_eksternal_rupiah > 0) {
            $result['komisi_final_eksternal'] = $komisi_eksternal_rupiah;
        } else {
            $result['komisi_final_eksternal'] = $harga * ($komisi_eksternal_persen / 100);
        }
        $result['komisi_final_internal'] = 0;
    }
    
    return $result;
}

function createKomisiLog($conn, $lead_id, $komisi_data) {
    try {
        $stmt = $conn->prepare("
            SELECT l.*, u.id as developer_id, u.nama_lengkap as developer_name,
                   m.id as marketing_id, m.nama_lengkap as marketing_name,
                   un.id as unit_id, un.nomor_unit, un.tipe_unit
            FROM leads l
            LEFT JOIN users u ON l.ditugaskan_ke = u.id
            LEFT JOIN marketing_team m ON l.assigned_marketing_team_id = m.id
            LEFT JOIN units un ON l.id = un.lead_id
            WHERE l.id = ?
        ");
        $stmt->execute([$lead_id]);
        $lead = $stmt->fetch();
        
        if (!$lead) return 0;
        
        $komisi_final = ($lead['assigned_type'] === 'internal') 
            ? $komisi_data['komisi_final_internal'] 
            : $komisi_data['komisi_final_eksternal'];
        
        $stmt = $conn->prepare("
            INSERT INTO komisi_logs (
                lead_id, marketing_id, developer_id, unit_id,
                assigned_type, komisi_eksternal_persen, komisi_eksternal_rupiah,
                komisi_internal_rupiah, komisi_final, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $lead_id,
            $lead['assigned_marketing_team_id'],
            $lead['developer_id'],
            $lead['unit_id'],
            $lead['assigned_type'],
            $komisi_data['komisi_eksternal_persen'],
            $komisi_data['komisi_eksternal_rupiah'],
            $komisi_data['komisi_internal_rupiah'],
            $komisi_final
        ]);
        
        return $conn->lastInsertId();
        
    } catch (Exception $e) {
        error_log("Error createKomisiLog: " . $e->getMessage());
        return 0;
    }
}

// ============================================
// NOTIFIKASI KOMISI
// ============================================
function sendKomisiNotificationToMarketing($komisi_data, $marketing_data, $bank_data = null) {
    $config = getMarketingConfig();
    
    $api_url = WHATSAPP_API_URL;
    $api_key = $config['notification_token'] ?? $config['access_token'];
    $number_id = NOTIFICATION_NUMBER_ID;
    
    $target_clean = preg_replace('/[^0-9]/', '', $marketing_data['phone']);
    $target_formatted = (substr($target_clean, 0, 1) == '0') 
        ? '62' . substr($target_clean, 1) 
        : ((substr($target_clean, 0, 2) != '62') ? '62' . $target_clean : $target_clean);
    
    $template = "🔔 *KOMISI ANDA TELAH CAIR!*\n\n"
              . "Halo *{marketing_name}*,\n\n"
              . "Selamat! Komisi untuk penjualan unit telah cair:\n\n"
              . "📋 *DETAIL TRANSAKSI:*\n"
              . "• Customer: {customer_name}\n"
              . "• Unit: {unit_info}\n"
              . "• Harga: {harga_formatted}\n"
              . "• Komisi: {komisi_formatted}\n"
              . "• Tanggal Cair: {tanggal_cair}\n\n";
    
    if ($bank_data) {
        $template .= "💰 *KOMISI TELAH DITRANSFER KE:*\n"
                   . "🏦 Bank: {bank_name}\n"
                   . "📱 Rekening: {nomor_rekening}\n"
                   . "👤 Atas Nama: {atas_nama}\n\n";
    }
    
    $template .= "Terima kasih atas kerjasamanya!\n"
               . "*{developer_name}*";
    
    $placeholders = [
        '{marketing_name}' => $marketing_data['nama_lengkap'],
        '{customer_name}' => $komisi_data['customer_name'],
        '{unit_info}' => $komisi_data['unit_info'],
        '{harga_formatted}' => 'Rp ' . number_format($komisi_data['harga'], 0, ',', '.'),
        '{komisi_formatted}' => 'Rp ' . number_format($komisi_data['komisi_final'], 0, ',', '.'),
        '{tanggal_cair}' => date('d/m/Y H:i'),
        '{developer_name}' => $komisi_data['developer_name']
    ];
    
    if ($bank_data) {
        $placeholders['{bank_name}'] = $bank_data['nama_bank'];
        $placeholders['{nomor_rekening}'] = $bank_data['nomor_rekening'];
        $placeholders['{atas_nama}'] = $bank_data['atas_nama'];
    }
    
    $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
    
    $payload = [
        'api_key' => $api_key,
        'number_id' => $number_id,
        'enable_typing' => "1",
        'method_send' => "async",
        'phone_no' => $target_formatted,
        'country_code' => "62",
        'message' => $message
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code == 200);
}

function sendKomisiNotificationToExternal($komisi_data, $admin_data, $rekening_info = '') {
    $config = getMarketingConfig();
    
    $api_url = WHATSAPP_API_URL;
    $api_key = $config['notification_token'] ?? $config['access_token'];
    $number_id = NOTIFICATION_NUMBER_ID;
    
    $target_clean = preg_replace('/[^0-9]/', '', $admin_data['phone']);
    $target_formatted = (substr($target_clean, 0, 1) == '0') 
        ? '62' . substr($target_clean, 1) 
        : ((substr($target_clean, 0, 2) != '62') ? '62' . $target_clean : $target_clean);
    
    $template = "🔔 *KOMISI EKSTERNAL TELAH CAIR!*\n\n"
              . "Halo *{admin_name}*,\n\n"
              . "Komisi untuk lead eksternal telah cair:\n\n"
              . "📋 *DETAIL TRANSAKSI:*\n"
              . "• Customer: {customer_name}\n"
              . "• Developer: {developer_name}\n"
              . "• Unit: {unit_info}\n"
              . "• Harga: {harga_formatted}\n"
              . "• Komisi Eksternal: {komisi_formatted}\n"
              . "• Tanggal Cair: {tanggal_cair}\n\n";
    
    if (!empty($rekening_info)) {
        $template .= "💰 *DITRANSFER KE REKENING:*\n{$rekening_info}\n\n";
    }
    
    $template .= "✅ *STATUS:* Selesai\n\n"
               . "Terima kasih,\n"
               . "*System LeadEngine*";
    
    $placeholders = [
        '{admin_name}' => $admin_data['nama_lengkap'],
        '{customer_name}' => $komisi_data['customer_name'],
        '{developer_name}' => $komisi_data['developer_name'],
        '{unit_info}' => $komisi_data['unit_info'],
        '{harga_formatted}' => 'Rp ' . number_format($komisi_data['harga'], 0, ',', '.'),
        '{komisi_formatted}' => 'Rp ' . number_format($komisi_data['komisi_final'], 0, ',', '.'),
        '{tanggal_cair}' => date('d/m/Y H:i')
    ];
    
    $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
    
    $payload = [
        'api_key' => $api_key,
        'number_id' => $number_id,
        'enable_typing' => "1",
        'method_send' => "async",
        'phone_no' => $target_formatted,
        'country_code' => "62",
        'message' => $message
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code == 200);
}

function sendKomisiNotificationToFinance($komisi_data, $finance_data, $marketing_data, $link_konfirmasi = '') {
    $config = getMarketingConfig();
    
    $api_url = WHATSAPP_API_URL;
    $api_key = $config['notification_token'] ?? $config['access_token'];
    $number_id = NOTIFICATION_NUMBER_ID;
    
    $target_clean = preg_replace('/[^0-9]/', '', $finance_data['phone']);
    $target_formatted = (substr($target_clean, 0, 1) == '0') 
        ? '62' . substr($target_clean, 1) 
        : ((substr($target_clean, 0, 2) != '62') ? '62' . $target_clean : $target_clean);
    
    $template = "🔔 *KOMISI PERLU DITRANSFER!*\n\n"
              . "Halo Tim Finance,\n\n"
              . "Ada komisi yang perlu segera ditransfer:\n\n"
              . "📋 *DETAIL KOMISI:*\n"
              . "• Marketing: {marketing_name}\n"
              . "• Customer: {customer_name}\n"
              . "• Developer: {developer_name}\n"
              . "• Unit: {unit_info}\n"
              . "• Total Komisi: {komisi_formatted}\n"
              . "• Status: {status}\n\n";
    
    if (!empty($marketing_data['rekening_info'])) {
        $template .= "💳 *TUJUAN TRANSFER:*\n{$marketing_data['rekening_info']}\n\n";
    }
    
    if (!empty($link_konfirmasi)) {
        $template .= "🔄 *AKSI:*\n"
                   . "Klik link berikut untuk konfirmasi pembayaran:\n"
                   . "{$link_konfirmasi}\n\n";
    }
    
    $template .= "Segera proses ya!\n"
               . "*System LeadEngine*";
    
    $placeholders = [
        '{marketing_name}' => $marketing_data['nama_lengkap'],
        '{customer_name}' => $komisi_data['customer_name'],
        '{developer_name}' => $komisi_data['developer_name'],
        '{unit_info}' => $komisi_data['unit_info'],
        '{komisi_formatted}' => 'Rp ' . number_format($komisi_data['komisi_final'], 0, ',', '.'),
        '{status}' => $komisi_data['status'] ?? 'pending'
    ];
    
    $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
    
    $payload = [
        'api_key' => $api_key,
        'number_id' => $number_id,
        'enable_typing' => "1",
        'method_send' => "async",
        'phone_no' => $target_formatted,
        'country_code' => "62",
        'message' => $message
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code == 200);
}

// ============================================
// MARKETING KPI FUNCTIONS
// ============================================
function getMarketingKPI($conn, $marketing_id, $start_date = null, $end_date = null) {
    if (!$start_date) $start_date = date('Y-m-d', strtotime('-30 days'));
    if (!$end_date) $end_date = date('Y-m-d');
    
    $kpi = [
        'marketing_id' => $marketing_id,
        'periode' => "$start_date s/d $end_date",
        'total_leads_assigned' => 0,
        'total_leads_diterima' => 0,
        'total_follow_up' => 0,
        'total_status_update' => 0,
        'total_deal' => 0,
        'total_negatif' => 0,
        'conversion_rate' => 0,
        'avg_followups_per_lead' => 0,
        'last_activity' => null,
        'score_distribution' => ['hot' => 0, 'warm' => 0, 'cold' => 0, 'deal' => 0]
    ];
    
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM leads 
            WHERE assigned_marketing_team_id = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute([$marketing_id]);
        $kpi['total_leads_assigned'] = (int)$stmt->fetchColumn();
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM leads 
            WHERE assigned_marketing_team_id = ? AND DATE(created_at) BETWEEN ? AND ?
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute([$marketing_id, $start_date, $end_date]);
        $kpi['total_leads_diterima'] = (int)$stmt->fetchColumn();
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM marketing_activities 
            WHERE marketing_id = ? AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$marketing_id, $start_date, $end_date]);
        $kpi['total_follow_up'] = (int)$stmt->fetchColumn();
        
        $deal_statuses = ['Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'];
        $placeholders = implode(',', array_fill(0, count($deal_statuses), '?'));
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM leads 
            WHERE assigned_marketing_team_id = ? AND status IN ($placeholders)
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $params = array_merge([$marketing_id], $deal_statuses);
        $stmt->execute($params);
        $kpi['total_deal'] = (int)$stmt->fetchColumn();
        
        if ($kpi['total_leads_diterima'] > 0) {
            $kpi['conversion_rate'] = round(($kpi['total_deal'] / $kpi['total_leads_diterima']) * 100, 2);
        }
        
        $stmt = $conn->prepare("
            SELECT created_at FROM marketing_activities 
            WHERE marketing_id = ? ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$marketing_id]);
        $last = $stmt->fetch();
        $kpi['last_activity'] = $last ? $last['created_at'] : null;
        
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN lead_score >= 80 THEN 1 ELSE 0 END) as hot,
                SUM(CASE WHEN lead_score >= 60 AND lead_score < 80 THEN 1 ELSE 0 END) as warm,
                SUM(CASE WHEN lead_score < 60 THEN 1 ELSE 0 END) as cold,
                SUM(CASE WHEN status IN ('Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun') THEN 1 ELSE 0 END) as deal
            FROM leads 
            WHERE assigned_marketing_team_id = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute([$marketing_id]);
        $dist = $stmt->fetch();
        
        $kpi['score_distribution'] = [
            'hot' => (int)($dist['hot'] ?? 0),
            'warm' => (int)($dist['warm'] ?? 0),
            'cold' => (int)($dist['cold'] ?? 0),
            'deal' => (int)($dist['deal'] ?? 0)
        ];
        
    } catch (Exception $e) {
        error_log("Error in getMarketingKPI: " . $e->getMessage());
    }
    
    return $kpi;
}

function getAllMarketingKPI($conn, $developer_id, $start_date = null, $end_date = null) {
    $result = [
        'marketing' => [],
        'total' => ['total_leads' => 0, 'total_deal' => 0, 'total_follow_up' => 0, 'avg_conversion' => 0]
    ];
    
    try {
        $stmt = $conn->prepare("
            SELECT id, nama_lengkap, phone, username, is_active 
            FROM marketing_team WHERE developer_id = ? ORDER BY is_active DESC, nama_lengkap ASC
        ");
        $stmt->execute([$developer_id]);
        $marketing_list = $stmt->fetchAll();
        
        $total_leads = $total_deal = $total_follow_up = 0;
        
        foreach ($marketing_list as $m) {
            $kpi = getMarketingKPI($conn, $m['id'], $start_date, $end_date);
            $kpi['nama_lengkap'] = $m['nama_lengkap'];
            $kpi['phone'] = $m['phone'];
            $kpi['username'] = $m['username'];
            $kpi['is_active'] = $m['is_active'];
            
            $result['marketing'][] = $kpi;
            $total_leads += $kpi['total_leads_diterima'];
            $total_deal += $kpi['total_deal'];
            $total_follow_up += $kpi['total_follow_up'];
        }
        
        $result['total'] = [
            'total_leads' => $total_leads,
            'total_deal' => $total_deal,
            'total_follow_up' => $total_follow_up,
            'avg_conversion' => $total_leads > 0 ? round(($total_deal / $total_leads) * 100, 2) : 0
        ];
        
    } catch (Exception $e) {
        error_log("Error in getAllMarketingKPI: " . $e->getMessage());
    }
    
    return $result;
}

// ============================================
// QUEUE FUNCTIONS
// ============================================
function queueJob($job_data) {
    $conn = getDB();
    if (!$conn) return false;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO job_queue (type, payload, status, created_at) 
            VALUES (?, ?, 'pending', NOW())
        ");
        return $stmt->execute([$job_data['type'], json_encode($job_data['payload'])]);
    } catch (Exception $e) {
        error_log("Error queueJob: " . $e->getMessage());
        return false;
    }
}

// ============================================
// SYSTEM LOGS
// ============================================
function logSystemAction($action, $data, $ref_id = null, $ref_table = null) {
    $conn = getDB();
    if (!$conn) return false;
    
    $user_id = $_SESSION['user_id'] ?? $_SESSION['marketing_id'] ?? null;
    $role = $_SESSION['role'] ?? (isset($_SESSION['marketing_id']) ? 'marketing' : 'guest');
    $ip = getClientIP();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO system_logs (user_id, role, action_type, reference_id, reference_table, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$user_id, $role, $action, $ref_id, $ref_table, $ip, $ua]);
    } catch (Exception $e) {
        error_log("Error logSystemAction: " . $e->getMessage());
        return false;
    }
}

// ============================================
// RESPONSE FUNCTION
// ============================================
if (!function_exists('sendResponse')) {
    function sendResponse($success, $message, $data = null, $code = 200) {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $response = ['success' => $success, 'message' => $message, 'timestamp' => date('Y-m-d H:i:s')];
        if ($data !== null) $response['data'] = $data;
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// ============================================
// CREATE DIRECTORIES
// ============================================
$directories = [
    LOG_PATH, SESSION_PATH, UPLOAD_PATH, CANVASING_PATH,
    BUKTI_PATH, AKAD_PATH, PROFILE_PATH
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}
/**
 * Get developer SEO data
 */
function getDeveloperSEO($developer_id) {
    $conn = getDB();
    if (!$conn) return null;
    
    try {
        $stmt = $conn->prepare("SELECT * FROM developer_seo WHERE developer_id = ?");
        $stmt->execute([$developer_id]);
        $seo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$seo) {
            // Generate default SEO untuk developer baru
            return generateDefaultSEO($developer_id);
        }
        
        // Format image URLs
        if (!empty($seo['og_image'])) {
            // Cek apakah sudah full URL
            if (strpos($seo['og_image'], 'http') !== 0) {
                $seo['og_image'] = SITE_URL . '/' . ltrim($seo['og_image'], '/');
            }
        }
        
        if (!empty($seo['twitter_image'])) {
            if (strpos($seo['twitter_image'], 'http') !== 0) {
                $seo['twitter_image'] = SITE_URL . '/' . ltrim($seo['twitter_image'], '/');
            }
        }
        
        return $seo;
        
    } catch (Exception $e) {
        error_log("Error in getDeveloperSEO: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate default SEO untuk developer baru
 */
/**
 * Generate default SEO untuk developer baru
 * FIXED: Hapus kolom yang tidak ada di database
 */
function generateDefaultSEO($developer_id) {
    $conn = getDB();
    if (!$conn) return getDefaultPlatformSEO();
    
    try {
        // Ambil data developer
        $stmt = $conn->prepare("
            SELECT id, nama_lengkap, nama_perusahaan, kota, alamat_perusahaan, 
                   telepon_perusahaan, website_perusahaan, logo_perusahaan, email_perusahaan,
                   folder_name
            FROM users 
            WHERE id = ? AND role = 'developer' AND is_active = 1
        ");
        $stmt->execute([$developer_id]);
        $dev = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dev) {
            return getDefaultPlatformSEO();
        }
        
        $dev_name = $dev['nama_perusahaan'] ?: $dev['nama_lengkap'] ?: 'Developer';
        $dev_city = $dev['kota'] ?: 'Kuningan';
        $logo = $dev['logo_perusahaan'] ? SITE_URL . '/' . ltrim($dev['logo_perusahaan'], '/') : SITE_URL . '/assets/images/logo.png';
        
        // CEK KOLOM YANG ADA DI TABEL
        $columns = $conn->query("SHOW COLUMNS FROM developer_seo")->fetchAll(PDO::FETCH_COLUMN);
        
        $default = [
            'developer_id' => $developer_id,
            'seo_title' => "$dev_name - Developer Properti Terpercaya di $dev_city",
            'seo_description' => "Info lengkap properti dari $dev_name di $dev_city. Tersedia program subsidi dan komersil dengan bonus menarik. Proses cepat 7 hari!",
            'seo_keywords' => "$dev_name, properti $dev_city, rumah subsidi $dev_city, rumah komersil, developer properti kuningan",
            'canonical_url' => !empty($dev['folder_name']) ? SITE_URL . '/' . $dev['folder_name'] . '/' : SITE_URL . '/?dev_id=' . $dev['id'],
            'h1_tag' => "$dev_name - Hunian Nyaman di $dev_city",
            
            'meta_robots_index' => 1,
            'meta_robots_follow' => 1,
            'is_active' => 1,
            'is_default' => 0,
            'seo_priority' => 0,
            
            'og_type' => 'website',
            'og_title' => "$dev_name - Developer Properti di $dev_city",
            'og_description' => "Info lengkap properti dari $dev_name di $dev_city. Tersedia program subsidi tanpa DP dan komersil fleksibel.",
            'og_image' => ltrim(str_replace(SITE_URL, '', $logo), '/'),
            'og_image_width' => 1200,
            'og_image_height' => 630,
            'og_url' => !empty($dev['folder_name']) ? SITE_URL . '/' . $dev['folder_name'] . '/' : SITE_URL . '/?dev_id=' . $dev['id'],
            
            'twitter_title' => "$dev_name - Developer Properti $dev_city",
            'twitter_description' => "Info lengkap properti dari $dev_name. Subsidi tanpa DP, proses cepat 7 hari!",
            'twitter_image' => ltrim(str_replace(SITE_URL, '', $logo), '/'),
            'twitter_image_alt' => "Logo $dev_name",
            'twitter_card_type' => 'summary_large_image',
            
            'schema_json' => null,
            'faq_json' => null,
            'breadcrumb_json' => null
        ];
        
        // Generate schema JSON
        $default['schema_json'] = generateDeveloperSchema($dev, $default);
        
        // ===== BANGUN QUERY INSERT DINAMIS =====
        $insert_fields = ['developer_id'];
        $insert_values = [$developer_id];
        
        $field_map = [
            'seo_title' => $default['seo_title'],
            'seo_description' => $default['seo_description'],
            'seo_keywords' => $default['seo_keywords'],
            'canonical_url' => $default['canonical_url'],
            'h1_tag' => $default['h1_tag'],
            'meta_robots_index' => $default['meta_robots_index'],
            'meta_robots_follow' => $default['meta_robots_follow'],
            'is_active' => $default['is_active'],
            'is_default' => $default['is_default'],
            'seo_priority' => $default['seo_priority'],
            'og_type' => $default['og_type'],
            'og_title' => $default['og_title'],
            'og_description' => $default['og_description'],
            'og_image' => $default['og_image'],
            'og_image_width' => $default['og_image_width'],
            'og_image_height' => $default['og_image_height'],
            'og_url' => $default['og_url'],
            'twitter_title' => $default['twitter_title'],
            'twitter_description' => $default['twitter_description'],
            'twitter_image' => $default['twitter_image'],
            'twitter_image_alt' => $default['twitter_image_alt'],
            'twitter_card_type' => $default['twitter_card_type'],
            'schema_json' => $default['schema_json'],
            'faq_json' => $default['faq_json'],
            'breadcrumb_json' => $default['breadcrumb_json']
        ];
        
        foreach ($field_map as $field => $value) {
            if (in_array($field, $columns)) {
                $insert_fields[] = $field;
                $insert_values[] = $value;
            }
        }
        
        $insert_fields[] = 'created_at';
        $insert_fields[] = 'updated_at';
        $insert_values[] = date('Y-m-d H:i:s');
        $insert_values[] = date('Y-m-d H:i:s');
        
        $placeholders = implode(', ', array_fill(0, count($insert_fields), '?'));
        $insert_sql = "INSERT INTO developer_seo (" . implode(', ', $insert_fields) . ") VALUES ($placeholders)";
        
        $insert = $conn->prepare($insert_sql);
        $insert->execute($insert_values);
        
        return $default;
        
    } catch (Exception $e) {
        error_log("Error in generateDefaultSEO: " . $e->getMessage());
        return getDefaultPlatformSEO();
    }
}

/**
 * Generate developer schema.org JSON-LD
 */
function generateDeveloperSchema($dev, $seo = []) {
    $dev_name = $dev['nama_perusahaan'] ?: $dev['nama_lengkap'];
    $address = !empty($dev['alamat_perusahaan']) ? $dev['alamat_perusahaan'] : ($dev['alamat'] ?? '');
    $city = $dev['kota'] ?? 'Kuningan';
    $phone = $dev['telepon_perusahaan'] ?? $dev['contact_phone'] ?? (defined('MARKETING_PHONE') ? MARKETING_PHONE : '628133150078');
    $email = $dev['email_perusahaan'] ?? $dev['email'] ?? (defined('MARKETING_EMAIL') ? MARKETING_EMAIL : 'info@leadengine.com');
    $website = $dev['website_perusahaan'] ?? SITE_URL . '/developer/' . $dev['id'];
    $logo = !empty($dev['logo_perusahaan']) ? SITE_URL . '/' . ltrim($dev['logo_perusahaan'], '/') : SITE_URL . '/assets/images/logo.png';
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'RealEstateAgent',
        'name' => $dev_name,
        'description' => $seo['seo_description'] ?? "Developer properti terpercaya di $city",
        'url' => $website,
        'logo' => $logo,
        'image' => $logo,
        'telephone' => $phone,
        'email' => $email,
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $address,
            'addressLocality' => $city,
            'addressRegion' => 'Jawa Barat',
            'addressCountry' => 'ID'
        ],
        'priceRange' => 'Rp 150.000.000 - Rp 1.000.000.000',
        'areaServed' => [
            '@type' => 'City',
            'name' => $city
        ]
    ];
    
    return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Get default platform SEO (fallback)
 */
function getDefaultPlatformSEO() {
    return [
        'seo_title' => 'Lead Engine Property - Platform Rumah Subsidi & Komersil Multi Developer No.1 di Kuningan',
        'seo_description' => '✨ Wujudkan rumah impian Anda! Platform properti terintegrasi dengan berbagai developer terpercaya. Tersedia program SUBSIDI tanpa DP dan KOMERSIL fleksibel. Proses cepat 7 hari, bonus menarik!',
        'seo_keywords' => 'rumah subsidi kuningan, rumah tanpa dp, rumah komersil, kpr subsidi, perumahan subsidi, multi developer properti, rumah murah kuningan',
        'robots_meta' => 'index, follow',
        'canonical_url' => defined('SITE_URL') ? SITE_URL : 'https://leadproperti.com',
        'og_type' => 'website',
        'og_site_name' => 'Lead Engine Property',
        'og_title' => 'Lead Engine Property - Platform Rumah Subsidi & Komersil Multi Developer',
        'og_description' => '✨ Wujudkan rumah impian Anda dengan program subsidi tanpa DP atau komersil fleksibel. Bekerja sama dengan developer terpercaya di berbagai lokasi. Proses cepat, bonus menarik!',
        'og_image' => (defined('SITE_URL') ? SITE_URL : 'https://leadproperti.com') . '/assets/images/og-image.jpg',
        'og_image_width' => 1200,
        'og_image_height' => 630,
        'og_image_alt' => 'Lead Engine Property - Platform Properti Multi Developer',
        'og_url' => defined('SITE_URL') ? SITE_URL : 'https://leadproperti.com',
        'twitter_title' => 'Lead Engine Property - Platform Rumah Subsidi & Komersil',
        'twitter_description' => '✨ Wujudkan rumah impian Anda dengan program subsidi tanpa DP atau komersil fleksibel. Proses cepat 7 hari!',
        'twitter_image' => (defined('SITE_URL') ? SITE_URL : 'https://leadproperti.com') . '/assets/images/og-image.jpg',
        'twitter_image_alt' => 'Lead Engine Property Platform',
        'twitter_card_type' => 'summary_large_image',
        'schema_json' => null,
        'faq_json' => null,
        'breadcrumb_json' => null
    ];
}

/**
 * Get SEO for current page
 */
function getCurrentPageSEO() {
    $dev_id = isset($_GET['dev_id']) ? (int)$_GET['dev_id'] : (isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0);
    
    if ($dev_id > 0) {
        $seo = getDeveloperSEO($dev_id);
        if ($seo) {
            // Pastikan canonical URL menggunakan parameter dev_id
            if (empty($seo['canonical_url'])) {
                $seo['canonical_url'] = SITE_URL . '/?dev_id=' . $dev_id;
            }
            return $seo;
        }
    }
    
    return getDefaultPlatformSEO();
}

/**
 * Validate SEO data
 */
function validateSEOData($data) {
    $errors = [];
    
    // Validate title length
    if (empty($data['seo_title'])) {
        $errors['seo_title'] = 'SEO Title wajib diisi';
    } elseif (strlen($data['seo_title']) > 60) {
        $errors['seo_title'] = 'SEO Title maksimal 60 karakter';
    }
    
    // Validate description length
    if (empty($data['seo_description'])) {
        $errors['seo_description'] = 'Meta Description wajib diisi';
    } elseif (strlen($data['seo_description']) > 160) {
        $errors['seo_description'] = 'Meta Description maksimal 160 karakter';
    }
    
    // Validate canonical URL
    if (!empty($data['canonical_url']) && !filter_var($data['canonical_url'], FILTER_VALIDATE_URL)) {
        $errors['canonical_url'] = 'Canonical URL tidak valid';
    }
    
    // Validate OG image
    if (!empty($data['og_image']) && !preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $data['og_image'])) {
        $errors['og_image'] = 'OG Image harus berupa file gambar';
    }
    
    return $errors;
}

// ===== PASTIKAN FUNGSI INI ADA =====
if (!function_exists('logSystem')) {
    function logSystem($message, $data = null, $level = 'INFO', $file = 'system.log') {
        $log_dir = defined('LOG_PATH') ? LOG_PATH : __DIR__ . '/../logs/';
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        
        $log_file = $log_dir . $file;
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message\n";
        if ($data) {
            if (is_array($data)) {
                $log_entry .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                $log_entry .= $data . "\n";
            }
        }
        $log_entry .= str_repeat("-", 80) . "\n";
        
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// ============================================
// FUNGSI KOMISI SPLIT (Developer Bayar ke Platform)
// ============================================

/**
 * Hitung komisi split yang harus dibayar developer ke platform
 */
function hitungKomisiSplit($unit_data, $developer_id = null) {
    $conn = getDB();
    
    // Prioritas 1: Nilai dari unit (override)
    if (!empty($unit_data['komisi_split_rupiah']) && $unit_data['komisi_split_rupiah'] > 0) {
        return [
            'type' => 'FIXED',
            'value' => $unit_data['komisi_split_rupiah'],
            'source' => 'unit_override'
        ];
    }
    
    if (!empty($unit_data['komisi_split_persen']) && $unit_data['komisi_split_persen'] > 0) {
        $nominal = $unit_data['harga'] * ($unit_data['komisi_split_persen'] / 100);
        return [
            'type' => 'PERCENT',
            'percent' => $unit_data['komisi_split_persen'],
            'value' => $nominal,
            'source' => 'unit_override'
        ];
    }
    
    // Prioritas 2: Aturan platform (default)
    $aturan = getPlatformKomisiSplit();
    if ($aturan['type'] == 'FIXED') {
        return [
            'type' => 'FIXED',
            'value' => $aturan['value'],
            'source' => 'platform_default'
        ];
    } else {
        $nominal = $unit_data['harga'] * ($aturan['value'] / 100);
        return [
            'type' => 'PERCENT',
            'percent' => $aturan['value'],
            'value' => $nominal,
            'source' => 'platform_default'
        ];
    }
}

/**
 * Ambil aturan komisi split dari platform
 */
function getPlatformKomisiSplit() {
    $conn = getDB();
    if (!$conn) {
        return ['type' => 'PERCENT', 'value' => 2.50];
    }
    
    try {
        $stmt = $conn->query("SELECT * FROM platform_komisi_split WHERE id = 1");
        $aturan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($aturan) {
            return [
                'type' => $aturan['commission_type'],
                'value' => $aturan['commission_value']
            ];
        }
    } catch (Exception $e) {
        error_log("Error getting platform komisi split: " . $e->getMessage());
    }
    
    return ['type' => 'PERCENT', 'value' => 2.50];
}

/**
 * Catat hutang komisi split saat lead external deal
 */
function catatHutangKomisiSplit($conn, $lead_id, $unit_id, $developer_id) {
    try {
        // Cek apakah lead ini masuk ke external (akibat split)
        $stmt = $conn->prepare("
            SELECT l.*, u.harga, u.komisi_split_persen, u.komisi_split_rupiah
            FROM leads l
            JOIN units u ON l.unit_id = u.id
            WHERE l.id = ? AND l.assigned_type = 'external'
        ");
        $stmt->execute([$lead_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return ['success' => false, 'message' => 'Bukan lead external atau unit tidak ditemukan'];
        }
        
        // Hitung komisi split
        $komisi_split = hitungKomisiSplit($data, $developer_id);
        
        // Cek apakah sudah pernah dicatat
        $stmt = $conn->prepare("SELECT id FROM komisi_split_hutang WHERE lead_id = ?");
        $stmt->execute([$lead_id]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Hutang sudah pernah dicatat'];
        }
        
        // Catat hutang
        $stmt = $conn->prepare("
            INSERT INTO komisi_split_hutang (
                developer_id, lead_id, unit_id, nominal, 
                persentase, type, status, jatuh_tempo, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
        ");
        
        $result = $stmt->execute([
            $developer_id,
            $lead_id,
            $unit_id,
            $komisi_split['value'],
            $komisi_split['percent'] ?? null,
            $komisi_split['type']
        ]);
        
        if ($result) {
            $hutang_id = $conn->lastInsertId();
            
            // Log notifikasi
            logSystem("Hutang komisi split dicatat", [
                'hutang_id' => $hutang_id,
                'developer_id' => $developer_id,
                'lead_id' => $lead_id,
                'nominal' => $komisi_split['value']
            ], 'INFO', 'komisi_split.log');
            
            return [
                'success' => true,
                'hutang_id' => $hutang_id,
                'nominal' => $komisi_split['value']
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error catatHutangKomisiSplit: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}


// ===== PASTIKAN SITE_URL DEFINED =====
if (!defined('SITE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('SITE_URL', $protocol . '://' . $host);
}

// ===== PASTIKAN API_KEY DEFINED =====
if (!defined('API_KEY')) {
    define('API_KEY', 'taufikmarie7878');
}
?>