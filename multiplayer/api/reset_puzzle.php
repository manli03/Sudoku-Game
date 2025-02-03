<?php
// /multiplayer/api/reset_puzzle.php
session_start();
header('Content-Type: application/json');

require_once '../../db.php'; // Corrected path

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['game_id'])) {
    echo json_encode(['success' => false, 'error' => 'Game ID not provided.']);
    exit();
}

$game_id = intval($input['game_id']);

try {
    // Begin Transaction
    $db->beginTransaction();

    // Fetch game details
    $stmt = $db->prepare("SELECT owner_id, pattern, status FROM multiplayer_games WHERE id = :game_id FOR UPDATE");
    $stmt->execute(['game_id' => $game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Game not found.']);
        exit();
    }

    // Check if user is a player
    $stmt = $db->prepare("SELECT id FROM multiplayer_players WHERE game_id = :game_id AND user_id = :user_id");
    $stmt->execute(['game_id' => $game_id, 'user_id' => $user_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        echo json_encode(['success' => false, 'error' => 'You are not a participant of this game.']);
        exit();
    }


    // Player can reset their own progress
    $initial_pattern = $game['pattern'];
    $stmt = $db->prepare("UPDATE multiplayer_players SET progress = :progress, blanks_remaining = :blanks_remaining WHERE game_id = :game_id AND user_id = :user_id");
    $blanks_remaining = substr_count($initial_pattern, '.');
    $stmt->execute([
        'progress' => $initial_pattern,
        'blanks_remaining' => $blanks_remaining,
        'game_id' => $game_id,
        'user_id' => $user_id
    ]);

    $db->commit();

    echo json_encode(['success' => true, 'new_pattern' => $initial_pattern]);

} catch (Exception $e) {
    $db->rollBack();
    error_log("Error in reset_puzzle.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to reset the puzzle due to a server error.']);
}
?>