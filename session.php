
<?php
//session.php - Session management
// Start the session if it hasn't been started yet

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
?>