<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\IURLGenerator;
use OCP\IUserSession;

class Admin implements ISettings {

    private IConfig $config;
    private IURLGenerator $urlGen;
    private IUserSession $userSession;

    public function __construct(
        IConfig $config,
        IURLGenerator $urlGen,
        IUserSession $userSession
    ) {
        $this->config = $config;
        $this->urlGen = $urlGen;
        $this->userSession = $userSession;
    }

    public function getForm(): TemplateResponse {
        $user = $this->userSession->getUser();
        $ncUserId = $user ? $user->getUID() : '';

        return new TemplateResponse('v1rontalk', 'settings', [
            'wordpress_url'   => $this->config->getAppValue('v1rontalk', 'wordpress_url', ''),
            'has_api_key'     => !empty($this->config->getAppValue('v1rontalk', 'api_key', '')),
            'bot_system_user' => $this->config->getAppValue('v1rontalk', 'bot_system_user', ''),
            'auto_register'   => (bool) $this->config->getAppValue('v1rontalk', 'auto_register_bots', '1'),
            'nc_user_id'      => $ncUserId,
            'app_js_url'      => $this->urlGen->linkToRoute('v1rontalk.settings.load'),
        ]);
    }

    public function getSection(): string {
        return 'v1rontalk';
    }

    public function getPriority(): int {
        return 10;
    }
}
