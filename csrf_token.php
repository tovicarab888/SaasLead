<?php
/**
 * CSRF_TOKEN.PHP - TAUFIKMARIE.COM
 * Version: 3.0.0 - STANDARD (UNTUK LOGIN SAJA)
 * FULL CODE - 100% LENGKAP
 */

// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://taufikmarie.com');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Generate token untuk form login
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode([
    'success' => true,
    'csrf_token' => $_SESSION['csrf_token'],
    'session_id' => session_id()
]);
?>