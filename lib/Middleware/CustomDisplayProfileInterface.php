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
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Sanitizer\SanitizerInterface;

interface CustomDisplayProfileInterface
{
    /**
     * Return Display Profile type
     * @return string
     */
    public static function getType():string;

    /**
     * Return Display Profile name
     * @return string
     */
    public static function getName():string;

    /**
     * This function should return an array with default Display Profile config.
     *
     * @param ConfigServiceInterface $configService
     * @return array
     */
    public static function getDefaultConfig(ConfigServiceInterface $configService) : array;

    /**
     * This function should return full name, including extension (.twig) to the custom display profile edit form
     * the file is expected to be in the /custom folder along the custom Middleware.
     * To match naming convention twig file should be called displayprofile-form-edit-<type>.twig
     * This will be done automatically from the CustomDisplayProfileMiddlewareTrait.
     *
     * If you have named your twig file differently, override getCustomEditTemplate function in your middleware
     * @return string
     */
    public static function getCustomEditTemplate() : string;

    /**
     * This function handles any changes to the default Display Profile settings, as well as overrides per Display.
     * Each editable setting should have handling here.
     *
     * @param DisplayProfile $displayProfile
     * @param SanitizerInterface $sanitizedParams
     * @param array|null $config
     * @param Display|null $display
     * @param LogServiceInterface $logService
     * @return array
     */
    public static function editCustomConfigFields(DisplayProfile $displayProfile, SanitizerInterface $sanitizedParams, ?array $config, ?Display $display, LogServiceInterface $logService) : array;
}
