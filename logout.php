<?php
/**
 * LOGOUT.PHP - TAUFIKMARIE.COM
 * Version: 3.0.0 - DENGAN TOAST NOTIFICATION UI
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua data session
$_SESSION = array();

// Hapus session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hapus remember token cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Hancurkan session
session_destroy();

// Redirect ke login dengan parameter success
header('Location: login.php?logout=success');
exit();
?>