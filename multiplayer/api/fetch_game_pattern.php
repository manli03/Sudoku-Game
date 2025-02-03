<?php
session_start();
header('Content-Type: application/json');
require_once '../../db.php';

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
    // Fetch the initial pattern from multiplayer_games
    $stmt = $db->prepare("SELECT pattern, start_time FROM multiplayer_games WHERE id = :game_id");
    $stmt->execute(['game_id' => $game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Game not found.']);
        exit();
    }

    $pattern = $game['pattern'];
    $start_time = $game['start_time'];

    // Fetch the player's progress
    $stmt = $db->prepare("SELECT progress FROM multiplayer_players WHERE game_id = :game_id AND user_id = :user_id");
    $stmt->execute(['game_id' => $game_id, 'user_id' => $user_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    $progress = $player['progress'] ?? str_repeat('.', 81); // Default to empty progress if none

    echo json_encode([
        'success' => true,
        'pattern' => $pattern,
        'progress' => $progress,
        'start_time' => $start_time,
    ]);
} catch (Exception $e) {
    error_log("Error in fetch_game_pattern.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch game pattern due to a server error.']);
}
?>