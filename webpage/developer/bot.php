<?php
include '../app/config.php';
$access_token = $_COOKIE['access_token'];

if (!$access_token) {
    header('Location: /login?redirect=/developer/bots');
    exit;
}

$stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ?");
$stmt->bind_param("s", $access_token);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
}
$stmt->close();

if (!$user_id) {
    header('Location: /login?redirect=/developer/bots');
    exit;
}

$stmt = $mysqli->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $username = $row['username'];
    $profile_picture = $row['profile_picture'];
}
$stmt->close();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

$bot_id = null;
foreach ($segments as $key => $segment) {
    if ($segment === 'bot' && isset($segments[$key + 1]) && !empty($segments[$key + 1])) {
        $bot_id = $segments[$key + 1];
        break;
    }
}

if (!$bot_id) {
    header('Location: /developer/bots');
    exit;
}

$botsStmt = $mysqli->prepare("SELECT name, profile_picture, bot_token FROM bots WHERE id = ?");
$botsStmt->bind_param("s", $bot_id);
$botsStmt->execute();
$botsResult = $botsStmt->get_result();
$bot = $botsResult->fetch_assoc();
$botsStmt->close();

?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>wokki chat</title>
    <link rel="stylesheet" href="/assets/styles/main.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
</head>
<body>
    <div class="bots-container">
        <div class="developer-header">
            <h1 class="developer-title"><?php echo $bot['name']; ?></h1>
            <p class="developer-description">Here you'll find the general information regarding your bot.</p>
        </div>
        <div class="developer-content">
            <div class="developer-bot-profile">
                
                <div class="developer-bot-profile-picture-preview-container">
                    <img src="<?php echo $bot['profile_picture']; ?>" alt="Profile Picture" class="profile-picture-preview">
                    <span class="hover-text material-symbols-rounded">upload</span>
                </div>

                <div class="developer-bot-profile-info">
                    <div class="developer-bot-profile-info-container">
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" class="input-text-dark-bg w270" value="<?php echo $bot['name']; ?>">
                        <br>
                        <label for="bot-token">Bot Token:</label>
                        <input type="password" id="bot-token" name="bot-token" class="input-text-dark-bg w270" value="<?php echo $bot['bot_token']; ?>" readonly>
                        <div class="bot-token-buttons">
                            <button class="button-primary-filled" id="show-btn">Show Token</button>
                            <button class="button-primary-filled" id="copy-btn">Copy Token</button>
                        </div>
                        <br>
                        <label for="bot-invite">Bot Invite:</label>
                        <input type="text" id="bot-invite" name="bot-invite" class="input-text-dark-bg w270" value="https://chat.wokki20.nl/bot/invite/<?php echo $bot_id; ?>" readonly>
                        <div class="bot-token-buttons">
                            <button class="button-primary-filled" id="copy-invite-btn">Copy Invite</button>
                        </div>
                    </div>
                    <button class="button-primary-filled" id="save-bot-button">Save Bot</button>
                </div>
                
            </div>
        </div>
        
    </div>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="/assets/js/bot.js"></script>
    <script>
        // DO NOT TOUCH OR EDIT
        const access_token = "<?php echo $access_token; ?>";
        const user_id = "<?php echo $user_id; ?>";

        const originalBotName = "<?php echo $bot['name']; ?>";
        const bot_id = "<?php echo $bot_id; ?>";
    </script>
</body>
</html>
