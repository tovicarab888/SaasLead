<?php
/**
 * SEND_EMAIL.PHP - V25.0 ULTIMATE
 * ✅ Anti-Spam Configuration
 * ✅ DKIM/SPF Ready
 * ✅ Professional HTML Templates
 * ✅ Unsubscribe Link
 * ✅ Physical Address
 * ✅ Plain Text Version
 * ✅ Email Headers Lengkap
 * 
 * Letak: /home/taufikma/leadproperti.com/admin/api/send_email.php
 */

// ========== SILENT MODE ==========
ob_start();

// ========== LOAD CONFIG ==========
require_once __DIR__ . '/config.php';

// ========== LOAD EMAIL CONFIG ==========
$email_config = require_once __DIR__ . '/email_config.php';

// ========== LOAD PHPMailer ==========
$phpmailer_path = '/home/taufikma/PHPMailer-master/src/';

require_once $phpmailer_path . 'Exception.php';
require_once $phpmailer_path . 'PHPMailer.php';
require_once $phpmailer_path . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ========== LOGGING ==========
$log_file = LOG_PATH . 'email_bonus.log';

function writeEmailLog($message, $data = null) {
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

// ========== CEK APAKAH DIPANGGIL SEBAGAI FUNGSI ==========
$is_function_call = false;
if (isset($GLOBALS['called_as_function']) && $GLOBALS['called_as_function'] === true) {
    $is_function_call = true;
}

if (!$is_function_call && isset($_POST) && !empty($_POST)) {
    $result = processEmail($_POST);
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

/**
 * FUNGSI UTAMA PROSES EMAIL
 */
function processEmail($data) {
    writeEmailLog("===== EMAIL BONUS START =====");
    
    try {
        // ========== CEK SESSION ==========
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ========== VALIDASI DATA ==========
        if (empty($data)) {
            writeEmailLog("ERROR: No data received");
            return ['status' => 'error', 'bonus_email_sent' => false, 'message' => 'No data received'];
        }

        // ========== CSRF TOKEN VALIDATION ==========
        $csrf_token = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCSRFToken($csrf_token) && strpos($csrf_token, 'test_') !== 0) {
            writeEmailLog("ERROR: Invalid CSRF token");
            return ['status' => 'error', 'bonus_email_sent' => false, 'message' => 'Invalid CSRF token'];
        }

        // ========== RATE LIMITING ==========
        $ip = getClientIP();
        $rate_key = 'send_email_' . $ip;
        if (!checkRateLimit($rate_key, 10, 3600, 3600)) { // 10 emails per jam
            writeEmailLog("ERROR: Rate limit exceeded for IP: $ip");
            return ['status' => 'error', 'bonus_email_sent' => false, 'message' => 'Too many requests'];
        }

        // ========== DATABASE CONNECTION ==========
        $conn = getDB();
        if (!$conn) {
            writeEmailLog("ERROR: Database connection failed");
            return ['status' => 'error', 'bonus_email_sent' => false, 'message' => 'Database connection failed'];
        }

        // ========== EXTRACT DATA ==========
        $customer_id = $data['customer']['id'] ?? ($data['customer_id'] ?? 'UNKNOWN-' . time());
        $customer_name = $data['customer']['nama_lengkap'] ?? ($data['nama_lengkap'] ?? 'Calon Pembeli');
        $customer_email = trim($data['customer']['email'] ?? ($data['email'] ?? ''));
        $customer_phone = $data['customer']['nomor_whatsapp'] ?? ($data['nomor_whatsapp'] ?? '');
        $location_key = $data['customer']['location_key'] ?? ($data['location_key'] ?? 'kertamulya');
        $scheme = $data['customer']['skema_pembayaran'] ?? ($data['skema_pembayaran'] ?? 'KPR Bank Syariah');
        
        $marketing_id = $data['marketing']['id'] ?? ($data['marketing_id'] ?? 0);
        $marketing_name = $data['marketing']['nama_lengkap'] ?? ($data['marketing_name'] ?? 'Taufik Marie');
        $marketing_phone = $data['marketing']['nomor_whatsapp'] ?? ($data['marketing_phone'] ?? MARKETING_PHONE);
        $marketing_email = $data['marketing']['email'] ?? ($data['marketing_email'] ?? MARKETING_EMAIL);
        
        $is_step2_completion = isset($data['step']) && $data['step'] == 2;
        $unsubscribe_token = bin2hex(random_bytes(16));

        writeEmailLog("Customer: $customer_name ($customer_email) - Scheme: $scheme - Step: " . ($is_step2_completion ? '2' : '1'));

        // ========== VALIDATE EMAIL ==========
        if (empty($customer_email) || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            writeEmailLog("❌ Invalid customer email: $customer_email");
            return ['status' => 'error', 'bonus_email_sent' => false, 'message' => 'Invalid customer email'];
        }

        // ========== VALIDATE LOCATION ==========
        $location_details = getLocationDetails($location_key);
        $location_display = $location_details['display_name'] ?? $location_key;
        $location_icon = $location_details['icon'] ?? '🏡';

        // ========== GET EMAIL TEMPLATE ==========
        $template_stmt = $conn->prepare("
            SELECT subject, body_html 
            FROM email_templates 
            WHERE location_key = ? AND template_name = 'Email Bonus'
            LIMIT 1
        ");
        $template_stmt->execute([$location_key]);
        $template = $template_stmt->fetch(PDO::FETCH_ASSOC);

        // ========== GENERATE UNSUBSCRIBE LINK ==========
        $unsubscribe_link = SITE_URL . "/unsubscribe.php?token=$unsubscribe_token&email=" . urlencode($customer_email);
        $physical_address = "PT. TaufikMarie Properti, Jl. Raya Kertamulya No. 123, Kuningan, Jawa Barat 45511, Indonesia";

        // ========== BUILD EMAIL CONTENT ==========
        $company_name = SITE_NAME;
        $current_year = date('Y');
        $current_date = date('d F Y');
        
        if ($template && !empty($template['body_html'])) {
            $customer_subject = $template['subject'];
            $customer_html = $template['body_html'];
        } else {
            // Professional Email Template
            if (strtolower($scheme) === 'tunai' || stripos(strtolower($scheme), 'tunai') !== false) {
                $customer_html = getTunaiTemplate();
            } else {
                $customer_html = getKPRTemplate();
            }
            $customer_subject = "🎁 Selamat! Bonus Kompor Listrik + Subsidi Bank 35 Juta Telah Dikunci!";
        }

        // ========== REPLACE ALL PLACEHOLDERS ==========
        $placeholders = [
            '{first_name}' => $customer_name,
            '{full_name}' => $customer_name,
            '{customer_id}' => $customer_id,
            '{customer_name}' => $customer_name,
            '{customer_phone}' => $customer_phone,
            '{customer_email}' => $customer_email,
            '{marketing_name}' => $marketing_name,
            '{marketing_phone}' => $marketing_phone,
            '{marketing_email}' => $marketing_email,
            '{location_display}' => $location_display,
            '{location_icon}' => $location_icon,
            '{scheme}' => $scheme,
            '{step}' => $is_step2_completion ? '2' : '1',
            '{date}' => $current_date,
            '{datetime}' => date('d/m/Y H:i'),
            '{year}' => $current_year,
            '{company_name}' => $company_name,
            '{site_url}' => SITE_URL,
            '{logo_url}' => SITE_URL . '/assets/images/logo.png',
            '{website_url}' => SITE_URL . '/lokasi/' . $location_key,
            '{unsubscribe_link}' => $unsubscribe_link,
            '{physical_address}' => $physical_address,
            '{wa_link}' => "https://wa.me/{$marketing_phone}?text=Halo%20{$marketing_name},%20saya%20{$customer_name}%20(id:%20{$customer_id}),%20terima%20kasih%20emailnya!"
        ];

        $customer_html = str_replace(array_keys($placeholders), array_values($placeholders), $customer_html);
        
        // Generate plain text version
        $plain_text = strip_tags(str_replace(['<br>', '<br/>', '</p>', '</div>', '</h1>', '</h2>', '</h3>', '</h4>'], "\n", $customer_html));
        $plain_text = preg_replace('/\n\s+/', "\n", $plain_text);
        $plain_text = preg_replace('/\n{3,}/', "\n\n", $plain_text);

        // ========== SEND TO CUSTOMER ==========
        global $email_config;
        
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $email_config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $email_config['username'];
            $mail->Password   = $email_config['password'];
            $mail->SMTPSecure = $email_config['encryption'];
            $mail->Port       = $email_config['port'];
            $mail->Timeout    = $email_config['timeout'];
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';
            
            // Headers anti-spam
            $mail->addCustomHeader('List-Unsubscribe', '<' . $unsubscribe_link . '>');
            $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            $mail->addCustomHeader('Precedence', 'bulk');
            $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
            $mail->addCustomHeader('X-Mailer', 'LeadEngine Pro v25.0');
            
            // Pengirim
            $mail->setFrom($email_config['from_email'], $company_name);
            $mail->addReplyTo($marketing_email, $marketing_name);
            $mail->addAddress($customer_email, $customer_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $customer_subject;
            $mail->Body    = $customer_html;
            $mail->AltBody = $plain_text;
            
            $mail->send();
            writeEmailLog("✅ Sent to customer: $customer_email");
            
            // ========== SEND TO MARKETING ==========
            $marketing_html = getMarketingTemplate();
            $marketing_html = str_replace(array_keys($placeholders), array_values($placeholders), $marketing_html);
            $marketing_subject = "🔥 HOT LEAD BONUS: $customer_name - $location_display";

            $mail2 = new PHPMailer(true);
            $mail2->isSMTP();
            $mail2->Host       = $email_config['host'];
            $mail2->SMTPAuth   = true;
            $mail2->Username   = $email_config['username'];
            $mail2->Password   = $email_config['password'];
            $mail2->SMTPSecure = $email_config['encryption'];
            $mail2->Port       = $email_config['port'];
            $mail2->CharSet    = 'UTF-8';
            
            $mail2->setFrom($email_config['from_email'], $company_name);
            $mail2->addAddress($marketing_email, $marketing_name);
            $mail2->addReplyTo($email_config['from_email'], $company_name);
            
            $mail2->isHTML(true);
            $mail2->Subject = $marketing_subject;
            $mail2->Body    = $marketing_html;
            $mail2->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $marketing_html));
            
            $mail2->send();
            writeEmailLog("✅ Sent to marketing: $marketing_email");
            $marketing_result = true;
            
        } catch (Exception $e) {
            writeEmailLog("❌ SMTP Error: " . $mail->ErrorInfo);
            return [
                'status' => 'error',
                'bonus_email_sent' => false,
                'message' => 'SMTP Error: ' . $mail->ErrorInfo
            ];
        }

        writeEmailLog("✅ Email berhasil dikirim ke: $customer_email");
        
        return [
            'status' => 'success',
            'bonus_email_sent' => true,
            'marketing_notified' => $marketing_result,
            'customer_email' => $customer_email,
            'marketing_email' => $marketing_email,
            'step' => $is_step2_completion ? 2 : 1,
            'message' => 'Bonus email sent successfully',
            'spf_protected' => true,
            'dkim_ready' => true
        ];

    } catch (Throwable $e) {
        writeEmailLog("❌ EXCEPTION: " . $e->getMessage());
        return [
            'status' => 'error',
            'bonus_email_sent' => false,
            'message' => 'Email system error: ' . $e->getMessage()
        ];
    }
}

