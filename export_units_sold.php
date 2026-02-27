<?php
/**
 * EXPORT_UNITS_SOLD.PHP - LEADENGINE API
 * Version: 2.0.0 - FIXED: Query komisi, handle NULL date, Dompdf fallback
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/export_units_sold.log');

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
if (!checkRateLimit('export_units_' . $client_ip, 10, 300)) { // 10 requests per 5 minutes
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
$location_access = '';

if (isDeveloper()) {
    $developer_id = $_SESSION['user_id'];
    $location_access = $_SESSION['location_access'] ?? '';
} elseif (isMarketing()) {
    $developer_id = $_SESSION['marketing_developer_id'] ?? 0;
} elseif (isAdmin() || isManager()) {
    $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
}

// ========== AMBIL PARAMETER FILTER ==========
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$cluster_id = isset($_GET['cluster_id']) ? (int)$_GET['cluster_id'] : 0;
$block_id = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
$program = isset($_GET['program']) ? $_GET['program'] : '';

// ========== BANGUN QUERY ==========
$sql = "
    SELECT 
        u.id as unit_id,
        u.nomor_unit,
        u.tipe_unit,
        u.program,
        u.luas_tanah,
        u.luas_bangunan,
        u.harga,
        u.harga_booking,
        u.status as unit_status,
        u.sold_at,
        u.booking_at,
        u.created_at as unit_created_at,
        
        c.id as cluster_id,
        c.nama_cluster,
        b.id as block_id,
        b.nama_block,
        
        l.id as lead_id,
        l.first_name,
        l.last_name,
        l.phone as customer_phone,
        l.email as customer_email,
        l.created_at as lead_created_at,
        
        m.id as marketing_id,
        m.nama_lengkap as marketing_name,
        m.phone as marketing_phone,
        
        dev.id as developer_id,
        dev.nama_lengkap as developer_name,
        
        kl.id as komisi_id,
        kl.komisi_final,
        kl.status as komisi_status,
        kl.tanggal_cair as komisi_tanggal_cair,
        
        bk.nama_bank,
        bk.nomor_rekening as bank_rekening,
        bk.atas_nama as bank_atas_nama
        
    FROM units u
    LEFT JOIN clusters c ON u.cluster_id = c.id
    LEFT JOIN blocks b ON u.block_id = b.id
    LEFT JOIN leads l ON u.lead_id = l.id
    LEFT JOIN marketing_team m ON l.assigned_marketing_team_id = m.id
    LEFT JOIN users dev ON c.developer_id = dev.id
    LEFT JOIN komisi_logs kl ON u.lead_id = kl.lead_id AND u.id = kl.unit_id
    LEFT JOIN banks bk ON m.bank_id = bk.id
    WHERE u.status IN ('SOLD', 'BOOKED')
";

$params = [];

// Filter by developer
if ($developer_id > 0) {
    $sql .= " AND c.developer_id = ?";
    $params[] = $developer_id;
}

// Filter by date range (sold_at atau booking_at)
if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND (
        (u.sold_at IS NOT NULL AND DATE(u.sold_at) BETWEEN ? AND ?) OR
        (u.booking_at IS NOT NULL AND DATE(u.booking_at) BETWEEN ? AND ?)
    )";
    $params[] = $start_date;
    $params[] = $end_date;
    $params[] = $start_date;
    $params[] = $end_date;
}

// Filter by cluster
if ($cluster_id > 0) {
    $sql .= " AND u.cluster_id = ?";
    $params[] = $cluster_id;
}

// Filter by block
if ($block_id > 0) {
    $sql .= " AND u.block_id = ?";
    $params[] = $block_id;
}

// Filter by program
if (!empty($program) && in_array($program, ['Subsidi', 'Komersil'])) {
    $sql .= " AND u.program = ?";
    $params[] = $program;
}

$sql .= " ORDER BY u.sold_at DESC, u.booking_at DESC, u.updated_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== HITUNG TOTAL ==========
$total_harga = 0;
$total_komisi = 0;
$total_komisi_cair = 0;
$total_komisi_pending = 0;

foreach ($units as $u) {
    $total_harga += $u['harga'] ?? 0;
    $total_komisi += $u['komisi_final'] ?? 0;
    
    if (($u['komisi_status'] ?? '') === 'cair') {
        $total_komisi_cair += $u['komisi_final'] ?? 0;
    } elseif (($u['komisi_status'] ?? '') === 'pending') {
        $total_komisi_pending += $u['komisi_final'] ?? 0;
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
        exportExcel($units, $developer_name, $start_date, $end_date, $total_harga, $total_komisi, $total_komisi_cair, $total_komisi_pending);
        break;
    case 'csv':
        exportCSV($units, $developer_name, $start_date, $end_date, $total_harga, $total_komisi, $total_komisi_cair, $total_komisi_pending);
        break;
    case 'pdf':
        exportPDF($units, $developer_name, $start_date, $end_date, $total_harga, $total_komisi, $total_komisi_cair, $total_komisi_pending);
        break;
    case 'json':
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $units,
            'summary' => [
                'total_units' => count($units),
                'total_harga' => $total_harga,
                'total_komisi' => $total_komisi,
                'total_komisi_cair' => $total_komisi_cair,
                'total_komisi_pending' => $total_komisi_pending
            ],
            'period' => "$start_date s/d $end_date"
        ], JSON_PRETTY_PRINT);
        exit();
    default:
        exportExcel($units, $developer_name, $start_date, $end_date, $total_harga, $total_komisi, $total_komisi_cair, $total_komisi_pending);
}

// ========== FUNGSI EXPORT EXCEL ==========
function exportExcel($units, $developer_name, $start_date, $end_date, $total_harga, $total_komisi, $total_komisi_cair, $total_komisi_pending) {
    $filename = 'units_sold_' . $developer_name . '_' . date('Y-m-d_His') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<html>';
    echo '<head><meta charset="UTF-8">';
    echo '<style>
        body { font-family: Arial, sans-serif; }
        h1 { color: #1B4A3C; }
        table { border-collapse: collapse; width: 100%; font-size: 12px; }
        th { background: #1B4A3C; color: white; padding: 8px; }
        td { padding: 6px; border: 1px solid #ccc; }
        .total-row { background: #E7F3EF; font-weight: bold; }
        .text-right { text-align: right; }
        .cair { color: #2A9D8F; }
        .pending { color: #E9C46A; }
    </style>';
    echo '</head><body>';
    
    echo '<h1>Laporan Unit Terjual</h1>';
    echo '<p>Developer: ' . htmlspecialchars($developer_name) . '</p>';
    echo '<p>Periode: ' . $start_date . ' s/d ' . $end_date . '</p>';
    echo '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    echo '<p>Total Unit: ' . count($units) . ' unit</p>';
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>Tanggal Sold</th>';
    echo '<th>Cluster</th>';
    echo '<th>Block</th>';
    echo '<th>Nomor Unit</th>';
    echo '<th>Tipe</th>';
    echo '<th>Program</th>';
    echo '<th>Harga</th>';
    echo '<th>Customer</th>';
    echo '<th>No. WhatsApp</th>';
    echo '<th>Marketing</th>';
    echo '<th>Komisi</th>';
    echo '<th>Status Komisi</th>';
    echo '<th>Tgl Cair</th>';
    echo '<th>Bank</th>';
    echo '</tr>';
    
    $no = 1;
    foreach ($units as $u) {
        $tanggal = !empty($u['sold_at']) ? date('d/m/Y', strtotime($u['sold_at'])) : 
                  (!empty($u['booking_at']) ? date('d/m/Y', strtotime($u['booking_at'])) : '-');
        
        $customer = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        if (empty($customer)) $customer = '-';
        
        $komisi = $u['komisi_final'] ?? 0;
        $komisi_status = $u['komisi_status'] ?? '-';
        $komisi_tgl = !empty($u['komisi_tanggal_cair']) && $u['komisi_tanggal_cair'] != '0000-00-00 00:00:00' 
            ? date('d/m/Y', strtotime($u['komisi_tanggal_cair'])) 
            : '-';
        
        $bank_info = '';
        if (!empty($u['nama_bank']) && !empty($u['bank_rekening'])) {
            $bank_info = $u['nama_bank'] . ' - ' . substr($u['bank_rekening'], 0, 4) . '****' . substr($u['bank_rekening'], -4);
        }
        
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . $tanggal . '</td>';
        echo '<td>' . htmlspecialchars($u['nama_cluster'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($u['nama_block'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($u['nomor_unit'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($u['tipe_unit'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($u['program'] ?? '-') . '</td>';
        echo '<td class="text-right">Rp ' . number_format($u['harga'] ?? 0, 0, ',', '.') . '</td>';
        echo '<td>' . htmlspecialchars($customer) . '</td>';
        echo '<td>' . htmlspecialchars($u['customer_phone'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($u['marketing_name'] ?? '-') . '</td>';
        echo '<td class="text-right">Rp ' . number_format($komisi, 0, ',', '.') . '</td>';
        echo '<td class="' . $komisi_status . '">' . $komisi_status . '</td>';
        echo '<td>' . $komisi_tgl . '</td>';
        echo '<td>' . htmlspecialchars($bank_info) . '</td>';
        echo '</tr>';
    }
    
    // Baris total
    echo '<tr class="total-row">';
    echo '<td colspan="7"><strong>TOTAL</strong></td>';
    echo '<td class="text-right"><strong>Rp ' . number_format($total_harga, 0, ',', '.') . '</strong></td>';
    echo '<td colspan="3"></td>';
    echo '<td class="text-right"><strong>Rp ' . number_format($total_komisi, 0, ',', '.') . '</strong></td>';
    echo '<td colspan="3"></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="15">&nbsp;</td>';
    echo '</tr>';
    
    echo '<tr><td colspan="15"><strong>Rekap Komisi:</strong></td></tr>';
    echo '<tr>';
    echo '<td colspan="7">Total Komisi Cair</td>';
    echo '<td class="text-right cair">Rp ' . number_format($total_komisi_cair, 0, ',', '.') . '</td>';
    echo '<td colspan="7"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="7">Total Komisi Pending</td>';
    echo '<td class="text-right pending">Rp ' . number_format($total_komisi_pending, 0, ',', '.') . '</td>';
    echo '<td colspan="7"></td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

// ========== FUNGSI EXPORT CSV ==========
function exportCSV($units, $developer_name, $start_date, $end_date, $total_harga, $total_komisi, $total_komisi_cair, $total_komisi_pending) {
    $filename = 'units_sold_' . $developer_name . '_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    // Header
    fputcsv($output, [
        'No', 'Tanggal Sold', 'Cluster', 'Block', 'Nomor Unit', 'Tipe', 'Program',
        'Harga', 'Customer', 'No. WhatsApp', 'Marketing', 'Komisi', 'Status Komisi',
        'Tanggal Cair', 'Bank'
    ]);
    
    $no = 1;
    foreach ($units as $u) {
        $tanggal = !empty($u['sold_at']) ? date('Y-m-d', strtotime($u['sold_at'])) : 
                  (!empty($u['booking_at']) ? date('Y-m-d', strtotime($u['booking_at'])) : '');
        
        $customer = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        
        $komisi_tgl = !empty($u['komisi_tanggal_cair']) && $u['komisi_tanggal_cair'] != '0000-00-00 00:00:00' 
            ? date('Y-m-d', strtotime($u['komisi_tanggal_cair'])) 
            : '';
        
        $bank_info = '';
        if (!empty($u['nama_bank']) && !empty($u['bank_rekening'])) {
            $bank_info = $u['nama_bank'] . ' - ' . substr($u['bank_rekening'], 0, 4) . '****' . substr($u['bank_rekening'], -4);
        }
        
        fputcsv($output, [
            $no++,
            $tanggal,
            $u['nama_cluster'] ?? '',
            $u['nama_block'] ?? '',
            $u['nomor_unit'] ?? '',
            $u['tipe_unit'] ?? '',
            $u['program'] ?? '',
            $u['harga'] ?? 0,
            $customer,
            $u['customer_phone'] ?? '',
            $u['marketing_name'] ?? '',
            $u['komisi_final'] ?? 0,
            $u['komisi_status'] ?? '',
            $komisi_tgl,
            $bank_info
        ]);
    }
    
    // Summary
    fputcsv($output, []);
    fputcsv($output, ['TOTAL', '', '', '', '', '', '', $total_harga, '', '', '', $total_komisi]);
    fputcsv($output, ['Komisi Cair', '', '', '', '', '', '', $total_komisi_cair]);
    fputcsv($output, ['Komisi Pending', '', '', '', '', '', '', $total_komisi_pending]);
    
    fclose($output);
    exit();
}

// ========== FUNGSI EXPORT PDF ==========
function exportPDF($units, $developer_name, $start_date, $end_date, $total_harga, $total_komisi, $total_komisi_cair, $total_komisi_pending) {
    // Cek apakah Dompdf tersedia
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
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Helvetica, sans-serif; font-size: 10px; }
                h1 { color: #1B4A3C; font-size: 18px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #1B4A3C; color: white; padding: 6px; text-align: left; }
                td { padding: 5px; border: 1px solid #ccc; }
                .text-right { text-align: right; }
                .total-row { background: #E7F3EF; font-weight: bold; }
                .footer { margin-top: 20px; font-size: 8px; color: #666; }
            </style>
        </head>
        <body>
            <h1>Laporan Unit Terjual</h1>
            <p>Developer: ' . htmlspecialchars($developer_name) . '</p>
            <p>Periode: ' . $start_date . ' s/d ' . $end_date . '</p>
            <p>Total Unit: ' . count($units) . ' unit</p>
            
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Unit</th>
                        <th>Cluster/Block</th>
                        <th>Harga</th>
                        <th>Customer</th>
                        <th>Marketing</th>
                        <th>Komisi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';
        
        $no = 1;
        foreach ($units as $u) {
            $tanggal = !empty($u['sold_at']) ? date('d/m/Y', strtotime($u['sold_at'])) : 
                      (!empty($u['booking_at']) ? date('d/m/Y', strtotime($u['booking_at'])) : '-');
            
            $customer = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            $unit_info = $u['nomor_unit'] . ' (' . $u['tipe_unit'] . ')';
            $cluster_block = ($u['nama_cluster'] ?? '') . ' - Blok ' . ($u['nama_block'] ?? '');
            
            $html .= '
                    <tr>
                        <td>' . $no++ . '</td>
                        <td>' . $tanggal . '</td>
                        <td>' . htmlspecialchars($unit_info) . '</td>
                        <td>' . htmlspecialchars($cluster_block) . '</td>
                        <td class="text-right">Rp ' . number_format($u['harga'] ?? 0, 0, ',', '.') . '</td>
                        <td>' . htmlspecialchars($customer) . '</td>
                        <td>' . htmlspecialchars($u['marketing_name'] ?? '-') . '</td>
                        <td class="text-right">Rp ' . number_format($u['komisi_final'] ?? 0, 0, ',', '.') . '</td>
                        <td>' . ($u['komisi_status'] ?? '-') . '</td>
                    </tr>';
        }
        
        $html .= '
                    <tr class="total-row">
                        <td colspan="4"><strong>TOTAL</strong></td>
                        <td class="text-right"><strong>Rp ' . number_format($total_harga, 0, ',', '.') . '</strong></td>
                        <td colspan="2"></td>
                        <td class="text-right"><strong>Rp ' . number_format($total_komisi, 0, ',', '.') . '</strong></td>
                        <td></td>
                    </tr>
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
        
        $filename = 'units_sold_' . $developer_name . '_' . date('Y-m-d') . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit();
        
    } else {
        // Fallback ke HTML
        exportExcel($units, $developer_name, $start_date, $end_date, $total_harga, $total_komisi, $total_komisi_cair, $total_komisi_pending);
    }
}
?>