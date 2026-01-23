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
    <title>Wokki Chat - FAQ</title>
    <link rel="stylesheet" href="/assets/styles/index.css">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body>
    <div class="hover-overlay"></div>
    <div class="main-content no-gradient">
        <div class="sticky-header">
            <a href="/"><img src="/assets/images/logo-text-purple.png" alt="Wokki Chat" class="logo"></a>
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

        <div class="faq-content">
            <h1 class="page-title">Frequently Asked Questions</h1>
            <p class="page-description">Find answers to common questions about Wokki Chat below.</p>

            <div class="faq-list">
                <div class="faq-item">
                    <h3>Why is Wokki Chat experiencing bugs?</h3>
                    <p>Wokki Chat is currently in its alpha stage, which means some features may be unstable or under development. We appreciate your patience as we continue to improve the platform.</p>
                </div>
                <div class="faq-item">
                    <h3>Why am I unable to send messages?</h3>
                    <p>Please ensure you are logged in and that your account has no restrictions. Additionally, check your network connection and make sure you are following the server's rules.</p>
                </div>
                <div class="faq-item">
                    <h3>How can I create a bot for Wokki Chat?</h3>
                    <p>You can learn how to create and register a bot by visiting our <a class="link" href="https://chat.wokki20.nl/developer/docs/">Developer Documentation</a>. Bots can be created and managed through the <a class="link" href="https://chat.wokki20.nl/developer/bots/">Bot Management Portal</a>.</p>
                </div>
                <div class="faq-item">
                    <h3>How can I contribute to the development of Wokki Chat?</h3>
                    <p>At this time, staff applications are closed. We will announce opportunities to contribute in the future.</p>
                </div>
                <div class="faq-item">
                    <h3>How do I contact support?</h3>
                    <p>For assistance, you can reach out to our support team via email at <a class="link" href="mailto:support@chat.wokki20.nl">support@chat.wokki20.nl</a>.</p>
                </div>
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

    <script>
        const faqItems = document.querySelectorAll('.faq-item');
        faqItems.forEach(item => {
            item.addEventListener('click', () => {
                item.classList.toggle('active');
            });
        });
    </script>
</body>
</html>