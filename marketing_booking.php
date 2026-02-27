<?php
/**
 * MARKETING_BOOKING.PHP - LEADENGINE
 * Version: 8.0.0 - FINAL FIX: Program Booking, Upload, CSRF, Tunai
 * MOBILE FIRST - Format Rupiah Otomatis, Keypad Numerik di HP
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

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
$is_internal = ($developer_id > 0);

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Ambil data marketing untuk auto-load
$marketing_data = [
    'nama' => $marketing_name,
    'phone' => $marketing_phone
];

// Ambil semua cluster milik developer ini
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

// Cek apakah ada unit AVAILABLE
$check_units = $conn->prepare("
    SELECT COUNT(*) 
    FROM units u
    JOIN blocks b ON u.block_id = b.id
    JOIN clusters c ON b.cluster_id = c.id
    WHERE c.developer_id = ? AND u.status = 'AVAILABLE'
");
$check_units->execute([$developer_id]);
$total_available = $check_units->fetchColumn();

// Ambil rekening bank developer
$banks = [];
if ($developer_id > 0) {
    $stmt = $conn->prepare("
        SELECT * FROM banks 
        WHERE developer_id = ? AND is_active = 1 
        ORDER BY nama_bank
    ");
    $stmt->execute([$developer_id]);
    $banks = $stmt->fetchAll();
}

$page_title = 'Booking Unit';
$page_subtitle = 'Pilih dan Booking Unit untuk Customer';
$page_icon = 'fas fa-hand-holding-usd';

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

/* ===== MAIN LAYOUT ===== */
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

/* ===== STATS CARD ===== */
.stats-grid {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    padding: 4px 0 16px 0;
    margin-bottom: 16px;
    -webkit-overflow-scrolling: touch;
}

.stats-grid::-webkit-scrollbar {
    height: 4px;
}

.stats-grid::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.stats-grid::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.stat-card {
    flex: 0 0 140px;
    background: white;
    border-radius: 16px;
    padding: 14px;
    border-left: 4px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.stat-icon {
    font-size: 20px;
    color: var(--secondary);
    margin-bottom: 8px;
}

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

/* ===== TABLES ===== */
.table-container {
    background: white;
    border-radius: 24px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    overflow: hidden;
}

.table-responsive {
    overflow-x: auto;
    margin: 0 -20px;
    padding: 0 20px;
    width: calc(100% + 40px);
    -webkit-overflow-scrolling: touch;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

th {
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    font-size: 13px;
    text-transform: uppercase;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

tr:hover td {
    background: var(--primary-soft);
}

/* ===== BADGES ===== */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
}

.status-badge.AVAILABLE {
    background: var(--success);
    color: white;
}

.status-badge.BOOKED {
    background: var(--warning);
    color: #1A2A24;
}

.status-badge.SOLD {
    background: var(--danger);
    color: white;
}

.program-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
}

.program-badge.Subsidi {
    background: var(--success);
    color: white;
}

.program-badge.Komersil {
    background: var(--info);
    color: white;
}

/* ===== ACTION BUTTONS ===== */
.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
}

.action-btn {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    border: 1px solid var(--border);
}

.action-btn.book {
    background: #e8f0fe;
    color: var(--info);
    border-color: var(--info);
}

.action-btn.book:hover {
    background: var(--info);
    color: white;
}

.action-btn.cancel {
    background: #ffeeed;
    color: var(--danger);
    border-color: var(--danger);
}

.action-btn.cancel:hover {
    background: var(--danger);
    color: white;
}

.action-btn.view {
    background: #fff8e1;
    color: #B87C00;
    border-color: #B87C00;
}

.action-btn.view:hover {
    background: #B87C00;
    color: white;
}

/* ===== FILTER BAR ===== */
.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-select, .filter-input {
    flex: 1;
    min-width: 150px;
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    background: white;
    -webkit-appearance: none;
    appearance: none;
}

.filter-select:focus, .filter-input:focus {
    border-color: var(--secondary);
    outline: none;
}

.filter-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    min-height: 44px;
}

.filter-btn.reset {
    background: var(--border);
    color: var(--text);
}

/* ===== TABS ===== */
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.tab-btn {
    flex: 1;
    padding: 14px 20px;
    background: white;
    border: 2px solid var(--border);
    border-radius: 40px;
    font-weight: 600;
    font-size: 14px;
    color: var(--text);
    cursor: pointer;
    transition: all 0.2s;
    min-width: 120px;
    min-height: 52px;
}

.tab-btn.active {
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    border-color: var(--secondary);
    color: white;
}

.tab-btn i {
    margin-right: 6px;
}

/* ===== PAGINATION ===== */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.pagination-btn {
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: white;
    border: 2px solid var(--border);
    color: var(--text);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
}

.pagination-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.pagination-btn:hover:not(.active) {
    background: var(--primary-soft);
    border-color: var(--secondary);
}

/* ===== MODAL ===== */
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

/* ===== FORM ===== */
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

.form-group label .required {
    color: var(--danger);
    margin-left: 2px;
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
    -webkit-appearance: none;
    appearance: none;
}

.form-control:focus, .form-select:focus {
    border-color: var(--secondary);
    outline: none;
}

textarea.form-control {
    min-height: 80px;
    resize: vertical;
}

/* ===== INPUT RUPIAH - KEYPAD ANGKA DI MOBILE ===== */
.rupiah-input {
    text-align: right;
    -webkit-appearance: none;
    appearance: none;
}

input[type="text"].rupiah-input,
input[type="text"][inputmode="numeric"] {
    -webkit-appearance: none;
    appearance: none;
}

