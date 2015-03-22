<?php

use Xibo\Helper\Log;

require_once('3rdparty/jquery-file-upload/UploadHandler.php');

class XiboUploadHandler extends UploadHandler
{
	protected function handle_form_data($file, $index)
    {
        // Handle form data, e.g. $_REQUEST['description'][$index]
        
        // Link the file to the module
        $name = $_REQUEST['name'][$index];

        // The media name might be empty here, because the user isn't forced to select it
        if ($name == '')
            $name = $file->name;

        Log::Audit('Upload complete for name: ' . $file->name . '. Name: ' . $name . '.');

        // Upload and Save
        try {
            // Guess the type
            $module = \Xibo\Factory\ModuleFactory::getByExtension(strtolower(substr(strrchr($file->name, '.'), 1)));

            // Add the Media
            $mediaObject = new Media();
            if (!$mediaId = $mediaObject->Add($file->name, $module->type, $name, 0, $file->name, $this->options['userId'])) {
                throw new Exception($mediaObject->GetErrorMessage());
            }

            // Get the storedAs valid for return
            $file->storedas = $mediaObject->GetStoredAs($mediaId);

            // Fonts, then install
            if ($module->type == 'font') {
                $mediaObject->installFonts();
            }

            // Are we assigning to a Playlist?
            if ($this->options['playlistId'] != 0) {
                Log::Audit('Assigning uploaded media to playlistId ' . $this->options['playlistId']);

                // Get the Playlist
                $playlist = \Xibo\Factory\PlaylistFactory::getById($this->options['playlistId']);

                // Create a Widget and add it to our region
                $widget = \Xibo\Factory\WidgetFactory::create($this->options['userId'], $playlist->playlistId, $module->type, 10);
                $widget->assignMedia($mediaId);

                // Assign the new widget to the playlist
                $playlist->widgets[] = $widget;

                // Save the playlist
                $playlist->save();
            }
        }
        catch (Exception $e) {
            $file->error = $e->getMessage();
            exit();
        }
    }


}