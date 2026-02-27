<?php
/**
 * PLATFORM_KOMISI_SPLIT.PHP - LEADENGINE
 * Version: 2.1.0 - INFO CARD MERAH SESUAI UI GLOBAL
 * MOBILE FIRST UI - SESUAI GLOBAL SISTEM
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek akses: hanya Super Admin & Finance Platform
if (!isAdmin() && !isFinancePlatform()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Admin dan Finance Platform.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== BUAT TABEL JIKA BELUM ADA ==========
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `platform_komisi_split` (
          `id` int NOT NULL AUTO_INCREMENT,
          `commission_type` enum('PERCENT','FIXED') NOT NULL DEFAULT 'PERCENT',
          `commission_value` decimal(15,2) NOT NULL DEFAULT 2.50,
          `updated_by` int DEFAULT NULL,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insert default jika belum ada
    $check = $conn->query("SELECT COUNT(*) FROM platform_komisi_split")->fetchColumn();
    if ($check == 0) {
        $conn->prepare("INSERT INTO platform_komisi_split (commission_type, commission_value) VALUES ('PERCENT', 2.50)")->execute();
    }
} catch (Exception $e) {
    error_log("Error creating table: " . $e->getMessage());
}

// ========== PROSES UPDATE ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commission_type = $_POST['commission_type'] ?? 'PERCENT';
    $commission_value = isset($_POST['commission_value']) ? (float)str_replace(['.', ','], ['', '.'], $_POST['commission_value']) : 2.50;
    
    if ($commission_value <= 0) {
        $error = "❌ Nilai komisi harus lebih dari 0!";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE platform_komisi_split SET 
                    commission_type = ?,
                    commission_value = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute([$commission_type, $commission_value, $_SESSION['user_id']]);
            
            $success = "✅ Aturan komisi split berhasil disimpan!";
            logSystem("Platform komisi split updated", [
                'type' => $commission_type,
                'value' => $commission_value,
                'by' => $_SESSION['username']
            ], 'INFO', 'komisi_split.log');
            
        } catch (Exception $e) {
            $error = "❌ Gagal menyimpan: " . $e->getMessage();
        }
    }
}

// ========== AMBIL DATA SAAT INI ==========
$stmt = $conn->query("SELECT * FROM platform_komisi_split WHERE id = 1");
$data = $stmt->fetch(PDO::FETCH_ASSOC);

$current_type = $data['commission_type'] ?? 'PERCENT';
$current_value = $data['commission_value'] ?? 2.50;

$page_title = 'Komisi Split Platform';
$page_subtitle = 'Atur Komisi yang Harus Dibayar Developer';
$page_icon = 'fas fa-handshake';

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
    --whatsapp: #25D366;
    --gold: #E3B584;
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

/* INFO CARD - GRADIENT MERAH (DANGER) */
.info-card {
    background: linear-gradient(135deg, var(--danger), var(--secondary-light));
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 20px;
    color: white;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.info-card i {
    font-size: 36px;
    color: white;
    background: rgba(255,255,255,0.2);
    width: 60px;
    height: 60px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.info-card p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.stat-card:nth-child(2) {
    border-left-color: var(--success);
}

.stat-card:nth-child(3) {
    border-left-color: var(--warning);
}

.stat-icon {
    font-size: 20px;
    color: var(--secondary);
    margin-bottom: 8px;
}

.stat-card:nth-child(2) .stat-icon {
    color: var(--success);
}

.stat-card:nth-child(3) .stat-icon {
    color: var(--warning);
}

.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 20px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

.table-container {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-soft);
    flex-wrap: wrap;
    gap: 12px;
}

.table-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.table-header h3 i {
    color: var(--secondary);
}

.table-badge {
    background: var(--primary-soft);
    color: var(--primary);
    padding: 6px 14px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
}

.rules-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.rules-title {
    color: var(--primary);
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-soft);
}

.rules-title i {
    color: var(--secondary);
}

.rule-item {
    background: var(--primary-soft);
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 24px;
}

.rule-label {
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
}

