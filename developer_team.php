<?php
/**
 * DEVELOPER_TEAM.PHP - LEADENGINE
 * Version: 5.0.0 - FIXED: Dropdown tanpa Rp 0, Email error, Format Rupiah, Keypad Mobile
 * MOBILE FIRST UI - CRUD LENGKAP DENGAN VALIDASI
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

// ========== AMBIL DATA MARKETING TYPES - HANYA UNTUK INTERNAL ==========
$marketing_types = [];
try {
    // Hanya ambil tipe untuk internal (sales_inhouse dan sales_canvasing)
    $stmt = $conn->prepare("
        SELECT id, type_name, commission_type 
        FROM marketing_types 
        WHERE type_name IN ('sales_inhouse', 'sales_canvasing')
        ORDER BY FIELD(type_name, 'sales_inhouse', 'sales_canvasing')
    ");
    $stmt->execute();
    $marketing_types = $stmt->fetchAll();
    
    // Jika masih kosong, beri default
    if (empty($marketing_types)) {
        $marketing_types = [
            ['id' => 1, 'type_name' => 'sales_inhouse', 'commission_type' => 'INTERNAL_FIXED'],
            ['id' => 2, 'type_name' => 'sales_canvasing', 'commission_type' => 'INTERNAL_PERCENT']
        ];
    }
} catch (Exception $e) {
    error_log("Error loading marketing types: " . $e->getMessage());
    $marketing_types = [
        ['id' => 1, 'type_name' => 'sales_inhouse', 'commission_type' => 'INTERNAL_FIXED'],
        ['id' => 2, 'type_name' => 'sales_canvasing', 'commission_type' => 'INTERNAL_PERCENT']
    ];
}

// ========== PROSES CRUD ==========
$success = '';
$error = '';

// Hapus user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    $check = $conn->prepare("
        SELECT id, role FROM users 
        WHERE id = ? AND developer_id = ? AND role IN ('manager_developer', 'finance', 'marketing')
    ");
    $check->execute([$delete_id, $developer_id]);
    $user = $check->fetch();
    
    if ($user) {
        try {
            $conn->beginTransaction();
            
            if ($user['role'] == 'marketing') {
                $delete_marketing = $conn->prepare("DELETE FROM marketing_team WHERE user_id = ?");
                $delete_marketing->execute([$delete_id]);
            }
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$delete_id]);
            
            $conn->commit();
            $success = "✅ User berhasil dihapus!";
            logSystem("Team member deleted", ['id' => $delete_id, 'by' => $developer_id], 'INFO', 'team.log');
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    } else {
        $error = "❌ User tidak ditemukan atau bukan bawahan Anda";
    }
}

// Reset password
if (isset($_GET['reset']) && is_numeric($_GET['reset'])) {
    $reset_id = (int)$_GET['reset'];
    $reset_key = $_GET['key'] ?? '';
    
    if ($reset_key === API_KEY) {
        $check = $conn->prepare("
            SELECT id FROM users 
            WHERE id = ? AND developer_id = ? AND role IN ('manager_developer', 'finance', 'marketing')
        ");
        $check->execute([$reset_id, $developer_id]);
        
        if ($check->fetch()) {
            $new_password = 'password123';
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed, $reset_id]);
            
            $success = "✅ Password berhasil direset menjadi 'password123'!";
            logSystem("Team password reset", ['id' => $reset_id], 'INFO', 'team.log');
        } else {
            $error = "❌ User tidak ditemukan atau bukan bawahan Anda";
        }
    } else {
        $error = "❌ Key tidak valid untuk reset password";
    }
}

// Tambah/Edit user
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add' || $action == 'edit') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $marketing_type_id = isset($_POST['marketing_type_id']) ? (int)$_POST['marketing_type_id'] : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validasi role
        if (!in_array($role, ['manager_developer', 'finance', 'marketing'])) {
            $error = "❌ Role tidak valid";
        } elseif (empty($nama_lengkap) || empty($username) || empty($email)) {
            $error = "❌ Nama lengkap, username, dan email wajib diisi!";
        } elseif ($action == 'add' && empty($password)) {
            $error = "❌ Password wajib diisi untuk user baru!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "❌ Format email tidak valid!";
        } elseif ($role == 'marketing' && $marketing_type_id <= 0) {
            $error = "❌ Tipe marketing wajib dipilih!";
        } else {
            // Validasi nomor WhatsApp
            $phone_clean = null;
            if (!empty($phone)) {
                $phone_clean = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($phone_clean) < 10 || strlen($phone_clean) > 15) {
                    $error = "❌ Nomor WhatsApp harus 10-15 digit!";
                } elseif (substr($phone_clean, 0, 1) == '0') {
                    $phone_clean = '62' . substr($phone_clean, 1);
                } elseif (substr($phone_clean, 0, 2) != '62') {
                    $phone_clean = '62' . $phone_clean;
                }
            }
            
            if (empty($error)) {
                try {
                    $conn->beginTransaction();
                    
                    if ($action == 'add') {
                        // Cek duplikat username
                        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
                        $check->execute([$username]);
                        if ($check->fetch()) {
                            throw new Exception("Username sudah digunakan");
                        }
                        
                        // Cek duplikat email
                        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                        $check->execute([$email]);
                        if ($check->fetch()) {
                            throw new Exception("Email sudah digunakan");
                        }
                        
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert ke users
                        $stmt = $conn->prepare("
                            INSERT INTO users (
                                username, email, password, nama_lengkap, role, 
                                developer_id, phone, is_active, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $username, $email, $hashed, $nama_lengkap, $role,
                            $developer_id, $phone_clean, $is_active
                        ]);
                        
                        $new_user_id = $conn->lastInsertId();
                        
                        // Jika role marketing, insert juga ke marketing_team
                        if ($role == 'marketing') {
                            // 🔥 PERBAIKAN: Hapus kolom 'email' dari query karena tidak ada di tabel marketing_team
                            $marketing_stmt = $conn->prepare("
                                INSERT INTO marketing_team (
                                    user_id, developer_id, nama_lengkap, phone, username,
                                    marketing_type_id, is_active, created_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $marketing_stmt->execute([
                                $new_user_id,
                                $developer_id,
                                $nama_lengkap,
                                $phone_clean,
                                $username,
                                $marketing_type_id,
                                $is_active
                            ]);
                        }
                        
                        $role_name = $role == 'manager_developer' ? 'Manager Developer' : ($role == 'finance' ? 'Finance' : 'Marketing');
                        $success = "✅ $role_name berhasil ditambahkan!";
                        logSystem("Team member added", ['username' => $username, 'role' => $role], 'INFO', 'team.log');
                        
                    } else {
                        // Edit user - cek kepemilikan
                        $check = $conn->prepare("
                            SELECT id, role FROM users 
                            WHERE id = ? AND developer_id = ? AND role IN ('manager_developer', 'finance', 'marketing')
                        ");
                        $check->execute([$id, $developer_id]);
                        $existing = $check->fetch();
                        
                        if (!$existing) {
                            throw new Exception("User tidak ditemukan atau bukan bawahan Anda");
                        }
                        
                        $old_role = $existing['role'];
                        
                        // Cek duplikat username
                        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                        $check->execute([$username, $id]);
                        if ($check->fetch()) {
                            throw new Exception("Username sudah digunakan");
                        }
                        
                        // Cek duplikat email
                        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $check->execute([$email, $id]);
                        if ($check->fetch()) {
                            throw new Exception("Email sudah digunakan");
                        }
                        
                        if (!empty($password)) {
                            $hashed = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("
                                UPDATE users SET 
                                    username = ?, email = ?, password = ?,
                                    nama_lengkap = ?, role = ?, phone = ?, is_active = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([$username, $email, $hashed, $nama_lengkap, $role, $phone_clean, $is_active, $id]);
                        } else {
                            $stmt = $conn->prepare("
                                UPDATE users SET 
                                    username = ?, email = ?,
                                    nama_lengkap = ?, role = ?, phone = ?, is_active = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([$username, $email, $nama_lengkap, $role, $phone_clean, $is_active, $id]);
                        }
                        
                        // Handle perubahan role
                        if ($old_role != $role) {
                            if ($role == 'marketing' && $old_role != 'marketing') {
                                // 🔥 PERBAIKAN: Hapus kolom 'email'
                                $marketing_stmt = $conn->prepare("
                                    INSERT INTO marketing_team (
                                        user_id, developer_id, nama_lengkap, phone, username,
                                        marketing_type_id, is_active, created_at
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                                ");
                                $marketing_stmt->execute([
                                    $id,
                                    $developer_id,
                                    $nama_lengkap,
                                    $phone_clean,
                                    $username,
                                    $marketing_type_id,
                                    $is_active
                                ]);
                            }
                            elseif ($old_role == 'marketing' && $role != 'marketing') {
                                $delete_marketing = $conn->prepare("DELETE FROM marketing_team WHERE user_id = ?");
                                $delete_marketing->execute([$id]);
                            }
                        } 
                        elseif ($role == 'marketing') {
                            $check_marketing = $conn->prepare("SELECT id FROM marketing_team WHERE user_id = ?");
                            $check_marketing->execute([$id]);
                            
                            if ($check_marketing->fetch()) {
                                // 🔥 PERBAIKAN: Hapus kolom 'email'
                                $update_marketing = $conn->prepare("
                                    UPDATE marketing_team SET
                                        nama_lengkap = ?, phone = ?, username = ?,
                                        marketing_type_id = ?, is_active = ?, updated_at = NOW()
                                    WHERE user_id = ?
                                ");
                                $update_marketing->execute([
                                    $nama_lengkap, $phone_clean, $username,
                                    $marketing_type_id, $is_active, $id
                                ]);
                            } else {
                                // 🔥 PERBAIKAN: Hapus kolom 'email'
                                $insert_marketing = $conn->prepare("
                                    INSERT INTO marketing_team (
                                        user_id, developer_id, nama_lengkap, phone, username,
                                        marketing_type_id, is_active, created_at
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                                ");
                                $insert_marketing->execute([
                                    $id, $developer_id, $nama_lengkap, $phone_clean, $username,
                                    $marketing_type_id, $is_active
                                ]);
                            }
                        }
                        
                        $role_name = $role == 'manager_developer' ? 'Manager Developer' : ($role == 'finance' ? 'Finance' : 'Marketing');
                        $success = "✅ $role_name berhasil diupdate!";
                        logSystem("Team member updated", ['id' => $id], 'INFO', 'team.log');
                    }
                    
                    $conn->commit();
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = "❌ Gagal: " . $e->getMessage();
                }
            }
        }
    }
}

// Ambil data team (manager_developer, finance, marketing)
$team = [];
$stmt = $conn->prepare("
    SELECT u.*, 
           mt.marketing_type_id,
           t.type_name as marketing_type_name,
           t.commission_type
    FROM users u
    LEFT JOIN marketing_team mt ON u.id = mt.user_id
    LEFT JOIN marketing_types t ON mt.marketing_type_id = t.id
    WHERE u.developer_id = ? AND u.role IN ('manager_developer', 'finance', 'marketing')
    ORDER BY 
        CASE u.role 
            WHEN 'manager_developer' THEN 1 
            WHEN 'finance' THEN 2 
            WHEN 'marketing' THEN 3
            ELSE 4 
        END, 
        u.nama_lengkap ASC
");
$stmt->execute([$developer_id]);
$team = $stmt->fetchAll();

// Hitung statistik
$total_manager = 0;
$total_finance = 0;
$total_marketing = 0;
$total_active = 0;

foreach ($team as $t) {
    if ($t['role'] == 'manager_developer') $total_manager++;
    if ($t['role'] == 'finance') $total_finance++;
    if ($t['role'] == 'marketing') $total_marketing++;
    if ($t['is_active']) $total_active++;
}

$page_title = 'Tim Manajemen & Marketing';
$page_subtitle = 'Kelola Manager, Finance, dan Marketing Internal';
$page_icon = 'fas fa-users-cog';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- ===== CSS (SAMA PERSIS DENGAN SEBELUMNYA) ===== -->
<style>
/* [CSS LENGKAP SAMA PERSIS DENGAN FILE ASLI ANDA] */
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

