<?php
/**
 * MARKETING_LEADS.PHP - Semua Leads untuk Marketing
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'api/config.php';

if (!isMarketing()) {
    header('Location: login.php');
    exit();
}

$conn = getDB();
$marketing_id = $_SESSION['marketing_id'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$params = [$marketing_id];
$sql = "SELECT l.*, loc.display_name as location_display, loc.icon
        FROM leads l
        LEFT JOIN locations loc ON l.location_key = loc.location_key
        WHERE l.assigned_marketing_team_id = ?
        AND (l.deleted_at IS NULL)";

if ($search) {
    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.phone LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($status) {
    $sql .= " AND l.status = ?";
    $params[] = $status;
}

// Count total
$count_sql = "SELECT COUNT(*) FROM (" . $sql . ") as tmp";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();

// Get data
$sql .= " ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

$page_title = 'Data Leads';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="top-bar">
        <div class="welcome-text">
            <i class="fas fa-users"></i>
            <h2>Data Leads <span>Semua leads Anda</span></h2>
        </div>
        <div class="datetime">
            <div class="date"><i class="fas fa-calendar-alt"></i> <span></span></div>
            <div class="time"><i class="fas fa-clock"></i> <span></span></div>
        </div>
    </div>
    
    <!-- Filter -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <input type="text" name="search" class="filter-input" placeholder="Cari nama/telepon..." value="<?= htmlspecialchars($search) ?>">
            <select name="status" class="filter-select">
                <option value="">Semua Status</option>
                <option value="Baru" <?= $status == 'Baru' ? 'selected' : '' ?>>Baru</option>
                <option value="Follow Up" <?= $status == 'Follow Up' ? 'selected' : '' ?>>Follow Up</option>
                <option value="Survey" <?= $status == 'Survey' ? 'selected' : '' ?>>Survey</option>
                <option value="Booking" <?= $status == 'Booking' ? 'selected' : '' ?>>Booking</option>
                <option value="Deal KPR" <?= $status == 'Deal KPR' ? 'selected' : '' ?>>Deal KPR</option>
                <option value="Tolak Slik" <?= $status == 'Tolak Slik' ? 'selected' : '' ?>>Tolak Slik</option>
                <option value="Tidak Minat" <?= $status == 'Tidak Minat' ? 'selected' : '' ?>>Tidak Minat</option>
                <option value="Batal" <?= $status == 'Batal' ? 'selected' : '' ?>>Batal</option>
            </select>
            <div class="filter-actions">
                <button type="submit" class="filter-btn"><i class="fas fa-search"></i> Cari</button>
                <a href="?" class="filter-btn reset"><i class="fas fa-redo"></i> Reset</a>
            </div>
        </form>
    </div>
    
    <!-- Table -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Daftar Leads (<?= $total ?>)</h3>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Kontak</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): 
                        $score_class = $lead['lead_score'] >= 80 ? 'hot' : ($lead['lead_score'] >= 60 ? 'warm' : 'cold');
                    ?>
                    <tr>
                        <td>#<?= $lead['id'] ?></td>
                        <td><strong><?= htmlspecialchars($lead['first_name'] . ' ' . ($lead['last_name'] ?? '')) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($lead['phone']) ?><br>
                            <small><?= htmlspecialchars($lead['email'] ?? '-') ?></small>
                        </td>
                        <td><span class="location-badge"><span><?= $lead['icon'] ?? '🏠' ?></span> <?= htmlspecialchars($lead['location_display'] ?? $lead['location_key']) ?></span></td>
                        <td><span class="status-badge <?= str_replace(' ', '', $lead['status'] ?? 'Baru') ?>"><?= $lead['status'] ?? 'Baru' ?></span></td>
                        <td><span class="score-badge score-<?= $score_class ?>"><?= $lead['lead_score'] ?? 0 ?></span></td>
                        <td><?= date('d/m/Y', strtotime($lead['created_at'])) ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewLead(<?= $lead['id'] ?>)" title="Lihat"><i class="fas fa-eye"></i></button>
                                <button class="action-btn edit" onclick="editLead(<?= $lead['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="action-btn note" onclick="openNoteModal(<?= $lead['id'] ?>, '<?= htmlspecialchars(addslashes($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''))) ?>')" title="Catatan"><i class="fas fa-sticky-note"></i></button>
                                <a href="https://wa.me/<?= $lead['phone'] ?>" target="_blank" class="action-btn whatsapp" title="WA"><i class="fab fa-whatsapp"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total > $limit): 
            $total_pages = ceil($total / $limit);
        ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" 
                   class="pagination-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/modals_marketing.php'; ?>

<script>
function viewLead(id) {
    fetch('api/leads_marketing.php?action=get&id=' + id, { credentials: 'include' })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const l = data.data;
            const fullName = l.first_name + ' ' + (l.last_name || '');
            const scoreClass = l.lead_score >= 80 ? 'hot' : (l.lead_score >= 60 ? 'warm' : 'cold');
            
            document.getElementById('viewModalBody').innerHTML = `
                <div class="view-section">
                    <div class="view-section-title"><i class="fas fa-user"></i> Informasi Customer</div>
                    <div class="view-grid">
                        <div class="view-item"><div class="view-item-label">Nama</div><div class="view-item-value">${fullName}</div></div>
                        <div class="view-item"><div class="view-item-label">WhatsApp</div><div class="view-item-value"><a href="https://wa.me/${l.phone}" target="_blank">${l.phone}</a></div></div>
                        <div class="view-item"><div class="view-item-label">Email</div><div class="view-item-value">${l.email || '-'}</div></div>
                        <div class="view-item"><div class="view-item-label">Lokasi</div><div class="view-item-value">${l.icon || ''} ${l.location_display || l.location_key}</div></div>
                        <div class="view-item"><div class="view-item-label">Status</div><div class="view-item-value"><span class="status-badge ${l.status.replace(' ', '')}">${l.status}</span></div></div>
                        <div class="view-item"><div class="view-item-label">Score</div><div class="view-item-value"><span class="score-badge score-${scoreClass}">${l.lead_score}</span></div></div>
                    </div>
                </div>
            `;
            openModal('viewModal');
        }
    });
}

function editLead(id) {
    fetch('api/leads_marketing.php?action=get&id=' + id, { credentials: 'include' })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const l = data.data;
            document.getElementById('edit_id').value = l.id;
            document.getElementById('edit_customer_name').innerHTML = '<i class="fas fa-user"></i> ' + l.first_name + ' ' + (l.last_name || '');
            document.getElementById('edit_phone').innerHTML = l.phone;
            document.getElementById('edit_status').value = l.status || 'Baru';
            document.getElementById('edit_email').value = l.email || '';
            document.getElementById('edit_unit_type').value = l.unit_type || 'Type 36/60';
            document.getElementById('edit_program').value = l.program || 'Subsidi';
            document.getElementById('edit_address').value = l.address || '';
            document.getElementById('edit_city').value = l.city || '';
            document.getElementById('edit_notes').value = l.notes || '';
            openModal('editModal');
        }
    });
}

function openNoteModal(leadId, customerName) {
    document.getElementById('note_lead_id').value = leadId;
    document.getElementById('note_customer_name').innerHTML = 'Lead: ' + customerName;
    document.getElementById('note_text').value = '';
    openModal('noteModal');
}

function submitNote(e) {
    e.preventDefault();
    const data = {
        lead_id: document.getElementById('note_lead_id').value,
        action_type: document.getElementById('note_action_type').value,
        note: document.getElementById('note_text').value
    };
    
    fetch('api/leads_marketing.php?action=add_note', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data), credentials: 'include'
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showToast('✅ Catatan ditambahkan', 'success');
            closeNoteModal();
        } else {
            showToast('❌ ' + res.message, 'error');
        }
    });
}

function submitEditLead(e) {
    e.preventDefault();
    const form = document.getElementById('editLeadForm');
    const data = Object.fromEntries(new FormData(form));
    
    fetch('api/leads_marketing.php?action=update_with_scoring', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data), credentials: 'include'
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showToast('✅ Lead diupdate', 'success');
            closeEditModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('❌ ' + res.message, 'error');
        }
    });
}

function openModal(id) { document.getElementById(id).classList.add('show'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('show'); document.body.style.overflow = ''; }
function closeViewModal() { closeModal('viewModal'); }
function closeEditModal() { closeModal('editModal'); }
function closeNoteModal() { closeModal('noteModal'); }

function showToast(msg, type) {
    let t = document.querySelector('.toast-message');
    if (!t) { t = document.createElement('div'); t.className = 'toast-message'; document.body.appendChild(t); }
    t.textContent = msg;
    t.style.background = type === 'success' ? '#2A9D8F' : '#D64F3C';
    t.style.opacity = '1';
    setTimeout(() => t.style.opacity = '0', 3000);
}

function updateDateTime() {
    const d = new Date();
    document.querySelector('.date span').textContent = d.toLocaleDateString('id-ID', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    document.querySelector('.time span').textContent = d.toLocaleTimeString('id-ID', { hour12:false });
}
setInterval(updateDateTime, 1000);
updateDateTime();
</script>

<?php include 'includes/footer.php'; ?>