<?php
/**
 * DEVELOPER_UNITS.PHP - LEADENGINE
 * Version: 6.0.0 - FIXED: Hapus override komisi, sinkron dengan aturan developer & platform
 * MOBILE FIRST UI - INPUT RUPIAH OTOMATIS + KEYPAD ANGKA
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

// Ambil block_id dari URL
$block_id = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;

// Validasi block milik developer ini
if ($block_id > 0) {
    $check = $conn->prepare("
        SELECT b.*, c.nama_cluster, c.developer_id 
        FROM blocks b
        JOIN clusters c ON b.cluster_id = c.id
        WHERE b.id = ? AND c.developer_id = ?
    ");
    $check->execute([$block_id, $developer_id]);
    $block = $check->fetch();
    
    if (!$block) {
        header('Location: /admin/developer_clusters.php');
        exit();
    }
    
    $cluster_id = $block['cluster_id'];
    $cluster_name = $block['nama_cluster'];
    $block_name = $block['nama_block'];
} else {
    header('Location: /admin/developer_clusters.php');
    exit();
}

// ========== AMBIL DATA MASTER ==========
// Program Booking
$program_booking = [];
$stmt = $conn->prepare("
    SELECT * FROM program_booking 
    WHERE developer_id = ? AND is_active = 1 
    ORDER BY booking_fee
");
$stmt->execute([$developer_id]);
$program_booking = $stmt->fetchAll();

// Kategori Biaya
$biaya_kategoris = [];
$stmt = $conn->prepare("
    SELECT * FROM biaya_kategori 
    WHERE developer_id = ? AND is_active = 1 
    ORDER BY nama_kategori
");
$stmt->execute([$developer_id]);
$biaya_kategoris = $stmt->fetchAll();

// Biaya per Block untuk block ini
$block_biayas = [];
$stmt = $conn->prepare("
    SELECT * FROM block_biaya_tambahan 
    WHERE block_id = ? 
    ORDER BY nama_biaya
");
$stmt->execute([$block_id]);
$block_biayas = $stmt->fetchAll();

// ========== AMBIL ATURAN KOMISI DARI DEVELOPER & PLATFORM ==========
// Ambil komisi internal dari developer_komisi_rules
$komisi_internal = [];
$inhouse_value = 1000000;
$canvasing_value = 3.00;

try {
    $stmt = $conn->prepare("
        SELECT kr.*, mt.type_name, mt.commission_type
        FROM komisi_rules kr
        JOIN marketing_types mt ON kr.marketing_type_id = mt.id
        WHERE kr.developer_id = ?
    ");
    $stmt->execute([$developer_id]);
    $komisi_internal = $stmt->fetchAll();
    
    $komisi_data = [];
    foreach ($komisi_internal as $k) {
        $komisi_data[$k['type_name']] = $k['commission_value'];
    }
    
    $inhouse_value = $komisi_data['sales_inhouse'] ?? 1000000;
    $canvasing_value = $komisi_data['sales_canvasing'] ?? 3.00;
    
} catch (Exception $e) {
    error_log("Error loading komisi rules: " . $e->getMessage());
}

// Ambil komisi split dari platform
$komisi_split_platform = getPlatformKomisiSplit(); // Dari config.php
$split_type = $komisi_split_platform['type']; // 'PERCENT' atau 'FIXED'
$split_value = $komisi_split_platform['value']; // 2.50 atau 2500000

// Ambil komisi eksternal default dari platform (untuk marketing external)
$komisi_eksternal_persen = 3.00;
$komisi_eksternal_rupiah = null;

try {
    $stmt = $conn->query("SELECT * FROM marketing_config WHERE id = 2");
    $config = $stmt->fetch();
    if ($config) {
        $komisi_eksternal_persen = $config['komisi_eksternal_persen'] ?? 3.00;
        $komisi_eksternal_rupiah = $config['komisi_eksternal_rupiah'] ?? null;
    }
} catch (Exception $e) {
    error_log("Error loading marketing config: " . $e->getMessage());
}

// ========== PROSES CRUD ==========
$success = '';
$error = '';

// Hapus unit
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    $check = $conn->prepare("
        SELECT u.id FROM units u
        JOIN blocks b ON u.block_id = b.id
        JOIN clusters c ON b.cluster_id = c.id
        WHERE u.id = ? AND c.developer_id = ?
    ");
    $check->execute([$delete_id, $developer_id]);
    
    if ($check->fetch()) {
        try {
            $conn->prepare("DELETE FROM unit_biaya_tambahan WHERE unit_id = ?")->execute([$delete_id]);
            $stmt = $conn->prepare("DELETE FROM units WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "✅ Unit berhasil dihapus!";
            logSystem("Unit deleted", ['id' => $delete_id], 'INFO', 'unit.log');
        } catch (Exception $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    } else {
        $error = "❌ Unit tidak ditemukan atau bukan milik Anda";
    }
}

// Tambah/Edit unit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_single' || $action == 'edit') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nomor_unit = trim($_POST['nomor_unit'] ?? '');
        $tipe_unit = trim($_POST['tipe_unit'] ?? '');
        $program = $_POST['program'] ?? 'Subsidi';
        $luas_tanah = !empty($_POST['luas_tanah']) ? (float)$_POST['luas_tanah'] : null;
        $luas_bangunan = !empty($_POST['luas_bangunan']) ? (float)$_POST['luas_bangunan'] : null;
        
        // Ambil harga dari hidden field
        $harga = !empty($_POST['harga']) ? (float)$_POST['harga'] : null;
        $harga_booking = !empty($_POST['harga_booking']) ? (float)$_POST['harga_booking'] : 0;
        
        $status = $_POST['status'] ?? 'AVAILABLE';
        $program_booking_id = !empty($_POST['program_booking_id']) ? (int)$_POST['program_booking_id'] : null;
        
        // ===== KOMISI - DIAMBIL DARI ATURAN DEFAULT (TIDAK BISA DI-OVERRIDE) =====
        $komisi_eksternal_persen = $komisi_eksternal_persen;
        $komisi_eksternal_rupiah = $komisi_eksternal_rupiah;
        $komisi_internal_rupiah = $inhouse_value;
        
        // Komisi split dari platform
        $komisi_split_persen = ($split_type == 'PERCENT') ? $split_value : null;
        $komisi_split_rupiah = ($split_type == 'FIXED') ? $split_value : null;
        
        $biaya_tambahan_json = $_POST['biaya_tambahan_json'] ?? '[]';
        
        if (empty($nomor_unit)) {
            $error = "❌ Nomor unit wajib diisi!";
        } elseif (empty($tipe_unit)) {
            $error = "❌ Tipe unit wajib diisi!";
        } elseif (empty($harga)) {
            $error = "❌ Harga unit wajib diisi!";
        } else {
            try {
                $conn->beginTransaction();
                
                if ($action == 'add_single') {
                    $check = $conn->prepare("SELECT id FROM units WHERE block_id = ? AND nomor_unit = ?");
                    $check->execute([$block_id, $nomor_unit]);
                    
                    if ($check->fetch()) {
                        $error = "❌ Nomor unit sudah ada dalam block ini!";
                        $conn->rollBack();
                    } else {
                        $stmt = $conn->prepare("
                            INSERT INTO units (
                                cluster_id, block_id, nomor_unit, tipe_unit, program,
                                luas_tanah, luas_bangunan, harga, harga_booking, status,
                                komisi_eksternal_persen, komisi_eksternal_rupiah, komisi_internal_rupiah,
                                komisi_split_persen, komisi_split_rupiah,
                                program_booking_id, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $cluster_id, $block_id, $nomor_unit, $tipe_unit, $program,
                            $luas_tanah, $luas_bangunan, $harga, $harga_booking, $status,
                            $komisi_eksternal_persen, $komisi_eksternal_rupiah, $komisi_internal_rupiah,
                            $komisi_split_persen, $komisi_split_rupiah,
                            $program_booking_id
                        ]);
                        
                        $unit_id = $conn->lastInsertId();
                        
                        $biaya_array = json_decode($biaya_tambahan_json, true);
                        if (!empty($biaya_array) && is_array($biaya_array)) {
                            $insert_biaya = $conn->prepare("
                                INSERT INTO unit_biaya_tambahan 
                                (unit_id, biaya_kategori_id, quantity, nominal_final, keterangan, created_at)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            
                            foreach ($biaya_array as $b) {
                                if (!empty($b['nama']) && $b['nominal'] > 0) {
                                    $kategori_id = isset($b['kategori_id']) && $b['kategori_id'] ? $b['kategori_id'] : null;
                                    $keterangan = $b['is_custom'] ? 'Custom: ' . $b['nama'] : $b['nama'];
                                    
                                    $insert_biaya->execute([
                                        $unit_id,
                                        $kategori_id,
                                        $b['quantity'] ?? 1,
                                        $b['nominal'],
                                        $keterangan
                                    ]);
                                }
                            }
                        }
                        
                        $conn->commit();
                        $success = "✅ Unit berhasil ditambahkan!";
                        logSystem("Unit added", ['unit' => $nomor_unit], 'INFO', 'unit.log');
                    }
                    
                } elseif ($action == 'edit') {
                    $check = $conn->prepare("
                        SELECT u.id FROM units u
                        JOIN blocks b ON u.block_id = b.id
                        JOIN clusters c ON b.cluster_id = c.id
                        WHERE u.id = ? AND c.developer_id = ?
                    ");
                    $check->execute([$id, $developer_id]);
                    
                    if ($check->fetch()) {
                        $stmt = $conn->prepare("
                            UPDATE units SET 
                                nomor_unit = ?,
                                tipe_unit = ?,
                                program = ?,
                                luas_tanah = ?,
                                luas_bangunan = ?,
                                harga = ?,
                                harga_booking = ?,
                                status = ?,
                                komisi_eksternal_persen = ?,
                                komisi_eksternal_rupiah = ?,
                                komisi_internal_rupiah = ?,
                                komisi_split_persen = ?,
                                komisi_split_rupiah = ?,
                                program_booking_id = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $nomor_unit, $tipe_unit, $program,
                            $luas_tanah, $luas_bangunan, $harga, $harga_booking, $status,
                            $komisi_eksternal_persen, $komisi_eksternal_rupiah, $komisi_internal_rupiah,
                            $komisi_split_persen, $komisi_split_rupiah,
                            $program_booking_id,
                            $id
                        ]);
                        
                        $conn->prepare("DELETE FROM unit_biaya_tambahan WHERE unit_id = ?")->execute([$id]);
                        
                        $biaya_array = json_decode($biaya_tambahan_json, true);
                        if (!empty($biaya_array) && is_array($biaya_array)) {
                            $insert_biaya = $conn->prepare("
                                INSERT INTO unit_biaya_tambahan 
                                (unit_id, biaya_kategori_id, quantity, nominal_final, keterangan, created_at)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            
                            foreach ($biaya_array as $b) {
                                if (!empty($b['nama']) && $b['nominal'] > 0) {
                                    $kategori_id = isset($b['kategori_id']) && $b['kategori_id'] ? $b['kategori_id'] : null;
                                    $keterangan = $b['is_custom'] ? 'Custom: ' . $b['nama'] : $b['nama'];
                                    
                                    $insert_biaya->execute([
                                        $id,
                                        $kategori_id,
                                        $b['quantity'] ?? 1,
                                        $b['nominal'],
                                        $keterangan
                                    ]);
                                }
                            }
                        }
                        
                        $conn->commit();
                        $success = "✅ Unit berhasil diupdate!";
                        logSystem("Unit updated", ['id' => $id], 'INFO', 'unit.log');
                    } else {
                        $error = "❌ Unit tidak ditemukan atau bukan milik Anda";
                        $conn->rollBack();
                    }
                }
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    }
    
    // Tambah massal
    elseif ($action == 'add_massal') {
        $prefix = trim($_POST['prefix'] ?? '');
        $start = (int)($_POST['start'] ?? 1);
        $end = (int)($_POST['end'] ?? 1);
        $tipe_unit = trim($_POST['tipe_unit_massal'] ?? '');
        $program = $_POST['program_massal'] ?? 'Subsidi';
        $luas_tanah = !empty($_POST['luas_tanah_massal']) ? (float)$_POST['luas_tanah_massal'] : null;
        $luas_bangunan = !empty($_POST['luas_bangunan_massal']) ? (float)$_POST['luas_bangunan_massal'] : null;
        
        $harga = !empty($_POST['harga_massal']) ? (float)$_POST['harga_massal'] : null;
        $harga_booking = !empty($_POST['harga_booking_massal']) ? (float)$_POST['harga_booking_massal'] : 0;
        
        $program_booking_id = !empty($_POST['program_booking_id_massal']) ? (int)$_POST['program_booking_id_massal'] : null;
        
        // Komisi massal - ambil dari aturan default
        $komisi_eksternal_persen = $komisi_eksternal_persen;
        $komisi_eksternal_rupiah = $komisi_eksternal_rupiah;
        $komisi_internal_rupiah = $inhouse_value;
        $komisi_split_persen = ($split_type == 'PERCENT') ? $split_value : null;
        $komisi_split_rupiah = ($split_type == 'FIXED') ? $split_value : null;
        
        if (empty($tipe_unit)) {
            $error = "❌ Tipe unit wajib diisi!";
        } elseif (empty($harga)) {
            $error = "❌ Harga unit wajib diisi!";
        } elseif ($start > $end) {
            $error = "❌ Nomor awal harus lebih kecil dari nomor akhir!";
        } else {
            try {
                $conn->beginTransaction();
                
                $inserted = 0;
                $skipped = 0;
                
                for ($i = $start; $i <= $end; $i++) {
                    $nomor = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
                    
                    $check = $conn->prepare("SELECT id FROM units WHERE block_id = ? AND nomor_unit = ?");
                    $check->execute([$block_id, $nomor]);
                    
                    if (!$check->fetch()) {
                        $stmt = $conn->prepare("
                            INSERT INTO units (
                                cluster_id, block_id, nomor_unit, tipe_unit, program,
                                luas_tanah, luas_bangunan, harga, harga_booking, status,
                                komisi_eksternal_persen, komisi_eksternal_rupiah, komisi_internal_rupiah,
                                komisi_split_persen, komisi_split_rupiah,
                                program_booking_id, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'AVAILABLE', ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $cluster_id, $block_id, $nomor, $tipe_unit, $program,
                            $luas_tanah, $luas_bangunan, $harga, $harga_booking,
                            $komisi_eksternal_persen, $komisi_eksternal_rupiah, $komisi_internal_rupiah,
                            $komisi_split_persen, $komisi_split_rupiah,
                            $program_booking_id
                        ]);
                        $inserted++;
                    } else {
                        $skipped++;
                    }
                }
                
                $conn->commit();
                $success = "✅ Berhasil menambahkan $inserted unit" . ($skipped > 0 ? " ($skipped unit sudah ada)" : "");
                logSystem("Mass units added", ['inserted' => $inserted, 'skipped' => $skipped], 'INFO', 'unit.log');
                
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    }
}

// Ambil data unit milik block ini
$units = [];
$stmt = $conn->prepare("
    SELECT u.*, 
           pb.nama_program, pb.booking_fee as program_booking_fee, pb.is_all_in
    FROM units u
    LEFT JOIN program_booking pb ON u.program_booking_id = pb.id
    WHERE u.block_id = ?
    ORDER BY 
        CASE 
            WHEN u.nomor_unit REGEXP '^[0-9]+$' THEN LPAD(u.nomor_unit, 10, '0')
            ELSE u.nomor_unit 
        END
");
$stmt->execute([$block_id]);
$units = $stmt->fetchAll();

// Ambil biaya tambahan untuk setiap unit
foreach ($units as &$unit) {
    $stmt = $conn->prepare("
        SELECT * FROM unit_biaya_tambahan 
        WHERE unit_id = ?
        ORDER BY id
    ");
    $stmt->execute([$unit['id']]);
    $unit['biaya_tambahan'] = $stmt->fetchAll();
}

// Hitung statistik
$total_units = count($units);
$available = 0;
$booked = 0;
$sold = 0;

foreach ($units as $u) {
    if ($u['status'] == 'AVAILABLE') $available++;
    elseif ($u['status'] == 'BOOKED') $booked++;
    elseif ($u['status'] == 'SOLD') $sold++;
}

$page_title = 'Kelola Unit';
$page_subtitle = $cluster_name . ' - Block ' . $block_name;
$page_icon = 'fas fa-home';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== MOBILE FIRST VARIABLES ===== */
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
    --gold: #E3B584;
    --platform: #D64F3C;
}

