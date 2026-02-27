<?php
/**
 * FINANCE_BOOKING_VERIFIKASI.PHP - Verifikasi Booking Internal untuk Finance Developer
 * Version: 3.0.0 - UI GLOBAL SISTEM (SESUAI FILE LAIN)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Hanya finance developer yang bisa akses
if (!isFinance()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$developer_id = $_SESSION['developer_id'] ?? 0;
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['nama_lengkap'] ?? 'Finance Developer';

if ($developer_id <= 0) {
    die("Developer ID tidak ditemukan");
}

// ========== PROSES VERIFIKASI BOOKING ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_booking') {
    
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $status = $_POST['status'] ?? ''; // 'diterima' atau 'ditolak'
    $catatan = trim($_POST['catatan'] ?? '');
    
    if ($booking_id <= 0) {
        $error = "❌ ID Booking tidak valid";
    } elseif (!in_array($status, ['diterima', 'ditolak'])) {
        $error = "❌ Status tidak valid";
    } elseif (empty($catatan)) {
        $error = "❌ Catatan verifikasi wajib diisi";
    } else {
        try {
            $conn->beginTransaction();
            
            // Ambil data booking - PASTIKAN MILIK DEVELOPER INI
            $stmt = $conn->prepare("
                SELECT bl.*, u.nomor_unit, u.tipe_unit, u.harga,
                       l.first_name, l.last_name, l.phone, l.email,
                       l.location_key,
                       m.nama_lengkap as marketing_name, m.id as marketing_id,
                       c.developer_id,
                       dev.nama_lengkap as developer_name
                FROM booking_logs bl
                JOIN leads l ON bl.lead_id = l.id
                JOIN units u ON bl.unit_id = u.id
                JOIN blocks b ON u.block_id = b.id
                JOIN clusters c ON b.cluster_id = c.id
                JOIN marketing_team m ON bl.marketing_id = m.id
                LEFT JOIN users dev ON c.developer_id = dev.id
                WHERE bl.id = ? AND c.developer_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$booking_id, $developer_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception("Booking tidak ditemukan atau bukan milik developer Anda");
            }
            
            if ($booking['status_verifikasi'] !== 'pending') {
                throw new Exception("Booking sudah diverifikasi sebelumnya");
            }
            
            // Update status booking
            $update = $conn->prepare("
                UPDATE booking_logs SET 
                    status_verifikasi = ?,
                    catatan_verifikasi = CONCAT(IFNULL(catatan_verifikasi, ''), ?),
                    diverifikasi_oleh = ?,
                    diverifikasi_at = NOW()
                WHERE id = ?
            ");
            
            $verifikasi_note = "\n[" . date('d/m/Y H:i') . "] Diverifikasi oleh Finance Developer: $catatan";
            $update->execute([$status, $verifikasi_note, $user_id, $booking_id]);
            
            // Jika ditolak, unit kembali AVAILABLE
            if ($status === 'ditolak') {
                $update_unit = $conn->prepare("
                    UPDATE units SET 
                        status = 'AVAILABLE',
                        lead_id = NULL,
                        booking_at = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update_unit->execute([$booking['unit_id']]);
                
                // Update lead status
                $update_lead = $conn->prepare("
                    UPDATE leads SET 
                        status = 'Batal',
                        updated_at = NOW(),
                        notes = CONCAT(IFNULL(notes, ''), ?)
                    WHERE id = ?
                ");
                $lead_note = "\n[" . date('d/m/Y H:i') . "] Booking ditolak: $catatan";
                $update_lead->execute([$lead_note, $booking['lead_id']]);
            }
            
            // Jika diterima, unit jadi SOLD
            if ($status === 'diterima') {
                $update_unit = $conn->prepare("
                    UPDATE units SET 
                        status = 'SOLD',
                        sold_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update_unit->execute([$booking['unit_id']]);
                
                // Update lead status jadi DEAL
                $update_lead = $conn->prepare("
                    UPDATE leads SET 
                        status = 'Deal KPR',
                        updated_at = NOW(),
                        notes = CONCAT(IFNULL(notes, ''), ?)
                    WHERE id = ?
                ");
                $lead_note = "\n[" . date('d/m/Y H:i') . "] Booking diterima.";
                $update_lead->execute([$lead_note, $booking['lead_id']]);
            }
            
            $conn->commit();
            
            $success = "✅ Booking #$booking_id berhasil diverifikasi (Status: $status)";
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "❌ Gagal: " . $e->getMessage();
        }
    }
}

// ========== FILTER ==========
$status_filter = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$marketing_filter = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// ========== AMBIL DATA BOOKING UNTUK DEVELOPER INI ==========
$sql = "
    SELECT 
        bl.*,
        l.first_name, l.last_name, l.phone, l.email, l.location_key,
        loc.display_name as location_display,
        loc.icon,
        u.nomor_unit, u.tipe_unit, u.program, u.harga,
        u.komisi_eksternal_persen, u.komisi_eksternal_rupiah, u.komisi_internal_rupiah,
        c.nama_cluster,
        c.developer_id,  // <-- SUDAH ADA DI SINI
        b.nama_block,
        m.nama_lengkap as marketing_name,
        m.phone as marketing_phone,
        dev.nama_lengkap as developer_name
    FROM booking_logs bl
    JOIN leads l ON bl.lead_id = l.id
    JOIN units u ON bl.unit_id = u.id
    JOIN blocks b ON u.block_id = b.id
    JOIN clusters c ON b.cluster_id = c.id
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    LEFT JOIN marketing_team m ON bl.marketing_id = m.id
    LEFT JOIN users dev ON c.developer_id = dev.id
    WHERE c.developer_id = ?
";

$params = [$developer_id];

if (!empty($status_filter)) {
    $sql .= " AND bl.status_verifikasi = ?";
    $params[] = $status_filter;
}

if ($marketing_filter > 0) {
    $sql .= " AND bl.marketing_id = ?";
    $params[] = $marketing_filter;
}

if (!empty($search)) {
    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ? OR u.nomor_unit LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}

if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND DATE(bl.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

// Hitung total
$count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as tmp";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Ambil data dengan pagination
$sql .= " ORDER BY bl.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Format data
foreach ($bookings as &$b) {
    $b['full_name'] = trim($b['first_name'] . ' ' . ($b['last_name'] ?? ''));
    $b['unit_display'] = $b['nama_cluster'] . ' - Block ' . $b['nama_block'] . ' - ' . $b['nomor_unit'];
    $b['harga_formatted'] = $b['harga'] > 0 ? 'Rp ' . number_format($b['harga'], 0, ',', '.') : 'Hubungi marketing';
    $b['harga_booking_formatted'] = $b['harga_booking'] > 0 ? 'Rp ' . number_format($b['harga_booking'], 0, ',', '.') : 'Gratis';
    $b['date_formatted'] = date('d/m/Y H:i', strtotime($b['created_at']));
    
    $status_class = '';
    if ($b['status_verifikasi'] == 'diterima') $status_class = 'success';
    elseif ($b['status_verifikasi'] == 'ditolak') $status_class = 'danger';
    else $status_class = 'warning';
    
    $b['status_class'] = $status_class;
    $b['status_text'] = $b['status_verifikasi'] == 'diterima' ? 'Diterima' : 
                       ($b['status_verifikasi'] == 'ditolak' ? 'Ditolak' : 'Pending');
}

// Statistik
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status_verifikasi = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status_verifikasi = 'diterima' THEN 1 ELSE 0 END) as diterima,
        SUM(CASE WHEN status_verifikasi = 'ditolak' THEN 1 ELSE 0 END) as ditolak
    FROM booking_logs bl
    JOIN units u ON bl.unit_id = u.id
    JOIN blocks b ON u.block_id = b.id
    JOIN clusters c ON b.cluster_id = c.id
    WHERE c.developer_id = ?
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute([$developer_id]);
$stats = $stats_stmt->fetch();

// Ambil daftar marketing untuk filter
$marketing_stmt = $conn->prepare("
    SELECT id, nama_lengkap, phone 
    FROM marketing_team 
    WHERE developer_id = ? AND is_active = 1 
    ORDER BY nama_lengkap
");
$marketing_stmt->execute([$developer_id]);
$marketings = $marketing_stmt->fetchAll();

// Generate CSRF token
$csrf_token = generateCSRFToken();

$page_title = 'Verifikasi Booking';
$page_subtitle = 'Finance Developer';
$page_icon = 'fas fa-calendar-check';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== GLOBAL SYSTEM STYLES - SAMA PERSIS DENGAN FILE LAIN ===== */
:root {
    --primary: #1B4A3C;
    --primary-light: #2A5F4E;
    --secondary: #D64F3C;
    --secondary-light: #FF6B4A;
    --bg: #F5F3F0;
    --surface: #FFFFFF;
    --text: #1A2A24;
    --text-light: #4A5A54;
    --text-muted: #7A8A84;
    --border: #E0DAD3;
    --primary-soft: #E7F3EF;
    --success: #2A9D8F;
    --warning: #E9C46A;
    --danger: #D64F3C;
    --info: #4A90E2;
}

