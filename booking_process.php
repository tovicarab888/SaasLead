<?php
/**
 * BOOKING_PROCESS.PHP - LEADENGINE API
 * Version: 5.0.0 - FINAL FIX: Semua kolom sudah sesuai tabel
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/booking_process.log');

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';

// Logging
$log_dir = __DIR__ . '/../../logs/';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$log_file = $log_dir . 'booking_process.log';

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

writeLog("========== BOOKING PROCESS ======");
writeLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
writeLog("POST: " . json_encode($_POST));
writeLog("FILES: " . json_encode($_FILES));

// Rate limiting
if (!checkRateLimit('booking_' . getClientIP(), 10, 60)) {
    writeLog("ERROR: Rate limit exceeded");
    http_response_code(429);
    die(json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']));
}

// CSRF untuk method POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        writeLog("ERROR: Invalid CSRF token");
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
    }
}

// Cek akses
$is_marketing = isMarketing();
$is_finance_platform = isFinancePlatform();
$is_admin = isAdmin();
$is_finance = isFinance();

if (!$is_marketing && !$is_finance_platform && !$is_admin && !$is_finance) {
    writeLog("ERROR: Unauthorized");
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$conn = getDB();
if (!$conn) {
    writeLog("ERROR: Database connection failed");
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$marketing_id = $_SESSION['marketing_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;
$developer_id = $_SESSION['marketing_developer_id'] ?? $_SESSION['developer_id'] ?? 0;

writeLog("Marketing ID: $marketing_id, Developer ID: $developer_id");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

writeLog("Action: $action");

// ============================================
// ACTION: BOOK UNIT
// ============================================
if ($action === 'book') {
    
    if (!$is_marketing) {
        writeLog("ERROR: Bukan marketing");
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Unauthorized - Hanya untuk marketing']));
    }
    
    $lead_id = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
    $unit_id = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0;
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'transfer';
    $program_ids = $_POST['program_ids'] ?? '';
    $booking_fee_final = isset($_POST['booking_fee_final']) ? (float)$_POST['booking_fee_final'] : 0;
    $catatan = trim($_POST['catatan'] ?? '');
    
    // Data untuk transfer
    $nama_pengirim = trim($_POST['nama_pengirim'] ?? '');
    $bank_pengirim = trim($_POST['bank_pengirim'] ?? '');
    $nomor_rekening_pengirim = trim($_POST['nomor_rekening_pengirim'] ?? '');
    $bank_id = isset($_POST['bank_id']) ? (int)$_POST['bank_id'] : 0;
    
    // Data untuk cash
    $nominal_cash = isset($_POST['nominal_cash_value']) ? (float)$_POST['nominal_cash_value'] : 0;
    $keterangan_cash = trim($_POST['keterangan_cash'] ?? '');
    
    writeLog("Book request: lead_id=$lead_id, unit_id=$unit_id, metode=$metode_pembayaran");
    
    if ($lead_id <= 0) {
        die(json_encode(['success' => false, 'message' => 'Lead ID tidak valid']));
    }
    
    if ($unit_id <= 0) {
        die(json_encode(['success' => false, 'message' => 'Unit ID tidak valid']));
    }
    
    // Validasi upload file
    if (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_OK) {
        die(json_encode(['success' => false, 'message' => 'Bukti pembayaran wajib diupload']));
    }
    
    $file = $_FILES['bukti_transfer'];
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        die(json_encode(['success' => false, 'message' => 'Format file harus JPG, PNG, atau PDF']));
    }
    
    if ($file['size'] > $max_size) {
        die(json_encode(['success' => false, 'message' => 'File maksimal 5MB']));
    }
    
    // Upload file
    $upload_dir = UPLOAD_PATH . 'bukti_pembayaran/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'bukti_' . time() . '_' . $lead_id . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        die(json_encode(['success' => false, 'message' => 'Gagal menyimpan file']));
    }
    
    $relative_path = 'uploads/bukti_pembayaran/' . $filename;
    
    try {
        $conn->beginTransaction();
        
        // Cek lead
        $lead_stmt = $conn->prepare("
            SELECT l.*, u.developer_id 
            FROM leads l
            LEFT JOIN users u ON l.ditugaskan_ke = u.id
            WHERE l.id = ? AND l.assigned_marketing_team_id = ? 
            AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
            FOR UPDATE
        ");
        $lead_stmt->execute([$lead_id, $marketing_id]);
        $lead = $lead_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lead) {
            $conn->rollBack();
            die(json_encode(['success' => false, 'message' => 'Lead tidak ditemukan']));
        }
        
        // Cek unit - AMBIL DEVELOPER_ID DARI CLUSTERS
        $unit_stmt = $conn->prepare("
            SELECT u.*, c.developer_id 
            FROM units u
            JOIN blocks b ON u.block_id = b.id
            JOIN clusters c ON b.cluster_id = c.id
            WHERE u.id = ? AND c.developer_id = ?
            FOR UPDATE
        ");
        $unit_stmt->execute([$unit_id, $developer_id]);
        $unit = $unit_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$unit) {
            $conn->rollBack();
            die(json_encode(['success' => false, 'message' => 'Unit tidak ditemukan']));
        }
        
        if ($unit['status'] !== 'AVAILABLE') {
            $conn->rollBack();
            die(json_encode(['success' => false, 'message' => 'Unit sudah tidak tersedia']));
        }
        
        // Update unit menjadi BOOKED
        $update_unit = $conn->prepare("
            UPDATE units SET 
                status = 'BOOKED', 
                lead_id = ?,
                booking_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $update_unit->execute([$lead_id, $unit_id]);
        
        // Update lead status menjadi Booking
        $old_status = $lead['status'];
        $update_lead = $conn->prepare("
            UPDATE leads SET 
                status = 'Booking',
                unit_type = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $update_lead->execute([$unit['tipe_unit'], $lead_id]);
        
        // Hitung lead score baru
        $lead_data = [
            'first_name' => $lead['first_name'],
            'last_name' => $lead['last_name'],
            'phone' => $lead['phone'],
            'email' => $lead['email'],
            'location_key' => $lead['location_key'],
            'source' => $lead['source']
        ];
        $new_score = calculateLeadScorePremium('Booking', $lead_data, $old_status);
        
        $update_score = $conn->prepare("UPDATE leads SET lead_score = ? WHERE id = ?");
        $update_score->execute([$new_score, $lead_id]);
        
        // Catat di marketing_activities
        $activity_note = "Booking unit " . $unit['nomor_unit'] . " (" . $unit['tipe_unit'] . ")";
        if (!empty($catatan)) {
            $activity_note .= " - " . $catatan;
        }
        
        $activity_stmt = $conn->prepare("
            INSERT INTO marketing_activities (
                lead_id, marketing_id, developer_id, action_type, 
                status_before, status_after, note_text, created_at
            ) VALUES (?, ?, ?, 'booking', ?, 'Booking', ?, NOW())
        ");
        $activity_stmt->execute([$lead_id, $marketing_id, $developer_id, $old_status, $activity_note]);
        
        // Tentukan harga booking final
        $harga_booking_final = $booking_fee_final > 0 ? $booking_fee_final : $unit['harga_booking'];
        
        // INSERT KE BOOKING_LOGS - SESUAI STRUKTUR TABEL
        $booking_log = $conn->prepare("
            INSERT INTO booking_logs (
                lead_id, 
                unit_id, 
                marketing_id, 
                harga_booking,
                status_sebelum, 
                status_sesudah, 
                metode_pembayaran,
                selected_programs,
                nama_pengirim, 
                bank_pengirim, 
                nomor_rekening_pengirim, 
                bank_id,
                nominal_cash, 
                keterangan_cash, 
                bukti_pembayaran,
                status_verifikasi, 
                catatan_verifikasi, 
                created_at
            ) VALUES (
                ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                ?, ?, ?, 
                'pending', ?, NOW()
            )
        ");
        
        $params = [
            $lead_id,                          // 1
            $unit_id,                          // 2
            $marketing_id,                     // 3
            $harga_booking_final,               // 4
            'AVAILABLE',                        // 5
            'BOOKED',                           // 6
            $metode_pembayaran,                  // 7
            $program_ids,                        // 8
            $nama_pengirim,                      // 9
            $bank_pengirim,                      // 10
            $nomor_rekening_pengirim,            // 11
            $bank_id,                            // 12
            $nominal_cash,                        // 13
            $keterangan_cash,                     // 14
            $relative_path,                        // 15
            $catatan                               // 16 (untuk catatan_verifikasi)
        ];
        
        writeLog("Params count: " . count($params));
        
        $booking_log->execute($params);
        $booking_id = $conn->lastInsertId();
        
        $conn->commit();
        
        writeLog("SUKSES: Unit $unit_id berhasil dibooking, booking_id: $booking_id");
        
        echo json_encode([
            'success' => true,
            'message' => 'Unit berhasil dibooking',
            'data' => [
                'booking_id' => $booking_id,
                'lead_id' => $lead_id,
                'unit_id' => $unit_id,
                'unit_nomor' => $unit['nomor_unit'],
                'unit_tipe' => $unit['tipe_unit']
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        writeLog("ERROR: " . $e->getMessage());
        writeLog("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: CANCEL BOOKING
// ============================================
elseif ($action === 'cancel') {
    
    if (!$is_marketing) {
        writeLog("ERROR: Bukan marketing");
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Unauthorized - Hanya untuk marketing']));
    }
    
    $lead_id = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
    $unit_id = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0;
    $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $alasan = trim($_POST['alasan'] ?? '');
    
    writeLog("Cancel request: lead_id=$lead_id, unit_id=$unit_id, booking_id=$booking_id");
    
    if ($lead_id <= 0 || $unit_id <= 0) {
        die(json_encode(['success' => false, 'message' => 'Data tidak valid']));
    }
    
    if (empty($alasan)) {
        die(json_encode(['success' => false, 'message' => 'Alasan pembatalan wajib diisi']));
    }
    
    try {
        $conn->beginTransaction();
        
        // Cek unit
        $unit_stmt = $conn->prepare("
            SELECT u.*, c.developer_id 
            FROM units u
            JOIN blocks b ON u.block_id = b.id
            JOIN clusters c ON b.cluster_id = c.id
            WHERE u.id = ? AND c.developer_id = ? AND u.lead_id = ?
            FOR UPDATE
        ");
        $unit_stmt->execute([$unit_id, $developer_id, $lead_id]);
        $unit = $unit_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$unit) {
            $conn->rollBack();
            die(json_encode(['success' => false, 'message' => 'Data tidak valid']));
        }
        
        if ($unit['status'] !== 'BOOKED') {
            $conn->rollBack();
            die(json_encode(['success' => false, 'message' => 'Unit tidak dalam status booking']));
        }
        
        // Update unit kembali ke AVAILABLE
        $update_unit = $conn->prepare("
            UPDATE units SET 
                status = 'AVAILABLE', 
                lead_id = NULL,
                booking_at = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $update_unit->execute([$unit_id]);
        
        // Update lead
        $update_lead = $conn->prepare("
            UPDATE leads SET 
                status = 'Batal',
                updated_at = NOW(),
                notes = CONCAT(IFNULL(notes, ''), ?)
            WHERE id = ?
        ");
        $note = "\n[" . date('d/m/Y H:i') . "] Booking dibatalkan: " . $alasan;
        $update_lead->execute([$note, $lead_id]);
        
        // Update booking_logs
        $log_update = $conn->prepare("
            UPDATE booking_logs 
            SET status_verifikasi = 'ditolak', 
                catatan_verifikasi = CONCAT(IFNULL(catatan_verifikasi, ''), ?)
            WHERE id = ? OR (lead_id = ? AND unit_id = ?)
            ORDER BY id DESC LIMIT 1
        ");
        $cancel_note = "\nDibatalkan pada " . date('d/m/Y H:i') . " oleh marketing. Alasan: $alasan";
        
        if ($booking_id > 0) {
            $log_update->execute([$cancel_note, $booking_id]);
        } else {
            $log_update->execute([$cancel_note, 0, $lead_id, $unit_id]);
        }
        
        $conn->commit();
        
        writeLog("SUKSES: Booking unit $unit_id dibatalkan");
        
        echo json_encode([
            'success' => true,
            'message' => 'Booking berhasil dibatalkan'
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        writeLog("ERROR: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: GET MY BOOKINGS
// ============================================
elseif ($action === 'my_bookings') {
    
    if (!$is_marketing) {
        writeLog("ERROR: Bukan marketing");
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Unauthorized - Hanya untuk marketing']));
    }
    
    writeLog("ACTION: my_bookings untuk marketing ID: $marketing_id");
    
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;
    
    try {
        // Hitung total
        $count_sql = "SELECT COUNT(*) FROM booking_logs WHERE marketing_id = ?";
        $count_params = [$marketing_id];
        
        if (!empty($status)) {
            $count_sql .= " AND status_verifikasi = ?";
            $count_params[] = $status;
        }
        
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute($count_params);
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $limit);
        
        // Ambil data booking
        $sql = "
            SELECT 
                bl.id as booking_id,
                bl.lead_id,
                bl.unit_id,
                bl.harga_booking,
                bl.metode_pembayaran,
                bl.status_verifikasi,
                bl.catatan_verifikasi,
                bl.selected_programs as program_ids,
                bl.nama_pengirim,
                bl.bank_pengirim,
                bl.nomor_rekening_pengirim,
                bl.bukti_pembayaran,
                bl.created_at as booking_date,
                l.first_name,
                l.last_name,
                l.phone,
                u.nomor_unit,
                u.tipe_unit,
                u.program,
                u.harga,
                c.nama_cluster,
                b.nama_block
            FROM booking_logs bl
            JOIN leads l ON bl.lead_id = l.id
            JOIN units u ON bl.unit_id = u.id
            JOIN blocks b ON u.block_id = b.id
            JOIN clusters c ON b.cluster_id = c.id
            WHERE bl.marketing_id = ?
        ";
        $params = [$marketing_id];
        
        if (!empty($status)) {
            $sql .= " AND bl.status_verifikasi = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY bl.id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data
        foreach ($bookings as &$b) {
            $b['full_name'] = trim($b['first_name'] . ' ' . ($b['last_name'] ?? ''));
            $b['unit_display'] = $b['nama_cluster'] . ' - Block ' . $b['nama_block'] . ' - ' . $b['nomor_unit'];
            $b['harga_formatted'] = 'Rp ' . number_format($b['harga'], 0, ',', '.');
            $b['harga_booking_formatted'] = $b['harga_booking'] > 0 ? 'Rp ' . number_format($b['harga_booking'], 0, ',', '.') : 'Gratis';
            $b['date_formatted'] = date('d/m/Y H:i', strtotime($b['booking_date']));
            
            $status_class = '';
            if ($b['status_verifikasi'] == 'diterima') $status_class = 'success';
            elseif ($b['status_verifikasi'] == 'ditolak') $status_class = 'danger';
            else $status_class = 'warning';
            
            $b['status_class'] = $status_class;
            $b['status_text'] = $b['status_verifikasi'] == 'diterima' ? 'Diterima' : 
                               ($b['status_verifikasi'] == 'ditolak' ? 'Ditolak' : 'Pending');
        }
        
        echo json_encode([
            'success' => true,
            'data' => $bookings,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_records' => $total_records,
                'limit' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR my_bookings: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: GET DETAIL BOOKING
// ============================================
elseif ($action === 'detail') {
    $booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
    
    writeLog("Detail booking: booking_id=$booking_id");
    
    if ($booking_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Booking ID diperlukan']);
        exit();
    }
    
    try {
        $sql = "
            SELECT 
                bl.*,
                l.first_name, l.last_name, l.phone, l.email, l.location_key,
                loc.display_name as location_display,
                loc.icon,
                u.nomor_unit, u.tipe_unit, u.program, u.harga,
                u.komisi_eksternal_persen, u.komisi_eksternal_rupiah, u.komisi_internal_rupiah,
                m.nama_lengkap as marketing_name,
                m.phone as marketing_phone,
                c.nama_cluster,
                c.developer_id,
                b.nama_block,
                dev.nama_lengkap as developer_name
            FROM booking_logs bl
            JOIN leads l ON bl.lead_id = l.id
            JOIN units u ON bl.unit_id = u.id
            JOIN blocks b ON u.block_id = b.id
            JOIN clusters c ON b.cluster_id = c.id
            LEFT JOIN locations loc ON l.location_key = loc.location_key
            LEFT JOIN marketing_team m ON bl.marketing_id = m.id
            LEFT JOIN users dev ON c.developer_id = dev.id
            WHERE bl.id = ?
        ";
        
        $params = [$booking_id];
        
        // Filter berdasarkan role
        if ($is_marketing) {
            $sql .= " AND bl.marketing_id = ?";
            $params[] = $marketing_id;
        } elseif ($is_finance) {
            $sql .= " AND c.developer_id = ?";
            $params[] = $developer_id;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            $data['harga_formatted'] = 'Rp ' . number_format($data['harga'], 0, ',', '.');
            $data['harga_booking_formatted'] = $data['harga_booking'] > 0 ? 'Rp ' . number_format($data['harga_booking'], 0, ',', '.') : 'Gratis';
            $data['full_name'] = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
            $data['date_formatted'] = date('d/m/Y H:i', strtotime($data['created_at']));
            
            $status_class = '';
            if ($data['status_verifikasi'] == 'diterima') $status_class = 'success';
            elseif ($data['status_verifikasi'] == 'ditolak') $status_class = 'danger';
            else $status_class = 'warning';
            
            $data['status_class'] = $status_class;
            $data['status_text'] = $data['status_verifikasi'] == 'diterima' ? 'Diterima' : 
                                   ($data['status_verifikasi'] == 'ditolak' ? 'Ditolak' : 'Pending');
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Data booking tidak ditemukan'
            ]);
        }
        
    } catch (Exception $e) {
        writeLog("ERROR detail: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: GET AVAILABLE LEADS
// ============================================
elseif ($action === 'available_leads') {
    
    if (!$is_marketing) {
        writeLog("ERROR: Bukan marketing");
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Unauthorized - Hanya untuk marketing']));
    }
    
    writeLog("ACTION: available_leads untuk marketing ID: $marketing_id");
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                l.id,
                l.first_name,
                l.last_name,
                l.phone,
                l.status,
                CONCAT(l.first_name, ' ', IFNULL(l.last_name, '')) as full_name
            FROM leads l
            WHERE l.assigned_marketing_team_id = ? 
                AND l.status IN ('Baru', 'Follow Up', 'Survey')
                AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
                AND NOT EXISTS (
                    SELECT 1 FROM units u WHERE u.lead_id = l.id AND u.status IN ('BOOKED', 'SOLD')
                )
            ORDER BY l.created_at DESC
            LIMIT 200
        ");
        $stmt->execute([$marketing_id]);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $leads,
            'total' => count($leads)
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR available_leads: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: STATS
// ============================================
elseif ($action === 'stats') {
    
    if (!$is_marketing) {
        writeLog("ERROR: Bukan marketing");
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Unauthorized - Hanya untuk marketing']));
    }
    
    writeLog("ACTION: stats untuk marketing ID: $marketing_id");
    
    try {
        // Total booking marketing ini
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status_verifikasi = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status_verifikasi = 'diterima' THEN 1 ELSE 0 END) as diterima,
                SUM(CASE WHEN status_verifikasi = 'ditolak' THEN 1 ELSE 0 END) as ditolak
            FROM booking_logs
            WHERE marketing_id = ?
        ");
        $stmt->execute([$marketing_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Unit tersedia
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total_available
            FROM units u
            JOIN blocks b ON u.block_id = b.id
            JOIN clusters c ON b.cluster_id = c.id
            WHERE c.developer_id = ? AND u.status = 'AVAILABLE'
        ");
        $stmt->execute([$developer_id]);
        $available = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'my_bookings' => [
                    'total' => (int)$stats['total_bookings'],
                    'pending' => (int)$stats['pending'],
                    'diterima' => (int)$stats['diterima'],
                    'ditolak' => (int)$stats['ditolak']
                ],
                'available_units' => [
                    'total' => (int)$available['total_available']
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR stats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION DEFAULT
// ============================================
else {
    writeLog("ERROR: Action tidak dikenal: $action");
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Action tidak dikenal'
    ]);
}

writeLog("========== BOOKING PROCESS SELESAI ======\n");
?>