<?php
/**
 * MANAGER_TOP_PERFORMER.PHP - TAUFIKMARIE.COM
 * Version: 2.1.0 - FIXED HEADER TEXT COLOR (PUTIH)
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

if (!checkAuth()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

if (!isAdmin() && !isManager()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Admin dan Manager.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== AMBIL SEMUA DEVELOPER ==========
$developers = $conn->query("
    SELECT id, username, nama_lengkap 
    FROM users 
    WHERE role = 'developer' AND is_active = 1 
    ORDER BY nama_lengkap ASC
")->fetchAll();

// ========== FILTER ==========
$selected_developer = isset($_GET['developer']) ? (int)$_GET['developer'] : 0;
$period = $_GET['period'] ?? 'month'; // month, year, all
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

// ========== BUILD QUERY ==========
$deal_statuses = ['Deal KPR', 'Deal Tunai', 'Deal Bertahap 6 Bulan', 'Deal Bertahap 1 Tahun'];
$deal_placeholders = implode(',', array_fill(0, count($deal_statuses), '?'));

$date_condition = "";
if ($period === 'month') {
    $date_condition = "AND MONTH(l.created_at) = MONTH(CURDATE()) AND YEAR(l.created_at) = YEAR(CURDATE())";
} elseif ($period === 'year') {
    $date_condition = "AND YEAR(l.created_at) = YEAR(CURDATE())";
}

$developer_condition = "";
$params = $deal_statuses;
if ($selected_developer > 0) {
    $developer_condition = "AND m.developer_id = ?";
    $params[] = $selected_developer;
}

$sql = "
    SELECT 
        m.id,
        m.nama_lengkap,
        m.phone,
        m.username,
        u.nama_lengkap as developer_name,
        COUNT(l.id) as total_leads,
        SUM(CASE WHEN l.status IN ($deal_placeholders) THEN 1 ELSE 0 END) as total_deal,
        AVG(l.lead_score) as avg_score
    FROM marketing_team m
    LEFT JOIN users u ON m.developer_id = u.id
    LEFT JOIN leads l ON m.id = l.assigned_marketing_team_id 
        AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
        $date_condition
    WHERE m.is_active = 1
    $developer_condition
    GROUP BY m.id
    HAVING total_leads > 0
    ORDER BY total_deal DESC, avg_score DESC
    LIMIT ?
";

$params[] = $limit;
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$top_performers = $stmt->fetchAll();

// ========== STATISTIK ==========
$total_marketing = count($top_performers);
$total_deal_all = 0;
$total_leads_all = 0;
foreach ($top_performers as $p) {
    $total_deal_all += $p['total_deal'];
    $total_leads_all += $p['total_leads'];
}

$page_title = 'Top Performer Marketing';
$page_subtitle = 'Marketing dengan Performa Terbaik';
$page_icon = 'fas fa-crown';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== VARIABLES ===== */
:root {
    --primary: #1B4A3C;
    --primary-light: #2A5F4E;
    --secondary: #D64F3C;
    --secondary-light: #FF6B4A;
    --success: #2A9D8F;
    --info: #4A90E2;
    --bg: #F5F3F0;
    --surface: #FFFFFF;
    --text: #1A2A24;
    --text-light: #4A5A54;
    --text-muted: #7A8A84;
    --border: #E0DAD3;
    --primary-soft: #E7F3EF;
}

/* ===== MAIN LAYOUT ===== */
.main-content {
    margin-left: 280px;
    padding: 24px;
    background: var(--bg);
    min-height: 100vh;
}

/* ===== TOP BAR ===== */
.top-bar {
    background: var(--surface);
    border-radius: 20px;
    padding: 20px 24px;
    margin-bottom: 24px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.welcome-text {
    display: flex;
    align-items: center;
    gap: 16px;
}

.welcome-text i {
    width: 56px;
    height: 56px;
    background: rgba(214,79,60,0.1);
    color: var(--secondary);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.welcome-text h2 {
    font-size: 22px;
    font-weight: 800;
    color: var(--text);
    margin: 0;
    line-height: 1.3;
}

.welcome-text h2 span {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-muted);
    margin-top: 4px;
}

.datetime {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--bg);
    padding: 10px 20px;
    border-radius: 40px;
}

.date, .time {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--primary);
}

.time {
    background: var(--surface);
    padding: 6px 16px;
    border-radius: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

/* ===== HEADER PREMIUM - TEXT PUTIH ===== */
.top-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    color: white !important; /* DIPAKSA PUTIH */
    box-shadow: 0 10px 25px rgba(27,74,60,0.3);
}

.top-header h3,
.top-header p,
.top-header div,
.top-header span {
    color: white !important; /* SEMUA TEXT DI DALAM HEADER PUTIH */
}

