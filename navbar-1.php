<?php
// Initialize session if it hasn't been started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check for user login status
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Fetch user data if not already fetched or missing required fields
    if (!isset($user) || !isset($user['username']) || !isset($user['profile_color'])) {
        require 'db.php';

        // Retrieve user data from database
        $stmt = $db->prepare("SELECT username, profile_color FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $user_id]);
        $navbar_user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Set the user data if not previously set
        if (!isset($user)) {
            $user = $navbar_user;
        }
    }

    // Fallback to 'Guest' if username is not set
    $display_name = !empty($user['username']) ? $user['username'] : 'Guest';
    // Default color to 'blue' if profile color is not set
    $profile_color = !empty($user['profile_color']) ? $user['profile_color'] : 'blue';

    // Define available color gradients
    $colorGradients = [
        'green' => ['#27ae60', '#2ecc71'],
        'blue' => ['#3498db', '#2980b9'],
        'purple' => ['#8e44ad', '#9b59b6'],
        'orange' => ['#e67e22', '#d35400'],
        'red' => ['#e74c3c', '#c0392b']
    ];

    // Select gradient based on profile color, default to 'blue'
    $gradient = $colorGradients[$profile_color] ?? $colorGradients['blue'];
} else {
    // Default display for not logged in user
    $display_name = 'Guest';
    $gradient = ['#3498db', '#2980b9'];
}
?>

<!-- Inline styles for profile picture with gradient background -->
<style>
    .navbar-profile-pic {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 1rem;
        color: white;
        font-weight: bold;
        background-image: linear-gradient(to right,
                <?php echo $gradient[0]; ?>
                ,
                <?php echo $gradient[1]; ?>
            );
        margin-right: 8px;
    }

    .navbar {
        background-color: #00D1B2;
        height: 4rem;
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand fs-2 fw-bold" href="/exercise/dashboard.php">Sudoku</a>

        <!-- Toggle Button for Mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown"
            aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Menu -->
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/exercise/dashboard.php">Dashboard</a>
                </li>
                <!-- User Profile Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdownMenuLink"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="navbar-profile-pic">
                            <?php echo strtoupper(substr($display_name, 0, 1)); ?>
                        </div>
                        <span><?php echo htmlspecialchars($display_name); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownMenuLink">
                        <li><a class="dropdown-item" href="/exercise/profile.php">Profile</a></li>
                        <li>
                            <a class="dropdown-item text-danger" id="logout-link" href="#">Logout</a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Logout Confirmation Script -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const logoutLink = document.getElementById('logout-link');

        logoutLink.addEventListener('click', (event) => {
            event.preventDefault();

            Swal.fire({
                title: 'Are you sure?',
                text: "Do you want to logout?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, logout!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "/exercise/logout.php";
                }
            });
        });
    });
</script>