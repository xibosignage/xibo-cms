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

namespace Xibo\Widget\Provider;

use Psr\Log\LoggerInterface;
use Xibo\Entity\Module;
use Xibo\Entity\Widget;

/**
 * Widget Validator Interface
 * --------------------------
 * Used to validate the properties of a module after it all of its individual properties and those of its
 * template have been validated via their property rules.
 */
interface WidgetValidatorInterface
{
    public function getLog(): LoggerInterface;

    public function setLog(LoggerInterface $logger): WidgetValidatorInterface;

    /**
     * Validate the widget provided
     * @param Module $module The Module
     * @param Widget $widget The Widget - this is read only
     * @param string $stage Which stage are we validating, either `save` or `status`
     */
    public function validate(Module $module, Widget $widget, string $stage): void;
}
