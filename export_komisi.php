<?php
/**
 * EXPORT_KOMISI.PHP - LEADENGINE API
 * Version: 2.0.0 - FIXED: Validasi type, filter booking verification, total per status
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/export_komisi.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Cek session (finance, manager_developer, atau admin)
if (!isFinance() && !isManagerDeveloper() && !isAdmin() && !isManager()) {
    http_response_code(403);
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
    } else {
        die('Akses ditolak.');
    }
    exit();
}

// Rate limiting
$client_ip = getClientIP();
if (!checkRateLimit('export_komisi_' . $client_ip, 10, 300)) {
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

// ========== DETEKSI ROLE ==========
$current_role = getCurrentRole();
$developer_id = 0;
$type = $_GET['type'] ?? 'finance'; // 'finance' atau 'manager_developer'

// Validasi type
if (!in_array($type, ['finance', 'manager_developer'])) {
    $type = 'finance';
}

if ($type === 'manager_developer') {
    if (!isManagerDeveloper()) {
        die('Akses ditolak. Halaman ini hanya untuk Manager Developer.');
    }
    $developer_id = $_SESSION['developer_id'] ?? 0;
} else {
    // Finance atau admin
    if (!isFinance() && !isAdmin() && !isManager()) {
        die('Akses ditolak. Halaman ini hanya untuk Finance.');
    }
    
    if (isFinance()) {
        $developer_id = $_SESSION['developer_id'] ?? 0;
    } elseif (isAdmin() || isManager()) {
        $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0;
    }
}

if ($developer_id <= 0) {
    die("Error: Developer ID tidak valid");
}

// ========== AMBIL PARAMETER FILTER ==========
$status = $_GET['status'] ?? 'all';
$marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'csv';
$verifikasi_status = isset($_GET['verifikasi_status']) ? $_GET['verifikasi_status'] : ''; // PENDING, APPROVED, REJECTED

// ========== BANGUN QUERY DENGAN VIEW v_komisi_laporan ==========
$sql = "
    SELECT 
        k.*,
        l.first_name,
        l.last_name,
        l.phone as customer_phone,
        m.nama_lengkap as marketing_name,
        m.phone as marketing_phone,
        u.nomor_unit,
        u.tipe_unit,
        u.harga as unit_harga,
        u.program,
        c.nama_cluster,
        b.nama_block,
        bl.verifikasi_status as booking_verifikasi_status,
        bl.catatan_verifikasi
    FROM komisi_logs k
    LEFT JOIN leads l ON k.lead_id = l.id
    LEFT JOIN marketing_team m ON k.marketing_id = m.id
    LEFT JOIN units u ON k.unit_id = u.id
    LEFT JOIN clusters c ON u.cluster_id = c.id
    LEFT JOIN blocks b ON u.block_id = b.id
    LEFT JOIN booking_logs bl ON k.lead_id = bl.lead_id AND k.unit_id = bl.unit_id
    WHERE k.developer_id = ?
";

$params = [$developer_id];

// Filter berdasarkan tipe
if ($type === 'manager_developer') {
    $sql .= " AND k.assigned_type = 'internal'";
}

// Filter status komisi
if ($status !== 'all' && in_array($status, ['pending', 'cair', 'batal'])) {
    $sql .= " AND k.status = ?";
    $params[] = $status;
}

// Filter verifikasi booking
if (!empty($verifikasi_status) && in_array($verifikasi_status, ['PENDING', 'APPROVED', 'REJECTED'])) {
    $sql .= " AND bl.verifikasi_status = ?";
    $params[] = $verifikasi_status;
}

// Filter marketing
if ($marketing_id > 0) {
    $sql .= " AND k.marketing_id = ?";
    $params[] = $marketing_id;
}

// Filter tanggal
if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND DATE(k.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

$sql .= " ORDER BY k.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$komisi_list = $stmt->fetchAll();

// ========== HITUNG TOTAL PER STATUS ==========
$total_komisi = 0;
$total_pending = 0;
$total_cair = 0;
$total_batal = 0;
$total_by_marketing = [];

foreach ($komisi_list as $k) {
    $total_komisi += $k['komisi_final'];
    
    // Total per status
    if ($k['status'] == 'pending') {
        $total_pending += $k['komisi_final'];
    } elseif ($k['status'] == 'cair') {
        $total_cair += $k['komisi_final'];
    } elseif ($k['status'] == 'batal') {
        $total_batal += $k['komisi_final'];
    }
    
    // Total per marketing
    $marketing = $k['marketing_name'] ?? 'Unknown';
    if (!isset($total_by_marketing[$marketing])) {
        $total_by_marketing[$marketing] = 0;
    }
    $total_by_marketing[$marketing] += $k['komisi_final'];
}

// ========== AMBIL DATA DEVELOPER ==========
$stmt = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
$stmt->execute([$developer_id]);
$developer_name = $stmt->fetchColumn() ?: 'Developer';

// ========== EXPORT BERDASARKAN FORMAT ==========
if ($format === 'csv') {
    exportCSV($komisi_list, $developer_name, $date_from, $date_to, $total_komisi, $total_pending, $total_cair, $total_batal, $type, $total_by_marketing);
} elseif ($format === 'excel') {
    exportExcel($komisi_list, $developer_name, $date_from, $date_to, $total_komisi, $total_pending, $total_cair, $total_batal, $type, $total_by_marketing);
} elseif ($format === 'pdf') {
    exportPDF($komisi_list, $developer_name, $date_from, $date_to, $total_komisi, $total_pending, $total_cair, $total_batal, $type, $total_by_marketing);
} elseif ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $komisi_list,
        'summary' => [
            'total' => $total_komisi,
            'pending' => $total_pending,
            'cair' => $total_cair,
            'batal' => $total_batal,
            'by_marketing' => $total_by_marketing,
            'count' => count($komisi_list)
        ],
        'developer' => $developer_name,
        'period' => "$date_from s/d $date_to"
    ], JSON_PRETTY_PRINT);
    exit();
} else {
    exportCSV($komisi_list, $developer_name, $date_from, $date_to, $total_komisi, $total_pending, $total_cair, $total_batal, $type, $total_by_marketing);
}

// ========== FUNGSI EXPORT CSV ==========
function exportCSV($komisi_list, $developer_name, $date_from, $date_to, $total_komisi, $total_pending, $total_cair, $total_batal, $type, $total_by_marketing) {
    $filename = 'komisi_' . $developer_name . '_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    // Header CSV
    fputcsv($output, [
        'No',
        'ID Komisi',
        'Tanggal',
        'Marketing',
        'Tipe',
        'Customer',
        'No. WhatsApp',
        'Unit',
        'Cluster/Block',
        'Harga Unit',
        'Komisi Final',
        'Status',
        'Verifikasi Booking',
        'Tanggal Cair',
        'Keterangan'
    ]);
    
    $no = 1;
    foreach ($komisi_list as $k) {
        $customer_name = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
        $cluster_block = ($k['nama_cluster'] ?? '') . ' - Blok ' . ($k['nama_block'] ?? '');
        $tanggal = date('d/m/Y', strtotime($k['created_at']));
        $tanggal_cair = (!empty($k['tanggal_cair']) && $k['tanggal_cair'] != '0000-00-00 00:00:00') 
            ? date('d/m/Y', strtotime($k['tanggal_cair'])) 
            : '-';
        
        fputcsv($output, [
            $no++,
            $k['id'],
            $tanggal,
            $k['marketing_name'] ?? '-',
            $k['assigned_type'] ?? '-',
            $customer_name ?: '-',
            $k['customer_phone'] ?? '-',
            $k['nomor_unit'] ?? '-',
            $cluster_block ?: '-',
            $k['unit_harga'] ?? 0,
            $k['komisi_final'] ?? 0,
            $k['status'] ?? '-',
            $k['booking_verifikasi_status'] ?? '-',
            $tanggal_cair,
            $k['catatan'] ?? '-'
        ]);
    }
    
    // Baris total
    fputcsv($output, []);
    fputcsv($output, ['TOTAL KOMISI', '', '', '', '', '', '', '', '', '', $total_komisi]);
    fputcsv($output, ['RINCIAN PER STATUS:', '', '', '', '', '', '', '', '', '']);
    fputcsv($output, ['Pending', '', '', '', '', '', '', '', '', '', $total_pending]);
    fputcsv($output, ['Sudah Cair', '', '', '', '', '', '', '', '', '', $total_cair]);
    fputcsv($output, ['Batal', '', '', '', '', '', '', '', '', '', $total_batal]);
    
    // Total per marketing
    fputcsv($output, []);
    fputcsv($output, ['TOTAL PER MARKETING:', '', '', '', '', '', '', '', '', '']);
    foreach ($total_by_marketing as $marketing => $total) {
        fputcsv($output, [$marketing, '', '', '', '', '', '', '', '', '', $total]);
    }
    
    fclose($output);
    exit();
}

// ========== FUNGSI EXPORT EXCEL ==========
function exportExcel($komisi_list, $developer_name, $date_from, $date_to, $total_komisi, $total_pending, $total_cair, $total_batal, $type, $total_by_marketing) {
    $filename = 'komisi_' . $developer_name . '_' . date('Y-m-d') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $title = ($type === 'manager_developer') ? 'Komisi Marketing Internal' : 'Laporan Komisi Finance';
    
    echo '<html>';
    echo '<head><meta charset="UTF-8">';
    echo '<style>
        body { font-family: Arial, sans-serif; }
        h2 { color: #1B4A3C; }
        table { border-collapse: collapse; width: 100%; font-size: 11px; }
        th { background: #1B4A3C; color: white; padding: 8px; }
        td { padding: 6px; border: 1px solid #ccc; }
        .total { background: #E7F3EF; font-weight: bold; }
        .text-right { text-align: right; }
        .pending { color: #E9C46A; font-weight: bold; }
        .cair { color: #2A9D8F; font-weight: bold; }
        .batal { color: #D64F3C; font-weight: bold; }
    </style>';
    echo '</head><body>';
    
    echo '<h2>' . $title . '</h2>';
    echo '<p>Developer: ' . htmlspecialchars($developer_name) . '</p>';
    echo '<p>Periode: ' . $date_from . ' s/d ' . $date_to . '</p>';
    echo '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    echo '<p>Total Komisi: Rp ' . number_format($total_komisi, 0, ',', '.') . '</p>';
    
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
    echo '<th>Verifikasi</th>';
    echo '<th>Tgl Cair</th>';
    echo '<th>Keterangan</th>';
    echo '</tr>';
    
    $no = 1;
    foreach ($komisi_list as $k) {
        $customer_name = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
        $unit_info = ($k['nomor_unit'] ?? '') . ' (' . ($k['tipe_unit'] ?? '') . ')';
        $cluster_block = ($k['nama_cluster'] ?? '') . ' - Blok ' . ($k['nama_block'] ?? '');
        $status_class = $k['status'] ?? '';
        $tanggal_cair = (!empty($k['tanggal_cair']) && $k['tanggal_cair'] != '0000-00-00 00:00:00') 
            ? date('d/m/Y', strtotime($k['tanggal_cair'])) 
            : '-';
        
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($k['created_at'])) . '</td>';
        echo '<td><strong>' . htmlspecialchars($k['marketing_name'] ?? '-') . '</strong></td>';
        echo '<td>' . htmlspecialchars($k['assigned_type'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($customer_name ?: '-') . '<br><small>' . htmlspecialchars($k['customer_phone'] ?? '') . '</small></td>';
        echo '<td>' . htmlspecialchars($unit_info) . '</td>';
        echo '<td>' . htmlspecialchars($cluster_block) . '</td>';
        echo '<td class="text-right">Rp ' . number_format($k['unit_harga'] ?? 0, 0, ',', '.') . '</td>';
        echo '<td class="text-right"><strong>Rp ' . number_format($k['komisi_final'] ?? 0, 0, ',', '.') . '</strong></td>';
        echo '<td class="' . $status_class . '">' . htmlspecialchars($k['status'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($k['booking_verifikasi_status'] ?? '-') . '</td>';
        echo '<td>' . $tanggal_cair . '</td>';
        echo '<td>' . htmlspecialchars($k['catatan'] ?? '-') . '</td>';
        echo '</tr>';
    }
    
    // Baris total
    echo '<tr class="total">';
    echo '<td colspan="7"><strong>TOTAL</strong></td>';
    echo '<td class="text-right"><strong>Rp ' . number_format(array_sum(array_column($komisi_list, 'unit_harga')), 0, ',', '.') . '</strong></td>';
    echo '<td class="text-right"><strong>Rp ' . number_format($total_komisi, 0, ',', '.') . '</strong></td>';
    echo '<td colspan="4"></td>';
    echo '</tr>';
    
    echo '<tr><td colspan="13">&nbsp;</td></tr>';
    
    echo '<tr><td colspan="13"><strong>Rekap per Status:</strong></td></tr>';
    echo '<tr><td colspan="7">Pending</td><td class="text-right pending">Rp ' . number_format($total_pending, 0, ',', '.') . '</td><td colspan="5"></td></tr>';
    echo '<tr><td colspan="7">Sudah Cair</td><td class="text-right cair">Rp ' . number_format($total_cair, 0, ',', '.') . '</td><td colspan="5"></td></tr>';
    echo '<tr><td colspan="7">Batal</td><td class="text-right batal">Rp ' . number_format($total_batal, 0, ',', '.') . '</td><td colspan="5"></td></tr>';
    
    echo '<tr><td colspan="13">&nbsp;</td></tr>';
    echo '<tr><td colspan="13"><strong>Rekap per Marketing:</strong></td></tr>';
    foreach ($total_by_marketing as $marketing => $total) {
        echo '<tr><td colspan="7">' . htmlspecialchars($marketing) . '</td>';
        echo '<td class="text-right">Rp ' . number_format($total, 0, ',', '.') . '</td>';
        echo '<td colspan="5"></td></tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

// ========== FUNGSI EXPORT PDF ==========
function exportPDF($komisi_list, $developer_name, $date_from, $date_to, $total_komisi, $total_pending, $total_cair, $total_batal, $type, $total_by_marketing) {
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
        
        $title = ($type === 'manager_developer') ? 'Komisi Marketing Internal' : 'Laporan Komisi Finance';
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Helvetica, sans-serif; font-size: 9px; }
                h2 { color: #1B4A3C; margin-bottom: 5px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #1B4A3C; color: white; padding: 5px; text-align: left; }
                td { padding: 4px; border: 1px solid #ccc; }
                .text-right { text-align: right; }
                .total { background: #E7F3EF; font-weight: bold; }
                .pending { color: #E9C46A; }
                .cair { color: #2A9D8F; }
                .batal { color: #D64F3C; }
                .footer { margin-top: 15px; font-size: 7px; color: #666; }
            </style>
        </head>
        <body>
            <h2>' . $title . '</h2>
            <p>Developer: ' . htmlspecialchars($developer_name) . '</p>
            <p>Periode: ' . $date_from . ' s/d ' . $date_to . '</p>
            <p>Total Komisi: Rp ' . number_format($total_komisi, 0, ',', '.') . '</p>
            
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Marketing</th>
                        <th>Customer</th>
                        <th>Unit</th>
                        <th>Komisi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';
        
        $no = 1;
        foreach ($komisi_list as $k) {
            $customer_name = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
            $unit_info = ($k['nomor_unit'] ?? '') . ' (' . ($k['tipe_unit'] ?? '') . ')';
            
            $html .= '
                    <tr>
                        <td>' . $no++ . '</td>
                        <td>' . date('d/m/Y', strtotime($k['created_at'])) . '</td>
                        <td>' . htmlspecialchars($k['marketing_name'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($customer_name) . '</td>
                        <td>' . htmlspecialchars($unit_info) . '</td>
                        <td class="text-right">Rp ' . number_format($k['komisi_final'] ?? 0, 0, ',', '.') . '</td>
                        <td class="' . ($k['status'] ?? '') . '">' . ($k['status'] ?? '-') . '</td>
                    </tr>';
        }
        
        $html .= '
                    <tr class="total">
                        <td colspan="4"><strong>TOTAL</strong></td>
                        <td></td>
                        <td class="text-right"><strong>Rp ' . number_format($total_komisi, 0, ',', '.') . '</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            
            <div style="margin-top: 15px;">
                <table style="width: 50%;">
                    <tr><td><strong>Pending</strong></td><td class="text-right pending">Rp ' . number_format($total_pending, 0, ',', '.') . '</td></tr>
                    <tr><td><strong>Sudah Cair</strong></td><td class="text-right cair">Rp ' . number_format($total_cair, 0, ',', '.') . '</td></tr>
                    <tr><td><strong>Batal</strong></td><td class="text-right batal">Rp ' . number_format($total_batal, 0, ',', '.') . '</td></tr>
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
        
        $filename = 'komisi_' . $developer_name . '_' . date('Y-m-d') . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit();
        
    } else {
        // Fallback ke Excel
        exportExcel($komisi_list, $developer_name, $date_from, $date_to, $total_komisi, $total_pending, $total_cair, $total_batal, $type, $total_by_marketing);
    }
}
?>