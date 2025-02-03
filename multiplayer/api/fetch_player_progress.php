<?php
// /multiplayer/api/fetch_player_progress.php
session_start();
header('Content-Type: application/json');

require_once '../../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$game_id = $_GET['game_id'] ?? null;

if (!$game_id) {
    echo json_encode(['success' => false, 'error' => 'Missing game ID']);
    exit();
}

// Verify that the user is part of the game
$stmt = $db->prepare("SELECT COUNT(*) FROM multiplayer_players WHERE game_id = :game_id AND user_id = :user_id");
$stmt->execute(['game_id' => $game_id, 'user_id' => $user_id]);
$is_participant = $stmt->fetchColumn() > 0;

if (!$is_participant) {
    echo json_encode(['success' => false, 'error' => 'You are not a participant of this game']);
    exit();
}

// Fetch players and their blanks_remaining
$stmt = $db->prepare("
    SELECT u.username, mp.blanks_remaining 
    FROM multiplayer_players mp
    JOIN users u ON mp.user_id = u.id
    WHERE mp.game_id = :game_id
");
$stmt->execute(['game_id' => $game_id]);
$players = $stmt->fetchAll();

if ($players) {
    echo json_encode(['success' => true, 'players' => $players]);
} else {
    echo json_encode(['success' => false, 'error' => 'No players found for this game']);
}
?>