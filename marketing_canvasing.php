<?php
/**
 * MARKETING_CANVASING.PHP - LEADENGINE
 * Version: 7.0.0 - FINAL: Validasi nomor 13 digit, GPS stabil, anti error
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

// Ambil lokasi untuk dropdown
$locations = [];
if ($developer_id > 0) {
    $stmt = $conn->prepare("
        SELECT l.* FROM locations l
        JOIN users u ON FIND_IN_SET(l.location_key, u.location_access)
        WHERE u.id = ? AND l.is_active = 1
        ORDER BY l.sort_order
    ");
    $stmt->execute([$developer_id]);
    $locations = $stmt->fetchAll();
}

$page_title = 'Canvasing';
$page_subtitle = 'Ambil Foto Bukti Kunjungan';
$page_icon = 'fas fa-camera';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* ===== CANVASING STYLES ===== */
:root {
    --primary: #1B4A3C;
    --primary-light: #2A5F4E;
    --secondary: #D64F3C;
    --secondary-light: #FF6B4A;
    --success: #2A9D8F;
    --warning: #E9C46A;
    --danger: #D64F3C;
    --info: #4A90E2;
    --bg: #F5F3F0;
    --surface: #FFFFFF;
    --text: #1A2A24;
    --text-light: #4A5A54;
    --text-muted: #7A8A84;
    --border: #E0DAD3;
    --primary-soft: #E7F3EF;
}

.main-content {
    margin-left: 280px;
    padding: 24px;
    background: var(--bg);
    min-height: 100vh;
}

.top-bar {
    background: var(--surface);
    border-radius: 20px;
    padding: 20px 24px;
    margin-bottom: 24px;
    border-left: 6px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.welcome-text {
    display: flex;
    align-items: center;
    gap: 16px;
}

.welcome-text i {
    width: 56px;
    height: 56px;
    background: rgba(214,79,60,0.1);
    color: var(--secondary);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.welcome-text h2 {
    font-size: 22px;
    font-weight: 800;
    color: var(--text);
    margin: 0;
    line-height: 1.3;
}

.welcome-text h2 span {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-muted);
    margin-top: 4px;
}

.datetime {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--bg);
    padding: 10px 20px;
    border-radius: 40px;
}

.date, .time {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--primary);
}

.time {
    background: var(--surface);
    padding: 6px 16px;
    border-radius: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.canvasing-card {
    background: white;
    border-radius: 28px;
    padding: 30px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    max-width: 800px;
    margin: 0 auto;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 2px solid var(--primary-soft);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.form-section-title {
    color: var(--primary);
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-section-title i {
    color: var(--secondary);
    font-size: 20px;
}

/* ===== CANVASING TYPE GRID ===== */
.canvasing-type-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}

.type-option {
    position: relative;
    cursor: pointer;
}

.type-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.type-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    aspect-ratio: 1;
    background: var(--primary-soft);
    border-radius: 16px;
    font-size: 28px;
    color: var(--primary);
    border: 2px solid var(--border);
    transition: all 0.2s;
    margin-bottom: 5px;
}

.type-label {
    display: block;
    text-align: center;
    font-size: 11px;
    font-weight: 600;
    color: var(--text);
}

.type-option input[type="radio"]:checked + .type-icon {
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border-color: var(--secondary);
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(214,79,60,0.3);
}

/* ===== CAMERA SECTION ===== */
.camera-container {
    position: relative;
    width: 100%;
    background: #1A2A24;
    border-radius: 20px;
    overflow: hidden;
    aspect-ratio: 4/3;
    margin-bottom: 15px;
}

#videoElement {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: none;
}

#photoPreview {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: none;
}

.camera-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.5);
    color: white;
    z-index: 10;
    text-align: center;
    padding: 20px;
}

.camera-overlay i {
    font-size: 48px;
    margin-bottom: 15px;
    color: var(--secondary);
}

.camera-error {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(214,79,60,0.95);
    color: white;
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 20;
    text-align: center;
    padding: 20px;
}

.camera-controls {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 15px;
}

.camera-btn {
    padding: 14px 24px;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    min-width: 140px;
}

.camera-btn.primary {
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
}

.camera-btn.secondary {
    background: var(--surface);
    color: var(--text);
    border: 2px solid var(--border);
}

.camera-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* ===== GPS SECTION ===== */
.gps-section {
    background: var(--primary-soft);
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 20px;
}

.gps-status {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.gps-icon {
    width: 48px;
    height: 48px;
    background: white;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--success);
    font-size: 24px;
    flex-shrink: 0;
}

.gps-info {
    flex: 1;
    min-width: 200px;
}

.gps-coords {
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
    margin-bottom: 4px;
    word-break: break-word;
}

