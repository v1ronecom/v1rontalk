<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\Controller;

use OCA\V1RonTalk\Service\TalkBotService;
use OCA\V1RonTalk\Service\V1RonApiService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Handles incoming webhook calls from Nextcloud Talk.
 *
 * When a user messages a character bot in Talk, Talk sends a POST
 * request to this controller's /bot/handle endpoint. We process the
 * message and respond in the Talk conversation.
 */
class BotController extends Controller {

    private TalkBotService $talkBot;
    private V1RonApiService $v1ronApi;

    public function __construct(
        string $appName,
        IRequest $request,
        TalkBotService $talkBot,
        V1RonApiService $v1ronApi
    ) {
        parent::__construct($appName, $request);
        $this->talkBot = $talkBot;
        $this->v1ronApi = $v1ronApi;
    }

    /**
     * POST /bot/handle
     *
     * Webhook endpoint called by Nextcloud Talk when a bot receives a message.
     * The request body contains the conversation token, user ID, message text,
     * and bot identifier.
     */
    public function handle(): JSONResponse {
        $data = $this->request->getParams();

        $ncUserId     = $data['user_id'] ?? '';
        $message      = $data['message'] ?? '';
        $convToken    = $data['conversation_token'] ?? '';
        $botId        = $data['bot_id'] ?? '';
        $botName      = $data['bot_name'] ?? '';
        // NC Talk sends conversation type: 1=one-to-one, 2=group, 3=public
        $convType     = (int) ($data['conversation_type'] ?? 1);
        $isGroupChat  = $convType >= 2;

        if (empty($ncUserId) || empty($message)) {
            return new JSONResponse([
                'success' => false,
                'error'   => 'Missing required fields: user_id, message',
            ], 400);
        }

        // Extract character ID from bot name or ID
        $charId = 0;
        if (preg_match('/^v1ron[_-]?(\d+)$/i', $botName, $m)) {
            $charId = (int) $m[1];
        } elseif (is_numeric($botId)) {
            $charId = (int) $botId;
        }

        if ($charId <= 0) {
            return new JSONResponse([
                'success' => false,
                'error'   => 'Unknown character bot',
            ], 400);
        }

        // Sync user to WordPress
        $userInfo = $this->v1ronApi->syncUser($ncUserId);
        if (!$userInfo['success']) {
            return new JSONResponse([
                'success' => false,
                'error'   => 'User sync failed: ' . ($userInfo['error'] ?? 'unknown'),
            ], 500);
        }

        // Send message to character, passing group chat flag for credit cost calculation
        $result = $this->v1ronApi->chat($charId, $ncUserId, $message, '', [], $isGroupChat);

        if (!$result['success']) {
            return new JSONResponse([
                'success' => false,
                'error'   => $result['error'] ?? 'Chat failed',
                'code'    => $result['code'] ?? '',
            ], 200); // Return 200 so Talk doesn't retry
        }

        return new JSONResponse([
            'success'       => true,
            'reply'         => $result['reply'] ?? '',
            'memories'      => $result['memories'] ?? [],
            'image_prompts' => $result['image_prompts'] ?? [],
        ]);
    }
}
