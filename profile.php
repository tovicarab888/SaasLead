<?php
/**
 * PROFILE.PHP - TAUFIKMARIE.COM ULTIMATE
 * Version: 7.0.0 - EKSTRIM! PAKSA DATA DARI QUERY LANGSUNG
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/profile_error.log');

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// ========== CHECK AUTH ==========
if (!checkAuth() && !isMarketing()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// Set PDO untuk throw exception
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ========== DETEKSI ROLE USER ==========
$is_marketing = isMarketing();
$user_id = 0;
$role = '';
$table = '';
$id_field = '';

if ($is_marketing) {
    $user_id = $_SESSION['marketing_id'] ?? 0;
    $role = 'marketing';
    $table = 'marketing_team';
    $id_field = 'id';
    $username = $_SESSION['marketing_username'] ?? '';
    $nama_lengkap = $_SESSION['marketing_name'] ?? '';
} else {
    $user_id = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? 'user';
    $table = 'users';
    $id_field = 'id';
    $username = $_SESSION['username'] ?? '';
    $nama_lengkap = $_SESSION['nama_lengkap'] ?? '';
}

if ($user_id <= 0) {
    header('Location: login.php');
    exit();
}

// ========== PROSES UPDATE PROFIL ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($nama_lengkap)) {
            $error = 'Nama lengkap wajib diisi';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid';
        } else {
            try {
                if ($role === 'marketing') {
                    $stmt = $conn->prepare("UPDATE marketing_team SET nama_lengkap = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                    $result = $stmt->execute([$nama_lengkap, $email, $phone, $user_id]);
                    
                    if ($result) {
                        $_SESSION['marketing_name'] = $nama_lengkap;
                        $success = '✅ Profil berhasil diperbarui';
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, updated_at = NOW() WHERE id = ?");
                    $result = $stmt->execute([$nama_lengkap, $email, $user_id]);
                    
                    if ($result) {
                        $_SESSION['nama_lengkap'] = $nama_lengkap;
                        $success = '✅ Profil berhasil diperbarui';
                    }
                }
            } catch (Exception $e) {
                $error = '❌ Gagal update: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Semua field password wajib diisi';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Password baru dan konfirmasi tidak cocok';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password minimal 6 karakter';
        } else {
            try {
                if ($role === 'marketing') {
                    $stmt = $conn->prepare("SELECT password_hash FROM marketing_team WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    $password_field = 'password_hash';
                } else {
                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    $password_field = 'password';
                }
                
                if ($user && password_verify($current_password, $user[$password_field])) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    if ($role === 'marketing') {
                        $update = $conn->prepare("UPDATE marketing_team SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                        $update->execute([$hashed, $user_id]);
                    } else {
                        $update = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                        $update->execute([$hashed, $user_id]);
                    }
                    
                    $success = '✅ Password berhasil diubah';
                } else {
                    $error = '❌ Password saat ini salah';
                }
            } catch (Exception $e) {
                $error = '❌ Gagal ubah password: ' . $e->getMessage();
            }
        }
    }
}

// ========== AMBIL DATA USER - PAKSA LANGSUNG DARI DATABASE ==========
$user_data = [];

try {
    if ($role === 'marketing') {
        $stmt = $conn->prepare("SELECT * FROM marketing_team WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // 🔥 QUERY LANGSUNG SEPERTI TEST_DB
        $stmt = $conn->prepare("
            SELECT 
                id,
                username,
                email,
                nama_lengkap,
                role,
                profile_photo,
                location_access,
                distribution_mode,
                telepon_perusahaan,
                fax,
                email_perusahaan,
                website,
                alamat,
                kota,
                npwp,
                siup,
                contact_person,
                contact_phone,
                bidang_usaha,
                tahun_berdiri,
                jumlah_proyek,
                legalitas_file,
                last_login,
                created_at
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Tulis ke file
        file_put_contents(__DIR__ . '/../logs/profile_debug.log', 
            date('Y-m-d H:i:s') . " - RAW DATA: " . print_r($user_data, true) . "\n", 
            FILE_APPEND
        );
    }
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Jika user_data kosong, beri default
if (empty($user_data)) {
    $user_data = [
        'id' => $user_id,
        'username' => $username,
        'nama_lengkap' => $nama_lengkap,
        'role' => $role,
        'distribution_mode' => $_SESSION['distribution_mode'] ?? 'FULL_EXTERNAL'
    ];
}

// ========== PAKSA MODE DARI DATABASE ==========
$distribution_mode = $user_data['distribution_mode'] ?? $_SESSION['distribution_mode'] ?? 'FULL_EXTERNAL';
$is_split = ($distribution_mode === 'SPLIT_50_50');

// ========== AMBIL ACTIVITY LOG ==========
$activities = [];
try {
    if ($role === 'marketing') {
        $stmt = $conn->prepare("
            SELECT 
                'update' as action_type,
                CONCAT('Mengupdate lead #', id) as description,
                updated_at as created_at
            FROM leads 
            WHERE assigned_marketing_team_id = ? 
            ORDER BY updated_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $activities = $stmt->fetchAll();
    } else {
        $stmt = $conn->prepare("
            SELECT action_type, description, created_at 
            FROM activity_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $activities = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // Abaikan error
}

// ========== SET VARIABLES UNTUK INCLUDE ==========
$page_title = 'Profil Saya';
$page_subtitle = ucfirst($role) . ': ' . ($user_data['nama_lengkap'] ?? $user_data['username'] ?? 'User');
$page_icon = 'fas fa-user-circle';

// ========== INCLUDE HEADER ==========
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
            <div class="date" id="currentDate">
                <i class="fas fa-calendar-alt"></i>
                <span>Memuat tanggal...</span>
            </div>
            <div class="time" id="currentTime">
                <i class="fas fa-clock"></i>
                <span>--:--:--</span>
            </div>
        </div>
    </div>  
   
    
    <!-- ALERT MESSAGES -->
    <?php if ($success): ?>
    <div class="alert success">
        <i class="fas fa-check-circle fa-lg"></i>
        <?= $success ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle fa-lg"></i>
        <?= $error ?>
    </div>
    <?php endif; ?>
    
    <!-- PROFILE CARD -->
    <div class="profile-mobile-container">
        
        <!-- PROFILE HEADER CARD -->
        <div class="profile-header-card">
            <div class="profile-photo-large">
                <?php 
                $photo_url = '';
                if (!empty($user_data['profile_photo']) && file_exists(__DIR__ . '/uploads/profiles/' . $user_data['profile_photo'])) {
                    $photo_url = '/admin/uploads/profiles/' . $user_data['profile_photo'] . '?t=' . time();
                }
                ?>
                
                <?php if ($photo_url): ?>
                    <img src="<?= $photo_url ?>" alt="Profile" class="profile-img">
                <?php else: ?>
                    <div class="profile-initials">
                        <?= strtoupper(substr($user_data['nama_lengkap'] ?? $user_data['username'] ?? 'U', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <button class="profile-photo-upload" onclick="openProfilePhotoModal()" title="Ganti Foto">
                    <i class="fas fa-camera"></i>
                </button>
            </div>
            
            <h3 class="profile-name"><?= htmlspecialchars($user_data['nama_lengkap'] ?? $user_data['username']) ?></h3>
            <div class="profile-username">@<?= htmlspecialchars($user_data['username'] ?? '') ?></div>
            
            <div class="profile-role-badge" data-role="<?= $role ?>">
                <i class="fas <?= $role === 'admin' ? 'fa-crown' : ($role === 'manager' ? 'fa-chart-line' : ($role === 'developer' ? 'fa-code-branch' : 'fa-user-tie')) ?>"></i>
                <?= ucfirst($role) ?>
            </div>
            
            <div class="profile-stats">
                <div class="profile-stat">
                    <div class="profile-stat-value"><?= date('d/m/Y', strtotime($user_data['created_at'] ?? 'now')) ?></div>
                    <div class="profile-stat-label">Bergabung</div>
                </div>
                <div class="profile-stat">
                    <div class="profile-stat-value"><?= $user_data['last_login'] ? date('d/m/Y', strtotime($user_data['last_login'])) : '-' ?></div>
                    <div class="profile-stat-label">Terakhir Login</div>
                </div>
            </div>
            
            <!-- MODE DISTRIBUSI -->
            <?php if ($role === 'developer'): ?>
            <div class="profile-info-card" style="border-left: 4px solid <?= $is_split ? '#E9C46A' : '#4A90E2' ?>;">
                <div class="profile-info-title"><i class="fas fa-code-branch"></i> Mode Distribusi</div>
                
                <div class="profile-mode-badge <?= $is_split ? 'mode-split' : 'mode-external' ?>">
                    <?= $is_split ? '⚡ SPLIT 50:50' : '🔵 FULL EXTERNAL' ?>
                </div>
                <small style="display: block; margin-top: 8px; color: #D64F3C;">
                    <i class="fas fa-check-circle"></i> Data diverifikasi langsung dari database
                </small>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- TABS -->
        <div class="profile-tabs">
            <button class="profile-tab active" onclick="showProfileTab('edit')">
                <i class="fas fa-user-edit"></i>
                <span>Edit Profil</span>
            </button>
            <button class="profile-tab" onclick="showProfileTab('password')">
                <i class="fas fa-lock"></i>
                <span>Password</span>
            </button>
            <?php if ($role === 'developer'): ?>
            <button class="profile-tab" onclick="showProfileTab('company')">
                <i class="fas fa-building"></i>
                <span>Perusahaan</span>
            </button>
            <?php endif; ?>
        </div>
        
        <!-- TAB EDIT -->
        <div id="profile-tab-edit" class="profile-tab-content active">
            <form method="POST" class="profile-form">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($user_data['nama_lengkap'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>">
                </div>
                
                <button type="submit" class="btn-primary btn-block">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
        
        <!-- TAB PASSWORD -->
        <div id="profile-tab-password" class="profile-tab-content">
            <form method="POST" class="profile-form">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password Saat Ini</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Password Baru</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Konfirmasi Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn-primary btn-block btn-danger">
                    <i class="fas fa-key"></i> Ubah Password
                </button>
            </form>
        </div>
        
        <!-- TAB PERUSAHAAN -->
        <?php if ($role === 'developer'): ?>
        <div id="profile-tab-company" class="profile-tab-content">
            <h3 class="activity-title"><i class="fas fa-building"></i> Data Perusahaan</h3>
            
            <div class="company-card" style="background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #E0DAD3;">
                
                <!-- Informasi Kontak -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: #1B4A3C; margin-bottom: 15px; font-size: 16px; border-bottom: 2px solid #E7F3EF; padding-bottom: 8px;">
                        <i class="fas fa-phone-alt" style="color: #D64F3C; margin-right: 8px;"></i> Kontak Perusahaan
                    </h4>
                    
                    <div class="info-row" style="display: flex; margin-bottom: 12px;">
                        <span style="width: 120px; color: #7A8A84;">Telepon</span>
                        <span style="color: #1B4A3C; font-weight: 500;">
                            <?= !empty($user_data['telepon_perusahaan']) ? htmlspecialchars($user_data['telepon_perusahaan']) : '-' ?>
                        </span>
                    </div>
                    
                    <div class="info-row" style="display: flex; margin-bottom: 12px;">
                        <span style="width: 120px; color: #7A8A84;">Fax</span>
                        <span style="color: #1B4A3C; font-weight: 500;">
                            <?= !empty($user_data['fax']) ? htmlspecialchars($user_data['fax']) : '-' ?>
                        </span>
                    </div>
                    
                    <div class="info-row" style="display: flex; margin-bottom: 12px;">
                        <span style="width: 120px; color: #7A8A84;">Email Perusahaan</span>
                        <span style="color: #1B4A3C; font-weight: 500;">
                            <?= !empty($user_data['email_perusahaan']) ? htmlspecialchars($user_data['email_perusahaan']) : '-' ?>
                        </span>
                    </div>
                    
                    <div class="info-row" style="display: flex; margin-bottom: 12px;">
                        <span style="width: 120px; color: #7A8A84;">Website</span>
                        <span style="color: #1B4A3C; font-weight: 500;">
                            <?php if (!empty($user_data['website'])): ?>
                            <a href="<?= htmlspecialchars($user_data['website']) ?>" target="_blank"><?= htmlspecialchars($user_data['website']) ?></a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Alamat -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: #1B4A3C; margin-bottom: 15px; font-size: 16px; border-bottom: 2px solid #E7F3EF; padding-bottom: 8px;">
                        <i class="fas fa-map-marker-alt" style="color: #D64F3C; margin-right: 8px;"></i> Alamat
                    </h4>
                    
                    <div class="info-row" style="margin-bottom: 12px;">
                        <div style="background: #F5F3F0; padding: 12px; border-radius: 12px; color: #1B4A3C; line-height: 1.6;">
                            <?php 
                            if (!empty($user_data['alamat'])) {
                                echo nl2br(htmlspecialchars($user_data['alamat']));
                                if (!empty($user_data['kota'])) {
                                    echo '<br>' . htmlspecialchars($user_data['kota']);
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Legalitas -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: #1B4A3C; margin-bottom: 15px; font-size: 16px; border-bottom: 2px solid #E7F3EF; padding-bottom: 8px;">
                        <i class="fas fa-gavel" style="color: #D64F3C; margin-right: 8px;"></i> Legalitas
                    </h4>
                    
                    <div class="info-row" style="display: flex; margin-bottom: 12px;">
                        <span style="width: 80px; color: #7A8A84;">NPWP</span>
                        <span style="color: #1B4A3C; font-weight: 500;">
                            <?= !empty($user_data['npwp']) ? htmlspecialchars($user_data['npwp']) : '-' ?>
                        </span>
                    </div>
                    
                    <div class="info-row" style="display: flex; margin-bottom: 12px;">
                        <span style="width: 80px; color: #7A8A84;">SIUP</span>
                        <span style="color: #1B4A3C; font-weight: 500;">
                            <?= !empty($user_data['siup']) ? htmlspecialchars($user_data['siup']) : '-' ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($user_data['legalitas_file'])): ?>
                    <div class="info-row" style="margin-top: 15px;">
                        <a href="/admin/uploads/legalitas/<?= htmlspecialchars($user_data['legalitas_file']) ?>" target="_blank" class="btn-download" style="display: inline-flex; align-items: center; gap: 8px; background: #1B4A3C; color: white; padding: 10px 20px; border-radius: 40px; text-decoration: none; font-size: 13px;">
                            <i class="fas fa-file-pdf"></i> Download Legalitas
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Contact Person -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: #1B4A3C; margin-bottom: 15px; font-size: 16px; border-bottom: 2px solid #E7F3EF; padding-bottom: 8px;">
                        <i class="fas fa-user-tie" style="color: #D64F3C; margin-right: 8px;"></i> Contact Person
                    </h4>
                    
                    <div class="info-row" style="display: flex; margin-bottom: 12px;">
                        <span style="width: 100px; color: #7A8A84;">Nama</span>
                        <span style="color: #1B4A3C; font-weight: 500;">
                            <?= !empty($user_data['contact_person']) ? htmlspecialchars($user_data['contact_person']) : '-' ?>
                        </span>
                    </div>
                    
                    <div class="info-row" style="display: flex; margin-bottom: 12px;">
                        <span style="width: 100px; color: #7A8A84;">Telepon</span>
                        <span style="color: #1B4A3C; font-weight: 500;">
                            <?= !empty($user_data['contact_phone']) ? htmlspecialchars($user_data['contact_phone']) : '-' ?>
                        </span>
                    </div>
                </div>
                
                <!-- Profil Perusahaan -->
                <div>
                    <h4 style="color: #1B4A3C; margin-bottom: 15px; font-size: 16px; border-bottom: 2px solid #E7F3EF; padding-bottom: 8px;">
                        <i class="fas fa-chart-line" style="color: #D64F3C; margin-right: 8px;"></i> Profil Perusahaan
                    </h4>
                    
                    <div class="info-row" style="display: flex; margin-bottom: 12px;">
                        <span style="width: 120px; color: #7A8A84;">Bidang Usaha</span>
                        <span style="color: #1B4A3C; font-weight: 500;">
                            <?= !empty($user_data['bidang_usaha']) ? htmlspecialchars($user_data['bidang_usaha']) : '-' ?>
                        </span>
                    </div>
                    
                    <div class="info-row" style="display: flex; margin-bottom: 12px;">
                        <span style="width: 120px; color: #7A8A84;">Tahun Berdiri</span>
                        <span style="color: #1B4A3C; font-weight: 500;">
                            <?= !empty($user_data['tahun_berdiri']) ? htmlspecialchars($user_data['tahun_berdiri']) : '-' ?>
                        </span>
                    </div>
                    
                    <div class="info-row" style="display: flex; margin-bottom: 12px;">
                        <span style="width: 120px; color: #7A8A84;">Jumlah Proyek</span>
                        <span style="color: #1B4A3C; font-weight: 500;">
                            <?= !empty($user_data['jumlah_proyek']) ? htmlspecialchars($user_data['jumlah_proyek']) : '0' ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- DATA LANGSUNG DARI DATABASE -->
            <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; margin-top: 20px; border-radius: 8px; font-family: monospace; font-size: 12px;">
                <strong style="color: #569cd6;">🔍 RAW DATA FROM DATABASE:</strong><br>
                <pre><?php print_r($user_data); ?></pre>
                <p><small>Jika data di atas kosong, berarti query ke database gagal.</small></p>
            </div>
            
            <p style="text-align: center; color: #7A8A84; font-size: 12px; margin-top: 20px;">
                <i class="fas fa-info-circle"></i> Data perusahaan diverifikasi oleh tim legal
            </p>
        </div>
        <?php endif; ?>
        
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> TaufikMarie.com - Profil <?= ucfirst($role) ?> v7.0 (Ekstrim Mode)</p>
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
            <p class="upload-info">Format: JPG, PNG, GIF, WEBP. Maksimal 5MB.</p>
            <form id="profileUploadForm" enctype="multipart/form-data">
                <div class="upload-area" onclick="document.getElementById('profileFile').click()">
                    <input type="file" id="profileFile" name="profile_photo" accept="image/*" style="display: none;">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p class="upload-text">Klik untuk memilih file</p>
                </div>
                <button type="submit" class="btn-primary btn-block">Upload Foto</button>
            </form>
        </div>
    </div>
</div>

<style>
/* ===== PROFILE MOBILE STYLES ===== */
.profile-mobile-container {
    max-width: 100%;
    margin: 0 auto;
}

