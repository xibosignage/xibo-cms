<?php

namespace Xibo\Helper;

use Exception;
use Xibo\Entity\Permission;
use Xibo\Entity\Widget;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\WidgetFactory;

class XiboUploadHandler extends BlueImpUploadHandler
{
    protected function handle_form_data($file, $index)
    {
        // Handle form data, e.g. $_REQUEST['description'][$index]
        // Link the file to the module
        $fileName = $file->name;
        $name = $_REQUEST['name'][$index];

        // The media name might be empty here, because the user isn't forced to select it
        $name = ($name == '') ? $fileName : $name;

        // Set the name to the one we have selected
        $file->name = $name;

        Log::debug('Upload complete for name: ' . $fileName . '. Name: ' . $name . '.');

        // Upload and Save
        try {
            // Guess the type
            $module = ModuleFactory::getByExtension(strtolower(substr(strrchr($fileName, '.'), 1)));

            Log::debug('Module Type = %s', $module);

            // Add the Media
            $media = MediaFactory::create($name, $fileName, $module->type, $this->options['userId']);

            // Old Media Id or not?
            if ($this->options['oldMediaId'] != 0) {
                // Load old media
                $oldMedia = MediaFactory::getById($this->options['oldMediaId']);

                // Set the old record to edited
                $oldMedia->isEdited = 1;
                $oldMedia->save(false);

                // Reset the name
                $media->name = $oldMedia->name;

                // Save
                $media->save();

                foreach (PermissionFactory::getAllByObjectId('Media', $oldMedia->mediaId) as $permission) {
                    /* @var Permission $permission */
                    $permission = clone $permission;
                    $permission->objectId = $media->mediaId;
                    $permission->save();
                }

                // Do we want to replace this in all layouts?
                if ($this->options['replaceInAllLayouts'] == 1) {
                    foreach (WidgetFactory::getByMediaId($media->mediaId) as $widget) {
                        /* @var Widget $widget */
                        $widget->unassignMedia($oldMedia->mediaId);
                        $widget->assignMedia($media->mediaId);
                        $widget->save();
                    }
                }

                // We either want to Link the old record to this one, or delete it
                if ($this->options['replaceInAllLayouts'] == 1 && $this->options['deleteOldRevisions'] == 1) {
                    $oldMedia->delete();
                } else {
                    $oldMedia->parentId = $media->mediaId;
                    $oldMedia->save(false);
                }
            } else {
                // Save
                $media->save();
            }

            // Get the storedAs valid for return
            $file->storedas = $media->storedAs;

            // Fonts, then install
            if ($module->type == 'font') {
                $controller = $this->options['controller'];
                /* @var \Xibo\Controller\Library $controller */
                $controller->installFonts();
            }

            // Are we assigning to a Playlist?
            if ($this->options['playlistId'] != 0) {
                Log::debug('Assigning uploaded media to playlistId ' . $this->options['playlistId']);

                // Get the Playlist
                $playlist = PlaylistFactory::getById($this->options['playlistId']);

                // Create a Widget and add it to our region
                $widget = WidgetFactory::create($this->options['userId'], $playlist->playlistId, $module->type, 10);
                $widget->assignMedia($media->mediaId);

                // Assign the new widget to the playlist
                $playlist->assignWidget($widget);

                // Save the playlist
                $playlist->save();
            }
        } catch (Exception $e) {
            $file->error = $e->getMessage();
        }
    }
}