/* ===== MOBILE FIRST LAYOUT ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}

.main-content {
    width: 100%;
    min-height: 100vh;
    padding: 12px;
    margin-left: 0 !important;
}

/* ===== TOP BAR - MOBILE FIRST ===== */
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
    flex-shrink: 0;
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

/* ===== BREADCRUMB ===== */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 13px;
    overflow-x: auto;
    white-space: nowrap;
    padding: 4px 0;
    -webkit-overflow-scrolling: touch;
}

.breadcrumb::-webkit-scrollbar {
    display: none;
}

.breadcrumb a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    padding: 8px 12px;
    background: white;
    border-radius: 40px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.breadcrumb i {
    color: var(--secondary);
    font-size: 12px;
}

.breadcrumb span {
    color: var(--text-muted);
    padding: 8px 12px;
    background: var(--surface);
    border-radius: 40px;
}

/* ===== STATS CARD - HORIZONTAL SCROLL ===== */
.stats-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    padding: 4px 0 16px 0;
    margin-bottom: 8px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

.stats-horizontal::-webkit-scrollbar {
    height: 4px;
}

.stats-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.stats-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.stat-card {
    flex: 0 0 130px;
    background: white;
    border-radius: 16px;
    padding: 14px;
    border-left: 4px solid;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.stat-card.available { border-left-color: var(--success); }
.stat-card.booked { border-left-color: var(--warning); }
.stat-card.sold { border-left-color: var(--danger); }

.stat-icon {
    font-size: 20px;
    margin-bottom: 8px;
}

.stat-card.available .stat-icon { color: var(--success); }
.stat-card.booked .stat-icon { color: #B87C00; }
.stat-card.sold .stat-icon { color: var(--danger); }

.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 2px;
}

.stat-value {
    font-size: 22px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

/* ===== ALERT ===== */
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

/* ===== ACTION BAR ===== */
.action-bar {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.btn-add {
    width: 100%;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border: none;
    padding: 16px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(214,79,60,0.2);
    transition: all 0.3s;
    min-height: 56px;
}

.btn-add i {
    font-size: 16px;
    width: auto;
    height: auto;
}

.btn-add:active {
    transform: scale(0.98);
}

.btn-massal {
    width: 100%;
    background: linear-gradient(135deg, #4A90E2, #6DA5F0);
    color: white;
    border: none;
    padding: 16px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(74,144,226,0.2);
    min-height: 56px;
}

.btn-back {
    width: 100%;
    background: var(--border);
    color: var(--text);
    border: none;
    padding: 14px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    text-decoration: none;
    min-height: 48px;
}

.btn-back i {
    color: var(--secondary);
}

/* ===== UNIT CARDS - HORIZONTAL DI MOBILE ===== */
.units-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    padding: 8px 0 20px 0;
    margin-bottom: 16px;
    -webkit-overflow-scrolling: touch;
}

.units-horizontal::-webkit-scrollbar {
    height: 4px;
}

.units-horizontal::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.units-horizontal::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.unit-card {
    flex: 0 0 320px;
    background: white;
    border-radius: 24px;
    padding: 18px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    border-left: 6px solid;
    transition: transform 0.2s;
}

.unit-card.status-AVAILABLE { border-left-color: var(--success); }
.unit-card.status-BOOKED { border-left-color: var(--warning); }
.unit-card.status-SOLD { border-left-color: var(--danger); }

.unit-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.unit-number {
    font-weight: 800;
    color: var(--primary);
    font-size: 22px;
    background: var(--primary-soft);
    padding: 8px 16px;
    border-radius: 16px;
}

.unit-status {
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.unit-status.AVAILABLE {
    background: var(--success);
    color: white;
}

.unit-status.BOOKED {
    background: var(--warning);
    color: #1A2A24;
}

.unit-status.SOLD {
    background: var(--danger);
    color: white;
}

/* ===== UNIT DETAILS ===== */
.unit-details {
    margin: 16px 0;
    padding: 12px;
    background: var(--primary-soft);
    border-radius: 16px;
}

.unit-detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
}

.unit-detail-row:last-child {
    margin-bottom: 0;
}

.unit-detail-label {
    color: var(--text-muted);
    font-weight: 500;
}

.unit-detail-value {
    font-weight: 700;
    color: var(--primary);
}

.unit-program {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
    margin-right: 8px;
}

.unit-program.Subsidi {
    background: #2A9D8F;
    color: white;
}

.unit-program.Komersil {
    background: #4A90E2;
    color: white;
}

/* ===== PROGRAM BOOKING BADGE ===== */
.program-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 700;
    background: var(--primary);
    color: white;
    margin-top: 5px;
}

.program-badge.allin {
    background: #D64F3C;
}

/* ===== BIAYA TAMBAHAN LIST ===== */
.biaya-list {
    margin: 10px 0;
    padding: 8px;
    background: var(--bg);
    border-radius: 12px;
    font-size: 11px;
}

.biaya-item {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    border-bottom: 1px dashed var(--border);
}

.biaya-item:last-child {
    border-bottom: none;
}

.biaya-nama {
    color: var(--text-light);
}

.biaya-nominal {
    font-weight: 700;
    color: var(--secondary);
}

/* ===== KOMISI TAG - FIX DESIMAL ===== */
.komisi-tag {
    background: white;
    padding: 8px 12px;
    border-radius: 12px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid var(--border);
}

.komisi-label {
    font-size: 11px;
    color: var(--text-muted);
}

.komisi-value {
    font-weight: 700;
    color: var(--primary);
    font-size: 13px;
}

.komisi-value.eksternal {
    color: #4A90E2;
}

.komisi-value.internal {
    color: #D64F3C;
}

.komisi-value.split {
    color: var(--platform);
}

.badge-platform {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    background: var(--platform);
    color: white;
    font-size: 8px;
    font-weight: 700;
    margin-left: 8px;
}

/* ===== PRICE TAGS ===== */
.price-tag {
    background: white;
    padding: 8px 12px;
    border-radius: 12px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid var(--border);
}

.price-label {
    font-size: 12px;
    color: var(--text-muted);
}

.price-value {
    font-weight: 800;
    color: var(--primary);
    font-size: 16px;
}

.price-value.booking {
    color: var(--secondary);
}

/* ===== BLOCK BIAYA INFO ===== */
.block-biaya-info {
    background: #E8F0FE;
    padding: 10px;
    border-radius: 12px;
    margin: 10px 0;
    font-size: 11px;
    border-left: 4px solid #4A90E2;
}

.block-biaya-info i {
    color: #4A90E2;
    margin-right: 4px;
}

/* ===== ACTION BUTTONS ===== */
.unit-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}

.btn-icon {
    flex: 1;
    min-width: 44px;
    min-height: 44px;
    border: none;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    border: 1px solid var(--border);
}

.btn-icon i {
    font-size: 16px;
    width: auto;
    height: auto;
}

.btn-icon.edit {
    background: #fff8e1;
    color: #B87C00;
    border-color: #B87C00;
}

.btn-icon.edit:active {
    background: #B87C00;
    color: white;
}

.btn-icon.delete {
    background: #ffeeed;
    color: var(--danger);
    border-color: var(--danger);
}

.btn-icon.delete:active {
    background: var(--danger);
    color: white;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 24px;
    width: 100%;
}

.empty-state i {
    font-size: 60px;
    color: #E0DAD3;
    margin-bottom: 16px;
}

.empty-state h4 {
    color: var(--text);
    margin-bottom: 8px;
    font-size: 18px;
}

.empty-state p {
    color: var(--text-muted);
    margin-bottom: 20px;
    font-size: 14px;
}

/* ===== TABS ===== */
.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    overflow-x: auto;
    padding: 4px 0;
    -webkit-overflow-scrolling: touch;
}

