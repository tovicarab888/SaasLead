<?php
/**
 * FINANCE_DEVELOPER_DASHBOARD.PHP - Dashboard untuk Finance Developer (ID 9)
 * Version: 2.0.0 - UI SUPER KEREN (Mempertahankan UI Referensi)
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// ========== CHECK AUTH ==========
if (!checkAuth()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Hanya finance developer yang bisa akses
if (!isFinance()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Finance Developer.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$developer_id = $_SESSION['developer_id'] ?? 0;
if ($developer_id <= 0) {
    die("Error: Developer ID tidak ditemukan");
}

// ========== STATISTIK KOMISI INTERNAL ==========
$stats_sql = "
    SELECT 
        COUNT(DISTINCT kl.id) as total_komisi,
        COUNT(DISTINCT CASE WHEN kl.status = 'pending' THEN kl.id END) as pending_komisi,
        COUNT(DISTINCT CASE WHEN kl.status = 'cair' THEN kl.id END) as cair_komisi,
        COALESCE(SUM(CASE WHEN kl.status = 'pending' THEN kl.komisi_final ELSE 0 END), 0) as total_pending,
        COALESCE(SUM(CASE WHEN kl.status = 'cair' THEN kl.komisi_final ELSE 0 END), 0) as total_cair,
        COUNT(DISTINCT kl.marketing_id) as total_marketing
    FROM komisi_logs kl
    WHERE kl.assigned_type = 'internal' AND kl.developer_id = ?
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute([$developer_id]);
$stats = $stats_stmt->fetch();

// ========== STATISTIK UNIT TERJUAL ==========
$unit_sql = "
    SELECT 
        COUNT(*) as total_terjual,
        COALESCE(SUM(harga), 0) as total_nilai,
        COUNT(DISTINCT CASE WHEN MONTH(sold_at) = MONTH(NOW()) THEN id END) as bulan_ini
    FROM units
    WHERE status = 'SOLD' AND developer_id = ?
";
$unit_stmt = $conn->prepare($unit_sql);
$unit_stmt->execute([$developer_id]);
$unit_stats = $unit_stmt->fetch();

// ========== KOMISI PENDING ==========
$pending_sql = "
    SELECT 
        kl.*,
        l.first_name,
        l.last_name,
        l.phone as customer_phone,
        l.location_key,
        loc.display_name as location_name,
        m.nama_lengkap as marketing_name,
        m.phone as marketing_phone,
        un.nomor_unit,
        un.tipe_unit,
        un.harga
    FROM komisi_logs kl
    LEFT JOIN leads l ON kl.lead_id = l.id
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    LEFT JOIN marketing_team m ON kl.marketing_id = m.id
    LEFT JOIN units un ON kl.unit_id = un.id
    WHERE kl.assigned_type = 'internal' AND kl.developer_id = ? AND kl.status = 'pending'
    ORDER BY kl.created_at DESC
    LIMIT 10
";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->execute([$developer_id]);
$pending_komisi = $pending_stmt->fetchAll();

// ========== REKENING MARKETING BELUM VERIFIKASI ==========
$rekening_sql = "
    SELECT 
        m.*,
        COUNT(kl.id) as total_komisi
    FROM marketing_team m
    LEFT JOIN komisi_logs kl ON m.id = kl.marketing_id AND kl.assigned_type = 'internal' AND kl.status = 'pending'
    WHERE m.developer_id = ? AND m.rekening_verified = 0 
    AND m.nomor_rekening IS NOT NULL AND m.nomor_rekening != ''
    GROUP BY m.id
    ORDER BY m.updated_at DESC
    LIMIT 10
";
$rekening_stmt = $conn->prepare($rekening_sql);
$rekening_stmt->execute([$developer_id]);
$rekening_unverified = $rekening_stmt->fetchAll();

// ========== TOP MARKETING INTERNAL ==========
$top_marketing_sql = "
    SELECT 
        m.id,
        m.nama_lengkap,
        m.phone,
        COUNT(kl.id) as total_komisi,
        SUM(kl.komisi_final) as total_nominal
    FROM komisi_logs kl
    JOIN marketing_team m ON kl.marketing_id = m.id
    WHERE kl.assigned_type = 'internal' AND kl.developer_id = ? AND kl.status = 'cair'
    GROUP BY m.id
    ORDER BY total_nominal DESC
    LIMIT 5
";
$top_marketing_stmt = $conn->prepare($top_marketing_sql);
$top_marketing_stmt->execute([$developer_id]);
$top_marketing = $top_marketing_stmt->fetchAll();

// ========== LAPORAN BULANAN ==========
$monthly_sql = "
    SELECT 
        DATE_FORMAT(kl.created_at, '%Y-%m') as bulan,
        COUNT(*) as total_komisi,
        SUM(kl.komisi_final) as total_nominal,
        COUNT(DISTINCT kl.marketing_id) as marketing_count
    FROM komisi_logs kl
    WHERE kl.assigned_type = 'internal' AND kl.developer_id = ?
    AND kl.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(kl.created_at, '%Y-%m')
    ORDER BY bulan DESC
";
$monthly_stmt = $conn->prepare($monthly_sql);
$monthly_stmt->execute([$developer_id]);
$monthly = $monthly_stmt->fetchAll();

// ========== SET VARIABLES ==========
$page_title = 'Finance Developer Dashboard';
$page_subtitle = 'Kelola Komisi Marketing Internal';
$page_icon = 'fas fa-coins';

include 'includes/header.php';
?>

<!-- SIDEBAR -->
<?php include 'includes/sidebar.php'; ?>

<style>
/* ===== MOBILE FIRST VARIABLES ===== */
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
    --finance: #2A9D8F;
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

