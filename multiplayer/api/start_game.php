<?php
// /multiplayer/api/start_game.php
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

    // Check if the game exists and the user is the owner
    $stmt = $db->prepare("SELECT owner_id, status FROM multiplayer_games WHERE id = :game_id FOR UPDATE");
    $stmt->execute(['game_id' => $game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Game not found.']);
        $db->rollBack();
        exit();
    }

    if ($game['owner_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Only the game owner can start the game.']);
        $db->rollBack();
        exit();
    }

    if ($game['status'] !== 'waiting') {
        echo json_encode(['success' => false, 'error' => 'Game has already been started or ended.']);
        $db->rollBack();
        exit();
    }

    // Check current number of players
    $stmt = $db->prepare("SELECT COUNT(*) FROM multiplayer_players WHERE game_id = :game_id");
    $stmt->execute(['game_id' => $game_id]);
    $player_count = $stmt->fetchColumn();

    // If the game was waiting and now has at least 2 players, update status to 'started' and set start_time
    if ($player_count >= 2 && $game['status'] === 'waiting') {
        $stmt = $db->prepare("UPDATE multiplayer_games SET status = 'started', start_time = NOW() WHERE id = :game_id");
        $stmt->execute(['game_id' => $game_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not enough players to start the game.']); 
        $db->rollBack();
        exit();
    }

    $db->commit();

    echo json_encode(['success' => true, 'message' => 'Game started successfully.']);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error in start_game.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to start the game due to a server error.']);
}
?>