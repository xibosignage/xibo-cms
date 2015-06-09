<?php

namespace Xibo\Helper;

use Exception;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
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
            $media->save();

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
                $playlist->widgets[] = $widget;

                // Save the playlist
                $playlist->save();
            }
        } catch (Exception $e) {
            $file->error = $e->getMessage();
        }
    }
}