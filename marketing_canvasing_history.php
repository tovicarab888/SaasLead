<?php
/**
 * MARKETING_CANVASING_HISTORY.PHP - LEADENGINE
 * Version: 2.0.0 - FIXED: Tampilan foto, path benar
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek session marketing
if (!isMarketing()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$marketing_id = $_SESSION['marketing_id'];
$marketing_name = $_SESSION['marketing_name'] ?? 'Marketing';
$developer_id = $_SESSION['marketing_developer_id'] ?? 0;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Hitung total
$count_sql = "SELECT COUNT(*) FROM canvasing_logs WHERE marketing_id = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute([$marketing_id]);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Ambil data canvasing
$sql = "
    SELECT c.*, l.display_name as location_display, l.icon
    FROM canvasing_logs c
    LEFT JOIN locations l ON c.location_key = l.location_key
    WHERE c.marketing_id = ?
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->execute([$marketing_id, $limit, $offset]);
$canvasing = $stmt->fetchAll();

$page_title = 'Riwayat Canvasing';
$page_subtitle = 'Foto Bukti Kunjungan Anda';
$page_icon = 'fas fa-history';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== CANVASING HISTORY STYLES ===== */
:root {
    --primary: #1B4A3C;
    --primary-light: #2A5F4E;
    --secondary: #D64F3C;
    --secondary-light: #FF6B4A;
    --bg: #F5F3F0;
    --surface: #FFFFFF;
    --text: #1A2A24;
    --text-muted: #7A8A84;
    --border: #E0DAD3;
}

.main-content {
    margin-left: 280px;
    padding: 24px;
    background: var(--bg);
    min-height: 100vh;
}

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

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--surface);
    border-radius: 16px;
    padding: 20px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.stat-icon {
    font-size: 24px;
    color: var(--secondary);
    margin-bottom: 12px;
}

.stat-label {
    font-size: 13px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

/* Canvasing Grid */
.canvasing-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.canvasing-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    transition: transform 0.3s;
}

.canvasing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
}

.canvasing-photo {
    width: 100%;
    height: 200px;
    object-fit: cover;
    cursor: pointer;
    transition: transform 0.3s;
    background-color: #f0f0f0;
}

.canvasing-photo:hover {
    transform: scale(1.02);
}

.canvasing-photo-error {
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, #f5f5f5, #e0e0e0);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 48px;
    cursor: pointer;
}

.canvasing-photo-error span {
    font-size: 14px;
    margin-top: 10px;
    color: var(--text-muted);
}

.canvasing-body {
    padding: 16px;
}

.canvasing-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 8px;
}

.canvasing-location {
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.canvasing-time {
    font-size: 12px;
    color: var(--text-muted);
    background: var(--bg);
    padding: 4px 10px;
    border-radius: 30px;
}

.canvasing-details {
    margin-top: 10px;
}

.canvasing-detail-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 13px;
    color: var(--text-light);
}

.canvasing-detail-item i {
    color: var(--secondary);
    width: 18px;
    margin-top: 2px;
}

.canvasing-gps {
    background: var(--primary-soft);
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 11px;
    color: var(--primary);
    margin-top: 10px;
    word-break: break-all;
}

.canvasing-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn-view {
    flex: 1;
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-view:hover {
    background: var(--primary-light);
}

.btn-wa {
    background: #25D366;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-wa:hover {
    background: #128C7E;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 20px;
    grid-column: span 2;
}

.empty-state i {
    font-size: 60px;
    color: #E0DAD3;
    margin-bottom: 16px;
}

.empty-state h4 {
    color: var(--text);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--text-muted);
    margin-bottom: 20px;
}

.empty-state .btn {
    display: inline-block;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    padding: 12px 30px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 8px;
    border-radius: 10px;
    background: white;
    border: 2px solid var(--border);
    color: var(--text);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s;
}

.pagination-btn:hover {
    background: var(--primary-soft);
    border-color: var(--primary);
    transform: translateY(-2px);
}

.pagination-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* Photo Modal */
.photo-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    z-index: 100000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.photo-modal.show {
    display: flex;
}

.photo-modal img {
    max-width: 100%;
    max-height: 90vh;
    border-radius: 10px;
    box-shadow: 0 30px 60px rgba(0,0,0,0.5);
}

.photo-modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 25px;
    color: white;
    font-size: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.photo-modal-close:hover {
    background: rgba(255,255,255,0.3);
    transform: rotate(90deg);
}

