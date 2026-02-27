<?php
/**
 * MARKETING_LEADERBOARD.PHP - LEADENGINE
 * Version: 1.0.0 - Peringkat Marketing
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

// Ambil semua marketing dalam satu developer (untuk leaderboard)
$all_marketing = [];
if ($developer_id > 0) {
    $stmt = $conn->prepare("
        SELECT id, nama_lengkap, phone, username 
        FROM marketing_team 
        WHERE developer_id = ? AND is_active = 1
        ORDER BY nama_lengkap
    ");
    $stmt->execute([$developer_id]);
    $all_marketing = $stmt->fetchAll();
}

$page_title = 'Leaderboard';
$page_subtitle = 'Peringkat Marketing';
$page_icon = 'fas fa-trophy';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== LEADERBOARD STYLES ===== */
:root {
    --primary: #1B4A3C;
    --primary-light: #2A5F4E;
    --secondary: #D64F3C;
    --secondary-light: #FF6B4A;
    --gold: #FFD700;
    --silver: #C0C0C0;
    --bronze: #CD7F32;
    --bg: #F5F3F0;
    --surface: #FFFFFF;
    --text: #1A2A24;
    --text-light: #4A5A54;
    --text-muted: #7A8A84;
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

/* Filter Bar */
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
    border: 2px solid #E0DAD3;
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

/* Podium */
.podium-container {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    gap: 15px;
    margin: 40px 0;
    flex-wrap: wrap;
}

.podium-item {
    text-align: center;
    width: 160px;
    background: white;
    border-radius: 24px 24px 0 0;
    padding: 20px 15px 15px;
    box-shadow: 0 -5px 20px rgba(0,0,0,0.05);
    border: 1px solid #E0DAD3;
    border-bottom: none;
    transition: transform 0.3s;
}

.podium-item:hover {
    transform: translateY(-10px);
}

.podium-1 {
    height: 220px;
    background: linear-gradient(135deg, #FFF9E6, #FFF);
    border-top: 6px solid var(--gold);
}

.podium-2 {
    height: 180px;
    background: linear-gradient(135deg, #F5F5F5, #FFF);
    border-top: 6px solid var(--silver);
}

.podium-3 {
    height: 140px;
    background: linear-gradient(135deg, #FDF5E6, #FFF);
    border-top: 6px solid var(--bronze);
}

.podium-rank {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 10px;
}

.podium-1 .podium-rank { color: var(--gold); }
.podium-2 .podium-rank { color: var(--silver); }
.podium-3 .podium-rank { color: var(--bronze); }

.podium-name {
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.podium-stats {
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 10px;
}

.podium-value {
    font-size: 24px;
    font-weight: 800;
    color: var(--secondary);
}

/* Leaderboard Cards */
.leaderboard-card {
    background: white;
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 16px;
    border-left: 6px solid;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    transition: transform 0.2s;
}

.leaderboard-card:hover {
    transform: translateX(5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}

.leaderboard-card.rank-1 { border-left-color: var(--gold); }
.leaderboard-card.rank-2 { border-left-color: var(--silver); }
.leaderboard-card.rank-3 { border-left-color: var(--bronze); }

.leaderboard-rank {
    width: 40px;
    height: 40px;
    background: #F5F3F0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 18px;
    color: var(--primary);
    flex-shrink: 0;
}

.rank-1 .leaderboard-rank { background: var(--gold); color: white; }
.rank-2 .leaderboard-rank { background: var(--silver); color: white; }
.rank-3 .leaderboard-rank { background: var(--bronze); color: white; }

.leaderboard-info {
    flex: 1;
    min-width: 0;
}

.leaderboard-name {
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.leaderboard-meta {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: var(--text-muted);
    flex-wrap: wrap;
}

.leaderboard-meta i {
    color: var(--secondary);
    margin-right: 3px;
}

.leaderboard-stats {
    display: flex;
    gap: 20px;
    margin-top: 8px;
}

.stat-item {
    text-align: center;
    min-width: 60px;
}

.stat-value {
    font-weight: 800;
    font-size: 18px;
    color: var(--primary);
}

.stat-label {
    font-size: 10px;
    color: var(--text-muted);
    text-transform: uppercase;
}

.leaderboard-action {
    flex-shrink: 0;
}

.whatsapp-btn {
    width: 44px;
    height: 44px;
    background: #25D366;
    color: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    transition: all 0.2s;
}

.whatsapp-btn:hover {
    transform: scale(1.1);
    background: #128C7E;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 20px;
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
        width: 100%;
    }
    
    .podium-container {
        flex-direction: column;
        align-items: center;
    }
    
    .podium-item {
        width: 100%;
        max-width: 300px;
    }
    
    .leaderboard-card {
        flex-wrap: wrap;
    }
    
    .leaderboard-stats {
        width: 100%;
        justify-content: space-around;
    }
    
    .leaderboard-action {
        width: 100%;
    }
    
    .whatsapp-btn {
        width: 100%;
        border-radius: 40px;
    }
}

@media (max-width: 480px) {
    .leaderboard-stats {
        gap: 10px;
    }
    
    .stat-item {
        min-width: 50px;
    }
    
    .stat-value {
        font-size: 16px;
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
    
    <!-- FILTER -->
    <div class="filter-bar">
        <form class="filter-form" id="filterForm" onsubmit="loadLeaderboard(); return false;">
            <select name="period" id="period" class="filter-select">
                <option value="today">Hari Ini</option>
                <option value="week" selected>Minggu Ini</option>
                <option value="month">Bulan Ini</option>
            </select>
            
            <select name="sort_by" id="sort_by" class="filter-select">
                <option value="deal">Peringkat berdasarkan DEAL</option>
                <option value="leads">Peringkat berdasarkan LEADS</option>
                <option value="score">Peringkat berdasarkan SCORE</option>
            </select>
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
            </div>
        </form>
    </div>
    
    <!-- PODIUM (TOP 3) -->
    <div class="podium-container" id="podiumContainer">
        <div class="podium-item podium-2">
            <div class="podium-rank">🥈</div>
            <div class="podium-name">-</div>
            <div class="podium-stats">Deal: - | Leads: -</div>
            <div class="podium-value">-</div>
        </div>
        <div class="podium-item podium-1">
            <div class="podium-rank">🥇</div>
            <div class="podium-name">-</div>
            <div class="podium-stats">Deal: - | Leads: -</div>
            <div class="podium-value">-</div>
        </div>
        <div class="podium-item podium-3">
            <div class="podium-rank">🥉</div>
            <div class="podium-name">-</div>
            <div class="podium-stats">Deal: - | Leads: -</div>
            <div class="podium-value">-</div>
        </div>
    </div>
    
    <!-- LEADERBOARD CARDS -->
    <div id="leaderboardContainer">
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin fa-3x" style="color: var(--secondary);"></i>
            <p style="margin-top: 16px; color: var(--text-muted);">Memuat leaderboard...</p>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Marketing Leaderboard</p>
    </div>
    
</div>

<script>
// Load leaderboard data
function loadLeaderboard() {
    const period = document.getElementById('period').value;
    const sortBy = document.getElementById('sort_by').value;
    
    fetch(`api/leaderboard_api.php?action=get&period=${period}&sort_by=${sortBy}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderLeaderboard(data.data, sortBy);
            } else {
                document.getElementById('leaderboardContainer').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <h4>Gagal Memuat Data</h4>
                        <p>${data.message || 'Terjadi kesalahan'}</p>
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error('Leaderboard error:', err);
            document.getElementById('leaderboardContainer').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-circle"></i>
                    <h4>Error</h4>
                    <p>Gagal terhubung ke server</p>
                </div>
            `;
        });
}

// Render leaderboard
function renderLeaderboard(data, sortBy) {
    const podiumContainer = document.getElementById('podiumContainer');
    const leaderboardContainer = document.getElementById('leaderboardContainer');
    
    if (!data || data.length === 0) {
        podiumContainer.style.display = 'none';
        leaderboardContainer.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-trophy"></i>
                <h4>Belum Ada Data</h4>
                <p>Belum ada aktivitas marketing untuk periode ini</p>
            </div>
        `;
        return;
    }
    
    podiumContainer.style.display = 'flex';
    
    // Tampilkan top 3 di podium
    if (data.length >= 1) {
        const rank1 = data[0];
        document.querySelector('.podium-1 .podium-name').textContent = rank1.nama_lengkap;
        document.querySelector('.podium-1 .podium-stats').innerHTML = `Deal: ${rank1.total_deal} | Leads: ${rank1.total_leads}`;
        
        if (sortBy === 'deal') {
            document.querySelector('.podium-1 .podium-value').textContent = rank1.total_deal;
        } else if (sortBy === 'leads') {
            document.querySelector('.podium-1 .podium-value').textContent = rank1.total_leads;
        } else {
            document.querySelector('.podium-1 .podium-value').textContent = rank1.avg_score + '⭐';
        }
    }
    
    if (data.length >= 2) {
        const rank2 = data[1];
        document.querySelector('.podium-2 .podium-name').textContent = rank2.nama_lengkap;
        document.querySelector('.podium-2 .podium-stats').innerHTML = `Deal: ${rank2.total_deal} | Leads: ${rank2.total_leads}`;
        
        if (sortBy === 'deal') {
            document.querySelector('.podium-2 .podium-value').textContent = rank2.total_deal;
        } else if (sortBy === 'leads') {
            document.querySelector('.podium-2 .podium-value').textContent = rank2.total_leads;
        } else {
            document.querySelector('.podium-2 .podium-value').textContent = rank2.avg_score + '⭐';
        }
    } else {
        document.querySelector('.podium-2 .podium-name').textContent = '-';
        document.querySelector('.podium-2 .podium-stats').innerHTML = 'Deal: - | Leads: -';
        document.querySelector('.podium-2 .podium-value').textContent = '-';
    }
    
    if (data.length >= 3) {
        const rank3 = data[2];
        document.querySelector('.podium-3 .podium-name').textContent = rank3.nama_lengkap;
        document.querySelector('.podium-3 .podium-stats').innerHTML = `Deal: ${rank3.total_deal} | Leads: ${rank3.total_leads}`;
        
        if (sortBy === 'deal') {
            document.querySelector('.podium-3 .podium-value').textContent = rank3.total_deal;
        } else if (sortBy === 'leads') {
            document.querySelector('.podium-3 .podium-value').textContent = rank3.total_leads;
        } else {
            document.querySelector('.podium-3 .podium-value').textContent = rank3.avg_score + '⭐';
        }
    } else {
        document.querySelector('.podium-3 .podium-name').textContent = '-';
        document.querySelector('.podium-3 .podium-stats').innerHTML = 'Deal: - | Leads: -';
        document.querySelector('.podium-3 .podium-value').textContent = '-';
    }
    
    // Render cards untuk semua marketing
    let html = '';
    data.forEach((marketing, index) => {
        const rank = index + 1;
        let rankClass = '';
        if (rank === 1) rankClass = 'rank-1';
        else if (rank === 2) rankClass = 'rank-2';
        else if (rank === 3) rankClass = 'rank-3';
        
        let displayValue = '';
        if (sortBy === 'deal') displayValue = marketing.total_deal;
        else if (sortBy === 'leads') displayValue = marketing.total_leads;
        else displayValue = marketing.avg_score + '⭐';
        
        html += `
            <div class="leaderboard-card ${rankClass}">
                <div class="leaderboard-rank">${rank}</div>
                <div class="leaderboard-info">
                    <div class="leaderboard-name">${marketing.nama_lengkap}</div>
                    <div class="leaderboard-meta">
                        <span><i class="fas fa-phone-alt"></i> ${marketing.phone || '-'}</span>
                        <span><i class="fas fa-user"></i> @${marketing.username || 'marketing'}</span>
                    </div>
                    <div class="leaderboard-stats">
                        <div class="stat-item">
                            <div class="stat-value">${marketing.total_deal}</div>
                            <div class="stat-label">DEAL</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">${marketing.total_leads}</div>
                            <div class="stat-label">LEADS</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">${marketing.avg_score}</div>
                            <div class="stat-label">SCORE</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">${marketing.follow_up}</div>
                            <div class="stat-label">FOLLOW</div>
                        </div>
                    </div>
                </div>
                <div class="leaderboard-action">
                    <a href="https://wa.me/${marketing.phone}" target="_blank" class="whatsapp-btn" title="Chat via WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>
        `;
    });
    
    leaderboardContainer.innerHTML = html;
}

// Load on page load
document.addEventListener('DOMContentLoaded', function() {
    loadLeaderboard();
    
    // Auto refresh setiap 30 detik
    setInterval(loadLeaderboard, 30000);
    
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
});
</script>

<?php include 'includes/footer.php'; ?>