<?php
/**
 * NHK Mobile - Secure Session Termination
 * 
 * Description: Clears all authentication tokens and session data 
 * to securely log out users and administrators. Redirects to homepage.
 * 
 * Author: NguyenHuuKhanh
 * Version: 2.1
 * Date: 2026-04-08
 */
require_once 'includes/auth_functions.php';

// Flush all session variables
session_unset();

// Obliterate the session data
session_destroy();

// Return to the previous page if possible, otherwise login page
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';

// If coming from an admin page or profile page, redirect to login page
if (strpos($referer, '/admin/') !== false || strpos($referer, 'profile.php') !== false || strpos($referer, 'logout.php') !== false) {
    header("Location: login.php");
} else {
    header("Location: " . $referer);
}
exit;
?>
