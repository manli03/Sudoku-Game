<?php
session_start();
require '../db.php';

// Check if user is logged in
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

// Continue with the dashboard content
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Play Solo Sudoku game with interactive features and challenges.">
    <title>Sudoku Game</title>

    <style>
        .invalid {
            background-color: #ffcccc !important;
            /* Red for invalid cells */
        }
    </style>
</head>

<body style="background-color: #f9f9f9; overflow-x: auto;">
    <!-- Include the Navigation Bar -->
    <?php include '../navbar.php'; ?>
    <div style="font-family: Arial, sans-serif; margin: 0; padding: 0;">
        <h1 class="title has-text-centered" style="color: #2c3e50; margin-top: 20px;">Sudoku Game</h1>
        <div id="controls"
            style="text-align: center; margin-bottom: 20px; padding: 10px; background-color: #ecf0f1; border-radius: 10px; max-width: 500px; margin: 20px auto;">
            <button id="generate"
                style="padding: 10px 20px; margin: 5px; font-size: 16px; border: none; border-radius: 5px; background-color: #3498db; color: white; cursor: pointer;">Generate
                Puzzle</button>
            <button id="restart"
                style="padding: 10px 20px; margin: 5px; font-size: 16px; border: none; border-radius: 5px; background-color: #e67e22; color: white; cursor: pointer;">Restart
                Puzzle</button>
            <button id="clear"
                style="padding: 10px 20px; margin: 5px; font-size: 16px; border: none; border-radius: 5px; background-color: #e74c3c; color: white; cursor: pointer;">Clear</button>
            <div style="margin-top: 10px;">
                <label for="difficulty" style="font-size: 16px; font-weight: bold; color: #2c3e50;">Difficulty:</label>
                <select id="difficulty"
                    style="padding: 5px 10px; font-size: 16px; border-radius: 5px; border: 1px solid #ddd; color: #34495e;">
                    <option value="easy">Easy</option>
                    <option value="medium">Medium</option>
                    <option value="hard">Hard</option>
                    <option value="expert">Expert</option>
                    <option value="master">Master</option>
                    <option value="extreme">Extreme</option>
                </select>
            </div>
            <p id="timer" style="margin-top: 10px; font-size: 18px; font-weight: bold; color: #34495e;">Time: 00:00</p>
        </div>
        <div id="sudoku-board"
            style="display: grid; grid-template-columns: repeat(9, 1fr); gap: 2px; max-width: 360px; margin: 0 auto; justify-content: center;">
        </div>
        <div id="history"
            style="margin: 20px auto; max-width: 600px; background-color: #ecf0f1; padding: 20px; border-radius: 10px; justify-content: center;">
            <h2 style="text-align: center; color: #2c3e50; margin-bottom: 20px;">Game History</h2>

            <div class="table-container" style="border-radius: 10px;">
                <table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
                    <thead style="background-color: #bdc3c7; color: #2c3e50;">
                        <tr>
                            <th style="padding: 10px; border: 1px solid #ddd;">#</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Difficulty</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Time</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Status</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Last Played</th>
                        </tr>
                    </thead>
                    <tbody id="history-list" style="font-size: 14px;"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const sudokuBoard = document.getElementById("sudoku-board");
            const generateButton = document.getElementById("generate");
            const restartButton = document.getElementById("restart");
            const clearButton = document.getElementById("clear");
            const difficultySelector = document.getElementById("difficulty");
            const timerDisplay = document.getElementById("timer");
            const historyList = document.getElementById("history-list");

            const userId = <?php echo json_encode($user_id); ?>; // Define userId inside the listener

            let timer = null;
            let startTime = null;
            let elapsedTime = 0;
            let history = [];
            let currentPuzzle = ""; // Stores the current puzzle's initial state

            // Timer Functions
            function startTimer() {
                clearTimer(); // Clear any existing timer
                startTime = Date.now() - elapsedTime;
                timer = setInterval(updateTimer, 1000);
            }

            function updateTimer() {
                elapsedTime = Date.now() - startTime;
                const seconds = Math.floor(elapsedTime / 1000) % 60;
                const minutes = Math.floor(elapsedTime / (1000 * 60));
                timerDisplay.textContent = `Time: ${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
            }

            function stopTimer() {
                clearInterval(timer);
            }

            function clearTimer() {
                clearInterval(timer);
                timer = null;
                elapsedTime = 0;
                timerDisplay.textContent = "Time: 00:00";
            }

            // Global variable to keep track of disabled cells
            let disabledCells = new Set();

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

                    // Hide the toast message if there are no errors
                    // hideToast();
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
                        input.style.backgroundColor = "#ffe4b5"; // Light orange for cube
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

            // Attach event listener to validate input and highlight cells
            function createGrid() {
                sudokuBoard.innerHTML = "";
                for (let row = 0; row < 9; row++) {
                    for (let col = 0; col < 9; col++) {
                        const cell = document.createElement("input");
                        cell.type = "text";
                        cell.maxLength = 1;

                        cell.dataset.row = row;
                        cell.dataset.col = col;

                        // Style for Sudoku cells
                        cell.style.width = "40px";
                        cell.style.height = "40px";
                        cell.style.textAlign = "center";
                        cell.style.fontSize = "18px";
                        cell.style.fontFamily = "Arial, sans-serif";
                        cell.style.border = "1px solid #ddd";
                        cell.style.outline = "none";
                        cell.style.backgroundColor = "#fff";

                        // Add bold borders for 3x3 subgrid divisions
                        if (row % 3 === 0 && row !== 0) cell.style.borderTop = "2px solid black";
                        if (col % 3 === 0 && col !== 0) cell.style.borderLeft = "2px solid black";

                        // Allow only numbers 1-9 and validate input
                        cell.addEventListener("input", function () {
                            if (!/^[1-9]$/.test(cell.value)) {
                                cell.value = ""; // Clear invalid input
                            } else {
                                const value = cell.value;
                                validateInput(row, col, value);
                            }

                            // Clear error highlight if the user clears the cell
                            if (cell.value === "") {
                                validateInput(row, col, cell.value);
                            }

                            // Reapply all highlights on input change
                            reapplyHighlights(row, col);

                            checkIfGameComplete(); // Check if the game is complete after input
                        });

                        // Highlight related cells on focus
                        cell.addEventListener("focus", handleHighlight);
                        cell.addEventListener("blur", clearHighlight);

                        sudokuBoard.appendChild(cell);
                    }
                }
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

            // Load a Sudoku puzzle into the grid
            function loadPuzzle(puzzle) {
                const cells = sudokuBoard.querySelectorAll("input");
                for (let i = 0; i < 81; i++) {
                    cells[i].value = puzzle[i] === "." ? "" : puzzle[i];
                    cells[i].disabled = puzzle[i] !== "."; // Disable pre-filled cells
                    cells[i].style.color = puzzle[i] !== "." ? "#000" : "#555"; // Pre-filled cells in black, editable cells in gray
                }
                // Re-enable the game board
                sudokuBoard.style.pointerEvents = "auto";
            }

            // Generate a new Sudoku puzzle
            generateButton.addEventListener("click", function () {
                const difficulty = difficultySelector.value;
                const puzzle = sudoku.generate(difficulty);
                currentPuzzle = puzzle; // Save the puzzle's initial state
                loadPuzzle(puzzle);
                clearTimer();
                startTimer();
            });

            // Restart the current puzzle
            restartButton.addEventListener("click", function () {
                if (!currentPuzzle) {
                    alert("No puzzle to restart!");
                    return;
                }
                loadPuzzle(currentPuzzle);
                clearTimer();
                startTimer();
            });

            // Clear the grid
            clearButton.addEventListener("click", function () {
                const cells = sudokuBoard.querySelectorAll("input");
                cells.forEach(cell => {
                    cell.value = "";
                    cell.disabled = false;
                    cell.style.color = "#555";
                });
                stopTimer();
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
                    onGameComplete(); // Trigger game completion logic
                }

                return isValid;
            }

            // Save game to database and update history table
            function saveGameToHistory(difficulty, time, status) {
                fetch('save_game.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: userId, // Use the logged-in user's ID
                        difficulty: difficulty,
                        time: time,
                        status: status,
                    }),
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log("Game history saved successfully!");
                            // Add the latest game to the history table dynamically
                            const game = data.latest_game;
                            const row = document.createElement("tr");
                            row.innerHTML = `
                                <td style="padding: 10px; border: 1px solid #ddd;">${document.getElementById("history-list").childElementCount + 1}</td>
                                <td style="padding: 10px; border: 1px solid #ddd;">${game.difficulty}</td>
                                <td style="padding: 10px; border: 1px solid #ddd;">${game.time}</td>
                                <td style="padding: 10px; border: 1px solid #ddd;">${game.status}</td>
                                <td style="padding: 10px; border: 1px solid #ddd;">${game.last_played}</td>
                            `;
                            historyList.appendChild(row);
                        } else {
                            console.error("Failed to save game history:", data.error);
                        }
                    })
                    .catch(error => {
                        console.error("Error saving game history:", error);
                    });
            }

            // On game completion
            function onGameComplete() {
                const difficulty = difficultySelector.value; // Get the current difficulty
                const time = timerDisplay.textContent.replace('Time: ', ''); // Get the timer value
                const status = "completed"; // Mark the game as completed

                // Save to history and update the table
                saveGameToHistory(difficulty, time, status);

                // Stop the timer
                stopTimer();

                // Disable the game board
                sudokuBoard.style.pointerEvents = "none";

                // Show SweetAlert2 modal with confetti animation
                Swal.fire({
                    title: 'Congratulations!',
                    text: 'You completed the game.',
                    imageUrl: 'https://media.giphy.com/media/26AHONQ79FdWZhAI0/giphy.gif', // Example GIF, replace with your own
                    imageWidth: 400,
                    imageHeight: 200,
                    imageAlt: 'Hooray!',
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

            // Show confirmation dialog before leaving the page
            window.addEventListener("beforeunload", function (event) {
                if (!confirm()) {
                    event.preventDefault();
                    event.returnValue = "";
                }
            }, { capture: true });

            // Fetch game history from the database
            function fetchGameHistory(userId) {
                fetch('fetch_history.php?user_id=' + userId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.history.length > 0) {
                            // Clear existing history to prevent duplicates
                            historyList.innerHTML = "";

                            // Iterate through each game and append to the table
                            data.history.forEach((game, index) => {
                                const row = document.createElement("tr");
                                row.innerHTML = `
                                    <td style="padding: 10px; border: 1px solid #ddd;">${index + 1}</td>
                                    <td style="padding: 10px; border: 1px solid #ddd;">${game.difficulty}</td>
                                    <td style="padding: 10px; border: 1px solid #ddd;">${game.time}</td>
                                    <td style="padding: 10px; border: 1px solid #ddd;">${game.status}</td>
                                    <td style="padding: 10px; border: 1px solid #ddd;">${game.last_played}</td>
                                `;
                                historyList.appendChild(row);
                            });
                        } else {
                            // Optionally handle cases with no history
                            historyList.innerHTML = `<tr><td colspan="5" style="padding: 10px; border: 1px solid #ddd;">No game history available.</td></tr>`;
                        }
                    })
                    .catch(error => console.error("Error fetching game history:", error));
            }

            // Fetch and display game history on page load
            fetchGameHistory(userId);

            // Initialize the grid
            createGrid();
        });
    </script>

</body>

</html>