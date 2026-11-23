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

namespace Xibo\Service;

use Psr\Log\LoggerInterface;
use Xibo\Entity\Layout;
use Xibo\Entity\Media;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\Environment;
use Xibo\Support\Exception\GeneralException;

/**
 * Builds the Default Layout from a brand folder: its PNG logos and its theme.css colours.
 */
class DefaultLayoutBuilder
{
    public const NAME = 'Default Layout';

    private const DESCRIPTION = 'Displays need a default layout to show when no content is available, or when there'
        . ' is a problem with the scheduled content.';

    private const WIDTH = 1920;
    private const HEIGHT = 1080;
    private const DURATION = 10;

    /**
     * The colours used when the brand's theme.css doesn't set a token: the stock Default Layout's navy and cyan, so
     * the stock brand, whose theme.css sets none, keeps its design.
     */
    private const DEFAULT_TOKENS = ['brand-primary' => '#02266c', 'brand-accent' => '#8cdffd'];

    /** The clock is light on a dark background and dark on a light one. */
    private const CLOCK_LIGHT = '#f9f9f9';
    private const CLOCK_DARK = '#222222';

    /** Below this contrast ratio against the background, the accent gives way to the clock colour. */
    private const MIN_ACCENT_CONTRAST = 3.0;

    /** The files tried for the icon, in order. Players can't show SVG, so only PNGs. */
    public const ICON_FILES = ['layout-icon.png', 'logo-icon.png'];

    /** The thumbnail shown in the Layouts grid. The stock one shows the stock design. */
    public const THUMBNAIL_FILE = 'layout-thumbnail.png';

    public function __construct(
        private readonly ConfigServiceInterface $config,
        private readonly LayoutFactory $layoutFactory,
        private readonly PlaylistFactory $playlistFactory,
        private readonly WidgetFactory $widgetFactory,
        private readonly ModuleFactory $moduleFactory,
        private readonly MediaFactory $mediaFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Builds and saves the Default Layout, published.
     * @throws GeneralException
     */
    public function build(string $brandFolder, int $ownerId): Layout
    {
        $brandFolder = rtrim($brandFolder, '/') . '/';
        $colours = self::readColours($brandFolder);

        $logoFiles = self::getLogoFiles($colours['brand-primary']);
        $logo = $this->addLogo($brandFolder, $logoFiles, 'Default Layout Logo', $ownerId);
        $icon = $this->addLogo($brandFolder, self::ICON_FILES, 'Default Layout Icon', $ownerId);

        $layout = $this->layoutFactory->createEmpty();
        $layout->layout = self::NAME;
        $layout->description = self::DESCRIPTION;
        $layout->width = self::WIDTH;
        $layout->height = self::HEIGHT;
        $layout->orientation = 'landscape';
        $layout->backgroundzIndex = 0;
        $layout->backgroundColor = $colours['brand-primary'];
        $layout->schemaVersion = Environment::$XLF_VERSION;
        $layout->autoApplyTransitions = 1;
        $layout->setOwner($ownerId);

        $this->layoutFactory->addRegion($layout, 'canvas', self::WIDTH, self::HEIGHT, 0, 0);
        $region = $layout->regions[count($layout->regions) - 1];
        $region->regionPlaylist = $this->playlistFactory->create($region->name, $ownerId);

        $module = $this->moduleFactory->getByType('global');
        $widget = $this->widgetFactory->create($ownerId, null, 'global', self::DURATION, $module->schemaVersion);
        foreach ([$logo, $icon] as $media) {
            if ($media !== null) {
                $widget->assignMedia($media->mediaId);
            }
        }
        $widget->setOptionValue('itemsPerPage', 'attrib', 1);
        $widget->setOptionValue('elements', 'raw', json_encode(self::getElements($colours, $logo, $icon)));
        $widget->setOptionValue('enableStat', 'attrib', $this->config->getSetting('WIDGET_STATS_ENABLED_DEFAULT'));
        $widget->calculateDuration($module);
        $region->regionPlaylist->assignWidget($widget);

        $layout->save(['audit' => false, 'import' => true]);
        $this->storeThumbnail($layout, $brandFolder);
        return $layout;
    }

    /**
     * The brand's colours from its theme.css, falling back to the stock design's for a token that is missing or isn't
     * a hex colour. An accent that wouldn't read on the background is replaced by the clock colour.
     * @return array<string, string>
     */
    public static function readColours(string $brandFolder): array
    {
        $tokens = [];
        $themeFile = rtrim($brandFolder, '/') . '/theme.css';
        if (is_file($themeFile)) {
            [$tokens] = BrandService::readThemeTokens((string)file_get_contents($themeFile));
        }

        $colours = [];
        foreach (self::DEFAULT_TOKENS as $token => $default) {
            $value = $tokens[$token] ?? '';
            $colours[$token] = preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) ? $value : $default;
        }

        if (self::getContrast($colours['brand-accent'], $colours['brand-primary']) < self::MIN_ACCENT_CONTRAST) {
            $colours['brand-accent'] = self::getClockColour($colours['brand-primary']);
        }
        return $colours;
    }

