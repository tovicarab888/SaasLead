<?php
/**
 * MARKETING_EXTERNAL_TEAM.PHP - Kelola Tim Marketing External
 * Version: 13.0.0 - FINAL: Modal fix + Icon horizontal
 */

session_start();
require_once 'api/config.php';

// ===== CEK AKSES: HANYA SUPER ADMIN YANG BISA =====
if (!checkAuth() || !isAdmin()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
if (!$conn) die("Database connection failed");

// ========== PROSES FORM ==========
$success = '';
$error = '';

// ===== SYNC: Tambahkan user marketing_external yang belum ada di marketing_external_team =====
if (isset($_GET['sync'])) {
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("
            SELECT u.id, u.nama_lengkap, u.is_active
            FROM users u
            LEFT JOIN marketing_external_team met ON u.id = met.user_id
            WHERE u.role = 'marketing_external' AND met.id IS NULL
        ");
        $stmt->execute();
        $users_to_sync = $stmt->fetchAll();
        
        $synced = 0;
        foreach ($users_to_sync as $user) {
            $order_stmt = $conn->query("SELECT MAX(round_robin_order) FROM marketing_external_team");
            $max_order = $order_stmt->fetchColumn();
            $new_order = ($max_order === null) ? 0 : $max_order + 1;
            
            $insert = $conn->prepare("
                INSERT INTO marketing_external_team (user_id, super_admin_id, round_robin_order, is_active, created_at)
                VALUES (?, 1, ?, ?, NOW())
            ");
            $insert->execute([$user['id'], $new_order, $user['is_active']]);
            $synced++;
        }
        
        $conn->commit();
        $success = "✅ Berhasil sinkronisasi $synced user marketing external!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "❌ Gagal sinkronisasi: " . $e->getMessage();
    }
}

// ===== TAMBAH MARKETING =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_marketing') {
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $order = (int)($_POST['round_robin_order'] ?? 0);
    
    if (empty($nama) || empty($phone) || empty($username) || empty($password)) {
        $error = "❌ Semua field wajib diisi!";
    } else {
        $phone_validation = validatePhone($phone);
        if (!$phone_validation['valid']) {
            $error = "❌ " . $phone_validation['message'];
        } else {
            try {
                $conn->beginTransaction();
                
                $stmt1 = $conn->prepare("
                    INSERT INTO users (
                        username, email, password, nama_lengkap, contact_phone, 
                        role, is_active, created_at
                    ) VALUES (?, ?, ?, ?, ?, 'marketing_external', 1, NOW())
                ");
                $stmt1->execute([
                    $username, 
                    $email, 
                    password_hash($password, PASSWORD_DEFAULT), 
                    $nama, 
                    $phone_validation['number']
                ]);
                
                $user_id = $conn->lastInsertId();
                
                $stmt2 = $conn->prepare("
                    INSERT INTO marketing_external_team (user_id, super_admin_id, round_robin_order, is_active, created_at) 
                    VALUES (?, 1, ?, 1, NOW())
                ");
                $stmt2->execute([$user_id, $order]);
                
                $conn->commit();
                $success = "✅ Marketing berhasil ditambahkan!";
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    }
}

// ===== EDIT MARKETING =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_marketing') {
    $external_id = (int)($_POST['external_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $order = (int)($_POST['round_robin_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($external_id <= 0 || $user_id <= 0 || empty($nama) || empty($username)) {
        $error = "❌ Data tidak valid!";
    } else {
        $phone_validation = validatePhone($phone);
        if (!$phone_validation['valid']) {
            $error = "❌ " . $phone_validation['message'];
        } else {
            try {
                $conn->beginTransaction();
                
                $update_user = $conn->prepare("
                    UPDATE users SET 
                        username = ?,
                        email = ?,
                        nama_lengkap = ?,
                        contact_phone = ?,
                        is_active = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update_user->execute([
                    $username, $email, $nama, $phone_validation['number'], $is_active, $user_id
                ]);
                
                if (!empty($_POST['password'])) {
                    $pass_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $conn->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$pass_hash, $user_id]);
                }
                
                $update_team = $conn->prepare("
                    UPDATE marketing_external_team SET 
                        round_robin_order = ?,
                        is_active = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update_team->execute([$order, $is_active, $external_id]);
                
                $conn->commit();
                $success = "✅ Marketing berhasil diupdate!";
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "❌ Gagal update: " . $e->getMessage();
            }
        }
    }
}

// ===== UPDATE ORDER =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order') {
    if (isset($_POST['order']) && is_array($_POST['order'])) {
        try {
            $conn->beginTransaction();
            foreach ($_POST['order'] as $id => $order) {
                $update = $conn->prepare("UPDATE marketing_external_team SET round_robin_order = ? WHERE id = ?");
                $update->execute([(int)$order, (int)$id]);
            }
            $conn->commit();
            $success = "✅ Urutan berhasil diupdate!";
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "❌ Gagal: " . $e->getMessage();
        }
    }
}

// ===== HAPUS (Nonaktifkan) =====
if (isset($_GET['delete']) && (int)$_GET['delete'] > 0) {
    $id = (int)$_GET['delete'];
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("SELECT user_id FROM marketing_external_team WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();
        
        if ($user_id) {
            $conn->prepare("UPDATE marketing_external_team SET is_active = 0, updated_at = NOW() WHERE id = ?")->execute([$id]);
            $conn->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?")->execute([$user_id]);
        }
        
        $conn->commit();
        $success = "✅ Marketing berhasil dinonaktifkan!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "❌ Gagal nonaktifkan: " . $e->getMessage();
    }
}

// ===== AKTIFKAN =====
if (isset($_GET['activate']) && (int)$_GET['activate'] > 0) {
    $id = (int)$_GET['activate'];
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("SELECT user_id FROM marketing_external_team WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();
        
        if ($user_id) {
            $conn->prepare("UPDATE marketing_external_team SET is_active = 1, updated_at = NOW() WHERE id = ?")->execute([$id]);
            $conn->prepare("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ?")->execute([$user_id]);
        }
        
        $conn->commit();
        $success = "✅ Marketing berhasil diaktifkan!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "❌ Gagal mengaktifkan: " . $e->getMessage();
    }
}

// ===== TOGGLE STATUS =====
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->prepare("UPDATE marketing_external_team SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    header('Location: marketing_external_team.php');
    exit();
}

// ========== AMBIL DATA ==========
$team = $conn->query("
    SELECT 
        met.id as external_id,
        met.user_id,
        met.round_robin_order,
        met.last_assigned,
        met.is_active as external_active,
        met.created_at as external_created,
        u.id,
        u.username,
        u.nama_lengkap,
        u.email,
        u.contact_phone as phone,
        u.is_active as user_active,
        u.last_login,
        u.created_at,
        COUNT(l.id) as total_leads,
        SUM(CASE WHEN l.status IN ('Deal KPR','Deal Tunai','Deal Bertahap 6 Bulan','Deal Bertahap 1 Tahun') THEN 1 ELSE 0 END) as total_deal
    FROM marketing_external_team met
    JOIN users u ON met.user_id = u.id
    LEFT JOIN leads l ON l.assigned_marketing_team_id = u.id AND l.assigned_type = 'external' AND (l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')
    WHERE u.role = 'marketing_external'
    GROUP BY met.id, u.id
    ORDER BY 
        met.round_robin_order ASC,
        u.nama_lengkap ASC
")->fetchAll();

$unregistered = $conn->query("
    SELECT u.*, u.contact_phone as phone
    FROM users u
    LEFT JOIN marketing_external_team met ON u.id = met.user_id
    WHERE met.id IS NULL 
      AND u.role = 'marketing_external' 
      AND u.is_active = 1
    ORDER BY u.nama_lengkap
")->fetchAll();

$total_marketing = count($team);
$total_unregistered = count($unregistered);
$total_leads = $conn->query("SELECT COUNT(*) FROM leads WHERE assigned_type = 'external' AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')")->fetchColumn();
$total_deals = $conn->query("SELECT COUNT(*) FROM leads WHERE assigned_type = 'external' AND status LIKE 'Deal%' AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')")->fetchColumn();

// ========== SET VARIABLES ==========
$page_title = 'Marketing External Team';
$page_subtitle = 'Kelola Tim Marketing External';
$page_icon = 'fas fa-user-tie';

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

/* ===== RESET ===== */
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
}

.main-content {
    width: 100%;
    min-height: 100vh;
    padding: 12px;
    margin-left: 0 !important;
}

/* ===== TOP BAR ===== */
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

/* ===== ALERT ===== */
.alert {
    padding: 16px 20px;
    border-radius: 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
}

.alert.success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid var(--danger);
}

/* ===== STATS CARDS ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 16px 12px;
    border-left: 4px solid var(--secondary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.stat-icon {
    font-size: 24px;
    color: var(--secondary);
    margin-bottom: 8px;
}

.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 22px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

.stat-small {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
}

/* ===== ACTION BAR ===== */
.action-bar {
    margin-bottom: 24px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-add {
    flex: 1;
    min-width: 200px;
    padding: 16px;
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 8px 20px rgba(214,79,60,0.3);
    transition: all 0.3s;
    text-decoration: none;
}

.btn-sync {
    flex: 0 0 auto;
    padding: 16px 24px;
    background: var(--info);
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 8px 20px rgba(74,144,226,0.3);
    text-decoration: none;
}

/* ===== WARNING CARD ===== */
.warning-card {
    background: #fff3cd;
    border-left: 6px solid #ffc107;
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #856404;
}

.warning-card i {
    font-size: 24px;
    color: #ffc107;
}

/* ===== TABLE CARD ===== */
.table-card {
    background: white;
    border-radius: 24px;
    padding: 20px;
    border: 1px solid var(--border);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
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
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-header h3 i {
    color: var(--secondary);
}

.table-badge {
    background: var(--primary-soft);
    color: var(--primary);
    padding: 6px 16px;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 600;
}

/* ===== TABLE RESPONSIVE ===== */
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
    min-width: 1000px;
}

th {
    text-align: left;
    padding: 14px 12px;
    background: var(--primary-soft);
    color: var(--primary);
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

td {
    padding: 16px 12px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: middle;
}

.order-input {
    width: 70px;
    padding: 10px;
    border: 2px solid var(--border);
    border-radius: 10px;
    text-align: center;
    font-weight: 600;
}

.order-input:focus {
    border-color: var(--secondary);
    outline: none;
}

/* ===== STATUS BADGE ===== */
.status-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

.status-badge.active {
    background: var(--success);
}

.status-badge.inactive {
    background: var(--danger);
}

/* ===== ACTION BUTTONS HORIZONTAL ===== */
.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
    align-items: center;
    flex-direction: row;
    flex-wrap: nowrap;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    border: 1px solid;
    background: white;
    flex-shrink: 0;
}

.action-btn.edit {
    background: #fff8e1;
    color: #ff8f00;
    border-color: #ff8f00;
}

.action-btn.edit:hover {
    background: #ff8f00;
    color: white;
}

.action-btn.delete {
    background: #ffebee;
    color: #d32f2f;
    border-color: #d32f2f;
}

.action-btn.delete:hover {
    background: #d32f2f;
    color: white;
}

.action-btn.activate {
    background: #e8f5e9;
    color: #2e7d32;
    border-color: #2e7d32;
}

.action-btn.activate:hover {
    background: #2e7d32;
    color: white;
}

/* ===== BUTTON SAVE ===== */
.btn-save {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, var(--success), #40BEB0);
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
}

/* ===== UNREGISTERED CARD ===== */
.unregistered-card {
    background: var(--primary-soft);
    border-radius: 20px;
    padding: 16px;
    margin-bottom: 20px;
    border: 1px dashed var(--secondary);
}

.unregistered-title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--primary);
    font-weight: 700;
    margin-bottom: 12px;
}

.unregistered-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.unregistered-item {
    background: white;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid var(--border);
}

.unregistered-item i {
    color: var(--secondary);
}

/* ===== INFO CARD ===== */
.info-card {
    background: var(--primary-soft);
    border-radius: 20px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.info-icon {
    width: 48px;
    height: 48px;
    background: var(--secondary);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.info-text strong {
    color: var(--primary);
    display: block;
    margin-bottom: 4px;
}

.info-text p {
    color: var(--text-light);
    font-size: 13px;
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

/* ===== MODAL ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(10px);
    align-items: center;
    justify-content: center;
    z-index: 999999;
    padding: 20px;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 30px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalIn 0.3s ease;
}

@keyframes modalIn {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 2px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
}

.modal-header h2 {
    font-size: 20px;
    font-weight: 800;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-header h2 i {
    color: var(--secondary);
}

.modal-close {
    width: 40px;
    height: 40px;
    background: var(--bg);
    border: none;
    border-radius: 12px;
    color: var(--secondary);
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: var(--danger);
    color: white;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 2px solid var(--border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    background: #fafafa;
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

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--border);
    border-radius: 14px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
}

.form-control:focus {
    border-color: var(--secondary);
    outline: none;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--primary-soft);
    border-radius: 14px;
}

.checkbox-group input {
    width: 20px;
    height: 20px;
    accent-color: var(--secondary);
}

.btn-primary {
    background: var(--secondary);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 40px;
    font-weight: 600;
    cursor: pointer;
}

.btn-secondary {
    background: var(--bg);
    color: var(--text);
    border: 1px solid var(--border);
    padding: 12px 24px;
    border-radius: 40px;
    font-weight: 600;
    cursor: pointer;
}

/* ===== DESKTOP ===== */
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
}
</style>
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
            <div class="date"><i class="fas fa-calendar-alt"></i> <span id="date"></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span id="time"></span></div>
        </div>
    </div>
    
    <!-- ALERT MESSAGES -->
    <?php if ($success): ?>
    <div class="alert success" style="background: #d4edda; color: #155724; padding: 16px 20px; border-radius: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; border-left: 4px solid #28a745;">
        <i class="fas fa-check-circle fa-lg"></i>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert error" style="background: #f8d7da; color: #721c24; padding: 16px 20px; border-radius: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; border-left: 4px solid #dc3545;">
        <i class="fas fa-exclamation-circle fa-lg"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>
    
    <!-- STATS CARDS - WARNA MERAH -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Terdaftar</div>
            <div class="stat-value"><?= $total_marketing ?></div>
            <div class="stat-small">di marketing_external_team</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--info);">
            <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
            <div class="stat-label">Belum Terdaftar</div>
            <div class="stat-value"><?= $total_unregistered ?></div>
            <div class="stat-small">di users (role external)</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-label">Total Leads</div>
            <div class="stat-value"><?= number_format($total_leads) ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
            <div class="stat-label">Total Deal</div>
            <div class="stat-value"><?= number_format($total_deals) ?></div>
        </div>
    </div>
    
    <!-- WARNING CARD (jika ada yang belum terdaftar) -->
    <?php if ($total_unregistered > 0): ?>
    <div class="warning-card">
        <i class="fas fa-exclamation-triangle"></i>
        <p>Terdapat <strong><?= $total_unregistered ?></strong> user dengan role marketing_external yang belum terdaftar di tabel marketing_external_team. Klik tombol Sync untuk mendaftarkan mereka.</p>
    </div>
    <?php endif; ?>
    
    <!-- ACTION BUTTONS -->
    <div class="action-bar">
        <button onclick="openAddModal()" class="btn-add">
            <i class="fas fa-plus-circle"></i> Tambah Marketing External
        </button>
        <a href="?sync=1" class="btn-sync" onclick="return confirm('Sinkronisasi semua user marketing_external yang belum terdaftar?')">
            <i class="fas fa-sync-alt"></i> Sync Users
        </a>
    </div>
    
    <!-- TABLE CARD -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-users" style="color: var(--secondary);"></i> Daftar Marketing External</h3>
            <div class="table-badge">
                <i class="fas fa-database"></i> Total: <?= count($team) ?>
            </div>
        </div>
        
        <?php if (empty($team)): ?>
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <p>Belum ada marketing external terdaftar</p>
            <button onclick="openAddModal()" class="btn-add" style="display: inline-flex;">
                <i class="fas fa-plus-circle"></i> Tambah Sekarang
            </button>
        </div>
        <?php else: ?>
        <form method="POST" id="orderForm">
            <input type="hidden" name="action" value="update_order">
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Kontak</th>
                            <th>Round Robin</th>
                            <th>Total Leads</th>
                            <th>Total Deal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team as $i => $t): 
                            $last_login = $t['last_login'] ? date('d/m/Y H:i', strtotime($t['last_login'])) : '-';
                        ?>
                        <tr>
                            <td><strong>#<?= $t['external_id'] ?></strong><br><small>User: <?= $t['user_id'] ?></small></td>
                            <td>
                                <strong style="color: var(--primary);"><?= htmlspecialchars($t['nama_lengkap']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($t['email']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($t['username']) ?></td>
                            <td>
                                <?php if (!empty($t['phone'])): ?>
                                <span style="color: var(--primary);"><?= htmlspecialchars($t['phone']) ?></span>
                                <?php else: ?>
                                <span style="color: #7A8A84;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number" name="order[<?= $t['external_id'] ?>]" 
                                       value="<?= $t['round_robin_order'] ?>" 
                                       class="order-input" min="0">
                            </td>
                            <td style="font-weight: 600;"><?= number_format($t['total_leads'] ?? 0) ?></td>
                            <td>
                                <span style="background: var(--success); color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px;">
                                    <?= $t['total_deal'] ?? 0 ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $is_active = ($t['user_active'] && $t['external_active']);
                                ?>
                                <span class="status-badge <?= $is_active ? 'active' : 'inactive' ?>">
                                    <?= $is_active ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <!-- EDIT BUTTON -->
                                    <button class="action-btn edit" onclick='editMarketing(<?= json_encode([
                                        'external_id' => $t['external_id'],
                                        'user_id' => $t['user_id'],
                                        'nama_lengkap' => $t['nama_lengkap'],
                                        'username' => $t['username'],
                                        'email' => $t['email'],
                                        'phone' => $t['phone'],
                                        'round_robin_order' => $t['round_robin_order'],
                                        'is_active' => ($t['user_active'] && $t['external_active'])
                                    ]) ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <!-- TOGGLE STATUS -->
                                    <?php if ($is_active): ?>
                                    <a href="?delete=<?= $t['external_id'] ?>" class="action-btn delete" 
                                       onclick="return confirm('Nonaktifkan marketing <?= htmlspecialchars($t['nama_lengkap']) ?>?')" 
                                       title="Nonaktifkan">
                                        <i class="fas fa-ban"></i>
                                    </a>
                                    <?php else: ?>
                                    <a href="?activate=<?= $t['external_id'] ?>" class="action-btn activate" 
                                       onclick="return confirm('Aktifkan marketing <?= htmlspecialchars($t['nama_lengkap']) ?>?')" 
                                       title="Aktifkan">
                                        <i class="fas fa-check-circle"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <!-- HAPUS PERMANEN (hidden, bisa diaktifkan jika perlu) 
                                    <a href="?delete_permanent=<?= $t['external_id'] ?>&confirm=yes" class="action-btn delete" 
                                       onclick="return confirm('HAPUS PERMANEN marketing <?= htmlspecialchars($t['nama_lengkap']) ?>? Semua data terkait akan hilang!')" 
                                       title="Hapus Permanen">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    -->
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> Simpan Urutan Round Robin
            </button>
        </form>
        <?php endif; ?>
    </div>
    
    <!-- UNREGISTERED USERS CARD -->
    <?php if (!empty($unregistered)): ?>
    <div class="unregistered-card">
        <div class="unregistered-title">
            <i class="fas fa-user-clock"></i>
            <span>User Marketing External Belum Terdaftar di Team (<?= count($unregistered) ?>)</span>
        </div>
        <div class="unregistered-list">
            <?php foreach ($unregistered as $u): ?>
            <div class="unregistered-item">
                <i class="fas fa-user"></i>
                <?= htmlspecialchars($u['nama_lengkap']) ?> (<?= htmlspecialchars($u['username']) ?>)
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- INFO CARD - DENGAN ICON MERAH -->
    <div class="info-card">
        <div class="info-icon" style="background: var(--secondary);">
            <i class="fas fa-info-circle"></i>
        </div>
        <div class="info-text">
            <strong>Informasi Round Robin & Sinkronisasi</strong>
            <p>Leads external akan didistribusikan secara bergantian berdasarkan urutan Round Robin (semakin kecil angka, semakin prioritas). Gunakan tombol Sync untuk mendaftarkan user marketing_external yang sudah ada di tabel users ke tabel marketing_external_team.</p>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Marketing External Team v12.0</p>
    </div>
    
</div>

<!-- MODAL TAMBAH - DENGAN ICON MERAH -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle" style="color: var(--secondary);"></i> Tambah Marketing External</h2>
            <button class="modal-close" onclick="closeAddModal()">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_marketing">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-user" style="color: var(--secondary);"></i> Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" class="form-control" required placeholder="Contoh: Budi Santoso">
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-whatsapp" style="color: var(--secondary);"></i> Nomor WhatsApp *</label>
                    <input type="tel" name="phone" class="form-control" required placeholder="628123456789">
                    <small style="color: var(--text-muted);">Format: 628xxxxxxxxx</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope" style="color: var(--secondary);"></i> Email</label>
                    <input type="email" name="email" class="form-control" placeholder="email@domain.com">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-circle" style="color: var(--secondary);"></i> Username *</label>
                    <input type="text" name="username" class="form-control" required placeholder="username">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock" style="color: var(--secondary);"></i> Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sort-numeric-down" style="color: var(--secondary);"></i> Round Robin Order</label>
                    <input type="number" name="round_robin_order" class="form-control" value="0" min="0">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddModal()">Batal</button>
                <button type="submit" class="btn-primary">Tambah Marketing</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT - DENGAN ICON MERAH -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit" style="color: var(--secondary);"></i> Edit Marketing External</h2>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="edit_marketing">
            <input type="hidden" name="external_id" id="edit_external_id" value="">
            <input type="hidden" name="user_id" id="edit_user_id" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-user" style="color: var(--secondary);"></i> Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-whatsapp" style="color: var(--secondary);"></i> Nomor WhatsApp *</label>
                    <input type="tel" name="phone" id="edit_phone" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope" style="color: var(--secondary);"></i> Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-circle" style="color: var(--secondary);"></i> Username *</label>
                    <input type="text" name="username" id="edit_username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock" style="color: var(--secondary);"></i> Password (kosongkan jika tidak diubah)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sort-numeric-down" style="color: var(--secondary);"></i> Round Robin Order</label>
                    <input type="number" name="round_robin_order" id="edit_order" class="form-control" min="0">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                    <label for="edit_is_active">Aktif</label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Batal</button>
                <button type="submit" class="btn-primary">Update Marketing</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL HAPUS PREMIUM (Nonaktifkan) -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Konfirmasi Nonaktifkan</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div style="width: 80px; height: 80px; background: rgba(214,79,60,0.1); border-radius: 30px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; border: 3px solid var(--danger);">
                <i class="fas fa-ban" style="font-size: 40px; color: var(--danger);"></i>
            </div>
            
            <h3 style="font-size: 24px; font-weight: 800; color: var(--danger); margin-bottom: 8px;">Nonaktifkan Marketing?</h3>
            <p style="color: var(--text-muted); margin-bottom: 20px;">Marketing akan dinonaktifkan dan tidak bisa login</p>
            
            <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; font-weight: 700; color: var(--primary); margin-bottom: 20px; border: 2px dashed var(--danger);" id="deleteName">
                Loading...
            </div>
            
            <div style="background: #fff3cd; border-radius: 12px; padding: 16px; display: flex; align-items: flex-start; gap: 12px; margin-bottom: 20px; border-left: 4px solid #ffc107; text-align: left;">
                <i class="fas fa-exclamation-circle" style="font-size: 24px; color: #ffc107;"></i>
                <div>
                    <strong style="color: #856404;">Peringatan!</strong>
                    <p style="margin: 5px 0 0; font-size: 13px; color: #856404;">Marketing akan dinonaktifkan dan tidak bisa login. Semua data leads tetap tersimpan.</p>
                </div>
            </div>
            
            <input type="hidden" id="deleteId">
            
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button class="btn-secondary" onclick="closeDeleteModal()" style="flex: 1;">Batal</button>
                <a href="#" class="btn-primary" id="confirmDeleteBtn" style="flex: 1; background: var(--danger);">Nonaktifkan</a>
            </div>
        </div>
    </div>
</div>

<script>
// ===== MODAL FUNCTIONS =====
function openAddModal() {
    document.getElementById('addModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('show');
    document.body.style.overflow = '';
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
    document.body.style.overflow = '';
}

function showDeleteModal(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name;
    document.getElementById('confirmDeleteBtn').href = '?delete=' + id;
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== EDIT FUNCTION =====
function editMarketing(data) {
    console.log('Edit data:', data);
    
    // Pastikan modal tidak tertutup
    event.preventDefault();
    event.stopPropagation();
    
    document.getElementById('edit_external_id').value = data.external_id || '';
    document.getElementById('edit_user_id').value = data.user_id || '';
    document.getElementById('edit_nama').value = data.nama_lengkap || '';
    document.getElementById('edit_phone').value = data.phone || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_username').value = data.username || '';
    document.getElementById('edit_order').value = data.round_robin_order || 0;
    document.getElementById('edit_is_active').checked = data.is_active == 1 || data.is_active === true;
    
    // Tampilkan modal
    document.getElementById('editModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    
    return false; // Mencegah default action
}

// ===== DELETE BUTTON HANDLER =====
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.dataset.id;
        const name = this.dataset.name;
        showDeleteModal(id, name);
    });
});

// ===== TOAST FUNCTION =====
function showToast(message, type = 'success') {
    let toast = document.querySelector('.toast-message');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-message';
        document.body.appendChild(toast);
    }
    
    toast.className = `toast-message ${type}`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) toast.remove();
        }, 300);
    }, 3000);
}

