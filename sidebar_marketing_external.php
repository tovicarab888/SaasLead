<?php
/**
 * SIDEBAR_MARKETING_EXTERNAL.PHP - Sidebar khusus marketing external
 * Version: 1.0.0 - UI SAMA PERSIS DENGAN SIDEBAR LAIN
 */

$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['nama_lengkap'] ?? 'Marketing External';
$first_char = strtoupper(substr($user_name, 0, 1));
?>
<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="sidebar-title">
            Lead Engine
            <small>Marketing External</small>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <div class="sidebar-menu-title">MENU UTAMA</div>
        
        <a href="marketing_external_dashboard.php" class="sidebar-item <?= $current_page == 'marketing_external_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="marketing_external_leads.php" class="sidebar-item <?= in_array($current_page, ['marketing_external_leads.php', 'marketing_external_lead_detail.php']) ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Leads Saya</span>
            <span class="badge">Baru</span>
        </a>
        
        <a href="marketing_external_komisi.php" class="sidebar-item <?= $current_page == 'marketing_external_komisi.php' ? 'active' : '' ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Komisi</span>
        </a>
        
        <a href="marketing_external_profile.php" class="sidebar-item <?= $current_page == 'marketing_external_profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i>
            <span>Profil Saya</span>
        </a>
        
        <div class="sidebar-menu-title">LAINNYA</div>
        
        <a href="logout.php" class="sidebar-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
    
    <!-- User Profile Section -->
    <div style="position: absolute; bottom: 20px; left: 20px; right: 20px;">
        <div style="background: rgba(255,255,255,0.1); border-radius: 16px; padding: 16px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 45px; height: 45px; background: var(--secondary); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px;">
                    <?= $first_char ?>
                </div>
                <div style="flex: 1; color: white;">
                    <div style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($user_name) ?></div>
                    <div style="font-size: 11px; opacity: 0.7;">Marketing External</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BOTTOM NAVIGATION UNTUK MOBILE -->
<div class="bottom-nav">
    <div class="bottom-nav-container">
        <a href="marketing_external_dashboard.php" class="bottom-nav-item <?= $current_page == 'marketing_external_dashboard.php' ? 'active' : '' ?>">
            <div class="bottom-nav-icon"><i class="fas fa-home"></i></div>
            <span>Home</span>
        </a>
        <a href="marketing_external_leads.php" class="bottom-nav-item <?= $current_page == 'marketing_external_leads.php' ? 'active' : '' ?>">
            <div class="bottom-nav-icon"><i class="fas fa-users"></i></div>
            <span>Leads</span>
        </a>
        <a href="marketing_external_komisi.php" class="bottom-nav-item <?= $current_page == 'marketing_external_komisi.php' ? 'active' : '' ?>">
            <div class="bottom-nav-icon"><i class="fas fa-money-bill-wave"></i></div>
            <span>Komisi</span>
        </a>
        <a href="marketing_external_profile.php" class="bottom-nav-item <?= $current_page == 'marketing_external_profile.php' ? 'active' : '' ?>">
            <div class="bottom-nav-icon"><i class="fas fa-user"></i></div>
            <span>Profil</span>
        </a>
    </div>
</div>

<style>
/* ===== BOTTOM NAVIGATION STYLES ===== */
.bottom-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
    z-index: 1000;
    padding: 8px 0 12px;
    border-radius: 30px 30px 0 0;
}

.bottom-nav-container {
    display: flex;
    justify-content: space-around;
    align-items: center;
    max-width: 500px;
    margin: 0 auto;
    padding: 0 8px;
}

.bottom-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #7A8A84;
    font-size: 11px;
    font-weight: 600;
    padding: 6px 0 2px;
    gap: 4px;
    min-width: 50px;
}

.bottom-nav-icon {
    width: 42px;
    height: 42px;
    border-radius: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    background: transparent;
}

.bottom-nav-item i {
    font-size: 20px;
}

.bottom-nav-item.active {
    color: #D64F3C;
}

.bottom-nav-item.active .bottom-nav-icon {
    background: rgba(214,79,60,0.15);
    color: #D64F3C;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .bottom-nav {
        display: block;
    }
    
    .sidebar {
        display: none;
    }
    
    .main-content {
        margin-left: 0 !important;
        padding-bottom: 90px !important;
    }
}
</style>