.tabs::-webkit-scrollbar {
    display: none;
}

.tab-btn {
    flex: 0 0 auto;
    padding: 12px 20px;
    background: white;
    border: 2px solid var(--border);
    border-radius: 40px;
    font-weight: 600;
    font-size: 14px;
    color: var(--text);
    cursor: pointer;
    white-space: nowrap;
    min-height: 48px;
}

.tab-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.tab-btn i {
    margin-right: 6px;
    font-size: 14px;
}

/* ===== MODAL MOBILE FIRST ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    z-index: 10000;
    align-items: flex-end;
    justify-content: center;
    padding: 0;
}

.modal.show {
    display: flex !important;
}

.modal-content {
    background: white;
    border-radius: 28px 28px 0 0;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}

.modal-header {
    padding: 20px 20px 16px;
    border-bottom: 2px solid var(--primary-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    color: var(--primary);
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-header h2 i {
    color: var(--secondary);
    font-size: 20px;
}

.modal-close {
    width: 44px;
    height: 44px;
    background: var(--primary-soft);
    border: none;
    border-radius: 12px;
    color: var(--secondary);
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    max-height: 60vh;
}

.modal-footer {
    padding: 16px 20px 24px;
    display: flex;
    gap: 12px;
    border-top: 1px solid var(--border);
}

.modal-footer button {
    flex: 1;
    min-height: 48px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 15px;
}

/* ===== FORM ELEMENTS ===== */
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

