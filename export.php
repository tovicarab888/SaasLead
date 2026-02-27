<?php
/**
 * EXPORT.PHP - TAUFIKMARIE.COM
 * Version: 6.0.0 - MERGED with export_filtered.php, OPTIMIZED queries
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/export.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Cek autentikasi
if (!checkAuth() && !isMarketing()) {
    http_response_code(401);
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    } else {
        die('Unauthorized');
    }
    exit();
}

// Rate limiting
$client_ip = getClientIP();
if (!checkRateLimit('export_' . $client_ip, 10, 300)) { // 10 requests per 5 minutes
    http_response_code(429);
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']);
    } else {
        die('Terlalu banyak permintaan. Silakan coba lagi nanti.');
    }
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== DETEKSI ROLE USER ==========
$current_role = getCurrentRole();
$user_id = 0;
$marketing_id = 0;
$developer_id = 0;
$location_access = '';
$user_name = '';

if (isMarketing()) {
    $current_role = 'marketing';
    $marketing_id = $_SESSION['marketing_id'] ?? 0;
    $marketing_name = $_SESSION['marketing_name'] ?? 'Marketing';
    $developer_id = $_SESSION['marketing_developer_id'] ?? 0;
    $user_name = $marketing_name;
    
    // Ambil lokasi developer
    $stmt = $conn->prepare("SELECT location_access FROM users WHERE id = ?");
    $stmt->execute([$developer_id]);
    $dev_data = $stmt->fetch();
    $location_access = $dev_data['location_access'] ?? '';
    
} elseif (isDeveloper()) {
    $current_role = 'developer';
    $developer_id = $_SESSION['user_id'];
    $location_access = $_SESSION['location_access'] ?? '';
    $user_name = $_SESSION['nama_lengkap'] ?? 'Developer';
    
} elseif (isAdmin()) {
    $current_role = 'admin';
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['nama_lengkap'] ?? 'Admin';
    
} elseif (isManager()) {
    $current_role = 'manager';
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['nama_lengkap'] ?? 'Manager';
    
} elseif (isFinance() || isManagerDeveloper()) {
    $current_role = $current_role;
    $developer_id = $_SESSION['developer_id'] ?? 0;
    $user_name = $_SESSION['nama_lengkap'] ?? 'User';
}

// ========== AMBIL FILTER DARI URL ATAU POST ==========
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
} else {
    $input = $_GET;
}

$action = isset($input['action']) ? $input['action'] : 'export';
$format = isset($input['format']) ? $input['format'] : 'excel';
$search = isset($input['search']) ? trim($input['search']) : '';
$location_filter = isset($input['location']) ? $input['location'] : '';
$status_filter = isset($input['status']) ? $input['status'] : '';
$assigned_marketing = isset($input['assigned_marketing']) ? (int)$input['assigned_marketing'] : 0;
$developer_filter = isset($input['developer_id']) ? (int)$input['developer_id'] : 0;

// Filter lanjutan (dari export_filtered)
$period = isset($input['period']) ? $input['period'] : 'all';
$start_date = isset($input['start_date']) ? $input['start_date'] : '';
$end_date = isset($input['end_date']) ? $input['end_date'] : '';
$status_list = isset($input['status_list']) && is_array($input['status_list']) ? $input['status_list'] : [];
$location_list = isset($input['location_list']) && is_array($input['location_list']) ? $input['location_list'] : [];
$developer_list = isset($input['developer_list']) && is_array($input['developer_list']) ? $input['developer_list'] : [];
$score_min = isset($input['score_min']) ? (int)$input['score_min'] : 0;
$score_max = isset($input['score_max']) ? (int)$input['score_max'] : 100;
$include_duplicate = isset($input['include_duplicate']) ? (int)$input['include_duplicate'] : 1;

// Validasi format
if (!in_array($format, ['excel', 'pdf', 'csv', 'json'])) {
    $format = 'excel';
}

// ========== BUILD QUERY DASAR ==========
$base_sql = "SELECT 
            l.*, 
            loc.display_name as location_display, 
            loc.icon,
            u.nama_lengkap as developer_name,
            m.nama_lengkap as marketing_name,
            m.phone as marketing_phone,
            c.nama_cluster,
            b.nama_block,
            un.nomor_unit,
            un.tipe_unit as unit_tipe,
            un.program as unit_program,
            un.status as unit_status,
            un.harga as unit_harga,
            un.harga_booking as unit_harga_booking,
            DATE(l.created_at) as tanggal,
            TIME(l.created_at) as waktu,
            CONCAT(l.first_name, ' ', l.last_name) as nama_lengkap,
            CONCAT(l.first_name, ' ', l.last_name) as full_name
        FROM leads l 
        LEFT JOIN locations loc ON l.location_key = loc.location_key 
        LEFT JOIN users u ON l.ditugaskan_ke = u.id
        LEFT JOIN marketing_team m ON l.assigned_marketing_team_id = m.id
        LEFT JOIN units un ON l.id = un.lead_id
        LEFT JOIN blocks b ON un.block_id = b.id
        LEFT JOIN clusters c ON un.cluster_id = c.id
        WHERE (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";

$params = [];
$count_params = [];

// ========== FILTER BERDASARKAN ROLE ==========
if ($current_role === 'marketing') {
    if ($marketing_id <= 0) {
        die(json_encode(['success' => false, 'message' => 'Marketing ID tidak valid']));
    }
    $base_sql .= " AND l.assigned_marketing_team_id = ?";
    $params[] = $marketing_id;
    
} elseif ($current_role === 'developer') {
    if (empty($location_access)) {
        die(json_encode(['success' => false, 'message' => 'Developer tidak memiliki akses lokasi']));
    }
    
    $locations = explode(',', $location_access);
    $locations = array_map('trim', $locations);
    $locations = array_filter($locations);
    
    if (empty($locations)) {
        die(json_encode(['success' => false, 'message' => 'Tidak ada lokasi yang diassign']));
    }
    
    $placeholders = implode(',', array_fill(0, count($locations), '?'));
    $base_sql .= " AND l.location_key IN ($placeholders)";
    $params = array_merge($params, $locations);
    
    if ($location_filter && in_array($location_filter, $locations)) {
        $base_sql .= " AND l.location_key = ?";
        $params[] = $location_filter;
    }
    
} elseif (in_array($current_role, ['admin', 'manager'])) {
    if ($assigned_marketing > 0) {
        $base_sql .= " AND l.assigned_marketing_team_id = ?";
        $params[] = $assigned_marketing;
    }
    
    if ($location_filter) {
        $base_sql .= " AND l.location_key = ?";
        $params[] = $location_filter;
    }
    
    if ($developer_filter > 0) {
        $base_sql .= " AND l.ditugaskan_ke = ?";
        $params[] = $developer_filter;
    }
    
} elseif (in_array($current_role, ['finance', 'manager_developer'])) {
    if ($developer_id > 0) {
        $base_sql .= " AND l.ditugaskan_ke = ?";
        $params[] = $developer_id;
    }
}

// ========== FILTER PERIODE ==========
if ($period === 'today') {
    $base_sql .= " AND DATE(l.created_at) = CURDATE()";
} elseif ($period === 'yesterday') {
    $base_sql .= " AND DATE(l.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($period === 'week') {
    $base_sql .= " AND YEARWEEK(l.created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($period === 'month') {
    $base_sql .= " AND MONTH(l.created_at) = MONTH(CURDATE()) AND YEAR(l.created_at) = YEAR(CURDATE())";
} elseif ($period === 'last_month') {
    $base_sql .= " AND MONTH(l.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                    AND YEAR(l.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
} elseif ($period === 'year') {
    $base_sql .= " AND YEAR(l.created_at) = YEAR(CURDATE())";
} elseif ($period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $base_sql .= " AND DATE(l.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

// ========== FILTER STATUS ==========
if (!empty($status_filter) && empty($status_list)) {
    $base_sql .= " AND l.status = ?";
    $params[] = $status_filter;
}

if (!empty($status_list)) {
    $placeholders = implode(',', array_fill(0, count($status_list), '?'));
    $base_sql .= " AND l.status IN ($placeholders)";
    $params = array_merge($params, $status_list);
}

// ========== FILTER LOKASI ==========
if (!empty($location_list) && in_array($current_role, ['admin', 'manager'])) {
    $placeholders = implode(',', array_fill(0, count($location_list), '?'));
    $base_sql .= " AND l.location_key IN ($placeholders)";
    $params = array_merge($params, $location_list);
}

// ========== FILTER DEVELOPER ==========
if (!empty($developer_list) && in_array($current_role, ['admin', 'manager'])) {
    $placeholders = implode(',', array_fill(0, count($developer_list), '?'));
    $base_sql .= " AND l.ditugaskan_ke IN ($placeholders)";
    $params = array_merge($params, $developer_list);
}

// ========== FILTER SCORE ==========
if ($score_min > 0 || $score_max < 100) {
    $base_sql .= " AND l.lead_score BETWEEN ? AND ?";
    $params[] = $score_min;
    $params[] = $score_max;
}

// ========== FILTER DUPLICATE ==========
if ($include_duplicate === 0) {
    $base_sql .= " AND (l.is_duplicate_warning = 0 OR l.is_duplicate_warning IS NULL)";
}

// ========== FILTER PENCARIAN ==========
if (!empty($search)) {
    $base_sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? OR CONCAT(l.first_name, ' ', l.last_name) LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}

// ========== ORDER BY ==========
$base_sql .= " ORDER BY l.created_at DESC";

// ========== JIKA ACTION = PREVIEW, KEMBALIKAN STATISTIK ==========
if ($action === 'preview') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // Hitung total
        $count_sql = "SELECT COUNT(*) FROM (" . $base_sql . ") as tmp";
        $stmt = $conn->prepare($count_sql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => $total,
                'filtered' => true
            ]
        ]);
        exit();
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit();
    }
}

// ========== EKSEKUSI QUERY ==========
$stmt = $conn->prepare($base_sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Mask data sensitif untuk role tertentu
foreach ($leads as &$lead) {
    if (!in_array($current_role, ['admin', 'manager', 'finance'])) {
        if (!empty($lead['phone'])) {
            $lead['phone_masked'] = substr($lead['phone'], 0, 4) . '****' . substr($lead['phone'], -4);
        }
        if (!empty($lead['email'])) {
            $parts = explode('@', $lead['email']);
            if (isset($parts[0])) {
                $lead['email_masked'] = substr($parts[0], 0, 2) . '****@' . $parts[1];
            }
        }
    }
}

// ========== EXPORT BERDASARKAN FORMAT ==========
switch ($format) {
    case 'csv':
        exportCSV($leads, $user_name, $current_role, $search, $location_filter, $status_filter, $assigned_marketing);
        break;
    case 'excel':
        exportExcel($leads, $user_name, $current_role, $search, $location_filter, $status_filter, $assigned_marketing);
        break;
    case 'pdf':
        exportPDF($leads, $user_name, $current_role, $search, $location_filter, $status_filter, $assigned_marketing);
        break;
    case 'json':
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $leads,
            'total' => count($leads),
            'role' => $current_role,
            'user' => $user_name
        ], JSON_PRETTY_PRINT);
        exit();
    default:
        exportExcel($leads, $user_name, $current_role, $search, $location_filter, $status_filter, $assigned_marketing);
}

// ========== CSV EXPORT ==========
function exportCSV($leads, $user_name, $role, $search, $location, $status, $assigned_marketing) {
    $filename = 'leads_' . $role . '_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    // Header CSV
    fputcsv($output, [
        'ID', 'Tanggal', 'Waktu', 'Nama Depan', 'Nama Belakang', 'Nama Lengkap',
        'WhatsApp', 'Email', 'Lokasi', 'Developer', 'Marketing', 'Tipe Unit (Leads)',
        'Program (Leads)', 'Status Lead', 'Lead Score', 'Kota', 'Alamat', 'Sumber',
        'Duplicate Warning', 'Catatan', 'Cluster', 'Block', 'Nomor Unit',
        'Tipe Unit (Booking)', 'Program Unit', 'Status Unit', 'Harga Unit', 'Harga Booking'
    ]);
    
    foreach ($leads as $lead) {
        $full_name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
        $marketing_name = $lead['marketing_name'] ?? ($lead['assigned_marketing_team_id'] ? 'Marketing #' . $lead['assigned_marketing_team_id'] : '');
        $developer_name = $lead['developer_name'] ?? '';
        
        // Escape special characters
        $first_name = str_replace(['"', ','], '', $lead['first_name'] ?? '');
        $last_name = str_replace(['"', ','], '', $lead['last_name'] ?? '');
        $full_name = str_replace(['"', ','], '', $full_name);
        $notes = str_replace(['"', ','], '', $lead['notes'] ?? '');
        $email = str_replace(['"', ','], '', $lead['email'] ?? '');
        $address = str_replace(['"', ','], '', $lead['address'] ?? '');
        
        fputcsv($output, [
            $lead['id'],
            date('Y-m-d', strtotime($lead['created_at'])),
            date('H:i:s', strtotime($lead['created_at'])),
            $first_name,
            $last_name,
            $full_name,
            $lead['phone'] ?? '',
            $email,
            $lead['location_display'] ?? $lead['location_key'],
            $developer_name,
            $marketing_name,
            $lead['unit_type'] ?? 'Type 36/60',
            $lead['program'] ?? 'Subsidi',
            $lead['status'] ?? 'Baru',
            $lead['lead_score'] ?? 0,
            $lead['city'] ?? '',
            $address,
            $lead['source'] ?? 'website',
            !empty($lead['is_duplicate_warning']) ? 'YA' : 'TIDAK',
            $notes,
            $lead['nama_cluster'] ?? '',
            $lead['nama_block'] ?? '',
            $lead['nomor_unit'] ?? '',
            $lead['unit_tipe'] ?? '',
            $lead['unit_program'] ?? '',
            $lead['unit_status'] ?? '',
            $lead['unit_harga'] ?? '',
            $lead['unit_harga_booking'] ?? ''
        ], ',', '"');
    }
    
    fclose($output);
    exit();
}

// ========== EXCEL EXPORT ==========
function exportExcel($leads, $user_name, $role, $search, $location, $status, $assigned_marketing) {
    $filename = 'leads_' . $role . '_' . date('Y-m-d_His') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $role_display = ucfirst($role);
    
    echo '<html>';
    echo '<head><meta charset="UTF-8">';
    echo '<style>
        body { font-family: Arial, sans-serif; }
        h1 { color: #1B4A3C; }
        .header { background: #1B4A3C; color: white; padding: 15px; }
        table { border-collapse: collapse; width: 100%; font-size: 11px; }
        th { background: #1B4A3C; color: white; padding: 8px; }
        td { padding: 6px; border: 1px solid #ccc; }
        .duplicate { background: #FFF3CD; }
        .text-right { text-align: right; }
    </style>';
    echo '</head><body>';
    
    echo '<div class="header">';
    echo '<h1 style="color: white;">📊 TaufikMarie.com - Export Data Leads</h1>';
    echo '<p>Role: ' . $role_display . ' | User: ' . htmlspecialchars($user_name) . '</p>';
    echo '</div>';
    
    echo '<p>Total: ' . count($leads) . ' Leads | Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    
    if (empty($leads)) {
        echo '<div style="padding: 50px; text-align: center; background: #f5f5f5;">Tidak ada data</div>';
    } else {
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Tanggal</th>';
        echo '<th>Nama</th>';
        echo '<th>WhatsApp</th>';
        echo '<th>Email</th>';
        echo '<th>Lokasi</th>';
        echo '<th>Status</th>';
        echo '<th>Score</th>';
        echo '<th>Marketing</th>';
        echo '<th>Developer</th>';
        echo '<th>Cluster</th>';
        echo '<th>Block</th>';
        echo '<th>Unit</th>';
        echo '<th>Tipe Unit</th>';
        echo '<th>Harga</th>';
        echo '</tr>';
        
        foreach ($leads as $lead) {
            $dup_class = !empty($lead['is_duplicate_warning']) ? 'duplicate' : '';
            $full_name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
            $marketing_name = $lead['marketing_name'] ?? ($lead['assigned_marketing_team_id'] ? 'Marketing #' . $lead['assigned_marketing_team_id'] : '-');
            
            echo '<tr class="' . $dup_class . '">';
            echo '<td>#' . $lead['id'] . '</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($lead['created_at'])) . '</td>';
            echo '<td><strong>' . htmlspecialchars($full_name) . '</strong></td>';
            echo '<td>' . htmlspecialchars($lead['phone'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['email'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['location_display'] ?? $lead['location_key']) . '</td>';
            echo '<td>' . htmlspecialchars($lead['status'] ?? 'Baru') . '</td>';
            echo '<td class="text-right"><strong>' . ($lead['lead_score'] ?? 0) . '</strong></td>';
            echo '<td>' . htmlspecialchars($marketing_name) . '</td>';
            echo '<td>' . htmlspecialchars($lead['developer_name'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['nama_cluster'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['nama_block'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['nomor_unit'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['unit_tipe'] ?? '-') . '</td>';
            echo '<td class="text-right">' . (!empty($lead['unit_harga']) ? 'Rp ' . number_format($lead['unit_harga'], 0, ',', '.') : '-') . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    echo '<div style="margin-top: 20px; padding: 10px; background: #f5f5f5; text-align: center; font-size: 10px;">';
    echo '© ' . date('Y') . ' TaufikMarie.com - Export System v6.0';
    echo '</div>';
    
    echo '</body></html>';
    exit();
}

// ========== PDF EXPORT ==========
function exportPDF($leads, $user_name, $role, $search, $location, $status, $assigned_marketing) {
    // Cek Dompdf
    $dompdf_path = __DIR__ . '/../../vendor/autoload.php';
    
    if (file_exists($dompdf_path)) {
        require_once $dompdf_path;
        
        use Dompdf\Dompdf;
        use Dompdf\Options;
        
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        
        $dompdf = new Dompdf($options);
        
        $role_display = ucfirst($role);
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Helvetica, sans-serif; font-size: 9px; }
                h1 { color: #1B4A3C; font-size: 18px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #1B4A3C; color: white; padding: 5px; text-align: left; }
                td { padding: 4px; border: 1px solid #ccc; }
                .duplicate { background: #FFF3CD; }
                .text-right { text-align: right; }
                .footer { margin-top: 15px; font-size: 7px; color: #666; }
            </style>
        </head>
        <body>
            <h1>Export Data Leads - ' . $role_display . '</h1>
            <p>User: ' . htmlspecialchars($user_name) . ' | Tanggal: ' . date('d/m/Y H:i:s') . '</p>
            <p>Total: ' . count($leads) . ' Leads</p>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Nama</th>
                        <th>Kontak</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Marketing</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($leads as $lead) {
            $dup_class = !empty($lead['is_duplicate_warning']) ? 'duplicate' : '';
            $full_name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
            $marketing_name = $lead['marketing_name'] ?? ($lead['assigned_marketing_team_id'] ? 'Marketing #' . $lead['assigned_marketing_team_id'] : '-');
            $unit_info = ($lead['nomor_unit'] ?? '') ? $lead['nomor_unit'] . ' (' . ($lead['unit_tipe'] ?? '') . ')' : '-';
            
            $html .= '
                    <tr class="' . $dup_class . '">
                        <td>#' . $lead['id'] . '</td>
                        <td>' . date('d/m/Y', strtotime($lead['created_at'])) . '</td>
                        <td>' . htmlspecialchars($full_name) . '</td>
                        <td>' . htmlspecialchars($lead['phone'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($lead['location_display'] ?? $lead['location_key']) . '</td>
                        <td>' . htmlspecialchars($lead['status'] ?? 'Baru') . '</td>
                        <td class="text-right">' . ($lead['lead_score'] ?? 0) . '</td>
                        <td>' . htmlspecialchars($marketing_name) . '</td>
                        <td>' . htmlspecialchars($unit_info) . '</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            
            <div class="footer">
                <p>Dicetak dari LeadEngine - ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </body>
        </html>';
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        $filename = 'leads_' . $role . '_' . date('Y-m-d') . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit();
        
    } else {
        // Fallback ke Excel
        exportExcel($leads, $user_name, $role, $search, $location, $status, $assigned_marketing);
    }
}
?>