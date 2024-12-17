<?php

/*
 *  ____           _            __           _____
 * |  _ \    ___  (_)  _ __    / _|  _   _  |_   _|   ___    __ _   _ __ ___
 * | |_) |  / _ \ | | | '_ \  | |_  | | | |   | |    / _ \  / _` | | '_ ` _ \
 * |  _ <  |  __/ | | | | | | |  _| | |_| |   | |   |  __/ | (_| | | | | | | |
 * |_| \_\  \___| |_| |_| |_| |_|    \__, |   |_|    \___|  \__,_| |_| |_| |_|
 *                                   |___/
 *
 * This software enforces "vanilla Minecraft" mechanics and prevents exploitation
 * of Minecraft protocol weaknesses, ensuring server security. It detects various
 * cheats including flying, speeding, combat hacks, block-breaking hacks, and more.
 *
 * @author Blitz
 * @link https://github.com/Blitz/
 */

declare(strict_types=1);

namespace Blitz\Cobalt\checks\aimassist;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use Blitz\Cobalt\checks\Check;
use Blitz\Cobalt\player\PlayerAPI;
use Blitz\Cobalt\utils\discord\DiscordWebhookException;

class AimAssistA extends Check {
    public function getName(): string {
        return "AimAssist";
    }

    public function getSubType(): string {
        return "A";
    }

    public function maxViolations(): int {
        return 10;
    }

    /**
     * Checks for aim assist violations.
     *
     * @throws DiscordWebhookException
     */
    public function check(DataPacket $packet, PlayerAPI $playerAPI): void {
        if (!$packet instanceof PlayerAuthInputPacket) {
            return;
        }

        $player = $playerAPI->getPlayer();

        // Skip checks for non-survival, attack delays, or teleport delays
        if (
            !$player->isSurvival() ||
            $playerAPI->getAttackTicks() > 20 ||
            $playerAPI->getTeleportTicks() < 100 ||
            $player->isFlying() ||
            $player->getAllowFlight()
        ) {
            return;
        }

        $nLocation = $playerAPI->getNLocation();

        if (!empty($nLocation)) {
            $yawDifference = abs($nLocation["to"]->getYaw() - $nLocation["from"]->getYaw());
            $samePitch = $nLocation["from"]->getPitch() === $nLocation["to"]->getPitch();

            if ($samePitch && $yawDifference >= 3 && $nLocation["from"]->getPitch() !== 90 && $nLocation["to"]->getPitch() !== 90) {
                $this->failed($playerAPI);
            }

            $this->debug($playerAPI, "yawDifference={$yawDifference}");
        }
    }
}
