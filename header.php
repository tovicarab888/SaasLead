<?php
/**
 * HEADER.PHP - LEAD ENGINE PROPERTY
 * Version: 411.0.0 - DENGAN LEAD SCORE DISPLAY
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// ========== DATA USER ==========
$user_name = $_SESSION['nama_lengkap'] ?? $_SESSION['marketing_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? (isset($_SESSION['marketing_id']) ? 'marketing' : 'guest');
$user_id = $_SESSION['user_id'] ?? $_SESSION['marketing_id'] ?? 0;

// ========== FOTO PRIORITAS ==========
$photo_url = '';

// FUNGSI GET DB (ASUMSI SUDAH ADA)
if (!function_exists('getDB')) {
    function getDB() {
        try {
            $host = 'localhost';
            $dbname = 'lead_engine';
            $username = 'root';
            $password = '';
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            error_log("DB Connection failed: " . $e->getMessage());
            return null;
        }
    }
}

// PRIORITAS 1: PAKSA ADMIN (user_id = 1)
if ($user_id == 1) {
    $photo_url = '/admin/uploads/profiles/admin_1_1771687068.jpg?t=' . time();
}
// PRIORITAS 2: PAKSA MANAGER (user_id = 2)
elseif ($user_id == 2) {
    $photo_url = '/admin/uploads/profiles/manager_2_1771692882.png?t=' . time();
}
// PRIORITAS 3: DEVELOPER & LAINNYA - CEK DATABASE DULU
else {
    $conn = getDB();
    if ($conn) {
        // Tentukan tabel berdasarkan role
        if ($user_role === 'marketing') {
            $table = 'marketing_team';
        } else {
            $table = 'users';
        }
        
        // Ambil dari database
        try {
            $stmt = $conn->prepare("SELECT profile_photo FROM $table WHERE id = ?");
            $stmt->execute([$user_id]);
            $photo_filename = $stmt->fetchColumn();
            
            if (!empty($photo_filename)) {
                $full_path = __DIR__ . '/uploads/profiles/' . $photo_filename;
                if (file_exists($full_path)) {
                    $photo_url = '/admin/uploads/profiles/' . $photo_filename . '?t=' . filemtime($full_path);
                }
            }
            
            // KALAU TIDAK ADA DI DATABASE, CARI DI FOLDER
            if (empty($photo_url)) {
                $upload_dir = __DIR__ . '/uploads/profiles/';
                
                // Cari file dengan pola role_id_*
                $pattern = $user_role . '_' . $user_id . '_*';
                $files = glob($upload_dir . $pattern . '.{jpg,jpeg,png,gif}', GLOB_BRACE);
                
                if (empty($files)) {
                    // Coba cari dengan pola bebas
                    $files = glob($upload_dir . '*_' . $user_id . '_*.{jpg,jpeg,png,gif}', GLOB_BRACE);
                }
                
                if (!empty($files)) {
                    $filename = basename($files[0]);
                    $photo_url = '/admin/uploads/profiles/' . $filename . '?t=' . filemtime($files[0]);
                }
            }
        } catch (PDOException $e) {
            error_log("Error fetching profile photo: " . $e->getMessage());
        }
    }
}

// ========== NOTIFIKASI LENGKAP ==========
$notif_count = 0;
$recent_notifications = [];

$conn = getDB();
if ($conn) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
        $notif_count = (int)$stmt->fetchColumn();
        
        $stmt = $conn->query("
            SELECT n.*, l.first_name, l.last_name, l.lead_score 
            FROM notifications n
            LEFT JOIN leads l ON n.lead_id = l.id
            ORDER BY n.created_at DESC 
            LIMIT 20
        ");
        $recent_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?= isset($page_title) ? $page_title . ' - Lead Engine Property' : 'Lead Engine Property' ?></title>
     <!-- ===== FONT AWESOME LOKAL ===== -->
    <link rel="stylesheet" href="/admin/assets/fontawesome/css/all.min.css">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Admin CSS -->
    <link rel="stylesheet" href="/admin/assets/css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/admin/assets/css/modal.css?v=<?= time() ?>"> 
    
    <?php if (isset($use_chart) && $use_chart): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php endif; ?>
    
    <style>
        .lead-header {
            background: white;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #E0DAD3;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .lead-header-left { display: flex; align-items: center; gap: 12px; }
        .lead-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, #1B4A3C, #2A5F4E);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 24px;
            box-shadow: 0 6px 12px rgba(27,74,60,0.2);
        }
        .lead-brand {
            font-weight: 800; font-size: 20px; color: #1B4A3C;
            letter-spacing: -0.3px;
        }
        .lead-header-right { display: flex; align-items: center; gap: 16px; }
        .lead-notif-btn {
            width: 44px; height: 44px; background: #F5F3F0; border: none;
            border-radius: 14px; display: flex; align-items: center; justify-content: center;
            color: #1B4A3C; font-size: 20px; cursor: pointer; position: relative;
        }
        .lead-notif-badge {
            position: absolute; top: -4px; right: -4px;
            background: #D64F3C; color: white; font-size: 11px;
            min-width: 20px; height: 20px; border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            padding: 0 4px; border: 2px solid white; font-weight: 700;
        }
        .lead-profile-btn {
            display: flex; align-items: center; gap: 12px;
            padding: 4px 4px 4px 16px; background: #F5F3F0;
            border-radius: 40px; text-decoration: none;
            transition: all 0.2s ease;
        }
        .lead-profile-btn:hover {
            background: #E7F3EF;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .lead-profile-info { text-align: right; }
        .lead-profile-name {
            font-weight: 700; color: #1B4A3C; font-size: 14px;
            white-space: nowrap;
        }
        .lead-profile-role {
            font-size: 11px; color: #7A8A84; white-space: nowrap;
        }
        .lead-profile-photo {
            width: 44px; height: 44px; border-radius: 44px;
            object-fit: cover; border: 2px solid white; background: #D64F3C;
        }
        @media (max-width: 768px) {
            .lead-header { padding: 8px 16px; }
            .lead-brand { font-size: 16px; }
            .lead-icon { width: 40px; height: 40px; font-size: 20px; }
            .lead-profile-info { display: none; }
            .lead-profile-btn { background: transparent; padding: 0; }
            .lead-profile-photo { width: 44px; height: 44px; }
            .lead-notif-btn { width: 44px; height: 44px; }
        }
        .notif-modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 10000; display: none; align-items: center; justify-content: center;
            padding: 16px;
        }
        .notif-modal.show { display: flex; }
        .notif-modal-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(5px);
        }
        .notif-modal-content {
            position: relative; width: 100%; max-width: 500px; background: white;
            border-radius: 28px; box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            animation: modalPop 0.3s ease; max-height: 80vh;
            display: flex; flex-direction: column;
        }
        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        .notif-modal-header {
            padding: 20px 24px; border-bottom: 2px solid #E7F3EF;
            display: flex; justify-content: space-between; align-items: center;
        }
        .notif-modal-header h3 {
            color: #1B4A3C; font-size: 20px; font-weight: 700;
            margin: 0; display: flex; align-items: center; gap: 8px;
        }
        .notif-modal-header h3 i { color: #D64F3C; font-size: 22px; }
        .notif-header-actions { display: flex; gap: 8px; }
        .notif-header-btn {
            width: 40px; height: 40px; background: #F5F3F0; border: none;
            border-radius: 12px; color: #4A5A54; font-size: 18px; cursor: pointer;
        }
        .notif-header-btn:hover { background: #D64F3C; color: white; }
        .notif-modal-body { padding: 16px; overflow-y: auto; max-height: 400px; }
        .notif-item {
            display: flex; align-items: flex-start; gap: 12px; padding: 16px;
            background: #F5F3F0; border-radius: 18px; margin-bottom: 10px;
            cursor: pointer; border-left: 4px solid transparent;
        }
        .notif-item.unread { background: #E7F3EF; border-left-color: #D64F3C; }
        .notif-checkbox { margin-top: 4px; flex-shrink: 0; }
        .notif-checkbox input { width: 20px; height: 20px; accent-color: #D64F3C; }
        .notif-icon {
            width: 40px; height: 40px; background: white; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: #D64F3C; font-size: 18px; flex-shrink: 0;
        }
        .notif-content { flex: 1; }
        .notif-title { font-weight: 700; color: #1B4A3C; font-size: 15px; margin-bottom: 4px; }
        .notif-message { font-size: 13px; color: #4A5A54; margin-bottom: 6px; }
        .notif-meta { display: flex; gap: 12px; font-size: 11px; color: #7A8A84; }
        .notif-empty {
            text-align: center; padding: 60px 20px; color: #7A8A84;
        }
        .notif-empty i { font-size: 60px; color: #E0DAD3; margin-bottom: 15px; }
        .notif-modal-footer {
            padding: 16px 24px; border-top: 1px solid #E7F3EF;
            display: flex; justify-content: space-between; align-items: center;
        }
        .notif-select-all { display: flex; align-items: center; gap: 8px; }
        .notif-select-all input { width: 20px; height: 20px; accent-color: #D64F3C; }
        .notif-select-all label { font-size: 14px; color: #1B4A3C; cursor: pointer; }
        .notif-bulk-actions { display: flex; gap: 8px; }
        .notif-bulk-btn {
            background: #F5F3F0; border: none; padding: 10px 18px; border-radius: 40px;
            font-size: 13px; font-weight: 600; color: #1B4A3C; cursor: pointer;
            display: flex; align-items: center; gap: 6px;
        }
        .notif-bulk-btn:hover { background: #D64F3C; color: white; }
        .toast-message {
            position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
            background: #1B4A3C; color: white; padding: 12px 24px; border-radius: 40px;
            font-size: 14px; font-weight: 500; z-index: 10001; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>

<div class="lead-header">
    <div class="lead-header-left">
        <div class="lead-icon"><i class="fas fa-building"></i></div>
        <div class="lead-brand">Lead Engine Property</div>
    </div>
    
    <div class="lead-header-right">
        <button class="lead-notif-btn" onclick="toggleNotifModal()">
            <i class="fas fa-bell"></i>
            <?php if ($notif_count > 0): ?>
            <span class="lead-notif-badge"><?= $notif_count > 9 ? '9+' : $notif_count ?></span>
            <?php endif; ?>
        </button>
        
        <!-- ===== PROFILE BUTTON ===== -->
        <a href="<?= $user_role === 'marketing' ? 'marketing_dashboard.php' : 'profile.php' ?>" 
           class="lead-profile-btn">
            <div class="lead-profile-info">
                <div class="lead-profile-name"><?= htmlspecialchars($user_name) ?></div>
                <div class="lead-profile-role"><?= ucfirst($user_role) ?></div>
            </div>
            <?php if (!empty($photo_url)): ?>
                <img src="<?= $photo_url ?>" class="lead-profile-photo" alt="Profile">
            <?php else: ?>
                <div class="lead-profile-photo" style="display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px; background: #D64F3C;">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
            <?php endif; ?>
        </a>
    </div>
</div>

<div class="notif-modal" id="notifModal" onclick="if(event.target===this) closeNotifModal()">
    <div class="notif-modal-overlay" onclick="closeNotifModal()"></div>
    <div class="notif-modal-content">
        <div class="notif-modal-header">
            <h3><i class="fas fa-bell"></i> Notifikasi</h3>
            <div class="notif-header-actions">
                <button class="notif-header-btn" onclick="refreshNotif()"><i class="fas fa-sync-alt"></i></button>
                <button class="notif-header-btn" onclick="closeNotifModal()"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="notif-modal-body" id="notifList">
            <?php if (empty($recent_notifications)): ?>
            <div class="notif-empty"><i class="fas fa-bell-slash"></i><p>Tidak ada notifikasi</p></div>
            <?php else: ?>
                <?php foreach ($recent_notifications as $n): ?>
                <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>" data-id="<?= $n['id'] ?>">
                    <div class="notif-checkbox"><input type="checkbox" class="notif-checkbox-item" value="<?= $n['id'] ?>"></div>
                    <div class="notif-icon"><i class="fas fa-info-circle"></i></div>
                    <div class="notif-content" onclick="markAsRead(<?= $n['id'] ?>)">
                        <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="notif-message"><?= htmlspecialchars($n['message']) ?></div>
                        <div class="notif-meta">
                            <span><i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></span>
                            <?php if (isset($n['lead_score'])): ?>
                            <span><i class="fas fa-chart-line"></i> Score: <?= $n['lead_score'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($recent_notifications)): ?>
        <div class="notif-modal-footer">
            <div class="notif-select-all">
                <input type="checkbox" id="selectAllNotif" onclick="toggleSelectAll()">
                <label for="selectAllNotif">Pilih Semua</label>
            </div>
            <div class="notif-bulk-actions">
                <button class="notif-bulk-btn" onclick="markSelectedRead()"><i class="fas fa-check-double"></i> Baca</button>
                <button class="notif-bulk-btn" onclick="deleteSelected()"><i class="fas fa-trash"></i> Hapus</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleNotifModal() { 
    document.getElementById('notifModal').classList.toggle('show'); 
}
function closeNotifModal() { 
    document.getElementById('notifModal').classList.remove('show'); 
}
function refreshNotif() { 
    location.reload(); 
}
function markAsRead(id) {
    fetch('/admin/api/mark_notification.php', {
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, key: 'taufikmarie7878' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`.notif-item[data-id="${id}"]`).classList.remove('unread');
            updateNotifBadge();
        }
    })
    .catch(error => console.error('Error:', error));
}
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAllNotif');
    document.querySelectorAll('.notif-checkbox-item').forEach(cb => cb.checked = selectAll.checked);
}
function markSelectedRead() {
    const ids = Array.from(document.querySelectorAll('.notif-checkbox-item:checked')).map(cb => cb.value);
    if (ids.length === 0) return alert('Pilih notifikasi');
    fetch('/admin/api/mark_multiple_read.php', {
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: ids, key: 'taufikmarie7878' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            ids.forEach(id => document.querySelector(`.notif-item[data-id="${id}"]`).classList.remove('unread'));
            document.getElementById('selectAllNotif').checked = false;
            updateNotifBadge();
            showToast('✅ ' + ids.length + ' notifikasi dibaca');
        }
    })
    .catch(error => console.error('Error:', error));
}
function deleteSelected() {
    const ids = Array.from(document.querySelectorAll('.notif-checkbox-item:checked')).map(cb => cb.value);
    if (ids.length === 0) return alert('Pilih notifikasi');
    if (!confirm('Hapus ' + ids.length + ' notifikasi?')) return;
    fetch('/admin/api/delete_notifications.php', {
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: ids, key: 'taufikmarie7878' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            ids.forEach(id => {
                const el = document.querySelector(`.notif-item[data-id="${id}"]`);
                if (el) el.remove();
            });
            document.getElementById('selectAllNotif').checked = false;
            updateNotifBadge();
            showToast('✅ ' + ids.length + ' notifikasi dihapus');
            
            // Cek apakah masih ada notifikasi
            if (document.querySelectorAll('.notif-item').length === 0) {
                document.getElementById('notifList').innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash"></i><p>Tidak ada notifikasi</p></div>';
                document.querySelector('.notif-modal-footer').style.display = 'none';
            }
        }
    })
    .catch(error => console.error('Error:', error));
}
function updateNotifBadge() {
    fetch('/admin/api/get_unread_count.php?key=taufikmarie7878')
    .then(r => r.json())
    .then(data => {
        const btn = document.querySelector('.lead-notif-btn');
        let badge = btn.querySelector('.lead-notif-badge');
        if (data.count > 0) {
            if (badge) {
                badge.textContent = data.count > 9 ? '9+' : data.count;
            } else {
                const b = document.createElement('span');
                b.className = 'lead-notif-badge';
                b.textContent = data.count > 9 ? '9+' : data.count;
                btn.appendChild(b);
            }
        } else if (badge) {
            badge.remove();
        }
    })
    .catch(error => console.error('Error:', error));
}
function showToast(msg) {
    let toast = document.querySelector('.toast-message');
    if (!toast) { 
        toast = document.createElement('div'); 
        toast.className = 'toast-message'; 
        document.body.appendChild(toast); 
    }
    toast.textContent = msg;
    toast.style.opacity = '1';
    setTimeout(() => toast.style.opacity = '0', 3000);
}

// Inisialisasi
setInterval(updateNotifBadge, 30000);
updateNotifBadge();
</script>

</body>
</html>