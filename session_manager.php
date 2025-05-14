<?php
// session_manager.php

// Prevent direct access
if (!defined('APP_START')) {
    exit('Direct access denied.');
}

// Set session cookie parameters before starting the session
session_set_cookie_params([
    'lifetime' => 0, // Session lasts until browser is closed
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Secure for HTTPS only
    'httponly' => true, // Prevent JavaScript access
    'samesite' => 'Strict' // Mitigate CSRF
]);

// Start the session
session_start();

// Regenerate session ID for security
session_regenerate_id(true);

// Initialize CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to validate CSRF token
function validateCsrfToken($token) {
    if (!isset($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new Exception("Jeton CSRF invalide.");
    }
    return true;
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to require login, redirect if not logged in
function requireLogin($redirect = 'auth.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit;
    }
}

// Function to get current user ID
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

// Function to destroy session (logout)
function destroySession() {
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}
?>