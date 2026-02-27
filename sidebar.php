<?php
/**
 * SIDEBAR.PHP - TAUFIKMARIE.COM ULTIMATE
 * Version: 30.0.0 - FINAL: Semua role tersinkronisasi, menu cepat seragam
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Dapatkan role user
$current_role = getCurrentRole();
$user_name = '';
$user_photo = '';

// Ambil data user berdasarkan role
$conn_sidebar = getDB();
if ($conn_sidebar) {
    if (isMarketing()) {
        $marketing_id = $_SESSION['marketing_id'] ?? 0;
        $stmt = $conn_sidebar->prepare("SELECT nama_lengkap, profile_photo, marketing_type_id FROM marketing_team WHERE id = ?");
        $stmt->execute([$marketing_id]);
        $user_data = $stmt->fetch();
        $user_name = $user_data['nama_lengkap'] ?? $_SESSION['marketing_name'] ?? 'Marketing';
        $user_photo = $user_data['profile_photo'] ?? '';
        
        // Tentukan tipe marketing (internal/external)
        $marketing_type_id = $user_data['marketing_type_id'] ?? 0;
        $is_marketing_external = ($marketing_type_id == 2); // Asumsi type_id 2 untuk external
    } else {
        $user_id = $_SESSION['user_id'] ?? 0;
        $stmt = $conn_sidebar->prepare("SELECT nama_lengkap, profile_photo FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        $user_name = $user_data['nama_lengkap'] ?? $_SESSION['nama_lengkap'] ?? 'User';
        $user_photo = $user_data['profile_photo'] ?? '';
    }
}

// Ambil URL saat ini untuk active menu
$current_page = basename($_SERVER['PHP_SELF']);
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : '';

// ===== CEK ROLE DARI SESSION =====
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$is_manager = (isset($_SESSION['role']) && $_SESSION['role'] === 'manager');
$is_developer = (isset($_SESSION['role']) && $_SESSION['role'] === 'developer');
$is_manager_developer = (isset($_SESSION['role']) && $_SESSION['role'] === 'manager_developer');
$is_finance = (isset($_SESSION['role']) && $_SESSION['role'] === 'finance');
$is_finance_platform = (isset($_SESSION['role']) && $_SESSION['role'] === 'finance_platform');
$is_marketing = (isset($_SESSION['marketing_id']));
$is_marketing_external = isset($is_marketing_external) ? $is_marketing_external : false;

// Untuk manager developer & finance, ambil developer_id dari session
$developer_id = 0;
if ($is_developer) {
    $developer_id = $_SESSION['user_id'];
} elseif ($is_manager_developer || $is_finance) {
    $developer_id = $_SESSION['developer_id'] ?? 0;
}
?>
<!-- SIDEBAR - DESKTOP -->
<div class="sidebar">
    
    <!-- PROFILE SECTION -->
    <div class="sidebar-profile">
        <div class="profile-photo-wrapper">
            <?php if (!empty($user_photo) && file_exists(dirname(__DIR__) . '/uploads/profiles/' . $user_photo)): ?>
                <img src="/admin/uploads/profiles/<?= htmlspecialchars($user_photo) ?>?t=<?= time() ?>" alt="Profile">
            <?php else: ?>
                <div class="profile-initials-sidebar">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
            <?php endif; ?>
            
            <!-- Tombol upload foto -->
            <button onclick="openProfilePhotoModal()" class="profile-photo-upload-btn" title="Ganti Foto Profil">
                <i class="fas fa-camera"></i>
            </button>
        </div>
        <div class="profile-info">
            <div class="profile-name-sidebar"><?= htmlspecialchars($user_name) ?></div>
            <div class="profile-role-sidebar">
                <i class="fas fa-circle" style="font-size: 6px; color: #2A9D8F;"></i>
                <?php 
                if ($is_marketing) {
                    if ($is_marketing_external) {
                        echo 'MARKETING EXTERNAL';
                    } else {
                        echo 'MARKETING INTERNAL';
                    }
                } elseif ($is_admin) echo 'SUPER ADMIN';
                elseif ($is_manager) echo 'MANAGER PLATFORM';
                elseif ($is_manager_developer) echo 'MANAGER DEVELOPER';
                elseif ($is_finance) echo 'FINANCE DEVELOPER';
                elseif ($is_finance_platform) echo 'FINANCE PLATFORM';
                elseif ($is_developer) echo 'DEVELOPER';
                else echo 'GUEST';
                ?>
            </div>
        </div>
    </div>
    
    <!-- SIDEBAR MENU -->
    <div class="sidebar-menu">
        
        <!-- ===== MENU UTAMA ===== -->
        <div class="sidebar-menu-title">UTAMA</div>
        
        <!-- Dashboard Admin -->
        <?php if ($is_admin): ?>
        <a href="index.php" class="sidebar-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard Utama</span>
        </a>
        <?php endif; ?>
        
        <!-- Dashboard Manager -->
        <?php if ($is_manager): ?>
        <a href="manager_dashboard.php" class="sidebar-item <?= $current_page == 'manager_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i>
            <span>Dashboard Manager</span>
        </a>
        <?php endif; ?>
        
        <!-- Dashboard Developer -->
        <?php if ($is_developer): ?>
        <a href="developer_dashboard.php" class="sidebar-item <?= $current_page == 'developer_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard Developer</span>
        </a>
        <?php endif; ?>
        
        <!-- Dashboard Manager Developer -->
        <?php if ($is_manager_developer): ?>
        <a href="manager_developer_dashboard.php" class="sidebar-item <?= $current_page == 'manager_developer_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard Manager</span>
        </a>
        <?php endif; ?>
        
        <!-- Dashboard Finance Developer -->
        <?php if ($is_finance): ?>
        <a href="finance_dashboard.php" class="sidebar-item <?= $current_page == 'finance_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i>
            <span>Dashboard Finance</span>
        </a>
        <?php endif; ?>
        
        <!-- Dashboard Finance Platform -->
        <?php if ($is_finance_platform): ?>
        <a href="finance_platform_dashboard.php" class="sidebar-item <?= $current_page == 'finance_platform_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-coins"></i>
            <span>Dashboard Finance</span>
        </a>
        <?php endif; ?>
        
        <!-- Dashboard Marketing -->
        <?php if ($is_marketing): ?>
        <a href="marketing_dashboard.php" class="sidebar-item <?= $current_page == 'marketing_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard Marketing</span>
        </a>
        <?php endif; ?>
        
        <!-- ===== FINANCE PLATFORM ===== -->
        <?php if ($is_finance_platform): ?>
        <div class="sidebar-menu-title">FINANCE PLATFORM</div>
        
        <a href="finance_platform_dashboard.php" class="sidebar-item <?= $current_page == 'finance_platform_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i>
            <span>Dashboard</span>
        </a>
        
        <!-- Verifikasi Komisi External -->
        <a href="finance_platform_verifikasi.php" class="sidebar-item <?= $current_page == 'finance_platform_verifikasi.php' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i>
            <span>Verifikasi Komisi</span>
            <?php
            if ($conn_sidebar) {
                $stmt = $conn_sidebar->query("SELECT COUNT(*) FROM komisi_logs WHERE assigned_type = 'external' AND status = 'pending'");
                $pending = $stmt->fetchColumn();
                if ($pending > 0) {
                    echo '<span class="badge">' . $pending . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Kelola Komisi -->
        <a href="finance_platform_komisi.php" class="sidebar-item <?= $current_page == 'finance_platform_komisi.php' ? 'active' : '' ?>">
            <i class="fas fa-coins"></i>
            <span>Kelola Komisi</span>
        </a>
        
        <!-- Verifikasi Rekening External -->
        <a href="finance_platform_rekening.php" class="sidebar-item <?= $current_page == 'finance_platform_rekening.php' ? 'active' : '' ?>">
            <i class="fas fa-university"></i>
            <span>Rekening External</span>
            <?php
            if ($conn_sidebar) {
                $stmt = $conn_sidebar->query("
                    SELECT COUNT(*) FROM marketing_team m 
                    LEFT JOIN marketing_types mt ON m.marketing_type_id = mt.id 
                    WHERE (mt.type_name = 'external' OR mt.type_name IS NULL) 
                    AND m.rekening_verified = 0 
                    AND m.nomor_rekening IS NOT NULL
                ");
                $unverified = $stmt->fetchColumn();
                if ($unverified > 0) {
                    echo '<span class="badge">' . $unverified . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Laporan Komisi -->
        <a href="finance_platform_laporan.php" class="sidebar-item <?= $current_page == 'finance_platform_laporan.php' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice"></i>
            <span>Laporan Komisi</span>
        </a>
        
        <!-- Kelola Marketing External -->
        <a href="finance_platform_external.php" class="sidebar-item <?= $current_page == 'finance_platform_external.php' ? 'active' : '' ?>">
            <i class="fas fa-user-tie"></i>
            <span>Marketing External</span>
            <?php
            if ($conn_sidebar) {
                $stmt = $conn_sidebar->query("
                    SELECT COUNT(*) FROM marketing_team m 
                    LEFT JOIN marketing_types mt ON m.marketing_type_id = mt.id 
                    WHERE (mt.type_name = 'external' OR mt.type_name IS NULL) AND m.is_active = 1
                ");
                $total = $stmt->fetchColumn();
                if ($total > 0) {
                    echo '<span class="badge">' . $total . '</span>';
                }
            }
            ?>
        </a>
        <?php endif; ?>
        
        <!-- ===== MENU KHUSUS MANAGER PLATFORM ===== -->
        <?php if ($is_manager): ?>
        <div class="sidebar-menu-title">MANAJEMEN KINERJA</div>
        
        <!-- KPI Marketing -->
        <a href="manager_kpi.php" class="sidebar-item <?= $current_page == 'manager_kpi.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i>
            <span>KPI Marketing</span>
            <?php
            if ($conn_sidebar) {
                $stmt = $conn_sidebar->query("SELECT COUNT(*) FROM marketing_team WHERE is_active = 1");
                $total_marketing = $stmt->fetchColumn();
                if ($total_marketing > 0) {
                    echo '<span class="badge">' . $total_marketing . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Top Performer -->
        <a href="manager_top_performer.php" class="sidebar-item <?= $current_page == 'manager_top_performer.php' ? 'active' : '' ?>">
            <i class="fas fa-crown"></i>
            <span>Top Performer</span>
        </a>
        
        <!-- Analisis Bulanan -->
        <a href="manager_analisis.php" class="sidebar-item <?= $current_page == 'manager_analisis.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Analisis Bulanan</span>
        </a>
        
        <!-- Aktivitas Marketing -->
        <a href="manager_activities.php" class="sidebar-item <?= $current_page == 'manager_activities.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i>
            <span>Aktivitas Marketing</span>
            <?php
            if ($conn_sidebar) {
                $stmt = $conn_sidebar->query("SELECT COUNT(*) FROM marketing_activities WHERE DATE(created_at) = CURDATE()");
                $today_activities = $stmt->fetchColumn();
                if ($today_activities > 0) {
                    echo '<span class="badge">' . $today_activities . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Canvasing Dashboard -->
        <a href="manager_canvasing_dashboard.php" class="sidebar-item <?= $current_page == 'manager_canvasing_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-camera-retro"></i>
            <span>Canvasing</span>
            <?php
            if ($conn_sidebar) {
                $stmt = $conn_sidebar->query("SELECT COUNT(*) FROM canvasing_logs WHERE DATE(created_at) = CURDATE()");
                $today = $stmt->fetchColumn();
                if ($today > 0) {
                    echo '<span class="badge">' . $today . '</span>';
                }
            }
            ?>
        </a>
        <?php endif; ?>
        
        <!-- ===== MANAJEMEN PROYEK - UNTUK DEVELOPER ===== -->
        <?php if ($is_developer): ?>
        <div class="sidebar-menu-title">MANAJEMEN PROYEK</div>
        
        <!-- Kelola Cluster -->
        <a href="developer_clusters.php" class="sidebar-item <?= $current_page == 'developer_clusters.php' ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i>
            <span>Kelola Cluster</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM clusters WHERE developer_id = ?");
                $stmt->execute([$developer_id]);
                $total = $stmt->fetchColumn();
                if ($total > 0) {
                    echo '<span class="badge">' . $total . '</span>';
                }
            }
            ?>
        </a>
        
        
        
        <!-- Program Booking -->
        <a href="developer_program_booking.php" class="sidebar-item <?= $current_page == 'developer_program_booking.php' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i>
            <span>Program Booking</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM program_booking WHERE developer_id = ? AND is_active = 1");
                $stmt->execute([$developer_id]);
                $total = $stmt->fetchColumn();
                if ($total > 0) {
                    echo '<span class="badge">' . $total . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Master Biaya -->
        <a href="developer_biaya_kategori.php" class="sidebar-item <?= $current_page == 'developer_biaya_kategori.php' ? 'active' : '' ?>">
            <i class="fas fa-coins"></i>
            <span>Master Biaya</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM biaya_kategori WHERE developer_id = ? AND is_active = 1");
                $stmt->execute([$developer_id]);
                $total = $stmt->fetchColumn();
                if ($total > 0) {
                    echo '<span class="badge">' . $total . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Biaya per Block -->
        <a href="developer_block_biaya.php" class="sidebar-item <?= $current_page == 'developer_block_biaya.php' ? 'active' : '' ?>">
            <i class="fas fa-cubes"></i>
            <span>Biaya per Block</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("
                    SELECT COUNT(*) FROM block_biaya_tambahan bb
                    JOIN blocks b ON bb.block_id = b.id
                    JOIN clusters c ON b.cluster_id = c.id
                    WHERE c.developer_id = ?
                ");
                $stmt->execute([$developer_id]);
                $total = $stmt->fetchColumn();
                if ($total > 0) {
                    echo '<span class="badge">' . $total . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Rekening Bank Perusahaan -->
        <a href="developer_banks.php" class="sidebar-item <?= $current_page == 'developer_banks.php' ? 'active' : '' ?>">
            <i class="fas fa-university"></i>
            <span>Rekening Bank</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM banks WHERE developer_id = ? AND is_active = 1");
                $stmt->execute([$developer_id]);
                $total = $stmt->fetchColumn();
                if ($total > 0) {
                    echo '<span class="badge">' . $total . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Unit Programs -->
        <a href="unit_programs.php?developer_id=<?= $developer_id ?>" class="sidebar-item <?= $current_page == 'unit_programs.php' ? 'active' : '' ?>">
            <i class="fas fa-check-double"></i>
            <span>Unit Programs</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM units WHERE cluster_id IN (SELECT id FROM clusters WHERE developer_id = ?)");
                $stmt->execute([$developer_id]);
                $total_units = $stmt->fetchColumn();
                if ($total_units > 0) {
                    echo '<span class="badge">' . $total_units . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Canvasing Dashboard -->
        <div class="sidebar-menu-title">MONITORING CANVASING</div>
        
        <a href="developer_canvasing_dashboard.php" class="sidebar-item <?= $current_page == 'developer_canvasing_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-camera-retro"></i>
            <span>Canvasing Dashboard</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM canvasing_logs WHERE developer_id = ? AND DATE(created_at) = CURDATE()");
                $stmt->execute([$developer_id]);
                $today = $stmt->fetchColumn();
                if ($today > 0) {
                    echo '<span class="badge">' . $today . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Tracking Analytics -->
        <div class="sidebar-menu-title">TRACKING PIXEL</div>
        
        <a href="developer_tracking.php" class="sidebar-item <?= $current_page == 'developer_tracking.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Tracking Analytics</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM tracking_logs WHERE developer_id = ? AND DATE(created_at) = CURDATE()");
                $stmt->execute([$developer_id]);
                $today = $stmt->fetchColumn();
                if ($today > 0) {
                    echo '<span class="badge">' . $today . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Tim Manajemen -->
        <div class="sidebar-menu-title">MANAJEMEN TIM</div>
        
        <a href="developer_team.php" class="sidebar-item <?= $current_page == 'developer_team.php' ? 'active' : '' ?>">
            <i class="fas fa-user-tie"></i>
            <span>Tim Manajemen</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM users WHERE developer_id = ? AND role IN ('manager_developer', 'finance') AND is_active = 1");
                $stmt->execute([$developer_id]);
                $total = $stmt->fetchColumn();
                if ($total > 0) {
                    echo '<span class="badge">' . $total . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Marketing Team -->
        <a href="marketing_team.php" class="sidebar-item <?= $current_page == 'marketing_team.php' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i>
            <span>Marketing Team</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM marketing_team WHERE developer_id = ? AND is_active = 1");
                $stmt->execute([$developer_id]);
                $total_marketing = $stmt->fetchColumn();
                if ($total_marketing > 0) {
                    echo '<span class="badge">' . $total_marketing . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- KPI Marketing -->
        <a href="marketing_kpi.php" class="sidebar-item <?= $current_page == 'marketing_kpi.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i>
            <span>KPI Marketing</span>
        </a>
        <?php endif; ?>
        
        <!-- ===== MENU UNTUK MANAGER DEVELOPER ===== -->
        <?php if ($is_manager_developer): ?>
        <div class="sidebar-menu-title">MANAGER DEVELOPER</div>
        
        
        
        <!-- Tracking Komisi -->
        <a href="manager_developer_komisi.php" class="sidebar-item <?= $current_page == 'manager_developer_komisi.php' ? 'active' : '' ?>">
            <i class="fas fa-coins"></i>
            <span>Tracking Komisi</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM komisi_logs WHERE developer_id = ? AND status = 'pending'");
                $stmt->execute([$developer_id]);
                $pending = $stmt->fetchColumn();
                if ($pending > 0) {
                    echo '<span class="badge">' . $pending . '</span>';
                }
            }
            ?>
        </a>
        
      
        
        <!-- Kelola Marketing -->
        <a href="marketing_team.php" class="sidebar-item <?= $current_page == 'marketing_team.php' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i>
            <span>Kelola Marketing</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM marketing_team WHERE developer_id = ? AND is_active = 1");
                $stmt->execute([$developer_id]);
                $total = $stmt->fetchColumn();
                if ($total > 0) {
                    echo '<span class="badge">' . $total . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- KPI Marketing -->
        <a href="marketing_kpi.php" class="sidebar-item <?= $current_page == 'marketing_kpi.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i>
            <span>KPI Marketing</span>
        </a>
        
        <!-- Aktivitas Marketing -->
        <a href="marketing_activities.php" class="sidebar-item <?= $current_page == 'marketing_activities.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i>
            <span>Aktivitas Marketing</span>
        </a>
        
        <!-- Leaderboard -->
        <a href="marketing_leaderboard.php" class="sidebar-item <?= $current_page == 'marketing_leaderboard.php' ? 'active' : '' ?>">
            <i class="fas fa-trophy"></i>
            <span>Leaderboard</span>
        </a>
        
        <!-- Canvasing Dashboard -->
        <div class="sidebar-menu-title">MONITORING CANVASING</div>
        
        <a href="manager_developer_canvasing.php" class="sidebar-item <?= $current_page == 'manager_developer_canvasing.php' ? 'active' : '' ?>">
            <i class="fas fa-camera-retro"></i>
            <span>Canvasing Dashboard</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM canvasing_logs WHERE developer_id = ? AND DATE(created_at) = CURDATE()");
                $stmt->execute([$developer_id]);
                $today = $stmt->fetchColumn();
                if ($today > 0) {
                    echo '<span class="badge">' . $today . '</span>';
                }
            }
            ?>
        </a>
        
       
        <?php endif; ?>
        
        <!-- ===== MENU UNTUK FINANCE DEVELOPER ===== -->
        <?php if ($is_finance): ?>
        <div class="sidebar-menu-title">FINANCE DEVELOPER</div>
        
        <a href="finance_dashboard.php" class="sidebar-item <?= $current_page == 'finance_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i>
            <span>Dashboard Finance</span>
        </a>
        
        <!-- Untuk Finance Developer -->
<?php if ($is_finance): ?>
<a href="finance_booking_verifikasi.php" class="sidebar-item <?= $current_page == 'finance_booking_verifikasi.php' ? 'active' : '' ?>">
    <i class="fas fa-calendar-check"></i>
    <span>Verifikasi Booking</span>
    <?php
    if ($conn_sidebar && $developer_id > 0) {
        $stmt = $conn_sidebar->prepare("
            SELECT COUNT(*) FROM booking_logs bl
            JOIN units u ON bl.unit_id = u.id
            JOIN blocks b ON u.block_id = b.id
            JOIN clusters c ON b.cluster_id = c.id
            WHERE c.developer_id = ? AND bl.status_verifikasi = 'pending'
        ");
        $stmt->execute([$developer_id]);
        $pending = $stmt->fetchColumn();
        if ($pending > 0) {
            echo '<span class="badge">' . $pending . '</span>';
        }
    }
    ?>
</a>
<?php endif; ?>
        
        <!-- Komisi Pending -->
        <a href="finance_komisi.php" class="sidebar-item <?= $current_page == 'finance_komisi.php' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i>
            <span>Komisi Pending</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM komisi_logs WHERE developer_id = ? AND status = 'pending'");
                $stmt->execute([$developer_id]);
                $pending = $stmt->fetchColumn();
                if ($pending > 0) {
                    echo '<span class="badge">' . $pending . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Konfirmasi Pencairan -->
        <a href="finance_konfirmasi.php" class="sidebar-item <?= $current_page == 'finance_konfirmasi.php' ? 'active' : '' ?>">
            <i class="fas fa-check-double"></i>
            <span>Konfirmasi Cair</span>
        </a>
        
        <!-- Laporan Keuangan -->
        <a href="finance_laporan.php" class="sidebar-item <?= $current_page == 'finance_laporan.php' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice"></i>
            <span>Laporan Keuangan</span>
        </a>
        
        <!-- Rekening Marketing -->
        <a href="finance_rekening.php" class="sidebar-item <?= $current_page == 'finance_rekening.php' ? 'active' : '' ?>">
            <i class="fas fa-university"></i>
            <span>Rekening Marketing</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM marketing_team WHERE developer_id = ? AND rekening_verified = 0 AND nomor_rekening IS NOT NULL");
                $stmt->execute([$developer_id]);
                $unverified = $stmt->fetchColumn();
                if ($unverified > 0) {
                    echo '<span class="badge">' . $unverified . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Unit Terjual -->
        <a href="finance_units_sold.php" class="sidebar-item <?= $current_page == 'finance_units_sold.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Unit Terjual</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("
                    SELECT COUNT(*) FROM units u 
                    JOIN clusters c ON u.cluster_id = c.id 
                    WHERE c.developer_id = ? AND u.status = 'SOLD'
                ");
                $stmt->execute([$developer_id]);
                $sold = $stmt->fetchColumn();
                if ($sold > 0) {
                    echo '<span class="badge">' . $sold . '</span>';
                }
            }
            ?>
        </a>
        <?php endif; ?>
        
        <!-- ===== MENU MARKETING ===== -->
        <?php if ($is_marketing): ?>
        <div class="sidebar-menu-title">MARKETING</div>
        
        <!-- Dashboard -->
        <a href="marketing_dashboard.php" class="sidebar-item <?= $current_page == 'marketing_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <!-- Data Leads -->
        <a href="marketing_leads.php" class="sidebar-item <?= $current_page == 'marketing_leads.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Data Leads</span>
            <?php
            if ($conn_sidebar && $is_marketing) {
                $marketing_id = $_SESSION['marketing_id'];
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM leads WHERE assigned_marketing_team_id = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
                $stmt->execute([$marketing_id]);
                $total = $stmt->fetchColumn();
                if ($total > 0) {
                    echo '<span class="badge">' . ($total > 99 ? '99+' : $total) . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Booking Unit - BEDA UNTUK INTERNAL VS EXTERNAL -->
        <?php if ($is_marketing_external): ?>
        <a href="external_booking.php" class="sidebar-item <?= $current_page == 'external_booking.php' ? 'active' : '' ?>">
            <i class="fas fa-hand-holding-usd"></i>
            <span>Booking Unit</span>
        </a>
        <?php else: ?>
        <a href="marketing_booking.php" class="sidebar-item <?= $current_page == 'marketing_booking.php' ? 'active' : '' ?>">
            <i class="fas fa-hand-holding-usd"></i>
            <span>Booking Unit</span>
        </a>
        <?php endif; ?>
        
        <!-- Form Offline -->
        <a href="marketing_offline_form.php" class="sidebar-item <?= $current_page == 'marketing_offline_form.php' ? 'active' : '' ?>">
            <i class="fas fa-file-alt"></i>
            <span>Form Offline</span>
        </a>
        
        <!-- Leaderboard -->
        <a href="marketing_leaderboard.php" class="sidebar-item <?= $current_page == 'marketing_leaderboard.php' ? 'active' : '' ?>">
            <i class="fas fa-trophy"></i>
            <span>Leaderboard</span>
        </a>
        
        <!-- CANVASING (Hanya untuk internal) -->
        <?php if (!$is_marketing_external): ?>
        <a href="marketing_canvasing.php" class="sidebar-item <?= $current_page == 'marketing_canvasing.php' ? 'active' : '' ?>">
            <i class="fas fa-camera"></i>
            <span>Canvasing</span>
            <?php
            if ($conn_sidebar && $is_marketing) {
                $marketing_id = $_SESSION['marketing_id'];
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM canvasing_logs WHERE marketing_id = ? AND DATE(created_at) = CURDATE()");
                $stmt->execute([$marketing_id]);
                $today = $stmt->fetchColumn();
                if ($today > 0) {
                    echo '<span class="badge">' . $today . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Riwayat Canvasing -->
        <a href="marketing_canvasing_history.php" class="sidebar-item <?= $current_page == 'marketing_canvasing_history.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i>
            <span>Riwayat Canvasing</span>
        </a>
        <?php endif; ?>
        
       
        <!-- Rekening Pribadi -->
        <a href="marketing_rekening.php" class="sidebar-item <?= $current_page == 'marketing_rekening.php' ? 'active' : '' ?>">
            <i class="fas fa-university"></i>
            <span>Rekening Pribadi</span>
        </a>
        <?php endif; ?>
        
        <!-- ===== DATA & LEADS (UNTUK NON-MARKETING) ===== -->
        <?php if (!$is_marketing): ?>
        <div class="sidebar-menu-title">DATA & LEADS</div>
        
        <!-- Data Leads untuk Admin/Manager/Finance Platform -->
        <?php if ($is_admin || $is_manager || $is_finance_platform): ?>
        <a href="index.php?tab=leads" class="sidebar-item <?= $current_page == 'index.php' && $current_tab == 'leads' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Data Leads</span>
            <?php
            if ($conn_sidebar) {
                $sql = "SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'";
                if ($is_finance_platform) {
                    $sql .= " AND assigned_type = 'external'";
                }
                $stmt = $conn_sidebar->query($sql);
                $total_leads = $stmt->fetchColumn();
                if ($total_leads > 0) {
                    echo '<span class="badge">' . ($total_leads > 99 ? '99+' : $total_leads) . '</span>';
                }
            }
            ?>
        </a>
        <?php endif; ?>
        
        <!-- Data Leads untuk Developer (via dashboard) -->
        <?php if ($is_developer): ?>
        <a href="developer_dashboard.php" class="sidebar-item <?= $current_page == 'developer_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Data Leads</span>
        </a>
        <?php endif; ?>
        
        <!-- Data Leads untuk Manager Developer -->
        <?php if ($is_manager_developer): ?>
        <a href="manager_developer_dashboard.php" class="sidebar-item <?= $current_page == 'manager_developer_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Data Leads</span>
        </a>
        <?php endif; ?>
        
        <!-- KPI Marketing untuk Developer & Manager Developer -->
        <?php if ($is_developer || $is_manager_developer): ?>
        <a href="marketing_kpi.php" class="sidebar-item <?= $current_page == 'marketing_kpi.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i>
            <span>KPI Marketing</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM marketing_team WHERE developer_id = ? AND is_active = 1");
                $stmt->execute([$developer_id]);
                $total_marketing = $stmt->fetchColumn();
                if ($total_marketing > 0) {
                    echo '<span class="badge">' . $total_marketing . '</span>';
                }
            }
            ?>
        </a>
        <?php endif; ?>
        
        <!-- Unit Programs (untuk Admin & Developer) -->
        <?php if ($is_admin || $is_developer): ?>
        <a href="unit_programs.php" class="sidebar-item <?= $current_page == 'unit_programs.php' ? 'active' : '' ?>">
            <i class="fas fa-check-double"></i>
            <span>Unit Programs</span>
        </a>
        <?php endif; ?>
        
        <!-- Tracking Analytics -->
        <?php if ($is_admin || $is_manager || $is_developer || $is_manager_developer): ?>
        <a href="tracking_analytics.php" class="sidebar-item <?= $current_page == 'tracking_analytics.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Tracking Analytics</span>
        </a>
        <?php endif; ?>
        
        <!-- Export Premium -->
        <?php if ($is_admin || $is_manager || $is_finance_platform): ?>
        <a href="#" onclick="openPremiumExportModal(); return false;" class="sidebar-item">
            <i class="fas fa-download"></i>
            <span>Export Premium</span>
        </a>
        <?php endif; ?>
        
        <!-- Backup Database (Admin Only) -->
        <?php if ($is_admin): ?>
        <a href="backup.php" class="sidebar-item <?= $current_page == 'backup.php' ? 'active' : '' ?>">
            <i class="fas fa-database"></i>
            <span>Backup Database</span>
        </a>
        <?php endif; ?>
        <?php endif; ?><!-- end !isMarketing -->
        
        <!-- ===== MANAJEMEN ===== -->
        <?php if ($is_admin || $is_developer || $is_manager_developer || $is_finance_platform): ?>
        <div class="sidebar-menu-title">MANAJEMEN</div>
        
        <!-- Users (Admin Only) -->
        <?php if ($is_admin): ?>
        <a href="users.php" class="sidebar-item <?= $current_page == 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i>
            <span>Manajemen User</span>
        </a>
        <?php endif; ?>
        
        <!-- Tim Manajemen (UNTUK DEVELOPER) -->
        <?php if ($is_developer): ?>
        <a href="developer_team.php" class="sidebar-item <?= $current_page == 'developer_team.php' ? 'active' : '' ?>">
            <i class="fas fa-user-tie"></i>
            <span>Tim Manajemen</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM users WHERE developer_id = ? AND role IN ('manager_developer', 'finance') AND is_active = 1");
                $stmt->execute([$developer_id]);
                $total = $stmt->fetchColumn();
                if ($total > 0) {
                    echo '<span class="badge">' . $total . '</span>';
                }
            }
            ?>
        </a>
        <?php endif; ?>
        
        <!-- Marketing Team -->
        <?php if ($is_developer || $is_manager_developer): ?>
        <a href="marketing_team.php" class="sidebar-item <?= $current_page == 'marketing_team.php' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i>
            <span>Marketing Team</span>
            <?php
            if ($conn_sidebar && $developer_id > 0) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) FROM marketing_team WHERE developer_id = ? AND is_active = 1");
                $stmt->execute([$developer_id]);
                $total_marketing = $stmt->fetchColumn();
                if ($total_marketing > 0) {
                    echo '<span class="badge">' . $total_marketing . '</span>';
                }
            }
            ?>
        </a>
        <?php endif; ?>
        
        <!-- Marketing External Team -->
        <?php if ($is_admin || $is_finance_platform): ?>
        <a href="marketing_external_team.php" class="sidebar-item <?= $current_page == 'marketing_external_team.php' ? 'active' : '' ?>">
            <i class="fas fa-user-tie"></i>
            <span>Marketing External</span>
            <?php
            if ($conn_sidebar) {
                $stmt = $conn_sidebar->query("SELECT COUNT(*) FROM marketing_external_team WHERE is_active = 1");
                $total = $stmt->fetchColumn();
                if ($total > 0) {
                    echo '<span class="badge">' . $total . '</span>';
                }
            }
            ?>
        </a>
        <?php endif; ?>
        <?php endif; ?>
        
        <!-- ===== PENGATURAN (HANYA ADMIN) ===== -->
        <?php if ($is_admin): ?>
        <div class="sidebar-menu-title">PENGATURAN</div>
        
        <!-- Settings -->
        <a href="settings.php" class="sidebar-item <?= $current_page == 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i>
            <span>Pengaturan</span>
        </a>
        
        <!-- Lokasi Perumahan -->
        <a href="locations.php" class="sidebar-item <?= $current_page == 'locations.php' ? 'active' : '' ?>">
            <i class="fas fa-map-marker-alt"></i>
            <span>Lokasi Perumahan</span>
        </a>
        
        <!-- Template WhatsApp -->
        <a href="messages.php" class="sidebar-item <?= $current_page == 'messages.php' ? 'active' : '' ?>">
            <i class="fab fa-whatsapp"></i>
            <span>Template WhatsApp</span>
        </a>
        
        <!-- Template Email -->
        <a href="emails.php" class="sidebar-item <?= $current_page == 'emails.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i>
            <span>Template Email</span>
        </a>
        
        <!-- Tracking Pixel Config -->
        <a href="tracking_config.php" class="sidebar-item <?= $current_page == 'tracking_config.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Tracking Pixel</span>
        </a>
       
        <!-- SEO Developer -->
        <a href="select_developer_seo.php" class="sidebar-item <?= $current_page == 'select_developer_seo.php' ? 'active' : '' ?>">
            <i class="fas fa-code-branch" style="color: #E3B584;"></i>
            <span>SEO Developer</span>
            <?php
            // Hitung developer yang belum punya SEO
            $conn_sidebar = getDB();
            if ($conn_sidebar) {
                $stmt = $conn_sidebar->query("
                    SELECT COUNT(*) FROM users u 
                    LEFT JOIN developer_seo ds ON u.id = ds.developer_id 
                    WHERE u.role = 'developer' AND u.is_active = 1 AND ds.id IS NULL
                ");
                $pending_seo = $stmt->fetchColumn();
                if ($pending_seo > 0) {
                    echo '<span class="badge" style="background: #E3B584;">' . $pending_seo . '</span>';
                }
            }
            ?>
        </a>
        
        <!-- Settings External -->
        <a href="settings_external.php" class="sidebar-item <?= $current_page == 'settings_external.php' ? 'active' : '' ?>">
            <i class="fas fa-user-tie"></i>
            <span>Marketing External</span>
        </a>
        
        <!-- DEBUG CANVASING -->
        <a href="canvasing_debug.php" class="sidebar-item <?= $current_page == 'canvasing_debug.php' ? 'active' : '' ?>">
            <i class="fas fa-bug" style="color: #D64F3C;"></i>
            <span>Debug Canvasing</span>
            <span class="badge" style="background: #D64F3C;">Log</span>
        </a>
        
        <!-- HAPUS DATA CANVASING -->
        <a href="canvasing_delete.php" class="sidebar-item <?= $current_page == 'canvasing_delete.php' ? 'active' : '' ?>">
            <i class="fas fa-trash-alt" style="color: #D64F3C;"></i>
            <span>Hapus Data Canvasing</span>
            <span class="badge" style="background: #D64F3C;">Admin Only</span>
        </a>
        
        <!-- TEST PHONE VALIDATION -->
        <a href="test_phone.php" class="sidebar-item <?= $current_page == 'test_phone.php' ? 'active' : '' ?>">
            <i class="fas fa-phone-alt" style="color: #25D366;"></i>
            <span>Test Validasi Phone</span>
        </a>
        <?php endif; ?>
        
        <!-- ===== AKUN ===== -->
        <div class="sidebar-menu-title">AKUN</div>
        
        <!-- Profile (kecuali marketing) -->
        <?php if (!$is_marketing): ?>
        <a href="profile.php" class="sidebar-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i>
            <span>Profil Saya</span>
        </a>
        <?php endif; ?>
        
        <!-- Profile untuk marketing -->
        <?php if ($is_marketing): ?>
        <a href="profile.php" class="sidebar-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i>
            <span>Profil</span>
        </a>
        <?php endif; ?>
        
        <!-- Logout -->
        <a href="logout.php" class="sidebar-item" onclick="return confirm('Yakin ingin logout?')">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- ===== BOTTOM NAVIGATION - MOBILE ===== -->
<div class="bottom-nav">
    <div class="bottom-nav-container">
        
        <!-- MENU 1: HOME / DASHBOARD -->
        <?php
        $home_link = 'index.php';
        $home_active = false;
        
        if ($is_marketing) {
            $home_link = 'marketing_dashboard.php';
            $home_active = ($current_page == 'marketing_dashboard.php');
        } elseif ($is_developer) {
            $home_link = 'developer_dashboard.php';
            $home_active = ($current_page == 'developer_dashboard.php');
        } elseif ($is_manager_developer) {
            $home_link = 'manager_developer_dashboard.php';
            $home_active = ($current_page == 'manager_developer_dashboard.php');
        } elseif ($is_finance) {
            $home_link = 'finance_dashboard.php';
            $home_active = ($current_page == 'finance_dashboard.php');
        } elseif ($is_finance_platform) {
            $home_link = 'finance_platform_dashboard.php';
            $home_active = ($current_page == 'finance_platform_dashboard.php');
        } elseif ($is_manager) {
            $home_link = 'manager_dashboard.php';
            $home_active = ($current_page == 'manager_dashboard.php');
        } elseif ($is_admin) {
            $home_link = 'index.php';
            $home_active = ($current_page == 'index.php');
        }
        ?>
        <a href="<?= $home_link ?>" class="bottom-nav-item <?= $home_active ? 'active' : '' ?>">
            <div class="bottom-nav-icon">
                <i class="fas fa-home"></i>
            </div>
            <span>Home</span>
        </a>
        
        <!-- MENU 2: LEADS / DATA -->
        <?php
        $leads_link = '#';
        $leads_active = false;
        
        if ($is_marketing) {
            $leads_link = 'marketing_leads.php';
            $leads_active = ($current_page == 'marketing_leads.php');
        } elseif ($is_developer) {
            $leads_link = 'developer_dashboard.php';
            $leads_active = ($current_page == 'developer_dashboard.php');
        } elseif ($is_manager_developer) {
            $leads_link = 'manager_developer_dashboard.php';
            $leads_active = ($current_page == 'manager_developer_dashboard.php');
        } elseif ($is_finance) {
            $leads_link = 'manager_developer_booking.php';
            $leads_active = ($current_page == 'manager_developer_booking.php');
        } elseif ($is_finance_platform) {
            $leads_link = 'finance_platform_verifikasi.php';
            $leads_active = ($current_page == 'finance_platform_verifikasi.php');
        } elseif ($is_manager || $is_admin) {
            $leads_link = 'index.php?tab=leads';
            $leads_active = ($current_tab == 'leads');
        }
        ?>
        <a href="<?= $leads_link ?>" class="bottom-nav-item <?= $leads_active ? 'active' : '' ?>">
            <div class="bottom-nav-icon">
                <i class="fas fa-users"></i>
            </div>
            <span>Leads</span>
            <span class="badge-dot" id="mobileNotificationDot" style="display: none;"></span>
        </a>
        
        <!-- FAB - QUICK ACTIONS -->
        <div class="bottom-nav-center">
            <div class="bottom-nav-fab" onclick="toggleQuickActions()">
                <i class="fas fa-plus"></i>
            </div>
            <span class="fab-label">Menu</span>
        </div>
        
        <!-- MENU 3: KHUSUS PER ROLE (SERAGAM) -->
        <?php if ($is_admin): ?>
            <!-- ADMIN: Tools -->
            <div class="bottom-nav-item" onclick="showAdminToolsMenu()">
                <div class="bottom-nav-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <span>Tools</span>
            </div>
           
        <?php elseif ($is_manager): ?>
            <!-- MANAGER: KPI -->
            <a href="manager_kpi.php" class="bottom-nav-item <?= $current_page == 'manager_kpi.php' ? 'active' : '' ?>">
                <div class="bottom-nav-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span>KPI</span>
            </a>
            
        <?php elseif ($is_marketing): ?>
            <!-- MARKETING: CANVASING (internal) atau BOOKING (external) -->
            <?php if ($is_marketing_external): ?>
            <a href="external_booking.php" class="bottom-nav-item <?= $current_page == 'external_booking.php' ? 'active' : '' ?>">
                <div class="bottom-nav-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <span>Booking</span>
            </a>
            <?php else: ?>
            <a href="marketing_canvasing.php" class="bottom-nav-item <?= $current_page == 'marketing_canvasing.php' ? 'active' : '' ?>">
                <div class="bottom-nav-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <span>Canvasing</span>
            </a>
            <?php endif; ?>
            
        <?php elseif ($is_developer): ?>
            <!-- DEVELOPER: Canvasing -->
            <a href="developer_canvasing_dashboard.php" class="bottom-nav-item <?= $current_page == 'developer_canvasing_dashboard.php' ? 'active' : '' ?>">
                <div class="bottom-nav-icon">
                    <i class="fas fa-camera-retro"></i>
                </div>
                <span>Canvasing</span>
            </a>
            
        <?php elseif ($is_manager_developer): ?>
            <!-- MANAGER DEVELOPER: Verifikasi -->
            <a href="manager_developer_booking.php" class="bottom-nav-item <?= $current_page == 'manager_developer_booking.php' ? 'active' : '' ?>">
                <div class="bottom-nav-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <span>Verifikasi</span>
            </a>
            
        <?php elseif ($is_finance): ?>
            <!-- FINANCE DEVELOPER: Komisi -->
            <a href="finance_komisi.php" class="bottom-nav-item <?= $current_page == 'finance_komisi.php' ? 'active' : '' ?>">
                <div class="bottom-nav-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <span>Komisi</span>
            </a>
            
        <?php elseif ($is_finance_platform): ?>
            <!-- FINANCE PLATFORM: Verifikasi -->
            <a href="finance_platform_verifikasi.php" class="bottom-nav-item <?= $current_page == 'finance_platform_verifikasi.php' ? 'active' : '' ?>">
                <div class="bottom-nav-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <span>Verifikasi</span>
            </a>
            
        <?php else: ?>
            <!-- DEFAULT -->
            <a href="#" class="bottom-nav-item" style="opacity: 0.5; pointer-events: none;">
                <div class="bottom-nav-icon">
                    <i class="fas fa-download"></i>
                </div>
                <span>Export</span>
            </a>
        <?php endif; ?>
        
        <!-- MENU 4: LOGOUT -->
        <a href="logout.php" class="bottom-nav-item" onclick="return confirm('Yakin ingin logout?')">
            <div class="bottom-nav-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <span>Logout</span>
        </a>
        
    </div>
</div>

<!-- MODAL ADMIN TOOLS (UNTUK BOTTOM NAV) -->
<div class="modal" id="adminToolsModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2><i class="fas fa-tools"></i> Admin Tools</h2>
            <button class="modal-close" onclick="closeModal('adminToolsModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="canvasing_debug.php" class="btn-primary" style="text-decoration: none; text-align: center;">
                    <i class="fas fa-bug"></i> Debug Canvasing
                </a>
                <a href="canvasing_delete.php" class="btn-primary" style="text-decoration: none; text-align: center; background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-trash-alt"></i> Hapus Data Canvasing
                </a>
                <a href="test_phone.php" class="btn-primary" style="text-decoration: none; text-align: center; background: linear-gradient(135deg, #25D366, #128C7E);">
                    <i class="fas fa-phone-alt"></i> Test Validasi Phone
                </a>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('adminToolsModal')">Tutup</button>
        </div>
    </div>
</div>

<!-- ===== QUICK ACTIONS MENU (MOBILE) - UI SERAGAM UNTUK SEMUA ROLE ===== -->
<div class="quick-actions" id="quickActions">
    <div class="quick-actions-overlay" onclick="toggleQuickActions()"></div>
    <div class="quick-actions-menu">
        <div class="quick-actions-header">
            <h3><i class="fas fa-bolt"></i> Menu Cepat</h3>
            <button class="quick-actions-close" onclick="toggleQuickActions()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="quick-actions-grid">
            
            <!-- ===== SUPER ADMIN ===== -->
            <?php if ($is_admin): ?>
            <a href="index.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span>Dashboard</span>
            </a>
            
            <a href="users.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-users-cog"></i>
                </div>
                <span>User</span>
            </a>
            
            <a href="locations.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <span>Lokasi</span>
            </a>
            
            <a href="messages.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #25D366, #128C7E);">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <span>WhatsApp</span>
            </a>
            
            <a href="tracking_config.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1877F2, #0D65D9);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span>Tracking</span>
            </a>
            
            <a href="unit_programs.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-check-double"></i>
                </div>
                <span>Program</span>
            </a>
            
            <!-- SEO Developer -->
            <a href="select_developer_seo.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #E3B584, #D4A373);">
                    <i class="fas fa-code-branch"></i>
                </div>
                <span>SEO</span>
                <?php
                $conn_sidebar = getDB();
                if ($conn_sidebar) {
                    $stmt = $conn_sidebar->query("
                        SELECT COUNT(*) FROM users u 
                        LEFT JOIN developer_seo ds ON u.id = ds.developer_id 
                        WHERE u.role = 'developer' AND u.is_active = 1 AND ds.id IS NULL
                    ");
                    $pending_seo = $stmt->fetchColumn();
                    if ($pending_seo > 0) {
                        echo '<span class="badge" style="position: absolute; top: 5px; right: 5px; background: #E3B584; color: #000; font-size: 9px; padding: 2px 4px; border-radius: 10px;">' . $pending_seo . '</span>';
                    }
                }
                ?>
            </a>
            
            <a href="canvasing_debug.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-bug"></i>
                </div>
                <span>Debug</span>
            </a>
            
            <a href="canvasing_delete.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <span>Hapus</span>
            </a>
            
            <a href="test_phone.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #25D366, #128C7E);">
                    <i class="fas fa-phone-alt"></i>
                </div>
                <span>Test Phone</span>
            </a>
            
            <a href="backup.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-database"></i>
                </div>
                <span>Backup</span>
            </a>
            
            <a href="settings.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #E9C46A, #F0D48C);">
                    <i class="fas fa-cog"></i>
                </div>
                <span>Settings</span>
            </a>
            
            <a href="profile.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-user-circle"></i>
                </div>
                <span>Profil</span>
            </a>
            
            <a href="logout.php" class="quick-action-item" onclick="return confirm('Yakin ingin logout?')">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span>Logout</span>
            </a>
            <?php endif; ?>
            
            <!-- ===== MANAGER PLATFORM ===== -->
            <?php if ($is_manager): ?>
            <a href="manager_dashboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <span>Dashboard</span>
            </a>
            
            <a href="manager_kpi.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span>KPI</span>
            </a>
            
            <a href="manager_top_performer.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #FFD700, #FFA500);">
                    <i class="fas fa-crown"></i>
                </div>
                <span>Top</span>
            </a>
            
            <a href="manager_activities.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-history"></i>
                </div>
                <span>Aktivitas</span>
            </a>
            
            <a href="manager_canvasing_dashboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-camera-retro"></i>
                </div>
                <span>Canvasing</span>
            </a>
            
            <a href="index.php?tab=leads" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-users"></i>
                </div>
                <span>Leads</span>
            </a>
            
            <a href="tracking_analytics.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1877F2, #0D65D9);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span>Tracking</span>
            </a>
            
            <a href="#" onclick="openPremiumExportModal(); toggleQuickActions(); return false;" class="quick-action-item">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-download"></i>
                </div>
                <span>Export</span>
            </a>
            
            <a href="profile.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-user-circle"></i>
                </div>
                <span>Profil</span>
            </a>
            
            <a href="logout.php" class="quick-action-item" onclick="return confirm('Yakin ingin logout?')">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span>Logout</span>
            </a>
            <?php endif; ?>
            
            <!-- ===== DEVELOPER ===== -->
            <?php if ($is_developer): ?>
            <a href="developer_dashboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #E9C46A, #F0D48C);">
                    <i class="fas fa-code-branch"></i>
                </div>
                <span>Dashboard</span>
            </a>
            
            <a href="developer_clusters.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-layer-group"></i>
                </div>
                <span>Cluster</span>
            </a>
            
           
            
            <a href="developer_program_booking.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #E9C46A, #F0D48C);">
                    <i class="fas fa-tags"></i>
                </div>
                <span>Program</span>
            </a>
            
            <a href="developer_biaya_kategori.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-coins"></i>
                </div>
                <span>Master Biaya</span>
            </a>
            
            <a href="developer_block_biaya.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-cubes"></i>
                </div>
                <span>Biaya Block</span>
            </a>
            
            <a href="developer_banks.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-university"></i>
                </div>
                <span>Rekening</span>
            </a>
            
            <a href="developer_canvasing_dashboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-camera-retro"></i>
                </div>
                <span>Canvasing</span>
            </a>
            
            <a href="developer_team.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-user-tie"></i>
                </div>
                <span>Tim</span>
            </a>
            
            <a href="marketing_team.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-users-cog"></i>
                </div>
                <span>Marketing</span>
            </a>
            
            <a href="marketing_kpi.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span>KPI</span>
            </a>
            
            <a href="unit_programs.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-check-double"></i>
                </div>
                <span>Unit Program</span>
            </a>
            
            <a href="developer_tracking.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1877F2, #0D65D9);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span>Tracking</span>
            </a>
            
            <a href="profile.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-user-circle"></i>
                </div>
                <span>Profil</span>
            </a>
            
            <a href="logout.php" class="quick-action-item" onclick="return confirm('Yakin ingin logout?')">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span>Logout</span>
            </a>
            <?php endif; ?>
            
            <!-- ===== MANAGER DEVELOPER ===== -->
            <?php if ($is_manager_developer): ?>
            <a href="manager_developer_dashboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span>Dashboard</span>
            </a>
            
            <a href="manager_developer_komisi.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #E9C46A, #F0D48C);">
                    <i class="fas fa-coins"></i>
                </div>
                <span>Komisi</span>
            </a>
            
            
            
            <a href="marketing_team.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-users-cog"></i>
                </div>
                <span>Marketing</span>
            </a>
            
            <a href="marketing_kpi.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span>KPI</span>
            </a>
            
            <a href="marketing_activities.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-history"></i>
                </div>
                <span>Aktivitas</span>
            </a>
            
            <a href="marketing_leaderboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #FFD700, #FFA500);">
                    <i class="fas fa-trophy"></i>
                </div>
                <span>Leaderboard</span>
            </a>
            
            <a href="manager_developer_canvasing.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-camera-retro"></i>
                </div>
                <span>Canvasing</span>
            </a>
            
           
            
            <a href="profile.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-user-circle"></i>
                </div>
                <span>Profil</span>
            </a>
            
            <a href="logout.php" class="quick-action-item" onclick="return confirm('Yakin ingin logout?')">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span>Logout</span>
            </a>
            <?php endif; ?>
            
            <!-- ===== FINANCE DEVELOPER ===== -->
            <?php if ($is_finance): ?>
            <a href="finance_dashboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <span>Dashboard</span>
            </a>
            
            <a href="manager_developer_booking.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <span>Verifikasi</span>
            </a>
            
            <a href="finance_komisi.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #E9C46A, #F0D48C);">
                    <i class="fas fa-coins"></i>
                </div>
                <span>Komisi</span>
            </a>
            
            <a href="finance_rekening.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-university"></i>
                </div>
                <span>Rekening</span>
            </a>
            
            <a href="finance_laporan.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <span>Laporan</span>
            </a>
            
            <a href="finance_units_sold.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-home"></i>
                </div>
                <span>Unit Terjual</span>
            </a>
            
            <a href="profile.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-user-circle"></i>
                </div>
                <span>Profil</span>
            </a>
            
            <a href="logout.php" class="quick-action-item" onclick="return confirm('Yakin ingin logout?')">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span>Logout</span>
            </a>
            <?php endif; ?>
            
            <!-- ===== FINANCE PLATFORM ===== -->
            <?php if ($is_finance_platform): ?>
            <a href="finance_platform_dashboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <span>Dashboard</span>
            </a>
            
            <a href="finance_platform_verifikasi.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <span>Verifikasi</span>
            </a>
            
            <a href="finance_platform_komisi.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #E9C46A, #F0D48C);">
                    <i class="fas fa-coins"></i>
                </div>
                <span>Komisi</span>
            </a>
            
            <a href="finance_platform_rekening.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-university"></i>
                </div>
                <span>Rekening</span>
            </a>
            
            <a href="finance_platform_laporan.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <span>Laporan</span>
            </a>
            
            <a href="finance_platform_external.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-user-tie"></i>
                </div>
                <span>Marketing</span>
            </a>
            
            <a href="external_booking.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #E9C46A, #F0D48C);">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <span>Booking</span>
            </a>
            
            <a href="index.php?tab=leads" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-users"></i>
                </div>
                <span>Leads</span>
            </a>
            
            <a href="#" onclick="openPremiumExportModal(); toggleQuickActions(); return false;" class="quick-action-item">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-download"></i>
                </div>
                <span>Export</span>
            </a>
            
            <a href="profile.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-user-circle"></i>
                </div>
                <span>Profil</span>
            </a>
            
            <a href="logout.php" class="quick-action-item" onclick="return confirm('Yakin ingin logout?')">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span>Logout</span>
            </a>
            <?php endif; ?>
            
            <!-- ===== MARKETING INTERNAL ===== -->
            <?php if ($is_marketing && !$is_marketing_external): ?>
            <a href="marketing_dashboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-user-tie"></i>
                </div>
                <span>Dashboard</span>
            </a>
            
            <a href="marketing_leads.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-users"></i>
                </div>
                <span>Leads</span>
            </a>
            
            <a href="marketing_booking.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <span>Booking</span>
            </a>
            
            <a href="marketing_offline_form.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-file-alt"></i>
                </div>
                <span>Form</span>
            </a>
            
            <a href="marketing_canvasing.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-camera"></i>
                </div>
                <span>Canvasing</span>
            </a>
            
            <a href="marketing_canvasing_history.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-history"></i>
                </div>
                <span>Riwayat</span>
            </a>
            
            
            
            <a href="marketing_kpi.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span>KPI</span>
            </a>
            
            <a href="marketing_leaderboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #FFD700, #FFA500);">
                    <i class="fas fa-trophy"></i>
                </div>
                <span>Leaderboard</span>
            </a>
            
            <a href="marketing_rekening.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-university"></i>
                </div>
                <span>Rekening</span>
            </a>
            
            <a href="profile.php" class="sidebar-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i>
            <span>Profil</span>
        </a>
            
            <a href="logout.php" class="quick-action-item" onclick="return confirm('Yakin ingin logout?')">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span>Logout</span>
            </a>
            <?php endif; ?>
            
            <!-- ===== MARKETING EXTERNAL ===== -->
            <?php if ($is_marketing && $is_marketing_external): ?>
            <a href="marketing_dashboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
                    <i class="fas fa-user-tie"></i>
                </div>
                <span>Dashboard</span>
            </a>
            
            <a href="marketing_leads.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-users"></i>
                </div>
                <span>Leads</span>
            </a>
            
            <a href="external_booking.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <span>Booking</span>
            </a>
            
           
            
            <a href="marketing_kpi.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #4A90E2, #6DA5F0);">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span>KPI</span>
            </a>
            
            <a href="marketing_leaderboard.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #FFD700, #FFA500);">
                    <i class="fas fa-trophy"></i>
                </div>
                <span>Leaderboard</span>
            </a>
            
            <a href="marketing_rekening.php" class="quick-action-item" onclick="toggleQuickActions()">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B4A3C, #2A5F4E);">
                    <i class="fas fa-university"></i>
                </div>
                <span>Rekening</span>
            </a>
            
            <a href="logout.php" class="quick-action-item" onclick="return confirm('Yakin ingin logout?')">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span>Logout</span>
            </a>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- MODAL UPLOAD FOTO PROFIL -->
<div class="modal" id="profilePhotoModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h2><i class="fas fa-camera"></i> Ganti Foto Profil</h2>
            <button class="modal-close" onclick="closeProfilePhotoModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <!-- Preview foto -->
            <div class="upload-preview">
                <img id="profilePreview" src="<?= !empty($user_photo) && file_exists(dirname(__DIR__) . '/uploads/profiles/' . $user_photo) ? '/admin/uploads/profiles/' . htmlspecialchars($user_photo) . '?t=' . time() : '' ?>" style="display: <?= !empty($user_photo) ? 'block' : 'none' ?>;">
                <div id="profilePreviewPlaceholder" class="upload-preview-placeholder" style="display: <?= empty($user_photo) ? 'flex' : 'none' ?>;">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
            </div>
            
            <p class="upload-info">
                Format: JPG, PNG, GIF, WEBP. Maksimal 5MB.
            </p>
            
            <form id="profileUploadForm" enctype="multipart/form-data">
                <div class="upload-area" onclick="document.getElementById('profileFile').click()">
                    <input type="file" id="profileFile" name="profile_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" style="display: none;" onchange="previewProfilePhoto(this)">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p class="upload-text">Klik untuk memilih file</p>
                    <p class="upload-filename" id="selectedFileName">Belum ada file dipilih</p>
                </div>
                
                <div class="upload-progress" id="uploadProgress">
                    <div class="progress-bar" id="uploadProgressBar"></div>
                </div>
                
                <button type="submit" class="btn-primary btn-block" id="uploadBtn">
                    <i class="fas fa-upload"></i> Upload Foto
                </button>
            </form>
        </div>
    </div>
</div>

<style>
/* ===== SIDEBAR PROFILE STYLES ===== */
.sidebar-profile {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
}

