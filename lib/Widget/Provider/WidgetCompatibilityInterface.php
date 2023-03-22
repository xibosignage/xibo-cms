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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\Widget;

/**
 * The Widget Compatibility Interface
 */
interface WidgetCompatibilityInterface
{
    public function getLog(): LoggerInterface;
    public function setLog(LoggerInterface $logger): WidgetCompatibilityInterface;

    /**
     * Get the event dispatcher
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public function getDispatcher(): EventDispatcherInterface;

    /**
     * Set the event dispatcher
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $logger
     * @return \Xibo\Widget\Provider\WidgetProviderInterface
     */
    public function setDispatcher(EventDispatcherInterface $logger): WidgetCompatibilityInterface;

    /**
     * Upgrade the given widget to be compatible with the specified schema version.
     *
     * @param Widget $widget The widget model to upgrade.
     * @param int $fromSchema The version of the schema the widget is currently using.
     * @param int $toSchema The version of the schema to upgrade the widget to.
     * @return void
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): void;

    /**
     * Save the given widget template to the templates/ subfolder.
     *
     * @param string $template The widget template to save.
     * @param string $fileName The file name to save the template as.
     * @return bool Returns true if the template was saved successfully, false otherwise.
     */
    public function saveTemplate(string $template, string $fileName): bool;
}
