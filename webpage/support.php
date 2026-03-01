<?php
include 'app/config.php';
include 'global.php';
include 'app/maintenance.php';

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
    <title>Wokki Chat - Support</title>
    <link rel="stylesheet" href="assets/styles/index.css">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body>
    <div class="hover-overlay"></div>
    <div class="main-content no-gradient">
        <div class="sticky-header">
            <a href="/" class="logo"></a>
            <?php echo $headerBtn; ?>
        </div>
        <div class="fixed-header">
            <a href="/support" class="header-option" tabindex="0">Support</a>
            <div class="header-option multiple" tabindex="0">
                <p class="header-option-text">Legal</p>
                <div class=header-option-dropdown>
                    <a href="/legal/privacy" class="link">Privacy Policy</a>
                    <a href="/legal/terms" class="link">Terms of Service</a>
                </div>
            </div>
            <div class="header-option multiple" tabindex="0">
                <p class="header-option-text">Developers</p>
                <div class=header-option-dropdown>
                    <a href="/developer" class="link">Developer Portal</a>
                    <a href="/developer/docs" class="link">Developer Documentation</a>
                    <a href="/developer/docs#sdk" class="link">Wokki Chat Bots SDK</a>
                </div>
            </div>
        </div>
        <div class="support-content">
            <h1 class="page-title">Need Help with Wokki Chat?</h1>
            <p class="page-description">We're here to help you get the most out of Wokki Chat. Browse FAQs, contact support, or check our status.</p>

            <div class="support-options">
                <div class="support-card">
                    <h2>📄 FAQs</h2>
                    <p>Find answers to common questions about Wokki Chat.</p>
                    <a href="/support/faqs" class="button-primary-outline no-underline">Browse FAQs</a>
                </div>
                <div class="support-card">
                    <h2>📧 Email Support</h2>
                    <p>Need direct help? Our support team is ready to assist you.</p>
                    <a href="mailto:support@cm.wokki20.nl" class="button-primary-outline no-underline">Email Us</a>
                </div>
                <div class="support-card">
                    <h2>⚙️ System Status</h2>
                    <p>Check if Wokki Chat is running smoothly right now.</p>
                    <a href="https://status.chat.wokki20.nl" target="_blank" class="button-primary-outline no-underline">View Status</a>
                </div>
            </div>
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