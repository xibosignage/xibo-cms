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

/**
 * Resolves this CMS's brand: its names and texts, and where each brand image is served from.
 */
interface BrandServiceInterface
{
    public const SLOT_LOGO = 'logo';
    public const SLOT_LOGO_DARK = 'logo-dark';
    public const SLOT_LOGO_ICON = 'logo-icon';
    public const SLOT_FAVICON = 'favicon';

    /**
     * Brand names and texts; a key is null when the brand doesn't set it.
     * @return array{productName: ?string, appName: ?string, supportUrl: ?string, cmsSourceUrl: ?string,
     *     aboutText: ?string, removeLicenceFromLogin: ?bool}
     */
    public function getConfig(): array;

    /**
     * The URL a browser loads a brand image from.
     * @param string $slot one of the SLOT_* constants
     */
    public function getAssetUrl(string $slot): string;

    /**
     * The URL of the brand stylesheet, or null when the brand has none.
     */
    public function getStylesheetUrl(): ?string;

    /**
     * The brand colours to set as CSS variables in the page, by variable name without the leading dashes.
     * @return array<string, string>
     */
    public function getTokens(): array;

    /**
     * The local file or URL of a brand image, for server-side rendering such as PDFs, or null when it has none.
     * @param string $slot one of the SLOT_* constants
     */
    public function getAssetPath(string $slot): ?string;
}
