<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Spring Signage Ltd
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

use Xibo\Entity\Region;
use Xibo\Exception\XiboException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ModuleFactory;

/**
 * Class WidgetSyncTask
 * @package Xibo\XTR
 */
class WidgetSyncTask implements TaskInterface
{
    use TaskTrait;

    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->moduleFactory = $container->get('moduleFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        // Get an array of modules to use
        $modules = $this->moduleFactory->get();

        $currentLayoutId = 0;
        $layout = null;
        $countWidgets = 0;
        $countLayouts = 0;
        $widgetsDone = [];

        $sql = '
          SELECT requiredfile.itemId, requiredfile.displayId 
            FROM `requiredfile` 
              INNER JOIN `layout`
              ON layout.layoutId = requiredfile.itemId
              INNER JOIN `display`
              ON display.displayId = requiredfile.displayId
           WHERE requiredfile.type = \'L\' 
            AND display.loggedIn = 1
          ORDER BY itemId, displayId
        ';

        $smt = $this->store->getConnection()->prepare($sql);
        $smt->execute();

        // Track the total time we've spent caching (excluding all other operations, etc)
        $timeCaching = 0.0;

        // Get a list of Layouts which are currently active, along with the display they are active on
        // get the widgets from each layout and call get resource on them
        while ($row = $smt->fetch(\PDO::FETCH_ASSOC)) {

            try {
                // We have a Layout
                $layoutId = (int)$row['itemId'];
                $displayId = (int)$row['displayId'];

                $this->log->debug('Found layout to keep in sync ' . $layoutId);

                if ($layoutId !== $currentLayoutId) {
                    $countLayouts++;

                    // Add a little break in here
                    if ($currentLayoutId !== 0) {
                        usleep(10000);
                    }

                    // We've changed layout
                    // load in the new one
                    $layout = $this->layoutFactory->getById($layoutId);
                    $layout->load();

                    // Update pointer
                    $currentLayoutId = $layoutId;

                    // Clear out the list of widgets we've done
                    $widgetsDone = [];
                }

                // Load the layout XML and work out if we have any ticker / text / dataset media items
                foreach ($layout->regions as $region) {
                    /* @var Region $region */
                    $playlist = $region->getPlaylist();
                    $playlist->setModuleFactory($this->moduleFactory);

                    foreach ($playlist->expandWidgets() as $widget) {
                        // See if we have a cache
                        if ($widget->type == 'ticker' ||
                            $widget->type == 'text' ||
                            $widget->type == 'datasetview' ||
                            $widget->type == 'webpage' ||
                            $widget->type == 'embedded' ||
                            $modules[$widget->type]->renderAs == 'html'
                        ) {
                            $countWidgets++;

                            // Make me a module from the widget
                            $module = $this->moduleFactory->createWithWidget($widget, $region);

                            // Have we done this widget before?
                            if (in_array($widget->widgetId, $widgetsDone) && !$module->isCacheDisplaySpecific()) {
                                $this->log->debug('This widgetId ' . $widget->widgetId . ' has been done before and is not display specific, so we skip');
                                continue;
                            }

                            // Record start time
                            $startTime = microtime(true);

                            // Cache the widget
                            $module->getResourceOrCache($displayId);

                            // Record we have done this widget
                            $widgetsDone[] = $widget->widgetId;

                            // Record end time and aggregate for final total
                            $duration = (microtime(true) - $startTime);
                            $timeCaching = $timeCaching + $duration;

                            $this->log->debug('Took ' . $duration . ' seconds to check and/or cache widgetId ' . $widget->widgetId . ' for displayId ' . $displayId);

                            // Commit so that any images we've downloaded have their cache times updated for the next request
                            // this makes sense because we've got a file cache that is already written out.
                            $this->store->commitIfNecessary();
                        }
                    }
                }
            } catch (XiboException $xiboException) {
                // Log and skip to the next layout
                $this->log->debug($xiboException->getTraceAsString());
                $this->log->error('Cannot process layoutId ' . $layoutId . ', E = ' . $xiboException->getMessage());
            }
        }

        $this->log->info('Total time spent caching is ' . $timeCaching);

        $this->appendRunMessage('Synced ' . $countWidgets . ' widgets across ' . $countLayouts . ' layouts.');
    }
}