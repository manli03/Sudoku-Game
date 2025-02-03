<?php
// /multiplayer/game.php
session_start();
require '../db.php';

// Handle AJAX requests for updating the winner and transferring ownership
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    // Handle updating the winner
    if (isset($input['action']) && $input['action'] === 'update_winner') {
        $game_id = isset($input['game_id']) ? intval($input['game_id']) : 0;
        $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;

        if ($game_id <= 0 || $user_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid game or user ID.']);
            exit();
        }

        // Check if the game is already completed
        $stmt = $db->prepare("SELECT status FROM multiplayer_games WHERE id = :game_id");
        $stmt->execute(['game_id' => $game_id]);
        $game_status = $stmt->fetchColumn();

        if ($game_status !== 'started') {
            echo json_encode(['success' => false, 'error' => 'Game is not in a state that can be completed.']);
            exit();
        }

        // Begin transaction
        $db->beginTransaction();

        try {
            // Save player progress to game_results table
            $stmt = $db->prepare(
                "INSERT INTO game_results (game_id, user_id, username, blanks_remaining, progress)
             SELECT p.game_id, p.user_id, u.username, p.blanks_remaining, p.progress
             FROM multiplayer_players p
             JOIN users u ON p.user_id = u.id
             WHERE p.game_id = :game_id"
            );
            $stmt->execute(['game_id' => $game_id]);

            // Mark the game as completed
            $stmt = $db->prepare("UPDATE multiplayer_games SET winner_id = :user_id, status = 'completed', end_time = NOW() WHERE id = :game_id");
            $stmt->execute(['user_id' => $user_id, 'game_id' => $game_id]);

            // Remove players from multiplayer_players table
            $stmt = $db->prepare("DELETE FROM multiplayer_players WHERE game_id = :game_id");
            $stmt->execute(['game_id' => $game_id]);

            $db->commit();

            echo json_encode(['success' => true, 'message' => 'Winner updated and game completed.']);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to update winner: ' . $e->getMessage()]);
        }
        exit();
    }


    // Handle updating player progress
    if (isset($input['action']) && $input['action'] === 'update_progress') {
        $game_id = isset($input['game_id']) ? intval($input['game_id']) : 0;
        $cell_index = isset($input['cell_index']) ? intval($input['cell_index']) : -1;
        $value = isset($input['value']) ? intval($input['value']) : 0;

        if ($game_id <= 0 || $cell_index < 0 || $cell_index > 80 || $value < 1 || $value > 9) {
            echo json_encode(['success' => false, 'error' => 'Invalid input parameters.']);
            exit();
        }

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
            exit();
        }

        $user_id = $_SESSION['user_id'];

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

            $progress = $player['progress'] ?? str_repeat('.', 81); // Default to all dots if no progress exists

            // Update the progress with the new value
            $new_progress = substr_replace($progress, $value, $cell_index, 1);

            // Calculate remaining blanks ('.') in the progress
            $blanks_remaining = substr_count($new_progress, '.');

            // Update the player's progress and blanks_remaining in the database
            $stmt = $db->prepare("UPDATE multiplayer_players SET progress = :progress, blanks_remaining = :blanks_remaining WHERE game_id = :game_id AND user_id = :user_id");
            $stmt->execute(['progress' => $new_progress, 'blanks_remaining' => $blanks_remaining, 'game_id' => $game_id, 'user_id' => $user_id]);

            $db->commit();

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error in update_progress: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to update progress due to a server error.']);
        }
        exit();
    }


    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$game_id = $_GET['game_id'] ?? null;

// Fetch user data with all necessary fields
$stmt = $db->prepare("SELECT username, email, profile_color, is_profile_complete 
                      FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // User not found, log out
    header("Location: ../logout.php");
    exit();
}

if (!$user['is_profile_complete']) {
    // Redirect to profile.php with a message to complete profile
    header("Location: ../profile.php?message=complete_profile");
    exit();
}

if (!$game_id) {
    header("Location: lobby.php?message=missing_game_id");
    exit();
}

$game_id = intval($game_id);

