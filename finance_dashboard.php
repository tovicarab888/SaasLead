<?php
/**
 * FINANCE_DASHBOARD.PHP - LEADENGINE
 * Version: 1.0.0 - Dashboard untuk Finance
 * MOBILE FIRST UI - STATISTIK KOMISI & REKENING
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek session finance
if (!isFinance()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$finance_id = $_SESSION['user_id'];
$finance_name = $_SESSION['nama_lengkap'] ?? 'Finance';
$developer_id = $_SESSION['developer_id'] ?? 0; // ID developer yang dikelola

if ($developer_id <= 0) {
    die("Error: Developer ID tidak ditemukan");
}

// Ambil data developer
$stmt = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
$stmt->execute([$developer_id]);
$developer_name = $stmt->fetchColumn() ?: 'Developer';

// ========== STATISTIK KOMISI ==========
// Total komisi pending
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_pending,
        SUM(komisi_final) as total_nominal_pending
    FROM komisi_logs 
    WHERE developer_id = ? AND status = 'pending'
");
$stmt->execute([$developer_id]);
$pending = $stmt->fetch();

// Total komisi cair bulan ini
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_cair_bulan,
        SUM(komisi_final) as total_nominal_cair_bulan
    FROM komisi_logs 
    WHERE developer_id = ? AND status = 'cair' 
    AND MONTH(tanggal_cair) = MONTH(CURDATE()) 
    AND YEAR(tanggal_cair) = YEAR(CURDATE())
");
$stmt->execute([$developer_id]);
$cair_bulan = $stmt->fetch();

// Total komisi cair semua
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_cair_all,
        SUM(komisi_final) as total_nominal_cair_all
    FROM komisi_logs 
    WHERE developer_id = ? AND status = 'cair'
");
$stmt->execute([$developer_id]);
$cair_all = $stmt->fetch();

// ========== STATISTIK REKENING ==========
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM banks 
    WHERE developer_id = ? AND is_active = 1
");
$stmt->execute([$developer_id]);
$total_rekening = $stmt->fetchColumn();

// ========== STATISTIK MARKETING ==========
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_marketing,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as aktif
    FROM marketing_team 
    WHERE developer_id = ?
");
$stmt->execute([$developer_id]);
$marketing_stats = $stmt->fetch();

// ========== 5 KOMISI PENDING TERBARU ==========
$stmt = $conn->prepare("
    SELECT k.*, 
           l.first_name, l.last_name, l.phone as customer_phone,
           m.nama_lengkap as marketing_name,
           u.nomor_unit, u.tipe_unit
    FROM komisi_logs k
    LEFT JOIN leads l ON k.lead_id = l.id
    LEFT JOIN marketing_team m ON k.marketing_id = m.id
    LEFT JOIN units u ON k.unit_id = u.id
    WHERE k.developer_id = ? AND k.status = 'pending'
    ORDER BY k.created_at DESC
    LIMIT 5
");
$stmt->execute([$developer_id]);
$recent_pending = $stmt->fetchAll();

$page_title = 'Dashboard Finance';
$page_subtitle = $developer_name;
$page_icon = 'fas fa-chart-pie';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
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

.main-content {
    width: 100%;
    min-height: 100vh;
    padding: 12px;
    margin-left: 0 !important;
}

.top-bar {
    background: var(--surface);
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 16px;
    border-left: 6px solid var(--finance);
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
    background: rgba(42,157,143,0.1);
    color: var(--finance);
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

/* ===== STATS GRID ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stats-card {
    background: white;
    border-radius: 20px;
    padding: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    border-left: 4px solid;
}

.stats-card.pending {
    border-left-color: var(--warning);
}

.stats-card.success {
    border-left-color: var(--success);
}

.stats-card.info {
    border-left-color: var(--info);
}

.stats-card.primary {
    border-left-color: var(--primary);
}

.stats-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.stats-title {
    font-size: 13px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
}

.stats-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.stats-icon.pending {
    background: rgba(233,196,106,0.15);
    color: #B87C00;
}

.stats-icon.success {
    background: rgba(42,157,143,0.15);
    color: var(--success);
}

.stats-icon.info {
    background: rgba(74,144,226,0.15);
    color: var(--info);
}

.stats-value {
    font-size: 24px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
    margin-bottom: 4px;
}

.stats-nominal {
    font-size: 16px;
    font-weight: 700;
    color: var(--secondary);
}

.stats-label {
    font-size: 12px;
    color: var(--text-muted);
}

/* ===== SECTION TITLE ===== */
.section-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    margin: 20px 0 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: var(--finance);
}

