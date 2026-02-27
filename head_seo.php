<?php
/**
 * KERTAMULYA RESIDENCE - LANDING PAGE
 * Developer ID: 3
 */

// Include SEO dari admin
$seo_path = dirname(__DIR__) . '/admin/includes/head_seo.php';
if (file_exists($seo_path)) {
    include $seo_path;
}
?>
<!DOCTYPE html>
<html lang="id-ID">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        /* ===== CSS SAMA SEPERTI FILE index.html ANDA ===== */
        /* Copy semua CSS dari file index.html Anda di sini */
        :root {
            --primary: #D64F3C;
            --primary-light: #FF6B4A;
            --secondary: #2A9D8F;
            --dark: #1B4A3C;
            --text: #1A2A24;
            --border: #E0E7E0;
            --bg: #F5F7FA;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        
        .desktop-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            min-height: 100vh;
        }
        
        .video-side {
            background: #000;
            position: relative;
        }
        
        .video-side video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .video-caption {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .content-side {
            padding: 40px;
            overflow-y: auto;
            max-height: 100vh;
            background: white;
        }
        
        .social-proof {
            background: linear-gradient(135deg, var(--secondary), #1E7A6F);
            border-radius: 60px;
            padding: 12px 20px;
            margin-bottom: 24px;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .badge-super {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 24px;
        }
        
        .headline {
            font-size: 42px;
            font-weight: 800;
            color: var(--dark);
            line-height: 1.2;
            margin-bottom: 16px;
        }
        
        .headline span {
            color: var(--primary);
            display: block;
            font-size: 32px;
            margin-top: 8px;
        }
        
        .subheadline {
            font-size: 18px;
            color: #4A5A54;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .price-super {
            background: linear-gradient(145deg, #0A2A21, var(--dark));
            border-radius: 28px;
            padding: 32px;
            margin: 24px 0;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .price-number {
            font-size: 64px;
            font-weight: 800;
            color: #E3B584;
            line-height: 1;
        }
        
        .price-number small {
            font-size: 24px;
            color: rgba(255,255,255,0.8);
        }
        
        .price-caption {
            font-size: 16px;
            font-weight: 600;
            background: rgba(255,255,255,0.1);
            padding: 12px 20px;
            border-radius: 60px;
            display: inline-block;
            margin-top: 16px;
        }
        
        .benefits-3x2 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 24px 0;
        }
        
        .benefit-card {
            background: #F5F7F5;
            border-radius: 18px;
            padding: 16px 10px;
            text-align: center;
            border: 1px solid var(--border);
        }
        
        .benefit-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            margin: 0 auto 12px;
        }
        
        .benefit-title {
            font-weight: 700;
            font-size: 14px;
            color: var(--dark);
            margin-bottom: 4px;
        }
        
        .benefit-sub {
            font-size: 12px;
            color: var(--primary);
            font-weight: 600;
        }
        
        .countdown-super {
            background: linear-gradient(145deg, var(--primary), var(--primary-light));
            border-radius: 30px;
            padding: 30px 25px;
            margin: 24px 0;
            color: white;
            text-align: center;
        }
        
        .countdown-numbers {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 20px 0;
        }
        
        .countdown-block {
            background: rgba(255,255,255,0.2);
            padding: 15px 20px;
            border-radius: 25px;
            min-width: 100px;
        }
        
        .countdown-block span {
            display: block;
            font-size: 42px;
            font-weight: 800;
        }
        
        .countdown-block small {
            font-size: 14px;
        }
        
        .rating-super {
            display: flex;
            gap: 24px;
            margin-top: 20px;
        }
        
        .rating-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .rating-icon {
            width: 44px;
            height: 44px;
            background: #F5F7F5;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #E3B584;
            border: 1px solid var(--border);
        }
        
        .rating-number {
            font-weight: 800;
            font-size: 18px;
            color: var(--dark);
        }
        
        .rating-label {
            font-size: 12px;
            color: #4A5A54;
        }
        
        .form-super {
            background: white;
            border-radius: 32px;
            padding: 35px 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            margin-top: 30px;
        }
        
        .form-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
            text-align: center;
            margin-bottom: 8px;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: var(--dark);
            margin-bottom: 6px;
        }
        
        .form-input {
            width: 100%;
            padding: 16px 18px;
            border: 2px solid var(--border);
            border-radius: 18px;
            font-size: 15px;
            background: #FAFCFA;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .agree-box {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            background: #E7F3EF;
            padding: 14px 18px;
            border-radius: 18px;
        }
        
        .btn-cta {
            width: 100%;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 800;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .trust-badges {
            display: flex;
            justify-content: center;
            gap: 24px;
            font-size: 12px;
            color: #4A5A54;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        
        .trust-badges i {
            color: var(--secondary);
        }
        
        .floating-mobile {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            display: none;
            z-index: 9998;
        }
        
        .floating-mobile.show {
            display: block;
        }
        
        .floating-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            border-radius: 60px;
            font-weight: 800;
            font-size: 18px;
            cursor: pointer;
        }
        
        .hidden {
            display: none !important;
        }
        
        .modal-thankyou {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            z-index: 999999;
        }
        
        .modal-thankyou.show {
            display: flex;
        }
        
        .thankyou-card {
            background: white;
            border-radius: 40px;
            max-width: 500px;
            width: 90%;
            padding: 30px 25px;
            text-align: center;
        }
        
        @media (max-width: 1024px) {
            .desktop-grid {
                grid-template-columns: 1fr;
            }
            
            .video-side {
                height: 50vh;
            }
            
            .content-side {
                max-height: none;
                padding: 30px 20px;
            }
            
            .headline {
                font-size: 32px;
                text-align: center;
            }
            
            .headline span {
                font-size: 26px;
            }
        }
        
        @media (max-width: 768px) {
            .benefits-3x2 {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .countdown-block {
                min-width: 70px;
                padding: 10px 8px;
            }
            
            .countdown-block span {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="desktop-grid">
        <!-- LEFT SIDE: VIDEO -->
        <div class="video-side">
            <video id="mainVideo" autoplay loop playsinline poster="/kertamulya/rumahsubsidi.webp">
                <source src="/kertamulya/kertamulya-1.webm" type="video/webm">
                Browser Anda tidak mendukung video.
            </video>
            <div class="video-caption" id="videoCaption">
                <i class="fas fa-volume-up"></i> Dengan Suara
            </div>
        </div>
        
        <!-- RIGHT SIDE: CONTENT -->
        <div class="content-side" id="formState">
            <!-- SOCIAL PROOF -->
            <div class="social-proof" id="liveNotif">
                <i class="fas fa-user-check"></i>
                <span id="notifMessage">Budi baru booking 2 menit lalu</span>
            </div>
            
            <!-- BADGE -->
            <div class="badge-super">
                <i class="fas fa-fire"></i> PROMO SUBSIDI 2026 - TERBATAS
            </div>
            
            <!-- HEADLINE -->
            <h1 class="headline">
                CUMA 500RB 
                <span>SUDAH PUNYA RUMAH</span>
            </h1>
            
            <!-- SUBHEADLINE -->
            <div class="subheadline">
                <i class="fas fa-check-circle"></i> 
                <span>Booking Fee All-In • Siap Tinggal</span>
            </div>
            
            <!-- PRICE -->
            <div class="price-super">
                <div class="price-number">
                    500RB <small>Booking fee</small>
                </div>
                <div class="price-caption">
                    <i class="fas fa-gem"></i> CICILAN 1 JUTA/BULAN
                </div>
            </div>
            
            <!-- BENEFITS -->
            <div class="benefits-3x2">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-percent"></i></div>
                    <div class="benefit-title">GRATIS PPN 11%</div>
                    <div class="benefit-sub">Hemat Rp 18 Jt</div>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-file-invoice"></i></div>
                    <div class="benefit-title">GRATIS BPHTB</div>
                    <div class="benefit-sub">Hemat Rp 8 Jt</div>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-home"></i></div>
                    <div class="benefit-title">SIAP HUNI</div>
                    <div class="benefit-sub">Keramik 40x40</div>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-mosque"></i></div>
                    <div class="benefit-title">MASJID CLUSTER</div>
                    <div class="benefit-sub">Al-Fawwaz</div>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-gift"></i></div>
                    <div class="benefit-title">BONUS KOMPOR</div>
                    <div class="benefit-sub">Rp 800.000</div>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-university"></i></div>
                    <div class="benefit-title">SUBSIDI BANK</div>
                    <div class="benefit-sub">Rp 35 Juta</div>
                </div>
            </div>
            
            <!-- COUNTDOWN -->
            <div class="countdown-super">
                <div class="countdown-label">
                    <i class="fas fa-clock"></i> PROMO BERAKHIR
                </div>
                <div class="countdown-numbers" id="countdownTimer">
                    <div class="countdown-block">
                        <span>23</span>
                        <small>Jam</small>
                    </div>
                    <div class="countdown-block">
                        <span>59</span>
                        <small>Menit</small>
                    </div>
                    <div class="countdown-block">
                        <span>59</span>
                        <small>Detik</small>
                    </div>
                </div>
                <div class="stock-left">
                    <i class="fas fa-fire"></i> Hanya 3 unit tersisa!
                </div>
            </div>
            
            <!-- RATING -->
            <div class="rating-super">
                <div class="rating-item">
                    <div class="rating-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="rating-text">
                        <span class="rating-number">4.9/5.0</span>
                        <span class="rating-label">128 Review</span>
                    </div>
                </div>
                <div class="rating-item">
                    <div class="rating-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="rating-text">
                        <span class="rating-number">386+</span>
                        <span class="rating-label">Penghuni</span>
                    </div>
                </div>
            </div>
            
            <!-- FORM -->
            <div class="form-super">
                <h2 class="form-title">
                    DAPATKAN BONUS <br>Rp 35.000.000
                </h2>
                <div class="form-sub">
                    <i class="fas fa-bolt"></i> Marketing akan WA dalam 5 menit
                </div>
                
                <form id="leadForm">
                    <input type="hidden" name="developer_id" value="3">
                    <input type="hidden" name="location" value="kertamulya">
                    <input type="hidden" name="source" value="super_landing_2026">
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Nama Lengkap</label>
                        <input type="text" id="first_name" name="first_name" class="form-input" placeholder="Contoh: Budi Santoso" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fab fa-whatsapp"></i> Nomor WhatsApp</label>
                        <input type="tel" id="phone" name="phone" class="form-input" placeholder="0812 3456 7890" required maxlength="13">
                        <div class="validation-msg" id="phone_validation"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-envelope"></i> Email (opsional)</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="nama@email.com">
                    </div>
                    
                    <div class="agree-box">
                        <input type="checkbox" id="agree" checked disabled>
                        <label for="agree">Saya setuju syarat & ketentuan</label>
                    </div>
                    
                    <button type="submit" class="btn-cta" id="submitBtn">
                        <i class="fas fa-bolt"></i> CLAIM BONUS & INFO
                    </button>
                    
                    <div class="trust-badges">
                        <span><i class="fas fa-shield-alt"></i> SSL 256-bit</span>
                        <span><i class="fas fa-lock"></i> Data Terenkripsi</span>
                        <span><i class="fas fa-check-circle"></i> Resmi & Terpercaya</span>
                    </div>
                </form>
            </div>
            
            <!-- FOOTER -->
            <div style="text-align: center; padding: 30px 0 0; font-size: 12px; color: #4A5A54;">
                <p>© 2026 Kertamulya Residence - PT Rumah Mulia Indonesia</p>
            </div>
        </div>
    </div>
    
    <!-- FLOATING CTA -->
    <div class="floating-mobile" id="floatingCta">
        <button class="floating-btn" onclick="document.querySelector('.form-super').scrollIntoView({behavior: 'smooth'})">
            <i class="fas fa-pen"></i> ISI FORM & CLAIM BONUS
        </button>
    </div>
    
    <!-- THANK YOU MODAL -->
    <div class="modal-thankyou" id="thankyouModal">
        <div class="thankyou-card">
            <div class="thankyou-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="thankyou-title">TERIMA KASIH!</h3>
            <p class="thankyou-message">
                Data Anda telah kami terima. Tim marketing akan segera menghubungi via WhatsApp.
            </p>
            <button class="thankyou-close" onclick="closeThankYou()">
                Tutup
            </button>
        </div>
    </div>
    
    <script>
        // Video
        document.addEventListener('DOMContentLoaded', function() {
            const video = document.getElementById('mainVideo');
            if (video) {
                video.volume = 0.5;
                video.play().catch(() => {
                    video.muted = true;
                    video.play();
                });
            }
        });
        
        // Social proof
        const buyers = [
            { name: 'Budi', time: '2 menit' },
            { name: 'Siti', time: '5 menit' },
            { name: 'Ahmad', time: '8 menit' }
        ];
        
        let index = 0;
        setInterval(() => {
            const b = buyers[index % buyers.length];
            document.getElementById('notifMessage').innerHTML = 
                `${b.name} baru booking ${b.time} lalu`;
            index++;
        }, 12000);
        
        // Form submit
        document.getElementById('leadForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const name = document.getElementById('first_name').value.trim();
            const phone = document.getElementById('phone').value.replace(/\D/g, '');
            
            if (!name || phone.length < 10) {
                alert('Isi data dengan benar');
                return;
            }
            
            document.getElementById('formState').classList.add('hidden');
            document.getElementById('thankyouModal').classList.add('show');
            
            // Kirim ke API
            const formData = new FormData(this);
            fetch('/admin/api/api_master.php', {
                method: 'POST',
                body: formData,
                keepalive: true
            }).catch(() => {});
        });
        
        window.closeThankYou = function() {
            document.getElementById('thankyouModal').classList.remove('show');
            document.getElementById('formState').classList.remove('hidden');
        };
        
        // Countdown
        function startCountdown() {
            const end = new Date();
            end.setHours(23, 59, 59, 0);
            
            function update() {
                const now = new Date().getTime();
                const distance = end.getTime() - now;
                
                if (distance < 0) return;
                
                const hours = Math.floor(distance / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById('countdownTimer').innerHTML = `
                    <div class="countdown-block"><span>${hours.toString().padStart(2, '0')}</span><small>Jam</small></div>
                    <div class="countdown-block"><span>${minutes.toString().padStart(2, '0')}</span><small>Menit</small></div>
                    <div class="countdown-block"><span>${seconds.toString().padStart(2, '0')}</span><small>Detik</small></div>
                `;
            }
            
            update();
            setInterval(update, 1000);
        }
        startCountdown();
    </script>
</body>
</html>