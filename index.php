<!DOCTYPE html>
<?php
// Deteksi apakah di localhost atau production
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

require_once __DIR__ . '/../admin/api/config.php';
$csrf_token = generateCSRFToken();
?>
<html lang="id-ID" dir="ltr" prefix="og: https://ogp.me/ns# fb: https://ogp.me/ns/fb# product: https://ogp.me/ns/product#">
<head>
    <!-- ========== META SUPER SEO 2026 (LENGKAP) ========== -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="#0b5b2f">
    
    <!-- ========== SEO TERPUSAT - DIAMBIL DARI DATABASE ========== -->
    <?php
    // ===== DEBUGGING MODE - HAPUS NANTI =====
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // DETEKSI DEVELOPER ID DARI URL
    $dev_id = isset($_GET['dev_id']) ? (int)$_GET['dev_id'] : 0;
    if ($dev_id > 0) {
        $_GET['developer_id'] = $dev_id;
    }
    
    // DEBUG: Cek apakah developer_id terbaca
    echo "<!-- DEBUG: dev_id = $dev_id -->";
    
    // INCLUDE FILE SEO - PASTIKAN PATH BENAR
    $seo_path = __DIR__ . '/admin/includes/head_seo.php';
    echo "<!-- DEBUG: SEO Path = $seo_path -->";
    echo "<!-- DEBUG: File exists = " . (file_exists($seo_path) ? 'YES' : 'NO') . " -->";
    
    if (file_exists($seo_path)) {
        include $seo_path;
        echo "<!-- DEBUG: SEO included successfully -->";
    } else {
        // FALLBACK MANUAL
        echo "<!-- DEBUG: Using fallback SEO -->";
        ?>
        <title>🔥 CUMA 500RB! Rumah Subsidi Kertamulya Kuningan - KPR 1 Juta-an</title>
        <meta name="description" content="✅ DENGAN 500RB ANDA SUDAH PUNYA RUMAH! Booking Fee 500rb All-In, KPR Syariah Flat 1 Juta-an, Bebas PPN 11% & BPHTB">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="https://leadproperti.com/?dev_id=<?= $dev_id ?>">
        <?php
    }
    ?>
    
    <!-- ========== FONTS & ICONS ========== -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- ========== TRACKING PIXELS (CLIENT-SIDE) ========== -->
    <script>
    (function() {
        if (window.trackingLoaded) return;
        window.trackingLoaded = true;
        
        // Ambil developer_id dari URL untuk tracking spesifik
        const urlParams = new URLSearchParams(window.location.search);
        const devId = urlParams.get('dev_id') || urlParams.get('developer_id') || '';
        
        let apiUrl = '/admin/api/get_tracking_config.php';
        if (devId) {
            apiUrl += '?developer_id=' + devId;
        }
        
        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                if (!data.success) return;
                
                const config = data.data || {};
                console.log('🚀 Tracking config loaded:', config);
                
                // Meta Pixel
                if (config.meta && config.meta.pixel_id && config.meta.is_active) {
                    !function(f,b,e,v,n,t,s) {
                        if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
                        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
                        n.queue=[];t=b.createElement(e);t.async=!0;
                        t.src=v;s=b.getElementsByTagName(e)[0];
                        s.parentNode.insertBefore(t,s)
                    }(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
                    
                    fbq('init', config.meta.pixel_id);
                    fbq('track', 'PageView');
                    console.log('✅ Meta Pixel loaded:', config.meta.pixel_id);
                }
                
                // TikTok Pixel
                if (config.tiktok && config.tiktok.pixel_id && config.tiktok.is_active) {
                    !function (w, d, t) {
                        w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(
                        var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script")
                        ;n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
                        
                        ttq.load(config.tiktok.pixel_id);
                        ttq.page();
                        console.log('✅ TikTok Pixel loaded:', config.tiktok.pixel_id);
                    }(window, document, 'ttq');
                }
                
                // Google Analytics
                if (config.google && config.google.measurement_id && config.google.is_active) {
                    const script = document.createElement('script');
                    script.async = true;
                    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + config.google.measurement_id;
                    document.head.appendChild(script);
                    
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    gtag('js', new Date());
                    gtag('config', config.google.measurement_id, {
                        'send_page_view': true,
                        'cookie_domain': 'leadproperti.com'
                    });
                    window.gtag = gtag;
                    console.log('✅ Google Analytics loaded:', config.google.measurement_id);
                }
            })
            .catch(error => console.error('Error loading tracking config:', error));
    })();
    </script>
    
    <!-- ========== PROTECTION SYSTEM ========== -->
    <script>
    (function() {
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            showProtectModal('Hai! Konten ini dilindungi ya. Tapi kamu bisa hubungi kami via WhatsApp untuk info lebih lanjut 😊');
            return false;
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || e.keyCode === 123 || (e.ctrlKey && e.key === 'u') || (e.ctrlKey && e.shiftKey && e.key === 'I')) {
                e.preventDefault();
                showProtectModal('Maaf ya, fitur ini tidak bisa digunakan. Tapi kami siap bantu via WhatsApp kok! 👋');
                return false;
            }
        });
        
        function showProtectModal(message) {
            const modal = document.createElement('div');
            modal.className = 'modal-protect show';
            modal.innerHTML = `
                <div class="protect-card">
                    <div class="protect-icon">
                        <i class="fas fa-smile"></i>
                    </div>
                    <h3 class="protect-title">Ada yang bisa dibantu?</h3>
                    <p class="protect-text">${message || 'Konten ini dilindungi. Tapi jangan ragu hubungi kami via WhatsApp ya!'}</p>
                    <button class="protect-btn" onclick="this.closest('.modal-protect').remove()">
                        <i class="fas fa-check"></i> OK, Saya Mengerti
                    </button>
                </div>
            `;
            document.body.appendChild(modal);
            
            setTimeout(() => {
                if (modal.parentNode) {
                    modal.remove();
                }
            }, 4000);
        }
        
        window.showProtectModal = showProtectModal;
    })();
    </script>
    
    <!-- ========== CSS SUPER OPTIMIZED ========== -->
    <style>
        /* ===== RESET TOTAL - LIGHT MODE ONLY ===== */
        :root {
            --primary: #D64F3C;
            --primary-dark: #B33A2A;
            --primary-light: #FF6B4A;
            --secondary: #2A9D8F;
            --secondary-dark: #1E7A6F;
            --accent: #E3B584;
            --dark: #1B4A3C;
            --dark-bg: #0A2A21;
            --light: #F5F7FA;
            --light-bg: #FFFFFF;
            --text-dark: #1A2A24;
            --text-light: #4A5A54;
            --border: #E0E7E0;
            --shadow: rgba(0,0,0,0.1);
            --success: #2A9D8F;
            --warning: #D64F3C;
        }

        /* PAKSA LIGHT MODE SELALU */
        body {
            background: #F5F7FA !important;
            color: #1A2A24 !important;
        }

        .main-container, .form-super, .benefit-card {
            background: white !important;
            color: #1A2A24 !important;
        }
        
        /* COUNTDOWN TIMER - TETAP WARNA MERAH */
        .countdown-super {
            background: linear-gradient(145deg, #D64F3C, #FF6B4A) !important;
            color: white !important;
        }
        
        .countdown-block {
            background: rgba(255,255,255,0.2) !important;
            border: 2px solid rgba(255,255,255,0.3) !important;
        }
        
        .countdown-block span, 
        .countdown-block small,
        .countdown-label,
        .stock-left {
            color: white !important;
        }

        /* ===== RESET TOTAL ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F5F7FA;
            color: #1A2A24;
            line-height: 1.5;
            min-height: 100vh;
        }

        /* ===== CONTAINER ===== */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 0 0 40px 40px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
            position: relative;
        }

        /* ===== DESKTOP GRID: VIDEO KIRI, KONTEN KANAN ===== */
        .desktop-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 0;
            min-height: 100vh;
        }

        /* LEFT SIDE - VIDEO DENGAN SUARA */
        .video-side {
            background: #000;
            position: relative;
            overflow: hidden;
        }

        .video-side video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .video-caption {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(10px);
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            z-index: 10;
            cursor: pointer;
        }

        .video-caption i {
            color: #E3B584;
        }

        /* RIGHT SIDE - CONTENT */
        .content-side {
            padding: 40px;
            overflow-y: auto;
            max-height: 100vh;
        }

        /* ===== SOCIAL PROOF NOTIF ===== */
        .social-proof {
            background: linear-gradient(135deg, #2A9D8F, #1E7A6F);
            border-radius: 60px;
            padding: 12px 20px;
            margin-bottom: 24px;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: gentle-pulse 3s infinite;
            box-shadow: 0 10px 20px rgba(42,157,143,0.3);
            max-width: 100%;
        }

        .social-proof i {
            font-size: 20px;
            background: rgba(255,255,255,0.2);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .social-proof span {
            flex: 1;
            font-weight: 500;
            font-size: 14px;
            line-height: 1.4;
        }

        @keyframes gentle-pulse {
            0% { transform: scale(1); box-shadow: 0 10px 20px rgba(42,157,143,0.3); }
            50% { transform: scale(1.01); box-shadow: 0 12px 24px rgba(42,157,143,0.4); }
            100% { transform: scale(1); box-shadow: 0 10px 20px rgba(42,157,143,0.3); }
        }

        /* ===== BADGE SUPER ===== */
        .badge-super {
            display: inline-block;
            background: linear-gradient(135deg, #D64F3C, #FF6B4A);
            color: white;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 24px;
            box-shadow: 0 10px 20px rgba(214,79,60,0.3);
            animation: pulse-badge 2s infinite;
        }

        @keyframes pulse-badge {
            0% { transform: scale(1); box-shadow: 0 10px 20px rgba(214,79,60,0.3); }
            50% { transform: scale(1.02); box-shadow: 0 15px 30px rgba(214,79,60,0.5); }
            100% { transform: scale(1); box-shadow: 0 10px 20px rgba(214,79,60,0.3); }
        }

        /* ===== HEADLINE ===== */
        .headline {
            font-size: 42px;
            font-weight: 800;
            color: #1B4A3C;
            line-height: 1.2;
            margin-bottom: 16px;
        }

        .headline span {
            color: #D64F3C;
            display: block;
            font-size: 32px;
            font-weight: 700;
            margin-top: 8px;
        }

        /* ===== SUBHEADLINE ===== */
        .subheadline {
            font-size: 18px;
            font-weight: 500;
            color: #4A5A54;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .subheadline i {
            color: #2A9D8F;
            font-size: 20px;
        }

        /* ===== PRICE AREA ===== */
        .price-super {
            background: linear-gradient(145deg, #0A2A21, #1B4A3C);
            border-radius: 28px;
            padding: 32px;
            margin: 24px 0;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(27,74,60,0.3);
        }

        .price-super::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .price-tag {
            position: relative;
            z-index: 10;
            font-size: 14px;
            color: #E3B584;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .price-number {
            position: relative;
            z-index: 10;
            font-size: 64px;
            font-weight: 800;
            color: #E3B584;
            line-height: 1;
            margin-bottom: 10px;
        }

        .price-number small {
            font-size: 24px;
            font-weight: 500;
            color: rgba(255,255,255,0.8);
            margin-left: 10px;
        }

        .price-caption {
            position: relative;
            z-index: 10;
            font-size: 16px;
            font-weight: 600;
            background: rgba(255,255,255,0.1);
            padding: 12px 20px;
            border-radius: 60px;
            display: inline-block;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* ===== BENEFITS GRID 3x2 ===== */
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
            transition: all 0.3s ease;
            border: 1px solid #E0E7E0;
        }

        .benefit-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            border-color: #D64F3C;
        }

        .benefit-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #D64F3C, #FF6B4A);
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
            color: #1B4A3C;
            margin-bottom: 4px;
        }

        .benefit-sub {
            font-size: 12px;
            color: #D64F3C;
            font-weight: 600;
        }

        /* ===== COUNTDOWN TIMER - DIPERBESAR DAN TIDAK GEPENG ===== */
        .countdown-super {
            background: linear-gradient(145deg, #D64F3C, #FF6B4A);
            border-radius: 30px;
            padding: 30px 25px;
            margin: 24px 0;
            color: white;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-shadow: 0 20px 40px rgba(214,79,60,0.4);
        }

        .countdown-super::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(45deg, transparent, transparent 15px, rgba(255,255,255,0.1) 15px, rgba(255,255,255,0.1) 30px);
            animation: moveStripes 20s linear infinite;
        }

        @keyframes moveStripes {
            from { transform: translateX(0) translateY(0); }
            to { transform: translateX(50px) translateY(50px); }
        }

        .countdown-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 700;
            font-size: 20px;
            position: relative;
            z-index: 10;
            margin-bottom: 25px;
            width: 100%;
        }

        .countdown-label i {
            font-size: 24px;
        }

        .countdown-numbers {
            display: flex;
            gap: 20px;
            position: relative;
            z-index: 10;
            justify-content: center;
            flex-wrap: wrap;
            width: 100%;
            margin-bottom: 25px;
        }

        .countdown-block {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            border-radius: 25px;
            text-align: center;
            min-width: 100px;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .countdown-block span {
            display: block;
            font-size: 42px;
            font-weight: 800;
            color: white;
            line-height: 1.2;
        }

        .countdown-block small {
            font-size: 14px;
            color: rgba(255,255,255,0.9);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .stock-left {
            position: relative;
            z-index: 10;
            font-size: 18px;
            font-weight: 700;
            background: rgba(0,0,0,0.3);
            padding: 12px 25px;
            border-radius: 50px;
            border: 2px solid rgba(255,255,255,0.2);
            display: inline-block;
        }

        /* ===== RATING SUPER ===== */
        .rating-super {
            display: flex;
            gap: 24px;
            align-items: center;
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
            font-size: 22px;
            color: #E3B584;
            border: 1px solid #E0E7E0;
            flex-shrink: 0;
        }

        .rating-text {
            display: flex;
            flex-direction: column;
        }

        .rating-number {
            font-weight: 800;
            font-size: 18px;
            color: #1B4A3C;
        }

        .rating-label {
            font-size: 12px;
            color: #4A5A54;
        }

        /* ===== FORM 3 KOLOM ===== */
        .form-super {
            background: white;
            border-radius: 32px;
            padding: 35px 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.1);
            border: 1px solid #E0E7E0;
            margin-top: 30px;
        }

        .form-title {
            font-size: 28px;
            font-weight: 800;
            color: #1B4A3C;
            margin-bottom: 8px;
            line-height: 1.3;
            text-align: center;
        }

        .form-sub {
            font-size: 15px;
            color: #4A5A54;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
        }

        .form-sub i {
            color: #D64F3C;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #1B4A3C;
            margin-bottom: 6px;
        }

        .form-label i {
            color: #D64F3C;
            width: 18px;
            margin-right: 5px;
        }

        .form-input {
            width: 100%;
            padding: 16px 18px;
            border: 2px solid #E0E7E0;
            border-radius: 18px;
            font-size: 15px;
            transition: all 0.3s;
            background: #FAFCFA;
            color: #1A2A24;
        }

        .form-input:focus {
            outline: none;
            border-color: #D64F3C;
            box-shadow: 0 0 0 4px rgba(214,79,60,0.1);
        }

        .form-input.valid {
            border-color: #2A9D8F;
        }

        .form-input.invalid {
            border-color: #D64F3C;
        }

        .validation-msg {
            font-size: 12px;
            margin-top: 5px;
            min-height: 18px;
            color: #D64F3C;
        }

        .agree-box {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            background: #E7F3EF;
            padding: 14px 18px;
            border-radius: 18px;
            border: 1px solid #D0DDD6;
        }

        .agree-box input {
            width: 20px;
            height: 20px;
            accent-color: #D64F3C;
            flex-shrink: 0;
        }

        .agree-box label {
            font-size: 13px;
            color: #1B4A3C;
        }

        .agree-box a {
            color: #D64F3C;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-cta {
            width: 100%;
            padding: 20px;
            background: linear-gradient(135deg, #D64F3C, #FF6B4A);
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
            box-shadow: 0 15px 30px rgba(214,79,60,0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-cta:hover::before {
            left: 100%;
        }

        /* ===== TRUST BADGES 1 BARIS CENTER ===== */
        .trust-badges {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 24px;
            font-size: 12px;
            color: #4A5A54;
            flex-wrap: wrap;
            text-align: center;
            margin-top: 16px;
        }

        .trust-badges span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
        }

        .trust-badges i {
            color: #2A9D8F;
            font-size: 13px;
        }

        /* ===== FLOATING CTA MOBILE ===== */
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
            background: linear-gradient(135deg, #D64F3C, #FF6B4A);
            color: white;
            border: none;
            border-radius: 60px;
            font-weight: 800;
            font-size: 18px;
            box-shadow: 0 15px 30px rgba(214,79,60,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
        }

        .hidden {
            display: none !important;
        }

        /* ===== MODAL THANK YOU SUPER KEREN ===== */
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
            padding: 15px;
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
            animation: popIn 0.5s ease;
            box-shadow: 0 40px 80px rgba(0,0,0,0.2);
            border: 3px solid #E3B584;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes popIn {
            0% {
                opacity: 0;
                transform: scale(0.8);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .thankyou-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #2A9D8F, #40BEB0);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: white;
            margin: 0 auto 20px;
            box-shadow: 0 20px 40px rgba(42,157,143,0.4);
        }

        .thankyou-title {
            font-size: 32px;
            font-weight: 800;
            color: #1B4A3C;
            margin-bottom: 10px;
        }

        .thankyou-message {
            font-size: 16px;
            color: #4A5A54;
            margin-bottom: 20px;
            line-height: 1.5;
            background: #F5F7F5;
            padding: 15px;
            border-radius: 20px;
        }

        .marketing-info {
            background: linear-gradient(145deg, #0A2A21, #1B4A3C);
            border-radius: 30px;
            padding: 20px;
            margin: 20px 0;
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
            text-align: left;
        }

        .marketing-avatar {
            width: 70px;
            height: 70px;
            background: #E3B584;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 35px;
            font-weight: 700;
            color: #0A2A21;
            flex-shrink: 0;
            border: 3px solid white;
        }

        .marketing-detail {
            flex: 1;
        }

        .marketing-label {
            font-size: 12px;
            color: #E3B584;
            margin-bottom: 5px;
        }

        .marketing-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .marketing-phone {
            font-size: 16px;
            color: #E3B584;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .estimate-time {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: linear-gradient(135deg, #D64F3C, #FF6B4A);
            padding: 15px;
            border-radius: 50px;
            margin: 20px 0;
            color: white;
            font-size: 15px;
            font-weight: 600;
        }

        .thankyou-close {
            background: none;
            border: 2px solid #E0E7E0;
            padding: 15px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            color: #4A5A54;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            background: white;
        }

        .thankyou-close:hover {
            background: #D64F3C;
            border-color: #D64F3C;
            color: white;
        }

        /* ===== MODAL INFO & PROTECT ===== */
        .modal-info, .modal-protect {
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
            padding: 15px;
        }

        .modal-info.show, .modal-protect.show {
            display: flex;
        }

        .info-card, .protect-card {
            background: white;
            border-radius: 40px;
            max-width: 500px;
            width: 90%;
            padding: 30px 25px;
            text-align: center;
            animation: slideUp 0.4s ease;
            box-shadow: 0 40px 80px rgba(0,0,0,0.2);
            border: 1px solid #E0E7E0;
            max-height: 80vh;
            overflow-y: auto;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .info-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #1B4A3C, #2A5F4E);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 35px;
            color: white;
            margin: 0 auto 20px;
        }

        .info-title {
            font-size: 26px;
            font-weight: 800;
            color: #1B4A3C;
            margin-bottom: 20px;
        }

        .info-body {
            color: #4A5A54;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 25px;
            text-align: left;
        }

        .info-body h4 {
            color: #1B4A3C;
            margin: 20px 0 10px;
        }

        .info-close {
            background: #1B4A3C;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 50px;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
        }

        .protect-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #E3B584, #F0C48C);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 35px;
            color: white;
            margin: 0 auto 20px;
        }

        .protect-title {
            font-size: 24px;
            font-weight: 800;
            color: #1B4A3C;
            margin-bottom: 15px;
        }

        .protect-text {
            font-size: 15px;
            color: #4A5A54;
            margin-bottom: 25px;
        }

        .protect-btn {
            background: #1B4A3C;
            color: white;
            border: none;
            padding: 15px 20px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }

        /* ===== MOBILE RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .desktop-grid {
                grid-template-columns: 1fr;
            }
            
            .video-side {
                height: 50vh;
            }
            
            .video-side video {
                object-fit: cover;
                width: 100%;
                height: 100%;
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
            
            .subheadline {
                justify-content: center;
                text-align: center;
            }
            
            .price-number {
                font-size: 52px;
            }
            
            .price-number small {
                font-size: 18px;
            }
            
            .price-caption {
                display: block;
                text-align: center;
                width: 100%;
            }
            
            .price-tag {
                justify-content: center;
            }
            
            .rating-super {
                justify-content: center;
            }
            
            .badge-super {
                margin-left: auto;
                margin-right: auto;
                display: table;
            }
            
            .countdown-numbers {
                justify-content: center;
            }
            
            .trust-badges {
                gap: 16px;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .trust-badges span {
                white-space: nowrap;
            }
        }

        @media (max-width: 768px) {
            .benefits-3x2 {
                gap: 8px;
            }
            
            .benefit-card {
                padding: 12px 5px;
            }
            
            .benefit-title {
                font-size: 12px;
            }
            
            .countdown-block {
                min-width: 70px;
                padding: 10px 8px;
            }
            
            .countdown-block span {
                font-size: 32px;
            }
            
            .countdown-block small {
                font-size: 12px;
            }
            
            .form-super {
                padding: 25px 20px;
            }
            
            .form-title {
                font-size: 24px;
            }
            
            .marketing-info {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }
            
            .marketing-avatar {
                margin: 0 auto 10px;
            }
            
            .thankyou-card {
                padding: 25px 20px;
            }
            
            .thankyou-title {
                font-size: 28px;
            }
            
            .thankyou-icon {
                width: 80px;
                height: 80px;
                font-size: 40px;
            }
        }

        @media (max-width: 480px) {
            .content-side {
                padding: 20px 15px;
            }
            
            .benefits-3x2 {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .price-number {
                font-size: 42px;
            }
            
            .price-number small {
                font-size: 14px;
            }
            
            .countdown-numbers {
                gap: 10px;
            }
            
            .countdown-block {
                min-width: 60px;
                padding: 8px 5px;
            }
            
            .countdown-block span {
                font-size: 28px;
            }
            
            .countdown-block small {
                font-size: 11px;
            }
            
            .rating-super {
                gap: 12px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .rating-item {
                width: auto;
            }
            
            .trust-badges {
                gap: 8px;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .trust-badges span {
                font-size: 11px;
            }
            
            .thankyou-title {
                font-size: 24px;
            }
            
            .thankyou-message {
                font-size: 14px;
                padding: 12px;
            }
            
            .marketing-name {
                font-size: 20px;
            }
            
            .marketing-phone {
                font-size: 14px;
            }
        }

        @media (max-width: 360px) {
            .countdown-block {
                min-width: 50px;
                padding: 6px 3px;
            }
            
            .countdown-block span {
                font-size: 24px;
            }
            
            .countdown-block small {
                font-size: 10px;
            }
            
            .trust-badges span {
                font-size: 10px;
            }
        }
    </style>
    
    <!-- ========== KONFIGURASI DEVELOPER ========== -->
    <script>
        window.DEVELOPER_CONFIG = {
            developerId: <?= $dev_id > 0 ? $dev_id : 3 ?>,
            locationKey: 'kertamulya',
            locationName: 'Kertamulya Residence',
            icon: '🏡',
            videoUrl: '/kertamulya/kertamulya-1.webm',
            posterUrl: '/kertamulya/rumahsubsidi.webp',
            waNumber: '628133150078',
            bonusTitle: '🔥 KOMPOR LISTRIK + 🏦 SUBSIDI BANK 35 JUTA',
            bonusDesc: 'GRATIS PPN 11% (Rp 18 Juta) + GRATIS BPHTB (Rp 8 Juta) + GRATIS BIAYA ADMIN BANK + GRATIS ASURANSI JIWA'
        };
    </script>
</head>
<body>
    <!-- ===== MAIN DESKTOP GRID: VIDEO KIRI, KONTEN KANAN ===== -->
    <div class="desktop-grid">
        <!-- LEFT SIDE: VIDEO DENGAN SUARA -->
        <div class="video-side">
            <video id="mainVideo" autoplay loop playsinline poster="/kertamulya/rumahsubsidi.webp">
                <source src="/kertamulya/kertamulya-1.webm" type="video/webm">
                Browser Anda tidak mendukung video.
            </video>
            <div class="video-caption" id="videoCaption">
                <i class="fas fa-volume-up"></i> Dengan Suara
            </div>
        </div>
        
        <!-- RIGHT SIDE: CONTENT + FORM -->
        <div class="content-side" id="formState">
            <!-- SOCIAL PROOF NOTIF REAL-TIME -->
            <div class="social-proof" id="liveNotif">
                <i class="fas fa-user-check"></i>
                <span id="notifMessage">Budi baru booking 2 menit lalu</span>
            </div>
            
            <!-- BADGE SUPER -->
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
            
            <!-- PRICE AREA -->
            <div class="price-super">
                <div class="price-tag">
                    <i class="fas fa-tag"></i> HARGA KHUSUS BULAN INI
                </div>
                <div class="price-number">
                    500RB <small>Booking fee</small>
                </div>
                <div class="price-caption">
                    <i class="fas fa-gem"></i> CICILAN 1 JUTA/BULAN
                </div>
            </div>
            
            <!-- BENEFITS GRID 3x2 -->
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
            
            <!-- COUNTDOWN TIMER -->
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
            
            <!-- RATING SUPER -->
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
            
            <!-- FORM 3 KOLOM -->
            <div class="form-super">
                <h2 class="form-title">
                    DAPATKAN BONUS <br>Rp 35.000.000
                </h2>
                <div class="form-sub">
                    <i class="fas fa-bolt"></i> Marketing akan WA dalam 5 menit
                </div>
                
                <form id="leadForm">
                    <input type="hidden" name="developer_id" value="<?= $dev_id > 0 ? $dev_id : 3 ?>">
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
                        <label for="agree">Saya setuju <a href="#" onclick="openTermsModal()">syarat & ketentuan</a> dan <a href="#" onclick="openPrivacyModal()">kebijakan privasi</a></label>
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
                <p style="margin-top: 8px;">
                    <a href="#" onclick="openPrivacyModal()" style="color: #4A5A54; text-decoration: none;">Kebijakan Privasi</a> • 
                    <a href="#" onclick="openTermsModal()" style="color: #4A5A54; text-decoration: none;">Syarat & Ketentuan</a> •
                    <a href="#" onclick="openDisclaimerModal()" style="color: #4A5A54; text-decoration: none;">Disclaimer</a>
                </p>
            </div>
        </div>
    </div>
    
    <!-- ===== FLOATING CTA MOBILE ===== -->
    <div class="floating-mobile" id="floatingCta">
        <button class="floating-btn" onclick="scrollToForm()">
            <i class="fas fa-pen"></i> ISI FORM & CLAIM BONUS
        </button>
    </div>
    
    <!-- ===== MODALS LENGKAP ===== -->
    <!-- THANK YOU MODAL SUPER KEREN -->
    <div class="modal-thankyou" id="thankyouModal">
        <div class="thankyou-card">
            <div class="thankyou-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="thankyou-title" id="thankyouTitle">TERIMA KASIH!</h3>
            <p class="thankyou-message" id="thankyouMessage">
                Data Anda telah kami terima. Tim marketing akan segera menghubungi via WhatsApp.
            </p>
            <div class="marketing-info" id="marketingInfo">
                <div class="marketing-avatar" id="marketingAvatar">T</div>
                <div class="marketing-detail">
                    <div class="marketing-label">Marketing Anda:</div>
                    <div class="marketing-name" id="marketingName">Tim Marketing</div>
                    <div class="marketing-phone" id="marketingPhone">
                        <i class="fab fa-whatsapp"></i> 628xxxxxx
                    </div>
                </div>
            </div>
            <div class="estimate-time">
                <i class="fas fa-bolt"></i>
                <span>Marketing akan menghubungi dalam 1-2 menit</span>
            </div>
            <button class="thankyou-close" onclick="closeThankYou()">
                <i class="fas fa-check"></i> Tutup
            </button>
        </div>
    </div>
    
    <!-- PRIVACY MODAL -->
    <div class="modal-info" id="privacyModal">
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h3 class="info-title">KEBIJAKAN PRIVASI</h3>
            <div class="info-body">
                <p><strong>Terakhir diperbarui: 26 Februari 2026</strong></p>
                <h4>1. Informasi yang Kami Kumpulkan</h4>
                <p>Kami mengumpulkan informasi pribadi Anda ketika Anda mengisi formulir di website ini, termasuk:</p>
                <ul>
                    <li>Nama lengkap</li>
                    <li>Nomor WhatsApp/telepon</li>
                    <li>Alamat email</li>
                    <li>Data tracking seperti IP address, user agent, cookie, dan pixel data dari Meta, TikTok, dan Google</li>
                </ul>
                <h4>2. Penggunaan Informasi</h4>
                <p>Informasi Anda digunakan untuk menghubungi Anda terkait produk properti Kertamulya Residence, memproses pengajuan KPR, mengirimkan informasi promo, dan analisis internal.</p>
                <h4>3. Perlindungan Data</h4>
                <p>Data Anda dienkripsi dengan SSL 256-bit dan disimpan di server aman. Kami tidak akan pernah menjual data Anda ke pihak ketiga.</p>
            </div>
            <button class="info-close" onclick="closeModal('privacyModal')">Tutup</button>
        </div>
    </div>
    
    <!-- TERMS MODAL -->
    <div class="modal-info" id="termsModal">
        <div class="info-card">
            <div class="info-icon" style="background: linear-gradient(135deg, #D64F3C, #FF6B4A);">
                <i class="fas fa-file-contract"></i>
            </div>
            <h3 class="info-title">SYARAT & KETENTUAN</h3>
            <div class="info-body">
                <p><strong>Berlaku untuk program Kertamulya Residence - Update Februari 2026</strong></p>
                <h4>1. Booking Fee</h4>
                <ul>
                    <li>Booking fee Rp 500.000 untuk program ekonomis (unit subsidi)</li>
                    <li>Booking fee Rp 6.000.000 untuk program all-in siap huni premium</li>
                    <li>Booking fee TIDAK DAPAT DIREFUND jika batal atas kemauan sendiri setelah melewati masa 3 hari</li>
                </ul>
                <h4>2. Proses KPR Syariah</h4>
                <ul>
                    <li>KPR Syariah dengan bank mitra (BSI, BTN Syariah, Bank Mega Syariah, dll)</li>
                    <li>Cicilan mulai Rp 1.200.000 per bulan untuk tenor 20 tahun</li>
                    <li>Proses persetujuan KPR 7-14 hari kerja sejak dokumen lengkap</li>
                </ul>
            </div>
            <button class="info-close" onclick="closeModal('termsModal')">Tutup</button>
        </div>
    </div>
    
    <!-- DISCLAIMER MODAL -->
    <div class="modal-info" id="disclaimerModal">
        <div class="info-card">
            <div class="info-icon" style="background: linear-gradient(135deg, #E3B584, #F0C48C);">
                <i class="fas fa-info-circle"></i>
            </div>
            <h3 class="info-title">DISCLAIMER</h3>
            <div class="info-body">
                <p><strong>Informasi Penting</strong></p>
                <ul>
                    <li>Website ini dikelola oleh Kertamulya Residence, developer resmi berbadan hukum PT. Rumah Mulia Indonesia.</li>
                    <li>Harga, promo, dan ketersediaan unit dapat berubah sewaktu-waktu tanpa pemberitahuan.</li>
                    <li>Gambar dan video hanya ilustrasi, dapat berbeda dengan unit sebenarnya.</li>
                </ul>
            </div>
            <button class="info-close" onclick="closeModal('disclaimerModal')">Tutup</button>
        </div>
    </div>
    
    <!-- ===== INCLUDE SEMUA JS FILES ===== -->
    <script src="form.js"></script>
    <script src="instagram-fix.js"></script>
    <script src="main.js"></script>
    
    <script>
        // ===== VIDEO DENGAN SUARA =====
        document.addEventListener('DOMContentLoaded', function() {
            const video = document.getElementById('mainVideo');
            const caption = document.getElementById('videoCaption');
            
            if (video) {
                video.volume = 0.5;
                video.play().catch(function(error) {
                    console.log('Auto-play dengan suara diblokir browser. Mencoba muted fallback...');
                    video.muted = true;
                    video.play();
                    
                    if (caption) {
                        caption.innerHTML = '<i class="fas fa-volume-mute"></i> Klik untuk suara';
                        caption.onclick = function() {
                            video.muted = false;
                            video.volume = 0.5;
                            this.innerHTML = '<i class="fas fa-volume-up"></i> Dengan Suara';
                        };
                    }
                });
            }
        });
        
        // ===== SOCIAL PROOF REAL-TIME =====
        const buyers = [
            { name: 'Budi', time: '2 menit' },
            { name: 'Siti', time: '5 menit' },
            { name: 'Ahmad', time: '8 menit' },
            { name: 'Dewi', time: '12 menit' },
            { name: 'Rudi', time: '15 menit' }
        ];
        
        let index = 0;
        setInterval(() => {
            const b = buyers[index % buyers.length];
            document.getElementById('notifMessage').innerHTML = 
                `${b.name} baru booking ${b.time} lalu`;
            index++;
        }, 12000);
        
        // ===== FORM SUBMIT HANDLER =====
        if (document.getElementById('leadForm')) {
            document.getElementById('leadForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Disable button
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
                }
                
                // Validasi
                const name = document.getElementById('first_name').value.trim();
                const phone = document.getElementById('phone').value.replace(/\D/g, '');
                
                if (!name) {
                    showProtectModal('Nama lengkap harus diisi');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-bolt"></i> CLAIM BONUS & INFO';
                    }
                    return;
                }
                
                if (phone.length < 10) {
                    showProtectModal('Nomor WhatsApp minimal 10 digit');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-bolt"></i> CLAIM BONUS & INFO';
                    }
                    return;
                }
                
                // ===== INSTAN THANK YOU =====
                document.getElementById('formState').classList.add('hidden');
                document.getElementById('thankyouModal').classList.add('show');
                
                // Set default marketing info
                document.getElementById('marketingName').textContent = 'Tim Marketing';
                document.getElementById('marketingPhone').innerHTML = '<i class="fab fa-whatsapp"></i> 628133150078';
                document.getElementById('marketingAvatar').textContent = 'T';
                
                // ===== BACKGROUND SUBMIT KE API MASTER =====
                const formData = new FormData(this);
                
                // Tambah data tracking
                try {
                    const urlParams = new URLSearchParams(window.location.search);
                    const fbclid = urlParams.get('fbclid');
                    if (fbclid) formData.append('fbclid', fbclid);
                    
                    const ttclid = urlParams.get('ttclid');
                    if (ttclid) formData.append('ttclid', ttclid);
                    
                    const gclid = urlParams.get('gclid');
                    if (gclid) formData.append('gclid', gclid);
                    
                    const utm_source = urlParams.get('utm_source');
                    if (utm_source) formData.append('utm_source', utm_source);
                    
                    const utm_medium = urlParams.get('utm_medium');
                    if (utm_medium) formData.append('utm_medium', utm_medium);
                    
                    const utm_campaign = urlParams.get('utm_campaign');
                    if (utm_campaign) formData.append('utm_campaign', utm_campaign);
                    
                    const utm_content = urlParams.get('utm_content');
                    if (utm_content) formData.append('utm_content', utm_content);
                    
                    const utm_term = urlParams.get('utm_term');
                    if (utm_term) formData.append('utm_term', utm_term);
                } catch (e) {
                    console.warn('Error adding tracking params:', e);
                }
                
                // Tambah data browser
                try {
                    formData.append('screen_resolution', screen.width + 'x' + screen.height);
                    formData.append('timezone_offset', new Date().getTimezoneOffset());
                    formData.append('page_url', window.location.href);
                    formData.append('referrer', document.referrer);
                    formData.append('client_timestamp', Math.floor(Date.now() / 1000));
                } catch (e) {
                    console.warn('Error adding browser data:', e);
                }
                
                // Kirim ke API Master
                fetch('/admin/api/api_master.php', {
                    method: 'POST',
                    body: formData,
                    keepalive: true,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('✅ Lead terkirim:', data);
                    
                    // Update thank you dengan data real dari server
                    if (data.success && data.data) {
                        if (data.data.assigned_marketing_name) {
                            document.getElementById('marketingName').textContent = data.data.assigned_marketing_name;
                            document.getElementById('marketingAvatar').textContent = data.data.assigned_marketing_name.charAt(0).toUpperCase();
                        }
                        if (data.data.assigned_marketing_phone) {
                            document.getElementById('marketingPhone').innerHTML = '<i class="fab fa-whatsapp"></i> ' + data.data.assigned_marketing_phone;
                        }
                        if (data.data.assigned_marketing_photo) {
                            document.getElementById('marketingAvatar').innerHTML = `<img src="${data.data.assigned_marketing_photo}" style="width: 100%; height: 100%; border-radius: 25px; object-fit: cover;">`;
                        }
                        
                        // Update message dengan nama
                        const thankyouMessage = document.getElementById('thankyouMessage');
                        if (thankyouMessage) {
                            thankyouMessage.textContent = `Hai ${name}, data Anda telah kami terima. ${data.data.assigned_marketing_name || 'Tim marketing'} akan segera menghubungi via WhatsApp dalam 1-2 menit.`;
                        }
                        
                        // Update bonus info
                        if (data.data.bonus_title) {
                            document.getElementById('thankyouTitle').innerHTML = '🎉 ' + data.data.bonus_title;
                        }
                    }
                    
                    // Trigger tracking pixel
                    if (typeof fbq !== 'undefined') {
                        fbq('track', 'Lead', {
                            content_name: 'Kertamulya Residence',
                            content_category: 'Real Estate',
                            value: 500000000,
                            currency: 'IDR'
                        });
                    }
                    
                    if (typeof ttq !== 'undefined') {
                        ttq.track('Lead', {
                            content_name: 'Kertamulya Residence',
                            content_id: 'KML-36-60',
                            value: 500000000,
                            currency: 'IDR'
                        });
                    }
                    
                    if (typeof gtag !== 'undefined') {
                        gtag('event', 'generate_lead', {
                            'value': 500000000,
                            'currency': 'IDR'
                        });
                    }
                })
                .catch(error => {
                    console.error('❌ Error submit ke API:', error);
                    // Tetap tampilkan thank you meskipun error
                })
                .finally(() => {
                    setTimeout(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-bolt"></i> CLAIM BONUS & INFO';
                        }
                    }, 5000);
                });
            });
        }
        
        // ===== CLOSE THANK YOU =====
        window.closeThankYou = function() {
            document.getElementById('thankyouModal').classList.remove('show');
            document.getElementById('formState').classList.remove('hidden');
            
            // Reset form
            document.getElementById('leadForm').reset();
        };
        
        // ===== MODAL FUNCTIONS =====
        window.openPrivacyModal = function() {
            document.getElementById('privacyModal').classList.add('show');
        };
        
        window.openTermsModal = function() {
            document.getElementById('termsModal').classList.add('show');
        };
        
        window.openDisclaimerModal = function() {
            document.getElementById('disclaimerModal').classList.add('show');
        };
        
        window.closeModal = function(id) {
            document.getElementById(id).classList.remove('show');
        };
        
        // ===== SCROLL TO FORM =====
        window.scrollToForm = function() {
            document.querySelector('.form-super').scrollIntoView({ behavior: 'smooth' });
        };
        
        // ===== PHONE VALIDATION =====
        if (document.getElementById('phone')) {
            document.getElementById('phone').addEventListener('input', function() {
                let v = this.value.replace(/\D/g, '');
                if (v.length > 13) v = v.slice(0, 13);
                this.value = v;
                
                const msg = document.getElementById('phone_validation');
                if (v.length < 10) {
                    msg.innerHTML = '⛔ Minimal 10 digit';
                    msg.style.color = '#D64F3C';
                } else if (v.length >= 10 && v.length <= 13) {
                    msg.innerHTML = '✅ Nomor valid';
                    msg.style.color = '#2A9D8F';
                } else {
                    msg.innerHTML = '⛔ Maksimal 13 digit';
                    msg.style.color = '#D64F3C';
                }
            });
        }
        
        // ===== COUNTDOWN =====
        function startCountdown() {
            const timer = document.getElementById('countdownTimer');
            if (!timer) return;
            
            // Set end time to 23:59:59 today
            const end = new Date();
            end.setHours(23, 59, 59, 0);
            
            function update() {
                const now = new Date().getTime();
                const distance = end.getTime() - now;
                
                if (distance < 0) {
                    timer.innerHTML = `
                        <div class="countdown-block"><span>00</span><small>Jam</small></div>
                        <div class="countdown-block"><span>00</span><small>Menit</small></div>
                        <div class="countdown-block"><span>00</span><small>Detik</small></div>
                    `;
                    return;
                }
                
                const hours = Math.floor(distance / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                timer.innerHTML = `
                    <div class="countdown-block"><span>${hours.toString().padStart(2, '0')}</span><small>Jam</small></div>
                    <div class="countdown-block"><span>${minutes.toString().padStart(2, '0')}</span><small>Menit</small></div>
                    <div class="countdown-block"><span>${seconds.toString().padStart(2, '0')}</span><small>Detik</small></div>
                `;
            }
            
            update();
            setInterval(update, 1000);
        }
        
        // ===== FLOATING CTA MOBILE =====
        function initFloatingCTA() {
            const floating = document.getElementById('floatingCta');
            const form = document.querySelector('.form-super');
            
            if (!floating || !form) return;
            
            window.addEventListener('scroll', function() {
                const rect = form.getBoundingClientRect();
                const formVisible = rect.top < window.innerHeight && rect.bottom > 0;
                
                if (!formVisible) {
                    floating.classList.add('show');
                } else {
                    floating.classList.remove('show');
                }
            });
            
            // Trigger once on load
            setTimeout(() => {
                const rect = form.getBoundingClientRect();
                if (rect.bottom < 0 || rect.top > window.innerHeight) {
                    floating.classList.add('show');
                }
            }, 1000);
        }
        
        // ===== INIT =====
        document.addEventListener('DOMContentLoaded', function() {
            startCountdown();
            initFloatingCTA();
            
            // Close modals on outside click
            document.querySelectorAll('.modal-info, .modal-thankyou, .modal-protect').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.remove('show');
                    }
                });
            });
            
            // Escape key close modals
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal-info.show, .modal-thankyou.show, .modal-protect.show').forEach(modal => {
                        modal.classList.remove('show');
                    });
                }
            });
        });
    </script>
</body>
</html>