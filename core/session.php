<?php
// session.php - Session management
session_start();

function requireLogin() {
    if(!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
?>