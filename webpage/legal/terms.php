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
    <title>Wokki Chat - Terms of Service</title>
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
                    <a href="https://chat.wokki20.nl/developer/bots-sdk" class="link">Wokki Chat Bots SDK</a>
                </div>
            </div>
        </div>

        <div class="legal-content">
            <h1 class="page-title">Terms of Service</h1>
            <p class="page-description">Last updated: 24th of January 2026.</p>

            <div class="legal-container">
                <h2>1. Acceptance of Terms</h2>
                <p>By accessing or using Wokki Chat, you agree to these Terms of Service. If you do not agree, do not use our platform. These terms apply to all features, including chat, Spotify integration, and any future updates or services.</p>

                <h2>2. Age Requirement</h2>
                <p>You must be at least 13 years old (or the minimum age required by your government) to use Wokki Chat. Users under the required age must have a parent or guardian's consent. By creating an account, you confirm that you meet these age requirements or have guardian approval.</p>

                <h2>3. Account Responsibilities</h2>
                <ul>
                    <li>Keep your account credentials secure. Wokki Chat is not responsible for unauthorized access to your account.</li>
                    <li>Only you are responsible for the content you post and share on the platform.</li>
                    <li>You may not impersonate Wokki Chat or its owner (wokki20).</li>
                </ul>

                <h2>4. User Conduct</h2>
                <p>Users are expected to behave respectfully. Harassment, hate speech, threats, or any offensive behavior will not be tolerated. Users violating these rules may have their accounts suspended or terminated.</p>

                <h2>5. Connections & Data Usage</h2>

                <h3>Spotify Connection</h3>
                <p>By enabling Spotify, you consent to Wokki Chat storing your access token, refresh token, user ID, username, user URI, and profile image. Tokens are only used to retrieve your currently playing music if you have this feature enabled (enabled by default). Tokens cannot be used for any other purposes beyond retrieving your music.</p>

                <h3>Chat Connection</h3>
                <p>For chat functionality, your username, user URL, and profile image will be stored in our database. This data allows other users to see your profile information in chat.</p>

                <h2>6. Prohibited Activities</h2>
                <ul>
                    <li>Do not attempt to reverse-engineer, hack, or exploit Wokki Chat (Unless authorized).</li>
                    <li>Do not advertise or claim ownership of Wokki Chat except for wokki20.</li>
                    <li>Do not share harmful content, spam, or malware.</li>
                </ul>

                <h2>7. Termination of Use</h2>
                <p>Wokki Chat may suspend or terminate accounts that violate these Terms or act maliciously. Users who are removed may not be allowed to return. Termination does not remove the platform's right to retain certain information for legal or administrative purposes.</p>

                <h2>8. Modifications to Terms</h2>
                <p>Wokki Chat may update these Terms at any time. Continued use of the platform constitutes acceptance of the updated Terms. Users are encouraged to review the Terms periodically.</p>

                <h2>9. Contact</h2>
                <p>If you have questions about these Terms, you can contact us at <strong><a href="mailto:support@wokki20.nl">support@wokki20.nl</a></strong>.</p>
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