<?php
include 'app/config.php';

function maxAccountsForAlpha($mysqli) {
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS count FROM users WHERE email_verified = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $row = $result->fetch_assoc();
    return $row['count'] >= 500;
}
?>
<!DOCTYPE html>
<html lang="en" class="login dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="assets/styles/main.css">
</head>
<body>
    <div class="login-bg">
        <img class="bg-img" id="bg-super-low" src="/assets/images/login-bg-super-low.png" />
        <img class="bg-img" id="bg-low" />
        <img class="bg-img" id="bg-normal" />
        <img class="bg-img" id="bg-full" />
    </div>
    <div class="register-form">
        <div class="register-form-content">
            <h2>Create Account</h2>
            <div class="input-container">
                <label for="nickname">Username:</label>
                <input type="text" name="nickname" placeholder="myUsername" required class="input-text-dark-bg w270" minlength="3" maxlength="20" autocomplete="off" id="username">
            </div>
            <div class="input-container">
                <label for="username">Email:</label>
                <input type="email" name="username" placeholder="johndoe@example.com" required class="input-text-dark-bg w270" minlength="3" autocomplete="username" id="email">
            </div>
            <div class="input-container">
                <label for="password">Password:</label>
                <input type="password" name="password" placeholder="myVeryStrongPassword123" required class="input-text-dark-bg w270" minlength="8" autocomplete="new-password" id="password">
            </div>
            <div class="input-container">
                <div class="terms-container">
                    <input type="checkbox" id="accept-terms" required>
                    <label for="accept-terms">
                        I accept the <a href="https://chat.wokki20.nl/legal/privacy" class="link" target="_blank">Privacy Policy</a> and 
                        <a href="https://chat.wokki20.nl/legal/terms" class="link" target="_blank">Terms of Service</a>
                    </label>
                </div>
            </div>
            <button type="submit" <?php if (maxAccountsForAlpha($mysqli)) echo 'disabled'; ?> class="button-primary-filled" onclick="create_account()">Create Account</button>
            <p>Already have an account? <a href="login" class="link">Login</a></p>
            <p class="error-message" id="error-message">An unknown error occurred, please try again</p>
        </div>
    </div>
    <script src="assets/js/register.js"></script>
</body>
</html>