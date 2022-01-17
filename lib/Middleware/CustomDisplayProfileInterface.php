<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Middleware;

use Xibo\Entity\Display;
use Xibo\Entity\DisplayProfile;
use Xibo\Support\Sanitizer\SanitizerInterface;

interface CustomDisplayProfileInterface
{
    /**
     * This function should return an array with default Display Profile config.
     * If requested type does not match the custom Display Profile in the middleware an empty array should be returned.
     *
     * @param string $type
     * @return array
     */
    public function registerCustomDisplayProfile(string $type) : array;

    /**
     * This function should return full name, including extension (.twig) to the custom display profile edit form
     * the file is expected to be in the /custom folder along the custom Middleware.
     * To match naming convention twig file should be called displayprofile-form-edit-<type>.twig
     *
     * @param string $type
     * @return string
     */
    public function getCustomEditTemplate(string $type) : string;

    /**
     * This function should return the Custom Display Profile type
     * @return string
     */
    public function getProfileType() : string;

    /**
     * This function handles any changes to the default Display Profile settings, as well as overrides per Display.
     * Each editable setting should have handling here.
     *
     * @param DisplayProfile $displayProfile
     * @param SanitizerInterface $sanitizedParams
     * @param array|null $config
     * @param Display|null $display
     * @return array
     */
    public function editCustomConfigFields(DisplayProfile $displayProfile, SanitizerInterface $sanitizedParams, ?array $config, ?Display $display) : array;

}
