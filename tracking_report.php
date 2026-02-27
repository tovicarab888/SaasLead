<?php
/**
 * TRACKING_REPORT.PHP - Laporan Performa Tracking Pixel
 * Version: 5.0.0 - CSS INDEPENDENT TIDAK GANGGU GLOBAL
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

// Hanya admin, manager, developer yang bisa akses
if (!isAdmin() && !isManager() && !isDeveloper() && !isManagerDeveloper()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Admin, Manager, dan Developer.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== AMBIL DATA UNTUK FILTER ==========
$dev_sql = "SELECT id, nama_lengkap FROM users WHERE role = 'developer' AND is_active = 1 ORDER BY nama_lengkap";

if (isDeveloper()) {
    $dev_sql = "SELECT id, nama_lengkap FROM users WHERE id = " . $_SESSION['user_id'];
} elseif (isManagerDeveloper() && isset($_SESSION['developer_id'])) {
    $dev_sql = "SELECT id, nama_lengkap FROM users WHERE id = " . $_SESSION['developer_id'];
}

$developers = $conn->query($dev_sql)->fetchAll();

// ========== SET VARIABLES ==========
$page_title = 'Tracking Report';
$page_subtitle = 'Analisis Performa Tracking Pixel';
$page_icon = 'fas fa-chart-line';
$default_start = date('Y-m-d', strtotime('-7 days'));
$default_end = date('Y-m-d');

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- ===== CSS KHUSUS TRACKING REPORT ===== -->
<style>
/* ===== RESET KHUSUS UNTUK TRACKING REPORT ===== */
.tracking-report * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.tracking-report {
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
    --meta: #1877F2;
    --tiktok: #000000;
    --google: #EA4335;
    
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--text);
    line-height: 1.5;
}

/* ===== MAIN CONTENT ===== */
.tracking-report .main-content {
    width: 100%;
    min-height: 100vh;
    padding: 12px;
    margin-left: 0 !important;
    background: var(--bg);
}

/* ===== HEADER ===== */
.tracking-report .header-gradient {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 20px;
    padding: 20px 16px;
    margin-bottom: 16px;
    color: white;
    box-shadow: 0 4px 12px rgba(27,74,60,0.2);
}

.tracking-report .header-gradient h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: white;
}

.tracking-report .header-gradient h1 i {
    color: var(--warning);
    font-size: 1.6rem;
}

.tracking-report .header-gradient p {
    font-size: 0.85rem;
    opacity: 0.9;
    color: white;
}

/* ===== TOP BAR ===== */
.tracking-report .top-bar {
    background: var(--surface);
    border-radius: 16px;
    padding: 12px;
    margin-bottom: 16px;
    border-left: 4px solid var(--secondary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    gap: 8px;
}

@media (min-width: 768px) {
    .tracking-report .top-bar {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.tracking-report .welcome-text {
    display: flex;
    align-items: center;
    gap: 10px;
}

.tracking-report .welcome-text i {
    width: 40px;
    height: 40px;
    background: rgba(214,79,60,0.1);
    color: var(--secondary);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.tracking-report .welcome-text h2 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    margin: 0;
}

.tracking-report .welcome-text h2 span {
    display: block;
    font-size: 12px;
    font-weight: 400;
    color: var(--text-muted);
    margin-top: 2px;
}

.tracking-report .datetime {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--bg);
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 500;
    color: var(--primary);
}

.tracking-report .time {
    background: var(--surface);
    padding: 4px 10px;
    border-radius: 20px;
}

/* ===== STATS CARDS ===== */
.tracking-report .stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}

@media (min-width: 768px) {
    .tracking-report .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

.tracking-report .stat-card {
    background: white;
    border-radius: 16px;
    padding: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    border-left: 4px solid var(--secondary);
    transition: all 0.2s;
}

.tracking-report .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}

.tracking-report .stat-icon {
    width: 36px;
    height: 36px;
    background: var(--primary-soft);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1rem;
    margin-bottom: 8px;
}

.tracking-report .stat-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 2px;
}

.tracking-report .stat-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text);
    line-height: 1.2;
}

.tracking-report .stat-trend {
    font-size: 0.65rem;
    margin-top: 4px;
}

.tracking-report .trend-up { color: var(--success); }
.tracking-report .trend-down { color: var(--danger); }

/* ===== FILTER SECTION ===== */
.tracking-report .filter-section {
    background: white;
    border-radius: 18px;
    padding: 16px;
    margin-bottom: 16px;
    border: 1px solid var(--border);
}

.tracking-report .filter-header {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    color: var(--primary);
    cursor: pointer;
    padding: 4px 0;
}

