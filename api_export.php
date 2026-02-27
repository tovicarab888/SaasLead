<?php
/**
 * API_EXPORT.PHP - TAUFIKMARIE.COM
 * Version: 3.0.0 - Export untuk Developer dengan Filter Lengkap
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Cek session login
if (!checkAuth()) {
    http_response_code(401);
    die('Unauthorized');
}

// Cek role developer
if (!isDeveloper()) {
    http_response_code(403);
    die('Forbidden');
}

$conn = getDB();
if (!$conn) {
    die('Database connection failed');
}

$developer_id = $_SESSION['user_id'];
$location_access = $_SESSION['location_access'] ?? '';
$developer_name = $_SESSION['nama_lengkap'] ?? 'Developer';

$locations = explode(',', $location_access);
if (empty($locations)) {
    die('No location access');
}

$placeholders = implode(',', array_fill(0, count($locations), '?'));

// ========== AMBIL FILTER DARI URL ==========
$format = $_GET['format'] ?? 'csv';
$search = $_GET['search'] ?? '';
$location_filter = $_GET['location'] ?? '';
$status_filter = $_GET['status'] ?? '';

// ========== BUILD QUERY DENGAN FILTER ==========
$sql = "SELECT l.*, loc.display_name as location_display, loc.icon 
        FROM leads l 
        LEFT JOIN locations loc ON l.location_key = loc.location_key 
        WHERE l.location_key IN ($placeholders)
        AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";

$params = $locations;

if ($search) {
    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? OR l.city LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}

if ($location_filter && in_array($location_filter, $locations)) {
    $sql .= " AND l.location_key = ?";
    $params[] = $location_filter;
}

if ($status_filter) {
    $sql .= " AND l.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY l.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// ========== EXPORT BERDASARKAN FORMAT ==========
switch ($format) {
    case 'csv':
        exportCSV($leads, $developer_name, $search, $location_filter, $status_filter);
        break;
    case 'excel':
        exportExcel($leads, $developer_name, $search, $location_filter, $status_filter);
        break;
    case 'pdf':
        exportPDF($leads, $developer_name, $search, $location_filter, $status_filter);
        break;
    default:
        exportCSV($leads, $developer_name, $search, $location_filter, $status_filter);
}

// ========== CSV EXPORT ==========
function exportCSV($leads, $developer_name, $search, $location, $status) {
    $filename = 'leads_' . $developer_name . '_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    $filter_info = [];
    if ($search) $filter_info[] = "Cari: $search";
    if ($location) $filter_info[] = "Lokasi: $location";
    if ($status) $filter_info[] = "Status: $status";
    $filter_text = empty($filter_info) ? "Semua Data" : implode(", ", $filter_info);
    
    // Header
    fputcsv($output, [
        'ID', 'Nama Depan', 'Nama Belakang', 'Nama Lengkap', 'WhatsApp', 'Email',
        'Lokasi', 'Kota', 'Tipe Unit', 'Program', 'Status', 'Score',
        'Sumber', 'Tanggal', 'Catatan', 'Duplicate Warning'
    ]);
    
    foreach ($leads as $lead) {
        $full_name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
        fputcsv($output, [
            $lead['id'],
            $lead['first_name'],
            $lead['last_name'] ?? '',
            $full_name,
            $lead['phone'],
            $lead['email'] ?? '',
            $lead['location_display'] ?? $lead['location_key'],
            $lead['city'] ?? '',
            $lead['unit_type'] ?? 'Type 36/60',
            $lead['program'] ?? 'Subsidi',
            $lead['status'] ?? 'Baru',
            $lead['lead_score'] ?? 0,
            $lead['source'] ?? 'website',
            date('d/m/Y', strtotime($lead['created_at'])),
            $lead['notes'] ?? '',
            $lead['is_duplicate_warning'] ? 'YA' : 'TIDAK'
        ]);
    }
    
    fclose($output);
    exit();
}

// ========== EXCEL EXPORT ==========
function exportExcel($leads, $developer_name, $search, $location, $status) {
    $filename = 'leads_' . $developer_name . '_' . date('Y-m-d_His') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $filter_info = [];
    if ($search) $filter_info[] = "Pencarian: $search";
    if ($location) $filter_info[] = "Lokasi: $location";
    if ($status) $filter_info[] = "Status: $status";
    $filter_text = empty($filter_info) ? "Semua Data" : implode(" | ", $filter_info);
    
    echo '<html>';
    echo '<head><meta charset="UTF-8">';
    echo '<style>
        th { background: #1B4A3C; color: white; padding: 8px; }
        td { padding: 6px; border: 1px solid #ccc; }
        .duplicate { background: #FFF3CD; }
    </style>';
    echo '</head><body>';
    
    echo '<h2>Data Leads - ' . htmlspecialchars($developer_name) . '</h2>';
    echo '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . ' | Filter: ' . $filter_text . ' | Total: ' . count($leads) . '</p>';
    
    echo '<table border="1">';
    echo '<tr>
        <th>ID</th><th>Nama</th><th>WhatsApp</th><th>Email</th><th>Lokasi</th>
        <th>Kota</th><th>Tipe Unit</th><th>Program</th><th>Status</th>
        <th>Score</th><th>Sumber</th><th>Tanggal</th><th>Duplicate</th>
    </tr>';
    
    foreach ($leads as $lead) {
        $dup_class = $lead['is_duplicate_warning'] ? 'duplicate' : '';
        $full_name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
        
        echo '<tr class="' . $dup_class . '">';
        echo '<td>#' . $lead['id'] . '</td>';
        echo '<td>' . htmlspecialchars($full_name) . '</td>';
        echo '<td>' . htmlspecialchars($lead['phone']) . '</td>';
        echo '<td>' . htmlspecialchars($lead['email'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($lead['location_display'] ?? $lead['location_key']) . '</td>';
        echo '<td>' . htmlspecialchars($lead['city'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($lead['unit_type'] ?? 'Type 36/60') . '</td>';
        echo '<td>' . htmlspecialchars($lead['program'] ?? 'Subsidi') . '</td>';
        echo '<td>' . htmlspecialchars($lead['status'] ?? 'Baru') . '</td>';
        echo '<td><strong>' . $lead['lead_score'] . '</strong></td>';
        echo '<td>' . htmlspecialchars($lead['source'] ?? 'website') . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($lead['created_at'])) . '</td>';
        echo '<td>' . ($lead['is_duplicate_warning'] ? '⚠️ YA' : '-') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '<p><em>© ' . date('Y') . ' TaufikMarie.com</em></p>';
    echo '</body></html>';
    
    exit();
}

// ========== PDF EXPORT (HTML) ==========
function exportPDF($leads, $developer_name, $search, $location, $status) {
    $filename = 'leads_' . $developer_name . '_' . date('Y-m-d_His') . '.html';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $filter_info = [];
    if ($search) $filter_info[] = "Pencarian: $search";
    if ($location) $filter_info[] = "Lokasi: $location";
    if ($status) $filter_info[] = "Status: $status";
    $filter_text = empty($filter_info) ? "Semua Data" : implode(" | ", $filter_info);
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head><meta charset="UTF-8"><title>Data Leads - ' . htmlspecialchars($developer_name) . '</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #1B4A3C; }
        table { border-collapse: collapse; width: 100%; font-size: 12px; }
        th { background: #1B4A3C; color: white; padding: 8px; text-align: left; }
        td { padding: 6px; border: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
        .duplicate { background: #FFF3CD; }
        .footer { margin-top: 20px; font-size: 11px; color: #666; }
    </style>';
    echo '</head><body>';
    
    echo '<h1>📊 Data Leads - ' . htmlspecialchars($developer_name) . '</h1>';
    echo '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . ' | Filter: ' . $filter_text . ' | Total: ' . count($leads) . '</p>';
    
    echo '<table>';
    echo '<tr>
        <th>ID</th><th>Nama</th><th>WhatsApp</th><th>Email</th><th>Lokasi</th>
        <th>Kota</th><th>Tipe Unit</th><th>Program</th><th>Status</th>
        <th>Score</th><th>Sumber</th><th>Tanggal</th>
    </tr>';
    
    foreach ($leads as $lead) {
        $dup_class = $lead['is_duplicate_warning'] ? 'duplicate' : '';
        $full_name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
        
        echo '<tr class="' . $dup_class . '">';
        echo '<td>#' . $lead['id'] . '</td>';
        echo '<td><strong>' . htmlspecialchars($full_name) . '</strong></td>';
        echo '<td>' . htmlspecialchars($lead['phone']) . '</td>';
        echo '<td>' . htmlspecialchars($lead['email'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($lead['location_display'] ?? $lead['location_key']) . '</td>';
        echo '<td>' . htmlspecialchars($lead['city'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($lead['unit_type'] ?? 'Type 36/60') . '</td>';
        echo '<td>' . htmlspecialchars($lead['program'] ?? 'Subsidi') . '</td>';
        echo '<td>' . htmlspecialchars($lead['status'] ?? 'Baru') . '</td>';
        echo '<td><strong>' . $lead['lead_score'] . '</strong></td>';
        echo '<td>' . htmlspecialchars($lead['source'] ?? 'website') . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($lead['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '<div class="footer">';
    echo '<p>© ' . date('Y') . ' TaufikMarie.com - Developer Export System</p>';
    echo '</div>';
    
    echo '</body></html>';
    
    exit();
}
?>