<?php
include 'app/config.php';
include 'global.php';

$logged_in = false;

if (!isset($_COOKIE['access_token'])) {
    $logged_in = false;
} else {
    $logged_in = true;
}

$username = null;
$profile_picture = null;
$access_token = null;
$user_id = null;
$is_staff = false;
$is_developer = false;

if ($logged_in) {
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

    $stmt = $mysqli->prepare("SELECT username, profile_picture, is_staff, is_developer FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $username = $row['username'];
        $profile_picture = $row['profile_picture'];
        $is_staff = $row['is_staff'] === 1 ? 'true' : 'false';
        $is_developer = $row['is_developer'] === 1 ? 'true' : 'false';
    }
    $stmt->close();

    $param = json_encode(['user_id' => (int)$user_id]);
}

$ideasStmt = $mysqli->prepare("SELECT * FROM ideas");
$ideasStmt->execute();
$ideasResult = $ideasStmt->get_result();

$ideas = [];

if ($ideasResult->num_rows > 0) {
    $ideas = $ideasResult->fetch_all(MYSQLI_ASSOC) ?? [];
}

$ideasStmt->close();

foreach ($ideas as $index => $idea) {
    $userStmt = $mysqli->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
    $userStmt->bind_param("i", $idea['user_id']);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    if ($userResult->num_rows > 0) {
        $user = $userResult->fetch_assoc();
        $idea['username'] = $user['username'];
        $idea['profile_picture'] = $user['profile_picture'];
    }
    $userStmt->close();

    if ($logged_in) {
        $votedStmt = $mysqli->prepare("SELECT 1 FROM idea_votes WHERE idea_id = ? AND user_id = ?");
        $votedStmt->bind_param("si", $idea['id'], $user_id);
        $votedStmt->execute();
        $votedResult = $votedStmt->get_result();
        $idea['voted'] = $votedResult->num_rows > 0 ? 1 : 0;
        $votedStmt->close();
    } else {
        $idea['voted'] = 0;
    }

    $votesStmt = $mysqli->prepare("SELECT COUNT(*) AS votes FROM idea_votes WHERE idea_id = ?");
    $votesStmt->bind_param("s", $idea['id']);
    $votesStmt->execute();
    $votesResult = $votesStmt->get_result();
    $idea['votes'] = $votesResult->num_rows > 0 ? $votesResult->fetch_assoc()['votes'] : 0;
    $votesStmt->close();

    $idea['github_assignees'] = isset($idea['github_assignees']) ? json_decode($idea['github_assignees'], true) : [];

    $ideas[$index] = $idea;
}


function render_idea($idea) {
    $assignees = $idea['github_assignees'] ?? [];
    $assignees_html = '';
    if (!empty($assignees)) {
        $assignees_html .= '<div class="idea-assignees">';
        foreach (array_slice($assignees, 0, 5) as $assignee) {
            $assignees_html .= '<img draggable="false" class="idea-assignee-avatar" src="' . htmlspecialchars($assignee['avatar_url']) . '" title="' . htmlspecialchars($assignee['username']) . '" alt="' . htmlspecialchars($assignee['username']) . '">';
        }
        if (count($assignees) > 5) {
            $assignees_html .= '<span class="idea-assignees-overflow">+' . (count($assignees) - 5) . '</span>';
        }
        $assignees_html .= '</div>';
    }

    return '
        <div class="idea" data-id="' . $idea['id'] . '">
            <div class="idea-header">
                <p class="idea-title">' . htmlspecialchars($idea['title']) . '</p>
                <div class="idea-votes">
                    <span class="material-symbols-rounded idea-votes-icon ' . ($idea['voted'] ? 'filled' : '') . '" data-id="' . $idea['id'] . '">arrow_shape_up</span>
                    <p class="idea-votes-count" data-id="' . $idea['id'] . '">' . htmlspecialchars($idea['votes']) . '</p>
                </div>
            </div>
            <img draggable="false" class="idea-image" src="' . htmlspecialchars($idea['image_path']) . '">
            ' . $assignees_html . '
        </div>
    ';
}

?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wokki Chat - Ideas</title>
    <link rel="stylesheet" href="/assets/styles/ideas.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdn.wokki20.nl/content/jspt-v2.1.0/jspt.css">
