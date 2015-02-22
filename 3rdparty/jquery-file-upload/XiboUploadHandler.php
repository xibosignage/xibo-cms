<?php 

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

        Debug::Audit('Upload complete for name: ' . $file->name . '. Name: ' . $name . '.');

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
        }
        catch (Exception $e) {
            $file->error = $e->getMessage();
            exit();
        }
    }
}