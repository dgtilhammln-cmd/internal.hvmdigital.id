<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out...</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #050505;
            --neon-1: #a1ff5a;
            --neon-2: #4efdc4;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-white: #ffffff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Montserrat', sans-serif; }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-white);
            height: 100vh;
            display: flex; justify-content: center; align-items: center;
            overflow: hidden; position: relative;
        }

        /* Ambient Glow */
        .ambient-glow {
            position: absolute; border-radius: 50%; filter: blur(120px); opacity: 0.3; z-index: -1;
            animation: floatGlow 8s infinite alternate;
        }
        .glow-1 { top: -100px; left: -100px; width: 500px; height: 500px; background: var(--neon-1); }
        .glow-2 { bottom: -100px; right: -100px; width: 500px; height: 500px; background: var(--neon-2); }
        @keyframes floatGlow { from { transform: scale(1); } to { transform: scale(1.1); } }

        /* Logout Card */
        .logout-card {
            background: rgba(20, 20, 20, 0.6); backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border); border-radius: 24px;
            padding: 50px; text-align: center; width: 400px; max-width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative; overflow: hidden;
        }

        /* Top Line Neon */
        .logout-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px;
            background: linear-gradient(90deg, var(--neon-1), var(--neon-2));
            box-shadow: 0 0 20px var(--neon-1);
        }

        .icon-box {
            font-size: 3rem; color: var(--neon-1); margin-bottom: 20px;
            animation: pulse 1.5s infinite;
        }

        h2 { font-size: 1.8rem; font-weight: 700; margin-bottom: 10px; color: #fff; }
        p { color: #888; font-size: 0.9rem; margin-bottom: 30px; }

        /* Loader Bar */
        .loader-container {
            width: 100%; height: 6px; background: rgba(255,255,255,0.1);
            border-radius: 10px; overflow: hidden; position: relative;
        }
        .loader-bar {
            width: 0%; height: 100%; background: linear-gradient(90deg, var(--neon-1), var(--neon-2));
            border-radius: 10px; animation: load 2s forwards ease-in-out;
            box-shadow: 0 0 15px var(--neon-1);
        }

        @keyframes popIn { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
        @keyframes pulse { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.1); opacity: 0.8; } 100% { transform: scale(1); opacity: 1; } }
        @keyframes load { 0% { width: 0%; } 100% { width: 100%; } }

    </style>
</head>
<body>
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="logout-card">
        <div class="icon-box">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h2>See You Soon!</h2>
        <p>You have successfully logged out of the HVM Digital system..</p>
        
        <div class="loader-container">
            <div class="loader-bar"></div>
        </div>
        <p style="margin-top: 15px; font-size: 0.75rem; color: #666;">Redirecting to login...</p>
    </div>

    <script>
        // Redirect setelah 2.5 detik
        setTimeout(() => {
            window.location.href = '/';
        }, 2500);
    </script>
</body>
</html>