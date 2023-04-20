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
use Xibo\Entity\Widget;

/**
 * Widget Compatibility Interface should be implemented by custom widget upgrade classes.
 * Takes necessary actions to make the existing widgets from v3 compatible with v4.
 *
 * The schema from and the schema to (currently set to 1 and 2, respectively).
 * It also provides a method to save a template to the library in a sub-folder named templates/. This method
 * is called whenever a widget is loaded with a different schema version.
 *
 */
interface WidgetCompatibilityInterface
{
    public function getLog(): LoggerInterface;

    public function setLog(LoggerInterface $logger): WidgetCompatibilityInterface;

    /**
     * Upgrade the given widget to be compatible with the specified schema version.
     *
     * @param Widget $widget The widget model to upgrade.
     * @param int $fromSchema The version of the schema the widget is currently using.
     * @param int $toSchema The version of the schema to upgrade the widget to.
     * @return bool Whether the upgrade was successful
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): bool;

    /**
     * Save the given widget template to the templates/ subfolder.
     *
     * @param string $template The widget template to save.
     * @param string $fileName The file name to save the template as.
     * @return bool Returns true if the template was saved successfully, false otherwise.
     */
    public function saveTemplate(string $template, string $fileName): bool;
}