/* ===== TOP BAR - MOBILE FIRST ===== */
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
    flex-shrink: 0;
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

/* ===== STATS CARD - HORIZONTAL SCROLL ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .stats-grid {
        display: flex;
        overflow-x: auto;
        gap: 12px;
        padding: 4px 0 16px 0;
        margin-bottom: 8px;
        -webkit-overflow-scrolling: touch;
    }
    
    .stats-grid .stat-card {
        flex: 0 0 140px;
    }
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 14px;
    border-left: 4px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.stat-icon {
    font-size: 20px;
    color: var(--secondary);
    margin-bottom: 8px;
}

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

.stat-nominal {
    font-size: 12px;
    font-weight: 600;
    color: var(--success);
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
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

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
    min-width: 800px;
}

th {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 12px;
    text-transform: uppercase;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
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
    text-align: center;
    color: white;
}

.status-badge.pending {
    background: var(--warning);
    color: #1A2A24;
}

.status-badge.cair,
.status-badge.active {
    background: var(--success);
}

.status-badge.batal,
.status-badge.inactive {
    background: var(--danger);
}

/* ===== ACTION BUTTONS ===== */
.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
    align-items: center;
    flex-wrap: nowrap;
}

.action-btn {
    width: 32px;
    height: 32px;
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
    text-decoration: none;
    flex-shrink: 0;
}

.action-btn.edit {
    background: #fff8e1;
    color: #B87C00;
    border-color: #B87C00;
}

.action-btn.edit:hover {
    background: #B87C00;
    color: white;
}

.action-btn.view {
    background: #e8f0fe;
    color: #1976d2;
    border-color: #1976d2;
}

.action-btn.view:hover {
    background: #1976d2;
    color: white;
}

.action-btn i {
    font-size: 14px;
}

/* ===== TWO COLUMN GRID ===== */
.two-column-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .two-column-grid {
        grid-template-columns: 1fr;
    }
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 16px;
}

