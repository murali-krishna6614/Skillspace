<?php
session_start(); // Enable session handling
if(!isset($_SESSION['user_id']) && isset($_COOKIE['user_id'])) {
    $_SESSION['user_id']   = $_COOKIE['user_id'];
    $_SESSION['user_name'] = $_COOKIE['user_name'];
    $_SESSION['role']      = $_COOKIE['role'];
}
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<?php
session_start();

// Log the logout action (optional, for debugging)
if (isset($_SESSION['user_id'])) {
    error_log("logout.php: User " . $_SESSION['user_id'] . " logged out at " . date('Y-m-d H:i:s'));
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?>