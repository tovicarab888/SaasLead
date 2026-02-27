<?php
/**
 * LOCATIONS.PHP - TAUFIKMARIE.COM ULTIMATE
 * Version: 33.0.0 - SINKRON DENGAN get_locations.php & form.js
 * FIXED: Modal Add Cluster + Flow Tambah Cluster + Unit Types Sinkron
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
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

// ========== PROSES UPDATE ==========
$success = '';
$error = '';

// Handle TAMBAH LOKASI BARU
if (isset($_POST['action']) && $_POST['action'] == 'add') {
    $location_key = trim($_POST['location_key'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Program & Unit - Format baru
    $subsidi_units = isset($_POST['subsidi_units']) ? implode(',', $_POST['subsidi_units']) : '';
    $komersil_units = isset($_POST['komersil_units']) ? implode(',', $_POST['komersil_units']) : '';
    
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validasi
    if (empty($location_key) || empty($display_name) || empty($icon) || empty($city)) {
        $error = "❌ Location key, nama tampilan, icon, dan kota wajib diisi!";
    } else {
        // Format location_key: lowercase, tanpa spasi
        $location_key = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $location_key)));
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO locations (
                    location_key, display_name, icon, city, description, 
                    subsidi_units, komersil_units, sort_order, is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $result = $stmt->execute([
                $location_key, $display_name, $icon, $city, $description,
                $subsidi_units, $komersil_units, $sort_order, $is_active
            ]);
            
            if ($result) {
                $success = "✅ Lokasi baru berhasil ditambahkan!";
                logSystem("Location added", ['key' => $location_key], 'INFO', 'cms.log');
            } else {
                throw new Exception("Gagal menambahkan lokasi");
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = "❌ Location key sudah digunakan!";
            } else {
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    }
}

// Handle HAPUS LOKASI
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $location_key = trim($_GET['delete']);
    
    // Cek apakah ada leads yang menggunakan lokasi ini
    $check = $conn->prepare("SELECT COUNT(*) as total FROM leads WHERE location_key = ?");
    $check->execute([$location_key]);
    $total_leads = $check->fetch()['total'] ?? 0;
    
    if ($total_leads > 0) {
        $error = "❌ Tidak dapat menghapus lokasi karena masih ada $total_leads data lead yang menggunakan lokasi ini!";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM locations WHERE location_key = ?");
            $stmt->execute([$location_key]);
            
            if ($stmt->rowCount() > 0) {
                $success = "✅ Lokasi berhasil dihapus!";
                logSystem("Location deleted", ['key' => $location_key], 'INFO', 'cms.log');
            } else {
                $error = "❌ Lokasi tidak ditemukan.";
            }
        } catch (Exception $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    }
}

// Handle UPDATE LOKASI (existing)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    try {
        $conn->beginTransaction();
        
        foreach ($_POST['locations'] as $key => $data) {
            $display_name = trim($data['display_name']);
            $icon = trim($data['icon']);
            $city = trim($data['city']);
            $description = trim($data['description']);
            
            // Program & Unit - Format baru
            $subsidi_units = isset($data['subsidi_units']) ? implode(',', $data['subsidi_units']) : '';
            $komersil_units = isset($data['komersil_units']) ? implode(',', $data['komersil_units']) : '';
            
            $sort_order = (int)$data['sort_order'];
            $is_active = isset($data['is_active']) ? 1 : 0;
            
            if (empty($display_name) || empty($icon) || empty($city)) {
                throw new Exception("Nama, icon, dan kota wajib diisi untuk semua lokasi");
            }
            
            $stmt = $conn->prepare("
                UPDATE locations SET 
                    display_name = ?,
                    icon = ?,
                    city = ?,
                    description = ?,
                    subsidi_units = ?,
                    komersil_units = ?,
                    sort_order = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE location_key = ?
            ");
            
            $stmt->execute([
                $display_name, $icon, $city, $description, 
                $subsidi_units, $komersil_units, $sort_order, $is_active, $key
            ]);
        }
        
        $conn->commit();
        $success = "✅ Data lokasi berhasil diupdate!";
        logSystem("Locations updated", ['by' => $_SESSION['username']], 'INFO', 'cms.log');
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "❌ Gagal update: " . $e->getMessage();
        logSystem("Locations update failed", ['error' => $e->getMessage()], 'ERROR', 'cms.log');
    }
}

// ========== AMBIL DATA LOKASI ==========
$locations = $conn->query("SELECT * FROM locations ORDER BY sort_order")->fetchAll();

// ========== SET VARIABLES UNTUK INCLUDE ==========
$page_title = 'CMS Lokasi';
$page_subtitle = 'Kelola Lokasi Perumahan';
$page_icon = 'fas fa-map-marker-alt';

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
    
    <!-- INFO CARD + TOMBOL TAMBAH -->
    <div style="background: linear-gradient(135deg, #1B4A3C 0%, #2A5F4E 100%); border-radius: 20px; padding: 16px 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px; color: white; box-shadow: 0 15px 35px rgba(27, 74, 60, 0.3);">
        <i class="fas fa-info-circle" style="font-size: 28px; color: #E3B584; flex-shrink: 0;"></i>
        <div style="flex: 1; font-size: 14px; line-height: 1.5;">
            <strong style="font-size: 15px; color: #E3B584;">Info:</strong> 
            Unit yang diisi di sini akan muncul di <code>get_locations.php</code> dan <code>form.js</code>.
        </div>
        <button onclick="openAddLocationModal()" id="btnAddLocation" style="background: #D64F3C; color: white; border: none; padding: 12px 24px; border-radius: 50px; font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 8px 20px rgba(214, 79, 60, 0.3); flex-shrink: 0; white-space: nowrap;">
            <i class="fas fa-plus-circle"></i> Tambah Lokasi Baru
        </button>
        <button onclick="previewLocations()" style="background: #2A9D8F; color: white; border: none; padding: 12px 24px; border-radius: 50px; font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 8px 20px rgba(42, 157, 143, 0.3); flex-shrink: 0; white-space: nowrap;">
            <i class="fas fa-eye"></i> Preview
        </button>
    </div>
    
    <!-- ACCORDION FORM UNTUK EDIT LOKASI -->
    <form method="POST" id="locationsForm" style="max-width: 1200px; margin: 0 auto;">
        <input type="hidden" name="action" value="update">
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($locations as $index => $loc): 
                // PASTIKAN DATA DIUBAH JADI ARRAY
                $subsidi_units_array = !empty($loc['subsidi_units']) ? explode(',', $loc['subsidi_units']) : [];
                $komersil_units_array = !empty($loc['komersil_units']) ? explode(',', $loc['komersil_units']) : [];
            ?>
            <div class="accordion-item" style="background: white; border-radius: 20px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05); overflow: hidden; border: 1px solid #E0DAD3;">
                <!-- Accordion Header -->
                <div class="accordion-header" onclick="toggleAccordion(<?= $index ?>)" style="padding: 16px 20px; background: linear-gradient(135deg, #F5F3F0 0%, #E7F3EF 100%); cursor: pointer; display: flex; align-items: center; gap: 12px; border-bottom: 3px solid transparent;" id="header_<?= $index ?>">
                    <!-- Icon Preview -->
                    <div style="font-size: 32px; background: white; width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 12px rgba(27,74,60,0.1); border: 2px solid white; flex-shrink: 0;">
                        <?= $loc['icon'] ?>
                    </div>
                    
                    <!-- Info -->
                    <div style="flex: 1; min-width: 0;">
                        <h3 style="font-size: 18px; font-weight: 700; color: #1B4A3C; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($loc['display_name']) ?></h3>
                        <div style="display: flex; gap: 15px; color: #4A5A54; font-size: 12px; flex-wrap: wrap;">
                            <span><i class="fas fa-city" style="margin-right: 4px; color: #D64F3C;"></i> <?= htmlspecialchars($loc['city']) ?></span>
                            <span><i class="fas fa-map-pin" style="margin-right: 4px; color: #D64F3C;"></i> <?= $loc['location_key'] ?></span>
                        </div>
                    </div>
                    
                    <!-- Status, Delete, Icon -->
                    <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                        <span style="background: <?= $loc['is_active'] ? '#2A9D8F' : '#D64F3C'; ?>; color: white; padding: 4px 8px; border-radius: 30px; font-size: 10px; font-weight: 600; white-space: nowrap;">
                            <?= $loc['is_active'] ? 'AKTIF' : 'NON-AKTIF' ?>
                        </span>
                        <?php if (count($locations) > 1): ?>
                        <a href="?delete=<?= urlencode($loc['location_key']) ?>" class="delete-location" onclick="return confirm('Yakin ingin menghapus lokasi <?= htmlspecialchars($loc['display_name']) ?>?')" style="color: #D64F3C; font-size: 16px; text-decoration: none;" title="Hapus Lokasi">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down" style="color: #D64F3C; font-size: 16px; transition: transform 0.3s;" id="icon_<?= $index ?>"></i>
                    </div>
                </div>
                
                <!-- Accordion Content -->
                <div class="accordion-content" id="content_<?= $index ?>" style="display: none; padding: 20px; background: white;">
                    <input type="hidden" name="locations[<?= $loc['location_key'] ?>][sort_order]" value="<?= $loc['sort_order'] ?>">
                    
                    <!-- Grid untuk form -->
                    <div class="locations-form-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                        <!-- Nama Tampilan -->
                        <div class="form-item" style="grid-column: span 2;">
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                                <i class="fas fa-tag" style="color: #D64F3C; margin-right: 6px;"></i> Nama Tampilan
                            </label>
                            <input type="text" 
                                   name="locations[<?= $loc['location_key'] ?>][display_name]" 
                                   value="<?= htmlspecialchars($loc['display_name']) ?>" 
                                   required
                                   style="width: 100%; padding: 12px 14px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px; background: #F9FCFC;">
                        </div>
                        
                        <!-- Icon dan Kota -->
                        <div class="form-item" style="grid-column: span 1;">
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                                <i class="fas fa-icons" style="color: #D64F3C; margin-right: 6px;"></i> Icon
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" 
                                       name="locations[<?= $loc['location_key'] ?>][icon]" 
                                       value="<?= $loc['icon'] ?>" 
                                       maxlength="5" 
                                       required
                                       oninput="document.getElementById('preview_icon_<?= $loc['location_key'] ?>').textContent = this.value || '🏠'; updatePreview()"
                                       style="flex: 1; padding: 12px 14px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px; background: #F9FCFC;">
                                <div style="width: 46px; height: 46px; background: #E7F3EF; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; border: 2px solid white; flex-shrink: 0;" id="preview_icon_<?= $loc['location_key'] ?>">
                                    <?= $loc['icon'] ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-item" style="grid-column: span 1;">
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                                <i class="fas fa-city" style="color: #D64F3C; margin-right: 6px;"></i> Kota
                            </label>
                            <input type="text" 
                                   name="locations[<?= $loc['location_key'] ?>][city]" 
                                   value="<?= htmlspecialchars($loc['city']) ?>" 
                                   required
                                   style="width: 100%; padding: 12px 14px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px; background: #F9FCFC;">
                        </div>
                        
                        <!-- PROGRAM & UNIT SUBSIDI -->
                        <div class="form-item" style="grid-column: span 2;">
                            <div style="background: #E7F3EF; padding: 15px; border-radius: 12px; margin-bottom: 10px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                                    <span style="background: #2A9D8F; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">PROGRAM SUBSIDI</span>
                                    <span style="color: #1B4A3C; font-size: 11px;">Unit ini akan muncul di get_locations.php</span>
                                </div>
                                
                                <div style="background: white; padding: 15px; border-radius: 12px;">
                                    <!-- Daftar unit yang sudah dipilih -->
                                    <div id="subsidi_units_container_<?= $loc['location_key'] ?>" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
                                        <?php 
                                        // Tampilkan unit yang sudah dipilih
                                        foreach ($subsidi_units_array as $unit): 
                                        ?>
                                        <label style="display: inline-flex; align-items: center; gap: 4px; background: #2A9D8F; color: white; padding: 6px 12px; border-radius: 30px; border: 1px solid #2A9D8F; cursor: pointer;">
                                            <input type="checkbox" name="locations[<?= $loc['location_key'] ?>][subsidi_units][]" value="<?= htmlspecialchars($unit) ?>" checked style="width: 16px; height: 16px; accent-color: white;">
                                            <span style="font-size: 12px;"><?= htmlspecialchars($unit) ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Input untuk unit custom -->
                                    <div style="display: flex; gap: 8px; margin-top: 10px;">
                                        <input type="text" id="subsidi_custom_<?= $loc['location_key'] ?>" 
                                               placeholder="Contoh: Scandinavia 30/60" 
                                               style="flex: 1; padding: 10px; border: 1px solid #E0DAD3; border-radius: 8px; font-size: 13px;">
                                        <button type="button" onclick="addCustomUnit('<?= $loc['location_key'] ?>', 'subsidi')" 
                                                style="background: #2A9D8F; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 13px; cursor: pointer; font-weight: 600;">
                                            Tambah
                                        </button>
                                    </div>
                                    <small style="color: #7A8A84; display: block; margin-top: 5px;">Pisah dengan koma untuk multiple unit: <code>Scandinavia 30/60, Scandinavia 36/60</code></small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- PROGRAM & UNIT KOMERSIL -->
                        <div class="form-item" style="grid-column: span 2;">
                            <div style="background: #E7F3EF; padding: 15px; border-radius: 12px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                                    <span style="background: #D64F3C; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">PROGRAM KOMERSIL</span>
                                    <span style="color: #1B4A3C; font-size: 11px;">Unit ini akan muncul di get_locations.php</span>
                                </div>
                                
                                <div style="background: white; padding: 15px; border-radius: 12px;">
                                    <!-- Daftar unit yang sudah dipilih -->
                                    <div id="komersil_units_container_<?= $loc['location_key'] ?>" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
                                        <?php 
                                        // Tampilkan unit yang sudah dipilih
                                        foreach ($komersil_units_array as $unit): 
                                        ?>
                                        <label style="display: inline-flex; align-items: center; gap: 4px; background: #D64F3C; color: white; padding: 6px 12px; border-radius: 30px; border: 1px solid #D64F3C; cursor: pointer;">
                                            <input type="checkbox" name="locations[<?= $loc['location_key'] ?>][komersil_units][]" value="<?= htmlspecialchars($unit) ?>" checked style="width: 16px; height: 16px; accent-color: white;">
                                            <span style="font-size: 12px;"><?= htmlspecialchars($unit) ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Input untuk unit custom -->
                                    <div style="display: flex; gap: 8px; margin-top: 10px;">
                                        <input type="text" id="komersil_custom_<?= $loc['location_key'] ?>" 
                                               placeholder="Contoh: Scandinavia 50/60" 
                                               style="flex: 1; padding: 10px; border: 1px solid #E0DAD3; border-radius: 8px; font-size: 13px;">
                                        <button type="button" onclick="addCustomUnit('<?= $loc['location_key'] ?>', 'komersil')" 
                                                style="background: #D64F3C; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 13px; cursor: pointer; font-weight: 600;">
                                            Tambah
                                        </button>
                                    </div>
                                    <small style="color: #7A8A84; display: block; margin-top: 5px;">Pisah dengan koma untuk multiple unit: <code>Scandinavia 50/60, Scandinavia 70/120</code></small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Deskripsi - Full Width -->
                        <div class="form-item" style="grid-column: span 2;">
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                                <i class="fas fa-align-left" style="color: #D64F3C; margin-right: 6px;"></i> Deskripsi
                            </label>
                            <textarea name="locations[<?= $loc['location_key'] ?>][description]" 
                                      rows="3"
                                      style="width: 100%; padding: 12px 14px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 13px; background: #F9FCFC; resize: vertical;"><?= htmlspecialchars($loc['description']) ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Footer Card -->
                    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 2px solid #E7F3EF;">
                        <!-- Checkbox Active -->
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; background: #E7F3EF; padding: 8px 16px; border-radius: 40px;">
                            <input type="checkbox" 
                                   name="locations[<?= $loc['location_key'] ?>][is_active]" 
                                   id="active_<?= $loc['id'] ?>" 
                                   <?= $loc['is_active'] ? 'checked' : '' ?>
                                   style="width: 18px; height: 18px; accent-color: #D64F3C;"
                                   onchange="updatePreview()">
                            <span style="font-weight: 600; color: #1B4A3C; font-size: 12px;">
                                <i class="fas fa-check-circle" style="color: #2A9D8F;"></i> Tampil di Website
                            </span>
                        </label>
                        
                        <!-- Key Badge -->
                        <div style="background: #F5F3F0; padding: 6px 14px; border-radius: 40px; display: flex; align-items: center; gap: 8px;">
                            <span style="color: #4A5A54; font-size: 11px;">ID:</span>
                            <code style="background: white; padding: 4px 12px; border-radius: 30px; color: #D64F3C; font-weight: 600; font-size: 11px; border: 1px solid #E0DAD3;"><?= $loc['location_key'] ?></code>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Save Button -->
        <button type="submit" style="background: linear-gradient(135deg, #1B4A3C 0%, #2A5F4E 100%); color: white; border: none; padding: 16px 28px; border-radius: 50px; font-weight: 700; font-size: 15px; cursor: pointer; width: 100%; max-width: 300px; margin: 30px auto 20px; display: block; box-shadow: 0 15px 35px rgba(27, 74, 60, 0.3);">
            <i class="fas fa-save" style="margin-right: 8px;"></i> SIMPAN PERUBAHAN
        </button>
    </form>
    
    <!-- MODAL TAMBAH LOKASI BARU -->
    <div class="modal" id="addLocationModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Tambah Lokasi Baru</h2>
                <button class="modal-close" onclick="closeAddLocationModal()">&times;</button>
            </div>
            <form method="POST" id="addLocationForm">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body" style="overflow-y: auto; max-height: 70vh; padding: 20px;">
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Location Key (ID unik)</label>
                        <input type="text" name="location_key" id="location_key" class="form-control" placeholder="contoh: perumahan_baru" required>
                        <small>Huruf kecil, tanpa spasi, gunakan underscore (_)</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Nama Tampilan</label>
                        <input type="text" name="display_name" id="display_name" class="form-control" placeholder="Contoh: Perumahan Baru Residence" required>
                    </div>
                    
                    <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label><i class="fas fa-icons"></i> Icon</label>
                            <input type="text" name="icon" id="icon" class="form-control" placeholder="🏠" maxlength="5" required>
                        </div>
                        <div>
                            <label><i class="fas fa-city"></i> Kota</label>
                            <input type="text" name="city" id="city" class="form-control" placeholder="Kuningan" required>
                        </div>
                    </div>
                    
                    <!-- SUBSIDI UNITS -->
                    <div class="form-group">
                        <label style="color: #2A9D8F; font-weight: 700;"><i class="fas fa-home"></i> Tipe Unit SUBSIDI</label>
                        <div style="background: #F5F3F0; padding: 15px; border-radius: 12px;">
                            <div id="modal_subsidi_container" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
                                <!-- Akan diisi oleh JavaScript -->
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="modal_subsidi_input" placeholder="Contoh: Scandinavia 30/60" 
                                       style="flex: 1; padding: 10px; border: 1px solid #E0DAD3; border-radius: 8px; font-size: 13px;">
                                <button type="button" onclick="addModalUnit('subsidi')" 
                                        style="background: #2A9D8F; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 13px; cursor: pointer;">
                                    Tambah
                                </button>
                            </div>
                            <small>Pisah dengan koma untuk multiple unit</small>
                        </div>
                    </div>
                    
                    <!-- KOMERSIL UNITS -->
                    <div class="form-group">
                        <label style="color: #D64F3C; font-weight: 700;"><i class="fas fa-building"></i> Tipe Unit KOMERSIL</label>
                        <div style="background: #F5F3F0; padding: 15px; border-radius: 12px;">
                            <div id="modal_komersil_container" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
                                <!-- Akan diisi oleh JavaScript -->
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="modal_komersil_input" placeholder="Contoh: Scandinavia 50/60" 
                                       style="flex: 1; padding: 10px; border: 1px solid #E0DAD3; border-radius: 8px; font-size: 13px;">
                                <button type="button" onclick="addModalUnit('komersil')" 
                                        style="background: #D64F3C; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 13px; cursor: pointer;">
                                    Tambah
                                </button>
                            </div>
                            <small>Pisah dengan koma untuk multiple unit</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Deskripsi</label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="Deskripsi lokasi..."></textarea>
                    </div>
                    
                    <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label><i class="fas fa-sort-numeric-down"></i> Sort Order</label>
                            <input type="number" name="sort_order" id="sort_order" class="form-control" value="5" min="0">
                        </div>
                        <div style="display: flex; align-items: center; justify-content: center;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="is_active" id="is_active" checked value="1" style="width: 18px; height: 18px; accent-color: #D64F3C;">
                                <span><i class="fas fa-check-circle" style="color: #2A9D8F;"></i> Aktif</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- MODAL FOOTER -->
                <div class="modal-footer" style="display: flex; gap: 12px; justify-content: flex-end; background: #fafafa; border-top: 2px solid var(--primary-soft); padding: 16px 20px;">
                    <button type="submit" class="btn-primary" style="min-width: 120px;">Tambah Lokasi</button>
                    <button type="button" class="btn-secondary" onclick="closeAddLocationModal()" style="min-width: 120px;">Batal</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- PREVIEW SECTION -->
    <div style="background: white; border-radius: 20px; padding: 20px; margin-top: 20px; border: 2px dashed #D64F3C; display: none;" id="previewPanel">
        <h3 style="color: #1B4A3C; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 700;">
            <i class="fas fa-eye" style="color: #D64F3C;"></i> Preview di Website
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;" id="previewContainer"></div>
    </div>
    
    <!-- FOOTER -->
    <div style="text-align: center; margin-top: 40px; padding: 20px; color: #7A8A84; font-size: 12px; border-top: 1px solid #E0DAD3;">
        <p>© <?= date('Y') ?> TaufikMarie.com - CMS Version 33.0.0 (Sinkron dengan get_locations.php)</p>
    </div>
    
</div>

<style>
/* ===== RESPONSIVE FIX UNTUK MOBILE ===== */
@media (max-width: 768px) {
    .locations-form-grid {
        grid-template-columns: 1fr !important;
    }
    
    .form-item[style*="grid-column: span 2"] {
        grid-column: span 1 !important;
    }
    
    .accordion-content {
        padding: 15px !important;
    }
    
    .accordion-content label {
        font-size: 12px !important;
    }
    
    .accordion-content input,
    .accordion-content textarea {
        padding: 10px 12px !important;
        font-size: 13px !important;
    }
    
    .accordion-header {
        padding: 12px 15px !important;
        gap: 8px !important;
    }
    
    .accordion-header > div:first-child {
        width: 40px !important;
        height: 40px !important;
        font-size: 24px !important;
    }
    
    .accordion-header h3 {
        font-size: 15px !important;
    }
}

