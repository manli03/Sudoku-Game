<?php

// fetch game owner by game id
session_start();
header('Content-Type: application/json');

require_once '../../db.php'; // Corrected path

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;

if ($game_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid game ID.']);
    exit();
}

try {
    $stmt = $db->prepare("SELECT owner_id FROM multiplayer_games WHERE id = :game_id");
    $stmt->execute(['game_id' => $game_id]);
    $owner_id = $stmt->fetchColumn();

    if ($owner_id) {
        echo json_encode(['success' => true, 'owner_id' => $owner_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Game not found.']);
    }
} catch (Exception $e) {
    error_log("Error in fetch_game_owner.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}