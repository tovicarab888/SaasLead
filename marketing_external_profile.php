<?php
/**
 * MARKETING_EXTERNAL_PROFILE.PHP - Profil Marketing External
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

$user_id = $_SESSION['user_id'];

// Ambil data user
$stmt = $conn->prepare("
    SELECT 
        u.*,
        met.id as external_id,
        met.round_robin_order,
        met.last_assigned,
        met.created_at as external_created
    FROM users u
    LEFT JOIN marketing_external_team met ON u.id = met.user_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit();
}

// Proses update profile
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($nama_lengkap) || empty($email) || empty($phone)) {
        $error = "❌ Semua field wajib diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "❌ Format email tidak valid!";
    } else {
        $phone_validation = validatePhone($phone);
        if (!$phone_validation['valid']) {
            $error = "❌ " . $phone_validation['message'];
        } else {
            try {
                $update = $conn->prepare("
                    UPDATE users SET 
                        nama_lengkap = ?,
                        email = ?,
                        contact_phone = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->execute([$nama_lengkap, $email, $phone_validation['number'], $user_id]);
                
                // Update session
                $_SESSION['nama_lengkap'] = $nama_lengkap;
                
                $success = "✅ Profil berhasil diupdate!";
                
                // Refresh data
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } catch (Exception $e) {
                $error = "❌ Gagal update: " . $e->getMessage();
            }
        }
    }
}

// Proses ganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($current) || empty($new) || empty($confirm)) {
        $error = "❌ Semua field password wajib diisi!";
    } elseif ($new !== $confirm) {
        $error = "❌ Password baru dan konfirmasi tidak cocok!";
    } elseif (strlen($new) < 6) {
        $error = "❌ Password minimal 6 karakter!";
    } else {
        // Verifikasi password lama
        $stmt_pass = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_pass->execute([$user_id]);
        $hash = $stmt_pass->fetchColumn();
        
        if (password_verify($current, $hash)) {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$new_hash, $user_id]);
            $success = "✅ Password berhasil diubah!";
        } else {
            $error = "❌ Password lama salah!";
        }
    }
}

$page_title = 'Profil Saya';
$page_subtitle = 'Informasi akun marketing external';
$page_icon = 'fas fa-user-circle';

include 'includes/header.php';
include 'includes/sidebar_marketing_external.php';
?>

<style>
.profile-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

@media (min-width: 992px) {
    .profile-container {
        grid-template-columns: 1fr 1fr;
    }
}

.profile-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    border: 1px solid var(--border);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--primary-soft);
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: white;
    flex-shrink: 0;
}

.profile-title h3 {
    color: var(--primary);
    font-size: 20px;
    margin-bottom: 4px;
}

.profile-title p {
    color: var(--text-muted);
    font-size: 13px;
}

.info-row {
    display: flex;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px dashed var(--border);
}

.info-label {
    width: 140px;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 13px;
}

.info-label i {
    color: var(--secondary);
    width: 20px;
    margin-right: 5px;
}

.info-value {
    flex: 1;
    color: var(--text);
    font-weight: 500;
}

/* ===== FORM STYLES ===== */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--primary);
    font-size: 14px;
}

.form-group label i {
    color: var(--secondary);
    margin-right: 6px;
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-size: 15px;
    font-family: 'Inter', sans-serif;
    background: white;
}

.form-control:focus {
    border-color: var(--secondary);
    outline: none;
}

.btn-save {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border: none;
    border-radius: 40px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    margin-top: 10px;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(214,79,60,0.2);
}

.alert {
    padding: 14px 16px;
    border-radius: 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert.success {
    background: #d4edda;
    color: #155724;
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
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
    
    <!-- ALERT MESSAGES -->
    <?php if ($success): ?>
    <div class="alert success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <!-- PROFILE CONTAINER -->
    <div class="profile-container">
        <!-- LEFT CARD - INFO -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                </div>
                <div class="profile-title">
                    <h3><?= htmlspecialchars($user['nama_lengkap']) ?></h3>
                    <p>Marketing External • ID: <?= $user['external_id'] ?? '-' ?></p>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-user"></i> Username</div>
                <div class="info-value"><?= htmlspecialchars($user['username']) ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fab fa-whatsapp"></i> WhatsApp</div>
                <div class="info-value"><?= htmlspecialchars($user['contact_phone'] ?? '-') ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-calendar"></i> Bergabung</div>
                <div class="info-value"><?= date('d F Y', strtotime($user['created_at'])) ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-clock"></i> Terakhir Login</div>
                <div class="info-value"><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-' ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-sort-numeric-down"></i> Round Robin</div>
                <div class="info-value"><?= $user['round_robin_order'] ?? '0' ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-clock"></i> Last Assigned</div>
                <div class="info-value"><?= $user['last_assigned'] ? date('d/m/Y H:i', strtotime($user['last_assigned'])) : 'Belum pernah' ?></div>
            </div>
        </div>
        
        <!-- RIGHT CARD - EDIT PROFILE -->
        <div class="profile-card">
            <h3 style="margin-bottom: 20px; color: var(--primary);"><i class="fas fa-edit"></i> Edit Profil</h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-whatsapp"></i> Nomor WhatsApp</label>
                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['contact_phone'] ?? '') ?>" required>
                    <small style="color: var(--text-muted);">Format: 628xxxxxxxxx</small>
                </div>
                
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Update Profil
                </button>
            </form>
        </div>
        
        <!-- BOTTOM CARD - GANTI PASSWORD -->
        <div class="profile-card" style="grid-column: 1/-1;">
            <h3 style="margin-bottom: 20px; color: var(--primary);"><i class="fas fa-key"></i> Ganti Password</h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div style="display: grid; grid-template-columns: 1fr; gap: 16px;">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password Lama</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password Baru</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-save" style="background: linear-gradient(135deg, var(--primary), var(--primary-light));">
                    <i class="fas fa-key"></i> Ganti Password
                </button>
            </form>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Marketing External Profile</p>
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