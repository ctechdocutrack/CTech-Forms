<?php
session_start();
include '../config/dbconn.php';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $sql_run = mysqli_query($conn, $sql);

    if (mysqli_num_rows($sql_run) > 0) {
        $row = mysqli_fetch_assoc($sql_run);
        session_start();

        $_SESSION['authUser'] = [
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'role' => $row['role']
        ];

        header("Location: ../views/admin/my_forms.php");
        exit();
    } else {
        $error = "Invalid username or password.";
        header("Location: ../index.php?error=" . urlencode($error));
        exit();
    }
}