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
    <title>Wokki Chat - Privacy Policy</title>
    <link rel="stylesheet" href="../assets/styles/index.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
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

        <div class="legal-content">
            <h1 class="page-title">Privacy Policy</h1>
            <p class="page-description">Last updated: 24th of January 2026.</p>

            <div class="legal-container">
                <h2>1. Information We Collect</h2>
                <ul>
                    <li><strong>Chat:</strong> Username, user URL, profile image.</li>
                    <li><strong>Spotify Connection:</strong> Access token, refresh token, user ID, username, user URI, profile image. Tokens are only used to retrieve your currently playing music.</li>
                </ul>

                <h2>2. How We Use Your Information</h2>
                <ul>
                    <li>To provide and maintain chat and connection features.</li>
                    <li>To display your currently playing music (if Spotify integration is enabled).</li>
                    <li>To ensure platform security and compliance with our Terms of Service.</li>
                </ul>

                <h2>3. Sharing and Disclosure</h2>
                <p>We do not sell or share your personal information for advertising purposes. Data may only be accessed by authorized personnel for platform maintenance, moderation, or legal obligations.</p>

                <h2>4. Data Storage and Security</h2>
                <p>We store user data securely in our database and take reasonable technical and administrative measures to protect your information from unauthorized access, alteration, disclosure, or destruction.</p>

                <h2>5. User Rights</h2>
                <ul>
                    <li>You can request the deletion of your account and all associated data at any time.</li>
                    <li>You can disable Spotify integration at any time, this will still keep the tokens stored in our database.</li>
                    <li>You can update or remove your profile information by contacting support.</li>
                </ul>

                <h2>6. Children's Privacy</h2>
                <p>Wokki Chat is not intended for children under the age of 13 (or the age required by local law) without parental consent. Users under the required age must have guardian approval to use the platform.</p>

                <h2>7. Cookies & Tracking</h2>
                <p>Wokki Chat may use cookies or similar technologies for functional purposes such as keeping you logged in, personalizing your experience, and monitoring usage to improve our services.</p>

                <h2>8. Changes to Privacy Policy</h2>
                <p>Wokki Chat may update this Privacy Policy at any time. Continued use of the platform constitutes acceptance of any changes. Users should check this page periodically to stay informed about updates.</p>

                <h2>9. Contact</h2>
                <p>If you have questions about these Terms, you can contact us at <strong><a class="link" href="mailto:support@wokki20.nl">support@wokki20.nl</a></strong>.</p>
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
</body>
</html>