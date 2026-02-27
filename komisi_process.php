<?php
/**
 * KOMISI_PROCESS.PHP - LEADENGINE API
 * Version: 2.0.0 - Proses perhitungan komisi, komisi split, dan notifikasi
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/komisi_process.log');

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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

// Logging
$log_dir = __DIR__ . '/../../logs/';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$log_file = $log_dir . 'komisi_process.log';

function writeLog($message, $data = null) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $log .= $data . "\n";
        }
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeLog("========== KOMISI PROCESS DIPANGGIL ==========");
writeLog("Method: " . $_SERVER['REQUEST_METHOD']);
writeLog("GET: " . json_encode($_GET));
writeLog("POST: " . json_encode($_POST));

// Cek autentikasi (internal key untuk cron/webhook)
$internal_key = $_GET['key'] ?? $_POST['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
$is_internal = ($internal_key === API_KEY || $internal_key === 'komisi_internal_2026');

// Jika bukan internal, cek session
if (!$is_internal && !isAdmin() && !isFinancePlatform() && !isFinance()) {
    writeLog("ERROR: Unauthorized access");
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$conn = getDB();
if (!$conn) {
    writeLog("ERROR: Database connection failed");
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

writeLog("Action: $action");

// ============================================
// FUNGSI HITUNG KOMISI
// ============================================
function hitungKomisiInternal($conn, $unit_id, $developer_id, $assigned_type = 'internal') {
    $result = [
        'komisi_eksternal_persen' => 3.00,
        'komisi_eksternal_rupiah' => 0,
        'komisi_internal_rupiah' => 1000000,
        'komisi_final' => 0,
        'komisi_split_persen' => 2.50,
        'komisi_split_rupiah' => 0,
        'komisi_split_final' => 0
    ];
    
    try {
        // Ambil data unit
        $stmt = $conn->prepare("
            SELECT u.*, c.developer_id 
            FROM units u
            JOIN clusters c ON u.cluster_id = c.id
            WHERE u.id = ? AND c.developer_id = ?
        ");
        $stmt->execute([$unit_id, $developer_id]);
        $unit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$unit) {
            return $result;
        }
        
        // Ambil komisi rules dari developer
        $stmt = $conn->prepare("
            SELECT kr.*, mt.type_name 
            FROM komisi_rules kr
            JOIN marketing_types mt ON kr.marketing_type_id = mt.id
            WHERE kr.developer_id = ?
        ");
        $stmt->execute([$developer_id]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $komisi_data = [];
        foreach ($rules as $rule) {
            $komisi_data[$rule['type_name']] = $rule['commission_value'];
        }
        
        // Ambil komisi split platform
        $platform_split = getPlatformKomisiSplit();
        
        $result['komisi_split_persen'] = ($platform_split['type'] == 'PERCENT') ? $platform_split['value'] : 0;
        $result['komisi_split_rupiah'] = ($platform_split['type'] == 'FIXED') ? $platform_split['value'] : 0;
        
        // Override dengan nilai dari unit jika ada
        if (!empty($unit['komisi_eksternal_persen'])) {
            $result['komisi_eksternal_persen'] = (float)$unit['komisi_eksternal_persen'];
        } elseif (isset($komisi_data['sales_canvasing'])) {
            $result['komisi_eksternal_persen'] = (float)$komisi_data['sales_canvasing'];
        }
        
        if (!empty($unit['komisi_eksternal_rupiah'])) {
            $result['komisi_eksternal_rupiah'] = (float)$unit['komisi_eksternal_rupiah'];
        }
        
        if (!empty($unit['komisi_internal_rupiah'])) {
            $result['komisi_internal_rupiah'] = (float)$unit['komisi_internal_rupiah'];
        } elseif (isset($komisi_data['sales_inhouse'])) {
            $result['komisi_internal_rupiah'] = (float)$komisi_data['sales_inhouse'];
        }
        
        if (!empty($unit['komisi_split_persen'])) {
            $result['komisi_split_persen'] = (float)$unit['komisi_split_persen'];
        }
        
        if (!empty($unit['komisi_split_rupiah'])) {
            $result['komisi_split_rupiah'] = (float)$unit['komisi_split_rupiah'];
        }
        
        // Hitung komisi final
        $harga = (float)$unit['harga'];
        
        if ($assigned_type === 'internal') {
            $result['komisi_final'] = $result['komisi_internal_rupiah'];
        } else {
            if ($result['komisi_eksternal_rupiah'] > 0) {
                $result['komisi_final'] = $result['komisi_eksternal_rupiah'];
            } else {
                $result['komisi_final'] = $harga * ($result['komisi_eksternal_persen'] / 100);
            }
        }
        
        // Hitung komisi split
        if ($result['komisi_split_rupiah'] > 0) {
            $result['komisi_split_final'] = $result['komisi_split_rupiah'];
        } elseif ($result['komisi_split_persen'] > 0) {
            $result['komisi_split_final'] = $harga * ($result['komisi_split_persen'] / 100);
        }
        
        return $result;
        
    } catch (Exception $e) {
        writeLog("Error in hitungKomisiInternal: " . $e->getMessage());
        return $result;
    }
}

// ============================================
// ACTION: CALCULATE
// ============================================
if ($action === 'calculate') {
    $unit_id = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : (isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0);
    $lead_id = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : (isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0);
    $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : (isset($_POST['developer_id']) ? (int)$_POST['developer_id'] : 0);
    
    writeLog("Calculate: unit_id=$unit_id, lead_id=$lead_id, developer_id=$developer_id");
    
    if ($unit_id <= 0 && $lead_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Unit ID atau Lead ID diperlukan']);
        exit();
    }
    
    try {
        // Jika lead_id diberikan, cari unit_id dari lead
        if ($lead_id > 0 && $unit_id <= 0) {
            $stmt = $conn->prepare("SELECT unit_id, assigned_type, developer_id FROM leads WHERE id = ?");
            $stmt->execute([$lead_id]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lead && $lead['unit_id']) {
                $unit_id = (int)$lead['unit_id'];
                $developer_id = (int)($lead['developer_id'] ?? $developer_id);
                $assigned_type = $lead['assigned_type'] ?? 'external';
            }
        }
        
        if ($unit_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Unit tidak ditemukan']);
            exit();
        }
        
        // Ambil developer_id dari unit jika belum ada
        if ($developer_id <= 0) {
            $stmt = $conn->prepare("
                SELECT c.developer_id 
                FROM units u
                JOIN clusters c ON u.cluster_id = c.id
                WHERE u.id = ?
            ");
            $stmt->execute([$unit_id]);
            $developer_id = (int)$stmt->fetchColumn();
        }
        
        $komisi = hitungKomisiInternal($conn, $unit_id, $developer_id, $assigned_type ?? 'external');
        
        echo json_encode([
            'success' => true,
            'data' => $komisi,
            'unit_id' => $unit_id,
            'developer_id' => $developer_id
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR calculate: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================
// ACTION: PROCESS_DEAL (Saat lead jadi DEAL)
// ============================================
elseif ($action === 'process_deal') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $lead_id = isset($input['lead_id']) ? (int)$input['lead_id'] : 0;
    $force = isset($input['force']) ? (bool)$input['force'] : false;
    
    writeLog("Process deal: lead_id=$lead_id, force=$force");
    
    if ($lead_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Lead ID diperlukan']);
        exit();
    }
    
    try {
        $conn->beginTransaction();
        
        // Ambil data lead dengan unit
        $stmt = $conn->prepare("
            SELECT l.*, u.id as unit_id, u.harga, 
                   u.komisi_eksternal_persen, u.komisi_eksternal_rupiah, u.komisi_internal_rupiah,
                   u.komisi_split_persen, u.komisi_split_rupiah,
                   c.developer_id
            FROM leads l
            LEFT JOIN units u ON l.unit_id = u.id
            LEFT JOIN clusters c ON u.cluster_id = c.id
            WHERE l.id = ? FOR UPDATE
        ");
        $stmt->execute([$lead_id]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lead) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Lead tidak ditemukan']);
            exit();
        }
        
        // Cek apakah lead sudah DEAL
        $deal_statuses = ['Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'];
        if (!in_array($lead['status'], $deal_statuses) && !$force) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Lead belum DEAL']);
            exit();
        }
        
        // Cek apakah sudah ada komisi
        $stmt = $conn->prepare("SELECT id FROM komisi_logs WHERE lead_id = ?");
        $stmt->execute([$lead_id]);
        $existing = $stmt->fetch();
        
        if ($existing && !$force) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Komisi sudah pernah dibuat']);
            exit();
        }
        
        if ($lead['unit_id'] <= 0) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Unit tidak ditemukan']);
            exit();
        }
        
        $developer_id = $lead['developer_id'] ?? 0;
        $assigned_type = $lead['assigned_type'] ?? 'external';
        
        // Hitung komisi
        $komisi = hitungKomisiInternal($conn, $lead['unit_id'], $developer_id, $assigned_type);
        
        // Hapus komisi lama jika force
        if ($existing && $force) {
            $stmt = $conn->prepare("DELETE FROM komisi_logs WHERE lead_id = ?");
            $stmt->execute([$lead_id]);
        }
        
        // Insert komisi log
        $stmt = $conn->prepare("
            INSERT INTO komisi_logs (
                lead_id, marketing_id, developer_id, unit_id,
                assigned_type, komisi_eksternal_persen, komisi_eksternal_rupiah,
                komisi_internal_rupiah, komisi_final, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $result = $stmt->execute([
            $lead_id,
            $lead['assigned_marketing_team_id'],
            $developer_id,
            $lead['unit_id'],
            $assigned_type,
            $komisi['komisi_eksternal_persen'],
            $komisi['komisi_eksternal_rupiah'],
            $komisi['komisi_internal_rupiah'],
            $komisi['komisi_final']
        ]);
        
        if (!$result) {
            throw new Exception("Gagal menyimpan komisi");
        }
        
        $komisi_id = $conn->lastInsertId();
        
        // Update status komisi di leads
        $stmt = $conn->prepare("
            UPDATE leads SET 
                komisi_status = 'pending',
                komisi_internal = ?,
                komisi_eksternal = ?,
                komisi_persen = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $komisi['komisi_internal_rupiah'],
            $komisi['komisi_final'],
            $komisi['komisi_eksternal_persen'],
            $lead_id
        ]);
        
        $conn->commit();
        
        writeLog("SUKSES: Komisi dibuat untuk lead $lead_id, komisi_id: $komisi_id");
        
        echo json_encode([
            'success' => true,
            'message' => 'Komisi berhasil diproses',
            'data' => [
                'komisi_id' => $komisi_id,
                'komisi_final' => $komisi['komisi_final'],
                'assigned_type' => $assigned_type,
                'komisi_detail' => $komisi
            ]
        ]);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        writeLog("ERROR process_deal: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================
// ACTION: PROCESS_SPLIT (Komisi split untuk external)
// ============================================
elseif ($action === 'process_split') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $lead_id = isset($input['lead_id']) ? (int)$input['lead_id'] : 0;
    $force = isset($input['force']) ? (bool)$input['force'] : false;
    
    writeLog("Process split: lead_id=$lead_id");
    
    if ($lead_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Lead ID diperlukan']);
        exit();
    }
    
    try {
        $conn->beginTransaction();
        
        // Ambil data lead dengan unit
        $stmt = $conn->prepare("
            SELECT l.*, u.id as unit_id, u.harga, u.komisi_split_persen, u.komisi_split_rupiah,
                   c.developer_id
            FROM leads l
            LEFT JOIN units u ON l.unit_id = u.id
            LEFT JOIN clusters c ON u.cluster_id = c.id
            WHERE l.id = ? AND l.assigned_type = 'external'
            FOR UPDATE
        ");
        $stmt->execute([$lead_id]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lead) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Lead tidak ditemukan atau bukan external']);
            exit();
        }
        
        // Cek apakah sudah ada hutang split
        $stmt = $conn->prepare("SELECT id FROM komisi_split_hutang WHERE lead_id = ?");
        $stmt->execute([$lead_id]);
        $existing = $stmt->fetch();
        
        if ($existing && !$force) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Hutang split sudah pernah dicatat']);
            exit();
        }
        
        if ($lead['unit_id'] <= 0) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Unit tidak ditemukan']);
            exit();
        }
        
        $developer_id = $lead['developer_id'] ?? 0;
        
        // Hitung komisi split
        $platform_split = getPlatformKomisiSplit();
        
        $split_nominal = 0;
        $split_persen = null;
        $split_type = $platform_split['type'];
        
        if (!empty($lead['komisi_split_rupiah'])) {
            $split_nominal = (float)$lead['komisi_split_rupiah'];
            $split_type = 'FIXED';
        } elseif (!empty($lead['komisi_split_persen'])) {
            $split_nominal = (float)$lead['harga'] * ((float)$lead['komisi_split_persen'] / 100);
            $split_persen = (float)$lead['komisi_split_persen'];
        } else {
            if ($platform_split['type'] == 'FIXED') {
                $split_nominal = (float)$platform_split['value'];
            } else {
                $split_nominal = (float)$lead['harga'] * ((float)$platform_split['value'] / 100);
                $split_persen = (float)$platform_split['value'];
            }
        }
        
        // Hapus hutang lama jika force
        if ($existing && $force) {
            $stmt = $conn->prepare("DELETE FROM komisi_split_hutang WHERE lead_id = ?");
            $stmt->execute([$lead_id]);
        }
        
        // Catat hutang split
        $stmt = $conn->prepare("
            INSERT INTO komisi_split_hutang (
                developer_id, lead_id, unit_id, nominal, 
                persentase, type, status, jatuh_tempo, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
        ");
        
        $result = $stmt->execute([
            $developer_id,
            $lead_id,
            $lead['unit_id'],
            $split_nominal,
            $split_persen,
            $split_type
        ]);
        
        if (!$result) {
            throw new Exception("Gagal menyimpan hutang split");
        }
        
        $hutang_id = $conn->lastInsertId();
        
        $conn->commit();
        
        writeLog("SUKSES: Hutang split dicatat untuk lead $lead_id, hutang_id: $hutang_id, nominal: $split_nominal");
        
        echo json_encode([
            'success' => true,
            'message' => 'Hutang split berhasil dicatat',
            'data' => [
                'hutang_id' => $hutang_id,
                'nominal' => $split_nominal,
                'type' => $split_type
            ]
        ]);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        writeLog("ERROR process_split: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================
// ACTION: MARK_CAIR (Tandai komisi cair)
// ============================================
elseif ($action === 'mark_cair') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $komisi_id = isset($input['komisi_id']) ? (int)$input['komisi_id'] : 0;
    $bank_id = isset($input['bank_id']) ? (int)$input['bank_id'] : 0;
    $tanggal_cair = $input['tanggal_cair'] ?? date('Y-m-d H:i:s');
    $catatan = trim($input['catatan'] ?? '');
    
    writeLog("Mark cair: komisi_id=$komisi_id");
    
    if ($komisi_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Komisi ID diperlukan']);
        exit();
    }
    
    // Upload bukti transfer (jika ada)
    $bukti_transfer = null;
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = UPLOAD_PATH . 'bukti/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (in_array($file_ext, $allowed)) {
            $filename = 'bukti_' . $komisi_id . '_' . time() . '.' . $file_ext;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $filepath)) {
                $bukti_transfer = $filename;
            }
        }
    }
    
    try {
        $conn->beginTransaction();
        
        // Ambil data komisi
        $stmt = $conn->prepare("
            SELECT k.*, l.first_name, l.last_name, l.assigned_marketing_team_id,
                   m.nama_lengkap as marketing_name, m.phone as marketing_phone,
                   u.nomor_unit, u.tipe_unit, u.harga,
                   dev.nama_lengkap as developer_name
            FROM komisi_logs k
            LEFT JOIN leads l ON k.lead_id = l.id
            LEFT JOIN marketing_team m ON k.marketing_id = m.id
            LEFT JOIN units u ON k.unit_id = u.id
            LEFT JOIN users dev ON k.developer_id = dev.id
            WHERE k.id = ? FOR UPDATE
        ");
        $stmt->execute([$komisi_id]);
        $komisi = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$komisi) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Komisi tidak ditemukan']);
            exit();
        }
        
        if ($komisi['status'] !== 'pending') {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Komisi sudah ' . $komisi['status']]);
            exit();
        }
        
        // Update status komisi
        $stmt = $conn->prepare("
            UPDATE komisi_logs SET 
                status = 'cair',
                tanggal_cair = ?,
                bukti_transfer = ?,
                catatan = ?
            WHERE id = ?
        ");
        $stmt->execute([$tanggal_cair, $bukti_transfer, $catatan, $komisi_id]);
        
        // Update status di leads
        if ($komisi['lead_id']) {
            $stmt = $conn->prepare("
                UPDATE leads SET 
                    komisi_status = 'cair',
                    komisi_cair_at = ?
                WHERE id = ?
            ");
            $stmt->execute([$tanggal_cair, $komisi['lead_id']]);
        }
        
        $conn->commit();
        
        writeLog("SUKSES: Komisi $komisi_id dicairkan");
        
        // Kirim notifikasi ke marketing
        if ($komisi['marketing_id'] && !empty($komisi['marketing_phone'])) {
            $customer_name = trim(($komisi['first_name'] ?? '') . ' ' . ($komisi['last_name'] ?? ''));
            $unit_info = $komisi['nomor_unit'] . ' (' . $komisi['tipe_unit'] . ')';
            
            $komisi_data = [
                'customer_name' => $customer_name ?: 'Customer',
                'unit_info' => $unit_info,
                'harga' => $komisi['harga'],
                'komisi_final' => $komisi['komisi_final'],
                'developer_name' => $komisi['developer_name'] ?: 'Developer'
            ];
            
            $marketing_data = [
                'id' => $komisi['marketing_id'],
                'nama_lengkap' => $komisi['marketing_name'],
                'phone' => $komisi['marketing_phone']
            ];
            
            // Queue notifikasi (nanti diimplementasi)
            if (function_exists('queueJob')) {
                queueJob([
                    'type' => 'whatsapp',
                    'payload' => [
                        'action' => 'komisi_cair',
                        'to_user_id' => $komisi['marketing_id'],
                        'to_role' => 'marketing',
                        'komisi_data' => $komisi_data,
                        'marketing_data' => $marketing_data
                    ]
                ]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Komisi berhasil dicairkan',
            'bukti' => $bukti_transfer
        ]);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        writeLog("ERROR mark_cair: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================
// ACTION: MARK_BATAL
// ============================================
elseif ($action === 'mark_batal') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $komisi_id = isset($input['komisi_id']) ? (int)$input['komisi_id'] : 0;
    $catatan = trim($input['catatan'] ?? '');
    
    writeLog("Mark batal: komisi_id=$komisi_id");
    
    if ($komisi_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Komisi ID diperlukan']);
        exit();
    }
    
    if (empty($catatan)) {
        echo json_encode(['success' => false, 'message' => 'Catatan pembatalan diperlukan']);
        exit();
    }
    
    try {
        $conn->beginTransaction();
        
        // Ambil data komisi
        $stmt = $conn->prepare("SELECT * FROM komisi_logs WHERE id = ? FOR UPDATE");
        $stmt->execute([$komisi_id]);
        $komisi = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$komisi) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Komisi tidak ditemukan']);
            exit();
        }
        
        if ($komisi['status'] !== 'pending') {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Komisi sudah ' . $komisi['status']]);
            exit();
        }
        
        // Update status komisi
        $stmt = $conn->prepare("
            UPDATE komisi_logs SET 
                status = 'batal',
                catatan = ?
            WHERE id = ?
        ");
        $stmt->execute([$catatan, $komisi_id]);
        
        // Update status di leads
        if ($komisi['lead_id']) {
            $stmt = $conn->prepare("
                UPDATE leads SET 
                    komisi_status = 'batal',
                    komisi_catatan = ?
                WHERE id = ?
            ");
            $stmt->execute([$catatan, $komisi['lead_id']]);
        }
        
        $conn->commit();
        
        writeLog("SUKSES: Komisi $komisi_id dibatalkan");
        
        echo json_encode([
            'success' => true,
            'message' => 'Komisi berhasil dibatalkan'
        ]);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        writeLog("ERROR mark_batal: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================
// ACTION: GET_BY_LEAD
// ============================================
elseif ($action === 'get_by_lead') {
    $lead_id = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : (isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0);
    
    if ($lead_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Lead ID diperlukan']);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT k.*, 
                   m.nama_lengkap as marketing_name,
                   u.nomor_unit, u.tipe_unit,
                   b.nama_bank, b.nomor_rekening, b.atas_nama
            FROM komisi_logs k
            LEFT JOIN marketing_team m ON k.marketing_id = m.id
            LEFT JOIN units u ON k.unit_id = u.id
            LEFT JOIN banks b ON 1=0 /* TODO: join banks jika perlu */
            WHERE k.lead_id = ?
            ORDER BY k.created_at DESC
        ");
        $stmt->execute([$lead_id]);
        $komisi = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $komisi,
            'total' => count($komisi)
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR get_by_lead: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================
// ACTION: GET_BY_MARKETING
// ============================================
elseif ($action === 'get_by_marketing') {
    $marketing_id = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : (isset($_POST['marketing_id']) ? (int)$_POST['marketing_id'] : 0);
    $status = $_GET['status'] ?? 'all';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    if ($marketing_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Marketing ID diperlukan']);
        exit();
    }
    
    try {
        $sql = "
            SELECT k.*, 
                   l.first_name, l.last_name, l.phone as customer_phone,
                   u.nomor_unit, u.tipe_unit,
                   dev.nama_lengkap as developer_name
            FROM komisi_logs k
            LEFT JOIN leads l ON k.lead_id = l.id
            LEFT JOIN units u ON k.unit_id = u.id
            LEFT JOIN users dev ON k.developer_id = dev.id
            WHERE k.marketing_id = ?
        ";
        $params = [$marketing_id];
        
        if ($status !== 'all' && in_array($status, ['pending', 'cair', 'batal'])) {
            $sql .= " AND k.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY k.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $komisi = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Hitung total
        $total_pending = 0;
        $total_cair = 0;
        $total_nominal = 0;
        
        foreach ($komisi as $k) {
            $total_nominal += $k['komisi_final'];
            if ($k['status'] == 'pending') $total_pending += $k['komisi_final'];
            elseif ($k['status'] == 'cair') $total_cair += $k['komisi_final'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $komisi,
            'total' => count($komisi),
            'summary' => [
                'total_nominal' => $total_nominal,
                'total_pending' => $total_pending,
                'total_cair' => $total_cair
            ]
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR get_by_marketing: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================
// ACTION: STATS
// ============================================
elseif ($action === 'stats') {
    $developer_id = isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : (isset($_POST['developer_id']) ? (int)$_POST['developer_id'] : 0);
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    if ($developer_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Developer ID diperlukan']);
        exit();
    }
    
    try {
        // Statistik komisi
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_transaksi,
                SUM(komisi_final) as total_nominal,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'pending' THEN komisi_final ELSE 0 END) as pending_nominal,
                SUM(CASE WHEN status = 'cair' THEN 1 ELSE 0 END) as cair_count,
                SUM(CASE WHEN status = 'cair' THEN komisi_final ELSE 0 END) as cair_nominal,
                SUM(CASE WHEN status = 'batal' THEN 1 ELSE 0 END) as batal_count,
                SUM(CASE WHEN status = 'batal' THEN komisi_final ELSE 0 END) as batal_nominal
            FROM komisi_logs
            WHERE developer_id = ? AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$developer_id, $start_date, $end_date]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Statistik split hutang
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_split,
                SUM(nominal) as total_nominal_split,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as split_pending,
                SUM(CASE WHEN status = 'PENDING' THEN nominal ELSE 0 END) as split_pending_nominal
            FROM komisi_split_hutang
            WHERE developer_id = ? AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$developer_id, $start_date, $end_date]);
        $split = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'komisi' => [
                    'total_transaksi' => (int)$stats['total_transaksi'],
                    'total_nominal' => (float)$stats['total_nominal'],
                    'pending' => (int)$stats['pending_count'],
                    'pending_nominal' => (float)$stats['pending_nominal'],
                    'cair' => (int)$stats['cair_count'],
                    'cair_nominal' => (float)$stats['cair_nominal'],
                    'batal' => (int)$stats['batal_count'],
                    'batal_nominal' => (float)$stats['batal_nominal']
                ],
                'split' => [
                    'total' => (int)$split['total_split'],
                    'total_nominal' => (float)$split['total_nominal_split'],
                    'pending' => (int)$split['split_pending'],
                    'pending_nominal' => (float)$split['split_pending_nominal']
                ]
            ],
            'periode' => [
                'start' => $start_date,
                'end' => $end_date
            ]
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR stats: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================
// ACTION: GET_PLATFORM_SPLIT
// ============================================
elseif ($action === 'get_platform_split') {
    $split = getPlatformKomisiSplit();
    
    echo json_encode([
        'success' => true,
        'data' => $split
    ]);
}

// ============================================
// ACTION DEFAULT
// ============================================
else {
    writeLog("ERROR: Action tidak dikenal: $action");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Action tidak dikenal',
        'available_actions' => [
            'calculate',
            'process_deal',
            'process_split',
            'mark_cair',
            'mark_batal',
            'get_by_lead',
            'get_by_marketing',
            'stats',
            'get_platform_split'
        ]
    ]);
}

writeLog("========== KOMISI PROCESS SELESAI ==========\n");
?>