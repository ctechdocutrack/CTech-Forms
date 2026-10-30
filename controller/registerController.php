<?php
include '../config/dbconn.php';

if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = "Admin";

    $sql = "INSERT INTO users (username, password, role) VALUES ('$username', '$password', '$role')";
    $sql_run = mysqli_query($conn, $sql);

    if ($sql_run) {
        $success = "Registration successful. You can now log in.";
        header("Location: ../index.php?success=" . urlencode($success));
        exit();
    } else {
        $error = "Error: " . mysqli_error($conn);
        header("Location: ../register.php?error=" . urlencode($error));
        exit();
    }
}