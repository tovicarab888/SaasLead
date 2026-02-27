<?php
/**
 * BOOKING_PROCESS_EXTERNAL.PHP - LEADENGINE
 * Version: 1.0.0 - Booking untuk external marketing
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/booking_process_external.log');

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

function writeExtBookingLog($message, $data = null) {
    $log_dir = LOG_PATH;
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_file = $log_dir . 'booking_process_external.log';
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if ($data !== null) {
        $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    $log .= str_repeat("-", 50) . "\n";
    @file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
}

writeExtBookingLog("========== BOOKING PROCESS EXTERNAL ======");
writeExtBookingLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
writeExtBookingLog("GET: " . json_encode($_GET));
writeExtBookingLog("POST: " . json_encode($_POST));
writeExtBookingLog("Session: " . json_encode($_SESSION));

// Cek session marketing external
if (!isMarketing()) {
    writeExtBookingLog("ERROR: Bukan marketing");
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized - Hanya untuk marketing']));
}

// Cek apakah marketing ini external
$conn = getDB();
if (!$conn) {
    writeExtBookingLog("ERROR: Koneksi database gagal");
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$marketing_id = $_SESSION['marketing_id'];

// Cek tipe marketing
$type_check = $conn->prepare("
    SELECT mt.type_name, mt.commission_type, mt.commission_value
    FROM marketing_team m
    JOIN marketing_types mt ON m.marketing_type_id = mt.id
    WHERE m.id = ?
");
$type_check->execute([$marketing_id]);
$marketing_type = $type_check->fetch(PDO::FETCH_ASSOC);

$is_external = false;
if ($marketing_type && ($marketing_type['type_name'] === 'external' || $marketing_type['commission_type'] === 'EXTERNAL_PERCENT')) {
    $is_external = true;
}

if (!$is_external) {
    writeExtBookingLog("ERROR: Marketing ini bukan external");
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Fitur ini hanya untuk marketing external']));
}

// Rate limiting
$ip = getClientIP();
$rate_key = 'booking_external_' . $ip;
if (!checkRateLimit($rate_key, 10, 60, 300)) {
    writeExtBookingLog("ERROR: Rate limit exceeded for IP: $ip");
    http_response_code(429);
    die(json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']));
}

// CSRF token check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        writeExtBookingLog("ERROR: Invalid CSRF token");
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
    }
}

// Ambil action
$action = '';
if (isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif (isset($_POST['action'])) {
    $action = $_POST['action'];
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['action'])) {
        $action = $input['action'];
        $_POST = array_merge($_POST, $input);
    }
}

writeExtBookingLog("Action: $action");

// ============================================
// ACTION: BOOK UNIT (EXTERNAL)
// ============================================
if ($action === 'book') {
    $lead_id = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : (isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0);
    $unit_id = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : (isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0);
    $developer_id = isset($_POST['developer_id']) ? (int)$_POST['developer_id'] : (isset($_GET['developer_id']) ? (int)$_GET['developer_id'] : 0);
    $metode_pembayaran = isset($_POST['metode_pembayaran']) ? trim($_POST['metode_pembayaran']) : 'transfer';
    $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';

    writeExtBookingLog("Book request: lead_id=$lead_id, unit_id=$unit_id, developer_id=$developer_id");

    if ($lead_id <= 0) {
        writeExtBookingLog("ERROR: Lead ID tidak valid");
        die(json_encode(['success' => false, 'message' => 'Lead ID tidak valid']));
    }

    if ($unit_id <= 0) {
        writeExtBookingLog("ERROR: Unit ID tidak valid");
        die(json_encode(['success' => false, 'message' => 'Unit ID tidak valid']));
    }

    if ($developer_id <= 0) {
        writeExtBookingLog("ERROR: Developer ID tidak valid");
        die(json_encode(['success' => false, 'message' => 'Developer ID tidak valid']));
    }

    try {
        // CEK APAKAH EXTERNAL MARKETING BOLEH AKSES DEVELOPER INI
        require_once __DIR__ . '/can_external_access_developer.php';
        $access_check = canExternalAccessDeveloper($marketing_id, $developer_id);

        if (!$access_check['can_access']) {
            writeExtBookingLog("ERROR: Marketing tidak punya akses ke developer $developer_id");
            die(json_encode([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke developer ini',
                'allowed_developers' => $access_check['allowed_developers']
            ]));
        }

        $conn->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
        $conn->beginTransaction();

        // CEK LEAD
        $lead_stmt = $conn->prepare("
            SELECT l.*, u.developer_id as lead_developer_id
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
            writeExtBookingLog("ERROR: Lead tidak ditemukan atau bukan milik marketing ini");
            die(json_encode(['success' => false, 'message' => 'Lead tidak ditemukan atau bukan milik Anda']));
        }

        // CEK APAKAH LEAD SUDAH PUNYA BOOKING AKTIF
        $check_active_booking = $conn->prepare("
            SELECT u.id, u.nomor_unit
            FROM units u
            WHERE u.lead_id = ? AND u.status = 'BOOKED'
            FOR UPDATE
        ");
        $check_active_booking->execute([$lead_id]);
        $active_booking = $check_active_booking->fetch();
        if ($active_booking) {
            $conn->rollBack();
            writeExtBookingLog("ERROR: Lead $lead_id sudah punya booking aktif di unit " . $active_booking['nomor_unit']);
            die(json_encode([
                'success' => false,
                'message' => 'Lead ini sudah memiliki booking aktif',
                'active_unit' => $active_booking
            ]));
        }

        // CEK UNIT (milik developer yang dimaksud)
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
            writeExtBookingLog("ERROR: Unit tidak ditemukan atau bukan milik developer $developer_id");
            die(json_encode(['success' => false, 'message' => 'Unit tidak ditemukan']));
        }

        if ($unit['status'] !== 'AVAILABLE') {
            $conn->rollBack();
            writeExtBookingLog("ERROR: Unit tidak tersedia (status: {$unit['status']})");
            die(json_encode(['success' => false, 'message' => 'Unit sudah tidak tersedia (status: ' . $unit['status'] . ')']));
        }

        // UPDATE UNIT
        $update_unit = $conn->prepare("
            UPDATE units SET
                status = 'BOOKED',
                lead_id = ?,
                booking_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $update_unit->execute([$lead_id, $unit_id]);

        // UPDATE LEAD
        $old_status = $lead['status'];
        $update_lead = $conn->prepare("
            UPDATE leads SET
                status = 'Booking',
                unit_type = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $update_lead->execute([$unit['tipe_unit'], $lead_id]);

        // HITUNG LEAD SCORE
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

        // CATAT DI MARKETING ACTIVITIES
        $activity_note = "Booking unit " . $unit['nomor_unit'] . " (" . $unit['tipe_unit'] . ") - External";
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

        // CATAT DI BOOKING_LOGS
        $booking_log = $conn->prepare("
            INSERT INTO booking_logs (
                lead_id, unit_id, marketing_id, developer_id, harga_booking,
                status_sebelum, status_sesudah, metode_pembayaran,
                status_verifikasi, catatan_verifikasi, assigned_type, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'external', NOW())
        ");

        $harga_booking = $unit['harga_booking'] > 0 ? $unit['harga_booking'] : null;
        $booking_log->execute([
            $lead_id, $unit_id, $marketing_id, $developer_id, $harga_booking,
            'AVAILABLE', 'BOOKED', $metode_pembayaran, $catatan
        ]);

        $booking_id = $conn->lastInsertId();

        // HITUNG KOMISI EKSTERNAL
        $komisi_data = hitungKomisi($unit, ['assigned_type' => 'external']);
        $komisi_log_id = createKomisiLog($conn, $lead_id, $komisi_data);

        // KIRIM NOTIFIKASI KE FINANCE PLATFORM (BUKAN FINANCE DEVELOPER)
        $finance_platform_stmt = $conn->prepare("
            SELECT id, nama_lengkap, phone, email
            FROM users
            WHERE role = 'finance_platform' AND is_active = 1
            ORDER BY id ASC
            LIMIT 1
        ");
        $finance_platform_stmt->execute();
        $finance_platform = $finance_platform_stmt->fetch(PDO::FETCH_ASSOC);

        if ($finance_platform) {
            queueJob([
                'type' => 'whatsapp',
                'payload' => [
                    'action' => 'external_booking_notification',
                    'to_user_id' => $finance_platform['id'],
                    'to_role' => 'finance_platform',
                    'marketing_id' => $marketing_id,
                    'lead_id' => $lead_id,
                    'unit_id' => $unit_id,
                    'developer_id' => $developer_id,
                    'booking_id' => $booking_id,
                    'komisi_log_id' => $komisi_log_id
                ]
            ]);
        }

        $conn->commit();

        writeExtBookingLog("SUKSES: Unit $unit_id berhasil dibooking oleh external marketing");

        echo json_encode([
            'success' => true,
            'message' => 'Unit berhasil dibooking',
            'data' => [
                'booking_id' => $booking_id,
                'komisi_log_id' => $komisi_log_id,
                'lead_id' => $lead_id,
                'unit_id' => $unit_id,
                'unit_nomor' => $unit['nomor_unit'],
                'unit_tipe' => $unit['tipe_unit'],
                'developer_id' => $developer_id,
                'new_status' => 'Booking',
                'new_score' => $new_score,
                'komisi' => $komisi_data
            ]
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        writeExtBookingLog("ERROR: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: GET_AVAILABLE_DEVELOPERS
// ============================================
elseif ($action === 'available_developers') {
    writeExtBookingLog("ACTION: available_developers untuk marketing ID: $marketing_id");

    try {
        require_once __DIR__ . '/can_external_access_developer.php';
        $access = canExternalAccessDeveloper($marketing_id);

        if (!$access['can_access']) {
            echo json_encode([
                'success' => true,
                'data' => [],
                'allowed_developers' => [],
                'message' => 'Anda belum memiliki akses ke developer manapun'
            ]);
            exit();
        }

        // Ambil detail developer yang diizinkan
        $allowed_ids = $access['allowed_developers'];
        if (empty($allowed_ids)) {
            echo json_encode([
                'success' => true,
                'data' => [],
                'allowed_developers' => []
            ]);
            exit();
        }

        $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
        $stmt = $conn->prepare("
            SELECT
                u.id,
                u.nama_lengkap as developer_name,
                u.nama_perusahaan,
                u.logo_perusahaan,
                u.location_access,
                (SELECT COUNT(*) FROM units u2
                 JOIN blocks b ON u2.block_id = b.id
                 JOIN clusters c ON b.cluster_id = c.id
                 WHERE c.developer_id = u.id AND u2.status = 'AVAILABLE') as available_units
            FROM users u
            WHERE u.id IN ($placeholders) AND u.is_active = 1
            ORDER BY u.nama_lengkap
        ");
        $stmt->execute($allowed_ids);
        $developers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $developers,
            'allowed_developers' => $allowed_ids,
            'count' => count($developers)
        ]);

    } catch (Exception $e) {
        writeExtBookingLog("ERROR available_developers: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: GET_MY_EXTERNAL_BOOKINGS
// ============================================
elseif ($action === 'my_bookings') {
    writeExtBookingLog("ACTION: my_bookings untuk external marketing ID: $marketing_id");

    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    try {
        $count_sql = "
            SELECT COUNT(*) as total
            FROM booking_logs bl
            WHERE bl.marketing_id = ? AND bl.assigned_type = 'external'
        ";
        $count_params = [$marketing_id];

        if (!empty($status)) {
            $count_sql .= " AND bl.status_verifikasi = ?";
            $count_params[] = $status;
        }

        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute($count_params);
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $limit);

        $sql = "
            SELECT
                bl.id as booking_id,
                bl.lead_id,
                bl.unit_id,
                bl.developer_id,
                bl.harga_booking,
                bl.status_sebelum,
                bl.status_sesudah,
                bl.metode_pembayaran,
                bl.status_verifikasi,
                bl.catatan_verifikasi,
                bl.created_at as booking_date,
                l.first_name,
                l.last_name,
                l.phone,
                l.location_key,
                loc.display_name as location_display,
                loc.icon,
                u.nomor_unit,
                u.tipe_unit,
                u.program,
                u.harga,
                u.komisi_eksternal_persen,
                u.komisi_eksternal_rupiah,
                c.nama_cluster,
                b.nama_block,
                dev.nama_lengkap as developer_name,
                kl.id as komisi_log_id,
                kl.status as komisi_status,
                kl.komisi_final
            FROM booking_logs bl
            JOIN leads l ON bl.lead_id = l.id
            JOIN units u ON bl.unit_id = u.id
            JOIN blocks b ON u.block_id = b.id
            JOIN clusters c ON b.cluster_id = c.id
            JOIN users dev ON c.developer_id = dev.id
            LEFT JOIN locations loc ON l.location_key = loc.location_key
            LEFT JOIN komisi_logs kl ON l.id = kl.lead_id AND kl.status = 'pending'
            WHERE bl.marketing_id = ? AND bl.assigned_type = 'external'
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

        foreach ($bookings as &$b) {
            $b['full_name'] = trim($b['first_name'] . ' ' . ($b['last_name'] ?? ''));
            $b['unit_display'] = $b['nama_cluster'] . ' - Block ' . $b['nama_block'] . ' - ' . $b['nomor_unit'];
            $b['harga_formatted'] = $b['harga'] > 0 ? 'Rp ' . number_format($b['harga'], 0, ',', '.') : 'Hubungi marketing';
            $b['harga_booking_formatted'] = $b['harga_booking'] > 0 ? 'Rp ' . number_format($b['harga_booking'], 0, ',', '.') : 'Gratis';
            $b['date_formatted'] = date('d/m/Y H:i', strtotime($b['booking_date']));

            // Hitung komisi
            if ($b['komisi_final'] > 0) {
                $b['komisi_formatted'] = 'Rp ' . number_format($b['komisi_final'], 0, ',', '.');
            } elseif ($b['komisi_eksternal_rupiah'] > 0) {
                $b['komisi_formatted'] = 'Rp ' . number_format($b['komisi_eksternal_rupiah'], 0, ',', '.');
            } else {
                $komisi = $b['harga'] * ($b['komisi_eksternal_persen'] / 100);
                $b['komisi_formatted'] = number_format($b['komisi_eksternal_persen'], 2) . '% (Rp ' . number_format($komisi, 0, ',', '.') . ')';
            }

            $status_class = '';
            if ($b['status_verifikasi'] == 'diterima') $status_class = 'success';
            elseif ($b['status_verifikasi'] == 'ditolak') $status_class = 'danger';
            else $status_class = 'warning';

            $b['status_class'] = $status_class;
            $b['status_text'] = $b['status_verifikasi'] == 'diterima' ? 'Diterima' :
                               ($b['status_verifikasi'] == 'ditolak' ? 'Ditolak' : 'Pending');
        }

        writeExtBookingLog("Ditemukan " . count($bookings) . " external bookings");

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
        writeExtBookingLog("ERROR my_bookings: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION: STATS
// ============================================
elseif ($action === 'stats') {
    writeExtBookingLog("ACTION: stats untuk external marketing ID: $marketing_id");

    try {
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status_verifikasi = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status_verifikasi = 'diterima' THEN 1 ELSE 0 END) as diterima,
                SUM(CASE WHEN status_verifikasi = 'ditolak' THEN 1 ELSE 0 END) as ditolak
            FROM booking_logs
            WHERE marketing_id = ? AND assigned_type = 'external'
        ");
        $stmt->execute([$marketing_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Total komisi pending
        $komisi_stmt = $conn->prepare("
            SELECT SUM(kl.komisi_final) as total_komisi_pending
            FROM komisi_logs kl
            JOIN booking_logs bl ON kl.lead_id = bl.lead_id
            WHERE bl.marketing_id = ? AND bl.assigned_type = 'external' AND kl.status = 'pending'
        ");
        $komisi_stmt->execute([$marketing_id]);
        $komisi = $komisi_stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'data' => [
                'bookings' => [
                    'total' => (int)$stats['total_bookings'],
                    'pending' => (int)$stats['pending'],
                    'diterima' => (int)$stats['diterima'],
                    'ditolak' => (int)$stats['ditolak']
                ],
                'komisi_pending' => $komisi ? (float)$komisi : 0,
                'komisi_formatted' => $komisi ? 'Rp ' . number_format($komisi, 0, ',', '.') : 'Rp 0'
            ]
        ]);

    } catch (Exception $e) {
        writeExtBookingLog("ERROR stats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================
// ACTION DEFAULT
// ============================================
else {
    writeExtBookingLog("ERROR: Action tidak dikenal: $action");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Action tidak dikenal',
        'available_actions' => ['book', 'available_developers', 'my_bookings', 'stats']
    ]);
}

writeExtBookingLog("========== BOOKING PROCESS EXTERNAL SELESAI ======\n");
?>