<?php
// 1. CONFIG
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

// Security Check
if(!isset($_SESSION['admin'])){ 
    echo "<script>alert('Silahkan Login!'); window.location='/index.php';</script>"; exit; 
}

$current_role = $_SESSION['role']; 

// 2. AUTO-FIX DATABASE
$check = mysqli_query($conn, "SHOW TABLES LIKE 'teams'");
if(mysqli_num_rows($check) == 0) {
    $sql = "CREATE TABLE teams (
        team_id VARCHAR(10) PRIMARY KEY,
        name VARCHAR(100),
        position VARCHAR(100),
        jobdesk TEXT,
        education VARCHAR(150),
        photo VARCHAR(255),
        id_card VARCHAR(255),
        whatsapp VARCHAR(20),
        domicile VARCHAR(100),
        email VARCHAR(100) UNIQUE,
        password VARCHAR(255),
        role ENUM('super_admin','admin','staff') DEFAULT 'staff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    mysqli_query($conn, $sql);
}

// Auto-fix id_card column if not exists
$chk_idcard = mysqli_query($conn, "SHOW COLUMNS FROM teams LIKE 'id_card'");
if(mysqli_num_rows($chk_idcard) == 0) {
    mysqli_query($conn, "ALTER TABLE teams ADD COLUMN id_card VARCHAR(255) AFTER photo");
}

// 3. AUTO ID
$q_id = mysqli_query($conn, "SELECT MAX(team_id) as max_id FROM teams");
$r_id = mysqli_fetch_assoc($q_id);
$auto_id = str_pad((int)$r_id['max_id'] + 1, 4, "0", STR_PAD_LEFT);

// Fetch Team List for Broadcast Dropdown
$team_list = [];
$q_teams = mysqli_query($conn, "SELECT team_id, name FROM teams ORDER BY name ASC");
while($t = mysqli_fetch_assoc($q_teams)){
    $team_list[] = $t;
}

// 4. HANDLE UPLOAD
function handlePhotoUpload($file){
    if($file['error'] == 0){
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = time() . '_' . rand(100,999) . '.' . $ext;
        $dest = $_SERVER['DOCUMENT_ROOT'] . '/uploads/teams/';
        if(!file_exists($dest)) mkdir($dest, 0755, true);
        move_uploaded_file($file['tmp_name'], $dest . $name);
        return $name;
    }
    return null;
}

