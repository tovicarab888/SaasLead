<?php
/**
 * EXPORT_MODAL.PHP - LEADENGINE
 * Version: 8.0.0 - FIXED: Developer location access & Close button
 * FULL CODE - 100% LENGKAP TANPA POTONGAN
 */

// Aktifkan session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';

// ========== CEK SESSION LOGIN ==========
$is_authenticated = false;
$current_role = 'guest';
$user_name = 'Guest';
$user_id = 0;
$marketing_id = 0;
$developer_id = 0;
$location_access = '';

// Cek marketing
if (isset($_SESSION['marketing_id']) && $_SESSION['marketing_id'] > 0) {
    $is_authenticated = true;
    $current_role = 'marketing';
    $user_name = $_SESSION['marketing_name'] ?? 'Marketing';
    $marketing_id = $_SESSION['marketing_id'];
    $developer_id = $_SESSION['marketing_developer_id'] ?? 0;
} 
// Cek user biasa
elseif (checkAuth()) {
    $is_authenticated = true;
    $current_role = $_SESSION['role'] ?? 'user';
    $user_name = $_SESSION['nama_lengkap'] ?? 'User';
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if ($current_role === 'developer') {
        $developer_id = $user_id;
        $location_access = $_SESSION['location_access'] ?? '';
    }
}