/* ===== TABLET ===== */
@media (min-width: 769px) and (max-width: 1024px) {
    .locations-form-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .form-item[style*="grid-column: span 2"] {
        grid-column: span 2 !important;
    }
}

/* ===== DESKTOP ===== */
@media (min-width: 1025px) {
    .locations-form-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .form-item[style*="grid-column: span 2"] {
        grid-column: span 2 !important;
    }
}

/* ===== MODAL FIX ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    z-index: 999999 !important;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.modal.show {
    display: flex !important;
}

.modal-content {
    background: white;
    border-radius: 28px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 30px 60px rgba(0,0,0,0.3);
    animation: modalFade 0.3s ease;
    position: relative;
    z-index: 1000000;
}

@keyframes modalFade {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
</style>

<script>
// ===== FUNGSI ACCORDION =====
function toggleAccordion(index) {
    const content = document.getElementById('content_' + index);
    const icon = document.getElementById('icon_' + index);
    const header = document.getElementById('header_' + index);
    
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
        header.style.borderBottomColor = '#D64F3C';
        header.style.background = 'linear-gradient(135deg, #E7F3EF 0%, #d4e8e0 100%)';
        
        if (window.innerWidth <= 768) {
            setTimeout(() => {
                content.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        }
    } else {
        content.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
        header.style.borderBottomColor = 'transparent';
        header.style.background = 'linear-gradient(135deg, #F5F3F0 0%, #E7F3EF 100%)';
    }
}

function updatePreview() {
    const previewPanel = document.getElementById('previewPanel');
    const container = document.getElementById('previewContainer');
    
    if (!previewPanel || !container) return;
    
    const items = document.querySelectorAll('.accordion-item');
    let html = '';
    
    items.forEach(item => {
        const iconInput = item.querySelector('input[name*="[icon]"]');
        const nameInput = item.querySelector('input[name*="[display_name]"]');
        const cityInput = item.querySelector('input[name*="[city]"]');
        const subsidiLabels = item.querySelectorAll('input[name*="[subsidi_units]"]:checked');
        const komersilLabels = item.querySelectorAll('input[name*="[komersil_units]"]:checked');
        const activeCheck = item.querySelector('input[name*="[is_active]"]');
        
        if (iconInput && nameInput && cityInput) {
            const icon = iconInput.value || '🏠';
            const name = nameInput.value;
            const city = cityInput.value;
            const isActive = activeCheck ? activeCheck.checked : true;
            
            // Ambil unit
            let subsidi = [];
            subsidiLabels.forEach(cb => subsidi.push(cb.value));
            
            let komersil = [];
            komersilLabels.forEach(cb => komersil.push(cb.value));
            
            if (isActive) {
                let unitsHtml = '';
                if (subsidi.length > 0) {
                    unitsHtml += `<div style="font-size: 11px; margin-top: 8px;"><span style="color: #2A9D8F;">SUBSIDI:</span> ${subsidi.join(', ')}</div>`;
                }
                if (komersil.length > 0) {
                    unitsHtml += `<div style="font-size: 11px;"><span style="color: #D64F3C;">KOMERSIL:</span> ${komersil.join(', ')}</div>`;
                }
                
                html += `
                    <div style="background: linear-gradient(135deg, #1B4A3C 0%, #2A5F4E 100%); padding: 16px; border-radius: 16px; color: white; border-left: 4px solid #D64F3C;">
                        <div style="font-size: 36px; margin-bottom: 8px;">${icon}</div>
                        <div style="font-weight: 700; font-size: 16px; margin-bottom: 4px;">${name}</div>
                        <div style="font-size: 12px; opacity: 0.9;"><i class="fas fa-map-marker-alt" style="color: #E3B584;"></i> ${city}</div>
                        ${unitsHtml}
                    </div>
                `;
            }
        }
    });
    
    container.innerHTML = html;
    previewPanel.style.display = 'block';
    
    if (window.innerWidth <= 768) {
        setTimeout(() => {
            previewPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }
}

function previewLocations() {
    updatePreview();
}

// ===== FUNGSI MODAL =====
function openAddLocationModal() {
    console.log('openAddLocationModal dipanggil');
    const modal = document.getElementById('addLocationModal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        console.log('Modal ditampilkan');
    } else {
        console.error('Modal dengan ID addLocationModal tidak ditemukan!');
    }
}

function closeAddLocationModal() {
    const modal = document.getElementById('addLocationModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// ===== FUNGSI TAMBAH UNIT CUSTOM =====
function addCustomUnit(locationKey, program) {
    const inputId = program === 'subsidi' ? `subsidi_custom_${locationKey}` : `komersil_custom_${locationKey}`;
    const containerId = program === 'subsidi' ? `subsidi_units_container_${locationKey}` : `komersil_units_container_${locationKey}`;
    const bgColor = program === 'subsidi' ? '#2A9D8F' : '#D64F3C';
    
    const input = document.getElementById(inputId);
    const container = document.getElementById(containerId);
    
    if (!input || !container) return;
    
    const customValue = input.value.trim();
    if (!customValue) return;
    
    // Split koma
    const units = customValue.split(',').map(u => u.trim());
    
    units.forEach(unit => {
        if (unit) {
            // Cek apakah sudah ada
            const existing = Array.from(container.querySelectorAll('input')).some(cb => cb.value === unit);
            if (!existing) {
                const label = document.createElement('label');
                label.style.cssText = `display: inline-flex; align-items: center; gap: 4px; background: ${bgColor}; color: white; padding: 6px 12px; border-radius: 30px; border: 1px solid ${bgColor}; cursor: pointer; margin: 0 5px 5px 0;`;
                label.innerHTML = `
                    <input type="checkbox" name="locations[${locationKey}][${program}_units][]" value="${unit}" checked style="width: 16px; height: 16px; accent-color: white;">
                    <span style="font-size: 12px;">${unit}</span>
                `;
                container.appendChild(label);
            }
        }
    });
    
    input.value = '';
}

function addModalUnit(program) {
    const inputId = program === 'subsidi' ? 'modal_subsidi_input' : 'modal_komersil_input';
    const containerId = program === 'subsidi' ? 'modal_subsidi_container' : 'modal_komersil_container';
    const fieldName = program === 'subsidi' ? 'subsidi_units[]' : 'komersil_units[]';
    const bgColor = program === 'subsidi' ? '#2A9D8F' : '#D64F3C';
    
    const input = document.getElementById(inputId);
    const container = document.getElementById(containerId);
    
    if (!input || !container) return;
    
    const customValue = input.value.trim();
    if (!customValue) return;
    
    // Split koma
    const units = customValue.split(',').map(u => u.trim());
    
    units.forEach(unit => {
        if (unit) {
            // Cek apakah sudah ada
            const existing = Array.from(container.querySelectorAll('input')).some(cb => cb.value === unit);
            if (!existing) {
                const label = document.createElement('label');
                label.style.cssText = `display: inline-flex; align-items: center; gap: 4px; background: ${bgColor}; color: white; padding: 6px 12px; border-radius: 30px; border: 1px solid ${bgColor}; cursor: pointer; margin: 0 5px 5px 0;`;
                label.innerHTML = `
                    <input type="checkbox" name="${fieldName}" value="${unit}" checked style="width: 16px; height: 16px; accent-color: white;">
                    <span style="font-size: 12px;">${unit}</span>
                `;
                container.appendChild(label);
            }
        }
    });
    
    input.value = '';
}

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - Locations JS v33.0.0');
    
    // Buka accordion pertama secara default
    if (document.querySelector('.accordion-item')) {
        setTimeout(() => {
            toggleAccordion(0);
        }, 100);
    }
    
    // Event listener untuk tombol tambah
    const btnAdd = document.getElementById('btnAddLocation');
    if (btnAdd) {
        btnAdd.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Tombol tambah diklik via event listener');
            openAddLocationModal();
        });
    } else {
        console.error('Tombol dengan ID btnAddLocation tidak ditemukan!');
    }
    
    // Tutup modal jika klik di luar
    const modal = document.getElementById('addLocationModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeAddLocationModal();
            }
        });
    }
    
    updateDateTime();
    setInterval(updateDateTime, 1000);
});

function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const dateEl = document.getElementById('currentDate')?.querySelector('span');
    const timeEl = document.getElementById('currentTime')?.querySelector('span');
    
    if (dateEl) dateEl.textContent = now.toLocaleDateString('id-ID', options);
    if (timeEl) timeEl.textContent = now.toLocaleTimeString('id-ID', { hour12: false });
}

let formChanged = false;
const locationsForm = document.getElementById('locationsForm');
if (locationsForm) {
    locationsForm.addEventListener('input', () => formChanged = true);
    locationsForm.addEventListener('change', () => formChanged = true);
}

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'Ada perubahan yang belum disimpan. Yakin ingin keluar?';
    }
});
</script>

<!-- FOOTER -->
<?php include 'includes/footer.php'; ?>