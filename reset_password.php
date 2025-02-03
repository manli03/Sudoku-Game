<?php
require 'db.php';

$message = '';
$messageType = '';
$account_email = '';

// Redirect to forgot_password.php if no token parameter is present
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['token'])) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body>
        <script>
            Swal.fire({
                title: 'Error',
                text: 'No reset token provided. Redirecting to forgot password page.',
                icon: 'error',
                timer: 3000,
                timerProgressBar: true,
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'forgot_password.php';
            });
        </script>
    </body>
    </html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Fetch the account associated with the token from 'users' table
        $stmt = $db->prepare("SELECT email FROM users WHERE reset_token = :token AND reset_expires > NOW() LIMIT 1");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $account_email = $user['email'];
        } else {
            echo "<!DOCTYPE html>
            <html>
            <head>
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        title: 'Error',
                        text: 'Invalid or expired reset token.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'forgot_password.php';
                    });
                </script>
            </body>
            </html>";
            exit;
        }
    } catch (PDOException $e) {
        // Handle database errors
        echo "<!DOCTYPE html>
        <html>
        <head>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred. Please try again later.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'forgot_password.php';
                });
            </script>
        </body>
        </html>";
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm-password'] ?? '';

    // Check if passwords match
    if ($new_password !== $confirm_password) {
        header("Location: reset_password.php?token=" . urlencode($token) . "&error=password_mismatch");
        exit;
    }

    // Validate password length
    if (strlen($new_password) < 8) {
        header("Location: reset_password.php?token=" . urlencode($token) . "&error=weak_password");
        exit;
    }

    try {
        // Verify the token and find the associated account
        $stmt = $db->prepare("SELECT email FROM users WHERE reset_token = :token AND reset_expires > NOW() LIMIT 1");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $account_email = $user['email'];

            // Hash the new password and update it in the database
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $updateStmt = $db->prepare("UPDATE users SET password = :password, reset_token = NULL, reset_expires = NULL WHERE email = :email");
            $updateStmt->execute([
                'password' => $hashed_password,
                'email' => $account_email
            ]);

            echo "<!DOCTYPE html>
            <html>
            <head>
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        title: 'Success',
                        text: 'Your password has been reset successfully.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                </script>
            </body>
            </html>";
            exit;
        } else {
            echo "<!DOCTYPE html>
            <html>
            <head>
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        title: 'Error',
                        text: 'Invalid or expired reset token.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'forgot_password.php';
                    });
                </script>
            </body>
            </html>";
            exit;
        }
    } catch (PDOException $e) {
        // Handle database errors
        echo "<!DOCTYPE html>
        <html>
        <head>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred. Please try again later.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'forgot_password.php';
                });
            </script>
        </body>
        </html>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset your Sudoku account password.">
    <title>Reset Password</title>
    <link rel="icon" href="https://codex.berhad.com.my/wp-content/uploads/2023/04/Logo_Merged-51x51.png" sizes="32x32">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.9.3/css/bulma.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(to right, #f5f7fa, #c3cfe2);
            margin: 0;
            font-family: 'Roboto', sans-serif;
        }

        .reset-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 400px;
            text-align: center;
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }

        .error-message {
            color: red;
            font-size: 0.85rem;
            text-align: left;
            margin-top: 5px;
            margin-bottom: 10px;
        }

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

<body>
    <div class="reset-container">
        <?php if ($message): ?>
            <script>
                Swal.fire({
                    title: '<?php echo $messageType === 'success' ? 'Success!' : 'Error'; ?>',
                    text: '<?php echo $message; ?>',
                    icon: '<?php echo $messageType; ?>',
                    confirmButtonText: 'OK'
                }).then(() => {
                    <?php if ($messageType === 'success'): ?>
                        window.location.href = 'index.php';
                    <?php else: ?>
                        window.location.href = 'forgot_password.php';
                    <?php endif; ?>
                });
            </script>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'password_mismatch'): ?>
            <script>
                Swal.fire({
                    title: 'Error',
                    text: 'Passwords do not match. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            </script>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'weak_password'): ?>
            <script>
                Swal.fire({
                    title: 'Error',
                    text: 'Password must be at least 8 characters long.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            </script>
        <?php endif; ?>

        <?php if ($account_email): ?>
            <h2 class="title is-4 has-text-primary">Reset Password</h2>
            <p>Resetting password for: <strong><?php echo htmlspecialchars($account_email); ?></strong></p><br>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                <div class="field">
                    <label class="label">New Password</label>
                    <div class="control has-icons-right">
                        <input type="password" id="password" name="password" class="input" placeholder="Enter new password"
                            required>
                        <span class="password-toggle" id="toggle-password">
                            <i id="toggle-password-icon" class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                <div class="password-requirements mt-2 mb-4 has-text-left" id="password-requirements">
                    <p id="length-req" class="has-text-danger">
                        <i class="fas fa-times-circle"></i> At least 8 characters
                    </p>
                </div>
                <div class="field">
                    <label class="label">Confirm Password</label>
                    <div class="control has-icons-right">
                        <input type="password" id="confirm-password" name="confirm-password" class="input"
                            placeholder="Confirm new password" required>
                        <span class="password-toggle" id="toggle-confirm-password">
                            <i id="toggle-confirm-password-icon" class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                <p id="confirm-password-error" class="error-message"></p>
                <div class="field">
                    <div class="control">
                        <button type="submit" id="reset-button" class="button is-primary is-fullwidth" disabled>Reset
                            Password</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        $(document).ready(function () {
            $('#password').on('input', validateForm);
            $('#confirm-password').on('input', validateForm);

            $('#toggle-password').on('click', function () {
                togglePassword('password', 'toggle-password-icon');
            });

            $('#toggle-confirm-password').on('click', function () {
                togglePassword('confirm-password', 'toggle-confirm-password-icon');
            });

            // Updated JavaScript for Form Validation
            function validateForm() {
                const password = $('#password').val();
                const confirmPassword = $('#confirm-password').val();

                // Validate password length
                const isPasswordValid = password.length >= 8;

                // Update requirement indicator
                const lengthReq = $('#length-req');
                if (isPasswordValid) {
                    lengthReq.removeClass('has-text-danger').addClass('has-text-success');
                    lengthReq.find('i').removeClass('fa-times-circle').addClass('fa-check-circle');
                } else {
                    lengthReq.removeClass('has-text-success').addClass('has-text-danger');
                    lengthReq.find('i').removeClass('fa-check-circle').addClass('fa-times-circle');
                }

                // Check if passwords match
                const doPasswordsMatch = password === confirmPassword;

                // Show/hide password mismatch message
                if (password && confirmPassword) {
                    $('#confirm-password-error').text(doPasswordsMatch ? '' : 'Passwords do not match.').toggle(!doPasswordsMatch);
                } else {
                    $('#confirm-password-error').text('').hide();
                }

                // Enable/disable submit button
                const isFormValid = isPasswordValid && doPasswordsMatch;
                $('#reset-button').prop('disabled', !isFormValid);
            }


            function togglePassword(fieldId, iconId) {
                const field = document.getElementById(fieldId);
                const icon = document.getElementById(iconId);

                if (field.type === 'password') {
                    field.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    field.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        });
    </script>
</body>

</html>