.tracking-report .filter-header i:first-child {
    color: var(--secondary);
}

.tracking-report .filter-header i:last-child {
    margin-left: auto;
    transition: transform 0.3s;
    color: var(--secondary);
}

.tracking-report .filter-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    margin-top: 16px;
}

@media (min-width: 768px) {
    .tracking-report .filter-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1024px) {
    .tracking-report .filter-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}

.tracking-report .filter-item {
    width: 100%;
}

.tracking-report .filter-item label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 4px;
    color: var(--primary);
}

.tracking-report .filter-item label i {
    color: var(--secondary);
    margin-right: 4px;
    width: 14px;
}

.tracking-report .filter-item select,
.tracking-report .filter-item input {
    width: 100%;
    padding: 10px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-size: 0.9rem;
    background: white;
    font-family: inherit;
}

.tracking-report .filter-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
    flex-wrap: wrap;
}

.tracking-report .btn {
    padding: 10px 16px;
    border: none;
    border-radius: 30px;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 40px;
    flex: 1 1 auto;
    transition: all 0.2s;
    font-family: inherit;
}

.tracking-report .btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: 0 4px 8px rgba(27,74,60,0.2);
}

.tracking-report .btn-primary:active {
    transform: scale(0.98);
}

.tracking-report .btn-secondary {
    background: var(--primary-soft);
    color: var(--primary);
}

.tracking-report .btn-secondary:active {
    background: var(--border);
}

.tracking-report .btn-success {
    background: linear-gradient(135deg, var(--success), #40BEB0);
    color: white;
}

.tracking-report .btn-danger {
    background: linear-gradient(135deg, var(--danger), #FF6B4A);
    color: white;
}

/* ===== CHARTS SECTION ===== */
.tracking-report .charts-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

@media (min-width: 992px) {
    .tracking-report .charts-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.tracking-report .chart-card {
    background: white;
    border-radius: 18px;
    padding: 16px;
    border: 1px solid var(--border);
}

.tracking-report .chart-card h3 {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.tracking-report .chart-card h3 i {
    color: var(--secondary);
}

.tracking-report .chart-container {
    position: relative;
    height: 200px;
    width: 100%;
}

/* ===== TABLE SECTION ===== */
.tracking-report .table-section {
    background: white;
    border-radius: 18px;
    padding: 16px;
    border: 1px solid var(--border);
    margin-bottom: 16px;
}

.tracking-report .table-header {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
}

@media (min-width: 768px) {
    .tracking-report .table-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.tracking-report .table-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.tracking-report .table-title i {
    color: var(--secondary);
    font-size: 1.2rem;
}

.tracking-report .table-title h2 {
    font-size: 1rem;
    color: var(--primary);
    margin: 0;
}

.tracking-report .auto-refresh {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--primary-soft);
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 0.8rem;
    flex-wrap: wrap;
}

.tracking-report .auto-refresh input {
    width: 50px;
    padding: 4px;
    border: 1px solid var(--border);
    border-radius: 6px;
    text-align: center;
}

/* Toggle Switch */
.tracking-report .switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 24px;
}

.tracking-report .switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.tracking-report .slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--border);
    transition: .3s;
    border-radius: 24px;
}

.tracking-report .slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

.tracking-report input:checked + .slider {
    background: linear-gradient(135deg, var(--success), #40BEB0);
}

.tracking-report input:checked + .slider:before {
    transform: translateX(22px);
}

/* ===== TABLE RESPONSIVE ===== */
.tracking-report .table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 0 -16px;
    padding: 0 16px;
    width: calc(100% + 32px);
}

.tracking-report .table-responsive::-webkit-scrollbar {
    height: 4px;
}

.tracking-report .table-responsive::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.tracking-report .table-responsive::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

/* ===== DATATABLE CUSTOM ===== */
.tracking-report .dataTables_wrapper {
    width: 100% !important;
    font-size: 0.8rem;
}

.tracking-report table.dataTable {
    width: 100% !important;
    border-collapse: collapse;
    min-width: 900px;
}

.tracking-report table.dataTable thead th {
    background: linear-gradient(135deg, var(--primary-soft), #f0f7f4);
    padding: 12px 8px;
    font-weight: 700;
    color: var(--primary);
    font-size: 0.7rem;
    text-transform: uppercase;
    white-space: nowrap;
    border-bottom: 2px solid var(--border);
}

.tracking-report table.dataTable thead th i {
    margin-right: 4px;
    color: var(--secondary);
}

.tracking-report table.dataTable tbody td {
    padding: 10px 8px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    font-size: 0.75rem;
}

.tracking-report table.dataTable tbody tr:hover td {
    background: var(--primary-soft);
}

/* ===== BADGES ===== */
.tracking-report .status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    text-align: center;
    min-width: 60px;
}

.tracking-report .status-success {
    background: #e3f2ed;
    color: var(--success);
    border-left: 2px solid var(--success);
}

.tracking-report .status-failed {
    background: #fee9e7;
    color: var(--danger);
    border-left: 2px solid var(--danger);
}

.tracking-report .status-pending {
    background: #fff4e0;
    color: #b85c00;
    border-left: 2px solid #ffc107;
}

.tracking-report .platform-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
}

