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

use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Xibo\Entity\HelpLink;

/**
 * Class HelpService
 * @package Xibo\Service
 */
class HelpService implements HelpServiceInterface
{
    /** @var string */
    private string $helpBase;

    private ?array $links = null;

    /**
     * @inheritdoc
     */
    public function __construct($helpBase)
    {
        $this->helpBase = $helpBase;
    }

    public function getLandingPage(): string
    {
        return $this->helpBase;
    }

    public function getLinksForPage(string $pageName): array
    {
        if ($this->links === null) {
            $this->loadLinks();
        }
        return $this->links[$pageName] ?? [];
    }

    private function loadLinks(): void
    {
        // Load links from file.
        try {
            if (file_exists(PROJECT_ROOT . '/custom/help-links.yaml')) {
                $links = (array)Yaml::parseFile(PROJECT_ROOT . '/custom/help-links.yaml');
            } else if (file_exists(PROJECT_ROOT . '/help-links.yaml')) {
                $links = (array)Yaml::parseFile(PROJECT_ROOT . '/help-links.yaml');
            } else {
                $this->links = [];
                return;
            }
        } catch (\Exception) {
            return;
        }

        // Parse links.
        $this->links = [];

        foreach ($links as $pageName => $page) {
            // New page
            $this->links[$pageName] = [];

            foreach ($page as $link) {
                $helpLink = new HelpLink($link);
                if (!Str::startsWith($helpLink->url, ['http://', 'https://'])) {
                    $helpLink->url = $this->helpBase . $helpLink->url;
                }

                $this->links[$pageName][] = $helpLink;
            }
        }
    }
}
