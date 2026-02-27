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
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Lead Engine Property — Premium</title>
    
    <!-- Fonts & Icons -->
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
            --text-muted: #8B9D95;
            --glass: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-sm: 0 4px 12px rgba(0,0,0,0.02);
            --shadow-md: 0 8px 24px rgba(0,0,0,0.04);
            --shadow-lg: 0 20px 40px rgba(0,0,0,0.06);
            --shadow-xl: 0 30px 60px rgba(0,0,0,0.08);
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
            position: fixed;
            width: 100%;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }
        
        /* Premium Dynamic Background */
        .bg-gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(232, 92, 63, 0.03) 0%, transparent 45%),
                        radial-gradient(circle at 80% 70%, rgba(10, 46, 36, 0.05) 0%, transparent 50%),
                        linear-gradient(145deg, #F5F0EA 0%, #EFE9E2 100%);
            z-index: -1;
        }
        
        /* Animated Orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, rgba(232,92,63,0.1), transparent 70%);
            filter: blur(40px);
            z-index: -1;
            animation: floatOrb 15s ease-in-out infinite;
        }
        
        .orb-1 {
            width: 400px;
            height: 400px;
            top: -100px;
            right: -100px;
            background: radial-gradient(circle at 30% 30%, rgba(232,92,63,0.1), transparent 70%);
            animation-delay: 0s;
        }
        
        .orb-2 {
            width: 500px;
            height: 500px;
            bottom: -150px;
            left: -150px;
            background: radial-gradient(circle at 70% 70%, rgba(10,46,36,0.1), transparent 70%);
            animation-delay: -5s;
        }
        
        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0); }
            33% { transform: translate(30px, -30px); }
            66% { transform: translate(-20px, 20px); }
        }
        
        /* Main Container */
        .container {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 10;
        }
        
        /* Premium Glass Card with 3D Effect - NO SCROLL */
        .card {
            width: 100%;
            max-width: 360px;
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 56px;
            padding: 28px 24px;
            box-shadow: var(--shadow-xl), 0 0 0 1px var(--glass-border) inset;
            border: 1px solid rgba(255,255,255,0.8);
            display: flex;
            flex-direction: column;
            transform: perspective(1000px) rotateX(2deg);
            animation: cardFloat 6s ease-in-out infinite;
            margin: auto;
            max-height: 90vh;
            overflow: hidden; /* NO SCROLL */
        }
        
        @keyframes cardFloat {
            0%, 100% { transform: perspective(1000px) rotateX(2deg) translateY(0); }
            50% { transform: perspective(1000px) rotateX(2deg) translateY(-5px); }
        }
        
        /* Content Wrapper - Flex column with auto spacing */
        .content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 0; /* Important for flex children */
        }
        
        /* Premium Badge with Glow */
        .badge-premium {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(10px);
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
            border: 1px solid rgba(255,255,255,0.8);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            margin-bottom: 12px;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
        }
        
        .badge-premium::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: rotate(45deg);
            animation: badgeShine 4s infinite;
        }
        
        @keyframes badgeShine {
            0% { transform: translateX(-100%) rotate(45deg); }
            20%, 100% { transform: translateX(100%) rotate(45deg); }
        }
        
        .badge-premium i {
            color: var(--secondary);
            font-size: 14px;
        }
        
        /* Premium 3D Illustration */
        .illustration {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 8px 0 20px;
            perspective: 800px;
        }
        
        .grid-3d {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            width: 160px;
            transform-style: preserve-3d;
            animation: rotateGrid 10s ease-in-out infinite;
        }
        
        @keyframes rotateGrid {
            0%, 100% { transform: rotateY(0deg) rotateX(5deg); }
            25% { transform: rotateY(5deg) rotateX(8deg); }
            75% { transform: rotateY(-5deg) rotateX(2deg); }
        }
        
        .grid-item {
            width: 100%;
            aspect-ratio: 1/1;
            background: white;
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 28px;
            box-shadow: 0 25px 35px -10px rgba(10,46,36,0.15), 0 0 0 1px rgba(255,255,255,0.8) inset;
            transform: translateZ(10px);
            transition: all 0.3s ease;
            animation: gridItemFloat 3s ease-in-out infinite;
        }
        
        .grid-item i {
            font-size: 28px;
            width: auto;
            height: auto;
            display: inline-block;
        }
        
        .grid-item:nth-child(1) { animation-delay: 0s; background: linear-gradient(145deg, #FFFFFF, #FAF8F5); }
        .grid-item:nth-child(2) { animation-delay: 0.2s; background: linear-gradient(145deg, #FFFFFF, #FAF8F5); }
        .grid-item:nth-child(3) { animation-delay: 0.4s; background: linear-gradient(145deg, #FFFFFF, #FAF8F5); }
        .grid-item:nth-child(4) { animation-delay: 0.6s; background: linear-gradient(145deg, #FFFFFF, #FAF8F5); }
        
        @keyframes gridItemFloat {
            0%, 100% { transform: translateZ(10px) translateY(0); }
            50% { transform: translateZ(20px) translateY(-5px); }
        }
        
        /* Typography with Gradient */
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
            font-weight: 400;
        }
        
        /* Premium Points with Hover Effect */
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
            border: 1px solid transparent;
        }
        
        .point:hover {
            background: rgba(255,255,255,0.6);
            border-color: rgba(232,92,63,0.2);
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
            transition: all 0.3s ease;
        }
        
        .point-icon i {
            font-size: 20px;
            width: auto;
            height: auto;
        }
        
        .point:hover .point-icon {
            transform: scale(1.05) rotate(5deg);
        }
        
        .point-text {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            line-height: 1.3;
        }
        
        /* Premium Button with Ripple Effect */
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
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
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
        
        .btn-premium i {
            font-size: 15px;
            transition: transform 0.3s;
        }
        
        .btn-premium:hover i {
            transform: translateX(5px);
        }
        
        /* Premium Pagination */
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
            position: relative;
        }
        
        .dot.active {
            width: 30px;
            background: var(--secondary);
            box-shadow: 0 0 0 4px rgba(232,92,63,0.2);
        }
        
        .dot.active::after {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            border-radius: 12px;
            background: rgba(232,92,63,0.1);
            z-index: -1;
            animation: pulseDot 2s infinite;
        }
        
        @keyframes pulseDot {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.2; }
        }
        
        /* Responsive untuk layar kecil */
        @media (max-height: 700px) {
            .card { padding: 20px 20px; }
            .grid-3d { width: 140px; gap: 12px; }
            .grid-item { font-size: 24px; }
            .grid-item i { font-size: 24px; }
            .point { gap: 12px; padding: 4px 8px; }
            .point-icon { width: 40px; height: 40px; font-size: 18px; }
            .point-icon i { font-size: 18px; }
            .headline { font-size: 26px; }
            .subheadline { font-size: 13px; margin-bottom: 16px; }
            .btn-premium { height: 50px; margin: 12px 0 12px; }
        }
        
        @media (max-height: 600px) {
            .card { padding: 16px 20px; }
            .grid-3d { width: 120px; gap: 10px; }
            .grid-item { font-size: 22px; }
            .grid-item i { font-size: 22px; }
            .headline { font-size: 24px; }
            .point-icon { width: 36px; height: 36px; font-size: 16px; }
            .point-icon i { font-size: 16px; }
            .point-text { font-size: 13px; }
            .btn-premium { height: 46px; font-size: 14px; }
            .points { gap: 8px; margin-bottom: 12px; }
        }
        
        /* Center fix for all screen sizes */
        @media (max-width: 380px) {
            .card { max-width: 320px; padding: 20px 18px; }
        }
    </style>
</head>
<body>
    <!-- Dynamic Background -->
    <div class="bg-gradient"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    
    <div class="container">
        <div class="card">
            <div class="badge-premium">
                <i class="fas fa-crown"></i>
                <span>ENTERPRISE LEAD ENGINE</span>
            </div>
            
            <div class="content">
                <div class="illustration">
                    <div class="grid-3d">
                        <div class="grid-item"><i class="fas fa-chart-line"></i></div>
                        <div class="grid-item"><i class="fas fa-users"></i></div>
                        <div class="grid-item"><i class="fas fa-bullseye"></i></div>
                        <div class="grid-item"><i class="fas fa-rocket"></i></div>
                    </div>
                </div>
                
                <h1 class="headline">Lead Engine<br>Property</h1>
                <p class="subheadline">Intelligence-driven lead distribution untuk agency & developer properti</p>
                
                <div class="points">
                    <div class="point">
                        <div class="point-icon"><i class="fas fa-chart-line"></i></div>
                        <span class="point-text">Tracking real-time dari semua sumber iklan</span>
                    </div>
                    <div class="point">
                        <div class="point-icon"><i class="fas fa-brain"></i></div>
                        <span class="point-text">Smart scoring — hot, warm, cold otomatis</span>
                    </div>
                    <div class="point">
                        <div class="point-icon"><i class="fas fa-shuffle"></i></div>
                        <span class="point-text">Distribusi round robin & split 50:50</span>
                    </div>
                </div>
            </div>
            
            <div>
                <button class="btn-premium" onclick="window.location.href='page2.php'">
                    <span>Lihat Cara Kerja</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
                
                <div class="pagination">
                    <span class="dot active"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tracking Pixel Events -->
    <script>
    fbq('track', 'ViewContent', {
        content_name: 'Splash Page 1',
        content_category: 'Onboarding'
    });
    
    ttq.track('ViewContent', {
        content_name: 'Splash Page 1',
        content_category: 'Onboarding'
    });
    </script>
</body>
</html>