.section-title a {
    margin-left: auto;
    font-size: 12px;
    color: var(--secondary);
    text-decoration: none;
}

/* ===== HORIZONTAL SCROLL CARDS ===== */
.horizontal-scroll {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    padding: 8px 0 20px 0;
    margin-bottom: 16px;
}

.horizontal-scroll::-webkit-scrollbar {
    height: 4px;
}

.horizontal-scroll::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.horizontal-scroll::-webkit-scrollbar-thumb {
    background: var(--finance);
    border-radius: 10px;
}

.komisi-card {
    flex: 0 0 280px;
    background: white;
    border-radius: 20px;
    padding: 16px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    border-left: 6px solid var(--warning);
}

.komisi-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.komisi-marketing {
    font-weight: 700;
    color: var(--primary);
    font-size: 15px;
}

.komisi-status {
    font-size: 10px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 20px;
    background: var(--warning);
    color: #1A2A24;
}

.komisi-customer {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 8px;
}

.komisi-detail {
    font-size: 12px;
    color: var(--text-light);
    margin: 8px 0;
    line-height: 1.6;
}

.komisi-detail i {
    color: var(--finance);
    width: 18px;
    margin-right: 4px;
}

.komisi-nominal {
    font-size: 18px;
    font-weight: 800;
    color: var(--secondary);
    text-align: right;
    margin: 12px 0 8px;
}

.komisi-tanggal {
    font-size: 10px;
    color: var(--text-muted);
    text-align: right;
}

.btn-action {
    display: block;
    background: var(--finance);
    color: white;
    text-align: center;
    padding: 10px;
    border-radius: 40px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    margin-top: 12px;
}

.btn-action:hover {
    background: var(--primary-light);
}

/* ===== QUICK ACTIONS ===== */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.quick-action-item {
    background: white;
    border-radius: 16px;
    padding: 16px;
    text-align: center;
    text-decoration: none;
    border: 1px solid var(--border);
    transition: all 0.2s;
}

.quick-action-item:active {
    transform: scale(0.98);
    background: var(--primary-soft);
}

.quick-action-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--finance), #40BEB0);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
    margin: 0 auto 10px;
}

.quick-action-item span {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    display: block;
}