.top-header i {
    color: rgba(255,255,255,0.9) !important;
}

/* ===== FILTER BAR ===== */
.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.filter-form {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-select {
    flex: 1;
    min-width: 150px;
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    background: white;
}

.filter-select:focus {
    border-color: var(--secondary);
    outline: none;
}

.filter-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}

.filter-btn.reset {
    background: var(--border);
    color: var(--text);
}

.filter-btn.reset:hover {
    background: var(--text-muted);
    color: white;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

/* ===== TOP PERFORMER CARDS - HORIZONTAL SCROLL ===== */
.cards-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    padding: 8px 4px 20px 4px;
    margin-bottom: 24px;
    scrollbar-width: thin;
    scrollbar-color: var(--secondary) var(--primary-soft);
    -webkit-overflow-scrolling: touch;
}

.cards-horizontal::-webkit-scrollbar {
    height: 6px;
}

.cards-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.cards-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.performer-card {
    flex: 0 0 280px;
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    position: relative;
    border-left: 6px solid;
    transition: transform 0.3s;
}

.performer-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.performer-card.rank-1 {
    border-left-color: var(--success);
    background: linear-gradient(135deg, #f8fffe, white);
}

.performer-card.rank-2 {
    border-left-color: var(--info);
}

.performer-card.rank-3 {
    border-left-color: var(--secondary);
}

.rank-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 16px;
    color: white;
}

.rank-1 .rank-badge {
    background: var(--success);
}

.rank-2 .rank-badge {
    background: var(--info);
}

.rank-3 .rank-badge {
    background: var(--secondary);
}

.performer-card h3 {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 5px 0;
    padding-right: 40px;
    color: var(--primary);
}

