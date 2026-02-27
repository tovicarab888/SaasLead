<?php
/**
 * MARKETING_EXTERNAL_DASHBOARD.PHP - Dashboard Khusus Marketing External
 * Version: 1.0.0 - UI SAMA PERSIS DENGAN DASHBOARD LAIN
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

// Cek apakah user adalah marketing external
$currentRole = getCurrentRole();
if ($currentRole !== 'marketing_external') {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Marketing External.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$user_id = $_SESSION['user_id'];
$external_id = $_SESSION['external_id'] ?? 0;

// ========== AMBIL STATISTIK ==========
// Total leads yang ditugaskan ke marketing external ini
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_leads,
        SUM(CASE WHEN status IN ('Baru', 'Follow Up') THEN 1 ELSE 0 END) as need_followup,
        SUM(CASE WHEN status = 'Booking' THEN 1 ELSE 0 END) as booking,
        SUM(CASE WHEN status IN ('Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun') THEN 1 ELSE 0 END) as deal
    FROM leads 
    WHERE assigned_marketing_team_id = ? AND assigned_type = 'external' 
    AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// ========== AMBIL LEADS TERBARU ==========
$recent_leads = $conn->prepare("
    SELECT 
        l.*,
        loc.display_name as location_display,
        loc.icon
    FROM leads l
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    WHERE l.assigned_marketing_team_id = ? AND l.assigned_type = 'external'
    AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
    ORDER BY l.created_at DESC
    LIMIT 10
");
$recent_leads->execute([$user_id]);
$recent_leads = $recent_leads->fetchAll();

// ========== AMBIL KOMISI TERBARU ==========
$recent_komisi = $conn->prepare("
    SELECT 
        k.*,
        l.first_name,
        l.last_name,
        l.location_key,
        un.nomor_unit,
        un.tipe_unit
    FROM komisi_logs k
    LEFT JOIN leads l ON k.lead_id = l.id
    LEFT JOIN units un ON k.unit_id = un.id
    WHERE k.marketing_id = ?
    ORDER BY k.created_at DESC
    LIMIT 5
");
$recent_komisi->execute([$user_id]);
$recent_komisi = $recent_komisi->fetchAll();

// ========== SET VARIABLES ==========
$page_title = 'Dashboard Marketing External';
$page_subtitle = 'Selamat datang, ' . htmlspecialchars($_SESSION['nama_lengkap']);
$page_icon = 'fas fa-user-tie';

include 'includes/header.php';
include 'includes/sidebar_marketing_external.php';
?>

<style>
/* ===== VARIABLES - SAMA PERSIS DENGAN DASHBOARD LAIN ===== */
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

/* ===== STATS CARDS ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

@media (min-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    border-left: 4px solid var(--secondary);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-soft);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.2rem;
    margin-bottom: 12px;
}

.stat-label {
    font-size: 0.8rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.stat-value {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

/* ===== SECTION CARD ===== */
.section-card {
    background: white;
    border-radius: 24px;
    padding: 20px;
    margin-bottom: 24px;
    border: 1px solid var(--border);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-soft);
}

.section-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.section-header h3 i {
    color: var(--secondary);
}

.section-link {
    color: var(--secondary);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.section-link:hover {
    text-decoration: underline;
}

/* ===== TABLE STYLES ===== */
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
    padding: 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 12px;
    text-transform: uppercase;
}

td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

tr:hover td {
    background: var(--primary-soft);
}

/* ===== STATUS BADGE ===== */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
}

.status-badge.sent {
    background: var(--primary-soft);
    color: var(--success);
}

.status-badge.pending {
    background: #FFF3E0;
    color: #F4A261;
}

.status-badge.failed {
    background: #FFE5E5;
    color: var(--danger);
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: #F8FAFC;
    border-radius: 16px;
}

.empty-state i {
    font-size: 48px;
    color: #E0DAD3;
    margin-bottom: 12px;
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
@media (min-width: 1024px) {
    .main-content {
        margin-left: 280px !important;
        padding: 24px !important;
        max-width: 1400px;
        margin-right: auto !important;
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
        gap: 20px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        font-size: 1.4rem;
    }
    
    .stat-value {
        font-size: 2rem;
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
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Leads</div>
            <div class="stat-value"><?= number_format($stats['total_leads'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Perlu Follow Up</div>
            <div class="stat-value"><?= number_format($stats['need_followup'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-bookmark"></i></div>
            <div class="stat-label">Booking</div>
            <div class="stat-value"><?= number_format($stats['booking'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
            <div class="stat-label">Deal</div>
            <div class="stat-value"><?= number_format($stats['deal'] ?? 0) ?></div>
        </div>
    </div>
    
    <!-- RECENT LEADS SECTION -->
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-history"></i> Leads Terbaru</h3>
            <a href="marketing_external_leads.php" class="section-link">
                Lihat Semua <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <?php if (empty($recent_leads)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Belum ada leads yang ditugaskan</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Kontak</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_leads as $lead): 
                        $full_name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($full_name ?: 'Tanpa Nama') ?></strong></td>
                        <td>
                            <a href="https://wa.me/<?= $lead['phone'] ?>" target="_blank" style="color: #25D366;">
                                <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($lead['phone']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($lead['location_display'] ?? $lead['location_key']) ?></td>
                        <td>
                            <span class="status-badge" style="background: <?php
                                switch($lead['status']) {
                                    case 'Baru': echo '#4A90E2'; break;
                                    case 'Follow Up': echo '#E9C46A'; break;
                                    case 'Survey': echo '#E9C46A'; break;
                                    case 'Booking': echo '#1B4A3C'; break;
                                    case 'Deal KPR': echo '#2A9D8F'; break;
                                    case 'Deal Tunai': echo '#FF9800'; break;
                                    default: echo '#757575';
                                }
                            ?>; color: white;">
                                <?= $lead['status'] ?>
                            </span>
                        </td>
                        <td><strong><?= $lead['lead_score'] ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($lead['created_at'])) ?></td>
                        <td>
                            <a href="marketing_external_lead_detail.php?id=<?= $lead['id'] ?>" class="btn-icon" style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); display: inline-flex; align-items: center; justify-content: center; color: var(--text); text-decoration: none;">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- RECENT KOMISI SECTION -->
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-money-bill-wave"></i> Riwayat Komisi</h3>
            <a href="marketing_external_komisi.php" class="section-link">
                Lihat Semua <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <?php if (empty($recent_komisi)): ?>
        <div class="empty-state">
            <i class="fas fa-coins"></i>
            <p>Belum ada riwayat komisi</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Unit</th>
                        <th>Komisi</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_komisi as $k): 
                        $lead_name = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($lead_name ?: 'Lead #' . $k['lead_id']) ?></td>
                        <td><?= htmlspecialchars($k['nomor_unit'] ?? '-') ?> (<?= $k['tipe_unit'] ?? '-' ?>)</td>
                        <td><strong style="color: var(--success);">Rp <?= number_format($k['komisi_final'], 0, ',', '.') ?></strong></td>
                        <td>
                            <span class="status-badge" style="background: <?= $k['status'] == 'cair' ? '#2A9D8F' : ($k['status'] == 'pending' ? '#E9C46A' : '#D64F3C') ?>; color: white;">
                                <?= ucfirst($k['status']) ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y', strtotime($k['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Marketing External Dashboard v1.0</p>
    </div>
    
</div>

<script>
function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', options);
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { hour12: false });
}
setInterval(updateDateTime, 1000);
updateDateTime();
</script>

<?php include 'includes/footer.php'; ?>