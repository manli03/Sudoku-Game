<?php
// /multiplayer/lobby.php
session_start();
require '../db.php'; // Include database connection

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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
    // Redirect to profile.php with a message
    header("Location: ../profile.php?message=complete_profile");
    exit();
}

// Check if the user is already in a game
$player_stmt = $db->prepare("SELECT game_id FROM multiplayer_players WHERE user_id = :user_id");
$player_stmt->execute(['user_id' => $user_id]);
$current_game = $player_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Join the Multiplayer Sudoku Lobby to challenge other players and enjoy Sudoku together.">
    <title>Multiplayer Sudoku Lobby</title>

    <!-- Custom Styles -->
    <style>
        /* Style for the lobby container to allow horizontal scrolling */
        .lobby-container {
            overflow-x: auto;
        }
    </style>
</head>

<body>
    <!-- Include the Navigation Bar -->
    <?php include '../navbar.php'; ?>
    <section class="section">
        <div class="container">
            <h1 class="title has-text-centered">Sudoku Multiplayer Lobby</h1>

            <!-- Lobby Data -->
            <div id="lobby-container" class="box">
                <!-- Lobby data (games, players) will be dynamically updated here -->
            </div>

            <!-- Action Buttons -->
            <div class="box has-text-centered">
                <?php if ($current_game): ?>
                    <!-- Button to rejoin current game -->
                    <a href="game.php?game_id=<?= htmlspecialchars($current_game) ?>" class="button is-info">Rejoin Current
                        Game</a>
                <?php else: ?>
                    <!-- Button to create a new game -->
                    <button id="create-game-btn" class="button is-primary">Create New Game</button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- JavaScript for Lobby Functionality -->
    <script>
        const gameButton = document.getElementById('create-game-btn');
        /**
         * Show toast notifications using SweetAlert2
         * @param {string} message - The message to display
         * @param {string} [icon='error'] - The icon type ('success', 'error', 'warning', 'info', 'question')
         */
        function showToast(message, icon = 'error') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: icon, // 'success', 'error', 'warning', 'info', 'question'
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
        }

        // Display messages based on URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        if (message) {
            const messages = {
                'already_in_game': ['You are already in a game.', 'warning'],
                'game_not_joinable': ['This game cannot be joined.', 'error'],
                'game_created': ['New game created successfully!', 'success'],
                'game_left': ['You have left the game.', 'info'],
                'missing_game_id': ['You can join a game or create a new one.', 'info'],
                'game_not_found': ['Game not found.', 'error'],
                'error_joining_game': ['Error joining the game.', 'error'],
                'game_completed': ['Game completed successfully!', 'success']
            };

            if (message.includes('not_in_game')) {
                const gameId = message.split('_').pop();
                showToast(`You are not in game with ID ${gameId}.`, 'info');
            } else if (messages[message]) {
                showToast(messages[message][0], messages[message][1]);
            }
        }

        // Show message to return to current game if applicable
        <?php if (isset($_SESSION['joined_game']) && $_SESSION['joined_game'] === true): ?>
            showToast("You can return to current game by using the \'Rejoin Current Game\' button.", 'info');
        <?php endif; ?>

        /**
         * Fetch and update lobby data from the server
         */
        function fetchLobbyData() {
            fetch("api/fetch_lobby_data.php")
                .then(response => response.json())
                .then(data => {
                    const lobbyContainer = document.getElementById("lobby-container");
                    if (data.success) {
                        const currentGameId = data.current_game;
                        let html = `
                            <h2 class="subtitle has-text-weight-bold has-text-info-dark">Available Games</h2>
                            <div class="table-container">
                                <table class="table is-fullwidth is-striped">
                                    <thead>
                                        <tr>
                                            <th>Game ID</th>
                                            <th>Owner</th>
                                            <th>Players</th>
                                            <th>Difficulty</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        if (data.games.length > 0) {
                            data.games.forEach(game => {
                                const isCurrentGame = currentGameId && currentGameId == game.id;
                                const max_players = 4; // Fixed maximum players
                                const joinable = (game.player_count < max_players) && (game.status !== 'completed');
                                const started = game.status === 'started';

                                html += `
                                    <tr>
                                        <td>${game.id}</td>
                                        <td>${game.owner_name}</td>
                                        <td>${game.player_count}/${max_players}</td>
                                        <td>${game.difficulty ? capitalizeFirstLetter(game.difficulty) : 'N/A'}</td>
                                        <td>${game.status ? capitalizeFirstLetter(game.status) : 'N/A'}</td>
                                        <td>
                                            ${isCurrentGame
                                        ? `<button class="button is-danger is-small leave-game-btn" data-game-id="${game.id}">Leave</button>`
                                        : started
                                            ? `<button class="button is-static is-small" disabled>Game Started</button>`
                                            : joinable
                                                ? `<button class="button is-primary is-small join-game-btn" data-game-id="${game.id}">Join</button>`
                                                : `<button class="button is-static is-small" disabled>Already Full</button>`}
                                        </td>
                                    </tr>
                                `;
                            });
                        } else {
                            html += `
                                <tr>
                                    <td colspan="6" class="has-text-centered">No games available. Create a new game to start!</td>
                                </tr>
                            `;
                        }

                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                        lobbyContainer.innerHTML = html;

                        // Attach event listeners to Join and Leave buttons
                        document.querySelectorAll('.join-game-btn').forEach(button => {
                            button.addEventListener('click', () => {
                                const gameId = button.dataset.gameId;
                                joinGame(gameId);
                            });
                        });

                        document.querySelectorAll('.leave-game-btn').forEach(button => {
                            button.addEventListener('click', () => {
                                const gameId = button.dataset.gameId;
                                leaveGame(gameId);
                            });
                        });
                    } else {
                        lobbyContainer.innerHTML = `<p class="has-text-centered">Error fetching lobby data. Please try again later.</p>`;
                        showToast("Error fetching lobby data. Please try again later.", 'error');
                    }
                })
                .catch(err => {
                    console.error("Error fetching lobby data:", err);
                    showToast("Error fetching lobby data. Please try again later.", 'error');
                });
        }

        /**
         * Capitalize the first letter of a string
         * @param {string} string - The string to capitalize
         * @returns {string} - The capitalized string
         */
        function capitalizeFirstLetter(string) {
            if (typeof string !== 'string') return 'N/A';
            return string.charAt(0).toUpperCase() + string.slice(1);
        }

        /**
         * Join a game with the specified game ID
         * @param {string} gameId - The ID of the game to join
         */
        function joinGame(gameId) {
            Swal.fire({
                title: 'Confirm Join',
                text: "Are you sure you want to join this game?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, join!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send AJAX request to join_game.php
                    fetch("api/join_game.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ game_id: gameId }),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Change the button to "Rejoin Current Game"
                                gameButton.removeEventListener('click', createNewGame);
                                gameButton.textContent = 'Rejoin Current Game';
                                gameButton.className = 'button is-info';
                                showToast("Joined the game successfully!", 'success');
                                gameButton.onclick = () => {
                                    window.location.href = `game.php?game_id=${data.game_id}`;
                                };
                                // Wait for 3 seconds and redirect to the game
                                setTimeout(() => {
                                    window.location.href = `game.php?game_id=${data.game_id}`;
                                }, 3000);
                            } else {
                                showToast(data.error || "Failed to join the game.", 'error');
                            }
                        })
                        .catch(() => {
                            showToast("Failed to join the game due to a server error.", 'error');
                        });
                }
            });
        }

        /**
         * Leave a game with the specified game ID
         * @param {string} gameId - The ID of the game to leave
         */
        function leaveGame(gameId) {
            Swal.fire({
                title: 'Confirm Leave',
                text: "Are you sure you want to leave the game?",
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
                        body: JSON.stringify({ game_id: gameId }),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast(data.message, 'success');
                                window.location.href = "lobby.php?message=game_left";
                            } else {
                                showToast(data.error || "Failed to leave the game.", 'error');
                            }
                        })
                        .catch(() => {
                            showToast("Failed to leave the game due to a server error.", 'error');
                        });
                }
            });
        }

        /**
         * Create a new game with a selected difficulty
         */
        function createNewGame() {
            Swal.fire({
                title: 'Select Difficulty',
                input: 'select',
                inputOptions: {
                    'easy': 'Easy',
                    'medium': 'Medium',
                    'hard': 'Hard',
                    'expert': 'Expert',
                    'master': 'Master',
                    'extreme': 'Extreme'
                },
                inputPlaceholder: 'Select a difficulty level',
                showCancelButton: true,
                confirmButtonText: 'Create',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    return new Promise((resolve) => {
                        if (value) {
                            resolve();
                        } else {
                            resolve('You need to select a difficulty level!');
                        }
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const difficulty = result.value;

                    // Generate puzzle using sudoku.js based on selected difficulty
                    // Ensure that sudoku.js has a generate function that returns an 81-character puzzle string
                    let puzzle = sudoku.generate(difficulty, true); // Assuming the generate function can take difficulty

                    if (!puzzle || puzzle.length !== 81) {
                        showToast("Failed to generate puzzle. Please try again.", 'error');
                        return;
                    }

                    // Send the generated puzzle and difficulty to the server to create the game
                    fetch("api/create_game.php", {
                        method: "POST",
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ difficulty: difficulty, puzzle: puzzle }),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast("Game created successfully!", 'success');
                                // Change the button to "Rejoin Current Game"
                                gameButton.removeEventListener('click', createNewGame);
                                gameButton.textContent = 'Rejoin Current Game';
                                gameButton.className = 'button is-info';
                                gameButton.onclick = () => {
                                    window.location.href = `game.php?game_id=${data.game_id}`;
                                };
                                // Wait for 3 seconds and redirect to the game
                                setTimeout(() => {
                                    window.location.href = `game.php?game_id=${data.game_id}`;
                                }, 3000);
                            } else {
                                showToast(data.error || "Failed to create game.", 'error');
                            }
                        })
                        .catch(() => {
                            showToast("Failed to create game due to a server error.", 'error');
                        });
                }
            });
        }

        // Attach event listener to Create Game button
        gameButton.addEventListener('click', createNewGame);


        // Initial Fetch and Auto-Refresh
        setInterval(fetchLobbyData, 5000); // Refresh lobby data every 5 seconds
        fetchLobbyData(); // Initial fetch on page load
    </script>
</body>

</html>