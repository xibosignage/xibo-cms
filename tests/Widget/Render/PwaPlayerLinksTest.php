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

namespace Xibo\Tests\Widget\Render;

use PHPUnit\Framework\TestCase;
use Slim\Views\Twig;
use Stash\Driver\Ephemeral;
use Stash\Pool;
use Xibo\Entity\Display;
use Xibo\Factory\ModuleFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Widget\Render\WidgetDataProviderCache;
use Xibo\Widget\Render\WidgetHtmlRenderer;
use Xibo\Xmds\PlayerFileLink;

/**
 * A PWA caches files under their xmds.php link, so media in widget HTML and data is linked through the CMS.
 */
class PwaPlayerLinksTest extends TestCase
{
    private ConfigServiceInterface $config;
    private LibraryStorageRegistryInterface $registry;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigServiceInterface::class);
        $this->config->method('getApiKeyDetails')->willReturn(['encryptionKey' => 'secret']);

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('getDirectDownloadUrl')
            ->willReturnCallback(fn ($key) => 'https://storage.example.com/' . $key);
        $this->registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $this->registry->method('get')->willReturn($storage);
    }

    private function display(string $clientType): Display
    {
        $display = $this->createMock(Display::class);
        $display->method('getSetting')->willReturnCallback(
            fn ($key, $default = null) => $key === 'collectInterval' ? 300 : $default
        );
        $display->method('isPwa')->willReturn($clientType === 'chromeOS');
        $display->displayId = 7;
        $display->clientType = $clientType;
        $display->license = 'hardware-key';
        return $display;
    }

    private function playerFileLink(): PlayerFileLink
    {
        return new PlayerFileLink($this->config, $this->registry);
    }

    public function testWidgetHtmlLinksLibraryMediaThroughTheCms()
    {
        $renderer = new WidgetHtmlRenderer(
            $this->registry,
            $this->createMock(StorageServiceInterface::class),
            new Pool(new Ephemeral()),
            $this->createMock(Twig::class),
            $this->config,
            $this->createMock(ModuleFactory::class)
        );

        $output = $renderer->decorateForPlayer(
            $this->playerFileLink(),
            $this->display('chromeOS'),
            '<img src="[[mediaId=5]]"><img src="[[libraryId=6]]">',
            [5 => '5.jpg', 6 => '6.png'],
        );

        $this->assertStringContainsString('/xmds.php?file=5.jpg&displayId=7&type=M&itemId=5', $output);
        $this->assertStringContainsString('/xmds.php?file=6.png&displayId=7&type=M&itemId=6', $output);
        $this->assertStringNotContainsString('storage.example.com', $output);
    }

    public function testWidgetDataLinksMediaThroughTheCms()
    {
        $data = (new WidgetDataProviderCache(new Pool(new Ephemeral())))->decorateForPlayer(
            $this->playerFileLink(),
            $this->display('chromeOS'),
            [['image' => '[[mediaId=5]]'], (object)['image' => '[[mediaId=5]]']],
            [5 => '5.jpg'],
        );

        foreach ([$data[0]['image'], $data[1]->image] as $url) {
            $this->assertStringContainsString('/xmds.php?file=5.jpg&displayId=7&type=M&itemId=5', $url);
            $this->assertStringNotContainsString('storage.example.com', $url);
        }
    }

    public function testOtherPlayersKeepTheFileName()
    {
        $data = (new WidgetDataProviderCache(new Pool(new Ephemeral())))->decorateForPlayer(
            $this->playerFileLink(),
            $this->display('android'),
            [['image' => '[[mediaId=5]]']],
            [5 => '5.jpg'],
        );

        $this->assertSame('5.jpg', $data[0]['image']);
    }
}
