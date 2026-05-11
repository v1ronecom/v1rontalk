<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\Controller;

use OCA\V1RonTalk\Service\V1RonApiService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Manages per-user preferences for V1Ron Talk (e.g. "allow bots to chat").
 */
class UserSettingsController extends Controller {

    private IConfig $config;
    private IUserSession $userSession;
    private V1RonApiService $v1ronApi;

    public function __construct(
        string $appName,
        IRequest $request,
        IConfig $config,
        IUserSession $userSession,
        V1RonApiService $v1ronApi
    ) {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->userSession = $userSession;
        $this->v1ronApi = $v1ronApi;
    }

    /**
     * GET /api/user/settings — Load current user's preferences.
     */
    public function load(): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        $uid       = $user->getUID();
        $allowBots = $this->config->getUserValue($uid, 'v1rontalk', 'allow_bots', '1') === '1';

        // Sync the NC user to WP and get balance + assigned characters
        $syncResult     = $this->v1ronApi->syncUser($uid, $user->getEMailAddress() ?? '', $user->getDisplayName() ?? '');
        $balanceResult  = $this->v1ronApi->getBalance($uid);
        $charsResult    = $this->v1ronApi->getCharacters($uid, false);

        return new JSONResponse([
            'success'    => true,
            'allow_bots' => $allowBots,
            'balance'    => $balanceResult['balance_formatted'] ?? '0',
            'wp_user_id' => $syncResult['wp_user_id'] ?? null,
            'characters' => $charsResult['characters'] ?? [],
        ]);
    }

    /**
     * POST /api/user/settings — Save current user's preferences.
     *
     * Body: { allow_bots: true|false }
     */
    public function save(): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        $uid       = $user->getUID();
        $allowBots = (bool) $this->request->getParam('allow_bots', true);

        $this->config->setUserValue($uid, 'v1rontalk', 'allow_bots', $allowBots ? '1' : '0');

        return new JSONResponse(['success' => true, 'allow_bots' => $allowBots]);
    }

    /**
     * POST /api/user/sync — Sync the current NC user to WordPress.
     */
    public function sync(): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        $uid    = $user->getUID();
        $result = $this->v1ronApi->syncUser($uid, $user->getEMailAddress() ?? '', $user->getDisplayName() ?? '');

        if (!$result['success']) {
            return new JSONResponse(['success' => false, 'error' => $result['error'] ?? 'Sync failed'], 500);
        }

        $balance = $this->v1ronApi->getBalance($uid);
        $chars   = $this->v1ronApi->getCharacters($uid, false);

        return new JSONResponse([
            'success'    => true,
            'wp_user_id' => $result['wp_user_id'] ?? null,
            'balance'    => $balance['balance_formatted'] ?? '0',
            'characters' => $chars['characters'] ?? [],
        ]);
    }
}
