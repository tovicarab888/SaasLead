<?php
/**
 * MARKETING_EXTERNAL_LEAD_DETAIL.PHP - Detail Lead untuk Marketing External
 * Version: 1.0.0 - UI GLOBAL KEREN
 */

session_start();
require_once 'api/config.php';

// Cek akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'marketing_external') {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) die("Database connection failed");

$lead_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

if ($lead_id <= 0) {
    header('Location: marketing_external_leads.php');
    exit();
}

// Ambil detail lead (pastikan hanya milik marketing ini)
$stmt = $conn->prepare("
    SELECT 
        l.*,
        loc.display_name as location_display,
        loc.icon,
        loc.city,
        d.nama_lengkap as developer_name,
        d.nama_perusahaan
    FROM leads l
    LEFT JOIN locations loc ON l.location_key = loc.location_key
    LEFT JOIN users d ON l.ditugaskan_ke = d.id
    WHERE l.id = ? AND l.assigned_marketing_team_id = ? AND l.assigned_type = 'external'
");
$stmt->execute([$lead_id, $user_id]);
$lead = $stmt->fetch();

if (!$lead) {
    header('Location: marketing_external_leads.php');
    exit();
}

// Ambil aktivitas
$activities = $conn->prepare("
    SELECT * FROM marketing_activities 
    WHERE lead_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$activities->execute([$lead_id]);
$activities = $activities->fetchAll();

// Ambil data komisi
$komisi = $conn->prepare("
    SELECT * FROM komisi_logs 
    WHERE lead_id = ? AND marketing_id = ?
    ORDER BY created_at DESC
");
$komisi->execute([$lead_id, $user_id]);
$komisi = $komisi->fetchAll();

$page_title = 'Detail Lead';
$page_subtitle = htmlspecialchars($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
$page_icon = 'fas fa-user';

include 'includes/header.php';
include 'includes/sidebar_marketing_external.php';
?>

<style>
.detail-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

@media (min-width: 992px) {
    .detail-container {
        grid-template-columns: 1fr 1fr;
    }
}

.detail-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    border: 1px solid var(--border);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    margin-bottom: 20px;
}

.detail-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--primary-soft);
}

.detail-avatar {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    color: white;
    flex-shrink: 0;
}

.detail-title h3 {
    color: var(--primary);
    font-size: 20px;
    margin-bottom: 4px;
}

.detail-title p {
    color: var(--text-muted);
    font-size: 13px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.info-item {
    background: var(--primary-soft);
    padding: 16px;
    border-radius: 16px;
}

.info-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 4px;
}

.info-value {
    font-size: 16px;
    font-weight: 600;
    color: var(--text);
    word-break: break-word;
}

.info-value a {
    color: #25D366;
    text-decoration: none;
}

.info-value a:hover {
    text-decoration: underline;
}

.status-badge-large {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    color: white;
}

/* ===== ACTIVITY TIMELINE ===== */
.timeline {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.timeline-item {
    display: flex;
    gap: 12px;
    padding: 16px;
    background: #F8FAFC;
    border-radius: 16px;
    border-left: 4px solid var(--secondary);
}

.timeline-icon {
    width: 36px;
    height: 36px;
    background: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--secondary);
    flex-shrink: 0;
}

.timeline-content {
    flex: 1;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
}

.timeline-action {
    font-weight: 700;
    color: var(--primary);
    font-size: 13px;
}

.timeline-time {
    font-size: 11px;
    color: var(--text-muted);
}

.timeline-note {
    background: white;
    padding: 10px;
    border-radius: 8px;
    font-size: 12px;
    color: var(--text-light);
    border: 1px solid var(--border);
}

/* ===== BACK BUTTON ===== */
.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--primary-soft);
    color: var(--primary);
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 20px;
}

.back-btn:hover {
    background: var(--border);
}

