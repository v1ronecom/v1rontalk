<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\Settings;

use OCP\Settings\IIconSection;
use OCP\IURLGenerator;

class PersonalSection implements IIconSection {

    private IURLGenerator $urlGen;

    public function __construct(IURLGenerator $urlGen) {
        $this->urlGen = $urlGen;
    }

    public function getID(): string {
        return 'v1rontalk-personal';
    }

    public function getName(): string {
        return 'V1Ron Talk';
    }

    public function getPriority(): int {
        return 75;
    }

    public function getIcon(): string {
        return $this->urlGen->imagePath('v1rontalk', 'app.svg');
    }
}
