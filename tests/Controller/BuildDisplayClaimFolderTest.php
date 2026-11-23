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
use Xibo\Entity\Folder;
use Xibo\Entity\User;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * The Player's registration puts the display in the claim's folder without checking it, so the
 * folder is checked when the claim is built, from either Add Display route.
 */
class BuildDisplayClaimFolderTest extends TestCase
{
    private function buildClaim(
        array $params,
        FolderFactory $folderFactory,
        bool $canViewFolder = true,
        bool $canModify = true
    ): array {
        $user = $this->createMock(User::class);
        $user->userId = 7;
        $user->method('featureEnabled')->with('displays.modify')->willReturn($canModify);
        $user->method('checkViewable')->willReturn($canViewFolder);
        $user->method('isSuperAdmin')->willReturn(false);

        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->with('FOLDERS_ALLOW_SAVE_IN_ROOT')->willReturn(0);

        $dependencies = new BaseDependenciesService();
        $dependencies->setUser($user);
        $dependencies->setConfig($config);

        $controller = new class ($this->createMock(DisplayGroupFactory::class), $folderFactory) extends Base {
            use BuildDisplayClaimTrait;

            public function __construct(
                private readonly DisplayGroupFactory $displayGroupFactory,
                private readonly FolderFactory $folderFactory
            ) {
            }

            public function build($sanitizedParams): array
            {
                return $this->buildDisplayClaim($sanitizedParams);
            }
        };
        $controller->useBaseDependenciesService($dependencies);

        return $controller->build((new SanitizerService())->getSanitizer($params));
    }

    private function folderFactory(): FolderFactory
    {
        $folderFactory = $this->createMock(FolderFactory::class);
        $folderFactory->method('getById')->with(9, 0)->willReturn($this->createMock(Folder::class));

        return $folderFactory;
    }

    public function testAFolderTheUserCanSeeIsKept()
    {
        $this->assertSame(9, $this->buildClaim(['folderId' => 9], $this->folderFactory())['folderId']);
    }

    // The folder picker only offers folders the user can see, so anything else was sent by hand.
    public function testAFolderTheUserCannotSeeIsRefused()
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied to Folder');

        $this->buildClaim(['folderId' => 9], $this->folderFactory(), false);
    }

    public function testRootIsRefusedWhenSavingInRootIsDisabled()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Saving into root folder is disabled');

        $this->buildClaim(['folderId' => 1], $this->createMock(FolderFactory::class));
    }

    // Without the edit permission the folder is ignored, so it isn't looked up either.
    public function testAFolderFromAUserWhoCannotEditIsIgnored()
    {
        $folderFactory = $this->createMock(FolderFactory::class);
        $folderFactory->expects($this->never())->method('getById');

        $claim = $this->buildClaim(['folderId' => 9], $folderFactory, false, false);

        $this->assertArrayNotHasKey('folderId', $claim);
    }
}
