<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

$m = $_GET['m'];
$y = $_GET['y'];

// Fetch notes for markers
$agendas = [];
$q = mysqli_query($conn, "SELECT note_date, title FROM workspace_notes WHERE MONTH(note_date) = '$m' AND YEAR(note_date) = '$y'");
while($r = mysqli_fetch_assoc($q)) {
    $agendas[$r['note_date']][] = $r['title'];
}

$days_label = ['S','M','T','W','T','F','S'];
foreach($days_label as $h) echo "<div class='ch'>$h</div>";

$start_day = date('w', strtotime("$y-$m-01"));
for($i=0; $i<$start_day; $i++) echo "<div></div>";

$total_days = date('t', strtotime("$y-$m-01"));
for($d=1; $d<=$total_days; $d++) {
    $full_date = "$y-".str_pad($m, 2, "0", STR_PAD_LEFT)."-".str_pad($d, 2, "0", STR_PAD_LEFT);
    $is_today = ($full_date == date('Y-m-d')) ? 'is-today' : '';
    $has_ag = isset($agendas[$full_date]) ? 'has-agenda' : '';
    $info = isset($agendas[$full_date]) ? implode(" • ", $agendas[$full_date]) : "";
    
    echo "<div class='cd $is_today $has_ag' onclick='showAgendaDetail(\"$info\")'>$d</div>";
}