<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "ctech_forms";

$conn = mysqli_connect($host, $username, $password, $dbname);
if(!$conn){
    exit("Database Connection Failed");
}
?>