/* ===== RADIO GROUP ===== */
.radio-group {
    display: flex;
    gap: 12px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.radio-option {
    flex: 1;
    min-width: 100px;
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

/* ===== PROGRAM CHIP ===== */
.program-container {
    margin-bottom: 20px;
}

.program-chip {
    display: inline-block;
    background: var(--primary-soft);
    border: 1px solid var(--border);
    border-radius: 30px;
    padding: 8px 16px;
    margin: 0 6px 6px 0;
    font-size: 12px;
    font-weight: 600;
    color: var(--primary);
    cursor: pointer;
    transition: all 0.2s;
}

.program-chip.selected {
    background: var(--secondary);
    color: white;
    border-color: var(--secondary);
}

.program-chip.all-in {
    border-left: 4px solid var(--warning);
}

.program-chip i {
    margin-right: 4px;
    font-size: 11px;
}

.program-chip .allin-badge {
    background: var(--warning);
    color: #1A2A24;
    padding: 2px 6px;
    border-radius: 20px;
    font-size: 9px;
    margin-left: 6px;
    font-weight: 700;
}

.selected-programs {
    background: var(--primary-soft);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 15px;
}

.selected-programs .program-chip {
    margin: 2px;
    cursor: default;
}

/* ===== BANK CARD ===== */
.bank-card {
    background: var(--primary-soft);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 12px;
    border-left: 4px solid var(--info);
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.bank-card.selected {
    border-left-color: var(--success);
    background: linear-gradient(135deg, var(--primary-soft), #d4e8e0);
    box-shadow: 0 4px 12px rgba(42,157,143,0.2);
}

.bank-card input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.bank-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 13px;
}

.bank-label {
    color: var(--text-muted);
    font-weight: 500;
}

.bank-value {
    font-weight: 700;
    color: var(--primary);
}

/* ===== UPLOAD AREA ===== */
.upload-area {
    border: 2px dashed var(--border);
    border-radius: 14px;
    padding: 20px;
    text-align: center;
    background: var(--primary-soft);
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 10px;
}

.upload-area:hover {
    border-color: var(--secondary);
    background: #d4e8e0;
}

.upload-area i {
    font-size: 32px;
    color: var(--secondary);
    margin-bottom: 8px;
}

.upload-area p {
    color: var(--text-muted);
    font-size: 13px;
    margin: 0;
}

.upload-preview {
    display: none;
    margin-top: 10px;
    position: relative;
}

.upload-preview img {
    width: 100%;
    max-height: 150px;
    object-fit: cover;
    border-radius: 12px;
    border: 2px solid var(--success);
}

.upload-preview .remove-file {
    position: absolute;
    top: 5px;
    right: 5px;
    width: 30px;
    height: 30px;
    background: var(--danger);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ===== UNIT CARD ===== */
.unit-card {
    background: var(--primary-soft);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 20px;
    border-left: 4px solid var(--info);
}

.unit-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
}

.unit-label {
    color: var(--text-muted);
    font-weight: 500;
}

.unit-value {
    font-weight: 700;
    color: var(--primary);
}

.unit-value.komisi {
    color: var(--success);
    font-weight: 800;
}

/* ===== BOOKING FEE DISPLAY ===== */
.booking-fee-display {
    background: var(--primary);
    color: white;
    padding: 12px;
    border-radius: 12px;
    margin: 15px 0;
    text-align: center;
    font-weight: 700;
    font-size: 16px;
}

/* ===== TRANSFER DETAIL ===== */
.transfer-detail {
    background: var(--primary-soft);
    border-radius: 16px;
    padding: 16px;
    margin: 15px 0;
    display: none;
}

.transfer-detail.show {
    display: block;
}

/* ===== CASH DETAIL ===== */
.cash-detail {
    background: var(--primary-soft);
    border-radius: 16px;
    padding: 16px;
    margin: 15px 0;
    display: none;
}

.cash-detail.show {
    display: block;
}

/* ===== INFO CARD ===== */
.info-card {
    background: #fff3cd;
    border: 1px solid #ffeeba;
    border-radius: 12px;
    padding: 12px;
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #856404;
}

.info-card i {
    font-size: 18px;
    color: #856404;
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

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #FF6B4A);
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    min-height: 52px;
}

.btn-primary:disabled, .btn-danger:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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

/* ===== RESPONSIVE ===== */
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
    
    .modal {
        align-items: center;
        padding: 20px;
    }
    
    .modal-content {
        border-radius: 28px;
        max-width: 700px;
        animation: modalFade 0.3s ease;
    }
    
    @keyframes modalFade {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }
    
    .radio-group {
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
                <span><?= $page_subtitle ?></span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <!-- STATS -->
    <div class="stats-grid" id="statsContainer">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="stat-label">Total Booking</div>
            <div class="stat-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Pending</div>
            <div class="stat-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--success);"></i></div>
            <div class="stat-label">Diterima</div>
            <div class="stat-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-home"></i></div>
            <div class="stat-label">Unit Tersedia</div>
            <div class="stat-value"><?= $total_available ?></div>
        </div>
    </div>
    
    <!-- TABS -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('available')" id="tabAvailableBtn">
            <i class="fas fa-home"></i> Unit Tersedia (<?= $total_available ?>)
        </button>
        <button class="tab-btn" onclick="showTab('mybookings')" id="tabBookingsBtn">
            <i class="fas fa-history"></i> Booking Saya
        </button>
    </div>
    
    <!-- FILTER UNIT TERSEDIA -->
    <div class="filter-bar" id="availableFilter">
        <form class="filter-form" onsubmit="loadAvailableUnits(); return false;">
            <select id="clusterFilter" class="filter-select">
                <option value="">Semua Cluster</option>
                <?php foreach ($clusters as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nama_cluster']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <select id="programFilter" class="filter-select">
                <option value="">Semua Program</option>
                <option value="Subsidi">Subsidi</option>
                <option value="Komersil">Komersil</option>
            </select>
            
            <input type="text" id="searchFilter" class="filter-input" placeholder="Cari nomor unit...">
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> Cari
                </button>
                <button type="button" class="filter-btn reset" onclick="resetFilters()">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </form>
    </div>
    
    <!-- FILTER BOOKING SAYA -->
    <div class="filter-bar" id="bookingFilter" style="display: none;">
        <form class="filter-form" onsubmit="loadMyBookings(1); return false;">
            <select id="bookingStatusFilter" class="filter-select">
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="diterima">Diterima</option>
                <option value="ditolak">Ditolak</option>
            </select>
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> Filter
                </button>
                <button type="button" class="filter-btn reset" onclick="resetBookingFilter()">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </form>
    </div>
    
    <!-- SECTION: UNIT TERSEDIA -->
    <div id="availableSection">
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-home"></i> Unit Tersedia untuk Diboooking</h3>
                <div class="table-badge" id="unitCountBadge">Memuat...</div>
            </div>
            <div class="table-responsive">
                <table id="unitsTable">
                    <thead>
                        <tr>
                            <th>Cluster</th>
                            <th>Block</th>
                            <th>No. Unit</th>
                            <th>Tipe</th>
                            <th>Program</th>
                            <th>Harga</th>
                            <th>Booking Fee</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="unitsTableBody">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                <i class="fas fa-spinner fa-spin"></i> Memuat data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination" id="unitPagination"></div>
        </div>
    </div>
    
    <!-- SECTION: BOOKING SAYA -->
    <div id="myBookingsSection" style="display: none;">
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-history"></i> Riwayat Booking Saya</h3>
                <div class="table-badge" id="bookingCountBadge">Memuat...</div>
            </div>
            <div class="table-responsive">
                <table id="myBookingsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Unit</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="myBookingsBody">
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <i class="fas fa-spinner fa-spin"></i> Memuat data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination" id="bookingPagination"></div>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Booking Unit v8.0 (Final Fix)</p>
    </div>
    
</div>

<!-- MODAL BOOKING -->
<div class="modal" id="bookingModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-hand-holding-usd"></i> Booking Unit</h2>
            <button class="modal-close" onclick="closeBookingModal()">&times;</button>
        </div>
        
        <form id="bookingForm" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="book">
            <input type="hidden" name="unit_id" id="bookingUnitId">
            <input type="hidden" name="marketing_id" value="<?= $marketing_id ?>">
            <input type="hidden" name="developer_id" value="<?= $developer_id ?>">
            <input type="hidden" name="program_ids" id="selectedPrograms" value="">
            <input type="hidden" name="booking_fee_final" id="bookingFeeFinal" value="">
            
            <div class="modal-body">
                <!-- Info Unit Terpilih -->
                <div class="unit-card" id="selectedUnitInfo">Memilih unit...</div>
                
                <!-- Pilih Customer -->
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Pilih Customer <span class="required">*</span></label>
                    <select name="lead_id" id="leadSelect" class="form-select" required>
                        <option value="">— Pilih Customer —</option>
                    </select>
                </div>
                
                <!-- Program Booking -->
              <div class="form-group">
    <label><i class="fas fa-tags"></i> Program Booking</label>
    <div id="programContainer" class="program-container">
        <p class="text-muted">Memuat program...</p>
    </div>
    <div class="selected-programs" id="selectedProgramsDisplay">
        <small>Klik program untuk memilih (hanya satu program)</small>
    </div>
</div>
                
                <!-- Total Booking Fee -->
                <div class="booking-fee-display" id="totalBookingFee">
                    Total Booking Fee: Rp 0
                </div>
                
                <!-- Metode Pembayaran -->
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Metode Pembayaran <span class="required">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" name="metode_pembayaran" id="metodeTransfer" value="transfer" checked onchange="togglePaymentMethod()">
                            <label for="metodeTransfer"><i class="fas fa-exchange-alt"></i> Transfer</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="metode_pembayaran" id="metodeCash" value="cash" onchange="togglePaymentMethod()">
                            <label for="metodeCash"><i class="fas fa-money-bill-wave"></i> Tunai</label>
                        </div>
                    </div>
                </div>
                
                <!-- Upload Area - SATU AREA UNTUK SEMUA METODE -->
                <div class="form-group">
                    <label><i class="fas fa-camera"></i> Upload Bukti Pembayaran <span class="required">*</span></label>
                    <div class="upload-area" id="uploadArea" onclick="document.getElementById('buktiFile').click()">
                        <input type="file" name="bukti_transfer" id="buktiFile" accept="image/jpeg,image/png,application/pdf" style="display: none;" onchange="previewFile(this)">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Klik untuk upload foto bukti pembayaran</p>
                        <small style="color: var(--text-muted);">Format: JPG, PNG, PDF, maks 5MB</small>
                    </div>
                    <div class="upload-preview" id="uploadPreview">
                        <img src="" alt="Preview">
                        <button type="button" class="remove-file" onclick="removeFile()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- DETAIL TRANSFER -->
                <div id="transferDetail" class="transfer-detail show">
                    <h4 style="color: var(--primary); margin: 0 0 15px 0; font-size: 15px;">
                        <i class="fas fa-university"></i> Transfer ke Rekening Developer
                    </h4>
                    
                    <!-- Pilih Bank -->
                    <div class="form-group">
                        <label>Pilih Rekening Tujuan <span class="required">*</span></label>
                        <?php if (!empty($banks)): ?>
                            <?php foreach ($banks as $index => $bank): ?>
                            <label class="bank-card <?= $index === 0 ? 'selected' : '' ?>">
                                <input type="radio" name="bank_id" value="<?= $bank['id'] ?>" <?= $index === 0 ? 'checked' : '' ?> onchange="selectBank(this)">
                                <div class="bank-row">
                                    <span class="bank-label">Bank</span>
                                    <span class="bank-value"><?= htmlspecialchars($bank['nama_bank']) ?></span>
                                </div>
                                <div class="bank-row">
                                    <span class="bank-label">No. Rekening</span>
                                    <span class="bank-value"><?= htmlspecialchars($bank['nomor_rekening']) ?></span>
                                </div>
                                <div class="bank-row">
                                    <span class="bank-label">Atas Nama</span>
                                    <span class="bank-value"><?= htmlspecialchars($bank['atas_nama']) ?></span>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="info-card">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Belum ada rekening bank. Hubungi developer.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h4 style="color: var(--primary); margin: 20px 0 15px 0; font-size: 15px;">
                        <i class="fas fa-user"></i> Informasi Pengirim
                    </h4>
                    
                    <!-- Nama Pengirim -->
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nama Pengirim <span class="required">*</span></label>
                        <input type="text" name="nama_pengirim" id="namaPengirim" class="form-control" 
                               value="<?= htmlspecialchars($marketing_name) ?>" 
                               placeholder="Nama sesuai rekening pengirim" required>
                    </div>
                    
                    <!-- Bank Pengirim -->
                    <div class="form-group">
                        <label><i class="fas fa-university"></i> Bank Pengirim <span class="required">*</span></label>
                        <input type="text" name="bank_pengirim" id="bankPengirim" class="form-control" placeholder="Contoh: BCA, Mandiri, BRI" required>
                    </div>
                    
                    <!-- Nomor Rekening Pengirim -->
                    <div class="form-group">
                        <label><i class="fas fa-credit-card"></i> Nomor Rekening Pengirim <span class="required">*</span></label>
                        <input type="text" name="nomor_rekening_pengirim" id="nomorRekeningPengirim" class="form-control" 
                               placeholder="1234567890" inputmode="numeric" required>
                    </div>
                </div>

                <!-- DETAIL TUNAI -->
                <div id="cashDetail" class="cash-detail">
                    <h4 style="color: var(--primary); margin: 0 0 15px 0; font-size: 15px;">
                        <i class="fas fa-money-bill-wave"></i> Pembayaran Tunai
                    </h4>
                    
                    <!-- Nominal Booking Fee -->
                    <div class="form-group">
                        <label><i class="fas fa-money-bill-wave"></i> Nominal Pembayaran <span class="required">*</span></label>
                        <input type="text" name="nominal_cash" id="nominalCash" class="form-control rupiah-input" 
                               placeholder="Rp 0" inputmode="numeric" 
                               onkeyup="formatRupiahInput(this)" onblur="formatRupiahBlur(this)">
                        <small style="color: var(--text-muted);">Minimal sesuai total booking fee</small>
                    </div>
                    
                    <!-- Keterangan (opsional) -->
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Keterangan (opsional)</label>
                        <textarea name="keterangan_cash" class="form-control" rows="2" placeholder="Contoh: Pembayaran tunai di kantor..."></textarea>
                    </div>
                    
                    <!-- Informasi Upload -->
                    <div class="info-card" style="margin-top: 10px;">
                        <i class="fas fa-info-circle"></i>
                        <span>Upload bukti pembayaran (kwitansi/foto) di area upload di atas</span>
                    </div>
                </div>
                
                <!-- Catatan Tambahan -->
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> Catatan (opsional)</label>
                    <textarea name="catatan" id="bookingCatatan" class="form-control" rows="2" placeholder="Catatan tambahan untuk booking..."></textarea>
                </div>
                
                <!-- Info Penting -->
                <div class="info-card">
                    <i class="fas fa-info-circle"></i>
                    <span>Setelah dibooking, unit akan pending dan menunggu verifikasi finance.</span>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeBookingModal()">Batal</button>
                <button type="submit" class="btn-primary" id="submitBookingBtn">Konfirmasi Booking</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CANCEL BOOKING -->
<div class="modal" id="cancelModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-times-circle"></i> Batalkan Booking</h2>
            <button class="modal-close" onclick="closeCancelModal()">&times;</button>
        </div>
        <form id="cancelForm">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="lead_id" id="cancelLeadId">
            <input type="hidden" name="unit_id" id="cancelUnitId">
            <input type="hidden" name="booking_id" id="cancelBookingId">
            <input type="hidden" name="marketing_id" value="<?= $marketing_id ?>">
            
            <div class="modal-body">
                <p>Yakin ingin membatalkan booking untuk unit <strong id="cancelUnitDisplay"></strong>?</p>
                
                <div class="form-group">
                    <label>Alasan Pembatalan <span class="required">*</span></label>
                    <textarea name="alasan" id="cancelAlasan" class="form-control" rows="3" required placeholder="Contoh: Customer batal, data tidak valid..."></textarea>
                </div>
                
                <div class="info-card" style="background: #ffeeed; color: var(--danger);">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Unit akan kembali tersedia untuk marketing lain.</span>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeCancelModal()">Batal</button>
                <button type="submit" class="btn-danger">Ya, Batalkan Booking</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DETAIL BOOKING -->
<div class="modal" id="detailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-info-circle"></i> Detail Booking</h2>
            <button class="modal-close" onclick="closeDetailModal()">&times;</button>
        </div>
        <div class="modal-body" id="detailContent">
            <div style="text-align: center; padding: 20px;">
                <i class="fas fa-spinner fa-spin"></i> Memuat...
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeDetailModal()">Tutup</button>
        </div>
    </div>
</div>

<script>
// ===== GLOBAL VARIABLES =====
let currentUnits = [];
let currentBookings = [];
let currentUnitPage = 1;
let currentBookingPage = 1;
let totalUnitPages = 1;
let totalBookingPages = 1;
let totalAvailableUnits = <?= $total_available ?>;
let selectedPrograms = [];
let isInternalMarketing = <?= $is_internal ? 'true' : 'false' ?>;
let unitBookingFee = 0;

// ===== FUNGSI FORMAT RUPIAH =====
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

// ===== TOGGLE PAYMENT METHOD =====
function togglePaymentMethod() {
    console.log('Toggling payment method...');
    
    const metodeTransfer = document.getElementById('metodeTransfer');
    const metodeCash = document.getElementById('metodeCash');
    const transferDetail = document.getElementById('transferDetail');
    const cashDetail = document.getElementById('cashDetail');
    const uploadArea = document.getElementById('uploadArea');
    const uploadPreview = document.getElementById('uploadPreview');
    
    if (!metodeTransfer || !metodeCash || !transferDetail || !cashDetail) {
        console.error('❌ Element payment method tidak ditemukan');
        return;
    }
    
    if (metodeTransfer.checked) {
        console.log('✅ Mode: TRANSFER');
        transferDetail.classList.add('show');
        cashDetail.classList.remove('show');
        
        // Set required fields untuk TRANSFER
        const namaPengirim = document.getElementById('namaPengirim');
        const bankPengirim = document.getElementById('bankPengirim');
        const nomorRekening = document.getElementById('nomorRekeningPengirim');
        const buktiFile = document.getElementById('buktiFile');
        const nominalCash = document.getElementById('nominalCash');
        const bankRadios = document.querySelectorAll('input[name="bank_id"]');
        
        if (namaPengirim) namaPengirim.required = true;
        if (bankPengirim) bankPengirim.required = true;
        if (nomorRekening) nomorRekening.required = true;
        if (buktiFile) buktiFile.required = true;
        if (nominalCash) nominalCash.required = false;
        
        // Bank required
        bankRadios.forEach(radio => {
            radio.required = true;
        });
        
    } else if (metodeCash.checked) {
        console.log('✅ Mode: TUNAI');
        transferDetail.classList.remove('show');
        cashDetail.classList.add('show');
        
        // Set required fields untuk TUNAI
        const namaPengirim = document.getElementById('namaPengirim');
        const bankPengirim = document.getElementById('bankPengirim');
        const nomorRekening = document.getElementById('nomorRekeningPengirim');
        const buktiFile = document.getElementById('buktiFile');
        const nominalCash = document.getElementById('nominalCash');
        const bankRadios = document.querySelectorAll('input[name="bank_id"]');
        
        if (namaPengirim) namaPengirim.required = false;
        if (bankPengirim) bankPengirim.required = false;
        if (nomorRekening) nomorRekening.required = false;
        if (buktiFile) buktiFile.required = true; // TETAP REQUIRED untuk upload bukti tunai
        if (nominalCash) nominalCash.required = true;
        
        // Bank tidak required
        bankRadios.forEach(radio => {
            radio.required = false;
            radio.checked = false;
        });
        
        // Hapus selected class dari bank cards
        document.querySelectorAll('.bank-card').forEach(card => {
            card.classList.remove('selected');
        });
    }
}

// ===== SELECT BANK =====
function selectBank(radio) {
    document.querySelectorAll('.bank-card').forEach(card => {
        card.classList.remove('selected');
    });
    radio.closest('.bank-card').classList.add('selected');
}

// ===== FILE UPLOAD PREVIEW =====
function previewFile(input) {
    const preview = document.getElementById('uploadPreview');
    const uploadArea = document.getElementById('uploadArea');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileSize = file.size / 1024 / 1024; // MB
        
        if (fileSize > 5) {
            alert('File terlalu besar. Maksimal 5MB.');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const img = preview.querySelector('img');
            img.src = e.target.result;
            preview.style.display = 'block';
            uploadArea.style.display = 'none';
        };
        
        reader.readAsDataURL(file);
    }
}

function removeFile() {
    const fileInput = document.getElementById('buktiFile');
    const preview = document.getElementById('uploadPreview');
    const uploadArea = document.getElementById('uploadArea');
    
    fileInput.value = '';
    preview.style.display = 'none';
    uploadArea.style.display = 'block';
}

// ===== LOAD STATS =====
function loadStats() {
    fetch('api/booking_process.php?action=stats')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const stats = data.data;
                document.getElementById('statsContainer').innerHTML = `
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-history"></i></div>
                        <div class="stat-label">Total Booking</div>
                        <div class="stat-value">${stats.my_bookings.total}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-label">Pending</div>
                        <div class="stat-value">${stats.my_bookings.pending}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--success);"></i></div>
                        <div class="stat-label">Diterima</div>
                        <div class="stat-value">${stats.my_bookings.diterima}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-home"></i></div>
                        <div class="stat-label">Unit Tersedia</div>
                        <div class="stat-value">${totalAvailableUnits}</div>
                    </div>
                `;
            }
        })
        .catch(err => console.error('Error loading stats:', err));
}

// ===== LOAD AVAILABLE UNITS =====
function loadAvailableUnits(page = 1) {
    currentUnitPage = page;
    
    const clusterId = document.getElementById('clusterFilter')?.value || '';
    const program = document.getElementById('programFilter')?.value || '';
    const search = document.getElementById('searchFilter')?.value || '';
    
    let url = 'api/get_available_units.php?page=' + page + '&limit=20';
    if (clusterId) url += '&cluster_id=' + clusterId;
    if (program) url += '&program=' + encodeURIComponent(program);
    if (search) url += '&search=' + encodeURIComponent(search);
    
    document.getElementById('unitsTableBody').innerHTML = `
        <tr>
            <td colspan="8" style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin"></i> Memuat data...
            </td>
        </tr>
    `;
    
    fetch(url)
        .then(res => {
            if (!res.ok) throw new Error('HTTP error ' + res.status);
            return res.json();
        })
        .then(data => {
            if (data.success) {
                currentUnits = data.flat_data || [];
                
                if (currentUnits.length === 0) {
                    if (data.total_all_available > 0) {
                        document.getElementById('unitsTableBody').innerHTML = `
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-info-circle"></i> Tidak ada unit dengan filter ini. 
                                    Total unit tersedia: ${data.total_all_available} unit.
                                    <br><button class="filter-btn reset" onclick="resetFilters()" style="margin-top: 10px;">Reset Filter</button>
                                </td>
                            </tr>
                        `;
                    } else {
                        document.getElementById('unitsTableBody').innerHTML = `
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-home"></i> Belum ada unit tersedia.
                                </td>
                            </tr>
                        `;
                    }
                } else {
                    renderUnitsTable(currentUnits);
                }
                
                document.getElementById('unitCountBadge').textContent = `Menampilkan ${currentUnits.length} dari ${data.pagination?.total_records || 0} unit`;
                
                if (data.pagination) {
                    renderUnitPagination(data.pagination);
                }
            } else {
                document.getElementById('unitsTableBody').innerHTML = `
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-circle"></i> Gagal memuat data: ${data.message}
                        </td>
                    </tr>
                `;
            }
        })
        .catch(err => {
            console.error('Error:', err);
            document.getElementById('unitsTableBody').innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <i class="fas fa-exclamation-triangle"></i> Error: ${err.message}
                    </td>
                </tr>
            `;
        });
}

function renderUnitsTable(units) {
    if (!units || units.length === 0) {
        document.getElementById('unitsTableBody').innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px;">
                    <i class="fas fa-home"></i> Tidak ada unit tersedia
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    units.forEach(u => {
        html += `
            <tr data-unit-id="${u.id}">
                <td>${u.nama_cluster || '-'}</td>
                <td>Block ${u.nama_block || '-'}</td>
                <td><strong>${u.nomor_unit}</strong></td>
                <td>${u.tipe_unit}</td>
                <td><span class="program-badge ${u.program}">${u.program}</span></td>
                <td>${u.harga_formatted || 'Rp 0'}</td>
                <td>${u.harga_booking_formatted || 'Gratis'}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn book" onclick="openBookingModal(${u.id})" title="Booking Unit">
                            <i class="fas fa-hand-holding-usd"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    document.getElementById('unitsTableBody').innerHTML = html;
}

function renderUnitPagination(pagination) {
    if (pagination.total_pages <= 1) {
        document.getElementById('unitPagination').innerHTML = '';
        return;
    }
    
    let html = '';
    const current = pagination.current_page;
    const total = pagination.total_pages;
    
    if (current > 1) {
        html += `<button class="pagination-btn" onclick="loadAvailableUnits(${current - 1})"><i class="fas fa-chevron-left"></i></button>`;
    }
    
    const start = Math.max(1, current - 2);
    const end = Math.min(total, current + 2);
    
    for (let i = start; i <= end; i++) {
        html += `<button class="pagination-btn ${i === current ? 'active' : ''}" onclick="loadAvailableUnits(${i})">${i}</button>`;
    }
    
    if (current < total) {
        html += `<button class="pagination-btn" onclick="loadAvailableUnits(${current + 1})"><i class="fas fa-chevron-right"></i></button>`;
    }
    
    document.getElementById('unitPagination').innerHTML = html;
}

// ===== LOAD MY BOOKINGS =====
function loadMyBookings(page = 1) {
    currentBookingPage = page;
    
    const status = document.getElementById('bookingStatusFilter')?.value || '';
    
    let url = 'api/booking_process.php?action=my_bookings&page=' + page;
    if (status) url += '&status=' + encodeURIComponent(status);
    
    document.getElementById('myBookingsBody').innerHTML = `
        <tr>
            <td colspan="6" style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin"></i> Memuat data...
            </td>
        </tr>
    `;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentBookings = data.data || [];
                renderMyBookings(currentBookings);
                document.getElementById('bookingCountBadge').textContent = `Total: ${data.pagination.total_records}`;
                
                if (data.pagination) {
                    totalBookingPages = data.pagination.total_pages;
                    renderBookingPagination(data.pagination);
                }
            } else {
                document.getElementById('myBookingsBody').innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-circle"></i> Gagal memuat
                        </td>
                    </tr>
                `;
            }
        })
        .catch(err => {
            console.error('Error:', err);
            document.getElementById('myBookingsBody').innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        <i class="fas fa-exclamation-triangle"></i> Error
                    </td>
                </tr>
            `;
        });
}

function renderMyBookings(bookings) {
    if (!bookings || bookings.length === 0) {
        document.getElementById('myBookingsBody').innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px;">
                    <i class="fas fa-inbox"></i> Belum ada booking
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    bookings.forEach(b => {
        const statusClass = b.status_verifikasi === 'diterima' ? 'success' : 
                           (b.status_verifikasi === 'ditolak' ? 'danger' : 'warning');
        const statusText = b.status_verifikasi === 'diterima' ? 'Diterima' :
                          (b.status_verifikasi === 'ditolak' ? 'Ditolak' : 'Pending');
        
        html += `
            <tr>
                <td>#${b.booking_id}</td>
                <td>${b.full_name || '-'}<br><small>${b.phone || ''}</small></td>
                <td>${b.unit_display || '-'}</td>
                <td>${b.date_formatted || '-'}</td>
                <td>
                    <span class="status-badge" style="background: ${statusClass === 'success' ? '#2A9D8F' : (statusClass === 'danger' ? '#D64F3C' : '#E9C46A')}; color: white;">
                        ${statusText}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn view" onclick="viewBookingDetail(${b.booking_id})" title="Detail">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${b.status_verifikasi === 'pending' ? `
                            <button class="action-btn cancel" onclick="openCancelModal(${b.lead_id}, ${b.unit_id}, '${b.nomor_unit || ''}', ${b.booking_id})" title="Batalkan">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });
    
    document.getElementById('myBookingsBody').innerHTML = html;
}

function renderBookingPagination(pagination) {
    if (pagination.total_pages <= 1) {
        document.getElementById('bookingPagination').innerHTML = '';
        return;
    }
    
    let html = '';
    const current = pagination.current_page;
    const total = pagination.total_pages;
    
    if (current > 1) {
        html += `<button class="pagination-btn" onclick="loadMyBookings(${current - 1})"><i class="fas fa-chevron-left"></i></button>`;
    }
    
    const start = Math.max(1, current - 2);
    const end = Math.min(total, current + 2);
    
    for (let i = start; i <= end; i++) {
        html += `<button class="pagination-btn ${i === current ? 'active' : ''}" onclick="loadMyBookings(${i})">${i}</button>`;
    }
    
    if (current < total) {
        html += `<button class="pagination-btn" onclick="loadMyBookings(${current + 1})"><i class="fas fa-chevron-right"></i></button>`;
    }
    
    document.getElementById('bookingPagination').innerHTML = html;
}

// ===== LOAD LEADS FOR DROPDOWN =====
function loadLeadsForBooking() {
    fetch('api/booking_process.php?action=available_leads')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                let options = '<option value="">— Pilih Customer —</option>';
                data.data.forEach(l => {
                    options += `<option value="${l.id}">${l.full_name} - ${l.phone} (${l.status})</option>`;
                });
                document.getElementById('leadSelect').innerHTML = options;
            } else {
                document.getElementById('leadSelect').innerHTML = '<option value="">— Tidak ada lead tersedia —</option>';
            }
        })
        .catch(err => {
            console.error('Error loading leads:', err);
            document.getElementById('leadSelect').innerHTML = '<option value="">— Error memuat data —</option>';
        });
}

