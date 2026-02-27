<?php
/**
 * MARKETING_OFFLINE_FORM.PHP - LEADENGINE
 * Version: 3.0.0 - Form Input Offline untuk Marketing
 * UPDATE: Tambah Source Marketing Internal & External + Assignment
 * MOBILE FIRST UI - FULL CODE 100% LENGKAP TANPA POTONGAN
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
$marketing_phone = $_SESSION['marketing_phone'] ?? '';
$developer_id = $_SESSION['marketing_developer_id'] ?? 0;

// Ambil lokasi untuk dropdown
$locations = [];
$stmt = $conn->prepare("SELECT * FROM locations WHERE is_active = 1 ORDER BY sort_order");
$stmt->execute();
$locations = $stmt->fetchAll();

// Ambil cluster milik developer ini
$clusters = [];
if ($developer_id > 0) {
    $stmt = $conn->prepare("
        SELECT * FROM clusters 
        WHERE developer_id = ? AND is_active = 1 
        ORDER BY nama_cluster
    ");
    $stmt->execute([$developer_id]);
    $clusters = $stmt->fetchAll();
}

// Ambil semua marketing internal untuk dropdown (kecuali diri sendiri)
$internal_marketing = [];
if ($developer_id > 0) {
    $stmt = $conn->prepare("
        SELECT id, nama_lengkap, phone 
        FROM marketing_team 
        WHERE developer_id = ? AND is_active = 1 AND id != ?
        ORDER BY nama_lengkap
    ");
    $stmt->execute([$developer_id, $marketing_id]);
    $internal_marketing = $stmt->fetchAll();
}

// Ambil data external marketing (Super Admin)
$external_data = getExternalMarketingData();

$page_title = 'Form Input Offline';
$page_subtitle = 'Tambah Customer dari Sumber Offline';
$page_icon = 'fas fa-file-alt';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ===== VARIABLES ===== */
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

/* ===== FORM CARD ===== */
.form-card {
    background: white;
    border-radius: 28px;
    padding: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    max-width: 100%;
    margin: 0 auto;
}

.form-section {
    margin-bottom: 24px;
    padding-bottom: 20px;
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

.form-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.form-group {
    margin-bottom: 0;
    width: 100%;
}

.form-group.full-width {
    width: 100%;
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
    width: 18px;
}

.form-control, .form-select {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    background: white;
    transition: all 0.2s;
    min-height: 52px;
    appearance: none;
    -webkit-appearance: none;
}

.form-select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%231B4A3C' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    padding-right: 48px;
}

.form-control:focus, .form-select:focus {
    border-color: var(--secondary);
    outline: none;
    box-shadow: 0 0 0 4px rgba(214,79,60,0.1);
}

/* ===== NAME ROW ===== */
.name-row {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 0;
}

/* ===== SELECTOR GROUP ===== */
.selector-group {
    background: var(--primary-soft);
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 0;
}

.selector-title {
    color: var(--primary);
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.selector-title i {
    color: var(--secondary);
}

.selector-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.unit-preview {
    background: white;
    border-radius: 16px;
    padding: 16px;
    margin-top: 16px;
    border-left: 6px solid var(--success);
}

.unit-preview-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
}

.unit-preview-label {
    color: var(--text-muted);
}

.unit-preview-value {
    font-weight: 700;
    color: var(--primary);
}

.unit-preview-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
}

.unit-preview-badge.Subsidi {
    background: var(--success);
    color: white;
}

.unit-preview-badge.Komersil {
    background: var(--info);
    color: white;
}

/* ===== SOURCE SELECTOR - UPDATED ===== */
.source-group {
    background: var(--primary-soft);
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 0;
}

.source-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 15px;
}

.source-option {
    width: 100%;
}

.source-option input[type="radio"] {
    display: none;
}

.source-option label {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: white;
    border: 2px solid var(--border);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 600;
    font-size: 14px;
    min-height: 56px;
}

.source-option label i {
    font-size: 20px;
    color: var(--primary);
    width: 24px;
    text-align: center;
}

.source-option input[type="radio"]:checked + label {
    border-color: var(--secondary);
    background: linear-gradient(135deg, rgba(214,79,60,0.05), rgba(255,107,74,0.05));
    box-shadow: 0 4px 12px rgba(214,79,60,0.15);
}

