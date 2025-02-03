<?php
session_start();
include 'db.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="View and filter your Sudoku game results.">
    <title>Game Results</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <!-- DataTables CSS for Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: radial-gradient(circle farthest-side at 0% 50%, #ffffff 23.5%, rgba(255, 170, 0, 0) 0) 21px 30px,
                radial-gradient(circle farthest-side at 0% 50%, #f5f5f5 24%, rgba(240, 166, 17, 0) 0) 19px 30px,
                linear-gradient(#ffffff 14%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 85%, #ffffff 0) 0 0,
                linear-gradient(150deg, #ffffff 24%, #f5f5f5 0, #f5f5f5 26%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 74%, #f5f5f5 0, #f5f5f5 76%, #ffffff 0) 0 0,
                linear-gradient(30deg, #ffffff 24%, #f5f5f5 0, #f5f5f5 26%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 74%, #f5f5f5 0, #f5f5f5 76%, #ffffff 0) 0 0,
                linear-gradient(90deg, #f5f5f5 2%, #ffffff 0, #ffffff 98%, #f5f5f5 0%) 0 0 #ffffff;
            background-size: 40px 60px;
            font-family: 'Arial', sans-serif;
            color: white;
            min-height: 100vh;
            overflow-x: auto;
            margin-top: 50px;
            margin-bottom: 50px;
            padding-top: 3rem;
        }

        .container {
            background: white;
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            margin-bottom: 20px;
            color: #363636;
            max-width: 1000px;
            background-color: #f5f5f5;
        }

        .id-badge {
            background-color: #00d1b2;
            color: white;
            border-radius: 5px;
            padding: 2px 8px;
            font-size: 12px;
            margin-left: 10px;
            font-weight: bold;
        }

        .sudoku-board {
            display: grid;
            grid-template-columns: repeat(9, 40px);
            grid-template-rows: repeat(9, 40px);
            gap: 2px;
            justify-content: center;
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

        .sudoku-board input.blank {
            background-color: rgb(125, 255, 169);
            color: #000;
        }

        @media screen and (max-width: 576px) {
            .container {
                max-width: 100%;
            }

            .sudoku-board {
                grid-template-columns: repeat(9, 30px);
                /* Reduce cell size */
                grid-template-rows: repeat(9, 30px);
                /* Reduce cell size */
                gap: 1px;
                /* Smaller gap between cells */
                justify-content: center;
            }

            .sudoku-board input {
                width: 30px;
                /* Adjust width for smaller cells */
                height: 30px;
                /* Adjust height for smaller cells */
                font-size: 14px;
                /* Smaller font for mobile view */
            }
        }

        .player-name {
            text-transform: capitalize;
            font-weight: bold;
        }

        .game-ended-message {
            color: red;
            font-weight: bold;
            margin-top: 10px;
        }

        .modal-content {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>

    <div class="container">
        <!-- Include the Navigation Bar -->
        <?php include 'navbar-1.php'; ?>
        <!-- Multiplayer Games -->
        <h1 class="text-center">Multiplayer Game History</h1>
        <div class="table-responsive">
            <table id="multiplayer-games-table" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Game ID</th>
                        <th>Difficulty</th>
                        <th>Status</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Game Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch multiplayer games, winner, and owner
                    $sql = "
                        SELECT 
                            mg.id AS game_id, 
                            mg.pattern, 
                            mg.status, 
                            mg.difficulty, 
                            mg.start_time, 
                            mg.end_time, 
                            mg.owner_id,
                            mg.winner_id, 
                            u.username AS winner_name,
                            o.username AS owner_name
                        FROM multiplayer_games mg
                        LEFT JOIN users u ON mg.winner_id = u.id
                        LEFT JOIN users o ON mg.owner_id = o.id
                        WHERE mg.owner_id = :user_id OR mg.id IN (SELECT game_id FROM game_results WHERE user_id = :user_id)
                    ";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(['user_id' => $user_id]);

                    if ($stmt->rowCount() > 0) {
                        foreach ($stmt as $row) {
                            echo "<tr>";
                            echo "<td>{$row['game_id']}</td>";
                            echo "<td>" . ucfirst($row['difficulty']) . "</td>";
                            echo "<td>" . ucfirst($row['status']) . "</td>";
                            echo "<td>" . ($row['start_time'] ? $row['start_time'] : 'N/A') . "</td>";
                            echo "<td>" . ($row['end_time'] ? $row['end_time'] : 'N/A') . "</td>";
                            echo "<td>
                                    <button class='btn btn-info btn-sm view-players' data-bs-toggle='modal' 
                                            data-bs-target='#playersModal' 
                                            data-game-id='{$row['game_id']}' 
                                            data-pattern='{$row['pattern']}' 
                                            data-owner-name='{$row['owner_name']}'
                                            data-owner-id='{$row['owner_id']}'
                                            data-winner-name='{$row['winner_name']}'
                                            data-winner-id='{$row['winner_id']}'
                                            data-time-taken='" . ($row['end_time'] ? strtotime($row['end_time']) - strtotime($row['start_time']) : 'N/A') . "'
                                            data-status='{$row['status']}'>
                                        View Details
                                    </button>
                                </td>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Solo Game History -->
    <div class="container">
        <h1 class="text-center">Solo Game History</h1>
        <div class="table-responsive">
            <table id="solo-games-table" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Game ID</th>
                        <th>Difficulty</th>
                        <th>Time Taken</th>
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch solo game history for the user
                    $sql = "
                        SELECT id, difficulty, time, status, created_at 
                        FROM games 
                        WHERE user_id = :user_id
                    ";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(['user_id' => $user_id]);

                    if ($stmt->rowCount() > 0) {
                        foreach ($stmt as $row) {
                            echo "<tr>";
                            echo "<td>{$row['id']}</td>";
                            echo "<td>" . ucfirst($row['difficulty']) . "</td>";
                            echo "<td>{$row['time']}</td>";
                            echo "<td>" . ucfirst($row['status']) . "</td>";
                            echo "<td>{$row['created_at']}</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for Players -->
    <div class="modal fade" id="playersModal" tabindex="-1" aria-labelledby="playersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="playersModalLabel">Game Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 class="game-ended-message" id="game-message"></h6>
                    <h5 style="color: #000;" class="player-name">Game Creator: <span id="owner-name"></span></h5>
                    <div id="winner-and-time-taken" style="display: none;">
                        <h5 class="player-name text-primary">Winner: <span id="winner-name"></span></h5>
                        <h5 class="player-name text-primary">Time Taken: <span id="time-taken"></span></h5>
                    </div>
                    <h6 style="color: #000;">Initial Puzzle Pattern:</h6>
                    <div class="sudoku-board" id="sudoku-board"></div>
                    <h6 style="color: #000;" class="mt-3">Players:</h6>
                    <ul id="players-list" class="list-group"></ul>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- DataTables JS for Bootstrap 5 -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('playersModal');
            const ownerNameElement = document.getElementById('owner-name');
            const sudokuBoard = document.getElementById('sudoku-board');
            const playersList = document.getElementById('players-list');
            const gameMessage = document.getElementById('game-message');

            // Initialize DataTables
            $('#multiplayer-games-table').DataTable({
                "order": [[0, "asc"]], // Order by Game ID ascending
                "pageLength": 10,
                "columnDefs": [
                    {
                        "orderable": false, // Disable sorting
                        "targets": 5        // Targeting the 6th column (0-based index)
                    }
                ],
                "language": {
                    "emptyTable": "No multiplayer games available."
                }
            });

            $('#solo-games-table').DataTable({
                "order": [[0, "asc"]], // Order by Game ID ascending
                "pageLength": 10,
                "language": {
                    "emptyTable": "No solo games available."
                }
            });

            document.querySelectorAll('.view-players').forEach(button => {
                button.addEventListener('click', () => {
                    const gameId = button.getAttribute('data-game-id');
                    const pattern = button.getAttribute('data-pattern');
                    const ownerName = button.getAttribute('data-owner-name');
                    const ownerId = button.getAttribute('data-owner-id');
                    const winnerName = button.getAttribute('data-winner-name');
                    const winnerId = button.getAttribute('data-winner-id');
                    const timeTaken = button.getAttribute('data-time-taken');
                    const status = button.getAttribute('data-status');

                    const currentUserId = <?php echo json_encode($user_id); ?>;

                    // Display owner name with (You) if the logged-in user is the creator
                    document.getElementById('owner-name').textContent =
                        ownerName + (parseInt(ownerId) === currentUserId ? ' (You)' : '');

                    // Display winner name with (You) if the logged-in user is the winner
                    if (status === 'completed') {
                        document.getElementById('winner-and-time-taken').style.display = 'block';
                        document.getElementById('winner-name').textContent =
                            winnerName + (parseInt(winnerId) === currentUserId ? ' (You)' : '');

                        if (timeTaken && timeTaken !== 'N/A') {
                            const minutes = Math.floor(timeTaken / 60);
                            const seconds = timeTaken % 60;
                            document.getElementById('time-taken').textContent = `${minutes} min ${seconds} sec`;
                        } else {
                            document.getElementById('time-taken').textContent = "N/A";
                        }
                    } else {
                        document.getElementById('winner-and-time-taken').style.display = 'none';
                    }

                    // Display game-ended message if applicable
                    document.getElementById('game-message').textContent = status === 'ended' ? 'Game ended because all players left.' : '';

                    // Render Sudoku board
                    const sudokuBoard = document.getElementById('sudoku-board');
                    sudokuBoard.innerHTML = '';
                    if (pattern) {
                        pattern.split('').forEach(cell => {
                            const input = document.createElement('input');
                            input.value = cell === '.' ? '' : cell;
                            input.disabled = true;
                            if (cell === '.') {
                                input.classList.add('blank');
                            }
                            sudokuBoard.appendChild(input);
                        });
                    }

                    // Fetch players and append "(You)" for the logged-in user
                    fetch(`fetch_players.php?game_id=${gameId}`)
                        .then(response => response.json())
                        .then(data => {
                            playersList.innerHTML = '';
                            if (data.length > 0) {
                                data.forEach(player => {
                                    const isCurrentUser = parseInt(player.user_id) === currentUserId;
                                    playersList.innerHTML += `
                                <li class="list-group-item">
                                    <span class="player-name">${player.username}${isCurrentUser ? ' (You)' : ''}</span>
                                    <span class="id-badge">ID: ${player.user_id}</span>
                                </li>`;
                                });
                            } else {
                                playersList.innerHTML = '<li class="list-group-item">No players found.</li>';
                            }
                        });
                });
            });
        });
    </script>
</body>

</html>