<!-- Bulma CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.9.4/css/bulma.min.css">
<!-- Sudoku Library -->
<script src="/exercise/sudoku.js"></script>
<!-- FontAwesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Canvas Confetti for celebration animation -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>

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
    body {
        background: radial-gradient(circle farthest-side at 0% 50%, #ffffff 23.5%, rgba(255, 170, 0, 0) 0) 21px 30px,
            radial-gradient(circle farthest-side at 0% 50%, #f5f5f5 24%, rgba(240, 166, 17, 0) 0) 19px 30px,
            linear-gradient(#ffffff 14%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 85%, #ffffff 0) 0 0,
            linear-gradient(150deg, #ffffff 24%, #f5f5f5 0, #f5f5f5 26%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 74%, #f5f5f5 0, #f5f5f5 76%, #ffffff 0) 0 0,
            linear-gradient(30deg, #ffffff 24%, #f5f5f5 0, #f5f5f5 26%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 74%, #f5f5f5 0, #f5f5f5 76%, #ffffff 0) 0 0,
            linear-gradient(90deg, #f5f5f5 2%, #ffffff 0, #ffffff 98%, #f5f5f5 0%) 0 0 #ffffff;
        /* background: radial-gradient(circle farthest-side at 0% 50%, #282828 23.5%, rgba(255, 170, 0, 0) 0) 21px 30px,
            radial-gradient(circle farthest-side at 0% 50%, #2c3539 24%, rgba(240, 166, 17, 0) 0) 19px 30px,
            linear-gradient(#282828 14%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 85%, #282828 0) 0 0,
            linear-gradient(150deg, #282828 24%, #2c3539 0, #2c3539 26%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 74%, #2c3539 0, #2c3539 76%, #282828 0) 0 0,
            linear-gradient(30deg, #282828 24%, #2c3539 0, #2c3539 26%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 74%, #2c3539 0, #2c3539 76%, #282828 0) 0 0,
            linear-gradient(90deg, #2c3539 2%, #282828 0, #282828 98%, #2c3539 0%) 0 0 #282828; */
        background-size: 40px 60px;
        overflow-x: auto;
        min-height: 100vh;
    }

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
</style>

<nav class="navbar is-primary fixed-top" role="navigation" aria-label="main navigation">
    <div class="navbar-brand">
        <a class="navbar-item is-size-3" href="/exercise/dashboard.php">
            <strong>Sudoku</strong>
        </a>

        <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarMenu">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </a>
    </div>

    <div id="navbarMenu" class="navbar-menu">
        <div class="navbar-end">
            <a href="/exercise/dashboard.php" class="navbar-item">
                Dashboard
            </a>

            <!-- User Profile and Logout Dropdown -->
            <div class="navbar-item has-dropdown is-hoverable">
                <div class="navbar-link" style="display: flex; align-items: center; cursor: pointer;">
                    <div class="navbar-profile-pic">
                        <?php echo strtoupper(substr($display_name, 0, 1)); ?>
                    </div>
                    <span><?php echo htmlspecialchars($display_name); ?></span>
                </div>

                <div class="navbar-dropdown" style="display: none;">
                    <a href="/exercise/profile.php" class="navbar-item">
                        Profile
                    </a>
                    <a href="/exercise/logout.php" class="navbar-item has-text-danger">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- JavaScript for interactive navbar functionality -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Toggle navbar menu on burger button click
        const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
        if ($navbarBurgers.length > 0) {
            $navbarBurgers.forEach(el => {
                el.addEventListener('click', () => {
                    const target = el.dataset.target;
                    const $target = document.getElementById(target);
                    el.classList.toggle('is-active');
                    $target.classList.toggle('is-active');
                });
            });
        }

        // Toggle dropdown on profile picture or username click
        const navbarLink = document.querySelector('.navbar-item.has-dropdown .navbar-link');
        const navbarDropdown = document.querySelector('.navbar-item.has-dropdown .navbar-dropdown');
        const dropdownContainer = document.querySelector('.navbar-item.has-dropdown');
        let isClickOpened = false;

        navbarLink.addEventListener('click', (event) => {
            event.preventDefault();
            isClickOpened = !isClickOpened;
            navbarDropdown.style.display = isClickOpened ? 'block' : 'none';
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (event) => {
            if (!navbarLink.contains(event.target) && !navbarDropdown.contains(event.target)) {
                navbarDropdown.style.display = 'none';
                isClickOpened = false;
            }
        });

        // Open/close dropdown on hover for desktop view
        if (!window.matchMedia("(max-width: 768px)").matches) {
            dropdownContainer.addEventListener('mouseenter', () => {
                if (!isClickOpened) {
                    navbarDropdown.style.display = 'block';
                }
            });

            dropdownContainer.addEventListener('mouseleave', () => {
                if (!isClickOpened) {
                    navbarDropdown.style.display = 'none';
                }
            });
        }

        // Ask confirmation before logout
        document.querySelectorAll('.navbar-item a[href="/exercise/logout.php"]').forEach(el => {
            el.addEventListener('click', (event) => {
                event.preventDefault();

                Swal.fire({
                    title: 'Are you sure?',
                    text: "Do you want to logout?",
                    icon: 'warning',
                    showCancelButton: true,
                    // button confirm button color is set to 'danger'
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
    });
</script>