.sidebar-profile:hover {
    background: rgba(255, 255, 255, 0.05);
}

.profile-photo-wrapper {
    position: relative;
    width: 50px;
    height: 50px;
    flex-shrink: 0;
}

.profile-photo-wrapper img {
    width: 100%;
    height: 100%;
    border-radius: 16px;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.profile-photo-wrapper:hover img {
    border-color: var(--secondary);
    transform: scale(1.05);
}

.profile-initials-sidebar {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #D64F3C, #FF8A5C);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
    font-weight: 700;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.profile-photo-upload-btn {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 22px;
    height: 22px;
    background: white;
    border: none;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1B4A3C;
    font-size: 12px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    opacity: 0;
    transform: scale(0.8);
    transition: all 0.2s ease;
    border: 2px solid white;
    z-index: 2;
}

.profile-photo-wrapper:hover .profile-photo-upload-btn {
    opacity: 1;
    transform: scale(1);
}

.profile-photo-upload-btn:hover {
    background: var(--secondary);
    color: white;
}

.profile-info {
    flex: 1;
    min-width: 0;
}

.profile-name-sidebar {
    font-weight: 700;
    color: white;
    font-size: 15px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.profile-role-sidebar {
    font-size: 11px;
    color: var(--accent);
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Upload Modal Styles */
.upload-preview {
    width: 120px;
    height: 120px;
    margin: 0 auto 20px;
    border-radius: 20px;
    overflow: hidden;
    border: 3px solid #D64F3C;
}

.upload-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.upload-preview-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #1B4A3C, #2A5F4E);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 48px;
    font-weight: 700;
}

.upload-info {
    text-align: center;
    color: #4A5A54;
    font-size: 13px;
    margin-bottom: 20px;
}

.upload-area {
    border: 2px dashed #E0DAD3;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
    background: #F5F3F0;
    cursor: pointer;
    text-align: center;
}

.upload-area i {
    font-size: 40px;
    color: #D64F3C;
    margin-bottom: 10px;
}

.upload-text {
    color: #1B4A3C;
    font-weight: 600;
    margin-bottom: 5px;
}

.upload-filename {
    color: #7A8A84;
    font-size: 12px;
}

.upload-progress {
    height: 8px;
    background: #E0DAD3;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 20px;
    display: none;
}

.upload-progress .progress-bar {
    width: 0%;
    height: 100%;
    background: linear-gradient(90deg, #D64F3C, #FF8A5C);
    transition: width 0.3s;
}

.btn-block {
    width: 100%;
    padding: 16px !important;
    font-size: 16px !important;
}

/* Quick Actions - UI Seragam */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.quick-action-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    gap: 6px;
    padding: 12px 6px;
    background: #F8F9FA;
    border-radius: 14px;
    transition: all 0.2s;
    color: #1B4A3C;
    position: relative;
    font-size: 11px;
}

.quick-action-item:active {
    transform: scale(0.95);
    background: #E7F3EF;
}

.quick-action-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.quick-action-item span {
    font-weight: 600;
    text-align: center;
    line-height: 1.3;
}

/* Untuk mobile */
@media (max-width: 768px) {
    .profile-photo-upload-btn {
        width: 28px;
        height: 28px;
        font-size: 13px;
        bottom: -1px;
        right: -1px;
    }
}
</style>

<script>
// ===== FUNGSI UNTUK ADMIN TOOLS =====
function showAdminToolsMenu() {
    openModal('adminToolsModal');
}

// ===== PROFILE PHOTO FUNCTIONS =====
function openProfilePhotoModal() {
    openModal('profilePhotoModal');
}

function closeProfilePhotoModal() {
    closeModal('profilePhotoModal');
    document.getElementById('profileFile').value = '';
    document.getElementById('selectedFileName').textContent = 'Belum ada file dipilih';
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('uploadProgressBar').style.width = '0%';
}

function previewProfilePhoto(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('selectedFileName').textContent = file.name;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
            document.getElementById('profilePreview').style.display = 'block';
            document.getElementById('profilePreviewPlaceholder').style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
}

document.getElementById('profileUploadForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('profileFile');
    if (!fileInput.files || !fileInput.files[0]) {
        showToast('❌ Pilih file terlebih dahulu', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('profile_photo', fileInput.files[0]);
    
    const uploadBtn = document.getElementById('uploadBtn');
    const originalText = uploadBtn.innerHTML;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    uploadBtn.disabled = true;
    
    document.getElementById('uploadProgress').style.display = 'block';
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/admin/api/upload_profile.php', true);
    
    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            const percent = (e.loaded / e.total) * 100;
            document.getElementById('uploadProgressBar').style.width = percent + '%';
        }
    };
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    showToast('✅ ' + response.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('❌ ' + response.message, 'error');
                }
            } catch (e) {
                showToast('❌ Error parsing response', 'error');
            }
        } else {
            showToast('❌ Upload failed: ' + xhr.status, 'error');
        }
        
        uploadBtn.innerHTML = originalText;
        uploadBtn.disabled = false;
    };
    
    xhr.onerror = function() {
        showToast('❌ Network error', 'error');
        uploadBtn.innerHTML = originalText;
        uploadBtn.disabled = false;
    };
    
    xhr.send(formData);
});

