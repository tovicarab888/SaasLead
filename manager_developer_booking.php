<?php
/**
 * MANAGER_DEVELOPER_BOOKING.PHP - VERIFIKASI BOOKING UNTUK MANAGER DEVELOPER
 * Version: 1.0.0 - SPLIT 50:50 (Manager & Finance)
 * FULL CODE - MENGIKUTI STRUKTUR MANAGER_DASHBOARD.PHP
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/manager_developer_booking.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

if (!checkAuth()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Hanya Finance Developer dan Finance Platform yang bisa akses halaman ini
if (!isFinance() && !isFinancePlatform()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Finance Developer dan Finance Platform.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// Ambil data user dari session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$nama_lengkap = $_SESSION['nama_lengkap'];
$role = $_SESSION['role'];
$developer_id = $_SESSION['developer_id'] ?? 0;

// Ambil data developer
$stmt = $conn->prepare("SELECT nama_lengkap as nama_perusahaan FROM users WHERE id = ?");
$stmt->execute([$developer_id]);
$developer = $stmt->fetch();
$developer_name = $developer['nama_perusahaan'] ?? 'Developer';

// ============================================
// PROSES FORM (POST)
// ============================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token';
        $message_type = 'error';
    } else {
        
        if ($_POST['action'] === 'verify_booking') {
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $notes = trim($_POST['notes'] ?? '');
            
            if ($booking_id > 0 && in_array($status, ['diterima', 'ditolak'])) {
                try {
                    $conn->beginTransaction();
                    
                    // Ambil data booking dengan FOR UPDATE (lock)
                    $stmt = $conn->prepare("
                        SELECT bl.*, u.developer_id, u.nomor_unit, u.tipe_unit, u.harga,
                               u.komisi_eksternal_persen, u.komisi_eksternal_rupiah, u.komisi_internal_rupiah,
                               l.first_name, l.last_name, l.assigned_marketing_team_id,
                               m.nama_lengkap as marketing_name
                        FROM booking_logs bl
                        JOIN units u ON bl.unit_id = u.id
                        JOIN blocks b ON u.block_id = b.id
                        JOIN clusters c ON b.cluster_id = c.id
                        JOIN leads l ON bl.lead_id = l.id
                        LEFT JOIN marketing_team m ON bl.marketing_id = m.id
                        WHERE bl.id = ? AND c.developer_id = ?
                        FOR UPDATE
                    ");
                    $stmt->execute([$booking_id, $developer_id]);
                    $booking = $stmt->fetch();
                    
                    if (!$booking) {
                        throw new Exception("Booking tidak ditemukan");
                    }
                    
                    // Update booking_logs
                    $update = $conn->prepare("
                        UPDATE booking_logs 
                        SET status_verifikasi = ?, 
                            catatan_verifikasi = CONCAT(IFNULL(catatan_verifikasi, ''), ?),
                            diverifikasi_oleh = ?,
                            diverifikasi_at = NOW()
                        WHERE id = ?
                    ");
                    $verifikasi_note = "\n[" . date('d/m/Y H:i') . "] Diverifikasi oleh $nama_lengkap ($role): $notes";
                    $update->execute([$status, $verifikasi_note, $user_id, $booking_id]);
                    
                    // Catat di tabel verifikasi
                    $insert_verif = $conn->prepare("
                        INSERT INTO booking_verifikasi (booking_id, verified_by, status, notes, verification_date, created_at)
                        VALUES (?, ?, ?, ?, NOW(), NOW())
                    ");
                    $insert_verif->execute([$booking_id, $user_id, strtoupper($status), $notes]);
                    
                    // Jika ditolak, kembalikan unit ke AVAILABLE
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
                        
                        // Update lead status menjadi Batal
                        $update_lead = $conn->prepare("
                            UPDATE leads SET 
                                status = 'Batal',
                                updated_at = NOW(),
                                notes = CONCAT(IFNULL(notes, ''), ?)
                            WHERE id = ?
                        ");
                        $lead_note = "\n[" . date('d/m/Y H:i') . "] Booking ditolak oleh $role. Alasan: $notes";
                        $update_lead->execute([$lead_note, $booking['lead_id']]);
                        
                        // Update komisi_logs menjadi batal
                        $update_komisi = $conn->prepare("
                            UPDATE komisi_logs 
                            SET status = 'batal' 
                            WHERE lead_id = ? AND unit_id = ?
                        ");
                        $update_komisi->execute([$booking['lead_id'], $booking['unit_id']]);
                    } else {
                        // Jika diterima, update status di komisi_logs
                        $update_komisi = $conn->prepare("
                            UPDATE komisi_logs 
                            SET status = 'pending' 
                            WHERE lead_id = ? AND unit_id = ?
                        ");
                        $update_komisi->execute([$booking['lead_id'], $booking['unit_id']]);
                    }
                    
                    $conn->commit();
                    
                    $message = "Booking berhasil diverifikasi dengan status: " . ucfirst($status);
                    $message_type = 'success';
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    $message = "Error: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

// ============================================
// AMBIL DATA UNTUK DITAMPILKAN
// ============================================

// Filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$marketing_filter = isset($_GET['marketing_id']) ? (int)$_GET['marketing_id'] : 0;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Bangun query untuk menghitung total records
$count_sql = "
    SELECT COUNT(*) 
    FROM booking_logs bl
    JOIN units u ON bl.unit_id = u.id
    JOIN blocks b ON u.block_id = b.id
    JOIN clusters c ON b.cluster_id = c.id
    JOIN leads l ON bl.lead_id = l.id
    WHERE c.developer_id = ?
";
$count_params = [$developer_id];

if ($status_filter !== 'all') {
    $count_sql .= " AND bl.status_verifikasi = ?";
    $count_params[] = $status_filter;
}

if (!empty($search)) {
    $count_sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR u.nomor_unit LIKE ? OR l.phone LIKE ?)";
    $s = "%$search%";
    $count_params[] = $s;
    $count_params[] = $s;
    $count_params[] = $s;
    $count_params[] = $s;
}

if ($marketing_filter > 0) {
    $count_sql .= " AND bl.marketing_id = ?";
    $count_params[] = $marketing_filter;
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
        bl.status_sebelum,
        bl.status_sesudah,
        bl.metode_pembayaran,
        bl.status_verifikasi,
        bl.catatan_verifikasi,
        bl.created_at as booking_date,
        bl.diverifikasi_oleh,
        bl.diverifikasi_at,
        l.id as lead_id,
        l.first_name,
        l.last_name,
        l.phone,
        l.email,
        l.location_key,
        loc.display_name as location_display,
        loc.icon,
        u.nomor_unit,
        u.tipe_unit,
        u.program,
        u.harga,
        u.komisi_eksternal_persen,
        u.komisi_eksternal_rupiah,
        u.komisi_internal_rupiah,
        c.nama_cluster,
        b.nama_block,
        m.id as marketing_id,
        m.nama_lengkap as marketing_name,
        m.phone as marketing_phone
    FROM booking_logs bl
    JOIN leads l ON bl.lead_id = l.id
    JOIN units u ON bl.unit_id = u.id
    JOIN blocks b ON u.block_id = b.id
    JOIN clusters c ON b.cluster_id = c.id
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    LEFT JOIN marketing_team m ON bl.marketing_id = m.id
    WHERE c.developer_id = ?
";

$params = [$developer_id];

if ($status_filter !== 'all') {
    $sql .= " AND bl.status_verifikasi = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR u.nomor_unit LIKE ? OR l.phone LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
}

if ($marketing_filter > 0) {
    $sql .= " AND bl.marketing_id = ?";
    $params[] = $marketing_filter;
}

$sql .= " ORDER BY bl.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Format data
foreach ($bookings as &$b) {
    $b['full_name'] = trim($b['first_name'] . ' ' . ($b['last_name'] ?? ''));
    $b['unit_display'] = $b['nama_cluster'] . ' - Block ' . $b['nama_block'] . ' - ' . $b['nomor_unit'];
    $b['harga_formatted'] = $b['harga'] > 0 ? 'Rp ' . number_format($b['harga'], 0, ',', '.') : 'Hubungi marketing';
    $b['harga_booking_formatted'] = $b['harga_booking'] > 0 ? 'Rp ' . number_format($b['harga_booking'], 0, ',', '.') : 'Gratis';
    $b['date_formatted'] = date('d/m/Y H:i', strtotime($b['booking_date']));
    
    // Hitung komisi
    if ($b['komisi_eksternal_rupiah'] > 0) {
        $b['komisi_marketing'] = 'Rp ' . number_format($b['komisi_eksternal_rupiah'], 0, ',', '.');
    } else {
        $b['komisi_marketing'] = number_format($b['komisi_eksternal_persen'] ?? 3.00, 2, ',', '.') . '%';
    }
    $b['komisi_internal_formatted'] = 'Rp ' . number_format($b['komisi_internal_rupiah'] ?? 0, 0, ',', '.');
    
    // Status badge
    $status_class = '';
    if ($b['status_verifikasi'] == 'diterima') $status_class = 'success';
    elseif ($b['status_verifikasi'] == 'ditolak') $status_class = 'danger';
    else $status_class = 'warning';
    
    $b['status_class'] = $status_class;
    $b['status_text'] = $b['status_verifikasi'] == 'diterima' ? 'Diterima' : 
                       ($b['status_verifikasi'] == 'ditolak' ? 'Ditolak' : 'Pending');
    
    // Verifikator
    if ($b['diverifikasi_oleh']) {
        $verif = $conn->prepare("SELECT nama_lengkap, role FROM users WHERE id = ?");
        $verif->execute([$b['diverifikasi_oleh']]);
        $verif_data = $verif->fetch();
        $b['diverifikasi_oleh_nama'] = $verif_data['nama_lengkap'] ?? 'User ' . $b['diverifikasi_oleh'];
        $b['diverifikasi_role'] = $verif_data['role'] ?? '';
        $b['diverifikasi_at_formatted'] = date('d/m/Y H:i', strtotime($b['diverifikasi_at']));
    }
}

// Ambil statistik
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

// Set variables untuk header
$page_title = 'Verifikasi Booking';
$page_subtitle = $developer_name . ' - ' . ($role == 'manager_developer' ? 'Manager Developer' : 'Finance');
$page_icon = 'fas fa-calendar-check';

// Include header
include 'includes/header.php';
?>

<!-- SIDEBAR -->
<?php include 'includes/sidebar.php'; ?>

<!-- MAIN CONTENT -->
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
    
    <!-- Alert Message -->
    <?php if ($message): ?>
    <div class="alert <?= $message_type ?>">
        <i class="fas <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    
    <!-- Statistik Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-label">Total Booking</div>
            <div class="stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: #ffc107;">
            <div class="stat-icon" style="color: #ffc107;"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= number_format($stats['pending'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: #28a745;">
            <div class="stat-icon" style="color: #28a745;"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Diterima</div>
            <div class="stat-value"><?= number_format($stats['diterima'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: #dc3545;">
            <div class="stat-icon" style="color: #dc3545;"><i class="fas fa-times-circle"></i></div>
            <div class="stat-label">Ditolak</div>
            <div class="stat-value"><?= number_format($stats['ditolak'] ?? 0) ?></div>
        </div>
    </div>
    
    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
            <select name="status" class="filter-select">
                <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="diterima" <?= $status_filter == 'diterima' ? 'selected' : '' ?>>Diterima</option>
                <option value="ditolak" <?= $status_filter == 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>Semua Status</option>
            </select>
            
            <select name="marketing_id" class="filter-select">
                <option value="0">Semua Marketing</option>
                <?php foreach ($marketings as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $marketing_filter == $m['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <input type="text" name="search" class="filter-input" placeholder="Cari nama / unit / no. HP" value="<?= htmlspecialchars($search) ?>">
            
            <input type="date" name="date_from" class="filter-input" value="<?= $date_from ?>" style="max-width: 150px;">
            <input type="date" name="date_to" class="filter-input" value="<?= $date_to ?>" style="max-width: 150px;">
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn"><i class="fas fa-search"></i> Filter</button>
                <a href="?" class="filter-btn reset"><i class="fas fa-sync"></i> Reset</a>
            </div>
        </form>
    </div>
    
    <!-- Tabel Booking -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fas fa-list"></i> Daftar Booking
                <?php if ($status_filter != 'all'): ?>
                <span class="table-badge"><i class="fas fa-filter"></i> Status: <?= ucfirst($status_filter) ?></span>
                <?php endif; ?>
            </h3>
            <span class="table-badge"><i class="fas fa-database"></i> Total: <?= number_format($total_records) ?></span>
        </div>
        
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
                        <th>Komisi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">
                            <i class="fas fa-calendar-times" style="font-size: 48px; color: #ccc; margin-bottom: 16px; display: block;"></i>
                            <p style="color: #7A8A84; font-size: 16px;">Tidak ada data booking</p>
                            <p style="color: #7A8A84; font-size: 14px;">Belum ada booking yang perlu diverifikasi.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $b): ?>
                        <tr class="<?= $b['status_verifikasi'] == 'pending' ? 'duplicate-warning' : '' ?>">
                            <td>#<?= $b['booking_id'] ?></td>
                            <td><strong><?= date('d/m/Y', strtotime($b['booking_date'])) ?></strong><br><small><?= date('H:i', strtotime($b['booking_date'])) ?></small></td>
                            <td>
                                <strong><?= htmlspecialchars($b['marketing_name'] ?? '-') ?></strong><br>
                                <small><a href="https://wa.me/<?= $b['marketing_phone'] ?>" target="_blank" style="color: #25D366;"><?= htmlspecialchars($b['marketing_phone'] ?? '') ?></a></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($b['full_name'] ?: $b['first_name']) ?></strong><br>
                                <small><a href="https://wa.me/<?= $b['phone'] ?>" target="_blank" style="color: #25D366;"><?= htmlspecialchars($b['phone']) ?></a></small>
                            </td>
                            <td>
                                <span class="location-badge">
                                    <span class="location-icon-small"><?= htmlspecialchars($b['icon'] ?? '🏠') ?></span>
                                    <?= htmlspecialchars($b['unit_display']) ?>
                                </span><br>
                                <small><?= htmlspecialchars($b['tipe_unit']) ?> - <?= htmlspecialchars($b['program']) ?></small>
                            </td>
                            <td><strong><?= $b['harga_formatted'] ?></strong><br><small>Booking: <?= $b['harga_booking_formatted'] ?></small></td>
                            <td>
                                <strong><?= $b['komisi_marketing'] ?></strong><br>
                                <small>Internal: <?= $b['komisi_internal_formatted'] ?></small>
                            </td>
                            <td>
                                <span class="status-badge" style="background: <?= $b['status_class'] == 'success' ? '#28a745' : ($b['status_class'] == 'danger' ? '#dc3545' : '#ffc107') ?>; color: <?= $b['status_class'] == 'warning' ? '#000' : '#fff' ?>;">
                                    <?= $b['status_text'] ?>
                                </span>
                                <?php if ($b['diverifikasi_oleh_nama'] ?? false): ?>
                                <br><small class="text-muted">oleh <?= htmlspecialchars($b['diverifikasi_oleh_nama']) ?> (<?= $b['diverifikasi_role'] ?>)<br><?= $b['diverifikasi_at_formatted'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($b['status_verifikasi'] == 'pending'): ?>
                                    <button class="action-btn view" onclick="openVerifyModal(<?= $b['booking_id'] ?>, '<?= htmlspecialchars(addslashes($b['full_name'] ?: $b['first_name'])) ?>', '<?= htmlspecialchars(addslashes($b['unit_display'])) ?>')" title="Verifikasi Booking">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button class="action-btn view" onclick="viewDetail(<?= $b['booking_id'] ?>)" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <a href="https://wa.me/<?= $b['phone'] ?>?text=Halo%20<?= urlencode($b['full_name'] ?: $b['first_name']) ?>%2C%20saya%20dari%20<?= urlencode($developer_name) ?>%20ingin%20mengkonfirmasi%20booking%20unit%20Anda." target="_blank" class="action-btn whatsapp" title="Chat Customer">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <a href="?page=1&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&marketing_id=<?= $marketing_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fas fa-angle-double-left"></i></a>
            <a href="?page=<?= max(1, $page-1) ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&marketing_id=<?= $marketing_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fas fa-angle-left"></i></a>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a href="?page=<?= $i ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&marketing_id=<?= $marketing_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            
            <a href="?page=<?= min($total_pages, $page+1) ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&marketing_id=<?= $marketing_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="fas fa-angle-right"></i></a>
            <a href="?page=<?= $total_pages ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&marketing_id=<?= $marketing_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="fas fa-angle-double-right"></i></a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <p>© <?= date('Y') ?> TaufikMarie.com - <?= $page_title ?> v1.0</p>
        <p>Mode Split 50:50 - Verifikasi oleh Manager Developer & Finance</p>
    </div>
    
</div>

<!-- Modal Verifikasi -->
<div class="modal" id="verifyModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-check-circle"></i> Verifikasi Booking</h2>
            <button class="modal-close" onclick="closeModal('verifyModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="action" value="verify_booking">
                <input type="hidden" name="booking_id" id="modal_booking_id">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Customer</label>
                    <p class="form-control" readonly id="modal_customer" style="background: #f5f5f5;"></p>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-home"></i> Unit</label>
                    <p class="form-control" readonly id="modal_unit" style="background: #f5f5f5;"></p>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Status Verifikasi</label>
                    <div style="display: flex; gap: 20px; margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="status" value="diterima" checked> 
                            <span style="color: #28a745; font-weight: 600;"><i class="fas fa-check-circle"></i> Terima</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="status" value="ditolak"> 
                            <span style="color: #dc3545; font-weight: 600;"><i class="fas fa-times-circle"></i> Tolak</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> Catatan Verifikasi</label>
                    <textarea name="notes" class="form-control" rows="4" placeholder="Masukkan catatan verifikasi..."></textarea>
                </div>
                
                <div class="alert info" id="cancel_warning" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    Jika ditolak, unit akan dikembalikan ke status AVAILABLE dan status lead menjadi Batal.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('verifyModal')">Batal</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Simpan Verifikasi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detail -->
<div class="modal" id="detailModal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2><i class="fas fa-info-circle"></i> Detail Booking</h2>
            <button class="modal-close" onclick="closeModal('detailModal')">&times;</button>
        </div>
        <div class="modal-body" id="detail_content" style="max-height: 70vh; overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">
                <div class="spinner"></div>
                <p style="margin-top: 20px; color: #7A8A84;">Memuat data...</p>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/index.js"></script>
<script>
// Update datetime
document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
});

function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', options);
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { hour12: false });
}

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = '';
}

// Open verify modal
function openVerifyModal(bookingId, customer, unit) {
    document.getElementById('modal_booking_id').value = bookingId;
    document.getElementById('modal_customer').innerText = customer;
    document.getElementById('modal_unit').innerText = unit;
    
    document.querySelectorAll('input[name="status"]')[0].checked = true;
    document.querySelector('textarea[name="notes"]').value = '';
    document.getElementById('cancel_warning').style.display = 'none';
    
    openModal('verifyModal');
}

// Show warning if reject
document.querySelectorAll('input[name="status"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('cancel_warning').style.display = this.value === 'ditolak' ? 'block' : 'none';
    });
});

// View detail
function viewDetail(bookingId) {
    openModal('detailModal');
    
    fetch('api/booking_process.php?action=detail&booking_id=' + bookingId + '&key=<?= API_KEY ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                displayDetail(data.data);
            } else {
                document.getElementById('detail_content').innerHTML = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
            }
        })
        .catch(() => {
            document.getElementById('detail_content').innerHTML = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Gagal memuat data</div>';
        });
}

function displayDetail(data) {
    let html = `
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-user"></i> Data Customer</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-user"></i> Nama</div>
                    <div class="detail-item-value">${data.full_name || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-phone"></i> WhatsApp</div>
                    <div class="detail-item-value"><a href="https://wa.me/${data.phone}" target="_blank">${data.phone}</a></div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-envelope"></i> Email</div>
                    <div class="detail-item-value">${data.email || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-map-marker-alt"></i> Lokasi</div>
                    <div class="detail-item-value">${data.location_display || data.location_key || '-'}</div>
                </div>
            </div>
        </div>
        
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-building"></i> Data Unit</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-layer-group"></i> Cluster</div>
                    <div class="detail-item-value">${data.nama_cluster || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-cubes"></i> Block</div>
                    <div class="detail-item-value">${data.nama_block || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-hashtag"></i> No. Unit</div>
                    <div class="detail-item-value">${data.nomor_unit || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-ruler"></i> Tipe</div>
                    <div class="detail-item-value">${data.tipe_unit || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-tag"></i> Program</div>
                    <div class="detail-item-value">${data.program || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-money-bill"></i> Harga</div>
                    <div class="detail-item-value">${data.harga_formatted || '-'}</div>
                </div>
            </div>
        </div>
        
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-hand-holding-usd"></i> Data Booking</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-user-tie"></i> Marketing</div>
                    <div class="detail-item-value">${data.marketing_name || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-calendar"></i> Tanggal Booking</div>
                    <div class="detail-item-value">${data.booking_date ? new Date(data.booking_date).toLocaleString('id-ID') : '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-credit-card"></i> Metode</div>
                    <div class="detail-item-value">${data.metode_pembayaran || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-money-bill"></i> Fee Booking</div>
                    <div class="detail-item-value">${data.harga_booking_formatted || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-check-circle"></i> Status</div>
                    <div class="detail-item-value"><span class="status-badge" style="background: ${data.status_class == 'success' ? '#28a745' : (data.status_class == 'danger' ? '#dc3545' : '#ffc107')}; color: ${data.status_class == 'warning' ? '#000' : '#fff'};">${data.status_text || 'Pending'}</span></div>
                </div>
            </div>
        </div>
        
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-coins"></i> Komisi</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-percent"></i> Komisi Eksternal</div>
                    <div class="detail-item-value">${data.komisi_eksternal_formatted || '3%'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label"><i class="fas fa-coins"></i> Komisi Internal</div>
                    <div class="detail-item-value">${data.komisi_internal_formatted || 'Rp 0'}</div>
                </div>
            </div>
        </div>
        
        <div class="detail-section">
            <div class="detail-section-title"><i class="fas fa-history"></i> Riwayat Verifikasi</div>
            <div style="background: white; border-radius: 12px; padding: 12px;">
    `;
    
    if (data.catatan_verifikasi) {
        let notes = data.catatan_verifikasi.split('\n');
        notes.forEach(note => {
            if (note.trim()) {
                html += `<div style="padding: 8px; border-bottom: 1px solid #E0DAD3; font-size: 13px;">${note}</div>`;
            }
        });
    } else {
        html += `<div style="text-align: center; padding: 20px; color: #7A8A84;">Belum ada riwayat verifikasi</div>`;
    }
    
    html += `</div></div>`;
    document.getElementById('detail_content').innerHTML = html;
}

// Close modal on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
        document.body.style.overflow = '';
    }
}
</script>

<!-- FOOTER -->
<?php include 'includes/footer.php'; ?>