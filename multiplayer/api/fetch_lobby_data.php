<?php
// /multiplayer/api/fetch_lobby_data.php
session_start();
header('Content-Type: application/json');
require '../../db.php'; // Corrected path

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if the user is already in a game
$player_stmt = $db->prepare("SELECT game_id FROM multiplayer_players WHERE user_id = :user_id");
$player_stmt->execute(['user_id' => $user_id]);
$current_game = $player_stmt->fetchColumn();

// Define maximum players per game
$max_players = 4;

// Fetch all available games
$games_stmt = $db->prepare("
    SELECT 
        mg.id, 
        u.username AS owner_name, 
        COUNT(mp.id) AS player_count,
        mg.status,
        mg.difficulty
    FROM multiplayer_games mg
    JOIN users u ON mg.owner_id = u.id
    LEFT JOIN multiplayer_players mp ON mg.id = mp.game_id
    GROUP BY mg.id, u.username, mg.status, mg.difficulty
    HAVING mg.status IN ('waiting', 'started')
");
$games_stmt->execute();
$games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare response
$response = [
    'success' => true,
    'current_game' => $current_game,
    'games' => []
];

foreach ($games as $game) {
    // Determine if the game is joinable (not full)
    $joinable = ($game['player_count'] < $max_players) && ($game['status'] !== 'completed');

    // Only list games that are joinable or not full
    if ($joinable) {
        $response['games'][] = [
            'id' => $game['id'],
            'owner_name' => htmlspecialchars($game['owner_name']),
            'player_count' => intval($game['player_count']),
            'status' => htmlspecialchars($game['status']),
            'difficulty' => htmlspecialchars($game['difficulty']),
            'joinable' => $joinable
        ];
    }
}

echo json_encode($response);
?>