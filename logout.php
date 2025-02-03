<?php
session_start();
require 'db.php'; // Include database connection

// Check if the user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Remove the "Remember Me" token from the database
    try {
        $stmt = $db->prepare("UPDATE users SET remember_token = NULL WHERE id = :id");
        $stmt->execute(['id' => $userId]);
    } catch (Exception $e) {
        error_log("Error clearing remember_token: " . $e->getMessage());
    }

    // Clear the "Remember Me" cookie
    setcookie('remember_me', '', time() - 3600, '/', '', true, true);
}

// Clear all session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect to the login page or homepage after logout
header("Location: login.php");
exit;
?>