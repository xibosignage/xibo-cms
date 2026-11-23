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
use PHPUnit\Framework\TestCase;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Xibo\Controller\Maintenance;
use Xibo\Entity\User;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\SanitizerService;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\MediaServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Tidy Library on local storage keeps the video cover of a video that still exists.
 */
class MaintenanceTidyLibraryTest extends TestCase
{
    private string $library;

    protected function setUp(): void
    {
        $this->library = sys_get_temp_dir() . '/tidy-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->library);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->library . '*'));
        rmdir($this->library);
    }

    public function testALiveVideosCoverIsKeptAndADeletedVideosCoverIsRemoved()
    {
        foreach (['12.mp4', '12_videocover.png', '99_videocover.png'] as $file) {
            touch($this->library . $file);
        }

        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(
            fn ($setting) => $setting === 'LIBRARY_LOCATION' ? $this->library : '1'
        );

        $user = $this->createMock(User::class);
        $user->userId = 1;

        $dependencies = new BaseDependenciesService();
        $dependencies->setConfig($config);
        $dependencies->setLogger($this->createMock(LogServiceInterface::class));
        $dependencies->setSanitizer(new SanitizerService());
        $dependencies->setUser($user);
        $dependencies->setState(new ApplicationState());

        // Media 12 is a video still in use; media 99 has been deleted.
        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('getTidyCandidates')->willReturn([['12.mp4' => ['mediaid' => 12]], [], []]);

        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->willReturn($this->createMock(LibraryStorageServiceInterface::class));

        // No journalled work pending.
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('select')->willReturn([]);

        $controller = new Maintenance(
            $store,
            $mediaFactory,
            $this->createMock(MediaServiceInterface::class),
            $registry
        );
        $controller->useBaseDependenciesService($dependencies);
        $controller->setNoOutput();
        $controller->tidyLibrary(
            new ServerRequest(new PsrServerRequest('POST', '/maintenance/tidy')),
            new Response(new PsrResponse(), new HttpFactory())
        );

        $this->assertFileExists($this->library . '12.mp4');
        $this->assertFileExists($this->library . '12_videocover.png');
        $this->assertFileDoesNotExist($this->library . '99_videocover.png');
    }
}
