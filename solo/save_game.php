<?php
require '../db.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate the input
    $user_id = isset($data['user_id']) ? intval($data['user_id']) : null;
    $difficulty = isset($data['difficulty']) ? trim($data['difficulty']) : null;
    $time = isset($data['time']) ? trim($data['time']) : null;
    $status = isset($data['status']) ? trim($data['status']) : null;

    if ($user_id && $difficulty && $time && $status) {
        try {
            // Save the game to the database
            $query = "INSERT INTO games (user_id, difficulty, time, status) VALUES (:user_id, :difficulty, :time, :status)";
            $stmt = $db->prepare($query);

            // Bind parameters
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
            $stmt->bindParam(':time', $time, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);

            if ($stmt->execute()) {
                // Fetch the latest game (just inserted)
                $latest_game_id = $db->lastInsertId();
                $latest_game_query = $db->prepare(
                    "SELECT difficulty, time, status, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS last_played 
                     FROM games WHERE id = :id"
                );
                $latest_game_query->bindParam(':id', $latest_game_id, PDO::PARAM_INT);
                $latest_game_query->execute();
                $latest_game = $latest_game_query->fetch(PDO::FETCH_ASSOC);

                // Return the latest game
                echo json_encode(['success' => true, 'latest_game' => $latest_game]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save game history.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid input data.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
