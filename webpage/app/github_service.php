<?php
require_once __DIR__ . '/github_config.php';

class GitHubService {
    private string $owner;
    private string $repo;
    private int $projectNumber;
    private ?string $projectId = null;
    private ?string $statusFieldId = null;
    private array $statusOptionIds = [];

    private ?string $cachedToken = null;
    private int $tokenExpiresAt = 0;

    public function __construct() {
        $this->owner = GITHUB_OWNER;
        $this->repo = GITHUB_REPO;
        $this->projectNumber = GITHUB_PROJECT_NUMBER;
    }

    private function getGraphqlToken(): string {
        return $this->getInstallationToken();
    }

    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function generateJwt(): string {
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'iat' => $now - 60,
            'exp' => $now + 540,
            'iss' => (int) GITHUB_APP_ID
        ]));

        if (!file_exists(GITHUB_APP_PRIVATE_KEY_PATH)) {
            throw new RuntimeException('GitHub App private key not found at: ' . GITHUB_APP_PRIVATE_KEY_PATH);
        }
        $pem = file_get_contents(GITHUB_APP_PRIVATE_KEY_PATH);
        $pem = str_replace("\r\n", "\n", str_replace("\r", "\n", $pem));
        $privateKey = openssl_pkey_get_private($pem);
        openssl_sign("{$header}.{$payload}", $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return "{$header}.{$payload}." . $this->base64UrlEncode($signature);
    }

    private function getInstallationToken(): string {
        if ($this->cachedToken && time() < $this->tokenExpiresAt) {
            return $this->cachedToken;
        }

        $jwt = $this->generateJwt();

        $ch = curl_init("https://api.github.com/app/installations/" . GITHUB_APP_INSTALLATION_ID . "/access_tokens");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$jwt}",
            "Accept: application/vnd.github+json",
            "X-GitHub-Api-Version: 2022-11-28",
            "User-Agent: WokkiBot",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data['token'])) {
            throw new RuntimeException('GitHub App token exchange failed: ' . json_encode($data));
        }

        $this->cachedToken = $data['token'];
        $this->tokenExpiresAt = time() + 3300;

        return $this->cachedToken;
    }

    private function request(string $method, string $url, array $data = []): array {
        $token = $this->getInstallationToken();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Accept: application/vnd.github+json",
            "X-GitHub-Api-Version: 2022-11-28",
            "User-Agent: WokkiBot",
            "Content-Type: application/json"
        ]);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }

    public function graphql(string $query, array $variables = []): array {
        $token = $this->getGraphqlToken();

        $ch = curl_init("https://api.github.com/graphql");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json",
            "User-Agent: WokkiBot"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query, 'variables' => $variables]));
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }
    
    private function loadProjectMetadata(): void {
        if ($this->projectId !== null) return;

        $result = $this->graphql('
            query($owner: String!, $number: Int!) {
                organization(login: $owner) {
                    projectV2(number: $number) {
                        id
                        fields(first: 20) {
                            nodes {
                                ... on ProjectV2SingleSelectField {
                                    id
                                    name
                                    options { id name }
                                }
                            }
                        }
                    }
                }
            }
        ', ['owner' => $this->owner, 'number' => $this->projectNumber]);

        $project = $result['data']['organization']['projectV2'] ?? null;
        if (!$project) return;

        $this->projectId = $project['id'];
        $this->statusOptionIds = [];

        foreach ($project['fields']['nodes'] as $field) {
            if (!isset($field['name']) || strtolower($field['name']) !== 'status') continue;

            $this->statusFieldId = $field['id'];

            foreach ($field['options'] as $opt) {
                $name = $opt['name'];
                if ($name === 'Todo') $this->statusOptionIds['voting'] = $opt['id'];
                elseif ($name === 'In progress') $this->statusOptionIds['planned'] = $opt['id'];
                elseif ($name === 'Done') $this->statusOptionIds['implemented'] = $opt['id'];
            }
            break;
        }
    }

    public function debugGetToken(): string {
        return $this->getInstallationToken();
    }

    public function debugLoadProject(): array {
        return $this->graphql('
            query($owner: String!, $number: Int!) {
                organization(login: $owner) {
                    projectV2(number: $number) {
                        id
                        title
                        fields(first: 20) {
                            nodes {
                                ... on ProjectV2SingleSelectField {
                                    id
                                    name
                                    options { id name }
                                }
                            }
                        }
                    }
                }
            }
        ', ['owner' => $this->owner, 'number' => $this->projectNumber]);
    }

    public function ensureLabelsExist(): void {
        $labels = $this->request('GET', "https://api.github.com/repos/{$this->owner}/{$this->repo}/labels");
        $existingLabels = array_column($labels, 'name');

        $requiredLabels = [
            ['name' => 'idea', 'color' => '7c3aed', 'description' => 'Feature idea submitted via Wokki Chat'],
            ['name' => 'bug', 'color' => 'd73a4a', 'description' => 'Bug report submitted via Wokki Chat'],
            ['name' => 'api-request', 'color' => '0e8a16', 'description' => 'API endpoint request submitted via Wokki Chat'],
        ];

        foreach ($requiredLabels as $label) {
            if (!in_array($label['name'], $existingLabels)) {
                $this->request('POST', "https://api.github.com/repos/{$this->owner}/{$this->repo}/labels", $label);
            }
        }
    }

    public function ensureIdeaLabelExists(): void {
        $this->ensureLabelsExist();
    }

    public function createIssue(string $title, string $body, string $type = 'idea'): array {
        $labelMap = [
            'idea' => 'idea',
            'bug' => 'bug',
            'api_request' => 'api-request'
        ];

        $label = $labelMap[$type] ?? 'idea';

        $response = $this->request('POST', "https://api.github.com/repos/{$this->owner}/{$this->repo}/issues", [
            'title' => $title,
            'body' => $body,
            'labels' => [$label]
        ]);
        return [
            'number' => $response['number'] ?? null,
            'node_id' => $response['node_id'] ?? null
        ];
    }

    public function addIssueToProject(string $issueNodeId, string $status = 'voting'): ?string {
        $this->loadProjectMetadata();
        if (!$this->projectId) return null;

        $result = $this->graphql('
            mutation($projectId: ID!, $contentId: ID!) {
                addProjectV2ItemById(input: {projectId: $projectId, contentId: $contentId}) {
                    item { id }
                }
            }
        ', ['projectId' => $this->projectId, 'contentId' => $issueNodeId]);

        $itemId = $result['data']['addProjectV2ItemById']['item']['id'] ?? null;
        if ($itemId) $this->setProjectItemStatus($itemId, $status);
        return $itemId;
    }

    public function setProjectItemStatus(string $itemId, string $status): void {
        $this->loadProjectMetadata();

        $map = [
            'voting' => 'Todo',
            'planned' => 'In progress',
            'implemented' => 'Done'
        ];

        $key = strtolower($status);
        if (isset($map[$key])) {
            $status = $map[$key];
        }

        if (!$this->projectId || !$this->statusFieldId || !isset($this->statusOptionIds[$status])) return;

        $this->graphql('
            mutation($projectId: ID!, $itemId: ID!, $fieldId: ID!, $optionId: String!) {
                updateProjectV2ItemFieldValue(input: {
                    projectId: $projectId,
                    itemId: $itemId,
                    fieldId: $fieldId,
                    value: { singleSelectOptionId: $optionId }
                }) {
                    projectV2Item { id }
                }
            }
        ', [
            'projectId' => $this->projectId,
            'itemId' => $itemId,
            'fieldId' => $this->statusFieldId,
            'optionId' => $this->statusOptionIds[$status]
        ]);
    }

    public function getProjectItemIdForIssue(string $issueNodeId): ?string {
        $this->loadProjectMetadata();
        if (!$this->projectId) return null;

        $cursor = null;

        do {
            $result = $this->graphql('
                query($issueId: ID!, $cursor: String) {
                    node(id: $issueId) {
                        ... on Issue {
                            projectItems(first: 20, after: $cursor) {
                                pageInfo {
                                    hasNextPage
                                    endCursor
                                }
                                nodes {
                                    id
                                    project { id }
                                }
                            }
                        }
                    }
                }
            ', [
                'issueId' => $issueNodeId,
                'cursor' => $cursor
            ]);

            $items = $result['data']['node']['projectItems']['nodes'] ?? [];

            foreach ($items as $item) {
                if (($item['project']['id'] ?? '') === $this->projectId) {
                    return $item['id'];
                }
            }

            $page = $result['data']['node']['projectItems']['pageInfo'] ?? null;
            $cursor = $page['hasNextPage'] ? $page['endCursor'] : null;

        } while ($cursor);

        return null;
    }

    public function createComment(int $issueNumber, string $body): bool {
        $response = $this->request('POST', "https://api.github.com/repos/{$this->owner}/{$this->repo}/issues/{$issueNumber}/comments", [
            'body' => $body
        ]);
        return isset($response['id']);
    }

    public function getIssueComments(int $issueNumber): array {
        $data = $this->request('GET', "https://api.github.com/repos/{$this->owner}/{$this->repo}/issues/{$issueNumber}/comments");
        $comments = [];
        foreach ($data as $c) {
            $comments[] = [
                'id' => $c['id'],
                'body' => $c['body'],
                'created_at' => $c['created_at'],
                'github_username' => $c['user']['login'] ?? '',
                'github_avatar' => $c['user']['avatar_url'] ?? ''
            ];
        }
        return $comments;
    }

    public function getIssueAssignees(int $issueNumber): array {
        $data = $this->request('GET', "https://api.github.com/repos/{$this->owner}/{$this->repo}/issues/{$issueNumber}");
        $assignees = [];
        foreach ($data['assignees'] ?? [] as $a) {
            $assignees[] = [
                'username' => $a['login'],
                'avatar_url' => $a['avatar_url']
            ];
        }
        return $assignees;
    }
}