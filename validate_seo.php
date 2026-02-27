<?php
/**
 * VALIDATE_SEO.PHP - VALIDASI SEO REAL-TIME
 * Version: 1.0.0
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

$field = $_GET['field'] ?? $_POST['field'] ?? '';
$value = $_GET['value'] ?? $_POST['value'] ?? '';

$result = [
    'valid' => true,
    'message' => '',
    'length' => 0,
    'max_length' => 0
];

switch ($field) {
    case 'seo_title':
        $result['max_length'] = 60;
        $result['length'] = strlen($value);
        if (empty($value)) {
            $result['valid'] = false;
            $result['message'] = 'SEO Title wajib diisi';
        } elseif ($result['length'] > 60) {
            $result['valid'] = false;
            $result['message'] = 'Terlalu panjang! Maksimal 60 karakter';
        } elseif ($result['length'] < 10) {
            $result['valid'] = false;
            $result['message'] = 'Terlalu pendek! Minimal 10 karakter untuk SEO';
        }
        break;
        
    case 'seo_description':
        $result['max_length'] = 160;
        $result['length'] = strlen($value);
        if (empty($value)) {
            $result['valid'] = false;
            $result['message'] = 'Meta Description wajib diisi';
        } elseif ($result['length'] > 160) {
            $result['valid'] = false;
            $result['message'] = 'Terlalu panjang! Maksimal 160 karakter';
        } elseif ($result['length'] < 50) {
            $result['valid'] = false;
            $result['message'] = 'Terlalu pendek! Minimal 50 karakter untuk deskripsi';
        }
        break;
        
    case 'canonical_url':
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $result['valid'] = false;
            $result['message'] = 'URL tidak valid';
        }
        break;
        
    case 'og_image':
    case 'twitter_image':
        if (!empty($value)) {
            if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $value)) {
                $result['valid'] = false;
                $result['message'] = 'File harus gambar (jpg, png, gif, webp)';
            }
        }
        break;
}

echo json_encode($result);