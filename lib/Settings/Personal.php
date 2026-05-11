<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\IUserSession;

class Personal implements ISettings {

    private IConfig $config;
    private IUserSession $userSession;

    public function __construct(IConfig $config, IUserSession $userSession) {
        $this->config = $config;
        $this->userSession = $userSession;
    }

    public function getForm(): TemplateResponse {
        $user = $this->userSession->getUser();
        $uid  = $user ? $user->getUID() : '';

        $allowBots = $this->config->getUserValue($uid, 'v1rontalk', 'allow_bots', '1') === '1';
        $wpConfigured = !empty($this->config->getAppValue('v1rontalk', 'wordpress_url', ''));

        return new TemplateResponse('v1rontalk', 'personal', [
            'nc_user_id'    => $uid,
            'allow_bots'    => $allowBots,
            'wp_configured' => $wpConfigured,
        ], 'blank');
    }

    public function getSection(): string {
        return 'v1rontalk-personal';
    }

    public function getPriority(): int {
        return 10;
    }
}
