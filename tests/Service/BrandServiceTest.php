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

namespace Xibo\Tests\Service;

use PHPUnit\Framework\TestCase;
use Xibo\Service\BrandService;
use Xibo\Service\BrandServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

class BrandServiceTest extends TestCase
{
    private string $library;

    protected function setUp(): void
    {
        $this->library = sys_get_temp_dir() . '/brand-service-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->library . 'brand', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->library . 'brand/*'));
        rmdir($this->library . 'brand');
        rmdir($this->library);
    }

    private function put(string $file, string $content = 'x'): void
    {
        file_put_contents($this->library . 'brand/' . $file, $content);
    }

    public function testConfigIsAllNullWithoutConfigJson(): void
    {
        $config = (new BrandService($this->library))->getConfig();

        $this->assertSame([
            'productName' => null,
            'appName' => null,
            'supportUrl' => null,
            'cmsSourceUrl' => null,
            'aboutText' => null,
            'removeLicenceFromLogin' => null,
        ], $config);
    }

    public function testConfigReadsCurrentAndLegacyKeys(): void
    {
        $this->put('config.json', json_encode([
            'productName' => 'Acme Signage',
            'app_name' => 'Acme',
            'theme_url' => 'https://acme.example',
            'cmsSourceUrl' => '',
            'aboutText' => '<p>About</p>',
            'removeLicenceFromLogin' => 1,
        ]));

        $config = (new BrandService($this->library))->getConfig();

        $this->assertSame('Acme Signage', $config['productName']);
        $this->assertSame('Acme', $config['appName']);
        $this->assertSame('https://acme.example', $config['supportUrl']);
        $this->assertSame('', $config['cmsSourceUrl']);
        $this->assertSame('<p>About</p>', $config['aboutText']);
        $this->assertTrue($config['removeLicenceFromLogin']);
    }

    public function testEmptyLibraryLocationGivesAllNullConfig(): void
    {
        $this->assertSame(
            array_fill_keys(
                ['productName', 'appName', 'supportUrl', 'cmsSourceUrl', 'aboutText', 'removeLicenceFromLogin'],
                null
            ),
            (new BrandService(''))->getConfig()
        );
    }

    public function testEmptyNamesAndSupportUrlKeepDefaults(): void
    {
        $this->put('config.json', json_encode(['productName' => '', 'appName' => '', 'supportUrl' => '']));

        $config = (new BrandService($this->library))->getConfig();

        $this->assertNull($config['productName']);
        $this->assertNull($config['appName']);
        $this->assertNull($config['supportUrl']);
    }

    public function testLogoPrefersSvgOverPng(): void
    {
        $service = new BrandService($this->library);
        $this->assertSame('/brand/logo.png', $service->getAssetUrl(BrandServiceInterface::SLOT_LOGO));

        $this->put('logo.svg');
        $this->assertSame('/brand/logo.svg', $service->getAssetUrl(BrandServiceInterface::SLOT_LOGO));
        $this->assertSame('/brand/logo-icon.png', $service->getAssetUrl(BrandServiceInterface::SLOT_LOGO_ICON));
    }

    public function testDarkLogoFallsBackToLogo(): void
    {
        $service = new BrandService($this->library);
        $this->put('logo.svg');
        $this->assertSame('/brand/logo.svg', $service->getAssetUrl(BrandServiceInterface::SLOT_LOGO_DARK));

        $this->put('logo-dark.png');
        $this->assertSame('/brand/logo-dark.png', $service->getAssetUrl(BrandServiceInterface::SLOT_LOGO_DARK));

        $this->put('logo-dark.svg');
        $this->assertSame('/brand/logo-dark.svg', $service->getAssetUrl(BrandServiceInterface::SLOT_LOGO_DARK));
    }

    public function testFaviconStylesheetAndPath(): void
    {
        $service = new BrandService($this->library);

        $this->assertSame('/brand/favicon.ico', $service->getAssetUrl(BrandServiceInterface::SLOT_FAVICON));
        $this->assertSame('/brand/theme.css', $service->getStylesheetUrl());
        $this->assertSame([], $service->getTokens(), 'theme.css sets the colours');
        $this->assertSame(
            $this->library . 'brand/logo.png',
            $service->getAssetPath(BrandServiceInterface::SLOT_LOGO_DARK)
        );
    }

    public function testUnknownSlotThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new BrandService($this->library))->getAssetUrl('nope');
    }
}
