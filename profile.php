<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data from the database using id
$stmt = $db->prepare("SELECT username, email, password, phone, gender, birthday, profile_color, is_profile_complete 
                      FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Initialize status variables
$profile_update_success = null;
$password_change_success = null;
$password_change_error = null;

// Handle the profile update and password change
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Determine which form was submitted
    if (isset($_POST['profile_form'])) {
        // Handle Profile Update
        $username = $_POST['username'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $birthday = $_POST['birthday'] ?? '';
        $profile_color = $_POST['profile_color'] ?? '';

        if ($username && $phone && $gender && $birthday) {
            try {
                // Check if username already exists for a different user
                $stmt = $db->prepare("SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1");
                $stmt->execute([
                    'username' => $username,
                    'id' => $user_id,
                ]);
                $existing_user_username = $stmt->fetch(PDO::FETCH_ASSOC);

                // Check if phone already exists for a different user
                $stmt = $db->prepare("SELECT id FROM users WHERE phone = :phone AND id != :id LIMIT 1");
                $stmt->execute([
                    'phone' => $phone,
                    'id' => $user_id,
                ]);
                $existing_user_phone = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_user_username) {
                    // Redirect back with an error if username exists
                    header("Location: profile.php?profile_update=false&error=" . urlencode("Profile update failed. Username is already taken."));
                    exit();
                } elseif ($existing_user_phone) {
                    // Redirect back with an error if phone exists
                    header("Location: profile.php?profile_update=false&error=" . urlencode("Profile update failed. Phone number is already taken."));
                    exit();
                }

                // Update user profile in the database
                $stmt = $db->prepare("
                    UPDATE users 
                    SET username = :username, phone = :phone, gender = :gender, birthday = :birthday, profile_color = :profile_color, is_profile_complete = 1
                    WHERE id = :id
                ");
                $update_result = $stmt->execute([
                    'username' => $username,
                    'phone' => $phone,
                    'gender' => $gender,
                    'birthday' => $birthday,
                    'profile_color' => $profile_color,
                    'id' => $user_id
                ]);

                if ($update_result) {
                    $profile_update_success = true;
                } else {
                    $profile_update_success = false;
                }
            } catch (PDOException $e) {
                $profile_update_success = false;
                error_log('Database Error: ' . $e->getMessage());
            }
        } else {
            $profile_update_success = false;
        }

        // Redirect with profile update status
        header("Location: profile.php?profile_update=" . ($profile_update_success ? 'success' : 'false'));
        exit();
    }

    if (isset($_POST['password_form'])) {
        // Handle Password Change
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password && $new_password && $confirm_password) {
            if ($new_password !== $confirm_password) {
                $password_change_success = false;
                $password_change_error = "New password and confirmation do not match.";
            } else {
                // Verify the current password
                if (password_verify($current_password, $user['password'])) {
                    // Hash the new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    // Update password in the database
                    $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
                    $password_update_result = $stmt->execute([
                        'password' => $hashed_password,
                        'id' => $user_id
                    ]);

                    if ($password_update_result) {
                        $password_change_success = true;
                    } else {
                        $password_change_success = false;
                        $password_change_error = "Password update failed. Please try again.";
                    }
                } else {
                    $password_change_success = false;
                    $password_change_error = "Current password is incorrect.";
                }
            }
        } else {
            $password_change_success = false;
            $password_change_error = "All password fields are required.";
        }

        // Redirect with password change status
        if ($password_change_success === true) {
            header("Location: profile.php?password_update=success");
        } else {
            header("Location: profile.php?password_update=false&error=" . urlencode($password_change_error));
        }
        exit();
    }
}

// Fetch updated user data after potential changes
$stmt = $db->prepare("SELECT username, email, password, phone, gender, birthday, profile_color, is_profile_complete 
                      FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="View and edit your user profile." />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <title>User Profile</title>
    <style>
        /* Ensure cursor is not allowed when button is disabled */
        #saveButton:disabled,
        #changePasswordButton:disabled {
            cursor: not-allowed;
        }

        /* Profile Picture Styling */
        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-image: linear-gradient(to right, var(--color1), var(--color2));
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 2rem;
            color: white;
            font-weight: bold;
            position: relative;
            transition: background-image 0.3s ease;
        }

        .profile-pic-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
        }

        /* Color Icons */
        .color-icons {
            display: flex;
            gap: 10px;
            margin-left: 20px;
        }

        .color-icon {
            cursor: pointer;
            font-size: 1.5rem;
            color: #ccc;
            transition: color 0.3s;
        }

        .color-icon.selected {
            color: #000;
        }

        /* Loading Spinner */
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border-left-color: #fff;
            animation: spin 1s linear infinite;
            display: none;
            margin-left: 10px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Spacing for Password Section */
        #password-change-form {
            margin-top: 40px;
        }

        /* Edit Icon Styling */
        #editPasswordBtn {
            cursor: pointer;
            color: #3273dc;
            margin-left: 10px;
            font-size: 1.2rem;
        }

        #editPasswordBtn:hover {
            color: #2752a4;
        }
    </style>
