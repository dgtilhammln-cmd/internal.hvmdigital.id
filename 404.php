<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #050505;
            --neon-1: #a1ff5a;
            --neon-2: #4efdc4;
            --text-white: #ffffff;
            --glass-border: rgba(255, 255, 255, 0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Montserrat', sans-serif; }
        
        body {
            background-color: var(--bg-color); color: var(--text-white);
            height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center;
            overflow: hidden; position: relative; text-align: center;
        }

        /* Ambient Glow */
        .ambient-glow {
            position: absolute; border-radius: 50%; filter: blur(150px); opacity: 0.2; z-index: -1;
        }
        .glow-1 { top: -20%; left: -10%; width: 800px; height: 800px; background: var(--neon-1); }
        .glow-2 { bottom: -20%; right: -10%; width: 800px; height: 800px; background: var(--neon-2); }

        .container {
            position: relative; z-index: 10;
        }

        /* Glitch Effect for 404 */
        .error-code {
            font-size: 10rem; font-weight: 900; line-height: 1;
            background: linear-gradient(135deg, #fff, #888);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            position: relative; display: inline-block;
            text-shadow: 0 0 20px rgba(255,255,255,0.2);
        }
        
        .error-code::before, .error-code::after {
            content: '404'; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: var(--bg-color); overflow: hidden; opacity: 0.7;
        }
        .error-code::before {
            color: var(--neon-1); z-index: -1; animation: glitch-1 3s infinite reverse;
        }
        .error-code::after {
            color: var(--neon-2); z-index: -2; animation: glitch-2 3s infinite;
        }

        @keyframes glitch-1 {
            0% { transform: translate(0); } 20% { transform: translate(-2px, 2px); }
            40% { transform: translate(-2px, -2px); } 60% { transform: translate(2px, 2px); }
            80% { transform: translate(2px, -2px); } 100% { transform: translate(0); }
        }
        @keyframes glitch-2 {
            0% { transform: translate(0); } 20% { transform: translate(2px, -2px); }
            40% { transform: translate(2px, 2px); } 60% { transform: translate(-2px, -2px); }
            80% { transform: translate(-2px, 2px); } 100% { transform: translate(0); }
        }

        .error-title { font-size: 2rem; font-weight: 700; margin-top: 10px; color: #fff; }
        .error-desc { color: #888; margin: 15px 0 40px; font-size: 1rem; max-width: 400px; margin-left: auto; margin-right: auto; line-height: 1.6; }

        /* Astronaut Animation */
        .astro-box {
            font-size: 4rem; color: var(--neon-2); margin-bottom: 20px;
            animation: float 6s infinite ease-in-out;
        }
        @keyframes float { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-20px) rotate(5deg); } }

        /* Back Button */
        .btn-home {
            padding: 15px 40px; background: linear-gradient(90deg, var(--neon-1), var(--neon-2));
            color: #000; font-weight: 800; border: none; border-radius: 50px;
            text-decoration: none; font-size: 1rem; transition: 0.3s;
            box-shadow: 0 0 20px rgba(161, 255, 90, 0.3); display: inline-flex; align-items: center; gap: 10px;
        }
        .btn-home:hover { transform: scale(1.05); box-shadow: 0 0 40px rgba(161, 255, 90, 0.5); }

    </style>
</head>
<body>
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="container">
        <div class="astro-box"><i class="fas fa-user-astronaut"></i></div>
        
        <div class="error-code">404</div>
        <h2 class="error-title">Halaman Hilang di Angkasa</h2>
        <p class="error-desc">
            Maaf, halaman yang Anda cari mungkin telah dipindahkan, dihapus, atau tidak pernah ada dalam sistem database kami.
        </p>

        <a href="/dashboard/" class="btn-home">
            <i class="fas fa-rocket"></i> Kembali ke Dashboard
        </a>
    </div>
</body>
</html>