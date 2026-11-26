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

namespace Xibo\Tests\Factory;

use DOMDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Views\Twig;
use Stash\Interfaces\PoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Factory\ModuleTemplateFactory;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * User templates cannot declare assets by changing the asset tag's case or name.
 */
class ModuleTemplateAssetDeclarationTest extends TestCase
{
    public static function bypassAttempts(): array
    {
        return [
            'capitalised' => ['<assets><Asset id="poc" path="/custom/settings-custom.php" type="path"/></assets>'],
            'upper case' => ['<assets><ASSET id="poc" path="/custom/settings-custom.php" type="path"/></assets>'],
            'renamed child' => ['<assets><file id="poc" path="/custom/settings-custom.php" type="path"/></assets>'],
            'namespace prefix' => [
                '<assets><x:asset xmlns:x="urn:x" id="poc" path="/custom/settings-custom.php" type="path"/></assets>'
            ],
        ];
    }

    #[DataProvider('bypassAttempts')]
    public function testImportGuardRejectsAnyAssetDeclaration(string $assets): void
    {
        $this->assertTrue(ModuleTemplateFactory::declaresAssets($this->document($assets)));
    }

    public function testImportGuardRejectsLowercaseAsset(): void
    {
        $this->assertTrue(ModuleTemplateFactory::declaresAssets(
            $this->document('<assets><asset id="poc" path="/custom/settings-custom.php" type="path"/></assets>')
        ));
    }

    public function testImportGuardAcceptsTemplateWithoutAssets(): void
    {
        $this->assertFalse(ModuleTemplateFactory::declaresAssets($this->document('')));
    }

    #[DataProvider('bypassAttempts')]
    public function testParserIgnoresElementsNotNamedAsset(string $assets): void
    {
        $template = $this->factory()->createUserTemplate($this->xml($assets));

        $this->assertSame([], $template->assets);
    }

    public function testParserStillReadsLowercaseAsset(): void
    {
        $template = $this->factory()->createUserTemplate(
            $this->xml('<assets><asset id="shipped" path="/modules/assets/shipped.png" type="path"/></assets>')
        );

        $this->assertCount(1, $template->assets);
        $this->assertSame('/modules/assets/shipped.png', $template->assets[0]->path);
    }

    private function xml(string $assets): string
    {
        return '<?xml version="1.0"?>
            <template>
                <id>poc</id>
                <title>poc</title>
                <type>static</type>
                <dataType>text</dataType>
                <showIn>both</showIn>
                <properties></properties>
                ' . $assets . '
            </template>';
    }

    private function document(string $assets): DOMDocument
    {
        $document = new DOMDocument();
        $document->loadXML($this->xml($assets));
        return $document;
    }

    private function factory(): ModuleTemplateFactory
    {
        $dependencies = $this->createMock(BaseDependenciesService::class);
        $dependencies->method('getStore')->willReturn($this->createMock(StorageServiceInterface::class));
        $dependencies->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));
        $dependencies->method('getDispatcher')->willReturn(new EventDispatcher());

        $factory = new ModuleTemplateFactory(
            $this->createMock(PoolInterface::class),
            $this->createMock(Twig::class)
        );
        $factory->useBaseDependenciesService($dependencies);
        return $factory;
    }
}
