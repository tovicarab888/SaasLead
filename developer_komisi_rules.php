<?php
/**
 * DEVELOPER_KOMISI_RULES.PHP - LEADENGINE
 * Version: 2.0.0 - UI SESUAI GLOBAL SISTEM + FORMAT RUPIAH + KEYPAD MOBILE
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// Cek session developer
if (!isDeveloper()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

$developer_id = $_SESSION['user_id'];
$developer_name = $_SESSION['nama_lengkap'] ?? 'Developer';

// ========== BUAT TABEL KOMISI_RULES JIKA BELUM ADA ==========
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `komisi_rules` (
          `id` int NOT NULL AUTO_INCREMENT,
          `developer_id` int NOT NULL,
          `marketing_type_id` int NOT NULL,
          `commission_value` decimal(15,2) NOT NULL DEFAULT 0,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_developer_type` (`developer_id`, `marketing_type_id`),
          KEY `marketing_type_id` (`marketing_type_id`),
          CONSTRAINT `komisi_rules_ibfk_1` FOREIGN KEY (`developer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          CONSTRAINT `komisi_rules_ibfk_2` FOREIGN KEY (`marketing_type_id`) REFERENCES `marketing_types` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    error_log("Error creating komisi_rules table: " . $e->getMessage());
}

// ========== PROSES UPDATE KOMISI ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sales_inhouse = isset($_POST['sales_inhouse']) ? (float)str_replace(['.', ','], ['', '.'], $_POST['sales_inhouse']) : 0;
    $sales_canvasing = isset($_POST['sales_canvasing']) ? (float)str_replace(',', '.', $_POST['sales_canvasing']) : 0;
    
    try {
        $conn->beginTransaction();
        
        // Cari ID untuk sales_inhouse
        $stmt = $conn->prepare("SELECT id FROM marketing_types WHERE type_name = 'sales_inhouse'");
        $stmt->execute();
        $inhouse_id = $stmt->fetchColumn();
        
        if ($inhouse_id) {
            $conn->prepare("
                INSERT INTO komisi_rules (developer_id, marketing_type_id, commission_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE commission_value = ?
            ")->execute([$developer_id, $inhouse_id, $sales_inhouse, $sales_inhouse]);
        }
        
        // Cari ID untuk sales_canvasing
        $stmt = $conn->prepare("SELECT id FROM marketing_types WHERE type_name = 'sales_canvasing'");
        $stmt->execute();
        $canvasing_id = $stmt->fetchColumn();
        
        if ($canvasing_id) {
            $conn->prepare("
                INSERT INTO komisi_rules (developer_id, marketing_type_id, commission_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE commission_value = ?
            ")->execute([$developer_id, $canvasing_id, $sales_canvasing, $sales_canvasing]);
        }
        
        $conn->commit();
        $success = "✅ Aturan komisi berhasil disimpan!";
        logSystem("Komisi rules updated", ['developer_id' => $developer_id], 'INFO', 'komisi.log');
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "❌ Gagal menyimpan: " . $e->getMessage();
    }
}

// ========== AMBIL DATA KOMISI SAAT INI ==========
$komisi_rules = [];
try {
    $stmt = $conn->prepare("
        SELECT kr.*, mt.type_name, mt.commission_type
        FROM komisi_rules kr
        JOIN marketing_types mt ON kr.marketing_type_id = mt.id
        WHERE kr.developer_id = ?
    ");
    $stmt->execute([$developer_id]);
    $komisi_rules = $stmt->fetchAll();
    
    $komisi_data = [];
    foreach ($komisi_rules as $rule) {
        $komisi_data[$rule['type_name']] = $rule['commission_value'];
    }
} catch (Exception $e) {
    error_log("Error loading komisi rules: " . $e->getMessage());
    $komisi_data = [];
}

// Default values
$inhouse_value = isset($komisi_data['sales_inhouse']) ? $komisi_data['sales_inhouse'] : 1000000;
$canvasing_value = isset($komisi_data['sales_canvasing']) ? $komisi_data['sales_canvasing'] : 3.00;

$page_title = 'Aturan Komisi';
$page_subtitle = 'Kelola Nilai Komisi Marketing';
$page_icon = 'fas fa-coins';

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
    border-left: 6px solid var(--info);
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
    background: rgba(74,144,226,0.1);
    color: var(--info);
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

.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.info-card {
    background: linear-gradient(135deg, var(--info), #6DA5F0);
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
    color: #E3B584;
    background: rgba(255,255,255,0.1);
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
    border-left: 6px solid var(--info);
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
    color: var(--info);
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

/* ===== FORM RULES ===== */
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
    color: var(--info);
}

.rules-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-bottom: 24px;
}

.rule-item {
    background: var(--primary-soft);
    border-radius: 20px;
    padding: 20px;
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
    color: var(--info);
    width: 24px;
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
    margin-top: 8px;
    font-size: 12px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: white;
    border-radius: 40px;
}