    /**
     * Whichever of the light and dark clock colours contrasts more with $background, a hex colour as readColours
     * returns.
     */
    public static function getClockColour(string $background): string
    {
        return self::getContrast(self::CLOCK_LIGHT, $background) >= self::getContrast(self::CLOCK_DARK, $background)
            ? self::CLOCK_LIGHT
            : self::CLOCK_DARK;
    }

    /**
     * The logo files tried for a layout with $background, in order: layout-logo.png, then the brand logo meant for
     * that background. logo.png is the light logo, for dark backgrounds, and logo-dark.png the dark one, for light
     * backgrounds. Players can't show SVG, so only PNGs.
     * @return string[]
     */
    public static function getLogoFiles(string $background): array
    {
        return self::getClockColour($background) === self::CLOCK_LIGHT
            ? ['layout-logo.png', 'logo.png', 'logo-dark.png']
            : ['layout-logo.png', 'logo-dark.png', 'logo.png'];
    }

    /**
     * The WCAG contrast ratio between two hex colours, from 1 to 21.
     */
    public static function getContrast(string $colour, string $background): float
    {
        $lighter = max(self::getLuminance($colour), self::getLuminance($background));
        $darker = min(self::getLuminance($colour), self::getLuminance($background));
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * The relative luminance of a 3, 4, 6 or 8 digit hex colour, ignoring its alpha.
     */
    private static function getLuminance(string $colour): float
    {
        $hex = ltrim($colour, '#');
        if (strlen($hex) <= 4) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }

        $luminance = 0.0;
        foreach ([0.2126, 0.7152, 0.0722] as $index => $weight) {
            $channel = hexdec(substr($hex, $index * 2, 2)) / 255;
            $channel = $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            $luminance += $weight * $channel;
        }
        return $luminance;
    }

    /**
     * The first of $fileNames in the brand folder, or null when it has none of them.
     * @param string[] $fileNames
     */
    public static function findFile(string $brandFolder, array $fileNames): ?string
    {
        foreach ($fileNames as $fileName) {
            $file = rtrim($brandFolder, '/') . '/' . $fileName;
            if (is_file($file)) {
                return $file;
            }
        }
        return null;
    }

    /**
     * The canvas elements: the logo top right, the icon as a faint watermark, the time, the date and a line.
     * A logo that is null is left out.
     */
    public static function getElements(array $colours, ?Media $logo, ?Media $icon): array
    {
        $elements = [];
        if ($logo !== null) {
            $elements[] = self::imageElement($logo, 'logo', 1661, 58, 189, 120, 1, 100);
        }
        if ($icon !== null) {
            $elements[] = self::imageElement($icon, 'icon', -320, 11, 1186, 1128, 2, 20);
        }
        $clockColour = self::getClockColour($colours['brand-primary']);
        $elements[] = self::dateElement('time', 1120, 459, 380, 80, 3, 'H:i', $clockColour, 150);
        $elements[] = self::dateElement('date', 1020, 571, 600, 80, 4, 'l, d F Y', $colours['brand-accent'], 50);
        $elements[] = [
            'id' => 'line',
            'elementName' => '',
            'elementId' => 'element_line_default',
            'type' => 'global',
            'left' => 532,
            'top' => 880,
            'width' => 1388,
            'height' => 250,
            'layer' => 6,
            'rotation' => 0,
            'properties' => [
                ['id' => 'lineWidth', 'value' => 2],
                ['id' => 'lineColor', 'value' => $colours['brand-accent']],
                ['id' => 'lineStyle', 'value' => 'solid'],
                ['id' => 'tip1Type', 'value' => 'squared'],
                ['id' => 'tip2Type', 'value' => 'squared'],
            ],
            'isVisible' => true,
            'effect' => 'noTransition',
            'mediaName' => 'Line',
        ];

        return [['elements' => $elements]];
    }