// Fetch game details
$stmt = $db->prepare("SELECT id, owner_id, status, pattern, start_time, winner_id, difficulty, end_time FROM multiplayer_games WHERE id = :game_id LIMIT 1");
$stmt->execute(['game_id' => $game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    header("Location: lobby.php?message=game_not_found");
    exit();
}

// Fetch players
$stmt = $db->prepare("SELECT users.username, multiplayer_players.user_id, multiplayer_players.blanks_remaining 
                      FROM multiplayer_players 
                      JOIN users ON multiplayer_players.user_id = users.id 
                      WHERE multiplayer_players.game_id = :game_id");
$stmt->execute(['game_id' => $game_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

$is_owner = ($game['owner_id'] == $user_id);
$winner = null;
if ($game['winner_id']) {
    $stmt = $db->prepare("SELECT username FROM users WHERE id = :winner_id");
    $stmt->execute(['winner_id' => $game['winner_id']]);
    $winner = $stmt->fetchColumn();
}

// Function to check if the user is still in the game
function is_user_in_game($players, $user_id)
{
    foreach ($players as $player) {
        if ($player['user_id'] == $user_id) {
            $_SESSION['joined_game'] = true;
            return true;
        }
    }
    return false;
}

// Calculate initial blanks based on the game pattern
$initial_blanks = substr_count($game['pattern'], '.');

// If the game is completed, proceed to display completion message
if ($game['status'] === 'completed') {

    // Unset the 'joined_game' session variable
    unset($_SESSION['joined_game']);

    // Fetch winner details
    if ($game['winner_id']) {
        $stmt = $db->prepare("SELECT username FROM users WHERE id = :winner_id");
        $stmt->execute(['winner_id' => $game['winner_id']]);
        $winner = $stmt->fetchColumn();
    } else {
        $winner = null;
    }

    // Calculate time taken
    if ($game['start_time'] && $game['end_time']) {
        $start_time = new DateTime($game['start_time']);
        $end_time = new DateTime($game['end_time']);
        $interval = $start_time->diff($end_time);
        $time_taken = $interval->format('%H:%I:%S');
    } else {
        $time_taken = 'N/A';
    }

    // Fetch player progress from game_results table
    $stmt = $db->prepare("SELECT username, blanks_remaining, progress FROM game_results WHERE game_id = :game_id");
    $stmt->execute(['game_id' => $game_id]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Check if player joined the game and return to lobby if not
    if (!is_user_in_game($players, $user_id)) {
        header("Location: lobby.php?message=not_in_game_$game_id");
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Play a multiplayer Sudoku game with other players.">
    <title>Multiplayer Sudoku Game</title>

    <!-- Custom Styles -->
    <style>
        .sudoku-board {
            display: grid;
            grid-template-columns: repeat(9, 40px);
            grid-template-rows: repeat(9, 40px);
            gap: 2px;
            justify-content: center;
            margin: 20px 0;
        }

        .sudoku-board input {
            width: 100%;
            height: 100%;
            text-align: center;
            font-size: 18px;
            font-family: Arial, sans-serif;
            border: 1px solid #ddd;
            outline: none;
            background-color: #fff;
        }

        .sudoku-board input:nth-child(3n+1) {
            border-left: 2px solid black;
        }

        .sudoku-board input:nth-child(n+19):nth-child(-n+27),
        .sudoku-board input:nth-child(n+46):nth-child(-n+54) {
            border-bottom: 2px solid black;
        }

        .sudoku-board input:disabled {
            background-color: #f0f0f0;
            color: #000;
        }

        .invalid {
            background-color: #ffcccc !important;
            /* Red for invalid cells */
        }

        .progress-container {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .progress-text {
            margin-left: 10px;
            font-size: 14px;
            color: #4a4a4a;
        }
    </style>
</head>

<body style="overflow-x: auto;">
    <!-- Include the Navigation Bar -->
    <?php include '../navbar.php'; ?>
    <section class="section">
        <div class="container">
            <h1 class="title has-text-centered">Multiplayer Sudoku Game</h1>

            <?php if ($game['status'] === 'waiting'): ?>
                <div id="waiting-screen" class="box has-text-centered">
                    <p class="subtitle">Waiting for players to join.</p>
                    <div class="box">
                        <h2 class="subtitle">Players Waiting</h2>
                        <ul id="player-list">
                            <?php foreach ($players as $player): ?>
                                <li class="is-capitalized has-text-weight-semibold">
                                    <?= htmlspecialchars(ucwords(strtolower($player['username']))) . ($player['user_id'] == $game['owner_id'] ? " <span class='has-text-info'>(Owner)</span>" : "") ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <button id="leave-game-main" class="button is-danger">Leave Game</button>
                    <?php if ($is_owner): ?>
                        <button id="start-game" class="button is-primary">Start Game</button>
                    <?php endif; ?>
                </div>
            <?php elseif ($game['status'] === 'completed'): ?>
                <div id="completed-screen" class="box has-text-centered">
                    <h2 class="title">Game Completed!</h2>
                    <?php if ($winner): ?>
                        <p class="subtitle">Winner: <strong><?= htmlspecialchars(ucwords(strtolower($winner))) ?></strong></p>
                    <?php else: ?>
                        <p class="subtitle">The game ended with no winner.</p>
                    <?php endif; ?>
                    <p class="subtitle">Time Taken: <strong><?= htmlspecialchars($time_taken) ?></strong></p>
                    <div class="box">
                        <h3 class="subtitle">All Player Progress</h3>
                        <ul>
                            <?php
                            $color_classes = ['is-info', 'is-success', 'is-warning', 'is-danger', 'is-link', 'is-primary'];
                            $color_index = 0;
                            ?>
                            <?php foreach ($players as $player): ?>
                                <li>
                                    <strong><?= htmlspecialchars(ucwords(strtolower($player['username']))) ?>:</strong>
                                    <div class="progress-container">
                                        <progress class="progress <?= $color_classes[$color_index % count($color_classes)] ?>"
                                            value="<?= ($initial_blanks - $player['blanks_remaining']) ?>"
                                            max="<?= $initial_blanks ?>"></progress>
                                        <div class="progress-text"><?= $player['blanks_remaining'] ?> blanks remaining</div>
                                    </div>
                                </li>
                                <?php $color_index++; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <button id="return-to-lobby" class="button is-link">Return to Lobby</button>
                </div>
            <?php else: ?>
                <div id="game-content">
                    <div class="columns">
                        <div class="column is-4">
                            <div class="box">
                                <h2 class="subtitle">Players</h2>
                                <ul id="player-list">
                                    <?php foreach ($players as $player): ?>
                                        <li><?= htmlspecialchars($player['username']) ?><?= $player['user_id'] == $game['owner_id'] ? " (Owner)" : "" ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <div class="box">
                                <h2 class="subtitle">Player Progress</h2>
                                <ul id="progress-display">
                                    <?php
                                    $color_classes = ['is-info', 'is-success', 'is-warning', 'is-danger', 'is-link', 'is-primary'];
                                    $color_index = 0;
                                    ?>
                                    <?php foreach ($players as $player): ?>
                                        <li>
                                            <strong><?= htmlspecialchars(ucwords(strtolower($player['username']))) ?>:</strong>
                                            <div class="progress-container">
                                                <progress
                                                    class="progress <?= $color_classes[$color_index % count($color_classes)] ?>"
                                                    value="<?= ($initial_blanks - $player['blanks_remaining']) ?>"
                                                    max="<?= $initial_blanks ?>"></progress>
                                                <div class="progress-text"><?= $player['blanks_remaining'] ?> blanks remaining
                                                </div>
                                            </div>
                                        </li>
                                        <?php $color_index++; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <div class="box">
                                <h2 class="subtitle">Controls</h2>
                                <button id="restart-puzzle" class="button is-warning">Restart Puzzle</button>

                                <p id="timer" style="margin-top: 10px; font-size: 18px; font-weight: bold; color: #34495e;">
                                    Time: 00:00:00</p>
                            </div>

                            <div class="box">
                                <button id="leave-game-main" class="button is-danger">Leave Game</button>
                            </div>
                        </div>

                        <div class="column is-8">
                            <div class="sudoku-board" id="sudoku-board">
                                <!-- Sudoku grid will be dynamically generated -->
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        /**
         * Capitalizes the first letter of each word in a string.
         * @param {string} str - The string to capitalize.
         * @returns {string} - The capitalized string.
         */
        function capitalizeWords(str) {
            return str.toLowerCase().replace(/\b\w/g, char => char.toUpperCase());
        }
    </script>

    <?php if ($game['status'] === 'started'): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const gameId = <?= json_encode($game_id) ?>;
                const userId = <?= json_encode($user_id) ?>;
                const isOwner = <?= json_encode($is_owner) ?>;
                const timerDisplay = document.getElementById("timer");
                const sudokuBoard = document.getElementById("sudoku-board");
                const generatePuzzleButton = document.getElementById("generate-puzzle");
                const resetPuzzleButton = document.getElementById("restart-puzzle");
                const progressDisplay = document.getElementById("progress-display");
                const leaveGameButton = document.getElementById("leave-game-main");
                const playerList = document.getElementById("player-list");
                const waitingScreen = document.getElementById("waiting-screen");
                const gameContent = document.getElementById("game-content");
                const urlParams = new URLSearchParams(window.location.search);
                const message = urlParams.get('message');

                let timerInterval = null;
                let startTime = null;
                let elapsedTime = 0;
                let currentPuzzle = ""; // Stores the current puzzle's initial state
                let disabledCells = new Set(); // Track disabled cells
                let solved = false;
                let gameCompleted = false;
                // Initialize previousPlayers and a map for user_id to username
                let previousPlayerIds = [];
                let previousPlayerMap = {};

                // Timer Functions
                function startTimer(serverStartTime) {
                    clearTimer(); // Clear any existing timer
                    const now = Date.now();
                    const serverTime = new Date(serverStartTime).getTime();
                    elapsedTime = now - serverTime;
                    startTime = serverTime;
                    timerInterval = setInterval(updateTimer, 1000);
                }

                function updateTimer() {
                    elapsedTime += 1000;
                    const seconds = Math.floor(elapsedTime / 1000) % 60;
                    const minutes = Math.floor(elapsedTime / (1000 * 60)) % 60;
                    const hours = Math.floor(elapsedTime / (1000 * 60 * 60)) % 24;
                    timerDisplay.textContent = `Time: ${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                }

                function stopTimer() {
                    clearInterval(timerInterval);
                }

                function clearTimer() {
                    clearInterval(timerInterval);
                    timerInterval = null;
                    elapsedTime = 0;
                    timerDisplay.textContent = "Time: 00:00:00";
                }

                // Function to show SweetAlert2 toast messages
                function showToast(message, icon = 'error') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: icon, // Options: 'success', 'error', 'warning', 'info', 'question'
                        title: message,
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer)
                            toast.addEventListener('mouseleave', Swal.resumeTimer)
                        }
                    });
                }

                if (message === 'puzzle_reset_successfully') {
                    showToast("Puzzle reset to the initial state successfully!", 'success');
                    // Clean up the URL by removing the message parameter
                    const url = new URL(window.location.href);
                    url.searchParams.delete('message');
                    window.history.replaceState({}, document.title, url.toString());
                }

                // Function to validate user input and highlight errors
                function validateInput(row, col, value) {
                    const cells = sudokuBoard.querySelectorAll("input");
                    let isValid = true;

                    // Clear all existing error highlights and reset disabled state
                    cells.forEach(cell => {
                        const cellRow = parseInt(cell.dataset.row);
                        const cellCol = parseInt(cell.dataset.col);

                        // Remove error highlights and reset background color
                        if (
                            cellRow === row || cellCol === col ||
                            (Math.floor(cellRow / 3) === Math.floor(row / 3) &&
                                Math.floor(cellCol / 3) === Math.floor(col / 3))
                        ) {
                            if (cell.classList.contains("invalid")) {
                                cell.classList.remove("invalid");
                                cell.style.backgroundColor = "#fff"; // Reset to white
                            }
                        }
                    });

                    // Apply error highlights for duplicates in the same row, column, or 3x3 subgrid
                    if (value !== "") {
                        cells.forEach(cell => {
                            const cellRow = parseInt(cell.dataset.row);
                            const cellCol = parseInt(cell.dataset.col);

                            if (
                                (cellRow === row || cellCol === col ||
                                    (Math.floor(cellRow / 3) === Math.floor(row / 3) &&
                                        Math.floor(cellCol / 3) === Math.floor(col / 3))) &&
                                cell.value === value
                            ) {
                                if (cell !== cells[row * 9 + col]) {
                                    isValid = false;
                                    cell.classList.add("invalid");
                                    cell.style.backgroundColor = "#ffcccc"; // Light red for invalid cells
                                }
                            }
                        });
                    }

                    // Highlight the current cell in red if invalid
                    if (!isValid && value !== "") {
                        const currentCell = sudokuBoard.querySelector(`input[data-row='${row}'][data-col='${col}']`);
                        if (currentCell) {
                            currentCell.classList.add("invalid");
                            currentCell.style.backgroundColor = "#ffcccc"; // Light red for invalid cells
                        }

                        // Disable all other cells except the current one and track them
                        cells.forEach(cell => {
                            if (cell !== cells[row * 9 + col] && !cell.disabled) {
                                disabledCells.add(cell); // Track which cells are disabled
                                cell.disabled = true;
                            }
                        });

                        // Show SweetAlert2 toast message
                        showToast("Fix the error before continuing.", 'error');
                    } else {
                        // Re-enable only the cells that were disabled previously
                        disabledCells.forEach(cell => {
                            cell.disabled = false; // Re-enable previously disabled cells
                        });

                        // Clear the disabledCells set after enabling them
                        disabledCells.clear();
                    }

                    return isValid;
                }

                // Function to handle highlighting of related cells (normal highlight)
                function handleHighlight(event) {
                    const cell = event.target;
                    const row = parseInt(cell.dataset.row);
                    const col = parseInt(cell.dataset.col);

                    // Reapply all highlights when the cell value changes
                    reapplyHighlights(row, col);
                }

                // Function to reapply both normal and error highlights for a given cell
                function reapplyHighlights(row, col) {
                    const cells = sudokuBoard.querySelectorAll("input");

                    // Clear all highlights first
                    cells.forEach(cell => {
                        if (!cell.classList.contains("invalid")) {
                            cell.style.backgroundColor = "#fff"; // Reset to white
                        }
                    });

                    // Apply normal highlights (row, column, and subgrid)
                    cells.forEach(input => {
                        const inputRow = parseInt(input.dataset.row);
                        const inputCol = parseInt(input.dataset.col);

                        if (inputRow === row || inputCol === col) {
                            input.style.backgroundColor = "#f0f8ff"; // Light blue for row/column
                        }

                        if (
                            Math.floor(inputRow / 3) === Math.floor(row / 3) &&
                            Math.floor(inputCol / 3) === Math.floor(col / 3)
                        ) {
                            input.style.backgroundColor = "#ffe4b5"; // Light orange for subgrid
                        }
                    });

                    // Highlight the currently selected cell
                    const currentCell = sudokuBoard.querySelector(`input[data-row='${row}'][data-col='${col}']`);
                    if (currentCell) {
                        currentCell.style.backgroundColor = "#add8e6"; // Different color for the current cell
                    }

                    // Reapply error highlights
                    cells.forEach(cell => {
                        if (cell.classList.contains("invalid") && cell.value !== "") {
                            cell.style.backgroundColor = "#ffcccc"; // Light red for invalid cells
                        }
                    });
                }

                // Function to clear normal highlights
                function clearHighlight() {
                    const cells = sudokuBoard.querySelectorAll("input");
                    cells.forEach(input => {
                        // Reset only cells that are not invalid
                        if (!input.classList.contains("invalid")) {
                            input.style.backgroundColor = "#fff"; // Reset to white
                        }
                    });

                    // Reapply error highlights for invalid cells with a value
                    cells.forEach(input => {
                        if (input.classList.contains("invalid") && input.value !== "") {
                            input.style.backgroundColor = "#ffcccc"; // Light red for invalid cells
                        }
                    });
                }

                // Function to render the Sudoku board with enhanced functionalities
                function renderBoard(initialPattern, playerProgress) {
                    sudokuBoard.innerHTML = "";

                    for (let row = 0; row < 9; row++) {
                        for (let col = 0; col < 9; col++) {
                            const index = row * 9 + col;
                            const cell = document.createElement("input");
                            cell.type = "text";
                            cell.maxLength = 1;

                            cell.dataset.row = row;
                            cell.dataset.col = col;

                            // Determine the value to display (use progress if available, fallback to initial pattern)
                            const value = playerProgress[index] !== '.' ? playerProgress[index] : initialPattern[index];

                            if (initialPattern[index] !== '.') {
                                // Cell is part of the initial puzzle, make it uneditable
                                cell.value = value;
                                cell.disabled = true;
                                cell.style.color = "#000"; // Pre-filled cells in black
                            } else {
                                // Cell is editable
                                cell.value = value !== '.' ? value : ''; // Show the progress value or leave empty
                                cell.disabled = false;
                                cell.style.color = "#555"; // Editable cells in gray

                                // Attach event listeners for input and focus/blur
                                cell.addEventListener("input", function () {
                                    if (!/^[1-9]$/.test(cell.value)) {
                                        cell.value = ""; // Clear invalid input
                                    } else {
                                        const inputValue = cell.value;
                                        validateInput(row, col, inputValue);
                                        updateProgress(index, inputValue);
                                    }

                                    // Clear error highlight if the user clears the cell
                                    if (cell.value === "") {
                                        validateInput(row, col, cell.value);
                                    }

                                    // Reapply all highlights on input change
                                    reapplyHighlights(row, col);

                                    updatePlayerProgress(); // Update player's progress

                                    checkIfGameComplete(); // Check if the game is complete after input
                                });

                                // Highlight related cells on focus
                                cell.addEventListener("focus", handleHighlight);
                                cell.addEventListener("blur", clearHighlight);
                            }

                            sudokuBoard.appendChild(cell);
                        }
                    }
                }

                // Reset the puzzle to the initial state
                resetPuzzleButton?.addEventListener("click", function () {
                    Swal.fire({
                        title: "Are you sure you want to reset the puzzle?",
                        text: "This will remove all your progress and reset the puzzle to its initial state.",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#3085d6",
                        cancelButtonColor: "#d33",
                        confirmButtonText: "Yes, reset the puzzle!"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Save the reset progress to the server
                            fetch("api/reset_puzzle.php", {
                                method: "POST",
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ game_id: gameId }),
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        // Update the page header to indicate successful reset
                                        const url = new URL(window.location.href);
                                        url.searchParams.set('message', 'puzzle_reset_successfully');
                                        window.location.href = url.toString(); // Reload the page with the updated header
                                    } else {
                                        showToast(data.error || "Failed to reset the puzzle.", 'error');
                                    }
                                })
                                .catch(() => {
                                    showToast("Failed to reset the puzzle due to a server error.", 'error');
                                });
                        }
                    });
                });

                // Function to check if the game is complete
                function checkIfGameComplete() {
                    const cells = sudokuBoard.querySelectorAll("input");
                    const board = Array.from(cells).map(cell => cell.value || ".");

                    // Check if the board is completely filled
                    if (board.includes(".")) {
                        return false; // Game is not complete, there are still empty cells
                    }

                    // Convert board array to a string for validation
                    const boardString = board.join("");

                    // Use the Sudoku.js library to validate the solution
                    const isValid = sudoku.solve(boardString) === boardString;

                    if (isValid) {
                        Swal.close(); // Reset current Swal
                        Swal.fire({
                            title: 'Please Wait !',
                            html: 'Checking answer...', // add html attribute if you want or remove
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading()
                            },
                        });
                        setTimeout(onGameComplete, 3000); // Trigger game completion logic after 3 seconds
                    }

                    return isValid;
                }

                // On game completion
                function onGameComplete() {
                    if (solved || gameCompleted) return;
                    solved = true;
                    gameCompleted = true;

                    const timeTaken = timerDisplay.textContent.replace('Time: ', ''); // Get the timer value

                    // Stop the timer
                    stopTimer();

                    // Disable the game board
                    sudokuBoard.style.pointerEvents = "none";

                    // Send AJAX request to update the winner in the database
                    fetch("game.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ action: 'update_winner', game_id: gameId, user_id: userId }),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Optionally, you can show a success message here
                            } else {
                                Swal.fire("Error", data.error || "Failed to update the winner.", "error");
                                solved = false;
                            }
                        })
                        .catch(() => {
                            Swal.fire("Error", "Failed to update the winner.", "error");
                            solved = false;
                        });
                }

                // Function to fetch and load the current puzzle pattern from the server
                function fetchAndLoadPuzzle() {
                    fetch(`api/fetch_game_pattern.php?game_id=${gameId}&user_id=${userId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.pattern) {
                                const initialPattern = data.pattern;
                                const playerProgress = data.progress || '.................................................................................'; // 81 dots
                                renderBoard(initialPattern, playerProgress); // Pass both initial pattern and progress
                                startTimer(data.start_time); // Start timer using server's start time
                            } else {
                                console.error("Failed to fetch game pattern:", data.error);
                            }
                        })
                        .catch(error => {
                            console.error("Error fetching game pattern:", error);
                        });
                }



                // Poll the game status to check for completion or start
                function pollGameStatus() {
                    fetch(`api/fetch_game_status.php?game_id=${gameId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (data.status === 'completed') {
                                    gameCompleted = true; // Set the flag to prevent reload confirmation (for player that lose)
                                    // Reset swal
                                    Swal.close();
                                    window.location.reload();
                                } else if (data.status === 'started' && gameContent.style.display === 'none') {
                                    window.location.reload();
                                }
                            }
                        })
                        .catch(error => {
                            console.error("Error fetching game status:", error);
                        });
                }

                // Function to fetch and display player progress
                function updatePlayerProgress() {
                    fetch(`api/fetch_player_progress.php?game_id=${gameId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.players) {
                                let html = '';
                                let colorIndex = 0;
                                const colorClasses = ['is-info', 'is-success', 'is-warning', 'is-danger', 'is-link', 'is-primary'];
                                data.players.forEach(player => {
                                    html += `<li>
                                                    <strong>${player.username}:</strong>
                                                    <div class="progress-container">
                                                        <progress class="progress ${colorClasses[colorIndex % colorClasses.length]}" value="${(<?= $initial_blanks ?> - player.blanks_remaining)}" max="<?= $initial_blanks ?>"></progress>
                                                        <div class="progress-text">${player.blanks_remaining} blanks remaining</div>
                                                    </div>
                                                 </li>`;
                                    colorIndex++;
                                });
                                progressDisplay.innerHTML = html;
                            } else {
                                console.error("Failed to fetch player progress:", data.error);
                            }
                        })
                        .catch(error => {
                            console.error("Error fetching player progress:", error);
                        });
                }

                // Function to update player progress on the server
                function updateProgress(cellIndex, value) {
                    const cells = sudokuBoard.querySelectorAll("input");
                    const row = Math.floor(cellIndex / 9);
                    const col = cellIndex % 9;

                    // Validate the input before updating progress
                    const isValid = validateInput(row, col, value);
                    if (!isValid) {
                        showToast("Invalid value. Fix the error before continuing.", "error");
                        return;
                    }

                    fetch("game.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ action: 'update_progress', game_id: gameId, cell_index: cellIndex, value: value }),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                showToast(data.error || "Failed to update progress.", "error");
                            }
                        })
                        .catch(() => {
                            showToast("Failed to update progress.", "error");
                        });
                }


                // Handle leaving the game
                if (leaveGameButton) {
                    leaveGameButton.addEventListener("click", () => {
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "Do you want to leave the game?",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, leave!',
                            // button confirm button color is set to 'danger'
                            confirmButtonColor: '#dc3545',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch("api/leave_game.php", {
                                    method: "POST",
                                    headers: { "Content-Type": "application/json" },
                                    body: JSON.stringify({ game_id: gameId, user_id: userId })
                                })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            Swal.fire("Left Game", data.message, "success").then(() => {
                                                window.location.href = "lobby.php?message=game_left";
                                            });
                                        } else {
                                            Swal.fire("Error", data.error || "Failed to leave the game.", "error");
                                        }
                                    })
                                    .catch(() => {
                                        Swal.fire("Error", "Failed to leave the game.", "error");
                                    });
                            }
                        });
                    });
                }

                // Show confirmation dialog before leaving the page
                window.addEventListener("beforeunload", function (event) {
                    if (!gameCompleted) { // Only prompt if the game is not completed
                        event.preventDefault();
                        event.returnValue = ''; // This triggers the browser's default confirmation dialog
                    }
                }, { capture: true });


                // Initialize the game
                function initializeGame() {
                    fetchAndLoadPuzzle();
                    updatePlayerProgress();
                    updatePlayerListWithToasts();

                    setInterval(pollGameStatus, 5000); // Check game status every 5 seconds
                    setInterval(updatePlayerProgress, 5000); // Auto-refresh player progress every 5 seconds
                    setInterval(updatePlayerListWithToasts, 5000); // Auto-refresh player list every 5 seconds
                }

                // Function to update the player list with toast notifications
                function updatePlayerListWithToasts() {
                    fetch(`api/fetch_player_list.php?game_id=${gameId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.players) {
                                let html = '';
                                data.players.forEach(player => {
                                    html += `<li>${player.username}${player.user_id == <?= json_encode($game['owner_id']) ?> ? " (Owner)" : ""}</li>`;
                                });
                                playerList.innerHTML = html;

                                // Extract current player IDs
                                const currentPlayerIds = data.players.map(player => player.user_id);

                                if (previousPlayerIds.length > 0) { // Ensure this isn't the initial load
                                    // Determine joined players, excluding the current user
                                    const joinedPlayers = data.players.filter(player =>
                                        !previousPlayerIds.includes(player.user_id) && player.user_id !== userId
                                    );

                                    // Determine left players, excluding the current user
                                    const leftPlayerIds = previousPlayerIds.filter(id =>
                                        !currentPlayerIds.includes(id) && id !== userId
                                    );
                                    const leftPlayers = leftPlayerIds.map(id => previousPlayerMap[id]).filter(name => name); // Get usernames

                                    // Show toast for joined players
                                    if (joinedPlayers.length > 0) {
                                        const joinedNames = joinedPlayers.map(player => player.username).join(', ');
                                        const formattedJoinedNames = capitalizeWords(joinedNames);
                                        if (!gameCompleted) { // Only show toast if the game is not completed
                                            showToast(`${formattedJoinedNames} has joined the game.`, 'info');
                                        }
                                    }

                                    // Show toast for left players
                                    if (leftPlayers.length > 0) {
                                        const leftNames = leftPlayers.join(', ');
                                        const formattedLeftNames = capitalizeWords(leftNames);
                                        if (!gameCompleted) { // Only show toast if the game is not completed
                                            showToast(`${formattedLeftNames} has left the game.`, 'info');
                                        }
                                    }
                                }

                                // Update previousPlayerIds and previousPlayerMap
                                previousPlayerIds = currentPlayerIds;
                                previousPlayerMap = {};
                                data.players.forEach(player => {
                                    previousPlayerMap[player.user_id] = player.username;
                                });
                            }
                        })
                        .catch(error => {
                            console.error("Error fetching player list:", error);
                        });
                }

                initializeGame();
            });
        </script>
    <?php endif; ?>

    <?php if ($game['status'] === 'waiting'): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const gameId = <?= json_encode($game_id) ?>;
                const isOwner = <?= json_encode($is_owner) ?>;
                const userId = <?= json_encode($user_id) ?>; // Get the current user's ID
                const playerList = document.getElementById("player-list");
                const startGameButton = document.getElementById('start-game');
                const waitingScreen = document.getElementById("waiting-screen");
                const leaveGameButton = document.getElementById("leave-game-main");

                // Initialize previousPlayers and a map for user_id to username
                let previousPlayerIds = [];
                let previousPlayerMap = {};

                // Function to check game status
                function checkGameStatus() {
                    fetch(`api/fetch_game_status.php?game_id=${gameId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (data.status === 'started') {
                                    // If the game has started, reload the page or dynamically load the game content
                                    window.location.reload(); // Reload the page to show the started game
                                }
                            } else {
                                console.error("Failed to fetch game status:", data.error);
                            }
                        })
                        .catch(error => {
                            console.error("Error checking game status:", error);
                        });
                }

                // Poll the game status every 1 second
                setInterval(checkGameStatus, 1000);

                // Function to show SweetAlert2 toast messages
                function showToast(message, icon = 'error') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: icon, // Options: 'success', 'error', 'warning', 'info', 'question'
                        title: message,
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer)
                            toast.addEventListener('mouseleave', Swal.resumeTimer)
                        }
                    });
                }

                // Function to fetch and update the player list
                function updatePlayerList() {
                    fetch(`api/fetch_player_list.php?game_id=${gameId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.players) {
                                let html = '';
                                data.players.forEach(player => {
                                    html += `<li class="is-capitalized has-text-weight-semibold">${player.username}${player.user_id == <?= json_encode($game['owner_id']) ?> ? " <span class='has-text-info'>(Owner)</span>" : ""}</li>`;
                                });
                                playerList.innerHTML = html;

                                // Extract current player IDs
                                const currentPlayerIds = data.players.map(player => player.user_id);

                                if (previousPlayerIds.length > 0) { // Ensure this isn't the initial load
                                    // Determine joined players, excluding the current user
                                    const joinedPlayers = data.players.filter(player =>
                                        !previousPlayerIds.includes(player.user_id) && player.user_id !== userId
                                    );

                                    // Determine left players, excluding the current user
                                    const leftPlayerIds = previousPlayerIds.filter(id =>
                                        !currentPlayerIds.includes(id) && id !== userId
                                    );
                                    const leftPlayers = leftPlayerIds.map(id => previousPlayerMap[id]).filter(name => name); // Get usernames

                                    // Show toast for joined players
                                    if (joinedPlayers.length > 0) {
                                        const joinedNames = joinedPlayers.map(player => player.username).join(', ');
                                        const formattedJoinedNames = capitalizeWords(joinedNames);
                                        showToast(`${formattedJoinedNames} has joined the game.`, 'info');
                                    }

                                    // Show toast for left players
                                    if (leftPlayers.length > 0) {
                                        const leftNames = leftPlayers.join(', ');
                                        const formattedLeftNames = capitalizeWords(leftNames);
                                        showToast(`${formattedLeftNames} has left the game.`, 'info');
                                    }
                                }

                                // Update previousPlayerIds and previousPlayerMap
                                previousPlayerIds = currentPlayerIds;
                                previousPlayerMap = {};
                                data.players.forEach(player => {
                                    previousPlayerMap[player.user_id] = player.username;
                                });
                            }
                        })
                        .catch(error => {
                            console.error("Error fetching player list:", error);
                        });
                }


                // Update the new owner waiting screen with a start game button
                function updateWaitingScreen() {
                    fetch(`api/fetch_game_owner.php?game_id=${gameId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.owner_id == userId && !isOwner) {
                                setTimeout(() => {
                                    window.location.reload();
                                }, 3000);
                            }
                        })
                        .catch(error => {
                            console.error("Error fetching game owner:", error);
                        });
                }

                // Check if the owner changed and update the new owner waiting screen
                setInterval(updateWaitingScreen, 5000);

                // Poll the player list every 5 seconds
                setInterval(updatePlayerList, 5000);

                // Initial fetch to set previousPlayerIds and previousPlayerMap
                updatePlayerList();

                // Handle starting the game
                if (startGameButton) {
                    startGameButton.addEventListener('click', () => {
                        Swal.fire({
                            title: 'Start the Game?',
                            text: "Are you sure you want to start the game?",
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, start!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Send AJAX request to start_game.php
                                fetch("api/start_game.php", {
                                    method: "POST",
                                    headers: { "Content-Type": "application/json" },
                                    body: JSON.stringify({ game_id: gameId }),
                                })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            Swal.fire("Game Started!", "The game has begun.", "success");
                                            window.location.reload();
                                        } else {
                                            Swal.fire("Error", data.error || "Failed to start the game.", "error");
                                        }
                                    })
                                    .catch(() => {
                                        Swal.fire("Error", "Failed to start the game.", "error");
                                    });
                            }
                        });
                    });
                }

                // Handle leaving the game
                if (leaveGameButton) {
                    leaveGameButton.addEventListener("click", () => {
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "Do you want to leave the game?",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, leave!',
                            // button confirm button color is set to 'danger'
                            confirmButtonColor: '#dc3545',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch("api/leave_game.php", {
                                    method: "POST",
                                    headers: { "Content-Type": "application/json" },
                                    body: JSON.stringify({ game_id: gameId, user_id: userId })
                                })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            Swal.fire("Left Game", data.message, "success").then(() => {
                                                window.location.href = "lobby.php?message=game_left";
                                            });
                                        } else {
                                            Swal.fire("Error", data.error || "Failed to leave the game.", "error");
                                        }
                                    })
                                    .catch(() => {
                                        Swal.fire("Error", "Failed to leave the game.", "error");
                                    });
                            }
                        });
                    });
                }
            });
        </script>
    <?php endif; ?>

    <?php if ($game['status'] === 'completed'): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const winnerId = <?= json_encode($game['winner_id']) ?>; // Get the winner ID from PHP
                const currentUserId = <?= json_encode($user_id) ?>; // Get the current user's ID
                const winnerName = <?= json_encode($winner) ?>; // Get the winner's username
                const timeTaken = <?= json_encode($time_taken) ?>; // Get the time taken to complete the game

                // Check if the current user is the winner
                if (winnerId && winnerId === currentUserId) {
                    // Show SweetAlert2 modal with confetti animation for the winner
                    Swal.fire({
                        title: 'Congratulations!',
                        html: `<p>You are the winner of the game!</p>
                                                   <p><strong>Time Taken:</strong> ${timeTaken}</p>`,
                        imageUrl: 'https://media.giphy.com/media/26AHONQ79FdWZhAI0/giphy.gif', // Example celebration GIF
                        imageWidth: 400,
                        imageHeight: 200,
                        imageAlt: 'Celebration!',
                        showConfirmButton: true,
                        confirmButtonText: 'Awesome!',
                        willClose: () => {
                            // Trigger confetti animation
                            confetti({
                                particleCount: 200,
                                spread: 70,
                                origin: { y: 0.6 }
                            });
                        }
                    });
                }

                // Handle the "Return to Lobby" button click
                document.getElementById('return-to-lobby')?.addEventListener('click', () => {
                    window.location.href = "lobby.php?message=game_completed";
                });
            });
        </script>
    <?php endif; ?>

</body>

</html>
<!-- this is the best script -->