/* ===== INPUT RUPIAH & DESIMAL STYLES ===== */
.rupiah-input, .desimal-input {
    -webkit-appearance: none;
    appearance: none;
}

input[type="text"].rupiah-input,
input[type="text"].desimal-input,
input[type="text"][inputmode="numeric"] {
    -webkit-appearance: none;
    appearance: none;
}

.form-row {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.form-row .form-group {
    flex: 1;
    min-width: 120px;
}

.range-input {
    display: flex;
    align-items: center;
    gap: 8px;
}

.range-input input {
    flex: 1;
    text-align: center;
}

.range-input span {
    color: var(--text-muted);
    font-size: 14px;
}

/* ===== RADIO GROUP ===== */
.radio-group {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

.radio-option {
    flex: 1;
}

.radio-option input[type="radio"] {
    display: none;
}

.radio-option label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    background: white;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 52px;
}

.radio-option input[type="radio"]:checked + label {
    border-color: var(--secondary);
    background: linear-gradient(135deg, rgba(214,79,60,0.05), rgba(255,107,74,0.05));
}

.radio-option label i {
    color: var(--secondary);
    font-size: 16px;
}

/* ===== BIAYA TAMBAHAN ROW ===== */
.biaya-row {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.biaya-row select,
.biaya-row input {
    min-height: 44px;
}

.btn-add-row {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 10px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-icon-small {
    width: 44px;
    height: 44px;
    border: none;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
    background: white;
    border: 1px solid var(--border);
    color: var(--danger);
}

.btn-icon-small:hover {
    background: var(--danger);
    color: white;
}

/* ===== BUTTONS ===== */
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
    min-height: 52px;
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
    min-height: 52px;
}

/* ===== FOOTER ===== */
.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

/* ===== TABLET & DESKTOP UPGRADE ===== */
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
    
    .action-bar {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .btn-add, .btn-massal, .btn-back {
        width: auto;
        padding: 14px 28px;
    }
    
    .units-horizontal {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        overflow-x: visible;
        gap: 20px;
    }
    
    .unit-card {
        flex: none;
        width: auto;
    }
    
    .modal {
        align-items: center;
        padding: 20px;
    }
    
    .modal-content {
        border-radius: 28px;
        max-width: 800px;
        animation: modalFade 0.3s ease;
    }
    
    @keyframes modalFade {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }
    
    .form-row {
        flex-wrap: nowrap;
    }
    
    .biaya-row {
        flex-wrap: nowrap;
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
                <span><?= htmlspecialchars($page_subtitle) ?></span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <a href="/admin/developer_clusters.php"><i class="fas fa-layer-group"></i> Cluster</a>
        <i class="fas fa-chevron-right"></i>
        <a href="/admin/developer_blocks.php?cluster_id=<?= $cluster_id ?>"><i class="fas fa-cubes"></i> <?= htmlspecialchars($cluster_name) ?></a>
        <i class="fas fa-chevron-right"></i>
        <span>Block <?= htmlspecialchars($block_name) ?></span>
    </div>
    
    <!-- STATS HORIZONTAL SCROLL -->
    <div class="stats-horizontal">
        <div class="stat-card available">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Available</div>
            <div class="stat-value"><?= $available ?></div>
        </div>
        <div class="stat-card booked">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Booked</div>
            <div class="stat-value"><?= $booked ?></div>
        </div>
        <div class="stat-card sold">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-label">Sold</div>
            <div class="stat-value"><?= $sold ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #4A90E2;">
            <div class="stat-icon"><i class="fas fa-tag"></i></div>
            <div class="stat-label">Total</div>
            <div class="stat-value"><?= $total_units ?></div>
        </div>
    </div>
    
    <!-- BLOCK BIAYA INFO -->
    <?php if (!empty($block_biayas)): ?>
    <div class="block-biaya-info">
        <i class="fas fa-info-circle"></i> <strong>Biaya khusus block ini:</strong>
        <?php foreach ($block_biayas as $bb): ?>
        <span style="display: inline-block; background: white; padding: 2px 8px; border-radius: 20px; margin: 2px; font-size: 10px;">
            <?= htmlspecialchars($bb['nama_biaya']) ?>: Rp <?= number_format($bb['nominal'], 0, ',', '.') ?>
            <?php if ($bb['tipe_biaya'] == 'per_m2'): ?>/m²<?php elseif($bb['tipe_biaya'] == 'persen'): ?>%<?php endif; ?>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
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
    
    <!-- TABS -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('all')"><i class="fas fa-home"></i> Semua Unit</button>
        <button class="tab-btn" onclick="showTab('available')"><i class="fas fa-check-circle"></i> Available</button>
        <button class="tab-btn" onclick="showTab('booked')"><i class="fas fa-clock"></i> Booked</button>
        <button class="tab-btn" onclick="showTab('sold')"><i class="fas fa-check-double"></i> Sold</button>
    </div>
    
    <!-- ACTION BAR -->
    <div class="action-bar">
        <button class="btn-add" onclick="openAddUnitModal()">
            <i class="fas fa-plus-circle"></i> Tambah Unit
        </button>
        <button class="btn-massal" onclick="openAddMassalModal()">
            <i class="fas fa-layer-group"></i> Tambah Massal
        </button>
        
        <!-- TOMBOL KE HALAMAN TERKAIT -->
        <a href="/admin/developer_program_booking.php" class="btn-massal" style="background: linear-gradient(135deg, #E9C46A, #F0D48C); color: #1A2A24;">
            <i class="fas fa-tags"></i> Program Booking
        </a>
        
        <a href="/admin/developer_biaya_kategori.php" class="btn-massal" style="background: linear-gradient(135deg, #2A9D8F, #40BEB0);">
            <i class="fas fa-coins"></i> Master Biaya
        </a>
        
        <a href="/admin/developer_komisi_rules.php" class="btn-massal" style="background: linear-gradient(135deg, var(--secondary), var(--secondary-light));">
            <i class="fas fa-percent"></i> Atur Komisi
        </a>
        
        <a href="/admin/developer_split_hutang.php" class="btn-massal" style="background: linear-gradient(135deg, var(--platform), #FF6B4A);">
            <i class="fas fa-handshake"></i> Hutang Split
        </a>
        
        <a href="/admin/developer_blocks.php?cluster_id=<?= $cluster_id ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Kembali ke Block
        </a>
    </div>
    
    <!-- UNIT CARDS -->
    <?php if (empty($units)): ?>
    <div class="empty-state">
        <i class="fas fa-home"></i>
        <h4>Belum Ada Unit</h4>
        <p>Klik tombol "Tambah Unit" untuk membuat unit pertama</p>
    </div>
    <?php else: ?>
    <div class="units-horizontal" id="unitContainer">
        <?php foreach ($units as $u): 
            $status_class = strtolower($u['status']);
            $program_class = $u['program'];
            $lead_info = '';
            if ($u['lead_id'] && $u['status'] != 'AVAILABLE') {
                $stmt = $conn->prepare("SELECT first_name, last_name FROM leads WHERE id = ?");
                $stmt->execute([$u['lead_id']]);
                $lead = $stmt->fetch();
                $lead_info = $lead ? $lead['first_name'] . ' ' . ($lead['last_name'] ?? '') : 'Unknown';
            }
            
            // Format komisi dari aturan default
            if (!empty($komisi_eksternal_rupiah)) {
                $komisi_eksternal_tampil = 'Rp ' . number_format($komisi_eksternal_rupiah, 0, ',', '.');
            } else {
                $komisi_eksternal_tampil = number_format($komisi_eksternal_persen, 2, ',', '.') . '%';
            }
            
            $komisi_internal_tampil = 'Rp ' . number_format($inhouse_value, 0, ',', '.');
            
            if ($split_type == 'FIXED') {
                $komisi_split_tampil = 'Rp ' . number_format($split_value, 0, ',', '.');
            } else {
                $komisi_split_tampil = number_format($split_value, 2, ',', '.') . '%';
            }
            
            $total_harga = $u['harga'];
            if (!empty($u['biaya_tambahan'])) {
                foreach ($u['biaya_tambahan'] as $b) {
                    $total_harga += $b['nominal_final'] * ($b['quantity'] ?? 1);
                }
            }
        ?>
        <div class="unit-card status-<?= $status_class ?>" data-status="<?= $status_class ?>">
            <div class="unit-header">
                <div class="unit-number"><?= htmlspecialchars($u['nomor_unit']) ?></div>
                <span class="unit-status <?= $status_class ?>"><?= $u['status'] ?></span>
            </div>
            
            <div class="unit-details">
                <div class="unit-detail-row">
                    <span class="unit-detail-label">Tipe</span>
                    <span class="unit-detail-value"><?= htmlspecialchars($u['tipe_unit']) ?></span>
                </div>
                <div class="unit-detail-row">
                    <span class="unit-detail-label">Program</span>
                    <span class="unit-program <?= $program_class ?>"><?= $u['program'] ?></span>
                </div>
                <?php if ($u['luas_tanah'] && $u['luas_bangunan']): ?>
                <div class="unit-detail-row">
                    <span class="unit-detail-label">Luas</span>
                    <span class="unit-detail-value"><?= $u['luas_tanah'] ?>/<?= $u['luas_bangunan'] ?> m²</span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($u['program_booking_id'])): ?>
            <div class="program-badge <?= $u['is_all_in'] ? 'allin' : '' ?>">
                <?= htmlspecialchars($u['nama_program']) ?>: Rp <?= number_format($u['program_booking_fee'], 0, ',', '.') ?>
                <?php if ($u['is_all_in']): ?> (ALL-IN)<?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="price-tag">
                <span class="price-label">Harga Dasar</span>
                <span class="price-value">Rp <?= number_format($u['harga'], 0, ',', '.') ?></span>
            </div>
            
            <?php if (!empty($u['biaya_tambahan'])): ?>
            <div class="biaya-list">
                <?php foreach ($u['biaya_tambahan'] as $b): ?>
                <div class="biaya-item">
                    <span class="biaya-nama"><?= htmlspecialchars($b['keterangan']) ?></span>
                    <span class="biaya-nominal">
                        <?php if ($b['quantity'] > 1): ?>
                            <?= $b['quantity'] ?> x 
                        <?php endif; ?>
                        Rp <?= number_format($b['nominal_final'], 0, ',', '.') ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <div class="biaya-item" style="font-weight: 700; border-top: 1px solid var(--border); margin-top: 5px; padding-top: 5px;">
                    <span>Total + Biaya</span>
                    <span class="biaya-nominal" style="color: var(--secondary);">Rp <?= number_format($total_harga, 0, ',', '.') ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($u['harga_booking'] > 0): ?>
            <div class="price-tag">
                <span class="price-label">Booking Fee</span>
                <span class="price-value booking">Rp <?= number_format($u['harga_booking'], 0, ',', '.') ?></span>
            </div>
            <?php endif; ?>
            
            <!-- KOMISI SECTION - TANPA INPUT, HANYA INFO -->
            <div class="komisi-tag">
                <span class="komisi-label">Komisi Eksternal</span>
                <span class="komisi-value eksternal"><?= $komisi_eksternal_tampil ?></span>
            </div>
            <div class="komisi-tag">
                <span class="komisi-label">Komisi Internal</span>
                <span class="komisi-value internal"><?= $komisi_internal_tampil ?></span>
            </div>
            <div class="komisi-tag">
                <span class="komisi-label">Komisi Split ke Platform</span>
                <span class="komisi-value split"><?= $komisi_split_tampil ?></span>
                <span class="badge-platform">Platform</span>
            </div>
            
            <?php if ($lead_info): ?>
            <div class="price-tag" style="background: #f0f0f0;">
                <span class="price-label">Customer</span>
                <span class="price-value"><?= htmlspecialchars($lead_info) ?></span>
            </div>
            <?php endif; ?>
            
            <!-- ACTION BUTTONS -->
            <div class="unit-actions">
                <button class="btn-icon edit" onclick="editUnit(<?= htmlspecialchars(json_encode($u)) ?>)" title="Edit Unit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon delete" onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nomor_unit'])) ?>')" title="Hapus Unit">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div style="font-size: 10px; color: var(--text-muted); margin-top: 12px; text-align: right;">
                ID: #<?= $u['id'] ?> • <?= date('d/m/Y', strtotime($u['created_at'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Kelola Unit v6.0 (Sinkron Komisi)</p>
    </div>
    
</div>

<!-- MODAL ADD/EDIT SINGLE UNIT -->
<div class="modal" id="unitModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-home"></i> Tambah Unit</h2>
            <button class="modal-close" onclick="closeUnitModal()">&times;</button>
        </div>
        <form method="POST" id="unitForm" onsubmit="return prepareAllValues()">
            <input type="hidden" name="action" id="formAction" value="add_single">
            <input type="hidden" name="id" id="unitId" value="0">
            <input type="hidden" name="cluster_id" value="<?= $cluster_id ?>">
            <input type="hidden" name="block_id" value="<?= $block_id ?>">
            <input type="hidden" name="biaya_tambahan_json" id="biaya_tambahan_json" value="">
            
            <!-- Hidden fields untuk nilai asli -->
            <input type="hidden" name="harga" id="harga_hidden" value="">
            <input type="hidden" name="harga_booking" id="harga_booking_hidden" value="">
            
            <!-- Hidden fields untuk komisi (nilai default, tidak bisa diubah) -->
            <input type="hidden" name="komisi_eksternal_persen" value="<?= $komisi_eksternal_persen ?>">
            <input type="hidden" name="komisi_eksternal_rupiah" value="<?= $komisi_eksternal_rupiah ?>">
            <input type="hidden" name="komisi_internal_rupiah" value="<?= $inhouse_value ?>">
            <input type="hidden" name="komisi_split_persen" value="<?= ($split_type == 'PERCENT') ? $split_value : '' ?>">
            <input type="hidden" name="komisi_split_rupiah" value="<?= ($split_type == 'FIXED') ? $split_value : '' ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nomor Unit <span class="required">*</span></label>
                    <input type="text" name="nomor_unit" id="nomor_unit" class="form-control" placeholder="Contoh: A01, B17" required maxlength="20">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-ruler-combined"></i> Tipe Unit <span class="required">*</span></label>
                    <input type="text" name="tipe_unit" id="tipe_unit" class="form-control" placeholder="Contoh: 30/60, 36/60" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tasks"></i> Program <span class="required">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" name="program" id="program_subsidi" value="Subsidi" checked>
                            <label for="program_subsidi"><i class="fas fa-home"></i> Subsidi</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="program" id="program_komersil" value="Komersil">
                            <label for="program_komersil"><i class="fas fa-building"></i> Komersil</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-arrows-alt"></i> Luas Tanah (m²)</label>
                        <input type="number" name="luas_tanah" id="luas_tanah" class="form-control" step="0.01" placeholder="60">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-arrows-alt"></i> Luas Bangunan (m²)</label>
                        <input type="number" name="luas_bangunan" id="luas_bangunan" class="form-control" step="0.01" placeholder="30">
                    </div>
                </div>
                
                <!-- Harga Jual dengan Auto-format Rupiah -->
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Harga Jual <span class="required">*</span></label>
                    <input type="text" name="harga_display" id="harga_display" class="form-control rupiah-input" 
                           placeholder="Rp 150.000.000" required inputmode="numeric" 
                           onkeyup="formatRupiahInput(this)" onblur="formatRupiahBlur(this)">
                    <small style="color: var(--text-muted);">Contoh: 150000000 akan otomatis jadi Rp 150.000.000</small>
                </div>
                
                <!-- Harga Booking dengan Auto-format Rupiah -->
                <div class="form-group">
                    <label><i class="fas fa-hand-holding-usd"></i> Harga Booking (opsional)</label>
                    <input type="text" name="harga_booking_display" id="harga_booking_display" class="form-control rupiah-input" 
                           placeholder="Rp 1.000.000" value="0" inputmode="numeric" 
                           onkeyup="formatRupiahInput(this)" onblur="formatRupiahBlur(this)">
                    <small>Kosongkan atau isi 0 jika gratis</small>
                </div>
                
                <!-- PROGRAM BOOKING -->
                <div class="form-group">
                    <label><i class="fas fa-tags"></i> Program Booking</label>
                    <select name="program_booking_id" id="program_booking_id" class="form-select" onchange="updateBookingFeeDisplay()">
                        <option value="">— Tanpa Program Booking —</option>
                        <?php foreach ($program_booking as $pb): ?>
                        <option value="<?= $pb['id'] ?>" 
                                data-fee="<?= $pb['booking_fee'] ?>"
                                data-fee-display="Rp <?= number_format($pb['booking_fee'], 0, ',', '.') ?>"
                                data-allin="<?= $pb['is_all_in'] ?>">
                            <?= htmlspecialchars($pb['nama_program']) ?> (Rp <?= number_format($pb['booking_fee'], 0, ',', '.') ?>)
                            <?php if ($pb['is_all_in']): ?> - ALL-IN<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="booking-fee-display" style="margin-top: 8px; padding: 8px; background: var(--primary-soft); border-radius: 8px; display: none;">
                        <small>Booking Fee: <strong id="selected-booking-fee">Rp 0</strong></small>
                    </div>
                </div>
                
                <!-- INFO KOMISI - HANYA TAMPILAN (TIDAK BISA DIINPUT) -->
                <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; margin: 20px 0;">
                    <h4 style="color: var(--primary); margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-info-circle" style="color: var(--secondary);"></i> 
                        Informasi Komisi (Default)
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px;">
                        <div style="background: white; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 4px;">Komisi Eksternal</div>
                            <div style="font-weight: 700; color: #4A90E2; font-size: 15px;">
                                <?php if ($komisi_eksternal_rupiah): ?>
                                    Rp <?= number_format($komisi_eksternal_rupiah, 0, ',', '.') ?>
                                <?php else: ?>
                                    <?= number_format($komisi_eksternal_persen, 2) ?>%
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="background: white; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 4px;">Komisi Internal</div>
                            <div style="font-weight: 700; color: var(--secondary); font-size: 15px;">
                                Rp <?= number_format($inhouse_value, 0, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="background: white; padding: 12px; border-radius: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 11px; color: var(--text-muted);">Komisi Split ke Platform</span>
                            <span style="font-weight: 700; color: var(--platform); font-size: 15px;">
                                <?php if ($split_type == 'FIXED'): ?>
                                    Rp <?= number_format($split_value, 0, ',', '.') ?>
                                <?php else: ?>
                                    <?= number_format($split_value, 2) ?>%
                                <?php endif; ?>
                                <span style="display: inline-block; margin-left: 8px; padding: 2px 6px; background: var(--platform); color: white; font-size: 8px; border-radius: 20px;">Platform</span>
                            </span>
                        </div>
                    </div>
                    
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 12px; padding: 8px; background: white; border-radius: 8px;">
                        <i class="fas fa-info-circle" style="color: var(--secondary);"></i> 
                        Nilai komisi mengikuti aturan default. Ubah di 
                        <a href="developer_komisi_rules.php" style="color: var(--secondary);">Aturan Komisi</a> (internal) atau 
                        <a href="/admin/platform_komisi_split.php" style="color: var(--platform);">Platform</a> (split).
                    </div>
                </div>
                
                <!-- BIAYA TAMBAHAN SECTION -->
                <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; margin: 20px 0;">
                    <h4 style="color: var(--primary); margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-plus-circle"></i> Biaya Tambahan Unit
                    </h4>
                    
                    <div id="biaya-tambahan-container">
                        <!-- Template row biaya tambahan -->
                        <div class="biaya-row" style="display: flex; gap: 8px; margin-bottom: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="biaya_kategori_id[]" class="form-select biaya-kategori" style="flex: 2; min-width: 150px;" onchange="toggleBiayaCustom(this)">
                                <option value="">— Pilih Kategori —</option>
                                <option value="custom">+ Custom Biaya</option>
                                <?php foreach ($biaya_kategoris as $bk): ?>
                                <option value="<?= $bk['id'] ?>" 
                                        data-satuan="<?= $bk['satuan'] ?>"
                                        data-harga="<?= $bk['harga_default'] ?>"
                                        data-nama="<?= htmlspecialchars($bk['nama_kategori']) ?>">
                                    <?= htmlspecialchars($bk['nama_kategori']) ?> (<?= $bk['satuan'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <input type="text" name="biaya_custom_nama[]" class="form-control biaya-custom-nama" placeholder="Nama Biaya" style="flex: 2; min-width: 150px; display: none;">
                            
                            <input type="number" name="biaya_quantity[]" class="form-control biaya-quantity" placeholder="Qty" value="1" min="1" step="0.01" style="flex: 1; min-width: 80px;">
                            
                            <input type="text" name="biaya_nominal_display[]" class="form-control biaya-nominal rupiah-input" placeholder="Nominal" style="flex: 1; min-width: 120px;" inputmode="numeric" onkeyup="formatRupiahInput(this)" onblur="formatRupiahBlur(this)">
                            <input type="hidden" name="biaya_nominal[]" class="biaya-nominal-hidden" value="">
                            
                            <button type="button" class="btn-icon-small remove-row" onclick="removeBiayaRow(this)" style="flex: 0 0 44px;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="button" class="btn-add-row" onclick="addBiayaRow()">
                        <i class="fas fa-plus"></i> Tambah Biaya
                    </button>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-info-circle"></i> Status Awal</label>
                    <select name="status" id="status" class="form-select">
                        <option value="AVAILABLE">Available (Tersedia)</option>
                        <option value="BOOKED">Booked (Dibooking)</option>
                        <option value="SOLD">Sold (Terjual)</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeUnitModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan Unit</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ADD MASSAL -->
<div class="modal" id="massalModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-layer-group"></i> Tambah Unit Massal</h2>
            <button class="modal-close" onclick="closeMassalModal()">&times;</button>
        </div>
        <form method="POST" onsubmit="return prepareMassalValues()">
            <input type="hidden" name="action" value="add_massal">
            <input type="hidden" name="cluster_id" value="<?= $cluster_id ?>">
            <input type="hidden" name="block_id" value="<?= $block_id ?>">
            
            <!-- Hidden fields untuk nilai asli -->
            <input type="hidden" name="harga_massal" id="harga_massal_hidden" value="">
            <input type="hidden" name="harga_booking_massal" id="harga_booking_massal_hidden" value="0">
            
            <!-- Hidden fields untuk komisi massal (nilai default) -->
            <input type="hidden" name="komisi_eksternal_persen_massal" value="<?= $komisi_eksternal_persen ?>">
            <input type="hidden" name="komisi_eksternal_rupiah_massal" value="<?= $komisi_eksternal_rupiah ?>">
            <input type="hidden" name="komisi_internal_rupiah_massal" value="<?= $inhouse_value ?>">
            <input type="hidden" name="komisi_split_persen_massal" value="<?= ($split_type == 'PERCENT') ? $split_value : '' ?>">
            <input type="hidden" name="komisi_split_rupiah_massal" value="<?= ($split_type == 'FIXED') ? $split_value : '' ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Prefix Nomor</label>
                    <input type="text" name="prefix" class="form-control" placeholder="Contoh: A, B, (kosongkan jika tanpa prefix)" value="A">
                    <small>Nomor akan otomatis: A01, A02, A03</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nomor Awal</label>
                        <input type="number" name="start" class="form-control" value="1" min="1" max="999">
                    </div>
                    <div class="form-group">
                        <label>Nomor Akhir</label>
                        <input type="number" name="end" class="form-control" value="10" min="1" max="999">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-ruler-combined"></i> Tipe Unit <span class="required">*</span></label>
                    <input type="text" name="tipe_unit_massal" class="form-control" placeholder="Contoh: 30/60" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tasks"></i> Program <span class="required">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" name="program_massal" id="program_massal_subsidi" value="Subsidi" checked>
                            <label for="program_massal_subsidi"><i class="fas fa-home"></i> Subsidi</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="program_massal" id="program_massal_komersil" value="Komersil">
                            <label for="program_massal_komersil"><i class="fas fa-building"></i> Komersil</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-arrows-alt"></i> Luas Tanah (m²)</label>
                        <input type="number" name="luas_tanah_massal" class="form-control" step="0.01" placeholder="60">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-arrows-alt"></i> Luas Bangunan (m²)</label>
                        <input type="number" name="luas_bangunan_massal" class="form-control" step="0.01" placeholder="30">
                    </div>
                </div>
                
                <!-- Harga Jual Massal dengan Auto-format -->
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Harga Jual <span class="required">*</span></label>
                    <input type="text" name="harga_massal_display" id="harga_massal_display" class="form-control rupiah-input" 
                           placeholder="Rp 150.000.000" required inputmode="numeric" 
                           onkeyup="formatRupiahInput(this)" onblur="formatRupiahBlur(this)">
                </div>
                
                <!-- Harga Booking Massal -->
                <div class="form-group">
                    <label><i class="fas fa-hand-holding-usd"></i> Harga Booking (opsional)</label>
                    <input type="text" name="harga_booking_massal_display" id="harga_booking_massal_display" class="form-control rupiah-input" 
                           placeholder="Rp 1.000.000" value="0" inputmode="numeric" 
                           onkeyup="formatRupiahInput(this)" onblur="formatRupiahBlur(this)">
                </div>
                
                <!-- PROGRAM BOOKING MASSAL -->
                <div class="form-group">
                    <label><i class="fas fa-tags"></i> Program Booking</label>
                    <select name="program_booking_id_massal" class="form-select">
                        <option value="">— Tanpa Program Booking —</option>
                        <?php foreach ($program_booking as $pb): ?>
                        <option value="<?= $pb['id'] ?>">
                            <?= htmlspecialchars($pb['nama_program']) ?> (Rp <?= number_format($pb['booking_fee'], 0, ',', '.') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- INFO KOMISI MASSAL -->
                <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; margin: 20px 0;">
                    <h4 style="color: var(--primary); margin-bottom: 15px; font-size: 16px;">
                        <i class="fas fa-info-circle"></i> Informasi Komisi
                    </h4>
                    <p style="font-size: 13px; color: var(--text-light);">
                        Komisi akan menggunakan aturan default yang telah ditetapkan.
                    </p>
                </div>
                
                <div class="form-group" style="background: var(--primary-soft); padding: 16px; border-radius: 16px;">
                    <i class="fas fa-info-circle" style="color: var(--secondary);"></i>
                    <strong>Info:</strong> Akan membuat unit dari <span id="previewRange">A01 sampai A10</span> dengan tipe yang sama.
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeMassalModal()">Batal</button>
                <button type="submit" class="btn-primary">Buat Unit Massal</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-trash-alt"></i> Hapus Unit</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div style="font-size: 48px; color: var(--danger); margin-bottom: 16px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p style="font-size: 16px; margin-bottom: 16px;">Yakin ingin menghapus unit:</p>
            <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; font-weight: 700; font-size: 24px; color: var(--primary); margin-bottom: 16px;" id="deleteUnitName"></div>
            <p style="color: var(--text-muted); font-size: 13px;">
                <i class="fas fa-info-circle"></i> Data booking terkait akan ikut terhapus.
            </p>
            <input type="hidden" id="deleteUnitId">
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeDeleteModal()">Batal</button>
            <button class="btn-primary" style="background: var(--danger);" onclick="processDelete()">Ya, Hapus</button>
        </div>
    </div>
</div>

<script>
// ===== FUNGSI FORMAT RUPIAH KONSISTEN =====
function formatRupiah(angka, prefix = 'Rp ') {
    if (!angka && angka !== 0) return prefix + '0';
    
    let num = typeof angka === 'string' ? parseFloat(angka) : angka;
    if (isNaN(num)) return prefix + '0';
    
    let number_string = Math.floor(num).toString();
    let sisa = number_string.length % 3;
    let rupiah = number_string.substr(0, sisa);
    let ribuan = number_string.substr(sisa).match(/\d{3}/g);
    
    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    
    let desimal = (num % 1).toFixed(2).substring(1);
    if (desimal !== '.00') {
        rupiah += ',' + desimal.substring(2);
    }
    
    return prefix + rupiah;
}

function parseRupiah(rupiah) {
    if (!rupiah) return 0;
    let number = rupiah.toString()
        .replace(/[Rr]p\s?/g, '')
        .replace(/\./g, '')
        .replace(/,/g, '.');
    
    let parsed = parseFloat(number);
    return isNaN(parsed) ? 0 : parsed;
}

function formatRupiahInput(input) {
    let cursorPos = input.selectionStart;
    let value = input.value;
    let rawValue = value.replace(/[^0-9,]/g, '');
    
    if (rawValue) {
        let parts = rawValue.split(',');
        let integer = parts[0].replace(/^0+/, '') || '0';
        let decimal = parts[1] || '';
        
        let formattedInteger = '';
        for (let i = 0; i < integer.length; i++) {
            if (i > 0 && (integer.length - i) % 3 === 0) {
                formattedInteger += '.';
            }
            formattedInteger += integer[i];
        }
        
        if (decimal) {
            input.value = formattedInteger + ',' + decimal;
        } else {
            input.value = formattedInteger;
        }
        
        let newLength = input.value.length;
        let diff = newLength - value.length;
        input.setSelectionRange(cursorPos + diff, cursorPos + diff);
    }
}

function formatRupiahBlur(input) {
    let value = parseRupiah(input.value);
    if (value > 0) {
        input.value = formatRupiah(value, '').replace('Rp ', '');
    } else {
        input.value = '';
    }
}

// ===== FUNGSI FORMAT DESIMAL =====
function formatDesimal(angka) {
    if (!angka) return '0';
    
    let number_string = angka.toString().replace(/,/g, '.');
    number_string = number_string.replace(/[^\d.-]/g, '');
    
    let parts = number_string.split('.');
    if (parts.length > 2) {
        number_string = parts[0] + '.' + parts.slice(1).join('');
    }
    
    return number_string;
}

function parseDesimal(desimal) {
    if (!desimal) return 0;
    let number = desimal.toString().replace(/,/g, '.').replace(/[^\d.-]/g, '');
    let parsed = parseFloat(number);
    return isNaN(parsed) ? 0 : parsed;
}

function formatDesimalInput(input) {
    let value = input.value.replace(/,/g, '.');
    value = value.replace(/[^\d.]/g, '');
    
    let parts = value.split('.');
    if (parts.length > 2) {
        value = parts[0] + '.' + parts.slice(1).join('');
    }
    
    input.value = value;
}

function formatDesimalBlur(input) {
    let value = parseDesimal(input.value);
    if (!isNaN(value)) {
        input.value = value.toFixed(2);
    } else {
        input.value = '0.00';
    }
}

// ===== PREPARE ALL VALUES BEFORE SUBMIT =====
function prepareAllValues() {
    // Harga Jual
    const hargaDisplay = document.getElementById('harga_display');
    if (hargaDisplay) {
        const value = parseRupiah(hargaDisplay.value);
        document.getElementById('harga_hidden').value = value;
        if (value <= 0) {
            alert('Harga jual harus lebih dari 0');
            return false;
        }
    }
    
    // Harga Booking
    const bookingDisplay = document.getElementById('harga_booking_display');
    if (bookingDisplay) {
        document.getElementById('harga_booking_hidden').value = parseRupiah(bookingDisplay.value);
    }
    
    // Kumpulkan biaya tambahan
    const biayaRows = document.querySelectorAll('.biaya-row');
    const biayaArray = [];
    
    biayaRows.forEach(row => {
        const kategoriSelect = row.querySelector('.biaya-kategori');
        const kategoriId = kategoriSelect ? kategoriSelect.value : '';
        const customNamaInput = row.querySelector('.biaya-custom-nama');
        const customNama = customNamaInput ? customNamaInput.value : '';
        const quantityInput = row.querySelector('.biaya-quantity');
        const quantity = quantityInput ? quantityInput.value : '1';
        const nominalDisplay = row.querySelector('.biaya-nominal');
        const nominalHidden = row.querySelector('.biaya-nominal-hidden');
        
        if (nominalDisplay && nominalHidden) {
            const nominalValue = parseRupiah(nominalDisplay.value);
            nominalHidden.value = nominalValue;
            
            if ((kategoriId || customNama) && nominalValue > 0) {
                let nama = '';
                let isCustom = false;
                
                if (kategoriId === 'custom') {
                    nama = customNama;
                    isCustom = true;
                } else if (kategoriId) {
                    const selected = kategoriSelect.options[kategoriSelect.selectedIndex];
                    nama = selected ? selected.dataset.nama || selected.text.split(' (')[0] : '';
                }
                
                if (nama) {
                    biayaArray.push({
                        kategori_id: kategoriId !== 'custom' && kategoriId ? parseInt(kategoriId) : null,
                        nama: nama,
                        quantity: parseFloat(quantity) || 1,
                        nominal: nominalValue,
                        is_custom: isCustom
                    });
                }
            }
        }
    });
    
    document.getElementById('biaya_tambahan_json').value = JSON.stringify(biayaArray);
    
    return true;
}

function prepareMassalValues() {
    const hargaDisplay = document.getElementById('harga_massal_display');
    if (hargaDisplay) {
        const value = parseRupiah(hargaDisplay.value);
        document.getElementById('harga_massal_hidden').value = value;
        if (value <= 0) {
            alert('Harga jual harus lebih dari 0');
            return false;
        }
    }
    
    const bookingDisplay = document.getElementById('harga_booking_massal_display');
    if (bookingDisplay) {
        document.getElementById('harga_booking_massal_hidden').value = parseRupiah(bookingDisplay.value);
    }
    
    return true;
}

// Inisialisasi semua input
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.rupiah-input').forEach(input => {
        let value = input.value;
        if (value && !isNaN(value) && value.toString().indexOf('.') === -1) {
            input.value = formatRupiah(parseInt(value), '').replace('Rp ', '');
        }
    });
    
    document.querySelectorAll('.desimal-input').forEach(input => {
        let value = input.value;
        if (value && !isNaN(parseFloat(value))) {
            input.value = parseFloat(value).toFixed(2);
        }
    });
});

// ===== FUNGSI BOOKING FEE =====
function updateBookingFeeDisplay() {
    const select = document.getElementById('program_booking_id');
    const displayDiv = document.getElementById('booking-fee-display');
    const feeSpan = document.getElementById('selected-booking-fee');
    
    if (select && select.value) {
        const selected = select.options[select.selectedIndex];
        const fee = selected.dataset.fee;
        const feeDisplay = selected.dataset.feeDisplay || 'Rp ' + parseInt(fee).toLocaleString('id-ID');
        
        feeSpan.textContent = feeDisplay;
        displayDiv.style.display = 'block';
        
        const bookingDisplay = document.getElementById('harga_booking_display');
        if (bookingDisplay && (parseRupiah(bookingDisplay.value) === 0)) {
            bookingDisplay.value = formatRupiah(fee, '').replace('Rp ', '');
        }
    } else if (select) {
        displayDiv.style.display = 'none';
    }
}

// ===== TAB FUNCTIONS =====
function showTab(status) {
    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    if (status === 'all') tabs[0].classList.add('active');
    else if (status === 'available') tabs[1].classList.add('active');
    else if (status === 'booked') tabs[2].classList.add('active');
    else if (status === 'sold') tabs[3].classList.add('active');
    
    const cards = document.querySelectorAll('.unit-card');
    cards.forEach(card => {
        if (status === 'all') {
            card.style.display = 'block';
        } else {
            const cardStatus = card.getAttribute('data-status');
            card.style.display = cardStatus === status ? 'block' : 'none';
        }
    });
}

// ===== FUNGSI MODAL UNIT =====
function openAddUnitModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-home"></i> Tambah Unit';
    document.getElementById('formAction').value = 'add_single';
    document.getElementById('unitId').value = '0';
    document.getElementById('nomor_unit').value = '';
    document.getElementById('tipe_unit').value = '';
    document.getElementById('program_subsidi').checked = true;
    document.getElementById('luas_tanah').value = '';
    document.getElementById('luas_bangunan').value = '';
    document.getElementById('harga_display').value = '';
    document.getElementById('harga_hidden').value = '';
    document.getElementById('harga_booking_display').value = '0';
    document.getElementById('harga_booking_hidden').value = '0';
    document.getElementById('program_booking_id').value = '';
    
    document.getElementById('status').value = 'AVAILABLE';
    document.getElementById('booking-fee-display').style.display = 'none';
    
    document.getElementById('unitModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function openAddMassalModal() {
    document.querySelector('input[name="prefix"]').value = 'A';
    document.querySelector('input[name="start"]').value = '1';
    document.querySelector('input[name="end"]').value = '10';
    document.querySelector('input[name="tipe_unit_massal"]').value = '';
    document.getElementById('program_massal_subsidi').checked = true;
    document.getElementById('harga_massal_display').value = '';
    document.getElementById('harga_massal_hidden').value = '';
    document.getElementById('harga_booking_massal_display').value = '0';
    document.getElementById('harga_booking_massal_hidden').value = '0';
    document.querySelector('select[name="program_booking_id_massal"]').value = '';
    
    document.getElementById('massalModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function editUnit(unit) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Unit';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('unitId').value = unit.id;
    document.getElementById('nomor_unit').value = unit.nomor_unit;
    document.getElementById('tipe_unit').value = unit.tipe_unit;
    
    if (unit.program === 'Subsidi') {
        document.getElementById('program_subsidi').checked = true;
    } else {
        document.getElementById('program_komersil').checked = true;
    }
    
    document.getElementById('luas_tanah').value = unit.luas_tanah || '';
    document.getElementById('luas_bangunan').value = unit.luas_bangunan || '';
    
    let harga = parseFloat(unit.harga);
    document.getElementById('harga_display').value = formatRupiah(harga, '').replace('Rp ', '');
    document.getElementById('harga_hidden').value = harga;
    
    let hargaBooking = parseFloat(unit.harga_booking || 0);
    document.getElementById('harga_booking_display').value = formatRupiah(hargaBooking, '').replace('Rp ', '');
    document.getElementById('harga_booking_hidden').value = hargaBooking;
    
    document.getElementById('program_booking_id').value = unit.program_booking_id || '';
    document.getElementById('status').value = unit.status;
    
    updateBookingFeeDisplay();
    
    document.getElementById('unitModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeUnitModal() {
    document.getElementById('unitModal').classList.remove('show');
    document.body.style.overflow = '';
}

function closeMassalModal() {
    document.getElementById('massalModal').classList.remove('show');
    document.body.style.overflow = '';
}

function confirmDelete(id, name) {
    document.getElementById('deleteUnitId').value = id;
    document.getElementById('deleteUnitName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

function processDelete() {
    const id = document.getElementById('deleteUnitId').value;
    if (id) {
        window.location.href = '?block_id=<?= $block_id ?>&delete=' + id;
    }
}

// ===== BIAYA TAMBAHAN FUNCTIONS =====
function addBiayaRow() {
    const container = document.getElementById('biaya-tambahan-container');
    const template = container.children[0].cloneNode(true);
    
    template.querySelector('.biaya-kategori').value = '';
    template.querySelector('.biaya-custom-nama').value = '';
    template.querySelector('.biaya-custom-nama').style.display = 'none';
    template.querySelector('.biaya-quantity').value = '1';
    template.querySelector('.biaya-nominal').value = '';
    template.querySelector('.biaya-nominal-hidden').value = '';
    
    container.appendChild(template);
}

function removeBiayaRow(btn) {
    if (document.querySelectorAll('.biaya-row').length > 1) {
        btn.closest('.biaya-row').remove();
    }
}

function toggleBiayaCustom(select) {
    const row = select.closest('.biaya-row');
    const customNama = row.querySelector('.biaya-custom-nama');
    const nominalInput = row.querySelector('.biaya-nominal');
    
    if (select.value === 'custom') {
        customNama.style.display = 'block';
        nominalInput.value = '';
    } else {
        customNama.style.display = 'none';
        
        const selected = select.options[select.selectedIndex];
        const harga = selected ? selected.dataset.harga : null;
        if (harga) {
            nominalInput.value = formatRupiah(harga, '').replace('Rp ', '');
        }
    }
}

// ===== PREVIEW RANGE =====
document.querySelector('input[name="prefix"]')?.addEventListener('input', updateRangePreview);
document.querySelector('input[name="start"]')?.addEventListener('input', updateRangePreview);
document.querySelector('input[name="end"]')?.addEventListener('input', updateRangePreview);

function updateRangePreview() {
    const prefix = document.querySelector('input[name="prefix"]').value || '';
    const start = document.querySelector('input[name="start"]').value || 1;
    const end = document.querySelector('input[name="end"]').value || 10;
    
    const startStr = prefix + String(start).padStart(2, '0');
    const endStr = prefix + String(end).padStart(2, '0');
    
    document.getElementById('previewRange').textContent = startStr + ' sampai ' + endStr;
}

// ===== CLOSE MODAL ON OVERLAY CLICK =====
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// ===== DATE TIME =====
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

// ===== PREVENT FORM RESUBMISSION =====
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php include 'includes/footer.php'; ?>