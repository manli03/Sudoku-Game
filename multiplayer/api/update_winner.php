<?php
// /multiplayer/api/update_winner.php
session_start();
header('Content-Type: application/json');

require_once '../../db.php'; // Corrected path

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action']) || !isset($input['game_id']) || !isset($input['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters.']);
    exit();
}

$action = $input['action'];
$game_id = intval($input['game_id']);
$user_id = intval($input['user_id']);

if ($game_id <= 0 || $user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid game ID or user ID.']);
    exit();
}

if ($action !== 'update_winner') {
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    exit();
}

try {
    // Begin Transaction
    $db->beginTransaction();

    // Check if the game exists and is in 'started' status
    $stmt = $db->prepare("SELECT status FROM multiplayer_games WHERE id = :game_id FOR UPDATE");
    $stmt->execute(['game_id' => $game_id]);
    $game_status = $stmt->fetchColumn();

    if ($game_status !== 'started') {
        echo json_encode(['success' => false, 'error' => 'Game is not in a state that can be completed.']);
        $db->rollBack();
        exit();
    }

    // Update the winner and game status
    $stmt = $db->prepare("UPDATE multiplayer_games SET winner_id = :user_id, status = 'completed', end_time = NOW() WHERE id = :game_id");
    $result = $stmt->execute(['user_id' => $user_id, 'game_id' => $game_id]);

    if ($result) {
        // Remove all players from multiplayer_players table
        $stmt = $db->prepare("DELETE FROM multiplayer_players WHERE game_id = :game_id");
        $stmt->execute(['game_id' => $game_id]);

        $db->commit();

        echo json_encode(['success' => true, 'message' => 'Winner updated and players removed successfully.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update the winner.']);
        $db->rollBack();
    }
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error in update_winner.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to update the winner due to a server error.']);
}
?>