// ===== LOAD PROGRAMS FOR UNIT =====
function loadProgramsForUnit(unitId) {
    console.log('Loading programs for unit:', unitId);
    
    const programContainer = document.getElementById('programContainer');
    const selectedDisplay = document.getElementById('selectedProgramsDisplay');
    
    if (!programContainer) {
        console.error('❌ programContainer tidak ditemukan');
        return;
    }
    
    // Reset selected programs
    selectedPrograms = [];
    updateTotalBookingFee();
    
    programContainer.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Memuat program...</p>';
    
    fetch('api/get_unit_programs.php?unit_id=' + unitId)
        .then(res => {
            if (!res.ok) throw new Error('HTTP error ' + res.status);
            return res.json();
        })
        .then(data => {
            console.log('Programs response:', data);
            
            if (data.success && data.programs && data.programs.length > 0) {
                let html = '<div class="program-list">';
                data.programs.forEach(p => {
                    const allinClass = p.is_all_in ? 'all-in' : '';
                    html += `
                        <span class="program-chip ${allinClass}" 
                              data-id="${p.id}" 
                              data-nama="${p.nama_program}"
                              data-fee="${p.booking_fee}"
                              onclick="toggleProgram(this)">
                            <i class="fas fa-tag"></i> ${p.nama_program}
                            (Rp ${new Intl.NumberFormat('id-ID').format(p.booking_fee)})
                            ${p.is_all_in ? '<span class="allin-badge">ALL-IN</span>' : ''}
                        </span>
                    `;
                });
                html += '</div>';
                programContainer.innerHTML = html;
                
                if (selectedDisplay) {
                    selectedDisplay.innerHTML = '<small>Klik program untuk memilih</small>';
                }
            } else {
                programContainer.innerHTML = '<p class="text-muted">Tidak ada program booking tersedia</p>';
            }
        })
        .catch(err => {
            console.error('Error loading programs:', err);
            programContainer.innerHTML = '<p class="text-muted">Error memuat program: ' + err.message + '</p>';
        });
}

