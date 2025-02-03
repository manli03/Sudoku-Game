<?php
// /multiplayer/api/fetch_game_status.php
session_start();
header('Content-Type: application/json');
require '../../db.php'; // Corrected path

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
    $stmt = $db->prepare("SELECT status FROM multiplayer_games WHERE id = :game_id");
    $stmt->execute(['game_id' => $game_id]);
    $status = $stmt->fetchColumn();

    if (!$status) {
        echo json_encode(['success' => false, 'error' => 'Game not found.']);
        exit();
    }

    echo json_encode(['success' => true, 'status' => $status]);
} catch (Exception $e) {
    error_log("Error in fetch_game_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch game status.']);
}
?>