</head>

<body>
    <!-- Include the Navigation Bar -->
    <?php include 'navbar.php'; ?>

    <div class="container" style="max-width: 700px; margin: 50px auto;">
        <div class="box">
            <?php if ($user['is_profile_complete'] === 0): ?>
                <!-- Profile Completion Message -->
                <div class="notification is-warning">
                    <p><strong>Important!</strong> You must complete your profile before accessing other features on this
                        website. Please ensure all fields are filled and saved.</p>
                </div>
            <?php endif; ?>

            <h2 class="title is-4 has-text-centered">User Profile</h2>

            <!-- Success or Error Message for Profile Update -->
            <?php if (isset($_GET['profile_update'])): ?>
                <?php if ($_GET['profile_update'] == 'success'): ?>
                    <div class="notification is-success">
                        Profile updated successfully!
                    </div>
                <?php else: ?>
                    <div class="notification is-danger">
                        <?php
                        // Display specific error message if provided
                        echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : 'Profile update failed. Please try again.';
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Success or Error Message for Password Change -->
            <?php if (isset($_GET['password_update'])): ?>
                <?php if ($_GET['password_update'] == 'success'): ?>
                    <div class="notification is-success">
                        Password changed successfully!
                    </div>
                <?php else: ?>
                    <div class="notification is-danger">
                        <?php
                        // Display specific error message if provided
                        echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : 'Password change failed. Please try again.';
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Profile Picture and Color Icons -->
            <div class="profile-pic-container">
                <div class="profile-pic" id="profilePic"
                    style="--color1: <?php echo htmlspecialchars($user['profile_color']); ?>; --color2: #3498db;">
                    <?php echo strtoupper(substr(htmlspecialchars($user['username']), 0, 1)); ?>
                </div>
                <div class="color-icons" id="colorIcons">
                    <i class="fas fa-palette color-icon" data-color="green" title="Green"></i>
                    <i class="fas fa-palette color-icon" data-color="blue" title="Blue"></i>
                    <i class="fas fa-palette color-icon" data-color="purple" title="Purple"></i>
                    <i class="fas fa-palette color-icon" data-color="orange" title="Orange"></i>
                    <i class="fas fa-palette color-icon" data-color="red" title="Red"></i>
                </div>
            </div>

            <form method="POST" action="" id="profile-form">
                <input type="hidden" name="profile_form" value="1">
                <div class="box">
                    <!-- Profile Details -->
                    <div class="columns">
                        <div class="column is-half">
                            <strong>Username <span class="has-text-danger">*</span>:</strong>
                        </div>
                        <div class="column is-half">
                            <input class="input is-small" type="text" name="username" id="username"
                                value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            <p id="username-feedback" class="help"></p> <!-- Feedback area -->
                        </div>
                    </div>

                    <div class="columns">
                        <div class="column is-half">
                            <strong>Email:</strong>
                        </div>
                        <div class="column is-half">
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                    </div>
                    <div class="columns">
                        <div class="column is-half">
                            <strong>Password:</strong>
                        </div>
                        <div class="column is-half">
                            <?php echo str_repeat('*', 8); // Masked password ?>
                            <i class="fas fa-key" id="editPasswordBtn" title="Change Password"></i>
                        </div>
                    </div>
                    <div class="columns">
                        <div class="column is-half">
                            <strong>Phone <span class="has-text-danger">*</span>:</strong>
                        </div>
                        <div class="column is-half">
                            <input class="input is-small" type="text" name="phone" id="phone"
                                value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                    </div>
                    <div class="columns">
                        <div class="column is-half">
                            <strong>Gender <span class="has-text-danger">*</span>:</strong>
                        </div>
                        <div class="column is-half">
                            <select class="input is-small" name="gender" id="gender" required>
                                <option value="male" <?php if ($user['gender'] == 'male')
                                    echo 'selected'; ?>>Male
                                </option>
                                <option value="female" <?php if ($user['gender'] == 'female')
                                    echo 'selected'; ?>>Female
                                </option>
                                <option value="other" <?php if ($user['gender'] == 'other')
                                    echo 'selected'; ?>>Other
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="columns">
                        <div class="column is-half">
                            <strong>Birthday <span class="has-text-danger">*</span>:</strong>
                        </div>
                        <div class="column is-half">
                            <input class="input is-small" type="date" name="birthday" id="birthday"
                                value="<?php echo htmlspecialchars($user['birthday']); ?>" required>
                        </div>
                    </div>

                    <!-- Profile Color Option (now with icons) -->
                    <div class="columns">
                        <div class="column is-half">
                            <!-- <strong>Profile Color:</strong> -->
                        </div>
                        <div class="column is-half">
                            <!-- Hidden input to store the selected color -->
                            <input type="hidden" name="profile_color" id="profile_color"
                                value="<?php echo htmlspecialchars($user['profile_color']); ?>">
                        </div>
                    </div>

                    <!-- Save Profile Button -->
                    <div class="has-text-centered">
                        <button class="button is-primary" id="saveButton" type="submit">
                            Save Profile
                            <span class="spinner" id="saveSpinner"></span>
                        </button>
                    </div>
                </div>
            </form>

            <!-- Password Change Section (Hidden initially) -->
            <div id="password-change-form" style="display: none;">
                <h3 class="title is-5">Change Password</h3>
                <form method="POST" action="" id="password-form">
                    <input type="hidden" name="password_form" value="1">
                    <div class="box">
                        <div class="columns">
                            <div class="column is-half">
                                <strong>Current Password <span class="has-text-danger">*</span>:</strong>
                            </div>
                            <div class="column is-half">
                                <input class="input is-small" type="password" name="current_password"
                                    id="current_password" required>
                            </div>
                        </div>
                        <div class="columns">
                            <div class="column is-half">
                                <strong>New Password <span class="has-text-danger">*</span>:</strong>
                            </div>
                            <div class="column is-half">
                                <input class="input is-small" type="password" name="new_password" id="new_password"
                                    required>
                            </div>
                        </div>
                        <div class="columns">
                            <div class="column is-half">
                                <strong>Confirm Password <span class="has-text-danger">*</span>:</strong>
                            </div>
                            <div class="column is-half">
                                <input class="input is-small" type="password" name="confirm_password"
                                    id="confirm_password" required>
                            </div>
                        </div>
                        <div id="password-mismatch-error" class="notification is-danger is-light"
                            style="display: none;">
                            Passwords do not match.
                        </div>
                        <div class="has-text-centered">
                            <button class="button is-primary" id="changePasswordButton" type="submit">
                                Change Password
                                <span class="spinner" id="passwordSpinner"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let usernameAvailable = true; // Tracks if the username is available
        let usernameTimeout;

        document.addEventListener('DOMContentLoaded', function () {
            const usernameInput = document.getElementById('username');
            const phoneInput = document.getElementById('phone');
            const genderInput = document.getElementById('gender');
            const birthdayInput = document.getElementById('birthday');
            const profileColorInput = document.getElementById('profile_color');
            const saveButton = document.getElementById('saveButton');
            const feedback = document.getElementById('username-feedback');
            const profilePic = document.getElementById('profilePic');
            const colorIcons = document.querySelectorAll('.color-icon');
            const initialData = {
                username: "<?php echo addslashes(htmlspecialchars($user['username'])); ?>",
                phone: "<?php echo addslashes(htmlspecialchars($user['phone'])); ?>",
                gender: "<?php echo addslashes(htmlspecialchars($user['gender'])); ?>",
                birthday: "<?php echo addslashes(htmlspecialchars($user['birthday'])); ?>",
                profile_color: "<?php echo addslashes(htmlspecialchars($user['profile_color'])); ?>"
            };

            // Helper function to check if form fields are valid and have changes
            function validateForm() {
                const username = usernameInput.value.trim();
                const phone = phoneInput.value.trim();
                const gender = genderInput.value.trim();
                const birthday = birthdayInput.value.trim();
                const profile_color = profileColorInput.value;

                // Check if fields are filled
                const allFieldsFilled = username && phone && gender && birthday && profile_color;

                // Check if there are changes in the form
                const isChanged =
                    username !== initialData.username ||
                    phone !== initialData.phone ||
                    gender !== initialData.gender ||
                    birthday !== initialData.birthday ||
                    profile_color !== initialData.profile_color;

                // Enable save button only if all conditions are met
                if (allFieldsFilled && isChanged && usernameAvailable) {
                    saveButton.disabled = false;
                } else {
                    saveButton.disabled = true;
                }
            }

            // Handle username input with AJAX validation
            usernameInput.addEventListener('input', function () {
                const username = usernameInput.value.trim();
                feedback.textContent = ''; // Clear feedback

                if (username.length > 0) {
                    // Throttle AJAX requests
                    if (usernameTimeout) clearTimeout(usernameTimeout);

                    usernameTimeout = setTimeout(function () {
                        $.ajax({
                            url: 'check_username.php',
                            type: 'POST',
                            data: { username: username },
                            success: function (response) {
                                try {
                                    const data = JSON.parse(response);

                                    if (data.exists) {
                                        feedback.textContent = data.message;
                                        feedback.classList.add('is-danger');
                                        feedback.classList.remove('is-success');
                                        usernameAvailable = false;
                                    } else {
                                        feedback.textContent = data.message;
                                        feedback.classList.add('is-success');
                                        feedback.classList.remove('is-danger');
                                        usernameAvailable = true;
                                    }
                                } catch (e) {
                                    feedback.textContent = 'Invalid server response.';
                                    feedback.classList.add('is-danger');
                                    feedback.classList.remove('is-success');
                                    usernameAvailable = false;
                                }
                                validateForm(); // Revalidate form after username check
                            },
                            error: function () {
                                feedback.textContent = 'Error checking username. Please try again.';
                                feedback.classList.add('is-danger');
                                feedback.classList.remove('is-success');
                                usernameAvailable = false;
                                validateForm(); // Revalidate form on error
                            }
                        });
                    }, 500); // Throttle delay
                } else {
                    usernameAvailable = false;
                    feedback.textContent = 'Username cannot be empty.';
                    feedback.classList.add('is-danger');
                    feedback.classList.remove('is-success');
                    validateForm();
                }
            });

            // Add event listeners to validate form when other fields are changed
            [phoneInput, genderInput, birthdayInput, profileColorInput].forEach((input) => {
                input.addEventListener('input', validateForm);
                input.addEventListener('change', validateForm);
            });

            // Handle profile picture color change
            const colorGradients = {
                green: ['#27ae60', '#2ecc71'],
                blue: ['#3498db', '#2980b9'],
                purple: ['#8e44ad', '#9b59b6'],
                orange: ['#e67e22', '#d35400'],
                red: ['#e74c3c', '#c0392b']
            };

            function updateProfilePicGradient(color) {
                const gradient = colorGradients[color];
                if (gradient) {
                    profilePic.style.setProperty('--color1', gradient[0]);
                    profilePic.style.setProperty('--color2', gradient[1]);
                }
            }

            // Set initial gradient
            updateProfilePicGradient(initialData.profile_color);

            // Highlight the selected color icon
            colorIcons.forEach(icon => {
                if (icon.getAttribute('data-color') === initialData.profile_color) {
                    icon.classList.add('selected');
                }
            });

            // Handle color icon selection
            colorIcons.forEach(icon => {
                icon.addEventListener('click', function () {
                    // Remove 'selected' class from all icons
                    colorIcons.forEach(ic => ic.classList.remove('selected'));
                    // Add 'selected' class to the clicked icon
                    this.classList.add('selected');
                    // Get the selected color
                    const selectedColor = this.getAttribute('data-color');
                    // Update the hidden input
                    profileColorInput.value = selectedColor;
                    // Update the profile picture gradient
                    updateProfilePicGradient(selectedColor);
                    // Validate the form to potentially enable the save button
                    validateForm();
                });
            });

            // Password form validation
            function validatePasswordForm() {
                const currentPassword = document.getElementById('current_password').value.trim();
                const newPassword = document.getElementById('new_password').value.trim();
                const confirmPassword = document.getElementById('confirm_password').value.trim();
                const mismatchError = document.getElementById('password-mismatch-error');
                const changePasswordButton = document.getElementById('changePasswordButton');

                if (newPassword && confirmPassword && newPassword !== confirmPassword) {
                    mismatchError.style.display = 'block';
                    changePasswordButton.disabled = true;
                } else {
                    mismatchError.style.display = 'none';
                    changePasswordButton.disabled = !(currentPassword && newPassword && confirmPassword);
                }
            }

            // Add event listeners for password validation
            document.getElementById('current_password').addEventListener('input', validatePasswordForm);
            document.getElementById('new_password').addEventListener('input', validatePasswordForm);
            document.getElementById('confirm_password').addEventListener('input', validatePasswordForm);

            // Show loading spinner on form submit
            document.getElementById('profile-form').addEventListener('submit', function () {
                const saveSpinner = document.getElementById('saveSpinner');
                saveButton.disabled = true;
                saveSpinner.style.display = 'inline-block';
            });

            document.getElementById('password-form').addEventListener('submit', function () {
                const changePasswordButton = document.getElementById('changePasswordButton');
                const passwordSpinner = document.getElementById('passwordSpinner');
                changePasswordButton.disabled = true;
                passwordSpinner.style.display = 'inline-block';
            });

            // Toggle password form visibility
            document.getElementById('editPasswordBtn').addEventListener('click', function () {
                const passwordForm = document.getElementById('password-change-form');
                if (passwordForm.style.display === 'none' || passwordForm.style.display === '') {
                    passwordForm.style.display = 'block';
                    passwordForm.scrollIntoView({ behavior: 'smooth' });
                } else {
                    passwordForm.style.display = 'none';
                }
            });

            // Initial validation
            validateForm();
            validatePasswordForm();
        });
    </script>
</body>

</html>