<?php
/**
 * TRIGGER_KOMISI.PHP - LEADENGINE WEBHOOK
 * Version: 2.0.0 - Trigger otomatis saat lead berubah status menjadi DEAL + KOMISI SPLIT
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/trigger_komisi.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/config.php';

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$log_file = dirname(__DIR__, 2) . '/logs/trigger_komisi.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - TRIGGER KOMISI DIPANGGIL\n", FILE_APPEND);
file_put_contents($log_file, "Input: " . json_encode($input) . "\n", FILE_APPEND);

// Validasi signature atau key
$key = $input['key'] ?? $_GET['key'] ?? '';
if ($key !== API_KEY && $key !== 'komisi_trigger_2026') {
    http_response_code(401);
    file_put_contents($log_file, "ERROR: Invalid key\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid key']);
    exit();
}

$action = $input['action'] ?? '';

if ($action === 'check_and_process') {
    // Cek lead yang baru menjadi DEAL dalam X menit terakhir
    $minutes = isset($input['minutes']) ? (int)$input['minutes'] : 5;
    
    try {
        $deal_statuses = ['Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'];
        $placeholders = implode(',', array_fill(0, count($deal_statuses), '?'));
        
        // Cek lead yang baru DEAL dan belum diproses komisinya
        $stmt = $conn->prepare("
            SELECT l.id, l.status, l.updated_at, l.assigned_type, l.assigned_marketing_team_id,
                   u.id as unit_id, u.harga, u.komisi_eksternal_persen, u.komisi_eksternal_rupiah, u.komisi_internal_rupiah,
                   u.komisi_split_persen, u.komisi_split_rupiah,
                   c.developer_id
            FROM leads l
            LEFT JOIN units u ON l.unit_id = u.id
            LEFT JOIN clusters c ON u.cluster_id = c.id
            WHERE l.status IN ($placeholders)
            AND l.updated_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
            AND NOT EXISTS (SELECT 1 FROM komisi_logs WHERE lead_id = l.id)
            ORDER BY l.updated_at DESC
        ");
        
        $params = array_merge($deal_statuses, [$minutes]);
        $stmt->execute($params);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        file_put_contents($log_file, "Found " . count($leads) . " leads in last $minutes minutes\n", FILE_APPEND);
        
        $processed = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($leads as $lead) {
            // Panggil API komisi_process
            $ch = curl_init('https://leadproperti.com/admin/api/komisi_process.php?action=process_deal');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'lead_id' => $lead['id'],
                    'unit_id' => $lead['unit_id'],
                    'force' => false,
                    'key' => API_KEY
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                $result = json_decode($response, true);
                if ($result['success']) {
                    $processed++;
                    file_put_contents($log_file, "Processed lead {$lead['id']}: {$result['message']}\n", FILE_APPEND);
                } else {
                    $errors[] = "Lead {$lead['id']}: {$result['message']}";
                    file_put_contents($log_file, "Error lead {$lead['id']}: {$result['message']}\n", FILE_APPEND);
                }
            } else {
                $errors[] = "Lead {$lead['id']}: HTTP $http_code";
                file_put_contents($log_file, "HTTP error lead {$lead['id']}: $http_code\n", FILE_APPEND);
            }
        }
        
        // PROSES KOMISI SPLIT UNTUK LEAD EXTERNAL
        $stmt_split = $conn->prepare("
            SELECT l.id, l.unit_id, c.developer_id
            FROM leads l
            LEFT JOIN units u ON l.unit_id = u.id
            LEFT JOIN clusters c ON u.cluster_id = c.id
            WHERE l.status IN ($placeholders)
            AND l.assigned_type = 'external'
            AND l.updated_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            AND NOT EXISTS (SELECT 1 FROM komisi_split_hutang WHERE lead_id = l.id)
        ");
        
        $params_split = array_merge($deal_statuses, [$minutes]);
        $stmt_split->execute($params_split);
        $leads_split = $stmt_split->fetchAll(PDO::FETCH_ASSOC);
        
        $processed_split = 0;
        foreach ($leads_split as $lead_split) {
            $result = catatHutangKomisiSplit($conn, $lead_split['id'], $lead_split['unit_id'], $lead_split['developer_id']);
            if ($result['success']) {
                $processed_split++;
                file_put_contents($log_file, "Split processed lead {$lead_split['id']}: Rp " . number_format($result['nominal'], 0, ',', '.') . "\n", FILE_APPEND);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Processed: $processed, Split: $processed_split",
            'data' => [
                'processed' => $processed,
                'split_processed' => $processed_split,
                'errors' => $errors
            ]
        ]);
        
    } catch (Exception $e) {
        file_put_contents($log_file, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
} elseif ($action === 'process_lead') {
    // Proses satu lead tertentu
    $lead_id = isset($input['lead_id']) ? (int)$input['lead_id'] : 0;
    $process_split = isset($input['process_split']) ? (bool)$input['process_split'] : true;
    
    if ($lead_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Lead ID diperlukan']);
        exit();
    }
    
    try {
        $result_komisi = null;
        $result_split = null;
        
        // Proses komisi marketing
        $ch = curl_init('https://leadproperti.com/admin/api/komisi_process.php?action=process_deal');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'lead_id' => $lead_id,
                'force' => false,
                'key' => API_KEY
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $result_komisi = json_decode($response, true);
        }
        
        // Proses komisi split jika diminta
        if ($process_split) {
            $ch2 = curl_init('https://leadproperti.com/admin/api/komisi_process.php?action=process_split');
            curl_setopt_array($ch2, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'lead_id' => $lead_id,
                    'force' => false,
                    'key' => API_KEY
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response2 = curl_exec($ch2);
            $http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            
            if ($http_code2 == 200) {
                $result_split = json_decode($response2, true);
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'komisi' => $result_komisi,
                'split' => $result_split
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
} elseif ($action === 'cron_setup') {
    // Informasi untuk setup cron job
    echo json_encode([
        'success' => true,
        'message' => 'Cron job setup',
        'cron_command' => 'curl -X POST https://leadproperti.com/admin/api/trigger_komisi.php -H "Content-Type: application/json" -d \'{"action":"check_and_process","key":"komisi_trigger_2026","minutes":10}\'',
        'recommended_interval' => 'Setiap 5-10 menit'
    ]);
    
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

file_put_contents($log_file, str_repeat("=", 50) . "\n\n", FILE_APPEND);
?>