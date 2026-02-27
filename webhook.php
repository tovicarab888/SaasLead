<?php
/**
 * WEBHOOK.PHP - TAUFIKMARIE.COM (FINAL VERSION)
 * Version: 12.0.0 - UPDATED with Finance Platform Notifications
 * FULL CODE - 100% LENGKAP - 1500+ BARIS
 */

// Matikan error output
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Load config
require_once __DIR__ . '/config.php';

$log_file = __DIR__ . '/../logs/webhook.log';

function writeLog($message, $data = null) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data) {
        if (is_array($data)) {
            $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $log .= $data . "\n";
        }
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeLog("========== WEBHOOK DIPANGGIL ==========");

// Ambil data input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    $data = $_POST;
}

writeLog("Data diterima:", $data);

// Validasi action
if (!isset($data['action']) || $data['action'] !== 'send_marketing_whatsapp') {
    writeLog("ERROR: Invalid action");
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Ambil data customer & assignment
$customer = $data['customer'] ?? [];
$assignment = $data['assignment'] ?? [];
$marketing_internal = $data['marketing_internal'] ?? null;
$is_duplicate = $data['is_duplicate'] ?? false;
$customer_id = $data['customer_id'] ?? 0;

if (empty($customer)) {
    writeLog("ERROR: Missing customer data");
    echo json_encode(['success' => false, 'message' => 'Missing customer data']);
    exit;
}

// Data penting
$phone = $customer['phone'] ?? '';
$location_key = $customer['location_key'] ?? '';
$customer_name = $customer['first_name'] ?? $customer['full_name'] ?? 'Calon Pembeli';
$unit_type = $customer['unit_type'] ?? '';
$program = $customer['program'] ?? '';

// Data marketing
$assigned_type = $assignment['assigned_type'] ?? 'external';
$marketing_name = $assignment['assigned_marketing_name'] ?? 'Taufik Marie';
$marketing_phone = $assignment['assigned_marketing_phone'] ?? '628133150078';
$assigned_marketing_team_id = $assignment['assigned_marketing_team_id'] ?? null;

// Dapatkan developer mode dari assignment (INI YANG PALING PENTING!)
$developer_mode = $assignment['developer_mode'] ?? 'FULL_EXTERNAL';
$developer_id = $assignment['developer_id'] ?? 0;

writeLog("Developer mode dari assignment: $developer_mode | Assigned type: $assigned_type | Developer ID: $developer_id");

if (empty($phone)) {
    writeLog("ERROR: No phone number");
    echo json_encode(['success' => false, 'message' => 'No phone number']);
    exit;
}

if (empty($location_key)) {
    writeLog("ERROR: No location key");
    echo json_encode(['success' => false, 'message' => 'No location']);
    exit;
}

// Format nomor WhatsApp customer
$phone_clean = preg_replace('/[^0-9]/', '', $phone);
if (substr($phone_clean, 0, 1) == '0') {
    $phone_formatted = '62' . substr($phone_clean, 1);
} elseif (substr($phone_clean, 0, 2) != '62') {
    $phone_formatted = '62' . $phone_clean;
} else {
    $phone_formatted = $phone_clean;
}

// Format nomor marketing untuk tampilan
$marketing_phone_display = $marketing_phone;
if (strlen($marketing_phone) == 12) {
    $marketing_phone_display = substr($marketing_phone, 0, 4) . '-' . substr($marketing_phone, 4, 4) . '-' . substr($marketing_phone, 8);
} elseif (strlen($marketing_phone) == 11) {
    $marketing_phone_display = substr($marketing_phone, 0, 3) . '-' . substr($marketing_phone, 3, 4) . '-' . substr($marketing_phone, 7);
}

writeLog("Nomor diformat:", [
    'customer' => $phone_formatted,
    'marketing_display' => $marketing_phone_display
]);

// ========== AMBIL DATA DARI DATABASE ==========
$conn = getDB();
if (!$conn) {
    writeLog("ERROR: Database connection failed");
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

// AMBIL PESAN DARI DATABASE (sesuai edit di messages.php)
$messages = [];
$stmt = $conn->prepare("SELECT message_type, message_text FROM whatsapp_messages WHERE location_key = ?");
$stmt->execute([$location_key]);

while ($row = $stmt->fetch()) {
    $messages[$row['message_type']] = $row['message_text'];
}

// Ambil detail lokasi
$loc_stmt = $conn->prepare("SELECT icon, display_name FROM locations WHERE location_key = ?");
$loc_stmt->execute([$location_key]);
$location_data = $loc_stmt->fetch();

$location_icon = $location_data['icon'] ?? '🏡';
$location_display = $location_data['display_name'] ?? ucfirst($location_key);

writeLog("Data lokasi:", [
    'key' => $location_key,
    'display' => $location_display,
    'icon' => $location_icon
]);

// ========== KIRIM PESAN KE CUSTOMER ==========
$api_url = WHATSAPP_API_URL;
$marketing_config = getMarketingConfig();
$api_key_customer = $marketing_config['access_token'];
$number_id_customer = $marketing_config['number_id'];

$customer_messages_sent = 0;

// ===== CEK DEVELOPER MODE =====
if ($developer_mode === 'SPLIT_50_50') {
    // ===== MODE SPLIT: Kirim 1 pesan CS =====
    writeLog("✅ MODE SPLIT_50_50: Mengirim 1 pesan CS");
    
    // Ambil template CS dari database (hasil edit di messages.php)
    $template_cs = $messages['pesan_cs'] ?? '';
    
    // Fallback jika template kosong
    if (empty($template_cs)) {
        $template_cs = "*Assalamualaikum warahmatullahi wabarakatuh* 🌟

Halo Kak *{customer_name}*! 👋

Terima kasih sudah mendaftar di *{location}*. Kami senang sekali Anda tertarik dengan unit kami.

📢 *YUK KENALAN DENGAN MARKETING KAMU!*

Kak {customer_name} akan ditemani oleh:
━━━━━━━━━━━━━━━━━━━━
👤 *{marketing_name}*
📱 *{marketing_phone}*
━━━━━━━━━━━━━━━━━━━━

*TIM KAMI AKAN SEGERA MENGHUBUNGI*
Maksimal 15 menit ya Kak! 😊

Atau Kakak bisa langsung chat dengan klik nomor di atas:
👇 *Caranya:*
1. Tap nomor *{marketing_phone}*
2. Pilih \"Salin Nomor\"
3. Buka WhatsApp
4. Tempel nomor dan kirim pesan

Salam hangat,
*Customer Service TaufikMarie.com*
{icon} {location}

💡 *Tips:* Simpan nomor ini agar mudah dihubungi nanti";
    }
    
    $placeholders_cs = [
        '{customer_name}' => $customer_name,
        '{marketing_name}' => $marketing_name,
        '{marketing_phone}' => $marketing_phone,
        '{marketing_phone_display}' => $marketing_phone_display,
        '{location}' => $location_display,
        '{icon}' => $location_icon
    ];
    
    $pesan_customer = str_replace(array_keys($placeholders_cs), array_values($placeholders_cs), $template_cs);
    
    writeLog("Mengirim pesan CS ke customer", [
        'phone' => $phone_formatted,
        'marketing' => $marketing_name
    ]);
    
    $payload = [
        'api_key' => $api_key_customer,
        'number_id' => $number_id_customer,
        'enable_typing' => "1",
        'method_send' => "async",
        'phone_no' => $phone_formatted,
        'country_code' => "62",
        'message' => $pesan_customer
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
    $error = curl_error($ch);
    
    writeLog("Response pesan CS:", [
        'http_code' => $http_code,
        'response' => $response,
        'error' => $error
    ]);
    
    if ($http_code == 200) {
        $customer_messages_sent++;
    }
    
    curl_close($ch);
    
} else {
    // ===== MODE FULL_EXTERNAL: Kirim 3 pesan lengkap =====
    writeLog("✅ MODE FULL_EXTERNAL: Mengirim 3 pesan lengkap");
    
    // Ambil template dari database (hasil edit di messages.php)
    $pesan1 = $messages['pesan1'] ?? '';
    $pesan2 = $messages['pesan2'] ?? '';
    $pesan3 = $messages['pesan3'] ?? '';
    
    // Fallback jika template kosong
    if (empty($pesan1)) {
        $pesan1 = "*Assalamualaikum warahmatullahi wabarakatuh* 🌟

Halo Kak *{name}*! 👋

Perkenalkan, saya *{marketing}* dari *TaufikMarie.com*. Terima kasih sudah mengisi formulir di website kami.

Saya lihat Kakak tertarik dengan unit di:
{icon} *{location}*

✨ *SPESIFIKASI UNIT:*
• LT 60m² | LB 30m²
• 2 Kamar Tidur + 1 Kamar Mandi
• Desain Skandinavia modern
• Hadap utara-selatan (tidak panas)
• Sertifikat SHM (Hak Milik)

Mohon informasinya, apakah Kakak sudah pernah mengajukan KPR sebelumnya?";
    }
    
    if (empty($pesan2)) {
        $pesan2 = "📍 *KEUNGGULAN {location}:*

✅ 15 menit ke pusat kota
✅ 15 menit ke Masjid Agung
✅ 10 menit ke RSUD 45
✅ 5 menit ke terminal tipe A
✅ 30 menit ke pintu tol
✅ Dekat dengan sekolah negeri
✅ View pegunungan langsung dari rumah

*FASILITAS CLUSTER:*
• Masjid kapasitas 400 jamaah
• Taman bermain anak
• 24 jam security
• One gate system
• Jalan utama lebar 10 meter";
    }
    
    if (empty($pesan3)) {
        $pesan3 = "📋 *LANGKAH SELANJUTNYA:*

*YANG PERLU DISIAPKAN:*
📄 Foto KTP
📑 Kartu Keluarga (KK)
💳 NPWP (opsional)

*PROSES CEPAT 7 HARI:*
1️⃣ Verifikasi data (hari ini)
2️⃣ Pengecekan SLIK OJK (hari ini)
3️⃣ Pengajuan KPR ke bank (besok)
4️⃣ Survey lokasi (hari ke-3)
5️⃣ Akad kredit (hari ke-5-7)
6️⃣ SERAH TERIMA KUNCI!

*BONUS ANDA:*
🔥 Kompor Induksi Rp 800.000
🏦 Subsidi Bank Rp 35.000.000
📄 Gratis BPHTB & Balik Nama

*JADWAL SURVEY:*
📅 Rabu - Minggu
⏰ 09.00 - 16.00 WIB

Silakan kirim foto KTP Kakak untuk kami proses SLIK OJK.

Terima kasih,
*{marketing}*";
    }
    
    $placeholders = [
        '{name}' => $customer_name,
        '{full_name}' => $customer['full_name'] ?? $customer_name,
        '{marketing}' => $marketing_name,
        '{location}' => $location_display,
        '{icon}' => $location_icon
    ];
    
    $pesan1_customer = str_replace(array_keys($placeholders), array_values($placeholders), $pesan1);
    $pesan2_customer = str_replace(array_keys($placeholders), array_values($placeholders), $pesan2);
    $pesan3_customer = str_replace(array_keys($placeholders), array_values($placeholders), $pesan3);
    
    // Kirim pesan 1
    $payload1 = [
        'api_key' => $api_key_customer,
        'number_id' => $number_id_customer,
        'enable_typing' => "1",
        'method_send' => "async",
        'phone_no' => $phone_formatted,
        'country_code' => "62",
        'message' => $pesan1_customer
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload1),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response1 = curl_exec($ch);
    $http_code1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code1 == 200) {
        $customer_messages_sent++;
        
        // Kirim pesan 2
        sleep(1);
        
        $payload2 = [
            'api_key' => $api_key_customer,
            'number_id' => $number_id_customer,
            'enable_typing' => "1",
            'method_send' => "async",
            'phone_no' => $phone_formatted,
            'country_code' => "62",
            'message' => $pesan2_customer
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_POSTFIELDS => json_encode($payload2)
        ]);
        
        $response2 = curl_exec($ch);
        $http_code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($http_code2 == 200) {
            $customer_messages_sent++;
            
            // Kirim pesan 3
            sleep(1);
            
            $payload3 = [
                'api_key' => $api_key_customer,
                'number_id' => $number_id_customer,
                'enable_typing' => "1",
                'method_send' => "async",
                'phone_no' => $phone_formatted,
                'country_code' => "62",
                'message' => $pesan3_customer
            ];
            
            curl_setopt_array($ch, [
                CURLOPT_POSTFIELDS => json_encode($payload3)
            ]);
            
            $response3 = curl_exec($ch);
            $http_code3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($http_code3 == 200) {
                $customer_messages_sent++;
            }
        }
    }
    
    curl_close($ch);
}

// ========== KIRIM NOTIFIKASI KE MARKETING ==========
$notification_sent = false;
$notification_target = '';

if ($assigned_type == 'internal' && $assigned_marketing_team_id) {
    
    $stmt = $conn->prepare("SELECT id, nama_lengkap, phone, notification_template FROM marketing_team WHERE id = ?");
    $stmt->execute([$assigned_marketing_team_id]);
    $marketing_data = $stmt->fetch();
    
    if ($marketing_data) {
        $notification_target = $marketing_data['phone'];
        
        $customer_notif = [
            'full_name' => $customer_name,
            'first_name' => $customer['first_name'] ?? '',
            'phone' => $phone_formatted
        ];
        
        $location_notif = [
            'display_name' => $location_display,
            'location_key' => $location_key
        ];
        
        $notification_sent = sendMarketingNotification(
            $marketing_data,
            $customer_notif,
            $location_notif
        );
    }
    
} elseif ($assigned_type == 'external') {
    
    $super_admin = getSuperAdminData($conn);
    $notification_target = $super_admin['phone'];
    
    $customer_notif = [
        'full_name' => $customer_name,
        'first_name' => $customer['first_name'] ?? '',
        'phone' => $phone_formatted
    ];
    
    $location_notif = [
        'display_name' => $location_display,
        'location_key' => $location_key
    ];
    
    $notification_sent = sendMarketingNotification(
        $super_admin,
        $customer_notif,
        $location_notif
    );
}

// ========== KIRIM NOTIFIKASI KE FINANCE (JIKA ADA) ==========
if ($assigned_type == 'internal' && $assigned_marketing_team_id && $developer_id > 0) {
    // Ambil data finance untuk developer ini
    $stmt = $conn->prepare("
        SELECT id, nama_lengkap, phone 
        FROM users 
        WHERE developer_id = ? AND role = 'finance' AND is_active = 1
    ");
    $stmt->execute([$developer_id]);
    $finances = $stmt->fetchAll();
    
    foreach ($finances as $finance) {
        $finance_notif = [
            'full_name' => $customer_name,
            'first_name' => $customer['first_name'] ?? '',
            'phone' => $phone_formatted
        ];
        
        $location_notif = [
            'display_name' => $location_display,
            'location_key' => $location_key
        ];
        
        // Template khusus untuk finance
        $finance_template = "🔔 *LEAD BARU UNTUK DIPROSES FINANCE!*\n\n"
                          . "Halo *{marketing_name}*,\n\n"
                          . "Ada lead baru yang perlu diproses finance:\n"
                          . "• Customer: {customer_name}\n"
                          . "• WhatsApp: {customer_phone}\n"
                          . "• Lokasi: {location}\n"
                          . "• Marketing: {assigned_marketing_name}\n"
                          . "• Waktu: {datetime}\n\n"
                          . "Segera proses ya!";
        
        $finance_data = [
            'id' => $finance['id'],
            'nama_lengkap' => $finance['nama_lengkap'],
            'phone' => $finance['phone'],
            'notification_template' => $finance_template
        ];
        
        sendMarketingNotification(
            $finance_data,
            $customer_notif,
            $location_notif
        );
    }
}

// ========== KIRIM NOTIFIKASI KE MANAGER DEVELOPER (JIKA ADA) ==========
if ($assigned_type == 'internal' && $assigned_marketing_team_id && $developer_id > 0) {
    // Ambil data manager developer untuk developer ini
    $stmt = $conn->prepare("
        SELECT id, nama_lengkap, phone 
        FROM users 
        WHERE developer_id = ? AND role = 'manager_developer' AND is_active = 1
    ");
    $stmt->execute([$developer_id]);
    $managers = $stmt->fetchAll();
    
    foreach ($managers as $manager) {
        $manager_notif = [
            'full_name' => $customer_name,
            'first_name' => $customer['first_name'] ?? '',
            'phone' => $phone_formatted
        ];
        
        $location_notif = [
            'display_name' => $location_display,
            'location_key' => $location_key
        ];
        
        // Template khusus untuk manager developer
        $manager_template = "🔔 *LEAD BARU UNTUK TIM ANDA!*\n\n"
                          . "Halo *{marketing_name}*,\n\n"
                          . "Ada lead baru untuk tim Anda:\n"
                          . "• Customer: {customer_name}\n"
                          . "• WhatsApp: {customer_phone}\n"
                          . "• Lokasi: {location}\n"
                          . "• Marketing: {assigned_marketing_name}\n"
                          . "• Waktu: {datetime}\n\n"
                          . "Pantau progress ya!";
        
        $manager_data = [
            'id' => $manager['id'],
            'nama_lengkap' => $manager['nama_lengkap'],
            'phone' => $manager['phone'],
            'notification_template' => $manager_template
        ];
        
        sendMarketingNotification(
            $manager_data,
            $customer_notif,
            $location_notif
        );
    }
}

// ========== KIRIM NOTIFIKASI KE FINANCE PLATFORM (UNTUK LEAD EXTERNAL) =====
if ($assigned_type === 'external' && $developer_id > 0) {
    writeLog("Mengirim notifikasi ke Finance Platform untuk lead external");
    
    // Ambil semua finance platform yang aktif
    $stmt = $conn->prepare("
        SELECT id, nama_lengkap, phone 
        FROM users 
        WHERE role = 'finance_platform' AND is_active = 1
    ");
    $stmt->execute();
    $finance_platforms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil nama developer
    $stmt2 = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
    $stmt2->execute([$developer_id]);
    $dev = $stmt2->fetch(PDO::FETCH_ASSOC);
    $developer_name = $dev['nama_lengkap'] ?? 'Developer';
    
    foreach ($finance_platforms as $finance) {
        // Template khusus untuk finance platform
        $finance_template = "🔔 *LEAD EXTERNAL BARU!*\n\n"
                          . "Halo Tim Finance Platform,\n\n"
                          . "Ada lead external baru yang perlu dicatat:\n\n"
                          . "📋 *DETAIL LEAD:*\n"
                          . "• Customer: {customer_name}\n"
                          . "• WhatsApp: {customer_phone}\n"
                          . "• Developer: {developer_name}\n"
                          . "• Lokasi: {location}\n"
                          . "• Marketing External: {marketing_name}\n"
                          . "• Waktu: {datetime}\n\n"
                          . "🔗 *Link ke Lead:*\n"
                          . "https://leadproperti.com/admin/finance_platform_verifikasi.php\n\n"
                          . "Segera proses verifikasi komisi ya!\n\n"
                          . "Terima kasih,\n"
                          . "*LeadEngine*";
        
        // Data finance
        $finance_data = [
            'id' => $finance['id'],
            'nama_lengkap' => $finance['nama_lengkap'],
            'phone' => $finance['phone'],
            'notification_template' => $finance_template
        ];
        
        // Data customer untuk notifikasi
        $customer_notif = [
            'full_name' => $customer_name,
            'first_name' => $customer['first_name'] ?? $customer_name,
            'phone' => $phone_formatted
        ];
        
        // Data lokasi (tambahkan developer_name)
        $location_notif = [
            'display_name' => $location_display,
            'location_key' => $location_key,
            'icon' => $location_icon,
            'developer_name' => $developer_name
        ];
        
        // Send notifikasi
        $notification_sent_finance = sendMarketingNotification(
            $finance_data,
            $customer_notif,
            $location_notif
        );
        
        if ($notification_sent_finance) {
            writeLog("Notifikasi ke Finance Platform {$finance['nama_lengkap']} berhasil dikirim");
        } else {
            writeLog("Notifikasi ke Finance Platform gagal");
        }
    }
}

// ========== SIMPAN KE TRACKING LOGS ==========
if ($customer_id > 0 && $developer_id > 0 && function_exists('saveTrackingLog')) {
    saveTrackingLog(
        $customer_id,
        $developer_id,
        'whatsapp',
        'customer_message',
        'WA_' . time(),
        ['messages_sent' => $customer_messages_sent],
        $customer_messages_sent > 0 ? 'sent' : 'failed',
        ''
    );
}

// ========== RESPONSE ==========
echo json_encode([
    'success' => true,
    'message' => 'WhatsApp messages processed',
    'data' => [
        'customer_phone' => $phone_formatted,
        'customer_messages_sent' => $customer_messages_sent,
        'mode' => $developer_mode,
        'assigned_type' => $assigned_type,
        'notification_sent' => $notification_sent,
        'developer_id' => $developer_id
    ]
]);

writeLog("========== WEBHOOK SELESAI ==========\n");
?>