.rule-label i {
    color: var(--secondary);
    width: 24px;
    font-size: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.radio-group {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.radio-option {
    flex: 1;
    min-width: 120px;
}

.radio-option input[type="radio"] {
    display: none;
}

.radio-option label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px;
    background: white;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 52px;
}

.radio-option input[type="radio"]:checked + label {
    border-color: var(--secondary);
    background: rgba(214,79,60,0.05);
}

.radio-option label i {
    color: var(--secondary);
    font-size: 16px;
    width: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.rule-input-group {
    display: flex;
    align-items: center;
    background: white;
    border: 2px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 12px;
}

.rule-prefix {
    padding: 14px 16px;
    background: var(--primary-soft);
    color: var(--primary);
    font-weight: 600;
    border-right: 2px solid var(--border);
    font-size: 16px;
}

.rule-input {
    flex: 1;
    padding: 14px 16px;
    border: none;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    min-height: 52px;
    text-align: right;
    -webkit-appearance: none;
    appearance: none;
}

.rule-input:focus {
    outline: none;
}

.rule-suffix {
    padding: 14px 16px;
    background: var(--primary-soft);
    color: var(--primary);
    font-weight: 600;
    border-left: 2px solid var(--border);
    font-size: 16px;
}

.rule-desc {
    margin-top: 12px;
    font-size: 13px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: white;
    border-radius: 40px;
}

.rule-desc i {
    color: var(--secondary);
    font-size: 14px;
    width: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.action-buttons {
    display: flex;
    gap: 16px;
    justify-content: flex-end;
    margin-top: 24px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 14px 32px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 52px;
    box-shadow: 0 8px 20px rgba(27,74,60,0.2);
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(27,74,60,0.3);
}

.btn-primary i {
    font-size: 15px;
    width: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-secondary {
    background: var(--border);
    color: var(--text);
    border: none;
    padding: 14px 32px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    min-height: 52px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: var(--text-muted);
    color: white;
}

.btn-secondary i {
    font-size: 15px;
    width: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 12px !important;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .radio-group {
        flex-direction: column;
        gap: 12px;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 12px;
    }
    
    .btn-primary, .btn-secondary {
        width: 100%;
        justify-content: center;
    }
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
    
    <!-- STATS CARD -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-percent"></i></div>
            <div class="stat-label">Tipe Saat Ini</div>
            <div class="stat-value"><?= $current_type == 'PERCENT' ? 'Persen (%)' : 'Nominal Tetap (Rp)' ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-label">Nilai Komisi</div>
            <div class="stat-value">
                <?php if ($current_type == 'PERCENT'): ?>
                    <?= number_format($current_value, 2, ',', '.') ?>%
                <?php else: ?>
                    Rp <?= number_format($current_value, 0, ',', '.') ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-label">Terakhir Update</div>
            <div class="stat-value"><?= isset($data['updated_at']) ? date('d/m/Y', strtotime($data['updated_at'])) : '-' ?></div>
        </div>
    </div>
    
    <!-- INFO CARD - GRADIENT MERAH (DANGER) -->
    <div class="info-card">
        <i class="fas fa-handshake"></i>
        <div style="flex: 1;">
            <strong style="font-size: 16px;">Atur Komisi Split untuk Developer</strong>
            <p>Komisi ini WAJIB dibayarkan DEVELOPER ke PLATFORM setiap kali lead external (akibat SPLIT 50:50) menjadi DEAL.</p>
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
    
    <!-- FORM KOMISI SPLIT -->
    <form method="POST" id="komisiForm" onsubmit="return prepareKomisiValues()">
        <div class="rules-card">
            <div class="rules-title">
                <i class="fas fa-coins"></i> Aturan Komisi Split
            </div>
            
            <div class="rule-item">
                <div class="rule-label">
                    <i class="fas fa-handshake"></i> Tipe Komisi
                </div>
                
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" name="commission_type" id="type_percent" value="PERCENT" <?= $current_type == 'PERCENT' ? 'checked' : '' ?>>
                        <label for="type_percent"><i class="fas fa-percent"></i> Persen (%)</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" name="commission_type" id="type_fixed" value="FIXED" <?= $current_type == 'FIXED' ? 'checked' : '' ?>>
                        <label for="type_fixed"><i class="fas fa-coins"></i> Nominal Tetap (Rp)</label>
                    </div>
                </div>
                
                <div id="input_percent" style="display: <?= $current_type == 'PERCENT' ? 'block' : 'none' ?>;">
                    <div class="rule-label">
                        <i class="fas fa-percent"></i> Nilai Komisi (%)
                    </div>
                    <div class="rule-input-group">
                        <input type="text" name="commission_value_percent" id="commission_value_percent" 
                               class="rule-input desimal-input" 
                               value="<?= number_format($current_value, 2, ',', '.') ?>" 
                               placeholder="2.50" inputmode="decimal">
                        <span class="rule-suffix">%</span>
                    </div>
                    <div class="rule-desc">
                        <i class="fas fa-info-circle"></i> 
                        Contoh: Developer bayar 2.5% dari harga unit ke platform
                    </div>
                </div>
                
                <div id="input_fixed" style="display: <?= $current_type == 'FIXED' ? 'block' : 'none' ?>;">
                    <div class="rule-label">
                        <i class="fas fa-coins"></i> Nilai Komisi (Rp)
                    </div>
                    <div class="rule-input-group">
                        <span class="rule-prefix">Rp</span>
                        <input type="text" name="commission_value_fixed" id="commission_value_fixed" 
                               class="rule-input rupiah-input" 
                               value="<?= number_format($current_value, 0, ',', '.') ?>" 
                               placeholder="2.500.000" inputmode="numeric">
                    </div>
                    <div class="rule-desc">
                        <i class="fas fa-info-circle"></i> 
                        Contoh: Developer bayar Rp 2.500.000 per deal ke platform
                    </div>
                </div>
                
                <input type="hidden" name="commission_value" id="commission_value" value="<?= $current_value ?>">
            </div>
            
            <div style="background: var(--primary-soft); padding: 20px; border-radius: 16px; margin: 24px 0;">
                <p style="margin: 0; color: var(--text); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-lightbulb" style="color: var(--warning); font-size: 18px;"></i> 
                    <strong>Bagaimana sistem bekerja:</strong>
                </p>
                <ul style="margin-top: 12px; padding-left: 20px; color: var(--text-light);">
                    <li>Nilai di atas adalah <strong>DEFAULT</strong> untuk semua unit.</li>
                    <li>Developer bisa mengubah nilai ini <strong>per unit</strong> di halaman Kelola Unit.</li>
                    <li>Komisi akan otomatis dicatat saat lead external DEAL.</li>
                    <li>Finance Platform bisa melihat tagihan di halaman Tagihan Split.</li>
                </ul>
            </div>
            
            <div class="action-buttons">
                <a href="finance_platform_dashboard.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Simpan Aturan
                </button>
            </div>
        </div>
    </form>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Komisi Split Platform v2.1</p>
    </div>
    
</div>

<script>
// Fungsi format Rupiah
function formatRupiah(angka, prefix = '') {
    if (!angka && angka !== 0) return '0';
    let number_string = angka.toString().replace(/[^,\d]/g, ''),
        split = number_string.split(','),
        sisa = split[0].length % 3,
        rupiah = split[0].substr(0, sisa),
        ribuan = split[0].substr(sisa).match(/\d{3}/gi);
    
    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
    return prefix + rupiah;
}

function parseRupiah(rupiah) {
    if (!rupiah) return 0;
    let number = rupiah.toString().replace(/\./g, '').replace(/,/g, '.');
    return parseFloat(number) || 0;
}

// Format desimal
document.querySelectorAll('.desimal-input').forEach(input => {
    input.addEventListener('keyup', function(e) {
        let value = this.value.replace(/[^\d,]/g, '');
        value = value.replace(',', '.');
        let parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        this.value = value;
    });
});

// Toggle input berdasarkan tipe
document.querySelectorAll('input[name="commission_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'PERCENT') {
            document.getElementById('input_percent').style.display = 'block';
            document.getElementById('input_fixed').style.display = 'none';
        } else {
            document.getElementById('input_percent').style.display = 'none';
            document.getElementById('input_fixed').style.display = 'block';
        }
    });
});

function prepareKomisiValues() {
    const type = document.querySelector('input[name="commission_type"]:checked').value;
    let value = 0;
    
    if (type === 'PERCENT') {
        value = document.getElementById('commission_value_percent').value.replace(',', '.');
        value = parseFloat(value) || 0;
        if (value <= 0) {
            alert('Nilai komisi harus lebih dari 0');
            return false;
        }
    } else {
        value = parseRupiah(document.getElementById('commission_value_fixed').value);
        if (value <= 0) {
            alert('Nilai komisi harus lebih dari 0');
            return false;
        }
    }
    
    document.getElementById('commission_value').value = value;
    return true;
}

function updateDateTime() {
    const now = new Date();
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    const dayName = days[now.getDay()];
    const day = now.getDate();
    const month = months[now.getMonth()];
    const year = now.getFullYear();
    
    document.querySelector('.date span').textContent = dayName + ', ' + day + ' ' + month + ' ' + year;
    
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    document.querySelector('.time span').textContent = hours + ':' + minutes + ':' + seconds;
}

setInterval(updateDateTime, 1000);
updateDateTime();
</script>

<?php include 'includes/footer.php'; ?>