// ===== TOGGLE PROGRAM - SINGLE SELECT ONLY =====
function toggleProgram(element) {
    console.log('Toggling program:', element);
    
    if (!element) {
        console.error('❌ Element tidak ditemukan');
        return;
    }
    
    const programId = element.dataset.id;
    const programNama = element.dataset.nama;
    const programFee = parseInt(element.dataset.fee);
    
    if (!programId || !programNama || !programFee) {
        console.error('❌ Data program tidak lengkap', element.dataset);
        return;
    }
    
    console.log('Program ID:', programId, 'Nama:', programNama, 'Fee:', programFee);
    
    // CEK APAKAH PROGRAM INI SEDANG DIPILIH
    const isSelected = element.classList.contains('selected');
    
    // HAPUS SEMUA PROGRAM YANG DIPILIH
    document.querySelectorAll('.program-chip.selected').forEach(chip => {
        chip.classList.remove('selected');
    });
    
    // RESET selectedPrograms array
    selectedPrograms = [];
    
    // JIKA SEBELUMNYA TIDAK DIPILIH, PILIH PROGRAM INI
    if (!isSelected) {
        // Tambah program
        selectedPrograms.push({
            id: programId,
            nama: programNama,
            fee: programFee
        });
        element.classList.add('selected');
        console.log('✅ Program dipilih:', programNama);
    } else {
        // Jika sebelumnya dipilih, sekarang tidak dipilih (sudah di-reset)
        console.log('✅ Program dibatalkan');
    }
    
    console.log('Selected programs:', selectedPrograms);
    
    // Update hidden input dengan format CSV
    const selectedProgramsInput = document.getElementById('selectedPrograms');
    if (selectedProgramsInput) {
        selectedProgramsInput.value = selectedPrograms.map(p => p.id).join(',');
        console.log('Selected programs value:', selectedProgramsInput.value);
    }
    
    // Update display selected programs
    const selectedDisplay = document.getElementById('selectedProgramsDisplay');
    if (selectedDisplay) {
        if (selectedPrograms.length > 0) {
            let html = '<small>Program dipilih:</small><br>';
            selectedPrograms.forEach(p => {
                html += `<span class="program-chip selected" style="margin: 2px; cursor: default;" data-id="${p.id}">${p.nama}</span> `;
            });
            selectedDisplay.innerHTML = html;
        } else {
            selectedDisplay.innerHTML = '<small>Klik program untuk memilih</small>';
        }
    }
    
    // Update total booking fee
    updateTotalBookingFee();
}