.tracking-report .platform-meta { color: #1877F2; }
.tracking-report .platform-tiktok { color: #000000; }
.tracking-report .platform-google { color: #EA4335; }

/* ===== ACTION BUTTONS ===== */
.tracking-report .btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: white;
    color: var(--text);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    margin: 0 2px;
    padding: 0;
}

.tracking-report .btn-icon:hover {
    background: var(--primary-soft);
    color: var(--secondary);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.tracking-report .btn-icon:active {
    transform: scale(0.95);
}

.tracking-report .btn-icon.btn-danger {
    background: #fee9e7;
    color: var(--danger);
    border-color: #ffcdc7;
}

.tracking-report .btn-icon.btn-danger:hover {
    background: var(--danger);
    color: white;
}

/* ===== MODAL KHUSUS ===== */
.tracking-report .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.tracking-report .modal.show {
    display: flex !important;
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.tracking-report .modal-content {
    background: white;
    border-radius: 28px;
    width: 100%;
    max-width: 700px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 30px 60px rgba(0,0,0,0.4);
    animation: modalSlideUp 0.4s ease;
    overflow: hidden;
}

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.tracking-report .modal-header {
    padding: 20px 24px;
    border-bottom: 2px solid var(--primary-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, white, #fafafa);
    flex-shrink: 0;
}

.tracking-report .modal-header h3 {
    color: var(--primary);
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tracking-report .modal-header h3 i {
    color: var(--secondary);
    font-size: 1.4rem;
    background: rgba(214,79,60,0.1);
    padding: 8px;
    border-radius: 12px;
}

.tracking-report .modal-close {
    width: 44px;
    height: 44px;
    background: var(--primary-soft);
    border: none;
    border-radius: 14px;
    color: var(--secondary);
    font-size: 22px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
    z-index: 10;
    position: relative;
}

.tracking-report .modal-close:hover {
    background: var(--secondary);
    color: white;
    transform: rotate(90deg);
}

.tracking-report .modal-close:active {
    transform: scale(0.95);
}

.tracking-report .modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
    background: white;
}

.tracking-report .modal-body::-webkit-scrollbar {
    width: 6px;
}

.tracking-report .modal-body::-webkit-scrollbar-track {
    background: var(--primary-soft);
    border-radius: 10px;
}

.tracking-report .modal-body::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.tracking-report .modal-footer {
    padding: 16px 24px;
    border-top: 2px solid var(--primary-soft);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    background: #fafafa;
    flex-shrink: 0;
}

.tracking-report .modal-footer .btn {
    min-width: 100px;
}

/* ===== DETAIL ROWS ===== */
.tracking-report .detail-row {
    display: flex;
    margin-bottom: 12px;
    padding: 10px 0;
    border-bottom: 1px dashed var(--border);
    transition: all 0.2s;
}

.tracking-report .detail-row:hover {
    background: var(--primary-soft);
    padding-left: 10px;
    border-radius: 8px;
}

@media (max-width: 640px) {
    .tracking-report .detail-row {
        flex-direction: column;
    }
}

.tracking-report .detail-label {
    width: 130px;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.85rem;
    flex-shrink: 0;
}

.tracking-report .detail-label i {
    color: var(--secondary);
    width: 20px;
    margin-right: 5px;
}

.tracking-report .detail-value {
    flex: 1;
    color: var(--text);
    font-weight: 500;
    word-break: break-word;
}

.tracking-report .detail-value code {
    background: var(--primary-soft);
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    color: var(--primary);
    font-family: 'Courier New', monospace;
}

.tracking-report .detail-value a {
    color: var(--secondary);
    text-decoration: none;
    font-weight: 600;
}

.tracking-report .detail-value a:hover {
    text-decoration: underline;
}

/* ===== JSON PREVIEW ===== */
.tracking-report .json-preview {
    background: #1e2a26;
    color: #e2e8e2;
    padding: 16px;
    border-radius: 14px;
    font-family: 'Courier New', monospace;
    font-size: 0.8rem;
    line-height: 1.6;
    overflow-x: auto;
    white-space: pre-wrap;
    margin: 15px 0;
    max-height: 250px;
    overflow-y: auto;
    border-left: 4px solid var(--secondary);
}

.tracking-report .json-preview::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.tracking-report .json-preview::-webkit-scrollbar-track {
    background: #2a3a34;
    border-radius: 10px;
}

.tracking-report .json-preview::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

/* ===== RETRY BUTTON ===== */
.tracking-report .retry-btn {
    background: linear-gradient(135deg, var(--danger), #ff6b4a);
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    width: 100%;
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.3s;
    box-shadow: 0 8px 20px rgba(214,79,60,0.3);
}

.tracking-report .retry-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(214,79,60,0.4);
}

.tracking-report .retry-btn:active {
    transform: scale(0.98);
}

.tracking-report .retry-btn i {
    animation: spin 2s infinite linear;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* ===== PAGINATION ===== */
.tracking-report .dataTables_paginate {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 16px;
    justify-content: center;
}

.tracking-report .paginate_button {
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: white;
    color: var(--text);
    font-size: 0.75rem;
    cursor: pointer;
    min-width: 36px;
    text-align: center;
    transition: all 0.2s;
}

.tracking-report .paginate_button.current {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.tracking-report .paginate_button:hover:not(.current) {
    background: var(--primary-soft);
    border-color: var(--secondary);
}

.tracking-report .paginate_button.disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* ===== DATATABLE CONTROLS ===== */
.tracking-report .dataTables_length,
.tracking-report .dataTables_filter {
    margin-bottom: 12px;
}

.tracking-report .dataTables_length label,
.tracking-report .dataTables_filter label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: var(--text);
}

.tracking-report .dataTables_length select,
.tracking-report .dataTables_filter input {
    padding: 6px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: white;
}

.tracking-report .dataTables_filter input {
    flex: 1;
    min-width: 150px;
}

.tracking-report .dataTables_info {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin: 10px 0;
}

/* ===== FOOTER ===== */
.tracking-report .footer {
    text-align: center;
    margin-top: 30px;
    padding: 16px;
    color: var(--text-muted);
    font-size: 0.7rem;
    border-top: 1px solid var(--border);
}

/* ===== UTILITY ===== */
.tracking-report .text-center { text-align: center; }
.tracking-report .py-4 { padding: 16px 0; }

/* ===== DESKTOP UPGRADE ===== */
@media (min-width: 1024px) {
    .tracking-report .main-content {
        margin-left: 280px !important;
        padding: 24px !important;
    }
    
    .tracking-report .stat-value {
        font-size: 1.6rem;
    }
    
    .tracking-report .chart-container {
        height: 250px;
    }
}
</style>

<div class="tracking-report">
<div class="main-content">
    
    <!-- HEADER GRADIENT -->
    <div class="header-gradient">
        <h1>
            <i class="<?= $page_icon ?>"></i>
            <?= $page_title ?>
        </h1>
        <p>Monitor semua aktivitas tracking pixel Meta, TikTok, dan Google secara real-time.</p>
    </div>
    
    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="welcome-text">
            <i class="<?= $page_icon ?>"></i>
            <h2>
                Tracking Performance
                <span>Real-time monitoring dashboard</span>
            </h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <!-- STATS CARDS -->
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="stat-label">Loading...</div>
            <div class="stat-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="stat-label">Loading...</div>
            <div class="stat-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="stat-label">Loading...</div>
            <div class="stat-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="stat-label">Loading...</div>
            <div class="stat-value">-</div>
        </div>
    </div>
    
    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div class="filter-header" onclick="toggleFilter()">
            <i class="fas fa-filter"></i>
            <span>Filter Data Tracking</span>
            <i class="fas fa-chevron-down filter-toggle-icon"></i>
        </div>
        <div class="filter-body" id="filterBody" style="display: block;">
            <div class="filter-grid">
                <div class="filter-item">
                    <label><i class="fas fa-calendar"></i> Start Date</label>
                    <input type="date" id="startDate" value="<?= $default_start ?>">
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-calendar"></i> End Date</label>
                    <input type="date" id="endDate" value="<?= $default_end ?>">
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-chart-pie"></i> Platform</label>
                    <select id="platform">
                        <option value="all">All Platforms</option>
                        <option value="meta">Meta Pixel</option>
                        <option value="tiktok">TikTok Pixel</option>
                        <option value="google">Google Analytics</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-building"></i> Developer</label>
                    <select id="developer">
                        <option value="all">All Developers</option>
                        <?php foreach ($developers as $dev): ?>
                        <option value="<?= $dev['id'] ?>"><?= htmlspecialchars($dev['nama_lengkap']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-tag"></i> Status</label>
                    <select id="status">
                        <option value="all">All Status</option>
                        <option value="sent">Success</option>
                        <option value="failed">Failed</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" id="search" placeholder="Event ID / Lead ID...">
                </div>
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary" onclick="applyFilters()">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <button class="btn btn-secondary" onclick="resetFilters()">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button class="btn btn-success" onclick="exportData('csv')">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
                <button class="btn btn-success" onclick="exportData('excel')">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button class="btn btn-success" onclick="exportData('pdf')">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button class="btn btn-danger" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
    </div>
    
    <!-- CHARTS SECTION -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3><i class="fas fa-chart-line"></i> Events Per Day (7 Hari)</h3>
            <div class="chart-container">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3><i class="fas fa-chart-pie"></i> Platform Distribution</h3>
            <div class="chart-container">
                <canvas id="platformChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3><i class="fas fa-trophy"></i> Top Developers</h3>
            <div class="chart-container">
                <canvas id="developerChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- TABLE SECTION -->
    <div class="table-section">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-list"></i>
                <h2>Tracking Events Log</h2>
            </div>
            <div class="auto-refresh">
                <i class="fas fa-sync-alt"></i>
                <span>Auto</span>
                <input type="number" id="refreshInterval" value="30" min="5" max="300"> detik
                <label class="switch">
                    <input type="checkbox" id="autoRefresh" checked>
                    <span class="slider"></span>
                </label>
            </div>
        </div>
        
        <div class="table-responsive">
            <table id="trackingTable" class="display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-clock"></i> Timestamp</th>
                        <th><i class="fas fa-building"></i> Developer</th>
                        <th><i class="fas fa-chart-pie"></i> Platform</th>
                        <th><i class="fas fa-tag"></i> Event Name</th>
                        <th><i class="fas fa-fingerprint"></i> Event ID</th>
                        <th><i class="fas fa-link"></i> Lead ID</th>
                        <th><i class="fas fa-circle"></i> Status</th>
                        <th><i class="fas fa-code"></i> Response</th>
                        <th><i class="fas fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data dari AJAX -->
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Tracking Report v5.0 | Last updated: <span id="lastUpdate"><?= date('H:i:s') ?></span></p>
    </div>
    
</div>
</div>

<!-- DETAIL MODAL -->
<div class="tracking-report">
<div class="modal" id="detailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Tracking Event Details</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalContent">
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin fa-3x" style="color: var(--secondary);"></i>
                <p style="margin-top: 10px;">Loading...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Tutup</button>
        </div>
    </div>
</div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// ========== GLOBAL VARIABLES ==========
let trendChart, platformChart, developerChart;
let dataTable;
let refreshTimer;
const API_KEY = 'taufikmarie7878';
const default_start = '<?= $default_start ?>';
const default_end = '<?= $default_end ?>';

// ========== INITIALIZATION ==========
$(document).ready(function() {
    console.log('Document ready, initializing...');
    initDataTable();
    loadStats();
    loadCharts();
    startAutoRefresh();
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    $('#autoRefresh').change(function() {
        if ($(this).is(':checked')) {
            startAutoRefresh();
        } else {
            clearInterval(refreshTimer);
        }
    });
    
    $('#refreshInterval').change(function() {
        if ($('#autoRefresh').is(':checked')) {
            startAutoRefresh();
        }
    });
    
    $('#search').keypress(function(e) {
        if (e.which == 13) {
            applyFilters();
        }
    });
    
    // Set filter body visible by default
    $('#filterBody').show();
});

// ========== DATATABLE INIT ==========
function initDataTable() {
    console.log('Initializing DataTable...');
    
    if ($.fn.DataTable.isDataTable('#trackingTable')) {
        $('#trackingTable').DataTable().destroy();
    }
    
    dataTable = $('#trackingTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        scrollX: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        ajax: {
            url: 'api/get_tracking_logs.php',
            type: 'POST',
            data: function(d) {
                d.start_date = $('#startDate').val();
                d.end_date = $('#endDate').val();
                d.platform = $('#platform').val();
                d.developer_id = $('#developer').val();
                d.status = $('#status').val();
                d.search = $('#search').val();
                d.api_key = API_KEY;
            },
            xhrFields: {
                withCredentials: true
            },
            error: function(xhr, error, thrown) {
                console.error('DataTable AJAX error:', error, thrown);
                console.log('Response:', xhr.responseText);
            }
        },
        columns: [
            { data: 'id', name: 'id' },
            { 
                data: 'created_at',
                name: 'created_at',
                render: function(data) {
                    if (!data) return '-';
                    let date = new Date(data);
                    return date.toLocaleString('id-ID', { 
                        day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' 
                    });
                }
            },
            { 
                data: 'developer_name',
                name: 'developer_name',
                defaultContent: 'Global',
                render: function(data) {
                    return data || 'Global';
                }
            },
            { 
                data: 'pixel_type',
                name: 'pixel_type',
                render: function(data) {
                    let icons = {
                        'meta': '<span class="platform-badge platform-meta"><i class="fab fa-facebook"></i> Meta</span>',
                        'tiktok': '<span class="platform-badge platform-tiktok"><i class="fab fa-tiktok"></i> TikTok</span>',
                        'google': '<span class="platform-badge platform-google"><i class="fab fa-google"></i> Google</span>'
                    };
                    return icons[data] || data;
                }
            },
            { data: 'event_name', name: 'event_name', defaultContent: '-' },
            { 
                data: 'event_id', 
                name: 'event_id',
                render: function(data) {
                    if (!data) return '-';
                    return data.length > 20 ? data.substr(0, 20) + '...' : data;
                }
            },
            { 
                data: 'lead_id', 
                name: 'lead_id',
                render: function(data) {
                    return data ? '<a href="index.php?search=' + data + '" target="_blank">#' + data + '</a>' : '-';
                }
            },
            { 
                data: 'status',
                name: 'status',
                render: function(data) {
                    let classes = {
                        'sent': 'status-success',
                        'failed': 'status-failed',
                        'pending': 'status-pending'
                    };
                    return `<span class="status-badge ${classes[data] || ''}">${data}</span>`;
                }
            },
            { 
                data: 'response_code',
                name: 'response_code',
                render: function(data) {
                    if (!data || data === '-') return '-';
                    let color = data == 200 ? '#2A9D8F' : (data >= 400 ? '#D64F3C' : '#F4A261');
                    return `<span style="color: ${color}; font-weight: 600;">${data}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data) {
                    return `<button class="btn-icon" onclick="viewDetails(${data.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${data.status === 'failed' ? 
                                `<button class="btn-icon btn-danger" onclick="retryEvent(${data.id})" title="Retry">
                                    <i class="fas fa-sync-alt"></i>
                                </button>` : ''}`;
                }
            }
        ],
        order: [[0, 'desc']],
        language: {
            processing: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
            search: '_INPUT_',
            searchPlaceholder: 'Search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            zeroRecords: 'No matching records found'
        }
    });
}

// ========== LOAD STATS ==========
function loadStats() {
    const params = new URLSearchParams({
        start_date: $('#startDate').val(),
        end_date: $('#endDate').val(),
        platform: $('#platform').val(),
        developer_id: $('#developer').val(),
        api_key: API_KEY
    });
    
    fetch('/admin/api/get_tracking_stats.php?' + params, {
        credentials: 'include'
    })
    .then(res => res.json())
    .then(response => {
        if (response.success) {
            renderStats(response.data);
        }
    })
    .catch(error => {
        console.error('Error loading stats:', error);
    });
}

// ========== RENDER STATS ==========
function renderStats(stats) {
    const html = `
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-label">Today</div>
            <div class="stat-value">${stats.today || 0}</div>
            <div class="stat-trend ${stats.today_trend > 0 ? 'trend-up' : 'trend-down'}">
                <i class="fas fa-${stats.today_trend > 0 ? 'arrow-up' : 'arrow-down'}"></i>
                ${Math.abs(stats.today_trend || 0)}%
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
            <div class="stat-label">This Month</div>
            <div class="stat-value">${stats.month || 0}</div>
            <div class="stat-trend">
                <i class="fas fa-calendar"></i> ${stats.month_progress || 0}%
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Success Rate</div>
            <div class="stat-value">${stats.success_rate || 0}%</div>
            <div class="stat-trend trend-up">
                <i class="fas fa-check"></i> ${stats.success_count || 0}
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-label">Failed</div>
            <div class="stat-value">${stats.failed_count || 0}</div>
            <div class="stat-trend trend-down">
                <i class="fas fa-times"></i> ${stats.failed_rate || 0}%
            </div>
        </div>
    `;
    $('#statsGrid').html(html);
}

// ========== LOAD CHARTS ==========
function loadCharts() {
    const params = new URLSearchParams({
        start_date: $('#startDate').val(),
        end_date: $('#endDate').val(),
        platform: $('#platform').val(),
        developer_id: $('#developer').val(),
        api_key: API_KEY
    });
    
    fetch('/admin/api/get_tracking_charts.php?' + params, {
        credentials: 'include'
    })
    .then(res => res.json())
    .then(response => {
        if (response.success) {
            renderTrendChart(response.data.trend);
            renderPlatformChart(response.data.platform);
            renderDeveloperChart(response.data.developers);
        }
    })
    .catch(error => {
        console.error('Error loading charts:', error);
    });
}

// ========== RENDER TREND CHART ==========
function renderTrendChart(data) {
    const ctx = document.getElementById('trendChart').getContext('2d');
    
    if (trendChart) trendChart.destroy();
    
    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'Events',
                data: data.values || [],
                borderColor: '#1B4A3C',
                backgroundColor: 'rgba(27,74,60,0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#D64F3C',
                pointBorderColor: 'white',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

// ========== RENDER PLATFORM CHART ==========
function renderPlatformChart(data) {
    const ctx = document.getElementById('platformChart').getContext('2d');
    
    if (platformChart) platformChart.destroy();
    
    platformChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || ['Meta', 'TikTok', 'Google'],
            datasets: [{
                data: data.values || [0, 0, 0],
                backgroundColor: ['#1877F2', '#000000', '#EA4335'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            cutout: '60%'
        }
    });
}

// ========== RENDER DEVELOPER CHART ==========
function renderDeveloperChart(data) {
    const ctx = document.getElementById('developerChart').getContext('2d');
    
    if (developerChart) developerChart.destroy();
    
    developerChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'Events',
                data: data.values || [],
                backgroundColor: '#D64F3C',
                borderRadius: 8,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

// ========== APPLY FILTERS ==========
function applyFilters() {
    dataTable.ajax.reload();
    loadStats();
    loadCharts();
    $('#lastUpdate').text(new Date().toLocaleTimeString('id-ID'));
}

// ========== RESET FILTERS ==========
function resetFilters() {
    $('#startDate').val(default_start);
    $('#endDate').val(default_end);
    $('#platform').val('all');
    $('#developer').val('all');
    $('#status').val('all');
    $('#search').val('');
    applyFilters();
}

// ========== AUTO REFRESH ==========
function startAutoRefresh() {
    if (refreshTimer) clearInterval(refreshTimer);
    
    const interval = parseInt($('#refreshInterval').val()) * 1000;
    refreshTimer = setInterval(() => {
        if ($('#autoRefresh').is(':checked')) {
            dataTable.ajax.reload(null, false);
            loadStats();
            $('#lastUpdate').text(new Date().toLocaleTimeString('id-ID'));
        }
    }, interval);
}

// ========== VIEW DETAILS ==========
function viewDetails(id) {
    fetch('/admin/api/get_tracking_log.php?id=' + id + '&api_key=' + API_KEY, {
        credentials: 'include'
    })
    .then(res => res.json())
    .then(response => {
        if (response.success) {
            showDetailModal(response.data);
        } else {
            alert('Gagal memuat detail: ' + response.message);
        }
    })
    .catch(error => {
        console.error('Error loading details:', error);
        alert('Error loading details');
    });
}

// ========== SHOW DETAIL MODAL ==========
function showDetailModal(log) {
    let payloadHtml = '-';
    try {
        if (log.payload) {
            let payload = typeof log.payload === 'string' ? JSON.parse(log.payload) : log.payload;
            payloadHtml = JSON.stringify(payload, null, 2);
        }
    } catch (e) {
        payloadHtml = log.payload || '-';
    }
    
    let responseHtml = log.response || '-';
    if (log.response && log.response.length > 500) {
        responseHtml = log.response.substr(0, 500) + '... (truncated)';
    }
    
    // Extract response code if available
    let responseCode = log.response_code || '-';
    if (responseCode === '-' && log.response) {
        if (log.response.includes('"code":40105')) responseCode = '40105';
        else if (log.response.includes('200')) responseCode = '200';
    }
    
    const html = `
        <div class="detail-row">
            <div class="detail-label"><i class="fas fa-hashtag"></i> ID</div>
            <div class="detail-value"><code>#${log.id}</code></div>
        </div>
        <div class="detail-row">
            <div class="detail-label"><i class="fas fa-clock"></i> Timestamp</div>
            <div class="detail-value">${log.created_at || '-'}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label"><i class="fas fa-building"></i> Developer</div>
            <div class="detail-value"><strong>${log.developer_name || 'Global'}</strong></div>
        </div>
        <div class="detail-row">
            <div class="detail-label"><i class="fas fa-chart-pie"></i> Platform</div>
            <div class="detail-value">
                <span class="platform-badge platform-${log.pixel_type}">
                    <i class="fab fa-${log.pixel_type === 'meta' ? 'facebook' : log.pixel_type}"></i>
                    ${log.pixel_type ? log.pixel_type.toUpperCase() : '-'}
                </span>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-label"><i class="fas fa-tag"></i> Event Name</div>
            <div class="detail-value"><code>${log.event_name || '-'}</code></div>
        </div>
        <div class="detail-row">
            <div class="detail-label"><i class="fas fa-fingerprint"></i> Event ID</div>
            <div class="detail-value"><code>${log.event_id || '-'}</code></div>
        </div>
        <div class="detail-row">
            <div class="detail-label"><i class="fas fa-link"></i> Lead ID</div>
            <div class="detail-value">
                ${log.lead_id ? 
                    `<a href="index.php?search=${log.lead_id}" target="_blank">
                        <i class="fas fa-external-link-alt"></i> #${log.lead_id} ${log.lead_name ? '- ' + log.lead_name : ''}
                    </a>` : '-'}
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-label"><i class="fas fa-circle"></i> Status</div>
            <div class="detail-value">
                <span class="status-badge status-${log.status || 'pending'}">
                    <i class="fas fa-${log.status === 'sent' ? 'check-circle' : (log.status === 'failed' ? 'times-circle' : 'hourglass')}"></i>
                    ${(log.status || 'pending').toUpperCase()}
                </span>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-label"><i class="fas fa-code"></i> Response Code</div>
            <div class="detail-value">
                ${responseCode && responseCode !== '-' ? 
                    `<span style="color: ${responseCode == 200 ? '#2A9D8F' : '#D64F3C'}; font-weight: 700; background: ${responseCode == 200 ? 'rgba(42,157,143,0.1)' : 'rgba(214,79,60,0.1)'}; padding: 4px 12px; border-radius: 30px;">
                        ${responseCode}
                    </span>` : '-'}
            </div>
        </div>

        <h4 style="margin: 20px 0 10px; color: var(--primary);">
            <i class="fas fa-code"></i> Payload
        </h4>
        <div class="json-preview">${payloadHtml}</div>
        
        <h4 style="margin: 20px 0 10px; color: var(--primary);">
            <i class="fas fa-exchange-alt"></i> Response
        </h4>
        <div class="json-preview">${responseHtml}</div>
        
        ${log.status === 'failed' ? 
            `<button class="retry-btn" onclick="retryEvent(${log.id})">
                <i class="fas fa-sync-alt"></i> Retry Event
            </button>` : ''}
    `;
    
    $('#modalContent').html(html);
    $('#detailModal').addClass('show');
}

// ========== RETRY EVENT ==========
function retryEvent(id) {
    if (!confirm('Yakin ingin mengirim ulang event ini?')) return;
    
    fetch('/admin/api/retry_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, api_key: API_KEY }),
        credentials: 'include'
    })
    .then(res => res.json())
    .then(response => {
        if (response.success) {
            alert('Event berhasil di-retry');
            closeModal();
            dataTable.ajax.reload();
        } else {
            alert('Gagal: ' + (response.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error retrying event:', error);
        alert('Error retrying event');
    });
}

// ========== EXPORT DATA ==========
function exportData(format) {
    const params = new URLSearchParams({
        format: format,
        start_date: $('#startDate').val(),
        end_date: $('#endDate').val(),
        platform: $('#platform').val(),
        developer_id: $('#developer').val(),
        status: $('#status').val(),
        search: $('#search').val(),
        api_key: API_KEY
    });
    
    window.location.href = '/admin/api/export_tracking.php?' + params;
}

// ========== TOGGLE FILTER ==========
function toggleFilter() {
    const filterBody = document.getElementById('filterBody');
    const icon = document.querySelector('.filter-toggle-icon');
    
    if (filterBody.style.display === 'none' || getComputedStyle(filterBody).display === 'none') {
        filterBody.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        filterBody.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}

// ========== UPDATE DATETIME ==========
function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', options);
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { hour12: false });
}

// ========== MODAL FUNCTIONS ==========
function closeModal() {
    $('#detailModal').removeClass('show');
    $('#modalContent').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-3x" style="color: var(--secondary);"></i><p style="margin-top: 10px;">Loading...</p></div>');
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Close modal when clicking overlay
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal();
    }
});

// Responsive chart resize
window.addEventListener('resize', function() {
    if (trendChart) trendChart.resize();
    if (platformChart) platformChart.resize();
    if (developerChart) developerChart.resize();
});
</script>

<?php include 'includes/footer.php'; ?>