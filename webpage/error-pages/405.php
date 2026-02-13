<?php
include '../app/config.php';
include '../global.php';
include '../app/maintenance.php';

$access_token = $_COOKIE['access_token'] ?? '';

$stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ?");
$stmt->bind_param("s", $access_token);
$stmt->execute();
$result = $stmt->get_result();
$user_id = null;
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
}
$stmt->close();

$headerBtn = '<a href="login" class="button-primary-outline no-underline">Log In</a>';

if ($user_id) {
    $headerBtn = '<a href="home" class="button-primary-outline no-underline">Open Wokki Chat</a>';
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wokki Chat - Method Not Allowed</title>
    <link rel="stylesheet" href="/assets/styles/index.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body>
    <div class="hover-overlay"></div>
    <div class="main-content no-gradient error">
        <div class="sticky-header">
            <a href="/" class="logo"></a>
            <?php echo $headerBtn; ?>
        </div>
        <div class="fixed-header">
            <a href="https://chat.wokki20.nl/support" class="header-option" tabindex="0">Support</a>
            <div class="header-option multiple" tabindex="0">
                <p class="header-option-text">Legal</p>
                <div class=header-option-dropdown>
                    <a href="https://chat.wokki20.nl/legal/privacy" class="link">Privacy Policy</a>
                    <a href="https://chat.wokki20.nl/legal/terms" class="link">Terms of Service</a>
                </div>
            </div>
            <div class="header-option multiple" tabindex="0">
                <p class="header-option-text">Developers</p>
                <div class=header-option-dropdown>
                    <a href="https://chat.wokki20.nl/developer" class="link">Developer Portal</a>
                    <a href="https://chat.wokki20.nl/developer/docs" class="link">Developer Documentation</a>
                    <a href="https://chat.wokki20.nl/developer/docs#sdk" class="link">Wokki Chat Bots SDK</a>
                </div>
            </div>
        </div>
        <div class="error-content">
            <h1 class="page-title">Method Not Allowed</h1>
            <span class="page-description">You attempted to access a page using a method that is not allowed.</span>
            <span class="page-description">Please check the URL and method and try again.</span>
            <span class="page-description">If you're still having issues, here are some links that might help:</span>
            <a class="page-description link" href="https://chat.wokki20.nl/support">Contact Support</a>
            <a class="page-description link" href="https://status.chat.wokki20.nl">Status Page</a>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section">
                <h3 class="footer-logo">Wokki Chat</h3>
                <p>Bringing people together, one message at a time.</p>
                <p class="copyright">&copy; 2026 Wokki Chat</p>
            </div>

            <div class="footer-section">
                <h4>Legal</h4>
                <nav>
                    <a href="https://chat.wokki20.nl/legal/privacy" class="link">Privacy Policy</a>
                    <a href="https://chat.wokki20.nl/legal/terms" class="link">Terms of Service</a>
                </nav>
            </div>

            <div class="footer-section">
                <h4>Network</h4>
                <nav>
                    <a href="https://status.chat.wokki20.nl" class="link" target="_blank">System Status</a>
                    <a href="https://chat.wokki20.nl/invite/wokkichat" class="link">Join the Official Server</a>
                </nav>
            </div>
        </div>
    </footer>

    <script src="assets/js/index.js"></script>
</body>
</html>