.performer-card .developer {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.performer-card .developer i {
    color: var(--secondary);
    font-size: 12px;
}

.stats-mini {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin: 15px 0;
    text-align: center;
}

.stat-mini-item {
    background: var(--primary-soft);
    border-radius: 12px;
    padding: 8px 4px;
}

.stat-mini-value {
    font-size: 18px;
    font-weight: 800;
    color: var(--primary);
}

.stat-mini-label {
    font-size: 9px;
    color: var(--text-muted);
    text-transform: uppercase;
}

.stat-mini-value.deal {
    color: var(--success);
}

.btn-chat {
    display: block;
    background: #25D366;
    color: white;
    padding: 10px;
    border-radius: 12px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
    transition: all 0.2s;
    margin-top: 10px;
}

.btn-chat:hover {
    background: #128C7E;
    transform: translateY(-2px);
}

/* ===== TABLE UNTUK DESKTOP ===== */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    overflow-x: auto;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.table-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-badge {
    background: var(--primary-soft);
    color: var(--primary);
    padding: 6px 16px;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 600;
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
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 2px solid var(--border);
}

td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

tr:hover td {
    background: rgba(231,243,239,0.3);
}

/* ===== FOOTER ===== */
.footer {
    text-align: center;
    margin-top: 40px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

/* ===== MOBILE RESPONSIVE ===== */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 12px !important;
    }
    
    .top-bar {
        flex-direction: column;
        align-items: flex-start;
        padding: 16px;
    }
    
    .datetime {
        width: 100%;
        justify-content: space-between;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .filter-select {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
    }
    
    .filter-btn {
        flex: 1;
    }
    
    /* Tampilkan horizontal scroll di mobile */
    .cards-horizontal {
        display: flex;
    }
    
    .performer-card {
        flex: 0 0 260px;
    }
}

/* ===== DESKTOP ===== */
@media (min-width: 769px) {
    .cards-horizontal {
        display: none;
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
    
    <!-- HEADER PREMIUM - TEXT PUTIH -->
    <div class="top-header">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 30px; display: flex; align-items: center; justify-content: center; font-size: 30px;">
                <i class="fas fa-crown" style="color: white !important;"></i>
            </div>
            <div>
                <h3 style="font-size: 20px; font-weight: 800; margin-bottom: 4px; color: white !important;">Top <?= $limit ?> Marketing</h3>
                <p style="opacity: 0.9; font-size: 14px; color: white !important;">Berdasarkan jumlah deal dan lead score</p>
            </div>
        </div>
    </div>
    
    <!-- FILTER BAR -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <select name="developer" class="filter-select">
                <option value="">Semua Developer</option>
                <?php foreach ($developers as $dev): ?>
                <option value="<?= $dev['id'] ?>" <?= $selected_developer == $dev['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dev['nama_lengkap']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="period" class="filter-select">
                <option value="month" <?= $period == 'month' ? 'selected' : '' ?>>Bulan Ini</option>
                <option value="year" <?= $period == 'year' ? 'selected' : '' ?>>Tahun Ini</option>
                <option value="all" <?= $period == 'all' ? 'selected' : '' ?>>Semua Waktu</option>
            </select>
            
            <select name="limit" class="filter-select">
                <option value="5" <?= $limit == 5 ? 'selected' : '' ?>>Top 5</option>
                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>Top 10</option>
                <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>Top 20</option>
            </select>
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-filter"></i> Tampilkan
                </button>
                <a href="?" class="filter-btn reset">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- ===== HORIZONTAL SCROLL CARDS UNTUK MOBILE ===== -->
    <?php if (!empty($top_performers)): ?>
    <div class="cards-horizontal">
        <?php 
        $rank = 1;
        foreach ($top_performers as $top): 
        ?>
        <div class="performer-card rank-<?= $rank <= 3 ? $rank : 'other' ?>">
            <div class="rank-badge"><?= $rank ?></div>
            <h3><?= htmlspecialchars(substr($top['nama_lengkap'], 0, 20)) ?><?= strlen($top['nama_lengkap']) > 20 ? '...' : '' ?></h3>
            <div class="developer">
                <i class="fas fa-building"></i> <?= htmlspecialchars(substr($top['developer_name'] ?? 'Unknown', 0, 15)) ?>
            </div>
            
            <div class="stats-mini">
                <div class="stat-mini-item">
                    <div class="stat-mini-value"><?= $top['total_leads'] ?></div>
                    <div class="stat-mini-label">Leads</div>
                </div>
                <div class="stat-mini-item">
                    <div class="stat-mini-value deal"><?= $top['total_deal'] ?></div>
                    <div class="stat-mini-label">Deal</div>
                </div>
                <div class="stat-mini-item">
                    <div class="stat-mini-value"><?= round($top['avg_score'] ?? 0) ?></div>
                    <div class="stat-mini-label">Score</div>
                </div>
            </div>
            
            <?php 
            $conversion = $top['total_leads'] > 0 ? round(($top['total_deal'] / $top['total_leads']) * 100, 1) : 0;
            ?>
            <div style="font-size: 12px; color: var(--text-muted); text-align: center; margin-bottom: 10px;">
                Conversion: <strong style="color: var(--success);"><?= $conversion ?>%</strong>
            </div>
            
            <a href="https://wa.me/<?= $top['phone'] ?>" target="_blank" class="btn-chat">
                <i class="fab fa-whatsapp"></i> Chat Marketing
            </a>
        </div>
        <?php 
        $rank++;
        endforeach; 
        ?>
    </div>
    <?php endif; ?>
    
    <!-- ===== TABEL UNTUK DESKTOP ===== -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fas fa-list"></i>
                Detail Top Performer
            </h3>
            <span class="table-badge"><?= count($top_performers) ?> marketing</span>
        </div>
        
        <?php if (empty($top_performers)): ?>
        <div style="text-align: center; padding: 60px;">
            <i class="fas fa-inbox fa-4x" style="color: var(--border);"></i>
            <p style="margin-top: 16px; color: var(--text-muted);">Tidak ada data</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Marketing</th>
                    <th>Developer</th>
                    <th>Kontak</th>
                    <th>Total Leads</th>
                    <th>Total Deal</th>
                    <th>Conversion</th>
                    <th>Rata Score</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach ($top_performers as $top): 
                $conversion = $top['total_leads'] > 0 ? round(($top['total_deal'] / $top['total_leads']) * 100, 1) : 0;
                ?>
                <tr>
                    <td><strong>#<?= $rank ?></strong></td>
                    <td><strong><?= htmlspecialchars($top['nama_lengkap']) ?></strong><br><small>@<?= htmlspecialchars($top['username']) ?></small></td>
                    <td><?= htmlspecialchars($top['developer_name'] ?? '-') ?></td>
                    <td><a href="https://wa.me/<?= $top['phone'] ?>" target="_blank" style="color: #25D366;"><?= htmlspecialchars($top['phone']) ?></a></td>
                    <td><?= number_format($top['total_leads']) ?></td>
                    <td><strong style="color: var(--success);"><?= number_format($top['total_deal']) ?></strong></td>
                    <td><strong><?= $conversion ?>%</strong></td>
                    <td><?= round($top['avg_score'] ?? 0) ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="https://wa.me/<?= $top['phone'] ?>" target="_blank" class="action-btn whatsapp" title="Chat">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <button class="action-btn view" onclick="window.location.href='manager_activities.php?marketing=<?= $top['id'] ?>'" title="Lihat Aktivitas">
                                <i class="fas fa-history"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php 
                $rank++;
                endforeach; 
                ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> TaufikMarie.com - Top Performer Marketing v2.1</p>
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

// Prevent form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php include 'includes/footer.php'; ?>