/* ===== ACTION BUTTON ===== */
.action-btn-large {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 24px;
    background: linear-gradient(135deg, #25D366, #128C7E);
    color: white;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    margin-top: 16px;
    width: 100%;
    justify-content: center;
}

.action-btn-large:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(37,211,102,0.2);
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
    
    <!-- BACK BUTTON -->
    <a href="marketing_external_leads.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Leads
    </a>
    
    <!-- DETAIL CONTAINER -->
    <div class="detail-container">
        <!-- LEFT COLUMN - INFO LEAD -->
        <div>
            <div class="detail-card">
                <div class="detail-header">
                    <div class="detail-avatar">
                        <?= strtoupper(substr($lead['first_name'], 0, 1)) ?>
                    </div>
                    <div class="detail-title">
                        <h3><?= htmlspecialchars($lead['first_name'] . ' ' . ($lead['last_name'] ?? '')) ?></h3>
                        <p>Lead ID: #<?= $lead['id'] ?> • Bergabung: <?= date('d/m/Y', strtotime($lead['created_at'])) ?></p>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-phone"></i> WhatsApp</div>
                        <div class="info-value">
                            <a href="https://wa.me/<?= $lead['phone'] ?>" target="_blank">
                                <?= htmlspecialchars($lead['phone']) ?>
                            </a>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                        <div class="info-value"><?= htmlspecialchars($lead['email'] ?? '-') ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-map-marker-alt"></i> Lokasi</div>
                        <div class="info-value"><?= htmlspecialchars($lead['location_display'] ?? $lead['location_key']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-building"></i> Developer</div>
                        <div class="info-value"><?= htmlspecialchars($lead['developer_name'] ?? '-') ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-home"></i> Tipe Unit</div>
                        <div class="info-value"><?= htmlspecialchars($lead['unit_type'] ?? '-') ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-tag"></i> Program</div>
                        <div class="info-value"><?= htmlspecialchars($lead['program'] ?? '-') ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-chart-line"></i> Lead Score</div>
                        <div class="info-value"><strong><?= $lead['lead_score'] ?></strong></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-circle"></i> Status</div>
                        <div class="info-value">
                            <span class="status-badge-large" style="background: <?php
                                switch($lead['status']) {
                                    case 'Baru': echo '#4A90E2'; break;
                                    case 'Follow Up': echo '#E9C46A'; break;
                                    case 'Survey': echo '#E9C46A'; break;
                                    case 'Booking': echo '#1B4A3C'; break;
                                    case 'Deal KPR': echo '#2A9D8F'; break;
                                    case 'Deal Tunai': echo '#FF9800'; break;
                                    default: echo '#757575';
                                }
                            ?>;">
                                <?= $lead['status'] ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($lead['address'])): ?>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                    <div class="info-label"><i class="fas fa-map-pin"></i> Alamat</div>
                    <div style="margin-top: 8px; color: var(--text-light);"><?= nl2br(htmlspecialchars($lead['address'])) ?></div>
                </div>
                <?php endif; ?>
                
                <a href="https://wa.me/<?= $lead['phone'] ?>?text=Halo%20<?= urlencode($lead['first_name']) ?>%2C%20saya%20<?= urlencode($_SESSION['nama_lengkap']) ?>%20dari%20LeadEngine" 
                   target="_blank" class="action-btn-large">
                    <i class="fab fa-whatsapp"></i> Chat via WhatsApp
                </a>
            </div>
            
            <!-- KOMISI CARD -->
            <?php if (!empty($komisi)): ?>
            <div class="detail-card">
                <h3 style="margin-bottom: 16px; color: var(--primary);"><i class="fas fa-money-bill-wave"></i> Riwayat Komisi</h3>
                
                <?php foreach ($komisi as $k): ?>
                <div style="background: var(--primary-soft); border-radius: 16px; padding: 16px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-weight: 600; color: var(--primary);">Komisi</span>
                        <span class="status-badge" style="background: <?= $k['status'] == 'cair' ? '#2A9D8F' : ($k['status'] == 'pending' ? '#E9C46A' : '#D64F3C') ?>; color: white;">
                            <?= ucfirst($k['status']) ?>
                        </span>
                    </div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--success); margin-bottom: 8px;">
                        Rp <?= number_format($k['komisi_final'], 0, ',', '.') ?>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted);">
                        <?= date('d/m/Y H:i', strtotime($k['created_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- RIGHT COLUMN - ACTIVITIES -->
        <div>
            <div class="detail-card">
                <h3 style="margin-bottom: 16px; color: var(--primary);"><i class="fas fa-history"></i> Riwayat Aktivitas</h3>
                
                <?php if (empty($activities)): ?>
                <div class="empty-state" style="padding: 30px;">
                    <i class="fas fa-inbox"></i>
                    <p>Belum ada aktivitas</p>
                </div>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($activities as $act): 
                        $icon = '📋';
                        switch($act['action_type']) {
                            case 'follow_up': $icon = '📞'; break;
                            case 'call': $icon = '📱'; break;
                            case 'whatsapp': $icon = '💬'; break;
                            case 'survey': $icon = '📍'; break;
                            case 'booking': $icon = '📝'; break;
                            case 'update_status': $icon = '🔄'; break;
                        }
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-icon"><?= $icon ?></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-action"><?= ucfirst(str_replace('_', ' ', $act['action_type'])) ?></span>
                                <span class="timeline-time"><?= date('d/m H:i', strtotime($act['created_at'])) ?></span>
                            </div>
                            
                            <?php if ($act['status_before'] && $act['status_after']): ?>
                            <div style="margin-bottom: 8px; font-size: 12px;">
                                <span style="background: #FEE2E2; padding: 2px 8px; border-radius: 12px;"><?= $act['status_before'] ?></span>
                                <i class="fas fa-arrow-right" style="margin: 0 8px; color: var(--secondary);"></i>
                                <span style="background: #D1FAE5; padding: 2px 8px; border-radius: 12px;"><?= $act['status_after'] ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($act['note_text'])): ?>
                            <div class="timeline-note">
                                <?= nl2br(htmlspecialchars($act['note_text'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Marketing External Lead Detail</p>
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