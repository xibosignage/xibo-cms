<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\XTR;

/**
 * Converts a white-label override.css (produced by the old white-label builder)
 * into the new theme.css format used by the React frontend.
 *
 * Extracts --brand-primary from the sidebar/navbar background color and
 * --brand-accent from sidebar hover border highlights. Self-disables after a
 * successful conversion. Re-enable the task manually after a theme update.
 */
class ThemeCssMigrateTask implements TaskInterface
{
    use TaskTrait;

    /** @inheritdoc */
    public function setFactories($container)
    {
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Theme CSS Migration') . PHP_EOL . PHP_EOL;

        $theme = basename($this->getConfig()->getSetting('GLOBAL_THEME_NAME', 'default'));

        if ($theme === 'default') {
            $this->appendRunMessage(__('No custom theme active, nothing to convert.'));
            return;
        }

        $overridePath = PROJECT_ROOT . '/web/theme/custom/' . $theme . '/css/override.css';

        if (!file_exists($overridePath)) {
            $this->appendRunMessage(sprintf(
                __('override.css not found at theme/custom/%s/css/override.css, waiting.'),
                $theme
            ));
            return;
        }

        $css = file_get_contents($overridePath);

        $primary = $this->extractPrimary($css) ?? '#3f7fff';
        $accent  = $this->extractAccent($css)  ?? '#eb7857';

        $themeCssDir  = PROJECT_ROOT . '/web/theme/custom/' . $theme . '/css/';
        $themeCssPath = $themeCssDir . 'theme.css';

        if (!is_dir($themeCssDir)) {
            mkdir($themeCssDir, 0755, true);
        }

        file_put_contents($themeCssPath, $this->buildThemeCss($primary, $accent));

        $this->appendRunMessage(sprintf(
            __('Converted override.css to theme.css for theme "%s" (primary: %s, accent: %s).'),
            $theme,
            $primary,
            $accent
        ));

        $this->getTask()->isActive = 0;
        $this->getTask()->save();

        $this->appendRunMessage(__('Done.'));
    }

    /**
     * Extract the primary brand color from the override.css.
     * Checks sidebar/navbar background properties in priority order.
     */
    private function extractPrimary(string $css): ?string
    {
        $candidates = [
            // Sidebar wrapper — the main nav background in 4.4
            ['selector' => '#sidebar-wrapper',    'properties' => ['background-color', 'background']],
            ['selector' => '\.sidebar-wrapper',   'properties' => ['background-color', 'background']],
            ['selector' => '#sidebar',             'properties' => ['background-color', 'background']],
            ['selector' => '\.sidebar(?![-_])',    'properties' => ['background-color', 'background']],
            // Top header bar — same color in most themes
            ['selector' => '\.row\.header',        'properties' => ['background-color', 'background']],
            // Horizontal nav fallback
            ['selector' => '\.navbar-default(?![\w-])', 'properties' => ['background-color', 'background']],
        ];

        foreach ($candidates as $candidate) {
            $color = $this->extractColor($css, $candidate['selector'], $candidate['properties']);
            if ($color !== null) {
                return $color;
            }
        }

        return null;
    }

    /**
     * Extract the accent brand color from the override.css.
     * Checks sidebar hover border highlights and link colors in priority order.
     */
    private function extractAccent(string $css): ?string
    {
        $candidates = [
            // Sidebar active-item accent stripe — most distinctive accent in v43
            [
                'selector' => 'ul\.sidebar\s+\.sidebar-list\s+a:hover',
                'properties' => ['border-left', 'border-left-color'],
            ],
            [
                'selector' => 'ul\.sidebar\s+\.sidebar-list\s+a\.sidebar-list-selected',
                'properties' => ['border-left', 'border-left-color'],
            ],
            // Navbar open/active accent
            [
                'selector' => '\.navbar-default\s+\.navbar-nav\s*>\s*\.open\s*>\s*a',
                'properties' => ['border-bottom', 'border-bottom-color'],
            ],
            // Anchor color as last-resort accent (^|\n ensures we match the standalone `a` rule, not `.class a`)
            ['selector' => '(?:^|\n)a(?:[\s,{])', 'properties' => ['color']],
        ];

        foreach ($candidates as $candidate) {
            $color = $this->extractColor($css, $candidate['selector'], $candidate['properties']);
            if ($color !== null) {
                return $color;
            }
        }

        return null;
    }

    /**
     * Extract the first hex color value for any of the given properties
     * within the first CSS block matching the selector regex.
     *
     * Handles background shorthands (e.g. `background: #hex url(...)`)
     * and border shorthands (e.g. `border-left: 3px solid #hex`).
     *
     * @param string   $css            Raw CSS content
     * @param string   $selectorRegex  Regex fragment matching the selector
     * @param string[] $properties     CSS property names to look for, in priority order
     * @return string|null  Lowercase hex color, or null if not found
     */
    private function extractColor(string $css, string $selectorRegex, array $properties): ?string
    {
        // Match the first rule block whose selector contains our pattern
        if (!preg_match('/' . $selectorRegex . '[^{]*\{([^}]+)\}/is', $css, $blockMatch)) {
            return null;
        }

        $block = $blockMatch[1];

        foreach ($properties as $property) {
            // Match the property value, stripping !important and trailing spaces
            if (!preg_match(
                '/\b' . preg_quote($property, '/') . '\s*:\s*([^;]+?)\s*(?:!important\s*)?;/i',
                $block,
                $valueMatch
            )) {
                continue;
            }

            $value = trim($valueMatch[1]);

            // Extract a 3- or 6-digit hex color from the value
            // (handles shorthands like "3px solid #e99d1a" or "url(...) #hex")
            if (preg_match('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', $value, $hexMatch)) {
                return strtolower($hexMatch[0]);
            }

            // Extract rgb(r, g, b) and convert to hex
            if (preg_match('/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i', $value, $rgbMatch)) {
                return sprintf('#%02x%02x%02x', (int)$rgbMatch[1], (int)$rgbMatch[2], (int)$rgbMatch[3]);
            }
        }

        return null;
    }

    /**
     * Build the theme.css content with the extracted brand tokens.
     */
    private function buildThemeCss(string $primary, string $accent): string
    {
        return <<<CSS
/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Generated by the Theme CSS Migration task from override.css.
 * To regenerate (e.g. after a theme update), re-enable the
 * "Theme CSS Migration" task in the task schedule.
 */

:root {
  --brand-primary: {$primary};
  --brand-accent:  {$accent};
}
CSS;
    }
}