if (!$is_authenticated) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== AMBIL DATA UNTUK DROPDOWN ==========
$locations = $conn->query("SELECT * FROM locations ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);

$status_list = [
    'Baru',
    'Follow Up',
    'Survey',
    'Booking',
    'Tolak Slik',
    'Tidak Minat',
    'Batal',
    'Deal KPR',
    'Deal Tunai',
    'Deal Bertahap 6 Bulan',
    'Deal Bertahap 1 Tahun'
];

// ========== AMBIL LOKASI UNTUK DEVELOPER ==========
$developer_locations = [];
if ($current_role === 'developer' && !empty($location_access)) {
    $loc_keys = explode(',', $location_access);
    $loc_keys = array_map('trim', $loc_keys);
    $loc_keys = array_filter($loc_keys);
    
    if (!empty($loc_keys)) {
        $placeholders = implode(',', array_fill(0, count($loc_keys), '?'));
        $stmt = $conn->prepare("SELECT * FROM locations WHERE location_key IN ($placeholders) ORDER BY sort_order");
        $stmt->execute($loc_keys);
        $developer_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ========== HITUNG STATISTIK AWAL ==========
try {
    // Base query
    $base_sql = "SELECT COUNT(*) FROM leads WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
    $params = [];
    
    // Filter berdasarkan role
    if ($current_role === 'marketing' && $marketing_id > 0) {
        $base_sql .= " AND assigned_marketing_team_id = ?";
        $params[] = $marketing_id;
    } elseif ($current_role === 'developer' && !empty($location_access)) {
        $loc_keys = explode(',', $location_access);
        $loc_keys = array_map('trim', $loc_keys);
        $loc_keys = array_filter($loc_keys);
        
        if (!empty($loc_keys)) {
            $placeholders = implode(',', array_fill(0, count($loc_keys), '?'));
            $base_sql .= " AND location_key IN ($placeholders)";
            $params = array_merge($params, $loc_keys);
        }
    }
    
    // Total
    $stmt = $conn->prepare($base_sql);
    $stmt->execute($params);
    $initial_total = (int)$stmt->fetchColumn();
    
    // Hari ini
    $today_sql = $base_sql . " AND DATE(created_at) = CURDATE()";
    $stmt = $conn->prepare($today_sql);
    $stmt->execute($params);
    $initial_today = (int)$stmt->fetchColumn();
    
    // Minggu ini
    $week_sql = $base_sql . " AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
    $stmt = $conn->prepare($week_sql);
    $stmt->execute($params);
    $initial_week = (int)$stmt->fetchColumn();
    
    // Bulan ini
    $month_sql = $base_sql . " AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
    $stmt = $conn->prepare($month_sql);
    $stmt->execute($params);
    $initial_month = (int)$stmt->fetchColumn();
    
} catch (Exception $e) {
    $initial_total = 0;
    $initial_today = 0;
    $initial_week = 0;
    $initial_month = 0;
}

$role_display = ucfirst($current_role);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Export Data Premium - LeadEngine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #1B4A3C;
            --primary-light: #2A5F4E;
            --primary-soft: #E7F3EF;
            --secondary: #D64F3C;
            --secondary-light: #FF6B4A;
            --bg: #F5F3F0;
            --surface: #FFFFFF;
            --text: #1A2A24;
            --text-light: #4A5A54;
            --text-muted: #7A8A84;
            --border: #E0DAD3;
            --success: #2A9D8F;
            --warning: #E9C46A;
            --danger: #D64F3C;
            --info: #4A90E2;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 20px;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .export-container {
            background: var(--surface);
            border-radius: 32px;
            width: 100%;
            max-width: 1200px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
            animation: fadeIn 0.5s ease;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .export-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 25px 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .export-header i {
            font-size: 36px;
            color: #E3B584;
        }
        
        .export-header h1 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 5px;
        }
        
        .export-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .role-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            margin-left: 15px;
        }
        
        .export-close {
            margin-left: auto;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 14px;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .export-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        
        .export-close i {
            font-size: 20px;
            color: white;
        }
        
        .export-body {
            padding: 30px;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        
        .filter-section {
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 25px;
        }
        
        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .section-title i {
            color: var(--secondary);
            font-size: 20px;
        }
        
        .format-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .format-btn {
            padding: 16px 10px;
            border: 2px solid var(--border);
            border-radius: 16px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .format-btn.active {
            border-color: var(--secondary);
            background: linear-gradient(135deg, rgba(214, 79, 60, 0.05), rgba(255, 107, 74, 0.05));
            box-shadow: 0 4px 12px rgba(214, 79, 60, 0.15);
        }
        
        .format-btn i {
            font-size: 36px;
            margin-bottom: 8px;
            display: block;
        }
        
        .format-btn.excel i { color: #1D6F42; }
        .format-btn.pdf i { color: #D64F3C; }
        .format-btn.csv i { color: #4A90E2; }
        
        .format-btn span {
            font-weight: 700;
            font-size: 15px;
            display: block;
            margin-bottom: 4px;
        }
        
        .format-btn small {
            color: var(--text-muted);
            font-size: 11px;
        }
        
        .preview-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            border: 2px dashed var(--secondary);
        }
        
        .preview-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .preview-horizontal {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .preview-item {
            flex: 1 1 200px;
            min-width: 150px;
            background: white;
            border-radius: 14px;
            padding: 15px 12px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        
        .preview-item .label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        
        .preview-item .value {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.2;
        }
        
        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 15px;
            padding: 12px 16px;
            background: var(--primary-soft);
            border-radius: 40px;
            font-size: 13px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .total-badge {
            background: var(--primary);
            color: white;
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }
        
        .period-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .period-item {
            background: white;
            border: 2px solid var(--border);
            border-radius: 40px;
            padding: 10px 5px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        
        .period-item.active {
            background: var(--secondary);
            border-color: var(--secondary);
            color: white;
        }
        
        .period-item input[type="radio"] {
            display: none;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--primary);
            font-size: 13px;
        }
        
        .form-group label i {
            color: var(--secondary);
            margin-right: 4px;
            width: 16px;
        }
        
        .form-control,
        .form-select {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: white;
        }
        
        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(214, 79, 60, 0.1);
        }
        
        .date-input-group {
            position: relative;
        }
        
        .date-input-group i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            font-size: 14px;
        }
        
        .checkbox-group {
            background: var(--primary-soft);
            border-radius: 14px;
            padding: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        
        .checkbox-item:hover {
            background: white;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--secondary);
            cursor: pointer;
        }
        
        .checkbox-item label {
            margin: 0;
            font-weight: 500;
            color: var(--text);
            cursor: pointer;
            flex: 1;
            font-size: 13px;
        }
        
        .badge {
            background: var(--primary-soft);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .export-footer {
            padding: 20px 30px 30px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            background: white;
            border-top: 1px solid var(--border);
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
            color: white;
            box-shadow: 0 8px 20px rgba(214, 79, 60, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(214, 79, 60, 0.4);
        }
        
        .btn-secondary {
            background: var(--border);
            color: var(--text);
        }
        
        .btn-secondary:hover {
            background: var(--text-muted);
            color: white;
        }
        
        .btn-close {
            background: none;
            border: 2px solid var(--border);
            color: var(--text-muted);
        }
        
        .btn-close:hover {
            background: var(--border);
            color: var(--text);
        }
        
        .btn-close i {
            display: inline-block;
            margin-right: 4px;
        }
        
        /* SCROLLBAR */
        .export-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .export-body::-webkit-scrollbar-track {
            background: var(--primary-soft);
            border-radius: 10px;
        }
        
        .export-body::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        
        /* DESKTOP */
        @media (min-width: 1024px) {
            .period-grid {
                grid-template-columns: repeat(8, 1fr);
            }
        }
        
        /* TABLET */
        @media (min-width: 769px) and (max-width: 1023px) {
            .period-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        /* MOBILE */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .export-header {
                padding: 16px 18px;
                flex-wrap: wrap;
            }
            
            .export-header h1 {
                font-size: 20px;
            }
            
            .export-body {
                padding: 20px;
            }
            
            .format-buttons {
                grid-template-columns: repeat(3, 1fr);
                gap: 6px;
            }
            
            .format-btn {
                padding: 12px 6px;
            }
            
            .format-btn i {
                font-size: 28px;
            }
            
            .period-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 6px;
            }
            
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .export-footer {
                padding: 16px 20px 20px;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .period-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .preview-item {
                flex: 1 1 calc(50% - 10px);
                min-width: 120px;
            }
            
            .preview-item .value {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="export-container">
        <div class="export-header">
            <i class="fas fa-download"></i>
            <div>
                <h1>Export Data Premium</h1>
                <p>Filter data leads sesuai kebutuhan Anda</p>
            </div>
            <span class="role-badge"><?= $role_display ?>: <?= htmlspecialchars($user_name) ?></span>
            <button class="export-close" id="closeHeaderBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="export-body">
            <!-- Pilihan Format Export -->
            <div class="filter-section">
                <div class="section-title">
                    <i class="fas fa-file-export"></i>
                    <span>Pilih Format Export</span>
                </div>
                <div class="format-buttons">
                    <div class="format-btn excel active" data-format="excel" onclick="setFormat('excel')">
                        <i class="fas fa-file-excel"></i>
                        <span>Excel</span>
                        <small>.xls</small>
                    </div>
                    <div class="format-btn pdf" data-format="pdf" onclick="setFormat('pdf')">
                        <i class="fas fa-file-pdf"></i>
                        <span>PDF</span>
                        <small>.html</small>
                    </div>
                    <div class="format-btn csv" data-format="csv" onclick="setFormat('csv')">
                        <i class="fas fa-file-csv"></i>
                        <span>CSV</span>
                        <small>.csv</small>
                    </div>
                </div>
            </div>
            
            <!-- PREVIEW DATA -->
            <div class="preview-card">
                <div class="preview-title">
                    <i class="fas fa-chart-pie" style="color: var(--secondary);"></i>
                    <span>Preview Data</span>
                </div>
                <div class="preview-horizontal" id="previewHorizontal">
                    <div class="preview-item">
                        <div class="label">Total Leads</div>
                        <div class="value" id="previewTotal"><?= $initial_total ?></div>
                    </div>
                    <div class="preview-item">
                        <div class="label">Hari Ini</div>
                        <div class="value" id="previewToday"><?= $initial_today ?></div>
                    </div>
                    <div class="preview-item">
                        <div class="label">Minggu Ini</div>
                        <div class="value" id="previewWeek"><?= $initial_week ?></div>
                    </div>
                    <div class="preview-item">
                        <div class="label">Bulan Ini</div>
                        <div class="value" id="previewMonth"><?= $initial_month ?></div>
                    </div>
                </div>
                
                <div class="info-row">
                    <span><i class="fas fa-map-marker-alt" style="color: var(--secondary);"></i> <span id="previewLocationInfo">
                        <?php if ($current_role === 'developer'): ?>
                            Lokasi Anda (otomatis)
                        <?php else: ?>
                            Semua Lokasi
                        <?php endif; ?>
                    </span></span>
                    <span><i class="fas fa-tags" style="color: var(--secondary);"></i> <span id="previewStatusInfo">Semua Status</span></span>
                    <span class="total-badge" id="previewTotalBadge"><?= $initial_total ?> Data</span>
                </div>
                
                <?php if ($current_role === 'developer' && empty($developer_locations)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 12px; margin-top: 15px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Perhatian:</strong> Anda belum memiliki akses lokasi. Data yang ditampilkan akan kosong.
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Filter Periode -->
            <div class="filter-section">
                <div class="section-title">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Periode Waktu</span>
                </div>
                
                <div class="period-grid" id="periodGrid">
                    <div class="period-item active" onclick="selectPeriod(this, 'all')">
                        <input type="radio" name="period" value="all" checked> Semua
                    </div>
                    <div class="period-item" onclick="selectPeriod(this, 'today')">
                        <input type="radio" name="period" value="today"> Hari Ini
                    </div>
                    <div class="period-item" onclick="selectPeriod(this, 'yesterday')">
                        <input type="radio" name="period" value="yesterday"> Kemarin
                    </div>
                    <div class="period-item" onclick="selectPeriod(this, 'week')">
                        <input type="radio" name="period" value="week"> Minggu
                    </div>
                    <div class="period-item" onclick="selectPeriod(this, 'month')">
                        <input type="radio" name="period" value="month"> Bulan
                    </div>
                    <div class="period-item" onclick="selectPeriod(this, 'last_month')">
                        <input type="radio" name="period" value="last_month"> Bulan Lalu
                    </div>
                    <div class="period-item" onclick="selectPeriod(this, 'year')">
                        <input type="radio" name="period" value="year"> Tahun
                    </div>
                    <div class="period-item" onclick="selectPeriod(this, 'custom')">
                        <input type="radio" name="period" value="custom"> Kustom
                    </div>
                </div>
                
                <div id="customDateRange" style="display: none; margin-top: 16px;">
                    <div class="grid-2">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-plus"></i> Tanggal Mulai</label>
                            <div class="date-input-group">
                                <input type="date" id="start_date" class="form-control" value="<?= date('Y-m-01') ?>" onchange="updatePreview()">
                                <i class="fas fa-calendar"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-check"></i> Tanggal Akhir</label>
                            <div class="date-input-group">
                                <input type="date" id="end_date" class="form-control" value="<?= date('Y-m-d') ?>" onchange="updatePreview()">
                                <i class="fas fa-calendar"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Status -->
            <div class="filter-section">
                <div class="section-title">
                    <i class="fas fa-tags"></i>
                    <span>Filter Status</span>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <span class="badge"><i class="fas fa-check-circle"></i> <span id="selectedStatusCount">0</span> status dipilih</span>
                    <button type="button" onclick="toggleAllStatus()" class="badge" style="background: white; border: 1px solid var(--border); cursor: pointer;">
                        <i class="fas fa-check-double"></i> Pilih Semua
                    </button>
                </div>
                
                <div class="checkbox-group" id="statusCheckboxGroup">
                    <?php foreach ($status_list as $status): ?>
                    <div class="checkbox-item">
                        <input type="checkbox" id="status_<?= str_replace(' ', '_', $status) ?>" value="<?= $status ?>" class="status-checkbox" onchange="updateSelectedCounts(); updatePreview()">
                        <label for="status_<?= str_replace(' ', '_', $status) ?>"><?= $status ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Filter Lokasi untuk Admin/Manager -->
            <?php if ($current_role === 'admin' || $current_role === 'manager'): ?>
            <div class="filter-section">
                <div class="section-title">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Filter Lokasi</span>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <span class="badge"><i class="fas fa-check-circle"></i> <span id="selectedLocationCount">0</span> lokasi dipilih</span>
                    <button type="button" onclick="toggleAllLocations()" class="badge" style="background: white; border: 1px solid var(--border); cursor: pointer;">
                        <i class="fas fa-check-double"></i> Pilih Semua
                    </button>
                </div>
                
                <div class="checkbox-group" id="locationCheckboxGroup">
                    <?php foreach ($locations as $loc): ?>
                    <div class="checkbox-item">
                        <input type="checkbox" id="loc_<?= $loc['location_key'] ?>" value="<?= $loc['location_key'] ?>" class="location-checkbox" onchange="updateSelectedCounts(); updatePreview()">
                        <label for="loc_<?= $loc['location_key'] ?>">
                            <?= $loc['icon'] ?? '🏠' ?> <?= htmlspecialchars($loc['display_name']) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Informasi untuk Developer -->
            <?php if ($current_role === 'developer'): ?>
            <div class="filter-section">
                <div class="section-title">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Lokasi Anda</span>
                </div>
                <div class="checkbox-group" style="background: var(--primary-soft);">
                    <?php if (empty($developer_locations)): ?>
                    <div class="checkbox-item" style="justify-content: center; color: var(--text-muted);">
                        <i class="fas fa-exclamation-circle"></i> Belum ada lokasi yang diassign
                    </div>
                    <?php else: ?>
                        <?php foreach ($developer_locations as $loc): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" id="loc_<?= $loc['location_key'] ?>" value="<?= $loc['location_key'] ?>" class="location-checkbox" checked disabled style="accent-color: var(--secondary); opacity: 0.7;">
                            <label for="loc_<?= $loc['location_key'] ?>" style="opacity: 0.9;">
                                <?= $loc['icon'] ?? '🏠' ?> <?= htmlspecialchars($loc['display_name']) ?>
                                <span style="color: var(--text-muted); font-size: 11px;">(otomatis)</span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <small style="display: block; margin-top: 8px; color: var(--text-muted); font-size: 12px;">
                    <i class="fas fa-info-circle"></i> 
                    Lokasi export akan mengikuti akses developer Anda
                </small>
            </div>
            <?php endif; ?>
            
            <!-- Informasi untuk Marketing -->
            <?php if ($current_role === 'marketing'): ?>
            <div class="filter-section">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i>
                    <span>Informasi</span>
                </div>
                <div class="checkbox-item" style="background: var(--primary-soft); padding: 12px; border-radius: 12px;">
                    <i class="fas fa-user-check" style="color: var(--secondary); font-size: 18px;"></i>
                    <div>
                        <strong>Anda akan mengexport data leads Anda sendiri</strong>
                        <small style="display: block; color: var(--text-muted); margin-top: 2px;">
                            Hanya leads yang ditugaskan kepada Anda yang akan diexport
                        </small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Opsi Tambahan -->
            <div class="filter-section">
                <div class="section-title">
                    <i class="fas fa-sliders-h"></i>
                    <span>Opsi Tambahan</span>
                </div>
                
                <div class="checkbox-item" style="background: var(--primary-soft); padding: 12px; border-radius: 12px;">
                    <input type="checkbox" id="include_duplicate" value="1" checked onchange="updatePreview()">
                    <label for="include_duplicate">
                        <strong>Sertakan data duplikat</strong>
                        <small style="display: block; color: var(--text-muted); margin-top: 2px;">
                            Data dengan peringatan duplikat tetap diexport
                        </small>
                    </label>
                </div>
            </div>
        </div>
        
        <div class="export-footer">
            <button class="btn btn-close" id="closeFooterBtn">
                <i class="fas fa-times"></i> Tutup
            </button>
            <button class="btn btn-primary" onclick="exportData()" id="exportBtn">
                <i class="fas fa-download"></i> Export Sekarang
            </button>
        </div>
    </div>
    
    <script>
        // ========== GLOBAL VARIABLES ==========
        let currentFormat = 'excel';
        let currentRole = '<?= $current_role ?>';
        let marketingId = <?= $marketing_id ?: 0 ?>;
        let developerId = <?= $developer_id ?: 0 ?>;
        let hasLocations = <?= ($current_role === 'developer' && !empty($developer_locations)) ? 'true' : 'true' ?>;
        
        // ========== FUNGSI CLOSE MODAL ==========
        function closeExportModal() {
            console.log('closeExportModal called from role: ' + currentRole);
            try {
                // Coba panggil parent function
                if (window.parent && typeof window.parent.closePremiumExportModal === 'function') {
                    window.parent.closePremiumExportModal();
                } else if (window.parent && typeof window.parent.closeExportModal === 'function') {
                    window.parent.closeExportModal();
                } else {
                    // Fallback: reload parent
                    try {
                        window.parent.location.reload();
                    } catch (e) {
                        // Jika gagal, redirect sendiri
                        window.location.href = 'index.php';
                    }
                }
            } catch (error) {
                console.error('Close modal error:', error);
                window.location.href = 'index.php';
            }
        }
        
        // ========== FORMAT FUNCTIONS ==========
        function setFormat(format) {
            currentFormat = format;
            document.querySelectorAll('.format-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`.format-btn.${format}`).classList.add('active');
        }
        
        // ========== PERIOD FUNCTIONS ==========
        function selectPeriod(element, value) {
            document.querySelectorAll('.period-item').forEach(item => {
                item.classList.remove('active');
            });
            element.classList.add('active');
            
            const radio = element.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
            
            const customRange = document.getElementById('customDateRange');
            if (value === 'custom') {
                customRange.style.display = 'block';
            } else {
                customRange.style.display = 'none';
            }
            
            updatePreview();
        }
        
        // ========== CHECKBOX FUNCTIONS ==========
        function updateSelectedCounts() {
            const statusCount = document.querySelectorAll('.status-checkbox:checked').length;
            document.getElementById('selectedStatusCount').textContent = statusCount;
            
            if (document.getElementById('selectedLocationCount')) {
                const locationCount = document.querySelectorAll('.location-checkbox:checked').length;
                document.getElementById('selectedLocationCount').textContent = locationCount;
            }
        }
        
        function toggleAllStatus() {
            const checkboxes = document.querySelectorAll('.status-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
            updateSelectedCounts();
            updatePreview();
        }
        
        function toggleAllLocations() {
            const checkboxes = document.querySelectorAll('.location-checkbox:not([disabled])');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
            updateSelectedCounts();
            updatePreview();
        }
        
        // ========== PREVIEW FUNCTION ==========
        function updatePreview() {
            const periodElement = document.querySelector('.period-item.active input[type="radio"]');
            const period = periodElement ? periodElement.value : 'all';
            const startDate = document.getElementById('start_date') ? document.getElementById('start_date').value : '';
            const endDate = document.getElementById('end_date') ? document.getElementById('end_date').value : '';
            const status = Array.from(document.querySelectorAll('.status-checkbox:checked')).map(cb => cb.value);
            
            <?php if ($current_role === 'admin' || $current_role === 'manager'): ?>
            const locations = Array.from(document.querySelectorAll('.location-checkbox:checked')).map(cb => cb.value);
            <?php else: ?>
            const locations = [];
            <?php endif; ?>
            
            const includeDuplicate = document.getElementById('include_duplicate').checked ? 1 : 0;
            
            // Update info text
            <?php if ($current_role === 'admin' || $current_role === 'manager'): ?>
            let locationText = locations.length === 0 ? 'Semua Lokasi' : locations.length + ' lokasi dipilih';
            <?php elseif ($current_role === 'developer'): ?>
            let locationText = 'Lokasi Anda (otomatis)';
            <?php else: ?>
            let locationText = 'Lokasi (sesuai akses)';
            <?php endif; ?>
            
            let statusText = status.length === 0 ? 'Semua Status' : status.length + ' status dipilih';
            document.getElementById('previewLocationInfo').textContent = locationText;
            document.getElementById('previewStatusInfo').textContent = statusText;
            
            // Loading state
            document.getElementById('previewTotal').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            document.getElementById('previewToday').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            document.getElementById('previewWeek').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            document.getElementById('previewMonth').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            const formData = new FormData();
            formData.append('action', 'preview');
            formData.append('period', period);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('include_duplicate', includeDuplicate);
            
            status.forEach(s => formData.append('status[]', s));
            locations.forEach(l => formData.append('locations[]', l));
            
            fetch('api/export_filtered.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    document.getElementById('previewTotal').textContent = data.stats.total;
                    document.getElementById('previewToday').textContent = data.stats.today;
                    document.getElementById('previewWeek').textContent = data.stats.week;
                    document.getElementById('previewMonth').textContent = data.stats.month;
                    document.getElementById('previewTotalBadge').textContent = data.stats.total + ' Data';
                } else {
                    document.getElementById('previewTotal').textContent = 'Error';
                    document.getElementById('previewToday').textContent = 'Error';
                    document.getElementById('previewWeek').textContent = 'Error';
                    document.getElementById('previewMonth').textContent = 'Error';
                }
            })
            .catch(error => {
                console.error('Preview error:', error);
                document.getElementById('previewTotal').textContent = 'Error';
                document.getElementById('previewToday').textContent = 'Error';
                document.getElementById('previewWeek').textContent = 'Error';
                document.getElementById('previewMonth').textContent = 'Error';
            });
        }
        
        // ========== EXPORT FUNCTION ==========
        function exportData() {
            const periodElement = document.querySelector('.period-item.active input[type="radio"]');
            const period = periodElement ? periodElement.value : 'all';
            const startDate = document.getElementById('start_date') ? document.getElementById('start_date').value : '';
            const endDate = document.getElementById('end_date') ? document.getElementById('end_date').value : '';
            const status = Array.from(document.querySelectorAll('.status-checkbox:checked')).map(cb => cb.value);
            
            <?php if ($current_role === 'admin' || $current_role === 'manager'): ?>
            const locations = Array.from(document.querySelectorAll('.location-checkbox:checked')).map(cb => cb.value);
            <?php else: ?>
            const locations = [];
            <?php endif; ?>
            
            const includeDuplicate = document.getElementById('include_duplicate').checked ? 1 : 0;
            
            // Validasi untuk developer
            <?php if ($current_role === 'developer' && empty($developer_locations)): ?>
            alert('❌ Anda belum memiliki akses lokasi. Data export akan kosong.');
            <?php endif; ?>
            
            const btn = document.getElementById('exportBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('format', currentFormat);
            formData.append('period', period);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('include_duplicate', includeDuplicate);
            
            status.forEach(s => formData.append('status[]', s));
            locations.forEach(l => formData.append('locations[]', l));
            
            fetch('api/export_filtered.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.blob();
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                
                let extension = 'xls';
                if (currentFormat === 'pdf') extension = 'html';
                else if (currentFormat === 'csv') extension = 'csv';
                
                a.href = url;
                a.download = `leads_export_${currentRole}_${new Date().getTime()}.${extension}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
                
                btn.innerHTML = originalText;
                btn.disabled = false;
            })
            .catch(error => {
                console.error('Export error:', error);
                alert('❌ Gagal export: ' + error.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // ========== EVENT LISTENERS ==========
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Export modal loaded - Role: ' + currentRole);
            
            // Close buttons
            const closeHeaderBtn = document.getElementById('closeHeaderBtn');
            if (closeHeaderBtn) {
                closeHeaderBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Close header button clicked');
                    closeExportModal();
                });
            }
            
            const closeFooterBtn = document.getElementById('closeFooterBtn');
            if (closeFooterBtn) {
                closeFooterBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Close footer button clicked');
                    closeExportModal();
                });
            }
            
            // Update counts
            updateSelectedCounts();
            updatePreview();
            
            // Auto refresh preview setiap 30 detik
            setInterval(updatePreview, 30000);
        });
        
        // Export functions ke global
        window.closeExportModal = closeExportModal;
        window.setFormat = setFormat;
        window.selectPeriod = selectPeriod;
        window.updateSelectedCounts = updateSelectedCounts;
        window.toggleAllStatus = toggleAllStatus;
        window.toggleAllLocations = toggleAllLocations;
        window.updatePreview = updatePreview;
        window.exportData = exportData;
    </script>
</body>
</html>