// ===== QUICK ACTIONS TOGGLE =====
function toggleQuickActions() {
    const qa = document.getElementById('quickActions');
    qa.classList.toggle('show');
}

// ===== EXPORT MODAL =====
function openPremiumExportModal() {
    <?php if ($is_admin || $is_manager || $is_finance_platform): ?>
    if (typeof window.openExportModal === 'function') {
        window.openExportModal();
    } else if (typeof window.openPremiumExportModalFromSidebar === 'function') {
        window.openPremiumExportModalFromSidebar();
    } else {
        window.location.href = 'export_modal.php';
    }
    <?php else: ?>
    showToast('⚠️ Fitur export hanya untuk Admin, Manager, dan Finance Platform', 'error');
    <?php endif; ?>
}

// ===== TOAST FUNCTION =====
function showToast(message, type = 'info') {
    let toast = document.querySelector('.toast-message');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-message';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.style.opacity = '1';
    toast.style.background = type === 'success' ? '#2A9D8F' : (type === 'error' ? '#D64F3C' : '#1B4A3C');
    
    setTimeout(() => {
        toast.style.opacity = '0';
    }, 3000);
}

// ===== MODAL FUNCTIONS =====
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = '';
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// Update notification badge di mobile
setInterval(() => {
    fetch('/admin/api/get_unread_count.php?key=taufikmarie7878')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.count > 0) {
                const dot = document.getElementById('mobileNotificationDot');
                if (dot) dot.style.display = 'block';
            } else {
                const dot = document.getElementById('mobileNotificationDot');
                if (dot) dot.style.display = 'none';
            }
        })
        .catch(e => console.warn(e));
}, 30000);
</script>
<?php
// Pastikan tidak ada output tambahan setelah ini
?>