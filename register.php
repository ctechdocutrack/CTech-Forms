<?php 
include 'config/dbconn.php';

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="node_modules/bootstrap-icons/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">

<nav class="navbar shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-3" href="/CTech%20Forms/index.php">
            <img src="assets/images/CTech Logo.png" alt="" style="width: 50px; height: 50px;">
            CTech Forms
        </a>
    </div>
</nav>

<div class="container py-4 py-md-5">
    <div class="row justify-content-center align-items-center">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-start mb-3">
                        <h2 class="fw-bold mb-3">Register</h2>
                        <p class="text-secondary mb-0">
                            Create your account to access the admin panel.
                        </p>
                    </div>
                    <?php
                    if ($success){
                        echo '
                            <div class="alert alert-success" role="alert">' . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . '</div>
                        ';
                    } 
                    if ($error){
                        echo '
                            <div class="alert alert-danger" role="alert">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>
                        ';
                    } 
                    ?>
                    <form method="POST" action="controller/registerController.php">
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">Username</label>
                            <input type="text" class="form-control" name="username" autocomplete="username" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" autocomplete="current-password" required>
                            </div>
                        </div>
                        <button type="submit" name="register" class="btn btn-primary w-100 py-2">
                            Register
                        </button>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>