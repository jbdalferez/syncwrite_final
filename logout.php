<?php
// logout.php - Logout functionality
require_once 'session.php';
session_destroy();
header('Location: login.php');
exit();
?>