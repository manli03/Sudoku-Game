<?php
// Include database connection file
include('db.php');

// Initialize session if not already started
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle email availability check
if (isset($_POST['check_email'])) {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 'invalid';
        exit;
    }

    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $exists = $stmt->fetch();

    echo $exists ? 'exists' : 'available';
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request verification.";
    } else {
        // Generate a new CSRF token to prevent resubmission
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Get and sanitize form data
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validation checks
        if (empty($email) || empty($password) || empty($confirm_password)) {
            $error = "All fields are required!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format!";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long!";
        // } elseif (!preg_match("/[A-Z]/", $password)) {
        //     $error = "Password must contain at least one uppercase letter!";
        // } elseif (!preg_match("/[a-z]/", $password)) {
        //     $error = "Password must contain at least one lowercase letter!";
        // } elseif (!preg_match("/[0-9]/", $password)) {
        //     $error = "Password must contain at least one number!";
        // } elseif (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) {
        //     $error = "Password must contain at least one special character!";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } else {
            try {
                // Check if email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    $error = "Email already registered!";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_ARGON2ID);

                    // Insert new user
                    $stmt = $db->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                    if ($stmt->execute([$email, $hashed_password])) {
                        // Redirect to avoid duplicate submission
                        $_SESSION['success_message'] = "Registration successful! Please log in to continue.";
                        $success = "Registration successful! Please log in to continue.";
                        // header("Location: " . $_SERVER['REQUEST_URI']);
                        // exit;
                    } else {
                        $error = "Error: Unable to register at this time.";
                    }
                }
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = "An unexpected error occurred. Please try again later.";
            }
        }
    }
}

// Display success message after redirect
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="User registration page for a web application.">
    <title>User Registration</title>

    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.9.4/css/bulma.min.css">
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .password-requirements {
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }

        .requirement-met {
            color: #48c774;
        }

        .requirement-unmet {
            color: #f14668;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="py-8 min-h-screen flex items-center justify-center">
        <div class="container mx-4">
            <div class="max-w-md mx-auto bg-white py-6 px-8 rounded-lg shadow-xl border border-gray-200">
                <div class="text-center mb-4">
                    <span class="icon is-large">
                        <i class="fas fa-user-plus fa-3x text-gray-600"></i>
                    </span>
                </div>

                <h2 class="title is-3 has-text-centered mb-4">Register</h2>

                <!-- Messages -->
                <?php if (isset($error)): ?>
                    <div class="notification is-danger is-light mb-4">
                        <button class="delete" onclick="this.parentElement.style.display='none';"></button>
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div class="notification is-success is-light mb-4">
                        <button class="delete" onclick="this.parentElement.style.display='none';"></button>
                        <?= htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Registration Form -->
                <form method="POST" action="" class="space-y-4" id="registrationForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="register" value="1">

                    <div class="field">
                        <label class="label is-small">Email</label>
                        <div class="control has-icons-left has-icons-right">
                            <input class="input" type="email" name="email" id="email" required
                                value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <span class="icon is-small is-left">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <span class="icon is-small is-right hidden" id="email-status-icon">
                                <i class="fas"></i>
                            </span>
                        </div>
                        <p class="help is-danger hidden" id="email-error"></p>
                    </div>

                    <div class="field">
                        <label class="label is-small">Password</label>
                        <div class="control has-icons-left">
                            <input class="input" type="password" name="password" id="password" required>
                            <span class="icon is-small is-left">
                                <i class="fas fa-lock"></i>
                            </span>
                        </div>
                        <div class="password-requirements ml-3 mt-2" id="password-requirements">
                            <p id="length-req" class="requirement-unmet">
                                <i class="fas fa-times-circle"></i> At least 8 characters
                            </p>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label is-small">Confirm Password</label>
                        <div class="control has-icons-left">
                            <input class="input" type="password" name="confirm_password" id="confirm_password" required>
                            <span class="icon is-small is-left">
                                <i class="fas fa-lock"></i>
                            </span>
                        </div>
                        <p class="help is-danger hidden" id="password-mismatch">Passwords do not match</p>
                    </div>

                    <div class="field mt-5">
                        <div class="control">
                            <button class="button is-primary is-fullwidth hover:is-link" type="submit" id="registerBtn"
                                disabled>
                                <span class="icon">
                                    <i class="fas fa-user-plus"></i>
                                </span>
                                <span>Register</span>
                            </button>
                        </div>
                    </div>
                </form>

                <div class="has-text-centered mt-4">
                    <p class="text-gray-600 text-sm">
                        Already have an account?
                        <a href="login.php" class="text-blue-600 hover:text-blue-800 hover:underline">Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form elements
        const form = document.getElementById('registrationForm');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const registerBtn = document.getElementById('registerBtn');
        const passwordMismatch = document.getElementById('password-mismatch');
        const emailError = document.getElementById('email-error');
        const emailStatusIcon = document.getElementById('email-status-icon');

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Check email availability
        const checkEmail = debounce(async (email) => {
            if (email && email !== '') {
                const formData = new FormData();
                formData.append('check_email', '1');
                formData.append('email', email);

                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.text();

                    emailStatusIcon.classList.remove('hidden');

                    if (result === 'exists') {
                        emailError.textContent = 'Email is already registered';
                        emailError.classList.remove('hidden');
                        emailStatusIcon.querySelector('i').className = 'fas fa-times text-red-500';
                        registerBtn.disabled = true;
                    } else if (result === 'invalid') {
                        emailError.textContent = 'Invalid email format';
                        emailError.classList.remove('hidden');
                        emailStatusIcon.querySelector('i').className = 'fas fa-times text-red-500';
                        registerBtn.disabled = true;
                    } else {
                        emailError.classList.add('hidden');
                        emailStatusIcon.querySelector('i').className = 'fas fa-check text-green-500';
                        validateForm();  // Re-validate the form when email is valid
                    }
                } catch (error) {
                    console.error('Error checking email:', error);
                }
            }
        }, 500);

        // Password validation
        function validatePassword(password) {
            const isValid = password.length >= 8;

            // Update requirement indicator
            const lengthReq = document.getElementById('length-req');
            if (isValid) {
                lengthReq.className = 'requirement-met';
                lengthReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                lengthReq.className = 'requirement-unmet';
                lengthReq.querySelector('i').className = 'fas fa-times-circle';
            }

            return isValid;
        }

        // Form validation
        function validateForm() {
            const email = emailInput.value.trim();
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            const isPasswordValid = validatePassword(password);
            const doPasswordsMatch = password === confirmPassword;
            const isEmailValid = emailError.classList.contains('hidden');

            // Show/hide password mismatch message
            if (password && confirmPassword) {
                passwordMismatch.classList.toggle('hidden', doPasswordsMatch);
            }

            // Enable/disable submit button
            const isFormValid = email && isPasswordValid && doPasswordsMatch && isEmailValid;
            registerBtn.disabled = !isFormValid;
            registerBtn.classList.toggle('cursor-not-allowed', !isFormValid);
        }

        // Event listeners
        emailInput.addEventListener('input', (e) => {
            const email = e.target.value.trim();
            checkEmail(email);
        });

        passwordInput.addEventListener('input', validateForm);
        confirmPasswordInput.addEventListener('input', validateForm);

        // Prevent form submission if button is disabled
        form.addEventListener('submit', (e) => {
            if (registerBtn.disabled) {
                e.preventDefault();
            }
        });

    </script>
</body>