.empty-state i {
    font-size: 48px;
    color: #E0DAD3;
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

/* ===== TABLET & DESKTOP UPGRADE ===== */
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
    
    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card" style="border-left-color: var(--warning);">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Pending Komisi</div>
            <div class="stat-value"><?= number_format($stats['pending_komisi'] ?? 0) ?></div>
            <div class="stat-nominal">Rp <?= number_format($stats['total_pending'] ?? 0, 0, ',', '.') ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--success);">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Sudah Cair</div>
            <div class="stat-value"><?= number_format($stats['cair_komisi'] ?? 0) ?></div>
            <div class="stat-nominal">Rp <?= number_format($stats['total_cair'] ?? 0, 0, ',', '.') ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--primary);">
            <div class="stat-icon"><i class="fas fa-home"></i></div>
            <div class="stat-label">Unit Terjual</div>
            <div class="stat-value"><?= number_format($unit_stats['total_terjual'] ?? 0) ?></div>
            <div class="stat-nominal">Bulan ini: <?= number_format($unit_stats['bulan_ini'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--info);">
            <div class="stat-icon"><i class="fas fa-building"></i></div>
            <div class="stat-label">Nilai Penjualan</div>
            <div class="stat-value">Rp <?= number_format($unit_stats['total_nilai'] ?? 0, 0, ',', '.') ?></div>
        </div>
    </div>
    
    <div class="stats-grid" style="margin-top: -20px;">
        <div class="stat-card" style="border-left-color: var(--secondary);">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Marketing Aktif</div>
            <div class="stat-value"><?= number_format($stats['total_marketing'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--primary); grid-column: span 3;">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-label">Total Komisi</div>
            <div class="stat-value">Rp <?= number_format(($stats['total_pending'] ?? 0) + ($stats['total_cair'] ?? 0), 0, ',', '.') ?></div>
        </div>
    </div>
    
    <!-- TWO COLUMN GRID -->
    <div class="two-column-grid">
        
        <!-- PENDING KOMISI -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-clock" style="color: var(--warning);"></i> Komisi Pending</h3>
                <a href="finance_developer_verifikasi.php" class="table-badge" style="text-decoration: none;">Lihat Semua <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <?php if (empty($pending_komisi)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>Tidak ada komisi pending</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Marketing</th>
                            <th>Customer</th>
                            <th>Komisi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_komisi as $komisi): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($komisi['marketing_name'] ?? '-') ?></strong><br>
                                <small style="color: var(--text-muted);"><?= $komisi['marketing_phone'] ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($komisi['first_name'] ?? '') ?><br>
                                <small style="color: var(--text-muted);"><?= $komisi['customer_phone'] ?></small>
                            </td>
                            <td><strong style="color: var(--warning);">Rp <?= number_format($komisi['komisi_final'], 0, ',', '.') ?></strong></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="finance_developer_verifikasi.php?id=<?= $komisi['id'] ?>" class="action-btn edit" title="Verifikasi">
                                        <i class="fas fa-check-circle"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- REKENING UNVERIFIED -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-university" style="color: var(--warning);"></i> Rekening Belum Verifikasi</h3>
                <a href="finance_developer_rekening.php" class="table-badge" style="text-decoration: none;">Lihat Semua <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <?php if (empty($rekening_unverified)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>Semua rekening sudah terverifikasi</p>
            </div>
            <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($rekening_unverified as $rek): ?>
                <div style="background: var(--primary-soft); border-radius: 16px; padding: 16px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong><?= htmlspecialchars($rek['nama_lengkap']) ?></strong><br>
                        <small style="color: var(--text-muted);"><?= $rek['phone'] ?></small><br>
                        <small><?= $rek['nama_bank_rekening'] ?? '-' ?> - <?= $rek['nomor_rekening'] ?? '-' ?></small>
                    </div>
                    <div>
                        <a href="finance_developer_rekening.php?id=<?= $rek['id'] ?>" class="action-btn edit">
                            <i class="fas fa-check-circle"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
    </div>
    
    <!-- TWO COLUMN GRID - LAPORAN BULANAN & TOP MARKETING -->
    <div class="two-column-grid">
        
        <!-- LAPORAN BULANAN -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-calendar-alt"></i> Laporan 6 Bulan Terakhir</h3>
                <a href="finance_developer_laporan.php" class="table-badge" style="text-decoration: none;">Lihat Laporan <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <?php if (empty($monthly)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <p>Belum ada data laporan</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th>Jumlah</th>
                            <th>Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly as $m): ?>
                        <tr>
                            <td><strong><?= date('F Y', strtotime($m['bulan'] . '-01')) ?></strong></td>
                            <td><?= $m['total_komisi'] ?> komisi</td>
                            <td><strong style="color: var(--success);">Rp <?= number_format($m['total_nominal'], 0, ',', '.') ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- TOP MARKETING -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-crown" style="color: #FFD700;"></i> Top Marketing Internal</h3>
            </div>
            
            <?php if (empty($top_marketing)): ?>
            <div class="empty-state">
                <i class="fas fa-trophy"></i>
                <p>Belum ada data marketing</p>
            </div>
            <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($top_marketing as $index => $top): ?>
                <div style="display: flex; align-items: center; gap: 15px; background: var(--primary-soft); padding: 15px; border-radius: 16px;">
                    <div style="width: 40px; height: 40px; background: <?= $index == 0 ? '#FFD700' : ($index == 1 ? '#C0C0C0' : '#CD7F32'); ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: <?= $index == 0 ? '#1A2A24' : 'white'; ?>;">
                        <?= $index + 1 ?>
                    </div>
                    <div style="flex: 1;">
                        <strong><?= htmlspecialchars($top['nama_lengkap']) ?></strong><br>
                        <small style="color: var(--text-muted);"><?= $top['phone'] ?></small>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 700; color: var(--success);">Rp <?= number_format($top['total_nominal'], 0, ',', '.') ?></div>
                        <small style="color: var(--text-muted);"><?= $top['total_komisi'] ?> komisi</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Finance Developer Dashboard v2.0</p>
    </div>
    
</div>

<script>
function updateDateTime() {
    const now = new Date();
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { 
        hour12: false 
    });
}
setInterval(updateDateTime, 1000);
updateDateTime();
</script>

<?php include 'includes/footer.php'; ?>