.quick-action-item small {
    font-size: 10px;
    color: var(--text-muted);
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 20px;
    width: 100%;
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

/* ===== TABLET & DESKTOP ===== */
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
        grid-template-columns: repeat(4, 1fr);
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .horizontal-scroll {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        overflow-x: visible;
    }
    
    .komisi-card {
        flex: none;
        width: auto;
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
                <span><?= htmlspecialchars($page_subtitle) ?></span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <!-- STATS GRID -->
    <div class="stats-grid">
        <!-- Pending -->
        <div class="stats-card pending">
            <div class="stats-header">
                <span class="stats-title">Pending</span>
                <div class="stats-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stats-value"><?= number_format($pending['total_pending'] ?? 0) ?></div>
            <div class="stats-nominal">Rp <?= number_format($pending['total_nominal_pending'] ?? 0, 0, ',', '.') ?></div>
            <div class="stats-label">Menunggu pencairan</div>
        </div>
        
        <!-- Cair Bulan Ini -->
        <div class="stats-card success">
            <div class="stats-header">
                <span class="stats-title">Cair Bulan Ini</span>
                <div class="stats-icon success">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
            <div class="stats-value"><?= number_format($cair_bulan['total_cair_bulan'] ?? 0) ?></div>
            <div class="stats-nominal">Rp <?= number_format($cair_bulan['total_nominal_cair_bulan'] ?? 0, 0, ',', '.') ?></div>
            <div class="stats-label"><?= date('F Y') ?></div>
        </div>
        
        <!-- Total Cair -->
        <div class="stats-card info">
            <div class="stats-header">
                <span class="stats-title">Total Cair</span>
                <div class="stats-icon info">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
            <div class="stats-value"><?= number_format($cair_all['total_cair_all'] ?? 0) ?></div>
            <div class="stats-nominal">Rp <?= number_format($cair_all['total_nominal_cair_all'] ?? 0, 0, ',', '.') ?></div>
            <div class="stats-label">Semua waktu</div>
        </div>
        
        <!-- Rekening & Marketing -->
        <div class="stats-card primary">
            <div class="stats-header">
                <span class="stats-title">Rekening</span>
                <div class="stats-icon primary">
                    <i class="fas fa-university"></i>
                </div>
            </div>
            <div class="stats-value"><?= $total_rekening ?></div>
            <div class="stats-nominal">Marketing: <?= $marketing_stats['aktif'] ?? 0 ?>/<?= $marketing_stats['total_marketing'] ?? 0 ?></div>
            <div class="stats-label">Aktif/total</div>
        </div>
    </div>
    
    <!-- QUICK ACTIONS -->
    <div class="section-title">
        <i class="fas fa-bolt"></i> Aksi Cepat
    </div>
    
    <div class="quick-actions-grid">
        <a href="finance_komisi.php?status=pending" class="quick-action-item">
            <div class="quick-action-icon">
                <i class="fas fa-clock"></i>
            </div>
            <span>Komisi Pending</span>
            <small><?= number_format($pending['total_pending'] ?? 0) ?> menunggu</small>
        </a>
        
        <a href="finance_komisi.php?status=cair" class="quick-action-item">
            <div class="quick-action-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <span>Riwayat Cair</span>
            <small><?= number_format($cair_all['total_cair_all'] ?? 0) ?> transaksi</small>
        </a>
        
        <a href="finance_rekening.php" class="quick-action-item">
            <div class="quick-action-icon">
                <i class="fas fa-university"></i>
            </div>
            <span>Rekening Bank</span>
            <small><?= $total_rekening ?> terdaftar</small>
        </a>
        
        <a href="finance_laporan.php" class="quick-action-item">
            <div class="quick-action-icon">
                <i class="fas fa-file-invoice"></i>
            </div>
            <span>Laporan</span>
            <small>Export PDF/Excel</small>
        </a>
    </div>
    
    <!-- KOMISI PENDING TERBARU -->
    <div class="section-title">
        <i class="fas fa-clock"></i> Komisi Pending Terbaru
        <a href="finance_komisi.php?status=pending">Lihat Semua <i class="fas fa-arrow-right"></i></a>
    </div>
    
    <?php if (empty($recent_pending)): ?>
    <div class="empty-state">
        <i class="fas fa-check-circle"></i>
        <p>Tidak ada komisi pending</p>
    </div>
    <?php else: ?>
    <div class="horizontal-scroll">
        <?php foreach ($recent_pending as $k): 
            $full_name = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
        ?>
        <div class="komisi-card">
            <div class="komisi-header">
                <span class="komisi-marketing"><?= htmlspecialchars($k['marketing_name'] ?? 'Unknown') ?></span>
                <span class="komisi-status">Pending</span>
            </div>
            
            <div class="komisi-customer">
                <i class="fas fa-user"></i> <?= htmlspecialchars($full_name ?: 'Lead #' . $k['lead_id']) ?>
            </div>
            
            <div class="komisi-detail">
                <div><i class="fas fa-home"></i> Unit: <?= htmlspecialchars($k['nomor_unit'] ?? '-') ?> (<?= htmlspecialchars($k['tipe_unit'] ?? '-') ?>)</div>
                <div><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($k['customer_phone'] ?? '-') ?></div>
                <div><i class="fas fa-tag"></i> Tipe: <?= $k['assigned_type'] == 'internal' ? 'Internal' : 'External' ?></div>
            </div>
            
            <div class="komisi-nominal">
                Rp <?= number_format($k['komisi_final'], 0, ',', '.') ?>
            </div>
            
            <div class="komisi-tanggal">
                <i class="far fa-clock"></i> <?= date('d/m/Y H:i', strtotime($k['created_at'])) ?>
            </div>
            
            <a href="finance_konfirmasi.php?id=<?= $k['id'] ?>" class="btn-action">
                <i class="fas fa-check-circle"></i> Proses Cair
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Finance Dashboard v1.0</p>
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