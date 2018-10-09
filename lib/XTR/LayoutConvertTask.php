<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2018 Spring Signage Ltd
 * (LayoutConvertTask.php)
 */


namespace Xibo\XTR;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\PermissionFactory;

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

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->permissionFactory = $container->get('permissionFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        // lklayoutmedia is removed at the end of this task
        if (!$this->store->exists('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :name', [
            'schema' => $_SERVER['MYSQL_DATABASE'],
            'name' => 'lklayoutmedia'
        ])) {
            return;
        }

        // Build a keyed array of existing widget permissions
        $mediaPermissions = [];
        foreach ($this->store->select('SELECT groupId, layoutId, regionId, mediaId, `view`, `edit`, `del` FROM `lklayoutmediagroup`', []) as $row) {
            $permission = $this->permissionFactory->createEmpty();
            $permission->entityId = 6; // Widget
            $permission->groupId = $row['groupId'];
            $permission->objectId = $row['layoutId'];
            $permission->objectIdString = $row['regionId'];
            $permission->view = $row['view'];
            $permission->edit = $row['edit'];
            $permission->delete = $row['del'];

            $mediaPermissions[$row['mediaId']] = $permission;
        }

        // Build a keyed array of existing region permissions
        $regionPermissions = [];
        foreach ($this->store->select('SELECT groupId, layoutId, regionId, `view`, `edit`, `del` FROM `lklayoutregiongroup`', []) as $row) {
            $permission = $this->permissionFactory->createEmpty();
            $permission->entityId = 7; // Widget
            $permission->groupId = $row['groupId'];
            $permission->objectId = $row['layoutId'];
            $permission->view = $row['view'];
            $permission->edit = $row['edit'];
            $permission->delete = $row['del'];

            $regionPermissions[$row['regionId']] = $permission;
        }

        // Get the library location to store backups of existing XLF
        $libraryLocation = $this->config->GetSetting('LIBRARY_LOCATION');

        // We need to go through each layout, save the XLF as a backup in the library and then upgrade it.
        foreach ($this->store->select('SELECT layoutId, xml FROM `layout`', []) as $oldLayout) {

            $oldLayoutId = intval($oldLayout['layoutId']);

            try {
                // Does this layout have any XML associated with it? If not, then it is an empty layout.
                if (empty($oldLayout['xml'])) {
                    // This is frankly, odd, so we better log it
                    $this->log->critical('Layout upgrade without any existing XLF, i.e. empty. ID = ' . $oldLayoutId);

                    // Pull out the layout record, and set some best guess defaults
                    $layout = $this->layoutFactory->getById($oldLayoutId);
                    $layout->schemaVersion = 2;
                    $layout->width = 1920;
                    $layout->height = 1080;

                } else {
                    // Save off a copy of the XML in the library
                    file_put_contents($libraryLocation . 'archive_' . $oldLayoutId . '.xlf', $oldLayout['xml']);

                    // Create a new layout from the XML
                    $layout = $this->layoutFactory->loadByXlf($oldLayout['xml'], $this->layoutFactory->getById($oldLayoutId));
                }

                // Save the layout
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
                $this->log->critical('Error upgrading Layout, this should be checked post-upgrade. ID: ' . $oldLayoutId);
                $this->log->error($e->getMessage() . ' - ' . $e->getTraceAsString());
            }
        }

        // Drop the permissions
        $this->store->update('DROP TABLE `lklayoutmediagroup`;', []);
        $this->store->update('DROP TABLE `lklayoutregiongroup`;', []);
        $this->store->update('DROP TABLE lklayoutmedia', []);
        $this->store->update('ALTER TABLE `layout` DROP `xml`;', []);

        // Disable the task
        $this->getTask()->isActive = 0;

        $this->appendRunMessage('Conversion Completed');
    }
}