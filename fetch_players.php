<?php
include 'db.php';

if (isset($_GET['game_id'])) {
    $game_id = intval($_GET['game_id']); // Ensure game_id is an integer

    // Query to fetch players related to the game
    $sql = "
        SELECT gr.user_id, u.username
        FROM game_results gr
        JOIN users u ON gr.user_id = u.id
        WHERE gr.game_id = :game_id
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(['game_id' => $game_id]);

    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the players as JSON
    echo json_encode($players);
    exit();
}
