<?php
include 'config.php';
include '_scopes.php';

error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (isset($_SERVER['HTTP_ORIGIN'])) {
	header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Max-Age: 0');
}

header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: text/html; charset=utf-8');

function generateUUIDv4() {
	$data = random_bytes(16);
	$data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
	$data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function generate_token(int $bytes = 32): string {
	return bin2hex(random_bytes($bytes));
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	exit;
}

$client_id = $_GET['client_id'] ?? $_POST['client_id'] ?? null;
$redirect_uri = $_GET['redirect_uri'] ?? $_POST['redirect_uri'] ?? null;
$scopes = $_GET['scopes'] ?? $_POST['scopes'] ?? null;
$state = $_GET['state'] ?? $_POST['state'] ?? null;
$response_type = $_GET['response_type'] ?? 'code';

if (!$client_id || !$redirect_uri) {
	http_response_code(400);
	echo 'Missing client_id or redirect_uri.';
	exit;
}

if ($response_type !== 'code') {
	http_response_code(400);
	echo 'Unsupported response_type.';
	exit;
}

if ($scopes) {
	$requested_scopes = array_filter(explode(' ', $scopes));
	if (!validate_scopes($requested_scopes)) {
		http_response_code(400);
		echo 'One or more requested scopes are invalid.';
		exit;
	}
}

$stmt = $mysqli->prepare("SELECT * FROM oauth_clients WHERE client_id = ? AND is_active = 1 AND revoked_at IS NULL");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$stmt->close();

if (!$client) {
	http_response_code(400);
	echo 'Unknown or inactive client.';
	exit;
}

$bot = null;
if (!empty($client['bot_id'])) {
	$stmt = $mysqli->prepare("SELECT name, profile_picture, bio FROM bots WHERE id = ?");
	$stmt->bind_param("s", $client['bot_id']);
	$stmt->execute();
	$result = $stmt->get_result();
	$bot = $result->fetch_assoc();
	$stmt->close();
}

$stmt = $mysqli->prepare("SELECT uri FROM oauth_client_redirect_uris WHERE client_id = ?");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$registered_uris = [];
while ($row = $result->fetch_assoc()) {
	$registered_uris[] = $row['uri'];
}
$stmt->close();

if (!in_array($redirect_uri, $registered_uris, true)) {
	http_response_code(400);
	echo 'Redirect URI is not registered for this client.';
	exit;
}

$access_token = $_COOKIE['access_token'] ?? null;
$user_id = null;
$current_user = null;

if ($access_token) {
	$stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ? AND access_token_expires_at > NOW()");
	$stmt->bind_param("s", $access_token);
	$stmt->execute();
	$result = $stmt->get_result();
	$token_row = $result->fetch_assoc();
	$stmt->close();
	if ($token_row) {
		$user_id = $token_row['user_id'];
		$stmt = $mysqli->prepare("SELECT id, username, email, profile_picture FROM users WHERE id = ?");
		$stmt->bind_param("s", $user_id);
		$stmt->execute();
		$result = $stmt->get_result();
		$current_user = $result->fetch_assoc();
		$stmt->close();
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$submitted_csrf = $_POST['csrf_token'] ?? null;
	$cookie_csrf = $_COOKIE['authorize_csrf'] ?? null;

	if (!$submitted_csrf || !$cookie_csrf || !hash_equals($cookie_csrf, $submitted_csrf)) {
		http_response_code(403);
		echo 'Invalid or missing CSRF token.';
		exit;
	}

	setcookie('authorize_csrf', '', time() - 3600, '/', '', true, true);

	$action = $_POST['action'] ?? null;

	if ($action === 'deny') {
		$location = $redirect_uri . (str_contains($redirect_uri, '?') ? '&' : '?') . 'error=access_denied';
		if ($state) $location .= '&state=' . urlencode($state);
		header('Location: ' . $location);
		exit;
	}

	if ($action === 'approve') {
		if (!$user_id) {
			http_response_code(403);
			echo 'No authenticated user.';
			exit;
		}

		$code = generate_token(32);
		$code_id = generateUUIDv4();
		$expires_at = date('Y-m-d H:i:s', time() + 600);

		$hash = hash('sha256', $code);

		$stmt = $mysqli->prepare("INSERT INTO oauth_authorization_codes (id, code, client_id, user_id, redirect_uri, scopes, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("sssssss", $code_id, $hash, $client_id, $user_id, $redirect_uri, $scopes, $expires_at);
		$stmt->execute();
		$stmt->close();

		$location = $redirect_uri . (str_contains($redirect_uri, '?') ? '&' : '?') . 'code=' . urlencode($code);
		if ($state) $location .= '&state=' . urlencode($state);
		header('Location: ' . $location);
		exit;
	}
}

$csrf_token = generate_token(32);
setcookie('authorize_csrf', $csrf_token, [
	'expires'  => time() + 600,
	'path'     => '/',
	'secure'   => true,
	'httponly' => true,
	'samesite' => 'Strict',
]);

$display_name = ($bot && !empty($bot['name'])) ? $bot['name'] : $client['name'];
$login_redirect = urlencode('https://api-chat.wokki20.nl/authorize?' . http_build_query(['client_id' => $client_id, 'redirect_uri' => $redirect_uri, 'response_type' => $response_type, 'scopes' => $scopes, 'state' => $state]));
$form_action = 'authorize?' . htmlspecialchars(http_build_query(['client_id' => $client_id, 'redirect_uri' => $redirect_uri, 'response_type' => $response_type, 'scopes' => $scopes, 'state' => $state]));
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Wokki Chat - Authorize <?= htmlspecialchars($display_name) ?></title>
	<link rel="stylesheet" href="assets/styles/main.css">
	<link rel="icon" type="image/x-icon" href="favicon.ico">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body>
	<div class="login-bg">
		<img class="bg-img" id="bg-super-low" src="assets/images/login-bg-super-low.png" />
		<img class="bg-img" id="bg-low" />
		<img class="bg-img" id="bg-normal" />
		<img class="bg-img" id="bg-full" />
	</div>
	<form action="<?= $form_action ?>" method="post" class="login-form">
		<div class="login-form-content">

			<div class="bot-profile">
				<?php if ($bot && !empty($bot['profile_picture'])): ?>
					<img class="bot-avatar" src="https://chat.wokki20.nl<?= htmlspecialchars($bot['profile_picture']) ?>" alt="<?= htmlspecialchars($display_name) ?>">
				<?php else: ?>
					<div class="bot-avatar bot-avatar-placeholder"><?= htmlspecialchars(mb_substr($display_name, 0, 1)) ?></div>
				<?php endif; ?>
				<p class="bot-name"><?= htmlspecialchars($display_name) ?></p>
				<?php if ($bot && !empty($bot['bio'])): ?>
					<p class="bot-bio"><?= htmlspecialchars($bot['bio']) ?></p>
				<?php endif; ?>
			</div>

			<div class="divider"></div>

			<p class="authorize-description"><?= htmlspecialchars($display_name) ?> wants to access your account.</p>
			<?php if ($scopes): ?>
				<?php
					$requested_scopes = array_filter(explode(' ', $scopes));
				?>
				<div class="authorize-scopes">
					<p class="authorize-scopes-label">This app will be able to:</p>
					<ul class="authorize-scopes-list">
						<?php foreach ($requested_scopes as $scope): ?>
							<?php if (isset($scope_descriptions[$scope])): ?>
								<li class="authorize-scope-item">
									<span class="scope-icon"><?= $scope_descriptions[$scope][1] ?></span>
									<span class="scope-text"><?= htmlspecialchars($scope_descriptions[$scope][0]) ?></span>
									<?php if (isset(SCOPE_EXPANSIONS[$scope])): ?>
										<ul class="authorize-scope-children">
											<?php foreach (SCOPE_EXPANSIONS[$scope] as $child): ?>
												<?php if (isset($scope_descriptions[$child])): ?>
													<li><?= htmlspecialchars($scope_descriptions[$child][0]) ?></li>
												<?php endif; ?>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="user-switcher" id="userSwitcher">
				<button type="button" class="user-switcher-trigger" id="userSwitcherTrigger">
					<?php if ($current_user): ?>
						<?php if (!empty($current_user['profile_picture'])): ?>
							<img class="user-switcher-avatar" src="https://chat.wokki20.nl<?= htmlspecialchars($current_user['profile_picture']) ?>" alt="">
						<?php else: ?>
							<div class="user-switcher-avatar user-switcher-avatar-placeholder"><?= htmlspecialchars(mb_substr($current_user['username'], 0, 1)) ?></div>
						<?php endif; ?>
						<div class="user-switcher-info">
							<span class="user-switcher-name"><?= htmlspecialchars($current_user['username']) ?></span>
							<span class="user-switcher-email"><?= htmlspecialchars($current_user['email']) ?></span>
						</div>
					<?php else: ?>
						<div class="user-switcher-avatar user-switcher-avatar-placeholder">?</div>
						<div class="user-switcher-info">
							<span class="user-switcher-name">Not logged in</span>
						</div>
					<?php endif; ?>
					<svg class="user-switcher-chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
				</button>
				<div class="user-switcher-dropdown" id="userSwitcherDropdown">
					<?php if ($current_user): ?>
						<div class="user-switcher-option user-switcher-option-active">
							<?php if (!empty($current_user['profile_picture'])): ?>
								<img class="user-switcher-avatar" src="https://chat.wokki20.nl<?= htmlspecialchars($current_user['profile_picture']) ?>" alt="">
							<?php else: ?>
								<div class="user-switcher-avatar user-switcher-avatar-placeholder"><?= htmlspecialchars(mb_substr($current_user['username'], 0, 1)) ?></div>
							<?php endif; ?>
							<div class="user-switcher-info">
								<span class="user-switcher-name"><?= htmlspecialchars($current_user['username']) ?></span>
								<span class="user-switcher-email"><?= htmlspecialchars($current_user['email']) ?></span>
							</div>
							<svg class="user-switcher-check" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
						</div>
						<div class="user-switcher-separator"></div>
					<?php endif; ?>
					<a href="https://chat.wokki20.nl/login?redirect=<?= $login_redirect ?>" class="user-switcher-login">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
						<?= $current_user ? 'Log in with a different account' : 'Log in first' ?>
					</a>
				</div>
			</div>

			<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
			<input type="hidden" name="client_id" value="<?= htmlspecialchars($client_id) ?>">
			<input type="hidden" name="redirect_uri" value="<?= htmlspecialchars($redirect_uri) ?>">
			<?php if ($scopes): ?>
				<input type="hidden" name="scopes" value="<?= htmlspecialchars($scopes) ?>">
			<?php endif; ?>
			<?php if ($state): ?>
				<input type="hidden" name="state" value="<?= htmlspecialchars($state) ?>">
			<?php endif; ?>
			<div class="authorize-actions">
				<button type="submit" name="action" value="deny" class="button-primary-outline">Deny</button>
				<button type="submit" name="action" value="approve" class="button-primary-filled"<?= !$current_user ? ' disabled' : '' ?>>Approve</button>
			</div>
		</div>
	</form>
	<script src="assets/js/authorize.js"></script>
	<script>
		const trigger = document.getElementById('userSwitcherTrigger');
		const dropdown = document.getElementById('userSwitcherDropdown');
		const switcher = document.getElementById('userSwitcher');

		trigger.addEventListener('click', () => {
			switcher.classList.toggle('open');
		});

		document.addEventListener('click', (e) => {
			if (!switcher.contains(e.target)) {
				switcher.classList.remove('open');
			}
		});
	</script>
</body>
</html>