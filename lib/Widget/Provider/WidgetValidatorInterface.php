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
