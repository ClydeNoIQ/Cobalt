<?php

/*
 *
 *  ____           _            __           _____
 * |  _ \    ___  (_)  _ __    / _|  _   _  |_   _|   ___    __ _   _ __ ___
 * | |_) |  / _ \ | | | '_ \  | |_  | | | |   | |    / _ \  / _` | | '_ ` _ \
 * |  _ <  |  __/ | | | | | | |  _| | |_| |   | |   |  __/ | (_| | | | | | | |
 * |_| \_\  \___| |_| |_| |_| |_|    \__, |   |_|    \___|  \__,_| |_| |_| |_|
 *                                   |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\moving\speed;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\Event;
use pocketmine\entity\effect\VanillaEffects;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\utils\BlockUtil;
use pocketmine\block\BlockTypeIds;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function intval;

class SpeedA extends Check {
	public function getName() : string {
		return "Speed";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 4;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if ($player === null) {
			return;
		}
		if($event instanceof PlayerMoveEvent) {
			if(
				!$player->isSurvival() ||
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->getSlimeBlockTicks() < 20 || 
				$playerAPI->isOnAdhesion()
			) {
				return;
			}
			
			$time = $playerAPI->getExternalData("moveTimeA");
			if($time !== null) {
				$distance = round(BlockUtil::distance($event->getFrom(), $event->getTo()), 5); // Round precision of 5
				$timeDiff = abs($time - microtime(true));
				$speed = round($distance / $timeDiff, 5); // Round precision of 5
				
				// Calculate the possible speed limit
				$speedLimit = $this->getConstant("walking-speed-limit"); // Walking 
				$speedLimit += $player->isSprinting() ? $this->getConstant("sprinting-speed-limit") : 0; // Sprinting
				$speedLimit += $playerAPI->getJumpTicks() < 40 ? $this->getConstant("jump-speed-limit") : 0; // Jumping
				$speedLimit += $player->getInAirTicks() > 10 ? $this->getConstant("momentum-speed-limit") : 0; // Falling Momentum
				$speedLimit += $playerAPI->isOnIce() ? $this->getConstant("ice-walking-speed-limit") : 0; // Ice walking limit
				
				$timeLimit = $this->getConstant("time-limit");
				
				// Calculate max distance must be the limit of blocks travelled.
				$distanceLimit = $this->getConstant("wakling-distance-limit"); // Walking
				$distanceLimit += $player->isSprinting() ? $this->getConstant("sprinting-distance-limit") : 0; // Sprinting
				$distanceLimit += $playerAPI->getJumpTicks() < 40 ? $this->getConstant("jump-distance-limit") : 0; // Jumping
				$distanceLimit += $player->getInAirTicks() > 10 ? $this->getConstant("momentum-distance-limit") : 0; // Falling Momentum
				$distanceLimit += $playerAPI->isOnIce() ? $this->getConstant("ice-walking-distance-limit") : 0; // Ice walking limit
				
				// Calculate speed potion deriviation..
				if(($effect = $player->getEffects()->get(VanillaEffects::SPEED())) !== null) {
					$speedLimit += $this->getConstant("speed-effect-limit") * $effect->getEffectLevel();
					$timeLimit += $this->getConstant("time-effect-limit") * $effect->getEffectLevel();
					$distanceLimit += $this->getConstant("speed-effect-distance-limit") * $effect->getEffectLevel();
				}
				
				$this->debug($playerAPI, "timeDiff=$timeDiff, speed=$speed, distance=$distance, speedLimit=$speedLimit, distanceLimit=$distanceLimit, timeLimit=$timeLimit");
				
				// If the time travelled is greater than the calculated time limit, fail immediately.
				// If speed is on limit and the distance travelled limit is high. 
				if($time > $timeLimit || $speed > $speedLimit && $distance > $distanceLimit) {
					$this->failed($playerAPI);
				}
			}
			
			$playerAPI->setExternalData("moveTimeA", microtime(true));
		}
	}
}