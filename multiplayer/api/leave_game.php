<?php
// /multiplayer/api/leave_game.php
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

    // Check if the user is part of the game
    $stmt = $db->prepare("SELECT game_id FROM multiplayer_players WHERE game_id = :game_id AND user_id = :user_id");
    $stmt->execute(['game_id' => $game_id, 'user_id' => $user_id]);
    $is_participant = $stmt->fetchColumn();

    if (!$is_participant) {
        echo json_encode(['success' => false, 'error' => 'You are not part of this game.']);
        $db->rollBack();
        exit();
    }

    // Remove the user from multiplayer_players
    $stmt = $db->prepare("DELETE FROM multiplayer_players WHERE game_id = :game_id AND user_id = :user_id");
    $stmt->execute(['game_id' => $game_id, 'user_id' => $user_id]);

    // Check if the user was the owner
    $stmt = $db->prepare("SELECT owner_id FROM multiplayer_games WHERE id = :game_id");
    $stmt->execute(['game_id' => $game_id]);
    $owner_id = $stmt->fetchColumn();

    if ($owner_id == $user_id) {
        // Find the next player who joined the game
        $stmt = $db->prepare("SELECT user_id FROM multiplayer_players WHERE game_id = :game_id ORDER BY joined_at ASC LIMIT 1");
        $stmt->execute(['game_id' => $game_id]);
        $new_owner_id = $stmt->fetchColumn();

        if ($new_owner_id) {
            // Transfer ownership to the new owner
            $stmt = $db->prepare("UPDATE multiplayer_games SET owner_id = :new_owner_id WHERE id = :game_id");
            $stmt->execute(['new_owner_id' => $new_owner_id, 'game_id' => $game_id]);
        } else {
            // No players left, end the game
            $stmt = $db->prepare("UPDATE multiplayer_games SET status = 'ended' WHERE id = :game_id");
            $stmt->execute(['game_id' => $game_id]);
        }
    }

    // If no players left, optionally delete the game or keep it as 'ended'
    $stmt = $db->prepare("SELECT COUNT(*) FROM multiplayer_players WHERE game_id = :game_id");
    $stmt->execute(['game_id' => $game_id]);
    $remaining_players = $stmt->fetchColumn();

    if ($remaining_players == 0) {
        // Optionally delete the game
        // $stmt = $db->prepare("DELETE FROM multiplayer_games WHERE id = :game_id");
        // $stmt->execute(['game_id' => $game_id]);

        // Or keep it as 'ended'
        $stmt = $db->prepare("UPDATE multiplayer_games SET status = 'ended' WHERE id = :game_id");
        $stmt->execute(['game_id' => $game_id]);
    }

    $db->commit();

    echo json_encode(['success' => true, 'message' => 'You have left the game successfully.']);
    // Unset the 'joined_game' session variable
    unset($_SESSION['joined_game']);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error in leave_game.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to leave the game due to a server error.']);
}
?>