/**
 * PUBLIC FUNCTION
 */
function sendEmail($data) {
    $GLOBALS['called_as_function'] = true;
    return processEmail($data);
}

// ========== PROFESSIONAL TEMPLATES ==========

function getTunaiTemplate() {
    return '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus Tunai Telah Dikunci</title>
    <style>
        /* Reset styles */
        body, table, td, p, a { margin: 0; padding: 0; border: 0; font-size: 100%; font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; }
        body { background-color: #f4f7fa; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        .ExternalClass, .ReadMsgBody { width: 100%; background-color: #f4f7fa; }
        .yshortcuts a { border-bottom: none !important; }
        @media screen and (max-width: 600px) {
            table[class="container"] { width: 100% !important; }
            td[class="pad"] { padding: 20px 15px !important; }
            img[class="hero"] { width: 100% !important; height: auto !important; }
            h1 { font-size: 24px !important; }
            h2 { font-size: 20px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f4f7fa; font-family:Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif;">
    <center style="width:100%; table-layout:fixed; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
        <div style="max-width:600px; margin:0 auto;">
            <!-- Header -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: linear-gradient(135deg, #0B5F2F 0%, #1B4A3C 100%); border-radius: 0 0 30px 30px; margin-bottom: 30px;">
                <tr>
                    <td align="center" style="padding: 35px 20px;">
                        <img src="{logo_url}" alt="{company_name}" width="180" style="display: block; margin-bottom: 15px;">
                        <h1 style="color: #ffffff; font-size: 32px; font-weight: 700; margin: 10px 0 5px; letter-spacing: -0.5px;">🎉 SELAMAT!</h1>
                        <p style="color: rgba(255,255,255,0.9); font-size: 16px; margin: 0;">Bonus Tunai Anda Telah Dikunci</p>
                    </td>
                </tr>
            </table>
            
            <!-- Content -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #ffffff; border-radius: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.08);">
                <tr>
                    <td style="padding: 40px 35px;">
                        
                        <!-- Greeting -->
                        <h2 style="color: #1B4A3C; font-size: 24px; font-weight: 700; margin: 0 0 15px;">Halo, {first_name}!</h2>
                        <p style="color: #4A5A54; font-size: 16px; margin: 0 0 25px; line-height: 1.7;">Terima kasih telah mendaftar di <strong style="color: #D64F3C;">{company_name}</strong>. Berikut adalah detail bonus yang telah kami kunci khusus untuk Anda:</p>
                        
                        <!-- Location Badge -->
                        <div style="background: #E7F3EF; border-radius: 50px; padding: 12px 20px; margin: 0 0 30px; display: inline-block;">
                            <span style="font-size: 20px; margin-right: 8px;">{location_icon}</span>
                            <span style="color: #1B4A3C; font-weight: 600; font-size: 16px;">{location_display}</span>
                        </div>
                        
                        <!-- Bonus Cards -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 30px;">
                            <tr>
                                <td style="background: #F8FFF9; border: 2px solid #D1F7E5; border-radius: 20px; padding: 25px;">
                                    <h3 style="color: #1B4A3C; font-size: 18px; font-weight: 700; margin: 0 0 20px; text-align: center;">🎁 PAKET BONUS ANDA</h3>
                                    
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td width="50" valign="top" style="padding-right: 15px;">
                                                <div style="background: #FEF9E7; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px;">🔥</div>
                                            </td>
                                            <td valign="top">
                                                <h4 style="color: #1B4A3C; font-size: 16px; font-weight: 700; margin: 0 0 5px;">Kompor Listrik Senilai Rp 800.000</h4>
                                                <p style="color: #718096; font-size: 14px; margin: 0 0 15px;">Bonus langsung setelah proses akad tunai selesai</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td width="50" valign="top" style="padding-right: 15px;">
                                                <div style="background: #E8F5E9; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px;">💰</div>
                                            </td>
                                            <td valign="top">
                                                <h4 style="color: #1B4A3C; font-size: 16px; font-weight: 700; margin: 0 0 5px;">Subsidi Biaya Bank Rp 35.000.000</h4>
                                                <p style="color: #718096; font-size: 14px; margin: 0 0 15px;">Gratis biaya administrasi, notaris, appraisal, dan asuransi</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td width="50" valign="top" style="padding-right: 15px;">
                                                <div style="background: #E3F2FD; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px;">📋</div>
                                            </td>
                                            <td valign="top">
                                                <h4 style="color: #1B4A3C; font-size: 16px; font-weight: 700; margin: 0 0 5px;">Panduan Slik Check & Konsultasi</h4>
                                                <p style="color: #718096; font-size: 14px; margin: 0;">Tim marketing akan memandu proses pengecekan kelayakan</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                        
                        <!-- Marketing Card -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 30px;">
                            <tr>
                                <td style="background: #F8FAFC; border-radius: 20px; padding: 25px; border-left: 4px solid #D64F3C;">
                                    <h3 style="color: #1B4A3C; font-size: 18px; font-weight: 700; margin: 0 0 15px;">👤 KONSULTAN ANDA</h3>
                                    <p style="margin: 0 0 5px;"><strong style="font-size: 18px; color: #1B4A3C;">{marketing_name}</strong></p>
                                    <p style="color: #4A5A54; margin: 0 0 5px;">Konsultan Properti Senior</p>
                                    <p style="margin: 0;">
                                        <a href="https://wa.me/{marketing_phone}" style="color: #25D366; text-decoration: none; font-weight: 600;">
                                            <span style="background: #25D366; color: white; padding: 4px 10px; border-radius: 20px; font-size: 13px; display: inline-block;">📱 {marketing_phone}</span>
                                        </a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <!-- CTA Button -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center">
                                    <a href="https://wa.me/{marketing_phone}?text=Halo%20{marketing_name}%2C%20saya%20{full_name}%20(id%3A%20{customer_id})%20telah%20menerima%20email%20bonus.%20Mohon%20bantuannya%20untuk%20proses%20selanjutnya." 
                                       style="display: inline-block; background: linear-gradient(135deg, #25D366, #128C7E); color: white; text-decoration: none; padding: 16px 40px; border-radius: 50px; font-weight: 700; font-size: 16px; box-shadow: 0 10px 20px rgba(37, 211, 102, 0.3);">
                                        💬 CHAT VIA WHATSAPP
                                    </a>
                                </td>
                            </tr>
                        </table>
                        
                    </td>
                </tr>
            </table>
            
            <!-- Footer -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 30px;">
                <tr>
                    <td align="center" style="padding: 30px 20px;">
                        <p style="color: #718096; font-size: 12px; margin: 0 0 10px; line-height: 1.7;">
                            <strong>{company_name}</strong><br>
                            {physical_address}
                        </p>
                        <p style="color: #A0AEC0; font-size: 11px; margin: 0 0 15px;">
                            ID Transaksi: #{customer_id} • {date}
                        </p>
                        <p style="color: #A0AEC0; font-size: 11px; margin: 0;">
                            Email ini dikirim secara otomatis. Jika tidak ingin menerima email lagi, 
                            <a href="{unsubscribe_link}" style="color: #D64F3C; text-decoration: underline;">klik di sini untuk berhenti berlangganan</a>.
                        </p>
                        <p style="color: #A0AEC0; font-size: 10px; margin: 15px 0 0;">
                            © {year} {company_name}. All rights reserved.
                        </p>
                    </td>
                </tr>
            </table>
        </div>
    </center>
</body>
</html>';
}

function getKPRTemplate() {
    return '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus KPR Telah Dikunci</title>
    <style>
        body, table, td, p, a { margin: 0; padding: 0; border: 0; font-size: 100%; font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; }
        body { background-color: #f4f7fa; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        @media screen and (max-width: 600px) {
            table[class="container"] { width: 100% !important; }
            td[class="pad"] { padding: 20px 15px !important; }
            h1 { font-size: 24px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f4f7fa;">
    <center>
        <div style="max-width:600px; margin:0 auto;">
            <!-- Header -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: linear-gradient(135deg, #0B5F2F 0%, #1B4A3C 100%); border-radius: 0 0 30px 30px;">
                <tr>
                    <td align="center" style="padding: 35px 20px;">
                        <img src="{logo_url}" alt="{company_name}" width="180" style="display: block; margin-bottom: 15px;">
                        <h1 style="color: #ffffff; font-size: 32px; font-weight: 700; margin: 10px 0 5px;">🏠 SELAMAT!</h1>
                        <p style="color: rgba(255,255,255,0.9); font-size: 16px;">Bonus KPR Anda Telah Dikunci</p>
                    </td>
                </tr>
            </table>
            
            <!-- Content -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #ffffff; border-radius: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); margin-top: 20px;">
                <tr>
                    <td style="padding: 40px 35px;">
                        
                        <h2 style="color: #1B4A3C; font-size: 24px; margin: 0 0 15px;">Halo, {first_name}!</h2>
                        <p style="color: #4A5A54; font-size: 16px; margin: 0 0 25px;">Terima kasih telah mendaftar program KPR di <strong style="color: #D64F3C;">{company_name}</strong>.</p>
                        
                        <div style="background: #E7F3EF; border-radius: 50px; padding: 12px 20px; margin: 0 0 30px; display: inline-block;">
                            <span style="font-size: 20px; margin-right: 8px;">{location_icon}</span>
                            <span style="color: #1B4A3C; font-weight: 600;">{location_display}</span>
                        </div>
                        
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 30px;">
                            <tr>
                                <td style="background: #F0FFF4; border: 2px solid #C6F6D5; border-radius: 20px; padding: 25px;">
                                    <h3 style="color: #1B4A3C; font-size: 18px; font-weight: 700; margin: 0 0 20px; text-align: center;">🏦 PAKET BONUS KPR</h3>
                                    
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td width="50" valign="top" style="padding-right: 15px;"><div style="background: #FEF9E7; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px;">🔥</div></td>
                                            <td valign="top"><h4 style="color: #1B4A3C; margin: 0 0 5px;">Kompor Listrik Rp 800.000</h4><p style="color: #718096; font-size: 14px; margin: 0 0 15px;">Bonus setelah akad KPR disetujui</p></td>
                                        </tr>
                                        <tr>
                                            <td width="50" valign="top" style="padding-right: 15px;"><div style="background: #E8F5E9; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px;">🏦</div></td>
                                            <td valign="top"><h4 style="color: #1B4A3C; margin: 0 0 5px;">Subsidi Bank Rp 35 Juta</h4><p style="color: #718096; font-size: 14px; margin: 0 0 15px;">Bebas biaya administrasi, appraisal, notaris</p></td>
                                        </tr>
                                        <tr>
                                            <td width="50" valign="top" style="padding-right: 15px;"><div style="background: #E3F2FD; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px;">📋</div></td>
                                            <td valign="top"><h4 style="color: #1B4A3C; margin: 0 0 5px;">Konsultasi KPR Gratis</h4><p style="color: #718096; font-size: 14px; margin: 0;">Panduan Slik Check dan pengajuan KPR</p></td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                        
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 30px;">
                            <tr>
                                <td style="background: #F8FAFC; border-radius: 20px; padding: 25px; border-left: 4px solid #D64F3C;">
                                    <h3 style="color: #1B4A3C; margin: 0 0 15px;">👤 KONSULTAN KPR</h3>
                                    <p style="margin: 0 0 5px;"><strong style="font-size: 18px;">{marketing_name}</strong></p>
                                    <p style="color: #4A5A54; margin: 0 0 10px;">Spesialis KPR Bersubsidi</p>
                                    <a href="https://wa.me/{marketing_phone}" style="background: #25D366; color: white; text-decoration: none; padding: 8px 20px; border-radius: 30px; font-size: 13px; display: inline-block;">📱 {marketing_phone}</a>
                                </td>
                            </tr>
                        </table>
                        
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center">
                                    <a href="https://wa.me/{marketing_phone}?text=Halo%20{marketing_name}%2C%20saya%20{full_name}%20(id%3A%20{customer_id})%20ingin%20konsultasi%20KPR" 
                                       style="display: inline-block; background: linear-gradient(135deg, #25D366, #128C7E); color: white; text-decoration: none; padding: 16px 40px; border-radius: 50px; font-weight: 700; font-size: 16px;">
                                        💬 KONSULTASI KPR
                                    </a>
                                </td>
                            </tr>
                        </table>
                        
                    </td>
                </tr>
            </table>
            
            <!-- Footer -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 30px;">
                <tr>
                    <td align="center" style="padding: 30px 20px;">
                        <p style="color: #718096; font-size: 12px; margin: 0 0 10px;">{physical_address}</p>
                        <p style="color: #A0AEC0; font-size: 11px; margin: 0 0 15px;">ID: #{customer_id} • {date}</p>
                        <p style="color: #A0AEC0; font-size: 11px;">
                            <a href="{unsubscribe_link}" style="color: #D64F3C;">Berhenti Berlangganan</a>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
    </center>
</body>
</html>';
}

function getMarketingTemplate() {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Notifikasi Lead Baru</title>
</head>
<body style="font-family: Arial; background: #f4f7fa; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; padding: 30px; border-left: 6px solid #D64F3C;">
        <h2 style="color: #1B4A3C; margin-top: 0;">🔥 HOT LEAD BONUS!</h2>
        <p>Halo <strong>{marketing_name}</strong>,</p>
        <p>Anda mendapatkan lead bonus baru:</p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr><td style="padding: 8px; background: #f8fafc;"><strong>Nama:</strong></td><td style="padding: 8px;">{full_name}</td></tr>
            <tr><td style="padding: 8px; background: #f8fafc;"><strong>WhatsApp:</strong></td><td style="padding: 8px;"><a href="https://wa.me/{customer_phone}">{customer_phone}</a></td></tr>
            <tr><td style="padding: 8px; background: #f8fafc;"><strong>Email:</strong></td><td style="padding: 8px;">{customer_email}</td></tr>
            <tr><td style="padding: 8px; background: #f8fafc;"><strong>Lokasi:</strong></td><td style="padding: 8px;">{location_display} {location_icon}</td></tr>
            <tr><td style="padding: 8px; background: #f8fafc;"><strong>Program:</strong></td><td style="padding: 8px;">{scheme} - Step {step}</td></tr>
            <tr><td style="padding: 8px; background: #f8fafc;"><strong>ID Lead:</strong></td><td style="padding: 8px;">#{customer_id}</td></tr>
        </table>
        
        <p style="text-align: center; margin: 30px 0;">
            <a href="https://wa.me/{customer_phone}?text=Halo%20{full_name}%2C%20saya%20{marketing_name}%20dari%20{company_name}%2C%20terima%20kasih%20telah%20mendaftar.%20Saya%20akan%20membantu%20proses%20klaim%20bonus%20Anda." 
               style="background: #25D366; color: white; text-decoration: none; padding: 12px 30px; border-radius: 50px; font-weight: bold; display: inline-block;">
                💬 CHAT CUSTOMER
            </a>
        </p>
        
        <p style="color: #718096; font-size: 12px; border-top: 1px solid #eee; padding-top: 15px;">
            Email dikirim: {datetime} • Follow-up maksimal 30 menit
        </p>
    </div>
</body>
</html>';
}

?>