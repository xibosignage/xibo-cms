<?php 

require_once("3rdparty/jquery-file-upload/UploadHandler.php");

class XiboUploadHandler extends UploadHandler {
	
	protected function handle_form_data($file, $index) {
        // Handle form data, e.g. $_REQUEST['description'][$index]
        
        // Link the file to the module
        $name = $_REQUEST['name'][$index];
        $duration = $_REQUEST['duration'][$index];

        $layoutId = Kit::GetParam('layoutid', _REQUEST, _INT);
        $type = Kit::GetParam('type', _REQUEST, _WORD);

        Debug::LogEntry('audit', 'Upload complete for Type: ' . $type . ' and file name: ' . $file->name . '. Name: ' . $name . '. Duration:' . $duration);

        // We want to create a module for each of the uploaded files.
        // Do not pass in the region ID so that we only assign to the library and not to the layout
        try {
            $module = ModuleFactory::createForLibrary($type, $layoutId, $this->options['db'], $this->options['user']);
        }
        catch (Exception $e) {
            $file->error = $e->getMessage();
            exit();
        }

        // We want to add this item to our library
        if (!$storedAs = $module->AddLibraryMedia($file->name, $name, $duration, $file->name)) {
            $file->error = $module->GetErrorMessage();
        }

        // Set new file details
        $file->storedas = $storedAs;

        // Delete the file
        @unlink($this->get_upload_path($file->name));
    }
}