/* ===== MOBILE FIRST LAYOUT ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}

.main-content {
    width: 100%;
    min-height: 100vh;
    padding: 12px;
    margin-left: 0 !important;
}

/* ===== TOP BAR ===== */
.top-bar {
    background: var(--surface);
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 16px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.welcome-text {
    display: flex;
    align-items: center;
    gap: 12px;
}

.welcome-text i {
    width: 48px;
    height: 48px;
    background: rgba(214,79,60,0.1);
    color: var(--secondary);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.welcome-text h2 {
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.3;
}

.welcome-text h2 span {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
    margin-top: 2px;
}

.datetime {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg);
    padding: 8px 16px;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 600;
    color: var(--primary);
}

.time {
    background: var(--surface);
    padding: 4px 12px;
    border-radius: 30px;
}

/* ===== STATS CARDS - HORIZONTAL SCROLL DI MOBILE ===== */
.stats-grid {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    padding: 4px 0 16px 0;
    margin-bottom: 16px;
    -webkit-overflow-scrolling: touch;
}

.stats-grid::-webkit-scrollbar {
    height: 4px;
}

.stats-grid::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.stats-grid::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.stat-card {
    flex: 0 0 140px;
    background: white;
    border-radius: 16px;
    padding: 14px;
    border-left: 4px solid;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.stat-card.total { border-left-color: var(--info); }
.stat-card.pending { border-left-color: var(--warning); }
.stat-card.diterima { border-left-color: var(--success); }
.stat-card.ditolak { border-left-color: var(--danger); }

.stat-icon {
    font-size: 20px;
    margin-bottom: 8px;
}
.stat-card.total .stat-icon { color: var(--info); }
.stat-card.pending .stat-icon { color: var(--warning); }
.stat-card.diterima .stat-icon { color: var(--success); }
.stat-card.ditolak .stat-icon { color: var(--danger); }

.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 2px;
}

.stat-value {
    font-size: 22px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

/* ===== ALERT ===== */
.alert {
    padding: 14px 16px;
    border-radius: 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
    border-left: 4px solid;
}

.alert.success {
    background: #d4edda;
    color: #155724;
    border-left-color: #28a745;
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
    border-left-color: var(--danger);
}

/* ===== FILTER BAR - MOBILE FIRST ===== */
.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
    width: 100%;
    box-sizing: border-box;
}

.filter-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    width: 100%;
    box-sizing: border-box;
}

