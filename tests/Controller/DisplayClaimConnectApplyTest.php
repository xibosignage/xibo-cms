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

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\ServerRequest as PsrServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Stash\Driver\Ephemeral;
use Stash\Pool;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Controller\DisplayClaim;
use Xibo\Entity\Display;
use Xibo\Entity\User;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\SanitizerService;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Saving Manually Configure settings is authorised by holding the code that brought the display in,
 * not by rights over the display, so only the code's owner may apply them, and only once.
 */
class DisplayClaimConnectApplyTest extends TestCase
{
    private const CODE = 'K7QX';
    private const USER_ID = 7;

    private Pool $pool;
    private ApplicationState $state;
    private Display $display;

    protected function setUp(): void
    {
        $this->pool = new Pool(new Ephemeral());
        $this->state = new ApplicationState();

        $this->display = $this->createMock(Display::class);
        $this->display->displayId = 5;
        $this->display->display = 'Player name';
        $this->display->folderId = 1;
        $this->display->licensed = 1;
    }

    private function storeClaim(array $claim): void
    {
        $item = $this->pool->getItem(Display::getManualConnectCacheKey(self::CODE));
        $item->set($claim);
        $this->pool->save($item);
    }

    private function storedClaim(): array
    {
        return $this->pool->getItem(Display::getManualConnectCacheKey(self::CODE))->get();
    }

    private function connectApply(array $params, array $features = ['displays.modify' => true]): void
    {
        $user = $this->createMock(User::class);
        $user->userId = self::USER_ID;
        $user->method('featureEnabled')->willReturnCallback(fn ($feature) => $features[$feature] ?? false);
        $user->method('checkViewable')->willReturn(true);

        $dependencies = new BaseDependenciesService();
        $dependencies->setConfig($this->createMock(ConfigServiceInterface::class));
        $dependencies->setLogger($this->createMock(LogServiceInterface::class));
        $dependencies->setSanitizer(new SanitizerService());
        $dependencies->setUser($user);
        $dependencies->setState($this->state);
        $dependencies->setDispatcher(new EventDispatcher());

        $displayFactory = $this->createMock(DisplayFactory::class);
        $displayFactory->method('getById')->with(5)->willReturn($this->display);

        $controller = new DisplayClaim(
            $displayFactory,
            $this->pool,
            $this->createMock(ConfigServiceInterface::class),
            $this->createMock(NotificationFactory::class),
            $this->createMock(UserGroupFactory::class),
            $this->createMock(DisplayGroupFactory::class),
            $this->createMock(FolderFactory::class)
        );
        $controller->useBaseDependenciesService($dependencies);
        $controller->setNoOutput();

        $controller->connectApply(
            (new ServerRequest(new PsrServerRequest('PUT', '/display/connect/' . self::CODE)))
                ->withParsedBody($params),
            new Response(new PsrResponse(), new HttpFactory()),
            self::CODE
        );
    }

    public function testTheOwnerOfAConnectedCodeAppliesTheSettingsOnce()
    {
        $this->storeClaim(['userId' => self::USER_ID, 'displayId' => 5, 'notificationId' => 3]);
        $this->display->expects($this->once())->method('save');

        $this->connectApply(['displayName' => 'Lobby', 'authorised' => 0]);

        $this->assertSame('Lobby', $this->display->display);
        // Turned off even though the CMS authorised it automatically.
        $this->assertSame(0, $this->display->licensed);
        $this->assertSame('Lobby', $this->state->getData()['display']);
        $this->assertTrue($this->state->getData()['displayGroupAssigned']);
        $this->assertTrue($this->storedClaim()['applied']);
    }

    // Authorising is the only setting an add-only user may choose, as with an activation code.
    public function testWithoutDisplaysModifyOnlyTheAuthorisationIsApplied()
    {
        $this->storeClaim(['userId' => self::USER_ID, 'displayId' => 5]);

        $this->connectApply(['displayName' => 'Lobby', 'folderId' => 9, 'authorised' => 1], []);

        $this->assertSame('Player name', $this->display->display);
        $this->assertSame(1, $this->display->folderId);
        $this->assertSame(1, $this->display->licensed);
    }

    // Reported the same as an unknown code, so codes can't be probed.
    public function testSomeoneElsesCodeIsNotFound()
    {
        $this->storeClaim(['userId' => 99, 'displayId' => 5]);
        $this->display->expects($this->never())->method('save');

        $this->expectException(NotFoundException::class);
        $this->connectApply(['displayName' => 'Lobby']);
    }

    public function testAnExpiredCodeIsNotFound()
    {
        $this->expectException(NotFoundException::class);
        $this->connectApply(['displayName' => 'Lobby']);
    }

    public static function refusedClaims(): array
    {
        return [
            'the Player has not connected' => [['displayId' => null]],
            'the Player was already registered' => [['displayId' => 5, 'existing' => true]],
            'the settings were already saved' => [['displayId' => 5, 'applied' => true]],
        ];
    }

    #[DataProvider('refusedClaims')]
    public function testAClaimThatCannotTakeSettingsIsRefused(array $claim)
    {
        $this->storeClaim(['userId' => self::USER_ID] + $claim);
        $this->display->expects($this->never())->method('save');

        try {
            $this->connectApply(['displayName' => 'Lobby']);
            $this->fail('The settings should have been refused.');
        } catch (InvalidArgumentException) {
            // Left as it was, so the form still reports the same state.
            $this->assertSame(['userId' => self::USER_ID] + $claim, $this->storedClaim());
        }
    }

    // e.g. no licence left: nothing was saved, so the operator can correct the form and save again.
    public function testAFailedSavePutsTheClaimBack()
    {
        $claim = ['userId' => self::USER_ID, 'displayId' => 5, 'notificationId' => 3];
        $this->storeClaim($claim);
        $this->display->method('save')->willThrowException(new GeneralException('No licence left'));

        try {
            $this->connectApply(['displayName' => 'Lobby', 'authorised' => 1]);
            $this->fail('The failed save should have been reported.');
        } catch (GeneralException $e) {
            $this->assertSame('No licence left', $e->getMessage());
        }

        $this->assertSame($claim, $this->storedClaim());
    }
}