.stats-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    padding: 4px 0 16px 0;
    margin-bottom: 16px;
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

.info-card {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
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
    color: #E3B584;
    background: rgba(255,255,255,0.1);
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
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
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

.role-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
}

.role-badge.manager_developer {
    background: #4A90E2;
    color: white;
}

.role-badge.finance {
    background: #2A9D8F;
    color: white;
}

.role-badge.marketing {
    background: #D64F3C;
    color: white;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
}

.status-badge.active {
    background: var(--success);
    color: white;
}

.status-badge.inactive {
    background: var(--danger);
    color: white;
}

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

.action-btn.edit {
    background: #fff8e1;
    color: #B87C00;
    border-color: #B87C00;
}

.action-btn.edit:hover {
    background: #B87C00;
    color: white;
}

.action-btn.reset {
    background: var(--primary-soft);
    color: var(--primary);
    border-color: var(--primary);
}

.action-btn.reset:hover {
    background: var(--primary);
    color: white;
}

.action-btn.delete {
    background: #ffeeed;
    color: var(--danger);
    border-color: var(--danger);
}

.action-btn.delete:hover {
    background: var(--danger);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 20px;
}

.empty-state i {
    font-size: 60px;
    color: #E0DAD3;
    margin-bottom: 16px;
}

.empty-state h4 {
    color: var(--text);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--text-muted);
    margin-bottom: 20px;
}

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

