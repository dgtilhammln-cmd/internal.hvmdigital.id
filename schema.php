<?php
require 'dashboard/includes/db.php';
$c = mysqli_fetch_all(mysqli_query($conn, 'DESCRIBE clients'), MYSQLI_ASSOC);
$p = mysqli_fetch_all(mysqli_query($conn, 'DESCRIBE prospects'), MYSQLI_ASSOC);
print_r(['clients' => $c, 'prospects' => $p]);
