<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\Service;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * HTTP client for communicating with the WordPress V1RonDHM REST API.
 */
class V1RonApiService {

    private IClientService $clientService;
    private IConfig $config;
    private LoggerInterface $logger;

    public function __construct(
        IClientService $clientService,
        IConfig $config,
        LoggerInterface $logger
    ) {
        $this->clientService = $clientService;
        $this->config = $config;
        $this->logger = $logger;
    }

    private function getBaseUrl(): string {
        return rtrim($this->config->getAppValue('v1rontalk', 'wordpress_url', ''), '/');
    }

    private function getApiKey(): string {
        return $this->config->getAppValue('v1rontalk', 'api_key', '');
    }

    private function getClient() {
        return $this->clientService->newClient();
    }

    private function headers(): array {
        return [
            'X-V1Ron-API-Key' => $this->getApiKey(),
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
        ];
    }

    /**
     * Proxy any request to the WordPress V1Ron REST API.
     * Used by the frontend to send arbitrary requests.
     */
    public function proxy(string $endpoint, array $params = [], string $method = 'GET'): array {
        $url = $this->getBaseUrl() . '/wp-json/v1ron/v1/' . ltrim($endpoint, '/');

        try {
            $client = $this->getClient();
            $options = [
                'headers' => $this->headers(),
                'timeout' => 120,
            ];

            if ($method === 'GET') {
                $options['query'] = $params;
                $response = $client->get($url, $options);
            } else {
                $options['body'] = json_encode($params);
                $response = $client->post($url, $options);
            }

            $body = $response->getBody();
            $status = $response->getStatusCode();
            $data = json_decode($body, true);

            if ($status !== 200 || !$data) {
                $this->logger->error('V1Ron API error', [
                    'endpoint' => $endpoint,
                    'status'   => $status,
                    'body'     => substr($body, 0, 500),
                ]);
                return ['success' => false, 'error' => 'API returned status ' . $status];
            }

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('V1Ron API request failed: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync a Nextcloud user to WordPress.
     */
    public function syncUser(string $ncUserId, string $email = '', string $displayName = ''): array {
        return $this->proxy('user/info', [
            'user_id'      => $ncUserId,
            'email'        => $email,
            'display_name' => $displayName,
        ], 'POST');
    }

    /**
     * Fetch all characters available to a user.
     */
    public function getCharacters(string $ncUserId, bool $publicOnly = false): array {
        return $this->proxy('characters', [
            'user_id' => $ncUserId,
            'public'  => $publicOnly ? '1' : '0',
        ], 'GET');
    }

    /**
     * Get a single character by ID.
     */
    public function getCharacter(int $charId, string $ncUserId): array {
        return $this->proxy('characters/' . $charId, [
            'user_id' => $ncUserId,
        ], 'GET');
    }

    /**
     * Send a chat message to a character.
     */
    public function chat(int $charId, string $ncUserId, string $message, string $fileContext = '', array $refFileUrls = []): array {
        return $this->proxy("characters/{$charId}/chat", [
            'user_id'       => $ncUserId,
            'message'       => $message,
            'file_context'  => $fileContext,
            'ref_file_urls' => $refFileUrls,
        ], 'POST');
    }

    /**
     * Get chat history with a character.
     */
    public function getChatHistory(int $charId, string $ncUserId, int $limit = 50): array {
        return $this->proxy("characters/{$charId}/messages", [
            'user_id' => $ncUserId,
            'limit'   => $limit,
        ], 'GET');
    }

    /**
     * Clear chat history with a character.
     */
    public function clearChat(int $charId, string $ncUserId): array {
        return $this->proxy("characters/{$charId}/messages", [
            'user_id' => $ncUserId,
        ], 'DELETE');
    }

    /**
     * Ingest file content into a character's knowledge base.
     */
    public function ingestKnowledge(int $charId, string $ncUserId, string $title, string $content, bool $isPrivate = false): array {
        return $this->proxy("characters/{$charId}/knowledge", [
            'user_id'    => $ncUserId,
            'title'      => $title,
            'content'    => $content,
            'is_private' => $isPrivate,
        ], 'POST');
    }

    /**
     * Get user's credit balance.
     */
    public function getBalance(string $ncUserId): array {
        return $this->proxy('user/balance', [
            'user_id' => $ncUserId,
        ], 'GET');
    }
}
