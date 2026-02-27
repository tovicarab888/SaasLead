<?php
/**
 * GET_MARKETING_BY_DEVELOPER.PHP - LEADENGINE API
 * Version: 2.0.0 - FIXED: Validasi developer_id + Data lengkap
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/get_marketing.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
if (!checkAuth() && !isMarketing()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Silakan login terlebih dahulu']);
    exit();
}

// Rate limiting
$client_ip = getClientIP();
if (!checkRateLimit('get_marketing_' . $client_ip, 30, 60)) {
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

// Tentukan developer_id berdasarkan parameter dan role
$requested_dev_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
$current_role = getCurrentRole();
$current_user_id = $_SESSION['user_id'] ?? 0;
$developer_id = 0;

// Validasi akses
if ($current_role === 'admin' || $current_role === 'manager') {
    // Admin/manager bisa lihat semua developer
    $developer_id = $requested_dev_id;
} elseif ($current_role === 'developer') {
    // Developer hanya bisa lihat marketing miliknya sendiri
    $developer_id = $current_user_id;
} elseif ($current_role === 'manager_developer' || $current_role === 'finance') {
    // Manager developer/finance hanya bisa lihat marketing di developernya
    $developer_id = $_SESSION['developer_id'] ?? 0;
} elseif ($current_role === 'marketing') {
    // Marketing hanya bisa lihat marketing di developernya (untuk keperluan tertentu)
    $developer_id = $_SESSION['marketing_developer_id'] ?? 0;
}

if ($developer_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Developer ID tidak valid',
        'debug' => [
            'requested' => $requested_dev_id,
            'calculated' => $developer_id,
            'role' => $current_role
        ]
    ]);
    exit();
}

// Validasi developer exists dan aktif
$stmt = $conn->prepare("SELECT id, nama_lengkap FROM users WHERE id = ? AND role = 'developer' AND is_active = 1");
$stmt->execute([$developer_id]);
$developer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$developer) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Developer tidak ditemukan atau tidak aktif']);
    exit();
}

try {
    // Ambil data marketing dengan informasi lengkap
    $stmt = $conn->prepare("
        SELECT 
            m.id,
            m.nama_lengkap,
            m.phone,
            m.username,
            m.is_active,
            m.last_login,
            m.last_assigned,
            m.bank_id,
            m.nomor_rekening,
            m.atas_nama_rekening,
            m.nama_bank_rekening,
            m.rekening_verified,
            m.profile_photo,
            m.created_at,
            mt.id as marketing_type_id,
            mt.type_name as marketing_type_name,
            mt.can_book,
            mt.can_canvasing,
            mt.commission_type,
            mt.commission_value,
            (SELECT COUNT(*) FROM leads WHERE assigned_marketing_team_id = m.id) as total_leads,
            (SELECT COUNT(*) FROM leads WHERE assigned_marketing_team_id = m.id AND status IN ('Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun')) as total_deal,
            (SELECT COUNT(*) FROM canvasing_logs WHERE marketing_id = m.id) as total_canvasing
        FROM marketing_team m
        LEFT JOIN marketing_types mt ON m.marketing_type_id = mt.id
        WHERE m.developer_id = ?
        ORDER BY 
            m.is_active DESC,
            m.nama_lengkap ASC
    ");
    $stmt->execute([$developer_id]);
    $marketing_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data
    foreach ($marketing_list as &$marketing) {
        $marketing['total_leads'] = (int)$marketing['total_leads'];
        $marketing['total_deal'] = (int)$marketing['total_deal'];
        $marketing['total_canvasing'] = (int)$marketing['total_canvasing'];
        
        // Format last_login
        if ($marketing['last_login']) {
            $marketing['last_login_formatted'] = date('d/m/Y H:i', strtotime($marketing['last_login']));
        }
        
        // Sembunyikan data sensitif kecuali untuk role tertentu
        if (!in_array($current_role, ['admin', 'manager', 'finance'])) {
            unset($marketing['bank_id']);
            unset($marketing['nomor_rekening']);
            unset($marketing['atas_nama_rekening']);
            unset($marketing['nama_bank_rekening']);
        } else {
            // Mask nomor rekening untuk keamanan
            if (!empty($marketing['nomor_rekening'])) {
                $len = strlen($marketing['nomor_rekening']);
                $marketing['nomor_rekening_masked'] = substr($marketing['nomor_rekening'], 0, 4) . str_repeat('*', $len - 8) . substr($marketing['nomor_rekening'], -4);
            }
        }
        
        // URL foto profil
        if (!empty($marketing['profile_photo'])) {
            $marketing['profile_photo_url'] = SITE_URL . '/uploads/profiles/' . $marketing['profile_photo'];
        }
    }

    // Hitung statistik
    $stats = [
        'total_marketing' => count($marketing_list),
        'aktif' => 0,
        'nonaktif' => 0,
        'total_leads' => 0,
        'total_deal' => 0,
        'total_canvasing' => 0
    ];

    foreach ($marketing_list as $m) {
        if ($m['is_active']) {
            $stats['aktif']++;
        } else {
            $stats['nonaktif']++;
        }
        $stats['total_leads'] += $m['total_leads'];
        $stats['total_deal'] += $m['total_deal'];
        $stats['total_canvasing'] += $m['total_canvasing'];
    }

    echo json_encode([
        'success' => true,
        'data' => $marketing_list,
        'stats' => $stats,
        'developer' => [
            'id' => $developer['id'],
            'nama' => $developer['nama_lengkap']
        ],
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    error_log("Error in get_marketing_by_developer: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>