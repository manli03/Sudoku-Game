<?php
session_start();
require 'db.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$max_attempts = 10; // Max allowed attempts
$cooldown_time = 300; // 5 minutes cooldown (300 seconds)

// Initialize login attempts and cooldown time if not set
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if cooldown is active
    if ($_SESSION['login_attempts'] >= $max_attempts && time() - $_SESSION['last_attempt_time'] < $cooldown_time) {
        $remaining_time = $cooldown_time - (time() - $_SESSION['last_attempt_time']);
        $error = "Too many failed attempts. Please try again in {$remaining_time} seconds.";
    } else {
        // Verify CSRF token
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            $error = "Invalid request verification.";
        } else {
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'] ?? '';
            $rememberMe = isset($_POST['remember-me']);

            if ($email && $password) {
                try {
                    // Fetch user by email
                    $stmt = $db->prepare("SELECT id, email, password FROM users WHERE email = :email LIMIT 1");
                    $stmt->execute(['email' => $email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user && password_verify($password, $user['password'])) {
                        // Successful login
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];

                        // Reset login attempts
                        $_SESSION['login_attempts'] = 0;

                        // ** Rehash the password if it's not using PASSWORD_ARGON2ID **
                        if (password_needs_rehash($user['password'], PASSWORD_ARGON2ID)) {
                            // Rehash the password with PASSWORD_ARGON2ID
                            $newHash = password_hash($password, PASSWORD_ARGON2ID);

                            // Update the password in the database
                            $updateStmt = $db->prepare("UPDATE users SET password = :newPassword WHERE id = :id");
                            $updateStmt->execute([
                                'newPassword' => $newHash,
                                'id' => $user['id']
                            ]);
                        }

                        // Handle "Remember Me"
                        if ($rememberMe) {
                            $rememberToken = bin2hex(random_bytes(32)); // Generate a secure token
                            $expiryTime = time() + (30 * 24 * 60 * 60); // 30 days

                            // Store the token in the database
                            $stmt = $db->prepare("UPDATE users SET remember_token = :token WHERE id = :id");
                            $stmt->execute(['token' => $rememberToken, 'id' => $user['id']]);

                            // Set the token in a secure cookie
                            setcookie('remember_me', $rememberToken, $expiryTime, '/', '', true, true);
                        }

                        header("Location: dashboard.php");
                        exit;
                    } else {
                        // Increment login attempts
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt_time'] = time();

                        $remaining_attempts = $max_attempts - $_SESSION['login_attempts'];

                        if ($remaining_attempts > 0) {
                            if ($remaining_attempts <= 3) {
                                $error = "Invalid email or password. You have {$remaining_attempts} attempt(s) remaining.";
                            } else {
                                $error = "Invalid email or password.";
                            }
                        } else {
                            $error = "Too many failed attempts. Please try again in {$cooldown_time} seconds.";
                        }
                    }
                } catch (Exception $e) {
                    error_log("Login error: " . $e->getMessage());
                    $error = "An unexpected error occurred. Please try again later.";
                }
            } else {
                $error = "Please fill in both fields.";
            }
        }
    }
}

// Automatic login via "Remember Me" cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $rememberToken = $_COOKIE['remember_me'];

    try {
        // Check the token in the database
        $stmt = $db->prepare("SELECT id FROM users WHERE remember_token = :token LIMIT 1");
        $stmt->execute(['token' => $rememberToken]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Token is valid, log the user in
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            header("Location: dashboard.php");
            exit;
        } else {
            // Token is invalid, clear the cookie
            setcookie('remember_me', '', time() - 3600, '/');
        }
    } catch (Exception $e) {
        error_log("Remember Me error: " . $e->getMessage());
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Log in to your Sudoku account to access your game history, statistics, and more.">
    <title>Login</title>

    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.9.4/css/bulma.min.css">
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        <style>

        /* Adjust the right icon to ensure it's clickable */
        .icon.is-right {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 100;
            /* Ensure the icon is above the input field */
            pointer-events: auto;
            /* Allow the icon to be clickable */
        }

        #password {
            padding-right: 2.5rem;
            /* Ensure enough space for the icon */
        }

        .cursor-pointer {
            cursor: pointer;
            /* Ensures the cursor changes to a pointer when hovering over the icon */
        }
    </style>

    </style>
</head>

