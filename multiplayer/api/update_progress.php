<?php
// /multiplayer/api/update_progress.php
session_start();
header('Content-Type: application/json');

require_once '../../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['game_id']) || !isset($input['cell_index']) || !isset($input['value'])) {
    echo json_encode(['success' => false, 'error' => 'Missing game_id, cell_index, or value.']);
    exit();
}

$game_id = intval($input['game_id']);
$cell_index = intval($input['cell_index']);
$value = intval($input['value']);

if ($cell_index < 0 || $cell_index > 80 || $value < 1 || $value > 9) {
    echo json_encode(['success' => false, 'error' => 'Invalid cell index or value.']);
    exit();
}

try {
    // Begin Transaction
    $db->beginTransaction();

    // Fetch the initial game pattern
    $stmt = $db->prepare("SELECT pattern FROM multiplayer_games WHERE id = :game_id FOR UPDATE");
    $stmt->execute(['game_id' => $game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Game not found.']);
        exit();
    }

    $pattern = $game['pattern'];

    // Check if the cell is editable (not part of the initial pattern)
    if ($pattern[$cell_index] !== '.') {
        echo json_encode(['success' => false, 'error' => 'This cell is not editable.']);
        exit();
    }

    // Fetch the player's current progress
    $stmt = $db->prepare("SELECT progress FROM multiplayer_players WHERE game_id = :game_id AND user_id = :user_id FOR UPDATE");
    $stmt->execute(['game_id' => $game_id, 'user_id' => $user_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        echo json_encode(['success' => false, 'error' => 'Player not found in the game.']);
        exit();
    }

    $progress = $player['progress'] ?? str_repeat('.', 81); // Initialize empty progress if null

    // Update the progress with the new value
    $new_progress = substr_replace($progress, $value, $cell_index, 1);

    // Update the player's progress in the database
    $stmt = $db->prepare("UPDATE multiplayer_players SET progress = :progress WHERE game_id = :game_id AND user_id = :user_id");
    $stmt->execute(['progress' => $new_progress, 'game_id' => $game_id, 'user_id' => $user_id]);

    // Calculate remaining blanks ('.') in the progress
    $blanks_remaining = substr_count($new_progress, '.');

    // Update blanks_remaining for the player
    $stmt = $db->prepare("UPDATE multiplayer_players SET blanks_remaining = :blanks_remaining WHERE game_id = :game_id AND user_id = :user_id");
    $stmt->execute(['blanks_remaining' => $blanks_remaining, 'game_id' => $game_id, 'user_id' => $user_id]);

    $db->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $db->rollBack();
    error_log("Error in update_progress.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to update progress due to a server error.']);
}
?>