</head>
<body>
    <div class="top-bar">
        <div class="top-bar-left">
            <img draggable="false" onclick="window.location.href = '/ideas'" class="top-bar-logo" src="/assets/images/logo-text-purple.png">
        </div>
        <div class="top-bar-right">
            <?php if ($logged_in) { ?>
            <div class="top-bar-add-idea">
                <button class="button-primary-filled top-bar-add-idea-button" id="add-idea-btn"><span class="material-symbols-rounded">add</span>New Idea</button>
            </div>
            <div class="top-bar-divider"></div>
            <div class="top-bar-profile" id="top-bar-profile">
                <img draggable="false" class="top-bar-profile-picture" src="<?php echo $profile_picture; ?>">
                <p class="top-bar-username"><?php echo $username; ?></p>
            </div>
            <div class="top-bar-profile-dropdown">
                <div class="top-bar-profile-dropdown-item" onclick="window.location.href = '/logout?from=/ideas'">
                    <span class="material-symbols-rounded top-bar-profile-dropdown-item-icon">logout</span>
                    <p class="top-bar-profile-dropdown-item-text">Logout</p>
                </div>
                <div class="top-bar-profile-dropdown-item" onclick="window.location.href = '/settings?from=/ideas'">
                    <span class="material-symbols-rounded top-bar-profile-dropdown-item-icon">settings</span>
                    <p class="top-bar-profile-dropdown-item-text">Settings</p>
                </div>
            </div>
            <?php } ?>
            <?php if (!$logged_in) { ?>
            <div class="top-bar-item">
                <a class="button-primary-filled" href="/login" style="text-decoration: none; margin-right: 25px;">Login</a>
            </div>
            <?php } ?>
        </div>
    </div>
    <div class="tabs">
        <div class="tab active" id="voting">
            <span class="material-symbols-rounded tab-icon">how_to_vote</span>
            <p class="tab-text">Voting</p>
        </div>
        <div class="tab" id="planned">
            <span class="material-symbols-rounded tab-icon">schedule</span>
            <p class="tab-text">Planned</p>
        </div>
        <div class="tab" id="implemented">
            <span class="material-symbols-rounded tab-icon">rocket_launch</span>
            <p class="tab-text">Implemented</p>
        </div>
    </div>
    <div class="ideas">
        <div class="ideas-voting" id="ideas-voting">
            <div class="ideas-voting-content">
                <?php foreach ($ideas as $idea) {
                    if ($idea['status'] === 'voting') echo render_idea($idea);
                } ?>
            </div>
        </div>
        <div class="ideas-planned" id="ideas-planned">
            <div class="ideas-planned-content">
                <?php foreach ($ideas as $idea) {
                    if ($idea['status'] === 'planned') echo render_idea($idea);
                } ?>
            </div>
        </div>
        <div class="ideas-implemented" id="ideas-implemented">
            <div class="ideas-implemented-content">
                <?php foreach ($ideas as $idea) {
                    if ($idea['status'] === 'implemented') echo render_idea($idea);
                } ?>
            </div>
        </div>
    </div>
    <wchat-data id="is_developer" value="<?php echo $is_developer; ?>"></wchat-data>
    <wchat-data id="is_staff" value="<?php echo htmlspecialchars($is_staff, ENT_QUOTES, 'UTF-8'); ?>"></wchat-data>
    <wchat-data id="ideas" value="<?php echo htmlspecialchars(json_encode($ideas), ENT_QUOTES, 'UTF-8'); ?>"></wchat-data>
    <wchat-data id="logged_in" value="<?php echo htmlspecialchars($logged_in, ENT_QUOTES, 'UTF-8'); ?>"></wchat-data>
    <wchat-data id="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"></wchat-data>
    <wchat-data id="access_token" value="<?php echo htmlspecialchars($access_token, ENT_QUOTES, 'UTF-8'); ?>"></wchat-data>
    <wchat-data id="user_id" value="<?php echo htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8'); ?>"></wchat-data>
    <wchat-data id="profile_picture" value="<?php echo htmlspecialchars($profile_picture, ENT_QUOTES, 'UTF-8'); ?>"></wchat-data>

    <script src="/assets/js/ideas.js" type="module"></script>
</body>
</html>