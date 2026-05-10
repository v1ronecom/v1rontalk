<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller {

    private IConfig $config;

    public function __construct(
        string $appName,
        IRequest $request,
        IConfig $config
    ) {
        parent::__construct($appName, $request);
        $this->config = $config;
    }

    /**
     * GET /api/settings — Load app settings (admin only).
     */
    public function load(): JSONResponse {
        if (!$this->isAdmin()) {
            return new JSONResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }

        return new JSONResponse([
            'success'          => true,
            'wordpress_url'    => $this->config->getAppValue('v1rontalk', 'wordpress_url', ''),
            'api_key'          => $this->config->getAppValue('v1rontalk', 'api_key', '') ? '••••••••' : '',
            'has_api_key'      => !empty($this->config->getAppValue('v1rontalk', 'api_key', '')),
            'bot_system_user'  => $this->config->getAppValue('v1rontalk', 'bot_system_user', ''),
            'auto_register'    => (bool) $this->config->getAppValue('v1rontalk', 'auto_register_bots', '1'),
        ]);
    }

    /**
     * POST /api/settings — Save app settings (admin only).
     */
    public function save(): JSONResponse {
        if (!$this->isAdmin()) {
            return new JSONResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }

        $wpUrl = $this->request->getParam('wordpress_url', '');
        $apiKey = $this->request->getParam('api_key', '');
        $botUser = $this->request->getParam('bot_system_user', '');
        $autoRegister = (bool) $this->request->getParam('auto_register', true);

        if (empty($wpUrl)) {
            return new JSONResponse(['success' => false, 'error' => 'WordPress URL is required'], 400);
        }

        $this->config->setAppValue('v1rontalk', 'wordpress_url', rtrim($wpUrl, '/'));

        // Only update API key if a new one is provided (not masked)
        if (!empty($apiKey) && $apiKey !== '••••••••') {
            $this->config->setAppValue('v1rontalk', 'api_key', $apiKey);
        }

        if (!empty($botUser)) {
            $this->config->setAppValue('v1rontalk', 'bot_system_user', $botUser);
        }

        $this->config->setAppValue('v1rontalk', 'auto_register_bots', $autoRegister ? '1' : '0');

        // Reset bot registration flag so bots re-register on next boot
        $this->config->setAppValue('v1rontalk', 'bots_registered_version', '0');

        return new JSONResponse(['success' => true]);
    }

    private function isAdmin(): bool {
        $user = \OC::$server->get(\OCP\IUserSession::class)->getUser();
        if (!$user) return false;
        return \OC_User::isAdminUser($user->getUID());
    }
}
