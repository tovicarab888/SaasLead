<?php
/**
 * MESSAGES.PHP - TAUFIKMARIE.COM ULTIMATE
 * Version: 11.3.0 - CMS Pesan WhatsApp dengan Template CS Personal
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
        
        foreach ($_POST['messages'] as $location_key => $messages) {
            foreach ($messages as $type => $text) {
                $check = $conn->prepare("SELECT id FROM whatsapp_messages WHERE location_key = ? AND message_type = ?");
                $check->execute([$location_key, $type]);
                
                if ($check->fetch()) {
                    $stmt = $conn->prepare("
                        UPDATE whatsapp_messages 
                        SET message_text = ?, updated_at = NOW()
                        WHERE location_key = ? AND message_type = ?
                    ");
                    $stmt->execute([$text, $location_key, $type]);
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO whatsapp_messages (location_key, message_type, message_text, created_at, updated_at)
                        VALUES (?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$location_key, $type, $text]);
                }
            }
        }
        
        $conn->commit();
        $success = "✅ Pesan WhatsApp berhasil diupdate!";
        logSystem("WhatsApp messages updated", ['by' => $_SESSION['username']], 'INFO', 'cms.log');
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "❌ Gagal update: " . $e->getMessage();
        logSystem("WhatsApp messages update failed", ['error' => $e->getMessage()], 'ERROR', 'cms.log');
    }
}

// ========== AMBIL DATA LOKASI ==========
$locations = $conn->query("SELECT * FROM locations ORDER BY sort_order")->fetchAll();

// ========== AMBIL DATA PESAN ==========
$messages = [];
foreach ($locations as $loc) {
    $stmt = $conn->prepare("SELECT message_type, message_text FROM whatsapp_messages WHERE location_key = ?");
    $stmt->execute([$loc['location_key']]);
    while ($row = $stmt->fetch()) {
        $messages[$loc['location_key']][$row['message_type']] = $row['message_text'];
    }
}

// ========== DEFAULT MESSAGES - UPDATED PERSONAL VERSION ==========
$default_messages = [
    // Pesan 1 - Perkenalan (untuk FULL_EXTERNAL)
    'pesan1' => "*Assalamualaikum warahmatullahi wabarakatuh* 🌟

Halo Kak *{name}*! 👋

Perkenalkan, saya *{marketing}* dari *TaufikMarie.com*. Terima kasih sudah mengisi formulir di website kami.

Saya lihat Kakak tertarik dengan unit di:
{icon} *{location}*

✨ *SPESIFIKASI UNIT:*
• LT 60m² | LB 30m²
• 2 Kamar Tidur + 1 Kamar Mandi
• Desain Skandinavia modern
• Hadap utara-selatan (tidak panas)
• Sertifikat SHM (Hak Milik)

Mohon informasinya, apakah Kakak sudah pernah mengajukan KPR sebelumnya?",
    
    // Pesan 2 - Keunggulan Lokasi (untuk FULL_EXTERNAL)
    'pesan2' => "📍 *KEUNGGULAN {location}:*

✅ 15 menit ke pusat kota
✅ 15 menit ke Masjid Agung
✅ 10 menit ke RSUD 45
✅ 5 menit ke terminal tipe A
✅ 30 menit ke pintu tol
✅ Dekat dengan sekolah negeri
✅ View pegunungan langsung dari rumah

*FASILITAS CLUSTER:*
• Masjid kapasitas 400 jamaah
• Taman bermain anak
• 24 jam security
• One gate system
• Jalan utama lebar 10 meter",
    
    // Pesan 3 - Langkah Selanjutnya (untuk FULL_EXTERNAL)
    'pesan3' => "📋 *LANGKAH SELANJUTNYA:*

*YANG PERLU DISIAPKAN:*
📄 Foto KTP
📑 Kartu Keluarga (KK)
💳 NPWP (opsional)

*PROSES CEPAT 7 HARI:*
1️⃣ Verifikasi data (hari ini)
2️⃣ Pengecekan SLIK OJK (hari ini)
3️⃣ Pengajuan KPR ke bank (besok)
4️⃣ Survey lokasi (hari ke-3)
5️⃣ Akad kredit (hari ke-5-7)
6️⃣ SERAH TERIMA KUNCI!

*BONUS ANDA:*
🔥 Kompor Induksi Rp 800.000
🏦 Subsidi Bank Rp 35.000.000
📄 Gratis BPHTB & Balik Nama

*JADWAL SURVEY:*
📅 Rabu - Minggu
⏰ 09.00 - 16.00 WIB

Silakan kirim foto KTP Kakak untuk kami proses SLIK OJK.

Terima kasih,
*{marketing}*",
    
    // ===== PESAN CS UNTUK MODE SPLIT =====
    'pesan_cs' => "*Assalamualaikum warahmatullahi wabarakatuh* 🌟

Halo Kak *{customer_name}*! 👋

Terima kasih sudah mendaftar di *{location}*. Kami senang sekali Anda tertarik dengan unit kami.

📢 *YUK KENALAN DENGAN MARKETING KAMU!*

Kak {customer_name} akan ditemani oleh:
━━━━━━━━━━━━━━━━━━━━
👤 *{marketing_name}*
📱 *{marketing_phone}*
━━━━━━━━━━━━━━━━━━━━

*TIM KAMI AKAN SEGERA MENGHUBUNGI*
Maksimal 15 menit ya Kak! 😊

Atau Kakak bisa langsung chat dengan klik nomor di atas:
👇 *Caranya:*
1. Tap nomor *{marketing_phone}*
2. Pilih \"Salin Nomor\"
3. Buka WhatsApp
4. Tempel nomor dan kirim pesan

Salam hangat,
*Customer Service TaufikMarie.com*
{icon} {location}

💡 *Tips:* Simpan nomor ini agar mudah dihubungi nanti"
];

// ========== SET VARIABLES UNTUK INCLUDE ==========
$page_title = 'CMS WhatsApp';
$page_subtitle = 'Edit Template Pesan Otomatis';
$page_icon = 'fab fa-whatsapp';

// ========== INCLUDE HEADER ==========
include 'includes/header.php';
?>

<!-- SIDEBAR -->
<?php include 'includes/sidebar.php'; ?>

<!-- MAIN CONTENT - (SISANYA SAMA PERSIS DENGAN FILE ANDA) -->
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
        <i class="fab fa-whatsapp" style="font-size: 28px; color: #25D366; flex-shrink: 0;"></i>
        <div style="flex: 1; font-size: 14px; line-height: 1.5; min-width: 200px;">
            <strong style="font-size: 15px; color: #25D366; display: block; margin-bottom: 6px;">Placeholder:</strong> 
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{name}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{full_name}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{marketing}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{location}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px;">{icon}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px; background: #25D366; color: white;">{customer_name}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px; background: #25D366; color: white;">{marketing_name}</code>
                <code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 8px; font-size: 12px; background: #25D366; color: white;">{marketing_phone}</code>
            </div>
        </div>
        <button onclick="copyPlaceholders()" style="background: #25D366; color: white; border: none; padding: 10px 18px; border-radius: 40px; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 8px 20px rgba(37, 211, 102, 0.3); flex-shrink: 0; white-space: nowrap;">
            <i class="fas fa-copy"></i> Copy
        </button>
    </div>
    
    <!-- ACCORDION FORM - (SAMA PERSIS DENGAN FILE ANDA) -->
    <form method="POST" id="messagesForm" style="max-width: 1000px; margin: 0 auto;">
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($locations as $index => $loc): 
                $loc_key = $loc['location_key'];
                $loc_messages = $messages[$loc_key] ?? [];
            ?>
            <div class="accordion-item" style="background: white; border-radius: 20px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05); overflow: hidden; border: 1px solid #E0DAD3;">
                <!-- Accordion Header -->
                <div class="accordion-header" onclick="toggleAccordion(<?= $index ?>)" style="padding: 16px 20px; background: linear-gradient(135deg, #F5F3F0 0%, #E7F3EF 100%); cursor: pointer; display: flex; align-items: center; gap: 12px; border-bottom: 3px solid transparent;" id="header_<?= $index ?>">
                    <!-- Icon -->
                    <div style="font-size: 32px; background: white; width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 12px rgba(27,74,60,0.1); border: 2px solid white; flex-shrink: 0;">
                        <?= $loc['icon'] ?>
                    </div>
                    
                    <!-- Info -->
                    <div style="flex: 1; min-width: 0;">
                        <h3 style="font-size: 18px; font-weight: 700; color: #1B4A3C; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($loc['display_name']) ?></h3>
                        <div style="display: flex; gap: 15px; color: #4A5A54; font-size: 12px;">
                            <span><i class="fab fa-whatsapp" style="margin-right: 4px; color: #25D366;"></i> 4 Pesan Otomatis</span>
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
                    
                    <!-- ===== INFO KETERANGAN ===== -->
                    <div style="background: #E7F3EF; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; border-left: 4px solid #25D366;">
                        <p style="margin: 0; color: #1B4A3C; font-size: 13px;">
                            <i class="fas fa-info-circle" style="color: #25D366;"></i> 
                            <strong>Pesan CS:</strong> Dikirim ke customer untuk memberitahu marketing yang akan menghubungi.
                            <br>Placeholder khusus: <code>{customer_name}</code>, <code>{marketing_name}</code>, <code>{marketing_phone}</code>
                        </p>
                    </div>
                    
                    <!-- PESAN CS -->
                    <div style="margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px dashed #25D366;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <label style="font-weight: 700; color: #1B4A3C; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                                <span style="background: #25D366; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px;">CS</span>
                                Pesan Customer Service (Memberitahu Marketing)
                            </label>
                            <button type="button" onclick="resetMessage('<?= $loc_key ?>', 'pesan_cs')" style="background: none; border: 1px solid #E0DAD3; padding: 4px 12px; border-radius: 30px; font-size: 11px; cursor: pointer; color: #4A5A54;">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                        <textarea name="messages[<?= $loc_key ?>][pesan_cs]" 
                                  rows="8"
                                  style="width: 100%; padding: 14px; border: 2px solid #25D366; border-radius: 14px; font-size: 13px; font-family: 'Courier New', monospace; background: #F9FCFC; line-height: 1.6;"
                                  id="msg_cs_<?= $loc_key ?>"
                                  oninput="updateCounter('msg_cs_<?= $loc_key ?>', 'counter_cs_<?= $loc_key ?>')"><?= htmlspecialchars($loc_messages['pesan_cs'] ?? $default_messages['pesan_cs']) ?></textarea>
                        <div style="text-align: right; margin-top: 4px; font-size: 11px; color: #7A8A84;" id="counter_cs_<?= $loc_key ?>">
                            <?= strlen($loc_messages['pesan_cs'] ?? $default_messages['pesan_cs']) ?> karakter
                        </div>
                    </div>
                    
                    <!-- PESAN 1 -->
                    <div style="margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <label style="font-weight: 700; color: #1B4A3C; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                                <span style="background: #D64F3C; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px;">1</span>
                                Pesan Perkenalan
                            </label>
                            <button type="button" onclick="resetMessage('<?= $loc_key ?>', 'pesan1')" style="background: none; border: 1px solid #E0DAD3; padding: 4px 12px; border-radius: 30px; font-size: 11px; cursor: pointer; color: #4A5A54;">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                        <textarea name="messages[<?= $loc_key ?>][pesan1]" 
                                  rows="6"
                                  style="width: 100%; padding: 14px; border: 2px solid #E0DAD3; border-radius: 14px; font-size: 13px; font-family: 'Courier New', monospace; background: #F9FCFC; line-height: 1.6;"
                                  id="msg1_<?= $loc_key ?>"
                                  oninput="updateCounter('msg1_<?= $loc_key ?>', 'counter1_<?= $loc_key ?>')"><?= htmlspecialchars($loc_messages['pesan1'] ?? $default_messages['pesan1']) ?></textarea>
                        <div style="text-align: right; margin-top: 4px; font-size: 11px; color: #7A8A84;" id="counter1_<?= $loc_key ?>">
                            <?= strlen($loc_messages['pesan1'] ?? $default_messages['pesan1']) ?> karakter
                        </div>
                    </div>
                    
                    <!-- PESAN 2 -->
                    <div style="margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <label style="font-weight: 700; color: #1B4A3C; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                                <span style="background: #D64F3C; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px;">2</span>
                                Keunggulan Lokasi
                            </label>
                            <button type="button" onclick="resetMessage('<?= $loc_key ?>', 'pesan2')" style="background: none; border: 1px solid #E0DAD3; padding: 4px 12px; border-radius: 30px; font-size: 11px; cursor: pointer; color: #4A5A54;">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                        <textarea name="messages[<?= $loc_key ?>][pesan2]" 
                                  rows="5"
                                  style="width: 100%; padding: 14px; border: 2px solid #E0DAD3; border-radius: 14px; font-size: 13px; font-family: 'Courier New', monospace; background: #F9FCFC; line-height: 1.6;"
                                  id="msg2_<?= $loc_key ?>"
                                  oninput="updateCounter('msg2_<?= $loc_key ?>', 'counter2_<?= $loc_key ?>')"><?= htmlspecialchars($loc_messages['pesan2'] ?? $default_messages['pesan2']) ?></textarea>
                        <div style="text-align: right; margin-top: 4px; font-size: 11px; color: #7A8A84;" id="counter2_<?= $loc_key ?>">
                            <?= strlen($loc_messages['pesan2'] ?? $default_messages['pesan2']) ?> karakter
                        </div>
                    </div>
                    
                    <!-- PESAN 3 -->
                    <div style="margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <label style="font-weight: 700; color: #1B4A3C; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                                <span style="background: #D64F3C; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px;">3</span>
                                Langkah Selanjutnya
                            </label>
                            <button type="button" onclick="resetMessage('<?= $loc_key ?>', 'pesan3')" style="background: none; border: 1px solid #E0DAD3; padding: 4px 12px; border-radius: 30px; font-size: 11px; cursor: pointer; color: #4A5A54;">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                        <textarea name="messages[<?= $loc_key ?>][pesan3]" 
                                  rows="6"
                                  style="width: 100%; padding: 14px; border: 2px solid #E0DAD3; border-radius: 14px; font-size: 13px; font-family: 'Courier New', monospace; background: #F9FCFC; line-height: 1.6;"
                                  id="msg3_<?= $loc_key ?>"
                                  oninput="updateCounter('msg3_<?= $loc_key ?>', 'counter3_<?= $loc_key ?>')"><?= htmlspecialchars($loc_messages['pesan3'] ?? $default_messages['pesan3']) ?></textarea>
                        <div style="text-align: right; margin-top: 4px; font-size: 11px; color: #7A8A84;" id="counter3_<?= $loc_key ?>">
                            <?= strlen($loc_messages['pesan3'] ?? $default_messages['pesan3']) ?> karakter
                        </div>
                    </div>
                    
                    <!-- Preview Button -->
                    <div style="display: flex; justify-content: center; margin: 16px 0 8px;">
                        <button type="button" onclick="previewMessages(<?= $index ?>)" style="background: #25D366; color: white; border: none; padding: 10px 24px; border-radius: 40px; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 8px 20px rgba(37,211,102,0.2);">
                            <i class="fas fa-eye"></i> Preview Semua Pesan
                        </button>
                    </div>
                    
                    <!-- Preview Panel -->
                    <div style="margin-top: 16px; padding: 16px; background: #F5F3F0; border-radius: 14px; display: none; border: 2px dashed #25D366;" id="preview_<?= $index ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h4 style="color: #1B4A3C; font-size: 14px;"><i class="fab fa-whatsapp" style="color: #25D366; margin-right: 6px;"></i> Preview Pesan</h4>
                            <button type="button" onclick="closePreview(<?= $index ?>)" style="background: none; border: none; color: #D64F3C; cursor: pointer; font-size: 18px;">&times;</button>
                        </div>
                        <div style="background: white; border-radius: 10px; padding: 16px; white-space: pre-wrap; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.6; max-height: 400px; overflow-y: auto;" id="preview_content_<?= $index ?>"></div>
                    </div>
                    
                    <!-- Footer Card -->
                    <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 16px; padding-top: 16px; border-top: 2px solid #E7F3EF;">
                        <div style="background: #F5F3F0; padding: 6px 16px; border-radius: 40px; display: flex; align-items: center; gap: 8px;">
                            <span style="color: #4A5A54; font-size: 11px;">ID Lokasi:</span>
                            <code style="background: white; padding: 4px 12px; border-radius: 30px; color: #D64F3C; font-weight: 600; font-size: 11px; border: 1px solid #E0DAD3;"><?= $loc_key ?></code>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Save Button -->
        <button type="submit" style="background: linear-gradient(135deg, #1B4A3C 0%, #2A5F4E 100%); color: white; border: none; padding: 16px 28px; border-radius: 50px; font-weight: 700; font-size: 15px; cursor: pointer; width: 100%; max-width: 300px; margin: 30px auto 20px; display: block; box-shadow: 0 15px 35px rgba(27, 74, 60, 0.3);">
            <i class="fas fa-save" style="margin-right: 8px;"></i> SIMPAN SEMUA PESAN
        </button>
    </form>
    
    <!-- FOOTER -->
    <div style="text-align: center; margin-top: 40px; padding: 20px; color: #7A8A84; font-size: 12px; border-top: 1px solid #E0DAD3;">
        <p>© <?= date('Y') ?> TaufikMarie.com - WhatsApp CMS Version 11.3.0</p>
    </div>
    
</div>

<script>
    const sampleData = {
        name: 'Budi',
        full_name: 'Budi Santoso',
        marketing: 'Taufik Marie',
        location: 'Kertamulya Residence',
        icon: '🏡',
        customer_name: 'Budi',
        marketing_name: 'Taufik Marie',
        marketing_phone: '628133150078'
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
    
    function updateCounter(textareaId, counterId) {
        const textarea = document.getElementById(textareaId);
        const counter = document.getElementById(counterId);
        const length = textarea.value.length;
        
        counter.textContent = length + ' karakter';
        
        if (length > 1000) {
            counter.style.color = '#D64F3C';
            counter.style.fontWeight = '700';
        } else if (length > 800) {
            counter.style.color = '#E9C46A';
            counter.style.fontWeight = '600';
        } else {
            counter.style.color = '#7A8A84';
            counter.style.fontWeight = '400';
        }
    }
    
    function resetMessage(locationKey, type) {
        const defaults = <?= json_encode($default_messages) ?>;
        const textarea = document.getElementById(type === 'pesan_cs' ? 'msg_cs_' + locationKey : type + '_' + locationKey);
        
        if (textarea && defaults[type]) {
            textarea.value = defaults[type];
            updateCounter(
                type === 'pesan_cs' ? 'msg_cs_' + locationKey : type + '_' + locationKey,
                type === 'pesan_cs' ? 'counter_cs_' + locationKey : 'counter' + type.slice(-1) + '_' + locationKey
            );
        }
    }
    
    function previewMessages(index) {
        const panel = document.getElementById('preview_' + index);
        const content = document.getElementById('preview_content_' + index);
        
        const locationKey = document.querySelectorAll('.accordion-item')[index].querySelector('input[name*="[pesan1]"]')?.id.replace('msg1_', '');
        
        if (!locationKey) return;
        
        let previewText = '';
        
        // Preview CS
        const csTextarea = document.getElementById('msg_cs_' + locationKey);
        if (csTextarea) {
            let msg = csTextarea.value;
            msg = msg.replace(/{customer_name}/g, sampleData.customer_name);
            msg = msg.replace(/{marketing_name}/g, sampleData.marketing_name);
            msg = msg.replace(/{marketing_phone}/g, sampleData.marketing_phone);
            msg = msg.replace(/{location}/g, sampleData.location);
            msg = msg.replace(/{icon}/g, sampleData.icon);
            previewText += '📞 PESAN CS:\n' + msg + '\n\n' + '─'.repeat(40) + '\n\n';
        }
        
        for (let i = 1; i <= 3; i++) {
            const textarea = document.getElementById('msg' + i + '_' + locationKey);
            if (textarea) {
                let msg = textarea.value;
                msg = msg.replace(/{name}/g, sampleData.name);
                msg = msg.replace(/{full_name}/g, sampleData.full_name);
                msg = msg.replace(/{marketing}/g, sampleData.marketing);
                msg = msg.replace(/{location}/g, sampleData.location);
                msg = msg.replace(/{icon}/g, sampleData.icon);
                
                previewText += '📨 PESAN ' + i + ':\n' + msg + '\n\n' + '─'.repeat(40) + '\n\n';
            }
        }
        
        content.textContent = previewText;
        panel.style.display = 'block';
    }
    
    function closePreview(index) {
        document.getElementById('preview_' + index).style.display = 'none';
    }
    
    function copyPlaceholders() {
        const placeholders = '{name}, {full_name}, {marketing}, {location}, {icon}, {customer_name}, {marketing_name}, {marketing_phone}';
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
    document.getElementById('messagesForm').addEventListener('input', function() {
        formChanged = true;
    });
    document.getElementById('messagesForm').addEventListener('change', function() {
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