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
use Xibo\Entity\Media;
use Xibo\Service\DefaultLayoutBuilder;

/**
 * The Default Layout takes its colours from the brand's theme.css and its logos from the brand's PNGs.
 */
class DefaultLayoutBuilderTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/brand_' . bin2hex(random_bytes(4));
        mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->folder);
    }

    public function testColoursDefaultToTheStockDesignWithoutThemeCss(): void
    {
        $this->assertSame(
            ['brand-primary' => '#02266c', 'brand-accent' => '#8cdffd'],
            DefaultLayoutBuilder::readColours($this->folder)
        );
    }

    public function testColoursComeFromThemeCss(): void
    {
        file_put_contents(
            $this->folder . '/theme.css',
            ":root {\n  --brand-primary: #123456;\n  --brand-accent: #abc;\n}\n"
        );

        $this->assertSame(
            ['brand-primary' => '#123456', 'brand-accent' => '#abc'],
            DefaultLayoutBuilder::readColours($this->folder)
        );
    }

    public function testColourThatIsNotHexFallsBackToDefault(): void
    {
        file_put_contents(
            $this->folder . '/theme.css',
            ':root { --brand-primary: oklch(0.5 0.2 250); --brand-accent: #00ff00; }'
        );

        $this->assertSame(
            ['brand-primary' => '#02266c', 'brand-accent' => '#00ff00'],
            DefaultLayoutBuilder::readColours($this->folder)
        );
    }

    public function testColourWithTheWrongNumberOfDigitsFallsBackToDefault(): void
    {
        file_put_contents($this->folder . '/theme.css', ':root { --brand-primary: #12345; --brand-accent: #ff00; }');

        $this->assertSame(
            ['brand-primary' => '#02266c', 'brand-accent' => '#ff00'],
            DefaultLayoutBuilder::readColours($this->folder)
        );
    }

    public function testAccentThatWouldNotReadOnTheBackgroundUsesTheClockColour(): void
    {
        // Orange on bright blue is about 1.6:1.
        file_put_contents(
            $this->folder . '/theme.css',
            ':root { --brand-primary: #0e70f6; --brand-accent: #eb7857; }'
        );
        $this->assertSame(
            ['brand-primary' => '#0e70f6', 'brand-accent' => '#f9f9f9'],
            DefaultLayoutBuilder::readColours($this->folder)
        );

        // The stock cyan on a white primary.
        file_put_contents($this->folder . '/theme.css', ':root { --brand-primary: #ffffff; }');
        $this->assertSame(
            ['brand-primary' => '#ffffff', 'brand-accent' => '#222222'],
            DefaultLayoutBuilder::readColours($this->folder)
        );
    }

    public function testClockContrastsWithTheBackground(): void
    {
        // The stock navy keeps the light clock the stock layout had.
        $this->assertSame('#f9f9f9', DefaultLayoutBuilder::getClockColour('#02266c'));
        $this->assertSame('#f9f9f9', DefaultLayoutBuilder::getClockColour('#0e70f6'));
        $this->assertSame('#222222', DefaultLayoutBuilder::getClockColour('#ffffff'));
        $this->assertSame('#222222', DefaultLayoutBuilder::getClockColour('#fd0'));
    }

    public function testLogoPrefersTheLayoutLogoThenTheOneForTheBackground(): void
    {
        $dark = DefaultLayoutBuilder::getLogoFiles('#02266c');
        $light = DefaultLayoutBuilder::getLogoFiles('#ffffff');
        $this->assertNull(DefaultLayoutBuilder::findFile($this->folder, $dark));

        // SVGs can't be shown by players, so they are never picked.
        touch($this->folder . '/logo.svg');
        touch($this->folder . '/logo-dark.png');
        $this->assertSame($this->folder . '/logo-dark.png', DefaultLayoutBuilder::findFile($this->folder, $dark));

        // logo.png is the light logo, for a dark background; logo-dark.png is for a light one.
        touch($this->folder . '/logo.png');
        $this->assertSame($this->folder . '/logo.png', DefaultLayoutBuilder::findFile($this->folder, $dark));
        $this->assertSame($this->folder . '/logo-dark.png', DefaultLayoutBuilder::findFile($this->folder, $light));

        touch($this->folder . '/layout-logo.png');
        $this->assertSame(
            $this->folder . '/layout-logo.png',
            DefaultLayoutBuilder::findFile($this->folder . '/', $light)
        );
    }

    public function testElementsUseTheLogosAndColours(): void
    {
        $colours = ['brand-primary' => '#123456', 'brand-accent' => '#abcdef'];
        $logo = $this->createMedia(7, 'Logo');
        $elements = DefaultLayoutBuilder::getElements($colours, $logo, $this->createMedia(8, 'Icon'));

        $this->assertCount(1, $elements);
        $items = $elements[0]['elements'];
        $this->assertSame(
            ['global_library_image', 'global_library_image', 'date_advanced', 'date_advanced', 'line'],
            array_column($items, 'id')
        );
        $this->assertSame([7, 8], array_column(array_slice($items, 0, 2), 'mediaId'));
        $this->assertSame('#abcdef', $this->property($items[3], 'fontColor'));
        $this->assertSame('#abcdef', $this->property($items[4], 'lineColor'));

        // Every element carries its properties, which the layout's font scan reads.
        foreach ($items as $item) {
            $this->assertIsArray($item['properties']);
        }
    }

    public function testElementsLeaveOutMissingLogos(): void
    {
        $colours = ['brand-primary' => '#123456', 'brand-accent' => '#abcdef'];
        $items = DefaultLayoutBuilder::getElements($colours, null, null)[0]['elements'];

        $this->assertSame(['date_advanced', 'date_advanced', 'line'], array_column($items, 'id'));
        $this->assertArrayNotHasKey('mediaId', $items[0]);
    }

    private function createMedia(int $mediaId, string $name): Media
    {
        $media = $this->createMock(Media::class);
        $media->mediaId = $mediaId;
        $media->name = $name;
        return $media;
    }

    private function property(array $element, string $id): mixed
    {
        foreach ($element['properties'] as $property) {
            if ($property['id'] === $id) {
                return $property['value'];
            }
        }
        return null;
    }
}