.source-option input[type="radio"]:checked + label i {
    color: var(--secondary);
}

/* ===== SOURCE DETAIL ===== */
.source-detail {
    margin-top: 15px;
    display: none;
}

.source-detail.show {
    display: block;
}

.source-detail input,
.source-detail select {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-size: 16px;
    min-height: 52px;
}

.source-detail input:focus,
.source-detail select:focus {
    border-color: var(--secondary);
    outline: none;
}

/* ===== MARKETING CARD ===== */
.marketing-card {
    background: linear-gradient(135deg, var(--primary-soft), white);
    border-radius: 16px;
    padding: 16px;
    margin-top: 10px;
    border-left: 4px solid var(--secondary);
}

.marketing-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
}

.marketing-label {
    color: var(--text-muted);
    font-weight: 500;
}

.marketing-value {
    font-weight: 700;
    color: var(--primary);
}

/* ===== SCORE PREVIEW ===== */
.score-preview {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    border-radius: 20px;
    padding: 20px;
    margin: 20px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
}

.score-info {
    display: flex;
    align-items: center;
    gap: 15px;
    width: 100%;
}

.score-circle {
    width: 60px;
    height: 60px;
    background: var(--primary);
    border-radius: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 800;
    font-size: 20px;
    box-shadow: 0 8px 15px rgba(27,74,60,0.2);
    flex-shrink: 0;
}

.score-text {
    color: var(--primary);
    font-weight: 700;
    font-size: 14px;
    text-align: left;
}

.score-badge {
    background: white;
    padding: 8px 24px;
    border-radius: 40px;
    font-weight: 700;
    color: var(--primary);
    font-size: 14px;
    border: 2px solid var(--secondary);
    display: inline-block;
}

/* ===== SUBMIT BUTTON ===== */
.btn-submit {
    width: 100%;
    padding: 18px 20px;
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
    min-height: 60px;
}

.btn-submit:active {
    transform: scale(0.98);
}

