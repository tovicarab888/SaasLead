<?php
/**
 * GET_CLUSTERS.PHP - LEADENGINE API
 * Version: 1.0.0 - Ambil daftar cluster per developer
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_clusters.log');

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
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Rate limiting
$client_ip = getClientIP();
if (!checkRateLimit('get_clusters_' . $client_ip, 30, 60)) {
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

// Tentukan developer_id berdasarkan role
$developer_id = 0;
$current_role = getCurrentRole();

if (isDeveloper()) {
    $developer_id = $_SESSION['user_id'];
} elseif (isMarketing()) {
    $developer_id = $_SESSION['marketing_developer_id'] ?? 0;
} elseif (in_array($current_role, ['admin', 'manager'])) {
    $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
} elseif (in_array($current_role, ['manager_developer', 'finance'])) {
    $developer_id = $_SESSION['developer_id'] ?? 0;
}

if ($developer_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Developer ID tidak valid']);
    exit();
}

// Validasi developer
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'developer' AND is_active = 1");
$stmt->execute([$developer_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Developer tidak ditemukan']);
    exit();
}

try {
    // Ambil semua cluster milik developer
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.nama_cluster,
            c.deskripsi,
            c.is_active,
            c.created_at,
            c.updated_at,
            COUNT(DISTINCT b.id) as total_blocks,
            COUNT(DISTINCT u.id) as total_units,
            COUNT(DISTINCT CASE WHEN u.status = 'AVAILABLE' THEN u.id END) as available_units,
            COUNT(DISTINCT CASE WHEN u.status = 'BOOKED' THEN u.id END) as booked_units,
            COUNT(DISTINCT CASE WHEN u.status = 'SOLD' THEN u.id END) as sold_units,
            (SELECT COUNT(*) FROM units WHERE cluster_id = c.id AND program = 'Subsidi') as subsidi_units,
            (SELECT COUNT(*) FROM units WHERE cluster_id = c.id AND program = 'Komersil') as komersil_units
        FROM clusters c
        LEFT JOIN blocks b ON c.id = b.cluster_id
        LEFT JOIN units u ON c.id = u.cluster_id
        WHERE c.developer_id = ?
        GROUP BY c.id
        ORDER BY c.nama_cluster ASC
    ");
    $stmt->execute([$developer_id]);
    $clusters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data
    foreach ($clusters as &$cluster) {
        $cluster['total_blocks'] = (int)$cluster['total_blocks'];
        $cluster['total_units'] = (int)$cluster['total_units'];
        $cluster['available_units'] = (int)$cluster['available_units'];
        $cluster['booked_units'] = (int)$cluster['booked_units'];
        $cluster['sold_units'] = (int)$cluster['sold_units'];
        $cluster['subsidi_units'] = (int)$cluster['subsidi_units'];
        $cluster['komersil_units'] = (int)$cluster['komersil_units'];
        
        // Hitung progress penjualan
        $cluster['progress'] = $cluster['total_units'] > 0 
            ? round(($cluster['sold_units'] / $cluster['total_units']) * 100, 1)
            : 0;
    }

    // Statistik total
    $total_units = array_sum(array_column($clusters, 'total_units'));
    $total_sold = array_sum(array_column($clusters, 'sold_units'));
    $total_available = array_sum(array_column($clusters, 'available_units'));
    $total_booked = array_sum(array_column($clusters, 'booked_units'));

    echo json_encode([
        'success' => true,
        'data' => $clusters,
        'stats' => [
            'total_clusters' => count($clusters),
            'total_units' => $total_units,
            'available' => $total_available,
            'booked' => $total_booked,
            'sold' => $total_sold,
            'progress' => $total_units > 0 ? round(($total_sold / $total_units) * 100, 1) : 0
        ],
        'developer_id' => $developer_id,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    error_log("Error in get_clusters: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>