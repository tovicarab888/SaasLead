<?php
/**
 * MARKETING_REKENING.PHP - LEADENGINE
 * Version: 2.0.0 - HANYA INPUT MANUAL UNTUK REKENING PRIBADI MARKETING
 * MOBILE FIRST UI - Input Rekening Sendiri
 * FIXED: Hapus tab "Pilih dari Master Bank", hanya manual input
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

// ========== PROSES UPDATE REKENING (HANYA INPUT MANUAL) ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_rekening') {
        // Input manual
        $nomor_rekening = trim($_POST['nomor_rekening'] ?? '');
        $atas_nama = trim($_POST['atas_nama'] ?? '');
        $nama_bank = trim($_POST['nama_bank'] ?? '');
        
        // Validasi
        if (empty($nomor_rekening) || empty($atas_nama) || empty($nama_bank)) {
            $error = "❌ Nomor rekening, atas nama, dan nama bank wajib diisi";
        } else {
            // Update dengan data manual, pastikan bank_id di-NULL-kan
            $stmt = $conn->prepare("
                UPDATE marketing_team SET 
                    bank_id = NULL,
                    nomor_rekening = ?,
                    atas_nama_rekening = ?,
                    nama_bank_rekening = ?,
                    rekening_verified = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            if ($stmt->execute([$nomor_rekening, $atas_nama, $nama_bank, $marketing_id])) {
                $success = "✅ Rekening berhasil disimpan. Menunggu verifikasi admin.";
            } else {
                $error = "❌ Gagal menyimpan rekening";
            }
        }
    }
}

// ========== AMBIL DATA REKENING MARKETING ==========
$stmt = $conn->prepare("
    SELECT 
        bank_id, 
        nomor_rekening, 
        atas_nama_rekening, 
        nama_bank_rekening,
        rekening_verified
    FROM marketing_team 
    WHERE id = ?
");
$stmt->execute([$marketing_id]);
$rekening = $stmt->fetch();

$page_title = 'Kelola Rekening Pribadi';
$page_subtitle = 'Input Rekening untuk Transfer Komisi';
$page_icon = 'fas fa-university';

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

.alert {
    padding: 14px 16px;
    border-radius: 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
    border-left: 4px solid;
}

.alert.success {
    background: #d4edda;
    color: #155724;
    border-left-color: #28a745;
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
    border-left-color: var(--danger);
}

.alert.info {
    background: #d1ecf1;
    color: #0c5460;
    border-left-color: #17a2b8;
}

.form-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    border: 1px solid var(--border);
}

.form-card h3 {
    color: var(--primary);
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-soft);
}

.form-card h3 i {
    color: var(--secondary);
}

.rekening-info {
    background: var(--primary-soft);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 6px solid var(--info);
}

.rekening-info .status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 10px;
}

.status-badge.verified {
    background: var(--success);
    color: white;
}

.status-badge.pending {
    background: var(--warning);
    color: #1A2A24;
}

.status-badge.not-set {
    background: var(--border);
    color: var(--text);
}

.rekening-detail {
    background: white;
    border-radius: 12px;
    padding: 16px;
    margin-top: 10px;
}

.rekening-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 14px;
}

.rekening-label {
    color: var(--text-muted);
    font-weight: 500;
}

.rekening-value {
    font-weight: 700;
    color: var(--text);
}

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

.form-control, .form-select {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    background: white;
    min-height: 52px;
}

.form-control:focus, .form-select:focus {
    border-color: var(--secondary);
    outline: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    min-height: 52px;
    margin-top: 20px;
}

.btn-secondary {
    background: var(--border);
    color: var(--text);
    border: none;
    padding: 14px 20px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    min-height: 48px;
}

.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

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
    
    .form-card {
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
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
    
    <!-- ALERT MESSAGES -->
    <?php if ($success): ?>
    <div class="alert success">
        <i class="fas fa-check-circle"></i>
        <?= $success ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle"></i>
        <?= $error ?>
    </div>
    <?php endif; ?>
    
    <!-- INFO REKENING SAAT INI -->
    <div class="form-card">
        <h3><i class="fas fa-info-circle"></i> Status Rekening Pribadi Anda</h3>
        
        <div class="rekening-info">
            <?php if (!$rekening || (!$rekening['bank_id'] && empty($rekening['nomor_rekening']))): ?>
                <span class="status-badge not-set">BELUM DIISI</span>
                <p style="margin-top: 10px; color: var(--text-light);">Anda belum mengatur rekening pribadi untuk transfer komisi.</p>
            <?php else: ?>
                <?php if ($rekening['rekening_verified']): ?>
                    <span class="status-badge verified">✓ TERVERIFIKASI</span>
                <?php else: ?>
                    <span class="status-badge pending">⏳ MENUNGGU VERIFIKASI</span>
                <?php endif; ?>
                
                <div class="rekening-detail">
                    <?php if ($rekening['bank_id']): ?>
                        <?php
                        // Jika ada bank_id, ambil dari tabel banks (untuk keperluan tampilan saja)
                        $stmt = $conn->prepare("SELECT * FROM banks WHERE id = ?");
                        $stmt->execute([$rekening['bank_id']]);
                        $bank = $stmt->fetch();
                        ?>
                        <div class="rekening-row">
                            <span class="rekening-label">Bank</span>
                            <span class="rekening-value"><?= htmlspecialchars($bank['nama_bank'] ?? '-') ?></span>
                        </div>
                        <div class="rekening-row">
                            <span class="rekening-label">Nomor Rekening</span>
                            <span class="rekening-value"><?= htmlspecialchars($bank['nomor_rekening'] ?? '-') ?></span>
                        </div>
                        <div class="rekening-row">
                            <span class="rekening-label">Atas Nama</span>
                            <span class="rekening-value"><?= htmlspecialchars($bank['atas_nama'] ?? '-') ?></span>
                        </div>
                    <?php else: ?>
                        <div class="rekening-row">
                            <span class="rekening-label">Bank</span>
                            <span class="rekening-value"><?= htmlspecialchars($rekening['nama_bank_rekening'] ?? '-') ?></span>
                        </div>
                        <div class="rekening-row">
                            <span class="rekening-label">Nomor Rekening</span>
                            <span class="rekening-value"><?= htmlspecialchars($rekening['nomor_rekening'] ?? '-') ?></span>
                        </div>
                        <div class="rekening-row">
                            <span class="rekening-label">Atas Nama</span>
                            <span class="rekening-value"><?= htmlspecialchars($rekening['atas_nama_rekening'] ?? '-') ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!$rekening['rekening_verified']): ?>
                <p style="margin-top: 15px; font-size: 13px; color: var(--warning);">
                    <i class="fas fa-clock"></i> Rekening akan diverifikasi oleh admin dalam 1x24 jam.
                </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- FORM INPUT MANUAL (SATU-SATUNYA) -->
        <form method="POST">
            <input type="hidden" name="action" value="update_rekening">
            
            <div class="form-group">
                <label><i class="fas fa-university"></i> Nama Bank <span style="color: var(--danger);">*</span></label>
                <input type="text" name="nama_bank" class="form-control" placeholder="Contoh: Bank Mandiri, BCA, BRI" value="<?= htmlspecialchars(($rekening && !$rekening['bank_id']) ? ($rekening['nama_bank_rekening'] ?? '') : '') ?>" required>
                <small style="color: var(--text-muted); display: block; margin-top: 6px;">
                    <i class="fas fa-info-circle"></i> Masukkan nama bank pribadi Anda.
                </small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-credit-card"></i> Nomor Rekening <span style="color: var(--danger);">*</span></label>
                <input type="text" name="nomor_rekening" class="form-control" placeholder="1234567890" value="<?= htmlspecialchars(($rekening && !$rekening['bank_id']) ? ($rekening['nomor_rekening'] ?? '') : '') ?>" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-user"></i> Atas Nama (Sesuai Rekening) <span style="color: var(--danger);">*</span></label>
                <input type="text" name="atas_nama" class="form-control" placeholder="Nama lengkap sesuai rekening" value="<?= htmlspecialchars(($rekening && !$rekening['bank_id']) ? ($rekening['atas_nama_rekening'] ?? '') : '') ?>" required>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Simpan Rekening Pribadi
            </button>
        </form>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="marketing_dashboard.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
    
    <!-- INFO PENTING -->
    <div class="form-card" style="background: var(--primary-soft);">
        <h3><i class="fas fa-info-circle"></i> Informasi Penting</h3>
        <ul style="margin-left: 20px; color: var(--text-light); line-height: 1.8;">
            <li>Masukkan data rekening pribadi Anda yang sah.</li>
            <li>Pastikan nomor rekening dan nama pemilik sudah benar.</li>
            <li>Rekening ini akan digunakan untuk transfer komisi hasil penjualan Anda.</li>
            <li>Setelah disimpan, rekening akan diverifikasi oleh admin (maksimal 1x24 jam).</li>
            <li>Komisi hanya akan ditransfer ke rekening yang sudah terverifikasi.</li>
            <li>Jika Anda sebelumnya memilih rekening dari master bank, data tersebut telah direset. Silakan input rekening pribadi Anda sekarang.</li>
        </ul>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Kelola Rekening Pribadi v2.0</p>
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