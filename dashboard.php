<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $db->prepare("SELECT username, email, profile_color, is_profile_complete FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit();
}

if (!$user['is_profile_complete']) {
    header("Location: profile.php?message=complete_profile");
    exit();
}

// Define difficulties and their corresponding colors
$difficulties = [
    "easy" => "is-success",      // Green
    "medium" => "is-info",       // Blue
    "hard" => "is-warning",      // Orange
    "expert" => "is-primary",    // Dark Blue
    "master" => "is-danger",     // Red
    "extreme" => "is-dark"       // Dark Gray
];

// Initialize Solo Stats
$soloStats = [
    "totalGames" => 0,
    "difficultyStats" => [],
    "fastestTimePerDifficulty" => [],
];

// Fetch Solo Game Statistics
foreach ($difficulties as $difficulty => $color) {
    $sql = "
        SELECT 
            COUNT(*) AS total_games,
            MIN(time) AS fastest_time
        FROM games
        WHERE user_id = :user_id 
          AND difficulty = :difficulty 
          AND status = 'completed'
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $user_id, 'difficulty' => $difficulty]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_games = (int) $data["total_games"];
    $fastest_time = $data["fastest_time"] !== null ? $data["fastest_time"] : "N/A";

    $soloStats["totalGames"] += $total_games;
    $soloStats["difficultyStats"][$difficulty] = $total_games;
    $soloStats["fastestTimePerDifficulty"][$difficulty] = $fastest_time;
}

// Initialize Multiplayer Stats
$multiplayerStats = [
    "totalGames" => 0,
    "difficultyStats" => [], // Win rate per difficulty
    "fastestTimePerDifficulty" => [],
    "totalGamesByDifficulty" => [], // Total games by difficulty
];

// Fetch Multiplayer Game Statistics
foreach ($difficulties as $difficulty => $color) {
    $sql = "
        SELECT mg.start_time, mg.end_time, mg.winner_id
        FROM multiplayer_games mg
        JOIN game_results gr ON mg.id = gr.game_id
        WHERE gr.user_id = :user_id
          AND mg.difficulty = :difficulty
          AND mg.status = 'completed'
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $user_id, 'difficulty' => $difficulty]);
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_games = count($games);
    $multiplayerStats["totalGames"] += $total_games;
    $multiplayerStats["totalGamesByDifficulty"][$difficulty] = $total_games;

    // Calculate wins
    $wins = 0;
    foreach ($games as $game) {
        if ($game['winner_id'] == $user_id) {
            $wins++;
        }
    }
    $win_rate = $total_games > 0 ? round(($wins / $total_games) * 100, 2) : 0;
    $multiplayerStats["difficultyStats"][$difficulty] = $win_rate;

    // Calculate fastest time among won games
    $fastest_time_seconds = null;
    foreach ($games as $game) {
        if ($game['winner_id'] == $user_id && $game['start_time'] && $game['end_time']) {
            $start_time = new DateTime($game['start_time']);
            $end_time = new DateTime($game['end_time']);
            $interval = $start_time->diff($end_time);
            $time_taken = ($interval->h * 3600) + ($interval->i * 60) + $interval->s; // Convert to seconds

            if ($fastest_time_seconds === null || $time_taken < $fastest_time_seconds) {
                $fastest_time_seconds = $time_taken;
            }
        }
    }
    $multiplayerStats["fastestTimePerDifficulty"][$difficulty] = $fastest_time_seconds !== null ? gmdate("i:s", $fastest_time_seconds) : "N/A";
}