.gps-address {
    font-size: 14px;
    color: var(--text);
    margin-bottom: 4px;
    line-height: 1.4;
    font-weight: 500;
}

.gps-accuracy {
    font-size: 12px;
    color: var(--text-muted);
}

.gps-badge {
    padding: 8px 20px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
}

.gps-badge.success {
    background: var(--success);
    color: white;
}

.gps-badge.warning {
    background: var(--warning);
    color: var(--text);
}

.gps-badge.error {
    background: var(--danger);
    color: white;
}

.gps-error {
    background: rgba(214,79,60,0.1);
    border: 2px solid var(--danger);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 16px;
    color: var(--danger);
    display: none;
    align-items: center;
    gap: 12px;
}

.map-container {
    height: 250px;
    width: 100%;
    border-radius: 16px;
    overflow: hidden;
    margin: 15px 0;
    border: 2px solid white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

#map {
    height: 100%;
    width: 100%;
    z-index: 1;
    background: #e5e5e5;
}

/* ===== FORM ===== */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.form-group {
    margin-bottom: 0;
}

.form-group.full-width {
    grid-column: span 2;
}

.form-group.hidden {
    display: none;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--primary);
    font-size: 13px;
}

.form-group label i {
    color: var(--secondary);
    margin-right: 6px;
    width: 18px;
}

.form-group label .required {
    color: var(--danger);
    margin-left: 2px;
}

.form-control, .form-select {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    background: white;
    transition: all 0.2s;
}

.form-control:focus, .form-select:focus {
    border-color: var(--secondary);
    outline: none;
    box-shadow: 0 0 0 4px rgba(214,79,60,0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.location-valid {
    background: rgba(42,157,143,0.1);
    border: 2px solid var(--success);
    border-radius: 12px;
    padding: 12px;
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--success);
}

.location-invalid {
    background: rgba(214,79,60,0.1);
    border: 2px solid var(--danger);
    border-radius: 12px;
    padding: 12px;
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--danger);
}

.location-warning {
    background: rgba(233,196,106,0.1);
    border: 2px solid var(--warning);
    border-radius: 12px;
    padding: 12px;
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #B87C00;
}

/* ===== SUBMIT BUTTON ===== */
.btn-submit {
    width: 100%;
    padding: 16px 24px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin: 20px 0 15px;
    box-shadow: 0 8px 20px rgba(27,74,60,0.3);
    transition: all 0.3s;
}

.btn-submit:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(27,74,60,0.4);
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* ===== TOAST ===== */
.toast-message {
    position: fixed;
    bottom: 80px;
    left: 50%;
    transform: translateX(-50%);
    color: white;
    padding: 16px 28px;
    border-radius: 50px;
    font-size: 15px;
    font-weight: 600;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    z-index: 10001;
    max-width: 90%;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
}

.toast-message i {
    font-size: 18px;
}

.toast-message.success {
    background: rgba(42,157,143,0.95);
}

.toast-message.error {
    background: rgba(214,79,60,0.95);
}

.footer {
    text-align: center;
    margin-top: 40px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 12px !important;
    }
    
    .top-bar {
        flex-direction: column;
        align-items: flex-start;
        padding: 16px;
    }
    
    .datetime {
        width: 100%;
        justify-content: space-between;
    }
    
    .canvasing-card {
        padding: 20px;
    }
    
    .canvasing-type-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .form-grid {
        grid-template-columns: 1fr !important;
    }
    
    .form-group.full-width {
        grid-column: span 1;
    }
    
    .gps-status {
        flex-direction: column;
        text-align: center;
    }
    
    .camera-controls {
        flex-direction: column;
    }
    
    .camera-btn {
        width: 100%;
    }
    
    .map-container {
        height: 200px;
    }
}