<body class="bg-gray-100">
    <div class="py-8 min-h-screen flex items-center justify-center">
        <div class="container mx-4">
            <div class="max-w-sm mx-auto bg-white py-6 px-8 rounded-lg shadow-xl border border-gray-200">
                <!-- Logo/Icon -->
                <div class="text-center mb-4">
                    <span class="icon is-large">
                        <i class="fas fa-user-circle fa-3x text-gray-600"></i>
                    </span>
                </div>

                <h2 class="title is-3 has-text-centered mb-4">Login</h2>

                <!-- Show error message -->
                <?php if ($error): ?>
                    <div class="notification is-danger is-light mb-4" role="alert">
                        <button class="delete" onclick="this.parentElement.style.display='none';"></button>
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" action="" class="space-y-4" id="loginForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="field">
                        <label class="label is-small" for="email">Email</label>
                        <div class="control has-icons-left has-icons-right">
                            <input class="input" type="email" name="email" id="email" required autocomplete="email"
                                autofocus
                                value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <span class="icon is-small is-left">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <span class="icon is-small is-right hidden" id="email-validation-icon"></span>
                        </div>
                        <p class="help is-danger hidden" id="email-error"></p>
                    </div>

                    <div class="field">
                        <label class="label is-small" for="password">
                            Password
                            <a href="forgot_password.php"
                                class="is-pulled-right text-xs text-blue-600 hover:text-blue-800 hover:underline">
                                Forgot Password?
                            </a>
                        </label>
                        <div class="control has-icons-left has-icons-right">
                            <input class="input" type="password" name="password" id="password" required
                                autocomplete="current-password">
                            <span class="icon is-small is-left">
                                <i class="fas fa-lock"></i>
                            </span>
                            <span class="icon is-small is-right" style="pointer-events: auto;">
                                <i class="far fa-eye-slash cursor-pointer" id="togglePassword"></i>
                            </span>
                        </div>

                    </div>

                    <div class="field">
                        <div class="control">
                            <label class="checkbox">
                                <input type="checkbox" id="remember-me" name="remember-me">
                                <span class="ml-2 text-sm text-gray-600">Remember me</span>
                            </label>
                        </div>
                    </div>

                    <div class="field mt-5">
                        <div class="control">
                            <button class="button is-primary is-fullwidth hover:is-link" type="submit" id="loginBtn"
                                disabled>
                                <span class="icon">
                                    <i class="fas fa-sign-in-alt"></i>
                                </span>
                                <span>Login</span>
                            </button>
                        </div>
                    </div>
                </form>

                <div class="has-text-centered mt-4">
                    <p class="text-gray-600 text-sm">
                        Don't have an account?
                        <a href="register.php" class="text-blue-600 hover:text-blue-800 hover:underline">Sign up</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Get form elements
        const form = document.getElementById('loginForm');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const loginBtn = document.getElementById('loginBtn');
        const togglePassword = document.getElementById('togglePassword');
        const emailError = document.getElementById('email-error');
        const emailValidationIcon = document.getElementById('email-validation-icon');

        // Toggle password visibility
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle eye icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Basic email validation
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email.toLowerCase());
        }

        // Form validation
        function validateForm() {
            const email = emailInput.value.trim();
            const password = passwordInput.value.trim();

            // Validate email format
            if (email) {
                if (validateEmail(email)) {
                    emailError.classList.add('hidden');
                    emailValidationIcon.classList.remove('hidden');
                    emailValidationIcon.innerHTML = '<i class="fas fa-check text-green-500"></i>';
                } else {
                    emailError.textContent = 'Please enter a valid email address';
                    emailError.classList.remove('hidden');
                    emailValidationIcon.classList.remove('hidden');
                    emailValidationIcon.innerHTML = '<i class="fas fa-times text-red-500"></i>';
                }
            } else {
                emailError.classList.add('hidden');
                emailValidationIcon.classList.add('hidden');
            }

            // Enable/disable submit button
            const isFormValid = email && password && validateEmail(email);
            loginBtn.disabled = !isFormValid;
            loginBtn.classList.toggle('cursor-not-allowed', !isFormValid);
            loginBtn.classList.toggle('opacity-50', !isFormValid);
        }

        // Add event listeners
        emailInput.addEventListener('input', validateForm);
        passwordInput.addEventListener('input', validateForm);

        // Prevent form submission if button is disabled
        form.addEventListener('submit', (e) => {
            if (loginBtn.disabled) {
                e.preventDefault();
            }
        });

        // Handle "Remember me" functionality
        if (localStorage.getItem('rememberedEmail')) {
            emailInput.value = localStorage.getItem('rememberedEmail');
            document.getElementById('remember-me').checked = true;
            validateForm();
        }

        form.addEventListener('submit', () => {
            if (document.getElementById('remember-me').checked) {
                localStorage.setItem('rememberedEmail', emailInput.value.trim());
            } else {
                localStorage.removeItem('rememberedEmail');
            }
        });
    </script>
</body>

</html>