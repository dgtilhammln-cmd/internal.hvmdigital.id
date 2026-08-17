<?php
session_start();
header('Content-Type: application/json');
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if(!isset($_SESSION['admin'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }
$user = $_SESSION['admin'];

// AUTO FIX DB
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS keep_notes (id INT AUTO_INCREMENT PRIMARY KEY, user_name VARCHAR(100), title VARCHAR(255), content TEXT, color VARCHAR(20) DEFAULT 'default', is_pinned TINYINT(1) DEFAULT 0, is_trashed TINYINT(1) DEFAULT 0, reminder_date DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$check_trash_col = mysqli_query($conn, "SHOW COLUMNS FROM keep_notes LIKE 'trashed_at'");
if(mysqli_num_rows($check_trash_col) == 0) {
    mysqli_query($conn, "ALTER TABLE keep_notes ADD COLUMN trashed_at TIMESTAMP NULL");
}
$check_img_col = mysqli_query($conn, "SHOW COLUMNS FROM keep_notes LIKE 'image_path'");
if(mysqli_num_rows($check_img_col) == 0) {
    mysqli_query($conn, "ALTER TABLE keep_notes ADD COLUMN image_path VARCHAR(500) NULL");
}

// 1. SAVE NOTE (with optional image upload)
if(isset($_POST['action']) && $_POST['action'] == 'save_note') {
    $id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title   = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
    $content = mysqli_real_escape_string($conn, $_POST['content'] ?? '');
    $color   = mysqli_real_escape_string($conn, $_POST['color'] ?? 'default');
    $is_pinned = (int)($_POST['is_pinned'] ?? 0);
    $reminder  = !empty($_POST['reminder']) ? mysqli_real_escape_string($conn, $_POST['reminder']) : 'NULL';

    // Handle image upload
    $extra_set = '';
    if(isset($_FILES['note_image']) && $_FILES['note_image']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['note_image']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/notes/';
            if(!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
            $fname = 'note_' . time() . '_' . rand(100,999) . '.' . $ext;
            if(move_uploaded_file($_FILES['note_image']['tmp_name'], $upload_dir . $fname)) {
                $safe_path = mysqli_real_escape_string($conn, '/uploads/notes/'.$fname);
                $extra_set = ", image_path='$safe_path'";
            }
        }
    } elseif(!empty($_POST['remove_image'])) {
        $extra_set = ", image_path=NULL";
    }

    if(!empty($title) || !empty($content)) {
        if($id > 0) {
            $rem_sql = ($reminder == 'NULL') ? "reminder_date = NULL" : "reminder_date = '$reminder'";
            $sql = "UPDATE keep_notes SET title='$title', content='$content', color='$color', is_pinned='$is_pinned', $rem_sql $extra_set WHERE id=$id AND user_name='$user'";
        } else {
            $val_rem = ($reminder == 'NULL') ? "NULL" : "'$reminder'";
            $img_col = ''; $img_val = '';
            if(strpos($extra_set, 'image_path') !== false) {
                preg_match("/image_path='([^']+)'/", $extra_set, $m);
                if($m) { $img_col = ', image_path'; $img_val = ', \'' . $m[1] . '\''; }
            }
            $sql = "INSERT INTO keep_notes (user_name, title, content, color, is_pinned, reminder_date$img_col) VALUES ('$user', '$title', '$content', '$color', '$is_pinned', $val_rem$img_val)";
        }
        mysqli_query($conn, $sql);
        $new_id = ($id > 0) ? $id : mysqli_insert_id($conn);
        echo json_encode(['status' => 'success', 'id' => $new_id]);
    } else {
        echo json_encode(['status' => 'empty']);
    }
    exit;
}

// 2. GET NOTES
if(isset($_GET['action']) && $_GET['action'] == 'get_notes') {
    // Auto-delete notes in trash older than 30 days
    mysqli_query($conn, "DELETE FROM keep_notes WHERE is_trashed = 1 AND trashed_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND user_name='$user'");

    $view = $_GET['view'] ?? 'notes';
    $where = "user_name='$user'";
    if($view == 'trash') $where .= " AND is_trashed = 1";
    elseif($view == 'reminders') $where .= " AND is_trashed = 0 AND reminder_date IS NOT NULL";
    else $where .= " AND is_trashed = 0";
    
    $q = mysqli_query($conn, "SELECT *, DATEDIFF(DATE_ADD(trashed_at, INTERVAL 30 DAY), NOW()) as days_left FROM keep_notes WHERE $where ORDER BY is_pinned DESC, id DESC");
    $notes = [];
    while($row = mysqli_fetch_assoc($q)) $notes[] = $row;
    echo json_encode($notes);
    exit;
}

// 3. TRASH / RESTORE / DELETE
if(isset($_POST['action']) && ($_POST['action'] == 'trash_note' || $_POST['action'] == 'restore_note')) {
    $id = (int)$_POST['id'];
    if($_POST['action'] == 'trash_note') {
        mysqli_query($conn, "UPDATE keep_notes SET is_trashed=1, trashed_at=CURRENT_TIMESTAMP WHERE id=$id AND user_name='$user'");
    } else {
        mysqli_query($conn, "UPDATE keep_notes SET is_trashed=0, trashed_at=NULL WHERE id=$id AND user_name='$user'");
    }
    echo json_encode(['status' => 'success']); exit;
}
if(isset($_POST['action']) && $_POST['action'] == 'delete_forever') {
    $id = (int)$_POST['id'];
    mysqli_query($conn, "DELETE FROM keep_notes WHERE id=$id AND user_name='$user'");
    echo json_encode(['status' => 'success']); exit;
}

// 4. GET EVENTS
if(isset($_GET['action']) && $_GET['action'] == 'get_events') {
    $start = mysqli_real_escape_string($conn, $_GET['start']);
    $end = mysqli_real_escape_string($conn, $_GET['end']);
    $q = mysqli_query($conn, "SELECT * FROM events WHERE event_date BETWEEN '$start' AND '$end'");
    $events = [];
    while($row = mysqli_fetch_assoc($q)) $events[] = $row;
    echo json_encode($events); exit;
}
?>