@media (max-width: 480px) {
    .canvasing-type-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .canvasing-card {
        padding: 16px;
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
    
    <!-- CANVASING CARD -->
    <div class="canvasing-card">
        <form id="canvasingForm" onsubmit="submitCanvasing(event)">
            <input type="hidden" name="marketing_id" value="<?= $marketing_id ?>">
            <input type="hidden" name="developer_id" value="<?= $developer_id ?>">
            <input type="hidden" name="latitude" id="latitude" value="">
            <input type="hidden" name="longitude" id="longitude" value="">
            <input type="hidden" name="accuracy" id="accuracy" value="">
            <input type="hidden" name="photo_data" id="photo_data" value="">
            
            <!-- SECTION 1: FOTO -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="fas fa-camera"></i> 1. Foto Bukti Kunjungan
                </div>
                
                <div class="camera-container" id="cameraContainer">
                    <video id="videoElement" autoplay playsinline></video>
                    <img id="photoPreview" src="" alt="Preview">
                    
                    <div class="camera-overlay" id="cameraOverlay">
                        <i class="fas fa-camera"></i>
                        <p>Klik "Mulai Kamera" untuk mengambil foto</p>
                    </div>
                    
                    <div class="camera-error" id="cameraError">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p id="cameraErrorMessage">Tidak dapat mengakses kamera</p>
                        <small>Periksa izin kamera di browser Anda</small>
                    </div>
                </div>
                
                <div class="camera-controls">
                    <button type="button" class="camera-btn primary" id="startCameraBtn" onclick="startCamera()">
                        <i class="fas fa-video"></i> Mulai Kamera
                    </button>
                    <button type="button" class="camera-btn secondary" id="switchCameraBtn" onclick="switchCamera()" disabled>
                        <i class="fas fa-sync-alt"></i> <span id="cameraModeText">Kamera Belakang</span>
                    </button>
                    <button type="button" class="camera-btn secondary" id="captureBtn" onclick="capturePhoto()" disabled>
                        <i class="fas fa-camera"></i> Ambil Foto
                    </button>
                    <button type="button" class="camera-btn secondary" id="retakeBtn" onclick="retakePhoto()" style="display: none;">
                        <i class="fas fa-redo"></i> Foto Ulang
                    </button>
                </div>
                
                <p style="color: var(--text-muted); font-size: 12px; margin-top: 10px; text-align: center;">
                    <i class="fas fa-info-circle"></i> Wajib foto langsung (tidak bisa dari galeri)
                </p>
            </div>
            
            <!-- SECTION 2: GPS & MAP -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="fas fa-map-marker-alt"></i> 2. Lokasi GPS
                </div>
                
                <div class="gps-section">
                    <div class="gps-status">
                        <div class="gps-icon"><i class="fas fa-satellite-dish"></i></div>
                        <div class="gps-info">
                            <div class="gps-coords" id="gpsCoords">-</div>
                            <div class="gps-address" id="gpsAddress">-</div>
                            <div class="gps-accuracy" id="gpsAccuracy">-</div>
                        </div>
                        <div class="gps-badge warning" id="gpsBadge">Mendapatkan GPS...</div>
                    </div>
                    
                    <div class="map-container">
                        <div id="map"></div>
                    </div>
                    
                    <div class="gps-error" id="gpsError">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p id="gpsErrorMessage">Tidak dapat mengakses GPS</p>
                    </div>
                    
                    <button type="button" class="camera-btn secondary" id="getGpsBtn" onclick="getGPS()" style="width: 100%; margin-top: 10px;">
                        <i class="fas fa-sync-alt"></i> Dapatkan Lokasi GPS
                    </button>
                </div>
            </div>
            
            <!-- SECTION 3: TIPE CANVASING -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="fas fa-tag"></i> 3. Tipe Canvasing
                </div>
                
                <div class="canvasing-type-grid">
                    <label class="type-option">
                        <input type="radio" name="canvasing_type" value="individual" checked onchange="toggleCanvasingType()">
                        <span class="type-icon"><i class="fas fa-user"></i></span>
                        <span class="type-label">Individual</span>
                    </label>
                    <label class="type-option">
                        <input type="radio" name="canvasing_type" value="instansi" onchange="toggleCanvasingType()">
                        <span class="type-icon"><i class="fas fa-building"></i></span>
                        <span class="type-label">Instansi</span>
                    </label>
                    <label class="type-option">
                        <input type="radio" name="canvasing_type" value="toko" onchange="toggleCanvasingType()">
                        <span class="type-icon"><i class="fas fa-store"></i></span>
                        <span class="type-label">Toko</span>
                    </label>
                    <label class="type-option">
                        <input type="radio" name="canvasing_type" value="warung" onchange="toggleCanvasingType()">
                        <span class="type-icon"><i class="fas fa-coffee"></i></span>
                        <span class="type-label">Warung</span>
                    </label>
                    <label class="type-option">
                        <input type="radio" name="canvasing_type" value="kantor" onchange="toggleCanvasingType()">
                        <span class="type-icon"><i class="fas fa-briefcase"></i></span>
                        <span class="type-label">Kantor</span>
                    </label>
                    <label class="type-option">
                        <input type="radio" name="canvasing_type" value="rumah" onchange="toggleCanvasingType()">
                        <span class="type-icon"><i class="fas fa-home"></i></span>
                        <span class="type-label">Rumah</span>
                    </label>
                    <label class="type-option">
                        <input type="radio" name="canvasing_type" value="lainnya" onchange="toggleCanvasingType()">
                        <span class="type-icon"><i class="fas fa-ellipsis-h"></i></span>
                        <span class="type-label">Lainnya</span>
                    </label>
                </div>
            </div>
            
            <!-- SECTION 4: DATA INDIVIDUAL (DEFAULT TAMPAK) -->
            <div class="form-section" id="individualSection">
                <div class="form-section-title">
                    <i class="fas fa-user"></i> 4. Data Individual
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label><i class="fas fa-user"></i> Nama Lengkap</label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control" placeholder="Contoh: Budi Santoso">
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fab fa-whatsapp"></i> No. WhatsApp</label>
                        <input type="tel" name="customer_phone" id="customer_phone" class="form-control" 
                               placeholder="0812xxxx" 
                               maxlength="13"
                               onkeypress="return onlyNumbers(event)"
                               oninput="this.value = this.value.slice(0,13)">
                    </div>
                </div>
            </div>
            
            <!-- SECTION 5: DATA INSTANSI (HIDDEN DEFAULT) -->
            <div class="form-section" id="instansiSection" style="display: none;">
                <div class="form-section-title">
                    <i class="fas fa-building"></i> 4. Data Instansi / Toko / Warung / Kantor
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label><i class="fas fa-building"></i> Nama Instansi / Toko / Warung</label>
                        <input type="text" name="instansi_name" id="instansi_name" class="form-control" placeholder="Contoh: PT Maju Jaya / Toko Sembako 99">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user-tie"></i> Nama PIC</label>
                        <input type="text" name="pic_name" id="pic_name" class="form-control" placeholder="Contoh: Pak RT / Bu Lurah">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fab fa-whatsapp"></i> No. WhatsApp PIC</label>
                        <input type="tel" name="pic_phone" id="pic_phone" class="form-control" 
                               placeholder="0812xxxx" 
                               maxlength="13"
                               onkeypress="return onlyNumbers(event)"
                               oninput="this.value = this.value.slice(0,13)">
                    </div>
                </div>
            </div>
            
            <!-- SECTION 6: LOKASI -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="fas fa-map-marker-alt"></i> 5. Lokasi
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label><i class="fas fa-map-marker-alt"></i> Pilih Lokasi Canvasing</label>
                        <select name="location_key" id="location_key" class="form-select" required onchange="validateLocation()">
                            <option value="" disabled selected>— Pilih Lokasi —</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['location_key'] ?>">
                                <?= $loc['icon'] ?> <?= htmlspecialchars($loc['display_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group full-width" id="locationValidation"></div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-map-pin"></i> Alamat Lengkap</label>
                        <textarea name="address" id="address" class="form-control" rows="2" placeholder="Alamat lengkap lokasi..."></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-sticky-note"></i> Catatan (opsional)</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Hasil canvasing, info penting, dll..."></textarea>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-submit" id="submitBtn" disabled>
                <span>SIMPAN DATA CANVASING</span>
                <i class="fas fa-save"></i>
            </button>
        </form>
    </div>
    
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Canvasing System v7.0</p>
    </div>
    
</div>

<script>
// ===== GLOBAL VARIABLES =====
let videoStream = null;
let isPhotoTaken = false;
let isGpsValid = false;
let isLocationValid = false;
let isCameraReady = false;
let currentFacingMode = 'environment';
let map = null;
let mapMarker = null;
let mapInitialized = false;
let hasMultipleCameras = false;
let reverseGeocodingCache = {};

// ===== FUNGSI UNTUK MEMBATASI INPUT NOMOR =====
function limitPhoneInput(input, maxLength = 13) {
    if (input.value.length > maxLength) {
        input.value = input.value.slice(0, maxLength);
    }
}

// ===== ONLY NUMBERS =====
function onlyNumbers(e) {
    var charCode = (e.which) ? e.which : e.keyCode;
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
    }
    return true;
}

// ===== TOAST =====
function showToast(message, type = 'info') {
    const oldToast = document.querySelector('.toast-message');
    if (oldToast) oldToast.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast-message ${type}`;
    
    let icon = '';
    if (type === 'success') icon = '<i class="fas fa-check-circle"></i>';
    else if (type === 'error') icon = '<i class="fas fa-exclamation-circle"></i>';
    else icon = '<i class="fas fa-info-circle"></i>';
    
    toast.innerHTML = icon + ' ' + message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===== TOGGLE CANVASING TYPE =====
function toggleCanvasingType() {
    const type = document.querySelector('input[name="canvasing_type"]:checked').value;
    const individualSection = document.getElementById('individualSection');
    const instansiSection = document.getElementById('instansiSection');
    
    if (type === 'individual') {
        individualSection.style.display = 'block';
        instansiSection.style.display = 'none';
        
        // Non-required untuk field instansi
        document.getElementById('instansi_name').required = false;
        document.getElementById('pic_name').required = false;
        document.getElementById('pic_phone').required = false;
    } else {
        individualSection.style.display = 'none';
        instansiSection.style.display = 'block';
        
        // Required untuk field instansi
        document.getElementById('instansi_name').required = true;
        document.getElementById('pic_name').required = true;
        
        // Kosongkan field individual
        document.getElementById('customer_name').value = '';
        document.getElementById('customer_phone').value = '';
    }
}

// ===== INIT MAP =====
function initMap(lat, lng) {
    if (!mapInitialized) {
        map = L.map('map').setView([lat, lng], 17);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        mapInitialized = true;
    } else {
        map.setView([lat, lng], 17);
    }
    
    if (mapMarker) {
        map.removeLayer(mapMarker);
    }
    
    mapMarker = L.marker([lat, lng]).addTo(map)
        .bindPopup('Lokasi Anda')
        .openPopup();
}

// ===== CEK KETERSEDIAAN KAMERA =====
async function checkCameras() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(device => device.kind === 'videoinput');
        hasMultipleCameras = videoDevices.length > 1;
        console.log('Video devices found:', videoDevices.length);
        return videoDevices.length;
    } catch (err) {
        console.error('Error enumerating devices:', err);
        return 0;
    }
}

// ===== START CAMERA =====
async function startCamera() {
    const video = document.getElementById('videoElement');
    const overlay = document.getElementById('cameraOverlay');
    const startBtn = document.getElementById('startCameraBtn');
    const switchBtn = document.getElementById('switchCameraBtn');
    const captureBtn = document.getElementById('captureBtn');
    const cameraError = document.getElementById('cameraError');
    const errorMsg = document.getElementById('cameraErrorMessage');
    
    cameraError.style.display = 'none';
    
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        errorMsg.textContent = 'Browser tidak mendukung akses kamera';
        cameraError.style.display = 'block';
        showToast('Browser tidak mendukung kamera', 'error');
        return;
    }
    
    overlay.innerHTML = '<i class="fas fa-spinner fa-spin"></i><p>Meminta izin kamera...</p>';
    overlay.style.display = 'flex';
    
    await checkCameras();
    
    // Coba dengan kamera belakang dulu
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
            audio: false
        });
        
        handleCameraSuccess(stream, video, overlay, startBtn, switchBtn, captureBtn, cameraError);
        currentFacingMode = 'environment';
        document.getElementById('cameraModeText').textContent = 'Kamera Belakang';
        
    } catch (err) {
        console.log('Gagal dengan facingMode environment, coba tanpa constraint');
        
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: false
            });
            
            handleCameraSuccess(stream, video, overlay, startBtn, switchBtn, captureBtn, cameraError);
            
            const track = stream.getVideoTracks()[0];
            const settings = track.getSettings ? track.getSettings() : {};
            if (settings.facingMode) {
                currentFacingMode = settings.facingMode;
                document.getElementById('cameraModeText').textContent = 
                    currentFacingMode === 'environment' ? 'Kamera Belakang' : 'Kamera Depan';
            }
            
        } catch (finalErr) {
            console.error('Final camera error:', finalErr);
            handleCameraError(finalErr, cameraError, errorMsg, overlay, startBtn);
        }
    }
}

function handleCameraSuccess(stream, video, overlay, startBtn, switchBtn, captureBtn, cameraError) {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
    }
    
    videoStream = stream;
    video.srcObject = stream;
    
    video.onloadedmetadata = function() {
        video.play()
            .then(() => {
                video.style.display = 'block';
                overlay.style.display = 'none';
                startBtn.disabled = true;
                captureBtn.disabled = false;
                cameraError.style.display = 'none';
                isCameraReady = true;
                
                if (hasMultipleCameras) {
                    switchBtn.disabled = false;
                }
                
                showToast('✅ Kamera siap', 'success');
            })
            .catch(playErr => {
                console.error('Play error:', playErr);
                video.style.display = 'none';
                overlay.style.display = 'flex';
                overlay.innerHTML = '<i class="fas fa-exclamation-triangle"></i><p>Gagal memutar video</p><small>Coba refresh</small>';
                startBtn.disabled = false;
                isCameraReady = false;
            });
    };
}

function handleCameraError(err, cameraError, errorMsg, overlay, startBtn) {
    console.error('Camera error:', err);
    
    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        errorMsg.textContent = 'Izin kamera ditolak. Izinkan akses kamera.';
    } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        errorMsg.textContent = 'Tidak ada kamera yang terdeteksi.';
    } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
        errorMsg.textContent = 'Kamera sedang digunakan oleh aplikasi lain.';
    } else {
        errorMsg.textContent = 'Gagal mengakses kamera';
    }
    
    cameraError.style.display = 'flex';
    startBtn.disabled = false;
    overlay.innerHTML = '<i class="fas fa-camera"></i><p>Kamera belum aktif</p>';
    isCameraReady = false;
    
    showToast('❌ ' + errorMsg.textContent, 'error');
}

// ===== SWITCH CAMERA =====
async function switchCamera() {
    if (!isCameraReady) return;
    
    currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
    
    const video = document.getElementById('videoElement');
    const overlay = document.getElementById('cameraOverlay');
    const startBtn = document.getElementById('startCameraBtn');
    const switchBtn = document.getElementById('switchCameraBtn');
    const captureBtn = document.getElementById('captureBtn');
    const cameraError = document.getElementById('cameraError');
    const cameraModeText = document.getElementById('cameraModeText');
    
    cameraModeText.textContent = currentFacingMode === 'environment' ? 'Kamera Belakang' : 'Kamera Depan';
    
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
    }
    
    overlay.innerHTML = '<i class="fas fa-spinner fa-spin"></i><p>Mengganti kamera...</p>';
    overlay.style.display = 'flex';
    video.style.display = 'none';
    
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: currentFacingMode },
            audio: false
        });
        
        handleCameraSuccess(stream, video, overlay, startBtn, switchBtn, captureBtn, cameraError);
        
    } catch (err) {
        console.error('Switch camera error:', err);
        showToast('❌ Gagal mengganti kamera', 'error');
        switchBtn.disabled = true;
    }
}

// ===== CAPTURE PHOTO =====
function capturePhoto() {
    if (!isCameraReady || !videoStream) {
        showToast('Kamera belum siap', 'error');
        return;
    }
    
    const video = document.getElementById('videoElement');
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    const photoData = canvas.toDataURL('image/jpeg', 0.9);
    document.getElementById('photo_data').value = photoData;
    
    document.getElementById('photoPreview').src = photoData;
    document.getElementById('photoPreview').style.display = 'block';
    video.style.display = 'none';
    
    document.getElementById('captureBtn').disabled = true;
    document.getElementById('retakeBtn').style.display = 'inline-flex';
    
    isPhotoTaken = true;
    checkFormComplete();
    
    showToast('✅ Foto berhasil diambil', 'success');
}

// ===== RETAKE PHOTO =====
function retakePhoto() {
    const video = document.getElementById('videoElement');
    const preview = document.getElementById('photoPreview');
    const captureBtn = document.getElementById('captureBtn');
    const retakeBtn = document.getElementById('retakeBtn');
    
    video.style.display = 'block';
    preview.style.display = 'none';
    captureBtn.disabled = false;
    retakeBtn.style.display = 'none';
    document.getElementById('photo_data').value = '';
    
    isPhotoTaken = false;
    checkFormComplete();
}

// ===== STOP CAMERA =====
function stopCamera() {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
    isCameraReady = false;
}

// ===== GET ADDRESS FROM COORDINATES VIA PROXY =====
async function getAddressFromCoords(lat, lng) {
    try {
        const response = await fetch(`api/geocode_proxy.php?lat=${lat}&lng=${lng}&t=${Date.now()}`);
        
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        
        const data = await response.json();
        
        if (data.success && data.address) {
            return data.address;
        } else {
            return `Lokasi (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
        }
    } catch (error) {
        console.error('Geocode proxy error:', error);
        return `Lokasi (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
    }
}

// ===== GET GPS DENGAN FALLBACK UNTUK DESKTOP =====
function getGPS() {
    const gpsCoords = document.getElementById('gpsCoords');
    const gpsAddress = document.getElementById('gpsAddress');
    const gpsAccuracy = document.getElementById('gpsAccuracy');
    const gpsBadge = document.getElementById('gpsBadge');
    const gpsError = document.getElementById('gpsError');
    const errorMsg = document.getElementById('gpsErrorMessage');
    const addressField = document.getElementById('address');
    const getGpsBtn = document.getElementById('getGpsBtn');
    
    gpsError.style.display = 'none';
    
    if (!navigator.geolocation) {
        errorMsg.textContent = 'Browser tidak mendukung GPS';
        gpsError.style.display = 'flex';
        showToast('❌ Browser tidak mendukung GPS', 'error');
        return;
    }
    
    gpsBadge.className = 'gps-badge warning';
    gpsBadge.textContent = 'Mencari GPS...';
    gpsCoords.textContent = 'Meminta izin...';
    gpsAddress.textContent = 'Mendapatkan alamat...';
    getGpsBtn.disabled = true;
    getGpsBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mendapatkan GPS...';
    
    // Deteksi apakah di mobile
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    // Konfigurasi GPS berdasarkan device
    const gpsOptions = {
        enableHighAccuracy: isMobile, // TRUE di mobile, FALSE di desktop
        timeout: isMobile ? 15000 : 10000,
        maximumAge: isMobile ? 0 : 60000 // Boleh pakai cache di desktop
    };
    
    console.log('GPS Options:', gpsOptions, 'Is Mobile:', isMobile);
    
    navigator.geolocation.getCurrentPosition(
        async function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const acc = position.coords.accuracy;
            
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            document.getElementById('accuracy').value = acc;
            
            gpsCoords.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            
            // Tampilkan akurasi dengan warna sesuai
            if (acc <= 30) {
                gpsAccuracy.innerHTML = `<i class="fas fa-check-circle" style="color: #2A9D8F;"></i> Akurasi: Sangat Baik (±${acc.toFixed(1)}m)`;
            } else if (acc <= 100) {
                gpsAccuracy.innerHTML = `<i class="fas fa-exclamation-triangle" style="color: #E9C46A;"></i> Akurasi: Sedang (±${acc.toFixed(1)}m)`;
            } else {
                gpsAccuracy.innerHTML = `<i class="fas fa-exclamation-circle" style="color: #D64F3C;"></i> Akurasi: Rendah (±${acc.toFixed(1)}m)`;
            }
            
            // Tampilkan di peta
            initMap(lat, lng);
            
            // Dapatkan alamat via proxy
            try {
                const address = await getAddressFromCoords(lat, lng);
                gpsAddress.textContent = address;
                
                // Isi otomatis ke kolom alamat jika kosong
                if (addressField && addressField.value.trim() === '') {
                    addressField.value = address;
                }
            } catch (e) {
                console.error('Geocode error:', e);
                gpsAddress.textContent = `Lokasi (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
            }
            
            // Validasi GPS
            if (acc <= 100) {
                gpsBadge.className = 'gps-badge success';
                gpsBadge.textContent = 'GPS Valid';
                isGpsValid = true;
            } else {
                gpsBadge.className = 'gps-badge warning';
                gpsBadge.textContent = 'Akurasi Rendah';
                // Tetap anggap valid asal ada koordinat
                isGpsValid = true;
            }
            
            validateLocation();
            checkFormComplete();
            
            getGpsBtn.disabled = false;
            getGpsBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Dapatkan Ulang GPS';
            
            showToast('✅ GPS berhasil didapatkan', 'success');
        },
        function(error) {
            let message = 'Gagal mendapatkan GPS';
            
            if (error.code === 1) {
                message = 'Izin GPS ditolak. Izinkan akses lokasi.';
            } else if (error.code === 2) {
                message = 'GPS tidak tersedia. Coba di luar ruangan.';
            } else if (error.code === 3) {
                message = 'Timeout GPS. Coba lagi.';
            }
            
            gpsCoords.textContent = '-';
            gpsAddress.textContent = '-';
            gpsAccuracy.textContent = '-';
            gpsBadge.className = 'gps-badge error';
            gpsBadge.textContent = 'Error';
            
            errorMsg.textContent = message;
            gpsError.style.display = 'flex';
            
            isGpsValid = false;
            checkFormComplete();
            
            getGpsBtn.disabled = false;
            getGpsBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Coba Lagi';
            
            showToast('❌ ' + message, 'error');
        },
        gpsOptions
    );
}

// ===== VALIDATE LOCATION =====
function validateLocation() {
    const locationKey = document.getElementById('location_key').value;
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    const validationDiv = document.getElementById('locationValidation');
    
    if (!locationKey) {
        validationDiv.innerHTML = '';
        isLocationValid = false;
        checkFormComplete();
        return;
    }
    
    if (!lat || !lng) {
        validationDiv.innerHTML = `
            <div class="location-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>⚠️ Dapatkan GPS terlebih dahulu</span>
            </div>
        `;
        isLocationValid = false;
        checkFormComplete();
        return;
    }
    
    validationDiv.innerHTML = `
        <div class="location-warning">
            <i class="fas fa-spinner fa-spin"></i>
            <span>⏳ Memvalidasi lokasi...</span>
        </div>
    `;
    
    fetch('api/canvasing_validate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            location_key: locationKey,
            latitude: parseFloat(lat),
            longitude: parseFloat(lng),
            developer_id: <?= $developer_id ?>
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.valid) {
            validationDiv.innerHTML = `
                <div class="location-valid">
                    <i class="fas fa-check-circle"></i>
                    <span>✅ Lokasi valid! Anda berada di area yang benar.</span>
                </div>
            `;
            isLocationValid = true;
        } else {
            validationDiv.innerHTML = `
                <div class="location-invalid">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>⛔ ${data.message || 'Lokasi tidak valid'}</span>
                </div>
            `;
            isLocationValid = false;
        }
        checkFormComplete();
    })
    .catch(err => {
        console.error('Validation error:', err);
        validationDiv.innerHTML = `
            <div class="location-invalid">
                <i class="fas fa-exclamation-triangle"></i>
                <span>❌ Gagal validasi lokasi</span>
            </div>
        `;
        isLocationValid = false;
        checkFormComplete();
    });
}

