<?php
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
$data = json_decode(file_get_contents('php://input'), true);

$user = $_SESSION['admin'];
$title = mysqli_real_escape_string($conn, $data['title']);
$note = mysqli_real_escape_string($conn, $data['note']);
$project = mysqli_real_escape_string($conn, $data['project']);
$date = $data['date'];
$time = $data['time'];

mysqli_query($conn, "INSERT INTO workspace_notes (username, title, note, project_name, note_date, note_time) 
                    VALUES ('$user', '$title', '$note', '$project', '$date', '$time')");