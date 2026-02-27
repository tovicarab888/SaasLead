<?php
/**
 * FINANCE_PLATFORM_KOMISI.PHP - LEADENGINE
 * Version: 3.0.0 - UI MERAH SESUAI SISTEM + SINKRON DENGAN UNIT
 * MOBILE FIRST UI - KELOLA KOMISI UNTUK FINANCE PLATFORM
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

// ========== PROSES UPDATE KOMISI PLATFORM ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_komisi_external') {
        $komisi_eksternal_persen = isset($_POST['komisi_eksternal_persen']) ? (float)$_POST['komisi_eksternal_persen'] : 3.00;
        $komisi_eksternal_rupiah = isset($_POST['komisi_eksternal_rupiah']) ? (float)$_POST['komisi_eksternal_rupiah'] : null;
        
        // Update di tabel marketing_config atau platform_config
        try {
            $conn->beginTransaction();
            
            // Cek apakah sudah ada record
            $check = $conn->prepare("SELECT id FROM marketing_config WHERE id = 2");
            $check->execute();
            
            if ($check->fetch()) {
                // Update
                $stmt = $conn->prepare("
                    UPDATE marketing_config SET 
                        komisi_eksternal_persen = ?,
                        komisi_eksternal_rupiah = ?,
                        updated_at = NOW()
                    WHERE id = 2
                ");
                $stmt->execute([$komisi_eksternal_persen, $komisi_eksternal_rupiah]);
            } else {
                // Insert
                $stmt = $conn->prepare("
                    INSERT INTO marketing_config (id, komisi_eksternal_persen, komisi_eksternal_rupiah, created_at, updated_at)
                    VALUES (2, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$komisi_eksternal_persen, $komisi_eksternal_rupiah]);
            }
            
            $conn->commit();
            $success = "✅ Aturan komisi eksternal berhasil disimpan!";
            logSystem("Platform komisi eksternal updated", [
                'persen' => $komisi_eksternal_persen,
                'rupiah' => $komisi_eksternal_rupiah,
                'by' => $_SESSION['username']
            ], 'INFO', 'komisi.log');
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "❌ Gagal menyimpan: " . $e->getMessage();
        }
    }
}

// ========== AMBIL DATA KOMISI PLATFORM ==========
$komisi_eksternal_persen = 3.00;
$komisi_eksternal_rupiah = null;

try {
    $stmt = $conn->query("SELECT * FROM marketing_config WHERE id = 2");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        $komisi_eksternal_persen = $config['komisi_eksternal_persen'] ?? 3.00;
        $komisi_eksternal_rupiah = $config['komisi_eksternal_rupiah'] ?? null;
    }
} catch (Exception $e) {
    error_log("Error loading marketing config: " . $e->getMessage());
}

// ========== AMBIL STATISTIK ==========
// Total unit dengan override komisi
$stmt = $conn->query("
    SELECT 
        COUNT(*) as total_units,
        SUM(CASE WHEN komisi_eksternal_rupiah IS NOT NULL AND komisi_eksternal_rupiah > 0 THEN 1 ELSE 0 END) as units_with_fixed,
        SUM(CASE WHEN komisi_eksternal_rupiah IS NULL AND komisi_eksternal_persen != 3.00 THEN 1 ELSE 0 END) as units_with_percent_override
    FROM units
    WHERE komisi_eksternal_persen != 3.00 OR komisi_eksternal_rupiah IS NOT NULL
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Total developer yang menggunakan split
$stmt = $conn->query("
    SELECT COUNT(*) as total_split FROM users 
    WHERE role = 'developer' AND distribution_mode = 'SPLIT_50_50' AND is_active = 1
");
$split_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Komisi Platform';
$page_subtitle = 'Atur Komisi Eksternal & Split';
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

/* INFO CARD - GRADIENT MERAH */
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
    grid-template-columns: repeat(4, 1fr);
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
    border-left-color: var(--info);
}

