<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCA\V1RonTalk\Service\TalkBotService;
use OCA\V1RonTalk\Service\V1RonApiService;

class Application extends App implements IBootstrap {

    public const APP_ID = 'v1rontalk';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerService(TalkBotService::class, function ($c) {
            return new TalkBotService(
                $c->get(\OCP\IServerContainer::class),
                $c->get(V1RonApiService::class)
            );
        });
    }

    public function boot(IBootContext $context): void {
        $server = $context->getServerContainer();

        // Register Talk bot event listeners if Talk is available
        $dispatcher = $server->get(IEventDispatcher::class);

        // Listen for Talk bot invoke events (Talk >= 15 / NC >= 28)
        $dispatcher->addServiceListener(
            'OCA\Talk\Events\BotInvokeEvent',
            TalkBotService::class
        );

        // Also support Talk webhook style
        $dispatcher->addServiceListener(
            'OCA\Talk\Events\BotMessageEvent',
            TalkBotService::class
        );

        // Register the bot with Talk on first run
        $talkBotService = $context->getAppContainer()->get(TalkBotService::class);
        $talkBotService->registerBots();
    }
}
