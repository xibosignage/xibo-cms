<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Xibo\Controller\Base;
use Xibo\Controller\BuildDisplayClaimTrait;
use Xibo\Entity\User;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\BaseDependenciesService;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * The settings on the Add Display form are checked when the activation code is submitted, because the
 * Player's registration saves them without validating.
 */
class BuildDisplayClaimTraitTest extends TestCase
{
    protected function setUp(): void
    {
        // Names are counted in characters, which needs UTF-8. Reset it in case an earlier test left it changed.
        mb_internal_encoding('UTF-8');
    }

    private function buildClaim(array $params, bool $canModify = true): array
    {
        $user = $this->createMock(User::class);
        $user->userId = 7;
        $user->method('featureEnabled')->with('displays.modify')->willReturn($canModify);

        $dependencies = new BaseDependenciesService();
        $dependencies->setUser($user);

        $controller = new class ($this->createMock(DisplayGroupFactory::class)) extends Base {
            use BuildDisplayClaimTrait;

            public function __construct(private readonly DisplayGroupFactory $displayGroupFactory)
            {
            }

            public function build($sanitizedParams): array
            {
                return $this->buildDisplayClaim($sanitizedParams);
            }
        };
        $controller->useBaseDependenciesService($dependencies);

        return $controller->build((new SanitizerService())->getSanitizer($params));
    }

    public function testANameOfFiftyCharactersIsKept()
    {
        $name = str_repeat('a', 50);

        $this->assertSame($name, $this->buildClaim(['displayName' => $name])['display']);
    }

    // The column holds 50 characters, so a longer name would fail the Player's registration.
    public function testANameOverFiftyCharactersIsRefused()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name cannot exceed 50 characters');

        $this->buildClaim(['displayName' => str_repeat('a', 51)]);
    }

    // Counted in characters, as the column is, not bytes.
    public function testANameIsCountedInCharacters()
    {
        $name = str_repeat('é', 50);

        $this->assertSame($name, $this->buildClaim(['displayName' => $name])['display']);
    }

    // Without the edit permission the name is ignored, so it isn't checked either.
    public function testANameFromAUserWhoCannotEditIsIgnored()
    {
        $claim = $this->buildClaim(['displayName' => str_repeat('a', 51)], false);

        $this->assertArrayNotHasKey('display', $claim);
    }
}
