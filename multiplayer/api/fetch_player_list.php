<?php
// /multiplayer/api/fetch_player_list.php
session_start();
header('Content-Type: application/json');

require_once '../../db.php';

// Check if the user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit();
}

// Retrieve and validate the game_id from the GET parameters
$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;

if ($game_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid game ID.']);
    exit();
}

try {
    // Prepare the SQL statement to fetch players in the specified game
    $stmt = $db->prepare("
        SELECT users.id AS user_id, users.username
        FROM multiplayer_players
        JOIN users ON multiplayer_players.user_id = users.id
        WHERE multiplayer_players.game_id = :game_id
    ");

    // Execute the statement with the provided game_id
    $stmt->execute(['game_id' => $game_id]);

    // Fetch all players as an associative array
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the list of players in JSON format
    echo json_encode(['success' => true, 'players' => $players]);
} catch (Exception $e) {
    // Log the error for debugging purposes
    error_log("Error in fetch_player_list.php: " . $e->getMessage());

    // Return an error response
    echo json_encode(['success' => false, 'error' => 'Failed to fetch player list due to a server error.']);
}
?>