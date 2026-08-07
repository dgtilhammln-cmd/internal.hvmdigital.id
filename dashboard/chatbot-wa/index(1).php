<?php
/* =========================================
   NEBULA COMMAND CENTER - ULTIMATE EDITION
   ========================================= */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

// 1. SECURITY & ACCESS
if(!isset($_SESSION['admin'])){ 
    echo "<script>alert('Akses Ditolak. Silahkan Login!'); window.location='/index.php';</script>"; exit; 
}

// 2. AJAX HANDLER: TOGGLE BOT PER CHAT
if (isset($_POST['action']) && $_POST['action'] == 'toggle_bot') {
    $chat_id = mysqli_real_escape_string($conn, $_POST['chat_id']);
    $status = (int)$_POST['status'];
    mysqli_query($conn, "INSERT INTO chat_controls (chat_id, bot_enabled) 
                         VALUES ('$chat_id', $status) 
                         ON DUPLICATE KEY UPDATE bot_enabled = $status");
    exit(json_encode(['status' => 'success']));
}

// 3. AJAX HANDLER: RESTART BOT ENGINE
if (isset($_POST['action']) && $_POST['action'] == 'restart_bot') {
    file_put_contents('restart.lock', 'restart');
    exit(json_encode(['status' => 'restarting']));
}

// 4. FETCH DATA SIDEBAR (DAFTAR CHAT TERAKHIR)
$selected_chat = $_GET['chat'] ?? '';
$q_sidebar = "SELECT sender_wa, message, role, created_at 
              FROM chat_memories 
              WHERE id IN (SELECT MAX(id) FROM chat_memories GROUP BY sender_wa) 
              ORDER BY created_at DESC";
$res_sidebar = mysqli_query($conn, $q_sidebar);

// 5. FETCH DATA PESAN (JIKA CHAT DIPILIH)
$messages = [];
$bot_enabled = true;
if($selected_chat) {
    $chat_id_safe = mysqli_real_escape_string($conn, $selected_chat);
    $q_msg = "SELECT * FROM chat_memories WHERE sender_wa = '$chat_id_safe' ORDER BY id DESC LIMIT 50";
    $res_msg = mysqli_query($conn, $q_msg);
    while($row = mysqli_fetch_assoc($res_msg)) $messages[] = $row;

    // Cek Status Bot per chat ini
    $q_status = mysqli_query($conn, "SELECT bot_enabled FROM chat_controls WHERE chat_id = '$chat_id_safe'");
    $r_status = mysqli_fetch_assoc($q_status);
    $bot_enabled = ($r_status['bot_enabled'] ?? 1) == 1;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nebula OS - Command Center</title>
    
    <!-- FAVICON -->
    <link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
    
    <!-- FONTS & ICONS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS UTAMA (Gunakan file style.css Anda atau CSS Global) -->
    <link rel="stylesheet" href="../style.css">

    <style>
        :root {
            --neon-main: #00ff88;
            --glass-bg: rgba(10, 10, 10, 0.75);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        body { overflow-x: hidden; }

        /* --- NEBULA INTERFACE LAYOUT --- */
        .nebula-grid {
            display: flex;
            height: calc(100vh - 180px);
            gap: 25px;
            margin-top: 20px;
        }

        /* Navigasi Daftar Chat */
        .chat-navigator {
            width: 380px;
            background: var(--glass-bg);
            backdrop-filter: blur(25px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .nav-header { padding: 25px; border-bottom: 1px solid var(--glass-border); }
        .chat-scroller { flex: 1; overflow-y: auto; padding: 15px; }

        .chat-card {
            padding: 18px 20px;
            border-radius: 20px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid transparent;
        }

        .chat-card:hover { background: rgba(255,255,255,0.05); }
        .chat-card.active { 
            background: rgba(0, 255, 136, 0.08); 
            border-color: rgba(0, 255, 136, 0.2);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .chat-target { color: #fff; font-weight: 700; font-size: 0.9rem; display: block; }
        .chat-preview { font-size: 0.75rem; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Jendela Percakapan */
        .conversation-engine {
            flex: 1;
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .conv-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255,255,255,0.02);
        }

        .conv-body {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            display: flex;
            flex-direction: column-reverse;
            gap: 15px;
        }

        /* Bubble Chat */
        .bubble {
            max-width: 70%;
            padding: 14px 20px;
            border-radius: 22px;
            font-size: 0.9rem;
            line-height: 1.6;
            color: #fff;
            position: relative;
        }

        .bubble.user {
            align-self: flex-start;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            border-bottom-left-radius: 4px;
        }

        .bubble.assistant {
            align-self: flex-end;
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.2);
            border-bottom-right-radius: 4px;
        }

        .bubble-time { font-size: 0.65rem; color: #666; display: block; margin-top: 5px; text-align: right; }

        /* Neon Switch */
        .neon-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .neon-switch input { opacity: 0; width: 0; height: 0; }
        .neon-slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #222; transition: .4s; border-radius: 34px;
        }
        .neon-slider:before {
            position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px;
            background-color: #555; transition: .4s; border-radius: 50%;
        }
        input:checked + .neon-slider { background-color: rgba(0, 255, 136, 0.2); border: 1px solid var(--neon-main); }
        input:checked + .neon-slider:before { transform: translateX(24px); background-color: var(--neon-main); box-shadow: 0 0 10px var(--neon-main); }

        /* Floating QR */
        .qr-card {
            position: absolute; bottom: 30px; right: 30px; width: 200px;
            background: #000; padding: 15px; border-radius: 20px;
            border: 2px solid var(--neon-main); text-align: center;
            box-shadow: 0 10px 40px rgba(0, 255, 136, 0.3); z-index: 1000;
        }

        /* --- RESPONSIVE MOBILE --- */
        @media (max-width: 768px) {
            .nebula-grid { flex-direction: column; height: auto; padding-bottom: 100px; }
            .chat-navigator { width: 100%; height: 300px; }
            .conversation-engine { height: 500px; }
            .main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>
    
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <!-- INCLUDE SIDEBAR TERBARU -->
    <?php include 'sidebar-chatbot.php'; ?>

    <div class="dashboard-wrapper">
        <main class="main-content">
            <div class="page-headline">
                <h1>Nebula Command Center</h1>
                <p>Monitor real-time AI activity and manage corporate responses.</p>
            </div>

            <!-- ACTION BAR -->
            <div class="action-bar">
                <div class="status-badge online" id="botStatus">
                    <i class="fas fa-circle" style="font-size:0.6rem; color:var(--neon-main);"></i> ENGINE ACTIVE
                </div>
                <div style="display:flex; gap:12px;">
                    <button class="btn-secondary" onclick="restartBot()"><i class="fas fa-sync-alt"></i> Restart</button>
                    <button class="btn-grad" onclick="window.location.href='add_knowledge.php'"><i class="fas fa-brain"></i> AI Training</button>
                </div>
            </div>

            <!-- INTERFACE UTAMA -->
            <div class="nebula-grid">
                
                <!-- LIST CHAT -->
                <div class="chat-navigator">
                    <div class="nav-header">
                        <input type="text" class="search-input" placeholder="Cari percakapan..." style="width:100%">
                    </div>
                    <div class="chat-scroller">
                        <?php while($c = mysqli_fetch_assoc($res_sidebar)): ?>
                        <div class="chat-card <?php echo ($selected_chat == $c['sender_wa']) ? 'active' : ''; ?>" 
                             onclick="window.location.href='?chat=<?php echo $c['sender_wa']; ?>'">
                            <div class="avatar-box" style="width:45px; height:45px; margin-bottom:0; border: 1px solid rgba(255,255,255,0.1);">
                                <i class="fas fa-user-tie" style="line-height:43px; font-size:1.2rem; color:#fff;"></i>
                            </div>
                            <div class="chat-info">
                                <span class="chat-target"><?php echo substr($c['sender_wa'], 0, 15); ?>...</span>
                                <div class="chat-preview"><?php echo htmlspecialchars($c['message']); ?></div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- JENDELA CHAT -->
                <div class="conversation-engine">
                    <?php if($selected_chat): ?>
                        <div class="conv-header">
                            <div>
                                <h3 style="font-size:1rem; color:#fff; margin:0;"><?php echo $selected_chat; ?></h3>
                                <small style="color:var(--neon-main); font-weight:700; font-size:0.65rem; text-transform:uppercase;">
                                    <?php echo $bot_enabled ? 'Autonomous AI Mode' : 'Admin Handover Mode'; ?>
                                </small>
                            </div>
                            <div style="display:flex; align-items:center; gap:15px;">
                                <span style="font-size:0.7rem; font-weight:700; color:#aaa;">BOT AI</span>
                                <label class="neon-switch">
                                    <input type="checkbox" <?php echo $bot_enabled ? 'checked' : ''; ?> 
                                           onchange="toggleBot('<?php echo $selected_chat; ?>', this.checked)">
                                    <span class="neon-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="conv-body" id="chatBody">
                            <?php foreach($messages as $m): ?>
                                <div class="bubble <?php echo $m['role']; ?>">
                                    <?php echo nl2br(htmlspecialchars($m['message'])); ?>
                                    <span class="bubble-time"><?php echo date('H:i', strtotime($m['created_at'])); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php else: ?>
                        <!-- EMPTY STATE -->
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; opacity:0.3;">
                            <i class="fas fa-robot" style="font-size:5rem; margin-bottom:20px; color:var(--neon-main);"></i>
                            <h3>Pilih percakapan untuk memantau</h3>
                        </div>
                    <?php endif; ?>

                    <!-- EMERGENCY QR -->
                    <?php if(file_exists('qrcode.png')): ?>
                    <div class="qr-card">
                        <h4 style="font-size:0.8rem; margin-bottom:10px; color:var(--neon-main);">DISCONNECTED</h4>
                        <img src="qrcode.png?t=<?php echo time(); ?>" width="100%" style="border-radius:10px;">
                        <p style="font-size:0.6rem; margin-top:8px; color:#888;">Segera scan untuk menyambungkan bot</p>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- POPUP -->
    <div id="popup" class="popup">
        <i class="fas fa-check-circle"></i>
        <span id="popupMsg"></span>
    </div>

    <script>
        function toggleBot(chatId, isEnabled) {
            const formData = new FormData();
            formData.append('action', 'toggle_bot');
            formData.append('chat_id', chatId);
            formData.append('status', isEnabled ? 1 : 0);

            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(() => {
                showPopup('success', 'Status bot untuk ' + chatId + ' diperbarui.');
                setTimeout(() => location.reload(), 1000);
            });
        }

        function restartBot() {
            if(!confirm('Restart engine Nebula?')) return;
            const formData = new FormData();
            formData.append('action', 'restart_bot');

            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(() => {
                showPopup('success', 'Sinyal restart dikirim ke server lokal.');
            });
        }

        function showPopup(type, msg) {
            const p = document.getElementById('popup');
            document.getElementById('popupMsg').innerText = msg;
            p.className = 'popup ' + type + ' show';
            setTimeout(() => { p.classList.remove('show'); }, 3000);
        }
    </script>
</body>
</html>