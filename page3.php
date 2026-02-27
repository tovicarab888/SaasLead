<?php
header('Cache-Control: no-cache, must-revalidate');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0A2E24">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Lead Engine — Platform</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Meta Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '2224730075026860');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=2224730075026860&ev=PageView&noscript=1"/></noscript>
    
    <!-- TikTok Pixel -->
    <script>
    !function (w, d, t) {
      w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
      ttq.load('D3L405BC77U8AFC9O0RG');
      ttq.page();
    }(window, document, 'ttq');
    </script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #0A2E24;
            --primary-light: #1B4A3C;
            --secondary: #E85C3F;
            --secondary-light: #FF7A5C;
            --bg: #F5F0EA;
            --text: #1A2A24;
            --text-secondary: #5B6F68;
            --glass: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
            position: fixed;
            width: 100%;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }
        
        .bg-gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 30% 30%, rgba(232, 92, 63, 0.03) 0%, transparent 45%),
                        radial-gradient(circle at 70% 70%, rgba(10, 46, 36, 0.05) 0%, transparent 50%),
                        linear-gradient(145deg, #F5F0EA 0%, #EFE9E2 100%);
            z-index: -1;
        }
        
        .orb {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, rgba(232,92,63,0.1), transparent 70%);
            filter: blur(40px);
            z-index: -1;
            animation: floatOrb 20s ease-in-out infinite;
        }
        
        .orb-1 {
            width: 500px;
            height: 500px;
            top: -150px;
            right: -150px;
            background: radial-gradient(circle at 70% 30%, rgba(232,92,63,0.1), transparent 70%);
        }
        
        .orb-2 {
            width: 550px;
            height: 550px;
            bottom: -200px;
            left: -150px;
            background: radial-gradient(circle at 30% 70%, rgba(10,46,36,0.1), transparent 70%);
            animation-delay: -7s;
        }
        
        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0); }
            33% { transform: translate(40px, -30px); }
            66% { transform: translate(-30px, 40px); }
        }
        
        .container {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 10;
        }
        
        .card {
            width: 100%;
            max-width: 360px;
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 56px;
            padding: 24px 24px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.1), 0 0 0 1px var(--glass-border) inset;
            border: 1px solid rgba(255,255,255,0.8);
            display: flex;
            flex-direction: column;
            animation: cardFloat 8s ease-in-out infinite;
            margin: auto;
            max-height: 90vh;
            overflow: hidden; /* NO SCROLL */
        }
        
        @keyframes cardFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            background: rgba(255,255,255,0.4);
            backdrop-filter: blur(5px);
            border-radius: 40px;
            border: 1px solid rgba(255,255,255,0.5);
            width: fit-content;
            margin-bottom: 12px;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.6);
            transform: translateX(-5px);
        }
        
        .content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 0;
        }
        
        .illustration {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 8px 0 20px;
            perspective: 1000px;
        }
        
        .hierarchy-container {
            width: 100%;
            max-width: 240px;
            transform-style: preserve-3d;
            animation: rotateHierarchy 15s ease-in-out infinite;
        }
        
        @keyframes rotateHierarchy {
            0%, 100% { transform: rotateY(0deg) rotateX(2deg); }
            25% { transform: rotateY(4deg) rotateX(5deg); }
            75% { transform: rotateY(-4deg) rotateX(0deg); }
        }
        
        .hierarchy-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin: 8px 0;
        }
        
        .hierarchy-item {
            width: 60px;
            height: 60px;
            flex-shrink: 0;
            background: white;
            border-radius: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            box-shadow: 0 20px 30px -8px rgba(10,46,36,0.15), 0 0 0 1px rgba(255,255,255,0.8) inset;
            animation: hierarchyFloat 3s ease-in-out infinite;
            transform: translateZ(5px);
        }
        
        .hierarchy-item i {
            font-size: 22px;
            color: var(--primary);
            width: auto;
            height: auto;
        }
        
        .hierarchy-item span {
            font-size: 9px;
            font-weight: 600;
            color: var(--text);
        }
        
        .hierarchy-item.root {
            background: linear-gradient(145deg, var(--primary), var(--primary-light));
            width: 70px;
            height: 70px;
        }
        
        .hierarchy-item.root i,
        .hierarchy-item.root span {
            color: white;
        }
        
        .hierarchy-item:nth-child(1) { animation-delay: 0s; }
        .hierarchy-item:nth-child(2) { animation-delay: 0.2s; }
        .hierarchy-item:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes hierarchyFloat {
            0%, 100% { transform: translateZ(5px) translateY(0); }
            50% { transform: translateZ(15px) translateY(-5px); }
        }
        
        .headline {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(145deg, var(--primary) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.1;
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }
        
        .subheadline {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.4;
            margin-bottom: 20px;
        }
        
        .points {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .point {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 6px 10px;
            border-radius: 22px;
            background: rgba(255,255,255,0.3);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        
        .point:hover {
            background: rgba(255,255,255,0.6);
            transform: translateX(8px);
        }
        
        .point-icon {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            background: linear-gradient(145deg, var(--secondary), var(--secondary-light));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            box-shadow: 0 10px 20px rgba(232,92,63,0.25);
        }
        
        .point-icon i {
            font-size: 20px;
            width: auto;
            height: auto;
        }
        
        .point-text {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            line-height: 1.3;
        }
        
        .btn-premium {
            width: 100%;
            height: 54px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            margin: 16px 0 16px;
            box-shadow: 0 20px 30px rgba(10,46,36,0.25);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .btn-premium::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-premium:active::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-premium:active {
            transform: scale(0.97);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 4px 0;
        }
        
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 8px;
            background: #D9D2C9;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .dot.active {
            width: 30px;
            background: var(--secondary);
            box-shadow: 0 0 0 4px rgba(232,92,63,0.2);
        }
        
        /* NOTIFICATION MODAL PREMIUM */
        .notif-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 20000;
            padding: 16px;
        }
        
        .notif-card {
            background: white;
            border-radius: 44px;
            padding: 32px 24px;
            max-width: 320px;
            width: 100%;
            text-align: center;
            animation: modalPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 40px 80px rgba(0,0,0,0.2);
        }
        
        @keyframes modalPop {
            0% { transform: scale(0.7) translateY(50px); opacity: 0; }
            100% { transform: scale(1) translateY(0); opacity: 1; }
        }
        
        .notif-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: white;
            font-size: 36px;
            box-shadow: 0 20px 30px rgba(10,46,36,0.2);
        }
        
        .notif-card h3 {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .notif-card p {
            font-size: 15px;
            color: var(--text-secondary);
            margin-bottom: 28px;
            line-height: 1.5;
        }
        
        .notif-features {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 28px;
        }
        
        .notif-feature {
            text-align: center;
        }
        
        .notif-feature i {
            font-size: 22px;
            color: var(--secondary);
            background: #FFF0ED;
            width: 44px;
            height: 44px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 6px;
        }
        
        .notif-feature span {
            font-size: 11px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .notif-actions {
            display: flex;
            gap: 12px;
        }
        
        .notif-btn {
            flex: 1;
            height: 54px;
            border: none;
            border-radius: 27px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .notif-btn.primary {
            background: var(--secondary);
            color: white;
            box-shadow: 0 10px 20px rgba(232,92,63,0.2);
        }
        
        .notif-btn.primary:active {
            transform: scale(0.96);
        }
        
        .notif-btn.secondary {
            background: #F0E9E2;
            color: var(--primary);
        }
        
        /* Responsive untuk layar kecil */
        @media (max-height: 700px) {
            .card { padding: 20px 20px; }
            .hierarchy-item { width: 54px; height: 54px; }
            .hierarchy-item.root { width: 64px; height: 64px; }
            .point-icon { width: 40px; height: 40px; font-size: 18px; }
            .point-icon i { font-size: 18px; }
            .btn-premium { height: 50px; }
            .headline { font-size: 26px; }
        }
        
        @media (max-height: 600px) {
            .card { padding: 16px 20px; }
            .hierarchy-item { width: 48px; height: 48px; }
            .hierarchy-item.root { width: 58px; height: 58px; }
            .hierarchy-item i { font-size: 20px; }
            .point-icon { width: 36px; height: 36px; font-size: 16px; }
            .point-icon i { font-size: 16px; }
            .point-text { font-size: 13px; }
            .btn-premium { height: 46px; font-size: 14px; }
            .headline { font-size: 24px; }
        }
        
        @media (max-width: 380px) {
            .card { max-width: 320px; padding: 20px 18px; }
        }
    </style>
</head>
<body>
    <div class="bg-gradient"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    
    <div class="container">
        <div class="card">
            <a href="page2.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            
            <div class="content">
                <div class="illustration">
                    <div class="hierarchy-container">
                        <div class="hierarchy-row">
                            <div class="hierarchy-item root">
                                <i class="fas fa-crown"></i>
                                <span>PLATFORM</span>
                            </div>
                        </div>
                        <div class="hierarchy-row">
                            <div class="hierarchy-item">
                                <i class="fas fa-building"></i>
                                <span>Dev A</span>
                            </div>
                            <div class="hierarchy-item">
                                <i class="fas fa-building"></i>
                                <span>Dev B</span>
                            </div>
                            <div class="hierarchy-item">
                                <i class="fas fa-building"></i>
                                <span>Dev C</span>
                            </div>
                        </div>
                        <div class="hierarchy-row">
                            <div class="hierarchy-item">
                                <i class="fas fa-map-pin"></i>
                                <span>Lokasi</span>
                            </div>
                            <div class="hierarchy-item">
                                <i class="fas fa-map-pin"></i>
                                <span>Lokasi</span>
                            </div>
                            <div class="hierarchy-item">
                                <i class="fas fa-map-pin"></i>
                                <span>Lokasi</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h1 class="headline">Satu Platform<br>Semua Proyek</h1>
                <p class="subheadline">Scale operations tanpa kompleksitas</p>
                
                <div class="points">
                    <div class="point">
                        <div class="point-icon"><i class="fas fa-layer-group"></i></div>
                        <span class="point-text">Multi-developer — pisah data per pengembang</span>
                    </div>
                    <div class="point">
                        <div class="point-icon"><i class="fas fa-location-dot"></i></div>
                        <span class="point-text">Multi-lokasi — kelola banyak cluster</span>
                    </div>
                    <div class="point">
                        <div class="point-icon"><i class="fas fa-users"></i></div>
                        <span class="point-text">Multi-tim — internal, eksternal, admin</span>
                    </div>
                </div>
            </div>
            
            <div>
                <button class="btn-premium" id="goToLoginBtn">
                    <span>Masuk ke Dashboard</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
                
                <div class="pagination">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot active"></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- PREMIUM NOTIFICATION MODAL -->
    <div class="notif-modal" id="notifModal">
        <div class="notif-card">
            <div class="notif-icon">
                <i class="fas fa-bell"></i>
            </div>
            
            <h3>Aktifkan Notifikasi</h3>
            <p>Dapatkan update lead real-time langsung di perangkat Anda</p>
            
            <div class="notif-features">
                <div class="notif-feature">
                    <i class="fas fa-bolt"></i>
                    <span>Instan</span>
                </div>
                <div class="notif-feature">
                    <i class="fas fa-bell"></i>
                    <span>Real-time</span>
                </div>
                <div class="notif-feature">
                    <i class="fas fa-shield-alt"></i>
                    <span>Aman</span>
                </div>
            </div>
            
            <div class="notif-actions">
                <button class="notif-btn primary" id="allowNotifBtn">
                    <i class="fas fa-check"></i> Izinkan
                </button>
                <button class="notif-btn secondary" id="laterNotifBtn">
                    Nanti
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // PREMIUM NOTIFICATION HANDLER - MODAL LANGSUNG TUTUP
    const notifModal = document.getElementById('notifModal');
    const allowBtn = document.getElementById('allowNotifBtn');
    const laterBtn = document.getElementById('laterNotifBtn');
    const loginBtn = document.getElementById('goToLoginBtn');
    
    // Cek apakah sudah pernah minta izin
    if (!localStorage.getItem('notifAsked') && 'Notification' in window) {
        setTimeout(() => {
            notifModal.style.display = 'flex';
        }, 1000);
    }
    
    // IZINKAN NOTIFIKASI - MODAL LANGSUNG TUTUP
    allowBtn.addEventListener('click', () => {
        notifModal.style.display = 'none'; // LANGSUNG TUTUP
        
        Notification.requestPermission().then(perm => {
            if (perm === 'granted') {
                new Notification('Lead Engine Property', {
                    body: 'Notifikasi aktif! Anda akan mendapat update lead real-time.',
                    icon: '/assets/images/icon-192.png'
                });
            }
            localStorage.setItem('notifAsked', 'true');
        }).catch(() => {
            localStorage.setItem('notifAsked', 'true');
        });
    });
    
    // NANTI DULU
    laterBtn.addEventListener('click', () => {
        notifModal.style.display = 'none';
        localStorage.setItem('notifAsked', 'true');
    });
    
    // TUTUP MODAL jika klik background
    notifModal.addEventListener('click', (e) => {
        if (e.target === notifModal) {
            notifModal.style.display = 'none';
        }
    });
    
    // LOGIN BUTTON
    loginBtn.addEventListener('click', () => {
        window.location.href = '/admin/login.php?from_splash=1';
    });
    
    // Tracking Pixel Events
    fbq('track', 'ViewContent', {
        content_name: 'Splash Page 3',
        content_category: 'Onboarding'
    });
    
    ttq.track('ViewContent', {
        content_name: 'Splash Page 3',
        content_category: 'Onboarding'
    });
    </script>
</body>
</html>