/* ===== PASSWORD INPUT WITH TOGGLE ===== */
.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.password-wrapper input {
    width: 100%;
    padding-right: 50px;
}

.toggle-password {
    position: absolute;
    right: 10px;
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: color 0.2s;
}

.toggle-password:hover {
    color: var(--secondary);
}

.toggle-password i {
    pointer-events: none;
}

/* ===== INPUT DENGAN FORMAT RUPIAH ===== */
.rupiah-input {
    text-align: right;
    -webkit-appearance: none;
    appearance: none;
}

/* Menampilkan keypad angka di mobile */
input[type="text"][inputmode="numeric"] {
    -webkit-appearance: none;
    appearance: none;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--primary-soft);
    border-radius: 14px;
    margin-top: 20px;
}

.checkbox-group input[type="checkbox"] {
    width: 22px;
    height: 22px;
    accent-color: var(--secondary);
}

.checkbox-group label {
    margin: 0;
    font-weight: 600;
    color: var(--primary);
    cursor: pointer;
}

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

.footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
    border-top: 1px solid var(--border);
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
    
    .action-bar {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .btn-add {
        width: auto;
        padding: 14px 28px;
    }
    
    .modal {
        align-items: center;
        padding: 20px;
    }
    
    .modal-content {
        border-radius: 28px;
        max-width: 600px;
        animation: modalFade 0.3s ease;
    }
    
    @keyframes modalFade {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
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
    
    <!-- STATS HORIZONTAL -->
    <div class="stats-horizontal">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
            <div class="stat-label">Manager Developer</div>
            <div class="stat-value"><?= $total_manager ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #2A9D8F;">
            <div class="stat-icon"><i class="fas fa-coins" style="color: #2A9D8F;"></i></div>
            <div class="stat-label">Finance</div>
            <div class="stat-value"><?= $total_finance ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #D64F3C;">
            <div class="stat-icon"><i class="fas fa-user" style="color: #D64F3C;"></i></div>
            <div class="stat-label">Marketing</div>
            <div class="stat-value"><?= $total_marketing ?></div>
        </div>
        <div class="stat-card" style="border-left-color: var(--success);">
            <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--success);"></i></div>
            <div class="stat-label">Aktif</div>
            <div class="stat-value"><?= $total_active ?></div>
        </div>
    </div>
    
    <!-- INFO CARD -->
    <div class="info-card">
        <i class="fas fa-info-circle"></i>
        <div style="flex: 1;">
            <strong style="font-size: 16px;">Kelola Tim Lengkap</strong>
            <p>Tambahkan Manager Developer, Finance, dan Marketing Internal.</p>
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
    
    <!-- ACTION BAR -->
    <div class="action-bar">
        <button class="btn-add" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> Tambah Tim Baru
        </button>
        <a href="developer_komisi_rules.php" class="btn-add" style="background: linear-gradient(135deg, var(--info), #6DA5F0);">
            <i class="fas fa-coins"></i> Atur Komisi Rules
        </a>
    </div>
    
    <!-- TABLE -->
    <div class="table-container">
        <?php if (empty($team)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h4>Belum Ada Tim</h4>
            <p>Klik tombol "Tambah Tim Baru" untuk menambahkan Manager, Finance, atau Marketing</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Kontak</th>
                        <th>Role</th>
                        <th>Tipe Marketing</th>
                        <th>Status</th>
                        <th>Terakhir Login</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team as $t): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['nama_lengkap']) ?></strong></td>
                        <td><?= htmlspecialchars($t['username']) ?></td>
                        <td><?= htmlspecialchars($t['email']) ?></td>
                        <td><?= htmlspecialchars($t['phone'] ?? '-') ?></td>
                        <td>
                            <span class="role-badge <?= $t['role'] ?>">
                                <?= $t['role'] == 'manager_developer' ? 'Manager' : ($t['role'] == 'finance' ? 'Finance' : 'Marketing') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($t['role'] == 'marketing'): ?>
                                <?= ucfirst(str_replace('_', ' ', $t['marketing_type_name'] ?? 'Unknown')) ?>
                                <br>
                                <small>
                                    <a href="developer_komisi_rules.php" style="color: var(--info);">
                                        <i class="fas fa-coins"></i> Atur Komisi
                                    </a>
                                </small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $t['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $t['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td><?= $t['last_login'] ? date('d/m/Y H:i', strtotime($t['last_login'])) : '-' ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn edit" onclick="editUser(<?= htmlspecialchars(json_encode($t)) ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn reset" onclick="resetPassword(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['nama_lengkap'])) ?>')" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button class="action-btn delete" onclick="confirmDelete(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['nama_lengkap'])) ?>')" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>© <?= date('Y') ?> LeadEngine - Manajemen Tim v5.0 (FIXED: Email Error, Format Rupiah)</p>
    </div>
    
