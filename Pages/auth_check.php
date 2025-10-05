<?php
/**
 * Authentication Middleware
 * Include this file at the top of any page that requires authentication
 * 
 * Usage: require_once __DIR__ . '/auth_check.php';
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// If not logged in, redirect to signup page
if (!$is_logged_in) {
    // Store the attempted URL for redirect after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Determine the correct path to SignUp.php
    $current_dir = basename(dirname(__FILE__));
    
    if ($current_dir === 'Pages') {
        // We're already in Pages directory
        header('Location: SignUp.php');
    } else {
        // We're in root or another directory
        header('Location: Pages/SignUp.php');
    }
    exit;
}

// User is authenticated, continue with the page
?>