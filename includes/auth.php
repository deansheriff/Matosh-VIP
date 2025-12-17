<?php
// Start the session if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in.
// If 'user_id' is not set in the session, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    // Store the requested URL in the session to redirect after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    // If it's a POST request, store the POST data as well
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['redirect_post_data'] = $_POST;
    }
    
    header('Location: /pages/login.php');
    exit;
}

// Optional: Role-based access control
// You can expand this function to check for specific roles.
function has_role($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function has_any_role(array $roles) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    return in_array($_SESSION['user_role'], $roles, true);
}

// Example usage in a page:
// if (!has_role('Admin')) {
//     die('Access Denied: You do not have permission to view this page.');
// }
