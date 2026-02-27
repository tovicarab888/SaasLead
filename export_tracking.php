<?php
/**
 * API: EXPORT TRACKING - Export ke CSV/Excel/PDF
 * Version: 1.0.3 - FIXED response_code column (lagi)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek API Key atau session
$api_key = isset($_GET['api_key']) ? $_GET['api_key'] : '';
if ($api_key !== 'taufikmarie7878' && !checkAuth()) {
    header('Location: ../login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

try {
    // Ambil parameter
    $format = $_GET['format'] ?? 'csv';
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $platform = $_GET['platform'] ?? 'all';
    $developer_id = $_GET['developer_id'] ?? 'all';
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    // Validasi tanggal
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $start_date = date('Y-m-d', strtotime('-7 days'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $end_date = date('Y-m-d');
    }
    
    // Build conditions
    $conditions = ["DATE(tl.created_at) BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];
    
    if ($platform !== 'all') {
        $conditions[] = "tl.pixel_type = ?";
        $params[] = $platform;
    }
    
    if ($status !== 'all') {
        $conditions[] = "tl.status = ?";
        $params[] = $status;
    }
    
    // Filter developer berdasarkan role
    if (function_exists('isDeveloper') && isDeveloper() && isset($_SESSION['user_id'])) {
        $conditions[] = "tl.developer_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($developer_id !== 'all' && is_numeric($developer_id)) {
        $conditions[] = "tl.developer_id = ?";
        $params[] = intval($developer_id);
    }
    
    if (!empty($search)) {
        $conditions[] = "(tl.event_id LIKE ? OR tl.lead_id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $conditions);
    
    // Ambil data - TANPA response_code
    $sql = "
        SELECT 
            tl.id,
            tl.created_at,
            tl.pixel_type,
            tl.event_name,
            tl.event_id,
            tl.lead_id,
            tl.status,
            tl.response,
            COALESCE(u.nama_lengkap, 'Global') as developer_name,
            l.first_name,
            l.last_name,
            l.phone
        FROM tracking_logs tl
        LEFT JOIN users u ON tl.developer_id = u.id
        LEFT JOIN leads l ON tl.lead_id = l.id
        WHERE $where_clause
        ORDER BY tl.created_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Export berdasarkan format
    if ($format === 'csv') {
        exportCSV($data);
    } elseif ($format === 'excel') {
        exportExcel($data);
    } elseif ($format === 'pdf') {
        exportPDF($data, $start_date, $end_date);
    } else {
        header('Location: ../tracking_report.php');
    }
    
} catch (Exception $e) {
    error_log("Error in export_tracking: " . $e->getMessage());
    die("Export failed: " . $e->getMessage());
}

function exportCSV($data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tracking_export_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, [
        'ID', 'Timestamp', 'Developer', 'Platform', 'Event Name', 
        'Event ID', 'Lead ID', 'Lead Name', 'Lead Phone', 'Status'
    ]);
    
    // Data
    foreach ($data as $row) {
        $lead_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['developer_name'] ?? 'Global',
            $row['pixel_type'],
            $row['event_name'] ?? '-',
            $row['event_id'] ?? '-',
            $row['lead_id'] ?? '-',
            $lead_name ?: '-',
            $row['phone'] ?? '-',
            $row['status']
        ]);
    }
    
    fclose($output);
    exit();
}

function exportExcel($data) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename=tracking_export_' . date('Ymd_His') . '.xls');
    
    echo '<html>';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Header
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Timestamp</th>';
    echo '<th>Developer</th>';
    echo '<th>Platform</th>';
    echo '<th>Event Name</th>';
    echo '<th>Event ID</th>';
    echo '<th>Lead ID</th>';
    echo '<th>Lead Name</th>';
    echo '<th>Lead Phone</th>';
    echo '<th>Status</th>';
    echo '</tr>';
    
    // Data
    foreach ($data as $row) {
        $lead_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        
        echo '<tr>';
        echo '<td>' . $row['id'] . '</td>';
        echo '<td>' . $row['created_at'] . '</td>';
        echo '<td>' . ($row['developer_name'] ?? 'Global') . '</td>';
        echo '<td>' . $row['pixel_type'] . '</td>';
        echo '<td>' . ($row['event_name'] ?? '-') . '</td>';
        echo '<td>' . ($row['event_id'] ?? '-') . '</td>';
        echo '<td>' . ($row['lead_id'] ?? '-') . '</td>';
        echo '<td>' . ($lead_name ?: '-') . '</td>';
        echo '<td>' . ($row['phone'] ?? '-') . '</td>';
        echo '<td>' . $row['status'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}

function exportPDF($data, $start_date, $end_date) {
    // Simple HTML export
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename=tracking_export_' . date('Ymd_His') . '.html');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Tracking Report</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
    echo 'h1 { color: #1B4A3C; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background: #1B4A3C; color: white; padding: 10px; }';
    echo 'td { border: 1px solid #ddd; padding: 8px; }';
    echo '.header { margin-bottom: 20px; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="header">';
    echo '<h1>Tracking Performance Report</h1>';
    echo '<p>Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '</p>';
    echo '<p>Total Events: ' . count($data) . '</p>';
    echo '</div>';
    
    echo '<table>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Timestamp</th>';
    echo '<th>Developer</th>';
    echo '<th>Platform</th>';
    echo '<th>Event Name</th>';
    echo '<th>Event ID</th>';
    echo '<th>Lead ID</th>';
    echo '<th>Lead Name</th>';
    echo '<th>Phone</th>';
    echo '<th>Status</th>';
    echo '</tr>';
    
    foreach ($data as $row) {
        $lead_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        
        echo '<tr>';
        echo '<td>' . $row['id'] . '</td>';
        echo '<td>' . $row['created_at'] . '</td>';
        echo '<td>' . ($row['developer_name'] ?? 'Global') . '</td>';
        echo '<td>' . $row['pixel_type'] . '</td>';
        echo '<td>' . ($row['event_name'] ?? '-') . '</td>';
        echo '<td>' . ($row['event_id'] ?? '-') . '</td>';
        echo '<td>' . ($row['lead_id'] ?? '-') . '</td>';
        echo '<td>' . ($lead_name ?: '-') . '</td>';
        echo '<td>' . ($row['phone'] ?? '-') . '</td>';
        echo '<td>' . $row['status'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}
?>