// Fetch Leaderboard Data for multiplayer games
$leaderboard = [];
foreach ($difficulties as $difficulty => $color) {
    $sql = "
        SELECT 
            u.username,
            MIN(TIMESTAMPDIFF(SECOND, mg.start_time, mg.end_time)) AS fastest_time
        FROM multiplayer_games mg
        JOIN users u ON mg.winner_id = u.id
        WHERE mg.difficulty = :difficulty 
          AND mg.status = 'completed'
        GROUP BY u.id
        ORDER BY fastest_time ASC
        LIMIT 10
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(['difficulty' => $difficulty]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $leaderboard[$difficulty] = $results;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Play and View your Sudoku game statistics and leaderboard rankings." />
    <title>Dashboard - Sudoku</title>
    <style>
        /* Dashboard styling */
        .welcome-card {
            background: linear-gradient(135deg, #3498db, #2ecc71);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .welcome-card .username {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .dashboard-content {
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        /* Feature Section Background */
        .feature-section {
            padding: 3rem 0;
            /* Space around the feature section */
        }

        /* Feature Card Styling */
        .feature-card {
            background-color: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            color: #363636;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            /* Elevation on hover */
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* Distinct Colors for Each Feature */
        .feature-access {
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            /* Purple to Blue */
            color: white;
        }

        .feature-compete {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            /* Teal to Green */
            color: white;
        }

        .feature-history {
            background: linear-gradient(135deg, #ff7e5f, #feb47b);
            /* Orange to Peach */
            color: white;
        }

        /* Icon Styling */
        .feature-card .icon {
            margin-bottom: 1rem;
        }

        .feature-card .icon i {
            color: white;
        }

        /* Title and Subtitle Styling */
        .feature-card .title {
            color: white;
            margin-bottom: 0.5rem;
        }

        .feature-card .subtitle {
            color: rgba(255, 255, 255, 0.85);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .feature-card {
                padding: 1rem;
            }

            .feature-card .icon i {
                font-size: 2.5rem;
                /* Slightly smaller icons on mobile */
            }
        }


        .tabs {
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        .box {
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            font-size: 0.9rem;
        }

        #statisticsContent .columns {
            align-items: flex-start;
            flex-wrap: wrap;
        }

        /* Progress Bar Colors */
        .is-dark {
            --progress-bar-color: #363636;
        }

        /* Counter Styling */
        .counter {
            font-size: 1.3rem;
            font-weight: bold;
        }

        /* Section Partitioning */
        .stat-section {
            margin-bottom: 1rem;
            width: 100%;
        }

        .stat-section h4 {
            margin-bottom: 0.3rem;
            font-weight: bold;
            display: flex;
            align-items: center;
        }

        .stat-section h4 i {
            margin-right: 0.3rem;
            color: #3273dc;
        }

        .stat-list {
            list-style: none;
            padding: 0;
        }

        .stat-list li {
            margin-bottom: 0.3rem;
            font-size: 0.85rem;
        }

        .stat-list li i {
            margin-right: 0.3rem;
            color: #3273dc;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .columns {
                flex-direction: row;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            .chart-container {
                max-width: 100%;
                height: auto;
            }
        }

        /* Circle Styling for Total Games */
        .circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #e8f0fe;
            border: 2px solid #3273dc;
            color: #3273dc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1rem auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
            font-size: 2rem;
        }

        .stat-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
    </style>
</head>

<body>
    <!-- Include the Navigation Bar -->
    <?php include 'navbar.php'; ?>

    <!-- Dashboard Content -->
    <section class="section">
        <div class="container">
            <div class="welcome-card">
                <p class="username">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</p>
                <p>Your email: <?php echo htmlspecialchars($user['email']); ?></p>
                <p>Your profile color: <span style="color: <?php echo htmlspecialchars($user['profile_color']); ?>;">
                        <?php echo ucfirst(htmlspecialchars($user['profile_color'])); ?></span>
                </p>
            </div>

            <div class="dashboard-content">
                <h1 class="title"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <p>Welcome to the dashboard! We're excited to have you join the game community. Take a look around and
                    explore all the features available. Remember to check out the leaderboards and compete
                    with other players.</p>
                <div class="feature-section py-6">
                    <div class="container">
                        <div class="columns is-multiline">
                            <!-- Feature 1: Access Sudoku Games -->
                            <div class="column is-12-mobile is-4-tablet">
                                <a href="solo/" class="feature-card feature-access has-text-centered">
                                    <div class="card-content">
                                        <span class="icon is-large">
                                            <i class="fas fa-gamepad fa-3x"></i>
                                        </span>
                                        <h3 class="title is-5 mt-3">Access Sudoku Games</h3>
                                        <p class="subtitle is-6">Start playing exciting Sudoku puzzles in solo mode.</p>
                                    </div>
                                </a>
                            </div>
                            <!-- Feature 2: Compete with Friends -->
                            <div class="column is-12-mobile is-4-tablet">
                                <a href="multiplayer/" class="feature-card feature-compete has-text-centered">
                                    <div class="card-content">
                                        <span class="icon is-large">
                                            <i class="fas fa-users fa-3x"></i>
                                        </span>
                                        <h3 class="title is-5 mt-3">Compete with Friends</h3>
                                        <p class="subtitle is-6">Challenge your friends and test your skills in
                                            multiplayer mode.</p>
                                    </div>
                                </a>
                            </div>
                            <!-- Feature 3: View Your Game History -->
                            <div class="column is-12-mobile is-4-tablet">
                                <a href="history.php" class="feature-card feature-history has-text-centered">
                                    <div class="card-content">
                                        <span class="icon is-large">
                                            <i class="fas fa-history fa-3x"></i>
                                        </span>
                                        <h3 class="title is-5 mt-3">View Your Game History</h3>
                                        <p class="subtitle is-6">Review your past games and analyze your performance.
                                        </p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="container mt-6">
            <div class="tabs is-centered is-boxed">
                <ul>
                    <li class="is-active" id="soloTab"><a><i class="fas fa-user"></i> Solo Games</a></li>
                    <li id="multiplayerTab"><a><i class="fas fa-users"></i> Multiplayer Games</a></li>
                </ul>
            </div>

            <div id="statisticsContent" class="box">
                <div class="columns is-multiline">
                    <!-- Solo Games Section -->
                    <div class="column" id="soloSection">
                        <h3 class="title is-5 has-text-centered"><i class="fas fa-user"></i> Solo Game Statistics</h3>
                        <div class="columns is-multiline">
                            <!-- Total Games Circle -->
                            <div class="column is-12-mobile is-4-tablet">
                                <div class="stat-container">
                                    <div class="circle">
                                        <?php echo $soloStats["totalGames"]; ?>
                                    </div>
                                    <p>Total Games</p>
                                </div>
                            </div>
                            <!-- Total Games by Difficulty -->
                            <div class="column is-12-mobile is-4-tablet">
                                <h4><i class="fas fa-chart-bar"></i> Total Games by Difficulty:</h4>
                                <ul class="stat-list">
                                    <?php foreach ($difficulties as $difficulty => $color): ?>
                                        <li><i class="fas fa-chevron-right"></i> <?php echo ucfirst($difficulty); ?>:
                                            <span><?php echo $soloStats["difficultyStats"][$difficulty]; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <!-- Fastest Time -->
                            <div class="column is-12-mobile is-4-tablet">
                                <h4><i class="fas fa-stopwatch"></i> Fastest Time by Difficulty:</h4>
                                <ul class="stat-list">
                                    <?php foreach ($difficulties as $difficulty => $color): ?>
                                        <li><i class="fas fa-chevron-right"></i> <?php echo ucfirst($difficulty); ?>:
                                            <span><?php echo $soloStats["fastestTimePerDifficulty"][$difficulty]; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Multiplayer Games Section -->
                    <div class="column" id="multiplayerSection" style="display: none;">
                        <h3 class="title is-5 has-text-centered"><i class="fas fa-users"></i> Multiplayer Game
                            Statistics</h3>
                        <div class="columns is-multiline">
                            <!-- Total Games Circle -->
                            <div class="column is-12-mobile is-4-tablet">
                                <div class="stat-container">
                                    <div class="circle">
                                        <?php echo $multiplayerStats["totalGames"]; ?>
                                    </div>
                                    <p>Total Games</p>
                                </div>
                            </div>
                            <!-- Total Games by Difficulty -->
                            <div class="column is-12-mobile is-4-tablet">
                                <h4><i class="fas fa-chart-bar"></i> Total Games by Difficulty:</h4>
                                <ul class="stat-list">
                                    <?php foreach ($difficulties as $difficulty => $color): ?>
                                        <li><i class="fas fa-chevron-right"></i> <?php echo ucfirst($difficulty); ?>:
                                            <span><?php echo $multiplayerStats["totalGamesByDifficulty"][$difficulty]; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <!-- Fastest Time -->
                            <div class="column is-12-mobile is-4-tablet">
                                <h4><i class="fas fa-stopwatch"></i> Fastest Time by Difficulty (Wins Only):</h4>
                                <ul class="stat-list">
                                    <?php foreach ($difficulties as $difficulty => $color): ?>
                                        <li><i class="fas fa-chevron-right"></i> <?php echo ucfirst($difficulty); ?>:
                                            <span><?php echo $multiplayerStats["fastestTimePerDifficulty"][$difficulty]; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <div class="stat-section">
                            <h4><i class="fas fa-trophy"></i> Win Rate by Difficulty:</h4>
                            <div class="columns is-multiline">
                                <?php foreach ($difficulties as $difficulty => $color): ?>
                                    <div class="column is-12-mobile is-6-tablet">
                                        <label class="label"><?php echo ucfirst($difficulty); ?>:
                                            <span><?php echo $multiplayerStats["difficultyStats"][$difficulty]; ?>%</span></label>
                                        <progress class="progress <?php echo $color; ?>" value="0" max="100"
                                            data-target="<?php echo $multiplayerStats["difficultyStats"][$difficulty]; ?>"></progress>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="column is-full" style="margin-top: 4rem;">
                            <h3 class="title is-5 has-text-centered"><i class="fas fa-list-ol"></i> Leaderboard</h3>
                            <div class="tabs is-centered is-boxed">
                                <ul>
                                    <?php foreach ($difficulties as $difficulty => $color): ?>
                                        <li id="leaderboardTab-<?php echo $difficulty; ?>"
                                            class="<?php echo $difficulty === 'easy' ? 'is-active' : ''; ?>">
                                            <a><?php echo ucfirst($difficulty); ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <!-- Leaderboard Content -->
                            <div id="leaderboardContent">
                                <?php foreach ($difficulties as $difficulty => $color): ?>
                                    <div id="leaderboard-<?php echo $difficulty; ?>" class="box"
                                        style="<?php echo $difficulty === 'easy' ? '' : 'display: none;'; ?>">
                                        <h4 class="subtitle is-5 has-text-centered"><?php echo ucfirst($difficulty); ?>
                                            Difficulty</h4>
                                        <table class="table is-fullwidth is-striped is-hoverable">
                                            <thead>
                                                <tr>
                                                    <th>Rank</th>
                                                    <th>Player</th>
                                                    <th>Fastest Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (isset($leaderboard[$difficulty]) && count($leaderboard[$difficulty]) > 0): ?>
                                                    <?php foreach ($leaderboard[$difficulty] as $index => $entry): ?>
                                                        <tr>
                                                            <td class="has-text-weight-bold"><?php echo $index + 1; ?></td>
                                                            <td>
                                                                <span class="icon"><i class="fas fa-user"></i></span>
                                                                <?php echo htmlspecialchars($entry['username']); ?>
                                                            </td>
                                                            <td>
                                                                <span
                                                                    class="tag <?php echo $color; ?> is-light"><?php echo gmdate("i:s", $entry['fastest_time']); ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="has-text-centered">No data available</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Tabs
            const soloTab = document.getElementById("soloTab");
            const multiplayerTab = document.getElementById("multiplayerTab");
            const soloSection = document.getElementById("soloSection");
            const multiplayerSection = document.getElementById("multiplayerSection");

            // Tab Click Events
            soloTab.addEventListener("click", () => {
                soloTab.classList.add("is-active");
                multiplayerTab.classList.remove("is-active");
                soloSection.style.display = "block";
                multiplayerSection.style.display = "none";
                soloSection.scrollIntoView({ behavior: 'smooth' }); // Smooth scroll to Solo Section
            });

            multiplayerTab.addEventListener("click", () => {
                multiplayerTab.classList.add("is-active");
                soloTab.classList.remove("is-active");
                soloSection.style.display = "none";
                multiplayerSection.style.display = "block";
                multiplayerSection.scrollIntoView({ behavior: 'smooth' }); // Smooth scroll to Multiplayer Section
                animateProgressBars(); // Trigger animation when Multiplayer is clicked
            });

            // Counter Initialization
            const counters = document.querySelectorAll('.counter');
            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                counter.innerText = target;
            });

            // Progress Bar Animation Function
            function animateProgressBars() {
                const progressBars = document.querySelectorAll('#multiplayerSection .progress');
                progressBars.forEach(progress => {
                    const target = +progress.getAttribute('data-target');
                    if (target > 0) {
                        // Animate from 0 to target
                        let current = 0;
                        const increment = target / 100; // Adjust for smoothness
                        const interval = setInterval(() => {
                            if (current >= target) {
                                progress.value = target;
                                clearInterval(interval);
                            } else {
                                current += increment;
                                progress.value = Math.min(current, target);
                            }
                        }, 10); // Adjusted interval time for visible animation (20ms)
                    } else {
                        progress.value = 0;
                    }
                });
            }

            // If Multiplayer tab is active on page load, animate progress bars
            if (multiplayerTab.classList.contains('is-active')) {
                animateProgressBars();
            }
        });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const leaderboardTabs = document.querySelectorAll("[id^='leaderboardTab-']");
            const leaderboardContents = document.querySelectorAll("[id^='leaderboard-']");

            leaderboardTabs.forEach(tab => {
                tab.addEventListener("click", () => {
                    // Remove active class from all tabs and hide all contents
                    leaderboardTabs.forEach(t => t.classList.remove("is-active"));
                    leaderboardContents.forEach(content => content.style.display = "none");

                    // Add active class to clicked tab and show corresponding content
                    tab.classList.add("is-active");
                    const difficulty = tab.id.split('-')[1];
                    const content = document.getElementById(`leaderboard-${difficulty}`);
                    content.style.display = "block";
                    content.scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    });
                });
            });
        });
    </script>

</body>

</html>