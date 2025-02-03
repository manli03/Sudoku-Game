<?php
// /multiplayer/api/create_game.php
session_start();
header('Content-Type: application/json');

require_once '../../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['difficulty']) && isset($input['puzzle'])) {
    $difficulty = strtolower(trim($input['difficulty']));
    $puzzle = trim($input['puzzle']);
    $valid_difficulties = ['easy', 'medium', 'hard', 'expert', 'master', 'extreme'];

    if (!in_array($difficulty, $valid_difficulties)) {
        echo json_encode(['success' => false, 'error' => 'Invalid difficulty level']);
        exit();
    }

    // Validate the puzzle format (must be 81 characters, digits 1-9 or '.')
    if (strlen($puzzle) !== 81 || preg_match('/[^1-9.]/', $puzzle)) {
        echo json_encode(['success' => false, 'error' => 'Invalid puzzle format']);
        exit();
    }

    try {
        $db->beginTransaction();

        // Insert new game into multiplayer_games
        $stmt = $db->prepare("INSERT INTO multiplayer_games (owner_id, status, pattern, difficulty) VALUES (:owner_id, 'waiting', :pattern, :difficulty)");
        $stmt->execute(['owner_id' => $user_id, 'pattern' => $puzzle, 'difficulty' => $difficulty]);
        $game_id = $db->lastInsertId();

        // Insert the owner into multiplayer_players with progress set to pattern
        $stmt = $db->prepare("INSERT INTO multiplayer_players (game_id, user_id, blanks_remaining, progress) VALUES (:game_id, :user_id, :blanks_remaining, :progress)");
        $blanks_remaining = substr_count($puzzle, '.');
        $stmt->execute([
            'game_id' => $game_id,
            'user_id' => $user_id,
            'blanks_remaining' => $blanks_remaining,
            'progress' => $puzzle
        ]);

        $db->commit();

        echo json_encode(['success' => true, 'game_id' => $game_id]);
    } catch (Exception $e) {
        $db->rollBack();
        // Log the exception message
        error_log("Error in create_game.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to create game: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Missing difficulty level or puzzle']);
    exit();
}
?>