// ===== DATE TIME =====
function updateDateTime() {
    const now = new Date();
    document.getElementById('date').textContent = now.toLocaleDateString('id-ID', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    document.getElementById('time').textContent = now.toLocaleTimeString('id-ID', { 
        hour12: false 
    });
}

setInterval(updateDateTime, 1000);
updateDateTime();

// ===== CLOSE MODAL ON OUTSIDE CLICK =====
document.addEventListener('click', function(e) {
    // Hanya tutup jika klik di backdrop (bukan di modal-content)
    if (e.target.classList.contains('modal')) {
        // Cek apakah yang diklik adalah backdrop (bukan children)
        if (e.target === document.getElementById('editModal') || 
            e.target === document.getElementById('addModal') || 
            e.target === document.getElementById('deleteModal')) {
            e.target.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
});

// ===== CONFIRM BEFORE LEAVE =====
let orderChanged = false;
document.querySelectorAll('.order-input').forEach(input => {
    input.addEventListener('change', () => {
        orderChanged = true;
    });
});

window.addEventListener('beforeunload', function(e) {
    if (orderChanged) {
        e.preventDefault();
        e.returnValue = 'Ada perubahan urutan yang belum disimpan. Yakin ingin keluar?';
    }
});

// Expose functions
window.openAddModal = openAddModal;
window.closeAddModal = closeAddModal;
window.closeEditModal = closeEditModal;
window.closeDeleteModal = closeDeleteModal;
window.editMarketing = editMarketing;
</script>

<?php include 'includes/footer.php'; ?>