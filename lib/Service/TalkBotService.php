<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\Service;

use OCA\V1RonTalk\Service\V1RonApiService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Handles Talk bot registration and incoming message events.
 *
 * This service listens for Talk bot events (BotInvokeEvent / BotMessageEvent)
 * and proxies user messages to the V1RonDHM WordPress backend, then sends
 * the character's response back into the Talk conversation.
 */
class TalkBotService implements IEventListener {

    private IServerContainer $server;
    private V1RonApiService $v1ronApi;
    private IConfig $config;
    private IURLGenerator $urlGen;
    private LoggerInterface $logger;

    public function __construct(
        IServerContainer $server,
        V1RonApiService $v1ronApi
    ) {
        $this->server = $server;
        $this->v1ronApi = $v1ronApi;
        $this->config = $server->get(IConfig::class);
        $this->urlGen = $server->get(IURLGenerator::class);
        $this->logger = $server->get(LoggerInterface::class);
    }

    /**
     * Register V1Ron characters as Talk bots in the database.
     *
     * This is called on app boot. It fetches characters from WordPress
     * and registers each one as a bot in Nextcloud Talk.
     */
    public function registerBots(): void {
        $wpUrl = $this->config->getAppValue('v1rontalk', 'wordpress_url', '');
        $apiKey = $this->config->getAppValue('v1rontalk', 'api_key', '');

        if (empty($wpUrl) || empty($apiKey)) {
            return; // Not configured yet
        }

        $registeredVersion = $this->config->getAppValue('v1rontalk', 'bots_registered_version', '0');
        $appVersion = $this->config->getAppValue('v1rontalk', 'installed_version', '0');

        if (version_compare($registeredVersion, $appVersion, '>=')) {
            return; // Already registered
        }

        try {
            // Fetch characters from WordPress (public ones)
            $result = $this->v1ronApi->getCharacters('', true);
            if (!$result['success'] || !isset($result['characters'])) {
                $this->logger->warning('Could not fetch characters for bot registration');
                return;
            }

            // Register each character as a bot command via Talk's API
            $botsRegistered = 0;
            foreach ($result['characters'] as $char) {
                if ($this->registerSingleBot($char)) {
                    $botsRegistered++;
                }
            }

            $this->config->setAppValue('v1rontalk', 'bots_registered_version', $appVersion);
            $this->logger->info("Registered {$botsRegistered} V1Ron characters as Talk bots");
        } catch (\Throwable $e) {
            $this->logger->error('Failed to register bots: ' . $e->getMessage());
        }
    }

    /**
     * Register a single character as a Talk bot.
     *
     * Uses Talk's BotServer API (Talk 21 / NC 31+).
     */
    private function registerSingleBot(array $character): bool {
        try {
            $botServerMapper = $this->server->get(\OCA\Talk\Model\BotServerMapper::class);

            $botUrl = $this->urlGen->linkToRouteAbsolute('v1rontalk.bot.handle');
            $charId = $character['id'];

            // Check if this bot URL is already registered
            $existingBots = $botServerMapper->getAllBots();
            foreach ($existingBots as $bot) {
                if ($bot->getUrl() === $botUrl && $bot->getName() === $character['name']) {
                    return true; // Already registered
                }
            }

            // Register as a webhook bot in talk_bots_server table
            $bot = new \OCA\Talk\Model\BotServer();
            $bot->setName($character['name']);
            $bot->setDescription(substr($character['description'] ?? 'AI character from V1Ron', 0, 400));
            $bot->setUrl($botUrl);
            $bot->setUrlHash(sha1($botUrl));
            $bot->setSecret($this->config->getAppValue('v1rontalk', 'api_key', ''));
            $bot->setState(\OCA\Talk\Model\Bot::STATE_ENABLED);
            $bot->setFeatures(\OCA\Talk\Model\Bot::FEATURE_WEBHOOK + \OCA\Talk\Model\Bot::FEATURE_RESPONSE);
            $botServerMapper->insert($bot);

            $this->logger->info("Registered bot: {$character['name']} (ID: {$charId})");
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning("Could not register bot {$character['name']}: " . $e->getMessage());

            // Fallback: register as a bot command
            try {
                if (class_exists('\OCA\Talk\Service\BotCommandService')) {
                    $cmdService = $this->server->get(\OCA\Talk\Service\BotCommandService::class);
                    $cmdService->installBotCommand(
                        '/v1ron-' . $character['id'],
                        "Talk to {$character['name']}",
                        $character['id'],
                    );
                    return true;
                }
            } catch (\Throwable $e2) {
                $this->logger->error("Bot command fallback also failed: " . $e2->getMessage());
            }

            return false;
        }
    }

