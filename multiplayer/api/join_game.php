<?php
// /multiplayer/api/join_game.php
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

    // Fetch game status and pattern
    $stmt = $db->prepare("SELECT status, pattern FROM multiplayer_games WHERE id = :game_id FOR UPDATE");
    $stmt->execute(['game_id' => $game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Game not found.']);
        $db->rollBack();
        exit();
    }

    if ($game['status'] !== 'waiting' && $game['status'] !== 'started') {
        echo json_encode(['success' => false, 'error' => 'This game cannot be joined.']);
        $db->rollBack();
        exit();
    }

    // Check current number of players
    $stmt = $db->prepare("SELECT COUNT(*) FROM multiplayer_players WHERE game_id = :game_id");
    $stmt->execute(['game_id' => $game_id]);
    $player_count = $stmt->fetchColumn();
    $max_players = 4; // Fixed maximum players

    if ($player_count >= $max_players) {
        echo json_encode(['success' => false, 'error' => 'This game is already full.']);
        $db->rollBack();
        exit();
    }

    // Check if user is already in the game
    $stmt = $db->prepare("SELECT id FROM multiplayer_players WHERE game_id = :game_id AND user_id = :user_id");
    $stmt->execute(['game_id' => $game_id, 'user_id' => $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You are already in this game.']);
        $db->rollBack();
        exit();
    }

    // Check if user is already in another game
    $stmt = $db->prepare("SELECT id FROM multiplayer_players WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You are already in another game.']);
        $db->rollBack();
        exit();
    }

    // Insert the user into multiplayer_players
    $stmt = $db->prepare("INSERT INTO multiplayer_players (game_id, user_id, blanks_remaining, progress) VALUES (:game_id, :user_id, :blanks_remaining, :progress)");
    $puzzle = trim($game['pattern']);
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
    error_log("Error in join_game.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to join the game due to a server error.']);
}
?>