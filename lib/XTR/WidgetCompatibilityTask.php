<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

use Xibo\Support\Exception\GeneralException;
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
    /** @var \Xibo\Factory\RegionFactory */
    private $regionFactory;

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
        $this->regionFactory = $container->get('regionFactory');
        return $this;
    }

    /** @inheritdoc
     */
    public function run()
    {
        // This task should only be run once when upgrading for the first time from v3 to v4.
        // If the Widget Compatibility class is defined, it needs to be executed to upgrade the widgets.
        $this->runMessage = '# ' . __('Widget Compatibility') . PHP_EOL . PHP_EOL;

        // Get all modules
        $modules = $this->moduleFactory->getAll();

        // For each module we should get all widgets which are < the schema version of the module installed, and
        // upgrade them to the schema version of the module installed
        foreach ($modules as $module) {
            // Run upgrade - Part 1
            // Upgrade a widget having the same module type
            $this->getLogger()->debug('run: finding widgets for ' . $module->type
                . ' with schema version less than ' . $module->schemaVersion);

            $statement = $this->executeStatement($module->type, $module->schemaVersion);
            $this->upgradeWidget($statement);

            // Run upgrade - Part 2
            // Upgrade a widget having the old style module type/legacy type
            $legacyTypes = [];
            if (count($module->legacyTypes) > 0) {
                // Get the name of the module legacy types
                $legacyTypes = array_column($module->legacyTypes, 'name'); // TODO Make this efficient
            }

            // Get module legacy type and update matched widgets
            foreach ($legacyTypes as $legacyType) {
                $statement = $this->executeStatement($legacyType, $module->schemaVersion);
                $this->upgradeWidget($statement);
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
        $this->appendRunMessage(__('Done.'. PHP_EOL));
    }

    /**
     *
     * @param string $type
     * @param int $version
     * @return false|\PDOStatement
     */
    private function executeStatement(string $type, int $version): bool|\PDOStatement
    {
        $sql = '
          SELECT widget.widgetId
          FROM `widget` 
          WHERE `widget`.`type` = :type
          and  `widget`.schemaVersion < :version
        ';
        $connection = $this->store->getConnection();
        $connection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        // Prepare the statement
        $statement = $connection->prepare($sql);

        // Execute
        $statement->execute([
            'type' => $type,
            'version' => $version
        ]);

        return $statement;
    }

    private function upgradeWidget(\PDOStatement $statement): void
    {
        // Load each widget and its options
        // Then run upgrade
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            try {
                $widget = $this->widgetFactory->getById((int) $row['widgetId']);
                $widget->loadMinimum();

                $this->log->debug('WidgetCompatibilityTask: Get Widget: ' . $row['widgetId']);

                // Form conditions from the widget's option and value, e.g, templateId==worldclock1
                $widgetConditionMatch = [];
                foreach ($widget->widgetOptions as $option) {
                    $widgetConditionMatch[] = $option->option . '==' . $option->value;
                }

                // Get module
                try {
                    $module = $this->moduleFactory->getByType($widget->type, $widgetConditionMatch);
                } catch (NotFoundException $e) {
                    $this->log->error('Module not found for widget: ' . $widget->type);
                    $this->appendRunMessage('Module not found for widget: '. $widget->widgetId);
                    continue;
                }

                // Run upgrade
                if ($module->isWidgetCompatibilityAvailable()) {
                    // Grab a widget compatibility interface, if there is one
                    $widgetCompatibilityInterface = $module->getWidgetCompatibilityOrNull();
                    if ($widgetCompatibilityInterface !== null) {
                        try {
                            // Pass the widget through the compatibility interface.
                            $upgraded = $widgetCompatibilityInterface->upgradeWidget(
                                $widget,
                                $widget->schemaVersion,
                                $module->schemaVersion
                            );

                            // Save widget version
                            if ($upgraded) {
                                $widget->schemaVersion = $module->schemaVersion;

                                // Assert the module type, unless the widget has already changed it.
                                if (!$widget->hasPropertyChanged('type')) {
                                    $widget->type = $module->type;
                                }

                                $widget->save(['alwaysUpdate' => true, 'upgrade' => true]);
                                $this->log->debug('WidgetCompatibilityTask: Upgraded');
                            }
                        } catch (\Exception $e) {
                            $this->log->error('Failed to upgrade for widgetId: ' . $widget->widgetId .
                                ', message: ' . $e->getMessage());
                            $this->appendRunMessage('Failed to upgrade for widgetId: : '. $widget->widgetId);
                        }
                    }

                    try {
                        // Get the layout of the widget and set it to rebuild.
                        $playlist = $this->playlistFactory->getById($widget->playlistId);

                        // check if the Widget was assigned to a region playlist
                        if ($playlist->isRegionPlaylist()) {
                            $playlist->load();
                            $region = $this->regionFactory->getById($playlist->regionId);

                            // set the region type accordingly
                            if ($region->isDrawer === 1) {
                                $regionType = 'drawer';
                            } else if (count($playlist->widgets) === 1 && $widget->type !== 'subplaylist') {
                                $regionType = 'frame';
                            } else if (count($playlist->widgets) === 0) {
                                $regionType = 'zone';
                            } else {
                                $regionType = 'playlist';
                            }

                            $region->type = $regionType;
                            $region->save(['notify' => false]);
                        }
                        $playlist->notifyLayouts();
                    } catch (\Exception $e) {
                        $this->log->error('Failed to set layout rebuild for widgetId: ' . $widget->widgetId .
                            ', message: ' . $e->getMessage());
                        $this->appendRunMessage('Layout rebuild error for widgetId: : '. $widget->widgetId);
                    }
                } else {
                    $this->getLogger()->debug('upgradeWidget: no compatibility task available for ' . $widget->type);
                }
            } catch (GeneralException $e) {
                $this->log->debug($e->getTraceAsString());
                $this->log->error('WidgetCompatibilityTask: Cannot process widget');
            }
        }

        $this->store->commitIfNecessary();
    }
}
