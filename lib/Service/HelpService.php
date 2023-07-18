<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Service;

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
        if (file_exists(PROJECT_ROOT . '/custom/help-links.json')) {
            $links = json_decode(file_get_contents(PROJECT_ROOT . '/custom/help-links.json'), true);
        } else if (file_exists(PROJECT_ROOT . '/help-links.json')) {
            // TODO: pull these in from the manual on build.
            $links = json_decode(file_get_contents(PROJECT_ROOT . '/help-links.json'), true);
        } else {
            $this->links = [];
            return;
        }

        // Parse links.
        foreach ($links as $pageName => $page) {
            foreach ($page as $link) {
                $this->links[$pageName][] = new HelpLink($link);
            }
        }
    }
}
