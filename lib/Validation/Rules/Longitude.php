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


namespace Xibo\Validation\Rules;


use Respect\Validation\Rules\AbstractRule;

/**
 * Class Longitude
 * @package Xibo\Validation\Rules
 */
class Longitude extends AbstractRule
{
    /**
     * @param $input
     * @return bool
     */
    public function validate($input): bool
    {
        if (!is_numeric($input)) {
            return false;
        }

        $longitude = doubleval($input);

        return ($longitude >= -180 && $longitude <= 180);
    }
}
