<?php
/**
 * BACKUP.PHP - TAUFIKMARIE.COM ULTIMATE
 * Version: 5.0.0 - Backup Database dengan Manajemen Lengkap
 * FULL CODE - TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// ========== CHECK AUTH ==========
if (!checkAuth()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Hanya admin yang bisa akses halaman ini
if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Admin.');
}

$conn = getDB();

// ========== CREATE BACKUP DIRECTORY ==========
$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// ========== HANDLE BACKUP DOWNLOAD ==========
if (isset($_GET['download']) && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $filepath = $backup_dir . $file;
    
    if (file_exists($filepath) && strpos($file, '.sql') !== false) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    }
}

// ========== HANDLE DELETE BACKUP ==========
if (isset($_GET['delete']) && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $filepath = $backup_dir . $file;
    
    if (file_exists($filepath)) {
        unlink($filepath);
        logSystem("Backup deleted", ['file' => $file, 'by' => $_SESSION['username']], 'INFO', 'backup.log');
        header('Location: backup.php?deleted=1');
        exit();
    }
}

// ========== CREATE NEW BACKUP ==========
$backup_created = false;
$backup_file = '';
$backup_error = '';

if (isset($_GET['action']) && $_GET['action'] == 'create') {
    try {
        // Generate filename
        $date = date('Y-m-d_H-i-s');
        $backup_file = "backup_{$date}.sql";
        $backup_path = $backup_dir . $backup_file;
        
        // Command to backup
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --opt --routines --triggers --events %s > %s 2>&1',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($backup_path)
        );
        
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        
        if (file_exists($backup_path) && filesize($backup_path) > 0) {
            $backup_created = true;
            logSystem("Backup created", ['file' => $backup_file, 'size' => filesize($backup_path)], 'INFO', 'backup.log');
        } else {
            // Fallback: PHP method
            $backup_content = generatePHPBackup();
            if (file_put_contents($backup_path, $backup_content)) {
                $backup_created = true;
                logSystem("Backup created (PHP fallback)", ['file' => $backup_file], 'INFO', 'backup.log');
            } else {
                throw new Exception("Gagal membuat backup dengan PHP fallback");
            }
        }
        
    } catch (Exception $e) {
        $backup_error = $e->getMessage();
        logSystem("Backup failed", ['error' => $e->getMessage()], 'ERROR', 'backup.log');
    }
}

// ========== CLEAN OLD BACKUPS (keep last 10) ==========
$backups = glob($backup_dir . '*.sql');
usort($backups, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

if (count($backups) > 10) {
    $to_delete = array_slice($backups, 10);
    foreach ($to_delete as $file) {
        unlink($file);
    }
    logSystem("Old backups cleaned", ['deleted' => count($to_delete)], 'INFO', 'backup.log');
}

// ========== GET BACKUP LIST ==========
$backup_files = [];
foreach (glob($backup_dir . '*.sql') as $file) {
    $backup_files[] = [
        'name' => basename($file),
        'size' => filesize($file),
        'date' => date('Y-m-d H:i:s', filemtime($file)),
        'timestamp' => filemtime($file)
    ];
}

// Sort by date descending
usort($backup_files, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// ========== GENERATE PHP BACKUP (FALLBACK) ==========
function generatePHPBackup() {
    $conn = getDB();
    if (!$conn) return "-- Database connection failed";
    
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $output = "-- TaufikMarie.com Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Database: " . DB_NAME . "\n";
    $output .= "-- PHP Version: " . phpversion() . "\n\n";
    $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $output .= "SET time_zone = \"+07:00\";\n";
    $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        // Drop table
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        
        // Create table
        $create = $conn->query("SHOW CREATE TABLE `$table`")->fetch();
        if ($create && isset($create['Create Table'])) {
            $output .= $create['Create Table'] . ";\n\n";
        }
        
        // Insert data
        $rows = $conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $values = array_map(function($value) use ($conn) {
                    if ($value === null) return 'NULL';
                    return $conn->quote($value);
                }, array_values($row));
                
                $columns = '`' . implode('`, `', array_keys($row)) . '`';
                $output .= "INSERT INTO `$table` ($columns) VALUES (" . implode(', ', $values) . ");\n";
            }
            $output .= "\n";
        }
    }
    
    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $output .= "-- Backup completed successfully\n";
    
    return $output;
}

// ========== FORMAT SIZE ==========
function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

// ========== SET VARIABLES UNTUK INCLUDE ==========
$page_title = 'Backup Database';
$page_subtitle = 'Buat, Download, dan Kelola Backup';
$page_icon = 'fas fa-database';

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
    <?php if ($backup_created): ?>
    <div class="alert success">
        <i class="fas fa-check-circle fa-lg"></i>
        ✅ Backup baru berhasil dibuat! <?= $backup_file ? "($backup_file)" : '' ?>
    </div>
    <?php endif; ?>
    
    <?php if ($backup_error): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle fa-lg"></i>
        ❌ Gagal membuat backup: <?= $backup_error ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert success" style="background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8;">
        <i class="fas fa-info-circle fa-lg"></i>
        Backup berhasil dihapus.
    </div>
    <?php endif; ?>
    
    <!-- ACTION CARDS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 30px;">
        <!-- Card Buat Backup -->
        <div style="background: white; border-radius: 24px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); border: 1px solid #E0DAD3; text-align: center; transition: all 0.3s; border-left: 4px solid #D64F3C;">
            <div style="font-size: 48px; color: #D64F3C; margin-bottom: 20px;">
                <i class="fas fa-plus-circle"></i>
            </div>
            <h3 style="font-size: 20px; font-weight: 700; color: #1B4A3C; margin-bottom: 12px;">Buat Backup Baru</h3>
            <p style="color: #4A5A54; font-size: 14px; margin-bottom: 24px; line-height: 1.6;">Buat cadangan database terbaru. File akan disimpan di server dan dapat diunduh.</p>
            <a href="?action=create" style="background: linear-gradient(135deg, #D64F3C, #FF8A5C); color: white; border: none; padding: 14px 28px; border-radius: 60px; font-weight: 600; font-size: 15px; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; text-decoration: none; box-shadow: 0 10px 20px rgba(214,79,60,0.2);">
                <i class="fas fa-database"></i> Buat Backup Sekarang
            </a>
        </div>
        
        <!-- Card Export CSV -->
        <div style="background: white; border-radius: 24px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); border: 1px solid #E0DAD3; text-align: center; transition: all 0.3s; border-left: 4px solid #2A9D8F;">
            <div style="font-size: 48px; color: #2A9D8F; margin-bottom: 20px;">
                <i class="fas fa-file-csv"></i>
            </div>
            <h3 style="font-size: 20px; font-weight: 700; color: #1B4A3C; margin-bottom: 12px;">Export Data Leads</h3>
            <p style="color: #4A5A54; font-size: 14px; margin-bottom: 24px; line-height: 1.6;">Export semua leads dalam format CSV untuk analisis lebih lanjut di Excel.</p>
            <a href="api/export.php?key=<?= API_KEY ?>&format=csv" target="_blank" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0); color: white; border: none; padding: 14px 28px; border-radius: 60px; font-weight: 600; font-size: 15px; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; text-decoration: none; box-shadow: 0 10px 20px rgba(42,157,143,0.2);">
                <i class="fas fa-download"></i> Download CSV
            </a>
        </div>
        
        <!-- Card Informasi -->
        <div style="background: white; border-radius: 24px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); border: 1px solid #E0DAD3; text-align: center; transition: all 0.3s; border-left: 4px solid #1B4A3C;">
            <div style="font-size: 48px; color: #1B4A3C; margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i>
            </div>
            <h3 style="font-size: 20px; font-weight: 700; color: #1B4A3C; margin-bottom: 12px;">Informasi Backup</h3>
            <p style="color: #4A5A54; font-size: 14px; margin-bottom: 16px;">Total <?= count($backup_files) ?> file backup. Maksimal 10 backup terbaru disimpan.</p>
            <div style="background: #E7F3EF; padding: 12px; border-radius: 40px; font-size: 13px; color: #1B4A3C;">
                <i class="fas fa-folder-open"></i> Lokasi: /admin/backups/
            </div>
        </div>
    </div>
    
    <!-- DAFTAR BACKUP -->
    <div style="background: white; border-radius: 28px; padding: 28px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); border: 1px solid #E0DAD3;">
        <h3 style="color: #1B4A3C; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-size: 20px; font-weight: 700;">
            <i class="fas fa-history" style="color: #D64F3C;"></i> 
            Daftar Backup (<?= count($backup_files) ?>)
        </h3>
        
        <?php if (empty($backup_files)): ?>
        <div style="text-align: center; padding: 50px 20px; background: #F5F3F0; border-radius: 20px;">
            <i class="fas fa-folder-open" style="font-size: 60px; color: #7A8A84; margin-bottom: 15px; opacity: 0.5;"></i>
            <p style="color: #4A5A54; font-size: 16px;">Belum ada file backup. Klik "Buat Backup Baru" untuk memulai.</p>
        </div>
        <?php else: ?>
        
        <!-- Table Backup -->
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: linear-gradient(135deg, #F5F3F0, #E7F3EF);">
                        <th style="padding: 16px; text-align: left; font-weight: 700; color: #1B4A3C; border-radius: 16px 0 0 0;">Nama File</th>
                        <th style="padding: 16px; text-align: left; font-weight: 700; color: #1B4A3C;">Ukuran</th>
                        <th style="padding: 16px; text-align: left; font-weight: 700; color: #1B4A3C;">Tanggal Dibuat</th>
                        <th style="padding: 16px; text-align: center; font-weight: 700; color: #1B4A3C; border-radius: 0 16px 0 0;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backup_files as $backup): ?>
                    <tr style="border-bottom: 1px solid #E0DAD3;">
                        <td style="padding: 16px;">
                            <code style="background: #F5F3F0; padding: 6px 12px; border-radius: 8px; font-family: monospace; color: #1B4A3C;"><?= htmlspecialchars($backup['name']) ?></code>
                        </td>
                        <td style="padding: 16px;">
                            <span style="background: #E7F3EF; padding: 6px 14px; border-radius: 40px; font-size: 12px; font-weight: 600; color: #1B4A3C;">
                                <?= formatSize($backup['size']) ?>
                            </span>
                        </td>
                        <td style="padding: 16px; color: #4A5A54;">
                            <i class="fas fa-calendar-alt" style="color: #D64F3C; margin-right: 6px;"></i>
                            <?= $backup['date'] ?>
                        </td>
                        <td style="padding: 16px; text-align: center;">
                            <div style="display: flex; gap: 8px; justify-content: center;">
                                <a href="?download=1&file=<?= urlencode($backup['name']) ?>" 
                                   style="background: #2A9D8F; color: white; width: 38px; height: 38px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s;"
                                   title="Download"
                                   onmouseover="this.style.transform='scale(1.1)'"
                                   onmouseout="this.style.transform='scale(1)'">
                                    <i class="fas fa-download"></i>
                                </a>
                                <a href="?delete=1&file=<?= urlencode($backup['name']) ?>" 
                                   style="background: #D64F3C; color: white; width: 38px; height: 38px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s;"
                                   title="Hapus"
                                   onclick="return confirm('Yakin ingin menghapus backup <?= $backup['name'] ?>?')"
                                   onmouseover="this.style.transform='scale(1.1)'"
                                   onmouseout="this.style.transform='scale(1)'">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- INFO PANEL -->
<div style="background: linear-gradient(135deg, #E7F3EF, #d4e8e0); border-radius: 24px; padding: 24px; margin-top: 30px; border-left: 4px solid #D64F3C;">
    <h4 style="color: #1B4A3C; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-size: 18px;">
        <i class="fas fa-shield-alt" style="color: #D64F3C; font-size: 20px; width: auto; height: auto;"></i>
        Tips Keamanan Backup
    </h4>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <!-- Tip 1 -->
        <div style="display: flex; align-items: center; gap: 12px; background: white; padding: 16px; border-radius: 16px; box-shadow: 0 4px 8px rgba(0,0,0,0.03);">
            <div style="background: #2A9D8F; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; flex-shrink: 0;">
                <i class="fas fa-check" style="font-size: 18px; width: auto; height: auto;"></i>
            </div>
            <span style="color: #1A2A24; font-size: 14px;">Download backup secara rutin dan simpan di tempat aman.</span>
        </div>
        
        <!-- Tip 2 -->
        <div style="display: flex; align-items: center; gap: 12px; background: white; padding: 16px; border-radius: 16px; box-shadow: 0 4px 8px rgba(0,0,0,0.03);">
            <div style="background: #2A9D8F; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; flex-shrink: 0;">
                <i class="fas fa-check" style="font-size: 18px; width: auto; height: auto;"></i>
            </div>
            <span style="color: #1A2A24; font-size: 14px;">Hanya 10 backup terbaru yang disimpan di server.</span>
        </div>
        
        <!-- Tip 3 -->
        <div style="display: flex; align-items: center; gap: 12px; background: white; padding: 16px; border-radius: 16px; box-shadow: 0 4px 8px rgba(0,0,0,0.03);">
            <div style="background: #2A9D8F; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; flex-shrink: 0;">
                <i class="fas fa-check" style="font-size: 18px; width: auto; height: auto;"></i>
            </div>
            <span style="color: #1A2A24; font-size: 14px;">Backup otomatis dihapus jika lebih dari 10 file.</span>
        </div>
    </div>
</div>
    
    <!-- FOOTER -->
    <div style="text-align: center; margin-top: 50px; padding: 25px; color: #7A8A84; font-size: 13px; border-top: 1px solid #E0DAD3;">
        <p>© <?= date('Y') ?> TaufikMarie.com - Backup System Version 5.0.0</p>
    </div>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function updateDateTime() {
        const dateEl = document.getElementById('currentDate')?.querySelector('span');
        const timeEl = document.getElementById('currentTime')?.querySelector('span');
        if (dateEl && timeEl) {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateEl.textContent = now.toLocaleDateString('id-ID', options);
            timeEl.textContent = now.toLocaleTimeString('id-ID', { hour12: false });
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
});
</script>

<!-- FOOTER -->
<?php include 'includes/footer.php'; ?>