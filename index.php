<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

$login_success = false;
$login_error = '';
$user_name = '';

if(isset($_POST['do_login'])) {
    $input = mysqli_real_escape_string($conn, $_POST['username']); 
    $pass = $_POST['password'];

    $q = "SELECT * FROM teams WHERE email='$input' OR name='$input'";
    $res = mysqli_query($conn, $q);

    if(mysqli_num_rows($res) > 0){
        $row = mysqli_fetch_assoc($res);
        if(password_verify($pass, $row['password'])){
            $_SESSION['admin'] = $row['name'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['team_id'] = $row['team_id'];
            $login_success = true;
            $user_name = $row['name'];
        } else {
            $login_error = "Password Anda salah!";
        }
    } else {
        $q_old = "SELECT * FROM admin_users WHERE username='$input'";
        $res_old = mysqli_query($conn, $q_old);
        if(mysqli_num_rows($res_old) > 0){
            $row_old = mysqli_fetch_assoc($res_old);
            if($pass == $row_old['password']) {
                $_SESSION['admin'] = $row_old['username'];
                $_SESSION['role'] = $row_old['role'] ?? 'super_admin';
                $login_success = true;
                $user_name = $row_old['username'];
            } else {
                $login_error = "Password Anda salah!";
            }
        } else {
            $login_error = "Akun tidak ditemukan!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>HVM Digital - Internal Access</title>
    <link rel="shortcut icon" href="uploads/icon.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #050505;
            --neon-1: #a1ff5a;
            --neon-2: #4efdc4;
            --glass-border: rgba(255, 255, 255, 0.07);
            --text-white: #ffffff;
            --text-muted: #8b8b93;
            --gradient-main: linear-gradient(135deg, var(--neon-1), var(--neon-2));
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Montserrat', sans-serif; }

        body {
            background-color: var(--bg-color);
            background-image:
                radial-gradient(ellipse 55% 50% at 5% 50%, rgba(161,255,90,0.06) 0%, transparent 70%),
                radial-gradient(ellipse 45% 55% at 95% 50%, rgba(78,253,196,0.05) 0%, transparent 70%);
            color: var(--text-white);
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100vw;
        }

        /* =========================================
           SPLIT WRAPPER
           ========================================= */
        .split-wrapper {
            display: flex;
            width: 88%;
            max-width: 980px;
            height: 580px;
            border-radius: 28px;
            overflow: hidden;
            box-shadow:
                0 0 0 1px rgba(161,255,90,0.10),
                0 40px 100px rgba(0,0,0,0.75),
                0 0 60px rgba(161,255,90,0.03);
            animation: popIn 0.75s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        /* =========================================
           LEFT PANEL — dark forest, zero blue
           ========================================= */
        .left-panel {
            flex: 1.1;
            background:
                radial-gradient(ellipse 90% 60% at 15% 15%, rgba(161,255,90,0.11) 0%, transparent 55%),
                radial-gradient(ellipse 70% 70% at 85% 85%, rgba(78,253,196,0.09) 0%, transparent 55%),
                linear-gradient(155deg, #0c160c 0%, #080e08 45%, #050505 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 42px 38px;
            position: relative;
            overflow: hidden;
            border-right: 1px solid rgba(161,255,90,0.07);
        }

        /* Soft glow orb top-right */
        .left-panel::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(161,255,90,0.12) 0%, transparent 70%);
            pointer-events: none;
        }
        /* Soft glow orb bottom-left */
        .left-panel::after {
            content: '';
            position: absolute;
            bottom: -40px; left: -40px;
            width: 240px; height: 240px;
            background: radial-gradient(circle, rgba(78,253,196,0.09) 0%, transparent 70%);
            pointer-events: none;
        }

        .left-top, .left-bottom { position: relative; z-index: 1; }

        .left-logo { height: 36px; width: auto; object-fit: contain; margin-bottom: 28px; display: block; }

        .badge-pill {
            background: rgba(161,255,90,0.08);
            border: 1px solid rgba(161,255,90,0.22);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.68rem;
            color: var(--neon-1);
            display: inline-flex; align-items: center; gap: 7px;
            letter-spacing: 1.5px; text-transform: uppercase;
            margin-bottom: 22px;
        }
        .badge-pill i { font-size: 0.65rem; }

        .accent-line {
            width: 44px; height: 3px;
            background: var(--gradient-main);
            border-radius: 2px; margin-bottom: 20px;
        }

        .left-title {
            font-size: 2.1rem; font-weight: 800; line-height: 1.18;
            color: #fff; margin-bottom: 14px;
        }
        .left-title span {
            display: block;
            background: var(--gradient-main);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 14px rgba(161,255,90,0.35));
        }

        .left-desc {
            font-size: 0.82rem; color: var(--text-muted);
            line-height: 1.7; max-width: 258px;
        }

        /* Info cards */
        .left-bottom { display: flex; flex-direction: column; gap: 10px; }

        .info-card {
            background: rgba(161,255,90,0.04);
            border: 1px solid rgba(161,255,90,0.09);
            border-radius: 14px; padding: 13px 16px;
            display: flex; align-items: center; gap: 14px;
            transition: background 0.3s, border-color 0.3s;
        }
        .info-card:first-child { border-left: 2px solid var(--neon-1); }
        .info-card:last-child  { border-left: 2px solid var(--neon-2); }
        .info-card:hover { background: rgba(161,255,90,0.08); border-color: rgba(161,255,90,0.2); }
        .info-card i {
            font-size: 1.2rem;
            background: var(--gradient-main);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            width: 20px; text-align: center;
        }
        .info-card h4 { font-size: 0.82rem; font-weight: 700; color: #d4ffd4; margin: 0; }
        .info-card p  { font-size: 0.62rem; color: #3d6b3d; margin: 0; text-transform: uppercase; letter-spacing: 1px; }

        /* =========================================
           RIGHT PANEL
           ========================================= */
        .right-panel {
            flex: 1;
            background: #070707;
            display: flex; flex-direction: column; justify-content: center;
            padding: 50px 44px;
            position: relative;
        }

        /* Neon line top */
        .right-panel::before {
            content: '';
            position: absolute; top: 0; left: 50%;
            transform: translateX(-50%);
            width: 55%; height: 2px;
            background: var(--gradient-main);
            box-shadow: 0 0 20px rgba(161,255,90,0.7), 0 0 50px rgba(78,253,196,0.35);
            border-radius: 0 0 8px 8px;
        }

        .right-logo { height: 30px; width: auto; object-fit: contain; margin-bottom: 24px; display: block; }
        .login-title    { font-size: 1.65rem; font-weight: 800; color: #fff; margin-bottom: 5px; }
        .login-subtitle { font-size: 0.78rem; color: #404040; margin-bottom: 28px; }

        /* Error */
        .error-msg {
            background: rgba(255,69,96,0.08); border: 1px solid rgba(255,69,96,0.35);
            color: #ff4560; padding: 11px 14px; border-radius: 10px;
            margin-bottom: 18px; font-size: 0.82rem; font-weight: 600;
            display: flex; align-items: center; gap: 9px;
        }

        /* Inputs */
        .input-group { margin-bottom: 16px; }
        .input-group label {
            display: block; color: #3a3a3a;
            font-size: 0.7rem; font-weight: 700;
            margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.8px;
        }
        .glass-input {
            width: 100%; padding: 13px 15px;
            background: rgba(255,255,255,0.025);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 11px; color: #fff; outline: none;
            font-size: 0.92rem; transition: border-color 0.25s, background 0.25s, box-shadow 0.25s;
            font-family: 'Montserrat', sans-serif;
        }
        .glass-input::placeholder { color: #252525; }
        .glass-input:focus {
            border-color: rgba(161,255,90,0.40);
            background: rgba(161,255,90,0.04);
            box-shadow: 0 0 0 3px rgba(161,255,90,0.06);
        }

        /* Submit */
        .btn-submit {
            width: 100%; padding: 14px;
            background: var(--gradient-main);
            color: #040804; border: none; border-radius: 11px;
            font-weight: 800; font-size: 0.9rem; cursor: pointer;
            margin-top: 6px; transition: transform 0.25s, box-shadow 0.25s;
            letter-spacing: 0.8px; text-transform: uppercase;
            font-family: 'Montserrat', sans-serif;
            box-shadow: 0 4px 28px rgba(161,255,90,0.28);
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 38px rgba(78,253,196,0.38); }
        .btn-submit:active { transform: translateY(0); }

        .footer-text { margin-top: 22px; color: #1e1e1e; font-size: 0.68rem; text-align: center; }

        /* =========================================
           LOADING OVERLAY
           ========================================= */
        .loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: var(--bg-color); z-index: 20000;
            display: none; justify-content: center; align-items: center;
            opacity: 0; transition: opacity 0.5s ease;
        }
        .loading-overlay.active { display: flex; opacity: 1; }

        .loading-card {
            background: rgba(12,18,12,0.9); backdrop-filter: blur(30px);
            border: 1px solid rgba(161,255,90,0.12); border-radius: 24px;
            padding: 50px; text-align: center;
            width: 380px; max-width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.7);
            position: relative; overflow: hidden;
        }
        .loading-card::before {
            content: ''; position: absolute; top: 0; left: 0;
            width: 100%; height: 3px;
            background: var(--gradient-main);
            box-shadow: 0 0 20px var(--neon-1);
        }
        .icon-box { font-size: 3rem; color: var(--neon-1); margin-bottom: 20px; animation: pulse 1.5s infinite; }
        .loader-container { width: 100%; height: 5px; background: rgba(255,255,255,0.07); border-radius: 10px; overflow: hidden; margin-top: 20px; }
        .loader-bar { width: 0%; height: 100%; background: var(--gradient-main); border-radius: 10px; animation: load 2s forwards ease-in-out; box-shadow: 0 0 12px var(--neon-1); }

        /* =========================================
           KEYFRAMES
           ========================================= */
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.93) translateY(28px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50%       { transform: scale(1.1); opacity: 0.75; }
        }
        @keyframes load { from { width: 0%; } to { width: 100%; } }

        /* =========================================
           RESPONSIVE
           ========================================= */
        @media (max-width: 768px) {
            body { align-items: stretch; justify-content: stretch; }
            .split-wrapper {
                flex-direction: column; width: 100%; height: 100dvh;
                border-radius: 0; border: none; box-shadow: none;
            }
            .left-panel  { flex: none; padding: 28px 22px 22px; }
            .left-logo   { height: 28px; margin-bottom: 14px; }
            .badge-pill  { display: none; }
            .accent-line { margin-bottom: 10px; }
            .left-title  { font-size: 1.45rem; margin-bottom: 8px; }
            .left-desc   { font-size: 0.78rem; max-width: 100%; }
            .left-bottom { flex-direction: row; gap: 8px; margin-top: 14px; }
            .info-card   { flex: 1; padding: 10px 12px; gap: 8px; }
            .right-panel { flex: 1; padding: 28px 22px; }
            .right-panel::before { display: none; }
            .right-logo  { display: none; }
            .login-title { font-size: 1.35rem; }
        }
    </style>
</head>
<body>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-card">
            <div class="icon-box"><i class="fas fa-rocket"></i></div>
            <h2 style="color:#fff; font-size:1.4rem; margin-bottom:5px;">Welcome Team!</h2>
            <p style="color:#555; font-size:0.85rem;">Initializing Dashboard...</p>
            <div class="loader-container"><div class="loader-bar"></div></div>
        </div>
    </div>

    <!-- Split Wrapper -->
    <div class="split-wrapper">

        <!-- LEFT: Branding -->
        <div class="left-panel">
            <div class="left-top">
                <img src="uploads/logohvm.png" alt="HVM Logo" class="left-logo">
                <div class="badge-pill"><i class="fas fa-layer-group"></i> Internal System v2.0</div>
                <div class="accent-line"></div>
                <h1 class="left-title">The Next Gen <span>Database System.</span></h1>
                <p class="left-desc">Centralized ecosystem for data automation, team collaboration, and real-time performance tracking with high-level security.</p>
            </div>
            <div class="left-bottom">
                <div class="info-card">
                    <i class="fas fa-shield-alt"></i>
                    <div><h4>SECURE</h4><p>Encrypted Data</p></div>
                </div>
                <div class="info-card">
                    <i class="fas fa-database"></i>
                    <div><h4>REALTIME</h4><p>Live Sync</p></div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Login Form -->
        <div class="right-panel">
            <img src="uploads/logohvm.png" alt="Logo" class="right-logo">
            <h2 class="login-title">Team Login</h2>
            <p class="login-subtitle">Masukkan kredensial untuk mengakses sistem</p>

            <?php if(isset($login_error) && !empty($login_error)): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $login_error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label>Email or Username</label>
                    <input type="text" name="username" class="glass-input" placeholder="Enter your email" required autocomplete="off">
                </div>
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" class="glass-input" placeholder="••••••••" required>
                </div>
                <button type="submit" name="do_login" class="btn-submit">
                    Enter System <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <p class="footer-text">Protected by HVM Secure Gateway &copy; 2025</p>
        </div>

    </div>

    <script>
        const overlay = document.getElementById('loadingOverlay');
        <?php if(isset($login_success) && $login_success): ?>
            overlay.classList.add('active');
            setTimeout(() => { window.location.href = '/dashboard/'; }, 2200);
        <?php endif; ?>
    </script>

</body>
</html>