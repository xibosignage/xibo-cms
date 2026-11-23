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
use Psr\Log\NullLogger;
use Xibo\Entity\Layout;
use Xibo\Entity\User;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\FontFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\UserFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DefaultLayoutBuilder;
use Xibo\Service\DefaultsImporter;
use Xibo\Service\MediaServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * The defaults are imported once: the Default Layout is built from the brand and its zip ignored, a retry skips
 * layouts already saved, an unusable zip is skipped, and a failure stops the import without recording it as done.
 */
class DefaultsImporterTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        // A brand folder whose layouts hold a stock layout and a Default Layout zip, which is ignored.
        $this->folder = sys_get_temp_dir() . '/defaults_' . bin2hex(random_bytes(4));
        mkdir($this->folder . '/layouts', 0777, true);

        foreach (['stock.zip' => 'Stock', 'default-layout.zip' => 'Default Layout'] as $file => $name) {
            $zip = new \ZipArchive();
            $zip->open($this->folder . '/layouts/' . $file, \ZipArchive::CREATE);
            $zip->addFromString('layout.json', json_encode(['layoutDefinitions' => ['layout' => $name]]));
            $zip->close();
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->folder . '/layouts/stock.zip');
        @unlink($this->folder . '/layouts/default-layout.zip');
        @rmdir($this->folder . '/layouts');
        @rmdir($this->folder);
    }

    /**
     * @param \Closure(LayoutFactory): void $configureLayouts
     */
    private function createImporter(
        bool $alreadyImported,
        \Closure $configureLayouts,
        ConfigServiceInterface $config,
        ?DefaultLayoutBuilder $builder = null
    ): DefaultsImporter {
        if ($builder === null) {
            $builder = $this->createMock(DefaultLayoutBuilder::class);
            $builder->method('build')->willReturn($this->createLayout(10));
        }

        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('exists')->willReturn($alreadyImported);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $users = $this->createMock(UserFactory::class);
        $users->method('getSystemUser')->willReturn($user);

        $layouts = $this->createMock(LayoutFactory::class);
        $configureLayouts($layouts);

        // Unless the test sets one, the default is a layout a user chose, registered last so a test's stub wins.
        $chosen = $this->createLayout(1);
        $chosen->layout = 'Chosen';
        $layouts->method('getById')->willReturn($chosen);

        // Every default font is already installed.
        $fonts = $this->createMock(FontFactory::class);
        $fonts->method('getByFileName')->willReturn([new \stdClass()]);

        return new DefaultsImporter(
            $config,
            $store,
            $layouts,
            $users,
            $this->createMock(DataSetFactory::class),
            $fonts,
            $this->createMock(MediaServiceInterface::class),
            $builder,
            new NullLogger()
        );
    }

    private function createLayout(int $layoutId): Layout
    {
        $layout = $this->createMock(Layout::class);
        $layout->layoutId = $layoutId;
        return $layout;
    }

    private function createConfig(bool $expectsDone): ConfigServiceInterface
    {
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(fn (string $setting) => match ($setting) {
            'DEFAULTS_IMPORTED' => 0,
            'DEFAULT_LAYOUT' => '1',
            default => null,
        });
        $config->expects($expectsDone ? $this->once() : $this->never())
            ->method('changeSetting')
            ->with('DEFAULTS_IMPORTED', 1);
        return $config;
    }

    public function testIsRequiredOnlyUntilImported(): void
    {
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturn(1);
        $importer = $this->createImporter(false, fn () => null, $config);

        $this->assertFalse($importer->isRequired());
    }

    public function testImportsLayoutAndRecordsDone(): void
    {
        $importer = $this->createImporter(false, function (LayoutFactory $layouts) {
            $layout = $this->createLayout(11);
            $layout->expects($this->once())->method('save');
            $layouts->expects($this->once())->method('createFromZip')->willReturn($layout);
        }, $this->createConfig(true));

        $importer->import($this->folder);
    }

    public function testRetrySkipsLayoutAlreadyImported(): void
    {
        $importer = $this->createImporter(true, function (LayoutFactory $layouts) {
            $layouts->expects($this->never())->method('createFromZip');
        }, $this->createConfig(true));

        $importer->import($this->folder);
    }

    public function testUnusableZipIsSkipped(): void
    {
        $importer = $this->createImporter(false, function (LayoutFactory $layouts) {
            $layouts->method('createFromZip')->willThrowException(new InvalidArgumentException('Bad zip'));
        }, $this->createConfig(true));

        $importer->import($this->folder);
    }

    public function testStorageFailureStopsWithoutRecordingDone(): void
    {
        $importer = $this->createImporter(false, function (LayoutFactory $layouts) {
            $layouts->method('createFromZip')->willThrowException(new GeneralException('Storage unavailable'));
        }, $this->createConfig(false));

        $this->expectException(GeneralException::class);
        $importer->import($this->folder);
    }

    public function testMissingLayoutFolderIsNotRecordedAsDone(): void
    {
        $importer = $this->createImporter(false, function (LayoutFactory $layouts) {
            $layouts->expects($this->never())->method('createFromZip');
        }, $this->createConfig(false));

        $this->expectException(GeneralException::class);
        $importer->import($this->folder . '/missing');
    }

    public function testBuildsDefaultLayoutFromBrandAndIgnoresItsZip(): void
    {
        $builder = $this->createMock(DefaultLayoutBuilder::class);
        $builder->expects($this->once())
            ->method('build')
            ->with($this->folder, 1)
            ->willReturn($this->createLayout(10));

        // Only stock.zip is imported; default-layout.zip is skipped.
        $importer = $this->createImporter(false, function (LayoutFactory $layouts) {
            $layouts->expects($this->once())->method('createFromZip')->willReturn($this->createLayout(11));
        }, $this->createConfig(true), $builder);

        $importer->import($this->folder . '/');
    }

    public function testRetrySkipsDefaultLayoutAlreadyBuilt(): void
    {
        $builder = $this->createMock(DefaultLayoutBuilder::class);
        $builder->expects($this->never())->method('build');

        $importer = $this->createImporter(true, fn () => null, $this->createConfig(true), $builder);

        $importer->import($this->folder);
    }

    public function testBuiltLayoutBecomesTheDefaultWhenNoneIsSet(): void
    {
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturn(null);
        $changes = [];
        $config->method('changeSetting')->willReturnCallback(function ($setting, $value) use (&$changes) {
            $changes[$setting] = $value;
        });

        // No default is set until the built layout becomes it, so the stock layout imported next doesn't replace it.
        $importer = $this->createImporter(false, function (LayoutFactory $layouts) use (&$changes) {
            $layouts->method('getById')->willReturnCallback(function () use (&$changes) {
                if (!isset($changes['DEFAULT_LAYOUT'])) {
                    throw new NotFoundException();
                }
                return $this->createLayout($changes['DEFAULT_LAYOUT']);
            });
            $layouts->method('createFromZip')->willReturn($this->createLayout(11));
        }, $config);

        $importer->import($this->folder);

        $this->assertSame(['DEFAULT_LAYOUT' => 10, 'DEFAULTS_IMPORTED' => 1], $changes);
    }

    public function testBuildFailureStopsWithoutRecordingDone(): void
    {
        $builder = $this->createMock(DefaultLayoutBuilder::class);
        $builder->method('build')->willThrowException(new GeneralException('Storage unavailable'));

        $importer = $this->createImporter(false, function (LayoutFactory $layouts) {
            $layouts->expects($this->never())->method('createFromZip');
        }, $this->createConfig(false), $builder);

        $this->expectException(GeneralException::class);
        $importer->import($this->folder);
    }

    public function testDailyMaintenanceImportsTheRestButRetriesABuildFailure(): void
    {
        $builder = $this->createMock(DefaultLayoutBuilder::class);
        $builder->method('build')->willThrowException(new GeneralException('Storage unavailable'));

        // The stock layouts are imported, but the import isn't recorded as done, so the next run builds it.
        $importer = $this->createImporter(false, function (LayoutFactory $layouts) {
            $layouts->expects($this->once())->method('createFromZip')->willReturn($this->createLayout(11));
        }, $this->createConfig(false), $builder);

        $importer->import($this->folder, true);
    }

    public function testRetriedBuildReplacesTheStockLayoutAsTheDefault(): void
    {
        $settings = ['DEFAULTS_IMPORTED' => 0, 'DEFAULT_LAYOUT' => 11];
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(fn (string $setting) => $settings[$setting] ?? null);
        $config->method('changeSetting')->willReturnCallback(function ($setting, $value) use (&$settings) {
            $settings[$setting] = $value;
        });

        // An earlier run whose build failed imported the stock layout, which became the default.
        $importer = $this->createImporter(false, function (LayoutFactory $layouts) {
            $stock = $this->createLayout(11);
            $stock->layout = 'Stock';
            $stock->method('getOwnerId')->willReturn(1);
            $layouts->method('getById')->willReturn($stock);
            $layouts->method('createFromZip')->willReturn($this->createLayout(12));
        }, $config);

        $importer->import($this->folder);

        $this->assertSame(10, $settings['DEFAULT_LAYOUT']);
        $this->assertSame(1, $settings['DEFAULTS_IMPORTED']);
    }

    public function testBuildKeepsADefaultChosenByAUser(): void
    {
        $settings = ['DEFAULTS_IMPORTED' => 0, 'DEFAULT_LAYOUT' => 20];
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(fn (string $setting) => $settings[$setting] ?? null);
        $config->method('changeSetting')->willReturnCallback(function ($setting, $value) use (&$settings) {
            $settings[$setting] = $value;
        });

        $importer = $this->createImporter(false, function (LayoutFactory $layouts) {
            $chosen = $this->createLayout(20);
            $chosen->layout = 'My Layout';
            $chosen->method('getOwnerId')->willReturn(1);
            $layouts->method('getById')->willReturn($chosen);
            $layouts->method('createFromZip')->willReturn($this->createLayout(12));
        }, $config);

        $importer->import($this->folder);

        $this->assertSame(20, $settings['DEFAULT_LAYOUT']);
    }

    public function testDailyMaintenanceSkipsAnyFailure(): void
    {
        $importer = $this->createImporter(false, function (LayoutFactory $layouts) {
            $layouts->method('createFromZip')->willThrowException(new GeneralException('Module missing'));
        }, $this->createConfig(true));

        $importer->import($this->folder, true);
    }
}
