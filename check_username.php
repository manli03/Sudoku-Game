<?php
session_start();
require 'db.php';

if (isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $user_id = $_SESSION['user_id'];

    // Check if the username exists in the database for a different user
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1");
    $stmt->execute([
        'username' => $username,
        'id' => $user_id
    ]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_user) {
        echo json_encode(['exists' => true, 'message' => 'Username is already taken.']);
    } else {
        echo json_encode(['exists' => false, 'message' => 'Username is available.']);
    }
} else {
    echo json_encode(['error' => 'Invalid request.']);
}
?>