// ===== CHECK FORM COMPLETE =====
function checkFormComplete() {
    const submitBtn = document.getElementById('submitBtn');
    const locationKey = document.getElementById('location_key').value;
    const canvasingType = document.querySelector('input[name="canvasing_type"]:checked').value;
    
    let typeValid = true;
    
    if (canvasingType === 'individual') {
        // Tidak wajib diisi, tapi kalau diisi harus valid
        typeValid = true;
    } else {
        // Untuk instansi, wajib ada nama instansi dan nama PIC
        const instansiName = document.getElementById('instansi_name').value.trim();
        const picName = document.getElementById('pic_name').value.trim();
        
        if (!instansiName || !picName) {
            typeValid = false;
        }
    }
    
    console.log('Check form:', {
        isPhotoTaken,
        isGpsValid,
        isLocationValid,
        locationKey,
        typeValid
    });
    
    if (isPhotoTaken && isGpsValid && isLocationValid && locationKey && typeValid) {
        submitBtn.disabled = false;
    } else {
        submitBtn.disabled = true;
    }
}

// ===== SUBMIT CANVASING =====
function submitCanvasing(e) {
    e.preventDefault();
    
    // Validasi nomor telepon di frontend
    const canvasingType = document.querySelector('input[name="canvasing_type"]:checked').value;
    
    if (canvasingType === 'individual') {
        const customerPhone = document.getElementById('customer_phone').value;
        if (customerPhone) {
            const cleanPhone = customerPhone.replace(/\D/g, '');
            if (cleanPhone.length < 10 || cleanPhone.length > 13) {
                showToast('❌ No. Customer harus 10-13 digit', 'error');
                return;
            }
        }
    } else {
        const picPhone = document.getElementById('pic_phone').value;
        if (picPhone) {
            const cleanPhone = picPhone.replace(/\D/g, '');
            if (cleanPhone.length < 10 || cleanPhone.length > 13) {
                showToast('❌ No. PIC harus 10-13 digit', 'error');
                return;
            }
        }
    }
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    submitBtn.disabled = true;
    
    const formData = new FormData(document.getElementById('canvasingForm'));
    
    // Tambahkan canvasing_type ke form data
    formData.append('canvasing_type', canvasingType);
    
    fetch('api/canvasing_upload.php', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('HTTP error ' + res.status);
        }
        return res.json();
    })
    .then(res => {
        if (res.success) {
            showToast('✅ ' + res.message, 'success');
            
            document.getElementById('canvasingForm').reset();
            document.getElementById('photo_data').value = '';
            document.getElementById('latitude').value = '';
            document.getElementById('longitude').value = '';
            document.getElementById('accuracy').value = '';
            
            document.getElementById('gpsCoords').textContent = '-';
            document.getElementById('gpsAddress').textContent = '-';
            document.getElementById('gpsAccuracy').textContent = '-';
            document.getElementById('gpsBadge').className = 'gps-badge warning';
            document.getElementById('gpsBadge').textContent = 'Mendapatkan GPS...';
            
            document.getElementById('locationValidation').innerHTML = '';
            
            isPhotoTaken = false;
            isGpsValid = false;
            isLocationValid = false;
            
            retakePhoto();
            
            setTimeout(() => {
                window.location.href = 'marketing_canvasing_history.php';
            }, 2000);
        } else {
            showToast('❌ ' + res.message, 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(err => {
        console.error('Submit error:', err);
        showToast('❌ Terjadi kesalahan: ' + err.message, 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// ===== DATE TIME =====
function updateDateTime() {
    const now = new Date();
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { 
        hour12: false 
    });
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Event listeners untuk check form
    document.getElementById('customer_name').addEventListener('input', checkFormComplete);
    document.getElementById('customer_phone').addEventListener('input', checkFormComplete);
    document.getElementById('instansi_name').addEventListener('input', checkFormComplete);
    document.getElementById('pic_name').addEventListener('input', checkFormComplete);
    document.getElementById('pic_phone').addEventListener('input', checkFormComplete);
    
    // Batasi input nomor
    const phoneInputs = ['customer_phone', 'pic_phone'];
    phoneInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', function() {
                if (this.value.length > 13) {
                    this.value = this.value.slice(0, 13);
                }
            });
        }
    });
    
    window.addEventListener('beforeunload', function() {
        stopCamera();
    });
});
</script>

<?php include 'includes/footer.php'; ?>