    /**
     * Adds the first of $fileNames in the brand folder to the library, reusing a media item of the same name.
     * @param string[] $fileNames
     * @throws GeneralException
     */
    private function addLogo(string $brandFolder, array $fileNames, string $name, int $ownerId): ?Media
    {
        $file = self::findFile($brandFolder, $fileNames);
        if ($file === null) {
            $this->logger->warning('Default Layout: the brand folder has none of ' . implode(', ', $fileNames)
                . ', so the layout is built without the ' . $name . ' image.');
            return null;
        }

        // A retry reuses the image an earlier attempt saved, but never another user's media of the same name.
        $existing = $this->mediaFactory->query(null, [
            'disableUserCheck' => 1,
            'nameExact' => $name,
            'type' => 'image',
            'ownerId' => $ownerId,
            'allModules' => 1,
        ]);
        if (count($existing) > 0) {
            return $existing[0];
        }

        // Saving moves the file out of temp, so the brand's own file is copied there first.
        $tempName = 'default_layout_' . bin2hex(random_bytes(8)) . '.png';
        $tempPath = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/' . $tempName;
        if (!copy($file, $tempPath)) {
            throw new GeneralException('Unable to copy ' . $file . ' to ' . $tempPath);
        }

        try {
            $media = $this->mediaFactory->create($name, $tempName, 'image', $ownerId, self::DURATION);
            $media->folderId = 1;
            $media->permissionsFolderId = 1;
            $media->enableStat = $this->config->getSetting('MEDIA_STATS_ENABLED_DEFAULT');
            $media->save();
            return $media;
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Stores the brand's layout-thumbnail.png as the layout's thumbnail, when it has one. Storing moves the file, so
     * a copy is stored.
     */
    private function storeThumbnail(Layout $layout, string $brandFolder): void
    {
        $file = self::findFile($brandFolder, [self::THUMBNAIL_FILE]);
        if ($file === null) {
            return;
        }

        $tempPath = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/default_layout_thumb_'
            . bin2hex(random_bytes(8)) . '.png';
        try {
            if (copy($file, $tempPath)) {
                $layout->storeThumbnail($tempPath);
            } else {
                $this->logger->error('Default Layout: unable to copy ' . $file . ' to ' . $tempPath);
            }
        } finally {
            @unlink($tempPath);
        }
    }

    private static function imageElement(
        Media $media,
        string $key,
        int $left,
        int $top,
        int $width,
        int $height,
        int $layer,
        int $opacity
    ): array {
        return [
            'id' => 'global_library_image',
            'elementName' => '',
            'elementId' => 'element_global_library_image_' . $key,
            'type' => 'global',
            'left' => $left,
            'top' => $top,
            'width' => $width,
            'height' => $height,
            'layer' => $layer,
            'rotation' => 0,
            'properties' => [
                ['id' => 'opacity', 'value' => $opacity],
                ['id' => 'objectFit', 'value' => 'contain'],
                ['id' => 'alignId', 'value' => 'center'],
                ['id' => 'valignId', 'value' => 'middle'],
                ['id' => 'roundBorder', 'value' => 0],
                ['id' => 'borderRadius', 'value' => 20],
                ['id' => 'imageShadow', 'value' => 0],
                ['id' => 'shadowX', 'value' => 1],
                ['id' => 'shadowY', 'value' => 1],
                ['id' => 'shadowBlur', 'value' => 2],
            ],
            'isVisible' => true,
            'effect' => 'noTransition',
            'mediaId' => $media->mediaId,
            'mediaName' => $media->name,
        ];
    }

    private static function dateElement(
        string $key,
        int $left,
        int $top,
        int $width,
        int $height,
        int $layer,
        string $format,
        string $colour,
        int $fontSize
    ): array {
        return [
            'id' => 'date_advanced',
            'elementName' => '',
            'elementId' => 'element_date_advanced_' . $key,
            'type' => 'global',
            'left' => $left,
            'top' => $top,
            'width' => $width,
            'height' => $height,
            'layer' => $layer,
            'rotation' => 0,
            'properties' => [
                ['id' => 'currentDate', 'value' => 1],
                ['id' => 'date', 'value' => ''],
                ['id' => 'dateFormat', 'value' => $format],
                ['id' => 'lang', 'value' => ''],
                ['id' => 'fontFamily', 'value' => ''],
                ['id' => 'fontColor', 'value' => $colour],
                ['id' => 'fitToArea', 'value' => 1],
                ['id' => 'useGradient', 'value' => 0],
                ['id' => 'gradient', 'value' => '{"type":"linear","color1":"#222222","color2":"#eee","angle":0}'],
                ['id' => 'fontSize', 'value' => $fontSize],
                ['id' => 'lineHeight', 'value' => 1.2],
                ['id' => 'bold', 'value' => 0],
                ['id' => 'italics', 'value' => 0],
                ['id' => 'underline', 'value' => 0],
                ['id' => 'textWrap', 'value' => 1],
                ['id' => 'showOverflow', 'value' => 1],
                ['id' => 'textShadow', 'value' => 0],
                ['id' => 'textShadowColor', 'value' => ''],
                ['id' => 'shadowX', 'value' => 1],
                ['id' => 'shadowY', 'value' => 1],
                ['id' => 'shadowBlur', 'value' => 2],
                ['id' => 'horizontalAlign', 'value' => 'center'],
                ['id' => 'verticalAlign', 'value' => 'center'],
            ],
            'isVisible' => true,
            'effect' => 'noTransition',
            'mediaName' => 'Date / Time',
        ];
    }
}
