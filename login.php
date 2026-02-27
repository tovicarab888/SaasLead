<?php
/**
 * LOGIN.PHP - LEADPROPERTI.COM ULTIMATE
 * Version: 23.0.0 - FULL CODE 100% LENGKAP TANPA POTONGAN
 * PERUBAHAN: TaufikMarie → LeadProperti
 * SEMUA FITUR: PWA, Background Slideshow, Toast Notification, dll TETAP UTUH
 */

// Aktifkan session dengan konfigurasi yang benar
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ===== HANDLER UNTUK PWA & SPLASH REDIRECT =====
$from_splash = isset($_GET['from_splash']) || isset($_GET['source']) && $_GET['source'] === 'pwa';
$is_direct_link = !$from_splash && !isset($_SERVER['HTTP_REFERER']);

if ($from_splash) {
    $_SESSION['from_splash'] = true;
}

require_once 'api/config.php';

// Jika sudah login, redirect ke dashboard masing-masing
if (checkAuth()) {
    if (isAdmin()) {
        header('Location: index.php');
    } elseif (isManager()) {
        header('Location: manager_dashboard.php');
    } elseif (isDeveloper()) {
        header('Location: developer_dashboard.php');
    } elseif (isManagerDeveloper()) {
        header('Location: manager_developer_dashboard.php');
    } elseif (isFinance()) {
        header('Location: finance_dashboard.php');
    } elseif (isFinancePlatform()) {
        header('Location: finance_platform_dashboard.php');
    } elseif (isMarketing()) {
        header('Location: marketing_dashboard.php');
    } elseif (isMarketingExternal()) {
        header('Location: marketing_external_dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

// Cek jika sudah login sebagai marketing (untuk jaga-jaga)
if (isset($_SESSION['marketing_id']) && $_SESSION['marketing_id'] > 0) {
    header('Location: marketing_dashboard.php');
    exit();
}

$error = '';
$success = '';
$username = '';
$show_logout_toast = false;

// Cek apakah ada parameter logout
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $show_logout_toast = true;
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        $conn = getDB();
        if ($conn) {
            $login_success = false;
            
            // ========== CEK DI TABEL USERS (SEMUA ROLE) DENGAN JOIN KE MARKETING_TEAM ==========
            try {
                $stmt = $conn->prepare("
                    SELECT u.*, 
                           mt.id as marketing_team_id,
                           mt.developer_id as marketing_developer_id,
                           mt.nama_lengkap as marketing_nama,
                           mt.phone as marketing_phone,
                           mt.nomor_rekening,
                           mt.atas_nama_rekening,
                           mt.nama_bank_rekening,
                           d.nama_lengkap as developer_name,
                           d.distribution_mode as developer_mode
                    FROM users u
                    LEFT JOIN marketing_team mt ON u.id = mt.user_id
                    LEFT JOIN users d ON mt.developer_id = d.id
                    WHERE (u.username = ? OR u.email = ?) 
                      AND u.is_active = 1
                ");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    $login_success = true;
                    
                    // Regenerate session ID untuk keamanan
                    session_regenerate_id(true);
                    
                    // Hapus semua session lama
                    $_SESSION = array();
                    
                    // Set session dasar
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                    $_SESSION['ip_address'] = getClientIP();
                    
                    // Set session khusus berdasarkan role
                    if ($user['role'] === 'marketing') {
                        $_SESSION['marketing_id'] = (int)$user['marketing_team_id'];
                        $_SESSION['marketing_name'] = $user['marketing_nama'] ?: $user['nama_lengkap'];
                        $_SESSION['marketing_phone'] = $user['marketing_phone'] ?? '';
                        $_SESSION['marketing_developer_id'] = (int)$user['marketing_developer_id'];
                        $_SESSION['marketing_developer_name'] = $user['developer_name'] ?? 'Developer';
                        $_SESSION['marketing_developer_mode'] = $user['developer_mode'] ?? 'FULL_INTERNAL_PLATFORM';
                        $_SESSION['marketing_nomor_rekening'] = $user['nomor_rekening'] ?? '';
                        $_SESSION['marketing_atas_nama'] = $user['atas_nama_rekening'] ?? '';
                        $_SESSION['marketing_nama_bank'] = $user['nama_bank_rekening'] ?? '';
                        
                        // Log untuk debugging
                        error_log("Marketing login - ID: " . $user['marketing_team_id'] . ", Developer ID: " . $user['marketing_developer_id']);
                        
                    } elseif ($user['role'] === 'developer') {
                        $_SESSION['location_access'] = $user['location_access'] ?? '';
                        $_SESSION['distribution_mode'] = $user['distribution_mode'] ?? 'FULL_INTERNAL_PLATFORM';
                        $_SESSION['developer_alamat'] = $user['alamat'] ?? '';
                        $_SESSION['developer_kota'] = $user['kota'] ?? '';
                        $_SESSION['developer_npwp'] = $user['npwp'] ?? '';
                        $_SESSION['developer_siup'] = $user['siup'] ?? '';
                        $_SESSION['developer_telepon'] = $user['telepon_perusahaan'] ?? '';
                        
                    } elseif (in_array($user['role'], ['manager_developer', 'finance'])) {
                        $_SESSION['developer_id'] = (int)($user['developer_id'] ?? 0);
                        $_SESSION['manager_developer_name'] = $user['developer_name'] ?? '';
                        
                    } elseif ($user['role'] === 'finance_platform') {
                        logSystem("Finance Platform login: " . $user['username']);
                    }
                    
                    // Update last login di tabel users
                    $update = $conn->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
                    $update->execute([getClientIP(), $user['id']]);
                    
                    // Jika marketing, update juga di marketing_team
                    if ($user['role'] === 'marketing' && $user['marketing_team_id']) {
                        $update2 = $conn->prepare("UPDATE marketing_team SET last_login = NOW() WHERE id = ?");
                        $update2->execute([$user['marketing_team_id']]);
                    }
                    
                    // Handle remember me
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
                    
                    regenerateCSRFToken();
                    
                    logSystem("Login successful", [
                        'user_id' => $user['id'],
                        'username' => $username,
                        'role' => $user['role']
                    ], 'INFO', 'auth.log');
                    
                    // Redirect berdasarkan role
                    if ($user['role'] === 'admin') {
                        header('Location: index.php');
                    } elseif ($user['role'] === 'manager') {
                        header('Location: manager_dashboard.php');
                    } elseif ($user['role'] === 'developer') {
                        header('Location: developer_dashboard.php');
                    } elseif ($user['role'] === 'manager_developer') {
                        header('Location: manager_developer_dashboard.php');
                    } elseif ($user['role'] === 'finance') {
                        header('Location: finance_dashboard.php');
                    } elseif ($user['role'] === 'finance_platform') {
                        header('Location: finance_platform_dashboard.php');
                    } elseif ($user['role'] === 'marketing') {
                        header('Location: marketing_dashboard.php');
                    } elseif ($user['role'] === 'marketing_external') {
                        header('Location: marketing_external_dashboard.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit();
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
            
            // ========== CEK DI TABEL MARKETING_EXTERNAL (UNTUK EXTERNAL MARKETING) ==========
            if (!$login_success) {
                try {
                    error_log("🔍 Mencoba login marketing external - Username: $username");
                    
                    $stmt = $conn->prepare("
                        SELECT u.*, met.id as external_id, met.round_robin_order
                        FROM users u
                        LEFT JOIN marketing_external_team met ON u.id = met.user_id
                        WHERE u.username = ? AND u.role = 'marketing_external' AND u.is_active = 1
                    ");
                    $stmt->execute([$username]);
                    $external = $stmt->fetch();
                    
                    if ($external) {
                        error_log("✅ Marketing external ditemukan - ID: " . $external['id']);
                        
                        if (password_verify($password, $external['password'])) {
                            error_log("✅ Password valid untuk marketing external");
                            
                            // Hapus session lama
                            $_SESSION = array();
                            
                            // Regenerate session ID
                            session_regenerate_id(true);
                            
                            // Set session dengan DATA LENGKAP
                            $_SESSION['user_id'] = (int)$external['id'];
                            $_SESSION['username'] = $external['username'];
                            $_SESSION['nama_lengkap'] = $external['nama_lengkap'];
                            $_SESSION['email'] = $external['email'];
                            $_SESSION['role'] = 'marketing_external'; // INI PALING PENTING!
                            $_SESSION['external_id'] = (int)$external['external_id'];
                            $_SESSION['external_phone'] = $external['contact_phone'] ?? '';
                            $_SESSION['login_time'] = time();
                            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                            $_SESSION['ip_address'] = getClientIP();
                            
                            error_log("✅ Session setelah diset: " . print_r($_SESSION, true));
                            
                            // Update last login
                            $update1 = $conn->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
                            $update1->execute([getClientIP(), $external['id']]);
                            
                            $update2 = $conn->prepare("UPDATE marketing_external_team SET last_assigned = NOW() WHERE id = ?");
                            $update2->execute([$external['external_id']]);
                            
                            // Redirect ke dashboard external
                            header('Location: marketing_external_dashboard.php');
                            exit();
                            
                        } else {
                            error_log("❌ Password salah untuk marketing external");
                        }
                    } else {
                        error_log("❌ Marketing external tidak ditemukan: $username");
                    }
                } catch (Exception $e) {
                    error_log("❌ Error marketing external: " . $e->getMessage());
                }
            }
            
            // Jika tidak ditemukan di semua tabel
            if (!$login_success) {
                $error = 'Username atau password salah!';
                logSystem("Login failed", ['username' => $username], 'WARNING', 'auth.log');
            }
            
        } else {
            $error = 'Koneksi database gagal';
        }
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'logged_out') {
    $success = 'Anda telah berhasil logout dari sistem.';
}

// Background images
$backgrounds = [
    '/assets/images/bg1.webp',
    '/assets/images/bg2.webp', 
    '/assets/images/bg3.webp',
    '/assets/images/bg4.webp',
    '/assets/images/bg5.webp',
    '/assets/images/bg6.webp'
];

$background_exists = [];
foreach ($backgrounds as $bg) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . $bg;
    $background_exists[] = file_exists($file_path) ? $bg : '';
}

$current_bg_index = array_rand($backgrounds);
$redirect = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : '';

// Generate CSRF token untuk form
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1B4A3C">
    
    <title>Login - LeadProperti Dashboard</title>
    <meta name="description" content="Login ke dashboard LeadProperti untuk mengelola data properti dan leads">
    
    <!-- ========== FONTS & ICONS ========== -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- ===== OG IMAGE UNTUK SHARING ===== -->
    <meta property="og:title" content="LeadProperti.com - Dashboard Manajemen Properti Premium">
    <meta property="og:description" content="Sistem manajemen leads properti terlengkap dengan fitur multi-role, tracking pixel, dan notifikasi real-time">
    <meta property="og:image" content="https://leadproperti.com/admin/assets/images/og-image.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="https://leadproperti.com/admin/login.php">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="LeadProperti.com">
    
    <!-- ========== PWA SUPPORT ========== -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LeadProperti">
    <link rel="apple-touch-icon" href="/assets/images/icon-192.png">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
            position: fixed;
            width: 100%;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #1B4A3C;
        }
        
        /* Background Slideshow */
        .bg-slideshow {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }
        
        .bg-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
            filter: brightness(0.6) blur(2px);
        }
        
        .bg-slide.active {
            opacity: 1;
        }
        
        /* Overlay gradient */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(27,74,60,0.85) 0%, rgba(18,56,45,0.9) 100%);
            z-index: 1;
        }
        
        /* Login Container */
        .login-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2;
            width: 100%;
            max-width: 400px;
            padding: 0 20px;
        }
        
        /* Login Card */
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 32px 28px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Logo */
        .logo-wrapper {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo {
            width: 70px;
            height: 70px;
            margin: 0 auto 10px;
            background: white;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(214, 79, 60, 0.2);
            border: 2px solid #D64F3C;
            overflow: hidden;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .logo i {
            font-size: 32px;
            color: #D64F3C;
        }
        
        .logo-wrapper h1 {
            color: #1B4A3C;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }
        
        .logo-wrapper p {
            color: #4A5A54;
            font-size: 13px;
            font-weight: 400;
        }
        
        /* Badge untuk role */
        .role-badge {
            display: inline-block;
            background: #E7F3EF;
            color: #1B4A3C;
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 8px;
        }
        
        .role-badge i {
            color: #D64F3C;
            margin-right: 4px;
        }
        
        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert.error {
            background: rgba(214, 79, 60, 0.1);
            color: #D64F3C;
            border-left: 4px solid #D64F3C;
        }
        
        .alert.success {
            background: rgba(42, 157, 143, 0.1);
            color: #2A9D8F;
            border-left: 4px solid #2A9D8F;
        }
        
        .alert i {
            font-size: 14px;
        }
        
        /* ===== TOAST NOTIFICATION UNTUK LOGOUT ===== */
        .toast-notification {
            position: fixed;
            top: 30px;
            right: 30px;
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 15px;
            max-width: 350px;
            animation: slideInRight 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border-left: 6px solid #2A9D8F;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .toast-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #2A9D8F, #40BEB0);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
            box-shadow: 0 8px 15px rgba(42, 157, 143, 0.3);
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 800;
            color: #1B4A3C;
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .toast-message {
            color: #4A5A54;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: #7A8A84;
            font-size: 18px;
            cursor: pointer;
            padding: 5px;
            transition: color 0.2s;
        }
        
        .toast-close:hover {
            color: #D64F3C;
        }
        
        /* Form */
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #1B4A3C;
            font-size: 13px;
        }
        
        .form-group label i {
            color: #D64F3C;
            margin-right: 6px;
            font-size: 12px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #7A8A84;
            font-size: 14px;
            transition: color 0.2s;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1.5px solid #E0DAD3;
            border-radius: 14px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            color: #1A2A24;
            background: white;
            height: 46px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #D64F3C;
            box-shadow: 0 0 0 3px rgba(214, 79, 60, 0.1);
        }
        
        .form-control:focus + i {
            color: #D64F3C;
        }
        
        /* Password wrapper */
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper .form-control {
            padding-right: 45px;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7A8A84;
            cursor: pointer;
            font-size: 14px;
            padding: 8px;
            transition: color 0.2s;
        }
        
        .toggle-password:hover {
            color: #D64F3C;
        }
        
        /* Form options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0 20px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #4A5A54;
            font-size: 13px;
            cursor: pointer;
        }
        
        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #D64F3C;
            cursor: pointer;
        }
        
        .forgot-link {
            color: #D64F3C;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .forgot-link:hover {
            color: #B83F2E;
            text-decoration: underline;
        }
        
        /* Login button */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #D64F3C;
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 8px 16px rgba(214, 79, 60, 0.2);
        }
        
        .btn-login:hover {
            background: #B83F2E;
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(214, 79, 60, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login i {
            font-size: 14px;
        }
        
        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid #E0DAD3;
            color: #7A8A84;
            font-size: 11px;
        }
        
        .login-footer a {
            color: #1B4A3C;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
            font-size: 12px;
        }
        
        .login-footer a:hover {
            color: #D64F3C;
        }
        
        /* Shake animation */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }
            20%, 40%, 60%, 80% { transform: translateX(4px); }
        }
        
        .shake {
            animation: shake 0.4s ease;
        }
        
        /* Splash indicator */
        .splash-indicator {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.3);
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 10px;
            backdrop-filter: blur(5px);
            z-index: 100;
            display: <?= $from_splash ? 'flex' : 'none' ?>;
            align-items: center;
            gap: 4px;
        }
        
        .splash-indicator i {
            color: #25D366;
        }
        
        /* ===== PREMIUM PWA BANNER ===== */
        .pwa-banner-premium {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            max-width: 400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 28px;
            padding: 20px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.5);
            z-index: 10000;
            animation: slideUpBanner 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: none;
        }
        
        @keyframes slideUpBanner {
            0% {
                opacity: 0;
                transform: translateY(100px) scale(0.9);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .banner-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .banner-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #1B4A3C, #2A5F4E);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            box-shadow: 0 10px 20px rgba(27,74,60,0.3);
            animation: floatIcon 3s ease-in-out infinite;
        }
        
        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .banner-title {
            flex: 1;
        }
        
        .banner-title h3 {
            font-size: 18px;
            font-weight: 800;
            color: #1B4A3C;
            margin-bottom: 4px;
        }
        
        .banner-title p {
            font-size: 12px;
            color: #4A5A54;
        }
        
        .banner-features {
            display: flex;
            justify-content: space-around;
            margin-bottom: 18px;
            background: #E7F3EF;
            border-radius: 40px;
            padding: 12px 8px;
        }
        
        .banner-feature {
            text-align: center;
            flex: 1;
        }
        
        .banner-feature i {
            font-size: 20px;
            color: #D64F3C;
            margin-bottom: 4px;
            display: block;
        }
        
        .banner-feature span {
            font-size: 10px;
            font-weight: 600;
            color: #1B4A3C;
            display: block;
        }
        
        .banner-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn-install {
            flex: 2;
            background: linear-gradient(135deg, #D64F3C, #FF6B4A);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 8px 20px rgba(214,79,60,0.3);
            transition: all 0.3s;
        }
        
        .btn-install:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(214,79,60,0.4);
        }
        
        .btn-install i {
            font-size: 14px;
        }
        
        .btn-close {
            flex: 1;
            background: #F5F3F0;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            color: #4A5A54;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-close:hover {
            background: #E0DAD3;
        }
        
        .install-progress {
            margin-top: 16px;
            display: none;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #E0DAD3;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .progress-fill {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #D64F3C, #FF8A5C);
            transition: width 0.3s ease;
        }
        
        .progress-text {
            font-size: 11px;
            color: #4A5A54;
            text-align: center;
        }
        
        /* Welcome Banner untuk yang sudah install */
        .welcome-banner {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            max-width: 400px;
            margin: 0 auto;
            background: linear-gradient(135deg, #1B4A3C, #2A5F4E);
            border-radius: 28px;
            padding: 16px 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            z-index: 10000;
            animation: slideUpBanner 0.5s ease;
            border: 1px solid rgba(255,255,255,0.2);
            display: none;
        }
        
        .welcome-content {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
        }
        
        .welcome-content i {
            font-size: 28px;
            color: #FF8A5C;
        }
        
        .welcome-content h4 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        
        .welcome-content p {
            font-size: 12px;
            opacity: 0.9;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .toast-notification {
                top: 20px;
                right: 20px;
                left: 20px;
                max-width: none;
            }
        }
        
        @media (max-width: 380px) {
            .banner-features {
                flex-wrap: wrap;
                gap: 8px;
            }
            .banner-actions {
                flex-direction: column;
            }
        }
        
        @media (max-width: 375px) {
            .login-container { max-width: 340px; }
            .login-card { padding: 24px 20px; }
            .logo { width: 60px; height: 60px; }
            .logo i { font-size: 28px; }
            .logo-wrapper h1 { font-size: 22px; }
            .form-control { padding: 10px 12px 10px 38px; font-size: 13px; height: 44px; }
            .btn-login { padding: 12px; font-size: 14px; }
        }
        
        @media (max-width: 320px) {
            .login-container { max-width: 300px; }
            .login-card { padding: 20px 16px; }
            .logo { width: 55px; height: 55px; }
            .logo i { font-size: 26px; }
            .logo-wrapper h1 { font-size: 20px; }
            .form-control { padding: 9px 10px 9px 35px; font-size: 12px; height: 42px; }
            .btn-login { padding: 11px; font-size: 13px; }
        }
        
        @media (max-height: 500px) and (orientation: landscape) {
            .login-container { max-width: 350px; }
            .login-card { padding: 16px 20px; }
            .logo-wrapper { margin-bottom: 8px; }
            .logo { width: 45px; height: 45px; }
            .logo-wrapper h1 { font-size: 18px; }
            .form-group { margin-bottom: 10px; }
            .form-options { margin: 8px 0 10px; }
            .pwa-banner-premium { bottom: 10px; padding: 12px; }
        }
    </style>
</head>
<body class="<?= isset($_GET['pwa']) || isset($_GET['source']) && $_GET['source'] === 'pwa' ? 'from-pwa' : '' ?>">
    
    <!-- TOAST NOTIFICATION UNTUK LOGOUT -->
    <?php if ($show_logout_toast): ?>
    <div class="toast-notification" id="logoutToast">
        <div class="toast-icon">
            <i class="fas fa-check"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">Logout Berhasil</div>
            <div class="toast-message">Anda telah keluar dari sistem. Sampai jumpa kembali!</div>
        </div>
        <button class="toast-close" onclick="document.getElementById('logoutToast').remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <script>
    // Auto hide toast setelah 5 detik
    setTimeout(function() {
        const toast = document.getElementById('logoutToast');
        if (toast) {
            toast.style.animation = 'slideOutRight 0.5s ease forwards';
            setTimeout(() => {
                if (toast) toast.remove();
            }, 500);
        }
    }, 5000);
    
    // Tambahkan keyframes untuk slideOut
    const style = document.createElement('style');
    style.innerHTML = `
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100px);
            }
        }
    `;
    document.head.appendChild(style);
    </script>
    <?php endif; ?>
    
    <!-- Splash Indicator (hanya muncul jika dari splash) -->
    <div class="splash-indicator">
        <i class="fas fa-rocket"></i> Dari Lead Engine
    </div>
    
    <!-- Background Slideshow -->
    <div class="bg-slideshow">
        <?php 
        $valid_bgs = array_filter($background_exists);
        if (empty($valid_bgs)): 
        ?>
            <div class="bg-slide active" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);"></div>
        <?php else: 
            foreach ($background_exists as $index => $bg): 
                if (!empty($bg)):
        ?>
        <div class="bg-slide <?= $index === $current_bg_index ? 'active' : '' ?>" 
             style="background-image: url('<?= $bg ?>');"></div>
        <?php 
                endif;
            endforeach;
        endif; 
        ?>
    </div>
    <div class="overlay"></div>
    
    <!-- Login Container -->
    <div class="login-container">
        <div class="login-card <?= $error ? 'shake' : '' ?>">
            <!-- Logo -->
            <div class="logo-wrapper">
                <div class="logo">
                    <img src="/assets/images/lp_logo.png" alt="LeadProperti" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <i class="fas fa-home" style="display: none;"></i>
                </div>
                <h1>LeadProperti</h1>
                <p>Lead Engine Property Agency</p>
                <!-- Badge informasi -->
                <div class="role-badge">
                    <i class="fas fa-users"></i> Admin • Manager • Developer • Manager Developer • Finance • Marketing
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if ($error): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i>
                <?= $success ?>
            </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" id="loginForm">
                <?php if ($redirect): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                <?php endif; ?>
                
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username / Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" id="username" class="form-control" value="<?= htmlspecialchars($username) ?>" placeholder="Masukkan username" required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember" checked>
                        <span>Ingat saya</span>
                    </label>
                    <a href="#" class="forgot-link" onclick="showResetInfo()">
                        <i class="fas fa-key"></i> Lupa password?
                    </a>
                </div>
                
                <button type="submit" class="btn-login">
                    <span>MASUK DASHBOARD</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            
            <!-- Footer -->
            <div class="login-footer">
                <p>© <?= date('Y') ?> LeadProperti.com - Lead Engine v23.0 (Multi Role System)</p>
                <p><a href="/"><i class="fas fa-home"></i> Kembali ke Beranda</a></p>
            </div>
        </div>
    </div>
    
    <!-- PREMIUM PWA BANNER - HANYA UNTUK DIRECT LINK DAN BELUM INSTALL -->
    <?php if ($is_direct_link): ?>
    <div class="pwa-banner-premium" id="pwaBannerPremium">
        <div class="banner-header">
            <div class="banner-icon">
                <i class="fas fa-rocket"></i>
            </div>
            <div class="banner-title">
                <h3>Install Lead Engine</h3>
                <p>Akses lebih cepat & notifikasi real-time</p>
            </div>
        </div>
        
        <div class="banner-features">
            <div class="banner-feature">
                <i class="fas fa-bolt"></i>
                <span>Akses Cepat</span>
            </div>
            <div class="banner-feature">
                <i class="fas fa-bell"></i>
                <span>Notifikasi</span>
            </div>
            <div class="banner-feature">
                <i class="fas fa-wifi-slash"></i>
                <span>Offline Mode</span>
            </div>
        </div>
        
        <div class="banner-actions">
            <button class="btn-install" id="installButton">
                <i class="fas fa-download"></i> INSTALL SEKARANG
            </button>
            <button class="btn-close" onclick="dismissPWA()">
                Nanti
            </button>
        </div>
        
        <div class="install-progress" id="installProgress">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <p class="progress-text" id="progressText">Mempersiapkan instalasi...</p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- WELCOME BANNER UNTUK YANG SUDAH INSTALL -->
    <div class="welcome-banner" id="welcomeBanner">
        <div class="welcome-content">
            <i class="fas fa-check-circle"></i>
            <div>
                <h4>Lead Engine Terinstall</h4>
                <p>Buka dari home screen untuk akses terbaik</p>
            </div>
        </div>
    </div>
    
    <!-- Reset Info Modal -->
    <div style="display: none;" id="resetInfo">
        <div style="background: white; border-radius: 24px; padding: 24px; max-width: 280px; color: #1A2A24; text-align: center;">
            <i class="fas fa-whatsapp" style="font-size: 48px; color: #25D366; margin-bottom: 16px;"></i>
            <h3 style="margin-bottom: 12px; color: #1B4A3C; font-size: 18px;">Reset Password</h3>
            <p style="margin-bottom: 16px; color: #4A5A54; font-size: 13px;">Hubungi admin via WhatsApp untuk reset password:</p>
            <p style="font-size: 18px; font-weight: 700; color: #D64F3C; margin-bottom: 8px;">628133150078</p>
            <p style="font-size: 11px; color: #7A8A84;">Admin akan mereset dalam 1x24 jam</p>
        </div>
    </div>
    
    <script>
    // ===== FUNGSI-FUNGSI DASAR =====
    // Toggle password visibility
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.querySelector('.toggle-password i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleBtn.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            toggleBtn.className = 'fas fa-eye';
        }
    }
    
    // Background Slideshow
    const slides = document.querySelectorAll('.bg-slide');
    let currentSlide = 0;
    
    function nextSlide() {
        if (slides.length === 0) return;
        slides[currentSlide].classList.remove('active');
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add('active');
    }
    
    if (slides.length > 1) {
        setInterval(nextSlide, 5000);
    }
    
    // Show reset info
    function showResetInfo() {
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        `;
        
        const resetContent = document.getElementById('resetInfo').innerHTML;
        modal.innerHTML = `
            <div style="animation: fadeInUp 0.3s ease;">
                ${resetContent}
                <button onclick="this.closest('div').parentElement.remove()" style="margin-top: 12px; background: #D64F3C; color: white; border: none; padding: 8px 24px; border-radius: 40px; font-weight: 600; cursor: pointer; width: 100%; font-size: 13px;">Tutup</button>
            </div>
        `;
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        document.body.appendChild(modal);
    }
    
    // ===== FUNGSI PWA =====
    let deferredPrompt;
    let bannerShown = false;
    
    function isPWAInstalled() {
        return (window.matchMedia('(display-mode: standalone)').matches || 
                window.navigator.standalone === true);
    }
    
    // Tampilkan banner yang sesuai
    function showAppropriateBanner() {
        const installBanner = document.getElementById('pwaBannerPremium');
        const welcomeBanner = document.getElementById('welcomeBanner');
        
        if (isPWAInstalled()) {
            if (installBanner) installBanner.style.display = 'none';
            if (welcomeBanner) {
                welcomeBanner.style.display = 'block';
                setTimeout(() => {
                    welcomeBanner.style.display = 'none';
                }, 5000);
            }
        } else {
            <?php if ($is_direct_link): ?>
            if (!localStorage.getItem('pwa-dismissed') && !bannerShown) {
                setTimeout(() => {
                    document.getElementById('pwaBannerPremium').style.display = 'block';
                    bannerShown = true;
                }, 1500);
            }
            <?php endif; ?>
        }
    }
    
    window.dismissPWA = function() {
        const banner = document.getElementById('pwaBannerPremium');
        if (banner) {
            banner.style.display = 'none';
            localStorage.setItem('pwa-dismissed', 'true');
        }
    };
    
    // ===== FUNGSI NOTIFIKASI PWA =====
    async function requestNotificationPermission() {
        if (!('Notification' in window)) {
            console.log('Browser tidak mendukung notifikasi');
            return false;
        }
        
        if (Notification.permission === 'granted') {
            console.log('Izin notifikasi sudah diberikan');
            return true;
        }
        
        if (Notification.permission === 'denied') {
            console.log('Izin notifikasi ditolak');
            return false;
        }
        
        try {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                console.log('Izin notifikasi diberikan');
                new Notification('Lead Engine Property', {
                    body: 'Notifikasi aktif! Anda akan mendapat update lead real-time.',
                    icon: '/assets/images/icon-192.png',
                    badge: '/assets/images/icon-72.png',
                    vibrate: [500, 250, 500]
                });
                return true;
            } else {
                console.log('Izin notifikasi ditolak');
                return false;
            }
        } catch (error) {
            console.error('Error request permission:', error);
            return false;
        }
    }
    
    function sendToSW(message) {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage(message);
            return true;
        }
        return false;
    }
    
    async function setAppBadge(count) {
        if ('setAppBadge' in navigator) {
            try {
                await navigator.setAppBadge(count);
                console.log('Badge set ke', count);
                return true;
            } catch (e) {
                console.log('Gagal set badge:', e);
                return false;
            }
        }
        return false;
    }
    
    async function clearAppBadge() {
        if ('clearAppBadge' in navigator) {
            try {
                await navigator.clearAppBadge();
                console.log('Badge cleared');
                return true;
            } catch (e) {
                console.log('Gagal clear badge:', e);
                return false;
            }
        } else if ('setAppBadge' in navigator) {
            try {
                await navigator.setAppBadge(0);
                console.log('Badge reset ke 0');
                return true;
            } catch (e) {
                return false;
            }
        }
        return false;
    }
    
    function playNotificationSound() {
        const audio = new Audio('/assets/sounds/notification.mp3');
        audio.play().catch(e => console.log('Gagal play sound:', e));
    }
    
    window.testNotifikasi = async function() {
        console.log('🧪 Test notifikasi dimulai...');
        
        if (!('Notification' in window)) {
            alert('Browser tidak mendukung notifikasi');
            return;
        }
        
        if (Notification.permission !== 'granted') {
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') {
                alert('Izin notifikasi ditolak');
                return;
            }
        }
        
        await setAppBadge(5);
        playNotificationSound();
        
        try {
            new Notification('Lead Engine - Test', {
                body: 'Ini adalah test notifikasi',
                icon: '/assets/images/icon-192.png',
                badge: '/assets/images/icon-72.png',
                vibrate: [500, 250, 500],
                silent: false,
                requireInteraction: true
            });
            console.log('✅ Notifikasi via API berhasil');
        } catch (e) {
            console.error('❌ Error notifikasi:', e);
        }
        
        sendToSW({
            type: 'TRIGGER_NOTIFICATION',
            payload: {
                title: 'Lead Engine - Test',
                body: 'Test dari halaman login',
                count: 5
            }
        });
        
        alert('✅ Test notifikasi dijalankan. Cek notifikasi dan badge!');
    };
    
    // ===== SERVICE WORKER REGISTRATION =====
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', async () => {
            console.log('Memulai registrasi Service Worker...');
            
            try {
                const registrations = await navigator.serviceWorker.getRegistrations();
                for (let registration of registrations) {
                    if (registration.active && registration.active.scriptURL.includes('service-worker')) {
                        const unregistered = await registration.unregister();
                        if (unregistered) {
                            console.log('Service Worker lama di-unregister');
                        }
                    }
                }
            } catch (e) {
                console.log('Error unregister:', e);
            }
            
            try {
                const registration = await navigator.serviceWorker.register('/service-worker.js', {
                    scope: '/',
                    updateViaCache: 'none'
                });
                
                console.log('✅ Service Worker registered:', registration);
                
                registration.addEventListener('updatefound', () => {
                    console.log('Update ditemukan untuk Service Worker');
                });
                
                if (registration.active) {
                    console.log('Service Worker aktif');
                    sendToSW({ type: 'LOGIN_PAGE_LOADED' });
                }
                
                setTimeout(() => {
                    requestNotificationPermission();
                }, 2000);
                
            } catch (error) {
                console.log('❌ Service Worker registration failed:', error);
            }
        });
        
        navigator.serviceWorker.addEventListener('message', event => {
            console.log('Message dari Service Worker:', event.data);
            
            if (event.data && event.data.type === 'PUSH_RECEIVED') {
                if (event.data.payload && event.data.payload.count) {
                    setAppBadge(event.data.payload.count);
                }
                playNotificationSound();
            }
        });
        
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            console.log('Service Worker controller berubah');
            window.location.reload();
        });
        
    } else {
        console.log('Service Worker tidak didukung browser ini');
    }
    
    // ===== INIT =====
    document.addEventListener('DOMContentLoaded', function() {
        showAppropriateBanner();
        
        const installBtn = document.getElementById('installButton');
        const progress = document.getElementById('installProgress');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        
        if (installBtn) {
            installBtn.addEventListener('click', async () => {
                if (!deferredPrompt) {
                    alert('Untuk menginstall aplikasi:\n\n1. Buka menu browser (⋮ tiga titik)\n2. Pilih "Add to Home screen"\n3. Klik "Install"');
                    return;
                }
                
                progress.style.display = 'block';
                installBtn.disabled = true;
                installBtn.style.opacity = '0.5';
                
                let width = 0;
                const interval = setInterval(() => {
                    width += 10;
                    progressFill.style.width = width + '%';
                    
                    if (width >= 100) {
                        clearInterval(interval);
                        progressText.textContent = 'Menginstall...';
                    }
                }, 100);
                
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                
                if (outcome === 'accepted') {
                    progressText.textContent = 'Installasi berhasil!';
                    setTimeout(() => {
                        document.getElementById('pwaBannerPremium').style.display = 'none';
                        showAppropriateBanner();
                    }, 1000);
                } else {
                    progress.style.display = 'none';
                    installBtn.disabled = false;
                    installBtn.style.opacity = '1';
                    progressFill.style.width = '0%';
                }
                
                deferredPrompt = null;
            });
        }
        
        if (isPWAInstalled()) {
            console.log('✅ Aplikasi berjalan dalam mode PWA (standalone)');
            
            setTimeout(() => {
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission();
                }
            }, 1000);
        }
        
        // Tampilkan tombol test jika ada parameter debug
        const testBtn = document.createElement('button');
        testBtn.innerHTML = '🔔 Test Notifikasi';
        testBtn.style.cssText = `
            position: fixed;
            bottom: 100px;
            right: 20px;
            background: #D64F3C;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            z-index: 9999;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(214,79,60,0.3);
            display: none;
        `;
        testBtn.onclick = window.testNotifikasi;
        
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('debug')) {
            testBtn.style.display = 'block';
            document.body.appendChild(testBtn);
        }
    });
    
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        console.log('✅ beforeinstallprompt fired');
        
        <?php if ($is_direct_link): ?>
        if (!isPWAInstalled() && !bannerShown && !localStorage.getItem('pwa-dismissed')) {
            setTimeout(() => {
                document.getElementById('pwaBannerPremium').style.display = 'block';
                bannerShown = true;
            }, 1500);
        }
        <?php endif; ?>
    });
    
    window.addEventListener('appinstalled', () => {
        console.log('✅ PWA installed');
        localStorage.removeItem('pwa-dismissed');
        showAppropriateBanner();
    });
    
    // Prevent form resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
</body>
</html>