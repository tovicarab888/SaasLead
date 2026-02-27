<?php
/**
 * MODALS_MARKETING.PHP - LEADENGINE
 * Version: 8.0.0 - FULL CODE PASTI BERHASIL
 * 100% LENGKAP TANPA POTONGAN
 */
?>

<!-- ===== MODAL VIEW LEAD ===== -->
<div class="modal" id="viewModal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2><i class="fas fa-eye"></i> Detail Lead</h2>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body" id="viewModalBody">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin fa-3x" style="color: #D64F3C;"></i>
                <p style="margin-top: 16px; color: #7A8A84;">Memuat data...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeViewModal()">Tutup</button>
            <button class="btn-primary" id="editFromViewBtn" onclick="editFromView()">Edit Lead</button>
        </div>
    </div>
</div>

<!-- ===== MODAL EDIT LEAD ===== -->
<div class="modal" id="editModal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Lead</h2>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <form id="editLeadForm" onsubmit="submitEditLead(event)">
                <input type="hidden" name="id" id="edit_id" value="">
                <input type="hidden" name="marketing_id" id="edit_marketing_id" value="<?= $_SESSION['marketing_id'] ?? 0 ?>">
                
                <!-- Customer Info Card -->
                <div style="background: linear-gradient(135deg, #E7F3EF, #d4e8e0); border-radius: 16px; padding: 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 20px; border-left: 4px solid #D64F3C;">
                    <div style="width: 64px; height: 64px; background: #1B4A3C; border-radius: 18px; display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; flex-shrink: 0;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; font-size: 20px; color: #1B4A3C; margin-bottom: 6px;" id="edit_customer_name">-</div>
                        <div style="display: flex; align-items: center; gap: 8px; color: #4A5A54; font-size: 16px;" id="edit_phone">-</div>
                    </div>
                </div>
                
                <!-- Form Grid -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                    <!-- Status - Full Width -->
                    <div style="grid-column: span 2; margin-bottom: 8px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                            <i class="fas fa-tag" style="color: #D64F3C; margin-right: 6px;"></i> Status
                        </label>
                        <select name="status" id="edit_status" style="width: 100%; padding: 12px 16px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px; background: white;" required>
                            <option value="Baru">Baru</option>
                            <option value="Follow Up">Follow Up</option>
                            <option value="Survey">Survey</option>
                            <option value="Booking">Booking</option>
                            <option value="Tolak Slik">Tolak Slik</option>
                            <option value="Tidak Minat">Tidak Minat</option>
                            <option value="Deal KPR">Deal KPR</option>
                            <option value="Deal Tunai">Deal Tunai</option>
                            <option value="Deal Bertahap 6 Bulan">Deal Bertahap 6 Bulan</option>
                            <option value="Deal Bertahap 1 Tahun">Deal Bertahap 1 Tahun</option>
                            <option value="Batal">Batal</option>
                        </select>
                    </div>
                    
                    <!-- Email -->
                    <div style="margin-bottom: 8px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                            <i class="fas fa-envelope" style="color: #D64F3C; margin-right: 6px;"></i> Email
                        </label>
                        <input type="email" name="email" id="edit_email" value="" style="width: 100%; padding: 12px 16px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px;" placeholder="email@domain.com">
                    </div>
                    
                    <!-- Tipe Unit -->
                    <div style="margin-bottom: 8px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                            <i class="fas fa-home" style="color: #D64F3C; margin-right: 6px;"></i> Tipe Unit
                        </label>
                        <input type="text" name="unit_type" id="edit_unit_type" value="Type 36/60" style="width: 100%; padding: 12px 16px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px;">
                    </div>
                    
                    <!-- Program -->
                    <div style="margin-bottom: 8px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                            <i class="fas fa-coins" style="color: #D64F3C; margin-right: 6px;"></i> Program
                        </label>
                        <input type="text" name="program" id="edit_program" value="Subsidi" style="width: 100%; padding: 12px 16px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px;">
                    </div>
                    
                    <!-- Kota -->
                    <div style="margin-bottom: 8px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                            <i class="fas fa-city" style="color: #D64F3C; margin-right: 6px;"></i> Kota
                        </label>
                        <input type="text" name="city" id="edit_city" value="" style="width: 100%; padding: 12px 16px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px;" placeholder="Kota">
                    </div>
                    
                    <!-- Alamat - Full Width -->
                    <div style="grid-column: span 2; margin-bottom: 8px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                            <i class="fas fa-map-marker-alt" style="color: #D64F3C; margin-right: 6px;"></i> Alamat
                        </label>
                        <textarea name="address" id="edit_address" rows="2" style="width: 100%; padding: 12px 16px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px; resize: vertical;" placeholder="Alamat lengkap"></textarea>
                    </div>
                    
                    <!-- Catatan - Full Width -->
                    <div style="grid-column: span 2; margin-bottom: 8px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                            <i class="fas fa-sticky-note" style="color: #D64F3C; margin-right: 6px;"></i> Catatan
                        </label>
                        <textarea name="notes" id="edit_notes" rows="3" style="width: 100%; padding: 12px 16px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px; resize: vertical;" placeholder="Catatan untuk lead ini..."></textarea>
                        <small style="display: block; margin-top: 6px; font-size: 12px; color: #D64F3C;">
                            <i class="fas fa-info-circle"></i> Perubahan status akan mempengaruhi Lead Score
                        </small>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- FOOTER -->
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeEditModal()">Batal</button>
            <button type="submit" class="btn-primary" form="editLeadForm">Simpan Perubahan</button>
        </div>
    </div>
