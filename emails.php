<?php
/**
 * EMAILS.PHP - TAUFIKMARIE.COM ULTIMATE
 * Version: 8.0.0 - CMS Email dengan Manajemen Template Lengkap
 * FULL CODE - TANPA POTONGAN
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

// Hanya admin yang bisa akses halaman ini
if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    die('Akses ditolak. Halaman ini hanya untuk Admin.');
}

$conn = getDB();

// ========== PROSES UPDATE ==========
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->beginTransaction();
        
        foreach ($_POST['templates'] as $location_key => $data) {
            $subject = trim($data['subject'] ?? '');
            $body_html = trim($data['body_html'] ?? '');
            
            if (empty($subject) || empty($body_html)) {
                throw new Exception("Subject dan body email wajib diisi untuk semua lokasi");
            }
            
            // Check if exists
            $check = $conn->prepare("SELECT id FROM email_templates WHERE location_key = ?");
            $check->execute([$location_key]);
            $exists = $check->fetch();
            
            if ($exists && isset($exists['id'])) {
                // Update
                $stmt = $conn->prepare("
                    UPDATE email_templates 
                    SET subject = ?, body_html = ?, updated_at = NOW()
                    WHERE location_key = ?
                ");
                $stmt->execute([$subject, $body_html, $location_key]);
            } else {
                // Insert
                $stmt = $conn->prepare("
                    INSERT INTO email_templates (location_key, template_name, subject, body_html, created_at, updated_at)
                    VALUES (?, 'Email Bonus', ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$location_key, $subject, $body_html]);
            }
        }
        
        $conn->commit();
        $success = "✅ Template email berhasil diupdate!";
        logSystem("Email templates updated", ['by' => $_SESSION['username']], 'INFO', 'cms.log');
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "❌ Gagal update: " . $e->getMessage();
        logSystem("Email templates update failed", ['error' => $e->getMessage()], 'ERROR', 'cms.log');
    }
}

// ========== AMBIL DATA LOKASI ==========
$locations = $conn->query("SELECT * FROM locations ORDER BY sort_order")->fetchAll();

// ========== AMBIL DATA TEMPLATE ==========
$templates = [];
foreach ($locations as $loc) {
    $stmt = $conn->prepare("SELECT * FROM email_templates WHERE location_key = ?");
    $stmt->execute([$loc['location_key']]);
    $template = $stmt->fetch();
    if ($template && is_array($template)) {
        $templates[$loc['location_key']] = $template;
    }
}

// ========== DEFAULT TEMPLATE ==========
$default_template = [
    'subject' => '🎁 Selamat! Bonus Kompor Listrik + Subsidi Bank 35 Juta Telah Dikunci!',
    'body_html' => '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus Anda Telah Dikunci</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7fa; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #1B4A3C, #2A5F4E); padding: 30px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .bonus-box { background: #f8fff9; border: 2px solid #d1f7e5; border-radius: 12px; padding: 25px; margin: 20px 0; }
        .location-badge { background: #E7F3EF; display: inline-block; padding: 10px 20px; border-radius: 50px; color: #1B4A3C; font-weight: bold; margin: 15px 0; }
        .marketing-card { background: #f8fafc; border-radius: 12px; padding: 20px; margin: 20px 0; border-left: 4px solid #1B4A3C; }
        .btn { display: inline-block; background: #25D366; color: white; text-decoration: none; padding: 15px 35px; border-radius: 50px; font-weight: bold; margin: 20px 0; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #718096; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 SELAMAT!</h1>
            <p>Pendaftaran Anda Berhasil</p>
        </div>
        <div class="content">
            <h2>Halo, {first_name}!</h2>
            <p>Terima kasih telah mendaftar di <strong>TaufikMarie.com</strong>.</p>
            
            <div class="location-badge">{icon} {location_display}</div>
            
            <div class="bonus-box">
                <h3 style="color: #1B4A3C; margin-top: 0;">🎁 PAKET BONUS ANDA</h3>
                <p><strong>🔥 Kompor Listrik Rp 800.000</strong><br>Bonus langsung setelah akad</p>
                <p><strong>🏦 Subsidi Bank Rp 35.000.000</strong><br>Gratis biaya administrasi, notaris, appraisal</p>
                <p><strong>📋 Panduan Slik Check</strong><br>Konsultasi gratis</p>
            </div>
            
            <div class="marketing-card">
                <h3 style="color: #1B4A3C; margin-top: 0;">👤 KONSULTAN ANDA</h3>
                <p><strong>{marketing_name}</strong><br>WhatsApp: {marketing_phone}</p>
            </div>
            
            <div style="text-align: center;">
                <a href="https://wa.me/{marketing_phone}" class="btn">💬 CHAT VIA WHATSAPP</a>
            </div>
        </div>
        <div class="footer">
            <p>© 2026 TaufikMarie.com - All Rights Reserved</p>
            <p>ID: #{customer_id} • {date}</p>
        </div>
    </div>
</body>
</html>'
];

// ========== SET VARIABLES UNTUK INCLUDE ==========
$page_title = 'CMS Email';
$page_subtitle = 'Edit Template Email Marketing';
$page_icon = 'fas fa-envelope';

// ========== INCLUDE HEADER ==========
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
    <?php if ($success): ?>
    <div class="alert success">
        <i class="fas fa-check-circle fa-lg"></i>
        <?= $success ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle fa-lg"></i>
        <?= $error ?>
    </div>
    <?php endif; ?>
    
    <!-- INFO CARD -->
    <div style="background: linear-gradient(135deg, #1B4A3C 0%, #2A5F4E 100%); border-radius: 20px; padding: 16px 18px; margin-bottom: 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px; color: white; box-shadow: 0 15px 35px rgba(27, 74, 60, 0.3);">
        <i class="fas fa-info-circle" style="font-size: 28px; color: #E3B584; flex-shrink: 0;"></i>
        <div style="flex: 1; font-size: 14px; line-height: 1.5; min-width: 200px;">
            <strong style="font-size: 15px; color: #E3B584; display: block; margin-bottom: 6px;">Placeholder:</strong> 
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{first_name}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{full_name}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{marketing_name}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{location_display}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{icon}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{customer_id}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{date}</code>
            </div>
        </div>
        <button onclick="copyPlaceholders()" style="background: #D64F3C; color: white; border: none; padding: 10px 18px; border-radius: 40px; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 8px 20px rgba(214, 79, 60, 0.3); transition: all 0.3s; flex-shrink: 0; white-space: nowrap;">
            <i class="fas fa-copy"></i> Copy
        </button>
    </div>
    
    <!-- ACCORDION FORM -->
    <form method="POST" id="emailsForm" style="max-width: 1000px; margin: 0 auto;">
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($locations as $index => $loc): 
                $loc_key = $loc['location_key'];
                $template = isset($templates[$loc_key]) && is_array($templates[$loc_key]) 
                    ? $templates[$loc_key] 
                    : $default_template;
            ?>
            <div class="accordion-item" style="background: white; border-radius: 20px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05); overflow: hidden; border: 1px solid #E0DAD3;">
                <!-- Accordion Header -->
                <div class="accordion-header" onclick="toggleAccordion(<?= $index ?>)" style="padding: 16px 20px; background: linear-gradient(135deg, #F5F3F0 0%, #E7F3EF 100%); cursor: pointer; display: flex; align-items: center; gap: 12px; border-bottom: 3px solid transparent; transition: all 0.3s;" id="header_<?= $index ?>">
                    <!-- Icon -->
                    <div style="font-size: 32px; background: white; width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 12px rgba(27,74,60,0.1); border: 2px solid white; flex-shrink: 0;">
                        <?= $loc['icon'] ?>
                    </div>
                    
                    <!-- Info -->
                    <div style="flex: 1; min-width: 0;">
                        <h3 style="font-size: 18px; font-weight: 700; color: #1B4A3C; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($loc['display_name']) ?></h3>
                        <div style="display: flex; gap: 15px; color: #4A5A54; font-size: 12px;">
                            <span><i class="fas fa-envelope" style="margin-right: 4px; color: #D64F3C;"></i> Template Email</span>
                            <span><i class="fas fa-map-pin" style="margin-right: 4px; color: #D64F3C;"></i> <?= $loc['location_key'] ?></span>
                        </div>
                    </div>
                    
                    <!-- Icon Chevron -->
                    <div style="display: flex; align-items: center;">
                        <i class="fas fa-chevron-down" style="color: #D64F3C; font-size: 18px; transition: transform 0.3s;" id="icon_<?= $index ?>"></i>
                    </div>
                </div>
                
                <!-- Accordion Content -->
                <div class="accordion-content" id="content_<?= $index ?>" style="display: none; padding: 20px; background: white;">
                    <!-- Subject -->
                    <div style="margin-bottom: 18px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1B4A3C; font-size: 13px;">
                            <i class="fas fa-heading" style="color: #D64F3C; margin-right: 6px;"></i> SUBJECT EMAIL
                        </label>
                        <input type="text" 
                               name="templates[<?= $loc_key ?>][subject]" 
                               value="<?= htmlspecialchars($template['subject']) ?>" 
                               required
                               style="width: 100%; padding: 12px 16px; border: 2px solid #E0DAD3; border-radius: 14px; font-size: 14px; font-weight: 500; background: #F9FCFC;">
                    </div>
                    
                    <!-- Body HTML -->
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label style="font-weight: 600; color: #1B4A3C; font-size: 13px;">
                                <i class="fas fa-code" style="color: #D64F3C; margin-right: 6px;"></i> BODY EMAIL (HTML)
                            </label>
                            <button type="button" onclick="previewEmail(<?= $index ?>)" style="background: #1B4A3C; color: white; border: none; padding: 6px 16px; border-radius: 30px; font-size: 11px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                        </div>
                        <textarea name="templates[<?= $loc_key ?>][body_html]" 
                                  rows="10"
                                  style="width: 100%; padding: 14px; border: 2px solid #E0DAD3; border-radius: 14px; font-size: 13px; font-family: 'Courier New', monospace; background: #1e1e1e; color: #d4d4d4; line-height: 1.5;"><?= htmlspecialchars($template['body_html']) ?></textarea>
                    </div>
                    
                    <!-- Preview Panel -->
                    <div style="margin-top: 18px; padding: 16px; background: #F5F3F0; border-radius: 14px; display: none; border: 2px dashed #D64F3C;" id="preview_<?= $index ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h4 style="color: #1B4A3C; font-size: 14px;"><i class="fas fa-eye" style="color: #D64F3C; margin-right: 6px;"></i> Preview Email</h4>
                            <button type="button" onclick="closePreview(<?= $index ?>)" style="background: none; border: none; color: #D64F3C; cursor: pointer; font-size: 18px;">&times;</button>
                        </div>
                        <div style="background: white; padding: 12px; border-radius: 10px; margin-bottom: 12px; font-weight: 500; font-size: 13px;" id="preview_subject_<?= $index ?>"></div>
                        <iframe style="width: 100%; height: 300px; border: 1px solid #E0DAD3; border-radius: 10px; background: white;" id="preview_iframe_<?= $index ?>"></iframe>
                    </div>
                    
                    <!-- Footer Card -->
                    <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 18px; padding-top: 16px; border-top: 2px solid #E7F3EF;">
                        <div style="background: #F5F3F0; padding: 6px 16px; border-radius: 40px; display: flex; align-items: center; gap: 8px;">
                            <span style="color: #4A5A54; font-size: 11px;">ID:</span>
                            <code style="background: white; padding: 4px 12px; border-radius: 30px; color: #D64F3C; font-weight: 600; font-size: 11px; border: 1px solid #E0DAD3;"><?= $loc_key ?></code>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Save Button -->
        <button type="submit" style="background: linear-gradient(135deg, #1B4A3C 0%, #2A5F4E 100%); color: white; border: none; padding: 16px 28px; border-radius: 50px; font-weight: 700; font-size: 15px; cursor: pointer; width: 100%; max-width: 300px; margin: 30px auto 20px; display: block; box-shadow: 0 15px 35px rgba(27, 74, 60, 0.3);">
            <i class="fas fa-save" style="margin-right: 8px;"></i> SIMPAN SEMUA TEMPLATE
        </button>
    </form>
    
    <!-- FOOTER -->
    <div style="text-align: center; margin-top: 40px; padding: 20px; color: #7A8A84; font-size: 12px; border-top: 1px solid #E0DAD3;">
        <p>© <?= date('Y') ?> TaufikMarie.com - Email CMS Version 8.0.0</p>
    </div>
    
</div>

<script>
    // Sample data for preview
    const sampleData = {
        first_name: 'Budi',
        full_name: 'Budi Santoso',
        marketing_name: 'Taufik Marie',
        marketing_phone: '628133150078',
        location_display: 'Kertamulya Residence',
        icon: '🏡',
        customer_id: '12345',
        date: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
    };
    
    function toggleAccordion(index) {
        const content = document.getElementById('content_' + index);
        const icon = document.getElementById('icon_' + index);
        const header = document.getElementById('header_' + index);
        
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.style.transform = 'rotate(180deg)';
            header.style.borderBottomColor = '#D64F3C';
            header.style.background = 'linear-gradient(135deg, #E7F3EF 0%, #d4e8e0 100%)';
        } else {
            content.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
            header.style.borderBottomColor = 'transparent';
            header.style.background = 'linear-gradient(135deg, #F5F3F0 0%, #E7F3EF 100%)';
            
            const preview = document.getElementById('preview_' + index);
            if (preview) preview.style.display = 'none';
        }
    }
    
    function previewEmail(index) {
        const subjectInput = document.querySelector(`input[name*="[subject]"]`);
        const bodyInput = document.querySelector(`textarea[name*="[body_html]"]`);
        
        if (!subjectInput || !bodyInput) return;
        
        let subject = subjectInput.value;
        let body = bodyInput.value;
        
        body = body.replace(/{first_name}/g, sampleData.first_name);
        body = body.replace(/{full_name}/g, sampleData.full_name);
        body = body.replace(/{marketing_name}/g, sampleData.marketing_name);
        body = body.replace(/{marketing_phone}/g, sampleData.marketing_phone);
        body = body.replace(/{location_display}/g, sampleData.location_display);
        body = body.replace(/{icon}/g, sampleData.icon);
        body = body.replace(/{customer_id}/g, sampleData.customer_id);
        body = body.replace(/{date}/g, sampleData.date);
        
        document.getElementById('preview_subject_' + index).textContent = '📧 Subject: ' + subject;
        
        const iframe = document.getElementById('preview_iframe_' + index);
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(body);
        iframeDoc.close();
        
        document.getElementById('preview_' + index).style.display = 'block';
    }
    
    function closePreview(index) {
        document.getElementById('preview_' + index).style.display = 'none';
    }
    
    function copyPlaceholders() {
        const placeholders = '{first_name}, {full_name}, {marketing_name}, {marketing_phone}, {location_display}, {icon}, {customer_id}, {date}';
        navigator.clipboard.writeText(placeholders).then(() => {
            alert('✅ Placeholder berhasil dicopy!');
        }).catch(() => {
            alert('❌ Gagal copy. Silakan copy manual.');
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        if (document.querySelector('.accordion-item')) {
            setTimeout(() => {
                toggleAccordion(0);
            }, 100);
        }
    });
    
    let formChanged = false;
    document.getElementById('emailsForm').addEventListener('input', function() {
        formChanged = true;
    });
    document.getElementById('emailsForm').addEventListener('change', function() {
        formChanged = true;
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'Ada perubahan yang belum disimpan. Yakin ingin keluar?';
        }
    });
</script>

<!-- FOOTER -->
<?php include 'includes/footer.php'; ?>