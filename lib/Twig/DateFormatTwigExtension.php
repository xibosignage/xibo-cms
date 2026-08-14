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

namespace Xibo\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Class DateFormatTwigExtension
 * @package Xibo\Twig
 *
 * Registers the `datehms` Twig filter, which formats a duration given in seconds as HH:mm:ss.
 */
class DateFormatTwigExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('datehms', [$this, 'dateFormat'])
        ];
    }

    /**
     * @param int $seconds
     * @return string formatted as HH:mm:ss
     */
    public function dateFormat($seconds)
    {
        return gmdate('H:i:s', $seconds);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'datehms';
    }
}