function updateTotalBookingFee() {
    const totalFee = selectedPrograms.reduce((sum, p) => sum + p.fee, 0);
    const totalFeeDisplay = document.getElementById('totalBookingFee');
    const bookingFeeFinal = document.getElementById('bookingFeeFinal');
    
    if (totalFeeDisplay) {
        if (totalFee > 0) {
            totalFeeDisplay.innerHTML = `Booking Fee: Rp ${new Intl.NumberFormat('id-ID').format(totalFee)}`;
            if (bookingFeeFinal) bookingFeeFinal.value = totalFee;
        } else {
            totalFeeDisplay.innerHTML = `Booking Fee: Rp ${new Intl.NumberFormat('id-ID').format(unitBookingFee)} (Default)`;
            if (bookingFeeFinal) bookingFeeFinal.value = unitBookingFee || 0;
        }
    }
}

// ===== OPEN BOOKING MODAL =====
function openBookingModal(unitId) {
    console.log('🔍 openBookingModal called with unitId:', unitId);
    
    // Validasi element sebelum digunakan
    const bookingUnitIdEl = document.getElementById('bookingUnitId');
    if (!bookingUnitIdEl) {
        console.error('❌ Element bookingUnitId tidak ditemukan!');
        alert('Terjadi kesalahan: Form booking tidak lengkap. Refresh halaman.');
        return;
    }
    
    const unit = currentUnits.find(u => u.id == unitId);
    
    if (!unit) {
        console.error('❌ Unit tidak ditemukan di currentUnits');
        alert('Unit tidak ditemukan. Silakan refresh halaman.');
        return;
    }
    
    console.log('✅ Unit ditemukan:', unit);
    
    bookingUnitIdEl.value = unitId;
    unitBookingFee = unit.harga_booking || 0;
    
    // Load programs untuk unit ini
    loadProgramsForUnit(unitId);
    
    // Format harga
    const hargaFormatted = unit.harga_formatted || 
        (unit.harga ? 'Rp ' + new Intl.NumberFormat('id-ID').format(unit.harga) : 'Rp 0');
    
    const bookingFeeFormatted = unit.harga_booking_formatted || 
        (unit.harga_booking ? 'Rp ' + new Intl.NumberFormat('id-ID').format(unit.harga_booking) : 'Gratis');
    
    // Tampilkan info unit - TANPA KOMISI UNTUK INTERNAL
    let komisiHtml = '';
    if (!isInternalMarketing) {
        if (unit.komisi_eksternal_rupiah && unit.komisi_eksternal_rupiah > 0) {
            komisiHtml = `
                <div class="unit-row">
                    <span class="unit-label">Komisi Anda</span>
                    <span class="unit-value komisi">Rp ${Number(unit.komisi_eksternal_rupiah).toLocaleString('id-ID')}</span>
                </div>
            `;
        } else {
            const komisiPersen = unit.komisi_eksternal_persen || 3.00;
            komisiHtml = `
                <div class="unit-row">
                    <span class="unit-label">Komisi Anda</span>
                    <span class="unit-value komisi">${komisiPersen}% dari harga</span>
                </div>
            `;
        }
    }
    
    const selectedUnitInfo = document.getElementById('selectedUnitInfo');
    if (selectedUnitInfo) {
        selectedUnitInfo.innerHTML = `
            <div class="unit-row">
                <span class="unit-label">Unit</span>
                <span class="unit-value">${unit.nama_cluster || '-'} - Block ${unit.nama_block || '-'} - ${unit.nomor_unit}</span>
            </div>
            <div class="unit-row">
                <span class="unit-label">Tipe</span>
                <span class="unit-value">${unit.tipe_unit} (${unit.program || 'Subsidi'})</span>
            </div>
            <div class="unit-row">
                <span class="unit-label">Harga</span>
                <span class="unit-value">${hargaFormatted}</span>
            </div>
            <div class="unit-row">
                <span class="unit-label">Booking Fee Dasar</span>
                <span class="unit-value">${bookingFeeFormatted}</span>
            </div>
            ${komisiHtml}
        `;
    }
    
    // Reset form
    const namaPengirimEl = document.getElementById('namaPengirim');
    if (namaPengirimEl) namaPengirimEl.value = '<?= htmlspecialchars($marketing_name) ?>';
    
    const bankPengirimEl = document.getElementById('bankPengirim');
    if (bankPengirimEl) bankPengirimEl.value = '';
    
    const nomorRekeningEl = document.getElementById('nomorRekeningPengirim');
    if (nomorRekeningEl) nomorRekeningEl.value = '';
    
    const buktiFileEl = document.getElementById('buktiFile');
    if (buktiFileEl) buktiFileEl.value = '';
    
    const nominalCashEl = document.getElementById('nominalCash');
    if (nominalCashEl) nominalCashEl.value = '';
    
    const bookingCatatanEl = document.getElementById('bookingCatatan');
    if (bookingCatatanEl) bookingCatatanEl.value = '';
    
    const uploadPreview = document.getElementById('uploadPreview');
    const uploadArea = document.getElementById('uploadArea');
    
    if (uploadPreview) uploadPreview.style.display = 'none';
    if (uploadArea) uploadArea.style.display = 'block';
    
    // Reset program selection
    selectedPrograms = [];
    const selectedProgramsEl = document.getElementById('selectedPrograms');
    if (selectedProgramsEl) selectedProgramsEl.value = '';
    
    const selectedProgramsDisplay = document.getElementById('selectedProgramsDisplay');
    if (selectedProgramsDisplay) {
        selectedProgramsDisplay.innerHTML = '<small>Klik program untuk memilih (bisa pilih lebih dari satu)</small>';
    }
    
    updateTotalBookingFee();
    
    // Reset bank selection
    const bankCards = document.querySelectorAll('.bank-card');
    if (bankCards.length > 0) {
        bankCards.forEach((card, index) => {
            if (index === 0) {
                card.classList.add('selected');
                const radio = card.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            } else {
                card.classList.remove('selected');
                const radio = card.querySelector('input[type="radio"]');
                if (radio) radio.checked = false;
            }
        });
    }
    
    togglePaymentMethod();
    loadLeadsForBooking();
    
    const modal = document.getElementById('bookingModal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeBookingModal() {
    document.getElementById('bookingModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SUBMIT BOOKING =====
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Validasi program (opsional)
    const programIds = document.getElementById('selectedPrograms').value;
    if (programIds) {
        formData.append('program_ids', programIds);
    }
    
    // Validasi customer
    const leadId = formData.get('lead_id');
    if (!leadId) {
        alert('Pilih customer terlebih dahulu');
        return;
    }
    
    const metode = formData.get('metode_pembayaran');
    
    // Validasi berdasarkan metode
    if (metode === 'transfer') {
        const bankId = formData.get('bank_id');
        const namaPengirim = formData.get('nama_pengirim');
        const bankPengirim = formData.get('bank_pengirim');
        const nomorRekening = formData.get('nomor_rekening_pengirim');
        const bukti = document.getElementById('buktiFile').files[0];
        
        if (!bankId) {
            alert('Pilih rekening tujuan');
            return;
        }
        
        if (!namaPengirim) {
            alert('Nama pengirim wajib diisi');
            return;
        }
        
        if (!bankPengirim) {
            alert('Bank pengirim wajib diisi');
            return;
        }
        
        if (!nomorRekening) {
            alert('Nomor rekening pengirim wajib diisi');
            return;
        }
        
        if (!bukti) {
            alert('Upload bukti transfer');
            return;
        }
    } else {
        const nominal = parseRupiah(document.getElementById('nominalCash').value);
        const totalFee = parseFloat(document.getElementById('bookingFeeFinal').value) || unitBookingFee;
        const bukti = document.getElementById('buktiFile').files[0];
        
        if (nominal <= 0) {
            alert('Nominal pembayaran wajib diisi');
            return;
        }
        
        if (nominal < totalFee) {
            alert('Nominal pembayaran minimal Rp ' + new Intl.NumberFormat('id-ID').format(totalFee));
            return;
        }
        
        if (!bukti) {
            alert('Upload bukti pembayaran (kwitansi/foto)');
            return;
        }
        
        formData.append('nominal_cash_value', nominal);
    }
    
    // Tambah booking fee final
    const bookingFeeFinal = document.getElementById('bookingFeeFinal').value;
    if (bookingFeeFinal) {
        formData.append('booking_fee_final', bookingFeeFinal);
    }
    
    const submitBtn = document.getElementById('submitBookingBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
    submitBtn.disabled = true;
    
    fetch('api/booking_process.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ Unit berhasil dibooking! Menunggu verifikasi finance.');
            closeBookingModal();
            loadAvailableUnits(currentUnitPage);
            loadMyBookings(currentBookingPage);
            loadStats();
            
            totalAvailableUnits--;
            document.querySelector('#tabAvailableBtn').innerHTML = `<i class="fas fa-home"></i> Unit Tersedia (${totalAvailableUnits})`;
        } else {
            alert('❌ Gagal: ' + data.message);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('❌ Terjadi kesalahan: ' + err.message);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// ===== CANCEL BOOKING =====
function openCancelModal(leadId, unitId, unitNomor, bookingId) {
    document.getElementById('cancelLeadId').value = leadId || '';
    document.getElementById('cancelUnitId').value = unitId || '';
    document.getElementById('cancelBookingId').value = bookingId || '';
    document.getElementById('cancelUnitDisplay').textContent = unitNomor || 'Unit';
    document.getElementById('cancelModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('cancelForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const alasan = formData.get('alasan');
    
    if (!alasan) {
        alert('Alasan pembatalan wajib diisi');
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Membatalkan...';
    submitBtn.disabled = true;
    
    fetch('api/booking_process.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ Booking dibatalkan');
            closeCancelModal();
            loadAvailableUnits(currentUnitPage);
            loadMyBookings(currentBookingPage);
            loadStats();
            
            totalAvailableUnits++;
            document.querySelector('#tabAvailableBtn').innerHTML = `<i class="fas fa-home"></i> Unit Tersedia (${totalAvailableUnits})`;
        } else {
            alert('❌ Gagal: ' + data.message);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('❌ Terjadi kesalahan: ' + err.message);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// ===== VIEW DETAIL =====
function viewBookingDetail(bookingId) {
    document.getElementById('detailContent').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>';
    document.getElementById('detailModal').classList.add('show');
    
    fetch(`api/booking_process.php?action=detail&booking_id=${bookingId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const b = data.data;
                
                let komisiInfo = '';
                if (b.komisi_eksternal_formatted) {
                    komisiInfo = `
                        <div class="unit-row">
                            <span class="unit-label">Komisi Eksternal</span>
                            <span class="unit-value">${b.komisi_eksternal_formatted}</span>
                        </div>
                        <div class="unit-row">
                            <span class="unit-label">Komisi Internal</span>
                            <span class="unit-value">${b.komisi_internal_formatted}</span>
                        </div>
                    `;
                }
                
                let buktiHtml = '';
                if (b.bukti_pembayaran) {
                    buktiHtml = `
                        <div class="unit-row">
                            <span class="unit-label">Bukti Transfer</span>
                            <span class="unit-value">
                                <a href="${b.bukti_pembayaran}" target="_blank" class="btn-primary" style="padding: 8px 16px; font-size: 12px;">
                                    <i class="fas fa-eye"></i> Lihat Bukti
                                </a>
                            </span>
                        </div>
                    `;
                }
                
                let pengirimHtml = '';
                if (b.nama_pengirim) {
                    pengirimHtml = `
                        <div class="unit-row">
                            <span class="unit-label">Pengirim</span>
                            <span class="unit-value">${b.nama_pengirim} (${b.bank_pengirim} - ${b.nomor_rekening_pengirim})</span>
                        </div>
                    `;
                }
                
                document.getElementById('detailContent').innerHTML = `
                    <div class="unit-card">
                        <div class="unit-row"><span class="unit-label">ID Booking:</span><span class="unit-value">#${b.booking_id}</span></div>
                        <div class="unit-row"><span class="unit-label">Customer:</span><span class="unit-value">${b.full_name}</span></div>
                        <div class="unit-row"><span class="unit-label">WhatsApp:</span><span class="unit-value"><a href="https://wa.me/${b.phone}" target="_blank">${b.phone}</a></span></div>
                        <div class="unit-row"><span class="unit-label">Unit:</span><span class="unit-value">${b.nama_cluster} - Block ${b.nama_block} - ${b.nomor_unit}</span></div>
                        <div class="unit-row"><span class="unit-label">Tipe:</span><span class="unit-value">${b.tipe_unit} (${b.unit_program})</span></div>
                        <div class="unit-row"><span class="unit-label">Harga:</span><span class="unit-value">${b.harga_formatted}</span></div>
                        <div class="unit-row"><span class="unit-label">Booking Fee:</span><span class="unit-value">${b.harga_booking_formatted}</span></div>
                        ${komisiInfo} ${pengirimHtml} ${buktiHtml}
                        <div class="unit-row"><span class="unit-label">Metode:</span><span class="unit-value">${b.metode_pembayaran}</span></div>
                        <div class="unit-row"><span class="unit-label">Tanggal:</span><span class="unit-value">${b.date_formatted}</span></div>
                        <div class="unit-row"><span class="unit-label">Status:</span>
                            <span class="unit-value"><span class="status-badge" style="background: ${b.status_class === 'success' ? '#2A9D8F' : (b.status_class === 'danger' ? '#D64F3C' : '#E9C46A')}; color: white;">${b.status_text}</span></span>
                        </div>
                        ${b.catatan_verifikasi ? `<div class="unit-row"><span class="unit-label">Catatan:</span><span class="unit-value">${b.catatan_verifikasi}</span></div>` : ''}
                    </div>
                `;
            } else {
                document.getElementById('detailContent').innerHTML = '<div style="text-align: center; color: var(--danger);">Gagal memuat detail</div>';
            }
        })
        .catch(err => {
            console.error('Error:', err);
            document.getElementById('detailContent').innerHTML = '<div style="text-align: center; color: var(--danger);">Terjadi kesalahan</div>';
        });
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== TAB SWITCH =====
function showTab(tab) {
    const availableSection = document.getElementById('availableSection');
    const bookingsSection = document.getElementById('myBookingsSection');
    const availableFilter = document.getElementById('availableFilter');
    const bookingFilter = document.getElementById('bookingFilter');
    const tabAvailable = document.getElementById('tabAvailableBtn');
    const tabBookings = document.getElementById('tabBookingsBtn');
    
    if (tab === 'available') {
        availableSection.style.display = 'block';
        bookingsSection.style.display = 'none';
        availableFilter.style.display = 'block';
        bookingFilter.style.display = 'none';
        
        tabAvailable.classList.add('active');
        tabBookings.classList.remove('active');
        
        loadAvailableUnits(1);
    } else {
        availableSection.style.display = 'none';
        bookingsSection.style.display = 'block';
        availableFilter.style.display = 'none';
        bookingFilter.style.display = 'block';
        
        tabBookings.classList.add('active');
        tabAvailable.classList.remove('active');
        
        loadMyBookings(1);
    }
}

function resetFilters() {
    document.getElementById('clusterFilter').value = '';
    document.getElementById('programFilter').value = '';
    document.getElementById('searchFilter').value = '';
    loadAvailableUnits(1);
}

function resetBookingFilter() {
    document.getElementById('bookingStatusFilter').value = '';
    loadMyBookings(1);
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
    
    loadStats();
    loadAvailableUnits(1);
    showTab('available');
    
    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(modal => {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            });
        }
    });
});

// Expose functions to global scope
window.openBookingModal = openBookingModal;
window.closeBookingModal = closeBookingModal;
window.openCancelModal = openCancelModal;
window.closeCancelModal = closeCancelModal;
window.viewBookingDetail = viewBookingDetail;
window.closeDetailModal = closeDetailModal;
window.showTab = showTab;
window.resetFilters = resetFilters;
window.resetBookingFilter = resetBookingFilter;
window.loadAvailableUnits = loadAvailableUnits;
window.loadMyBookings = loadMyBookings;
window.togglePaymentMethod = togglePaymentMethod;
window.selectBank = selectBank;
window.previewFile = previewFile;
window.removeFile = removeFile;
window.toggleProgram = toggleProgram;
window.formatRupiahInput = formatRupiahInput;
window.formatRupiahBlur = formatRupiahBlur;

console.log('✅ Marketing Booking v8.0 loaded - Internal:', isInternalMarketing);
</script>

<?php include 'includes/footer.php'; ?>