.stat-card:nth-child(4) {
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
    color: var(--info);
}

.stat-card:nth-child(4) .stat-icon {
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

@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 12px !important;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    
    .stat-card {
        padding: 12px;
    }
    
    .stat-value {
        font-size: 16px;
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
    
    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-percent"></i></div>
            <div class="stat-label">Komisi Eksternal (%)</div>
            <div class="stat-value"><?= number_format($komisi_eksternal_persen, 2) ?>%</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-label">Komisi Eksternal (Rp)</div>
            <div class="stat-value">
                <?= $komisi_eksternal_rupiah ? 'Rp ' . number_format($komisi_eksternal_rupiah, 0, ',', '.') : '-' ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-code-branch"></i></div>
            <div class="stat-label">Developer Split</div>
            <div class="stat-value"><?= $split_stats['total_split'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-edit"></i></div>
            <div class="stat-label">Unit Override</div>
            <div class="stat-value"><?= ($stats['units_with_fixed'] ?? 0) + ($stats['units_with_percent_override'] ?? 0) ?></div>
        </div>
    </div>
    
    <!-- INFO CARD - MERAH -->
    <div class="info-card">
        <i class="fas fa-info-circle"></i>
        <div style="flex: 1;">
            <strong style="font-size: 16px;">Aturan Komisi Platform</strong>
            <p>Nilai di bawah adalah DEFAULT untuk semua unit. Developer bisa meng-override per unit di halaman mereka.</p>
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
    
    <!-- FORM KOMISI EXTERNAL -->
    <form method="POST" id="komisiForm">
        <input type="hidden" name="action" value="update_komisi_external">
        
        <div class="rules-card">
            <div class="rules-title">
                <i class="fas fa-coins"></i> Aturan Komisi Eksternal (Marketing External)
            </div>
            
            <div class="rule-item">
                <div class="rule-label">
                    <i class="fas fa-percent"></i> Komisi Eksternal (%)
                </div>
                <div class="rule-input-group">
                    <input type="text" name="komisi_eksternal_persen" id="komisi_eksternal_persen" 
                           class="rule-input desimal-input" 
                           value="<?= number_format($komisi_eksternal_persen, 2, ',', '.') ?>" 
                           placeholder="3.00" inputmode="decimal">
                    <span class="rule-suffix">%</span>
                </div>
                <div class="rule-desc">
                    <i class="fas fa-info-circle"></i> 
                    Persen dari harga unit untuk marketing external (default)
                </div>
            </div>
            
            <div class="rule-item">
                <div class="rule-label">
                    <i class="fas fa-coins"></i> Komisi Eksternal (Rp)
                </div>
                <div class="rule-input-group">
                    <span class="rule-prefix">Rp</span>
                    <input type="text" name="komisi_eksternal_rupiah" id="komisi_eksternal_rupiah" 
                           class="rule-input rupiah-input" 
                           value="<?= $komisi_eksternal_rupiah ? number_format($komisi_eksternal_rupiah, 0, ',', '.') : '' ?>" 
                           placeholder="2.500.000" inputmode="numeric">
                </div>
                <div class="rule-desc">
                    <i class="fas fa-info-circle"></i> 
                    Kosongkan jika ingin pakai persen
                </div>
            </div>
            
            <div style="background: var(--primary-soft); padding: 20px; border-radius: 16px; margin: 24px 0;">
                <p style="margin: 0; color: var(--text); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-lightbulb" style="color: var(--warning); font-size: 18px;"></i> 
                    <strong>Bagaimana sistem bekerja:</strong>
                </p>
                <ul style="margin-top: 12px; padding-left: 20px; color: var(--text-light);">
                    <li>Nilai di atas adalah <strong>DEFAULT</strong> untuk semua unit.</li>
                    <li>Developer bisa mengubah nilai ini <strong>per unit</strong> di halaman Kelola Unit.</li>
                    <li>Saat ini ada <strong><?= $stats['units_with_fixed'] ?? 0 ?></strong> unit dengan komisi fixed (Rp) dan 
                        <strong><?= $stats['units_with_percent_override'] ?? 0 ?></strong> unit dengan override persen.</li>
                    <li>Override per unit akan <strong>MENGALAHKAN</strong> nilai default.</li>
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
    
    <!-- TABEL UNIT DENGAN OVERRIDE -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Unit dengan Override Komisi</h3>
            <div class="table-badge">Total: <?= ($stats['units_with_fixed'] ?? 0) + ($stats['units_with_percent_override'] ?? 0) ?></div>
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Developer</th>
                        <th>Cluster</th>
                        <th>Block</th>
                        <th>Unit</th>
                        <th>Harga</th>
                        <th>Komisi Default</th>
                        <th>Komisi Aktual</th>
                        <th>Tipe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $units_override = $conn->query("
                        SELECT 
                            u.id, u.nomor_unit, u.tipe_unit, u.program, u.harga,
                            u.komisi_eksternal_persen, u.komisi_eksternal_rupiah,
                            b.nama_block, c.nama_cluster,
                            dev.nama_lengkap as developer_name
                        FROM units u
                        JOIN blocks b ON u.block_id = b.id
                        JOIN clusters c ON b.cluster_id = c.id
                        JOIN users dev ON c.developer_id = dev.id
                        WHERE u.komisi_eksternal_persen != 3.00 OR u.komisi_eksternal_rupiah IS NOT NULL
                        ORDER BY dev.nama_lengkap, c.nama_cluster, b.nama_block, u.nomor_unit
                        LIMIT 50
                    ")->fetchAll();
                    
                    if (empty($units_override)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <i class="fas fa-inbox" style="font-size: 40px; color: var(--border); margin-bottom: 10px; display: block;"></i>
                            Tidak ada unit dengan override komisi
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($units_override as $unit): 
                            $default_komisi = $komisi_eksternal_rupiah ? 'Rp ' . number_format($komisi_eksternal_rupiah, 0, ',', '.') : number_format($komisi_eksternal_persen, 2) . '%';
                            
                            if ($unit['komisi_eksternal_rupiah']) {
                                $aktual_komisi = 'Rp ' . number_format($unit['komisi_eksternal_rupiah'], 0, ',', '.');
                                $tipe = 'Fixed (Rp)';
                            } else {
                                $aktual_komisi = number_format($unit['komisi_eksternal_persen'], 2) . '%';
                                $tipe = $unit['komisi_eksternal_persen'] != $komisi_eksternal_persen ? 'Override %' : 'Default %';
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($unit['developer_name']) ?></td>
                            <td><?= htmlspecialchars($unit['nama_cluster']) ?></td>
                            <td><?= htmlspecialchars($unit['nama_block']) ?></td>
                            <td><?= htmlspecialchars($unit['nomor_unit']) ?> (<?= htmlspecialchars($unit['tipe_unit']) ?>)</td>
                            <td>Rp <?= number_format($unit['harga'], 0, ',', '.') ?></td>
                            <td><?= $default_komisi ?></td>
                            <td><strong style="color: var(--secondary);"><?= $aktual_komisi ?></strong></td>
                            <td><span style="background: var(--primary-soft); padding: 4px 8px; border-radius: 20px; font-size: 11px;"><?= $tipe ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Komisi Platform v3.0 (UI Merah)</p>
    </div>
    
</div>

<script>
// ===== FUNGSI FORMAT RUPIAH =====
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

// Format rupiah
document.querySelectorAll('.rupiah-input').forEach(input => {
    input.addEventListener('keyup', function(e) {
        let value = this.value.replace(/[^\d]/g, '');
        if (value) {
            this.value = formatRupiah(value);
        }
    });
    
    input.addEventListener('blur', function() {
        if (!this.value) this.value = '';
    });
});

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