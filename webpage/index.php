<?php
include 'app/config.php';
include 'global.php';
include 'app/maintenance.php';

$access_token = $_COOKIE['access_token'];

$stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ?");
$stmt->bind_param("s", $access_token);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
}
$stmt->close();

$headerBtn = '<a href="login" class="button-primary-outline no-underline">Login</a>';

if ($user_id) {
    $headerBtn = '<a href="home" class="button-primary-outline no-underline">Open Wokki Chat</a>';
}

?>
<!DOCTYPE html>
<html lang="en" class="night">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wokki Chat - Connect with friends, share your world & make every conversation count</title>
    <link rel="stylesheet" href="assets/styles/index.css">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body>
    <div class="hover-overlay"></div>
    <div class="main-content">
        <div class="sticky-header">
            <a href="/"><img src="assets/images/logo-text-purple.png" alt="Wokki Chat" class="logo"></a>
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
                    <a href="https://chat.wokki20.nl/developer/bots-sdk" class="link">Wokki Chat Bots SDK</a>
                </div>
            </div>
        </div>
        <div class="center-text">
            <div class="slogan">
                <div class="top-line">Connect with friends,</div>
                
                <div class="bottom-section">
                    <div class="ampersand">&</div>
                    <div class="text-stack">
                        <div>Share your world</div>
                        <div>Make every conversation count</div>
                    </div>
                </div>
            </div>
            <div class="description">
                <span class="description-text">Wokki Chat is the ultimate place to connect with friends, share your thoughts and moments freely,</span>
                <span class="description-text">exciting conversations, discover new connections, and keep the people you care about just a click away.</span>
                <span class="description-text">All of that without paying a dime.</span>
            </div>
        </div>
    </div>
    <div class="start-using-today">
        <a href="login" class="button-primary-filled no-underline">Start using Wokki Chat today</a>
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