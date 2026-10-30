
<?php
session_start();

// Clear all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../index.php?success=" . urlencode("You have logged out successfully."));
exit();
?>