.filter-select {
    width: 100%;
    min-width: 0; /* Penting! Mencegah elemen memaksa lebar */
    max-width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--border);
    border-radius: 30px;
    font-size: 14px;
    background: white;
    -webkit-appearance: none;
    appearance: none;
    box-sizing: border-box;
}

/* Input date khusus */
input[type="date"].filter-select {
    font-family: 'Inter', sans-serif;
    min-width: 0;
}

/* Tombol actions */
.filter-actions {
    grid-column: span 2;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    width: 100%;
}

.filter-btn {
    width: 100%;
    padding: 14px 16px;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border: none;
    border-radius: 30px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 52px;
    box-sizing: border-box;
}

.filter-btn.reset {
    background: var(--border);
    color: var(--text);
}

/* ===== TABLE CARD ===== */
.table-card {
    background: white;
    border-radius: 24px;
    padding: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    margin-bottom: 24px;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-soft);
    flex-wrap: wrap;
    gap: 12px;
}

.table-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.table-header h3 i {
    color: var(--secondary);
}

.table-badge {
    background: var(--primary-soft);
    color: var(--primary);
    padding: 6px 14px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
}

/* ===== TABLE RESPONSIVE ===== */
.table-responsive {
    overflow-x: auto;
    margin: 0 -20px;
    padding: 0 20px;
    width: calc(100% + 40px);
    -webkit-overflow-scrolling: touch;
}

