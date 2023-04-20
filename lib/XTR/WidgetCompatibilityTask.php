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

namespace Xibo\XTR;

use Xibo\Entity\Task;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class WidgetCompatibilityTask
 * Run only once when upgrading widget from v3 to v4
 * @package Xibo\XTR
 */
class WidgetCompatibilityTask implements TaskInterface
{
    use TaskTrait;

    /** @var \Xibo\Factory\ModuleFactory */
    private $moduleFactory;

    /** @var \Xibo\Factory\WidgetFactory */
    private $widgetFactory;

    /** @var \Xibo\Factory\LayoutFactory */
    private $layoutFactory;

    /** @var \Xibo\Factory\playlistFactory */
    private $playlistFactory;

    /** @var \Xibo\Factory\TaskFactory */
    private $taskFactory;

    /** @var array The cache for layout */
    private $layoutCache = [];

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->moduleFactory = $container->get('moduleFactory');
        $this->widgetFactory = $container->get('widgetFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->playlistFactory = $container->get('playlistFactory');
        $this->taskFactory = $container->get('taskFactory');
        return $this;
    }

    /** @inheritdoc
     */
    public function run()
    {
        // This task should only be run once when upgrading for the first time from v3 to v4.
        // If the Widget Compatibility class is defined, it needs to be executed to upgrade the widgets.
        $this->runMessage = '# ' . __('Widget Compatibility') . PHP_EOL . PHP_EOL;

        $widgets = $this->widgetFactory->query(null, [
            'schemaVersion' => 1
        ]);

        $countWidgets = 0;
        foreach ($widgets as $widget) {
            // Load the widget
            $widget->load();

            // Form conditions from the widget's option and value, e.g, templateId==worldclock1
            $widgetConditionMatch = [];
            foreach ($widget->widgetOptions as $option) {
                $widgetConditionMatch[] = $option->option . '==' . $option->value;
            }

            // Get module
            try {
                $module = $this->moduleFactory->getByType($widget->type, $widgetConditionMatch);
            } catch (NotFoundException $notFoundException) {
                $this->log->error('Module not found for widget: ' . $widget->type);
                $this->appendRunMessage('Upgrade widget error for widgetId: : '. $widget->widgetId);
                continue;
            }

            if ($module->isWidgetCompatibilityAvailable()) {
                $countWidgets++;

                // Grab a widget compatibility interface, if there is one
                $widgetCompatibilityInterface = $module->getWidgetCompatibilityOrNull();
                if ($widgetCompatibilityInterface !== null) {
                    $this->log->debug('WidgetCompatibilityTask: widgetId ' . $widget->widgetId);
                    try {
                        $upgraded = $widgetCompatibilityInterface->upgradeWidget($widget, $widget->schemaVersion, 2);
                        if ($upgraded) {
                            $widget->schemaVersion = 2;
                            $widget->save(['alwaysUpdate'=>true]);
                        }
                    } catch (\Exception $e) {
                        $this->log->error('Failed to upgrade for widgetId: ' . $widget->widgetId .
                            ', message: ' . $e->getMessage());
                        $this->appendRunMessage('Upgrade widget error for widgetId: : '. $widget->widgetId);
                    }
                }

                try {
                    // Get the layout of the widget and set it to rebuild.
                    $playlist = $this->playlistFactory->getById($widget->playlistId);
                    $playlist->notifyLayouts();
                } catch (\Exception $e) {
                    $this->log->error('Failed to set layout rebuild for widgetId: ' . $widget->widgetId .
                        ', message: ' . $e->getMessage());
                    $this->appendRunMessage('Layout rebuild error for widgetId: : '. $widget->widgetId);
                }
            }
        }

        // Get Widget Compatibility Task
        $compatibilityTask = $this->taskFactory->getByClass('\Xibo\XTR\\WidgetCompatibilityTask');

        // Mark the task as disabled if it is active
        if ($compatibilityTask->isActive == 1) {
            $compatibilityTask->isActive = 0;
            $compatibilityTask->save();
            $this->store->commitIfNecessary();

            $this->appendRunMessage('Disabling widget compatibility task.');
            $this->log->debug('Disabling widget compatibility task.');
        }
        $this->log->info('Total widgets upgraded: '. $countWidgets);
        $this->appendRunMessage('Total widgets upgraded: '. $countWidgets);
    }
}
