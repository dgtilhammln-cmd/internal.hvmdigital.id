<?php
require 'includes/db_connect.php';
$q = mysqli_query($conn, 'SHOW CREATE TABLE clients');
$r = mysqli_fetch_row($q);
echo "CLIENTS:\n" . $r[1] . "\n\n";

$q2 = mysqli_query($conn, 'SHOW CREATE TABLE prospects');
$r2 = mysqli_fetch_row($q2);
echo "PROSPECTS:\n" . $r2[1] . "\n\n";