</div>

<!-- ===== MODAL TAMBAH CATATAN ===== -->
<div class="modal" id="noteModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2><i class="fas fa-sticky-note"></i> Tambah Catatan</h2>
            <button class="modal-close" onclick="closeNoteModal()">&times;</button>
        </div>
        <form id="noteForm" onsubmit="submitNote(event)">
            <input type="hidden" name="lead_id" id="note_lead_id">
            
            <div class="modal-body">
                <!-- Customer Info -->
                <div style="background: linear-gradient(135deg, #E7F3EF, #d4e8e0); border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-weight: 600; color: #1B4A3C; display: flex; align-items: center; gap: 10px; border-left: 4px solid #D64F3C;" id="note_customer_name"></div>
                
                <!-- STATUS AKTIVITAS (Bukan Status Lead) -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1B4A3C; font-size: 13px;">
                        <i class="fas fa-tasks" style="color: #D64F3C; margin-right: 6px;"></i> Status Aktivitas
                    </label>
                    <select name="action_type" id="note_action_type" style="width: 100%; padding: 12px 16px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px; background: white;" required>
                        <option value="">-- Pilih Status Aktivitas --</option>
                        <option value="follow_up">📞 Follow Up</option>
                        <option value="call">📱 Telepon</option>
                        <option value="whatsapp">💬 WhatsApp</option>
                        <option value="survey">📍 Survey</option>
                        <option value="booking">📝 Booking</option>
                        <option value="cek_slik">🔍 Cek Slik</option>
                        <option value="utj">💰 UTJ (Uang Tanda Jadi)</option>
                        <option value="pemberkasan">📋 Pemberkasan</option>
                        <option value="proses_bank">🏦 Proses Bank</option>
                        <option value="akad">📝 Akad</option>
                        <option value="serah_terima">🔑 Serah Terima Kunci</option>
                    </select>
                    <small style="display: block; margin-top: 6px; color: #D64F3C; font-size: 11px;">
                        <i class="fas fa-info-circle"></i> Pilih status aktivitas untuk melacak progress KPR
                    </small>
                </div>
                
                <!-- Isi Catatan -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #1B4A3C; font-size: 13px;">
                        <i class="fas fa-pen" style="color: #D64F3C; margin-right: 6px;"></i> Catatan Detail
                    </label>
                    <textarea name="note" id="note_text" rows="4" style="width: 100%; padding: 12px 16px; border: 2px solid #E0DAD3; border-radius: 12px; font-size: 14px; resize: vertical;" required placeholder="Tulis detail aktivitas..."></textarea>
                </div>
                
                <!-- Tips -->
                <div style="background: #E7F3EF; border-radius: 8px; padding: 12px; font-size: 13px; color: #4A5A54; display: flex; align-items: flex-start; gap: 10px;">
                    <i class="fas fa-lightbulb" style="color: #D64F3C; font-size: 16px; margin-top: 2px;"></i>
                    <span>
                        <strong>Tips:</strong> Catat hasil setiap tahapan. Contoh: "Slik OJK clear", "UTJ sudah transfer", "Dokumen lengkap", "Akad jadwal 5 Maret".
                    </span>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeNoteModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan Catatan</button>
            </div>
        </form>
    </div>
</div>

