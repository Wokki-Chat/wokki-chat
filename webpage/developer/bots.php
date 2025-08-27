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

$botsStmt = $mysqli->prepare("SELECT id, name, profile_picture FROM bots WHERE created_by = ?");
$botsStmt->bind_param("i", $user_id);
$botsStmt->execute();
$botsResult = $botsStmt->get_result();
$bots = $botsResult->fetch_all(MYSQLI_ASSOC);
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
</head>
<body>
    <div class="bots-container">
        <div class="developer-header">
            <h1 class="developer-title">Bots</h1>
            <p class="developer-description">You can develop bots to enhance your wokki chat server and elevate its functionality to the next level.</p>
        </div>
        <div class="developer-content">
            <h3>Your Bots:</h3>
            <div class="developer-bots">
                <?php
                foreach ($bots as $bot) {
                    echo '
                    <div class="developer-bot" onclick="window.location.href = \'/developer/bot/' . $bot['id'] . '\' ">
                        <img class="developer-bot-profile-picture" src="' . $bot['profile_picture'] . '" alt="' . $bot['name'] . '">
                        <p class="developer-bot-name">' . $bot['name'] . '</p>
                    </div>';
                }       
                ?>
                <div class="developer-bot" id="add-bot" onclick="openCreateBotModal()">
                    <div class="add-bot-container"><span class="material-symbols-rounded add-bot">add</span></div>
                    <p class="developer-bot-name">Create New Bot</p>
                </div>
            </div>
        </div>
    </div>
    <script src="/assets/js/bots.js"></script>
    <script>
        // DO NOT TOUCH OR EDIT
        const access_token = "<?php echo $access_token; ?>";
        const user_id = "<?php echo $user_id; ?>";
    </script>
</body>
</html>
