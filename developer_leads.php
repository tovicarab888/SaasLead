<?php
/**
 * DEVELOPER_LEADS.PHP - TAUFIKMARIE.COM
 * Version: 4.0.0 - FIXED: Admin access validation, marketing filter, CSV enclosure
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/developer_leads.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Rate limiting
$client_ip = getClientIP();
if (!checkRateLimit('developer_leads_' . $client_ip, 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']);
    exit();
}

// Cek session login
if (!checkAuth()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized - Silakan login terlebih dahulu'
    ]);
    exit();
}

// Cek role
$current_role = getCurrentRole();
$current_user_id = $_SESSION['user_id'] ?? 0;

// Validasi akses dasar
if (!in_array($current_role, ['admin', 'manager', 'developer', 'manager_developer', 'finance'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden - Role tidak valid'
    ]);
    exit();
}

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ========== VALIDASI AKSES ADMIN/MANAGER KE DEVELOPER ==========
function validateDeveloperAccess($conn, $current_role, $current_user_id, $target_dev_id) {
    if ($target_dev_id <= 0) {
        return ['valid' => false, 'message' => 'Developer ID tidak valid'];
    }
    
    if (in_array($current_role, ['admin', 'manager'])) {
        // Admin/manager bisa akses semua developer
        $stmt = $conn->prepare("SELECT id, nama_lengkap FROM users WHERE id = ? AND role = 'developer' AND is_active = 1");
        $stmt->execute([$target_dev_id]);
        $developer = $stmt->fetch();
        
        if (!$developer) {
            return ['valid' => false, 'message' => 'Developer tidak ditemukan atau tidak aktif'];
        }
        
        return ['valid' => true, 'developer' => $developer];
        
    } elseif ($current_role === 'developer') {
        // Developer hanya bisa akses dirinya sendiri
        if ($target_dev_id != $current_user_id) {
            return ['valid' => false, 'message' => 'Anda hanya bisa mengakses data sendiri'];
        }
        
        $stmt = $conn->prepare("SELECT id, nama_lengkap, location_access FROM users WHERE id = ? AND role = 'developer' AND is_active = 1");
        $stmt->execute([$target_dev_id]);
        $developer = $stmt->fetch();
        
        if (!$developer) {
            return ['valid' => false, 'message' => 'Developer tidak ditemukan'];
        }
        
        return ['valid' => true, 'developer' => $developer];
        
    } elseif (in_array($current_role, ['manager_developer', 'finance'])) {
        // Manager developer/finance hanya bisa akses developernya sendiri
        $user_dev_id = $_SESSION['developer_id'] ?? 0;
        
        if ($target_dev_id != $user_dev_id) {
            return ['valid' => false, 'message' => 'Anda hanya bisa mengakses data developer Anda sendiri'];
        }
        
        $stmt = $conn->prepare("SELECT id, nama_lengkap, location_access FROM users WHERE id = ? AND role = 'developer' AND is_active = 1");
        $stmt->execute([$target_dev_id]);
        $developer = $stmt->fetch();
        
        if (!$developer) {
            return ['valid' => false, 'message' => 'Developer tidak ditemukan'];
        }
        
        return ['valid' => true, 'developer' => $developer];
    }
    
    return ['valid' => false, 'message' => 'Akses ditolak'];
}

switch ($action) {
    case 'get_leads':
        getDeveloperLeads($conn, $current_role, $current_user_id);
        break;
    case 'get_stats':
        getDeveloperStats($conn, $current_role, $current_user_id);
        break;
    case 'export':
        exportDeveloperLeads($conn, $current_role, $current_user_id);
        break;
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
}

// ========== GET DEVELOPER LEADS ==========
function getDeveloperLeads($conn, $current_role, $current_user_id) {
    // Tentukan target developer ID
    $target_dev_id = 0;
    
    if ($current_role === 'developer') {
        $target_dev_id = $current_user_id;
    } elseif (in_array($current_role, ['admin', 'manager'])) {
        $target_dev_id = isset($_GET['dev_id']) ? (int)$_GET['dev_id'] : 0;
        
        if ($target_dev_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parameter dev_id wajib untuk admin/manager'
            ]);
            return;
        }
    } elseif (in_array($current_role, ['manager_developer', 'finance'])) {
        $target_dev_id = $_SESSION['developer_id'] ?? 0;
        
        if ($target_dev_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Developer ID tidak ditemukan di session'
            ]);
            return;
        }
    }
    
    // Validasi akses
    $access = validateDeveloperAccess($conn, $current_role, $current_user_id, $target_dev_id);
    if (!$access['valid']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $access['message']]);
        return;
    }
    
    $developer = $access['developer'];
    
    // Dapatkan lokasi yang diassign ke developer target
    $location_access = $developer['location_access'] ?? '';
    $locations = explode(',', $location_access);
    $locations = array_map('trim', $locations);
    $locations = array_filter($locations);
    
    // Handle jika lokasi kosong
    if (empty($locations)) {
        $locations = [''];
    }
    
    $placeholders = implode(',', array_fill(0, count($locations), '?'));
    
    // Ambil parameter filter
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Bangun query dasar
    $sql = "SELECT 
        l.*, 
        loc.display_name as location_display, 
        loc.icon,
        -- Marketing Internal
        m.nama_lengkap as marketing_name,
        m.phone as marketing_phone,
        -- Marketing External
        u_external.nama_lengkap as external_marketing_name,
        u_external.phone as external_marketing_phone,
        -- Tampilkan nama marketing berdasarkan tipe
        CASE 
            WHEN l.assigned_type = 'internal' THEN m.nama_lengkap
            WHEN l.assigned_type = 'external' THEN u_external.nama_lengkap
            ELSE '-'
        END as marketing_display,
        l.assigned_type
        FROM leads l 
        LEFT JOIN locations loc ON l.location_key = loc.location_key 
        LEFT JOIN marketing_team m ON l.assigned_marketing_team_id = m.id
        LEFT JOIN users u_external ON l.assigned_marketing_team_id = u_external.id AND u_external.role = 'marketing_external'
        WHERE l.location_key IN ($placeholders)
        AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";
    
    $params = $locations;
    
    if ($search) {
        $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? OR CONCAT(l.first_name, ' ', l.last_name) LIKE ?)";
        $s = "%$search%";
        $params = array_merge($params, [$s, $s, $s, $s, $s]);
    }
    
    if ($status) {
        $sql .= " AND l.status = ?";
        $params[] = $status;
    }
    
    if ($marketing_id > 0) {
        $sql .= " AND l.assigned_marketing_team_id = ?";
        $params[] = $marketing_id;
    }
    
    // Count total
    $count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as tmp";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Get data dengan pagination
    $sql .= " ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $leads,
        'developer' => [
            'id' => $developer['id'],
            'name' => $developer['nama_lengkap']
        ],
        'pagination' => [
            'page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'limit' => $limit
        ]
    ]);
}

// ========== GET DEVELOPER STATS ==========
function getDeveloperStats($conn, $current_role, $current_user_id) {
    // Tentukan target developer ID
    $target_dev_id = 0;
    
    if ($current_role === 'developer') {
        $target_dev_id = $current_user_id;
    } elseif (in_array($current_role, ['admin', 'manager'])) {
        $target_dev_id = isset($_GET['dev_id']) ? (int)$_GET['dev_id'] : 0;
        
        if ($target_dev_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parameter dev_id wajib untuk admin/manager'
            ]);
            return;
        }
    } elseif (in_array($current_role, ['manager_developer', 'finance'])) {
        $target_dev_id = $_SESSION['developer_id'] ?? 0;
    }
    
    // Validasi akses
    $access = validateDeveloperAccess($conn, $current_role, $current_user_id, $target_dev_id);
    if (!$access['valid']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $access['message']]);
        return;
    }
    
    $developer = $access['developer'];
    
    // Dapatkan lokasi yang diassign ke developer target
    $location_access = $developer['location_access'] ?? '';
    $locations = explode(',', $location_access);
    $locations = array_map('trim', $locations);
    $locations = array_filter($locations);
    
    if (empty($locations)) {
        $locations = [''];
    }
    
    $placeholders = implode(',', array_fill(0, count($locations), '?'));
    
    $stats = [];
    
    try {
        // Total leads
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE location_key IN ($placeholders) AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
        $stmt->execute($locations);
        $stats['total'] = (int)$stmt->fetchColumn();
        
        // Today
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE location_key IN ($placeholders) AND DATE(created_at) = CURDATE() AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
        $stmt->execute($locations);
        $stats['today'] = (int)$stmt->fetchColumn();
        
        // This week
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE location_key IN ($placeholders) AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
        $stmt->execute($locations);
        $stats['week'] = (int)$stmt->fetchColumn();
        
        // This month
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leads WHERE location_key IN ($placeholders) AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
        $stmt->execute($locations);
        $stats['month'] = (int)$stmt->fetchColumn();
        
        // Status counts
        $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM leads WHERE location_key IN ($placeholders) AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') GROUP BY status");
        $stmt->execute($locations);
        $status_counts = $stmt->fetchAll();
        
        $stats['status'] = [];
        foreach ($status_counts as $row) {
            $stats['status'][$row['status']] = (int)$row['count'];
        }
        
        // Score stats (hot, warm, cold)
        $stmt = $conn->prepare("SELECT 
            COUNT(CASE WHEN status IN ('Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun') THEN 1 END) as hot,
            COUNT(CASE WHEN status IN ('Booking', 'Survey', 'Follow Up') THEN 1 END) as warm,
            COUNT(CASE WHEN status IN ('Tolak Slik', 'Tidak Minat', 'Batal') THEN 1 END) as cold
            FROM leads WHERE location_key IN ($placeholders) AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
        $stmt->execute($locations);
        $score = $stmt->fetch();
        
        $stats['hot'] = (int)($score['hot'] ?? 0);
        $stats['warm'] = (int)($score['warm'] ?? 0);
        $stats['cold'] = (int)($score['cold'] ?? 0);
        
        // Tambah marketing count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM marketing_team WHERE developer_id = ? AND is_active = 1");
        $stmt->execute([$target_dev_id]);
        $stats['total_marketing'] = (int)$stmt->fetchColumn();
        
        $stats['developer_name'] = $developer['nama_lengkap'];
        $stats['developer_id'] = $developer['id'];
        $stats['location_count'] = count(array_filter($locations));
        
    } catch (Exception $e) {
        error_log("Error in getDeveloperStats: " . $e->getMessage());
        $stats = [
            'total' => 0, 'today' => 0, 'week' => 0, 'month' => 0,
            'hot' => 0, 'warm' => 0, 'cold' => 0, 'status' => [],
            'developer_name' => $developer['nama_lengkap'] ?? 'Unknown',
            'developer_id' => $developer['id'] ?? 0,
            'location_count' => 0,
            'total_marketing' => 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}

// ========== EXPORT DEVELOPER LEADS ==========
function exportDeveloperLeads($conn, $current_role, $current_user_id) {
    // Tentukan target developer ID
    $target_dev_id = 0;
    
    if ($current_role === 'developer') {
        $target_dev_id = $current_user_id;
    } elseif (in_array($current_role, ['admin', 'manager'])) {
        $target_dev_id = isset($_GET['dev_id']) ? (int)$_GET['dev_id'] : 0;
        
        if ($target_dev_id <= 0) {
            die('Parameter dev_id wajib untuk admin/manager');
        }
    } elseif (in_array($current_role, ['manager_developer', 'finance'])) {
        $target_dev_id = $_SESSION['developer_id'] ?? 0;
    }
    
    // Validasi akses
    $access = validateDeveloperAccess($conn, $current_role, $current_user_id, $target_dev_id);
    if (!$access['valid']) {
        die($access['message']);
    }
    
    $developer = $access['developer'];
    $developer_name = $developer['nama_lengkap'];
    $location_access = $developer['location_access'] ?? '';
    
    $locations = explode(',', $location_access);
    $locations = array_map('trim', $locations);
    $locations = array_filter($locations);
    
    if (empty($locations)) {
        die('Developer tidak memiliki akses lokasi');
    }
    
    $placeholders = implode(',', array_fill(0, count($locations), '?'));
    
    $format = $_GET['format'] ?? 'csv';
    $marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Ambil semua data
    $sql = "SELECT l.*, 
            loc.display_name as location_display, 
            loc.icon,
            m.nama_lengkap as marketing_name,
            m.phone as marketing_phone,
            u.nomor_unit,
            u.tipe_unit,
            u.program as unit_program,
            u.status as unit_status,
            u.harga as unit_harga
            FROM leads l 
            LEFT JOIN locations loc ON l.location_key = loc.location_key 
            LEFT JOIN marketing_team m ON l.assigned_marketing_team_id = m.id
            LEFT JOIN units u ON l.id = u.lead_id
            WHERE l.location_key IN ($placeholders)
            AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";
    
    $params = $locations;
    
    if ($marketing_id > 0) {
        $sql .= " AND l.assigned_marketing_team_id = ?";
        $params[] = $marketing_id;
    }
    
    if (!empty($status)) {
        $sql .= " AND l.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY l.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();
    
    if ($format === 'csv') {
        // Export CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads_' . $developer_name . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        
        // Header CSV
        fputcsv($output, [
            'ID', 'Nama Depan', 'Nama Belakang', 'Nama Lengkap', 'WhatsApp', 'Email',
            'Lokasi', 'Kota', 'Tipe Unit', 'Program', 'Status', 'Score',
            'Sumber', 'Tanggal', 'Marketing', 'Catatan', 'Unit Terbooking',
            'Tipe Unit (Booking)', 'Harga Unit'
        ]);
        
        foreach ($leads as $lead) {
            $full_name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
            $marketing = $lead['marketing_name'] ?? ($lead['assigned_marketing_team_id'] ? 'Marketing #' . $lead['assigned_marketing_team_id'] : '');
            
            // Escape special characters
            $first_name = str_replace(['"', ','], '', $lead['first_name']);
            $last_name = str_replace(['"', ','], '', $lead['last_name'] ?? '');
            $full_name = str_replace(['"', ','], '', $full_name);
            $email = str_replace(['"', ','], '', $lead['email'] ?? '');
            $notes = str_replace(['"', ','], '', $lead['notes'] ?? '');
            $marketing = str_replace(['"', ','], '', $marketing);
            
            fputcsv($output, [
                $lead['id'],
                $first_name,
                $last_name,
                $full_name,
                $lead['phone'],
                $email,
                $lead['location_display'] ?? $lead['location_key'],
                $lead['city'] ?? '',
                $lead['unit_type'] ?? 'Type 36/60',
                $lead['program'] ?? 'Subsidi',
                $lead['status'] ?? 'Baru',
                $lead['lead_score'] ?? 0,
                $lead['source'] ?? 'website',
                date('Y-m-d', strtotime($lead['created_at'])),
                $marketing,
                $notes,
                $lead['nomor_unit'] ?? '',
                $lead['tipe_unit'] ?? '',
                $lead['unit_harga'] ?? ''
            ], ',', '"');
        }
        
        fclose($output);
        exit();
        
    } elseif ($format === 'excel') {
        // Export Excel
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads_' . $developer_name . '_' . date('Y-m-d') . '.xls"');
        
        echo '<html>';
        echo '<head><meta charset="UTF-8">';
        echo '<style>
            th { background: #1B4A3C; color: white; padding: 8px; }
            td { padding: 6px; border: 1px solid #ccc; }
        </style>';
        echo '</head><body>';
        
        echo '<h2>Data Leads - ' . htmlspecialchars($developer_name) . '</h2>';
        echo '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . ' | Total: ' . count($leads) . '</p>';
        
        echo '<table border="1">';
        echo '<tr>
                <th>ID</th>
                <th>Nama</th>
                <th>WhatsApp</th>
                <th>Email</th>
                <th>Lokasi</th>
                <th>Status</th>
                <th>Score</th>
                <th>Marketing</th>
                <th>Tanggal</th>
                <th>Unit</th>
              </tr>';
        
        foreach ($leads as $lead) {
            $full_name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
            $marketing = $lead['marketing_name'] ?? ($lead['assigned_marketing_team_id'] ? 'Marketing #' . $lead['assigned_marketing_team_id'] : '');
            
            echo '<tr>
                    <td>' . $lead['id'] . '</td>
                    <td>' . htmlspecialchars($full_name) . '</td>
                    <td>' . htmlspecialchars($lead['phone']) . '</td>
                    <td>' . htmlspecialchars($lead['email'] ?? '') . '</td>
                    <td>' . htmlspecialchars($lead['location_display'] ?? $lead['location_key']) . '</td>
                    <td>' . htmlspecialchars($lead['status'] ?? 'Baru') . '</td>
                    <td>' . $lead['lead_score'] . '</td>
                    <td>' . htmlspecialchars($marketing) . '</td>
                    <td>' . date('d/m/Y', strtotime($lead['created_at'])) . '</td>
                    <td>' . htmlspecialchars($lead['nomor_unit'] ?? '-') . '</td>
                  </tr>';
        }
        
        echo '</table>';
        echo '</body></html>';
        exit();
    }
    
    die('Format tidak didukung');
}
?>