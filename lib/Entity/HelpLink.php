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

namespace Xibo\Entity;

/**
 * A simple help link, used by the help service.
 */
class HelpLink implements \JsonSerializable
{
    public $title;
    public $summary;
    public $url;
    public $isAllowWhiteLabel;

    /**
     * @param $array
     */
    public function __construct($array)
    {
        $this->title = $array['title'] ?? '';
        $this->summary = $array['summary'] ?? '';
        $this->url = $array['url'] ?? '';
        $this->isAllowWhiteLabel = $array['isAllowWhiteLabel'] ?? true;
    }

    public function jsonSerialize(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'url' => $this->url,
            'isAllowWhiteLabel' => $this->isAllowWhiteLabel,
        ];
    }
}