</div>

<!-- MODAL ADD/EDIT -->
<div class="modal" id="userModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-user-plus"></i> Tambah Tim</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="userForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="userId" value="0">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="nama_lengkap" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-circle"></i> Username</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-whatsapp"></i> Nomor WhatsApp (opsional)</label>
                    <input type="text" name="phone" id="phone" class="form-control" placeholder="628123456789" inputmode="numeric">
                    <small style="color: var(--text-muted); display: block; margin-top: 4px;">Format: 628xxxxxxxxxx</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password <span id="passwordNote" style="font-size: 11px; color: var(--text-muted);">(min. 6 karakter)</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-control" minlength="6" autocomplete="new-password">
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Role</label>
                    <select name="role" id="role" class="form-select" required onchange="toggleMarketingFields()">
                        <option value="manager_developer">Manager Developer</option>
                        <option value="finance">Finance</option>
                        <option value="marketing">Marketing Internal</option>
                    </select>
                </div>
                
                <!-- Marketing Type Fields (hanya muncul jika role = marketing) -->
                <div id="marketingFields" style="display: none; background: var(--primary-soft); padding: 16px; border-radius: 16px; margin-top: 10px;">
                    <h4 style="color: var(--primary); margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-store-alt" style="color: var(--secondary);"></i> 
                        Tipe Marketing
                    </h4>
                    
                    <div class="form-group">
                        <select name="marketing_type_id" id="marketing_type_id" class="form-select">
                            <option value="">Pilih Tipe Marketing</option>
                            <?php foreach ($marketing_types as $type): 
                                $type_label = ucfirst(str_replace('_', ' ', $type['type_name']));
                                $komisi_info = $type['commission_type'] == 'INTERNAL_FIXED' ? '(Komisi Tetap)' : '(Komisi Persentase)';
                            ?>
                            <option value="<?= $type['id'] ?>">
                                <?= $type_label ?> <?= $komisi_info ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--text-muted); display: block; margin-top: 8px; padding: 8px; background: white; border-radius: 8px;">
                            <i class="fas fa-info-circle" style="color: var(--secondary);"></i> 
                            <strong>Sales Inhouse:</strong> Komisi tetap (Rp) | 
                            <strong>Sales Canvasing:</strong> Komisi persentase (%)<br>
                            <a href="developer_komisi_rules.php" style="color: var(--info); text-decoration: underline;">
                                Atur nilai komisi di sini
                            </a>
                        </small>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="is_active" checked value="1">
                    <label for="is_active">
                        <i class="fas fa-check-circle" style="color: #2A9D8F;"></i> Aktif
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 style="color: var(--danger);"><i class="fas fa-trash-alt"></i> Hapus User</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div style="font-size: 48px; color: var(--danger); margin-bottom: 16px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p style="font-size: 16px; margin-bottom: 16px;">Yakin ingin menghapus user:</p>
            <div style="background: var(--primary-soft); padding: 16px; border-radius: 16px; font-weight: 700; font-size: 18px; color: var(--primary); margin-bottom: 16px;" id="deleteName"></div>
            <input type="hidden" id="deleteId">
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeDeleteModal()">Batal</button>
            <button class="btn-primary" style="background: var(--danger);" onclick="processDelete()">Ya, Hapus</button>
        </div>
    </div>
