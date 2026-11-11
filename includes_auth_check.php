<?php
session_start();


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: /project_root/login.php");
    exit();
}


function checkRole($requiredRole) {
    if ($_SESSION['role'] !== $requiredRole) {
        header("Location: /project_root/index.php?error=unauthorized");
        exit();
    }
}
?>