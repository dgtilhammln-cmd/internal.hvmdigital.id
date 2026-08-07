<?php
include('includes/db_connect.php');
$r = mysqli_query($conn, 'DESCRIBE clients');
while($row = mysqli_fetch_assoc($r)) echo $row['Field'].' - '.$row['Type']."\n";
echo "\n--- payments ---\n";
$r = mysqli_query($conn, 'DESCRIBE payments');
while($row = mysqli_fetch_assoc($r)) echo $row['Field'].' - '.$row['Type']."\n";