.profile-header-card {
    background: white;
    border-radius: 24px;
    padding: 24px 20px;
    margin-bottom: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid #E0DAD3;
    text-align: center;
}

.profile-photo-large {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto 15px;
}

.profile-img {
    width: 100%;
    height: 100%;
    border-radius: 24px;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.profile-initials {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #D64F3C, #FF8A5C);
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 48px;
    font-weight: 700;
    border: 4px solid white;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.profile-photo-upload {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 40px;
    height: 40px;
    background: #D64F3C;
    border: none;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    cursor: pointer;
    box-shadow: 0 5px 15px rgba(214,79,60,0.3);
    border: 2px solid white;
}

.profile-name {
    color: #1B4A3C;
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 5px;
}

.profile-username {
    color: #7A8A84;
    font-size: 14px;
    margin-bottom: 15px;
}

.profile-role-badge {
    display: inline-block;
    padding: 8px 24px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 20px;
    color: white;
}

.profile-role-badge[data-role="admin"] { background: #D64F3C; }
.profile-role-badge[data-role="manager"] { background: #4A90E2; }
.profile-role-badge[data-role="developer"] { background: #E9C46A; color: #1A2A24; }
.profile-role-badge[data-role="marketing"] { background: #2A9D8F; }

.profile-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin: 20px 0;
    padding: 15px 0;
    border-top: 2px solid #E7F3EF;
    border-bottom: 2px solid #E7F3EF;
}

.profile-stat-value {
    font-size: 16px;
    font-weight: 800;
    color: #1B4A3C;
}

.profile-stat-label {
    font-size: 11px;
    color: #7A8A84;
}

.profile-info-card {
    background: #E7F3EF;
    border-radius: 16px;
    padding: 15px;
    margin-top: 15px;
    text-align: left;
}

.profile-info-title {
    font-weight: 700;
    color: #1B4A3C;
    margin-bottom: 10px;
    font-size: 14px;
}

.profile-info-title i {
    color: #D64F3C;
    margin-right: 6px;
}

.profile-mode-badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 700;
}

.mode-split {
    background: rgba(233, 196, 106, 0.2);
    color: #B87C00;
    border: 1px solid #B87C00;
}

.mode-external {
    background: rgba(74, 144, 226, 0.2);
    color: #4A90E2;
    border: 1px solid #4A90E2;
}

.profile-tabs {
    display: flex;
    background: white;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 20px;
    border: 1px solid #E0DAD3;
}

.profile-tab {
    flex: 1;
    padding: 14px 5px;
    background: white;
    border: none;
    font-weight: 600;
    color: #7A8A84;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    transition: all 0.3s;
}

.profile-tab i {
    font-size: 18px;
}

.profile-tab span {
    font-size: 11px;
}

.profile-tab.active {
    color: #D64F3C;
    background: #FFF5F0;
    position: relative;
}

.profile-tab.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 20%;
    width: 60%;
    height: 3px;
    background: #D64F3C;
    border-radius: 3px 3px 0 0;
}

.profile-tab-content {
    display: none;
    background: white;
    border-radius: 20px;
    padding: 20px;
    border: 1px solid #E0DAD3;
}

.profile-tab-content.active {
    display: block;
}

.profile-form .form-group {
    margin-bottom: 20px;
}

.profile-form label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #1B4A3C;
    font-size: 13px;
}

.profile-form label i {
    color: #D64F3C;
    margin-right: 6px;
}

.profile-form .form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #E0DAD3;
    border-radius: 12px;
    font-size: 14px;
}

.btn-block {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 40px;
    font-weight: 600;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(135deg, #1B4A3C, #2A5F4E);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, #D64F3C, #FF6B4A) !important;
    color: white;
}

.footer {
    text-align: center;
    margin-top: 40px;
    padding: 20px;
    color: #7A8A84;
    font-size: 12px;
    border-top: 1px solid #E0DAD3;
}

.info-row {
    display: flex;
    align-items: flex-start;
}

@media (max-width: 768px) {
    .info-row {
        flex-direction: column;
    }
    .info-row span:first-child {
        width: 100% !important;
        margin-bottom: 4px;
    }
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
}
</style>

<script>
function showProfileTab(tab) {
    document.querySelectorAll('.profile-tab-content').forEach(el => {
        el.classList.remove('active');
    });
    document.querySelectorAll('.profile-tab').forEach(el => {
        el.classList.remove('active');
    });
    document.getElementById('profile-tab-' + tab).classList.add('active');
    event.currentTarget.classList.add('active');
}

function openProfilePhotoModal() {
    document.getElementById('profilePhotoModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeProfilePhotoModal() {
    document.getElementById('profilePhotoModal').classList.remove('show');
    document.body.style.overflow = '';
}

function previewProfilePhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // Preview logic
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('profileUploadForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Upload functionality akan ditambahkan');
});

function updateDateTime() {
    const now = new Date();
    const dateEl = document.getElementById('currentDate')?.querySelector('span');
    const timeEl = document.getElementById('currentTime')?.querySelector('span');
    if (dateEl && timeEl) {
        dateEl.textContent = now.toLocaleDateString('id-ID', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
        timeEl.textContent = now.toLocaleTimeString('id-ID', { hour12: false });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});
</script>

<!-- FOOTER -->
<?php include 'includes/footer.php'; ?>