    /**
     * Handle incoming Talk bot events.
     *
     * @param Event $event
     */
    public function handle(Event $event): void {
        // Support both BotInvokeEvent and BotMessageEvent interfaces
        try {
            // Try newer BotMessageEvent (Talk >= 18)
            if (method_exists($event, 'getMessage') && method_exists($event, 'getConversation')) {
                $this->handleBotMessage($event);
                return;
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        try {
            // Try older BotInvokeEvent (Talk 15-17)
            if (method_exists($event, 'getCommand') && method_exists($event, 'getConversation')) {
                $this->handleBotInvoke($event);
                return;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Unhandled bot event type: ' . get_class($event));
        }
    }

    /**
     * Handle BotMessageEvent (Talk >= 18).
     */
    private function handleBotMessage($event): void {
        $message = $event->getMessage();
        $conversation = $event->getConversation();
        $actor = $event->getActor();
        $bot = $event->getBot();

        $ncUserId = $actor->getUserId();
        $botName = $bot->getName();
        $convToken = $conversation->getToken();
        $text = $message->getMessage();

        if (!$ncUserId || !$text) return;

        // Find which character this bot corresponds to
        $charId = $this->getCharacterIdFromBotUrl($bot->getUrl());

        $this->processAndRespond($charId, $ncUserId, $text, $convToken);
    }

    /**
     * Handle BotInvokeEvent (Talk 15-17).
     */
    private function handleBotInvoke($event): void {
        $command = $event->getCommand();
        $conversation = $event->getConversation();
        $actor = $event->getActor();

        $ncUserId = $actor->getUserId();
        $convToken = $conversation->getToken();
        $text = $event->getArguments() ?? '';

        if (!$ncUserId) return;

        // Extract character ID from command (format: /v1ron-{id})
        $charId = 0;
        if (preg_match('/^v1ron-(\d+)$/', $command?->getName() ?? '', $m)) {
            $charId = (int) $m[1];
        }

        $this->processAndRespond($charId, $ncUserId, $text, $convToken);
    }

    /**
     * Process a message and send response back to Talk.
     */
    private function processAndRespond(int $charId, string $ncUserId, string $text, string $convToken): void {
        if ($charId <= 0 || empty($text)) return;

        // Sync the user to WordPress first
        $userInfo = $this->v1ronApi->syncUser($ncUserId);
        if (!$userInfo['success']) {
            $this->sendTalkMessage($convToken, "⚠️ Could not sync your account. Please configure the WordPress connection.");
            return;
        }

        // Check if message contains a file request
        $fileContext = '';
        $refFileUrls = [];

        // Patterns: "read file X", "open file Y", "edit file Z", "save to file X"
        $fileReadMatch = [];
        if (preg_match('/(?:read|open|show|analyze|summarize)\s+(?:file\s+)?(?:from\s+)?`([^`]+)`/i', $text, $fileReadMatch)) {
            $filePath = $fileReadMatch[1];
            $fileResult = $this->readFileContent($ncUserId, $filePath);
            if ($fileResult['success']) {
                if (isset($fileResult['content'])) {
                    $fileContext = $fileResult['content'];
                    // Notify user that file was read
                    $this->sendTalkMessage($convToken,
                        "📄 *Reading file: {$fileResult['name']}*\n" .
                        "({$fileResult['size']} bytes, {$fileResult['mime']})"
                    );
                } elseif (isset($fileResult['download_url'])) {
                    $refFileUrls[] = $fileResult['download_url'];
                }
            } else {
                $this->sendTalkMessage($convToken, "🔍 Could not read file: {$fileResult['error']}");
            }
        }

        // Check for file edit/save command
        $fileEditMatch = [];
        if (preg_match('/(?:save|write|update|edit)\s+(?:to\s+)?(?:file\s+)?`([^`]+)`/i', $text, $fileEditMatch)) {
            // Character will respond first, then we check if the response contains file content
            // We handle this after getting the character's reply
        }

        // Send the message to the V1Ron character
        $result = $this->v1ronApi->chat(
            $charId,
            $ncUserId,
            $text,
            $fileContext,
            $refFileUrls
        );

        if (!$result['success']) {
            $errorMsg = $result['error'] ?? 'Unknown error';
            if (isset($result['code']) && $result['code'] === 'insufficient_credits') {
                $this->sendTalkMessage($convToken, "⚠️ You don't have enough credits to chat. Please top up your balance.");
            } else {
                $this->sendTalkMessage($convToken, "⚠️ Sorry, I couldn't reach the character: {$errorMsg}");
            }
            return;
        }

        $reply = $result['reply'] ?? '...';

        // Handle file save commands from character's reply
        // The character can request file operations via structured tags
        $reply = $this->handleFileOperations($ncUserId, $reply);

        // Send the character's reply to the Talk conversation
        $this->sendTalkMessage($convToken, $reply);

        // If there were image prompts generated, send a note
        if (!empty($result['image_prompts'])) {
            $this->sendTalkMessage($convToken,
                "🎨 *I'm creating some images for you...*\n" .
                "Check back in the V1Ron Studio to see them."
            );
        }
    }

    /**
     * Handle file operation tags in character's reply.
     * The LLM can request file operations via structured tags like:
     * [FILE_READ path/to/file]
     * [FILE_WRITE path/to/file]content[/FILE_WRITE]
     * [FILE_SEARCH query]
     */
    private function handleFileOperations(string $ncUserId, string $reply): string {
        // Handle [FILE_SAVE]...[/FILE_SAVE] tags
        $reply = preg_replace_callback(
            '/\[FILE_SAVE\s+(`[^`]+`|\S+)\](.*?)\[\/FILE_SAVE\]/is',
            function ($matches) use ($ncUserId) {
                $path = trim($matches[1], '`');
                $content = trim($matches[2]);
                $result = $this->writeFileContent($ncUserId, $path, $content);
                if ($result['success']) {
                    return "✅ *Saved file:* `{$path}` ({$result['action']})";
                }
                return "❌ *Failed to save:* `{$path}` — {$result['error']}";
            },
            $reply
        );

        // Handle [FILE_READ]path[/FILE_READ] tags
        $reply = preg_replace_callback(
            '/\[FILE_READ\](`?[^`\[]+`?)\[\/FILE_READ\]/is',
            function ($matches) use ($ncUserId) {
                $path = trim($matches[1], '`');
                $result = $this->readFileContent($ncUserId, $path);
                if ($result['success'] && isset($result['content'])) {
                    $preview = mb_substr($result['content'], 0, 2000);
                    return "📄 *Content of `{$path}`:*\n```\n{$preview}\n```";
                }
                return "❌ *Could not read:* `{$path}`";
            },
            $reply
        );

        // Handle [FILE_LIST]directory[/FILE_LIST] tags
        $reply = preg_replace_callback(
            '/\[FILE_LIST\](`?[^`\[]+`?)\[\/FILE_LIST\]/is',
            function ($matches) use ($ncUserId) {
                $dir = trim($matches[1], '`') ?: '/';
                $result = $this->listFiles($ncUserId, $dir);
                if ($result['success']) {
                    $lines = array_map(fn($f) => "📄 {$f['name']} (" . ($f['type'] === 'folder' ? '📁' : $this->formatSize($f['size'])) . ")", $result['items']);
                    return "📁 *Contents of `{$dir}`:*\n" . implode("\n", $lines);
                }
                return "❌ *Could not list:* `{$dir}`";
            },
            $reply
        );

        return $reply;
    }

    /**
     * Send a message to a Talk conversation using the Talk REST API.
     */
    private function sendTalkMessage(string $convToken, string $message): void {
        try {
            // Try using Talk's internal API
            $roomService = $this->server->get(\OCA\Talk\Service\RoomService::class);
            $manager = $this->server->get(\OCA\Talk\Manager::class);

            $room = $manager->getRoomByToken($convToken);

            // Use the bot as the sending user — requires a bot system user
            $botUserId = $this->config->getAppValue('v1rontalk', 'bot_system_user', '');
            $userManager = $this->server->get(\OCP\IUserManager::class);
            $botUser = $botUserId ? $userManager->get($botUserId) : null;

            if ($botUser) {
                $room->sendMessage($botUser, $message);
            } else {
                // Fallback: send via chat API
                $chatManager = $this->server->get(\OCA\Talk\Chat\ChatManager::class);
                $chatManager->addSystemMessage($room, 'bot', $message);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Talk message: ' . $e->getMessage(), [
                'convToken' => $convToken,
                'message'   => substr($message, 0, 100),
            ]);

            // Last resort: try REST API
            try {
                $client = $this->server->get(\OCP\Http\Client\IClientService::class)->newClient();
                $baseUrl = $this->urlGen->getBaseUrl();
                $client->post($baseUrl . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $convToken, [
                    'headers' => [
                        'OCS-APIRequest' => 'true',
                        'Content-Type'   => 'application/json',
                    ],
                    'body' => json_encode([
                        'message' => $message,
                        'actorDisplayName' => 'V1Ron Character',
                        'actorType' => 'bots',
                        'actorId' => 'v1ron',
                    ]),
                ]);
            } catch (\Throwable $e2) {
                $this->logger->error('Failed to send via REST API: ' . $e2->getMessage());
            }
        }
    }

    // ── File Operations (forwarded to FileService) ───────────────────────────

    private function readFileContent(string $ncUserId, string $path): array {
        try {
            $fileService = $this->server->get(\OCP\IServerContainer::class)->get(FileService::class);
            return $fileService->readFile($ncUserId, $path);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function writeFileContent(string $ncUserId, string $path, string $content): array {
        try {
            $fileService = $this->server->get(\OCP\IServerContainer::class)->get(FileService::class);
            return $fileService->writeFile($ncUserId, $path, $content);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function listFiles(string $ncUserId, string $dir): array {
        try {
            $fileService = $this->server->get(\OCP\IServerContainer::class)->get(FileService::class);
            return $fileService->listDirectory($ncUserId, $dir);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getCharacterIdFromBotUrl(string $url): int {
        // Extract character ID from the bot route or URL
        if (preg_match('/v1ron-(\d+)/', $url, $m)) {
            return (int) $m[1];
        }
        // Default: check the app config for a mapping
        $mapping = json_decode(
            $this->config->getAppValue('v1rontalk', 'bot_character_map', '{}'),
            true
        );
        return (int) ($mapping[$url] ?? 0);
    }

    private function formatSize(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 1) . ' GB';
    }
}
