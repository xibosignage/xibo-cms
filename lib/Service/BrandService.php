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

use Xibo\Support\Exception\InvalidArgumentException;

/**
 * The brand kept in the library's brand folder, served by the /brand alias.
 */
class BrandService implements BrandServiceInterface
{
    private const URL_PREFIX = '/brand/';

    /** The colour tokens a brand can set, as CSS variable names. */
    public const TOKENS = ['brand-primary', 'brand-accent'];

    private ?array $config = null;

    public function __construct(private readonly string $libraryLocation)
    {
    }

    /** @inheritDoc */
    public function getConfig(): array
    {
        if ($this->config === null) {
            $this->config = self::readConfigFile($this->libraryLocation === '' ? '' : $this->folder() . 'config.json');
        }
        return $this->config;
    }

    /**
     * Reads a brand config.json into the getConfig() shape; all null when the file is missing.
     */
    public static function readConfigFile(string $configFile): array
    {
        $raw = [];
        if ($configFile !== '' && file_exists($configFile)) {
            $raw = json_decode(file_get_contents($configFile), true) ?? [];
        }

        // Accepts both the current keys and the legacy PHP theme keys (theme_title, app_name, ...).
        // An empty name or support URL keeps Xibo's default.
        return [
            'productName'            => ($raw['productName']  ?? $raw['theme_title'] ?? null) ?: null,
            'appName'                => ($raw['appName']      ?? $raw['app_name']    ?? null) ?: null,
            'supportUrl'             => ($raw['supportUrl']   ?? $raw['theme_url']   ?? null) ?: null,
            'cmsSourceUrl'           => $raw['cmsSourceUrl'] ?? $raw['cms_source_url'] ?? null,
            'aboutText'              => $raw['aboutText']    ?? null,
            'removeLicenceFromLogin' => isset($raw['removeLicenceFromLogin'])
                ? (bool)$raw['removeLicenceFromLogin']
                : null,
        ];
    }

    /**
     * The colour tokens set in a theme.css, and whether it holds any other CSS.
     * @return array{0: array<string, string>, 1: bool}
     */
    public static function readThemeTokens(string $css): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $tokens = [];
        foreach (self::TOKENS as $token) {
            if (preg_match('/--' . preg_quote($token, '/') . '\s*:\s*([^;}]+)/', $css, $match)) {
                $tokens[$token] = trim($match[1]);
                $css = str_replace($match[0], '', $css);
            }
        }
        // Whatever is left beyond an empty :root block is CSS the tokens don't cover.
        $rest = preg_replace('/:root\s*\{[\s;]*\}/', '', $css);
        return [$tokens, trim($rest) !== ''];
    }

    /** @inheritDoc */
    public function getAssetUrl(string $slot): string
    {
        return self::URL_PREFIX . $this->fileName($slot);
    }

    /** @inheritDoc */
    public function getStylesheetUrl(): ?string
    {
        return self::URL_PREFIX . 'theme.css';
    }

    /** @inheritDoc */
    public function getTokens(): array
    {
        // The stylesheet sets the colours.
        return [];
    }

    /** @inheritDoc */
    public function getAssetPath(string $slot): ?string
    {
        return $this->folder() . $this->fileName($slot);
    }

    /**
     * The file in the brand folder behind a slot, preferring SVG over PNG.
     * @throws InvalidArgumentException for an unknown slot
     */
    private function fileName(string $slot): string
    {
        return match ($slot) {
            self::SLOT_LOGO, self::SLOT_LOGO_ICON => $this->svgOrPng($slot),
            self::SLOT_LOGO_DARK => $this->firstExisting(['logo-dark.svg', 'logo-dark.png'])
                ?? $this->svgOrPng(self::SLOT_LOGO),
            self::SLOT_FAVICON => 'favicon.ico',
            default => throw new InvalidArgumentException('Unknown brand slot ' . $slot, 'slot'),
        };
    }

    private function svgOrPng(string $base): string
    {
        return $this->firstExisting([$base . '.svg']) ?? $base . '.png';
    }

    private function firstExisting(array $fileNames): ?string
    {
        foreach ($fileNames as $fileName) {
            if (file_exists($this->folder() . $fileName)) {
                return $fileName;
            }
        }
        return null;
    }

    private function folder(): string
    {
        return rtrim($this->libraryLocation, '/') . '/brand/';
    }
}