<style>
/* ===== MODAL STYLES ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.modal.show {
    display: flex !important;
}

.modal-content {
    background: white;
    border-radius: 28px;
    width: 100%;
    max-width: 700px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 30px 60px rgba(0,0,0,0.3);
    animation: modalFade 0.3s ease;
}

@keyframes modalFade {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 2px solid #E7F3EF;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, white, #fafafa);
    flex-shrink: 0;
    border-radius: 28px 28px 0 0;
}

.modal-header h2 {
    color: #1B4A3C;
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h2 i {
    color: #D64F3C;
    font-size: 22px;
}

.modal-close {
    width: 40px;
    height: 40px;
    background: #E7F3EF;
    border: none;
    border-radius: 12px;
    color: #D64F3C;
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.modal-close:hover {
    background: #D64F3C;
    color: white;
    transform: rotate(90deg);
}

.modal-close:active {
    transform: scale(0.95) rotate(90deg);
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
    background: white;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 2px solid #E7F3EF;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    background: #fafafa;
    flex-shrink: 0;
    border-radius: 0 0 28px 28px;
}

/* ===== FORM ELEMENTS ===== */
.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #1B4A3C;
    font-size: 14px;
    letter-spacing: 0.3px;
}

.form-group label i {
    color: #D64F3C;
    margin-right: 8px;
    width: 20px;
}

.form-control, .form-select {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #E0DAD3;
    border-radius: 16px;
    font-size: 15px;
    font-family: 'Inter', sans-serif;
    background: white;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #D64F3C;
    outline: none;
    box-shadow: 0 0 0 4px rgba(214, 79, 60, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.form-control[readonly] {
    background: #f5f5f5;
    border-color: #ddd;
    color: #666;
    cursor: not-allowed;
}

/* ===== BUTTONS ===== */
.btn-primary {
    background: linear-gradient(135deg, #1B4A3C, #2A5F4E);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(27,74,60,0.2);
    transition: all 0.2s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(27,74,60,0.3);
}

.btn-primary:active {
    transform: translateY(0);
}

.btn-secondary {
    background: #E0DAD3;
    color: #1A2A24;
    border: none;
    padding: 12px 24px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: #7A8A84;
    color: white;
    transform: translateY(-2px);
}

.btn-secondary:active {
    transform: translateY(0);
}

/* ===== NOTE MODAL SPECIAL ===== */
#noteModal select option {
    padding: 8px;
}

#noteModal select option:first-child {
    color: #7A8A84;
    font-style: italic;
}

#noteModal select option[disabled] {
    background: #f5f5f5;
    color: #999;
    font-size: 11px;
    text-align: center;
}

#noteModal select option[value="follow_up"] { background-color: #e3f2fd; }
#noteModal select option[value="call"] { background-color: #e8f5e9; }
#noteModal select option[value="whatsapp"] { background-color: #d4edda; }
#noteModal select option[value="survey"] { background-color: #fff3e0; }
#noteModal select option[value="booking"] { background-color: #e8d4ff; }
#noteModal select option[value="cek_slik"] { background-color: #fff3cd; }
#noteModal select option[value="utj"] { background-color: #d4edda; }
#noteModal select option[value="pemberkasan"] { background-color: #d1ecf1; }
#noteModal select option[value="proses_bank"] { background-color: #cce5ff; }
#noteModal select option[value="akad"] { background-color: #e8d4ff; }
#noteModal select option[value="serah_terima"] { background-color: #d6f0d6; }

/* ===== MOBILE RESPONSIVE ===== */
@media (max-width: 768px) {
    .modal-content {
        max-height: 85vh;
    }
    
    .modal-header {
        padding: 16px 20px;
    }
    
    .modal-header h2 {
        font-size: 18px;
    }
    
    .modal-close {
        width: 36px;
        height: 36px;
        font-size: 18px;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .modal-footer {
        padding: 16px 20px;
    }
    
    .modal-footer button {
        flex: 1;
    }
    
    /* Grid 2 kolom di mobile */
    [style*="grid-template-columns: repeat(2, 1fr)"] {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px;
    }
}

@media (max-width: 480px) {
    .modal-footer {
        flex-direction: row;
    }
    
    .modal-footer button {
        padding: 10px 12px;
        font-size: 13px;
    }
    
    [style*="grid-template-columns: repeat(2, 1fr)"] {
        grid-template-columns: 1fr !important;
    }
    
    [style*="grid-column: span 2"] {
        grid-column: span 1 !important;
    }
}
</style>