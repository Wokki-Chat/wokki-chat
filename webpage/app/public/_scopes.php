<?php

define('ALLOWED_SCOPES', [
	'user:read',
	'user:read:profile',
	'user:read:email',
	'servers:read',
	'contacts:read',
]);

define('SCOPE_EXPANSIONS', [
	'user:read' => [
		'user:read:profile',
		'user:read:email',
	],
]);

$scope_descriptions = [
    'user:read' => ['View all your profile information', '<span class="material-symbols-rounded">account_circle</span>'],
    'user:read:profile' => ['View basic profile information like username, display name, and profile picture', '<span class="material-symbols-rounded">account_box</span>'],
    'user:read:email' => ['View your email address', '<span class="material-symbols-rounded">alternate_email</span>'],
    'servers:read' => ['View your servers and its details', '<span class="material-symbols-rounded">event_list</span>'],
    'contacts:read' => ['View your contacts list', '<span class="material-symbols-rounded">group</span>'],
];

function validate_scopes(array $scopes): bool {
	foreach ($scopes as $scope) {
		if (!in_array($scope, ALLOWED_SCOPES, true)) {
			return false;
		}
	}
	return true;
}

function expand_scopes(array $scopes): array {
	$expanded = [];
	foreach ($scopes as $scope) {
		if (isset(SCOPE_EXPANSIONS[$scope])) {
			foreach (SCOPE_EXPANSIONS[$scope] as $child) {
				$expanded[] = $child;
			}
		} else {
			$expanded[] = $scope;
		}
	}
	return array_unique($expanded);
}