</div>

<script>
// ===== FUNGSI TOGGLE PASSWORD =====
function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// ===== FUNGSI TOGGLE MARKETING FIELDS =====
function toggleMarketingFields() {
    const role = document.getElementById('role').value;
    const marketingFields = document.getElementById('marketingFields');
    const marketingTypeSelect = document.getElementById('marketing_type_id');
    
    if (role === 'marketing') {
        marketingFields.style.display = 'block';
        marketingTypeSelect.required = true;
        marketingTypeSelect.setAttribute('required', 'required');
    } else {
        marketingFields.style.display = 'none';
        marketingTypeSelect.required = false;
        marketingTypeSelect.removeAttribute('required');
    }
}

// ===== FUNGSI MODAL =====
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Tambah Tim';
    document.getElementById('formAction').value = 'add';
    document.getElementById('userId').value = '0';
    document.getElementById('nama_lengkap').value = '';
    document.getElementById('username').value = '';
    document.getElementById('email').value = '';
    document.getElementById('phone').value = '';
    
    // Reset password field
    const passwordInput = document.getElementById('password');
    passwordInput.value = '';
    passwordInput.type = 'password';
    passwordInput.required = true;
    
    // Reset toggle icon
    const toggleBtn = document.querySelector('.toggle-password i');
    if (toggleBtn) {
        toggleBtn.classList.remove('fa-eye-slash');
        toggleBtn.classList.add('fa-eye');
    }
    
    document.getElementById('role').value = 'manager_developer';
    document.getElementById('marketing_type_id').value = '';
    document.getElementById('marketingFields').style.display = 'none';
    document.getElementById('marketing_type_id').required = false;
    document.getElementById('marketing_type_id').removeAttribute('required');
    document.getElementById('is_active').checked = true;
    
    document.getElementById('passwordNote').style.display = 'inline';
    document.getElementById('userModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function editUser(user) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit"></i> Edit Tim';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('userId').value = user.id;
    document.getElementById('nama_lengkap').value = user.nama_lengkap;
    document.getElementById('username').value = user.username;
    document.getElementById('email').value = user.email;
    document.getElementById('phone').value = user.phone || '';
    
    // Reset password field untuk edit
    const passwordInput = document.getElementById('password');
    passwordInput.value = '';
    passwordInput.type = 'password';
    passwordInput.required = false;
    
    // Reset toggle icon
    const toggleBtn = document.querySelector('.toggle-password i');
    if (toggleBtn) {
        toggleBtn.classList.remove('fa-eye-slash');
        toggleBtn.classList.add('fa-eye');
    }
    
    document.getElementById('role').value = user.role;
    document.getElementById('is_active').checked = user.is_active == 1;
    
    // Set marketing fields jika role = marketing
    if (user.role === 'marketing') {
        document.getElementById('marketingFields').style.display = 'block';
        document.getElementById('marketing_type_id').required = true;
        document.getElementById('marketing_type_id').setAttribute('required', 'required');
        document.getElementById('marketing_type_id').value = user.marketing_type_id || '';
    } else {
        document.getElementById('marketingFields').style.display = 'none';
        document.getElementById('marketing_type_id').required = false;
        document.getElementById('marketing_type_id').removeAttribute('required');
    }
    
    document.getElementById('passwordNote').style.display = 'inline';
    document.getElementById('userModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('userModal').classList.remove('show');
    document.body.style.overflow = '';
}

function resetPassword(id, name) {
    if (confirm('Reset password untuk ' + name + ' ke "password123"?')) {
        window.location.href = '?reset=' + id + '&key=<?= API_KEY ?>';
    }
}

function confirmDelete(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

function processDelete() {
    const id = document.getElementById('deleteId').value;
    if (id) {
        window.location.href = '?delete=' + id;
    }
}

// Close modal when clicking outside
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

if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php include 'includes/footer.php'; ?>