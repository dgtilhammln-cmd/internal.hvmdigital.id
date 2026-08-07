<?php
session_start();
header('Content-Type: application/json');
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if(!isset($_SESSION['admin'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }
$user = $_SESSION['admin'];

// AUTO FIX DB
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS keep_notes (id INT AUTO_INCREMENT PRIMARY KEY, user_name VARCHAR(100), title VARCHAR(255), content TEXT, color VARCHAR(20) DEFAULT 'default', is_pinned TINYINT(1) DEFAULT 0, is_trashed TINYINT(1) DEFAULT 0, reminder_date DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

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
    $view = $_GET['view'] ?? 'notes';
    $where = "user_name='$user'";
    if($view == 'trash') $where .= " AND is_trashed = 1";
    elseif($view == 'reminders') $where .= " AND is_trashed = 0 AND reminder_date IS NOT NULL";
    else $where .= " AND is_trashed = 0";
    
    $q = mysqli_query($conn, "SELECT * FROM keep_notes WHERE $where ORDER BY is_pinned DESC, id DESC");
    $notes = [];
    while($row = mysqli_fetch_assoc($q)) $notes[] = $row;
    echo json_encode($notes);
    exit;
}

// 3. TRASH / RESTORE / DELETE
if(isset($_POST['action']) && ($_POST['action'] == 'trash_note' || $_POST['action'] == 'restore_note')) {
    $id = (int)$_POST['id'];
    $val = ($_POST['action'] == 'trash_note') ? 1 : 0;
    mysqli_query($conn, "UPDATE keep_notes SET is_trashed=$val WHERE id=$id AND user_name='$user'");
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