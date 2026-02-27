<?php
/**
 * API_MASTER.PHP - LEADENGINE SUPER DEWA
 * Version: 53.1.0 - UPDATED with External Marketing Access Check + Security
 * FULL CODE - 100% LENGKAP DENGAN SEMUA FUNGSI ASLI
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_master_error.log');

while (ob_get_level()) {
    ob_end_clean();
}

ob_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    echo json_encode(['success' => true]);
    exit();
}

require_once __DIR__ . '/config.php';

$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}

$log_file = $log_dir . '/api_master.log';
$error_file = $log_dir . '/api_master_error.log';

function superDebug($message, $data = null) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data) {
        if (is_array($data) || is_object($data)) {
            $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $log .= $data . "\n";
        }
    }
    $log .= str_repeat("-", 80) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

superDebug("========== API MASTER SUPER DEWA DIPANGGIL ==========");

// ========== TAMBAHAN KEAMANAN ==========
// Rate limiting
if (!checkRateLimit('api_master_' . getClientIP(), 5, 60)) {
    superDebug("Rate limit exceeded");
    sendResponse(false, 'Terlalu banyak permintaan. Silakan coba lagi nanti.', null, 429);
}

// CSRF untuk method POST - DINONAKTIFKAN SEMENTARA
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
//     superDebug("Invalid CSRF token");
//     sendResponse(false, 'Invalid CSRF token', null, 400);
// }
// ========== END TAMBAHAN KEAMANAN ==========

try {
    $data = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST)) {
            $data = $_POST;
            superDebug("Data dari POST", $data);
        } else {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $data = json_decode($input, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $data = [];
                    superDebug("JSON decode error: " . json_last_error_msg());
                } else {
                    superDebug("Data dari JSON input", $data);
                }
            }
        }
    } else {
        $data = $_GET;
        superDebug("Data dari GET", $data);
    }
    
    if (empty($data)) {
        superDebug("ERROR: No data received");
        sendResponse(false, 'Tidak ada data yang diterima', null, 400);
    }
    
    $first_name = trim($data['first_name'] ?? $data['nama_depan'] ?? $data['name'] ?? '');
    $last_name  = trim($data['last_name'] ?? $data['nama_belakang'] ?? '');
    $phone      = trim($data['phone'] ?? $data['nomor_whatsapp'] ?? $data['no_wa'] ?? $data['whatsapp'] ?? '');
    $email      = trim($data['email'] ?? $data['mail'] ?? '');
    $location   = trim($data['location'] ?? $data['lokasi'] ?? '');
    $unit_type  = trim($data['unit_type'] ?? $data['tipe_unit'] ?? 'Scandinavia 30/60');
    $program    = trim($data['program'] ?? $data['program'] ?? 'Subsidi');
    
    // ✅ AMBIL DEVELOPER_ID DARI FORM (PENTING!)
    $developer_id = isset($data['developer_id']) ? (int)$data['developer_id'] : 0;
    
    superDebug("Data setelah mapping", [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'phone'      => $phone,
        'email'      => $email,
        'location'   => $location,
        'unit_type'  => $unit_type,
        'program'    => $program,
        'developer_id' => $developer_id
    ]);
    
    $errors = [];
    if (empty($first_name)) $errors[] = 'Nama depan wajib diisi';
    if (empty($phone))      $errors[] = 'Nomor WhatsApp wajib diisi';
    if (empty($location) && $developer_id == 0) $errors[] = 'Pilih lokasi';
    
    if (!empty($errors)) {
        superDebug("Validasi gagal", $errors);
        sendResponse(false, $errors[0], null, 400);
    }
    
    $conn = getDB();
    if (!$conn) {
        superDebug("ERROR: Database connection failed");
        sendResponse(false, 'Koneksi database gagal', null, 500);
    }
    
    superDebug("Database connected successfully");
    
    $phone_validation = validatePhone($phone);
    if (!$phone_validation['valid']) {
        superDebug("Validasi phone gagal", $phone);
        sendResponse(false, $phone_validation['message'], null, 400);
    }
    
    $phone_clean = $phone_validation['number'];
    superDebug("Phone valid", $phone_clean);
    
    $duplicate_check = checkDuplicateLead($conn, $phone_clean, $email);
    $is_duplicate = $duplicate_check['is_duplicate'];
    
    superDebug("Duplicate check", [
        'is_duplicate' => $is_duplicate,
        'data' => $duplicate_check
    ]);
    
    $location_data = getLocationDetails($location);
    superDebug("Location details", $location_data);
    
    // ✅ JIKA DEVELOPER_ID TIDAK DIKIRIM, CARI DARI LOKASI
    if ($developer_id == 0) {
        $stmt = $conn->prepare("
            SELECT id, username, nama_lengkap, location_access, distribution_mode 
            FROM users 
            WHERE role = 'developer' AND is_active = 1 
            AND (location_access LIKE ? OR location_access = ?)
            LIMIT 1
        ");
        $search_term = '%' . $location . '%';
        $stmt->execute([$search_term, $location]);
        $developer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$developer) {
            $stmt = $conn->prepare("
                SELECT id, username, nama_lengkap, location_access, distribution_mode 
                FROM users 
                WHERE role = 'developer' AND is_active = 1 
                AND location_access = ?
                LIMIT 1
            ");
            $stmt->execute([$location]);
            $developer = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$developer) {
            superDebug("ERROR: Developer tidak ditemukan untuk lokasi: $location");
            sendResponse(false, 'Developer tidak ditemukan untuk lokasi ini', null, 400);
        }
        
        $developer_id = $developer['id'];
        $developer_mode = $developer['distribution_mode'] ?? 'FULL_EXTERNAL';
        $developer_name = $developer['nama_lengkap'];
        
        superDebug("Developer ditemukan dari lokasi", [
            'id' => $developer_id,
            'name' => $developer_name,
            'mode' => $developer_mode
        ]);
    } else {
        // ✅ DEVELOPER_ID SUDAH ADA, AMBIL DATA DARI DATABASE
        $stmt = $conn->prepare("SELECT id, nama_lengkap, distribution_mode FROM users WHERE id = ? AND role = 'developer'");
        $stmt->execute([$developer_id]);
        $developer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$developer) {
            superDebug("ERROR: Developer ID $developer_id tidak ditemukan");
            sendResponse(false, 'Developer tidak valid', null, 400);
        }
        
        $developer_mode = $developer['distribution_mode'] ?? 'FULL_EXTERNAL';
        $developer_name = $developer['nama_lengkap'];
        
        superDebug("Developer ID dari form", [
            'id' => $developer_id,
            'name' => $developer_name,
            'mode' => $developer_mode
        ]);
    }
    
    $assignment = assignLeadToMarketing($conn, $developer_id, [
        'phone'    => $phone_clean,
        'location' => $location,
        'email'    => $email
    ]);
    
    superDebug("Assignment result", $assignment);
    
    // ===== CEK AKSES EXTERNAL MARKETING KE DEVELOPER =====
    if ($assignment['assigned_type'] === 'external' && isset($assignment['marketing_data']['marketing_external_id'])) {
        $can_access = canExternalAccessDeveloper(
            $conn, 
            $assignment['marketing_data']['marketing_external_id'], 
            $developer_id
        );
        
        if (!$can_access) {
            superDebug("⚠️ External marketing ID {$assignment['marketing_data']['id']} TIDAK punya akses ke developer $developer_id");
            superDebug("Fallback ke Super Admin");
            
            // Assign ulang ke Super Admin
            $super_admin = getSuperAdminData($conn);
            $assignment = [
                'assigned_type' => 'external',
                'assigned_marketing_team_id' => $super_admin['id'],
                'assigned_marketing_name' => $super_admin['nama_lengkap'],
                'assigned_marketing_phone' => $super_admin['phone'],
                'assigned_marketing_id' => $super_admin['id'],
                'marketing_data' => $super_admin
            ];
            
            superDebug("Assignment ulang ke Super Admin", $assignment);
        } else {
            superDebug("✅ External marketing punya akses ke developer $developer_id");
        }
    }
    
    $unique_suffix = bin2hex(random_bytes(8));
    $meta_event_id   = 'META_' . time() . '_' . $unique_suffix;
    $tiktok_event_id = 'TT_' . time() . '_' . $unique_suffix;
    $google_event_id = 'GA_' . time() . '_' . $unique_suffix;
    
    $client_ip   = getClientIP();
    $user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $fbp         = $data['fbp'] ?? $data['_fbp'] ?? '';
    $fbc         = $data['fbc'] ?? $data['fbclid'] ?? '';
    $ttclid      = $data['ttclid'] ?? $data['ttclid'] ?? '';
    $gclid       = $data['gclid'] ?? $data['gclid'] ?? '';
    $page_url    = $data['page_url'] ?? $data['url'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $referrer    = $data['referrer'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $source      = $data['source'] ?? $data['utm_source'] ?? 'website';
    $utm_source  = $data['utm_source'] ?? $data['source'] ?? '';
    $utm_medium  = $data['utm_medium'] ?? '';
    $utm_campaign= $data['utm_campaign'] ?? '';
    $utm_content = $data['utm_content'] ?? '';
    $utm_term    = $data['utm_term'] ?? '';
    
    superDebug("Tracking data", [
        'meta_event_id'   => $meta_event_id,
        'tiktok_event_id' => $tiktok_event_id,
        'client_ip'       => $client_ip,
        'fbp'             => $fbp,
        'fbc'             => $fbc
    ]);
    
    $lead_data_for_score = [
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'phone'        => $phone_clean,
        'email'        => $email,
        'location_key' => $location,
        'source'       => $source
    ];
    
    $lead_score = calculateLeadScorePremium('Baru', $lead_data_for_score);
    
    $full_name = trim($first_name . ' ' . $last_name);
    if (empty($full_name)) $full_name = $first_name;
    
    superDebug("Lead score premium: $lead_score, Full name: $full_name");
    
    $sql = "INSERT INTO leads (
        first_name, last_name, phone, email, location_key, lead_score,
        unit_type, program,
        source, utm_source, utm_medium, utm_campaign, utm_content, utm_term,
        meta_event_id, tiktok_event_id, fbp, fbc, ttclid, gclid,
        client_ip, user_agent, page_url, referrer, ditugaskan_ke,
        assigned_marketing_team_id, assigned_marketing_name, assigned_marketing_phone, assigned_type,
        is_duplicate_warning, created_at, updated_at
    ) VALUES (
        :first_name, :last_name, :phone, :email, :location_key, :lead_score,
        :unit_type, :program,
        :source, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term,
        :meta_event_id, :tiktok_event_id, :fbp, :fbc, :ttclid, :gclid,
        :client_ip, :user_agent, :page_url, :referrer, :ditugaskan_ke,
        :assigned_marketing_team_id, :assigned_marketing_name, :assigned_marketing_phone, :assigned_type,
        :is_duplicate_warning, NOW(), NOW()
    )";
    
    $is_duplicate_warning = $is_duplicate ? 1 : 0;
    
    superDebug("SQL Query", $sql);
    
    $params = [
        ':first_name'                  => $first_name,
        ':last_name'                   => $last_name,
        ':phone'                        => $phone_clean,
        ':email'                        => $email,
        ':location_key'                 => $location,
        ':lead_score'                   => $lead_score,
        ':unit_type'                    => $unit_type,
        ':program'                      => $program,
        ':source'                       => $source,
        ':utm_source'                   => $utm_source,
        ':utm_medium'                   => $utm_medium,
        ':utm_campaign'                  => $utm_campaign,
        ':utm_content'                   => $utm_content,
        ':utm_term'                      => $utm_term,
        ':meta_event_id'                 => $meta_event_id,
        ':tiktok_event_id'               => $tiktok_event_id,
        ':fbp'                           => $fbp,
        ':fbc'                           => $fbc,
        ':ttclid'                        => $ttclid,
        ':gclid'                         => $gclid,
        ':client_ip'                     => $client_ip,
        ':user_agent'                    => $user_agent,
        ':page_url'                      => $page_url,
        ':referrer'                      => $referrer,
        ':ditugaskan_ke'                 => $developer_id,
        ':assigned_marketing_team_id'    => $assignment['assigned_marketing_team_id'],
        ':assigned_marketing_name'       => $assignment['assigned_marketing_name'],
        ':assigned_marketing_phone'      => $assignment['assigned_marketing_phone'],
        ':assigned_type'                 => $assignment['assigned_type'],
        ':is_duplicate_warning'          => $is_duplicate_warning
    ];
    
    superDebug("SQL Params", $params);
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute($params);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        superDebug("SQL Execute Error", $errorInfo);
        sendResponse(false, 'Gagal menyimpan data: ' . ($errorInfo[2] ?? 'Unknown error'), null, 500);
    }
    
    $lead_id = $conn->lastInsertId();
    
    superDebug("✅ Lead inserted with ID: $lead_id");
    
    $notif_title = $is_duplicate ? '⚠️ Lead Duplikat' : '🎯 Lead Baru';
    $notif_type = $is_duplicate ? 'warning' : 'success';
    $notif_message = "$full_name - {$location_data['display_name']} -> {$assignment['assigned_marketing_name']} (Score: $lead_score)";
    
    if ($is_duplicate) {
        $notif_message .= " (DUPLIKAT)";
    }
    
    try {
        $notif = $conn->prepare("INSERT INTO notifications (lead_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
        $notif->execute([$lead_id, $notif_title, $notif_message, $notif_type]);
        superDebug("Notification created in database");
    } catch (Exception $e) {
        superDebug("Notification error: " . $e->getMessage());
    }
    
    $push_data = [
        'title'         => $notif_title,
        'body'          => $notif_message,
        'url'           => '/admin/splash/',
        'count'         => 1,
        'icon'          => '/assets/images/icon-192.png',
        'badge'         => '/assets/images/icon-72.png',
        'sound'         => '/assets/sounds/notification.mp3',
        'lead_id'       => $lead_id,
        'developer_id'  => $developer_id,
        'marketing_id'  => $assignment['assigned_marketing_team_id'] ?: 0,
        'assigned_type' => $assignment['assigned_type'],
        'timestamp'     => time()
    ];
    
    $push_data['role'] = 'admin';
    $push_data['user_id'] = 0;
    sendPushNotification($push_data);
    
    $push_data['role'] = 'manager';
    sendPushNotification($push_data);
    
    if ($developer_id > 0) {
        $push_data['role'] = 'developer';
        $push_data['user_id'] = $developer_id;
        $push_data['developer_id'] = $developer_id;
        sendPushNotification($push_data);
    }
    
    $stmt = $conn->prepare("
        SELECT id FROM users 
        WHERE developer_id = ? AND role = 'manager_developer' AND is_active = 1
    ");
    $stmt->execute([$developer_id]);
    $manager_developers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($manager_developers as $md) {
        $push_data['role'] = 'manager_developer';
        $push_data['user_id'] = $md['id'];
        $push_data['developer_id'] = $developer_id;
        sendPushNotification($push_data);
    }
    
    $stmt = $conn->prepare("
        SELECT id FROM users 
        WHERE developer_id = ? AND role = 'finance' AND is_active = 1
    ");
    $stmt->execute([$developer_id]);
    $finances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($finances as $f) {
        $push_data['role'] = 'finance';
        $push_data['user_id'] = $f['id'];
        $push_data['developer_id'] = $developer_id;
        sendPushNotification($push_data);
    }
    
    if (!empty($assignment['assigned_marketing_team_id'])) {
        $push_data['role'] = 'marketing';
        $push_data['user_id'] = $assignment['assigned_marketing_team_id'];
        $push_data['marketing_id'] = $assignment['assigned_marketing_team_id'];
        sendPushNotification($push_data);
    }
    
    $tracking_data = [
        'customer_id'     => $lead_id,
        'first_name'      => $first_name,
        'last_name'       => $last_name,
        'full_name'       => $full_name,
        'email'           => $email,
        'phone'           => $phone_clean,
        'location'        => $location_data['display_name'] ?? $location,
        'unit_type'       => $unit_type,
        'program'         => $program,
        'meta_event_id'   => $meta_event_id,
        'tiktok_event_id' => $tiktok_event_id,
        'google_event_id' => $google_event_id,
        'fbp'             => $fbp,
        'fbc'             => $fbc,
        'ttclid'          => $ttclid,
        'gclid'           => $gclid,
        'client_ip'       => $client_ip,
        'user_agent'      => $user_agent,
        'page_url'        => $page_url,
        'event_name'      => 'Lead'
    ];
    
    // ✅ KIRIM DEVELOPER_ID UNTUK TRACKING PER DEVELOPER
    if (function_exists('sendAllTracking')) {
        try {
            sendAllTracking($tracking_data, $developer_id);
            superDebug("Tracking sent to all platforms for developer ID: $developer_id");
        } catch (Exception $e) {
            superDebug("Tracking error: " . $e->getMessage());
        }
    }
    
    if (function_exists('triggerWebhookAsync')) {
        try {
            triggerWebhookAsync(
                $lead_id,
                $full_name,
                $first_name,
                $phone_clean,
                $location,
                $location_data,
                $assignment,
                $is_duplicate,
                $developer_mode
            );
            superDebug("Webhook triggered with mode: $developer_mode");
        } catch (Exception $e) {
            superDebug("Webhook error: " . $e->getMessage());
        }
    } else {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $webhook_url = $protocol . $_SERVER['HTTP_HOST'] . '/admin/api/webhook.php';
        
        $webhook_data = [
            'action' => 'send_marketing_whatsapp',
            'customer' => [
                'id'               => $lead_id,
                'full_name'        => $full_name,
                'first_name'       => $first_name,
                'phone'            => $phone_clean,
                'location_key'     => $location,
                'location_display' => $location_data['display_name'] ?? $location,
                'location_icon'    => $location_data['icon'] ?? '🏠',
                'unit_type'        => $unit_type,
                'program'          => $program
            ],
            'assignment' => [
                'assigned_type'              => $assignment['assigned_type'],
                'assigned_marketing_name'    => $assignment['assigned_marketing_name'],
                'assigned_marketing_phone'   => $assignment['assigned_marketing_phone'],
                'assigned_marketing_team_id' => $assignment['assigned_marketing_team_id'],
                'assigned_marketing_id'      => $assignment['assigned_marketing_id'] ?? $assignment['assigned_marketing_team_id'],
                'developer_mode'              => $developer_mode
            ],
            'marketing_internal' => $assignment['marketing_data'],
            'customer_id'        => $lead_id,
            'is_duplicate'       => $is_duplicate,
            'timestamp'          => date('Y-m-d H:i:s')
        ];
        
        $ch = curl_init($webhook_url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($webhook_data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        curl_exec($ch);
        curl_close($ch);
        
        superDebug("Webhook fallback triggered");
    }
    
    $tracking_configs = getTrackingConfig(null, $developer_id);
    
    $tracking_script = [
        'meta'   => [
            'event_id'  => $meta_event_id,
            'pixel_id'  => $tracking_configs['meta']['pixel_id'] ?? '2224730075026860'
        ],
        'tiktok' => [
            'event_id'  => $tiktok_event_id,
            'pixel_id'  => $tracking_configs['tiktok']['pixel_id'] ?? 'D3L405BC77U8AFC9O0RG'
        ],
        'ga'     => [
            'client_id'      => $fbp ?: 'lead_' . $lead_id,
            'measurement_id' => $tracking_configs['google']['measurement_id'] ?? 'G-B9YZXZQ8L8'
        ]
    ];
    
    $response_data = [
        'id'                         => (string)$lead_id,
        'is_duplicate'               => $is_duplicate,
        'assigned_type'              => $assignment['assigned_type'],
        'assigned_marketing_name'    => $assignment['assigned_marketing_name'],
        'assigned_marketing_phone'   => $assignment['assigned_marketing_phone'],
        'assigned_marketing_id'      => $assignment['assigned_marketing_id'] ?? $assignment['assigned_marketing_team_id'],
        'lead_score'                 => $lead_score,
        'unit_type'                  => $unit_type,
        'program'                    => $program,
        'customer'                   => [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'full_name'  => $full_name,
            'phone'      => $phone_clean
        ],
        'services'                   => [
            'whatsapp' => true,
            'email'    => !empty($email)
        ],
        'tracking'                   => $tracking_script,
        'developer'                  => [
            'id'   => $developer_id,
            'name' => $developer_name ?? 'Developer',
            'mode' => $developer_mode
        ]
    ];
    
    superDebug("✅ Response sukses", $response_data);
    
    ob_end_clean();
    
    sendResponse(true, 'Pendaftaran berhasil!', $response_data, 200);
    
} catch (Exception $e) {
    $error_msg   = $e->getMessage();
    $error_file  = $e->getFile();
    $error_line  = $e->getLine();
    $error_trace = $e->getTraceAsString();
    
    superDebug("❌ EXCEPTION CAUGHT", [
        'message' => $error_msg,
        'file'    => $error_file,
        'line'    => $error_line,
        'trace'   => $error_trace
    ]);
    
    @file_put_contents($error_file, date('Y-m-d H:i:s') . " - FATAL ERROR\n", FILE_APPEND);
    @file_put_contents($error_file, "Message: $error_msg\n", FILE_APPEND);
    @file_put_contents($error_file, "File: $error_file Line: $error_line\n", FILE_APPEND);
    @file_put_contents($error_file, "Trace: $error_trace\n", FILE_APPEND);
    @file_put_contents($error_file, str_repeat("=", 80) . "\n\n", FILE_APPEND);
    
    ob_end_clean();
    
    sendResponse(false, 'System error occurred. Tim telah diberitahu.', null, 500);
}

function sendResponse($success, $message, $data = null, $code = 200) {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    
    $response = [
        'success'   => $success,
        'message'   => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

function sendPushNotification($data) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $webhook_url = $protocol . $_SERVER['HTTP_HOST'] . '/admin/api/send_notification.php';
    
    $ch = curl_init($webhook_url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT        => 2,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    curl_exec($ch);
    curl_close($ch);
}

if (!function_exists('triggerWebhookAsync')) {
    function triggerWebhookAsync($lead_id, $full_name, $first_name, $phone_clean, $location, $location_data, $assignment, $is_duplicate, $developer_mode) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $webhook_url = $protocol . $_SERVER['HTTP_HOST'] . '/admin/api/webhook.php';
        
        $webhook_data = [
            'action' => 'send_marketing_whatsapp',
            'customer' => [
                'id'               => $lead_id,
                'full_name'        => $full_name,
                'first_name'       => $first_name,
                'phone'            => $phone_clean,
                'location_key'     => $location,
                'location_display' => $location_data['display_name'] ?? $location,
                'location_icon'    => $location_data['icon'] ?? '🏠',
                'unit_type'        => $unit_type ?? 'Type 36/60',
                'program'          => $program ?? 'Subsidi'
            ],
            'assignment' => [
                'assigned_type'              => $assignment['assigned_type'],
                'assigned_marketing_name'    => $assignment['assigned_marketing_name'],
                'assigned_marketing_phone'   => $assignment['assigned_marketing_phone'],
                'assigned_marketing_team_id' => $assignment['assigned_marketing_team_id'],
                'assigned_marketing_id'      => $assignment['assigned_marketing_id'] ?? $assignment['assigned_marketing_team_id'],
                'developer_mode'              => $developer_mode
            ],
            'marketing_internal' => $assignment['marketing_data'],
            'customer_id'        => $lead_id,
            'is_duplicate'       => $is_duplicate,
            'timestamp'          => date('Y-m-d H:i:s')
        ];
        
        $ch = curl_init($webhook_url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($webhook_data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        curl_exec($ch);
        curl_close($ch);
    }
}

?>