.btn-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
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
    
    .form-card {
        max-width: 800px;
        padding: 30px;
        margin: 0 auto;
    }
    
    .form-grid {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .form-group {
        flex: 1 1 calc(50% - 8px);
    }
    
    .form-group.full-width {
        flex: 1 1 100%;
    }
    
    .name-row {
        flex-direction: row;
        gap: 16px;
    }
    
    .name-row .form-group {
        flex: 1;
    }
    
    .selector-grid {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .selector-grid .form-group {
        flex: 1 1 calc(25% - 12px);
    }
    
    .source-options {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .source-option {
        flex: 1 1 calc(33.333% - 8px);
    }
    
    .score-preview {
        flex-direction: row;
        justify-content: space-between;
    }
    
    .score-info {
        width: auto;
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
    
    <!-- FORM CARD -->
    <div class="form-card">
        <form id="offlineForm" onsubmit="submitOfflineForm(event)">
            <input type="hidden" name="marketing_id" value="<?= $marketing_id ?>">
            <input type="hidden" name="developer_id" value="<?= $developer_id ?>">
            
            <!-- SECTION 1: DATA CUSTOMER -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="fas fa-user"></i> Data Customer
                </div>
                
                <div class="form-grid">
                    <div class="name-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Nama Depan <span class="required">*</span></label>
                            <input type="text" name="first_name" id="first_name" class="form-control" placeholder="Contoh: Budi" required maxlength="50">
                            <div class="error-msg" id="first_name_error"></div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Nama Belakang</label>
                            <input type="text" name="last_name" id="last_name" class="form-control" placeholder="Contoh: Santoso" maxlength="50">
                            <div class="error-msg" id="last_name_error"></div>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fab fa-whatsapp"></i> Nomor WhatsApp <span class="required">*</span></label>
                        <input type="tel" name="phone" id="phone" class="form-control" placeholder="0812 3456 7890" required maxlength="15" onkeypress="return onlyNumbers(event)" oninput="validatePhoneInput(this)">
                        <div class="validation-msg" id="phone_validation"></div>
                        <div class="error-msg" id="phone_error"></div>
                        <small style="color: var(--text-muted); display: block; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> Minimal 10 digit, maksimal 13 digit
                        </small>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-envelope"></i> Email (opsional)</label>
                        <input type="email" name="email" id="email" class="form-control" placeholder="nama@email.com" maxlength="100" oninput="validateEmailInput(this)">
                        <div class="error-msg" id="email_error"></div>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 2: LOKASI & UNIT -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="fas fa-map-marker-alt"></i> Lokasi & Unit
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label><i class="fas fa-map-marker-alt"></i> Pilih Lokasi <span class="required">*</span></label>
                        <select name="location_key" id="location_key" class="form-select" required onchange="loadUnitTypes()">
                            <option value="" disabled selected>— Pilih Lokasi —</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['location_key'] ?>" data-subsidi="<?= htmlspecialchars($loc['subsidi_units'] ?? '') ?>" data-komersil="<?= htmlspecialchars($loc['komersil_units'] ?? '') ?>">
                                <?= $loc['icon'] ?> <?= htmlspecialchars($loc['display_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error-msg" id="location_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-home"></i> Tipe Unit <span class="required">*</span></label>
                        <select name="unit_type" id="unit_type" class="form-select" required>
                            <option value="" disabled selected>— Pilih Lokasi Dulu —</option>
                        </select>
                        <div class="error-msg" id="unit_type_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Program <span class="required">*</span></label>
                        <select name="program" id="program" class="form-select" required onchange="filterUnitTypes()">
                            <option value="" disabled selected>— Pilih Program —</option>
                            <option value="Subsidi">Subsidi</option>
                            <option value="Komersil">Komersil</option>
                        </select>
                        <div class="error-msg" id="program_error"></div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-map-pin"></i> Alamat (opsional)</label>
                        <textarea name="address" id="address" class="form-control" rows="2" placeholder="Alamat lengkap customer..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-city"></i> Kota (opsional)</label>
                        <input type="text" name="city" id="city" class="form-control" placeholder="Contoh: Kuningan">
                    </div>
                </div>
            </div>
            
            <!-- SECTION 3: CLUSTER, BLOCK, UNIT (OPSIONAL) -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="fas fa-home"></i> Pilih Unit Spesifik (Opsional)
                </div>
                
                <div class="selector-group">
                    <div class="selector-title">
                        <i class="fas fa-layer-group"></i> Cluster & Block
                    </div>
                    
                    <div class="selector-grid">
                        <div class="form-group">
                            <label>Cluster</label>
                            <select id="clusterSelect" class="form-select" onchange="loadBlocks()">
                                <option value="">Pilih Cluster</option>
                                <?php foreach ($clusters as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nama_cluster']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Program</label>
                            <select id="programSelect" class="form-select" onchange="loadBlocks()" disabled>
                                <option value="">Pilih Program</option>
                                <option value="Subsidi">Subsidi</option>
                                <option value="Komersil">Komersil</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Block</label>
                            <select id="blockSelect" class="form-select" onchange="loadUnits()" disabled>
                                <option value="">Pilih Block</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Unit</label>
                            <select id="unitSelect" class="form-select" onchange="selectUnit()" disabled>
                                <option value="">Pilih Unit</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Preview Unit Terpilih -->
                    <div class="unit-preview" id="unitPreview" style="display: none;">
                        <div class="unit-preview-row">
                            <span class="unit-preview-label">Unit</span>
                            <span class="unit-preview-value" id="previewUnit">-</span>
                        </div>
                        <div class="unit-preview-row">
                            <span class="unit-preview-label">Tipe</span>
                            <span class="unit-preview-value" id="previewTipe">-</span>
                        </div>
                        <div class="unit-preview-row">
                            <span class="unit-preview-label">Program</span>
                            <span id="previewProgram" class="unit-preview-badge">-</span>
                        </div>
                        <div class="unit-preview-row">
                            <span class="unit-preview-label">Harga</span>
                            <span class="unit-preview-value" id="previewHarga">-</span>
                        </div>
                        <input type="hidden" name="unit_id" id="selectedUnitId" value="">
                    </div>
                </div>
            </div>
            
            <!-- SECTION 4: SUMBER CUSTOMER (LENGKAP DENGAN MARKETING) -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="fas fa-chart-line"></i> Sumber Customer
                </div>
                
                <div class="source-group">
                    <div class="source-options">
                        <!-- BROSUR -->
                        <div class="source-option">
                            <input type="radio" name="source_type" id="source_brosur" value="brosur" onchange="toggleSourceDetail('brosur')">
                            <label for="source_brosur">
                                <i class="fas fa-file-pdf"></i>
                                <span>Brosur</span>
                            </label>
                        </div>
                        
                        <!-- EVENT -->
                        <div class="source-option">
                            <input type="radio" name="source_type" id="source_event" value="event" onchange="toggleSourceDetail('event')">
                            <label for="source_event">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Event</span>
                            </label>
                        </div>
                        
                        <!-- IKLAN KANTOR -->
                        <div class="source-option">
                            <input type="radio" name="source_type" id="source_iklan_kantor" value="iklan_kantor" onchange="toggleSourceDetail('iklan_kantor')">
                            <label for="source_iklan_kantor">
                                <i class="fas fa-building"></i>
                                <span>Iklan Kantor</span>
                            </label>
                        </div>
                        
                        <!-- IKLAN PRIBADI -->
                        <div class="source-option">
                            <input type="radio" name="source_type" id="source_iklan_pribadi" value="iklan_pribadi" onchange="toggleSourceDetail('iklan_pribadi')">
                            <label for="source_iklan_pribadi">
                                <i class="fas fa-user-tie"></i>
                                <span>Iklan Pribadi</span>
                            </label>
                        </div>
                        
                        <!-- REFERENSI -->
                        <div class="source-option">
                            <input type="radio" name="source_type" id="source_referensi" value="referensi" onchange="toggleSourceDetail('referensi')">
                            <label for="source_referensi">
                                <i class="fas fa-users"></i>
                                <span>Referensi</span>
                            </label>
                        </div>
                        
                        <!-- MARKETING INTERNAL (BARU) -->
                        <div class="source-option">
                            <input type="radio" name="source_type" id="source_marketing_internal" value="marketing_internal" onchange="toggleSourceDetail('marketing_internal')">
                            <label for="source_marketing_internal">
                                <i class="fas fa-user-check"></i>
                                <span>Marketing Internal</span>
                            </label>
                        </div>
                        
                        <!-- MARKETING EXTERNAL (BARU) -->
                        <div class="source-option">
                            <input type="radio" name="source_type" id="source_marketing_external" value="marketing_external" onchange="toggleSourceDetail('marketing_external')">
                            <label for="source_marketing_external">
                                <i class="fas fa-globe"></i>
                                <span>Marketing External</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- DETAIL BROSUR -->
                    <div class="source-detail" id="detail_brosur">
                        <input type="text" name="source_detail_brosur" id="source_detail_brosur" placeholder="Nomor Brosur / Kode Promo" oninput="updateScore()">
                    </div>
                    
                    <!-- DETAIL EVENT -->
                    <div class="source-detail" id="detail_event">
                        <input type="text" name="source_detail_event" id="source_detail_event" placeholder="Nama Event / Lokasi Event" oninput="updateScore()">
                    </div>
                    
                    <!-- DETAIL IKLAN KANTOR -->
                    <div class="source-detail" id="detail_iklan_kantor">
                        <select name="source_detail_iklan_kantor" id="source_detail_iklan_kantor" class="form-select" onchange="updateScore()">
                            <option value="">— Pilih Platform —</option>
                            <option value="iklan_kantor_ig">📱 Instagram</option>
                            <option value="iklan_kantor_fb">📘 Facebook</option>
                            <option value="iklan_kantor_tt">🎵 TikTok</option>
                        </select>
                    </div>
                    
                    <!-- DETAIL IKLAN PRIBADI -->
                    <div class="source-detail" id="detail_iklan_pribadi">
                        <select name="source_detail_iklan_pribadi" id="source_detail_iklan_pribadi" class="form-select" onchange="updateScore()">
                            <option value="">— Pilih Platform —</option>
                            <option value="iklan_pribadi_ig">📱 Instagram</option>
                            <option value="iklan_pribadi_fb">📘 Facebook</option>
                            <option value="iklan_pribadi_tt">🎵 TikTok</option>
                        </select>
                    </div>
                    
                    <!-- DETAIL REFERENSI -->
                    <div class="source-detail" id="detail_referensi">
                        <input type="text" name="source_detail_referensi" id="source_detail_referensi" placeholder="Nama Referensi / Relasi" oninput="updateScore()">
                    </div>
                    
                    <!-- DETAIL MARKETING INTERNAL (BARU) -->
                    <div class="source-detail" id="detail_marketing_internal">
                        <select name="target_marketing_id" id="target_marketing_id" class="form-select">
                            <option value="">— Pilih Marketing Tujuan —</option>
                            <?php foreach ($internal_marketing as $m): ?>
                            <option value="<?= $m['id'] ?>" data-phone="<?= $m['phone'] ?>">
                                <?= htmlspecialchars($m['nama_lengkap']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <!-- Preview Marketing -->
                        <div class="marketing-card" id="marketingPreview" style="display: none;">
                            <div class="marketing-row">
                                <span class="marketing-label">Marketing</span>
                                <span class="marketing-value" id="previewMarketingName">-</span>
                            </div>
                            <div class="marketing-row">
                                <span class="marketing-label">WhatsApp</span>
                                <span class="marketing-value" id="previewMarketingPhone">-</span>
                            </div>
                            <small style="display: block; margin-top: 8px; color: var(--secondary);">
                                <i class="fas fa-info-circle"></i> Lead akan diassign ke marketing ini
                            </small>
                        </div>
                    </div>
                    
                    <!-- DETAIL MARKETING EXTERNAL (BARU) -->
                    <div class="source-detail" id="detail_marketing_external">
                        <div class="marketing-card">
                            <div class="marketing-row">
                                <span class="marketing-label">Marketing External</span>
                                <span class="marketing-value"><?= htmlspecialchars($external_data['nama_lengkap']) ?></span>
                            </div>
                            <div class="marketing-row">
                                <span class="marketing-label">WhatsApp</span>
                                <span class="marketing-value"><?= htmlspecialchars($external_data['nomor_whatsapp']) ?></span>
                            </div>
                            <small style="display: block; margin-top: 8px; color: var(--secondary);">
                                <i class="fas fa-info-circle"></i> Lead akan masuk ke platform (external)
                            </small>
                        </div>
                        <input type="hidden" name="target_marketing_id" value="0">
                    </div>
                </div>
                
                <!-- CATATAN TAMBAHAN -->
                <div class="form-group" style="margin-top: 15px;">
                    <label><i class="fas fa-sticky-note"></i> Catatan (opsional)</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Catatan tambahan tentang customer..."></textarea>
                </div>
            </div>
            
            <!-- SCORE PREVIEW -->
            <div class="score-preview" id="scorePreview">
                <div class="score-info">
                    <div class="score-circle" id="scoreValue">50</div>
                    <div class="score-text">
                        <div>Lead Score</div>
                        <small>Berdasarkan sumber & data</small>
                    </div>
                </div>
                <div class="score-badge" id="scoreCategory">BARU</div>
            </div>
            
            <!-- SUBMIT BUTTON -->
            <button type="submit" class="btn-submit" id="submitBtn">
                <span>SIMPAN DATA CUSTOMER</span>
                <i class="fas fa-arrow-right"></i>
            </button>
            
            <div style="text-align: center; color: var(--text-muted); font-size: 12px;">
                <i class="fas fa-shield-alt"></i> Data akan langsung masuk ke leads Anda
            </div>
        </form>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Offline Form v3.0</p>
    </div>
    
</div>

<script>
// ===== FUNGSI VALIDASI =====
function onlyNumbers(e) {
    var charCode = (e.which) ? e.which : e.keyCode;
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
    }
    return true;
}

function validatePhoneInput(input) {
    const phoneValue = input.value.replace(/\D/g, '');
    const validationMsg = document.getElementById('phone_validation');
    
    input.value = phoneValue;
    input.classList.remove('valid', 'invalid');
    
    if (phoneValue.length === 0) {
        validationMsg.innerHTML = '';
        return;
    }
    
    if (phoneValue.length < 10) {
        validationMsg.innerHTML = '⛔ Minimal 10 digit';
        validationMsg.style.color = '#D64F3C';
        input.classList.add('invalid');
    } else if (phoneValue.length > 13) {
        validationMsg.innerHTML = '⛔ Maksimal 13 digit';
        validationMsg.style.color = '#D64F3C';
        input.classList.add('invalid');
        input.value = phoneValue.slice(0, 13);
    } else {
        if (phoneValue.startsWith('0') || phoneValue.startsWith('62')) {
            validationMsg.innerHTML = '✅ Nomor valid';
            validationMsg.style.color = '#2A9D8F';
            input.classList.add('valid');
            input.classList.remove('invalid');
        } else {
            validationMsg.innerHTML = '⛔ Harus diawali 0 atau 62';
            validationMsg.style.color = '#D64F3C';
            input.classList.add('invalid');
        }
    }
    updateScore();
}

function validateEmailInput(input) {
    const emailValue = input.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    input.classList.remove('valid', 'invalid');
    
    if (emailValue === '') {
        updateScore();
        return;
    }
    
    if (emailRegex.test(emailValue)) {
        input.classList.add('valid');
        input.classList.remove('invalid');
    } else {
        input.classList.add('invalid');
        input.classList.remove('valid');
        document.getElementById('email_error').innerHTML = 'Format email tidak valid';
        document.getElementById('email_error').style.display = 'block';
    }
    updateScore();
}

// ===== LOAD UNIT TYPES =====
function loadUnitTypes() {
    const locationSelect = document.getElementById('location_key');
    const unitSelect = document.getElementById('unit_type');
    
    const selectedOption = locationSelect.options[locationSelect.selectedIndex];
    const subsidiUnits = selectedOption.getAttribute('data-subsidi') || '';
    const komersilUnits = selectedOption.getAttribute('data-komersil') || '';
    
    window.subsidiUnits = subsidiUnits.split(',').filter(u => u.trim());
    window.komersilUnits = komersilUnits.split(',').filter(u => u.trim());
    
    unitSelect.innerHTML = '<option value="" disabled selected>— Pilih Program Dulu —</option>';
}

function filterUnitTypes() {
    const program = document.getElementById('program').value;
    const unitSelect = document.getElementById('unit_type');
    
    if (!program) return;
    
    let units = program === 'Subsidi' ? window.subsidiUnits : window.komersilUnits;
    
    if (units.length > 0) {
        let options = '<option value="" disabled selected>— Pilih Tipe Unit —</option>';
        units.forEach(unit => {
            if (unit.trim()) {
                options += `<option value="${unit.trim()}">${unit.trim()}</option>`;
            }
        });
        unitSelect.innerHTML = options;
    }
}

// ===== CLUSTER, BLOCK, UNIT FUNCTIONS =====
function loadBlocks() {
    const clusterId = document.getElementById('clusterSelect').value;
    const program = document.getElementById('programSelect').value;
    const programSelect = document.getElementById('programSelect');
    const blockSelect = document.getElementById('blockSelect');
    
    if (!clusterId) {
        programSelect.disabled = true;
        blockSelect.disabled = true;
        blockSelect.innerHTML = '<option value="">Pilih Cluster Dulu</option>';
        return;
    }
    
    programSelect.disabled = false;
    
    if (!program) {
        blockSelect.disabled = true;
        blockSelect.innerHTML = '<option value="">Pilih Program Dulu</option>';
        return;
    }
    
    blockSelect.innerHTML = '<option value="">Memuat...</option>';
    blockSelect.disabled = true;
    
    fetch('api/get_blocks.php?cluster_id=' + clusterId + '&program=' + encodeURIComponent(program))
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                let options = '<option value="">Pilih Block</option>';
                data.data.forEach(block => {
                    if (block.available_units > 0) {
                        options += `<option value="${block.id}">${block.nama_block} (${block.available_units} unit)</option>`;
                    }
                });
                blockSelect.innerHTML = options;
                blockSelect.disabled = false;
            } else {
                blockSelect.innerHTML = '<option value="">Tidak ada block</option>';
                blockSelect.disabled = true;
            }
        })
        .catch(err => {
            console.error('Error:', err);
            blockSelect.innerHTML = '<option value="">Error</option>';
        });
}

function loadUnits() {
    const blockId = document.getElementById('blockSelect').value;
    const unitSelect = document.getElementById('unitSelect');
    
    if (!blockId) {
        unitSelect.disabled = true;
        unitSelect.innerHTML = '<option value="">Pilih Block Dulu</option>';
        document.getElementById('unitPreview').style.display = 'none';
        return;
    }
    
    unitSelect.innerHTML = '<option value="">Memuat...</option>';
    unitSelect.disabled = true;
    
    fetch('api/get_units.php?block_id=' + blockId + '&status=AVAILABLE')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                let options = '<option value="">Pilih Unit</option>';
                data.data.forEach(unit => {
                    options += `<option value="${unit.id}" 
                        data-nomor="${unit.nomor_unit}"
                        data-tipe="${unit.tipe_unit}"
                        data-program="${unit.program}"
                        data-harga="${unit.harga}">${unit.nomor_unit} - ${unit.tipe_unit} (${unit.program})</option>`;
                });
                unitSelect.innerHTML = options;
                unitSelect.disabled = false;
            } else {
                unitSelect.innerHTML = '<option value="">Tidak ada unit tersedia</option>';
                unitSelect.disabled = true;
                document.getElementById('unitPreview').style.display = 'none';
            }
        })
        .catch(err => {
            console.error('Error:', err);
            unitSelect.innerHTML = '<option value="">Error</option>';
        });
}

function selectUnit() {
    const unitSelect = document.getElementById('unitSelect');
    const selectedOption = unitSelect.options[unitSelect.selectedIndex];
    
    if (!selectedOption || !selectedOption.value) {
        document.getElementById('unitPreview').style.display = 'none';
        document.getElementById('selectedUnitId').value = '';
        return;
    }
    
    document.getElementById('previewUnit').textContent = selectedOption.dataset.nomor;
    document.getElementById('previewTipe').textContent = selectedOption.dataset.tipe;
    document.getElementById('previewProgram').textContent = selectedOption.dataset.program;
    document.getElementById('previewProgram').className = 'unit-preview-badge ' + selectedOption.dataset.program;
    document.getElementById('previewHarga').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(selectedOption.dataset.harga);
    document.getElementById('selectedUnitId').value = selectedOption.value;
    
    document.getElementById('unitPreview').style.display = 'block';
}

// ===== SOURCE DETAIL TOGGLE =====
function toggleSourceDetail(source) {
    // Sembunyikan semua detail
    document.querySelectorAll('.source-detail').forEach(el => {
        el.classList.remove('show');
    });
    
    // Tampilkan detail yang dipilih
    document.getElementById('detail_' + source).classList.add('show');
    
    // Jika marketing internal, update preview
    if (source === 'marketing_internal') {
        document.getElementById('target_marketing_id').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            if (selected.value) {
                document.getElementById('previewMarketingName').textContent = selected.text;
                document.getElementById('previewMarketingPhone').textContent = selected.dataset.phone;
                document.getElementById('marketingPreview').style.display = 'block';
            } else {
                document.getElementById('marketingPreview').style.display = 'none';
            }
        });
    }
    
    updateScore();
}