.table-responsive::-webkit-scrollbar {
    height: 4px;
}

.table-responsive::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

th {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 12px;
    text-transform: uppercase;
    white-space: nowrap;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    font-size: 13px;
}

tr:hover td {
    background: var(--primary-soft);
}

/* ===== STATUS BADGE ===== */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.status-badge.pending {
    background: var(--warning);
    color: #1A2A24;
}

.status-badge.diterima {
    background: var(--success);
    color: white;
}

.status-badge.ditolak {
    background: var(--danger);
    color: white;
}

/* ===== ACTION BUTTONS ===== */
.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
    flex-wrap: wrap;
}

.action-btn {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    border: 1px solid var(--border);
}

.action-btn.view {
    color: var(--info);
    border-color: var(--info);
}
.action-btn.view:hover {
    background: var(--info);
    color: white;
}

.action-btn.verify {
    color: var(--success);
    border-color: var(--success);
}
.action-btn.verify:hover {
    background: var(--success);
    color: white;
}

.action-btn.whatsapp {
    color: #25D366;
    border-color: #25D366;
}
.action-btn.whatsapp:hover {
    background: #25D366;
    color: white;
}

/* ===== MODAL GLOBAL ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    z-index: 10000;
    align-items: flex-end;
    justify-content: center;
    padding: 0;
}

.modal.show {
    display: flex !important;
}

.modal-content {
    background: white;
    border-radius: 28px 28px 0 0;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}

.modal-header {
    padding: 20px 20px 16px;
    border-bottom: 2px solid var(--primary-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    color: var(--primary);
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-header h2 i {
    color: var(--secondary);
    font-size: 20px;
}

.modal-close {
    width: 44px;
    height: 44px;
    background: var(--primary-soft);
    border: none;
    border-radius: 12px;
    color: var(--secondary);
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    max-height: 60vh;
}

.modal-footer {
    padding: 16px 20px 24px;
    display: flex;
    gap: 12px;
    border-top: 1px solid var(--border);
}

.modal-footer button {
    flex: 1;
    min-height: 48px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 15px;
}

/* ===== DETAIL SECTION ===== */
.detail-section {
    background: var(--primary-soft);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 16px;
    border-left: 4px solid var(--secondary);
}

.detail-section h4 {
    color: var(--primary);
    font-size: 15px;
    font-weight: 700;
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-section h4 i {
    color: var(--secondary);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.detail-item {
    background: white;
    padding: 10px;
    border-radius: 10px;
}

.detail-item-label {
    font-size: 10px;
    color: var(--text-muted);
    margin-bottom: 2px;
    text-transform: uppercase;
}

.detail-item-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    word-break: break-word;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding: 8px 0;
    border-bottom: 1px dashed rgba(0,0,0,0.1);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    color: var(--text-muted);
    font-weight: 500;
    font-size: 12px;
}

.detail-value {
    font-weight: 600;
    color: var(--text);
    text-align: right;
    max-width: 60%;
    word-break: break-word;
}

/* ===== FORM ELEMENTS ===== */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--primary);
    font-size: 14px;
}

.form-group label i {
    color: var(--secondary);
    margin-right: 6px;
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
}

textarea.form-control {
    min-height: 80px;
    resize: vertical;
}

/* ===== RADIO GROUP ===== */
.radio-group {
    display: flex;
    gap: 12px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.radio-option {
    flex: 1;
    min-width: 100px;
}

.radio-option input[type="radio"] {
    display: none;
}

.radio-option label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    background: white;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 52px;
}

.radio-option input[type="radio"]:checked + label {
    border-color: var(--secondary);
    background: linear-gradient(135deg, rgba(214,79,60,0.05), rgba(255,107,74,0.05));
}

.radio-option.diterima label i { color: var(--success); }
.radio-option.ditolak label i { color: var(--danger); }