/* Footer */
.footer {
    text-align: center;
    margin-top: 40px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 12px !important;
    }
    
    .top-bar {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .datetime {
        width: 100%;
        justify-content: space-between;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .canvasing-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .empty-state {
        grid-column: span 1;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stat-card {
        padding: 12px;
    }
    
    .stat-value {
        font-size: 20px;
    }
    
    .canvasing-actions {
        flex-direction: column;
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
    
    <!-- STATS -->
    <?php
    // Hitung statistik
    $stmt = $conn->prepare("SELECT COUNT(*) FROM canvasing_logs WHERE marketing_id = ?");
    $stmt->execute([$marketing_id]);
    $total_canvasing = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM canvasing_logs WHERE marketing_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$marketing_id]);
    $today_canvasing = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT location_key) FROM canvasing_logs WHERE marketing_id = ?");
    $stmt->execute([$marketing_id]);
    $unique_locations = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT MAX(created_at) FROM canvasing_logs WHERE marketing_id = ?");
    $stmt->execute([$marketing_id]);
    $last_canvasing = $stmt->fetchColumn();
    ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-camera"></i></div>
            <div class="stat-label">Total Canvasing</div>
            <div class="stat-value"><?= $total_canvasing ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-label">Hari Ini</div>
            <div class="stat-value"><?= $today_canvasing ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div class="stat-label">Lokasi</div>
            <div class="stat-value"><?= $unique_locations ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Terakhir</div>
            <div class="stat-value"><?= $last_canvasing ? date('d/m', strtotime($last_canvasing)) : '-' ?></div>
        </div>
    </div>
    
    <!-- CANVASING GRID -->
    <div class="canvasing-grid">
        <?php if (empty($canvasing)): ?>
        <div class="empty-state">
            <i class="fas fa-camera"></i>
            <h4>Belum Ada Canvasing</h4>
            <p>Anda belum melakukan canvasing. Ambil foto bukti kunjungan pertama Anda!</p>
            <a href="marketing_canvasing.php" class="btn">
                <i class="fas fa-camera"></i> Ambil Foto Sekarang
            </a>
        </div>
        <?php else: ?>
            <?php foreach ($canvasing as $c): 
                // Buat URL foto yang benar
                $photo_url = 'https://taufikmarie.com/' . $c['photo_path'];
                $photo_path_absolute = '/home/taufikma/public_html/' . $c['photo_path'];
                $photo_exists = file_exists($photo_path_absolute);
            ?>
            <div class="canvasing-card">
                <?php if ($photo_exists): ?>
                <img src="<?= $photo_url ?>" 
                     class="canvasing-photo" 
                     alt="Canvasing"
                     onclick="showPhoto('<?= $photo_url ?>')"
                     onerror="this.onerror=null; this.style.display='none'; this.parentElement.querySelector('.canvasing-photo-error').style.display='flex';">
                <?php else: ?>
                <div class="canvasing-photo-error" onclick="showPhotoError()">
                    <i class="fas fa-image"></i>
                    <span>Foto tidak tersedia</span>
                </div>
                <?php endif; ?>
                
                <div class="canvasing-body">
                    <div class="canvasing-header">
                        <div class="canvasing-location">
                            <?= $c['icon'] ?? '📍' ?> <?= htmlspecialchars($c['location_display'] ?? $c['location_key']) ?>
                        </div>
                        <div class="canvasing-time">
                            <i class="far fa-clock"></i> <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                        </div>
                    </div>
                    
                    <div class="canvasing-details">
                        <?php if (!empty($c['customer_name'])): ?>
                        <div class="canvasing-detail-item">
                            <i class="fas fa-user"></i>
                            <span><?= htmlspecialchars($c['customer_name']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($c['customer_phone'])): ?>
                        <div class="canvasing-detail-item">
                            <i class="fab fa-whatsapp"></i>
                            <span><?= htmlspecialchars($c['customer_phone']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($c['notes'])): ?>
                        <div class="canvasing-detail-item">
                            <i class="fas fa-sticky-note"></i>
                            <span><?= htmlspecialchars($c['notes']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="canvasing-gps">
                            <i class="fas fa-map-pin"></i> 
                            <?= $c['latitude'] ?>, <?= $c['longitude'] ?> 
                            (akurasi ±<?= round($c['accuracy']) ?>m)
                        </div>
                    </div>
                    
                    <div class="canvasing-actions">
                        <?php if ($photo_exists): ?>
                        <button class="btn-view" onclick="showPhoto('<?= $photo_url ?>')">
                            <i class="fas fa-eye"></i> Lihat Foto
                        </button>
                        <?php else: ?>
                        <button class="btn-view" onclick="showPhotoError()" style="background: #999;">
                            <i class="fas fa-eye-slash"></i> Foto Tidak Ada
                        </button>
                        <?php endif; ?>
                        
                        <?php if (!empty($c['customer_phone'])): ?>
                        <a href="https://wa.me/<?= $c['customer_phone'] ?>" target="_blank" class="btn-wa">
                            <i class="fab fa-whatsapp"></i> WA
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>" class="pagination-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
        <a href="?page=<?= $i ?>" class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page+1 ?>" class="pagination-btn">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Canvasing History v2.0</p>
    </div>
    
</div>

<!-- PHOTO MODAL -->
<div class="photo-modal" id="photoModal" onclick="hidePhoto()">
    <button class="photo-modal-close" onclick="hidePhoto()">&times;</button>
    <img id="modalPhoto" src="" alt="Canvasing Photo">
</div>

<script>
function showPhoto(url) {
    document.getElementById('modalPhoto').src = url;
    document.getElementById('photoModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function showPhotoError() {
    alert('File foto tidak ditemukan di server. Mungkin sudah dihapus.');
}

function hidePhoto() {
    document.getElementById('photoModal').classList.remove('show');
    document.body.style.overflow = '';
}

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

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hidePhoto();
    }
});
</script>

<?php include 'includes/footer.php'; ?>