// ===== UPDATE SCORE PREVIEW =====
function updateScore() {
    const sourceType = document.querySelector('input[name="source_type"]:checked');
    if (!sourceType) return;
    
    let sourceValue = sourceType.value;
    let sourceDetail = '';
    
    // Ambil detail sesuai source
    if (sourceValue === 'brosur') {
        sourceDetail = document.getElementById('source_detail_brosur').value;
    } else if (sourceValue === 'event') {
        sourceDetail = document.getElementById('source_detail_event').value;
    } else if (sourceValue === 'iklan_kantor') {
        sourceDetail = document.getElementById('source_detail_iklan_kantor').value;
        if (sourceDetail) sourceValue = sourceDetail;
    } else if (sourceValue === 'iklan_pribadi') {
        sourceDetail = document.getElementById('source_detail_iklan_pribadi').value;
        if (sourceDetail) sourceValue = sourceDetail;
    } else if (sourceValue === 'referensi') {
        sourceDetail = document.getElementById('source_detail_referensi').value;
        if (sourceDetail) sourceValue = 'referensi_nama';
    } else if (sourceValue === 'marketing_internal') {
        sourceValue = 'marketing_internal';
    } else if (sourceValue === 'marketing_external') {
        sourceValue = 'marketing_external';
    }
    
    // Data untuk hitung score
    const data = {
        source: sourceValue,
        first_name: document.getElementById('first_name').value,
        last_name: document.getElementById('last_name').value,
        phone: document.getElementById('phone').value,
        email: document.getElementById('email').value,
        location_key: document.getElementById('location_key').value,
        source_detail: sourceDetail
    };
    
    // Panggil API untuk hitung score
    fetch('api/offline_form_api.php?action=calculate_score', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('HTTP error ' + res.status);
        }
        return res.json();
    })
    .then(res => {
        if (res.success) {
            document.getElementById('scoreValue').textContent = res.score;
            document.getElementById('scoreCategory').textContent = res.category;
        }
    })
    .catch(err => {
        console.error('Score update error:', err);
        // Fallback score
        document.getElementById('scoreValue').textContent = '50';
        document.getElementById('scoreCategory').textContent = 'BARU';
    });
}