/* ===== INFO CARD ===== */
.info-card {
    background: #fff3cd;
    border: 1px solid #ffeeba;
    border-radius: 12px;
    padding: 12px;
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #856404;
}

.info-card i {
    font-size: 18px;
}

/* ===== BUTTONS ===== */
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 52px;
}

.btn-secondary {
    background: var(--border);
    color: var(--text);
    border: none;
    padding: 14px 20px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 52px;
}

/* ===== PAGINATION ===== */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.pagination-btn {
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: white;
    border: 2px solid var(--border);
    color: var(--text);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
}

.pagination-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 24px;
}

.empty-state i {
    font-size: 48px;
    color: var(--border);
    margin-bottom: 16px;
}

.empty-state p {
    color: var(--text-muted);
}

/* ===== FOOTER ===== */
.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

/* ===== DESKTOP UPGRADE ===== */
@media (min-width: 768px) {
    .main-content {
        margin-left: 280px !important;
        padding: 24px !important;
    }
    
    .top-bar {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
    }
    
    .welcome-text i {
        width: 56px;
        height: 56px;
        font-size: 24px;
    }
    
    .welcome-text h2 {
        font-size: 22px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        overflow-x: visible;
        gap: 20px;
    }
    
    .stat-card {
        flex: none;
        width: auto;
    }
    
    .modal {
        align-items: center;
        padding: 20px;
    }
    
    .modal-content {
        border-radius: 28px;
        max-width: 600px;
        animation: modalFade 0.3s ease;
    }
    
    @keyframes modalFade {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }
    
    .detail-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

</style>

<div class="main-content">
    
    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="welcome-text">
            <i class="<?= $page_icon ?>"></i>
            <h2>
                <?= $page_title ?>
                <span><?= $page_subtitle ?></span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <!-- ALERT -->
    <?php if ($success): ?>
    <div class="alert success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <!-- STATS - HORIZONTAL SCROLL DI MOBILE, GRID DI DESKTOP -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-label">Total Booking</div>
            <div class="stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
        </div>
        <div class="stat-card pending">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= number_format($stats['pending'] ?? 0) ?></div>
        </div>
        <div class="stat-card diterima">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Diterima</div>
            <div class="stat-value"><?= number_format($stats['diterima'] ?? 0) ?></div>
        </div>
        <div class="stat-card ditolak">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-label">Ditolak</div>
            <div class="stat-value"><?= number_format($stats['ditolak'] ?? 0) ?></div>
        </div>
    </div>
    
<!-- FILTER - 2 ATAS 2 BAWAH (TANPA SEARCH) -->
<div class="filter-bar">
    <form method="GET" class="filter-form">
        <!-- BARIS 1: Status dan Marketing -->
        <select name="status" class="filter-select">
            <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="diterima" <?= $status_filter == 'diterima' ? 'selected' : '' ?>>Diterima</option>
            <option value="ditolak" <?= $status_filter == 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
            <option value="" <?= $status_filter == '' ? 'selected' : '' ?>>Semua Status</option>
        </select>
        
        <select name="marketing_id" class="filter-select">
            <option value="0">Semua Marketing</option>
            <?php foreach ($marketings as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $marketing_filter == $m['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($m['nama_lengkap']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        
        <!-- BARIS 2: Dari Tanggal dan Sampai Tanggal -->
        <input type="date" name="date_from" class="filter-select" value="<?= $date_from ?>">
        
        <input type="date" name="date_to" class="filter-select" value="<?= $date_to ?>">
        
        <!-- BARIS 3: Tombol Filter dan Reset -->
        <div class="filter-actions">
            <button type="submit" class="filter-btn"><i class="fas fa-filter"></i><span class="desktop-only"> Filter</span></button>
            <a href="?" class="filter-btn reset"><i class="fas fa-times"></i><span class="desktop-only"> Reset</span></a>
        </div>
    </form>
</div>
    
    <!-- TABLE -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Daftar Booking</h3>
            <span class="table-badge"><i class="fas fa-database"></i> Total: <?= $total_records ?> | Halaman <?= $page ?> dari <?= $total_pages ?></span>
        </div>
        
        <?php if (empty($bookings)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <p>Tidak ada data booking</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Marketing</th>
                        <th>Customer</th>
                        <th>Unit</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><strong>#<?= $b['id'] ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($b['created_at'])) ?><br><small><?= date('H:i', strtotime($b['created_at'])) ?></small></td>
                        <td><strong><?= htmlspecialchars($b['marketing_name'] ?? '-') ?></strong><br><small><?= htmlspecialchars($b['marketing_phone'] ?? '') ?></small></td>
                        <td><strong><?= htmlspecialchars($b['full_name']) ?></strong><br><small><?= htmlspecialchars($b['phone']) ?></small></td>
                        <td><?= htmlspecialchars($b['unit_display']) ?><br><small><?= $b['tipe_unit'] ?> (<?= $b['program'] ?>)</small></td>
                        <td><strong><?= $b['harga_formatted'] ?></strong><br><small>Fee: <?= $b['harga_booking_formatted'] ?></small></td>
                        <td><span class="status-badge <?= $b['status_class'] ?>"><?= $b['status_text'] ?></span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewDetail(<?= $b['id'] ?>)"><i class="fas fa-eye"></i></button>
                                <?php if ($b['status_verifikasi'] == 'pending'): ?>
                                <button class="action-btn verify" onclick="openVerifyModal(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['full_name'])) ?>', '<?= htmlspecialchars(addslashes($b['unit_display'])) ?>')"><i class="fas fa-check-circle"></i></button>
                                <?php endif; ?>
                                <a href="https://wa.me/<?= $b['phone'] ?>" target="_blank" class="action-btn whatsapp"><i class="fab fa-whatsapp"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&status=<?= $status_filter ?>&marketing_id=<?= $marketing_filter ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= $status_filter ?>&marketing_id=<?= $marketing_filter ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&status=<?= $status_filter ?>&marketing_id=<?= $marketing_filter ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Finance Developer Verifikasi Booking v3.0</p>
    </div>
    
</div>

<!-- MODAL DETAIL -->
<div class="modal" id="detailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-info-circle"></i> Detail Booking</h2>
            <button class="modal-close" onclick="closeModal('detailModal')">&times;</button>
        </div>
        <div class="modal-body" id="detailContent">
            <div style="text-align: center; padding: 20px;">
                <i class="fas fa-spinner fa-spin"></i> Memuat...
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('detailModal')">Tutup</button>
        </div>
    </div>
</div>

<!-- MODAL VERIFIKASI -->
<div class="modal" id="verifyModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-check-circle"></i> Verifikasi Booking</h2>
            <button class="modal-close" onclick="closeModal('verifyModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="verify_booking">
            <input type="hidden" name="booking_id" id="verifyBookingId">
            
            <div class="modal-body">
                <div class="detail-section" id="verifyInfo" style="margin-top: 0;">
                    <h4><i class="fas fa-info-circle"></i> Informasi Booking</h4>
                    <div class="detail-row">
                        <span class="detail-label">Customer</span>
                        <span class="detail-value" id="verifyCustomer">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Unit</span>
                        <span class="detail-value" id="verifyUnit">-</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Status Verifikasi</label>
                    <div class="radio-group">
                        <div class="radio-option diterima">
                            <input type="radio" name="status" id="status_terima" value="diterima" checked>
                            <label for="status_terima"><i class="fas fa-check-circle"></i> Terima</label>
                        </div>
                        <div class="radio-option ditolak">
                            <input type="radio" name="status" id="status_tolak" value="ditolak">
                            <label for="status_tolak"><i class="fas fa-times-circle"></i> Tolak</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> Catatan Verifikasi <span class="required">*</span></label>
                    <textarea name="catatan" class="form-control" rows="4" required placeholder="Masukkan catatan verifikasi..."></textarea>
                </div>
                
                <div class="info-card" id="cancelWarning" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Jika ditolak, unit akan kembali tersedia dan status lead menjadi Batal.</span>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('verifyModal')">Batal</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Simpan Verifikasi</button>
            </div>
        </form>
    </div>
</div>

<script>
// ===== MODAL FUNCTIONS =====
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = '';
}

// ===== VIEW DETAIL =====
function viewDetail(bookingId) {
    openModal('detailModal');
    
    fetch('api/booking_process.php?action=detail&booking_id=' + bookingId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const b = data.data;
                
                let html = `
                    <div class="detail-section">
                        <h4><i class="fas fa-user"></i> Data Customer</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="detail-item-label">Nama</div>
                                <div class="detail-item-value">${b.full_name || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">WhatsApp</div>
                                <div class="detail-item-value"><a href="https://wa.me/${b.phone}" target="_blank">${b.phone}</a></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Email</div>
                                <div class="detail-item-value">${b.email || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Lokasi</div>
                                <div class="detail-item-value">${b.icon || ''} ${b.location_display || b.location_key || '-'}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4><i class="fas fa-home"></i> Data Unit</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="detail-item-label">Unit</div>
                                <div class="detail-item-value">${b.nama_cluster || '-'} - Block ${b.nama_block || '-'} - ${b.nomor_unit || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Tipe</div>
                                <div class="detail-item-value">${b.tipe_unit || '-'} (${b.program || '-'})</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Harga</div>
                                <div class="detail-item-value">${b.harga_formatted || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Booking Fee</div>
                                <div class="detail-item-value">${b.harga_booking_formatted || '-'}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4><i class="fas fa-file-invoice"></i> Data Booking</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="detail-item-label">ID Booking</div>
                                <div class="detail-item-value">#${b.booking_id || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Tanggal</div>
                                <div class="detail-item-value">${b.date_formatted || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Metode</div>
                                <div class="detail-item-value">${b.metode_pembayaran || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Marketing</div>
                                <div class="detail-item-value">${b.marketing_name || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Developer</div>
                                <div class="detail-item-value">${b.developer_name || '-'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Status</div>
                                <div class="detail-item-value"><span class="status-badge ${b.status_class}">${b.status_text}</span></div>
                            </div>
                        </div>
                    </div>
                `;
                
                if (b.bukti_pembayaran) {
                    html += `
                        <div class="detail-section">
                            <h4><i class="fas fa-camera"></i> Bukti Pembayaran</h4>
                            <div style="text-align: center;">
                                <a href="<?= SITE_URL ?>/${b.bukti_pembayaran}" target="_blank" class="btn-primary" style="display: inline-block; padding: 8px 16px; font-size: 14px; text-decoration: none;">
                                    <i class="fas fa-eye"></i> Lihat Bukti
                                </a>
                            </div>
                        </div>
                    `;
                }
                
                if (b.catatan_verifikasi) {
                    html += `
                        <div class="detail-section">
                            <h4><i class="fas fa-sticky-note"></i> Catatan</h4>
                            <div style="background: white; padding: 12px; border-radius: 8px; white-space: pre-line;">${b.catatan_verifikasi}</div>
                        </div>
                    `;
                }
                
                document.getElementById('detailContent').innerHTML = html;
            } else {
                document.getElementById('detailContent').innerHTML = '<div style="text-align: center; padding: 40px; color: var(--danger);">Gagal memuat detail</div>';
            }
        })
        .catch(err => {
            document.getElementById('detailContent').innerHTML = '<div style="text-align: center; padding: 40px; color: var(--danger);">Terjadi kesalahan</div>';
        });
}

// ===== OPEN VERIFY MODAL =====
function openVerifyModal(bookingId, customer, unit) {
    document.getElementById('verifyBookingId').value = bookingId;
    document.getElementById('verifyCustomer').textContent = customer;
    document.getElementById('verifyUnit').textContent = unit;
    
    document.getElementById('status_terima').checked = true;
    document.querySelector('textarea[name="catatan"]').value = '';
    document.getElementById('cancelWarning').style.display = 'none';
    
    openModal('verifyModal');
}

// Show warning if reject
document.querySelectorAll('input[name="status"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('cancelWarning').style.display = this.value === 'ditolak' ? 'block' : 'none';
    });
});

// ===== DATE TIME =====
function updateDateTime() {
    const now = new Date();
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { 
        hour12: false 
    });
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Close modal on outside click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(modal => {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            });
        }
    });
});

// Expose functions
window.viewDetail = viewDetail;
window.openVerifyModal = openVerifyModal;
window.closeModal = closeModal;
</script>

<?php include 'includes/footer.php'; ?>