.rule-desc i {
    color: var(--info);
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
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }
    
    .stat-card {
        padding: 12px;
    }
    
    .stat-value {
        font-size: 16px;
    }
    
    .rules-grid {
        grid-template-columns: 1fr;
        gap: 16px;
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
            <div class="stat-icon"><i class="fas fa-store"></i></div>
            <div class="stat-label">Sales Inhouse</div>
            <div class="stat-value"><?= 'Rp ' . number_format($inhouse_value, 0, ',', '.') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-camera"></i></div>
            <div class="stat-label">Sales Canvasing</div>
            <div class="stat-value"><?= number_format($canvasing_value, 2, ',', '.') ?>%</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Marketing</div>
            <div class="stat-value"><?= $total_marketing ?></div>
        </div>
    </div>
    
    <!-- INFO CARD -->
    <div class="info-card">
        <i class="fas fa-info-circle"></i>
        <div style="flex: 1;">
            <strong style="font-size: 16px;">Atur Nilai Komisi Marketing</strong>
            <p>Tentukan sendiri nilai komisi untuk marketing internal Anda. Sales Inhouse = komisi tetap (Rp), Sales Canvasing = komisi persentase (%).</p>
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
    
    <!-- FORM KOMISI RULES -->
    <form method="POST" id="komisiForm" onsubmit="return prepareKomisiValues()">
        <div class="rules-card">
            <div class="rules-title">
                <i class="fas fa-coins"></i> Nilai Komisi per Tipe Marketing
            </div>
            
            <div class="rules-grid">
                <!-- Sales Inhouse -->
                <div class="rule-item">
                    <div class="rule-label">
                        <i class="fas fa-store"></i> Sales Inhouse
                    </div>
                    <div class="rule-input-group">
                        <span class="rule-prefix">Rp</span>
                        <input type="text" name="sales_inhouse" id="sales_inhouse" class="rule-input rupiah-input" 
                               value="<?= number_format($inhouse_value, 0, ',', '.') ?>" 
                               placeholder="1.000.000" inputmode="numeric" required>
                        <span class="rule-suffix">/deal</span>
                    </div>
                    <div class="rule-desc">
                        <i class="fas fa-info-circle"></i> Komisi tetap per deal (dalam Rupiah)
                    </div>
                </div>
                
                <!-- Sales Canvasing -->
                <div class="rule-item">
                    <div class="rule-label">
                        <i class="fas fa-camera"></i> Sales Canvasing
                    </div>
                    <div class="rule-input-group">
                        <input type="text" name="sales_canvasing" id="sales_canvasing" class="rule-input desimal-input" 
                               value="<?= number_format($canvasing_value, 2, ',', '.') ?>" 
                               placeholder="3.00" inputmode="decimal" required>
                        <span class="rule-suffix">%</span>
                    </div>
                    <div class="rule-desc">
                        <i class="fas fa-info-circle"></i> Persentase dari harga unit
                    </div>
                </div>
            </div>
            
            <div style="background: var(--primary-soft); padding: 20px; border-radius: 16px; margin: 24px 0;">
                <p style="margin: 0; color: var(--text); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-lightbulb" style="color: var(--warning); font-size: 18px;"></i> 
                    <strong>Catatan:</strong> Nilai komisi ini akan digunakan untuk semua marketing internal yang Anda tambahkan. Anda bisa mengubahnya kapan saja.
                </p>
            </div>
            
            <div class="action-buttons">
                <a href="developer_team.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Simpan Aturan Komisi
                </button>
            </div>
        </div>
    </form>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Aturan Komisi v2.0 (UI Global + Format Rupiah)</p>
    </div>
    
</div>

<script>
// ===== FUNGSI FORMAT RUPIAH =====
function formatRupiah(angka, prefix = '') {
    if (!angka && angka !== 0) return '0';
    
    let number_string = angka.toString().replace(/[^,\d]/g, '');
    let split = number_string.split(',');
    let sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    let ribuan = split[0].substr(sisa).match(/\d{3}/gi);
    
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

// Format saat mengetik (Rupiah)
document.querySelectorAll('.rupiah-input').forEach(input => {
    input.addEventListener('keyup', function(e) {
        let value = this.value.replace(/[^\d]/g, '');
        if (value) {
            this.value = formatRupiah(value);
        }
    });
    
    input.addEventListener('blur', function() {
        if (!this.value) this.value = '0';
    });
});

// Format desimal (persen)
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

function prepareKomisiValues() {
    const inhouse = document.getElementById('sales_inhouse');
    const canvasing = document.getElementById('sales_canvasing');
    
    if (!inhouse.value || parseRupiah(inhouse.value) <= 0) {
        alert('❌ Nilai komisi Sales Inhouse harus diisi lebih dari 0');
        inhouse.focus();
        return false;
    }
    
    if (!canvasing.value || parseFloat(canvasing.value) <= 0) {
        alert('❌ Nilai komisi Sales Canvasing harus diisi lebih dari 0');
        canvasing.focus();
        return false;
    }
    
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