// 5. CRUD OPERATION
if(isset($_POST['save_team'])){
    try {
        if($current_role !== 'super_admin'){ throw new Exception("Access Denied: Only Super Admin can edit!"); }

        $mode = $_POST['mode'];
        $id = $_POST['team_id'];
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $pos = mysqli_real_escape_string($conn, $_POST['position']);
        $job = mysqli_real_escape_string($conn, $_POST['jobdesk']);
        $edu = mysqli_real_escape_string($conn, $_POST['education']);
        $wa = $_POST['whatsapp'];
        $dom = mysqli_real_escape_string($conn, $_POST['domicile']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $role = $_POST['role'];
        $photo = isset($_FILES['photo']) ? handlePhotoUpload($_FILES['photo']) : null;
        $id_card = isset($_FILES['id_card']) ? handlePhotoUpload($_FILES['id_card']) : null;

        if($mode == 'add'){
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "INSERT INTO teams (team_id, name, position, jobdesk, education, photo, id_card, whatsapp, domicile, email, password, role) 
                    VALUES ('$id', '$name', '$pos', '$job', '$edu', '$photo', '$id_card', '$wa', '$dom', '$email', '$pass', '$role')";
            $msg = "Team berhasil ditambahkan!";
        } else {
            $photo_q = $photo ? ", photo='$photo'" : "";
            $id_card_q = $id_card ? ", id_card='$id_card'" : "";
            $pass_q = !empty($_POST['password']) ? ", password='".password_hash($_POST['password'], PASSWORD_DEFAULT)."'" : "";
            $sql = "UPDATE teams SET name='$name', position='$pos', jobdesk='$job', education='$edu', whatsapp='$wa', domicile='$dom', email='$email', role='$role' $pass_q $photo_q $id_card_q WHERE team_id='$id'";
            $msg = "Data berhasil diupdate!";
        }

        mysqli_query($conn, $sql);
        $_SESSION['popup'] = ['type'=>'success', 'msg'=>$msg];
        header("Location: index.php"); exit;

    } catch (Exception $e) {
        $err = ($e->getCode() == 1062) ? "Email already registered!" : $e->getMessage();
        $_SESSION['popup'] = ['type'=>'error', 'msg'=>$err];
        header("Location: index.php"); exit;
    }
}

// DELETE
if(isset($_GET['del'])){
    if($current_role !== 'super_admin'){
        $_SESSION['popup'] = ['type'=>'error', 'msg'=>"Access Denied!"];
    } else {
        $id = $_GET['del'];
        mysqli_query($conn, "DELETE FROM teams WHERE team_id='$id'");
        $_SESSION['popup'] = ['type'=>'success', 'msg'=>"Data dihapus."];
    }
    header("Location: index.php"); exit;
}

// BROADCAST
if(isset($_POST['send_broadcast'])){
    if($current_role !== 'super_admin'){
        $_SESSION['popup'] = ['type'=>'error', 'msg'=>"Access Denied!"];
    } else {
        $target = $_POST['target'];
        $message = mysqli_real_escape_string($conn, $_POST['message']);
        $type = 'system';
        
        if ($target === 'all') {
            $final_msg = "[BROADCAST] " . $message;
        } else {
            $t_name = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM teams WHERE team_id='$target'"))['name'];
            $final_msg = "[TO: $t_name] " . $message;
        }
        
        $sql = "INSERT INTO notifications (type, message) VALUES ('$type', '$final_msg')";
        mysqli_query($conn, $sql);
        $_SESSION['popup'] = ['type'=>'success', 'msg'=>"Broadcast sent!"];
    }
    header("Location: index.php"); exit;
}

// 6. AJAX: TEAM MEETING HISTORY
if(isset($_GET['get_meetings']) && !empty($_GET['name'])) {
    header('Content-Type: application/json');
    $name_esc = mysqli_real_escape_string($conn, $_GET['name']);
    $meetings = [];
    $chk_ti = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'teams_involved'");
    if(mysqli_num_rows($chk_ti) > 0) {
        $chk_log = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'log_hasil'");
        if(mysqli_num_rows($chk_log) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `log_hasil` TEXT DEFAULT NULL");
        $q_m = mysqli_query($conn, "SELECT id, title, event_date, time_start, meeting_type, meeting_mode, location, target_name, target_type, teams_involved, log_hasil FROM events WHERE FIND_IN_SET('$name_esc', teams_involved) > 0 ORDER BY event_date DESC");
        while($r = mysqli_fetch_assoc($q_m)) $meetings[] = $r;
    }
    echo json_encode(['count' => count($meetings), 'meetings' => $meetings]);
    exit;
}

// 7. FETCH DATA
$search = $_GET['q'] ?? '';
$q = "SELECT * FROM teams WHERE name LIKE '%$search%' OR team_id LIKE '%$search%' ORDER BY team_id DESC";
$res = mysqli_query($conn, $q);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams - HVM</title>
    
    <!-- FAVICON -->
    <link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
    
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="dashboard-wrapper">
        <?php include '../sidebar.php'; ?>

        <main class="main-content">
            <div class="page-headline">
                <h1>Teams Squad</h1>
                <p>Manage your creative army & access control.</p>
            </div>

            <div class="action-bar">
                <form method="GET" style="flex:1;">
                    <input type="text" name="q" class="search-input" placeholder="Search ID / Name..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
                
                <!-- Buttons -->
                <button class="btn-secondary" onclick="checkAccess('broadcast', null)">
                    <i class="fas fa-bullhorn"></i> Broadcast
                </button>
                <button class="btn-grad" onclick="checkAccess('add', null)">
                    <i class="fas fa-plus"></i> New Team
                </button>
            </div>

            <div class="team-grid">
                <?php while($row = mysqli_fetch_assoc($res)): ?>
                <div class="glass-card">
                    <div class="avatar-box">
                        <?php if($row['photo']): ?>
                            <img src="/uploads/teams/<?php echo $row['photo']; ?>" alt="Foto">
                        <?php else: ?>
                            <i class="fas fa-user-astronaut"></i>
                        <?php endif; ?>
                    </div>
                    <h3 class="t-name"><?php echo htmlspecialchars($row['name']); ?></h3>
                    <div class="t-role"><?php echo htmlspecialchars($row['position']); ?></div>
                    
                    <div class="card-btns">
                        <button class="btn-act" onclick='viewDetail(<?php echo json_encode($row); ?>)' title="Detail Profil"><i class="fas fa-id-badge"></i></button>
                        <button class="btn-act" onclick='checkAccess("edit", <?php echo json_encode($row); ?>)' title="Edit Data"><i class="fas fa-edit"></i></button>
                        <?php if($row['id_card']): ?>
                        <button class="btn-act" onclick='viewIdCard("<?php echo $row['id_card']; ?>")' title="Lihat ID Card"><i class="fas fa-address-card"></i></button>
                        <?php endif; ?>
                        <button class="btn-act btn-del" onclick='checkAccess("delete", <?php echo json_encode($row); ?>)' title="Hapus"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </main>
    </div>

    <!-- 1. MODAL ADD/EDIT -->
    <div class="modal-overlay" id="teamModal">
        <div class="modal-content form-mode wide">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add Team</h2>
                <button class="close-btn" onclick="closeModal('teamModal')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="mode" id="formMode" value="add">
                
                <div class="form-grid">
                    <div class="form-group"><label>Team ID</label><input type="text" name="team_id" id="f_id" class="form-input" readonly style="color:#777;"></div>
                    <div class="form-group"><label>Full Name</label><input type="text" name="name" id="f_name" class="form-input" required></div>
                    
                    <div class="form-group"><label>Email (Login)</label><input type="email" name="email" id="f_email" class="form-input" required></div>
                    <div class="form-group"><label>Password <small id="passHint"></small></label><input type="password" name="password" id="f_pass" class="form-input"></div>
                    
                    <div class="form-group"><label>Posisi</label><input type="text" name="position" id="f_pos" class="form-input" required></div>
                    <div class="form-group"><label>Role Access</label>
                        <select name="role" id="f_role" class="form-input" style="background:#1a1a1a;">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>

                    <div class="form-group"><label>Pendidikan</label><input type="text" name="education" id="f_edu" class="form-input"></div>
                    <div class="form-group"><label>WhatsApp</label><input type="text" name="whatsapp" id="f_wa" class="form-input" required></div>
                    <div class="form-group"><label>Domisili</label><input type="text" name="domicile" id="f_dom" class="form-input"></div>
                    <div class="form-group"><label>Foto Profil</label><input type="file" name="photo" class="form-input" accept="image/*"></div>
                    <div class="form-group"><label>ID Card (Rasio 2233x3546)</label><input type="file" name="id_card" class="form-input" accept="image/*"></div>
                    
                    <div class="form-group full"><label>Job Description</label><textarea name="jobdesk" id="f_job" class="form-input" rows="4"></textarea></div>
                </div>
                
                <button type="submit" name="save_team" class="btn-grad" style="width:100%; justify-content:center; margin-top:20px;">Save Data</button>
            </form>
        </div>
    </div>

    <!-- 2. MODAL VIEW DETAIL (LANDSCAPE) -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-content wide">
            <div class="modal-header">
                <h2 class="modal-title">Profile Detail</h2>
                <button class="close-btn" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div id="viewContent"></div>
        </div>
    </div>

    <!-- 3. MODAL BROADCAST -->
    <div class="modal-overlay" id="broadcastModal">
        <div class="modal-content medium">
            <div class="modal-header">
                <h2 class="modal-title">Broadcast Message</h2>
                <button class="close-btn" onclick="closeModal('broadcastModal')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target" class="form-input" style="background:#1a1a1a;">
                        <option value="all">All Teams</option>
                        <?php foreach($team_list as $tl): ?>
                            <option value="<?php echo $tl['team_id']; ?>"><?php echo $tl['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" class="form-input" rows="5" required placeholder="Tulis pengumuman di sini..."></textarea>
                </div>
                <button type="submit" name="send_broadcast" class="btn-grad" style="width:100%; justify-content:center;">Send Broadcast</button>
            </form>
        </div>
    </div>

    <!-- 4. MODAL MEETING DETAIL -->
    <div class="modal-overlay" id="meetingDetailModal">
        <div class="modal-content medium" style="background:#0f0f12; border:1px solid rgba(161,255,90,0.1); border-radius:16px;">
            <div class="modal-header">
                <h2 class="modal-title" style="color:#a1ff5a;"><i class="fas fa-calendar-check" style="margin-right:8px;"></i>Detail Meeting</h2>
                <button class="close-btn" onclick="closeModal('meetingDetailModal')">&times;</button>
            </div>
            <div id="meetingDetailContent" style="padding-top:10px;"></div>
        </div>
    </div>

    <!-- 5. MODAL ID CARD -->
    <div class="modal-overlay" id="idCardModal">
        <div class="modal-content medium" style="background:transparent; border:none; box-shadow:none; padding:0; display:flex; justify-content:center; align-items:center;">
            <div style="position:relative; max-width:90vw; max-height:90vh;">
                <button class="close-btn" onclick="closeModal('idCardModal')" style="position:absolute; top:-40px; right:0; color:#fff; background:rgba(255,255,255,0.1); width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; z-index:10;"><i class="fas fa-times"></i></button>
                <img id="idCardImage" src="" style="max-width:100%; max-height:90vh; border-radius:20px; object-fit:contain; box-shadow:0 20px 60px rgba(0,0,0,0.9);">
            </div>
        </div>
    </div>

    <!-- POPUP -->
    <div id="popup" class="popup">
        <i class="fas fa-check-circle"></i>
        <span id="popupMsg">Success</span>
    </div>

    <script>
        const userRole = "<?php echo $current_role; ?>";

        function checkAccess(action, data) {
            if (userRole !== 'super_admin') {
                showPopup('error', 'Restricted: Only Super Admin can perform this action!');
            } else {
                if (action === 'add') openAdd();
                else if (action === 'edit') editData(data);
                else if (action === 'delete') {
                    if(confirm('Yakin ingin menghapus data ini?')) window.location.href = "?del=" + data.team_id;
                }
                else if (action === 'broadcast') openBroadcast();
            }
        }

        function openAdd() {
            document.getElementById('teamModal').classList.add('active');
            document.getElementById('modalTitle').innerText = "Add New Team";
            document.getElementById('formMode').value = "add";
            document.getElementById('f_id').value = "<?php echo $auto_id; ?>";
            ['f_name','f_email','f_pos','f_edu','f_wa','f_dom','f_job'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('f_pass').required = true;
        }

        function editData(data) {
            document.getElementById('teamModal').classList.add('active');
            document.getElementById('modalTitle').innerText = "Edit Team";
            document.getElementById('formMode').value = "edit";
            
            document.getElementById('f_id').value = data.team_id;
            document.getElementById('f_name').value = data.name;
            document.getElementById('f_email').value = data.email;
            document.getElementById('f_pos').value = data.position;
            document.getElementById('f_role').value = data.role;
            document.getElementById('f_edu').value = data.education;
            document.getElementById('f_wa').value = data.whatsapp;
            document.getElementById('f_dom').value = data.domicile;
            document.getElementById('f_job').value = data.jobdesk;
            
            document.getElementById('f_pass').required = false;
            document.getElementById('passHint').innerText = "(Leave blank if not changing)";
        }

        function openBroadcast() { document.getElementById('broadcastModal').classList.add('active'); }

        let teamMeetingsData = [];

        function switchTeamTab(tabName) {
            document.querySelectorAll('.team-tab-btn').forEach(b => b.style.color = '#888');
            document.querySelectorAll('.team-tab-btn').forEach(b => b.style.borderBottom = '2px solid transparent');
            const activeBtn = document.getElementById('tab-btn-' + tabName);
            if(activeBtn) {
                activeBtn.style.color = '#a1ff5a';
                activeBtn.style.borderBottom = '2px solid #a1ff5a';
            }
            
            document.getElementById('t-tab-profil').style.display = 'none';
            document.getElementById('t-tab-meeting').style.display = 'none';
            document.getElementById('t-tab-' + tabName).style.display = 'block';
        }

        function viewMeetingDetail(idx) {
            const m = teamMeetingsData[idx];
            if(!m) return;
            const d = new Date(m.event_date).toLocaleDateString('id-ID',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
            
            const html = `
                <div style="margin-bottom:15px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:12px;">
                    <div style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:4px;">JUDUL MEETING</div>
                    <div style="font-size:1.1rem;color:#fff;font-weight:700;">${m.title}</div>
                </div>
                <div style="margin-bottom:15px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:12px;">
                    <div style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:4px;">WAKTU</div>
                    <div style="font-size:0.9rem;color:#ccc;">${d} &bull; ${m.time_start||'Seharian'}</div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;border-bottom:1px solid rgba(255,255,255,0.05);padding-bottom:12px;">
                    <div>
                        <div style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:4px;">JENIS & MODE</div>
                        <div style="font-size:0.85rem;color:#fff;">${m.meeting_type||'-'} &bull; <span style="color:#a1ff5a;">${m.meeting_mode||'-'}</span></div>
                    </div>
                    <div>
                        <div style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:4px;">PERUSAHAAN</div>
                        <div style="font-size:0.85rem;color:#fff;">${m.target_type||'-'}: ${m.target_name||'-'}</div>
                    </div>
                </div>
                <div style="margin-bottom:15px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:12px;">
                    <div style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:4px;">LOKASI / LINK</div>
                    <div style="font-size:0.85rem;color:#4efdc4;"><a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(m.location||'')}" target="_blank" style="color:#4efdc4;text-decoration:none;">${m.location||'-'}</a></div>
                </div>
                <div style="margin-bottom:15px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:12px;">
                    <div style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:4px;">TIM HADIR</div>
                    <div style="font-size:0.85rem;color:#ccc;">${m.teams_involved ? m.teams_involved.split(',').map(t=>`<span style="display:inline-block;background:rgba(161,255,90,0.1);color:#a1ff5a;padding:2px 8px;border-radius:12px;margin:2px 4px 2px 0;font-size:0.75rem;">${t.trim()}</span>`).join('') : '-'}</div>
                </div>
                <div>
                    <div style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:4px;">LOG HASIL</div>
                    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.05);padding:12px;border-radius:10px;color:#aaa;font-size:0.85rem;white-space:pre-wrap;">${m.log_hasil||'Belum ada log.'}</div>
                </div>
            `;
            document.getElementById('meetingDetailContent').innerHTML = html;
            document.getElementById('meetingDetailModal').classList.add('active');
        }

        function viewDetail(data) {
            document.getElementById('viewModal').classList.add('active');
            const img = data.photo ? `/uploads/teams/${data.photo}` : null;
            const avatarHtml = img ? `<img src="${img}">` : '<i class="fas fa-user-astronaut"></i>';
            
            const html = `
                <div class="profile-modal-container">
                    <div class="profile-left">
                        <div class="detail-avatar">${avatarHtml}</div>
                        <div class="detail-name">${data.name}</div>
                        <div class="detail-role" style="color:#a1ff5a; font-weight:800; font-size:0.8rem;">${data.position}</div>
                        <div class="info-grid" style="grid-template-columns:1fr; gap:10px; margin-top:25px;">
                            <div class="info-item"><span class="info-label">ID Team</span> <div class="info-value">${data.team_id}</div></div>
                            <div class="info-item"><span class="info-label">Role Access</span> <div class="info-value" style="color:#ff9f43;">${data.role.toUpperCase()}</div></div>
                        </div>
                    </div>
                    <div class="profile-right" style="display:flex; flex-direction:column;">
                        <!-- TABS HEADER -->
                        <div style="display:flex; gap:20px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:20px; padding-bottom:5px;">
                            <div id="tab-btn-profil" class="team-tab-btn" onclick="switchTeamTab('profil')" style="cursor:pointer; color:#a1ff5a; font-weight:700; font-size:0.9rem; padding:5px 0; border-bottom:2px solid #a1ff5a; transition:0.3s;">Profil Lengkap</div>
                            <div id="tab-btn-meeting" class="team-tab-btn" onclick="switchTeamTab('meeting')" style="cursor:pointer; color:#888; font-weight:700; font-size:0.9rem; padding:5px 0; border-bottom:2px solid transparent; transition:0.3s;">Riwayat Meeting</div>
                        </div>

                        <!-- TAB: PROFIL -->
                        <div id="t-tab-profil" style="display:block; flex:1; overflow-y:auto; padding-right:10px;">
                            <div class="info-grid" style="margin-bottom:20px;">
                                <div class="info-item" style="display:flex;align-items:center;gap:15px;">
                                    <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;color:#fff;"><i class="fas fa-envelope"></i></div>
                                    <div><span class="info-label" style="margin-bottom:2px;">Email</span><div class="info-value" style="font-size:0.9rem;">${data.email}</div></div>
                                </div>
                                <div class="info-item" style="display:flex;align-items:center;gap:15px;">
                                    <div style="width:40px;height:40px;border-radius:10px;background:rgba(37,211,102,0.1);display:flex;align-items:center;justify-content:center;color:#25D366;"><i class="fab fa-whatsapp"></i></div>
                                    <div><span class="info-label" style="margin-bottom:2px;">WhatsApp</span><div class="info-value" style="font-size:0.9rem;">${data.whatsapp}</div></div>
                                </div>
                                <div class="info-item" style="display:flex;align-items:center;gap:15px;">
                                    <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,159,67,0.1);display:flex;align-items:center;justify-content:center;color:#ff9f43;"><i class="fas fa-graduation-cap"></i></div>
                                    <div><span class="info-label" style="margin-bottom:2px;">Pendidikan</span><div class="info-value" style="font-size:0.9rem;">${data.education||'-'}</div></div>
                                </div>
                                <div class="info-item" style="display:flex;align-items:center;gap:15px;">
                                    <div style="width:40px;height:40px;border-radius:10px;background:rgba(78,253,196,0.1);display:flex;align-items:center;justify-content:center;color:#4efdc4;"><i class="fas fa-map-marker-alt"></i></div>
                                    <div><span class="info-label" style="margin-bottom:2px;">Domisili</span><div class="info-value" style="font-size:0.9rem;">${data.domicile||'-'}</div></div>
                                </div>
                            </div>
                            <div class="job-section">
                                <div class="job-title"><i class="fas fa-briefcase" style="margin-right:8px;"></i>Job Description / Tanggung Jawab</div>
                                <div class="job-content">${data.jobdesk || '<span style="color:#666;font-style:italic;">Belum ada data jobdesk...</span>'}</div>
                            </div>
                        </div>

                        <!-- TAB: MEETING -->
                        <div id="t-tab-meeting" style="display:none; flex:1; overflow-y:auto; padding-right:10px;">
                            <div id="teamMeetingStats" style="font-size:0.82rem;color:#888;margin-bottom:12px;background:rgba(255,255,255,0.02);padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.05);">Memuat data meeting...</div>
                            <div id="teamMeetingList" style="display:flex; flex-direction:column; gap:10px;"></div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('viewContent').innerHTML = html;
            
            // Load meeting history via AJAX
            fetch(`?get_meetings=1&name=${encodeURIComponent(data.name)}`)
                .then(r => r.json())
                .then(res => {
                    const stats = document.getElementById('teamMeetingStats');
                    const list = document.getElementById('teamMeetingList');
                    if(!stats || !list) return;
                    teamMeetingsData = res.meetings || [];
                    
                    if(res.count > 0) {
                        document.getElementById('tab-btn-meeting').innerText = 'Riwayat Meeting (' + res.count + ')';
                        stats.innerHTML = `<i class="fas fa-chart-line" style="margin-right:6px; color:#a1ff5a;"></i>Total: <strong style="color:#fff;">${res.count} meeting</strong> dihadiri`;
                        
                        let listHtml = '';
                        res.meetings.forEach((m, idx) => {
                            const d = new Date(m.event_date).toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'});
                            const pName = (m.target_name && m.target_name.length > 20) ? m.target_name.substring(0,20)+'...' : (m.target_name||'Internal');
                            const modeCol = (m.meeting_mode==='Offline') ? 'var(--neon-2)' : 'var(--neon-main)';
                            listHtml += `
                                <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05); padding:12px; border-radius:12px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; cursor:pointer; transition:0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.06)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'" onclick="viewMeetingDetail(${idx})">
                                    <div>
                                        <div style="font-weight:700; font-size:0.9rem; margin-bottom:4px; color:#fff;">${m.title}</div>
                                        <div style="font-size:0.75rem; color:#888;">${d} &bull; ${m.time_start||'-'} &bull; <span style="color:${modeCol};">${m.meeting_mode||'-'}</span></div>
                                    </div>
                                    <div style="font-size:0.75rem; background:rgba(255,255,255,0.05); padding:4px 10px; border-radius:12px; color:#ccc;">${pName}</div>
                                </div>
                            `;
                        });
                        list.innerHTML = listHtml;
                    } else {
                        stats.innerHTML = '';
                        list.innerHTML = '<div style="color:#666; font-style:italic; font-size:0.85rem; text-align:center; padding:20px;">Belum ada riwayat meeting.</div>';
                    }
                }).catch(() => {
                    const stats = document.getElementById('teamMeetingStats');
                    if(stats) stats.innerHTML = '<span style="color:#ff5a5a;">Tidak dapat memuat data meeting.</span>';
                });
        }
        
        function viewIdCard(fileName) {
            document.getElementById('idCardImage').src = `/uploads/teams/${fileName}`;
            document.getElementById('idCardModal').classList.add('active');
        }

        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        function showPopup(type, msg) {
            const p = document.getElementById('popup');
            const icon = p.querySelector('i');
            document.getElementById('popupMsg').innerText = msg;
            
            p.className = 'popup ' + type; 
            if(type === 'error') icon.className = 'fas fa-exclamation-triangle';
            else icon.className = 'fas fa-check-circle';
            
            p.classList.add('show');
            setTimeout(() => { p.classList.remove('show'); }, 3000);
        }

        <?php if(isset($_SESSION['popup'])): ?>
            showPopup("<?php echo $_SESSION['popup']['type']; ?>", "<?php echo $_SESSION['popup']['msg']; ?>");
            <?php unset($_SESSION['popup']); ?>
        <?php endif; ?>

        window.onclick = function(e) { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('active'); }
    </script>
</body>
</html>