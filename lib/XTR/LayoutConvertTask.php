<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2018 Spring Signage Ltd
 * (LayoutConvertTask.php)
 */


namespace Xibo\XTR;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Helper\Environment;

/**
 * Class LayoutConvertTask
 * @package Xibo\XTR
 */
class LayoutConvertTask implements TaskInterface
{
    use TaskTrait;

    /** @var PermissionFactory */
    private $permissionFactory;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var \Xibo\Factory\ModuleFactory */
    private $moduleFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->permissionFactory = $container->get('permissionFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->moduleFactory = $container->get('moduleFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        // lklayoutmedia is removed at the end of this task
        if (!$this->store->exists('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :name', [
            'name' => 'lklayoutmedia'
        ])) {
            $this->appendRunMessage('Already converted');

            // Disable the task
            $this->disableTask();

            // Don't do anything further
            return;
        }

        // Permissions handling
        // -------------------
        // Layout permissions should remain the same
        // the lklayoutmediagroup table and lklayoutregiongroup table will be removed
        // We do not have simple switch for the lklayoutmediagroup table as these are supposed to represent "Widgets"
        // which did not exist at this point.
        // Build a keyed array of existing widget permissions
        $mediaPermissions = [];
        foreach ($this->store->select('
                SELECT `lklayoutmediagroup`.groupId, `lkwidgetmedia`.widgetId, `view`, `edit`, `del` 
                  FROM `lklayoutmediagroup`
                    INNER JOIN `lkwidgetmedia`
                    ON `lklayoutmediagroup`.`mediaId` = `lkwidgetmedia`.widgetId
                 WHERE `lkwidgetmedia`.widgetId IN (
                     SELECT widget.widgetId
                       FROM `widget`
                        INNER JOIN `playlist`
                        ON `playlist`.playlistId = `widget`.playlistId
                      WHERE `playlist`.regionId = `lklayoutmediagroup`.regionId
                 )
            ', []) as $row) {
            $permission = $this->permissionFactory->create(
                $row['groupId'],
                Widget::class,
                $row['widgetId'],
                $row['view'],
                $row['edit'],
                $row['del']
            );

            $mediaPermissions[$row['mediaId']] = $permission;
        }

        // Build a keyed array of existing region permissions
        $regionPermissions = [];
        foreach ($this->store->select('SELECT groupId, layoutId, regionId, `view`, `edit`, `del` FROM `lklayoutregiongroup`', []) as $row) {
            $permission = $this->permissionFactory->create(
                $row['groupId'],
                Region::class,
                $row['regionId'],
                $row['view'],
                $row['edit'],
                $row['del']
            );

            $regionPermissions[$row['regionId']] = $permission;
        }

        // Get the library location to store backups of existing XLF
        $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION');

        // We need to go through each layout, save the XLF as a backup in the library and then upgrade it.
        // This task applies to Layouts which are schemaVersion 2 or lower. xibosignage/xibo#2056
        foreach ($this->store->select('SELECT layoutId, xml FROM `layout` WHERE schemaVersion <= :schemaVersion', [
            'schemaVersion' => 2
        ]) as $oldLayout) {

            $oldLayoutId = intval($oldLayout['layoutId']);

            try {
                // Does this layout have any XML associated with it? If not, then it is an empty layout.
                if (empty($oldLayout['xml'])) {
                    // This is frankly, odd, so we better log it
                    $this->log->critical('Layout upgrade without any existing XLF, i.e. empty. ID = ' . $oldLayoutId);

                    // Pull out the layout record, and set some best guess defaults
                    $layout = $this->layoutFactory->getById($oldLayoutId);

                    // We have to guess something here as we do not have any XML to go by. Default to landscape 1080p.
                    $layout->width = 1920;
                    $layout->height = 1080;

                } else {
                    // Save off a copy of the XML in the library
                    file_put_contents($libraryLocation . 'archive_' . $oldLayoutId . '.xlf', $oldLayout['xml']);

                    // Create a new layout from the XML
                    $layout = $this->layoutFactory->loadByXlf($oldLayout['xml'], $this->layoutFactory->getById($oldLayoutId));
                }

                // We need one final pass through all widgets on the layout so that we can set the durations properly.
                foreach ($layout->getWidgets() as $widget) {
                    $module = $this->moduleFactory->createWithWidget($widget);
                    $widget->calculateDuration($module, true);

                    // Get global stat setting of widget to set to on/off/inherit
                    $widget->setOptionValue('enableStat', 'attrib', $this->config->getSetting('WIDGET_STATS_ENABLED_DEFAULT'));
                }

                // Save the layout
                $layout->schemaVersion = Environment::$XLF_VERSION;
                $layout->save(['notify' => false, 'audit' => false]);

                // Now that we have new ID's we need to cross reference them with the old IDs and recreate the permissions
                foreach ($layout->regions as $region) {
                    /* @var \Xibo\Entity\Region $region */
                    if (array_key_exists($region->tempId, $regionPermissions)) {
                        $permission = $regionPermissions[$region->tempId];
                        /* @var \Xibo\Entity\Permission $permission */
                        // Double check we are for the same layout
                        if ($permission->objectId == $layout->layoutId) {
                            $permission = clone $permission;
                            $permission->objectId = $region->regionId;
                            $permission->save();
                        }
                    }

                    /* @var \Xibo\Entity\Playlist $playlist */
                    foreach ($region->getPlaylist()->widgets as $widget) {
                        /* @var \Xibo\Entity\Widget $widget */
                        if (array_key_exists($widget->tempId, $mediaPermissions)) {
                            $permission = $mediaPermissions[$widget->tempId];
                            /* @var \Xibo\Entity\Permission $permission */
                            if ($permission->objectId == $layout->layoutId && $region->tempId == $permission->objectIdString) {
                                $permission = clone $permission;
                                $permission->objectId = $widget->widgetId;
                                $permission->save();
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->appendRunMessage('Error upgrading Layout, this should be checked post-upgrade. ID: ' . $oldLayoutId);
                $this->log->critical('Error upgrading Layout, this should be checked post-upgrade. ID: ' . $oldLayoutId);
                $this->log->error($e->getMessage() . ' - ' . $e->getTraceAsString());
            }
        }

        $this->appendRunMessage('Finished converting, dropping unnecessary tables.');

        // Drop the permissions
        $this->store->update('DROP TABLE `lklayoutmediagroup`;', []);
        $this->store->update('DROP TABLE `lklayoutregiongroup`;', []);
        $this->store->update('DROP TABLE lklayoutmedia', []);
        $this->store->update('ALTER TABLE `layout` DROP `xml`;', []);

        // Disable the task
        $this->disableTask();

        $this->appendRunMessage('Conversion Completed');
    }

    /**
     * Disables and saves this task immediately
     */
    private function disableTask()
    {
        $this->getTask()->isActive = 0;
        $this->getTask()->save();
        $this->store->commitIfNecessary();
    }
}