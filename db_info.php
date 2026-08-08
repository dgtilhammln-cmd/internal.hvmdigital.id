<?php
include 'includes/db_connect.php';
$q = mysqli_query($conn, "SHOW TABLES");
while($r = mysqli_fetch_array($q)) {
    $t = $r[0];
    echo "TABLE: $t\n";
    $qc = mysqli_query($conn, "SHOW COLUMNS FROM $t");
    while($rc = mysqli_fetch_assoc($qc)) {
        echo "  - ".$rc['Field']."\n";
    }
}
