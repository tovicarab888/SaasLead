<?php
/**
 * EXPORT_FILTERED.PHP - TAUFIKMARIE.COM PREMIUM
 * Version: 4.0.0 - FIXED: Session authentication + Data Cluster/Block/Unit
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/export_filtered.log');

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Buat folder logs jika belum ada
$log_dir = dirname(__DIR__) . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Untuk preview, kita kirim JSON
// Untuk export, kita kirim file

// Ambil data POST
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Log input untuk debugging
$log_file = $log_dir . '/export_filtered.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Export Filtered Request\n", FILE_APPEND);
file_put_contents($log_file, "Input: " . print_r($input, true) . "\n", FILE_APPEND);

// ========== CEK AUTH ==========
if (!checkAuth() && !isMarketing()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// ========== DETEKSI ROLE USER ==========
$current_role = '';
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
    
    // Ambil lokasi developer untuk informasi
    $conn = getDB();
    if ($conn) {
        $stmt = $conn->prepare("SELECT location_access FROM users WHERE id = ?");
        $stmt->execute([$developer_id]);
        $dev_data = $stmt->fetch();
        $location_access = $dev_data['location_access'] ?? '';
    }
    
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
}

$action = isset($input['action']) ? $input['action'] : 'export';
$format = isset($input['format']) ? $input['format'] : 'excel';
$period = isset($input['period']) ? $input['period'] : 'all';
$start_date = isset($input['start_date']) ? $input['start_date'] : '';
$end_date = isset($input['end_date']) ? $input['end_date'] : '';
$status_list = isset($input['status']) && is_array($input['status']) ? $input['status'] : [];
$location_list = isset($input['locations']) && is_array($input['locations']) ? $input['locations'] : [];
$developer_list = isset($input['developers']) && is_array($input['developers']) ? $input['developers'] : [];
$score_min = isset($input['score_min']) ? (int)$input['score_min'] : 0;
$score_max = isset($input['score_max']) ? (int)$input['score_max'] : 100;
$include_duplicate = isset($input['include_duplicate']) ? (int)$input['include_duplicate'] : 1;
$search = isset($input['search']) ? trim($input['search']) : '';

// Validasi format
if (!in_array($format, ['excel', 'pdf', 'csv'])) {
    $format = 'excel';
}

$conn = getDB();
if (!$conn) {
    if ($action === 'preview') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    } else {
        die("Database connection failed");
    }
}

// ========== BUILD QUERY DASAR ==========
$base_sql = "FROM leads l 
             LEFT JOIN locations loc ON l.location_key = loc.location_key 
             LEFT JOIN users u ON l.ditugaskan_ke = u.id
             LEFT JOIN marketing_team m ON l.assigned_marketing_team_id = m.id
             LEFT JOIN units un ON l.id = un.lead_id
             LEFT JOIN blocks b ON un.block_id = b.id
             LEFT JOIN clusters c ON un.cluster_id = c.id
             WHERE (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";

$params = [];

// ========== FILTER BERDASARKAN ROLE ==========
if ($current_role === 'marketing') {
    // MARKETING: HANYA LIHAT LEAD YANG DIASSIGN KE DIRINYA
    if ($marketing_id <= 0) {
        if ($action === 'preview') {
            echo json_encode(['success' => false, 'message' => 'Marketing ID tidak valid']);
            exit();
        } else {
            die("Marketing ID tidak valid");
        }
    }
    $base_sql .= " AND l.assigned_marketing_team_id = ?";
    $params[] = $marketing_id;
    
} elseif ($current_role === 'developer') {
    // DEVELOPER: HANYA LIHAT LEAD DARI LOKASI YANG DIASSIGN
    if (empty($location_access)) {
        if ($action === 'preview') {
            echo json_encode(['success' => false, 'message' => 'Developer tidak memiliki akses lokasi']);
            exit();
        } else {
            die("Developer tidak memiliki akses lokasi");
        }
    }
    
    $dev_locations = explode(',', $location_access);
    $dev_locations = array_map('trim', $dev_locations);
    $dev_locations = array_filter($dev_locations);
    
    if (empty($dev_locations)) {
        if ($action === 'preview') {
            echo json_encode(['success' => false, 'message' => 'Tidak ada lokasi yang diassign']);
            exit();
        } else {
            die("Tidak ada lokasi yang diassign");
        }
    }
    
    $placeholders = implode(',', array_fill(0, count($dev_locations), '?'));
    $base_sql .= " AND l.location_key IN ($placeholders)";
    $params = array_merge($params, $dev_locations);
    
} elseif ($current_role === 'admin' || $current_role === 'manager') {
    // ADMIN/MANAGER: BISA LIHAT SEMUA, BISA FILTER BERDASARKAN DEVELOPER
    if (!empty($developer_list)) {
        $placeholders = implode(',', array_fill(0, count($developer_list), '?'));
        $base_sql .= " AND l.ditugaskan_ke IN ($placeholders)";
        $params = array_merge($params, $developer_list);
    }
}

// ========== FILTER BERDASARKAN PERIODE ==========
$period_sql = "";
$period_params = [];

if ($period === 'today') {
    $period_sql = " AND DATE(l.created_at) = CURDATE()";
} elseif ($period === 'yesterday') {
    $period_sql = " AND DATE(l.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($period === 'week') {
    $period_sql = " AND YEARWEEK(l.created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($period === 'month') {
    $period_sql = " AND MONTH(l.created_at) = MONTH(CURDATE()) AND YEAR(l.created_at) = YEAR(CURDATE())";
} elseif ($period === 'last_month') {
    $period_sql = " AND MONTH(l.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                    AND YEAR(l.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
} elseif ($period === 'year') {
    $period_sql = " AND YEAR(l.created_at) = YEAR(CURDATE())";
} elseif ($period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_sql = " AND DATE(l.created_at) BETWEEN ? AND ?";
    $period_params = [$start_date, $end_date];
}

// ========== FILTER BERDASARKAN STATUS ==========
$status_sql = "";
$status_params = [];
if (!empty($status_list)) {
    $placeholders = implode(',', array_fill(0, count($status_list), '?'));
    $status_sql = " AND l.status IN ($placeholders)";
    $status_params = $status_list;
}

// ========== FILTER BERDASARKAN LOKASI (UNTUK ADMIN/MANAGER) ==========
$location_sql = "";
$location_params = [];
if (($current_role === 'admin' || $current_role === 'manager') && !empty($location_list)) {
    $placeholders = implode(',', array_fill(0, count($location_list), '?'));
    $location_sql = " AND l.location_key IN ($placeholders)";
    $location_params = $location_list;
}

// ========== FILTER BERDASARKAN SCORE ==========
$score_sql = "";
$score_params = [];
if ($score_min > 0 || $score_max < 100) {
    $score_sql = " AND l.lead_score BETWEEN ? AND ?";
    $score_params = [$score_min, $score_max];
}

// ========== FILTER DUPLICATE WARNING ==========
$duplicate_sql = "";
if ($include_duplicate === 0) {
    $duplicate_sql = " AND (l.is_duplicate_warning = 0 OR l.is_duplicate_warning IS NULL)";
}

// ========== FILTER PENCARIAN ==========
$search_sql = "";
$search_params = [];
if (!empty($search)) {
    $search_sql = " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? OR CONCAT(l.first_name, ' ', l.last_name) LIKE ?)";
    $s = "%$search%";
    $search_params = [$s, $s, $s, $s, $s];
}

// Gabungkan semua filter
$full_sql = $base_sql . $period_sql . $status_sql . $location_sql . $score_sql . $duplicate_sql . $search_sql;
$all_params = array_merge($params, $period_params, $status_params, $location_params, $score_params, $search_params);

// Log query untuk debugging
file_put_contents($log_file, "Role: $current_role\n", FILE_APPEND);
file_put_contents($log_file, "SQL: $full_sql\n", FILE_APPEND);
file_put_contents($log_file, "Params: " . print_r($all_params, true) . "\n", FILE_APPEND);

// ========== JIKA ACTION = PREVIEW, KEMBALIKAN STATISTIK ==========
if ($action === 'preview') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // Hitung total berdasarkan filter
        $count_sql = "SELECT COUNT(*) " . $full_sql;
        $stmt = $conn->prepare($count_sql);
        $stmt->execute($all_params);
        $total = (int)$stmt->fetchColumn();
        
        // Hitung hari ini (dengan filter yang sama)
        $today_sql = "SELECT COUNT(*) " . $base_sql . " AND DATE(l.created_at) = CURDATE()" . $status_sql . $location_sql . $score_sql . $duplicate_sql . $search_sql;
        $today_params = array_merge($params, $status_params, $location_params, $score_params, $search_params);
        $stmt = $conn->prepare($today_sql);
        $stmt->execute($today_params);
        $today = (int)$stmt->fetchColumn();
        
        // Hitung minggu ini
        $week_sql = "SELECT COUNT(*) " . $base_sql . " AND YEARWEEK(l.created_at, 1) = YEARWEEK(CURDATE(), 1)" . $status_sql . $location_sql . $score_sql . $duplicate_sql . $search_sql;
        $week_params = array_merge($params, $status_params, $location_params, $score_params, $search_params);
        $stmt = $conn->prepare($week_sql);
        $stmt->execute($week_params);
        $week = (int)$stmt->fetchColumn();
        
        // Hitung bulan ini
        $month_sql = "SELECT COUNT(*) " . $base_sql . " AND MONTH(l.created_at) = MONTH(CURDATE()) AND YEAR(l.created_at) = YEAR(CURDATE())" . $status_sql . $location_sql . $score_sql . $duplicate_sql . $search_sql;
        $month_params = array_merge($params, $status_params, $location_params, $score_params, $search_params);
        $stmt = $conn->prepare($month_sql);
        $stmt->execute($month_params);
        $month = (int)$stmt->fetchColumn();
        
        // Hitung kemarin untuk persentase
        $yesterday_sql = "SELECT COUNT(*) " . $base_sql . " AND DATE(l.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)" . $status_sql . $location_sql . $score_sql . $duplicate_sql . $search_sql;
        $yesterday_params = array_merge($params, $status_params, $location_params, $score_params, $search_params);
        $stmt = $conn->prepare($yesterday_sql);
        $stmt->execute($yesterday_params);
        $yesterday = (int)$stmt->fetchColumn();
        
        // Hitung persentase perubahan
        $today_percent = 0;
        if ($yesterday > 0) {
            $today_percent = round((($today - $yesterday) / $yesterday) * 100);
        } elseif ($today > 0 && $yesterday == 0) {
            $today_percent = 100;
        }
        
        file_put_contents($log_file, "Preview - Total: $total, Today: $today, Week: $week, Month: $month, Yesterday: $yesterday\n", FILE_APPEND);
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => $total,
                'today' => $today,
                'week' => $week,
                'month' => $month,
                'yesterday' => $yesterday,
                'today_percent' => $today_percent
            ]
        ]);
        exit();
        
    } catch (Exception $e) {
        file_put_contents($log_file, "Preview Error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit();
    }
}

// ========== JIKA ACTION = EXPORT, LANJUTKAN EXPORT ==========

// Ambil semua data untuk export
$select_sql = "SELECT 
            l.*, 
            loc.display_name as location_display, 
            loc.icon,
            u.nama_lengkap as developer_name,
            m.nama_lengkap as marketing_name,
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
            CONCAT(l.first_name, ' ', l.last_name) as nama_lengkap
        " . $full_sql . " ORDER BY l.created_at DESC";

$stmt = $conn->prepare($select_sql);
$stmt->execute($all_params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

file_put_contents($log_file, "Total leads found: " . count($leads) . "\n", FILE_APPEND);
file_put_contents($log_file, str_repeat("=", 50) . "\n\n", FILE_APPEND);

// ========== EXPORT BERDASARKAN FORMAT ==========
exportByFormat($leads, $format, $input, $current_role, $user_name);

function exportByFormat($leads, $format, $filter, $role, $user_name) {
    switch ($format) {
        case 'excel':
            exportExcel($leads, $filter, $role, $user_name);
            break;
        case 'pdf':
            exportPDF($leads, $filter, $role, $user_name);
            break;
        case 'csv':
            exportCSV($leads, $filter, $role, $user_name);
            break;
        default:
            exportExcel($leads, $filter, $role, $user_name);
    }
}

// ========== EXPORT EXCEL PREMIUM ==========
function exportExcel($leads, $filter, $role, $user_name) {
    $filename = 'leads_export_' . $role . '_' . date('Y-m-d_His') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Buat info filter untuk ditampilkan di file
    $filter_info = [];
    
    $period = isset($filter['period']) ? $filter['period'] : 'all';
    $start_date = isset($filter['start_date']) ? $filter['start_date'] : '';
    $end_date = isset($filter['end_date']) ? $filter['end_date'] : '';
    $status_list = isset($filter['status']) && is_array($filter['status']) ? $filter['status'] : [];
    $location_list = isset($filter['locations']) && is_array($filter['locations']) ? $filter['locations'] : [];
    $developer_list = isset($filter['developers']) && is_array($filter['developers']) ? $filter['developers'] : [];
    $score_min = isset($filter['score_min']) ? (int)$filter['score_min'] : 0;
    $score_max = isset($filter['score_max']) ? (int)$filter['score_max'] : 100;
    $include_duplicate = isset($filter['include_duplicate']) ? (int)$filter['include_duplicate'] : 1;
    $search = isset($filter['search']) ? $filter['search'] : '';
    
    if ($period === 'today') $filter_info[] = "Hari Ini";
    elseif ($period === 'yesterday') $filter_info[] = "Kemarin";
    elseif ($period === 'week') $filter_info[] = "Minggu Ini";
    elseif ($period === 'month') $filter_info[] = "Bulan Ini";
    elseif ($period === 'last_month') $filter_info[] = "Bulan Lalu";
    elseif ($period === 'year') $filter_info[] = "Tahun Ini";
    elseif ($period === 'custom' && $start_date && $end_date) {
        $filter_info[] = $start_date . ' s/d ' . $end_date;
    }
    
    if (!empty($status_list)) $filter_info[] = "Status: " . implode(', ', $status_list);
    if (!empty($location_list)) $filter_info[] = "Lokasi: " . implode(', ', $location_list);
    if (!empty($developer_list)) $filter_info[] = "Developer: " . implode(', ', $developer_list);
    if ($score_min > 0 || $score_max < 100) {
        $filter_info[] = "Score: {$score_min} - {$score_max}";
    }
    if ($include_duplicate == 0) $filter_info[] = "Exclude Duplikat";
    if (!empty($search)) $filter_info[] = "Pencarian: " . $search;
    
    $filter_text = empty($filter_info) ? "Semua Data" : implode(" | ", $filter_info);
    
    $role_display = ucfirst($role);
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #1B4A3C; font-size: 24px; margin-bottom: 10px; }
        .header { 
            background: linear-gradient(135deg, #1B4A3C, #2A5F4E); 
            color: white; 
            padding: 20px 25px; 
            border-radius: 12px 12px 0 0; 
            margin-bottom: 0;
        }
        .header h1 { color: white; margin: 0; font-size: 26px; }
        .header p { margin: 5px 0 0 0; opacity: 0.9; font-size: 14px; }
        .filter-info { 
            background: #E7F3EF; 
            padding: 15px 20px; 
            margin: 0; 
            border-left: 4px solid #D64F3C;
            font-size: 13px;
        }
        .summary {
            background: white;
            padding: 15px 20px;
            border-bottom: 2px solid #E0DAD3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-badge {
            background: #1B4A3C;
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 14px;
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-top: 0;
            font-size: 11px;
        }
        th { 
            background: #1B4A3C; 
            color: white; 
            padding: 10px 6px; 
            text-align: left; 
            font-weight: 600;
            white-space: nowrap;
        }
        td { 
            padding: 8px 6px; 
            border: 1px solid #ddd; 
            vertical-align: top;
        }
        tr:nth-child(even) { background: #f9f9f9; }
        tr:hover { background: #f1f1f1; }
        .hot { background: #ffebee; }
        .warm { background: #fff3e0; }
        .cold { background: #e3f2fd; }
        .duplicate { background: #FFF3CD; }
        .badge { 
            display: inline-block; 
            padding: 3px 8px; 
            border-radius: 20px; 
            font-size: 10px; 
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-success { background: #2A9D8F; color: white; }
        .badge-warning { background: #E9C46A; color: #1A2A24; }
        .badge-danger { background: #D64F3C; color: white; }
        .badge-info { background: #4A90E2; color: white; }
        .badge-primary { background: #1B4A3C; color: white; }
        .footer { 
            margin-top: 25px; 
            padding: 15px 20px; 
            background: #f5f5f5; 
            border-radius: 0 0 12px 12px;
            font-size: 11px; 
            color: #666; 
            text-align: center;
            border-top: 1px solid #ddd;
        }
        .footer strong { color: #1B4A3C; }
        .whatsapp-link { color: #25D366; text-decoration: none; font-weight: 600; }
        .nowrap { white-space: nowrap; }
    </style>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="header">';
    echo '<h1>📊 TaufikMarie.com - Export Data Leads Premium</h1>';
    echo '<p>' . $role_display . ': ' . htmlspecialchars($user_name) . '</p>';
    echo '</div>';
    
    echo '<div class="filter-info">';
    echo '<strong>📋 Filter yang diterapkan:</strong> ' . htmlspecialchars($filter_text);
    echo '</div>';
    
    echo '<div class="summary">';
    echo '<div>';
    echo '<strong>📅 Tanggal Export:</strong> ' . date('d/m/Y H:i:s') . ' WIB<br>';
    echo '<strong>👤 Diekspor oleh:</strong> ' . htmlspecialchars($user_name) . ' (' . $role_display . ')';
    echo '</div>';
    echo '<div class="total-badge">Total: ' . count($leads) . ' Leads</div>';
    echo '</div>';
    
    if (empty($leads)) {
        echo '<div style="text-align: center; padding: 50px; background: white; border: 2px dashed #ccc; margin-top: 20px;">';
        echo '<i class="fas fa-inbox fa-4x" style="color: #ccc; margin-bottom: 15px;"></i>';
        echo '<h3 style="color: #666;">Tidak ada data yang sesuai dengan filter</h3>';
        echo '<p style="color: #999;">Coba ubah filter export Anda</p>';
        echo '</div>';
    } else {
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Tanggal</th>';
        echo '<th>Waktu</th>';
        echo '<th>Nama</th>';
        echo '<th>WhatsApp</th>';
        echo '<th>Email</th>';
        echo '<th>Lokasi</th>';
        echo '<th>Developer</th>';
        echo '<th>Marketing</th>';
        echo '<th>Status</th>';
        echo '<th>Score</th>';
        echo '<th>Cluster</th>';
        echo '<th>Block</th>';
        echo '<th>Unit</th>';
        echo '<th>Tipe Unit</th>';
        echo '<th>Program</th>';
        echo '<th>Status Unit</th>';
        echo '<th>Harga Unit</th>';
        echo '<th>Harga Booking</th>';
        echo '<th>Kota</th>';
        echo '<th>Sumber</th>';
        echo '<th>Catatan</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($leads as $lead) {
            $score_class = '';
            if ($lead['lead_score'] >= 80) $score_class = 'hot';
            elseif ($lead['lead_score'] >= 60) $score_class = 'warm';
            else $score_class = 'cold';
            
            $dup_class = !empty($lead['is_duplicate_warning']) ? 'duplicate' : '';
            $full_name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
            if (empty($full_name)) $full_name = '-';
            
            $status = $lead['status'] ?? 'Baru';
            $status_class = '';
            if ($status == 'Baru') $status_class = 'badge-info';
            elseif ($status == 'Follow Up') $status_class = 'badge-warning';
            elseif ($status == 'Survey') $status_class = 'badge-warning';
            elseif ($status == 'Booking') $status_class = 'badge-primary';
            elseif ($status == 'Deal KPR') $status_class = 'badge-success';
            elseif ($status == 'Deal Tunai') $status_class = 'badge-success';
            elseif ($status == 'Tolak Slik') $status_class = 'badge-danger';
            elseif ($status == 'Tidak Minat') $status_class = 'badge-danger';
            elseif ($status == 'Batal') $status_class = 'badge-danger';
            else $status_class = 'badge-info';
            
            $marketing_name = $lead['marketing_name'] ?? ($lead['assigned_marketing_team_id'] ? 'Marketing #' . $lead['assigned_marketing_team_id'] : '-');
            $developer_name = $lead['developer_name'] ?? '-';
            
            // Format harga
            $harga_unit = !empty($lead['unit_harga']) ? 'Rp ' . number_format($lead['unit_harga'], 0, ',', '.') : '-';
            $harga_booking = !empty($lead['unit_harga_booking']) ? 'Rp ' . number_format($lead['unit_harga_booking'], 0, ',', '.') : '-';
            
            $unit_info = $lead['nomor_unit'] ?? '-';
            if ($lead['nama_cluster'] && $lead['nama_block']) {
                $unit_info = $lead['nomor_unit'] ?? '-';
            }
            
            echo '<tr class="' . $dup_class . '">';
            echo '<td><strong>#' . $lead['id'] . '</strong></td>';
            echo '<td class="nowrap">' . date('d/m/Y', strtotime($lead['created_at'])) . '</td>';
            echo '<td class="nowrap">' . date('H:i', strtotime($lead['created_at'])) . '</td>';
            echo '<td><strong>' . htmlspecialchars($full_name) . '</strong></td>';
            echo '<td class="nowrap">';
            if (!empty($lead['phone'])) {
                echo '<a href="https://wa.me/' . $lead['phone'] . '" target="_blank" class="whatsapp-link">';
                echo '<i class="fab fa-whatsapp"></i> ' . htmlspecialchars($lead['phone']);
                echo '</a>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td>' . htmlspecialchars($lead['email'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['location_display'] ?? $lead['location_key']) . '</td>';
            echo '<td>' . htmlspecialchars($developer_name) . '</td>';
            echo '<td>' . htmlspecialchars($marketing_name) . '</td>';
            echo '<td><span class="badge ' . $status_class . '">' . htmlspecialchars($status) . '</span></td>';
            echo '<td class="' . $score_class . '"><strong>' . ($lead['lead_score'] ?? 0) . '</strong></td>';
            echo '<td>' . htmlspecialchars($lead['nama_cluster'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['nama_block'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['nomor_unit'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['unit_tipe'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['unit_program'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['unit_status'] ?? '-') . '</td>';
            echo '<td>' . $harga_unit . '</td>';
            echo '<td>' . $harga_booking . '</td>';
            echo '<td>' . htmlspecialchars($lead['city'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['source'] ?? 'website') . '</td>';
            echo '<td>' . htmlspecialchars(substr($lead['notes'] ?? '-', 0, 50)) . (strlen($lead['notes'] ?? '') > 50 ? '...' : '') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    echo '<div class="footer">';
    echo '<p><strong>© ' . date('Y') . ' TaufikMarie.com</strong> - Premium Export System v4.0</p>';
    echo '<p>Dokumen ini digenerate berdasarkan role: ' . $role_display . '</p>';
    echo '</div>';
    
    echo '</body>';
    echo '</html>';
    exit();
}

// ========== EXPORT PDF PREMIUM ==========
function exportPDF($leads, $filter, $role, $user_name) {
    $filename = 'leads_export_' . $role . '_' . date('Y-m-d_His') . '.html';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Buat info filter untuk ditampilkan di file
    $filter_info = [];
    
    $period = isset($filter['period']) ? $filter['period'] : 'all';
    $start_date = isset($filter['start_date']) ? $filter['start_date'] : '';
    $end_date = isset($filter['end_date']) ? $filter['end_date'] : '';
    $status_list = isset($filter['status']) && is_array($filter['status']) ? $filter['status'] : [];
    $location_list = isset($filter['locations']) && is_array($filter['locations']) ? $filter['locations'] : [];
    $developer_list = isset($filter['developers']) && is_array($filter['developers']) ? $filter['developers'] : [];
    $score_min = isset($filter['score_min']) ? (int)$filter['score_min'] : 0;
    $score_max = isset($filter['score_max']) ? (int)$filter['score_max'] : 100;
    $include_duplicate = isset($filter['include_duplicate']) ? (int)$filter['include_duplicate'] : 1;
    $search = isset($filter['search']) ? $filter['search'] : '';
    
    if ($period === 'today') $filter_info[] = "Hari Ini";
    elseif ($period === 'yesterday') $filter_info[] = "Kemarin";
    elseif ($period === 'week') $filter_info[] = "Minggu Ini";
    elseif ($period === 'month') $filter_info[] = "Bulan Ini";
    elseif ($period === 'last_month') $filter_info[] = "Bulan Lalu";
    elseif ($period === 'year') $filter_info[] = "Tahun Ini";
    elseif ($period === 'custom' && $start_date && $end_date) {
        $filter_info[] = $start_date . ' s/d ' . $end_date;
    }
    
    if (!empty($status_list)) $filter_info[] = "Status: " . implode(', ', $status_list);
    if (!empty($location_list)) $filter_info[] = "Lokasi: " . implode(', ', $location_list);
    if (!empty($developer_list)) $filter_info[] = "Developer: " . implode(', ', $developer_list);
    if ($score_min > 0 || $score_max < 100) {
        $filter_info[] = "Score: {$score_min} - {$score_max}";
    }
    if ($include_duplicate == 0) $filter_info[] = "Exclude Duplikat";
    if (!empty($search)) $filter_info[] = "Pencarian: " . $search;
    
    $filter_text = empty($filter_info) ? "Semua Data" : implode(" | ", $filter_info);
    
    $role_display = ucfirst($role);
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Export Leads - TaufikMarie.com</title>';
    echo '<style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 30px; 
            color: #333;
            line-height: 1.5;
        }
        h1 { 
            color: #1B4A3C; 
            font-size: 28px; 
            margin-bottom: 10px;
            border-bottom: 3px solid #D64F3C;
            padding-bottom: 10px;
        }
        .header {
            margin-bottom: 25px;
        }
        .filter-info { 
            background: #f0f7f4; 
            padding: 15px 20px; 
            border-radius: 8px; 
            margin: 20px 0;
            border-left: 5px solid #D64F3C;
            font-size: 13px;
        }
        .summary {
            background: #f9f9f9;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #ddd;
        }
        .total-badge {
            background: #1B4A3C;
            color: white;
            padding: 8px 20px;
            border-radius: 40px;
            font-weight: bold;
            font-size: 16px;
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            font-size: 11px;
            margin-top: 20px;
        }
        th { 
            background: #1B4A3C; 
            color: white; 
            padding: 10px 6px; 
            text-align: left; 
            font-weight: 600;
            white-space: nowrap;
        }
        td { 
            padding: 8px 6px; 
            border: 1px solid #ddd; 
            vertical-align: middle;
        }
        tr:nth-child(even) { background: #f9f9f9; }
        tr:hover { background: #f1f1f1; }
        .hot { background: #ffebee; }
        .warm { background: #fff3e0; }
        .cold { background: #e3f2fd; }
        .duplicate { background: #FFF3CD; }
        .badge { 
            display: inline-block; 
            padding: 3px 8px; 
            border-radius: 20px; 
            font-size: 10px; 
            font-weight: bold;
            white-space: nowrap;
        }
        .badge-success { background: #2A9D8F; color: white; }
        .badge-warning { background: #E9C46A; color: #1A2A24; }
        .badge-danger { background: #D64F3C; color: white; }
        .badge-info { background: #4A90E2; color: white; }
        .badge-primary { background: #1B4A3C; color: white; }
        .footer { 
            margin-top: 40px; 
            padding: 20px; 
            background: #f5f5f5; 
            border-radius: 8px;
            font-size: 11px; 
            color: #666; 
            text-align: center;
            border-top: 2px solid #ddd;
        }
        .footer strong { color: #1B4A3C; }
        .nowrap { white-space: nowrap; }
        .whatsapp-link { color: #25D366; text-decoration: none; }
        @media print {
            body { margin: 0.5in; }
            .no-print { display: none; }
        }
    </style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h1>📊 TaufikMarie.com - Export Data Leads</h1>';
    echo '<div class="header">';
    echo '<p style="font-size: 14px; color: #666;">';
    echo '<strong>Role:</strong> ' . $role_display . ' | <strong>User:</strong> ' . htmlspecialchars($user_name);
    echo '</p>';
    echo '</div>';
    
    echo '<div class="filter-info">';
    echo '<strong>📋 FILTER:</strong> ' . htmlspecialchars($filter_text);
    echo '</div>';
    
    echo '<div class="summary">';
    echo '<div>';
    echo '<strong>📅 Tanggal:</strong> ' . date('d/m/Y H:i:s') . ' WIB';
    echo '</div>';
    echo '<div class="total-badge">Total: ' . count($leads) . ' Leads</div>';
    echo '</div>';
    
    if (empty($leads)) {
        echo '<div style="text-align: center; padding: 60px; background: #f9f9f9; border: 2px dashed #ccc; border-radius: 12px;">';
        echo '<div style="font-size: 48px; margin-bottom: 20px;">📭</div>';
        echo '<h3 style="color: #666; margin-bottom: 10px;">Tidak Ada Data</h3>';
        echo '<p style="color: #999;">Tidak ditemukan leads yang sesuai dengan filter yang dipilih</p>';
        echo '</div>';
    } else {
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Tanggal</th>';
        echo '<th>Nama</th>';
        echo '<th>Kontak</th>';
        echo '<th>Lokasi</th>';
        echo '<th>Developer</th>';
        echo '<th>Marketing</th>';
        echo '<th>Status</th>';
        echo '<th>Score</th>';
        echo '<th>Cluster</th>';
        echo '<th>Block</th>';
        echo '<th>Unit</th>';
        echo '<th>Tipe Unit</th>';
        echo '<th>Program</th>';
        echo '<th>Harga</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($leads as $lead) {
            $score_class = '';
            if ($lead['lead_score'] >= 80) $score_class = 'hot';
            elseif ($lead['lead_score'] >= 60) $score_class = 'warm';
            else $score_class = 'cold';
            
            $dup_class = !empty($lead['is_duplicate_warning']) ? 'duplicate' : '';
            $full_name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
            if (empty($full_name)) $full_name = '-';
            
            $status = $lead['status'] ?? 'Baru';
            $status_class = '';
            if ($status == 'Baru') $status_class = 'badge-info';
            elseif ($status == 'Follow Up') $status_class = 'badge-warning';
            elseif ($status == 'Survey') $status_class = 'badge-warning';
            elseif ($status == 'Booking') $status_class = 'badge-primary';
            elseif ($status == 'Deal KPR') $status_class = 'badge-success';
            elseif ($status == 'Deal Tunai') $status_class = 'badge-success';
            elseif ($status == 'Tolak Slik') $status_class = 'badge-danger';
            elseif ($status == 'Tidak Minat') $status_class = 'badge-danger';
            elseif ($status == 'Batal') $status_class = 'badge-danger';
            else $status_class = 'badge-info';
            
            $marketing_name = $lead['marketing_name'] ?? ($lead['assigned_marketing_team_id'] ? 'Marketing #' . $lead['assigned_marketing_team_id'] : '-');
            $developer_name = $lead['developer_name'] ?? '-';
            
            // Format harga
            $harga_unit = !empty($lead['unit_harga']) ? 'Rp ' . number_format($lead['unit_harga'], 0, ',', '.') : '-';
            
            echo '<tr class="' . $dup_class . '">';
            echo '<td><strong>#' . $lead['id'] . '</strong></td>';
            echo '<td class="nowrap">' . date('d/m/Y', strtotime($lead['created_at'])) . '</td>';
            echo '<td><strong>' . htmlspecialchars($full_name) . '</strong></td>';
            echo '<td>';
            if (!empty($lead['phone'])) {
                echo '<div><span style="color: #25D366;">📱</span> ' . htmlspecialchars($lead['phone']) . '</div>';
            }
            if (!empty($lead['email'])) {
                echo '<div style="font-size: 10px; color: #666;">' . htmlspecialchars($lead['email']) . '</div>';
            }
            echo '</td>';
            echo '<td>' . htmlspecialchars($lead['location_display'] ?? $lead['location_key']) . '</td>';
            echo '<td>' . htmlspecialchars($developer_name) . '</td>';
            echo '<td>' . htmlspecialchars($marketing_name) . '</td>';
            echo '<td><span class="badge ' . $status_class . '">' . htmlspecialchars($status) . '</span></td>';
            echo '<td class="' . $score_class . '"><strong>' . ($lead['lead_score'] ?? 0) . '</strong></td>';
            echo '<td>' . htmlspecialchars($lead['nama_cluster'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['nama_block'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['nomor_unit'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['unit_tipe'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($lead['unit_program'] ?? '-') . '</td>';
            echo '<td>' . $harga_unit . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    echo '<div class="footer">';
    echo '<p><strong>© ' . date('Y') . ' TaufikMarie.com</strong> — Premium Export System v4.0</p>';
    echo '<p>Dokumen ini digenerate berdasarkan role: ' . $role_display . '</p>';
    echo '</div>';
    
    echo '</body>';
    echo '</html>';
    exit();
}

// ========== EXPORT CSV PREMIUM ==========
function exportCSV($leads, $filter, $role, $user_name) {
    $filename = 'leads_export_' . $role . '_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header CSV
    fputcsv($output, [
        'ID',
        'Tanggal',
        'Waktu',
        'Nama Depan',
        'Nama Belakang',
        'Nama Lengkap',
        'WhatsApp',
        'Email',
        'Lokasi',
        'Developer',
        'Marketing',
        'Tipe Unit (Leads)',
        'Program (Leads)',
        'Status Lead',
        'Lead Score',
        'Kota',
        'Alamat',
        'Sumber',
        'Duplicate Warning',
        'Catatan',
        'Cluster',
        'Block',
        'Nomor Unit',
        'Tipe Unit (Booking)',
        'Program Unit',
        'Status Unit',
        'Harga Unit',
        'Harga Booking'
    ]);
    
    foreach ($leads as $lead) {
        $full_name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
        $marketing_name = $lead['marketing_name'] ?? ($lead['assigned_marketing_team_id'] ? 'Marketing #' . $lead['assigned_marketing_team_id'] : '');
        $developer_name = $lead['developer_name'] ?? '';
        
        fputcsv($output, [
            $lead['id'],
            date('Y-m-d', strtotime($lead['created_at'])),
            date('H:i:s', strtotime($lead['created_at'])),
            $lead['first_name'] ?? '',
            $lead['last_name'] ?? '',
            $full_name,
            $lead['phone'] ?? '',
            $lead['email'] ?? '',
            $lead['location_display'] ?? $lead['location_key'],
            $developer_name,
            $marketing_name,
            $lead['unit_type'] ?? 'Type 36/60',
            $lead['program'] ?? 'Subsidi',
            $lead['status'] ?? 'Baru',
            $lead['lead_score'] ?? 0,
            $lead['city'] ?? '',
            $lead['address'] ?? '',
            $lead['source'] ?? 'website',
            !empty($lead['is_duplicate_warning']) ? 'YA' : 'TIDAK',
            $lead['notes'] ?? '',
            $lead['nama_cluster'] ?? '',
            $lead['nama_block'] ?? '',
            $lead['nomor_unit'] ?? '',
            $lead['unit_tipe'] ?? '',
            $lead['unit_program'] ?? '',
            $lead['unit_status'] ?? '',
            $lead['unit_harga'] ?? '',
            $lead['unit_harga_booking'] ?? ''
        ]);
    }
    
    fclose($output);
    exit();
}
?>