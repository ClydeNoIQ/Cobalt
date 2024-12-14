<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;

class OmniSprint extends Check {

    private array $playerFlags = [];

    public function getName(): string {
        return "OmniSprint";
    }

    public function getSubType(): string {
        return "A";
    }

    public function maxViolations(): int {
        return 10;
    }

    /**
     * Checks player input flags for suspicious behavior.
     */
    public function check(DataPacket $packet, PlayerAPI $playerAPI): void {
        if (!$packet instanceof PlayerAuthInputPacket) {
            return;
        }

        $inputMode = $packet->getInputMode();
        if (!in_array($inputMode, [InputMode::MOUSE_KEYBOARD, InputMode::TOUCHSCREEN], true)) {
            return;
        }

        $player = $playerAPI->getPlayer();
        $inputFlags = $packet->getInputFlags();

        $movingLaterally = ($inputFlags & (1 << PlayerAuthInputFlags::LEFT)) !== 0
            || ($inputFlags & (1 << PlayerAuthInputFlags::RIGHT)) !== 0
            || ($inputFlags & (1 << PlayerAuthInputFlags::DOWN)) !== 0;

        if ($movingLaterally && !$player->isSprinting()) {
            $playerId = spl_object_id($playerAPI);
            if (isset($this->playerFlags[$playerId])) {
                $this->failed($playerAPI);
            } else {
                $this->playerFlags[$playerId] = true;
            }

            $this->debug($playerAPI, sprintf(
                "inputFlags=%d, inputMode=%d, checkExists=%s",
                $inputFlags,
                $inputMode,
                isset($this->playerFlags[$playerId]) ? "true" : "false"
            ));
        }
    }

    /**
     * Checks player movement speed for irregularities.
     */
    public function checkEvent(Event $event, PlayerAPI $playerAPI): void {
        if (!$event instanceof PlayerMoveEvent) {
            return;
        }

        $player = $playerAPI->getPlayer();
        $distanceSquared = MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo());
        $maxSpeed = $this->getConstant("max-speed");

        $playerId = spl_object_id($playerAPI);
        if ($distanceSquared > $maxSpeed && !$player->getEffects()->has(VanillaEffects::SPEED())) {
            $this->playerFlags[$playerId] = true;
        } else {
            unset($this->playerFlags[$playerId]);
        }

        $this->debug($playerAPI, sprintf(
            "speed=%.2f, isSprinting=%s",
            $distanceSquared,
            $player->isSprinting() ? "true" : "false"
        ));
    }
}
