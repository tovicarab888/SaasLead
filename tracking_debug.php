<?php
/**
 * TRACKING_DEBUG.PHP - Debug & Test Tracking Pixel
 * Version: 1.0.0
 * FULL CODE - 100% LENGKAP
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

// Hanya admin yang bisa akses halaman debug
if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Super Admin.');
}

$conn = getDB();
if (!$conn) {
    die("Database connection failed");
}

// ========== PROSES TEST ==========
$test_result = null;
$test_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'test_tracking') {
        $developer_id = !empty($_POST['developer_id']) ? (int)$_POST['developer_id'] : null;
        $platform = $_POST['platform'] ?? 'all';
        $test_name = $_POST['test_name'] ?? 'Test User';
        $test_phone = $_POST['test_phone'] ?? '6281234567890';
        $test_email = $_POST['test_email'] ?? 'test@example.com';
        
        // Buat data test
        $test_data = [
            'customer_id' => 999999,
            'first_name' => $test_name,
            'last_name' => 'Test',
            'full_name' => $test_name . ' Test',
            'email' => $test_email,
            'phone' => $test_phone,
            'location' => 'kertamulya',
            'unit_type' => 'Type 36/60',
            'program' => 'Subsidi',
            'meta_event_id' => 'TEST_META_' . time(),
            'tiktok_event_id' => 'TEST_TT_' . time(),
            'fbp' => 'test.fbp.123456',
            'fbc' => 'test.fbc.123456',
            'client_ip' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'page_url' => 'https://leadproperti.com/test',
            'event_name' => 'TestEvent'
        ];
        
        $test_result = [];
        
        if ($platform === 'all' || $platform === 'meta') {
            $result = sendMetaTracking($test_data, $developer_id);
            $test_result['meta'] = $result;
        }
        
        if ($platform === 'all' || $platform === 'tiktok') {
            $result = sendTikTokTracking($test_data, $developer_id);
            $test_result['tiktok'] = $result;
        }
        
        if ($platform === 'all' || $platform === 'google') {
            $result = sendGATracking($test_data, $developer_id);
            $test_result['google'] = $result;
        }
    }
    
    elseif ($_POST['action'] === 'test_config') {
        $developer_id = !empty($_POST['test_dev_id']) ? (int)$_POST['test_dev_id'] : null;
        $test_result = getTrackingConfig(null, $developer_id);
    }
    
    elseif ($_POST['action'] === 'clear_logs') {
        $days = (int)($_POST['days'] ?? 7);
        
        try {
            $stmt = $conn->prepare("
                DELETE FROM tracking_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status = 'sent'
            ");
            $stmt->execute([$days]);
            $deleted = $stmt->rowCount();
            $test_result = "Berhasil menghapus $deleted log tracking yang lebih dari $days hari";
        } catch (Exception $e) {
            $test_error = "Gagal menghapus: " . $e->getMessage();
        }
    }
    
    elseif ($_POST['action'] === 'retry_failed') {
        $limit = (int)($_POST['limit'] ?? 10);
        
        try {
            $stmt = $conn->prepare("
                SELECT * FROM tracking_logs 
                WHERE status = 'failed' 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $failed_logs = $stmt->fetchAll();
            
            $retry_count = 0;
            foreach ($failed_logs as $log) {
                // Ambil data lead
                $lead_stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
                $lead_stmt->execute([$log['lead_id']]);
                $lead = $lead_stmt->fetch();
                
                if ($lead) {
                    $tracking_data = [
                        'customer_id' => $lead['id'],
                        'first_name' => $lead['first_name'],
                        'last_name' => $lead['last_name'],
                        'full_name' => $lead['first_name'] . ' ' . ($lead['last_name'] ?? ''),
                        'email' => $lead['email'],
                        'phone' => $lead['phone'],
                        'location' => $lead['location_key'],
                        'meta_event_id' => $lead['meta_event_id'],
                        'tiktok_event_id' => $lead['tiktok_event_id'],
                        'fbp' => $lead['fbp'],
                        'fbc' => $lead['fbc'],
                        'client_ip' => $lead['client_ip'],
                        'user_agent' => $lead['user_agent'],
                        'page_url' => $lead['page_url'],
                        'event_name' => 'Lead'
                    ];
                    
                    if ($log['pixel_type'] === 'meta') {
                        $result = sendMetaTracking($tracking_data, $log['developer_id']);
                        if ($result['success']) $retry_count++;
                    } elseif ($log['pixel_type'] === 'tiktok') {
                        $result = sendTikTokTracking($tracking_data, $log['developer_id']);
                        if ($result['success']) $retry_count++;
                    } elseif ($log['pixel_type'] === 'google') {
                        $result = sendGATracking($tracking_data, $log['developer_id']);
                        if ($result['success']) $retry_count++;
                    }
                    
                    // Beri jeda antar pengiriman
                    usleep(500000); // 0.5 detik
                }
            }
            
            $test_result = "Berhasil mengirim ulang $retry_count dari " . count($failed_logs) . " failed logs";
            
        } catch (Exception $e) {
            $test_error = "Gagal retry: " . $e->getMessage();
        }
    }
}

// ========== AMBIL DATA UNTUK FORM ==========
$developers = $conn->query("
    SELECT id, nama_lengkap, nama_perusahaan 
    FROM users 
    WHERE role = 'developer' AND is_active = 1 
    ORDER BY nama_lengkap
")->fetchAll();

// Ambil sample failed logs
$failed_logs = $conn->query("
    SELECT 
        tl.*,
        l.first_name,
        l.last_name,
        l.phone
    FROM tracking_logs tl
    LEFT JOIN leads l ON tl.lead_id = l.id
    WHERE tl.status = 'failed'
    ORDER BY tl.created_at DESC
    LIMIT 20
")->fetchAll();

// ========== SET VARIABLES ==========
$page_title = 'Tracking Debug';
$page_subtitle = 'Test & Debug Tracking Pixel';
$page_icon = 'fas fa-bug';

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
    <?php if ($test_result): ?>
    <div class="alert success">
        <i class="fas fa-check-circle"></i>
        <?php if (is_array($test_result)): ?>
            <pre style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; overflow-x: auto;"><?= json_encode($test_result, JSON_PRETTY_PRINT) ?></pre>
        <?php else: ?>
            <?= htmlspecialchars($test_result) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($test_error): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($test_error) ?>
    </div>
    <?php endif; ?>
    
    <!-- DEBUG CARDS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px;">
        
        <!-- TEST TRACKING -->
        <div class="card" style="padding: 25px;">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-paper-plane"></i> Test Send Tracking</h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="test_tracking">
                
                <div class="form-group">
                    <label>Developer</label>
                    <select name="developer_id" class="form-control">
                        <option value="">GLOBAL (Default)</option>
                        <?php foreach ($developers as $dev): ?>
                        <option value="<?= $dev['id'] ?>"><?= htmlspecialchars($dev['nama_lengkap']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Platform</label>
                    <select name="platform" class="form-control" required>
                        <option value="all">All Platforms</option>
                        <option value="meta">Meta Only</option>
                        <option value="tiktok">TikTok Only</option>
                        <option value="google">Google Only</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Nama Test</label>
                    <input type="text" name="test_name" class="form-control" value="Test User" required>
                </div>
                
                <div class="form-group">
                    <label>Nomor Test</label>
                    <input type="text" name="test_phone" class="form-control" value="6281234567890" required>
                </div>
                
                <div class="form-group">
                    <label>Email Test</label>
                    <input type="email" name="test_email" class="form-control" value="test@example.com" required>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Kirim Test Tracking
                </button>
            </form>
        </div>
        
        <!-- TEST CONFIG -->
        <div class="card" style="padding: 25px;">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-cog"></i> Test Tracking Config</h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="test_config">
                
                <div class="form-group">
                    <label>Developer ID (kosongkan untuk global)</label>
                    <input type="number" name="test_dev_id" class="form-control" placeholder="Contoh: 3">
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Cek Config
                </button>
            </form>
            
            <hr style="margin: 25px 0;">
            
            <h4 style="margin-bottom: 15px;"><i class="fas fa-trash"></i> Maintenance</h4>
            
            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus logs yang sudah sent?')">
                <input type="hidden" name="action" value="clear_logs">
                
                <div class="form-group">
                    <label>Hapus logs > (hari)</label>
                    <input type="number" name="days" class="form-control" value="7" min="1" max="90">
                </div>
                
                <button type="submit" class="btn-secondary" style="background: #D64F3C;">
                    <i class="fas fa-trash"></i> Hapus Logs Lama
                </button>
            </form>
            
            <hr style="margin: 25px 0;">
            
            <form method="POST" onsubmit="return confirm('Yakin ingin mengirim ulang semua failed logs?')">
                <input type="hidden" name="action" value="retry_failed">
                
                <div class="form-group">
                    <label>Jumlah maksimal retry</label>
                    <input type="number" name="limit" class="form-control" value="10" min="1" max="50">
                </div>
                
                <button type="submit" class="btn-primary" style="background: #2A9D8F;">
                    <i class="fas fa-redo-alt"></i> Retry Failed Logs
                </button>
            </form>
        </div>
        
        <!-- FAILED LOGS -->
        <div class="card" style="padding: 25px; grid-column: 1/-1;">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-exclamation-triangle" style="color: #D64F3C;"></i> Failed Logs Terbaru</h3>
            
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Waktu</th>
                            <th>Developer</th>
                            <th>Pixel</th>
                            <th>Lead</th>
                            <th>Event ID</th>
                            <th>Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failed_logs as $log): ?>
                        <tr>
                            <td>#<?= $log['id'] ?></td>
                            <td><?= date('d/m H:i', strtotime($log['created_at'])) ?></td>
                            <td><?= $log['developer_id'] ?: 'Global' ?></td>
                            <td>
                                <span style="background: 
                                    <?= $log['pixel_type'] == 'meta' ? '#1877F2' : 
                                       ($log['pixel_type'] == 'tiktok' ? '#000000' : '#EA4335'); ?>; 
                                    color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px;">
                                    <?= strtoupper($log['pixel_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['lead_id']): ?>
                                    <a href="index.php?search=<?= $log['lead_id'] ?>" target="_blank">
                                        #<?= $log['lead_id'] ?> - <?= htmlspecialchars($log['first_name'] ?? '') ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><code><?= substr($log['event_id'], 0, 20) ?>...</code></td>
                            <td><button class="btn-icon" onclick="showResponse(<?= htmlspecialchars(json_encode($log['response'])) ?>)"><i class="fas fa-eye"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($failed_logs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px;">Tidak ada failed logs</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
    
    <!-- FOOTER -->
    <div style="text-align: center; margin-top: 40px; padding: 20px; color: #7A8A84; font-size: 12px;">
        <p>© <?= date('Y') ?> TaufikMarie.com - Tracking Debug v1.0</p>
    </div>
    
</div>

<!-- MODAL RESPONSE -->
<div class="modal" id="responseModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2><i class="fas fa-code"></i> Response Details</h2>
            <button class="modal-close" onclick="closeModal('responseModal')">&times;</button>
        </div>
        <div class="modal-body" id="responseContent" style="max-height: 70vh; overflow-y: auto;">
            Loading...
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('responseModal')">Tutup</button>
        </div>
    </div>
</div>

<script>
function showResponse(response) {
    document.getElementById('responseContent').innerHTML = '<pre style="background: #F5F7F5; padding: 15px; border-radius: 12px;">' + response + '</pre>';
    openModal('responseModal');
}

function openModal(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}

// Update datetime
function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.querySelector('.date span').textContent = now.toLocaleDateString('id-ID', options);
    document.querySelector('.time span').textContent = now.toLocaleTimeString('id-ID', { hour12: false });
}
setInterval(updateDateTime, 1000);
updateDateTime();

// Close modal on outside click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});
</script>

<!-- FOOTER -->
<?php include 'includes/footer.php'; ?>