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

// 1. SAVE NOTE
if(isset($_POST['action']) && $_POST['action'] == 'save_note') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $color = $_POST['color'] ?? 'default';
    $is_pinned = $_POST['is_pinned'] ?? 0;
    $reminder = !empty($_POST['reminder']) ? $_POST['reminder'] : 'NULL';
    
    if(!empty($title) || !empty($content)) {
        if($id > 0) {
            $rem_sql = ($reminder == 'NULL') ? "reminder_date = NULL" : "reminder_date = '$reminder'";
            $sql = "UPDATE keep_notes SET title='$title', content='$content', color='$color', is_pinned='$is_pinned', $rem_sql WHERE id=$id AND user_name='$user'";
        } else {
            $val_rem = ($reminder == 'NULL') ? "NULL" : "'$reminder'";
            $sql = "INSERT INTO keep_notes (user_name, title, content, color, is_pinned, reminder_date) VALUES ('$user', '$title', '$content', '$color', '$is_pinned', $val_rem)";
        }
        mysqli_query($conn, $sql);
        echo json_encode(['status' => 'success']);
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