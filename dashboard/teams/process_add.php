<?php
include '../../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $division = $_POST['division'];
    $jobdesk = $_POST['jobdesk'];
    
    // Handle File Upload
    $photo_name = $_FILES['photo']['name'];
    $target = "../../uploads/" . basename($photo_name);
    move_uploaded_file($_FILES['photo']['tmp_name'], $target);

    $query = "INSERT INTO teams (name, division, photo, jobdesk) VALUES ('$name', '$division', '$photo_name', '$jobdesk')";
    
    if(mysqli_query($conn, $query)) {
        header("Location: index.php?status=success");
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>