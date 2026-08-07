<?php
/**
 * HVM WorkSpace - Brainstorming Deck
 * Full Code Rev 1.0
 */
session_start();

// 1. KONEKSI DATABASE
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

// 2. CEK LOGIN
if(!isset($_SESSION['admin'])){ 
    header("Location: /"); 
    exit; 
}

// 3. UPDATE LAST SEEN (Real-time Presence Logic)
$user_logged = $_SESSION['admin'];
$user_id_safe = mysqli_real_escape_string($conn, $user_logged);
mysqli_query($conn, "UPDATE teams SET last_seen = NOW() WHERE name = '$user_id_safe'");

// 4. DATA TAMBAHAN
$full_name = $user_logged;
$session_id = $_GET['id'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>HVM | Brainstorming Deck</title>
    <link rel="shortcut icon" href="/uploads/icon.png">
    
    <!-- STYLESHEETS -->
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    
<!-- 1. Panggil Fabric.js duluan (Wajib Paling Atas) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>

    <!-- 2. Panggil SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- 3. Panggil Engine dengan Path Absolut agar tidak 404 -->
    <!-- Kita tambahkan ?v= waktu agar browser selalu ambil versi terbaru (anti-cache) -->
    <script src="/dashboard/workspace/brainstorm/brainstorm-engine.js?v=<?php echo time(); ?>"></script>

</head>
<body class="brainstorm-page">

    <div class="dashboard-wrapper">
        
        <!-- 1. SIDEBAR NAVIGATION -->
        <?php 
            $sidebar_path = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/workspace/brainstorm/sidebar.php';
            if(file_exists($sidebar_path)) {
                include $sidebar_path;
            } else {
                echo "<div style='color:red; position:fixed; left:20px; bottom:20px; z-index:9999; background:#000; padding:10px; border:1px solid red;'>[System Error] Sidebar Not Found.</div>";
            }
        ?>

        <!-- 2. MAIN CONTENT AREA -->
        <main class="main-content">
            <!-- Background Aesthetics -->
            <div class="ambient-glow glow-1"></div>
            <div class="ambient-glow glow-2"></div>

            <div class="brainstorm-container">
                
                <!-- TOOLBAR (Floating Left) -->
                <aside class="canvas-toolbar animate-slide-right">
                    <div class="tool-section">
                        <button class="t-btn active" id="tool-select" onclick="changeTool('select')" title="Select (V)"><i class="fas fa-mouse-pointer"></i></button>
                        <button class="t-btn" id="tool-draw" onclick="changeTool('draw')" title="Draw (P)"><i class="fas fa-pen-nib"></i></button>
                        <button class="t-btn" onclick="clearCanvas()" title="Clear All"><i class="fas fa-trash-alt"></i></button>
                    </div>
                    
                    <div class="tool-divider"></div>
                    
                    <div class="tool-section">
                        <button class="t-btn" onclick="addSticky()" title="Sticky Note"><i class="fas fa-sticky-note" style="color:#ffee58"></i></button>
                        <button class="t-btn" onclick="addNode()" title="Mind Map Node"><i class="fas fa-project-diagram" style="color:var(--neon)"></i></button>
                        <button class="t-btn" onclick="addShape('rect')" title="Square"><i class="far fa-square"></i></button>
                        <button class="t-btn" onclick="addShape('circle')" title="Circle"><i class="far fa-circle"></i></button>
                    </div>

                    <div class="tool-divider"></div>

                    <div class="tool-section">
                        <button class="t-btn" onclick="document.getElementById('img-upload').click()" title="Moodboard (Image)"><i class="fas fa-images"></i></button>
                        <input type="file" id="img-upload" hidden accept="image/*">
                        <button class="t-btn" onclick="openTemplates()" title="Templates Library"><i class="fas fa-layer-group"></i></button>
                        <button class="t-btn nebula-trigger" onclick="askNebulaBrain()" title="Nebula AI Brainstorm"><i class="fas fa-atom"></i></button>
                    </div>
                </aside>

                <!-- TOP NAV (Deck Header) -->
                <nav class="canvas-nav">
                    <div class="nav-left">
                        <button class="back-btn" onclick="location.href='/dashboard/workspace/'" title="Back to Workspace"><i class="fas fa-chevron-left"></i></button>
                        <div class="title-group">
                            <h1 id="sessionTitle" contenteditable="true">Untitled Deck</h1>
                            <span id="saveStatus">Core Linked</span>
                        </div>
                    </div>

                    <div class="nav-right">
                        <div class="zoom-indicator">
                            <button onclick="zoomCanvas(0.9)"><i class="fas fa-minus"></i></button>
                            <span id="zoomLevel">100%</span>
                            <button onclick="zoomCanvas(1.1)"><i class="fas fa-plus"></i></button>
                        </div>
                        <button class="btn-export" onclick="exportCanvas()"><i class="fas fa-file-export"></i> EXPORT</button>
                    </div>
                </nav>

                <!-- CANVAS AREA -->
                <main class="canvas-area" id="canvas-wrapper">
                    <canvas id="mainCanvas"></canvas>
                </main>

                <!-- CONTEXT MENU (Hidden by default) -->
                <div id="contextMenu" class="context-menu">
                    <div class="cm-item" onclick="convertToProject('planner')"><i class="fas fa-calendar-plus"></i> Send to Planner</div>
                    <div class="cm-item" onclick="convertToProject('keep')"><i class="fas fa-lightbulb"></i> Send to Keep</div>
                    <div class="cm-divider"></div>
                    <div class="cm-item delete" onclick="deleteSelected()"><i class="fas fa-trash"></i> Delete Object</div>
                </div>
            </div>

            <!-- MODAL: TEMPLATES LIBRARY -->
            <div id="tplModal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-layer-group"></i> Template Library</h3>
                        <button class="close-btn" onclick="closeTpl()">&times;</button>
                    </div>
                    <div class="tpl-grid">
                        <div class="tpl-card" onclick="loadTemplate('swot')">
                            <div class="tpl-icon"><i class="fas fa-th-large"></i></div>
                            <h4>SWOT Analysis</h4>
                            <p>Strengths, Weaknesses, Opportunities, Threats.</p>
                        </div>
                        <div class="tpl-card" onclick="loadTemplate('persona')">
                            <div class="tpl-icon"><i class="fas fa-user-tag"></i></div>
                            <h4>User Persona</h4>
                            <p>Identify your ideal customer profile.</p>
                        </div>
                        <div class="tpl-card" onclick="loadTemplate('funnel')">
                            <div class="tpl-icon"><i class="fas fa-filter"></i></div>
                            <h4>Marketing Funnel</h4>
                            <p>Awareness, Consideration, Conversion.</p>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- ENGINE SCRIPTS -->
    <script src="brainstorm-engine.js"></script>
</body>
</html>