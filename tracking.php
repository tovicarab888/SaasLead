<?php
/**
 * TRACKING.PHP - REDIRECT TO SETTINGS
 * Version: 1.0.0 - Redirect ke halaman Settings tab Tracking
 * FULL CODE - 100% LENGKAP
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// ========== CHECK AUTH ==========
if (!checkAuth()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Hanya admin yang bisa akses halaman ini
if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Admin.');
}

// Redirect ke settings dengan tab tracking
header('Location: settings.php?tab=tracking');
exit();
?>