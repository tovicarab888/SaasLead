<?php
/**
 * EXPORT_LAPORAN_KEUANGAN.PHP - LEADENGINE API
 * Version: 2.0.0 - FIXED: Menggunakan tanggal_cair, filter marketing_type
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/export_keuangan.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Cek autentikasi (hanya finance, manager_developer, admin)
if (!isFinance() && !isManagerDeveloper() && !isAdmin() && !isManager()) {
    http_response_code(403);
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Forbidden - Hanya untuk finance']);
    } else {
        die('Akses ditolak. Halaman ini hanya untuk Finance.');
    }
    exit();
}

// Rate limiting
$client_ip = getClientIP();
if (!checkRateLimit('export_keuangan_' . $client_ip, 10, 300)) {
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
$developer_id = 0;

if (isDeveloper()) {
    $developer_id = $_SESSION['user_id'];
} elseif (isFinance() || isManagerDeveloper()) {
    $developer_id = $_SESSION['developer_id'] ?? 0;
} elseif (isAdmin() || isManager()) {
    $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
}

// ========== AMBIL PARAMETER FILTER ==========
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
$marketing_type = isset($_GET['marketing_type']) ? $_GET['marketing_type'] : ''; // 'internal' atau 'external'
$status = isset($_GET['status']) ? $_GET['status'] : 'cair'; // 'cair', 'pending', 'all'

// ========== BANGUN QUERY ==========
$sql = "
    SELECT 
        k.id as komisi_id,
        k.komisi_final,
        k.status as komisi_status,
        k.tanggal_cair,
        k.created_at as komisi_created_at,
        k.assigned_type,
        
        l.id as lead_id,
        l.first_name,
        l.last_name,
        l.phone as customer_phone,
        l.created_at as lead_created_at,
        l.status as lead_status,
        
        m.id as marketing_id,
        m.nama_lengkap as marketing_name,
        m.phone as marketing_phone,
        mt.type_name as marketing_type_name,
        mt.commission_type,
        mt.commission_value,
        
        u.id as unit_id,
        u.nomor_unit,
        u.tipe_unit,
        u.program,
        u.harga as unit_harga,
        
        c.nama_cluster,
        b.nama_block,
        
        dev.id as developer_id,
        dev.nama_lengkap as developer_name,
        
        bk.nama_bank,
        bk.nomor_rekening as bank_rekening,
        bk.atas_nama as bank_atas_nama
        
    FROM komisi_logs k
    LEFT JOIN leads l ON k.lead_id = l.id
    LEFT JOIN marketing_team m ON k.marketing_id = m.id
    LEFT JOIN marketing_types mt ON m.marketing_type_id = mt.id
    LEFT JOIN units u ON k.unit_id = u.id
    LEFT JOIN clusters c ON u.cluster_id = c.id
    LEFT JOIN blocks b ON u.block_id = b.id
    LEFT JOIN users dev ON k.developer_id = dev.id
    LEFT JOIN banks bk ON m.bank_id = bk.id
    WHERE 1=1
";

$params = [];

// Filter by developer
if ($developer_id > 0) {
    $sql .= " AND k.developer_id = ?";
    $params[] = $developer_id;
}

// Filter by date - GUNAKAN TANGGAL CAIR untuk status cair
if ($status === 'cair') {
    // Untuk komisi yang sudah cair, gunakan tanggal_cair
    if (!empty($start_date) && !empty($end_date)) {
        $sql .= " AND k.status = 'cair' AND DATE(k.tanggal_cair) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    } else {
        $sql .= " AND k.status = 'cair'";
    }
} elseif ($status === 'pending') {
    // Untuk pending, gunakan created_at
    $sql .= " AND k.status = 'pending'";
    if (!empty($start_date) && !empty($end_date)) {
        $sql .= " AND DATE(k.created_at) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
} else {
    // All status, gunakan created_at
    if (!empty($start_date) && !empty($end_date)) {
        $sql .= " AND DATE(k.created_at) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
}

// Filter by marketing
if ($marketing_id > 0) {
    $sql .= " AND k.marketing_id = ?";
    $params[] = $marketing_id;
}

// Filter by marketing type (internal/external)
if (!empty($marketing_type) && in_array($marketing_type, ['internal', 'external'])) {
    $sql .= " AND k.assigned_type = ?";
    $params[] = $marketing_type;
}

$sql .= " ORDER BY k.tanggal_cair DESC, k.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$komisi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== HITUNG TOTAL ==========
$total_komisi = 0;
$total_komisi_cair = 0;
$total_komisi_pending = 0;
$total_komisi_batal = 0;

foreach ($komisi_list as $k) {
    $total_komisi += $k['komisi_final'] ?? 0;
    
    if (($k['komisi_status'] ?? '') === 'cair') {
        $total_komisi_cair += $k['komisi_final'] ?? 0;
    } elseif (($k['komisi_status'] ?? '') === 'pending') {
        $total_komisi_pending += $k['komisi_final'] ?? 0;
    } elseif (($k['komisi_status'] ?? '') === 'batal') {
        $total_komisi_batal += $k['komisi_final'] ?? 0;
    }
}

// ========== AMBIL NAMA DEVELOPER ==========
$developer_name = 'Developer';
if ($developer_id > 0) {
    $stmt = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
    $stmt->execute([$developer_id]);
    $developer_name = $stmt->fetchColumn() ?: 'Developer';
}

// ========== EKSPOR BERDASARKAN FORMAT ==========
switch ($format) {
    case 'excel':
        exportExcel($komisi_list, $developer_name, $start_date, $end_date, $status, 
                   $total_komisi, $total_komisi_cair, $total_komisi_pending, $total_komisi_batal);
        break;
    case 'csv':
        exportCSV($komisi_list, $developer_name, $start_date, $end_date, $status,
                 $total_komisi, $total_komisi_cair, $total_komisi_pending, $total_komisi_batal);
        break;
    case 'pdf':
        exportPDF($komisi_list, $developer_name, $start_date, $end_date, $status,
                 $total_komisi, $total_komisi_cair, $total_komisi_pending, $total_komisi_batal);
        break;
    case 'json':
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $komisi_list,
            'summary' => [
                'total_komisi' => $total_komisi,
                'cair' => $total_komisi_cair,
                'pending' => $total_komisi_pending,
                'batal' => $total_komisi_batal,
                'total_records' => count($komisi_list)
            ],
            'period' => "$start_date s/d $end_date",
            'status_filter' => $status
        ], JSON_PRETTY_PRINT);
        exit();
    default:
        exportExcel($komisi_list, $developer_name, $start_date, $end_date, $status,
                   $total_komisi, $total_komisi_cair, $total_komisi_pending, $total_komisi_batal);
}

// ========== FUNGSI EXPORT EXCEL ==========
function exportExcel($komisi_list, $developer_name, $start_date, $end_date, $status_filter,
                    $total_komisi, $total_komisi_cair, $total_komisi_pending, $total_komisi_batal) {
    
    $filename = 'laporan_keuangan_' . $developer_name . '_' . date('Y-m-d_His') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $status_text = $status_filter === 'cair' ? 'Sudah Cair' : 
                  ($status_filter === 'pending' ? 'Pending' : 'Semua Status');
    
    echo '<html>';
    echo '<head><meta charset="UTF-8">';
    echo '<style>
        body { font-family: Arial, sans-serif; }
        h1 { color: #1B4A3C; }
        table { border-collapse: collapse; width: 100%; font-size: 11px; }
        th { background: #1B4A3C; color: white; padding: 8px; }
        td { padding: 6px; border: 1px solid #ccc; }
        .total-row { background: #E7F3EF; font-weight: bold; }
        .text-right { text-align: right; }
        .cair { color: #2A9D8F; font-weight: bold; }
        .pending { color: #E9C46A; font-weight: bold; }
        .batal { color: #D64F3C; font-weight: bold; text-decoration: line-through; }
    </style>';
    echo '</head><body>';
    
    echo '<h1>Laporan Keuangan - Komisi Marketing</h1>';
    echo '<p>Developer: ' . htmlspecialchars($developer_name) . '</p>';
    echo '<p>Periode: ' . $start_date . ' s/d ' . $end_date . ' | Status: ' . $status_text . '</p>';
    echo '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    echo '<p>Total Transaksi: ' . count($komisi_list) . '</p>';
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>Tanggal</th>';
    echo '<th>Marketing</th>';
    echo '<th>Tipe</th>';
    echo '<th>Customer</th>';
    echo '<th>Unit</th>';
    echo '<th>Cluster/Block</th>';
    echo '<th>Harga Unit</th>';
    echo '<th>Komisi</th>';
    echo '<th>Status</th>';
    echo '<th>Tanggal Cair</th>';
    echo '<th>Bank</th>';
    echo '<th>Keterangan</th>';
    echo '</tr>';
    
    $no = 1;
    foreach ($komisi_list as $k) {
        $tanggal = $k['komisi_status'] === 'cair' && !empty($k['tanggal_cair']) && $k['tanggal_cair'] != '0000-00-00 00:00:00'
            ? date('d/m/Y', strtotime($k['tanggal_cair']))
            : date('d/m/Y', strtotime($k['komisi_created_at']));
        
        $customer = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
        if (empty($customer)) $customer = '-';
        
        $unit_info = ($k['nomor_unit'] ?? '') . ' (' . ($k['tipe_unit'] ?? '') . ')';
        $cluster_block = ($k['nama_cluster'] ?? '') . ' - Blok ' . ($k['nama_block'] ?? '');
        
        $status_class = $k['komisi_status'] ?? '';
        $status_display = $k['komisi_status'] ?? '-';
        
        $bank_info = '';
        if (!empty($k['nama_bank']) && !empty($k['bank_rekening'])) {
            $bank_info = $k['nama_bank'] . ' - ' . substr($k['bank_rekening'], 0, 4) . '****' . substr($k['bank_rekening'], -4);
        } elseif (!empty($k['marketing_name'])) {
            $bank_info = 'Belum input rekening';
        }
        
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . $tanggal . '</td>';
        echo '<td><strong>' . htmlspecialchars($k['marketing_name'] ?? '-') . '</strong></td>';
        echo '<td>' . htmlspecialchars($k['assigned_type'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($customer) . '<br><small>' . htmlspecialchars($k['customer_phone'] ?? '') . '</small></td>';
        echo '<td>' . htmlspecialchars($unit_info) . '</td>';
        echo '<td>' . htmlspecialchars($cluster_block) . '</td>';
        echo '<td class="text-right">Rp ' . number_format($k['unit_harga'] ?? 0, 0, ',', '.') . '</td>';
        echo '<td class="text-right"><strong>Rp ' . number_format($k['komisi_final'] ?? 0, 0, ',', '.') . '</strong></td>';
        echo '<td class="' . $status_class . '">' . $status_display . '</td>';
        echo '<td>' . ($k['komisi_status'] === 'cair' && !empty($k['tanggal_cair']) ? date('d/m/Y', strtotime($k['tanggal_cair'])) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($bank_info) . '</td>';
        echo '<td>' . htmlspecialchars($k['catatan'] ?? '-') . '</td>';
        echo '</tr>';
    }
    
    // Baris total
    echo '<tr class="total-row">';
    echo '<td colspan="8"><strong>TOTAL KOMISI</strong></td>';
    echo '<td class="text-right"><strong>Rp ' . number_format($total_komisi, 0, ',', '.') . '</strong></td>';
    echo '<td colspan="4"></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="13">&nbsp;</td>';
    echo '</tr>';
    
    echo '<tr><td colspan="13"><strong>Rekap per Status:</strong></td></tr>';
    echo '<tr>';
    echo '<td colspan="8">Sudah Cair</td>';
    echo '<td class="text-right cair">Rp ' . number_format($total_komisi_cair, 0, ',', '.') . '</td>';
    echo '<td colspan="4"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="8">Pending</td>';
    echo '<td class="text-right pending">Rp ' . number_format($total_komisi_pending, 0, ',', '.') . '</td>';
    echo '<td colspan="4"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="8">Batal</td>';
    echo '<td class="text-right batal">Rp ' . number_format($total_komisi_batal, 0, ',', '.') . '</td>';
    echo '<td colspan="4"></td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

// ========== FUNGSI EXPORT CSV ==========
function exportCSV($komisi_list, $developer_name, $start_date, $end_date, $status_filter,
                  $total_komisi, $total_komisi_cair, $total_komisi_pending, $total_komisi_batal) {
    
    $filename = 'laporan_keuangan_' . $developer_name . '_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    fputcsv($output, [
        'No', 'Tanggal', 'Marketing', 'Tipe', 'Customer', 'No. Customer',
        'Unit', 'Cluster/Block', 'Harga Unit', 'Komisi', 'Status',
        'Tanggal Cair', 'Bank', 'Keterangan'
    ]);
    
    $no = 1;
    foreach ($komisi_list as $k) {
        $tanggal = $k['komisi_status'] === 'cair' && !empty($k['tanggal_cair']) && $k['tanggal_cair'] != '0000-00-00 00:00:00'
            ? date('Y-m-d', strtotime($k['tanggal_cair']))
            : date('Y-m-d', strtotime($k['komisi_created_at']));
        
        $customer = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
        $unit_info = ($k['nomor_unit'] ?? '') . ' (' . ($k['tipe_unit'] ?? '') . ')';
        $cluster_block = ($k['nama_cluster'] ?? '') . ' - Blok ' . ($k['nama_block'] ?? '');
        
        $bank_info = '';
        if (!empty($k['nama_bank']) && !empty($k['bank_rekening'])) {
            $bank_info = $k['nama_bank'] . ' - ' . substr($k['bank_rekening'], 0, 4) . '****' . substr($k['bank_rekening'], -4);
        }
        
        fputcsv($output, [
            $no++,
            $tanggal,
            $k['marketing_name'] ?? '',
            $k['assigned_type'] ?? '',
            $customer,
            $k['customer_phone'] ?? '',
            $unit_info,
            $cluster_block,
            $k['unit_harga'] ?? 0,
            $k['komisi_final'] ?? 0,
            $k['komisi_status'] ?? '',
            $k['komisi_status'] === 'cair' && !empty($k['tanggal_cair']) ? date('Y-m-d', strtotime($k['tanggal_cair'])) : '',
            $bank_info,
            $k['catatan'] ?? ''
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['TOTAL', '', '', '', '', '', '', '', '', $total_komisi]);
    fputcsv($output, ['Sudah Cair', '', '', '', '', '', '', '', '', $total_komisi_cair]);
    fputcsv($output, ['Pending', '', '', '', '', '', '', '', '', $total_komisi_pending]);
    fputcsv($output, ['Batal', '', '', '', '', '', '', '', '', $total_komisi_batal]);
    
    fclose($output);
    exit();
}

// ========== FUNGSI EXPORT PDF ==========
function exportPDF($komisi_list, $developer_name, $start_date, $end_date, $status_filter,
                  $total_komisi, $total_komisi_cair, $total_komisi_pending, $total_komisi_batal) {
    
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
        
        $status_text = $status_filter === 'cair' ? 'Sudah Cair' : 
                      ($status_filter === 'pending' ? 'Pending' : 'Semua Status');
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Helvetica, sans-serif; font-size: 9px; }
                h1 { color: #1B4A3C; font-size: 16px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #1B4A3C; color: white; padding: 5px; text-align: left; }
                td { padding: 4px; border: 1px solid #ccc; }
                .text-right { text-align: right; }
                .total-row { background: #E7F3EF; font-weight: bold; }
                .cair { color: #2A9D8F; }
                .pending { color: #E9C46A; }
                .batal { color: #D64F3C; text-decoration: line-through; }
                .footer { margin-top: 15px; font-size: 7px; color: #666; }
            </style>
        </head>
        <body>
            <h1>Laporan Keuangan - Komisi Marketing</h1>
            <p>Developer: ' . htmlspecialchars($developer_name) . '</p>
            <p>Periode: ' . $start_date . ' s/d ' . $end_date . ' | Status: ' . $status_text . '</p>
            <p>Total Transaksi: ' . count($komisi_list) . '</p>
            
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Marketing</th>
                        <th>Customer</th>
                        <th>Unit</th>
                        <th>Harga</th>
                        <th>Komisi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';
        
        $no = 1;
        foreach ($komisi_list as $k) {
            $tanggal = $k['komisi_status'] === 'cair' && !empty($k['tanggal_cair']) && $k['tanggal_cair'] != '0000-00-00 00:00:00'
                ? date('d/m/Y', strtotime($k['tanggal_cair']))
                : date('d/m/Y', strtotime($k['komisi_created_at']));
            
            $customer = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
            $unit_info = ($k['nomor_unit'] ?? '') . ' (' . ($k['tipe_unit'] ?? '') . ')';
            
            $html .= '
                    <tr>
                        <td>' . $no++ . '</td>
                        <td>' . $tanggal . '</td>
                        <td>' . htmlspecialchars($k['marketing_name'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($customer) . '</td>
                        <td>' . htmlspecialchars($unit_info) . '</td>
                        <td class="text-right">Rp ' . number_format($k['unit_harga'] ?? 0, 0, ',', '.') . '</td>
                        <td class="text-right">Rp ' . number_format($k['komisi_final'] ?? 0, 0, ',', '.') . '</td>
                        <td class="' . ($k['komisi_status'] ?? '') . '">' . ($k['komisi_status'] ?? '-') . '</td>
                    </tr>';
        }
        
        $html .= '
                    <tr class="total-row">
                        <td colspan="5"><strong>TOTAL</strong></td>
                        <td class="text-right"><strong>Rp ' . number_format(array_sum(array_column($komisi_list, 'unit_harga')), 0, ',', '.') . '</strong></td>
                        <td class="text-right"><strong>Rp ' . number_format($total_komisi, 0, ',', '.') . '</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            
            <div style="margin-top: 15px;">
                <table style="width: 50%;">
                    <tr><td><strong>Sudah Cair</strong></td><td class="text-right cair">Rp ' . number_format($total_komisi_cair, 0, ',', '.') . '</td></tr>
                    <tr><td><strong>Pending</strong></td><td class="text-right pending">Rp ' . number_format($total_komisi_pending, 0, ',', '.') . '</td></tr>
                    <tr><td><strong>Batal</strong></td><td class="text-right batal">Rp ' . number_format($total_komisi_batal, 0, ',', '.') . '</td></tr>
                </table>
            </div>
            
            <div class="footer">
                <p>Dicetak dari LeadEngine - ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </body>
        </html>';
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        $filename = 'laporan_keuangan_' . $developer_name . '_' . date('Y-m-d') . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit();
        
    } else {
        // Fallback ke Excel
        exportExcel($komisi_list, $developer_name, $start_date, $end_date, $status_filter,
                   $total_komisi, $total_komisi_cair, $total_komisi_pending, $total_komisi_batal);
    }
}
?>