// ===== SUBMIT FORM =====
function submitOfflineForm(e) {
    e.preventDefault();
    
    // Validasi dasar
    const firstName = document.getElementById('first_name').value.trim();
    if (!firstName) {
        showToast('Nama depan wajib diisi', 'error');
        return;
    }
    
    const phone = document.getElementById('phone').value.replace(/\D/g, '');
    if (phone.length < 10) {
        showToast('Nomor WhatsApp minimal 10 digit', 'error');
        return;
    }
    
    const location = document.getElementById('location_key').value;
    if (!location) {
        showToast('Pilih lokasi', 'error');
        return;
    }
    
    const unitType = document.getElementById('unit_type').value;
    if (!unitType) {
        showToast('Pilih tipe unit', 'error');
        return;
    }
    
    const program = document.getElementById('program').value;
    if (!program) {
        showToast('Pilih program', 'error');
        return;
    }
    
    const sourceType = document.querySelector('input[name="source_type"]:checked');
    if (!sourceType) {
        showToast('Pilih sumber customer', 'error');
        return;
    }
    
    // Validasi khusus marketing internal
    if (sourceType.value === 'marketing_internal') {
        const targetMarketing = document.getElementById('target_marketing_id').value;
        if (!targetMarketing) {
            showToast('Pilih marketing tujuan', 'error');
            return;
        }
    }
    
    // Submit form
    const formData = new FormData(document.getElementById('offlineForm'));
    
    // Tambahkan source detail
    let sourceDetail = '';
    if (sourceType.value === 'brosur') {
        sourceDetail = document.getElementById('source_detail_brosur').value;
    } else if (sourceType.value === 'event') {
        sourceDetail = document.getElementById('source_detail_event').value;
    } else if (sourceType.value === 'iklan_kantor') {
        sourceDetail = document.getElementById('source_detail_iklan_kantor').value;
    } else if (sourceType.value === 'iklan_pribadi') {
        sourceDetail = document.getElementById('source_detail_iklan_pribadi').value;
    } else if (sourceType.value === 'referensi') {
        sourceDetail = document.getElementById('source_detail_referensi').value;
    }
    
    formData.append('source_detail', sourceDetail);
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    
    fetch('api/offline_form_api.php?action=submit', {
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
            setTimeout(() => {
                window.location.href = 'marketing_dashboard.php';
            }, 1500);
        } else {
            showToast('❌ ' + res.message, 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('❌ Terjadi kesalahan: ' + err.message, 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
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
    
    // Event listeners untuk update score
    document.getElementById('first_name').addEventListener('input', updateScore);
    document.getElementById('last_name').addEventListener('input', updateScore);
    document.getElementById('email').addEventListener('input', updateScore);
    document.getElementById('location_key').addEventListener('change', updateScore);
});
</script>

<?php include 'includes/footer.php'; ?>